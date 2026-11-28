<?php

declare(strict_types=1);

namespace ItPortal\Engineering;

use ItPortal\Config;
use ItPortal\Db\OracleConnection;
use ItPortal\Db\OracleQuery;
use ItPortal\Engineering\Sql\LegacySql;

/**
 * Lightweight scrap aggregate — same DASHBOARD SQL as SELECT_REPORT_LINE_SCRAP core.
 */
final class ScrapReportService
{
    /**
     * @return array{ok:bool,message:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    public function summarize(string $lineId, string $wcLine, string $startTs, string $endTs): array
    {
        $lineId = trim($lineId);
        $wcLine = trim($wcLine) !== '' ? trim($wcLine) : '%';
        if ($lineId === '' || $startTs === '' || $endTs === '') {
            return [
                'ok' => false,
                'message' => 'line, start and end are required (DD/MM/YYYY HH24:MI:SS).',
                'rows' => [],
                'meta' => [],
            ];
        }

        // Legacy used ID like 'LINE%'
        $lineLike = str_ends_with($lineId, '%') ? $lineId : $lineId . '%';

        $driver = (string) Config::get('app.auth_driver', 'mock');
        if ($driver === 'oracle' || ($driver !== 'mock' && OracleConnection::available())) {
            try {
                $conn = OracleConnection::connect();
                $rows = OracleQuery::fetchAll($conn, LegacySql::DASHBOARD_SCRAP_SUM, [
                    ':line_id' => $lineLike,
                    ':wc_line' => $wcLine,
                    ':start_ts' => $startTs,
                    ':end_ts' => $endTs,
                ]);
                $row = $rows[0] ?? [];
                return [
                    'ok' => true,
                    'message' => 'DASHBOARD scrap sum (legacy SELECT_REPORT_LINE_SCRAP core).',
                    'rows' => [$this->normalize($row)],
                    'meta' => [
                        'mode' => 'oracle',
                        'line_id' => $lineLike,
                        'wc_line' => $wcLine,
                        'source' => 'function.php SELECT_REPORT_LINE_SCRAP',
                    ],
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Oracle scrap report failed: ' . $e->getMessage(),
                    'rows' => [],
                    'meta' => ['mode' => 'oracle-error'],
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Demo scrap aggregate (mock). Set AUTH_DRIVER=oracle for DASHBOARD SQL.',
            'rows' => [[
                'qty' => '1200',
                'scrap_0' => '12',
                'scrap_1' => '3',
                'scrap_2' => '1',
                'scrap_3' => '0',
                'scrap_4' => '2',
                'scrap_5' => '0',
                'scrap_6' => '1',
                'scrap_7' => '0',
                'scrap_8' => '0',
                'scrap_9' => '0',
                'scrap_10' => '0',
            ]],
            'meta' => [
                'mode' => 'mock',
                'line_id' => $lineLike,
                'wc_line' => $wcLine,
                'source' => 'mock (SQL ready in Engineering\\Sql\\LegacySql)',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function normalize(array $row): array
    {
        $out = [];
        foreach (['QTY', 'SCRAP_0', 'SCRAP_1', 'SCRAP_2', 'SCRAP_3', 'SCRAP_4', 'SCRAP_5', 'SCRAP_6', 'SCRAP_7', 'SCRAP_8', 'SCRAP_9', 'SCRAP_10'] as $key) {
            $out[strtolower($key)] = (string) ($row[$key] ?? '0');
        }
        return $out;
    }
}
