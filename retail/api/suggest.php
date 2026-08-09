<?php
/**
 * Arama önerileri (JSON)
 * ----------------------
 * Üst çubuktaki arama kutusu için. Salt okunur, oturum gerektirmez, yazma yok.
 *
 * Arama kutusu JavaScript olmadan da çalışıyor (form → shop.php); burası
 * sadece hızlandırma. Bu yüzden hata durumunda sessizce boş liste dönüyoruz:
 * öneri gelmemesi kullanıcı için bir arıza değil.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$q = trim((string)($_GET['q'] ?? ''));

// Çok kısa sorgu tüm katalogu tarar, faydası da yok.
if (mb_strlen($q) < 2) {
    echo json_encode(['items' => []]);
    exit;
}

// Kaba kuvvetle katalog taratmayı sınırla.
if (!vr_rate_ok('suggest', 120, 60)) {
    echo json_encode(['items' => []]);
    exit;
}

$rows  = vr_query(['q' => $q, 'per_page' => 6, 'in_stock' => true])['rows'];
$items = [];

foreach ($rows as $p) {
    $items[] = [
        'brand' => $p['brand'],
        'name'  => mb_substr(vr_card_name($p), 0, 60),
        'price' => vr_money((int)$p['price_cents']),
        'url'   => vr_url('product.php', ['id' => $p['id']]),
        // Öneri kutusundaki kare 48 piksel; oraya 2744 pikselli dosya
        // göndermenin anlamı yok (bkz. assets/img.php).
        'img'   => vr_img(vr_product_image($p), 180),
    ];
}

// Sonuç yoksa markayı öner: "balen" yazana BALENCIAGA sayfasını gösterelim.
if (!$items) {
    foreach (array_keys(vr_facets()['brands']) as $brand) {
        if (mb_stripos($brand, $q) === false) continue;
        $items[] = [
            'brand' => $brand,
            'name'  => t('view_all'),
            'price' => '',
            'url'   => vr_url('shop.php', ['brand' => $brand]),
            'img'   => vr_url('assets/art.php', ['s' => vr_slug($brand), 'w' => 84, 'h' => 104]),
        ];
        if (count($items) >= 4) break;
    }
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
