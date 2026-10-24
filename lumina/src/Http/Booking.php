<?php

declare(strict_types=1);

namespace Lumina\Http;

use DateTimeImmutable;
use DateTimeZone;
use Lumina\App;
use Lumina\Auth;
use Lumina\Brand;
use Lumina\Db;
use Lumina\DomainException;
use Lumina\HttpException;
use Lumina\Ics;
use Lumina\Input;
use Lumina\View;

final class Booking
{
    public function show(string $slug): void
    {
        $business = $this->business($slug);
        App::$bookings->sweep((int) $business['id']);
        $zone = App::$engine->zone($business);
        $today = App::$clock->today($zone);
        $services = Db::all(
            App::$pdo,
            "SELECT * FROM services WHERE business_id = :id AND active = 1 AND kind = 'appointment' ORDER BY name",
            ['id' => (int) $business['id']],
        );
        $serviceId = (int) ($_GET['servizio'] ?? 0);
        $service = null;
        foreach ($services as $row) {
            if ((int) $row['id'] === $serviceId) {
                $service = $row;
            }
        }
        $staff = Db::all(App::$pdo, 'SELECT * FROM staff WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => (int) $business['id']]);
        $eligible = [];
        if ($service !== null) {
            $eligible = Db::all(
                App::$pdo,
                'SELECT s.* FROM staff s
                 INNER JOIN service_staff ss ON ss.staff_id = s.id
                 WHERE ss.service_id = :service AND s.active = 1
                 ORDER BY s.name',
                ['service' => (int) $service['id']],
            );
        }
        $staffId = (int) ($_GET['staff'] ?? 0);
        $anchor = valid_date((string) ($_GET['da'] ?? '')) ?? $today;
        $date = valid_date((string) ($_GET['data'] ?? '')) ?? $anchor;
        $user = Auth::user(App::$pdo);
        $email = '';
        if ($user !== null && (string) $user['role'] === 'client') {
            $email = (string) $user['email'];
        } elseif (filter_var((string) ($_GET['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $email = mb_strtolower((string) $_GET['email'], 'UTF-8');
        }
        $known = null;
        $cards = [];
        if ($email !== '') {
            $known = Db::one(
                App::$pdo,
                'SELECT id, name, preferred_staff_id FROM clients WHERE business_id = :business AND email = :email',
                ['business' => (int) $business['id'], 'email' => $email],
            );
            if ($known !== null && $service !== null) {
                $cards = Db::all(
                    App::$pdo,
                    'SELECT cp.credits_left, cp.expires_at, p.name
                     FROM client_packages cp
                     INNER JOIN packages p ON p.id = cp.package_id
                     WHERE cp.client_id = :client AND cp.credits_left > 0 AND cp.expires_at > :now
                       AND (p.service_id IS NULL OR p.service_id = :service)
                     ORDER BY cp.expires_at',
                    [
                        'client' => (int) $known['id'],
                        'now' => App::$clock->sql(App::$clock->now()),
                        'service' => (int) $service['id'],
                    ],
                );
            }
        }
        $dots = [];
        $slots = [];
        if ($service !== null) {
            $dots = App::$engine->dots((int) $business['id'], (int) $service['id'], $staffId > 0 ? $staffId : null, $anchor, 10);
            $slots = App::$engine->slots((int) $business['id'], (int) $service['id'], $date, $staffId > 0 ? $staffId : null);
        }
        $sessions = Db::all(
            App::$pdo,
            "SELECT s.*, sv.name AS service_name, st.name AS staff_name,
                    (SELECT COUNT(*) FROM class_bookings b WHERE b.session_id = s.id AND b.status = 'booked') AS taken
             FROM class_sessions s
             INNER JOIN services sv ON sv.id = s.service_id
             INNER JOIN staff st ON st.id = s.staff_id
             WHERE s.business_id = :business AND s.status = 'scheduled' AND s.starts_at >= :now
             ORDER BY s.starts_at
             LIMIT 8",
            ['business' => (int) $business['id'], 'now' => App::$clock->sql(App::$clock->now())],
        );
        View::render('booking/show', [
            'title' => $business['name'] . ' · Prenota',
            'business' => $business,
            'services' => $services,
            'service' => $service,
            'staff' => $staff,
            'eligible' => $eligible,
            'staffId' => $staffId,
            'anchor' => $anchor,
            'date' => $date,
            'dots' => $dots,
            'slots' => $this->grouped($slots),
            'slotCount' => count($slots),
            'sessions' => $sessions,
            'user' => $user,
            'email' => $email,
            'known' => $known,
            'cards' => $cards,
            'zone' => $zone->getName(),
        ]);
    }

    public function logo(string $slug): void
    {
        $business = $this->business($slug);
        $ext = (string) ($business['logo_ext'] ?? '');
        $file = Brand::path((int) $business['id'], $ext);
        if ($file === null) {
            throw new HttpException(404, 'Nessun logo.');
        }
        header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'image/jpeg'));
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    public function book(string $slug): void
    {
        $business = $this->business($slug);
        $this->throttle();
        if (Input::str('website', 80) !== '') {
            flash('ok', 'Richiesta ricevuta.');
            go('/b/' . $business['slug']);
            exit;
        }
        $serviceId = Input::int('service_id', 1, 999999999);
        [$staffId, $resourceId, $start] = $this->slot();
        $user = Auth::user(App::$pdo);
        if ($user !== null && (string) $user['role'] === 'client') {
            $name = (string) $user['name'];
            $email = (string) $user['email'];
            $userId = (int) $user['id'];
        } else {
            $name = Input::str('name', 120, true);
            $email = Input::email('email');
            $userId = null;
        }
        if (mb_strlen($name) < 2) {
            throw new DomainException('validation', 'Scrivi nome e cognome.');
        }
        $appointment = App::$bookings->bookResolved(
            (int) $business['id'],
            $serviceId,
            $staffId,
            $resourceId,
            $start,
            $name,
            $email,
            Input::phone('phone'),
            Input::str('notes', 500),
            Input::flag('use_package') === 1,
            $userId,
        );
        go('/b/' . $business['slug'] . '/ok/' . $appointment['public_token']);
        exit;
    }

    public function bookClass(string $slug): void
    {
        $business = $this->business($slug);
        $this->throttle();
        if (Input::str('website', 80) !== '') {
            flash('ok', 'Richiesta ricevuta.');
            go('/b/' . $business['slug']);
            exit;
        }
        $session = Db::one(
            App::$pdo,
            'SELECT * FROM class_sessions WHERE id = :id AND business_id = :business',
            ['id' => Input::int('session_id', 1, 999999999), 'business' => (int) $business['id']],
        );
        if ($session === null) {
            throw new DomainException('not_found', 'Corso non trovato.');
        }
        $user = Auth::user(App::$pdo);
        if ($user !== null && (string) $user['role'] === 'client') {
            $name = (string) $user['name'];
            $email = (string) $user['email'];
            $userId = (int) $user['id'];
        } else {
            $name = Input::str('name', 120, true);
            $email = Input::email('email');
            $userId = null;
        }
        $booking = App::$bookings->bookClassGuest(
            (int) $session['id'],
            $name,
            $email,
            Input::phone('phone'),
            Input::flag('use_package') === 1,
            $userId,
        );
        go('/c/' . $booking['public_token']);
        exit;
    }

    public function wait(string $slug): void
    {
        $business = $this->business($slug);
        $this->throttle();
        if (Input::str('website', 80) !== '') {
            flash('ok', 'Richiesta ricevuta.');
            go('/b/' . $business['slug']);
            exit;
        }
        $staffId = Input::int('staff_id', 0, 999999999, false);
        $user = Auth::user(App::$pdo);
        $userId = $user !== null && (string) $user['role'] === 'client' ? (int) $user['id'] : null;
        $name = $userId !== null ? (string) $user['name'] : Input::str('name', 120, true);
        $email = $userId !== null ? (string) $user['email'] : Input::email('email');
        $entry = App::$bookings->joinWaitlist(
            (int) $business['id'],
            Input::int('service_id', 1, 999999999),
            $staffId > 0 ? $staffId : null,
            $name,
            $email,
            Input::phone('phone'),
            Input::date('date'),
            Input::time('time_from'),
            Input::time('time_to'),
            $userId,
        );
        go('/w/' . $entry['public_token']);
        exit;
    }

    public function done(string $slug, string $token): void
    {
        $appointment = $this->appointmentByToken($token, $slug);
        View::render('booking/done', [
            'title' => 'Prenotazione confermata',
            'appointment' => $appointment,
        ]);
    }

    public function guestAppointment(string $token): void
    {
        $appointment = $this->appointmentByToken($token, null);
        View::render('guest/appointment', [
            'title' => 'La tua prenotazione',
            'appointment' => $appointment,
        ]);
    }

    public function guestCancel(string $token): void
    {
        $appointment = $this->appointmentByToken($token, null);
        App::$bookings->cancelAppointment((int) $appointment['id'], (int) $appointment['business_id'], false, Input::str('reason', 180));
        flash('ok', 'Prenotazione annullata.');
        go('/p/' . $token);
        exit;
    }

    public function guestIcs(string $token): void
    {
        $appointment = $this->appointmentByToken($token, null);
        $ics = Ics::event(
            'lumina-' . $token . '@lumina',
            App::$clock->parseUtc((string) $appointment['starts_at']),
            App::$clock->parseUtc((string) $appointment['ends_at']),
            $appointment['service_name'] . ' · ' . $appointment['business_name'],
            trim($appointment['address'] . ' ' . $appointment['city']),
            'Prenotazione Lumina',
        );
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="lumina.ics"');
        echo $ics;
        exit;
    }

    public function guestClass(string $token): void
    {
        View::render('guest/class', [
            'title' => 'Iscrizione al corso',
            'booking' => $this->classByToken($token),
        ]);
    }

    public function guestClassCancel(string $token): void
    {
        $booking = $this->classByToken($token);
        $start = App::$clock->parseUtc((string) $booking['starts_at']);
        $business = Db::one(App::$pdo, 'SELECT cancel_hours FROM businesses WHERE id = :id', ['id' => (int) $booking['business_id']]);
        $seconds = $start->getTimestamp() - App::$clock->now()->getTimestamp();
        if ($seconds < (int) ($business['cancel_hours'] ?? 12) * 3600) {
            throw new DomainException('policy', 'Puoi annullare solo entro la finestra prevista. Contatta l\'attività.');
        }
        App::$bookings->setClassBookingStatus((int) $booking['id'], (int) $booking['business_id'], 'cancelled');
        flash('ok', 'Iscrizione annullata.');
        go('/c/' . $token);
        exit;
    }

    public function guestWait(string $token): void
    {
        $entry = $this->waitByToken($token);
        App::$bookings->sweep((int) $entry['business_id']);
        View::render('guest/wait', [
            'title' => 'Lista d\'attesa',
            'entry' => $this->waitByToken($token),
        ]);
    }

    public function guestWaitAccept(string $token): void
    {
        $entry = $this->waitByToken($token);
        $appointment = App::$bookings->acceptOffer((int) $entry['id'], Input::flag('use_package') === 1);
        go('/p/' . $appointment['public_token']);
        exit;
    }

    public function guestWaitCancel(string $token): void
    {
        $entry = $this->waitByToken($token);
        App::$bookings->cancelWaitlist((int) $entry['id'], (int) $entry['business_id']);
        flash('ok', 'Richiesta annullata.');
        go('/w/' . $token);
        exit;
    }

    /** @return array{0: int, 1: ?int, 2: DateTimeImmutable} */
    private function slot(): array
    {
        $parts = explode('|', Input::str('slot', 80, true));
        if (count($parts) !== 3 || preg_match('/^\d+$/', $parts[0]) !== 1 || preg_match('/^\d+$/', $parts[1]) !== 1 || preg_match('/^\d+$/', $parts[2]) !== 1) {
            throw new DomainException('validation', 'Seleziona un orario.');
        }
        $resource = (int) $parts[1];

        return [
            (int) $parts[0],
            $resource > 0 ? $resource : null,
            (new DateTimeImmutable('@' . $parts[2]))->setTimezone(new DateTimeZone('UTC')),
        ];
    }

    private function throttle(): void
    {
        $now = time();
        $hits = $_SESSION['book_hits'] ?? [];
        if (!is_array($hits)) {
            $hits = [];
        }
        $hits = array_values(array_filter($hits, static fn (mixed $hit): bool => is_int($hit) && $hit > $now - 3600));
        if (count($hits) >= 20) {
            throw new DomainException('policy', 'Hai fatto troppe richieste. Riprova tra un\'ora.');
        }
        $hits[] = $now;
        $_SESSION['book_hits'] = $hits;
    }

    /** @return array<string, mixed> */
    private function business(string $slug): array
    {
        if (preg_match('/^[a-z0-9-]{1,80}$/', $slug) !== 1) {
            throw new HttpException(404, 'Attività non trovata.');
        }
        $business = Db::one(App::$pdo, 'SELECT * FROM businesses WHERE slug = :slug', ['slug' => $slug]);
        if ($business === null) {
            throw new HttpException(404, 'Attività non trovata.');
        }

        return $business;
    }

    /** @return array<string, mixed> */
    private function appointmentByToken(string $token, ?string $slug): array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            throw new HttpException(404, 'Prenotazione non trovata.');
        }
        $row = Db::one(
            App::$pdo,
            'SELECT a.*, s.name AS service_name, st.name AS staff_name, c.name AS client_name,
                    b.name AS business_name, b.slug, b.timezone, b.address, b.city, b.phone AS business_phone,
                    b.cancel_hours, r.name AS resource_name
             FROM appointments a
             INNER JOIN services s ON s.id = a.service_id
             INNER JOIN staff st ON st.id = a.staff_id
             INNER JOIN clients c ON c.id = a.client_id
             INNER JOIN businesses b ON b.id = a.business_id
             LEFT JOIN resources r ON r.id = a.resource_id
             WHERE a.public_token = :token',
            ['token' => $token],
        );
        if ($row === null || ($slug !== null && (string) $row['slug'] !== $slug)) {
            throw new HttpException(404, 'Prenotazione non trovata.');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function classByToken(string $token): array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            throw new HttpException(404, 'Iscrizione non trovata.');
        }
        $row = Db::one(
            App::$pdo,
            'SELECT b.*, s.starts_at, s.ends_at, s.status AS session_status, s.business_id,
                    sv.name AS service_name, st.name AS staff_name, c.name AS client_name,
                    bz.name AS business_name, bz.slug, bz.timezone, bz.city, bz.cancel_hours
             FROM class_bookings b
             INNER JOIN class_sessions s ON s.id = b.session_id
             INNER JOIN services sv ON sv.id = s.service_id
             INNER JOIN staff st ON st.id = s.staff_id
             INNER JOIN clients c ON c.id = b.client_id
             INNER JOIN businesses bz ON bz.id = s.business_id
             WHERE b.public_token = :token',
            ['token' => $token],
        );
        if ($row === null) {
            throw new HttpException(404, 'Iscrizione non trovata.');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function waitByToken(string $token): array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            throw new HttpException(404, 'Richiesta non trovata.');
        }
        $row = Db::one(
            App::$pdo,
            'SELECT w.*, s.name AS service_name, c.name AS client_name, b.name AS business_name, b.slug, b.timezone
             FROM waitlist w
             INNER JOIN services s ON s.id = w.service_id
             INNER JOIN clients c ON c.id = w.client_id
             INNER JOIN businesses b ON b.id = w.business_id
             WHERE w.public_token = :token',
            ['token' => $token],
        );
        if ($row === null) {
            throw new HttpException(404, 'Richiesta non trovata.');
        }

        return $row;
    }

    /** @param list<array<string, mixed>> $slots @return array<string, list<array<string, mixed>>> */
    private function grouped(array $slots): array
    {
        $groups = ['ideal' => [], 'adjacent' => [], 'open' => [], 'fragment' => []];
        foreach ($slots as $slot) {
            $groups[(string) $slot['quality']][] = $slot;
        }

        return $groups;
    }
}
