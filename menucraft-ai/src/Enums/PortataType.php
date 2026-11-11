<?php

declare(strict_types=1);

namespace MenuCraft\Enums;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

enum PortataType: string
{
    case Primo = 'primo';
    case Secondo = 'secondo';
    case Contorno = 'contorno';
    case Dessert = 'dessert';
    case Yogurt = 'yogurt';
    case Frutta = 'frutta';
    case Bevanda = 'bevanda';

    public function etichetta(): string
    {
        return match ($this) {
            self::Primo => 'Primo',
            self::Secondo => 'Secondo',
            self::Contorno => 'Contorno',
            self::Dessert => 'Dolce',
            self::Yogurt => 'Yogurt',
            self::Frutta => 'Frutta',
            self::Bevanda => 'Bevanda',
        };
    }

    public function ordine(): int
    {
        return match ($this) {
            self::Primo => 1,
            self::Secondo => 2,
            self::Contorno => 3,
            self::Dessert => 4,
            self::Yogurt => 5,
            self::Frutta => 6,
            self::Bevanda => 7,
        };
    }

    public static function interpreta(string $valore): ?self
    {
        $normalizzato = mb_strtolower(trim($valore));
        $normalizzato = match ($normalizzato) {
            'primi' => self::Primo->value,
            'secondi' => self::Secondo->value,
            'contorni' => self::Contorno->value,
            'dolce', 'dolci' => self::Dessert->value,
            'frutto', 'frutti' => self::Frutta->value,
            'bevande' => self::Bevanda->value,
            default => $normalizzato,
        };

        return self::tryFrom($normalizzato);
    }
}
