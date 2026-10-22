<?php
/**
 * ChatHelp — dump-mpdf — makePDF + window.makePDF sarmalari + isLetter + wrapper (OKUR).
 *  ACIL: 'Als PDF speichern' -> makePDF cagriyor ama PDF/modal cikmiyor. Neden?
 * KULLANIM: pull2.php?key=...&files=dump-mpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{40,})/','***',$s); }
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-mpdf (".strlen($src)." B) ==\n\n";

echo "=== [1] function makePDF tanimi (ilk 950) ===\n";
$p=strpos($src,'function makePDF');
if($p!==false) echo mask(substr($src,$p,950))."\n\n";

echo "=== [2] window.makePDF= sarmalari (her yer, ±40 before 320) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'window.makePDF=',$off))!==false && $n<6){
  echo "  [$n] @$p ...".mask(substr($src,max(0,$p-40),360))."...\n\n"; $off=$p+15;$n++;
}
if($n===0) echo "  window.makePDF= yok\n";

echo "=== [3] isLetter tanimi ===\n";
$p=strpos($src,'function isLetter');
if($p!==false) echo mask(substr($src,$p,420))."\n\n"; else echo "  function isLetter yok\n";

echo "=== [4] adres-imza4 wrapper (chPrintDoc __addr4) civari ===\n";
$p=strpos($src,'__addr4');
if($p!==false) echo mask(substr($src,max(0,$p-640),820))."\n\n";

echo "== BITTI ==\n";
