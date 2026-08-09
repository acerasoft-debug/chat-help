<?php
/** VESTRA — PUBLIC brand catalogue export as a real .xlsx WITH EMBEDDED PRODUCT PHOTOS.
 *
 *  For cold-outreach / campaign links. Deliberately carries NO trade pricing and NO seller
 *  identity (a teaser line-sheet that entices boutiques to register for trade access); all
 *  confidential data stays behind the members-only exports. Unlike the old .xls-as-HTML trick
 *  (Excel drops <img> tags), this emits a genuine .xlsx with the product photos embedded via
 *  drawing anchors, so every row shows a picture.
 *
 *  One row PER COLOURWAY where a product has variants (so every colour shows its own photo +
 *  article/model code); products without variants fall back to a single row (primary photo,
 *  SKU/reference code). Every row carries an identification code — article code → SKU → id —
 *  so buyers can match items to their own systems.
 *
 *    /catalog?brand=lacoste   → .xlsx of that brand's listings (photos + codes, no pricing)
 *    /catalog                 → the full public selection (all brands)
 *
 *  Download file name is intentionally neutral ("VESTRA-Selection-<date>.xlsx").
 */
/* Public by design (see header above): a members-only gate was tried here and broke
   every campaign-email download link, since cold leads have no session to gate against.
   No trade pricing or seller identity is in this export, so there is nothing the gate
   was protecting -- back to public. */
require __DIR__.'/inc/products.php';
require __DIR__.'/inc/xlsx.php';

/* Accepts one brand ("Lacoste") or several, comma-separated
   ("Lacoste,Amiri"), so a buyer can pull exactly the brands they care about in a
   single sheet instead of downloading each one and merging by hand. */
$brandQ  = trim((string)($_GET['brand'] ?? ''));
$wanted  = array_values(array_filter(array_map('trim', explode(',', $brandQ)), fn($b) => $b !== ''));

$items = [];
foreach (vestra_products() as $p) {
    if ($wanted) {
        $b = trim((string)($p['brand'] ?? ''));
        $hit = false;
        foreach ($wanted as $w) { if (strcasecmp($b, $w) === 0) { $hit = true; break; } }
        if (!$hit) continue;
    }
    $items[] = $p;
}

/* A never-empty identification code for a row: variant article code → product SKU →
   uppercased id. Lets every product/colour carry a code buyers can reference. */
function vestra_export_code(array $p, array $v = []): string {
    $art = trim((string)($v['art'] ?? ''));
    if ($art !== '') return $art;
    $sku = trim((string)($p['sku'] ?? ''));
    if ($sku !== '') return $sku;
    $id = trim((string)($p['id'] ?? ''));
    return $id !== '' ? strtoupper($id) : '';
}
/* Resolve a web image path ("/uploads/..") to a local file for embedding, or '' if absent. */
function vestra_export_local(string $img): string {
    if ($img !== '' && $img[0] === '/') { $c = __DIR__.$img; if (is_file($c)) return $c; }
    return '';
}

$headers = ['#', 'Brand', 'Product', 'Colour', 'Article / Code', 'Model / Ref', 'MOQ', 'Photo'];
$rows = [];
$i = 0;
foreach ($items as $p) {
    $brand = (string)($p['brand'] ?? '');
    $name  = (string)($p['name'] ?? '');
    $moq   = ((int)($p['moq'] ?? 0)) . ' ' . (string)($p['unit'] ?? 'pc');
    $variants = (!empty($p['variants']) && is_array($p['variants'])) ? $p['variants'] : [];
    if ($variants) {
        // One row per colourway → each shows its own photo + article/model code.
        foreach ($variants as $v) {
            $i++;
            $rows[] = ['cells' => [
                (string)$i, $brand, $name,
                (string)($v['color'] ?? ''),
                vestra_export_code($p, $v),
                (string)($v['model'] ?? ''),
                $moq, '',
            ], 'image' => vestra_export_local((string)($v['image'] ?? ''))];
        }
    } else {
        $i++;
        $colours = implode(', ', array_filter((array)($p['colors'] ?? [])));
        $rows[] = ['cells' => [
            (string)$i, $brand, $name,
            $colours,
            vestra_export_code($p),
            '',
            $moq, '',
        ], 'image' => vestra_export_local(vestra_primary_image($p))];
    }
}
if (!$rows) {
    $rows[] = ['cells' => ['', '', 'This selection is available on request — register free at vestrasales.com', '', '', '', '', ''], 'image' => ''];
}
// Footer note (no photo): drives registration; keeps trade pricing gated.
$rows[] = ['cells' => ['', '', 'Trade pricing & full line-sheets: register free at vestrasales.com — every seller KYC-verified, goods authenticity-verified on delivery, escrow-protected invoicing.', '', '', '', '', ''], 'image' => ''];

$title = count($wanted) === 1 ? $wanted[0] : 'VESTRA Selection';
$xlsx = vestra_xlsx_with_photos_file($headers, $rows, $title);
if ($xlsx === '') { http_response_code(500); header('Content-Type: text/plain'); exit('catalog temporarily unavailable'); }

/* Delivery. These sheets run 1.7-17.8 MB because every row embeds a photo, and at
   that size the transfer is what breaks, not the build.
   1. Any buffered output -- a stray newline or BOM from an include -- would sit in
      front of the zip header and make Excel reject the file. Discard it.
   2. zlib.output_compression is commonly on for shared hosting. It would gzip the
      body while the Content-Length below still advertises the UNCOMPRESSED size, so
      the client stops reading early and saves a truncated, unopenable file. A .xlsx
      is a zip and is already compressed, so re-compressing only costs CPU anyway. */
while (ob_get_level() > 0) ob_end_clean();
if (function_exists('ini_set')) { @ini_set('zlib.output_compression', 'Off'); }
@ini_set('max_execution_time', '120');

$fname = 'VESTRA-Selection-'.date('Y-m-d').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: '.filesize($xlsx));  // now truthful: no buffer, no compression
header('Accept-Ranges: none');
header('Cache-Control: private, max-age=300');
/* Streamed, not echoed. Holding the finished workbook in a string was the last of the
   three catalogue-sized copies that pushed this page past the 128 MB limit; readfile()
   sends it in chunks, so the response size no longer sets the memory ceiling. */
readfile($xlsx);
@unlink($xlsx);
flush();
