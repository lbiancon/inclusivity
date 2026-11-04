const apiRoot = document.body.dataset.api;
const state = {
    view: "terminal",
    model: null,
    status: null,
    people: [],
    workplaces: [],
    operations: [],
    monitor: null,
    objects: null,
    objectType: "operation",
    log: [],
    workplaceCode: "TORN-01",
    operatorCode: "op.01",
    qty: 1,
    scrapReason: "bava",
    effects: [],
    serviceName: "operation.start",
    serviceBody: "",
    serviceResult: "",
    busy: false,
};

const titles = {
    terminal: "Terminale di reparto",
    monitor: "Monitor di officina",
    objects: "Oggetti del modello",
    services: "Servizi",
    bridge: "Da HYDRA 8 a HYDRA X",
};

const stateLabel = {
    idle: "libera",
    running: "in lavoro",
    down: "in fermo",
    ready: "pronta",
    held: "sospesa",
    done: "chiusa",
    released: "rilasciato",
    in_progress: "in corso",
};

function h(tag, props, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(props || {})) {
        if (value == null) continue;
        if (key === "class") node.className = value;
        else if (key === "text") node.textContent = String(value);
        else if (key.startsWith("on") && typeof value === "function") node.addEventListener(key.slice(2).toLowerCase(), value);
        else node.setAttribute(key, String(value));
    }
    for (const child of children.flat()) {
        if (child == null || child === false) continue;
        node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    }
    return node;
}

async function api(path, options = {}) {
    const response = await fetch(apiRoot + encodeURIComponent(path), {
        method: options.method || "GET",
        headers: options.body ? { "Content-Type": "application/json" } : {},
        body: options.body ? JSON.stringify(options.body) : undefined,
    });
    let data = {};
    try {
        data = await response.json();
    } catch (_error) {
        data = { error: "Risposta non valida." };
    }
    if (!response.ok) throw new Error(data.error || "Errore " + response.status);
    return data;
}

function showError(message) {
    const alert = document.getElementById("alert");
    alert.hidden = false;
    alert.textContent = message;
}

function clearError() {
    const alert = document.getElementById("alert");
    alert.hidden = true;
    alert.textContent = "";
}

function pill(value) {
    return h("span", { class: "pill " + value, text: stateLabel[value] || value });
}

async function loadStatus() {
    state.status = await api("/api/status");
    const driver = state.status.driver === "oci" ? "Oracle" : "SQLite";
    document.getElementById("driver-line").textContent =
        driver + " · PHP " + state.status.php + " · orari Europe/Rome";
}

async function loadTerminal() {
    const [workplaces, operations, people] = await Promise.all([
        api("/api/workplaces"),
        api("/api/operations"),
        api("/api/people"),
    ]);
    state.workplaces = workplaces.workplaces;
    state.operations = operations.operations;
    state.people = people.people;
    if (!state.people.some((person) => person.code === state.operatorCode) && state.people[0]) {
        state.operatorCode = state.people[0].code;
    }
}

async function refresh() {
    if (state.view === "terminal") await loadTerminal();
    if (state.view === "monitor") state.monitor = await api("/api/monitor");
    if (state.view === "objects") state.objects = await api("/api/objects/" + state.objectType);
    if (state.view === "services") state.log = (await api("/api/log")).entries;
    await loadStatus();
}

function currentQty(code) {
    const input = document.getElementById("qty-" + code);
    const n = Number(input ? input.value : state.qty);
    return Number.isInteger(n) && n > 0 ? n : 0;
}

async function act(service, body) {
    if (state.busy) return;
    state.busy = true;
    clearError();
    render();
    try {
        const result = await api("/api/services/" + service, { method: "POST", body });
        state.effects = result.effects || [];
        state.serviceResult = JSON.stringify(result, null, 2);
    } catch (error) {
        showError(error.message);
    } finally {
        state.busy = false;
        try {
            await refresh();
        } catch (error) {
            showError(error.message);
        }
        render();
    }
}

function renderEffects() {
    const items = state.effects.length
        ? state.effects.map((line) => h("li", { text: line }))
        : [h("li", { text: "Ogni azione elenca gli effetti sul modello condiviso." })];
    return h("div", { class: "effects" }, h("h2", { text: "Effetti" }), h("ul", {}, items));
}

function bookBar(op) {
    const scrapOptions = Object.entries(state.model.scrapReasons).map(([code, label]) => {
        const option = h("option", { value: code, text: label });
        if (code === state.scrapReason) option.selected = true;
        return option;
    });
    return h("div", { class: "book" },
        h("label", { class: "field inline" }, "Pezzi",
            h("input", { id: "qty-" + op.code, type: "number", min: "1", step: "1", value: String(state.qty) })),
        h("button", {
            type: "button", class: "go", disabled: state.busy ? "disabled" : null,
            onclick: () => act("operation.book", {
                operationCode: op.code,
                operatorCode: state.operatorCode,
                kind: "good",
                qty: currentQty(op.code),
            }),
        }, "Buoni"),
        h("label", { class: "field inline" }, "Causale scarto",
            h("select", {
                onchange: (event) => { state.scrapReason = event.target.value; },
            }, scrapOptions)),
        h("button", {
            type: "button", class: "danger", disabled: state.busy ? "disabled" : null,
            onclick: () => act("operation.book", {
                operationCode: op.code,
                operatorCode: state.operatorCode,
                kind: "scrap",
                qty: currentQty(op.code),
                reason: state.scrapReason,
            }),
        }, "Scarto"),
        h("button", {
            type: "button", class: "ghost", disabled: state.busy ? "disabled" : null,
            onclick: () => act("operation.finish", { operationCode: op.code }),
        }, "Chiudi fase"));
}

function renderOperation(op) {
    return h("article", { class: "card" },
        h("header", { class: "card-head" },
            h("div", {},
                h("p", { class: "code", text: op.code + " · " + op.name }),
                h("p", { text: op.orderCode + " · " + op.materialName }),
                h("p", { class: "muted", text: op.operatorCode ? "Operatore " + op.operatorCode : "Nessun operatore" })),
            pill(op.status)),
        h("p", { class: "nums", text: "Buoni " + op.qtyGood + " · Scarto " + op.qtyScrap }),
        op.status === "ready"
            ? h("button", {
                type: "button", class: "go", disabled: state.busy ? "disabled" : null,
                onclick: () => act("operation.start", { operationCode: op.code, operatorCode: state.operatorCode }),
            }, "Avvia")
            : null,
        op.status === "running" ? bookBar(op) : null,
        op.status === "held" ? h("p", { class: "warn", text: "Fermo aperto: il versamento è bloccato finché non chiudi il fermo." }) : null);
}

function renderTerminal() {
    const visible = state.operations.filter((op) => op.workplaceCode === state.workplaceCode);
    const people = state.people.map((person) => {
        const option = h("option", { value: person.code, text: person.code + " · " + person.name });
        if (person.code === state.operatorCode) option.selected = true;
        return option;
    });
    return h("div", { class: "layout" },
        h("section", { class: "stack" },
            h("h2", { text: "Postazione" }),
            state.workplaces.map((wp) => h("button", {
                type: "button",
                class: "tile" + (wp.code === state.workplaceCode ? " is-on" : ""),
                onclick: () => { state.workplaceCode = wp.code; render(); },
            },
                h("span", { class: "tile-code", text: wp.code }),
                h("span", { class: "tile-name", text: wp.name }),
                pill(wp.state),
                wp.downtimeReason ? h("span", { class: "muted", text: wp.downtimeReason }) : null)),
            h("label", { class: "field" }, "Operatore",
                h("select", { onchange: (event) => { state.operatorCode = event.target.value; } }, people))),
        h("section", { class: "stack grow" },
            h("h2", { text: "Fasi in coda" }),
            h("p", { class: "muted", text: "Il tornio ha due fasi di ordini diversi: se ne avvii una, l'altra aspetta che la postazione torni libera." }),
            visible.map(renderOperation),
            renderEffects()));
}

function renderMonitor() {
    const data = state.monitor;
    if (!data) return h("p", { text: "Caricamento." });
    const totals = [
        ["Buoni oggi", data.totals.goodToday],
        ["Scarto oggi", data.totals.scrapToday],
        ["In lavoro", data.totals.running],
        ["In fermo", data.totals.down],
        ["Ordini aperti", data.totals.openOrders],
    ].map(([label, value]) => h("div", { class: "total" }, h("strong", { text: value }), h("span", { text: label })));
    const cards = data.workplaces.map((wp) => h("article", { class: "card" },
        h("div", { class: "card-head" }, h("p", { class: "code", text: wp.name }), pill(wp.state)),
        h("p", { class: "muted", text: wp.code }),
        h("p", { text: wp.currentOperation ? wp.currentOperation + " · " + (stateLabel[wp.currentStatus] || "") : "Nessuna fase attiva" }),
        h("p", { class: "nums", text: "Oggi " + wp.qtyGoodToday + " buoni · " + wp.qtyScrapToday + " scarto" }),
        wp.downtimeReason ? h("p", { class: "warn", text: wp.downtimeReason }) : null));
    const head = h("tr", {}, ["Ordine", "Materiale", "Stato", "Buoni", "Scarto", "Avanzamento"].map((label) => h("th", { text: label })));
    const orders = data.orders.map((order) => h("tr", {},
        h("td", { text: order.code }),
        h("td", { text: order.materialName }),
        h("td", {}, pill(order.status)),
        h("td", { text: order.qtyGood + " / " + order.qtyPlanned }),
        h("td", { text: order.qtyScrap }),
        h("td", {}, h("div", { class: "bar" }, h("span", { style: "width:" + order.progress + "%" })))));
    return h("div", {},
        h("div", { class: "totals" }, totals),
        h("div", { class: "cards" }, cards),
        h("section", { class: "panel" },
            h("h2", { text: "Ordini" }),
            h("table", {}, h("thead", {}, head), h("tbody", {}, orders))));
}

function renderObjects() {
    const rows = state.objects ? state.objects.rows : [];
    const columns = rows[0] ? Object.keys(rows[0]).filter((key) => key !== "operations") : [];
    const types = state.model.objects.map((object) => h("button", {
        type: "button",
        class: "choice" + (object.type === state.objectType ? " is-on" : ""),
        onclick: async () => {
            state.objectType = object.type;
            state.objects = await api("/api/objects/" + object.type);
            render();
        },
    }, object.title));
    let body;
    if (rows.length === 0) {
        body = h("p", { class: "muted", text: "Nessun movimento. Dal terminale avvia una fase, oppure carica un turno nella pagina Da 8 a X." });
    } else {
        const head = h("tr", {}, columns.map((column) => h("th", { text: column })));
        const bodyRows = rows.map((row) => h("tr", {}, columns.map((column) => {
            const value = row[column];
            const text = value == null ? "" : typeof value === "object" ? JSON.stringify(value) : value;
            return h("td", { text });
        })));
        body = h("table", {}, h("thead", {}, head), h("tbody", {}, bodyRows));
    }
    return h("div", { class: "layout" },
        h("section", { class: "stack" }, h("h2", { text: "Tipi" }), types),
        h("section", { class: "panel grow" },
            h("p", { text: state.objects ? state.objects.meaning : "" }),
            body));
}

function selectedService() {
    return state.model.services.find((service) => service.name === state.serviceName) || state.model.services[0];
}

function renderServices() {
    const service = selectedService();
    const catalog = state.model.services.map((item) => h("button", {
        type: "button",
        class: "choice" + (item.name === service.name ? " is-on" : ""),
        onclick: () => {
            state.serviceName = item.name;
            state.serviceBody = JSON.stringify(item.example, null, 2);
            state.serviceResult = "";
            render();
        },
    }, item.title));
    let journal;
    if (state.log.length === 0) {
        journal = h("p", { class: "muted", text: "Ancora nessuna chiamata." });
    } else {
        const head = h("tr", {}, ["Ora", "Servizio", "Esito"].map((label) => h("th", { text: label })));
        const entries = state.log.map((entry) => h("tr", {},
            h("td", { text: entry.created_at }),
            h("td", { text: entry.service }),
            h("td", { text: entry.ok ? "ok" : "rifiutato" })));
        journal = h("table", {}, h("thead", {}, head), h("tbody", {}, entries));
    }
    return h("div", { class: "layout" },
        h("section", { class: "stack" }, h("h2", { text: "Catalogo" }), h("div", { class: "service-list" }, catalog)),
        h("section", { class: "stack grow" },
            h("h2", { text: service.name }),
            h("p", { text: service.from8 }),
            h("textarea", { id: "service-body" }, state.serviceBody),
            h("button", {
                type: "button", class: "go", disabled: state.busy ? "disabled" : null,
                onclick: sendService,
            }, "Invia servizio"),
            state.serviceResult ? h("pre", { class: "result", text: state.serviceResult }) : null,
            h("h2", { text: "Giornale" }),
            journal));
}

async function sendService() {
    const raw = document.getElementById("service-body").value;
    let body;
    try {
        body = JSON.parse(raw);
    } catch (_error) {
        showError("Il corpo non è JSON valido.");
        return;
    }
    if (body === null || typeof body !== "object" || Array.isArray(body)) {
        showError("Il corpo deve essere un oggetto JSON.");
        return;
    }
    state.serviceBody = raw;
    await act(state.serviceName, body);
}

function renderBridge() {
    const driver = state.status && state.status.driver === "oci"
        ? "Questo banco sta già parlando con Oracle."
        : "Questo banco sta su SQLite, il file data/sim.sqlite. Va bene per htdocs senza driver Oracle.";
    return h("article", { class: "panel prose" },
        h("p", { text: "Sei su HYDRA 8: client MOC, database Oracle di produzione, extension del client in C#/.NET, servizi personalizzati in Java. HYDRA X sposta il lavoro nel browser: mApp su un modello condiviso, servizi, e i segnali macchina che entrano da Distributed Edge Computing." }),
        h("p", { text: "Banco X allena solo quel gesto. Terminale e Monitor sono due mApp. Non tengono copie private dei pezzi: chiamano gli stessi servizi e rileggono gli stessi oggetti. Il segnale macchina non scrive un versamento a mano. Diventa operation.book oppure un fermo." }),
        h("p", { class: "warn", text: "Non è MIP, non è il MOC, e non va puntato allo schema HYDRA. Un utente Oracle dedicato, HXSIM, e le tabelle hxs_*." }),
        h("p", { text: driver }),
        h("h2", { text: "Metterlo in htdocs" }),
        h("ol", {},
            h("li", { text: "Copia la cartella hydra-x-sim dentro htdocs (su XAMPP: C:\\xampp\\htdocs\\hydra-x-sim)." }),
            h("li", { text: "Apri http://localhost/hydra-x-sim/ . Serve PHP 8.1 o successivo, con pdo_sqlite." }),
            h("li", { text: "La cartella data/ deve essere scrivibile dal processo di Apache." })),
        h("h2", { text: "Passare al tuo Oracle" }),
        h("ol", {},
            h("li", { text: "Crea l'utente HXSIM con CREATE SESSION, CREATE TABLE, CREATE SEQUENCE, CREATE TRIGGER e quota sullo tablespace. Non usare lo schema HYDRA." }),
            h("li", { text: "Connesso come HXSIM, esegui sql/oracle.sql." }),
            h("li", { text: "Copia config.example.php in config.local.php e metti DSN, utente e password. Il driver PHP pdo_oci e Instant Client devono essere attivi: XAMPP di solito non li ha." }),
            h("li", { text: "Ricarica la pagina. Se le tabelle sono vuote, il banco inserisce tornio, fresatrice, assemblaggio e due ordini." })),
        h("h2", { text: "Cosa ti serve davvero in azienda" }),
        h("ul", {},
            h("li", { text: "Un'istanza di test HYDRA, separata dalla linea." }),
            h("li", { text: "Su 8, se resti sul MOC: CUT-HDB e poi CUT-MOC. Il C# è per le extension, Java per i servizi." }),
            h("li", { text: "Verso X: DT-MBO sul modello, poi DT-UI o DT-OSD, e DT-DEC quando entri sulle macchine. Lì il driver è C#/.NET." })),
        renderEffects(),
        h("div", { class: "row-actions" },
            h("button", { type: "button", class: "go", disabled: state.busy ? "disabled" : null, onclick: () => act("lab.demo", { confirm: "DEMO" }) }, "Carica un turno di esempio"),
            h("button", {
                type: "button", class: "danger", disabled: state.busy ? "disabled" : null,
                onclick: () => {
                    if (window.confirm("Ripristinare le anagrafiche del banco? I versamenti di questa simulazione si perdono.")) {
                        act("lab.reset", { confirm: "RESET" });
                    }
                },
            }, "Ripristina anagrafiche")));
}

function render() {
    document.getElementById("view-title").textContent = titles[state.view];
    document.querySelectorAll(".rail button").forEach((button) => {
        const on = button.dataset.view === state.view;
        button.classList.toggle("is-on", on);
        button.setAttribute("aria-current", on ? "page" : "false");
    });
    const view = {
        terminal: renderTerminal,
        monitor: renderMonitor,
        objects: renderObjects,
        services: renderServices,
        bridge: renderBridge,
    }[state.view];
    document.getElementById("app").replaceChildren(view());
}

document.querySelectorAll(".rail button").forEach((button) => {
    button.addEventListener("click", async () => {
        state.view = button.dataset.view;
        clearError();
        try {
            await refresh();
        } catch (error) {
            showError(error.message);
        }
        render();
    });
});

boot();

async function boot() {
    try {
        state.model = await api("/api/model");
        state.serviceName = state.model.services[0].name;
        state.serviceBody = JSON.stringify(state.model.services[0].example, null, 2);
        await loadStatus();
        await loadTerminal();
        render();
    } catch (error) {
        showError(error.message);
    }
}
