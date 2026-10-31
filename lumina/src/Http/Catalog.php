<?php

declare(strict_types=1);

namespace Lumina\Http;

use Lumina\App;
use Lumina\Db;
use Lumina\DomainException;
use Lumina\Input;
use Lumina\Plan;
use PDOException;

final class Catalog
{
    public function services(): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        Guard::view('app/services', $workspace, [
            'title' => 'Servizi',
            'services' => Db::all(App::$pdo, 'SELECT * FROM services WHERE business_id = :id ORDER BY active DESC, name', ['id' => $businessId]),
            'staff' => Db::all(App::$pdo, 'SELECT * FROM staff WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => $businessId]),
            'resources' => Db::all(App::$pdo, 'SELECT * FROM resources WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => $businessId]),
            'links' => $this->links($businessId),
        ]);
    }

    public function saveService(?string $id = null): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        $serviceId = $id === null ? 0 : (int) $id;
        $kind = Input::choice('kind', ['appointment', 'class']);
        $capacity = $kind === 'class' ? Input::int('capacity', 1, 80) : 1;
        $payload = [
            'name' => Input::str('name', 140, true),
            'description' => Input::str('description', 500),
            'duration' => Input::int('duration_min', 5, 480),
            'before' => Input::int('buffer_before', 0, 120, false),
            'after' => Input::int('buffer_after', 0, 120, false),
            'price' => Input::money('price'),
            'kind' => $kind,
            'capacity' => $capacity,
            'active' => Input::flag('active'),
        ];
        if (mb_strlen($payload['name']) < 2) {
            throw new DomainException('validation', 'Il servizio ha bisogno di un nome.');
        }
        $staffIds = $this->ownedIds('staff', Input::ids('staff_ids'), $businessId);
        $resourceIds = $this->ownedIds('resources', Input::ids('resource_ids'), $businessId);
        App::$pdo->beginTransaction();
        try {
            if ($serviceId > 0) {
                $existing = Db::one(App::$pdo, 'SELECT id FROM services WHERE id = :id AND business_id = :business', ['id' => $serviceId, 'business' => $businessId]);
                if ($existing === null) {
                    throw new DomainException('not_found', 'Servizio non trovato.');
                }
                Db::run(App::$pdo, 'UPDATE services SET
                    name = :name, description = :description, duration_min = :duration, buffer_before = :before,
                    buffer_after = :after, price_cents = :price, kind = :kind, capacity = :capacity, active = :active
                    WHERE id = :id', $payload + ['id' => $serviceId]);
            } else {
                Db::run(App::$pdo, 'INSERT INTO services (
                    business_id, name, description, duration_min, buffer_before, buffer_after, price_cents, kind, capacity, active
                ) VALUES (
                    :business, :name, :description, :duration, :before, :after, :price, :kind, :capacity, :active
                )', $payload + ['business' => $businessId]);
                $serviceId = Db::id(App::$pdo);
            }
            Db::run(App::$pdo, 'DELETE FROM service_staff WHERE service_id = :id', ['id' => $serviceId]);
            Db::run(App::$pdo, 'DELETE FROM service_resources WHERE service_id = :id', ['id' => $serviceId]);
            foreach ($staffIds as $staffId) {
                Db::run(App::$pdo, 'INSERT INTO service_staff (service_id, staff_id) VALUES (:service, :staff)', ['service' => $serviceId, 'staff' => $staffId]);
            }
            foreach ($resourceIds as $resourceId) {
                Db::run(App::$pdo, 'INSERT INTO service_resources (service_id, resource_id) VALUES (:service, :resource)', ['service' => $serviceId, 'resource' => $resourceId]);
            }
            App::$pdo->commit();
        } catch (\Throwable $exception) {
            if (App::$pdo->inTransaction()) {
                App::$pdo->rollBack();
            }
            throw $exception;
        }
        flash('ok', 'Servizio salvato.');
        go('/app/servizi');
        exit;
    }

    public function staffPage(): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        $staff = Db::all(
            App::$pdo,
            'SELECT s.*, u.email FROM staff s LEFT JOIN users u ON u.id = s.user_id WHERE s.business_id = :id ORDER BY s.active DESC, s.name',
            ['id' => $businessId],
        );
        $hours = Db::all(App::$pdo, 'SELECT * FROM weekly_hours WHERE business_id = :id AND staff_id IS NOT NULL', ['id' => $businessId]);
        Guard::view('app/staff', $workspace, [
            'title' => 'Persone',
            'staff' => $staff,
            'hours' => $hours,
            'resources' => Db::all(App::$pdo, 'SELECT * FROM resources WHERE business_id = :id ORDER BY active DESC, name', ['id' => $businessId]),
        ]);
    }

    public function saveStaff(?string $id = null): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        $staffId = $id === null ? 0 : (int) $id;
        $name = Input::str('name', 120, true);
        if (mb_strlen($name) < 2) {
            throw new DomainException('validation', 'Scrivi il nome.');
        }
        $payload = [
            'name' => $name,
            'title' => Input::str('title', 80),
            'bio' => Input::str('bio', 400),
            'color' => Input::color('color'),
            'active' => Input::flag('active'),
        ];
        App::$pdo->beginTransaction();
        try {
            if ($staffId === 0) {
                Plan::assertStaff(App::$pdo, $businessId, true);
                $userId = $this->maybeUser(null);
                Db::run(App::$pdo, 'INSERT INTO staff (business_id, user_id, name, title, bio, color, active) VALUES (:business, :user, :name, :title, :bio, :color, :active)', $payload + [
                    'business' => $businessId,
                    'user' => $userId,
                ]);
            } else {
                $existing = Db::one(App::$pdo, 'SELECT * FROM staff WHERE id = :id AND business_id = :business', ['id' => $staffId, 'business' => $businessId]);
                if ($existing === null) {
                    throw new DomainException('not_found', 'Persona non trovata.');
                }
                if ((int) $existing['active'] !== 1 && (int) $payload['active'] === 1) {
                    Plan::assertStaff(App::$pdo, $businessId, true);
                }
                $userId = $this->maybeUser($existing['user_id'] !== null ? (int) $existing['user_id'] : null);
                Db::run(App::$pdo, 'UPDATE staff SET user_id = :user, name = :name, title = :title, bio = :bio, color = :color, active = :active WHERE id = :id', $payload + [
                    'user' => $userId ?? ($existing['user_id'] !== null ? (int) $existing['user_id'] : null),
                    'id' => $staffId,
                ]);
            }
            App::$pdo->commit();
        } catch (PDOException $exception) {
            if (App::$pdo->inTransaction()) {
                App::$pdo->rollBack();
            }
            if (Db::duplicate($exception)) {
                throw new DomainException('validation', 'Email già usata da un altro account.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if (App::$pdo->inTransaction()) {
                App::$pdo->rollBack();
            }
            throw $exception;
        }
        flash('ok', 'Persona salvata.');
        go('/app/staff');
        exit;
    }

    public function saveHours(string $id): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        $staffId = (int) $id;
        $existing = Db::one(App::$pdo, 'SELECT id FROM staff WHERE id = :id AND business_id = :business', ['id' => $staffId, 'business' => $businessId]);
        if ($existing === null) {
            throw new DomainException('not_found', 'Persona non trovata.');
        }
        $custom = Input::flag('custom') === 1;
        $rows = $custom ? $this->shifts() : [];
        App::$pdo->beginTransaction();
        try {
            Db::run(App::$pdo, 'DELETE FROM weekly_hours WHERE staff_id = :staff', ['staff' => $staffId]);
            foreach ($rows as $row) {
                Db::run(App::$pdo, 'INSERT INTO weekly_hours (business_id, staff_id, weekday, start_time, end_time) VALUES (:business, :staff, :weekday, :start_time, :end_time)', [
                    'business' => $businessId,
                    'staff' => $staffId,
                    'weekday' => $row['weekday'],
                    'start_time' => $row['start'],
                    'end_time' => $row['end'],
                ]);
            }
            App::$pdo->commit();
        } catch (\Throwable $exception) {
            if (App::$pdo->inTransaction()) {
                App::$pdo->rollBack();
            }
            throw $exception;
        }
        flash('ok', $custom ? 'Turno personale salvato.' : 'Usa di nuovo gli orari dell\'attività.');
        go('/app/staff');
        exit;
    }

    public function saveResource(?string $id = null): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        $resourceId = $id === null ? 0 : (int) $id;
        $payload = [
            'name' => Input::str('name', 120, true),
            'kind' => Input::choice('kind', ['chair', 'room', 'equipment', 'other']),
            'capacity' => Input::int('capacity', 1, 40),
            'active' => Input::flag('active'),
        ];
        if ($resourceId === 0) {
            Db::run(App::$pdo, 'INSERT INTO resources (business_id, name, kind, capacity, active) VALUES (:business, :name, :kind, :capacity, :active)', $payload + ['business' => $businessId]);
        } else {
            $existing = Db::one(App::$pdo, 'SELECT id FROM resources WHERE id = :id AND business_id = :business', ['id' => $resourceId, 'business' => $businessId]);
            if ($existing === null) {
                throw new DomainException('not_found', 'Postazione non trovata.');
            }
            Db::run(App::$pdo, 'UPDATE resources SET name = :name, kind = :kind, capacity = :capacity, active = :active WHERE id = :id', $payload + ['id' => $resourceId]);
        }
        flash('ok', 'Postazione salvata.');
        go('/app/staff');
        exit;
    }

    /** @return array{staff: array<int, list<int>>, resources: array<int, list<int>>} */
    private function links(int $businessId): array
    {
        $staff = [];
        foreach (Db::all(App::$pdo, 'SELECT ss.service_id, ss.staff_id FROM service_staff ss INNER JOIN services s ON s.id = ss.service_id WHERE s.business_id = :id', ['id' => $businessId]) as $row) {
            $staff[(int) $row['service_id']][] = (int) $row['staff_id'];
        }
        $resources = [];
        foreach (Db::all(App::$pdo, 'SELECT sr.service_id, sr.resource_id FROM service_resources sr INNER JOIN services s ON s.id = sr.service_id WHERE s.business_id = :id', ['id' => $businessId]) as $row) {
            $resources[(int) $row['service_id']][] = (int) $row['resource_id'];
        }

        return ['staff' => $staff, 'resources' => $resources];
    }

    /** @param list<int> $ids @return list<int> */
    private function ownedIds(string $table, array $ids, int $businessId): array
    {
        if ($ids === []) {
            return [];
        }
        if (!in_array($table, ['staff', 'resources'], true)) {
            return [];
        }
        $owned = [];
        foreach ($ids as $id) {
            $row = Db::one(App::$pdo, "SELECT id FROM {$table} WHERE id = :id AND business_id = :business", ['id' => $id, 'business' => $businessId]);
            if ($row !== null) {
                $owned[] = $id;
            }
        }

        return $owned;
    }

    private function maybeUser(?int $existingUserId): ?int
    {
        $email = Input::email('email', false);
        $password = Input::str('password', 200);
        if ($existingUserId === null && $email === '') {
            return null;
        }
        if ($existingUserId === null) {
            if (mb_strlen($password) < 10) {
                throw new DomainException('validation', 'Per l\'accesso serve una password di almeno 10 caratteri.');
            }
            Db::run(App::$pdo, 'INSERT INTO users (name, email, password_hash, role, email_verified_at) VALUES (:name, :email, :hash, :role, :verified)', [
                'name' => Input::str('name', 120, true),
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'staff',
                'verified' => App::$clock->sql(App::$clock->now()),
            ]);

            return Db::id(App::$pdo);
        }
        if ($password !== '') {
            if (mb_strlen($password) < 10) {
                throw new DomainException('validation', 'La password deve avere almeno 10 caratteri.');
            }
            Db::run(App::$pdo, 'UPDATE users SET password_hash = :hash WHERE id = :id', [
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $existingUserId,
            ]);
        }

        return $existingUserId;
    }

    /** @return list<array{weekday: int, start: string, end: string}> */
    public function shiftsPublic(): array
    {
        return $this->shifts();
    }

    /** @return list<array{weekday: int, start: string, end: string}> */
    private function shifts(): array
    {
        $rows = [];
        for ($day = 1; $day <= 7; $day++) {
            foreach (['a', 'b'] as $shift) {
                $start = trim((string) ($_POST[$day . '_' . $shift . '_start'] ?? ''));
                $end = trim((string) ($_POST[$day . '_' . $shift . '_end'] ?? ''));
                if ($start === '' && $end === '') {
                    continue;
                }
                if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end) !== 1 || $end <= $start) {
                    throw new DomainException('validation', 'Controlla i turni: ogni fascia deve avere un inizio e una fine successivi.');
                }
                $rows[] = ['weekday' => $day, 'start' => $start . ':00', 'end' => $end . ':00'];
            }
        }

        return $rows;
    }
}
