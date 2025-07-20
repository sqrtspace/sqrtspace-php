<?php

namespace SqrtSpace\SpaceTime\Streams\Iterators;

/**
 * Iterator that filters elements based on a predicate
 */
class FilterIterator extends \FilterIterator
{
    private $predicate;
    
    public function __construct(\Iterator $iterator, callable $predicate)
    {
        parent::__construct($iterator);
        $this->predicate = $predicate;
    }
    
    public function accept(): bool
    {
        return ($this->predicate)($this->current());
    }
}