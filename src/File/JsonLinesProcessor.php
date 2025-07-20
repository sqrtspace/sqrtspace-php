<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\File;

use SqrtSpace\SpaceTime\Streams\SpaceTimeStream;
use SqrtSpace\SpaceTime\SpaceTimeConfig;

/**
 * Process JSON Lines (JSONL) files efficiently
 */
class JsonLinesProcessor
{
    /**
     * Read JSONL file as stream
     */
    public static function read(string $filename): SpaceTimeStream
    {
        return SpaceTimeStream::fromFile($filename)
            ->map(fn($line) => json_decode($line, true))
            ->filter(fn($data) => $data !== null);
    }
    
    /**
     * Write data to JSONL file
     */
    public static function write(iterable $data, string $filename, bool $append = false): int
    {
        $mode = $append ? 'a' : 'w';
        $handle = fopen($filename, $mode);
        
        if (!$handle) {
            throw new \RuntimeException("Cannot open file for writing: $filename");
        }
        
        $count = 0;
        
        try {
            foreach ($data as $item) {
                $json = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($json === false) {
                    throw new \RuntimeException('JSON encoding failed: ' . json_last_error_msg());
                }
                
                fwrite($handle, $json . "\n");
                $count++;
            }
        } finally {
            fclose($handle);
        }
        
        return $count;
    }
    
    /**
     * Process JSONL file in √n chunks
     */
    public static function processInChunks(string $filename, callable $processor): void
    {
        $totalLines = self::countLines($filename);
        $chunkSize = SpaceTimeConfig::calculateSqrtN($totalLines);
        
        self::read($filename)
            ->chunk($chunkSize)
            ->each($processor);
    }
    
    /**
     * Merge multiple JSONL files
     */
    public static function merge(array $filenames, string $outputFile): int
    {
        $count = 0;
        $handle = fopen($outputFile, 'w');
        
        if (!$handle) {
            throw new \RuntimeException("Cannot open output file: $outputFile");
        }
        
        try {
            foreach ($filenames as $filename) {
                $stream = self::read($filename);
                
                $stream->each(function($item) use ($handle, &$count) {
                    fwrite($handle, json_encode($item) . "\n");
                    $count++;
                });
            }
        } finally {
            fclose($handle);
        }
        
        return $count;
    }
    
    /**
     * Split JSONL file into multiple files
     */
    public static function split(string $filename, int $linesPerFile, string $outputPrefix): array
    {
        $files = [];
        $fileIndex = 0;
        $currentLines = 0;
        $currentHandle = null;
        
        try {
            self::read($filename)->each(function($item) use (
                &$files,
                &$fileIndex,
                &$currentLines,
                &$currentHandle,
                $linesPerFile,
                $outputPrefix
            ) {
                // Open new file if needed
                if ($currentLines === 0) {
                    $outputFile = sprintf('%s_%04d.jsonl', $outputPrefix, $fileIndex);
                    $currentHandle = fopen($outputFile, 'w');
                    $files[] = $outputFile;
                }
                
                // Write line
                fwrite($currentHandle, json_encode($item) . "\n");
                $currentLines++;
                
                // Close file if limit reached
                if ($currentLines >= $linesPerFile) {
                    fclose($currentHandle);
                    $currentHandle = null;
                    $currentLines = 0;
                    $fileIndex++;
                }
            });
            
            // Close last file if open
            if ($currentHandle) {
                fclose($currentHandle);
            }
        } catch (\Exception $e) {
            // Clean up on error
            if ($currentHandle) {
                fclose($currentHandle);
            }
            throw $e;
        }
        
        return $files;
    }
    
    /**
     * Filter JSONL file
     */
    public static function filter(string $inputFile, string $outputFile, callable $predicate): int
    {
        $count = 0;
        
        $filtered = self::read($inputFile)
            ->filter($predicate)
            ->toArray();
        
        return self::write($filtered, $outputFile);
    }
    
    /**
     * Transform JSONL file
     */
    public static function transform(string $inputFile, string $outputFile, callable $transformer): int
    {
        $transformed = self::read($inputFile)
            ->map($transformer)
            ->filter(fn($item) => $item !== null);
        
        return self::write($transformed, $outputFile);
    }
    
    /**
     * Count lines in file
     */
    private static function countLines(string $filename): int
    {
        $count = 0;
        $handle = fopen($filename, 'r');
        
        if (!$handle) {
            throw new \RuntimeException("Cannot open file: $filename");
        }
        
        while (!feof($handle)) {
            fgets($handle);
            $count++;
        }
        
        fclose($handle);
        
        return $count;
    }
}