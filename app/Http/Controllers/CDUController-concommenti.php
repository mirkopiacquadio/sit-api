<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CDUController extends Controller
{
    const COD_COMUNE = "D469";

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
        "C250" => 'castelpoto-webgis',
        "F113" => 'melizzano-webgis',
        "G386" => 'paupisi-webgis',
        "H087" => 'puglianello-webgis'
    );

    //costruttore privato per il singleton
    private function setDB()
    {
        $comu = SELf::COD_COMUNE;
        $dbn = $this->nomiDb[$comu];
        if (array_key_exists($comu, $this->nomiDb)) {
            // Ottieni il nome del database dal codice del comune
            $dbn = $this->nomiDb[$comu];

            // Configura la connessione al database
            $this->dbConnection = DB::connection('pgsql')->setDatabaseName($dbn);
        } else {
            // Gestione dell'errore se il codice del comune non esiste nell'array nomiDb
            // Puoi lanciare un'eccezione, registrare un errore, ecc.
            exit;
        }

        // Eseguire la query per ottenere l'elenco dei fogli
        $query = "select replace(tablename, 'utm', '') as nm from pg_tables where tablename like ? and tablename like ?";
        $res = DB::connection('pgsql2')->select($query, [SELF::COD_COMUNE . '%', '%utm']);

        // Inizializzare l'array $elencoFg con i risultati della query
        unset($elencoFg);
        foreach ($res as $row) {
            $this->elencoFg[] = substr($row->nm, 4);
        }
    }

    function selectPoligonoUiuCat($tabella, $fg, $nm, $tipo)
    {
        //possibile risultato non univoco!!!
        $this->setDB();
        $res = DB::connection('pgsql2')->table($tabella)
            ->select('oid')
            ->where('FOGLIO', '=', $fg)
            ->where('PARTICELLA', '=', $nm)
            ->where('TIPOLOGIA', '=', $tipo)
            ->get();

        return $res;
    }

    function selectPoligonoUiuUrb($tabella, $nome)
    {
        //possibile risultato non univoco!!!

        $this->setDB();
        $res = DB::connection('pgsql2')->table($tabella)
            ->select('oid')
            ->where('STRING', '=', $nome)
            ->get();

        return $res;
    }


    /********************************URBANISTICA********************************************/

    public function elPiani()
    {
        $this->setDB();
        $query = "select replace(tablename,'urbutm','') as nm from pg_tables where tablename like '%urbutm' order by tablename";
        return DB::connection('pgsql2')->select($query);
    }

    public function elencoNormePiani($tabella)
    {
        $this->setDB();
        $query = "select distinct initcap(replace(\"LAYER\",'_', ' ')) as \"LAYER\",\"STRING\" as nm from $tabella order by \"LAYER\"";
        return DB::connection('pgsql2')->select($query);
    }

    /* function elencoFogli()
    {
        $q = 'select replace(tablename, \'utm\', \'\') as nm from pg_tables where tablename like \'' . $this->codCom . '%\' and tablename like \'%utm\' order by tablename';
        //echo $q;
        $res = pg_query($this->dbA, $q);
        $resArr = array();
        while ($row = pg_fetch_array($res, NULL, PGSQL_ASSOC)) {
            array_push($this->elencoFg, substr($row['nm'], 4));
        }
    } */

    //trova le intersezioi con tutti i piani urbanistici..
    public function intersezioniPianiUrbanistici($table, $oid)
    {
        $this->setDB();

        // Elenco dei piani
        $q = "select table_name  from information_schema.tables where table_name like '%urbutm%' order by table_name";
        $res = DB::connection('pgsql2')->select($q);
        $intNulla = true;
        $arrRes = array();

        foreach ($res as $row) {
            $piano = substr($row->table_name, 0, -6);

            $q = "SELECT \"LAYER\",\"STRING\",\"FOGLIO\", \"PARTICELLA\",\"TIPOLOGIA\",tt.auiu as auiu, sum(tt.perc) as perc, sum(tt.aisect) as aisect FROM(
            SELECT \"LAYER\",\"STRING\",\"FOGLIO\", \"PARTICELLA\",\"TIPOLOGIA\",
            round(cast(st_area(a.the_geom) as numeric), 3) as auiu, round(cast(st_area(ST_Intersection(a.the_geom, b.the_geom)) as numeric), 3) as aisect,
            round(cast(st_area(ST_Intersection(a.the_geom, b.the_geom)) * 100 / st_area(a.the_geom) as numeric), 2) as perc from $table a
            inner join $row->table_name b ON ST_Intersects(a.the_geom, b.the_geom) where a.oid=?
        ) as tt  group by tt.\"LAYER\",\"FOGLIO\", \"PARTICELLA\",\"TIPOLOGIA\",tt.\"STRING\", tt.auiu ORDER BY \"LAYER\"";

            $res1 = DB::select($q, [$oid]);

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

        if (!$intNulla) {
            return $arrRes;
        } else {
            return false;
        }
    }
    /***************************FINE URBANISTICA*********************************************/



    /*********************** CALCOLO CDU **********************************/
    public function calcolaCdu($foglio, $numero, $piano)
    {
        $this->setDB();
        $fg = $foglio;
        if ($fg != '') {
            $fg = \AppHelper::formatNumber($fg, 3);
        }

        if (in_array($fg, $this->elencoFg)) {
            $query = DB::table(SELF::COD_COMUNE . $fg . 'utm as a')
                ->select(
                    'tt.LAYER',
                    'tt.STRING',
                    DB::raw('round(cast(st_area(a.the_geom) as numeric), 3) as auiu'),
                    DB::raw('round(cast(st_area(ST_Intersection(a.the_geom, b.the_geom)) as numeric), 3) as aisect'),
                    DB::raw('round(cast(st_area(ST_Intersection(a.the_geom, b.the_geom)) * 100 / st_area(a.the_geom) as numeric), 2) as perc')
                )
                ->join($piano . 'urbutm as b', function ($join) {
                    $join->on(DB::raw('ST_Intersects(a.the_geom, b.the_geom)'));
                })
                ->where('a.FOGLIO', $foglio)
                ->where('a.PARTICELLA', $numero)
                ->where('a.TIPOLOGIA', 'PARTICELLA')
                ->groupBy('tt.LAYER', 'tt.STRING', 'tt.auiu')
                ->orderBy('tt.LAYER');

            // Esegui la query e restituisci i risultati
            return $query->get();
        }

        return null;
    }
    /************************ FINE CALCOLO CDU ****************************/
}
