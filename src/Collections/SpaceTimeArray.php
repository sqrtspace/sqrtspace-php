<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Collections;

use ArrayAccess;
use Countable;
use Iterator;
use SqrtSpace\SpaceTime\SpaceTimeConfig;
use SqrtSpace\SpaceTime\Storage\ExternalStorage;

/**
 * Memory-efficient array that automatically switches between in-memory and external storage
 */
class SpaceTimeArray implements ArrayAccess, Iterator, Countable
{
    private array $hotData = [];
    private ?ExternalStorage $coldStorage = null;
    private int $threshold;
    private int $count = 0;
    private int $iteratorPosition = 0;
    private array $config;
    private array $allKeys = [];

    public function __construct($thresholdOrConfig = [])
    {
        // Support both old integer API and new config array API
        if (is_int($thresholdOrConfig)) {
            $this->config = [
                'threshold' => $thresholdOrConfig,
                'compression' => true,
                'storage' => 'file',
            ];
            $this->threshold = $thresholdOrConfig;
        } else {
            $this->config = array_merge([
                'threshold' => 'auto',
                'compression' => true,
                'storage' => 'file',
            ], $thresholdOrConfig);
            
            $this->threshold = $this->calculateThreshold();
        }
    }

    /**
     * Set memory threshold for switching to external storage
     */
    public function setThreshold(int $threshold): void
    {
        $this->threshold = $threshold;
    }

    /**
     * ArrayAccess: Check if offset exists
     */
    public function offsetExists(mixed $offset): bool
    {
        if (isset($this->hotData[$offset])) {
            return true;
        }
        
        if ($this->coldStorage !== null) {
            return $this->coldStorage->exists((string)$offset);
        }
        
        return false;
    }

    /**
     * ArrayAccess: Get value at offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (isset($this->hotData[$offset])) {
            return $this->hotData[$offset];
        }
        
        if ($this->coldStorage !== null && $this->coldStorage->exists((string)$offset)) {
            return $this->coldStorage->get((string)$offset);
        }
        
        return null;
    }

    /**
     * ArrayAccess: Set value at offset
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $offset = $this->count;
        }
        
        // Check if we need to switch to external storage
        if (count($this->hotData) >= $this->threshold && !isset($this->hotData[$offset])) {
            $this->ensureColdStorage();
            $this->coldStorage->set((string)$offset, $value);
        } else {
            $this->hotData[$offset] = $value;
        }
        
        if (!in_array($offset, $this->allKeys, true)) {
            $this->allKeys[] = $offset;
            $this->count++;
        }
    }

    /**
     * ArrayAccess: Unset offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if (isset($this->hotData[$offset])) {
            unset($this->hotData[$offset]);
        }
        
        if ($this->coldStorage !== null) {
            $this->coldStorage->delete((string)$offset);
        }
        
        $this->allKeys = array_values(array_diff($this->allKeys, [$offset]));
        $this->count--;
    }

    /**
     * Iterator: Rewind to first element
     */
    public function rewind(): void
    {
        $this->iteratorPosition = 0;
    }
    
    /**
     * Get hot data (for testing)
     */
    public function getHotData(): array
    {
        return $this->hotData;
    }
    
    /**
     * Get cold indices (for testing)
     */
    public function getColdIndices(): array
    {
        // In this implementation, cold items are tracked by which keys are not in hotData
        if ($this->coldStorage === null) {
            return [];
        }
        
        $coldKeys = [];
        foreach ($this->allKeys as $key) {
            if (!isset($this->hotData[$key])) {
                $coldKeys[] = $key;
            }
        }
        return $coldKeys;
    }

    /**
     * Iterator: Get current element
     */
    public function current(): mixed
    {
        if (!isset($this->allKeys[$this->iteratorPosition])) {
            return null;
        }
        
        $key = $this->allKeys[$this->iteratorPosition];
        return $this->offsetGet($key);
    }

    /**
     * Iterator: Get current key
     */
    public function key(): mixed
    {
        return $this->allKeys[$this->iteratorPosition] ?? null;
    }

    /**
     * Iterator: Move to next element
     */
    public function next(): void
    {
        $this->iteratorPosition++;
    }

    /**
     * Iterator: Check if current position is valid
     */
    public function valid(): bool
    {
        return isset($this->allKeys[$this->iteratorPosition]);
    }

    /**
     * Countable: Get count of elements
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Process array in √n chunks
     */
    public function chunkBySqrtN(): \Generator
    {
        $chunkSize = SpaceTimeConfig::calculateSqrtN($this->count);
        $chunk = [];
        $chunkCount = 0;
        
        foreach ($this as $key => $value) {
            $chunk[$key] = $value;
            $chunkCount++;
            
            if ($chunkCount >= $chunkSize) {
                yield $chunk;
                $chunk = [];
                $chunkCount = 0;
            }
        }
        
        if (!empty($chunk)) {
            yield $chunk;
        }
    }

    /**
     * Apply callback to each element
     */
    public function map(callable $callback): self
    {
        $result = new self($this->config);
        
        foreach ($this as $key => $value) {
            $result[$key] = $callback($value, $key);
        }
        
        return $result;
    }

    /**
     * Filter elements using callback
     */
    public function filter(callable $callback): self
    {
        $result = new self($this->config);
        
        foreach ($this as $key => $value) {
            if ($callback($value, $key)) {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Reduce array to single value
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $accumulator = $initial;
        
        foreach ($this as $key => $value) {
            $accumulator = $callback($accumulator, $value, $key);
        }
        
        return $accumulator;
    }

    /**
     * Convert to regular array (caution with large datasets!)
     */
    public function toArray(): array
    {
        $result = [];
        
        foreach ($this as $key => $value) {
            $result[$key] = $value;
        }
        
        return $result;
    }

    /**
     * Get memory usage statistics
     */
    public function getStats(): array
    {
        return [
            'total_items' => $this->count,
            'hot_items' => count($this->hotData),
            'cold_items' => $this->count - count($this->hotData),
            'threshold' => $this->threshold,
            'has_cold_storage' => $this->coldStorage !== null,
            'memory_usage' => memory_get_usage(true),
        ];
    }

    /**
     * Calculate threshold based on available memory
     */
    private function calculateThreshold(): int
    {
        if ($this->config['threshold'] === 'auto') {
            $availableMemory = SpaceTimeConfig::getAvailableMemory();
            $avgItemSize = 1024; // Estimate 1KB per item
            return max(100, (int)($availableMemory / $avgItemSize / 10)); // Use 10% of available memory
        }
        
        return (int)$this->config['threshold'];
    }

    /**
     * Ensure cold storage is initialized
     */
    private function ensureColdStorage(): void
    {
        if ($this->coldStorage === null) {
            $this->coldStorage = new ExternalStorage(
                'spacetime_array_' . spl_object_id($this),
                $this->config
            );
            
            // Move some hot data to cold storage if needed
            if (count($this->hotData) > $this->threshold) {
                $toMove = array_slice($this->hotData, 0, count($this->hotData) - $this->threshold, true);
                foreach ($toMove as $key => $value) {
                    $this->coldStorage->set((string)$key, $value);
                    unset($this->hotData[$key]);
                }
            }
        }
    }

    /**
     * Clean up external storage on destruction
     */
    public function __destruct()
    {
        if ($this->coldStorage !== null) {
            $this->coldStorage->cleanup();
        }
    }
}