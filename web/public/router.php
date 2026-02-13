<?php
// Router for PHP built-in server: php -S localhost:8080 -t public public/router.php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}
require __DIR__ . '/index.php';
