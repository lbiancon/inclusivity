<?php

declare(strict_types=1);

if (!defined('LUMINA_ROOT')) {
    require dirname(__DIR__) . '/src/bootstrap.php';
}

use Lumina\Db;

function lumina_statements(string $sql): array
{
    $stripped = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*(?:\R|$)/', $stripped) ?: [];

    return array_values(array_filter(array_map(trim(...), $parts), static fn (string $part): bool => $part !== ''));
}

function lumina_reset(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables = $pdo->query('SHOW TABLES');
    if ($tables !== false) {
        foreach ($tables->fetchAll(PDO::FETCH_NUM) as $row) {
            $name = (string) $row[0];
            if (preg_match('/^[A-Za-z0-9_]+$/', $name) === 1) {
                $pdo->exec('DROP TABLE IF EXISTS `' . $name . '`');
            }
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function lumina_migrate(PDO $pdo): void
{
    $sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Schema mancante.');
    }
    foreach (lumina_statements($sql) as $statement) {
        $pdo->exec($statement);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $pdo = Db::connect();
    lumina_reset($pdo);
    lumina_migrate($pdo);
    echo "Schema applicato.\n";
}
