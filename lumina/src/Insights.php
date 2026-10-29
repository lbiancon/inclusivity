<?php

declare(strict_types=1);

namespace Lumina;

use DateTimeZone;
use Lumina\Schedule\Engine;
use PDO;

final class Insights
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
        private Engine $engine,
    ) {
    }

    public function dashboard(array $business): array
    {
        $zone = $this->engine->zone($business);
        $today = $this->clock->today($zone);
        [$dayStart, $dayEnd] = $this->clock->dayBounds($today, $zone);
        $local = $this->clock->local($this->clock->now(), $zone)->setTime(0, 0);
        $monday = $local->modify('-' . ((int) $local->format('N') - 1) . ' days');
        $weekStart = $monday->setTimezone(new DateTimeZone('UTC'));
        $weekEnd = $monday->modify('+7 days')->setTimezone(new DateTimeZone('UTC'));
        $from = $this->clock->now()->modify('-30 days');
        $rows = $this->appointments((int) $business['id'], $from, $weekEnd);
        $expected = 0;
        $completedWeek = 0;
        $kept = 0;
        $missed = 0;
        $todayRows = [];
        $deposit = 0;
        foreach ($rows as $row) {
            $start = $this->clock->parseUtc((string) $row['starts_at']);
            $status = (string) $row['status'];
            if ($start >= $dayStart && $start < $dayEnd && $status !== 'cancelled') {
                $todayRows[] = $row;
                $expected += (int) $row['price_cents'];
                if ((int) $row['deposit_advised'] === 1) {
                    $deposit++;
                }
            }
            if ($start >= $weekStart && $start < $weekEnd && $status === 'completed') {
                $completedWeek += (int) $row['price_cents'];
            }
            if ($start >= $from && $status === 'completed') {
                $kept++;
            }
            if ($start >= $from && $status === 'no_show') {
                $missed++;
            }
        }
        $sample = $kept + $missed;
        $upcoming = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->clock->parseUtc((string) $row['starts_at']) >= $this->clock->now()
                && in_array((string) $row['status'], ['confirmed', 'arrived', 'in_progress'], true),
        ));
        usort($upcoming, fn (array $a, array $b): int => strcmp((string) $a['starts_at'], (string) $b['starts_at']));

        return [
            'today' => $today,
            'today_rows' => $todayRows,
            'expected_today' => $expected,
            'completed_week' => $completedWeek + $this->classRevenue((int) $business['id'], $weekStart, $weekEnd, 'attended'),
            'next' => array_slice($upcoming, 0, 6),
            'occupancy' => $this->engine->occupancy((int) $business['id'], $today),
            'ideals' => $this->engine->idealCount((int) $business['id'], $today),
            'waiting' => (int) Db::value($this->pdo, "SELECT COUNT(*) FROM waitlist WHERE business_id = :id AND status = 'waiting'", ['id' => (int) $business['id']]),
            'offered' => (int) Db::value($this->pdo, "SELECT COUNT(*) FROM waitlist WHERE business_id = :id AND status = 'offered'", ['id' => (int) $business['id']]),
            'noshow_percent' => $sample > 0 ? (int) round($missed / $sample * 100) : null,
            'deposit_today' => $deposit,
        ];
    }

    public function picture(array $business): array
    {
        $zone = $this->engine->zone($business);
        $local = $this->clock->local($this->clock->now(), $zone);
        $start30 = $local->modify('-30 days')->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'));
        $start7 = $local->modify('-7 days')->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'));
        $end = $local->modify('+7 days')->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'));
        $rows = $this->appointments((int) $business['id'], $start30, $end);
        $done30 = 0;
        $done7 = 0;
        $ahead = 0;
        $kept = 0;
        $missed = 0;
        $hours = array_fill(7, 15, 0);
        $services = [];
        foreach ($rows as $row) {
            $start = $this->clock->parseUtc((string) $row['starts_at']);
            $status = (string) $row['status'];
            $price = (int) $row['price_cents'];
            if ($status === 'completed') {
                $done30 += $price;
                if ($start >= $start7) {
                    $done7 += $price;
                }
                $kept++;
            }
            if ($status === 'no_show') {
                $missed++;
            }
            if ($start >= $this->clock->now() && $start < $end && !in_array($status, ['cancelled', 'no_show'], true)) {
                $ahead += $price;
            }
            if ($start >= $start30 && $start < $this->clock->now() && $status !== 'cancelled') {
                $hour = (int) $this->clock->local($start, $zone)->format('G');
                if ($hour >= 7 && $hour <= 21) {
                    $hours[$hour]++;
                }
                $name = (string) $row['service_name'];
                $services[$name] = ($services[$name] ?? 0) + 1;
            }
        }
        arsort($services);
        $sample = $kept + $missed;
        $classDone = $this->classRevenue((int) $business['id'], $start30, $this->clock->now(), 'attended');

        return [
            'done30' => $done30 + $classDone,
            'done7' => $done7,
            'ahead' => $ahead,
            'noshow_percent' => $sample > 0 ? (int) round($missed / $sample * 100) : null,
            'sample' => $sample,
            'hours' => $hours,
            'hour_max' => max(1, max($hours)),
            'services' => array_slice($services, 0, 6, true),
            'occupancy' => $this->engine->occupancy((int) $business['id'], $this->clock->today($zone)),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function appointments(int $businessId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return Db::all(
            $this->pdo,
            'SELECT a.*, s.name AS service_name, st.name AS staff_name, st.color AS staff_color,
                    c.name AS client_name, c.allergies
             FROM appointments a
             INNER JOIN services s ON s.id = a.service_id
             INNER JOIN staff st ON st.id = a.staff_id
             INNER JOIN clients c ON c.id = a.client_id
             WHERE a.business_id = :business AND a.starts_at >= :from_at AND a.starts_at < :to_at
             ORDER BY a.starts_at',
            [
                'business' => $businessId,
                'from_at' => $this->clock->sql($from),
                'to_at' => $this->clock->sql($to),
            ],
        );
    }

    private function classRevenue(int $businessId, \DateTimeImmutable $from, \DateTimeImmutable $to, string $status): int
    {
        return (int) Db::value(
            $this->pdo,
            'SELECT COALESCE(SUM(b.price_cents), 0)
             FROM class_bookings b
             INNER JOIN class_sessions s ON s.id = b.session_id
             WHERE s.business_id = :business AND b.status = :status
               AND s.starts_at >= :from_at AND s.starts_at < :to_at',
            [
                'business' => $businessId,
                'status' => $status,
                'from_at' => $this->clock->sql($from),
                'to_at' => $this->clock->sql($to),
            ],
        );
    }
}
