<?php

declare(strict_types=1);

namespace ItPortal\Auth;

/**
 * Local demo users mirroring lot_employee fields.
 * Used when AUTH_DRIVER=mock (no Oracle required).
 */
final class MockEmployeeRepository implements EmployeeRepositoryInterface
{
    /** @var array<string, UserRecord> */
    private array $users;

    public function __construct()
    {
        $this->users = [
            '12345' => new UserRecord('12345', '00001', 'Rossi', 'Mario', 'Y', 'P', 'SU'),
            '71875' => new UserRecord('71875', '71875', 'Quality', 'Demo', 'Y', 'Q', 'QA'),
            'guard' => new UserRecord('guard', '00999', 'Porta', 'Anna', 'N', 'G', 'GUA'),
            'demo' => new UserRecord('demo', '00014', 'Bianconi', 'Luca', 'Y', 'P', 'SU'),
        ];
    }

    public function findByBadge(string $badge): ?UserRecord
    {
        return $this->users[$badge] ?? null;
    }
}
