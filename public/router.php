<?php
// Router for PHP's built-in web server, started with: php -S 127.0.0.1:8080 -t . public/router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = dirname(__DIR__) . $path;
if ($path !== '/' && is_file($file)) { return false; }
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
require __DIR__ . '/index.php';
