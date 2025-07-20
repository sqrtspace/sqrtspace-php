<?php

namespace SqrtSpace\SpaceTime\Streams\Iterators;

/**
 * Iterator that takes only the first n elements
 */
class TakeIterator implements \Iterator
{
    private \Iterator $iterator;
    private int $limit;
    private int $position = 0;
    
    public function __construct(\Iterator $iterator, int $limit)
    {
        $this->iterator = $iterator;
        $this->limit = $limit;
    }
    
    public function rewind(): void
    {
        $this->iterator->rewind();
        $this->position = 0;
    }
    
    public function current(): mixed
    {
        return $this->iterator->current();
    }
    
    public function key(): mixed
    {
        return $this->iterator->key();
    }
    
    public function next(): void
    {
        $this->iterator->next();
        $this->position++;
    }
    
    public function valid(): bool
    {
        return $this->position < $this->limit && $this->iterator->valid();
    }
}