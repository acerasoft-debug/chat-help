<?php
/** ChatHelp — dump-welcome: karsilama ekrani ornek cumleleri (yapi + id'ler). SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx === false) exit("index.php okunamadi\n");
echo "dump-welcome (idx=".strlen($idx)." bayt)\n";

function WIN($s,$needle,$lbl,$before=150,$len=1200,$max=4){
  echo "\n──── $lbl ────\n";
  $o=0;$n=0;
  while(($p=strpos($s,$needle,$o))!==false && $n<$max){
    echo "@$p:\n".substr($s,max(0,$p-$before),$len)."\n---\n";
    $o=$p+strlen($needle); $n++;
  }
  if(!$n) echo "  (yok)\n";
}

WIN($idx,'Vermieter erhöht','ornek cumle 1 gectigi yerler',300,900,4);
WIN($idx,'id="wc1"','wc1 markup',200,1000,2);
WIN($idx,'wlc-p','karsilama paragraf/altyapi',200,900,3);
WIN($idx,"c1:",'UI_TEXTS c1 tanimlari (ilk 3 dil)',80,700,3);
echo "\n=== BITTI ===\n";
