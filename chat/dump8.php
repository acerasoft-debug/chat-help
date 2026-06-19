<?php
/**
 * ChatHelp — dump8 (SADECE OKUR): Fall render/upsell markup (premium CSS için)
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump8.php"
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
// render() gövdesini olabildiğince geniş al (innerHTML üretimi)
R($idx,'function render(){',0,3000,'render() gövde');
R($idx,'function upsell',0,900,'upsell()');
R($idx,'fallNew',0,300,'fallNew tetik');
// Fall ile ilgili mevcut CSS sınıfları
foreach(['fall-hero','fall-empty','fall-list','fall-item','fall-bubble','fall-actions','f-msg','f-bub','fall-input','fall-send','newfall','fnew','f-card','fcard'] as $k){
    $p=strpos($idx,$k); echo "  CSS '$k' -> ".($p===false?'yok':'VAR')."\n";
}
// Sidebar "Fall eröffnen" kartı
R($idx,'Fall eröffnen',-200,500,'sidebar Fall eröffnen kartı');
echo "\n=== BİTTİ. SİL: rm dump8.php ===\n";
