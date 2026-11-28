<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

use ItPortal\Db\OracleConnection;
use ItPortal\Db\OracleQuery;
use ItPortal\Tracking\Sql\LegacySql;

/**
 * SELECT_SINGLE_PROCESS core — reads LOT_PROCESS_VARIABLES* by serial.
 * Legacy HTML/operator enrichment stays out; this returns structured rows.
 */
final class OracleProcessRepository
{
    /**
     * @return array{ok:bool,message:string,serial:string,table:string,rows:list<array<string,mixed>>,meta:array<string,string>}
     */
    public function search(string $serial, string $procType = '0', int $fields = 20): array
    {
        $table = LegacySql::processTable($procType);
        $sql = LegacySql::processVariables($table, $fields > 10);
        $conn = OracleConnection::connect();

        $rows = OracleQuery::fetchAll($conn, $sql, [
            ':part_sn' => $serial,
        ]);

        if ($rows === []) {
            return [
                'ok' => false,
                'message' => "No rows in {$table} for this serial.",
                'serial' => $serial,
                'table' => $table,
                'rows' => [],
                'meta' => ['mode' => 'oracle', 'source' => 'function.php SELECT_SINGLE_PROCESS'],
            ];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $values = [];
            for ($i = 1; $i <= ($fields > 10 ? 25 : 10); $i++) {
                $key = 'VALUE_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                    $values[$key] = (string) $row[$key];
                }
            }
            $normalized[] = [
                'part' => (string) ($row['PART'] ?? ''),
                'part_sn' => (string) ($row['PART_SN'] ?? ''),
                'wc_line_code' => (string) ($row['WC_LINE_CODE'] ?? ''),
                'wc_machine_group_code' => (string) ($row['WC_MACHINE_GROUP_CODE'] ?? ''),
                'wc_machine_number' => (string) ($row['WC_MACHINE_NUMBER'] ?? ''),
                'machine_type' => (string) ($row['MACHINE_TYPE'] ?? ''),
                'timestamp' => (string) ($row['TIME_STAMP_STRING'] ?? ''),
                'values' => $values,
            ];
        }

        return [
            'ok' => true,
            'message' => 'SELECT_SINGLE_PROCESS (legacy query, parameterized).',
            'serial' => $serial,
            'table' => $table,
            'rows' => $normalized,
            'meta' => [
                'mode' => 'oracle',
                'source' => 'function.php SELECT_SINGLE_PROCESS / get_name_table_proc',
                'proc_type' => $procType,
            ],
        ];
    }
}
