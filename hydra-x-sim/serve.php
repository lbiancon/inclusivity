<?php
declare(strict_types=1);

/**
 * Router solo per il server interno di PHP:
 *   php -S localhost:8765 serve.php
 * In htdocs non serve: Apache esegue index.php.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if (preg_match('#^/(lib|sql|tests|data)(/|$)#', $uri) === 1) {
    http_response_code(404);
    echo 'Non trovato';
    return;
}
$local = __DIR__ . $uri;
if ($uri !== '/' && is_file($local)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
