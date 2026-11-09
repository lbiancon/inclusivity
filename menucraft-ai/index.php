<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MenuCraft AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,680&family=Outfit:wght@380;500;640&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        serif: ['Fraunces', 'Georgia', 'serif'],
                        sans: ['Outfit', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        ink: '#241c16',
                        cream: '#f4efe6',
                        terracotta: '#c4491d',
                        moss: '#1f6b4a',
                    },
                },
            },
        };
    </script>
    <style>
        [hidden] { display: none !important; }
        body { font-family: Outfit, ui-sans-serif, system-ui, sans-serif; background: #f4efe6; color: #241c16; }
        h1, h2, .font-serif { font-family: Fraunces, Georgia, serif; }
    </style>
</head>
<body class="min-h-screen bg-cream text-ink font-sans antialiased">
    <div id="login-view" class="mx-auto flex min-h-screen max-w-6xl items-center px-4 py-10">
        <div class="grid w-full gap-8 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="rounded-[2rem] bg-ink px-8 py-10 text-[#f6f1e8] shadow-2xl">
                <p class="text-xs uppercase tracking-[0.28em] text-[#e7b59a]">MenuCraft AI</p>
                <h1 class="mt-4 max-w-xl font-serif text-5xl leading-tight">La mensa aziendale, con le regole del turno già dentro.</h1>
                <p class="mt-5 max-w-lg text-lg text-[#f0e6da]/80">Prenotazioni isolate per azienda, tetto di scelte, un solo secondo, doppio primo solo se il tenant lo consente, e chiusure automatiche su weekend, feste nazionali e patroni.</p>
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
                <h2 class="font-serif text-3xl">Entra in mensa</h2>
                <p class="mt-2 text-sm text-ink/70">Ogni matricola vede solo i piatti della propria azienda.</p>
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
                    <button type="submit" class="w-full rounded-2xl bg-terracotta px-4 py-3 font-medium text-white transition hover:bg-[#a63b16]">Accedi</button>
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
                        <button type="button" class="demo rounded-2xl border border-ink/10 px-3 py-3 text-left hover:border-terracotta" data-slug="aurora-welfare" data-matricola="CAT-A">
                            <span class="block text-sm font-medium">Caterer Aurora</span>
                            <span class="block text-xs text-ink/60">Carica il menu del giovedì</span>
                        </button>
                        <button type="button" class="demo rounded-2xl border border-ink/10 px-3 py-3 text-left hover:border-terracotta" data-slug="officine-rossi" data-matricola="CAT-R">
                            <span class="block text-sm font-medium">Caterer Rossi</span>
                            <span class="block text-xs text-ink/60">Doppio primo e doppio contorno</span>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div id="app-view" hidden>
        <header class="sticky top-0 z-20 border-b border-ink/10 bg-cream/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.22em] text-terracotta">MenuCraft AI</p>
                    <h1 id="azienda-nome" class="font-serif text-2xl"></h1>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p id="utente-nome" class="text-sm font-medium"></p>
                        <p id="utente-meta" class="text-xs text-ink/60"></p>
                    </div>
                    <button id="logout" type="button" class="rounded-full border border-ink/15 px-4 py-2 text-sm hover:border-terracotta">Esci</button>
                </div>
            </div>
        </header>

        <main class="mx-auto grid max-w-6xl gap-6 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="space-y-5">
                <div id="banner" hidden role="alert" class="rounded-3xl border p-4"></div>
                <div id="upload-card" hidden class="rounded-3xl bg-white p-5 shadow-sm shadow-ink/5">
                    <h2 class="font-serif text-2xl">Pubblica il menu</h2>
                    <p id="upload-window" class="mt-2 text-sm text-ink/70"></p>
                    <p class="mt-2 text-sm text-ink/60">Colonne: data, codice, nome, categoria, prezzo. Il file sostituisce i giorni indicati. CSV con virgola o punto e virgola, oppure xlsx.</p>
                    <form id="upload-form" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <input id="menu-file" name="menu" type="file" accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="block w-full text-sm">
                        <button type="submit" class="rounded-2xl bg-ink px-4 py-3 text-sm text-white">Carica menu</button>
                    </form>
                </div>
                <div id="weeks" class="space-y-4"></div>
                <div id="menu" class="space-y-4"></div>
            </section>
            <aside class="h-fit rounded-[2rem] bg-white p-5 shadow-xl shadow-ink/5 lg:sticky lg:top-24">
                <p class="text-xs uppercase tracking-[0.18em] text-ink/50">Buono pasto</p>
                <h2 id="summary-title" class="mt-1 font-serif text-3xl">Vassoio</h2>
                <p id="summary-reason" class="mt-2 text-sm text-ink/70"></p>
                <div id="pips" class="mt-4 flex gap-2" aria-hidden="true"></div>
                <ul id="summary-lines" class="mt-4 space-y-2 text-sm"></ul>
                <div id="bill" class="mt-5 rounded-2xl bg-cream p-4" aria-live="polite"></div>
                <div class="mt-4 grid gap-2">
                    <button id="submit-order" type="button" class="rounded-2xl bg-terracotta px-4 py-3 font-medium text-white disabled:cursor-not-allowed disabled:bg-ink/20">Conferma prenotazione</button>
                    <button id="cancel-order" type="button" hidden class="rounded-2xl border border-ink/15 px-4 py-3 text-sm">Annulla buono</button>
                </div>
            </aside>
        </main>
    </div>
    <noscript class="mx-auto block max-w-xl p-8 text-center">MenuCraft AI ha bisogno di JavaScript per inviare la prenotazione al validatore.</noscript>
    <script>
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
            const upload = document.getElementById('upload-card');
            upload.hidden = payload.utente.ruolo !== 'caterer';
        }

        async function entra(payload) {
            applicaSessione(payload);
            loginView.hidden = true;
            appView.hidden = false;
            await caricaGiorno(null);
        }

        async function caricaGiorno(data) {
            state.pending = false;
            const path = data ? 'api/menu.php?data=' + encodeURIComponent(data) : 'api/menu.php';
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
            if (payload.caricamento) {
                document.getElementById('upload-window').textContent = payload.caricamento.messaggio;
            }
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
                    nodo.className = 'rounded-2xl border px-3 py-3 text-left ' + (
                        giorno.saltato
                            ? 'border-dashed border-ink/15 bg-cream text-ink/40'
                            : attivo
                                ? 'border-terracotta bg-terracotta text-white'
                                : 'border-ink/10 hover:border-terracotta'
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
                    bottone.className = 'rounded-2xl border px-4 py-3 text-left disabled:cursor-default ' + (
                        scelto ? 'border-terracotta bg-[#fff4ef]' : 'border-ink/10 hover:border-terracotta'
                    );
                    const nome = document.createElement('span');
                    nome.className = 'block font-medium';
                    nome.textContent = piatto.nome;
                    const meta = document.createElement('span');
                    meta.className = 'mt-1 block text-sm text-ink/60';
                    meta.textContent = piatto.codice + ' · ' + euro(cents(piatto.prezzo));
                    bottone.append(nome, meta);
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
                const payload = await api('api/ordina.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ data: state.giorno.data, piatti }),
                });
                state.saldo = payload.saldo;
                await caricaGiorno(state.giorno.data);
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
                const payload = await api('api/login.php', {
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
                await api('api/logout.php', { method: 'POST' });
            } catch (error) {
                // La sessione locale si chiude comunque se il server ha già invalidato il cookie.
            }
            state.csrf = null;
            appView.hidden = true;
            loginView.hidden = false;
        });

        document.getElementById('upload-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const input = document.getElementById('menu-file');
            if (!input.files || !input.files[0]) {
                mostraBanner('errore', 'Seleziona un file .xlsx o .csv.', 'FILE_MANCANTE', []);
                return;
            }
            const body = new FormData();
            body.append('menu', input.files[0]);
            try {
                const payload = await api('api/upload-excel.php', { method: 'POST', body });
                input.value = '';
                await caricaGiorno(state.giorno?.data || null);
                const dettagli = [
                    'Giorni: ' + payload.pubblicazione.giorni,
                    'Piatti: ' + payload.pubblicazione.piatti,
                    'Visibile dal ' + payload.pubblicazione.visibile_dal,
                ];
                mostraBanner('ok', payload.pubblicazione.messaggio, null, dettagli);
            } catch (error) {
                mostraBanner('errore', error.message, error.codice, error.dettagli);
            }
        });

        api('api/sessione.php').then(entra).catch(() => {
            loginView.hidden = false;
        });
    </script>
</body>
</html>
