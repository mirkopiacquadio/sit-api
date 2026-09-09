{{-- Input file con etichette in italiano: il browser mostrerebbe di default
     "Choose file" / "No file chosen" (testo nativo, non traducibile via HTML),
     quindi lo nascondiamo e mostriamo un pulsante + nome file gestiti da noi. --}}
<div class="input-group input-group-sm file-input-it flex-grow-1">
    <label class="btn btn-outline-secondary btn-scegli-file">
        Scegli file
        <input type="file" class="visually-hidden" data-import="{{ $nome }}" accept="{{ $accept ?? '.xlsx,.xls,.csv' }}">
    </label>
    <span class="form-control nome-file text-muted text-truncate" data-filename-for="{{ $nome }}">Nessun file selezionato</span>
</div>
