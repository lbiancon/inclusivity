<?php

declare(strict_types=1);

namespace MenuCraft\Support;

use MenuCraft\Enums\PortataType;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class LetteraPasto
{
    /** @var list<string> */
    public const ALFABETO = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'Z'];

    public function assegna(PDO $pdo, int $aziendaId, string $data): void
    {
        $this->ricalcola($pdo, $aziendaId, $data);
    }

    /** Riassegna sempre A, B, C… in ordine di portata, così togliere un primo non lascia buchi né Q. */
    public function ricalcola(PDO $pdo, int $aziendaId, string $data): void
    {
        $stmt = $pdo->prepare(
            'SELECT v.id, p.categoria, p.nome
             FROM menu_voci v
             INNER JOIN piatti p
                ON p.id = v.piatto_id
               AND p.azienda_id = v.azienda_id
             WHERE v.azienda_id = :azienda
               AND v.data_pasto = :data
             ORDER BY v.id'
        );
        $stmt->execute(['azienda' => $aziendaId, 'data' => $data]);
        $righe = $stmt->fetchAll();
        if ($righe === []) {
            return;
        }

        usort($righe, static function (array $a, array $b): int {
            $ordineA = PortataType::tryFrom((string) $a['categoria'])?->ordine() ?? 9;
            $ordineB = PortataType::tryFrom((string) $b['categoria'])?->ordine() ?? 9;
            if ($ordineA !== $ordineB) {
                return $ordineA <=> $ordineB;
            }
            $nome = strcmp((string) $a['nome'], (string) $b['nome']);
            if ($nome !== 0) {
                return $nome;
            }

            return ((int) $a['id']) <=> ((int) $b['id']);
        });

        $update = $pdo->prepare(
            'UPDATE menu_voci SET lettera = :lettera WHERE id = :id AND azienda_id = :azienda'
        );
        foreach ($righe as $indice => $riga) {
            $lettera = self::ALFABETO[$indice] ?? null;
            if ($lettera === null) {
                break;
            }
            $update->execute([
                'lettera' => $lettera,
                'id' => (int) $riga['id'],
                'azienda' => $aziendaId,
            ]);
        }
    }

    public static function valida(?string $lettera): ?string
    {
        if ($lettera === null) {
            return null;
        }
        $lettera = strtoupper(trim($lettera));
        if ($lettera === '') {
            return null;
        }
        if (!in_array($lettera, self::ALFABETO, true)) {
            return null;
        }

        return $lettera;
    }
}
