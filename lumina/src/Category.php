<?php

declare(strict_types=1);

namespace Lumina;

enum Category: string
{
    case Gym = 'gym';
    case Hair = 'hair';
    case Beauty = 'beauty';
    case Wellness = 'wellness';
    case Health = 'health';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Gym => 'Palestra',
            self::Hair => 'Parrucchiere',
            self::Beauty => 'Estetica',
            self::Wellness => 'Benessere',
            self::Health => 'Salute',
            self::Other => 'Altro',
        };
    }
}
