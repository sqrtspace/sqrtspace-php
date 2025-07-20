<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Checkpoint;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-based checkpoint storage for Laravel
 */
class CacheCheckpointStorage implements CheckpointStorage
{
    private string $prefix = 'spacetime_checkpoint_';
    private int $ttl = 86400; // 24 hours default
    
    public function save(string $id, array $data): void
    {
        Cache::put($this->prefix . $id, $data, $this->ttl);
    }
    
    public function load(string $id): ?array
    {
        return Cache::get($this->prefix . $id);
    }
    
    public function exists(string $id): bool
    {
        return Cache::has($this->prefix . $id);
    }
    
    public function delete(string $id): void
    {
        Cache::forget($this->prefix . $id);
    }
    
    public function list(): array
    {
        // Note: This is limited by cache driver capabilities
        // Some drivers may not support listing keys
        return [];
    }
    
    public function cleanup(int $olderThanTimestamp): int
    {
        // Cache entries expire automatically
        return 0;
    }
    
    /**
     * Set TTL for checkpoints
     */
    public function setTtl(int $seconds): void
    {
        $this->ttl = max(60, $seconds);
    }
}