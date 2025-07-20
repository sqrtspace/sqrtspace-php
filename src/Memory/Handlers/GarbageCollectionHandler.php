<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Memory\Handlers;

use SqrtSpace\SpaceTime\Memory\MemoryPressureHandler;
use SqrtSpace\SpaceTime\Memory\MemoryPressureLevel;

/**
 * Trigger garbage collection under memory pressure
 */
class GarbageCollectionHandler implements MemoryPressureHandler
{
    private float $lastCollection = 0;
    private float $minInterval = 1.0; // Minimum seconds between collections
    
    public function shouldHandle(MemoryPressureLevel $level): bool
    {
        return $level->isHigherThan(MemoryPressureLevel::LOW);
    }
    
    public function handle(MemoryPressureLevel $level, array $memoryInfo): void
    {
        $now = microtime(true);
        
        // Don't collect too frequently
        if ($now - $this->lastCollection < $this->minInterval) {
            return;
        }
        
        // Force collection for high/critical pressure
        if ($level->isHigherThan(MemoryPressureLevel::MEDIUM)) {
            $this->forceCollection();
            $this->lastCollection = $now;
        }
    }
    
    private function forceCollection(): void
    {
        // Enable GC if disabled
        $wasEnabled = gc_enabled();
        if (!$wasEnabled) {
            gc_enable();
        }
        
        // Collect cycles
        $collected = gc_collect_cycles();
        
        // Restore previous state
        if (!$wasEnabled) {
            gc_disable();
        }
    }
}