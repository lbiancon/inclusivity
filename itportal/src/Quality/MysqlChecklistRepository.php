<?php

declare(strict_types=1);

namespace ItPortal\Quality;

use ItPortal\Db\MysqlConnection;
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
}
