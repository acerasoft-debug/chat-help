<?php
/** ChatHelp — dump-country-picker: ülke seçici menüsünün TAM HTML'i (sabit mi, dinamik mi) — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");

function WIN($s,$needle,$b,$l,$lbl){echo "\n──────── $lbl ────────\n";$p=strpos($s,$needle);if($p===false){echo "(yok: $needle)\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "###### 1) country-grid TAM HTML (sabit mi CC_DATA'dan mı üretiliyor) ######\n";
WIN($idx,'country-grid',-100,3500,'country-grid TAM bağlam');

echo "\n\n###### 2) '.co' class'lı elemanlar kaç kez geçiyor + her geçişin bağlamı ######\n";
$ps=ALL($idx,'class="co"');
echo "class=\"co\" (tam eşleşme) -> ".count($ps)." kez\n";
$ps2=ALL($idx,"data-cc=");
echo "data-cc= -> ".count($ps2)." kez, konumlar: ".implode(',',array_slice($ps2,0,20))."\n";
foreach(array_slice($ps2,0,20) as $p){ echo "\n[@$p]\n".substr($idx,max(0,$p-80),140)."\n"; }

echo "\n\n###### 3) CC_DATA TAM tanımı (hangi ülkeler zaten sabit tanımlı) ######\n";
WIN($idx,'CC_DATA={',-10,1800,'CC_DATA tanımı');
WIN($idx,'CC_DATA = {',-10,1800,'CC_DATA tanımı (boşluklu, alternatif)');

echo "\n\n###### 4) Ülke seçiciyi AÇAN kod (hangi buton, hangi fonksiyon country-grid'i render ediyor) ######\n";
foreach(['function openCountry','function showCountry','function renderCountryGrid','sb-country'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
WIN($idx,'sb-country',-40,900,'sb-country tıklama/açma bağlamı');

echo "\n=== BİTTİ. SİL: rm dump-country-picker.php ===\n";
