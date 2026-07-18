<?php
/* dump-profblock (READ-ONLY) — Konto Profil blogu (Bearbeiten butonu civari)
   TAM HTML + Name/E-Mail/Adresse deger element ID'leri.
   KULLANIM: pull2.php?key=...&files=dump-profblock.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-profblock (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");

$p=strpos($s,'editProfile()">');
if($p!==false){
  echo "=== Profil blogu (Bearbeiten civari -2000/+400) ===\n";
  echo substr($s,max(0,$p-2000),2400)."\n";
}
echo "\n=== fillKonto set() cagrilari (ID'ler) ===\n";
$p=strpos($s,'function fillKonto');
if($p!==false){ $blk=substr($s,$p,1500);
  if(preg_match_all('/set\([\'"]([a-z0-9\-]+)[\'"]\s*,/i',$blk,$m)) echo "  set ID: ".implode(', ',$m[1])."\n";
  echo "  --- fillKonto ilk 1200 ---\n".$blk."\n";
}
echo "\nBITTI.\n";
