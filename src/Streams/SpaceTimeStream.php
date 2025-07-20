<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Streams;

use SqrtSpace\SpaceTime\SpaceTimeConfig;

/**
 * Memory-efficient stream processing
 */
class SpaceTimeStream
{
    private iterable $source;
    private array $operations = [];

    private function __construct(iterable $source)
    {
        $this->source = $source;
    }

    /**
     * Create stream from array or iterable
     */
    public static function from(iterable $source): self
    {
        return new self($source);
    }

    /**
     * Create stream from file
     */
    public static function fromFile(string $filename, string $mode = 'r'): self
    {
        $generator = function() use ($filename, $mode) {
            $handle = fopen($filename, $mode);
            if (!$handle) {
                throw new \RuntimeException("Cannot open file: $filename");
            }
            
            try {
                while (($line = fgets($handle)) !== false) {
                    yield rtrim($line, "\r\n");
                }
            } finally {
                fclose($handle);
            }
        };
        
        return new self($generator());
    }

    /**
     * Create stream from CSV file
     */
    public static function fromCsv(string $filename, array $options = []): self
    {
        $options = array_merge([
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'headers' => true,
        ], $options);
        
        $generator = function() use ($filename, $options) {
            $handle = fopen($filename, 'r');
            if (!$handle) {
                throw new \RuntimeException("Cannot open CSV file: $filename");
            }
            
            try {
                $headers = null;
                if ($options['headers']) {
                    $headers = fgetcsv($handle, 0, $options['delimiter'], $options['enclosure'], $options['escape']);
                }
                
                while (($row = fgetcsv($handle, 0, $options['delimiter'], $options['enclosure'], $options['escape'])) !== false) {
                    if ($headers) {
                        yield array_combine($headers, $row);
                    } else {
                        yield $row;
                    }
                }
            } finally {
                fclose($handle);
            }
        };
        
        return new self($generator());
    }

    /**
     * Map operation
     */
    public function map(callable $callback): self
    {
        $this->operations[] = ['type' => 'map', 'callback' => $callback];
        return $this;
    }

    /**
     * Filter operation
     */
    public function filter(callable $callback): self
    {
        $this->operations[] = ['type' => 'filter', 'callback' => $callback];
        return $this;
    }

    /**
     * Flat map operation
     */
    public function flatMap(callable $callback): self
    {
        $this->operations[] = ['type' => 'flatMap', 'callback' => $callback];
        return $this;
    }

    /**
     * Take first n elements
     */
    public function take(int $n): self
    {
        $this->operations[] = ['type' => 'take', 'count' => $n];
        return $this;
    }

    /**
     * Skip first n elements
     */
    public function skip(int $n): self
    {
        $this->operations[] = ['type' => 'skip', 'count' => $n];
        return $this;
    }

    /**
     * Chunk stream into √n sized chunks
     */
    public function chunkBySqrtN(): self
    {
        $this->operations[] = ['type' => 'chunkBySqrtN'];
        return $this;
    }

    /**
     * Chunk stream into fixed size chunks
     */
    public function chunk(int $size): self
    {
        $this->operations[] = ['type' => 'chunk', 'size' => $size];
        return $this;
    }

    /**
     * Apply operations and iterate
     */
    public function each(callable $callback): void
    {
        foreach ($this->toIterator() as $item) {
            $callback($item);
        }
    }

    /**
     * Collect to array (caution with large streams!)
     */
    public function toArray(): array
    {
        return iterator_to_array($this->toIterator());
    }

    /**
     * Reduce stream to single value
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $accumulator = $initial;
        
        foreach ($this->toIterator() as $item) {
            $accumulator = $callback($accumulator, $item);
        }
        
        return $accumulator;
    }

    /**
     * Count elements
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this->toIterator() as $item) {
            $count++;
        }
        return $count;
    }

    /**
     * Write to file
     */
    public function toFile(string $filename, string $mode = 'w'): void
    {
        $handle = fopen($filename, $mode);
        if (!$handle) {
            throw new \RuntimeException("Cannot open file for writing: $filename");
        }
        
        try {
            foreach ($this->toIterator() as $item) {
                fwrite($handle, (string)$item . "\n");
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Write to CSV file
     */
    public function toCsv(string $filename, array $options = []): void
    {
        $options = array_merge([
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'headers' => true,
        ], $options);
        
        $handle = fopen($filename, 'w');
        if (!$handle) {
            throw new \RuntimeException("Cannot open CSV file for writing: $filename");
        }
        
        try {
            $firstRow = true;
            
            foreach ($this->toIterator() as $row) {
                if ($firstRow && $options['headers'] && is_array($row)) {
                    fputcsv($handle, array_keys($row), $options['delimiter'], $options['enclosure'], $options['escape']);
                    $firstRow = false;
                }
                
                if (is_array($row)) {
                    fputcsv($handle, $row, $options['delimiter'], $options['enclosure'], $options['escape']);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get iterator with all operations applied
     */
    private function toIterator(): \Iterator
    {
        $iterator = $this->source instanceof \Iterator ? $this->source : new \ArrayIterator($this->source);
        
        foreach ($this->operations as $operation) {
            $iterator = match($operation['type']) {
                'map' => new MapIterator($iterator, $operation['callback']),
                'filter' => new FilterIterator($iterator, $operation['callback']),
                'flatMap' => new FlatMapIterator($iterator, $operation['callback']),
                'take' => new TakeIterator($iterator, $operation['count']),
                'skip' => new SkipIterator($iterator, $operation['count']),
                'chunk' => new ChunkIterator($iterator, $operation['size']),
                'chunkBySqrtN' => new ChunkIterator($iterator, $this->estimateSqrtN()),
                default => $iterator,
            };
        }
        
        return $iterator;
    }

    /**
     * Estimate √n for chunking
     */
    private function estimateSqrtN(): int
    {
        // If source is countable, use exact count
        if (is_array($this->source) || $this->source instanceof \Countable) {
            return SpaceTimeConfig::calculateSqrtN(count($this->source));
        }
        
        // Otherwise use a reasonable default
        return 1000;
    }
}

/**
 * Map iterator
 */
class MapIterator extends \IteratorIterator
{
    private $callback;

    public function __construct(\Iterator $iterator, callable $callback)
    {
        parent::__construct($iterator);
        $this->callback = $callback;
    }

    public function current(): mixed
    {
        return ($this->callback)(parent::current());
    }
}

/**
 * Filter iterator
 */
class FilterIterator extends \FilterIterator
{
    private $callback;

    public function __construct(\Iterator $iterator, callable $callback)
    {
        parent::__construct($iterator);
        $this->callback = $callback;
    }

    public function accept(): bool
    {
        return ($this->callback)($this->current());
    }
}

/**
 * Flat map iterator
 */
class FlatMapIterator implements \Iterator
{
    private \Iterator $iterator;
    private $callback;
    private ?\Iterator $currentIterator = null;
    private int $index = 0;

    public function __construct(\Iterator $iterator, callable $callback)
    {
        $this->iterator = $iterator;
        $this->callback = $callback;
    }

    public function rewind(): void
    {
        $this->iterator->rewind();
        $this->index = 0;
        $this->loadCurrentIterator();
    }

    public function current(): mixed
    {
        return $this->currentIterator?->current();
    }

    public function key(): mixed
    {
        return $this->index;
    }

    public function next(): void
    {
        $this->index++;
        $this->currentIterator?->next();
        
        if (!$this->currentIterator || !$this->currentIterator->valid()) {
            $this->iterator->next();
            $this->loadCurrentIterator();
        }
    }

    public function valid(): bool
    {
        return $this->currentIterator && $this->currentIterator->valid();
    }

    private function loadCurrentIterator(): void
    {
        $this->currentIterator = null;
        
        while ($this->iterator->valid()) {
            $result = ($this->callback)($this->iterator->current());
            
            if (is_array($result)) {
                $this->currentIterator = new \ArrayIterator($result);
            } elseif ($result instanceof \Iterator) {
                $this->currentIterator = $result;
            } elseif ($result instanceof \IteratorAggregate) {
                $this->currentIterator = $result->getIterator();
            } else {
                $this->currentIterator = new \ArrayIterator([$result]);
            }
            
            $this->currentIterator->rewind();
            
            if ($this->currentIterator->valid()) {
                return;
            }
            
            // Current result is empty, move to next
            $this->iterator->next();
        }
    }
}

/**
 * Take iterator
 */
class TakeIterator extends \IteratorIterator
{
    private int $count;
    private int $taken = 0;

    public function __construct(\Iterator $iterator, int $count)
    {
        parent::__construct($iterator);
        $this->count = $count;
    }

    public function rewind(): void
    {
        parent::rewind();
        $this->taken = 0;
    }

    public function next(): void
    {
        parent::next();
        $this->taken++;
    }

    public function valid(): bool
    {
        return $this->taken < $this->count && parent::valid();
    }
}

/**
 * Skip iterator
 */
class SkipIterator extends \IteratorIterator
{
    private int $count;
    private bool $skipped = false;

    public function __construct(\Iterator $iterator, int $count)
    {
        parent::__construct($iterator);
        $this->count = $count;
    }

    public function rewind(): void
    {
        parent::rewind();
        $this->skip();
    }

    private function skip(): void
    {
        if (!$this->skipped) {
            for ($i = 0; $i < $this->count && parent::valid(); $i++) {
                parent::next();
            }
            $this->skipped = true;
        }
    }
}

/**
 * Chunk iterator
 */
class ChunkIterator implements \Iterator
{
    private \Iterator $iterator;
    private int $chunkSize;
    private array $currentChunk = [];
    private int $position = 0;

    public function __construct(\Iterator $iterator, int $chunkSize)
    {
        $this->iterator = $iterator;
        $this->chunkSize = max(1, $chunkSize);
    }

    public function rewind(): void
    {
        $this->iterator->rewind();
        $this->position = 0;
        $this->loadChunk();
    }

    public function current(): array
    {
        return $this->currentChunk;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
        $this->loadChunk();
    }

    public function valid(): bool
    {
        return !empty($this->currentChunk);
    }

    private function loadChunk(): void
    {
        $this->currentChunk = [];
        
        for ($i = 0; $i < $this->chunkSize && $this->iterator->valid(); $i++) {
            $this->currentChunk[] = $this->iterator->current();
            $this->iterator->next();
        }
    }
}