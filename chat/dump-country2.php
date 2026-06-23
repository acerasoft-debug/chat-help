<?php
/** ChatHelp — dump-country2: setCountry + welcome grid + CAT yapısı (Fransa modülü için son parça) */
header('Content-Type: text/plain; charset=UTF-8');
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo substr($s,max(0,$p-$b),$l)."\n";}

echo "### setCountry (ülke değiştirme — programatik çağırmak için) ###\n";
R($idx,'function setCountry',0,900,'setCountry gövde');

echo "\n### Welcome kategori grid'i (.wcat-grid) — kategoriler nereden? ###\n";
R($idx,'class="wcat-grid"',-50,1400,'wcat-grid HTML (hardcoded mı, CATS mı?)');

echo "\n### showCat (CATS kullanan) var mı? ###\n";
R($idx,'function showCat(',0,400,'showCat( tam');

echo "\n### CAT_DOCS / CAT_LABELS (showCatDocs sistemi) ###\n";
R($idx,'CAT_DOCS=',0,500,'CAT_DOCS');
R($idx,'CAT_LABELS=',0,400,'CAT_LABELS');

echo "\n### PK (profil localStorage anahtarı) + kaydetme ###\n";
R($idx,'const PK',-20,120,'PK anahtarı');
R($idx,'savePref',0,300,'savePref (tercih kaydetme)');
R($idx,'function savePref',0,300,'savePref fonksiyon');

echo "\n### dil ayarlama (UL / setFlag) ###\n";
R($idx,'function setFlag',0,300,'setFlag');
R($idx,'UL=',-30,120,'UL ataması');

echo "\n=== BİTTİ. SİL: rm dump-country2.php ===\n";
