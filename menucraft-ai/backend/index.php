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
        body { font-family: "Source Sans 3", ui-sans-serif, sans-serif; background: #efe6d2; color: #1d2420; }
        h1, h2, .font-serif { font-family: "Barlow Condensed", Impact, sans-serif; }
        .counter { background: #1d4a38; color: #f6f3ea; }
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
            <div class="mt-6 grid gap-2 sm:grid-cols-2">
                <button type="button" class="demo rounded-2xl border border-ink/10 px-3 py-3 text-left" data-slug="aurora-welfare" data-matricola="CAT-A">
                    <span class="block text-sm font-medium">Caterer Aurora</span>
                    <span class="block text-xs text-ink/60">Intranet · welfare</span>
                </button>
                <button type="button" class="demo rounded-2xl border border-ink/10 px-3 py-3 text-left" data-slug="officine-rossi" data-matricola="CAT-R">
                    <span class="block text-sm font-medium">Caterer Rossi</span>
                    <span class="block text-xs text-ink/60">Internet · wallet</span>
                </button>
                <button type="button" class="demo rounded-2xl border border-ink/10 px-3 py-3 text-left" data-slug="menucraft" data-matricola="ADMIN">
                    <span class="block text-sm font-medium">Servizio MenuCraft</span>
                    <span class="block text-xs text-ink/60">Apre una cucina nuova</span>
                </button>
            </div>
        </section>
    </div>

    <div id="app-view" hidden>
        <header class="counter sticky top-0 z-20">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.22em] text-[#d7eadf]">Backend</p>
                    <h1 id="azienda-nome" class="font-serif text-3xl uppercase"></h1>
                </div>
                <button id="logout" type="button" class="rounded-full border border-white/30 px-4 py-2 text-sm text-white">Esci</button>
            </div>
        </header>
        <main class="mx-auto max-w-3xl space-y-4 px-4 py-6">
            <div id="banner" hidden role="alert" class="rounded-3xl border p-4"></div>
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
                <ul id="societa-elenco" class="mt-4 space-y-2 text-sm"></ul>
            </section>
            <div id="banco" class="space-y-4">
            <form id="regole-form" class="space-y-4">
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
                    <h2 class="font-serif text-3xl uppercase">Regole del vassoio</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="text-sm font-semibold">Scelte massime per buono
                            <input id="regola-max" name="max_scelte" type="number" min="1" max="10" required class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                        </label>
                        <label class="text-sm font-semibold">Chiusura di oggi, solo intranet
                            <input id="regola-oggi" name="limite_oggi" type="time" required class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                        </label>
                        <label class="text-sm font-semibold">Blocco del giorno prima
                            <input id="regola-domani" name="limite_domani" type="time" required class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                        </label>
                        <div class="flex flex-col justify-end gap-2 text-sm">
                            <label class="flex items-center gap-2"><input id="regola-primo" type="checkbox"> Doppio primo consentito</label>
                            <label class="flex items-center gap-2"><input id="regola-contorno" type="checkbox"> Doppio contorno consentito</label>
                            <label class="flex items-center gap-2"><input id="regola-bevanda" type="checkbox"> Acqua obbligatoria dentro le scelte</label>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                        <label class="rounded-2xl border border-ink/10 bg-cream p-4">
                            <input id="paga-azienda" name="tipo_pagamento" type="radio" value="welfare" class="mr-2"> A carico dell’azienda
                        </label>
                        <label class="rounded-2xl border border-ink/10 bg-cream p-4">
                            <input id="paga-dipendente" name="tipo_pagamento" type="radio" value="dipendente" class="mr-2"> Paga il dipendente
                        </label>
                    </div>
                    <button type="submit" class="mt-4 w-full rounded-xl bg-moss px-4 py-3 text-sm font-semibold text-white">Salva sul backend</button>
                </section>
            </form>
            <section class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                <h2 class="font-serif text-3xl uppercase">File per la cucina</h2>
                <p id="cucina-testo" class="mt-2 text-sm text-ink/70"></p>
                <label class="mt-4 block text-sm font-semibold">Giorno
                    <input id="giorno-cucina" type="date" class="mt-1 rounded-xl border border-ink/15 bg-cream px-3 py-2">
                </label>
                <button id="scarica-riepilogo" type="button" class="mt-4 rounded-xl bg-terracotta px-4 py-3 text-sm font-semibold text-white">Scarica il file</button>
            </section>
            <section class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                <h2 class="font-serif text-3xl uppercase">Menu del giovedì</h2>
                <p id="upload-window" class="mt-2 text-sm text-ink/70"></p>
                <p class="mt-2 text-sm text-ink/60">Template Excel della settimana, oppure CSV con data, codice, nome, categoria, prezzo. Un .xls vecchio va salvato come .xlsx.</p>
                <form id="upload-form" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input id="menu-file" name="menu" type="file" accept=".csv,.txt,.xlsx,.xls" class="block w-full text-sm">
                    <button type="submit" class="rounded-2xl bg-ink px-4 py-3 text-sm text-white">Carica menu</button>
                </form>
            </section>
            <section class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
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
                        <input id="utente-password" required minlength="10" maxlength="72" type="password" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                    </label>
                    <label class="text-sm font-semibold">Pagamento
                        <select id="utente-pagamento" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2">
                            <option value="eredita">Eredita dall’azienda</option>
                            <option value="welfare">Sempre a carico azienda</option>
                            <option value="pagante">Sempre pagante</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Saldo iniziale, se paga
                        <input id="utente-saldo" inputmode="decimal" class="mt-1 w-full rounded-xl border border-ink/15 bg-cream px-3 py-2" placeholder="0,00">
                    </label>
                    <button type="submit" class="self-end rounded-xl bg-moss px-4 py-3 text-sm font-semibold text-white">Crea utente</button>
                </form>
                <ul id="utenti-elenco" class="mt-4 space-y-2 text-sm"></ul>
            </section>
            <section class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                <h2 class="font-serif text-3xl uppercase">Prenotazioni del giorno</h2>
                <p class="mt-2 text-sm text-ink/70">Tutti i buoni di questa azienda, ospiti compresi. È da qui che la cucina vede gli ordini, prima di scaricare il file.</p>
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
            banner.className = 'rounded-3xl border p-4 ' + (tipo === 'ok'
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
            document.getElementById('regola-oggi').value = payload.azienda.limite_oggi;
            document.getElementById('regola-domani').value = payload.azienda.limite_domani;
            document.getElementById('regola-primo').checked = payload.azienda.permetti_doppio_primo === true;
            document.getElementById('regola-contorno').checked = payload.azienda.permetti_doppio_contorno === true;
            document.getElementById('regola-bevanda').checked = payload.azienda.bevanda_obbligatoria === true;
            document.getElementById(payload.azienda.tipo_pagamento === 'dipendente' ? 'paga-dipendente' : 'paga-azienda').checked = true;
            document.getElementById(payload.azienda.modalita === 'internet' ? 'modalita-internet' : 'modalita-intranet').checked = true;
            document.getElementById('modalita-testo').textContent = testoModalita(payload.azienda.modalita);
            document.getElementById('cucina-testo').textContent = payload.azienda.modalita === 'internet'
                ? 'Scarichi il CSV di questa azienda e lo mandi con l’applicazione esterna, anche se non sei in sede.'
                : 'Scarichi il CSV di questa azienda e lo mandi con l’applicazione che usi in intranet.';
        }

        function etichettaPagamento(valore) {
            if (valore === 'welfare') {
                return 'a carico azienda';
            }
            if (valore === 'pagante' || valore === 'dipendente') {
                return 'pagante';
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

        async function caricaSocieta() {
            const payload = await api(API + 'societa.php');
            state.csrf = payload.csrf;
            const lista = document.getElementById('societa-elenco');
            const righe = payload.societa.map((societa) => {
                const item = document.createElement('li');
                item.className = 'rounded-2xl bg-cream px-4 py-3';
                item.textContent = societa.nome + ' · azienda ' + societa.slug + ' · ' + etichettaPagamento(societa.tipo_pagamento) + ' · ' + societa.modalita;
                return item;
            });
            disegnaVoci(lista, 'Nessuna cucina ancora. La prima si crea da questo modulo.', righe);
        }

        async function caricaUtenti() {
            const payload = await api(API + 'utenti.php');
            state.csrf = payload.csrf;
            const lista = document.getElementById('utenti-elenco');
            const righe = payload.utenti.map((utente) => {
                const item = document.createElement('li');
                item.className = 'rounded-2xl bg-cream px-4 py-3';
                item.textContent = utente.matricola + ' · ' + utente.nome + ' · ' + utente.ruolo + ' · ' + etichettaPagamento(utente.pagamento);
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
            const righe = payload.prenotazioni.map((voce) => {
                const item = document.createElement('li');
                item.className = 'rounded-2xl bg-cream px-4 py-3';
                const chi = voce.ospite ? 'Ospite ' + (voce.ospite_nome || '') : voce.nome;
                const piatti = voce.piatti.map((piatto) => piatto.nome).join(', ');
                const carico = voce.a_carico === 'dipendente' ? 'paga il dipendente' : 'a carico azienda';
                item.textContent = voce.matricola + ' · ' + chi + ' · ' + piatti + ' · ' + carico + ' · ' + String(voce.totale).replace('.', ',') + ' €';
                return item;
            });
            disegnaVoci(lista, 'Nessuna prenotazione in questo giorno.', righe);
        }

        async function entra(payload) {
            mostraBanner('ok', '');
            if (payload.utente.ruolo !== 'caterer' && payload.utente.ruolo !== 'piattaforma') {
                await api(API + 'logout.php', { method: 'POST' });
                mostraLoginErrore({ messaggio: 'Questo ingresso è il backend. I dipendenti usano la sezione frontend.' });
                return;
            }
            state.ruolo = payload.utente.ruolo;
            applica(payload);
            const piattaforma = payload.utente.ruolo === 'piattaforma';
            document.getElementById('servizio').hidden = !piattaforma;
            document.getElementById('banco').hidden = piattaforma;
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
            document.getElementById('giorno-cucina').value = domani.toISOString().slice(0, 10);
            await caricaUtenti();
            await caricaPrenotazioni();
        }

        document.getElementById('login-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            document.getElementById('login-error').hidden = true;
            try {
                const payload = await api(API + 'login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        slug: document.getElementById('slug').value,
                        matricola: document.getElementById('matricola').value,
                        password: document.getElementById('password').value,
                    }),
                });
                await entra(payload);
            } catch (error) {
                mostraLoginErrore(error);
            }
        });

        document.querySelectorAll('.demo').forEach((bottone) => {
            bottone.addEventListener('click', () => {
                document.getElementById('slug').value = bottone.dataset.slug;
                document.getElementById('matricola').value = bottone.dataset.matricola;
                document.getElementById('password').value = 'MenuCraft!2026';
            });
        });

        document.getElementById('regole-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const modalita = document.getElementById('modalita-internet').checked ? 'internet' : 'intranet';
            try {
                const payload = await api(API + 'regole.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        max_scelte: Number(document.getElementById('regola-max').value),
                        limite_oggi: document.getElementById('regola-oggi').value,
                        limite_domani: document.getElementById('regola-domani').value,
                        permetti_doppio_primo: document.getElementById('regola-primo').checked,
                        permetti_doppio_contorno: document.getElementById('regola-contorno').checked,
                        bevanda_obbligatoria: document.getElementById('regola-bevanda').checked,
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
                mostraBanner('errore', 'Seleziona il template del giovedì.');
                return;
            }
            const body = new FormData();
            body.append('menu', input.files[0]);
            try {
                const payload = await api(API + 'upload-excel.php', { method: 'POST', body });
                input.value = '';
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
                await caricaSocieta();
                mostraBanner('ok', payload.messaggio);
            } catch (error) {
                mostraBanner('errore', error.message);
            }
        });

        document.getElementById('utente-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const payload = await api(API + 'utenti.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nome: document.getElementById('utente-nome').value,
                        matricola: document.getElementById('utente-matricola').value,
                        password: document.getElementById('utente-password').value,
                        pagamento: document.getElementById('utente-pagamento').value,
                        saldo: document.getElementById('utente-saldo').value,
                    }),
                });
                state.csrf = payload.csrf;
                document.getElementById('utente-form').reset();
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
