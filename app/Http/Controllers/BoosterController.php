<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BoosterController extends Controller
{
    private $elencoFg = array();
    private $nomiDb = array(
        "9999" => 'morcone-webgis',
        "B946" => 'casavatore-webgis',
        "D230" => 'cusanomutri-webgis',
        "D469" => 'faicchio-webgis',
        "D784" => 'frasso_telesino-webgis',
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
    private $infoComune = [];
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

        // NON chiamare setDB qui: quando si arriva da elaboraWeb
        // il DB è già configurato correttamente, e pgsql2 non va toccata

        $fakeRequest = new \Illuminate\Http\Request([
            'code_comune' => $code_comune,
            'foglio'      => $foglio,
            'particella'  => $particella,
            'sub'         => $sub,
        ]);

        $controller = new \App\Http\Controllers\CatastoImmobileController();

        // Prova prima terreni, poi fabbricati
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

    public function getFoglioParticellaBooster(Request $request)
    {
        try {
            // Pulizia e validazione input
            $code_comune = strtoupper($request->code_comune);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);

            $tableName = strtolower($code_comune) . '_catasto';

            // Esecuzione query
            $query = "SELECT * FROM {$tableName} WHERE TRIM(UPPER(\"TIPOLOGIA\")) IN ('EDIFICIO', 'PARTICELLA')";
            $result = DB::select($query);
            $ress = \AppHelper::selectSuperficieTerreno('1', '17', '', strtolower($code_comune));
            $mq = intval($ress['ettari']) * 10000 + intval($ress['are']) * 100 + intval($ress['centiare']);
            return response()->json($mq);
        } catch (\Exception $e) {
            // Gestione errore generico (es. tabella non esistente)
            return response()->json([
                'error' => 'Errore durante il recupero dei dati',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function zto(Request $request)
    {
        try {
            $code_comune = strtoupper($request->code_comune);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);

            if ($this->pianiComuneBooster[0]->codice_piano != '') {
                $piano_name = strtoupper(str_replace('urbutm', '', $this->pianiComuneBooster[0]->codice_piano ?? ''));
                $query = "SELECT DISTINCT \"STRING\" FROM {$this->pianiComuneBooster[0]->codice_piano} ORDER BY \"STRING\" ASC";
                $result = DB::select($query);
                return response()->json(['piano_name' => $piano_name, 'data' => $result]);
            } else {
                return response()->json(['error' => 'Nessun piano trovato'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore interno: ' . $e->getMessage()], 500);
        }
    }

    public function elabora(Request $request)
    {
        try {

            $code_comune = strtoupper($request->get('code_comune'));
            $zto = $request->get('zto', []);
            $exclude = filter_var($request->get('exclude'), FILTER_VALIDATE_BOOLEAN);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);

            $data = now()->format('d_m_Y');
            $finalTable = "aree_edificabili_finali_{$data}";

            // Check se già esiste
            $tableExists = DB::select("
                SELECT to_regclass('{$finalTable}') IS NOT NULL as exists
            ")[0]->exists;

            if ($tableExists) {
                return response()->json([
                    'error' => 'Elaborazione già presente per oggi. È necessario eliminarla prima di procedere.'
                ], 409); // HTTP 409 Conflict
            }

            $urbanistica = $this->pianiComuneBooster[0]->codice_piano ?? null;
            if (!$urbanistica) {
                return response()->json(['error' => 'Piano urbanistico non trovato'], 404);
            }

            // FASE 1: crea base
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            DB::statement("
            CREATE TABLE aree_edificabili_base AS
            SELECT c.*, 
                CASE 
                    WHEN c.\"TIPOLOGIA\" = 'PARTICELLA' THEN 
                        CASE WHEN EXISTS (
                            SELECT 1 FROM {$code_comune}_catasto_1 e
                            WHERE e.\"TIPOLOGIA\" = 'EDIFICIO' AND ST_Covers(c.geom, e.geom)
                        ) THEN 'EDIFICATA' ELSE 'LIBERA' END
                    ELSE 'NON_APPLICABILE_SOLO_EDIFICIO'
                END AS \"STATO\"
            FROM {$code_comune}_catasto_1 c
        ");

            // FASE 2: crea base1
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base1 CASCADE");
            DB::statement("
            CREATE TABLE aree_edificabili_base1 AS
            SELECT  
                p.gid, p.\"FOGLIO\", p.\"PARTICELLA\", p.\"TIPOLOGIA\", p.\"STATO\",
                CASE 
                    WHEN COUNT(e.*) = 0 THEN p.geom 
                    ELSE ST_Difference(p.geom, ST_Union(e.geom)) 
                END AS geom
            FROM aree_edificabili_base p
            LEFT JOIN aree_edificabili_base e ON ST_Intersects(p.geom, e.geom) AND e.\"TIPOLOGIA\" = 'EDIFICIO'
            WHERE p.\"TIPOLOGIA\" = 'PARTICELLA'
            GROUP BY p.gid, p.\"FOGLIO\", p.\"PARTICELLA\", p.\"TIPOLOGIA\", p.\"STATO\", p.geom
        ");

            // FASE 3: crea finali
            $ztoList = collect($zto)->map(fn($v) => "'" . addslashes($v) . "'")->join(',');

            DB::statement("
            CREATE TABLE {$finalTable} AS
            SELECT  
                tt.\"LAYER\", tt.\"STRING\", tt.auiu, sum(tt.perc) as perc,
                sum(tt.aisect) as aisect, tt.\"TIPOLOGIA\", tt.\"FOGLIO\", tt.\"PARTICELLA\", tt.\"STATO\",
                ST_Union(tt.geom_intersection) as geom
            FROM (
                SELECT 
                    u.\"LAYER\", u.\"STRING\",
                    round(CAST(ST_Area(a.geom) AS numeric), 3) as auiu,
                    round(CAST(ST_Area(ST_Intersection(a.geom, u.geom)) AS numeric), 3) as aisect,
                    round(CAST(ST_Area(ST_Intersection(a.geom, u.geom)) * 100 / ST_Area(a.geom) AS numeric), 2) as perc,
                    a.\"TIPOLOGIA\", a.\"FOGLIO\", a.\"PARTICELLA\", a.\"STATO\",
                    ST_Intersection(a.geom, u.geom) as geom_intersection
                FROM aree_edificabili_base1 a
                INNER JOIN {$urbanistica} u ON ST_Intersects(a.geom, u.geom)
                WHERE a.\"TIPOLOGIA\" IN ('PARTICELLA', 'EDIFICIO')
                " . ($exclude ? "AND a.\"STATO\" = 'LIBERA'" : "") . "
                " . (!empty($ztoList) ? "AND u.\"STRING\" IN ({$ztoList})" : "") . "
            ) as tt
            GROUP BY tt.\"LAYER\", tt.\"STRING\", tt.auiu, tt.\"TIPOLOGIA\", tt.\"PARTICELLA\", tt.\"FOGLIO\", tt.\"STATO\"
            ORDER BY tt.\"LAYER\", tt.\"FOGLIO\", tt.\"PARTICELLA\", tt.\"STATO\"
        ");
            // Aggiunta campo mq (metri quadri)
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN proprietario TEXT");

            // Pulizia finale
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base1 CASCADE");
            $records = DB::table($finalTable)->select('FOGLIO', 'PARTICELLA')->distinct()->get();
            foreach ($records as $record) {
                $owners = $this->getProprietariAttuali($code_comune, $record->FOGLIO, $record->PARTICELLA);

                if (!empty($owners)) {
                    // Recupera i dati della riga originale
                    $row = (array) DB::table($finalTable)
                        ->where('FOGLIO', $record->FOGLIO)
                        ->where('PARTICELLA', $record->PARTICELLA)
                        ->first();

                    // Cancella l’originale PRIMA degli insert
                    DB::table($finalTable)
                        ->where('FOGLIO', $record->FOGLIO)
                        ->where('PARTICELLA', $record->PARTICELLA)
                        ->delete();

                    // Inserisci una riga per ogni proprietario
                    foreach ($owners as $o) {
                        $newRow = $row;
                        $newRow['proprietario'] = "{$o['nome']} ({$o['cf']}) - Titolo: {$o['titolo']} - {$o['descrizione']}";
                        DB::table($finalTable)->insert($newRow);
                    }
                }
            }

            return response()->json(['success' => true, 'table' => $finalTable, 'date' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore durante l’elaborazione: ' . $e->getMessage()], 500);
        }
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

    public function downloadElaborazione(Request $request)
    {
        $code_comune = strtoupper($request->get('code_comune'));

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
            return response()->json(['error' => 'Codice comune non valido'], 400);
        }

        $this->setDB($code_comune);

        $table = $request->get('table');
        if (!preg_match('/^aree_edificabili_finali_\d{2}_\d{2}_\d{4}(_\d{2}_\d{2}_\d{2})?$/', $table)) {
            return response()->json(['error' => 'Nome tabella non valido'], 400);
        }

        try {
            $fileName = "{$table}.csv";
            $handle = fopen('php://temp', 'w+');

            $rows = DB::select("SELECT * FROM {$table}");

            if (count($rows) > 0) {
                $headers = array_keys((array)$rows[0]);
                if (($k = array_search('geom', $headers)) !== false) {
                    unset($headers[$k]);
                }
                fputcsv($handle, $headers);

                foreach ($rows as $row) {
                    $r = (array)$row;
                    unset($r['geom']);
                    fputcsv($handle, $r);
                }
            }

            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            return response($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename={$fileName}",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore CSV: ' . $e->getMessage()], 500);
        }
    }

    public function eliminaElaborazione(Request $request)
    {
        try {
            $code_comune = strtoupper($request->get('code_comune'));

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $code_comune)) {
                return response()->json(['error' => 'Codice comune non valido'], 400);
            }

            $this->setDB($code_comune);

            $table = $request->get('table');

            if (!preg_match('/^aree_edificabili_finali_\d{2}_\d{2}_\d{4}$/', $table)) {
                return response()->json(['error' => 'Nome tabella non valido'], 400);
            }

            $this->setDB($code_comune);
            DB::statement("DROP TABLE IF EXISTS {$table} CASCADE");

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore durante l’eliminazione: ' . $e->getMessage()], 500);
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

    public function selectPoligonoUiuCat($tabella, $fg, $nm, $tipo, $cod_comune)
    {
        //possibile risultato non univoco!!!
        $this->setDB($cod_comune);
        $res = DB::connection('pgsql')->table($tabella)
            ->select('gid')
            ->where('FOGLIO', '=', $fg)
            ->where('PARTICELLA', '=', $nm)
            ->where('TIPOLOGIA', '=', $tipo)
            ->get();

        return $res;
    }

    public function selectPoligonoUiuUrb($tabella, $nome, $cod_comune)
    {
        //possibile risultato non univoco!!!

        $this->setDB($cod_comune);
        $res = DB::table($tabella)
            ->select('gid')
            ->where('STRING', '=', $nome)
            ->get();

        return $res;
    }

    /********************************URBANISTICA********************************************/
    public function elencoNormePiani($tabella, $code_comune)
    {
        $this->setDB($code_comune);
        $query = "select distinct initcap(replace(\"LAYER\",'_', ' ')) as \"LAYER\",\"STRING\" as nm from $tabella order by \"LAYER\"";
        return DB::select($query);
    }

    public function intersezioniPianiUrbanistici($table, $gid, $code_comune)
    {
        $this->setDB($code_comune);

        // Elenco dei piani
        $q = "select table_name  from information_schema.tables where table_name like '%urbutm%' order by table_name";
        $res = DB::connection('pgsql')->select($q);
        $intNulla = true;
        $arrRes = array();

        foreach ($res as $row) {
            $piano = substr($row->table_name, 0, -6);
            $nome_tabella = $row->table_name;

            if ($nome_tabella) {
                $q = 'SELECT "LAYER","STRING","FOGLIO", "PARTICELLA","TIPOLOGIA",tt.auiu as auiu, sum(tt.perc) as perc, sum(tt.aisect) as
                aisect FROM(
                SELECT "LAYER","STRING","FOGLIO", "PARTICELLA","TIPOLOGIA",
                round(cast(st_area(a.geom)as numeric),3)as auiu, round(cast(st_area(ST_Intersection(a.geom, b.geom))as numeric),3)as
                aisect,
                round(cast(st_area(ST_Intersection(a.geom, b.geom))*100/st_area(a.geom)as numeric),2) as perc from ' . $table . ' a
                inner join "' . $nome_tabella . '" b ON ST_Intersects(a.geom, b.geom) where a.gid=' . $gid . '
                )as tt group by tt."LAYER","FOGLIO", "PARTICELLA","TIPOLOGIA",tt."STRING", tt.auiu ORDER BY "LAYER"';

                $res1 = DB::connection('pgsql')->select($q);

                if ($res1) {
                    if (!isset($arrRes[$piano])) {
                        $arrRes[$piano] = array();
                    }

                    foreach ($res1 as $item) {
                        $arrRes[$piano][] = $item;
                    }

                    $intNulla = false;
                }
            }
        }

        if (!$intNulla) {
            // return $arrRes; ritorna array con le intersezioni
            foreach ($arrRes as $piano => $value) {
                $this->calcolaCdu(7, 488, $piano . 'urbutm', $code_comune); //MIRKOOOO
                print_r('TEST');
                exit;
            }
        } else {
            return false;
        }
    }
    /***************************FINE URBANISTICA*********************************************/

    /*********************** CALCOLO CDU **********************************/
    public function generaCDU(Request $request)
    {
        $this->setDB(strtoupper($request->code_comune));
        $comune = strtoupper($request->code_comune);
        $post = $request->all();

        $visMq = $request->has('cdusetmq');
        $visPerc = $request->has('cdusetperc');
        $decimali = $request->cifdecvisu;
        $approx = $request->input('cifdecvisu') == '1' && $request->has('cdusetapprox');

        $piani = $request->piano;
        $elUiu = json_decode($post['uiu']);

        $c = count($elUiu);
        $c1 = count($piani);

        //array che contiene le uiu senza intersezioni
        $intNulla = [];
        $cIntNulla = 0;

        //array che contiene le uiu con superficie catastale nulla
        $supNulla = [];
        $cSupNulla = 0;


        //riempi array di uiu:
        $uiu = [];
        // $cUiu = 0;

        //elenco delle norme
        $norme = [];

        $contaUiu = 0;
        for ($i = 0; $i < $c; $i++) {
            if ($elUiu[$i]->fg != '' && $elUiu[$i]->plla != '') {
                //trova la superficie catastale:
                $ress = \AppHelper::selectSuperficieTerreno($elUiu[$i]->fg, $elUiu[$i]->plla, $elUiu[$i]->sb, strtolower($comune));

                if (!$ress) {
                    print_r('QUI 3');
                    exit;
                }

                $mq = intval($ress['ettari']) * 10000 + intval($ress['are']) * 100 + intval($ress['centiare']);
                //echo '_MQ='.$mq;
                if ($mq > 0) {

                    $uiu[$contaUiu]['fg'] = $elUiu[$i]->fg;
                    $uiu[$contaUiu]['nm'] = $elUiu[$i]->plla;
                    $uiu[$contaUiu]['sb'] = $elUiu[$i]->sb;
                    $uiu[$contaUiu]['intersects'] = [];

                    //trova intersezioni con i piani
                    for ($j = 0; $j < $c1; $j++) {
                        $res1 = $this->calcolaCdu($elUiu[$i]->fg, $elUiu[$i]->plla, $piani[$j], $comune);
                        if ($res1 !== null) {
                            foreach ($res1 as $row) {
                                if ($row != null) {
                                    $row->cal = -1;
                                    $row->cal = $this->calcolaValoreCdu($row->aisect, $mq, $row->auiu, $row->perc, $decimali, $approx, $visPerc, $visMq);
                                    if ($row->cal !== false) {
                                        if (!isset($uiu[$contaUiu]['intersects'][$piani[$j]]) || !is_array($uiu[$contaUiu]['intersects'][$piani[$j]])) {
                                            $uiu[$contaUiu]['intersects'][$piani[$j]] = [];
                                        }
                                        array_push($uiu[$contaUiu]['intersects'][$piani[$j]], (array) $row); // Cast $row to an array

                                        // Insert the norm
                                        if (!isset($norme[$piani[$j]]) || !is_array($norme[$piani[$j]])) {
                                            $norme[$piani[$j]] = [];
                                        }
                                        if (!in_array($row->LAYER, $norme[$piani[$j]])) {
                                            $norme[$piani[$j]][] = $row->LAYER;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (count($uiu[$contaUiu]['intersects']) == 0) {
                        $intNulla[$cIntNulla]['fg'] = $elUiu[$i]->fg;
                        $intNulla[$cIntNulla]['nm'] = $elUiu[$i]->plla;
                        $intNulla[$cIntNulla]['sb'] = $elUiu[$i]->sb;
                        $cIntNulla++;
                    }

                    $uiu[$contaUiu]['mq'] = number_format($mq, $decimali, ',', '.') . ' mq';
                    $contaUiu++;
                } else {

                    $supNulla[$cSupNulla]['fg'] = $elUiu[$i]->fg;
                    $supNulla[$cSupNulla]['nm'] = $elUiu[$i]->plla;
                    $supNulla[$cSupNulla]['sb'] = $elUiu[$i]->sb;
                    $cSupNulla++;
                }
            }
        }

        $content = \AppHelper::formattaCdu($post, $uiu, $norme, $comune, $this->nomiPiani);

        if ($content !== null) {
            $nomeFile = 'CDU_' . date('d-m-Y');

            // Percorso per il file temporaneo HTML
            $tempHtmlPath = storage_path('app/' . strtoupper($comune) . '/tmp/' . $nomeFile . '.html');

            // Crea la directory per tmp se non esiste
            File::makeDirectory(dirname($tempHtmlPath), 0755, true, true);

            // Crea il file temporaneo HTML
            File::put($tempHtmlPath, $content);

            // Verifica se il file è stato creato correttamente
            if (File::exists($tempHtmlPath)) {
                // Percorso per il file di output Word
                $outputWordPath = storage_path('app/' . strtoupper($comune) . '/documenti/' . $nomeFile . '.doc');

                // Crea la directory per documenti se non esiste
                File::makeDirectory(dirname($outputWordPath), 0755, true, true);

                // Esegui la conversione utilizzando LibreOffice
                exec('"C:\Program Files\LibreOffice\program\soffice.bin" --convert-to "doc:MS Word 97" --outdir ' . storage_path('app/' . strtoupper($comune) . '/documenti/') . ' ' . $tempHtmlPath);

                // Verifica se il file Word è stato creato correttamente
                if (File::exists($outputWordPath)) {
                    // Cancella il file temporaneo .html
                    File::delete($tempHtmlPath);

                    // Ritorna il file Word come risposta HTTP e cancella il file dopo l'invio
                    return response()->download($outputWordPath)->deleteFileAfterSend(true);
                } else {
                    // Se la creazione del file Word ha fallito, ritorna un messaggio di errore
                    return response()->json(['error' => 'Failed to create Word file'], 500);
                }
            } else {
                // Se la creazione del file HTML temporaneo ha fallito, ritorna un messaggio di errore
                return response()->json(['error' => 'Failed to create temporary HTML file'], 500);
            }
        } else {
            echo 'Non disponibile';
        }
    }

    public function generaCDUHtml(Request $request)
    {
        $this->setDB(strtoupper($request->code_comune));
        $comune = strtoupper($request->code_comune);
        $post = $request->all();

        $visMq = $request->has('cdusetmq');
        $visPerc = $request->has('cdusetperc');
        $decimali = $request->cifdecvisu;
        $approx = $request->input('cifdecvisu') == '1' && $request->has('cdusetapprox');

        $piani = $request->piano;
        $elUiu = json_decode($post['uiu']);

        //print_r(array_values($elUiu));
        $c = count($elUiu);
        $c1 = count($piani);

        //array che contiene le uiu senza intersezioni
        $intNulla = [];
        $cIntNulla = 0;

        //array che contiene le uiu con superficie catastale nulla
        $supNulla = [];
        $cSupNulla = 0;


        //riempi array di uiu:
        $uiu = [];
        // $cUiu = 0;

        //elenco delle norme
        $norme = [];

        $contaUiu = 0;
        for ($i = 0; $i < $c; $i++) {
            if ($elUiu[$i]->fg != '' && $elUiu[$i]->plla != '') {
                //trova la superficie catastale:
                $ress = \AppHelper::selectSuperficieTerreno($elUiu[$i]->fg, $elUiu[$i]->plla, $elUiu[$i]->sb, strtolower($comune));

                if (!$ress) {
                    print_r('NON E\' PRESENTE ');
                    exit;
                }

                $mq = intval($ress['ettari']) * 10000 + intval($ress['are']) * 100 + intval($ress['centiare']);
                //echo '_MQ='.$mq;
                if ($mq > 0) {

                    $uiu[$contaUiu]['fg'] = $elUiu[$i]->fg;
                    $uiu[$contaUiu]['nm'] = $elUiu[$i]->plla;
                    $uiu[$contaUiu]['sb'] = $elUiu[$i]->sb;
                    $uiu[$contaUiu]['intersects'] = [];

                    //trova intersezioni con i piani
                    for ($j = 0; $j < $c1; $j++) {
                        $res1 = $this->calcolaCdu($elUiu[$i]->fg, $elUiu[$i]->plla, $piani[$j], $comune);
                        if ($res1 !== null) {
                            foreach ($res1 as $row) {
                                if ($row != null) {
                                    $row->cal = -1;
                                    $row->cal = $this->calcolaValoreCdu($row->aisect, $mq, $row->auiu, $row->perc, $decimali, $approx, $visPerc, $visMq);
                                    if ($row->cal !== false) {
                                        if (!isset($uiu[$contaUiu]['intersects'][$piani[$j]]) || !is_array($uiu[$contaUiu]['intersects'][$piani[$j]])) {
                                            $uiu[$contaUiu]['intersects'][$piani[$j]] = [];
                                        }
                                        array_push($uiu[$contaUiu]['intersects'][$piani[$j]], (array) $row); // Cast $row to an array

                                        // Insert the norm
                                        if (!isset($norme[$piani[$j]]) || !is_array($norme[$piani[$j]])) {
                                            $norme[$piani[$j]] = [];
                                        }
                                        if (!in_array($row->LAYER, $norme[$piani[$j]])) {
                                            $norme[$piani[$j]][] = $row->LAYER;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (count($uiu[$contaUiu]['intersects']) == 0) {
                        $intNulla[$cIntNulla]['fg'] = $elUiu[$i]->fg;
                        $intNulla[$cIntNulla]['nm'] = $elUiu[$i]->plla;
                        $intNulla[$cIntNulla]['sb'] = $elUiu[$i]->sb;
                        $cIntNulla++;
                    }

                    $uiu[$contaUiu]['mq'] = number_format($mq, $decimali, ',', '.') . ' mq';
                    $contaUiu++;
                } else {

                    $supNulla[$cSupNulla]['fg'] = $elUiu[$i]->fg;
                    $supNulla[$cSupNulla]['nm'] = $elUiu[$i]->plla;
                    $supNulla[$cSupNulla]['sb'] = $elUiu[$i]->sb;
                    $cSupNulla++;
                }
            }
        }

        $data_uiu = $uiu[0];
        $nmPiani = $this->nomiPiani;
        $vista = view('table', compact('data_uiu', 'nmPiani'))->render();

        return ['vista' => $vista, 'mq' => $data_uiu['mq']];
    }

    function print_cdu_from_modal(Request $request)
    {
        $this->setDB(strtoupper($request->code_comune));
        $comune = strtoupper($request->code_comune);
        $post = $request->all();

        $visMq = $request->has('cdusetmq');
        $visPerc = $request->has('cdusetperc');
        $decimali = $request->cifdecvisu;
        $approx = $request->input('cifdecvisu') == '1' && $request->has('cdusetapprox');

        $piani = json_decode($request->piano);
        $elUiu = json_decode($post['uiu']);

        $c = count($elUiu);
        $c1 = count($piani);

        //array che contiene le uiu senza intersezioni
        $intNulla = [];
        $cIntNulla = 0;

        //array che contiene le uiu con superficie catastale nulla
        $supNulla = [];
        $cSupNulla = 0;


        //riempi array di uiu:
        $uiu = [];
        // $cUiu = 0;

        //elenco delle norme
        $norme = [];

        $contaUiu = 0;
        for ($i = 0; $i < $c; $i++) {
            if ($elUiu[$i]->fg != '' && $elUiu[$i]->plla != '') {
                //trova la superficie catastale:
                $ress = \AppHelper::selectSuperficieTerreno($elUiu[$i]->fg, $elUiu[$i]->plla, $elUiu[$i]->sb, strtolower($comune));

                if (!$ress) {
                    print_r('NON E\' PRESENTE ');
                    exit;
                }

                $mq = intval($ress['ettari']) * 10000 + intval($ress['are']) * 100 + intval($ress['centiare']);
                //echo '_MQ='.$mq;
                if ($mq > 0) {

                    $uiu[$contaUiu]['fg'] = $elUiu[$i]->fg;
                    $uiu[$contaUiu]['nm'] = $elUiu[$i]->plla;
                    $uiu[$contaUiu]['sb'] = $elUiu[$i]->sb;
                    $uiu[$contaUiu]['intersects'] = [];

                    //trova intersezioni con i piani
                    for ($j = 0; $j < $c1; $j++) {
                        $res1 = $this->calcolaCdu($elUiu[$i]->fg, $elUiu[$i]->plla, $piani[$j], $comune);
                        if ($res1 !== null) {
                            foreach ($res1 as $row) {
                                if ($row != null) {
                                    $row->cal = -1;
                                    $row->cal = $this->calcolaValoreCdu($row->aisect, $mq, $row->auiu, $row->perc, $decimali, $approx, $visPerc, $visMq);
                                    if ($row->cal !== false) {
                                        if (!isset($uiu[$contaUiu]['intersects'][$piani[$j]]) || !is_array($uiu[$contaUiu]['intersects'][$piani[$j]])) {
                                            $uiu[$contaUiu]['intersects'][$piani[$j]] = [];
                                        }
                                        array_push($uiu[$contaUiu]['intersects'][$piani[$j]], (array) $row); // Cast $row to an array

                                        // Insert the norm
                                        if (!isset($norme[$piani[$j]]) || !is_array($norme[$piani[$j]])) {
                                            $norme[$piani[$j]] = [];
                                        }
                                        if (!in_array($row->LAYER, $norme[$piani[$j]])) {
                                            $norme[$piani[$j]][] = $row->LAYER;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (count($uiu[$contaUiu]['intersects']) == 0) {
                        $intNulla[$cIntNulla]['fg'] = $elUiu[$i]->fg;
                        $intNulla[$cIntNulla]['nm'] = $elUiu[$i]->plla;
                        $intNulla[$cIntNulla]['sb'] = $elUiu[$i]->sb;
                        $cIntNulla++;
                    }

                    $uiu[$contaUiu]['mq'] = number_format($mq, $decimali, ',', '.') . ' mq';
                    $contaUiu++;
                } else {

                    $supNulla[$cSupNulla]['fg'] = $elUiu[$i]->fg;
                    $supNulla[$cSupNulla]['nm'] = $elUiu[$i]->plla;
                    $supNulla[$cSupNulla]['sb'] = $elUiu[$i]->sb;
                    $cSupNulla++;
                }
            }
        }

        $data_uiu = $uiu[0];

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', TRUE);
        $options->set('debugKeepTemp', TRUE);
        $options->set('isHtml5ParserEnabled', TRUE);
        $options->set('chroot', '/');
        $options->setIsRemoteEnabled(true);
        $dompdf = new Dompdf($options);
        $nmPiani = $this->nomiPiani;
        $comune = $this->infoComune;

        $html = view('table_email', compact('data_uiu', 'elUiu', 'comune', 'nmPiani'));

        $dompdf->loadHtml($html);

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream('Riassunto', ["compress" => 1, "Attachment" => false]);
        exit;
    }

    private function calcolaCdu($foglio, $numero, $piano, $code_comune)
    {
        $fg = $foglio;
        if ($fg != '') {
            $fg = \AppHelper::formatNumber($fg, 3);
        }

        if (in_array($fg . 'utm', $this->elencoFg)) {

            $query = 'select tt."LAYER" as "LAYER", tt."STRING", tt.auiu as auiu, sum(tt.perc) as perc, sum(tt.aisect) as aisect from(
                SELECT "LAYER", "STRING", round(cast(st_area(a.geom)as numeric),3)as auiu, round(cast(st_area(ST_Intersection(a.geom, b.geom))as numeric),3)as aisect, round(cast(st_area(ST_Intersection(a.geom, b.geom))*100/st_area(a.geom)as numeric),2) as perc
                FROM ' . strtolower($code_comune) . $fg . 'utm a 
                INNER JOIN ' . $piano . ' b ON ST_Intersects(a.geom, b.geom)
                where a."FOGLIO"=\'' . $foglio . '\' AND a."PARTICELLA"=\'' . $numero . '\' AND a."TIPOLOGIA"=\'PARTICELLA\')as tt group by tt."LAYER",tt."STRING", tt.auiu ORDER BY "LAYER"';

            $res = \DB::select($query);

            if ($res) return $res;
            else return null;
        } else return null;
    }

    private static function calcolaValoreCdu(&$aisect, &$mq, &$auiu, &$perc, $cifreDecimali, &$approssimazione, &$visPerc, &$visMq)
    {
        global $mqMinimo;
        $prop = round((($aisect * $mq) / $auiu), $cifreDecimali);

        if ($cifreDecimali === 0 && $approssimazione) {
            $lastDigit = $prop % 10;
            if ($lastDigit <= 5) {
                $prop = $prop - $lastDigit;
            } else {
                $prop = $prop + (10 - $lastDigit);
            }
        }
        if ($prop > $mq)
            $prop = $mq;
        else if ($prop < $mqMinimo) {
            return false;
        }

        $str = '';

        if ($visMq === true) {
            $str .= number_format($prop, $cifreDecimali, ',', '.') . ' mq ';
        }
        if ($visPerc === true) {
            if ($visMq === true)
                $str .= ' (' . number_format($perc, $cifreDecimali, ',', '.') . ' %)';
            else
                $str .= number_format($perc, $cifreDecimali, ',', '.') . ' %';
        }
        return $str;
    }
    /************************ FINE CALCOLO CDU ****************************/

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
    public function elaboraWeb(Request $request)
    {
        try {
            $code_comune = strtoupper($request->code_comune);
            $zto = $request->zto;
            $exclude = filter_var($request->input('exclude'), FILTER_VALIDATE_BOOLEAN);

            echo "STEP 1: code_comune=$code_comune";
            flush();

            $this->setDB($code_comune);
            echo " | STEP 2: setDB OK";
            flush();

            $data = now()->format('d_m_Y');
            $finalTable = "aree_edificabili_finali_{$data}";

            $tableExists = DB::select("SELECT to_regclass('{$finalTable}') IS NOT NULL as exists")[0]->exists;
            echo " | STEP 3: tableExists=$tableExists";
            flush();

            if ($tableExists) {
                exit("BLOCCATO: tabella già esistente $finalTable");
            }

            $urbanistica = $this->pianiComuneBooster[0]->codice_piano ?? null;
            echo " | STEP 4: urbanistica=$urbanistica";
            flush();

            if (!$urbanistica) exit("BLOCCATO: piano urbanistico null");

            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            echo " | STEP 5: DROP base OK";
            flush();

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
            echo " | STEP 6: CREATE base OK";
            flush();

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
            echo " | STEP 7: CREATE base1 OK";
            flush();

            $ztoList = collect($zto)->map(fn($v) => "'" . addslashes($v) . "'")->join(',');
            echo " | STEP 8: ztoList=$ztoList";
            flush();

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
            echo " | STEP 9: CREATE finalTable OK";
            flush();

            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN proprietario TEXT");
            echo " | STEP 10: ALTER TABLE OK";
            flush();

            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base1 CASCADE");
            echo " | STEP 11: cleanup OK";
            flush();

            $records = DB::table($finalTable)->select('FOGLIO', 'PARTICELLA')->distinct()->limit(500)->get();
            echo " | STEP 12: records count=" . count($records);
            flush();

            foreach ($records as $i => $record) {
                echo " | STEP 13.$i: foglio={$record->FOGLIO} particella={$record->PARTICELLA}";
                flush();

                $owners = $this->getProprietariAttuali($code_comune, $record->FOGLIO, $record->PARTICELLA);
                echo " owners=" . count($owners);
                flush();

                if (!empty($owners)) {
                    $row = (array) DB::table($finalTable)
                        ->where('FOGLIO', $record->FOGLIO)
                        ->where('PARTICELLA', $record->PARTICELLA)
                        ->first();

                    DB::table($finalTable)
                        ->where('FOGLIO', $record->FOGLIO)
                        ->where('PARTICELLA', $record->PARTICELLA)
                        ->delete();

                    foreach ($owners as $o) {
                        $newRow = $row;
                        $newRow['proprietario'] = "{$o['nome']} ({$o['cf']}) - Titolo: {$o['titolo']} - {$o['descrizione']}";
                        DB::table($finalTable)->insert($newRow);
                    }
                }
            }

            echo " | STEP 14: DONE";
            flush();
            exit;
        } catch (\Throwable $e) {
            exit("ECCEZIONE al: " . $e->getMessage() . " in " . $e->getFile() . " linea " . $e->getLine());
        }
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

            if (!preg_match('/^aree_edificabili_finali_\d{2}_\d{2}_\d{4}$/', $table)) {
                return redirect()->back()->with('error', 'Nome tabella non valido');
            }

            $this->setDB($code_comune);

            $rows = DB::table($table)->paginate(50);

            return view('booster.dettaglio', compact('rows', 'code_comune', 'table'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore: ' . $e->getMessage());
        }
    }
}
