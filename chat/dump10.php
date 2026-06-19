<?php
/**
 * ChatHelp — dump10 (SADECE OKUR): CATS / DOCS yapısı (Verträge kategorisi için)
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump10.php"
 */
header('Content-Type: text/plain; charset=UTF-8');
$idx = @file_get_contents(__DIR__ . '/index.php');
if ($idx === false) exit("index.php yok\n");
function R($s,$needle,$before,$len,$lbl){
    echo "\n──── $lbl ────\n";
    $p=strpos($s,$needle);
    if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}
    echo substr($s,max(0,$p-$before),$len)."\n";
}
// CATS tanımı + ilk birkaç kategori
foreach(['const CATS','var CATS','CATS=','let CATS'] as $k){ if(strpos($idx,$k)!==false){ R($idx,$k,0,1100,"CATS tanımı ($k)"); break; } }
// DOCS tanımı + ilk doc (alan adları + soru formatı)
foreach(['const DOCS','var DOCS','DOCS=','let DOCS'] as $k){ if(strpos($idx,$k)!==false){ R($idx,$k,0,1700,"DOCS tanımı ($k)"); break; } }
// Sorulu örnek bir doc (q/qs formatı netleşsin)
R($idx,'q:[',-120,700,'örnek soru bloğu q:[');
R($idx,'qs:[',-120,700,'örnek soru bloğu qs:[');
// kategori ızgarası nasıl çiziliyor + ikon alanı
R($idx,'showAllCats',0,700,'showAllCats (kategori kartları)');
// ülke/hukuk
foreach(['CC_DATA','CC=','dLang','const CC'] as $k){ $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':'VAR')."\n"; }
echo "\n=== BİTTİ. SİL: rm dump10.php ===\n";
