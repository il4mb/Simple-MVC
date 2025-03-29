<?php

namespace Il4mb\BlockNode;

class NodeParser
{
    static function toHtml(array $nodes): string
    {
        return array_reduce($nodes, fn($html, $node) => $html . $node->render(), '');
    }

    static function fromArray(array $component): NodeList
    {
        $nodes = new NodeList();
        if (array_is_list($component)) {
            foreach ($component as $node) {
                if ($node instanceof Node) {
                    $nodes[] = $node;
                } elseif (is_array($node)) {
                    $nodes->append(self::fromArray($node));
                }
            }
        } elseif (isset($component['tagName']) || isset($component['children'])) {
            $attribute = $component['attribute'] ?? [];
            if (isset($component['children'])) {
                $attribute['children'] = $component['children'];
            }
            $nodes[] = new Node($component['tagName'] ?? 'div', $attribute);
        } elseif (is_string($component)) {
            $nodes[] = self::isHTML($component)
                ? self::fromHTML($component)
                : new TextNode($component);
        }
        return $nodes;
    }

    static function fromHTML(string $html, ?callable $callback = null): NodeList
    {
        if (trim($html) === '') return [];

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML("<div>$html</div>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $blocks = new NodeList();
        foreach ($dom->firstChild->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement) {
                $block = self::parseElement($childNode, $callback);
                $blocks[] = $callback ? $callback($block) : $block;
            }
        }
        return $blocks;
    }

    static function parseElement(\DOMElement $element, ?callable $callback = null): Node
    {
        $block = new Node($element->tagName, []);
        foreach ($element->attributes as $attr) {
            $block->addAttr($attr->nodeName, $attr->nodeValue ?? '');
        }

        foreach ($element->childNodes as $child) {
            $child = $child instanceof \DOMElement
                ? self::parseElement($child, $callback)
                : ($child instanceof \DOMText && trim($child->textContent) !== '' ? new TextNode($child->textContent) : null);

            if ($child) $block->append($callback ? $callback($child) : $child);
        }

        return $block;
    }

    public static function isHTML(string $string): bool
    {
        return preg_match('/^<(\w+)[^>]*>.*/s', trim($string)) === 1;
    }
}
