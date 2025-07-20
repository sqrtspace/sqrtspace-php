<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Checkpoint;

/**
 * Interface for checkpoint storage backends
 */
interface CheckpointStorage
{
    /**
     * Save checkpoint data
     */
    public function save(string $id, array $data): void;
    
    /**
     * Load checkpoint data
     */
    public function load(string $id): ?array;
    
    /**
     * Check if checkpoint exists
     */
    public function exists(string $id): bool;
    
    /**
     * Delete checkpoint
     */
    public function delete(string $id): void;
    
    /**
     * List all checkpoints
     */
    public function list(): array;
    
    /**
     * Clean up old checkpoints
     */
    public function cleanup(int $olderThanTimestamp): int;
}