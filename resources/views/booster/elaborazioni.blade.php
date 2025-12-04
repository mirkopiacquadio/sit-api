<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elenco Elaborazioni - Booster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>📁 Elenco Elaborazioni</h1>
                    <a href="{{ route('booster.zto') }}" 
                       class="btn btn-primary"
                       onclick="event.preventDefault(); document.getElementById('back-form').submit();">
                        ← Torna alle ZTO
                    </a>
                    <form id="back-form" action="{{ route('booster.zto') }}" method="POST" style="display: none;">
                        @csrf
                        <input type="hidden" name="code_comune" value="{{ $code_comune }}">
                    </form>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="card shadow">
                    <div class="card-body p-0">
                        @if(empty($elaborazioni))
                            <div class="p-5 text-center text-muted">
                                <h4>Nessuna elaborazione presente</h4>
                                <p>Crea una nuova elaborazione dalla sezione ZTO</p>
                            </div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($elaborazioni as $tabella)
                                    @php
                                        $dataVisuale = str_replace('aree_edificabili_finali_', '', $tabella);
                                        $dataVisuale = str_replace('_', '/', $dataVisuale);
                                    @endphp
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                        <div>
                                            <h5 class="mb-1">
                                                <a href="{{ route('booster.dettaglio', ['code_comune' => $code_comune, 'table' => $tabella]) }}" 
                                                   class="text-decoration-none">
                                                    📄 {{ $dataVisuale }}
                                                </a>
                                            </h5>
                                            <small class="text-muted">{{ $tabella }}</small>
                                        </div>
                                        <div class="btn-group">
                                            <a href="{{ route('booster.dettaglio', ['code_comune' => $code_comune, 'table' => $tabella]) }}" 
                                               class="btn btn-sm btn-outline-primary">
                                                👁️ Visualizza
                                            </a>
                                            <a href="{{ route('booster.download', ['code_comune' => $code_comune, 'table' => $tabella]) }}" 
                                               class="btn btn-sm btn-success">
                                                📥 CSV
                                            </a>
                                            <form action="{{ route('booster.elimina', ['code_comune' => $code_comune, 'table' => $tabella]) }}" 
                                                  method="POST" 
                                                  onsubmit="return confirm('Sei sicuro di voler eliminare questa elaborazione?');"
                                                  style="display: inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>