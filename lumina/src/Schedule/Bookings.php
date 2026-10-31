<?php

declare(strict_types=1);

namespace Lumina\Schedule;

use DateTimeImmutable;
use DateTimeZone;
use Lumina\Clock;
use Lumina\Db;
use Lumina\DomainException;
use Lumina\Plan;
use Lumina\Policy;
use PDO;
use PDOException;

final class Bookings
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
        private Engine $engine,
    ) {
    }

    public function bookAppointment(
        int $businessId,
        int $serviceId,
        int $staffId,
        ?int $resourceId,
        int $clientId,
        DateTimeImmutable $startsAt,
        string $notes,
        bool $usePackage,
        ?int $seriesId = null,
        bool $allowPast = false,
    ): array {
        return $this->tx(function () use ($businessId, $serviceId, $staffId, $resourceId, $clientId, $startsAt, $notes, $usePackage, $seriesId, $allowPast): array {
            $this->lockStaff($staffId);

            return $this->insertAppointment(
                $businessId,
                $serviceId,
                $staffId,
                $resourceId,
                $clientId,
                $startsAt,
                $notes,
                $usePackage,
                $seriesId,
                $allowPast,
            );
        });
    }

    public function bookResolved(
        int $businessId,
        int $serviceId,
        int $staffId,
        ?int $resourceId,
        DateTimeImmutable $startsAt,
        string $name,
        ?string $email,
        string $phone,
        string $notes,
        bool $usePackage,
        ?int $userId,
    ): array {
        return $this->tx(function () use ($businessId, $serviceId, $staffId, $resourceId, $startsAt, $name, $email, $phone, $notes, $usePackage, $userId): array {
            $this->lockStaff($staffId);
            $client = $this->resolveClient($businessId, $name, $email, $phone, $userId);

            return $this->insertAppointment(
                $businessId,
                $serviceId,
                $staffId,
                $resourceId,
                (int) $client['id'],
                $startsAt,
                $notes,
                $usePackage,
                null,
                false,
            );
        });
    }

    /** @return array{series_id: int, booked: list<array<string, mixed>>, skipped: list<array{date: string, message: string}>} */
    public function bookSeries(
        int $businessId,
        int $serviceId,
        int $staffId,
        ?int $resourceId,
        int $clientId,
        DateTimeImmutable $firstStart,
        int $occurrences,
        string $notes,
        bool $usePackage,
    ): array {
        Plan::assertFeature($this->pdo, $businessId, 'series');
        $occurrences = max(2, min(Policy::MAX_SERIES, $occurrences));
        $business = $this->business($businessId);
        $zone = $this->engine->zone($business);
        $local = $this->clock->local($firstStart, $zone);
        $seriesId = $this->tx(function () use ($businessId, $local, $occurrences): int {
            Db::run(
                $this->pdo,
                'INSERT INTO series (business_id, weekday, time_start, occurrences, created_at)
                 VALUES (:business, :weekday, :time_start, :occurrences, :created)',
                [
                    'business' => $businessId,
                    'weekday' => (int) $local->format('N'),
                    'time_start' => $local->format('H:i:s'),
                    'occurrences' => $occurrences,
                    'created' => $this->clock->sql($this->clock->now()),
                ],
            );

            return Db::id($this->pdo);
        });
        $booked = [];
        $skipped = [];
        for ($i = 0; $i < $occurrences; $i++) {
            $when = $local->modify('+' . ($i * 7) . ' days');
            $instant = $this->clock->at($when->format('Y-m-d H:i:s'), $zone);
            try {
                $booked[] = $this->bookAppointment(
                    $businessId,
                    $serviceId,
                    $staffId,
                    $resourceId,
                    $clientId,
                    $instant,
                    $notes,
                    $usePackage,
                    $seriesId,
                    false,
                );
            } catch (DomainException $exception) {
                $skipped[] = ['date' => $when->format('Y-m-d'), 'message' => $exception->getMessage()];
            }
        }

        return ['series_id' => $seriesId, 'booked' => $booked, 'skipped' => $skipped];
    }

    public function cancelAppointment(int $appointmentId, int $businessId, bool $force, string $reason): ?array
    {
        $preview = Db::one(
            $this->pdo,
            'SELECT * FROM appointments WHERE id = :id AND business_id = :business',
            ['id' => $appointmentId, 'business' => $businessId],
        );
        if ($preview === null) {
            throw new DomainException('not_found', 'Appuntamento non trovato.');
        }

        return $this->tx(function () use ($preview, $force, $reason): ?array {
            $this->lockStaff((int) $preview['staff_id']);
            $appointment = Db::one(
                $this->pdo,
                'SELECT * FROM appointments WHERE id = :id FOR UPDATE',
                ['id' => (int) $preview['id']],
            );
            if ($appointment === null) {
                throw new DomainException('not_found', 'Appuntamento non trovato.');
            }
            if (in_array((string) $appointment['status'], ['cancelled', 'completed', 'no_show'], true)) {
                throw new DomainException('policy', 'Questo appuntamento non si può più annullare.');
            }
            if (!$force && (string) $appointment['status'] !== 'confirmed') {
                throw new DomainException('policy', 'L\'appuntamento è già iniziato. Chiedi in reception di annullarlo.');
            }
            $business = $this->business((int) $appointment['business_id']);
            $start = $this->clock->parseUtc((string) $appointment['starts_at']);
            $seconds = $start->getTimestamp() - $this->clock->now()->getTimestamp();
            if (!$force && $seconds < (int) $business['cancel_hours'] * 3600) {
                throw new DomainException(
                    'policy',
                    'Puoi annullare gratis solo fino a ' . (int) $business['cancel_hours'] . ' ore prima. Per spostarlo contatta l\'attività.',
                );
            }
            if ($appointment['client_package_id'] !== null) {
                $this->refundPackage((int) $appointment['client_package_id']);
            }
            Db::run(
                $this->pdo,
                'UPDATE appointments
                 SET status = \'cancelled\', cancel_reason = :reason, client_package_id = NULL
                 WHERE id = :id',
                ['reason' => mb_substr($reason, 0, 180), 'id' => (int) $appointment['id']],
            );
            $offer = $this->offerSlot(
                (int) $appointment['business_id'],
                (int) $appointment['service_id'],
                (int) $appointment['staff_id'],
                $start,
                (int) $appointment['client_id'],
            );
            $this->notifyOwner(
                (int) $appointment['business_id'],
                'Prenotazione annullata',
                'Si è liberato un orario. La lista d\'attesa è stata controllata.',
                '/app/attesa',
            );

            return $offer;
        });
    }

    public function setStatus(int $appointmentId, int $businessId, string $status): void
    {
        $allowed = [
            'confirmed' => ['arrived', 'in_progress', 'completed', 'no_show'],
            'arrived' => ['in_progress', 'completed', 'no_show'],
            'in_progress' => ['completed', 'no_show'],
        ];
        $preview = $this->appointment($appointmentId, $businessId);
        $this->tx(function () use ($preview, $status, $allowed): void {
            $this->lockStaff((int) $preview['staff_id']);
            $appointment = Db::one($this->pdo, 'SELECT * FROM appointments WHERE id = :id FOR UPDATE', ['id' => (int) $preview['id']]);
            if ($appointment === null) {
                throw new DomainException('not_found', 'Appuntamento non trovato.');
            }
            $from = (string) $appointment['status'];
            if (!in_array($status, $allowed[$from] ?? [], true)) {
                throw new DomainException('policy', 'Passaggio di stato non consentito.');
            }
            Db::run(
                $this->pdo,
                'UPDATE appointments SET status = :status WHERE id = :id',
                ['status' => $status, 'id' => (int) $appointment['id']],
            );
        });
    }

    public function refundAppointmentCredit(int $appointmentId, int $businessId): bool
    {
        $preview = $this->appointment($appointmentId, $businessId);

        return $this->tx(function () use ($preview): bool {
            $appointment = Db::one($this->pdo, 'SELECT * FROM appointments WHERE id = :id FOR UPDATE', ['id' => (int) $preview['id']]);
            if ($appointment === null || $appointment['client_package_id'] === null) {
                throw new DomainException('validation', 'Non c\'è un credito di tessera da restituire.');
            }
            $extended = $this->refundPackage((int) $appointment['client_package_id']);
            Db::run(
                $this->pdo,
                'UPDATE appointments SET client_package_id = NULL WHERE id = :id',
                ['id' => (int) $appointment['id']],
            );

            return $extended;
        });
    }

    /** @return array{kept: int, missed: int, sample: int, percent: ?int, advise_deposit: bool} */
    public function reliability(int $clientId, int $threshold): array
    {
        $row = Db::one(
            $this->pdo,
            "SELECT
                COALESCE(SUM(status = 'completed'), 0) AS kept,
                COALESCE(SUM(status = 'no_show'), 0) AS missed
             FROM appointments WHERE client_id = :client",
            ['client' => $clientId],
        );
        $kept = (int) ($row['kept'] ?? 0);
        $missed = (int) ($row['missed'] ?? 0);
        $sample = $kept + $missed;
        $percent = $sample >= Policy::MIN_SAMPLE ? (int) round($kept / $sample * 100) : null;

        return [
            'kept' => $kept,
            'missed' => $missed,
            'sample' => $sample,
            'percent' => $percent,
            'advise_deposit' => $percent !== null && $percent < $threshold,
        ];
    }

    public function joinWaitlist(
        int $businessId,
        int $serviceId,
        ?int $staffId,
        string $name,
        string $email,
        string $phone,
        string $date,
        string $timeFrom,
        string $timeTo,
        ?int $userId,
    ): array {
        Plan::assertFeature($this->pdo, $businessId, 'waitlist');
        if ($timeFrom >= $timeTo) {
            throw new DomainException('validation', 'L\'orario di fine deve essere successivo all\'inizio.');
        }
        $service = $this->service($businessId, $serviceId);
        if ((string) $service['kind'] !== 'appointment') {
            throw new DomainException('validation', 'I corsi si prenotano dalla sezione corsi.');
        }

        return $this->tx(function () use ($businessId, $serviceId, $staffId, $name, $email, $phone, $date, $timeFrom, $timeTo, $userId): array {
            if ($staffId !== null) {
                $this->lockStaff($staffId);
            }
            $client = $this->resolveClient($businessId, $name, $email, $phone, $userId);
            $existing = Db::one(
                $this->pdo,
                "SELECT id FROM waitlist
                 WHERE client_id = :client AND service_id = :service AND preferred_date = :date
                   AND status IN ('waiting', 'offered')
                 LIMIT 1",
                ['client' => (int) $client['id'], 'service' => $serviceId, 'date' => $date],
            );
            if ($existing !== null) {
                throw new DomainException('policy', 'Sei già in lista per questo servizio in quel giorno.');
            }
            $token = $this->token();
            Db::run(
                $this->pdo,
                'INSERT INTO waitlist (
                    business_id, service_id, staff_id, client_id, preferred_date, time_from, time_to,
                    status, public_token, created_at
                 ) VALUES (
                    :business, :service, :staff, :client, :date, :time_from, :time_to,
                    \'waiting\', :token, :created
                 )',
                [
                    'business' => $businessId,
                    'service' => $serviceId,
                    'staff' => $staffId,
                    'client' => (int) $client['id'],
                    'date' => $date,
                    'time_from' => $timeFrom . ':00',
                    'time_to' => $timeTo . ':00',
                    'token' => $token,
                    'created' => $this->clock->sql($this->clock->now()),
                ],
            );
            $id = Db::id($this->pdo);
            $this->notifyOwner($businessId, 'Nuova richiesta in lista d\'attesa', $name . ' aspetta un posto.', '/app/attesa');

            return Db::one($this->pdo, 'SELECT * FROM waitlist WHERE id = :id', ['id' => $id]) ?? [];
        });
    }

    public function acceptOffer(int $waitlistId, bool $usePackage): array
    {
        $preview = Db::one($this->pdo, 'SELECT * FROM waitlist WHERE id = :id', ['id' => $waitlistId]);
        if ($preview === null) {
            throw new DomainException('not_found', 'Richiesta non trovata.');
        }
        $staffId = (int) ($preview['staff_id'] ?? 0);
        if ($staffId <= 0 || $preview['offer_starts_at'] === null) {
            throw new DomainException('conflict', 'Questa offerta non ha più un orario assegnato.');
        }

        $this->pdo->beginTransaction();
        $committed = false;
        try {
            $this->lockStaff($staffId);
            $row = Db::one($this->pdo, 'SELECT * FROM waitlist WHERE id = :id FOR UPDATE', ['id' => $waitlistId]);
            if ($row === null || (string) $row['status'] !== 'offered' || $row['offer_starts_at'] === null) {
                throw new DomainException('policy', 'L\'offerta non è più attiva.');
            }
            $expires = $this->clock->parseUtc((string) $row['offer_expires_at']);
            if ($expires <= $this->clock->now()) {
                throw new DomainException('policy', 'L\'offerta è scaduta.');
            }
            try {
                $appointment = $this->insertAppointment(
                    (int) $row['business_id'],
                    (int) $row['service_id'],
                    (int) $row['staff_id'],
                    null,
                    (int) $row['client_id'],
                    $this->clock->parseUtc((string) $row['offer_starts_at']),
                    '',
                    $usePackage,
                    null,
                    false,
                );
            } catch (DomainException $exception) {
                if ($exception->reason !== 'conflict') {
                    throw $exception;
                }
                Db::run(
                    $this->pdo,
                    "UPDATE waitlist SET status = 'expired' WHERE id = :id",
                    ['id' => $waitlistId],
                );
                $this->pdo->commit();
                $committed = true;
                throw new DomainException('conflict', 'Questo orario è stato appena preso. L\'offerta è stata chiusa.');
            }
            Db::run(
                $this->pdo,
                "UPDATE waitlist SET status = 'booked', appointment_id = :appointment WHERE id = :id",
                ['appointment' => (int) $appointment['id'], 'id' => $waitlistId],
            );
            $this->pdo->commit();
            $committed = true;

            return $appointment;
        } catch (\Throwable $exception) {
            if (!$committed && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancelWaitlist(int $waitlistId, int $businessId): void
    {
        $this->tx(function () use ($waitlistId, $businessId): void {
            $row = Db::one(
                $this->pdo,
                'SELECT * FROM waitlist WHERE id = :id AND business_id = :business FOR UPDATE',
                ['id' => $waitlistId, 'business' => $businessId],
            );
            if ($row === null) {
                throw new DomainException('not_found', 'Richiesta non trovata.');
            }
            if (!in_array((string) $row['status'], ['waiting', 'offered'], true)) {
                throw new DomainException('policy', 'Questa richiesta è già chiusa.');
            }
            Db::run($this->pdo, "UPDATE waitlist SET status = 'cancelled' WHERE id = :id", ['id' => $waitlistId]);
        });
    }

    public function sweep(int $businessId): void
    {
        $now = $this->clock->sql($this->clock->now());
        $expired = Db::all(
            $this->pdo,
            "SELECT * FROM waitlist
             WHERE business_id = :business AND status = 'offered' AND offer_expires_at IS NOT NULL AND offer_expires_at <= :now",
            ['business' => $businessId, 'now' => $now],
        );
        foreach ($expired as $row) {
            $this->tx(function () use ($row): void {
                $staffId = (int) ($row['staff_id'] ?? 0);
                if ($staffId > 0) {
                    $this->lockStaff($staffId);
                }
                $current = Db::one($this->pdo, 'SELECT * FROM waitlist WHERE id = :id FOR UPDATE', ['id' => (int) $row['id']]);
                if ($current === null || (string) $current['status'] !== 'offered') {
                    return;
                }
                if ($this->clock->parseUtc((string) $current['offer_expires_at']) > $this->clock->now()) {
                    return;
                }
                Db::run($this->pdo, "UPDATE waitlist SET status = 'expired' WHERE id = :id", ['id' => (int) $current['id']]);
                if ($staffId > 0 && $current['offer_starts_at'] !== null) {
                    $this->offerSlot(
                        (int) $current['business_id'],
                        (int) $current['service_id'],
                        $staffId,
                        $this->clock->parseUtc((string) $current['offer_starts_at']),
                        0,
                    );
                }
            });
        }
    }

    public function scheduleSession(
        int $businessId,
        int $serviceId,
        int $staffId,
        ?int $resourceId,
        DateTimeImmutable $startsAt,
        int $capacity,
        string $notes,
    ): array {
        return $this->tx(function () use ($businessId, $serviceId, $staffId, $resourceId, $startsAt, $capacity, $notes): array {
            Plan::assertFeature($this->pdo, $businessId, 'classes');
            $staff = $this->lockStaff($staffId);
            if ((int) $staff['business_id'] !== $businessId || (int) $staff['active'] !== 1) {
                throw new DomainException('validation', 'Operatore non disponibile.');
            }
            $service = $this->service($businessId, $serviceId);
            if ((string) $service['kind'] !== 'class') {
                throw new DomainException('validation', 'Questo servizio non è un corso.');
            }
            $linked = Db::value(
                $this->pdo,
                'SELECT 1 FROM service_staff WHERE service_id = :service AND staff_id = :staff',
                ['service' => $serviceId, 'staff' => $staffId],
            );
            if ($linked === null) {
                throw new DomainException('validation', 'Questa persona non tiene il corso.');
            }
            $resource = $this->lockChosenResource($serviceId, $resourceId);
            $block = $this->blockFor($service, $startsAt);
            $this->assertInsideHours($businessId, $staffId, $block['block_starts_at'], $block['block_ends_at']);
            $this->assertFree($staffId, $resource, $block['block_starts_at'], $block['block_ends_at']);
            $capacity = max(1, min(80, $capacity));
            Db::run(
                $this->pdo,
                'INSERT INTO class_sessions (
                    business_id, service_id, staff_id, resource_id, starts_at, ends_at, block_starts_at, block_ends_at,
                    capacity, price_cents, status, notes
                 ) VALUES (
                    :business, :service, :staff, :resource, :starts, :ends, :block_starts, :block_ends,
                    :capacity, :price, \'scheduled\', :notes
                 )',
                [
                    'business' => $businessId,
                    'service' => $serviceId,
                    'staff' => $staffId,
                    'resource' => $resource === null ? null : (int) $resource['id'],
                    'starts' => $this->clock->sql($block['starts_at']),
                    'ends' => $this->clock->sql($block['ends_at']),
                    'block_starts' => $this->clock->sql($block['block_starts_at']),
                    'block_ends' => $this->clock->sql($block['block_ends_at']),
                    'capacity' => $capacity,
                    'price' => (int) $service['price_cents'],
                    'notes' => mb_substr($notes, 0, 300),
                ],
            );

            return Db::one($this->pdo, 'SELECT * FROM class_sessions WHERE id = :id', ['id' => Db::id($this->pdo)]) ?? [];
        });
    }

    public function setSessionStatus(int $sessionId, int $businessId, string $status): void
    {
        if (!in_array($status, ['cancelled', 'completed'], true)) {
            throw new DomainException('validation', 'Stato del corso non valido.');
        }
        $this->tx(function () use ($sessionId, $businessId, $status): void {
            $session = Db::one(
                $this->pdo,
                'SELECT * FROM class_sessions WHERE id = :id AND business_id = :business FOR UPDATE',
                ['id' => $sessionId, 'business' => $businessId],
            );
            if ($session === null || (string) $session['status'] !== 'scheduled') {
                throw new DomainException('policy', 'Il corso non è modificabile.');
            }
            if ($status === 'cancelled') {
                $bookings = Db::all(
                    $this->pdo,
                    "SELECT * FROM class_bookings WHERE session_id = :session AND status = 'booked' FOR UPDATE",
                    ['session' => $sessionId],
                );
                foreach ($bookings as $booking) {
                    if ($booking['client_package_id'] !== null) {
                        $this->refundPackage((int) $booking['client_package_id']);
                    }
                    Db::run(
                        $this->pdo,
                        "UPDATE class_bookings SET status = 'cancelled', client_package_id = NULL WHERE id = :id",
                        ['id' => (int) $booking['id']],
                    );
                }
            }
            Db::run(
                $this->pdo,
                'UPDATE class_sessions SET status = :status WHERE id = :id',
                ['status' => $status, 'id' => $sessionId],
            );
        });
    }

    public function bookClass(int $sessionId, int $clientId, bool $usePackage): array
    {
        return $this->tx(function () use ($sessionId, $clientId, $usePackage): array {
            $session = Db::one($this->pdo, 'SELECT * FROM class_sessions WHERE id = :id FOR UPDATE', ['id' => $sessionId]);
            if ($session === null || (string) $session['status'] !== 'scheduled') {
                throw new DomainException('conflict', 'Il corso non è prenotabile.');
            }
            Plan::assertFeature($this->pdo, (int) $session['business_id'], 'classes');
            if ($this->clock->parseUtc((string) $session['starts_at']) <= $this->clock->now()) {
                throw new DomainException('policy', 'Il corso è già iniziato.');
            }
            $client = Db::one($this->pdo, 'SELECT * FROM clients WHERE id = :id', ['id' => $clientId]);
            if ($client === null || (int) $client['business_id'] !== (int) $session['business_id']) {
                throw new DomainException('validation', 'Cliente non trovato.');
            }
            $this->assertClientFree((int) $client['id'], $this->clock->parseUtc((string) $session['block_starts_at']), $this->clock->parseUtc((string) $session['block_ends_at']));
            $taken = Db::all(
                $this->pdo,
                "SELECT id FROM class_bookings WHERE session_id = :session AND status = 'booked' FOR UPDATE",
                ['session' => $sessionId],
            );
            $existing = Db::one(
                $this->pdo,
                'SELECT * FROM class_bookings WHERE session_id = :session AND client_id = :client FOR UPDATE',
                ['session' => $sessionId, 'client' => $clientId],
            );
            if ($existing !== null && (string) $existing['status'] !== 'cancelled') {
                throw new DomainException('policy', 'Questa persona è già iscritta.');
            }
            if (count($taken) >= (int) $session['capacity']) {
                throw new DomainException('conflict', 'Il corso è al completo.');
            }
            $packageId = null;
            $price = (int) $session['price_cents'];
            $label = '';
            if ($usePackage) {
                $package = $this->lockPackage((int) $client['id'], (int) $session['service_id']);
                $this->consumePackage((int) $package['id']);
                $packageId = (int) $package['id'];
                $price = 0;
                $label = 'Tessera';
            }
            if ($existing === null) {
                Db::run(
                    $this->pdo,
                    'INSERT INTO class_bookings (
                        session_id, client_id, status, price_cents, price_label, client_package_id, public_token, created_at
                     ) VALUES (
                        :session, :client, \'booked\', :price, :label, :package, :token, :created
                     )',
                    [
                        'session' => $sessionId,
                        'client' => $clientId,
                        'price' => $price,
                        'label' => $label,
                        'package' => $packageId,
                        'token' => $this->token(),
                        'created' => $this->clock->sql($this->clock->now()),
                    ],
                );
                $id = Db::id($this->pdo);
            } else {
                Db::run(
                    $this->pdo,
                    "UPDATE class_bookings
                     SET status = 'booked', price_cents = :price, price_label = :label, client_package_id = :package
                     WHERE id = :id",
                    [
                        'price' => $price,
                        'label' => $label,
                        'package' => $packageId,
                        'id' => (int) $existing['id'],
                    ],
                );
                $id = (int) $existing['id'];
            }

            return Db::one($this->pdo, 'SELECT * FROM class_bookings WHERE id = :id', ['id' => $id]) ?? [];
        });
    }

    public function bookClassGuest(
        int $sessionId,
        string $name,
        string $email,
        string $phone,
        bool $usePackage,
        ?int $userId,
    ): array {
        $session = Db::one($this->pdo, 'SELECT * FROM class_sessions WHERE id = :id', ['id' => $sessionId]);
        if ($session === null) {
            throw new DomainException('not_found', 'Corso non trovato.');
        }

        return $this->tx(function () use ($session, $name, $email, $phone, $usePackage, $userId): array {
            $client = $this->resolveClient((int) $session['business_id'], $name, $email, $phone, $userId);

            return $this->bookClass((int) $session['id'], (int) $client['id'], $usePackage);
        });
    }

    public function setClassBookingStatus(int $bookingId, int $businessId, string $status): void
    {
        if (!in_array($status, ['attended', 'no_show', 'cancelled'], true)) {
            throw new DomainException('validation', 'Stato iscrizione non valido.');
        }
        $this->tx(function () use ($bookingId, $businessId, $status): void {
            $booking = $this->classBookingForBusiness($bookingId, $businessId, true);
            if ((string) $booking['status'] !== 'booked') {
                throw new DomainException('policy', 'Iscrizione non modificabile.');
            }
            if ($status === 'cancelled' && $booking['client_package_id'] !== null) {
                $this->refundPackage((int) $booking['client_package_id']);
            }
            Db::run(
                $this->pdo,
                'UPDATE class_bookings
                 SET status = :status, client_package_id = CASE WHEN :cancelled = 1 THEN NULL ELSE client_package_id END
                 WHERE id = :id',
                [
                    'status' => $status,
                    'cancelled' => $status === 'cancelled' ? 1 : 0,
                    'id' => $bookingId,
                ],
            );
        });
    }

    public function refundClassCredit(int $bookingId, int $businessId): bool
    {
        return $this->tx(function () use ($bookingId, $businessId): bool {
            $booking = $this->classBookingForBusiness($bookingId, $businessId, true);
            if ($booking['client_package_id'] === null) {
                throw new DomainException('validation', 'Non c\'è un credito di tessera da restituire.');
            }
            $extended = $this->refundPackage((int) $booking['client_package_id']);
            Db::run(
                $this->pdo,
                'UPDATE class_bookings SET client_package_id = NULL WHERE id = :id',
                ['id' => $bookingId],
            );

            return $extended;
        });
    }

    public function ensureClient(int $businessId, string $name, ?string $email, string $phone, ?int $userId): array
    {
        return $this->tx(fn (): array => $this->resolveClient($businessId, $name, $email, $phone, $userId));
    }

    public function sellPackage(int $businessId, int $clientId, int $packageId): array
    {
        return $this->tx(function () use ($businessId, $clientId, $packageId): array {
            Plan::assertFeature($this->pdo, $businessId, 'packages');
            $client = Db::one(
                $this->pdo,
                'SELECT * FROM clients WHERE id = :id AND business_id = :business FOR UPDATE',
                ['id' => $clientId, 'business' => $businessId],
            );
            $package = Db::one(
                $this->pdo,
                'SELECT * FROM packages WHERE id = :id AND business_id = :business AND active = 1',
                ['id' => $packageId, 'business' => $businessId],
            );
            if ($client === null || $package === null) {
                throw new DomainException('not_found', 'Tessera o cliente non trovati.');
            }
            $now = $this->clock->now();
            $expires = $now->modify('+' . (int) $package['valid_days'] . ' days');
            Db::run(
                $this->pdo,
                'INSERT INTO client_packages (
                    client_id, package_id, credits_total, credits_left, purchased_at, expires_at
                 ) VALUES (
                    :client, :package, :total, :left, :purchased, :expires
                 )',
                [
                    'client' => $clientId,
                    'package' => $packageId,
                    'total' => (int) $package['credits'],
                    'left' => (int) $package['credits'],
                    'purchased' => $this->clock->sql($now),
                    'expires' => $this->clock->sql($expires),
                ],
            );

            return Db::one($this->pdo, 'SELECT * FROM client_packages WHERE id = :id', ['id' => Db::id($this->pdo)]) ?? [];
        });
    }

    /** @return array<string, mixed> */
    private function insertAppointment(
        int $businessId,
        int $serviceId,
        int $staffId,
        ?int $resourceId,
        int $clientId,
        DateTimeImmutable $startsAt,
        string $notes,
        bool $usePackage,
        ?int $seriesId,
        bool $allowPast,
    ): array {
        $staff = Db::one($this->pdo, 'SELECT * FROM staff WHERE id = :id', ['id' => $staffId]);
        if ($staff === null || (int) $staff['business_id'] !== $businessId || (int) $staff['active'] !== 1) {
            throw new DomainException('validation', 'Operatore non disponibile.');
        }
        $service = $this->service($businessId, $serviceId);
        if ((string) $service['kind'] !== 'appointment') {
            throw new DomainException('validation', 'I corsi si prenotano dalla sezione corsi.');
        }
        $linked = Db::value(
            $this->pdo,
            'SELECT 1 FROM service_staff WHERE service_id = :service AND staff_id = :staff',
            ['service' => $serviceId, 'staff' => $staffId],
        );
        if ($linked === null) {
            throw new DomainException('validation', 'Questa persona non esegue il servizio.');
        }
        $client = Db::one($this->pdo, 'SELECT * FROM clients WHERE id = :id FOR UPDATE', ['id' => $clientId]);
        if ($client === null || (int) $client['business_id'] !== $businessId) {
            throw new DomainException('validation', 'Cliente non trovato.');
        }
        $business = $this->business($businessId);
        Plan::assertBooking($this->pdo, $this->clock, $business);
        if (!$allowPast && $startsAt <= $this->clock->now()) {
            throw new DomainException('policy', 'Non si può prenotare un orario già iniziato.');
        }
        $this->lockLinkedResources($serviceId, $resourceId);
        $slot = $this->engine->quote($businessId, $serviceId, $staffId, $resourceId, $startsAt, $allowPast);
        if ($slot === null) {
            throw new DomainException('conflict', 'Questo orario non è più disponibile. Scegline un altro.');
        }
        $resource = null;
        if ($slot['resource_id'] !== null) {
            $resource = Db::one($this->pdo, 'SELECT * FROM resources WHERE id = :id FOR UPDATE', ['id' => (int) $slot['resource_id']]);
        }
        $this->assertFree($staffId, $resource, $slot['block_starts_at'], $slot['block_ends_at']);
        $this->assertClientFree($clientId, $slot['block_starts_at'], $slot['block_ends_at']);

        $price = (int) $slot['price_cents'];
        $label = (string) $slot['price_label'];
        $packageId = null;
        if ($usePackage) {
            $package = $this->lockPackage($clientId, $serviceId);
            $this->consumePackage((int) $package['id']);
            $packageId = (int) $package['id'];
            $price = 0;
            $label = 'Tessera';
        }
        $score = $this->reliability($clientId, (int) $business['reliability_threshold']);
        $token = $this->token();
        Db::run(
            $this->pdo,
            'INSERT INTO appointments (
                business_id, service_id, staff_id, resource_id, client_id,
                starts_at, ends_at, block_starts_at, block_ends_at, status,
                price_cents, list_price_cents, price_label, slot_quality, deposit_advised,
                client_package_id, series_id, public_token, notes, created_at
             ) VALUES (
                :business, :service, :staff, :resource, :client,
                :starts, :ends, :block_starts, :block_ends, \'confirmed\',
                :price, :list_price, :label, :quality, :deposit,
                :package, :series, :token, :notes, :created
             )',
            [
                'business' => $businessId,
                'service' => $serviceId,
                'staff' => $staffId,
                'resource' => $slot['resource_id'],
                'client' => $clientId,
                'starts' => $this->clock->sql($slot['starts_at']),
                'ends' => $this->clock->sql($slot['ends_at']),
                'block_starts' => $this->clock->sql($slot['block_starts_at']),
                'block_ends' => $this->clock->sql($slot['block_ends_at']),
                'price' => $price,
                'list_price' => (int) $slot['list_price_cents'],
                'label' => mb_substr($label, 0, 80),
                'quality' => (string) $slot['quality'],
                'deposit' => $score['advise_deposit'] ? 1 : 0,
                'package' => $packageId,
                'series' => $seriesId,
                'token' => $token,
                'notes' => mb_substr($notes, 0, 500),
                'created' => $this->clock->sql($this->clock->now()),
            ],
        );
        $id = Db::id($this->pdo);
        Db::run(
            $this->pdo,
            'UPDATE clients SET preferred_staff_id = :staff WHERE id = :id',
            ['staff' => $staffId, 'id' => $clientId],
        );
        $when = $this->clock->local($slot['starts_at'], $this->engine->zone($business))->format('d/m H:i');
        $this->notifyOwner($businessId, 'Nuova prenotazione', $client['name'] . ' · ' . $service['name'] . ' · ' . $when, '/app/appuntamenti/' . $id);
        if ($client['user_id'] !== null) {
            $this->notify((int) $client['user_id'], $businessId, 'Prenotazione confermata', $service['name'] . ' · ' . $when, '/p/' . $token);
        }

        return Db::one($this->pdo, 'SELECT * FROM appointments WHERE id = :id', ['id' => $id]) ?? [];
    }

    /** @return array<string, mixed>|null */
    private function offerSlot(int $businessId, int $serviceId, int $staffId, DateTimeImmutable $start, int $excludeClientId): ?array
    {
        $slot = $this->engine->quote($businessId, $serviceId, $staffId, null, $start, false);
        if ($slot === null) {
            return null;
        }
        $business = $this->business($businessId);
        $local = $this->clock->local($start, $this->engine->zone($business));
        $candidates = Db::all(
            $this->pdo,
            "SELECT * FROM waitlist
             WHERE business_id = :business AND service_id = :service AND status = 'waiting'
               AND preferred_date = :date
               AND time_from <= :slot_from AND time_to >= :slot_to
               AND (staff_id IS NULL OR staff_id = :staff)
               AND client_id <> :exclude
             ORDER BY created_at, id
             FOR UPDATE",
            [
                'business' => $businessId,
                'service' => $serviceId,
                'date' => $local->format('Y-m-d'),
                'slot_from' => $local->format('H:i:s'),
                'slot_to' => $local->format('H:i:s'),
                'staff' => $staffId,
                'exclude' => $excludeClientId,
            ],
        );
        $expires = $this->clock->now()->modify('+' . Policy::OFFER_MINUTES . ' minutes');
        if ($expires > $start) {
            $expires = $start;
        }
        if ($expires <= $this->clock->now()) {
            return null;
        }
        foreach ($candidates as $candidate) {
            if ($this->clientOverlaps((int) $candidate['client_id'], $slot['block_starts_at'], $slot['block_ends_at'])) {
                continue;
            }
            Db::run(
                $this->pdo,
                "UPDATE waitlist
                 SET status = 'offered', staff_id = :staff, offer_starts_at = :starts, offer_expires_at = :expires
                 WHERE id = :id AND status = 'waiting'",
                [
                    'staff' => $staffId,
                    'starts' => $this->clock->sql($start),
                    'expires' => $this->clock->sql($expires),
                    'id' => (int) $candidate['id'],
                ],
            );
            $client = Db::one($this->pdo, 'SELECT * FROM clients WHERE id = :id', ['id' => (int) $candidate['client_id']]);
            $href = '/w/' . $candidate['public_token'];
            if ($client !== null && $client['user_id'] !== null) {
                $this->notify((int) $client['user_id'], $businessId, 'Si è liberato un posto', 'Hai un\'offerta a tempo. Aprilo prima che scada.', $href);
            }
            $this->notifyOwner($businessId, 'Offerta inviata dalla lista d\'attesa', 'Un posto libero è stato proposto al primo in lista.', '/app/attesa');

            return Db::one($this->pdo, 'SELECT * FROM waitlist WHERE id = :id', ['id' => (int) $candidate['id']]);
        }

        return null;
    }

    /** @param array<string, mixed>|null $resource */
    private function assertFree(int $staffId, ?array $resource, DateTimeImmutable $blockStart, DateTimeImmutable $blockEnd, int $ignoreAppointmentId = 0, int $ignoreSessionId = 0): void
    {
        $params = [
            'staff' => $staffId,
            'end_at' => $this->clock->sql($blockEnd),
            'start_at' => $this->clock->sql($blockStart),
            'ignore_appointment' => $ignoreAppointmentId,
        ];
        $hit = Db::one(
            $this->pdo,
            "SELECT id FROM appointments
             WHERE staff_id = :staff AND status <> 'cancelled'
               AND block_starts_at < :end_at AND block_ends_at > :start_at
               AND id <> :ignore_appointment
             LIMIT 1 FOR UPDATE",
            $params,
        );
        if ($hit !== null) {
            throw new DomainException('conflict', 'Questo orario non è più disponibile. Scegline un altro.');
        }
        $classHit = Db::one(
            $this->pdo,
            "SELECT id FROM class_sessions
             WHERE staff_id = :staff AND status <> 'cancelled'
               AND block_starts_at < :end_at AND block_ends_at > :start_at
               AND id <> :ignore_session
             LIMIT 1 FOR UPDATE",
            [
                'staff' => $staffId,
                'end_at' => $params['end_at'],
                'start_at' => $params['start_at'],
                'ignore_session' => $ignoreSessionId,
            ],
        );
        if ($classHit !== null) {
            throw new DomainException('conflict', 'La persona è già impegnata in un corso.');
        }
        if ($resource === null) {
            return;
        }
        $resourceHits = Db::all(
            $this->pdo,
            "SELECT id FROM appointments
             WHERE resource_id = :resource AND status <> 'cancelled'
               AND block_starts_at < :end_at AND block_ends_at > :start_at
               AND id <> :ignore_appointment
             FOR UPDATE",
            [
                'resource' => (int) $resource['id'],
                'end_at' => $params['end_at'],
                'start_at' => $params['start_at'],
                'ignore_appointment' => $ignoreAppointmentId,
            ],
        );
        $sessionHits = Db::all(
            $this->pdo,
            "SELECT id FROM class_sessions
             WHERE resource_id = :resource AND status <> 'cancelled'
               AND block_starts_at < :end_at AND block_ends_at > :start_at
               AND id <> :ignore_session
             FOR UPDATE",
            [
                'resource' => (int) $resource['id'],
                'end_at' => $params['end_at'],
                'start_at' => $params['start_at'],
                'ignore_session' => $ignoreSessionId,
            ],
        );
        if (count($resourceHits) + count($sessionHits) >= (int) $resource['capacity']) {
            throw new DomainException('conflict', 'La postazione è già occupata in quell\'orario.');
        }
    }

    private function assertClientFree(int $clientId, DateTimeImmutable $blockStart, DateTimeImmutable $blockEnd): void
    {
        if ($this->clientOverlaps($clientId, $blockStart, $blockEnd)) {
            throw new DomainException('policy', 'C\'è già un altro impegno per questa persona in quell\'orario.');
        }
    }

    private function clientOverlaps(int $clientId, DateTimeImmutable $blockStart, DateTimeImmutable $blockEnd): bool
    {
        $params = [
            'client' => $clientId,
            'end_at' => $this->clock->sql($blockEnd),
            'start_at' => $this->clock->sql($blockStart),
        ];
        $appointment = Db::one(
            $this->pdo,
            "SELECT a.id FROM appointments a
             WHERE a.client_id = :client AND a.status NOT IN ('cancelled', 'no_show')
               AND a.block_starts_at < :end_at AND a.block_ends_at > :start_at
             LIMIT 1",
            $params,
        );
        if ($appointment !== null) {
            return true;
        }
        $class = Db::one(
            $this->pdo,
            "SELECT b.id FROM class_bookings b
             INNER JOIN class_sessions s ON s.id = b.session_id
             WHERE b.client_id = :client AND b.status = 'booked' AND s.status <> 'cancelled'
               AND s.block_starts_at < :end_at AND s.block_ends_at > :start_at
             LIMIT 1",
            $params,
        );

        return $class !== null;
    }

    private function assertInsideHours(int $businessId, int $staffId, DateTimeImmutable $blockStart, DateTimeImmutable $blockEnd): void
    {
        $business = $this->business($businessId);
        $zone = $this->engine->zone($business);
        $date = $this->clock->local($blockStart, $zone)->format('Y-m-d');
        $hours = Db::all($this->pdo, 'SELECT * FROM weekly_hours WHERE business_id = :business', ['business' => $businessId]);
        $staff = Db::one($this->pdo, 'SELECT * FROM staff WHERE id = :id', ['id' => $staffId]);
        if ($staff === null) {
            throw new DomainException('validation', 'Operatore non trovato.');
        }
        $own = array_values(array_filter(
            $hours,
            static fn (array $row): bool => $row['staff_id'] !== null && (int) $row['staff_id'] === $staffId,
        ));
        $source = $own !== [] ? $own : array_values(array_filter($hours, static fn (array $row): bool => $row['staff_id'] === null));
        $weekday = (int) $this->clock->local($blockStart, $zone)->format('N');
        $fits = false;
        foreach ($source as $row) {
            if ((int) $row['weekday'] !== $weekday) {
                continue;
            }
            $windowStart = $this->clock->at($date . ' ' . substr((string) $row['start_time'], 0, 8), $zone);
            $windowEnd = $this->clock->at($date . ' ' . substr((string) $row['end_time'], 0, 8), $zone);
            if ($blockStart >= $windowStart && $blockEnd <= $windowEnd) {
                $fits = true;
                break;
            }
        }
        if (!$fits) {
            throw new DomainException('policy', 'L\'orario cade fuori dal turno.');
        }
        $closure = Db::one(
            $this->pdo,
            'SELECT id FROM closures
             WHERE business_id = :business AND starts_at < :end_at AND ends_at > :start_at
               AND (staff_id IS NULL OR staff_id = :staff)
             LIMIT 1',
            [
                'business' => $businessId,
                'end_at' => $this->clock->sql($blockEnd),
                'start_at' => $this->clock->sql($blockStart),
                'staff' => $staffId,
            ],
        );
        if ($closure !== null) {
            throw new DomainException('policy', 'In quell\'orario c\'è una chiusura.');
        }
    }

    /** @param array<string, mixed> $service @return array{starts_at: DateTimeImmutable, ends_at: DateTimeImmutable, block_starts_at: DateTimeImmutable, block_ends_at: DateTimeImmutable} */
    private function blockFor(array $service, DateTimeImmutable $startsAt): array
    {
        $ends = $startsAt->modify('+' . (int) $service['duration_min'] . ' minutes');
        $blockStart = $startsAt->modify('-' . (int) $service['buffer_before'] . ' minutes');
        $blockEnd = $ends->modify('+' . (int) $service['buffer_after'] . ' minutes');

        return [
            'starts_at' => $startsAt,
            'ends_at' => $ends,
            'block_starts_at' => $blockStart,
            'block_ends_at' => $blockEnd,
        ];
    }

    /** @return array<string, mixed>|null */
    private function lockChosenResource(int $serviceId, ?int $resourceId): ?array
    {
        $linked = Db::all(
            $this->pdo,
            'SELECT r.* FROM resources r
             INNER JOIN service_resources sr ON sr.resource_id = r.id
             WHERE sr.service_id = :service AND r.active = 1
             ORDER BY r.id FOR UPDATE',
            ['service' => $serviceId],
        );
        if ($linked === [] && $resourceId === null) {
            return null;
        }
        if ($linked === []) {
            throw new DomainException('validation', 'Questo corso non ha una sala collegata.');
        }
        if ($resourceId === null) {
            throw new DomainException('validation', 'Scegli la sala o la postazione.');
        }
        foreach ($linked as $resource) {
            if ((int) $resource['id'] === $resourceId) {
                return $resource;
            }
        }
        throw new DomainException('validation', 'Postazione non valida per questo corso.');
    }

    private function lockLinkedResources(int $serviceId, ?int $resourceId): void
    {
        if ($resourceId !== null) {
            Db::one($this->pdo, 'SELECT id FROM resources WHERE id = :id FOR UPDATE', ['id' => $resourceId]);

            return;
        }
        Db::all(
            $this->pdo,
            'SELECT r.id FROM resources r
             INNER JOIN service_resources sr ON sr.resource_id = r.id
             WHERE sr.service_id = :service
             ORDER BY r.id FOR UPDATE',
            ['service' => $serviceId],
        );
    }

    /** @return array<string, mixed> */
    private function lockPackage(int $clientId, int $serviceId): array
    {
        $package = Db::one(
            $this->pdo,
            'SELECT cp.* FROM client_packages cp
             INNER JOIN packages p ON p.id = cp.package_id
             WHERE cp.client_id = :client AND cp.credits_left > 0 AND cp.expires_at > :now
               AND (p.service_id IS NULL OR p.service_id = :service)
             ORDER BY cp.expires_at, cp.id
             LIMIT 1 FOR UPDATE',
            [
                'client' => $clientId,
                'now' => $this->clock->sql($this->clock->now()),
                'service' => $serviceId,
            ],
        );
        if ($package === null) {
            throw new DomainException('validation', 'Nessuna tessera attiva per questo servizio.');
        }

        return $package;
    }

    private function consumePackage(int $clientPackageId): void
    {
        $statement = Db::run(
            $this->pdo,
            'UPDATE client_packages SET credits_left = credits_left - 1 WHERE id = :id AND credits_left > 0',
            ['id' => $clientPackageId],
        );
        if ($statement->rowCount() !== 1) {
            throw new DomainException('conflict', 'Il credito della tessera non è più disponibile.');
        }
    }

    private function refundPackage(int $clientPackageId): bool
    {
        $package = Db::one($this->pdo, 'SELECT * FROM client_packages WHERE id = :id FOR UPDATE', ['id' => $clientPackageId]);
        if ($package === null) {
            return false;
        }
        $expires = $this->clock->parseUtc((string) $package['expires_at']);
        $extended = false;
        if ($expires <= $this->clock->now()) {
            $expires = $this->clock->now()->modify('+7 days');
            $extended = true;
        }
        $left = min((int) $package['credits_total'], (int) $package['credits_left'] + 1);
        Db::run(
            $this->pdo,
            'UPDATE client_packages SET credits_left = :left, expires_at = :expires WHERE id = :id',
            ['left' => $left, 'expires' => $this->clock->sql($expires), 'id' => $clientPackageId],
        );

        return $extended;
    }

    /** @return array<string, mixed> */
    private function resolveClient(int $businessId, string $name, ?string $email, string $phone, ?int $userId): array
    {
        $email = $email !== null && $email !== '' ? mb_strtolower($email, 'UTF-8') : null;
        $existing = null;
        if ($email !== null) {
            $existing = Db::one(
                $this->pdo,
                'SELECT * FROM clients WHERE business_id = :business AND email = :email FOR UPDATE',
                ['business' => $businessId, 'email' => $email],
            );
        }
        if ($existing !== null) {
            if ($phone !== '') {
                Db::run($this->pdo, 'UPDATE clients SET phone = :phone WHERE id = :id', ['phone' => $phone, 'id' => (int) $existing['id']]);
            }
            if ($userId !== null && $existing['user_id'] === null) {
                Db::run($this->pdo, 'UPDATE clients SET user_id = :user WHERE id = :id', ['user' => $userId, 'id' => (int) $existing['id']]);
            }

            return Db::one($this->pdo, 'SELECT * FROM clients WHERE id = :id', ['id' => (int) $existing['id']]) ?? $existing;
        }
        try {
            Db::run(
                $this->pdo,
                'INSERT INTO clients (business_id, user_id, name, email, phone, notes, created_at)
                 VALUES (:business, :user, :name, :email, :phone, \'\', :created)',
                [
                    'business' => $businessId,
                    'user' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'created' => $this->clock->sql($this->clock->now()),
                ],
            );
        } catch (PDOException $exception) {
            if (!Db::duplicate($exception) || $email === null) {
                throw $exception;
            }

            return Db::one(
                $this->pdo,
                'SELECT * FROM clients WHERE business_id = :business AND email = :email',
                ['business' => $businessId, 'email' => $email],
            ) ?? throw new DomainException('conflict', 'Cliente già presente, riprova.');
        }

        return Db::one($this->pdo, 'SELECT * FROM clients WHERE id = :id', ['id' => Db::id($this->pdo)]) ?? [];
    }

    /** @return array<string, mixed> */
    private function classBookingForBusiness(int $bookingId, int $businessId, bool $lock): array
    {
        $sql = 'SELECT b.* FROM class_bookings b
                INNER JOIN class_sessions s ON s.id = b.session_id
                WHERE b.id = :id AND s.business_id = :business';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $booking = Db::one($this->pdo, $sql, ['id' => $bookingId, 'business' => $businessId]);
        if ($booking === null) {
            throw new DomainException('not_found', 'Iscrizione non trovata.');
        }

        return $booking;
    }

    /** @return array<string, mixed> */
    private function lockStaff(int $staffId): array
    {
        $staff = Db::one($this->pdo, 'SELECT * FROM staff WHERE id = :id FOR UPDATE', ['id' => $staffId]);
        if ($staff === null) {
            throw new DomainException('not_found', 'Operatore non trovato.');
        }

        return $staff;
    }

    /** @return array<string, mixed> */
    private function business(int $businessId): array
    {
        $business = Db::one($this->pdo, 'SELECT * FROM businesses WHERE id = :id', ['id' => $businessId]);
        if ($business === null) {
            throw new DomainException('not_found', 'Attività non trovata.');
        }

        return $business;
    }

    /** @return array<string, mixed> */
    private function service(int $businessId, int $serviceId): array
    {
        $service = Db::one(
            $this->pdo,
            'SELECT * FROM services WHERE id = :id AND business_id = :business AND active = 1',
            ['id' => $serviceId, 'business' => $businessId],
        );
        if ($service === null) {
            throw new DomainException('not_found', 'Servizio non trovato.');
        }

        return $service;
    }

    /** @return array<string, mixed> */
    private function appointment(int $appointmentId, int $businessId): array
    {
        $appointment = Db::one(
            $this->pdo,
            'SELECT * FROM appointments WHERE id = :id AND business_id = :business',
            ['id' => $appointmentId, 'business' => $businessId],
        );
        if ($appointment === null) {
            throw new DomainException('not_found', 'Appuntamento non trovato.');
        }

        return $appointment;
    }

    private function notifyOwner(int $businessId, string $title, string $body, string $href): void
    {
        $owner = Db::value($this->pdo, 'SELECT owner_user_id FROM businesses WHERE id = :id', ['id' => $businessId]);
        if ($owner !== null) {
            $this->notify((int) $owner, $businessId, $title, $body, $href);
        }
    }

    private function notify(int $userId, int $businessId, string $title, string $body, string $href): void
    {
        if ($userId <= 0) {
            return;
        }
        Db::run(
            $this->pdo,
            'INSERT INTO notifications (business_id, user_id, title, body, href, created_at)
             VALUES (:business, :user, :title, :body, :href, :created)',
            [
                'business' => $businessId,
                'user' => $userId,
                'title' => mb_substr($title, 0, 160),
                'body' => mb_substr($body, 0, 400),
                'href' => mb_substr($href, 0, 240),
                'created' => $this->clock->sql($this->clock->now()),
            ],
        );
    }

    private function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function tx(callable $fn): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $fn();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $fn();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
