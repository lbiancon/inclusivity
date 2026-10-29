<?php

declare(strict_types=1);

require_once __DIR__ . '/migrate.php';

use Lumina\App;
use Lumina\Clock;
use Lumina\Db;
use Lumina\DomainException;

function lumina_demo(bool $fresh = false): void
{
if ($fresh) {
    lumina_reset(App::$pdo);
    lumina_migrate(App::$pdo);
}

try {
    $existing = Db::one(App::$pdo, 'SELECT id FROM businesses WHERE slug = :slug', ['slug' => 'atelier-luce']);
} catch (PDOException) {
    lumina_migrate(App::$pdo);
    $existing = null;
}
if ($existing !== null) {
    if (PHP_SAPI === 'cli') {
        echo "Demo già presente. Usa php bin/seed.php --fresh per ricrearla.\n";
        exit(0);
    }

    return;
}

$clock = App::$clock;
$zone = $clock->zone();
$bookings = App::$bookings;
$password = password_hash('Lumina-demo-2026', PASSWORD_DEFAULT);

$coming = static function (array $weekdays, string $time, array $skip = []) use ($clock, $zone): DateTimeImmutable {
    $now = $clock->local($clock->now(), $zone);
    [$hour, $minute] = array_map(intval(...), explode(':', $time));
    for ($i = 0; $i < 28; $i++) {
        $day = $now->modify('+' . $i . ' days')->setTime($hour, $minute);
        if (!in_array((int) $day->format('N'), $weekdays, true)) {
            continue;
        }
        if ($day <= $now || in_array($day->format('Y-m-d'), $skip, true)) {
            continue;
        }

        return $day;
    }
    throw new RuntimeException('Nessun giorno aperto per ' . $time);
};

$past = static function (int $nth, string $time) use ($clock, $zone): DateTimeImmutable {
    $now = $clock->local($clock->now(), $zone);
    [$hour, $minute] = array_map(intval(...), explode(':', $time));
    $found = 0;
    for ($i = 1; $i < 40; $i++) {
        $day = $now->modify('-' . $i . ' days')->setTime($hour, $minute);
        $weekday = (int) $day->format('N');
        if ($weekday < 2 || $weekday > 6) {
            continue;
        }
        $found++;
        if ($found === $nth) {
            return $day;
        }
    }
    throw new RuntimeException('Nessun giorno passato.');
};

$instant = static function (DateTimeImmutable $local) use ($clock, $zone): DateTimeImmutable {
    return $clock->at($local->format('Y-m-d H:i:s'), $zone);
};

$userId = static function (string $name, string $email, string $role, string $phone) use ($password): int {
    Db::run(App::$pdo, 'INSERT INTO users (name, email, password_hash, phone, role, email_verified_at) VALUES (:name, :email, :hash, :phone, :role, :verified)', [
        'name' => $name,
        'email' => $email,
        'hash' => $password,
        'phone' => $phone,
        'role' => $role,
        'verified' => $clock->sql($clock->now()),
    ]);

    return Db::id(App::$pdo);
};

$hours = static function (int $businessId, ?int $staffId, array $weekdays, array $shifts): void {
    foreach ($weekdays as $day) {
        foreach ($shifts as [$start, $end]) {
            Db::run(App::$pdo, 'INSERT INTO weekly_hours (business_id, staff_id, weekday, start_time, end_time) VALUES (:business, :staff, :weekday, :start_time, :end_time)', [
                'business' => $businessId,
                'staff' => $staffId,
                'weekday' => $day,
                'start_time' => $start,
                'end_time' => $end,
            ]);
        }
    }
};

$giulia = $userId('Giulia Ferraro', 'giulia@atelierluce.test', 'owner', '+39 339 120 4401');
$marcoUser = $userId('Marco Silvestri', 'marco@atelierluce.test', 'staff', '+39 347 220 1184');
$elena = $userId('Elena Marini', 'elena@palestraborgo.test', 'owner', '+39 333 880 2210');
$biancaUser = $userId('Bianca Leone', 'bianca@email.test', 'client', '+39 328 441 9033');

Db::run(App::$pdo, 'INSERT INTO businesses (
    owner_user_id, name, slug, category, tagline, description, address, city, phone, email,
    timezone, cancel_hours, slot_minutes, reliability_threshold, plan, plan_interval
) VALUES (
    :owner, :name, :slug, :category, :tagline, :description, :address, :city, :phone, :email,
    :timezone, :cancel_hours, :slot_minutes, :threshold, :plan, :interval
)', [
    'owner' => $giulia,
    'name' => 'Atelier Luce',
    'slug' => 'atelier-luce',
    'category' => 'hair',
    'tagline' => 'Taglio, colore e un buco in meno in agenda.',
    'description' => 'Salone in centro a Carpineto Romano. Due poltrone, tre persone, orario spezzato. Gli slot ideali sono quelli che chiudono esattamente lo spazio tra un cliente e l\'altro.',
    'address' => 'Via del Corso 12',
    'city' => 'Carpineto Romano',
    'phone' => '+39 06 976 1102',
    'email' => 'giulia@atelierluce.test',
    'timezone' => 'Europe/Rome',
    'cancel_hours' => 12,
    'slot_minutes' => 15,
    'threshold' => 70,
    'plan' => 'salone',
    'interval' => 'month',
]);
$hair = Db::id(App::$pdo);

Db::run(App::$pdo, 'INSERT INTO businesses (
    owner_user_id, name, slug, category, tagline, description, address, city, phone, email,
    timezone, cancel_hours, slot_minutes, reliability_threshold, plan, plan_interval
) VALUES (
    :owner, :name, :slug, :category, :tagline, :description, :address, :city, :phone, :email,
    :timezone, :cancel_hours, :slot_minutes, :threshold, :plan, :interval
)', [
    'owner' => $elena,
    'name' => 'Palestra Borgo',
    'slug' => 'palestra-borgo',
    'category' => 'gym',
    'tagline' => 'Personal, yoga e un prezzo più basso la mattina.',
    'description' => 'Sala unica e un\'istruttrice. Il personal training costa meno tra le 7 e le 11: lo sconto è una regola, non un codice da ricordare.',
    'address' => 'Via della Fonte 4',
    'city' => 'Carpineto Romano',
    'phone' => '+39 06 976 3340',
    'email' => 'elena@palestraborgo.test',
    'timezone' => 'Europe/Rome',
    'cancel_hours' => 8,
    'slot_minutes' => 15,
    'threshold' => 70,
    'plan' => 'borgo',
    'interval' => 'year',
]);
$gym = Db::id(App::$pdo);

$hours($hair, null, [2, 3, 4, 5, 6], [['09:00:00', '13:00:00'], ['15:00:00', '19:00:00']]);
$hours($gym, null, [1, 2, 3, 4, 5], [['07:00:00', '13:00:00'], ['15:00:00', '21:00:00']]);

$staffId = static function (int $businessId, ?int $user, string $name, string $title, string $color, string $bio) : int {
    Db::run(App::$pdo, 'INSERT INTO staff (business_id, user_id, name, title, bio, color, active) VALUES (:business, :user, :name, :title, :bio, :color, 1)', [
        'business' => $businessId,
        'user' => $user,
        'name' => $name,
        'title' => $title,
        'bio' => $bio,
        'color' => $color,
    ]);

    return Db::id(App::$pdo);
};

$giuliaStaff = $staffId($hair, $giulia, 'Giulia Ferraro', 'Titolare', '#e23d12', 'Colore e taglio. Tiene la poltrona finestra.');
$marcoStaff = $staffId($hair, $marcoUser, 'Marco Silvestri', 'Taglio uomo', '#1e3a5f', 'Solo la mattina, dal martedì al sabato.');
$davideStaff = $staffId($hair, null, 'Davide Cola', 'Collaboratore', '#1c6b45', 'In salone, senza accesso all\'agenda.');
$elenaStaff = $staffId($gym, $elena, 'Elena Marini', 'Istruttrice', '#8a5a10', 'Personal e corsi in sala.');
$hours($hair, $marcoStaff, [2, 3, 4, 5, 6], [['09:00:00', '13:00:00']]);

$chair = static function (int $businessId, string $name) : int {
    Db::run(App::$pdo, 'INSERT INTO resources (business_id, name, kind, capacity, active) VALUES (:business, :name, :kind, 1, 1)', [
        'business' => $businessId,
        'name' => $name,
        'kind' => 'chair',
    ]);

    return Db::id(App::$pdo);
};
$window = $chair($hair, 'Poltrona finestra');
$mirror = $chair($hair, 'Poltrona specchio');
Db::run(App::$pdo, 'INSERT INTO resources (business_id, name, kind, capacity, active) VALUES (:business, :name, :kind, 1, 1)', [
    'business' => $gym,
    'name' => 'Sala borgo',
    'kind' => 'room',
]);
$room = Db::id(App::$pdo);

$serviceId = static function (int $businessId, string $name, string $description, int $duration, int $before, int $after, int $cents, string $kind, int $capacity) : int {
    Db::run(App::$pdo, 'INSERT INTO services (
        business_id, name, description, duration_min, buffer_before, buffer_after, price_cents, kind, capacity, active
    ) VALUES (
        :business, :name, :description, :duration, :before, :after, :price, :kind, :capacity, 1
    )', [
        'business' => $businessId,
        'name' => $name,
        'description' => $description,
        'duration' => $duration,
        'before' => $before,
        'after' => $after,
        'price' => $cents,
        'kind' => $kind,
        'capacity' => $capacity,
    ]);

    return Db::id(App::$pdo);
};

$taglioDonna = $serviceId($hair, 'Taglio donna', 'Taglio e piega veloce.', 45, 0, 15, 3500, 'appointment', 1);
$taglioUomo = $serviceId($hair, 'Taglio uomo', 'Forbice e macchinetta.', 30, 0, 10, 2200, 'appointment', 1);
$colore = $serviceId($hair, 'Colore', 'Applicazione e posa. Il margine copre il risciacquo.', 90, 0, 15, 7800, 'appointment', 1);
$piega = $serviceId($hair, 'Piega', 'Trenta minuti, senza margine.', 30, 0, 0, 2000, 'appointment', 1);
$lampo = $serviceId($hair, 'Trattamento lampo', 'Un\'ora esatta: è il servizio che chiude i buchi.', 60, 0, 0, 4800, 'appointment', 1);
$pt = $serviceId($gym, 'Personal training', 'Un\'ora in sala, a corpo libero o con i pesi.', 60, 0, 0, 4500, 'appointment', 1);
$consulenza = $serviceId($gym, 'Consulenza', 'Prima scheda e misure.', 45, 0, 0, 3500, 'appointment', 1);
$yoga = $serviceId($gym, 'Yoga', 'Lezione guidata, porta il tuo materassino.', 60, 0, 0, 1200, 'class', 12);
$functional = $serviceId($gym, 'Functional', 'Circuito a piccoli gruppi.', 45, 0, 0, 1000, 'class', 10);

$linkStaff = static function (int $service, array $people): void {
    foreach ($people as $person) {
        Db::run(App::$pdo, 'INSERT INTO service_staff (service_id, staff_id) VALUES (:service, :staff)', ['service' => $service, 'staff' => $person]);
    }
};
$linkResource = static function (int $service, array $resources): void {
    foreach ($resources as $resource) {
        Db::run(App::$pdo, 'INSERT INTO service_resources (service_id, resource_id) VALUES (:service, :resource)', ['service' => $service, 'resource' => $resource]);
    }
};
$linkStaff($taglioDonna, [$giuliaStaff, $davideStaff]);
$linkStaff($taglioUomo, [$giuliaStaff, $marcoStaff, $davideStaff]);
$linkStaff($colore, [$giuliaStaff, $davideStaff]);
$linkStaff($piega, [$giuliaStaff, $marcoStaff, $davideStaff]);
$linkStaff($lampo, [$giuliaStaff, $davideStaff]);
$linkStaff($pt, [$elenaStaff]);
$linkStaff($consulenza, [$elenaStaff]);
$linkStaff($yoga, [$elenaStaff]);
$linkStaff($functional, [$elenaStaff]);
$linkResource($taglioDonna, [$window, $mirror]);
$linkResource($taglioUomo, [$window, $mirror]);
$linkResource($colore, [$window, $mirror]);
$linkResource($lampo, [$window, $mirror]);
$linkResource($pt, [$room]);
$linkResource($consulenza, [$room]);
$linkResource($yoga, [$room]);
$linkResource($functional, [$room]);

Db::run(App::$pdo, 'INSERT INTO packages (business_id, service_id, name, credits, price_cents, valid_days, active) VALUES (:business, :service, :name, :credits, :price, :days, 1)', [
    'business' => $hair, 'service' => $piega, 'name' => 'Dieci pieghe', 'credits' => 8, 'price' => 12000, 'days' => 120,
]);
$piegheCard = Db::id(App::$pdo);
Db::run(App::$pdo, 'INSERT INTO packages (business_id, service_id, name, credits, price_cents, valid_days, active) VALUES (:business, :service, :name, :credits, :price, :days, 1)', [
    'business' => $gym, 'service' => $pt, 'name' => 'Dieci personal', 'credits' => 10, 'price' => 36000, 'days' => 90,
]);
$ptCard = Db::id(App::$pdo);
Db::run(App::$pdo, 'INSERT INTO pricing_rules (business_id, service_id, name, weekday, start_time, end_time, discount_percent, active) VALUES (:business, :service, :name, NULL, :start_time, :end_time, :discount, 1)', [
    'business' => $gym,
    'service' => $pt,
    'name' => 'Mattina leggera',
    'start_time' => '07:00:00',
    'end_time' => '11:00:00',
    'discount' => 20,
]);

$client = static function (int $businessId, string $name, ?string $email, string $phone, ?int $user, string $allergies = '', string $preferences = '') use ($bookings): int {
    $row = $bookings->ensureClient($businessId, $name, $email, $phone, $user);
    if ($allergies !== '' || $preferences !== '') {
        Db::run(App::$pdo, 'UPDATE clients SET allergies = :allergies, preferences = :preferences WHERE id = :id', [
            'allergies' => $allergies,
            'preferences' => $preferences,
            'id' => (int) $row['id'],
        ]);
    }

    return (int) $row['id'];
};

$nora = $client($hair, 'Nora Bianchi', 'nora@email.test', '+39 340 100 2200', null, 'Ammoniaca', 'Frangia morbida, niente volume in cima.');
$luca = $client($hair, 'Luca Ferretti', 'luca@email.test', '+39 349 555 0188', null);
$marta = $client($hair, 'Marta Greco', 'marta@email.test', '+39 331 222 9090', null);
$irene = $client($hair, 'Irene Sala', 'irene@email.test', '+39 320 777 4545', null);
$bianca = $client($hair, 'Bianca Leone', 'bianca@email.test', '+39 328 441 9033', $biancaUser, '', 'Piega liscia.');
$paolo = $client($hair, 'Paolo Neri', 'paolo@email.test', '+39 333 010 2871', null);
$sofia = $client($hair, 'Sofia Greco', 'sofia@email.test', '+39 347 616 1004', null);
$adele = $client($hair, 'Adele Conti', 'adele@email.test', '+39 338 404 2219', null);
$chiara = $client($hair, 'Chiara Rinaldi', 'chiara@email.test', '+39 342 818 6630', null);
$pietro = $client($gym, 'Pietro Sala', 'pietro@email.test', '+39 345 909 1144', null);
$sara = $client($gym, 'Sara Vitale', 'sara@email.test', '+39 329 303 7781', null);
$lezioni = [
    $client($gym, 'Giulia Conti', 'giuliaconti@email.test', '+39 340 222 1001', null),
    $client($gym, 'Marta Riva', 'martariva@email.test', '+39 340 222 1002', null),
    $client($gym, 'Elena Fois', 'elenaf@email.test', '+39 340 222 1003', null),
    $client($gym, 'Davide Serra', 'davideserra@email.test', '+39 340 222 1004', null),
];

$book = static function (int $business, int $service, int $staff, int $person, DateTimeImmutable $local, string $notes, bool $usePackage) use ($bookings, $instant, $clock): array {
    $start = $instant($local);

    return $bookings->bookAppointment(
        $business,
        $service,
        $staff,
        null,
        $person,
        $start,
        $notes,
        $usePackage,
        null,
        $start <= $clock->now(),
    );
};

try {
    foreach ([1, 2] as $nth) {
        $missed = $book($hair, $taglioUomo, $davideStaff, $luca, $past($nth, '10:00'), 'Non si è presentato.', false);
        $bookings->setStatus((int) $missed['id'], $hair, 'no_show');
    }

    $showcase = $coming([2, 3, 4, 5, 6], '16:00');
    $day = $showcase->format('Y-m-d');
    $book($hair, $taglioUomo, $marcoStaff, $sofia, $showcase->setTime(9, 0), '', false);
    $book($hair, $piega, $giuliaStaff, $adele, $showcase->setTime(11, 0), '', false);
    $book($hair, $taglioDonna, $giuliaStaff, $nora, $showcase->setTime(15, 0), 'Controllare la frangia.', false);
    $book($hair, $colore, $giuliaStaff, $chiara, $showcase->setTime(17, 0), 'Ritocco ricrescita.', false);
    $book($hair, $taglioUomo, $davideStaff, $luca, $showcase->setTime(15, 30), 'Chiedere la caparra in cassa.', false);

    $offerDay = $coming([2, 3, 4, 5, 6], '12:00', [$day]);
    $bookings->sellPackage($hair, $bianca, $piegheCard);
    $book($hair, $piega, $marcoStaff, $bianca, $offerDay->setTime(10, 0), 'Scala una piega dalla tessera.', true);
    $bookings->joinWaitlist($hair, $piega, $marcoStaff, 'Marta Greco', 'marta@email.test', '+39 331 222 9090', $offerDay->format('Y-m-d'), '11:00', '13:00', null);
    $bookings->joinWaitlist($hair, $colore, null, 'Irene Sala', 'irene@email.test', '+39 320 777 4545', $day, '15:00', '19:00', null);
    $held = $book($hair, $piega, $marcoStaff, $paolo, $offerDay->setTime(12, 0), 'Poi disdice.', false);
    $offer = $bookings->cancelAppointment((int) $held['id'], $hair, true, 'Impegno improvviso.');
    if ($offer === null || (string) ($offer['status'] ?? '') !== 'offered') {
        throw new RuntimeException('La disdetta non ha proposto il posto a Marta.');
    }

    $closure = $offerDay->modify('+7 days')->setTime(9, 0);
    while ((int) $closure->format('N') !== 2 || $closure->format('Y-m-d') === $day) {
        $closure = $closure->modify('+1 day')->setTime(9, 0);
    }
    $closureEnd = $instant($closure->setTime(13, 0));
    Db::run(App::$pdo, 'INSERT INTO closures (business_id, staff_id, starts_at, ends_at, reason) VALUES (:business, NULL, :starts, :ends, :reason)', [
        'business' => $hair,
        'starts' => $clock->sql($instant($closure)),
        'ends' => $clock->sql($closureEnd),
        'reason' => 'Ferie di mezza giornata',
    ]);

    $gymMorning = $coming([1, 2, 3, 4, 5], '08:00');
    $book($gym, $pt, $elenaStaff, $pietro, $gymMorning, 'Scheda spinta.', false);
    $bookings->scheduleSession($gym, $functional, $elenaStaff, $room, $instant($gymMorning->setTime(9, 30)), 10, 'Porta l\'asciugamano.');
    $bookings->bookClass((int) Db::value(App::$pdo, 'SELECT id FROM class_sessions WHERE business_id = :id ORDER BY id DESC LIMIT 1', ['id' => $gym]), $lezioni[0], false);
    $bookings->bookClass((int) Db::value(App::$pdo, 'SELECT id FROM class_sessions WHERE business_id = :id ORDER BY id DESC LIMIT 1', ['id' => $gym]), $lezioni[1], false);

    $yogaAt = $coming([1, 2, 3, 4, 5], '18:15');
    $session = $bookings->scheduleSession($gym, $yoga, $elenaStaff, $room, $instant($yogaAt), 12, 'Livello aperto.');
    foreach ($lezioni as $person) {
        $bookings->bookClass((int) $session['id'], $person, false);
    }

    $saraDay = $coming([1, 2, 3, 4, 5], '08:30', [$gymMorning->format('Y-m-d')]);
    $bookings->sellPackage($gym, $sara, $ptCard);
    $book($gym, $pt, $elenaStaff, $sara, $saraDay, 'Primo credito della tessera.', true);
} catch (DomainException $exception) {
    if (PHP_SAPI !== 'cli') {
        throw $exception;
    }
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

if (PHP_SAPI === 'cli') {
    echo "Demo pronta. Password: Lumina-demo-2026\n";
    echo "Atelier Luce /b/atelier-luce — giulia@atelierluce.test, marco@atelierluce.test\n";
    echo "Palestra Borgo /b/palestra-borgo — elena@palestraborgo.test\n";
    echo "Portale cliente — bianca@email.test\n";
}
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    App::boot();
    lumina_demo(in_array('--fresh', $argv ?? [], true));
}
