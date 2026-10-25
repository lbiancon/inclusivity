<?php

declare(strict_types=1);

namespace Lumina;

use PDO;
use PDOException;
use PDOStatement;

final class Db
{
    public static function connect(?string $name = null): PDO
    {
        try {
            return self::open($name);
        } catch (PDOException $exception) {
            if (!self::canRepair($name, $exception)) {
                throw $exception;
            }
            $root = self::root();
            if (!$root instanceof PDO) {
                throw $exception;
            }
            self::installLocal($root);

            return self::open($name);
        }
    }

    public static function installLocal(PDO $root): void
    {
        $root->exec('CREATE DATABASE IF NOT EXISTS lumina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $root->exec('CREATE DATABASE IF NOT EXISTS lumina_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        foreach (['127.0.0.1', 'localhost'] as $host) {
            $root->exec("CREATE USER IF NOT EXISTS 'lumina'@'{$host}' IDENTIFIED BY 'lumina_local'");
            $root->exec("ALTER USER 'lumina'@'{$host}' IDENTIFIED BY 'lumina_local'");
            $root->exec("GRANT ALL PRIVILEGES ON lumina.* TO 'lumina'@'{$host}'");
            $root->exec("GRANT ALL PRIVILEGES ON lumina_test.* TO 'lumina'@'{$host}'");
        }
        $root->exec('FLUSH PRIVILEGES');
    }

    public static function localAccountHelp(): string
    {
        return "MariaDB è avviato, ma rifiuta l'utente lumina.\n"
            . "Su XAMPP, lumina@localhost e lumina@127.0.0.1 sono due account diversi e servono entrambi, con password lumina_local.\n\n"
            . "Se root non ha password, nel prompt:\n"
            . "cd C:\\xampp\\htdocs\\lumina\n"
            . "C:\\xampp\\php\\php.exe bin\\xampp.php\n\n"
            . "Se quel comando dice che root non entra, apri http://localhost/phpmyadmin, scheda SQL, ed esegui:\n"
            . "CREATE DATABASE IF NOT EXISTS lumina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
            . "CREATE DATABASE IF NOT EXISTS lumina_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
            . "CREATE USER IF NOT EXISTS 'lumina'@'localhost' IDENTIFIED BY 'lumina_local';\n"
            . "CREATE USER IF NOT EXISTS 'lumina'@'127.0.0.1' IDENTIFIED BY 'lumina_local';\n"
            . "ALTER USER 'lumina'@'localhost' IDENTIFIED BY 'lumina_local';\n"
            . "ALTER USER 'lumina'@'127.0.0.1' IDENTIFIED BY 'lumina_local';\n"
            . "GRANT ALL PRIVILEGES ON lumina.* TO 'lumina'@'localhost';\n"
            . "GRANT ALL PRIVILEGES ON lumina.* TO 'lumina'@'127.0.0.1';\n"
            . "GRANT ALL PRIVILEGES ON lumina_test.* TO 'lumina'@'localhost';\n"
            . "GRANT ALL PRIVILEGES ON lumina_test.* TO 'lumina'@'127.0.0.1';\n"
            . "FLUSH PRIVILEGES;\n\n"
            . "Poi, sempre dentro C:\\xampp\\htdocs\\lumina:\n"
            . "C:\\xampp\\php\\php.exe bin\\seed.php\n\n"
            . "Gli altri database restano dov'erano. Ricarica http://localhost/lumina/";
    }

    private static function open(?string $name): PDO
    {
        $database = $name ?? (getenv('LUMINA_DB_NAME') ?: 'lumina');
        $host = getenv('LUMINA_DB_HOST') ?: '127.0.0.1';
        $port = getenv('LUMINA_DB_PORT') ?: '3306';
        $user = getenv('LUMINA_DB_USER') ?: 'lumina';
        $pass = getenv('LUMINA_DB_PASS') ?: 'lumina_local';
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }

    private static function canRepair(?string $name, PDOException $exception): bool
    {
        $database = $name ?? (getenv('LUMINA_DB_NAME') ?: 'lumina');
        $host = getenv('LUMINA_DB_HOST') ?: '127.0.0.1';
        $user = getenv('LUMINA_DB_USER') ?: 'lumina';
        $pass = getenv('LUMINA_DB_PASS') ?: 'lumina_local';
        $message = $exception->getMessage();
        $repairable = str_contains($message, '[1045]')
            || str_contains($message, '[1044]')
            || str_contains($message, '[1049]');
        if (!$repairable || !in_array($database, ['lumina', 'lumina_test'], true)) {
            return false;
        }
        if (!in_array($host, ['127.0.0.1', 'localhost'], true) || $user !== 'lumina' || $pass !== 'lumina_local') {
            return false;
        }
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return true;
        }
        $addr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return $addr === '' || $addr === '127.0.0.1' || $addr === '::1';
    }

    private static function root(): ?PDO
    {
        $password = getenv('XAMPP_MYSQL_ROOT_PASSWORD');
        $password = is_string($password) ? $password : '';
        $port = getenv('LUMINA_DB_PORT') ?: '3306';
        foreach (['127.0.0.1', 'localhost'] as $host) {
            try {
                return new PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } catch (PDOException) {
                continue;
            }
        }

        return null;
    }

    public static function run(PDO $pdo, string $sql, array $params = []): PDOStatement
    {
        $statement = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? (string) $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($name, $value, $type);
        }
        $statement->execute();

        return $statement;
    }

    public static function all(PDO $pdo, string $sql, array $params = []): array
    {
        return self::run($pdo, $sql, $params)->fetchAll();
    }

    public static function one(PDO $pdo, string $sql, array $params = []): ?array
    {
        $row = self::run($pdo, $sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public static function value(PDO $pdo, string $sql, array $params = []): mixed
    {
        $value = self::run($pdo, $sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    public static function id(PDO $pdo): int
    {
        return (int) $pdo->lastInsertId();
    }

    public static function duplicate(PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }
}
