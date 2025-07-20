<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime;

/**
 * Global configuration for SpaceTime operations
 */
class SpaceTimeConfig
{
    private static array $config = [
        'memory_limit' => 134217728, // 128MB default
        'external_storage_path' => null,
        'chunk_strategy' => 'sqrt_n',
        'enable_checkpointing' => true,
        'checkpoint_interval' => 'auto',
        'compression' => true,
        'compression_level' => 6,
        'storage_driver' => 'file',
        'enable_profiling' => false,
    ];

    private static array $storageDrivers = [];
    private static ?string $tempPath = null;

    /**
     * Configure SpaceTime globally
     */
    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
        
        // Convert memory limit string to bytes if needed
        if (is_string(self::$config['memory_limit'])) {
            self::$config['memory_limit'] = self::parseMemoryLimit(self::$config['memory_limit']);
        }
    }

    /**
     * Get configuration value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * Set configuration value
     */
    public static function set(string $key, mixed $value): void
    {
        self::$config[$key] = $value;
    }

    /**
     * Get memory limit in bytes
     */
    public static function getMemoryLimit(): int
    {
        return (int) self::$config['memory_limit'];
    }

    /**
     * Get external storage path
     */
    public static function getStoragePath(): string
    {
        if (self::$config['external_storage_path'] === null) {
            if (self::$tempPath === null) {
                self::$tempPath = sys_get_temp_dir() . '/spacetime_' . getmypid();
                if (!is_dir(self::$tempPath)) {
                    mkdir(self::$tempPath, 0777, true);
                }
            }
            return self::$tempPath;
        }
        
        return self::$config['external_storage_path'];
    }

    /**
     * Calculate √n for a given size
     */
    public static function calculateSqrtN(int $n): int
    {
        return max(1, (int) sqrt($n));
    }

    /**
     * Calculate optimal chunk size based on available memory
     */
    public static function calculateOptimalChunkSize(int $totalItems, int $itemSize = 1024): int
    {
        $availableMemory = self::getAvailableMemory();
        $memoryLimit = self::getMemoryLimit();
        $useableMemory = min($availableMemory, $memoryLimit) * 0.8; // Use 80% of available
        
        $strategy = self::$config['chunk_strategy'];
        
        return match ($strategy) {
            'sqrt_n' => self::calculateSqrtN($totalItems),
            'memory_based' => max(1, (int) ($useableMemory / $itemSize)),
            'fixed' => 1000,
            default => self::calculateSqrtN($totalItems),
        };
    }

    /**
     * Get available memory
     */
    public static function getAvailableMemory(): int
    {
        $limit = self::parseMemoryLimit(ini_get('memory_limit'));
        $used = memory_get_usage(true);
        
        if ($limit === -1) {
            // No memory limit, use 1GB as reasonable default
            return 1073741824 - $used;
        }
        
        return max(0, $limit - $used);
    }

    /**
     * Parse memory limit string to bytes
     */
    private static function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        
        if ($limit === '-1') {
            return -1;
        }
        
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Register a storage driver
     */
    public static function registerStorageDriver(string $name, string $class): void
    {
        self::$storageDrivers[$name] = $class;
    }

    /**
     * Get storage driver class
     */
    public static function getStorageDriver(string $name): ?string
    {
        return self::$storageDrivers[$name] ?? null;
    }

    /**
     * Cleanup temporary files
     */
    public static function cleanup(): void
    {
        if (self::$tempPath !== null && is_dir(self::$tempPath)) {
            self::recursiveRemove(self::$tempPath);
            self::$tempPath = null;
        }
    }

    /**
     * Recursively remove directory
     */
    private static function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::recursiveRemove($path) : unlink($path);
        }
        rmdir($dir);
    }
}