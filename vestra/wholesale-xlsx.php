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
require_once __DIR__.'/inc/auth.php';

/* FIYAT KAPISI. Bu dosya /price-list ile AYNI rakamlari tasiyor, sadece Excel
   olarak. HTML sayfasi kilitliyken bu ucun acik kalmasi kurali anlamsiz kilardi:
   tek bir adres, tum toptan fiyat listesini kayitsiz indirtiyordu.

   403 yerine /price-list'e YONLENDIRIYORUZ. Bu baglanti zaten gonderilmis
   kampanya e-postalarinin icinde duruyor; tiklayan kisi hata sayfasi degil,
   ne yapmasi gerektigini soyleyen bir sayfa gormeli -- orada "belgenizi
   yukleyin, fiyatlar acilir" bandi ve kayit dugmesi var. Marka suzgeci de
   korunuyor ki adam aradigi markanin sayfasina dussun. */
if (!auth_prices_unlocked(auth_user())) {
    $_q = ($brandFilterRaw = trim((string)($_GET['brand'] ?? ''))) !== ''
        ? '?brand='.rawurlencode($brandFilterRaw) : '';
    header('Location: /price-list'.$_q, true, 302);
    exit;
}


$brandFilter = trim((string)($_GET['brand'] ?? ''));

$byBrand = [];
foreach (vestra_products() as $p) {
    $brand = trim((string)($p['brand'] ?? ''));
    /* Sepetin MOQ'da tahsil ettigi fiyat — 'list' degil. mode=sale'de 'list'
       ustu cizili eski fiyattir ve liste 33 urunu %28-42 pahali gosteriyordu;
       L1212'de ise tersi, listede 29,90 gorunen urun sepette 34,00 cikiyordu. */
    $price = vestra_export_price($p);
    if ($brand === '' || $price <= 0) continue;
    if ($brandFilter !== '' && strcasecmp($brand, $brandFilter) !== 0) continue;
    $byBrand[$brand][] = $p;
}
ksort($byBrand, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($byBrand as &$rs) {
    usort($rs, fn($a, $b) => strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
}
unset($rs);

/* NO PHOTO COLUMN. The pictures were embedded in the sheet and a number of them showed
   as empty boxes in the customer's Excel -- the file carried an image for every row, but
   not every image rendered: where GD cannot re-encode a source file (a CMYK or progressive
   JPEG) the original is embedded as-is, and Excel will not draw those. A price list with
   holes in it looks like a catalogue with holes in it, which is worse than one that never
   promised pictures. Every row still carries its product link, and the photographs are on
   the page it opens -- always current, and 5.6 MB lighter as an attachment.
   The PDF (wholesale-list.pdf) keeps its photographs: its own encoder handles CMYK. */
/* "Was EUR" ve "Volume prices" yeni: indirimli urunde eski fiyat kendi
   sutununda (bir siralamadan sonra bile indirim oldugu kaybolmasin), hacim
   kademeleri de ilk kez listede — toptancinin ilk aradigi sey o. */
$headers = ['#', 'Brand', 'Art. No', 'VESTRA Ref', 'Product', 'Category', 'Sizes',
            'MOQ', 'Unit', 'Wholesale EUR', 'Was EUR', 'Volume prices',
            'Retail EUR', 'Retail source',
            'Stock total', 'Stock by size', 'Product link'];

$rows = [];
$n = 0;
foreach ($byBrand as $brand => $list) {
    foreach ($list as $p) {
        $n++;
        $price = vestra_export_price($p);
        $was   = vestra_export_was($p);
        $volsL = vestra_export_tiers_label($p);
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
            $was > 0 ? number_format($was, 2, '.', '') : '',
            $volsL,
            $real ? number_format($rrp, 2, '.', '') : '',
            $real ? 'brand RRP' : '',
            /* Plain integer, so a buyer can sum and sort it. The by-size string sits in
               its own column rather than inside the size run: the run is what we sell in,
               the stock is what is left, and merging them makes both unreadable. */
            $hasStock ? (string)$stock['total'] : '',
            $hasStock ? implode(' · ', array_map(fn($k, $v) => $k.' '.$v,
                        array_keys($stock['sizes']), $stock['sizes'])) : '',
            $id !== '' ? 'https://vestrasales.com/product?id='.$id : '',
        ], 'image' => ''];
    }
}

/* Alt notlar. Genisligi basliktan aliyor: satirlar elle 15 hucreye doldurulmustu ve
   bir sutun kaldirilinca hepsi bir hucre tasiyordu. Not metni 5. sutunda basliyor
   (urun adi sutunu), cunku orasi sayfada en genis olan. */
$note = function (string $text) use ($headers): array {
    $cells = array_fill(0, count($headers), '');
    /* Metin artik ILK hucrede: uretici not satirini tam genislige birlestirip
       italik/soluk basiyor (style=note) -- 5. sutunda baslayan metin, birlesik
       hucrede gorunmez kalirdi. */
    $cells[0] = $text;
    return ['cells' => $cells, 'image' => '', 'style' => 'note'];
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
$rows[] = $note('Photographs: the PRODUCT LINK column opens the article\'s page, where every photograph of it is '
    .'shown at full size. A printable list with photographs in it: https://vestrasales.com/wholesale-list.pdf'
    .($brandFilter !== '' ? '?brand='.rawurlencode($brandFilter) : ''));
/* A spreadsheet goes stale the moment stock moves; the page does not. Anyone working from
   a forwarded copy should be one click from the current list. */
$rows[] = $note('Always-current version of this list: https://vestrasales.com/price-list'
    .($brandFilter !== '' ? '?brand='.rawurlencode($brandFilter) : '').'  ·  every brand: https://vestrasales.com/price-lists');

$title = $brandFilter !== '' ? $brandFilter.' wholesale' : 'VESTRA wholesale';
/* Gorunum: marka bandi + donmus baslik + filtre oklari + zebra + gercek sayi
   hucreleri. Fiyatlar sayi OLDUGU icin musteri artik temizlemeden toplayip
   siralayabiliyor; urun linki gercek kopru. Sutun genislikleri iceriğe gore. */
$file  = vestra_xlsx_with_photos_file($headers, $rows, $title, [
    'band'    => 'VESTRA — Wholesale Price List'
               . ($brandFilter !== '' ? ' · '.$brandFilter : '')
               . ' · '.date('F Y'),
    'freeze'  => true,
    'filter'  => true,
    'zebra'   => true,
    'numcols' => [7 => 'int', 9 => 'num', 10 => 'num', 12 => 'num', 14 => 'int'],
    'linkcols'=> [16],
    'widths'  => [0 => 5, 1 => 15, 2 => 18, 3 => 15, 4 => 38, 5 => 15, 6 => 30,
                  7 => 7, 8 => 6, 9 => 14, 10 => 10, 11 => 22, 12 => 12, 13 => 12,
                  14 => 10, 15 => 24, 16 => 44],
]);
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
