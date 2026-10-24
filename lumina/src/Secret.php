<?php

declare(strict_types=1);

namespace Lumina;

final class Secret
{
    public static function key(): string
    {
        $env = getenv('LUMINA_APP_KEY');
        if (is_string($env) && strlen($env) >= 32) {
            return $env;
        }
        $path = LUMINA_ROOT . '/storage/app.key';
        if (is_file($path)) {
            $stored = trim((string) file_get_contents($path));
            if (strlen($stored) >= 32) {
                return $stored;
            }
        }
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('Cartella storage non creabile.');
        }
        $stored = bin2hex(random_bytes(32));
        file_put_contents($path, $stored);
        chmod($path, 0600);

        return $stored;
    }

    public static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    public static function publicUrl(): string
    {
        $env = getenv('LUMINA_PUBLIC_URL');
        if (is_string($env) && preg_match('#^https?://[A-Za-z0-9.-]+(?::\d+)?$#', rtrim($env, '/')) === 1) {
            return rtrim($env, '/');
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080');
        if (preg_match('/^[A-Za-z0-9.-]+(?::\d+)?$/', $host) !== 1) {
            $host = '127.0.0.1:8080';
        }

        return ($https ? 'https' : 'http') . '://' . $host . lumina_base();
    }
}
