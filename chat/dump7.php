<?php
/**
 * ChatHelp — dump7 (SADECE OKUR): ana sohbet doSend/addMsg/.ia giriş
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump7.php"
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
// .ia giriş alanı (input id'si)
R($idx,'class="ia"',0,700,'.ia giriş alanı HTML (input id)');
R($idx,'<textarea',0,300,'ilk textarea');
foreach(['id="mi-input"','id="chat-input"','id="ci"','id="inp"','id="msg-input"','class="ia-input"','id="ta"'] as $k){
    $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($idx,'function doSend',0,1400,'doSend()');
R($idx,'function addMsg',0,800,'addMsg()');
// mevcut foto OCR fonksiyonu (analyze_photo'yu çağıran)
R($idx,"action=analyze_photo",600,500,'analyze_photo çağıran fonksiyon (öncesi)');
R($idx,'handlePhoto',0,500,'handlePhoto (varsa)');
R($idx,'onPhoto',0,400,'onPhoto (varsa)');
R($idx,'accept="image',0,400,'mevcut foto input');
echo "\n=== BİTTİ. SİL: rm dump7.php ===\n";
