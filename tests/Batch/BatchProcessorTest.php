<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Tests\Batch;

use PHPUnit\Framework\TestCase;
use SqrtSpace\SpaceTime\Batch\BatchProcessor;
use SqrtSpace\SpaceTime\Batch\BatchResult;

class BatchProcessorTest extends TestCase
{
    public function testBasicBatchProcessing(): void
    {
        $processor = new BatchProcessor([
            'batch_size' => 3,
            'checkpoint_enabled' => false,
        ]);
        
        $items = range(1, 10);
        
        $result = $processor->process($items, function($batch) {
            $processed = [];
            foreach ($batch as $key => $item) {
                $processed[$key] = $item * 2;
            }
            return $processed;
        });
        
        $this->assertEquals(10, $result->getProcessedCount());
        $this->assertEquals(10, $result->getSuccessCount());
        $this->assertEquals(0, $result->getErrorCount());
        
        // Check results
        $results = $result->getResults();
        $this->assertEquals(2, $results[0]);
        $this->assertEquals(20, $results[9]);
    }
    
    public function testBatchProcessingWithErrors(): void
    {
        $processor = new BatchProcessor([
            'batch_size' => 2,
            'checkpoint_enabled' => false,
            'max_retries' => 1,
        ]);
        
        $items = range(1, 5);
        
        $result = $processor->process($items, function($batch) {
            $processed = [];
            foreach ($batch as $key => $item) {
                if ($item === 3) {
                    throw new \Exception('Error processing item 3');
                }
                $processed[$key] = $item * 2;
            }
            return $processed;
        });
        
        $this->assertEquals(5, $result->getProcessedCount());
        $this->assertEquals(3, $result->getSuccessCount());
        $this->assertEquals(2, $result->getErrorCount());
        
        $errors = $result->getErrors();
        $this->assertArrayHasKey(2, $errors); // Item 3 is at index 2
    }
    
    public function testProgressCallback(): void
    {
        $progressCalls = [];
        
        $processor = new BatchProcessor([
            'batch_size' => 2,
            'checkpoint_enabled' => false,
            'progress_callback' => function($batchNumber, $batchSize, $result) use (&$progressCalls) {
                $progressCalls[] = [
                    'batch' => $batchNumber,
                    'size' => $batchSize,
                    'processed' => $result->getProcessedCount(),
                ];
            },
        ]);
        
        $items = range(1, 5);
        $processor->process($items, fn($batch) => $batch);
        
        $this->assertCount(3, $progressCalls); // 5 items in batches of 2
        $this->assertEquals(0, $progressCalls[0]['batch']);
        $this->assertEquals(2, $progressCalls[0]['size']);
    }
    
    public function testBatchResult(): void
    {
        $result = new BatchResult();
        
        $result->addSuccess('key1', 'value1');
        $result->addSuccess('key2', 'value2');
        $result->addError('key3', new \Exception('Error'));
        
        $this->assertEquals(3, $result->getProcessedCount());
        $this->assertEquals(2, $result->getSuccessCount());
        $this->assertEquals(1, $result->getErrorCount());
        
        $this->assertTrue($result->isProcessed('key1'));
        $this->assertFalse($result->isComplete());
        
        $this->assertEquals('value1', $result->getResult('key1'));
        $this->assertNotNull($result->getError('key3'));
        
        $summary = $result->getSummary();
        $this->assertEquals(3, $summary['total_processed']);
        $this->assertGreaterThan(0, $summary['execution_time']);
    }
    
    public function testCheckpointingState(): void
    {
        $result = new BatchResult();
        
        $result->addSuccess('a', 1);
        $result->addSuccess('b', 2);
        
        $state = $result->getState();
        
        $newResult = new BatchResult();
        $newResult->restore($state);
        
        $this->assertEquals(2, $newResult->getProcessedCount());
        $this->assertEquals(1, $newResult->getResult('a'));
        $this->assertEquals(2, $newResult->getResult('b'));
    }
}