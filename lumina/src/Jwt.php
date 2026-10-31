<?php

declare(strict_types=1);

namespace Lumina;

final class Jwt
{
    /** @param list<array<string, mixed>> $keys @param array<string, string|list<string>> $expect @return array<string, mixed> */
    public static function verify(string $jwt, array $keys, array $expect): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new DomainException('validation', 'Token di accesso non leggibile.');
        }
        $header = self::json($parts[0]);
        $payload = self::json($parts[1]);
        if (($header['alg'] ?? '') !== 'RS256') {
            throw new DomainException('validation', 'Algoritmo del token non accettato.');
        }
        $pem = null;
        foreach ($keys as $key) {
            if (($key['kid'] ?? '') === ($header['kid'] ?? '') && ($key['kty'] ?? '') === 'RSA') {
                $pem = self::rsaPem($key);
                break;
            }
        }
        if ($pem === null) {
            throw new DomainException('validation', 'Chiave del token non riconosciuta.');
        }
        $signature = self::b64($parts[2]);
        $ok = openssl_verify($parts[0] . '.' . $parts[1], $signature, $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new DomainException('validation', 'Firma del token non valida.');
        }
        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp < time() - 60) {
            throw new DomainException('validation', 'Token scaduto. Riprova l\'accesso.');
        }
        foreach ($expect as $claim => $accepted) {
            $value = (string) ($payload[$claim] ?? '');
            $options = is_array($accepted) ? $accepted : [$accepted];
            if (!in_array($value, $options, true)) {
                throw new DomainException('validation', 'Token non destinato a questa installazione.');
            }
        }

        return $payload;
    }

    public static function signEs256(string $payload, string $privateKeyPem, string $kid): string
    {
        $header = self::b64url((string) json_encode(['alg' => 'ES256', 'kid' => $kid], JSON_THROW_ON_ERROR));
        $body = self::b64url($payload);
        $input = $header . '.' . $body;
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new DomainException('validation', 'Chiave Apple illeggibile.');
        }
        $der = '';
        if (!openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new DomainException('validation', 'Firma Apple non riuscita.');
        }

        return $input . '.' . self::b64url(self::derToRaw($der));
    }

    /** @param array<string, mixed> $jwk */
    private static function rsaPem(array $jwk): string
    {
        $n = self::unsigned(self::b64((string) $jwk['n']));
        $e = self::unsigned(self::b64((string) $jwk['e']));
        $rsa = self::asn1(0x30, $n . $e);
        $bit = self::asn1(0x03, "\x00" . $rsa);
        $alg = self::asn1(0x30, (string) hex2bin('300d06092a864886f70d0101010500'));
        $spki = self::asn1(0x30, $alg . $bit);

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derToRaw(string $der): string
    {
        $pos = 0;
        if (ord($der[$pos++]) !== 0x30) {
            throw new DomainException('validation', 'Firma Apple inattesa.');
        }
        self::derLen($der, $pos);
        $read = static function (string $der, int &$pos): string {
            if (ord($der[$pos++]) !== 0x02) {
                throw new DomainException('validation', 'Firma Apple inattesa.');
            }
            $len = self::derLen($der, $pos);
            $value = substr($der, $pos, $len);
            $pos += $len;

            return ltrim($value, "\x00");
        };
        $r = str_pad($read($der, $pos), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($read($der, $pos), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    private static function derLen(string $der, int &$pos): int
    {
        $first = ord($der[$pos++]);
        if ($first < 128) {
            return $first;
        }
        $bytes = $first & 0x7f;
        $len = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $len = ($len << 8) | ord($der[$pos++]);
        }

        return $len;
    }

    private static function unsigned(string $bytes): string
    {
        if ($bytes !== '' && (ord($bytes[0]) & 0x80) === 128) {
            $bytes = "\x00" . $bytes;
        }

        return self::asn1(0x02, $bytes);
    }

    private static function asn1(int $tag, string $value): string
    {
        $len = strlen($value);
        if ($len < 128) {
            $prefix = chr($len);
        } elseif ($len < 256) {
            $prefix = chr(0x81) . chr($len);
        } else {
            $prefix = chr(0x82) . pack('n', $len);
        }

        return chr($tag) . $prefix . $value;
    }

    /** @return array<string, mixed> */
    private static function json(string $segment): array
    {
        $decoded = json_decode(self::b64($segment), true);
        if (!is_array($decoded)) {
            throw new DomainException('validation', 'Token di accesso non leggibile.');
        }

        return $decoded;
    }

    private static function b64(string $segment): string
    {
        $padded = strtr($segment, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($padded, true);
        if ($raw === false) {
            throw new DomainException('validation', 'Token di accesso non leggibile.');
        }

        return $raw;
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
