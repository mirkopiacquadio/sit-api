<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio Elaborazione - Booster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-wrapper {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .badge-custom {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>📊 Dettaglio Elaborazione</h2>
                        @php
                            $raw = str_replace('aree_edificabili_finali_', '', $table);
                            $parts = explode('_', $raw);
                            $dataVisuale = count($parts) >= 5
                                ? "{$parts[0]}/{$parts[1]}/{$parts[2]} ore {$parts[3]}:{$parts[4]}"
                                : "{$parts[0]}/{$parts[1]}/{$parts[2]}";
                        @endphp
                        <p class="text-muted mb-0">Tabella: <strong>{{ $dataVisuale }}</strong></p>
                    </div>
                    <div class="btn-group">
                        <a href="{{ route('booster.download', ['code_comune' => $code_comune, 'table' => $table]) }}" 
                           class="btn btn-success">
                            📥 Scarica CSV
                        </a>
                        <a href="{{ route('booster.zto') }}" 
                           class="btn btn-secondary"
                           onclick="event.preventDefault(); document.getElementById('back-form').submit();">
                            ← Torna alle ZTO
                        </a>
                        <form id="back-form" action="{{ route('booster.zto') }}" method="POST" style="display: none;">
                            @csrf
                            <input type="hidden" name="code_comune" value="{{ $code_comune }}">
                        </form>
                    </div>
                </div>
            </div>
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

        <!-- Tabella dati -->
        <div class="table-wrapper">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>LAYER</th>
                            <th>ZTO</th>
                            <th>FOGLIO</th>
                            <th>PARTICELLA</th>
                            <th>TIPO CATASTO</th>
                            <th>STATO</th>
                            <th class="text-end">SUPERFICIE (m²)</th>
                            <th>PROPRIETARI / SUB</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php $subData = json_decode($row->sub_data ?? '[]', true) ?: []; @endphp
                            <tr>
                                <td>{{ $row->LAYER }}</td>
                                <td><span class="badge bg-info badge-custom">{{ $row->STRING }}</span></td>
                                <td><strong>{{ $row->FOGLIO }}</strong></td>
                                <td><strong>{{ $row->PARTICELLA }}</strong></td>
                                <td><span class="badge bg-secondary badge-custom">{{ $row->catasto_tipo ?? '—' }}</span></td>
                                <td>
                                    @if($row->STATO == 'LIBERA')
                                        <span class="badge bg-success badge-custom">{{ $row->STATO }}</span>
                                    @elseif($row->STATO == 'EDIFICATA')
                                        <span class="badge bg-warning text-dark badge-custom">{{ $row->STATO }}</span>
                                    @else
                                        <span class="badge bg-secondary badge-custom">{{ $row->STATO }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($row->aisect, 2, ',', '.') }}</td>
                                <td style="max-width:380px;">
                                    {{-- Proprietari particella principale --}}
                                    @if($row->proprietario)
                                        <div class="mb-1"><small>{{ $row->proprietario }}</small></div>
                                    @else
                                        <div class="mb-1 text-muted fst-italic"><small>Non disponibile</small></div>
                                    @endif
                                    {{-- Sub (fabbricati) --}}
                                    @foreach($subData as $sub)
                                        <div class="mt-1 ps-2 border-start border-2 border-primary">
                                            <span class="badge bg-primary" style="font-size:0.7rem;">
                                                Sub {{ $sub['sub'] }}
                                                @if(!empty($sub['catqua'])) · {{ $sub['catqua'] }}@endif
                                            </span>
                                            <small class="d-block text-muted mt-1">
                                                {{ $sub['proprietario'] ?: 'Proprietario non disponibile' }}
                                            </small>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <em>Nessun dato disponibile</em>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($rows->hasPages())
            <div class="p-3 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        Visualizzati {{ $rows->firstItem() }} - {{ $rows->lastItem() }} di {{ $rows->total() }} righe
                    </div>
                    <div>
                        {{ $rows->appends(['code_comune' => $code_comune])->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>