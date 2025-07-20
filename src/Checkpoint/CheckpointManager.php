<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Checkpoint;

use SqrtSpace\SpaceTime\SpaceTimeConfig;

/**
 * Manage checkpoints for resumable operations
 */
class CheckpointManager
{
    private string $checkpointId;
    private CheckpointStorage $storage;
    private int $checkpointInterval;
    private float $lastCheckpoint = 0;
    
    public function __construct(string $checkpointId, ?CheckpointStorage $storage = null)
    {
        $this->checkpointId = $checkpointId;
        $this->storage = $storage ?? $this->createDefaultStorage();
        $this->checkpointInterval = 60; // seconds
    }
    
    /**
     * Save checkpoint data
     */
    public function save(array $data): void
    {
        $checkpoint = [
            'id' => $this->checkpointId,
            'timestamp' => time(),
            'data' => $data,
        ];
        
        $this->storage->save($this->checkpointId, $checkpoint);
        $this->lastCheckpoint = microtime(true);
    }
    
    /**
     * Load checkpoint data
     */
    public function load(): ?array
    {
        $checkpoint = $this->storage->load($this->checkpointId);
        
        if ($checkpoint === null) {
            return null;
        }
        
        return $checkpoint['data'] ?? null;
    }
    
    /**
     * Check if checkpoint exists
     */
    public function exists(): bool
    {
        return $this->storage->exists($this->checkpointId);
    }
    
    /**
     * Delete checkpoint
     */
    public function delete(): void
    {
        $this->storage->delete($this->checkpointId);
    }
    
    /**
     * Check if it's time to checkpoint
     */
    public function shouldCheckpoint(): bool
    {
        if (!SpaceTimeConfig::isCheckpointingEnabled()) {
            return false;
        }
        
        $now = microtime(true);
        return ($now - $this->lastCheckpoint) >= $this->checkpointInterval;
    }
    
    /**
     * Set checkpoint interval
     */
    public function setInterval(int $seconds): void
    {
        $this->checkpointInterval = max(1, $seconds);
    }
    
    /**
     * Create checkpoint wrapper for operations
     */
    public function wrap(callable $operation, array $initialState = []): mixed
    {
        // Try to resume from checkpoint
        $state = $this->load() ?? $initialState;
        
        try {
            $result = $operation($state, $this);
            
            // Clean up on success
            $this->delete();
            
            return $result;
        } catch (\Exception $e) {
            // Checkpoint remains for retry
            throw $e;
        }
    }
    
    /**
     * Create default storage based on configuration
     */
    private function createDefaultStorage(): CheckpointStorage
    {
        $storageType = config('spacetime.checkpoint_storage', 'file');
        
        return match ($storageType) {
            'cache' => new CacheCheckpointStorage(),
            'database' => new DatabaseCheckpointStorage(),
            default => new FileCheckpointStorage(),
        };
    }
}