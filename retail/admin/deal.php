<?php
/**
 * 48h-Aktion (data/flash-deal.json): ein Artikel mit GEPRÜFTER UVP, Rabatt
 * in Prozent, Start und Ende. Die Startseite zeigt den Countdown, der Preis
 * fällt automatisch nach Ablauf zurück — keine Aktion ohne echtes Ende.
 *
 * Die Datei wird nicht mehr vom Deployment überschrieben (Whitelist), was
 * hier steht, gilt.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$raw = vr_store_read('flash-deal.json', []);
if (!is_array($raw)) $raw = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'stop') {
        vr_store_write('flash-deal.json', ['id' => '', 'pct' => 0, 'starts_at' => '', 'ends_at' => '', 'note' => 'beendet ' . date('c') . ' (' . ($raw['id'] ?? '') . ')']);
        vr_admin_audit('deal_stop', ['id' => (string)($raw['id'] ?? '')]);
        vr_flash('Aktion beendet. Startseite, Badge und Preis sind sofort wieder normal.', 'ok');
        vr_redirect('admin/deal.php');
    }
    if ($act === 'save') {
        $id  = trim((string)($_POST['id'] ?? ''));
        $pct = (int)($_POST['pct'] ?? 0);
        $start = trim((string)($_POST['starts_at'] ?? ''));
        $hours = max(1, min(240, (int)($_POST['hours'] ?? 48)));
        $p = vr_product($id);
        $err = '';
        if (!$p) $err = 'Artikel nicht gefunden oder nicht sichtbar.';
        elseif (!$p['rrp_cents']) $err = 'Dieser Artikel hat keine geprüfte UVP. Ohne echte UVP darf kein Rabatt gegen einen Vergleichspreis beworben werden (PAngV). UVP zuerst in official-prices.json mit Quelle eintragen.';
        elseif ($pct < 5 || $pct > 80) $err = 'Rabatt zwischen 5 und 80 %.';
        $s = $start !== '' ? (strtotime($start) ?: 0) : time();
        if ($err === '' && $s <= 0) $err = 'Startzeit ungültig.';
        if ($err !== '') { vr_flash($err, 'err'); vr_redirect('admin/deal.php'); }
        $e = $s + $hours * 3600;
        $rrp = (int)$p['rrp_cents'];
        $dealPrice = (int)round($rrp * (100 - $pct) / 100);
        vr_store_write('flash-deal.json', [
            'id' => $id, 'pct' => $pct, 'starts_at' => gmdate('Y-m-d\TH:i:s\Z', $s), 'ends_at' => gmdate('Y-m-d\TH:i:s\Z', $e),
            'note' => $p['brand'] . ' ' . $p['sku'] . ', UVP ' . number_format($rrp / 100, 2, ',', '.') . ' € → ' . number_format($dealPrice / 100, 2, ',', '.') . ' €, ' . $hours . ' Stunden, gesetzt im Panel ' . date('d.m.Y H:i'),
        ]);
        vr_admin_audit('deal_save', ['id' => $id, 'pct' => $pct, 'hours' => $hours]);
        vr_flash('Aktion gespeichert: ' . $p['brand'] . ' · ' . vr_card_name($p) . ' −' . $pct . ' % bis ' . date('d.m.Y H:i', $e) . '.', 'ok');
        vr_redirect('admin/deal.php');
    }
}

$cur = vr_flash_deal();
$curP = $cur ? vr_product($cur['id']) : null;
$stored = ($raw['id'] ?? '') !== '' ? $raw : null;
$storedP = $stored ? vr_product((string)$stored['id']) : null;
$storedS = $stored ? (strtotime((string)$stored['starts_at']) ?: 0) : 0;
$storedE = $stored ? (strtotime((string)$stored['ends_at']) ?: 0) : 0;

// Kandidaten: alle sichtbaren Artikel mit geprüfter UVP, teuerste zuerst.
$cands = array_values(array_filter(vr_catalog(), fn($p) => $p['rrp_cents'] && $p['stock'] > 0 && $p['images']));
usort($cands, fn($a, $b) => (int)$b['rrp_cents'] <=> (int)$a['rrp_cents']);

$st = vr_stats_range(7);
$dealViews = $cur ? (int)($st['prod'][$cur['id']] ?? 0) : 0;
$dealSold = 0;
if ($cur) foreach (vr_orders() as $o) { if ((string)$o['status'] !== 'paid' || (int)$o['created_at'] < $cur['starts_at']) continue; foreach ((array)$o['lines'] as $l) if ((string)$l['pid'] === $cur['id']) $dealSold += (int)$l['qty']; }

vr_admin_head('48h-Aktion', ['sub' => 'Ein Artikel, ein echter Rabatt auf die geprüfte UVP, ein hartes Ende. Startseite und Artikelseite zeigen den Countdown automatisch.',
    'actions' => $stored ? vr_admin_button('stop', [], 'Aktion beenden', 'btn btn--ghost btn--sm btn--danger', 'Aktion sofort beenden?') : '']);
?>
<?php if ($cur && $curP): ?>
  <?php vr_admin_card_open('Läuft gerade', '<a href="' . h(vr_product_url($curP)) . '" target="_blank" rel="noopener">Artikelseite ↗</a>'); ?>
    <div class="adeal">
      <img src="<?= h(vr_img(vr_card_image($curP), 400)) ?>" alt="">
      <div>
        <p class="adeal__t"><b><?= h($curP['brand']) ?></b> <?= h(vr_card_name($curP)) ?></p>
        <p class="adeal__p"><s><?= h(vr_money((int)$curP['rrp_cents'])) ?></s> <b><?= h(vr_money((int)$curP['price_cents'])) ?></b> <?= vr_admin_pill('−' . (int)$cur['pct'] . ' %', 'warn') ?></p>
        <dl class="adl">
          <dt>Start</dt><dd><?= h(date('d.m.Y H:i', $cur['starts_at'])) ?></dd>
          <dt>Ende</dt><dd><?= h(date('d.m.Y H:i', $cur['ends_at'])) ?> <span class="asmall">(noch <?= h(vr_admin_ago($cur['ends_at'])) ?>)</span></dd>
          <dt>Aufrufe 7 T.</dt><dd><?= $dealViews ?></dd>
          <dt>Verkauft seit Start</dt><dd><?= $dealSold ?> Stück · Bestand <?= (int)$curP['stock'] ?></dd>
        </dl>
      </div>
    </div>
  <?php vr_admin_card_close(); ?>
<?php elseif ($stored): ?>
  <div class="flash flash--info adm__flash"><?= $storedS > time() ? 'Aktion geplant: startet ' . h(date('d.m.Y H:i', $storedS)) : 'Letzte Aktion abgelaufen am ' . h(date('d.m.Y H:i', $storedE)) ?> · <?= h((string)($storedP ? $storedP['brand'] . ' · ' . vr_card_name($storedP) : $stored['id'])) ?> −<?= (int)$stored['pct'] ?> %<?= $storedP && !$storedP['rrp_cents'] ? ' · ' . vr_admin_pill('UVP fehlt, Aktion inaktiv', 'bad') : '' ?></div>
<?php else: ?>
  <div class="flash flash--info adm__flash">Zurzeit keine Aktion.</div>
<?php endif; ?>

<div class="agrid agrid--12">
  <?php vr_admin_card_open('Neue Aktion'); ?>
    <form method="post" class="aform"><?= vr_csrf_field() ?><input type="hidden" name="action" value="save">
      <?= vr_admin_field('Artikel', '<select name="id" required><option value="">— wählen —</option>' . implode('', array_map(fn($p) => '<option value="' . h($p['id']) . '"' . (($stored['id'] ?? '') === $p['id'] ? ' selected' : '') . '>' . h($p['brand'] . ' · ' . vr_card_name($p) . ' · UVP ' . vr_money((int)$p['rrp_cents']) . ' · Bestand ' . $p['stock']) . '</option>', $cands)) . '</select>', count($cands) . ' Artikel mit geprüfter UVP, Bestand und Foto') ?>
      <?= vr_admin_field('Rabatt auf die UVP (%)', '<input type="number" name="pct" min="5" max="80" value="' . (int)($stored['pct'] ?? 50) . '" required>') ?>
      <?= vr_admin_field('Start', '<input type="datetime-local" name="starts_at" value="">', 'Leer = sofort') ?>
      <?= vr_admin_field('Dauer (Stunden)', '<input type="number" name="hours" min="1" max="240" value="48">', 'Standard 48 Stunden. Nach Ablauf gilt wieder der normale Preis.') ?>
      <p><button class="btn btn--sm" type="submit"><span>Aktion starten</span></button></p>
      <p class="anote">Eine neue Aktion ersetzt die laufende. Es gibt bewusst nur eine Aktion gleichzeitig: ein Stück, das wirklich günstiger ist, wirkt mehr als ein Banner voller Rabatte.</p>
    </form>
  <?php vr_admin_card_close(); ?>

  <?php vr_admin_card_open('Kandidaten', 'nach UVP', 'acard--pad0'); ?>
    <div class="tablewrap"><table class="table">
      <thead><tr><th></th><th>Artikel</th><th class="anum">UVP</th><th class="anum">Preis jetzt</th><th class="anum">bei −50 %</th><th class="anum">Bestand</th><th class="anum">Aufrufe 7 T.</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($cands, 0, 40) as $p): ?>
        <tr><td style="width:46px"><img class="athumb" src="<?= h(vr_img(vr_card_image($p), 180)) ?>" alt="" loading="lazy"></td>
          <td><a href="<?= h(vr_url('admin/product.php', ['id' => $p['id']])) ?>"><b><?= h($p['brand']) ?></b> <?= h(vr_card_name($p)) ?></a></td>
          <td class="anum"><?= h(vr_money((int)$p['rrp_cents'])) ?></td><td class="anum"><?= h(vr_money((int)$p['price_cents'])) ?></td>
          <td class="anum"><?= h(vr_money((int)round($p['rrp_cents'] / 2))) ?></td><td class="anum"><?= (int)$p['stock'] ?></td><td class="anum"><?= (int)($st['prod'][$p['id']] ?? 0) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$cands): ?><tr><td colspan="7" class="asmall">Kein Artikel mit geprüfter UVP.</td></tr><?php endif; ?>
      </tbody></table></div>
  <?php vr_admin_card_close(); ?>
</div>
<?php vr_admin_foot(); ?>
