<?php
/**
 * ChatHelp — dump-gengate — dilekce URETIM kapisinin TAM govdesi (@992298 civari):
 *  paywall return sonrasi generation nasil devam ediyor (busy/sbtn/quota/gen cagrisi).
 *  1-free-IP grant'i bu akisa dogru baglamak icin.
 * KULLANIM: pull2.php?key=...&files=dump-gengate.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-gengate ==\n\n";

/* paywall return + sonrasi ~1800 B */
$p=strpos($src,'window._pendingDoc={dk,ans};');
if($p===false){ $p=strpos($src,'_pendingDoc={dk,ans}'); }
if($p!==false){
  $st=max(0,$p-260);
  echo "=== uretim kapisi + sonrasi (@$st) ===\n";
  echo substr($src,$st,2100)."\n\n";
} else echo "  uretim kapisi bulunamadi\n";

/* bu kod hangi fonksiyon icinde? geriye function ara */
if($p!==false){
  $fn=strrpos(substr($src,0,$p),'function ');
  echo "=== icindeki fonksiyon basi (@$fn) ===\n";
  echo substr($src,$fn,160)."\n";
}
echo "\n== BITTI ==\n";
