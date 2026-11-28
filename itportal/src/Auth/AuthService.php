<?php

declare(strict_types=1);

namespace ItPortal\Auth;

use ItPortal\Config;

/**
 * Replaces login.php + LOGIN_PORTAL + LOGIN_PORTAL_LDAP + MySwipe flow.
 */
final class AuthService
{
    private EmployeeRepositoryInterface $employees;
    private ?LdapAuthenticator $ldap;

    public function __construct(private readonly Session $session)
    {
        $driver = (string) Config::get('app.auth_driver', 'mock');
        $this->employees = $driver === 'oracle'
            ? new OracleEmployeeRepository()
            : new MockEmployeeRepository();

        $this->ldap = $driver === 'oracle' ? LdapAuthenticator::fromEnv() : null;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function check(): bool
    {
        return $this->session->isLoggedIn();
    }

    public function requireLogin(string $redirect = '/login.php'): void
    {
        if (!$this->check()) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    /**
     * Badge / magnetic card login (legacy pass field = user_id).
     */
    public function loginByBadge(string $badge): AuthResult
    {
        $badge = trim($badge);
        if ($badge === '') {
            return AuthResult::fail('Enter your badge / password.', 'warning');
        }

        $user = $this->employees->findByBadge($badge);
        if ($user === null) {
            return AuthResult::fail('Wrong password or user not registered.');
        }

        if (!$this->isAllowedQualCode($user)) {
            return AuthResult::fail('Access denied.');
        }

        $this->establishSession($user);
        return AuthResult::success('/index.php');
    }

    /**
     * LDAP username + password, then map to portal role (legacy LOGIN_PORTAL_LDAP).
     */
    public function loginByLdap(string $username, string $password): AuthResult
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return AuthResult::fail('Username and password required.', 'warning');
        }

        // Mock LDAP for local demos: username "ldap.user" / any password maps to SU if badge exists
        if ($this->ldap === null) {
            if (strtolower($username) === 'ldap.user') {
                $user = $this->employees->findByBadge('demo') ?? $this->employees->findByBadge('12345');
                if ($user === null) {
                    return AuthResult::fail('Mock LDAP user missing.');
                }
                $mapped = new UserRecord(
                    login: $username,
                    emplId: $user->emplId,
                    nome: $user->nome,
                    cognome: $user->cognome,
                    superuser: 'Y',
                    empQuaCode: 'IT',
                    roles: 'SU',
                );
                $this->establishSession($mapped);
                return AuthResult::success('/index.php', 'LDAP (mock) signed in');
            }
            return AuthResult::fail('LDAP not configured. Use badge login or set AUTH_DRIVER=oracle.');
        }

        try {
            $ldapUser = $this->ldap->validate($username, $password);
        } catch (\Throwable $e) {
            return AuthResult::fail('LDAP error: ' . $e->getMessage());
        }

        if ($ldapUser === null) {
            return AuthResult::fail('LDAP authentication failed.');
        }

        $roles = 'SU';
        if (str_contains(strtoupper($ldapUser['title'] . ' ' . $ldapUser['department']), 'IT')) {
            $roles = 'SU';
        }

        $record = new UserRecord(
            login: $ldapUser['sam'],
            emplId: '',
            nome: $ldapUser['sam'],
            cognome: '',
            superuser: 'Y',
            empQuaCode: $ldapUser['department'] !== '' ? $ldapUser['department'] : 'IT',
            roles: $roles,
        );

        $this->establishSession($record);
        return AuthResult::success('/index.php', 'LDAP signed in');
    }

    /**
     * Entry point matching legacy login.php / tables.php / MySwipe.
     */
    public function attemptFromRequest(array $post): AuthResult
    {
        $user = isset($post['user']) ? (string) $post['user'] : '';
        $pass = isset($post['pass']) ? (string) $post['pass'] : '';

        if ($user !== '') {
            return $this->loginByLdap($user, $pass);
        }

        return $this->loginByBadge($pass);
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    private function establishSession(UserRecord $user): void
    {
        $this->session->regenerate();
        $this->session->putMany($user->toSession());
    }

    /**
     * Mirrors legacy switch on EMPL_QUAL_CODE / ROLES.
     */
    private function isAllowedQualCode(UserRecord $user): bool
    {
        if ($user->roles === 'SU') {
            return true;
        }

        $allowed = ['Q', 'M', 'H', 'P', 'G', 'A', 'T', 'E'];
        return in_array($user->empQuaCode, $allowed, true);
    }
}
