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
use MenuCraft\Services\Anagrafica;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\CalendarioChiusure;
use MenuCraft\Services\PrenotazioniAzienda;
use MenuCraft\Services\KitchenSummary;
use MenuCraft\Services\RegoleAzienda;
use MenuCraft\Services\ItalianHolidayCalendar;
use MenuCraft\Services\LogoAzienda;
use MenuCraft\Services\MenuBanco;
use MenuCraft\Services\MenuPublisher;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\MenuValidator;
use MenuCraft\Services\OrderService;
use MenuCraft\Services\SpreadsheetMenuParser;
use MenuCraft\Support\CodicePiatto;
use MenuCraft\Support\Money;
use MenuCraft\Services\StoricoPrenotazioni;

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
$daInternet = new AziendaConfig(4, 'Web', 'web', PagamentoType::Dipendente, 5, false, false, '09:30:00', '18:00:00', 'internet');
attesaEccezione('OGGI_SOLO_INTRANET', fn () => $validator->validaCutoff(
    $daInternet,
    new DateTimeImmutable('2026-11-11', $fuso),
    new DateTimeImmutable('2026-11-11 08:00:00', $fuso),
), 'internet blocca il pasto di oggi');
$validator->validaCutoff(
    $daInternet,
    new DateTimeImmutable('2026-11-12', $fuso),
    new DateTimeImmutable('2026-11-11 08:00:00', $fuso),
);

verifica($validator->motivoWeekend(new DateTimeImmutable('2026-11-14', $fuso)) !== null, 'sabato chiuso');
verifica($validator->motivoWeekend(new DateTimeImmutable('2026-11-15', $fuso)) !== null, 'domenica chiusa');
verifica($validator->motivoWeekend(new DateTimeImmutable('2026-11-13', $fuso)) === null, 'venerdì aperto');
$sabatoAperto = new AziendaConfig(30, 'Sabato', 'sabato', PagamentoType::Welfare, 5, false, false, '09:30:00', '18:00:00', 'intranet', true, false, 0, 0, 0, false, false, 0, 0, null, 5, '', '', 'sempre', true, true, false);
verifica($validator->motivoWeekend(new DateTimeImmutable('2026-11-14', $fuso), $sabatoAperto) === null, 'sabato lavorativo per il servizio');
verifica($validator->motivoWeekend(new DateTimeImmutable('2026-11-15', $fuso), $sabatoAperto) !== null, 'domenica resta chiusa se non aperta');

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
$conAcqua = new AziendaConfig(9, 'Acqua', 'acqua', PagamentoType::Welfare, 5, false, false, '09:30:00', '18:00:00', 'intranet', true, false, 0, 0, 0, false, false, 0, 0, null, 5);
$ampio = new AziendaConfig(10, 'Ampio', 'ampio', PagamentoType::Dipendente, 5, true, true, '09:30:00', '18:00:00', 'intranet', true, false, 2, 1, 3);
$validator->validaComposizione($ampio, [PortataType::Contorno, PortataType::Contorno, PortataType::Contorno, PortataType::Secondo, PortataType::Bevanda]);
$validator->validaQuantita($ampio, [
    ['id' => 1, 'nome' => 'Patate', 'categoria' => PortataType::Contorno],
    ['id' => 1, 'nome' => 'Patate', 'categoria' => PortataType::Contorno],
    ['id' => 2, 'nome' => 'Insalata', 'categoria' => PortataType::Contorno],
    ['id' => 3, 'nome' => 'Pollo', 'categoria' => PortataType::Secondo],
]);
$validator->validaQuantita($ampio, [
    ['id' => 8, 'nome' => 'Pasta', 'categoria' => PortataType::Primo],
    ['id' => 8, 'nome' => 'Pasta', 'categoria' => PortataType::Primo],
]);
attesaEccezione('DOPPIO_PIATTO', fn () => $validator->validaQuantita($ampio, [
    ['id' => 3, 'nome' => 'Pollo', 'categoria' => PortataType::Secondo],
    ['id' => 3, 'nome' => 'Pollo', 'categoria' => PortataType::Secondo],
]), 'doppio secondo non consentito');
attesaEccezione('LIMITE_PRIMO', fn () => $validator->validaComposizione($ampio, [PortataType::Primo, PortataType::Primo, PortataType::Primo, PortataType::Bevanda]), 'tre primi');
$validator->validaComposizione($conAcqua, [PortataType::Primo, PortataType::Secondo, PortataType::Contorno, PortataType::Frutta, PortataType::Bevanda]);
attesaEccezione('BEVANDA_OBBLIGATORIA', fn () => $validator->validaComposizione($conAcqua, [PortataType::Primo, PortataType::Secondo]), 'manca l\'acqua');
attesaEccezione('BEVANDA_OBBLIGATORIA', fn () => $validator->validaComposizione($conAcqua, [PortataType::Bevanda, PortataType::Bevanda]), 'due bevande');
attesaEccezione('SCELTE_INCOMPLETE', fn () => $validator->validaComposizione($conAcqua, [PortataType::Bevanda]), 'solo acqua non basta');
attesaEccezione('SCELTE_INCOMPLETE', fn () => $validator->validaComposizione($conAcqua, [
    PortataType::Primo,
    PortataType::Secondo,
    PortataType::Bevanda,
]), 'sotto il minimo con acqua');
attesaEccezione('LIMITE_SCELTE', fn () => $validator->validaComposizione($conAcqua, [
    PortataType::Primo,
    PortataType::Secondo,
    PortataType::Contorno,
    PortataType::Dessert,
    PortataType::Frutta,
    PortataType::Bevanda,
]), 'oltre il massimo con acqua');
verifica($welfare->prezzoApplicato('8.20') === '0.00', 'welfare azzera il prezzo');
verifica($azienda->prezzoApplicato('8.20') === '8.20', 'dipendente paga il listino');

$giovediSera = new DateTimeImmutable('2026-11-12 19:00:00', $fuso);
$soloGiovedi = new AziendaConfig(20, 'Giovedi', 'giovedi', PagamentoType::Welfare, 5, false, false, '09:30:00', '18:00:00', 'intranet', true, false, 0, 0, 0, false, false, 0, 0, null, 5, '', '', 'giovedi', false);
$sempreAperto = new AziendaConfig(21, 'Sempre', 'sempre', PagamentoType::Welfare, 5, false, false, '09:30:00', '18:00:00', 'intranet', true, false, 0, 0, 0, false, false, 0, 0, null, 5, '', '', 'sempre', true);
$finestra = $validator->finestraPubblicazione($soloGiovedi, $giovediSera);
verifica($finestra['dal']->format('Y-m-d') === '2026-11-16', 'caricamento dal lunedì successivo');
verifica($finestra['al']->format('Y-m-d') === '2026-12-31', 'caricamento fino a fine mese successivo');
verifica($finestra['visibile_dal']->format('Y-m-d H:i') === '2026-11-13 07:00', 'prenotazioni dal venerdì mattina');
attesaEccezione('CARICAMENTO_ORARIO', fn () => $validator->assertCaricamentoConsentito($soloGiovedi, new DateTimeImmutable('2026-11-12 18:59:59', $fuso)), 'giovedì prima delle 19');
attesaEccezione('CARICAMENTO_GIORNO', fn () => $validator->assertCaricamentoConsentito($soloGiovedi, new DateTimeImmutable('2026-11-11 20:00:00', $fuso)), 'caricamento fuori giovedì');
$validator->assertCaricamentoConsentito($sempreAperto, new DateTimeImmutable('2026-11-11 20:00:00', $fuso));
$finestraLibera = $validator->finestraPubblicazione($sempreAperto, new DateTimeImmutable('2026-11-11 20:00:00', $fuso));
verifica($finestraLibera['dal']->format('Y-m-d') === '2026-11-11', 'servizio libero carica dal giorno corrente');

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
$xlsRinominato = tempnam(sys_get_temp_dir(), 'mc') . '.xls';
copy($xlsx, $xlsRinominato);
$righeXls = $parser->parse($xlsRinominato, 'menu.xls');
verifica($righeXls[0]->codice === 'SE-XLS', 'xlsx rinominato in xls');
unlink($xlsx);
unlink($xlsRinominato);
$xlsVecchio = tempnam(sys_get_temp_dir(), 'mc') . '.xls';
file_put_contents($xlsVecchio, hex2bin('D0CF11E0A1B11AE1'));
attesaEccezione('FORMATO_XLS', fn () => $parser->parse($xlsVecchio, 'vecchio.xls'), 'xls binario rifiutato');
unlink($xlsVecchio);

$griglia = tempnam(sys_get_temp_dir(), 'mc') . '.xlsx';
scriviXlsx($griglia, [
    ['Menu Mensa dal 09 al 13 Novembre 2026'],
    ['', 'C_LU', '', 'C_MA', '', 'C_ME', '', 'C_GI', '', 'C_VE'],
    ['PRIMO', 'A', 'Pasta al pomodoro', 'A', 'Risotto allo zafferano', 'A', 'Minestrone', 'A', 'Tortellini', 'A', 'Passato di verdure'],
    ['', 'B', 'Lasagne', 'B', 'Pastina'],
    ['SECONDO', 'D', 'Pollo arrosto 9,90', 'D', 'Merluzzo'],
    ['FRUTTO', 'H', 'Mela', 'H', 'Pera'],
]);
$righeGriglia = $parser->parse($griglia, 'menu-settimana.xlsx');
unlink($griglia);
$perChiave = [];
foreach ($righeGriglia as $rigaGriglia) {
    $perChiave[$rigaGriglia->data->format('Y-m-d') . '|' . $rigaGriglia->codice] = $rigaGriglia;
}
verifica(
    ($perChiave['2026-11-09|A-20261109']->nome ?? '') === 'Pasta al pomodoro'
    && ($perChiave['2026-11-09|A-20261109']->categoria ?? null) === PortataType::Primo
    && ($perChiave['2026-11-09|A-20261109']->prezzo ?? null) === '',
    'griglia lunedì codice A',
);
verifica(($perChiave['2026-11-10|B-20261110']->nome ?? '') === 'Pastina', 'griglia martedì codice B');
verifica(
    ($perChiave['2026-11-09|D-20261109']->categoria ?? null) === PortataType::Secondo
    && ($perChiave['2026-11-09|D-20261109']->prezzo ?? '') === '9.90',
    'prezzo nel nome del template',
);
verifica(
    ($perChiave['2026-11-13|A-20261113']->nome ?? '') === 'Passato di verdure'
    && ($perChiave['2026-11-09|H-20261109']->categoria ?? null) === PortataType::Frutta,
    'venerdì e frutto',
);
verifica(CodicePiatto::etichetta('A-20261109') === 'A' && CodicePiatto::etichetta('SE2') === 'SE2', 'etichetta codice cucina');

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
        'INSERT INTO aziende (nome, slug, tipo_pagamento, max_scelte, permetti_doppio_primo, permetti_doppio_contorno, limite_oggi, limite_domani, bevanda_obbligatoria, caricamento_menu)
         VALUES (:nome, :slug, :tipo, 5, :primo, :contorno, \'09:30:00\', \'18:00:00\', 0, \'giovedi\')'
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
$storico = new StoricoPrenotazioni($pdo);
$storicoAurora = $storico->perUtente($auroraId, $auroraUser);
$storicoRossi = $storico->perUtente($rossiId, $rossiUser);
verifica(
    count($storicoAurora) === 1
    && $storicoAurora[0]['data'] === '2026-11-13'
    && $storicoAurora[0]['piatti'] !== []
    && $storicoRossi === [],
    'lo storico mostra solo le prenotazioni del dipendente',
);
verifica($annullo['annullato'] === true, 'annullo prenotazione');

$calendario = new CalendarioChiusure($pdo);
$patronale = $calendario->aggiungi($auroraId, [
    'data' => '2026-11-16',
    'descrizione' => 'Festa patronale QA',
]);
verifica($patronale['data'] === '2026-11-16', 'chiusura del servizio salvata');
$motivo = $validator->motivoCalendario($auroraId, new DateTimeImmutable('2026-11-16', $fuso));
verifica(is_string($motivo) && str_contains($motivo, 'Festa patronale QA'), 'chiusura di tenant');
$motivoRossi = $validator->motivoCalendario($rossiId, new DateTimeImmutable('2026-11-16', $fuso));
verifica($motivoRossi === null, 'la chiusura non trapela sull\'altro tenant');
$elencoChiusure = $calendario->elenco($auroraId);
verifica(count(array_filter($elencoChiusure['proprie'], static fn (array $voce): bool => $voce['data'] === '2026-11-16')) === 1, 'elenco chiusure proprie');
verifica($elencoChiusure['nazionali'] !== [], 'feste nazionali in sola lettura');
$calendario->togli($auroraId, (int) $patronale['id']);
verifica($validator->motivoCalendario($auroraId, new DateTimeImmutable('2026-11-16', $fuso)) === null, 'chiusura del servizio tolta');
attesaEccezione('CHIUSURA_NON_NECESSARIA', fn () => $calendario->aggiungi($auroraId, [
    'data' => '2026-11-14',
    'descrizione' => 'Sabato inutile',
]), 'sabato non si segna come chiusura se resta chiuso');
$nazionaleId = (int) ($elencoChiusure['nazionali'][0]['id'] ?? 0);
$nazionaleData = (string) ($elencoChiusure['nazionali'][0]['data'] ?? '');
if ($nazionaleId > 0) {
    attesaEccezione('CHIUSURA_NON_TROVATA', fn () => $calendario->togli($auroraId, $nazionaleId), 'le feste nazionali non si cancellano dal banco');
}
if ($nazionaleData !== '') {
    $apertura = $calendario->apri($auroraId, [
        'data' => $nazionaleData,
        'descrizione' => 'Aperto in deroga',
    ]);
    verifica($validator->motivoCalendario($auroraId, new DateTimeImmutable($nazionaleData, $fuso)) === null, 'festa nazionale resa lavorativa');
    $calendario->togliApertura($auroraId, (int) $apertura['id']);
    verifica($validator->motivoCalendario($auroraId, new DateTimeImmutable($nazionaleData, $fuso)) !== null, 'togliendo la deroga la festa torna chiusa');
}
$pdo->prepare('UPDATE aziende SET apri_sabato = 1 WHERE id = :id')->execute(['id' => $auroraId]);
verifica($validator->motivoCalendario($auroraId, new DateTimeImmutable('2026-11-14', $fuso)) === null, 'con apri_sabato il sabato è lavorativo');
$pdo->prepare('UPDATE aziende SET apri_sabato = 0 WHERE id = :id')->execute(['id' => $auroraId]);
$calendario->aggiungi($auroraId, [
    'data' => '2026-11-16',
    'descrizione' => 'Festa patronale QA',
]);

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
$pdo->prepare('UPDATE aziende SET caricamento_menu = \'sempre\' WHERE id = :id')->execute(['id' => $rossiId]);
$csvLibero = tempnam(sys_get_temp_dir(), 'mc');
file_put_contents($csvLibero, "data,codice,nome,categoria,prezzo\n2026-11-12,LIB-1,Zuppa libera,primo,3.00\n");
$libero = $publisher->pubblica(
    $rossiId,
    $rossiCaterer,
    $csvLibero,
    'libero.csv',
    new DateTimeImmutable('2026-11-11 20:00:00', $fuso),
);
verifica($libero['giorni'] === 1, 'servizio libero carica il file fuori dal giovedì');
unlink($csvLibero);
$pdo->prepare('UPDATE aziende SET caricamento_menu = \'giovedi\' WHERE id = :id')->execute(['id' => $rossiId]);

$conteggioRossi = (int) $pdo->query('SELECT COUNT(*) FROM piatti WHERE azienda_id = ' . $rossiId)->fetchColumn();
$conteggioAurora = (int) $pdo->query('SELECT COUNT(*) FROM piatti WHERE azienda_id = ' . $auroraId . " AND codice = 'NX-1'")->fetchColumn();
verifica($conteggioRossi > 0 && $conteggioAurora === 0, 'il menu caricato resta nel tenant');

$pdo->prepare('INSERT INTO piatti (azienda_id, codice, nome, categoria, prezzo) VALUES (?, ?, ?, ?, ?)')
    ->execute([$rossiId, 'OLD-POLLO', 'Pollo arrosto', 'secondo', '9.90']);
$settimana = tempnam(sys_get_temp_dir(), 'mc') . '.xlsx';
scriviXlsx($settimana, [
    ['Menu Mensa dal 16 al 20 novembre 2026'],
    ['', "C_LU\nLUNEDI'", '', 'C_MA'],
    ['PRIMO', 'A', 'Tortelli di zucca', 'A', 'Riso in bianco'],
    ['SECONDO', 'D', 'Pollo arrosto', 'D', 'Pollo arrosto'],
]);
$pubblicataGriglia = $publisher->pubblica($rossiId, $rossiCaterer, $settimana, 'template-giovedi.xlsx', $giovediSera);
verifica($pubblicataGriglia['giorni'] === 2, 'template settimanale pubblicato');
$prezzoRiusato = (string) $pdo->query('SELECT prezzo FROM piatti WHERE azienda_id = ' . $rossiId . " AND codice = 'D-20261116'")->fetchColumn();
$prezzoNuovo = (string) $pdo->query('SELECT prezzo FROM piatti WHERE azienda_id = ' . $rossiId . " AND codice = 'A-20261116'")->fetchColumn();
verifica($prezzoRiusato === '9.90' && $prezzoNuovo === '0.00', 'prezzo noto riusato, piatto nuovo a 0,00');
$pubblicataAurora = $publisher->pubblica($auroraId, $auroraCaterer, $settimana, 'template-giovedi.xlsx', $giovediSera);
verifica($pubblicataAurora['giorni'] === 1 && $pubblicataAurora['valido_dal'] === '2026-11-17', 'il lunedì chiuso non entra nel menu');
unlink($settimana);

$cucina = new KitchenSummary($pdo);
$auroraCucina = $cucina->perGiorno($auroraId, '2026-11-13');
verifica($auroraCucina['buoni'] === 1 && $auroraCucina['porzioni'][0]['codice'] === 'SE2', 'riepilogo cucina del tenant');
verifica($cucina->perGiorno($rossiId, '2026-11-13')['buoni'] === 0, 'riepilogo isolato per azienda');
$csvCucina = $cucina->csv($auroraId, 'QA Aurora', '2026-11-13');
verifica(str_contains($csvCucina, 'SE2;') && str_contains($csvCucina, 'TOT. 1') && str_contains($csvCucina, 'CONTEGGI'), 'csv con lettera, totale e parziali');
verifica($auroraCucina['porzioni'][0]['lettera'] !== '' && $auroraCucina['porzioni'][0]['fascia'] === 'verde', 'il parziale cucina ha lettera e fascia');

$prenotazioni = new PrenotazioniAzienda($pdo);
$listaAurora = $prenotazioni->delGiorno($auroraId, '2026-11-13');
verifica(
    count($listaAurora) === 1
    && $listaAurora[0]['matricola'] === 'DIP'
    && $listaAurora[0]['a_carico'] === 'azienda'
    && $listaAurora[0]['piatti'][0]['nome'] === 'Merluzzo',
    'prenotazioni dell\'azienda',
);
verifica($prenotazioni->delGiorno($rossiId, '2026-11-13') === [], 'le prenotazioni non trapelano');

verifica($ordini->saldo(AziendaConfig::trova($pdo, $rossiId), $rossiUser) === '15.00', 'annullo restituisce il wallet');
$ospiteAzienda = $ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsRossi[7]], $adesso, true, 'Mario Ospite', 'azienda');
verifica($ospiteAzienda['ordine']['totale'] === '0.00' && $ospiteAzienda['saldo'] === '15.00', 'ospite a carico azienda non scala il wallet');
$personale = $ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsRossi[7]], $adesso);
verifica($personale['ordine']['totale'] === '0.80' && $personale['saldo'] === '14.20', 'il vassoio personale convive con l\'ospite');
verifica((int) $pdo->query('SELECT COUNT(*) FROM ordini WHERE utente_id = ' . $rossiUser . " AND data_pasto = '2026-11-13'")->fetchColumn() === 2, 'due buoni lo stesso giorno');
$ospiteMio = $ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsRossi[7]], $adesso, true, 'Mario Ospite', 'dipendente');
verifica($ospiteMio['ordine']['totale'] === '0.80' && $ospiteMio['saldo'] === '13.40', 'ospite a carico del dipendente');
$ordini->invia($rossiUser, $rossiId, '2026-11-13', [], $adesso, true, 'Mario Ospite', 'dipendente');
$ordini->invia($rossiUser, $rossiId, '2026-11-13', [], $adesso);
verifica($ordini->saldo(AziendaConfig::trova($pdo, $rossiId), $rossiUser) === '15.00', 'annullo ospite e vassoio');
$doppioPrimo = $ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsRossi[0], $idsRossi[0]], $adesso);
verifica($doppioPrimo['ordine']['totale'] === '7.00' && $doppioPrimo['saldo'] === '8.00', 'doppio dello stesso primo');
$ordini->invia($rossiUser, $rossiId, '2026-11-13', [], $adesso);
verifica($ordini->saldo(AziendaConfig::trova($pdo, $rossiId), $rossiUser) === '15.00', 'annullo del doppio');
attesaEccezione('DOPPIO_PIATTO', fn () => $ordini->invia($rossiUser, $rossiId, '2026-11-13', [$idsRossi[0], $idsRossi[0], $idsRossi[0]], $adesso), 'tre volte lo stesso piatto');
verifica($prenotazioni->delGiorno($rossiId, '2026-11-13') === [], 'giorno di nuovo vuoto');

$regoleServizio = new RegoleAzienda($pdo);
$regoleNuove = $regoleServizio->salva($auroraId, [
    'max_scelte' => 3,
    'permetti_doppio_primo' => false,
    'permetti_doppio_contorno' => true,
    'limite_oggi' => '08:15',
    'limite_domani' => '17:45',
]);
verifica($regoleNuove->maxScelte === 3 && $regoleNuove->permettiDoppioContorno && $regoleNuove->limiteOggi === '08:15:00' && $regoleNuove->modalita === 'intranet', 'regole salvate sull\'azienda');
$modalitaInternet = $regoleServizio->salva($auroraId, [
    'max_scelte' => 3,
    'permetti_doppio_primo' => false,
    'permetti_doppio_contorno' => true,
    'limite_oggi' => '08:15',
    'limite_domani' => '17:45',
    'modalita' => 'internet',
]);
verifica($modalitaInternet->modalita === 'internet' && AziendaConfig::trova($pdo, $rossiId)->modalita === 'intranet', 'la modalità resta nel tenant');
$rossiIntatto = AziendaConfig::trova($pdo, $rossiId);
verifica($rossiIntatto->maxScelte === 5 && $rossiIntatto->limiteOggi === '09:30:00', 'le regole restano nel tenant');
attesaEccezione('REGOLA_NON_VALIDA', fn () => $regoleServizio->salva($auroraId, [
    'max_scelte' => 0,
    'limite_oggi' => '09:30',
    'limite_domani' => '18:00',
]), 'scelte fuori scala');
$conBevanda = $regoleServizio->salva($auroraId, [
    'max_scelte' => 5,
    'permetti_doppio_primo' => false,
    'permetti_doppio_contorno' => false,
    'limite_oggi' => '09:30',
    'limite_domani' => '18:00',
    'modalita' => 'intranet',
    'tipo_pagamento' => 'dipendente',
    'bevanda_obbligatoria' => true,
]);
verifica($conBevanda->bevandaObbligatoria && $conBevanda->tipoPagamento === PagamentoType::Dipendente, 'pagamento e acqua salvati');
verifica(AziendaConfig::trova($pdo, $rossiId)->bevandaObbligatoria === false, 'l\'acqua obbligatoria resta nel tenant');

$anagrafica = new Anagrafica($pdo);
$pagante = $anagrafica->creaUtente($auroraId, [
    'nome' => 'Ada Pagante',
    'matricola' => 'ADA',
    'password' => 'MenuCraft!2026',
    'pagamento' => 'pagante',
    'saldo' => '20,00',
]);
$esitoPagante = $ordini->invia(
    (int) $pagante['id'],
    $auroraId,
    '2026-11-13',
    [$idsAurora[0], $idsAurora[2], $idsAurora[4], $idsAurora[6], $idsAurora[7]],
    $adesso,
);
verifica($esitoPagante['ordine']['totale'] === '14.30' && $esitoPagante['saldo'] === '5.70', 'in welfare un pagante usa il listino');
verifica(count(array_filter(
    $anagrafica->elencoUtenti($rossiId),
    static fn (array $utente): bool => $utente['matricola'] === 'ADA',
)) === 0, 'l\'utente resta nella sua azienda');
attesaEccezione('MATRICOLA_ESISTENTE', fn () => $anagrafica->creaUtente($auroraId, [
    'nome' => 'Ada Doppia',
    'matricola' => 'ADA',
    'password' => 'MenuCraft!2026',
    'pagamento' => 'eredita',
]), 'matricola duplicata');

$creata = $anagrafica->creaSocieta([
    'nome' => 'Nuova Cucina QA',
    'slug' => 'qa-nuova-cucina',
    'tipo_pagamento' => 'welfare',
    'modalita' => 'intranet',
    'caterer_nome' => 'Cuoco QA',
    'caterer_matricola' => 'CAT-QA',
    'caterer_password' => 'MenuCraft!2026',
]);
verifica($creata['slug'] === 'qa-nuova-cucina' && $creata['caterer_matricola'] === 'CAT-QA', 'nuova cucina con catering');
verifica(AziendaConfig::trova($pdo, (int) $creata['id'])->bevandaObbligatoria === true, 'la cucina nuova ha l\'acqua obbligatoria');
attesaEccezione('SLUG_ESISTENTE', fn () => $anagrafica->creaSocieta([
    'nome' => 'Nuova Cucina QA',
    'slug' => 'qa-nuova-cucina',
    'tipo_pagamento' => 'welfare',
    'modalita' => 'intranet',
    'caterer_nome' => 'Cuoco QA',
    'caterer_matricola' => 'CAT-QA',
    'caterer_password' => 'MenuCraft!2026',
]), 'slug duplicato');
$anagrafica->segnalaAllergie($auroraId, $auroraUser, 'Lattosio');
$pdo->prepare('INSERT INTO portafogli (azienda_id, utente_id, saldo_centesimi) VALUES (?, ?, 2000) ON DUPLICATE KEY UPDATE saldo_centesimi = 2000')
    ->execute([$auroraId, $auroraUser]);
$ordini->invia($auroraUser, $auroraId, '2026-11-13', [$idsAurora[0], $idsAurora[2], $idsAurora[4], $idsAurora[6], $idsAurora[7]], $adesso);
$segnalate = array_values(array_filter(
    (new PrenotazioniAzienda($pdo))->delGiorno($auroraId, '2026-11-13'),
    static fn (array $riga): bool => $riga['matricola'] === 'DIP' && $riga['nota_cucina'] === 'Lattosio' && $riga['ospite'] === false,
));
verifica(count($segnalate) === 1, 'la cucina vede l\'allergia sulla prenotazione');
$ruoloPiattaforma = (string) $pdo->query("SELECT u.ruolo FROM utenti u INNER JOIN aziende a ON a.id = u.azienda_id WHERE a.slug = 'menucraft' AND u.matricola = 'ADMIN'")->fetchColumn();
verifica($ruoloPiattaforma === 'piattaforma', 'operatore del servizio');

$stretta = new AziendaConfig(11, 'Stretta', 'stretta', PagamentoType::Welfare, 5, false, false, '09:30:00', '18:00:00', 'intranet', true, false, 0, 0, 0, false, false, 0, 0, null, 3);
attesaEccezione('LIMITE_FRUTTA', fn () => $validator->validaComposizione($stretta, [PortataType::Primo, PortataType::Frutta, PortataType::Frutta, PortataType::Bevanda]), 'una sola frutta');
attesaEccezione('LIMITE_DOLCE', fn () => $validator->validaComposizione($stretta, [PortataType::Primo, PortataType::Dessert, PortataType::Dessert, PortataType::Bevanda]), 'un solo dolce');
$aperta = new AziendaConfig(12, 'Aperta', 'aperta', PagamentoType::Welfare, 5, false, false, '09:30:00', '18:00:00', 'intranet', true, false, 0, 0, 0, true, true, 2, 2, null, 5);
$validator->validaComposizione($aperta, [PortataType::Frutta, PortataType::Frutta, PortataType::Dessert, PortataType::Dessert, PortataType::Bevanda]);
$validator->validaQuantita($aperta, [
    ['id' => 1, 'nome' => 'Mela', 'categoria' => PortataType::Frutta],
    ['id' => 1, 'nome' => 'Mela', 'categoria' => PortataType::Frutta],
    ['id' => 2, 'nome' => 'Budino', 'categoria' => PortataType::Dessert],
    ['id' => 2, 'nome' => 'Budino', 'categoria' => PortataType::Dessert],
]);

$banco = new MenuBanco($pdo, $validator);
$menuGiorno = $banco->giorno($auroraId, '2026-11-13');
$lettere = array_column($menuGiorno['piatti'], 'lettera');
$codiciMenu = array_column($menuGiorno['piatti'], 'codice');
verifica(in_array('A', $lettere, true) && in_array('PR1', $codiciMenu, true) && !in_array('J', $lettere, true), 'lettera del pasto accanto al codice');
$conPera = $banco->crea($auroraId, $auroraCaterer, [
    'data' => '2026-11-13',
    'nome' => 'Pera',
    'categoria' => 'frutta',
    'prezzo' => '1,00',
    'codice' => 'FR1',
]);
verifica(count($conPera['piatti']) === count($menuGiorno['piatti']) + 1, 'piatto creato nel menu del giorno');
$lettereDopo = array_column($conPera['piatti'], 'lettera');
sort($lettereDopo);
$attese = array_slice(MenuCraft\Support\LetteraPasto::ALFABETO, 0, count($lettereDopo));
verifica($lettereDopo === $attese, 'le lettere restano consecutive dopo un aggiunta');
$primoId = null;
foreach ($conPera['piatti'] as $voce) {
    if ($voce['categoria'] === 'primo') {
        $primoId = (int) $voce['id'];
        break;
    }
}
verifica($primoId !== null, 'c\'è un primo da togliere');
$senzaPrimo = $banco->togli($auroraId, $auroraCaterer, '2026-11-13', $primoId);
verifica(!in_array($primoId, array_column($senzaPrimo['piatti'], 'id'), true), 'il primo è uscito dal menu del giorno');
$lettereTogli = array_column($senzaPrimo['piatti'], 'lettera');
sort($lettereTogli);
verifica($lettereTogli === array_slice(MenuCraft\Support\LetteraPasto::ALFABETO, 0, count($lettereTogli)), 'dopo aver tolto un primo le lettere ripartono da A');
verifica(!in_array('Q', $lettereTogli, true), 'non compare una Q fuori sequenza');
$pdo->prepare('UPDATE aziende SET menu_banco = 0 WHERE id = :id')->execute(['id' => $auroraId]);
attesaEccezione('MENU_BANCO_CHIUSO', fn () => $banco->crea($auroraId, $auroraCaterer, [
    'data' => '2026-11-13',
    'nome' => 'Kiwi',
    'categoria' => 'frutta',
    'prezzo' => '1,20',
]), 'senza menu banco non si aggiungono piatti a mano');
$pdo->prepare('UPDATE aziende SET menu_banco = 1 WHERE id = :id')->execute(['id' => $auroraId]);

$giornoVuoto = '2026-11-27';
$suGiornoNuovo = $banco->crea($auroraId, $auroraCaterer, [
    'data' => $giornoVuoto,
    'nome' => 'Lasagne della casa',
    'categoria' => 'primo',
    'prezzo' => '4,50',
    'codice' => 'PR-LAS',
]);
verifica(count($suGiornoNuovo['piatti']) === 1 && $suGiornoNuovo['piatti'][0]['lettera'] === 'A', 'su un giorno vuoto si crea la prima voce del menu');

$elencoDopo = $anagrafica->elencoSocieta();
$slugNuova = array_column($elencoDopo, 'slug');
verifica(in_array('qa-nuova-cucina', $slugNuova, true), 'la cucina appena creata resta nell\'elenco');
$guidaSalvata = (new RegoleAzienda($pdo))->salva($auroraId, [
    'max_scelte' => 5,
    'min_scelte' => 5,
    'limite_oggi' => '09:30',
    'limite_domani' => '18:00',
    'modalita' => 'intranet',
    'bevanda_obbligatoria' => true,
    'guida_linea' => 'Dopo le 9:30 chiama o scrivi su WhatsApp.',
    'contatto_linea' => '+39 333 0000000',
]);
verifica($guidaSalvata->guidaLinea === 'Dopo le 9:30 chiama o scrivi su WhatsApp.' && $guidaSalvata->contattoLinea === '+39 333 0000000', 'messaggio e contatto salvati nelle regole');

$anagrafica->aggiornaUtente($auroraId, $auroraCaterer, [
    'utente_id' => (int) $pagante['id'],
    'nome' => 'Ada Aggiornata',
    'pagamento' => 'pagante',
    'allergie' => 'Noci',
]);
$aggiornata = array_values(array_filter(
    $anagrafica->elencoUtenti($auroraId),
    static fn (array $utente): bool => $utente['matricola'] === 'ADA',
));
verifica($aggiornata[0]['nome'] === 'Ada Aggiornata' && $aggiornata[0]['allergie'] === 'Noci', 'utente modificato');
$anagrafica->eliminaUtente($auroraId, $auroraCaterer, (int) $pagante['id']);
verifica(array_filter(
    $anagrafica->elencoUtenti($auroraId),
    static fn (array $utente): bool => $utente['matricola'] === 'ADA',
) === [], 'utente eliminato');
attesaEccezione('RUOLO', fn () => $anagrafica->eliminaUtente($auroraId, $auroraCaterer, $auroraCaterer), 'il catering non elimina se stesso');

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$logoFile = tempnam(sys_get_temp_dir(), 'logo');
file_put_contents($logoFile, $png);
$percorsoLogo = (new LogoAzienda($pdo))->salva($auroraId, $logoFile);
verifica(is_file(dirname(__DIR__) . '/' . $percorsoLogo), 'logo azienda conservato');
@unlink(dirname(__DIR__) . '/' . $percorsoLogo);
@unlink($logoFile);

$pulizia->execute(['slug' => 'qa-%']);
$pdo->prepare('DELETE FROM login_tentativi WHERE azienda_slug LIKE :slug')->execute(['slug' => 'qa-%']);

function scriviXlsx(string $path, array $righe): void
{
    $strings = [];
    $index = [];
    $sheet = '';
    foreach ($righe as $r => $celle) {
        $n = $r + 1;
        $xml = '';
        foreach ($celle as $c => $valore) {
            if ($valore === '') {
                continue;
            }
            $col = '';
            $nCol = $c;
            do {
                $col = chr(65 + ($nCol % 26)) . $col;
                $nCol = intdiv($nCol, 26) - 1;
            } while ($nCol >= 0);
            if (!isset($index[$valore])) {
                $index[$valore] = count($strings);
                $strings[] = $valore;
            }
            $xml .= '<c r="' . $col . $n . '" t="s"><v>' . $index[$valore] . '</v></c>';
        }
        if ($xml !== '') {
            $sheet .= '<row r="' . $n . '">' . $xml . '</row>';
        }
    }
    $sst = '';
    foreach ($strings as $stringa) {
        $sst .= '<si><t>' . htmlspecialchars($stringa, ENT_XML1) . '</t></si>';
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('xlsx di test non creato');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $sst . '</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheet . '</sheetData></worksheet>');
    $zip->close();
}

if ($falliti > 0) {
    fwrite(STDERR, "{$falliti} controlli falliti\n");
    exit(1);
}

fwrite(STDOUT, "Tutti i controlli MenuCraft sono passati.\n");
