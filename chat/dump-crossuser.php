<?php
/**
 * ChatHelp — dump-crossuser — KRITIK: belgede baska kullanicinin adi/e-postasi cikiyor
 *  (adres dogru, isim/email yanlis kullaniciya ait). Olasi neden: $ph (placeholder->deger)
 *  dizisinin insasi, profil yuklemesi, veya istekler-arasi cache/static degisken sizintisi.
 *  SADECE OKUR.
 * KULLANIM: pull2.php?key=...&files=dump-crossuser.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/api.php';
$src=@file_get_contents($f);
if($src===false){ echo "api.php YOK\n"; exit; }
echo "== dump-crossuser (".strlen($src)." B) ==\n\n";

echo "=== [1] istekler-arasi sizinti riski: static/global degiskenler ===\n";
foreach(['static $profile','static $ph','static $repl','static $user','apcu_','apc_store','$GLOBALS[','global $profile','global $ph'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}

echo "\n=== [2] \$ph dizisi insasi (VORNAME/NACHNAME/EMAIL atamalari) ===\n";
$off=0;$n=0;
while(($p=strpos($src,"\$ph[",$off))!==false && $n<12){
  echo "  [$n] @$p ...".substr($src,max(0,$p-70),200)."...\n"; $off=$p+4;$n++;
}
if($n===0) echo "  '\$ph[' bulunamadi\n";

echo "\n=== [3] \$profile nasil yukleniyor (fonksiyon + anahtar: user_id/session/token) ===\n";
foreach(['function loadProfile','function getProfile','$profile = ','$profile=getProfile','$profile=loadProfile'] as $kw){
  $p=strpos($src,$kw);
  if($p!==false){ echo "  [$kw] @$p ...".substr($src,$p,320)."...\n\n"; }
}

echo "\n=== [4] profile SQL/dosya sorgusu (WHERE anahtari: id/email/token/session) ===\n";
$off=0;$n=0;
while(($p=stripos($src,'FROM users',$off))!==false && $n<6){
  echo "  [$n] @$p ...".substr($src,max(0,$p-200),400)."...\n\n"; $off=$p+10;$n++;
}
foreach(['FROM profiles','profiles.json','WHERE id=','WHERE token=','WHERE email='] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}

echo "\n=== [5] opcache/apcu kullanimi (surec-arasi kalici cache riski) ===\n";
foreach(['opcache_','apcu_fetch','apcu_store','memcache','redis'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}

echo "\n== BITTI ==\n";
