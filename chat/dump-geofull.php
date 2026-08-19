<?php
/** ChatHelp — dump-geofull (SADECE OKUR) — F2-1 son kanit turu:
 *  [1] geo.php'nin TAM icerigi (lawCountry hesaplamasi guvenli mi? desteklenmeyen
 *      ulkeler icin fallback var mi, yoksa ham ISO kod mu donuyor?)
 *  [2] CC_DATA'nin TAM listesi (kac ulke, GB/US var mi?)
 *  [3] "ch_geo_multi" akisi (ikinci geo sistemi) TAM govdesi
 *  [4] CATS icinde country: alani olmayan/gecersiz deger olan var mi (riskli)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$geo=(string)@file_get_contents("$D/geo.php");

echo "════════════════ geo.php TAM ICERIK ════════════════\n";
echo $geo."\n";

echo "\n════════════════ CC_DATA TAM LISTE ════════════════\n";
$p=strpos($idx,'CC_DATA={');
if($p!==false){
    $e=strpos($idx,'};',$p);
    echo substr($idx,$p,($e-$p+2))."\n";
} else echo "(bulunamadi)\n";

echo "\n════════════════ ch_geo_multi akisi ════════════════\n";
$p=strpos($idx,'ch_geo_multi');
if($p!==false){
    echo substr($idx,max(0,$p-400),1600)."\n";
} else echo "(bulunamadi)\n";

echo "\n════════════════ CATS country: degerleri (ornek) ════════════════\n";
preg_match_all('/country:"([A-Z]{0,3})"/',$idx,$m);
$counts=array_count_values($m[1]);
foreach($counts as $k=>$v) echo "  '$k' -> $v kez\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
