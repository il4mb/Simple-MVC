<?php

use Il4mb\Simvc\Systems\App;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");

require_once __DIR__ . "/vendor/autoload.php";

$env = require_once __DIR__ . "/env.php";
foreach ($env as $key => $val) {
    $_ENV["APP_" . strtoupper($key)] = $val;
}

$app = new App();
$app->loadController(__DIR__ . "/controllers");
$output =  $app->render();
echo $output;
