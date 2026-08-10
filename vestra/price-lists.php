<?php
/**
 * VESTRA — one page listing a downloadable price list per brand.
 *
 *   /price-lists
 *
 * WHY A PAGE RATHER THAN A LIST OF LINKS. The per-brand downloads are just
 * /price-list?brand=X, so a colleague could in principle be handed the pattern and
 * left to type brand names. That fails twice: the spelling has to match the catalogue
 * exactly (a typo silently yields an empty list rather than an error), and a hand-written
 * list goes stale the day a brand is added. This page is generated from the live
 * catalogue, so the names are always right and a new brand appears on its own.
 *
 * PUBLIC ON PURPOSE, like the two downloads it points at: it exists to be sent to a
 * retailer who has not registered yet. It carries prices and no supplier names, which is
 * the same line the price list itself draws.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/i18n.php';

$PAGE = t('Wholesale price lists');
$NAV  = '';
$META = t('VESTRA wholesale price lists — browse the full trade catalogue or a single brand on the site, or take it as Excel. Prices per piece, minimum order quantities, sizes and product links.');

/* Count and total per brand from the live catalogue. Articles without a price to quote are
   left out here for the same reason the price list leaves them out: a row with no number
   is not a price list entry. */
$brands = [];
foreach (vestra_products() as $p) {
    $b = trim((string)($p['brand'] ?? ''));
    $price = (float)($p['list'] ?? $p['price'] ?? 0);
    if ($b === '' || $price <= 0) continue;
    if (!isset($brands[$b])) $brands[$b] = ['n' => 0, 'min' => $price, 'cats' => []];
    $brands[$b]['n']++;
    if ($price < $brands[$b]['min']) $brands[$b]['min'] = $price;
    $c = trim((string)($p['cat'] ?? ''));
    if ($c !== '') $brands[$b]['cats'][$c] = true;
}
ksort($brands, SORT_NATURAL | SORT_FLAG_CASE);
$totalArticles = array_sum(array_column($brands, 'n'));

require __DIR__.'/inc/head.php';
?>
<style>
  .pl-wrap{max-width:1000px;margin:0 auto;padding:0 24px 80px}
  .pl-head{padding:56px 0 28px;border-bottom:1px solid var(--line)}
  .pl-head h1{font-size:clamp(30px,5vw,46px);margin:0 0 14px}
  .pl-head p{color:var(--mut);max-width:62ch;margin:0 0 6px;line-height:1.6}
  .pl-all{display:flex;gap:12px;flex-wrap:wrap;margin:26px 0 0}
  .pl-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;border-radius:8px;
    border:1px solid var(--acc);font-weight:600;font-size:14px;transition:.2s}
  .pl-btn.solid{background:var(--acc);color:#1a1408}
  .pl-btn.solid:hover{filter:brightness(1.08)}
  .pl-btn.ghost{color:var(--ink)}
  .pl-btn.ghost:hover{background:rgba(201,168,106,.1)}
  .pl-note{margin-top:16px;font-size:13px;color:var(--mut);max-width:70ch;line-height:1.6}
  table.pl{width:100%;border-collapse:collapse;margin-top:34px;font-size:14.5px}
  table.pl th{text-align:left;font-size:11px;letter-spacing:.14em;text-transform:uppercase;
    color:var(--mut);font-weight:600;padding:0 12px 10px 0;border-bottom:1px solid var(--line)}
  table.pl td{padding:15px 12px 15px 0;border-bottom:1px solid var(--line);vertical-align:middle}
  table.pl tr:hover td{background:rgba(255,255,255,.02)}
  .pl-brand{font-family:'Playfair Display',Georgia,serif;font-size:18px;font-weight:700}
  .pl-cats{color:var(--mut);font-size:12.5px;margin-top:3px;line-height:1.45}
  .pl-n{color:var(--mut);font-variant-numeric:tabular-nums;white-space:nowrap}
  .pl-from{font-variant-numeric:tabular-nums;white-space:nowrap}
  .pl-dl{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
  .pl-dl a{font-size:12px;font-weight:700;letter-spacing:.04em;padding:7px 13px;border-radius:6px;
    border:1px solid var(--line);color:var(--ink);transition:.18s;white-space:nowrap}
  .pl-dl a:hover{border-color:var(--acc);color:var(--acc)}
  @media(max-width:720px){
    table.pl thead{display:none}
    table.pl tr{display:block;border-bottom:1px solid var(--line);padding:14px 0}
    table.pl td{display:block;border:0;padding:2px 0}
    .pl-dl{justify-content:flex-start;margin-top:10px}
  }
</style>

<div class="pl-wrap">
  <div class="pl-head">
    <h1><?= t('Wholesale price lists') ?></h1>
    <p><?= t('Every article we hold, with the trade price per piece, the minimum order quantity, sizes, the manufacturer\'s article number and a link to the live product page. Prices in EUR, excluding VAT and freight.') ?></p>
    <p><?= t('The Excel is the same list in a form you can sort, filter and paste into your own buying sheet.') ?></p>
    <div class="pl-all">
      <a class="pl-btn solid" href="/price-list"><?= t('Browse the full list') ?></a>
      <a class="pl-btn ghost" href="/wholesale-list.xlsx"><?= t('All brands — Excel') ?></a>
    </div>
    <p class="pl-note"><?= sprintf(t('%1$d articles across %2$d brands. Payment is escrow-protected up to EUR 3,000 per order — the platform holds the money and releases it to the seller only after you confirm the goods arrived as described — or against invoice above that. Delivery within the EU, Greece included, is typically 7 to 14 working days.'), $totalArticles, count($brands)) ?></p>
  </div>

  <table class="pl">
    <thead>
      <tr>
        <th><?= t('Brand') ?></th>
        <th><?= t('Articles') ?></th>
        <th><?= t('From') ?></th>
        <th style="text-align:right"><?= t('Download') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($brands as $b => $info):
        $q = rawurlencode($b);
        $cats = array_keys($info['cats']);
        sort($cats, SORT_NATURAL | SORT_FLAG_CASE);
      ?>
      <tr>
        <td>
          <div class="pl-brand"><?= htmlspecialchars($b) ?></div>
          <?php if ($cats): ?><div class="pl-cats"><?= htmlspecialchars(implode(' · ', array_slice($cats, 0, 6))) ?><?= count($cats) > 6 ? ' · …' : '' ?></div><?php endif; ?>
        </td>
        <td class="pl-n"><?= (int)$info['n'] ?></td>
        <td class="pl-from">€<?= number_format($info['min'], 2) ?></td>
        <td>
          <div class="pl-dl">
            <a href="/price-list?brand=<?= $q ?>"><?= t('VIEW') ?> →</a>
            <a href="/wholesale-list.xlsx?brand=<?= $q ?>">EXCEL ↓</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p class="pl-note" style="margin-top:26px">
    <?= sprintf(t('The retail column in both files is marked on every row: "RRP" where the brand\'s own recommended price is on file, and "guide x%1$d" where it is our estimate at %1$d times wholesale. The two are never merged, because a recommended price for branded goods belongs to the brand.'), VESTRA_RETAIL_MULTIPLE) ?>
  </p>
</div>

<?php require __DIR__.'/inc/foot.php'; ?>
