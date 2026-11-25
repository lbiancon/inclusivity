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
        'matricola' => ['matricola', 'badge', 'code_employee', 'codice', 'id'],
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

    /** @return array{creati: int, aggiornati: int, saltati: int, errori: list<array{riga: int, messaggio: string}>, utenti: list<array<string, mixed>>} */
    public function importa(int $aziendaId, string $path, string $filename): array
    {
        $tabella = $this->parser->leggiTabella(
            $path,
            $filename,
            'Carica il template utenti (.xlsx o .csv) con le colonne matricola, nome, password.',
        );
        if ($tabella === []) {
            throw new MenuRuleException('FILE_VUOTO', 'Il file non contiene righe.');
        }

        $mappa = $this->mappaColonne($tabella[0]);
        $anagrafica = new Anagrafica($this->pdo);
        $creati = 0;
        $aggiornati = 0;
        $saltati = 0;
        $errori = [];

        $corpo = array_slice($tabella, 1);
        if (count($corpo) > self::MAX_RIGHE) {
            throw new MenuRuleException('FILE_TROPPO_GRANDE', 'Il file utenti supera le 500 righe.');
        }

        foreach ($corpo as $indice => $celle) {
            $numeroRiga = $indice + 2;
            if ($this->rigaVuota($celle)) {
                continue;
            }
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
            try {
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
                } else {
                    if ($input['password'] === '') {
                        throw new MenuRuleException('PASSWORD_NON_VALIDA', 'La password è obbligatoria per un nuovo utente.');
                    }
                    $anagrafica->creaUtente($aziendaId, $input);
                    $creati++;
                }
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

        if ($creati === 0 && $aggiornati === 0 && $errori === []) {
            throw new MenuRuleException('FILE_VUOTO', 'Nessuna riga utente da importare.');
        }

        return [
            'creati' => $creati,
            'aggiornati' => $aggiornati,
            'saltati' => $saltati,
            'errori' => $errori,
            'utenti' => $anagrafica->elencoUtenti($aziendaId),
        ];
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
        foreach (['matricola', 'nome', 'password'] as $obbligatoria) {
            if (!isset($mappa[$obbligatoria])) {
                throw new MenuRuleException(
                    'COLONNE_MANCANTI',
                    'Nel file servono le colonne matricola, nome e password (pagamento, allergie e saldo sono opzionali).',
                );
            }
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

    /** Contenuto CSV del template (Excel lo apre senza problemi). */
    public static function templateCsv(): string
    {
        $righe = [
            ['matricola', 'nome', 'password', 'pagamento', 'allergie', 'saldo', 'email', 'mail_reminder'],
            ['A101', 'Mario Rossi', 'CambiaMe!2026', 'eredita', '', '', 'mario.rossi@example.com', 'Y'],
            ['A102', 'Lucia Bianchi', 'CambiaMe!2026', 'busta', 'lattosio', '', '', 'N'],
            ['A103', 'Paolo Verdi', 'CambiaMe!2026', 'pagante', '', '25,00', 'paolo.verdi@example.com', 'Y'],
        ];
        $out = "\xEF\xBB\xBF";
        foreach ($righe as $riga) {
            $out .= implode(';', array_map(static function (string $cella): string {
                if (str_contains($cella, ';') || str_contains($cella, '"')) {
                    return '"' . str_replace('"', '""', $cella) . '"';
                }

                return $cella;
            }, $riga)) . "\r\n";
        }

        return $out;
    }
}
