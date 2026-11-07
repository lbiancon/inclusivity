<?php
declare(strict_types=1);

final class LabException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
