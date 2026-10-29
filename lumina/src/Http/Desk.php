<?php

declare(strict_types=1);

namespace Lumina\Http;

use DateTimeImmutable;
use DateTimeZone;
use Lumina\App;
use Lumina\Db;
use Lumina\DomainException;
use Lumina\HttpException;
use Lumina\Input;
use Lumina\Insights;
use Lumina\Plan;

final class Desk
{
    public function dashboard(): void
    {
        $workspace = Guard::workspace();
        $insights = (new Insights(App::$pdo, App::$clock, App::$engine))->dashboard($workspace['business']);
        Guard::view('app/dashboard', $workspace, [
            'title' => 'Oggi',
            'insights' => $insights,
        ]);
    }

    public function agenda(): void
    {
        $workspace = Guard::workspace();
        $zone = App::$engine->zone($workspace['business']);
        $anchor = valid_date((string) ($_GET['data'] ?? '')) ?? App::$clock->today($zone);
        $local = new DateTimeImmutable($anchor . ' 00:00:00', $zone);
        $monday = $local->modify('-' . ((int) $local->format('N') - 1) . ' days');
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $monday->modify('+' . $i . ' days');
            $days[] = $day->format('Y-m-d');
        }
        [$start] = App::$clock->dayBounds($days[0], $zone);
        [, $end] = App::$clock->dayBounds($days[6], $zone);
        $rows = Db::all(
            App::$pdo,
            "SELECT a.*, s.name AS service_name, st.name AS staff_name, st.color, c.name AS client_name
             FROM appointments a
             INNER JOIN services s ON s.id = a.service_id
             INNER JOIN staff st ON st.id = a.staff_id
             INNER JOIN clients c ON c.id = a.client_id
             WHERE a.business_id = :business AND a.status <> 'cancelled'
               AND a.starts_at >= :start_at AND a.starts_at < :end_at
             ORDER BY a.starts_at",
            [
                'business' => (int) $workspace['business']['id'],
                'start_at' => App::$clock->sql($start),
                'end_at' => App::$clock->sql($end),
            ],
        );
        $byDay = array_fill_keys($days, []);
        foreach ($rows as $row) {
            $key = App::$clock->local(App::$clock->parseUtc((string) $row['starts_at']), $zone)->format('Y-m-d');
            if (isset($byDay[$key])) {
                $byDay[$key][] = $row;
            }
        }
        Guard::view('app/agenda', $workspace, [
            'title' => 'Agenda',
            'days' => $days,
            'byDay' => $byDay,
            'previous' => $monday->modify('-7 days')->format('Y-m-d'),
            'next' => $monday->modify('+7 days')->format('Y-m-d'),
            'monday' => $days[0],
        ]);
    }

    public function day(): void
    {
        $workspace = Guard::workspace();
        $businessId = (int) $workspace['business']['id'];
        $zone = App::$engine->zone($workspace['business']);
        $date = valid_date((string) ($_GET['data'] ?? '')) ?? App::$clock->today($zone);
        $frame = App::$engine->frame($businessId, $date);
        [$start, $end] = App::$clock->dayBounds($date, $zone);
        $staff = Db::all(App::$pdo, 'SELECT * FROM staff WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => $businessId]);
        $appointments = Db::all(
            App::$pdo,
            "SELECT a.*, s.name AS service_name, c.name AS client_name, c.allergies
             FROM appointments a
             INNER JOIN services s ON s.id = a.service_id
             INNER JOIN clients c ON c.id = a.client_id
             WHERE a.business_id = :business AND a.status <> 'cancelled'
               AND a.starts_at >= :start_at AND a.starts_at < :end_at",
            ['business' => $businessId, 'start_at' => App::$clock->sql($start), 'end_at' => App::$clock->sql($end)],
        );
        $sessions = Db::all(
            App::$pdo,
            "SELECT s.*, sv.name AS service_name
             FROM class_sessions s
             INNER JOIN services sv ON sv.id = s.service_id
             WHERE s.business_id = :business AND s.status <> 'cancelled'
               AND s.starts_at >= :start_at AND s.starts_at < :end_at",
            ['business' => $businessId, 'start_at' => App::$clock->sql($start), 'end_at' => App::$clock->sql($end)],
        );
        $lanes = [];
        foreach ($staff as $person) {
            $lanes[(int) $person['id']] = ['staff' => $person, 'blocks' => []];
        }
        foreach ($appointments as $row) {
            $block = $this->block($row, $frame, $zone, '/app/appuntamenti/' . $row['id'], (string) $row['client_name'], (string) $row['service_name'], (string) $row['status'], (string) $row['allergies'] !== '');
            if ($block !== null && isset($lanes[(int) $row['staff_id']])) {
                $lanes[(int) $row['staff_id']]['blocks'][] = $block;
            }
        }
        foreach ($sessions as $row) {
            $block = $this->block($row, $frame, $zone, '/app/classi/' . $row['id'], 'Corso', (string) $row['service_name'], (string) $row['status'], false);
            if ($block !== null && isset($lanes[(int) $row['staff_id']])) {
                $lanes[(int) $row['staff_id']]['blocks'][] = $block;
            }
        }
        $serviceId = (int) ($_GET['servizio'] ?? 0);
        $staffId = (int) ($_GET['staff'] ?? 0);
        $services = Db::all(App::$pdo, "SELECT * FROM services WHERE business_id = :id AND active = 1 AND kind = 'appointment' ORDER BY name", ['id' => $businessId]);
        $slots = $serviceId > 0 ? App::$engine->slots($businessId, $serviceId, $date, $staffId > 0 ? $staffId : null) : [];
        $clients = Db::all(App::$pdo, 'SELECT id, name, phone FROM clients WHERE business_id = :id ORDER BY name', ['id' => $businessId]);
        Guard::view('app/day', $workspace, [
            'title' => 'Bacheca',
            'date' => $date,
            'frame' => $frame,
            'lanes' => $lanes,
            'nowMinute' => $this->nowMinute($frame, $zone, $date),
            'services' => $services,
            'staff' => $staff,
            'clients' => $clients,
            'serviceId' => $serviceId,
            'staffId' => $staffId,
            'slots' => $slots,
        ]);
    }

    public function appointment(string $id): void
    {
        $workspace = Guard::workspace();
        $row = $this->load((int) $id, (int) $workspace['business']['id']);
        $score = App::$bookings->reliability((int) $row['client_id'], (int) $workspace['business']['reliability_threshold']);
        Guard::view('app/appointment', $workspace, [
            'title' => $row['client_name'],
            'appointment' => $row,
            'score' => $score,
        ]);
    }

    public function create(): void
    {
        $workspace = Guard::workspace();
        $businessId = (int) $workspace['business']['id'];
        [$staffId, $resourceId, $start] = $this->slot();
        $clientId = Input::int('client_id', 0, 999999999, false);
        if ($clientId === 0) {
            $email = Input::email('client_email', false);
            $client = App::$bookings->ensureClient(
                $businessId,
                Input::str('client_name', 120, true),
                $email !== '' ? $email : null,
                Input::phone('client_phone'),
                null,
            );
            $clientId = (int) $client['id'];
        } else {
            $owned = Db::one(App::$pdo, 'SELECT id FROM clients WHERE id = :id AND business_id = :business', ['id' => $clientId, 'business' => $businessId]);
            if ($owned === null) {
                throw new DomainException('validation', 'Cliente non trovato.');
            }
        }
        $occurrences = Input::int('occurrences', 1, 12, false);
        $serviceId = Input::int('service_id', 1, 999999999);
        $notes = Input::str('notes', 500);
        $usePackage = Input::flag('use_package') === 1;
        if ($occurrences > 1) {
            $series = App::$bookings->bookSeries($businessId, $serviceId, $staffId, null, $clientId, $start, $occurrences, $notes, $usePackage);
            flash('ok', count($series['booked']) . ' prenotazioni create' . ($series['skipped'] === [] ? '.' : ', ' . count($series['skipped']) . ' settimane saltate.'));
            go('/app/agenda');
            exit;
        }
        $appointment = App::$bookings->bookAppointment($businessId, $serviceId, $staffId, $resourceId, $clientId, $start, $notes, $usePackage);
        flash('ok', 'Prenotazione creata.');
        go('/app/appuntamenti/' . $appointment['id']);
        exit;
    }

    public function status(string $id): void
    {
        $workspace = Guard::workspace();
        App::$bookings->setStatus(
            (int) $id,
            (int) $workspace['business']['id'],
            Input::choice('status', ['arrived', 'in_progress', 'completed', 'no_show']),
        );
        flash('ok', 'Stato aggiornato.');
        go('/app/appuntamenti/' . (int) $id);
        exit;
    }

    public function cancel(string $id): void
    {
        $workspace = Guard::workspace();
        $offer = App::$bookings->cancelAppointment((int) $id, (int) $workspace['business']['id'], true, Input::str('reason', 180));
        flash('ok', $offer === null ? 'Prenotazione annullata.' : 'Prenotazione annullata e posto offerto a chi era in lista.');
        go('/app/giornata');
        exit;
    }

    public function refund(string $id): void
    {
        $workspace = Guard::workspace();
        $extended = App::$bookings->refundAppointmentCredit((int) $id, (int) $workspace['business']['id']);
        flash('ok', $extended ? 'Credito restituito. La tessera era scaduta: vale per un\'altra settimana.' : 'Credito restituito.');
        go('/app/appuntamenti/' . (int) $id);
        exit;
    }

    public function export(): void
    {
        $workspace = Guard::workspace();
        Plan::assertFeature(App::$pdo, (int) $workspace['business']['id'], 'export');
        $zone = App::$engine->zone($workspace['business']);
        $anchor = valid_date((string) ($_GET['data'] ?? '')) ?? App::$clock->today($zone);
        $local = new DateTimeImmutable($anchor . ' 00:00:00', $zone);
        $monday = $local->modify('-' . ((int) $local->format('N') - 1) . ' days');
        [$start] = App::$clock->dayBounds($monday->format('Y-m-d'), $zone);
        [, $end] = App::$clock->dayBounds($monday->modify('+6 days')->format('Y-m-d'), $zone);
        $rows = Db::all(
            App::$pdo,
            'SELECT a.starts_at, a.ends_at, a.status, a.price_cents, a.price_label, a.notes,
                    s.name AS service_name, st.name AS staff_name, c.name AS client_name, c.phone, c.email
             FROM appointments a
             INNER JOIN services s ON s.id = a.service_id
             INNER JOIN staff st ON st.id = a.staff_id
             INNER JOIN clients c ON c.id = a.client_id
             WHERE a.business_id = :business AND a.starts_at >= :start_at AND a.starts_at < :end_at
             ORDER BY a.starts_at',
            [
                'business' => (int) $workspace['business']['id'],
                'start_at' => App::$clock->sql($start),
                'end_at' => App::$clock->sql($end),
            ],
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="lumina-' . $monday->format('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        echo implode(',', array_map(csv_cell(...), ['Inizio', 'Fine', 'Cliente', 'Telefono', 'Email', 'Servizio', 'Operatore', 'Stato', 'Prezzo', 'Nota prezzo', 'Note'])) . "\n";
        foreach ($rows as $row) {
            echo implode(',', array_map(csv_cell(...), [
                dt((string) $row['starts_at'], (string) $workspace['business']['timezone'], 'yyyy-MM-dd HH:mm'),
                dt((string) $row['ends_at'], (string) $workspace['business']['timezone'], 'HH:mm'),
                (string) $row['client_name'],
                (string) $row['phone'],
                (string) ($row['email'] ?? ''),
                (string) $row['service_name'],
                (string) $row['staff_name'],
                appointment_status_label((string) $row['status']),
                number_format(((int) $row['price_cents']) / 100, 2, ',', ''),
                (string) $row['price_label'],
                (string) $row['notes'],
            ])) . "\n";
        }
        exit;
    }

    public function notifications(): void
    {
        $workspace = Guard::workspace();
        $rows = Db::all(
            App::$pdo,
            'SELECT * FROM notifications WHERE user_id = :user ORDER BY id DESC LIMIT 40',
            ['user' => (int) $workspace['user']['id']],
        );
        Guard::view('app/notifications', $workspace, ['title' => 'Notifiche', 'rows' => $rows]);
    }

    public function readNotifications(): void
    {
        $workspace = Guard::workspace();
        Db::run(
            App::$pdo,
            'UPDATE notifications SET read_at = :now WHERE user_id = :user AND read_at IS NULL',
            ['now' => App::$clock->sql(App::$clock->now()), 'user' => (int) $workspace['user']['id']],
        );
        go('/app/notifiche');
        exit;
    }

    /** @return array<string, mixed> */
    private function load(int $id, int $businessId): array
    {
        $row = Db::one(
            App::$pdo,
            'SELECT a.*, s.name AS service_name, s.duration_min, st.name AS staff_name, st.color,
                    c.name AS client_name, c.phone, c.email, c.allergies, c.preferences, c.notes AS client_notes,
                    r.name AS resource_name
             FROM appointments a
             INNER JOIN services s ON s.id = a.service_id
             INNER JOIN staff st ON st.id = a.staff_id
             INNER JOIN clients c ON c.id = a.client_id
             LEFT JOIN resources r ON r.id = a.resource_id
             WHERE a.id = :id AND a.business_id = :business',
            ['id' => $id, 'business' => $businessId],
        );
        if ($row === null) {
            throw new HttpException(404, 'Appuntamento non trovato.');
        }

        return $row;
    }

    /** @param array<string, mixed> $row @param array{closed: bool, open_min: int, close_min: int, breaks: list<array{start_min: int, end_min: int}>} $frame @return array<string, mixed>|null */
    private function block(array $row, array $frame, DateTimeZone $zone, string $href, string $who, string $what, string $status, bool $allergy): ?array
    {
        if ($frame['closed']) {
            return null;
        }
        $start = App::$clock->local(App::$clock->parseUtc((string) $row['starts_at']), $zone);
        $end = App::$clock->local(App::$clock->parseUtc((string) $row['ends_at']), $zone);
        $startMin = ((int) $start->format('H')) * 60 + (int) $start->format('i');
        $endMin = ((int) $end->format('H')) * 60 + (int) $end->format('i');
        $left = max($frame['open_min'], $startMin);
        $right = min($frame['close_min'], $endMin);
        $span = $frame['close_min'] - $frame['open_min'];
        if ($span <= 0 || $right <= $left) {
            return null;
        }

        return [
            'left' => ($left - $frame['open_min']) / $span * 100,
            'width' => ($right - $left) / $span * 100,
            'start_min' => $startMin,
            'end_min' => $endMin,
            'href' => $href,
            'who' => $who,
            'what' => $what,
            'status' => $status,
            'allergy' => $allergy,
            'time' => $start->format('H:i'),
        ];
    }

    /** @param array{closed: bool, open_min: int, close_min: int, breaks: list<array{start_min: int, end_min: int}>} $frame */
    private function nowMinute(array $frame, DateTimeZone $zone, string $date): ?int
    {
        if ($frame['closed'] || $date !== App::$clock->today($zone)) {
            return null;
        }
        $local = App::$clock->local(App::$clock->now(), $zone);
        $minute = ((int) $local->format('H')) * 60 + (int) $local->format('i');
        if ($minute < $frame['open_min'] || $minute > $frame['close_min']) {
            return null;
        }

        return $minute;
    }

    /** @return array{0: int, 1: ?int, 2: DateTimeImmutable} */
    private function slot(): array
    {
        $parts = explode('|', Input::str('slot', 80, true));
        if (count($parts) !== 3 || preg_match('/^\d+\|\d+\|\d+$/', implode('|', $parts)) !== 1) {
            throw new DomainException('validation', 'Seleziona un orario.');
        }
        $resource = (int) $parts[1];

        return [
            (int) $parts[0],
            $resource > 0 ? $resource : null,
            (new DateTimeImmutable('@' . $parts[2]))->setTimezone(new DateTimeZone('UTC')),
        ];
    }
}
