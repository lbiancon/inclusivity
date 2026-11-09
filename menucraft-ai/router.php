<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$bloccato = preg_match('#^/(?:config|src|tests|bin)(?:/|$)#', $path) === 1
    || $path === '/bootstrap.php'
    || $path === '/router.php';

if ($bloccato) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Non trovato\n";
    return true;
}

if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Non trovato\n";
return true;
