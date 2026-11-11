<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use DateTimeZone;
use MenuCraft\Enums\PortataType;
use MenuCraft\Models\AziendaConfig;
use MenuCraft\Support\Money;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class MenuValidator
{
    public const ORARIO_PUBBLICAZIONE = '19:00:00';
    public const ORARIO_PRENOTAZIONI = '07:00:00';
    public const FUSO = 'Europe/Rome';

    private readonly DateTimeZone $fuso;

    public function __construct(
        private readonly PDO $pdo,
        ?DateTimeZone $fuso = null,
    ) {
        $this->fuso = $fuso ?? new DateTimeZone(self::FUSO);
    }

    public function fuso(): DateTimeZone
    {
        return $this->fuso;
    }

    public function adesso(?DateTimeImmutable $istante = null): DateTimeImmutable
    {
        $istante ??= new DateTimeImmutable('now', $this->fuso);

        return $istante->setTimezone($this->fuso);
    }

    public function giornoCivile(DateTimeImmutable $istante): DateTimeImmutable
    {
        return $this->adesso($istante)->setTime(0, 0, 0);
    }

    /**
     * @param list<PortataType> $categorie
     */
    public function validaComposizione(AziendaConfig $azienda, array $categorie): void
    {
        if ($categorie === []) {
            throw new MenuRuleException('SELEZIONE_VUOTA', 'Seleziona almeno un piatto per il buono pasto.');
        }

        $conteggi = [];
        foreach (PortataType::cases() as $caso) {
            $conteggi[$caso->value] = 0;
        }

        foreach ($categorie as $categoria) {
            if (!$categoria instanceof PortataType) {
                throw new MenuRuleException('CATEGORIA_NON_VALIDA', 'Una portata del vassoio non è riconosciuta.');
            }
            $conteggi[$categoria->value]++;
        }

        $totale = array_sum($conteggi);
        $bevande = $conteggi[PortataType::Bevanda->value];
        $pietanze = $totale - $bevande;
        $minimo = $azienda->minimoScelte();
        $massimo = $azienda->maxScelte;

        if ($azienda->bevandaObbligatoria) {
            if ($bevande !== 1) {
                throw new MenuRuleException(
                    'BEVANDA_OBBLIGATORIA',
                    sprintf(
                        'L\'acqua è obbligatoria e conta nelle %d scelte: scegline una sola.',
                        $massimo,
                    ),
                );
            }
            if ($pietanze < 1) {
                throw new MenuRuleException(
                    'SCELTE_INCOMPLETE',
                    sprintf(
                        'L\'acqua da sola non basta: servono da %d a %d scelte, acqua compresa.',
                        $minimo,
                        $massimo,
                    ),
                );
            }
        }

        if ($totale > $massimo) {
            throw new MenuRuleException(
                'LIMITE_SCELTE',
                $azienda->bevandaObbligatoria
                    ? sprintf('Puoi scegliere al massimo %d piatti, acqua compresa.', $massimo)
                    : sprintf('Puoi scegliere al massimo %d piatti per buono pasto.', $massimo),
            );
        }

        $this->limitePortata($azienda, PortataType::Secondo, $conteggi[PortataType::Secondo->value], 'LIMITE_SECONDO', 'secondo', 'secondi');
        $this->limitePortata($azienda, PortataType::Primo, $conteggi[PortataType::Primo->value], 'LIMITE_PRIMO', 'primo', 'primi');
        $this->limitePortata($azienda, PortataType::Contorno, $conteggi[PortataType::Contorno->value], 'LIMITE_CONTORNO', 'contorno', 'contorni');
        $this->limitePortata($azienda, PortataType::Frutta, $conteggi[PortataType::Frutta->value], 'LIMITE_FRUTTA', 'frutta', 'frutti');
        $this->limitePortata($azienda, PortataType::Dessert, $conteggi[PortataType::Dessert->value], 'LIMITE_DOLCE', 'dolce', 'dolci');

        if ($totale < $minimo) {
            throw new MenuRuleException(
                'SCELTE_INCOMPLETE',
                $azienda->bevandaObbligatoria
                    ? sprintf('Servono almeno %d scelte, acqua compresa.', $minimo)
                    : sprintf('Servono almeno %d piatti per il buono pasto.', $minimo),
            );
        }
    }

    /**
     * @param list<array{id: int, nome: string, categoria: PortataType}> $piatti
     */
    public function validaQuantita(AziendaConfig $azienda, array $piatti): void
    {
        /** @var array<int, array{n: int, nome: string, categoria: PortataType}> $perId */
        $perId = [];
        foreach ($piatti as $piatto) {
            $id = (int) $piatto['id'];
            if (!isset($perId[$id])) {
                $perId[$id] = ['n' => 0, 'nome' => (string) $piatto['nome'], 'categoria' => $piatto['categoria']];
            }
            $perId[$id]['n']++;
        }

        foreach ($perId as $voce) {
            if ($voce['n'] > 2) {
                throw new MenuRuleException('DOPPIO_PIATTO', 'Lo stesso piatto si può prendere al massimo due volte.');
            }
            if ($voce['n'] === 2 && !$azienda->doppioStessoPiatto($voce['categoria'])) {
                throw new MenuRuleException(
                    'DOPPIO_PIATTO',
                    sprintf('Per questa azienda non si può raddoppiare %s.', $voce['nome']),
                );
            }
        }
    }

    private function limitePortata(
        AziendaConfig $azienda,
        PortataType $categoria,
        int $conteggio,
        string $codice,
        string $singolare,
        string $plurale,
    ): void {
        $massimo = $azienda->maxPerCategoria($categoria);
        if ($conteggio <= $massimo) {
            return;
        }

        $messaggio = $massimo === 1
            ? sprintf('Per questa azienda è consentito un solo %s.', $singolare)
            : sprintf('Sono consentiti al massimo %d %s.', $massimo, $plurale);
        throw new MenuRuleException($codice, $messaggio);
    }

    public function validaCutoff(
        AziendaConfig $azienda,
        DateTimeImmutable $dataPasto,
        ?DateTimeImmutable $adesso = null,
    ): void {
        $now = $this->adesso($adesso);
        $oggi = $this->giornoCivile($now);
        $pasto = $this->giornoCivile($dataPasto);
        $chiaveOggi = $oggi->format('Y-m-d');
        $chiavePasto = $pasto->format('Y-m-d');
        $chiaveDomani = $oggi->modify('+1 day')->format('Y-m-d');

        if ($chiavePasto < $chiaveOggi) {
            throw new MenuRuleException(
                'DATA_PASSATA',
                'Non è possibile prenotare o modificare un pasto già trascorso.',
            );
        }

        if ($chiavePasto === $chiaveOggi && $azienda->modalita === 'internet') {
            throw new MenuRuleException(
                'OGGI_SOLO_INTRANET',
                'Da internet il pasto di oggi non si prenota. Si prenota solo dalla rete aziendale, entro le '
                . substr($azienda->limiteOggi, 0, 5) . '.',
            );
        }

        if ($chiavePasto === $chiaveOggi) {
            $limite = $this->combina($oggi, $azienda->limiteOggi);
            if ($now > $limite) {
                throw new MenuRuleException(
                    'CUTOFF_OGGI',
                    sprintf(
                        'Le prenotazioni di oggi sono chiuse dopo le %s.',
                        substr($azienda->limiteOggi, 0, 5),
                    ),
                );
            }

            return;
        }

        if ($chiavePasto === $chiaveDomani) {
            $limite = $this->combina($oggi, $azienda->limiteDomani);
            if ($now >= $limite) {
                throw new MenuRuleException(
                    'CUTOFF_DOMANI',
                    sprintf(
                        'Le prenotazioni per domani sono bloccate dalle %s: il riepilogo è già stato inviato in cucina.',
                        substr($azienda->limiteDomani, 0, 5),
                    ),
                );
            }
        }
    }

    public function motivoWeekend(DateTimeImmutable $data): ?string
    {
        $giorno = (int) $this->giornoCivile($data)->format('N');

        return match ($giorno) {
            6 => 'Sabato: la mensa è chiusa.',
            7 => 'Domenica: la mensa è chiusa.',
            default => null,
        };
    }

    /**
     * Festività nazionali e chiusure del tenant. La descrizione del tenant prevale.
     *
     * @return array<string, string>
     */
    public function chiusureNelPeriodo(int $aziendaId, DateTimeImmutable $dal, DateTimeImmutable $al): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT data_chiusura, descrizione, azienda_id
             FROM calendario_chiusure
             WHERE data_chiusura BETWEEN :dal AND :al
               AND (azienda_id = :azienda OR azienda_id IS NULL)
             ORDER BY azienda_id IS NULL DESC'
        );
        $stmt->execute([
            'dal' => $this->giornoCivile($dal)->format('Y-m-d'),
            'al' => $this->giornoCivile($al)->format('Y-m-d'),
            'azienda' => $aziendaId,
        ]);

        $mappa = [];
        foreach ($stmt as $riga) {
            $mappa[(string) $riga['data_chiusura']] = (string) $riga['descrizione'];
        }

        return $mappa;
    }

    public function motivoCalendario(int $aziendaId, DateTimeImmutable $data, ?array $chiusure = null): ?string
    {
        $weekend = $this->motivoWeekend($data);
        if ($weekend !== null) {
            return $weekend;
        }

        $chiave = $this->giornoCivile($data)->format('Y-m-d');
        $mappa = $chiusure ?? $this->chiusureNelPeriodo($aziendaId, $data, $data);
        if (!isset($mappa[$chiave])) {
            return null;
        }

        return 'Mensa chiusa: ' . $mappa[$chiave] . '.';
    }

    public function validaCalendario(int $aziendaId, DateTimeImmutable $data): void
    {
        $motivo = $this->motivoCalendario($aziendaId, $data);
        if ($motivo !== null) {
            throw new MenuRuleException('GIORNO_CHIUSO', $motivo);
        }
    }

    public function assertCaricamentoConsentito(\MenuCraft\Models\AziendaConfig $azienda, DateTimeImmutable $adesso): void
    {
        if (!$azienda->caricaSoloGiovedi()) {
            return;
        }

        $now = $this->adesso($adesso);
        if ((int) $now->format('N') !== 4) {
            throw new MenuRuleException(
                'CARICAMENTO_GIORNO',
                'Per questa azienda il file della settimana successiva si carica solo il giovedì, dopo le 19:00.',
            );
        }

        $apertura = $this->combina($this->giornoCivile($now), self::ORARIO_PUBBLICAZIONE);
        if ($now < $apertura) {
            throw new MenuRuleException(
                'CARICAMENTO_ORARIO',
                'Per questa azienda il giovedì il caricamento del file apre alle 19:00.',
            );
        }
    }

    /**
     * @return array{dal: DateTimeImmutable, al: DateTimeImmutable, visibile_dal: DateTimeImmutable}
     */
    public function finestraPubblicazione(\MenuCraft\Models\AziendaConfig $azienda, DateTimeImmutable $adesso): array
    {
        $this->assertCaricamentoConsentito($azienda, $adesso);
        $oggi = $this->giornoCivile($this->adesso($adesso));

        if (!$azienda->caricaSoloGiovedi()) {
            $fine = $oggi->modify('first day of next month')->modify('last day of this month');

            return [
                'dal' => $oggi,
                'al' => $fine,
                'visibile_dal' => $this->adesso($adesso),
            ];
        }

        $lunedi = $oggi->modify('next monday');
        $fine = $oggi->modify('first day of next month')->modify('last day of this month');

        return [
            'dal' => $lunedi,
            'al' => $fine,
            'visibile_dal' => $this->combina($oggi->modify('+1 day'), self::ORARIO_PRENOTAZIONI),
        ];
    }

    /**
     * @param array{dal: DateTimeImmutable, al: DateTimeImmutable, visibile_dal: DateTimeImmutable} $finestra
     */
    public function dataNellaFinestra(int $aziendaId, DateTimeImmutable $data, array $finestra): void
    {
        $chiave = $this->giornoCivile($data)->format('Y-m-d');
        if ($chiave < $finestra['dal']->format('Y-m-d') || $chiave > $finestra['al']->format('Y-m-d')) {
            throw new MenuRuleException(
                'DATA_FUORI_FINESTRA',
                sprintf(
                    'La data %s è fuori dal periodo caricabile (dal %s al %s).',
                    $this->formattaData($data),
                    $finestra['dal']->format('d/m/Y'),
                    $finestra['al']->format('d/m/Y'),
                ),
            );
        }

        $this->validaCalendario($aziendaId, $data);
    }

    /**
     * @return array{presente: bool, visibile: bool, visibile_dal: ?string, voci: int}
     */
    public function statoMenu(int $aziendaId, DateTimeImmutable $data, ?DateTimeImmutable $adesso = null): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT MIN(pub.visibile_dal) AS visibile_dal, COUNT(*) AS voci
             FROM menu_voci v
             INNER JOIN menu_pubblicazioni pub
                ON pub.id = v.pubblicazione_id
               AND pub.azienda_id = v.azienda_id
             WHERE v.azienda_id = :azienda
               AND v.data_pasto = :data'
        );
        $stmt->execute([
            'azienda' => $aziendaId,
            'data' => $this->giornoCivile($data)->format('Y-m-d'),
        ]);
        $riga = $stmt->fetch();
        $voci = (int) ($riga['voci'] ?? 0);
        if ($voci < 1 || $riga === false || $riga['visibile_dal'] === null) {
            return [
                'presente' => false,
                'visibile' => false,
                'visibile_dal' => null,
                'voci' => 0,
            ];
        }

        $now = $this->adesso($adesso)->format('Y-m-d H:i:s');
        $visibileDal = (string) $riga['visibile_dal'];

        return [
            'presente' => true,
            'visibile' => $visibileDal <= $now,
            'visibile_dal' => $visibileDal,
            'voci' => $voci,
        ];
    }

    public function assertMenuVisibile(int $aziendaId, DateTimeImmutable $data, ?DateTimeImmutable $adesso = null): void
    {
        $stato = $this->statoMenu($aziendaId, $data, $adesso);
        if (!$stato['presente']) {
            throw new MenuRuleException('MENU_ASSENTE', 'Per questa data non è stato pubblicato un menu.');
        }
        if (!$stato['visibile']) {
            throw new MenuRuleException(
                'FINESTRA_PRENOTAZIONE',
                'I dipendenti possono prenotare da venerdì alle 07:00. Questo menu non è ancora aperto.',
            );
        }
    }

    /**
     * @param list<int> $piattoIds
     * @return list<array{id: int, codice: string, nome: string, categoria: PortataType, prezzo: string}>
     */
    public function piattiPrenotabili(
        AziendaConfig $azienda,
        DateTimeImmutable $data,
        array $piattoIds,
        ?DateTimeImmutable $adesso = null,
    ): array {
        if ($piattoIds === []) {
            throw new MenuRuleException('SELEZIONE_VUOTA', 'Seleziona almeno un piatto per il buono pasto.');
        }

        $segnaposto = implode(', ', array_fill(0, count($piattoIds), '?'));
        $sql = 'SELECT p.id, p.azienda_id, p.codice, p.nome, p.categoria, p.prezzo
                FROM piatti p
                INNER JOIN menu_voci v
                    ON v.piatto_id = p.id
                   AND v.azienda_id = p.azienda_id
                INNER JOIN menu_pubblicazioni pub
                    ON pub.id = v.pubblicazione_id
                   AND pub.azienda_id = v.azienda_id
                WHERE p.azienda_id = ?
                  AND v.data_pasto = ?
                  AND pub.visibile_dal <= ?
                  AND p.id IN (' . $segnaposto . ')';

        $stmt = $this->pdo->prepare($sql);
        $now = $this->adesso($adesso);
        $parametri = [
            $azienda->id,
            $this->giornoCivile($data)->format('Y-m-d'),
            $now->format('Y-m-d H:i:s'),
        ];
        foreach ($piattoIds as $id) {
            $parametri[] = $id;
        }
        $stmt->execute($parametri);

        $perId = [];
        foreach ($stmt->fetchAll() as $riga) {
            if ((int) $riga['azienda_id'] !== $azienda->id) {
                throw new MenuRuleException(
                    'PIATTI_NON_DISPONIBILI',
                    'Uno o più piatti non sono disponibili per questa azienda in questa data.',
                );
            }
            $perId[(int) $riga['id']] = [
                'id' => (int) $riga['id'],
                'codice' => (string) $riga['codice'],
                'nome' => (string) $riga['nome'],
                'categoria' => PortataType::from((string) $riga['categoria']),
                'prezzo' => Money::normalizza((string) $riga['prezzo']),
            ];
        }

        $unici = array_values(array_unique($piattoIds));
        if (count($perId) !== count($unici)) {
            throw new MenuRuleException(
                'PIATTI_NON_DISPONIBILI',
                'Uno o più piatti non sono disponibili per questa azienda in questa data.',
            );
        }

        $ordinati = [];
        foreach ($piattoIds as $id) {
            $ordinati[] = $perId[$id];
        }

        return $ordinati;
    }

    /**
     * @param list<int> $piattoIds
     * @return list<int>
     */
    public function normalizzaIdPiatti(array $piattoIds, int $massimo): array
    {
        if (count($piattoIds) > $massimo) {
            throw new MenuRuleException(
                'LIMITE_SCELTE',
                sprintf('Puoi scegliere al massimo %d piatti per buono pasto.', $massimo),
            );
        }

        $ids = [];
        foreach ($piattoIds as $id) {
            if (is_int($id)) {
                $valore = $id;
            } elseif (is_string($id) && preg_match('/^\d+$/', $id) === 1) {
                $valore = (int) $id;
            } else {
                throw new MenuRuleException('PIATTO_NON_VALIDO', 'Elenco piatti non valido.');
            }
            if ($valore < 1) {
                throw new MenuRuleException('PIATTO_NON_VALIDO', 'Elenco piatti non valido.');
            }
            $ids[] = $valore;
        }

        $conteggi = array_count_values($ids);
        foreach ($conteggi as $volte) {
            if ($volte > 2) {
                throw new MenuRuleException('DOPPIO_PIATTO', 'Lo stesso piatto si può prendere al massimo due volte.');
            }
        }

        return $ids;
    }

    public function combina(DateTimeImmutable $giorno, string $orario): DateTimeImmutable
    {
        if (!preg_match('/^(?<h>\d{2}):(?<m>\d{2})(?::(?<s>\d{2}))?$/', $orario, $parti)) {
            throw new \InvalidArgumentException('Orario non valido.');
        }

        $ore = (int) $parti['h'];
        $minuti = (int) $parti['m'];
        $secondi = (int) ($parti['s'] ?? 0);
        if ($ore > 23 || $minuti > 59 || $secondi > 59) {
            throw new \InvalidArgumentException('Orario non valido.');
        }

        return $this->giornoCivile($giorno)->setTime($ore, $minuti, $secondi);
    }

    public function formattaData(DateTimeImmutable $data): string
    {
        return $this->giornoCivile($data)->format('d/m/Y');
    }
}
