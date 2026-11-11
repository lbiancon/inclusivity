<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use MenuCraft\Enums\PortataType;
use MenuCraft\Support\CodicePiatto;
use MenuCraft\Support\LetteraPasto;
use MenuCraft\Support\Money;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class MenuBanco
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MenuValidator $validator,
    ) {
    }

    /** @return array<string, mixed> */
    public function giorno(int $aziendaId, string $data): array
    {
        $giorno = $this->data($data);
        (new LetteraPasto())->assegna($this->pdo, $aziendaId, $giorno);

        $voci = $this->pdo->prepare(
            'SELECT v.id, v.lettera, v.piatto_id, p.codice, p.nome, p.categoria, p.prezzo
             FROM menu_voci v
             INNER JOIN piatti p ON p.id = v.piatto_id AND p.azienda_id = v.azienda_id
             WHERE v.azienda_id = :azienda AND v.data_pasto = :data
             ORDER BY v.lettera, p.nome'
        );
        $voci->execute(['azienda' => $aziendaId, 'data' => $giorno]);
        $piatti = [];
        $presenti = [];
        foreach ($voci as $riga) {
            $presenti[(int) $riga['piatto_id']] = true;
            $piatti[] = $this->riga($riga);
        }

        $catalogo = $this->pdo->prepare(
            'SELECT id, codice, nome, categoria, prezzo
             FROM piatti
             WHERE azienda_id = :azienda
             ORDER BY categoria, nome'
        );
        $catalogo->execute(['azienda' => $aziendaId]);
        $disponibili = [];
        foreach ($catalogo as $riga) {
            if (isset($presenti[(int) $riga['id']])) {
                continue;
            }
            $disponibili[] = [
                'id' => (int) $riga['id'],
                'codice' => CodicePiatto::etichetta((string) $riga['codice']),
                'nome' => (string) $riga['nome'],
                'categoria' => (string) $riga['categoria'],
                'prezzo' => Money::normalizza((string) $riga['prezzo']),
            ];
        }

        return [
            'data' => $giorno,
            'piatti' => $piatti,
            'catalogo' => $disponibili,
            'lettere' => LetteraPasto::ALFABETO,
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function aggiungi(int $aziendaId, int $attoreId, array $input): array
    {
        $this->ruolo($aziendaId, $attoreId);
        $giorno = $this->data((string) ($input['data'] ?? ''));
        $this->dataAperta($giorno);
        $piattoId = (int) ($input['piatto_id'] ?? 0);
        if ($piattoId < 1 || !$this->piattoDellAzienda($aziendaId, $piattoId)) {
            throw new MenuRuleException('PIATTO_NON_VALIDO', 'Scegli un piatto del catalogo di questa azienda.');
        }

        $this->pdo->beginTransaction();
        try {
            $pubblicazione = $this->pubblicazione($aziendaId, $attoreId, $giorno);
            $this->inserisciVoce($aziendaId, $pubblicazione, $piattoId, $giorno, LetteraPasto::valida(isset($input['lettera']) ? (string) $input['lettera'] : null));
            (new LetteraPasto())->assegna($this->pdo, $aziendaId, $giorno);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->giorno($aziendaId, $giorno);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function crea(int $aziendaId, int $attoreId, array $input): array
    {
        $this->ruolo($aziendaId, $attoreId);
        $giorno = $this->data((string) ($input['data'] ?? ''));
        $this->dataAperta($giorno);
        $nome = preg_replace('/\s+/u', ' ', trim((string) ($input['nome'] ?? ''))) ?? '';
        $nome = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $nome) ?? '');
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
            throw new MenuRuleException('PIATTO_NON_VALIDO', 'Il nome del piatto va da 2 a 120 caratteri.');
        }
        $categoria = PortataType::interpreta((string) ($input['categoria'] ?? ''));
        if ($categoria === null) {
            throw new MenuRuleException('CATEGORIA_NON_VALIDA', 'La portata non è riconosciuta.');
        }
        $prezzo = Money::normalizza(str_replace(',', '.', trim((string) ($input['prezzo'] ?? '0'))));
        $codice = strtoupper(trim((string) ($input['codice'] ?? '')));
        if ($codice === '') {
            $codice = 'MC-' . strtoupper(bin2hex(random_bytes(3)));
        }
        if (preg_match('/^[A-Z0-9][A-Z0-9._-]{0,30}$/', $codice) !== 1) {
            throw new MenuRuleException('PIATTO_NON_VALIDO', 'Il codice usa lettere, numeri, punto, trattino o underscore.');
        }
        if (strlen($codice) === 1) {
            $codice = CodicePiatto::daLettera($codice, new DateTimeImmutable($giorno));
        }

        $this->pdo->beginTransaction();
        try {
            $cerca = $this->pdo->prepare('SELECT id FROM piatti WHERE azienda_id = :azienda AND codice = :codice');
            $cerca->execute(['azienda' => $aziendaId, 'codice' => $codice]);
            $esistente = $cerca->fetchColumn();
            if ($esistente === false) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO piatti (azienda_id, codice, nome, categoria, prezzo)
                     VALUES (:azienda, :codice, :nome, :categoria, :prezzo)'
                );
                $insert->execute([
                    'azienda' => $aziendaId,
                    'codice' => $codice,
                    'nome' => $nome,
                    'categoria' => $categoria->value,
                    'prezzo' => $prezzo,
                ]);
                $piattoId = (int) $this->pdo->lastInsertId();
            } else {
                $piattoId = (int) $esistente;
            }
            $pubblicazione = $this->pubblicazione($aziendaId, $attoreId, $giorno);
            $this->inserisciVoce($aziendaId, $pubblicazione, $piattoId, $giorno, LetteraPasto::valida(isset($input['lettera']) ? (string) $input['lettera'] : null));
            (new LetteraPasto())->assegna($this->pdo, $aziendaId, $giorno);
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
            if ($e instanceof \PDOException && $e->getCode() === '23000') {
                throw new MenuRuleException('PIATTO_NON_VALIDO', 'Questo piatto è già nel menu del giorno.', 409);
            }
            throw $e;
        }

        return $this->giorno($aziendaId, $giorno);
    }

    /** @return array<string, mixed> */
    public function togli(int $aziendaId, int $attoreId, string $data, int $piattoId): array
    {
        $this->ruolo($aziendaId, $attoreId);
        $giorno = $this->data($data);
        $this->dataAperta($giorno);
        if ($piattoId < 1) {
            throw new MenuRuleException('PIATTO_NON_VALIDO', 'Indica il piatto da togliere dal menu del giorno.');
        }
        $stmt = $this->pdo->prepare(
            'DELETE FROM menu_voci WHERE azienda_id = :azienda AND data_pasto = :data AND piatto_id = :piatto'
        );
        $stmt->execute(['azienda' => $aziendaId, 'data' => $giorno, 'piatto' => $piattoId]);
        if ($stmt->rowCount() < 1) {
            throw new MenuRuleException('PIATTO_NON_VALIDO', 'Questo piatto non è nel menu del giorno scelto.');
        }
        (new LetteraPasto())->ricalcola($this->pdo, $aziendaId, $giorno);

        return $this->giorno($aziendaId, $giorno);
    }

    private function ruolo(int $aziendaId, int $attoreId): void
    {
        $stmt = $this->pdo->prepare('SELECT ruolo FROM utenti WHERE id = :id AND azienda_id = :azienda');
        $stmt->execute(['id' => $attoreId, 'azienda' => $aziendaId]);
        $ruolo = (string) $stmt->fetchColumn();
        if (!in_array($ruolo, ['caterer', 'piattaforma'], true)) {
            throw new MenuRuleException('RUOLO', 'Solo il banco può comporre il menu.', 403);
        }
    }

    private function data(string $data): string
    {
        $parsata = DateTimeImmutable::createFromFormat('!Y-m-d', $data, $this->validator->fuso());
        if ($parsata === false || $parsata->format('Y-m-d') !== $data) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'La data del menu non è valida.');
        }

        return $data;
    }

    private function dataAperta(string $data): void
    {
        $oggi = $this->validator->giornoCivile($this->validator->adesso(null))->format('Y-m-d');
        if ($data < $oggi) {
            throw new MenuRuleException('DATA_PASSATA', 'Un menu già trascorso si consulta, non si modifica.');
        }
    }

    private function piattoDellAzienda(int $aziendaId, int $piattoId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM piatti WHERE id = :id AND azienda_id = :azienda');
        $stmt->execute(['id' => $piattoId, 'azienda' => $aziendaId]);

        return $stmt->fetchColumn() !== false;
    }

    private function pubblicazione(int $aziendaId, int $attoreId, string $giorno): int
    {
        $cerca = $this->pdo->prepare(
            'SELECT pubblicazione_id FROM menu_voci WHERE azienda_id = :azienda AND data_pasto = :data LIMIT 1'
        );
        $cerca->execute(['azienda' => $aziendaId, 'data' => $giorno]);
        $id = $cerca->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $adesso = $this->validator->adesso(null)->format('Y-m-d H:i:s');
        $insert = $this->pdo->prepare(
            'INSERT INTO menu_pubblicazioni
                (azienda_id, pubblicato_il, visibile_dal, valido_dal, valido_al, caricato_da)
             VALUES (:azienda, :pubblicato, :visibile, :giorno, :giorno, :attore)'
        );
        $insert->execute([
            'azienda' => $aziendaId,
            'pubblicato' => $adesso,
            'visibile' => $adesso,
            'giorno' => $giorno,
            'attore' => $attoreId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function inserisciVoce(int $aziendaId, int $pubblicazioneId, int $piattoId, string $giorno, ?string $lettera): void
    {
        if ($lettera !== null) {
            $occupata = $this->pdo->prepare(
                'SELECT id FROM menu_voci WHERE azienda_id = :azienda AND data_pasto = :data AND lettera = :lettera'
            );
            $occupata->execute(['azienda' => $aziendaId, 'data' => $giorno, 'lettera' => $lettera]);
            if ($occupata->fetchColumn() !== false) {
                $lettera = null;
            }
        }
        $insert = $this->pdo->prepare(
            'INSERT INTO menu_voci (azienda_id, pubblicazione_id, piatto_id, data_pasto, lettera)
             VALUES (:azienda, :pubblicazione, :piatto, :data, :lettera)'
        );
        $insert->execute([
            'azienda' => $aziendaId,
            'pubblicazione' => $pubblicazioneId,
            'piatto' => $piattoId,
            'data' => $giorno,
            'lettera' => $lettera,
        ]);
    }

    /** @param array<string, mixed> $riga
     * @return array<string, mixed>
     */
    private function riga(array $riga): array
    {
        return [
            'id' => (int) $riga['piatto_id'],
            'voce_id' => (int) $riga['id'],
            'lettera' => $riga['lettera'] !== null ? (string) $riga['lettera'] : '',
            'codice' => CodicePiatto::etichetta((string) $riga['codice']),
            'nome' => (string) $riga['nome'],
            'categoria' => (string) $riga['categoria'],
            'prezzo' => Money::normalizza((string) $riga['prezzo']),
        ];
    }
}
