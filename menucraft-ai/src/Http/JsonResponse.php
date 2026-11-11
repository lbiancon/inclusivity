<?php

declare(strict_types=1);

namespace MenuCraft\Http;

use MenuCraft\Services\MenuRuleException;
use PDOException;
use Throwable;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class JsonResponse
{
    /** @param array<string, mixed> $payload */
    public static function send(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /** @return array<string, mixed> */
    public static function readJson(): array
    {
        $raw = file_get_contents('php://input', false, null, 0, 100_001);
        if ($raw === false) {
            throw new MenuRuleException('CORPO_NON_VALIDO', 'Richiesta non valida.', 400);
        }
        if (strlen($raw) > 100_000) {
            throw new MenuRuleException('CORPO_TROPPO_GRANDE', 'Richiesta troppo grande.', 413);
        }
        if (trim($raw) === '') {
            throw new MenuRuleException('CORPO_VUOTO', 'Richiesta vuota.', 400);
        }

        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new MenuRuleException('JSON_NON_VALIDO', 'Il corpo della richiesta non è JSON valido.', 400);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new MenuRuleException('JSON_NON_VALIDO', 'Il corpo della richiesta deve essere un oggetto JSON.', 400);
        }

        return $data;
    }

    public static function guard(callable $azione): never
    {
        try {
            $azione();
            self::send(500, [
                'ok' => false,
                'errore' => [
                    'codice' => 'RISPOSTA_ASSENTE',
                    'messaggio' => 'Operazione non riuscita. Riprova.',
                ],
            ]);
        } catch (MenuRuleException $e) {
            $errore = [
                'codice' => $e->codice(),
                'messaggio' => $e->getMessage(),
            ];
            if ($e->dettagli() !== []) {
                $errore['dettagli'] = $e->dettagli();
            }
            self::send($e->httpStatus(), ['ok' => false, 'errore' => $errore]);
        } catch (PDOException $e) {
            error_log('MenuCraft DB: ' . $e->getMessage());
            self::send(503, [
                'ok' => false,
                'errore' => [
                    'codice' => 'DATABASE',
                    'messaggio' => 'Servizio temporaneamente non disponibile.',
                ],
            ]);
        } catch (Throwable $e) {
            error_log('MenuCraft: ' . $e::class . ' ' . $e->getMessage());
            self::send(500, [
                'ok' => false,
                'errore' => [
                    'codice' => 'ERRORE_INTERNO',
                    'messaggio' => 'Operazione non riuscita. Riprova.',
                ],
            ]);
        }
    }
}
