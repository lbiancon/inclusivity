<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class CalendarioChiusure
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{proprie: list<array<string, mixed>>, nazionali: list<array<string, mixed>>} */
    public function elenco(int $aziendaId, ?DateTimeImmutable $dal = null, ?DateTimeImmutable $al = null): array
    {
        $fuso = new DateTimeZone(MenuValidator::FUSO);
        $dal = ($dal ?? new DateTimeImmutable('today', $fuso))->setTime(0, 0, 0);
        $al = ($al ?? $dal->modify('+18 months'))->setTime(0, 0, 0);

        $stmt = $this->pdo->prepare(
            'SELECT id, azienda_id, data_chiusura, descrizione
             FROM calendario_chiusure
             WHERE data_chiusura BETWEEN :dal AND :al
               AND (azienda_id = :azienda OR azienda_id IS NULL)
             ORDER BY data_chiusura ASC, azienda_id IS NULL DESC, id ASC'
        );
        $stmt->execute([
            'dal' => $dal->format('Y-m-d'),
            'al' => $al->format('Y-m-d'),
            'azienda' => $aziendaId,
        ]);

        $proprie = [];
        $nazionali = [];
        foreach ($stmt as $riga) {
            $voce = [
                'id' => (int) $riga['id'],
                'data' => (string) $riga['data_chiusura'],
                'descrizione' => (string) $riga['descrizione'],
                'propria' => $riga['azienda_id'] !== null,
            ];
            if ($voce['propria']) {
                $proprie[] = $voce;
            } else {
                $nazionali[] = $voce;
            }
        }

        return [
            'proprie' => $proprie,
            'nazionali' => $nazionali,
            'dal' => $dal->format('Y-m-d'),
            'al' => $al->format('Y-m-d'),
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function aggiungi(int $aziendaId, array $input): array
    {
        $data = $this->data((string) ($input['data'] ?? ''));
        $descrizione = $this->descrizione((string) ($input['descrizione'] ?? ''));
        if ($this->motivoWeekend($data) !== null) {
            throw new MenuRuleException(
                'CHIUSURA_NON_NECESSARIA',
                'Sabato e domenica sono già chiusi per tutti i servizi.',
            );
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO calendario_chiusure (azienda_id, data_chiusura, descrizione)
                 VALUES (:azienda, :data, :descrizione)'
            );
            $stmt->execute([
                'azienda' => $aziendaId,
                'data' => $data,
                'descrizione' => $descrizione,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new MenuRuleException(
                    'CHIUSURA_DUPLICATA',
                    'Questa data è già segnata come chiusura per il servizio.',
                    409,
                );
            }
            throw $e;
        }

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'data' => $data,
            'descrizione' => $descrizione,
            'propria' => true,
        ];
    }

    public function togli(int $aziendaId, int $chiusuraId): void
    {
        if ($chiusuraId < 1) {
            throw new MenuRuleException('CHIUSURA_NON_VALIDA', 'Indica la chiusura da togliere.');
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM calendario_chiusure
             WHERE id = :id AND azienda_id = :azienda'
        );
        $stmt->execute(['id' => $chiusuraId, 'azienda' => $aziendaId]);
        if ($stmt->rowCount() < 1) {
            throw new MenuRuleException(
                'CHIUSURA_NON_TROVATA',
                'Puoi togliere solo le chiusure del tuo servizio, non le feste nazionali.',
                404,
            );
        }
    }

    private function data(string $valore): string
    {
        $fuso = new DateTimeZone(MenuValidator::FUSO);
        $parsata = DateTimeImmutable::createFromFormat('!Y-m-d', $valore, $fuso);
        if ($parsata === false || $parsata->format('Y-m-d') !== $valore) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'La data di chiusura non è valida.');
        }
        $oggi = new DateTimeImmutable('today', $fuso);
        if ($parsata < $oggi) {
            throw new MenuRuleException('DATA_PASSATA', 'Una chiusura già trascorsa non si modifica.');
        }

        return $valore;
    }

    private function descrizione(string $testo): string
    {
        $testo = preg_replace('/\s+/u', ' ', trim($testo)) ?? '';
        $testo = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $testo) ?? '');
        if ($testo === '') {
            $testo = 'Chiusura del servizio';
        }
        if (mb_strlen($testo) > 160) {
            throw new MenuRuleException('DESCRIZIONE_NON_VALIDA', 'La descrizione resta entro 160 caratteri.');
        }

        return $testo;
    }

    private function motivoWeekend(string $data): ?string
    {
        $giorno = (int) (new DateTimeImmutable($data, new DateTimeZone(MenuValidator::FUSO)))->format('N');

        return match ($giorno) {
            6 => 'Sabato',
            7 => 'Domenica',
            default => null,
        };
    }
}
