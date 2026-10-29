<?php

declare(strict_types=1);

namespace Lumina;

use DateTimeZone;
use PDO;

final class Plan
{
    /** @var array<string, array{staff: int, bookings: ?int, rules: ?int, waitlist: bool, classes: bool, packages: bool, series: bool, analytics: bool, export: bool, month: int, year: int, name: string, line: string}> */
    private const OFFERS = [
        'bottega' => [
            'name' => 'Bottega',
            'line' => 'Per chi apre da solo e vuole vedere se l\'agenda gli sta addosso.',
            'staff' => 1,
            'bookings' => 40,
            'rules' => 0,
            'waitlist' => false,
            'classes' => false,
            'packages' => false,
            'series' => false,
            'analytics' => false,
            'export' => false,
            'month' => 0,
            'year' => 0,
        ],
        'salone' => [
            'name' => 'Salone',
            'line' => 'Il piano con cui un salone ci lavora ogni giorno.',
            'staff' => 8,
            'bookings' => null,
            'rules' => 8,
            'waitlist' => true,
            'classes' => true,
            'packages' => true,
            'series' => true,
            'analytics' => false,
            'export' => true,
            'month' => 2900,
            'year' => 29000,
        ],
        'borgo' => [
            'name' => 'Borgo',
            'line' => 'Per la palestra o lo studio che vuole i numeri, non solo l\'agenda.',
            'staff' => 40,
            'bookings' => null,
            'rules' => null,
            'waitlist' => true,
            'classes' => true,
            'packages' => true,
            'series' => true,
            'analytics' => true,
            'export' => true,
            'month' => 7900,
            'year' => 79000,
        ],
    ];

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::OFFERS);
    }

    /** @return array<string, array<string, mixed>> */
    public static function offers(): array
    {
        return self::OFFERS;
    }

    /** @param array<string, mixed> $business */
    public static function id(array $business): string
    {
        $plan = (string) ($business['plan'] ?? 'salone');

        return isset(self::OFFERS[$plan]) ? $plan : 'salone';
    }

    /** @param array<string, mixed> $business */
    public static function allows(array $business, string $feature): bool
    {
        return (bool) (self::OFFERS[self::id($business)][$feature] ?? false);
    }

    public static function assertFeature(PDO $pdo, int $businessId, string $feature): void
    {
        $business = self::load($pdo, $businessId);
        if (!self::allows($business, $feature)) {
            throw new DomainException('plan', self::locked(self::id($business), $feature));
        }
    }

    /** @param array<string, mixed> $business */
    public static function assertBooking(PDO $pdo, Clock $clock, array $business): void
    {
        $offer = self::OFFERS[self::id($business)];
        $cap = $offer['bookings'];
        if ($cap === null) {
            return;
        }
        $zone = new DateTimeZone((string) ($business['timezone'] ?? 'Europe/Rome'));
        $local = $clock->local($clock->now(), $zone);
        $start = $local->modify('first day of this month')->setTime(0, 0);
        $end = $start->modify('+1 month');
        $count = (int) Db::value(
            $pdo,
            "SELECT COUNT(*) FROM appointments
             WHERE business_id = :id AND status <> 'cancelled' AND starts_at >= :start_at AND starts_at < :end_at",
            [
                'id' => (int) $business['id'],
                'start_at' => $clock->sql($start->setTimezone(new DateTimeZone('UTC'))),
                'end_at' => $clock->sql($end->setTimezone(new DateTimeZone('UTC'))),
            ],
        );
        if ($count >= $cap) {
            throw new DomainException('plan', 'Bottega include ' . $cap . ' prenotazioni al mese. Il Salone toglie il tetto.');
        }
    }

    public static function assertStaff(PDO $pdo, int $businessId, bool $adding): void
    {
        if (!$adding) {
            return;
        }
        $business = self::load($pdo, $businessId);
        $cap = self::OFFERS[self::id($business)]['staff'];
        $count = (int) Db::value($pdo, 'SELECT COUNT(*) FROM staff WHERE business_id = :id AND active = 1', ['id' => $businessId]);
        if ($count >= $cap) {
            throw new DomainException('plan', 'Questo piano arriva a ' . $cap . ' ' . ($cap === 1 ? 'persona' : 'persone') . ' in agenda.');
        }
    }

    public static function assertRule(PDO $pdo, int $businessId): void
    {
        $business = self::load($pdo, $businessId);
        $cap = self::OFFERS[self::id($business)]['rules'];
        if ($cap === null) {
            return;
        }
        if ($cap === 0) {
            throw new DomainException('plan', 'Il prezzo vivo parte dal piano Salone.');
        }
        $count = (int) Db::value($pdo, 'SELECT COUNT(*) FROM pricing_rules WHERE business_id = :id AND active = 1', ['id' => $businessId]);
        if ($count >= $cap) {
            throw new DomainException('plan', 'Il Salone include ' . $cap . ' regole di prezzo. Borgo non ha questo tetto.');
        }
    }

    /** @return array<string, mixed> */
    private static function load(PDO $pdo, int $businessId): array
    {
        $business = Db::one($pdo, 'SELECT * FROM businesses WHERE id = :id', ['id' => $businessId]);
        if ($business === null) {
            throw new DomainException('not_found', 'Attività non trovata.');
        }

        return $business;
    }

    private static function locked(string $plan, string $feature): string
    {
        $name = self::OFFERS[$plan]['name'];

        return match ($feature) {
            'waitlist' => $name . ' non include la lista d\'attesa. Si apre col Salone.',
            'classes' => $name . ' non include i corsi. Si aprono col Salone.',
            'packages' => $name . ' non include le tessere. Si aprono col Salone.',
            'series' => $name . ' non include le serie settimanali. Si aprono col Salone.',
            'analytics' => 'Le analisi sono nel piano Borgo.',
            'export' => 'L\'export della settimana è nel piano Salone.',
            default => 'Questa funzione non è nel piano ' . $name . '.',
        };
    }
}
