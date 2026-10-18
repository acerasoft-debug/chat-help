<?php
/**
 * Kasa ödeme seçenekleri teşhisi — SALT OKUNUR, anahtar YAZMAZ
 *   php tools/audit-checkout.php [--root=DIR]
 *
 * "Kasada kart görünmüyor" sorusu için iki açıdan bakar:
 *   1) Ayar dosyaları (meta): var mı, okunabilir mi, izin/sahip/boyut/tarih,
 *      hangi alan adları dolu (değerler ASLA yazılmaz), vr_stripe_settings()
 *      ve vr_oxapay_settings() ne döndürüyor (configured/mode).
 *   2) Web akışı: sunucunun kendisinden canlı siteye sepet → kasa isteği atar ve
 *      kasa HTML'inde kart/kripto radyoları mı, tek yöntem (hidden) mi basılmış
 *      ölçer. CLI ile web sürecinin farkı varsa burada görünür.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/config.php';
require_once $root . '/inc/oxapay.php';

$out = ['at' => gmdate('c'), 'root' => $root, 'php' => PHP_VERSION];
$out['cli_user'] = function_exists('posix_geteuid') ? ((posix_getpwuid(posix_geteuid())['name'] ?? '?')) : '?';

// ------------------------------------------------------------ 1) ayar meta
function diag_file_meta(string $f): array
{
    if (!is_file($f)) return ['exists' => false];
    $owner = function_exists('posix_getpwuid') ? ((posix_getpwuid((int)fileowner($f))['name'] ?? (string)fileowner($f))) : (string)fileowner($f);
    $json  = is_readable($f) ? json_decode((string)file_get_contents($f), true) : null;
    $keys  = [];
    if (is_array($json)) {
        foreach ($json as $k => $v) {
            // yalnız alan adı + dolu mu + uzunluk; değer yok
            $keys[(string)$k] = is_string($v) ? ['set' => $v !== '', 'len' => strlen($v)] : ['set' => $v !== null && $v !== '' && $v !== [], 'type' => gettype($v)];
        }
    }
    return [
        'exists' => true, 'readable' => is_readable($f), 'perms' => substr(sprintf('%o', fileperms($f)), -4),
        'owner' => $owner, 'size' => filesize($f), 'mtime' => gmdate('c', (int)filemtime($f)),
        'valid_json' => is_array($json), 'fields' => $keys,
    ];
}
$data = vr_data_dir();
$s = vr_stripe_settings();
$o = vr_oxapay_settings();
$out['data_dir'] = $data;
$out['stripe'] = [
    'configured'    => $s['configured'],
    'mode'          => $s['mode'],
    'webhook_ready' => $s['webhook_ready'],
    'env_secret_set' => (bool)getenv('STRIPE_SECRET_KEY'),
    'file'          => diag_file_meta($data . '/stripe_settings.json'),
    'alt_file_in_root_data' => diag_file_meta($root . '/data/stripe_settings.json'),
];
$out['oxapay'] = [
    'configured' => $o['configured'], 'sandbox' => $o['sandbox'],
    'file' => diag_file_meta($data . '/oxapay_settings.json'),
];

// --------------------------------------------------------------- 2) web akışı
function diag_req(string $url, string $jar, ?array $post = null, array &$hdrs = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_USERAGENT => 'sarvesto-diag',
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw  = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redir = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $err  = curl_error($ch);
    curl_close($ch);
    $split = (int)strpos($raw, "\r\n\r\n");
    $hdrs  = explode("\r\n", substr($raw, 0, $split));
    return ['code' => $code, 'redirect' => $redir, 'err' => $err, 'body' => substr($raw, $split + 4)];
}
$origin = vr_canonical_origin() . vr_base_url();
$jar = tempnam(sys_get_temp_dir(), 'diagjar');
$flow = [];
$h = [];
$r = diag_req($origin . '/shop.php', $jar, null, $h);
$flow['shop'] = ['code' => $r['code'], 'err' => $r['err']];
$pid = preg_match('/product\.php\?id=([^"&]+)/', $r['body'], $m) ? $m[1] : '';
$flow['pid'] = $pid;
if ($pid !== '') {
    $r = diag_req($origin . '/product.php?id=' . rawurlencode($pid), $jar, null, $h);
    $flow['product'] = ['code' => $r['code']];
    $csrf = preg_match('/name="_csrf" value="([^"]+)"/', $r['body'], $m) ? $m[1] : '';
    $size = preg_match('/name="size" value="([^"]+)"/', $r['body'], $m) ? $m[1] : '';
    $col  = preg_match('/name="colour" value="([^"]+)"/', $r['body'], $m) ? $m[1] : '';
    $flow['form'] = ['csrf' => $csrf !== '', 'size' => $size, 'colour' => $col !== ''];
    $post = ['_csrf' => $csrf, 'action' => 'add', 'pid' => $pid, 'size' => $size, 'qty' => '1'];
    if ($col !== '') $post['colour'] = $col;
    $r = diag_req($origin . '/cart.php', $jar, $post, $h);
    $flow['add'] = ['code' => $r['code'], 'redirect' => $r['redirect']];
    $r = diag_req($origin . '/checkout.php', $jar, null, $h);
    $csp = '';
    foreach ($h as $ln) if (stripos($ln, 'content-security-policy:') === 0) $csp = mb_substr($ln, 0, 300);
    $b = $r['body'];
    $flow['checkout'] = [
        'code'              => $r['code'],
        'redirect'          => $r['redirect'],
        'bytes'             => strlen($b),
        'radio_card'        => (bool)preg_match('/name="pay_method"\s+value="card"/', $b),
        'radio_crypto'      => (bool)preg_match('/name="pay_method"\s+value="crypto"/', $b),
        'hidden_pay_method' => preg_match('/type="hidden" name="pay_method" value="([^"]+)"/', $b, $m) ? $m[1] : '',
        'crypto_fields'     => str_contains($b, 'data-crypto-fields'),
        'pay_unavailable_box' => str_contains($b, 'notice--soon'),
        'stripe_lock_note'  => str_contains($b, 'data-card-only'),
        'csp'               => $csp,
    ];
}
@unlink($jar);
$out['web_flow'] = $flow;

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
