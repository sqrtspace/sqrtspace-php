<?php

namespace SqrtSpace\SpaceTime\Streams\Iterators;

/**
 * Iterator that flattens results of a mapping function
 */
class FlatMapIterator implements \Iterator
{
    private \Iterator $iterator;
    private $callback;
    private $currentInner = null;
    private $currentInnerIterator = null;
    
    public function __construct(\Iterator $iterator, callable $callback)
    {
        $this->iterator = $iterator;
        $this->callback = $callback;
    }
    
    public function rewind(): void
    {
        $this->iterator->rewind();
        $this->advance();
    }
    
    public function current(): mixed
    {
        return $this->currentInnerIterator?->current();
    }
    
    public function key(): mixed
    {
        return null; // Keys are not preserved in flatMap
    }
    
    public function next(): void
    {
        if ($this->currentInnerIterator) {
            $this->currentInnerIterator->next();
            if (!$this->currentInnerIterator->valid()) {
                $this->iterator->next();
                $this->advance();
            }
        }
    }
    
    public function valid(): bool
    {
        return $this->currentInnerIterator && $this->currentInnerIterator->valid();
    }
    
    private function advance(): void
    {
        while ($this->iterator->valid()) {
            $result = ($this->callback)($this->iterator->current());
            
            if (is_array($result)) {
                $this->currentInnerIterator = new \ArrayIterator($result);
            } elseif ($result instanceof \Iterator) {
                $this->currentInnerIterator = $result;
            } elseif ($result instanceof \IteratorAggregate) {
                $this->currentInnerIterator = $result->getIterator();
            } else {
                // Single value, wrap in array
                $this->currentInnerIterator = new \ArrayIterator([$result]);
            }
            
            $this->currentInnerIterator->rewind();
            if ($this->currentInnerIterator->valid()) {
                return;
            }
            
            $this->iterator->next();
        }
        
        $this->currentInnerIterator = null;
    }
}