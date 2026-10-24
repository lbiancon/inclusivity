<?php

declare(strict_types=1);

namespace Lumina\Http;

use Lumina\App;
use Lumina\Brand;
use Lumina\Category;
use Lumina\Db;
use Lumina\DomainException;
use Lumina\HttpException;
use Lumina\Input;
use Lumina\Insights;
use Lumina\Plan;

final class Studio
{
    public function classes(): void
    {
        $workspace = Guard::workspace();
        $businessId = (int) $workspace['business']['id'];
        Guard::view('app/classes', $workspace, [
            'title' => 'Corsi',
            'sessions' => Db::all(
                App::$pdo,
                "SELECT s.*, sv.name AS service_name, st.name AS staff_name,
                        (SELECT COUNT(*) FROM class_bookings b WHERE b.session_id = s.id AND b.status = 'booked') AS taken
                 FROM class_sessions s
                 INNER JOIN services sv ON sv.id = s.service_id
                 INNER JOIN staff st ON st.id = s.staff_id
                 WHERE s.business_id = :business AND s.starts_at >= :now
                 ORDER BY s.starts_at LIMIT 30",
                ['business' => $businessId, 'now' => App::$clock->sql(App::$clock->now()->modify('-1 day'))],
            ),
            'services' => Db::all(App::$pdo, "SELECT * FROM services WHERE business_id = :id AND kind = 'class' AND active = 1 ORDER BY name", ['id' => $businessId]),
            'staff' => Db::all(App::$pdo, 'SELECT * FROM staff WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => $businessId]),
            'resources' => Db::all(App::$pdo, 'SELECT * FROM resources WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => $businessId]),
        ]);
    }

    public function saveSession(): void
    {
        $workspace = Guard::workspace();
        $zone = App::$engine->zone($workspace['business']);
        $start = App::$clock->at(Input::date('date') . ' ' . Input::time('time') . ':00', $zone);
        $resource = Input::int('resource_id', 0, 999999999, false);
        $session = App::$bookings->scheduleSession(
            (int) $workspace['business']['id'],
            Input::int('service_id', 1, 999999999),
            Input::int('staff_id', 1, 999999999),
            $resource > 0 ? $resource : null,
            $start,
            Input::int('capacity', 1, 80),
            Input::str('notes', 300),
        );
        flash('ok', 'Corso messo in agenda.');
        go('/app/classi/' . $session['id']);
        exit;
    }

    public function session(string $id): void
    {
        $workspace = Guard::workspace();
        $session = $this->sessionRow((int) $id, (int) $workspace['business']['id']);
        Guard::view('app/class', $workspace, [
            'title' => $session['service_name'],
            'session' => $session,
            'bookings' => Db::all(
                App::$pdo,
                'SELECT b.*, c.name AS client_name, c.phone FROM class_bookings b INNER JOIN clients c ON c.id = b.client_id WHERE b.session_id = :id ORDER BY c.name',
                ['id' => (int) $session['id']],
            ),
            'clients' => Db::all(App::$pdo, 'SELECT id, name FROM clients WHERE business_id = :id ORDER BY name', ['id' => (int) $workspace['business']['id']]),
        ]);
    }

    public function bookSession(string $id): void
    {
        $workspace = Guard::workspace();
        $session = $this->sessionRow((int) $id, (int) $workspace['business']['id']);
        $clientId = Input::int('client_id', 0, 999999999, false);
        if ($clientId === 0) {
            $email = Input::email('client_email', false);
            $client = App::$bookings->ensureClient(
                (int) $workspace['business']['id'],
                Input::str('client_name', 120, true),
                $email !== '' ? $email : null,
                Input::phone('client_phone'),
                null,
            );
            $clientId = (int) $client['id'];
        }
        App::$bookings->bookClass((int) $session['id'], $clientId, Input::flag('use_package') === 1);
        flash('ok', 'Iscrizione registrata.');
        go('/app/classi/' . $session['id']);
        exit;
    }

    public function sessionStatus(string $id): void
    {
        $workspace = Guard::workspace();
        App::$bookings->setSessionStatus((int) $id, (int) $workspace['business']['id'], Input::choice('status', ['cancelled', 'completed']));
        flash('ok', 'Corso aggiornato.');
        go('/app/classi/' . (int) $id);
        exit;
    }

    public function bookingStatus(string $id): void
    {
        $workspace = Guard::workspace();
        $booking = Db::one(
            App::$pdo,
            'SELECT b.id, b.session_id FROM class_bookings b INNER JOIN class_sessions s ON s.id = b.session_id WHERE b.id = :id AND s.business_id = :business',
            ['id' => (int) $id, 'business' => (int) $workspace['business']['id']],
        );
        if ($booking === null) {
            throw new HttpException(404, 'Iscrizione non trovata.');
        }
        App::$bookings->setClassBookingStatus((int) $booking['id'], (int) $workspace['business']['id'], Input::choice('status', ['attended', 'no_show', 'cancelled']));
        flash('ok', 'Iscrizione aggiornata.');
        go('/app/classi/' . $booking['session_id']);
        exit;
    }

    public function refundBooking(string $id): void
    {
        $workspace = Guard::workspace();
        $booking = Db::one(
            App::$pdo,
            'SELECT b.session_id FROM class_bookings b INNER JOIN class_sessions s ON s.id = b.session_id WHERE b.id = :id AND s.business_id = :business',
            ['id' => (int) $id, 'business' => (int) $workspace['business']['id']],
        );
        if ($booking === null) {
            throw new HttpException(404, 'Iscrizione non trovata.');
        }
        $extended = App::$bookings->refundClassCredit((int) $id, (int) $workspace['business']['id']);
        flash('ok', $extended ? 'Credito restituito e tessera prorogata di una settimana.' : 'Credito restituito.');
        go('/app/classi/' . $booking['session_id']);
        exit;
    }

    public function packages(): void
    {
        $workspace = Guard::workspace(true);
        Guard::view('app/packages', $workspace, [
            'title' => 'Tessere',
            'packages' => Db::all(App::$pdo, 'SELECT p.*, s.name AS service_name FROM packages p LEFT JOIN services s ON s.id = p.service_id WHERE p.business_id = :id ORDER BY p.active DESC, p.name', ['id' => (int) $workspace['business']['id']]),
            'services' => Db::all(App::$pdo, 'SELECT id, name FROM services WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => (int) $workspace['business']['id']]),
        ]);
    }

    public function savePackage(?string $id = null): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        Plan::assertFeature(App::$pdo, $businessId, 'packages');
        $packageId = $id === null ? 0 : (int) $id;
        $serviceId = Input::int('service_id', 0, 999999999, false);
        if ($serviceId > 0) {
            $service = Db::one(App::$pdo, 'SELECT id FROM services WHERE id = :id AND business_id = :business', ['id' => $serviceId, 'business' => $businessId]);
            if ($service === null) {
                $serviceId = 0;
            }
        }
        $payload = [
            'name' => Input::str('name', 140, true),
            'service' => $serviceId > 0 ? $serviceId : null,
            'credits' => Input::int('credits', 1, 200),
            'price' => Input::money('price'),
            'days' => Input::int('valid_days', 1, 730),
            'active' => Input::flag('active'),
        ];
        if ($packageId === 0) {
            Db::run(App::$pdo, 'INSERT INTO packages (business_id, service_id, name, credits, price_cents, valid_days, active) VALUES (:business, :service, :name, :credits, :price, :days, :active)', $payload + ['business' => $businessId]);
        } else {
            $existing = Db::one(App::$pdo, 'SELECT id FROM packages WHERE id = :id AND business_id = :business', ['id' => $packageId, 'business' => $businessId]);
            if ($existing === null) {
                throw new DomainException('not_found', 'Tessera non trovata.');
            }
            Db::run(App::$pdo, 'UPDATE packages SET service_id = :service, name = :name, credits = :credits, price_cents = :price, valid_days = :days, active = :active WHERE id = :id', $payload + ['id' => $packageId]);
        }
        flash('ok', 'Tessera salvata.');
        go('/app/pacchetti');
        exit;
    }

    public function waitlist(): void
    {
        $workspace = Guard::workspace();
        Guard::view('app/waitlist', $workspace, [
            'title' => 'Lista d\'attesa',
            'rows' => Db::all(
                App::$pdo,
                'SELECT w.*, s.name AS service_name, c.name AS client_name, c.phone, st.name AS staff_name
                 FROM waitlist w
                 INNER JOIN services s ON s.id = w.service_id
                 INNER JOIN clients c ON c.id = w.client_id
                 LEFT JOIN staff st ON st.id = w.staff_id
                 WHERE w.business_id = :business AND w.status IN (\'waiting\', \'offered\')
                 ORDER BY FIELD(w.status, \'offered\', \'waiting\'), w.created_at',
                ['business' => (int) $workspace['business']['id']],
            ),
        ]);
    }

    public function accept(string $id): void
    {
        $workspace = Guard::workspace();
        $row = Db::one(App::$pdo, 'SELECT * FROM waitlist WHERE id = :id AND business_id = :business', ['id' => (int) $id, 'business' => (int) $workspace['business']['id']]);
        if ($row === null) {
            throw new HttpException(404, 'Richiesta non trovata.');
        }
        App::$bookings->acceptOffer((int) $row['id'], Input::flag('use_package') === 1);
        flash('ok', 'Offerta convertita in prenotazione.');
        go('/app/attesa');
        exit;
    }

    public function cancelWait(string $id): void
    {
        $workspace = Guard::workspace();
        App::$bookings->cancelWaitlist((int) $id, (int) $workspace['business']['id']);
        flash('ok', 'Richiesta chiusa.');
        go('/app/attesa');
        exit;
    }

    public function pricing(): void
    {
        $workspace = Guard::workspace(true);
        Guard::view('app/pricing', $workspace, [
            'title' => 'Prezzo vivo',
            'rules' => Db::all(
                App::$pdo,
                'SELECT r.*, s.name AS service_name FROM pricing_rules r LEFT JOIN services s ON s.id = r.service_id WHERE r.business_id = :id ORDER BY r.active DESC, r.name',
                ['id' => (int) $workspace['business']['id']],
            ),
            'services' => Db::all(App::$pdo, "SELECT id, name FROM services WHERE business_id = :id AND kind = 'appointment' AND active = 1 ORDER BY name", ['id' => (int) $workspace['business']['id']]),
        ]);
    }

    public function saveRule(): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        Plan::assertRule(App::$pdo, $businessId);
        $serviceId = Input::int('service_id', 0, 999999999, false);
        $weekday = Input::int('weekday', 0, 7, false);
        $start = Input::time('start_time');
        $end = Input::time('end_time');
        if ($end <= $start) {
            throw new DomainException('validation', 'La fascia deve finire dopo l\'inizio.');
        }
        Db::run(App::$pdo, 'INSERT INTO pricing_rules (
            business_id, service_id, name, weekday, start_time, end_time, discount_percent, active
        ) VALUES (
            :business, :service, :name, :weekday, :start_time, :end_time, :discount, 1
        )', [
            'business' => $businessId,
            'service' => $serviceId > 0 ? $serviceId : null,
            'name' => Input::str('name', 120, true),
            'weekday' => $weekday > 0 ? $weekday : null,
            'start_time' => $start . ':00',
            'end_time' => $end . ':00',
            'discount' => Input::int('discount_percent', 1, 90),
        ]);
        flash('ok', 'Regola attiva: gli orari in fascia mostrano il prezzo nuovo.');
        go('/app/prezzi');
        exit;
    }

    public function toggleRule(string $id): void
    {
        $workspace = Guard::workspace(true);
        $rule = Db::one(App::$pdo, 'SELECT * FROM pricing_rules WHERE id = :id AND business_id = :business', ['id' => (int) $id, 'business' => (int) $workspace['business']['id']]);
        if ($rule === null) {
            throw new HttpException(404, 'Regola non trovata.');
        }
        Db::run(App::$pdo, 'UPDATE pricing_rules SET active = :active WHERE id = :id', [
            'active' => (int) $rule['active'] === 1 ? 0 : 1,
            'id' => (int) $rule['id'],
        ]);
        go('/app/prezzi');
        exit;
    }

    public function analytics(): void
    {
        $workspace = Guard::workspace();
        if (!Plan::allows($workspace['business'], 'analytics')) {
            flash('err', 'Le analisi sono nel piano Borgo.');
            go('/app/piano');
            exit;
        }
        Guard::view('app/analytics', $workspace, [
            'title' => 'Analisi',
            'picture' => (new Insights(App::$pdo, App::$clock, App::$engine))->picture($workspace['business']),
        ]);
    }

    public function settings(): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        Guard::view('app/settings', $workspace, [
            'title' => 'Impostazioni',
            'categories' => Category::cases(),
            'hours' => Db::all(App::$pdo, 'SELECT * FROM weekly_hours WHERE business_id = :id AND staff_id IS NULL ORDER BY weekday, start_time', ['id' => $businessId]),
            'closures' => Db::all(
                App::$pdo,
                'SELECT c.*, st.name AS staff_name FROM closures c LEFT JOIN staff st ON st.id = c.staff_id WHERE c.business_id = :id AND c.ends_at >= :now ORDER BY c.starts_at',
                ['id' => $businessId, 'now' => App::$clock->sql(App::$clock->now()->modify('-1 day'))],
            ),
            'staff' => Db::all(App::$pdo, 'SELECT id, name FROM staff WHERE business_id = :id AND active = 1 ORDER BY name', ['id' => $businessId]),
        ]);
    }

    public function saveSettings(): void
    {
        $workspace = Guard::workspace(true);
        $businessId = (int) $workspace['business']['id'];
        $logo = Brand::uploaded();
        $removeLogo = Input::str('remove_logo', 3) === '1';
        $profile = [
            'name' => Input::str('name', 140, true),
            'tagline' => Input::str('tagline', 180),
            'description' => Input::str('description', 2000),
            'address' => Input::str('address', 180),
            'city' => Input::str('city', 80),
            'phone' => Input::str('phone', 40),
            'email' => Input::email('email', false),
            'category' => Input::choice('category', array_map(static fn (Category $c): string => $c->value, Category::cases())),
            'cancel_hours' => Input::int('cancel_hours', 0, 168),
            'slot_minutes' => (int) Input::choice('slot_minutes', ['10', '15', '20', '30']),
            'threshold' => Input::int('reliability_threshold', 1, 100),
            'accent' => hex_color(Input::str('accent', 7)),
            'id' => $businessId,
        ];
        $rows = (new Catalog())->shiftsPublic();
        App::$pdo->beginTransaction();
        try {
            Db::run(App::$pdo, 'UPDATE businesses SET
                name = :name, tagline = :tagline, description = :description, address = :address, city = :city,
                phone = :phone, email = :email, category = :category, cancel_hours = :cancel_hours,
                slot_minutes = :slot_minutes, reliability_threshold = :threshold, accent = :accent
                WHERE id = :id', $profile);
            Db::run(App::$pdo, 'DELETE FROM weekly_hours WHERE business_id = :business AND staff_id IS NULL', ['business' => $businessId]);
            foreach ($rows as $row) {
                Db::run(App::$pdo, 'INSERT INTO weekly_hours (business_id, staff_id, weekday, start_time, end_time) VALUES (:business, NULL, :weekday, :start_time, :end_time)', [
                    'business' => $businessId,
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
        if ($removeLogo) {
            Brand::delete($businessId);
            Db::run(App::$pdo, 'UPDATE businesses SET logo_ext = NULL WHERE id = :id', ['id' => $businessId]);
        }
        if ($logo !== null) {
            Brand::store($businessId, $logo['ext'], $logo['bytes']);
            Db::run(App::$pdo, 'UPDATE businesses SET logo_ext = :ext WHERE id = :id', ['ext' => $logo['ext'], 'id' => $businessId]);
        }
        flash('ok', 'Impostazioni salvate.');
        go('/app/impostazioni');
    }

    public function saveClosure(): void
    {
        $workspace = Guard::workspace(true);
        $zone = App::$engine->zone($workspace['business']);
        $date = Input::date('date');
        $start = App::$clock->at($date . ' ' . Input::time('start_time') . ':00', $zone);
        $end = App::$clock->at($date . ' ' . Input::time('end_time') . ':00', $zone);
        if ($end <= $start) {
            throw new DomainException('validation', 'La chiusura deve finire dopo l\'inizio.');
        }
        $staffId = Input::int('staff_id', 0, 999999999, false);
        if ($staffId > 0) {
            $staff = Db::one(App::$pdo, 'SELECT id FROM staff WHERE id = :id AND business_id = :business', ['id' => $staffId, 'business' => (int) $workspace['business']['id']]);
            if ($staff === null) {
                $staffId = 0;
            }
        }
        Db::run(App::$pdo, 'INSERT INTO closures (business_id, staff_id, starts_at, ends_at, reason) VALUES (:business, :staff, :starts, :ends, :reason)', [
            'business' => (int) $workspace['business']['id'],
            'staff' => $staffId > 0 ? $staffId : null,
            'starts' => App::$clock->sql($start),
            'ends' => App::$clock->sql($end),
            'reason' => Input::str('reason', 180),
        ]);
        flash('ok', 'Chiusura segnata. Quegli orari non saranno più prenotabili.');
        go('/app/impostazioni');
        exit;
    }

    public function deleteClosure(string $id): void
    {
        $workspace = Guard::workspace(true);
        Db::run(App::$pdo, 'DELETE FROM closures WHERE id = :id AND business_id = :business', [
            'id' => (int) $id,
            'business' => (int) $workspace['business']['id'],
        ]);
        flash('ok', 'Chiusura rimossa.');
        go('/app/impostazioni');
        exit;
    }

    /** @return array<string, mixed> */
    private function sessionRow(int $id, int $businessId): array
    {
        $session = Db::one(
            App::$pdo,
            'SELECT s.*, sv.name AS service_name, st.name AS staff_name, r.name AS resource_name
             FROM class_sessions s
             INNER JOIN services sv ON sv.id = s.service_id
             INNER JOIN staff st ON st.id = s.staff_id
             LEFT JOIN resources r ON r.id = s.resource_id
             WHERE s.id = :id AND s.business_id = :business',
            ['id' => $id, 'business' => $businessId],
        );
        if ($session === null) {
            throw new HttpException(404, 'Corso non trovato.');
        }

        return $session;
    }
}
