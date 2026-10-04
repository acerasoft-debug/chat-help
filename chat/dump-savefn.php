<?php
/**
 * ChatHelp — dump-savefn — 'Persönliche Daten' kaydetme fonksiyonunun TAM govdesi
 *  (dogrulamadan SONRA nereye yaziyor) + cagiran buton (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-savefn.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-savefn (".strlen($src)." B) ==\n\n";

echo "=== [1] kaydetme fonksiyonu TAM govde (±300 once, 1800 sonra) ===\n";
$p=strpos($src,"f7=document.getElementById('f7').value.trim();");
if($p!==false){ echo substr($src,max(0,$p-350),2100)."\n"; }
else echo "  bulunamadi\n";

echo "\n== BITTI ==\n";
