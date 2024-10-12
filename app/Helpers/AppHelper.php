<?php

namespace App\Helpers;

class AppHelper
{
    public static function formatNumber($number, $length)
    {
        // Converte il numero in stringa e ne calcola la lunghezza
        $numberStr = strval($number);
        $numberLength = strlen($numberStr);

        // Se la lunghezza della stringa è maggiore o uguale a quella richiesta, restituisci la stringa originale
        if ($numberLength >= $length) {
            return $numberStr;
        }

        // Calcola il numero di zeri da aggiungere
        $zeroCount = $length - $numberLength;

        // Costruisci la stringa con zeri aggiunti prima del numero
        $formattedNumber = str_repeat('0', $zeroCount) . $numberStr;

        return $formattedNumber;
    }

    public static function selectSuperficieTerreno($f, $n, $s, $codCom)
    {
        $n = AppHelper::formatNumber($n, 5);
        $q = 'SELECT ettari,are,centiare FROM c_terr_info WHERE cod_com=\'' . strtoupper($codCom) . '\'';
        if ($f != '')
            $q .= " AND foglio='" . $f . "'";
        if ($n != '') {
            if (substr($q, -1) == "'")
                $q .= " AND ";
            $q .= " numero='" . $n . "'";
        }
        if ($s != '') {
            if (substr($q, -1) == "'")
                $q .= " AND ";
            $q .= " sub='" . $s . '\'';
        }
        $q .= " limit 1";

        $res = \DB::connection('pgsql2')->select($q);
        
        if (!empty($res)) {
            $ress = (array)$res[0];  // Assicurati che $ress sia un array
            return $ress;
        } else {
            // Gestisci il caso in cui non ci siano risultati
            return null; // O qualsiasi valore predefinito tu voglia usare
        }
    }

    public static function formattaCdu($dati, $uiu, $elencoNorme, $codCom, $nomiPiani)
    {
        $uiu = (array)$uiu;
        $indirizzo = $dati['cducitta'].' - '.$dati['cducap'];
        $content = file_get_contents(storage_path("app/$codCom/Modelli/cdu.html"));
        $content = str_replace('{titolo}', $dati['cdutitolo'], $content);
        $content = str_replace('{qualita}', $dati['cduqualita'], $content);
        $content = str_replace('{protocollo}', $dati['cduprot'], $content);
        $content = str_replace('{cognome}', $dati['cducgn'], $content);
        $content = str_replace('{nome}', $dati['cdunm'], $content);
        $content = str_replace('{luogo_nascita}', $dati['cduluogo'], $content);
        $content = str_replace('{prov_nascita}', $dati['cduprovn'], $content);
        $content = str_replace('{data_nascita}', $dati['cdudatan'], $content);
        $content = str_replace('{citta}',$indirizzo , $content);
        $content = str_replace('{prov}', $dati['cduprovv'], $content);
        $content = str_replace('{via}', $dati['cduvia'], $content);
        $content = str_replace('{num}', $dati['cdunum'], $content);
        $content = str_replace('{protocollorichiesta}', $dati['cduprotric'], $content);
        $content = str_replace('{datarichiesta}', $dati['cdudataric'], $content);
        $content = str_replace('{dataprotocollo}', $dati['cdudata'], $content);

        $count = sizeof($uiu);

        $elencoFogli = '<ul>';

        $elencoIntersezioni = '';

        $creaDocumento = false;

        for ($i = 0; $i < $count; $i++) {

            if ($uiu[$i]['fg'] != '' && $uiu[$i]['nm'] && count($uiu[$i]['intersects']) > 0) {
                $sp = '';
                if ($i > 0)
                    $sp = '<br><br>';
                $elencoIntersezioni .= $sp . '<p><b> - che</b> la zona di terreno riportata al N.C.T. al foglio n. ' . $uiu[$i]['fg'] . '  particella n. ' . $uiu[$i]['nm'];
                $elencoFogli .= '<li>foglio n. ' . $uiu[$i]['fg'] . ' particella n. ' . $uiu[$i]['nm'];
                if ($uiu[$i]['sb'] != '') {
                    $elencoIntersezioni .= ' sub n. ' . $uiu[$i]['sb'];
                    $elencoFogli .= ' sub n. ' . $uiu[$i]['sb'];
                }
                $elencoFogli .= '</li>';

                $elencoIntersezioni .= ' <b>(' . $uiu[$i]['mq'] . ')</b> e\' inclusa nel piano:<ol>';
                $c1 = count($uiu[$i]['intersects']);

                while (current($uiu[$i]['intersects'])) {
                    $key = key($uiu[$i]['intersects']);

                  
                    /**
                     * Prova per il cambio dei nomi del piano, eliminare dopo l'inserimento del Model per la creazione
                     * del nome personalizzato, tale creazione prevede assegnazione dekeyNormal nome della tabell e nome User Friendly
                     * es. psaiurbutm -> PSAI associazione 1-1,
                     * I piani all'interno del JS del CDU (le radio button devono essere chiamate dal BE)
                     */
                    $nome = isset($nomiPiani[$key]) ? $nomiPiani[$key] : $key;
                    if(str_contains($nome, 'urbutm')) $nome = str_replace('urbutm', '', $key);
                    $elencoIntersezioni .= '<li><b>' . strtoupper($nome) . '</b></li><ul>';

                    $c2 = count($uiu[$i]['intersects'][$key]);
                    for ($w = 0; $w < $c2; $w++) {
                        $zona = 'zona ';
                        if (stripos($uiu[$i]['intersects'][$key][$w]['LAYER'], 'zona') !== false) $zona = '';
                        $elencoIntersezioni .= '<li>per <b>' . $uiu[$i]['intersects'][$key][$w]['cal'] . '</b>  nella ' . $zona . $uiu[$i]['intersects'][$key][$w]['LAYER'] . '</li>';
                    }
                    $elencoIntersezioni .= '</ul>';
                    next($uiu[$i]['intersects']);
                }
                
                $elencoIntersezioni .= '</ul></ol>';
                $creaDocumento = true;
            }
        }

        if ($creaDocumento) {
            $elencoFogli .= '</ul>';
            $content = str_replace('{elencoplle}', $elencoFogli, $content);
            $content = str_replace('{certifica}', $elencoIntersezioni, $content);
            
            $strNorme = '';            
            while ($elNorma = current($elencoNorme)) {
                $keyNorma = key($elencoNorme);
                $nomeNorma = isset($nomiPiani[$keyNorma]) ? $nomiPiani[$keyNorma] : $keyNorma;
                if(str_contains($nomeNorma, 'urbutm')) $nomeNorma = str_replace('urbutm', '', $keyNorma);
                //echo "kn=$keyNorma";
                $strNorme .= '<P><b>' . strtoupper($nomeNorma) . '</b></P><UL>';
                //echo 'NN = '.$elNorma;
                $c3 = count($elNorma);
                //  echo 'C: '. $c3.' |   ';

                for ($z = 0; $z < $c3; $z++) {

                        $pathF = storage_path('app/' . $codCom . '/Urbanistica/'. strtoupper(str_replace('urbutm', '', $keyNorma)).'/'.$elNorma[$z].'.html');
                        $strNorme .= '<li>' . file_get_contents($pathF) . '</li>';

                }

                $strNorme .= '</UL>';
                next($elencoNorme);
            }

            $content = str_replace('{norme}', $strNorme, $content);

            return $content;
        } else return null;
    }
}
