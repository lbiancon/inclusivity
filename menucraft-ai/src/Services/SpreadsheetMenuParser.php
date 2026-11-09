<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use MenuCraft\Enums\PortataType;
use MenuCraft\Models\RigaMenu;
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
            default => throw new MenuRuleException(
                'FORMATO_FILE',
                'Carica un file .xlsx oppure .csv con le colonne data, codice, nome, categoria, prezzo.',
            ),
        };

        return $this->mappa($tabella);
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
