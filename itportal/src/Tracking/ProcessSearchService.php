<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

use ItPortal\Config;
use ItPortal\Db\OracleConnection;
use ItPortal\Tracking\Sql\LegacySql;

/**
 * Process-variable search (legacy SELECT_SINGLE_PROCESS data layer).
 */
final class ProcessSearchService
{
    public function __construct(
        private readonly TraceNormalizer $normalizer,
        private readonly ?OracleProcessRepository $oracle = null,
    ) {
    }

    /**
     * @return array{ok:bool,message:string,serial:string,table:string,rows:list<array<string,mixed>>,meta:array<string,string>}
     */
    public function search(string $serial, string $procType = '0', int $fields = 20): array
    {
        $serial = $this->normalizer->serial($serial);
        if ($serial === '') {
            return [
                'ok' => false,
                'message' => 'Enter a serial number.',
                'serial' => '',
                'table' => '',
                'rows' => [],
                'meta' => [],
            ];
        }

        $driver = (string) Config::get('app.auth_driver', 'mock');
        if ($driver === 'oracle' || ($driver !== 'mock' && OracleConnection::available())) {
            try {
                $repo = $this->oracle ?? new OracleProcessRepository();
                return $repo->search($serial, $procType, $fields);
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Oracle process search failed: ' . $e->getMessage(),
                    'serial' => $serial,
                    'table' => LegacySql::processTable($procType),
                    'rows' => [],
                    'meta' => ['mode' => 'oracle-error'],
                ];
            }
        }

        $table = LegacySql::processTable($procType);
        return [
            'ok' => true,
            'message' => 'Demo process vars (mock). Set AUTH_DRIVER=oracle for SELECT_SINGLE_PROCESS.',
            'serial' => $serial,
            'table' => $table,
            'rows' => [
                [
                    'part' => 'DEMO-PART-001',
                    'part_sn' => $serial,
                    'wc_line_code' => 'AB1',
                    'wc_machine_group_code' => 'WELD',
                    'wc_machine_number' => '03',
                    'machine_type' => 'WELD',
                    'timestamp' => date('d-m-Y H:i:s', time() - 1800),
                    'values' => [
                        'VALUE_01' => '12.4',
                        'VALUE_02' => 'OK',
                        'VALUE_03' => 'PASS',
                    ],
                ],
                [
                    'part' => 'DEMO-PART-001',
                    'part_sn' => $serial,
                    'wc_line_code' => 'AB1',
                    'wc_machine_group_code' => 'LEAK',
                    'wc_machine_number' => '02',
                    'machine_type' => 'LEAK',
                    'timestamp' => date('d-m-Y H:i:s', time() - 900),
                    'values' => [
                        'VALUE_01' => '0.02',
                        'VALUE_02' => 'PASS',
                    ],
                ],
            ],
            'meta' => [
                'mode' => 'mock',
                'source' => 'mock (legacy SQL ready in LegacySql::processVariables)',
                'proc_type' => $procType,
            ],
        ];
    }
}
