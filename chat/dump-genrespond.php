<?php
/** ChatHelp — dump-genrespond: api.php'nin belgeyi dondurdugu noktalar + sender blok sablonu. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$api = @file_get_contents(__DIR__.'/api.php');
if ($api === false) exit("api.php okunamadi\n");
echo "dump-genrespond (api=".strlen($api)." bayt)\n";

function OCC($s,$needle,$lbl,$ctx=260,$max=8){
  echo "\n──── $lbl ────\n";
  $o=0;$n=0;
  while(($p=strpos($s,$needle,$o))!==false && $n<$max){
    echo "  @$p:\n".substr($s,max(0,$p-$ctx),$ctx*2+strlen($needle))."\n  ---\n";
    $o=$p+strlen($needle); $n++;
  }
  if(!$n) echo "  (yok)\n";
}

OCC($api,"'document'","'document' anahtari gecen yerler");
OCC($api,'respond([','respond( cagrilarindan belge donenler',120,14);
OCC($api,'Telefon','sender blok sablonu (Telefon)',300,4);
OCC($api,'Geburtsdatum','sender blok sablonu (Geburtsdatum)',300,4);
echo "\n=== BITTI ===\n";
