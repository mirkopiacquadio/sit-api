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
        /* Riga marcata come "lavorato" (vince su striped/hover di Bootstrap) */
        tr.riga-lavorata > td { background-color: #d4edda !important; }
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

        @php
            // Intestazione di colonna ordinabile (prime 5 colonne).
            $sortTh = function ($label, $field) use ($sort, $dir, $code_comune, $table) {
                $nextDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
                $arrow   = $sort === $field ? ($dir === 'asc' ? '▲' : '▼') : '⇅';
                $url     = route('booster.dettaglio', [
                    'code_comune' => $code_comune,
                    'table'       => $table,
                    'sort'        => $field,
                    'dir'         => $nextDir,
                ]);
                return '<a href="' . $url . '" class="text-white text-decoration-none">'
                    . e($label)
                    . ' <span style="opacity:.6;font-size:.75em;">' . $arrow . '</span></a>';
            };
        @endphp

        <!-- Barra azioni selezione -->
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <span class="text-muted" id="sel-count">0 selezionate</span>
            <button type="button" class="btn btn-sm btn-success" onclick="bulkLavorato(1)">✔ Segna lavorate</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="bulkLavorato(0)">↺ Segna non lavorate</button>
            <button type="button" class="btn btn-sm btn-danger" onclick="eliminaSelezionate()">🗑 Elimina selezionate</button>
            <small class="text-muted ms-auto">La selezione agisce sulle righe della pagina corrente.</small>
        </div>

        <!-- Tabella dati -->
        <div class="table-wrapper">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:34px;text-align:center;"><input type="checkbox" onclick="toggleAll(this)" title="Seleziona pagina"></th>
                            <th style="text-align:center;white-space:nowrap;">LAVORATO</th>
                            <th style="white-space:nowrap;">{!! $sortTh('FOGLIO', 'FOGLIO') !!}</th>
                            <th style="white-space:nowrap;">{!! $sortTh('PARTICELLA', 'PARTICELLA') !!}</th>
                            <th style="white-space:nowrap;">{!! $sortTh('STATO', 'STATO') !!}</th>
                            <th style="white-space:nowrap;">{!! $sortTh('ZTO', 'STRING') !!}</th>
                            {{-- <th>LAYER</th> --}}
                            <th style="white-space:nowrap;">{!! $sortTh('TIPO CATASTO', 'catasto_tipo') !!}</th>
                            <th class="text-end">SUP. CATASTALE (m²)</th>
                            <th class="text-end">%</th>
                            <th class="text-end">SUP. IN ZTO (m²)</th>
                            <th>PROPRIETARI / SUB</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php $subData = json_decode($row->sub_data ?? '[]', true) ?: []; @endphp
                            <tr class="{{ $row->lavorato ? 'riga-lavorata' : '' }}" data-row-id="{{ $row->id }}">
                                <td style="text-align:center;">
                                    <input type="checkbox" class="row-check" value="{{ $row->id }}" onchange="updateSelCount()">
                                </td>
                                <td style="text-align:center;">
                                    <div class="form-check form-switch d-flex justify-content-center m-0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               {{ $row->lavorato ? 'checked' : '' }}
                                               onchange="toggleLavorato({{ $row->id }}, this.checked, this)">
                                    </div>
                                </td>
                                <td><strong>{{ $row->FOGLIO }}</strong></td>
                                <td><strong>{{ $row->PARTICELLA }}</strong></td>
                                <td>
                                    @if($row->STATO == 'LIBERA')
                                        <span class="badge bg-success badge-custom">{{ $row->STATO }}</span>
                                    @elseif($row->STATO == 'EDIFICATA')
                                        <span class="badge bg-warning text-dark badge-custom">{{ $row->STATO }}</span>
                                    @else
                                        <span class="badge bg-secondary badge-custom">{{ $row->STATO }}</span>
                                    @endif
                                </td>
                                {{-- <td>{{ $row->LAYER }}</td> --}}
                                <td><span class="badge bg-secondary badge-custom">{{ $row->catasto_tipo ?? '—' }}</span></td>
                                <td><span class="badge bg-info badge-custom">{{ $row->STRING }}</span></td>
                                <td class="text-end">{{ number_format($row->auiu, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row->perc, 2, ',', '.') }}%</td>
                                <td class="text-end">{{ number_format($row->aisect, 2, ',', '.') }}</td>
                                <td style="max-width:420px; font-size:0.82rem;">
                                    @php
                                        $subTerreni   = array_filter($subData, fn($s) => ($s['tipo'] ?? '') === 'Terreno');
                                        $subFabbricati = array_filter($subData, fn($s) => ($s['tipo'] ?? '') === 'Fabbricato');
                                    @endphp

                                    {{-- SEZIONE TERRENO --}}
                                    <div class="mb-2">
                                        <strong>Terreno</strong>
                                        @if($row->proprietario && $row->proprietario !== 'ERRORE')
                                            <ul class="mb-0 ps-3">
                                                @foreach(explode(' | ', $row->proprietario) as $prop)
                                                    <li>{{ trim($prop) }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <div class="text-muted fst-italic">Non disponibile</div>
                                        @endif
                                    </div>

                                    {{-- SEZIONE FABBRICATI --}}
                                    @if(count($subFabbricati) > 0)
                                        <div>
                                            <strong>Fabbricati</strong>
                                            @foreach($subFabbricati as $sub)
                                                <div class="mt-2 ps-2 border-start border-2 border-primary">
                                                    <span class="badge bg-primary" style="font-size:0.7rem;">
                                                        Sub {{ $sub['sub'] }} · {{ $sub['tipo'] }}
                                                        @if(!empty($sub['catqua'])) · {{ $sub['catqua'] }}@endif
                                                    </span>
                                                    @if(!empty($sub['proprietario']))
                                                        <ul class="mb-0 mt-1 ps-3" style="font-size:0.8rem;">
                                                            @foreach(explode(' | ', $sub['proprietario']) as $prop)
                                                                <li>{{ trim($prop) }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <small class="d-block text-muted mt-1 fst-italic">Non disponibile</small>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-5">
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
                        {{ $rows->appends(array_filter(['code_comune' => $code_comune, 'sort' => $sort, 'dir' => $dir]))->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BOOSTER_CODE  = @json($code_comune);
        const BOOSTER_TABLE = @json($table);

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.row-check:checked'))
                .map(c => parseInt(c.value, 10))
                .filter(n => !isNaN(n) && n > 0);
        }

        function updateSelCount() {
            document.getElementById('sel-count').textContent = getSelectedIds().length + ' selezionate';
        }

        function toggleAll(master) {
            document.querySelectorAll('.row-check').forEach(c => { c.checked = master.checked; });
            updateSelCount();
        }

        function postJson(url, payload) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json());
        }

        // Switch singolo: aggiorna stato lavorato di una riga (ottimistico).
        function toggleLavorato(id, checked, input) {
            const val = checked ? 1 : 0;
            postJson('/api/booster/lavorato', { code_comune: BOOSTER_CODE, table: BOOSTER_TABLE, ids: [id], lavorato: val })
                .then(res => {
                    if (res && res.ok) {
                        const tr = input.closest('tr');
                        if (tr) tr.classList.toggle('riga-lavorata', val === 1);
                    } else {
                        input.checked = !checked; // revert
                        alert('Errore: ' + ((res && res.error) || 'operazione fallita'));
                    }
                })
                .catch(() => { input.checked = !checked; alert('Errore di rete'); });
        }

        function bulkLavorato(val) {
            const ids = getSelectedIds();
            if (!ids.length) { alert('Nessuna riga selezionata'); return; }
            postJson('/api/booster/lavorato', { code_comune: BOOSTER_CODE, table: BOOSTER_TABLE, ids: ids, lavorato: val })
                .then(res => {
                    if (res && res.ok) location.reload();
                    else alert('Errore: ' + ((res && res.error) || 'operazione fallita'));
                })
                .catch(() => alert('Errore di rete'));
        }

        function eliminaSelezionate() {
            const ids = getSelectedIds();
            if (!ids.length) { alert('Nessuna riga selezionata'); return; }
            if (!confirm('Eliminare definitivamente ' + ids.length + ' riga/e? L\'operazione non è reversibile.')) return;
            postJson('/api/booster/eliminaRighe', { code_comune: BOOSTER_CODE, table: BOOSTER_TABLE, ids: ids })
                .then(res => {
                    if (res && res.ok) location.reload();
                    else alert('Errore: ' + ((res && res.error) || 'operazione fallita'));
                })
                .catch(() => alert('Errore di rete'));
        }
    </script>
</body>
</html>