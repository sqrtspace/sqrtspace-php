# SqrtSpace SpaceTime for PHP

[![Latest Stable Version](https://poser.pugx.org/sqrtspace/spacetime/v)](https://packagist.org/packages/sqrtspace/spacetime)
[![Total Downloads](https://poser.pugx.org/sqrtspace/spacetime/downloads)](https://packagist.org/packages/sqrtspace/spacetime)
[![License](https://poser.pugx.org/sqrtspace/spacetime/license)](https://packagist.org/packages/sqrtspace/spacetime)
[![PHP Version Require](https://poser.pugx.org/sqrtspace/spacetime/require/php)](https://packagist.org/packages/sqrtspace/spacetime)

Memory-efficient algorithms and data structures for PHP using Williams' √n space-time tradeoffs.

## Installation

```bash
composer require sqrtspace/spacetime
```

## Core Concepts

SpaceTime implements theoretical computer science results showing that many algorithms can achieve better memory usage by accepting slightly slower runtime. The key insight is using √n memory instead of n memory, where n is the input size.

### Key Features

- **External Sorting**: Sort large datasets that don't fit in memory
- **External Grouping**: Group and aggregate data with minimal memory usage  
- **Streaming Operations**: Process files and data streams efficiently
- **Memory Pressure Handling**: Automatic response to low memory conditions
- **Checkpoint/Resume**: Save progress and resume long-running operations
- **Laravel Integration**: Deep integration with Laravel collections and queries

## Quick Start

```php
use SqrtSpace\SpaceTime\Collections\SpaceTimeArray;
use SqrtSpace\SpaceTime\Algorithms\ExternalSort;

// Handle large arrays with automatic memory management
$array = new SpaceTimeArray();
for ($i = 0; $i < 10000000; $i++) {
    $array[] = random_int(1, 1000000);
}

// Sort large datasets using only √n memory
$sorted = ExternalSort::sort($array);

// Process in optimal chunks
foreach ($array->chunkBySqrtN() as $chunk) {
    processChunk($chunk);
}
```

## Examples

### Basic Examples
See [`examples/comprehensive_example.php`](examples/comprehensive_example.php) for a complete demonstration of all features including:
- Memory-efficient arrays and dictionaries
- External sorting and grouping
- Stream processing
- CSV import/export
- Batch processing with checkpoints
- Memory pressure monitoring

### Laravel Application
Check out [`examples/laravel-app/`](examples/laravel-app/) for a complete Laravel application demonstrating:
- Streaming API endpoints
- Memory-efficient CSV exports
- Background job processing with checkpoints
- Real-time analytics with SSE
- Production-ready configurations

See the [Laravel example README](examples/laravel-app/README.md) for setup instructions and detailed usage.

## Features

### 1. Memory-Efficient Collections

```php
use SqrtSpace\SpaceTime\Collections\SpaceTimeArray;
use SqrtSpace\SpaceTime\Collections\AdaptiveDictionary;

// Adaptive array - automatically switches between memory and disk
$array = new SpaceTimeArray();
$array->setThreshold(10000); // Switch to external storage after 10k items

// Adaptive dictionary with optimal memory usage
$dict = new AdaptiveDictionary();
for ($i = 0; $i < 1000000; $i++) {
    $dict["key_$i"] = "value_$i";
}
```

### 2. External Algorithms

```php
use SqrtSpace\SpaceTime\Algorithms\ExternalSort;
use SqrtSpace\SpaceTime\Algorithms\ExternalGroupBy;

// Sort millions of records using minimal memory
$data = getData(); // Large dataset
$sorted = ExternalSort::sort($data, fn($a, $b) => $a['date'] <=> $b['date']);

// Group by with external storage
$grouped = ExternalGroupBy::groupBy($data, fn($item) => $item['category']);
```

### 3. Streaming Operations

```php
use SqrtSpace\SpaceTime\Streams\SpaceTimeStream;

// Process large files with bounded memory
$stream = SpaceTimeStream::fromFile('large_file.csv')
    ->map(fn($line) => str_getcsv($line))
    ->filter(fn($row) => $row[2] > 100)
    ->chunkBySqrtN()
    ->each(function($chunk) {
        processBatch($chunk);
    });
```

### 4. Database Integration

```php
use SqrtSpace\SpaceTime\Database\SpaceTimeQueryBuilder;

// Process large result sets efficiently
$query = new SpaceTimeQueryBuilder($pdo);
$query->from('orders')
    ->where('status', '=', 'pending')
    ->orderByExternal('created_at', 'desc')
    ->chunkBySqrtN(function($orders) {
        foreach ($orders as $order) {
            processOrder($order);
        }
    });

// Stream results for minimal memory usage
$stream = $query->from('logs')
    ->where('level', '=', 'error')
    ->stream();
    
$stream->filter(fn($log) => strpos($log['message'], 'critical') !== false)
    ->each(fn($log) => alertAdmin($log));
```

### 5. Laravel Integration

```php
// In AppServiceProvider
use SqrtSpace\SpaceTime\Laravel\SpaceTimeServiceProvider;

public function register()
{
    $this->app->register(SpaceTimeServiceProvider::class);
}

// Collection macros
$collection = collect($largeArray);

// Sort using external memory
$sorted = $collection->sortByExternal('price');

// Group by with external storage
$grouped = $collection->groupByExternal('category');

// Process in √n chunks
$collection->chunkBySqrtN()->each(function ($chunk) {
    processBatch($chunk);
});

// Query builder extensions
DB::table('orders')
    ->chunkBySqrtN(function ($orders) {
        foreach ($orders as $order) {
            processOrder($order);
        }
    });
```

### 6. Memory Pressure Handling

```php
use SqrtSpace\SpaceTime\Memory\MemoryPressureMonitor;
use SqrtSpace\SpaceTime\Memory\Handlers\LoggingHandler;
use SqrtSpace\SpaceTime\Memory\Handlers\CacheEvictionHandler;
use SqrtSpace\SpaceTime\Memory\Handlers\GarbageCollectionHandler;

$monitor = new MemoryPressureMonitor('512M');

// Add handlers
$monitor->registerHandler(new LoggingHandler($logger));
$monitor->registerHandler(new CacheEvictionHandler());
$monitor->registerHandler(new GarbageCollectionHandler());

// Check pressure in your operations
if ($monitor->check() === MemoryPressureLevel::HIGH) {
    // Switch to more aggressive memory saving
    $processor->useExternalStorage();
}
```

### 7. Checkpointing for Fault Tolerance

```php
use SqrtSpace\SpaceTime\Checkpoint\CheckpointManager;

$checkpoint = new CheckpointManager('import_job_123');

foreach ($largeDataset->chunkBySqrtN() as $chunk) {
    processChunk($chunk);
    
    // Save progress every √n items
    if ($checkpoint->shouldCheckpoint()) {
        $checkpoint->save([
            'processed' => $processedCount,
            'last_id' => $lastId
        ]);
    }
}
```

## Real-World Examples

### Processing Large CSV Files

```php
use SqrtSpace\SpaceTime\File\CsvReader;
use SqrtSpace\SpaceTime\Algorithms\ExternalGroupBy;

$reader = new CsvReader('sales_data.csv');

// Get column statistics
$stats = $reader->getColumnStats('amount');
echo "Average order: $" . $stats['avg'];

// Process with type conversion
$totals = $reader->readWithTypes([
    'amount' => 'float',
    'quantity' => 'int',
    'date' => 'date'
])->reduce(function ($totals, $row) {
    $month = $row['date']->format('Y-m');
    $totals[$month] = ($totals[$month] ?? 0) + $row['amount'];
    return $totals;
}, []);
```

### Large Data Export

```php
use SqrtSpace\SpaceTime\File\CsvExporter;
use SqrtSpace\SpaceTime\Database\SpaceTimeQueryBuilder;

$exporter = new CsvExporter('users_export.csv');
$query = new SpaceTimeQueryBuilder($pdo);

// Export with headers
$exporter->writeHeaders(['ID', 'Name', 'Email', 'Created At']);

// Stream data directly to CSV
$query->from('users')
    ->orderBy('created_at', 'desc')
    ->chunkBySqrtN(function($users) use ($exporter) {
        $exporter->writeRows(array_map(function($user) {
            return [
                $user['id'],
                $user['name'],
                $user['email'],
                $user['created_at']
            ];
        }, $users));
    });

echo "Exported " . number_format($exporter->getBytesWritten()) . " bytes\n";
```

### Batch Processing with Memory Limits

```php
use SqrtSpace\SpaceTime\Batch\BatchProcessor;

$processor = new BatchProcessor([
    'memory_threshold' => 0.8,
    'checkpoint_enabled' => true,
    'progress_callback' => function($batch, $size, $result) {
        echo "Processed batch $batch ($size items)\n";
    }
]);

$result = $processor->process($millionItems, function($batch) {
    $processed = [];
    foreach ($batch as $key => $item) {
        $processed[$key] = expensiveOperation($item);
    }
    return $processed;
}, 'job_123');

echo "Success: " . $result->getSuccessCount() . "\n";
echo "Errors: " . $result->getErrorCount() . "\n";
echo "Time: " . $result->getExecutionTime() . "s\n";
```

## Configuration

```php
use SqrtSpace\SpaceTime\SpaceTimeConfig;

// Global configuration
SpaceTimeConfig::configure([
    'memory_limit' => '512M',
    'external_storage_path' => '/tmp/spacetime',
    'chunk_strategy' => 'sqrt_n', // or 'memory_based', 'fixed'
    'enable_checkpointing' => true,
    'compression' => true,
    'compression_level' => 6
]);

// Per-operation configuration
$array = new SpaceTimeArray(10000); // threshold

// Check configuration
echo "Chunk size for 1M items: " . SpaceTimeConfig::calculateSqrtN(1000000) . "\n";
echo "Storage path: " . SpaceTimeConfig::getStoragePath() . "\n";
```

## Advanced Usage

### JSON Lines Processing

```php
use SqrtSpace\SpaceTime\File\JsonLinesProcessor;

// Process large JSONL files
JsonLinesProcessor::processInChunks('events.jsonl', function($events) {
    foreach ($events as $event) {
        if ($event['type'] === 'error') {
            logError($event);
        }
    }
});

// Split large file
$files = JsonLinesProcessor::split('huge.jsonl', 100000, 'output/chunk');
echo "Split into " . count($files) . " files\n";

// Merge multiple files
$count = JsonLinesProcessor::merge($files, 'merged.jsonl');
echo "Merged $count records\n";
```

### Streaming Operations

```php
use SqrtSpace\SpaceTime\Streams\SpaceTimeStream;

// Chain operations efficiently
SpaceTimeStream::fromCsv('sales.csv')
    ->filter(fn($row) => $row['region'] === 'US')
    ->map(fn($row) => [
        'product' => $row['product'],
        'revenue' => $row['quantity'] * $row['price']
    ])
    ->chunkBySqrtN()
    ->each(function($chunk) {
        $total = array_sum(array_column($chunk, 'revenue'));
        echo "Chunk revenue: \$$total\n";
    });
```

### Custom Batch Jobs

```php
use SqrtSpace\SpaceTime\Batch\BatchJob;

class ImportJob extends BatchJob
{
    private string $filename;
    
    public function __construct(string $filename)
    {
        parent::__construct();
        $this->filename = $filename;
    }
    
    protected function getItems(): iterable
    {
        return SpaceTimeStream::fromCsv($this->filename);
    }
    
    public function processItem(array $batch): array
    {
        $results = [];
        foreach ($batch as $key => $row) {
            $user = User::create([
                'name' => $row['name'],
                'email' => $row['email']
            ]);
            $results[$key] = $user->id;
        }
        return $results;
    }
    
    protected function getUniqueId(): string
    {
        return md5($this->filename);
    }
}

// Run job with automatic checkpointing
$job = new ImportJob('users.csv');
$result = $job->execute();

// Or resume if interrupted
if ($job->canResume()) {
    $result = $job->resume();
}
```

## Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit tests/Algorithms

# With coverage
vendor/bin/phpunit --coverage-html coverage
```

## Performance Considerations

1. **Chunk Size**: The default √n chunk size is optimal for most cases, but you can tune it:
   ```php
   SpaceTimeConfig::configure(['chunk_strategy' => 'fixed', 'fixed_chunk_size' => 5000]);
   ```

2. **Compression**: Enable for text-heavy data, disable for already compressed data:
   ```php
   SpaceTimeConfig::configure(['compression' => false]);
   ```

3. **Storage Location**: Use fast local SSDs for external storage:
   ```php
   SpaceTimeConfig::configure(['external_storage_path' => '/mnt/fast-ssd/spacetime']);
   ```

## Framework Integration

### Laravel

```php
// config/spacetime.php
return [
    'memory_limit' => env('SPACETIME_MEMORY_LIMIT', '256M'),
    'storage_driver' => env('SPACETIME_STORAGE', 'file'),
    'redis_connection' => env('SPACETIME_REDIS', 'default'),
];

// In controller
public function exportOrders()
{
    return SpaceTimeResponse::stream(function() {
        Order::orderByExternal('created_at')
            ->chunkBySqrtN(function($orders) {
                foreach ($orders as $order) {
                    echo $order->toCsv() . "\n";
                }
            });
    });
}
```

### Symfony

For a complete Symfony integration example, see our [Symfony bundle documentation](https://github.com/MarketAlly/Ubiquity/wiki/Symfony-Integration).

```yaml
# config/bundles.php
return [
    // ...
    SqrtSpace\SpaceTime\Symfony\SpaceTimeBundle::class => ['all' => true],
];
```

```yaml
# config/packages/spacetime.yaml
spacetime:
    memory_limit: '%env(SPACETIME_MEMORY_LIMIT)%'
    storage_path: '%kernel.project_dir%/var/spacetime'
    chunk_strategy: 'sqrt_n'
    enable_checkpointing: true
    compression: true
```

```php
// In controller
use SqrtSpace\SpaceTime\Batch\BatchProcessor;
use SqrtSpace\SpaceTime\File\CsvReader;

#[Route('/import')]
public function import(BatchProcessor $processor): Response
{
    $reader = new CsvReader($this->getParameter('import_file'));
    
    $result = $processor->process(
        $reader->stream(),
        fn($batch) => $this->importBatch($batch)
    );
    
    return $this->json([
        'imported' => $result->getSuccessCount(),
        'errors' => $result->getErrorCount()
    ]);
}
```

```bash
# Console command
php bin/console spacetime:process-file input.csv output.csv --format=csv --checkpoint
```

## Troubleshooting

### Out of Memory Errors

1. Reduce chunk size:
   ```php
   SpaceTimeConfig::configure(['chunk_strategy' => 'fixed', 'fixed_chunk_size' => 1000]);
   ```

2. Enable more aggressive memory handling:
   ```php
   $monitor = new MemoryPressureMonitor('128M'); // Lower threshold
   ```

3. Use external storage earlier:
   ```php
   $array = new SpaceTimeArray(100); // Smaller threshold
   ```

### Performance Issues

1. Check disk I/O speed
2. Enable compression for text data
3. Use memory-based external storage:
   ```php
   SpaceTimeConfig::configure(['external_storage_path' => '/dev/shm/spacetime']);
   ```

### Checkpoint Recovery

```php
$checkpoint = new CheckpointManager('job_id');
if ($checkpoint->exists()) {
    $state = $checkpoint->load();
    echo "Resuming from: " . json_encode($state) . "\n";
}
```

## Requirements

- PHP 8.1 or higher
- ext-json
- ext-mbstring

## Optional Extensions

- ext-apcu for faster caching
- ext-redis for distributed operations
- ext-zlib for compression

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The Apache 2.0 License. Please see [LICENSE](LICENSE) for details.