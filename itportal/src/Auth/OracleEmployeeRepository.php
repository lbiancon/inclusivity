<?php

declare(strict_types=1);

namespace ItPortal\Auth;

/**
 * Production adapter for Oracle lot_employee (legacy LOGIN_PORTAL).
 * Requires oci8 extension and ORACLE_* env vars.
 */
final class OracleEmployeeRepository implements EmployeeRepositoryInterface
{
    /** @var resource|false */
    private $connection;

    public function __construct()
    {
        if (!function_exists('oci_connect')) {
            throw new \RuntimeException('PHP oci8 extension is required for AUTH_DRIVER=oracle.');
        }

        $user = (string) getenv('ORACLE_USER');
        $pass = (string) getenv('ORACLE_PASSWORD');
        $conn = (string) getenv('ORACLE_CONNECTION');

        $this->connection = @oci_connect($user, $pass, $conn);
        if ($this->connection === false) {
            $err = oci_error();
            throw new \RuntimeException('Oracle connection failed: ' . ($err['message'] ?? 'unknown'));
        }
    }

    public function findByBadge(string $badge): ?UserRecord
    {
        $sql = 'SELECT EMPL_ID, USER_ID, SUPERUSER, EMPL_QUAL_CODE, ROLES, EMPL_LAST, EMPL_FIRST
                FROM lot_employee
                WHERE user_id = :badge AND ROLES IS NOT NULL';

        $stid = oci_parse($this->connection, $sql);
        oci_bind_by_name($stid, ':badge', $badge);
        oci_execute($stid, OCI_DEFAULT);

        $row = oci_fetch_assoc($stid);
        if ($row === false) {
            return null;
        }

        return new UserRecord(
            login: (string) $row['USER_ID'],
            emplId: (string) $row['EMPL_ID'],
            nome: (string) $row['EMPL_LAST'],
            cognome: (string) $row['EMPL_FIRST'],
            superuser: (string) $row['SUPERUSER'],
            empQuaCode: (string) $row['EMPL_QUAL_CODE'],
            roles: (string) $row['ROLES'],
        );
    }
}
