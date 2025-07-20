<?php

namespace SqrtSpace\SpaceTime\Streams\Iterators;

/**
 * Iterator that skips the first n elements
 */
class SkipIterator extends \FilterIterator
{
    private int $skip;
    private int $position = 0;
    private bool $initialized = false;
    
    public function __construct(\Iterator $iterator, int $skip)
    {
        parent::__construct($iterator);
        $this->skip = $skip;
    }
    
    public function rewind(): void
    {
        parent::rewind();
        $this->position = 0;
        $this->initialized = false;
        
        // Skip initial elements
        while ($this->position < $this->skip && parent::valid()) {
            parent::next();
            $this->position++;
        }
        $this->initialized = true;
    }
    
    public function accept(): bool
    {
        // After initialization, accept all elements
        return $this->initialized;
    }
}