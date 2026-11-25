<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\ImportUtenti;
use MenuCraft\Services\MenuRuleException;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuoli(['caterer', 'piattaforma']);

    $esporta = (($_GET['esporta'] ?? '1') === '1');
    $aziendaId = null;
    $nomeFile = 'menucraft-utenti-template.csv';

    if ($utente['ruolo'] === 'caterer') {
        $aziendaId = (int) $utente['azienda_id'];
    } else {
        $richiesta = (int) ($_GET['azienda_id'] ?? 0);
        if ($richiesta > 0) {
            $check = $pdo->prepare('SELECT id FROM aziende WHERE id = :id');
            $check->execute(['id' => $richiesta]);
            if ($check->fetchColumn() === false) {
                throw new MenuRuleException('AZIENDA_SCONOSCIUTA', 'Azienda non trovata.', 404);
            }
            $aziendaId = $richiesta;
        }
    }

    if ($aziendaId !== null) {
        $slug = $pdo->prepare('SELECT slug FROM aziende WHERE id = :id');
        $slug->execute(['id' => $aziendaId]);
        $codice = (string) $slug->fetchColumn();
        $nomeFile = $esporta
            ? 'menucraft-utenti-' . $codice . '.csv'
            : 'menucraft-utenti-template-' . $codice . '.csv';
    }

    $csv = (new ImportUtenti($pdo))->generaTemplateCsv($aziendaId, $esporta && $aziendaId !== null);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeFile . '"');
    header('Cache-Control: no-store');
    echo $csv;
    exit;
});
