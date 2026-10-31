<?php

declare(strict_types=1);

namespace Lumina\Http;

use Lumina\App;
use Lumina\Auth;
use Lumina\Db;
use Lumina\DomainException;
use Lumina\HttpException;
use Lumina\Ics;
use Lumina\Input;
use Lumina\View;
use PDOException;

final class Front
{
    public function home(): void
    {
        $businesses = Db::all(
            App::$pdo,
            'SELECT b.*,
                    (SELECT COUNT(*) FROM services s WHERE s.business_id = b.id AND s.active = 1) AS service_count
             FROM businesses b
             ORDER BY b.name',
        );
        View::render('home', [
            'title' => 'Lumina — l\'agenda che protegge il margine',
            'businesses' => $businesses,
        ]);
    }

    public function login(): void
    {
        $email = Input::email('email');
        $password = Input::str('password', 200, true);
        $user = Auth::attempt(App::$pdo, $email, $password);
        if ($user === null) {
            throw new DomainException('validation', 'Email o password non corretti.');
        }
        $this->linkClients($user);
        $next = safe_path((string) ($_SESSION['after_login'] ?? ''));
        unset($_SESSION['after_login']);
        if ($next === null || $next === '/accedi') {
            $next = (string) $user['role'] === 'client' ? '/portale' : '/app';
        }
        go($next);
        exit;
    }

    public function logout(): void
    {
        Auth::logout();
        go('/');
        exit;
    }

    public function portal(): void
    {
        $user = Guard::user();
        $this->linkClients($user);
        $clients = $this->myClients($user);
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $clients);
        $upcoming = [];
        $past = [];
        $offers = [];
        $cards = [];
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = Db::all(
                App::$pdo,
                "SELECT a.*, s.name AS service_name, st.name AS staff_name, b.name AS business_name, b.timezone, b.slug
                 FROM appointments a
                 INNER JOIN services s ON s.id = a.service_id
                 INNER JOIN staff st ON st.id = a.staff_id
                 INNER JOIN businesses b ON b.id = a.business_id
                 WHERE a.client_id IN ($placeholders)
                 ORDER BY a.starts_at DESC
                 LIMIT 40",
                $ids,
            );
            $now = App::$clock->now();
            foreach ($rows as $row) {
                if ((string) $row['status'] === 'cancelled') {
                    $past[] = $row;
                    continue;
                }
                if (App::$clock->parseUtc((string) $row['starts_at']) >= $now) {
                    $upcoming[] = $row;
                } else {
                    $past[] = $row;
                }
            }
            $offers = Db::all(
                App::$pdo,
                "SELECT w.*, s.name AS service_name, b.name AS business_name, b.timezone
                 FROM waitlist w
                 INNER JOIN services s ON s.id = w.service_id
                 INNER JOIN businesses b ON b.id = w.business_id
                 WHERE w.client_id IN ($placeholders) AND w.status = 'offered'
                 ORDER BY w.offer_expires_at",
                $ids,
            );
            $cards = Db::all(
                App::$pdo,
                "SELECT cp.*, p.name, b.name AS business_name
                 FROM client_packages cp
                 INNER JOIN packages p ON p.id = cp.package_id
                 INNER JOIN clients c ON c.id = cp.client_id
                 INNER JOIN businesses b ON b.id = c.business_id
                 WHERE cp.client_id IN ($placeholders)
                 ORDER BY cp.expires_at",
                $ids,
            );
        }
        View::render('portal/home', [
            'title' => 'Le tue prenotazioni',
            'user' => $user,
            'upcoming' => $upcoming,
            'past' => array_slice($past, 0, 8),
            'offers' => $offers,
            'cards' => $cards,
        ]);
    }

    public function portalCancel(string $id): void
    {
        $user = Guard::user();
        $this->linkClients($user);
        $appointment = $this->ownedAppointment((int) $id, $user);
        App::$bookings->cancelAppointment((int) $appointment['id'], (int) $appointment['business_id'], false, Input::str('reason', 180));
        flash('ok', 'Prenotazione annullata.');
        go('/portale');
        exit;
    }

    public function portalAccept(string $id): void
    {
        $user = Guard::user();
        $this->linkClients($user);
        $offer = $this->ownedOffer((int) $id, $user);
        $appointment = App::$bookings->acceptOffer((int) $offer['id'], Input::flag('use_package') === 1);
        flash('ok', 'Posto confermato.');
        go('/p/' . $appointment['public_token']);
        exit;
    }

    public function portalIcs(string $id): void
    {
        $user = Guard::user();
        $this->linkClients($user);
        $appointment = $this->ownedAppointment((int) $id, $user);
        $this->sendIcs($appointment);
    }

    /** @param array<string, mixed> $user */
    private function linkClients(array $user): void
    {
        Db::run(
            App::$pdo,
            'UPDATE clients SET user_id = :user WHERE email = :email AND user_id IS NULL',
            ['user' => (int) $user['id'], 'email' => (string) $user['email']],
        );
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    private function myClients(array $user): array
    {
        return Db::all(
            App::$pdo,
            'SELECT * FROM clients WHERE user_id = :user OR email = :email',
            ['user' => (int) $user['id'], 'email' => (string) $user['email']],
        );
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    private function ownedAppointment(int $id, array $user): array
    {
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $this->myClients($user));
        $appointment = Db::one(App::$pdo, 'SELECT * FROM appointments WHERE id = :id', ['id' => $id]);
        if ($appointment === null || !in_array((int) $appointment['client_id'], $ids, true)) {
            throw new HttpException(404, 'Prenotazione non trovata.');
        }

        return $appointment;
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    private function ownedOffer(int $id, array $user): array
    {
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $this->myClients($user));
        $offer = Db::one(App::$pdo, 'SELECT * FROM waitlist WHERE id = :id', ['id' => $id]);
        if ($offer === null || !in_array((int) $offer['client_id'], $ids, true)) {
            throw new HttpException(404, 'Offerta non trovata.');
        }

        return $offer;
    }

    /** @param array<string, mixed> $appointment */
    private function sendIcs(array $appointment): void
    {
        $service = Db::one(App::$pdo, 'SELECT name FROM services WHERE id = :id', ['id' => (int) $appointment['service_id']]);
        $business = Db::one(App::$pdo, 'SELECT * FROM businesses WHERE id = :id', ['id' => (int) $appointment['business_id']]);
        $ics = Ics::event(
            'lumina-' . $appointment['public_token'] . '@lumina',
            App::$clock->parseUtc((string) $appointment['starts_at']),
            App::$clock->parseUtc((string) $appointment['ends_at']),
            (string) ($service['name'] ?? 'Appuntamento') . ' · ' . (string) ($business['name'] ?? ''),
            trim((string) ($business['address'] ?? '') . ' ' . (string) ($business['city'] ?? '')),
            'Prenotazione Lumina',
        );
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="lumina.ics"');
        echo $ics;
        exit;
    }
}
