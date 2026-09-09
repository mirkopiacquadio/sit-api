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
                        <input type="file" class="form-control form-control-sm" data-import="immobili" accept=".xlsx,.xls,.csv">
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="immobili">Importa</button>
                        <span class="badge-stato text-muted" data-stato="immobili">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 2 &middot; Dettaglio per sottocategoria</div>
                        <input type="file" class="form-control form-control-sm" data-import="dettaglio" accept=".xlsx,.xls,.csv">
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="dettaglio">Importa</button>
                        <span class="badge-stato text-muted" data-stato="dettaglio">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 3 &middot; Tariffario TARI</div>
                        <input type="number" class="form-control form-control-sm" style="max-width:100px" placeholder="anno" data-anno="tariffario">
                        <input type="file" class="form-control form-control-sm" data-import="tariffario" accept=".xlsx,.xls,.csv">
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="tariffario">Importa</button>
                        <span class="badge-stato text-muted" data-stato="tariffario">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 4 &middot; Riduzioni TARI</div>
                        <input type="number" class="form-control form-control-sm" style="max-width:100px" placeholder="anno" data-anno="riduzioni">
                        <input type="file" class="form-control form-control-sm" data-import="riduzioni" accept=".xlsx,.xls,.csv">
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="riduzioni">Importa</button>
                        <span class="badge-stato text-muted" data-stato="riduzioni">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 6 &middot; Componenti familiari (Anagrafe)</div>
                        <input type="file" class="form-control form-control-sm" data-import="anagrafe-famiglie" accept=".xlsx,.xls,.csv">
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="anagrafe-famiglie">Importa</button>
                        <span class="badge-stato text-muted" data-stato="anagrafe-famiglie">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 7 &middot; Cittadini residenti (Anagrafe)</div>
                        <input type="file" class="form-control form-control-sm" data-import="anagrafe-residenti" accept=".xlsx,.xls,.csv">
                        <button class="btn btn-sm btn-primary" data-azione="importa" data-import="anagrafe-residenti">Importa</button>
                        <span class="badge-stato text-muted" data-stato="anagrafe-residenti">non importato</span>
                    </div>
                    <div class="file-row">
                        <div class="titolo">File 8 &middot; Raggruppamento famiglie</div>
                        <input type="file" class="form-control form-control-sm" data-import="gruppi-famiglia" accept=".xlsx,.xls,.csv">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const baseUrl = '/api/monter/booster-tributi';
        let comuneCorrente = null;

        document.getElementById('comuneSelect').addEventListener('change', (e) => {
            comuneCorrente = e.target.value || null;
            document.getElementById('pannelloComune').style.display = comuneCorrente ? 'block' : 'none';
            document.getElementById('cardAnomalie').style.display = 'none';
            document.getElementById('cardRisultati').style.display = 'none';
            if (comuneCorrente) aggiornaStato();
        });

        function aggiornaStato() {
            fetch(`${baseUrl}/${comuneCorrente}/stato`)
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

                fetch(`${baseUrl}/${comuneCorrente}/import/${tipo}`, {
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
            const interval = setInterval(() => {
                fetch(`${baseUrl}/calcola/stato/${jobKey}`)
                    .then(r => r.json())
                    .then(res => {
                        if (!res.success) { clearInterval(interval); return; }
                        ontick(res);
                        if (res.status === 'completed' || res.status === 'error') {
                            clearInterval(interval);
                            onfine(res);
                        }
                    });
            }, 1500);
        }

        function avviaCalcolo(tipo, endpoint) {
            if (!comuneCorrente) { alert('Seleziona prima un comune'); return; }
            const progresso = document.getElementById('progressoCalcolo');
            progresso.textContent = 'Avvio calcolo...';

            fetch(`${baseUrl}/${comuneCorrente}/calcola/${endpoint}`, {
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
                });
        }

        document.getElementById('btnCalcolaMq').addEventListener('click', () => avviaCalcolo('mq', 'mq'));
        document.getElementById('btnCalcolaComponenti').addEventListener('click', () => avviaCalcolo('componenti', 'componenti'));

        function caricaRisultati(tipo, endpoint) {
            fetch(`${baseUrl}/${comuneCorrente}/risultati/${endpoint}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return;
                    document.getElementById('cardRisultati').style.display = 'block';
                    document.getElementById('linkExport').href = `${baseUrl}/${comuneCorrente}/risultati/${endpoint}/export`;
                    document.getElementById('totaleGenerale').textContent =
                        '€ ' + res.totale_generale.toLocaleString('it-IT', { minimumFractionDigits: 2 });

                    const diffLabel = tipo === 'mq' ? 'Diff. mq' : 'Diff. componenti';
                    const diffField = tipo === 'mq' ? 'mq_diff' : 'componenti_diff';
                    const thead = document.querySelector('#tabellaRisultati thead');
                    const tbody = document.querySelector('#tabellaRisultati tbody');
                    thead.innerHTML = `<tr><th>Codice utenza</th><th>Denominazione</th><th>Indirizzo immobile</th><th>${diffLabel}</th><th>Totale recuperabile</th></tr>`;
                    tbody.innerHTML = res.righe.map(r => `<tr>
                        <td>${r.codice_utenza}</td>
                        <td>${r.denominazione ?? ''}</td>
                        <td>${r.indirizzo_immobile ?? ''}</td>
                        <td>${r[diffField]}</td>
                        <td>€ ${Number(r.totale_recuperabile).toLocaleString('it-IT', { minimumFractionDigits: 2 })}</td>
                    </tr>`).join('');
                });
        }
    </script>
</body>

</html>
