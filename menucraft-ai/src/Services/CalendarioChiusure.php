<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use DateTimeZone;
use MenuCraft\Models\AziendaConfig;
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

    /** @return array<string, mixed> */
    public function elenco(int $aziendaId, ?DateTimeImmutable $dal = null, ?DateTimeImmutable $al = null): array
    {
        $fuso = new DateTimeZone(MenuValidator::FUSO);
        $dal = ($dal ?? new DateTimeImmutable('today', $fuso))->setTime(0, 0, 0);
        $al = ($al ?? $dal->modify('+18 months'))->setTime(0, 0, 0);
        $azienda = AziendaConfig::trova($this->pdo, $aziendaId);

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

        $aperture = $this->pdo->prepare(
            'SELECT id, data_apertura, nota
             FROM calendario_aperture
             WHERE azienda_id = :azienda
               AND data_apertura BETWEEN :dal AND :al
             ORDER BY data_apertura ASC'
        );
        $aperture->execute([
            'azienda' => $aziendaId,
            'dal' => $dal->format('Y-m-d'),
            'al' => $al->format('Y-m-d'),
        ]);
        $lavorativi = [];
        $dateAperte = [];
        foreach ($aperture as $riga) {
            $data = (string) $riga['data_apertura'];
            $dateAperte[$data] = true;
            $lavorativi[] = [
                'id' => (int) $riga['id'],
                'data' => $data,
                'descrizione' => trim((string) ($riga['nota'] ?? '')) !== ''
                    ? (string) $riga['nota']
                    : 'Giorno reso lavorativo',
            ];
        }

        $proprie = [];
        $nazionali = [];
        foreach ($stmt as $riga) {
            $data = (string) $riga['data_chiusura'];
            $voce = [
                'id' => (int) $riga['id'],
                'data' => $data,
                'descrizione' => (string) $riga['descrizione'],
                'propria' => $riga['azienda_id'] !== null,
                'lavorativo' => isset($dateAperte[$data]),
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
            'lavorativi' => $lavorativi,
            'apri_sabato' => $azienda->apriSabato,
            'apri_domenica' => $azienda->apriDomenica,
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
        $azienda = AziendaConfig::trova($this->pdo, $aziendaId);
        $giorno = (int) (new DateTimeImmutable($data, new DateTimeZone(MenuValidator::FUSO)))->format('N');
        if (!$azienda->weekendAperto($giorno) && ($giorno === 6 || $giorno === 7)) {
            throw new MenuRuleException(
                'CHIUSURA_NON_NECESSARIA',
                'Sabato e domenica sono già chiusi. Attiva “lavora il sabato/domenica” se vuoi gestirli giorno per giorno.',
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
                'Puoi togliere solo le chiusure del tuo servizio. Per una festa nazionale usa “Rendi lavorativo”.',
                404,
            );
        }
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function apri(int $aziendaId, array $input): array
    {
        $data = $this->data((string) ($input['data'] ?? ''));
        $nota = $this->descrizione((string) ($input['descrizione'] ?? 'Giorno reso lavorativo'));

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO calendario_aperture (azienda_id, data_apertura, nota)
                 VALUES (:azienda, :data, :nota)'
            );
            $stmt->execute([
                'azienda' => $aziendaId,
                'data' => $data,
                'nota' => $nota,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new MenuRuleException(
                    'APERTURA_DUPLICATA',
                    'Questa data è già segnata come lavorativa per il servizio.',
                    409,
                );
            }
            throw $e;
        }

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'data' => $data,
            'descrizione' => $nota,
        ];
    }

    public function togliApertura(int $aziendaId, int $aperturaId): void
    {
        if ($aperturaId < 1) {
            throw new MenuRuleException('APERTURA_NON_VALIDA', 'Indica il giorno lavorativo da togliere.');
        }
        $stmt = $this->pdo->prepare(
            'DELETE FROM calendario_aperture
             WHERE id = :id AND azienda_id = :azienda'
        );
        $stmt->execute(['id' => $aperturaId, 'azienda' => $aziendaId]);
        if ($stmt->rowCount() < 1) {
            throw new MenuRuleException('APERTURA_NON_TROVATA', 'Giorno lavorativo non trovato.', 404);
        }
    }

    private function data(string $valore): string
    {
        $fuso = new DateTimeZone(MenuValidator::FUSO);
        $parsata = DateTimeImmutable::createFromFormat('!Y-m-d', $valore, $fuso);
        if ($parsata === false || $parsata->format('Y-m-d') !== $valore) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'La data non è valida.');
        }
        $oggi = new DateTimeImmutable('today', $fuso);
        if ($parsata < $oggi) {
            throw new MenuRuleException('DATA_PASSATA', 'Una data già trascorsa non si modifica.');
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
}
