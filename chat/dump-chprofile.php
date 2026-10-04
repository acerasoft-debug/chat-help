<?php
/**
 * ChatHelp — dump-chprofile — 'ch_profile' (TEKIL, ch_profiles/ch_prof_v3 DEGIL) TUM
 *  yazma/okuma yerlerini bulur (OKUR). DB kesin dogru (elite) ama UI hala 'basic'
 *  gosteriyor -> supheli: bu eski/ayri anahtar hic senkronize edilmiyor.
 * KULLANIM: pull2.php?key=...&files=dump-chprofile.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-chprofile (".strlen($src)." B) ==\n\n";

echo "=== TUM \"ch_profile\" (TEKIL) gecisleri (write/read ayrimi) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'ch_profile"',$off))!==false && $n<20){
  $seg=substr($src,max(0,$p-90),190);
  $kind = (stripos($seg,'setItem')!==false) ? 'YAZMA' : ((stripos($seg,'getItem')!==false)?'OKUMA':'?');
  echo "  [$n][$kind] @$p ...".trim($seg)."...\n\n";
  $off=$p+11;$n++;
}
$off=0;
while(($p=strpos($src,"ch_profile'",$off))!==false && $n<30){
  $seg=substr($src,max(0,$p-90),190);
  $kind = (stripos($seg,'setItem')!==false) ? 'YAZMA' : ((stripos($seg,'getItem')!==false)?'OKUMA':'?');
  echo "  [$n][$kind] @$p ...".trim($seg)."...\n\n";
  $off=$p+11;$n++;
}

echo "\n=== renderKontoPlanSelector / Konto plan-goruntuleme tam govde ===\n";
$p=strpos($src,'function renderKontoPlanSelector');
if($p!==false) echo substr($src,$p,900)."\n\n"; else echo "  fonksiyon bulunamadi (farkli isim olabilir)\n";

echo "\n== BITTI ==\n";
