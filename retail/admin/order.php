<?php
/**
 * Tek sipariş: satırlar, satıcı dağılımı, kargo kaydı, ödeme durumu.
 *
 * Buradan PARA HAREKETİ yapılmıyor. İade ve iptal Stripe panelinden yürür;
 * bir tıkla para iade eden bir düğme, ele geçirilen bir oturumun doğrudan
 * kasaya erişmesi demek olurdu. Burada yalnızca kargo bilgisi ve not var.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$o = vr_order_get((string)($_GET['id'] ?? ''));
if ($o === null) { http_response_code(404); require __DIR__ . '/../404.php'; exit; }

$saved = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'ship') {
        $patch = [
            'shipped_at'  => time(),
            'carrier'     => mb_substr(trim((string)($_POST['carrier'] ?? '')), 0, 60),
            'tracking'    => mb_substr(trim((string)($_POST['tracking'] ?? '')), 0, 120),
        ];
        vr_order_update((string)$o['id'], $patch);
        $saved = 'Als versendet vermerkt.';
    } elseif ($act === 'unship') {
        vr_order_update((string)$o['id'], ['shipped_at' => 0, 'carrier' => '', 'tracking' => '']);
        $saved = 'Versandvermerk entfernt.';
    } elseif ($act === 'note') {
        vr_order_update((string)$o['id'], ['admin_note' => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 2000)]);
        $saved = 'Notiz gespeichert.';
    }
    $o = vr_order_get((string)$o['id']);
}

$addr = (array)($o['customer']['address'] ?? []);
vr_admin_head('Bestellung ' . (string)$o['number']);
?>
<?php if ($saved !== ''): ?>
  <div class="flash flash--ok" style="margin-bottom:18px"><?= h($saved) ?></div>
<?php endif; ?>

<?php vr_admin_stats([
  'Status'  => vr_order_status_label((string)$o['status']),
  'Summe'   => vr_money((int)$o['total_cents']),
  'Provision' => vr_money((int)($o['commission_cents'] ?? 0)),
  'Datum'   => vr_date((int)$o['created_at']),
]); ?>

<div class="panel" style="margin-top:26px">
  <h2>Artikel</h2>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <tbody>
      <?php foreach ((array)$o['lines'] as $l): ?>
        <tr>
          <td><?= h((string)($l['name'] ?? '')) ?>
            <?php if (($l['size'] ?? '') !== ''): ?><span style="color:var(--muted)"> · <?= h((string)$l['size']) ?></span><?php endif; ?>
            <?php if (($l['seller_type'] ?? '') === 'private'): ?>
              <span class="pill" style="margin-left:6px">Privatverkauf</span><?php endif; ?>
          </td>
          <td style="text-align:right;white-space:nowrap"><?= (int)($l['qty'] ?? 1) ?> × <?= h(vr_money((int)($l['unit_cents'] ?? 0))) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr><td>Versand<?php if (($o['ship_label'] ?? '') !== ''): ?> · <?= h((string)$o['ship_label']) ?><?php endif; ?></td>
          <td style="text-align:right"><?= h(vr_money((int)$o['ship_cents'])) ?></td></tr>
              <?php if ((int)($o['discount_cents'] ?? 0) > 0): ?>
                <tr>
                  <td><?= h('Gutschein ' . (string)($o["voucher_code"] ?? "")) ?></td>
                  <td style="text-align:right;color:var(--ember-text)">−<?= h(vr_money((int)$o['discount_cents'])) ?></td>
                </tr>
              <?php endif; ?>
      <tr><td><strong>Gesamt</strong> <span style="color:var(--muted)">(davon <?= h(vr_money((int)$o['vat_cents'])) ?> MwSt.)</span></td>
          <td style="text-align:right"><strong><?= h(vr_money((int)$o['total_cents'])) ?></strong></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="panel" style="margin-top:24px">
  <h2>Kunde</h2>
  <div class="dl">
    <dt>Name</dt><dd><?= h((string)($o['customer']['name'] ?? '—')) ?></dd>
    <dt>E-Mail</dt><dd><?= h((string)($o['customer']['email'] ?? '—')) ?></dd>
    <dt>Adresse</dt><dd>
      <?= h(trim((string)($addr['line1'] ?? ''))) ?><br>
      <?= h(trim(((string)($addr['postal_code'] ?? '')) . ' ' . ((string)($addr['city'] ?? '')))) ?>
      <?= h((string)($addr['country'] ?? $o['ship_country'] ?? '')) ?>
    </dd>
  </div>
</div>

<?php if (!empty($o['by_seller'])): ?>
<div class="panel" style="margin-top:24px">
  <h2>Auszahlung — <?= h((string)($o['payout_mode'] ?? '—')) ?></h2>
  <?php if (!empty($o['payout_pending'])): ?>
    <div class="notice" style="border-left-color:var(--ember)">
      Auszahlung offen: Der Verkäufer war bei Zahlungseingang noch nicht bei Stripe freigeschaltet.
      Nach der Freischaltung läuft <code>php tools/selftest.php</code> bzw. der nächste Webhook die Übertragung nach.
    </div>
  <?php endif; ?>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <thead><tr><th>Verkäufer</th><th>Anteil</th><th>Provision</th><th>Transfer</th></tr></thead>
      <tbody>
      <?php foreach ((array)$o['by_seller'] as $sid => $s): ?>
        <tr>
          <td><?= h((string)($s['label'] ?? $sid)) ?></td>
          <td><?= h(vr_money((int)($s['gross_cents'] ?? 0))) ?></td>
          <td><?= h(vr_money((int)($s['fee_cents'] ?? 0))) ?></td>
          <td><?= h((string)(($o['transfers'][$sid] ?? '') ?: '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:24px">
  <h2>Versand</h2>
  <?php if (!empty($o['shipped_at'])): ?>
    <p>Versendet am <?= h(vr_date((int)$o['shipped_at'], true)) ?>
       <?php if (($o['carrier'] ?? '') !== ''): ?> · <?= h((string)$o['carrier']) ?><?php endif; ?>
       <?php if (($o['tracking'] ?? '') !== ''): ?> · <?= h((string)$o['tracking']) ?><?php endif; ?></p>
    <form method="post" style="margin-top:10px">
      <?= vr_csrf_field() ?><input type="hidden" name="action" value="unship">
      <button class="btn btn--ghost btn--sm" type="submit"><span>Vermerk entfernen</span></button>
    </form>
  <?php else: ?>
    <form class="form" method="post">
      <?= vr_csrf_field() ?><input type="hidden" name="action" value="ship">
      <div class="field"><label for="carrier">Versanddienst</label>
        <input id="carrier" name="carrier" type="text" placeholder="DHL"></div>
      <div class="field"><label for="tracking">Sendungsnummer</label>
        <input id="tracking" name="tracking" type="text"></div>
      <div class="field"><button class="btn btn--brass btn--sm" type="submit"><span>Als versendet vermerken</span></button></div>
    </form>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:24px">
  <h2>Interne Notiz</h2>
  <form class="form" method="post">
    <?= vr_csrf_field() ?><input type="hidden" name="action" value="note">
    <div class="field">
      <textarea name="note" rows="3" style="width:100%;padding:10px;border:1px solid var(--bone-3);background:var(--bone);font:inherit;font-size:13.5px"><?= h((string)($o['admin_note'] ?? '')) ?></textarea>
      <small>Nur intern. Der Kunde sieht diese Notiz nicht.</small>
    </div>
    <div class="field"><button class="btn btn--ghost btn--sm" type="submit"><span>Notiz speichern</span></button></div>
  </form>
</div>

<p style="margin-top:24px"><a class="link" href="<?= h(vr_url('admin/orders.php')) ?>">Zurück zur Liste</a></p>
<?php vr_admin_foot(); ?>
