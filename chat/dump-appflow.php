<?php
/**
 * ChatHelp — dump-appflow — App yazdirma yolunun TAM statik resmi.
 *  Amac: Claude'un canli koddan teshis yapmasi icin: isWV() TAM govde,
 *  free-print butonunun baglandigi handler, TUM ChelpNative kullanimlari,
 *  CH_APPPDF (App-PDF disari atma) mekanizmasi, chPrintDoc/makePDF cagrilari.
 * KULLANIM: pull2.php?key=...&files=dump-appflow.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-appflow (".strlen($src)." B) ==\n\n";

function ctx($src,$needle,$before,$len,$max=30){
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    echo "  [@$p] ...".preg_replace('/\s+/',' ',substr($src,max(0,$p-$before),$len))."...\n\n";
    $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  YOK\n";
}

echo "=== [1] isWV() TAM govde ===\n";
$p=strpos($src,'function isWV');
if($p===false)$p=strpos($src,'isWV=function');
echo $p!==false? substr($src,max(0,$p-20),1100)."\n" : "  isWV YOK\n";

echo "\n=== [2] TUM ChelpNative kullanimlari (context) ===\n";
ctx($src,'ChelpNative',80,240,30);

echo "\n=== [3] CH_APPPDF blogu (App-PDF disari atma) ===\n";
ctx($src,'CH_APPPDF',60,900,6);

echo "\n=== [4] chPrintDoc( CAGRILARI (tanim degil, cagri) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'chPrintDoc(',$off))!==false && $n<30){
  echo "  [@$p] ...".preg_replace('/\s+/',' ',substr($src,max(0,$p-160),320))."...\n\n"; $off=$p+11;$n++;
}
if($n===0) echo "  YOK\n";

echo "\n=== [5] free-print buton handler (doFree / 'Kostenlos' onclick zinciri) ===\n";
foreach(['doFree','doPrintFree','printFree','freePrint','.free','proceed('] as $k){
  echo "-- '$k' --\n"; ctx($src,$k,120,300,6);
}

echo "\n=== [6] makePDF( CAGRILARI ===\n";
$off=0;$n=0;
while(($p=strpos($src,'makePDF(',$off))!==false && $n<20){
  echo "  [@$p] ...".preg_replace('/\s+/',' ',substr($src,max(0,$p-120),260))."...\n\n"; $off=$p+8;$n++;
}
if($n===0) echo "  YOK\n";

echo "\n=== [7] CH_APPDIAG / CH_APPDIAG2 su an ekli mi ===\n";
echo "  CH_APPDIAG=".substr_count($src,'CH_APPDIAG')."  CH_APPDIAG2=".substr_count($src,'CH_APPDIAG2')."\n";

echo "\n== BITTI ==\n";
