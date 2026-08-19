<?php
/**
 * ChatHelp — dump-studiopdf — CV Studio + Vertrag Studio makePdf() fonksiyonlarinin
 *  GUNCEL canli govdesi (repo'daki apply-cv-studio.php/apply-contract-studio.php'den
 *  farkli olabilir, araya baska yama girmis olabilir) (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-studiopdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-studiopdf (".strlen($src)." B) ==\n\n";

echo "=== [1] TUM 'function makePdf' konumlari ===\n";
$off=0;$n=0;$pos=[];
while(($p=strpos($src,'function makePdf',$off))!==false){ $pos[]=$p; $off=$p+10; }
echo "  toplam: ".count($pos)." -> ".implode(', ',$pos)."\n\n";

foreach($pos as $i=>$p){
  echo "=== KOPYA #$i @$p (900 karakter) ===\n";
  echo substr($src,max(0,$p-30),950)."\n\n";
}

echo "\n== BITTI ==\n";
