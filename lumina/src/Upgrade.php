<?php

declare(strict_types=1);

namespace Lumina;

use PDO;

final class Upgrade
{
    public static function apply(PDO $pdo): void
    {
        if (!self::table($pdo, 'users')) {
            return;
        }
        if (!self::column($pdo, 'users', 'email_verified_at')) {
            $pdo->exec('ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL, ADD COLUMN email_verified_at DATETIME NULL');
            $pdo->exec('UPDATE users SET email_verified_at = created_at WHERE password_hash IS NOT NULL AND email_verified_at IS NULL');
        }
        if (!self::column($pdo, 'businesses', 'logo_ext')) {
            $pdo->exec("ALTER TABLE businesses ADD COLUMN logo_ext VARCHAR(4) NULL, ADD COLUMN accent CHAR(7) NOT NULL DEFAULT '#12151a'");
        }
        if (!self::column($pdo, 'businesses', 'plan')) {
            $pdo->exec("ALTER TABLE businesses ADD COLUMN plan ENUM('bottega','salone','borgo') NOT NULL DEFAULT 'salone', ADD COLUMN plan_interval ENUM('month','year') NOT NULL DEFAULT 'month'");
            $pdo->exec("UPDATE businesses SET plan = 'borgo' WHERE slug = 'palestra-borgo'");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            code_hash CHAR(64) NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            ip_hash CHAR(64) NOT NULL,
            KEY ix_codes_email (email, created_at),
            KEY ix_codes_ip (ip_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS oauth_states (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nonce CHAR(64) NOT NULL,
            provider ENUM('google','apple') NOT NULL,
            verifier VARCHAR(128) NOT NULL,
            nonce_token VARCHAR(64) NOT NULL,
            intent VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            UNIQUE KEY uq_oauth_nonce (nonce)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS oauth_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            provider ENUM('google','apple') NOT NULL,
            subject VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_oauth_subject (provider, subject),
            KEY ix_oauth_user (user_id),
            CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mail_letters (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            subject VARCHAR(180) NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            KEY ix_letters (email, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private static function table(PDO $pdo, string $table): bool
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return false;
        }
        $statement = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

        return $statement !== false && $statement->fetch() !== false;
    }

    private static function column(PDO $pdo, string $table, string $column): bool
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table . $column) !== 1) {
            return false;
        }
        $statement = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column));

        return $statement !== false && $statement->fetch() !== false;
    }
}
