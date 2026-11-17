<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

/**
 * Contratto stabile per l'applicazione esterna (VB.NET / Excel):
 * tabella export_cucina con le stesse colonne del file cucina,
 * più viste piatte se serve unire i dati a mano.
 */
final class ExportCucina
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Riempie export_cucina per azienda+giorno con le lettere del pasto
     * (non i codici catalogo), così VB.NET può fare SELECT * ... e creare l'XLS.
     *
     * @return list<array<string, string>>
     */
    public function materializza(int $aziendaId, string $data): array
    {
        $cucina = new KitchenSummary($this->pdo);
        $riepilogo = $cucina->perGiorno($aziendaId, $data);
        $quando = \DateTimeImmutable::createFromFormat('!Y-m-d', $riepilogo['data']);
        $giorno = $quando instanceof \DateTimeImmutable
            ? $this->giornoSettimana((int) $quando->format('N'))
            : '';

        $perOrdine = [];
        foreach ($riepilogo['buoni_righe'] as $riga) {
            $perOrdine[$riga['ordine_id']][] = $riga;
        }

        $righe = [];
        foreach ($perOrdine as $voci) {
            $prima = $voci[0];
            $istante = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $prima['creato_il']);
            $righe[] = [
                'badge' => $prima['matricola'],
                'employee' => $prima['ospite'] !== '' ? $prima['ospite'] : $prima['dipendente'],
                'primo' => $this->cellaPortata($voci, 'primo', 0),
                'secondo' => $this->cellaPortata($voci, 'secondo', 0),
                'contorno1' => $this->cellaPortata($voci, 'contorno', 0),
                'contorno2' => $this->cellaPortata($voci, 'contorno', 1),
                'frutta' => $this->cellaPortata($voci, 'frutta', 0),
                'yogurt' => $this->cellaPortata($voci, 'yogurt', 0),
                'dessert' => $this->cellaPortata($voci, 'dessert', 0),
                'bevande' => $this->cellaPortata($voci, 'bevanda', 0),
                'bevande2' => $this->cellaPortata($voci, 'bevanda', 1),
                'giorno' => $giorno,
                'time_stamp' => $istante instanceof \DateTimeImmutable
                    ? $istante->format('d/m/Y H:i')
                    : $prima['creato_il'],
                'payment' => $prima['a_carico'] === 'dipendente' ? 'P' : '',
                'allergie' => $prima['nota_cucina'],
            ];
        }

        $this->pdo->prepare(
            'DELETE FROM export_cucina WHERE azienda_id = :azienda AND data_pasto = :data'
        )->execute(['azienda' => $aziendaId, 'data' => $riepilogo['data']]);

        $inserisci = $this->pdo->prepare(
            'INSERT INTO export_cucina
                (azienda_id, data_pasto, badge, employee, primo, secondo, contorno1, contorno2,
                 frutta, yogurt, dessert, bevande, bevande2, giorno, time_stamp, payment, allergie, aggiornato_il)
             VALUES
                (:azienda, :data, :badge, :employee, :primo, :secondo, :contorno1, :contorno2,
                 :frutta, :yogurt, :dessert, :bevande, :bevande2, :giorno, :time_stamp, :payment, :allergie, NOW())'
        );
        foreach ($righe as $riga) {
            $inserisci->execute([
                'azienda' => $aziendaId,
                'data' => $riepilogo['data'],
                'badge' => $riga['badge'],
                'employee' => $riga['employee'],
                'primo' => $riga['primo'],
                'secondo' => $riga['secondo'],
                'contorno1' => $riga['contorno1'],
                'contorno2' => $riga['contorno2'],
                'frutta' => $riga['frutta'],
                'yogurt' => $riga['yogurt'],
                'dessert' => $riga['dessert'],
                'bevande' => $riga['bevande'],
                'bevande2' => $riga['bevande2'],
                'giorno' => $riga['giorno'],
                'time_stamp' => $riga['time_stamp'],
                'payment' => $riga['payment'],
                'allergie' => $riga['allergie'],
            ]);
        }

        return $righe;
    }

    public function xls(int $aziendaId, string $nomeAzienda, string $data): string
    {
        $righeExport = $this->materializza($aziendaId, $data);
        $cucina = new KitchenSummary($this->pdo);
        $riepilogo = $cucina->perGiorno($aziendaId, $data);
        $quando = \DateTimeImmutable::createFromFormat('!Y-m-d', $riepilogo['data']);
        $giorno = $quando instanceof \DateTimeImmutable
            ? $this->giornoSettimana((int) $quando->format('N'))
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
        $foglio[] = [
            'BADGE', 'EMPLOYEE', 'PRIMO', 'SECONDO', 'CONTORNO', 'CONTORNO',
            'FRUTTA', 'YOGURT', 'DESSERT', 'BEVANDE', 'BEVANDE2', 'GIORNO', 'TIME_STAMP', 'PAYMENT', 'ALLERGIE',
        ];
        foreach ($righeExport as $riga) {
            $foglio[] = [
                $riga['badge'],
                $riga['employee'],
                $riga['primo'],
                $riga['secondo'],
                $riga['contorno1'],
                $riga['contorno2'],
                $riga['frutta'],
                $riga['yogurt'],
                $riga['dessert'],
                $riga['bevande'],
                $riga['bevande2'],
                $riga['giorno'],
                $riga['time_stamp'],
                $riga['payment'],
                $riga['allergie'],
            ];
        }

        return $this->spreadsheetMl($foglio);
    }

    /**
     * @param list<array<string, mixed>> $voci
     */
    private function cellaPortata(array $voci, string $categoria, int $slot): string
    {
        $perLettera = [];
        foreach ($voci as $voce) {
            if ($voce['categoria'] !== $categoria) {
                continue;
            }
            $lettera = $voce['lettera'] !== '' ? $voce['lettera'] : $voce['codice'];
            $perLettera[$lettera] = ($perLettera[$lettera] ?? 0) + 1;
        }
        $ordine = array_flip(\MenuCraft\Support\LetteraPasto::ALFABETO);
        uksort($perLettera, static function (string $a, string $b) use ($ordine): int {
            return ($ordine[$a] ?? 99) <=> ($ordine[$b] ?? 99);
        });
        $blocchi = [];
        foreach ($perLettera as $lettera => $quantita) {
            $blocchi[] = $quantita > 1 ? $lettera . 'x' . $quantita : $lettera;
        }
        if ($categoria === 'contorno' || $categoria === 'bevanda') {
            return $blocchi[$slot] ?? '';
        }

        return $slot === 0 ? implode(' ', $blocchi) : '';
    }

    private function giornoSettimana(int $numero): string
    {
        return [1 => 'lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'][$numero] ?? '';
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
