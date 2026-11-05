<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib/LabException.php';
require dirname(__DIR__) . '/lib/Lab.php';

$failed = 0;

function check(bool $ok, string $message): void
{
    global $failed;
    if ($ok) {
        echo "ok  $message\n";
        return;
    }
    $failed++;
    echo "FAIL $message\n";
}

function expectMessage(Lab $lab, string $service, array $body, string $needle, bool $missing = false): void
{
    try {
        $lab->call($service, $body);
        check(false, "$service doveva fallire");
    } catch (LabException $e) {
        $statusOk = $missing ? $e->status === 404 : $e->status !== 404;
        check($statusOk && str_contains($e->getMessage(), $needle), "$service · $needle");
    }
}

$path = sys_get_temp_dir() . '/banco-x-' . getmypid() . '.sqlite';
@unlink($path);
$lab = Lab::open(['driver' => 'sqlite', 'sqlite_path' => $path]);
$status = $lab->status();
check($status['driver'] === 'sqlite', 'driver sqlite');
check($status['counts']['workplaces'] === 3, 'tre postazioni');
check($status['counts']['operations'] === 4, 'quattro fasi');
check($status['counts']['bookings'] === 0, 'nessun versamento iniziale');

$started = $lab->call('operation.start', ['operationCode' => 'OF-24081-10', 'operatorCode' => 'op.01']);
check(in_array('Postazione TORN-01 passa a running.', $started['effects'], true), 'avvio: tornio in lavoro');
expectMessage($lab, 'operation.start', ['operationCode' => 'OF-24082-10', 'operatorCode' => 'op.02'], 'non è libera');
expectMessage($lab, 'operation.book', [
    'operationCode' => 'OF-24081-10',
    'operatorCode' => 'op.01',
    'kind' => 'scrap',
    'qty' => 1,
], 'causale');

$lab->call('operation.book', [
    'operationCode' => 'OF-24081-10',
    'operatorCode' => 'op.01',
    'kind' => 'good',
    'qty' => 4,
]);
$lab->call('downtime.start', ['workplaceCode' => 'TORN-01', 'reasonCode' => 'guasto']);
$held = null;
foreach ($lab->operations('TORN-01') as $row) {
    if ($row['code'] === 'OF-24081-10') {
        $held = $row;
    }
}
check($held !== null && $held['status'] === 'held', 'fermo: fase sospesa');
expectMessage($lab, 'operation.book', [
    'operationCode' => 'OF-24081-10',
    'operatorCode' => 'op.01',
    'kind' => 'good',
    'qty' => 1,
], 'running');
expectMessage($lab, 'operation.finish', ['operationCode' => 'OF-24081-10'], 'fermo');

$lab->call('downtime.end', ['workplaceCode' => 'TORN-01']);
$signal = $lab->call('edge.signal', [
    'workplaceCode' => 'TORN-01',
    'signal' => 'piece_good',
    'value' => '2',
]);
check(str_contains((string) $signal['applied'], 'booking good 2'), 'segnale piece_good versa');
$lab->call('edge.signal', ['workplaceCode' => 'TORN-01', 'signal' => 'fault', 'value' => 'attrezzaggio']);
$lab->call('edge.signal', ['workplaceCode' => 'TORN-01', 'signal' => 'clear', 'value' => '1']);
$closed = $lab->call('operation.finish', ['operationCode' => 'OF-24081-10']);
check($closed['order']['status'] === 'in_progress', 'ordine ancora aperto dopo la tornitura');
check($closed['workplace']['state'] === 'idle', 'tornio libero');

$lab->call('operation.start', ['operationCode' => 'OF-24081-20', 'operatorCode' => 'op.02']);
$done = $lab->call('operation.finish', ['operationCode' => 'OF-24081-20']);
check($done['order']['status'] === 'done', 'ordine chiuso quando le fasi sono chiuse');
check($done['order']['qtyGood'] === 6, 'buoni 4 più 2 dal segnale');

expectMessage($lab, 'nope.missing', [], 'sconosciuto', true);
foreach (Lab::catalog() as $service) {
    try {
        $lab->call($service['name'], []);
        check(false, $service['name'] . ' senza corpo doveva fallire');
    } catch (LabException $e) {
        check($e->status !== 404, $service['name'] . ' è nel catalogo');
    }
}

$monitor = $lab->monitor();
check($monitor['totals']['goodToday'] === 6, 'monitor conta i buoni di oggi');
check($monitor['day'] === (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d'), 'giorno in Europe/Rome');

$lab->call('lab.reset', ['confirm' => 'RESET']);
check($lab->status()['counts']['bookings'] === 0, 'reset azzera i versamenti');
check($lab->status()['counts']['operations'] === 4, 'reset rimette le fasi');
$lab->call('lab.demo', ['confirm' => 'DEMO']);
check($lab->status()['counts']['bookings'] === 2, 'demo lascia due versamenti');
$fres = null;
foreach ($lab->workplaces() as $workplace) {
    if ($workplace['code'] === 'FRES-02') {
        $fres = $workplace;
    }
}
check($fres !== null && $fres['state'] === 'idle', 'demo chiude il fermo della fresatrice');

$fallbackPath = sys_get_temp_dir() . '/banco-x-fallback-' . getmypid() . '.sqlite';
@unlink($fallbackPath);
if (!extension_loaded('pdo_oci')) {
    $fallback = Lab::open([
        'driver' => 'oci',
        'sqlite_path' => $fallbackPath,
        'oracle_dsn' => 'oci:dbname=//127.0.0.1:1521/X',
        'oracle_user' => 'HXSIM',
        'oracle_password' => 'x',
    ]);
    check($fallback->status()['driver'] === 'sqlite', 'senza pdo_oci il banco resta su sqlite');
    check(is_file($fallbackPath), 'senza pdo_oci il file sqlite viene creato');
    check($fallback->status()['warning'] !== '', 'l\'assenza di pdo_oci è visibile');
}

@unlink($path);
@unlink($fallbackPath);
if ($failed > 0) {
    echo "$failed controlli falliti\n";
    exit(1);
}
echo "tutti i controlli ok\n";
