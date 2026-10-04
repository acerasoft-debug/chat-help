<?php
/**
 * ChatHelp — dump-plan — Plan/paket ozellik listeleri + Konto + intro + fax-kota +
 *  "Schreiben Modus" yerlerini DOGRULA (OKUR). Amac: paketlere free-fax ekleme,
 *  Schreiben Modu kapisi, intro guncelleme icin dogru anchor'lar.
 * KULLANIM: pull2.php?key=...&files=dump-plan.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{40,})/','***MASKED***',$s); }
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-plan (".strlen($src)." B) ==\n\n";

function ctx($src,$needle,$before,$len,$max=3){
  $off=0;$n=0;
  while(($p=stripos($src,$needle,$off))!==false && $n<$max){
    echo "  [$n] @$p ...".mask(substr($src,max(0,$p-$before),$len))."...\n\n";
    $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  '$needle' bulunamadi\n";
}

echo "=== [1] fax-kota sabitleri (Basic5/Pro25/Elite100, Gratis-Fax) ===\n";
foreach(['CH_FAX_QUOTA','Gratis-Fax','chFaxQuota','faxQuota','Basic 5','Basic5','free:5','remaining'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n  -- 'chFaxQuota' tanim civari --\n";
ctx($src,'function chFaxQuota',40,420,1);
ctx($src,'CH_FAX_QUOTA',80,360,2);

echo "\n=== [2] Plan/paket ozellik listeleri (13,99 / 29,99 / 99,99 civari) ===\n";
foreach(['13,99','29,99','99,99','Basic','Elite'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n  -- ilk '13,99' civari (plan kart/feature) --\n";
ctx($src,'13,99',200,700,2);

echo "\n=== [3] Schreiben Modus / Schreiben Modu ===\n";
foreach(['Schreiben-Modus','Schreiben Modus','Schreibmodus','schreibmodus','Schreiben-Mod','sendMode','send_mode','versandmodus'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}

echo "\n=== [4] intro / ilk giris / 'wie funktioniert' / onboarding ===\n";
foreach(['wie funktioniert','So funktioniert','nasil calis','onboard','ch_onboard','intro','hero-sub','Willkommen','ilk giris'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n  -- hero alt-satiri (erstellen und versenden) civari --\n";
ctx($src,'versenden',120,300,3);

echo "\n=== [5] Konto paneli (pnl-konto / plan gosterimi) ===\n";
foreach(['pnl-konto','pnl-account','id="konto','Mein Konto','Konto"'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n== BITTI ==\n";
