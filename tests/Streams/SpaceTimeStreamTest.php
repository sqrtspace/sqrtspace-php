<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Tests\Streams;

use PHPUnit\Framework\TestCase;
use SqrtSpace\SpaceTime\Streams\SpaceTimeStream;

class SpaceTimeStreamTest extends TestCase
{
    private string $testFile;
    private string $testCsv;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testFile = sys_get_temp_dir() . '/test_stream.txt';
        file_put_contents($this->testFile, "line1\nline2\nline3\nline4\nline5");
        
        $this->testCsv = sys_get_temp_dir() . '/test_stream.csv';
        file_put_contents($this->testCsv, "name,age\nJohn,25\nJane,30\nBob,20");
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        if (file_exists($this->testCsv)) {
            unlink($this->testCsv);
        }
        
        parent::tearDown();
    }
    
    public function testFromArray(): void
    {
        $data = [1, 2, 3, 4, 5];
        $result = SpaceTimeStream::from($data)->toArray();
        
        $this->assertEquals($data, $result);
    }
    
    public function testMap(): void
    {
        $data = [1, 2, 3, 4, 5];
        $result = SpaceTimeStream::from($data)
            ->map(fn($x) => $x * 2)
            ->toArray();
        
        $this->assertEquals([2, 4, 6, 8, 10], $result);
    }
    
    public function testFilter(): void
    {
        $data = [1, 2, 3, 4, 5];
        $result = SpaceTimeStream::from($data)
            ->filter(fn($x) => $x % 2 === 0)
            ->toArray();
        
        $this->assertEquals([1 => 2, 3 => 4], $result);
    }
    
    public function testChaining(): void
    {
        $data = range(1, 10);
        $result = SpaceTimeStream::from($data)
            ->filter(fn($x) => $x % 2 === 0)
            ->map(fn($x) => $x * 2)
            ->take(3)
            ->toArray();
        
        $expected = [1 => 4, 3 => 8, 5 => 12];
        $this->assertEquals($expected, $result);
    }
    
    public function testFromFile(): void
    {
        $lines = SpaceTimeStream::fromFile($this->testFile)->toArray();
        
        $this->assertEquals(['line1', 'line2', 'line3', 'line4', 'line5'], $lines);
    }
    
    public function testFromCsv(): void
    {
        $rows = SpaceTimeStream::fromCsv($this->testCsv)->toArray();
        
        $expected = [
            ['name' => 'John', 'age' => '25'],
            ['name' => 'Jane', 'age' => '30'],
            ['name' => 'Bob', 'age' => '20'],
        ];
        
        $this->assertEquals($expected, $rows);
    }
    
    public function testReduce(): void
    {
        $sum = SpaceTimeStream::from([1, 2, 3, 4, 5])
            ->reduce(fn($acc, $x) => $acc + $x, 0);
        
        $this->assertEquals(15, $sum);
    }
    
    public function testCount(): void
    {
        $count = SpaceTimeStream::from(range(1, 100))
            ->filter(fn($x) => $x % 2 === 0)
            ->count();
        
        $this->assertEquals(50, $count);
    }
    
    public function testChunk(): void
    {
        $chunks = SpaceTimeStream::from(range(1, 10))
            ->chunk(3)
            ->toArray();
        
        $this->assertCount(4, $chunks);
        $this->assertEquals([1, 2, 3], $chunks[0]);
        $this->assertEquals([4, 5, 6], $chunks[1]);
        $this->assertEquals([7, 8, 9], $chunks[2]);
        $this->assertEquals([10], $chunks[3]);
    }
    
    public function testFlatMap(): void
    {
        $data = [[1, 2], [3, 4], [5]];
        $result = SpaceTimeStream::from($data)
            ->flatMap(fn($arr) => $arr)
            ->toArray();
        
        $this->assertEquals([1, 2, 3, 4, 5], array_values($result));
    }
    
    public function testSkip(): void
    {
        $result = SpaceTimeStream::from(range(1, 10))
            ->skip(5)
            ->toArray();
        
        $this->assertEquals([6, 7, 8, 9, 10], array_values($result));
    }
    
    public function testWriteToFile(): void
    {
        $outputFile = sys_get_temp_dir() . '/output_stream.txt';
        
        SpaceTimeStream::from(['a', 'b', 'c'])
            ->map(fn($x) => strtoupper($x))
            ->toFile($outputFile);
        
        $content = file_get_contents($outputFile);
        $this->assertEquals("A\nB\nC\n", $content);
        
        unlink($outputFile);
    }
}