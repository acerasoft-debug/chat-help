<?php
/**
 * IndexNow gönderimi — CLI
 * ========================
 *   php tools/indexnow.php --sample        # ana sayfalar + en yeni ürünler
 *   php tools/indexnow.php URL [URL ...]   # belirli adresler
 *
 * Bing ve Yandex'e içerik değiştiğini ANINDA bildirir (Google IndexNow'ı
 * kullanmıyor; orada sitemap + Search Console geçerli). Anahtar
 * config seo.indexnow_key'de tanımlı değilse sessizce çıkar — hata değil.
 *
 * Anahtar doğrulaması: https://sarvesto.com/<anahtar>.txt (indexnow-key.php
 * üzerinden dinamik sunulur), keyLocation olarak bu adres bildirilir.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); echo "CLI only\n"; exit(1); }

require_once __DIR__ . '/../inc/view.php';

$key = trim((string)(vr_config('seo')['indexnow_key'] ?? ''));
if ($key === '') { echo "IndexNow-Schlüssel nicht gesetzt (config seo.indexnow_key) — übersprungen.\n"; exit(0); }

$origin = vr_canonical_origin();
$args = array_slice($argv, 1);
$urls = [];

if (in_array('--sample', $args, true) || !$args) {
    foreach (['/', '/shop.php', '/outlet.php', '/brands.php', '/journal.php'] as $p) $urls[] = $origin . $p;
    // En yeni 200 ürün (created_at'e göre)
    $cat = array_values(vr_catalog());
    usort($cat, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));
    foreach (array_slice($cat, 0, 200) as $p) {
        if ((int)$p['stock'] < 1) continue;
        $urls[] = $origin . vr_product_url($p);
    }
} else {
    foreach ($args as $a) {
        if ($a === '' || $a[0] === '-') continue;
        $urls[] = str_starts_with($a, 'http') ? $a : $origin . '/' . ltrim($a, '/');
    }
}

$urls = array_values(array_unique($urls));
if (!$urls) { echo "Gönderilecek adres yok.\n"; exit(0); }

$host = (string)parse_url($origin, PHP_URL_HOST);
$payload = [
    'host'        => $host,
    'key'         => $key,
    'keyLocation' => $origin . '/' . $key . '.txt',
    'urlList'     => array_slice($urls, 0, 10000),
];

$ok = 0;
foreach (['https://api.indexnow.org/indexnow', 'https://www.bing.com/indexnow'] as $ep) {
    $ch = curl_init($ep);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $st  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // IndexNow: 200/202 kabul, 200 = işlendi, 202 = alındı/doğrulama bekliyor.
    echo basename($ep) . ": HTTP $st · " . count($payload['urlList']) . " URL\n";
    if ($st === 200 || $st === 202) $ok++;
}
echo $ok > 0 ? "IndexNow gönderimi tamam.\n" : "IndexNow gönderimi başarısız (anahtar/doğrulama dosyasını kontrol edin).\n";
exit($ok > 0 ? 0 : 1);
