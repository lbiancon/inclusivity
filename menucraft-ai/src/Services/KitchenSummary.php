<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use MenuCraft\Enums\PortataType;
use MenuCraft\Support\CodicePiatto;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class KitchenSummary
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{
     *   data: string,
     *   buoni: int,
     *   porzioni: list<array{codice: string, categoria: string, nome: string, porzioni: int}>,
     *   buoni_righe: list<array{matricola: string, dipendente: string, codice: string, categoria: string, nome: string}>
     * }
     */
    public function perGiorno(int $aziendaId, string $data): array
    {
        $data = $this->data($data);
        $porzioni = $this->pdo->prepare(
            'SELECT p.codice, r.categoria, r.nome, COUNT(*) AS porzioni
             FROM ordine_righe r
             INNER JOIN ordini o
                ON o.id = r.ordine_id
               AND o.azienda_id = r.azienda_id
             INNER JOIN piatti p
                ON p.id = r.piatto_id
               AND p.azienda_id = r.azienda_id
             WHERE o.azienda_id = :azienda
               AND o.data_pasto = :data
             GROUP BY p.codice, r.categoria, r.nome
             ORDER BY r.categoria, p.codice'
        );
        $porzioni->execute(['azienda' => $aziendaId, 'data' => $data]);

        $righe = $this->pdo->prepare(
            'SELECT u.matricola, u.nome AS dipendente, p.codice, r.categoria, r.nome
             FROM ordini o
             INNER JOIN utenti u
                ON u.id = o.utente_id
               AND u.azienda_id = o.azienda_id
             INNER JOIN ordine_righe r
                ON r.ordine_id = o.id
               AND r.azienda_id = o.azienda_id
             INNER JOIN piatti p
                ON p.id = r.piatto_id
               AND p.azienda_id = r.azienda_id
             WHERE o.azienda_id = :azienda
               AND o.data_pasto = :data
             ORDER BY u.matricola, r.id'
        );
        $righe->execute(['azienda' => $aziendaId, 'data' => $data]);

        $buoni = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ordini WHERE azienda_id = :azienda AND data_pasto = :data'
        );
        $buoni->execute(['azienda' => $aziendaId, 'data' => $data]);

        $elenco = [];
        foreach ($porzioni->fetchAll() as $riga) {
            $elenco[] = [
                'codice' => CodicePiatto::etichetta((string) $riga['codice']),
                'categoria' => (string) $riga['categoria'],
                'nome' => (string) $riga['nome'],
                'porzioni' => (int) $riga['porzioni'],
            ];
        }
        usort($elenco, static function (array $a, array $b): int {
            $ordineA = PortataType::tryFrom($a['categoria'])?->ordine() ?? 99;
            $ordineB = PortataType::tryFrom($b['categoria'])?->ordine() ?? 99;

            return [$ordineA, $a['codice']] <=> [$ordineB, $b['codice']];
        });

        $dettaglio = [];
        foreach ($righe->fetchAll() as $riga) {
            $dettaglio[] = [
                'matricola' => (string) $riga['matricola'],
                'dipendente' => (string) $riga['dipendente'],
                'codice' => CodicePiatto::etichetta((string) $riga['codice']),
                'categoria' => (string) $riga['categoria'],
                'nome' => (string) $riga['nome'],
            ];
        }

        return [
            'data' => $data,
            'buoni' => (int) $buoni->fetchColumn(),
            'porzioni' => $elenco,
            'buoni_righe' => $dettaglio,
        ];
    }

    public function csv(int $aziendaId, string $nomeAzienda, string $data): string
    {
        $riepilogo = $this->perGiorno($aziendaId, $data);
        $righe = [
            ['azienda', $nomeAzienda],
            ['data', $riepilogo['data']],
            ['buoni', (string) $riepilogo['buoni']],
            [],
            ['PORZIONI'],
            ['categoria', 'codice', 'piatto', 'porzioni'],
        ];
        foreach ($riepilogo['porzioni'] as $porzione) {
            $righe[] = [$porzione['categoria'], $porzione['codice'], $porzione['nome'], (string) $porzione['porzioni']];
        }
        $righe[] = [];
        $righe[] = ['BUONI'];
        $righe[] = ['matricola', 'dipendente', 'categoria', 'codice', 'piatto'];
        foreach ($riepilogo['buoni_righe'] as $riga) {
            $righe[] = [$riga['matricola'], $riga['dipendente'], $riga['categoria'], $riga['codice'], $riga['nome']];
        }

        $csv = "\xEF\xBB\xBF";
        foreach ($righe as $riga) {
            $csv .= implode(';', array_map($this->cella(...), $riga)) . "\r\n";
        }

        return $csv;
    }

    private function data(string $data): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'Indica la data del riepilogo nel formato AAAA-MM-GG.');
        }
        $parsata = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($parsata === false || $parsata->format('Y-m-d') !== $data) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'La data del riepilogo non è valida.');
        }

        return $data;
    }

    private function cella(string $valore): string
    {
        if (str_contains($valore, ';') || str_contains($valore, '"') || str_contains($valore, "\n")) {
            return '"' . str_replace('"', '""', $valore) . '"';
        }

        return $valore;
    }
}
