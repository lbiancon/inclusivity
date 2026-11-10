<?php

declare(strict_types=1);

putenv('MENUCRAFT_DB_HOST=127.0.0.1');
putenv('MENUCRAFT_DB_PORT=3306');
putenv('MENUCRAFT_DB_NAME=menucraft');
putenv('MENUCRAFT_DB_USER=menucraft');
putenv('MENUCRAFT_DB_PASS=menucraft');

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Enums\PagamentoType;
use MenuCraft\Enums\PortataType;
use MenuCraft\Models\AziendaConfig;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\KitchenSummary;
use MenuCraft\Services\RegoleAzienda;
use MenuCraft\Services\ItalianHolidayCalendar;
use MenuCraft\Services\MenuPublisher;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\MenuValidator;
use MenuCraft\Services\OrderService;
use MenuCraft\Services\SpreadsheetMenuParser;
use MenuCraft\Support\Money;

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$falliti = 0;

function verifica(bool $condizione, string $messaggio): void
{
    global $falliti;
    if ($falliti > 40) {
        return;
    }
    if (!$condizione) {
        $falliti++;
        fwrite(STDERR, "FAIL {$messaggio}\n");
    }
}

function attesaEccezione(string $codice, callable $azione, string $messaggio): void
{
    try {
        $azione();
        verifica(false, $messaggio . ' (nessuna eccezione)');
    } catch (MenuRuleException $e) {
        verifica($e->codice() === $codice, $messaggio . " atteso {$codice}, ricevuto {$e->codice()} ({$e->getMessage()})");
    }
}

$fuso = new DateTimeZone('Europe/Rome');
$pdo = menucraft_db();
$validator = new MenuValidator($pdo);
$ordini = new OrderService($pdo, $validator);

verifica((bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false, 'prepared statements nativi');
verifica((string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn() !== '', 'sql mode impostato');

$calendario = new ItalianHolidayCalendar($fuso);
verifica($calendario->pasqua(2024)->format('Y-m-d') === '2024-03-31', 'Pasqua 2024');
verifica($calendario->pasqua(2025)->format('Y-m-d') === '2025-04-20', 'Pasqua 2025');
verifica($calendario->pasqua(2026)->format('Y-m-d') === '2026-04-05', 'Pasqua 2026');
verifica($calendario->pasqua(2027)->format('Y-m-d') === '2027-03-28', 'Pasqua 2027');
verifica(($calendario->nazionali(2026)['2026-04-06'] ?? '') === "Lunedì dell'Angelo", 'Pasquetta 2026');

verifica(Money::euroToCentesimi('8.20') === 820, 'centesimi 8.20');
verifica(Money::normalizza('4,2') === '4.20', 'normalizza virgola');
verifica(Money::formatta(1810) === '18.10', 'formatta 18.10');

$azienda = new AziendaConfig(1, 'Test', 'test', PagamentoType::Dipendente, 5, false, false, '09:30:00', '18:00:00');
$welfare = new AziendaConfig(2, 'Welfare', 'welfare', PagamentoType::Welfare, 5, false, true, '09:30:00', '18:00:00');
$flessibile = new AziendaConfig(3, 'Rossi', 'rossi', PagamentoType::Dipendente, 5, true, true, '09:30:00', '18:00:00');

$mercoledi = new DateTimeImmutable('2026-11-11 09:30:00', $fuso);
$validator->validaCutoff($azienda, new DateTimeImmutable('2026-11-11', $fuso), $mercoledi);
attesaEccezione('CUTOFF_OGGI', fn () => $validator->validaCutoff(
    $azienda,
    new DateTimeImmutable('2026-11-11', $fuso),
    new DateTimeImmutable('2026-11-11 09:30:01', $fuso),
), 'stesso giorno dopo il limite');
$validator->validaCutoff(
    $azienda,
    new DateTimeImmutable('2026-11-12', $fuso),
    new DateTimeImmutable('2026-11-11 17:59:59', $fuso),
);
attesaEccezione('CUTOFF_DOMANI', fn () => $validator->validaCutoff(
    $azienda,
    new DateTimeImmutable('2026-11-12', $fuso),
    new DateTimeImmutable('2026-11-11 18:00:00', $fuso),
), 'domani bloccato alle 18:00');
$validator->validaCutoff(
    $azienda,
    new DateTimeImmutable('2026-11-13', $fuso),
    new DateTimeImmutable('2026-11-11 23:40:00', $fuso),
);
attesaEccezione('DATA_PASSATA', fn () => $validator->validaCutoff(
    $azienda,
    new DateTimeImmutable('2026-11-10', $fuso),
    $mercoledi,
), 'data passata');

verifica($validator->motivoWeekend(new DateTimeImmutable('2026-11-14', $fuso)) !== null, 'sabato chiuso');
verifica($validator->motivoWeekend(new DateTimeImmutable('2026-11-15', $fuso)) !== null, 'domenica chiusa');
verifica($validator->motivoWeekend(new DateTimeImmutable('2026-11-13', $fuso)) === null, 'venerdì aperto');

$validator->validaComposizione($azienda, [PortataType::Primo, PortataType::Secondo, PortataType::Contorno, PortataType::Frutta, PortataType::Bevanda]);
attesaEccezione('LIMITE_SECONDO', fn () => $validator->validaComposizione($azienda, [PortataType::Secondo, PortataType::Secondo]), 'due secondi');
attesaEccezione('LIMITE_PRIMO', fn () => $validator->validaComposizione($azienda, [PortataType::Primo, PortataType::Primo]), 'doppio primo negato');
$validator->validaComposizione($flessibile, [PortataType::Primo, PortataType::Primo, PortataType::Contorno, PortataType::Contorno]);
attesaEccezione('LIMITE_PRIMO', fn () => $validator->validaComposizione($flessibile, [PortataType::Primo, PortataType::Primo, PortataType::Primo]), 'tre primi');
attesaEccezione('LIMITE_SCELTE', fn () => $validator->validaComposizione($azienda, [
    PortataType::Primo,
    PortataType::Secondo,
    PortataType::Contorno,
    PortataType::Dessert,
    PortataType::Yogurt,
    PortataType::Bevanda,
]), 'sesta scelta');
verifica($welfare->prezzoApplicato('8.20') === '0.00', 'welfare azzera il prezzo');
verifica($azienda->prezzoApplicato('8.20') === '8.20', 'dipendente paga il listino');

$giovediSera = new DateTimeImmutable('2026-11-12 19:00:00', $fuso);
$finestra = $validator->finestraPubblicazione($giovediSera);
verifica($finestra['dal']->format('Y-m-d') === '2026-11-16', 'caricamento dal lunedì successivo');
verifica($finestra['al']->format('Y-m-d') === '2026-12-31', 'caricamento fino a fine mese successivo');
verifica($finestra['visibile_dal']->format('Y-m-d H:i') === '2026-11-13 07:00', 'prenotazioni dal venerdì mattina');
attesaEccezione('CARICAMENTO_ORARIO', fn () => $validator->assertCaricamentoConsentito(new DateTimeImmutable('2026-11-12 18:59:59', $fuso)), 'giovedì prima delle 19');
attesaEccezione('CARICAMENTO_GIORNO', fn () => $validator->assertCaricamentoConsentito(new DateTimeImmutable('2026-11-11 20:00:00', $fuso)), 'caricamento fuori giovedì');

$parser = new SpreadsheetMenuParser($fuso);
$csv = tempnam(sys_get_temp_dir(), 'mc');
file_put_contents($csv, "data;codice;nome;categoria;prezzo\n2026-11-16;PR-TEST;Pasta al burro;primo;3,50\n");
$righeCsv = $parser->parse($csv, 'menu.csv');
verifica(count($righeCsv) === 1 && $righeCsv[0]->prezzo === '3.50' && $righeCsv[0]->categoria === PortataType::Primo, 'parser csv');
unlink($csv);

$xlsx = tempnam(sys_get_temp_dir(), 'mc') . '.xlsx';
$baseExcel = new DateTimeImmutable('1899-12-30', $fuso);
$seriale = $baseExcel->diff(new DateTimeImmutable('2026-11-17', $fuso))->days;
$zip = new ZipArchive();
$zip->open($xlsx, ZipArchive::CREATE);
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
$zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>data</t></si><si><t>codice</t></si><si><t>nome</t></si><si><t>categoria</t></si><si><t>prezzo</t></si><si><t>SE-XLS</t></si><si><t>Pollo</t></si><si><t>secondo</t></si><si><t>5.80</t></si></sst>');
$zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
    . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c></row>'
    . '<row r="2"><c r="A2"><v>' . $seriale . '</v></c><c r="B2" t="s"><v>5</v></c><c r="C2" t="s"><v>6</v></c><c r="D2" t="s"><v>7</v></c><c r="E2" t="s"><v>8</v></c></row>'
    . '</sheetData></worksheet>');
$zip->close();
$righeXlsx = $parser->parse($xlsx, 'menu.xlsx');
verifica($righeXlsx[0]->data->format('Y-m-d') === '2026-11-17' && $righeXlsx[0]->codice === 'SE-XLS', 'parser xlsx');
unlink($xlsx);

$csvMale = tempnam(sys_get_temp_dir(), 'mc');
file_put_contents($csvMale, "data,codice,nome,categoria,prezzo\n2026-11-16,BAD,Piatto,zuppa,1.00\n");
attesaEccezione('FILE_NON_VALIDO', fn () => $parser->parse($csvMale, 'male.csv'), 'categoria rifiutata');
unlink($csvMale);

$hash = (string) $pdo->query("SELECT u.password_hash FROM utenti u INNER JOIN aziende a ON a.id = u.azienda_id WHERE a.slug = 'aurora-welfare' AND u.matricola = 'A100'")->fetchColumn();
verifica(str_starts_with($hash, '$2y$12$'), 'bcrypt cost 12');
verifica($hash !== 'MenuCraft!2026' && password_verify('MenuCraft!2026', $hash), 'password non in chiaro');

$auroraDemo = $pdo->query("SELECT u.id AS utente_id, a.id AS azienda_id FROM utenti u INNER JOIN aziende a ON a.id = u.azienda_id WHERE a.slug = 'aurora-welfare' AND u.matricola = 'A100'")->fetch();
$rossiDemo = $pdo->query("SELECT u.id AS utente_id, a.id AS azienda_id FROM utenti u INNER JOIN aziende a ON a.id = u.azienda_id WHERE a.slug = 'officine-rossi' AND u.matricola = 'R200'")->fetch();
$board = new MenuCraft\Services\BookingBoard($pdo, $validator, $ordini);
$adessoDemo = new DateTimeImmutable('now', $fuso);
$quadroAurora = $board->carica((int) $auroraDemo['azienda_id'], (int) $auroraDemo['utente_id'], null, $adessoDemo);
$quadroRossi = $board->carica((int) $rossiDemo['azienda_id'], (int) $rossiDemo['utente_id'], null, $adessoDemo);
$settimanaAurora = $quadroAurora['settimane'][1];
verifica($settimanaAurora['lunedi_saltato'] === true, 'Aurora salta il lunedì patronale');
verifica($settimanaAurora['ancora'] === $settimanaAurora['giorni'][1]['data'], 'il focus di quella settimana è martedì');
verifica($quadroRossi['settimane'][1]['lunedi_saltato'] === false, 'Rossi non eredita la festa patronale');
$prezziWelfare = [];
foreach ($quadroAurora['giorno']['portate'] as $portata) {
    foreach ($portata['piatti'] as $piatto) {
        $prezziWelfare[] = $piatto['prezzo'];
    }
}
verifica($prezziWelfare !== [] && count(array_unique($prezziWelfare)) === 1 && $prezziWelfare[0] === '0.00', 'il menu welfare mostra solo 0.00');
$prezziRossi = [];
foreach ($quadroRossi['giorno']['portate'] as $portata) {
    foreach ($portata['piatti'] as $piatto) {
        $prezziRossi[] = $piatto['prezzo'];
    }
}
verifica(in_array('8.20', $prezziRossi, true), 'il menu dipendente mostra il listino');

$pulizia = $pdo->prepare('DELETE FROM aziende WHERE slug LIKE :slug');
$pulizia->execute(['slug' => 'qa-%']);
$pdo->prepare('DELETE FROM login_tentativi WHERE azienda_slug LIKE :slug')->execute(['slug' => 'qa-%']);

function creaTenant(PDO $pdo, string $slug, string $tipo, bool $doppioPrimo, bool $doppioContorno, int $wallet): array
{
    $azienda = $pdo->prepare(
        'INSERT INTO aziende (nome, slug, tipo_pagamento, max_scelte, permetti_doppio_primo, permetti_doppio_contorno, limite_oggi, limite_domani)
         VALUES (:nome, :slug, :tipo, 5, :primo, :contorno, \'09:30:00\', \'18:00:00\')'
    );
    $azienda->execute([
        'nome' => $slug,
        'slug' => $slug,
        'tipo' => $tipo,
        'primo' => $doppioPrimo ? 1 : 0,
        'contorno' => $doppioContorno ? 1 : 0,
    ]);
    $aziendaId = (int) $pdo->lastInsertId();
    $hash = password_hash('MenuCraft!2026', PASSWORD_BCRYPT, ['cost' => 4]);
    $utente = $pdo->prepare(
        'INSERT INTO utenti (azienda_id, matricola, nome, password_hash, ruolo) VALUES (?, ?, ?, ?, ?)'
    );
    $utente->execute([$aziendaId, 'DIP', 'Dipendente QA', $hash, 'dipendente']);
    $dipendente = (int) $pdo->lastInsertId();
    $utente->execute([$aziendaId, 'CAT', 'Caterer QA', $hash, 'caterer']);
    $caterer = (int) $pdo->lastInsertId();
    if ($wallet > 0) {
        $pdo->prepare('INSERT INTO portafogli (azienda_id, utente_id, saldo_centesimi) VALUES (?, ?, ?)')
            ->execute([$aziendaId, $dipendente, $wallet]);
    }

    return [$aziendaId, $dipendente, $caterer];
}

/** @param list<array{codice: string, nome: string, categoria: string, prezzo: string}> $piatti */
function pubblicaGiorno(PDO $pdo, int $aziendaId, int $catererId, array $piatti, string $data): array
{
    $inserisci = $pdo->prepare(
        'INSERT INTO piatti (azienda_id, codice, nome, categoria, prezzo) VALUES (?, ?, ?, ?, ?)'
    );
    $ids = [];
    foreach ($piatti as $piatto) {
        $inserisci->execute([$aziendaId, $piatto['codice'], $piatto['nome'], $piatto['categoria'], $piatto['prezzo']]);
        $ids[] = (int) $pdo->lastInsertId();
    }
    $pdo->prepare(
        'INSERT INTO menu_pubblicazioni (azienda_id, pubblicato_il, visibile_dal, valido_dal, valido_al, caricato_da)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$aziendaId, '2026-11-05 19:30:00', '2026-11-06 07:00:00', $data, $data, $catererId]);
    $pubblicazione = (int) $pdo->lastInsertId();
    $voce = $pdo->prepare(
        'INSERT INTO menu_voci (azienda_id, pubblicazione_id, piatto_id, data_pasto) VALUES (?, ?, ?, ?)'
    );
    foreach ($ids as $id) {
        $voce->execute([$aziendaId, $pubblicazione, $id, $data]);
    }

    return $ids;
}

[$auroraId, $auroraUser, $auroraCaterer] = creaTenant($pdo, 'qa-aurora', 'welfare', false, false, 0);
[$rossiId, $rossiUser, $rossiCaterer] = creaTenant($pdo, 'qa-rossi', 'dipendente', true, true, 1500);
$catalogo = [
    ['codice' => 'PR1', 'nome' => 'Pasta', 'categoria' => 'primo', 'prezzo' => '3.50'],
    ['codice' => 'PR2', 'nome' => 'Riso', 'categoria' => 'primo', 'prezzo' => '4.20'],
    ['codice' => 'SE1', 'nome' => 'Pollo', 'categoria' => 'secondo', 'prezzo' => '5.80'],
    ['codice' => 'SE2', 'nome' => 'Merluzzo', 'categoria' => 'secondo', 'prezzo' => '8.20'],
    ['codice' => 'CO1', 'nome' => 'Insalata', 'categoria' => 'contorno', 'prezzo' => '1.80'],
    ['codice' => 'CO2', 'nome' => 'Verdure', 'categoria' => 'contorno', 'prezzo' => '2.10'],
    ['codice' => 'DE1', 'nome' => 'Budino', 'categoria' => 'dessert', 'prezzo' => '2.40'],
    ['codice' => 'BE1', 'nome' => 'Acqua', 'categoria' => 'bevanda', 'prezzo' => '0.80'],
];
$idsRossi = pubblicaGiorno($pdo, $rossiId, $rossiCaterer, $catalogo, '2026-11-13');
$idsAurora = pubblicaGiorno($pdo, $auroraId, $auroraCaterer, $catalogo, '2026-11-13');
pubblicaGiorno($pdo, $rossiId, $rossiCaterer, [['codice' => 'BE2', 'nome' => 'Acqua 2', 'categoria' => 'bevanda', 'prezzo' => '0.80']], '2026-11-11');

$adesso = new DateTimeImmutable('2026-11-11 08:00:00', $fuso);
$esito = $ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsRossi[0], $idsRossi[2], $idsRossi[4]], $adesso);
verifica($esito['ordine']['totale'] === '11.10', 'totale dipendente 3.50+5.80+1.80');
verifica($esito['saldo'] === '3.90', 'wallet scalato a 3.90');

$aggiornato = $ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsRossi[7]], $adesso);
verifica($aggiornato['ordine']['totale'] === '0.80', 'modifica riduce il conto');
verifica($aggiornato['saldo'] === '14.20', 'rimborso del delta');

attesaEccezione('SALDO_INSUFFICIENTE', fn () => $ordini->invia(
    $rossiUser,
    $rossiId,
    '2026-11-13',
    [$idsRossi[1], $idsRossi[3], $idsRossi[5], $idsRossi[6], $idsRossi[7]],
    $adesso,
), 'wallet insufficiente');
$saldoDopoRifiuto = $ordini->saldo(AziendaConfig::trova($pdo, $rossiId), $rossiUser);
verifica($saldoDopoRifiuto === '14.20', 'rifiuto non muove il wallet');

attesaEccezione('PIATTI_NON_DISPONIBILI', fn () => $ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsAurora[0]], $adesso), 'isolamento tenant');
attesaEccezione('LIMITE_SECONDO', fn () => $ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsRossi[2], $idsRossi[3]], $adesso), 'secondo doppio rifiutato');
$ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsRossi[0], $idsRossi[1]], $adesso);
attesaEccezione('LIMITE_PRIMO', fn () => $ordini->invia($auroraUser, $auroraId, '2026-11-13', [$idsAurora[0], $idsAurora[1]], $adesso), 'welfare senza doppio primo');

$welfareEsito = $ordini->invia($auroraUser, $auroraId, '2026-11-13', [$idsAurora[3]], $adesso);
verifica($welfareEsito['ordine']['totale'] === '0.00', 'ordine welfare a zero');
verifica($welfareEsito['saldo'] === null, 'welfare senza wallet');
verifica($welfareEsito['ordine']['piatti'][0]['prezzo'] === '0.00', 'riga welfare a zero');

attesaEccezione('GIORNO_CHIUSO', fn () => $ordini->invia($rossiUser, $rossiId, '2026-11-14', [$idsRossi[7]], $adesso), 'sabato rifiutato');
attesaEccezione('GIORNO_CHIUSO', fn () => $ordini->invia($rossiUser, $rossiId, '2026-12-25', [$idsRossi[7]], $adesso), 'Natale rifiutato');
attesaEccezione('CUTOFF_OGGI', fn () => $ordini->invia(
    $rossiUser,
    $rossiId,
    '2026-11-11',
    [(int) $pdo->query("SELECT id FROM piatti WHERE azienda_id = {$rossiId} AND codice = 'BE2'")->fetchColumn()],
    new DateTimeImmutable('2026-11-11 09:31:00', $fuso),
), 'cutoff di oggi sul servizio');
attesaEccezione('DATA_NON_VALIDA', fn () => $ordini->invia($rossiUser, $rossiId, '2026-11-13; DROP TABLE ordini', [$idsRossi[7]], $adesso), 'data non interpolata');
verifica((int) $pdo->query("SELECT COUNT(*) FROM ordini")->fetchColumn() > 0, 'tabella ordini ancora presente');

$annullo = $ordini->invia($rossiUser, $rossiId, '2026-11-13', [], $adesso);
verifica($annullo['annullato'] === true, 'annullo prenotazione');

$pdo->prepare('INSERT INTO calendario_chiusure (azienda_id, data_chiusura, descrizione) VALUES (?, ?, ?)')
    ->execute([$auroraId, '2026-11-16', 'Festa patronale QA']);
$motivo = $validator->motivoCalendario($auroraId, new DateTimeImmutable('2026-11-16', $fuso));
verifica(is_string($motivo) && str_contains($motivo, 'Festa patronale QA'), 'chiusura di tenant');
$motivoRossi = $validator->motivoCalendario($rossiId, new DateTimeImmutable('2026-11-16', $fuso));
verifica($motivoRossi === null, 'la chiusura non trapela sull\'altro tenant');

$auth = new AuthService($pdo);
$login = $auth->login('qa-rossi', 'DIP', 'MenuCraft!2026');
verifica($login['utente']['nome'] === 'Dipendente QA' && $login['azienda']['id'] === $rossiId, 'login nel tenant corretto');
verifica(strlen($login['csrf']) === 64, 'csrf emesso');
attesaEccezione('CREDENZIALI', fn () => $auth->login("qa-rossi' OR 1=1 --", 'DIP', 'MenuCraft!2026'), 'slug rifiutato');
attesaEccezione('CREDENZIALI', fn () => $auth->login('qa-aurora', 'DIP', 'sbagliata'), 'password errata');

$csvOk = tempnam(sys_get_temp_dir(), 'mc');
file_put_contents($csvOk, "data,codice,nome,categoria,prezzo\n2026-11-17,NX-1,Minestrone,primo,3.10\n2026-11-18,NX-1,Minestrone,primo,3.10\n");
$publisher = new MenuPublisher($pdo, $validator, new SpreadsheetMenuParser($fuso));
$pubblicato = $publisher->pubblica($rossiId, $rossiCaterer, $csvOk, 'settimana.csv', $giovediSera);
verifica($pubblicato['giorni'] === 2 && $pubblicato['visibile_dal'] === '2026-11-13 07:00', 'pubblicazione giovedì sera');
unlink($csvOk);
attesaEccezione('CARICAMENTO_GIORNO', fn () => $publisher->pubblica(
    $rossiId,
    $rossiCaterer,
    $csvOk,
    'settimana.csv',
    new DateTimeImmutable('2026-11-11 20:00:00', $fuso),
), 'upload fuori finestra');

$conteggioRossi = (int) $pdo->query('SELECT COUNT(*) FROM piatti WHERE azienda_id = ' . $rossiId)->fetchColumn();
$conteggioAurora = (int) $pdo->query('SELECT COUNT(*) FROM piatti WHERE azienda_id = ' . $auroraId . " AND codice = 'NX-1'")->fetchColumn();
verifica($conteggioRossi > 0 && $conteggioAurora === 0, 'il menu caricato resta nel tenant');

$cucina = new KitchenSummary($pdo);
$auroraCucina = $cucina->perGiorno($auroraId, '2026-11-13');
verifica($auroraCucina['buoni'] === 1 && $auroraCucina['porzioni'][0]['codice'] === 'SE2', 'riepilogo cucina del tenant');
verifica($cucina->perGiorno($rossiId, '2026-11-13')['buoni'] === 0, 'riepilogo isolato per azienda');
verifica(str_contains($cucina->csv($auroraId, 'QA Aurora', '2026-11-13'), 'SE2;'), 'csv porzioni');

$regoleServizio = new RegoleAzienda($pdo);
$regoleNuove = $regoleServizio->salva($auroraId, [
    'max_scelte' => 3,
    'permetti_doppio_primo' => false,
    'permetti_doppio_contorno' => true,
    'limite_oggi' => '08:15',
    'limite_domani' => '17:45',
]);
verifica($regoleNuove->maxScelte === 3 && $regoleNuove->permettiDoppioContorno && $regoleNuove->limiteOggi === '08:15:00', 'regole salvate sull\'azienda');
$rossiIntatto = AziendaConfig::trova($pdo, $rossiId);
verifica($rossiIntatto->maxScelte === 5 && $rossiIntatto->limiteOggi === '09:30:00', 'le regole restano nel tenant');
attesaEccezione('REGOLA_NON_VALIDA', fn () => $regoleServizio->salva($auroraId, [
    'max_scelte' => 0,
    'limite_oggi' => '09:30',
    'limite_domani' => '18:00',
]), 'scelte fuori scala');

$pulizia->execute(['slug' => 'qa-%']);
$pdo->prepare('DELETE FROM login_tentativi WHERE azienda_slug LIKE :slug')->execute(['slug' => 'qa-%']);

if ($falliti > 0) {
    fwrite(STDERR, "{$falliti} controlli falliti\n");
    exit(1);
}

fwrite(STDOUT, "Tutti i controlli MenuCraft sono passati.\n");
