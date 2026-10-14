<?php
/** Müşteri paneli — özet: sayılar, son sipariş ve durumu, kısayollar, merkliste. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/../inc/lists.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$orders = vr_customer_orders($me);
$spent  = 0;
foreach ($orders as $o) if ((string)($o['status'] ?? '') === 'paid') $spent += (int)($o['total_cents'] ?? 0);
$wish = vr_wish_products();
$last = $orders[0] ?? null;

$name  = trim((string)$me['name']);
$first = $name !== '' ? (string)strtok($name, ' ') : '';
vr_acc_open($me, 'index.php', $first !== '' ? t('acc_hello', ['name' => $first]) : t('acc_title'));
?>
<?php vr_verify_notice($me); ?>

<div class="accstats">
  <div><b data-countup="<?= count($orders) ?>"><?= count($orders) ?></b><span><?= te('acc_stat_orders') ?></span></div>
  <div><b><?= h(vr_money($spent)) ?></b><span><?= te('acc_stat_spent') ?></span></div>
  <div><b data-countup="<?= count($wish) ?>"><?= count($wish) ?></b><span><?= te('acc_stat_wish') ?></span></div>
  <div><b><?= h(vr_date((int)($me['created_at'] ?? time()))) ?></b><span><?= te('acc_stat_since') ?></span></div>
</div>

<?php if ($last): ?>
  <article class="acccard acccard--order">
    <header class="acccard__h">
      <h2><?= te('acc_latest') ?></h2>
      <a class="link" href="<?= h(vr_url('account/order.php', ['id' => $last['id']])) ?>"><?= te('acc_order_view') ?></a>
    </header>
    <div class="olast">
      <div class="olast__pics">
        <?php foreach (array_slice((array)$last['lines'], 0, 3) as $l): $im = vr_acc_line_img($l); ?>
          <span class="olast__pic"><?php if ($im !== ''): ?><img src="<?= h(vr_img($im, 180)) ?>" alt="" loading="lazy"><?php endif; ?></span>
        <?php endforeach; ?>
      </div>
      <div class="olast__meta">
        <p class="olast__no"><?= te('order_number') ?> <b><?= h((string)$last['number']) ?></b></p>
        <p class="olast__sub"><?= h(vr_date((int)$last['created_at'])) ?> · <?= te('acc_items_n', ['n' => array_sum(array_map(fn($l) => (int)($l['qty'] ?? 1), (array)$last['lines']))]) ?> · <?= h(vr_money((int)$last['total_cents'])) ?></p>
        <?php vr_acc_steps($last); ?>
      </div>
    </div>
  </article>
<?php elseif (!empty($me['verified'])): ?>
  <article class="acccard accempty">
    <?= vr_icon('bag', 26) ?>
    <h2><?= te('acc_no_orders') ?></h2>
    <a class="btn btn--sm" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('acc_no_orders_cta') ?></span><?= vr_icon('arrow', 14) ?></a>
  </article>
<?php endif; ?>

<h2 class="acch2"><?= te('acc_quick') ?></h2>
<div class="accquick">
  <?php
  $quick = [
      ['account/orders.php',      'bag',   'acc_nav_orders',    'acc_quick_orders_d'],
      ['account/addresses.php',   'truck', 'acc_nav_addresses', 'acc_quick_addresses_d'],
      ['wishlist.php',            'heart', 'acc_nav_wishlist',  'acc_quick_wish_d'],
      ['account/profile.php',     'user',  'acc_nav_profile',   'acc_quick_profile_d'],
      ['account/preferences.php', 'bell',  'acc_nav_prefs',     'acc_quick_prefs_d'],
      ['account/security.php',    'lock',  'acc_nav_security',  'acc_quick_security_d'],
  ];
  foreach ($quick as [$href, $ico, $t, $d]): ?>
    <a class="accquick__a" href="<?= h(vr_url($href)) ?>">
      <span class="accquick__i"><?= vr_icon($ico, 20) ?></span>
      <b><?= te($t) ?></b><small><?= te($d) ?></small>
      <span class="accquick__go"><?= vr_icon('arrow', 14) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<h2 class="acch2"><?= te('acc_wish_title') ?></h2>
<?php if ($wish): ?>
  <?php vr_grid(array_slice($wish, 0, 4), ['class' => 'grid--4']); ?>
  <?php if (count($wish) > 4): ?>
    <p style="margin-top:18px"><a class="link" href="<?= h(vr_url('wishlist.php')) ?>"><?= te('acc_nav_wishlist') ?> (<?= count($wish) ?>)</a></p>
  <?php endif; ?>
<?php else: ?>
  <p class="accmuted"><?= te('acc_wish_empty') ?></p>
<?php endif; ?>

<aside class="acchelp">
  <?= vr_icon('shield', 22) ?>
  <div>
    <b><?= te('acc_help_t') ?></b>
    <p><?= te('acc_help_b', ['days' => (int)vr_config('return_days', 30)]) ?></p>
    <p><a class="link" href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('acc_returns_link') ?></a>
       · <a class="link" href="<?= h(vr_url('contact.php')) ?>"><?= te('acc_contact_link') ?></a></p>
  </div>
</aside>
<?php vr_acc_close(); ?>
