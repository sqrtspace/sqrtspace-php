<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Batch;

/**
 * Result container for batch processing
 */
class BatchResult
{
    private array $processed = [];
    private array $errors = [];
    private array $results = [];
    private int $successCount = 0;
    private int $errorCount = 0;
    private float $startTime;
    private float $endTime;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
    }
    
    /**
     * Add successful result
     */
    public function addSuccess(string|int $key, mixed $result = null): void
    {
        $this->processed[$key] = true;
        $this->results[$key] = $result;
        $this->successCount++;
    }
    
    /**
     * Add error
     */
    public function addError(string|int $key, \Throwable $error): void
    {
        $this->processed[$key] = true;
        $this->errors[$key] = [
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ];
        $this->errorCount++;
    }
    
    /**
     * Check if item was processed
     */
    public function isProcessed(string|int $key): bool
    {
        return isset($this->processed[$key]);
    }
    
    /**
     * Check if all items were successful
     */
    public function isComplete(): bool
    {
        return $this->errorCount === 0;
    }
    
    /**
     * Get total processed count
     */
    public function getProcessedCount(): int
    {
        return $this->successCount + $this->errorCount;
    }
    
    /**
     * Get success count
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
    
    /**
     * Get error count
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }
    
    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get all results
     */
    public function getResults(): array
    {
        return $this->results;
    }
    
    /**
     * Get result for specific key
     */
    public function getResult(string|int $key): mixed
    {
        return $this->results[$key] ?? null;
    }
    
    /**
     * Get error for specific key
     */
    public function getError(string|int $key): ?array
    {
        return $this->errors[$key] ?? null;
    }
    
    /**
     * Get execution time
     */
    public function getExecutionTime(): float
    {
        $endTime = $this->endTime ?? microtime(true);
        return $endTime - $this->startTime;
    }
    
    /**
     * Mark as finished
     */
    public function finish(): void
    {
        $this->endTime = microtime(true);
    }
    
    /**
     * Get state for checkpointing
     */
    public function getState(): array
    {
        return [
            'processed' => $this->processed,
            'errors' => $this->errors,
            'results' => $this->results,
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'start_time' => $this->startTime,
        ];
    }
    
    /**
     * Restore from checkpoint state
     */
    public function restore(array $state): void
    {
        $this->processed = $state['processed'] ?? [];
        $this->errors = $state['errors'] ?? [];
        $this->results = $state['results'] ?? [];
        $this->successCount = $state['success_count'] ?? 0;
        $this->errorCount = $state['error_count'] ?? 0;
        $this->startTime = $state['start_time'] ?? microtime(true);
    }
    
    /**
     * Merge another result
     */
    public function merge(BatchResult $other): void
    {
        foreach ($other->processed as $key => $value) {
            $this->processed[$key] = $value;
        }
        
        foreach ($other->results as $key => $result) {
            $this->results[$key] = $result;
        }
        
        foreach ($other->errors as $key => $error) {
            $this->errors[$key] = $error;
        }
        
        $this->successCount += $other->successCount;
        $this->errorCount += $other->errorCount;
    }
    
    /**
     * Get summary statistics
     */
    public function getSummary(): array
    {
        return [
            'total_processed' => $this->getProcessedCount(),
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'success_rate' => $this->getProcessedCount() > 0 
                ? ($this->successCount / $this->getProcessedCount()) * 100 
                : 0,
            'execution_time' => $this->getExecutionTime(),
            'items_per_second' => $this->getExecutionTime() > 0 
                ? $this->getProcessedCount() / $this->getExecutionTime() 
                : 0,
        ];
    }
}