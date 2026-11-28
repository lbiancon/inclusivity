<?php

declare(strict_types=1);

namespace ItPortal\Auth;

final class Session
{
    private int $timeout;

    private function __construct(int $timeout)
    {
        $this->timeout = $timeout;
    }

    public static function start(int $timeout = 900): self
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        $session = new self($timeout);
        $session->enforceTimeout();

        return $session;
    }

    private function enforceTimeout(): void
    {
        if (!$this->isLoggedIn()) {
            return;
        }

        $last = (int) ($this->get('timestamp') ?? 0);
        if ($last > 0 && (time() - $last) > $this->timeout) {
            $this->destroy();
            return;
        }

        $this->set('timestamp', time());
    }

    public function isLoggedIn(): bool
    {
        $login = (string) ($this->get('login') ?? '');
        return $login !== '';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function putMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    public function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    /** @return array{login:string,emplId:string,nome:string,cognome:string,superuser:string,empQuaCode:string,roles:string} */
    public function user(): array
    {
        return [
            'login' => (string) ($this->get('login') ?? ''),
            'emplId' => (string) ($this->get('emplId') ?? ''),
            'nome' => (string) ($this->get('nome') ?? ''),
            'cognome' => (string) ($this->get('cognome') ?? ''),
            'superuser' => (string) ($this->get('superuser') ?? ''),
            'empQuaCode' => (string) ($this->get('empQuaCode') ?? ''),
            'roles' => (string) ($this->get('roles') ?? ''),
        ];
    }
}
