# Lumina

Agenda operativa per parrucchieri, palestre e studi. PHP 8.5, MySQL 8, nessuna dipendenza da Composer oltre al runtime.

Gli orari si scrivono nel fuso dell'attività (in demo, Europe/Roma) e si conservano in UTC. Uno slot è **ideale** quando riempie esattamente il buco tra due impegni, **spezzato** quando lascia meno di 30 minuti. Il prezzo vivo applica solo lo sconto più alto della fascia, senza cumularli. Una disdetta offre il posto al primo in lista compatibile, per 180 minuti o fino all'orario. La caparra è un avviso in agenda, non un pagamento. Le notifiche restano nell'applicazione: non partono email né SMS.

## Avvio

```bash
cd lumina
php bin/setup.sh
php bin/serve.sh
```

Poi apri http://127.0.0.1:8080

`setup.sh` applica lo schema se manca e carica la demo. `php bin/seed.php --fresh` la ricrea. `php bin/selftest.php` esegue i controlli sul database `lumina_test`.

## Dentro htdocs

Lumina può stare in `C:\xampp\htdocs\lumina` e aprirsi su http://localhost/lumina/ insieme alle altre cartelle. Apache deve già parlare PHP 8.5.12: copiare la cartella prima, con un PHP più vecchio, mostra solo il messaggio di versione. Gli altri siti restano com'erano.

1. Ferma Apache, rinomina `C:\xampp\php` in `php-vecchio` e scompatta PHP 8.5.12 Thread Safe x64 VS17 in `C:\xampp\php`. Il `php.ini` vecchio non si ricopia: Xdebug e altre direttive di quella versione impediscono l'avvio. Parti da `php.ini-development` rinominato in `php.ini`, con `extension_dir = "C:\xampp\php\ext"`, il fuso `Europe/Rome` e le estensioni `curl`, `gd`, `intl`, `mbstring`, `mysqli`, `openssl`, `pdo_mysql`, `zip`. `session.save_path` deve puntare a una cartella che esiste, di solito `C:\xampp\tmp`. Xdebug si rimette dopo, con la DLL `php_xdebug-3.5.3-8.5-ts-vs17-x86_64`, non con il file di prima. Se Apache non parte, rimetti `php-vecchio`.
2. Copia l'intera cartella `lumina` in `C:\xampp\htdocs\lumina`. Non copiare solo `public`.
3. Avvia MySQL e lancia una volta `C:\xampp\php\php.exe bin\xampp.php` dentro quella cartella. Crea i database e si chiude. Non avvia un secondo sito.
4. Avvia Apache e apri http://localhost/lumina/

Il logo e il colore si cambiano da Impostazioni, nella scheda pubblica. La pagina del cliente è `http://localhost/lumina/b/nome-attivita`.

## XAMPP e Lumina insieme

I due convivono. Apache di XAMPP continua a servire `htdocs` su http://localhost. Lumina è un secondo programma, sulla porta 8080, e usa lo stesso MariaDB. Non si reinstallano XAMPP né si tocca `httpd.conf`.

1. Dal pannello XAMPP avvia Apache e MySQL. Controlla che i siti di sempre si aprano su http://localhost.
2. Nella cartella `lumina` lancia `bin\xampp.bat` e lascia la finestra aperta.
3. Apri http://127.0.0.1:8080

Lo script crea solo i database `lumina` e `lumina_test` e l'utente `lumina` / `lumina_local`. Gli altri database restano.

Lumina chiede PHP 8.5.12. Se il PHP dentro XAMPP è già quello, `xampp.bat` lo usa soltanto per la porta 8080: Apache sulla porta 80 non cambia. Se è più vecchio, scarica lo zip di PHP 8.5.12 da https://windows.php.net/download/ e scompattalo in `C:\php85`, fuori da `C:\xampp`. Poi rilancia `bin\xampp.bat`. Le build 8.2.35, 8.3.36 e 8.4.27 chiudono le falle del 21 ottobre 2026, ma questa agenda usa la sintassi di PHP 8.5, quindi il PHP vecchio resta alle app di `htdocs`.

Se un lancio precedente aveva aggiunto un virtual host e Apache non parte, rimetti `C:\xampp\apache\conf\httpd.conf.lumina-bak` al posto di `httpd.conf` e cancella `C:\xampp\apache\conf\extra\httpd-lumina.conf`. Poi riavvia Apache.

Se root di MariaDB ha una password: `set XAMPP_MYSQL_ROOT_PASSWORD=...` prima di lanciare lo script. Se il PHP 8.5 sta in un'altra cartella: `set LUMINA_PHP=D:\php\php.exe`.

Database locale previsto: `lumina` su `127.0.0.1`, utente `lumina`, password `lumina_local`. Variabili opzionali: `LUMINA_DB_HOST`, `LUMINA_DB_PORT`, `LUMINA_DB_NAME`, `LUMINA_DB_USER`, `LUMINA_DB_PASS`. Con `LUMINA_DEBUG=1` gli errori finiscono nella pagina.

## Demo

Password di tutti gli account: `Lumina-demo-2026`

| Chi | Email | Cosa vede |
| --- | --- | --- |
| Giulia, Atelier Luce | giulia@atelierluce.test | Agenda del salone |
| Marco, collaboratore | marco@atelierluce.test | Operatività, senza listino |
| Elena, Palestra Borgo | elena@palestraborgo.test | Agenda della palestra |
| Bianca, cliente | bianca@email.test | Portale |
| Davide | — | In salone, senza login |

Pagine pubbliche: `/b/atelier-luce` e `/b/palestra-borgo`. Nel salone, il trattamento lampo delle 16 nel primo pomeriggio aperto è lo slot ideale tra un taglio e un colore.

## Ingresso

La registrazione chiede un codice di 6 cifre, valido 10 minuti, monouso, con al massimo 5 tentativi. In alternativa ci sono Google e Apple, se l'installazione ha le chiavi. Un'email non confermata non viene agganciata a un account già presente.

Senza `LUMINA_SMTP_HOST` la lettera resta nella pagina del codice: è la posta locale, non un invio vero. Con SMTP il codice parte e non viene più mostrato.

```
LUMINA_PUBLIC_URL=https://agenda.example
LUMINA_APP_KEY=una-stringa-lunga-almeno-32-caratteri
LUMINA_GOOGLE_CLIENT_ID=
LUMINA_GOOGLE_CLIENT_SECRET=
LUMINA_APPLE_CLIENT_ID=
LUMINA_APPLE_TEAM_ID=
LUMINA_APPLE_KEY_ID=
LUMINA_APPLE_PRIVATE_KEY=/percorso/AuthKey.p8
LUMINA_SMTP_HOST=
LUMINA_SMTP_PORT=587
LUMINA_SMTP_USER=
LUMINA_SMTP_PASS=
LUMINA_MAIL_FROM=noreply@example
```

URI da registrare: `/auth/google/ritorno` e `/auth/apple/ritorno`.

## Piani

IVA esclusa. Bottega 0 €: 1 persona, 40 prenotazioni al mese, slot ideale. Salone 29 € al mese o 290 € l'anno: 8 persone, prezzo vivo, lista, tessere, corsi, export. Borgo 79 € al mese o 790 € l'anno: 40 persone, analisi, regole di prezzo senza tetto. L'anno costa dieci mesi.

Il piano si attiva subito e i limiti valgono. Non parte alcun addebito: il pagamento online non è collegato. In demo Atelier Luce è Salone, Palestra Borgo è Borgo.

## Cosa non fa

Un solo locale per attività. Niente pagamenti online, niente SMS, niente account staff che modifica prezzi o impostazioni. Le email partono solo se configuri l'SMTP.
