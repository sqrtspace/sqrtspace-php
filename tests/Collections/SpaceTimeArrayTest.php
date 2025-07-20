<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Tests\Collections;

use PHPUnit\Framework\TestCase;
use SqrtSpace\SpaceTime\Collections\SpaceTimeArray;
use SqrtSpace\SpaceTime\SpaceTimeConfig;

class SpaceTimeArrayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Configure for testing
        SpaceTimeConfig::configure([
            'memory_limit' => '10M',
            'external_storage_path' => sys_get_temp_dir() . '/spacetime_test',
        ]);
    }
    
    protected function tearDown(): void
    {
        // Clean up test files
        $path = sys_get_temp_dir() . '/spacetime_test';
        if (is_dir($path)) {
            array_map('unlink', glob("$path/*"));
            rmdir($path);
        }
        
        parent::tearDown();
    }
    
    public function testBasicArrayOperations(): void
    {
        $array = new SpaceTimeArray(100);
        
        // Test set and get
        $array['key1'] = 'value1';
        $this->assertEquals('value1', $array['key1']);
        
        // Test isset
        $this->assertTrue(isset($array['key1']));
        $this->assertFalse(isset($array['key2']));
        
        // Test unset
        unset($array['key1']);
        $this->assertFalse(isset($array['key1']));
        
        // Test count
        $array['a'] = 1;
        $array['b'] = 2;
        $this->assertEquals(2, count($array));
    }
    
    public function testSpilloverToExternalStorage(): void
    {
        $array = new SpaceTimeArray(2); // Small threshold
        
        // Add items that will stay in memory
        $array['hot1'] = 'value1';
        $array['hot2'] = 'value2';
        
        // This should trigger spillover
        $array['cold1'] = 'value3';
        $array['cold2'] = 'value4';
        
        // All items should still be accessible
        $this->assertEquals('value1', $array['hot1']);
        $this->assertEquals('value3', $array['cold1']);
        
        // Count should include all items
        $this->assertEquals(4, count($array));
    }
    
    public function testIterator(): void
    {
        $array = new SpaceTimeArray(2);
        
        $data = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
        foreach ($data as $key => $value) {
            $array[$key] = $value;
        }
        
        // Test iteration
        $result = [];
        foreach ($array as $key => $value) {
            $result[$key] = $value;
        }
        
        $this->assertEquals($data, $result);
    }
    
    public function testLargeDataSet(): void
    {
        $array = new SpaceTimeArray(100);
        
        // Add 1000 items
        for ($i = 0; $i < 1000; $i++) {
            $array["key_$i"] = "value_$i";
        }
        
        // Verify count
        $this->assertEquals(1000, count($array));
        
        // Verify random access
        $this->assertEquals('value_500', $array['key_500']);
        $this->assertEquals('value_999', $array['key_999']);
        $this->assertEquals('value_0', $array['key_0']);
    }
    
    public function testArrayMethods(): void
    {
        $array = new SpaceTimeArray(10);
        
        $array['a'] = 1;
        $array['b'] = 2;
        $array['c'] = 3;
        
        // Test toArray
        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $array->toArray());
        
        // Test keys
        $this->assertEquals(['a', 'b', 'c'], $array->keys());
        
        // Test values
        $this->assertEquals([1, 2, 3], $array->values());
        
        // Test clear
        $array->clear();
        $this->assertEquals(0, count($array));
    }
}