<?php
/** ChatHelp — dump-country: ülke (CC) sistemi — Fransa anahtarı + belge filtreleme için */
header('Content-Type: text/plain; charset=UTF-8');
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo substr($s,max(0,$p-$b),$l)."\n";}

echo "### CC (ülke) sistemi ###\n";
foreach(['let CC','var CC','const CC','CC=','CC_DATA','function setCC','function selCC','function setCountry','function selectCountry','ch_country','sb-country','sb-cname'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($idx,'CC_DATA',0,900,'CC_DATA (ülke verisi)');
R($idx,'CC=',-40,160,'CC ataması (varsayılan)');
R($idx,'sb-country',-60,600,'ülke seçici (sb-country)');
R($idx,'setCC',0,500,'setCC/ülke değiştirme fonksiyonu');
R($idx,'selectCountry',0,500,'selectCountry (varsa)');
R($idx,"'country':CC",-200,300,'genDoc country gönderimi');
R($idx,'country:CC',-150,250,'country:CC');

echo "\n### Kategori render (ülkeye göre filtre için) ###\n";
R($idx,'function showAllCats',0,700,'showAllCats');
R($idx,'function showCat',0,500,'showCat');

echo "\n### Ülke seçim ekranı / liste ###\n";
R($idx,'CC_LIST',-40,500,'CC_LIST (varsa ülke listesi)');
R($idx,'Deutschland',-200,400,'Deutschland (ülke listesi/seçici)');
R($idx,'France',-100,300,'France (zaten var mı?)');

echo "\n### api.php lawSystems (FR hazır mı) ###\n";
R($api,"'FR'",-60,300,'lawSystems FR');

echo "\n=== BİTTİ. SİL: rm dump-country.php ===\n";
