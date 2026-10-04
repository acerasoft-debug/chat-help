<?php
/**
 * ChatHelp — dump-doccard — chat belge kartinin CANLI HTML-builder'i + buton
 *  handler'i (Senden eklemek icin birebir anchor) (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-doccard.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-doccard (".strlen($src)." B) ==\n\n";

$off=0;$n=0;
while(($p=strpos($src,'ch-doccard-btn',$off))!==false && $n<6){
  echo "=== [#$n @$p] ===\n".substr($src,max(0,$p-260),640)."\n\n";
  $off=$p+14;$n++;
}
echo "toplam 'ch-doccard-btn': ".substr_count($src,'ch-doccard-btn')."\n";
echo "\n== BITTI ==\n";
