<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\RegoleAzienda;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuolo('caterer');
    $auth->richiediCsrf();
    $azienda = (new RegoleAzienda($pdo))->salva($utente['azienda_id'], JsonResponse::readJson());

    JsonResponse::send(200, [
        'ok' => true,
        'messaggio' => 'Regole aggiornate per ' . $azienda->nome . '.',
        'azienda' => $azienda->toArray(),
        'regole' => [
            'max_scelte' => $azienda->maxScelte,
            'max_primo' => $azienda->permettiDoppioPrimo ? 2 : 1,
            'max_secondo' => 1,
            'max_contorno' => $azienda->permettiDoppioContorno ? 2 : 1,
        ],
    ]);
});
