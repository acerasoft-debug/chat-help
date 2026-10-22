<?php
/**
 * ChatHelp — dump-prof — prof() fonksiyonu + profile-payload olusturma + hesap-degisimi
 *  temizlik listesi (identity-bind) DOGRULA (OKUR). Amac: baska kullanicinin
 *  isim/e-postasinin cihazda kalip yeni kullaniciya karismasi.
 * KULLANIM: pull2.php?key=...&files=dump-prof.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-prof (".strlen($src)." B) ==\n\n";

echo "=== [1] function prof() tanimi ===\n";
$p=strpos($src,'function prof(');
if($p!==false) echo substr($src,$p,600)."\n\n"; else echo "  yok\n";

echo "=== [2] 'profile:' payload olusturma (fetch body icinde) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'profile:prof()',$off))!==false && $n<6){
  echo "  [$n] @$p ...".substr($src,max(0,$p-140),260)."...\n\n"; $off=$p+14;$n++;
}
if($n===0){
  $off=0;
  while(($p=strpos($src,'profile:',$off))!==false && $n<8){
    echo "  [alt$n] @$p ...".substr($src,max(0,$p-100),220)."...\n\n"; $off=$p+8;$n++;
  }
}

echo "=== [3] login/hesap-degisim temizlik listesi (identity-bind) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'ch_prof',$off))!==false && $n<15){
  $seg=trim(substr($src,max(0,$p-60),140));
  echo "  [$n] @$p ...".$seg."...\n"; $off=$p+7;$n++;
}
echo "\n  -- removeItem listeleri (hesap degisimi temizligi) --\n";
$off=0;$n=0;
while(($p=strpos($src,'.forEach(function(k){ try{ localStorage.removeItem(k)',$off))!==false && $n<4){
  echo "  [$n] @$p ...".substr($src,max(0,$p-260),300)."...\n\n"; $off=$p+40;$n++;
}

echo "=== [4] login basarili sonrasi ne kaydediliyor (ch_user setItem) ===\n";
$off=0;$n=0;
while(($p=strpos($src,"setItem('ch_user'",$off))!==false && $n<6){
  echo "  [$n] @$p ...".substr($src,max(0,$p-160),260)."...\n\n"; $off=$p+18;$n++;
}
echo "\n== BITTI ==\n";
