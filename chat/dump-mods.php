<?php
/** ChatHelp — dump-mods: fixpack sonrasi dogrulama. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("okunamadi\n");
echo "dump-mods (idx=".strlen($idx)." bayt)\n\n──── markerlar ────\n";
foreach(['CH_CV_STUDIO','CH_VST','CH_FIXPACK10','CH_NAVY_THEME','CH_PREMIUM_UI2'] as $m)
  echo "  $m: ".(strpos($idx,$m)!==false?'VAR':'YOK')."\n";
function OCC($s,$n,$l,$c=200,$m=3){echo "\n──── $l ────\n";$o=0;$k=0;
  while(($p=stripos($s,$n,$o))!==false&&$k<$m){echo "@$p: ".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$c),$c*2)))."\n---\n";$o=$p+strlen($n);$k++;}
  if(!$k)echo "  (yok)\n";}
OCC($idx,'10x','10x ibaresi');
OCC($idx,'10×','10× ibaresi');
OCC($idx,'Ihre Wahl wird gespeichert','2. design blogu kaynagi',350,2);
OCC($idx,'Interface-Sprache','Interface-Sprache kaynagi',250,2);
echo "\n=== BITTI ===\n";
