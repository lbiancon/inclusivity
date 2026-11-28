<?php

declare(strict_types=1);

namespace ItPortal\Db;

/**
 * Thin OCI8 connection factory. Credentials only from env — never from source.
 */
final class OracleConnection
{
    /** @var resource|false|null */
    private static $connection = null;

    public static function available(): bool
    {
        return function_exists('oci_connect')
            && (string) getenv('ORACLE_USER') !== ''
            && (string) getenv('ORACLE_CONNECTION') !== '';
    }

    /** @return resource */
    public static function connect()
    {
        if (self::$connection !== null && self::$connection !== false) {
            return self::$connection;
        }

        if (!function_exists('oci_connect')) {
            throw new \RuntimeException('PHP oci8 extension is not installed.');
        }

        $user = (string) getenv('ORACLE_USER');
        $pass = (string) getenv('ORACLE_PASSWORD');
        $conn = (string) getenv('ORACLE_CONNECTION');

        if ($user === '' || $conn === '') {
            throw new \RuntimeException('ORACLE_USER / ORACLE_CONNECTION env vars are required.');
        }

        $handle = @oci_connect($user, $pass, $conn);
        if ($handle === false) {
            $err = oci_error();
            throw new \RuntimeException('Oracle connect failed: ' . ($err['message'] ?? 'unknown'));
        }

        self::$connection = $handle;
        return $handle;
    }
}
