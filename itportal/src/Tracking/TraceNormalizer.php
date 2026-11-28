<?php

declare(strict_types=1);

namespace ItPortal\Tracking;

/**
 * Small shared helpers for tracking (replaces bits of cleanString / convertDate clutter).
 */
final class TraceNormalizer
{
    public function serial(string $value): string
    {
        return strtoupper(trim($value));
    }

    public function server(string $value): string
    {
        $server = strtoupper(trim($value));
        $allowed = [
            'CFP1', 'NODO1', 'NODO2', 'NODO3', 'BREED',
            'NODO0_DB_HISTORIC_ONLINE', 'NODO0_DB_HISTORICAL',
            'NODO0_DB_KSS_OFFLINE', 'NODO0_DB_KSS_ONLINE',
            'NODO0_MYSQL_MIGR', 'NODO0_MYSQL_MIGR_ONLINE', 'NODO0_MYSQL_MIGR_ONLINE2007',
        ];

        return in_array($server, $allowed, true) ? $server : 'CFP1';
    }
}
