<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use MenuCraft\Enums\PortataType;
use MenuCraft\Models\AziendaConfig;
use MenuCraft\Support\LetteraPasto;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

/**
 * Export cucina dinamico: le colonne PRIMO/SECONDO/... seguono i massimi
 * del servizio (max_primo, max_contorno, max_yogurt, …).
 * Tabella export_cucina espone intestazione+celle in JSON per VB.NET.
 */
final class ExportCucina
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{categoria: string, slot: int, header: string}>
     */
    public static function layoutColonne(AziendaConfig $azienda): array
    {
        $layout = [];
        $portate = [
            [PortataType::Primo, 'PRIMO'],
            [PortataType::Secondo, 'SECONDO'],
            [PortataType::Contorno, 'CONTORNO'],
            [PortataType::Frutta, 'FRUTTA'],
            [PortataType::Yogurt, 'YOGURT'],
            [PortataType::Dessert, 'DESSERT'],
            [PortataType::Bevanda, 'BEVANDE'],
        ];
        foreach ($portate as [$categoria, $base]) {
            $quanti = $categoria === PortataType::Bevanda
                ? max(1, $azienda->bevandaObbligatoria ? 1 : 1)
                : max(1, $azienda->maxPerCategoria($categoria));
            if ($categoria === PortataType::Bevanda && !$azienda->bevandaObbligatoria) {
                $quanti = 1;
            }
            for ($slot = 0; $slot < $quanti; $slot++) {
                $header = $slot === 0 ? $base : ($base === 'BEVANDE' ? 'BEVANDE' . ($slot + 1) : $base . ($slot + 1));
                $layout[] = [
                    'categoria' => $categoria->value,
                    'slot' => $slot,
                    'header' => $header,
                ];
            }
        }

        return $layout;
    }

    /**
     * @param list<array<string, mixed>> $voci
     * @param list<array{categoria: string, slot: int, header: string}> $layout
     * @return list<string>
     */
    public static function valoriCelle(array $voci, array $layout): array
    {
        $valori = [];
        foreach ($layout as $colonna) {
            $valori[] = self::cellaPortata($voci, $colonna['categoria'], $colonna['slot']);
        }

        return $valori;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materializza(int $aziendaId, string $data): array
    {
        $azienda = AziendaConfig::trova($this->pdo, $aziendaId);
        $layout = self::layoutColonne($azienda);
        $intestazione = array_column($layout, 'header');
        $cucina = new KitchenSummary($this->pdo);
        $riepilogo = $cucina->perGiorno($aziendaId, $data);
        $quando = \DateTimeImmutable::createFromFormat('!Y-m-d', $riepilogo['data']);
        $giorno = $quando instanceof \DateTimeImmutable
            ? self::giornoSettimana((int) $quando->format('N'))
            : '';

        $perOrdine = [];
        foreach ($riepilogo['buoni_righe'] as $riga) {
            $perOrdine[$riga['ordine_id']][] = $riga;
        }

        $righe = [];
        foreach ($perOrdine as $voci) {
            $prima = $voci[0];
            $istante = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $prima['creato_il']);
            $celle = self::valoriCelle($voci, $layout);
            $righe[] = [
                'badge' => $prima['matricola'],
                'employee' => $prima['ospite'] !== '' ? $prima['ospite'] : $prima['dipendente'],
                'giorno' => $giorno,
                'time_stamp' => $istante instanceof \DateTimeImmutable
                    ? $istante->format('d/m/Y H:i')
                    : $prima['creato_il'],
                'payment' => $prima['a_carico'] === 'dipendente' ? 'P' : '',
                'allergie' => $prima['nota_cucina'],
                'intestazione' => $intestazione,
                'celle' => $celle,
            ];
        }

        $this->pdo->prepare(
            'DELETE FROM export_cucina WHERE azienda_id = :azienda AND data_pasto = :data'
        )->execute(['azienda' => $aziendaId, 'data' => $riepilogo['data']]);

        $inserisci = $this->pdo->prepare(
            'INSERT INTO export_cucina
                (azienda_id, data_pasto, badge, employee, giorno, time_stamp, payment, allergie, intestazione_json, celle_json, aggiornato_il)
             VALUES
                (:azienda, :data, :badge, :employee, :giorno, :time_stamp, :payment, :allergie, :intestazione, :celle, NOW())'
        );
        foreach ($righe as $riga) {
            $inserisci->execute([
                'azienda' => $aziendaId,
                'data' => $riepilogo['data'],
                'badge' => $riga['badge'],
                'employee' => $riga['employee'],
                'giorno' => $riga['giorno'],
                'time_stamp' => $riga['time_stamp'],
                'payment' => $riga['payment'],
                'allergie' => $riga['allergie'],
                'intestazione' => json_encode($riga['intestazione'], JSON_UNESCAPED_UNICODE),
                'celle' => json_encode($riga['celle'], JSON_UNESCAPED_UNICODE),
            ]);
        }

        return $righe;
    }

    public function xls(int $aziendaId, string $nomeAzienda, string $data): string
    {
        return $this->documento($aziendaId, $nomeAzienda, $data, 'xls');
    }

    public function csv(int $aziendaId, string $nomeAzienda, string $data): string
    {
        return $this->documento($aziendaId, $nomeAzienda, $data, 'csv');
    }

    private function documento(int $aziendaId, string $nomeAzienda, string $data, string $formato): string
    {
        $azienda = AziendaConfig::trova($this->pdo, $aziendaId);
        $layout = self::layoutColonne($azienda);
        $righeExport = $this->materializza($aziendaId, $data);
        $cucina = new KitchenSummary($this->pdo);
        $riepilogo = $cucina->perGiorno($aziendaId, $data);
        $quando = \DateTimeImmutable::createFromFormat('!Y-m-d', $riepilogo['data']);
        $giorno = $quando instanceof \DateTimeImmutable
            ? self::giornoSettimana((int) $quando->format('N'))
            : '';
        $titolo = 'PRENOTAZIONE MENSA ' . strtoupper($giorno) . ' '
            . ($quando instanceof \DateTimeImmutable ? $quando->format('d/m/Y') : $riepilogo['data'])
            . ' TOT. ' . $riepilogo['buoni'];

        $foglio = [];
        $foglio[] = [$titolo];
        $foglio[] = ['azienda', $nomeAzienda];
        $foglio[] = [];
        $foglio[] = ['CONTEGGI'];
        $foglio[] = ['lettera', 'fascia', 'categoria', 'codice', 'piatto', 'porzioni'];
        foreach ($riepilogo['porzioni'] as $porzione) {
            $foglio[] = [
                $porzione['lettera'],
                $porzione['fascia'],
                $porzione['categoria'],
                $porzione['codice'],
                $porzione['nome'],
                (string) $porzione['porzioni'],
            ];
        }
        $foglio[] = [];
        $intestazione = array_merge(
            ['BADGE', 'EMPLOYEE'],
            array_column($layout, 'header'),
            ['GIORNO', 'TIME_STAMP', 'PAYMENT', 'ALLERGIE'],
        );
        $foglio[] = $intestazione;
        foreach ($righeExport as $riga) {
            $foglio[] = array_merge(
                [$riga['badge'], $riga['employee']],
                $riga['celle'],
                [$riga['giorno'], $riga['time_stamp'], $riga['payment'], $riga['allergie']],
            );
        }

        return $formato === 'xls' ? $this->spreadsheetMl($foglio) : $this->csvTesto($foglio);
    }

    /**
     * @param list<array<string, mixed>> $voci
     */
    public static function cellaPortata(array $voci, string $categoria, int $slot): string
    {
        $perLettera = [];
        foreach ($voci as $voce) {
            if ($voce['categoria'] !== $categoria) {
                continue;
            }
            $lettera = $voce['lettera'] !== '' ? $voce['lettera'] : $voce['codice'];
            $perLettera[$lettera] = ($perLettera[$lettera] ?? 0) + 1;
        }
        $ordine = array_flip(LetteraPasto::ALFABETO);
        uksort($perLettera, static function (string $a, string $b) use ($ordine): int {
            return ($ordine[$a] ?? 99) <=> ($ordine[$b] ?? 99);
        });
        $blocchi = [];
        foreach ($perLettera as $lettera => $quantita) {
            $blocchi[] = $quantita > 1 ? $lettera . 'x' . $quantita : $lettera;
        }

        return $blocchi[$slot] ?? '';
    }

    public static function giornoSettimana(int $numero): string
    {
        return [1 => 'lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'][$numero] ?? '';
    }

    /** @param list<list<string>> $foglio */
    private function csvTesto(array $foglio): string
    {
        $csv = "\xEF\xBB\xBF";
        foreach ($foglio as $riga) {
            $csv .= implode(';', array_map($this->cellaCsv(...), $riga)) . "\r\n";
        }

        return $csv;
    }

    private function cellaCsv(string $valore): string
    {
        if (str_contains($valore, ';') || str_contains($valore, '"') || str_contains($valore, "\n")) {
            return '"' . str_replace('"', '""', $valore) . '"';
        }

        return $valore;
    }

    /** @param list<list<string>> $foglio */
    private function spreadsheetMl(array $foglio): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\r\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\r\n";
        $xml .= '<Worksheet ss:Name="Cucina"><Table>' . "\r\n";
        foreach ($foglio as $riga) {
            $xml .= '<Row>';
            foreach ($riga as $cella) {
                $valore = htmlspecialchars((string) $cella, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $tipo = is_numeric($cella) && $cella !== '' && !str_contains((string) $cella, '/')
                    ? 'Number'
                    : 'String';
                $xml .= '<Cell><Data ss:Type="' . $tipo . '">' . $valore . '</Data></Cell>';
            }
            $xml .= '</Row>' . "\r\n";
        }
        $xml .= '</Table></Worksheet></Workbook>';

        return $xml;
    }
}
