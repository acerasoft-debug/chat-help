<?php
/** VESTRA — dynamic sitemap (rewritten from /sitemap.xml). Public pages + product cards. */
require __DIR__.'/inc/products.php';
require __DIR__.'/inc/journal.php';
header('Content-Type: application/xml; charset=UTF-8');
$host = 'https://'.($_SERVER['HTTP_HOST'] ?? 'vestrasales.com');
$urls = [
  ['/', 'daily', '1.0'],
  ['/shop', 'daily', '0.9'],
  ['/groups', 'weekly', '0.7'],
  ['/requests', 'weekly', '0.6'],
  ['/membership', 'monthly', '0.5'],
  ['/journal', 'daily', '0.7'],
  ['/help', 'monthly', '0.5'],
  ['/faq', 'monthly', '0.5'],
  ['/register', 'monthly', '0.5'],
];
foreach (vestra_products() as $p) {
  $urls[] = ['/product?id='.rawurlencode($p['id']), 'weekly', '0.6'];
}
foreach (vestra_journal_published() as $a) {
  $urls[] = ['/journal?slug='.rawurlencode($a['slug'] ?? ''), 'monthly', '0.5'];
}
$langs = array_keys(vlang_list());   // en, de, fr, it, es
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
foreach ($urls as [$path, $freq, $prio]) {
  $sep = str_contains($path, '?') ? '&' : '?';
  echo "  <url><loc>".htmlspecialchars($host.$path, ENT_XML1)."</loc>\n";
  foreach ($langs as $l) {
    $href = $host.$path.($l === 'en' ? '' : $sep.'lang='.$l);
    echo '    <xhtml:link rel="alternate" hreflang="'.$l.'" href="'.htmlspecialchars($href, ENT_XML1)."\"/>\n";
  }
  echo '    <xhtml:link rel="alternate" hreflang="x-default" href="'.htmlspecialchars($host.$path, ENT_XML1)."\"/>\n";
  echo "    <changefreq>$freq</changefreq><priority>$prio</priority></url>\n";
}
echo "</urlset>\n";
