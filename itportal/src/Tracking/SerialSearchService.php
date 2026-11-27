<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

use ItPortal\Config;

/**
 * Replaces the part_sn branch of legacy tables.php + LOT_SERIAL helpers.
 * Keep this file focused: search serial only. Parent/process/photo stay sibling services.
 */
final class SerialSearchService
{
    public function __construct(private readonly TraceNormalizer $normalizer)
    {
    }

    /**
     * @return array{ok:bool,message:string,serial:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    public function search(string $serial, string $server = 'CFP1', bool $parent = false): array
    {
        $serial = $this->normalizer->serial($serial);
        $server = $this->normalizer->server($server);

        if ($serial === '') {
            return $this->fail('', 'Enter a serial number.');
        }

        $driver = (string) Config::get('app.auth_driver', 'mock');
        if ($driver === 'mock') {
            return $this->mockTrace($serial, $server, $parent);
        }

        // Production: inject Oracle repository here (LOT_SERIAL / PARENT_PART_SN).
        return $this->fail(
            $serial,
            'Oracle tracking adapter not connected yet. Keep AUTH_DRIVER=mock for UI demos, or wire LOT_SERIAL next.',
            ['server' => $server]
        );
    }

    /**
     * @param array<string,string> $meta
     * @return array{ok:bool,message:string,serial:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    private function fail(string $serial, string $message, array $meta = []): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'serial' => $serial,
            'rows' => [],
            'meta' => $meta,
        ];
    }

    /**
     * @return array{ok:bool,message:string,serial:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    private function mockTrace(string $serial, string $server, bool $parent): array
    {
        $base = substr(preg_replace('/[^A-Z0-9]/', '', $serial) ?: 'DEMO', 0, 14);
        $parentSn = $parent ? 'P' . $base : $base;

        return [
            'ok' => true,
            'message' => 'Demo trace (mock). Same UI will bind to Oracle LOT_SERIAL / process tables.',
            'serial' => $serial,
            'rows' => [
                ['station' => 'FINAL-01', 'op' => '10', 'result' => 'PASS', 'part' => 'AB-HOUSING', 'timestamp' => date('Y-m-d H:i:s', time() - 3600), 'operator' => 'ROSSI M.'],
                ['station' => 'WELD-03', 'op' => '20', 'result' => 'PASS', 'part' => 'AB-COVER', 'timestamp' => date('Y-m-d H:i:s', time() - 2400), 'operator' => 'BIANCHI L.'],
                ['station' => 'LEAK-02', 'op' => '30', 'result' => 'PASS', 'part' => 'INFLATOR', 'timestamp' => date('Y-m-d H:i:s', time() - 1200), 'operator' => 'VERDI A.'],
                ['station' => 'PACKOUT', 'op' => '90', 'result' => 'OK', 'part' => 'FG', 'timestamp' => date('Y-m-d H:i:s', time() - 300), 'operator' => 'NERI S.'],
            ],
            'meta' => [
                'server' => $server,
                'parent_part_sn' => $parentSn,
                'part' => 'DEMO-PART-001',
                'mode' => 'mock',
            ],
        ];
    }
}
