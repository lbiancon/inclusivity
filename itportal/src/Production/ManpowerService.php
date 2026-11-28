<?php

declare(strict_types=1);

namespace ItPortal\Production;

use ItPortal\Config;
use ItPortal\Db\OracleConnection;
use ItPortal\Db\OracleQuery;
use ItPortal\Production\Sql\LegacySql;

/**
 * MP tables from functionTools.php SELECT_TABLE_MP / MP-AB.
 */
final class ManpowerService
{
    /**
     * @return array{ok:bool,message:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    public function roster(string $plant = 'IF'): array
    {
        $sql = strtoupper($plant) === 'AB' ? LegacySql::MP_ROSTER_AB : LegacySql::MP_ROSTER;
        $driver = (string) Config::get('app.auth_driver', 'mock');

        if ($driver === 'oracle' || ($driver !== 'mock' && OracleConnection::available())) {
            try {
                $conn = OracleConnection::connect();
                $raw = OracleQuery::fetchAll($conn, $sql);
                $rows = [];
                foreach ($raw as $row) {
                    $rows[] = [
                        'fullname' => (string) ($row['E_FULLNAME'] ?? $row['EMPL_ID'] ?? ''),
                        'empl_id' => (string) ($row['EMPL_ID'] ?? $row['E_ID'] ?? ''),
                        'center' => (string) ($row['E_CENTER_CODE'] ?? 'PRO'),
                    ];
                }
                return [
                    'ok' => true,
                    'message' => 'MP roster (functionTools SELECT_TABLE_MP).',
                    'rows' => $rows,
                    'meta' => ['mode' => 'oracle', 'plant' => $plant, 'source' => 'functionTools.php'],
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Oracle MP failed: ' . $e->getMessage(),
                    'rows' => [],
                    'meta' => ['mode' => 'oracle-error'],
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Demo MP roster (mock). SQL from functionTools.php ready.',
            'rows' => [
                ['fullname' => 'ROSSI MARIO', 'empl_id' => '00001', 'center' => 'PRO'],
                ['fullname' => 'BIANCHI LUCA', 'empl_id' => '00014', 'center' => 'PRO'],
            ],
            'meta' => [
                'mode' => 'mock',
                'plant' => $plant,
                'source' => 'mock (Production\\Sql\\LegacySql::MP_ROSTER*)',
            ],
        ];
    }
}
