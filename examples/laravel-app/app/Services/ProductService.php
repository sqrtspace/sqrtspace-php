<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use SqrtSpace\SpaceTime\Algorithms\ExternalSort;
use SqrtSpace\SpaceTime\Algorithms\ExternalGroupBy;
use SqrtSpace\SpaceTime\Collections\SpaceTimeArray;
use SqrtSpace\SpaceTime\Memory\MemoryPressureMonitor;

class ProductService
{
    private MemoryPressureMonitor $memoryMonitor;
    
    public function __construct()
    {
        $this->memoryMonitor = new MemoryPressureMonitor(
            config('spacetime.memory_limit', '128M')
        );
    }
    
    /**
     * Search products with memory-efficient sorting
     */
    public function searchProducts(string $query, string $sortBy, int $limit): Collection
    {
        // Get all matching products
        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->get()
            ->map(function ($product) use ($query) {
                // Calculate relevance score
                $nameScore = $this->calculateRelevance($product->name, $query) * 2;
                $descScore = $this->calculateRelevance($product->description, $query);
                $product->relevance_score = $nameScore + $descScore;
                return $product;
            });
        
        // Use external sort for large result sets
        if ($products->count() > 1000) {
            $sorted = $this->externalSort($products, $sortBy);
        } else {
            $sorted = $this->inMemorySort($products, $sortBy);
        }
        
        return collect($sorted)->take($limit);
    }
    
    /**
     * Get product statistics using external grouping
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_products' => Product::count(),
            'total_value' => 0,
            'by_category' => [],
            'price_ranges' => [],
            'stock_alerts' => []
        ];
        
        // Use SpaceTimeArray for memory efficiency
        $allProducts = new SpaceTimeArray(1000);
        
        Product::chunk(1000, function ($products) use (&$allProducts) {
            foreach ($products as $product) {
                $allProducts[] = [
                    'category' => $product->category,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'value' => $product->price * $product->stock
                ];
            }
        });
        
        // Calculate total value
        $stats['total_value'] = array_sum(array_column($allProducts->toArray(), 'value'));
        
        // Group by category using external grouping
        $byCategory = ExternalGroupBy::groupBySum(
            $allProducts->toArray(),
            fn($p) => $p['category'],
            fn($p) => $p['value']
        );
        $stats['by_category'] = $byCategory;
        
        // Price range distribution
        $priceRanges = [
            '0-50' => 0,
            '50-100' => 0,
            '100-500' => 0,
            '500+' => 0
        ];
        
        foreach ($allProducts as $product) {
            if ($product['price'] < 50) {
                $priceRanges['0-50']++;
            } elseif ($product['price'] < 100) {
                $priceRanges['50-100']++;
            } elseif ($product['price'] < 500) {
                $priceRanges['100-500']++;
            } else {
                $priceRanges['500+']++;
            }
            
            // Low stock alerts
            if ($product['stock'] < 10) {
                $stats['stock_alerts'][] = [
                    'category' => $product['category'],
                    'stock' => $product['stock']
                ];
            }
        }
        
        $stats['price_ranges'] = $priceRanges;
        $stats['memory_usage'] = $this->memoryMonitor->getMemoryInfo();
        
        return $stats;
    }
    
    /**
     * Import products from CSV with progress tracking
     */
    public function importFromCsv(string $filePath, callable $progressCallback = null): array
    {
        $imported = 0;
        $errors = [];
        $batchSize = 100;
        $batch = [];
        
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle); // Skip headers
        
        while (($row = fgetcsv($handle)) !== false) {
            try {
                $batch[] = [
                    'name' => $row[0],
                    'sku' => $row[1],
                    'category' => $row[2],
                    'price' => (float)$row[3],
                    'stock' => (int)$row[4],
                    'description' => $row[5] ?? '',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                if (count($batch) >= $batchSize) {
                    Product::insert($batch);
                    $imported += count($batch);
                    $batch = [];
                    
                    if ($progressCallback) {
                        $progressCallback($imported);
                    }
                    
                    // Check memory pressure
                    if ($this->memoryMonitor->shouldCleanup()) {
                        gc_collect_cycles();
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Row " . ($imported + 1) . ": " . $e->getMessage();
            }
        }
        
        // Insert remaining batch
        if (!empty($batch)) {
            Product::insert($batch);
            $imported += count($batch);
        }
        
        fclose($handle);
        
        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }
    
    private function calculateRelevance(string $text, string $query): float
    {
        $text = strtolower($text);
        $query = strtolower($query);
        
        // Exact match
        if (strpos($text, $query) !== false) {
            return 1.0;
        }
        
        // Word match
        $words = explode(' ', $query);
        $matches = 0;
        foreach ($words as $word) {
            if (strpos($text, $word) !== false) {
                $matches++;
            }
        }
        
        return $matches / count($words);
    }
    
    private function externalSort(Collection $products, string $sortBy): array
    {
        $sortKey = match($sortBy) {
            'price_asc' => fn($p) => $p->price,
            'price_desc' => fn($p) => -$p->price,
            'name' => fn($p) => $p->name,
            default => fn($p) => -$p->relevance_score
        };
        
        return ExternalSort::sortBy($products->toArray(), $sortKey);
    }
    
    private function inMemorySort(Collection $products, string $sortBy): Collection
    {
        return match($sortBy) {
            'price_asc' => $products->sortBy('price'),
            'price_desc' => $products->sortByDesc('price'),
            'name' => $products->sortBy('name'),
            default => $products->sortByDesc('relevance_score')
        };
    }
}