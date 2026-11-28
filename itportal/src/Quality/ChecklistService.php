<?php

declare(strict_types=1);

namespace ItPortal\Quality;

use ItPortal\Config;
use ItPortal\Db\MysqlConnection;

/**
 * Quality checklist data layer (legacy get_check_* / save_check / insert_login_check).
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
                return [
                    'ok' => true,
                    'message' => 'lot_check_config lines (legacy get_check_list_config).',
                    'lines' => $repo->listLines(),
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
            'message' => 'Demo checklist lines (mock). Set MYSQL_CHECK_* for live checkdb.',
            'lines' => [
                ['line' => 'AB1', 'rev' => '3'],
                ['line' => 'IF2', 'rev' => '2'],
                ['line' => 'LCS||C7799001||3114219-AA', 'rev' => '1'],
            ],
            'meta' => ['mode' => 'mock', 'source' => 'mock (SQL ready in ChecklistSql)'],
        ];
    }

    /**
     * @return array{ok:bool,message:string,items:list<array<string,mixed>>,meta:array<string,string>}
     */
    public function config(string $line, string $checkPart = 'csl', string $rev = '1'): array
    {
        $line = trim($line);
        if ($line === '') {
            return ['ok' => false, 'message' => 'Line is required.', 'items' => [], 'meta' => []];
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

    /**
     * @return array{ok:bool,message:string,operator?:array<string,string>,meta:array<string,string>}
     */
    public function loginOperator(string $line, string $badge, string $checkname = 'csl', string $rev = '1'): array
    {
        $line = trim($line);
        $badge = trim($badge);
        if ($line === '' || $badge === '') {
            return ['ok' => false, 'message' => 'Line and badge are required.', 'meta' => []];
        }

        if ($this->useMysql()) {
            try {
                $repo = $this->mysql ?? new MysqlChecklistRepository();
                $result = $repo->loginOperator($line, $badge, $checkname, $rev);
                return [
                    'ok' => $result['ok'],
                    'message' => $result['message'],
                    'operator' => $result['employee'] ?? null,
                    'meta' => ['mode' => 'mysql', 'source' => 'function.php insert_login_check'],
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Login operator failed: ' . $e->getMessage(),
                    'meta' => ['mode' => 'mysql-error'],
                ];
            }
        }

        // Mock: accept any non-empty badge, keep in session
        $_SESSION['checklist_ops'][$line] = [
            'user_id' => $badge,
            'empl_last' => 'Mock',
            'empl_first' => 'Operator',
            'roles' => 'QI',
            'checkname' => $checkname,
            'rev' => $rev,
            'at' => date('c'),
        ];

        return [
            'ok' => true,
            'message' => 'Mock operator logged on line (legacy insert_login_check flow).',
            'operator' => [
                'empl_last' => 'Mock',
                'empl_first' => 'Operator',
                'roles' => 'QI',
                'superuser' => 'N',
            ],
            'meta' => ['mode' => 'mock', 'line' => $line, 'badge' => $badge],
        ];
    }

    /**
     * @return array{ok:bool,message:string,meta:array<string,string>}
     */
    public function logoutOperators(string $line, string $checkname = 'csl'): array
    {
        $line = trim($line);
        if ($line === '') {
            return ['ok' => false, 'message' => 'Line is required.', 'meta' => []];
        }

        if ($this->useMysql()) {
            try {
                ($this->mysql ?? new MysqlChecklistRepository())->logoutOperators($line, $checkname);
                return [
                    'ok' => true,
                    'message' => 'Operators logged out on line.',
                    'meta' => ['mode' => 'mysql', 'source' => 'function.php logout'],
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Logout failed: ' . $e->getMessage(),
                    'meta' => ['mode' => 'mysql-error'],
                ];
            }
        }

        unset($_SESSION['checklist_ops'][$line]);
        return [
            'ok' => true,
            'message' => 'Mock operators cleared for line.',
            'meta' => ['mode' => 'mock', 'line' => $line],
        ];
    }

    /**
     * @return array{ok:bool,message:string,meta:array<string,string>}
     */
    public function saveAnswer(
        string $line,
        string $idQuestion,
        string $checkValue,
        string $question,
        string $userId = '',
        string $checkPart = 'csl',
        string $rev = '1',
        string $note = '',
        string $status = 'Y',
    ): array {
        $line = trim($line);
        $idQuestion = trim($idQuestion);
        if ($line === '' || $idQuestion === '') {
            return ['ok' => false, 'message' => 'Line and question id are required.', 'meta' => []];
        }

        if ($userId === '') {
            $userId = (string) ($_SESSION['checklist_ops'][$line]['user_id'] ?? $_SESSION['login'] ?? 'unknown');
        }

        if ($this->useMysql()) {
            try {
                ($this->mysql ?? new MysqlChecklistRepository())->saveAnswer([
                    'user_id' => $userId,
                    'line' => $line,
                    'id_question' => $idQuestion,
                    'check_part' => $checkPart,
                    'check_value' => $checkValue,
                    'status' => $status,
                    'note' => $note,
                    'question' => $question,
                    'rev' => $rev,
                ]);
                return [
                    'ok' => true,
                    'message' => 'Answer saved (legacy save_check).',
                    'meta' => ['mode' => 'mysql', 'source' => 'function.php save_check'],
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Save failed: ' . $e->getMessage(),
                    'meta' => ['mode' => 'mysql-error'],
                ];
            }
        }

        $_SESSION['checklist_answers'][$line][] = [
            'id_question' => $idQuestion,
            'check_value' => $checkValue,
            'question' => $question,
            'user_id' => $userId,
            'at' => date('c'),
        ];

        return [
            'ok' => true,
            'message' => 'Mock answer stored in session (legacy save_check).',
            'meta' => [
                'mode' => 'mock',
                'line' => $line,
                'saved' => (string) count($_SESSION['checklist_answers'][$line] ?? []),
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
