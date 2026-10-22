<?php
/**
 * ChatHelp — dump-sr2 — 'function showResult' 2 kez neden? cift tanim mi substring mi (OKUR).
 *  Ayrica kritik PDF fonksiyonlarinda cift-tanim var mi kontrol eder.
 * KULLANIM: pull2.php?key=...&files=dump-sr2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-sr2 (".strlen($src)." B) ==\n\n";

echo "=== 'function showResult' her yer (±10 before, 70 after) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'function showResult',$off))!==false && $n<6){
  echo "  [$n] @$p ...".substr($src,max(0,$p-10),90)."...\n";
  $off=$p+18;$n++;
}

echo "\n=== kritik fonksiyon cift-tanim taramasi ===\n";
foreach(['function makePDF','function chPrintDoc','chPrintDoc=function','function proceed','function doFree','function reallySend','function doHome','function openTopic','function showPaywall','function buildPages'] as $fn){
  $c=substr_count($src,$fn);
  echo "  ".($c<=1?'✓':'⚠').' '.$fn." = $c\n";
}

echo "\n=== 'showResult(' cagri/def toplam ===\n";
echo "  showResult( : ".substr_count($src,'showResult(')."\n";
echo "  showResult=function : ".substr_count($src,'showResult=function')."\n";

echo "\n== BITTI ==\n";
