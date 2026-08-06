<?php
/** Sipariş geçmişi — yalnızca doğrulanmış e-posta için (bkz. inc/customers.php). */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$orders = vr_customer_orders($me);

vr_layout_start(['title' => t('acc_nav_orders'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap">
    <h1 class="sechead__t" style="margin-bottom:28px"><?= te('acc_nav_orders') ?></h1>
    <div class="dash">
      <?php vr_accnav('orders.php'); ?>
      <div>
        <?php vr_verify_notice($me); ?>
        <p class="sechead__s" style="margin-bottom:22px"><?= te('acc_orders_lead') ?></p>

        <?php if (!$orders): ?>
          <p style="color:var(--muted);font-size:14px"><?= te('acc_no_orders') ?></p>
          <p style="margin-top:14px"><a class="btn btn--ghost btn--sm" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('acc_no_orders_cta') ?></span></a></p>
        <?php else: ?>
          <div class="tablewrap" tabindex="0">
            <table class="table">
              <thead><tr>
                <th><?= te('order_number') ?></th><th><?= te('date') ?></th>
                <th><?= te('status') ?></th><th><?= te('total') ?></th><th></th>
              </tr></thead>
              <tbody>
              <?php foreach ($orders as $o): ?>
                <tr>
                  <td><?= h((string)$o['number']) ?></td>
                  <td><?= h(vr_date((int)$o['created_at'])) ?></td>
                  <td><?= h(vr_order_status_label((string)$o['status'])) ?></td>
                  <td><?= h(vr_money((int)$o['total_cents'])) ?></td>
                  <td><a class="link" href="<?= h(vr_url('account/order.php', ['id' => $o['id']])) ?>"><?= te('acc_order_view') ?></a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
