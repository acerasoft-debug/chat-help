<?php
/** ChatHelp — dump-origin-direct (SADECE OKUR)
 *  Cloudflare'i TAMAMEN ATLAYARAK (127.0.0.1 + Host basligi) origin'in SU AN
 *  gercekte ne urettigini gosterir + karsilastirma icin public URL'yi benzersiz
 *  query ile cager (taze MISS almali). Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);

function probe($url, $host=null) {
    $ch = curl_init($url);
    $hdrs = ['Accept: text/html'];
    if ($host) $hdrs[] = 'Host: '.$host;
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_HTTPHEADER=>$hdrs,CURLOPT_HEADER=>true]);
    $res = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $head = substr((string)$res,0,$hs);
    $body = substr((string)$res,$hs);
    echo "HTTP: $code".($err?" · cURL: $err":"")." · govde: ".strlen($body)." B\n";
    foreach (['cf-cache-status','age','cache-control','last-modified','x-gateway-cache-status'] as $h) {
        if (preg_match('/^'.$h.':\s*(.+)$/mi',$head,$m)) echo "  $h: ".trim($m[1])."\n";
    }
    echo "  ilk 200: ".substr(preg_replace('/\s+/',' ',$body),0,200)."\n";
    echo "  son 120: ".substr(preg_replace('/\s+/',' ',$body),-120)."\n";
    echo "  CH_EDGE_BYPASS icinde mi: ".(strpos($body,'CH_EDGE_BYPASS')!==false?"EVET":"hayir")."\n";
    echo "  CH_UI_STAB2 icinde mi: ".(strpos($body,'CH_UI_STAB2')!==false?"EVET":"hayir")."\n\n";
}

echo "════════ [1] ORIGIN DOGRUDAN (127.0.0.1, CF atlanir) ════════\n";
probe('https://127.0.0.1/chat/', 'chat-help.com');
echo "════════ [2] ORIGIN DOGRUDAN http (443 sorun cikarirsa) ════════\n";
probe('http://127.0.0.1/chat/', 'chat-help.com');
echo "════════ [3] PUBLIC + BENZERSIZ QUERY (taze MISS beklenir) ════════\n";
probe('https://chat-help.com/chat/?_fresh='.time());
echo "════════ [4] index.php dosyasinin ILK 250 karakteri (edge-bypass basta mi?) ════════\n";
echo substr((string)@file_get_contents(__DIR__.'/index.php'),0,250)."\n";
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
