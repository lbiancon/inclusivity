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
    ) {
        if ($this->id < 1) {
            throw new \InvalidArgumentException('Identificativo azienda non valido.');
        }
        if ($this->maxScelte < 1 || $this->maxScelte > 10) {
            throw new \InvalidArgumentException('Il numero massimo di scelte deve essere compreso tra 1 e 10.');
        }
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
        );
    }

    public static function trova(PDO $pdo, int $aziendaId): self
    {
        $stmt = $pdo->prepare(
            'SELECT id, nome, slug, tipo_pagamento, max_scelte, permetti_doppio_primo,
                    permetti_doppio_contorno, limite_oggi, limite_domani
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
        return match ($categoria) {
            PortataType::Secondo => 1,
            PortataType::Primo => $this->permettiDoppioPrimo ? 2 : 1,
            PortataType::Contorno => $this->permettiDoppioContorno ? 2 : 1,
            default => $this->maxScelte,
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
            'permetti_doppio_primo' => $this->permettiDoppioPrimo,
            'permetti_doppio_contorno' => $this->permettiDoppioContorno,
            'limite_oggi' => substr($this->limiteOggi, 0, 5),
            'limite_domani' => substr($this->limiteDomani, 0, 5),
        ];
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
