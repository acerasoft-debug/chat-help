<?php
/** Sipariş listesi ve filtreleri. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$f = (string)($_GET['f'] ?? 'all');
$q = trim((string)($_GET['q'] ?? ''));

$rows = vr_orders();
usort($rows, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));

$rows = array_values(array_filter($rows, function ($o) use ($f, $q) {
    $st = (string)($o['status'] ?? '');
    if ($f === 'paid'     && $st !== 'paid') return false;
    if ($f === 'pending'  && $st !== 'pending') return false;
    if ($f === 'shipped'  && empty($o['shipped_at'])) return false;
    if ($f === 'open'     && (!empty($o['shipped_at']) || $st !== 'paid')) return false;
    if ($f === 'payout'   && empty($o['payout_pending'])) return false;
    if ($q !== '') {
        $hay = strtolower(($o['number'] ?? '') . ' ' . ($o['customer']['email'] ?? '') . ' ' . ($o['customer']['name'] ?? ''));
        if (!str_contains($hay, strtolower($q))) return false;
    }
    return true;
}));

$tabs = ['all' => 'Alle', 'open' => 'Offen', 'shipped' => 'Versendet', 'paid' => 'Bezahlt',
         'pending' => 'Unbezahlt', 'payout' => 'Auszahlung offen'];

vr_admin_head('Bestellungen');
?>
<nav class="sizetabs" style="margin-bottom:18px">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="<?= $f === $k ? 'is-on' : '' ?>" href="<?= h(vr_url('admin/orders.php', ['f' => $k])) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<form method="get" action="<?= h(vr_url('admin/orders.php')) ?>" style="display:flex;gap:8px;margin-bottom:18px">
  <input type="hidden" name="f" value="<?= h($f) ?>">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Nummer, Name oder E-Mail"
         style="flex:1;padding:9px 12px;border:1px solid var(--bone-3);background:var(--bone);font:inherit;font-size:13px">
  <button class="btn btn--ghost btn--sm" type="submit"><span>Suchen</span></button>
</form>

<p style="font-size:12.5px;color:var(--muted);margin-bottom:12px"><?= count($rows) ?> Bestellung(en)</p>

<?php if (!$rows): ?>
  <p style="color:var(--muted);font-size:14px">Nichts gefunden.</p>
<?php else: ?>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <thead><tr><th>Nummer</th><th>Datum</th><th>Kunde</th><th>Status</th><th>Versand</th><th>Summe</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $o): ?>
        <tr>
          <td><?= h((string)$o['number']) ?></td>
          <td><?= h(vr_date((int)$o['created_at'])) ?></td>
          <td><?= h((string)($o['customer']['email'] ?? '—')) ?></td>
          <td><?= h(vr_order_status_label((string)$o['status'])) ?></td>
          <td><?= !empty($o['shipped_at']) ? h(vr_date((int)$o['shipped_at'])) : '—' ?></td>
          <td><?= h(vr_money((int)$o['total_cents'])) ?></td>
          <td><a class="link" href="<?= h(vr_url('admin/order.php', ['id' => $o['id']])) ?>">Öffnen</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php vr_admin_foot(); ?>
