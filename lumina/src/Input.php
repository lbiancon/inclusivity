<?php

declare(strict_types=1);

namespace Lumina;

final class Input
{
    public static function str(string $key, int $max, bool $required = false): string
    {
        $value = trim((string) ($_POST[$key] ?? ''));
        if ($required && $value === '') {
            throw new DomainException('validation', 'Compila tutti i campi obbligatori.');
        }
        if (mb_strlen($value) > $max) {
            throw new DomainException('validation', 'Un campo supera la lunghezza consentita.');
        }

        return $value;
    }

    public static function email(string $key, bool $required = true): string
    {
        $value = mb_strtolower(self::str($key, 190, $required), 'UTF-8');
        if ($value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('validation', 'Email non valida.');
        }

        return $value;
    }

    public static function phone(string $key): string
    {
        $value = self::str($key, 40);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[0-9+().\\/\\s-]{5,40}$/', $value) !== 1) {
            throw new DomainException('validation', 'Numero di telefono non valido.');
        }

        return $value;
    }

    public static function int(string $key, int $min, int $max, bool $required = true): int
    {
        $raw = trim((string) ($_POST[$key] ?? ''));
        if ($raw === '') {
            if ($required) {
                throw new DomainException('validation', 'Manca un valore numerico.');
            }

            return $min;
        }
        if (preg_match('/^-?\d+$/', $raw) !== 1) {
            throw new DomainException('validation', 'Valore numerico non valido.');
        }
        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            throw new DomainException('validation', 'Un valore numerico è fuori dall\'intervallo consentito.');
        }

        return $value;
    }

    public static function money(string $key): int
    {
        return parse_money(self::str($key, 20, true));
    }

    public static function choice(string $key, array $allowed): string
    {
        $value = self::str($key, 40, true);
        if (!in_array($value, $allowed, true)) {
            throw new DomainException('validation', 'Scelta non valida.');
        }

        return $value;
    }

    /** @return list<int> */
    public static function ids(string $key): array
    {
        $raw = $_POST[$key] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $item) {
            if (is_scalar($item) && preg_match('/^\d+$/', (string) $item) === 1) {
                $ids[] = (int) $item;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function color(string $key): string
    {
        $value = self::str($key, 7);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
            return '#1f6b45';
        }

        return strtolower($value);
    }

    public static function date(string $key): string
    {
        $value = self::str($key, 10, true);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new DomainException('validation', 'Data non valida.');
        }

        return $value;
    }

    public static function time(string $key): string
    {
        $value = self::str($key, 5, true);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            throw new DomainException('validation', 'Orario non valido.');
        }

        return $value;
    }

    public static function flag(string $key): int
    {
        return isset($_POST[$key]) && (string) $_POST[$key] === '1' ? 1 : 0;
    }
}
