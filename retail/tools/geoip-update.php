<?php
/**
 * IP → ülke veritabanı (yerel) — php tools/geoip-update.php [--force]
 * ==================================================================
 * Kaynak: DB-IP "IP to Country Lite" (aylık, ücretsiz, CC BY 4.0 —
 * https://db-ip.com). Atıf: Datenschutz sayfasında.
 *
 * Aylık CSV'yi (başlangıç IP, bitiş IP, ülke) sunucuya indirir ve ikili
 * aramaya uygun iki küçük dosyaya çevirir:
 *   data/geoip-v4.bin   kayıt = başlangıç(4) bitiş(4) ülke(2)    — 10 bayt
 *   data/geoip-v6.bin   kayıt = başlangıç(16) bitiş(16) ülke(2)  — 34 bayt
 *   data/geoip-meta.json  ay, kayıt sayısı, kaynak
 * Arama inc/geo.php'de (vr_geoip_lookup): dosyada ~20 okuma, ağ yok.
 *
 * Ziyaretçinin IP'si hiçbir yere gönderilmez: indirilen yalnızca herkese açık
 * tablo, arama tamamen bu sunucuda. Ay değişmediyse hiçbir şey yapmaz.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../inc/config.php';

$force = in_array('--force', $argv, true);
$dir   = vr_data_dir();
$meta  = json_decode((string)@file_get_contents("$dir/geoip-meta.json"), true) ?: [];

$months = [gmdate('Y-m'), gmdate('Y-m', strtotime('first day of last month'))];
if (!$force && ($meta['month'] ?? '') === $months[0] && is_file("$dir/geoip-v4.bin")) {
    echo "IP→ülke tablosu güncel ({$meta['month']}, " . ($meta['v4'] ?? 0) . " + " . ($meta['v6'] ?? 0) . " aralık)\n";
    exit(0);
}

$gz = null; $month = '';
foreach ($months as $m) {
    if (!$force && ($meta['month'] ?? '') === $m && is_file("$dir/geoip-v4.bin")) {
        echo "Bu ayın tablosu henüz yayımlanmamış; $m tablosu kullanılıyor.\n";
        exit(0);
    }
    $url = "https://download.db-ip.com/free/dbip-country-lite-$m.csv.gz";
    $tmp = "$dir/.geoip-" . getmypid() . '.csv.gz';
    $ch  = curl_init($url);
    $fh  = fopen($tmp, 'wb');
    curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 180,
                            CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_USERAGENT => 'sarvesto-geoip/1.0']);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch); fclose($fh);
    if ($code === 200 && filesize($tmp) > 1_000_000) { $gz = $tmp; $month = $m; break; }
    @unlink($tmp);
    echo "  $m: HTTP $code\n";
}
if ($gz === null) { fwrite(STDERR, "IP→ülke tablosu indirilemedi — eski tablo (varsa) kullanılmaya devam ediyor.\n"); exit(1); }

$in  = gzopen($gz, 'rb');
$v4t = "$dir/.geoip-v4." . getmypid(); $v6t = "$dir/.geoip-v6." . getmypid();
$o4  = fopen($v4t, 'wb'); $o6 = fopen($v6t, 'wb');
$n4 = $n6 = 0; $last4 = ''; $last6 = ''; $sorted = true;
while (($line = gzgets($in)) !== false) {
    $r = str_getcsv(trim($line), ',', '"', '');
    if (count($r) < 3) continue;
    $cc = strtoupper(trim($r[2]));
    if (!preg_match('/^[A-Z]{2}$/', $cc) || $cc === 'ZZ') continue;
    $a = @inet_pton(trim($r[0])); $b = @inet_pton(trim($r[1]));
    if ($a === false || $b === false || strlen($a) !== strlen($b)) continue;
    if (strlen($a) === 4) {
        if ($a < $last4) $sorted = false;
        fwrite($o4, $a . $b . $cc); $n4++; $last4 = $a;
    } else {
        if ($a < $last6) $sorted = false;
        fwrite($o6, $a . $b . $cc); $n6++; $last6 = $a;
    }
}
gzclose($in); fclose($o4); fclose($o6); @unlink($gz);

if (!$sorted || $n4 < 100000) {
    @unlink($v4t); @unlink($v6t);
    fwrite(STDERR, "Tablo beklenen biçimde değil (sıralı: " . ($sorted ? 'evet' : 'hayır') . ", IPv4 aralık: $n4) — eski tablo korunuyor.\n");
    exit(1);
}
@chmod($v4t, 0640); @chmod($v6t, 0640);
rename($v4t, "$dir/geoip-v4.bin");
rename($v6t, "$dir/geoip-v6.bin");
file_put_contents("$dir/geoip-meta.json", json_encode([
    'month' => $month, 'v4' => $n4, 'v6' => $n6, 'built' => gmdate('c'),
    'source' => 'DB-IP IP to Country Lite (CC BY 4.0) https://db-ip.com',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
printf("IP→ülke tablosu: %s · IPv4 %d · IPv6 %d aralık\n", $month, $n4, $n6);
