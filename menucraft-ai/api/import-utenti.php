<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\ImportUtenti;
use MenuCraft\Services\MenuRuleException;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuoli(['caterer', 'piattaforma']);
    $auth->richiediCsrf();

    $aziendaFallback = null;
    if ($utente['ruolo'] === 'caterer') {
        $aziendaFallback = (int) $utente['azienda_id'];
    } else {
        $id = (int) ($_POST['azienda_id'] ?? 0);
        if ($id > 0) {
            $aziendaFallback = $auth->aziendaOperativa($utente, ['azienda_id' => $id]);
        }
    }

    $file = $_FILES['utenti'] ?? null;
    if (!is_array($file)) {
        throw new MenuRuleException('FILE_MANCANTE', 'Seleziona il file utenti (.xlsx o .csv).', 400);
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

    $esito = (new ImportUtenti($pdo))->importa(
        $aziendaFallback,
        $temporaneo,
        (string) ($file['name'] ?? 'utenti.csv'),
        [
            'ruolo' => (string) $utente['ruolo'],
            'azienda_id' => (int) $utente['azienda_id'],
            'id' => (int) $utente['id'],
        ],
    );

    $parti = [];
    if ($esito['creati'] > 0) {
        $parti[] = $esito['creati'] . ' creati';
    }
    if ($esito['aggiornati'] > 0) {
        $parti[] = $esito['aggiornati'] . ' aggiornati';
    }
    if ($esito['saltati'] > 0) {
        $parti[] = $esito['saltati'] . ' con errore';
    }
    if ($esito['per_azienda'] !== []) {
        $dettaglio = [];
        foreach ($esito['per_azienda'] as $slug => $conteggi) {
            $dettaglio[] = $slug . ' (' . $conteggi['creati'] . '+' . $conteggi['aggiornati'] . ')';
        }
        $parti[] = 'aziende: ' . implode(', ', $dettaglio);
    }

    JsonResponse::send(200, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'messaggio' => 'Import utenti: ' . implode(', ', $parti) . '.',
        'import' => $esito,
        'utenti' => $esito['utenti'],
    ]);
});
