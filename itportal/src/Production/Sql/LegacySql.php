<?php

declare(strict_types=1);

namespace ItPortal\Production\Sql;

/**
 * SQL from functionTools.php — MP / Efficiency / targets.
 * Table names for MP/efficiency are passed by the legacy pages; whitelist only.
 */
final class LegacySql
{
    /** Allowed Oracle detail tables used by tools pages. */
    public static function assertDetailTable(string $table): string
    {
        $allowed = [
            'LOT_PROCESS_VARIABLES_DETAIL',
            'LOT_MP_EMPLOYEE_IF',
            'LOT_MP_EMPLOYEE_AB',
            'LOT_PRODUCTION',
        ];
        $table = strtoupper(trim($table));
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Table not allowed: ' . $table);
        }
        return $table;
    }

    /**
     * SELECT_TABLE_MP core — manpower roster where E_CENTER_CODE = PRO.
     * Legacy: Select * from $table where E_CENTER_CODE = 'PRO'
     */
    public const MP_ROSTER = <<<'SQL'
SELECT *
  FROM LOT_MP_EMPLOYEE_IF
 WHERE E_CENTER_CODE = 'PRO'
 ORDER BY E_FULLNAME DESC
SQL;

    public const MP_ROSTER_AB = <<<'SQL'
SELECT *
  FROM LOT_MP_EMPLOYEE_AB
 WHERE E_CENTER_CODE = 'PRO'
 ORDER BY E_FULLNAME DESC
SQL;

    /**
     * SELECT_TABLE_EFFICENCY core.
     * Legacy: Select * from $table where MACHINE_TYPE LIKE '%REQUEST_EFF'
     */
    public const EFFICIENCY_REQUESTS = <<<'SQL'
SELECT *
  FROM LOT_PROCESS_VARIABLES_DETAIL
 WHERE MACHINE_TYPE LIKE '%REQUEST_EFF'
SQL;

    /**
     * Presence / production punches used beside MP tables.
     */
    public const PRODUCTION_BY_EMPL = <<<'SQL'
SELECT *
  FROM LOT_PRODUCTION
 WHERE EMPL_ID LIKE :empl_id
SQL;
}
