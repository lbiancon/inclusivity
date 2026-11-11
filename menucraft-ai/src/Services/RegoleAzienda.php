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
        $pagamento = array_key_exists('tipo_pagamento', $input)
            ? $this->pagamento($input['tipo_pagamento'])
            : $attuale->tipoPagamento->value;
        $bevanda = array_key_exists('bevanda_obbligatoria', $input)
            ? ($this->flag($input['bevanda_obbligatoria']) ? 1 : 0)
            : ($attuale->bevandaObbligatoria ? 1 : 0);
        $doppioPrimo = array_key_exists('permetti_doppio_primo', $input)
            ? ($this->flag($input['permetti_doppio_primo']) ? 1 : 0)
            : ($attuale->permettiDoppioPrimo ? 1 : 0);
        $doppioSecondo = array_key_exists('permetti_doppio_secondo', $input)
            ? ($this->flag($input['permetti_doppio_secondo']) ? 1 : 0)
            : ($attuale->permettiDoppioSecondo ? 1 : 0);
        $doppioContorno = array_key_exists('permetti_doppio_contorno', $input)
            ? ($this->flag($input['permetti_doppio_contorno']) ? 1 : 0)
            : ($attuale->permettiDoppioContorno ? 1 : 0);
        $doppioFrutta = array_key_exists('permetti_doppio_frutta', $input)
            ? ($this->flag($input['permetti_doppio_frutta']) ? 1 : 0)
            : ($attuale->permettiDoppioFrutta ? 1 : 0);
        $doppioDessert = array_key_exists('permetti_doppio_dessert', $input)
            ? ($this->flag($input['permetti_doppio_dessert']) ? 1 : 0)
            : ($attuale->permettiDoppioDessert ? 1 : 0);
        $maxPrimo = array_key_exists('max_primo', $input)
            ? $this->porzioni($input['max_primo'], 'Il massimo dei primi è tra 1 e 4.')
            : max(1, $attuale->maxPerCategoria(\MenuCraft\Enums\PortataType::Primo));
        $maxSecondo = array_key_exists('max_secondo', $input)
            ? $this->porzioni($input['max_secondo'], 'Il massimo dei secondi è tra 1 e 4.')
            : max(1, $attuale->maxPerCategoria(\MenuCraft\Enums\PortataType::Secondo));
        $maxContorno = array_key_exists('max_contorno', $input)
            ? $this->porzioni($input['max_contorno'], 'Il massimo dei contorni è tra 1 e 4.')
            : max(1, $attuale->maxPerCategoria(\MenuCraft\Enums\PortataType::Contorno));
        $maxFrutta = array_key_exists('max_frutta', $input)
            ? $this->porzioni($input['max_frutta'], 'Il massimo della frutta è tra 1 e 4.')
            : max(1, $attuale->maxPerCategoria(\MenuCraft\Enums\PortataType::Frutta));
        $maxDessert = array_key_exists('max_dessert', $input)
            ? $this->porzioni($input['max_dessert'], 'Il massimo dei dolci è tra 1 e 4.')
            : max(1, $attuale->maxPerCategoria(\MenuCraft\Enums\PortataType::Dessert));

        $stmt = $this->pdo->prepare(
            'UPDATE aziende
             SET max_scelte = :max,
                 permetti_doppio_primo = :primo,
                 permetti_doppio_secondo = :secondo,
                 permetti_doppio_contorno = :contorno,
                 permetti_doppio_frutta = :frutta,
                 permetti_doppio_dessert = :dessert,
                 max_primo = :max_primo,
                 max_secondo = :max_secondo,
                 max_contorno = :max_contorno,
                 max_frutta = :max_frutta,
                 max_dessert = :max_dessert,
                 limite_oggi = :oggi,
                 limite_domani = :domani,
                 modalita = :modalita,
                 tipo_pagamento = :pagamento,
                 bevanda_obbligatoria = :bevanda
             WHERE id = :id'
        );
        $stmt->execute([
            'max' => $max,
            'primo' => $doppioPrimo,
            'secondo' => $doppioSecondo,
            'contorno' => $doppioContorno,
            'frutta' => $doppioFrutta,
            'dessert' => $doppioDessert,
            'max_primo' => $maxPrimo,
            'max_secondo' => $maxSecondo,
            'max_contorno' => $maxContorno,
            'max_frutta' => $maxFrutta,
            'max_dessert' => $maxDessert,
            'oggi' => $oggi,
            'domani' => $domani,
            'modalita' => $modalita,
            'pagamento' => $pagamento,
            'bevanda' => $bevanda,
            'id' => $aziendaId,
        ]);

        return AziendaConfig::trova($this->pdo, $aziendaId);
    }

    private function porzioni(mixed $valore, string $messaggio): int
    {
        $numero = filter_var($valore, FILTER_VALIDATE_INT);
        if ($numero === false || $numero < 1 || $numero > 4) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', $messaggio);
        }

        return $numero;
    }

    private function pagamento(mixed $valore): string
    {
        $pagamento = strtolower(trim((string) $valore));
        if (!in_array($pagamento, ['welfare', 'dipendente'], true)) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'Il pagamento è a carico dell\'azienda oppure del dipendente.');
        }

        return $pagamento;
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
