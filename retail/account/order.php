<?php
/**
 * Tek sipariş. Sahiplik vr_customer_order() içinde doğrulanıyor: başkasının
 * sipariş kimliğini yazmak 404 veriyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$o  = vr_customer_order($me, (string)($_GET['id'] ?? ''));
if ($o === null) { http_response_code(404); require __DIR__ . '/../404.php'; exit; }

$addr = (array)($o['customer']['address'] ?? []);

vr_layout_start(['title' => t('order_number') . ' ' . $o['number'], 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap">
    <h1 class="sechead__t" style="margin-bottom:28px"><?= te('order_number') ?> <?= h((string)$o['number']) ?></h1>
    <div class="dash">
      <?php vr_accnav('orders.php'); ?>
      <div>
        <div class="panel" style="border-top:0;padding-top:0">
          <div class="dl">
            <dt><?= te('date') ?></dt><dd><?= h(vr_date((int)$o['created_at'], true)) ?></dd>
            <dt><?= te('status') ?></dt><dd><?= h(vr_order_status_label((string)$o['status'])) ?></dd>
            <dt><?= te('total') ?></dt><dd><?= h(vr_money((int)$o['total_cents'])) ?></dd>
          </div>
        </div>

        <div class="panel" style="margin-top:24px">
          <h2><?= te('acc_order_items') ?></h2>
          <div class="tablewrap" tabindex="0">
            <table class="table">
              <tbody>
              <?php foreach ((array)$o['lines'] as $l): ?>
                <tr>
                  <td><?= h((string)($l['name'] ?? '')) ?><?php if (($l['size'] ?? '') !== ''): ?>
                      <span style="color:var(--muted)"> · <?= h((string)$l['size']) ?></span><?php endif; ?></td>
                  <td style="text-align:right;white-space:nowrap"><?= (int)($l['qty'] ?? 1) ?> ×
                      <?= h(vr_money((int)($l['unit_cents'] ?? 0))) ?></td>
                </tr>
              <?php endforeach; ?>
              <tr>
                <td><?= te('shipping') ?><?php if (($o['ship_label'] ?? '') !== ''): ?>
                    <span style="color:var(--muted)"> · <?= h((string)$o['ship_label']) ?></span><?php endif; ?></td>
                <td style="text-align:right"><?= (int)$o['ship_cents'] === 0 ? te('shipping_free') : h(vr_money((int)$o['ship_cents'])) ?></td>
              </tr>
              <tr>
                <td><strong><?= te('total') ?></strong></td>
                <td style="text-align:right"><strong><?= h(vr_money((int)$o['total_cents'])) ?></strong></td>
              </tr>
              </tbody>
            </table>
          </div>
        </div>

        <?php if (array_filter($addr)): ?>
          <div class="panel" style="margin-top:24px">
            <h2><?= te('acc_order_shipto') ?></h2>
            <p>
              <?= h((string)($o['customer']['name'] ?? '')) ?><br>
              <?= h(trim((string)($addr['line1'] ?? ''))) ?><br>
              <?= h(trim(((string)($addr['postal_code'] ?? '')) . ' ' . ((string)($addr['city'] ?? '')))) ?><br>
              <?= h((string)($addr['country'] ?? $o['ship_country'] ?? '')) ?>
            </p>
          </div>
        <?php endif; ?>

        <p style="margin-top:26px">
          <a class="link" href="<?= h(vr_url('account/orders.php')) ?>"><?= te('back') ?></a>
        </p>
      </div>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
