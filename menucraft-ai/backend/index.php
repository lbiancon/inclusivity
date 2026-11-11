<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MenuCraft · Banco</title>
    <link rel="stylesheet" href="../assets/app.css">
    <style>
        [hidden] { display: none !important; }
        .fascia-verde { background: #d9f2c7; }
        .fascia-gialla { background: #fff3b0; }
        .fascia-azzurra { background: #b7f0f6; }
        .lettera { display: inline-flex; min-width: 1.7rem; align-items: center; justify-content: center; border-radius: 0.4rem; padding: 0.05rem 0.35rem; font-weight: 700; }
        .lettera-verde { background: #8dce78; }
        .lettera-gialla { background: #ffe56a; }
        .lettera-azzurra { background: #67dff0; }
        body { font-family: "Source Sans 3", ui-sans-serif, sans-serif; background: #efe6d2; color: #1d2420; }
        h1, h2, .font-serif { font-family: "Barlow Condensed", Impact, sans-serif; }
        .counter { background: #1d4a38; color: #f6f3ea; }
        .logo-azienda {
            width: 3rem !important;
            height: 3rem !important;
            max-width: 3rem !important;
            max-height: 3rem !important;
            object-fit: contain;
            object-position: center;
            flex-shrink: 0;
            background: #fff;
            border-radius: 1rem;
            padding: 0.25rem;
            box-shadow: 0 0 0 2px #fff;
        }
        .accesso-salvato { position: relative; }
        .accesso-salvato .togli-accesso {
            position: absolute;
            top: 0.35rem;
            right: 0.35rem;
            border: 0;
            background: transparent;
            color: rgba(29, 36, 32, 0.45);
            font-size: 0.75rem;
            line-height: 1;
            padding: 0.2rem;
        }
        #banner.is-toast {
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            z-index: 40;
            width: min(24rem, calc(100% - 2rem));
            box-shadow: 0 12px 30px rgba(29, 36, 32, 0.18);
        }
    </style>
</head>
<body class="min-h-screen bg-cream text-ink">
    <div id="login-view" class="mx-auto flex min-h-screen max-w-3xl items-center px-4 py-10">
        <section class="w-full rounded-[2rem] bg-white p-8 shadow-xl shadow-ink/5">
            <p class="text-xs uppercase tracking-[0.22em] text-ink/50">Backend · banco cucina</p>
            <h1 class="mt-2 font-serif text-4xl uppercase">Azienda, matricola, password</h1>
            <p class="mt-2 text-sm text-ink/70">Il catering gestisce la propria cucina. Chi vende il servizio entra con l’azienda MenuCraft e apre una società nuova, con le persone che prenotano.</p>
            <form id="login-form" class="mt-6 space-y-4">
                <label class="block text-sm font-medium" for="slug">Azienda
                    <input id="slug" name="slug" autocomplete="organization" required class="mt-1 w-full rounded-2xl border border-ink/10 bg-cream px-4 py-3 outline-none ring-terracotta focus:ring-2" placeholder="aurora-welfare">
                </label>
                <label class="block text-sm font-medium" for="matricola">Matricola
                    <input id="matricola" name="matricola" autocomplete="username" required class="mt-1 w-full rounded-2xl border border-ink/10 bg-cream px-4 py-3 outline-none ring-terracotta focus:ring-2" placeholder="CAT-A">
                </label>
                <label class="block text-sm font-medium" for="password">Password
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-2xl border border-ink/10 bg-cream px-4 py-3 outline-none ring-terracotta focus:ring-2">
                </label>
                <button type="submit" class="w-full rounded-2xl bg-moss px-4 py-3 font-semibold text-white">Entra nel banco</button>
            </form>
            <div id="login-error" hidden class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-900" role="alert"></div>
            <div class="mt-6 space-y-3">
                <p class="text-xs uppercase tracking-[0.18em] text-ink/50">Servizio</p>
                <button type="button" id="accesso-servizio" class="w-full rounded-2xl border border-ink/10 px-3 py-3 text-left" data-slug="menucraft" data-matricola="ADMIN" data-password="MenuCraft!2026">
                    <span class="block text-sm font-medium">Servizio MenuCraft</span>
                    <span class="block text-xs text-ink/60">Apre e gestisce le cucine · password MenuCraft!2026</span>
                </button>
                <p class="text-xs uppercase tracking-[0.18em] text-ink/50">Banchi salvati su questo dispositivo</p>
                <div id="accessi-salvati" class="grid gap-2 sm:grid-cols-2"></div>
                <p id="accessi-vuoti" class="text-sm text-ink/60">Dopo l’accesso di un catering puoi salvare qui azienda e matricola.</p>
            </div>
        </section>
    </div>

    <div id="app-view" hidden>
        <header class="counter sticky top-0 z-20">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                <div class="flex items-center gap-3">
                    <img id="azienda-logo" alt="" hidden class="logo-azienda">
                    <div>
                        <p class="text-xs uppercase tracking-[0.22em] text-[#d7eadf]">Backend</p>
                        <h1 id="azienda-nome" class="font-serif text-3xl uppercase"></h1>
                    </div>
                </div>
                <button id="logout" type="button" class="rounded-full border border-white/30 px-4 py-2 text-sm text-white">Esci</button>
            </div>
        </header>
        <div id="banner" hidden role="alert" class="is-toast rounded-3xl border p-4"></div>
        <main class="mx-auto max-w-6xl space-y-4 px-4 py-6">
            <nav id="sezioni" hidden class="flex flex-wrap gap-2">
                <button type="button" class="sezione rounded-full bg-moss px-4 py-2 text-sm font-semibold text-white" data-sezione="regole">Regole</button>
                <button type="button" class="sezione rounded-full border border-ink/15 bg-white px-4 py-2 text-sm font-semibold" data-sezione="menu">Menu</button>
                <button type="button" class="sezione rounded-full border border-ink/15 bg-white px-4 py-2 text-sm font-semibold" data-sezione="utenti">Utenti</button>
                <button type="button" class="sezione rounded-full border border-ink/15 bg-white px-4 py-2 text-sm font-semibold" data-sezione="cucina">Cucina</button>
            </nav>
            <section id="servizio" hidden class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                <h2 class="font-serif text-3xl uppercase">Nuova società / cucina</h2>
                <p class="mt-2 text-sm text-ink/70">Nasce l’azienda, con l’acqua obbligatoria e il primo catering. Poi quel catering crea le persone che prenotano.</p>
                <form id="societa-form" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-semibold">Nome società
                        <input id="societa-nome" required maxlength="120" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                    </label>
                    <label class="text-sm font-semibold">Codice azienda
                        <input id="societa-slug" required maxlength="60" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2" placeholder="nuova-cucina">
                    </label>
                    <label class="text-sm font-semibold">Chi paga di default
                        <select id="societa-pagamento" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                            <option value="welfare">A carico dell’azienda</option>
                            <option value="dipendente">Paga il dipendente</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Dove prenotano
                        <select id="societa-modalita" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                            <option value="intranet">Intranet</option>
                            <option value="internet">Internet</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Nome catering
                        <input id="societa-caterer-nome" required maxlength="120" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                    </label>
                    <label class="text-sm font-semibold">Matricola catering
                        <input id="societa-caterer-matricola" required maxlength="32" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2" placeholder="CAT-1">
                    </label>
                    <label class="text-sm font-semibold sm:col-span-2">Password catering
                        <input id="societa-caterer-password" required minlength="10" maxlength="72" type="password" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                    </label>
                    <button type="submit" class="rounded-xl bg-moss px-4 py-3 text-sm font-semibold text-white sm:col-span-2">Crea la cucina</button>
                </form>
                <p class="mt-4 text-sm text-ink/60">Le cucine create restano in questo elenco. Per entrare nel banco di una cucina fai Esci e accedi con il suo codice azienda e la matricola del catering. Su ogni riga puoi anche caricare il logo.</p>
                <p id="societa-conteggio" class="mt-3 text-sm font-semibold text-ink/80"></p>
                <ul id="societa-elenco" class="mt-2 space-y-2 text-sm"></ul>
            </section>
            <div id="banco" class="space-y-4">
            <form id="regole-form" data-pannello="regole" class="space-y-4">
                <section class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                    <h2 class="font-serif text-3xl uppercase">Dove gira questa azienda</h2>
                    <p id="modalita-testo" class="mt-2 text-sm text-ink/70"></p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="rounded-2xl border border-ink/10 bg-cream p-4 text-sm">
                            <input id="modalita-intranet" name="modalita" type="radio" value="intranet" class="mr-2"> Intranet
                            <span class="mt-1 block text-ink/60">Oggi si prenota in mensa fino all’orario di chiusura. Il file cucina lo scarichi e lo mandi con l’applicazione che usi in azienda.</span>
                        </label>
                        <label class="rounded-2xl border border-ink/10 bg-cream p-4 text-sm">
                            <input id="modalita-internet" name="modalita" type="radio" value="internet" class="mr-2"> Internet
                            <span class="mt-1 block text-ink/60">Il pasto di oggi è chiuso. Si prenota da domani in poi, con lo stesso blocco serale. Il file cucina si scarica anche da fuori sede e si manda con l’applicazione esterna.</span>
                        </label>
                    </div>
                </section>
                <section class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                    <h2 class="font-serif text-3xl uppercase">Quando si carica il menu</h2>
                    <p class="mt-2 text-sm text-ink/70">Non è una regola fissa del prodotto: ogni servizio sceglie se aggiornare giorno per giorno oppure solo il file della settimana il giovedì sera.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="rounded-2xl border border-ink/10 bg-cream p-4 text-sm">
                            <input id="carica-sempre" name="caricamento_menu" type="radio" value="sempre" class="mr-2"> Quando serve
                            <span class="mt-1 block text-ink/60">Puoi aggiornare il menu giorno per giorno dal tab Menu, oppure caricare un file settimanale o mensile in qualsiasi momento.</span>
                        </label>
                        <label class="rounded-2xl border border-ink/10 bg-cream p-4 text-sm">
                            <input id="carica-giovedi" name="caricamento_menu" type="radio" value="giovedi" class="mr-2"> Solo giovedì dopo le 19:00
                            <span class="mt-1 block text-ink/60">Il file della settimana successiva si carica solo il giovedì sera. Il menu del giorno si può comunque ritoccare dal tab Menu.</span>
                        </label>
                    </div>
                </section>
                <section class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                    <h2 class="font-serif text-3xl uppercase">Regole del vassoio</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="text-sm font-semibold">Minimo scelte, acqua inclusa se obbligatoria
                            <input id="regola-min" name="min_scelte" type="number" min="1" max="10" required class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                        </label>
                        <label class="text-sm font-semibold">Massimo scelte, acqua inclusa se obbligatoria
                            <input id="regola-max" name="max_scelte" type="number" min="1" max="10" required class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                        </label>
                        <label class="text-sm font-semibold">Chiusura di oggi, solo intranet
                            <input id="regola-oggi" name="limite_oggi" type="time" required class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                        </label>
                        <label class="text-sm font-semibold">Blocco del giorno prima
                            <input id="regola-domani" name="limite_domani" type="time" required class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                        </label>
                        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input id="regola-bevanda" type="checkbox"> Acqua obbligatoria e conta nelle scelte</label>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-wider text-ink/50">
                                    <th class="py-2 pr-3">Portata</th>
                                    <th class="py-2 pr-3">Massimo, il doppio è 2</th>
                                    <th class="py-2">Anche lo stesso piatto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="py-2 pr-3 font-semibold">Primo</td>
                                    <td class="py-2 pr-3"><input id="regola-max-primo" type="number" min="1" max="4" required class="w-20 rounded-xl border border-ink/15 bg-cream px-3 py-2"></td>
                                    <td class="py-2"><input id="regola-primo" type="checkbox" aria-label="Stesso primo due volte"></td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-3 font-semibold">Secondo</td>
                                    <td class="py-2 pr-3"><input id="regola-max-secondo" type="number" min="1" max="4" required class="w-20 rounded-xl border border-ink/15 bg-cream px-3 py-2"></td>
                                    <td class="py-2"><input id="regola-secondo" type="checkbox" aria-label="Stesso secondo due volte"></td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-3 font-semibold">Contorno</td>
                                    <td class="py-2 pr-3"><input id="regola-max-contorno" type="number" min="1" max="4" required class="w-20 rounded-xl border border-ink/15 bg-cream px-3 py-2"></td>
                                    <td class="py-2"><input id="regola-contorno" type="checkbox" aria-label="Stesso contorno due volte"></td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-3 font-semibold">Frutta</td>
                                    <td class="py-2 pr-3"><input id="regola-max-frutta" type="number" min="1" max="4" required class="w-20 rounded-xl border border-ink/15 bg-cream px-3 py-2"></td>
                                    <td class="py-2"><input id="regola-frutta" type="checkbox" aria-label="Stessa frutta due volte"></td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-3 font-semibold">Dolce</td>
                                    <td class="py-2 pr-3"><input id="regola-max-dessert" type="number" min="1" max="4" required class="w-20 rounded-xl border border-ink/15 bg-cream px-3 py-2"></td>
                                    <td class="py-2"><input id="regola-dessert" type="checkbox" aria-label="Stesso dolce due volte"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-sm text-ink/60">Con acqua obbligatoria, minimo e massimo valgono acqua inclusa: per il gestore senza pagamento al bancone di solito sono 5 e 5. L’acqua da sola non chiude il buono. Nella tabella, il numero è quante porzioni di quella portata entrano; la casella consente due volte lo stesso piatto.</p>
                </section>
                <section class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                    <h2 class="font-serif text-3xl uppercase">Messaggi per chi prenota</h2>
                    <p class="mt-2 text-sm text-ink/70">Queste indicazioni compaiono in linea. Scrivi orari speciali, un numero da chiamare o un WhatsApp se la linea è già chiusa, e altre regole utili.</p>
                    <div class="mt-4 grid gap-3">
                        <label class="text-sm font-semibold">Messaggio di guida
                            <textarea id="regola-guida" name="guida_linea" rows="4" maxlength="800" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2 font-normal" placeholder="Esempio: dopo le 9:30 chiama o scrivi su WhatsApp per prenotare."></textarea>
                        </label>
                        <label class="text-sm font-semibold">Telefono o WhatsApp
                            <input id="regola-contatto" name="contatto_linea" maxlength="120" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2" placeholder="+39 333 1234567">
                        </label>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                        <label class="rounded-2xl border border-ink/10 bg-cream p-4">
                            <input id="paga-azienda" name="tipo_pagamento" type="radio" value="welfare" class="mr-2"> A carico dell’azienda
                        </label>
                        <label class="rounded-2xl border border-ink/10 bg-cream p-4">
                            <input id="paga-dipendente" name="tipo_pagamento" type="radio" value="dipendente" class="mr-2"> Paga il dipendente
                        </label>
                    </div>
                    <label class="mt-4 block text-sm font-semibold">Logo dell’azienda
                        <span class="mt-1 block text-xs font-normal text-ink/60">Viene ridimensionato in un quadrato uguale per tutte le aziende, così resta allineato all’intestazione.</span>
                        <input id="logo-file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="mt-1 block w-full text-sm">
                    </label>
                    <button type="submit" class="mt-4 w-full rounded-xl bg-moss px-4 py-3 text-sm font-semibold text-white">Salva sul backend</button>
                </section>
            </form>
            <section data-pannello="cucina" hidden class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                <h2 class="font-serif text-3xl uppercase">File per la cucina</h2>
                <p id="cucina-testo" class="mt-2 text-sm text-ink/70"></p>
                <label class="mt-4 block text-sm font-semibold">Giorno
                    <input id="giorno-cucina" type="date" class="mt-1 rounded-xl border border-ink/15 bg-cream px-3 py-2">
                </label>
                <button id="scarica-riepilogo" type="button" class="mt-4 rounded-xl bg-terracotta px-4 py-3 text-sm font-semibold text-white">Scarica il file</button>
            </section>
            <section data-pannello="menu" hidden class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                <h2 class="font-serif text-3xl uppercase">Menu del giorno</h2>
                <p class="mt-2 text-sm text-ink/70">Qui componi il menu che vede chi prenota. Ogni pasto tiene il codice già in uso e, accanto, la lettera del giorno: togliendo o aggiungendo un piatto le lettere ripartono da A B C, senza saltare a Q.</p>
                <label class="mt-4 block text-sm font-semibold">Giorno del menu
                    <input id="menu-giorno" type="date" class="mt-1 rounded-xl border border-ink/15 bg-cream px-3 py-2">
                </label>
                <ul id="menu-elenco" class="mt-4 space-y-2 text-sm"></ul>
                <form id="menu-esistente" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                    <label class="text-sm font-semibold">Dal catalogo
                        <select id="menu-catalogo" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2"></select>
                    </label>
                    <button type="submit" class="self-end rounded-xl bg-moss px-4 py-3 text-sm font-semibold text-white">Aggiungi al giorno</button>
                </form>
                <form id="menu-nuovo" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-semibold">Nome nuovo piatto
                        <input id="piatto-nome" maxlength="120" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                    </label>
                    <label class="text-sm font-semibold">Codice, se ne hai già uno
                        <input id="piatto-codice" maxlength="32" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2" placeholder="PR-POM">
                    </label>
                    <label class="text-sm font-semibold">Portata
                        <select id="piatto-categoria" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                            <option value="primo">Primo</option>
                            <option value="secondo">Secondo</option>
                            <option value="contorno">Contorno</option>
                            <option value="dessert">Dolce</option>
                            <option value="yogurt">Yogurt</option>
                            <option value="frutta">Frutta</option>
                            <option value="bevanda">Bevanda</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Prezzo
                        <input id="piatto-prezzo" inputmode="decimal" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2" placeholder="3,50">
                    </label>
                    <button type="submit" class="rounded-xl border border-ink px-4 py-3 text-sm font-semibold sm:col-span-2">Crea e metti nel giorno</button>
                </form>
                <h3 class="mt-6 font-serif text-2xl uppercase">File settimanale o mensile</h3>
                <div id="upload-window" class="mt-3 rounded-2xl border border-[#d5cbb8] bg-cream px-4 py-3 text-sm font-medium text-ink"></div>
                <p class="mt-2 text-sm text-ink/60">Carica un CSV o Excel: il menu della settimana o del mese entra in automatico. I modelli pronti si scaricano qui sotto. Un .xls vecchio va salvato come .xlsx. La finestra di caricamento dipende dalla regola scelta in Regole.</p>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <a class="rounded-full border border-ink/15 px-3 py-2 font-semibold" href="../esempi/menu-settimanale.csv" download>Scarica menu settimanale</a>
                    <a class="rounded-full border border-ink/15 px-3 py-2 font-semibold" href="../esempi/menu-mensile.csv" download>Scarica menu mensile</a>
                </div>
                <form id="upload-form" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input id="menu-file" name="menu" type="file" accept=".csv,.txt,.xlsx,.xls" class="block w-full text-sm">
                    <button type="submit" class="rounded-2xl bg-ink px-4 py-3 text-sm text-white">Carica menu</button>
                </form>
            </section>
            <section data-pannello="utenti" hidden class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                <h2 class="font-serif text-3xl uppercase">Chi può prenotare</h2>
                <p class="mt-2 text-sm text-ink/70">Ogni persona entra dal frontend con l’azienda, la matricola e la password. Pagante usa il wallet. A carico azienda resta a 0,00 €. Eredita segue la regola della società.</p>
                <form id="utente-form" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-semibold">Nome
                        <input id="utente-nome" required maxlength="120" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                    </label>
                    <label class="text-sm font-semibold">Matricola
                        <input id="utente-matricola" required maxlength="32" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                    </label>
                    <label class="text-sm font-semibold">Password
                        <input id="utente-password" minlength="10" maxlength="72" type="password" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2" placeholder="Obbligatoria solo in creazione">
                    </label>
                    <label class="text-sm font-semibold">Pagamento
                        <select id="utente-pagamento" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                            <option value="eredita">Eredita dall’azienda</option>
                            <option value="busta">Impiegato · scala in busta paga</option>
                            <option value="buono">Operaio · paga con buono</option>
                            <option value="pagante">Pagante con wallet</option>
                            <option value="welfare">Sempre a carico azienda</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Saldo iniziale, se paga
                        <input id="utente-saldo" inputmode="decimal" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2" placeholder="0,00">
                    </label>
                    <label class="text-sm font-semibold sm:col-span-2">Intolleranze o allergie
                        <input id="utente-allergie" maxlength="240" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2" placeholder="Lattosio, glutine">
                    </label>
                    <input id="utente-id" type="hidden" value="">
                    <button id="utente-salva" type="submit" class="self-end rounded-xl bg-moss px-4 py-3 text-sm font-semibold text-white">Crea utente</button>
                    <button id="utente-annulla" type="button" hidden class="self-end rounded-xl border border-ink/15 px-4 py-3 text-sm">Annulla modifica</button>
                </form>
                <ul id="utenti-elenco" class="mt-4 space-y-2 text-sm"></ul>
            </section>
            <section data-pannello="cucina" hidden class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                <h2 class="font-serif text-3xl uppercase">Prenotazioni del giorno</h2>
                <p class="mt-2 text-sm text-ink/70">I parziali per lettera sono quello che prepara la cucina: prima tutti gli A, poi i B. Il file scaricato ha gli stessi conteggi e una riga per persona con le lettere.</p>
                <div id="conteggi-cucina" class="mt-4 flex flex-wrap gap-2 text-sm"></div>
                <ul id="prenotazioni-elenco" class="mt-4 space-y-2 text-sm"></ul>
            </section>
            </div>
        </main>
    </div>
    <script>
        const API = '../api/';
        const state = { csrf: null, azienda: null, ruolo: null };

        function mostraLoginErrore(errore) {
            const box = document.getElementById('login-error');
            box.hidden = false;
            box.textContent = errore?.messaggio || 'Accesso non riuscito.';
        }

        function mostraBanner(tipo, messaggio) {
            const banner = document.getElementById('banner');
            if (!messaggio) {
                banner.hidden = true;
                banner.textContent = '';
                return;
            }
            banner.hidden = false;
            banner.className = 'is-toast rounded-3xl border p-4 ' + (tipo === 'ok'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                : 'border-red-200 bg-red-50 text-red-900');
            banner.textContent = messaggio;
        }

        async function api(path, options = {}) {
            const headers = new Headers(options.headers || {});
            headers.set('Accept', 'application/json');
            if (state.csrf && options.method && options.method !== 'GET') {
                headers.set('X-CSRF-Token', state.csrf);
            }
            const response = await fetch(path, { ...options, headers, credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok || payload.ok === false) {
                const errore = new Error(payload?.errore?.messaggio || 'Richiesta non riuscita.');
                errore.codice = payload?.errore?.codice || 'ERRORE';
                throw errore;
            }
            return payload;
        }

        function testoModalita(modalita) {
            return modalita === 'internet'
                ? 'Internet: oggi non si prenota. Domani si chiude all’orario di blocco. Il file si scarica e si manda fuori.'
                : 'Intranet: oggi si prenota fino all’orario di chiusura. Il file si scarica e si manda con l’applicazione aziendale.';
        }

        function applica(payload) {
            state.csrf = payload.csrf;
            state.azienda = payload.azienda;
            document.getElementById('azienda-nome').textContent = payload.azienda.nome;
            document.getElementById('regola-max').value = payload.azienda.max_scelte;
            document.getElementById('regola-min').value = payload.azienda.min_scelte;
            document.getElementById('regola-oggi').value = payload.azienda.limite_oggi;
            document.getElementById('regola-domani').value = payload.azienda.limite_domani;
            document.getElementById('regola-primo').checked = payload.azienda.permetti_doppio_primo === true;
            document.getElementById('regola-secondo').checked = payload.azienda.permetti_doppio_secondo === true;
            document.getElementById('regola-contorno').checked = payload.azienda.permetti_doppio_contorno === true;
            document.getElementById('regola-max-primo').value = payload.azienda.max_primo;
            document.getElementById('regola-max-secondo').value = payload.azienda.max_secondo;
            document.getElementById('regola-max-contorno').value = payload.azienda.max_contorno;
            document.getElementById('regola-max-frutta').value = payload.azienda.max_frutta;
            document.getElementById('regola-max-dessert').value = payload.azienda.max_dessert;
            document.getElementById('regola-frutta').checked = payload.azienda.permetti_doppio_frutta === true;
            document.getElementById('regola-dessert').checked = payload.azienda.permetti_doppio_dessert === true;
            const logo = document.getElementById('azienda-logo');
            if (payload.azienda.logo) {
                logo.src = '../' + payload.azienda.logo + '?v=' + Date.now();
                logo.alt = payload.azienda.nome;
                logo.hidden = false;
            } else {
                logo.hidden = true;
            }
            document.getElementById('regola-bevanda').checked = payload.azienda.bevanda_obbligatoria === true;
            document.getElementById('regola-guida').value = payload.azienda.guida_linea || '';
            document.getElementById('regola-contatto').value = payload.azienda.contatto_linea || '';
            document.getElementById(payload.azienda.caricamento_menu === 'giovedi' ? 'carica-giovedi' : 'carica-sempre').checked = true;
            document.getElementById(payload.azienda.tipo_pagamento === 'dipendente' ? 'paga-dipendente' : 'paga-azienda').checked = true;
            document.getElementById(payload.azienda.modalita === 'internet' ? 'modalita-internet' : 'modalita-intranet').checked = true;
            document.getElementById('modalita-testo').textContent = testoModalita(payload.azienda.modalita);
            document.getElementById('cucina-testo').textContent = payload.azienda.modalita === 'internet'
                ? 'Scarichi il CSV di questa azienda e lo mandi con l’applicazione esterna, anche se non sei in sede.'
                : 'Scarichi il CSV di questa azienda e lo mandi con l’applicazione che usi in intranet.';
        }

        function etichettaPagamento(valore) {
            if (valore === 'welfare' || valore === 'busta') {
                return 'impiegato · busta paga';
            }
            if (valore === 'buono') {
                return 'operaio · buono';
            }
            if (valore === 'pagante' || valore === 'dipendente') {
                return 'pagante · wallet';
            }
            return 'eredita dall’azienda';
        }

        function disegnaVoci(lista, vuoto, righe) {
            lista.replaceChildren();
            if (!righe.length) {
                const item = document.createElement('li');
                item.className = 'text-ink/60';
                item.textContent = vuoto;
                lista.append(item);
                return;
            }
            righe.forEach((riga) => lista.append(riga));
        }

        function disegnaSocieta(elenco) {
            const lista = document.getElementById('societa-elenco');
            const conteggio = document.getElementById('societa-conteggio');
            conteggio.textContent = elenco.length === 0
                ? 'Nessuna cucina in elenco.'
                : (elenco.length === 1 ? '1 cucina in elenco.' : elenco.length + ' cucine in elenco.');
            const righe = elenco.map((societa) => {
                const item = document.createElement('li');
                item.className = 'flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-cream px-4 py-3';
                const testo = document.createElement('span');
                const catering = societa.caterer_matricola
                    ? ' · catering ' + societa.caterer_matricola
                    : '';
                testo.textContent = societa.nome + ' · azienda ' + societa.slug + catering + ' · ' + etichettaPagamento(societa.tipo_pagamento) + ' · ' + societa.modalita;
                const file = document.createElement('input');
                file.type = 'file';
                file.accept = 'image/png,image/jpeg,image/webp,image/gif';
                file.className = 'text-xs';
                file.addEventListener('change', () => {
                    if (!file.files || !file.files[0]) {
                        return;
                    }
                    const body = new FormData();
                    body.append('logo', file.files[0]);
                    body.append('slug', societa.slug);
                    api(API + 'logo.php', { method: 'POST', body })
                        .then((risposta) => {
                            state.csrf = risposta.csrf || state.csrf;
                            file.value = '';
                            mostraBanner('ok', 'Logo di ' + societa.nome + ' aggiornato.');
                        })
                        .catch((error) => mostraBanner('errore', error.message));
                });
                item.append(testo, file);
                return item;
            });
            disegnaVoci(lista, 'Nessuna cucina ancora. La prima si crea da questo modulo.', righe);
        }

        async function caricaSocieta() {
            const payload = await api(API + 'societa.php');
            state.csrf = payload.csrf;
            disegnaSocieta(payload.societa || []);
        }

        async function caricaUtenti() {
            const payload = await api(API + 'utenti.php');
            state.csrf = payload.csrf;
            const lista = document.getElementById('utenti-elenco');
            const righe = payload.utenti.map((utente) => {
                const item = document.createElement('li');
                item.className = 'flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-cream px-4 py-3';
                const testo = document.createElement('span');
                const allergia = utente.allergie ? ' · allergie: ' + utente.allergie : '';
                testo.textContent = utente.matricola + ' · ' + utente.nome + ' · ' + utente.ruolo + ' · ' + etichettaPagamento(utente.pagamento) + allergia;
                const azioni = document.createElement('span');
                azioni.className = 'flex gap-2';
                const modifica = document.createElement('button');
                modifica.type = 'button';
                modifica.className = 'rounded-full border border-ink/15 px-3 py-1';
                modifica.textContent = 'Modifica';
                modifica.addEventListener('click', () => compilaUtente(utente));
                const elimina = document.createElement('button');
                elimina.type = 'button';
                elimina.className = 'rounded-full border border-red-200 px-3 py-1 text-red-800';
                elimina.textContent = 'Elimina';
                elimina.addEventListener('click', () => eliminaUtente(utente));
                azioni.append(modifica, elimina);
                item.append(testo, azioni);
                return item;
            });
            disegnaVoci(lista, 'Nessun utente in questa azienda.', righe);
        }

        async function caricaPrenotazioni() {
            const data = document.getElementById('giorno-cucina').value;
            const lista = document.getElementById('prenotazioni-elenco');
            if (!data) {
                disegnaVoci(lista, 'Indica il giorno.', []);
                return;
            }
            const payload = await api(API + 'prenotazioni.php?data=' + encodeURIComponent(data));
            state.csrf = payload.csrf;
            const conteggi = document.getElementById('conteggi-cucina');
            conteggi.replaceChildren();
            const totale = document.createElement('span');
            totale.className = 'rounded-full bg-ink px-3 py-1 font-semibold text-white';
            totale.textContent = 'Totale ' + payload.totale;
            conteggi.append(totale);
            payload.conteggi.forEach((voce) => {
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-2 rounded-full px-3 py-1 fascia-' + (voce.fascia || 'gialla');
                const lettera = document.createElement('span');
                lettera.className = 'lettera lettera-' + (voce.fascia || 'gialla');
                lettera.textContent = voce.lettera || '–';
                const testo = document.createElement('span');
                testo.textContent = voce.nome + ' · ' + voce.porzioni;
                chip.append(lettera, testo);
                conteggi.append(chip);
            });
            const righe = payload.prenotazioni.map((voce) => {
                const item = document.createElement('li');
                item.className = 'rounded-2xl bg-cream px-4 py-3';
                const titolo = document.createElement('p');
                titolo.className = 'font-semibold';
                const chi = voce.ospite ? 'Ospite ' + (voce.ospite_nome || '') : voce.nome;
                const carico = voce.a_carico === 'dipendente' ? 'paga il dipendente' : 'a carico azienda';
                const allergia = voce.nota_cucina ? ' · ATTENZIONE ' + voce.nota_cucina : '';
                titolo.textContent = voce.matricola + ' · ' + chi + ' · ' + carico + ' · ' + String(voce.totale).replace('.', ',') + ' €' + allergia;
                const piatti = document.createElement('div');
                piatti.className = 'mt-2 flex flex-wrap gap-2';
                const visti = new Map();
                voce.piatti.forEach((piatto) => {
                    const chiave = (piatto.lettera || piatto.nome);
                    const attuale = visti.get(chiave) || { ...piatto, quantita: 0 };
                    attuale.quantita += 1;
                    visti.set(chiave, attuale);
                });
                visti.forEach((piatto) => {
                    const riga = document.createElement('span');
                    riga.className = 'inline-flex items-center gap-2 rounded-full px-2 py-1 fascia-' + (piatto.fascia || 'gialla');
                    const lettera = document.createElement('span');
                    lettera.className = 'lettera lettera-' + (piatto.fascia || 'gialla');
                    lettera.textContent = piatto.lettera ? (piatto.quantita > 1 ? piatto.lettera + 'x' + piatto.quantita : piatto.lettera) : '–';
                    const nome = document.createElement('span');
                    nome.textContent = piatto.nome;
                    riga.append(lettera, nome);
                    piatti.append(riga);
                });
                item.append(titolo, piatti);
                return item;
            });
            disegnaVoci(lista, 'Nessuna prenotazione in questo giorno.', righe);
        }

        const STORAGE_ACCESSI = 'menucraft.accessi.backend';

        function leggiAccessi() {
            try {
                const grezzo = localStorage.getItem(STORAGE_ACCESSI);
                const lista = grezzo ? JSON.parse(grezzo) : [];
                return Array.isArray(lista) ? lista.filter((voce) => voce && voce.slug && voce.matricola && voce.password) : [];
            } catch (error) {
                return [];
            }
        }

        function scriviAccessi(lista) {
            localStorage.setItem(STORAGE_ACCESSI, JSON.stringify(lista.slice(0, 12)));
        }

        function disegnaAccessi() {
            const lista = document.getElementById('accessi-salvati');
            const vuoti = document.getElementById('accessi-vuoti');
            const accessi = leggiAccessi().filter((voce) => !(voce.slug === 'menucraft' && voce.matricola === 'ADMIN'));
            lista.replaceChildren();
            vuoti.hidden = accessi.length > 0;
            accessi.forEach((accesso, indice) => {
                const bottone = document.createElement('button');
                bottone.type = 'button';
                bottone.className = 'accesso-salvato rounded-2xl border border-ink/10 px-3 py-3 text-left';
                const titolo = document.createElement('span');
                titolo.className = 'block pr-5 text-sm font-medium';
                titolo.textContent = accesso.nome || accesso.slug;
                const meta = document.createElement('span');
                meta.className = 'block text-xs text-ink/60';
                meta.textContent = accesso.matricola + ' · ' + accesso.slug;
                const togli = document.createElement('button');
                togli.type = 'button';
                togli.className = 'togli-accesso';
                togli.setAttribute('aria-label', 'Rimuovi accesso salvato');
                togli.textContent = '×';
                togli.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const aggiornati = leggiAccessi().filter((voce) => !(voce.slug === accesso.slug && voce.matricola === accesso.matricola));
                    scriviAccessi(aggiornati);
                    disegnaAccessi();
                });
                bottone.append(titolo, meta, togli);
                bottone.addEventListener('click', () => {
                    document.getElementById('slug').value = accesso.slug;
                    document.getElementById('matricola').value = accesso.matricola;
                    document.getElementById('password').value = accesso.password;
                    document.getElementById('login-error').hidden = true;
                });
                lista.append(bottone);
            });
        }

        function proponiSalvataggio(payload, password) {
            if (payload.utente.ruolo === 'piattaforma') {
                return;
            }
            const slug = payload.azienda.slug;
            const matricola = payload.utente.matricola;
            const gia = leggiAccessi().some((voce) => voce.slug === slug && voce.matricola === matricola);
            if (gia) {
                return;
            }
            const ok = window.confirm(
                'Vuoi salvare ' + payload.azienda.nome + ' (' + matricola + ') tra i banchi di questo dispositivo?',
            );
            if (!ok) {
                return;
            }
            const lista = leggiAccessi().filter((voce) => !(voce.slug === slug && voce.matricola === matricola));
            lista.unshift({
                slug,
                matricola,
                password,
                nome: payload.azienda.nome,
            });
            scriviAccessi(lista);
            disegnaAccessi();
        }

        async function entra(payload, password) {
            mostraBanner('ok', '');
            if (payload.utente.ruolo !== 'caterer' && payload.utente.ruolo !== 'piattaforma') {
                await api(API + 'logout.php', { method: 'POST' });
                mostraLoginErrore({ messaggio: 'Questo ingresso è il backend. I dipendenti usano la sezione frontend.' });
                return;
            }
            proponiSalvataggio(payload, password || '');
            state.ruolo = payload.utente.ruolo;
            applica(payload);
            const piattaforma = payload.utente.ruolo === 'piattaforma';
            document.getElementById('servizio').hidden = !piattaforma;
            document.getElementById('banco').hidden = piattaforma;
            document.getElementById('sezioni').hidden = piattaforma;
            document.getElementById('login-view').hidden = true;
            document.getElementById('app-view').hidden = false;
            if (piattaforma) {
                await caricaSocieta();
                return;
            }
            const menu = await api(API + 'menu.php');
            state.csrf = menu.csrf;
            document.getElementById('upload-window').textContent = menu.caricamento.messaggio;
            const domani = new Date();
            domani.setDate(domani.getDate() + 1);
            const iso = domani.toISOString().slice(0, 10);
            document.getElementById('giorno-cucina').value = iso;
            document.getElementById('menu-giorno').value = iso;
            mostraSezione('regole');
            await caricaUtenti();
            await caricaPrenotazioni();
            await caricaMenu();
        }

        document.getElementById('login-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            document.getElementById('login-error').hidden = true;
            const password = document.getElementById('password').value;
            try {
                const payload = await api(API + 'login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        slug: document.getElementById('slug').value,
                        matricola: document.getElementById('matricola').value,
                        password,
                    }),
                });
                await entra(payload, password);
            } catch (error) {
                mostraLoginErrore(error);
            }
        });

        document.getElementById('accesso-servizio').addEventListener('click', () => {
            const bottone = document.getElementById('accesso-servizio');
            document.getElementById('slug').value = bottone.dataset.slug;
            document.getElementById('matricola').value = bottone.dataset.matricola;
            document.getElementById('password').value = bottone.dataset.password;
            document.getElementById('login-error').hidden = true;
        });

        disegnaAccessi();

        document.getElementById('regole-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const modalita = document.getElementById('modalita-internet').checked ? 'internet' : 'intranet';
            try {
                const payload = await api(API + 'regole.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        max_scelte: Number(document.getElementById('regola-max').value),
                        min_scelte: Number(document.getElementById('regola-min').value),
                        limite_oggi: document.getElementById('regola-oggi').value,
                        limite_domani: document.getElementById('regola-domani').value,
                        permetti_doppio_primo: document.getElementById('regola-primo').checked,
                        permetti_doppio_secondo: document.getElementById('regola-secondo').checked,
                        permetti_doppio_contorno: document.getElementById('regola-contorno').checked,
                        permetti_doppio_frutta: document.getElementById('regola-frutta').checked,
                        permetti_doppio_dessert: document.getElementById('regola-dessert').checked,
                        max_primo: Number(document.getElementById('regola-max-primo').value),
                        max_secondo: Number(document.getElementById('regola-max-secondo').value),
                        max_contorno: Number(document.getElementById('regola-max-contorno').value),
                        max_frutta: Number(document.getElementById('regola-max-frutta').value),
                        max_dessert: Number(document.getElementById('regola-max-dessert').value),
                        bevanda_obbligatoria: document.getElementById('regola-bevanda').checked,
                        guida_linea: document.getElementById('regola-guida').value,
                        contatto_linea: document.getElementById('regola-contatto').value,
                        caricamento_menu: document.getElementById('carica-giovedi').checked ? 'giovedi' : 'sempre',
                        tipo_pagamento: document.getElementById('paga-dipendente').checked ? 'dipendente' : 'welfare',
                        modalita,
                    }),
                });
                state.csrf = payload.csrf || state.csrf;
                applica({ csrf: state.csrf, azienda: payload.azienda });
                mostraBanner('ok', payload.messaggio);
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        });

        document.getElementById('scarica-riepilogo').addEventListener('click', async () => {
            const data = document.getElementById('giorno-cucina').value;
            if (!data) {
                mostraBanner('errore', 'Indica il giorno del file.');
                return;
            }
            try {
                const response = await fetch(API + 'riepilogo.php?data=' + encodeURIComponent(data), {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/csv, application/json' },
                });
                const tipo = response.headers.get('Content-Type') || '';
                if (!response.ok || tipo.includes('application/json')) {
                    const payload = await response.json();
                    throw new Error(payload?.errore?.messaggio || 'File non disponibile.');
                }
                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'riepilogo-cucina-' + data + '.csv';
                link.click();
                URL.revokeObjectURL(url);
                mostraBanner('ok', 'File cucina scaricato per il ' + data + '. Mandalo con la tua applicazione.');
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        });

        document.getElementById('upload-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const input = document.getElementById('menu-file');
            if (!input.files || !input.files[0]) {
                mostraBanner('errore', 'Seleziona il file del menu da caricare.');
                return;
            }
            const body = new FormData();
            body.append('menu', input.files[0]);
            try {
                const payload = await api(API + 'upload-excel.php', { method: 'POST', body });
                input.value = '';
                await caricaMenu();
                mostraBanner('ok', payload.pubblicazione.messaggio);
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        });

        document.getElementById('giorno-cucina').addEventListener('change', () => {
            caricaPrenotazioni().catch((error) => mostraBanner('errore', error.message));
        });

        document.getElementById('societa-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const payload = await api(API + 'societa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nome: document.getElementById('societa-nome').value,
                        slug: document.getElementById('societa-slug').value,
                        tipo_pagamento: document.getElementById('societa-pagamento').value,
                        modalita: document.getElementById('societa-modalita').value,
                        caterer_nome: document.getElementById('societa-caterer-nome').value,
                        caterer_matricola: document.getElementById('societa-caterer-matricola').value,
                        caterer_password: document.getElementById('societa-caterer-password').value,
                    }),
                });
                state.csrf = payload.csrf;
                document.getElementById('societa-form').reset();
                if (Array.isArray(payload.elenco)) {
                    disegnaSocieta(payload.elenco);
                } else {
                    await caricaSocieta();
                }
                const creata = payload.societa || {};
                mostraBanner(
                    'ok',
                    (payload.messaggio || 'Cucina creata.') + (creata.slug && creata.caterer_matricola
                        ? ' Per gestirla: Esci, poi entra con azienda ' + creata.slug + ' e matricola ' + creata.caterer_matricola + '.'
                        : ''),
                );
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        });

        function mostraSezione(nome) {
            document.querySelectorAll('[data-pannello]').forEach((pannello) => {
                pannello.hidden = pannello.dataset.pannello !== nome;
            });
            document.querySelectorAll('.sezione').forEach((bottone) => {
                const attivo = bottone.dataset.sezione === nome;
                bottone.className = 'sezione rounded-full px-4 py-2 text-sm font-semibold ' + (attivo ? 'bg-moss text-white' : 'border border-ink/15 bg-white');
            });
        }

        function compilaUtente(utente) {
            document.getElementById('utente-id').value = String(utente.id);
            document.getElementById('utente-nome').value = utente.nome;
            document.getElementById('utente-matricola').value = utente.matricola;
            document.getElementById('utente-matricola').readOnly = true;
            document.getElementById('utente-password').value = '';
            document.getElementById('utente-pagamento').value = utente.pagamento === 'dipendente' ? 'pagante' : utente.pagamento;
            document.getElementById('utente-allergie').value = utente.allergie || '';
            document.getElementById('utente-salva').textContent = 'Salva modifiche';
            document.getElementById('utente-annulla').hidden = false;
            mostraSezione('utenti');
        }

        function resetUtente() {
            document.getElementById('utente-form').reset();
            document.getElementById('utente-id').value = '';
            document.getElementById('utente-matricola').readOnly = false;
            document.getElementById('utente-salva').textContent = 'Crea utente';
            document.getElementById('utente-annulla').hidden = true;
        }

        async function eliminaUtente(utente) {
            if (!window.confirm('Eliminare ' + utente.nome + '? Le sue prenotazioni escono dal banco.')) {
                return;
            }
            try {
                const payload = await api(API + 'utenti.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ azione: 'elimina', utente_id: utente.id }),
                });
                state.csrf = payload.csrf;
                await caricaUtenti();
                mostraBanner('ok', payload.messaggio);
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        }

        async function caricaMenu() {
            const data = document.getElementById('menu-giorno').value;
            if (!data) {
                return;
            }
            const payload = await api(API + 'menu-banco.php?data=' + encodeURIComponent(data));
            state.csrf = payload.csrf;
            const lista = document.getElementById('menu-elenco');
            const righe = payload.menu.piatti.map((piatto) => {
                const item = document.createElement('li');
                item.className = 'flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-cream px-4 py-3';
                const testo = document.createElement('span');
                const lettera = piatto.lettera ? piatto.lettera + ' · ' : '';
                testo.textContent = lettera + piatto.codice + ' · ' + piatto.nome + ' · ' + piatto.categoria + ' · ' + String(piatto.prezzo).replace('.', ',') + ' €';
                const togli = document.createElement('button');
                togli.type = 'button';
                togli.className = 'rounded-full border border-ink/15 px-3 py-1';
                togli.textContent = 'Togli';
                togli.addEventListener('click', () => {
                    api(API + 'menu-banco.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ azione: 'togli', data, piatto_id: piatto.id }),
                    }).then(async (risposta) => {
                        state.csrf = risposta.csrf || state.csrf;
                        await caricaMenu();
                        mostraBanner('ok', risposta.messaggio);
                    }).catch((error) => mostraBanner('errore', error.message));
                });
                item.append(testo, togli);
                return item;
            });
            disegnaVoci(lista, 'Nessun piatto in questo giorno. Aggiungine uno dal catalogo o creane uno nuovo.', righe);
            const select = document.getElementById('menu-catalogo');
            select.replaceChildren();
            payload.menu.catalogo.forEach((piatto) => {
                const option = document.createElement('option');
                option.value = String(piatto.id);
                option.textContent = piatto.codice + ' · ' + piatto.nome + ' · ' + piatto.categoria;
                select.append(option);
            });
        }

        document.querySelectorAll('.sezione').forEach((bottone) => {
            bottone.addEventListener('click', () => mostraSezione(bottone.dataset.sezione));
        });

        document.getElementById('menu-giorno').addEventListener('change', () => {
            caricaMenu().catch((error) => mostraBanner('errore', error.message));
        });

        document.getElementById('menu-esistente').addEventListener('submit', async (event) => {
            event.preventDefault();
            const piatto = document.getElementById('menu-catalogo').value;
            if (!piatto) {
                mostraBanner('errore', 'Il catalogo non ha altri piatti da aggiungere.');
                return;
            }
            try {
                const payload = await api(API + 'menu-banco.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        azione: 'aggiungi',
                        data: document.getElementById('menu-giorno').value,
                        piatto_id: Number(piatto),
                    }),
                });
                state.csrf = payload.csrf;
                await caricaMenu();
                mostraBanner('ok', payload.messaggio);
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        });

        document.getElementById('menu-nuovo').addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const payload = await api(API + 'menu-banco.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        azione: 'crea',
                        data: document.getElementById('menu-giorno').value,
                        nome: document.getElementById('piatto-nome').value,
                        codice: document.getElementById('piatto-codice').value,
                        categoria: document.getElementById('piatto-categoria').value,
                        prezzo: document.getElementById('piatto-prezzo').value,
                    }),
                });
                state.csrf = payload.csrf;
                document.getElementById('menu-nuovo').reset();
                await caricaMenu();
                mostraBanner('ok', payload.messaggio);
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        });

        document.getElementById('logo-file').addEventListener('change', async () => {
            const input = document.getElementById('logo-file');
            if (!input.files || !input.files[0]) {
                return;
            }
            const body = new FormData();
            body.append('logo', input.files[0]);
            try {
                const payload = await api(API + 'logo.php', { method: 'POST', body });
                state.csrf = payload.csrf || state.csrf;
                input.value = '';
                if (state.azienda) {
                    state.azienda.logo = payload.logo;
                    applica({ csrf: state.csrf, azienda: state.azienda });
                }
                mostraBanner('ok', payload.messaggio);
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        });

        document.getElementById('utente-annulla').addEventListener('click', resetUtente);

        document.getElementById('utente-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = document.getElementById('utente-id').value;
            const password = document.getElementById('utente-password').value;
            if (!id && password.length < 10) {
                mostraBanner('errore', 'La password deve avere tra 10 e 72 caratteri.');
                return;
            }
            try {
                const payload = await api(API + 'utenti.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        azione: id ? 'aggiorna' : 'crea',
                        utente_id: id ? Number(id) : undefined,
                        nome: document.getElementById('utente-nome').value,
                        matricola: document.getElementById('utente-matricola').value,
                        password,
                        pagamento: document.getElementById('utente-pagamento').value,
                        saldo: document.getElementById('utente-saldo').value,
                        allergie: document.getElementById('utente-allergie').value,
                    }),
                });
                state.csrf = payload.csrf;
                resetUtente();
                await caricaUtenti();
                mostraBanner('ok', payload.messaggio);
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        });

        document.getElementById('logout').addEventListener('click', async () => {
            try {
                await api(API + 'logout.php', { method: 'POST' });
            } catch (error) {
                // La vista torna al login anche se la sessione era già chiusa.
            }
            state.csrf = null;
            document.getElementById('app-view').hidden = true;
            document.getElementById('login-view').hidden = false;
        });

        api(API + 'sessione.php').then(entra).catch(() => {
            document.getElementById('login-view').hidden = false;
        });
    </script>
</body>
</html>
