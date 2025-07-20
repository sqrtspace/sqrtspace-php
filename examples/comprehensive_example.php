<?php

require_once __DIR__ . '/../vendor/autoload.php';

use SqrtSpace\SpaceTime\SpaceTimeConfig;
use SqrtSpace\SpaceTime\Collections\SpaceTimeArray;
use SqrtSpace\SpaceTime\Algorithms\ExternalSort;
use SqrtSpace\SpaceTime\Algorithms\ExternalGroupBy;
use SqrtSpace\SpaceTime\Streams\SpaceTimeStream;
use SqrtSpace\SpaceTime\File\CsvReader;
use SqrtSpace\SpaceTime\File\CsvExporter;
use SqrtSpace\SpaceTime\File\JsonLinesProcessor;
use SqrtSpace\SpaceTime\Batch\BatchProcessor;
use SqrtSpace\SpaceTime\Memory\MemoryPressureMonitor;
use SqrtSpace\SpaceTime\Memory\MemoryPressureLevel;
use SqrtSpace\SpaceTime\Memory\Handlers\LoggingHandler;
use SqrtSpace\SpaceTime\Checkpoint\CheckpointManager;

// Configure SpaceTime
SpaceTimeConfig::configure([
    'memory_limit' => '256M',
    'external_storage_path' => __DIR__ . '/temp',
    'chunk_strategy' => 'sqrt_n',
    'enable_checkpointing' => true,
    'compression' => true,
]);

echo "=== Ubiquity SpaceTime PHP Examples ===\n\n";

// Example 1: Memory-Efficient Array
echo "1. SpaceTimeArray Example\n";
$array = new SpaceTimeArray(1000); // Spill to disk after 1000 items

// Add 10,000 items
for ($i = 0; $i < 10000; $i++) {
    $array["key_$i"] = "value_$i";
}

echo "   - Created array with " . count($array) . " items\n";
echo "   - Memory usage: " . number_format(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n\n";

// Example 2: External Sorting
echo "2. External Sort Example\n";
$unsorted = [];
for ($i = 0; $i < 50000; $i++) {
    $unsorted[] = [
        'id' => $i,
        'value' => mt_rand(1, 1000000),
        'name' => 'Item ' . $i
    ];
}

$sorted = ExternalSort::sortBy($unsorted, fn($item) => $item['value']);
echo "   - Sorted " . count($sorted) . " items by value\n";
echo "   - First item value: " . $sorted[0]['value'] . "\n";
echo "   - Last item value: " . $sorted[count($sorted) - 1]['value'] . "\n\n";

// Example 3: External GroupBy
echo "3. External GroupBy Example\n";
$orders = [];
for ($i = 0; $i < 10000; $i++) {
    $orders[] = [
        'customer_id' => mt_rand(1, 100),
        'amount' => mt_rand(10, 1000),
        'category' => ['Electronics', 'Clothing', 'Food', 'Books'][mt_rand(0, 3)]
    ];
}

$byCategory = ExternalGroupBy::groupBySum(
    $orders,
    fn($order) => $order['category'],
    fn($order) => $order['amount']
);

foreach ($byCategory as $category => $total) {
    echo "   - $category: $" . number_format($total, 2) . "\n";
}
echo "\n";

// Example 4: Stream Processing
echo "4. Stream Processing Example\n";

// Create sample CSV file
$csvFile = __DIR__ . '/sample.csv';
$csv = fopen($csvFile, 'w');
fputcsv($csv, ['id', 'name', 'price', 'quantity']);
for ($i = 1; $i <= 1000; $i++) {
    fputcsv($csv, [$i, "Product $i", mt_rand(10, 100), mt_rand(1, 50)]);
}
fclose($csv);

// Process CSV stream
$totalRevenue = SpaceTimeStream::fromCsv($csvFile)
    ->map(fn($row) => [
        'id' => $row['id'],
        'revenue' => (float)$row['price'] * (int)$row['quantity']
    ])
    ->reduce(fn($total, $row) => $total + $row['revenue'], 0);

echo "   - Total revenue from 1000 products: $" . number_format($totalRevenue, 2) . "\n\n";

// Example 5: Memory Pressure Monitoring
echo "5. Memory Pressure Monitoring Example\n";
$monitor = new MemoryPressureMonitor('100M');

// Simulate memory usage
$data = [];
for ($i = 0; $i < 100; $i++) {
    $data[] = str_repeat('x', 100000); // 100KB per item
    
    $level = $monitor->check();
    if ($level !== MemoryPressureLevel::NONE) {
        echo "   - Memory pressure detected: " . $level->value . "\n";
        $info = $monitor->getMemoryInfo();
        echo "   - Memory usage: " . round($info['percentage'], 2) . "%\n";
        break;
    }
}

// Clean up
unset($data);
$monitor->forceCleanup();
echo "\n";

// Example 6: Batch Processing with Checkpoints
echo "6. Batch Processing Example\n";

$processor = new BatchProcessor([
    'batch_size' => 100,
    'checkpoint_enabled' => true,
    'progress_callback' => function($batch, $size, $result) {
        echo "   - Processing batch $batch ($size items)\n";
    }
]);

$items = range(1, 500);
$result = $processor->process($items, function($batch) {
    $processed = [];
    foreach ($batch as $key => $value) {
        // Simulate processing
        $processed[$key] = $value * 2;
    }
    return $processed;
}, 'example_job');

echo "   - Processed: " . $result->getSuccessCount() . " items\n";
echo "   - Execution time: " . round($result->getExecutionTime(), 2) . " seconds\n\n";

// Example 7: CSV Export with Streaming
echo "7. CSV Export Example\n";

$exportFile = __DIR__ . '/export.csv';
$exporter = new CsvExporter($exportFile);

$exporter->writeHeaders(['ID', 'Name', 'Email', 'Created']);

// Simulate exporting user data
$exporter->writeInChunks(function() {
    for ($i = 1; $i <= 1000; $i++) {
        yield [
            'id' => $i,
            'name' => "User $i",
            'email' => "user$i@example.com",
            'created' => date('Y-m-d H:i:s')
        ];
    }
}());

echo "   - Exported to: $exportFile\n";
echo "   - File size: " . number_format(filesize($exportFile) / 1024, 2) . " KB\n\n";

// Example 8: JSON Lines Processing
echo "8. JSON Lines Processing Example\n";

$jsonlFile = __DIR__ . '/events.jsonl';
$events = [];
for ($i = 0; $i < 100; $i++) {
    $events[] = [
        'id' => $i,
        'type' => ['click', 'view', 'purchase'][mt_rand(0, 2)],
        'timestamp' => time() - mt_rand(0, 86400),
        'user_id' => mt_rand(1, 50)
    ];
}

JsonLinesProcessor::write($events, $jsonlFile);

// Process and filter
$filtered = __DIR__ . '/purchases.jsonl';
$count = JsonLinesProcessor::filter(
    $jsonlFile,
    $filtered,
    fn($event) => $event['type'] === 'purchase'
);

echo "   - Created JSONL with 100 events\n";
echo "   - Filtered $count purchase events\n\n";

// Clean up example files
echo "Cleaning up example files...\n";
unlink($csvFile);
unlink($exportFile);
unlink($jsonlFile);
unlink($filtered);

echo "\n=== Examples Complete ===\n";