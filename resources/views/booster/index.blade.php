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
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --sidebar-width: 380px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Sidebar fisso */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
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

        .sidebar-menu {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .menu-item {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .menu-item a,
        .menu-item .menu-label {
            display: block;
            padding: 1.25rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
            cursor: pointer;
        }

        .menu-item a:hover,
        .menu-item.active .menu-label {
            background: rgba(255,255,255,0.1);
        }

        .menu-item.active {
            background: rgba(52, 152, 219, 0.3);
            border-left: 4px solid var(--accent-color);
        }

        .menu-item.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Area principale */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .comune-selector {
            padding: 1.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            margin: 1rem 1.5rem;
        }

        .form-select-white {
            background: white;
            color: var(--primary-color);
            border: 2px solid rgba(255,255,255,0.3);
            font-weight: 500;
        }

        .form-select-white:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .alert-warning-custom {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: 1.5rem;
            border-radius: 8px;
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
            transition: all 0.2s ease;
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
            text-transform: uppercase;
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
    </style>
</head>
<body>
    <!-- Sidebar fisso -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">
                <i class="bi bi-building me-2"></i>Booster Monter
            </div>
            <div class="sidebar-subtitle">Sistema di analisi urbanistica</div>
        </div>

        <!-- Select Comune -->
        <div class="comune-selector">
            <label class="form-label text-white mb-2">
                <i class="bi bi-geo-alt me-2"></i>Seleziona Comune
            </label>
            <select id="comuneSelect" class="form-select form-select-white">
                <option value="">-- Scegli un comune --</option>
                @foreach($comuni as $code => $nome)
                    <option value="{{ $code }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>

        <!-- Menu laterale -->
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

    <!-- Contenuto principale -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="mb-2">Elaborazione Zone Territoriali Omogenee</h1>
            <p class="text-muted mb-0">Sistema di analisi e gestione aree edificabili</p>
        </div>

        <!-- Alert di selezione comune -->
        <div id="comuneWarning" class="alert-warning-custom mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Attenzione:</strong> Seleziona un comune dal menu laterale per iniziare l'elaborazione.
        </div>

        <!-- Loading spinner -->
        <div id="loadingSpinner" class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Caricamento...</span>
            </div>
            <p class="mt-3 text-muted">Caricamento dati in corso...</p>
        </div>

        <!-- Contenuto principale (nascosto inizialmente) -->
        <div id="mainContent" style="display: none;">
            <!-- Messaggi -->
            <div id="messages"></div>

            <div class="row">
                <!-- Form Elaborazione -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <i class="bi bi-plus-circle me-2"></i>Nuova Elaborazione
                            <span id="pianoName" class="badge bg-info ms-2"></span>
                        </div>
                        <div class="card-body">
                            <form id="elaboraForm">
                                <input type="hidden" id="codeComune" name="code_comune">

                                <!-- Selezione ZTO -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <label class="form-label fw-semibold mb-0">
                                            <i class="bi bi-geo-alt me-2"></i>Seleziona Zone Territoriali
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleAllBtn">
                                            <i class="bi bi-check-all me-1"></i>Seleziona Tutto
                                        </button>
                                    </div>
                                    
                                    <div class="zto-container" id="ztoContainer">
                                        <!-- ZTO verranno caricate qui -->
                                    </div>
                                </div>

                                <hr class="my-4">

                                <!-- Opzione esclusione -->
                                <div class="mb-4" style="background: #fff3cd; border: 1px solid #ffc107; padding: 1rem; border-radius: 8px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="exclude" id="exclude">
                                        <label class="form-check-label fw-semibold" for="exclude" style="color: #856404;">
                                            <i class="bi bi-building me-2"></i>Escludi interamente particelle con edificio
                                        </label>
                                    </div>
                                </div>

                                <!-- Pulsante elabora -->
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary btn-professional">
                                        <i class="bi bi-gear me-2"></i>Avvia Elaborazione
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Elaborazioni esistenti -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <i class="bi bi-folder me-2"></i>Elaborazioni Archiviate
                        </div>
                        <div id="elaborazioniList">
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
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

        // Gestione selezione comune
        document.getElementById('comuneSelect').addEventListener('change', function() {
            const comuneCode = this.value;
            
            if (!comuneCode) {
                // Reset interfaccia
                document.getElementById('comuneWarning').style.display = 'block';
                document.getElementById('mainContent').style.display = 'none';
                currentComune = null;
                return;
            }

            currentComune = comuneCode;
            loadComuneData(comuneCode);
        });

        // Carica dati del comune
        async function loadComuneData(comuneCode) {
            // Nascondi warning, mostra spinner
            document.getElementById('comuneWarning').style.display = 'none';
            document.getElementById('mainContent').style.display = 'none';
            document.getElementById('loadingSpinner').classList.add('active');

            try {
                // Carica ZTO
                const response = await fetch('/api/test/booster/zto', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ code_comune: comuneCode })
                });

                const data = await response.json();

                if (data.error) {
                    showMessage(data.error, 'danger');
                    return;
                }

                // Popola ZTO
                populateZTO(data.data);
                document.getElementById('pianoName').textContent = data.piano_name;
                document.getElementById('codeComune').value = comuneCode;

                // Carica elaborazioni esistenti
                await loadElaborazioni(comuneCode);

                // Mostra contenuto
                document.getElementById('loadingSpinner').classList.remove('active');
                document.getElementById('mainContent').style.display = 'block';

            } catch (error) {
                console.error('Errore:', error);
                showMessage('Errore durante il caricamento dei dati', 'danger');
                document.getElementById('loadingSpinner').classList.remove('active');
            }
        }

        // Popola lista ZTO
        function populateZTO(ztoList) {
            const container = document.getElementById('ztoContainer');
            container.innerHTML = '<div class="row g-2"></div>';
            const row = container.querySelector('.row');

            ztoList.forEach((zto, index) => {
                const col = document.createElement('div');
                col.className = 'col-md-6';
                col.innerHTML = `
                    <div class="form-check">
                        <input class="form-check-input zto-checkbox" type="checkbox" 
                               name="zto[]" value="${zto.STRING}" id="zto_${index}">
                        <label class="form-check-label fw-semibold" for="zto_${index}">
                            ${zto.STRING}
                        </label>
                    </div>
                `;
                row.appendChild(col);
            });
        }

        // Carica elaborazioni esistenti
        async function loadElaborazioni(comuneCode) {
            try {
                const response = await fetch(`/api/test/booster/elaborazioni/${comuneCode}`);
                const elaborazioni = await response.json();

                const container = document.getElementById('elaborazioniList');

                if (!elaborazioni || elaborazioni.length === 0) {
                    container.innerHTML = `
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-3 mb-0">Nessuna elaborazione disponibile</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = '<div class="elaborazioni-list"></div>';
                const list = container.querySelector('.elaborazioni-list');

                elaborazioni.forEach(tabella => {
                    const dataVisuale = tabella.replace('aree_edificabili_finali_', '').replace(/_/g, '/');
                    
                    const item = document.createElement('div');
                    item.className = 'elaborazione-item';
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <a href="/api/test/booster/dettaglio/${comuneCode}/${tabella}" 
                                   class="text-decoration-none fw-semibold text-dark d-block">
                                    <i class="bi bi-file-earmark-text me-2"></i>${dataVisuale}
                                </a>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <a href="/api/test/booster/download/${comuneCode}/${tabella}" 
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-download"></i>
                            </a>
                            <button onclick="deleteElaborazione('${comuneCode}', '${tabella}')" 
                                    class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                    list.appendChild(item);
                });

            } catch (error) {
                console.error('Errore caricamento elaborazioni:', error);
            }
        }

        // Toggle selezione tutto
        document.getElementById('toggleAllBtn').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.zto-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        });

        // Submit form elaborazione
        document.getElementById('elaboraForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const checkedZTO = document.querySelectorAll('.zto-checkbox:checked');
            if (checkedZTO.length === 0) {
                alert('Seleziona almeno una ZTO prima di procedere.');
                return;
            }

            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Elaborazione in corso...';

            const formData = {
                code_comune: currentComune,
                zto: Array.from(checkedZTO).map(cb => cb.value),
                exclude: document.getElementById('exclude').checked
            };

            try {
                const response = await fetch('/api/test/booster/elabora', {
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
                } else {
                    showMessage('Elaborazione completata con successo!', 'success');
                    await loadElaborazioni(currentComune);
                }

            } catch (error) {
                showMessage('Errore durante l\'elaborazione', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-gear me-2"></i>Avvia Elaborazione';
            }
        });

        // Elimina elaborazione
        async function deleteElaborazione(comuneCode, table) {
            if (!confirm('Confermi l\'eliminazione di questa elaborazione?')) {
                return;
            }

            try {
                const response = await fetch(`/api/test/booster/elimina/${comuneCode}/${table}`, {
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

        // Mostra messaggio
        function showMessage(message, type) {
            const container = document.getElementById('messages');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            container.appendChild(alert);

            setTimeout(() => alert.remove(), 5000);
        }
    </script>
</body>
</html>