<?php
/**
 * ChatHelp — dump-aichat2 — api.php aichat provider yonlendirmesi: Claude nasil
 *  cagriliyor (provider adi) — iki asamali fax dogrulamasi icin (OKUR, secret maskeli).
 * KULLANIM: pull2.php?key=...&files=dump-aichat2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/api.php');
if($src===false){ echo "api.php YOK\n"; exit; }
function m($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{4})[A-Za-z0-9_\-]+/','$1***',(string)$s); }
echo "== dump-aichat2 (".strlen($src)." B) ==\n\n";

echo "=== provider adlari + yonlendirme ===\n";
foreach(['deepseek','claude','anthropic',"provider"] as $pat){
  $off=0;$n=0;
  while(($p=stripos($src,$pat,$off))!==false && $n<4){
    echo "  [$pat @$p] ...".m(str_replace("\n"," ",substr($src,max(0,$p-80),200)))."...\n"; $off=$p+strlen($pat);$n++;
  }
  echo "\n";
}
echo "=== aichat action govde ===\n";
$p=stripos($src,'aichat');
if($p!==false){ echo m(substr($src,max(0,$p-40),700))."\n"; }
echo "\n== BITTI ==\n";
