<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Checkpoint;

use SqrtSpace\SpaceTime\SpaceTimeConfig;

/**
 * File-based checkpoint storage
 */
class FileCheckpointStorage implements CheckpointStorage
{
    private string $basePath;
    
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? SpaceTimeConfig::getStoragePath() . '/checkpoints';
        
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }
    
    public function save(string $id, array $data): void
    {
        $filename = $this->getFilename($id);
        $content = serialize($data);
        
        if (SpaceTimeConfig::isCompressionEnabled()) {
            $content = gzcompress($content, SpaceTimeConfig::getCompressionLevel());
        }
        
        file_put_contents($filename, $content, LOCK_EX);
    }
    
    public function load(string $id): ?array
    {
        $filename = $this->getFilename($id);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $content = file_get_contents($filename);
        
        if (SpaceTimeConfig::isCompressionEnabled()) {
            $content = gzuncompress($content);
        }
        
        return unserialize($content);
    }
    
    public function exists(string $id): bool
    {
        return file_exists($this->getFilename($id));
    }
    
    public function delete(string $id): void
    {
        $filename = $this->getFilename($id);
        
        if (file_exists($filename)) {
            unlink($filename);
        }
    }
    
    public function list(): array
    {
        $checkpoints = [];
        $files = glob($this->basePath . '/*.checkpoint');
        
        foreach ($files as $file) {
            $id = basename($file, '.checkpoint');
            $checkpoints[] = [
                'id' => $id,
                'timestamp' => filemtime($file),
                'size' => filesize($file),
            ];
        }
        
        return $checkpoints;
    }
    
    public function cleanup(int $olderThanTimestamp): int
    {
        $count = 0;
        $files = glob($this->basePath . '/*.checkpoint');
        
        foreach ($files as $file) {
            if (filemtime($file) < $olderThanTimestamp) {
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
    
    private function getFilename(string $id): string
    {
        // Sanitize ID for filesystem
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $id);
        return $this->basePath . '/' . $safeId . '.checkpoint';
    }
}