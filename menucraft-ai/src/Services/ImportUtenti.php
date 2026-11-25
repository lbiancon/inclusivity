<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class ImportUtenti
{
    private const MAX_RIGHE = 500;

    /** @var array<string, list<string>> */
    private const COLONNE = [
        'azienda' => ['azienda', 'azienda_slug', 'codice_azienda', 'slug_azienda', 'societa', 'company'],
        'matricola' => ['matricola', 'badge', 'code_employee'],
        'nome' => ['nome', 'employee', 'cognome_nome', 'nominativo', 'name'],
        'password' => ['password', 'pwd', 'pass', 'chiave'],
        'pagamento' => ['pagamento', 'payment', 'tipo_pagamento', 'modo'],
        'allergie' => ['allergie', 'intolleranze', 'nota', 'allergia'],
        'saldo' => ['saldo', 'wallet', 'credito', 'importo'],
        'email' => ['email', 'mail', 'e_mail', 'posta'],
        'mail_reminder' => ['mail_reminder', 'reminder', 'invia_reminder', 'reminder_mail'],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly SpreadsheetMenuParser $parser = new SpreadsheetMenuParser(),
    ) {
    }

    /**
     * Importa utenti da CSV/Excel. Ogni riga può indicare `azienda` (slug);
     * se manca si usa `$aziendaFallbackId` (azienda operativa della sessione).
     *
     * @param array{ruolo: string, azienda_id: int, id?: int} $attore
     * @return array{
     *   creati: int,
     *   aggiornati: int,
     *   saltati: int,
     *   errori: list<array{riga: int, messaggio: string}>,
     *   per_azienda: array<string, array{creati: int, aggiornati: int}>,
     *   dettaglio: list<array{riga: int, azione: string, azienda: string, matricola: string, nome: string}>,
     *   verifica_db: array<string, int>,
     *   utenti: list<array<string, mixed>>
     * }
     */
    public function importa(
        ?int $aziendaFallbackId,
        string $path,
        string $filename,
        array $attore,
    ): array {
        $tabella = $this->parser->leggiTabella(
            $path,
            $filename,
            'Carica il template utenti (.xlsx o .csv) con le colonne azienda, matricola, nome, password.',
        );
        if ($tabella === []) {
            throw new MenuRuleException('FILE_VUOTO', 'Il file non contiene righe.');
        }

        $mappa = $this->mappaColonne($tabella[0]);
        $haColonnaAzienda = isset($mappa['azienda']);
        if ($aziendaFallbackId === null && !$haColonnaAzienda) {
            throw new MenuRuleException(
                'AZIENDA_MANCANTE',
                'Nel file serve la colonna azienda (codice slug della cucina). Usa “Scarica utenti” sulla riga della società.',
                422,
            );
        }

        $anagrafica = new Anagrafica($this->pdo);
        $creati = 0;
        $aggiornati = 0;
        $saltati = 0;
        $errori = [];
        $perAzienda = [];
        $dettaglio = [];
        $ultimeUtenti = [];
        $ultimoAziendaId = $aziendaFallbackId;

        $corpo = array_slice($tabella, 1);
        if (count($corpo) > self::MAX_RIGHE) {
            throw new MenuRuleException('FILE_TROPPO_GRANDE', 'Il file utenti supera le 500 righe.');
        }

        foreach ($corpo as $indice => $celle) {
            $numeroRiga = $indice + 2;
            if ($this->rigaVuota($celle)) {
                continue;
            }
            try {
                $slugRiga = $haColonnaAzienda ? $this->cella($celle, $mappa, 'azienda') : '';
                if ($this->slugSegnaposto($slugRiga)) {
                    throw new MenuRuleException(
                        'AZIENDA_MANCANTE',
                        'Sostituisci INSERISCI-SLUG-AZIENDA con il codice reale della cucina (colonna azienda).',
                        422,
                    );
                }
                $aziendaId = $this->risolviAziendaId($slugRiga, $aziendaFallbackId, $attore);
                $input = [
                    'matricola' => $this->cella($celle, $mappa, 'matricola'),
                    'nome' => $this->cella($celle, $mappa, 'nome'),
                    'password' => $this->cella($celle, $mappa, 'password'),
                    'pagamento' => $this->cella($celle, $mappa, 'pagamento'),
                    'allergie' => $this->cella($celle, $mappa, 'allergie'),
                    'saldo' => $this->cella($celle, $mappa, 'saldo'),
                    'email' => $this->cella($celle, $mappa, 'email'),
                    'mail_reminder' => $this->cella($celle, $mappa, 'mail_reminder'),
                ];
                if ($input['pagamento'] === '') {
                    $input['pagamento'] = 'eredita';
                }
                if ($input['mail_reminder'] === '') {
                    $input['mail_reminder'] = 'N';
                }

                $slugKey = $this->slugDi($aziendaId);
                if (!isset($perAzienda[$slugKey])) {
                    $perAzienda[$slugKey] = ['creati' => 0, 'aggiornati' => 0];
                }

                $esistente = $this->trovaPerMatricola($aziendaId, $input['matricola']);
                if ($esistente !== null) {
                    if ($esistente['ruolo'] !== 'dipendente') {
                        throw new MenuRuleException('RUOLO', 'La matricola appartiene a un catering: non si importa da file.');
                    }
                    $input['utente_id'] = $esistente['id'];
                    if ($input['password'] === '') {
                        unset($input['password']);
                    }
                    $anagrafica->aggiornaUtente($aziendaId, $esistente['id'], $input);
                    $aggiornati++;
                    $perAzienda[$slugKey]['aggiornati']++;
                    $azione = 'aggiornato';
                    $utenteId = $esistente['id'];
                } else {
                    if ($input['password'] === '') {
                        throw new MenuRuleException('PASSWORD_NON_VALIDA', 'La password è obbligatoria per un nuovo utente.');
                    }
                    $creato = $anagrafica->creaUtente($aziendaId, $input);
                    $creati++;
                    $perAzienda[$slugKey]['creati']++;
                    $azione = 'creato';
                    $utenteId = (int) $creato['id'];
                }

                $check = $this->pdo->prepare(
                    'SELECT id FROM utenti WHERE id = :id AND azienda_id = :azienda AND matricola = :matricola LIMIT 1'
                );
                $check->execute([
                    'id' => $utenteId,
                    'azienda' => $aziendaId,
                    'matricola' => strtoupper(trim($input['matricola'])),
                ]);
                if ($check->fetchColumn() === false) {
                    throw new MenuRuleException(
                        'IMPORT_NON_PERSISTITO',
                        'Scrittura non trovata nel database dopo l’import (controlla di essere sul DB menucraft).',
                    );
                }

                $dettaglio[] = [
                    'riga' => $numeroRiga,
                    'azione' => $azione,
                    'azienda' => $slugKey,
                    'matricola' => strtoupper(trim($input['matricola'])),
                    'nome' => $input['nome'],
                ];
                $ultimoAziendaId = $aziendaId;
            } catch (MenuRuleException $e) {
                $saltati++;
                $errori[] = [
                    'riga' => $numeroRiga,
                    'messaggio' => $e->getMessage(),
                ];
                if (count($errori) > 50) {
                    break;
                }
            }
        }

        if ($creati === 0 && $aggiornati === 0) {
            $primo = $errori[0]['messaggio'] ?? 'Nessuna riga utente importata.';
            throw new MenuRuleException(
                'IMPORT_VUOTO',
                'Nessun utente scritto nel database. ' . $primo
                    . ($errori !== [] ? ' (vedi errori riga per riga)' : ''),
                422,
            );
        }

        $verificaDb = [];
        foreach (array_keys($perAzienda) as $slugKey) {
            $idAz = $this->idDaSlug($slugKey);
            $conta = $this->pdo->prepare(
                "SELECT COUNT(*) FROM utenti WHERE azienda_id = :a AND ruolo = 'dipendente'"
            );
            $conta->execute(['a' => $idAz]);
            $verificaDb[$slugKey] = (int) $conta->fetchColumn();
        }

        if ($ultimoAziendaId !== null) {
            $ultimeUtenti = $anagrafica->elencoUtenti($ultimoAziendaId);
        }

        return [
            'creati' => $creati,
            'aggiornati' => $aggiornati,
            'saltati' => $saltati,
            'errori' => $errori,
            'per_azienda' => $perAzienda,
            'dettaglio' => $dettaglio,
            'verifica_db' => $verificaDb,
            'utenti' => $ultimeUtenti,
        ];
    }

    private function slugSegnaposto(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        return $slug === 'inserisci-slug-azienda'
            || $slug === 'codice-azienda'
            || $slug === 'slug-azienda';
    }

    /**
     * Template scaricabile: intestazione + righe esempio, oppure esportazione
     * degli utenti già in anagrafica (password vuota = non cambia in reimport).
     *
     * @param list<string>|null $slugEsempio
     */
    public function generaTemplateCsv(
        ?int $aziendaId = null,
        bool $includiEsistenti = true,
        ?array $slugEsempio = null,
    ): string {
        $righe = [[
            'azienda', 'matricola', 'nome', 'password', 'pagamento', 'allergie', 'saldo', 'email', 'mail_reminder',
        ]];

        if ($aziendaId !== null) {
            $slug = $this->slugDi($aziendaId);
            if ($includiEsistenti) {
                $stmt = $this->pdo->prepare(
                    "SELECT matricola, nome, pagamento, allergie, email, mail_reminder
                     FROM utenti
                     WHERE azienda_id = :azienda AND ruolo = 'dipendente'
                     ORDER BY matricola"
                );
                $stmt->execute(['azienda' => $aziendaId]);
                $trovati = 0;
                foreach ($stmt as $riga) {
                    $trovati++;
                    $righe[] = [
                        $slug,
                        (string) $riga['matricola'],
                        (string) $riga['nome'],
                        '',
                        (string) $riga['pagamento'],
                        $riga['allergie'] !== null ? (string) $riga['allergie'] : '',
                        '',
                        $riga['email'] !== null ? (string) $riga['email'] : '',
                        (string) ($riga['mail_reminder'] ?? 'N') === 'Y' ? 'Y' : 'N',
                    ];
                }
                if ($trovati === 0) {
                    foreach ($this->righeEsempio($slug) as $riga) {
                        $righe[] = $riga;
                    }
                }
            } else {
                foreach ($this->righeEsempio($slug) as $riga) {
                    $righe[] = $riga;
                }
            }
        } elseif ($slugEsempio !== null && $slugEsempio !== []) {
            foreach ($this->righeEsempio((string) $slugEsempio[0]) as $riga) {
                $righe[] = $riga;
            }
        } else {
            // Segnaposto: se non lo sostituisci l'import si ferma con errore chiaro.
            foreach ($this->righeEsempio('INSERISCI-SLUG-AZIENDA') as $riga) {
                $righe[] = $riga;
            }
        }

        return $this->csvDaRighe($righe);
    }

    /** Compat: template statico senza DB. */
    public static function templateCsv(?string $slugAzienda = null): string
    {
        $self = new self(menucraft_db());
        if ($slugAzienda !== null && $slugAzienda !== '' && !$self->slugSegnaposto($slugAzienda)) {
            return $self->generaTemplateCsv(null, false, [$slugAzienda]);
        }

        return $self->generaTemplateCsv(null, false, null);
    }

    /** @return list<list<string>> */
    private function righeEsempio(string $slug, int $offset = 0): array
    {
        $base = 101 + $offset;

        return [
            [$slug, 'A' . $base, 'Mario Rossi', 'CambiaMe!2026', 'eredita', '', '', 'mario.rossi@example.com', 'Y'],
            [$slug, 'A' . ($base + 1), 'Lucia Bianchi', 'CambiaMe!2026', 'busta', 'lattosio', '', '', 'N'],
            [$slug, 'A' . ($base + 2), 'Paolo Verdi', 'CambiaMe!2026', 'pagante', '', '25,00', 'paolo.verdi@example.com', 'Y'],
        ];
    }

    /** @param array{ruolo: string, azienda_id: int} $attore */
    private function risolviAziendaId(string $slugRiga, ?int $fallbackId, array $attore): int
    {
        $slugRiga = strtolower(trim($slugRiga));
        if ($slugRiga !== '') {
            $id = $this->idDaSlug($slugRiga);
            if ($attore['ruolo'] === 'caterer' && $id !== (int) $attore['azienda_id']) {
                throw new MenuRuleException(
                    'AZIENDA_NON_CONSENTITA',
                    'Puoi importare solo utenti della tua azienda (' . $this->slugDi((int) $attore['azienda_id']) . ').',
                    403,
                );
            }

            return $id;
        }

        if ($fallbackId === null || $fallbackId < 1) {
            throw new MenuRuleException('AZIENDA_MANCANTE', 'Manca il codice azienda sulla riga.', 422);
        }
        if ($attore['ruolo'] === 'caterer' && $fallbackId !== (int) $attore['azienda_id']) {
            throw new MenuRuleException('AZIENDA_NON_CONSENTITA', 'Azienda non consentita.', 403);
        }

        return $fallbackId;
    }

    private function idDaSlug(string $slug): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM aziende WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new MenuRuleException(
                'AZIENDA_SCONOSCIUTA',
                'Azienda sconosciuta: ' . $slug . '. Usa il codice (slug) della società.',
                404,
            );
        }

        return (int) $id;
    }

    private function slugDi(int $aziendaId): string
    {
        $stmt = $this->pdo->prepare('SELECT slug FROM aziende WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $aziendaId]);
        $slug = $stmt->fetchColumn();
        if ($slug === false) {
            throw new MenuRuleException('AZIENDA_SCONOSCIUTA', 'Azienda non trovata.', 404);
        }

        return (string) $slug;
    }

    /** @return list<string> */
    private function primiSlug(int $max): array
    {
        $stmt = $this->pdo->query(
            "SELECT slug FROM aziende WHERE slug <> 'menucraft' ORDER BY nome LIMIT " . (int) $max
        );
        $out = [];
        foreach ($stmt as $riga) {
            $out[] = (string) $riga['slug'];
        }

        return $out;
    }

    /** @return array{id: int, ruolo: string}|null */
    private function trovaPerMatricola(int $aziendaId, string $matricola): ?array
    {
        $matricola = strtoupper(trim($matricola));
        if ($matricola === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, ruolo FROM utenti WHERE azienda_id = :azienda AND matricola = :matricola LIMIT 1'
        );
        $stmt->execute(['azienda' => $aziendaId, 'matricola' => $matricola]);
        $riga = $stmt->fetch();
        if ($riga === false) {
            return null;
        }

        return [
            'id' => (int) $riga['id'],
            'ruolo' => (string) $riga['ruolo'],
        ];
    }

    /**
     * @param list<string> $intestazioni
     * @return array<string, int>
     */
    private function mappaColonne(array $intestazioni): array
    {
        $normalizzate = [];
        foreach ($intestazioni as $i => $testo) {
            $chiave = $this->normalizza($testo);
            $normalizzate[$chiave] = $i;
        }
        $mappa = [];
        foreach (self::COLONNE as $campo => $alias) {
            foreach ($alias as $nome) {
                if (isset($normalizzate[$nome])) {
                    $mappa[$campo] = $normalizzate[$nome];
                    break;
                }
            }
        }
        foreach (['matricola', 'nome'] as $obbligatoria) {
            if (!isset($mappa[$obbligatoria])) {
                throw new MenuRuleException(
                    'COLONNE_MANCANTI',
                    'Nel file servono le colonne matricola e nome (azienda consigliata; password obbligatoria sui nuovi).',
                );
            }
        }
        if (!isset($mappa['password'])) {
            // password column can be missing only if every row is update — still require header for clarity
            throw new MenuRuleException(
                'COLONNE_MANCANTI',
                'Nel file serve la colonna password (lascia vuota per non cambiare quella già presente).',
            );
        }

        return $mappa;
    }

    /** @param list<string> $celle
     * @param array<string, int> $mappa
     */
    private function cella(array $celle, array $mappa, string $campo): string
    {
        if (!isset($mappa[$campo])) {
            return '';
        }
        $i = $mappa[$campo];

        return isset($celle[$i]) ? trim((string) $celle[$i]) : '';
    }

    /** @param list<string> $celle */
    private function rigaVuota(array $celle): bool
    {
        foreach ($celle as $cella) {
            if (trim((string) $cella) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizza(string $testo): string
    {
        $testo = strtolower(trim($testo));
        $testo = strtr($testo, [
            'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);
        $testo = preg_replace('/[^a-z0-9]+/', '_', $testo) ?? '';

        return trim($testo, '_');
    }

    /** @param list<list<string>> $righe */
    private function csvDaRighe(array $righe): string
    {
        $out = "\xEF\xBB\xBF";
        foreach ($righe as $riga) {
            $out .= implode(';', array_map(static function (string $cella): string {
                if (str_contains($cella, ';') || str_contains($cella, '"') || str_contains($cella, "\n")) {
                    return '"' . str_replace('"', '""', $cella) . '"';
                }

                return $cella;
            }, $riga)) . "\r\n";
        }

        return $out;
    }
}
