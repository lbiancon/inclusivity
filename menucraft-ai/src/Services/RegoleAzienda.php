<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use MenuCraft\Models\AziendaConfig;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class RegoleAzienda
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $input */
    public function salva(int $aziendaId, array $input): AziendaConfig
    {
        $max = filter_var($input['max_scelte'] ?? null, FILTER_VALIDATE_INT);
        if ($max === false || $max < 1 || $max > 10) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'Il numero di scelte deve essere compreso tra 1 e 10.');
        }

        $oggi = $this->orario((string) ($input['limite_oggi'] ?? ''), 'L\'orario di chiusura di oggi non è valido.');
        $domani = $this->orario((string) ($input['limite_domani'] ?? ''), 'L\'orario di blocco del giorno prima non è valido.');
        $attuale = AziendaConfig::trova($this->pdo, $aziendaId);
        $modalita = array_key_exists('modalita', $input)
            ? $this->modalita($input['modalita'])
            : $attuale->modalita;

        $stmt = $this->pdo->prepare(
            'UPDATE aziende
             SET max_scelte = :max,
                 permetti_doppio_primo = :primo,
                 permetti_doppio_contorno = :contorno,
                 limite_oggi = :oggi,
                 limite_domani = :domani,
                 modalita = :modalita
             WHERE id = :id'
        );
        $stmt->execute([
            'max' => $max,
            'primo' => $this->flag($input['permetti_doppio_primo'] ?? false) ? 1 : 0,
            'contorno' => $this->flag($input['permetti_doppio_contorno'] ?? false) ? 1 : 0,
            'oggi' => $oggi,
            'domani' => $domani,
            'modalita' => $modalita,
            'id' => $aziendaId,
        ]);

        return AziendaConfig::trova($this->pdo, $aziendaId);
    }

    private function modalita(mixed $valore): string
    {
        $modalita = strtolower(trim((string) $valore));
        if (!in_array($modalita, ['intranet', 'internet'], true)) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'La modalità è intranet oppure internet.');
        }

        return $modalita;
    }

    private function flag(mixed $valore): bool
    {
        return $valore === true || $valore === 1 || $valore === '1' || $valore === 'true' || $valore === 'on';
    }

    private function orario(string $orario, string $messaggio): string
    {
        if (preg_match('/^(?<h>\d{2}):(?<m>\d{2})(?::(?<s>\d{2}))?$/', trim($orario), $parti) !== 1) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', $messaggio);
        }
        $ore = (int) $parti['h'];
        $minuti = (int) $parti['m'];
        $secondi = (int) ($parti['s'] ?? 0);
        if ($ore > 23 || $minuti > 59 || $secondi > 59) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', $messaggio);
        }

        return sprintf('%02d:%02d:%02d', $ore, $minuti, $secondi);
    }
}
