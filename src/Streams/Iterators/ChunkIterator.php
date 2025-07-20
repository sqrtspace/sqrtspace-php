<?php

namespace SqrtSpace\SpaceTime\Streams\Iterators;

/**
 * Iterator that groups elements into chunks of specified size
 */
class ChunkIterator implements \Iterator
{
    private \Iterator $iterator;
    private int $chunkSize;
    private ?array $currentChunk = null;
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
        $this->loadNextChunk();
    }
    
    public function current(): mixed
    {
        return $this->currentChunk;
    }
    
    public function key(): mixed
    {
        return $this->position;
    }
    
    public function next(): void
    {
        $this->position++;
        $this->loadNextChunk();
    }
    
    public function valid(): bool
    {
        return $this->currentChunk !== null;
    }
    
    private function loadNextChunk(): void
    {
        $chunk = [];
        
        for ($i = 0; $i < $this->chunkSize && $this->iterator->valid(); $i++) {
            $chunk[] = $this->iterator->current();
            $this->iterator->next();
        }
        
        $this->currentChunk = empty($chunk) ? null : $chunk;
    }
}