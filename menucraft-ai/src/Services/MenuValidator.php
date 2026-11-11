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
        if ($totale > $azienda->maxScelte) {
            throw new MenuRuleException(
                'LIMITE_SCELTE',
                sprintf('Puoi scegliere al massimo %d piatti per buono pasto.', $azienda->maxScelte),
            );
        }

        if ($conteggi[PortataType::Secondo->value] > 1) {
            throw new MenuRuleException('LIMITE_SECONDO', 'È consentito al massimo un secondo per vassoio.');
        }

        $maxPrimo = $azienda->maxPerCategoria(PortataType::Primo);
        if ($conteggi[PortataType::Primo->value] > $maxPrimo) {
            throw new MenuRuleException(
                'LIMITE_PRIMO',
                $azienda->permettiDoppioPrimo
                    ? 'Sono consentiti al massimo due primi.'
                    : 'Per questa azienda il doppio primo non è consentito.',
            );
        }

        $maxContorno = $azienda->maxPerCategoria(PortataType::Contorno);
        if ($conteggi[PortataType::Contorno->value] > $maxContorno) {
            throw new MenuRuleException(
                'LIMITE_CONTORNO',
                $azienda->permettiDoppioContorno
                    ? 'Sono consentiti al massimo due contorni.'
                    : 'Per questa azienda il doppio contorno non è consentito.',
            );
        }

        if ($azienda->bevandaObbligatoria && $conteggi[PortataType::Bevanda->value] !== 1) {
            throw new MenuRuleException(
                'BEVANDA_OBBLIGATORIA',
                sprintf(
                    'L\'acqua è obbligatoria: una bevanda e al massimo %d altre scelte.',
                    max(0, $azienda->maxScelte - 1),
                ),
            );
        }
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

    public function assertCaricamentoConsentito(DateTimeImmutable $adesso): void
    {
        $now = $this->adesso($adesso);
        if ((int) $now->format('N') !== 4) {
            throw new MenuRuleException(
                'CARICAMENTO_GIORNO',
                'Il menu della settimana successiva si carica solo il giovedì, dopo le 19:00.',
            );
        }

        $apertura = $this->combina($this->giornoCivile($now), self::ORARIO_PUBBLICAZIONE);
        if ($now < $apertura) {
            throw new MenuRuleException(
                'CARICAMENTO_ORARIO',
                'Il giovedì il caricamento del menu apre alle 19:00.',
            );
        }
    }

    /**
     * @return array{dal: DateTimeImmutable, al: DateTimeImmutable, visibile_dal: DateTimeImmutable}
     */
    public function finestraPubblicazione(DateTimeImmutable $adesso): array
    {
        $this->assertCaricamentoConsentito($adesso);
        $giovedi = $this->giornoCivile($this->adesso($adesso));
        $lunedi = $giovedi->modify('next monday');
        $fine = $giovedi->modify('first day of next month')->modify('last day of this month');

        return [
            'dal' => $lunedi,
            'al' => $fine,
            'visibile_dal' => $this->combina($giovedi->modify('+1 day'), self::ORARIO_PRENOTAZIONI),
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

        if (count($perId) !== count($piattoIds)) {
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

        if (count($ids) !== count(array_unique($ids))) {
            throw new MenuRuleException(
                'PIATTO_DUPLICATO',
                'Lo stesso piatto non può comparire due volte sullo stesso vassoio.',
            );
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
