<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use MenuCraft\Enums\PortataType;
use MenuCraft\Support\CodicePiatto;
use MenuCraft\Support\LetteraPasto;
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
        (new LetteraPasto())->assegna($this->pdo, $aziendaId, $data);
        $porzioni = $this->pdo->prepare(
            'SELECT p.codice, r.categoria, r.nome, v.lettera, COUNT(*) AS porzioni
             FROM ordine_righe r
             INNER JOIN ordini o
                ON o.id = r.ordine_id
               AND o.azienda_id = r.azienda_id
             INNER JOIN piatti p
                ON p.id = r.piatto_id
               AND p.azienda_id = r.azienda_id
             LEFT JOIN menu_voci v
                ON v.azienda_id = o.azienda_id
               AND v.data_pasto = o.data_pasto
               AND v.piatto_id = r.piatto_id
             WHERE o.azienda_id = :azienda
               AND o.data_pasto = :data
             GROUP BY p.codice, r.categoria, r.nome, v.lettera'
        );
        $porzioni->execute(['azienda' => $aziendaId, 'data' => $data]);

        $righe = $this->pdo->prepare(
            'SELECT u.matricola, u.nome AS dipendente, o.id AS ordine_id, o.ospite, o.ospite_nome, o.a_carico, o.creato_il,
                    COALESCE(NULLIF(o.nota_cucina, \'\'), u.allergie) AS nota_cucina, p.codice, r.categoria, r.nome, v.lettera
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
             LEFT JOIN menu_voci v
                ON v.azienda_id = o.azienda_id
               AND v.data_pasto = o.data_pasto
               AND v.piatto_id = r.piatto_id
             WHERE o.azienda_id = :azienda
               AND o.data_pasto = :data
             ORDER BY u.matricola, o.id, r.id'
        );
        $righe->execute(['azienda' => $aziendaId, 'data' => $data]);

        $buoni = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ordini WHERE azienda_id = :azienda AND data_pasto = :data'
        );
        $buoni->execute(['azienda' => $aziendaId, 'data' => $data]);

        $elenco = [];
        foreach ($porzioni->fetchAll() as $riga) {
            $categoria = (string) $riga['categoria'];
            $elenco[] = [
                'codice' => CodicePiatto::etichetta((string) $riga['codice']),
                'lettera' => $riga['lettera'] !== null ? (string) $riga['lettera'] : '',
                'fascia' => PortataType::tryFrom($categoria)?->fascia() ?? 'gialla',
                'categoria' => $categoria,
                'nome' => (string) $riga['nome'],
                'porzioni' => (int) $riga['porzioni'],
            ];
        }
        $alfabeto = array_flip(LetteraPasto::ALFABETO);
        usort($elenco, static function (array $a, array $b) use ($alfabeto): int {
            $posA = $alfabeto[$a['lettera']] ?? 99;
            $posB = $alfabeto[$b['lettera']] ?? 99;

            return [$posA, $a['nome']] <=> [$posB, $b['nome']];
        });

        $dettaglio = [];
        foreach ($righe->fetchAll() as $riga) {
            $dettaglio[] = [
                'matricola' => (string) $riga['matricola'],
                'dipendente' => (string) $riga['dipendente'],
                'ospite' => (int) $riga['ospite'] === 1 ? (string) ($riga['ospite_nome'] ?? '') : '',
                'a_carico' => (string) $riga['a_carico'],
                'nota_cucina' => $riga['nota_cucina'] !== null ? (string) $riga['nota_cucina'] : '',
                'ordine_id' => (int) $riga['ordine_id'],
                'creato_il' => (string) $riga['creato_il'],
                'codice' => CodicePiatto::etichetta((string) $riga['codice']),
                'lettera' => $riga['lettera'] !== null ? (string) $riga['lettera'] : '',
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
        return (new ExportCucina($this->pdo))->csv($aziendaId, $nomeAzienda, $data);
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
}
