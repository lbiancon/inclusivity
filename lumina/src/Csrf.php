<?php

declare(strict_types=1);

namespace Lumina;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public static function check(): void
    {
        $sent = $_POST['_csrf'] ?? '';
        $known = $_SESSION['csrf'] ?? '';
        if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
            throw new HttpException(419, 'La sessione è scaduta. Ricarica la pagina e riprova.');
        }
    }
}
