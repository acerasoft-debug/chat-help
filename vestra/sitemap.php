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
  /* Dropshipping'i ARAYAN kisi genelde henuz musterimiz degil ve "dropshipping
     supplier Europe" diye ariyor; anlatim sayfasi o aramanin inebilecegi tek
     sayfa. Satin alma sayfasi (/dropship) girissiz bos gorunecegi icin burada
     olmasi gereken bu. */
  ['/dropshipping', 'monthly', '0.7'],
  ['/api-docs', 'monthly', '0.5'],
  /* /register is deliberately absent: robots.txt disallows it along with the rest of
     the account pages, and listing a blocked URL here only invites the "indexed,
     though blocked" result with no snippet. /seller-invite takes its place — it is
     the public landing page sellers are actually meant to arrive on. */
  ['/seller-invite', 'monthly', '0.6'],
];
/* Per-brand B2B landing pages. These are the pages trade searches actually land on
   ("Lacoste wholesale"), so they rank above individual listings here -- and they are
   derived from live stock, so a sold-out house drops out of the sitemap by itself. */
foreach (vestra_seo_brands(0) as $b) {
  $urls[] = ['/wholesale/'.vestra_brand_slug($b), 'weekly', '0.8'];
}
/* Category, collection and brand × category landing pages (inc/seo.php). Same rule as
   the brand pages: derived from live stock, so nothing is listed that would render 404. */
foreach (vestra_seo_landing_paths() as $row) $urls[] = $row;
/* Pazar (ulke/bolge) inis sayfalari -- /wholesale-to/<pazar>. Tablo inc/seo.php'de;
   sayfalar katalog bos olmadigi surece 200 donuyor (market.php stok yoksa 404 basar,
   ve o durumda katalogun tamami bos demektir, yani sitemap'te zaten urun de yok). */
foreach (vestra_seo_market_paths() as $row) $urls[] = $row;
/* <lastmod>: dorduncu alan, ve YALNIZCA kaydin kendi tarihi varsa yaziliyor.
   Bugunun tarihini her satira basmak, her tarama icin "her sey degisti" demek
   olurdu -- tarama butcesi gercekten degisen sayfalardan calinir ve sinyal
   guvenilmez hale gelir. Tarih uydurulmuyor: alani olmayan satirda etiket yok
   (KURAL 3'un sitemap hali). */
foreach (vestra_products() as $p) {
  $urls[] = ['/product?id='.rawurlencode($p['id']), 'weekly', '0.6', vestra_sitemap_date($p['updated_at'] ?? ($p['added_at'] ?? ''))];
}
foreach (vestra_journal_published() as $a) {
  $urls[] = ['/journal?slug='.rawurlencode($a['slug'] ?? ''), 'monthly', '0.5', vestra_sitemap_date($a['updated'] ?? ($a['created'] ?? ''))];
}

/** Ham tarih -> W3C tarih bicimi, cozulemezse '' (satirda etiket basilmaz). */
function vestra_sitemap_date($raw): string {
  $raw = trim((string)$raw);
  if ($raw === '') return '';
  $ts = strtotime($raw);
  return $ts ? date('Y-m-d', $ts) : '';
}
$langs = array_keys(vlang_list());   // derived, not hardcoded — every site language is emitted
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
foreach ($urls as $row) {
  [$path, $freq, $prio] = $row; $mod = $row[3] ?? '';
  $sep = str_contains($path, '?') ? '&' : '?';
  echo "  <url><loc>".htmlspecialchars($host.$path, ENT_XML1)."</loc>\n";
  foreach ($langs as $l) {
    $href = $host.$path.($l === 'en' ? '' : $sep.'lang='.$l);
    echo '    <xhtml:link rel="alternate" hreflang="'.$l.'" href="'.htmlspecialchars($href, ENT_XML1)."\"/>\n";
  }
  echo '    <xhtml:link rel="alternate" hreflang="x-default" href="'.htmlspecialchars($host.$path, ENT_XML1)."\"/>\n";
  if ($mod !== '') echo "    <lastmod>$mod</lastmod>\n";
  echo "    <changefreq>$freq</changefreq><priority>$prio</priority></url>\n";
}
echo "</urlset>\n";
