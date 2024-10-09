<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatastoImmobileController extends Controller
{
    
    function selectFgPllaSubCatasto(Request $request)
    {
        $comune = strtoupper($request->code_comune);

        $f = $request->foglio ?? '';
        $n = $request->particella ?? '';
        $s = $request->sub ?? '';
        $tipo = $request->tipo ?? null;
        $arr = [];


        if ($tipo == null || $tipo == 'ter') {
            $sql = "SELECT DISTINCT ON (P.foglio, P.numero, P.sub) P.id, P.foglio, regexp_replace(P.numero, '0*', '') AS numero, regexp_replace(P.sub, '0*', '') AS sub, 'terreno' AS tipologia, PCQ.descrizione AS catqua FROM (SELECT L1.* FROM c_terr_info AS L1  INNER JOIN (SELECT id_immobile, MAX(id) AS max_id FROM c_terr_info WHERE cod_com='" . $comune . "'";

            if ($f != '') {
                $sql .= " AND foglio='" . $f . "'";
            }

            if ($n != '') {
                $n = \AppHelper::formatNumber($n, 5);
                $sql .= " AND numero='" . $n . "'";
            }

            if ($s != '') {
                $sql .= " AND sub='" . $s . "'";
            }

            $sql .= " GROUP BY id_immobile) AS L2 ON L1.id = L2.max_id AND L1.id_immobile = L2.id_immobile) AS P LEFT JOIN c_predefinito_codice_qualita AS PCQ ON P.qualita::smallint = PCQ.codice ORDER BY P.foglio, P.numero, P.sub";

            $res = DB::connection('pgsql2')->select($sql);

            $arr[] = $res;
        }

        if ($tipo == null || $tipo == 'fab') {
            unset($sql);

            $sql = "SELECT DISTINCT ON(IDN.foglio, IDN.numero, IDN.sub)
            IDN.id_fabb_info AS id,
            regexp_replace(IDN.foglio, '0*', '') AS foglio,
            regexp_replace(IDN.numero, '0*', '') AS numero,
            regexp_replace(IDN.sub, '0*', '') AS sub,
            'fabbricato' AS tipologia, 
            cat AS catqua
            FROM c_fabb_info AS L1 
            INNER JOIN
            (SELECT id_immobile, MAX(id) AS max_id FROM c_fabb_info
            WHERE cod_com='" . $comune . "' 
            GROUP BY id_immobile) AS L2 ON L1.id = L2.max_id AND L1.id_immobile = L1.id_immobile
            JOIN c_fabb_identificativi AS IDN ON IDN.id_fabb_info = L1.id
            WHERE 1=1";

            if ($f != '') {
                $f = \AppHelper::formatNumber($f, 4);
                $sql .= " AND IDN.foglio='" . $f . "'";
            }

            if ($n != '') {
                $n = \AppHelper::formatNumber($n, 5);
                $sql .= " AND IDN.numero='" . $n . "'";
            }

            if ($s != '') {
                $s = \AppHelper::formatNumber($s, 4);
                $sql .= " AND IDN.sub='" . $s . "'";
            }

            $sql .= " ORDER BY IDN.foglio, IDN.numero, IDN.sub";

            $res = DB::connection('pgsql2')->select($sql);
            $arr[] = $res;
        }

        return response()->json($arr);
    }

    function elencoMutazioniCatastoTerreni(Request $request, $bool_print = false)
    {
        $comune = strtoupper($request->code_comune);

        $f = $request->foglio ?? '';
        $n = $request->particella ?? '';
        $s = $request->sub ?? '';

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

        $sql .= $condPlla . ' ORDER BY INFO.id ASC';

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


        //SITUAZIONI ATTUALI + EVENTUALI PORZIONI
        // $resMut = array();
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

                    //4 RIGHE SEGG. NON PRESENTI NEI TER FAB TIT SOG
                    // 	$res[$r]['reddito_agr_euro']=$res[$r]['reddito_agr_euro_pz'];
                    // 	$res[$r]['reddito_agr_lire']=$res[$r]['reddito_agr_lire_pz'];	
                    // 	$res[$r]['reddito_dom_euro']=$res[$r]['reddito_dom_euro_pz'];
                    // 	$res[$r]['reddito_dom_lire']=$res[$r]['reddito_dom_lire_pz'];		

                }
            }

            //trova uiu collegate, es.: in caso di soppressione dell'immobile
            $this->immobiliCollegatiRigaTabUnoCatasto($res, $f, $n, $s);
            $this->formattaDatiTabCatasto($arrTabUnoDue, $arrProprietari, $res, false, false, $comune);
        }

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
                $this->formattaDatiTabCatasto($arrTabUnoDue, $arrProprietari, $elMutPrec, true, false, $comune);

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
            //FINE EVENTUALI SITUAZIONI ANTECEDENTI   
        }

        $resFinale = [];
        $resFinale[0] = $arrTabUnoDue;
        $resFinale[1] = $arrProprietari;

        if (!$bool_print) return response()->json($resFinale);
        else return $resFinale;
    }

    function elencoMutazioniCatastoFabbricati(Request $request, $bool_print = false)
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


    /**RICERCA AVANZATA */
    function selectPersoneFisicheCatasto(Request $request)
    {
        $comune = strtoupper($request->code_comune);

        $cat = $request->cat ?? '';
        $qua = $request->qua ?? '';
        $cf = strtoupper($request->cf);

        $q = 'select distinct on(F.id, cognome, nome, data_nascita, luogo_nascita, cf, indicazioni)F.id, cognome, nome, data_nascita, (select descrizione from c_predefinito_lista_comuni where codice_catastale=luogo_nascita) as desc_l_nas,(select pv from c_predefinito_lista_comuni where codice_catastale=luogo_nascita) as pv_nas, cf, indicazioni from c_sogg_fisico F';
        if ($cat != '') $q .= ' join c_tit_fabb_sogg_f  CT on CT.id_sogg_f=F.id join c_fabb_info INFO on INFO.id=CT.id_fabb';
        if ($qua != '') $q .= ' left join c_tit_terr_sogg_f CT on CT.id_sogg_f=F.id left join c_terr_info T on T.id=CT.id_terr';
        $q .= " where F.cod_com='" . $comune . "'";

        $nm = strtoupper($request->nome) ?? '';
        $cgn = strtoupper($request->cognome) ?? '';

        if ($cgn != '') $q .= " AND cognome like '%$cgn%'";
        if ($nm != '') $q .= " AND nome like '%$nm%'";
        if ($cf != '') $q .= " AND cf='$cf'";


        if ($cat != '' || $qua != '') {
            $q .= " AND (";
            if ($cat != '' && $qua == '')
                $q .= "cat='$cat')";
            else if ($cat == '' && $qua != '')
                $q .= "qualita='$qua')";
            else //cat e qua !=-1
                $q .= "cat='$cat' OR qualita='$qua')";
        }

        $q .= ' order by cognome, nome, data_nascita';

        $res = DB::connection('pgsql2')->select($q);
        return response()->json($res);
    }

    function selectPersoneGiuridicheCatasto(Request $request)
    {
        $comune = strtoupper($request->code_comune);

        $piva = '';
        $cat = $request->cat ?? '';
        $qua = $request->qua ?? '';

        if ($request->cat != '') $cat = '';
        if ($request->qua != '') $qua = '';
        if ($request->piva != '') $piva = '';

        $q = 'select distinct on(G.id, denominazione, cf) G.id, denominazione, cf from c_sogg_giuridico G';
        if ($cat != '') $q .= ' join c_tit_fabb_sogg_g  CT on CT.id_sogg_g=G.id join c_fabb_info INFO on INFO.id=CT.id_fabb';
        if ($qua != '') $q .= ' left join c_tit_terr_sogg_g CT on CT.id_sogg_g=G.id left join c_terr_info T on T.id=CT.id_terr';
        $q .= " where G.cod_com='" . $comune . "'";

        if ($request->denominazione != '') {
            $denominazione = strtoupper($request->denominazione);
            $q .= " AND denominazione LIKE '%$denominazione%'";
        }
        if ($piva != '') $q .= " AND cf='$piva'";

        if ($cat != '' || $qua != '') {
            $q .= " AND (";
            if ($cat != '' && $qua == '') $q .= "cat='$cat')";
            else if ($cat == '' && $qua != '')
                $q .= "qualita='$qua')";
            else $q .= "cat='$cat' OR qualita='$qua')";
        }

        $q .= ' order by denominazione, cf';

        $res = DB::connection('pgsql2')->select($q);
        return response()->json($res);
    }


    function selectUiuSogg(Request $request)
    {
        $comune = strtoupper($request->code_comune);

        $id = $request->id;
        $tipoSogg = $request->tipoSogg;
        $cat = $request->cat ?? '';
        $qua = $request->qua ?? '';

        $count = 1;
        $arr = array();

        if ($tipoSogg == 'f') //F FISICI
        {
            //cerco prima nei terr::
            $qTerr = "select distinct on(P.foglio,P.numero,P.sub )
          P.id, P.foglio,regexp_replace(P.numero, '0*', '')as numero, 
              regexp_replace(P.sub, '0*', '')as sub, 
              'terreno' as tipologia, qualita as catqua from c_terr_info P join 
              c_tit_terr_sogg_f CT on CT.id_terr=P.id where P.cod_com='" . $comune . "' AND CT.id_sogg_f='" . $id . "'";

            //quindi nei fabb::
            $qFabb = "select distinct on(IDN.foglio,IDN.numero, IDN.sub )
          IDN.id_fabb_info as id,
          regexp_replace(IDN.foglio, '0*', '')as foglio,
              regexp_replace(IDN.numero, '0*', '')as numero,
              regexp_replace(IDN.sub, '0*', '')as sub, 'fabbricato' as tipologia, cat as catqua
              FROM c_fabb_info F  JOIN c_fabb_identificativi IDN ON IDN.id_fabb_info=F.id
              join  c_tit_fabb_sogg_f CT on F.id=CT.id_fabb where CT.id_sogg_f='" . $id . "'";
        } else   //G GIURIDICI
        {
            //cerco prima nei terr::
            $qTerr = "select distinct on(P.foglio,P.numero,P.sub )
          P.id, P.foglio,regexp_replace(P.numero, '0*', '')as numero, 
              regexp_replace(P.sub, '0*', '')as sub, qualita as catqua,
              'terreno' as tipologia from c_terr_info P join 
              c_tit_terr_sogg_g CT on CT.id_terr=P.id where P.cod_com='" . $comune . "' AND CT.id_sogg_g='" . $id . "'";

            //quindi nei fabb::
            $qFabb = "select distinct on(IDN.foglio,IDN.numero, IDN.sub )
          IDN.id_fabb_info as id,
          regexp_replace(IDN.foglio, '0*', '')as foglio,
              regexp_replace(IDN.numero, '0*', '')as numero,
              regexp_replace(IDN.sub, '0*', '')as sub, 'fabbricato' as tipologia, cat as catqua
              FROM c_fabb_info F  JOIN c_fabb_identificativi IDN ON IDN.id_fabb_info=F.id
              join  c_tit_fabb_sogg_g CT on F.id=CT.id_fabb where CT.id_sogg_g='" . $id . "'";
        }

        if ($cat != '') {
            $qFabb .= " AND cat='" . $cat . "'";
            $res = DB::connection('pgsql2')->select($qFabb);
            for ($i = 0; $i < count($res); $i++) {
                $row = (array) $res[$i]; // Cast esplicito a array
                $row['r'] = $count . ")";
                $count++;
                array_push($arr, $row);
            }
        }

        if ($qua != '') {
            $qTerr .= " AND qualita='" . $qua . "'";
            $res = DB::connection('pgsql2')->select($qTerr);
            for ($i = 0; $i < count($res); $i++) {
                $row = (array) $res[$i]; // Cast esplicito a array
                $row['r'] = $count . ")";
                $count++;
                array_push($arr, $row);
            }
        }

        if ($cat == '' && $qua == '') {

            $res = DB::connection('pgsql2')->select($qTerr);

            for ($i = 0; $i < count($res); $i++) {
                $row = (array) $res[$i]; // Cast esplicito a array
                $row['r'] = $count . ")";
                $count++;
                array_push($arr, $row);
            }

            $res = DB::connection('pgsql2')->select($qFabb);
            for ($i = 0; $i < count($res); $i++) {
                $row = (array) $res[$i]; // Cast esplicito a array
                $row['r'] = $count . ")";
                $count++;
                array_push($arr, $row);
            }
        }
        return response()->json($arr);
    }

    /**PRINT */

    public function print_catasto(Request $request)
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', TRUE);
        $options->set('debugKeepTemp', TRUE);
        $options->set('isHtml5ParserEnabled', TRUE);
        $options->set('chroot', '/');
        $options->setIsRemoteEnabled(true);
        $dompdf = new Dompdf($options);

        if ($request->type == 'terreno') {
            $res = $this->elencoMutazioniCatastoTerreni($request, true);
            $html = view('print.print', compact('res'));
        } else {
            $res = $this->elencoMutazioniCatastoFabbricati($request, true);
            $html = view('print.print1', compact('res'));
        }

        $dompdf->loadHtml($html);

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4', 'landscape');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream('Visura catastale', ["compress" => 1, "Attachment" => false]);
        exit;
    }
}
