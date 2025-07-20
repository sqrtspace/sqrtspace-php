<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Tests\Algorithms;

use PHPUnit\Framework\TestCase;
use SqrtSpace\SpaceTime\Algorithms\ExternalGroupBy;
use SqrtSpace\SpaceTime\SpaceTimeConfig;

class ExternalGroupByTest extends TestCase
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
    
    public function testBasicGroupBy(): void
    {
        $data = [
            ['category' => 'A', 'value' => 1],
            ['category' => 'B', 'value' => 2],
            ['category' => 'A', 'value' => 3],
            ['category' => 'B', 'value' => 4],
            ['category' => 'C', 'value' => 5],
        ];
        
        $grouped = ExternalGroupBy::groupBy($data, fn($item) => $item['category']);
        
        $this->assertCount(3, $grouped);
        $this->assertCount(2, $grouped['A']);
        $this->assertCount(2, $grouped['B']);
        $this->assertCount(1, $grouped['C']);
        
        $this->assertEquals(1, $grouped['A'][0]['value']);
        $this->assertEquals(3, $grouped['A'][1]['value']);
    }
    
    public function testGroupByCount(): void
    {
        $data = [
            ['type' => 'foo'],
            ['type' => 'bar'],
            ['type' => 'foo'],
            ['type' => 'baz'],
            ['type' => 'foo'],
        ];
        
        $counts = ExternalGroupBy::groupByCount($data, fn($item) => $item['type']);
        
        $this->assertEquals(3, $counts['foo']);
        $this->assertEquals(1, $counts['bar']);
        $this->assertEquals(1, $counts['baz']);
    }
    
    public function testGroupBySum(): void
    {
        $data = [
            ['group' => 'A', 'amount' => 10],
            ['group' => 'B', 'amount' => 20],
            ['group' => 'A', 'amount' => 15],
            ['group' => 'B', 'amount' => 25],
        ];
        
        $sums = ExternalGroupBy::groupBySum(
            $data,
            fn($item) => $item['group'],
            fn($item) => $item['amount']
        );
        
        $this->assertEquals(25, $sums['A']);
        $this->assertEquals(45, $sums['B']);
    }
    
    public function testGroupByAggregate(): void
    {
        $data = [
            ['user' => 'john', 'score' => 80],
            ['user' => 'jane', 'score' => 90],
            ['user' => 'john', 'score' => 85],
            ['user' => 'jane', 'score' => 95],
        ];
        
        $maxScores = ExternalGroupBy::groupByAggregate(
            $data,
            fn($item) => $item['user'],
            fn($max, $item) => max($max ?? 0, $item['score']),
            0
        );
        
        $this->assertEquals(85, $maxScores['john']);
        $this->assertEquals(95, $maxScores['jane']);
    }
    
    public function testGroupByStreaming(): void
    {
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data[] = [
                'group' => chr(65 + ($i % 5)), // A-E
                'value' => $i,
            ];
        }
        
        $groups = [];
        foreach (ExternalGroupBy::groupByStreaming($data, fn($item) => $item['group']) as $key => $items) {
            $groups[$key] = count($items);
        }
        
        $this->assertCount(5, $groups);
        $this->assertEquals(20, $groups['A']);
        $this->assertEquals(20, $groups['B']);
    }
    
    public function testGroupByWithLimit(): void
    {
        $data = [];
        for ($i = 0; $i < 50; $i++) {
            $data[] = ['key' => "group_$i", 'value' => $i];
        }
        
        $grouped = ExternalGroupBy::groupByWithLimit(
            $data,
            fn($item) => $item['key'],
            5 // Small limit to force external storage
        );
        
        $this->assertCount(50, $grouped);
        
        foreach ($grouped as $key => $items) {
            $this->assertCount(1, $items);
            $this->assertEquals($key, $items[0]['key']);
        }
    }
}