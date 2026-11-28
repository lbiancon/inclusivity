<?php

declare(strict_types=1);

namespace ItPortal\Dashboard;

use ItPortal\Config;
use ItPortal\Db\OracleConnection;
use ItPortal\Db\OracleQuery;
use ItPortal\Engineering\Sql\LegacySql;

/**
 * Data slice of functionDashboard.php SELECT_DASHBOARD_LINE.
 */
final class DashboardLineService
{
    /**
     * @return array{ok:bool,message:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    public function byId(string $dashId, string $wcLine = '%'): array
    {
        $dashId = trim($dashId);
        if ($dashId === '') {
            return ['ok' => false, 'message' => 'Dashboard id required.', 'rows' => [], 'meta' => []];
        }
        $idLike = str_ends_with($dashId, '%') ? $dashId : $dashId . '%';
        $wcLine = trim($wcLine) !== '' ? trim($wcLine) : '%';

        $driver = (string) Config::get('app.auth_driver', 'mock');
        if ($driver === 'oracle' || ($driver !== 'mock' && OracleConnection::available())) {
            try {
                $conn = OracleConnection::connect();
                $raw = OracleQuery::fetchAll($conn, LegacySql::DASHBOARD_BY_ID, [
                    ':dash_id' => $idLike,
                    ':wc_line' => $wcLine,
                ]);
                $rows = [];
                foreach ($raw as $row) {
                    $rows[] = [
                        'id' => (string) ($row['ID'] ?? ''),
                        'wc_line_code' => (string) ($row['WC_LINE_CODE'] ?? ''),
                        'wc_machine_group_code' => (string) ($row['WC_MACHINE_GROUP_CODE'] ?? ''),
                        'wc_machine_number' => (string) ($row['WC_MACHINE_NUMBER'] ?? ''),
                        'start' => (string) ($row['START_TIME_STRING'] ?? ''),
                        'end' => (string) ($row['END_TIME_STRING'] ?? ''),
                        'qty' => (string) ($row['QTY'] ?? '0'),
                        'scrap_0' => (string) ($row['SCRAP_0'] ?? '0'),
                        'note' => (string) ($row['NOTE'] ?? ''),
                    ];
                }
                return [
                    'ok' => true,
                    'message' => 'DASHBOARD by id (functionDashboard SELECT_DASHBOARD_LINE).',
                    'rows' => $rows,
                    'meta' => ['mode' => 'oracle', 'source' => 'functionDashboard.php'],
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Oracle dashboard failed: ' . $e->getMessage(),
                    'rows' => [],
                    'meta' => ['mode' => 'oracle-error'],
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Demo dashboard row (mock). SQL from functionDashboard.php ready.',
            'rows' => [[
                'id' => rtrim($idLike, '%') . 'M01',
                'wc_line_code' => 'AB',
                'wc_machine_group_code' => 'WELD',
                'wc_machine_number' => '01',
                'start' => date('d/m/Y H:i', time() - 3600),
                'end' => date('d/m/Y H:i'),
                'qty' => '85',
                'scrap_0' => '2',
                'note' => 'mock',
            ]],
            'meta' => [
                'mode' => 'mock',
                'source' => 'mock (SQL in Engineering\\Sql\\LegacySql::DASHBOARD_BY_ID)',
            ],
        ];
    }
}
