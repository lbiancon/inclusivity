<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use DateTimeZone;
use MenuCraft\Models\AziendaConfig;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class AuthService
{
    public const COSTO_BCRYPT = 12;
    private const TENTATIVI_MASSIMI = 8;
    private const BLOCCO_SECONDI = 900;
    private const SESSIONE_SECONDI = 43200;
    private const TIMING_PAD = '$2y$12$bbCvQcV.Vkd44EPydkfF7OK6yC19cZABrcuLviFrKWjfe2oAUc4H6';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function login(string $slug, string $matricola, string $password): array
    {
        $slug = strtolower(trim($slug));
        $matricola = trim($matricola);

        if (
            preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
            || strlen($slug) > 60
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,31}$/', $matricola) !== 1
            || $password === ''
            || strlen($password) > 200
        ) {
            $this->pad($password);
            throw new MenuRuleException('CREDENZIALI', 'Credenziali non valide.', 401);
        }

        $this->pdo->beginTransaction();
        try {
            $blocco = $this->pdo->prepare(
                'SELECT id, tentativi, bloccato_fino
                 FROM login_tentativi
                 WHERE azienda_slug = ? AND matricola = ?
                 FOR UPDATE'
            );
            $blocco->execute([$slug, $matricola]);
            $riga = $blocco->fetch();
            $now = new DateTimeImmutable('now', new DateTimeZone(MenuValidator::FUSO));

            if (is_array($riga) && $riga['bloccato_fino'] !== null && (string) $riga['bloccato_fino'] > $now->format('Y-m-d H:i:s')) {
                $this->pdo->commit();
                $this->pad($password);
                throw new MenuRuleException('TROPPI_TENTATIVI', 'Troppi tentativi. Riprova tra 15 minuti.', 429);
            }

            $stmt = $this->pdo->prepare(
                'SELECT u.id, u.azienda_id, u.matricola, u.nome, u.password_hash, u.ruolo,
                        a.nome AS azienda_nome, a.slug, a.tipo_pagamento, a.max_scelte,
                        a.permetti_doppio_primo, a.permetti_doppio_contorno, a.limite_oggi, a.limite_domani,
                        a.modalita
                 FROM utenti u
                 INNER JOIN aziende a ON a.id = u.azienda_id
                 WHERE a.slug = :slug AND u.matricola = :matricola
                 LIMIT 1'
            );
            $stmt->execute(['slug' => $slug, 'matricola' => $matricola]);
            $utente = $stmt->fetch();

            $hash = is_array($utente) ? (string) $utente['password_hash'] : self::TIMING_PAD;
            $valido = is_array($utente) && password_verify($password, $hash);

            if (!$valido) {
                $this->registraFallimento($slug, $matricola, is_array($riga) ? $riga : null, $now);
                $this->pdo->commit();
                throw new MenuRuleException('CREDENZIALI', 'Credenziali non valide.', 401);
            }

            if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::COSTO_BCRYPT])) {
                $aggiorna = $this->pdo->prepare(
                    'UPDATE utenti SET password_hash = :hash WHERE id = :id AND azienda_id = :azienda'
                );
                $aggiorna->execute([
                    'hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => self::COSTO_BCRYPT]),
                    'id' => (int) $utente['id'],
                    'azienda' => (int) $utente['azienda_id'],
                ]);
            }

            if (is_array($riga)) {
                $cancella = $this->pdo->prepare('DELETE FROM login_tentativi WHERE id = ? AND azienda_slug = ?');
                $cancella->execute([(int) $riga['id'], $slug]);
            }

            $this->pdo->commit();
        } catch (MenuRuleException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->apriSessione((int) $utente['id'], (int) $utente['azienda_id']);

        $azienda = AziendaConfig::fromRow([
            'id' => $utente['azienda_id'],
            'nome' => $utente['azienda_nome'],
            'slug' => $utente['slug'],
            'tipo_pagamento' => $utente['tipo_pagamento'],
            'max_scelte' => $utente['max_scelte'],
            'permetti_doppio_primo' => $utente['permetti_doppio_primo'],
            'permetti_doppio_contorno' => $utente['permetti_doppio_contorno'],
            'limite_oggi' => $utente['limite_oggi'],
            'limite_domani' => $utente['limite_domani'],
            'modalita' => $utente['modalita'],
        ]);

        return [
            'csrf' => $_SESSION['auth']['csrf'],
            'utente' => [
                'id' => (int) $utente['id'],
                'nome' => (string) $utente['nome'],
                'matricola' => (string) $utente['matricola'],
                'ruolo' => (string) $utente['ruolo'],
            ],
            'azienda' => $azienda->toArray(),
        ];
    }

    public function logout(): void
    {
        $this->assicuraSessione();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parametri = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $parametri['path'],
                    'domain' => $parametri['domain'],
                    'secure' => (bool) $parametri['secure'],
                    'httponly' => (bool) $parametri['httponly'],
                    'samesite' => 'Strict',
                ],
            );
        }
        session_destroy();
    }

    /** @return array{id: int, azienda_id: int, matricola: string, nome: string, ruolo: string, csrf: string} */
    public function richiediUtente(): array
    {
        $this->assicuraSessione();
        $auth = $_SESSION['auth'] ?? null;
        if (!is_array($auth)) {
            throw new MenuRuleException('NON_AUTENTICATO', 'Accesso richiesto.', 401);
        }
        if ((int) ($auth['scadenza'] ?? 0) < time()) {
            $this->logout();
            throw new MenuRuleException('SESSIONE_SCADUTA', 'Sessione scaduta. Accedi di nuovo.', 401);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, azienda_id, matricola, nome, ruolo
             FROM utenti
             WHERE id = :id AND azienda_id = :azienda'
        );
        $stmt->execute([
            'id' => (int) ($auth['utente_id'] ?? 0),
            'azienda' => (int) ($auth['azienda_id'] ?? 0),
        ]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            $this->logout();
            throw new MenuRuleException('NON_AUTENTICATO', 'Accesso richiesto.', 401);
        }

        return [
            'id' => (int) $riga['id'],
            'azienda_id' => (int) $riga['azienda_id'],
            'matricola' => (string) $riga['matricola'],
            'nome' => (string) $riga['nome'],
            'ruolo' => (string) $riga['ruolo'],
            'csrf' => (string) ($auth['csrf'] ?? ''),
        ];
    }

    public function richiediCsrf(): void
    {
        $this->assicuraSessione();
        $inviato = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $atteso = $_SESSION['auth']['csrf'] ?? '';
        if (!is_string($inviato) || !is_string($atteso) || $atteso === '' || !hash_equals($atteso, $inviato)) {
            throw new MenuRuleException('CSRF', 'Richiesta non autorizzata. Ricarica la pagina.', 403);
        }
    }

    /** @return array{id: int, azienda_id: int, matricola: string, nome: string, ruolo: string, csrf: string} */
    public function richiediRuolo(string $ruolo): array
    {
        $utente = $this->richiediUtente();
        if ($utente['ruolo'] !== $ruolo) {
            throw new MenuRuleException('RUOLO', 'Questa operazione non è consentita al tuo profilo.', 403);
        }

        return $utente;
    }

    /** @param array{id: int, tentativi: mixed, bloccato_fino: mixed}|null $riga */
    private function registraFallimento(string $slug, string $matricola, ?array $riga, DateTimeImmutable $now): void
    {
        $tentativi = $riga === null ? 1 : ((int) $riga['tentativi']) + 1;
        $fino = null;
        if ($tentativi >= self::TENTATIVI_MASSIMI) {
            $fino = $now->modify('+' . self::BLOCCO_SECONDI . ' seconds')->format('Y-m-d H:i:s');
            $tentativi = 0;
        }

        if ($riga === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO login_tentativi (azienda_slug, matricola, tentativi, bloccato_fino)
                 VALUES (:slug, :matricola, :tentativi, :fino)'
            );
            $stmt->execute([
                'slug' => $slug,
                'matricola' => $matricola,
                'tentativi' => $tentativi,
                'fino' => $fino,
            ]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE login_tentativi
             SET tentativi = :tentativi, bloccato_fino = :fino
             WHERE id = :id AND azienda_slug = :slug'
        );
        $stmt->execute([
            'tentativi' => $tentativi,
            'fino' => $fino,
            'id' => (int) $riga['id'],
            'slug' => $slug,
        ]);
    }

    private function apriSessione(int $utenteId, int $aziendaId): void
    {
        $this->assicuraSessione();
        session_regenerate_id(true);
        $_SESSION = [
            'auth' => [
                'utente_id' => $utenteId,
                'azienda_id' => $aziendaId,
                'csrf' => bin2hex(random_bytes(32)),
                'scadenza' => time() + self::SESSIONE_SECONDI,
            ],
        ];
    }

    private function assicuraSessione(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function pad(string $password): void
    {
        password_verify($password !== '' ? $password : ' ', self::TIMING_PAD);
    }
}
