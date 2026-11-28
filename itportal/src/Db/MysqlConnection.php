<?php

declare(strict_types=1);

namespace ItPortal\Db;

/**
 * PDO MySQL factory for portal DBs (itportal / checkdb / mensa).
 * Credentials only from env.
 */
final class MysqlConnection
{
    /** @var array<string, \PDO> */
    private static array $pool = [];

    public static function available(string $name = 'check'): bool
    {
        $prefix = self::prefix($name);
        return (string) getenv($prefix . '_HOST') !== ''
            && (string) getenv($prefix . '_DATABASE') !== '';
    }

    public static function connect(string $name = 'check'): \PDO
    {
        if (isset(self::$pool[$name])) {
            return self::$pool[$name];
        }

        $prefix = self::prefix($name);
        $host = (string) getenv($prefix . '_HOST');
        $port = (string) (getenv($prefix . '_PORT') ?: '3306');
        $db = (string) getenv($prefix . '_DATABASE');
        $user = (string) getenv($prefix . '_USER');
        $pass = (string) (getenv($prefix . '_PASSWORD') ?: '');

        if ($host === '' || $db === '') {
            throw new \RuntimeException("MySQL env incomplete for [{$name}] ({$prefix}_HOST / _DATABASE).");
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        self::$pool[$name] = $pdo;
        return $pdo;
    }

    private static function prefix(string $name): string
    {
        return match ($name) {
            'portal' => 'MYSQL',
            'check' => 'MYSQL_CHECK',
            'mensa' => 'MYSQL_MENSA',
            default => 'MYSQL_' . strtoupper($name),
        };
    }
}
