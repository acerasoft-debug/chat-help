<?php
/**
 * ChatHelp — dump-pform — showProfileForm + KAYDET akisi + _lastGenAns kullanimi (OKUR):
 *  profil doldurulunca dilekce cevaplarini (ans) kaybetmeden uretimi devam ettirmek icin.
 * KULLANIM: pull2.php?key=...&files=dump-pform.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-pform (".number_format(strlen($src))." B) ==\n\n";

echo "=== [A] showProfileForm tanimi (TAM) ===\n";
$p=strpos($src,'function showProfileForm');
if($p!==false){ $e=strpos($src,"\n  function ",$p+20); if($e===false||$e-$p>3200)$e=$p+3200; echo substr($src,$p,$e-$p)."\n\n"; }
else echo "  (yok)\n\n";

echo "=== [B] _lastGenAns kullanim yerleri ===\n";
$off=0;$n=0;
while(($p=strpos($src,'_lastGenAns',$off))!==false && $n<8){
  echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-80),200))."...\n";
  $off=$p+11; $n++;
}

echo "\n=== [C] profil kaydet butonu / saveProfileForm / pf-save ===\n";
foreach(['saveProfileForm','pf-save','profSave','id="pf-','onclick="saveProf','Profil speichern','showQuestions('] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<3){
    echo "  [$pat @$p]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-50),200))."...\n";
    $off=$p+strlen($pat); $n++;
  }
}

echo "\n=== [D] 'vervollständigen Sie zuerst Ihr Profil' (sunucu/istemci hata) ===\n";
foreach(['vervollständigen Sie zuerst','vervollstaendigen','zuerst Ihr Profil','Profil vervoll'] as $pat){
  $p=strpos($src,$pat);
  echo "  '$pat': ".($p===false?'index.php de yok (sunucu/api.php)':"@$p")."\n";
}
echo "\n== BITTI ==\n";
