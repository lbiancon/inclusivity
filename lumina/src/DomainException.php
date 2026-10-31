<?php

declare(strict_types=1);

namespace Lumina;

final class DomainException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }
}
