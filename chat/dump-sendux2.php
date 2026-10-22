<?php
/**
 * ChatHelp — dump-sendux2 — chPrintDoc'un GERCEK tanim bicimini bul (chPrintDoc=
 * / var chPrintDoc / window.chPrintDoc) + govdesi (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-sendux2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-sendux2 (".strlen($src)." B) ==\n\n";

echo "=== [1] chPrintDoc tanim bicimleri ===\n";
foreach(['chPrintDoc=function','chPrintDoc = function','var chPrintDoc','window.chPrintDoc=function','window.chPrintDoc = function','function  chPrintDoc'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<4){
    echo "  [$pat @$p] ...".substr($src,max(0,$p-80),240)."...\n\n"; $off=$p+strlen($pat);$n++;
  }
}
echo "  'chPrintDoc' düz metin sayisi: ".substr_count($src,'chPrintDoc')."\n\n";

echo "=== [2] ilk 'chPrintDoc' konumunun GENIS govdesi (150 once, 2600 sonra) ===\n";
$p=strpos($src,'chPrintDoc');
if($p!==false){ echo substr($src,max(0,$p-150),2750)."\n"; }

echo "\n== BITTI ==\n";
