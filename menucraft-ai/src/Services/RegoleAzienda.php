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
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'Il numero massimo di scelte deve essere compreso tra 1 e 10.');
        }
        $min = array_key_exists('min_scelte', $input)
            ? filter_var($input['min_scelte'], FILTER_VALIDATE_INT)
            : null;
        if ($min === false || ($min !== null && ($min < 1 || $min > $max))) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'Il minimo delle scelte deve stare tra 1 e il massimo.');
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
        if ($min === null) {
            $min = $bevanda === 1 ? $max : max(1, $attuale->minimoScelte());
        }
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
        $guida = array_key_exists('guida_linea', $input)
            ? $this->guida((string) $input['guida_linea'])
            : $attuale->guidaLinea;
        $contatto = array_key_exists('contatto_linea', $input)
            ? $this->contatto((string) $input['contatto_linea'])
            : $attuale->contattoLinea;
        $caricamento = array_key_exists('caricamento_menu', $input)
            ? $this->caricamentoMenu($input['caricamento_menu'])
            : $attuale->caricamentoMenu;

        $stmt = $this->pdo->prepare(
            'UPDATE aziende
             SET max_scelte = :max,
                 min_scelte = :min,
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
                 bevanda_obbligatoria = :bevanda,
                 guida_linea = :guida,
                 contatto_linea = :contatto,
                 caricamento_menu = :caricamento
             WHERE id = :id'
        );
        $stmt->execute([
            'max' => $max,
            'min' => $min,
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
            'guida' => $guida !== '' ? $guida : null,
            'contatto' => $contatto !== '' ? $contatto : null,
            'caricamento' => $caricamento,
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

    private function caricamentoMenu(mixed $valore): string
    {
        $modo = strtolower(trim((string) $valore));
        if (!in_array($modo, ['sempre', 'giovedi'], true)) {
            throw new MenuRuleException(
                'REGOLA_NON_VALIDA',
                'Il caricamento del menu è libero oppure solo il giovedì dopo le 19:00.',
            );
        }

        return $modo;
    }

    private function flag(mixed $valore): bool
    {
        return $valore === true || $valore === 1 || $valore === '1' || $valore === 'true' || $valore === 'on';
    }

    private function guida(string $testo): string
    {
        $testo = preg_replace('/\s+/u', ' ', trim($testo)) ?? '';
        $testo = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $testo) ?? '');
        if (mb_strlen($testo) > 800) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'Il messaggio di guida resta entro 800 caratteri.');
        }

        return $testo;
    }

    private function contatto(string $valore): string
    {
        $valore = preg_replace('/\s+/u', ' ', trim($valore)) ?? '';
        $valore = trim(preg_replace('/[^\p{L}\p{N}\p{P}\p{Zs}+]/u', '', $valore) ?? '');
        if (mb_strlen($valore) > 120) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'Il numero o WhatsApp resta entro 120 caratteri.');
        }

        return $valore;
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
