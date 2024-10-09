<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificato di Destinazione Urbanistica</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-section {
            border-bottom: 1px solid #000;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .form-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-subtitle {
            margin-bottom: 10px;
            font-weight: bold;
        }

        .spacer {
            margin-top: 20px;
        }

        .table th,
        .table td {
            text-align: center;
        }

        .required-text {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="container mt-5">
        <!-- Form principale -->
        <form action="/submit-dati" method="POST" enctype="multipart/form-data">
            @csrf
            <h2 class="form-title">ISTANZA TELEMATICO DI RILASCIO<br>CERTIFICATO DI DESTINAZIONE URBANISTICA</h2>
            <p class="text-center"><small>(art. 30, comma 3 del D.P.R. n. 380/2001 s.m.i.)</small></p>

            <!-- Richiedente Section -->
            <form>
                <div class="form-section">
                    <h5 class="form-subtitle">RICHIEDENTE</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="richiedenteNome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="richiedenteNome" placeholder="Inserisci nome">
                        </div>
                        <div class="col-md-6">
                            <label for="richiedenteCognome" class="form-label">Cognome</label>
                            <input type="text" class="form-control" id="richiedenteCognome" placeholder="Inserisci cognome">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="richiedenteCF" class="form-label">C.F. (Codice Fiscale)</label>
                            <input type="text" class="form-control" id="richiedenteCF" placeholder="Inserisci codice fiscale">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="richiedenteVia" class="form-label">Via</label>
                            <input type="text" class="form-control" id="richiedenteVia" placeholder="Inserisci via">
                        </div>
                        <div class="col-md-2">
                            <label for="richiedenteNumero" class="form-label">N.</label>
                            <input type="text" class="form-control" id="richiedenteNumero" placeholder="Numero civico">
                        </div>
                        <div class="col-md-2">
                            <label for="richiedenteCitta" class="form-label">Città</label>
                            <input type="text" class="form-control" id="richiedenteCitta" placeholder="Inserisci città">
                        </div>
                        <div class="col-md-2">
                            <label for="richiedenteProvincia" class="form-label">Prov</label>
                            <input type="text" class="form-control" id="richiedenteProvincia" placeholder="Prov.">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="richiedenteEmail" class="form-label">Mail</label>
                            <input type="email" class="form-control" id="richiedenteEmail" placeholder="Inserisci email">
                        </div>
                        <div class="col-md-6">
                            <label for="richiedentePEC" class="form-label">PEC</label>
                            <input type="text" class="form-control" id="richiedentePEC" placeholder="Inserisci PEC">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="flexSwitchCheckDefault" value="off" onchange="changeDelegato()">
                        <label class="form-check-label" for="flexSwitchCheckDefault"><b>Istanza presentata tramite delegato</b></label>
                    </div>
                </div>

                <!-- Delegato Section -->
                <div class="form-section" id="div_delegato" style="display: none">
                    <h5 class="form-subtitle">DELEGATO</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="delegatoNome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="delegatoNome" placeholder="Inserisci nome delegato">
                        </div>
                        <div class="col-md-6">
                            <label for="delegatoCognome" class="form-label">Cognome</label>
                            <input type="text" class="form-control" id="delegatoCognome" placeholder="Inserisci cognome delegato">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="delegatoCF" class="form-label">C.F. (Codice Fiscale)</label>
                            <input type="text" class="form-control" id="delegatoCF" placeholder="Inserisci codice fiscale">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="delegatoVia" class="form-label">Via</label>
                            <input type="text" class="form-control" id="delegatoVia" placeholder="Inserisci via">
                        </div>
                        <div class="col-md-2">
                            <label for="delegatoNumero" class="form-label">N.</label>
                            <input type="text" class="form-control" id="delegatoNumero" placeholder="Numero civico">
                        </div>
                        <div class="col-md-2">
                            <label for="delegatoCitta" class="form-label">Città</label>
                            <input type="text" class="form-control" id="delegatoCitta" placeholder="Inserisci città">
                        </div>
                        <div class="col-md-2">
                            <label for="delegatoProvincia" class="form-label">Prov</label>
                            <input type="text" class="form-control" id="delegatoProvincia" placeholder="Prov.">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="delegatoEmail" class="form-label">Mail</label>
                            <input type="email" class="form-control" id="delegatoEmail" placeholder="Inserisci email">
                        </div>
                        <div class="col-md-6">
                            <label for="delegatoPEC" class="form-label">PEC</label>
                            <input type="text" class="form-control" id="delegatoPEC" placeholder="Inserisci PEC">
                        </div>
                    </div>

                </div>
                <div class="form-section">
                    <h5 class="form-subtitle">ESTREMI CATASTALI RICHIESTI</h5>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="foglio" class="form-label">Foglio</label>
                            <input type="number" class="form-control" id="foglio" placeholder="Inserisci foglio" required>
                        </div>
                        <div class="col-md-4">
                            <label for="particella" class="form-label">Particella</label>
                            <input type="number" class="form-control" id="particella" placeholder="Inserisci particella" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-primary btn-inserisci" id="inserisciBtn">Inserisci</button>
                        </div>
                    </div>

                    <!-- Tabella dinamica -->
                    <div class="table-responsive">
                        <table class="table table-bordered" id="catastaliTable">
                            <thead>
                                <tr>
                                    <th>Foglio</th>
                                    <th>Particella</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dati dinamici -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Campo nascosto per inviare i dati catastali -->
                    <input type="hidden" id="datiCatastali" name="datiCatastali" value="">

                    <!-- Submit form -->
                    <button type="submit" class="btn btn-success">Genera Istanza</button>
                </div>
                <!-- Sezione allegati -->
                <div class="form-section">
                    <div class="alert alert-warning" role="alert">
                        Prima di caricare gli allegati genera l'istanza!
                    </div>
                    <h5 class="form-subtitle">ALLEGATI</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Modello</th>
                                    <th>Upload</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="required-text">Istanza</td>
                                    <td></td>
                                    <td><input type="file" name="istanza" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg" required></td>
                                </tr>
                                <tr>
                                    <td class="required-text">Marche da Bollo Annullate</td>
                                    <td><a href="{{ route('download.marca_bollo') }}"><b>Scarica e compila</b></a></td>
                                    <td>
                                        <input type="file" name="marche_bollo" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg" required>
                                    </td>
                                </tr>
                                                   
                                <tr>
                                    <td class="required-text">Ricevuta Versamento</td>
                                    <td></td>
                                    <td><input type="file" name="ricevuta_versamento" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg" required></td>
                                </tr>
                                <tr>
                                    <td>Visura Catastale</td>
                                    <td></td>
                                    <td><input type="file" name="visura_catastale" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg"></td>
                                </tr>
                                <tr>
                                    <td>Estratti di mappa catastale</td>
                                    <td></td>
                                    <td><input type="file" name="estratti_mappa" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg"></td>
                                </tr>
                                <tr>
                                    <td class="required-text">Delega</td>
                                    <td></td>
                                    <td><input type="file" name="delega" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg" required></td>
                                </tr>
                                <tr>
                                    <td>Altro</td>
                                    <td></td>
                                    <td><input type="file" name="altro" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg"></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="submit" class="btn btn-success">Invia richiesta</button>
                    </div>
                </div>

                <!-- Sezione per Foglio e Particella -->

            </form>

    </div>

    <!-- jQuery e Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script per gestire la tabella dinamica dei fogli e particelle -->
    <script>
        $(document).ready(function() {
            let catastali = []; // Array per contenere i dati di Foglio e Particella

            // Funzione per gestire l'inserimento nella tabella
            $('#inserisciBtn').click(function() {
                let foglio = $('#foglio').val();
                let particella = $('#particella').val();

                if (foglio && particella) {
                    // Aggiungi i dati al array catastali
                    catastali.push({
                        foglio: foglio,
                        particella: particella
                    });

                    // Aggiungi una nuova riga alla tabella
                    $('#catastaliTable tbody').append(`
                    <tr>
                        <td>${foglio}</td>
                        <td>${particella}</td>
                    </tr>
                `);

                    // Pulisci i campi
                    $('#foglio').val('');
                    $('#particella').val('');

                    // Aggiorna il campo nascosto
                    $('#datiCatastali').val(JSON.stringify(catastali));
                }
            });
        });

        function changeDelegato() {
            if ($('#flexSwitchCheckDefault').val() == 'off') {
                $('#flexSwitchCheckDefault').val('on');
                $('#div_delegato').show();
            } else {
                $('#flexSwitchCheckDefault').val('off');
                $('#div_delegato').hide();
            }
        }
    </script>

</body>

</html>
