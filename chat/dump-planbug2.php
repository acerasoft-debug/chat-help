<?php
/**
 * ChatHelp — dump-planbug2 — P.plan='basic' hardcode'unun TAM baglami (OKUR).
 *  Hangi fonksiyon, hadTk ne kontrol ediyor, ne zaman tetikleniyor?
 * KULLANIM: pull2.php?key=...&files=dump-planbug2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-planbug2 (".strlen($src)." B) ==\n\n";

echo "=== [1] P.plan='basic' TAM baglam (±900 before, 900 after) ===\n";
$p=strpos($src,"P.plan='basic'");
if($p!==false){ echo substr($src,max(0,$p-900),1900)."\n"; }
else echo "  bulunamadi\n";

echo "\n=== [2] 'hadTk' TUM gecisler ===\n";
$off=0;$n=0;
while(($p=strpos($src,'hadTk',$off))!==false && $n<8){
  echo "  [$n] @$p ...".substr($src,max(0,$p-60),140)."...\n"; $off=$p+5;$n++;
}

echo "\n== BITTI ==\n";
