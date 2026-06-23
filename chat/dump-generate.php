<?php
/** ChatHelp — dump-generate: api.php doGenerate dil/ülke mantığı (Fransızca belge metni için son kontrol) */
header('Content-Type: text/plain; charset=UTF-8');
$api=@file_get_contents(__DIR__.'/api.php');
if($api===false) exit("api.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo substr($s,max(0,$p-$b),$l)."\n";}

echo "### lawSystems (FR hazır mı + yapısı) ###\n";
R($api,'$lawSystems',0,900,'lawSystems tanımı');
R($api,"'FR'",-40,260,'lawSystems FR satırı');

echo "\n### doGenerate — sistem promptu + dil yönlendirmesi ###\n";
R($api,'function doGenerate',0,500,'doGenerate başı');
R($api,'$sysLaw',-60,500,'sysLaw kullanımı');
R($api,'$country',-40,260,'country değişkeni');

echo "\n### Belge DİLİ: Almanca zorlaması var mı? (native açıklama mantığı) ###\n";
foreach(['auf Deutsch','Deutsch','native','muttersprache','$lang','explanation','Erklärung','primär','primary'] as $k){
  $p=strpos($api,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($api,'auf Deutsch',-260,500,'\"auf Deutsch\" bağlamı (varsa)');
R($api,'native',-200,420,'native açıklama bağlamı');
R($api,'$lang',-160,320,'lang kullanımı');

echo "\n### vtr_/bew_ override (fr_ için de gerek olabilir) ###\n";
R($api,"'vtr_'",-120,300,'vtr_ override');
R($api,'strpos($docType',-40,260,'docType prefix kontrolü');

echo "\n=== BİTTİ. SİL: rm dump-generate.php ===\n";
