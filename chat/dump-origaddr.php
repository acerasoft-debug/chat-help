<?php
/* ChatHelp - dump-origaddr (READ-ONLY) - 'Original an meine Adresse' akisi: handler + ucret kontrolu */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-origaddr (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] secenek metinleri + tiklama handler'lari ===\n";
foreach(array('Original an meine Adresse','zum Unterschreiben','Kostenlos selbst ausdrucken','ORIGINAL-Unterschrift','BGB §126','Direktversand nicht') as $pat){
  $off=0;$n=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $n<2){
    echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),' ',substr($src,($q-100>0?$q-100:0),380))."...\n\n";
    $off=$q+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "=== [2] 'meine Adresse' butonunun action cagrisi ===\n";
foreach(array('send_self','self_letter','to_self','own_address','eigene Adresse','send_original','original_home','selfmail') as $pat){
  $q=strpos($src,$pat);
  if($q!==false){ echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),' ',substr($src,($q-90>0?$q-90:0),260))."...\n"; }
  else echo "  (".$pat.": yok)\n";
}

echo "\n=== [3] postversand action listesi (server tarafinda hangi action'lar var) ===\n";
$pv=(string)@file_get_contents(__DIR__.'/postversand.php');
echo "  postversand.php: ".strlen($pv)." B\n";
if(preg_match_all("/action[\"']?\s*(?:===|==)\s*['\"]([a-z_]+)['\"]/i",$pv,$m)){
  echo "  actions: ".implode(', ',array_unique($m[1]))."\n";
}
foreach(array('self','own','meine','kostenlos','free','quota','plan','price','charge') as $pat){
  $c=substr_count(strtolower($pv),$pat);
  if($c) echo "  '".$pat."': $c kez\n";
}
echo "\n== BITTI ==\n";
