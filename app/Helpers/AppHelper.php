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
        $n = SELF::formatNumber($n, 5);
        $q = 'SELECT id,ettari,are,centiare FROM c_terr_info WHERE cod_com=\'' . strtoupper($codCom) . '\'';
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
        } else $q .= "AND sub IS NULL";
        $q .= " order by id DESC limit 1";

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
        $content = str_replace('{data_nascita}', date('d/m/Y', strtotime($dati['cdudatan'])), $content);
        $content = str_replace('{citta}',$indirizzo , $content);
        $content = str_replace('{prov}', $dati['cduprovv'], $content);
        $content = str_replace('{via}', $dati['cduvia'], $content);
        $content = str_replace('{num}', $dati['cdunum'], $content);
        $content = str_replace('{protocollorichiesta}', $dati['cduprotric'], $content);
        $content = str_replace(
            '{datarichiesta}',
            (!empty($dati['cdudataric']) ? date('d/m/Y', strtotime($dati['cdudataric'])) : ''),
            $content
        );        
        $content = str_replace(
            '{dataprotocollo}',
            (!empty($dati['cdudata']) ? date('d/m/Y', strtotime($dati['cdudata'])) : ''),
            $content
        );        

        $count = sizeof($uiu);

        $elencoFogli = '<ul>';

        $elencoIntersezioni = '';

        $creaDocumento = false;

        for ($i = 0; $i < $count; $i++) {

            if ($uiu[$i]['fg'] != '' && $uiu[$i]['nm'] && count($uiu[$i]['intersects']) > 0) {
                $sp = '';
                if ($i > 0)
                    $sp = '<br><br>';
                $elencoIntersezioni .= $sp . '<p><b> - che</b> l\'immobile identificato in Catasto al Foglio n. ' . $uiu[$i]['fg'] . '  Particella n. ' . $uiu[$i]['nm'];
                $elencoFogli .= '<li>foglio n. ' . $uiu[$i]['fg'] . ' particella n. ' . $uiu[$i]['nm'];
                if ($uiu[$i]['sb'] != '') {
                    $elencoIntersezioni .= ' sub n. ' . $uiu[$i]['sb'];
                    $elencoFogli .= ' sub n. ' . $uiu[$i]['sb'];
                }
                $elencoFogli .= '</li>';

                $elencoIntersezioni .= ' <b>(' . $uiu[$i]['mq'] . ')</b> ricade nel piano:<ol>';

                while (current($uiu[$i]['intersects'])) {
                    $key = key($uiu[$i]['intersects']);
                    $nome = isset($nomiPiani[$key]) ? $nomiPiani[$key] : $key;
                    if (str_contains($nome, 'urbutm')) $nome = str_replace('urbutm', '', $key);
                    
                    $intersects = $uiu[$i]['intersects'][$key];
                    if (empty($intersects)) {
                        // Nessuna intersezione
                        $elencoIntersezioni .= '<li><b>' . strtoupper($nome) . '</b>: non presente</li>';
                    } else {
                        // Lista delle intersezioni
                        $elencoIntersezioni .= '<li><b>' . strtoupper($nome) . '</b></li><ul>';
                        $c2 = count($intersects);
                        for ($w = 0; $w < $c2; $w++) {
                            $zona = 'zona ';
                            if (stripos($intersects[$w]['LAYER'], 'zona') !== false) $zona = '';
                            $elencoIntersezioni .= '<li>per <b>' . $intersects[$w]['cal'] . '</b> nella ' . $zona . ' ' . $intersects[$w]['STRING'] . '</li>';
                        }
                        $elencoIntersezioni .= '</ul>';
                    }
                
                    next($uiu[$i]['intersects']);
                }
                
                $elencoIntersezioni .= '</ul></ol><br>';
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
