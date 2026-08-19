<?php
/** ChatHelp — dump-headers (SADECE OKUR)
 *  Genel URL'nin YANIT BASLIKLARINI gosterir: cf-cache-status / age /
 *  cache-control — edge'in HTML'i onbellekleyip bayat kopya servis etme
 *  ihtimalini kesinlestirir. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
foreach (['https://chat-help.com/chat/', 'https://www.chat-help.com/chat/'] as $u) {
    echo "════════ $u ════════\n";
    $ch = curl_init($u);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HEADER=>true,CURLOPT_NOBODY=>false,CURLOPT_FOLLOWLOCATION=>true]);
    $res = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "HTTP: $code\n";
    echo substr((string)$res, 0, $hs)."\n";
}
echo "════════ BITTI ════════\n";
