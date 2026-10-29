<?php

declare(strict_types=1);

namespace Lumina;

use PDO;

final class Oauth
{
    public static function googleReady(): bool
    {
        return self::env('LUMINA_GOOGLE_CLIENT_ID') !== '' && self::env('LUMINA_GOOGLE_CLIENT_SECRET') !== '';
    }

    public static function appleReady(): bool
    {
        return self::env('LUMINA_APPLE_CLIENT_ID') !== ''
            && self::env('LUMINA_APPLE_TEAM_ID') !== ''
            && self::env('LUMINA_APPLE_KEY_ID') !== ''
            && self::appleKey() !== '';
    }

    public static function begin(PDO $pdo, Clock $clock, string $provider, string $intent): string
    {
        if ($provider === 'google' && !self::googleReady()) {
            throw new DomainException('validation', 'Google non è collegato. Servono LUMINA_GOOGLE_CLIENT_ID, LUMINA_GOOGLE_CLIENT_SECRET e l\'URI ' . Secret::publicUrl() . '/auth/google/ritorno.');
        }
        if ($provider === 'apple' && !self::appleReady()) {
            throw new DomainException('validation', 'Apple non è collegato. Servono LUMINA_APPLE_CLIENT_ID, LUMINA_APPLE_TEAM_ID, LUMINA_APPLE_KEY_ID, LUMINA_APPLE_PRIVATE_KEY e l\'URI ' . Secret::publicUrl() . '/auth/apple/ritorno.');
        }
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $nonce = bin2hex(random_bytes(32));
        $token = bin2hex(random_bytes(16));
        Db::run($pdo, 'DELETE FROM oauth_states WHERE created_at < :cutoff', [
            'cutoff' => $clock->sql($clock->now()->modify('-1 day')),
        ]);
        Db::run($pdo, 'INSERT INTO oauth_states (nonce, provider, verifier, nonce_token, intent, created_at) VALUES (:nonce, :provider, :verifier, :token, :intent, :created)', [
            'nonce' => $nonce,
            'provider' => $provider,
            'verifier' => $verifier,
            'token' => $token,
            'intent' => $intent,
            'created' => $clock->sql($clock->now()),
        ]);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if ($provider === 'google') {
            $query = http_build_query([
                'client_id' => self::env('LUMINA_GOOGLE_CLIENT_ID'),
                'redirect_uri' => Secret::publicUrl() . '/auth/google/ritorno',
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $nonce,
                'nonce' => $token,
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
                'prompt' => 'select_account',
            ]);

            return 'https://accounts.google.com/o/oauth2/v2/auth?' . $query;
        }
        $query = http_build_query([
            'client_id' => self::env('LUMINA_APPLE_CLIENT_ID'),
            'redirect_uri' => Secret::publicUrl() . '/auth/apple/ritorno',
            'response_type' => 'code',
            'response_mode' => 'form_post',
            'scope' => 'name email',
            'state' => $nonce,
            'nonce' => $token,
        ]);

        return 'https://appleid.apple.com/auth/authorize?' . $query;
    }

    /** @param array<string, mixed> $request @return array{kind: string, user?: array<string, mixed>} */
    public static function finish(PDO $pdo, Clock $clock, string $provider, array $request): array
    {
        $state = (string) ($request['state'] ?? '');
        $code = (string) ($request['code'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $state) !== 1 || $code === '' || strlen($code) > 2048) {
            throw new DomainException('validation', 'Ritorno dal provider incompleto. Riprova.');
        }
        $row = Db::one($pdo, 'SELECT * FROM oauth_states WHERE nonce = :nonce AND provider = :provider AND used_at IS NULL', [
            'nonce' => $state,
            'provider' => $provider,
        ]);
        if ($row === null || $clock->parseUtc((string) $row['created_at']) < $clock->now()->modify('-15 minutes')) {
            throw new DomainException('validation', 'La richiesta di accesso è scaduta. Riparti da capo.');
        }
        Db::run($pdo, 'UPDATE oauth_states SET used_at = :now WHERE id = :id', [
            'now' => $clock->sql($clock->now()),
            'id' => (int) $row['id'],
        ]);
        if ($provider === 'google') {
            $token = self::post('https://oauth2.googleapis.com/token', [
                'client_id' => self::env('LUMINA_GOOGLE_CLIENT_ID'),
                'client_secret' => self::env('LUMINA_GOOGLE_CLIENT_SECRET'),
                'code' => $code,
                'code_verifier' => (string) $row['verifier'],
                'grant_type' => 'authorization_code',
                'redirect_uri' => Secret::publicUrl() . '/auth/google/ritorno',
            ]);
            $payload = Jwt::verify((string) ($token['id_token'] ?? ''), self::jwks('https://www.googleapis.com/oauth2/v3/certs', 'google'), [
                'iss' => ['https://accounts.google.com', 'accounts.google.com'],
                'aud' => self::env('LUMINA_GOOGLE_CLIENT_ID'),
                'nonce' => (string) $row['nonce_token'],
            ]);
            $verified = $payload['email_verified'] ?? false;
            if ($verified !== true && $verified !== 'true') {
                throw new DomainException('validation', 'Google non ha confermato questa email.');
            }
            $name = trim((string) ($payload['name'] ?? ''));
        } else {
            $token = self::post('https://appleid.apple.com/auth/token', [
                'client_id' => self::env('LUMINA_APPLE_CLIENT_ID'),
                'client_secret' => self::appleSecret(),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => Secret::publicUrl() . '/auth/apple/ritorno',
            ]);
            $payload = Jwt::verify((string) ($token['id_token'] ?? ''), self::jwks('https://appleid.apple.com/auth/keys', 'apple'), [
                'iss' => 'https://appleid.apple.com',
                'aud' => self::env('LUMINA_APPLE_CLIENT_ID'),
                'nonce' => (string) $row['nonce_token'],
            ]);
            $name = self::appleName($request);
        }
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')), 'UTF-8');
        $subject = (string) ($payload['sub'] ?? '');
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $subject === '' || strlen($subject) > 255) {
            throw new DomainException('validation', 'Il provider non ha consegnato un\'email utilizzabile.');
        }

        return self::account($pdo, $clock, $provider, $subject, $email, $name);
    }

    /** @return array{kind: string, user?: array<string, mixed>} */
    private static function account(PDO $pdo, Clock $clock, string $provider, string $subject, string $email, string $name): array
    {
        $linked = Db::one($pdo, 'SELECT user_id FROM oauth_accounts WHERE provider = :provider AND subject = :subject', [
            'provider' => $provider,
            'subject' => $subject,
        ]);
        if ($linked !== null) {
            $user = Db::one($pdo, 'SELECT id, name, email, phone, role FROM users WHERE id = :id', ['id' => (int) $linked['user_id']]);
            if ($user === null) {
                throw new DomainException('validation', 'Account collegato non trovato.');
            }
            Auth::loginId((int) $user['id']);

            return ['kind' => 'login', 'user' => $user];
        }
        $existing = Db::one($pdo, 'SELECT * FROM users WHERE email = :email', ['email' => $email]);
        if ($existing !== null && $existing['email_verified_at'] === null) {
            throw new DomainException('validation', 'Questa email è già in Lumina ma non è confermata. Entra con il codice, poi potrai collegare ' . ($provider === 'google' ? 'Google' : 'Apple') . '.');
        }
        if ($existing !== null) {
            self::link($pdo, $clock, (int) $existing['id'], $provider, $subject);
            unset($existing['password_hash']);
            Auth::loginId((int) $existing['id']);

            return ['kind' => 'login', 'user' => $existing];
        }
        $_SESSION['pending_oauth'] = [
            'provider' => $provider,
            'subject' => $subject,
            'email' => $email,
            'name' => $name,
        ];
        $_SESSION['gate_email'] = $email;
        $_SESSION['gate_ok'] = true;

        return ['kind' => 'profile'];
    }

    public static function link(PDO $pdo, Clock $clock, int $userId, string $provider, string $subject): void
    {
        Db::run($pdo, 'INSERT INTO oauth_accounts (user_id, provider, subject, created_at) VALUES (:user, :provider, :subject, :created)', [
            'user' => $userId,
            'provider' => $provider,
            'subject' => $subject,
            'created' => $clock->sql($clock->now()),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function jwks(string $url, string $name): array
    {
        $path = LUMINA_ROOT . '/storage/jwks-' . $name . '.json';
        $fresh = is_file($path) && (time() - (int) filemtime($path)) < 3600;
        $raw = $fresh ? (string) file_get_contents($path) : self::get($url);
        if (!$fresh) {
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            file_put_contents($path, $raw);
        }
        $decoded = json_decode($raw, true);
        $keys = is_array($decoded) ? ($decoded['keys'] ?? null) : null;
        if (!is_array($keys)) {
            throw new DomainException('validation', 'Elenco chiavi del provider illeggibile.');
        }

        return $keys;
    }

    /** @param array<string, string> $fields @return array<string, mixed> */
    private static function post(string $url, array $fields): array
    {
        $raw = self::request($url, http_build_query($fields));
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || isset($decoded['error'])) {
            throw new DomainException('validation', 'Il provider ha rifiutato lo scambio del codice.');
        }

        return $decoded;
    }

    private static function get(string $url): string
    {
        return self::request($url, null);
    }

    private static function request(string $url, ?string $body): string
    {
        $headers = "Accept: application/json\r\n";
        $options = [
            'method' => $body === null ? 'GET' : 'POST',
            'header' => $body === null ? $headers : "Content-Type: application/x-www-form-urlencoded\r\n" . $headers,
            'timeout' => 8,
            'ignore_errors' => true,
        ];
        if ($body !== null) {
            $options['content'] = $body;
        }
        $context = stream_context_create(['http' => $options]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            throw new DomainException('validation', 'Il provider non risponde. Riprova tra un momento.');
        }

        return $raw;
    }

    /** @param array<string, mixed> $request */
    private static function appleName(array $request): string
    {
        $user = json_decode((string) ($request['user'] ?? ''), true);
        if (!is_array($user)) {
            return '';
        }
        $name = $user['name'] ?? null;
        if (!is_array($name)) {
            return '';
        }

        return trim((string) ($name['firstName'] ?? '') . ' ' . (string) ($name['lastName'] ?? ''));
    }

    private static function appleSecret(): string
    {
        $now = time();
        $payload = (string) json_encode([
            'iss' => self::env('LUMINA_APPLE_TEAM_ID'),
            'iat' => $now,
            'exp' => $now + 3600,
            'aud' => 'https://appleid.apple.com',
            'sub' => self::env('LUMINA_APPLE_CLIENT_ID'),
        ], JSON_THROW_ON_ERROR);

        return Jwt::signEs256($payload, self::appleKey(), self::env('LUMINA_APPLE_KEY_ID'));
    }

    private static function appleKey(): string
    {
        $inline = self::env('LUMINA_APPLE_PRIVATE_KEY');
        if (str_contains($inline, 'BEGIN PRIVATE KEY')) {
            return str_replace('\n', "\n", $inline);
        }
        if ($inline !== '' && is_file($inline)) {
            return (string) file_get_contents($inline);
        }

        return '';
    }

    private static function env(string $name): string
    {
        $value = getenv($name);

        return is_string($value) ? trim($value) : '';
    }
}
