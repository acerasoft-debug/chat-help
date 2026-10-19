<?php
/**
 * Proxy / istemci IP teşhisi — SALT OKUNUR, IP adresi YAZMAZ
 *   php tools/audit-proxy.php [--root=DIR]
 *
 * Soru: site bir ters vekilin (Cloudflare, yük dengeleyici) arkasında mı?
 * Bu, X-Forwarded-For'a ne zaman güvenileceğini belirler. İki kanıt:
 *   1) Sunucunun kendisinden canlı adrese HEAD isteği: yanıt başlıkları
 *      (server, cf-ray, via, x-litespeed-*) — yalnız başlık adları/değerleri.
 *   2) Web sunucusu erişim günlüğündeki istemci adreslerinin SINIFLARI
 *      (Cloudflare aralığı / özel ağ / diğer) — adreslerin kendisi değil,
 *      yalnız sayılar.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/config.php';

$out = ['at' => gmdate('c')];

// ------------------------------------------------------------- 1) başlıklar
$ch = curl_init(vr_canonical_origin() . '/');
curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_HEADER => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'sarvesto-diag']);
$raw = (string)curl_exec($ch);
$out['head'] = ['code' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE), 'primary_ip_class' => '', 'headers' => []];
$pip = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
curl_close($ch);
foreach (explode("\r\n", $raw) as $ln) {
    if (!str_contains($ln, ':')) continue;
    [$k, $v] = array_map('trim', explode(':', $ln, 2));
    $k = strtolower($k);
    if (in_array($k, ['server', 'cf-ray', 'cf-cache-status', 'via', 'x-litespeed-cache', 'x-turbo-charged-by', 'x-powered-by', 'x-cache', 'platform', 'hostinger'], true)) {
        $out['head']['headers'][$k] = $k === 'cf-ray' ? '(vorhanden)' : mb_substr($v, 0, 80);
    }
}

// Cloudflare IPv4 aralıkları (https://www.cloudflare.com/ips-v4)
$cf4 = ['173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20',
        '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22'];
$inCidr = static function (string $ip, string $cidr): bool {
    [$net, $bits] = explode('/', $cidr);
    $i = ip2long($ip); $n = ip2long($net);
    if ($i === false || $n === false) return false;
    $mask = -1 << (32 - (int)$bits);
    return ($i & $mask) === ($n & $mask);
};
$class = static function (string $ip) use ($cf4, $inCidr): string {
    if (str_contains($ip, ':')) return str_starts_with(strtolower($ip), '2606:4700:') || str_starts_with(strtolower($ip), '2a06:98c') ? 'cloudflare' : 'ipv6_other';
    foreach ($cf4 as $c) if ($inCidr($ip, $c)) return 'cloudflare';
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return 'private';
    return 'other';
};
$out['head']['primary_ip_class'] = $pip !== '' ? $class($pip) : '';

// ------------------------------------------------- 2) erişim günlüğü sınıfları
$home = (string)(getenv('HOME') ?: dirname(vr_data_dir()));
$files = array_merge(glob($home . '/access-logs/*sarvesto*') ?: [], glob($home . '/logs/*sarvesto*') ?: []);
$out['access_log'] = [];
foreach ($files as $lf) {
    if (!is_file($lf) || !is_readable($lf)) continue;
    $gz = str_ends_with($lf, '.gz');
    if ($gz && !function_exists('gzopen')) continue;
    $fh = $gz ? gzopen($lf, 'rb') : fopen($lf, 'r');
    if (!$fh) continue;
    $tail = [];
    while (($ln = $gz ? gzgets($fh, 8192) : fgets($fh)) !== false) {
        $tail[] = strtok($ln, ' ');
        if (count($tail) > 4000) array_shift($tail);
    }
    $gz ? gzclose($fh) : fclose($fh);
    $cnt = ['cloudflare' => 0, 'private' => 0, 'other' => 0, 'ipv6_other' => 0];
    $uniq = [];
    foreach ($tail as $ip) { $k = $class((string)$ip); $cnt[$k] = ($cnt[$k] ?? 0) + 1; $uniq[$ip] = 1; }
    $out['access_log'][basename($lf)] = ['lines_sampled' => count($tail), 'distinct_addresses' => count($uniq), 'classes' => $cnt];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
