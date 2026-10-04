<?php
/**
 * ChatHelp — dump-apppdf — App'te (Android WebView) PDF'e tiklaninca disari atma sorunu (OKUR).
 *  Neden: window.print()/window.open() WebView'de native app'i terk ettiriyor olabilir.
 *  isWV() + jsPDF + Capacitor varligi + makePDF'in TAM sonuna kadarki davranisini gosterir.
 * KULLANIM: pull2.php?key=...&files=dump-apppdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-apppdf (".strlen($src)." B) ==\n\n";

echo "=== [1] isWV() tanimi (WebView tespiti) ===\n";
$p=strpos($src,'function isWV');
if($p!==false) echo substr($src,$p,320)."\n\n"; else echo "  function isWV yok\n\n";

echo "=== [2] jsPDF yuklu mu? (script src / window.jspdf) ===\n";
foreach(['jspdf','jsPDF','window.jspdf'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
$p=stripos($src,'jspdf.umd');
if($p!==false) echo "  script-src civari: ...".substr($src,max(0,$p-100),220)."...\n";

echo "\n=== [3] Capacitor / native koprusu var mi? ===\n";
foreach(['window.Capacitor','Capacitor.isNativePlatform','capacitor://','android_asset'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}

echo "\n=== [4] makePDF TAM govdesi (basindan sonuna, max 3800) ===\n";
$p=strpos($src,'function makePDF');
if($p!==false){
  /* fonksiyonun kabaca sonunu bulmak icin ilk 3800 karakteri goster */
  echo substr($src,$p,3800)."\n";
}

echo "\n== BITTI ==\n";
