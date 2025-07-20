<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Memory\Handlers;

use SqrtSpace\SpaceTime\Memory\MemoryPressureHandler;
use SqrtSpace\SpaceTime\Memory\MemoryPressureLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Log memory pressure events
 */
class LoggingHandler implements MemoryPressureHandler
{
    private LoggerInterface $logger;
    private MemoryPressureLevel $minLevel;
    
    public function __construct(
        ?LoggerInterface $logger = null,
        MemoryPressureLevel $minLevel = MemoryPressureLevel::MEDIUM
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->minLevel = $minLevel;
    }
    
    public function shouldHandle(MemoryPressureLevel $level): bool
    {
        return $level->isHigherThan($this->minLevel) || $level === $this->minLevel;
    }
    
    public function handle(MemoryPressureLevel $level, array $memoryInfo): void
    {
        $context = [
            'level' => $level->value,
            'usage' => $this->formatBytes($memoryInfo['usage']),
            'limit' => $this->formatBytes($memoryInfo['limit']),
            'percentage' => round($memoryInfo['percentage'], 2),
            'available' => $this->formatBytes($memoryInfo['available']),
        ];
        
        match ($level) {
            MemoryPressureLevel::CRITICAL => $this->logger->critical('Critical memory pressure detected', $context),
            MemoryPressureLevel::HIGH => $this->logger->error('High memory pressure detected', $context),
            MemoryPressureLevel::MEDIUM => $this->logger->warning('Medium memory pressure detected', $context),
            MemoryPressureLevel::LOW => $this->logger->info('Low memory pressure detected', $context),
            default => $this->logger->debug('Memory pressure check', $context),
        };
    }
    
    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string)(int)$bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}