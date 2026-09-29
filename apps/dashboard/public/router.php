<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/dashboard/js/trash.js') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . '/js/trash.js');
    return true;
}
$file = __DIR__ . (is_string($path) ? $path : '');
if (is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
