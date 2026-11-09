<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use MenuCraft\Enums\PagamentoType;
use MenuCraft\Models\AziendaConfig;
use MenuCraft\Support\Money;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class OrderService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MenuValidator $validator,
    ) {
    }

    /**
     * @param list<mixed> $piattoIds
     * @return array<string, mixed>
     */
    public function invia(int $utenteId, int $aziendaId, string $data, array $piattoIds, ?DateTimeImmutable $adesso = null): array
    {
        $dataPasto = $this->dataPasto($data);
        $now = $this->validator->adesso($adesso);
        $azienda = AziendaConfig::trova($this->pdo, $aziendaId);

        $this->pdo->beginTransaction();
        try {
            $utente = $this->bloccaUtente($utenteId, $aziendaId);
            if ($utente['ruolo'] !== 'dipendente') {
                throw new MenuRuleException('RUOLO', 'Solo i dipendenti possono prenotare il pasto.', 403);
            }

            $this->validator->validaCalendario($azienda->id, $dataPasto);
            $this->validator->validaCutoff($azienda, $dataPasto, $now);

            $ordine = $this->bloccaOrdine($azienda->id, $utenteId, $dataPasto);
            $ids = $this->validator->normalizzaIdPiatti($piattoIds, $azienda->maxScelte);

            if ($ids === []) {
                $risultato = $this->annulla($azienda, $utenteId, $dataPasto, $ordine);
            } else {
                $this->validator->assertMenuVisibile($azienda->id, $dataPasto, $now);
                $piatti = $this->validator->piattiPrenotabili($azienda, $dataPasto, $ids, $now);
                $categorie = [];
                foreach ($piatti as $piatto) {
                    $categorie[] = $piatto['categoria'];
                }
                $this->validator->validaComposizione($azienda, $categorie);
                $risultato = $this->salva($azienda, $utenteId, $dataPasto, $now, $piatti, $ordine);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $risultato['saldo'] = $this->saldo($azienda, $utenteId);

        return $risultato;
    }

    public function saldo(AziendaConfig $azienda, int $utenteId): ?string
    {
        if ($azienda->tipoPagamento !== PagamentoType::Dipendente) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT saldo_centesimi FROM portafogli WHERE azienda_id = :azienda AND utente_id = :utente'
        );
        $stmt->execute(['azienda' => $azienda->id, 'utente' => $utenteId]);
        $valore = $stmt->fetchColumn();
        if ($valore === false) {
            return '0.00';
        }

        return Money::formatta((int) $valore);
    }

    private function dataPasto(string $data): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'Indica la data del pasto nel formato AAAA-MM-GG.');
        }

        $parsata = DateTimeImmutable::createFromFormat('!Y-m-d', $data, $this->validator->fuso());
        $errori = DateTimeImmutable::getLastErrors();
        if (
            $parsata === false
            || ($errori !== false && ($errori['warning_count'] > 0 || $errori['error_count'] > 0))
            || $parsata->format('Y-m-d') !== $data
        ) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'La data del pasto non è valida.');
        }

        return $parsata;
    }

    /** @return array{ruolo: string} */
    private function bloccaUtente(int $utenteId, int $aziendaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ruolo FROM utenti WHERE id = :id AND azienda_id = :azienda FOR UPDATE'
        );
        $stmt->execute(['id' => $utenteId, 'azienda' => $aziendaId]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            throw new MenuRuleException('NON_AUTENTICATO', 'Accesso richiesto.', 401);
        }

        return ['ruolo' => (string) $riga['ruolo']];
    }

    /** @return array{id: int, totale: string}|null */
    private function bloccaOrdine(int $aziendaId, int $utenteId, DateTimeImmutable $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, totale
             FROM ordini
             WHERE azienda_id = :azienda AND utente_id = :utente AND data_pasto = :data
             FOR UPDATE'
        );
        $stmt->execute([
            'azienda' => $aziendaId,
            'utente' => $utenteId,
            'data' => $data->format('Y-m-d'),
        ]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            return null;
        }

        return [
            'id' => (int) $riga['id'],
            'totale' => Money::normalizza((string) $riga['totale']),
        ];
    }

    /**
     * @param array{id: int, totale: string}|null $ordine
     * @return array<string, mixed>
     */
    private function annulla(AziendaConfig $azienda, int $utenteId, DateTimeImmutable $data, ?array $ordine): array
    {
        if ($ordine === null) {
            throw new MenuRuleException('SELEZIONE_VUOTA', 'Seleziona almeno un piatto per il buono pasto.');
        }

        $this->muoviWallet($azienda, $utenteId, -Money::euroToCentesimi($ordine['totale']));

        $righe = $this->pdo->prepare('DELETE FROM ordine_righe WHERE ordine_id = :ordine AND azienda_id = :azienda');
        $righe->execute(['ordine' => $ordine['id'], 'azienda' => $azienda->id]);
        $testata = $this->pdo->prepare(
            'DELETE FROM ordini WHERE id = :id AND azienda_id = :azienda AND utente_id = :utente'
        );
        $testata->execute(['id' => $ordine['id'], 'azienda' => $azienda->id, 'utente' => $utenteId]);

        return [
            'annullato' => true,
            'messaggio' => 'Prenotazione annullata.',
            'ordine' => null,
        ];
    }

    /**
     * @param list<array{id: int, codice: string, nome: string, categoria: \MenuCraft\Enums\PortataType, prezzo: string}> $piatti
     * @param array{id: int, totale: string}|null $ordine
     * @return array<string, mixed>
     */
    private function salva(
        AziendaConfig $azienda,
        int $utenteId,
        DateTimeImmutable $data,
        DateTimeImmutable $now,
        array $piatti,
        ?array $ordine,
    ): array {
        $nuovo = 0;
        $voci = [];
        foreach ($piatti as $piatto) {
            $prezzo = $azienda->prezzoApplicato($piatto['prezzo']);
            $nuovo += Money::euroToCentesimi($prezzo);
            $voci[] = [
                'id' => $piatto['id'],
                'codice' => $piatto['codice'],
                'nome' => $piatto['nome'],
                'categoria' => $piatto['categoria']->value,
                'prezzo' => $prezzo,
            ];
        }

        $precedente = $ordine === null ? 0 : Money::euroToCentesimi($ordine['totale']);
        $this->muoviWallet($azienda, $utenteId, $nuovo - $precedente);

        $totale = Money::formatta($nuovo);
        $istante = $now->format('Y-m-d H:i:s');
        if ($ordine === null) {
            $insert = $this->pdo->prepare(
                'INSERT INTO ordini (azienda_id, utente_id, data_pasto, totale, creato_il, aggiornato_il)
                 VALUES (:azienda, :utente, :data, :totale, :creato, :aggiornato)'
            );
            $insert->execute([
                'azienda' => $azienda->id,
                'utente' => $utenteId,
                'data' => $data->format('Y-m-d'),
                'totale' => $totale,
                'creato' => $istante,
                'aggiornato' => $istante,
            ]);
            $ordineId = (int) $this->pdo->lastInsertId();
        } else {
            $update = $this->pdo->prepare(
                'UPDATE ordini
                 SET totale = :totale, aggiornato_il = :aggiornato
                 WHERE id = :id AND azienda_id = :azienda AND utente_id = :utente'
            );
            $update->execute([
                'totale' => $totale,
                'aggiornato' => $istante,
                'id' => $ordine['id'],
                'azienda' => $azienda->id,
                'utente' => $utenteId,
            ]);
            $cancella = $this->pdo->prepare(
                'DELETE FROM ordine_righe WHERE ordine_id = :ordine AND azienda_id = :azienda'
            );
            $cancella->execute(['ordine' => $ordine['id'], 'azienda' => $azienda->id]);
            $ordineId = $ordine['id'];
        }

        $riga = $this->pdo->prepare(
            'INSERT INTO ordine_righe (ordine_id, azienda_id, piatto_id, nome, categoria, prezzo_applicato)
             VALUES (:ordine, :azienda, :piatto, :nome, :categoria, :prezzo)'
        );
        foreach ($voci as $voce) {
            $riga->execute([
                'ordine' => $ordineId,
                'azienda' => $azienda->id,
                'piatto' => $voce['id'],
                'nome' => $voce['nome'],
                'categoria' => $voce['categoria'],
                'prezzo' => $voce['prezzo'],
            ]);
        }

        return [
            'annullato' => false,
            'messaggio' => $ordine === null ? 'Prenotazione confermata.' : 'Prenotazione aggiornata.',
            'ordine' => [
                'id' => $ordineId,
                'data' => $data->format('Y-m-d'),
                'totale' => $totale,
                'piatti' => $voci,
            ],
        ];
    }

    private function muoviWallet(AziendaConfig $azienda, int $utenteId, int $deltaCentesimi): void
    {
        if ($azienda->tipoPagamento !== PagamentoType::Dipendente || $deltaCentesimi === 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, saldo_centesimi
             FROM portafogli
             WHERE azienda_id = :azienda AND utente_id = :utente
             FOR UPDATE'
        );
        $stmt->execute(['azienda' => $azienda->id, 'utente' => $utenteId]);
        $riga = $stmt->fetch();
        $saldo = $riga === false ? 0 : (int) $riga['saldo_centesimi'];
        $nuovo = $saldo - $deltaCentesimi;
        if ($nuovo < 0) {
            throw new MenuRuleException(
                'SALDO_INSUFFICIENTE',
                sprintf(
                    'Saldo insufficiente: servono %s € e il wallet ha %s €.',
                    str_replace('.', ',', Money::formatta($deltaCentesimi)),
                    str_replace('.', ',', Money::formatta($saldo)),
                ),
            );
        }

        if ($riga === false) {
            $insert = $this->pdo->prepare(
                'INSERT INTO portafogli (azienda_id, utente_id, saldo_centesimi) VALUES (:azienda, :utente, :saldo)'
            );
            $insert->execute([
                'azienda' => $azienda->id,
                'utente' => $utenteId,
                'saldo' => $nuovo,
            ]);
            return;
        }

        $update = $this->pdo->prepare(
            'UPDATE portafogli
             SET saldo_centesimi = :saldo
             WHERE id = :id AND azienda_id = :azienda AND utente_id = :utente'
        );
        $update->execute([
            'saldo' => $nuovo,
            'id' => (int) $riga['id'],
            'azienda' => $azienda->id,
            'utente' => $utenteId,
        ]);
    }
}
