<?php
/**
 * VESTRA — wholesale price list as Excel, with photographs embedded in the sheet.
 *
 *   /wholesale-list.xlsx                every brand
 *   /wholesale-list.xlsx?brand=Balmain  one brand
 *
 * The companion to wholesale-list.php. Same articles, same prices, same rules about what
 * is and is not printed; the difference is that a buyer can sort, filter and paste this
 * one into their own buying sheet, which is what a retailer actually does with a price
 * list before they order.
 *
 * It carries one column the PDF cannot: a real hyperlink per row is not needed, because
 * the URL is a cell — Excel makes it clickable on its own and it survives a paste into
 * any other system.
 *
 * PRICES AND CODES follow wholesale-list.php exactly:
 *   - ART. NO is the manufacturer's own article number; VESTRA REF is our internal id.
 *   - RETAIL is the brand's 'rrp' where one is stored, and wholesale x3 where it is not.
 *     The RETAIL SOURCE column says which of the two every single row is, so a computed
 *     number can never be mistaken for a brand-set one after a sort or a copy-paste.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/xlsx.php';


$brandFilter = trim((string)($_GET['brand'] ?? ''));

$byBrand = [];
foreach (vestra_products() as $p) {
    $brand = trim((string)($p['brand'] ?? ''));
    $price = (float)($p['list'] ?? $p['price'] ?? 0);
    if ($brand === '' || $price <= 0) continue;
    if ($brandFilter !== '' && strcasecmp($brand, $brandFilter) !== 0) continue;
    $byBrand[$brand][] = $p;
}
ksort($byBrand, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($byBrand as &$rs) {
    usort($rs, fn($a, $b) => strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
}
unset($rs);

/* Photo LAST: vestra_xlsx_with_photos_file() reserves the final column for the image. */
$headers = ['#', 'Brand', 'Art. No', 'VESTRA Ref', 'Product', 'Category', 'Sizes',
            'MOQ', 'Unit', 'Wholesale EUR', 'Retail EUR', 'Retail source',
            'Product link', 'Photo'];

$rows = [];
$n = 0;
foreach ($byBrand as $brand => $list) {
    foreach ($list as $p) {
        $n++;
        $price = (float)($p['list'] ?? $p['price'] ?? 0);
        $rrp   = (float)($p['rrp'] ?? 0);
        $real  = $rrp > 0;

        $id    = (string)($p['id'] ?? '');
        $ident = trim((string)($p['sku'] ?? ''));
        if ($ident === '') $ident = strtoupper(preg_replace('/^[a-z]{2,4}-/', '', $id));

        $rows[] = ['cells' => [
            (string)$n,
            $brand,
            $ident,
            $id,
            (string)($p['name'] ?? ''),
            (string)($p['cat'] ?? ''),
            (string)($p['sizes'] ?? ''),
            (string)($p['moq'] ?? ''),
            (string)($p['unit'] ?? 'pc'),
            /* Plain numbers, no currency symbol: the header carries the unit and a bare
               number is what a buyer can sum, sort and multiply without cleaning first. */
            number_format($price, 2, '.', ''),
            $real ? number_format($rrp, 2, '.', '') : '',
            $real ? 'brand RRP' : '',
            $id !== '' ? 'https://vestrasales.com/product?id='.$id : '',
            '',
        ], 'image' => function_exists('vestra_export_local')
            ? vestra_export_local(vestra_primary_image($p))
            : ''];
    }
}

/* Alt notlar. Genisligi basliktan aliyor: satirlar elle 15 hucreye doldurulmustu ve
   bir sutun kaldirilinca hepsi bir hucre tasiyordu. Not metni 5. sutunda basliyor
   (urun adi sutunu), cunku orasi sayfada en genis olan. */
$note = function (string $text) use ($headers): array {
    $cells = array_fill(0, count($headers), '');
    $cells[4] = $text;
    return ['cells' => $cells, 'image' => ''];
};

$rows[] = ['cells' => array_fill(0, count($headers), ''), 'image' => ''];
$rows[] = $note('Escrow-protected payment up to EUR 3,000 per order: the platform holds the money and '
    .'releases it to the seller only after you confirm the goods arrived as described. Above that, or on request, '
    .'we invoice and release the goods once payment is received.');
$rows[] = $note('Delivery within the EU (Greece included) typically 7-14 working days from release. '
    .'Freight quoted per order. MOQ is per article; no seasonal or collection minimum.');
$rows[] = $note('RETAIL EUR is the brand\'s own recommended price, read from the brand\'s own site. '
    .'Where a brand publishes none for an article the cell is empty: we do not estimate a retail price on a brand\'s behalf.');
/* A spreadsheet goes stale the moment stock moves; the page does not. Anyone working from
   a forwarded copy should be one click from the current list. */
$rows[] = $note('Always-current version of this list: https://vestrasales.com/price-list'
    .($brandFilter !== '' ? '?brand='.rawurlencode($brandFilter) : '').'  \u{00B7}  every brand: https://vestrasales.com/price-lists');

$title = $brandFilter !== '' ? $brandFilter.' wholesale' : 'VESTRA wholesale';
$file  = vestra_xlsx_with_photos_file($headers, $rows, $title);
if ($file === '' || !is_file($file)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('price list temporarily unavailable');
}

$slug = $brandFilter !== '' ? strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $brandFilter)).'-' : '';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="vestra-'.$slug.'wholesale-'.date('Y-m').'.xlsx"');
header('Content-Length: '.filesize($file));
header('X-Content-Type-Options: nosniff');
readfile($file);
@unlink($file);
