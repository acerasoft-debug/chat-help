<?php
/**
 * ChatHelp — dump-feats — plan feature dizileri (feats:) + onboarding STEPS metnini
 *  DOGRULA (OKUR). Amac: paketlere free-fax satiri + onboarding'e gonder/fax notu.
 * KULLANIM: pull2.php?key=...&files=dump-feats.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-feats (".strlen($src)." B) ==\n\n";

echo "=== [1] 'feats:' her yer (±40 before, 380 after) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'feats:',$off))!==false && $n<10){
  echo "  [$n] @$p ...".substr($src,max(0,$p-40),420)."...\n\n";
  $off=$p+6;$n++;
}

echo "=== [2] plan tanim objesi (Basic/Pro/Elite + price + feats civari) ===\n";
$p=strpos($src,'feats:');
if($p!==false){ echo "  -- ilk feats blok baglami (±700) --\n  ".substr($src,max(0,$p-700),1500)."\n"; }

echo "\n=== [3] onboarding STEPS (CH_ONBOARD_TOUR) — de/tr son adim ===\n";
$p=strpos($src,'ch-onboard-tour');
if($p!==false){ echo substr($src,$p,1600)."\n\n...\n"; }
/* son adim metinleri */
foreach(['sign and send','unterschreiben und','imzala','Finished PDF','fertige PDF','versenden'] as $kw){
  $q=strpos($src,$kw);
  if($q!==false){ echo "  [$kw @$q] ...".substr($src,max(0,$q-160),300)."...\n\n"; break; }
}
echo "\n== BITTI ==\n";
