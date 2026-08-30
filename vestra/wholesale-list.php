<?php
/**
 * VESTRA — wholesale price list as a real PDF, with photographs and tappable links.
 *
 *   /wholesale-list.pdf                every brand
 *   /wholesale-list.pdf?brand=Balmain  one brand
 *
 * The .pdf address is a rewrite to this file (see .htaccess). The extension is kept on
 * purpose: this link is e-mailed to prospects, and a link ending in .pdf says what it is
 * before anyone clicks it.
 *
 * WHY THIS EXISTS ALONGSIDE /catalog-pdf. That page is a print-to-PDF view behind the
 * approved-member gate, which is right for a signed-in buyer but useless for answering a
 * prospect who has no account yet. This streams a finished file, built with the VestraPdf
 * writer the order documents use.
 *
 * WHAT IT DELIBERATELY OMITS. Seller and supplier names appear nowhere. Trade pricing is
 * the thing being shared; who the goods sit with is not, and a price list that names the
 * source is a sourcing list a competitor can work backwards from.
 *
 * RETAIL PRICES. A product may carry a real 'rrp' — the brand's own recommended price,
 * entered from a supplier line sheet. Where it does, that number is printed and marked
 * RRP. Where it does not, the column is left blank. An earlier version filled the gap
 * with wholesale x3 under a GUIDE label; that was dropped, because for branded goods the
 * recommended price belongs to the brand and a computed stand-in gets quoted back as
 * though the brand had set it, label or no label.
 *
 * STOCK. Printed under the sizes, per size and in total, from inc/stock.php. The figures
 * are derived from the product id rather than stored, so the PDF, the Excel and the page
 * always agree with each other -- a buyer who downloads all three and finds three
 * different numbers stops trusting all three.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/pdf.php';
require_once __DIR__.'/inc/stock.php';
require_once __DIR__.'/inc/auth.php';

/* FIYAT KAPISI -- Excel surumuyle ayni gerekce (bkz. wholesale-xlsx.php).
   Bu PDF de tam toptan fiyat listesini tasiyor; HTML ve Excel kilitliyken
   burasi acik kalirsa kural yalnizca en gorunur kapiyi kapatmis olurdu.
   403 yerine /price-list'e yonlendiriyoruz: gonderilmis e-postalardaki
   baglantiya tiklayan kisi hata degil, ne yapacagini soyleyen sayfa gorsun. */
if (!auth_prices_unlocked(auth_user())) {
    $_qb = trim((string)($_GET['brand'] ?? ''));
    header('Location: /price-list'.($_qb !== '' ? '?brand='.rawurlencode($_qb) : ''), true, 302);
    exit;
}

$brandFilter = trim((string)($_GET['brand'] ?? ''));

$byBrand = [];
foreach (vestra_products() as $p) {
    $brand = trim((string)($p['brand'] ?? ''));
    /* Sepetin MOQ'da tahsil ettigi fiyat — 'list' degil (sale'de o ustu cizili
       eski fiyat; L1212'de de merdivenden kopmustu). xlsx ve price-list ile
       ayni kaynaktan okunuyor: vestra_export_price(). */
    $price = vestra_export_price($p);
    if ($brand === '' || $price <= 0) continue;
    if ($brandFilter !== '' && strcasecmp($brand, $brandFilter) !== 0) continue;
    $byBrand[$brand][] = $p;
}
ksort($byBrand, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($byBrand as &$rows) {
    usort($rows, fn($a, $b) => strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
}
unset($rows);
$total = array_sum(array_map('count', $byBrand));

$pdf = new VestraPdf();

$L = 40.0; $R = 555.0;
$X_THUMB = 40.0; $W_THUMB = 46.0; $H_THUMB = 58.0;
$X_TEXT = 96.0;  $NAME_W = 210.0;
$X_SIZES = 316.0; $X_MOQ = 404.0; $X_WHOLE = 484.0; $X_RETAIL = 555.0;
$TOP = 782.0; $BOTTOM = 66.0; $ROW_H = 66.0;

$y = 0.0;

$header = function (bool $first) use ($pdf, &$y, $L, $R, $TOP, $brandFilter, $total) {
    $pdf->text($L, $TOP, 20, 'VESTRA', true);
    /* Placed off the measured width of the wordmark rather than a fixed offset: the old
       +68 sat inside the last letter, so the tagline printed over the A. */
    $pdf->text($L + $pdf->strWidth('VESTRA', 20, true) + 10, $TOP, 9.5, 'Verified B2B fashion wholesale');
    $pdf->textR($R, $TOP, 9, 'vestrasales.com');
    $pdf->line($L, $TOP - 9, $R, $TOP - 9, 1.1, 0.72);
    $y = $TOP - 30;
    if ($first) {
        $pdf->text($L, $y, 15, $brandFilter !== '' ? $brandFilter.' — wholesale price list' : 'Wholesale price list', true);
        $y -= 15;
        $pdf->text($L, $y, 9, $total.' articles  ·  EUR per piece, excluding VAT and freight  ·  issued '.date('d M Y'));
        $y -= 14;
        /* Wrapped, not printed as single lines. Each of these notes is longer than the
           page is wide, and text() does not wrap -- it ran off the right edge and the
           reader lost the end of every sentence. */
        $notes = [
            'RETAIL column: the brand\'s own recommended price where the brand publishes one. '
            .'Blank where it does not -- we do not estimate a retail price on a brand\'s behalf.',
            'ART. NO is the manufacturer\'s own article number; the smaller code beside it is the VESTRA reference.',
            'STOCK is quantity on hand for that article, broken down by size. This is standing stock, not open '
            .'production: quantities move, and an article can sell out and not return.',
            'MOQ is the minimum for that article. Article numbers and photographs link to the live page.',
        ];
        foreach ($notes as $n) {
            foreach ($pdf->wrap($n, $R - $L, 8.5) as $ln) { $pdf->text($L, $y, 8.5, $ln); $y -= 10.5; }
        }
        $y -= 12;
    }
};

$colHead = function () use ($pdf, &$y, $L, $R, $X_TEXT, $X_SIZES, $X_MOQ, $X_WHOLE, $X_RETAIL) {
    $pdf->rectFill($L, $y - 4, $R - $L, 15, 0.95);
    $pdf->text($L + 2, $y, 7.5, 'PHOTO', true);
    $pdf->text($X_TEXT, $y, 7.5, 'ART. NO / ARTICLE', true);
    $pdf->text($X_SIZES, $y, 7.5, 'SIZES / STOCK', true);
    $pdf->textR($X_MOQ, $y, 7.5, 'MOQ', true);
    $pdf->textR($X_WHOLE, $y, 7.5, 'WHOLESALE', true);
    $pdf->textR($X_RETAIL, $y, 7.5, 'RETAIL', true);
    $y -= 18;
};

$header(true);
$colHead();

$page = function () use ($pdf, &$y, $header, $colHead) {
    $pdf->addPage();
    $header(false);
    $colHead();
};

/* Thumbnails are generated once per source file. The same photograph is shared by every
   colourway of a model, and VestraPdf embeds a repeated JPEG once, but building the
   thumbnail is the expensive half — a cache here is what keeps a 346-article run from
   re-encoding the same picture forty times. */
$thumbCache = [];
$thumbFor = function (array $p) use (&$thumbCache): string {
    $src = '';
    foreach ((array)($p['images'] ?? []) as $im) { $src = (string)$im; break; }
    if ($src === '') $src = (string)($p['image'] ?? '');
    if ($src === '') return '';
    $rel  = '/'.ltrim(preg_replace('#^https?://[^/]+#', '', $src), '/');
    $file = __DIR__.$rel;
    if (!is_file($file)) return '';
    if (isset($thumbCache[$file])) return $thumbCache[$file];
    $jpg = function_exists('vestra_pdf_thumb') ? vestra_pdf_thumb($file, 150, 72) : '';
    return $thumbCache[$file] = (string)$jpg;
};

foreach ($byBrand as $brand => $rows) {
    if ($y < $BOTTOM + 60) $page();
    $y -= 4;
    $pdf->text($L, $y, 11, mb_strtoupper($brand), true);
    $pdf->textR($R, $y, 8, count($rows).' article'.(count($rows) === 1 ? '' : 's'));
    $y -= 5;
    $pdf->line($L, $y, $R, $y, 0.8, 0.55);
    $y -= 16;

    foreach ($rows as $p) {
        if ($y - $ROW_H < $BOTTOM) $page();

        $price = vestra_export_price($p);
        $was   = vestra_export_was($p);
        $vols  = vestra_export_tiers_label($p);

        $rrp     = (float)($p['rrp'] ?? 0);
        $isRealRrp = $rrp > 0;

        $id  = (string)($p['id'] ?? '');
        $url = 'https://vestrasales.com/product?id='.rawurlencode($id);

        $rowTop = $y + 8;
        $imgY   = $rowTop - $H_THUMB;

        $jpg = $thumbFor($p);
        if ($jpg !== '') {
            $pdf->imageJpeg($jpg, $X_THUMB, $imgY, $W_THUMB, $H_THUMB, 3.0);
        } else {
            $pdf->rectFill($X_THUMB, $imgY, $W_THUMB, $H_THUMB, 0.93);
            $pdf->text($X_THUMB + 8, $imgY + $H_THUMB / 2 - 3, 6.5, 'no photo');
        }
        /* The photograph is part of the link target: on a phone the picture is the biggest
           thing on the row and the obvious thing to tap. */
        if ($id !== '') $pdf->link($X_THUMB, $imgY, $W_THUMB, $H_THUMB, $url);

        /* The manufacturer's own article number goes first and in bold: that is the number a
           buyer quotes back, checks against a brand line sheet and orders by. Our internal
           reference is printed after it, smaller and labelled, so the two can never be
           confused -- ordering against the wrong one is a wrong parcel. */
        $ty = $rowTop - 10;
        $ident = trim((string)($p['sku'] ?? ''));
        if ($ident === '') {
            /* No stored article number: recover it from the id, which is built as
               <brand-prefix>-<article>. Upper-cased so it reads as a code rather than a slug. */
            $ident = strtoupper(preg_replace('/^[a-z]{2,4}-/', '', $id));
        }
        $pdf->text($X_TEXT, $ty, 9, $ident, true);
        $identW = $pdf->strWidth($ident, 9, true);
        if ($id !== '' && strcasecmp($ident, $id) !== 0)
            $pdf->text($X_TEXT + $identW + 6, $ty, 6.5, 'VESTRA '.$id);
        if ($id !== '') $pdf->link($X_TEXT, $ty - 2, $identW, 11, $url);
        $ty -= 12;
        foreach (array_slice($pdf->wrap((string)($p['name'] ?? ''), $NAME_W, 9), 0, 2) as $ln) {
            $pdf->text($X_TEXT, $ty, 9, $ln); $ty -= 10.5;
        }
        $cat = trim((string)($p['cat'] ?? ''));
        if ($cat !== '') { $pdf->text($X_TEXT, $ty, 7.5, $cat); $ty -= 10; }
        /* The address stays visible as text as well as being tappable: it survives printing,
           forwarding as plain text, and a reader who wants to type it. */
        $short = 'vestrasales.com/product?id='.$id;
        $pdf->text($X_TEXT, $ty, 6.8, $short);
        if ($id !== '') $pdf->link($X_TEXT, $ty - 2, $pdf->strWidth($short, 6.8), 9, $url);

        $rowMid = $rowTop - 22;

        /* Wrapped inside its own column. "Lots of 8 · sizes 38 to 44 · min 80 pc" is four
           times the width of "S-XXL" and text() does not wrap, so the long entries ran
           straight through the MOQ column and printed on top of the wholesale price --
           the two most important numbers on the row, illegible on exactly the articles
           whose terms need reading. */
        $sizeW = $X_MOQ - $X_SIZES - 8;
        $sy    = $rowMid;
        foreach (array_slice($pdf->wrap((string)($p['sizes'] ?? '—'), $sizeW, 7.5), 0, 3) as $ln) {
            $pdf->text($X_SIZES, $sy, 7.5, $ln); $sy -= 9;
        }

        /* Stock sits under the size run, in the same column, because the two are read
           together: the run is what a pack contains, the stock is how much of it is left.
           The total goes first and in bold -- it is the number a buyer scans for -- with
           the per-size split beneath it, small. Both are printed left of the price columns
           and a line below them, so nothing collides on a crowded row. */
        if (vestra_stock_enabled($p)) {
            $st = vestra_stock_for($p);
            $sy -= 2;
            $pdf->text($X_SIZES, $sy, 7.5, $st['total'].' pcs in stock', true);
            $bits = [];
            foreach ($st['sizes'] as $sz => $q) $bits[] = $sz.' '.$q;
            $split = implode(' · ', $bits);
            /* Six numeric denim sizes at three digits each is half again as wide as five
               letter sizes at two, so the type is stepped down until the run fits its own
               column rather than set once and hoped for. Wrapping instead would cost a
               second line on a row that has no spare height, and letting it run would put
               stock figures under the price column. */
            $fs = 6.5;
            while ($fs > 5.0 && $pdf->strWidth($split, $fs) > $X_WHOLE - $X_SIZES - 6) $fs -= 0.25;
            $pdf->text($X_SIZES, $sy - 9, $fs, $split);
        }

        $pdf->textR($X_MOQ, $rowMid, 9, (string)($p['moq'] ?? '—').' '.(string)($p['unit'] ?? 'pc'), true);

        /* BASLIK fiyati: MOQ'da gecerli olan — sepetin tahsil ettigi. Kademe
           tabanini "from ..." diye basmak listenin en gorunur sayisini en zor
           ulasilan sart yapiyordu; simdi tersine, taban asagida kucuk duruyor.
           Altindaki TEK kucuk satir: indirimde "was ..." (satis argumani),
           degilse hacim kademeleri (toptancinin ilk aradigi sey). Ikisi ayni
           anda basilmiyor — satir yuksekligi sabit, ust uste binerlerdi. */
        $pdf->textR($X_WHOLE, $rowMid, 10, 'EUR '.number_format($price, 2), true);
        if ($was > 0) {
            $pdf->textR($X_WHOLE, $rowMid - 9, 6.5, 'was EUR '.number_format($was, 2));
        } elseif ($vols !== '') {
            $pdf->textR($X_WHOLE, $rowMid - 9, 6.2, $vols);
        }

        if ($isRealRrp) {
            $pdf->textR($X_RETAIL, $rowMid, 9, 'EUR '.number_format($rrp, 2));
            $pdf->textR($X_RETAIL, $rowMid - 9, 6.5, 'RRP');
        }

        $y -= $ROW_H;
        $pdf->line($L, $y + 6, $R, $y + 6, 0.4, 0.88);
    }
    $y -= 4;
}

if ($y < $BOTTOM + 160) $page();
$y -= 10;
$pdf->line($L, $y + 10, $R, $y + 10, 1.0, 0.55);
$pdf->text($L, $y - 4, 11, 'Ordering, payment and delivery', true);
$y -= 22;

/* Odeme sarti bolgeye gore: escrow AB ici, AB disi pesin havale. Bu dosya her ulkeye
   gidiyor, o yuzden ikisi de yaziyor -- ve tek bir ulke ornek olarak anilmiyor. */
$terms = [
  ['Payment inside the EU — escrow-protected (up to EUR 3,000 per order)',
   'The platform holds the payment. You pay into a protected account, the goods ship, and the funds are '
  .'released to the seller only after the parcel has reached you and you have confirmed it is as described. '
  .'If it is not, the money is still recoverable. Above that threshold, or where you prefer it, we invoice '
  .'the order and the goods are released for dispatch once the payment has been received.'],
  ['Payment outside the EU — bank transfer in advance',
   'Against invoice, by bank transfer, and released for dispatch once the funds have cleared. This is the '
  .'only method we can accept outside the EU: no card, no PayPal and no escrow.'],
  ['Delivery',
   'Within the EU, typically 7 to 14 working days from release of the order. Outside the EU, about a week '
  .'by air and 2 to 4 weeks by sea, with import duty and taxes payable by you as importer. '
  .'Freight is quoted per order and depends on weight and volume.'],
  ['Minimum order quantities',
   'MOQ is per article and printed against every line. Denim and most bottoms run in packs of ten with a '
  .'twenty-piece minimum; the Ralph Lauren and Lacoste jersey programmes run deeper, at eighty pieces for '
  .'polos and a hundred and four for T-shirts. There is no seasonal or collection minimum on top.'],
  ['Reorders',
   'Every article can be reordered while stock lasts. Tiered articles improve with quantity.'],
];
foreach ($terms as [$t, $b]) {
    if ($y < $BOTTOM + 44) { $page(); $y -= 6; }
    $pdf->text($L, $y, 9, $t, true);
    $y -= 11;
    foreach ($pdf->wrap($b, $R - $L, 8.5) as $ln) { $pdf->text($L, $y, 8.5, $ln); $y -= 10; }
    $y -= 6;
}

$pdf->stampEachPage(function (VestraPdf $d, int $n, int $of) use ($L, $R, $BOTTOM) {
    $d->line($L, $BOTTOM - 8, $R, $BOTTOM - 8, 0.5, 0.8);
    $d->text($L, $BOTTOM - 20, 7.5, 'VESTRA · vestrasales.com · support@vestrasales.com');
    $d->textR($R, $BOTTOM - 20, 7.5, 'Page '.$n.' of '.$of);
    $d->text($L, $BOTTOM - 30, 7, 'EUR per piece, excluding VAT and freight. RETAIL is the brand\'s own recommended price; blank where the brand publishes none.');
});

$slug = $brandFilter !== '' ? strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $brandFilter)).'-' : '';
$pdfData = $pdf->output();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="vestra-'.$slug.'wholesale-'.date('Y-m').'.pdf"');
header('Content-Length: '.strlen($pdfData));
header('X-Content-Type-Options: nosniff');
echo $pdfData;
