<?php

declare(strict_types=1);

namespace MenuCraft\Models;

use DateTimeImmutable;
use MenuCraft\Enums\PortataType;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

readonly class RigaMenu
{
    public function __construct(
        public DateTimeImmutable $data,
        public string $codice,
        public string $nome,
        public PortataType $categoria,
        public string $prezzo,
        public int $riga,
    ) {
    }
}
