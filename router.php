<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (strpos($path, '/data/') === 0 || $path === '/data') {
    http_response_code(404);
    exit('Not Found');
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

if ($path === '/') {
    require __DIR__ . '/index.html';
    return true;
}

http_response_code(404);
require __DIR__ . '/index.html';
return true;
