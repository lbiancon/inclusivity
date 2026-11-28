<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

/**
 * @deprecated Use portal('tracking.serial') / SerialSearchService.
 * Kept as a thin alias so older calls during migration do not break.
 */
final class SerialTracker
{
    public function search(string $serial, string $server = 'CFP1', bool $parent = false): array
    {
        return portal('tracking.serial')->search($serial, $server, $parent);
    }
}
