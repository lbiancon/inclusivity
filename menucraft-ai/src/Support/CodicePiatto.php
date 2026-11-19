<?php

declare(strict_types=1);

namespace MenuCraft\Support;

use DateTimeImmutable;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class CodicePiatto
{
    public static function etichetta(string $codice): string
    {
        if (preg_match('/^([A-Z][A-Z0-9]{0,2})-\d{8}$/', $codice, $parti) === 1) {
            return $parti[1];
        }

        return $codice;
    }

    public static function daLettera(string $lettera, DateTimeImmutable $data): string
    {
        return strtoupper($lettera) . '-' . $data->format('Ymd');
    }
}
