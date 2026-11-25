<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

/**
 * Password dimenticata senza mailer: la persona apre una richiesta,
 * il catering la vede e genera una password temporanea da comunicare a voce/WhatsApp.
 */
final class PasswordRichieste
{
    private const COOLDOWN_SECONDI = 300;
    private const PASSWORD_TEMP_LUNGHEZZA = 12;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{messaggio: string, contatto: string}
     */
    public function richiedi(string $slug, string $matricola): array
    {
        $slug = strtolower(trim($slug));
        $matricola = strtoupper(trim($matricola));
        if (
            preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
            || strlen($slug) > 60
            || preg_match('/^[A-Z0-9][A-Z0-9._-]{1,31}$/', $matricola) !== 1
        ) {
            throw new MenuRuleException('CREDENZIALI', 'Indica azienda e matricola corrette.');
        }

        $azienda = $this->pdo->prepare(
            "SELECT id, contatto_linea FROM aziende WHERE slug = :slug AND slug <> 'menucraft' LIMIT 1"
        );
        $azienda->execute(['slug' => $slug]);
        $rigaAzienda = $azienda->fetch();
        if ($rigaAzienda === false) {
            // Stesso messaggio generico: non rivelare se l’azienda esiste.
            return $this->rispostaNeutra('');
        }

        $aziendaId = (int) $rigaAzienda['id'];
        $contatto = trim((string) ($rigaAzienda['contatto_linea'] ?? ''));

        $utente = $this->pdo->prepare(
            "SELECT id, ruolo FROM utenti
             WHERE azienda_id = :azienda AND matricola = :matricola
             LIMIT 1"
        );
        $utente->execute(['azienda' => $aziendaId, 'matricola' => $matricola]);
        $rigaUtente = $utente->fetch();
        if ($rigaUtente === false || (string) $rigaUtente['ruolo'] !== 'dipendente') {
            return $this->rispostaNeutra($contatto);
        }

        $utenteId = (int) $rigaUtente['id'];
        $fuso = new DateTimeZone('Europe/Rome');
        $ora = new DateTimeImmutable('now', $fuso);

        $ultima = $this->pdo->prepare(
            "SELECT id, stato, creato_il FROM password_richieste
             WHERE azienda_id = :azienda AND utente_id = :utente
             ORDER BY id DESC
             LIMIT 1"
        );
        $ultima->execute(['azienda' => $aziendaId, 'utente' => $utenteId]);
        $precedente = $ultima->fetch();
        if (is_array($precedente) && (string) $precedente['stato'] === 'aperta') {
            return $this->rispostaNeutra($contatto);
        }
        if (is_array($precedente)) {
            $creata = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $precedente['creato_il'], $fuso);
            if ($creata instanceof DateTimeImmutable && ($ora->getTimestamp() - $creata->getTimestamp()) < self::COOLDOWN_SECONDI) {
                throw new MenuRuleException(
                    'RESET_ATTENDI',
                    'Hai già chiesto un reset poco fa. Attendi qualche minuto oppure contatta il catering.',
                    429,
                );
            }
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO password_richieste (azienda_id, utente_id, stato, creato_il)
             VALUES (:azienda, :utente, \'aperta\', :quando)'
        );
        $insert->execute([
            'azienda' => $aziendaId,
            'utente' => $utenteId,
            'quando' => $ora->format('Y-m-d H:i:s'),
        ]);

        return $this->rispostaNeutra($contatto);
    }

    /** @return list<array<string, mixed>> */
    public function elencoAperte(int $aziendaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.creato_il, u.id AS utente_id, u.matricola, u.nome
             FROM password_richieste r
             INNER JOIN utenti u ON u.id = r.utente_id AND u.azienda_id = r.azienda_id
             WHERE r.azienda_id = :azienda AND r.stato = 'aperta'
             ORDER BY r.creato_il ASC, r.id ASC"
        );
        $stmt->execute(['azienda' => $aziendaId]);
        $elenco = [];
        foreach ($stmt as $riga) {
            $elenco[] = [
                'id' => (int) $riga['id'],
                'utente_id' => (int) $riga['utente_id'],
                'matricola' => (string) $riga['matricola'],
                'nome' => (string) $riga['nome'],
                'creato_il' => (string) $riga['creato_il'],
            ];
        }

        return $elenco;
    }

    /**
     * Genera una password temporanea, la applica e chiude la richiesta.
     *
     * @return array{password_temporanea: string, matricola: string, nome: string, messaggio: string}
     */
    public function generaPasswordTemporanea(int $aziendaId, int $richiestaId, int $operatoreId): array
    {
        if ($richiestaId < 1) {
            throw new MenuRuleException('RICHIESTA', 'Indica la richiesta da evadere.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.utente_id, u.matricola, u.nome, u.ruolo
             FROM password_richieste r
             INNER JOIN utenti u ON u.id = r.utente_id AND u.azienda_id = r.azienda_id
             WHERE r.id = :id AND r.azienda_id = :azienda AND r.stato = 'aperta'
             LIMIT 1"
        );
        $stmt->execute(['id' => $richiestaId, 'azienda' => $aziendaId]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            throw new MenuRuleException('RICHIESTA', 'Richiesta non trovata o già chiusa.', 404);
        }
        if ((string) $riga['ruolo'] !== 'dipendente') {
            throw new MenuRuleException('RUOLO', 'Si può resettare solo l’accesso di un dipendente.');
        }

        $temporanea = $this->generaPassword();
        $hash = password_hash($temporanea, PASSWORD_BCRYPT, ['cost' => AuthService::COSTO_BCRYPT]);
        $ora = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $chiudi = $this->pdo->prepare(
                "UPDATE password_richieste
                 SET stato = 'evasa', evasa_il = :quando, evasa_da = :operatore
                 WHERE id = :id AND azienda_id = :azienda AND stato = 'aperta'"
            );
            $chiudi->execute([
                'quando' => $ora,
                'operatore' => $operatoreId,
                'id' => $richiestaId,
                'azienda' => $aziendaId,
            ]);
            if ($chiudi->rowCount() < 1) {
                throw new MenuRuleException('RICHIESTA', 'Richiesta non trovata o già chiusa.', 404);
            }

            $updUser = $this->pdo->prepare(
                'UPDATE utenti SET password_hash = :hash WHERE id = :id AND azienda_id = :azienda'
            );
            $updUser->execute([
                'hash' => $hash,
                'id' => (int) $riga['utente_id'],
                'azienda' => $aziendaId,
            ]);

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

        return [
            'password_temporanea' => $temporanea,
            'matricola' => (string) $riga['matricola'],
            'nome' => (string) $riga['nome'],
            'messaggio' => sprintf(
                'Password temporanea per %s (%s). Comunicala solo a quella persona, poi chiedile di cambiarla dalla sezione Password.',
                (string) $riga['nome'],
                (string) $riga['matricola'],
            ),
        ];
    }

    public function annulla(int $aziendaId, int $richiestaId, int $operatoreId): void
    {
        if ($richiestaId < 1) {
            throw new MenuRuleException('RICHIESTA', 'Indica la richiesta da chiudere.');
        }
        $ora = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "UPDATE password_richieste
             SET stato = 'annullata', evasa_il = :quando, evasa_da = :operatore
             WHERE id = :id AND azienda_id = :azienda AND stato = 'aperta'"
        );
        $stmt->execute([
            'quando' => $ora,
            'operatore' => $operatoreId,
            'id' => $richiestaId,
            'azienda' => $aziendaId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new MenuRuleException('RICHIESTA', 'Richiesta non trovata o già chiusa.', 404);
        }
    }

    /** @return array{messaggio: string, contatto: string} */
    private function rispostaNeutra(string $contatto): array
    {
        $messaggio = 'Se i dati sono corretti, il catering ha ricevuto la richiesta di reset. '
            . 'Ti comunicherà una password temporanea (di persona o via contatto aziendale).';
        if ($contatto !== '') {
            $messaggio .= ' Contatto catering: ' . $contatto . '.';
        }

        return [
            'messaggio' => $messaggio,
            'contatto' => $contatto,
        ];
    }

    private function generaPassword(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%';
        $max = strlen($alfabeto) - 1;
        $password = '';
        for ($i = 0; $i < self::PASSWORD_TEMP_LUNGHEZZA; $i++) {
            $password .= $alfabeto[random_int(0, $max)];
        }
        if (strlen($password) < 10) {
            throw new MenuRuleException('PASSWORD_NON_VALIDA', 'Impossibile generare la password temporanea.');
        }

        return $password;
    }
}
