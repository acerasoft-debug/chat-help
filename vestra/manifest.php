<?php
/* Uygulama manifesti — site.webmanifest'in DİLE GÖRE sürümü (12 Eki 2026).
   Sabit dosya yalnızca İngilizce'ydi: Almanca bir alıcı uygulamayı yükleyince ana ekranda
   "B2B Fashion Wholesale" ve İngilizce kısayollar görüyordu. Burada ad, açıklama ve
   kısayollar ziyaretçinin diliyle yazılır (head.php ?lang= ile sayfanın dilini geçirir;
   tarayıcı manifesti çerezsiz çeker, bu yüzden çerez tek başına yetmezdi).
   ?app=admin: operatörün ayrı "VESTRA Admin" uygulaması — /admin'de açılır, kendi
   kısayolları var; kimliği (id) ayrı olduğundan müşteri uygulamasıyla karışmaz. */
require_once __DIR__.'/inc/i18n.php';
$base = json_decode((string)@file_get_contents(__DIR__.'/site.webmanifest'), true);
if (!is_array($base)) $base = ['icons' => []];
$admin = (($_GET['app'] ?? '') === 'admin');
$l = $admin ? 'en' : vlang();
$ic = [['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png']];
$sc = function (string $name, string $short, string $url) use ($ic): array {
    return ['name' => $name, 'short_name' => $short, 'url' => $url, 'icons' => $ic];
};
if ($admin) {
    $m = array_merge($base, [
        'id'          => '/admin',
        'name'        => 'VESTRA Admin',
        'short_name'  => 'VESTRA Admin',
        'description' => 'VESTRA operatör paneli — siparişler, onaylar, mesajlar ve bildirimler.',
        'lang'        => 'tr',
        'dir'         => 'ltr',
        'start_url'   => '/admin?src=pwa',
        'scope'       => '/',
        'theme_color' => '#211d17',
        'background_color' => '#f4f2ee',
        'categories'  => ['business'],
        'shortcuts'   => [
            $sc('Siparişler', 'Siparişler', '/admin?tab=orders&src=pwa'),
            $sc('Onaylar', 'Onaylar', '/admin?tab=approvals&src=pwa'),
            $sc('Mesajlar', 'Mesajlar', '/admin?tab=messages&src=pwa'),
            $sc('Müşteriler', 'Müşteriler', '/admin?tab=prospects&src=pwa'),
        ],
    ]);
} else {
    $m = array_merge($base, [
        'name'        => 'VESTRA — '.t('B2B Fashion Wholesale'),
        'short_name'  => 'VESTRA',
        'description' => t('Verified B2B fashion wholesale marketplace — live prices, orders and messages.'),
        'lang'        => $l,
        'dir'         => vlang_dir(),
        'start_url'   => '/?src=pwa'.($l !== 'en' ? '&lang='.$l : ''),
        'shortcuts'   => [
            $sc(t('Catalog'), t('Catalog'), '/shop?src=pwa'),
            $sc(t('My orders'), t('Orders'), '/me?tab=orders&src=pwa'),
            $sc(t('Messages'), t('Messages'), '/me?tab=messages&src=pwa'),
            $sc(t('Cart'), t('Cart'), '/cart?src=pwa'),
        ],
    ]);
}
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex');
echo json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
