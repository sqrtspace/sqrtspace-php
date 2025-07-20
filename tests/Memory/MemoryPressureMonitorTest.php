<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Tests\Memory;

use PHPUnit\Framework\TestCase;
use SqrtSpace\SpaceTime\Memory\MemoryPressureMonitor;
use SqrtSpace\SpaceTime\Memory\MemoryPressureLevel;
use SqrtSpace\SpaceTime\Memory\MemoryPressureHandler;

class MemoryPressureMonitorTest extends TestCase
{
    public function testMemoryPressureLevels(): void
    {
        $monitor = new MemoryPressureMonitor('100M');
        
        // Get current level
        $level = $monitor->getCurrentLevel();
        $this->assertInstanceOf(MemoryPressureLevel::class, $level);
    }
    
    public function testMemoryInfo(): void
    {
        $monitor = new MemoryPressureMonitor();
        $info = $monitor->getMemoryInfo();
        
        $this->assertArrayHasKey('limit', $info);
        $this->assertArrayHasKey('usage', $info);
        $this->assertArrayHasKey('percentage', $info);
        $this->assertArrayHasKey('available', $info);
        
        $this->assertGreaterThan(0, $info['limit']);
        $this->assertGreaterThanOrEqual(0, $info['usage']);
        $this->assertGreaterThanOrEqual(0, $info['percentage']);
        $this->assertLessThanOrEqual(100, $info['percentage']);
    }
    
    public function testHandlerRegistration(): void
    {
        $monitor = new MemoryPressureMonitor();
        
        $handlerCalled = false;
        $handler = new class($handlerCalled) implements MemoryPressureHandler {
            private $called;
            
            public function __construct(&$called)
            {
                $this->called = &$called;
            }
            
            public function shouldHandle(MemoryPressureLevel $level): bool
            {
                return true;
            }
            
            public function handle(MemoryPressureLevel $level, array $memoryInfo): void
            {
                $this->called = true;
            }
        };
        
        $monitor->registerHandler($handler);
        $monitor->check();
        
        $this->assertTrue($handlerCalled);
    }
    
    public function testMemoryLimitParsing(): void
    {
        // Test various memory limit formats
        $testCases = [
            '256M' => 256 * 1024 * 1024,
            '1G' => 1024 * 1024 * 1024,
            '512K' => 512 * 1024,
            '1024' => 1024,
        ];
        
        foreach ($testCases as $limit => $expected) {
            $monitor = new MemoryPressureMonitor($limit);
            $info = $monitor->getMemoryInfo();
            $this->assertEquals($expected, $info['limit']);
        }
    }
    
    public function testPressureLevelComparison(): void
    {
        $this->assertTrue(MemoryPressureLevel::HIGH->isHigherThan(MemoryPressureLevel::MEDIUM));
        $this->assertTrue(MemoryPressureLevel::CRITICAL->isHigherThan(MemoryPressureLevel::HIGH));
        $this->assertFalse(MemoryPressureLevel::LOW->isHigherThan(MemoryPressureLevel::MEDIUM));
        $this->assertFalse(MemoryPressureLevel::NONE->isHigherThan(MemoryPressureLevel::LOW));
    }
}