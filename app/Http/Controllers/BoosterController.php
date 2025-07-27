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
        "9999" => 'morcone-webgis', //prova, per la versione development...
        "B946" => 'casavatore-webgis',
        "D230" => 'cusanomutri-webgis',
        "D469" => 'faicchio-webgis',
        "D784" => 'frasso_telesino-webgis',
        "F717" => 'morcone-webgis',
        "G848" => 'pontelandolfo-webgis',
        "L185" => 'toccocaudio-webgis',
        "H967" => 'sanlorenzomaggiore-webgis',
        "G311" => 'pannarano-webgis',
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
    //costruttore privato per il singleton
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

    public function test() {
        $code_comune = 'c245';
        $this->setDB($code_comune);
        /*
        $ress = \AppHelper::selectSuperficieTerreno(21, 254, '', $code_comune);
        $mq = intval($ress['ettari']) * 10000 + intval($ress['are']) * 100 + intval($ress['centiare']);*/

        $f = '21';
        $n = '254';
        $tipoFabbricati = 'F';
        $comune = 'c245';

        $condUiu = '';
        if ($f != '')
            $condUiu .= " AND foglio='$f'";
        if ($n != '')
            $condUiu .= " AND numero='$n'";
        else
            $condUiu .= " AND sub is null ";


        if ($tipoFabbricati) {
            $tipo = 'F';
            $strPF = 'from c_fabb_info INFO join c_fabb_identificativi IDN on INFO.id=IDN.id_fabb_info join c_tit_fabb_sogg_f REL on INFO.id=REL.id_fabb';
            $strPG = 'from c_fabb_info INFO join c_fabb_identificativi IDN on INFO.id=IDN.id_fabb_info join c_tit_fabb_sogg_g REL on INFO.id=REL.id_fabb';
        } else {
            $tipo = 'T';
            $strPF = 'from c_terr_info INFO  join c_tit_terr_sogg_f REL on INFO.id=REL.id_terr';
            $strPG = 'from c_terr_info INFO  join c_tit_terr_sogg_g REL on INFO.id=REL.id_terr';
        }


        $query = "select TIT.data_validita as datavaltit,TIT.quota_num, TIT.quota_den,  TIT.titolo_non_codificato,
	     (select descrizione from c_predefinito_codice_diritto where codice=TIT.codice_diritto) as diritto,
		     TIT.id_mutazione_iniziale, TIT.id_mutazione_finale,progressivo,data_efficacia,data_registrazione_atti, regexp_replace(TIT.numero_nota, '0*', '')as numero_nota,
		     regexp_replace(TIT.prog_nota, '0*', '')as prog_nota, TIT.anno_nota, data_efficacia1, data_registrazione_atti1,
		     regexp_replace(TIT.numero_nota1, '0*', '')as numero_nota1,regexp_replace(TIT.prog_nota1, '0*', '')as prog_nota1, TIT.anno_nota1,TIT.descrizione_atto_generante as att_gen,
		     (SELECT descrizione from c_predefinito_codici_causale where codice=TIT.codice_causale_atto_generante) as cod_atto,";


        //query sui fabb fisici
        $queryFisici = "$query true as pf,(select descrizione from c_predefinito_tipo_nota where tipo='$tipo' and codice=TIT.tipo_nota)as tipo_nota,
		     (select descrizione from c_predefinito_tipo_nota where tipo='$tipo' and codice=TIT.tipo_nota1) as tipo_nota1,
		     S.cognome, S.nome, S.data_nascita,S.sesso,(select descrizione from c_predefinito_lista_comuni where codice_catastale=S.luogo_nascita) as
		     luogo_nascita,(select pv from c_predefinito_lista_comuni where codice_catastale=S.luogo_nascita) as pv_nascita,'' as denominazione, '' as sede,S.cf, S.indicazioni
		     $strPF join c_titolarita TIT on TIT.id=REL.id_tit join c_sogg_fisico S on REL.id_sogg_f=S.id where INFO.cod_com='" . $comune . "' $condUiu ORDER by datavaltit DESC";



        //query sui fabb giuridici
        $queryGiuridic = "$query false as pf,(select descrizione from c_predefinito_tipo_nota where tipo='$tipo' and codice=TIT.tipo_nota)as tipo_nota,
		     (select descrizione from c_predefinito_tipo_nota where tipo='$tipo' and codice=TIT.tipo_nota1) as tipo_nota1,
		     '' as cognome, '' as nome, null as data_nascita,'' as sesso, '' as luogo_nascita, '' as pv_nascita,S.denominazione,
		     (select descrizione from c_predefinito_lista_comuni where codice_catastale=S.sede) as sede,S.cf,'' as indicazioni $strPG join c_titolarita TIT 
		     on TIT.id=REL.id_tit join c_sogg_giuridico S on REL.id_sogg_g=S.id where INFO.cod_com='" . $comune . "' $condUiu ORDER by
		     datavaltit DESC";

        $unionQuery = "SELECT * FROM( ($queryFisici) UNION ($queryGiuridic) )as f ORDER BY datavaltit DESC";

        $res = DB::connection('pgsql2')->select($unionQuery);

        $c = count($res);

        print_r($res);
        print_r($c);
        exit;
    }

    public function elPianiBooster(Request $request)
    {
        $this->setDB(strtoupper($request->code_comune));
        return $this->pianiComuneBooster;
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
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN mq INTEGER");
                /*
            // 1. Recupera tutte le particelle LIBERE dalla tabella finale
            $particelle = DB::table($finalTable)
                ->select('FOGLIO', 'PARTICELLA')
                ->where('STATO', 'LIBERA')
                ->distinct()
                ->get();

            // 2. Prendi i fogli e numeri unici
            $fogli = $particelle->pluck('FOGLIO')->unique()->toArray();
            $numeri = $particelle->pluck('PARTICELLA')->unique()->toArray();

            // 3. Prendi i dati catastali da pgsql2
            $superfici = DB::connection('pgsql2')
                ->table('c_terr_info')
                ->where('cod_com', strtoupper($code_comune))
                ->whereIn('foglio', $fogli)
                ->whereIn('numero', $numeri)
                ->orderByDesc('id')
                ->get();

            // 4. Crea mappa foglio+numero => mq
            $mqMap = [];
            foreach ($superfici as $s) {
                $key = trim($s->foglio) . '_' . ltrim($s->numero, '0');
                if (!isset($mqMap[$key])) {
                    $mqMap[$key] = ((int)$s->ettari * 10000) + ((int)$s->are * 100) + ((int)$s->centiare);
                }
            }

            // 5. Applica l'update in blocchi
            foreach ($particelle->chunk(1000) as $chunk) {
                foreach ($chunk as $p) {
                    $key = trim($p->FOGLIO) . '_' . ltrim($p->PARTICELLA, '0');
                    if (isset($mqMap[$key])) {
                        DB::table($finalTable)
                            ->where('FOGLIO', $p->FOGLIO)
                            ->where('PARTICELLA', $p->PARTICELLA)
                            ->update(['mq' => $mqMap[$key]]);
                    }
                }
            }
            */

            // Pulizia finale
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base1 CASCADE");

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
}
