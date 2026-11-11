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
    public function invia(
        int $utenteId,
        int $aziendaId,
        string $data,
        array $piattoIds,
        ?DateTimeImmutable $adesso = null,
        bool $ospite = false,
        string $ospiteNome = '',
        string $aCarico = '',
    ): array {
        $dataPasto = $this->dataPasto($data);
        $now = $this->validator->adesso($adesso);
        $azienda = AziendaConfig::trova($this->pdo, $aziendaId);
        $mostraSaldo = false;

        $this->pdo->beginTransaction();
        try {
            $utente = $this->bloccaUtente($utenteId, $aziendaId);
            if ($utente['ruolo'] !== 'dipendente') {
                throw new MenuRuleException('RUOLO', 'Solo i dipendenti possono prenotare il pasto.', 403);
            }

            $this->validator->validaCalendario($azienda->id, $dataPasto);
            $this->validator->validaCutoff($azienda, $dataPasto, $now);

            $ordine = $this->bloccaOrdine($azienda->id, $utenteId, $dataPasto, $ospite);
            $ids = $this->validator->normalizzaIdPiatti($piattoIds, $azienda->maxScelte);
            if ($ospite && $ids !== []) {
                $ospiteNome = $this->nomeOspite($ospiteNome);
                if (!in_array($aCarico, ['azienda', 'dipendente'], true)) {
                    throw new MenuRuleException('OSPITE_NON_VALIDO', 'Indica se l\'ospite è a carico dell\'azienda o del dipendente.');
                }
            }
            $pagaDiTasca = $this->pagamentoEffettivo($azienda, $utente['pagamento']) === 'dipendente';
            $aCaricoSalvato = $ospite
                ? $aCarico
                : ($pagaDiTasca ? 'dipendente' : 'azienda');
            $mostraSaldo = $pagaDiTasca || $aCaricoSalvato === 'dipendente';

            if ($ids === []) {
                $risultato = $this->annulla($azienda, $utenteId, $ordine, $ospite);
            } else {
                $this->validator->assertMenuVisibile($azienda->id, $dataPasto, $now);
                $piatti = $this->validator->piattiPrenotabili($azienda, $dataPasto, $ids, $now);
                $categorie = [];
                foreach ($piatti as $piatto) {
                    $categorie[] = $piatto['categoria'];
                }
                $this->validator->validaComposizione($azienda, $categorie);
                $risultato = $this->salva(
                    $azienda,
                    $utenteId,
                    $dataPasto,
                    $now,
                    $piatti,
                    $ordine,
                    $ospite,
                    $ospite ? $ospiteNome : null,
                    $aCaricoSalvato,
                );
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $risultato['saldo'] = $mostraSaldo ? $this->saldo($azienda, $utenteId, true) : null;

        return $risultato;
    }

    public function saldo(AziendaConfig $azienda, int $utenteId, bool $forza = false): ?string
    {
        if (!$forza && $azienda->tipoPagamento !== PagamentoType::Dipendente) {
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

    /** @return array{ruolo: string, pagamento: string} */
    private function bloccaUtente(int $utenteId, int $aziendaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ruolo, pagamento FROM utenti WHERE id = :id AND azienda_id = :azienda FOR UPDATE'
        );
        $stmt->execute(['id' => $utenteId, 'azienda' => $aziendaId]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            throw new MenuRuleException('NON_AUTENTICATO', 'Accesso richiesto.', 401);
        }

        return [
            'ruolo' => (string) $riga['ruolo'],
            'pagamento' => (string) ($riga['pagamento'] ?? 'eredita'),
        ];
    }

    /** @return array{id: int, totale: string, a_carico: string}|null */
    private function bloccaOrdine(int $aziendaId, int $utenteId, DateTimeImmutable $data, bool $ospite): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, totale, a_carico
             FROM ordini
             WHERE azienda_id = :azienda AND utente_id = :utente AND data_pasto = :data AND ospite = :ospite
             FOR UPDATE'
        );
        $stmt->execute([
            'azienda' => $aziendaId,
            'utente' => $utenteId,
            'data' => $data->format('Y-m-d'),
            'ospite' => $ospite ? 1 : 0,
        ]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            return null;
        }

        return [
            'id' => (int) $riga['id'],
            'totale' => Money::normalizza((string) $riga['totale']),
            'a_carico' => (string) $riga['a_carico'],
        ];
    }

    private function pagamentoEffettivo(AziendaConfig $azienda, string $pagamento): string
    {
        return match ($pagamento) {
            'welfare' => 'welfare',
            'pagante' => 'dipendente',
            default => $azienda->tipoPagamento->value,
        };
    }

    private function nomeOspite(string $nome): string
    {
        $nome = preg_replace('/\s+/u', ' ', trim($nome)) ?? '';
        $nome = preg_replace('/[^\p{L}\p{N}\p{P}\p{Zs}]/u', '', $nome) ?? '';
        $nome = trim($nome);
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) {
            throw new MenuRuleException('OSPITE_NON_VALIDO', 'Indica il nome dell\'ospite.');
        }

        return $nome;
    }

    /**
     * @param array{id: int, totale: string}|null $ordine
     * @return array<string, mixed>
     */
    private function annulla(AziendaConfig $azienda, int $utenteId, ?array $ordine, bool $ospite): array
    {
        if ($ordine === null) {
            throw new MenuRuleException('SELEZIONE_VUOTA', 'Seleziona almeno un piatto per il buono pasto.');
        }

        $giaAddebitato = $ordine['a_carico'] === 'dipendente' ? Money::euroToCentesimi($ordine['totale']) : 0;
        $this->muoviWallet($azienda->id, $utenteId, -$giaAddebitato);

        $righe = $this->pdo->prepare('DELETE FROM ordine_righe WHERE ordine_id = :ordine AND azienda_id = :azienda');
        $righe->execute(['ordine' => $ordine['id'], 'azienda' => $azienda->id]);
        $testata = $this->pdo->prepare(
            'DELETE FROM ordini WHERE id = :id AND azienda_id = :azienda AND utente_id = :utente'
        );
        $testata->execute(['id' => $ordine['id'], 'azienda' => $azienda->id, 'utente' => $utenteId]);

        return [
            'annullato' => true,
            'messaggio' => $ospite ? 'Ospite annullato.' : 'Prenotazione annullata.',
            'ordine' => null,
            'ospite' => $ospite,
        ];
    }

    /**
     * @param list<array{id: int, codice: string, nome: string, categoria: \MenuCraft\Enums\PortataType, prezzo: string}> $piatti
     * @param array{id: int, totale: string, a_carico: string}|null $ordine
     * @return array<string, mixed>
     */
    private function salva(
        AziendaConfig $azienda,
        int $utenteId,
        DateTimeImmutable $data,
        DateTimeImmutable $now,
        array $piatti,
        ?array $ordine,
        bool $ospite,
        ?string $ospiteNome,
        string $aCarico,
    ): array {
        $addebita = $aCarico === 'dipendente';
        $nuovo = 0;
        $voci = [];
        foreach ($piatti as $piatto) {
            $prezzo = $addebita ? Money::normalizza($piatto['prezzo']) : '0.00';
            $nuovo += Money::euroToCentesimi($prezzo);
            $voci[] = [
                'id' => $piatto['id'],
                'codice' => $piatto['codice'],
                'nome' => $piatto['nome'],
                'categoria' => $piatto['categoria']->value,
                'prezzo' => $prezzo,
            ];
        }

        $precedente = ($ordine !== null && $ordine['a_carico'] === 'dipendente')
            ? Money::euroToCentesimi($ordine['totale'])
            : 0;
        $this->muoviWallet($azienda->id, $utenteId, ($addebita ? $nuovo : 0) - $precedente);

        $totale = Money::formatta($nuovo);
        $istante = $now->format('Y-m-d H:i:s');
        if ($ordine === null) {
            $insert = $this->pdo->prepare(
                'INSERT INTO ordini
                    (azienda_id, utente_id, data_pasto, totale, creato_il, aggiornato_il, ospite, ospite_nome, a_carico)
                 VALUES (:azienda, :utente, :data, :totale, :creato, :aggiornato, :ospite, :ospite_nome, :a_carico)'
            );
            $insert->execute([
                'azienda' => $azienda->id,
                'utente' => $utenteId,
                'data' => $data->format('Y-m-d'),
                'totale' => $totale,
                'creato' => $istante,
                'aggiornato' => $istante,
                'ospite' => $ospite ? 1 : 0,
                'ospite_nome' => $ospiteNome,
                'a_carico' => $aCarico,
            ]);
            $ordineId = (int) $this->pdo->lastInsertId();
        } else {
            $update = $this->pdo->prepare(
                'UPDATE ordini
                 SET totale = :totale, aggiornato_il = :aggiornato, ospite_nome = :ospite_nome, a_carico = :a_carico
                 WHERE id = :id AND azienda_id = :azienda AND utente_id = :utente'
            );
            $update->execute([
                'totale' => $totale,
                'aggiornato' => $istante,
                'ospite_nome' => $ospiteNome,
                'a_carico' => $aCarico,
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
            'messaggio' => $ospite
                ? ($ordine === null ? 'Ospite confermato.' : 'Ospite aggiornato.')
                : ($ordine === null ? 'Prenotazione confermata.' : 'Prenotazione aggiornata.'),
            'ospite' => $ospite,
            'ordine' => [
                'id' => $ordineId,
                'data' => $data->format('Y-m-d'),
                'totale' => $totale,
                'a_carico' => $aCarico,
                'ospite_nome' => $ospiteNome,
                'piatti' => $voci,
            ],
        ];
    }

    private function muoviWallet(int $aziendaId, int $utenteId, int $deltaCentesimi): void
    {
        if ($deltaCentesimi === 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, saldo_centesimi
             FROM portafogli
             WHERE azienda_id = :azienda AND utente_id = :utente
             FOR UPDATE'
        );
        $stmt->execute(['azienda' => $aziendaId, 'utente' => $utenteId]);
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
                'azienda' => $aziendaId,
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
            'azienda' => $aziendaId,
            'utente' => $utenteId,
        ]);
    }
}
