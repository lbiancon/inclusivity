<?php

declare(strict_types=1);

namespace MenuCraft\Schema;

use DateTimeImmutable;
use DateTimeZone;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\ItalianHolidayCalendar;
use MenuCraft\Services\MenuValidator;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class Installer
{
    private const PASSWORD_DEMO = 'MenuCraft!2026';

    /** @var list<array{codice: string, nome: string, categoria: string, prezzo: string}> */
    private const CATALOGO = [
        ['codice' => 'PR-POM', 'nome' => 'Pasta al pomodoro', 'categoria' => 'primo', 'prezzo' => '3.50'],
        ['codice' => 'PR-RIS', 'nome' => 'Risotto allo zafferano', 'categoria' => 'primo', 'prezzo' => '4.20'],
        ['codice' => 'SE-POL', 'nome' => 'Petto di pollo al limone', 'categoria' => 'secondo', 'prezzo' => '5.80'],
        ['codice' => 'SE-MER', 'nome' => 'Merluzzo al forno', 'categoria' => 'secondo', 'prezzo' => '8.20'],
        ['codice' => 'CO-INS', 'nome' => 'Insalata mista', 'categoria' => 'contorno', 'prezzo' => '1.80'],
        ['codice' => 'CO-VER', 'nome' => 'Verdure grigliate', 'categoria' => 'contorno', 'prezzo' => '2.10'],
        ['codice' => 'DE-BUD', 'nome' => 'Budino alla vaniglia', 'categoria' => 'dessert', 'prezzo' => '2.40'],
        ['codice' => 'YO-BIA', 'nome' => 'Yogurt bianco', 'categoria' => 'yogurt', 'prezzo' => '1.20'],
        ['codice' => 'FR-MEL', 'nome' => 'Mela', 'categoria' => 'frutta', 'prezzo' => '1.00'],
        ['codice' => 'BE-NAT', 'nome' => 'Acqua naturale', 'categoria' => 'bevanda', 'prezzo' => '0.80'],
        ['codice' => 'BE-FRI', 'nome' => 'Acqua frizzante', 'categoria' => 'bevanda', 'prezzo' => '0.80'],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function install(): void
    {
        $this->creaTabelle();
        $this->assicuraModalita();
        $this->semeFestivita();
        $conteggio = (int) $this->pdo->query('SELECT COUNT(*) FROM aziende')->fetchColumn();
        if ($conteggio > 0) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->semeDemo();
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function creaTabelle(): void
    {
        $tabelle = [
            'CREATE TABLE IF NOT EXISTS aziende (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                nome VARCHAR(120) NOT NULL,
                slug VARCHAR(60) NOT NULL,
                tipo_pagamento VARCHAR(20) NOT NULL,
                max_scelte TINYINT UNSIGNED NOT NULL DEFAULT 5,
                permetti_doppio_primo TINYINT(1) NOT NULL DEFAULT 0,
                permetti_doppio_contorno TINYINT(1) NOT NULL DEFAULT 0,
                limite_oggi TIME NOT NULL,
                limite_domani TIME NOT NULL,
                modalita VARCHAR(20) NOT NULL DEFAULT \'intranet\',
                PRIMARY KEY (id),
                UNIQUE KEY uq_aziende_slug (slug),
                CONSTRAINT chk_tipo_pagamento CHECK (tipo_pagamento IN (\'welfare\', \'dipendente\')),
                CONSTRAINT chk_max_scelte CHECK (max_scelte BETWEEN 1 AND 10),
                CONSTRAINT chk_modalita CHECK (modalita IN (\'intranet\', \'internet\'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS utenti (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                azienda_id BIGINT UNSIGNED NOT NULL,
                matricola VARCHAR(32) NOT NULL,
                nome VARCHAR(120) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                ruolo VARCHAR(20) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_utenti_matricola (azienda_id, matricola),
                KEY idx_utenti_azienda (azienda_id),
                CONSTRAINT fk_utenti_azienda FOREIGN KEY (azienda_id) REFERENCES aziende (id) ON DELETE CASCADE,
                CONSTRAINT chk_ruolo CHECK (ruolo IN (\'dipendente\', \'caterer\'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS piatti (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                azienda_id BIGINT UNSIGNED NOT NULL,
                codice VARCHAR(32) NOT NULL,
                nome VARCHAR(120) NOT NULL,
                categoria VARCHAR(20) NOT NULL,
                prezzo DECIMAL(8,2) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_piatti_codice (azienda_id, codice),
                KEY idx_piatti_azienda (azienda_id),
                CONSTRAINT fk_piatti_azienda FOREIGN KEY (azienda_id) REFERENCES aziende (id) ON DELETE CASCADE,
                CONSTRAINT chk_categoria CHECK (categoria IN (\'primo\', \'secondo\', \'contorno\', \'dessert\', \'yogurt\', \'frutta\', \'bevanda\')),
                CONSTRAINT chk_prezzo CHECK (prezzo >= 0 AND prezzo <= 999.99)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS calendario_chiusure (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                azienda_id BIGINT UNSIGNED NULL,
                data_chiusura DATE NOT NULL,
                descrizione VARCHAR(160) NOT NULL,
                azienda_scope BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(azienda_id, 0)) STORED,
                PRIMARY KEY (id),
                UNIQUE KEY uq_chiusura (azienda_scope, data_chiusura),
                KEY idx_chiusura_data (data_chiusura),
                CONSTRAINT fk_chiusura_azienda FOREIGN KEY (azienda_id) REFERENCES aziende (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS portafogli (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                azienda_id BIGINT UNSIGNED NOT NULL,
                utente_id BIGINT UNSIGNED NOT NULL,
                saldo_centesimi INT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_portafoglio (azienda_id, utente_id),
                CONSTRAINT fk_portafoglio_azienda FOREIGN KEY (azienda_id) REFERENCES aziende (id) ON DELETE CASCADE,
                CONSTRAINT fk_portafoglio_utente FOREIGN KEY (utente_id) REFERENCES utenti (id) ON DELETE CASCADE,
                CONSTRAINT chk_saldo CHECK (saldo_centesimi <= 100000000)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS login_tentativi (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                azienda_slug VARCHAR(60) NOT NULL,
                matricola VARCHAR(32) NOT NULL,
                tentativi SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                bloccato_fino DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_tentativo (azienda_slug, matricola)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS menu_pubblicazioni (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                azienda_id BIGINT UNSIGNED NOT NULL,
                pubblicato_il DATETIME NOT NULL,
                visibile_dal DATETIME NOT NULL,
                valido_dal DATE NOT NULL,
                valido_al DATE NOT NULL,
                caricato_da BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                KEY idx_pub_azienda (azienda_id, visibile_dal),
                CONSTRAINT fk_pub_azienda FOREIGN KEY (azienda_id) REFERENCES aziende (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS menu_voci (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                azienda_id BIGINT UNSIGNED NOT NULL,
                pubblicazione_id BIGINT UNSIGNED NOT NULL,
                piatto_id BIGINT UNSIGNED NOT NULL,
                data_pasto DATE NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_voce (azienda_id, data_pasto, piatto_id),
                KEY idx_voce_data (azienda_id, data_pasto),
                CONSTRAINT fk_voce_azienda FOREIGN KEY (azienda_id) REFERENCES aziende (id) ON DELETE CASCADE,
                CONSTRAINT fk_voce_pub FOREIGN KEY (pubblicazione_id) REFERENCES menu_pubblicazioni (id) ON DELETE CASCADE,
                CONSTRAINT fk_voce_piatto FOREIGN KEY (piatto_id) REFERENCES piatti (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS ordini (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                azienda_id BIGINT UNSIGNED NOT NULL,
                utente_id BIGINT UNSIGNED NOT NULL,
                data_pasto DATE NOT NULL,
                totale DECIMAL(8,2) NOT NULL,
                creato_il DATETIME NOT NULL,
                aggiornato_il DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ordine_giorno (azienda_id, utente_id, data_pasto),
                KEY idx_ordini_data (azienda_id, data_pasto),
                CONSTRAINT fk_ordini_azienda FOREIGN KEY (azienda_id) REFERENCES aziende (id) ON DELETE CASCADE,
                CONSTRAINT fk_ordini_utente FOREIGN KEY (utente_id) REFERENCES utenti (id) ON DELETE CASCADE,
                CONSTRAINT chk_totale CHECK (totale >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'CREATE TABLE IF NOT EXISTS ordine_righe (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ordine_id BIGINT UNSIGNED NOT NULL,
                azienda_id BIGINT UNSIGNED NOT NULL,
                piatto_id BIGINT UNSIGNED NOT NULL,
                nome VARCHAR(120) NOT NULL,
                categoria VARCHAR(20) NOT NULL,
                prezzo_applicato DECIMAL(8,2) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_righe_ordine (ordine_id, azienda_id),
                CONSTRAINT fk_righe_ordine FOREIGN KEY (ordine_id) REFERENCES ordini (id) ON DELETE CASCADE,
                CONSTRAINT chk_prezzo_riga CHECK (prezzo_applicato >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];

        foreach ($tabelle as $sql) {
            $this->pdo->exec($sql);
        }
    }

    private function assicuraModalita(): void
    {
        $colonna = $this->pdo->query("SHOW COLUMNS FROM aziende LIKE 'modalita'")->fetch();
        if ($colonna !== false) {
            return;
        }

        $this->pdo->exec(
            "ALTER TABLE aziende
             ADD COLUMN modalita VARCHAR(20) NOT NULL DEFAULT 'intranet' AFTER limite_domani"
        );
        $this->pdo->exec(
            "UPDATE aziende SET modalita = 'internet' WHERE slug = 'officine-rossi'"
        );
    }

    private function semeFestivita(): void
    {
        $fuso = new DateTimeZone(MenuValidator::FUSO);
        $anno = (int) (new DateTimeImmutable('now', $fuso))->format('Y');
        $calendario = new ItalianHolidayCalendar($fuso);
        $stmt = $this->pdo->prepare(
            'INSERT INTO calendario_chiusure (azienda_id, data_chiusura, descrizione)
             SELECT NULL, :data, :descrizione
             WHERE NOT EXISTS (
                 SELECT 1 FROM calendario_chiusure
                 WHERE azienda_id IS NULL AND data_chiusura = :data_controllo
             )'
        );

        foreach ([$anno, $anno + 1] as $esercizio) {
            foreach ($calendario->nazionali($esercizio) as $data => $descrizione) {
                $stmt->execute([
                    'data' => $data,
                    'descrizione' => $descrizione,
                    'data_controllo' => $data,
                ]);
            }
        }
    }

    private function semeDemo(): void
    {
        $fuso = new DateTimeZone(MenuValidator::FUSO);
        $now = new DateTimeImmutable('now', $fuso);
        $hash = password_hash(self::PASSWORD_DEMO, PASSWORD_BCRYPT, ['cost' => AuthService::COSTO_BCRYPT]);

        $aurora = $this->inserisciAzienda(
            'Aurora Welfare',
            'aurora-welfare',
            'welfare',
            5,
            false,
            false,
        );
        $rossi = $this->inserisciAzienda(
            'Officine Rossi',
            'officine-rossi',
            'dipendente',
            5,
            true,
            true,
            'internet',
        );

        $this->inserisciUtente($aurora, 'A100', 'Giulia Bianchi', $hash, 'dipendente', null);
        $catererAurora = $this->inserisciUtente($aurora, 'CAT-A', 'Marco Righi', $hash, 'caterer', null);
        $this->inserisciUtente($rossi, 'R200', 'Luca Ferrara', $hash, 'dipendente', 1500);
        $catererRossi = $this->inserisciUtente($rossi, 'CAT-R', 'Elena Costa', $hash, 'caterer', null);

        $patrono = $now->modify('next monday')->setTime(0, 0, 0);
        $chiusura = $this->pdo->prepare(
            'INSERT INTO calendario_chiusure (azienda_id, data_chiusura, descrizione)
             VALUES (:azienda, :data, :descrizione)'
        );
        $chiusura->execute([
            'azienda' => $aurora,
            'data' => $patrono->format('Y-m-d'),
            'descrizione' => "Festa patronale di Sant'Aurora",
        ]);

        $festivi = $this->festiviIndicizzati();
        $piattiAurora = $this->inserisciCatalogo($aurora);
        $piattiRossi = $this->inserisciCatalogo($rossi);

        $pubblicato = $now->modify('last thursday')->setTime(19, 30, 0);
        $visibile = $pubblicato->modify('+1 day')->setTime(7, 0, 0);
        $this->pubblicaCatalogo($aurora, $catererAurora, $piattiAurora, $now, $pubblicato, $visibile, $festivi, $patrono);
        $this->pubblicaCatalogo($rossi, $catererRossi, $piattiRossi, $now, $pubblicato, $visibile, $festivi, null);
    }

    private function inserisciAzienda(
        string $nome,
        string $slug,
        string $pagamento,
        int $maxScelte,
        bool $doppioPrimo,
        bool $doppioContorno,
        string $modalita = 'intranet',
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO aziende
                (nome, slug, tipo_pagamento, max_scelte, permetti_doppio_primo, permetti_doppio_contorno, limite_oggi, limite_domani, modalita)
             VALUES (:nome, :slug, :pagamento, :max, :primo, :contorno, :oggi, :domani, :modalita)'
        );
        $stmt->execute([
            'nome' => $nome,
            'slug' => $slug,
            'pagamento' => $pagamento,
            'max' => $maxScelte,
            'primo' => $doppioPrimo ? 1 : 0,
            'contorno' => $doppioContorno ? 1 : 0,
            'oggi' => '09:30:00',
            'domani' => '18:00:00',
            'modalita' => $modalita,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function inserisciUtente(
        int $aziendaId,
        string $matricola,
        string $nome,
        string $hash,
        string $ruolo,
        ?int $saldoCentesimi,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO utenti (azienda_id, matricola, nome, password_hash, ruolo)
             VALUES (:azienda, :matricola, :nome, :hash, :ruolo)'
        );
        $stmt->execute([
            'azienda' => $aziendaId,
            'matricola' => $matricola,
            'nome' => $nome,
            'hash' => $hash,
            'ruolo' => $ruolo,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        if ($saldoCentesimi !== null) {
            $wallet = $this->pdo->prepare(
                'INSERT INTO portafogli (azienda_id, utente_id, saldo_centesimi) VALUES (:azienda, :utente, :saldo)'
            );
            $wallet->execute([
                'azienda' => $aziendaId,
                'utente' => $id,
                'saldo' => $saldoCentesimi,
            ]);
        }

        return $id;
    }

    /** @return array<string, int> */
    private function inserisciCatalogo(int $aziendaId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO piatti (azienda_id, codice, nome, categoria, prezzo)
             VALUES (:azienda, :codice, :nome, :categoria, :prezzo)'
        );
        $ids = [];
        foreach (self::CATALOGO as $piatto) {
            $stmt->execute([
                'azienda' => $aziendaId,
                'codice' => $piatto['codice'],
                'nome' => $piatto['nome'],
                'categoria' => $piatto['categoria'],
                'prezzo' => $piatto['prezzo'],
            ]);
            $ids[$piatto['codice']] = (int) $this->pdo->lastInsertId();
        }

        return $ids;
    }

    /** @return array<string, true> */
    private function festiviIndicizzati(): array
    {
        $stmt = $this->pdo->query(
            'SELECT data_chiusura FROM calendario_chiusure WHERE azienda_id IS NULL'
        );
        $feste = [];
        foreach ($stmt as $riga) {
            $feste[(string) $riga['data_chiusura']] = true;
        }

        return $feste;
    }

    /**
     * @param array<string, int> $piatti
     * @param array<string, true> $festivi
     */
    private function pubblicaCatalogo(
        int $aziendaId,
        int $catererId,
        array $piatti,
        DateTimeImmutable $now,
        DateTimeImmutable $pubblicato,
        DateTimeImmutable $visibile,
        array $festivi,
        ?DateTimeImmutable $chiusuraTenant,
    ): void {
        $giorni = [];
        $cursore = $now->setTime(0, 0, 0);
        $limite = $cursore->modify('+21 days');
        while ($cursore <= $limite) {
            $chiave = $cursore->format('Y-m-d');
            $feriale = (int) $cursore->format('N') < 6;
            $nazionale = isset($festivi[$chiave]);
            $locale = $chiusuraTenant !== null && $chiusuraTenant->format('Y-m-d') === $chiave;
            if ($feriale && !$nazionale && !$locale) {
                $giorni[] = $chiave;
            }
            $cursore = $cursore->modify('+1 day');
        }

        if ($giorni === []) {
            return;
        }

        $pub = $this->pdo->prepare(
            'INSERT INTO menu_pubblicazioni
                (azienda_id, pubblicato_il, visibile_dal, valido_dal, valido_al, caricato_da)
             VALUES (:azienda, :pubblicato, :visibile, :dal, :al, :caterer)'
        );
        $pub->execute([
            'azienda' => $aziendaId,
            'pubblicato' => $pubblicato->format('Y-m-d H:i:s'),
            'visibile' => $visibile->format('Y-m-d H:i:s'),
            'dal' => $giorni[0],
            'al' => $giorni[array_key_last($giorni)],
            'caterer' => $catererId,
        ]);
        $pubblicazioneId = (int) $this->pdo->lastInsertId();

        $voce = $this->pdo->prepare(
            'INSERT INTO menu_voci (azienda_id, pubblicazione_id, piatto_id, data_pasto)
             VALUES (:azienda, :pubblicazione, :piatto, :data)'
        );
        foreach ($giorni as $giorno) {
            foreach ($piatti as $piattoId) {
                $voce->execute([
                    'azienda' => $aziendaId,
                    'pubblicazione' => $pubblicazioneId,
                    'piatto' => $piattoId,
                    'data' => $giorno,
                ]);
            }
        }
    }
}
