<?php
/**
 * robots.txt (dinamik, çünkü kurulum yolu ve host değişebiliyor).
 * Sunucuda /robots.txt olarak sunmak için README'deki yönlendirme kuralına bak.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';

header('Content-Type: text/plain; charset=utf-8');
$base = vr_base_url();

// Yardımcı adresten geldiyse hiçbir şey taranmasın. Site aynı sayfaları
// birden fazla adresten döndürüyor (DNS taşınana kadar açtığımız test alt
// adı, cPanel'in ek alanla birlikte ürettiği alt adlar). Hepsi açık kalırsa
// arama motoru bunları çoğaltılmış içerik sayıp hangisini sıralayacağına
// kendi karar veriyor — ve genelde asıl adresi seçmiyor.
if (!vr_is_canonical_host()) {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}

echo "User-agent: *\n";
// Kişisel/işlemsel alanlar taranmasın: hem gereksiz yük hem gizlilik.
foreach (['cart.php', 'checkout.php', 'order.php', 'newsletter.php', 'wishlist.php',
          'seller/', 'account/', 'admin/', 'api/', 'tools/', 'data/'] as $p) {
    echo "Disallow: {$base}/{$p}\n";
}
// Filtre kombinasyonları sonsuz sayıda URL üretir; asıl sayfalar zaten açık.
echo "Disallow: {$base}/shop.php?*min=\n";
echo "Disallow: {$base}/shop.php?*max=\n";
echo "Allow: {$base}/\n\n";

echo "Sitemap: " . vr_origin() . $base . "/sitemap.php\n";
