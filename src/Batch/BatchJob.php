<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Batch;

use SqrtSpace\SpaceTime\Checkpoint\CheckpointManager;

/**
 * Abstract batch job for queue processing
 */
abstract class BatchJob
{
    protected array $options;
    protected BatchProcessor $processor;
    protected ?CheckpointManager $checkpoint = null;
    
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->getDefaultOptions(), $options);
        $this->processor = new BatchProcessor($this->options);
    }
    
    /**
     * Get job ID for checkpointing
     */
    public function getJobId(): string
    {
        return static::class . '_' . $this->getUniqueId();
    }
    
    /**
     * Execute the batch job
     */
    public function execute(): BatchResult
    {
        // Get items to process
        $items = $this->getItems();
        
        // Process items
        $result = $this->processor->process(
            $items,
            [$this, 'processItem'],
            $this->getJobId()
        );
        
        // Handle completion
        if ($result->isComplete()) {
            $this->onComplete($result);
        } else {
            $this->onError($result);
        }
        
        return $result;
    }
    
    /**
     * Get items to process
     */
    abstract protected function getItems(): iterable;
    
    /**
     * Process single item
     */
    abstract public function processItem(array $batch): array;
    
    /**
     * Get unique identifier for this job instance
     */
    abstract protected function getUniqueId(): string;
    
    /**
     * Called when job completes successfully
     */
    protected function onComplete(BatchResult $result): void
    {
        // Override in subclass
    }
    
    /**
     * Called when job has errors
     */
    protected function onError(BatchResult $result): void
    {
        // Override in subclass
    }
    
    /**
     * Get default options
     */
    protected function getDefaultOptions(): array
    {
        return [
            'batch_size' => null,
            'checkpoint_enabled' => true,
            'max_retries' => 3,
        ];
    }
    
    /**
     * Resume job from checkpoint
     */
    public function resume(): BatchResult
    {
        $checkpoint = new CheckpointManager($this->getJobId());
        
        if (!$checkpoint->exists()) {
            throw new \RuntimeException('No checkpoint found for job: ' . $this->getJobId());
        }
        
        return $this->execute();
    }
    
    /**
     * Check if job can be resumed
     */
    public function canResume(): bool
    {
        $checkpoint = new CheckpointManager($this->getJobId());
        return $checkpoint->exists();
    }
}