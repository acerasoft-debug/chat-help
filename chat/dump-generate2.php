<?php
/** ChatHelp — dump-generate2: nativeNote bloğunun TAMAMI + ulang + base sysLaw (FR belge dili yaması için) */
header('Content-Type: text/plain; charset=UTF-8');
$api=@file_get_contents(__DIR__.'/api.php');
if($api===false) exit("api.php yok\n");
function W($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo "[offset $p]\n".substr($s,max(0,$p-$b),$l)."\n";}

echo "### nativeNote bloğu TAM (tanım) ###\n";
W($api,'$nativeNote',-500,2200,'nativeNote ilk geçiş (tanım + tüm metin)');

echo "\n### nativeNote prompt'a nasıl ekleniyor (2. geçiş) ###\n";
$p1=strpos($api,'$nativeNote');
$p2=$p1!==false?strpos($api,'$nativeNote',$p1+10):false;
if($p2!==false){ echo "[offset $p2]\n".substr($api,max(0,$p2-300),700)."\n"; } else echo "(2. geçiş yok)\n";

echo "\n### \$ulang nereden geliyor ###\n";
W($api,'$ulang',-40,200,'ulang tanımı');

echo "\n### \$LN dil adı tablosu ###\n";
W($api,'$LN',-10,500,'LN tablosu (dil adları)');

echo "\n### base \$sysLaw (vtr_/bew_ DIŞINDA genel atama) ###\n";
W($api,'$sysLaw =',-20,260,'sysLaw ilk atama');
W($api,'lawSystems[',-30,200,'lawSystems[country] kullanımı');

echo "\n=== BİTTİ. SİL: rm dump-generate2.php ===\n";
