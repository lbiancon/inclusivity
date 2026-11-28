<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

use ItPortal\Db\OracleConnection;
use ItPortal\Db\OracleQuery;
use ItPortal\Tracking\Sql\LegacySql;

/**
 * Same SELECT_PALLET_CONT_AIR flow: try PALLET_ID, then CONT_ID.
 */
final class OraclePalletRepository
{
    /**
     * @return array{ok:bool,message:string,pallet:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    public function search(string $pallet, string $server): array
    {
        $conn = OracleConnection::connect();

        $rowsRaw = OracleQuery::fetchAll($conn, LegacySql::PALLET_BY_PALLET_ID, [
            ':pallet' => $pallet,
        ]);

        $via = 'PALLET_ID';
        if ($rowsRaw === []) {
            $rowsRaw = OracleQuery::fetchAll($conn, LegacySql::PALLET_BY_CONT_ID, [
                ':pallet' => $pallet,
            ]);
            $via = 'CONT_ID';
        }

        if ($rowsRaw === []) {
            return [
                'ok' => false,
                'message' => 'No pallet/cont rows (legacy SELECT_PALLET_CONT_AIR).',
                'pallet' => $pallet,
                'rows' => [],
                'meta' => ['server' => $server, 'mode' => 'oracle'],
            ];
        }

        $rows = [];
        foreach ($rowsRaw as $row) {
            $rows[] = [
                'cont_id' => (string) ($row['CONT_ID'] ?? ''),
                'part_sn' => (string) ($row['PART_SN'] ?? ''),
                'part' => (string) ($row['PART'] ?? ''),
                'qty' => '1',
                'timestamp' => (string) ($row['TIME_STAMP'] ?? ''),
                'pallet_id' => (string) ($row['PALLET_ID'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'message' => "SELECT_PALLET_CONT_AIR via {$via} (legacy query, parameterized).",
            'pallet' => $pallet,
            'rows' => $rows,
            'meta' => [
                'server' => $server,
                'mode' => 'oracle',
                'source' => 'function.php SELECT_PALLET_CONT_AIR',
            ],
        ];
    }
}
