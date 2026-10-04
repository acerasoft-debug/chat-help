<?php
/** ChatHelp — dump-servecheck: sunucunun HTTP uzerinden GERCEKTEN servis ettigi index.php'yi kontrol eder
 *  (dosya-okuma degil, kendi kendine HTTP cagrisi) — CDN/edge cache stale mi ayirt eder. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-servecheck BASLADI (PHP ".PHP_VERSION.")\n\n";

$url = 'https://chat-help.com/chat/?_nc=' . microtime(true) . '_' . bin2hex(random_bytes(4));
$ctx = stream_context_create(['http' => ['timeout' => 25, 'header' => "Cache-Control: no-cache\r\n"]]);
$t0 = microtime(true);
$body = @file_get_contents($url, false, $ctx);
$dt = round(microtime(true) - $t0, 2);

echo "Istek: $url\n";
echo "Sure: {$dt}s\n";
if ($body === false) { echo "HATA: cagri basarisiz.\n"; exit; }
echo "Boyut (HTTP uzerinden): " . strlen($body) . " bayt\n\n";

echo "###### Response headers ######\n";
if (isset($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('/^(HTTP|Cache-Control|Age|X-Cache|CF-Cache|Surrogate|X-Served|ETag|Last-Modified|Expires|Pragma|Server)/i', $h)) {
            echo "  $h\n";
        }
    }
} else { echo "  (baslik bilgisi alinamadi)\n"; }

echo "\n###### Marker kontrolu (HTTP UZERINDEN SERVIS EDILEN icerik) ######\n";
foreach (['CH_MODPACK','CH_MODHUB','CH_MODHUB_NAV','ch-hub-fab','nav-hub','CH_FIXPACK10','CH_CV_STUDIO'] as $m) {
    echo "  $m: " . (strpos($body, $m) !== false ? 'VAR (HTTP cikisinda mevcut)' : 'YOK (HTTP cikisinda YOK!)') . "\n";
}

echo "\n###### Dosyadan dogrudan okuma (karsilastirma icin) ######\n";
$file = @file_get_contents(__DIR__ . '/index.php');
echo "Dosya boyutu: " . ($file !== false ? strlen($file) : 'okunamadi') . " bayt\n";
foreach (['CH_MODPACK','CH_MODHUB_NAV'] as $m) {
    echo "  $m (dosyada): " . (strpos($file, $m) !== false ? 'VAR' : 'YOK') . "\n";
}

echo "\n###### ch-hub-fab / nav-hub konumu (HTTP ciktisinda, ilk 300 karakter) ######\n";
$p = strpos($body, 'ch-hub-fab');
echo $p !== false ? substr($body, max(0,$p-100), 400) . "\n" : "(HTTP ciktisinda ch-hub-fab BULUNAMADI)\n";

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-servecheck.php ===\n";
