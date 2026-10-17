<?php
/**
 * OxaPay kripto ödeme teşhisi — SALT OKUNUR
 *   php tools/audit-oxapay.php [--root=DIR] [--no-invoice]
 *
 * Canlı kurulumdaki GERÇEK kod yolunu (inc/oxapay.php) çalıştırır:
 *   1) anahtar yapılandırılmış mı (anahtarın kendisi ASLA yazılmaz; yalnız
 *      uzunluk + sha256 ön eki, kimlik doğrulamak için)
 *   2) odeme/ dosyaları ve .htaccess'te odeme'yi etkileyen satırlar
 *   3) günlükteki oxapay/kripto satırları (OxaPay'in verdiği hata burada)
 *   4) kripto siparişlerinin durumu (müşteri adı/e-posta YOK)
 *   5) HMAC öz-sınaması (callback imza doğrulaması anahtarla çalışıyor mu)
 *   6) 1 € deneme faturası — aynı fonksiyon, aynı anahtar; ödenmez, süresi dolar
 *   7) /odeme/ ve /checkout.php'ye sunucunun kendisinden HTTP erişim
 *
 * Stripe'a DOKUNMAZ: inc/orders.php (stripe.php'yi çeker) dahil edilmez;
 * siparişler doğrudan JSON depodan okunur. Siteye hiçbir şey yazılmaz.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$doInvoice = true;
foreach ($argv as $a) {
    if (str_starts_with($a, '--root=')) $root = substr($a, 7);
    if ($a === '--no-invoice') $doInvoice = false;
}
require_once $root . '/inc/oxapay.php';

$out = ['at' => gmdate('c'), 'root' => $root, 'php' => PHP_VERSION];

// ------------------------------------------------------------ 1) yapılandırma
$cfg  = vr_oxapay_settings();
$file = vr_data_dir() . '/oxapay_settings.json';
$out['config'] = [
    'data_dir'           => vr_data_dir(),
    'settings_file'      => $file,
    'settings_exists'    => is_file($file),
    'settings_readable'  => is_readable($file),
    'settings_mode'      => is_file($file) ? substr(sprintf('%o', fileperms($file)), -4) : null,
    'env_key_set'        => (bool)getenv('OXAPAY_MERCHANT_KEY'),
    'key_len'            => strlen($cfg['merchant_key']),
    'key_sha256_8'       => $cfg['merchant_key'] !== '' ? substr(hash('sha256', $cfg['merchant_key']), 0, 8) : '',
    'configured'         => $cfg['configured'],
    'ready'              => vr_oxapay_ready(),
    'enabled'            => vr_oxapay_enabled(),
    'crypto_enabled_cfg' => vr_config('crypto_enabled', true),
    'sandbox'            => $cfg['sandbox'],
    'lifetime'           => $cfg['lifetime'],
    'currency'           => (string)vr_config('currency', 'EUR'),
    'canonical_origin'   => vr_canonical_origin(),
    'curl'               => function_exists('curl_init'),
    'openssl'            => extension_loaded('openssl'),
];

// ----------------------------------------------------------------- 2) dosyalar
$out['files'] = [];
foreach (['odeme/oxapay-callback.php', 'odeme/tesekkurler.php', 'inc/oxapay.php', 'checkout.php', '.htaccess', 'odeme/.htaccess'] as $f) {
    $p = $root . '/' . $f;
    $out['files'][$f] = is_file($p) ? ['size' => filesize($p), 'mtime' => gmdate('c', (int)filemtime($p))] : null;
}
$out['htaccess_odeme_lines'] = [];
$ht = @file_get_contents($root . '/.htaccess');
if (is_string($ht)) {
    foreach (explode("\n", $ht) as $ln) {
        if (stripos($ln, 'odeme') !== false) $out['htaccess_odeme_lines'][] = trim($ln);
    }
}
if (is_file($root . '/odeme/.htaccess')) {
    $out['odeme_htaccess'] = mb_substr((string)@file_get_contents($root . '/odeme/.htaccess'), 0, 800);
}

// ------------------------------------------------------------------ 3) günlük
$log = vr_data_dir() . '/retail.log';
$out['log'] = ['file' => $log, 'exists' => is_file($log), 'readable' => is_readable($log), 'lines' => []];
if (is_readable($log)) {
    $lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $hits  = array_values(array_filter($lines, static fn ($l) => preg_match('/oxapay|crypto/i', $l) === 1));
    $out['log']['total_lines'] = count($lines);
    $out['log']['hits']        = count($hits);
    $out['log']['lines']       = array_slice($hits, -40);
}

// ------------------------------------------------- 4) kripto siparişleri (PII yok)
/**
 * Tarayıcı ailesi (UA'dan, kişisel veri değil). Neden: CSP form-action'ı
 * POST sonrası yönlendirmeye Chromium ve WebKit uygular, Firefox uygulamaz —
 * "müşteri OxaPay'e varamadı" teşhisi tarayıcıya bağlı.
 */
function diag_browser(string $ua): string
{
    if ($ua === '') return '';
    $m = static fn (string $re): bool => preg_match($re, $ua) === 1;
    $b = $m('/FxiOS\//') ? 'Firefox-iOS(WebKit)'
       : ($m('/CriOS\//') ? 'Chrome-iOS(WebKit)'
       : ($m('/Firefox\//') ? 'Firefox'
       : ($m('/Edg\//') ? 'Edge(Chromium)'
       : ($m('/SamsungBrowser\//') ? 'Samsung(Chromium)'
       : ($m('/OPR\//') ? 'Opera(Chromium)'
       : ($m('/Chrome\//') ? 'Chrome(Chromium)'
       : ($m('/Version\/.*Safari\//') ? 'Safari(WebKit)' : 'other')))))));
    $os = $m('/iPhone|iPad/') ? 'iOS' : ($m('/Android/') ? 'Android'
        : ($m('/Windows/') ? 'Windows' : ($m('/Mac OS X/') ? 'macOS' : ($m('/Linux/') ? 'Linux' : ''))));
    $wv = $m('/; wv\)|FBAN|FBAV|Instagram/') ? ' +webview' : '';
    return trim($b . ' ' . $os . $wv);
}
$orders = (array)vr_store_read('retail-orders.json', []);
$co = [];
$pendingTracks = [];                                   // bekleyenler için OxaPay'e durum sorusu
foreach ($orders as $o) {
    if (!is_array($o)) continue;
    if ((string)($o['payment_method'] ?? '') !== 'crypto' && !isset($o['oxapay'])) continue;
    if ((string)($o['status'] ?? '') === 'pending' && (string)($o['oxapay']['track_id'] ?? '') !== '') {
        $pendingTracks[(string)$o['number']] = (string)$o['oxapay']['track_id'];
    }
    $co[] = [
        'id'            => (string)($o['id'] ?? ''),
        'number'        => (string)($o['number'] ?? ''),
        'status'        => (string)($o['status'] ?? ''),
        'total_cents'   => (int)($o['total_cents'] ?? 0),
        'created_at'    => isset($o['created_at']) ? gmdate('c', (int)$o['created_at']) : null,
        'failed_reason' => (string)($o['failed_reason'] ?? ''),
        'oxapay_status' => (string)($o['oxapay']['status'] ?? ''),
        'has_track_id'  => (string)($o['oxapay']['track_id'] ?? '') !== '',
        'country'       => (string)($o['customer']['address']['country'] ?? ''),
        'browser'       => diag_browser((string)($o['client']['ua'] ?? '')),
        'failed_at'     => isset($o['failed_at']) ? gmdate('c', (int)$o['failed_at']) : null,
    ];
}
$out['orders_total']  = count($orders);
$out['crypto_orders'] = ['count' => count($co), 'last' => array_slice($co, -15)];

// ------------------------------------------------------- 5) HMAC öz-sınaması
$body = (string)json_encode(['track_id' => 'selftest', 'status' => 'Paid', 'order_id' => 'DIAG']);
$out['hmac_selftest'] = $cfg['configured']
    ? vr_oxapay_verify_callback($body, hash_hmac('sha512', $body, $cfg['merchant_key']))
    : null;

// --------------------------------------------- 6) deneme faturası (gerçek yol)
if ($doInvoice && $cfg['configured']) {
    $fake = [
        'id'          => 'DIAG-' . gmdate('YmdHis'),
        'token'       => 'diag',
        'number'      => 'DIAG',
        'total_cents' => 100,
        'customer'    => ['email' => 'diag@sarvesto.com'],
    ];
    [$ok, $r] = vr_oxapay_create_invoice($fake);
    $out['invoice_test'] = $ok
        ? ['ok' => true, 'payment_host' => (string)parse_url((string)$r['payment_url'], PHP_URL_HOST), 'track_id' => (string)$r['track_id']]
        : ['ok' => false, 'error' => (string)$r];
} else {
    $out['invoice_test'] = ['skipped' => true, 'reason' => $cfg['configured'] ? 'flag' : 'not_configured'];
}

// --------------------------- 6b) OxaPay'in gözüyle bekleyen faturalar (salt okunur)
// Webhook gelmediyse fatura OxaPay'de ne durumda? (v1: GET payment/{track_id})
function diag_oxapay_info(string $trackId, string $key): array
{
    $ch = curl_init(VR_OXAPAY_API . 'payment/' . rawurlencode($trackId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER     => ['merchant_api_key: ' . $key, 'Content-Type: application/json'],
    ]);
    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $j = is_string($raw) ? json_decode($raw, true) : null;
    $d = (is_array($j) && is_array($j['data'] ?? null)) ? $j['data'] : [];
    return [
        'http'       => $http,
        'err'        => $err,
        'message'    => (string)($j['message'] ?? ''),
        'status'     => (string)($d['status'] ?? ''),
        'amount'     => $d['amount'] ?? null,
        'currency'   => $d['currency'] ?? null,
        'date'       => $d['date'] ?? null,
        'expired_at' => $d['expired_at'] ?? ($d['expiredAt'] ?? null),
        'keys'       => array_slice(array_keys($d), 0, 24),
        'raw'        => $http !== 200 ? mb_substr((string)$raw, 0, 300) : null,
    ];
}
$out['oxapay_view'] = [];
if ($cfg['configured']) {
    foreach (array_slice($pendingTracks, -6, 6, true) as $num => $tid) {
        $out['oxapay_view'][$num] = diag_oxapay_info($tid, $cfg['merchant_key']);
    }
}

// --------------------------------- 6c) erişim günlüğü: callback/dönüş gerçekten geldi mi?
// OxaPay'in POST'u PHP'ye ulaşmadan (WAF/403) düşüyorsa yalnız burada görünür.
// IP'lerin son sekizlisi maskelenir.
$out['access_log'] = ['files' => [], 'hits' => []];
$home = (string)(getenv('HOME') ?: dirname(vr_data_dir()));
foreach (array_merge(glob($home . '/access-logs/*') ?: [], glob($home . '/logs/*') ?: []) as $lf) {
    if (!is_file($lf) || !is_readable($lf)) continue;
    $out['access_log']['files'][] = basename($lf) . ' (' . filesize($lf) . ' B)';
    if (filesize($lf) > 300 * 1024 * 1024) continue;
    // cPanel günlükleri .gz arşivdir (ay içinde günlük güncellenir).
    $gz = str_ends_with($lf, '.gz');
    if ($gz && !function_exists('gzopen')) { $out['access_log']['files'][] = '  (zlib yok, gz okunamadı)'; continue; }
    $fh = $gz ? gzopen($lf, 'rb') : fopen($lf, 'r');
    if (!$fh) continue;
    while (($ln = $gz ? gzgets($fh, 8192) : fgets($fh)) !== false) {
        if (strpos($ln, 'oxapay-callback') === false && strpos($ln, 'tesekkurler') === false) continue;
        $ln = preg_replace('/(\d+\.\d+\.\d+)\.\d+/', '$1.x', trim($ln)) ?? '';
        $out['access_log']['hits'][] = mb_substr($ln, 0, 260);
    }
    $gz ? gzclose($fh) : fclose($fh);
}
$out['access_log']['hits'] = array_slice($out['access_log']['hits'], -30);

// ------------------------------------ 7) dışarıdan erişim (sunucudan kendine)
function diag_http(string $method, string $url, string $body = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HEADER         => true,
        CURLOPT_USERAGENT      => 'sarvesto-diag',
    ]);
    if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw   = curl_exec($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redir = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $err   = curl_error($ch);
    curl_close($ch);
    $split    = is_string($raw) ? (int)strpos($raw, "\r\n\r\n") : 0;
    $hdrPart  = is_string($raw) ? substr($raw, 0, $split) : '';
    $bodyPart = is_string($raw) ? substr($raw, $split + 4) : '';
    // CSP form-action: tarayıcı (Chrome/Edge) bunu POST sonrası yönlendirmeye de
    // uygular; ödeme sayfası burada listeli değilse müşteri OxaPay'e varamaz.
    $fa = '';
    if (preg_match('/^content-security-policy:\s*(.+)$/mi', $hdrPart, $m)) {
        foreach (explode(';', $m[1]) as $d) {
            $d = trim($d);
            if (str_starts_with($d, 'form-action')) $fa = $d;
        }
    }
    return ['code' => $code, 'redirect' => $redir, 'err' => $err, 'csp_form_action' => $fa,
            'body' => mb_substr(trim(strip_tags($bodyPart)), 0, 160)];
}
$org = vr_canonical_origin();
$out['reach'] = [
    'callback_post_empty' => diag_http('POST', $org . '/odeme/oxapay-callback.php', '{}'), // beklenen: 400 "invalid signature"
    'tesekkurler_get'     => diag_http('GET',  $org . '/odeme/tesekkurler.php'),           // beklenen: 303 → order.php
    'checkout_get'        => diag_http('GET',  $org . '/checkout.php'),                     // beklenen: 303 → cart.php
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
