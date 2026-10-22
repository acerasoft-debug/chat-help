<?php
/**
 * ChatHelp — dump-senden9 — .ch-senden2 enjektorunun TAM kaynagini + surukleyen
 *  setInterval'i cikarir (kaynakta susturmak icin exact anchor).
 * KULLANIM: pull2.php?key=...&files=dump-senden9.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-senden9 (".strlen($src)." B) ==\n\n";

/* ch-senden2 enjektor bloku: geriye script id, ileriye kapanis + setInterval */
$p=strpos($src,'ch-senden2');
if($p===false){ echo "ch-senden2 YOK\n"; exit; }
/* bu enjektorun basini bul: en yakin '<script' geriye */
$sStart=strrpos(substr($src,0,$p),'<script');
$sEnd=strpos($src,'</script>',$p);
if($sEnd!==false) $sEnd+=9;
echo "=== .ch-senden2 enjektor script blogu (@$sStart .. $sEnd) ===\n";
echo substr($src,$sStart,($sEnd-$sStart))."\n\n";

echo "== BITTI ==\n";
