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
        #booster-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1955;
        }
        body.booster-modal-open #mini-dock {
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
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
    `;
    document.head.appendChild(style);
})();

function apriBoosterModal() {
    document.body.classList.add('booster-modal-open');

    if (!document.getElementById('booster-backdrop')) {
        const backdrop = document.createElement('div');
        backdrop.id = 'booster-backdrop';
        backdrop.addEventListener('click', chiudiBoosterModal);
        document.body.appendChild(backdrop);
    }

    document.addEventListener('keydown', boosterEscHandler);
}

// Ripristina soltanto la UI del modale (classe + backdrop + listener),
// senza interagire con lo stato del minidock di Lizmap.
function chiudiBoosterModalUI() {
    document.body.classList.remove('booster-modal-open');

    const backdrop = document.getElementById('booster-backdrop');
    if (backdrop) backdrop.remove();

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

    fetch(`https://sitmonter.it/api/booster/dettaglioElaborazione?code_comune=${comuneUtente}&table=${tabella}`)
        .then(res => res.json())
        .then(data => {
            window.boosterRows = data;
            window.currentPage = 1;
            window.rowsPerPage = 20;
            window.currentTabella = tabella;
            renderBoosterDettaglio();
        })
        .catch(() => {
            content.innerHTML = '<p class="text-danger">Errore durante il caricamento.</p>';
        });
}

function renderBoosterDettaglio() {
    const start = (window.currentPage - 1) * window.rowsPerPage;
    const end = start + window.rowsPerPage;
    const visibleRows = window.boosterRows.slice(start, end);
    const totalPages = Math.ceil(window.boosterRows.length / window.rowsPerPage);

    let html = `
        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
            <span class="text-muted">${window.boosterRows.length} righe totali</span>
            <button class="btn btn-sm btn-success" onclick="scaricaElaborazione('${window.currentTabella}')">⬇ Scarica CSV</button>
        </div>
        <div style="overflow-x: auto;">
        <table class="table table-bordered table-sm" style="font-size: 11px;">
            <thead><tr>
                <th>FOGLIO</th>
                <th>PARTICELLA</th>
                <th>STATO</th>
                <th>ZTO</th>
                <th>TIPO CATASTO</th>
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

        html += `<tr style="cursor:pointer;" onclick="apriDaBooster('${foglio}', '${particella}')">
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

function apriDaBooster(foglio, particella) {
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