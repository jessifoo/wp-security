<?php

namespace OMS;

use ArrayAccess;
use Iterator;

/**
 * Command class with proper return type declarations
 */
class Command implements ArrayAccess, Iterator
{
    private $position = 0;
    private $array = array();

    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        return isset($this->array[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset): mixed
    {
        return $this->array[$offset] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->array[] = $value;
        } else {
            $this->array[$offset] = $value;
        }
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        unset($this->array[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function current(): mixed
    {
        return $this->array[$this->position];
    }

    #[\ReturnTypeWillChange]
    public function key(): mixed
    {
        return $this->position;
    }

    #[\ReturnTypeWillChange]
    public function next(): void
    {
        ++$this->position;
    }

    #[\ReturnTypeWillChange]
    public function rewind(): void
    {
        $this->position = 0;
    }

    #[\ReturnTypeWillChange]
    public function valid(): bool
    {
        return isset($this->array[$this->position]);
    }
}
