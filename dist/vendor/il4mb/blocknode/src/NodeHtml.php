<?php

namespace Il4mb\BlockNode;

class NodeHtml extends Node
{

    public readonly Node $head;
    public readonly Node $body;


    function __construct($head = [], $body = [])
    {
        $this->head = new Node("head", $head);
        $this->body = new Node("body", $body);

        parent::__construct(
            "html",
            [
                "children" => [
                    $this->head,
                    $this->body
                ]
            ]
        );
    }

    function setTitle($title)
    {
        if (empty($this->head->query("title")->matches)) {
            $this->head->append(new Node("title", []));
        }
        $this->head->query("title")->append($title);
    }

    function toArray(): array
    {
        return [
            "tagName" => $this->tagName,
            "children" => array_map(fn($child) => $child->toArray(), $this->children)
        ];
    }
}
