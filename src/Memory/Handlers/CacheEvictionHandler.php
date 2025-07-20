<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Memory\Handlers;

use SqrtSpace\SpaceTime\Memory\MemoryPressureHandler;
use SqrtSpace\SpaceTime\Memory\MemoryPressureLevel;

/**
 * Evict cache entries under memory pressure
 */
class CacheEvictionHandler implements MemoryPressureHandler
{
    private array $caches = [];
    private array $evictionRates = [
        MemoryPressureLevel::LOW->value => 0.1,      // Evict 10%
        MemoryPressureLevel::MEDIUM->value => 0.25,   // Evict 25%
        MemoryPressureLevel::HIGH->value => 0.5,      // Evict 50%
        MemoryPressureLevel::CRITICAL->value => 0.9,  // Evict 90%
    ];
    
    /**
     * Register a cache that can be evicted
     */
    public function registerCache(EvictableCache $cache, int $priority = 0): void
    {
        $this->caches[] = ['cache' => $cache, 'priority' => $priority];
        
        // Sort by priority (lower number = higher priority to keep)
        usort($this->caches, fn($a, $b) => $b['priority'] <=> $a['priority']);
    }
    
    public function shouldHandle(MemoryPressureLevel $level): bool
    {
        return $level !== MemoryPressureLevel::NONE;
    }
    
    public function handle(MemoryPressureLevel $level, array $memoryInfo): void
    {
        $evictionRate = $this->evictionRates[$level->value] ?? 0;
        
        if ($evictionRate === 0) {
            return;
        }
        
        // Evict from lowest priority caches first
        foreach ($this->caches as $cacheInfo) {
            $cache = $cacheInfo['cache'];
            $size = $cache->size();
            
            if ($size > 0) {
                $toEvict = (int) ceil($size * $evictionRate);
                $cache->evict($toEvict);
                
                // Check if pressure is relieved
                $currentUsage = memory_get_usage(true);
                if ($currentUsage < $memoryInfo['limit'] * 0.7) {
                    break;
                }
            }
        }
    }
}

/**
 * Interface for caches that support eviction
 */
interface EvictableCache
{
    /**
     * Get current cache size
     */
    public function size(): int;
    
    /**
     * Evict n entries from cache
     */
    public function evict(int $count): void;
    
    /**
     * Clear entire cache
     */
    public function clear(): void;
}