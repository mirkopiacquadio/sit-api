<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booster Urbanistica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --sidebar-width: 380px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .sidebar-subtitle {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .comune-selector {
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin: 1rem 1.5rem;
        }

        .form-select-white {
            background: white;
            color: var(--primary-color);
            border: 2px solid rgba(255, 255, 255, 0.3);
            font-weight: 500;
        }

        .sidebar-menu {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .menu-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .menu-item .menu-label {
            display: block;
            padding: 1.25rem 1.5rem;
            color: white;
            font-weight: 500;
        }

        .menu-item.active {
            background: rgba(52, 152, 219, 0.3);
            border-left: 4px solid var(--accent-color);
        }

        .menu-item.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .zto-container {
            max-height: 450px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            background: #fafbfc;
        }

        .form-check {
            padding: 0.75rem 1rem;
            background: white;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            border: 1px solid #e9ecef;
        }

        .form-check:hover {
            background: #f8f9fa;
            border-color: var(--accent-color);
        }

        .elaborazioni-list {
            background: white;
        }

        .elaborazione-item {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .elaborazione-item:last-child {
            border-bottom: none;
        }

        .btn-professional {
            padding: 0.75rem 2rem;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .loading-spinner.active {
            display: block;
        }

        .errori-panel {
            background: #fff8e1;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title"><i class="bi bi-building me-2"></i>Booster Monter</div>
            <div class="sidebar-subtitle">Sistema di analisi urbanistica</div>
        </div>
        <div class="comune-selector">
            <label class="form-label text-white mb-2"><i class="bi bi-geo-alt me-2"></i>Seleziona Comune</label>
            <select id="comuneSelect" class="form-select form-select-white">
                <option value="">-- Scegli un comune --</option>
                @foreach ($comuni as $code => $nome)
                    <option value="{{ $code }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-item">
                <div class="menu-label">TARIFFARIO</div>
            </li>
            <li class="menu-item active">
                <div class="menu-label">AREE EDIFICABILI</div>
            </li>
            <li class="menu-item disabled">
                <div class="menu-label">EDIFICI FANTASMA</div>
            </li>
            <li class="menu-item disabled">
                <div class="menu-label">EDIFICI "F"</div>
            </li>
            <li class="menu-item disabled">
                <div class="menu-label">RURALITÀ</div>
            </li>
            <li class="menu-item disabled">
                <div class="menu-label">EDIFICI TUTTI</div>
            </li>
            <li class="menu-item disabled">
                <div class="menu-label">TERRENI TUTTI</div>
            </li>
            <li class="menu-item disabled">
                <div class="menu-label">QUADRO PROIEZIONI</div>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1 class="mb-2">Elaborazione Zone Territoriali Omogenee</h1>
            <p class="text-muted mb-0">Sistema di analisi e gestione aree edificabili</p>
        </div>

        <div id="comuneWarning" class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Attenzione:</strong> Seleziona un comune dal menu laterale per iniziare.
        </div>

        <div id="loadingSpinner" class="loading-spinner">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3 text-muted">Caricamento dati in corso...</p>
        </div>

        <div id="mainContent" style="display: none;">
            <div id="messages"></div>

            {{-- Banner job in corso --}}
            <div id="jobBanner" style="display:none;" class="alert alert-info d-flex align-items-center gap-3 mb-3 py-3 px-4 shadow-sm" role="alert">
                <span class="spinner-border spinner-border-sm flex-shrink-0" role="status"></span>
                <div>
                    <strong>Elaborazione in corso</strong>
                    <span id="jobBannerTable" class="ms-2 text-muted" style="font-size:0.85rem;"></span>
                    <div class="mt-1">
                        <span id="jobBannerText">Avvio in corso...</span>
                        <div class="progress mt-1" style="height:6px;min-width:200px;">
                            <div id="jobBannerBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pannello verifica errori geometrie --}}
            <div class="errori-panel" id="erroriPanel">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><i class="bi bi-exclamation-circle me-2"></i>Verifica Poligoni</strong>
                        <small class="text-muted ms-2">Controlla la validità delle geometrie prima di elaborare</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-warning" id="btnErroriCatasto" onclick="verificaErrori('catasto')">
                            <i class="bi bi-map me-1"></i>Verifica Catasto
                        </button>
                        <button class="btn btn-sm btn-warning" id="btnErroriUrbanistica" onclick="verificaErrori('urbanistica')">
                            <i class="bi bi-layers me-1"></i>Verifica Urbanistica
                        </button>
                    </div>
                </div>
                <div id="erroriResult" class="mt-2" style="display:none;"></div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <i class="bi bi-plus-circle me-2"></i>Nuova Elaborazione
                            <span id="pianoName" class="badge bg-info ms-2"></span>
                        </div>
                        <div class="card-body">
                            <form id="elaboraForm">
                                <input type="hidden" id="codeComune" name="code_comune">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <label class="form-label fw-semibold mb-0">
                                            <i class="bi bi-geo-alt me-2"></i>Seleziona Zone Territoriali
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleAllBtn">
                                            <i class="bi bi-check-all me-1"></i>Seleziona Tutto
                                        </button>
                                    </div>
                                    <div class="zto-container" id="ztoContainer"></div>
                                </div>
                                <hr class="my-4">
                                <div class="mb-4" style="background:#fff3cd;border:1px solid #ffc107;padding:1rem;border-radius:8px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="exclude" id="exclude">
                                        <label class="form-check-label fw-semibold" for="exclude" style="color:#856404;">
                                            <i class="bi bi-building me-2"></i>Escludi interamente particelle con edificio
                                        </label>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary btn-professional" id="submitBtn">
                                        <i class="bi bi-gear me-2"></i>Avvia Elaborazione
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <i class="bi bi-folder me-2"></i>Elaborazioni Archiviate
                        </div>
                        <div id="elaborazioniList">
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-inbox" style="font-size:3rem;opacity:0.3;"></i>
                                <p class="mt-3 mb-0">Nessuna elaborazione disponibile</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentComune = null;

        document.getElementById('comuneSelect').addEventListener('change', function() {
            const comuneCode = this.value;
            if (!comuneCode) {
                document.getElementById('comuneWarning').style.display = 'block';
                document.getElementById('mainContent').style.display = 'none';
                currentComune = null;
                return;
            }
            currentComune = comuneCode;
            loadComuneData(comuneCode);
        });

        async function loadComuneData(comuneCode) {
            document.getElementById('comuneWarning').style.display = 'none';
            document.getElementById('mainContent').style.display = 'none';
            document.getElementById('loadingSpinner').classList.add('active');
            document.getElementById('jobBanner').style.display = 'none';

            try {
                const response = await fetch('/api/monter/booster/zto', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        code_comune: comuneCode
                    })
                });
                const data = await response.json();
                if (data.error) {
                    showMessage(data.error, 'danger');
                    return;
                }

                populateZTO(data.data);
                document.getElementById('pianoName').textContent = data.piano_name;
                document.getElementById('codeComune').value = comuneCode;
                document.getElementById('erroriResult').style.display = 'none';
                await loadElaborazioni(comuneCode);
                await checkActiveJobs(comuneCode);

                document.getElementById('loadingSpinner').classList.remove('active');
                document.getElementById('mainContent').style.display = 'block';
            } catch (error) {
                showMessage('Errore durante il caricamento dei dati', 'danger');
                document.getElementById('loadingSpinner').classList.remove('active');
            }
        }

        async function checkActiveJobs(comuneCode) {
            try {
                const response = await fetch(`/api/monter/booster/elaborazioni/${comuneCode}`);
                const elaborazioni = await response.json();
                if (!elaborazioni || elaborazioni.length === 0) return;

                const latest = elaborazioni[0];
                const statusRes = await fetch(`/api/monter/booster/jobStatus?table=${latest}`);
                const status = await statusRes.json();

                // Solo se veramente in corso — NON su unknown
                if (status.status === 'running') {
                    setJobRunningUI(latest, status.processed ?? 0, status.total ?? 0);
                    startJobPolling(latest, document.getElementById('submitBtn'), comuneCode);
                }
                // queued e unknown → non fare nulla, il job è già finito
            } catch (e) {
                console.error('Errore check job attivo:', e);
            }
        }

        function populateZTO(ztoList) {
            const container = document.getElementById('ztoContainer');
            container.innerHTML = '<div class="row g-2"></div>';
            const row = container.querySelector('.row');
            ztoList.forEach((zto, index) => {
                const col = document.createElement('div');
                col.className = 'col-md-6';
                col.innerHTML = `
                    <div class="form-check">
                        <input class="form-check-input zto-checkbox" type="checkbox" name="zto[]" value="${zto.STRING}" id="zto_${index}">
                        <label class="form-check-label fw-semibold" for="zto_${index}">${zto.STRING}</label>
                    </div>`;
                row.appendChild(col);
            });
        }

        function formatTableLabel(tabella) {
            const raw = tabella.replace('aree_edificabili_finali_', '');
            const parts = raw.split('_');
            if (parts.length >= 5) {
                return `${parts[0]}/${parts[1]}/${parts[2]} ore ${parts[3]}:${parts[4]}`;
            }
            return `${parts[0]}/${parts[1]}/${parts[2]}`;
        }

        async function loadElaborazioni(comuneCode) {
            try {
                const response = await fetch(`/api/monter/booster/elaborazioni/${comuneCode}`);
                const elaborazioni = await response.json();
                const container = document.getElementById('elaborazioniList');

                if (!elaborazioni || elaborazioni.length === 0) {
                    container.innerHTML = `<div class="text-center text-muted py-5">
                        <i class="bi bi-inbox" style="font-size:3rem;opacity:0.3;"></i>
                        <p class="mt-3 mb-0">Nessuna elaborazione disponibile</p></div>`;
                    return;
                }

                container.innerHTML = '<div class="elaborazioni-list"></div>';
                const list = container.querySelector('.elaborazioni-list');
                elaborazioni.forEach(tabella => {
                    const dataVisuale = formatTableLabel(tabella);
                    const item = document.createElement('div');
                    item.className = 'elaborazione-item';
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <a href="/api/monter/booster/dettaglio/${comuneCode}/${tabella}" class="text-decoration-none fw-semibold text-dark d-block">
                                <i class="bi bi-file-earmark-text me-2"></i>${dataVisuale}
                            </a>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <a href="/api/monter/booster/download/${comuneCode}/${tabella}" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-download"></i>
                            </a>
                            <button onclick="deleteElaborazione('${comuneCode}', '${tabella}')" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>`;
                    list.appendChild(item);
                });
            } catch (error) {
                console.error('Errore caricamento elaborazioni:', error);
            }
        }

        document.getElementById('toggleAllBtn').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.zto-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        });

        document.getElementById('elaboraForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const checkedZTO = document.querySelectorAll('.zto-checkbox:checked');
            if (checkedZTO.length === 0) {
                alert('Seleziona almeno una ZTO prima di procedere.');
                return;
            }

            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creazione tabella...';

            const formData = {
                code_comune: currentComune,
                zto: Array.from(checkedZTO).map(cb => cb.value),
                exclude: document.getElementById('exclude').checked
            };

            try {
                const response = await fetch('/api/monter/booster/elabora', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(formData)
                });
                const result = await response.json();

                if (result.error) {
                    showMessage(result.error, 'danger');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-gear me-2"></i>Avvia Elaborazione';
                    return;
                }

                showMessage('Tabella creata. Popolamento proprietari in corso...', 'info');
                await loadElaborazioni(currentComune);
                startJobPolling(result.table, btn, currentComune);

            } catch (error) {
                showMessage('Errore durante l\'elaborazione', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-gear me-2"></i>Avvia Elaborazione';
            }
        });

        function setJobRunningUI(table, processed, total) {
            const btn = document.getElementById('submitBtn');
            const perc = total > 0 ? Math.round((processed / total) * 100) : 0;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Proprietari: ${processed}/${total} (${perc}%)`;

            const banner = document.getElementById('jobBanner');
            banner.style.display = 'flex';
            document.getElementById('jobBannerTable').textContent = formatTableLabel(table);
            document.getElementById('jobBannerText').textContent = `Proprietari elaborati: ${processed} / ${total} (${perc}%)`;
            document.getElementById('jobBannerBar').style.width = perc + '%';
        }

        function clearJobUI() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-gear me-2"></i>Avvia Elaborazione';
            document.getElementById('jobBanner').style.display = 'none';
        }

        async function startJobPolling(table, btn, pollingComune) {
            const poll = async () => {
                // Se il comune è cambiato nel frattempo, abbandona il polling
                if (currentComune !== pollingComune) {
                    return;
                }
                try {
                    const res = await fetch(`/api/monter/booster/jobStatus?table=${table}&code_comune=${pollingComune}`);
                    const data = await res.json();

                    if (currentComune !== pollingComune) return;

                    if (data.status === 'running' || data.status === 'queued') {
                        setJobRunningUI(table, data.processed ?? 0, data.total ?? 0);
                        setTimeout(poll, 3000);
                    } else if (data.status === 'completed') {
                        clearJobUI();
                        showMessage(`Completato! ${data.processed} particelle elaborate.`, 'success');
                        await loadElaborazioni(currentComune);
                    } else if (data.status === 'error') {
                        clearJobUI();
                        showMessage(`Errore nel job: ${data.error}`, 'danger');
                    } else {
                        // unknown: cache scaduta e nessun job in coda → ferma il polling
                        clearJobUI();
                    }
                } catch (e) {
                    if (currentComune === pollingComune) {
                        setTimeout(poll, 5000);
                    }
                }
            };
            setTimeout(poll, 2000);
        }

        async function verificaErrori(tipo) {
            const btn = tipo === 'catasto' ? document.getElementById('btnErroriCatasto') : document.getElementById('btnErroriUrbanistica');
            const label = tipo === 'catasto' ? 'Catasto' : 'Urbanistica';
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Verifica...`;

            try {
                const res = await fetch(`/api/monter/booster/errori-${tipo}-count/${currentComune}`);
                const data = await res.json();
                const resultDiv = document.getElementById('erroriResult');
                resultDiv.style.display = 'block';

                if (data.error) {
                    resultDiv.innerHTML = `<div class="alert alert-danger mb-0 py-2"><i class="bi bi-x-circle me-2"></i>${data.error}</div>`;
                } else if (data.length === 0) {
                    resultDiv.innerHTML = `<div class="alert alert-success mb-0 py-2"><i class="bi bi-check-circle me-2"></i><strong>${label}:</strong> nessun errore trovato nelle geometrie.</div>`;
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger mb-0 py-2 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-exclamation-triangle me-2"></i><strong>${label}:</strong> trovati <strong>${data.length}</strong> poligoni con errori geometrici.</span>
                        <a href="/api/monter/booster/errori-${tipo}/${currentComune}" target="_blank" class="btn btn-sm btn-danger ms-3">
                            <i class="bi bi-download me-1"></i>Scarica CSV
                        </a>
                    </div>`;
                }
            } catch (e) {
                document.getElementById('erroriResult').innerHTML = `<div class="alert alert-danger mb-0 py-2">Errore durante la verifica.</div>`;
                document.getElementById('erroriResult').style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.innerHTML = tipo === 'catasto' ?
                    '<i class="bi bi-map me-1"></i>Verifica Catasto' :
                    '<i class="bi bi-layers me-1"></i>Verifica Urbanistica';
            }
        }

        async function deleteElaborazione(comuneCode, table) {
            if (!confirm('Confermi l\'eliminazione di questa elaborazione?')) return;
            try {
                const response = await fetch(`/api/monter/booster/elimina/${comuneCode}/${table}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const result = await response.json();
                if (result.success) {
                    showMessage('Elaborazione eliminata con successo', 'success');
                    await loadElaborazioni(comuneCode);
                } else {
                    showMessage(result.error || 'Errore durante l\'eliminazione', 'danger');
                }
            } catch (error) {
                showMessage('Errore durante l\'eliminazione', 'danger');
            }
        }

        function showMessage(message, type) {
            const container = document.getElementById('messages');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            container.appendChild(alert);
            setTimeout(() => alert.remove(), 6000);
        }
    </script>
</body>

</html>
