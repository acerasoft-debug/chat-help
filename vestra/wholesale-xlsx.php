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
 *   - RETAIL is the brand's own 'rrp' where one is stored, and empty where none is. The
 *     RETAIL SOURCE column marks the ones that are real, so after a sort or a paste into
 *     another sheet a figure cannot lose the fact that the brand set it.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/xlsx.php';
require_once __DIR__.'/inc/stock.php';


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
            'Stock total', 'Stock by size', 'Product link', 'Photo'];

$rows = [];
$n = 0;
foreach ($byBrand as $brand => $list) {
    foreach ($list as $p) {
        $n++;
        $price = (float)($p['list'] ?? $p['price'] ?? 0);
        $rrp   = (float)($p['rrp'] ?? 0);
        $real  = $rrp > 0;

        $hasStock = vestra_stock_enabled($p);
        $stock    = $hasStock ? vestra_stock_for($p) : ['sizes' => [], 'total' => 0];

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
            /* Plain integer, so a buyer can sum and sort it. The by-size string sits in
               its own column rather than inside the size run: the run is what we sell in,
               the stock is what is left, and merging them makes both unreadable. */
            $hasStock ? (string)$stock['total'] : '',
            $hasStock ? implode(' · ', array_map(fn($k, $v) => $k.' '.$v,
                        array_keys($stock['sizes']), $stock['sizes'])) : '',
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
/* Odeme sarti ULKEYE gore degisiyor ve bu dosya her ulkeye gidiyor: escrow AB ici,
   AB disi pesin havale. Onceki not tek bir sart yaziyor ve "Yunanistan dahil" diyordu --
   Petros'a yazilmisti, oysa ayni dosya Japonya'ya da gitti. Sart kisa ve ikisi birden. */
$rows[] = $note('Payment — inside the EU: escrow-protected up to EUR 3,000 per order (the platform holds the '
    .'money and releases it to the seller only after you confirm the goods arrived as described); above that, or '
    .'on request, against invoice.');
$rows[] = $note('Payment — outside the EU: bank transfer in advance, against invoice. Goods are released for '
    .'dispatch once the funds have cleared.');
$rows[] = $note('Delivery within the EU typically 7-14 working days from release; outside the EU about a week by '
    .'air and 2-4 weeks by sea. Freight quoted per order. MOQ is per article; no seasonal or collection minimum. '
    .'Import duty and taxes are payable by the buyer as importer.');
$rows[] = $note('RETAIL EUR is the brand\'s own recommended price, read from the brand\'s own site. '
    .'Where a brand publishes none for an article the cell is empty: we do not estimate a retail price on a brand\'s behalf.');
/* A spreadsheet goes stale the moment stock moves; the page does not. Anyone working from
   a forwarded copy should be one click from the current list. */
$rows[] = $note('Always-current version of this list: https://vestrasales.com/price-list'
    .($brandFilter !== '' ? '?brand='.rawurlencode($brandFilter) : '').'  ·  every brand: https://vestrasales.com/price-lists');

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
