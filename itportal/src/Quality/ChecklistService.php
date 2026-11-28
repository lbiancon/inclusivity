<?php

declare(strict_types=1);

namespace ItPortal\Quality;

use ItPortal\Config;
use ItPortal\Db\MysqlConnection;

/**
 * Quality checklist data layer (legacy get_check_list_config / get_check_config).
 */
final class ChecklistService
{
    public function __construct(private readonly ?MysqlChecklistRepository $mysql = null)
    {
    }

    /**
     * @return array{ok:bool,message:string,lines:list<array{line:string,rev:string}>,meta:array<string,string>}
     */
    public function listLines(): array
    {
        if ($this->useMysql()) {
            try {
                $repo = $this->mysql ?? new MysqlChecklistRepository();
                $lines = $repo->listLines();
                return [
                    'ok' => true,
                    'message' => 'lot_check_config lines (legacy get_check_list_config).',
                    'lines' => $lines,
                    'meta' => ['mode' => 'mysql', 'source' => 'function.php get_check_list_config'],
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'MySQL checklist failed: ' . $e->getMessage(),
                    'lines' => [],
                    'meta' => ['mode' => 'mysql-error'],
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Demo checklist lines (mock). Set MYSQL_CHECK_* + AUTH/DB for live checkdb.',
            'lines' => [
                ['line' => 'AB1', 'rev' => '3'],
                ['line' => 'IF2', 'rev' => '2'],
                ['line' => 'LCS||C7799001||3114219-AA', 'rev' => '1'],
            ],
            'meta' => [
                'mode' => 'mock',
                'source' => 'mock (SQL ready in Quality\\Sql\\ChecklistSql)',
            ],
        ];
    }

    /**
     * @return array{ok:bool,message:string,items:list<array<string,mixed>>,meta:array<string,string>}
     */
    public function config(string $line, string $checkPart = 'csl', string $rev = '1'): array
    {
        $line = trim($line);
        if ($line === '') {
            return [
                'ok' => false,
                'message' => 'Line is required.',
                'items' => [],
                'meta' => [],
            ];
        }

        if ($this->useMysql()) {
            try {
                $repo = $this->mysql ?? new MysqlChecklistRepository();
                $items = $repo->config($line, $checkPart, $rev);
                return [
                    'ok' => $items !== [],
                    'message' => $items === [] ? 'No active checklist config.' : 'lot_check_config (legacy get_check_config).',
                    'items' => $items,
                    'meta' => [
                        'mode' => 'mysql',
                        'line' => $line,
                        'check_part' => $checkPart,
                        'rev' => $rev,
                        'source' => 'function.php get_check_config',
                    ],
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'MySQL checklist config failed: ' . $e->getMessage(),
                    'items' => [],
                    'meta' => ['mode' => 'mysql-error'],
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Demo checklist questions (mock).',
            'items' => [
                ['check_ctrl' => 'ctrl0', 'question' => 'PPE indossati?', 'check_type' => 'YN', 'status' => 'Y', 'rev' => $rev],
                ['check_ctrl' => 'ctrl1', 'question' => 'Area pulita?', 'check_type' => 'YN', 'status' => 'Y', 'rev' => $rev],
                ['check_ctrl' => 'ctrl2', 'question' => 'Parametri macchina OK?', 'check_type' => 'YN', 'status' => 'Y', 'rev' => $rev],
            ],
            'meta' => [
                'mode' => 'mock',
                'line' => $line,
                'check_part' => $checkPart,
                'rev' => $rev,
                'source' => 'mock (SQL ready in ChecklistSql::CONFIG_ACTIVE)',
            ],
        ];
    }

    private function useMysql(): bool
    {
        $driver = (string) Config::get('app.auth_driver', 'mock');
        if ($driver === 'mock') {
            return false;
        }
        return MysqlConnection::available('check');
    }
}
