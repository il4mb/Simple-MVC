<?php

use Il4mb\BlockNode\Node;
use Il4mb\BlockNode\NodeHtml;

include_once __DIR__ . "/vendor/autoload.php";

$node = new NodeHtml(["<title>Hallo</title>"], [
    "children" => [
        "<div class=\"test 1\"></div>"
    ],
    "class" => "test"
]);

$node->query(".test.1")->prepend("<p>Hallo</p>");
echo $node;
