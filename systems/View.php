<?php

namespace Il4mb\Simvc\Systems;

use Il4mb\BlockNode\NodeHtml;
use Il4mb\Simvc\Systems\Cores\Database;
use Il4mb\Simvc\Systems\Cores\ViewExtension;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class View
{

    protected $loader;
    protected $twig;
    private static $instance;
    private function __construct()
    {
        $this->loader =  new FilesystemLoader([
            Path::canonicalize(__DIR__ . "/../"),
            Path::canonicalize(__DIR__ . "/../views")
        ]);
        foreach (glob(__DIR__ . "/../views/*") as $file) {
            if (is_dir($file)) {
                $this->loader->addPath($file, strtolower(basename($file)));
            }
        }
        $this->twig   = new Environment($this->loader);
        $this->twig->addExtension(new ViewExtension());
    }

    private static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new View();
        }
        return self::$instance;
    }

    static function isHtmlDocument($string)
    {
        // Trim whitespace from the string
        $string = trim($string);

        // Check if it starts with <!DOCTYPE html> or <html>
        if (preg_match('/^(<!DOCTYPE html>|<html>)/i', $string)) {
            // Check if it contains <head> and <body>
            if (stripos($string, '<head>') !== false && stripos($string, '<body>') !== false) {
                return true;
            }
        }

        return false;
    }

    static function render($file, $data = [])
    {
        $document = new NodeHtml([], []);
        $output = self::getInstance()->twig->render($file, [
            ...$data,
            "document" => $document,
            "config" => [
                "pathOffset" => dirname($_SERVER['SCRIPT_NAME'], true)
            ],
            "database" => Database::getInstance()
        ]);
        if (!self::isHtmlDocument("$output")) {
            $document->query("body")->prepend("$output");
        } else {
            return "$output";
        }
        return $document;
    }
}
