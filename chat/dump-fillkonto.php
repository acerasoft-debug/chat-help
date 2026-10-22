<?php
/* dump-fillkonto (READ-ONLY) — fillKonto() TAM + Konto Profil blogu deger
   element ID'leri + chUser(). Profil'i aktif Absender-Profil'e baglamak icin.
   KULLANIM: pull2.php?key=...&files=dump-fillkonto.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-fillkonto (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [1] fillKonto() TAM ===\n";
$p=strpos($s,'function fillKonto');
if($p!==false){ $e=strpos($s,"\n    function ",$p+10); if($e===false||$e-$p>1600)$e=$p+1400; echo substr($s,$p,$e-$p)."\n"; }

echo "\n=== [2] Profil blogu deger elemanlari (GELTENDES RECHT civari geriye) ===\n";
$p=strpos($s,'GELTENDES RECHT');
if($p!==false){ echo trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-1100),1250)))."\n"; }

echo "\n=== [3] chUser() tanimi ===\n";
$p=strpos($s,'function chUser'); if($p===false)$p=strpos($s,'chUser=function');
if($p!==false) echo "  ".W($s,$p,0,240)."\n";

echo "\n=== [4] fillKonto nerede cagriliyor (fillKonto()) ===\n";
$off=0;$c=0; while(($p=strpos($s,'fillKonto(',$off))!==false && $c<6){ echo "  [".$p."] …".W($s,$p,40,40)."…\n"; $off=$p+8;$c++; }

echo "\n=== [5] Profil deger ID adaylari (konto-*/prof-* getElementById) ===\n";
if(preg_match_all('/getElementById\([\'"]([a-z0-9\-]*(?:name|email|addr|adres|land|recht|prof)[a-z0-9\-]*)[\'"]\)/i',$s,$m)){ echo "  ".implode(', ',array_unique($m[1]))."\n"; }

echo "\nBITTI.\n";
