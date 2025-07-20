<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Models\OrderAnalytics;
use SqrtSpace\SpaceTime\Checkpoint\CheckpointManager;
use SqrtSpace\SpaceTime\Memory\MemoryPressureMonitor;
use SqrtSpace\SpaceTime\Collections\SpaceTimeArray;

class ProcessLargeDataset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $jobId;
    private CheckpointManager $checkpointManager;
    private MemoryPressureMonitor $memoryMonitor;
    
    public function __construct(string $jobId = null)
    {
        $this->jobId = $jobId ?? 'process_dataset_' . uniqid();
    }
    
    public function handle()
    {
        $this->checkpointManager = app(CheckpointManager::class);
        $this->memoryMonitor = new MemoryPressureMonitor('64M');
        
        // Restore checkpoint if exists
        $checkpoint = $this->checkpointManager->restore($this->jobId);
        $state = $checkpoint ?? [
            'last_order_id' => 0,
            'processed_count' => 0,
            'analytics' => [
                'total_revenue' => 0,
                'order_count' => 0,
                'customers' => new SpaceTimeArray(1000),
                'products' => new SpaceTimeArray(1000),
                'daily_stats' => []
            ]
        ];
        
        $this->processOrders($state);
        
        // Clean up checkpoint after successful completion
        $this->checkpointManager->delete($this->jobId);
        
        // Save final analytics
        $this->saveAnalytics($state['analytics']);
    }
    
    private function processOrders(array &$state)
    {
        $lastOrderId = $state['last_order_id'];
        
        Order::where('id', '>', $lastOrderId)
            ->with(['customer', 'items.product'])
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$state) {
                foreach ($orders as $order) {
                    // Process order
                    $this->processOrder($order, $state['analytics']);
                    
                    $state['processed_count']++;
                    $state['last_order_id'] = $order->id;
                    
                    // Checkpoint every 100 orders
                    if ($state['processed_count'] % 100 === 0) {
                        $this->saveCheckpoint($state);
                        
                        // Check memory pressure
                        if ($this->memoryMonitor->shouldCleanup()) {
                            // Flush some analytics to database
                            $this->flushPartialAnalytics($state['analytics']);
                        }
                    }
                }
            });
    }
    
    private function processOrder(Order $order, array &$analytics)
    {
        // Update totals
        $analytics['total_revenue'] += $order->total_amount;
        $analytics['order_count']++;
        
        // Track customer spending
        $customerId = $order->customer_id;
        if (!isset($analytics['customers'][$customerId])) {
            $analytics['customers'][$customerId] = [
                'total_spent' => 0,
                'order_count' => 0,
                'last_order_date' => null
            ];
        }
        
        $analytics['customers'][$customerId]['total_spent'] += $order->total_amount;
        $analytics['customers'][$customerId]['order_count']++;
        $analytics['customers'][$customerId]['last_order_date'] = $order->created_at;
        
        // Track product sales
        foreach ($order->items as $item) {
            $productId = $item->product_id;
            if (!isset($analytics['products'][$productId])) {
                $analytics['products'][$productId] = [
                    'quantity_sold' => 0,
                    'revenue' => 0,
                    'order_count' => 0
                ];
            }
            
            $analytics['products'][$productId]['quantity_sold'] += $item->quantity;
            $analytics['products'][$productId]['revenue'] += $item->total_price;
            $analytics['products'][$productId]['order_count']++;
        }
        
        // Daily statistics
        $date = $order->created_at->format('Y-m-d');
        if (!isset($analytics['daily_stats'][$date])) {
            $analytics['daily_stats'][$date] = [
                'revenue' => 0,
                'orders' => 0,
                'unique_customers' => []
            ];
        }
        
        $analytics['daily_stats'][$date]['revenue'] += $order->total_amount;
        $analytics['daily_stats'][$date]['orders']++;
        $analytics['daily_stats'][$date]['unique_customers'][$customerId] = true;
    }
    
    private function saveCheckpoint(array $state)
    {
        $this->checkpointManager->save($this->jobId, [
            'last_order_id' => $state['last_order_id'],
            'processed_count' => $state['processed_count'],
            'analytics' => [
                'total_revenue' => $state['analytics']['total_revenue'],
                'order_count' => $state['analytics']['order_count'],
                'customers' => $state['analytics']['customers'],
                'products' => $state['analytics']['products'],
                'daily_stats' => $state['analytics']['daily_stats']
            ]
        ]);
        
        \Log::info("Checkpoint saved", [
            'job_id' => $this->jobId,
            'processed' => $state['processed_count']
        ]);
    }
    
    private function flushPartialAnalytics(array &$analytics)
    {
        // Save top customers to database
        $topCustomers = $this->getTopItems($analytics['customers'], 'total_spent', 100);
        foreach ($topCustomers as $customerId => $data) {
            OrderAnalytics::updateOrCreate(
                ['type' => 'customer', 'entity_id' => $customerId],
                ['data' => json_encode($data)]
            );
        }
        
        // Save top products
        $topProducts = $this->getTopItems($analytics['products'], 'revenue', 100);
        foreach ($topProducts as $productId => $data) {
            OrderAnalytics::updateOrCreate(
                ['type' => 'product', 'entity_id' => $productId],
                ['data' => json_encode($data)]
            );
        }
        
        // Clear processed items from memory
        $analytics['customers'] = new SpaceTimeArray(1000);
        $analytics['products'] = new SpaceTimeArray(1000);
        
        gc_collect_cycles();
    }
    
    private function getTopItems($items, $sortKey, $limit)
    {
        $sorted = [];
        foreach ($items as $id => $data) {
            $sorted[$id] = $data[$sortKey];
        }
        
        arsort($sorted);
        $topIds = array_slice(array_keys($sorted), 0, $limit);
        
        $result = [];
        foreach ($topIds as $id) {
            $result[$id] = $items[$id];
        }
        
        return $result;
    }
    
    private function saveAnalytics(array $analytics)
    {
        // Save summary
        OrderAnalytics::updateOrCreate(
            ['type' => 'summary', 'entity_id' => 'global'],
            [
                'data' => json_encode([
                    'total_revenue' => $analytics['total_revenue'],
                    'order_count' => $analytics['order_count'],
                    'avg_order_value' => $analytics['total_revenue'] / $analytics['order_count'],
                    'unique_customers' => count($analytics['customers']),
                    'unique_products' => count($analytics['products']),
                    'processed_at' => now()
                ])
            ]
        );
        
        // Save daily stats
        foreach ($analytics['daily_stats'] as $date => $stats) {
            OrderAnalytics::updateOrCreate(
                ['type' => 'daily', 'entity_id' => $date],
                [
                    'data' => json_encode([
                        'revenue' => $stats['revenue'],
                        'orders' => $stats['orders'],
                        'unique_customers' => count($stats['unique_customers']),
                        'avg_order_value' => $stats['revenue'] / $stats['orders']
                    ])
                ]
            );
        }
        
        \Log::info("Analytics processing completed", [
            'job_id' => $this->jobId,
            'total_processed' => $analytics['order_count']
        ]);
    }
}