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
        if (e.id === 'booster') {
            const headerStyles = window.getComputedStyle(document.getElementById('header'));
            let height, width;

            if (headerStyles.display === 'none') {
                const mapStyles = window.getComputedStyle(document.getElementById('map'));
                height = (parseFloat(mapStyles.height) * 50 / 100) - 15;
                width = document.getElementById('mini-dock').getBoundingClientRect().width + 15;
            } else {
                const sidemenuStyles = window.getComputedStyle(document.getElementById('mapmenu'));
                height = (parseFloat(sidemenuStyles.height) * 50 / 100);
                width = 735;
            }

            const boosterDock = document.getElementById('booster');
            const mini_dock = document.getElementById('mini-dock');

            boosterDock.style.width = width + 'px';
            boosterDock.style.height = height + 'px';
            mini_dock.style.maxWidth = width + 'px';
            mini_dock.style.maxHeight = height + 'px';
        }
    }
});

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