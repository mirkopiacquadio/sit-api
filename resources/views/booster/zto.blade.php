<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elaborazione ZTO - Booster Urbanistica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .info-banner {
            background: white;
            border-left: 4px solid var(--accent-color);
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: white;
            border-bottom: 2px solid #e9ecef;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .zto-container {
            max-height: 450px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            background: #fafbfc;
        }

        .zto-container::-webkit-scrollbar {
            width: 8px;
        }

        .zto-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .zto-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .zto-container::-webkit-scrollbar-thumb:hover {
            background: #555;
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
            transform: translateX(2px);
        }

        .form-check-input:checked {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .btn-professional {
            padding: 0.75rem 2rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-professional:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .elaborazioni-list {
            background: white;
        }

        .elaborazione-item {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }

        .elaborazione-item:last-child {
            border-bottom: none;
        }

        .elaborazione-item:hover {
            background: #f8f9fa;
        }

        .elaborazione-date {
            font-family: 'Courier New', monospace;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .action-buttons .btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .badge-count {
            background: var(--accent-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .checkbox-label {
            font-weight: 500;
            color: var(--primary-color);
            user-select: none;
        }

        .section-divider {
            border: none;
            border-top: 2px solid #e9ecef;
            margin: 2rem 0;
        }

        .exclude-option {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .exclude-option .form-check-label {
            color: #856404;
            font-weight: 600;
        }

        .alert-custom {
            border-left: 4px solid;
            border-radius: 4px;
        }

        .alert-danger.alert-custom {
            border-left-color: var(--danger-color);
            background-color: #f8d7da;
        }

        .alert-success.alert-custom {
            border-left-color: var(--success-color);
            background-color: #d4edda;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">Elaborazione Zone Territoriali Omogenee</h1>
                    <p class="mb-0 opacity-75">Sistema di analisi e gestione aree edificabili</p>
                </div>
                <a href="{{ route('booster.index') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left me-2"></i>Cambia Comune
                </a>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <!-- Info Banner -->
        <div class="info-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <strong>Piano Urbanistico:</strong> {{ $piano_name }}<br>
                    <small class="text-muted">Comune: {{ $code_comune }}</small>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge-count">{{ count($zto_list) }} ZTO disponibili</span>
                </div>
            </div>
        </div>

        <!-- Messaggi di sistema -->
        @if(session('error'))
            <div class="alert alert-danger alert-custom alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success alert-custom alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            <!-- Form Elaborazione -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-plus-circle me-2"></i>Nuova Elaborazione
                    </div>
                    <div class="card-body">
                        <form action="{{ route('booster.elaboraWeb') }}" method="POST" id="elabora-form">
                            @csrf
                            <input type="hidden" name="code_comune" value="{{ $code_comune }}">

                            <!-- Selezione ZTO -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label fw-semibold mb-0">
                                        <i class="bi bi-geo-alt me-2"></i>Seleziona Zone Territoriali
                                    </label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll()">
                                        <i class="bi bi-check-all me-1"></i>Seleziona Tutto
                                    </button>
                                </div>
                                
                                <div class="zto-container">
                                    <div class="row g-2">
                                        @foreach($zto_list as $index => $zto)
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input zto-checkbox" 
                                                           type="checkbox" 
                                                           name="zto[]" 
                                                           value="{{ $zto->STRING }}" 
                                                           id="zto_{{ $index }}">
                                                    <label class="form-check-label checkbox-label" for="zto_{{ $index }}">
                                                        {{ $zto->STRING }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <hr class="section-divider">

                            <!-- Opzione esclusione -->
                            <div class="exclude-option">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="exclude" id="exclude">
                                    <label class="form-check-label" for="exclude">
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
                    <div class="card-header">
                        <i class="bi bi-folder me-2"></i>Elaborazioni Archiviate
                    </div>
                    @if(empty($elaborazioni))
                        <div class="card-body text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-3 mb-0">Nessuna elaborazione disponibile</p>
                        </div>
                    @else
                        <div class="elaborazioni-list">
                            @foreach($elaborazioni as $tabella)
                                @php
                                    $dataVisuale = str_replace('aree_edificabili_finali_', '', $tabella);
                                    $dataVisuale = str_replace('_', '/', $dataVisuale);
                                @endphp
                                <div class="elaborazione-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="flex-grow-1">
                                            <a href="{{ route('booster.dettaglio', ['code_comune' => $code_comune, 'table' => $tabella]) }}" 
                                               class="text-decoration-none fw-semibold text-dark d-block">
                                                <i class="bi bi-file-earmark-text me-2"></i>{{ $dataVisuale }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="action-buttons d-flex gap-2 mt-2">
                                        <a href="{{ route('booster.download', ['code_comune' => $code_comune, 'table' => $tabella]) }}" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <form action="{{ route('booster.elimina', ['code_comune' => $code_comune, 'table' => $tabella]) }}" 
                                              method="POST" 
                                              onsubmit="return confirm('Confermi l\'eliminazione di questa elaborazione?');"
                                              class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll() {
            const checkboxes = document.querySelectorAll('.zto-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }

        document.getElementById('elabora-form').addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('.zto-checkbox:checked');
            if (checked.length === 0) {
                e.preventDefault();
                alert('Seleziona almeno una ZTO prima di procedere con l\'elaborazione.');
                return false;
            }
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Elaborazione in corso...';
        });
    </script>
</body>
</html>