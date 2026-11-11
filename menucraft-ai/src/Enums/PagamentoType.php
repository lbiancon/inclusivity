<?php

declare(strict_types=1);

namespace MenuCraft\Enums;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

enum PagamentoType: string
{
    case Welfare = 'welfare';
    case Dipendente = 'dipendente';

    public function copertoDaAzienda(): bool
    {
        return $this === self::Welfare;
    }

    public function etichetta(): string
    {
        return match ($this) {
            self::Welfare => 'Welfare aziendale',
            self::Dipendente => 'Quota dipendente',
        };
    }
}
