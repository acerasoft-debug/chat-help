<?php
/** ChatHelp — dump-hubcheck: hub kartinin baglandigi ogeler var mi? SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("okunamadi\n");
echo "dump-hubcheck (idx=".strlen($idx)." bayt)\n\n";

echo "──── markerlar ────\n";
foreach(['CH_MODHUB','CH_MODPACK','CH_FIXPACK10','CH_CV_STUDIO','CH_VST'] as $m)
  echo "  $m: ".(strpos($idx,$m)!==false?'VAR':'YOK')."\n";

echo "\n──── ankraj ogeleri (statik HTML'de var mi) ────\n";
foreach(['class="wlc-quick"','class="wlc-quick ','wlc-quick','class="sb-photo-btn"','openFall()','id="sb"','class="pnl"'] as $needle){
  $n=substr_count($idx,$needle);
  echo "  '$needle' -> $n kez\n";
}

echo "\n──── wlc-quick ilk gorunum baglami (statik mi JS mi) ────\n";
$p=strpos($idx,'wlc-quick');
if($p!==false){
  // en yakin < isaretine bak: statik div mi yoksa JS string mi
  echo substr($idx,max(0,$p-120),400)."\n";
} else echo "(wlc-quick hic yok)\n";

echo "\n──── addDiscover fonksiyonu yerinde mi ────\n";
$p=strpos($idx,'ch-hub-card');
echo $p!==false ? "ch-hub-card referansi VAR @".$p."\n" : "ch-hub-card YOK\n";

echo "\n──── sb-photo-btn ilk 2 gorunum (nav enjeksiyon hedefi) ────\n";
$o=0;$k=0;
while(($p=strpos($idx,'sb-photo-btn',$o))!==false && $k<2){
  echo "@$p: ".trim(preg_replace('/\s+/',' ',substr($idx,max(0,$p-40),260)))."\n---\n"; $o=$p+5;$k++;
}
echo "\n=== BITTI ===\n";
