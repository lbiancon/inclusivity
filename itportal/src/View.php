<?php

declare(strict_types=1);

namespace ItPortal;

use ItPortal\Auth\AuthService;
use ItPortal\Auth\Session;
use ItPortal\Menu\MenuBuilder;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $root = Config::root();
        $path = $root . '/templates/' . ltrim($template, '/') . '.php';
        if (!is_file($path)) {
            http_response_code(500);
            echo 'Template not found: ' . htmlspecialchars($template);
            return;
        }

        extract($data, EXTR_SKIP);
        require $path;
    }

    public static function layout(
        string $contentTemplate,
        AuthService $auth,
        Session $session,
        string $active = '',
        string $title = '',
        array $data = [],
    ): void {
        $menu = (new MenuBuilder($session))->visibleSections($active);
        $appName = (string) Config::get('app.name', 'JSS IT Portal');
        $version = (string) Config::get('app.version', '4.0.0');
        $user = $session->user();
        $flash = $session->flash('auth_message');
        $flashType = $session->flash('auth_type') ?? 'info';

        self::render('layout', array_merge($data, [
            'contentTemplate' => $contentTemplate,
            'auth' => $auth,
            'session' => $session,
            'menu' => $menu,
            'active' => $active,
            'title' => $title !== '' ? $title : $appName,
            'appName' => $appName,
            'version' => $version,
            'user' => $user,
            'flash' => $flash,
            'flashType' => $flashType,
            'loggedIn' => $session->isLoggedIn(),
        ]));
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
