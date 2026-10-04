<?php
/**
 * ChatHelp — dump-resultbar — belge SONUC ekrani + gonderim akisi anchorlari (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-resultbar.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false) exit("index.php okunamadi\n");
echo "== dump-resultbar (".strlen($src)." B) ==\n\n";
function allsnip($src,$needle,$b,$a,$label,$max=2){
  $off=0;$k=0;
  while(($p=stripos($src,$needle,$off))!==false && $k<$max){
    echo "-- [$label #".($k+1)."] --\n".substr($src,max(0,$p-$b),$b+$a)."\n----\n\n";
    $off=$p+strlen($needle);$k++;
  }
  if($k===0) echo "-- [$label] '$needle' YOK\n\n";
}
echo "########## SONUC BUTON CUBUGU ##########\n";
allsnip($src,'Als PDF speichern',320,520,'PDF btn',2);
allsnip($src,'Neues Dokument',280,420,'result acts',1);

echo "########## GONDERIM (postversand / send) ##########\n";
allsnip($src,'postversand',280,760,'postversand',2);
allsnip($src,'Per Post',160,520,'Per Post',1);

echo "== BITTI ==\n";
