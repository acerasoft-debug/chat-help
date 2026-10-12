<?php
/**
 * Fotoğraf kalitesi denetimi (SALT OKUNUR) — php tools/audit-photo-quality.php [--root=DIR]
 * ======================================================================================
 * Vitrinde görünen her ürün (renk grubunun temsilcisi + tek satırlar) için:
 *   n       galerideki kare sayısı (kopyalar ayıklandıktan sonra)
 *   short   kart karesinin kısa kenarı (px) — küçükse büyütünce bulanık
 *   grid    kart karesi kontakt sayfası / kolaj mı
 *   fill    kadrajın doluluğu (çok düşükse ürün küçücük duruyor)
 *   web     internetten onaylı ek kare var mı
 * ve bir "zayıflık" puanı. Çıktı JSON (en zayıf başta). Hiçbir şey yazmaz.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/catalog.php';
require_once $root . '/inc/view.php';

$cat   = vr_catalog();
$shape = vr_photo_shape_index();
$grid  = vr_photo_grid_index();
$extra = (array)vr_store_read('product-images-extra.json', []);
$vix   = vr_variant_index();
$hidden = [];
foreach ($vix as $ids) foreach (array_slice($ids, 1) as $id) $hidden[$id] = 1;   // şeritte, vitrinde değil

$out = [];
foreach ($cat as $id => $p) {
    $id = (string)$id;
    if (isset($hidden[$id])) continue;
    $imgs = array_values(array_filter((array)($p['images'] ?? []), 'strlen'));
    $card = vr_card_image($p);
    $m = $shape[$card] ?? [];
    $w = (int)($m['w'] ?? 0); $h = (int)($m['h'] ?? 0);
    $short = $w && $h ? min($w, $h) : 0;
    $isGrid = isset($grid[$card]);
    $fill = isset($m['fill']) ? (float)$m['fill'] : null;
    $web = 0;
    foreach ($extra as $k => $v) if ($k === $id || str_starts_with($k, $id . '@')) $web += count((array)$v);
    $real = array_values(array_filter($imgs, fn($x) => str_starts_with($x, '/uploads/')));

    $score = 0;
    $why = [];
    if (count($real) <= 1) { $score += 40; $why[] = 'tek kare'; }
    elseif (count($real) === 2) { $score += 15; $why[] = 'iki kare'; }
    if ($short > 0 && $short < 500) { $score += 30; $why[] = "küçük ($short px)"; }
    elseif ($short > 0 && $short < 750) { $score += 12; $why[] = "orta ($short px)"; }
    if ($isGrid) { $score += 35; $why[] = 'kart karesi kolaj'; }
    if ($fill !== null && $fill < 0.12) { $score += 15; $why[] = 'ürün kadrajda küçük'; }
    if (!$real) { $score += 60; $why[] = 'gerçek fotoğraf yok'; }

    $out[] = ['id' => $id, 'brand' => (string)$p['brand'], 'name' => vr_card_name($p), 'sku' => vr_model_code($p) ?: (string)($p['sku'] ?? ''),
              'colour' => (string)(vr_product_colours($p)[0] ?? ''), 'n' => count($real), 'card' => $card, 'w' => $w, 'h' => $h,
              'grid' => $isGrid, 'fill' => $fill, 'web' => $web, 'score' => $score, 'why' => $why,
              'stock' => (int)$p['stock'], 'images' => array_slice($real, 0, 8)];
}
usort($out, fn($a, $b) => [$b['score'], $a['brand'], $a['id']] <=> [$a['score'], $b['brand'], $b['id']]);
$sum = ['visible' => count($out), 'weak' => count(array_filter($out, fn($r) => $r['score'] >= 30)),
        'single' => count(array_filter($out, fn($r) => $r['n'] <= 1)), 'small' => count(array_filter($out, fn($r) => $r['w'] && min($r['w'], $r['h']) < 500)),
        'grid' => count(array_filter($out, fn($r) => $r['grid']))];
echo json_encode(['summary' => $sum, 'products' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
