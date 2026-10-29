<?php

declare(strict_types=1);

namespace Lumina;

use PDO;

final class Passcode
{
    public static function issue(PDO $pdo, Clock $clock, string $email, string $ip): string
    {
        $now = $clock->now();
        $hourAgo = $clock->sql($now->modify('-1 hour'));
        $recent = (int) Db::value($pdo, 'SELECT COUNT(*) FROM login_codes WHERE email = :email AND created_at >= :since', [
            'email' => $email,
            'since' => $hourAgo,
        ]);
        $ipHash = Secret::hmac('ip|' . $ip);
        $byIp = (int) Db::value($pdo, 'SELECT COUNT(*) FROM login_codes WHERE ip_hash = :ip AND created_at >= :since', [
            'ip' => $ipHash,
            'since' => $hourAgo,
        ]);
        if ($recent >= 5 || $byIp >= 30) {
            throw new DomainException('policy', 'Troppi codici richiesti. Riprova tra un\'ora.');
        }
        $last = Db::value($pdo, 'SELECT MAX(created_at) FROM login_codes WHERE email = :email', ['email' => $email]);
        if (is_string($last) && $last !== '') {
            $elapsed = $now->getTimestamp() - $clock->parseUtc($last)->getTimestamp();
            if ($elapsed < 60) {
                throw new DomainException('policy', 'Un codice è appena partito. Aspetta un minuto prima di chiederne un altro.');
            }
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pdo->beginTransaction();
        try {
            Db::run($pdo, 'UPDATE login_codes SET used_at = :now WHERE email = :email AND used_at IS NULL', [
                'now' => $clock->sql($now),
                'email' => $email,
            ]);
            Db::run($pdo, 'INSERT INTO login_codes (email, code_hash, attempts, expires_at, created_at, ip_hash) VALUES (:email, :hash, 0, :expires, :created, :ip)', [
                'email' => $email,
                'hash' => Secret::hmac('code|' . $email . '|' . $code),
                'expires' => $clock->sql($now->modify('+10 minutes')),
                'created' => $clock->sql($now),
                'ip' => $ipHash,
            ]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        $body = "Lumina\n\nIl tuo codice\n\n" . substr($code, 0, 3) . ' ' . substr($code, 3) . "\n\nVale 10 minuti e si usa una volta sola.\nSe non hai chiesto tu l'accesso, ignora questa lettera.\n";
        $letterId = Mailer::send($pdo, $clock, $email, 'Il tuo codice Lumina', $body);
        if ($letterId !== null && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['gate_letter'] = $letterId;
        }

        return $code;
    }

    public static function check(PDO $pdo, Clock $clock, string $email, string $code): bool
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }
        $pdo->beginTransaction();
        try {
            $row = Db::one(
                $pdo,
                'SELECT * FROM login_codes WHERE email = :email AND used_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE',
                ['email' => $email],
            );
            if ($row === null) {
                $pdo->commit();

                return false;
            }
            $expired = $clock->parseUtc((string) $row['expires_at']) < $clock->now();
            $burned = (int) $row['attempts'] >= 5;
            if ($expired || $burned) {
                Db::run($pdo, 'UPDATE login_codes SET used_at = :now WHERE id = :id', [
                    'now' => $clock->sql($clock->now()),
                    'id' => (int) $row['id'],
                ]);
                $pdo->commit();

                return false;
            }
            Db::run($pdo, 'UPDATE login_codes SET attempts = attempts + 1 WHERE id = :id', ['id' => (int) $row['id']]);
            $known = Secret::hmac('code|' . $email . '|' . $code);
            if (!hash_equals((string) $row['code_hash'], $known)) {
                $pdo->commit();

                return false;
            }
            Db::run($pdo, 'UPDATE login_codes SET used_at = :now WHERE email = :email AND used_at IS NULL', [
                'now' => $clock->sql($clock->now()),
                'email' => $email,
            ]);
            $pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
