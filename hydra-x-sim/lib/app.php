<?php
declare(strict_types=1);

require __DIR__ . '/LabException.php';
require __DIR__ . '/Lab.php';

/** @return array<string, mixed> */
function load_config(): array
{
    $config = [
        'driver' => 'sqlite',
        'sqlite_path' => __DIR__ . '/../data/sim.sqlite',
        'mysql_host' => '127.0.0.1',
        'mysql_port' => 3306,
        'mysql_database' => 'hxs',
        'mysql_user' => 'root',
        'mysql_password' => '',
        'oracle_dsn' => 'oci:dbname=//127.0.0.1:1521/FREEPDB1;charset=AL32UTF8',
        'oracle_user' => 'HXSIM',
        'oracle_password' => '',
    ];
    $local = __DIR__ . '/../config.local.php';
    if (is_file($local)) {
        $over = require $local;
        if (is_array($over)) {
            $config = array_merge($config, $over);
        }
    }
    return $config;
}

/** @param array<string, mixed> $data */
function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function fail_page(string $message): void
{
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><title>Banco X</title>';
    echo '<body style="font-family:Segoe UI,sans-serif;max-width:680px;margin:40px auto;line-height:1.45">';
    echo '<h1>Il database non è stato creato</h1>';
    echo '<p>' . $safe . '</p>';
    echo '<p>Dettaglio tecnico: <a href="controllo.php">controllo.php</a></p>';
    echo '</body></html>';
}

$route = isset($_GET['r']) ? (string) $_GET['r'] : (string) ($_SERVER['PATH_INFO'] ?? '/');
$route = '/' . ltrim($route, '/');
if ($route !== '/') {
    $route = rtrim($route, '/') ?: '/';
}

if (str_starts_with($route, '/api/')) {
    try {
        $lab = Lab::open(load_config());
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (preg_match('#^/api/services/([a-z0-9.]+)$#', $route, $match) === 1) {
            if ($method !== 'POST') {
                json_out(['error' => 'I servizi accettano solo POST.'], 405);
                return;
            }
            $raw = file_get_contents('php://input') ?: '';
            $body = $raw === '' ? [] : json_decode($raw, true);
            if (!is_array($body)) {
                json_out(['error' => 'Il corpo deve essere un oggetto JSON.'], 400);
                return;
            }
            json_out($lab->call($match[1], $body));
            return;
        }
        if ($method !== 'GET') {
            json_out(['error' => 'Lettura solo in GET.'], 405);
            return;
        }
        $payload = match ($route) {
            '/api/status' => $lab->status(),
            '/api/model' => $lab->model(),
            '/api/workplaces' => ['workplaces' => $lab->workplaces()],
            '/api/orders' => ['orders' => $lab->orders()],
            '/api/people' => ['people' => $lab->people()],
            '/api/operations' => ['operations' => $lab->operations()],
            '/api/monitor' => $lab->monitor(),
            '/api/kpi' => $lab->kpi(),
            '/api/lists' => $lab->lists(),
            '/api/log' => ['entries' => $lab->log()],
            default => null,
        };
        if ($payload === null && preg_match('#^/api/objects/([a-z]+)$#', $route, $match) === 1) {
            $payload = $lab->objectList($match[1]);
        }
        if ($payload === null && preg_match('#^/api/lists/([A-Za-z0-9-]+)$#', $route, $match) === 1) {
            $payload = $lab->runList($match[1]);
        }
        if ($payload === null) {
            json_out(['error' => 'Percorso assente.'], 404);
            return;
        }
        json_out($payload);
    } catch (LabException $e) {
        json_out(['error' => $e->getMessage()], $e->status);
    } catch (Throwable $e) {
        json_out(['error' => $e->getMessage()], 500);
    }
    return;
}

try {
    Lab::open(load_config());
} catch (Throwable $e) {
    fail_page($e->getMessage());
    return;
}

$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$base = rtrim($base, '/');
if ($base === '/' || $base === '.') {
    $base = '';
}
$api = ($base === '' ? '' : $base) . '/index.php?r=';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Banco X — laboratorio HYDRA X</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES) ?>/assets/app.css">
</head>
<body data-api="<?= htmlspecialchars($api, ENT_QUOTES) ?>">
    <div class="shell">
        <aside class="rail">
            <p class="mark">Banco X</p>
            <p class="mark-sub">Simulazione locale. Non è HYDRA.</p>
            <nav>
                <button type="button" data-view="terminal" class="is-on">Terminale</button>
                <button type="button" data-view="monitor">Monitor</button>
                <button type="button" data-view="kpi">KPI</button>
                <button type="button" data-view="lists">Liste</button>
                <button type="button" data-view="objects">Oggetti</button>
                <button type="button" data-view="services">Servizi</button>
                <button type="button" data-view="bridge">Da 8 a X</button>
            </nav>
        </aside>
        <div class="main">
            <header class="top">
                <div>
                    <p class="kicker" id="driver-line">Caricamento</p>
                    <h1 id="view-title">Terminale</h1>
                </div>
                <p class="stamp">Simulazione</p>
            </header>
            <p class="alert" id="alert" hidden></p>
            <div id="app"></div>
        </div>
    </div>
    <script src="<?= htmlspecialchars($base, ENT_QUOTES) ?>/assets/app.js"></script>
</body>
</html>
