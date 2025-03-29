<?php

namespace Il4mb\BlockNode;

class TextNode extends Node
{
    public function __construct($content)
    {
        parent::__construct("textnode", $content);
    }

    public function append(mixed $content): void
    {
        $this->children[] = $content;
    }

    public function prepend(mixed $content): void
    {
        array_unshift($this->children, $content);
    }

    public function render(): string
    {
        return implode("", $this->children);
    }

    public function toArray(): array
    {
        $array = [
            "tagName" => $this->tagName
        ];
        $conditional = [
            "children" => $this->children ?? [],
            "attribute" => $this->attribute ?? []
        ];
        foreach ($conditional as $key => $value) {
            if (!empty($value)) {
                $array[$key] = $value;
            }
        }
        return $array;
    }


    function __debugInfo()
    {
        return [
            "tagName" => $this->tagName,
            "parent" => $this->parent,
            "children" => $this->children,
            "attribute" => $this->attribute
        ];
    }
}
