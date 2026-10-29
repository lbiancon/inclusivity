<?php

declare(strict_types=1);

namespace Lumina;

final class View
{
    public static function render(string $name, array $data = [], ?string $layout = null): void
    {
        $layout ??= match (true) {
            str_starts_with($name, 'app/') => 'layouts/app',
            str_starts_with($name, 'portal/') => 'layouts/portal',
            default => 'layouts/public',
        };
        $data['content'] = self::capture($name, $data);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        extract($data, EXTR_SKIP);
        require self::path($layout);
    }

    public static function partial(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require self::path($name);
    }

    private static function capture(string $name, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require self::path($name);

        return (string) ob_get_clean();
    }

    private static function path(string $name): string
    {
        $path = LUMINA_ROOT . '/templates/' . $name . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('Template mancante: ' . $name);
        }

        return $path;
    }
}
