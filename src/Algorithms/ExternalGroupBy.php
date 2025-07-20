<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Algorithms;

use SqrtSpace\SpaceTime\Collections\SpaceTimeArray;
use SqrtSpace\SpaceTime\SpaceTimeConfig;
use SqrtSpace\SpaceTime\Storage\ExternalStorage;

/**
 * External group by algorithm using √n memory
 */
class ExternalGroupBy
{
    /**
     * Group data by key using external storage
     */
    public static function groupBy(iterable $data, callable $keyExtractor): array
    {
        $groups = new ExternalStorage('groupby_' . uniqid());
        $groupKeys = [];
        
        try {
            // First pass: distribute items to groups
            foreach ($data as $item) {
                $key = (string) $keyExtractor($item);
                
                if (!in_array($key, $groupKeys, true)) {
                    $groupKeys[] = $key;
                }
                
                // Get existing group or create new one
                $group = $groups->exists($key) ? $groups->get($key) : [];
                $group[] = $item;
                $groups->set($key, $group);
            }
            
            // Build result array
            $result = [];
            foreach ($groupKeys as $key) {
                $result[$key] = $groups->get($key);
            }
            
            return $result;
        } finally {
            $groups->cleanup();
        }
    }

    /**
     * Group by with aggregation
     */
    public static function groupByAggregate(
        iterable $data,
        callable $keyExtractor,
        callable $aggregator,
        mixed $initial = null
    ): array {
        $aggregates = [];
        
        foreach ($data as $item) {
            $key = (string) $keyExtractor($item);
            
            if (!isset($aggregates[$key])) {
                $aggregates[$key] = $initial;
            }
            
            $aggregates[$key] = $aggregator($aggregates[$key], $item);
        }
        
        return $aggregates;
    }

    /**
     * Group by with counting
     */
    public static function groupByCount(iterable $data, callable $keyExtractor): array
    {
        return self::groupByAggregate(
            $data,
            $keyExtractor,
            fn($count, $item) => ($count ?? 0) + 1,
            0
        );
    }

    /**
     * Group by with sum
     */
    public static function groupBySum(
        iterable $data,
        callable $keyExtractor,
        callable $valueExtractor
    ): array {
        return self::groupByAggregate(
            $data,
            $keyExtractor,
            fn($sum, $item) => ($sum ?? 0) + $valueExtractor($item),
            0
        );
    }

    /**
     * Group by with streaming output
     */
    public static function groupByStreaming(iterable $data, callable $keyExtractor): \Generator
    {
        $groups = new ExternalStorage('groupby_stream_' . uniqid());
        $seenKeys = [];
        
        try {
            // Collect all data
            foreach ($data as $item) {
                $key = (string) $keyExtractor($item);
                
                if (!in_array($key, $seenKeys, true)) {
                    $seenKeys[] = $key;
                }
                
                $group = $groups->exists($key) ? $groups->get($key) : [];
                $group[] = $item;
                $groups->set($key, $group);
            }
            
            // Stream results
            foreach ($seenKeys as $key) {
                yield $key => $groups->get($key);
            }
        } finally {
            $groups->cleanup();
        }
    }

    /**
     * Group by with memory limit
     */
    public static function groupByWithLimit(
        iterable $data,
        callable $keyExtractor,
        int $maxGroupsInMemory = 1000
    ): array {
        $inMemoryGroups = [];
        $externalGroups = null;
        $allKeys = [];
        
        foreach ($data as $item) {
            $key = (string) $keyExtractor($item);
            
            if (!in_array($key, $allKeys, true)) {
                $allKeys[] = $key;
            }
            
            // Use in-memory storage for small number of groups
            if (count($inMemoryGroups) < $maxGroupsInMemory && !isset($externalGroups)) {
                if (!isset($inMemoryGroups[$key])) {
                    $inMemoryGroups[$key] = [];
                }
                $inMemoryGroups[$key][] = $item;
            } else {
                // Switch to external storage
                if ($externalGroups === null) {
                    $externalGroups = new ExternalStorage('groupby_limit_' . uniqid());
                    
                    // Move existing groups to external storage
                    foreach ($inMemoryGroups as $k => $group) {
                        $externalGroups->set($k, $group);
                    }
                    $inMemoryGroups = [];
                }
                
                $group = $externalGroups->exists($key) ? $externalGroups->get($key) : [];
                $group[] = $item;
                $externalGroups->set($key, $group);
            }
        }
        
        // Build result
        $result = [];
        
        if ($externalGroups === null) {
            // All groups fit in memory
            return $inMemoryGroups;
        }
        
        // Retrieve from external storage
        try {
            foreach ($allKeys as $key) {
                $result[$key] = $externalGroups->get($key);
            }
            return $result;
        } finally {
            $externalGroups->cleanup();
        }
    }
}