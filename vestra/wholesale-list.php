<?php
/**
 * VESTRA — wholesale price list as a real PDF.
 *
 *   /wholesale-list.pdf                every brand
 *   /wholesale-list.pdf?brand=Balmain  one brand
 *
 * The .pdf address is a rewrite to this file (see .htaccess). The extension is kept on
 * purpose: this link is e-mailed to prospects, and a link ending in .pdf says what it is
 * before anyone clicks it.
 *
 * WHY THIS EXISTS ALONGSIDE /catalog-pdf. That page is a print-to-PDF view behind the
 * approved-member gate, which is right for a signed-in buyer but useless for the thing
 * this file is for: answering a prospect who has no account yet and asked for a price
 * list. A prospect cannot log in, and a document that has to be produced by the
 * recipient's own browser cannot be attached to a reply. So this streams a finished
 * file, generated with the same VestraPdf writer the order documents use.
 *
 * WHAT IT DELIBERATELY OMITS. Seller and supplier names appear nowhere. Trade pricing is
 * the thing being shared; who the goods sit with is not, and a price list that names the
 * source is a sourcing list a competitor can work backwards from. vestra_mask_seller()
 * exists for the same reason elsewhere in the site.
 *
 * RETAIL PRICES ARE A GUIDE, NOT AN RRP. No product record carries a manufacturer's
 * recommended price, so none is claimed. The retail column is wholesale x2.5 and is
 * labelled as a guide in the header and the footer, because for branded goods an RRP is
 * set by the brand and printing an invented one as if it came from them would be false.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/pdf.php';

const WL_RETAIL_MULTIPLE = 2.5;

$brandFilter = trim((string)($_GET['brand'] ?? ''));

/* Group by brand, drop anything without a price to quote. */
$byBrand = [];
foreach (vestra_products() as $p) {
    $brand = trim((string)($p['brand'] ?? ''));
    $price = (float)($p['list'] ?? $p['price'] ?? 0);
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

/* Layout. A4 is 595x842pt; 40pt margins leave 515pt of usable width. Columns are fixed
   rather than measured so a long product name wraps inside its own column instead of
   pushing the price out of alignment down the page. */
$L = 40.0; $R = 555.0;
$COL_CODE = 40.0; $COL_NAME = 118.0; $COL_SIZES = 330.0;
$X_MOQ = 398.0; $X_WHOLE = 480.0; $X_RETAIL = 555.0;
$NAME_W = 205.0; $TOP = 782.0; $BOTTOM = 64.0;

$y = 0.0;

$header = function (bool $first) use ($pdf, &$y, $L, $R, $TOP, $brandFilter, $total) {
    $pdf->text($L, $TOP, 20, 'VESTRA', true);
    $pdf->text($L + 68, $TOP, 9.5, 'Verified B2B fashion wholesale');
    $pdf->textR($R, $TOP, 9, 'vestrasales.com');
    $pdf->line($L, $TOP - 9, $R, $TOP - 9, 1.1, 0.72);
    $y = $TOP - 30;
    if ($first) {
        $pdf->text($L, $y, 15, $brandFilter !== '' ? $brandFilter.' — wholesale price list' : 'Wholesale price list', true);
        $y -= 15;
        $pdf->text($L, $y, 9, $total.' articles  ·  prices in EUR, per piece, excluding VAT and freight  ·  issued '.date('d M Y'));
        $y -= 12;
        $pdf->text($L, $y, 9, 'Retail column is a guide at '.number_format(WL_RETAIL_MULTIPLE, 1).'x wholesale, not a brand-set RRP.');
        $y -= 22;
    }
};

/* Column captions, repeated at the top of every page: a price list is read a page at a
   time and a column of bare numbers is ambiguous without them. */
$colHead = function () use ($pdf, &$y, $L, $R, $COL_CODE, $COL_NAME, $COL_SIZES, $X_MOQ, $X_WHOLE, $X_RETAIL) {
    $pdf->rectFill($L, $y - 4, $R - $L, 15, 0.95);
    $pdf->text($COL_CODE, $y, 7.5, 'CODE', true);
    $pdf->text($COL_NAME, $y, 7.5, 'ARTICLE', true);
    $pdf->text($COL_SIZES, $y, 7.5, 'SIZES', true);
    $pdf->textR($X_MOQ, $y, 7.5, 'MOQ', true);
    $pdf->textR($X_WHOLE, $y, 7.5, 'WHOLESALE', true);
    $pdf->textR($X_RETAIL, $y, 7.5, 'RETAIL (GUIDE)', true);
    $y -= 16;
};

$header(true);
$colHead();

$page = function () use ($pdf, &$y, $header, $colHead) {
    $pdf->addPage();
    $header(false);
    $colHead();
};

foreach ($byBrand as $brand => $rows) {
    if ($y < $BOTTOM + 46) $page();
    $y -= 6;
    $pdf->text($L, $y, 11, mb_strtoupper($brand), true);
    $pdf->textR($R, $y, 8, count($rows).' article'.(count($rows) === 1 ? '' : 's'));
    $y -= 5;
    $pdf->line($L, $y, $R, $y, 0.8, 0.55);
    $y -= 14;

    foreach ($rows as $p) {
        $price = (float)($p['list'] ?? $p['price'] ?? 0);
        /* Tier tables quote the entry price; the deeper rungs are on the product page and
           depend on quantity, so "from" is the honest word for a fixed sheet. */
        $best = $price;
        foreach ((array)($p['tiers'] ?? []) as $t) {
            $tp = (float)($t['price'] ?? 0);
            if ($tp > 0 && $tp < $best) $best = $tp;
        }
        $hasTiers = $best < $price - 0.001;

        $nameLines = $pdf->wrap((string)($p['name'] ?? ''), $NAME_W, 9);
        $rowH = max(22.0, 11.0 * count($nameLines) + 11.0);
        if ($y - $rowH < $BOTTOM) $page();

        $pdf->text($COL_CODE, $y, 8, (string)($p['sku'] ?? $p['id'] ?? ''));
        $ny = $y;
        foreach ($nameLines as $ln) { $pdf->text($COL_NAME, $ny, 9, $ln); $ny -= 10.5; }
        /* The link is printed as text rather than a PDF annotation: this file is read on
           phones and forwarded as an attachment, and a visible address survives both. */
        $pdf->text($COL_NAME, $ny + 0.5, 7, 'vestrasales.com/product?id='.(string)($p['id'] ?? ''));

        $pdf->text($COL_SIZES, $y, 8, (string)($p['sizes'] ?? '—'));
        $pdf->textR($X_MOQ, $y, 8.5, (string)($p['moq'] ?? '—').' '.(string)($p['unit'] ?? 'pc'));
        /* The number is right-aligned on its own, and "from" is drawn separately and small
           to its left. Setting "from EUR 37.90" as one string pushed the price left far
           enough to touch the MOQ column, and it also broke the alignment that makes a
           price column readable -- tiered and untiered rows no longer shared a right edge. */
        $num = 'EUR '.number_format($best, 2);
        $pdf->textR($X_WHOLE, $y, 9.5, $num, true);
        if ($hasTiers) $pdf->textR($X_WHOLE - $pdf->strWidth($num, 9.5, true) - 3, $y, 6.5, 'from');
        $pdf->textR($X_RETAIL, $y, 8.5, 'EUR '.number_format($best * WL_RETAIL_MULTIPLE, 2));

        $y -= $rowH;
        $pdf->line($L, $y + 8, $R, $y + 8, 0.4, 0.86);
    }
    $y -= 6;
}

/* Terms travel with the document. This sheet is sent to prospects who have not seen the
   site yet, and a price with no payment or delivery terms beside it invites the same
   three questions in every reply. */
if ($y < $BOTTOM + 150) $page();
$y -= 10;
$pdf->line($L, $y + 10, $R, $y + 10, 1.0, 0.55);
$pdf->text($L, $y - 4, 11, 'Ordering, payment and delivery', true);
$y -= 22;

$terms = [
  ['Escrow-protected payment (up to EUR 3,000 per order)',
   'The platform holds the payment. You pay into a protected account, the goods ship, and the funds are '
  .'released to the seller only after the parcel has reached you and you have confirmed it is as described. '
  .'If it is not, the money is still recoverable.'],
  ['Payment against invoice',
   'For orders above that threshold, or where you prefer it, we invoice the order and the goods are '
  .'released for dispatch once the payment has been received.'],
  ['Delivery',
   'Within the EU, typically 7 to 14 working days from release of the order. Greece is inside that range. '
  .'Freight is quoted per order and depends on weight and volume.'],
  ['Reorders',
   'Every article above can be reordered while stock lasts. Prices in this list hold for the season unless '
  .'marked otherwise; tiered articles improve with quantity.'],
];
foreach ($terms as [$t, $b]) {
    if ($y < $BOTTOM + 40) { $page(); $y -= 6; }
    $pdf->text($L, $y, 9, $t, true);
    $y -= 11;
    foreach ($pdf->wrap($b, $R - $L, 8.5) as $ln) { $pdf->text($L, $y, 8.5, $ln); $y -= 10; }
    $y -= 6;
}

$pdf->stampEachPage(function (VestraPdf $d, int $n, int $of) use ($L, $R, $BOTTOM) {
    $d->line($L, $BOTTOM - 8, $R, $BOTTOM - 8, 0.5, 0.8);
    $d->text($L, $BOTTOM - 20, 7.5, 'VESTRA · vestrasales.com · support@vestrasales.com');
    $d->textR($R, $BOTTOM - 20, 7.5, 'Page '.$n.' of '.$of);
    $d->text($L, $BOTTOM - 30, 7, 'Prices EUR per piece, excluding VAT and freight. Retail column is a guide, not a brand-set RRP.');
});

$slug = $brandFilter !== '' ? strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $brandFilter)).'-' : '';
$pdfData = $pdf->output();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="vestra-'.$slug.'wholesale-'.date('Y-m').'.pdf"');
header('Content-Length: '.strlen($pdfData));
header('X-Content-Type-Options: nosniff');
echo $pdfData;
