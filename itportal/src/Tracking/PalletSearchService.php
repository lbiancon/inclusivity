<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

use ItPortal\Config;
use ItPortal\Db\OracleConnection;

/**
 * Pallet/cont search — legacy SELECT_PALLET_CONT_AIR SQL when Oracle is configured.
 */
final class PalletSearchService
{
    public function __construct(
        private readonly TraceNormalizer $normalizer,
        private readonly ?OraclePalletRepository $oracle = null,
    ) {
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
        if ($driver === 'oracle' || ($driver !== 'mock' && OracleConnection::available())) {
            try {
                $repo = $this->oracle ?? new OraclePalletRepository();
                return $repo->search($pallet, $server);
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Oracle pallet search failed: ' . $e->getMessage(),
                    'pallet' => $pallet,
                    'rows' => [],
                    'meta' => ['server' => $server, 'mode' => 'oracle-error'],
                ];
            }
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
            'message' => 'Demo pallet (mock). Set AUTH_DRIVER=oracle to run SELECT_PALLET_CONT_AIR.',
            'pallet' => $pallet,
            'rows' => $rows,
            'meta' => [
                'server' => $server,
                'mode' => 'mock',
                'source' => 'mock (legacy SQL ready in Tracking\\Sql\\LegacySql)',
            ],
        ];
    }
}
