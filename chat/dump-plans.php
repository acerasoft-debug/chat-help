<?php
/* dump-plans (READ-ONLY) — paket/fiyat sayfasi yapisi: plan kartlari, ozellik
   listesi, plan adlari (Basic/Pro/Elite) + nasil render ediliyor. Ucretsiz-fax
   satirini eklemek icin.
   KULLANIM: pull2.php?key=...&files=dump-plans.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "dump-plans (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php'); if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [1] plan sayfasi id/panel ===\n";
foreach(['pnl-plans','pnl-plan','chOpenPlans','renderPlans','plan-card','pc-','PLANS=','ch-plans'] as $k){
  $p=strpos($s,$k); if($p!==false) echo "  '".$k."' [".$p."] ".W($s,$p,10,110)."\n";
}
echo "\n=== [2] plan ozellik listesi (feats/features) ornekleri ===\n";
$off=0;$c=0; while(($p=strpos($s,'Fallanalysen',$off))!==false && $c<3){ echo "  [".$p."] …".W($s,$p,220,120)."…\n\n"; $off=$p+10;$c++; }

echo "\n=== [3] plan tanimlari (Basic/Pro/Elite + feats dizisi) ===\n";
foreach(['basic:{','pro:{','elite:{','"Basic"','"Pro"','"Elite"','feats:','features:','name:"Basic'] as $k){
  $p=strpos($s,$k); if($p!==false) echo "  '".$k."' [".$p."] ".W($s,$p,0,160)."\n";
}
echo "\n=== [4] plan kart HTML uretimi (per-plan feats.map / <li>) ===\n";
$p=strpos($s,'chOpenPlans'); if($p===false)$p=strpos($s,'renderPlans');
if($p!==false){ echo W($s,$p,0,500)."\n"; }
echo "\n=== [5] '40'/'200'/'Dokumente pro Monat' (kota satirlari) ===\n";
$off=0;$c=0; while(($p=strpos($s,'pro Monat',$off))!==false && $c<4){ echo "  [".$p."] …".W($s,$p,80,40)."…\n"; $off=$p+8;$c++; }
echo "\nBITTI.\n";
