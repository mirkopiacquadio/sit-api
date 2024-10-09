<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CDUController extends Controller
{
    private $dbConnection;
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
        "L739" => 'venticano-webgis',
        "D756" => 'fragnetomonforte-webgis',
        "F113" => 'melizzano-webgis',
        "C250" => 'castelpoto-webgis',
        "F113" => 'melizzano-webgis',
        "G386" => 'paupisi-webgis',
        "H087" => 'puglianello-webgis'
    );

    //costruttore privato per il singleton
    private function setDB($code_comune)
    {
        $code_comune = strtoupper($code_comune);

        if (array_key_exists($code_comune, $this->nomiDb)) {
            // Ottieni il nome del database dal codice del comune
            $dbn = $this->nomiDb[$code_comune];

            // Configura la connessione al database
            DB::purge('pgsql'); // Pulisce la connessione precedente
            config(['database.connections.pgsql.database' => $dbn]);
            DB::reconnect('pgsql'); // Riconnette con il nuovo nome di database

        } else {
            // Gestione dell'errore se il codice del comune non esiste nell'array nomiDb
            throw new \Exception("Codice comune non valido: $code_comune");
        }

        
        // Eseguire la query per ottenere l'elenco dei fogli
        $q = 'select replace(tablename, \'utm\', \'\') as nm from pg_tables where tablename like \'' . $code_comune . '%\' and tablename like \'%utm\' order by tablename';
        //echo $q;
        $code_comune = strtolower($code_comune);
  
        $q = "SELECT table_name as nm FROM information_schema.tables WHERE table_name LIKE '%utm' AND table_name LIKE '" . $code_comune . "%' ORDER BY table_name;";
        $res = DB::select($q);

        // Inizializzare l'array $elencoFg con i risultati della query
        unset($elencoFg);
        foreach ($res as $row) {
            $this->elencoFg[] = substr($row->nm, 4);
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
    public function elPiani(Request $request)
    {
        $this->setDB(strtoupper($request->code_comune));
        $query = "select replace(tablename,'urbutm','') as nm from pg_tables where tablename like '%urbutm' order by tablename";
        $res = DB::select($query);
        return $res;
    }

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
                $this->calcolaCdu(7, 488, $piano.'urbutm', $code_comune); //MIRKOOOO
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
        $decimali = $request->input('cdusetdecimals') == '1' ? 0 : ($request->input('cdusetdecimals') == '2' ? 1 : 2);
        $approx = $request->input('cdusetdecimals') == '1' && $request->has('cdusetapprox');

        $piani = $request->piano;

        /*$keys = array_keys($post);
        $count = count($post);
        for ($i = 0; $i < $count; $i++) {
            if (strpos($keys[$i], 'cdup-') !== false) {
                $s = substr($keys[$i], 5);
                array_push($piani, $s);
            }
        }*/

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

        $content = \AppHelper::formattaCdu($post, $uiu, $norme, $comune);
        
        if ($content !== null) {
            $nomeFile = mt_rand();

            // Percorso per il file temporaneo HTML
            $tempHtmlPath = storage_path('app/'.strtoupper($comune).'/tmp/' . $nomeFile . '.html');

            // Crea la directory se non esiste
            File::makeDirectory(dirname($tempHtmlPath), 0755, true, true);

            // Crea il file temporaneo HTML
            File::put($tempHtmlPath, $content);

            // Verifica se il file è stato creato correttamente
            if (File::exists($tempHtmlPath)) {
                // Percorso per il file di output Word
                $outputWordPath = storage_path('app/'.strtoupper($comune).'/documenti/' . $nomeFile . '.doc');

                // Esegui la conversione utilizzando LibreOffice
                exec('"C:\Program Files\LibreOffice\program\soffice.bin" --convert-to "doc:MS Word 97" --outdir ' . storage_path('app/'.strtoupper($comune).'/documenti/') . ' ' . $tempHtmlPath);

                // Verifica se il file Word è stato creato correttamente

                // Cancella il file temporaneo .html
                File::delete($tempHtmlPath);

                // Ritorna il file Word come risposta HTTP
                return response()->file($outputWordPath);
            } else {
                // Se la creazione del file HTML temporaneo ha fallito, ritorna un messaggio di errore
                return response()->json(['error' => 'Failed to create temporary HTML file'], 500);
            }
        }
    }

    public function generaCDUHtml(Request $request)
    {
        $this->setDB(strtoupper($request->code_comune));
        $comune = strtoupper($request->code_comune);
        $post = $request->all();

        $visMq = $request->has('cdusetmq');
        $visPerc = $request->has('cdusetperc');
        $decimali = $request->input('cdusetdecimals') == '1' ? 0 : ($request->input('cdusetdecimals') == '2' ? 1 : 2);
        $approx = $request->input('cdusetdecimals') == '1' && $request->has('cdusetapprox');

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

                if (!$ress){
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
        $vista = view('table', compact('data_uiu'))->render();

        return ['vista' => $vista, 'mq' => $data_uiu['mq']];
    }

    function print_cdu_from_modal(Request $request) {
        $this->setDB(strtoupper($request->code_comune));
        $comune = strtoupper($request->code_comune);
        $post = $request->all();

        $visMq = $request->has('cdusetmq');
        $visPerc = $request->has('cdusetperc');
        $decimali = $request->input('cdusetdecimals') == '1' ? 0 : ($request->input('cdusetdecimals') == '2' ? 1 : 2);
        $approx = $request->input('cdusetdecimals') == '1' && $request->has('cdusetapprox');

        $piani = json_decode($request->piano);
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

                if (!$ress){
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

        $html = view('table_email', compact('data_uiu', 'elUiu'));

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

    private static function calcolaValoreCdu(&$aisect, &$mq, &$auiu, &$perc, &$cifreDecimali, &$approssimazione, &$visPerc, &$visMq)
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
