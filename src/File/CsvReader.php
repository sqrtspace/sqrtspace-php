<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\File;

use SqrtSpace\SpaceTime\Streams\SpaceTimeStream;
use SqrtSpace\SpaceTime\SpaceTimeConfig;

/**
 * Memory-efficient CSV reader
 */
class CsvReader
{
    private string $filename;
    private array $options;
    
    public function __construct(string $filename, array $options = [])
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException("File not found: $filename");
        }
        
        $this->filename = $filename;
        $this->options = array_merge([
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'headers' => true,
            'encoding' => 'UTF-8',
            'skip_empty' => true,
        ], $options);
    }
    
    /**
     * Read CSV as stream
     */
    public function stream(): SpaceTimeStream
    {
        return SpaceTimeStream::fromCsv($this->filename, $this->options);
    }
    
    /**
     * Read CSV in √n chunks
     */
    public function readInChunks(callable $callback): void
    {
        $totalLines = $this->countLines();
        $chunkSize = SpaceTimeConfig::calculateSqrtN($totalLines);
        
        $this->stream()
            ->chunk($chunkSize)
            ->each($callback);
    }
    
    /**
     * Read specific columns only
     */
    public function readColumns(array $columns): SpaceTimeStream
    {
        return $this->stream()->map(function($row) use ($columns) {
            return array_intersect_key($row, array_flip($columns));
        });
    }
    
    /**
     * Read with type conversion
     */
    public function readWithTypes(array $types): SpaceTimeStream
    {
        return $this->stream()->map(function($row) use ($types) {
            foreach ($types as $column => $type) {
                if (isset($row[$column])) {
                    $row[$column] = $this->convertType($row[$column], $type);
                }
            }
            return $row;
        });
    }
    
    /**
     * Get column statistics
     */
    public function getColumnStats(string $column): array
    {
        $stats = [
            'count' => 0,
            'null_count' => 0,
            'unique_count' => 0,
            'min' => null,
            'max' => null,
            'sum' => 0,
            'values' => [],
        ];
        
        $this->stream()->each(function($row) use ($column, &$stats) {
            $stats['count']++;
            
            if (!isset($row[$column]) || $row[$column] === '') {
                $stats['null_count']++;
                return;
            }
            
            $value = $row[$column];
            
            // Track unique values (up to a limit)
            if (count($stats['values']) < 1000) {
                $stats['values'][$value] = ($stats['values'][$value] ?? 0) + 1;
            }
            
            // Numeric stats
            if (is_numeric($value)) {
                $numValue = (float) $value;
                $stats['sum'] += $numValue;
                
                if ($stats['min'] === null || $numValue < $stats['min']) {
                    $stats['min'] = $numValue;
                }
                
                if ($stats['max'] === null || $numValue > $stats['max']) {
                    $stats['max'] = $numValue;
                }
            }
        });
        
        $stats['unique_count'] = count($stats['values']);
        $stats['avg'] = $stats['count'] > 0 ? $stats['sum'] / $stats['count'] : 0;
        
        // Find most common values
        arsort($stats['values']);
        $stats['most_common'] = array_slice($stats['values'], 0, 10, true);
        unset($stats['values']); // Remove full list to save memory
        
        return $stats;
    }
    
    /**
     * Validate CSV structure
     */
    public function validate(): array
    {
        $errors = [];
        $lineNumber = 0;
        $expectedColumns = null;
        
        $this->stream()->each(function($row) use (&$errors, &$lineNumber, &$expectedColumns) {
            $lineNumber++;
            
            if ($expectedColumns === null) {
                $expectedColumns = count($row);
            } elseif (count($row) !== $expectedColumns) {
                $errors[] = [
                    'line' => $lineNumber,
                    'error' => 'Column count mismatch',
                    'expected' => $expectedColumns,
                    'actual' => count($row),
                ];
            }
            
            // Additional validation can be added here
        });
        
        return $errors;
    }
    
    /**
     * Count lines in file
     */
    private function countLines(): int
    {
        $count = 0;
        $handle = fopen($this->filename, 'r');
        
        while (!feof($handle)) {
            fgets($handle);
            $count++;
        }
        
        fclose($handle);
        
        return $count;
    }
    
    /**
     * Convert value to specified type
     */
    private function convertType(mixed $value, string $type): mixed
    {
        return match($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'date' => new \DateTime($value),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}