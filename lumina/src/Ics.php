<?php

declare(strict_types=1);

namespace Lumina;

use DateTimeImmutable;
use DateTimeZone;

final class Ics
{
    public static function event(
        string $uid,
        DateTimeImmutable $startUtc,
        DateTimeImmutable $endUtc,
        string $summary,
        string $location,
        string $description,
    ): string {
        $utc = new DateTimeZone('UTC');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Lumina//Agenda//IT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . self::text($uid),
            'DTSTAMP:' . (new DateTimeImmutable('now', $utc))->format('Ymd\THis\Z'),
            'DTSTART:' . $startUtc->setTimezone($utc)->format('Ymd\THis\Z'),
            'DTEND:' . $endUtc->setTimezone($utc)->format('Ymd\THis\Z'),
            'SUMMARY:' . self::text($summary),
            'LOCATION:' . self::text($location),
            'DESCRIPTION:' . self::text($description),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    private static function text(string $value): string
    {
        return str_replace(
            ['\\', "\r", "\n", ',', ';'],
            ['\\\\', '', '\n', '\,', '\;'],
            $value,
        );
    }
}
