<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use DateTimeImmutable;
use MenuCraft\Enums\PortataType;
use MenuCraft\Models\AziendaConfig;
use MenuCraft\Support\CodicePiatto;
use MenuCraft\Support\Money;
use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class BookingBoard
{
    /** @var array<int, string> */
    private const GIORNI = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Gio',
        5 => 'Ven',
        6 => 'Sab',
        7 => 'Dom',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly MenuValidator $validator,
        private readonly OrderService $ordini,
    ) {
    }

    /** @return array<string, mixed> */
    public function carica(int $aziendaId, int $utenteId, ?string $dataRichiesta, ?DateTimeImmutable $adesso = null): array
    {
        $azienda = AziendaConfig::trova($this->pdo, $aziendaId);
        $pagamento = $this->pagamentoEffettivo($azienda, $utenteId);
        $now = $this->validator->adesso($adesso);
        $oggi = $this->validator->giornoCivile($now);
        $lunedi = $oggi->modify('monday this week');
        $fine = $lunedi->modify('+11 days');
        $chiusure = $this->validator->chiusureNelPeriodo($aziendaId, $lunedi, $fine);
        $menu = $this->menuNelPeriodo($aziendaId, $lunedi, $fine);

        $settimane = [
            $this->settimana($azienda, $lunedi, 'Questa settimana', $chiusure, $menu, $now, $oggi),
            $this->settimana($azienda, $lunedi->modify('+7 days'), 'Settimana successiva', $chiusure, $menu, $now, $oggi),
        ];

        $focus = null;
        foreach ($settimane as $settimana) {
            foreach ($settimana['giorni'] as $giorno) {
                if ($giorno['selezionabile']) {
                    $focus = $giorno['data'];
                    break 2;
                }
            }
        }

        if ($dataRichiesta !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRichiesta) === 1) {
            $scelta = $dataRichiesta;
        } else {
            $scelta = $focus ?? $oggi->format('Y-m-d');
        }

        return [
            'azienda' => $azienda->toArray(),
            'regole' => [
                'max_scelte' => $azienda->maxScelte,
                'max_primo' => $azienda->maxPerCategoria(PortataType::Primo),
                'max_secondo' => $azienda->maxPerCategoria(PortataType::Secondo),
                'max_contorno' => $azienda->maxPerCategoria(PortataType::Contorno),
                'doppio_primo' => $azienda->permettiDoppioPrimo,
                'doppio_secondo' => $azienda->permettiDoppioSecondo,
                'doppio_contorno' => $azienda->permettiDoppioContorno,
                'bevanda_obbligatoria' => $azienda->bevandaObbligatoria,
                'max_altre' => $azienda->maxScelte,
                'pagamento_effettivo' => $pagamento,
            ],
            'allergie' => $this->allergieUtente($aziendaId, $utenteId),
            'saldo' => $pagamento === 'dipendente' ? $this->ordini->saldo($azienda, $utenteId, true) : null,
            'portafoglio' => $this->ordini->saldo($azienda, $utenteId, true),
            'settimane' => $settimane,
            'focus' => $focus,
            'giorno' => $this->dettaglio($azienda, $utenteId, $scelta, $chiusure, $menu, $now, $oggi),
            'caricamento' => $this->statoCaricamento($now),
        ];
    }

    /**
     * @param array<string, string> $chiusure
     * @param array<string, array{visibile_dal: string, voci: int}> $menu
     * @return array<string, mixed>
     */
    private function settimana(
        AziendaConfig $azienda,
        DateTimeImmutable $lunedi,
        string $titolo,
        array $chiusure,
        array $menu,
        DateTimeImmutable $now,
        DateTimeImmutable $oggi,
    ): array {
        $giorni = [];
        for ($i = 0; $i < 5; $i++) {
            $giorni[] = $this->descriviGiorno($azienda, $lunedi->modify('+' . $i . ' days'), $chiusure, $menu, $now, $oggi);
        }

        $lunediSaltato = $giorni[0]['saltato'] === true;
        $ancora = $giorni[0]['data'];
        if ($lunediSaltato) {
            $ancora = $giorni[1]['data'];
        } else {
            foreach ($giorni as $giorno) {
                if ($giorno['selezionabile']) {
                    $ancora = $giorno['data'];
                    break;
                }
            }
        }

        return [
            'titolo' => $titolo,
            'lunedi_saltato' => $lunediSaltato,
            'ancora' => $ancora,
            'avviso' => $lunediSaltato
                ? 'Lunedì è chiuso: la settimana si prenota da martedì.'
                : null,
            'giorni' => $giorni,
        ];
    }

    /**
     * @param array<string, string> $chiusure
     * @param array<string, array{visibile_dal: string, voci: int}> $menu
     * @return array<string, mixed>
     */
    private function descriviGiorno(
        AziendaConfig $azienda,
        DateTimeImmutable $data,
        array $chiusure,
        array $menu,
        DateTimeImmutable $now,
        DateTimeImmutable $oggi,
    ): array {
        $chiave = $data->format('Y-m-d');
        $chiusura = $this->validator->motivoCalendario($azienda->id, $data, $chiusure);
        $info = $menu[$chiave] ?? null;
        $motivo = $chiusura;
        $selezionabile = false;

        if ($motivo === null && $chiave < $oggi->format('Y-m-d')) {
            $motivo = 'Data già trascorsa.';
        }
        if ($motivo === null && $info === null) {
            $motivo = 'Menu non ancora pubblicato.';
        }
        if ($motivo === null && $info['visibile_dal'] > $now->format('Y-m-d H:i:s')) {
            $motivo = 'Le prenotazioni aprono venerdì alle 07:00.';
        }
        if ($motivo === null) {
            try {
                $this->validator->validaCutoff($azienda, $data, $now);
                $selezionabile = true;
            } catch (MenuRuleException $e) {
                $motivo = $e->getMessage();
            }
        }

        $numero = (int) $data->format('N');

        return [
            'data' => $chiave,
            'etichetta' => self::GIORNI[$numero],
            'numero' => $data->format('j'),
            'saltato' => $chiusura !== null,
            'selezionabile' => $selezionabile,
            'motivo' => $motivo,
        ];
    }

    /**
     * @param array<string, string> $chiusure
     * @param array<string, array{visibile_dal: string, voci: int}> $menu
     * @return array<string, mixed>
     */
    private function dettaglio(
        AziendaConfig $azienda,
        int $utenteId,
        string $data,
        array $chiusure,
        array $menu,
        DateTimeImmutable $now,
        DateTimeImmutable $oggi,
    ): array {
        $parsata = DateTimeImmutable::createFromFormat('!Y-m-d', $data, $this->validator->fuso());
        if ($parsata === false || $parsata->format('Y-m-d') !== $data) {
            throw new MenuRuleException('DATA_NON_VALIDA', 'La data richiesta non è valida.');
        }

        $inizio = $oggi->modify('monday this week')->format('Y-m-d');
        $limite = $oggi->modify('monday this week')->modify('+11 days')->format('Y-m-d');
        if ($data < $inizio || $data > $limite) {
            $chiusure = $this->validator->chiusureNelPeriodo($azienda->id, $parsata, $parsata);
            $menu = $this->menuNelPeriodo($azienda->id, $parsata, $parsata);
        }

        $descrizione = $this->descriviGiorno($azienda, $parsata, $chiusure, $menu, $now, $oggi);
        $piatti = [];
        if ($descrizione['motivo'] === null || $descrizione['selezionabile'] === true) {
            $piatti = $this->piatti($azienda, $parsata, $now);
        } elseif (($menu[$data]['visibile_dal'] ?? '9999') <= $now->format('Y-m-d H:i:s') && $descrizione['saltato'] !== true) {
            $piatti = $this->piatti($azienda, $parsata, $now);
        }

        return [
            'data' => $data,
            'etichetta' => $descrizione['etichetta'],
            'numero' => $descrizione['numero'],
            'selezionabile' => $descrizione['selezionabile'],
            'saltato' => $descrizione['saltato'],
            'motivo' => $descrizione['motivo'],
            'portate' => $piatti,
            'ordine' => $this->ordineEsistente($azienda->id, $utenteId, $data, false),
            'ordine_ospite' => $this->ordineEsistente($azienda->id, $utenteId, $data, true),
        ];
    }

    private function allergieUtente(int $aziendaId, int $utenteId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT allergie FROM utenti WHERE id = :id AND azienda_id = :azienda'
        );
        $stmt->execute(['id' => $utenteId, 'azienda' => $aziendaId]);
        $testo = $stmt->fetchColumn();

        return is_string($testo) ? trim($testo) : '';
    }

    private function pagamentoEffettivo(AziendaConfig $azienda, int $utenteId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT pagamento FROM utenti WHERE id = :id AND azienda_id = :azienda'
        );
        $stmt->execute(['id' => $utenteId, 'azienda' => $azienda->id]);
        $pagamento = (string) $stmt->fetchColumn();

        return match ($pagamento) {
            'welfare' => 'welfare',
            'pagante' => 'dipendente',
            default => $azienda->tipoPagamento->value,
        };
    }

    /** @return list<array<string, mixed>> */
    private function piatti(AziendaConfig $azienda, DateTimeImmutable $data, DateTimeImmutable $now): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.codice, p.nome, p.categoria, p.prezzo
             FROM menu_voci v
             INNER JOIN piatti p
                ON p.id = v.piatto_id
               AND p.azienda_id = v.azienda_id
             INNER JOIN menu_pubblicazioni pub
                ON pub.id = v.pubblicazione_id
               AND pub.azienda_id = v.azienda_id
             WHERE v.azienda_id = :azienda
               AND v.data_pasto = :data
               AND pub.visibile_dal <= :adesso
             ORDER BY p.nome'
        );
        $stmt->execute([
            'azienda' => $azienda->id,
            'data' => $data->format('Y-m-d'),
            'adesso' => $now->format('Y-m-d H:i:s'),
        ]);

        $gruppi = [];
        foreach ($stmt as $riga) {
            $categoria = PortataType::from((string) $riga['categoria']);
            $gruppi[$categoria->value][] = [
                'id' => (int) $riga['id'],
                'codice' => CodicePiatto::etichetta((string) $riga['codice']),
                'nome' => (string) $riga['nome'],
                'categoria' => $categoria->value,
                'prezzo' => $azienda->prezzoApplicato((string) $riga['prezzo']),
                'prezzo_listino' => Money::normalizza((string) $riga['prezzo']),
            ];
        }

        $portate = [];
        foreach (PortataType::cases() as $categoria) {
            if (!isset($gruppi[$categoria->value])) {
                continue;
            }
            $portate[] = [
                'categoria' => $categoria->value,
                'etichetta' => $categoria->etichetta(),
                'massimo' => $azienda->maxPerCategoria($categoria),
                'piatti' => $gruppi[$categoria->value],
            ];
        }

        return $portate;
    }

    /** @return array<string, mixed>|null */
    private function ordineEsistente(int $aziendaId, int $utenteId, string $data, bool $ospite): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.id, o.totale, o.ospite_nome, o.a_carico, r.piatto_id, r.nome, r.categoria, r.prezzo_applicato
             FROM ordini o
             INNER JOIN ordine_righe r
                ON r.ordine_id = o.id
               AND r.azienda_id = o.azienda_id
             WHERE o.azienda_id = :azienda
               AND o.utente_id = :utente
               AND o.data_pasto = :data
               AND o.ospite = :ospite
             ORDER BY r.id'
        );
        $stmt->execute([
            'azienda' => $aziendaId,
            'utente' => $utenteId,
            'data' => $data,
            'ospite' => $ospite ? 1 : 0,
        ]);
        $righe = $stmt->fetchAll();
        if ($righe === []) {
            return null;
        }

        $piatti = [];
        foreach ($righe as $riga) {
            $piatti[] = [
                'id' => (int) $riga['piatto_id'],
                'nome' => (string) $riga['nome'],
                'categoria' => (string) $riga['categoria'],
                'prezzo' => (string) $riga['prezzo_applicato'],
            ];
        }

        return [
            'id' => (int) $righe[0]['id'],
            'totale' => (string) $righe[0]['totale'],
            'ospite_nome' => $righe[0]['ospite_nome'] !== null ? (string) $righe[0]['ospite_nome'] : null,
            'a_carico' => (string) $righe[0]['a_carico'],
            'piatti' => $piatti,
        ];
    }

    /** @return array<string, array{visibile_dal: string, voci: int}> */
    private function menuNelPeriodo(int $aziendaId, DateTimeImmutable $dal, DateTimeImmutable $al): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.data_pasto, MIN(pub.visibile_dal) AS visibile_dal, COUNT(*) AS voci
             FROM menu_voci v
             INNER JOIN menu_pubblicazioni pub
                ON pub.id = v.pubblicazione_id
               AND pub.azienda_id = v.azienda_id
             WHERE v.azienda_id = :azienda
               AND v.data_pasto BETWEEN :dal AND :al
             GROUP BY v.data_pasto'
        );
        $stmt->execute([
            'azienda' => $aziendaId,
            'dal' => $dal->format('Y-m-d'),
            'al' => $al->format('Y-m-d'),
        ]);

        $menu = [];
        foreach ($stmt as $riga) {
            $menu[(string) $riga['data_pasto']] = [
                'visibile_dal' => (string) $riga['visibile_dal'],
                'voci' => (int) $riga['voci'],
            ];
        }

        return $menu;
    }

    /** @return array{aperto: bool, messaggio: string} */
    private function statoCaricamento(DateTimeImmutable $now): array
    {
        try {
            $finestra = $this->validator->finestraPubblicazione($now);

            return [
                'aperto' => true,
                'messaggio' => sprintf(
                    'Finestra aperta. Puoi caricare il menu dal %s al %s. I dipendenti prenotano da venerdì alle 07:00.',
                    $finestra['dal']->format('d/m/Y'),
                    $finestra['al']->format('d/m/Y'),
                ),
            ];
        } catch (MenuRuleException $e) {
            return [
                'aperto' => false,
                'messaggio' => $e->getMessage(),
            ];
        }
    }
}
