<?php

declare(strict_types=1);

namespace MenuCraft\Models;

use MenuCraft\Enums\PagamentoType;
use MenuCraft\Enums\PortataType;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Support\Money;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

readonly class AziendaConfig
{
    public function __construct(
        public int $id,
        public string $nome,
        public string $slug,
        public PagamentoType $tipoPagamento,
        public int $maxScelte,
        public bool $permettiDoppioPrimo,
        public bool $permettiDoppioContorno,
        public string $limiteOggi,
        public string $limiteDomani,
        public string $modalita = 'intranet',
        public bool $bevandaObbligatoria = false,
        public bool $permettiDoppioSecondo = false,
        public int $maxPrimo = 0,
        public int $maxSecondo = 0,
        public int $maxContorno = 0,
        public bool $permettiDoppioFrutta = false,
        public bool $permettiDoppioDessert = false,
        public int $maxFrutta = 0,
        public int $maxDessert = 0,
        public ?string $logo = null,
        public int $minScelte = 0,
    ) {
        if ($this->id < 1) {
            throw new \InvalidArgumentException('Identificativo azienda non valido.');
        }
        if ($this->maxScelte < 1 || $this->maxScelte > 10) {
            throw new \InvalidArgumentException('Il numero massimo di scelte deve essere compreso tra 1 e 10.');
        }
        if ($this->minScelte !== 0 && ($this->minScelte < 1 || $this->minScelte > $this->maxScelte)) {
            throw new \InvalidArgumentException('Il numero minimo di scelte deve essere compreso tra 1 e il massimo.');
        }
        if (!in_array($this->modalita, ['intranet', 'internet'], true)) {
            throw new \InvalidArgumentException('Modalità azienda non valida.');
        }
    }

    public function minimoScelte(): int
    {
        if ($this->minScelte >= 1) {
            return $this->minScelte;
        }

        return $this->bevandaObbligatoria ? $this->maxScelte : 1;
    }

    /** @param array<string, mixed> $riga */
    public static function fromRow(array $riga): self
    {
        return new self(
            id: (int) $riga['id'],
            nome: (string) $riga['nome'],
            slug: (string) $riga['slug'],
            tipoPagamento: PagamentoType::from((string) $riga['tipo_pagamento']),
            maxScelte: (int) $riga['max_scelte'],
            permettiDoppioPrimo: (int) $riga['permetti_doppio_primo'] === 1,
            permettiDoppioContorno: (int) $riga['permetti_doppio_contorno'] === 1,
            limiteOggi: self::normalizzaOrario((string) $riga['limite_oggi']),
            limiteDomani: self::normalizzaOrario((string) $riga['limite_domani']),
            modalita: self::modalita((string) ($riga['modalita'] ?? 'intranet')),
            bevandaObbligatoria: (int) ($riga['bevanda_obbligatoria'] ?? 0) === 1,
            permettiDoppioSecondo: (int) ($riga['permetti_doppio_secondo'] ?? 0) === 1,
            maxPrimo: (int) ($riga['max_primo'] ?? 0),
            maxSecondo: (int) ($riga['max_secondo'] ?? 0),
            maxContorno: (int) ($riga['max_contorno'] ?? 0),
            permettiDoppioFrutta: (int) ($riga['permetti_doppio_frutta'] ?? 0) === 1,
            permettiDoppioDessert: (int) ($riga['permetti_doppio_dessert'] ?? 0) === 1,
            maxFrutta: (int) ($riga['max_frutta'] ?? 0),
            maxDessert: (int) ($riga['max_dessert'] ?? 0),
            logo: isset($riga['logo']) && is_string($riga['logo']) && $riga['logo'] !== '' ? $riga['logo'] : null,
            minScelte: (int) ($riga['min_scelte'] ?? 0),
        );
    }

    public static function trova(PDO $pdo, int $aziendaId): self
    {
        $stmt = $pdo->prepare(
            'SELECT id, nome, slug, tipo_pagamento, max_scelte, min_scelte, permetti_doppio_primo,
                    permetti_doppio_contorno, permetti_doppio_secondo, permetti_doppio_frutta, permetti_doppio_dessert,
                    max_primo, max_secondo, max_contorno, max_frutta, max_dessert, logo,
                    limite_oggi, limite_domani, modalita, bevanda_obbligatoria
             FROM aziende
             WHERE id = :id'
        );
        $stmt->execute(['id' => $aziendaId]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            throw new MenuRuleException('AZIENDA_ASSENTE', 'Azienda non trovata.', 404);
        }

        return self::fromRow($riga);
    }

    public function maxPerCategoria(PortataType $categoria): int
    {
        $base = match ($categoria) {
            PortataType::Secondo => $this->permettiDoppioSecondo ? 2 : 1,
            PortataType::Primo => $this->permettiDoppioPrimo ? 2 : 1,
            PortataType::Contorno => $this->permettiDoppioContorno ? 2 : 1,
            PortataType::Frutta => $this->permettiDoppioFrutta ? 2 : 1,
            PortataType::Dessert => $this->permettiDoppioDessert ? 2 : 1,
            default => $this->maxScelte,
        };
        $salvato = match ($categoria) {
            PortataType::Primo => $this->maxPrimo,
            PortataType::Secondo => $this->maxSecondo,
            PortataType::Contorno => $this->maxContorno,
            PortataType::Frutta => $this->maxFrutta,
            PortataType::Dessert => $this->maxDessert,
            default => 0,
        };
        if ($salvato < 1) {
            return $base;
        }
        if ($this->doppioStessoPiatto($categoria) && $salvato < 2) {
            return 2;
        }

        return $salvato;
    }

    public function doppioStessoPiatto(PortataType $categoria): bool
    {
        return match ($categoria) {
            PortataType::Primo => $this->permettiDoppioPrimo,
            PortataType::Secondo => $this->permettiDoppioSecondo,
            PortataType::Contorno => $this->permettiDoppioContorno,
            PortataType::Frutta => $this->permettiDoppioFrutta,
            PortataType::Dessert => $this->permettiDoppioDessert,
            default => false,
        };
    }

    public function prezzoApplicato(string $listino): string
    {
        if ($this->tipoPagamento->copertoDaAzienda()) {
            return '0.00';
        }

        return Money::normalizza($listino);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'slug' => $this->slug,
            'tipo_pagamento' => $this->tipoPagamento->value,
            'tipo_pagamento_etichetta' => $this->tipoPagamento->etichetta(),
            'max_scelte' => $this->maxScelte,
            'min_scelte' => $this->minimoScelte(),
            'permetti_doppio_primo' => $this->permettiDoppioPrimo,
            'permetti_doppio_secondo' => $this->permettiDoppioSecondo,
            'permetti_doppio_contorno' => $this->permettiDoppioContorno,
            'permetti_doppio_frutta' => $this->permettiDoppioFrutta,
            'permetti_doppio_dessert' => $this->permettiDoppioDessert,
            'max_primo' => $this->maxPerCategoria(PortataType::Primo),
            'max_secondo' => $this->maxPerCategoria(PortataType::Secondo),
            'max_contorno' => $this->maxPerCategoria(PortataType::Contorno),
            'max_frutta' => $this->maxPerCategoria(PortataType::Frutta),
            'max_dessert' => $this->maxPerCategoria(PortataType::Dessert),
            'logo' => $this->logo,
            'limite_oggi' => substr($this->limiteOggi, 0, 5),
            'limite_domani' => substr($this->limiteDomani, 0, 5),
            'modalita' => $this->modalita,
            'modalita_etichetta' => $this->modalita === 'internet' ? 'Internet' : 'Intranet',
            'bevanda_obbligatoria' => $this->bevandaObbligatoria,
            'pagamento_etichetta' => $this->tipoPagamento->copertoDaAzienda() ? 'A carico dell\'azienda' : 'Paga il dipendente',
        ];
    }

    private static function modalita(string $valore): string
    {
        $modalita = strtolower(trim($valore));
        if (!in_array($modalita, ['intranet', 'internet'], true)) {
            throw new \InvalidArgumentException('Modalità azienda non valida.');
        }

        return $modalita;
    }

    private static function normalizzaOrario(string $orario): string
    {
        if (!preg_match('/^(?<h>\d{2}):(?<m>\d{2})(?::(?<s>\d{2}))?$/', $orario, $parti)) {
            throw new \InvalidArgumentException('Orario del tenant non valido.');
        }

        $ore = (int) $parti['h'];
        $minuti = (int) $parti['m'];
        $secondi = (int) ($parti['s'] ?? 0);
        if ($ore > 23 || $minuti > 59 || $secondi > 59) {
            throw new \InvalidArgumentException('Orario del tenant non valido.');
        }

        return sprintf('%02d:%02d:%02d', $ore, $minuti, $secondi);
    }
}
