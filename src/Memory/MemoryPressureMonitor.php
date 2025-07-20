<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Memory;

/**
 * Monitor system memory pressure and trigger appropriate responses
 */
class MemoryPressureMonitor
{
    private float $memoryLimit;
    private array $handlers = [];
    private float $lastCheck = 0;
    private float $checkInterval = 1.0; // seconds
    
    public function __construct($memoryLimit = null)
    {
        if (is_int($memoryLimit)) {
            $this->memoryLimit = (float) $memoryLimit;
        } else {
            $this->memoryLimit = $this->parseMemoryLimit($memoryLimit ?? ini_get('memory_limit'));
        }
    }
    
    /**
     * Register a pressure handler
     */
    public function registerHandler(MemoryPressureHandler $handler): void
    {
        $this->handlers[] = $handler;
    }
    
    /**
     * Check current memory pressure
     */
    public function check(): MemoryPressureLevel
    {
        $now = microtime(true);
        
        // Throttle checks
        if ($now - $this->lastCheck < $this->checkInterval) {
            return $this->getCurrentLevel();
        }
        
        $this->lastCheck = $now;
        $level = $this->getCurrentLevel();
        
        // Notify handlers
        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($level)) {
                $handler->handle($level, $this->getMemoryInfo());
            }
        }
        
        return $level;
    }
    
    /**
     * Get current memory pressure level
     */
    public function getCurrentLevel(): MemoryPressureLevel
    {
        $usage = memory_get_usage(true);
        $percentage = ($usage / $this->memoryLimit) * 100;
        
        if ($percentage >= 95) {
            return MemoryPressureLevel::CRITICAL;
        } elseif ($percentage >= 85) {
            return MemoryPressureLevel::HIGH;
        } elseif ($percentage >= 70) {
            return MemoryPressureLevel::MEDIUM;
        } elseif ($percentage >= 50) {
            return MemoryPressureLevel::LOW;
        }
        
        return MemoryPressureLevel::NONE;
    }
    
    /**
     * Get detailed memory information
     */
    public function getMemoryInfo(): array
    {
        $usage = memory_get_usage(true);
        $realUsage = memory_get_usage(false);
        
        return [
            'limit' => $this->memoryLimit,
            'usage' => $usage,
            'real_usage' => $realUsage,
            'percentage' => ($usage / $this->memoryLimit) * 100,
            'available' => $this->memoryLimit - $usage,
            'peak_usage' => memory_get_peak_usage(true),
            'peak_real_usage' => memory_get_peak_usage(false),
        ];
    }
    
    /**
     * Force garbage collection if possible
     */
    public function forceCleanup(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): float
    {
        $limit = trim($limit);
        
        if ($limit === '-1') {
            return PHP_FLOAT_MAX;
        }
        
        $unit = strtolower($limit[strlen($limit) - 1]);
        $value = (float) $limit;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}

/**
 * Memory pressure levels
 */
enum MemoryPressureLevel: string
{
    case NONE = 'none';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
    
    public function isHigherThan(self $other): bool
    {
        $order = [
            self::NONE->value => 0,
            self::LOW->value => 1,
            self::MEDIUM->value => 2,
            self::HIGH->value => 3,
            self::CRITICAL->value => 4,
        ];
        
        return $order[$this->value] > $order[$other->value];
    }
}

/**
 * Interface for memory pressure handlers
 */
interface MemoryPressureHandler
{
    public function shouldHandle(MemoryPressureLevel $level): bool;
    public function handle(MemoryPressureLevel $level, array $memoryInfo): void;
}