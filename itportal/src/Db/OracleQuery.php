<?php

declare(strict_types=1);

namespace ItPortal\Db;

/**
 * Runs parameterized OCI queries. Replaces string-concat SQL from legacy function.php.
 */
final class OracleQuery
{
    /**
     * @param resource $conn
     * @param array<string, scalar|null> $binds  e.g. [':part_sn' => 'ABC']
     * @return list<array<string, mixed>>
     */
    public static function fetchAll($conn, string $sql, array $binds = []): array
    {
        $stid = oci_parse($conn, $sql);
        if ($stid === false) {
            $err = oci_error($conn);
            throw new \RuntimeException('oci_parse failed: ' . ($err['message'] ?? 'unknown'));
        }

        $refs = [];
        foreach ($binds as $name => $value) {
            $refs[$name] = $value;
            if (!@oci_bind_by_name($stid, $name, $refs[$name])) {
                $err = oci_error($stid);
                oci_free_statement($stid);
                throw new \RuntimeException('oci_bind failed on ' . $name . ': ' . ($err['message'] ?? 'unknown'));
            }
        }

        if (@oci_execute($stid, OCI_DEFAULT) === false) {
            $err = oci_error($stid);
            oci_free_statement($stid);
            throw new \RuntimeException('oci_execute failed: ' . ($err['message'] ?? 'unknown'));
        }

        $rows = [];
        while (($row = oci_fetch_assoc($stid)) !== false) {
            $rows[] = $row;
        }
        oci_free_statement($stid);

        return $rows;
    }
}
