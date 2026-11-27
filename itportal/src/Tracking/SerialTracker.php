<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

use ItPortal\Config;

/**
 * Tracking facade. Mock results locally; Oracle wiring lands when AUTH_DRIVER=oracle
 * and production adapters are connected (legacy tables.php / LOT_SERIAL).
 */
final class SerialTracker
{
    /**
     * @return array{ok:bool,message:string,serial:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    public function search(string $serial, string $server = 'CFP1', bool $parent = false): array
    {
        $serial = strtoupper(trim($serial));
        if ($serial === '') {
            return [
                'ok' => false,
                'message' => 'Enter a serial number.',
                'serial' => '',
                'rows' => [],
                'meta' => [],
            ];
        }

        $driver = (string) Config::get('app.auth_driver', 'mock');
        if ($driver !== 'mock') {
            return [
                'ok' => false,
                'message' => 'Oracle tracking adapter not connected yet. Keep AUTH_DRIVER=mock for UI demos, or wire LOT_SERIAL next.',
                'serial' => $serial,
                'rows' => [],
                'meta' => ['server' => $server],
            ];
        }

        // Deterministic demo trace for any serial (shows modern UI + flow)
        $base = substr(preg_replace('/[^A-Z0-9]/', '', $serial) ?: 'DEMO', 0, 14);
        $parentSn = $parent ? 'P' . $base : $base;
        $rows = [
            [
                'station' => 'FINAL-01',
                'op' => '10',
                'result' => 'PASS',
                'part' => 'AB-HOUSING',
                'timestamp' => date('Y-m-d H:i:s', time() - 3600),
                'operator' => 'ROSSI M.',
            ],
            [
                'station' => 'WELD-03',
                'op' => '20',
                'result' => 'PASS',
                'part' => 'AB-COVER',
                'timestamp' => date('Y-m-d H:i:s', time() - 2400),
                'operator' => 'BIANCHI L.',
            ],
            [
                'station' => 'LEAK-02',
                'op' => '30',
                'result' => 'PASS',
                'part' => 'INFLATOR',
                'timestamp' => date('Y-m-d H:i:s', time() - 1200),
                'operator' => 'VERDI A.',
            ],
            [
                'station' => 'PACKOUT',
                'op' => '90',
                'result' => 'OK',
                'part' => 'FG',
                'timestamp' => date('Y-m-d H:i:s', time() - 300),
                'operator' => 'NERI S.',
            ],
        ];

        return [
            'ok' => true,
            'message' => 'Demo trace (mock). Same UI will bind to Oracle LOT_SERIAL / process tables.',
            'serial' => $serial,
            'rows' => $rows,
            'meta' => [
                'server' => $server,
                'parent_part_sn' => $parentSn,
                'part' => 'DEMO-PART-001',
                'mode' => 'mock',
            ],
        ];
    }
}
