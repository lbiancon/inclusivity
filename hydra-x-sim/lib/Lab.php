<?php
declare(strict_types=1);

final class Lab
{
    /** @var list<string> */
    private array $effects = [];

    private function __construct(
        private readonly PDO $pdo,
        private readonly string $driver
    ) {
    }

    /** @param array<string, mixed> $config */
    public static function open(array $config): self
    {
        $driver = (string) ($config['driver'] ?? 'sqlite');
        if ($driver === 'sqlite') {
            $path = (string) ($config['sqlite_path'] ?? '');
            if ($path === '') {
                throw new LabException('Percorso SQLite mancante.', 500);
            }
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new LabException('Cartella dati non creabile.', 500);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $lab = new self($pdo, 'sqlite');
            $lab->ensureSqlite();
            $lab->seedIfEmpty();
            return $lab;
        }
        if ($driver !== 'oci') {
            throw new LabException('Driver sconosciuto. Usa sqlite oppure oci.', 500);
        }
        if (!extension_loaded('pdo_oci')) {
            throw new LabException(
                'Estensione pdo_oci assente. Il banco parte in SQLite finché PHP non ha il driver Oracle (Instant Client + pdo_oci).',
                500
            );
        }
        try {
            $pdo = new PDO(
                (string) $config['oracle_dsn'],
                (string) $config['oracle_user'],
                (string) $config['oracle_password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            throw new LabException('Connessione Oracle fallita: ' . $e->getMessage(), 500);
        }
        $lab = new self($pdo, 'oci');
        try {
            $lab->rows('SELECT COUNT(*) AS n FROM hxs_workplace');
        } catch (PDOException) {
            throw new LabException(
                'Schema Oracle assente. Esegui sql/oracle.sql con l\'utente dedicato HXSIM, non nello schema HYDRA.',
                500
            );
        }
        $lab->seedIfEmpty();
        return $lab;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return [
            'app' => 'Banco X',
            'driver' => $this->driver,
            'php' => PHP_VERSION,
            'pdo_oci' => extension_loaded('pdo_oci'),
            'timezone' => 'Europe/Rome',
            'counts' => [
                'workplaces' => $this->count('hxs_workplace'),
                'orders' => $this->count('hxs_order'),
                'operations' => $this->count('hxs_operation'),
                'bookings' => $this->count('hxs_booking'),
                'downtimes' => $this->count('hxs_downtime'),
                'signals' => $this->count('hxs_signal'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function model(): array
    {
        return [
            'name' => 'Modello di laboratorio',
            'disclaimer' => 'Non è il Virtual Production Reality di MPDV. I nomi HYDRA, MIP e ViPR restano del prodotto.',
            'objects' => [
                [
                    'type' => 'workplace',
                    'title' => 'Postazione',
                    'meaning' => 'Macchina o banco. Lo stato (ferma, in lavoro, in fermo) è un dato del modello, non un colore disegnato sulla maschera.',
                ],
                [
                    'type' => 'person',
                    'title' => 'Persona',
                    'meaning' => 'Operatore che firma avvio e versamenti.',
                ],
                [
                    'type' => 'order',
                    'title' => 'Ordine',
                    'meaning' => 'Ordine di produzione con materiale e quantità prevista.',
                ],
                [
                    'type' => 'operation',
                    'title' => 'Operazione',
                    'meaning' => 'Fase dell\'ordine legata a una postazione. È l\'oggetto su cui agiscono i servizi di raccolta.',
                ],
                [
                    'type' => 'booking',
                    'title' => 'Versamento',
                    'meaning' => 'Pezzi buoni o scarto. Il significato del numero sta nel campo kind, non nel nome di una colonna libera.',
                ],
                [
                    'type' => 'downtime',
                    'title' => 'Fermo',
                    'meaning' => 'Intervallo in cui la postazione non lavora. Un fermo aperto mette l\'operazione in held.',
                ],
                [
                    'type' => 'signal',
                    'title' => 'Segnale',
                    'meaning' => 'Ingresso macchina. Non scrive le tabelle di versamento: richiama la stessa logica dei servizi.',
                ],
            ],
            'services' => self::catalog(),
            'scrapReasons' => self::scrapReasons(),
            'downtimeReasons' => self::downtimeReasons(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function catalog(): array
    {
        return [
            [
                'name' => 'operation.start',
                'title' => 'Avvia operazione',
                'from8' => 'Nel MOC è il dialogo di inizio lavorazione. Qui è un servizio: Terminale e Monitor leggono lo stesso effetto.',
                'example' => ['operationCode' => 'OF-24081-10', 'operatorCode' => 'op.01'],
            ],
            [
                'name' => 'operation.book',
                'title' => 'Versa pezzi',
                'from8' => 'Nel MOC è la dichiarazione di quantità o scarto. Il servizio rifiuta lo scarto senza causale e rifiuta il versamento se la fase è ferma.',
                'example' => ['operationCode' => 'OF-24081-10', 'operatorCode' => 'op.01', 'kind' => 'good', 'qty' => 5],
            ],
            [
                'name' => 'operation.finish',
                'title' => 'Chiudi operazione',
                'from8' => 'Chiusura fase. Se tutte le fasi dell\'ordine sono chiuse, l\'ordine passa a done. Nessuna mApp ricalcola per conto suo.',
                'example' => ['operationCode' => 'OF-24081-10'],
            ],
            [
                'name' => 'downtime.start',
                'title' => 'Apri fermo',
                'from8' => 'Causale di fermo. Se c\'è una fase in corso, diventa held: il versamento si blocca finché il fermo non si chiude.',
                'example' => ['workplaceCode' => 'TORN-01', 'reasonCode' => 'guasto'],
            ],
            [
                'name' => 'downtime.end',
                'title' => 'Chiudi fermo',
                'from8' => 'Fine fermo. La fase held torna running e la postazione pure.',
                'example' => ['workplaceCode' => 'TORN-01'],
            ],
            [
                'name' => 'edge.signal',
                'title' => 'Segnale macchina',
                'from8' => 'In HYDRA 8 il canale è il PCC. In HYDRA X il corso corrispondente è DT-DEC, driver in C#. Il segnale non salta il servizio.',
                'example' => ['workplaceCode' => 'TORN-01', 'signal' => 'piece_good', 'value' => '1'],
            ],
            [
                'name' => 'lab.demo',
                'title' => 'Carica un turno',
                'from8' => 'Solo laboratorio. Parte dai dati anagrafici puliti e lascia un ordine a metà, così Monitor e Oggetti hanno movimenti.',
                'example' => ['confirm' => 'DEMO'],
            ],
            [
                'name' => 'lab.reset',
                'title' => 'Ripristina anagrafiche',
                'from8' => 'Cancella i movimenti del banco e rimette i due ordini di esempio. Non tocca altri schemi.',
                'example' => ['confirm' => 'RESET'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function workplaces(): array
    {
        $rows = $this->rows('SELECT * FROM hxs_workplace ORDER BY code');
        return array_map(fn (array $row): array => $this->workplaceView((int) $row['id']), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function orders(): array
    {
        $rows = $this->rows('SELECT id FROM hxs_order ORDER BY code');
        return array_map(fn (array $row): array => $this->orderView((int) $row['id']), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function people(): array
    {
        return array_map(
            fn (array $row): array => $this->ints($row, ['id']),
            $this->rows('SELECT id, code, name, role FROM hxs_person ORDER BY code')
        );
    }

    /** @return list<array<string, mixed>> */
    public function operations(?string $workplaceCode = null): array
    {
        if ($workplaceCode === null || $workplaceCode === '') {
            $ids = $this->rows('SELECT id FROM hxs_operation ORDER BY code');
        } else {
            $ids = $this->rows(
                'SELECT o.id FROM hxs_operation o JOIN hxs_workplace w ON w.id = o.workplace_id WHERE w.code = ? ORDER BY o.seq, o.code',
                [$workplaceCode]
            );
        }
        return array_map(fn (array $row): array => $this->operationView((int) $row['id']), $ids);
    }

    /** @return array<string, mixed> */
    public function monitor(): array
    {
        $workplaces = $this->workplaces();
        $orders = $this->orders();
        $good = 0;
        $scrap = 0;
        $running = 0;
        $down = 0;
        foreach ($workplaces as $workplace) {
            $good += (int) $workplace['qtyGoodToday'];
            $scrap += (int) $workplace['qtyScrapToday'];
            if ($workplace['state'] === 'running') {
                $running++;
            }
            if ($workplace['state'] === 'down') {
                $down++;
            }
        }
        $openOrders = 0;
        foreach ($orders as $order) {
            if ($order['status'] !== 'done') {
                $openOrders++;
            }
        }
        return [
            'day' => $this->today(),
            'totals' => [
                'goodToday' => $good,
                'scrapToday' => $scrap,
                'running' => $running,
                'down' => $down,
                'openOrders' => $openOrders,
            ],
            'workplaces' => $workplaces,
            'orders' => $orders,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function log(): array
    {
        $rows = $this->rows('SELECT * FROM hxs_service_log ORDER BY id DESC');
        $rows = array_slice($rows, 0, 40);
        return array_map(function (array $row): array {
            $row = $this->ints($row, ['id', 'ok']);
            $row['request'] = json_decode((string) $row['request_json'], true);
            $row['response'] = json_decode((string) $row['response_json'], true);
            unset($row['request_json'], $row['response_json']);
            return $row;
        }, $rows);
    }

    /** @return array<string, mixed> */
    public function objectList(string $type): array
    {
        $allowed = ['workplace', 'person', 'order', 'operation', 'booking', 'downtime', 'signal'];
        if (!in_array($type, $allowed, true)) {
            throw new LabException('Tipo oggetto sconosciuto.', 404);
        }
        $meaning = '';
        foreach ($this->model()['objects'] as $object) {
            if ($object['type'] === $type) {
                $meaning = $object['meaning'];
            }
        }
        $rows = match ($type) {
            'workplace' => $this->workplaces(),
            'person' => $this->people(),
            'order' => $this->orders(),
            'operation' => $this->operations(),
            'booking' => $this->bookingRows(),
            'downtime' => $this->downtimeRows(),
            'signal' => $this->signalRows(),
        };
        return ['type' => $type, 'meaning' => $meaning, 'rows' => $rows];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function call(string $name, array $body): array
    {
        $known = array_column(self::catalog(), 'name');
        if (!in_array($name, $known, true)) {
            throw new LabException('Servizio sconosciuto: ' . $name, 404);
        }
        $this->effects = [];
        $this->pdo->beginTransaction();
        try {
            $payload = $this->dispatch($name, $body);
            $this->pdo->commit();
        } catch (LabException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->writeLog($name, $body, ['error' => $e->getMessage()], false);
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->writeLog($name, $body, ['error' => 'Errore interno'], false);
            throw new LabException('Errore interno del servizio.', 500, $e);
        }
        $payload['service'] = $name;
        $payload['effects'] = $this->effects;
        $this->writeLog($name, $body, $payload, true);
        return $payload;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function dispatch(string $name, array $body): array
    {
        return match ($name) {
            'operation.start' => $this->startOperation($body),
            'operation.book' => $this->bookOperation($body),
            'operation.finish' => $this->finishOperation($body),
            'downtime.start' => $this->startDowntime($body),
            'downtime.end' => $this->endDowntime($body),
            'edge.signal' => $this->edgeSignal($body),
            'lab.demo' => $this->demo($body),
            'lab.reset' => $this->reset($body),
        };
    }

    /** @param array<string, mixed> $body */
    private function startOperation(array $body): array
    {
        $operation = $this->requireOperation($body);
        $operator = $this->requireOperator($body);
        if ($operation['status'] !== 'ready') {
            throw new LabException('Si avvia solo una fase in stato ready. Questa è ' . $operation['status'] . '.');
        }
        $workplace = $this->requireWorkplaceById((int) $operation['workplace_id']);
        if ($workplace['state'] !== 'idle') {
            throw new LabException('La postazione ' . $workplace['code'] . ' non è libera (' . $workplace['state'] . ').');
        }
        $now = $this->now();
        $this->exec(
            'UPDATE hxs_operation SET status = ?, started_at = ?, operator_id = ? WHERE id = ?',
            ['running', $now, (int) $operator['id'], (int) $operation['id']]
        );
        $this->exec('UPDATE hxs_workplace SET state = ? WHERE id = ?', ['running', (int) $workplace['id']]);
        $order = $this->requireOrderById((int) $operation['order_id']);
        if ($order['status'] === 'released') {
            $this->exec('UPDATE hxs_order SET status = ? WHERE id = ?', ['in_progress', (int) $order['id']]);
            $this->effect('Ordine ' . $order['code'] . ' passa a in_progress.');
        }
        $this->effect('Operazione ' . $operation['code'] . ' avviata da ' . $operator['code'] . '.');
        $this->effect('Postazione ' . $workplace['code'] . ' passa a running.');
        return [
            'operation' => $this->operationView((int) $operation['id']),
            'workplace' => $this->workplaceView((int) $workplace['id']),
            'order' => $this->orderView((int) $order['id']),
        ];
    }

    /** @param array<string, mixed> $body */
    private function bookOperation(array $body): array
    {
        $operation = $this->requireOperation($body);
        $operator = $this->requireOperator($body);
        if ($operation['status'] !== 'running') {
            throw new LabException('Si versa solo su una fase running. Questa è ' . $operation['status'] . '.');
        }
        $kind = (string) ($body['kind'] ?? '');
        if ($kind !== 'good' && $kind !== 'scrap') {
            throw new LabException('kind deve essere good oppure scrap.');
        }
        $qty = $this->qty($body['qty'] ?? null);
        $reason = null;
        if ($kind === 'scrap') {
            $reasonCode = (string) ($body['reason'] ?? '');
            $reasons = self::scrapReasons();
            if (!isset($reasons[$reasonCode])) {
                throw new LabException('Lo scarto richiede una causale: ' . implode(', ', array_keys($reasons)) . '.');
            }
            $reason = $reasons[$reasonCode];
        }
        $this->insert('hxs_booking', ['operation_id', 'kind', 'qty', 'reason', 'operator_id', 'created_at'], [
            (int) $operation['id'],
            $kind,
            $qty,
            $reason,
            (int) $operator['id'],
            $this->now(),
        ]);
        $view = $this->operationView((int) $operation['id']);
        $verb = $qty === 1 ? 'Versato' : 'Versati';
        $pezzi = $qty === 1 ? 'pezzo' : 'pezzi';
        $label = $kind === 'good' ? ($qty === 1 ? 'buono' : 'buoni') : 'di scarto';
        $this->effect($verb . ' ' . $qty . ' ' . $pezzi . ' ' . $label . ' su ' . $operation['code'] . '.');
        $this->effect('Totale buoni della fase: ' . $view['qtyGood'] . '. Scarto: ' . $view['qtyScrap'] . '.');
        return ['operation' => $view];
    }

    /** @param array<string, mixed> $body */
    private function finishOperation(array $body): array
    {
        $operation = $this->requireOperation($body);
        if ($operation['status'] === 'held') {
            throw new LabException('La fase è held per un fermo. Chiudi prima il fermo.');
        }
        if ($operation['status'] !== 'running') {
            throw new LabException('Si chiude solo una fase running. Questa è ' . $operation['status'] . '.');
        }
        $this->exec(
            'UPDATE hxs_operation SET status = ?, finished_at = ? WHERE id = ?',
            ['done', $this->now(), (int) $operation['id']]
        );
        $this->exec('UPDATE hxs_workplace SET state = ? WHERE id = ?', ['idle', (int) $operation['workplace_id']]);
        $workplace = $this->requireWorkplaceById((int) $operation['workplace_id']);
        $this->effect('Operazione ' . $operation['code'] . ' chiusa.');
        $this->effect('Postazione ' . $workplace['code'] . ' torna idle.');
        $orderView = $this->closeOrderIfDone((int) $operation['order_id']);
        return [
            'operation' => $this->operationView((int) $operation['id']),
            'workplace' => $this->workplaceView((int) $workplace['id']),
            'order' => $orderView,
        ];
    }

    /** @param array<string, mixed> $body */
    private function startDowntime(array $body): array
    {
        $workplace = $this->requireWorkplace($body);
        $open = $this->openDowntime((int) $workplace['id']);
        if ($open !== null) {
            throw new LabException('C\'è già un fermo aperto su ' . $workplace['code'] . '.');
        }
        $reasonCode = (string) ($body['reasonCode'] ?? '');
        $reasons = self::downtimeReasons();
        if (!isset($reasons[$reasonCode])) {
            throw new LabException('Causale fermo sconosciuta. Usa: ' . implode(', ', array_keys($reasons)) . '.');
        }
        $running = $this->one(
            'SELECT * FROM hxs_operation WHERE workplace_id = ? AND status = ?',
            [(int) $workplace['id'], 'running']
        );
        if ($running !== null) {
            $this->exec('UPDATE hxs_operation SET status = ? WHERE id = ?', ['held', (int) $running['id']]);
            $this->effect('Operazione ' . $running['code'] . ' passa a held.');
        }
        $this->exec('UPDATE hxs_workplace SET state = ? WHERE id = ?', ['down', (int) $workplace['id']]);
        $this->insert('hxs_downtime', ['workplace_id', 'reason_code', 'reason_text', 'started_at', 'ended_at'], [
            (int) $workplace['id'],
            $reasonCode,
            $reasons[$reasonCode],
            $this->now(),
            null,
        ]);
        $this->effect('Fermo ' . $reasonCode . ' aperto su ' . $workplace['code'] . '.');
        return ['workplace' => $this->workplaceView((int) $workplace['id'])];
    }

    /** @param array<string, mixed> $body */
    private function endDowntime(array $body): array
    {
        $workplace = $this->requireWorkplace($body);
        $open = $this->openDowntime((int) $workplace['id']);
        if ($open === null) {
            throw new LabException('Nessun fermo aperto su ' . $workplace['code'] . '.');
        }
        $this->exec('UPDATE hxs_downtime SET ended_at = ? WHERE id = ?', [$this->now(), (int) $open['id']]);
        $held = $this->one(
            'SELECT * FROM hxs_operation WHERE workplace_id = ? AND status = ?',
            [(int) $workplace['id'], 'held']
        );
        if ($held !== null) {
            $this->exec('UPDATE hxs_operation SET status = ? WHERE id = ?', ['running', (int) $held['id']]);
            $this->exec('UPDATE hxs_workplace SET state = ? WHERE id = ?', ['running', (int) $workplace['id']]);
            $this->effect('Operazione ' . $held['code'] . ' torna running.');
            $this->effect('Postazione ' . $workplace['code'] . ' torna running.');
        } else {
            $this->exec('UPDATE hxs_workplace SET state = ? WHERE id = ?', ['idle', (int) $workplace['id']]);
            $this->effect('Postazione ' . $workplace['code'] . ' torna idle.');
        }
        $this->effect('Fermo chiuso su ' . $workplace['code'] . '.');
        return ['workplace' => $this->workplaceView((int) $workplace['id'])];
    }

    /** @param array<string, mixed> $body */
    private function edgeSignal(array $body): array
    {
        $workplace = $this->requireWorkplace($body);
        $signal = (string) ($body['signal'] ?? '');
        $value = trim((string) ($body['value'] ?? ''));
        if ($value === '') {
            throw new LabException('Il segnale richiede un value.');
        }
        $applied = match ($signal) {
            'piece_good' => $this->applyPieceSignal($workplace, 'good', $value, null),
            'piece_scrap' => $this->applyPieceSignal($workplace, 'scrap', $value, 'materiale'),
            'fault' => $this->applyFaultSignal($workplace, $value),
            'clear' => $this->applyClearSignal($workplace),
            default => throw new LabException('Segnale sconosciuto. Usa piece_good, piece_scrap, fault, clear.'),
        };
        $this->insert('hxs_signal', ['workplace_id', 'signal', 'value', 'created_at', 'applied'], [
            (int) $workplace['id'],
            $signal,
            $value,
            $this->now(),
            $applied,
        ]);
        $this->effect('Segnale ' . $signal . ' registrato su ' . $workplace['code'] . '.');
        return [
            'applied' => $applied,
            'workplace' => $this->workplaceView((int) $workplace['id']),
        ];
    }

    /** @param array<string, mixed> $workplace */
    private function applyPieceSignal(array $workplace, string $kind, string $value, ?string $reason): string
    {
        $running = $this->one(
            'SELECT * FROM hxs_operation WHERE workplace_id = ? AND status = ?',
            [(int) $workplace['id'], 'running']
        );
        if ($running === null) {
            throw new LabException('Nessuna fase running su ' . $workplace['code'] . ': il contatore non ha dove versare.');
        }
        $operatorId = $running['operator_id'];
        if ($operatorId === null) {
            throw new LabException('La fase non ha un operatore.');
        }
        $qty = $this->qty($value);
        $reasonText = null;
        if ($kind === 'scrap') {
            $reasonText = self::scrapReasons()[$reason];
        }
        $this->insert('hxs_booking', ['operation_id', 'kind', 'qty', 'reason', 'operator_id', 'created_at'], [
            (int) $running['id'],
            $kind,
            $qty,
            $reasonText,
            (int) $operatorId,
            $this->now(),
        ]);
        $this->effect('Il segnale è diventato un versamento ' . $kind . ' di ' . $qty . ' su ' . $running['code'] . '.');
        return 'booking ' . $kind . ' ' . $qty . ' ' . $running['code'];
    }

    /** @param array<string, mixed> $workplace */
    private function applyFaultSignal(array $workplace, string $value): string
    {
        $this->startDowntime([
            'workplaceCode' => $workplace['code'],
            'reasonCode' => $value,
        ]);
        return 'downtime.start ' . $value;
    }

    /** @param array<string, mixed> $workplace */
    private function applyClearSignal(array $workplace): string
    {
        $this->endDowntime(['workplaceCode' => $workplace['code']]);
        return 'downtime.end';
    }

    /** @param array<string, mixed> $body */
    private function demo(array $body): array
    {
        if (($body['confirm'] ?? '') !== 'DEMO') {
            throw new LabException('Per caricare il turno serve confirm = DEMO.');
        }
        $ready = $this->one('SELECT status FROM hxs_operation WHERE code = ?', ['OF-24081-10']);
        if ($ready === null || $ready['status'] !== 'ready') {
            throw new LabException('Il turno di esempio parte solo dalle anagrafiche pulite. Esegui prima lab.reset.');
        }
        $this->startOperation(['operationCode' => 'OF-24081-10', 'operatorCode' => 'op.01']);
        $this->bookOperation(['operationCode' => 'OF-24081-10', 'operatorCode' => 'op.01', 'kind' => 'good', 'qty' => 6]);
        $this->bookOperation(['operationCode' => 'OF-24081-10', 'operatorCode' => 'op.01', 'kind' => 'scrap', 'qty' => 1, 'reason' => 'bava']);
        $this->finishOperation(['operationCode' => 'OF-24081-10']);
        $this->startDowntime(['workplaceCode' => 'FRES-02', 'reasonCode' => 'attesa_materiale']);
        $this->endDowntime(['workplaceCode' => 'FRES-02']);
        return ['note' => 'Tornitura OF-24081 chiusa con 6 buoni e 1 scarto. La fresatura è pronta.'];
    }

    /** @param array<string, mixed> $body */
    private function reset(array $body): array
    {
        if (($body['confirm'] ?? '') !== 'RESET') {
            throw new LabException('Per ripristinare serve confirm = RESET.');
        }
        foreach ([
            'hxs_signal',
            'hxs_service_log',
            'hxs_booking',
            'hxs_downtime',
            'hxs_operation',
            'hxs_order',
            'hxs_person',
            'hxs_workplace',
        ] as $table) {
            $this->exec('DELETE FROM ' . $table);
        }
        $this->seedMaster();
        $this->effect('Anagrafiche di esempio ricaricate. Nessun movimento.');
        return ['note' => 'Banco riportato ai dati iniziali.'];
    }

    private function closeOrderIfDone(int $orderId): array
    {
        $left = $this->one(
            'SELECT COUNT(*) AS n FROM hxs_operation WHERE order_id = ? AND status <> ?',
            [$orderId, 'done']
        );
        $order = $this->requireOrderById($orderId);
        if ((int) ($left['n'] ?? 0) === 0) {
            $this->exec('UPDATE hxs_order SET status = ? WHERE id = ?', ['done', $orderId]);
            $this->effect('Ordine ' . $order['code'] . ' passa a done: tutte le fasi sono chiuse.');
        }
        return $this->orderView($orderId);
    }

    private function seedIfEmpty(): void
    {
        if ($this->count('hxs_workplace') > 0) {
            return;
        }
        $this->pdo->beginTransaction();
        try {
            $this->seedMaster();
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function seedMaster(): void
    {
        $this->insert('hxs_workplace', ['code', 'name', 'state'], ['TORN-01', 'Tornio 1', 'idle']);
        $this->insert('hxs_workplace', ['code', 'name', 'state'], ['FRES-02', 'Fresatrice 2', 'idle']);
        $this->insert('hxs_workplace', ['code', 'name', 'state'], ['ASSY-03', 'Assemblaggio 3', 'idle']);
        $this->insert('hxs_person', ['code', 'name', 'role'], ['op.01', 'Operatore turno A', 'operatore']);
        $this->insert('hxs_person', ['code', 'name', 'role'], ['op.02', 'Operatore turno B', 'operatore']);
        $flange = $this->insert('hxs_order', ['code', 'material_code', 'material_name', 'qty_planned', 'status'], [
            'OF-24081', 'FL-DN50', 'Flangia DN50', 40, 'released',
        ]);
        $shaft = $this->insert('hxs_order', ['code', 'material_code', 'material_name', 'qty_planned', 'status'], [
            'OF-24082', 'ALB-18', 'Albero 18', 12, 'released',
        ]);
        $tornio = (int) $this->requireWorkplace(['workplaceCode' => 'TORN-01'])['id'];
        $fresa = (int) $this->requireWorkplace(['workplaceCode' => 'FRES-02'])['id'];
        $assy = (int) $this->requireWorkplace(['workplaceCode' => 'ASSY-03'])['id'];
        $this->insertOperation('OF-24081-10', $flange, $tornio, 10, 'Tornitura');
        $this->insertOperation('OF-24081-20', $flange, $fresa, 20, 'Fresatura');
        $this->insertOperation('OF-24082-10', $shaft, $tornio, 10, 'Tornitura');
        $this->insertOperation('OF-24082-20', $shaft, $assy, 20, 'Assemblaggio');
    }

    private function insertOperation(string $code, int $orderId, int $workplaceId, int $seq, string $name): void
    {
        $this->insert('hxs_operation', [
            'code', 'order_id', 'workplace_id', 'seq', 'name', 'status', 'started_at', 'finished_at', 'operator_id',
        ], [$code, $orderId, $workplaceId, $seq, $name, 'ready', null, null, null]);
    }

    private function ensureSqlite(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS hxs_workplace (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                state TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS hxs_person (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                role TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS hxs_order (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                material_code TEXT NOT NULL,
                material_name TEXT NOT NULL,
                qty_planned INTEGER NOT NULL,
                status TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS hxs_operation (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                order_id INTEGER NOT NULL REFERENCES hxs_order (id),
                workplace_id INTEGER NOT NULL REFERENCES hxs_workplace (id),
                seq INTEGER NOT NULL,
                name TEXT NOT NULL,
                status TEXT NOT NULL,
                started_at TEXT,
                finished_at TEXT,
                operator_id INTEGER REFERENCES hxs_person (id)
            );
            CREATE TABLE IF NOT EXISTS hxs_booking (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                operation_id INTEGER NOT NULL REFERENCES hxs_operation (id),
                kind TEXT NOT NULL,
                qty INTEGER NOT NULL,
                reason TEXT,
                operator_id INTEGER REFERENCES hxs_person (id),
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS hxs_downtime (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workplace_id INTEGER NOT NULL REFERENCES hxs_workplace (id),
                reason_code TEXT NOT NULL,
                reason_text TEXT NOT NULL,
                started_at TEXT NOT NULL,
                ended_at TEXT
            );
            CREATE TABLE IF NOT EXISTS hxs_signal (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workplace_id INTEGER NOT NULL REFERENCES hxs_workplace (id),
                signal TEXT NOT NULL,
                value TEXT NOT NULL,
                created_at TEXT NOT NULL,
                applied TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS hxs_service_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                service TEXT NOT NULL,
                request_json TEXT NOT NULL,
                response_json TEXT NOT NULL,
                ok INTEGER NOT NULL,
                created_at TEXT NOT NULL
            );
        SQL);
    }

    /** @return array<string, string> */
    public static function scrapReasons(): array
    {
        return [
            'bava' => 'Bava',
            'quota' => 'Fuori quota',
            'materiale' => 'Materiale difettoso',
            'attrezzaggio' => 'Errore di attrezzaggio',
        ];
    }

    /** @return array<string, string> */
    public static function downtimeReasons(): array
    {
        return [
            'guasto' => 'Guasto',
            'attesa_materiale' => 'Attesa materiale',
            'attrezzaggio' => 'Attrezzaggio',
            'mancanza_operatore' => 'Mancanza operatore',
        ];
    }

    /** @param array<string, mixed> $body */
    private function requireOperation(array $body): array
    {
        if (!empty($body['operationCode'])) {
            $row = $this->one('SELECT * FROM hxs_operation WHERE code = ?', [(string) $body['operationCode']]);
        } elseif (!empty($body['operationId'])) {
            $row = $this->one('SELECT * FROM hxs_operation WHERE id = ?', [(int) $body['operationId']]);
        } else {
            throw new LabException('Serve operationCode.');
        }
        if ($row === null) {
            throw new LabException('Operazione assente.');
        }
        return $row;
    }

    /** @param array<string, mixed> $body */
    private function requireOperator(array $body): array
    {
        if (!empty($body['operatorCode'])) {
            $row = $this->one('SELECT * FROM hxs_person WHERE code = ?', [(string) $body['operatorCode']]);
        } elseif (!empty($body['operatorId'])) {
            $row = $this->one('SELECT * FROM hxs_person WHERE id = ?', [(int) $body['operatorId']]);
        } else {
            throw new LabException('Serve operatorCode.');
        }
        if ($row === null) {
            throw new LabException('Operatore assente.');
        }
        return $row;
    }

    /** @param array<string, mixed> $body */
    private function requireWorkplace(array $body): array
    {
        if (empty($body['workplaceCode'])) {
            throw new LabException('Serve workplaceCode.');
        }
        $row = $this->one('SELECT * FROM hxs_workplace WHERE code = ?', [(string) $body['workplaceCode']]);
        if ($row === null) {
            throw new LabException('Postazione assente.');
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireWorkplaceById(int $id): array
    {
        $row = $this->one('SELECT * FROM hxs_workplace WHERE id = ?', [$id]);
        if ($row === null) {
            throw new LabException('Postazione assente.', 500);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function requireOrderById(int $id): array
    {
        $row = $this->one('SELECT * FROM hxs_order WHERE id = ?', [$id]);
        if ($row === null) {
            throw new LabException('Ordine assente.', 500);
        }
        return $row;
    }

    /** @return array<string, mixed>|null */
    private function openDowntime(int $workplaceId): ?array
    {
        return $this->one(
            'SELECT * FROM hxs_downtime WHERE workplace_id = ? AND ended_at IS NULL',
            [$workplaceId]
        );
    }

    /** @return array<string, mixed> */
    private function operationView(int $id): array
    {
        $row = $this->one(
            'SELECT o.*, wo.code AS order_code, wo.material_name, w.code AS workplace_code, w.name AS workplace_name,
                    p.code AS operator_code,
                    (SELECT COALESCE(SUM(b.qty), 0) FROM hxs_booking b WHERE b.operation_id = o.id AND b.kind = \'good\') AS qty_good,
                    (SELECT COALESCE(SUM(b.qty), 0) FROM hxs_booking b WHERE b.operation_id = o.id AND b.kind = \'scrap\') AS qty_scrap
             FROM hxs_operation o
             JOIN hxs_order wo ON wo.id = o.order_id
             JOIN hxs_workplace w ON w.id = o.workplace_id
             LEFT JOIN hxs_person p ON p.id = o.operator_id
             WHERE o.id = ?',
            [$id]
        );
        if ($row === null) {
            throw new LabException('Operazione assente.', 500);
        }
        return [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'seq' => (int) $row['seq'],
            'name' => $row['name'],
            'status' => $row['status'],
            'orderCode' => $row['order_code'],
            'materialName' => $row['material_name'],
            'workplaceCode' => $row['workplace_code'],
            'workplaceName' => $row['workplace_name'],
            'operatorCode' => $row['operator_code'],
            'startedAt' => $row['started_at'],
            'finishedAt' => $row['finished_at'],
            'qtyGood' => (int) $row['qty_good'],
            'qtyScrap' => (int) $row['qty_scrap'],
        ];
    }

    /** @return array<string, mixed> */
    private function workplaceView(int $id): array
    {
        $row = $this->requireWorkplaceById($id);
        $today = $this->today() . '%';
        $good = $this->one(
            'SELECT COALESCE(SUM(b.qty), 0) AS n
             FROM hxs_booking b
             JOIN hxs_operation o ON o.id = b.operation_id
             WHERE o.workplace_id = ? AND b.kind = \'good\' AND b.created_at LIKE ?',
            [$id, $today]
        );
        $scrap = $this->one(
            'SELECT COALESCE(SUM(b.qty), 0) AS n
             FROM hxs_booking b
             JOIN hxs_operation o ON o.id = b.operation_id
             WHERE o.workplace_id = ? AND b.kind = \'scrap\' AND b.created_at LIKE ?',
            [$id, $today]
        );
        $current = $this->one(
            'SELECT code, status FROM hxs_operation WHERE workplace_id = ? AND status IN (\'running\', \'held\')',
            [$id]
        );
        $open = $this->openDowntime($id);
        return [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'state' => $row['state'],
            'qtyGoodToday' => (int) ($good['n'] ?? 0),
            'qtyScrapToday' => (int) ($scrap['n'] ?? 0),
            'currentOperation' => $current['code'] ?? null,
            'currentStatus' => $current['status'] ?? null,
            'downtimeReason' => $open['reason_text'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function orderView(int $id): array
    {
        $row = $this->requireOrderById($id);
        $good = $this->one(
            'SELECT COALESCE(SUM(b.qty), 0) AS n
             FROM hxs_booking b
             JOIN hxs_operation o ON o.id = b.operation_id
             WHERE o.order_id = ? AND b.kind = \'good\'',
            [$id]
        );
        $scrap = $this->one(
            'SELECT COALESCE(SUM(b.qty), 0) AS n
             FROM hxs_booking b
             JOIN hxs_operation o ON o.id = b.operation_id
             WHERE o.order_id = ? AND b.kind = \'scrap\'',
            [$id]
        );
        $ops = $this->rows('SELECT code, status, name FROM hxs_operation WHERE order_id = ? ORDER BY seq', [$id]);
        $qtyGood = (int) ($good['n'] ?? 0);
        $planned = (int) $row['qty_planned'];
        return [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'materialCode' => $row['material_code'],
            'materialName' => $row['material_name'],
            'qtyPlanned' => $planned,
            'qtyGood' => $qtyGood,
            'qtyScrap' => (int) ($scrap['n'] ?? 0),
            'status' => $row['status'],
            'progress' => $planned === 0 ? 0 : min(100, (int) floor($qtyGood * 100 / $planned)),
            'operations' => array_map(static fn (array $op): array => [
                'code' => $op['code'],
                'name' => $op['name'],
                'status' => $op['status'],
            ], $ops),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function bookingRows(): array
    {
        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'operationCode' => $row['operation_code'],
                'kind' => $row['kind'],
                'qty' => (int) $row['qty'],
                'reason' => $row['reason'],
                'operatorCode' => $row['operator_code'],
                'createdAt' => $row['created_at'],
            ];
        }, $this->rows(
            'SELECT b.*, o.code AS operation_code, p.code AS operator_code
             FROM hxs_booking b
             JOIN hxs_operation o ON o.id = b.operation_id
             LEFT JOIN hxs_person p ON p.id = b.operator_id
             ORDER BY b.id DESC'
        ));
    }

    /** @return list<array<string, mixed>> */
    private function downtimeRows(): array
    {
        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'workplaceCode' => $row['workplace_code'],
                'reasonCode' => $row['reason_code'],
                'reasonText' => $row['reason_text'],
                'startedAt' => $row['started_at'],
                'endedAt' => $row['ended_at'],
            ];
        }, $this->rows(
            'SELECT d.*, w.code AS workplace_code
             FROM hxs_downtime d
             JOIN hxs_workplace w ON w.id = d.workplace_id
             ORDER BY d.id DESC'
        ));
    }

    /** @return list<array<string, mixed>> */
    private function signalRows(): array
    {
        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'workplaceCode' => $row['workplace_code'],
                'signal' => $row['signal'],
                'value' => $row['value'],
                'applied' => $row['applied'],
                'createdAt' => $row['created_at'],
            ];
        }, $this->rows(
            'SELECT s.*, w.code AS workplace_code
             FROM hxs_signal s
             JOIN hxs_workplace w ON w.id = s.workplace_id
             ORDER BY s.id DESC'
        ));
    }

    private function effect(string $text): void
    {
        $this->effects[] = $text;
    }

    private function count(string $table): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM ' . $table);
        return (int) ($row['n'] ?? 0);
    }

    private function qty(mixed $value): int
    {
        if (is_int($value)) {
            $n = $value;
        } elseif (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1) {
            $n = (int) $value;
        } else {
            throw new LabException('La quantità deve essere un intero positivo.');
        }
        if ($n <= 0 || $n > 100000) {
            throw new LabException('La quantità deve essere un intero positivo.');
        }
        return $n;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d H:i:s');
    }

    private function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
    }

    /** @param array<string, mixed> $body */
    /** @param array<string, mixed> $response */
    private function writeLog(string $service, array $body, array $response, bool $ok): void
    {
        try {
            $this->insert('hxs_service_log', ['service', 'request_json', 'response_json', 'ok', 'created_at'], [
                $service,
                $this->clip(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                $this->clip(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                $ok ? 1 : 0,
                $this->now(),
            ]);
        } catch (Throwable) {
            // Il giornale non deve mascherare l'esito del servizio.
        }
    }

    private function clip(string $json): string
    {
        if (strlen($json) <= 3500) {
            return $json;
        }
        return substr($json, 0, 3500);
    }

    /**
     * @param list<string> $cols
     * @param list<mixed> $vals
     */
    private function insert(string $table, array $cols, array $vals): int
    {
        $this->assertTable($table);
        $place = implode(', ', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . $place . ')';
        $this->pdo->prepare($sql)->execute($vals);
        if ($this->driver === 'sqlite') {
            return (int) $this->pdo->lastInsertId();
        }
        return (int) $this->pdo->query('SELECT ' . $table . '_seq.CURRVAL FROM dual')->fetchColumn();
    }

    /** @param list<mixed> $params */
    private function exec(string $sql, array $params = []): void
    {
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(static fn (array $row): array => array_change_key_case($row, CASE_LOWER), $rows);
    }

    /**
     * @param list<mixed> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params = []): ?array
    {
        $rows = $this->rows($sql, $params);
        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function ints(array $row, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $row[$key] = (int) $row[$key];
            }
        }
        return $row;
    }

    private function assertTable(string $table): void
    {
        $allowed = [
            'hxs_workplace', 'hxs_person', 'hxs_order', 'hxs_operation',
            'hxs_booking', 'hxs_downtime', 'hxs_signal', 'hxs_service_log',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new LabException('Tabella non prevista.', 500);
        }
    }
}
