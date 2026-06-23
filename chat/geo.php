<?php
/**
 * ChatHelp — IP'den ülke tespiti (geo) + önbellek
 * Ziyaretçinin IP'sine göre ülke kodu döner -> ön yüz CC + dili otomatik ayarlar.
 * GET -> {country:'FR', lang:'fr', ip:'...'}
 * Kullanıcı manuel seçtiyse ön yüz bunu ezer (localStorage).
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
error_reporting(0); @ini_set('display_errors','0');

/* Gerçek ziyaretçi IP'si (proxy arkasında olabilir) */
function visitor_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '';
}

/* Desteklenen ülkeler -> varsayılan dil eşlemesi */
$SUP = ['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','LU'=>'fr',
        'GB'=>'en','US'=>'en','TR'=>'tr','RU'=>'ru','ES'=>'es','IT'=>'it','NL'=>'en'];

$ip = visitor_ip();
$country = ''; $src = 'none';

/* Cloudflare/sunucu başlığı varsa (en hızlı) */
if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) { $country = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']); $src='header'; }

/* Önbellek (IP başına 30 gün) */
$dir = __DIR__ . '/cache_geo';
$cacheFile = $ip ? ($dir . '/' . md5($ip) . '.txt') : '';
if (!$country && $cacheFile && is_file($cacheFile) && (time() - filemtime($cacheFile) < 2592000)) {
    $country = trim(@file_get_contents($cacheFile)); $src='cache';
}

/* ip-api.com (ücretsiz, anahtarsız) */
if (!$country && $ip) {
    $ch = curl_init('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3]);
    $res = curl_exec($ch); curl_close($ch);
    $d = json_decode($res, true);
    if (($d['status'] ?? '') === 'success' && !empty($d['countryCode'])) {
        $country = strtoupper($d['countryCode']); $src='ip-api';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if ($cacheFile) @file_put_contents($cacheFile, $country);
    }
}

if (!$country) $country = 'DE'; // güvenli varsayılan

/* Sadece desteklenen ülkeleri "law" olarak ver; diğerleri için DE varsayılan ama dil önerisi yine de */
$lawCountry = isset($SUP[$country]) ? $country : 'DE';
$lang = $SUP[$country] ?? 'de';

echo json_encode([
    'country' => $lawCountry,   // hukuk/belge için (DE/FR/AT/CH...)
    'detected'=> $country,      // gerçek tespit (bilgi)
    'lang'    => $lang,         // önerilen arayüz dili
    'ip'      => $ip,
    'src'     => $src,
]);
