<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booster Urbanistica - Selezione Comune</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: none;
            border-radius: 15px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header text-center py-4">
                        <h2 class="mb-0">🏛️ Booster Urbanistica</h2>
                        <p class="mb-0 mt-2">Sistema di elaborazione aree edificabili</p>
                    </div>
                    <div class="card-body p-5">
                        <form action="{{ route('booster.zto') }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label for="code_comune" class="form-label fw-bold">Seleziona Comune</label>
                                <select name="code_comune" id="code_comune" class="form-select form-select-lg select-2" required>
                                    <option value="">-- Scegli un comune --</option>
                                    @foreach($comuni as $code => $nome)
                                        <option value="{{ $code }}">{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Avanti →
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>