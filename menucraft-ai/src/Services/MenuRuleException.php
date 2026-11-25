<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use RuntimeException;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class MenuRuleException extends RuntimeException
{
    /**
     * @param list<string> $dettagli
     */
    public function __construct(
        private readonly string $codice,
        string $messaggio,
        private readonly int $httpStatus = 422,
        private readonly array $dettagli = [],
    ) {
        parent::__construct($messaggio);
    }

    public function codice(): string
    {
        return $this->codice;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /** @return list<string> */
    public function dettagli(): array
    {
        return $this->dettagli;
    }
}
