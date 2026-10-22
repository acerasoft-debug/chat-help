<?php
/**
 * ChatHelp — dump-purge — purge() + doLogout() TAM govdesi + nerede cagriliyor (OKUR).
 *  KRITIK: hesap degisiminde 'ch_profiles' (coklu-profil dizisi) temizlenmiyor olabilir
 *  -> eski kullanicinin adi/e-postasi cihazda kalip yeni hesaba karisiyor.
 * KULLANIM: pull2.php?key=...&files=dump-purge.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-purge (".strlen($src)." B) ==\n\n";

echo "=== [1] function purge() TAM govde ===\n";
$p=strpos($src,'function purge()');
if($p!==false) echo substr($src,$p,700)."\n\n"; else echo "  yok\n";

echo "=== [2] purge( cagri yerleri ===\n";
$off=0;$n=0;
while(($p=strpos($src,'purge(',$off))!==false && $n<8){
  echo "  [$n] @$p ...".substr($src,max(0,$p-90),160)."...\n"; $off=$p+6;$n++;
}

echo "\n=== [3] function doLogout() TAM govde ===\n";
$p=strpos($src,'function doLogout()');
if($p!==false) echo substr($src,$p,700)."\n\n"; else echo "  yok\n";

echo "=== [4] 'ch_profiles' TUM referanslari ===\n";
$off=0;$n=0;
while(($p=strpos($src,'ch_profiles',$off))!==false && $n<10){
  echo "  [$n] @$p ...".substr($src,max(0,$p-70),160)."...\n"; $off=$p+11;$n++;
}

echo "\n=== [5] login-basari sonrasi coklu-profil senkron (P.f1 atamasi TAM baglam) ===\n";
$p=strpos($src,"P.f1   = d.user.name.split");
if($p!==false) echo "  ...".substr($src,max(0,$p-260),520)."...\n";

echo "\n== BITTI ==\n";
