<?php
/** Panel özeti: ciro, siparişler, katalog, Vault, bekleyen işler. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/listings.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$orders = vr_orders();
$paid   = array_values(array_filter($orders, fn($o) => (string)($o['status'] ?? '') === 'paid'));
$rev    = 0; $fee = 0;
foreach ($paid as $o) { $rev += (int)($o['total_cents'] ?? 0); $fee += (int)($o['commission_cents'] ?? 0); }

$since30 = time() - 30 * 86400;
$rev30 = 0; $n30 = 0;
foreach ($paid as $o) if ((int)($o['created_at'] ?? 0) >= $since30) { $rev30 += (int)$o['total_cents']; $n30++; }

$review = array_values(array_filter(vr_listings_all(), fn($r) => (string)($r['status'] ?? 'review') === 'review'));
$lots   = vr_vault_lots(['include_sold' => true]);
$open   = array_values(array_filter($lots, fn($v) => !$v['sold']));
$pendingPayout = array_values(array_filter($paid, fn($o) => !empty($o['payout_pending'])));

vr_admin_head('Übersicht');
?>
<?php vr_admin_stats([
  'Umsatz gesamt'   => vr_money($rev),
  'Umsatz 30 Tage'  => vr_money($rev30),
  'Bestellungen'    => count($paid) . ' / ' . count($orders),
  'Provision'       => vr_money($fee),
]); ?>

<div class="stats" style="margin-top:14px">
  <div><b><?= count(vr_catalog()) ?></b><span>Artikel im Katalog</span></div>
  <div><b><?= count($open) ?></b><span>Offene Vault-Lose</span></div>
  <div><b><?= count(vr_sellers()) ?></b><span>Verkäufer</span></div>
  <div><b><?= count(vr_customers()) ?></b><span>Kundenkonten</span></div>
</div>

<?php if ($review || $pendingPayout): ?>
  <h2 class="sechead__t" style="font-size:20px;margin:34px 0 12px">Zu erledigen</h2>
  <?php if ($review): ?>
    <div class="notice" style="border-left-color:var(--brass)">
      <strong><?= count($review) ?> Angebot(e) warten auf Prüfung.</strong>
      <a class="link" href="<?= h(vr_url('admin/listings.php')) ?>">Ansehen</a>
    </div>
  <?php endif; ?>
  <?php if ($pendingPayout): ?>
    <div class="notice" style="border-left-color:var(--ember);margin-top:10px">
      <strong><?= count($pendingPayout) ?> Auszahlung(en) offen.</strong>
      Der Verkäufer war bei Zahlungseingang noch nicht bei Stripe freigeschaltet.
      <a class="link" href="<?= h(vr_url('admin/orders.php', ['f' => 'payout'])) ?>">Ansehen</a>
    </div>
  <?php endif; ?>
<?php endif; ?>

<h2 class="sechead__t" style="font-size:20px;margin:34px 0 12px">Letzte Bestellungen</h2>
<?php
$recent = $orders;
usort($recent, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));
$recent = array_slice($recent, 0, 10);
?>
<?php if (!$recent): ?>
  <p style="color:var(--muted);font-size:14px">Noch keine Bestellungen.</p>
<?php else: ?>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <thead><tr><th>Nummer</th><th>Datum</th><th>Status</th><th>Summe</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr>
          <td><?= h((string)$o['number']) ?></td>
          <td><?= h(vr_date((int)$o['created_at'])) ?></td>
          <td><?= h(vr_order_status_label((string)$o['status'])) ?></td>
          <td><?= h(vr_money((int)$o['total_cents'])) ?></td>
          <td><a class="link" href="<?= h(vr_url('admin/order.php', ['id' => $o['id']])) ?>">Öffnen</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php vr_admin_foot(); ?>
