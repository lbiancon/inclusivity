<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

use ItPortal\Db\OracleConnection;
use ItPortal\Db\OracleQuery;
use ItPortal\Tracking\Sql\LegacySql;

/**
 * Runs the same LOT_SERIAL / PARENT_PART_SN queries as function.php.
 */
final class OracleSerialRepository
{
    /**
     * @return array{ok:bool,message:string,serial:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    public function search(string $serial, string $server, bool $parent, string $partLike = '%'): array
    {
        $conn = OracleConnection::connect();
        $partLike = $partLike === '' ? '%' : $partLike;

        $genealogySql = $parent ? LegacySql::PARENT_BY_PARENT_PART_SN : LegacySql::PARENT_BY_PART_SN;
        $genealogy = OracleQuery::fetchAll($conn, $genealogySql, [
            ':part_sn' => $serial,
            ':part_like' => $partLike,
        ]);

        $parentSn = $serial;
        if ($genealogy !== []) {
            $parentSn = (string) ($genealogy[0]['PARENT_PART_SN'] ?? $serial);
        }

        $rowsRaw = OracleQuery::fetchAll($conn, LegacySql::LOT_SERIAL_BY_PART_SN, [
            ':part_sn' => $parent ? $parentSn : $serial,
            ':part_like' => $partLike,
        ]);

        if ($rowsRaw === [] && $parentSn !== $serial) {
            $rowsRaw = OracleQuery::fetchAll($conn, LegacySql::LOT_SERIAL_BY_PART_SN, [
                ':part_sn' => $serial,
                ':part_like' => $partLike,
            ]);
        }

        if ($rowsRaw === []) {
            return [
                'ok' => false,
                'message' => 'No LOT_SERIAL rows for this serial.',
                'serial' => $serial,
                'rows' => [],
                'meta' => [
                    'server' => $server,
                    'mode' => 'oracle',
                    'parent_part_sn' => $parentSn,
                ],
            ];
        }

        $rows = [];
        $part = '';
        foreach ($rowsRaw as $row) {
            $part = (string) ($row['PART'] ?? $part);
            $rows[] = [
                'station' => (string) ($row['CONT_ID'] ?? ''),
                'op' => (string) ($row['OP_SEQ_NUMBER'] ?? ''),
                'result' => (string) ($row['IGNITER_TYPE'] ?? ''),
                'part' => (string) ($row['PART'] ?? ''),
                'timestamp' => (string) ($row['TIME_STAMP_STRING'] ?? ''),
                'operator' => '',
                'part_sn' => (string) ($row['PART_SN'] ?? ''),
                'parent_part_sn' => (string) ($row['PARENT_PART_SN'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'message' => 'LOT_SERIAL (legacy query, parameterized).',
            'serial' => $serial,
            'rows' => $rows,
            'meta' => [
                'server' => $server,
                'mode' => 'oracle',
                'part' => $part,
                'parent_part_sn' => $parentSn,
                'source' => 'function.php LOT_SERIAL / PARENT_PART_SN',
            ],
        ];
    }
}
