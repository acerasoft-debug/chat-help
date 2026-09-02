<?php
/* Yalnizca yerel sinama sunucusu icin (php -S 127.0.0.1:8085 -t vestra vestra/_router_local.php).
   .htaccess'teki yeniden yazma kurallarinin aynasi -- canlidaki adresler burada da
   ayni dosyaya dussun; aksi halde /wholesale/lacoste ve bilinmeyen adres index.php'ye
   dusuyor ve "404 sayfasi" diye ana sayfa inceleniyordu (2 Eyl 2026). */
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($p !== '/' && file_exists(__DIR__ . $p) && !is_dir(__DIR__ . $p)) return false;   // gercek dosya (css, png, js)
$try = ltrim($p, '/');
if (str_starts_with($try, 'data/') || str_starts_with($try, 'vendor/')) { http_response_code(403); exit; }
/* Ozel kurallar (.htaccess ile birebir) */
if (preg_match('#^wholesale/([A-Za-z0-9][A-Za-z0-9-]*)/?$#', $try, $m)) { $_GET['brand'] = $m[1]; $_REQUEST['brand'] = $m[1]; require __DIR__ . '/wholesale.php'; return true; }
$map = ['sitemap.xml' => 'sitemap.php', 'wholesale-list.pdf' => 'wholesale-list.php', 'wholesale-list.xlsx' => 'wholesale-xlsx.php',
        'membership' => 'membership.php', 'shop' => 'shop.php', 'price-lists' => 'price-lists.php', 'price-list' => 'price-list.php'];
$k = rtrim($try, '/');
if (isset($map[$k])) { require __DIR__ . '/' . $map[$k]; return true; }
if ($k === '') { require __DIR__ . '/index.php'; return true; }
foreach ([$k . '.php', $k . '/index.php'] as $c) {
    if (file_exists(__DIR__ . '/' . $c)) { require __DIR__ . '/' . $c; return true; }
}
/* Hicbir dosyaya cikmayan adres -> markali 404 (canlida da boyle). */
require __DIR__ . '/404.php';
return true;
