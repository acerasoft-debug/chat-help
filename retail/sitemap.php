<?php
/**
 * sitemap.xml — dinamik
 * Statik sayfalar + tüm ürünler + Vault losları + marka/kategori sayfaları,
 * her biri için hreflang alternatifleri (xhtml:link). 10 dil × N URL büyük bir
 * dosya olabildiği için 45.000 URL'de kesiyoruz (sınır 50.000).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');       // sitemap'in kendisi indekslenmesin

$langs = (array)vr_config('languages', ['en']);
$prim  = (string)vr_config('primary_lang', 'en');
$max   = 45000;
$count = 0;

/** Bir URL bloğu bas: tüm dil alternatifleriyle. */
$emit = static function (string $path, array $params = [], string $freq = 'weekly', string $prio = '0.6', ?int $lastmod = null) use ($langs, $prim, &$count, $max): void {
    if ($count >= $max) return;
    $count++;

    $abs = static function (string $lang) use ($path, $params, $prim): string {
        $p = $params;
        if ($lang !== $prim) $p['lang'] = $lang;
        return vr_origin() . vr_base_url() . '/' . ltrim($path, '/') . ($p ? '?' . http_build_query($p) : '');
    };

    echo "<url>\n";
    echo '  <loc>' . h($abs($prim)) . "</loc>\n";
    foreach ($langs as $lg) {
        echo '  <xhtml:link rel="alternate" hreflang="' . h($lg) . '" href="' . h($abs($lg)) . "\"/>\n";
    }
    echo '  <xhtml:link rel="alternate" hreflang="x-default" href="' . h($abs($prim)) . "\"/>\n";
    if ($lastmod) echo '  <lastmod>' . gmdate('Y-m-d', $lastmod) . "</lastmod>\n";
    echo '  <changefreq>' . h($freq) . "</changefreq>\n";
    echo '  <priority>' . h($prio) . "</priority>\n";
    echo "</url>\n";
};

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

// ---- sabit sayfalar
$emit('/', [], 'daily', '1.0');
$emit('shop.php', [], 'daily', '0.9');
$emit('outlet.php', [], 'hourly', '0.9');      // fiyat sürekli değişiyor
$emit('brands.php', [], 'weekly', '0.7');
$emit('sell.php', [], 'monthly', '0.7');
$emit('faq.php', [], 'monthly', '0.6');

foreach (['impressum', 'agb', 'widerruf', 'rueckgabe', 'versand', 'zahlung',
          'datenschutz', 'cookies', 'verkaeufer', 'streitbeilegung', 'barrierefreiheit'] as $doc) {
    $emit('legal/' . $doc . '.php', [], 'yearly', '0.3');
}

// ---- marka ve kategori sayfaları
$facets = vr_facets(['exclude_vault' => true]);
foreach (array_keys($facets['brands']) as $brand) $emit('shop.php', ['brand' => $brand], 'weekly', '0.7');
foreach (array_keys($facets['cats'])   as $cat)   $emit('shop.php', ['cat' => $cat], 'weekly', '0.6');

// ---- Vault losları
foreach (vr_vault_lots() as $v) {
    $emit('outlet.php', ['lot' => $v['lot_id']], 'hourly', '0.8');
}

// ---- ürünler
foreach (vr_catalog() as $p) {
    if ((int)$p['stock'] < 1) continue;         // tükenmişleri sitemap'e koymuyoruz
    $emit('product.php', ['id' => $p['id']], 'weekly', '0.8',
          (int)$p['created_at'] > 0 ? (int)$p['created_at'] : null);
}

echo "</urlset>\n";
