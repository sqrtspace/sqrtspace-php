<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Tests\Algorithms;

use PHPUnit\Framework\TestCase;
use SqrtSpace\SpaceTime\Algorithms\ExternalSort;
use SqrtSpace\SpaceTime\SpaceTimeConfig;

class ExternalSortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        SpaceTimeConfig::configure([
            'external_storage_path' => sys_get_temp_dir() . '/spacetime_test',
        ]);
    }
    
    protected function tearDown(): void
    {
        $path = sys_get_temp_dir() . '/spacetime_test';
        if (is_dir($path)) {
            array_map('unlink', glob("$path/*"));
            rmdir($path);
        }
        
        parent::tearDown();
    }
    
    public function testBasicSort(): void
    {
        $data = [5, 2, 8, 1, 9, 3, 7, 4, 6];
        $sorted = ExternalSort::sort($data);
        
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9], $sorted);
    }
    
    public function testSortWithCustomComparator(): void
    {
        $data = [5, 2, 8, 1, 9, 3, 7, 4, 6];
        $sorted = ExternalSort::sort($data, fn($a, $b) => $b <=> $a);
        
        $this->assertEquals([9, 8, 7, 6, 5, 4, 3, 2, 1], $sorted);
    }
    
    public function testSortBy(): void
    {
        $data = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 30],
            ['name' => 'Bob', 'age' => 20],
        ];
        
        $sorted = ExternalSort::sortBy($data, fn($item) => $item['age']);
        
        $this->assertEquals('Bob', $sorted[0]['name']);
        $this->assertEquals('John', $sorted[1]['name']);
        $this->assertEquals('Jane', $sorted[2]['name']);
    }
    
    public function testLargeDataSet(): void
    {
        // Generate large dataset
        $data = [];
        for ($i = 0; $i < 20000; $i++) {
            $data[] = mt_rand(1, 100000);
        }
        
        $sorted = ExternalSort::sort($data);
        
        // Verify it's sorted
        for ($i = 1; $i < count($sorted); $i++) {
            $this->assertGreaterThanOrEqual($sorted[$i - 1], $sorted[$i]);
        }
        
        // Verify same elements
        $this->assertEquals(count($data), count($sorted));
        sort($data);
        $this->assertEquals($data, $sorted);
    }
    
    public function testSortObjects(): void
    {
        $objects = [
            (object)['id' => 3, 'value' => 'c'],
            (object)['id' => 1, 'value' => 'a'],
            (object)['id' => 2, 'value' => 'b'],
        ];
        
        $sorted = ExternalSort::sortBy($objects, fn($obj) => $obj->id);
        
        $this->assertEquals(1, $sorted[0]->id);
        $this->assertEquals(2, $sorted[1]->id);
        $this->assertEquals(3, $sorted[2]->id);
    }
    
    public function testStreamingSort(): void
    {
        $data = range(10, 1);
        $result = [];
        
        foreach (ExternalSort::sortStreaming($data) as $item) {
            $result[] = $item;
        }
        
        $this->assertEquals(range(1, 10), $result);
    }
}