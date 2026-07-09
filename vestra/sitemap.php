<?php
/** VESTRA — dynamic sitemap (rewritten from /sitemap.xml). Public pages + product cards. */
require __DIR__.'/inc/products.php';
header('Content-Type: application/xml; charset=UTF-8');
$host = 'https://'.($_SERVER['HTTP_HOST'] ?? 'vestrasales.com');
$urls = [
  ['/', 'daily', '1.0'],
  ['/shop', 'daily', '0.9'],
  ['/groups', 'weekly', '0.7'],
  ['/requests', 'weekly', '0.6'],
  ['/membership', 'monthly', '0.5'],
  ['/faq', 'monthly', '0.5'],
  ['/register', 'monthly', '0.5'],
];
foreach (vestra_products() as $p) {
  $urls[] = ['/product?id='.rawurlencode($p['id']), 'weekly', '0.6'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
foreach ($urls as [$path, $freq, $prio]) {
  echo "  <url><loc>".htmlspecialchars($host.$path, ENT_XML1)."</loc><changefreq>$freq</changefreq><priority>$prio</priority></url>\n";
}
echo "</urlset>\n";
