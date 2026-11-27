<?php

declare(strict_types=1);

namespace ItPortal\Auth;

interface EmployeeRepositoryInterface
{
    public function findByBadge(string $badge): ?UserRecord;
}
