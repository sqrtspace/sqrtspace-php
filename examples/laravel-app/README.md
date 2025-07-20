# SqrtSpace SpaceTime Laravel Sample Application

This sample demonstrates how to integrate SqrtSpace SpaceTime with a Laravel application to build memory-efficient, scalable web applications.

## Features Demonstrated

### 1. **Large Dataset API Endpoints**
- Streaming JSON responses for large datasets
- Paginated queries with automatic memory management
- CSV export without memory bloat

### 2. **Background Job Processing**
- Memory-aware queue workers
- Checkpointed long-running jobs
- Batch processing with progress tracking

### 3. **Caching with SpaceTime**
- Hot/cold cache tiers
- Automatic memory pressure handling
- Cache warming strategies

### 4. **Real-World Use Cases**
- User activity log processing
- Sales report generation
- Product catalog management
- Real-time analytics

## Installation

1. **Install dependencies:**
```bash
composer install
```

2. **Configure environment:**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Configure SpaceTime in `.env`:**
```
SPACETIME_MEMORY_LIMIT=256M
SPACETIME_EXTERNAL_STORAGE=/tmp/spacetime
SPACETIME_CHUNK_STRATEGY=sqrt_n
SPACETIME_ENABLE_CHECKPOINTING=true
```

4. **Run migrations:**
```bash
php artisan migrate
php artisan db:seed
```

## Project Structure

```
laravel-app/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ProductController.php      # Streaming APIs
│   │   │   ├── AnalyticsController.php    # Real-time analytics
│   │   │   └── ReportController.php       # Large report generation
│   │   └── Middleware/
│   │       └── SpaceTimeMiddleware.php    # Memory monitoring
│   ├── Jobs/
│   │   ├── ProcessLargeDataset.php       # Checkpointed job
│   │   ├── GenerateReport.php            # Batch processing job
│   │   └── ImportProducts.php            # CSV import job
│   ├── Services/
│   │   ├── ProductService.php            # Business logic
│   │   ├── AnalyticsService.php         # Analytics processing
│   │   └── SpaceTimeCache.php           # Cache wrapper
│   └── Providers/
│       └── SpaceTimeServiceProvider.php  # Service registration
├── config/
│   └── spacetime.php                     # Configuration
├── routes/
│   ├── api.php                          # API routes
│   └── web.php                          # Web routes
└── tests/
    └── Feature/
        └── SpaceTimeTest.php            # Integration tests
```

## Usage Examples

### 1. Streaming Large Datasets

```php
// ProductController.php
public function stream()
{
    return response()->stream(function () {
        $products = SpaceTimeStream::fromQuery(
            Product::query()->orderBy('id')
        );
        
        echo "[";
        $first = true;
        
        foreach ($products->chunk(100) as $chunk) {
            foreach ($chunk as $product) {
                if (!$first) echo ",";
                echo $product->toJson();
                $first = false;
            }
            
            // Flush output buffer
            ob_flush();
            flush();
        }
        
        echo "]";
    }, 200, [
        'Content-Type' => 'application/json',
        'X-Accel-Buffering' => 'no'
    ]);
}
```

### 2. Memory-Efficient CSV Export

```php
// ReportController.php
public function exportCsv()
{
    $filename = 'products_' . date('Y-m-d') . '.csv';
    
    return response()->streamDownload(function () {
        $exporter = new CsvExporter('php://output');
        $exporter->writeHeaders(['ID', 'Name', 'Price', 'Stock']);
        
        Product::query()
            ->orderBy('id')
            ->chunkById(1000, function ($products) use ($exporter) {
                foreach ($products as $product) {
                    $exporter->writeRow([
                        $product->id,
                        $product->name,
                        $product->price,
                        $product->stock
                    ]);
                }
            });
    }, $filename, [
        'Content-Type' => 'text/csv',
    ]);
}
```

### 3. Checkpointed Background Job

```php
// ProcessLargeDataset.php
class ProcessLargeDataset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SpaceTimeCheckpointable;
    
    public function handle()
    {
        $checkpoint = $this->getCheckpoint();
        $lastId = $checkpoint['last_id'] ?? 0;
        
        Order::where('id', '>', $lastId)
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    // Process order
                    $this->processOrder($order);
                    
                    // Save checkpoint every 100 orders
                    if ($order->id % 100 === 0) {
                        $this->saveCheckpoint([
                            'last_id' => $order->id,
                            'processed' => $this->processed,
                        ]);
                    }
                }
            });
    }
}
```

### 4. Real-Time Analytics

```php
// AnalyticsController.php
public function realtime()
{
    return response()->stream(function () {
        $monitor = new MemoryPressureMonitor('100M');
        
        while (true) {
            $stats = $this->analyticsService->getCurrentStats();
            
            // Send as Server-Sent Event
            echo "data: " . json_encode($stats) . "\n\n";
            ob_flush();
            flush();
            
            // Check memory pressure
            if ($monitor->check() !== MemoryPressureLevel::NONE) {
                $this->analyticsService->compact();
            }
            
            sleep(1);
        }
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no'
    ]);
}
```

### 5. Memory-Aware Caching

```php
// SpaceTimeCache.php
class SpaceTimeCache
{
    private SpaceTimeDict $hot;
    private CacheInterface $cold;
    private MemoryPressureMonitor $monitor;
    
    public function get($key)
    {
        // Check hot cache first
        if (isset($this->hot[$key])) {
            return $this->hot[$key];
        }
        
        // Check cold storage
        $value = $this->cold->get($key);
        if ($value !== null) {
            // Promote to hot cache if memory allows
            if ($this->monitor->canAllocate(strlen($value))) {
                $this->hot[$key] = $value;
            }
        }
        
        return $value;
    }
}
```

## API Endpoints

### Products API

- `GET /api/products` - Paginated list
- `GET /api/products/stream` - Stream all products as NDJSON
- `GET /api/products/export/csv` - Export as CSV
- `POST /api/products/bulk-update` - Bulk update with checkpointing
- `POST /api/products/import` - Import CSV with progress

### Analytics API

- `GET /api/analytics/summary` - Get summary statistics
- `GET /api/analytics/realtime` - Real-time SSE stream
- `POST /api/analytics/report` - Generate large report
- `GET /api/analytics/top-products` - Top products with external sorting

### Reports API

- `POST /api/reports/generate` - Generate report (queued)
- `GET /api/reports/{id}/status` - Check generation status
- `GET /api/reports/{id}/download` - Download completed report

## Testing

Run the test suite:

```bash
php artisan test
```

Example test:

```php
public function test_can_stream_large_dataset()
{
    // Seed test data
    Product::factory()->count(10000)->create();
    
    // Make streaming request
    $response = $this->getJson('/api/products/stream');
    
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');
    
    // Verify memory usage stayed low
    $this->assertLessThan(50 * 1024 * 1024, memory_get_peak_usage());
}
```

## Performance Tips

1. **Configure memory limits** based on your server capacity
2. **Use streaming responses** for large datasets
3. **Enable checkpointing** for long-running jobs
4. **Monitor memory pressure** in production
5. **Use external storage** on fast SSDs
6. **Configure queue workers** with appropriate memory limits

## Deployment

### Nginx Configuration

```nginx
location /api/products/stream {
    proxy_pass http://backend;
    proxy_buffering off;
    proxy_read_timeout 3600;
}

location /api/analytics/realtime {
    proxy_pass http://backend;
    proxy_buffering off;
    proxy_read_timeout 0;
    proxy_http_version 1.1;
}
```

### Supervisor Configuration

```ini
[program:spacetime-worker]
command=php /path/to/artisan queue:work --memory=256
numprocs=4
autostart=true
autorestart=true
```

## Monitoring

Add to your monitoring:

```php
// app/Console/Commands/MonitorSpaceTime.php
$stats = [
    'memory_usage' => memory_get_usage(true),
    'peak_memory' => memory_get_peak_usage(true),
    'external_files' => count(glob(config('spacetime.external_storage') . '/*')),
    'cache_size' => $this->cache->size(),
];

Log::channel('metrics')->info('spacetime.stats', $stats);
```

## Troubleshooting

### High Memory Usage
- Check `SPACETIME_MEMORY_LIMIT` setting
- Enable more aggressive spillover
- Use smaller chunk sizes

### Slow Performance
- Ensure external storage is on SSD
- Increase memory limit if possible
- Use compression for large values

### Failed Checkpoints
- Check storage permissions
- Ensure sufficient disk space
- Verify checkpoint directory exists

## Learn More

- [SqrtSpace SpaceTime Documentation](https://github.com/MarketAlly/Ubiquity)
- [Laravel Documentation](https://laravel.com/docs)
- [Memory-Efficient PHP Patterns](https://example.com/patterns)