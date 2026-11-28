<?php

declare(strict_types=1);

namespace ItPortal\Auth;

/**
 * Modern rewrite of legacy validate_ldap().
 * Returns samAccountName, department, title on success.
 */
final class LdapAuthenticator
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $domain,
        private readonly string $baseDn,
        private readonly bool $useTls = false,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            host: (string) (getenv('LDAP_HOST') ?: 'ldap://10.101.10.12'),
            port: (int) (getenv('LDAP_PORT') ?: 389),
            domain: (string) (getenv('LDAP_DOMAIN') ?: '@joysonsafety.com'),
            baseDn: (string) (getenv('LDAP_BASE_DN') ?: 'OU=341,OU=10-Users,OU=EU,DC=corp,DC=joysonsafety,DC=com'),
            useTls: filter_var(getenv('LDAP_USE_TLS') ?: 'false', FILTER_VALIDATE_BOOL),
        );
    }

    /**
     * @return array{sam:string,department:string,title:string}|null
     */
    public function validate(string $username, string $password): ?array
    {
        if ($username === '' || $password === '') {
            return null;
        }

        if (!function_exists('ldap_connect')) {
            throw new \RuntimeException('PHP ldap extension is required for LDAP login.');
        }

        $connection = ldap_connect($this->host, $this->port);
        if ($connection === false) {
            throw new \RuntimeException('Could not connect to LDAP server.');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        if ($this->useTls) {
            @ldap_start_tls($connection);
        }

        $bindDn = $username . $this->domain;
        $bound = @ldap_bind($connection, $bindDn, $password);
        if (!$bound) {
            ldap_unbind($connection);
            return null;
        }

        $escaped = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
        $filter = '(&(objectCategory=person)(|(sAMAccountName=' . $escaped . ')(mail=' . $escaped . $this->domain . ')))';
        $search = @ldap_search($connection, $this->baseDn, $filter, ['samaccountname', 'department', 'title', 'sn', 'givenname', 'mail']);
        if ($search === false) {
            ldap_unbind($connection);
            return null;
        }

        $entries = ldap_get_entries($connection, $search);
        ldap_unbind($connection);

        if (($entries['count'] ?? 0) < 1) {
            return [
                'sam' => $username,
                'department' => 'IT',
                'title' => '',
            ];
        }

        $entry = $entries[0];
        return [
            'sam' => (string) ($entry['samaccountname'][0] ?? $username),
            'department' => (string) ($entry['department'][0] ?? ''),
            'title' => (string) ($entry['title'][0] ?? ''),
        ];
    }
}
