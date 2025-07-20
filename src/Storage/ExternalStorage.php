<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Storage;

use SqrtSpace\SpaceTime\SpaceTimeConfig;

/**
 * External storage for data that doesn't fit in memory
 */
class ExternalStorage
{
    private string $prefix;
    private string $basePath;
    private array $config;
    private array $index = [];
    private int $fileCounter = 0;
    private $currentFile = null;
    private string $currentFilePath = '';
    private int $currentFileSize = 0;
    private const MAX_FILE_SIZE = 10485760; // 10MB per file

    public function __construct(string $prefix, array $config = [])
    {
        $this->prefix = $prefix;
        $this->config = array_merge([
            'compression' => true,
            'compression_level' => 6,
        ], $config);
        
        $this->basePath = SpaceTimeConfig::getStoragePath();
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0777, true);
        }
    }

    /**
     * Store a value
     */
    public function set(string $key, mixed $value): void
    {
        $serialized = serialize($value);
        
        if ($this->config['compression']) {
            $serialized = gzcompress($serialized, $this->config['compression_level']);
        }
        
        $length = strlen($serialized);
        
        // Check if we need a new file
        if ($this->currentFile === null || $this->currentFileSize + $length > self::MAX_FILE_SIZE) {
            $this->rotateFile();
        }
        
        $offset = ftell($this->currentFile);
        fwrite($this->currentFile, $serialized);
        
        $this->index[$key] = [
            'file' => $this->currentFilePath,
            'offset' => $offset,
            'length' => $length,
        ];
        
        $this->currentFileSize += $length;
    }

    /**
     * Retrieve a value
     */
    public function get(string $key): mixed
    {
        if (!isset($this->index[$key])) {
            return null;
        }
        
        $info = $this->index[$key];
        $handle = fopen($info['file'], 'rb');
        
        if (!$handle) {
            return null;
        }
        
        fseek($handle, $info['offset']);
        $data = fread($handle, $info['length']);
        fclose($handle);
        
        if ($this->config['compression']) {
            $data = gzuncompress($data);
        }
        
        return unserialize($data);
    }

    /**
     * Check if key exists
     */
    public function exists(string $key): bool
    {
        return isset($this->index[$key]);
    }

    /**
     * Delete a value
     */
    public function delete(string $key): void
    {
        unset($this->index[$key]);
        // Note: We don't actually remove from file to avoid fragmentation
        // Files are cleaned up when the storage is destroyed
    }

    /**
     * Get all keys
     */
    public function keys(): array
    {
        return array_keys($this->index);
    }

    /**
     * Get storage statistics
     */
    public function getStats(): array
    {
        $totalSize = 0;
        $fileCount = 0;
        
        foreach (glob($this->basePath . '/' . $this->prefix . '_*.dat') as $file) {
            $totalSize += filesize($file);
            $fileCount++;
        }
        
        return [
            'keys' => count($this->index),
            'files' => $fileCount,
            'total_size' => $totalSize,
            'compression' => $this->config['compression'],
        ];
    }

    /**
     * Rotate to a new file
     */
    private function rotateFile(): void
    {
        if ($this->currentFile !== null) {
            fclose($this->currentFile);
        }
        
        $this->fileCounter++;
        $this->currentFilePath = $this->basePath . '/' . $this->prefix . '_' . $this->fileCounter . '.dat';
        $this->currentFile = fopen($this->currentFilePath, 'wb');
        $this->currentFileSize = 0;
        
        if (!$this->currentFile) {
            throw new \RuntimeException("Failed to create storage file: {$this->currentFilePath}");
        }
    }

    /**
     * Clean up all storage files
     */
    public function cleanup(): void
    {
        if ($this->currentFile !== null) {
            fclose($this->currentFile);
            $this->currentFile = null;
        }
        
        foreach (glob($this->basePath . '/' . $this->prefix . '_*.dat') as $file) {
            unlink($file);
        }
        
        $this->index = [];
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->currentFile !== null) {
            fclose($this->currentFile);
        }
    }
}