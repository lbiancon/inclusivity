<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function euro(int $cents): string
{
    return number_format($cents / 100, 2, ',', '.') . ' €';
}

function parse_money(string $raw): int
{
    $raw = trim(str_replace(['€', ' '], '', $raw));
    if (preg_match('/^\d{1,6}$/', $raw) === 1) {
        return (int) $raw * 100;
    }
    if (preg_match('/^\d{1,6}[.,]\d{1,2}$/', $raw) === 1) {
        $normalized = str_replace(',', '.', $raw);
        return (int) round((float) $normalized * 100);
    }
    throw new Lumina\DomainException('validation', 'Importo non valido. Usa un numero, per esempio 35 o 35,50.');
}

function slugify(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $raw = is_string($ascii) && $ascii !== '' ? $ascii : $value;
    $slugged = mb_strtolower($raw, 'UTF-8')
        |> (static fn (string $s): string => preg_replace('/[^a-z0-9]+/', '-', $s) ?? '');
    $slugged = trim($slugged, '-');

    return $slugged !== '' ? $slugged : 'attivita';
}

function dt(?string $utcSql, string $timezone, string $pattern = 'd MMM y, HH:mm'): string
{
    if ($utcSql === null || $utcSql === '') {
        return '';
    }
    $instant = new DateTimeImmutable($utcSql, new DateTimeZone('UTC'));
    $formatter = new IntlDateFormatter(
        'it_IT',
        IntlDateFormatter::NONE,
        IntlDateFormatter::NONE,
        $timezone,
        IntlDateFormatter::GREGORIAN,
        $pattern,
    );
    $formatted = $formatter->format($instant);

    return $formatted !== false ? $formatted : $instant->setTimezone(new DateTimeZone($timezone))->format('d/m/Y H:i');
}

function category_label(string $category): string
{
    return Lumina\Category::tryFrom($category)?->label() ?? 'Attività';
}

function quality_label(string $quality): string
{
    return match ($quality) {
        'ideal' => 'Ideale',
        'adjacent' => 'Aderente',
        'fragment' => 'Spezzato',
        default => 'Libero',
    };
}

function appointment_status_label(string $status): string
{
    return match ($status) {
        'confirmed' => 'Confermato',
        'arrived' => 'Arrivato',
        'in_progress' => 'In corso',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
        'no_show' => 'Assente',
        default => $status,
    };
}

function lumina_base(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(dirname($script), '/');
    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -strlen('/public'));
    }
    if ($dir === '' || $dir === '/' || $dir === '.') {
        return '';
    }

    return $dir;
}

function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';
    $base = lumina_base();
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base));
    }
    if ($path === '/public' || str_starts_with($path, '/public/')) {
        $path = substr($path, strlen('/public')) ?: '/';
    }
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    return $path;
}

function url(string $path): string
{
    if (preg_match('#\Ahttps?://#', $path) === 1) {
        return $path;
    }
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    return lumina_base() . $path;
}

function go(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function lumina_private_request(): bool
{
    $addr = strtolower(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
    if (str_starts_with($addr, '::ffff:')) {
        $addr = substr($addr, strlen('::ffff:'));
    }
    $addr = trim($addr, '[]');
    if ($addr === ''
        || $addr === '127.0.0.1'
        || $addr === '::1'
        || $addr === '0:0:0:0:0:0:0:1'
        || str_starts_with($addr, '10.')
        || str_starts_with($addr, '192.168.')
        || str_starts_with($addr, '172.')
    ) {
        return true;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $host = trim($host, '[]');

    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
}

function fail_page(int $status, string $message): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    if (strlen($message) < 1024) {
        $message .= "\n\nChrome, con un errore più corto di 512 byte, nasconde questo testo e mostra «Questa pagina non funziona».\n";
        $message .= str_repeat(" \n", 500);
    }
    echo $message;
    exit;
}

function path_is(string $prefix): bool
{
    $path = request_path();
    if ($prefix === '/app') {
        return $path === '/app';
    }

    return $path === $prefix || str_starts_with($path, $prefix . '/');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Lumina\Csrf::token()) . '">';
}

function redirect_field(string $path): string
{
    return '<input type="hidden" name="redirect" value="' . e($path) . '">';
}

function flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function pull_flash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function form_state(): array
{
    static $state = null;
    if ($state === null) {
        $state = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
        if (!is_array($state)) {
            $state = [];
        }
    }

    return $state;
}

function field(string $key, string $default = ''): string
{
    $state = form_state();

    return (string) ($state[$key] ?? $default);
}

function checked(string $key, string $expected = '1'): string
{
    return field($key) === $expected ? ' checked' : '';
}

function selected(string $key, string $expected, string $default = ''): string
{
    return field($key, $default) === $expected ? ' selected' : '';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($letters, 'UTF-8') >= 2) {
            break;
        }
    }

    return $letters !== '' ? $letters : '•';
}

function first_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];

    return (string) ($parts[0] ?? $name);
}

function safe_path(string $path): ?string
{
    if (preg_match('#\A/[A-Za-z0-9/_\-.?=&%]*\z#', $path) !== 1 || str_contains($path, '//')) {
        return null;
    }

    return $path;
}

function here(): string
{
    return safe_path((string) ($_SERVER['REQUEST_URI'] ?? '/')) ?? '/';
}

function valid_date(string $value): ?string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $parsed !== false && $parsed->format('Y-m-d') === $value ? $value : null;
}

function hex_color(mixed $value): string
{
    $color = (string) $value;

    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : '#1f6b45';
}

function weekday_name(int $iso): string
{
    return [
        1 => 'Lunedì',
        2 => 'Martedì',
        3 => 'Mercoledì',
        4 => 'Giovedì',
        5 => 'Venerdì',
        6 => 'Sabato',
        7 => 'Domenica',
    ][$iso] ?? '';
}

function csv_cell(string $value): string
{
    if (preg_match('/^[=+\-@]/', $value) === 1) {
        $value = "'" . $value;
    }

    return '"' . str_replace('"', '""', $value) . '"';
}
