<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $auth = new AuthService(menucraft_db());
    $auth->richiediUtente();
    $auth->richiediCsrf();
    $auth->logout();

    JsonResponse::send(200, ['ok' => true, 'messaggio' => 'Sessione chiusa.']);
});
