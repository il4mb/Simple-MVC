<?php

namespace Il4mb\BlockNode;

class Node
{
    public readonly string $tagName;
    protected ?Node $parent = null;
    protected array $children = [];
    protected array $attribute = [];

    /**
     * Node Constructor
     * @param string $tagName - Node TagName
     * @param array $attribute - Node Attribute
     * - chidren
     * - any other attribute
     */
    function __construct(string $tagName, array|string $attribute = [])
    {

        $this->tagName = $tagName;
        if (is_array($attribute)) {
            if (isset($attribute["children"])) {
                $this->append($attribute["children"]);
                unset($attribute["children"]);
            }
            $attribute = array_filter($attribute, function ($v) {
                if ($v instanceof Node || is_string($v) && NodeParser::isHTML(trim($v))) {
                    $this->append($v);
                    return false;
                }
                return true;
            });
            $this->attribute = array_map(fn($v) => is_string($v) ? trim($v) : $v, $attribute);
        } else {
            $this->append($attribute);
        }
    }

    private function addContent(mixed $content, bool $prepend = false): void
    {
        if ($content instanceof Node) {
            $content->parent = $this;
            $prepend ? array_unshift($this->children, $content) : $this->children[] = $content;
            return;
        } else if (is_string($content)) {
            if (NodeParser::isHTML(trim($content))) {
                $nodes = NodeParser::fromHTML(trim($content));
                $this->addContent($nodes, $prepend);
            } else {
                $node = $this->createTextNode($content);
                $node->parent = $this;
                $prepend ? array_unshift($this->children, $node) : $this->children[] = $node;
            }
            return;
        } else if (is_array($content) && (isset($content['children']) || isset($content['attribute']) || isset($content['tagname']))) {
            $this->addContent(NodeParser::fromArray($content), $prepend);
            return;
        }

        if (is_array($content)) {
            $content = $prepend ? array_reverse($content) : $content;
            foreach ($content as $child) {
                $this->addContent($child, $prepend);
            }
        }
    }

    private function createTextNode(mixed $content): TextNode
    {
        return new TextNode($content);
    }

    private function joinAttr(array $attribute, string $separator = " ", $isVoid = false)
    {

        return implode(
            $separator,
            array_map(
                function ($key) use ($attribute, $separator, $isVoid) {
                    $val = is_array($attribute[$key]) ? $this->joinAttr($attribute[$key], $separator, true) : $attribute[$key];
                    return $isVoid ? $val : "$key=\"$val\"";
                },
                array_keys(
                    $attribute
                )
            )
        );
    }

    public function query(string $string)
    {
        return new NodeQuery($string, $this);
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function append(mixed $content): void
    {
        $this->addContent($content);
    }

    public function prepend(mixed $content): void
    {
        $this->addContent($content, true);
    }

    public function addClass(string $className): void
    {
        $this->set("class", array_unique([...$this->get("class", []), $className]));
    }

    public function removeClass(string $className): void
    {
        $this->set("class", array_diff($this->get("class", []), [$className]));
    }

    public function hasClass(string $className): bool
    {
        return in_array($className, $this->get("class", []));
    }


    public function toggleClass(string $className): void
    {
        if ($this->hasClass($className)) {
            $this->removeClass($className);
        } else {
            $this->addClass($className);
        }
    }

    public function getAttr(string $name)
    {
        if ($name == "class") {
            $className = $this->get($name, "");
            return is_string($className) ? explode(" ", $className) : $className;
        }
        return $this->get($name);
    }

    public function addAttr(string $name, string $value)
    {
        if ($name == "class") {
            $this->set($name, explode(" ", $value));
            return;
        }
        $this->set($name, $value);
    }

    public function removeAttr(string $name)
    {
        unset($this->attribute[$name]);
    }

    public function toArray(): array
    {
        $array = [
            "tagName" => $this->tagName
        ];

        if (!empty($this->children)) {
            $children = array_map(fn($child) => $child->toArray(), $this->children);
            $array["children"] = $children;
        }
        if (!empty($attribute)) {
            $array["attribute"] = $this->attribute;
        }
        return $array;
    }

    public function remove(): void
    {
        if ($this->parent) {
            $this->parent->children = array_diff($this->parent->children, [$this]);
        }
    }



    public function render(): string
    {
        $voidElements = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
        $attribute = $this->joinAttr($this->attribute);
        $attribute = trim($attribute);
        if (!empty($attribute)) {
            $attribute = " $attribute";
        }

        if (in_array($this->tagName, $voidElements)) {
            return "<{$this->tagName}{$attribute} />";
        }

        $children = implode("", array_map(fn($child) => $child->render(), $this->children));
        return "<{$this->tagName}{$attribute}>{$children}</{$this->tagName}>";
    }

    function __tostring()
    {
        return $this->render();
    }

    protected function get(string $name, mixed $default = null)
    {
        if (isset($this->attribute[$name])) {
            return $this->attribute[$name];
        } else {
            return $default;
        }
    }

    protected function set(string $name, mixed $value): void
    {
        $this->attribute[$name] = $value;
    }
}
