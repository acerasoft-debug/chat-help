<?php
/**
 * VESTRA — members-only line-sheet downloads for flagged listings.
 *   /linesheet?id=<product>&fmt=pdf  → streams the supplier PDF from sheets/ (never web-exposed directly)
 *   /linesheet?id=<product>&fmt=xls  → generates an Excel presentation (one row per colourway)
 * Only listings carrying 'linesheet' => true offer these downloads.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$p = vestra_find($_GET['id'] ?? '');
/* 'linesheet' flags the generated pdf/xls pair; 'sheet' is a file the seller uploaded
   themselves. Both are trade documents and both go through the gate below — the
   uploaded one used to be linked straight out of /uploads, which meant a seller's
   price list was one click away for anyone who opened the product page. */
if (!$p || (empty($p['linesheet']) && empty($p['sheet']))) { http_response_code(404); exit('Not found'); }

/* Approved members only — the sheets contain product photography and trade terms,
   both of which are freischaltung-gated (login alone is not enough). */
$AUTH_USER = auth_user();
if (empty($_SESSION['vadmin']) && !auth_user_approved($AUTH_USER)) {
    header('Location: '.(!$AUTH_USER
        ? '/login?back='.urlencode('/product?id='.$p['id'])
        : ((($AUTH_USER['type'] ?? '') === 'seller') ? '/seller?tab=kyc' : '/buyer?tab=kyc')));
    exit;
}

$fmt = $_GET['fmt'] ?? 'pdf';
if (!in_array($fmt, ['pdf', 'xls', 'file'], true)) $fmt = 'pdf';
$slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $p['brand'].' '.$p['name']));

/* The seller's own upload, streamed from disk. basename() is what keeps the stored
   path from reaching outside uploads/ — the record is written by us, but it is read
   back from a JSON file, so it is treated as input rather than trusted. */
if ($fmt === 'file') {
    $stored = basename((string)($p['sheet'] ?? ''));
    $file   = __DIR__.'/uploads/'.$stored;
    if ($stored === '' || !is_file($file)) { http_response_code(404); exit('Sheet not available'); }
    $ext  = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
    $mime = ['xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
             'xls'  => 'application/vnd.ms-excel',
             'csv'  => 'text/csv'][$ext] ?? 'application/octet-stream';
    header('Content-Type: '.$mime);
    header('Content-Disposition: attachment; filename="'.$slug.'-linesheet.'.$ext.'"');
    header('Content-Length: '.filesize($file));
    header('X-Content-Type-Options: nosniff');
    readfile($file); exit;
}

/* pdf and xls stay reserved for listings flagged 'linesheet'. Widening the guard above
   to admit uploaded sheets must not also hand those listings the generated pair. */
if (empty($p['linesheet'])) { http_response_code(404); exit('Not found'); }

if ($fmt === 'pdf') {
    $file = __DIR__.'/sheets/'.basename((string)($p['sheet_file'] ?? ''));
    if (empty($p['sheet_file']) || !is_file($file)) { http_response_code(404); exit('Sheet not available'); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'.$slug.'-linesheet.pdf"');
    header('Content-Length: '.filesize($file));
    header('X-Content-Type-Options: nosniff');
    readfile($file); exit;
}

/* ── Excel (.xls via HTML table — opens natively in Excel/LibreOffice, no libraries needed) ── */
$host = (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'vestrasales.com');
$rows = [];
if (!empty($p['variants']) && is_array($p['variants'])) {
    foreach ($p['variants'] as $v) {
        $rows[] = [
            'art'   => $v['art'] ?: ($v['model'] ?? ''),
            'model' => $v['model'] ?? '',
            'color' => $v['color'] ?? '',
            'image' => !empty($v['image']) ? $host.$v['image'] : '',
        ];
    }
} else {
    $rows[] = ['art'=>$p['sku'], 'model'=>'', 'color'=>implode(', ', (array)($p['colors'] ?? [])), 'image'=>vestra_primary_image($p) ? $host.vestra_primary_image($p) : ''];
}
$unit  = $p['unit'] ?? 'pc';
$price = ($p['mode'] ?? '') === 'offer' ? 'On request' : eur(vestra_from_price($p)).' / '.$unit;
$pack  = (int)($p['size_step'] ?? 0) ?: 1;

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$slug.'-linesheet.xls"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel decodes accents correctly
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<table border="1">
  <tr><th colspan="9" style="background:#0e0e11;color:#fff;font-size:16px;text-align:left"><?= $h($p['brand'].' — '.$p['name']) ?></th></tr>
  <tr>
    <td colspan="9" style="text-align:left">
      SKU <?= $h($p['sku']) ?> · <?= $h($p['cat']) ?> · MOQ <?= (int)$p['moq'] ?> <?= $h($unit) ?> · Pack <?= $pack ?> <?= $h($unit) ?> · <?= $h(vestra_sizes_label((string)($p['sizes'] ?? ''))) ?> · <?= $h(strip_tags((string)($p['origin'] ?? ''))) ?>
    </td>
  </tr>
  <tr style="background:#f3c614;font-weight:bold">
    <th>#</th><th>Article No</th><th>Model No</th><th>Product</th><th>Colour</th>
    <th>MOQ</th><th>Pack</th><th>Unit price</th><th>Photo</th>
  </tr>
  <?php foreach ($rows as $i => $r): ?>
  <tr>
    <td><?= $i + 1 ?></td>
    <td><?= $h($r['art']) ?></td>
    <td><?= $h($r['model']) ?></td>
    <td><?= $h($p['brand'].' '.$p['name']) ?></td>
    <td><?= $h($r['color']) ?></td>
    <td><?= (int)$p['moq'] ?> <?= $h($unit) ?></td>
    <td><?= $pack ?> <?= $h($unit) ?></td>
    <td><?= $h($price) ?></td>
    <td><?= $r['image'] ? '<a href="'.$h($r['image']).'">'.$h($r['image']).'</a>' : '' ?></td>
  </tr>
  <?php endforeach; ?>
  <tr><td colspan="9" style="text-align:left">Payment by invoice (bank transfer). Prices excl. taxes &amp; shipping. VESTRA · vestrasales.com</td></tr>
</table>
