<?php

declare(strict_types=1);

namespace ItPortal\Quality;

use ItPortal\Db\MysqlConnection;
use ItPortal\Db\OracleConnection;
use ItPortal\Db\OracleQuery;
use ItPortal\Quality\Sql\ChecklistSql;

final class MysqlChecklistRepository
{
    /**
     * @return list<array{line:string,rev:string}>
     */
    public function listLines(): array
    {
        $pdo = MysqlConnection::connect('check');
        $stmt = $pdo->query(ChecklistSql::LIST_LINES);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'line' => (string) ($row['LINE'] ?? ''),
                'rev' => (string) ($row['REV'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function config(string $line, string $checkPart, string $rev = '1'): array
    {
        if ($rev === '') {
            $rev = '1';
        }
        $pdo = MysqlConnection::connect('check');
        $stmt = $pdo->prepare(ChecklistSql::CONFIG_ACTIVE);
        $stmt->execute([
            ':line' => $line,
            ':check_part' => $checkPart,
            ':rev' => $rev,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * @return array{user_id:string,name:string,roles:string,time_stamp:string,checkname:string,id:string}|null
     */
    public function activeLogin(string $line, string $checkname, string $date1, string $date2): ?array
    {
        $pdo = MysqlConnection::connect('check');
        $stmt = $pdo->prepare(ChecklistSql::LOGIN_CHECK);
        $stmt->execute([
            ':line' => $line,
            ':checkname' => $checkname,
            ':user_id' => '%',
            ':date1' => $date1,
            ':date2' => $date2,
        ]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'user_id' => (string) ($row['user_id'] ?? ''),
            'name' => trim((string) ($row['empl_last'] ?? '') . ' ' . (string) ($row['empl_first'] ?? '')),
            'roles' => (string) ($row['roles'] ?? ''),
            'time_stamp' => (string) ($row['time_stamp'] ?? ''),
            'checkname' => (string) ($row['checkname'] ?? ''),
            'id' => (string) ($row['id'] ?? ''),
        ];
    }

    /**
     * Legacy insert_login_check: resolve badge on Oracle, then write MySQL lot_user_check.
     *
     * @return array{ok:bool,message:string,employee?:array<string,string>}
     */
    public function loginOperator(string $line, string $badge, string $checkname, string $rev): array
    {
        $employee = $this->resolveEmployee($badge);
        if ($employee === null) {
            return ['ok' => false, 'message' => 'Badge not found in lot_employee.'];
        }

        $pdo = MysqlConnection::connect('check');
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(ChecklistSql::DEACTIVATE_LINE_LOGINS);
            $upd->execute([':line' => $line, ':checkname' => $checkname]);

            $ins = $pdo->prepare(ChecklistSql::INSERT_LOGIN);
            $ins->execute([
                ':user_id' => $badge,
                ':empl_last' => $employee['empl_last'],
                ':empl_first' => $employee['empl_first'],
                ':roles' => $employee['roles'],
                ':checkname' => $checkname,
                ':line' => $line,
                ':rev' => $rev,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['ok' => true, 'message' => 'Operator logged on checklist line.', 'employee' => $employee];
    }

    public function logoutOperators(string $line, string $checkname): void
    {
        $pdo = MysqlConnection::connect('check');
        $stmt = $pdo->prepare(ChecklistSql::LOGOUT_LINE);
        $stmt->execute([':line' => $line, ':checkname' => $checkname]);
    }

    /**
     * @param array{user_id:string,line:string,id_question:string,check_part:string,check_value:string,status:string,note:string,question:string,rev:string} $payload
     */
    public function saveAnswer(array $payload): void
    {
        $pdo = MysqlConnection::connect('check');
        $stmt = $pdo->prepare(ChecklistSql::SAVE_CHECK);
        $stmt->execute([
            ':user_id' => $payload['user_id'],
            ':line' => $payload['line'],
            ':id_question' => $payload['id_question'],
            ':check_part' => $payload['check_part'],
            ':check_value' => $payload['check_value'],
            ':status' => $payload['status'],
            ':note' => $payload['note'],
            ':question' => $payload['question'],
            ':rev' => $payload['rev'],
        ]);
    }

    /**
     * @return array{empl_last:string,empl_first:string,roles:string,superuser:string}|null
     */
    private function resolveEmployee(string $badge): ?array
    {
        if (!OracleConnection::available()) {
            // Allow plant MySQL-only demos only if badge matches session later; here require Oracle like legacy.
            return null;
        }
        $conn = OracleConnection::connect();
        $rows = OracleQuery::fetchAll($conn, ChecklistSql::EMPLOYEE_BY_BADGE, [':user_id' => $badge]);
        if ($rows === []) {
            return null;
        }
        $row = $rows[0];
        return [
            'empl_last' => (string) ($row['EMPL_LAST'] ?? ''),
            'empl_first' => (string) ($row['EMPL_FIRST'] ?? ''),
            'roles' => (string) ($row['ROLES'] ?? ''),
            'superuser' => (string) ($row['SUPERUSER'] ?? ''),
        ];
    }
}
