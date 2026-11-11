<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class PrenotazioniAzienda
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function delGiorno(int $aziendaId, string $data): array
    {
        $data = $this->data($data);
        $stmt = $this->pdo->prepare(
            'SELECT o.id, u.matricola, u.nome, o.ospite, o.ospite_nome, o.a_carico, o.totale,
                    r.nome AS piatto, r.categoria
             FROM ordini o
             INNER JOIN utenti u
                ON u.id = o.utente_id
               AND u.azienda_id = o.azienda_id
             LEFT JOIN ordine_righe r
                ON r.ordine_id = o.id
               AND r.azienda_id = o.azienda_id
             WHERE o.azienda_id = :azienda
               AND o.data_pasto = :data
             ORDER BY u.matricola, o.ospite, r.id'
        );
        $stmt->execute(['azienda' => $aziendaId, 'data' => $data]);

        $ordini = [];
        foreach ($stmt as $riga) {
            $id = (int) $riga['id'];
            if (!isset($ordini[$id])) {
                $ordini[$id] = [
                    'id' => $id,
                    'matricola' => (string) $riga['matricola'],
                    'nome' => (string) $riga['nome'],
                    'ospite' => (int) $riga['ospite'] === 1,
                    'ospite_nome' => $riga['ospite_nome'] !== null ? (string) $riga['ospite_nome'] : null,
                    'a_carico' => (string) $riga['a_carico'],
                    'totale' => (string) $riga['totale'],
                    'piatti' => [],
                ];
            }
            if ($riga['piatto'] !== null) {
                $ordini[$id]['piatti'][] = [
                    'nome' => (string) $riga['piatto'],
                    'categoria' => (string) $riga['categoria'],
                ];
            }
        }

        return array_values($ordini);
    }

    private function data(string $data): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'Indica il giorno nel formato AAAA-MM-GG.');
        }
        $parsata = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($parsata === false || $parsata->format('Y-m-d') !== $data) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'Il giorno richiesto non è valido.');
        }

        return $data;
    }
}
