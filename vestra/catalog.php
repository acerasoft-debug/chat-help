<?php
/** VESTRA — PUBLIC brand catalogue export (Excel), for cold-outreach / campaign links.
 *
 *  Unlike catalog-csv.php and linesheet.php (members-only, full trade pricing + seller names),
 *  this export is deliberately PUBLIC and carries NO trade pricing and NO seller identity.
 *  It is a teaser line-sheet — brand, product, category, colours, sizes, MOQ and a photo link —
 *  meant to entice a boutique to register for trade access. All confidential data stays gated
 *  behind the members-only exports; nothing here reveals a price or a supplier.
 *
 *    /catalog?brand=lacoste   → Excel (.xls) of that brand's live listings (no pricing)
 *    /catalog                 → the full public selection (all brands)
 *
 *  The DOWNLOAD file name is intentionally neutral ("VESTRA-Selection-<date>.xls") so the raw
 *  brand name never appears in the saved file name; the brand is shown inside the sheet only.
 */
require __DIR__.'/inc/products.php';

$brandQ = trim((string)($_GET['brand'] ?? ''));
$host = (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'vestrasales.com');

$items = [];
foreach (vestra_products() as $p) {
    if ($brandQ !== '' && strcasecmp(trim((string)($p['brand'] ?? '')), $brandQ) !== 0) continue;
    $items[] = $p;
}

// Neutral, brand-free download name (per campaign policy).
$fname = 'VESTRA-Selection-'.date('Y-m-d').'.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('X-Content-Type-Options: nosniff');
echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel decodes accents correctly

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$heading = $brandQ !== '' ? $brandQ : 'Full Selection';
?>
<table border="1" cellspacing="0" cellpadding="4">
  <tr>
    <th colspan="8" style="background:#14110c;color:#d8bd86;font-size:18px;text-align:left">
      VESTRA &mdash; <?= $h($heading) ?>
    </th>
  </tr>
  <tr>
    <td colspan="8" style="text-align:left;color:#3a3428">
      Authentic branded fashion &middot; wholesale &middot; KYC-verified B2B marketplace &middot; vestrasales.com
    </td>
  </tr>
  <tr style="background:#f4ecd8;font-weight:bold">
    <th>#</th><th>Brand</th><th>Product</th><th>Category</th><th>Colours</th><th>Sizes</th><th>MOQ</th><th>Photo</th>
  </tr>
  <?php if (!$items): ?>
  <tr>
    <td colspan="8" style="text-align:left">
      This selection is available on request &mdash; register free at vestrasales.com for the live line-sheet.
    </td>
  </tr>
  <?php else: $i = 0; foreach ($items as $p): $i++;
      $img = vestra_primary_image($p);
      if ($img !== '' && $img[0] === '/') $img = $host.$img;
      $colours = implode(', ', array_filter((array)($p['colors'] ?? [])));
  ?>
  <tr>
    <td><?= $i ?></td>
    <td><?= $h($p['brand'] ?? '') ?></td>
    <td><?= $h($p['name'] ?? '') ?></td>
    <td><?= $h($p['cat'] ?? '') ?></td>
    <td><?= $h($colours) ?></td>
    <td><?= $h($p['sizes'] ?? '') ?></td>
    <td><?= (int)($p['moq'] ?? 0) ?> <?= $h($p['unit'] ?? 'pc') ?></td>
    <td><?= $img !== '' ? '<a href="'.$h($img).'">view</a>' : '' ?></td>
  </tr>
  <?php endforeach; endif; ?>
  <tr style="background:#f4ecd8">
    <td colspan="8" style="text-align:left">
      <b>Trade pricing &amp; full line-sheets:</b> register free at vestrasales.com &mdash;
      every seller KYC-verified, goods authenticity-verified on delivery, escrow-protected invoicing.
    </td>
  </tr>
</table>
