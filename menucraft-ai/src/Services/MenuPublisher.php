<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use MenuCraft\Models\AziendaConfig;
use MenuCraft\Support\LetteraPasto;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class MenuPublisher
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MenuValidator $validator,
        private readonly SpreadsheetMenuParser $parser,
    ) {
    }

    /** @return array<string, mixed> */
    public function pubblica(
        int $aziendaId,
        int $catererId,
        string $path,
        string $filename,
        DateTimeImmutable $adesso,
    ): array {
        $ruolo = $this->pdo->prepare(
            'SELECT ruolo FROM utenti WHERE id = :id AND azienda_id = :azienda'
        );
        $ruolo->execute(['id' => $catererId, 'azienda' => $aziendaId]);
        if ((string) $ruolo->fetchColumn() !== 'caterer') {
            throw new MenuRuleException('RUOLO', 'Solo il caterer può pubblicare il menu.', 403);
        }

        $azienda = AziendaConfig::trova($this->pdo, $aziendaId);
        $finestra = $this->validator->finestraPubblicazione($azienda, $adesso);
        $lette = $this->parser->parse($path, $filename);
        $righe = [];
        foreach ($lette as $riga) {
            if ($this->validator->motivoCalendario($aziendaId, $riga->data) !== null) {
                continue;
            }
            $righe[] = $riga;
        }
        if ($righe === []) {
            throw new MenuRuleException('FILE_VUOTO', 'Il file non contiene giorni aperti da pubblicare.');
        }

        $errori = [];
        $definiti = [];
        $visti = [];
        $date = [];

        foreach ($righe as $riga) {
            $chiaveData = $riga->data->format('Y-m-d');
            $firma = $riga->nome . '|' . $riga->categoria->value . '|' . $riga->prezzo;
            if (isset($definiti[$riga->codice]) && $definiti[$riga->codice] !== $firma) {
                $errori[] = 'Riga ' . $riga->riga . ': il codice ' . $riga->codice . ' è definito in modo contraddittorio.';
            }
            $definiti[$riga->codice] = $firma;

            $coppia = $chiaveData . '|' . $riga->codice;
            if (isset($visti[$coppia])) {
                $errori[] = 'Riga ' . $riga->riga . ': piatto duplicato nello stesso giorno.';
            }
            $visti[$coppia] = true;
            $date[$chiaveData] = true;

            try {
                $this->validator->dataNellaFinestra($aziendaId, $riga->data, $finestra);
            } catch (MenuRuleException $e) {
                $errori[] = 'Riga ' . $riga->riga . ': ' . $e->getMessage();
            }
        }

        if ($errori !== []) {
            throw new MenuRuleException('FILE_NON_VALIDO', 'Il menu non è stato pubblicato.', 422, $errori);
        }

        $this->pdo->beginTransaction();
        try {
            $idPerCodice = [];
            $cerca = $this->pdo->prepare(
                'SELECT id, prezzo FROM piatti WHERE azienda_id = :azienda AND codice = :codice'
            );
            $inserisciPiatto = $this->pdo->prepare(
                'INSERT INTO piatti (azienda_id, codice, nome, categoria, prezzo)
                 VALUES (:azienda, :codice, :nome, :categoria, :prezzo)'
            );
            $aggiorna = $this->pdo->prepare(
                'UPDATE piatti
                 SET nome = :nome, categoria = :categoria, prezzo = :prezzo
                 WHERE id = :id AND azienda_id = :azienda'
            );
            $aggiornaSenzaPrezzo = $this->pdo->prepare(
                'UPDATE piatti
                 SET nome = :nome, categoria = :categoria
                 WHERE id = :id AND azienda_id = :azienda'
            );
            $prezzoNoto = $this->pdo->prepare(
                'SELECT prezzo FROM piatti
                 WHERE azienda_id = :azienda AND nome = :nome AND categoria = :categoria
                 ORDER BY id DESC
                 LIMIT 1'
            );

            foreach ($righe as $riga) {
                if (isset($idPerCodice[$riga->codice])) {
                    continue;
                }
                $cerca->execute(['azienda' => $aziendaId, 'codice' => $riga->codice]);
                $esistente = $cerca->fetch();
                if ($esistente !== false) {
                    if ($riga->prezzo === '') {
                        $aggiornaSenzaPrezzo->execute([
                            'nome' => $riga->nome,
                            'categoria' => $riga->categoria->value,
                            'id' => (int) $esistente['id'],
                            'azienda' => $aziendaId,
                        ]);
                    } else {
                        $aggiorna->execute([
                            'nome' => $riga->nome,
                            'categoria' => $riga->categoria->value,
                            'prezzo' => $riga->prezzo,
                            'id' => (int) $esistente['id'],
                            'azienda' => $aziendaId,
                        ]);
                    }
                    $idPerCodice[$riga->codice] = (int) $esistente['id'];
                    continue;
                }

                $prezzo = $riga->prezzo;
                if ($prezzo === '') {
                    $prezzoNoto->execute([
                        'azienda' => $aziendaId,
                        'nome' => $riga->nome,
                        'categoria' => $riga->categoria->value,
                    ]);
                    $trovato = $prezzoNoto->fetchColumn();
                    $prezzo = $trovato === false ? '0.00' : (string) $trovato;
                }
                $inserisciPiatto->execute([
                    'azienda' => $aziendaId,
                    'codice' => $riga->codice,
                    'nome' => $riga->nome,
                    'categoria' => $riga->categoria->value,
                    'prezzo' => $prezzo,
                ]);
                $idPerCodice[$riga->codice] = (int) $this->pdo->lastInsertId();
            }

            $dateChiavi = array_keys($date);
            sort($dateChiavi);
            $pubblica = $this->pdo->prepare(
                'INSERT INTO menu_pubblicazioni
                    (azienda_id, pubblicato_il, visibile_dal, valido_dal, valido_al, caricato_da)
                 VALUES (:azienda, :pubblicato, :visibile, :dal, :al, :caterer)'
            );
            $pubblica->execute([
                'azienda' => $aziendaId,
                'pubblicato' => $this->validator->adesso($adesso)->format('Y-m-d H:i:s'),
                'visibile' => $finestra['visibile_dal']->format('Y-m-d H:i:s'),
                'dal' => $dateChiavi[0],
                'al' => $dateChiavi[array_key_last($dateChiavi)],
                'caterer' => $catererId,
            ]);
            $pubblicazioneId = (int) $this->pdo->lastInsertId();

            $cancella = $this->pdo->prepare(
                'DELETE FROM menu_voci WHERE azienda_id = :azienda AND data_pasto = :data'
            );
            foreach ($dateChiavi as $giorno) {
                $cancella->execute(['azienda' => $aziendaId, 'data' => $giorno]);
            }

            $inserisci = $this->pdo->prepare(
                'INSERT INTO menu_voci (azienda_id, pubblicazione_id, piatto_id, data_pasto)
                 VALUES (:azienda, :pubblicazione, :piatto, :data)'
            );
            foreach ($righe as $riga) {
                $inserisci->execute([
                    'azienda' => $aziendaId,
                    'pubblicazione' => $pubblicazioneId,
                    'piatto' => $idPerCodice[$riga->codice],
                    'data' => $riga->data->format('Y-m-d'),
                ]);
            }
            $lettere = new LetteraPasto();
            foreach ($dateChiavi as $giorno) {
                $lettere->assegna($this->pdo, $aziendaId, $giorno);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'giorni' => count($dateChiavi),
            'piatti' => count($righe),
            'valido_dal' => $dateChiavi[0],
            'valido_al' => $dateChiavi[array_key_last($dateChiavi)],
            'visibile_dal' => $finestra['visibile_dal']->format('Y-m-d H:i'),
            'messaggio' => sprintf(
                'Menu pubblicato per %d giorni. I dipendenti potranno prenotare da venerdì alle 07:00 (%s).',
                count($dateChiavi),
                $finestra['visibile_dal']->format('d/m/Y H:i'),
            ),
        ];
    }
}
