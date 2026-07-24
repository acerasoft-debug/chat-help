<?php
/**
 * ChatHelp — dump-showresult2 — showResult'in KALANI: metin gövdesi nerede/nasil
 *  ekleniyor (blur enjekte etmek icin) + box'a appendler.
 * KULLANIM: pull2.php?key=...&files=dump-showresult2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-showresult2 ==\n\n";
$p=strpos($src,'function showResult');
if($p===false){ echo "showResult yok\n"; exit; }
$e=strpos($src,"\n  function ",$p+20); if($e===false||$e-$p>6000)$e=$p+6000;
$body=substr($src,$p,$e-$p);
/* metin govdesi ile ilgili kisimlari one cikar */
echo "=== showResult TAM ($p .. $e) ===\n";
echo $body."\n";
echo "\n== BITTI ==\n";
