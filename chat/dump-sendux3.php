<?php
/**
 * ChatHelp — dump-sendux3 — chPrintDoc'un IKI kopyasinin TAM govdesi (iframe-tabanli
 *  vs window.open-tabanli — hangisi SONRA calisip digerini eziyor) (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-sendux3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-sendux3 (".strlen($src)." B) ==\n\n";

$positions=[];
$off=0;
while(($p=strpos($src,'window.chPrintDoc=function',$off))!==false){ $positions[]=$p; $off=$p+10; }
echo "toplam tanim sayisi: ".count($positions)." -> ".implode(', ',$positions)."\n\n";

foreach($positions as $i=>$p){
  echo "=== KOPYA #$i @$p (3200 karakter) ===\n";
  echo substr($src,$p,3200)."\n\n";
}

echo "\n== BITTI ==\n";
