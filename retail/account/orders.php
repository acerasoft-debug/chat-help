<?php
/** Sipariş geçmişi — yalnızca doğrulanmış e-posta için (bkz. inc/customers.php). */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$orders = vr_customer_orders($me);

vr_acc_open($me, 'orders.php', t('acc_nav_orders'), t('acc_orders_lead'));
vr_verify_notice($me);
?>
<?php if (!$orders): ?>
  <article class="acccard accempty">
    <?= vr_icon('bag', 26) ?>
    <h2><?= te('acc_no_orders') ?></h2>
    <a class="btn btn--sm" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('acc_no_orders_cta') ?></span><?= vr_icon('arrow', 14) ?></a>
  </article>
<?php else: ?>
  <div class="olist">
    <?php foreach ($orders as $o):
      $n = array_sum(array_map(fn($l) => (int)($l['qty'] ?? 1), (array)$o['lines'])); ?>
      <a class="orow" href="<?= h(vr_url('account/order.php', ['id' => $o['id']])) ?>">
        <span class="orow__pics">
          <?php foreach (array_slice((array)$o['lines'], 0, 3) as $l): $im = vr_acc_line_img($l); ?>
            <span class="olast__pic"><?php if ($im !== ''): ?><img src="<?= h(vr_img($im, 180)) ?>" alt="" loading="lazy"><?php endif; ?></span>
          <?php endforeach; ?>
        </span>
        <span class="orow__main">
          <b><?= te('order_number') ?> <?= h((string)$o['number']) ?></b>
          <small><?= h(vr_date((int)$o['created_at'])) ?> · <?= te('acc_items_n', ['n' => $n]) ?></small>
        </span>
        <span class="ochip<?= !empty($o['shipped_at']) ? ' ochip--ship' : ((string)$o['status'] === 'paid' ? ' ochip--ok' : '') ?>">
          <?= !empty($o['shipped_at']) ? te('acc_track_shipped') : h(vr_order_status_label((string)$o['status'])) ?>
        </span>
        <span class="orow__sum"><?= h(vr_money((int)$o['total_cents'])) ?></span>
        <span class="orow__go"><?= vr_icon('arrow', 14) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php vr_acc_close(); ?>
