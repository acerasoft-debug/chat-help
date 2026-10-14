<?php
/**
 * Artikel-Detail: Galerie mit Kartenbild-Pin, Name/Kategorie, manueller Preis,
 * UVP, Bestand je Größe, Aufrufe, Bestellungen, Merklisten; ein-/ausblenden.
 *
 * Alle Änderungen landen in data/category-fix-admin.json (vr_category_fix_admin_set);
 * die Quelldateien (listings.json, retail-imported.json) werden NIE beschrieben.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$who = vr_admin_require();
$id  = trim((string)($_GET['id'] ?? ''));
$p   = $id !== '' ? vr_product($id) : null;
$raw = $id !== '' ? vr_admin_raw_product($id) : null;
if (!$p && !$raw) { vr_flash('Artikel nicht gefunden.', 'err'); vr_redirect('admin/products.php'); }

$cfAll = vr_category_fix();
$cf    = (array)($cfAll[$id] ?? []);
$hidden = $p === null;
$images = $p ? (array)$p['images'] : array_values(array_filter((array)($raw['images'] ?? []), 'is_string'));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $act = (string)($_POST['action'] ?? '');
    $patch = [];
    if ($act === 'face') {
        $img = (string)($_POST['img'] ?? '');
        if ($img !== '' && !in_array($img, $images, true)) { vr_flash('Dieses Bild gehört nicht zum Artikel.', 'err'); vr_redirect('admin/product.php', ['id' => $id]); }
        $patch = ['face' => $img];                        // '' = Pin aufheben (überstimmt auch die Repo-Korrektur)
        vr_flash($img !== '' ? 'Kartenbild festgelegt.' : 'Kartenbild-Pin aufgehoben.', 'ok');
    } elseif ($act === 'hide') {
        $patch = ['hidden' => true];
        vr_flash('Artikel ausgeblendet. Er ist sofort aus Shop, Suche und Karten verschwunden.', 'ok');
    } elseif ($act === 'show') {
        $patch = ['hidden' => false];
        vr_flash('Artikel wieder sichtbar.', 'ok');
    } elseif ($act === 'text') {
        $name = trim((string)($_POST['name'] ?? ''));
        $cat  = trim((string)($_POST['cat'] ?? ''));
        $patch = ['name' => $name !== '' ? mb_substr($name, 0, 160) : null, 'cat' => $cat !== '' ? mb_substr($cat, 0, 60) : null];
        vr_flash('Name und Kategorie gespeichert.', 'ok');
    } elseif ($act === 'price') {
        $eur = (float)str_replace(',', '.', (string)($_POST['price'] ?? ''));
        if ($eur > 0 && $eur < 20) { vr_flash('Preis unter 20 € — bitte prüfen.', 'err'); vr_redirect('admin/product.php', ['id' => $id]); }
        $patch = ['price' => $eur > 0 ? (int)round($eur * 100) : null];
        vr_flash($eur > 0 ? 'Manueller Preis gesetzt: ' . vr_money((int)round($eur * 100)) . '.' : 'Manueller Preis entfernt, es gilt wieder die automatische Berechnung.', 'ok');
    } elseif ($act === 'note') {
        $n = trim((string)($_POST['note'] ?? ''));
        $patch = ['note' => $n !== '' ? mb_substr($n, 0, 2000) : null];
        vr_flash('Notiz gespeichert.', 'ok');
    }
    if ($patch) { vr_category_fix_admin_set($id, $patch); vr_admin_audit('product_' . $act, ['id' => $id] + array_map(fn($v) => is_string($v) ? mb_substr($v, 0, 120) : $v, $patch)); }
    vr_redirect('admin/product.php', ['id' => $id]);
}

$brand = $p ? $p['brand'] : (string)($raw['brand'] ?? '—');
$name  = $p ? $p['name'] : (string)($cf['name'] ?? $raw['name'] ?? $id);
$cat   = $p ? $p['cat'] : (string)($cf['cat'] ?? $raw['cat'] ?? '');
$face  = $p ? vr_card_image($p) : '';
$deal  = vr_flash_deal();
$isDeal = $deal && $deal['id'] === $id;

$st30 = vr_stats_range(30); $st7 = vr_stats_range(7);
$views30 = (int)($st30['prod'][$id] ?? 0); $views7 = (int)($st7['prod'][$id] ?? 0);
$daily = [];
foreach (array_keys($st30['days']) as $day) $daily[$day] = (int)(vr_stats_day($day)['prod'][$id] ?? 0);

$orders = []; $soldQty = 0; $soldCents = 0;
foreach (vr_orders() as $o) {
    foreach ((array)($o['lines'] ?? []) as $l) {
        if ((string)($l['pid'] ?? '') !== $id) continue;
        $orders[] = $o + ['_line' => $l];
        if ((string)($o['status'] ?? '') === 'paid') { $soldQty += (int)($l['qty'] ?? 1); $soldCents += (int)($l['total_cents'] ?? (int)($l['qty'] ?? 1) * (int)($l['unit_cents'] ?? 0)); }
        break;
    }
}
usort($orders, fn($a, $b) => (int)$b['created_at'] <=> (int)$a['created_at']);
$wish = 0;
foreach (vr_customers() as $c) if (in_array($id, (array)($c['wish'] ?? []), true)) $wish++;

$cats = [];
foreach (vr_catalog() as $x) $cats[$x['cat']] = 1;
if ($cat !== '') $cats[$cat] = 1;
ksort($cats);
$ref = vr_official_ref($brand, $cat, (string)($p['sku'] ?? $raw['sku'] ?? ''), $name);

vr_admin_head($brand . ' · ' . ($p ? vr_card_name($p) : $name), [
    'back' => [vr_url('admin/products.php'), 'Artikel'],
    'sub'  => $id . ((($p['sku'] ?? $raw['sku'] ?? '') !== '') ? ' · SKU ' . ($p['sku'] ?? $raw['sku']) : '') . ($p && vr_model_code($p) !== '' ? ' · Modell ' . vr_model_code($p) : '') . ' · Quelle ' . ($p['source'] ?? 'Quelle') . ($p ? ' · ' . ($p['seller_type'] === 'vestra' ? 'Eigenbestand' : 'Verkäufer ' . $p['seller_uid']) : ''),
    'actions' => ($p ? '<a class="btn btn--ghost btn--sm" href="' . h(vr_product_url($p)) . '" target="_blank" rel="noopener"><span>Im Shop ↗</span></a>' : '')
        . ($hidden ? vr_admin_button('show', [], 'Wieder einblenden', 'btn btn--sm') : vr_admin_button('hide', [], 'Ausblenden', 'btn btn--ghost btn--sm btn--danger', 'Artikel aus dem Shop nehmen? Er bleibt in der Quelle und kann jederzeit wieder eingeblendet werden.')),
]);
?>
<?php if ($hidden): ?><div class="flash flash--err adm__flash">Dieser Artikel ist ausgeblendet: nicht im Shop, nicht in der Suche, nicht in Karten. Preis und Bestand werden erst nach dem Einblenden wieder berechnet.</div><?php endif; ?>
<?php if ($isDeal): ?><div class="flash flash--ok adm__flash">48h-Aktion läuft: −<?= (int)$deal['pct'] ?> % auf die UVP bis <?= h(date('d.m.Y H:i', $deal['ends_at'])) ?>. <a href="<?= h(vr_url('admin/deal.php')) ?>">Aktion bearbeiten →</a></div><?php endif; ?>

<?php vr_admin_stats([
  'Preis'     => [$p ? vr_money((int)$p['price_cents']) : '—', isset($cf['price']) ? 'manuell gesetzt' : ($isDeal ? '48h-Aktion' : ($ref ? ($ref['exact'] ? 'aus geprüfter UVP' : 'aus Referenzpreis') : 'automatisch'))],
  'UVP'       => [$p && $p['rrp_cents'] ? vr_money((int)$p['rrp_cents']) : '—', $p && $p['rrp_cents'] ? '−' . (int)round(100 - $p['price_cents'] * 100 / $p['rrp_cents']) . ' % im Shop' : ($ref ? 'Referenz ' . vr_money((int)$ref['cents']) . ', nicht geprüft' : 'keine Angabe')],
  'Bestand'   => [$p ? (int)$p['stock'] : 0, $p ? count((array)$p['sizes']) . ' Größe(n)' : ''],
  'Aufrufe'   => [$views30, $views7 . ' in 7 Tagen'],
  'Verkauft'  => [$soldQty, vr_money($soldCents)],
  'Merkliste' => [$wish, 'Kunden'],
], 6); ?>

<div class="agrid agrid--21">
  <div class="agrid">
    <?php vr_admin_card_open('Fotos', count($images) . ' Bild(er) · Kartenbild anklicken, um es festzulegen'); ?>
      <?php if (!$images): ?><p class="asmall">Keine Fotos. Der Shop zeigt eine neutrale Illustration.</p><?php endif; ?>
      <div class="agal agal--big">
        <?php foreach ($images as $i => $img): $isFace = $img === $face; $pinned = ($cf['face'] ?? '') === $img; ?>
          <figure class="<?= $isFace ? 'is-face' : '' ?>">
            <a href="<?= h($img) ?>" target="_blank" rel="noopener"><img src="<?= h(vr_img($img, 400)) ?>" alt="" loading="lazy"></a>
            <figcaption>
              <span class="asmall amono"><?= $i + 1 ?> · <?= h(basename($img)) ?></span>
              <?= $isFace ? vr_admin_pill($pinned ? 'Kartenbild (fix)' : 'Kartenbild (automatisch)', 'ink') : vr_admin_button('face', ['img' => $img], 'Als Kartenbild', 'btn btn--ghost btn--sm') ?>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <?php if (($cf['face'] ?? '') !== ''): ?><p class="asmall" style="margin-top:12px"><?= vr_admin_button('face', ['img' => ''], 'Pin aufheben (automatische Wahl)', 'btn btn--ghost btn--sm') ?></p><?php endif; ?>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Bestellungen mit diesem Artikel', count($orders) . ' Bestellung(en)', 'acard--pad0'); ?>
      <div class="tablewrap"><table class="table">
        <thead><tr><th>Nummer</th><th>Datum</th><th>Kunde</th><th>Größe</th><th class="anum">Menge</th><th>Status</th><th class="anum">Summe</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($orders, 0, 50) as $o): $l = $o['_line']; ?>
          <tr><td><a href="<?= h(vr_url('admin/order.php', ['id' => $o['id']])) ?>"><?= h((string)$o['number']) ?></a></td><td class="asmall"><?= h(vr_date((int)$o['created_at'], true)) ?></td>
            <td><?= h((string)($o['customer']['name'] ?? '')) ?><br><span class="asmall"><?= h((string)($o['customer']['email'] ?? '')) ?></span></td>
            <td><?= h((string)($l['size'] ?? '')) ?></td><td class="anum"><?= (int)($l['qty'] ?? 1) ?></td><td><?= vr_admin_order_pill($o) ?></td><td class="anum"><?= h(vr_money((int)$o['total_cents'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="7" class="asmall">Noch nicht bestellt.</td></tr><?php endif; ?>
        </tbody></table></div>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Aufrufe, 30 Tage'); ?>
      <?php vr_admin_chart(['Aufrufe' => $daily], array_map(fn($d) => date('d.m.', strtotime($d)), array_keys($daily)), ['h' => 160]); ?>
    <?php vr_admin_card_close(); ?>
  </div>

  <div class="agrid">
    <?php vr_admin_card_open('Name & Kategorie'); ?>
      <form method="post" class="aform"><?= vr_csrf_field() ?><input type="hidden" name="action" value="text">
        <?= vr_admin_field('Name im Shop', '<input type="text" name="name" value="' . h((string)($cf['name'] ?? '')) . '" placeholder="' . h($name) . '" maxlength="160">', 'Leer = Name aus der Quelle: ' . $name) ?>
        <?= vr_admin_field('Kategorie', '<input type="text" name="cat" list="acats" value="' . h((string)($cf['cat'] ?? '')) . '" placeholder="' . h($cat) . '" maxlength="60"><datalist id="acats">' . implode('', array_map(fn($c) => '<option value="' . h($c) . '">', array_keys($cats))) . '</datalist>', 'Leer = Kategorie aus der Quelle: ' . vr_cat_label($cat)) ?>
        <p><button class="btn btn--sm" type="submit"><span>Speichern</span></button></p>
      </form>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Preis'); ?>
      <form method="post" class="aform"><?= vr_csrf_field() ?><input type="hidden" name="action" value="price">
        <?= vr_admin_field('Manueller Verkaufspreis (€, brutto)', '<input type="text" inputmode="decimal" name="price" value="' . h(isset($cf['price']) ? number_format((int)$cf['price'] / 100, 2, ',', '') : '') . '" placeholder="' . h($p ? number_format((int)$p['price_cents'] / 100, 2, ',', '') : '') . '">', 'Leer = automatische Berechnung (feste Regel, UVP minus Hausrabatt, Stufenpreis). Ein manueller Preis gilt sofort und überstimmt alles außer der laufenden 48h-Aktion.') ?>
        <p><button class="btn btn--sm" type="submit"><span>Preis speichern</span></button>
        <?php if (isset($cf['price'])): ?> <span class="asmall">· aktuell manuell <?= h(vr_money((int)$cf['price'])) ?></span><?php endif; ?></p>
      </form>
      <dl class="adl">
        <dt>Geprüfte UVP</dt><dd><?= $p && $p['rrp_cents'] ? h(vr_money((int)$p['rrp_cents'])) . ' <span class="asmall">(official-prices.json, SKU-genau)</span>' : '<span class="asmall">keine — ohne geprüfte UVP wird im Shop kein durchgestrichener Preis gezeigt und keine Aktion angeboten</span>' ?></dd>
        <?php if ($ref && !$ref['exact']): ?><dt>Referenz</dt><dd><?= h(vr_money((int)$ref['cents'])) ?> <span class="asmall">(Marke/Kategorie, nicht modellgenau, wird nicht angezeigt)</span></dd><?php endif; ?>
        <dt>Hausrabatt</dt><dd>−<?= (int)vr_official_discount_bps($brand) / 100 ?> % <span class="asmall">auf die UVP</span></dd>
      </dl>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Bestand je Größe', $p ? 'abzüglich verkaufter Stücke' : ''); ?>
      <?php if ($p && $p['sizes']): ?>
        <table class="table"><thead><tr><th>Größe</th><th class="anum">Stück</th></tr></thead><tbody>
        <?php foreach ($p['sizes'] as $s): ?><tr><td><?= h($s['label']) ?></td><td class="anum"><?= (int)$s['qty'] ?></td></tr><?php endforeach; ?>
        </tbody></table>
        <p class="asmall" style="margin:10px 0 0">Bestand kommt aus der Quelle (Großhandel/Import) und wird um bezahlte Bestellungen reduziert. Änderungen am Grundbestand erfolgen in der Quelle, nicht hier.</p>
      <?php else: ?><p class="asmall"><?= $hidden ? 'Ausgeblendet — kein Bestand berechnet.' : 'Kein Bestand.' ?></p><?php endif; ?>
      <?php if ($p && !empty($p['sizes_unconfirmed'])): ?><p class="asmall"><?= vr_admin_pill('Größen unbestätigt', 'warn') ?> laut product-review.json</p><?php endif; ?>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Details'); ?>
      <dl class="adl">
        <dt>Haus</dt><dd><?= h($brand) ?></dd>
        <dt>Farben</dt><dd><?= $p ? h(implode(', ', vr_product_colours($p)) ?: '—') : '—' ?></dd>
        <dt>Zielgruppe</dt><dd><?= h((string)($p['audience'] ?? '') ?: '—') ?></dd>
        <dt>Zustand</dt><dd><?= h((string)($p['condition'] ?? $raw['condition'] ?? 'new')) ?></dd>
        <dt>Angelegt</dt><dd><?= !empty($p['created_at']) ? h(vr_date((int)$p['created_at'])) : '—' ?></dd>
        <?php if (!empty($p['origin']['url'])): ?><dt>Herkunft</dt><dd><a href="<?= h((string)$p['origin']['url']) ?>" target="_blank" rel="noopener"><?= h((string)($p['origin']['shop'] ?? parse_url((string)$p['origin']['url'], PHP_URL_HOST))) ?> ↗</a></dd><?php endif; ?>
        <?php if ($cf): ?><dt>Korrekturen</dt><dd class="amono asmall"><?= h(json_encode(array_diff_key($cf, ['note' => 1]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></dd><?php endif; ?>
      </dl>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Interne Notiz'); ?>
      <form method="post" class="aform"><?= vr_csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea name="note" rows="3" maxlength="2000" placeholder="Nur im Panel sichtbar"><?= h((string)($cf['note'] ?? '')) ?></textarea>
        <p><button class="btn btn--ghost btn--sm" type="submit"><span>Notiz speichern</span></button></p>
      </form>
    <?php vr_admin_card_close(); ?>
  </div>
</div>
<?php vr_admin_foot(); ?>
