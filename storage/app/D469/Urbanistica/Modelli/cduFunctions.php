<?php
//funzione che struttura il cdu per il comune di Frasso Telesino (BN)

function formattaCdu($dati, $uiu, $elencoNorme){
  
  global $comune;
  
  $content=file_get_contents("/home/httpd/https/sit-dev/DATA/$comune/Modelli/cdu.html");
  
  
  
  
  //sostituzione tag singoli
  $content=str_replace('{protocollo}', $dati[cduprot], $content);
  $content=str_replace('{dataprotocollo}', $dati[cdudata], $content);
  
  $content=str_replace('{cognome}', $dati[cducgn], $content);
  $content=str_replace('{nome}', $dati[cdunm], $content);
  $content=str_replace('{luogo_nascita}', $dati[cduluogo], $content);
  $content=str_replace('{prov_nascita}', $dati[cduprovn], $content);
  $content=str_replace('{data_nascita}', $dati[cdudatan], $content);
  
  $content=str_replace('{citta}', $dati[cducitta], $content);
  $content=str_replace('{prov}', $dati[cduprovv], $content);
  $content=str_replace('{via}', $dati[cduvia], $content);
  $content=str_replace('{num}', $dati[cdunum], $content);      
  
  $content=str_replace('{protocollorichiesta}', $dati[cduprotric], $content);
  $content=str_replace('{datarichiesta}', $dati[cdudataric], $content);

  

  
  //cicli...  
  //elenco delle uiu:
  //elenco delle intersezioni:
  
  $count=count($uiu);
  
  $elencoFogli='<ul>';
  
  $elencoIntersezioni='';
  
  $creaDocumento=false;
  
  for ($i=0; $i<$count; $i++)
  { 
    if ($uiu[$i][fg]!='' && $uiu[$i][nm] && count($uiu[$i][intersects])>0)
    {
      $sp='';
      if ($i>0)
	$sp='<br><br>';
      $elencoIntersezioni.=$sp.'<p><b> - che</b> la zona di terreno riportata al N.C.T. al foglio n. '.$uiu[$i][fg].'  particella n. '.$uiu[$i][nm];
      $elencoFogli.='<li>foglio n. '.$uiu[$i][fg].' particella n. '.$uiu[$i][nm];
      if ($uiu[$i][sb]!='')
      {
	$elencoIntersezioni.=' sub n. '.$uiu[$i][sb];
	$elencoFogli.=' sub n. '.$uiu[$i][sb];      
      }
      $elencoFogli.='</li>';
      
      $elencoIntersezioni.=' <b>('.$uiu[$i][mq].')</b> e\' inclusa nel piano:<ol>';
      $c1=count($uiu[$i][intersects]);
     // echo '   ';
     // print_r(array_values($uiu[$i][intersects]));
      while (current($uiu[$i][intersects]))
      {
	$key=key($uiu[$i][intersects]);
	$elencoIntersezioni.='<li><b>'.strtoupper($key).'</b></li><ul>';
	
	$c2=count($uiu[$i][intersects][$key]);
	for ($w=0;$w<$c2;$w++)
	{
	  $zona='zona ';
	  if (stripos($uiu[$i][intersects][$key][$w][LAYER], 'zona')!==false)
	    $zona='';
	  $elencoIntersezioni.='<li>per <b>'.$uiu[$i][intersects][$key][$w][cal].'</b>  nella '.$zona.$uiu[$i][intersects][$key][$w][LAYER].'</li>';
	}
	$elencoIntersezioni.='</ul>';
	next($uiu[$i][intersects]);  
      }        
      $elencoIntersezioni.= '</ul></ol>';
      $creaDocumento=true;
    }
  }
  
  
  if ($creaDocumento)
  {
    $elencoFogli.='</ul>';  
    
    $content=str_replace('{elencoplle}', $elencoFogli, $content);	
    $content=str_replace('{certifica}', $elencoIntersezioni, $content);    
    
    //x le norme:
    $strNorme='';
    while ($elNorma=current($elencoNorme))
    {
      $keyNorma =key($elencoNorme);
      $strNorme.='<P><b>'.strtoupper($keyNorma).'</b></P><UL>';

      $c3=count($elNorma);
      for ($z=0;$z<$c3;$z++)
      {
	$pathF='/home/httpd/https/sit/DATA/'.$comune.'/Urbanistica/'.strtoupper($keyNorma).'/'.$elNorma[$z].'.html';	
	$strNorme.='<li>'.file_get_contents($pathF).'</li>';
      }
      $strNorme.='</UL>';
      next($elencoNorme);
    }
    
    $content=str_replace('{norme}', $strNorme, $content);
      
    return $content;
  }
  else return null;
    
    
}




?> 
