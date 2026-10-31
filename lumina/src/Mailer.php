<?php

declare(strict_types=1);

namespace Lumina;

use PDO;

final class Mailer
{
    public static function isLocal(): bool
    {
        return trim((string) getenv('LUMINA_SMTP_HOST')) === '';
    }

    public static function send(PDO $pdo, Clock $clock, string $to, string $subject, string $body): ?int
    {
        if (preg_match("/[\r\n]/", $to . $subject) === 1) {
            throw new DomainException('validation', 'Indirizzo non inviabile.');
        }
        $now = $clock->sql($clock->now());
        Db::run($pdo, 'DELETE FROM mail_letters WHERE created_at < :cutoff', [
            'cutoff' => $clock->sql($clock->now()->modify('-1 day')),
        ]);
        if (!self::isLocal()) {
            self::smtp($to, $subject, $body);

            return null;
        }
        Db::run($pdo, 'INSERT INTO mail_letters (email, subject, body, created_at) VALUES (:email, :subject, :body, :created)', [
            'email' => $to,
            'subject' => $subject,
            'body' => $body,
            'created' => $now,
        ]);

        return Db::id($pdo);
    }

    /** @return array<string, mixed>|null */
    public static function letter(PDO $pdo, int $id, string $email): ?array
    {
        return Db::one($pdo, 'SELECT * FROM mail_letters WHERE id = :id AND email = :email', ['id' => $id, 'email' => $email]);
    }

    private static function smtp(string $to, string $subject, string $body): void
    {
        $host = (string) getenv('LUMINA_SMTP_HOST');
        $port = (int) (getenv('LUMINA_SMTP_PORT') ?: 587);
        $user = (string) getenv('LUMINA_SMTP_USER');
        $pass = (string) getenv('LUMINA_SMTP_PASS');
        $from = (string) (getenv('LUMINA_MAIL_FROM') ?: $user);
        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('validation', 'Manca LUMINA_MAIL_FROM, un indirizzo valido per spedire.');
        }
        $socket = @fsockopen($host, $port, $errno, $error, 8);
        if ($socket === false) {
            throw new DomainException('validation', 'Il server di posta non risponde. Il codice non è partito.');
        }
        stream_set_timeout($socket, 8);
        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO lumina', [250]);
            if ($port === 587) {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new DomainException('validation', 'La posta non ha accettato la connessione cifrata.');
                }
                self::command($socket, 'EHLO lumina', [250]);
            }
            if ($user !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($user), [334]);
                self::command($socket, base64_encode($pass), [235]);
            }
            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);
            $message = 'From: Lumina <' . $from . ">\r\n";
            $message .= 'To: <' . $to . ">\r\n";
            $message .= 'Subject: ' . $subject . "\r\n";
            $message .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            $message .= "\r\n" . str_replace("\n.", "\n..", str_replace(["\r\n", "\r"], "\n", $body));
            $message = str_replace("\n", "\r\n", $message) . "\r\n.";
            self::command($socket, $message, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket @param list<int> $ok */
    private static function command($socket, string $command, array $ok): void
    {
        fwrite($socket, $command . "\r\n");
        self::expect($socket, $ok);
    }

    /** @param resource $socket @param list<int> $ok */
    private static function expect($socket, array $ok): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $ok, true)) {
            throw new DomainException('validation', 'La posta ha rifiutato l\'invio. Il codice non è partito.');
        }
    }
}
