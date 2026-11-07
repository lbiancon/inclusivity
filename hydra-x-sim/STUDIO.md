# Studiare Banco X come l'HYDRA X che userai

Banco X è il gesto di HYDRA X, messo in una cartella che gira in htdocs. Le schermate, i nomi dei servizi e le tabelle `hxs_*` sono del laboratorio. L'abitudine che alleni è quella che ritrovi nel prodotto, tra qualche mese o a fine 2027: un comando ha un nome, un corpo e delle regole; scrive il modello una volta sola; Terminale, Monitor, lista e KPI rileggono quegli stessi oggetti.

HYDRA, MOC, MIP e ViPR sono nomi di MPDV. Questo banco non è il loro software e non si collega allo schema HYDRA di produzione.

I dati non stanno nella cartella del sito. Spostare `hydra-x-sim` dentro htdocs non crea un database nuovo e non cambia le tabelle. Senza `config.local.php` il banco apre MySQL su `127.0.0.1:3306`, database `hxs`, utente `root`, password vuota, e lì crea le tabelle. La riga in alto della pagina deve dire `MySQL · database hxs su 127.0.0.1:3306`. Se dice un percorso che finisce in `sim.sqlite`, nella cartella c'è un `config.local.php` con `driver` sqlite: cancellalo o metti `mysql`.

Per Oracle crei `config.local.php` con `driver` uguale a `oci`, esegui `sql/oracle.sql` come utente `HXSIM` e metti DSN, utente e password. Se manca `pdo_oci`, la pagina si ferma. Non ripiega su un file nella cartella.

## Cosa resta uguale, e cosa cambia nome

| Qui, oggi | Nel prodotto, quando ci arrivi |
| --- | --- |
| Terminale e Monitor sono due pagine. Nessuna delle due tiene il conto dei pezzi per conto suo. | Due mApp. Entrambe leggono il modello condiviso. Se una scrive e l'altra non si aggiorna, il disegno è sbagliato. |
| Il bottone chiama un servizio (`operation.book`) con un JSON. | Il bottone della mApp chiama un servizio REST. Il nome sarà quello del catalogo MPDV, non `operation.book`. La forma è la stessa: nome, corpo, esito, effetti. |
| Se la regola non è soddisfatta, il servizio rifiuta, annulla la transazione e scrive il rifiuto nel giornale. | Il rifiuto è un esito del servizio, non un messaggio inventato dalla maschera. Lo ritrovi nel giornale delle chiamate. |
| Il segnale `piece_good` non fa un `INSERT` sul versamento. Diventa la stessa logica del versamento. | In HYDRA 8 il canale macchina è il PCC. In HYDRA X è Distributed Edge Computing: un driver, di solito C#/.NET (corso DT-DEC), che consegna il segnale al servizio. Il driver non salta la regola. |
| LST-11 è una definizione: base, colonne, filtro. Il browser non manda SQL. | Una vista o una lista della mApp è una definizione sul modello. Il filtro lo scegli tu. La query la possiede la piattaforma. |
| L'OEE si ricalcola dai versamenti, dai fermi e dal tempo ciclo. Non ha una tabella sua. | La valutazione legge gli stessi fatti della raccolta. I tre fattori (disponibilità, performance, qualità) restano. Il calendario di turno, il tempo pianificato e la fonte del tempo ciclo saranno quelli dello stabilimento, non i dieci minuti di questo esempio. |
| `workplace.cycle` cambia `ideal_sec`. La colonna ha lo stesso nome su MySQL e su Oracle. | Un dato di anagrafica si cambia con un servizio. Lo schema fisico può essere un altro database. Il chiamante non conosce la tabella. |
| DLG, MST, LST, AUN | Sono parole del client HYDRA 8 e del VB.NET sulle tabelle Oracle. In X non le cerchi col vecchio codice. Cerchi la mApp (quello che era il DLG), l'oggetto del modello (quello che era l'MST), la vista (quello che era l'LST), la valutazione (quello che era l'AUN). |

Quello che non devi memorizzare come se fosse il prodotto: i nomi `hxs_*`, i nomi `operation.start` e simili, il file `lib/Lab.php`, il fatto che sotto ci sia PHP. Nel prodotto il modello si chiama ViPR, i servizi si modellano nel disegno oggetti/servizi (corso DT-OSD, in XML), le mApp si costruiscono con la UI Development Suite (DT-UI), il modello di business si studia in DT-MBO. Questo banco non sostituisce quei corsi. Ti fa arrivare al corso sapendo già che cosa stai guardando.

## Dizionario: il termine che ritrovi su X

I comandi di questo banco allenano il gesto del DLG. Il nome `operation.book` è del laboratorio: in HYDRA X il servizio ha il nome del catalogo della piattaforma, quello che disegnerai in DT-OSD. Il nome dell'oggetto, invece, è già quello del prodotto. Quando passi, cerchi queste parole, non DLG, MST, LST, AUN.

HYDRA 8 ha due client. Il MOC (MES Operation Center) è l'ufficio, dove lavori tu. L'AIP (Acquisition & Information Panel) è l'officina: lì l'operatore fa log on, quantità e fermi. In HYDRA X l'officina resta uno shop floor terminal; il software di raccolta è la mApp Work Order Execution. L'ufficio diventa un insieme di mApp sulla MIP. I dati comuni stanno in ViPR.

| Sul MOC, oggi | Nel banco | In HYDRA X | mApp |
| --- | --- | --- | --- |
| MOC, client di ufficio | La pagina Da 8 a X descrive il passaggio. Non è il MOC | Funzioni di ufficio come mApp | Work Order Management, Work Order Controlling |
| AIP, client di officina | Terminale | Shop floor terminal | Work Order Execution |
| DLG di inizio, versamento, fermo | `operation.start`, `operation.book`, `downtime.start` | Log on, quantity posting, downtime. Il codice DLG non si riusa | Work Order Execution chiama un servizio di ViPR |
| Postazione, spesso fusa con la macchina | `workplace` | `workplace`. Se la macchina è un'anagrafica distinta, è `machine` | Machine Monitoring assegna il dato al workplace |
| Operatore firmato sulla fase | `person` | `person` | Work Order Execution |
| Utente con cui entri nel MOC | Nessun login | Identità di accesso alle mApp, distinta dalla person | Accesso alle mApp |
| Ordine di produzione | `order` | `production order`. X ha anche overhead order e rework order | Work Order Management. Il work plan genera l'ordine, spesso l'ERP lo scarica |
| Fase, Arbeitsgang | `operation` | `operation` | Work Order Management la pianifica, Work Order Execution la raccoglie |
| Fase non avviata | `ready` | `planned` | Lista operation pianificate |
| Fase in lavoro | `running` | `logged on` | Work Order Execution |
| Fase sospesa dal fermo | `held` | `interrupt`. Lo stato macchina è un machine status | Work Order Execution e Machine Monitoring |
| Fase chiusa | `done` | `completed`, logged off | Work Order Execution |
| Pezzi buoni | `booking` `good` | `yield` | Raccolta in Work Order Execution, lettura in Work Order Controlling |
| Scarto con causale | `booking` `scrap`, `reason` | `scrap` con reason. X ha anche rework e open quantity, qui assenti | Work Order Execution |
| Fermo | `downtime` | downtime, come machine status su un time account e una status class | Machine Monitoring raccoglie. Machine Status Analysis valuta |
| LST 11, LST 16 | `list`, solo la definizione | Vista della mApp. Il filtro è tuo, la query è della piattaforma | Work Order Controlling, Machine Status Analysis |
| AUN, OEE | KPI | OEE e indici di macchina; quantità e tempi per workplace | Machine Performance KPIs, Work Order Controlling |
| PCC, segnale macchina | `edge.signal` | Distributed Edge Computing: il driver consegna il segnale al servizio | Machine Monitoring |

La pagina Da 8 a X mostra la stessa tabella. Sotto c'è un giro da fare sul MOC che hai già, solo leggendo.

1. Apri un ordine di produzione e scrivi accanto `production order`.
2. Apri le fasi e scrivi `operation`. Pianificata, in lavoro, chiusa diventano planned, logged on, completed.
3. La postazione della fase è `workplace`. Se la macchina è un oggetto a parte, è `machine`.
4. Un versamento: buoni = `yield`, scarti = `scrap`, causale = `reason`. La rilavorazione, se c'è, è `rework`.
5. Un fermo o uno stato macchina è `downtime` / machine status. La lista lo mostra. Il servizio lo ha scritto.
6. L'utente del MOC non è l'operatore. La `person` è il nominativo sulla fase.
7. Il DLG del versamento è la maschera di oggi. Su X è un'azione di Work Order Execution.

Questo giro si fa su un client di test quando il passo scrive qualcosa. Su un client di linea ci si limita a aprire e leggere. Il banco non va puntato allo schema HYDRA: i due mondi restano affiancati, e tu traduci i nomi.

## Cosa è stato creato

Tutto sta in questa cartella. Copiata in `C:\xampp\htdocs\hydra-x-sim`, Apache esegue `index.php`.

| File | Compito |
| --- | --- |
| `index.php` | Se PHP è sotto la 8.1 si ferma con una pagina chiara e non crea il database. Altrimenti carica `lib/app.php`. |
| `lib/app.php` | Front controller. Le letture sono GET (`/api/kpi`, `/api/lists/...`). Le scritture sono POST su `/api/services/{nome}`. Poi manda l'HTML della shell. |
| `lib/Lab.php` | L'unico posto che conosce le regole e scrive le tabelle. È il servizio, nel senso del laboratorio. |
| `lib/LabException.php` | Rifiuto con un messaggio e un codice HTTP. |
| `assets/app.js` | Le mApp: Terminale, Monitor, KPI, Liste, Oggetti, Servizi. Costruisce il JSON, chiama, poi rilegge. Non aggiorna i pezzi a mano. |
| `assets/app.css` | Impaginazione. |
| `sql/mysql.sql` | Tabelle per MySQL di XAMPP. All'avvio con `driver = mysql` il banco le crea nel database `hxs`. |
| `sql/oracle.sql` | Le stesse tabelle e le stesse colonne, tipi Oracle, sequenze e trigger per l'id. Si esegue a mano come utente `HXSIM`. |
| `config.example.php` | Modello di `config.local.php`. Senza quel file i dati stanno già in MySQL, database `hxs` su `127.0.0.1:3306`. Il file serve per Oracle (`driver` = `oci`) o per forzare un file SQLite. |
| `controllo.php` | Dice perché il file SQLite non nasce: PHP vecchio, `pdo_sqlite` spento, cartella non scrivibile da Apache. |
| `tests/run.php` | Le regole, lanciate da riga di comando. |
| `STUDIO.md` | Questa guida. |

`lab.demo` e `lab.reset` esistono solo qui. Servono a rimettere un turno noto. Nel prodotto non ci sono.

## Il modello

Sette oggetti, più la lista che è una definizione sopra gli oggetti.

| Oggetto | Tabella | A che serve |
| --- | --- | --- |
| Postazione | `hxs_workplace` | Macchina o banco. Stato `idle`, `running`, `down`. `ideal_sec` è il tempo ciclo standard, in secondi. |
| Persona | `hxs_person` | Chi firma avvio e versamento. |
| Ordine | `hxs_order` | Materiale e quantità prevista. Stato `released`, poi `in_progress`, poi `done`. |
| Operazione | `hxs_operation` | Fase dell'ordine su una postazione. Stato `ready`, `running`, `held`, `done`. |
| Versamento | `hxs_booking` | Pezzi. `kind` è `good` o `scrap`. Lo scarto ha la causale in `reason`. |
| Fermo | `hxs_downtime` | Intervallo. `ended_at` vuoto vuol dire fermo aperto. |
| Segnale | `hxs_signal` | Traccia di ciò che è arrivato dalla macchina e di quale servizio è stato applicato. |
| Lista | `hxs_list` | Codice (`LST-11`), titolo, base, colonne, filtro. Non contiene i pezzi. |

Dati di partenza, se le tabelle sono vuote: tornio `TORN-01` (ciclo 60 s), fresatrice `FRES-02` (90 s), assemblaggio `ASSY-03` (120 s), operatori `op.01` e `op.02`, ordini `OF-24081` (flangia, 40 pezzi) e `OF-24082` (albero, 12 pezzi), quattro fasi.

Stati che devi saper ridire ad alta voce:

- Si avvia solo una fase `ready`, e solo se la postazione è `idle`.
- Una postazione ha al massimo una fase `running` o `held`.
- Un fermo aperto porta la fase `running` a `held` e la postazione a `down`. Su `held` il versamento è rifiutato.
- Chiuso il fermo, la fase torna `running`.
- Si chiude solo una fase `running`. Se tutte le fasi dell'ordine sono `done`, l'ordine diventa `done`.
- Il primo avvio di una fase porta l'ordine da `released` a `in_progress`.

## Come è fatto un servizio

Il Terminale non scrive `hxs_booking`. Fa questo.

1. `assets/app.js` manda `POST index.php?r=/api/services/operation.book` con un JSON, per esempio `{ "operationCode": "OF-24081-10", "operatorCode": "op.01", "kind": "good", "qty": 5 }`.
2. `lib/app.php` legge il nome dalla URL e il corpo dalla richiesta. Se il corpo non è un oggetto JSON, risponde 400 e `Lab` non parte.
3. `Lab::call` controlla che il nome sia nel catalogo. Un nome assente risponde 404 e non apre la transazione di lavoro oltre il controllo.
4. Apre una transazione. `dispatch` sceglie il metodo (`bookOperation`, `startOperation`, …).
5. Il metodo applica le regole. Se una regola manca, lancia `LabException`. La transazione torna indietro: in tabella non resta mezza scrittura. Il rifiuto viene comunque scritto in `hxs_service_log` con `ok = 0`.
6. Se le regole passano, il metodo aggiorna le tabelle e accumula gli effetti, che sono frasi («Versati 5 pezzi buoni su OF-24081-10»). Poi commit.
7. La risposta contiene il servizio, gli effetti e gli oggetti ricalcolati. Il giornale riceve la chiamata con `ok = 1`.
8. Il browser non si fida della pagina che aveva prima. `refresh` rifà le GET (`/api/monitor`, `/api/kpi`, `/api/objects/...`, `/api/lists/...`). Monitor e KPI cambiano perché rileggono, non perché il Terminale gli abbia passato un numero.

Questo è il pezzo da riconoscere nel prodotto. Cambieranno il verbo HTTP preciso, il nome e lo strumento con cui il servizio è disegnato. Restano: catalogo, corpo, transazione, rifiuto spiegato, giornale, altre mApp che rileggono.

Le letture non passano da `call`. Sono GET e non scrivono. KPI e liste sono modelli di lettura.

Nel codice, le regole stanno nelle funzioni private di `lib/Lab.php` (`startOperation`, `bookOperation`, `finishOperation`, `startDowntime`, `endDowntime`, `edgeSignal`, `setCycle`, `saveList`, `deleteList`). Il catalogo che la pagina Servizi mostra è `Lab::catalog`. Se aggiungi un servizio, lo aggiungi lì e in `dispatch`: altrimenti la pagina non lo elenca e la URL risponde «servizio sconosciuto».

## Ogni comando: come lo passiamo, come lo riceviamo, come lo lavoriamo

DLG, MST, LST e AUN non sono comandi che viaggiano in rete. Sono le pagine. Il comando che muove i dati è il servizio sotto il bottone. La pagina Servizi mostra, per ciascuno, le tre frasi qui sotto.

Tutti i servizi di scrittura fanno la stessa strada.

1. Il browser passa un POST a `index.php?r=/api/services/` più il nome, con `Content-Type: application/json` e il corpo dell'esempio.
2. `lib/app.php` riceve il nome e il JSON. Se il metodo non è POST o il corpo non è un oggetto, risponde 400 e `Lab` non parte. Se il nome non è nel catalogo, `Lab::call` risponde 404 prima di aprire la transazione.
3. `dispatch` lavora il corpo nel metodo dedicato, dentro una transazione. Il rifiuto annulla le scritture e lascia una riga `ok = 0` in `hxs_service_log`. L'esito ok fa commit, aggiunge `service` ed `effects` alla risposta, e scrive il giornale con `ok = 1`. Il browser poi rifà le GET.

| Comando | Lo passiamo così | Lo riceviamo così | Lo lavoriamo così |
| --- | --- | --- | --- |
| `operation.start` | POST, `operationCode`, `operatorCode` | `startOperation` | Fase `ready` e postazione `idle`: aggiorna fase, postazione e, se serve, l'ordine |
| `operation.book` | POST, `kind` `good` o `scrap`, `qty`, `reason` se scarto | `bookOperation` | Solo fase `running`: insert in `hxs_booking` |
| `operation.finish` | POST, `operationCode` | `finishOperation` | Solo fase `running`: fase `done`, postazione `idle`, ordine `done` se non restano fasi |
| `downtime.start` | POST, `workplaceCode`, `reasonCode` | `startDowntime` | Insert fermo; fase `running` diventa `held` |
| `downtime.end` | POST, `workplaceCode` | `endDowntime` | Chiude il fermo; la fase `held` torna `running` |
| `edge.signal` | POST, `signal`, `value` | `edgeSignal` | `piece_good` e `piece_scrap` diventano un versamento; `fault` e `clear` diventano il fermo |
| `workplace.cycle` | POST, `idealSec` | `setCycle` | Aggiorna `ideal_sec`. Il KPI cambia alla lettura dopo |
| `list.save` | POST, numero, titolo, base, colonne, filtro. Nessun SQL | `saveList` | Insert o update di `hxs_list` |
| `list.delete` | POST, `code` | `deleteList` | Cancella la definizione. LST-11 e LST-16 restano |
| `lab.demo` | POST, `confirm` = `DEMO` | `demo` | Turno noto: 6 buoni, 1 scarto, 10 minuti, fermo di 15 |
| `lab.reset` | POST, `confirm` = `RESET` | `reset` | Rimette le anagrafiche. Non tocca le liste né altri schemi |

Le letture non sono comandi di scrittura. Sono GET: `/api/status`, `/api/model`, `/api/workplaces`, `/api/orders`, `/api/people`, `/api/operations`, `/api/monitor`, `/api/kpi`, `/api/lists`, `/api/lists/LST-11`, `/api/objects/booking`, `/api/log`. Non aprono la transazione di un servizio e non inseriscono versamenti.

## I servizi, uno per uno

Causali scarto: `bava`, `quota`, `materiale`, `attrezzaggio`. Causali fermo: `guasto`, `attesa_materiale`, `attrezzaggio`, `mancanza_operatore`.

### operation.start

Corpo: `operationCode`, `operatorCode`.

Regole: la fase è `ready`. La postazione è `idle`.

Effetti: la fase passa a `running` con operatore e ora di avvio. La postazione passa a `running`. Se l'ordine era `released`, diventa `in_progress`.

Nel MOC questo era il dialogo di inizio lavorazione. In X è il servizio di avvio: Terminale e Monitor mostrano lo stesso stato perché leggono la stessa fase.

Prova: avvia `OF-24081-10` sul tornio, poi prova ad avviare `OF-24082-10` sullo stesso tornio. Il secondo avvio deve fallire con «non è libera».

### operation.book

Corpo: `operationCode`, `operatorCode`, `kind` (`good` o `scrap`), `qty` intero positivo. Se `kind` è `scrap`, serve `reason` con una causale dell'elenco.

Regole: la fase è `running`. Su `held` il versamento è rifiutato. Senza causale lo scarto è rifiutato.

Effetti: una riga in `hxs_booking`. La risposta ricalcola i totali della fase.

Nel VB.NET spesso la maschera sapeva il nome della tabella e faceva lei l'`INSERT`. Qui la maschera conosce solo il nome del servizio e il corpo. La tabella la conosce `bookOperation`.

### operation.finish

Corpo: `operationCode`.

Regole: la fase è `running`. Se è `held`, il messaggio chiede di chiudere prima il fermo.

Effetti: la fase va a `done`, la postazione torna `idle`. Se non restano fasi aperte, l'ordine va a `done`.

### downtime.start

Corpo: `workplaceCode`, `reasonCode`.

Regole: non c'è già un fermo aperto su quella postazione. La causale è nell'elenco.

Effetti: nasce il fermo. Se c'era una fase `running`, diventa `held`. La postazione va a `down`.

### downtime.end

Corpo: `workplaceCode`.

Regole: un fermo aperto deve esserci.

Effetti: `ended_at` viene riempito. La fase `held` torna `running` e la postazione pure. Se non c'era una fase sospesa, la postazione torna `idle`.

### edge.signal

Corpo: `workplaceCode`, `signal`, `value`.

| signal | Che cosa applica |
| --- | --- |
| `piece_good` | Un versamento `good` sulla fase `running` di quella postazione. `value` è la quantità. |
| `piece_scrap` | Un versamento `scrap` con causale `materiale`. |
| `fault` | `downtime.start`. `value` è la causale fermo, per esempio `guasto`. |
| `clear` | `downtime.end`. |

Senza una fase `running`, il pezzo contato non ha dove andare e il servizio rifiuta. Il segnale resta tracciato in `hxs_signal` solo se l'applicazione è riuscita, perché insert del segnale e applicazione stanno nella stessa transazione: se l'applicazione fallisce, si annulla anche la traccia.

In X il driver di bordo fa la stessa cosa: traduce il bit della macchina nel servizio giusto. Non scrive la tabella di versamento per conto suo.

Prova: dalla pagina Servizi, con una fase avviata, invia `edge.signal` con `piece_good` e `value` `1`. Poi apri Oggetti, tipo Versamento, e il giornale. Devi vedere il versamento e la riga `edge.signal` con esito ok.

### workplace.cycle

Corpo: `workplaceCode`, `idealSec` da 1 a 3600.

Effetto: aggiorna `ideal_sec`. Il KPI di performance cambia al ricalcolo successivo, perché la performance usa quel numero. È l'anagrafica MST spostata su un servizio.

### list.save

Corpo: `code` (un numero, `21` o `LST-21`), `title`, `base`, `columns`, `filter`.

Basi ammesse: `booking`, `order`, `operation`, `downtime`, `workplace`. Le colonne e i filtri sono solo quelli elencati in `Lab::listBases`. Una colonna fuori elenco, una base sconosciuta o un filtro `kind` diverso da `good`/`scrap` vengono rifiutati. Il corpo non ha un campo SQL.

Effetto: insert o update di `hxs_list`. La risposta è già la lista eseguita, cioè le righe proiettate.

`11` e `LST-11` sono lo stesso codice. Il banco normalizza a `LST-11`.

### list.delete

Corpo: `code`.

`LST-11` e `LST-16` non si cancellano: sono le liste di esempio. Le altre sì.

### lab.demo

Corpo: `{ "confirm": "DEMO" }`.

Parte solo se `OF-24081-10` è ancora `ready`. Altrimenti chiede prima `lab.reset`. Esegue, in una sola transazione: avvio tornitura, 6 buoni, 1 scarto per bava, chiusura fase, fermo di attesa materiale sulla fresatrice aperto e chiuso. Poi riscrive gli orari così il conto OEE è stabile: la tornitura dura 10 minuti, il fermo della fresatrice dura 15 minuti.

### lab.reset

Corpo: `{ "confirm": "RESET" }`.

Cancella segnali, giornale, versamenti, fermi, fasi, ordini, persone, postazioni. Rimette le anagrafiche. Le liste restano, comprese quelle che hai creato tu.

## Liste

`LST-11` Scarti. Base versamenti, filtro `kind = scrap`. Colonne: ora, postazione, fase, quantità, causale, operatore.

`LST-16` Avanzamento ordini. Base ordini, senza filtro. Colonne: ordine, materiale, stato, buoni, scarti, previsti, avanzamento.

Una lista nuova, per esempio la 21 «Fasi del tornio», è lo stesso gesto con cui oggi crei una LST: scegli il numero, il titolo, la base, le colonne, il filtro. La differenza da portare in X è che il client salva la definizione. Non incolla un `SELECT`.

La GET `/api/lists/LST-11` non è un servizio di scrittura. `runList` legge la definizione, prende le righe della base e tiene solo colonne e filtro ammessi. Per questo una lista non può leggere una tabella arbitraria.

## KPI, con i numeri da rifare a mano

Formula usata dal banco, in `Lab::kpi`:

- Disponibilità = tempo in lavoro / (tempo in lavoro + tempo di fermo), sulle ultime 12 ore, fuso Europe/Rome. Un turno caricato poco dopo mezzanotte resta intero.
- Performance = pezzi × tempo ciclo ideale / tempo in lavoro, al massimo 100%.
- Qualità = buoni / (buoni + scarti).
- OEE = prodotto dei tre.
- Se un fattore è zero, l'OEE di quella postazione è 0. Se manca il tempo e mancano i pezzi, l'OEE è vuoto (`n/d`), non 100.
- Il reparto somma i tempi di tutte le postazioni e ricalcola i tre fattori sul totale. Non fa la media degli OEE.

Dopo `lab.demo`, sul tornio: 10 minuti di lavoro, nessun fermo, 7 pezzi, ciclo 60 secondi, 6 buoni e 1 scarto.

- Disponibilità = 600 / 600 = 100%
- Performance = 7 × 60 / 600 = 70%
- Qualità = 6 / 7 = 85,7%
- OEE = 0,70 × 6/7 = 60%

Sulla fresatrice: 0 minuti di lavoro, 15 minuti di fermo, nessun pezzo. Disponibilità 0, OEE 0.

Di reparto: lavoro 600 secondi, fermo 900, pezzi ancora 7 di cui 6 buoni.

- Disponibilità = 600 / 1500 = 40%
- Performance = 420 / 600 = 70%
- Qualità = 85,7%
- OEE = 0,40 × 0,70 × 6/7 = 24%
- Tasso di scarto = 1 / 7 = 14,3%, causale Bava

Se alzi il ciclo ideale del tornio, la performance sale fino al tetto del 100% e poi resta lì. Il campo «performance al 100%» sulla card dice che i pezzi hanno superato il ciclo standard.

Nel MES vero il denominatore della disponibilità è spesso il tempo pianificato del turno, non «lavoro più fermo osservato». Tieniti la struttura a tre fattori e l'abitudine a ricostruire il numero dai fatti. Il calendario lo imparerai sull'istanza di test, non da questa formula.

## MySQL e Oracle

Le schermate non cambiano col database. Il default è MySQL, anche se sposti la cartella. `config.local.php` serve per un altro host, per Oracle, o per tornare a un file.

Su MySQL il banco crea da solo il database `hxs` e lancia `sql/mysql.sql`. Gli id sono `AUTO_INCREMENT`. La colonna `signal` è quotata perché in MySQL è una parola riservata.

Su Oracle esegui tu `sql/oracle.sql` connesso come `HXSIM`, mai come schema HYDRA. Gli id arrivano da sequenza e trigger `BEFORE INSERT`, perché lo script è compatibile con 11g e non usa `IDENTITY`. Il PHP, quando il driver è `oci`, legge l'id con `CURRVAL`.

Le colonne coincidono: stessi nomi, stesse lunghezze di massima, le date sono stringhe `YYYY-MM-DD HH:MM:SS` da 19 caratteri. Per questo le query del laboratorio (segnaposto `?`, niente SQL concatenato col filtro della lista) girano sui due motori. Il giorno in cui sposti il banco, copi i dati o rilanci il seed: non riscrivi Terminale e KPI.

Lo schema HYDRA 8 dell'azienda resta dove sta. `HXSIM` è un utente di laboratorio.

## Percorso di studio

Fallo in ordine. Alla fine di ogni punto apri Servizi e guarda il giornale. Un rifiuto di regola (fase occupata, scarto senza causale, versamento durante il fermo) compare con esito rifiutato, e le tabelle non restano a metà. Un nome di servizio inesistente risponde 404 prima della transazione e non entra nel giornale.

1. Da 8 a X, ripristina le anagrafiche. KPI: l'OEE di reparto è `n/d`. Non c'è un 100% di cortesia.
2. Terminale, tornio, avvia `OF-24081-10` con `op.01`. Monitor: la postazione è in lavoro e l'ordine è in corso. Hai visto due mApp sullo stesso oggetto.
3. Prova la seconda fase del tornio. Deve rifiutare. Leggi la frase, non solo il colore rosso.
4. Versa 5 buoni. Poi uno scarto senza causale: rifiuto. Poi uno scarto `bava`: in Oggetti, versamenti, due righe con `kind` diverso.
5. Apri un fermo `guasto` sul tornio. La fase diventa sospesa. Un versamento in quello stato deve fallire. Chiudi il fermo. La fase torna in lavoro. Chiudi la fase.
6. Dalla pagina Servizi manda `edge.signal` solo dopo aver avviato un'altra fase `running`. Controlla che il versamento abbia lo stesso aspetto di uno fatto col bottone Buoni. Il giornale mostra `edge.signal`, non un insert libero.
7. Carica il turno di esempio. Rifai a mano il 60% del tornio e il 24% di reparto, con i conti del capitolo KPI. Se i numeri non tornano, la formula che hai in testa non è quella del servizio.
8. Liste. Apri LST-11: una riga, Bava, `TORN-01`. Salva la 21 sulle fasi del tornio, filtro postazione `TORN-01`. Devono restare due fasi, non quattro. In `hxs_list` c'è la definizione. I pezzi restano in `hxs_booking` e `hxs_operation`.
9. Cambia il ciclo del tornio da 60 a 120 e ricarica il KPI. La performance del tornio dimezza il rapporto pezzi/tempo (il tetto è 100%). Hai modificato un'anagrafica e la valutazione si è mossa da sola.
10. Pagina Servizi: prendi l'esempio di `operation.book`, cambia un campo, invia. È la stessa chiamata del bottone. Quando sarai su X, il catalogo servizi della piattaforma si legge così: nome, corpo, esito.

## Quando aprirai l'istanza vera

Il primo giorno non cercare DLG, MST, LST-11 o le tabelle `hxs_*`. Cerca queste quattro cose.

- Il catalogo dei servizi: nome, corpo, che oggetto restituisce, che cosa rifiuta.
- Il giornale di una chiamata andata male. Se il rifiuto non si ritrova, non sai ancora dove vive la regola.
- Due mApp sulla stessa fase. Un versamento fatto in una deve vedersi nell'altra senza che la seconda abbia una copia privata.
- Il punto in cui entra il segnale macchina. Deve nominare un servizio. Se scrive una tabella saltando il servizio, è il vecchio gesto del PCC o del VB.NET, anche se la maschera è nel browser.

L'istanza su cui esercitarti in azienda è una di test, staccata dalla linea. I corsi che corrispondono ai pezzi di questo banco sono DT-MBO per il modello, DT-OSD per oggetti e servizi, DT-UI per le mApp, DT-DEC per i driver macchina in C#. Il banco ti serve finché quei corsi non ci sono: ogni pomeriggio rifai i dieci punti e racconti, a voce, quale servizio ha scritto e quale pagina ha solo riletto.
