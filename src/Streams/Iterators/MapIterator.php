<?php

namespace SqrtSpace\SpaceTime\Streams\Iterators;

/**
 * Iterator that applies a mapping function to each element
 */
class MapIterator extends \FilterIterator
{
    private $callback;
    
    public function __construct(\Iterator $iterator, callable $callback)
    {
        parent::__construct($iterator);
        $this->callback = $callback;
    }
    
    public function accept(): bool
    {
        return true; // Accept all elements
    }
    
    public function current(): mixed
    {
        return ($this->callback)(parent::current());
    }
}