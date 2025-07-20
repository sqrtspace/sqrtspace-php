<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SqrtSpace\SpaceTime\Streams\SpaceTimeStream;
use SqrtSpace\SpaceTime\File\CsvExporter;
use SqrtSpace\SpaceTime\Checkpoint\CheckpointManager;

class ProductController extends Controller
{
    private ProductService $productService;
    
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }
    
    /**
     * Get paginated products
     */
    public function index(Request $request)
    {
        $perPage = min($request->get('per_page', 50), 100);
        
        return Product::query()
            ->when($request->get('category'), function ($query, $category) {
                $query->where('category', $category);
            })
            ->when($request->get('min_price'), function ($query, $minPrice) {
                $query->where('price', '>=', $minPrice);
            })
            ->orderBy('id')
            ->paginate($perPage);
    }
    
    /**
     * Stream all products as NDJSON
     */
    public function stream(Request $request)
    {
        return response()->stream(function () use ($request) {
            $query = Product::query()
                ->when($request->get('category'), function ($query, $category) {
                    $query->where('category', $category);
                })
                ->orderBy('id');
            
            $stream = SpaceTimeStream::fromQuery($query, 100);
            
            foreach ($stream as $product) {
                echo $product->toJson() . "\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache'
        ]);
    }
    
    /**
     * Export products as CSV
     */
    public function exportCsv(Request $request)
    {
        $filename = 'products_' . date('Y-m-d_His') . '.csv';
        
        return response()->streamDownload(function () use ($request) {
            $exporter = new CsvExporter('php://output');
            $exporter->writeHeaders([
                'ID', 'Name', 'SKU', 'Category', 'Price', 
                'Stock', 'Description', 'Created At'
            ]);
            
            Product::query()
                ->when($request->get('category'), function ($query, $category) {
                    $query->where('category', $category);
                })
                ->orderBy('id')
                ->chunkById(1000, function ($products) use ($exporter) {
                    foreach ($products as $product) {
                        $exporter->writeRow([
                            $product->id,
                            $product->name,
                            $product->sku,
                            $product->category,
                            $product->price,
                            $product->stock,
                            $product->description,
                            $product->created_at
                        ]);
                    }
                });
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
    
    /**
     * Bulk update product prices with checkpointing
     */
    public function bulkUpdatePrices(Request $request)
    {
        $request->validate([
            'category' => 'required|string',
            'adjustment_type' => 'required|in:percentage,fixed',
            'adjustment_value' => 'required|numeric'
        ]);
        
        $jobId = 'price_update_' . uniqid();
        $checkpointManager = app(CheckpointManager::class);
        
        // Check for existing checkpoint
        $checkpoint = $checkpointManager->restore($jobId);
        $lastId = $checkpoint['last_id'] ?? 0;
        $updated = $checkpoint['updated'] ?? 0;
        
        DB::beginTransaction();
        
        try {
            Product::where('category', $request->category)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->chunkById(100, function ($products) use ($request, &$updated, $jobId, $checkpointManager) {
                    foreach ($products as $product) {
                        if ($request->adjustment_type === 'percentage') {
                            $product->price *= (1 + $request->adjustment_value / 100);
                        } else {
                            $product->price += $request->adjustment_value;
                        }
                        $product->save();
                        $updated++;
                        
                        // Checkpoint every 100 updates
                        if ($updated % 100 === 0) {
                            $checkpointManager->save($jobId, [
                                'last_id' => $product->id,
                                'updated' => $updated
                            ]);
                        }
                    }
                });
            
            DB::commit();
            $checkpointManager->delete($jobId);
            
            return response()->json([
                'success' => true,
                'updated' => $updated,
                'job_id' => $jobId
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'job_id' => $jobId,
                'can_resume' => true
            ], 500);
        }
    }
    
    /**
     * Search products with memory-efficient sorting
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'sort_by' => 'in:relevance,price_asc,price_desc,name'
        ]);
        
        return $this->productService->searchProducts(
            $request->get('q'),
            $request->get('sort_by', 'relevance'),
            $request->get('limit', 100)
        );
    }
    
    /**
     * Get product statistics
     */
    public function statistics()
    {
        return $this->productService->getStatistics();
    }
}