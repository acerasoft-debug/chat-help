<?php
/**
 * Artikel: Katalog mit Marke/Kategorie/Suche, Bestand, Preis, UVP, Aktion,
 * Aufrufe (30 Tage) und Verkäufe; Tab „Ausgeblendet" aus der Panel-Korrektur.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$q     = trim((string)($_GET['q'] ?? ''));
$brand = trim((string)($_GET['brand'] ?? ''));
$cat   = trim((string)($_GET['cat'] ?? ''));
$f     = (string)($_GET['f'] ?? 'all');
$sort  = (string)($_GET['sort'] ?? 'views');

$all  = vr_catalog();
$cf   = vr_category_fix();
$st   = vr_stats_range(30);
$deal = vr_flash_deal();

$sold = [];
foreach (vr_orders() as $o) {
    if ((string)($o['status'] ?? '') !== 'paid') continue;
    foreach ((array)($o['lines'] ?? []) as $l) $sold[(string)($l['pid'] ?? '')] = ($sold[(string)($l['pid'] ?? '')] ?? 0) + (int)($l['qty'] ?? 1);
}

$hiddenIds = array_keys(array_filter($cf, fn($r) => !empty($r['hidden'])));
$brands = []; $cats = [];
foreach ($all as $p) { $brands[$p['brand']] = ($brands[$p['brand']] ?? 0) + 1; $cats[$p['cat']] = ($cats[$p['cat']] ?? 0) + 1; }
ksort($brands); ksort($cats);

$rows = [];
if ($f === 'hidden') {
    foreach ($hiddenIds as $id) {
        $r = vr_admin_raw_product((string)$id);
        $rows[] = ['id' => (string)$id, 'brand' => (string)($r['brand'] ?? '—'), 'name' => (string)($cf[$id]['name'] ?? $r['name'] ?? $id), 'cat' => (string)($cf[$id]['cat'] ?? $r['cat'] ?? ''), 'price_cents' => 0, 'rrp_cents' => null, 'stock' => 0, 'sizes' => [], 'images' => (array)($r['images'] ?? []), 'hidden' => true, 'sku' => (string)($r['sku'] ?? '')];
    }
} else {
    foreach ($all as $p) {
        if ($f === 'low' && $p['stock'] > 2) continue;
        if ($f === 'nophoto' && $p['images']) continue;
        if ($f === 'rrp' && !$p['rrp_cents']) continue;
        if ($f === 'override' && !isset($cf[$p['id']]['price']) && !isset($cf[$p['id']]['face']) && !isset($cf[$p['id']]['name']) && !isset($cf[$p['id']]['cat'])) continue;
        if ($brand !== '' && $p['brand'] !== $brand) continue;
        if ($cat !== '' && $p['cat'] !== $cat) continue;
        if ($q !== '') {
            $hay = strtolower($p['id'] . ' ' . $p['brand'] . ' ' . $p['name'] . ' ' . $p['sku'] . ' ' . $p['cat'] . ' ' . vr_model_code($p));
            if (!str_contains($hay, strtolower($q))) continue;
        }
        $rows[] = $p;
    }
}

usort($rows, function ($a, $b) use ($sort, $st, $sold) {
    return match ($sort) {
        'price'  => (int)$b['price_cents'] <=> (int)$a['price_cents'],
        'stock'  => (int)$b['stock'] <=> (int)$a['stock'],
        'sold'   => (int)($sold[$b['id']] ?? 0) <=> (int)($sold[$a['id']] ?? 0),
        'name'   => strcmp($a['brand'] . $a['name'], $b['brand'] . $b['name']),
        'new'    => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0),
        default  => (int)($st['prod'][$b['id']] ?? 0) <=> (int)($st['prod'][$a['id']] ?? 0),
    };
});

if (($_GET['export'] ?? '') === 'csv') {
    vr_admin_csv('artikel', ['id', 'marke', 'name', 'kategorie', 'sku', 'preis', 'uvp', 'bestand', 'groessen', 'fotos', 'aufrufe_30d', 'verkauft'],
        array_map(fn($p) => [$p['id'], $p['brand'], $p['name'], $p['cat'], $p['sku'], number_format((int)$p['price_cents'] / 100, 2, ',', ''), $p['rrp_cents'] ? number_format((int)$p['rrp_cents'] / 100, 2, ',', '') : '',
            (int)$p['stock'], implode(' ', array_map(fn($s) => $s['label'] . ':' . $s['qty'], (array)$p['sizes'])), count((array)$p['images']), (int)($st['prod'][$p['id']] ?? 0), (int)($sold[$p['id']] ?? 0)], $rows));
}

$stockAll = array_sum(array_map(fn($p) => (int)$p['stock'], $all));
$valueAll = array_sum(array_map(fn($p) => (int)$p['stock'] * (int)$p['price_cents'], $all));
$tabs = ['all' => ['Alle', count($all)], 'low' => ['Knapp (≤2)', count(array_filter($all, fn($p) => $p['stock'] <= 2))], 'nophoto' => ['Ohne Foto', count(array_filter($all, fn($p) => !$p['images']))],
         'rrp' => ['Mit UVP', count(array_filter($all, fn($p) => $p['rrp_cents']))], 'override' => ['Mit Korrektur', count(array_filter($all, fn($p) => isset($cf[$p['id']]) && !empty(array_intersect_key($cf[$p['id']], ['price' => 1, 'face' => 1, 'name' => 1, 'cat' => 1]))))],
         'hidden' => ['Ausgeblendet', count($hiddenIds)]];
$keep = array_filter(['q' => $q, 'brand' => $brand, 'cat' => $cat, 'sort' => $sort !== 'views' ? $sort : '']);
[$page, $pag] = vr_admin_paginate($rows, 60);

vr_admin_head('Artikel', ['wide' => true, 'sub' => count($all) . ' Artikel sichtbar · ' . $stockAll . ' Stück · Warenwert ' . vr_money($valueAll) . ' · ' . count($hiddenIds) . ' ausgeblendet',
    'actions' => '<a class="btn btn--ghost btn--sm" href="' . h(vr_url('admin/deal.php')) . '"><span>48h-Aktion</span></a><a class="btn btn--ghost btn--sm" href="' . h(vr_url('admin/products.php', $keep + ['f' => $f, 'export' => 'csv'])) . '"><span>CSV</span></a>']);
vr_admin_tabs($tabs, $f, 'products.php', 'f', $keep);
?>
<form class="afilter" method="get">
  <input type="hidden" name="f" value="<?= h($f) ?>">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Name, SKU, Modellcode, ID">
  <select name="brand"><option value="">Alle Häuser</option><?php foreach ($brands as $b => $n): ?><option value="<?= h($b) ?>"<?= $brand === $b ? ' selected' : '' ?>><?= h($b) ?> (<?= $n ?>)</option><?php endforeach; ?></select>
  <select name="cat"><option value="">Alle Kategorien</option><?php foreach ($cats as $c => $n): ?><option value="<?= h($c) ?>"<?= $cat === $c ? ' selected' : '' ?>><?= h(vr_cat_label($c)) ?> (<?= $n ?>)</option><?php endforeach; ?></select>
  <select name="sort"><?php foreach (['views' => 'Aufrufe', 'sold' => 'Verkäufe', 'price' => 'Preis', 'stock' => 'Bestand', 'name' => 'Name', 'new' => 'Neueste'] as $k => $l): ?><option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>>Sortierung: <?= $l ?></option><?php endforeach; ?></select>
  <button class="btn btn--ghost btn--sm" type="submit"><span>Filtern</span></button>
  <?php if ($keep): ?><a class="asmall" href="<?= h(vr_url('admin/products.php', ['f' => $f])) ?>">zurücksetzen</a><?php endif; ?>
</form>
<?php vr_admin_card_open('', count($rows) . ' Treffer', 'acard--pad0'); ?>
<div class="tablewrap"><table class="table">
  <thead><tr><th></th><th>Artikel</th><th>Kategorie</th><th class="anum">Preis</th><th class="anum">UVP</th><th class="anum">Bestand</th><th>Größen</th><th class="anum">Fotos</th><th class="anum">Aufrufe 30 T.</th><th class="anum">Verkauft</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($page as $p): $isDeal = $deal && $deal['id'] === $p['id']; $hid = !empty($p['hidden']); ?>
    <tr<?= $hid ? ' class="is-dim"' : '' ?>>
      <td style="width:52px"><?php if ($p['images'] && !$hid): ?><img class="athumb" src="<?= h(vr_img(vr_card_image($p), 180)) ?>" alt="" loading="lazy"><?php elseif ($p['images']): ?><img class="athumb" src="<?= h(vr_img((string)$p['images'][0], 180)) ?>" alt="" loading="lazy"><?php endif; ?></td>
      <td><a href="<?= h(vr_url('admin/product.php', ['id' => $p['id']])) ?>"><b><?= h($p['brand']) ?></b> <?= h($hid ? $p['name'] : vr_card_name($p)) ?></a>
        <br><span class="asmall amono"><?= h($p['id']) ?><?= $p['sku'] !== '' ? ' · ' . h($p['sku']) : '' ?></span>
        <?= $isDeal ? ' ' . vr_admin_pill('48h-Aktion', 'warn') : '' ?><?= isset($cf[$p['id']]['price']) ? ' ' . vr_admin_pill('Preis manuell', 'ink') : '' ?><?= isset($cf[$p['id']]['face']) ? ' ' . vr_admin_pill('Kartenbild fix', 'muted') : '' ?></td>
      <td class="asmall"><?= h(vr_cat_label($p['cat'])) ?></td>
      <td class="anum"><?= $hid ? '—' : h(vr_money((int)$p['price_cents'])) ?></td>
      <td class="anum asmall"><?= $p['rrp_cents'] ? h(vr_money((int)$p['rrp_cents'])) . '<br>−' . (int)round(100 - $p['price_cents'] * 100 / $p['rrp_cents']) . ' %' : '—' ?></td>
      <td class="anum"><?= $hid ? '—' : (int)$p['stock'] ?><?= !$hid && $p['stock'] <= 2 ? ' ' . vr_admin_pill($p['stock'] === 0 ? 'leer' : 'knapp', $p['stock'] === 0 ? 'bad' : 'warn') : '' ?></td>
      <td class="asmall"><?= h(implode(' ', array_map(fn($s) => $s['label'] . '·' . $s['qty'], array_slice((array)$p['sizes'], 0, 8)))) ?></td>
      <td class="anum"><?= count((array)$p['images']) ?></td>
      <td class="anum"><?= (int)($st['prod'][$p['id']] ?? 0) ?></td>
      <td class="anum"><?= (int)($sold[$p['id']] ?? 0) ?></td>
      <td class="asmall"><?php if (!$hid): ?><a href="<?= h(vr_product_url($p)) ?>" target="_blank" rel="noopener">Shop ↗</a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$page): ?><tr><td colspan="11" class="asmall">Keine Artikel.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php vr_admin_card_close(); echo $pag; ?>
<?php vr_admin_foot(); ?>
