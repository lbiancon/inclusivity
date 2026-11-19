<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuPublisher;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\MenuValidator;
use MenuCraft\Services\SpreadsheetMenuParser;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuolo('caterer');
    $auth->richiediCsrf();

    $file = $_FILES['menu'] ?? null;
    if (!is_array($file)) {
        throw new MenuRuleException('FILE_MANCANTE', 'Seleziona il template Excel del giovedì oppure un CSV.', 400);
    }

    $errore = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errore === UPLOAD_ERR_INI_SIZE || $errore === UPLOAD_ERR_FORM_SIZE) {
        throw new MenuRuleException('FILE_TROPPO_GRANDE', 'Il file supera la dimensione consentita di 2 MB.', 413);
    }
    if ($errore !== UPLOAD_ERR_OK) {
        throw new MenuRuleException('FILE_MANCANTE', 'Caricamento del file non riuscito.', 400);
    }
    if ((int) ($file['size'] ?? 0) > 2_000_000) {
        throw new MenuRuleException('FILE_TROPPO_GRANDE', 'Il file supera la dimensione consentita di 2 MB.', 413);
    }

    $temporaneo = (string) ($file['tmp_name'] ?? '');
    if ($temporaneo === '' || !is_uploaded_file($temporaneo)) {
        throw new MenuRuleException('FILE_MANCANTE', 'Caricamento del file non riuscito.', 400);
    }

    $publisher = new MenuPublisher($pdo, new MenuValidator($pdo), new SpreadsheetMenuParser());
    $esito = $publisher->pubblica(
        $utente['azienda_id'],
        $utente['id'],
        $temporaneo,
        (string) ($file['name'] ?? ''),
        new DateTimeImmutable('now', new DateTimeZone(MenuValidator::FUSO)),
    );

    JsonResponse::send(200, ['ok' => true, 'pubblicazione' => $esito]);
});
