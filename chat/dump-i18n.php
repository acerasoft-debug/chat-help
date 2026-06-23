<?php
/** ChatHelp — dump-i18n: çeviri mekanizması (tüm sayfayı FR'ye çevirecek katman için) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
$tr =@file_get_contents(__DIR__.'/translate.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo "[@".$p."]\n".substr($s,max(0,$p-$b),$l)."\n";}

echo "### translate.php — TAM içerik (istek/yanıt formatı) ###\n";
echo $tr===false ? "(translate.php yok!)\n" : $tr."\n";

echo "\n\n### index.php: translate.php nasıl çağrılıyor (fetch) ###\n";
R($idx,'translate.php',-400,900,'translate.php fetch çağrısı');

echo "\n### index.php: i18n katmanı (etiket çevirisi mantığı) ###\n";
R($idx,'ch-i18n-layer',0,1600,'ch-i18n-layer script başı');
R($idx,'function applyTr',0,700,'applyTr (varsa)');
R($idx,'function trLabels',0,700,'trLabels (varsa)');

echo "\n### Arayüz dili değişkeni (UL) + uygula ###\n";
foreach(['let UL','var UL','const UL','UL=','function applyUI','UI_TEXTS','function applyCC'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($idx,'function applyUI',0,900,'applyUI gövdesi');
R($idx,'UI_TEXTS',0,700,'UI_TEXTS (arayüz metinleri)');

echo "\n### Sabit Almanca metinler (çevrilmeyen örnekler) ###\n";
R($idx,'Ihr Recht',-120,300,'\"Ihr Recht\" başlığı');
R($idx,'Schutz',-120,260,'\"Schutz\"');
R($idx,'Meine Anträge',-160,300,'Meine Anträge (menü)');
R($idx,'Anträge',-120,220,'Anträge');

echo "\n=== BİTTİ. SİL: rm dump-i18n.php ===\n";
