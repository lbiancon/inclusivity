<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class StoricoPrenotazioni
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{data: string, totale: string, piatti: list<array{nome: string, categoria: string, prezzo: string}>}>
     */
    public function perUtente(int $aziendaId, int $utenteId): array
    {
        $ordini = $this->pdo->prepare(
            'SELECT id, data_pasto, totale, ospite, ospite_nome, a_carico, nota_cucina
             FROM ordini
             WHERE azienda_id = :azienda AND utente_id = :utente
             ORDER BY data_pasto DESC
             LIMIT 40'
        );
        $ordini->execute(['azienda' => $aziendaId, 'utente' => $utenteId]);
        $testate = $ordini->fetchAll();
        if ($testate === []) {
            return [];
        }

        $segnaposto = implode(', ', array_fill(0, count($testate), '?'));
        $righe = $this->pdo->prepare(
            'SELECT ordine_id, nome, categoria, prezzo_applicato
             FROM ordine_righe
             WHERE azienda_id = ? AND ordine_id IN (' . $segnaposto . ')
             ORDER BY id'
        );
        $parametri = [$aziendaId];
        foreach ($testate as $testata) {
            $parametri[] = (int) $testata['id'];
        }
        $righe->execute($parametri);

        $perOrdine = [];
        foreach ($righe->fetchAll() as $riga) {
            $perOrdine[(int) $riga['ordine_id']][] = [
                'nome' => (string) $riga['nome'],
                'categoria' => (string) $riga['categoria'],
                'prezzo' => (string) $riga['prezzo_applicato'],
            ];
        }

        $elenco = [];
        foreach ($testate as $testata) {
            $elenco[] = [
                'data' => (string) $testata['data_pasto'],
                'totale' => (string) $testata['totale'],
                'ospite' => (int) $testata['ospite'] === 1,
                'ospite_nome' => $testata['ospite_nome'] !== null ? (string) $testata['ospite_nome'] : null,
                'a_carico' => (string) $testata['a_carico'],
                'nota_cucina' => $testata['nota_cucina'] !== null ? (string) $testata['nota_cucina'] : '',
                'piatti' => $perOrdine[(int) $testata['id']] ?? [],
            ];
        }

        return $elenco;
    }
}
