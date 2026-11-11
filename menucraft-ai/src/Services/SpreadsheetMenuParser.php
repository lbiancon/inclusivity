<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use MenuCraft\Enums\PortataType;
use MenuCraft\Models\RigaMenu;
use MenuCraft\Support\CodicePiatto;
use MenuCraft\Support\Money;
use ZipArchive;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class SpreadsheetMenuParser
{
    private const MAX_RIGHE = 2000;
    private const MAX_XML = 8_000_000;

    /** @var array<string, list<string>> */
    private const COLONNE = [
        'data' => ['data', 'giorno', 'date', 'data_pasto'],
        'codice' => ['codice', 'code', 'sku'],
        'nome' => ['nome', 'piatto', 'descrizione', 'name'],
        'categoria' => ['categoria', 'portata', 'tipo', 'category'],
        'prezzo' => ['prezzo', 'price', 'costo', 'importo'],
    ];

    public function __construct(
        private readonly DateTimeZone $fuso = new DateTimeZone(MenuValidator::FUSO),
    ) {
    }

    /** @return list<RigaMenu> */
    public function parse(string $path, string $filename): array
    {
        $estensione = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $intestazione = (string) file_get_contents($path, false, null, 0, 4);

        $tabella = match ($estensione) {
            'csv', 'txt' => $this->leggiCsv($path),
            'xlsx' => $this->leggiXlsx($path, $intestazione),
            'xls' => $this->leggiXls($path, $intestazione),
            default => throw new MenuRuleException(
                'FORMATO_FILE',
                'Carica il template Excel del giovedì (.xlsx) oppure un file con le colonne data, codice, nome, categoria, prezzo.',
            ),
        };

        if ($this->sembraGriglia($tabella)) {
            return $this->mappaGriglia($tabella);
        }

        return $this->mappa($tabella);
    }

    /** @return list<list<string>> */
    private function leggiXls(string $path, string $intestazione): array
    {
        if (str_starts_with($intestazione, 'PK')) {
            return $this->leggiXlsx($path, $intestazione);
        }

        throw new MenuRuleException(
            'FORMATO_XLS',
            'Questo file è un Excel .xls del formato vecchio. Aprilo, salvalo come .xlsx (Cartella di lavoro Excel) e caricalo di nuovo.',
        );
    }

    /** @return list<list<string>> */
    private function leggiCsv(string $path): array
    {
        $campione = (string) file_get_contents($path, false, null, 0, 8192);
        $campione = preg_replace('/^\xEF\xBB\xBF/', '', $campione) ?? $campione;
        if (str_contains($campione, "\0")) {
            throw new MenuRuleException('FILE_ILLEGIBILE', 'Il file CSV non è un testo valido.');
        }
        $delimitatore = substr_count($campione, ';') >= substr_count($campione, ',') ? ';' : ',';

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new MenuRuleException('FILE_ILLEGIBILE', 'Impossibile leggere il file.');
        }

        try {
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $righe = [];
            while (($riga = fgetcsv($handle, 4000, $delimitatore, '"', '\\')) !== false) {
                if ($riga === [null] || $this->rigaVuota($riga)) {
                    continue;
                }
                $pulita = [];
                foreach ($riga as $cella) {
                    $pulita[] = trim((string) $cella);
                }
                $righe[] = $pulita;
                if (count($righe) > self::MAX_RIGHE + 1) {
                    throw new MenuRuleException('FILE_TROPPO_GRANDE', 'Il file supera le 2000 righe.');
                }
            }
        } finally {
            fclose($handle);
        }

        return $righe;
    }

    /** @return list<list<string>> */
    private function leggiXlsx(string $path, string $intestazione): array
    {
        if (!str_starts_with($intestazione, "PK")) {
            throw new MenuRuleException('FILE_ILLEGIBILE', 'Il file non è un foglio Excel .xlsx valido.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new MenuRuleException('FILE_ILLEGIBILE', 'Impossibile aprire il foglio Excel.');
        }

        try {
            $condivise = $this->stringheCondivise($zip);
            $xml = $this->primoFoglio($zip);
        } finally {
            $zip->close();
        }

        $documento = new DOMDocument();
        if (!$documento->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new MenuRuleException('FILE_ILLEGIBILE', 'Il foglio Excel non contiene una tabella leggibile.');
        }

        $xpath = new DOMXPath($documento);
        $righeXml = $xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]');
        if ($righeXml === false) {
            throw new MenuRuleException('FILE_ILLEGIBILE', 'Il foglio Excel non contiene una tabella leggibile.');
        }

        $righe = [];
        foreach ($righeXml as $rigaXml) {
            $celle = [];
            $nodi = $xpath->query('*[local-name()="c"]', $rigaXml);
            if ($nodi === false) {
                continue;
            }
            foreach ($nodi as $cella) {
                $riferimento = $cella->getAttribute('r');
                $colonna = preg_replace('/\d+/', '', $riferimento) ?? '';
                if ($colonna === '') {
                    continue;
                }
                $indice = $this->indiceColonna($colonna);
                $tipo = $cella->getAttribute('t');
                $valoreNodo = $xpath->query('*[local-name()="v"]', $cella);
                $inline = $xpath->query('*[local-name()="is"]', $cella);
                $testo = '';
                if ($tipo === 's' && $valoreNodo !== false && $valoreNodo->length > 0) {
                    $posizione = (int) $valoreNodo->item(0)?->textContent;
                    $testo = $condivise[$posizione] ?? '';
                } elseif ($tipo === 'inlineStr' && $inline !== false && $inline->length > 0) {
                    $testo = $inline->item(0)?->textContent ?? '';
                } elseif ($valoreNodo !== false && $valoreNodo->length > 0) {
                    $testo = $valoreNodo->item(0)?->textContent ?? '';
                }
                $celle[$indice] = trim($testo);
            }
            if ($celle === []) {
                continue;
            }
            ksort($celle);
            $compatta = [];
            $ultimo = max(array_keys($celle));
            for ($i = 0; $i <= $ultimo; $i++) {
                $compatta[] = $celle[$i] ?? '';
            }
            if (!$this->rigaVuota($compatta)) {
                $righe[] = $compatta;
            }
            if (count($righe) > self::MAX_RIGHE + 1) {
                throw new MenuRuleException('FILE_TROPPO_GRANDE', 'Il file supera le 2000 righe.');
            }
        }

        return $righe;
    }

    /** @return list<string> */
    private function stringheCondivise(ZipArchive $zip): array
    {
        $xml = $this->leggiVoce($zip, 'xl/sharedStrings.xml');
        if ($xml === null) {
            return [];
        }

        $documento = new DOMDocument();
        if (!$documento->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new MenuRuleException('FILE_ILLEGIBILE', 'Le stringhe del foglio Excel non sono leggibili.');
        }
        $xpath = new DOMXPath($documento);
        $nodi = $xpath->query('//*[local-name()="si"]');
        $stringhe = [];
        if ($nodi === false) {
            return [];
        }
        foreach ($nodi as $nodo) {
            $stringhe[] = trim($nodo->textContent);
        }

        return $stringhe;
    }

    private function primoFoglio(ZipArchive $zip): string
    {
        $diretto = $this->leggiVoce($zip, 'xl/worksheets/sheet1.xml');
        if ($diretto !== null) {
            return $diretto;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nome = (string) $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $nome) === 1) {
                $xml = $this->leggiVoce($zip, $nome);
                if ($xml !== null) {
                    return $xml;
                }
            }
        }

        throw new MenuRuleException('FILE_ILLEGIBILE', 'Il foglio Excel non contiene un foglio di lavoro.');
    }

    private function leggiVoce(ZipArchive $zip, string $nome): ?string
    {
        $stat = $zip->statName($nome);
        if ($stat === false) {
            return null;
        }
        if (($stat['size'] ?? 0) > self::MAX_XML) {
            throw new MenuRuleException('FILE_TROPPO_GRANDE', 'Il foglio Excel è troppo grande.');
        }
        $contenuto = $zip->getFromName($nome);
        if ($contenuto === false) {
            return null;
        }

        return $contenuto;
    }

    /** @param list<list<string>> $tabella */
    private function sembraGriglia(array $tabella): bool
    {
        $giorni = [];
        foreach ($tabella as $riga) {
            foreach ($riga as $cella) {
                $giorno = $this->giornoNellaCella($cella);
                if ($giorno !== null) {
                    $giorni[$giorno] = true;
                }
            }
        }

        return count($giorni) >= 2;
    }

    /**
     * @param list<list<string>> $tabella
     * @return list<RigaMenu>
     */
    private function mappaGriglia(array $tabella): array
    {
        $periodo = $this->periodoGriglia($tabella);
        $header = $this->rigaIntestazioneGiorni($tabella);
        $giorni = $this->colonneGiorni($tabella[$header], $periodo);
        $categoria = null;
        $righe = [];
        $errori = [];

        foreach ($tabella as $indice => $celle) {
            if ($indice <= $header) {
                continue;
            }
            $letta = $this->categoriaCella($celle[0] ?? '');
            if ($letta !== null) {
                $categoria = $letta;
            }
            if ($categoria === null) {
                continue;
            }

            foreach ($giorni as $giorno) {
                $codiceGrezzo = trim($celle[$giorno['col']] ?? '');
                $nomeGrezzo = $giorno['nomeCol'] === null ? '' : trim($celle[$giorno['nomeCol']] ?? '');
                $prezzoGrezzo = $giorno['prezzoCol'] === null ? '' : trim($celle[$giorno['prezzoCol']] ?? '');
                if ($codiceGrezzo === '' && $nomeGrezzo === '' && $prezzoGrezzo === '') {
                    continue;
                }
                try {
                    [$lettera, $nome, $prezzo] = $this->spezzaPiatto($codiceGrezzo, $nomeGrezzo, $prezzoGrezzo);
                    $righe[] = new RigaMenu(
                        data: $giorno['data'],
                        codice: CodicePiatto::daLettera($lettera, $giorno['data']),
                        nome: $this->parseNome($nome),
                        categoria: $categoria,
                        prezzo: $prezzo,
                        riga: $indice + 1,
                    );
                } catch (MenuRuleException $e) {
                    $errori[] = 'Riga ' . ($indice + 1) . ': ' . $e->getMessage();
                }
            }
            if (count($righe) > self::MAX_RIGHE) {
                throw new MenuRuleException('FILE_TROPPO_GRANDE', 'Il file supera le 2000 righe.');
            }
        }

        if ($errori !== []) {
            throw new MenuRuleException('FILE_NON_VALIDO', 'Il template settimanale contiene celle non valide.', 422, $errori);
        }
        if ($righe === []) {
            throw new MenuRuleException('FILE_VUOTO', 'Il template settimanale non contiene piatti.');
        }

        return $righe;
    }

    /**
     * @param list<list<string>> $tabella
     * @return array{dal: DateTimeImmutable, al: DateTimeImmutable}
     */
    private function periodoGriglia(array $tabella): array
    {
        foreach ($tabella as $riga) {
            foreach ($riga as $cella) {
                $periodo = $this->parseIntervallo($cella);
                if ($periodo !== null) {
                    return $periodo;
                }
            }
        }

        throw new MenuRuleException(
            'PERIODO_MANCANTE',
            'Nel template manca il periodo, per esempio «Menu Mensa dal 09 al 13 novembre 2026».',
        );
    }

    /** @return array{dal: DateTimeImmutable, al: DateTimeImmutable}|null */
    private function parseIntervallo(string $testo): ?array
    {
        $normalizzato = mb_strtolower(trim($testo));
        $normalizzato = strtr($normalizzato, [
            'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u',
        ]);

        if (preg_match(
            '/dal\s+(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\s+al\s+(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?(?:\s+(\d{4}))?/u',
            $normalizzato,
            $parti,
        ) === 1) {
            $anno = (int) ($parti[7] !== '' ? $parti[7] : ($parti[6] !== '' ? $parti[6] : ($parti[3] !== '' ? $parti[3] : 0)));
            if ($anno === 0) {
                return null;
            }
            if ($anno < 100) {
                $anno += 2000;
            }
            $dal = $this->dataCivile((int) $parti[1], (int) $parti[2], $parti[3] !== '' ? $this->annoBreve($parti[3]) : $anno);
            $al = $this->dataCivile((int) $parti[4], (int) $parti[5], $parti[6] !== '' ? $this->annoBreve($parti[6]) : $anno);
            if ($dal === null || $al === null || $al < $dal) {
                return null;
            }

            return ['dal' => $dal, 'al' => $al];
        }

        if (preg_match(
            '/dal\s+(\d{1,2})(?:\s+([a-z]+))?\s+al\s+(\d{1,2})\s+([a-z]+)(?:\s+(\d{4}))?/u',
            $normalizzato,
            $parti,
        ) === 1) {
            $meseAl = self::MESI[$parti[4]] ?? null;
            $meseDal = $parti[2] !== '' ? (self::MESI[$parti[2]] ?? null) : $meseAl;
            $anno = (int) ($parti[5] ?? 0);
            if ($meseAl === null || $meseDal === null || $anno < 2000) {
                return null;
            }
            $giornoDal = (int) $parti[1];
            $giornoAl = (int) $parti[3];
            if ($meseDal === $meseAl && $giornoDal > $giornoAl) {
                $meseDal = $meseDal === 1 ? 12 : $meseDal - 1;
                if ($meseDal === 12) {
                    $annoDal = $anno - 1;
                } else {
                    $annoDal = $anno;
                }
            } else {
                $annoDal = $anno;
            }
            $dal = $this->dataCivile($giornoDal, $meseDal, $annoDal);
            $al = $this->dataCivile($giornoAl, $meseAl, $anno);
            if ($dal === null || $al === null || $al < $dal) {
                return null;
            }

            return ['dal' => $dal, 'al' => $al];
        }

        return null;
    }

    private function annoBreve(string $anno): int
    {
        $valore = (int) $anno;

        return $valore < 100 ? $valore + 2000 : $valore;
    }

    private function dataCivile(int $giorno, int $mese, int $anno): ?DateTimeImmutable
    {
        if (!checkdate($mese, $giorno, $anno)) {
            return null;
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $anno, $mese, $giorno), $this->fuso);
    }

    /** @param list<list<string>> $tabella */
    private function rigaIntestazioneGiorni(array $tabella): int
    {
        $migliore = null;
        $conteggio = 0;
        foreach ($tabella as $indice => $riga) {
            $giorni = [];
            foreach ($riga as $cella) {
                $giorno = $this->giornoNellaCella($cella);
                if ($giorno !== null) {
                    $giorni[$giorno] = true;
                }
            }
            if (count($giorni) > $conteggio) {
                $conteggio = count($giorni);
                $migliore = $indice;
            }
        }

        if ($migliore === null || $conteggio < 2) {
            throw new MenuRuleException(
                'INTESTAZIONE',
                'Nel template non trovo i giorni (lunedì–venerdì oppure C_LU, C_MA, C_ME, C_GI, C_VE).',
            );
        }

        return $migliore;
    }

    /**
     * @param list<string> $intestazione
     * @param array{dal: DateTimeImmutable, al: DateTimeImmutable} $periodo
     * @return list<array{col: int, nomeCol: ?int, prezzoCol: ?int, data: DateTimeImmutable}>
     */
    private function colonneGiorni(array $intestazione, array $periodo): array
    {
        $date = $this->datePerGiorno($periodo['dal'], $periodo['al']);
        $colonne = [];
        foreach ($intestazione as $indice => $cella) {
            $giorno = $this->giornoNellaCella($cella);
            if ($giorno === null) {
                continue;
            }
            if (!isset($date[$giorno])) {
                throw new MenuRuleException(
                    'PERIODO_MANCANTE',
                    'Il periodo del template non contiene il giorno indicato in colonna.',
                );
            }
            $colonne[] = ['col' => $indice, 'giorno' => $giorno, 'data' => $date[$giorno]];
        }

        $uscite = [];
        $totale = count($colonne);
        for ($i = 0; $i < $totale; $i++) {
            $col = $colonne[$i]['col'];
            $prossima = $colonne[$i + 1]['col'] ?? ($col + 3);
            $gap = $prossima - $col;
            $uscite[] = [
                'col' => $col,
                'nomeCol' => $gap >= 2 ? $col + 1 : null,
                'prezzoCol' => $gap >= 3 ? $col + 2 : null,
                'data' => $colonne[$i]['data'],
            ];
        }

        return $uscite;
    }

    /**
     * @return array<int, DateTimeImmutable>
     */
    private function datePerGiorno(DateTimeImmutable $dal, DateTimeImmutable $al): array
    {
        $mappa = [];
        for ($giorno = $dal; $giorno <= $al; $giorno = $giorno->modify('+1 day')) {
            $numero = (int) $giorno->format('N');
            if (isset($mappa[$numero])) {
                throw new MenuRuleException(
                    'PERIODO_MANCANTE',
                    'Il periodo copre più di una settimana. Nel titolo indica i giorni, per esempio dal 09 al 13 novembre 2026.',
                );
            }
            $mappa[$numero] = $giorno;
        }

        return $mappa;
    }

    private function giornoNellaCella(string $testo): ?int
    {
        $n = mb_strtolower($testo);
        $n = strtr($n, [
            'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u',
            "'" => '', '’' => '',
        ]);
        $controlli = [
            1 => '/lunedi|(?:^|[^a-z])c_?lu(?:[^a-z]|$)/',
            2 => '/martedi|(?:^|[^a-z])c_?ma(?:[^a-z]|$)/',
            3 => '/mercoledi|(?:^|[^a-z])c_?me(?:[^a-z]|$)/',
            4 => '/giovedi|(?:^|[^a-z])c_?gi(?:[^a-z]|$)/',
            5 => '/venerdi|(?:^|[^a-z])c_?ve(?:[^a-z]|$)/',
            6 => '/sabato|(?:^|[^a-z])c_?sa(?:[^a-z]|$)/',
            7 => '/domenica|(?:^|[^a-z])c_?do(?:[^a-z]|$)/',
        ];
        foreach ($controlli as $numero => $pattern) {
            if (preg_match($pattern, $n) === 1) {
                return $numero;
            }
        }

        return null;
    }

    private function categoriaCella(string $valore): ?PortataType
    {
        $diretta = PortataType::interpreta($valore);
        if ($diretta !== null) {
            return $diretta;
        }
        if (preg_match('/^\s*(primi|primo|secondi|secondo|contorni|contorno|frutta|frutto|frutti|yogurt|dessert|dolce|dolci|bevande|bevanda)\b/iu', $valore, $parti) === 1) {
            return PortataType::interpreta($parti[1]);
        }

        return null;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function spezzaPiatto(string $codice, string $nome, string $prezzo): array
    {
        if ($nome === '' && preg_match('/^([A-Za-z][A-Za-z0-9]{0,2})\s+(.+)$/u', $codice, $parti) === 1) {
            $codice = $parti[1];
            $nome = $parti[2];
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9]{0,2}$/', $codice) !== 1) {
            throw new MenuRuleException('CODICE_NON_VALIDO', 'codice piatto non valido.');
        }
        $prezzoTrovato = $this->prezzoInCella($prezzo);
        if ($prezzoTrovato === '' && preg_match('/^(.*?)(?:\s+|€\s*)(\d+[.,]\d{2})\s*€?$/u', $nome, $parti) === 1) {
            $nome = trim($parti[1]);
            $prezzoTrovato = Money::normalizza($parti[2]);
        }

        return [strtoupper($codice), $nome, $prezzoTrovato];
    }

    private function prezzoInCella(string $valore): string
    {
        if ($valore === '') {
            return '';
        }
        if (preg_match('/^\d+(?:[.,]\d{1,2})?\s*€?$/', $valore) !== 1) {
            return '';
        }

        return Money::normalizza($valore);
    }

    /** @var array<string, int> */
    private const MESI = [
        'gennaio' => 1,
        'febbraio' => 2,
        'marzo' => 3,
        'aprile' => 4,
        'maggio' => 5,
        'giugno' => 6,
        'luglio' => 7,
        'agosto' => 8,
        'settembre' => 9,
        'ottobre' => 10,
        'novembre' => 11,
        'dicembre' => 12,
    ];

    /**
     * @param list<list<string>> $tabella
     * @return list<RigaMenu>
     */
    private function mappa(array $tabella): array
    {
        if ($tabella === []) {
            throw new MenuRuleException('FILE_VUOTO', 'Il file non contiene un menu.');
        }

        $intestazione = array_shift($tabella);
        $mappa = $this->mappaColonne($intestazione);
        $righe = [];
        $errori = [];
        $numero = 1;

        foreach ($tabella as $celle) {
            $numero++;
            if ($this->rigaVuota($celle)) {
                continue;
            }
            try {
                $righe[] = new RigaMenu(
                    data: $this->parseData($this->cella($celle, $mappa['data'])),
                    codice: $this->parseCodice($this->cella($celle, $mappa['codice'])),
                    nome: $this->parseNome($this->cella($celle, $mappa['nome'])),
                    categoria: $this->parseCategoria($this->cella($celle, $mappa['categoria'])),
                    prezzo: Money::normalizza($this->cella($celle, $mappa['prezzo'])),
                    riga: $numero,
                );
            } catch (MenuRuleException $e) {
                $errori[] = 'Riga ' . $numero . ': ' . $e->getMessage();
            }
            if (count($righe) > self::MAX_RIGHE) {
                throw new MenuRuleException('FILE_TROPPO_GRANDE', 'Il file supera le 2000 righe.');
            }
        }

        if ($errori !== []) {
            throw new MenuRuleException('FILE_NON_VALIDO', 'Il file contiene righe non valide.', 422, $errori);
        }
        if ($righe === []) {
            throw new MenuRuleException('FILE_VUOTO', 'Il file non contiene piatti da pubblicare.');
        }

        return $righe;
    }

    /**
     * @param list<string> $intestazione
     * @return array<string, int>
     */
    private function mappaColonne(array $intestazione): array
    {
        $indici = [];
        foreach ($intestazione as $posizione => $titolo) {
            $chiave = mb_strtolower(trim($titolo));
            foreach (self::COLONNE as $campo => $alias) {
                if (in_array($chiave, $alias, true)) {
                    $indici[$campo] = $posizione;
                }
            }
        }

        $mancanti = array_diff(array_keys(self::COLONNE), array_keys($indici));
        if ($mancanti !== []) {
            throw new MenuRuleException(
                'INTESTAZIONE',
                'Intestazione incompleta. Servono le colonne: data, codice, nome, categoria, prezzo.',
            );
        }

        return $indici;
    }

    /** @param list<string> $celle */
    private function cella(array $celle, int $indice): string
    {
        return trim($celle[$indice] ?? '');
    }

    private function parseData(string $valore): DateTimeImmutable
    {
        if ($valore === '') {
            throw new MenuRuleException('DATA_NON_VALIDA', 'data mancante.');
        }

        if (preg_match('/^(?<giorni>\d{5})(?:[.,]\d+)?$/', $valore, $parti) === 1) {
            $seriale = (int) $parti['giorni'];
            if ($seriale > 20000 && $seriale < 80000) {
                $base = new DateTimeImmutable('1899-12-30', $this->fuso);

                return $base->modify('+' . $seriale . ' days')->setTime(0, 0, 0);
            }
        }

        foreach (['!Y-m-d', '!d/m/Y', '!j/n/Y', '!d-m-Y', '!j-n-Y'] as $formato) {
            $data = DateTimeImmutable::createFromFormat($formato, $valore, $this->fuso);
            $errori = DateTimeImmutable::getLastErrors();
            if (
                !$data instanceof DateTimeImmutable
                || ($errori !== false && ($errori['warning_count'] > 0 || $errori['error_count'] > 0))
            ) {
                continue;
            }
            $mese = (int) $data->format('n');
            $giorno = (int) $data->format('j');
            $anno = (int) $data->format('Y');
            if (!checkdate($mese, $giorno, $anno)) {
                continue;
            }

            return $data->setTime(0, 0, 0);
        }

        throw new MenuRuleException('DATA_NON_VALIDA', 'data non valida (usa AAAA-MM-GG o GG/MM/AAAA).');
    }

    private function parseCodice(string $valore): string
    {
        $codice = strtoupper(trim($valore));
        if (preg_match('/^[A-Z0-9][A-Z0-9-]{0,31}$/', $codice) !== 1) {
            throw new MenuRuleException('CODICE_NON_VALIDO', 'codice piatto non valido.');
        }

        return $codice;
    }

    private function parseNome(string $valore): string
    {
        $nome = preg_replace('/\s+/u', ' ', trim($valore)) ?? '';
        $nome = preg_replace('/[^\p{L}\p{N}\p{P}\p{Zs}]/u', '', $nome) ?? '';
        $nome = trim($nome);
        if ($nome === '' || mb_strlen($nome) > 120) {
            throw new MenuRuleException('NOME_NON_VALIDO', 'nome del piatto mancante o troppo lungo.');
        }

        return $nome;
    }

    private function parseCategoria(string $valore): PortataType
    {
        $categoria = PortataType::interpreta($valore);
        if ($categoria === null) {
            throw new MenuRuleException(
                'CATEGORIA_NON_VALIDA',
                'categoria non valida (primo, secondo, contorno, dessert, yogurt, frutta, bevanda).',
            );
        }

        return $categoria;
    }

    /** @param list<string|null> $riga */
    private function rigaVuota(array $riga): bool
    {
        foreach ($riga as $cella) {
            if (trim((string) $cella) !== '') {
                return false;
            }
        }

        return true;
    }

    private function indiceColonna(string $colonna): int
    {
        $indice = 0;
        foreach (str_split(strtoupper($colonna)) as $lettera) {
            $indice = ($indice * 26) + (ord($lettera) - 64);
        }

        return $indice - 1;
    }
}
