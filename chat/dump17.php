<?php
/** ChatHelp — dump17: Meine Anträge (#pnl-ant) yapısı + geri butonları */
header('Content-Type: text/plain; charset=UTF-8');
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo substr($s,max(0,$p-$b),$l)."\n";}

echo "### #pnl-ant HTML (panel iskelet) ###\n";
R($idx,'id="pnl-ant"',0,1100,'pnl-ant açılış');

echo "\n### Tüm pnl-back-btn geçişleri ###\n";
$off=0;$i=0;
while(($p=strpos($idx,'pnl-back-btn',$off))!==false && $i<8){
  echo "  [".$i."] @".$p.": ".substr($idx,max(0,$p-40),140)."\n\n";
  $off=$p+5;$i++;
}

echo "\n### Meine Anträge render (loadAnt / renderAnt / showAnt) ###\n";
foreach(['function loadAnt','function renderAnt','function showAnt','function openAnt','pnl-ant'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($idx,'ant-empty',-400,500,'Meine Anträge liste/empty render');

echo "\n### 'Zurück' / geri ile ilgili her şey ###\n";
$off=0;$i=0;
while(($p=stripos($idx,'zurück',$off))!==false && $i<6){
  echo "  [z$i] @".$p.": ".substr($idx,max(0,$p-60),130)."\n\n";
  $off=$p+5;$i++;
}
echo "\n### ch-back / ch-antraege-fix marker ###\n";
foreach(['ch-back-chip','ch-antraege-fix','injectBackChip','#ch-back'] as $k){
  echo "  '$k' -> ".(strpos($idx,$k)!==false?'VAR':'yok')."\n";
}

echo "\n=== BİTTİ. SİL: rm dump17.php ===\n";
