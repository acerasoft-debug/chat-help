<?php
/** VESTRA — PUBLIC brand catalogue export as a real .xlsx WITH EMBEDDED PRODUCT PHOTOS.
 *
 *  For cold-outreach / campaign links. Deliberately carries NO trade pricing and NO seller
 *  identity (a teaser line-sheet that entices boutiques to register for trade access); all
 *  confidential data stays behind the members-only exports. Unlike the old .xls-as-HTML trick
 *  (Excel drops <img> tags), this emits a genuine .xlsx with the product photos embedded via
 *  drawing anchors, so every row shows a picture.
 *
 *    /catalog?brand=lacoste   → .xlsx of that brand's live listings (photos embedded, no pricing)
 *    /catalog                 → the full public selection (all brands)
 *
 *  Download file name is intentionally neutral ("VESTRA-Selection-<date>.xlsx").
 */
require __DIR__.'/inc/products.php';
require __DIR__.'/inc/xlsx.php';

$brandQ = trim((string)($_GET['brand'] ?? ''));

$items = [];
foreach (vestra_products() as $p) {
    if ($brandQ !== '' && strcasecmp(trim((string)($p['brand'] ?? '')), $brandQ) !== 0) continue;
    $items[] = $p;
}

$headers = ['#', 'Brand', 'Product', 'Category', 'Colours', 'Sizes', 'MOQ', 'Photo'];
$rows = [];
$i = 0;
foreach ($items as $p) {
    $i++;
    // Resolve the primary image to a LOCAL file path so its bytes can be embedded.
    $img = vestra_primary_image($p);
    $local = '';
    if ($img !== '' && $img[0] === '/') { $cand = __DIR__ . $img; if (is_file($cand)) $local = $cand; }
    $colours = implode(', ', array_filter((array)($p['colors'] ?? [])));
    $rows[] = ['cells' => [
        (string)$i,
        (string)($p['brand'] ?? ''),
        (string)($p['name'] ?? ''),
        (string)($p['cat'] ?? ''),
        $colours,
        (string)($p['sizes'] ?? ''),
        ((int)($p['moq'] ?? 0)) . ' ' . (string)($p['unit'] ?? 'pc'),
        '',
    ], 'image' => $local];
}
if (!$rows) {
    $rows[] = ['cells' => ['', '', 'This selection is available on request — register free at vestrasales.com', '', '', '', '', ''], 'image' => ''];
}
// Footer note (no photo): drives registration; keeps trade pricing gated.
$rows[] = ['cells' => ['', '', 'Trade pricing & full line-sheets: register free at vestrasales.com — every seller KYC-verified, goods authenticity-verified on delivery, escrow-protected invoicing.', '', '', '', '', ''], 'image' => ''];

$title = $brandQ !== '' ? $brandQ : 'VESTRA Selection';
$xlsx = vestra_xlsx_with_photos($headers, $rows, $title);
if ($xlsx === '') { http_response_code(500); header('Content-Type: text/plain'); exit('catalog temporarily unavailable'); }

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="VESTRA-Selection-'.date('Y-m-d').'.xlsx"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: '.strlen($xlsx));
echo $xlsx;
