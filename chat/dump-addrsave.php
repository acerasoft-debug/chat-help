<?php
/**
 * ChatHelp — dump-addrsave — 'Persönliche Daten' (Straße/PLZ&Ort) kaydetme mekanizmasi +
 *  belge uretiminin adres-eksik kontrolu (OKUR). Adres girilse de kaydolmuyor bug'i.
 * KULLANIM: pull2.php?key=...&files=dump-addrsave.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-addrsave (".strlen($src)." B) ==\n\n";

echo "=== [1] 'STRASSE' / 'Straße & Nr' form-alani + kaydetme handler ===\n";
$off=0;$n=0;
while(($p=stripos($src,'STRASSE',$off))!==false && $n<6){
  echo "  [$n] @$p ...".substr($src,max(0,$p-160),340)."...\n\n"; $off=$p+7;$n++;
}

echo "=== [2] profil-formu save/input listener (f3 alani = adres) ===\n";
$off=0;$n=0;
while(($p=strpos($src,"'f3'",$off))!==false && $n<10){
  echo "  [$n] @$p ...".substr($src,max(0,$p-100),200)."...\n\n"; $off=$p+4;$n++;
}
$off=0;
while(($p=strpos($src,'"f3"',$off))!==false && $n<20){
  echo "  [dq$n] @$p ...".substr($src,max(0,$p-100),200)."...\n\n"; $off=$p+4;$n++;
}

echo "=== [3] genDoc/dilekce-oncesi adres-eksik kontrolu ===\n";
foreach(['fehlt.*Adresse','Adresse.*fehlt','missing.*address','adres.*eksik','needAddr','requireAddr','hasAddr'] as $pat){
  if(preg_match_all('/.{80}'.$pat.'.{80}/i',$src,$m)){
    foreach(array_slice($m[0],0,3) as $seg){ echo "  [$pat] ...".$seg."...\n\n"; }
  }
}
echo "  'hasAddr' sayisi: ".substr_count($src,'hasAddr')."\n";

echo "\n== BITTI ==\n";
