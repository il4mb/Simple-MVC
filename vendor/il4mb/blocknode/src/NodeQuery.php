<?php

namespace Il4mb\BlockNode;

use ArrayIterator;
use Countable;

/**
 * NodeQuery
 * @class NodeQuery
 * @extends \ArrayIterator
 * @implements Countable
 * @constructor
 * @param string $selector
 * @param Node $node
 * @return NodeQuery
 */
class NodeQuery implements Countable
{
    private string $selector;
    private Node $node;
    public array $matches = [];
    private ArrayIterator $iterator;

    public function __construct(string $selector, Node $node)
    {
        $this->selector = $selector;
        $this->node = $node;

        $this->findMatches();
        $this->iterator = new ArrayIterator($this->matches);
    }

    protected function findMatches(): void
    {
        $selector = $this->selector;
        $parts = preg_split('/\s*(>| )\s*/', trim($selector), -1, PREG_SPLIT_DELIM_CAPTURE);

        $currentNodes = [$this->node];
        $isChildCombinator = false;

        foreach ($parts as $part) {
            if ($part === '>') {
                $isChildCombinator = true;
                continue;
            }

            $matches = [];

            foreach ($currentNodes as $node) {
                $children = $isChildCombinator
                    ? $node->getChildren()
                    : $this->getAllDescendants($node);

                foreach ($children as $child) {
                    if ($this->matchesSelector($child, $part)) {
                        $matches[] = $child;
                    }
                }
            }

            $isChildCombinator = false;
            $currentNodes = $matches;
            $this->matches = [
                ...$this->matches,
                ...$matches
            ];
        }
    }

    /**
     * Get all descendants of a node.
     */
    protected function getAllDescendants(Node $node): array
    {
        $stack = [$node];
        $descendants = [];

        while ($stack) {
            $current = array_pop($stack);
            foreach ($current->getChildren() as $child) {
                if ($child instanceof Node) {
                    $descendants[] = $child;
                    $stack[] = $child;
                }
            }
        }

        return $descendants;
    }

    /**
     * Checks if a node matches a CSS selector (ID, class, tag name, or combinations).
     */
    protected function matchesSelector(Node $node, string $selector): bool
    {

        // Match tag, ID, class, and attribute selectors
        $pattern = '/^(?<tag>\w+)?(?<id>#[a-zA-Z0-9_-]+)?(?<class>(\.[a-zA-Z0-9_-]+)*)(?<attributes>(\[[^\]]+\])*)$/';
        if (preg_match($pattern, $selector, $matches)) {
            $tag = $matches['tag'] ?? null;
            $id = isset($matches['id']) ? ltrim($matches['id'], '#') : null;
            $class = isset($matches['class'])
                ? array_filter(
                    explode('.', ltrim($matches['class'], '.')),
                    fn($v) => !empty(trim($v))
                )
                : [];
            $attributes = isset($matches['attributes']) ? $matches['attributes'] : '';


            // Check tag name
            if ($tag && $node->tagName !== $tag) {
                return false;
            }

            // Check ID
            if ($id && $node->getAttr('id') !== $id) {
                return false;
            }

            // Check class
            $nodeclass = $node->getAttr("class") ?? [];
            if (!empty($class) && array_diff($class, $nodeclass)) {
                return false;
            }

            // Check attributes
            if (!empty($attributes)) {
                $attributePattern = '/\[(?<name>[a-zA-Z0-9_-]+)(=(?<value>[^\]]+))?\]/';
                preg_match_all($attributePattern, $attributes, $attributeMatches, PREG_SET_ORDER);

                foreach ($attributeMatches as $attr) {
                    $name = $attr['name'];
                    $value = $attr['value'] ?? null;

                    // Check attribute value if specified
                    if ($value !== null && $node->getAttr($name) !== trim($value, "'\"")) {
                        return false;
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * get node by index
     * @param int $i
     * @return mixed
     */
    public function get(int $i = 0): Node|null
    {
        if (isset($this->matches[$i]))
            return $this->matches[$i];
        return null;
    }

    /**
     * node query
     * @param string $string
     * @param int $i
     * @return mixed
     */
    public function query(string $string, int $i = 0)
    {
        return $this->get($i)?->query($string);
    }

    /**
     * Summary of getChildren
     * @return array
     */
    public function getChildren(int $i = 0): array
    {
        return $this->get($i)?->getChildren() ?? [];
    }

    /**
     * append node
     * @param mixed $content
     * @param int $i
     * @return void
     */
    public function append(mixed $content, int $i = 0): void
    {
        $this->get($i)?->append($content);
    }

    /**
     * prepend node
     * @param mixed $content
     * @param int $i
     * @return void
     */
    public function prepend(mixed $content, int $i = 0): void
    {
        $this->get($i)?->prepend($content);
    }

    /**
     * add className
     * @param string $className
     * @param int $i
     * @return void
     */
    public function addClass(string $className, int $i = 0): void
    {
        $this->get($i)?->addClass($className);
    }

    /**
     * remove className
     * @param string $className
     * @param int $i
     * @return void
     */
    public function removeClass(string $className, int $i = 0): void
    {
        $this->get($i)?->removeClass($className);
    }

    /**
     * is className exist
     * @param string $className
     * @param int $i
     * @return bool
     */
    public function hasClass(string $className, int $i = 0): bool
    {
        return $this->get($i)?->hasClass($className);
    }

    /**
     * toggle className
     * @param string $className
     * @param int $i
     * @return void
     */
    public function toggleClass(string $className, int $i = 0): void
    {
        $this->get($i)?->toggleClass($className);
    }

    /**
     * get attribute
     * @param string $name
     * @param int $i
     * @return mixed
     */
    public function getAttr(string $name, int $i = 0)
    {
        return $this->get($i)?->getAttr($name);
    }

    /**
     * add attribute
     * @param string $name
     * @param string $value
     * @param int $i
     * @return void
     */
    public function addAttr(string $name, string $value, int $i = 0)
    {
        $this->get($i)?->addAttr($name, $value);
    }

    /**
     * remove attribute
     * @param string $name
     * @param int $i
     * @return void
     */
    public function removeAttr(string $name, int $i = 0)
    {
        $this->get($i)?->removeAttr($name);
    }

    /**
     * remove node
     * @param int $i
     * @return void
     */
    public function remove(int $i = 0): void
    {
        $this->get($i)?->remove();
    }

    /**
     * transform to array
     * @param int $i
     * @return array
     */
    public function toArray(int $i = 0): array
    {
        return $this->get($i)?->toArray();
    }

    /**
     * render node
     * @param int $i
     * @return string
     */
    public function render(int $i = 0): string
    {
        return $this->get($i)?->render();
    }





    function current(): Node
    {
        return $this->iterator->current();
    }
    function key(): int
    {
        return $this->iterator->key();
    }
    function next(): void
    {
        $this->iterator->next();
    }
    function rewind(): void
    {
        $this->iterator->rewind();
    }
    function valid(): bool
    {
        return $this->iterator->valid();
    }
    function count(): int
    {
        return count($this->matches);
    }
}
