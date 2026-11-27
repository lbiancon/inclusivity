<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

use ItPortal\Config;

/**
 * Replaces the pallet_sn branch of legacy tables.php.
 */
final class PalletSearchService
{
    public function __construct(private readonly TraceNormalizer $normalizer)
    {
    }

    /**
     * @return array{ok:bool,message:string,pallet:string,rows:list<array<string,string>>,meta:array<string,string>}
     */
    public function search(string $pallet, string $server = 'CFP1'): array
    {
        $pallet = $this->normalizer->serial($pallet);
        $server = $this->normalizer->server($server);

        if ($pallet === '') {
            return [
                'ok' => false,
                'message' => 'Enter a pallet / container id.',
                'pallet' => '',
                'rows' => [],
                'meta' => [],
            ];
        }

        $driver = (string) Config::get('app.auth_driver', 'mock');
        if ($driver !== 'mock') {
            return [
                'ok' => false,
                'message' => 'Oracle pallet adapter not connected yet.',
                'pallet' => $pallet,
                'rows' => [],
                'meta' => ['server' => $server],
            ];
        }

        $rows = [];
        for ($i = 1; $i <= 5; $i++) {
            $rows[] = [
                'cont_id' => $pallet,
                'part_sn' => 'SN' . substr($pallet, 0, 8) . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'part' => 'DEMO-PART',
                'qty' => '1',
                'timestamp' => date('Y-m-d H:i:s', time() - $i * 120),
            ];
        }

        return [
            'ok' => true,
            'message' => 'Demo pallet result. Production will call Oracle packout tables.',
            'pallet' => $pallet,
            'rows' => $rows,
            'meta' => [
                'server' => $server,
                'mode' => 'mock',
            ],
        ];
    }
}
