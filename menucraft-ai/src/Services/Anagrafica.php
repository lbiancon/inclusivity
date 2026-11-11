<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use MenuCraft\Support\Money;
use PDO;
use PDOException;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class Anagrafica
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function elencoSocieta(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nome, slug, tipo_pagamento, modalita, bevanda_obbligatoria
             FROM aziende
             WHERE slug <> 'menucraft'
             ORDER BY nome"
        );
        $elenco = [];
        foreach ($stmt as $riga) {
            $elenco[] = [
                'id' => (int) $riga['id'],
                'nome' => (string) $riga['nome'],
                'slug' => (string) $riga['slug'],
                'tipo_pagamento' => (string) $riga['tipo_pagamento'],
                'modalita' => (string) $riga['modalita'],
                'bevanda_obbligatoria' => (int) $riga['bevanda_obbligatoria'] === 1,
            ];
        }

        return $elenco;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function creaSocieta(array $input): array
    {
        $nome = $this->nome((string) ($input['nome'] ?? ''), 'Indica il nome della società.');
        $slug = $this->slug((string) ($input['slug'] ?? ''));
        $pagamento = $this->pagamentoAzienda($input['tipo_pagamento'] ?? 'welfare');
        $modalita = $this->modalita($input['modalita'] ?? 'intranet');
        $catererNome = $this->nome((string) ($input['caterer_nome'] ?? ''), 'Indica il nome di chi gestisce la cucina.');
        $matricola = $this->matricola((string) ($input['caterer_matricola'] ?? ''));
        $password = $this->password((string) ($input['caterer_password'] ?? ''));

        $this->pdo->beginTransaction();
        try {
            $esistente = $this->pdo->prepare('SELECT id FROM aziende WHERE slug = :slug');
            $esistente->execute(['slug' => $slug]);
            if ($esistente->fetchColumn() !== false) {
                throw new MenuRuleException('SLUG_ESISTENTE', 'Questa azienda esiste già. Scegli un altro codice.', 409);
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO aziende
                    (nome, slug, tipo_pagamento, max_scelte, permetti_doppio_primo, permetti_doppio_contorno,
                     limite_oggi, limite_domani, modalita, bevanda_obbligatoria, max_primo, max_secondo, max_contorno)
                 VALUES (:nome, :slug, :pagamento, 4, 0, 0, :oggi, :domani, :modalita, 1, 1, 1, 1)'
            );
            $insert->execute([
                'nome' => $nome,
                'slug' => $slug,
                'pagamento' => $pagamento,
                'oggi' => '09:30:00',
                'domani' => '18:00:00',
                'modalita' => $modalita,
            ]);
            $aziendaId = (int) $this->pdo->lastInsertId();
            $this->inserisciUtente($aziendaId, $matricola, $catererNome, $password, 'caterer', 'eredita', null);
            $this->pdo->commit();
        } catch (MenuRuleException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                throw new MenuRuleException('SLUG_ESISTENTE', 'Questa azienda esiste già. Scegli un altro codice.', 409);
            }
            throw $e;
        }

        return [
            'id' => $aziendaId,
            'nome' => $nome,
            'slug' => $slug,
            'tipo_pagamento' => $pagamento,
            'modalita' => $modalita,
            'caterer_matricola' => $matricola,
            'messaggio' => sprintf(
                'Cucina %s creata. Il catering entra con azienda %s e matricola %s.',
                $nome,
                $slug,
                $matricola,
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function elencoUtenti(int $aziendaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, matricola, nome, ruolo, pagamento, allergie
             FROM utenti
             WHERE azienda_id = :azienda AND ruolo <> :piattaforma
             ORDER BY ruolo, matricola'
        );
        $stmt->execute(['azienda' => $aziendaId, 'piattaforma' => 'piattaforma']);
        $elenco = [];
        foreach ($stmt as $riga) {
            $elenco[] = [
                'id' => (int) $riga['id'],
                'matricola' => (string) $riga['matricola'],
                'nome' => (string) $riga['nome'],
                'ruolo' => (string) $riga['ruolo'],
                'pagamento' => (string) $riga['pagamento'],
                'allergie' => $riga['allergie'] !== null ? (string) $riga['allergie'] : '',
            ];
        }

        return $elenco;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function creaUtente(int $aziendaId, array $input): array
    {
        $azienda = $this->azienda($aziendaId);
        $nome = $this->nome((string) ($input['nome'] ?? ''), 'Indica il nome di chi prenota.');
        $matricola = $this->matricola((string) ($input['matricola'] ?? ''));
        $password = $this->password((string) ($input['password'] ?? ''));
        $pagamento = $this->pagamentoUtente($input['pagamento'] ?? 'eredita');
        $effettivo = match ($pagamento) {
            'welfare' => 'welfare',
            'pagante' => 'dipendente',
            default => (string) $azienda['tipo_pagamento'],
        };
        $saldo = $effettivo === 'dipendente' ? $this->saldo($input['saldo'] ?? '0') : null;

        $this->pdo->beginTransaction();
        try {
            $id = $this->inserisciUtente($aziendaId, $matricola, $nome, $password, 'dipendente', $pagamento, $saldo);
            $this->scriviAllergie($aziendaId, $id, (string) ($input['allergie'] ?? ''));
            $this->pdo->commit();
        } catch (MenuRuleException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                throw new MenuRuleException('MATRICOLA_ESISTENTE', 'Questa matricola è già usata in azienda.', 409);
            }
            throw $e;
        }

        return [
            'id' => $id,
            'matricola' => $matricola,
            'nome' => $nome,
            'pagamento' => $pagamento,
            'messaggio' => sprintf('Utente %s creato. Entra con azienda %s e matricola %s.', $nome, $azienda['slug'], $matricola),
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function aggiornaUtente(int $aziendaId, int $attoreId, array $input): array
    {
        $id = (int) ($input['utente_id'] ?? 0);
        $persona = $this->persona($aziendaId, $id);
        if ($persona['ruolo'] === 'caterer' && $attoreId !== $id && (string) ($input['pagamento'] ?? $persona['pagamento']) !== $persona['pagamento']) {
            throw new MenuRuleException('RUOLO', 'Il pagamento del catering non si cambia da qui.');
        }
        $nome = $this->nome((string) ($input['nome'] ?? $persona['nome']), 'Indica il nome di chi prenota.');
        $pagamento = $persona['ruolo'] === 'dipendente'
            ? $this->pagamentoUtente($input['pagamento'] ?? $persona['pagamento'])
            : $persona['pagamento'];
        $password = trim((string) ($input['password'] ?? ''));
        if ($password !== '') {
            $password = $this->password($password);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE utenti SET nome = :nome, pagamento = :pagamento WHERE id = :id AND azienda_id = :azienda'
        );
        $stmt->execute([
            'nome' => $nome,
            'pagamento' => $pagamento,
            'id' => $id,
            'azienda' => $aziendaId,
        ]);
        if ($password !== '') {
            $hash = $this->pdo->prepare(
                'UPDATE utenti SET password_hash = :hash WHERE id = :id AND azienda_id = :azienda'
            );
            $hash->execute([
                'hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => AuthService::COSTO_BCRYPT]),
                'id' => $id,
                'azienda' => $aziendaId,
            ]);
        }
        $allergie = $this->scriviAllergie($aziendaId, $id, (string) ($input['allergie'] ?? ''));
        if (array_key_exists('saldo', $input) && trim((string) $input['saldo']) !== '' && $persona['ruolo'] === 'dipendente') {
            $saldo = $this->saldo($input['saldo']);
            $wallet = $this->pdo->prepare(
                'INSERT INTO portafogli (azienda_id, utente_id, saldo_centesimi) VALUES (:azienda, :utente, :saldo)
                 ON DUPLICATE KEY UPDATE saldo_centesimi = :saldo_nuovo'
            );
            $wallet->execute([
                'azienda' => $aziendaId,
                'utente' => $id,
                'saldo' => $saldo,
                'saldo_nuovo' => $saldo,
            ]);
        }

        return [
            'id' => $id,
            'nome' => $nome,
            'pagamento' => $pagamento,
            'allergie' => $allergie,
            'messaggio' => sprintf('Utente %s aggiornato.', $nome),
        ];
    }

    public function eliminaUtente(int $aziendaId, int $attoreId, int $utenteId): void
    {
        if ($attoreId === $utenteId) {
            throw new MenuRuleException('RUOLO', 'Non puoi eliminare il tuo accesso.');
        }
        $persona = $this->persona($aziendaId, $utenteId);
        if ($persona['ruolo'] === 'caterer') {
            $conta = $this->pdo->prepare(
                'SELECT COUNT(*) FROM utenti WHERE azienda_id = :azienda AND ruolo = :ruolo'
            );
            $conta->execute(['azienda' => $aziendaId, 'ruolo' => 'caterer']);
            if ((int) $conta->fetchColumn() < 2) {
                throw new MenuRuleException('RUOLO', 'Serve almeno un catering per questa azienda.');
            }
        }
        $stmt = $this->pdo->prepare('DELETE FROM utenti WHERE id = :id AND azienda_id = :azienda');
        $stmt->execute(['id' => $utenteId, 'azienda' => $aziendaId]);
    }

    /** @return array{id: int, nome: string, ruolo: string, pagamento: string} */
    private function persona(int $aziendaId, int $utenteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, ruolo, pagamento FROM utenti WHERE id = :id AND azienda_id = :azienda'
        );
        $stmt->execute(['id' => $utenteId, 'azienda' => $aziendaId]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            throw new MenuRuleException('UTENTE_SCONOSCIUTO', 'Utente non trovato in questa azienda.', 404);
        }

        return [
            'id' => (int) $riga['id'],
            'nome' => (string) $riga['nome'],
            'ruolo' => (string) $riga['ruolo'],
            'pagamento' => (string) $riga['pagamento'],
        ];
    }

    public function segnalaAllergie(int $aziendaId, int $utenteId, string $testo): string
    {
        $this->azienda($aziendaId);
        $cerca = $this->pdo->prepare('SELECT id FROM utenti WHERE id = :id AND azienda_id = :azienda');
        $cerca->execute(['id' => $utenteId, 'azienda' => $aziendaId]);
        if ($cerca->fetchColumn() === false) {
            throw new MenuRuleException('UTENTE_SCONOSCIUTO', 'Utente non trovato in questa azienda.', 404);
        }

        return $this->scriviAllergie($aziendaId, $utenteId, $testo);
    }

    private function scriviAllergie(int $aziendaId, int $utenteId, string $testo): string
    {
        $testo = preg_replace('/\s+/u', ' ', trim($testo)) ?? '';
        $testo = preg_replace('/[\x00-\x1F\x7F]/u', '', $testo) ?? '';
        $testo = trim($testo);
        if (mb_strlen($testo) > 240) {
            throw new MenuRuleException('ALLERGIA_NON_VALIDA', 'La segnalazione può avere al massimo 240 caratteri.');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE utenti SET allergie = :testo WHERE id = :id AND azienda_id = :azienda'
        );
        $stmt->execute([
            'testo' => $testo === '' ? null : $testo,
            'id' => $utenteId,
            'azienda' => $aziendaId,
        ]);

        return $testo;
    }

    /** @return array{slug: string, tipo_pagamento: string} */
    private function azienda(int $aziendaId): array
    {
        $stmt = $this->pdo->prepare('SELECT slug, tipo_pagamento FROM aziende WHERE id = :id');
        $stmt->execute(['id' => $aziendaId]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            throw new MenuRuleException('AZIENDA_SCONOSCIUTA', 'Azienda non trovata.', 404);
        }

        return [
            'slug' => (string) $riga['slug'],
            'tipo_pagamento' => (string) $riga['tipo_pagamento'],
        ];
    }

    private function inserisciUtente(
        int $aziendaId,
        string $matricola,
        string $nome,
        string $password,
        string $ruolo,
        string $pagamento,
        ?int $saldo,
    ): int {
        $duplicata = $this->pdo->prepare(
            'SELECT id FROM utenti WHERE azienda_id = :azienda AND matricola = :matricola'
        );
        $duplicata->execute(['azienda' => $aziendaId, 'matricola' => $matricola]);
        if ($duplicata->fetchColumn() !== false) {
            throw new MenuRuleException('MATRICOLA_ESISTENTE', 'Questa matricola è già usata in azienda.', 409);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO utenti (azienda_id, matricola, nome, password_hash, ruolo, pagamento)
             VALUES (:azienda, :matricola, :nome, :hash, :ruolo, :pagamento)'
        );
        $stmt->execute([
            'azienda' => $aziendaId,
            'matricola' => $matricola,
            'nome' => $nome,
            'hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => AuthService::COSTO_BCRYPT]),
            'ruolo' => $ruolo,
            'pagamento' => $pagamento,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        if ($saldo !== null) {
            $wallet = $this->pdo->prepare(
                'INSERT INTO portafogli (azienda_id, utente_id, saldo_centesimi) VALUES (:azienda, :utente, :saldo)'
            );
            $wallet->execute([
                'azienda' => $aziendaId,
                'utente' => $id,
                'saldo' => $saldo,
            ]);
        }

        return $id;
    }

    private function nome(string $nome, string $messaggio): string
    {
        $nome = preg_replace('/\s+/u', ' ', trim($nome)) ?? '';
        $nome = preg_replace('/[\x00-\x1F\x7F]/u', '', $nome) ?? '';
        $nome = trim($nome);
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
            throw new MenuRuleException('ANAGRAFICA_NON_VALIDA', $messaggio);
        }

        return $nome;
    }

    private function slug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) < 3 || strlen($slug) > 60) {
            throw new MenuRuleException(
                'SLUG_NON_VALIDO',
                'Il codice azienda usa lettere minuscole, numeri e trattini, da 3 a 60 caratteri.',
            );
        }
        if ($slug === 'menucraft') {
            throw new MenuRuleException('SLUG_NON_VALIDO', 'Questo codice è riservato al servizio.');
        }

        return $slug;
    }

    private function matricola(string $matricola): string
    {
        $matricola = strtoupper(trim($matricola));
        if (preg_match('/^[A-Z0-9][A-Z0-9._-]{1,31}$/', $matricola) !== 1) {
            throw new MenuRuleException('MATRICOLA_NON_VALIDA', 'La matricola usa lettere, numeri, punto, trattino o underscore.');
        }

        return $matricola;
    }

    private function password(string $password): string
    {
        if (str_contains($password, "\0") || strlen($password) < 10 || strlen($password) > 72) {
            throw new MenuRuleException('PASSWORD_NON_VALIDA', 'La password deve avere tra 10 e 72 caratteri.');
        }

        return $password;
    }

    private function pagamentoAzienda(mixed $valore): string
    {
        $pagamento = strtolower(trim((string) $valore));
        if (!in_array($pagamento, ['welfare', 'dipendente'], true)) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'Il pagamento è a carico dell\'azienda oppure del dipendente.');
        }

        return $pagamento;
    }

    private function pagamentoUtente(mixed $valore): string
    {
        $pagamento = strtolower(trim((string) $valore));
        if (!in_array($pagamento, ['eredita', 'welfare', 'pagante'], true)) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'Il pagamento della persona è eredita, a carico azienda o pagante.');
        }

        return $pagamento;
    }

    private function modalita(mixed $valore): string
    {
        $modalita = strtolower(trim((string) $valore));
        if (!in_array($modalita, ['intranet', 'internet'], true)) {
            throw new MenuRuleException('REGOLA_NON_VALIDA', 'La modalità è intranet oppure internet.');
        }

        return $modalita;
    }

    private function saldo(mixed $valore): int
    {
        $testo = trim((string) $valore);
        if ($testo === '') {
            return 0;
        }
        try {
            $centesimi = Money::euroToCentesimi(str_replace(',', '.', $testo));
        } catch (MenuRuleException) {
            throw new MenuRuleException('SALDO_NON_VALIDO', 'Il saldo iniziale non è un importo valido.');
        }
        if ($centesimi > 1_000_000) {
            throw new MenuRuleException('SALDO_NON_VALIDO', 'Il saldo iniziale supera 10000,00 €.');
        }

        return $centesimi;
    }
}
