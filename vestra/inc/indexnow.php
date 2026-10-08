<?php
/**
 * IndexNow — yeni ve degisen sayfalari Bing'e (ve IndexNow'a katilan diger
 * motorlara) aninda bildirir.
 *
 * NEDEN (operator, 8 Eki 2026: "chatgpt icin guclu yol ekle"): ChatGPT'nin arama
 * ozelligi sayfalari buyuk olcude Bing dizininden buluyor. Bing yeni bir ilani
 * kendi tarama sirasina gore haftalar sonra gorebilir; IndexNow ile ilan ayni gun
 * dizine giriyor. Hesap acmak gerekmiyor: anahtar, sitenin kokundeki
 * <anahtar>.txt dosyasiyla kanitlaniyor (gizli DEGIL, tasarim geregi herkese acik).
 *
 * Gonderen: cron_indexnow.php (gunluk, yalniz DEGISENLER) ve ilk toplu gonderim
 * icin ayni betigin --all kipi. Ayni adresi her gun yeniden gondermek IndexNow'un
 * kendi kurallarinda istenmiyor -- o yuzden durum dosyasi son kosuyu tutuyor.
 */
if (!defined('VESTRA_INDEXNOW_KEY')) define('VESTRA_INDEXNOW_KEY', '7922b201eec4b4be125ddd2c35700a42');
if (!defined('VESTRA_INDEXNOW_HOST')) define('VESTRA_INDEXNOW_HOST', 'vestrasales.com');

/** URL listesini gonderir. Donus: ['ok'=>bool,'code'=>int,'sent'=>int,'error'=>string]. */
function vestra_indexnow_submit(array $urls): array {
    $host = VESTRA_INDEXNOW_HOST;
    $urls = array_values(array_unique(array_filter($urls, fn($u) => is_string($u) && str_starts_with($u, 'https://'.$host.'/'))));
    if (!$urls) return ['ok' => true, 'code' => 0, 'sent' => 0, 'error' => ''];
    $urls = array_slice($urls, 0, 10000);                       // protokol tavani: istek basina 10.000
    $body = json_encode(['host' => $host, 'key' => VESTRA_INDEXNOW_KEY,
                         'keyLocation' => 'https://'.$host.'/'.VESTRA_INDEXNOW_KEY.'.txt', 'urlList' => $urls],
                        JSON_UNESCAPED_SLASHES);
    $code = 0; $err = '';
    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.indexnow.org/indexnow');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'], CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code === 0) $err = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json; charset=utf-8\r\n",
                                                 'content' => $body, 'timeout' => 20, 'ignore_errors' => true]]);
        @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
        if (isset($http_response_header[0]) && preg_match('~ (\d{3}) ~', $http_response_header[0], $m)) $code = (int)$m[1];
        else $err = 'baglanti yok';
    }
    /* 200 = alindi, 202 = alindi (anahtar dogrulamasi suruyor). Digerleri hata:
       400 bicim, 403 anahtar gecersiz, 422 adres hosta ait degil, 429 cok sik. */
    return ['ok' => in_array($code, [200, 202], true), 'code' => $code, 'sent' => count($urls), 'error' => $err];
}

/** Sitemap'teki TUM kanonik adresler (ilk toplu gonderim icin). sitemap.php'nin
 *  kendi ciktisindan okunur -- ikinci bir adres listesi yazilmaz. AYRI SURECTE:
 *  sitemap.php products.php'yi require ediyor ve o dosya ikinci kez yuklenince
 *  "Cannot redeclare" ile duser. */
function vestra_indexnow_all_urls(): array {
    $php = (PHP_BINARY !== '' && is_executable(PHP_BINARY)) ? PHP_BINARY : 'php';
    $xml = (string)shell_exec(escapeshellarg($php).' '.escapeshellarg(dirname(__DIR__).'/sitemap.php').' 2>/dev/null');
    preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);
    return array_map(fn($u) => html_entity_decode($u, ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1] ?? []);
}

/** $since'ten beri eklenen/degisen ilanlar, markalarinin ve kategorilerinin sayfalari
 *  ve yayimlanan journal yazilari. */
function vestra_indexnow_changed_urls(int $since): array {
    $base = 'https://'.VESTRA_INDEXNOW_HOST;
    $out = [];
    foreach (vestra_products() as $p) {
        $ts = max((int)strtotime((string)($p['updated_at'] ?? '')), (int)strtotime((string)($p['added_at'] ?? '')));
        if ($ts < $since) continue;
        $out[] = $base.'/product?id='.rawurlencode((string)$p['id']);
        $b = trim((string)($p['brand'] ?? ''));
        if ($b !== '') $out[] = $base.'/wholesale/'.vestra_brand_slug($b);
        $c = trim((string)($p['cat'] ?? ''));
        if ($c !== '' && strcasecmp($c, 'Other') !== 0 && function_exists('vestra_seo_cat_slug')) {
            $out[] = $base.'/b2b/'.vestra_seo_cat_slug($c);
            if ($b !== '') $out[] = $base.'/wholesale/'.vestra_brand_slug($b).'/'.vestra_seo_cat_slug($c);
        }
    }
    if (function_exists('vestra_journal_published')) {
        foreach (vestra_journal_published() as $a) {
            $ts = max((int)strtotime((string)($a['updated'] ?? '')), (int)strtotime((string)($a['created'] ?? '')));
            if ($ts >= $since && ($a['slug'] ?? '') !== '') $out[] = $base.'/journal?slug='.rawurlencode((string)$a['slug']);
        }
    }
    if ($out) { $out[] = $base.'/shop'; $out[] = $base.'/llms.txt'; }
    return array_values(array_unique($out));
}
