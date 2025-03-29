# BlockNode Library

BlockNode is a lightweight PHP library for creating, manipulating, and rendering HTML-like node structures programmatically. It supports dynamic DOM-style operations such as appending, prepending, and querying nodes using simple methods.

---

## Features

- Create nested node structures easily.
- Query and manipulate nodes using CSS-like selectors.
- Supports appending and prepending content dynamically.
- Automatically handles both string-based HTML and node objects.

---

## Installation

Install the library via [Composer](https://getcomposer.org/):

```bash
composer require il4mb/blocknode
```

---

## Quick Start

Here’s a simple example to get you started:

```php
<?php

use Il4mb\BlockNode\Node;

include_once __DIR__ . "/vendor/autoload.php";

$node = new Node("div", [
    "children" => [
        "<div class=\"test 1\"></div>"
    ],
    "class" => "test"
]);

$node->query(".test")->prepend("<p>Hallo</p>", 1);
echo $node;

// Output: <div class="test"><p>Hallo</p><div class="test 1"></div></div>
```

---

## API Usage

### **1. Creating Nodes**
You can create nodes with attributes and nested children:
```php
$node = new Node("div", [
    "id" => "container",
    "children" => [
        "<p>Hello, World!</p>"
    ],
    "class" => "main"
]);

echo $node;
// Output: <div id="container" class="main"><p>Hello, World!</p></div>
```

---

### **2. Querying Nodes**
Use the `query` method to find nodes using CSS-like selectors:
```php
$node = new Node("div", [
    "children" => [
        "<div class=\"child\"></div>",
        "<p class=\"child\">Text</p>"
    ]
]);

$childNodes = $node->query(".child");
echo $childNodes;
// Output: <div class="child"></div><p class="child">Text</p>
```

---

### **3. Appending and Prepending Content**
You can dynamically append or prepend content to nodes:

#### Append
```php
$node->append("<span>New Content</span>");
echo $node;
// Appends content as a child to the node.
```

#### Prepend
```php
$node->prepend("<span>First Content</span>");
echo $node;
// Prepends content as the first child of the node.
```

---

## Advanced Features

### **Dynamic HTML Handling**
BlockNode automatically parses valid HTML strings into nodes:
```php
$node->append("<div class=\"dynamic\">Dynamic Content</div>");
```

### **Support for Nested Structures**
You can pass deeply nested arrays or HTML strings as children:
```php
$node = new Node("ul", [
    "children" => [
        ["tagName" => "li", "attribute" => ["class" => "item"], "children" => ["Item 1"]],
        "<li>Item 2</li>",
    ]
]);

echo $node;
// Output: <ul><li class="item">Item 1</li><li>Item 2</li></ul>
```

---

## Contributing

Contributions are welcome! If you’d like to contribute:
1. Fork this repository.
2. Create a new branch for your feature/fix.
3. Submit a pull request with your changes.

---

## License

This library is licensed under the MIT License. See the `LICENSE` file for more details.

---
