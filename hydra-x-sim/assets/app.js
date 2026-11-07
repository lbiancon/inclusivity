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
    kpi: null,
    lists: null,
    listCode: "LST-11",
    listView: null,
    draftNumber: "21",
    draftTitle: "Fasi del tornio",
    draftBase: "operation",
    draftColumns: ["code", "status", "qtyGood", "qtyScrap"],
    draftKind: "",
    draftWorkplace: "TORN-01",
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
    terminal: "Terminale · DLG",
    monitor: "Monitor di officina",
    kpi: "KPI · AUN",
    lists: "Liste · LST",
    objects: "Oggetti · MST",
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
    const names = { oci: "Oracle", mysql: "MySQL", sqlite: "SQLite" };
    const driver = names[state.status.driver] || state.status.driver;
    const where = state.status.sqlitePath
        ? " · " + state.status.sqlitePath
        : state.status.mysqlDatabase
            ? " · database " + state.status.mysqlDatabase
            : "";
    document.getElementById("driver-line").textContent =
        driver + where + " · PHP " + state.status.php + " · orari Europe/Rome";
    if (state.status.warning) showError(state.status.warning);
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
    if (state.view === "kpi") state.kpi = await api("/api/kpi");
    if (state.view === "lists") {
        state.lists = await api("/api/lists");
        if (!state.lists.lists.some((item) => item.code === state.listCode) && state.lists.lists[0]) {
            state.listCode = state.lists.lists[0].code;
        }
        state.listView = state.listCode ? await api("/api/lists/" + state.listCode) : null;
    }
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

function pct(value) {
    if (value == null) return "n/d";
    return value.toLocaleString("it-IT", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "%";
}

function band(value) {
    if (value == null) return "na";
    if (value >= 85) return "good";
    if (value >= 60) return "mid";
    return "bad";
}

function minutes(seconds) {
    return (seconds / 60).toLocaleString("it-IT", { maximumFractionDigits: 1 });
}

function commandStrip() {
    return h("div", { class: "commands" }, state.model.commands.map((command) => h("button", {
        type: "button",
        class: state.view === command.view ? "is-on" : "",
        onclick: async () => {
            state.view = command.view;
            clearError();
            try {
                await refresh();
            } catch (error) {
                showError(error.message);
            }
            render();
        },
    }, command.code, h("small", { text: command.title }))));
}

function renderKpi() {
    const data = state.kpi;
    if (!data) return h("p", { text: "Caricamento." });
    const plant = data.plant;
    const factors = [
        ["Disponibilità", plant.availability],
        ["Performance", plant.performance],
        ["Qualità", plant.quality],
    ].map(([label, value]) => h("article", { class: "card kpi-factor" },
        h("p", { class: "muted", text: label }),
        h("strong", { class: "band-" + band(value), text: pct(value) })));
    const cards = data.workplaces.map((wp) => h("article", { class: "card" },
        h("div", { class: "card-head" },
            h("p", { class: "code", text: wp.name }),
            h("strong", { class: "band-" + band(wp.oee), text: pct(wp.oee) })),
        h("p", { class: "muted", text: wp.code + " · lavoro " + minutes(wp.runSec) + " min · fermo " + minutes(wp.downSec) + " min" }),
        ["Disponibilità", wp.availability, "Performance", wp.performance, "Qualità", wp.quality]
            .reduce((nodes, value, index, all) => {
                if (index % 2 === 1) return nodes;
                const label = all[index];
                const number = all[index + 1];
                nodes.push(h("div", { class: "factor" },
                    h("span", {}, label, h("b", { text: pct(number) })),
                    h("div", { class: "bar" }, h("span", { style: "width:" + (number == null ? 0 : number) + "%" }))));
                return nodes;
            }, []),
        h("p", { class: "nums", text: wp.good + " buoni · " + wp.scrap + " scarti" }),
        wp.performanceCapped ? h("p", { class: "muted", text: "I pezzi superano il ciclo ideale: la performance resta al 100%." }) : null,
        h("label", { class: "field inline" }, "Ciclo ideale (s)",
            h("input", {
                type: "number", min: "1", max: "3600", step: "1", value: String(wp.idealSec),
                onchange: (event) => act("workplace.cycle", { workplaceCode: wp.code, idealSec: Number(event.target.value) }),
            }))));
    const reasons = data.scrap.reasons.length
        ? data.scrap.reasons.map((reason) => h("div", { class: "pareto-row" },
            h("span", { text: reason.reason }),
            h("div", { class: "bar" }, h("span", { class: "scrap", style: "width:" + reason.share + "%" })),
            h("b", { text: String(reason.qty) })))
        : [h("p", { class: "muted", text: "Nessuno scarto oggi." })];
    return h("div", {},
        commandStrip(),
        h("p", { class: "lede", text: data.formula }),
        h("div", { class: "kpi-grid" },
            h("article", { class: "card kpi-hero" },
                h("p", { class: "muted", text: "OEE di reparto" }),
                h("strong", { class: "band-" + band(plant.oee), text: pct(plant.oee) }),
                h("p", { class: "nums", text: plant.good + " buoni · " + plant.scrap + " scarti · tasso " + pct(plant.scrapRate) })),
            factors),
        h("div", { class: "cards" }, cards),
        h("section", { class: "panel" },
            h("h2", { text: "Scarti per causale" }),
            h("div", { class: "pareto" }, reasons)),
        h("div", { class: "row-actions" },
            h("button", {
                type: "button", class: "go", disabled: state.busy ? "disabled" : null,
                onclick: () => act("lab.demo", { confirm: "DEMO" }),
            }, "Carica un turno di esempio")));
}

function renderLists() {
    const catalog = state.lists ? state.lists.lists : [];
    const view = state.listView;
    const base = state.model.listBases[state.draftBase];
    const baseOptions = Object.entries(state.model.listBases).map(([code, item]) => {
        const option = h("option", { value: code, text: item.title });
        if (code === state.draftBase) option.selected = true;
        return option;
    });
    const checks = Object.entries(base.columns).map(([key, label]) => h("label", {},
        h("input", {
            type: "checkbox",
            checked: state.draftColumns.includes(key) ? "checked" : null,
            onchange: (event) => {
                if (event.target.checked) state.draftColumns = state.draftColumns.concat(key);
                else state.draftColumns = state.draftColumns.filter((column) => column !== key);
            },
        }),
        label));
    let grid = h("p", { class: "muted", text: "Nessuna riga con questo filtro." });
    if (view && view.rows.length) {
        const head = h("tr", {}, view.columns.map((column) => h("th", { text: column.label })));
        const body = view.rows.map((row) => h("tr", {}, view.columns.map((column) => {
            const value = row[column.key];
            return h("td", { text: value == null ? "" : value });
        })));
        grid = h("table", {}, h("thead", {}, head), h("tbody", {}, body));
    }
    const filterBits = [];
    if (view && view.filter) {
        if (view.filter.kind) filterBits.push("tipo = " + view.filter.kind);
        if (view.filter.workplaceCode) filterBits.push("postazione = " + view.filter.workplaceCode);
        if (view.filter.status) filterBits.push("stato = " + view.filter.status);
        if (view.filter.state) filterBits.push("stato macchina = " + view.filter.state);
    }
    const filterNote = filterBits.length ? "Filtro " + filterBits.join(", ") + "." : "Senza filtro.";
    return h("div", {},
        commandStrip(),
        h("p", { class: "lede", text: "Una lista è un numero, un titolo, le colonne e un filtro. LST-11 sono gli scarti, LST-16 l'avanzamento. Il salvataggio chiama list.save: la definizione finisce in hxs_list, la SELECT non la scrive il dialogo." }),
        h("div", { class: "layout" },
            h("section", { class: "stack" },
                h("h2", { text: "Liste" }),
                catalog.map((item) => h("button", {
                    type: "button",
                    class: "choice" + (item.code === state.listCode ? " is-on" : ""),
                    onclick: async () => {
                        state.listCode = item.code;
                        state.listView = await api("/api/lists/" + item.code);
                        render();
                    },
                }, item.code + " · " + item.title))),
            h("section", { class: "panel grow" },
                h("h2", { text: view ? view.code + " · " + view.title : "Lista" }),
                h("p", { class: "muted", text: view ? view.baseTitle + ". " + filterNote : "" }),
                grid,
                h("h2", { text: "Nuova lista" }),
                h("div", { class: "book" },
                    h("label", { class: "field inline" }, "Numero",
                        h("input", {
                            id: "list-number", value: state.draftNumber,
                            oninput: (event) => { state.draftNumber = event.target.value; },
                        })),
                    h("label", { class: "field" }, "Titolo",
                        h("input", {
                            id: "list-title", value: state.draftTitle,
                            oninput: (event) => { state.draftTitle = event.target.value; },
                        })),
                    h("label", { class: "field" }, "Base",
                        h("select", {
                            onchange: (event) => {
                                state.draftBase = event.target.value;
                                state.draftColumns = Object.keys(state.model.listBases[state.draftBase].columns).slice(0, 4);
                                state.draftKind = "";
                                render();
                            },
                        }, baseOptions))),
                h("div", { class: "checks" }, checks),
                state.draftBase === "booking"
                    ? h("label", { class: "field" }, "Filtro tipo",
                        h("select", {
                            onchange: (event) => { state.draftKind = event.target.value; },
                        }, [
                            h("option", { value: "", text: "Tutti" }),
                            h("option", { value: "good", text: "Buoni", selected: state.draftKind === "good" ? "selected" : null }),
                            h("option", { value: "scrap", text: "Scarti", selected: state.draftKind === "scrap" ? "selected" : null }),
                        ]))
                    : null,
                base.filters.includes("workplaceCode")
                    ? h("label", { class: "field" }, "Postazione",
                        h("select", {
                            onchange: (event) => { state.draftWorkplace = event.target.value; },
                        }, [h("option", { value: "", text: "Tutte" })].concat(state.workplaces.map((wp) => {
                            const option = h("option", { value: wp.code, text: wp.code + " · " + wp.name });
                            if (wp.code === state.draftWorkplace) option.selected = true;
                            return option;
                        }))))
                    : null,
                h("div", { class: "row-actions" },
                    h("button", {
                        type: "button", class: "go", disabled: state.busy ? "disabled" : null,
                        onclick: () => {
                            const filter = {};
                            if (state.draftBase === "booking" && state.draftKind) filter.kind = state.draftKind;
                            if (state.draftWorkplace && state.model.listBases[state.draftBase].filters.includes("workplaceCode")) {
                                filter.workplaceCode = state.draftWorkplace;
                            }
                            state.listCode = "LST-" + String(state.draftNumber).replace(/\D/g, "");
                            act("list.save", {
                                code: state.draftNumber,
                                title: state.draftTitle,
                                base: state.draftBase,
                                columns: state.draftColumns,
                                filter,
                            });
                        },
                    }, "Salva lista"),
                    h("button", {
                        type: "button", class: "danger", disabled: state.busy ? "disabled" : null,
                        onclick: () => act("list.delete", { code: state.draftNumber }),
                    }, "Elimina questo numero")))));
}

function renderBridge() {
    const where = {
        oci: "Questo banco sta già parlando con Oracle, utente dedicato.",
        mysql: "Questo banco sta su MySQL, database " + (state.status.mysqlDatabase || "hxs") + ". Le tabelle hanno le stesse colonne di sql/oracle.sql.",
        sqlite: "Questo banco sta su SQLite, il file data/sim.sqlite. Per MySQL di XAMPP copia config.example.php in config.local.php.",
    }[state.status && state.status.driver] || "";
    return h("div", {},
        commandStrip(),
        h("article", { class: "panel prose" },
        h("p", { text: "Oggi il gesto è un comando sul client: DLG per il dialogo, MST per l'anagrafica, LST 11 o LST 16 per la lista, AUN per la valutazione. In VB.NET quel comando legge e scrive tabelle Oracle. Qui il dialogo e la lista chiamano un servizio, e le tabelle hanno gli stessi nomi su MySQL e su Oracle." }),
        h("p", { text: "HYDRA X sposta lo stesso gesto nel browser: la mApp non tiene una copia dei pezzi, chiama il servizio, e la valutazione rilegge il modello." }),
        h("p", { text: "Banco X allena solo quel gesto. Terminale e Monitor sono due mApp. Non tengono copie private dei pezzi: chiamano gli stessi servizi e rileggono gli stessi oggetti. Il segnale macchina non scrive un versamento a mano. Diventa operation.book oppure un fermo." }),
        h("p", { class: "warn", text: "Non è MIP, non è il MOC, e non va puntato allo schema HYDRA. Tabelle hxs_* su un database dedicato." }),
        h("p", { text: where }),
        h("h2", { text: "MySQL su XAMPP, stesse colonne di Oracle" }),
        h("ol", {},
            h("li", { text: "Nel pannello XAMPP avvia MySQL. In C:\\xampp\\php\\php.ini togli il ; da extension=pdo_mysql e riavvia Apache." }),
            h("li", { text: "Copia config.example.php in config.local.php. Il driver è mysql, utente root, password vuota, database hxs." }),
            h("li", { text: "Apri http://localhost/hydra-x-sim/ . La prima pagina crea il database e le tabelle di sql/mysql.sql." }),
            h("li", { text: "I dati non stanno più in data/sim.sqlite. La riga in alto dice MySQL · database hxs." })),
        h("h2", { text: "Spostarlo su Oracle" }),
        h("ol", {},
            h("li", { text: "Crea l'utente HXSIM con CREATE SESSION, CREATE TABLE, CREATE SEQUENCE, CREATE TRIGGER e quota sullo tablespace. Non usare lo schema HYDRA." }),
            h("li", { text: "Connesso come HXSIM, esegui sql/oracle.sql." }),
            h("li", { text: "In config.local.php cambia driver in oci e metti DSN, utente e password. Servono pdo_oci e Instant Client: XAMPP di solito non li ha. Ricarica: se le tabelle sono vuote arrivano tornio, fresatrice, due ordini, LST-11 e LST-16." })),
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
            }, "Ripristina anagrafiche"))));
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
        kpi: renderKpi,
        lists: renderLists,
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
