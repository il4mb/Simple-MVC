<?php

namespace Il4mb\BlockNode;

use ArrayAccess;
use Countable;
use Iterator;

class NodeList extends Node implements ArrayAccess, Countable, Iterator
{

    private int $position = 0;

    function __construct(array $children = [])
    {
        parent::__construct("div", $children);
        $this->children = $children;
    }

    function add(Node $node): void
    {
        $this->children[] = $node;
    }

    function render(): string
    {
        return implode(
            "\n",
            array_map(
                fn($child) => $child->render(),
                $this->children
            )
        );
    }

    // ArrayAccess implementation
    function offsetExists($offset): bool
    {
        return isset($this->children[$offset]);
    }

    function offsetGet($offset): ?Node
    {
        return $this->children[$offset] ?? null;
    }

    function offsetSet($offset, $value): void
    {
        if (!$value instanceof Node) {
            throw new \InvalidArgumentException("Value must be an instance of Node.");
        }
        if ($offset === null) {
            $this->children[] = $value;
        } else {
            $this->children[$offset] = $value;
        }
    }

    function offsetUnset($offset): void
    {
        unset($this->children[$offset]);
    }

    // Countable implementation
    function count(): int
    {
        return count($this->children);
    }

    // Iterator implementation
    function current(): ?Node
    {
        return $this->children[$this->position] ?? null;
    }

    function next(): void
    {
        ++$this->position;
    }

    function key(): int
    {
        return $this->position;
    }

    function valid(): bool
    {
        return isset($this->children[$this->position]);
    }

    function rewind(): void
    {
        $this->position = 0;
    }
}
