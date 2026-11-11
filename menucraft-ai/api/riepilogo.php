<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Models\AziendaConfig;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\KitchenSummary;
use MenuCraft\Services\MenuRuleException;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    JsonResponse::guard(static function (): void {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    });
}

try {
    $pdo = menucraft_db();
    $utente = (new AuthService($pdo))->richiediRuolo('caterer');
    $azienda = AziendaConfig::trova($pdo, $utente['azienda_id']);
    $data = (string) ($_GET['data'] ?? '');
    $csv = (new KitchenSummary($pdo))->csv($azienda->id, $azienda->nome, $data);
    $nome = 'riepilogo-cucina-' . $azienda->slug . '-' . $data . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('X-Content-Type-Options: nosniff');
    echo $csv;
} catch (MenuRuleException $e) {
    JsonResponse::send($e->httpStatus(), [
        'ok' => false,
        'errore' => ['codice' => $e->codice(), 'messaggio' => $e->getMessage()],
    ]);
} catch (Throwable $e) {
    error_log('MenuCraft riepilogo: ' . $e->getMessage());
    JsonResponse::send(500, [
        'ok' => false,
        'errore' => ['codice' => 'ERRORE_INTERNO', 'messaggio' => 'Riepilogo non disponibile. Riprova.'],
    ]);
}
