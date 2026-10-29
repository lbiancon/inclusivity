<?php

declare(strict_types=1);

namespace Lumina\Http;

use Lumina\App;
use Lumina\Db;
use Lumina\DomainException;
use Lumina\HttpException;
use Lumina\Input;
use PDOException;

final class People
{
    public function index(): void
    {
        $workspace = Guard::workspace();
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT * FROM clients WHERE business_id = :business';
        $params = ['business' => (int) $workspace['business']['id']];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $sql .= ' AND (name LIKE :q_name OR email LIKE :q_email OR phone LIKE :q_phone)';
            $params['q_name'] = $like;
            $params['q_email'] = $like;
            $params['q_phone'] = $like;
        }
        $sql .= ' ORDER BY name LIMIT 80';
        Guard::view('app/clients', $workspace, [
            'title' => 'Clienti',
            'clients' => Db::all(App::$pdo, $sql, $params),
            'q' => $q,
        ]);
    }

    public function show(string $id): void
    {
        $workspace = Guard::workspace();
        $client = $this->client((int) $id, (int) $workspace['business']['id']);
        $score = App::$bookings->reliability((int) $client['id'], (int) $workspace['business']['reliability_threshold']);
        Guard::view('app/client', $workspace, [
            'title' => $client['name'],
            'client' => $client,
            'score' => $score,
            'staff' => Db::all(App::$pdo, 'SELECT id, name FROM staff WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => (int) $workspace['business']['id']]),
            'history' => Db::all(
                App::$pdo,
                'SELECT a.*, s.name AS service_name, st.name AS staff_name
                 FROM appointments a
                 INNER JOIN services s ON s.id = a.service_id
                 INNER JOIN staff st ON st.id = a.staff_id
                 WHERE a.client_id = :client
                 ORDER BY a.starts_at DESC LIMIT 20',
                ['client' => (int) $client['id']],
            ),
            'cards' => Db::all(
                App::$pdo,
                'SELECT cp.*, p.name FROM client_packages cp INNER JOIN packages p ON p.id = cp.package_id WHERE cp.client_id = :client ORDER BY cp.expires_at DESC',
                ['client' => (int) $client['id']],
            ),
            'packages' => Db::all(App::$pdo, 'SELECT * FROM packages WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => (int) $workspace['business']['id']]),
        ]);
    }

    public function save(?string $id = null): void
    {
        $workspace = Guard::workspace();
        $businessId = (int) $workspace['business']['id'];
        $clientId = $id === null ? 0 : (int) $id;
        $name = Input::str('name', 120, true);
        if (mb_strlen($name) < 2) {
            throw new DomainException('validation', 'Scrivi il nome.');
        }
        $email = Input::email('email', false);
        $preferred = Input::int('preferred_staff_id', 0, 999999999, false);
        if ($preferred > 0) {
            $staff = Db::one(App::$pdo, 'SELECT id FROM staff WHERE id = :id AND business_id = :business', ['id' => $preferred, 'business' => $businessId]);
            if ($staff === null) {
                $preferred = 0;
            }
        }
        $payload = [
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => Input::phone('phone'),
            'preferences' => Input::str('preferences', 300),
            'allergies' => Input::str('allergies', 300),
            'notes' => Input::str('notes', 2000),
            'preferred' => $preferred > 0 ? $preferred : null,
        ];
        try {
            if ($clientId === 0) {
                Db::run(App::$pdo, 'INSERT INTO clients (
                    business_id, name, email, phone, preferences, allergies, notes, preferred_staff_id, created_at
                ) VALUES (
                    :business, :name, :email, :phone, :preferences, :allergies, :notes, :preferred, :created
                )', $payload + ['business' => $businessId, 'created' => App::$clock->sql(App::$clock->now())]);
                $clientId = Db::id(App::$pdo);
            } else {
                $this->client($clientId, $businessId);
                Db::run(App::$pdo, 'UPDATE clients SET
                    name = :name, email = :email, phone = :phone, preferences = :preferences,
                    allergies = :allergies, notes = :notes, preferred_staff_id = :preferred
                    WHERE id = :id', $payload + ['id' => $clientId]);
            }
        } catch (PDOException $exception) {
            if (Db::duplicate($exception)) {
                throw new DomainException('validation', 'Esiste già un cliente con questa email.');
            }
            throw $exception;
        }
        flash('ok', 'Scheda aggiornata.');
        go('/app/clienti/' . $clientId);
        exit;
    }

    public function sell(string $id): void
    {
        $workspace = Guard::workspace();
        App::$bookings->sellPackage((int) $workspace['business']['id'], (int) $id, Input::int('package_id', 1, 999999999));
        flash('ok', 'Tessera attivata.');
        go('/app/clienti/' . (int) $id);
        exit;
    }

    /** @return array<string, mixed> */
    private function client(int $id, int $businessId): array
    {
        $client = Db::one(App::$pdo, 'SELECT * FROM clients WHERE id = :id AND business_id = :business', ['id' => $id, 'business' => $businessId]);
        if ($client === null) {
            throw new HttpException(404, 'Cliente non trovato.');
        }

        return $client;
    }
}
