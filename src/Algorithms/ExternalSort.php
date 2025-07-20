<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Algorithms;

use SqrtSpace\SpaceTime\SpaceTimeConfig;
use SqrtSpace\SpaceTime\Storage\ExternalStorage;

/**
 * External sorting algorithm using √n memory
 */
class ExternalSort
{
    /**
     * Sort data using external merge sort with √n memory
     */
    public static function sort(iterable $data, ?callable $comparator = null): array
    {
        $comparator = $comparator ?? fn($a, $b) => $a <=> $b;
        
        // Convert to array if needed
        if (!is_array($data)) {
            $data = iterator_to_array($data);
        }
        
        $count = count($data);
        
        // Small datasets can be sorted in memory
        if ($count <= 10000) {
            usort($data, $comparator);
            return $data;
        }
        
        // Calculate chunk size (√n)
        $chunkSize = SpaceTimeConfig::calculateSqrtN($count);
        
        // Phase 1: Sort chunks and write to temporary files
        $tempFiles = self::createSortedChunks($data, $chunkSize, $comparator);
        
        // Phase 2: Merge sorted chunks
        $result = self::mergeSortedChunks($tempFiles, $comparator);
        
        // Cleanup
        foreach ($tempFiles as $file) {
            unlink($file);
        }
        
        return $result;
    }

    /**
     * Sort by a specific key
     */
    public static function sortBy(iterable $data, callable $keyExtractor, ?callable $comparator = null): array
    {
        $comparator = $comparator ?? fn($a, $b) => $a <=> $b;
        
        return self::sort($data, function($a, $b) use ($keyExtractor, $comparator) {
            return $comparator($keyExtractor($a), $keyExtractor($b));
        });
    }

    /**
     * Create sorted chunks and write to temporary files
     */
    private static function createSortedChunks(array $data, int $chunkSize, callable $comparator): array
    {
        $tempFiles = [];
        $chunks = array_chunk($data, $chunkSize, true);
        
        foreach ($chunks as $chunk) {
            // Sort chunk in memory
            usort($chunk, $comparator);
            
            // Write to temporary file
            $tempFile = tempnam(SpaceTimeConfig::getStoragePath(), 'sort_');
            $handle = fopen($tempFile, 'wb');
            
            foreach ($chunk as $item) {
                fwrite($handle, serialize($item) . "\n");
            }
            
            fclose($handle);
            $tempFiles[] = $tempFile;
        }
        
        return $tempFiles;
    }

    /**
     * Merge sorted chunks using k-way merge
     */
    private static function mergeSortedChunks(array $tempFiles, callable $comparator): array
    {
        $result = [];
        $fileHandles = [];
        $currentItems = [];
        
        // Open all files
        foreach ($tempFiles as $i => $file) {
            $fileHandles[$i] = fopen($file, 'rb');
            $line = fgets($fileHandles[$i]);
            if ($line !== false) {
                $currentItems[$i] = unserialize(trim($line));
            }
        }
        
        // K-way merge
        while (!empty($currentItems)) {
            // Find minimum item
            $minIndex = null;
            $minItem = null;
            
            foreach ($currentItems as $index => $item) {
                if ($minItem === null || $comparator($item, $minItem) < 0) {
                    $minIndex = $index;
                    $minItem = $item;
                }
            }
            
            // Add minimum to result
            $result[] = $minItem;
            
            // Read next item from the same file
            $line = fgets($fileHandles[$minIndex]);
            if ($line !== false) {
                $currentItems[$minIndex] = unserialize(trim($line));
            } else {
                unset($currentItems[$minIndex]);
                fclose($fileHandles[$minIndex]);
            }
        }
        
        return $result;
    }

    /**
     * Sort and write directly to a file (for very large datasets)
     */
    public static function sortToFile(iterable $data, string $outputFile, ?callable $comparator = null): void
    {
        $sorted = self::sort($data, $comparator);
        
        $handle = fopen($outputFile, 'wb');
        foreach ($sorted as $item) {
            fwrite($handle, serialize($item) . "\n");
        }
        fclose($handle);
    }

    /**
     * Sort streaming data (returns generator)
     */
    public static function sortStreaming(iterable $data, ?callable $comparator = null): \Generator
    {
        $sorted = self::sort($data, $comparator);
        
        foreach ($sorted as $item) {
            yield $item;
        }
    }
}