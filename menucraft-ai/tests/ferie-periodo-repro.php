<?php

declare(strict_types=1);

putenv('MENUCRAFT_DB_HOST=127.0.0.1');
putenv('MENUCRAFT_DB_PORT=3306');
putenv('MENUCRAFT_DB_NAME=menucraft');
putenv('MENUCRAFT_DB_USER=menucraft');
putenv('MENUCRAFT_DB_PASS=menucraft');

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Services\CalendarioChiusure;
use MenuCraft\Services\MenuValidator;

$pdo = menucraft_db();
$fuso = new DateTimeZone('Europe/Rome');
$adesso = new DateTimeImmutable('now', $fuso);
$dal = $adesso->modify('+10 days');
while ((int) $dal->format('N') !== 1) { // lunedì
    $dal = $dal->modify('+1 day');
}
$al = $dal->modify('+4 days'); // lun-ven
$slug = 'qa-ferie-' . $dal->format('Ymd');
$pdo->prepare('DELETE FROM aziende WHERE slug = ?')->execute([$slug]);
$pdo->prepare(
    'INSERT INTO aziende (nome, slug, tipo_pagamento, max_scelte, min_scelte, permetti_doppio_primo, permetti_doppio_contorno, limite_oggi, limite_domani, bevanda_obbligatoria, caricamento_menu)
     VALUES (?, ?, \'welfare\', 5, 1, 0, 0, \'09:30:00\', \'18:00:00\', 0, \'sempre\')'
)->execute([$slug, $slug]);
$aziendaId = (int) $pdo->lastInsertId();

$cal = new CalendarioChiusure($pdo);
$messaggio = 'Mensa chiusa per ferie aziendali. Riapertura dopo le date indicate.';
$voce = $cal->aggiungi($aziendaId, [
    'data' => $dal->format('Y-m-d'),
    'data_al' => $al->format('Y-m-d'),
    'messaggio' => $messaggio,
]);

echo 'aggiunti=' . $voce['giorni_aggiunti'] . ' dal=' . $voce['data'] . ' al=' . $voce['data_al'] . "\n";
$validator = new MenuValidator($pdo);
$motivo = $validator->motivoCalendario($aziendaId, $dal);
echo 'motivo=' . $motivo . "\n";
// Lun–ven con 8 dicembre (festa) nel mezzo → 4 giorni lavorativi chiusi.
$ok = ($voce['giorni_aggiunti'] ?? 0) === 4
    && is_string($motivo)
    && str_contains($motivo, 'ferie aziendali')
    && !str_starts_with($motivo, 'Mensa chiusa: Mensa');
echo $ok ? "OK\n" : "FAIL\n";
exit($ok ? 0 : 1);
