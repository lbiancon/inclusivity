# Banco X

Laboratorio locale per allenare il gesto di **HYDRA X** mentre in azienda si è ancora su **HYDRA 8** con Oracle.

La guida di studio è [STUDIO.md](STUDIO.md): che cosa è stato creato, che cosa fa ogni servizio, come è implementato, e che cosa di questo gesto ritrovi in HYDRA X tra qualche mese o a fine 2027.

Non è il software MPDV. Non si collega allo schema HYDRA. Terminale e Monitor sono due pagine sullo stesso modello: un'azione è un servizio, e l'altra pagina rilegge gli stessi oggetti. Un segnale macchina non scrive il versamento da solo: passa dalla stessa logica.

I nomi HYDRA, MOC, MIP e ViPR sono di MPDV. Qui il modello si chiama Banco X e le tabelle hanno il prefisso `hxs_`.

## In htdocs

Serve PHP 8.1 o successivo, con `pdo_mysql`. Il database non nasce nella cartella del sito.

1. Nel pannello XAMPP avvia MySQL e Apache.
2. In `C:\xampp\php\php.ini` togli il `;` da `extension=pdo_mysql` e riavvia Apache.
3. Copia questa cartella in htdocs, per esempio `C:\xampp\htdocs\hydra-x-sim`.
4. Apri `http://localhost/hydra-x-sim/`.

La prima richiesta crea il database MySQL `hxs` su `127.0.0.1:3306`, utente `root`, password vuota, e ci mette tornio, fresatrice, assemblaggio, due ordini e le liste LST-11 e LST-16. Spostare la cartella non sposta questi dati e non crea un altro database: la riga in alto deve dire `MySQL · database hxs su 127.0.0.1:3306`.

Un file `config.local.php` serve solo per un host o una password diversi, oppure per passare a Oracle (`driver` impostato a `oci`, poi `sql/oracle.sql` eseguito come utente `HXSIM`). `driver` impostato a `sqlite` è l'unica scelta che scrive `data/sim.sqlite`.

## MySQL, con le stesse colonne di Oracle

Su XAMPP il passo utile è MySQL. Le tabelle di `sql/mysql.sql` hanno gli stessi nomi e le stesse colonne di `sql/oracle.sql`. Quando sposti, esegui lo script Oracle: il banco non cambia schermate.

1. Avvia MySQL dal pannello XAMPP.
2. In `C:\xampp\php\php.ini` togli il `;` da `extension=pdo_mysql` e riavvia Apache.
3. Copia `config.example.php` in `config.local.php`.
4. Riapri `http://localhost/hydra-x-sim/`. La prima richiesta crea il database `hxs` e le tabelle.

Se la riga in alto dice ancora un percorso che finisce in `sim.sqlite`, nella cartella c'è un `config.local.php` con `driver` sqlite. Toglilo, oppure metti `mysql`.

DLG è il Terminale, MST sono gli Oggetti, LST sono le Liste (11 e 16 già pronte, altre si salvano con un numero), AUN è la pagina KPI: OEE e scarti. Il dialogo non scrive SQL. Chiama un servizio, e la lista salva solo colonne e filtro.

Se `sim.sqlite` non compare, apri `http://localhost/hydra-x-sim/controllo.php`. Quella pagina dice se PHP è troppo vecchio, se manca `pdo_sqlite`, o se Apache non può scrivere. Una pagina bianca su `index.php` di solito è PHP inferiore a 8.1: lo script si interrompe prima della scrittura.

Su Windows, in `C:\xampp\php\php.ini`, `extension=pdo_sqlite` e `extension=sqlite3` devono essere senza punto e virgola iniziale. Dopo la modifica: Stop e Start di Apache nel pannello XAMPP. In alternativa fai doppio clic su `avvia.bat` e apri `http://localhost:8765/controllo.php`.

Prova rapida senza Apache, da questa cartella:

```bash
php -S localhost:8765 serve.php
```

Poi apri `http://localhost:8765/`.

## Oracle, a parte da HYDRA

Il database di HYDRA 8 non va usato. Crea un utente dedicato:

```sql
CREATE USER HXSIM IDENTIFIED BY "una-password";
GRANT CREATE SESSION, CREATE TABLE, CREATE SEQUENCE, CREATE TRIGGER TO HXSIM;
ALTER USER HXSIM QUOTA UNLIMITED ON USERS;
```

Connesso come `HXSIM`, esegui `sql/oracle.sql` (SQL*Plus o SQLcl). Poi copia `config.example.php` in `config.local.php` e metti DSN, utente e password.

PHP deve avere `pdo_oci` e Oracle Instant Client. Se mancano, la pagina lo dice e non crea un file SQLite al posto di Oracle. Al primo avvio, se le tabelle `hxs_*` sono vuote, partono le anagrafiche di esempio.

## Cosa simula, e cosa no

Simula il passaggio che ti serve da key user a chi sviluppa verso X:

- le mApp non si tengono i dati in proprio
- il comando è un servizio con effetti espliciti (`operation.start`, `operation.book`, fermi, `edge.signal`)
- il giornale mostra la chiamata, anche quando viene rifiutata

Non simula il MOC, il PCC, i corsi MPDV, né le tabelle vere di HYDRA. In azienda il percorso resta un'istanza di test, poi — se il bersaglio è X — DT-MBO, DT-UI o DT-OSD, e DT-DEC per le macchine.

## Controlli

```bash
php tests/run.php
```
