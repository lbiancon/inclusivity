<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

/**
 * Reminder prenotazione: stessa logica del VB getReminderListMail su MenuCraft.
 * Mattina → pasto di oggi (se lavorativo). Pomeriggio → primo giorno utile dopo oggi.
 * Solo dipendenti con mail_reminder=Y e email valorizzata, senza ordine personale quel giorno.
 */
final class ReminderMensa
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<string>
     */
    public function elencoEmail(
        string $tipo,
        int $aziendaId,
        ?DateTimeImmutable $riferimento = null,
    ): array {
        $tipo = strtolower(trim($tipo));
        if ($tipo !== 'mattina' && $tipo !== 'pomeriggio') {
            throw new MenuRuleException('REMINDER_TIPO', 'Tipo reminder: mattina o pomeriggio.');
        }

        $validator = new MenuValidator($this->pdo);
        $fuso = new \DateTimeZone(MenuValidator::FUSO);
        $oggi = $validator->giornoCivile($riferimento ?? $validator->adesso());

        if ($tipo === 'mattina') {
            if ($validator->motivoCalendario($aziendaId, $oggi) !== null) {
                return [];
            }
            $target = $oggi;
        } else {
            $target = $validator->prossimoGiornoExport($aziendaId, $oggi);
        }

        $sql = 'SELECT u.email
                FROM utenti u
                WHERE u.azienda_id = :azienda
                  AND u.ruolo = \'dipendente\'
                  AND u.mail_reminder = \'Y\'
                  AND u.email IS NOT NULL
                  AND TRIM(u.email) <> \'\'
                  AND NOT EXISTS (
                      SELECT 1 FROM ordini o
                      WHERE o.utente_id = u.id
                        AND o.azienda_id = u.azienda_id
                        AND o.data_pasto = :pasto
                        AND o.ospite = 0
                  )
                ORDER BY u.nome ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'azienda' => $aziendaId,
            'pasto' => $target->format('Y-m-d'),
        ]);

        $out = [];
        foreach ($stmt as $riga) {
            $email = strtolower(trim((string) $riga['email']));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return array{limite_oggi: string, limite_domani: string} */
    public function limiti(int $aziendaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT limite_oggi, limite_domani FROM aziende WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $aziendaId]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            throw new MenuRuleException('AZIENDA_SCONOSCIUTA', 'Azienda non trovata.', 404);
        }

        return [
            'limite_oggi' => substr((string) $riga['limite_oggi'], 0, 5),
            'limite_domani' => substr((string) $riga['limite_domani'], 0, 5),
        ];
    }
}
