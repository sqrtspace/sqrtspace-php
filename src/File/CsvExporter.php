<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\File;

use SqrtSpace\SpaceTime\SpaceTimeConfig;

/**
 * Memory-efficient CSV exporter
 */
class CsvExporter
{
    private string $filename;
    private array $options;
    private $handle;
    private bool $headersWritten = false;
    
    public function __construct(string $filename, array $options = [])
    {
        $this->filename = $filename;
        $this->options = array_merge([
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'headers' => true,
            'encoding' => 'UTF-8',
            'append' => false,
        ], $options);
        
        $this->open();
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    /**
     * Write single row
     */
    public function writeRow(array $row): void
    {
        if ($this->options['headers'] && !$this->headersWritten) {
            $this->writeHeaders(array_keys($row));
        }
        
        fputcsv(
            $this->handle,
            array_values($row),
            $this->options['delimiter'],
            $this->options['enclosure'],
            $this->options['escape']
        );
    }
    
    /**
     * Write multiple rows
     */
    public function writeRows(iterable $rows): void
    {
        foreach ($rows as $row) {
            $this->writeRow($row);
        }
    }
    
    /**
     * Write rows in √n chunks
     */
    public function writeInChunks(iterable $data, ?int $totalCount = null): void
    {
        if ($totalCount === null && is_array($data)) {
            $totalCount = count($data);
        }
        
        $chunkSize = $totalCount ? SpaceTimeConfig::calculateSqrtN($totalCount) : 1000;
        $buffer = [];
        
        foreach ($data as $row) {
            $buffer[] = $row;
            
            if (count($buffer) >= $chunkSize) {
                $this->flushBuffer($buffer);
                $buffer = [];
            }
        }
        
        // Write remaining rows
        if (!empty($buffer)) {
            $this->flushBuffer($buffer);
        }
    }
    
    /**
     * Write from query results
     */
    public function writeFromQuery(\PDOStatement $statement): int
    {
        $count = 0;
        
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $this->writeRow($row);
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Write with transformation
     */
    public function writeWithTransform(iterable $data, callable $transformer): void
    {
        foreach ($data as $row) {
            $transformed = $transformer($row);
            if ($transformed !== null) {
                $this->writeRow($transformed);
            }
        }
    }
    
    /**
     * Write headers explicitly
     */
    public function writeHeaders(array $headers): void
    {
        if (!$this->headersWritten) {
            fputcsv(
                $this->handle,
                $headers,
                $this->options['delimiter'],
                $this->options['enclosure'],
                $this->options['escape']
            );
            $this->headersWritten = true;
        }
    }
    
    /**
     * Flush and sync to disk
     */
    public function flush(): void
    {
        if ($this->handle) {
            fflush($this->handle);
        }
    }
    
    /**
     * Get bytes written
     */
    public function getBytesWritten(): int
    {
        if ($this->handle) {
            $stat = fstat($this->handle);
            return $stat['size'] ?? 0;
        }
        return 0;
    }
    
    /**
     * Open file handle
     */
    private function open(): void
    {
        $mode = $this->options['append'] ? 'a' : 'w';
        $this->handle = fopen($this->filename, $mode);
        
        if (!$this->handle) {
            throw new \RuntimeException("Cannot open file for writing: {$this->filename}");
        }
        
        // Write BOM for UTF-8 if needed
        if (!$this->options['append'] && $this->options['encoding'] === 'UTF-8-BOM') {
            fwrite($this->handle, "\xEF\xBB\xBF");
        }
    }
    
    /**
     * Close file handle
     */
    private function close(): void
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }
    
    /**
     * Flush buffer to file
     */
    private function flushBuffer(array $buffer): void
    {
        foreach ($buffer as $row) {
            $this->writeRow($row);
        }
        $this->flush();
    }
}