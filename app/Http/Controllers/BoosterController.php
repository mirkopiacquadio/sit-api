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
    private $nomiDb = array(
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

            $rows = DB::select("SELECT * FROM {$table} ORDER BY \"FOGLIO\", \"PARTICELLA\", \"STRING\"");

            if (count($rows) > 0) {
                // Costruisci headers: escludi geom e sub_data, aggiungi sub alla fine
                $firstRow = (array)$rows[0];
                unset($firstRow['geom'], $firstRow['sub_data']);
                $mainHeaders = array_keys($firstRow);
                $headers = array_merge($mainHeaders, ['sub']);

                fwrite($handle, implode(';', array_map(fn($h) => '"' . $h . '"', $headers)) . "\r\n");

                $writeCsvLine = function (array $values) use ($handle, $headers) {
                    $ordered = [];
                    foreach ($headers as $h) {
                        $ordered[] = '"' . str_replace('"', '""', $values[$h] ?? '') . '"';
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
            'B946' => 'Casavatore',
            'D230' => 'Cusano Mutri',
            'D469' => 'Faicchio',
            'D784' => 'Frasso Telesino',
            "I062" => 'San Nicola Manfredi',
            'F717' => 'Morcone',
            'G848' => 'Pontelandolfo',
            'L185' => 'Tocco Caudio',
            'H967' => 'San Lorenzo Maggiore',
            'G311' => 'Pannarano',
            'G991' => 'Prata Sannita',
            'H313' => 'Ripalimosani',
            'L254' => 'Torrecuso',
            'F111' => 'Melito',
            'C659' => 'Chiusano San Domenico',
            'D361' => 'Dragoni',
            'C245' => 'Castelpagano',
            'H894' => 'San Giorgio del Sannio',
            'H898' => 'San Giorgio la Molara',
            'F448' => 'Montecalvo Irpino',
            'L739' => 'Venticano',
            'D756' => 'Fragneto Monforte',
            'F113' => 'Melizzano',
            'C250' => 'Castelpoto',
            'G386' => 'Paupisi',
            'H087' => 'Puglianello',
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

            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            Log::info("BOOSTER STEP 5: DROP base OK");

            DB::statement("CREATE TABLE aree_edificabili_base AS
            SELECT c.*, 
                CASE 
                    WHEN c.\"TIPOLOGIA\" = 'PARTICELLA' THEN 
                        CASE WHEN EXISTS (
                            SELECT 1 FROM {$code_comune}_catasto e
                            WHERE e.\"TIPOLOGIA\" = 'EDIFICIO' AND ST_Covers(c.geom, e.geom)
                        ) THEN 'EDIFICATA' ELSE 'LIBERA' END
                    ELSE 'NON_APPLICABILE_SOLO_EDIFICIO'
                END AS \"STATO\"
            FROM {$code_comune}_catasto c
        ");
            Log::info("BOOSTER STEP 6: CREATE base OK");

            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base1 CASCADE");
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
            Log::info("BOOSTER STEP 7: CREATE base1 OK");

            $ztoList = collect($zto)->map(fn($v) => "'" . addslashes($v) . "'")->join(',');
            Log::info("BOOSTER STEP 8: ztoList=$ztoList");

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
            Log::info("BOOSTER STEP 9: CREATE finalTable OK");

            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN proprietario TEXT");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN catasto_tipo TEXT");
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN sub_data TEXT");
            Log::info("BOOSTER STEP 10: ALTER TABLE OK");

            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base1 CASCADE");
            Log::info("BOOSTER STEP 11: cleanup tabelle temporanee OK");
            // Lancia il job in background — non blocca la risposta HTTP
            \App\Jobs\AggiornaPropietariBooster::dispatch($finalTable, $code_comune);
            Log::info("BOOSTER STEP 12: job AggiornaPropietari lanciato in background");

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
        if (!preg_match('/^aree_edificabili_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2})?$/', $table)) {
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
    public function dettaglioElaborazione($code_comune, $table)
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

            $rows = DB::table($table)
                ->orderByRaw('"FOGLIO", "PARTICELLA", "STRING"')
                ->paginate(50);

            return view('booster.dettaglio', compact('rows', 'code_comune', 'table'));
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
        if (!preg_match('/^aree_edificabili_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2}_\d{2})?$/', $table)) {
            return response()->json(['error' => 'Nome tabella non valido'], 400);
        }

        $this->setDB($code_comune);

        $rows = DB::select("SELECT \"LAYER\", \"STRING\", \"FOGLIO\", \"PARTICELLA\", \"STATO\", aisect, proprietario, catasto_tipo, sub_data FROM {$table} ORDER BY \"FOGLIO\", \"PARTICELLA\", \"STRING\"");

        return response()->json($rows);
    }
}
