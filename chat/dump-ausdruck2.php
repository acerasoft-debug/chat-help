<?php
/**
 * ChatHelp — dump-ausdruck2 — print yolunun TAM canli hali (6 katmanli yama teshisi).
 *  Amac: 'ausdrucken'/PDF neden calismiyor — hangi marker'lar var, chPrintDoc govdesi
 *  ne durumda, buton handler'i neyi cagiriyor, isWV/_isWv2/ChelpNative nasil.
 * KULLANIM: pull2.php?key=...&files=dump-ausdruck2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-ausdruck2 (".strlen($src)." B) ==\n\n";

echo "=== [1] Marker envanteri (kac kez) ===\n";
foreach(['CH_APPPDF','CH_CPDWV','CH_PDFBLOB','CH_PRINTDOC_WVFIRST','CH_APPPRINT','CH_APPPRINT2','CH_PDF_CORP','ch-pdf-corporate','ChelpNative','isWV','jspdf'] as $m){
  echo "  ".str_pad($m,22)." : ".substr_count($src,$m)."\n";
}

echo "\n=== [2] 'ausdrucken' TUM gecisleri (buton + handler) ===\n";
$off=0;$n=0;
while(($p=stripos($src,'ausdrucken',$off))!==false && $n<10){
  echo "  [$n] @$p ...".preg_replace('/\s+/',' ',substr($src,max(0,$p-140),300))."...\n\n"; $off=$p+10;$n++;
}
if($n===0) echo "  YOK\n";

echo "\n=== [3] window.chPrintDoc TAM govde (ilk tanim) ===\n";
$p=strpos($src,'chPrintDoc=function');
if($p===false) $p=strpos($src,'chPrintDoc = function');
if($p!==false){
  $st=max(0,$p-40);
  echo substr($src,$st,3000)."\n";
} else echo "  chPrintDoc tanimi YOK\n";

echo "\n=== [4] isWV() tanimi ===\n";
$p=strpos($src,'function isWV');
if($p===false) $p=strpos($src,'isWV=function');
if($p!==false) echo substr($src,max(0,$p-20),260)."\n"; else echo "  isWV YOK\n";

echo "\n=== [5] makePDF tanimi (bas kisim) ===\n";
$p=strpos($src,'function makePDF');
if($p===false) $p=strpos($src,'makePDF=function');
if($p!==false) echo substr($src,max(0,$p-20),700)."\n"; else echo "  makePDF YOK\n";

echo "\n== BITTI ==\n";
