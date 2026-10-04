<?php
/**
 * ChatHelp — dump-docpdf2 (SADECE OKUR) — CH_AICHAT_DOCPDF blogunun govdesi:
 *  restore() fonksiyonu + [[DOC]] metninin nasil saklandigi + PDF dugmesi.
 *  "..." bos PDF regresyonunun kesin duzeltmesi icin. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-docpdf2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-docpdf2 — SADECE OKUR ===\n\n";
$p=strpos($src,'CH_AICHAT_DOCPDF');
if($p===false) exit("CH_AICHAT_DOCPDF yok!\n");
$start=max(0,$p-200);
echo "--- blok 1/2 (baslangic @".$start.", 5500 kr) ---\n";
echo substr($src,$start,5500)."\n\n";
echo "--- blok 2/2 (devam, 5500 kr) ---\n";
echo substr($src,$start+5500,5500)."\n\n";
echo "--- 'function restore' tum gecisler ---\n";
$off=0;$n=0;
while(($q=strpos($src,'function restore',$off))!==false && $n<3){
  echo "@$q:\n".substr($src,$q,900)."\n\n"; $off=$q+10;$n++;
}
echo "=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
