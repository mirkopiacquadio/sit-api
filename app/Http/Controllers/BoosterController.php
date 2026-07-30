<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BoosterController extends Controller
{
    private $elencoFg = array();
    private $infoComune = [];
    public $nomiDb = array(
        "9999" => 'morcone-webgis',
        "B946" => 'casavatore-webgis',
        "D230" => 'cusanomutri-webgis',
        "D469" => 'faicchio-webgis',
        "D784" => 'frasso_telesino-webgis',
        "I062" => 'sannicolamanfredi-webgis',
        "F717" => 'morcone-webgis',
        "G848" => 'pontelandolfo-webgis',
        "L185" => 'toccocaudio-webgis',
        "H967" => 'sanlorenzomaggiore-webgis',
        "G311" => 'pannarano-webgis',
        "C659" => 'chiusanosandomenico-webgis',
        "G991" => 'prata_sannita-webgis',
        "H313" => 'ripalimosani-webgis',
        "L254" => 'torrecuso-webgis',
        "F111" => 'melito-webgis',
        "D361" => 'dragoni-webgis',
        "C245" => 'castelpagano-webgis',
        "H894" => 'sangiorgiodelsannio-webgis',
        "H898" => 'sangiorgiolamolara-webgis',
        "F448" => 'montecalvoirpino-webgis',
        "L739" => 'venticano-webgis',
        "D756" => 'fragnetomonforte-webgis',
        "I197" => 'santagatadegoti-webgis',
        "F113" => 'melizzano-webgis',
        "C250" => 'castelpoto-webgis',
        "F113" => 'melizzano-webgis',
        "G386" => 'paupisi-webgis',
        "H087" => 'puglianello-webgis'
    );

    private $nomiPiani = [];
    private $pianiComuneBooster = [];

    private function setDB($code_comune)
    {
        $code_comune = strtoupper($code_comune);
        // Prima stabilisci la connessione al database info-generali
        DB::purge('info-generali'); // Pulisce la connessione precedente
        config(['database.connections.info-generali.database' => 'info-generali']);
        DB::reconnect('info-generali'); // Riconnette con il database info-generali

        // Eseguire la query per ottenere i dati dalla tabella nome_piani
        $q = "SELECT codice, descrizione FROM nome_piani;";
        $res = DB::connection('info-generali')->select($q); // Usa la connessione al database info-generali

        // Inizializzare l'array $nomiPiani con i risultati della query
        $this->nomiPiani = [];
        foreach ($res as $row) {
            $this->nomiPiani[$row->codice] = $row->descrizione; // Aggiungi la descrizione con il codice come chiave
        }

        // Eseguire la query per ottenere i dati dalla tabella ana_comuni
        $q = "SELECT * FROM ana_comuni where codice = '$code_comune';";
        $res = DB::connection('info-generali')->select($q); // Usa la connessione al database info-generali

        // Inizializzare l'array $nomiPiani con i risultati della query
        $this->infoComune = $res;

        // Eseguire la query per ottenere i dati dalla tabella ana_comuni
        $q_booster = "SELECT * FROM comune_piani_relazione where codice_comune = '$code_comune';";
        $resu = DB::connection('info-generali')->select($q_booster); // Usa la connessione al database info-generali

        // Inizializzare l'array $nomiPiani con i risultati della query
        $this->pianiComuneBooster = $resu;

        if (array_key_exists($code_comune, $this->nomiDb)) {
            // Ottieni il nome del database dal codice del comune
            $dbn = $this->nomiDb[$code_comune];

            // Configura la connessione al database del comune
            DB::purge('pgsql'); // Pulisce la connessione precedente
            config(['database.connections.pgsql.database' => $dbn]);
            DB::reconnect('pgsql'); // Riconnette con il nuovo nome di database

        } else {
            // Gestione dell'errore se il codice del comune non esiste nell'array nomiDb
            throw new \Exception("Codice comune non valido: $code_comune");
        }

        // Esegui la query per ottenere l'elenco dei fogli
        $q = "SELECT table_name as nm FROM information_schema.tables WHERE table_name LIKE '%utm' AND table_name LIKE '" . strtolower($code_comune) . "%' ORDER BY table_name;";
        $res = DB::select($q);

        // Inizializzare l'array $elencoFg con i risultati della query
        unset($this->elencoFg);
        foreach ($res as $row) {
            $this->elencoFg[] = substr($row->nm, 4);
        }
    }

    public function getProprietariAttuali($code_comune = null, $foglio = null, $particella = null, $sub = '', Request $request = null)
    {
        if ($request instanceof Request) {
            $code_comune = strtoupper($request->input('code_comune', $code_comune));
            $foglio      = $request->input('foglio', $foglio);
            $particella  = $request->input('particella', $particella);
            $sub         = $request->input('sub', $sub);
        }

        if (!$code_comune || !$foglio || !$particella) {
            return response()->json(['error' => 'Parametri mancanti'], 400);
        }

        $fakeRequest = new \Illuminate\Http\Request([
            'code_comune' => $code_comune,
            'foglio'      => $foglio,
            'particella'  => $particella,
            'sub'         => $sub,
        ]);

        $controller = new \App\Http\Controllers\CatastoImmobileController();

        $result = $controller->elencoMutazioniCatastoTerreni($fakeRequest, true);

        if (empty($result[0]) && empty($result[1])) {
            $result = $controller->elencoMutazioniCatastoFabbricati($fakeRequest, true);
        }

        if (empty($result[0]) || empty($result[1])) {
            return [];
        }

        return $this->estraiProprietariAttuali($result);
    }

    /**
     * Logica di estrazione proprietari attuali, separata e riusabile
     */
    private function estraiProprietariAttuali(array $result): array
    {
        $owners = [];

        // 1️⃣ Trova la mutazione attiva: quella senza data_efficacia1 (non ancora chiusa)
        $mutazioneAttiva = null;
        foreach ($result[0] as $dati) {
            if (empty($dati[1]['data_efficacia1'])) {
                $mutazioneAttiva = $dati;
                break;
            }
        }

        if (!$mutazioneAttiva) {
            return [];
        }

        // 2️⃣ Recupera la chiave K per trovare i proprietari collegati
        $k = $mutazioneAttiva[2][0]['k'] ?? null;
        if (!$k || !isset($result[1][$k])) {
            return [];
        }

        $mutazioniParticella = $result[1][$k];

        // 3️⃣ Il primo id_mutaz è quello della situazione più recente (array già ordinato per dataval DESC)
        $idMutazionePrincipale = null;
        foreach ($mutazioniParticella as $m) {
            if (!empty($m['id_mutaz'])) {
                $idMutazionePrincipale = $m['id_mutaz'];
                break;
            }
        }

        if (!$idMutazionePrincipale) {
            return [];
        }

        // 4️⃣ Filtra solo le mutazioni con quell'id (tutti i cointestatari dello stesso atto)
        $mutazioniAttive = array_filter(
            $mutazioniParticella,
            fn($m) => isset($m['id_mutaz']) && $m['id_mutaz'] === $idMutazionePrincipale
        );

        // 5️⃣ Estrai i proprietari escludendo quelli con "fino al" nel titolo (storici)
        foreach ($mutazioniAttive as $m) {
            if (!empty($m['prop'])) {
                foreach ($m['prop'] as $p) {
                    if (stripos($p['titolo'] ?? '', 'fino al') !== false) {
                        continue; // proprietario storico, skip
                    }
                    $owners[] = [
                        'foglio'      => $m['foglio']   ?? '',
                        'particella'  => $m['particella'] ?? '',
                        'sub'         => $m['sub']       ?? '',
                        'nome'        => $p['pers1']     ?? '',
                        'cf'          => $p['perscf']    ?? '',
                        'titolo'      => trim($p['titolo'] ?? ''),
                        'descrizione' => $m['desc']      ?? '',
                        'data_eff'    => $m['dataval']   ?? '',
                    ];
                }
            }
        }

        return $owners;
    }

    public function elaborazioni(Request $request)
    {
        $code_comune = strtoupper($request->get('code_comune'));

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
            return response()->json(['error' => 'Codice comune non valido'], 400);
        }

        $this->setDB($code_comune);

        $tables = DB::select("
            SELECT tablename 
            FROM pg_tables 
            WHERE tablename LIKE 'aree_edificabili_finali_%'
            ORDER BY tablename DESC
        ");

        return response()->json(array_map(fn($t) => $t->tablename, $tables));
    }

    public function downloadElaborazione(Request $request, $code_comune = null, $table = null)
    {
        $code_comune = strtoupper($code_comune ?? $request->get('code_comune'));
        $table = $table ?? $request->get('table');

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
            return response()->json(['error' => 'Codice comune non valido'], 400);
        }

        $this->setDB($code_comune);

        if (!preg_match('/^aree_edificabili_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2})?$/', $table)) {
            return response()->json(['error' => 'Nome tabella non valido'], 400);
        }

        try {
            $fileName = "{$table}.csv";
            $handle = fopen('php://temp', 'w+');

            // Stesso ordinamento della vista dettaglio (sort/dir passati dal link CSV).
            $orderBy = $this->boosterOrderBy(
                $this->sortMapAree(),
                $request->get('sort'),
                $request->get('dir'),
                '"FOGLIO", "PARTICELLA", "STRING"'
            );
            $rows = DB::select("SELECT * FROM {$table} ORDER BY {$orderBy}");

            if (count($rows) > 0) {
                // Costruisci headers: escludi geom e sub_data, aggiungi sub alla fine
                $firstRow = (array)$rows[0];
                unset($firstRow['geom'], $firstRow['sub_data']);
                $mainHeaders = array_keys($firstRow);
                $headers = array_merge($mainHeaders, ['sub']);

                fwrite($handle, implode(';', array_map(fn($h) => '"' . $h . '"', $headers)) . "\r\n");

                $writeCsvLine = function (array $values) use ($handle, $headers) {
                    // Colonne numeriche: virgola decimale, niente separatore migliaia.
                    // Col punto Excel in locale IT lo interpreta come separatore delle
                    // migliaia e lo elimina (881.45 -> 88145).
                    $numeriche = ['area_mq' => 2, 'auiu' => 3, 'aisect' => 3, 'perc' => 2];
                    $ordered = [];
                    foreach ($headers as $h) {
                        $v = $values[$h] ?? '';
                        if (isset($numeriche[$h]) && $v !== '' && $v !== null) {
                            $v = number_format((float) $v, $numeriche[$h], ',', '');
                        }
                        $ordered[] = '"' . str_replace('"', '""', $v) . '"';
                    }
                    fwrite($handle, implode(';', $ordered) . "\r\n");
                };

                foreach ($rows as $row) {
                    $r = (array)$row;
                    unset($r['geom']);
                    $subData = json_decode($r['sub_data'] ?? '[]', true) ?: [];
                    unset($r['sub_data']);
                    $r['sub'] = ''; // colonna sub vuota per riga principale

                    // Riga(he) principale — split proprietari multipli
                    $proprietario = $r['proprietario'] ?? '';
                    if (!empty($proprietario) && str_contains($proprietario, ' | ')) {
                        foreach (explode(' | ', $proprietario) as $p) {
                            $writeCsvLine(array_merge($r, ['proprietario' => trim($p)]));
                        }
                    } else {
                        $writeCsvLine($r);
                    }

                    // Righe sub — solo FOGLIO, PARTICELLA, sub, proprietario, catasto_tipo
                    foreach ($subData as $sub) {
                        $subRow = array_fill_keys($headers, '');
                        $subRow['FOGLIO']      = $r['FOGLIO'];
                        $subRow['PARTICELLA']  = $r['PARTICELLA'];
                        $subRow['sub']         = $sub['sub'] ?? '';
                        $subRow['catasto_tipo'] = $sub['tipo'] ?? 'Fabbricato';
                        $subProp = $sub['proprietario'] ?? '';
                        if (!empty($subProp) && str_contains($subProp, ' | ')) {
                            foreach (explode(' | ', $subProp) as $p) {
                                $writeCsvLine(array_merge($subRow, ['proprietario' => trim($p)]));
                            }
                        } else {
                            $subRow['proprietario'] = $subProp;
                            $writeCsvLine($subRow);
                        }
                    }
                }
            }

            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            return response($csvContent, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename={$fileName}",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore CSV: ' . $e->getMessage()], 500);
        }
    }

    public function eliminaElaborazione(Request $request, $code_comune = null, $table = null)
    {
        try {
            $code_comune = strtoupper($code_comune ?? $request->get('code_comune'));
            $table = $table ?? $request->get('table');

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);

            if (!preg_match('/^aree_edificabili_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2})?$/', $table)) {
                return response()->json(['error' => 'Nome tabella non valido'], 400);
            }

            DB::statement("DROP TABLE IF EXISTS {$table} CASCADE");

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore durante l\'eliminazione: ' . $e->getMessage()], 500);
        }
    }

    public function erroriCatastoNumber(Request $request)
    {
        try {
            $code_comune = strtoupper($request->code_comune);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);
            $table = "{$code_comune}_catasto";

            $query = "SELECT gid, \"FOGLIO\", \"PARTICELLA\", ST_IsValidReason(geom) as errore FROM {$table} WHERE NOT ST_IsValid(geom)";
            $results = DB::select($query);

            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }

    public function erroriUrbanisticaNumber(Request $request)
    {
        try {
            $code_comune = strtoupper($request->code_comune);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);
            $table = $this->pianiComuneBooster[0]->codice_piano ?? null;

            if (!$table) {
                return response()->json(['error' => 'Piano urbanistico non disponibile'], 404);
            }

            $query = "SELECT gid, \"LAYER\", \"STRING\", ST_IsValidReason(geom) as errore FROM {$table} WHERE NOT ST_IsValid(geom)";
            $results = DB::select($query);

            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }

    public function erroriCatasto(Request $request)
    {
        try {
            $code_comune = strtoupper($request->code_comune);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);
            $table = "{$code_comune}_catasto";

            $query = "SELECT gid, \"FOGLIO\", \"PARTICELLA\", ST_IsValidReason(geom) as errore FROM {$table} WHERE NOT ST_IsValid(geom)";
            $results = DB::select($query);

            // Genera CSV
            $filename = "errori_catasto_{$code_comune}.csv";
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function () use ($results) {
                $output = fopen('php://output', 'w');
                // Intestazioni
                fputcsv($output, ['gid', 'FOGLIO', 'PARTICELLA', 'errore']);
                // Dati
                foreach ($results as $row) {
                    fputcsv($output, (array) $row);
                }
                fclose($output);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }

    public function erroriUrbanistica(Request $request)
    {
        try {
            $code_comune = strtoupper($request->code_comune);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);
            $table = $this->pianiComuneBooster[0]->codice_piano ?? null;

            if (!$table) {
                return response()->json(['error' => 'Piano urbanistico non disponibile'], 404);
            }

            $query = "SELECT gid, \"LAYER\", \"STRING\", ST_IsValidReason(geom) as errore FROM {$table} WHERE NOT ST_IsValid(geom)";
            $results = DB::select($query);

            $filename = "errori_urbanistica_{$code_comune}.csv";
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function () use ($results) {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['gid', 'LAYER', 'STRING', 'errore']);
                foreach ($results as $row) {
                    fputcsv($output, (array) $row);
                }
                fclose($output);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }

    //WEB
    /**
     * Helper: Restituisce la lista dei comuni
     */
    private function getComuniList()
    {
        return [
            "I062" => 'San Nicola Manfredi',
            'C659' => 'Chiusano San Domenico',
            'C245' => 'Castelpagano',
            'D756' => 'Fragneto Monforte',
            "I197" => 'Sant\'Agata dei Goti'
        ];
    }

    /**
     * Step 1: Mostra pagina principale con sidebar
     */
    public function index()
    {
        $comuni = $this->getComuniList();
        return view('booster.index', compact('comuni'));
    }

    /**
     * Step 2: Carica ZTO via AJAX (ritorna JSON)
     */
    public function showZto(Request $request)
    {
        $request->validate([
            'code_comune' => 'required|regex:/^[a-zA-Z0-9_]+$/',
        ]);

        try {
            $code_comune = strtoupper($request->code_comune);
            $this->setDB($code_comune);

            if (empty($this->pianiComuneBooster) || !isset($this->pianiComuneBooster[0]->codice_piano) || $this->pianiComuneBooster[0]->codice_piano == '') {
                return response()->json(['error' => 'Nessun piano urbanistico trovato per questo comune'], 404);
            }

            $piano_codice = $this->pianiComuneBooster[0]->codice_piano;
            $piano_name = strtoupper(str_replace('urbutm', '', $piano_codice));

            $query = "SELECT DISTINCT \"STRING\" FROM {$piano_codice} ORDER BY \"STRING\" ASC";
            $zto_list = DB::select($query);

            return response()->json([
                'piano_name' => $piano_name,
                'data' => $zto_list
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Step 3: Elabora le ZTO (ritorna JSON per AJAX)
     */
    public function elabora(Request $request)
    {
        set_time_limit(0);

        try {
            $code_comune = strtoupper($request->code_comune);
            $zto = $request->zto;
            $exclude = filter_var($request->input('exclude'), FILTER_VALIDATE_BOOLEAN);

            Log::info("BOOSTER STEP 1: code_comune=$code_comune, exclude=$exclude, zto=" . implode(',', $zto));

            $this->setDB($code_comune);
            Log::info("BOOSTER STEP 2: setDB OK");

            $data = now()->format('d_m_Y_H_i');
            $finalTable = "aree_edificabili_finali_{$data}";

            $tableExists = DB::select("SELECT to_regclass('{$finalTable}') IS NOT NULL as exists")[0]->exists;
            Log::info("BOOSTER STEP 3: tableExists=" . ($tableExists ? 'true' : 'false'));

            if ($tableExists) {
                return response()->json([
                    'error' => 'Elaborazione già presente per oggi. È necessario eliminarla prima di procedere.'
                ], 409);
            }

            $urbanistica = $this->pianiComuneBooster[0]->codice_piano ?? null;
            Log::info("BOOSTER STEP 4: urbanistica=$urbanistica");

            if (!$urbanistica) {
                return response()->json(['error' => 'Piano urbanistico non trovato'], 404);
            }

            // Le AREE EDIFICABILI usano la copia editabile del catasto
            // (<code>_catasto_base_aree_edif), non l'originale <code>_catasto.
            // La copia va creata a mano dall'operatore (correzione poligoni).
            $catastoAreeEdif = strtolower($code_comune) . '_catasto_base_aree_edif';
            $existsBase = DB::selectOne("SELECT to_regclass(?) IS NOT NULL AS ok", [$catastoAreeEdif]);
            if (!$existsBase || !$existsBase->ok) {
                return response()->json([
                    'error' => "Tabella '{$catastoAreeEdif}' non trovata. Va creata (copia editabile del catasto) prima di elaborare le aree edificabili."
                ], 422);
            }

            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            DB::statement("DROP TYPE IF EXISTS aree_edificabili_base CASCADE");
            Log::info("BOOSTER STEP 5: DROP base OK");

            DB::statement("CREATE TABLE aree_edificabili_base AS
            SELECT c.*,
                CASE
                    WHEN c.\"TIPOLOGIA\" = 'PARTICELLA' THEN
                        CASE WHEN EXISTS (
                            SELECT 1 FROM {$catastoAreeEdif} e
                            WHERE e.\"TIPOLOGIA\" = 'EDIFICIO' AND ST_Covers(c.geom, e.geom)
                        ) THEN 'EDIFICATA' ELSE 'LIBERA' END
                    ELSE 'NON_APPLICABILE_SOLO_EDIFICIO'
                END AS \"STATO\"
            FROM {$catastoAreeEdif} c
        ");
            Log::info("BOOSTER STEP 6: CREATE base OK");

            Log::info("BOOSTER STEP 7: DROP base1 START");
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base1 CASCADE");
            DB::statement("DROP TYPE IF EXISTS aree_edificabili_base1 CASCADE");
            Log::info("BOOSTER STEP 7b: DROP base1 OK - START CREATE base1");
            DB::statement("CREATE TABLE aree_edificabili_base1 AS
            SELECT p.gid, p.\"FOGLIO\", p.\"PARTICELLA\", p.\"TIPOLOGIA\", p.\"STATO\",
                CASE
                    WHEN COUNT(e.*) = 0 THEN p.geom
                    ELSE ST_Difference(p.geom, ST_Union(e.geom))
                END AS geom
            FROM aree_edificabili_base p
            LEFT JOIN aree_edificabili_base e ON ST_Intersects(p.geom, e.geom) AND e.\"TIPOLOGIA\" = 'EDIFICIO'
            WHERE p.\"TIPOLOGIA\" = 'PARTICELLA'
            GROUP BY p.gid, p.\"FOGLIO\", p.\"PARTICELLA\", p.\"TIPOLOGIA\", p.\"STATO\", p.geom
        ");
            Log::info("BOOSTER STEP 8: CREATE base1 OK");

            $ztoList = collect($zto)->map(fn($v) => "'" . str_replace("'", "''", $v) . "'")->join(',');
            Log::info("BOOSTER STEP 9: ztoList=$ztoList - START CREATE finalTable");

            DB::statement("CREATE TABLE {$finalTable} AS
            SELECT tt.\"LAYER\", tt.\"STRING\", tt.auiu, sum(tt.perc) as perc,
                sum(tt.aisect) as aisect, tt.\"TIPOLOGIA\", tt.\"FOGLIO\", tt.\"PARTICELLA\", tt.\"STATO\",
                ST_Union(tt.geom_intersection) as geom
            FROM (
                SELECT u.\"LAYER\", u.\"STRING\",
                    round(CAST(ST_Area(a.geom) AS numeric), 3) as auiu,
                    round(CAST(ST_Area(ST_Intersection(a.geom, u.geom)) AS numeric), 3) as aisect,
                    round(CAST(ST_Area(ST_Intersection(a.geom, u.geom)) * 100 / ST_Area(a.geom) AS numeric), 2) as perc,
                    a.\"TIPOLOGIA\", a.\"FOGLIO\", a.\"PARTICELLA\", a.\"STATO\",
                    ST_Intersection(a.geom, u.geom) as geom_intersection
                FROM aree_edificabili_base1 a
                INNER JOIN {$urbanistica} u ON ST_Intersects(a.geom, u.geom)
                WHERE a.\"TIPOLOGIA\" IN ('PARTICELLA', 'EDIFICIO')
                " . ($exclude ? "AND a.\"STATO\" = 'LIBERA'" : "") . "
                AND u.\"STRING\" IN ({$ztoList})
            ) as tt
            GROUP BY tt.\"LAYER\", tt.\"STRING\", tt.auiu, tt.\"TIPOLOGIA\", tt.\"PARTICELLA\", tt.\"FOGLIO\", tt.\"STATO\"
            ORDER BY tt.\"LAYER\", tt.\"FOGLIO\", tt.\"PARTICELLA\", tt.\"STATO\"
        ");
            Log::info("BOOSTER STEP 10: CREATE finalTable OK");

            Log::info("BOOSTER STEP 11: ALTER TABLE START");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN proprietario TEXT");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN catasto_tipo TEXT");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN sub_data TEXT");
            // Campo di lavorazione (0/1) e identita' di riga stabile.
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN lavorato smallint NOT NULL DEFAULT 0");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN gid serial PRIMARY KEY");
            Log::info("BOOSTER STEP 11b: ALTER TABLE OK");

            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base1 CASCADE");
            Log::info("BOOSTER STEP 12: cleanup tabelle temporanee OK");
            // Lancia il job in background — non blocca la risposta HTTP
            \App\Jobs\AggiornaPropietariBooster::dispatch($finalTable, $code_comune);
            Log::info("BOOSTER STEP 13: job AggiornaPropietari lanciato in background");

            return response()->json([
                'success' => true,
                'table'   => $finalTable,
                'date'    => $data,
                'message' => 'Tabella creata. I proprietari vengono popolati in background.'
            ]);
        } catch (\Throwable $e) {
            Log::error("BOOSTER ERRORE: " . $e->getMessage() . " in " . $e->getFile() . " linea " . $e->getLine());
            return response()->json(['error' => 'Errore durante l\'elaborazione: ' . $e->getMessage()], 500);
        }
    }

    public function jobStatus(Request $request)
    {
        $table = $request->input('table');
        if (!$this->isBoosterTable($table)) {
            return response()->json(['error' => 'Nome tabella non valido'], 400);
        }

        $status = Cache::get("job_status_{$table}");

        if (!$status) {
            return response()->json(['status' => 'unknown']);
        }

        return response()->json($status);
    }
    
    // Metodo listaElaborazioni - ritorna JSON
    public function listaElaborazioni($code_comune)
    {
        try {
            $code_comune = strtoupper($code_comune);

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);

            $tables = DB::select("
                SELECT tablename 
                FROM pg_tables 
                WHERE tablename LIKE 'aree_edificabili_finali_%'
                ORDER BY tablename DESC
            ");

            return response()->json(array_map(fn($t) => $t->tablename, $tables));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Metodo dettaglioElaborazione - mostra pagina dettaglio
    public function dettaglioElaborazione(Request $request, $code_comune, $table)
    {
        try {
            $code_comune = strtoupper($code_comune);

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return redirect()->back()->with('error', 'Codice comune non valido');
            }

            if (!preg_match('/^aree_edificabili_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2})?$/', $table)) {
                return redirect()->back()->with('error', 'Nome tabella non valido');
            }

            $this->setDB($code_comune);
            $this->ensureBoosterColumns($table);

            // Ordinamento sulle prime 5 colonne (whitelist -> nessuna injection).
            $sortMap = $this->sortMapAree();
            $sort = $request->get('sort');
            $dir  = strtolower($request->get('dir')) === 'desc' ? 'desc' : 'asc';
            if (!$sort || !isset($sortMap[$sort])) {
                $sort = null;
                $dir  = 'asc';
            }

            $rows = DB::table($table)
                ->orderByRaw($this->boosterOrderBy($sortMap, $sort, $dir, '"FOGLIO", "PARTICELLA", "STRING"'))
                ->paginate(50);

            return view('booster.dettaglio', compact('rows', 'code_comune', 'table', 'sort', 'dir'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore: ' . $e->getMessage());
        }
    }

    public function dettaglioElaborazioneJson(Request $request)
    {
        $code_comune = strtoupper($request->get('code_comune'));
        $table = $request->get('table');

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
            return response()->json(['error' => 'Codice comune non valido'], 400);
        }
        if (!preg_match('/^aree_edificabili_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2})?$/', $table)) {
            return response()->json(['error' => 'Nome tabella non valido'], 400);
        }

        $this->setDB($code_comune);
        $this->ensureBoosterColumns($table);

        $rows = DB::select("SELECT gid, lavorato, \"LAYER\", \"STRING\", \"FOGLIO\", \"PARTICELLA\", \"STATO\", auiu, perc, aisect, proprietario, catasto_tipo, sub_data FROM {$table} ORDER BY \"FOGLIO\", \"PARTICELLA\", \"STRING\"");

        return response()->json($rows);
    }

    /** Whitelist colonne ordinabili aree edificabili: chiave logica => espressione SQL. */
    private function sortMapAree()
    {
        return [
            'FOGLIO'       => '"FOGLIO"',
            'PARTICELLA'   => '"PARTICELLA"',
            'STATO'        => '"STATO"',
            'STRING'       => '"STRING"',      // ZTO
            'catasto_tipo' => 'catasto_tipo',  // TIPO CATASTO
        ];
    }

    /** Whitelist colonne ordinabili edifici fantasma. */
    private function sortMapEdificiFantasma()
    {
        return [
            'FOGLIO'        => '"FOGLIO"',
            'PARTICELLA'    => '"PARTICELLA"',
            'tipo_fantasma' => 'tipo_fantasma',
            'descr'         => 'descr',
            'area_mq'       => 'area_mq',
        ];
    }

    /**
     * Costruisce la clausola ORDER BY usata sia dalla vista dettaglio sia
     * dall'export CSV, cosi' il CSV rispetta l'ordinamento attivo a schermo.
     * L'ordinamento arriva sempre dalla whitelist -> nessuna injection.
     */
    private function boosterOrderBy(array $sortMap, $sort, $dir, $tieBreaker)
    {
        $dir = strtolower((string) $dir) === 'desc' ? 'desc' : 'asc';

        if (!$sort || !isset($sortMap[$sort])) {
            return $tieBreaker;
        }

        $col = $sortMap[$sort];
        // FOGLIO/PARTICELLA sono testo: ordinamento numerico-naturale con zero-pad.
        $expr = ($sort === 'FOGLIO' || $sort === 'PARTICELLA')
            ? "lpad({$col}::text, 12, '0') {$dir}"
            : "{$col} {$dir}";

        // tie-breaker stabile
        return "{$expr}, {$tieBreaker}";
    }

    /** Valida il nome di una tabella elaborazione booster (aree edif o edifici fantasma). */
    private function isBoosterTable($table)
    {
        return is_string($table)
            && preg_match('/^(aree_edificabili_finali|edifici_fantasma_finali)_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2})?$/', $table);
    }

    /**
     * Assicura che la tabella di elaborazione abbia le colonne 'lavorato' e 'id'.
     * Serve per le tabelle create prima dell'introduzione di questi campi.
     * $table deve essere gia' validato col regex delle tabelle booster.
     */
    private function ensureBoosterColumns($table)
    {
        $hasLavorato = DB::selectOne(
            "SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = 'lavorato'",
            [$table]
        );
        if (!$hasLavorato) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN lavorato smallint NOT NULL DEFAULT 0");
        }

        // Chiave di riga 'gid' come integer (int4): QGIS Server NON accetta
        // un campo bigint (int8) come chiave primaria. Migra anche le tabelle
        // vecchie che avevano 'id bigserial': droppa 'id' e crea 'gid serial'.
        $hasGid = DB::selectOne(
            "SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = 'gid'",
            [$table]
        );
        $hasId = DB::selectOne(
            "SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = 'id'",
            [$table]
        );
        if ($hasId) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN id");
        }
        if (!$hasGid) {
            // serial (int4) + PRIMARY KEY: assegna un gid univoco alle righe esistenti
            DB::statement("ALTER TABLE {$table} ADD COLUMN gid serial PRIMARY KEY");
        }
    }

    /** Estrae e valida code_comune + table + ids dalla request (usato dai due frontend). */
    private function parseRigheRequest(Request $request)
    {
        $code_comune = strtoupper($request->input('code_comune'));
        $table       = $request->input('table');

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
            return ['error' => 'Codice comune non valido', 'status' => 400];
        }
        if (!$this->isBoosterTable($table)) {
            return ['error' => 'Nome tabella non valido', 'status' => 400];
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            $ids = array_filter(explode(',', (string) $ids), fn($v) => $v !== '');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, fn($v) => $v > 0);

        if (empty($ids)) {
            return ['error' => 'Nessuna riga selezionata', 'status' => 400];
        }

        return ['code_comune' => $code_comune, 'table' => $table, 'ids' => array_values($ids)];
    }

    /** Imposta lavorato (0/1) su una o piu' righe. */
    public function setLavorato(Request $request)
    {
        try {
            $p = $this->parseRigheRequest($request);
            if (isset($p['error'])) {
                return response()->json(['error' => $p['error']], $p['status']);
            }

            $lavorato = (int) $request->input('lavorato') === 1 ? 1 : 0;

            $this->setDB($p['code_comune']);
            $this->ensureBoosterColumns($p['table']);

            $updated = DB::table($p['table'])->whereIn('gid', $p['ids'])->update(['lavorato' => $lavorato]);

            return response()->json(['ok' => true, 'updated' => $updated, 'lavorato' => $lavorato]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }

    /** Elimina definitivamente una o piu' righe dalla tabella di elaborazione. */
    public function eliminaRighe(Request $request)
    {
        try {
            $p = $this->parseRigheRequest($request);
            if (isset($p['error'])) {
                return response()->json(['error' => $p['error']], $p['status']);
            }

            $this->setDB($p['code_comune']);
            $this->ensureBoosterColumns($p['table']);

            $deleted = DB::table($p['table'])->whereIn('gid', $p['ids'])->delete();

            return response()->json(['ok' => true, 'deleted' => $deleted]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Prepara la tabella di elaborazione: aggiunge le colonne 'id' e 'lavorato'
     * se mancanti (retroattivo). Chiamato dal frontend all'apertura del dettaglio.
     */
    public function preparaTabella(Request $request)
    {
        try {
            $code_comune = strtoupper($request->input('code_comune'));
            $table       = $request->input('table');

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }
            if (!$this->isBoosterTable($table)) {
                return response()->json(['error' => 'Nome tabella non valido'], 400);
            }

            $this->setDB($code_comune);
            $this->ensureBoosterColumns($table);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }

    // =======================================================================
    // EDIFICI FANTASMA — pipeline (FASE 1..5) mappata dal PDF operativo.
    // Naming: tabelle base per-comune (<code>_catasto, _catasto_edifici),
    // tabelle intermedie generiche dentro il DB del comune, finale datata
    // edifici_fantasma_finali_<data>. SRID rilevato dalla sorgente.
    // =======================================================================

    /** Rileva lo SRID delle geometrie di una tabella (fallback 32633 = UTM33N). */
    private function detectSrid($table, $default = 32633)
    {
        try {
            $r = DB::selectOne("SELECT ST_SRID(geom) AS srid FROM {$table} WHERE geom IS NOT NULL LIMIT 1");
            return ($r && (int) $r->srid > 0) ? (int) $r->srid : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * FASE 1 (CTR): estrae gli edifici dalla CTR (descr IN <codici CSV>),
     * crea ctr_edifici_cartografia3d e la versione 2D, poi verifica i residui 3D.
     * Se restano geometrie 3D si ferma e le riporta (decisione operatore).
     */
    public function efFase1Ctr(Request $request)
    {
        try {
            $code_comune = strtoupper($request->input('code_comune'));
            $ctr = $request->input('nome_tabella_ctr');

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }
            if (!$ctr || !preg_match('/^[a-zA-Z0-9_]+$/', $ctr)) {
                return response()->json(['error' => 'Nome tabella CTR non valido'], 422);
            }

            $codici = $request->input('codici', []);
            if (!is_array($codici)) {
                $codici = preg_split('/[\r\n,;]+/', (string) $codici);
            }
            $codici = array_values(array_filter(array_map('trim', $codici), fn($v) => $v !== ''));
            if (empty($codici)) {
                return response()->json(['error' => 'Nessun codice edificio fornito (CSV vuoto)'], 422);
            }

            $this->setDB($code_comune);

            $ctrExists = DB::selectOne("SELECT to_regclass(?) IS NOT NULL AS ok", [strtolower($ctr)]);
            if (!$ctrExists || !$ctrExists->ok) {
                return response()->json(['error' => "Tabella CTR '{$ctr}' non trovata nel database. Verifica il nome (possibile errore di battitura)."], 422);
            }

            $placeholders = implode(',', array_fill(0, count($codici), '?'));

            // SUB FASE 1: estrazione 3D
            DB::statement("DROP TABLE IF EXISTS ctr_edifici_cartografia3d CASCADE");
            DB::statement("CREATE TABLE ctr_edifici_cartografia3d AS SELECT * FROM {$ctr} WHERE \"descr\" IN ({$placeholders})", $codici);

            // SUB FASE 2: versione 2D
            DB::statement("DROP TABLE IF EXISTS ctr_edifici_cartografia2d CASCADE");
            DB::statement("CREATE TABLE ctr_edifici_cartografia2d AS SELECT gid, descr, ST_Force2D(geom) AS geom FROM ctr_edifici_cartografia3d");
            // gid come PRIMARY KEY: serve a QGIS per poter modificare/eliminare i
            // poligoni non validi (il CREATE TABLE AS non porta il vincolo).
            DB::statement("ALTER TABLE ctr_edifici_cartografia2d ADD PRIMARY KEY (gid)");

            $count3d = (int) DB::selectOne("SELECT COUNT(*) AS c FROM ctr_edifici_cartografia3d")->c;
            $count2d = (int) DB::selectOne("SELECT COUNT(*) AS c FROM ctr_edifici_cartografia2d")->c;

            // SUB FASE 3: verifica residui 3D
            $records3D = DB::select("SELECT gid, descr FROM ctr_edifici_cartografia2d WHERE ST_NDims(geom) = 3 ORDER BY gid");
            $has3D = count($records3D) > 0;

            return response()->json([
                'ok'        => !$has3D,
                'has3D'     => $has3D,
                'count3d'   => $count3d,
                'count2d'   => $count2d,
                'records3D' => $records3D,
                'message'   => $has3D
                    ? 'Ci sono ancora record 3D: vanno verificati/eliminati prima di procedere.'
                    : 'FASE 1 completata: nessun record 3D residuo.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore FASE 1 (CTR): ' . $e->getMessage()], 500);
        }
    }

    /** Elimina i record 3D indicati da ctr_edifici_cartografia2d e ricontrolla. */
    public function efEliminaRecord3D(Request $request)
    {
        try {
            $code_comune = strtoupper($request->input('code_comune'));
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }
            $gids = $request->input('gids', []);
            if (!is_array($gids)) {
                $gids = array_filter(explode(',', (string) $gids), fn($v) => $v !== '');
            }
            $gids = array_values(array_filter(array_map('intval', $gids), fn($v) => $v > 0));
            if (empty($gids)) {
                return response()->json(['error' => 'Nessun record selezionato'], 422);
            }

            $this->setDB($code_comune);
            $deleted = DB::table('ctr_edifici_cartografia2d')->whereIn('gid', $gids)->delete();

            $records3D = DB::select("SELECT gid, descr FROM ctr_edifici_cartografia2d WHERE ST_NDims(geom) = 3 ORDER BY gid");
            return response()->json([
                'ok'        => true,
                'deleted'   => $deleted,
                'has3D'     => count($records3D) > 0,
                'records3D' => $records3D,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore eliminazione record 3D: ' . $e->getMessage()], 500);
        }
    }

    /** FASE 2 (Catasto): crea <code>_catasto_edifici con i soli poligoni EDIFICIO. */
    public function efFase2Catasto(Request $request)
    {
        try {
            $code_comune = strtoupper($request->input('code_comune'));
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }
            $this->setDB($code_comune);

            $catasto = strtolower($code_comune) . '_catasto';
            $edifici = strtolower($code_comune) . '_catasto_edifici';

            $exists = DB::selectOne("SELECT to_regclass(?) IS NOT NULL AS ok", [$catasto]);
            if (!$exists || !$exists->ok) {
                return response()->json(['error' => "Tabella '{$catasto}' non trovata."], 422);
            }

            DB::statement("DROP TABLE IF EXISTS {$edifici} CASCADE");
            DB::statement("CREATE TABLE {$edifici} AS SELECT * FROM {$catasto} WHERE \"TIPOLOGIA\" = 'EDIFICIO'");
            // gid come PRIMARY KEY: consente la modifica/eliminazione dei poligoni in QGIS.
            DB::statement("ALTER TABLE {$edifici} ADD PRIMARY KEY (gid)");
            $count = (int) DB::selectOne("SELECT COUNT(*) AS c FROM {$edifici}")->c;

            return response()->json([
                'ok'      => true,
                'table'   => $edifici,
                'count'   => $count,
                'message' => "FASE 2 completata: creata {$edifici} ({$count} edifici).",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore FASE 2 (Catasto): ' . $e->getMessage()], 500);
        }
    }

    /** FASE 3: verifica validità poligoni (tipo = 'catasto' | 'ctr'). */
    public function efVerificaPoligoni(Request $request)
    {
        try {
            $code_comune = strtoupper($request->input('code_comune'));
            $tipo = $request->input('tipo');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }
            $this->setDB($code_comune);

            if ($tipo === 'catasto') {
                $table = strtolower($code_comune) . '_catasto_edifici';
                $exists = DB::selectOne("SELECT to_regclass(?) IS NOT NULL AS ok", [$table]);
                if (!$exists || !$exists->ok) {
                    return response()->json(['error' => "Esegui prima la FASE 2 (manca {$table})."], 422);
                }
                $rows = DB::select("SELECT gid, \"FOGLIO\", \"PARTICELLA\", ST_IsValidReason(geom) AS errore FROM {$table} WHERE NOT ST_IsValid(geom) ORDER BY gid");
            } elseif ($tipo === 'ctr') {
                $table = 'ctr_edifici_cartografia2d';
                $exists = DB::selectOne("SELECT to_regclass(?) IS NOT NULL AS ok", [$table]);
                if (!$exists || !$exists->ok) {
                    return response()->json(['error' => "Esegui prima la FASE 1 (manca {$table})."], 422);
                }
                $rows = DB::select("SELECT gid, descr, ST_IsValidReason(geom) AS errore FROM {$table} WHERE NOT ST_IsValid(geom) ORDER BY gid");
            } else {
                return response()->json(['error' => 'Tipo verifica non valido (catasto|ctr)'], 422);
            }

            return response()->json(['ok' => true, 'tipo' => $tipo, 'invalidi' => $rows, 'count' => count($rows)]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore verifica poligoni: ' . $e->getMessage()], 500);
        }
    }

    /**
     * FASE 4 + 5: genera edifici_fantasma_finali_<data> (differenza CTR/catasto,
     * split in poligoni, area, filtro per soglie) e lancia il job proprietari.
     */
    public function efElabora(Request $request)
    {
        set_time_limit(0);
        try {
            $code_comune = strtoupper($request->input('code_comune'));
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $amplMax   = (float) $request->input('ampliamenti_max', 0);
            $nuovaMax  = (float) $request->input('nuova_edif_max', 0);
            $soloNuova = filter_var($request->input('solo_nuova_edificazione'), FILTER_VALIDATE_BOOLEAN);

            $this->setDB($code_comune);

            $catasto = strtolower($code_comune) . '_catasto';
            $edifici = strtolower($code_comune) . '_catasto_edifici';

            // Prerequisiti dalle fasi precedenti
            foreach (['ctr_edifici_cartografia2d' => 'FASE 1', $edifici => 'FASE 2', $catasto => 'catasto'] as $t => $fase) {
                $ex = DB::selectOne("SELECT to_regclass(?) IS NOT NULL AS ok", [$t]);
                if (!$ex || !$ex->ok) {
                    return response()->json(['error' => "Prerequisito mancante: tabella '{$t}' ({$fase}). Esegui prima le fasi precedenti."], 422);
                }
            }

            $srid = $this->detectSrid($catasto);
            $data = now()->format('d_m_Y_H_i');
            $finalTable = "edifici_fantasma_finali_{$data}";

            $exists = DB::selectOne("SELECT to_regclass(?) IS NOT NULL AS ok", [strtolower($finalTable)]);
            if ($exists && $exists->ok) {
                return response()->json(['error' => 'Elaborazione già presente per questo minuto. Attendere un minuto o eliminarla.'], 409);
            }

            // SUB FASE 1: base con differenza + classificazione tipo_fantasma
            DB::statement("DROP TABLE IF EXISTS edifici_fantasma_base CASCADE");
            DB::statement("CREATE TABLE edifici_fantasma_base AS
                SELECT a.gid, a.descr,
                    CASE WHEN COUNT(b.geom) = 0 THEN a.geom ELSE ST_Difference(a.geom, ST_Union(b.geom)) END AS geom,
                    CASE WHEN COUNT(b.geom) > 0 THEN 'AMPLIAMENTO' ELSE 'NUOVA EDIFICAZIONE' END AS tipo_fantasma
                FROM ctr_edifici_cartografia2d a
                LEFT JOIN {$edifici} b ON ST_Intersects(a.geom, b.geom)
                GROUP BY a.gid, a.descr, a.geom");

            // SUB FASE 2: intersezione con le sole PARTICELLE del catasto per
            // ricavare FOGLIO/PARTICELLA (gli EDIFICI non hanno questi campi).
            DB::statement("DROP TABLE IF EXISTS edifici_fantasma_tmp CASCADE");
            DB::statement("CREATE TABLE edifici_fantasma_tmp AS
                SELECT e.gid AS id_edificio, e.descr, e.tipo_fantasma, c.\"FOGLIO\", c.\"PARTICELLA\",
                    ST_Multi(ST_CollectionExtract(ST_Intersection(e.geom, c.geom), 3)) AS geom
                FROM edifici_fantasma_base e
                JOIN {$catasto} c ON e.geom && c.geom AND ST_Intersects(e.geom, c.geom)
                    AND c.\"TIPOLOGIA\" = 'PARTICELLA'
                WHERE NOT ST_IsEmpty(ST_CollectionExtract(ST_Intersection(e.geom, c.geom), 3))");

            // SUB FASE 4 + area + filtro soglie -> tabella finale con poligoni singoli
            $filtro = "NOT (tipo_fantasma = 'AMPLIAMENTO' AND area_mq BETWEEN 0 AND {$amplMax})"
                . " AND NOT (tipo_fantasma = 'NUOVA EDIFICAZIONE' AND area_mq BETWEEN 0 AND {$nuovaMax})";
            if ($soloNuova) {
                $filtro .= " AND tipo_fantasma = 'NUOVA EDIFICAZIONE'";
            }

            DB::statement("CREATE TABLE {$finalTable} AS
                WITH dumped AS (
                    SELECT id_edificio, descr, tipo_fantasma, \"FOGLIO\", \"PARTICELLA\", (ST_Dump(geom)).geom AS geom
                    FROM edifici_fantasma_tmp
                ),
                poly AS (
                    SELECT id_edificio, descr, tipo_fantasma, \"FOGLIO\", \"PARTICELLA\",
                        ST_SetSRID(geom, {$srid})::geometry(Polygon, {$srid}) AS geom,
                        ROUND(ST_Area(geom)::numeric, 2) AS area_mq
                    FROM dumped
                    WHERE ST_GeometryType(geom) IN ('ST_Polygon', 'ST_MultiPolygon')
                )
                SELECT * FROM poly WHERE {$filtro}");

            // FASE 5: colonne proprietari + lavorato/id (come aree edificabili)
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN proprietario TEXT");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN catasto_tipo TEXT");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN sub_data TEXT");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN lavorato smallint NOT NULL DEFAULT 0");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN gid serial PRIMARY KEY");

            DB::statement("DROP TABLE IF EXISTS edifici_fantasma_base CASCADE");
            DB::statement("DROP TABLE IF EXISTS edifici_fantasma_tmp CASCADE");

            // Popolamento proprietari in background (stesso job delle aree edif)
            \App\Jobs\AggiornaPropietariBooster::dispatch($finalTable, $code_comune);

            $count = (int) DB::selectOne("SELECT COUNT(*) AS c FROM {$finalTable}")->c;

            return response()->json([
                'success' => true,
                'table'   => $finalTable,
                'date'    => $data,
                'srid'    => $srid,
                'count'   => $count,
                'message' => 'Tabella edifici fantasma creata. I proprietari vengono popolati in background.',
            ]);
        } catch (\Throwable $e) {
            Log::error("EDIFICI FANTASMA ERRORE: " . $e->getMessage() . " in " . $e->getFile() . " linea " . $e->getLine());
            return response()->json(['error' => 'Errore elaborazione edifici fantasma: ' . $e->getMessage()], 500);
        }
    }

    /** Elenco elaborazioni edifici fantasma (tabelle edifici_fantasma_finali_%). */
    public function efElaborazioni($code_comune)
    {
        try {
            $code_comune = strtoupper($code_comune);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }
            $this->setDB($code_comune);
            // Solo le elaborazioni "datate" (edifici_fantasma_finali_gg_mm_aaaa[_hh_mm]);
            // esclude tabelle intermedie/manuali come edifici_fantasma_finali_poly*.
            $tables = DB::select("SELECT tablename FROM pg_tables WHERE tablename ~ '^edifici_fantasma_finali_[0-9]{2}_[0-9]{2}_[0-9]{4}(_[0-9]{2}_[0-9]{2})?$' ORDER BY tablename DESC");
            return response()->json(array_map(fn($t) => $t->tablename, $tables));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** Elimina un'elaborazione edifici fantasma (drop della tabella). */
    public function efElimina(Request $request, $code_comune = null, $table = null)
    {
        try {
            $code_comune = strtoupper($code_comune ?? $request->get('code_comune'));
            $table = $table ?? $request->get('table');

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }
            if (!preg_match('/^edifici_fantasma_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2})?$/', $table)) {
                return response()->json(['error' => 'Nome tabella non valido'], 400);
            }

            $this->setDB($code_comune);
            DB::statement("DROP TABLE IF EXISTS {$table} CASCADE");

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore durante l\'eliminazione: ' . $e->getMessage()], 500);
        }
    }

    /** Pagina dettaglio (paginata + ordinabile) di un'elaborazione edifici fantasma. */
    public function efDettaglio(Request $request, $code_comune, $table)
    {
        try {
            $code_comune = strtoupper($code_comune);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return redirect()->back()->with('error', 'Codice comune non valido');
            }
            if (!preg_match('/^edifici_fantasma_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2})?$/', $table)) {
                return redirect()->back()->with('error', 'Nome tabella non valido');
            }

            $this->setDB($code_comune);
            $this->ensureBoosterColumns($table);

            $sortMap = $this->sortMapEdificiFantasma();
            $sort = $request->get('sort');
            $dir  = strtolower($request->get('dir')) === 'desc' ? 'desc' : 'asc';
            if (!$sort || !isset($sortMap[$sort])) {
                $sort = null;
                $dir  = 'asc';
            }

            $rows = DB::table($table)
                ->orderByRaw($this->boosterOrderBy($sortMap, $sort, $dir, '"FOGLIO", "PARTICELLA"'))
                ->paginate(50);

            return view('booster.edifici_fantasma_dettaglio', compact('rows', 'code_comune', 'table', 'sort', 'dir'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore: ' . $e->getMessage());
        }
    }

    /** Export CSV di un'elaborazione edifici fantasma (proprietari espansi in righe distinte). */
    public function efDownload(Request $request, $code_comune = null, $table = null)
    {
        $code_comune = strtoupper($code_comune ?? $request->get('code_comune'));
        $table = $table ?? $request->get('table');

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
            return response()->json(['error' => 'Codice comune non valido'], 400);
        }
        if (!preg_match('/^edifici_fantasma_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2})?$/', $table)) {
            return response()->json(['error' => 'Nome tabella non valido'], 400);
        }

        $this->setDB($code_comune);

        try {
            $fileName = "{$table}.csv";
            $handle = fopen('php://temp', 'w+');

            // Stesso ordinamento della vista dettaglio (sort/dir passati dal link CSV).
            $orderBy = $this->boosterOrderBy(
                $this->sortMapEdificiFantasma(),
                $request->get('sort'),
                $request->get('dir'),
                '"FOGLIO", "PARTICELLA"'
            );
            $rows = DB::select("SELECT * FROM {$table} ORDER BY {$orderBy}");

            if (count($rows) > 0) {
                $firstRow = (array) $rows[0];
                unset($firstRow['geom'], $firstRow['sub_data']);
                $mainHeaders = array_keys($firstRow);
                $headers = array_merge($mainHeaders, ['sub']);

                fwrite($handle, implode(';', array_map(fn($h) => '"' . $h . '"', $headers)) . "\r\n");

                $writeCsvLine = function (array $values) use ($handle, $headers) {
                    // Colonne numeriche: virgola decimale, niente separatore migliaia.
                    // Col punto Excel in locale IT lo interpreta come separatore delle
                    // migliaia e lo elimina (881.45 -> 88145).
                    $numeriche = ['area_mq' => 2, 'auiu' => 3, 'aisect' => 3, 'perc' => 2];
                    $ordered = [];
                    foreach ($headers as $h) {
                        $v = $values[$h] ?? '';
                        if (isset($numeriche[$h]) && $v !== '' && $v !== null) {
                            $v = number_format((float) $v, $numeriche[$h], ',', '');
                        }
                        $ordered[] = '"' . str_replace('"', '""', $v) . '"';
                    }
                    fwrite($handle, implode(';', $ordered) . "\r\n");
                };

                foreach ($rows as $row) {
                    $r = (array) $row;
                    unset($r['geom']);
                    $subData = json_decode($r['sub_data'] ?? '[]', true) ?: [];
                    unset($r['sub_data']);
                    $r['sub'] = '';

                    $proprietario = $r['proprietario'] ?? '';
                    if (!empty($proprietario) && str_contains($proprietario, ' | ')) {
                        foreach (explode(' | ', $proprietario) as $p) {
                            $writeCsvLine(array_merge($r, ['proprietario' => trim($p)]));
                        }
                    } else {
                        $writeCsvLine($r);
                    }

                    foreach ($subData as $sub) {
                        $subRow = array_fill_keys($headers, '');
                        $subRow['FOGLIO']       = $r['FOGLIO'] ?? '';
                        $subRow['PARTICELLA']   = $r['PARTICELLA'] ?? '';
                        $subRow['sub']          = $sub['sub'] ?? '';
                        $subRow['catasto_tipo'] = $sub['tipo'] ?? 'Fabbricato';
                        $subProp = $sub['proprietario'] ?? '';
                        if (!empty($subProp) && str_contains($subProp, ' | ')) {
                            foreach (explode(' | ', $subProp) as $p) {
                                $writeCsvLine(array_merge($subRow, ['proprietario' => trim($p)]));
                            }
                        } else {
                            $subRow['proprietario'] = $subProp;
                            $writeCsvLine($subRow);
                        }
                    }
                }
            }

            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            return response($csvContent, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename={$fileName}",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore CSV: ' . $e->getMessage()], 500);
        }
    }
}
