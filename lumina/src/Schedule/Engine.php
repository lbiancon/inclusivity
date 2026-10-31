<?php

declare(strict_types=1);

namespace Lumina\Schedule;

use DateTimeImmutable;
use DateTimeZone;
use Lumina\Clock;
use Lumina\Db;
use Lumina\Policy;
use PDO;

final class Engine
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function slots(
        int $businessId,
        int $serviceId,
        string $date,
        ?int $staffId = null,
        bool $includePast = false,
        ?int $onlyResourceId = null,
        int $ignoreAppointmentId = 0,
        int $ignoreSessionId = 0,
    ): array {
        $context = $this->context($businessId, $serviceId, $date, $date, $ignoreAppointmentId, $ignoreSessionId);
        if ($context === null) {
            return [];
        }
        $slots = $this->computeDay($context, $date, $this->clock->now(), $includePast, $onlyResourceId);
        if ($staffId !== null && $staffId > 0) {
            $slots = array_values(array_filter(
                $slots,
                static fn (array $slot): bool => (int) $slot['staff_id'] === $staffId,
            ));
        }

        return $slots;
    }

    public function quote(
        int $businessId,
        int $serviceId,
        int $staffId,
        ?int $resourceId,
        DateTimeImmutable $startsAt,
        bool $includePast = false,
        int $ignoreAppointmentId = 0,
    ): ?array {
        $business = Db::one($this->pdo, 'SELECT * FROM businesses WHERE id = :id', ['id' => $businessId]);
        if ($business === null) {
            return null;
        }
        $local = $this->clock->local($startsAt, $this->zone($business));
        $slots = $this->slots(
            $businessId,
            $serviceId,
            $local->format('Y-m-d'),
            $staffId,
            $includePast,
            $resourceId,
            $ignoreAppointmentId,
        );
        foreach ($slots as $slot) {
            if ($slot['starts_at']->getTimestamp() === $startsAt->getTimestamp()) {
                return $slot;
            }
        }

        return null;
    }

    /** @return list<array{date: string, count: int, ideals: int}> */
    public function dots(int $businessId, int $serviceId, ?int $staffId, string $fromDate, int $days): array
    {
        $days = max(1, min(21, $days));
        $business = Db::one($this->pdo, 'SELECT * FROM businesses WHERE id = :id', ['id' => $businessId]);
        if ($business === null) {
            return [];
        }
        $zone = $this->zone($business);
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $fromDate, $zone);
        if ($start === false) {
            return [];
        }
        $endDate = $start->modify('+' . ($days - 1) . ' days')->format('Y-m-d');
        $context = $this->context($businessId, $serviceId, $fromDate, $endDate, 0, 0);
        $dots = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify('+' . $i . ' days')->format('Y-m-d');
            $slots = $context === null ? [] : $this->computeDay($context, $date, $this->clock->now(), false, null);
            if ($staffId !== null && $staffId > 0) {
                $slots = array_values(array_filter(
                    $slots,
                    static fn (array $slot): bool => (int) $slot['staff_id'] === $staffId,
                ));
            }
            $ideals = 0;
            foreach ($slots as $slot) {
                if ($slot['quality'] === 'ideal') {
                    $ideals++;
                }
            }
            $dots[] = ['date' => $date, 'count' => count($slots), 'ideals' => $ideals];
        }

        return $dots;
    }

    /** @return list<array<string, mixed>> */
    public function occupancy(int $businessId, string $date): array
    {
        $business = Db::one($this->pdo, 'SELECT * FROM businesses WHERE id = :id', ['id' => $businessId]);
        if ($business === null) {
            return [];
        }
        $zone = $this->zone($business);
        [$from, $to] = $this->clock->dayBounds($date, $zone);
        $staff = Db::all(
            $this->pdo,
            'SELECT * FROM staff WHERE business_id = :id AND active = 1 ORDER BY name',
            ['id' => $businessId],
        );
        $hours = $this->hours($businessId);
        $busy = $this->busy($businessId, $from->modify('-6 hours'), $to->modify('+6 hours'), 0, 0);
        $rows = [];
        foreach ($staff as $person) {
            $windows = $this->windows($hours, $person, $date, $zone);
            $open = 0;
            $spans = [];
            foreach ($windows as [$windowStart, $windowEnd]) {
                $open += (int) round(($windowEnd->getTimestamp() - $windowStart->getTimestamp()) / 60);
                $spans[] = [$windowStart->getTimestamp(), $windowEnd->getTimestamp()];
            }
            $pieces = [];
            foreach ($busy as $block) {
                if ((int) $block['staff_id'] !== (int) $person['id']) {
                    continue;
                }
                $blockStart = $this->clock->parseUtc((string) $block['block_starts_at'])->getTimestamp();
                $blockEnd = $this->clock->parseUtc((string) $block['block_ends_at'])->getTimestamp();
                foreach ($spans as [$windowStart, $windowEnd]) {
                    $start = max($blockStart, $windowStart);
                    $end = min($blockEnd, $windowEnd);
                    if ($start < $end) {
                        $pieces[] = [$start, $end];
                    }
                }
            }
            $busyMinutes = 0;
            foreach ($this->merge($pieces) as [$start, $end]) {
                $busyMinutes += (int) round(($end - $start) / 60);
            }
            $busyMinutes = min($busyMinutes, $open);
            $rows[] = [
                'staff_id' => (int) $person['id'],
                'name' => (string) $person['name'],
                'color' => (string) $person['color'],
                'open_minutes' => $open,
                'busy_minutes' => $busyMinutes,
                'ratio' => $open > 0 ? (int) round($busyMinutes / $open * 100) : 0,
            ];
        }

        return $rows;
    }

    public function idealCount(int $businessId, string $date): int
    {
        $services = Db::all(
            $this->pdo,
            "SELECT id FROM services WHERE business_id = :id AND active = 1 AND kind = 'appointment'",
            ['id' => $businessId],
        );
        $count = 0;
        foreach ($services as $service) {
            foreach ($this->slots($businessId, (int) $service['id'], $date) as $slot) {
                if ($slot['quality'] === 'ideal') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** @return array{closed: bool, open_min: int, close_min: int, breaks: list<array{start_min: int, end_min: int}>} */
    public function frame(int $businessId, string $date): array
    {
        $business = Db::one($this->pdo, 'SELECT * FROM businesses WHERE id = :id', ['id' => $businessId]);
        $empty = ['closed' => true, 'open_min' => 9 * 60, 'close_min' => 18 * 60, 'breaks' => []];
        if ($business === null) {
            return $empty;
        }
        $zone = $this->zone($business);
        $weekday = (int) (new DateTimeImmutable($date . ' 00:00:00', $zone))->format('N');
        $rows = array_values(array_filter(
            $this->hours($businessId),
            static fn (array $row): bool => $row['staff_id'] === null && (int) $row['weekday'] === $weekday,
        ));
        if ($rows === []) {
            return $empty;
        }
        $windows = [];
        foreach ($rows as $row) {
            $start = $this->minutes((string) $row['start_time']);
            $end = $this->minutes((string) $row['end_time']);
            if ($end > $start) {
                $windows[] = [$start, $end];
            }
        }
        usort($windows, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $breaks = [];
        for ($i = 1, $n = count($windows); $i < $n; $i++) {
            if ($windows[$i][0] > $windows[$i - 1][1]) {
                $breaks[] = ['start_min' => $windows[$i - 1][1], 'end_min' => $windows[$i][0]];
            }
        }

        return [
            'closed' => false,
            'open_min' => $windows[0][0],
            'close_min' => $windows[$n - 1][1],
            'breaks' => $breaks,
        ];
    }

    public function zone(array $business): DateTimeZone
    {
        $name = (string) ($business['timezone'] ?? 'Europe/Rome');
        try {
            return new DateTimeZone($name);
        } catch (\Throwable) {
            return new DateTimeZone('Europe/Rome');
        }
    }

    /** @return array<string, mixed>|null */
    private function context(
        int $businessId,
        int $serviceId,
        string $fromDate,
        string $toDate,
        int $ignoreAppointmentId,
        int $ignoreSessionId,
    ): ?array {
        $business = Db::one($this->pdo, 'SELECT * FROM businesses WHERE id = :id', ['id' => $businessId]);
        $service = Db::one(
            $this->pdo,
            'SELECT * FROM services WHERE id = :id AND business_id = :business AND active = 1',
            ['id' => $serviceId, 'business' => $businessId],
        );
        if ($business === null || $service === null || (string) $service['kind'] !== 'appointment') {
            return null;
        }
        $zone = $this->zone($business);
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $fromDate, $zone);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $toDate, $zone);
        if ($from === false || $to === false) {
            return null;
        }
        $rangeStart = $from->setTimezone(new DateTimeZone('UTC'))->modify('-6 hours');
        $rangeEnd = $to->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->modify('+6 hours');

        return [
            'business' => $business,
            'service' => $service,
            'staff' => Db::all(
                $this->pdo,
                'SELECT s.* FROM staff s
                 INNER JOIN service_staff ss ON ss.staff_id = s.id
                 WHERE s.business_id = :business AND ss.service_id = :service AND s.active = 1
                 ORDER BY s.name, s.id',
                ['business' => $businessId, 'service' => $serviceId],
            ),
            'resources' => Db::all(
                $this->pdo,
                'SELECT r.* FROM resources r
                 INNER JOIN service_resources sr ON sr.resource_id = r.id
                 WHERE sr.service_id = :service AND r.active = 1
                 ORDER BY r.id',
                ['service' => $serviceId],
            ),
            'hours' => $this->hours($businessId),
            'closures' => Db::all(
                $this->pdo,
                'SELECT * FROM closures
                 WHERE business_id = :business AND starts_at < :end AND ends_at > :start',
                [
                    'business' => $businessId,
                    'start' => $this->clock->sql($rangeStart),
                    'end' => $this->clock->sql($rangeEnd),
                ],
            ),
            'busy' => $this->busy($businessId, $rangeStart, $rangeEnd, $ignoreAppointmentId, $ignoreSessionId),
            'rules' => Db::all(
                $this->pdo,
                'SELECT * FROM pricing_rules WHERE business_id = :business AND active = 1',
                ['business' => $businessId],
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function hours(int $businessId): array
    {
        return Db::all(
            $this->pdo,
            'SELECT * FROM weekly_hours WHERE business_id = :business',
            ['business' => $businessId],
        );
    }

    /** @return list<array<string, mixed>> */
    private function busy(
        int $businessId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        int $ignoreAppointmentId,
        int $ignoreSessionId,
    ): array {
        return Db::all(
            $this->pdo,
            "SELECT id, staff_id, resource_id, block_starts_at, block_ends_at
             FROM appointments
             WHERE business_id = :business
               AND status <> 'cancelled'
               AND block_starts_at < :end_at
               AND block_ends_at > :start_at
               AND id <> :ignore_appointment
             UNION ALL
             SELECT id, staff_id, resource_id, block_starts_at, block_ends_at
             FROM class_sessions
             WHERE business_id = :business_class
               AND status <> 'cancelled'
               AND block_starts_at < :end_class
               AND block_ends_at > :start_class
               AND id <> :ignore_session",
            [
                'business' => $businessId,
                'end_at' => $this->clock->sql($end),
                'start_at' => $this->clock->sql($start),
                'ignore_appointment' => $ignoreAppointmentId,
                'business_class' => $businessId,
                'end_class' => $this->clock->sql($end),
                'start_class' => $this->clock->sql($start),
                'ignore_session' => $ignoreSessionId,
            ],
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function computeDay(array $context, string $date, DateTimeImmutable $now, bool $includePast, ?int $onlyResourceId): array
    {
        $zone = $this->zone($context['business']);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return [];
        }
        $service = $context['service'];
        $duration = (int) $service['duration_min'];
        $before = (int) $service['buffer_before'];
        $after = (int) $service['buffer_after'];
        $step = max(5, (int) $context['business']['slot_minutes']);
        $utc = new DateTimeZone('UTC');
        $slots = [];

        foreach ($context['staff'] as $person) {
            foreach ($this->windows($context['hours'], $person, $date, $zone) as [$windowStart, $windowEnd]) {
                for ($cursor = $windowStart; ; $cursor = $cursor->modify('+' . $step . ' minutes')) {
                    $end = $cursor->modify('+' . $duration . ' minutes');
                    if ($end > $windowEnd) {
                        break;
                    }
                    $blockStart = $cursor->modify('-' . $before . ' minutes');
                    $blockEnd = $end->modify('+' . $after . ' minutes');
                    if ($blockStart < $windowStart || $blockEnd > $windowEnd) {
                        continue;
                    }
                    $startUtc = $cursor->setTimezone($utc);
                    if (!$includePast && $startUtc <= $now) {
                        continue;
                    }
                    $blockStartUtc = $blockStart->setTimezone($utc);
                    $blockEndUtc = $blockEnd->setTimezone($utc);
                    if ($this->staffBusy($context['busy'], (int) $person['id'], $blockStartUtc, $blockEndUtc)) {
                        continue;
                    }
                    if ($this->closed($context['closures'], (int) $person['id'], $blockStartUtc, $blockEndUtc)) {
                        continue;
                    }
                    $resource = $this->pickResource($context['resources'], $context['busy'], $blockStartUtc, $blockEndUtc, $onlyResourceId);
                    if ($resource === false) {
                        continue;
                    }
                    [$quality, $reason] = $this->quality(
                        $context['busy'],
                        (int) $person['id'],
                        $windowStart,
                        $windowEnd,
                        $blockStartUtc,
                        $blockEndUtc,
                    );
                    [$price, $list, $label] = $this->price($context['rules'], $service, $cursor);
                    $slots[] = [
                        'staff_id' => (int) $person['id'],
                        'staff_name' => (string) $person['name'],
                        'staff_color' => (string) $person['color'],
                        'resource_id' => $resource === null ? null : (int) $resource['id'],
                        'resource_name' => $resource === null ? null : (string) $resource['name'],
                        'starts_at' => $startUtc,
                        'ends_at' => $end->setTimezone($utc),
                        'block_starts_at' => $blockStartUtc,
                        'block_ends_at' => $blockEndUtc,
                        'quality' => $quality,
                        'reason' => $reason,
                        'price_cents' => $price,
                        'list_price_cents' => $list,
                        'price_label' => $label,
                    ];
                }
            }
        }

        $rank = ['ideal' => 0, 'adjacent' => 1, 'open' => 2, 'fragment' => 3];
        usort($slots, static function (array $a, array $b) use ($rank): int {
            $byQuality = ($rank[$a['quality']] ?? 9) <=> ($rank[$b['quality']] ?? 9);
            if ($byQuality !== 0) {
                return $byQuality;
            }
            $byTime = $a['starts_at'] <=> $b['starts_at'];

            return $byTime !== 0 ? $byTime : ($a['staff_name'] <=> $b['staff_name']);
        });

        return $slots;
    }

    /**
     * @param list<array<string, mixed>> $hours
     * @param array<string, mixed> $person
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>
     */
    private function windows(array $hours, array $person, string $date, DateTimeZone $zone): array
    {
        $weekday = (int) (new DateTimeImmutable($date . ' 00:00:00', $zone))->format('N');
        $own = array_values(array_filter(
            $hours,
            static fn (array $row): bool => $row['staff_id'] !== null && (int) $row['staff_id'] === (int) $person['id'],
        ));
        $source = $own !== []
            ? $own
            : array_values(array_filter($hours, static fn (array $row): bool => $row['staff_id'] === null));
        $windows = [];
        foreach ($source as $row) {
            if ((int) $row['weekday'] !== $weekday) {
                continue;
            }
            $start = new DateTimeImmutable($date . ' ' . substr((string) $row['start_time'], 0, 8), $zone);
            $end = new DateTimeImmutable($date . ' ' . substr((string) $row['end_time'], 0, 8), $zone);
            if ($end > $start) {
                $windows[] = [$start, $end];
            }
        }
        usort($windows, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $windows;
    }

    /** @param list<array<string, mixed>> $busy */
    private function staffBusy(array $busy, int $staffId, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        foreach ($busy as $block) {
            if ((int) $block['staff_id'] !== $staffId) {
                continue;
            }
            $blockStart = $this->clock->parseUtc((string) $block['block_starts_at']);
            $blockEnd = $this->clock->parseUtc((string) $block['block_ends_at']);
            if ($this->overlaps($start, $end, $blockStart, $blockEnd)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string, mixed>> $closures */
    private function closed(array $closures, int $staffId, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        foreach ($closures as $closure) {
            if ($closure['staff_id'] !== null && (int) $closure['staff_id'] !== $staffId) {
                continue;
            }
            $closureStart = $this->clock->parseUtc((string) $closure['starts_at']);
            $closureEnd = $this->clock->parseUtc((string) $closure['ends_at']);
            if ($this->overlaps($start, $end, $closureStart, $closureEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $resources
     * @param list<array<string, mixed>> $busy
     * @return array<string, mixed>|null|false
     */
    private function pickResource(array $resources, array $busy, DateTimeImmutable $start, DateTimeImmutable $end, ?int $onlyResourceId): array|null|false
    {
        if ($resources === []) {
            return $onlyResourceId === null ? null : false;
        }
        foreach ($resources as $resource) {
            if ($onlyResourceId !== null && (int) $resource['id'] !== $onlyResourceId) {
                continue;
            }
            $used = 0;
            foreach ($busy as $block) {
                if ($block['resource_id'] === null || (int) $block['resource_id'] !== (int) $resource['id']) {
                    continue;
                }
                $blockStart = $this->clock->parseUtc((string) $block['block_starts_at']);
                $blockEnd = $this->clock->parseUtc((string) $block['block_ends_at']);
                if ($this->overlaps($start, $end, $blockStart, $blockEnd)) {
                    $used++;
                }
            }
            if ($used < (int) $resource['capacity']) {
                return $resource;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $busy
     * @return array{0: string, 1: string}
     */
    private function quality(
        array $busy,
        int $staffId,
        DateTimeImmutable $windowStart,
        DateTimeImmutable $windowEnd,
        DateTimeImmutable $blockStart,
        DateTimeImmutable $blockEnd,
    ): array {
        $previous = $windowStart->getTimestamp();
        $next = $windowEnd->getTimestamp();
        $start = $blockStart->getTimestamp();
        $end = $blockEnd->getTimestamp();
        foreach ($busy as $block) {
            if ((int) $block['staff_id'] !== $staffId) {
                continue;
            }
            $busyStart = $this->clock->parseUtc((string) $block['block_starts_at'])->getTimestamp();
            $busyEnd = $this->clock->parseUtc((string) $block['block_ends_at'])->getTimestamp();
            if ($busyEnd <= $start && $busyEnd > $previous) {
                $previous = $busyEnd;
            }
            if ($busyStart >= $end && $busyStart < $next) {
                $next = $busyStart;
            }
        }
        $before = (int) round(($start - $previous) / 60);
        $after = (int) round(($next - $end) / 60);
        if ($before === 0 && $after === 0) {
            return ['ideal', 'Riempie esattamente il buco tra due impegni.'];
        }
        if (
            ($before > 0 && $before < Policy::FRAGMENT_MINUTES)
            || ($after > 0 && $after < Policy::FRAGMENT_MINUTES)
        ) {
            return ['fragment', 'Lascia uno scarto sotto i 30 minuti, difficile da rivendere.'];
        }
        if ($before === 0 || $after === 0) {
            return ['adjacent', 'Si attacca a un impegno già in agenda, senza aprire un nuovo intervallo.'];
        }

        return ['open', 'Orario libero, lontano dagli altri impegni.'];
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, mixed> $service
     * @return array{0: int, 1: int, 2: string}
     */
    private function price(array $rules, array $service, DateTimeImmutable $localStart): array
    {
        $list = (int) $service['price_cents'];
        $weekday = (int) $localStart->format('N');
        $time = $localStart->format('H:i:s');
        $best = null;
        foreach ($rules as $rule) {
            if ((int) $rule['active'] !== 1) {
                continue;
            }
            if ($rule['service_id'] !== null && (int) $rule['service_id'] !== (int) $service['id']) {
                continue;
            }
            if ($rule['weekday'] !== null && (int) $rule['weekday'] !== $weekday) {
                continue;
            }
            $ruleStart = substr((string) $rule['start_time'], 0, 8);
            $ruleEnd = substr((string) $rule['end_time'], 0, 8);
            if ($time < $ruleStart || $time >= $ruleEnd) {
                continue;
            }
            if ($best === null || (int) $rule['discount_percent'] > (int) $best['discount_percent']) {
                $best = $rule;
            }
        }
        if ($best === null) {
            return [$list, $list, ''];
        }
        $discounted = (int) round($list * (100 - (int) $best['discount_percent']) / 100);

        return [$discounted, $list, (string) $best['name']];
    }

    private function overlaps(DateTimeImmutable $a0, DateTimeImmutable $a1, DateTimeImmutable $b0, DateTimeImmutable $b1): bool
    {
        return $a0->getTimestamp() < $b1->getTimestamp() && $b0->getTimestamp() < $a1->getTimestamp();
    }

    /** @param list<array{0: int, 1: int}> $intervals */
    private function merge(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }
        usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [array_first($intervals)];
        foreach ($intervals as $interval) {
            $last = $merged[count($merged) - 1];
            if ($interval[0] <= $last[1]) {
                $merged[count($merged) - 1] = [$last[0], max($last[1], $interval[1])];
            } else {
                $merged[] = $interval;
            }
        }

        return $merged;
    }

    private function minutes(string $time): int
    {
        $parts = explode(':', substr($time, 0, 5));

        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }
}
