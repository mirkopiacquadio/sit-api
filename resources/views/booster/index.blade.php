<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booster Urbanistica - Accesso Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-dark: #1a1a2e;
            --primary-light: #16213e;
            --accent-blue: #0f4c75;
            --accent-cyan: #3282b8;
            --text-light: #bbe1fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-light) 50%, var(--accent-blue) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(50, 130, 184, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            top: -250px;
            right: -250px;
            animation: float 20s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(15, 76, 117, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -200px;
            left: -200px;
            animation: float 15s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            padding: 20px;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeInDown 0.8s ease;
        }

        .brand-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px rgba(50, 130, 184, 0.3);
        }

        .brand-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .brand-title {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            color: var(--text-light);
            font-size: 0.95rem;
            font-weight: 300;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: fadeInUp 0.8s ease;
        }

        .card-body {
            padding: 3rem 2.5rem;
        }

        .form-label {
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
        }

        .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-select:focus {
            border-color: var(--accent-cyan);
            background-color: white;
            box-shadow: 0 0 0 4px rgba(50, 130, 184, 0.1);
            outline: none;
        }

        .form-select option {
            padding: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(50, 130, 184, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(50, 130, 184, 0.4);
            background: linear-gradient(135deg, #3a93c7, #0d3f5f);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            animation: slideInDown 0.5s ease;
            backdrop-filter: blur(10px);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .footer-text {
            text-align: center;
            color: var(--text-light);
            font-size: 0.85rem;
            margin-top: 2rem;
            animation: fadeIn 1s ease 0.5s both;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .card-body {
                padding: 2rem 1.5rem;
            }

            .brand-title {
                font-size: 1.5rem;
            }

            .brand-icon {
                width: 60px;
                height: 60px;
            }

            .brand-icon i {
                font-size: 2rem;
            }
        }

        /* Loading animation */
        .btn-primary.loading {
            pointer-events: none;
            position: relative;
            color: transparent;
        }

        .btn-primary.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Brand Header -->
        <div class="brand-header">
            <div class="brand-icon">
                <i class="bi bi-building"></i>
            </div>
            <h1 class="brand-title">Booster Urbanistica</h1>
            <p class="brand-subtitle">Sistema professionale di elaborazione aree edificabili</p>
        </div>

        <!-- Alert Messages -->
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Errore:</strong> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Login Card -->
        <div class="card">
            <div class="card-body">
                <form action="{{ route('booster.zto') }}" method="POST" id="access-form">
                    @csrf
                    <div class="mb-4">
                        <label for="code_comune" class="form-label">
                            <i class="bi bi-geo-alt me-2"></i>Seleziona Comune
                        </label>
                        <select name="code_comune" 
                                id="code_comune" 
                                class="form-select form-select-lg" 
                                required>
                            <option value="">Scegli il comune da gestire</option>
                            @foreach($comuni as $code => $nome)
                                <option value="{{ $code }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" id="submit-btn">
                            Accedi al Sistema
                            <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-text">
            <i class="bi bi-shield-check me-2"></i>
            Sistema sicuro e professionale per la gestione urbanistica
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('access-form').addEventListener('submit', function(e) {
            const select = document.getElementById('code_comune');
            const btn = document.getElementById('submit-btn');
            
            if (!select.value) {
                e.preventDefault();
                alert('Seleziona un comune prima di procedere.');
                return;
            }
            
            // Add loading state
            btn.classList.add('loading');
            btn.disabled = true;
        });

        // Auto-focus on select
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('code_comune').focus();
        });
    </script>
</body>
</html>