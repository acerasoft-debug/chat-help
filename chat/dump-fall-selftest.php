<?php
/** ChatHelp — dump-fall-selftest (SADECE OKUR + ic test istegi)
 *  "sonuc cikmiyor" teshisi:
 *   [1] Canli fall-api.php php -l (calisma-ani sozdizimi)
 *   [2] CH_FALL_LANG yamasinin 5 bolgesinin gercek baglami (dogru uygulandi mi)
 *   [3] SUNUCU ICINDEN fall-api.php'ye gercek fall_chat POST'u (Turkce mesaj)
 *       -> HTTP kodu + ham yanit gorunur; backend hata veriyorsa TAM hata gorunur
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-fall-selftest.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(120);
$D = __DIR__;

echo "════════ [1] CANLI fall-api.php LINT ════════\n\n";
$lo=[];$rc=0; exec('php -l '.escapeshellarg($D.'/fall-api.php').' 2>&1',$lo,$rc);
echo implode("\n",$lo)."\n".($rc===0?"✓ SOZDIZIMI TEMIZ":"✗✗ SOZDIZIMI HATASI — SORUNUN KAYNAGI BU OLABILIR")."\n";

echo "\n════════ [2] CH_FALL_LANG BOLGELERI (dogru uygulanma kontrolu) ════════\n\n";
$f = @file_get_contents($D.'/fall-api.php');
if ($f===false) exit("fall-api.php okunamadi\n");
echo "CH_FALL_LANG marker sayisi: ".substr_count($f,'CH_FALL_LANG')."\n\n";
$off=0;$n=0;
while(($p=strpos($f,'CH_FALL_LANG',$off))!==false && $n<8){
    $n++;
    echo "--- bolge #$n @".$p." ---\n";
    echo substr($f, max(0,$p-160), 360)."\n\n";
    $off=$p+10;
}

echo "\n════════ [3] IC TEST: fall_chat POST (Turkce) ════════\n\n";
$payload = json_encode([
    'action'   => 'fall_chat',
    'title'    => 'Test',
    'messages' => [['role'=>'user','content'=>'Merhaba, isten haksiz yere cikarildim, ne yapabilirim?']],
], JSON_UNESCAPED_UNICODE);
$ch = curl_init('https://chat-help.com/chat/fall-api.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 100,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "HTTP kodu: $code\n";
if ($err) echo "cURL hatasi: $err\n";
echo "Ham yanit (ilk 1200 bayt):\n".substr((string)$res,0,1200)."\n";

echo "\n════════ [4] PHP HATA GUNLUGU (varsa son satirlar) ════════\n\n";
foreach ([$D.'/error_log', $D.'/../error_log', ini_get('error_log')] as $el) {
    if ($el && @file_exists($el)) {
        echo "--- $el (son 1500 bayt) ---\n";
        $c = @file_get_contents($el);
        echo substr($c, -1500)."\n";
        break;
    }
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fall-selftest.php ════════\n";
