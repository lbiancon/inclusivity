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
    <title>MenuCraft · Linea</title>
    <link rel="stylesheet" href="../assets/app.css">
    <style>
        [hidden] { display: none !important; }
        body { font-family: "Source Sans 3", ui-sans-serif, sans-serif; background: #efe6d2; color: #1d2420; }
        h1, h2, h3, .font-serif { font-family: "Barlow Condensed", Impact, sans-serif; letter-spacing: 0.01em; }
        .counter { background: #1d4a38; color: #f6f3ea; }
        .scontrino {
            background:
                radial-gradient(circle at 12px 0, transparent 10px, #fffdf8 11px) left top / 24px 12px repeat-x,
                #fffdf8;
            border: 1px solid #d5cbb8;
        }
        .day.is-skip { background: repeating-linear-gradient(-45deg, #f4efe4, #f4efe4 6px, #e7e0d2 6px, #e7e0d2 12px); }
        .dish { display: flex; align-items: center; gap: 0.75rem; text-align: left; }
        .dish .code {
            font-family: "Barlow Condensed", sans-serif;
            background: #1d4a38;
            color: #f6f3ea;
            min-width: 4.5rem;
            padding: 0.7rem 0.4rem;
            text-align: center;
            letter-spacing: 0.04em;
        }
        .dish.is-on { background: #e7f3ec; border-color: #1d4a38; }
        .dish.is-on .code { background: #b6402c; }
    </style>
</head>
<body class="min-h-screen bg-cream text-ink font-sans antialiased">
    <div id="login-view" class="mx-auto flex min-h-screen max-w-6xl items-center px-4 py-10">
        <div class="grid w-full gap-8 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="counter rounded-[2rem] px-8 py-10 shadow-2xl">
                <p class="text-xs uppercase tracking-[0.28em] text-[#d7eadf]">Linea mensa</p>
                <h1 class="mt-4 max-w-xl font-serif text-5xl uppercase leading-none">Prenota il vassoio prima che chiuda la linea.</h1>
                <p class="mt-5 max-w-lg text-lg text-[#f6f3ea]/80">Ogni azienda ha il suo menu e le sue regole. Il dipendente compone il buono, la cucina riceve il riepilogo del giorno.</p>
                <dl class="mt-8 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl bg-white/5 p-4">
                        <dt class="text-xs uppercase tracking-wider text-[#e7b59a]">Oggi</dt>
                        <dd class="mt-1 text-2xl font-serif">09:30</dd>
                    </div>
                    <div class="rounded-2xl bg-white/5 p-4">
                        <dt class="text-xs uppercase tracking-wider text-[#e7b59a]">Domani</dt>
                        <dd class="mt-1 text-2xl font-serif">18:00</dd>
                    </div>
                    <div class="rounded-2xl bg-white/5 p-4">
                        <dt class="text-xs uppercase tracking-wider text-[#e7b59a]">Fuso</dt>
                        <dd class="mt-1 text-2xl font-serif">Roma</dd>
                    </div>
                </dl>
            </section>
            <section class="rounded-[2rem] bg-white p-8 shadow-xl shadow-ink/5">
                <h2 class="font-serif text-4xl uppercase">Badge mensa</h2>
                <p class="mt-2 text-sm text-ink/70">Azienda, matricola e password. Da qui controlli il menu, scegli il vassoio e vedi le tue prenotazioni.</p>
                <form id="login-form" class="mt-6 space-y-4">
                    <label class="block text-sm font-medium" for="slug">Azienda
                        <input id="slug" name="slug" autocomplete="organization" required class="mt-1 w-full rounded-2xl border border-ink/10 bg-cream px-4 py-3 outline-none ring-terracotta focus:ring-2" placeholder="aurora-welfare">
                    </label>
                    <label class="block text-sm font-medium" for="matricola">Matricola
                        <input id="matricola" name="matricola" autocomplete="username" required class="mt-1 w-full rounded-2xl border border-ink/10 bg-cream px-4 py-3 outline-none ring-terracotta focus:ring-2" placeholder="A100">
                    </label>
                    <label class="block text-sm font-medium" for="password">Password
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-2xl border border-ink/10 bg-cream px-4 py-3 outline-none ring-terracotta focus:ring-2">
                    </label>
                    <button type="submit" class="w-full rounded-2xl bg-moss px-4 py-3 font-semibold text-white transition hover:bg-[#163c2d]">Entra in linea</button>
                </form>
                <div id="login-error" hidden class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-900" role="alert"></div>
                <div class="mt-6">
                    <p class="text-xs uppercase tracking-[0.18em] text-ink/50">Accessi dimostrativi · password MenuCraft!2026</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button type="button" class="demo rounded-2xl border border-ink/10 px-3 py-3 text-left hover:border-terracotta" data-slug="aurora-welfare" data-matricola="A100">
                            <span class="block text-sm font-medium">Aurora Welfare</span>
                            <span class="block text-xs text-ink/60">Giulia Bianchi · prezzi a 0,00 €</span>
                        </button>
                        <button type="button" class="demo rounded-2xl border border-ink/10 px-3 py-3 text-left hover:border-terracotta" data-slug="officine-rossi" data-matricola="R200">
                            <span class="block text-sm font-medium">Officine Rossi</span>
                            <span class="block text-xs text-ink/60">Luca Ferrara · wallet e conto live</span>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div id="app-view" hidden>
        <header class="counter sticky top-0 z-20">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.22em] text-[#d7eadf]">MenuCraft · linea</p>
                    <h1 id="azienda-nome" class="font-serif text-3xl uppercase"></h1>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p id="utente-nome" class="text-sm font-semibold text-white"></p>
                        <p id="utente-meta" class="text-xs text-[#d7eadf]"></p>
                    </div>
                    <button id="logout" type="button" class="rounded-full border border-white/30 px-4 py-2 text-sm text-white hover:bg-white/10">Esci</button>
                </div>
            </div>
        </header>

        <main class="mx-auto grid max-w-6xl gap-6 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="space-y-5">
                <div id="banner" hidden role="alert" class="rounded-3xl border p-4"></div>
                <p id="modalita-nota" class="text-sm text-ink/70"></p>
                <div id="weeks" class="space-y-4"></div>
                <div id="menu" class="space-y-4"></div>
                <section class="rounded-3xl border border-[#d5cbb8] bg-white p-5">
                    <h2 class="font-serif text-3xl uppercase">Le tue prenotazioni</h2>
                    <ul id="storico" class="mt-4 space-y-3 text-sm"></ul>
                </section>
            </section>
            <aside class="scontrino h-fit rounded-[2rem] p-5 lg:sticky lg:top-24">
                <p class="text-xs uppercase tracking-[0.18em] text-ink/50">Scontrino vassoio</p>
                <h2 id="summary-title" class="mt-1 font-serif text-3xl">Vassoio</h2>
                <p id="summary-reason" class="mt-2 text-sm text-ink/70"></p>
                <div id="pips" class="mt-4 flex gap-2" aria-hidden="true"></div>
                <ul id="summary-lines" class="mt-4 space-y-2 text-sm"></ul>
                <div id="bill" class="mt-5 rounded-2xl bg-cream p-4" aria-live="polite"></div>
                <div class="mt-4 grid gap-2">
                    <button id="submit-order" type="button" class="rounded-2xl bg-moss px-4 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:bg-ink/20">Conferma prenotazione</button>
                    <button id="cancel-order" type="button" hidden class="rounded-2xl border border-ink/15 px-4 py-3 text-sm">Annulla buono</button>
                </div>
            </aside>
        </main>
    </div>
    <noscript class="mx-auto block max-w-xl p-8 text-center">MenuCraft AI ha bisogno di JavaScript per inviare la prenotazione al validatore.</noscript>
    <script>
        const API = '../api/';
        const state = {
            csrf: null,
            utente: null,
            azienda: null,
            regole: null,
            saldo: null,
            settimane: [],
            giorno: null,
            catalogo: new Map(),
            selezionati: [],
            pending: false,
        };

        const loginView = document.getElementById('login-view');
        const appView = document.getElementById('app-view');
        const loginError = document.getElementById('login-error');
        const banner = document.getElementById('banner');

        function cents(euro) {
            const [intero, decimali = ''] = String(euro).split('.');
            return (Number(intero) * 100) + Number((decimali + '00').slice(0, 2));
        }

        function euro(valore) {
            const segno = valore < 0 ? '-' : '';
            const assoluto = Math.abs(valore);
            return segno + Math.floor(assoluto / 100) + ',' + String(assoluto % 100).padStart(2, '0') + ' €';
        }

        function mostraLoginErrore(errore) {
            loginError.hidden = false;
            loginError.replaceChildren();
            const titolo = document.createElement('p');
            titolo.className = 'font-medium';
            titolo.textContent = errore?.messaggio || 'Accesso non riuscito.';
            loginError.append(titolo);
            if (errore?.codice) {
                const codice = document.createElement('p');
                codice.className = 'mt-1 text-xs uppercase tracking-wider';
                codice.textContent = errore.codice;
                loginError.append(codice);
            }
        }

        function mostraBanner(tipo, messaggio, codice, dettagli) {
            if (!messaggio) {
                banner.hidden = true;
                banner.replaceChildren();
                return;
            }
            banner.hidden = false;
            banner.className = tipo === 'ok'
                ? 'rounded-3xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-950'
                : 'rounded-3xl border border-red-200 bg-red-50 p-4 text-red-900';
            banner.replaceChildren();
            const titolo = document.createElement('p');
            titolo.className = 'font-medium';
            titolo.textContent = messaggio;
            banner.append(titolo);
            if (codice) {
                const etichetta = document.createElement('p');
                etichetta.className = 'mt-1 text-xs uppercase tracking-wider opacity-70';
                etichetta.textContent = codice;
                banner.append(etichetta);
            }
            if (Array.isArray(dettagli) && dettagli.length) {
                const lista = document.createElement('ul');
                lista.className = 'mt-3 list-disc space-y-1 pl-5 text-sm';
                dettagli.forEach((voce) => {
                    const item = document.createElement('li');
                    item.textContent = voce;
                    lista.append(item);
                });
                banner.append(lista);
            }
        }

        async function api(path, options = {}) {
            const headers = { Accept: 'application/json', ...(options.headers || {}) };
            if (state.csrf && options.method && options.method !== 'GET') {
                headers['X-CSRF-Token'] = state.csrf;
            }
            let response;
            try {
                response = await fetch(path, { ...options, headers, credentials: 'same-origin' });
            } catch (error) {
                const rete = new Error('Connessione al server non riuscita.');
                rete.codice = 'RETE';
                throw rete;
            }
            let payload = null;
            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }
            if (!response.ok || !payload || payload.ok === false) {
                const errore = new Error(payload?.errore?.messaggio || 'Richiesta non riuscita.');
                errore.codice = payload?.errore?.codice || 'ERRORE';
                errore.dettagli = payload?.errore?.dettagli || [];
                errore.status = response.status;
                throw errore;
            }
            return payload;
        }

        function applicaSessione(payload) {
            state.csrf = payload.csrf;
            state.utente = payload.utente;
            state.azienda = payload.azienda;
            document.getElementById('azienda-nome').textContent = payload.azienda.nome;
            document.getElementById('utente-nome').textContent = payload.utente.nome;
            const pagamento = payload.azienda.tipo_pagamento === 'welfare' ? 'Welfare · 0,00 €' : 'Quota dipendente';
            document.getElementById('utente-meta').textContent = payload.utente.matricola + ' · ' + pagamento;
            const nota = document.getElementById('modalita-nota');
            nota.textContent = payload.azienda.modalita === 'internet'
                ? 'Questa azienda è in internet: il pasto di oggi si prenota solo dalla rete aziendale.'
                : 'Questa azienda è in intranet: oggi si prenota fino all’orario di chiusura della linea.';
        }

        async function entra(payload) {
            if (payload.utente.ruolo !== 'dipendente') {
                await api(API + 'logout.php', { method: 'POST' });
                mostraLoginErrore({
                    messaggio: 'Questo ingresso è solo per i dipendenti. Il banco cucina è nella sezione backend.',
                    codice: 'RUOLO',
                });
                return;
            }
            applicaSessione(payload);
            loginView.hidden = true;
            appView.hidden = false;
            await caricaGiorno(null);
            await caricaStorico();
        }

        async function caricaStorico() {
            const lista = document.getElementById('storico');
            lista.replaceChildren();
            const payload = await api(API + 'storico.php');
            if (!payload.prenotazioni.length) {
                const vuoto = document.createElement('li');
                vuoto.className = 'text-ink/60';
                vuoto.textContent = 'Non hai ancora prenotazioni.';
                lista.append(vuoto);
                return;
            }
            payload.prenotazioni.forEach((prenotazione) => {
                const voce = document.createElement('li');
                voce.className = 'rounded-2xl bg-cream px-4 py-3';
                const titolo = document.createElement('p');
                titolo.className = 'font-semibold';
                titolo.textContent = prenotazione.data + ' · ' + String(prenotazione.totale).replace('.', ',') + ' €';
                const piatti = document.createElement('p');
                piatti.className = 'mt-1 text-ink/70';
                piatti.textContent = prenotazione.piatti.map((piatto) => piatto.nome).join(' · ');
                voce.append(titolo, piatti);
                lista.append(voce);
            });
        }

        async function caricaGiorno(data) {
            state.pending = false;
            const path = data ? API + 'menu.php?data=' + encodeURIComponent(data) : API + 'menu.php';
            const payload = await api(path);
            state.csrf = payload.csrf;
            state.regole = payload.regole;
            state.saldo = payload.saldo;
            state.settimane = payload.settimane;
            state.giorno = payload.giorno;
            state.catalogo = new Map();
            payload.giorno.portate.forEach((portata) => {
                portata.piatti.forEach((piatto) => state.catalogo.set(piatto.id, piatto));
            });
            const giaScelti = payload.giorno.ordine ? payload.giorno.ordine.piatti.map((piatto) => piatto.id) : [];
            state.selezionati = giaScelti.filter((id) => state.catalogo.has(id));
            mostraBanner(null, null);
            disegna();
        }

        function limiteMessaggio(ids) {
            const regole = state.regole;
            if (ids.length > regole.max_scelte) {
                return 'Puoi scegliere al massimo ' + regole.max_scelte + ' piatti per buono pasto.';
            }
            const conteggi = { primo: 0, secondo: 0, contorno: 0 };
            ids.forEach((id) => {
                const piatto = state.catalogo.get(id);
                if (conteggi[piatto.categoria] !== undefined) {
                    conteggi[piatto.categoria] += 1;
                }
            });
            if (conteggi.secondo > regole.max_secondo) {
                return 'È consentito al massimo un secondo per vassoio.';
            }
            if (conteggi.primo > regole.max_primo) {
                return regole.max_primo > 1
                    ? 'Sono consentiti al massimo due primi.'
                    : 'Per questa azienda il doppio primo non è consentito.';
            }
            if (conteggi.contorno > regole.max_contorno) {
                return regole.max_contorno > 1
                    ? 'Sono consentiti al massimo due contorni.'
                    : 'Per questa azienda il doppio contorno non è consentito.';
            }
            return null;
        }

        function togglePiatto(id) {
            if (!state.giorno?.selezionabile || state.utente.ruolo !== 'dipendente') {
                return;
            }
            const presenti = state.selezionati.includes(id);
            const prossimi = presenti ? state.selezionati.filter((voce) => voce !== id) : state.selezionati.concat(id);
            if (!presenti) {
                const messaggio = limiteMessaggio(prossimi);
                if (messaggio) {
                    mostraBanner('errore', messaggio, 'REGOLA');
                    return;
                }
            }
            state.selezionati = prossimi;
            mostraBanner(null, null);
            disegna();
        }

        function disegna() {
            const weeks = document.getElementById('weeks');
            weeks.replaceChildren();
            state.settimane.forEach((settimana) => {
                const card = document.createElement('section');
                card.className = 'rounded-3xl bg-white p-5 shadow-sm shadow-ink/5';
                const titolo = document.createElement('div');
                titolo.className = 'mb-3 flex flex-wrap items-end justify-between gap-2';
                const heading = document.createElement('h2');
                heading.className = 'font-serif text-2xl';
                heading.textContent = settimana.titolo;
                titolo.append(heading);
                if (settimana.avviso) {
                    const avviso = document.createElement('p');
                    avviso.className = 'rounded-full bg-[#f8e7df] px-3 py-1 text-xs font-medium text-terracotta';
                    avviso.textContent = settimana.avviso;
                    titolo.append(avviso);
                }
                const griglia = document.createElement('div');
                griglia.className = 'grid grid-cols-2 gap-2 sm:grid-cols-5';
                settimana.giorni.forEach((giorno) => {
                    const attivo = state.giorno && state.giorno.data === giorno.data;
                    const nodo = document.createElement(giorno.saltato ? 'div' : 'button');
                    if (!giorno.saltato) {
                        nodo.type = 'button';
                        nodo.dataset.giorno = giorno.data;
                    }
                    nodo.className = 'day rounded-2xl border px-3 py-3 text-left ' + (
                        giorno.saltato
                            ? 'is-skip border-dashed border-ink/15 text-ink/40'
                            : attivo
                                ? 'border-moss bg-moss text-white'
                                : 'border-ink/10 bg-white hover:border-moss'
                    );
                    const nome = document.createElement('span');
                    nome.className = 'block text-xs uppercase tracking-wider ' + (giorno.saltato ? 'line-through' : '');
                    nome.textContent = giorno.etichetta;
                    const numero = document.createElement('span');
                    numero.className = 'block font-serif text-2xl';
                    numero.textContent = giorno.numero;
                    nodo.append(nome, numero);
                    if (giorno.saltato) {
                        const nota = document.createElement('span');
                        nota.className = 'mt-1 block text-[11px] leading-snug';
                        nota.textContent = 'Saltato';
                        nodo.append(nota);
                    } else if (!giorno.selezionabile && giorno.motivo) {
                        const nota = document.createElement('span');
                        nota.className = 'mt-1 block text-[11px] leading-snug ' + (attivo ? 'text-white/80' : 'text-ink/50');
                        nota.textContent = 'Chiuso';
                        nodo.append(nota);
                    } else if (settimana.lunedi_saltato && giorno.data === settimana.ancora) {
                        const nota = document.createElement('span');
                        nota.className = 'mt-1 block text-[11px] leading-snug ' + (attivo ? 'text-white/80' : 'text-terracotta');
                        nota.textContent = 'Si parte da qui';
                        nodo.append(nota);
                    }
                    griglia.append(nodo);
                });
                card.append(titolo, griglia);
                weeks.append(card);
            });

            const menu = document.getElementById('menu');
            menu.replaceChildren();
            const giorno = state.giorno;
            if (giorno.motivo && !giorno.selezionabile) {
                const avviso = document.createElement('div');
                avviso.className = 'rounded-3xl border border-amber-200 bg-amber-50 p-4 text-amber-950';
                avviso.setAttribute('role', 'status');
                const testo = document.createElement('p');
                testo.className = 'font-medium';
                testo.textContent = giorno.motivo;
                avviso.append(testo);
                menu.append(avviso);
            }
            if (!giorno.portate.length && giorno.selezionabile) {
                const vuoto = document.createElement('p');
                vuoto.className = 'rounded-3xl bg-white p-5 text-sm text-ink/70';
                vuoto.textContent = 'Nessun piatto pubblicato per questo giorno.';
                menu.append(vuoto);
            }
            giorno.portate.forEach((portata) => {
                const blocco = document.createElement('section');
                blocco.className = 'rounded-3xl bg-white p-5 shadow-sm shadow-ink/5';
                const intestazione = document.createElement('div');
                intestazione.className = 'mb-3 flex items-baseline justify-between gap-3';
                const titolo = document.createElement('h3');
                titolo.className = 'font-serif text-2xl';
                titolo.textContent = portata.etichetta;
                const limite = document.createElement('p');
                limite.className = 'text-xs uppercase tracking-wider text-ink/50';
                limite.textContent = portata.categoria === 'secondo'
                    ? 'Massimo 1'
                    : (portata.categoria === 'primo' || portata.categoria === 'contorno')
                        ? 'Massimo ' + portata.massimo
                        : 'Nel tetto delle ' + state.regole.max_scelte + ' scelte';
                intestazione.append(titolo, limite);
                const griglia = document.createElement('div');
                griglia.className = 'grid gap-2 sm:grid-cols-2';
                portata.piatti.forEach((piatto) => {
                    const scelto = state.selezionati.includes(piatto.id);
                    const bottone = document.createElement('button');
                    bottone.type = 'button';
                    bottone.dataset.piatto = String(piatto.id);
                    bottone.setAttribute('aria-pressed', scelto ? 'true' : 'false');
                    bottone.disabled = !giorno.selezionabile || state.utente.ruolo !== 'dipendente';
                    bottone.className = 'dish w-full overflow-hidden rounded-2xl border border-ink/10 disabled:cursor-default ' + (scelto ? 'is-on' : '');
                    const codice = document.createElement('span');
                    codice.className = 'code text-sm';
                    codice.textContent = piatto.codice;
                    const testo = document.createElement('span');
                    testo.className = 'min-w-0 flex-1 py-2 pr-3';
                    const nome = document.createElement('span');
                    nome.className = 'block font-semibold';
                    nome.textContent = piatto.nome;
                    const meta = document.createElement('span');
                    meta.className = 'block text-sm text-ink/60';
                    meta.textContent = euro(cents(piatto.prezzo));
                    testo.append(nome, meta);
                    bottone.append(codice, testo);
                    griglia.append(bottone);
                });
                blocco.append(intestazione, griglia);
                menu.append(blocco);
            });

            document.getElementById('summary-title').textContent = giorno.etichetta + ' ' + giorno.numero;
            document.getElementById('summary-reason').textContent = giorno.selezionabile
                ? 'Massimo ' + state.regole.max_scelte + ' scelte. Oggi chiude alle ' + state.azienda.limite_oggi + ', il giorno prima alle ' + state.azienda.limite_domani + '.'
                : (giorno.motivo || '');

            const pips = document.getElementById('pips');
            pips.replaceChildren();
            for (let i = 0; i < state.regole.max_scelte; i += 1) {
                const pip = document.createElement('span');
                pip.className = 'h-2 flex-1 rounded-full ' + (i < state.selezionati.length ? 'bg-terracotta' : 'bg-ink/10');
                pips.append(pip);
            }

            const lines = document.getElementById('summary-lines');
            lines.replaceChildren();
            if (!state.selezionati.length) {
                const vuoto = document.createElement('li');
                vuoto.className = 'text-ink/50';
                vuoto.textContent = 'Nessun piatto sul vassoio.';
                lines.append(vuoto);
            }
            state.selezionati.forEach((id) => {
                const piatto = state.catalogo.get(id);
                const item = document.createElement('li');
                item.className = 'flex justify-between gap-3';
                const nome = document.createElement('span');
                nome.textContent = piatto.nome;
                const prezzo = document.createElement('span');
                prezzo.textContent = euro(cents(piatto.prezzo));
                item.append(nome, prezzo);
                lines.append(item);
            });

            const totale = state.selezionati.reduce((sum, id) => sum + cents(state.catalogo.get(id).prezzo), 0);
            const bill = document.getElementById('bill');
            bill.replaceChildren();
            const etichetta = document.createElement('p');
            etichetta.className = 'text-xs uppercase tracking-wider text-ink/50';
            etichetta.textContent = state.azienda.tipo_pagamento === 'welfare' ? 'Coperto dal welfare' : 'Conto live';
            const importo = document.createElement('p');
            importo.className = 'font-serif text-4xl';
            importo.textContent = euro(totale);
            bill.append(etichetta, importo);
            const welfare = state.azienda.tipo_pagamento === 'welfare';
            const saldoCentesimi = welfare || state.saldo === null ? null : cents(state.saldo);
            if (!welfare) {
                const saldo = document.createElement('p');
                saldo.className = 'mt-1 text-sm text-ink/70';
                saldo.textContent = 'Wallet ' + euro(saldoCentesimi);
                bill.append(saldo);
            }
            const oltreWallet = !welfare && saldoCentesimi !== null && totale > saldoCentesimi && state.selezionati.length > 0;
            if (oltreWallet) {
                const avviso = document.createElement('p');
                avviso.className = 'mt-2 text-sm font-medium text-red-700';
                avviso.textContent = 'Il wallet non copre questo vassoio.';
                bill.append(avviso);
            }

            const submit = document.getElementById('submit-order');
            const cancel = document.getElementById('cancel-order');
            const puoOrdinare = state.utente.ruolo === 'dipendente' && giorno.selezionabile && !state.pending;
            submit.hidden = state.utente.ruolo !== 'dipendente';
            cancel.hidden = state.utente.ruolo !== 'dipendente' || !giorno.ordine;
            submit.disabled = !puoOrdinare || state.selezionati.length === 0 || oltreWallet;
            cancel.disabled = !puoOrdinare;
            submit.textContent = giorno.ordine ? 'Aggiorna prenotazione' : 'Conferma prenotazione';
        }

        async function invia(piatti) {
            state.pending = true;
            disegna();
            try {
                const payload = await api(API + 'ordina.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ data: state.giorno.data, piatti }),
                });
                state.saldo = payload.saldo;
                await caricaGiorno(state.giorno.data);
                await caricaStorico();
                mostraBanner('ok', payload.messaggio, null, []);
            } catch (error) {
                mostraBanner('errore', error.message, error.codice, error.dettagli);
                state.pending = false;
                disegna();
            }
        }

        document.getElementById('login-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            loginError.hidden = true;
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
                mostraLoginErrore({ messaggio: error.message, codice: error.codice });
            }
        });

        document.querySelectorAll('.demo').forEach((bottone) => {
            bottone.addEventListener('click', () => {
                document.getElementById('slug').value = bottone.dataset.slug;
                document.getElementById('matricola').value = bottone.dataset.matricola;
                document.getElementById('password').value = 'MenuCraft!2026';
                loginError.hidden = true;
            });
        });

        document.getElementById('weeks').addEventListener('click', async (event) => {
            const bottone = event.target.closest('[data-giorno]');
            if (!bottone) {
                return;
            }
            try {
                await caricaGiorno(bottone.dataset.giorno);
            } catch (error) {
                mostraBanner('errore', error.message, error.codice, error.dettagli);
            }
        });

        document.getElementById('menu').addEventListener('click', (event) => {
            const bottone = event.target.closest('[data-piatto]');
            if (!bottone) {
                return;
            }
            togglePiatto(Number(bottone.dataset.piatto));
        });

        document.getElementById('submit-order').addEventListener('click', () => {
            invia(state.selezionati);
        });

        document.getElementById('cancel-order').addEventListener('click', () => {
            invia([]);
        });

        document.getElementById('logout').addEventListener('click', async () => {
            try {
                await api(API + 'logout.php', { method: 'POST' });
            } catch (error) {
                // La sessione locale si chiude comunque se il server ha già invalidato il cookie.
            }
            state.csrf = null;
            appView.hidden = true;
            loginView.hidden = false;
        });

        api(API + 'sessione.php').then(entra).catch(() => {
            loginView.hidden = false;
        });
    </script>
</body>
</html>
