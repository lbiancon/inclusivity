# Banco X

Laboratorio locale per allenare il gesto di **HYDRA X** mentre in azienda si è ancora su **HYDRA 8** con Oracle.

Non è il software MPDV. Non si collega allo schema HYDRA. Terminale e Monitor sono due pagine sullo stesso modello: un'azione è un servizio, e l'altra pagina rilegge gli stessi oggetti. Un segnale macchina non scrive il versamento da solo: passa dalla stessa logica.

I nomi HYDRA, MOC, MIP e ViPR sono di MPDV. Qui il modello si chiama Banco X e le tabelle hanno il prefisso `hxs_`.

## In htdocs

Serve PHP 8.1 o successivo, con `pdo_sqlite`.

1. Copia questa cartella in htdocs. Su XAMPP: `C:\xampp\htdocs\hydra-x-sim`.
2. La cartella `data/` deve essere scrivibile da Apache.
3. Apri `http://localhost/hydra-x-sim/`.

Senza file di configurazione i dati stanno in `data/sim.sqlite`. Il primo avvio inserisce un tornio, una fresatrice, un assemblaggio e due ordini.

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

PHP deve avere `pdo_oci` e Oracle Instant Client. XAMPP spesso non li ha: in quel caso il banco resta su SQLite e il modello non cambia. Al primo avvio, se le tabelle `hxs_*` sono vuote, partono le anagrafiche di esempio.

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
