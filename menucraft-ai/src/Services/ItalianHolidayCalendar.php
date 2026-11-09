<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use DateTimeZone;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class ItalianHolidayCalendar
{
    public function __construct(
        private readonly DateTimeZone $fuso = new DateTimeZone('Europe/Rome'),
    ) {
    }

    /** @return array<string, string> */
    public function nazionali(int $anno): array
    {
        $fisse = [
            '01-01' => 'Capodanno',
            '01-06' => 'Epifania',
            '04-25' => 'Festa della Liberazione',
            '05-01' => 'Festa dei Lavoratori',
            '06-02' => 'Festa della Repubblica',
            '08-15' => 'Ferragosto',
            '11-01' => 'Ognissanti',
            '12-08' => 'Immacolata Concezione',
            '12-25' => 'Natale',
            '12-26' => 'Santo Stefano',
        ];

        $feste = [];
        foreach ($fisse as $meseGiorno => $nome) {
            $feste[sprintf('%04d-%s', $anno, $meseGiorno)] = $nome;
        }

        $pasqua = $this->pasqua($anno);
        $feste[$pasqua->format('Y-m-d')] = 'Pasqua';
        $feste[$pasqua->modify('+1 day')->format('Y-m-d')] = "Lunedì dell'Angelo";

        return $feste;
    }

    public function pasqua(int $anno): DateTimeImmutable
    {
        $a = $anno % 19;
        $b = intdiv($anno, 100);
        $c = $anno % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mese = intdiv($h + $l - 7 * $m + 114, 31);
        $giorno = (($h + $l - 7 * $m + 114) % 31) + 1;

        $data = DateTimeImmutable::createFromFormat(
            '!Y-n-j',
            $anno . '-' . $mese . '-' . $giorno,
            $this->fuso,
        );
        if ($data === false) {
            throw new \RuntimeException('Calcolo della Pasqua non riuscito.');
        }

        return $data;
    }
}
