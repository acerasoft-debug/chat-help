<?php
/** ChatHelp — dump-mobile-probe (SADECE OKUR)
 *  Kullanici ayni cihazdan pull-updates'i CALISTIRABILIYOR ama /chat/ 403 —
 *  suphe: edge, /chat/ icin MOBIL cihaz-sinifi varyantina 403 sabitlemis
 *  (x-gateway-cache-key 'mobile' iceriyordu). Bu dump, farkli UA ve query
 *  kombinasyonlariyla public URL'yi yoklar ve 403'un tam hangi varyantta
 *  yasadigini bulur. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(90);

$UA_MOBILE = 'Mozilla/5.0 (Linux; Android 14; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36';
$UA_DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

function probe2($label, $url, $ua) {
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HEADER=>true,CURLOPT_USERAGENT=>$ua]);
    $res = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $head = substr((string)$res,0,$hs);
    $body = substr((string)$res,$hs);
    $cf=''; $age='';
    if (preg_match('/^cf-cache-status:\s*(.+)$/mi',$head,$m)) $cf=trim($m[1]);
    if (preg_match('/^age:\s*(.+)$/mi',$head,$m)) $age=trim($m[1]);
    echo str_pad($label,34)." HTTP:$code cf:$cf".($age!==''?" age:$age":"")." govde:".strlen($body)."B";
    if ($code!=200) echo "  ILK120: ".substr(preg_replace('/\s+/',' ',$body),0,120);
    echo "\n";
}

echo "════ MOBIL UA ════\n";
probe2("[M1] /chat/ (bare)",            'https://chat-help.com/chat/', $UA_MOBILE);
probe2("[M2] /chat/?fresh",             'https://chat-help.com/chat/?_p='.time(), $UA_MOBILE);
probe2("[M3] www /chat/ (bare)",        'https://www.chat-help.com/chat/', $UA_MOBILE);
probe2("[M4] /chat/index.php?fresh",    'https://chat-help.com/chat/index.php?_p='.time(), $UA_MOBILE);

echo "\n════ MASAUSTU UA ════\n";
probe2("[D1] /chat/ (bare)",            'https://chat-help.com/chat/', $UA_DESKTOP);
probe2("[D2] /chat/?fresh",             'https://chat-help.com/chat/?_p='.time(), $UA_DESKTOP);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
