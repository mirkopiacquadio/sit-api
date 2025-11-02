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

    public function test()
    {
        // 
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
            DB::statement("ALTER TABLE {$finalTable} ADD COLUMN proprietario TEXT");

            /*$resFinale = $this->elencoMutazioniCatastoTerreni($code_comune, 7, 10);
            return $resFinale;
            exit;*/

            // Pulizia finale
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base CASCADE");
            DB::statement("DROP TABLE IF EXISTS aree_edificabili_base1 CASCADE");

            return response()->json(['success' => true, 'table' => $finalTable, 'date' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore durante l’elaborazione: ' . $e->getMessage()], 500);
        }
    }

    private function elencoMutazioniCatastoTerreni($code_comune, $foglio, $particella)
    {
        $comune = strtoupper($code_comune);

        $f = $foglio ?? '';
        $n = $particella ?? '';
        $s = '';

        $arrTabUnoDue = [];
        $arrProprietari = [];

        $sql = "SELECT INFO.id_mutazione_iniziale, INFO.id_mutazione_finale, id_immobile, INFO.progressivo, data_efficacia, data_efficacia1
    	FROM c_terr_info AS INFO WHERE cod_com='" . $comune . "'";

        $condPlla = '';
        if ($f != '') {
            $condPlla .= " AND foglio='$f'";
        }
        if ($n != '') {
            $n = \AppHelper::formatNumber($n, 5);
            $condPlla .= " AND numero='$n'";
        }
        if ($s != '') {
            $condPlla .= " AND sub='$s'";
        }
        if ($f == '' && $n == '' && $s == '') $condPlla .= " AND sub is null";

        $sql .= $condPlla . " ORDER BY INFO.id ASC";

        $elMut = DB::connection('pgsql2')->select($sql);

        $query = "select INFO.id_immobile,INFO.id as idinfo,foglio as fg, numero as nm, sub as sb,RIS.cod_riserva,PZ.id_porzione , regexp_replace(PZ.classe, '0*', '') as classe_pz, PZ.ettari as ettari_pz, PZ.are as are_pz, PZ.centiare as centiare_pz,
            PZ.reddito_dom_euro as reddito_dom_euro_pz,PZ.reddito_agr_euro as reddito_agr_euro_pz, PZ.qualita as qua_pz, (select descrizione from
            c_predefinito_codice_qualita where  codice=PZ.qualita::smallint) as desc_qua_pz, id_mutazione_iniziale, id_mutazione_finale,progressivo,
            data_efficacia,data_registrazione_atti,(select descrizione from c_predefinito_tipo_nota where tipo='T' and codice=tipo_nota)as tipo_nota,
            (select descrizione from c_predefinito_partite_speciali_catasto where tipo_catasto='T' and codice=INFO.partita)as partita,descrizione_atto_generante,
            regexp_replace(partita, '0*', '') as partitanum,
            regexp_replace(numero_nota, '0*', '')as numero_nota, regexp_replace(progressivo_nota, '0*', '')as progressivo_nota,anno_nota, INFO.qualita as qua,
            (select descrizione from c_predefinito_codice_qualita where  codice=INFO.qualita::smallint) as desc_qua,foglio, regexp_replace(numero, '0*', '')
            as numero, regexp_replace(sub, '0*', '')as sub, data_efficacia1,data_registrazione_atti1, (select descrizione from c_predefinito_tipo_nota where
            tipo='T' and codice=tipo_nota1)as tipo_nota1,regexp_replace(numero_nota1, '0*', '')as numero_nota1,regexp_replace(progressivo_nota, '0*', '')
            as prog_nota1,anno_nota1,regexp_replace(INFO.classe, '0*', '') as classe, INFO.ettari,INFO.are,INFO.centiare, CAST(INFO.reddito_dom_euro as numeric(9,2)), CAST(INFO.reddito_agr_euro as numeric(8,2)),
			INFO.reddito_dom_lire,INFO.reddito_agr_lire, annotazione, D.simbolo from c_terr_info as INFO left join c_terr_deduzioni D on D.id_terr_info=INFO.id left join c_terr_porzioni    
	        PZ on PZ.id_terr_info=INFO.id left join c_terr_riserve RIS on RIS.id_terr_info=INFO.id where INFO.cod_com='" . $comune . "'";


        //SITUAZIONI ATTUALI
        $c = count($elMut);

        for ($i = $c - 1; $i >= 0; $i--) {

            if ($elMut[$i]->data_efficacia == '1900-01-01') $query .= $condPlla;

            if ($elMut[$i]->id_mutazione_iniziale == '') $idMut = ' is null';
            else $idMut = "='" . $elMut[$i]->id_mutazione_iniziale . "'";

            if ($i == $c - 1) {
                $idMut1 = '';
            } else {
                if ($elMut[$i]->id_mutazione_finale == '')
                    $idMut1 = 'and INFO.id_mutazione_finale is null';
                else
                    $idMut1 = "and INFO.id_mutazione_finale='" . $elMut[$i]->id_mutazione_finale . "'";
            }

            $q2 = "$query and INFO.id_mutazione_iniziale $idMut $idMut1 order by progressivo desc, INFO.id";

            $res = DB::connection('pgsql2')->select($q2);

            //trova proprietari
            //modifica $res in base alla presenza di porzioni o meno
            $countRes = count($res);

            if ($countRes > 1) {
                for ($r = 0; $r < $countRes; $r++) {
                    $res[$r]->desc_qua = $res[$r]->desc_qua_pz;
                    $res[$r]->classe = $res[$r]->classe_pz;
                    $res[$r]->ha = $res[$r]->ettari_pz;
                    $res[$r]->a = $res[$r]->are_pz;
                    $res[$r]->ca = $res[$r]->centiare_pz;
                }
            }

            //trova uiu collegate, es.: in caso di soppressione dell'immobile
            $this->immobiliCollegatiRigaTabUnoCatasto($res, $f, $n, $s);
            $this->formattaDatiTabCatasto($arrTabUnoDue, $arrProprietari, $res, false, false, $comune);
        }

        $resFinale = $this->estraiUltimiProprietariNomi($arrProprietari);

        return $resFinale;
    }

    private function immobiliCollegatiRigaTabUnoCatasto(&$res, $f, $n, $s)
    {
        $cRes = count($res);
        $found = -1;
        $idImmobile = '';

        for ($a = 0; $a < $cRes; $a++) {
            if (($res[$a]->fg == $f && $res[$a]->nm == $n && $res[$a]->sb == $s)/*&& ($res[$a]['partitanum']=='C' || $res[$a]['partitanum']=='0')*/) {
                $idImmobile = $res[$a]->id_immobile;
                $found = $a;
            }
            if ($found !== -1)
                break;
        }

        //poi cerco eventuali id_immobili diversi....
        if ($idImmobile != '') {

            $strCollegati = '';
            for ($a = 0; $a < $cRes; $a++) {
                if ($res[$a]->id_immobile !== $idImmobile) {
                    $strCollegati .= ' foglio ' . $res[$a]->foglio . ' plla ' . $res[$a]->numero;
                    if ($res[$a]->sub != '')
                        $strCollegati .= ' sub ' . $res[$a]->sub;

                    $strCollegati .= ',';
                    //cancella collegati da $res	    
                    unset($res[$a]);
                }
            }
            if (strlen($strCollegati) > 0) $strCollegati = 'ha generato e/o modificato i seguenti immobili: ' . substr($strCollegati, 0, -1);

            $res[$found]->collegati = $strCollegati;
        }
    }

    private function formattaDatiTabCatasto(&$arrTabUnoDue, &$arrProprietari, &$res, $precedente, $tipoFabbricati, $comune)
    {
        if ($res) {
            foreach ($res as $elm) {
                $primaRiga = false;
                $key = 'id' . $elm->idinfo;
                $arrTabUnoDue[$key][1] = $this->formattaRigaTabUnoCatasto($elm, $precedente);
                if (!isset($arrTabUnoDue[$key][2]) || !is_array($arrTabUnoDue[$key][2])) {
                    $primaRiga = true;
                    $arrTabUnoDue[$key][2] = [];
                }
                array_push($arrTabUnoDue[$key][2], $this->formattaRigaTabDueCatasto($elm, $primaRiga, $tipoFabbricati));

                //trova proprietari
                //aggiungi elemento all'array di uiu:
                if ($primaRiga) {
                    $keyUiu = 'f' . $elm->foglio . 'n' . $elm->numero . 's' . $elm->sub;
                    if (!array_key_exists($keyUiu, $arrProprietari)) {
                        $arrProprietari[$keyUiu] = array();
                        $this->elencoProprietariCatasto($arrProprietari, $keyUiu, $elm->fg, $elm->nm, $elm->sb, $tipoFabbricati, $comune);
                    }
                }
                $primaRiga = false;
            }
        }
    }

    private function estraiUltimiProprietariNomi(array $arrProprietari): array
    {
        $risultato = [];
    
        foreach ($arrProprietari as $chiaveUiu => $mutazioni) {
            if (!empty($mutazioni)) {
                // Prende l'id_mutaz della prima mutazione
                $idMutazioneUltima = $mutazioni[0]['id_mutaz'];
    
                foreach ($mutazioni as $mutazione) {
                    // Se ha la stessa id_mutaz, è uno degli ultimi proprietari
                    if ($mutazione['id_mutaz'] === $idMutazioneUltima && !empty($mutazione['prop'])) {
                        foreach ($mutazione['prop'] as $proprietario) {
                            if (!empty($proprietario['pers'])) {
                                // Pulisce rimuovendo "nato ..." e tutto dopo
                                $nomeCognome = preg_replace('/\s+nato.*$/i', '', $proprietario['pers']);
                                $risultato[] = trim($nomeCognome);
                            }
                        }
                    } else {
                        // Appena cambia id_mutaz, fermati
                        break;
                    }
                }
            }
        }
    
        return $risultato;
    }

    private function formattaRigaTabUnoCatasto(&$arr, $prec)
    {

        //formattazione stringhe per tab1...
        $rigaTabUno = array();
        if ($arr->data_efficacia != '1900-01-01')
            $rigaTabUno['data_efficacia'] = $arr->data_efficacia;
        if ($arr->data_registrazione_atti != '1900-01-01')
            $rigaTabUno['data_registrazione_atti'] = $arr->data_registrazione_atti;
        if (isset($arr->numero_nota) && $arr->numero_nota != '') {
            if ($arr->numero_nota != '')
                $arr->numero_nota = ' num.: ' . $arr->numero_nota;
            if ($arr->progressivo_nota != '')
                $arr->numero_nota .= '.' . $arr->progressivo_nota;
            if ($arr->anno_nota != '' && $arr->anno_nota != '0')
                $arr->numero_nota .= '/' . $arr->anno_nota;
            if (isset($rigaTabUno['data_registrazione_atti']))
                $arr->numero_nota .= ' in atti dal ' . $rigaTabUno['data_registrazione_atti'];
        }

        if (isset($arr->protocollo_notifica) && $arr->protocollo_notifica != '') {
            $rigaTabUno['prot_not'] = '(prot. num:' . $arr->protocollo_notifica;
            if ($arr->data_notifica != '')
                $rigaTabUno['prot_not'] .= ' ' . $arr->data_notifica;
            $rigaTabUno['prot_not'] .= ')';
        }

        if ($arr->data_efficacia1 != '1900-01-01')
            $rigaTabUno['data_efficacia1'] = $arr->data_efficacia1;
        if ($arr->data_registrazione_atti1 != '1900-01-01')
            $rigaTabUno['data_registrazione_atti1'] = $arr->data_registrazione_atti1;


        $rigaTabUno['progressivo'] = $arr->progressivo;

        if (isset($arr->cod_atto_generante) && $arr->cod_atto_generante != '') $rigaTabUno['descDati'] = $arr->cod_atto_generante;
        else $rigaTabUno['descDati'] = strtoupper($arr->tipo_nota);
        if (isset($rigaTabUno['data_efficacia'])) $rigaTabUno['descDati'] .= ' del ' . $rigaTabUno['data_efficacia'];
        $rigaTabUno['descDati'] .= $arr->numero_nota;

        if (isset($arr->protocollo_notifica) && $arr->protocollo_notifica != '') {
            $rigaTabUno['descDati'] .= '(prot. num:' . $arr->protocollo_notifica;
            if ($arr->data_notifica != '')
                $rigaTabUno['descDati'] .= ' ' . $arr->data_notifica;
            $rigaTabUno['descDati'] .= ')';
        }
        $rigaTabUno['descDati'] .= ' ' . $arr->descrizione_atto_generante;

        $rigaTabUno['annotazione'] = $arr->annotazione;

        if ($prec) {
            //array_push($rigaTabUno, 'situazione precedente');
            $rigaTabUno['descDati'] = '(SITUAZIONE PRECEDENTE) ' . $rigaTabUno['descDati'];
        }

        $rigaTabUno['coll'] = $arr->collegati ?? '';

        return $rigaTabUno;
    }

    private function formattaRigaTabDueCatasto(&$row, $primaRiga, $tipoFabbricati)
    {
        $rigaTabDue = array();

        if ($tipoFabbricati) {
            if ($primaRiga) {
                if ($row->cat != '') {
                    $rigaTabDue['cat1'] = $row->cat;
                    $row->cat .= "-" . $row->desc_cat;
                    if ($row->consistenza != '') {
                        $ch = substr($row->cat, 0, 1);
                        if ($ch == 'A')
                            $row->consistenza .= " vani";
                        else if ($ch == 'B')
                            $row->consistenza .= " mq"; //quadri
                        else if ($ch == 'C')
                            $row->consistenza .= " mc"; //cubi
                    }
                }

                $rigaTabDue['progressivo'] = $row->progressivo;
                $rigaTabDue['cat'] = $row->cat;
                $rigaTabDue['classe'] = $row->classe;
                $rigaTabDue['consistenza'] = $row->consistenza;
                $rigaTabDue['superficie'] = $row->superficie;
                $rigaTabDue['rendita_euro'] = $row->rendita_euro;
                $rigaTabDue['rendita_lire'] = $row->rendita_lire;
                $rigaTabDue['prt'] = $row->prt;

                if ($row->indirizzo != '') {
                    $rigaTabDue['indirizzo'] = $row->nome_toponimo . " " . $row->indirizzo . " " . $row->civico1 . " " . $row->civico2 . " " . $row->civico3;
                    if ($row->piano1 != '' || $row->piano2 != '' || $row->piano3 != '' || $row->piano4 != '')
                        $rigaTabDue['indirizzo'] .= ' piano ' . $row->piano1 . ' ' . $row->piano2 . $row->piano3 . ' ' . $row->piano4;
                    if ($row->scala != '')
                        $rigaTabDue['indirizzo'] .= ' scala ' . $row->scala;
                    if ($row->edificio != '')
                        $rigaTabDue['indirizzo'] .= ' edificio ' . $row->edificio;
                    if ($row->lotto != '')
                        $rigaTabDue['indirizzo'] .= ' lotto ' . $row->lotto;
                }
                if ($row->partita != '')
                    $rigaTabDue['partita'] = $row->partita;
                else
                    $rigaTabDue['partita'] = $row->partitanum;
            } else {
                if ($row->partita != '')
                    $rigaTabDue['partita'] = $row->partita;
                else {
                    $rigaTabDue['partita'] = $row->partitanum;
                }
            }
            $rigaTabDue['foglio'] = $row->foglio;
            $rigaTabDue['numero'] = $row->numero;
            $rigaTabDue['sub'] = $row->sub;
        } else //tipo terreni
        {
            if ($primaRiga) {
                $rigaTabDue['foglio'] = $row->foglio;
                $rigaTabDue['numero'] = $row->numero;
                $rigaTabDue['sub'] = $row->sub;
                $rigaTabDue['qua'] = $row->desc_qua;
                $rigaTabDue['domeuro'] = $row->reddito_dom_euro;
                $rigaTabDue['domlire'] = $row->reddito_dom_lire;
                $rigaTabDue['agreuro'] = $row->reddito_agr_euro;
                $rigaTabDue['agrlire'] = $row->reddito_agr_lire;
            }
            $rigaTabDue['ris'] = $row->cod_riserva;
            $rigaTabDue['pz'] = $row->id_porzione;
            $rigaTabDue['qua'] = $row->desc_qua;
            $rigaTabDue['ha'] = $row->ettari;
            $rigaTabDue['a'] = $row->are;
            $rigaTabDue['ca'] = $row->centiare;
            $rigaTabDue['cl'] = $row->classe;
            $rigaTabDue['deduzioni'] = $row->simbolo;

            if ($row->partita != '')
                $rigaTabDue['partita'] = $row->partita;
            else {
                $rigaTabDue['partita'] = $row->partitanum;
            }
        }
        //key per tab 3
        $rigaTabDue['k'] = 'f' . $row->foglio . 'n' . $row->numero . 's' . $row->sub;

        return $rigaTabDue;
    }

    function elencoProprietariCatasto(&$arrProprietari, $key, $f, $n, $s, $tipoFabbricati, $comune)
    {
        $condUiu = '';
        if ($f != '')
            $condUiu .= " AND foglio='$f'";
        if ($n != '')
            $condUiu .= " AND numero='$n'";
        if ($s != '')
            $condUiu .= " AND sub='$s'";
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

        $dataFinoAl = '';
        $finoAl = '';
        for ($j = 0; $j < $c; $j++) {

            //per la data fino al...
            if ($j > 0 && $res[$j]->datavaltit != $res[$j - 1]->datavaltit) {
                $dataFinoAl = $res[$j - 1]->datavaltit;
                $finoAl = $dataFinoAl;
            }

            $found = false;
            for ($i = 0; $i < count($arrProprietari[$key]); $i++) {
                if (isset($arrProprietari[$key][$i]->id_mutaz)) {
                    if ($res[$j]->id_mutazione_iniziale . $res[$j]->id_mutazione_finale == $arrProprietari[$key][$i]->id_mutaz) {
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                //formatta dati_derivanti_da
                $ddd = $res[$j]->cod_atto;
                if (isset($res[$j]->datavaltit) && $res[$j]->datavaltit != '' && $res[$j]->datavaltit != '1900-01-01')
                    $ddd .= ' del ' . $res[$j]->datavaltit;
                if (isset($res[$j]->numero_nota) && $res[$j]->numero_nota != '') {
                    $ddd .= ' Nota num.: ' . $res[$j]->numero_nota;
                }
                if (isset($res[$j]->progressivo_nota) && $res[$j]->progressivo_nota != '')
                    $ddd .= '.' . $res[$j]->progressivo_nota;
                if (isset($res[$j]->anno_nota) && $res[$j]->anno_nota != '' && $res[$j]->anno_nota != '0')
                    $ddd .= '/' . $res[$j]->anno_nota;
                if (isset($res[$j]->data_registrazione) && $res[$j]->data_registrazione)
                    $ddd .= ' in atti dal ' . $res[$j]->data_registrazione;
                if (isset($res[$j]->att_gen) && $res[$j]->att_gen != '')
                    $ddd .= ' - ' . $res[$j]->att_gen;

                $de = $res[$j]->data_efficacia ?? '';
                if ($de == '' || $de == '1900-01-01') $de = '';

                array_push($arrProprietari[$key], array(
                    'id_mutaz' => $res[$j]->id_mutazione_iniziale . $res[$j]->id_mutazione_finale,
                    'dataval' => $res[$j]->datavaltit,
                    'desc' => $ddd,
                    'data_efficacia' => $de,
                    'prop' => array(0 => $this->formattaRigaProprietarioCatasto($res[$j], $finoAl))
                ));
            } else {
                if (isset($arrProprietari[$key][$i]->prop)) array_push($arrProprietari[$key][$i]->prop ?? [], $this->formattaRigaProprietarioCatasto($res[$j], $finoAl));
            }
        }
    }

    private function formattaRigaProprietarioCatasto($row, $finoAl)
    {
        $res = array();
        //  $res->data=$row->pf;
        if (intval($row->pf) === 1) //persona fisica
        {
            $res['pers'] = $res['pers1'] = strtoupper($row->cognome) . ' ' . strtoupper($row->nome);
            if ($row->luogo_nascita != '') {
                if (strpos($row->sesso, '1') !== false) {
                    $res['pers'] .= ' nato a ';
                    $res['pers1'] .= ' nato a ';
                } else {
                    $res['pers'] .= ' nata a ';
                    $res['pers1'] .= ' nato a ';
                }
                $res['pers1'] .= $row->luogo_nascita . ' (' . $row->pv_nascita . ')';
                $res['pers'] .= $row->luogo_nascita . ' (' . $row->pv_nascita . ')';
            }
            if ($row->data_nascita) {
                $res['pers'] .= ' il ';
                $res['pers1'] .= ' il ';
            }
            $res['pers'] .= $row->data_nascita;
            $res['pers1'] .= $row->data_nascita;
            if ($row->cf != '')
                $res['pers'] .= ' - C.F.: ' . $row->cf;
            $res['perscf'] = $row->cf;
        } else //persona giuridic
        {
            $res['pers'] = $res['pers1'] = strtoupper($row->denominazione);
            if ($row->cf != '') $res['pers'] .= ' - p. I.V.A.: ' . $row->cf;
            $res['perscf'] = $row->cf;
        }

        if ($row->diritto != '')
            $res['titolo'] = $row->diritto;
        else
            $res['titolo'] = $row->titolo_non_codificato;
        if ($row->quota_num != '') {
            if ($finoAl != '')
                $res['titolo'] .= ' ' . $row->quota_num . '/' . $row->quota_den . ' fino al ' . $finoAl;
            else
                $res['titolo'] .= ' ' . $row->quota_num . '/' . $row->quota_den;
        }

        return $res;
    }

    private function elencoMutazioniCatastoFabbricati(Request $request, $bool_print = false)
    {
        $comune = strtoupper($request->code_comune);

        $f = $request->foglio ?? '';
        $n = $request->particella ?? '';
        $s = $request->sub ?? '';

        $arrProprietari = [];
        $arrTabUnoDue = [];


        $q = "select INFO.id_mutazione_iniziale, INFO.id_mutazione_finale, id_immobile, INFO.progressivo, data_efficacia, data_efficacia1
		from c_fabb_info INFO left join c_fabb_identificativi IDN on INFO.id=IDN.id_fabb_info where cod_com='" . $comune . "'";

        $condPlla = '';
        if ($f != '') {
            $f = \AppHelper::formatNumber($f, 4);
            $condPlla .= " AND foglio='$f'";
        }
        if ($n != '') {
            $n = \AppHelper::formatNumber($n, 5);
            $condPlla .= " AND numero='$n'";
        }
        if ($s != '') {
            $s = \AppHelper::formatNumber($s, 4);
            $condPlla .= " AND sub='$s'";
        }
        if ($f == '' && $n == '' && $s == '') $condPlla .= " AND sub is null";

        $q .= $condPlla . ' ORDER BY INFO.id ASC';

        $elMut = DB::connection('pgsql2')->select($q);

        $query = "SELECT INFO.id_immobile,foglio as fg, numero as nm, sub as sb,sezione,zona,partita as prt,INFO.id as idinfo,sezione,id_mutazione_iniziale, id_mutazione_finale, progressivo,data_efficacia,data_registrazione_atti,
			data_efficacia1,data_registrazione_atti1,flag_classamento,annotazione,(select descrizione from c_predefinito_tipo_nota where tipo='F' and codice=tipo_nota)as tipo_nota,descrizione_atto_generante,
			regexp_replace(numero_nota, '0*', '')as numero_nota,regexp_replace(progressivo_nota, '0*', '') as progressivo_nota,anno_nota,(select descrizione from c_predefinito_tipo_nota where tipo='F' and codice=tipo_nota1)as tipo_nota1,
			numero_nota1,progressivo_nota1,anno_nota1,(select descrizione from c_predefinito_partite_speciali_catasto where tipo_catasto='F' and codice=INFO.partita)as partita,
			regexp_replace(partita, '0*', '') as partitanum, regexp_replace(foglio, '0*', '')as foglio,regexp_replace(numero, '0*', '')as numero, regexp_replace(sub, '0*', '')as sub,cat,classe,consistenza,
			CAST(rendita_euro as numeric(18,2)),CAST(rendita_lire as numeric(15,2)),superficie,(select descrizione from c_predefinito_categorie_catastali where categoria=cat) as desc_cat,IND.indirizzo,
			(select descrizione from c_predefinito_codici_toponimo where codice=toponimo) as nome_toponimo,regexp_replace(civico1, '0*', '')as civico1,
			regexp_replace(civico2, '0*', '')as civico2,regexp_replace(civico3, '0*', '')as civico3,piano1,piano2,piano3,piano4,scala,lotto,edificio,
			protocollo_notifica, data_notifica,(SELECT descrizione FROM c_predefinito_codici_causale where codice=codice_causale_atto_generante)
            as cod_atto_generante, descrizione_atto_generante FROM c_fabb_info INFO left join c_fabb_identificativi IDN on INFO.id=IDN.id_fabb_info
            LEFT JOIN c_fabb_indirizzi IND on INFO.id=IND.id_fabb_info WHERE INFO.cod_com='" . $comune . "'";


        //SITUAZIONI ATTUALI + EVENTUALI GRAFFATI
        $c = count($elMut);
        for ($i = $c - 1; $i >= 0; $i--) {

            if ($elMut[$i]->id_mutazione_iniziale == '') $idMut = ' is null';
            else $idMut = "='" . $elMut[$i]->id_mutazione_iniziale . "'";

            if ($i == $c - 1) $idMut1 = '';
            else {
                if ($elMut[$i]->id_mutazione_finale == '') $idMut1 = 'and INFO.id_mutazione_finale is null';
                else $idMut1 = "and INFO.id_mutazione_finale='" . $elMut[$i]->id_mutazione_finale . "'";
            }

            $q2 = "$query and INFO.id_mutazione_iniziale $idMut $idMut1 order by progressivo desc, INFO.id ";
            $res = DB::connection('pgsql2')->select($q2);

            //trova uiu collegate,
            $this->immobiliCollegatiRigaTabUnoCatasto($res, $f, $n, $s);
            $this->formattaDatiTabCatasto($arrTabUnoDue, $arrProprietari, $res, false, true, $comune);
        }
        //FINE SITUAZIONI ATTUALI

        //EVENTUALI SITUAZIONI ANTECEDENTI  
        if (sizeof($elMut) > 0) {
            $q1 = "$query and INFO.id_mutazione_finale='" . $elMut[0]->id_mutazione_iniziale . "' order by progressivo desc,  INFO.id ";
            $elMutPrec = DB::connection('pgsql2')->select($q1);

            if (sizeof($elMutPrec) > 0) {
                //mi serve prendere la uiu padre:
                $condPadre = " and foglio='" . $elMutPrec[0]->fg . "' and numero='" . $elMutPrec[0]->nm . "'";
                if ($elMutPrec[0]->sb != '') $condPadre .= " and sub='" . $elMutPrec[0]->sb . "'";

                //trova collegamenti in caso di soppressione dell'immobile
                $this->immobiliCollegatiRigaTabUnoCatasto($elMutPrec, $f, $n, $s);
                $this->formattaDatiTabCatasto($arrTabUnoDue, $arrProprietari, $elMutPrec, true, true, $comune);

                $nonTrovareFratelli = true;
                while (!empty($elMutPrec) && isset($elMutPrec[0]->id_mutazione_iniziale) && $elMutPrec[0]->id_mutazione_iniziale != '9999999') {
                    $q1 = "$query and INFO.id_mutazione_finale='" . $elMutPrec[0]->id_mutazione_iniziale . "'";
                    if ($nonTrovareFratelli)
                        $q1 .= " $condPadre order by progressivo desc, INFO.id";
                    else
                        $q1 .= " order by progressivo desc, INFO.id";

                    $nonTrovareFratelli = false;

                    $elMutPrec =  DB::connection('pgsql2')->select($q1);

                    //trova collegamenti in caso di soppressione dell'immobile
                    $this->immobiliCollegatiRigaTabUnoCatasto($elMutPrec, $f, $n, $s);
                    $this->formattaDatiTabCatasto($arrTabUnoDue, $arrProprietari, $elMutPrec, true, false, $comune);
                }
            }
        }
        //FINE EVENTUALI SITUAZIONI ANTECEDENTI     

        $resFinale = array();
        $resFinale[0] = $arrTabUnoDue;
        $resFinale[1] = $arrProprietari;

        if (!$bool_print) return response()->json($resFinale);
        else return $resFinale;
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
