<?php

declare(strict_types=1);

namespace ItPortal\Auth;

final class AuthResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $message = '',
        public readonly string $type = 'danger',
        public readonly string $redirect = '/index.php',
    ) {
    }

    public static function success(string $redirect = '/index.php', string $message = 'Signed in'): self
    {
        return new self(true, $message, 'success', $redirect);
    }

    public static function fail(string $message, string $type = 'danger'): self
    {
        return new self(false, $message, $type);
    }
}
