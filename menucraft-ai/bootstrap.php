<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

define('MENUCRAFT_APP', true);

date_default_timezone_set('Europe/Rome');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.sid_length', '48');
ini_set('session.sid_bits_per_character', '6');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
ini_set('session.cookie_secure', $https ? '1' : '0');
session_name('MENUCRAFT_SID');

spl_autoload_register(static function (string $class): void {
    $prefix = 'MenuCraft\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/config/database.php';

if (PHP_SAPI !== 'cli') {
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header('Cache-Control: no-store, max-age=0');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; "
        . "base-uri 'self'; form-action 'self'"
    );
}
