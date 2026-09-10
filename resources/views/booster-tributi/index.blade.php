<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Booster Tributi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1f5f4a;
            --secondary-color: #2c7a5b;
            --accent-color: #e8a33d;
        }

        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', sans-serif;
        }

        .header-bt {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: #fff;
            padding: 1.5rem 2rem;
        }

        .container-bt {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .card-bt {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 1.25rem;
        }

        .file-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem 0;
            border-bottom: 1px solid #eee;
        }

        .file-row:last-child {
            border-bottom: none;
        }

        .file-row .titolo {
            flex: 0 0 260px;
            font-weight: 600;
            font-size: .9rem;
        }

        .badge-stato {
            font-size: .75rem;
        }

        .anomalia-pill {
            display: inline-block;
            padding: .25rem .6rem;
            border-radius: 20px;
            background: #fdecea;
            color: #c0392b;
            font-size: .8rem;
            margin: .15rem;
        }

        #totaleGenerale {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .file-input-it .btn-scegli-file {
            cursor: pointer;
            margin-bottom: 0;
            white-space: nowrap;
        }

        .file-input-it .nome-file {
            background: #fff;
        }

        #tabellaRisultati tbody tr {
            cursor: pointer;
        }

        #tabellaRisultati tbody tr:hover {
            background-color: rgba(31, 95, 74, .07);
        }

        .box-oggi {
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            overflow: hidden;
        }

        .box-oggi .box-oggi-titolo {
            background: #607d8b;
            color: #fff;
            font-weight: 600;
            padding: .4rem .75rem;
        }

        .box-domani .box-oggi-titolo {
            background: var(--secondary-color);
        }

        .diff-evidenza {
            font-weight: 700;
        }

        .diff-positiva {
            color: #c0392b;
        }

        .diff-nulla {
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="header-bt">
        <h3 class="mb-1"><i class="bi bi-graph-up-arrow"></i> Booster Tributi</h3>
        <div class="small opacity-75">Incrocio Halley Tributi (TARI) &times; Territorio &times; Anagrafe</div>
    </div>

    <div class="container-bt">
        <div class="card card-bt">
            <div class="card-body">
                <label class="form-label fw-bold">Comune</label>
                <select id="comuneSelect" class="form-select" style="max-width: 360px;">
                    <option value="">-- seleziona comune --</option>
                    @foreach ($comuni as $codice => $nome)
                        <option value="{{ $codice }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div id="pannelloComune" style="display:none;">
            <div class="card card-bt">
                <div class="card-header bg-white fw-bold"><i class="bi bi-upload"></i> Importazioni iniziali TARI</div>
                <div class="card-body">
                    <div class="file-row">
                        <div class="titolo">File 1 &middot; Immobili TARI attivi</div>
                        @include('booster-tributi.partials.file-input-it', ['nome' => 'immobili'])
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="immobili">Importa</button>
                        <span class="badge-stato text-muted" data-stato="immobili">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 2 &middot; Dettaglio per sottocategoria</div>
                        @include('booster-tributi.partials.file-input-it', ['nome' => 'dettaglio'])
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="dettaglio">Importa</button>
                        <span class="badge-stato text-muted" data-stato="dettaglio">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 3 &middot; Tariffario TARI</div>
                        <input type="number" class="form-control form-control-sm" style="max-width:100px" placeholder="anno" data-anno="tariffario">
                        @include('booster-tributi.partials.file-input-it', ['nome' => 'tariffario'])
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="tariffario">Importa</button>
                        <span class="badge-stato text-muted" data-stato="tariffario">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 4 &middot; Riduzioni TARI</div>
                        <input type="number" class="form-control form-control-sm" style="max-width:100px" placeholder="anno" data-anno="riduzioni">
                        @include('booster-tributi.partials.file-input-it', ['nome' => 'riduzioni'])
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="riduzioni">Importa</button>
                        <span class="badge-stato text-muted" data-stato="riduzioni">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 6 &middot; Componenti familiari (Anagrafe)</div>
                        @include('booster-tributi.partials.file-input-it', ['nome' => 'anagrafe-famiglie'])
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="anagrafe-famiglie">Importa</button>
                        <span class="badge-stato text-muted" data-stato="anagrafe-famiglie">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 7 &middot; Cittadini residenti (Anagrafe)</div>
                        @include('booster-tributi.partials.file-input-it', ['nome' => 'anagrafe-residenti'])
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="anagrafe-residenti">Importa</button>
                        <span class="badge-stato text-muted" data-stato="anagrafe-residenti">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 8 &middot; Raggruppamento famiglie</div>
                        @include('booster-tributi.partials.file-input-it', ['nome' => 'gruppi-famiglia'])
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="gruppi-famiglia">Importa</button>
                        <span class="badge-stato text-muted" data-stato="gruppi-famiglia">non importato</span>
                    </div>
                </div>
            </div>

            <div class="card card-bt" id="cardAnomalie" style="display:none;">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-camera"></i> Fotografia anomalie (File 1)</span>
                    <a href="#" id="linkExportAnomalie" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Esporta Excel</a>
                </div>
                <div class="card-body" id="anomalieBody"></div>
            </div>

            <div class="card card-bt">
                <div class="card-header bg-white fw-bold"><i class="bi bi-calculator"></i> Calcolo recupero</div>
                <div class="card-body">
                    <div class="d-flex gap-2 mb-3">
                        <button class="btn btn-outline-success" id="btnCalcolaMq">Calcola differenze mq TARI</button>
                        <button class="btn btn-outline-success" id="btnCalcolaComponenti">Calcola differenze componenti familiari</button>
                    </div>
                    <div id="progressoCalcolo" class="text-muted small"></div>
                </div>
            </div>

            <div class="card card-bt" id="cardRisultati" style="display:none;">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-table"></i> Risultati</span>
                    <a href="#" id="linkExport" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Esporta Excel</a>
                </div>
                <div class="card-body">
                    <div class="mb-2">Totale generale recuperabile: <span id="totaleGenerale">&euro; 0,00</span></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="tabellaRisultati">
                            <thead></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDettaglio" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> Dettaglio posizione &middot; Oggi e Domani</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body" id="dettaglioBody"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const baseUrl = '/api/monter/booster-tributi';
        let comuneCorrente = null;

        // Senza 'Accept: application/json' Laravel tratta le risposte di
        // errore (validazione, eccezioni) come richieste "normali" e
        // restituisce una pagina HTML invece di JSON, mandando in crash il
        // successivo r.json() (Unexpected token '<').
        function apiFetch(url, options = {}) {
            options.headers = Object.assign({ Accept: 'application/json' }, options.headers || {});

            return fetch(url, options);
        }

        document.querySelectorAll('input[type="file"][data-import]').forEach(input => {
            input.addEventListener('change', () => {
                const etichetta = document.querySelector(`[data-filename-for="${input.dataset.import}"]`);
                etichetta.textContent = input.files.length ? input.files[0].name : 'Nessun file selezionato';
            });
        });

        document.getElementById('comuneSelect').addEventListener('change', (e) => {
            comuneCorrente = e.target.value || null;
            document.getElementById('pannelloComune').style.display = comuneCorrente ? 'block' : 'none';
            document.getElementById('cardAnomalie').style.display = 'none';
            document.getElementById('cardRisultati').style.display = 'none';
            if (comuneCorrente) aggiornaStato();
        });

        function aggiornaStato() {
            apiFetch(`${baseUrl}/${comuneCorrente}/stato`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return;
                    const mappaTipi = {
                        immobili: 'file1_immobili',
                        dettaglio: 'file2_dettaglio_sottocategoria',
                        tariffario: 'file3_tariffario',
                        riduzioni: 'file4_riduzioni',
                        'anagrafe-famiglie': 'file6_anagrafe_famiglie',
                        'anagrafe-residenti': 'file7_anagrafe_residenti',
                        'gruppi-famiglia': 'file8_gruppi_famiglia',
                    };
                    Object.entries(mappaTipi).forEach(([chiave, tipoFile]) => {
                        const el = document.querySelector(`[data-stato="${chiave}"]`);
                        const batch = res.batch[tipoFile];
                        if (batch) {
                            el.textContent = `importato: ${batch.righe_importate} righe (${new Date(batch.imported_at).toLocaleString('it-IT')})`;
                            el.classList.remove('text-muted');
                            el.classList.add('text-success');
                        }
                    });
                });
        }

        document.querySelectorAll('[data-azione="importa"]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!comuneCorrente) { alert('Seleziona prima un comune'); return; }
                const tipo = btn.dataset.import;
                const input = document.querySelector(`input[type="file"][data-import="${tipo}"]`);
                if (!input.files.length) { alert('Seleziona un file'); return; }

                const formData = new FormData();
                formData.append('file', input.files[0]);

                const annoInput = document.querySelector(`input[data-anno="${tipo}"]`);
                if (annoInput) formData.append('anno', annoInput.value);

                btn.disabled = true;
                btn.textContent = 'Importazione...';

                apiFetch(`${baseUrl}/${comuneCorrente}/import/${tipo}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                })
                    .then(r => r.json())
                    .then(res => {
                        btn.disabled = false;
                        btn.textContent = 'Importa';
                        if (!res.success) { alert('Errore: ' + res.error); return; }
                        aggiornaStato();
                        if (tipo === 'immobili' && res.anomalie) {
                            mostraAnomalie(res.anomalie, res.import.batch_id);
                        }
                    })
                    .catch(err => {
                        btn.disabled = false;
                        btn.textContent = 'Importa';
                        alert('Errore di rete: ' + err.message);
                    });
            });
        });

        function mostraAnomalie(anomalie, batchId) {
            const card = document.getElementById('cardAnomalie');
            const body = document.getElementById('anomalieBody');
            document.getElementById('linkExportAnomalie').href = `${baseUrl}/${comuneCorrente}/anomalie/${batchId}/export`;
            const etichette = {
                senza_intestatario: 'Senza intestatario',
                senza_catasto: 'Senza riferimenti catastali',
                senza_indirizzo: 'Senza indirizzo residenza/recapito',
                componenti_zero_sospetti: 'Componenti residenti a 0 (sospetto)',
                senza_mq_tari: 'Senza mq TARI',
                deceduto: 'Intestatario deceduto',
            };
            body.innerHTML = `<p>Righe totali: <strong>${anomalie.totale_righe}</strong> &middot; con almeno un'anomalia: <strong>${anomalie.righe_con_anomalie}</strong></p>` +
                Object.entries(anomalie.per_tipo).map(([tipo, n]) =>
                    `<span class="anomalia-pill">${etichette[tipo] || tipo}: ${n}</span>`
                ).join('');
            card.style.display = 'block';
        }

        function pollJob(jobKey, ontick, onfine) {
            // Il job appare in cache solo quando un worker (`php artisan
            // queue:work`) lo prende in carico: se dopo ~15s non è ancora
            // comparso, molto probabilmente il worker non è attivo, invece
            // di restare bloccati in silenzio su "Avvio calcolo...".
            let tentativiSenzaEsito = 0;
            const SOGLIA_WORKER_INATTIVO = 10;

            const interval = setInterval(() => {
                apiFetch(`${baseUrl}/calcola/stato/${jobKey}`)
                    .then(r => r.json())
                    .then(res => {
                        if (!res.success) {
                            tentativiSenzaEsito++;
                            if (tentativiSenzaEsito >= SOGLIA_WORKER_INATTIVO) {
                                clearInterval(interval);
                                onfine({ status: 'error', errore: 'Il calcolo non è partito: il queue worker (php artisan queue:work) sembra non attivo sul server.' });
                            }
                            return;
                        }
                        tentativiSenzaEsito = 0;
                        ontick(res);
                        if (res.status === 'completed' || res.status === 'error') {
                            clearInterval(interval);
                            onfine(res);
                        }
                    })
                    .catch(err => {
                        clearInterval(interval);
                        onfine({ status: 'error', errore: 'Errore di rete: ' + err.message });
                    });
            }, 1500);
        }

        function avviaCalcolo(tipo, endpoint) {
            if (!comuneCorrente) { alert('Seleziona prima un comune'); return; }
            const progresso = document.getElementById('progressoCalcolo');
            progresso.textContent = 'Avvio calcolo...';

            apiFetch(`${baseUrl}/${comuneCorrente}/calcola/${endpoint}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) { progresso.textContent = ''; alert('Errore: ' + res.error); return; }
                    pollJob(res.job_key, (stato) => {
                        progresso.textContent = `Elaborazione in corso: ${stato.processate || 0}/${stato.totale || '?'}`;
                    }, (stato) => {
                        if (stato.status === 'error') {
                            progresso.textContent = 'Errore: ' + stato.errore;
                            return;
                        }
                        progresso.textContent = `Completato: ${stato.righe_risultato} immobili con recupero individuato.`;
                        caricaRisultati(tipo, endpoint);
                    });
                })
                .catch(err => {
                    progresso.textContent = '';
                    alert('Errore di rete: ' + err.message);
                });
        }

        document.getElementById('btnCalcolaMq').addEventListener('click', () => avviaCalcolo('mq', 'mq'));
        document.getElementById('btnCalcolaComponenti').addEventListener('click', () => avviaCalcolo('componenti', 'componenti'));

        let ultimiRisultati = [];
        let ultimoTipoRisultato = null;

        function fmtEuro(n) {
            return '€ ' + Number(n ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2 });
        }

        function caricaRisultati(tipo, endpoint) {
            apiFetch(`${baseUrl}/${comuneCorrente}/risultati/${endpoint}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return;
                    document.getElementById('cardRisultati').style.display = 'block';
                    document.getElementById('linkExport').href = `${baseUrl}/${comuneCorrente}/risultati/${endpoint}/export`;
                    document.getElementById('totaleGenerale').textContent = fmtEuro(res.totale_generale);

                    ultimiRisultati = res.righe;
                    ultimoTipoRisultato = tipo;

                    const diffLabel = tipo === 'mq' ? 'Diff. mq' : 'Diff. componenti';
                    const diffField = tipo === 'mq' ? 'mq_diff' : 'componenti_diff';
                    const thead = document.querySelector('#tabellaRisultati thead');
                    const tbody = document.querySelector('#tabellaRisultati tbody');
                    thead.innerHTML = `<tr><th>Codice utenza</th><th>Denominazione</th><th>Indirizzo immobile</th><th>${diffLabel}</th><th>Totale recuperabile</th></tr>`;
                    tbody.innerHTML = res.righe.map((r, idx) => `<tr data-idx="${idx}" title="Clicca per il dettaglio Oggi/Domani">
                        <td>${r.codice_utenza}</td>
                        <td>${r.denominazione ?? ''}</td>
                        <td>${r.indirizzo_immobile ?? ''}</td>
                        <td>${r[diffField]}</td>
                        <td>${fmtEuro(r.totale_recuperabile)}</td>
                    </tr>`).join('');
                    tbody.querySelectorAll('tr').forEach(tr => {
                        tr.addEventListener('click', () => mostraDettaglioPosizione(ultimiRisultati[tr.dataset.idx], ultimoTipoRisultato));
                    });
                });
        }

        function mostraDettaglioPosizione(riga, tipo) {
            const componentiDichiarati = Number(riga.componenti_residenti ?? 0);
            const mqOggi = Number(riga.mq_tari ?? 0);
            const mqCatasto = Number(riga.mq_catasto ?? 0);

            const oggi = {
                'Mq dichiarati TARI': mqOggi.toLocaleString('it-IT'),
                'Mq catastali (Sister)': mqCatasto.toLocaleString('it-IT'),
                'Componenti residenti dichiarati': componentiDichiarati,
                'Componenti non residenti': Number(riga.componenti_non_residenti ?? 0),
            };

            let domani, diffLabel, diffValore;
            if (tipo === 'mq') {
                const mqCorretti = Math.round(mqCatasto * 0.8 * 100) / 100;
                diffLabel = 'Differenza mq non dichiarati';
                diffValore = Number(riga.mq_diff ?? 0);
                domani = {
                    'Mq corretti (80% mq catasto)': mqCorretti.toLocaleString('it-IT'),
                    'Mq dichiarati TARI (oggi)': mqOggi.toLocaleString('it-IT'),
                };
            } else {
                const componentiReali = componentiDichiarati + Number(riga.componenti_diff ?? 0);
                diffLabel = 'Differenza componenti non dichiarati';
                diffValore = Number(riga.componenti_diff ?? 0);
                domani = {
                    'Componenti reali (Anagrafe)': componentiReali,
                    'Componenti dichiarati TARI (oggi)': componentiDichiarati,
                    'Indirizzo/residenza coerenti': riga.match_residenza_ubicazione === true ? 'Sì' : (riga.match_residenza_ubicazione === false ? 'No' : 'N/D'),
                };
            }

            const classeDiff = diffValore > 0 ? 'diff-positiva' : 'diff-nulla';

            const dettaglioAnni = typeof riga.dettaglio_anni === 'string' ? JSON.parse(riga.dettaglio_anni) : (riga.dettaglio_anni ?? {});
            const righeAnni = Object.keys(dettaglioAnni).sort().map(anno => {
                const d = dettaglioAnni[anno];
                if (d.errore) {
                    return `<tr><td>${anno}</td><td colspan="4" class="text-muted">${d.errore}</td></tr>`;
                }
                return `<tr>
                    <td>${anno}</td>
                    <td>${fmtEuro(d.dovuto)}</td>
                    <td>${fmtEuro(d.sanzione)}</td>
                    <td>${fmtEuro(d.interessi)}</td>
                    <td class="fw-bold">${fmtEuro(d.totale)}</td>
                </tr>`;
            }).join('');

            const boxHtml = (titolo, classe, dati) => `
                <div class="box-oggi ${classe} h-100">
                    <div class="box-oggi-titolo">${titolo}</div>
                    <table class="table table-sm mb-0">
                        <tbody>
                            ${Object.entries(dati).map(([k, v]) => `<tr><td>${k}</td><td class="text-end fw-semibold">${v}</td></tr>`).join('')}
                        </tbody>
                    </table>
                </div>`;

            document.getElementById('dettaglioBody').innerHTML = `
                <p class="mb-1"><strong>${riga.denominazione ?? ''}</strong> &middot; ${riga.codice_fiscale_piva ?? ''}</p>
                <p class="text-muted small mb-3">Codice utenza ${riga.codice_utenza} &middot; ${riga.indirizzo_immobile ?? ''}</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">${boxHtml('OGGI (dati storici)', 'box-oggi', oggi)}</div>
                    <div class="col-md-6">${boxHtml('DOMANI (dati simulati)', 'box-domani', domani)}</div>
                </div>
                <p class="${classeDiff} diff-evidenza">${diffLabel}: ${diffValore}</p>
                <h6 class="mt-3">Recupero per anno (dovuto, sanzioni, interessi)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Anno</th><th>Dovuto</th><th>Sanzione</th><th>Interessi</th><th>Totale</th></tr></thead>
                        <tbody>${righeAnni}</tbody>
                    </table>
                </div>
                <p class="text-end fw-bold fs-5 mb-0">Totale recuperabile: ${fmtEuro(riga.totale_recuperabile)}</p>
            `;

            new bootstrap.Modal(document.getElementById('modalDettaglio')).show();
        }
    </script>
</body>

</html>
