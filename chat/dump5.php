<?php
/**
 * ChatHelp — dump5 (SADECE OKUR): Fall panel UI yapısı
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump5.php"
 * Çıktıyı bana gönder. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$idx = @file_get_contents(__DIR__ . '/index.php');
if ($idx === false) exit("index.php yok\n");

function R($s,$needle,$before,$len,$lbl){
    echo "\n──── $lbl  ('$needle') ────\n";
    $p=strpos($s,$needle);
    if($p===false){echo "(BULUNAMADI)\n";return;}
    echo substr($s,max(0,$p-$before),$len)."\n";
}

echo "### FALL PANEL UI ###\n";
R($idx,'pnl-fall',0,600,'#pnl-fall ilk geçiş (HTML iskelet)');
R($idx,'id="pnl-fall"',0,1200,'pnl-fall tam blok');
R($idx,"fall_chat",200,700,'fall_chat fetch çağrısı (gönderim)');
R($idx,'function render',0,1400,'Fall render() fonksiyonu');
R($idx,'updateCur',0,400,'updateCur tanımı');

echo "\n### FALL GİRİŞ ALANI (input / send) ###\n";
R($idx,'fall',0,200,'ilk fall geçişi');
// Fall input/textarea ve gönder butonu
foreach(['fall-input','fallInput','f-input','fall-send','fallSend','fall-msg','fSend','#f-','fall-q'] as $k){
    $p=strpos($idx,$k);
    echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($idx,'Fall lösen',0,400,'"Fall lösen" butonu');
R($idx,'Fall eröffnen',0,400,'"Fall eröffnen" butonu');
R($idx,'eroeffnen',0,300,'eroeffnen');
R($idx,'neuerFall',0,300,'neuerFall');

echo "\n### FALL CSS sınıfları ###\n";
foreach(['fall-wrap','fall-msg','fall-card','f-card','fall-chat','fall-doc','fall-panel'] as $k){
    $p=strpos($idx,$k);
    echo "  '$k' -> ".($p===false?'yok':"VAR")."\n";
}

echo "\n=== BİTTİ. SİL: rm dump5.php ===\n";
