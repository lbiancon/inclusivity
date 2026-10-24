<?php

declare(strict_types=1);

namespace Lumina;

use PDO;

final class Auth
{
    private const DUMMY_HASH = '$2y$12$XzY5VW3d5PE9o7j8/5rg5eqjBdK7/SVVROI2QWKPEWgfqF.fBAgCq';

    public static function user(PDO $pdo): ?array
    {
        $id = $_SESSION['uid'] ?? null;
        if (!is_int($id) && !is_string($id)) {
            return null;
        }
        $user = Db::one($pdo, 'SELECT id, name, email, phone, role FROM users WHERE id = :id', ['id' => (int) $id]);

        return $user;
    }

    public static function attempt(PDO $pdo, string $email, string $password): ?array
    {
        $user = Db::one($pdo, 'SELECT * FROM users WHERE email = :email', ['email' => mb_strtolower(trim($email), 'UTF-8')]);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        if (!is_string($hash) || $hash === '' || !password_verify($password, $hash)) {
            return null;
        }
        if ($user === null) {
            return null;
        }
        if ($user['email_verified_at'] === null) {
            throw new DomainException('validation', 'Questa email non è ancora confermata. Chiedi il codice dalla registrazione.');
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        unset($user['password_hash']);

        return $user;
    }

    public static function loginId(int $id): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $id;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    public static function requireUser(PDO $pdo): array
    {
        $user = self::user($pdo);
        if ($user === null) {
            $_SESSION['after_login'] = request_path();
            go('/accedi');
            exit;
        }

        return $user;
    }
}
