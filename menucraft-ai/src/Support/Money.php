<?php

declare(strict_types=1);

namespace MenuCraft\Support;

use MenuCraft\Services\MenuRuleException;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class Money
{
    public static function euroToCentesimi(string $euro): int
    {
        $euro = trim($euro);
        if (!preg_match('/^(?<intero>\d{1,7})(?:\.(?<decimali>\d{1,2}))?$/', $euro, $parti)) {
            throw new MenuRuleException('PREZZO_NON_VALIDO', 'Prezzo non valido.');
        }

        $decimali = str_pad($parti['decimali'] ?? '', 2, '0');

        return ((int) $parti['intero']) * 100 + (int) $decimali;
    }

    public static function formatta(int $centesimi): string
    {
        if ($centesimi < 0) {
            throw new MenuRuleException('PREZZO_NON_VALIDO', 'Importo negativo non consentito.');
        }

        return intdiv($centesimi, 100) . '.' . str_pad((string) ($centesimi % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function normalizza(string $euro): string
    {
        $pulito = str_replace(['€', ' '], '', trim($euro));
        $pulito = str_replace(',', '.', $pulito);

        return self::formatta(self::euroToCentesimi($pulito));
    }
}
