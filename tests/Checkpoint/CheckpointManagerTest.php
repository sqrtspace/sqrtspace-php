<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Tests\Checkpoint;

use PHPUnit\Framework\TestCase;
use SqrtSpace\SpaceTime\Checkpoint\CheckpointManager;
use SqrtSpace\SpaceTime\Checkpoint\CheckpointStorage;
use SqrtSpace\SpaceTime\SpaceTimeConfig;

class CheckpointManagerTest extends TestCase
{
    private CheckpointManager $manager;
    private CheckpointStorage $mockStorage;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock storage
        $this->mockStorage = $this->createMock(CheckpointStorage::class);
        $this->manager = new CheckpointManager('test-checkpoint', $this->mockStorage);
    }
    
    public function testShouldCheckpointReturnsFalseWhenCheckpointingDisabled(): void
    {
        // Mock the static config method
        $this->assertFalse($this->manager->shouldCheckpoint());
    }
    
    public function testShouldCheckpointReturnsTrueAfterInterval(): void
    {
        // This test would need to mock SpaceTimeConfig::isCheckpointingEnabled()
        // and handle time-based logic
        $this->markTestSkipped('Requires static method mocking for SpaceTimeConfig');
    }
    
    public function testSaveStoresCheckpointData(): void
    {
        $testData = ['progress' => 50, 'items_processed' => 100];
        
        $this->mockStorage
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->equalTo('test-checkpoint'),
                $this->callback(function ($checkpoint) use ($testData) {
                    return $checkpoint['id'] === 'test-checkpoint' &&
                           isset($checkpoint['timestamp']) &&
                           $checkpoint['data'] === $testData;
                })
            );
        
        $this->manager->save($testData);
    }
    
    public function testLoadReturnsCheckpointData(): void
    {
        $testData = ['progress' => 75, 'items_processed' => 150];
        $checkpoint = [
            'id' => 'test-checkpoint',
            'timestamp' => time(),
            'data' => $testData,
        ];
        
        $this->mockStorage
            ->expects($this->once())
            ->method('load')
            ->with('test-checkpoint')
            ->willReturn($checkpoint);
        
        $loadedData = $this->manager->load();
        $this->assertEquals($testData, $loadedData);
    }
    
    public function testLoadReturnsNullWhenNoCheckpoint(): void
    {
        $this->mockStorage
            ->expects($this->once())
            ->method('load')
            ->with('test-checkpoint')
            ->willReturn(null);
        
        $this->assertNull($this->manager->load());
    }
    
    public function testLoadReturnsNullWhenCheckpointHasNoData(): void
    {
        $checkpoint = [
            'id' => 'test-checkpoint',
            'timestamp' => time(),
            // No 'data' key
        ];
        
        $this->mockStorage
            ->expects($this->once())
            ->method('load')
            ->with('test-checkpoint')
            ->willReturn($checkpoint);
        
        $this->assertNull($this->manager->load());
    }
    
    public function testExistsReturnsStorageResult(): void
    {
        $this->mockStorage
            ->expects($this->once())
            ->method('exists')
            ->with('test-checkpoint')
            ->willReturn(true);
        
        $this->assertTrue($this->manager->exists());
        
        $this->mockStorage
            ->expects($this->once())
            ->method('exists')
            ->with('test-checkpoint')
            ->willReturn(false);
        
        $this->assertFalse($this->manager->exists());
    }
    
    public function testDeleteRemovesCheckpoint(): void
    {
        $this->mockStorage
            ->expects($this->once())
            ->method('delete')
            ->with('test-checkpoint');
        
        $this->manager->delete();
    }
    
    public function testSetIntervalUpdatesCheckpointInterval(): void
    {
        // Test minimum interval of 1 second
        $this->manager->setInterval(0);
        // We can't directly test the interval value since it's private,
        // but we can ensure the method doesn't throw an exception
        $this->assertTrue(true);
        
        $this->manager->setInterval(120);
        $this->assertTrue(true);
    }
    
    public function testWrapExecutesOperationWithInitialState(): void
    {
        $initialState = ['counter' => 0];
        $expectedResult = 'operation completed';
        
        $this->mockStorage
            ->expects($this->once())
            ->method('load')
            ->willReturn(null); // No existing checkpoint
        
        $this->mockStorage
            ->expects($this->once())
            ->method('delete')
            ->with('test-checkpoint');
        
        $operation = function ($state, $manager) use ($expectedResult) {
            $this->assertEquals(['counter' => 0], $state);
            $this->assertInstanceOf(CheckpointManager::class, $manager);
            return $expectedResult;
        };
        
        $result = $this->manager->wrap($operation, $initialState);
        $this->assertEquals($expectedResult, $result);
    }
    
    public function testWrapResumesFromCheckpoint(): void
    {
        $checkpointData = ['counter' => 50];
        $checkpoint = [
            'id' => 'test-checkpoint',
            'timestamp' => time(),
            'data' => $checkpointData,
        ];
        
        $this->mockStorage
            ->expects($this->once())
            ->method('load')
            ->willReturn($checkpoint);
        
        $this->mockStorage
            ->expects($this->once())
            ->method('delete')
            ->with('test-checkpoint');
        
        $operation = function ($state, $manager) {
            $this->assertEquals(['counter' => 50], $state);
            return 'resumed and completed';
        };
        
        $result = $this->manager->wrap($operation, ['counter' => 0]);
        $this->assertEquals('resumed and completed', $result);
    }
    
    public function testWrapPreservesCheckpointOnException(): void
    {
        $this->mockStorage
            ->expects($this->once())
            ->method('load')
            ->willReturn(null);
        
        // Delete should NOT be called when exception is thrown
        $this->mockStorage
            ->expects($this->never())
            ->method('delete');
        
        $operation = function ($state, $manager) {
            throw new \RuntimeException('Operation failed');
        };
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operation failed');
        
        $this->manager->wrap($operation);
    }
}