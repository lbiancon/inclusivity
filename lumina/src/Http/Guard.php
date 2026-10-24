<?php

declare(strict_types=1);

namespace Lumina\Http;

use Lumina\App;
use Lumina\Auth;
use Lumina\Db;
use Lumina\HttpException;
use Lumina\View;

final class Guard
{
    public static function user(): array
    {
        return Auth::requireUser(App::$pdo);
    }

    /** @return array{user: array, business: array, staff: ?array} */
    public static function workspace(bool $ownerOnly = false): array
    {
        $user = self::user();
        $business = Db::one(App::$pdo, 'SELECT * FROM businesses WHERE owner_user_id = :id', ['id' => (int) $user['id']]);
        $staff = null;
        if ($business === null) {
            $staff = Db::one(App::$pdo, 'SELECT * FROM staff WHERE user_id = :id AND active = 1', ['id' => (int) $user['id']]);
            if ($staff !== null) {
                $business = Db::one(App::$pdo, 'SELECT * FROM businesses WHERE id = :id', ['id' => (int) $staff['business_id']]);
            }
        } else {
            $staff = Db::one(App::$pdo, 'SELECT * FROM staff WHERE user_id = :id', ['id' => (int) $user['id']]);
        }
        if ($business === null) {
            if ((string) $user['role'] === 'client') {
                go('/portale');
                exit;
            }
            throw new HttpException(403, 'Nessuna attività associata a questo account.');
        }
        if ($ownerOnly && (int) $business['owner_user_id'] !== (int) $user['id']) {
            throw new HttpException(403, 'Solo chi gestisce l\'attività può farlo.');
        }
        App::$bookings->sweep((int) $business['id']);

        return ['user' => $user, 'business' => $business, 'staff' => $staff];
    }

    public static function view(string $template, array $workspace, array $data = []): void
    {
        $data['user'] = $workspace['user'];
        $data['business'] = $workspace['business'];
        $data['staffProfile'] = $workspace['staff'];
        $data['unread'] = (int) Db::value(
            App::$pdo,
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user AND read_at IS NULL',
            ['user' => (int) $workspace['user']['id']],
        );
        $data['owner'] = (int) $workspace['business']['owner_user_id'] === (int) $workspace['user']['id'];
        $data['title'] = $data['title'] ?? 'Agenda';
        View::render($template, $data);
    }
}
