<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleziona Colonne – Excel → TXT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color:   #3498db;
            --color-note:     #d69e2e;
            --color-data:     #3182ce;
            --sidebar-width:  280px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 0;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
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

        .sidebar-title   { font-size: 1.35rem; font-weight: 700; margin-bottom: .4rem; }
        .sidebar-subtitle { font-size: .82rem; opacity: .75; }

        .sidebar-menu { padding: 0; margin: 0; list-style: none; }
        .menu-item    { border-bottom: 1px solid rgba(255,255,255,0.1); }

        .menu-item .menu-label {
            display: block;
            padding: 1.2rem 1.5rem;
            color: white;
            font-weight: 500;
            font-size: .88rem;
            letter-spacing: .03em;
        }

        .menu-item.active {
            background: rgba(52,152,219,0.3);
            border-left: 4px solid var(--accent-color);
        }

        .menu-item.active .menu-label { font-weight: 700; }
        .menu-item.done   .menu-label { opacity: .65; }
        .menu-item.disabled { opacity: .4; pointer-events: none; }

        /* ── Main ── */
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

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .card-header {
            background: white !important;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }

        .card-body { padding: 1.75rem; }

        /* Selectors */
        label.form-label {
            font-weight: 600; color: #4a5568;
            font-size: .82rem; text-transform: uppercase; letter-spacing: .04em;
        }

        .sel-note-wrap select:focus { border-color: var(--color-note) !important; box-shadow: 0 0 0 3px rgba(214,158,46,.2) !important; }
        .sel-data-wrap select:focus { border-color: var(--color-data) !important; box-shadow: 0 0 0 3px rgba(49,130,206,.2) !important; }

        .form-select {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: .9rem;
            padding: .55rem .9rem;
            color: #2d3748;
            background: #f7fafc;
            transition: border-color .2s;
        }

        /* Legenda pills */
        .legend-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: .8rem;
            font-weight: 600;
        }

        .pill-note { background: rgba(214,158,46,.15); color: #92400e; border: 1px solid rgba(214,158,46,.4); }
        .pill-data { background: rgba(49,130,206,.13); color: #1e3a5f;  border: 1px solid rgba(49,130,206,.4); }

        .dot { width: 9px; height: 9px; border-radius: 3px; flex-shrink: 0; }
        .dot-note { background: var(--color-note); }
        .dot-data { background: var(--color-data); }

        /* Tabella anteprima */
        .table-wrap { overflow-x: auto; border-radius: 8px; border: 1px solid #e9ecef; }

        table { width: 100%; border-collapse: collapse; font-size: .8rem; }

        thead tr { background: var(--primary-color); }

        th {
            padding: 10px 13px;
            color: #fff;
            text-align: left;
            white-space: nowrap;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            transition: background .15s;
        }

        th:hover { background: var(--secondary-color); }
        th.th-note { background: var(--color-note) !important; }
        th.th-data { background: var(--color-data) !important; }

        .col-idx {
            display: inline-block;
            background: rgba(255,255,255,.18);
            border-radius: 3px;
            padding: 1px 5px;
            font-size: .68rem;
            margin-right: 4px;
            font-family: monospace;
        }

        .badge-col {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 4px;
            font-size: .68rem;
            font-weight: 700;
            margin-left: 5px;
            vertical-align: middle;
        }

        .badge-note { background: var(--color-note); color: #fff; }
        .badge-data { background: var(--color-data); color: #fff; }

        tbody tr:nth-child(even) { background: #f7fafc; }
        tbody tr:hover { background: #ebf8ff; }

        td {
            padding: 8px 13px;
            color: #4a5568;
            border-bottom: 1px solid #e9ecef;
            white-space: nowrap;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        td.td-note { background: rgba(214,158,46,.1); color: #744210; font-weight: 600; }
        td.td-data { background: rgba(49,130,206,.1);  color: #1a365d; font-weight: 600; }

        /* Bottoni */
        .btn-professional {
            padding: .72rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* Info click hint */
        .click-hint {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: .75rem 1rem;
            border-radius: 0 8px 8px 0;
            font-size: .82rem;
        }
    </style>
</head>
<body>

<!-- ── Sidebar ── -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-title">
            <i class="bi bi-file-earmark-arrow-right me-2"></i>Excel → TXT
        </div>
        <div class="sidebar-subtitle">Convertitore notifiche fiscali</div>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-item done">
            <div class="menu-label"><i class="bi bi-check-circle me-2"></i>CARICA FILE</div>
        </li>
        <li class="menu-item active">
            <div class="menu-label"><i class="bi bi-table me-2"></i>ANTEPRIMA COLONNE</div>
        </li>
        <li class="menu-item disabled">
            <div class="menu-label"><i class="bi bi-download me-2"></i>GENERA TXT</div>
        </li>
    </ul>
</div>

<!-- ── Contenuto principale ── -->
<div class="main-content">
    <div class="page-header">
        <h1 class="mb-1">Seleziona le Colonne</h1>
        <p class="text-muted mb-0">
            Clicca su un'intestazione per selezionarla oppure usa i menu a tendina.
            Visualizzate le prime 10 righe del file.
        </p>
    </div>

    <form action="{{ route('excel-txt.generate') }}" method="POST" id="mainForm">
        @csrf

        <!-- Selezione colonne -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-ui-checks me-2"></i>Mappa le colonne
            </div>
            <div class="card-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-6 sel-note-wrap">
                        <label class="form-label" style="color: #92400e;">
                            <i class="bi bi-card-text me-1"></i>Colonna NOTE (numero atto)
                        </label>
                        <select name="col_note" id="sel_note" class="form-select" required>
                            @foreach($headers as $i => $h)
                                <option value="{{ $i }}" {{ $i == 14 ? 'selected' : '' }}>
                                    [{{ $i }}] {{ $h ?? '(vuota)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 sel-data-wrap">
                        <label class="form-label" style="color: #1e3a5f;">
                            <i class="bi bi-calendar-date me-1"></i>Colonna DATA notifica
                        </label>
                        <select name="col_data" id="sel_data" class="form-select" required>
                            @foreach($headers as $i => $h)
                                <option value="{{ $i }}" {{ $i == 9 ? 'selected' : '' }}>
                                    [{{ $i }}] {{ $h ?? '(vuota)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="click-hint">
                    <i class="bi bi-hand-index me-2"></i>
                    <strong>Suggerimento:</strong> puoi cliccare direttamente sull'intestazione di una colonna nella tabella per selezionarla rapidamente.
                    Il primo click la imposta come <strong>NOTE</strong>, il secondo come <strong>DATA</strong>.
                </div>
            </div>
        </div>

        <!-- Anteprima tabella -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-table me-2"></i>Anteprima dati &mdash; prime 10 righe</span>
                <div class="d-flex gap-2">
                    <span class="legend-pill pill-note"><span class="dot dot-note"></span>NOTE</span>
                    <span class="legend-pill pill-data"><span class="dot dot-data"></span>DATA</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-wrap">
                    <table id="previewTable">
                        <thead>
                            <tr>
                                @foreach($headers as $i => $h)
                                    <th data-col="{{ $i }}" onclick="clickHeader({{ $i }})">
                                        <span class="col-idx">{{ $i }}</span>{{ $h ?? '—' }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($preview as $row)
                                <tr>
                                    @foreach($row as $i => $cell)
                                        <td data-col="{{ $i }}">
                                            {{ is_object($cell) ? $cell->format('d/m/Y') : ($cell ?? '—') }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Azioni -->
        <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('excel-txt.index') }}" class="btn btn-outline-secondary btn-professional">
                <i class="bi bi-arrow-left-circle me-2"></i>Ricomincia
            </a>
            <button type="submit" class="btn btn-success btn-professional">
                <i class="bi bi-file-earmark-arrow-down me-2"></i>Genera TXT
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const selNote = document.getElementById('sel_note');
    const selData = document.getElementById('sel_data');

    // Aggiorna evidenziazione su tutta la tabella
    function highlightCols() {
        const noteCol = parseInt(selNote.value);
        const dataCol = parseInt(selData.value);

        // Rimuovi classi e badge
        document.querySelectorAll('th, td').forEach(el => {
            el.classList.remove('th-note', 'th-data', 'td-note', 'td-data');
        });
        document.querySelectorAll('.badge-note, .badge-data').forEach(b => b.remove());

        // Applica
        document.querySelectorAll('[data-col]').forEach(el => {
            const c = parseInt(el.dataset.col);
            if (c === noteCol) {
                el.classList.add(el.tagName === 'TH' ? 'th-note' : 'td-note');
                if (el.tagName === 'TH') {
                    el.insertAdjacentHTML('beforeend', '<span class="badge-col badge-note">NOTE</span>');
                }
            }
            if (c === dataCol) {
                el.classList.add(el.tagName === 'TH' ? 'th-data' : 'td-data');
                if (el.tagName === 'TH') {
                    el.insertAdjacentHTML('beforeend', '<span class="badge-col badge-data">DATA</span>');
                }
            }
        });
    }

    // Click intestazione: cicla nota → data → (nulla)
    let lastClicked = null;
    function clickHeader(colIndex) {
        const noteCol = parseInt(selNote.value);
        const dataCol = parseInt(selData.value);

        if (colIndex !== noteCol && colIndex !== dataCol) {
            // Colonna nuova → assegna a NOTE
            selNote.value = colIndex;
        } else if (colIndex === noteCol) {
            // Era NOTE → diventa DATA
            selData.value = colIndex;
            // Sposta NOTE a una colonna libera (la prima diversa da DATA)
            const allCols = Array.from(document.querySelectorAll('th')).map(t => parseInt(t.dataset.col));
            const free = allCols.find(c => c !== colIndex);
            if (free !== undefined) selNote.value = free;
        }
        // Se era già DATA → non fare nulla

        highlightCols();
    }

    selNote.addEventListener('change', highlightCols);
    selData.addEventListener('change', highlightCols);

    // Inizializza
    highlightCols();
</script>
</body>
</html>