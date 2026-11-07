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

$kpi = $lab->kpi();
$byCode = [];
foreach ($kpi['workplaces'] as $workplace) {
    $byCode[$workplace['code']] = $workplace;
}
check($byCode['TORN-01']['oee'] === 60.0, 'OEE tornio 60');
check($byCode['TORN-01']['availability'] === 100.0, 'tornio senza fermo');
check($byCode['TORN-01']['performance'] === 70.0, 'performance 7 pezzi in 10 minuti');
check($byCode['TORN-01']['quality'] === 85.7, 'qualità 6 su 7');
check($byCode['FRES-02']['oee'] === 0.0, 'fresatrice ferma: OEE 0');
check($kpi['plant']['oee'] === 24.0, 'OEE di reparto 24');
check($kpi['scrap']['qty'] === 1 && $kpi['scrap']['rate'] === 14.3, 'tasso scarto 1 su 7');
check($kpi['scrap']['reasons'][0]['reason'] === 'Bava', 'pareto causale bava');

$scrapList = $lab->runList('11');
check($scrapList['code'] === 'LST-11', '11 diventa LST-11');
check(count($scrapList['rows']) === 1 && $scrapList['rows'][0]['reason'] === 'Bava', 'LST-11 mostra lo scarto');
$orders = $lab->runList('LST-16');
check(count($orders['rows']) === 2, 'LST-16 ha i due ordini');
$saved = $lab->call('list.save', [
    'code' => '21',
    'title' => 'Fasi del tornio',
    'base' => 'operation',
    'columns' => ['code', 'status'],
    'filter' => ['workplaceCode' => 'TORN-01'],
]);
check(count($saved['rows']) === 2, 'LST-21 filtra le fasi del tornio');
expectMessage($lab, 'list.delete', ['code' => '11'], 'restano');
expectMessage($lab, 'list.save', [
    'code' => '22',
    'title' => 'x',
    'base' => 'hxs_booking; drop',
    'columns' => ['qty'],
    'filter' => [],
], 'Base lista');
$lab->call('list.delete', ['code' => '21']);
check($lab->status()['counts']['lists'] === 2, 'resta il paio di liste di esempio');

$mysql = file_get_contents(dirname(__DIR__) . '/sql/mysql.sql');
$oracle = file_get_contents(dirname(__DIR__) . '/sql/oracle.sql');
foreach (['hxs_workplace', 'hxs_person', 'hxs_order', 'hxs_operation', 'hxs_booking', 'hxs_downtime', 'hxs_signal', 'hxs_service_log', 'hxs_list'] as $table) {
    check(str_contains($mysql, $table) && str_contains($oracle, $table), "tabella $table su mysql e oracle");
}
foreach (['ideal_sec', 'base_object', 'columns_json', 'filter_json'] as $column) {
    check(str_contains($mysql, $column) && str_contains($oracle, $column), "colonna $column su mysql e oracle");
}

$old = sys_get_temp_dir() . '/banco-x-old-' . getmypid() . '.sqlite';
@unlink($old);
$legacy = new PDO('sqlite:' . $old);
$legacy->exec('CREATE TABLE hxs_workplace (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, state TEXT NOT NULL)');
$legacy->exec("INSERT INTO hxs_workplace (code, name, state) VALUES ('OLD-1', 'Vecchia', 'idle')");
$legacy = null;
$migrated = Lab::open(['driver' => 'sqlite', 'sqlite_path' => $old]);
check($migrated->status()['counts']['workplaces'] === 1, 'un file vecchio non viene riseminato');
check($migrated->status()['counts']['lists'] === 2, 'sul file vecchio arrivano LST-11 e LST-16');
check($migrated->kpi()['plant']['oee'] === null, 'senza movimenti l\'OEE è vuoto');
@unlink($old);

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
