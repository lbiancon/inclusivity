<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\BookingBoard;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\MenuValidator;
use MenuCraft\Services\OrderService;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $utente = (new AuthService($pdo))->richiediUtente();
    $validator = new MenuValidator($pdo);
    $board = new BookingBoard($pdo, $validator, new OrderService($pdo, $validator));
    $data = isset($_GET['data']) ? (string) $_GET['data'] : null;
    $quadro = $board->carica($utente['azienda_id'], $utente['id'], $data);

    JsonResponse::send(200, ['ok' => true, 'csrf' => $utente['csrf']] + $quadro);
});
