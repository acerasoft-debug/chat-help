<?php
/**
 * ChatHelp — dump-faxfind2 — fax-find bileseninin TAM handler'i (OKUR).
 *  Isim hangi alandan okunuyor? click handler + guard.
 * KULLANIM: pull2.php?key=...&files=dump-faxfind2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{40,})/','***',$s); }
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
$p=strpos($src,'Faxnummer per KI finden');
if($p===false){ echo "bulunamadi\n"; exit; }
/* script blogunun basi: geriye dogru <script id= ara */
$st=strrpos(substr($src,0,$p),'<script');
$en=strpos($src,'</script>',$p);
echo "== fax-find bileseni (@".$st." .. ".$en.") ==\n\n";
echo mask(substr($src,$st,($en-$st)+9))."\n";
echo "\n== BITTI ==\n";
