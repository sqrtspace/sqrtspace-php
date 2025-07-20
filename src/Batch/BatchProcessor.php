<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Batch;

use SqrtSpace\SpaceTime\SpaceTimeConfig;
use SqrtSpace\SpaceTime\Memory\MemoryPressureMonitor;
use SqrtSpace\SpaceTime\Memory\MemoryPressureLevel;
use SqrtSpace\SpaceTime\Checkpoint\CheckpointManager;

/**
 * Process large datasets in memory-efficient batches
 */
class BatchProcessor
{
    private MemoryPressureMonitor $memoryMonitor;
    private ?CheckpointManager $checkpoint = null;
    private array $options;
    
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'batch_size' => null, // Auto-calculate if null
            'memory_threshold' => 0.8, // 80% memory usage
            'checkpoint_enabled' => true,
            'progress_callback' => null,
            'error_handler' => null,
            'max_retries' => 3,
        ], $options);
        
        $this->memoryMonitor = new MemoryPressureMonitor();
    }
    
    /**
     * Process items in batches
     */
    public function process(iterable $items, callable $processor, ?string $checkpointId = null): BatchResult
    {
        $result = new BatchResult();
        
        // Setup checkpoint if enabled
        if ($this->options['checkpoint_enabled'] && $checkpointId) {
            $this->checkpoint = new CheckpointManager($checkpointId);
            
            // Resume from checkpoint if exists
            if ($this->checkpoint->exists()) {
                $state = $this->checkpoint->load();
                $result->restore($state);
            }
        }
        
        // Calculate batch size
        $batchSize = $this->calculateBatchSize($items);
        
        // Process batches
        $batch = [];
        $batchNumber = 0;
        
        foreach ($items as $key => $item) {
            // Skip already processed items
            if ($result->isProcessed($key)) {
                continue;
            }
            
            $batch[$key] = $item;
            
            // Process batch when full or memory pressure
            if (count($batch) >= $batchSize || $this->shouldProcessBatch()) {
                $this->processBatch($batch, $processor, $result, $batchNumber);
                $batch = [];
                $batchNumber++;
            }
        }
        
        // Process remaining items
        if (!empty($batch)) {
            $this->processBatch($batch, $processor, $result, $batchNumber);
        }
        
        // Clean up checkpoint on success
        if ($this->checkpoint && $result->isComplete()) {
            $this->checkpoint->delete();
        }
        
        return $result;
    }
    
    /**
     * Process items in parallel batches
     */
    public function processParallel(iterable $items, callable $processor, int $workers = 4): BatchResult
    {
        if (!function_exists('pcntl_fork')) {
            throw new \RuntimeException('Parallel processing requires pcntl extension');
        }
        
        $result = new BatchResult();
        $chunks = $this->splitIntoChunks($items, $workers);
        $pids = [];
        
        foreach ($chunks as $i => $chunk) {
            $pid = pcntl_fork();
            
            if ($pid === -1) {
                throw new \RuntimeException('Failed to fork process');
            } elseif ($pid === 0) {
                // Child process
                $chunkResult = $this->process($chunk, $processor);
                
                // Write result to shared memory or file
                $this->saveChunkResult($i, $chunkResult);
                
                exit(0);
            } else {
                // Parent process
                $pids[$i] = $pid;
            }
        }
        
        // Wait for all children
        foreach ($pids as $i => $pid) {
            pcntl_waitpid($pid, $status);
            
            // Merge chunk result
            $chunkResult = $this->loadChunkResult($i);
            $result->merge($chunkResult);
        }
        
        return $result;
    }
    
    /**
     * Process batch with error handling
     */
    private function processBatch(array $batch, callable $processor, BatchResult $result, int $batchNumber): void
    {
        $retries = 0;
        $success = false;
        
        while (!$success && $retries < $this->options['max_retries']) {
            try {
                // Call progress callback
                if ($this->options['progress_callback']) {
                    ($this->options['progress_callback'])($batchNumber, count($batch), $result);
                }
                
                // Process batch
                $batchResult = $processor($batch);
                
                // Record results
                foreach ($batch as $key => $item) {
                    $result->addSuccess($key, $batchResult[$key] ?? null);
                }
                
                $success = true;
                
            } catch (\Exception $e) {
                $retries++;
                
                if ($retries >= $this->options['max_retries']) {
                    // Record failures
                    foreach ($batch as $key => $item) {
                        $result->addError($key, $e);
                    }
                    
                    // Call error handler
                    if ($this->options['error_handler']) {
                        ($this->options['error_handler'])($e, $batch);
                    }
                } else {
                    // Wait before retry
                    sleep(pow(2, $retries)); // Exponential backoff
                }
            }
        }
        
        // Save checkpoint
        if ($this->checkpoint && $this->checkpoint->shouldCheckpoint()) {
            $this->checkpoint->save($result->getState());
        }
    }
    
    /**
     * Calculate optimal batch size
     */
    private function calculateBatchSize(iterable $items): int
    {
        if ($this->options['batch_size'] !== null) {
            return $this->options['batch_size'];
        }
        
        // Estimate based on available memory
        $memoryInfo = $this->memoryMonitor->getMemoryInfo();
        $availableMemory = $memoryInfo['available'];
        
        // Estimate item size (sample first few items)
        $sampleSize = 10;
        $totalSize = 0;
        $count = 0;
        
        foreach ($items as $item) {
            $totalSize += strlen(serialize($item));
            $count++;
            
            if ($count >= $sampleSize) {
                break;
            }
        }
        
        if ($count === 0) {
            return 100; // Default
        }
        
        $avgItemSize = $totalSize / $count;
        $targetMemoryUsage = $availableMemory * 0.5; // Use 50% of available memory
        
        return max(10, min(10000, (int)($targetMemoryUsage / $avgItemSize)));
    }
    
    /**
     * Check if batch should be processed due to memory pressure
     */
    private function shouldProcessBatch(): bool
    {
        $level = $this->memoryMonitor->check();
        
        return $level->isHigherThan(MemoryPressureLevel::MEDIUM);
    }
    
    /**
     * Split items into chunks for parallel processing
     */
    private function splitIntoChunks(iterable $items, int $numChunks): array
    {
        $chunks = array_fill(0, $numChunks, []);
        $i = 0;
        
        foreach ($items as $key => $item) {
            $chunks[$i % $numChunks][$key] = $item;
            $i++;
        }
        
        return $chunks;
    }
    
    /**
     * Save chunk result (simplified - use shared memory in production)
     */
    private function saveChunkResult(int $chunkId, BatchResult $result): void
    {
        $filename = sys_get_temp_dir() . "/batch_chunk_{$chunkId}.tmp";
        file_put_contents($filename, serialize($result));
    }
    
    /**
     * Load chunk result
     */
    private function loadChunkResult(int $chunkId): BatchResult
    {
        $filename = sys_get_temp_dir() . "/batch_chunk_{$chunkId}.tmp";
        $result = unserialize(file_get_contents($filename));
        unlink($filename);
        
        return $result;
    }
}