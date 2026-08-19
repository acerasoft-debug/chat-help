<?php
/**
 * ChatHelp — dump-addr2 — adres 'surekli soruluyor' bug'i: (OKUR)
 *  [1] showProfileForm'u KIM cagiriyor + hangi kosulla (adres eksik gate'i)
 *  [2] belge uretiminden ONCE adres/profil kontrolu
 *  [3] P'nin ilk yuklenisi + ch_prof_v3 yazma/okuma tutarliligi
 * KULLANIM: pull2.php?key=...&files=dump-addr2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-addr2 (".strlen($src)." B) ==\n\n";

echo "=== [1] showProfileForm cagiran yerler ===\n";
$off=0;$n=0;
while(($p=strpos($src,'showProfileForm',$off))!==false && $n<8){
  echo "  [#$n @$p] ...".substr($src,max(0,$p-160),260)."...\n\n"; $off=$p+15;$n++;
}

echo "=== [2] adres-eksik / profil-eksik kosullari (P.f3 / needProfile / !P.f) ===\n";
foreach(['!P.f3','!P.f1','!(P.f3','needProfile','profilComplete','hasProfile','P.f3||','!prof().f3'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [$pat @$p] ...".substr($src,max(0,$p-90),200)."...\n\n"; $off=$p+strlen($pat);$n++;
  }
}

echo "=== [3] showProfileForm save-butonu (nereye yaziyor, FK ne) ===\n";
$p=strpos($src,"function showProfileForm");
if($p!==false){
  $blk=substr($src,$p,2600);
  /* sadece save-butonu civari */
  $bp=strpos($blk,"btn.onclick");
  if($bp!==false) echo substr($blk,$bp,900)."\n\n";
  else echo substr($blk,0,900)."\n\n";
}

echo "=== [4] P ilk yukleme + FK tanimi ===\n";
$p=strpos($src,"P=JSON.parse(localStorage.getItem(FK)");
if($p!==false) echo "  ...".substr($src,max(0,$p-140),260)."...\n";
$p2=strpos($src,"FK='ch_prof_v3'");
if($p2!==false) echo "  FK tanimi: ...".substr($src,max(0,$p2-40),120)."...\n";

echo "\n== BITTI ==\n";
