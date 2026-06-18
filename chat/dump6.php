<?php
/**
 * ChatHelp — dump6 (SADECE OKUR): ana sohbet giriş/gönderim yapısı
 * (tüm chatlerde foto/dosya açmak için)
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump6.php"
 */
header('Content-Type: text/plain; charset=UTF-8');
$idx = @file_get_contents(__DIR__ . '/index.php');
$api = @file_get_contents(__DIR__ . '/api.php');
if ($idx === false) exit("index.php yok\n");
function R($s,$needle,$before,$len,$lbl){
    echo "\n──── $lbl  ('$needle') ────\n";
    if($s===false){echo "(dosya yok)\n";return;}
    $p=strpos($s,$needle);
    if($p===false){echo "(BULUNAMADI)\n";return;}
    echo substr($s,max(0,$p-$before),$len)."\n";
}

echo "### ANA SOHBET GİRİŞ ALANI ###\n";
foreach(['id="mi"',"getElementById('mi')",'id="sbtn"','sendMsg','function send(','async function send','id="msgs"','#mi'] as $k){
    $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($idx,'id="mi"',0,500,'ana input HTML');
R($idx,'id="sbtn"',0,400,'gönder butonu HTML');
R($idx,'async function send',0,1500,'send() fonksiyonu');
R($idx,'function send(',0,1500,'send() (alternatif)');

echo "\n### ANA SOHBET -> API action ###\n";
R($idx,"action:'followup'",100,400,'followup gönderimi');
R($idx,'analyze_photo',0,500,'analyze_photo (index)');
R($idx,'detectDoc',0,400,'detectDoc');

echo "\n### api.php: doFollowup + doPhoto ###\n";
R($api,'function doFollowup',0,1400,'doFollowup');
R($api,'function doPhoto',0,1600,'doPhoto (analyze_photo)');

echo "\n=== BİTTİ. SİL: rm dump6.php ===\n";
