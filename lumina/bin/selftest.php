<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/migrate.php';

use Lumina\Clock;
use Lumina\Db;
use Lumina\DomainException;
use Lumina\Passcode;
use Lumina\Plan;
use Lumina\Schedule\Bookings;
use Lumina\Schedule\Engine;

$pass = 0;
$fail = 0;

function check(bool $ok, string $message): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ok  {$message}\n";
        return;
    }
    $fail++;
    echo " FAIL {$message}\n";
}

function expect(string $reason, callable $fn): void
{
    try {
        $fn();
        check(false, "attesa eccezione {$reason}");
    } catch (DomainException $exception) {
        check($exception->reason === $reason, "motivo {$reason}, ottenuto {$exception->reason}: {$exception->getMessage()}");
    }
}

$rome = new DateTimeZone('Europe/Rome');
$clock = new Clock($rome, new DateTimeImmutable('2026-06-02 08:00:00', $rome));
$pdo = Db::connect('lumina_test');
lumina_reset($pdo);
lumina_migrate($pdo);
$engine = new Engine($pdo, $clock);
$bookings = new Bookings($pdo, $clock, $engine);

function morning(): void
{
    global $clock, $rome;
    $clock->freeze(new DateTimeImmutable('2026-06-02 08:00:00', $rome));
}

function at(string $local): DateTimeImmutable
{
    global $clock, $rome;

    return $clock->at($local, $rome);
}

function findSlot(array $slots, string $local): ?array
{
    $stamp = at($local)->getTimestamp();
    foreach ($slots as $slot) {
        if ($slot['starts_at']->getTimestamp() === $stamp) {
            return $slot;
        }
    }

    return null;
}

function clearBookings(): void
{
    global $pdo;
    foreach (['waitlist', 'class_bookings', 'appointments', 'class_sessions', 'series', 'notifications', 'client_packages'] as $table) {
        $pdo->exec('DELETE FROM `' . $table . '`');
    }
}

check(PHP_VERSION_ID >= 80512, 'PHP è almeno 8.5.12');
check(parse_money('35') === 3500, 'parse 35 euro');
check(parse_money('35,50') === 3550, 'parse 35,50');
check(parse_money('19.99') === 1999, 'parse 19.99');
check(slugify('Città Luce — Yoga') === 'citta-luce-yoga', 'slug');

$dst = $clock->at('2026-10-26 18:15:00', $rome);
check($clock->sql($dst) === '2026-10-26 17:15:00', 'ora legale: 18:15 a Roma è 17:15 UTC');
$round = Db::one($pdo, 'SELECT CAST(:ts AS DATETIME) AS ts', ['ts' => $clock->sql($dst)]);
$back = $clock->local($clock->parseUtc((string) $round['ts']), $rome);
check($back->format('Y-m-d H:i') === '2026-10-26 18:15', 'il datetime UTC riletto resta 18:15 a Roma');

$owner = Db::run($pdo, 'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :hash, :role)', [
    'name' => 'Giulia Test',
    'email' => 'giulia@test.local',
    'hash' => password_hash('Lumina-demo-2026', PASSWORD_DEFAULT),
    'role' => 'owner',
]);
$ownerId = Db::id($pdo);
unset($owner);
Db::run($pdo, 'INSERT INTO businesses (
    owner_user_id, name, slug, category, tagline, description, city, timezone, cancel_hours, slot_minutes, reliability_threshold
) VALUES (
    :owner, :name, :slug, :category, :tagline, :description, :city, :timezone, 12, 15, 70
)', [
    'owner' => $ownerId,
    'name' => 'Test',
    'slug' => 'test',
    'category' => 'hair',
    'tagline' => 'Test',
    'description' => 'Test',
    'city' => 'Carpineto Romano',
    'timezone' => 'Europe/Rome',
]);
$businessId = Db::id($pdo);
$weekday = (int) (new DateTimeImmutable('2026-06-02'))->format('N');
for ($day = 1; $day <= 7; $day++) {
    Db::run($pdo, 'INSERT INTO weekly_hours (business_id, staff_id, weekday, start_time, end_time) VALUES (:business, NULL, :weekday, :start_time, :end_time)', [
        'business' => $businessId,
        'weekday' => $day,
        'start_time' => '09:00:00',
        'end_time' => '13:00:00',
    ]);
}

$staffIds = [];
foreach (['A' => 'Giulia', 'B' => 'Marco', 'C' => 'Lea'] as $key => $name) {
    Db::run($pdo, 'INSERT INTO staff (business_id, name, title, color, active) VALUES (:business, :name, :title, :color, 1)', [
        'business' => $businessId,
        'name' => $name,
        'title' => 'Test',
        'color' => '#1f6b45',
    ]);
    $staffIds[$key] = Db::id($pdo);
}
$chairs = [];
foreach (['Poltrona 1', 'Poltrona 2'] as $name) {
    Db::run($pdo, 'INSERT INTO resources (business_id, name, kind, capacity, active) VALUES (:business, :name, :kind, 1, 1)', [
        'business' => $businessId,
        'name' => $name,
        'kind' => 'chair',
    ]);
    $chairs[] = Db::id($pdo);
}
Db::run($pdo, 'INSERT INTO resources (business_id, name, kind, capacity, active) VALUES (:business, :name, :kind, 1, 1)', [
    'business' => $businessId,
    'name' => 'Sala',
    'kind' => 'room',
]);
$roomId = Db::id($pdo);

$services = [];
foreach ([
    'taglio' => [60, 0, 0, 3000, 'appointment', 1],
    'lungo' => [120, 0, 0, 6000, 'appointment', 1],
    'buffer' => [60, 15, 15, 4000, 'appointment', 1],
    'corto' => [20, 0, 0, 1500, 'appointment', 1],
    'corso' => [60, 0, 0, 1000, 'class', 10],
] as $key => [$duration, $before, $after, $price, $kind, $capacity]) {
    Db::run($pdo, 'INSERT INTO services (
        business_id, name, duration_min, buffer_before, buffer_after, price_cents, kind, capacity, active
    ) VALUES (
        :business, :name, :duration, :before, :after, :price, :kind, :capacity, 1
    )', [
        'business' => $businessId,
        'name' => $key,
        'duration' => $duration,
        'before' => $before,
        'after' => $after,
        'price' => $price,
        'kind' => $kind,
        'capacity' => $capacity,
    ]);
    $services[$key] = Db::id($pdo);
}

$linkStaff = static function (int $serviceId, array $ids) use ($pdo): void {
    foreach ($ids as $id) {
        Db::run($pdo, 'INSERT INTO service_staff (service_id, staff_id) VALUES (:service, :staff)', ['service' => $serviceId, 'staff' => $id]);
    }
};
$linkResources = static function (int $serviceId, array $ids) use ($pdo): void {
    foreach ($ids as $id) {
        Db::run($pdo, 'INSERT INTO service_resources (service_id, resource_id) VALUES (:service, :resource)', ['service' => $serviceId, 'resource' => $id]);
    }
};
$linkStaff($services['taglio'], array_values($staffIds));
$linkStaff($services['lungo'], [$staffIds['A']]);
$linkStaff($services['buffer'], [$staffIds['A']]);
$linkStaff($services['corto'], [$staffIds['A']]);
$linkStaff($services['corso'], [$staffIds['A']]);
foreach (['taglio', 'lungo', 'buffer', 'corto'] as $key) {
    $linkResources($services[$key], $chairs);
}
$linkResources($services['corso'], [$roomId]);

Db::run($pdo, 'INSERT INTO pricing_rules (
    business_id, service_id, name, weekday, start_time, end_time, discount_percent, active
) VALUES (
    :business, :service, :name, :weekday, :start_time, :end_time, 20, 1
)', [
    'business' => $businessId,
    'service' => $services['taglio'],
    'name' => 'Mattina',
    'weekday' => $weekday,
    'start_time' => '09:00:00',
    'end_time' => '11:00:00',
]);

$clients = [];
foreach (['Nora', 'Luca', 'Bianca', 'Marta', 'Irene', 'Sara'] as $name) {
    Db::run($pdo, 'INSERT INTO clients (business_id, name, email, phone, notes) VALUES (:business, :name, :email, :phone, :notes)', [
        'business' => $businessId,
        'name' => $name,
        'email' => strtolower($name) . '@test.local',
        'phone' => '3330001122',
        'notes' => '',
    ]);
    $clients[$name] = Db::id($pdo);
}

echo "\nDisponibilità\n";
morning();
$slots = $engine->slots($businessId, $services['taglio'], '2026-06-02');
check(findSlot($slots, '2026-06-02 09:00:00') !== null, '09:00 è prenotabile');
check(findSlot($slots, '2026-06-02 08:00:00') === null, '08:00 è fuori orario');
check(findSlot($slots, '2026-06-03 09:00:00') === null, 'gli slot sono solo del giorno chiesto');
$early = findSlot($slots, '2026-06-02 09:00:00');
$later = findSlot($slots, '2026-06-02 11:00:00');
check($early !== null && $early['price_cents'] === 2400 && $early['price_label'] === 'Mattina', 'sconto vivo 20% alle 09:00');
check($later !== null && $later['price_cents'] === 3000 && $later['price_label'] === '', 'prezzo pieno alle 11:00');
$buffered = $engine->slots($businessId, $services['buffer'], '2026-06-02', $staffIds['A']);
check(findSlot($buffered, '2026-06-02 09:00:00') === null, 'il buffer impedisce le 09:00');
check(findSlot($buffered, '2026-06-02 09:15:00') !== null, 'primo slot con buffer alle 09:15');
check($engine->slots($businessId, $services['corso'], '2026-06-02') === [], 'un corso non genera slot singoli');

echo "\nConflitti e postazioni\n";
$first = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Nora'], at('2026-06-02 09:00:00'), '', false);
check((int) $first['price_cents'] === 2400, 'prezzo scontato salvato');
check((string) $first['resource_id'] !== '', 'assegna una poltrona');
expect('conflict', fn () => $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Luca'], at('2026-06-02 09:00:00'), '', false));
expect('policy', fn () => $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['B'], null, $clients['Nora'], at('2026-06-02 09:00:00'), '', false));
$second = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['B'], null, $clients['Luca'], at('2026-06-02 09:00:00'), '', false);
check((int) $second['resource_id'] !== (int) $first['resource_id'], 'due operatori usano due poltrone diverse');
expect('conflict', fn () => $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['C'], null, $clients['Bianca'], at('2026-06-02 09:00:00'), '', false));

echo "\nQualità del buco\n";
clearBookings();
morning();
$bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Nora'], at('2026-06-02 09:00:00'), '', false);
$bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Luca'], at('2026-06-02 12:00:00'), '', false);
$long = findSlot($engine->slots($businessId, $services['lungo'], '2026-06-02', $staffIds['A']), '2026-06-02 10:00:00');
$adjacent = findSlot($engine->slots($businessId, $services['taglio'], '2026-06-02', $staffIds['A']), '2026-06-02 10:00:00');
check($long !== null && $long['quality'] === 'ideal', '120 minuti chiudono il buco: ideale');
check($adjacent !== null && $adjacent['quality'] === 'adjacent', '60 minuti nel buco da 120: aderente');
check($engine->idealCount($businessId, '2026-06-02') >= 1, 'il conteggio dei buchi ideali è almeno 1');
clearBookings();
$bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Nora'], at('2026-06-02 09:00:00'), '', false);
$bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Luca'], at('2026-06-02 10:30:00'), '', false);
$fragment = findSlot($engine->slots($businessId, $services['corto'], '2026-06-02', $staffIds['A']), '2026-06-02 10:00:00');
check($fragment !== null && $fragment['quality'] === 'fragment', 'uno scarto sotto i 30 minuti è spezzato');
$savedFragment = $bookings->bookAppointment($businessId, $services['corto'], $staffIds['A'], null, $clients['Bianca'], at('2026-06-02 10:00:00'), '', false);
check((string) $savedFragment['slot_quality'] === 'fragment', 'lo slot spezzato resta prenotabile');

echo "\nChiusure e turni\n";
clearBookings();
morning();
Db::run($pdo, 'INSERT INTO closures (business_id, staff_id, starts_at, ends_at, reason) VALUES (:business, NULL, :starts, :ends, :reason)', [
    'business' => $businessId,
    'starts' => $clock->sql(at('2026-06-02 10:00:00')),
    'ends' => $clock->sql(at('2026-06-02 11:00:00')),
    'reason' => 'Pausa',
]);
$closed = $engine->slots($businessId, $services['taglio'], '2026-06-02', $staffIds['A']);
check(findSlot($closed, '2026-06-02 09:00:00') !== null, 'la chiusura delle 10 non toglie le 09:00');
check(findSlot($closed, '2026-06-02 10:00:00') === null, 'la chiusura toglie le 10:00');
$pdo->exec('DELETE FROM closures');
Db::run($pdo, 'INSERT INTO weekly_hours (business_id, staff_id, weekday, start_time, end_time) VALUES (:business, :staff, :weekday, :start_time, :end_time)', [
    'business' => $businessId,
    'staff' => $staffIds['A'],
    'weekday' => $weekday,
    'start_time' => '10:00:00',
    'end_time' => '12:00:00',
]);
$custom = $engine->slots($businessId, $services['taglio'], '2026-06-02', $staffIds['A']);
check(findSlot($custom, '2026-06-02 09:00:00') === null, 'il turno personale sostituisce l\'orario dell\'attività');
check(findSlot($custom, '2026-06-02 10:00:00') !== null, 'il turno personale apre alle 10:00');
$pdo->exec('DELETE FROM weekly_hours WHERE staff_id IS NOT NULL');
$clock->freeze(new DateTimeImmutable('2026-06-02 09:30:00', $rome));
$future = $engine->slots($businessId, $services['taglio'], '2026-06-02', $staffIds['A']);
check(findSlot($future, '2026-06-02 09:00:00') === null, 'non propone un orario già passato');
check(findSlot($future, '2026-06-02 10:00:00') !== null, 'propone ancora le 10:00');
morning();

echo "\nTessere\n";
clearBookings();
Db::run($pdo, 'INSERT INTO packages (business_id, service_id, name, credits, price_cents, valid_days, active) VALUES (:business, :service, :name, 3, 9000, 90, 1)', [
    'business' => $businessId,
    'service' => $services['taglio'],
    'name' => '3 tagli',
]);
$packageId = Db::id($pdo);
Db::run($pdo, 'INSERT INTO packages (business_id, service_id, name, credits, price_cents, valid_days, active) VALUES (:business, :service, :name, 2, 3000, 30, 1)', [
    'business' => $businessId,
    'service' => $services['corto'],
    'name' => '2 corti',
]);
$otherPackage = Db::id($pdo);
$bookings->sellPackage($businessId, $clients['Bianca'], $packageId);
$withCard = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Bianca'], at('2026-06-02 09:00:00'), '', true);
check((int) $withCard['price_cents'] === 0 && (string) $withCard['price_label'] === 'Tessera', 'la tessera azzera il prezzo');
$left = (int) Db::value($pdo, 'SELECT credits_left FROM client_packages WHERE client_id = :client AND package_id = :package', [
    'client' => $clients['Bianca'],
    'package' => $packageId,
]);
check($left === 2, 'scala un credito');
$bookings->cancelAppointment((int) $withCard['id'], $businessId, true, 'prova');
$left = (int) Db::value($pdo, 'SELECT credits_left FROM client_packages WHERE client_id = :client AND package_id = :package', [
    'client' => $clients['Bianca'],
    'package' => $packageId,
]);
check($left === 3, 'l\'annullo restituisce il credito');
$bookings->sellPackage($businessId, $clients['Bianca'], $otherPackage);
expect('validation', fn () => $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Sara'], at('2026-06-02 09:00:00'), '', true));
$pdo->exec('UPDATE client_packages SET expires_at = \'2026-05-01 00:00:00\'');
expect('validation', fn () => $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Bianca'], at('2026-06-02 09:00:00'), '', true));

echo "\nPolicy di annullo e lista d\'attesa\n";
clearBookings();
morning();
$soon = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Nora'], at('2026-06-02 11:00:00'), '', false);
$clock->freeze(new DateTimeImmutable('2026-06-02 10:00:00', $rome));
expect('policy', fn () => $bookings->cancelAppointment((int) $soon['id'], $businessId, false, 'tardi'));
morning();
$bookings->cancelAppointment((int) $soon['id'], $businessId, true, 'banco');
$again = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Nora'], at('2026-06-02 11:00:00'), '', false);
$bookings->joinWaitlist($businessId, $services['taglio'], null, 'Marta', 'marta@test.local', '3331112233', '2026-06-02', '09:00', '13:00', null);
$irene = $bookings->joinWaitlist($businessId, $services['taglio'], null, 'Irene', 'irene@test.local', '3331112244', '2026-06-02', '09:00', '13:00', null);
$offer = $bookings->cancelAppointment((int) $again['id'], $businessId, true, 'si libera');
check($offer !== null && (string) $offer['status'] === 'offered', 'la disdetta offre il posto al primo in lista');
check((int) $offer['client_id'] === $clients['Marta'], 'l\'offerta segue l\'ordine di arrivo');
$accepted = $bookings->acceptOffer((int) $offer['id'], false);
check((string) $accepted['status'] === 'confirmed', 'accettare l\'offerta crea la prenotazione');
expect('policy', fn () => $bookings->acceptOffer((int) $offer['id'], false));
$pdo->exec(
    "UPDATE waitlist
     SET status = 'offered', staff_id = " . $staffIds['A'] . ",
         offer_starts_at = '2026-06-02 09:00:00', offer_expires_at = '2026-06-02 05:00:00'
     WHERE id = " . (int) $irene['id'],
);
expect('policy', fn () => $bookings->acceptOffer((int) $irene['id'], false));

clearBookings();
morning();
$held = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Nora'], at('2026-06-02 11:00:00'), '', false);
$firstWait = $bookings->joinWaitlist($businessId, $services['taglio'], null, 'Marta', 'marta@test.local', '333', '2026-06-02', '09:00', '13:00', null);
$secondWait = $bookings->joinWaitlist($businessId, $services['taglio'], null, 'Irene', 'irene@test.local', '334', '2026-06-02', '09:00', '13:00', null);
$bookings->cancelAppointment((int) $held['id'], $businessId, true, 'libera');
$pdo->exec('UPDATE waitlist SET offer_expires_at = \'2026-06-02 05:00:00\' WHERE id = ' . (int) $firstWait['id']);
$bookings->sweep($businessId);
$after = Db::all($pdo, 'SELECT id, status FROM waitlist ORDER BY id');
$states = [];
foreach ($after as $row) {
    $states[(int) $row['id']] = (string) $row['status'];
}
check(($states[(int) $firstWait['id']] ?? '') === 'expired', 'l\'offerta scaduta viene chiusa');
check(($states[(int) $secondWait['id']] ?? '') === 'offered', 'il posto passa al successivo');

echo "\nCorsi, serie, affidabilità\n";
clearBookings();
morning();
$session = $bookings->scheduleSession($businessId, $services['corso'], $staffIds['A'], $roomId, at('2026-06-02 09:00:00'), 1, '');
$bookings->bookClass((int) $session['id'], $clients['Sara'], false);
expect('conflict', fn () => $bookings->bookClass((int) $session['id'], $clients['Luca'], false));
$bookingId = (int) Db::value($pdo, 'SELECT id FROM class_bookings WHERE session_id = :session', ['session' => (int) $session['id']]);
$bookings->setClassBookingStatus($bookingId, $businessId, 'cancelled');
$rebooked = $bookings->bookClass((int) $session['id'], $clients['Luca'], false);
check((string) $rebooked['status'] === 'booked', 'un posto annullato si può riassegnare');
$blocked = $engine->slots($businessId, $services['taglio'], '2026-06-02', $staffIds['A']);
check(findSlot($blocked, '2026-06-02 09:00:00') === null, 'il corso occupa l\'operatore');
check(findSlot($engine->slots($businessId, $services['taglio'], '2026-06-02', $staffIds['B']), '2026-06-02 09:00:00') !== null, 'gli altri operatori restano liberi');

clearBookings();
$bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Nora'], at('2026-06-09 09:00:00'), '', false);
$series = $bookings->bookSeries($businessId, $services['taglio'], $staffIds['A'], null, $clients['Luca'], at('2026-06-02 09:00:00'), 3, '', false);
check(count($series['booked']) === 2 && count($series['skipped']) === 1, 'la serie salta solo la settimana già occupata');

clearBookings();
$pastOne = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Luca'], at('2026-05-19 09:00:00'), '', false, null, true);
$pastTwo = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Luca'], at('2026-05-26 09:00:00'), '', false, null, true);
$bookings->setStatus((int) $pastOne['id'], $businessId, 'no_show');
$bookings->setStatus((int) $pastTwo['id'], $businessId, 'no_show');
$risky = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['A'], null, $clients['Luca'], at('2026-06-02 09:00:00'), '', false);
$fresh = $bookings->bookAppointment($businessId, $services['taglio'], $staffIds['B'], null, $clients['Sara'], at('2026-06-02 09:00:00'), '', false);
check((int) $risky['deposit_advised'] === 1, 'due assenze consigliano la caparra');
check((int) $fresh['deposit_advised'] === 0, 'un cliente nuovo non è segnalato');
$score = $bookings->reliability($clients['Luca'], 70);
check($score['percent'] === 0 && $score['advise_deposit'] === true, 'indice di affidabilità a zero');

Db::run($pdo, 'UPDATE services SET active = 0 WHERE id = :id', ['id' => $services['corto']]);
expect('not_found', fn () => $bookings->bookAppointment($businessId, $services['corto'], $staffIds['A'], null, $clients['Sara'], at('2026-06-02 11:00:00'), '', false));

echo "\nAccesso e piani\n";
$code = Passcode::issue($pdo, $clock, 'nuovo@test.local', '127.0.0.1');
$stored = Db::one($pdo, 'SELECT code_hash FROM login_codes WHERE email = :email ORDER BY id DESC LIMIT 1', ['email' => 'nuovo@test.local']);
check(is_array($stored) && $stored['code_hash'] !== $code && strlen((string) $stored['code_hash']) === 64, 'il codice in archivio è un hash');
check(Passcode::check($pdo, $clock, 'nuovo@test.local', $code), 'il codice giusto entra');
check(!Passcode::check($pdo, $clock, 'nuovo@test.local', $code), 'il codice usato non rientra');
$burn = Passcode::issue($pdo, $clock, 'altro@test.local', '127.0.0.1');
for ($try = 0; $try < 5; $try++) {
    Passcode::check($pdo, $clock, 'altro@test.local', '000000');
}
check(!Passcode::check($pdo, $clock, 'altro@test.local', $burn), 'dopo cinque errori il codice muore');
check(!Plan::allows(['plan' => 'bottega'], 'waitlist') && Plan::allows(['plan' => 'salone'], 'waitlist'), 'la lista d\'attesa parte dal Salone');
Db::run($pdo, "UPDATE businesses SET plan = 'bottega' WHERE id = :id", ['id' => $businessId]);
expect('plan', fn () => $bookings->joinWaitlist($businessId, $services['taglio'], null, 'Marta', 'marta@test.local', '333', '2026-06-02', '09:00', '13:00', null));

echo "\n{$pass} ok, {$fail} ko\n";
exit($fail > 0 ? 1 : 0);
