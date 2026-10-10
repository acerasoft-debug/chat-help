<?php
/**
 * Web uygulaması manifesti — /manifest.webmanifest (bkz. .htaccess).
 *
 * Android'de "Ana ekrana ekle / Uygulamayı yükle", iPhone'da "Ana Ekrana Ekle"
 * bu dosyayla tam ekran, kendi ikonlu bir uygulama olarak açılıyor. Ad ve dil
 * config'den geliyor: marka adı değişirse uygulamanın adı da değişir.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/boot.php';
require_once __DIR__ . '/inc/i18n.php';

$brand = (string)vr_config('brand');
$base  = vr_base_url();
$icon  = static fn(string $f): string => $base . '/assets/app/' . $f;

$m = [
    'id'               => $base . '/',
    'name'             => $brand,
    'short_name'       => $brand,
    'description'      => t('tagline'),
    'lang'             => vr_lang(),
    'dir'              => 'ltr',
    'start_url'        => vr_url('/', ['source' => 'app']),
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation'      => 'portrait',
    'background_color' => '#ffffff',
    'theme_color'      => '#000000',
    'categories'       => ['shopping', 'lifestyle'],
    'icons' => [
        ['src' => $icon('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icon('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icon('icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => t('nav_new'),    'url' => vr_url('shop.php', ['sort' => 'new', 'source' => 'app'])],
        ['name' => t('nav_outlet'), 'url' => vr_url('outlet.php', ['source' => 'app'])],
        ['name' => t('nav_wish'),  'url' => vr_url('wishlist.php', ['source' => 'app'])],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo json_encode($m, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
