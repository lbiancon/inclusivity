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
    $auth->richiediRuoli(['caterer', 'piattaforma']);

    $csv = ImportUtenti::templateCsv();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="menucraft-utenti-template.csv"');
    header('Cache-Control: no-store');
    echo $csv;
    exit;
});
