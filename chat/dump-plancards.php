<?php
/**
 * ChatHelp — dump-plancards — plan SECIM kartlari (feature bullet'lari) + onboarding
 *  "nasil calisir" icerigini + gonderim seciciyi kapali tutan gate'i DOGRULA (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-plancards.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{40,})/','***MASKED***',$s); }
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-plancards (".strlen($src)." B) ==\n\n";
function ctx($src,$needle,$before,$len,$max=3){
  $off=0;$n=0;
  while(($p=stripos($src,$needle,$off))!==false && $n<$max){
    echo "  [$n] @$p ...".mask(substr($src,max(0,$p-$before),$len))."...\n\n";
    $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  '$needle' bulunamadi\n";
}

echo "=== [1] plan kart feature listeleri (Dokumente/Monat, unbegrenzt) ===\n";
foreach(['Dokumente/Monat','Dokumente / Monat','40 Dokumente','200 Dokumente','unbegrenzt','Nutzer','planCard','plan-card','pk-card','tarif','Tarife','feats:','features:'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n  -- 'unbegrenzt' civari (Elite feature listesi) --\n";
ctx($src,'unbegrenzt',300,600,2);

echo "\n=== [2] showPaywall tanim + plan kart HTML civari ===\n";
ctx($src,'function showPaywall',0,700,1);
ctx($src,'showPaywall=',0,500,1);

echo "\n=== [3] plan feature dizileri ('Dokumente' bullet civari) ===\n";
ctx($src,'Dokumente',260,420,3);

echo "\n=== [4] onboarding / nasil calisir icerigi (ch_onboard) ===\n";
ctx($src,'ch_onboard',160,360,3);
echo "\n  -- onboarding adim metinleri ('Willkommen' civari) --\n";
ctx($src,'Willkommen bei',120,360,2);

echo "\n=== [5] Konto'da fax kotasi gosterimi (CH_FAX_QUOTA Konto kismi) ===\n";
ctx($src,'Konto gosterimi',0,200,1);
foreach(['renderFaxKonto','faxKontoRow','ch-fax-konto','Gratis-Fax'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n== BITTI ==\n";
