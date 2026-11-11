<?php

declare(strict_types=1);

use MenuCraft\Schema\Installer;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

/**
 * Connessione PDO per XAMPP e per gli ambienti in cui le credenziali arrivano
 * da MENUCRAFT_DB_HOST, MENUCRAFT_DB_PORT, MENUCRAFT_DB_NAME, MENUCRAFT_DB_USER
 * e MENUCRAFT_DB_PASS. Il default è l'utente root senza password di XAMPP.
 */
function menucraft_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = menucraft_env('MENUCRAFT_DB_HOST', '127.0.0.1');
    $port = menucraft_env('MENUCRAFT_DB_PORT', '3306');
    $name = menucraft_env('MENUCRAFT_DB_NAME', 'menucraft');
    $user = menucraft_env('MENUCRAFT_DB_USER', 'root');
    $pass = menucraft_env('MENUCRAFT_DB_PASS', '');

    if (preg_match('/^[A-Za-z0-9_.-]+$/', $host) !== 1 || preg_match('/^\d{2,5}$/', $port) !== 1) {
        throw new RuntimeException('Parametri di connessione non validi.');
    }
    if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
        throw new RuntimeException('Nome database non valido.');
    }

    $opzioni = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        PDO::MYSQL_ATTR_LOCAL_INFILE => false,
    ];

    $base = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $server = new PDO($base, $user, $pass, $opzioni);
    try {
        $server->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $name,
        ));
    } catch (PDOException) {
        // Un utente applicativo già limitato al proprio schema non può creare il database.
    }
    $server = null;

    $pdo = new PDO($base . ';dbname=' . $name, $user, $pass, $opzioni);
    $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    (new Installer($pdo))->install();

    return $pdo;
}

function menucraft_env(string $chiave, string $default): string
{
    $valore = getenv($chiave);
    if ($valore === false || $valore === '') {
        return $default;
    }

    return $valore;
}
