<?php
/**
 * Yönetim paneli canlı doğrulaması — SALT OKUNUR, giriş yapmaz, anahtar yazmaz
 *   php tools/audit-admin.php [--root=DIR]
 *
 * Sunucunun kendisinden canlı adrese (oturumsuz) şunları sorar:
 *   1) /admin/ → giriş sayfasına yönlendiriyor mu, noindex mi
 *   2) /admin/visitors.php?f=live&json=live → girişsiz veri SIZMIYOR mu
 *   3) giriş sayfası: yeni sürüm mü (CSS/JS sürüm damgası), hesap durumu
 *   4) sahte X-Forwarded-For ile mağaza isteği → istatistikte hangi adres
 *      kaydedildi (sunucunun kendi adresi; sahte olan kaydedilmemeli)
 *   5) hesap yapılandırması (e-posta/şifre YAZILMAZ): kendi hesap var mı,
 *      Vestra şifresi bulundu mu
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/config.php';
require_once $root . '/inc/admin.php';

$origin = vr_canonical_origin() . vr_base_url();
$req = static function (string $url, array $hdr = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 25, CURLOPT_USERAGENT => 'sarvesto-diag', CURLOPT_HTTPHEADER => $hdr]);
    $raw = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redir = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    $p = (int)strpos($raw, "\r\n\r\n");
    return ['code' => $code, 'redirect' => $redir, 'head' => substr($raw, 0, $p), 'body' => substr($raw, $p + 4)];
};

$out = ['at' => gmdate('c')];

$r = $req($origin . '/admin/');
$out['admin_root'] = ['code' => $r['code'], 'redirect' => $r['redirect']];

$r = $req($origin . '/admin/visitors.php?f=live&json=live');
$out['json_anon'] = [
    'code' => $r['code'], 'redirect' => $r['redirect'],
    'leaks_data' => (bool)preg_match('/"rows"|"map"|"side"/', $r['body']),
    'body_bytes' => strlen($r['body']),
];

$r = $req($origin . '/admin/login.php');
$out['login_page'] = [
    'code' => $r['code'],
    'noindex' => (bool)preg_match('/<meta name="robots" content="noindex/i', $r['body']),
    'form_has_email_field' => str_contains($r['body'], 'name="email"'),
    'unconfigured_notice' => str_contains($r['body'], 'Noch kein Administrator eingerichtet'),
];

// Sahte XFF: istatistik satırında sahte adres olmamalı.
$fake = '198.51.100.' . random_int(100, 250);
$day  = gmdate('Y-m-d');
$ua   = 'Mozilla/5.0 (X11; Linux) Firefox/131.0 sarvesto-diag-' . bin2hex(random_bytes(3));
$req($origin . '/shop.php', ['X-Forwarded-For: ' . $fake, 'User-Agent: ' . $ua]);
$hit = '';
$f = $root . '/../data/stats/hits-' . $day . '.jsonl';
if (!is_readable($f)) $f = vr_data_dir() . '/stats/hits-' . $day . '.jsonl';
if (is_readable($f)) {
    $lines = array_slice(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -50);
    foreach (array_reverse($lines) as $l) { $j = json_decode($l, true); if (is_array($j) && str_contains((string)($j['ua'] ?? ''), substr($ua, -17))) { $hit = (string)$j['ip']; break; } }
}
$out['xff_spoof'] = ['found_row' => $hit !== '', 'recorded_fake_ip' => $hit === $fake, 'recorded_ip_class' => $hit === '' ? '' : (filter_var($hit, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? 'public' : 'private_or_local')];

$c = vr_admin_config();
$out['account'] = [
    'own_account_configured' => $c !== null,
    'own_account_source'     => $c === null ? '' : (getenv('VR_ADMIN_USER') ? 'env' : 'data/admin.json'),
    'vestra_password_found'  => vr_vestra_admin_pass() !== '' || vr_vestra_admin_hash() !== '',
    'ip_allowlist_active'    => trim((string)getenv('VR_ADMIN_IPS')) !== '',
    'trusted_proxies_set'    => trim((string)getenv('VR_TRUSTED_PROXIES')) !== '',
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
