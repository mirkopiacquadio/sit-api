<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convertitore Excel → TXT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --sidebar-width: 280px;
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

        .sidebar-title   { font-size: 1.35rem; font-weight: 700; margin-bottom: 0.4rem; }
        .sidebar-subtitle { font-size: 0.82rem; opacity: 0.75; }

        .sidebar-menu { padding: 0; margin: 0; list-style: none; }

        .menu-item { border-bottom: 1px solid rgba(255,255,255,0.1); }

        .menu-item .menu-label {
            display: block;
            padding: 1.2rem 1.5rem;
            color: white;
            font-weight: 500;
            font-size: 0.88rem;
            letter-spacing: 0.03em;
        }

        .menu-item.active {
            background: rgba(52,152,219,0.3);
            border-left: 4px solid var(--accent-color);
        }

        .menu-item.active .menu-label { font-weight: 700; }

        .menu-item.disabled { opacity: 0.4; pointer-events: none; }

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

        /* Drop zone */
        .drop-zone {
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            padding: 2.5rem;
            text-align: center;
            cursor: pointer;
            background: #f7fafc;
            transition: border-color .2s, background .2s;
            position: relative;
        }

        .drop-zone:hover, .drop-zone.dragover {
            border-color: var(--accent-color);
            background: #ebf8ff;
        }

        .drop-zone input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
            width: 100%; height: 100%;
        }

        .drop-zone-icon { font-size: 2.5rem; color: #a0aec0; margin-bottom: .75rem; }
        .drop-zone-text { color: #718096; font-size: .9rem; }
        .drop-zone-text strong { color: var(--accent-color); }
        .file-name { margin-top: 10px; font-size: .85rem; color: #2d3748; font-weight: 600; }

        /* Inputs */
        label.form-label {
            font-weight: 600; color: #4a5568;
            font-size: .82rem; text-transform: uppercase; letter-spacing: .04em;
        }

        .form-control {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: .95rem;
            padding: .55rem .9rem;
            color: #2d3748;
            background: #f7fafc;
            transition: border-color .2s;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(52,152,219,.15);
            background: #fff;
        }

        .btn-professional {
            padding: .75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* Info box */
        .info-box {
            background: #ebf8ff;
            border-left: 4px solid var(--accent-color);
            border-radius: 0 8px 8px 0;
            padding: 1rem 1.25rem;
            font-size: .83rem;
            color: #2c5282;
        }

        .info-box code {
            background: rgba(49,130,206,.12);
            padding: 1px 5px;
            border-radius: 4px;
            font-size: .8rem;
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
        <li class="menu-item active">
            <div class="menu-label"><i class="bi bi-upload me-2"></i>CARICA FILE</div>
        </li>
        <li class="menu-item disabled">
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
        <h1 class="mb-1">Conversione File Notifiche</h1>
        <p class="text-muted mb-0">Carica il file Excel e imposta i parametri per generare il file TXT strutturato</p>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="bi bi-exclamation-circle me-2"></i>
            @foreach($errors->all() as $e){{ $e }}<br>@endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Form -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-sliders me-2"></i>Parametri di conversione
                </div>
                <div class="card-body">
                    <form action="{{ route('excel-txt.preview') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="row mb-4">
                            <div class="col-sm-4">
                                <label class="form-label">Tipo</label>
                                <input type="text" name="tipo" class="form-control"
                                       value="{{ old('tipo', 'A') }}" maxlength="5"
                                       placeholder="es. A" required>
                            </div>
                            <div class="col-sm-8">
                                <label class="form-label">Anno</label>
                                <input type="number" name="anno" class="form-control"
                                       value="{{ old('anno') }}" min="2000" max="2099"
                                       placeholder="es. 2015" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">File Excel (.xlsx)</label>
                            <div class="drop-zone" id="dropZone">
                                <div class="drop-zone-icon">
                                    <i class="bi bi-file-earmark-spreadsheet"></i>
                                </div>
                                <div class="drop-zone-text">
                                    Trascina qui il file o <strong>clicca per sfogliare</strong>
                                </div>
                                <div class="file-name" id="fileName"></div>
                                <input type="file" name="excel_file" id="fileInput" accept=".xlsx,.xls" required>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-professional">
                                <i class="bi bi-arrow-right-circle me-2"></i>Continua
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Guida -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>Formato di output
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Ogni riga del TXT generato avrà la struttura:</p>
                    <div class="info-box mb-4">
                        <code>TIPO ; ANNO ; NUMERO ; DATA</code><br>
                        <code style="color:#2d6a4f;">A;2015;1016;20240307</code>
                    </div>

                    <p class="text-muted fw-semibold mb-2"
                       style="font-size:.8rem; text-transform:uppercase; letter-spacing:.04em;">
                        Pattern NOTE riconosciuti automaticamente
                    </p>

                    <table class="table table-sm table-bordered small">
                        <thead class="table-light">
                            <tr>
                                <th>Testo nella colonna NOTE</th>
                                <th class="text-center" style="width:70px">Numero</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-monospace" style="font-size:.72rem">
                                    ACC. TASI 18 N. <strong>156</strong> CF. …
                                </td>
                                <td class="text-center fw-bold">156</td>
                            </tr>
                            <tr>
                                <td class="font-monospace" style="font-size:.72rem">
                                    ACC IMU N. <strong>825</strong> CF. …
                                </td>
                                <td class="text-center fw-bold">825</td>
                            </tr>
                            <tr>
                                <td class="font-monospace" style="font-size:.72rem">
                                    IMU 2020 N. <strong>1316</strong>-CF-CU
                                </td>
                                <td class="text-center fw-bold">1316</td>
                            </tr>
                            <tr>
                                <td class="font-monospace" style="font-size:.72rem">
                                    IMUSUP 20 <strong>1789</strong>
                                </td>
                                <td class="text-center fw-bold">1789</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const input    = document.getElementById('fileInput');
    const fileName = document.getElementById('fileName');
    const dropZone = document.getElementById('dropZone');

    input.addEventListener('change', () => {
        fileName.textContent = input.files[0]?.name || '';
    });

    dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        input.files = e.dataTransfer.files;
        fileName.textContent = input.files[0]?.name || '';
    });
</script>
</body>
</html>