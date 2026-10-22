<?php
/**
 * ChatHelp — dump-savefn3 — profil-formu save fonksiyonunun ETRAFINDA genis pencere
 *  (FK/P nerede tanimlaniyor, hangi fonksiyon bu) + P'nin SAYFA YUKLENINCE hangi
 *  localStorage anahtarindan OKUNDUGU + genDoc'un adres-eksik kontrolu (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-savefn3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-savefn3 (".strlen($src)." B) ==\n\n";

echo "=== [1] profil-form save fonksiyonu GENIS pencere (4200 once, 200 sonra) ===\n";
$anchor="const f1=document.getElementById('f1').value.trim()";
$p=strpos($src,$anchor);
if($p!==false){ echo substr($src,max(0,$p-4200),4400)."\n"; }
else echo "  bulunamadi (anchor degisti mi?)\n";

echo "\n=== [2] P'nin sayfa-yuklenirken OKUNDUGU yer(ler) ===\n";
foreach(['P=JSON.parse(localStorage.getItem(','P = JSON.parse(localStorage.getItem(','window.P=JSON.parse(','let P=','var P=','P={f1:','function loadProfile','function initProfile'] as $pat){
  $off=0;$n=0;
  while(($pp=strpos($src,$pat,$off))!==false && $n<4){
    echo "  [$pat @$pp] ...".substr($src,max(0,$pp-80),260)."...\n\n"; $off=$pp+strlen($pat);$n++;
  }
}

echo "=== [3] 'ch_prof_v3' TUM kullanim yerleri ===\n";
$off=0;$n=0;
while(($p=strpos($src,'ch_prof_v3',$off))!==false && $n<12){
  echo "  [$n] @$p ...".substr($src,max(0,$p-90),220)."...\n\n"; $off=$p+10;$n++;
}

echo "\n== BITTI ==\n";
