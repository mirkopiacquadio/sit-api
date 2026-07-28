function formatNomeElaborazione(nome) {
    const raw = nome.replace('aree_edificabili_finali_', '');
    const parts = raw.split('_');
    if (parts.length >= 5) {
        return `${parts[0]}/${parts[1]}/${parts[2]} ore ${parts[3]}:${parts[4]}`;
    }
    return `${parts[0]}/${parts[1]}/${parts[2]}`;
}

lizMap.events.on({
    uicreated: function (e) {
        const loginTributi = ($('#info-user-login').text() || '').toLowerCase();
        if (loginTributi.indexOf('tributi') === -1) return;

        const html_booster = `
            <div id="booster-main" style="padding: 10px; background-color: white; height: 100%; overflow-y: auto;">
                <h4>Elaborazioni Aree Edificabili</h4>
                <div id="elenco-elaborazioni">
                    <p class="text-muted">Caricamento elaborazioni...</p>
                </div>
            </div>

            <div id="booster-dettaglio" style="display: none; padding: 10px; background-color: white; height: 100%; overflow-y: auto;">
                <button class="btn btn-danger" onclick="tornaIndietro()">← Indietro</button>
                <h4 id="booster-dettaglio-title" style="margin-top: 10px;"></h4>
                <div id="booster-dettaglio-content"></div>
            </div>
        `;

        lizMap.addDock('booster', 'Booster Urbanistica', 'minidock', html_booster, 'icon-cog');
        aggiornaElaborazioni();
    },

    minidockopened: function (e) {
        // Il Booster viene mostrato come modale grande e centrato.
        // Ogni altro minidock ripristina lo stato normale.
        if (e.id === 'booster') {
            apriBoosterModal();
        } else {
            chiudiBoosterModalUI();
        }
    },

    minidockclosed: function (e) {
        if (e.id === 'booster') {
            chiudiBoosterModalUI();
        }
    }
});

// ---------------------------------------------------------------------------
// Modale Booster: trasforma il #mini-dock in un overlay centrato molto piu'
// grande quando e' attiva la scheda "booster". Tutti gli stili sono applicati
// via una classe sul <body> (booster-modal-open) e vengono rimossi alla
// chiusura, cosi' non viene alterato il comportamento degli altri minidock.
// ---------------------------------------------------------------------------
(function injectBoosterModalStyle() {
    if (document.getElementById('booster-modal-style')) return;
    const style = document.createElement('style');
    style.id = 'booster-modal-style';
    style.textContent = `
        body.booster-modal-open #mini-dock {
            position: fixed !important;
            top: 0 !important;
            right: 0 !important;
            left: auto !important;
            transform: none !important;
            width: min(1250px, 94vw) !important;
            height: min(880px, 92vh) !important;
            max-width: 94vw !important;
            max-height: 92vh !important;
            background: #fff !important;
            border-radius: 10px !important;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45) !important;
            z-index: 1960 !important;
            overflow: hidden !important;
        }
        body.booster-modal-open #mini-dock .tabbable {
            display: flex !important;
            flex-direction: column !important;
            height: 100% !important;
        }
        body.booster-modal-open #mini-dock #mini-dock-content {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            height: auto !important;
            overflow: hidden !important;
        }
        body.booster-modal-open #mini-dock #mini-dock-tabs {
            flex: 0 0 auto !important;
        }
        body.booster-modal-open #mini-dock #booster.tab-pane {
            height: 100% !important;
        }
        body.booster-modal-open #mini-dock #booster > .booster {
            height: 100% !important;
        }
        body.booster-modal-open #mini-dock #booster-main,
        body.booster-modal-open #mini-dock #booster-dettaglio {
            height: 100% !important;
            max-height: 100% !important;
        }
        /* Titolo leggibile su sfondo bianco (di default e' quasi bianco) */
        body.booster-modal-open #mini-dock span.text {
            color: #333 !important;
        }
        /* Pulsante di chiusura: l'icona e' uno sprite bianco (icon-white),
           quindi lo rendiamo visibile con un cerchietto rosso. */
        body.booster-modal-open #mini-dock .mini-dock-close {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 26px !important;
            height: 26px !important;
            background: #dc3545 !important;
            border-radius: 50% !important;
            z-index: 10 !important;
            cursor: pointer !important;
        }
        /* Riga marcata come "lavorato" */
        /* Riga selezionata (ultima cliccata) — grigia. Il verde "lavorato" ha la precedenza. */
        tr.booster-selezionata > td { background: #e2e3e5 !important; }
        tr.booster-lavorato > td { background: #d4edda !important; }
        /* Switch "lavorato" del dettaglio Booster */
        .booster-switch { position: relative; display: inline-block; width: 38px; height: 20px; vertical-align: middle; }
        .booster-switch input { opacity: 0; width: 0; height: 0; }
        .booster-switch span { position: absolute; inset: 0; background: #ccc; border-radius: 20px; transition: .2s; cursor: pointer; }
        .booster-switch span:before { content: ""; position: absolute; height: 14px; width: 14px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
        .booster-switch input:checked + span { background: #198754; }
        .booster-switch input:checked + span:before { transform: translateX(18px); }
    `;
    document.head.appendChild(style);
})();

function apriBoosterModal() {
    document.body.classList.add('booster-modal-open');
    // Nessun backdrop: la mappa sotto resta visibile e interagibile (pan/zoom).
    document.addEventListener('keydown', boosterEscHandler);
}

// Ripristina soltanto la UI del modale (classe + listener),
// senza interagire con lo stato del minidock di Lizmap.
function chiudiBoosterModalUI() {
    document.body.classList.remove('booster-modal-open');
    document.removeEventListener('keydown', boosterEscHandler);
}

// Chiusura "vera": preme il pulsante di chiusura del minidock Booster
// (che a sua volta scatena l'evento minidockclosed -> chiudiBoosterModalUI).
function chiudiBoosterModal() {
    const closeBtn = Array.from(
        document.querySelectorAll('#mini-dock #booster .mini-dock-close')
    ).find(el => el.offsetParent !== null);

    if (closeBtn) {
        closeBtn.click();
    } else {
        chiudiBoosterModalUI();
    }
}

function boosterEscHandler(e) {
    if (e.key === 'Escape') chiudiBoosterModal();
}

function aggiornaElaborazioni() {
    fetch(`https://sitmonter.it/api/booster/elaborazioni?code_comune=${comuneUtente}`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('elenco-elaborazioni');

            if (!data || data.length === 0) {
                container.innerHTML = '<p class="text-muted">Nessuna elaborazione disponibile.</p>';
                return;
            }

            let html = '<ol style="padding-left: 15px;">';
            data.forEach(nome => {
                const visuale = formatNomeElaborazione(nome);
                html += `
                    <li style="margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <a href="#" onclick="openDettaglioElaborazione('${nome}')" style="font-weight: bold;">${visuale}</a>
                            <div style="display: flex; gap: 5px;">
                                <button class="btn btn-sm btn-success" onclick="scaricaElaborazione('${nome}')">⬇ CSV</button>
                                <button class="btn btn-sm btn-danger" onclick="confermaEliminazione('${nome}')">🗑</button>
                            </div>
                        </div>
                    </li>
                `;
            });
            html += '</ol>';
            container.innerHTML = html;
        })
        .catch(err => {
            console.error('Errore aggiornamento elaborazioni:', err);
            document.getElementById('elenco-elaborazioni').innerHTML = '<p class="text-danger">Errore durante il caricamento.</p>';
        });
}

function scaricaElaborazione(tabella) {
    window.open(`https://sitmonter.it/api/booster/downloadElaborazione?code_comune=${comuneUtente}&table=${tabella}`, '_blank');
}

function confermaEliminazione(tabella) {
    const visuale = formatNomeElaborazione(tabella);
    if (!confirm(`Sei sicuro di voler eliminare l'elaborazione del ${visuale}?`)) return;

    fetch(`https://sitmonter.it/api/booster/elaborazione?code_comune=${comuneUtente}&table=${tabella}`, {
        method: 'DELETE'
    })
        .then(res => {
            if (!res.ok) throw new Error("Errore durante l'eliminazione");
            return res.json();
        })
        .then(() => {
            alert('Elaborazione eliminata con successo.');
            aggiornaElaborazioni();
        })
        .catch(err => {
            alert('Errore: ' + err.message);
        });
}

function openDettaglioElaborazione(tabella) {
    document.getElementById('booster-main').style.display = 'none';
    document.getElementById('booster-dettaglio').style.display = 'block';

    const visuale = formatNomeElaborazione(tabella);
    document.getElementById('booster-dettaglio-title').innerText = `Elaborazione del ${visuale}`;

    const content = document.getElementById('booster-dettaglio-content');
    content.innerHTML = '<p>Caricamento dati...</p>';

    // Retroattivo: assicura che la tabella abbia le colonne id/lavorato prima
    // di leggere i dati (le tabelle vecchie ne erano prive).
    preparaBoosterTabella(tabella).then(() => {
        fetch(`https://sitmonter.it/api/booster/dettaglioElaborazione?code_comune=${comuneUtente}&table=${tabella}`)
            .then(res => res.json())
            .then(data => {
                window.boosterRows = data;
                window.currentPage = 1;
                window.rowsPerPage = 20;
                window.currentTabella = tabella;
                window.boosterSort = null; // reset ordinamento per la nuova tabella
                renderBoosterDettaglio();
            })
            .catch(() => {
                content.innerHTML = '<p class="text-danger">Errore durante il caricamento.</p>';
            });
    });
}

// Chiama il BE per aggiungere id/lavorato se mancanti (idempotente).
function preparaBoosterTabella(tabella) {
    return fetch('https://sitmonter.it/api/booster/preparaTabella', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code_comune: comuneUtente, table: tabella })
    }).catch(() => {});
}

// Intestazione di colonna ordinabile (prime 5 colonne del dettaglio).
// field = nome della proprieta' nella riga (es. 'STRING' per ZTO).
function boosterTh(label, field) {
    const s = window.boosterSort || {};
    const arrow = s.col === field ? (s.dir === 'asc' ? ' ▲' : ' ▼') : ' ⇅';
    return `<th style="cursor:pointer;user-select:none;white-space:nowrap;" onclick="ordinaBooster('${field}')">${label}<span style="opacity:.5;font-size:10px;">${arrow}</span></th>`;
}

// Ordina window.boosterRows sul campo indicato, alternando asc/desc, e
// riparte da pagina 1. FOGLIO e PARTICELLA sono ordinati in modo numerico.
function ordinaBooster(field) {
    const s = window.boosterSort || {};
    const dir = (s.col === field && s.dir === 'asc') ? 'desc' : 'asc';
    window.boosterSort = { col: field, dir: dir };

    const numerico = (field === 'FOGLIO' || field === 'PARTICELLA');

    window.boosterRows.sort((a, b) => {
        let va = a[field], vb = b[field];
        va = (va === null || va === undefined) ? '' : va;
        vb = (vb === null || vb === undefined) ? '' : vb;

        let cmp;
        if (numerico) {
            const na = parseFloat(String(va).replace(',', '.'));
            const nb = parseFloat(String(vb).replace(',', '.'));
            if (!isNaN(na) && !isNaN(nb)) {
                cmp = na - nb;
            } else {
                cmp = String(va).localeCompare(String(vb), 'it', { numeric: true });
            }
        } else {
            cmp = String(va).localeCompare(String(vb), 'it', { numeric: true, sensitivity: 'base' });
        }
        return dir === 'asc' ? cmp : -cmp;
    });

    window.currentPage = 1;
    renderBoosterDettaglio();
}

function renderBoosterDettaglio() {
    const start = (window.currentPage - 1) * window.rowsPerPage;
    const end = start + window.rowsPerPage;
    const visibleRows = window.boosterRows.slice(start, end);
    const totalPages = Math.ceil(window.boosterRows.length / window.rowsPerPage);

    let html = `
        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; gap:8px; flex-wrap:wrap;">
            <span class="text-muted">${window.boosterRows.length} righe totali</span>
            <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                <span class="text-muted" id="booster-sel-count">0 selezionate</span>
                <button class="btn btn-sm btn-success" onclick="bulkLavorato(1)">✔ Lavorate</button>
                <button class="btn btn-sm btn-secondary" onclick="bulkLavorato(0)">↺ Non lavorate</button>
                <button class="btn btn-sm btn-danger" onclick="eliminaRigheSelezionate()">🗑 Elimina selezionate</button>
                <button class="btn btn-sm btn-outline-success" onclick="scaricaElaborazione('${window.currentTabella}')">⬇ CSV</button>
            </div>
        </div>
        <div style="overflow-x: auto;">
        <table class="table table-bordered table-sm" style="font-size: 11px;">
            <thead><tr>
                <th style="width:28px;text-align:center;"><input type="checkbox" title="Seleziona pagina" onclick="toggleSelectAllBooster(this)"></th>
                <th style="text-align:center;white-space:nowrap;">LAVORATO</th>
                ${boosterTh('FOGLIO', 'FOGLIO')}
                ${boosterTh('PARTICELLA', 'PARTICELLA')}
                ${boosterTh('STATO', 'STATO')}
                ${boosterTh('ZTO', 'STRING')}
                ${boosterTh('TIPO CATASTO', 'catasto_tipo')}
                <th>SUP. CATASTALE</th>
                <th>%</th>
                <th>SUP. IN ZTO</th>
                <th>PROPRIETARI / SUB</th>
            </tr></thead>
            <tbody>`;

    visibleRows.forEach(row => {
        const foglio = row.FOGLIO || '';
        const particella = row.PARTICELLA || '';

        // Parsing sub_data
        let subData = [];
        try { subData = row.sub_data ? JSON.parse(row.sub_data) : []; } catch(e) {}
        const subFabbricati = subData.filter(s => s.tipo === 'Fabbricato');

        // Sezione Terreno
        let terrenoHtml = '<div style="margin-bottom:4px;"><strong>Terreno</strong>';
        if (row.proprietario && row.proprietario !== 'ERRORE') {
            const lista = row.proprietario.split(' | ')
                .map(p => `<li style="margin-bottom:2px;">${p.trim()}</li>`)
                .join('');
            terrenoHtml += `<ul style="margin:2px 0 0;padding-left:16px;list-style:disc;">${lista}</ul>`;
        } else {
            terrenoHtml += `<em style="color:#888;font-size:10px;">Non disponibile</em>`;
        }
        terrenoHtml += '</div>';

        // Sezione Fabbricati
        let fabbricatiHtml = '';
        if (subFabbricati.length > 0) {
            fabbricatiHtml = '<div><strong>Fabbricati</strong>';
            subFabbricati.forEach(sub => {
                let label = `Sub ${sub.sub} · ${sub.tipo}`;
                if (sub.catqua) label += ` · ${sub.catqua}`;
                fabbricatiHtml += `<div style="margin-top:4px;padding-left:6px;border-left:2px solid #0d6efd;">
                    <span style="background:#0d6efd;color:white;border-radius:4px;padding:1px 5px;font-size:10px;">${label}</span>`;
                if (sub.proprietario) {
                    const lista = sub.proprietario.split(' | ')
                        .map(p => `<li style="margin-bottom:2px;">${p.trim()}</li>`)
                        .join('');
                    fabbricatiHtml += `<ul style="margin:2px 0 0;padding-left:16px;list-style:disc;">${lista}</ul>`;
                } else {
                    fabbricatiHtml += `<em style="color:#888;font-size:10px;">Non disponibile</em>`;
                }
                fabbricatiHtml += '</div>';
            });
            fabbricatiHtml += '</div>';
        }

        const statoColor = row.STATO === 'LIBERA' ? '#198754' : row.STATO === 'EDIFICATA' ? '#ffc107' : '#6c757d';
        const statoText = row.STATO === 'EDIFICATA' ? 'color:#000;' : 'color:white;';
        const lavorato = String(row.lavorato) === '1';

        html += `<tr class="${lavorato ? 'booster-lavorato' : ''}" style="cursor:pointer;" onclick="apriDaBooster('${foglio}', '${particella}', this)">
                    <td style="text-align:center;" onclick="event.stopPropagation()"><input type="checkbox" class="booster-row-check" value="${row.id}" onchange="aggiornaContatoreSelezione()"></td>
                    <td style="text-align:center;" onclick="event.stopPropagation()">
                        <label class="booster-switch"><input type="checkbox" ${lavorato ? 'checked' : ''} onchange="toggleLavoratoRiga('${row.id}', this.checked, this)"><span></span></label>
                    </td>
                    <td><strong>${foglio}</strong></td>
                    <td><strong>${particella}</strong></td>
                    <td><span style="background:${statoColor};${statoText}border-radius:4px;padding:1px 6px;font-size:10px;">${row.STATO || ''}</span></td>
                    <td>${row.STRING || ''}</td>
                    <td><span style="background:#6c757d;color:white;border-radius:4px;padding:1px 5px;font-size:10px;">${row.catasto_tipo || '—'}</span></td>
                    <td>${row.auiu ? parseFloat(row.auiu).toFixed(2) : ''}</td>
                    <td>${row.perc ? parseFloat(row.perc).toFixed(2) + '%' : ''}</td>
                    <td>${row.aisect ? parseFloat(row.aisect).toFixed(2) : ''}</td>
                    <td style="max-width:320px;">${terrenoHtml}${fabbricatiHtml}</td>
                </tr>`;
    });

    html += `</tbody></table></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
            <button class="btn btn-sm btn-outline-primary" ${window.currentPage === 1 ? 'disabled' : ''} onclick="cambioBoosterPage(-1)">« Prec</button>
            <span>Pagina ${window.currentPage} di ${totalPages}</span>
            <button class="btn btn-sm btn-outline-primary" ${window.currentPage === totalPages ? 'disabled' : ''} onclick="cambioBoosterPage(1)">Succ »</button>
        </div>`;

    document.getElementById('booster-dettaglio-content').innerHTML = html;
}

function apriDaBooster(foglio, particella, tr) {
    // Evidenzia in grigio la riga cliccata (selezione singola): spegne la
    // precedente e accende la nuova. Il verde "lavorato" resta prioritario.
    if (tr) {
        document.querySelectorAll('#booster-dettaglio-content tr.booster-selezionata')
            .forEach(r => r.classList.remove('booster-selezionata'));
        tr.classList.add('booster-selezionata');
    }

    var rowData = {
        foglio: foglio,
        numero: particella,
        sub: null,
        tipologia: 'terreno'
    };

    openLayer(rowData);
}

function cambioBoosterPage(delta) {
    const totalPages = Math.ceil(window.boosterRows.length / window.rowsPerPage);
    window.currentPage = Math.max(1, Math.min(window.currentPage + delta, totalPages));
    renderBoosterDettaglio();
}

function tornaIndietro() {
    document.getElementById('booster-main').style.display = 'block';
    document.getElementById('booster-dettaglio').style.display = 'none';
}

// ---------------------------------------------------------------------------
// Selezione righe + azioni "lavorato" / eliminazione (una o piu' righe).
// ---------------------------------------------------------------------------
function getBoosterSelectedIds() {
    return Array.from(document.querySelectorAll('.booster-row-check:checked'))
        .map(c => parseInt(c.value, 10))
        .filter(n => !isNaN(n) && n > 0);
}

function aggiornaContatoreSelezione() {
    const el = document.getElementById('booster-sel-count');
    if (el) el.textContent = getBoosterSelectedIds().length + ' selezionate';
}

function toggleSelectAllBooster(master) {
    document.querySelectorAll('.booster-row-check').forEach(c => { c.checked = master.checked; });
    aggiornaContatoreSelezione();
}

// Aggiorna localmente il valore lavorato nelle righe in memoria.
function aggiornaLavoratoLocale(ids, val) {
    const set = new Set(ids.map(String));
    window.boosterRows.forEach(r => { if (set.has(String(r.id))) r.lavorato = val; });
}

function toggleLavoratoRiga(id, checked, input) {
    const val = checked ? 1 : 0;
    fetch('https://sitmonter.it/api/booster/lavorato', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code_comune: comuneUtente, table: window.currentTabella, ids: [id], lavorato: val })
    })
        .then(res => res.json())
        .then(res => {
            if (res && res.ok) {
                // Aggiornamento ottimistico: niente re-render, mantiene selezione/posizione.
                aggiornaLavoratoLocale([id], val);
                if (input) {
                    const tr = input.closest('tr');
                    if (tr) tr.classList.toggle('booster-lavorato', val === 1);
                }
            } else {
                if (input) input.checked = !checked; // revert
                alert('Errore: ' + ((res && res.error) || 'operazione fallita'));
            }
        })
        .catch(() => { if (input) input.checked = !checked; alert('Errore di rete'); });
}

function bulkLavorato(val) {
    const ids = getBoosterSelectedIds();
    if (!ids.length) { alert('Nessuna riga selezionata'); return; }
    fetch('https://sitmonter.it/api/booster/lavorato', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code_comune: comuneUtente, table: window.currentTabella, ids: ids, lavorato: val })
    })
        .then(res => res.json())
        .then(res => {
            if (res && res.ok) {
                aggiornaLavoratoLocale(ids, val);
                renderBoosterDettaglio();
            } else {
                alert('Errore: ' + ((res && res.error) || 'operazione fallita'));
            }
        })
        .catch(() => alert('Errore di rete'));
}

function eliminaRigheSelezionate() {
    const ids = getBoosterSelectedIds();
    if (!ids.length) { alert('Nessuna riga selezionata'); return; }
    if (!confirm('Eliminare definitivamente ' + ids.length + ' riga/e? L\'operazione non è reversibile.')) return;

    fetch('https://sitmonter.it/api/booster/eliminaRighe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code_comune: comuneUtente, table: window.currentTabella, ids: ids })
    })
        .then(res => res.json())
        .then(res => {
            if (res && res.ok) {
                const set = new Set(ids.map(String));
                window.boosterRows = window.boosterRows.filter(r => !set.has(String(r.id)));
                const totalPages = Math.max(1, Math.ceil(window.boosterRows.length / window.rowsPerPage));
                if (window.currentPage > totalPages) window.currentPage = totalPages;
                renderBoosterDettaglio();
            } else {
                alert('Errore: ' + ((res && res.error) || 'operazione fallita'));
            }
        })
        .catch(() => alert('Errore di rete'));
}