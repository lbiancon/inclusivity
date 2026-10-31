<?php

declare(strict_types=1);

namespace Lumina;

use DateTimeImmutable;
use DateTimeZone;

final class Clock
{
    private DateTimeZone $utc;

    public function __construct(
        private DateTimeZone $zone = new DateTimeZone('Europe/Rome'),
        private ?DateTimeImmutable $frozen = null,
    ) {
        $this->utc = new DateTimeZone('UTC');
        if ($this->frozen !== null) {
            $this->frozen = $this->frozen->setTimezone($this->utc);
        }
    }

    public function zone(): DateTimeZone
    {
        return $this->zone;
    }

    public function freeze(DateTimeImmutable $instant): void
    {
        $this->frozen = $instant->setTimezone($this->utc);
    }

    public function now(): DateTimeImmutable
    {
        return $this->frozen ?? new DateTimeImmutable('now', $this->utc);
    }

    public function at(string $local, ?DateTimeZone $zone = null): DateTimeImmutable
    {
        return (new DateTimeImmutable($local, $zone ?? $this->zone))->setTimezone($this->utc);
    }

    public function local(DateTimeImmutable $instant, ?DateTimeZone $zone = null): DateTimeImmutable
    {
        return $instant->setTimezone($zone ?? $this->zone);
    }

    public function sql(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone($this->utc)->format('Y-m-d H:i:s');
    }

    public function today(?DateTimeZone $zone = null): string
    {
        return $this->local($this->now(), $zone)->format('Y-m-d');
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    public function dayBounds(string $date, ?DateTimeZone $zone = null): array
    {
        $zone ??= $this->zone;
        $start = new DateTimeImmutable($date . ' 00:00:00', $zone);

        return [$start->setTimezone($this->utc), $start->modify('+1 day')->setTimezone($this->utc)];
    }

    public function parseUtc(string $sql): DateTimeImmutable
    {
        return new DateTimeImmutable($sql, $this->utc);
    }
}
