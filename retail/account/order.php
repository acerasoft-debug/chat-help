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

vr_acc_open($me, 'orders.php', t('order_number') . ' ' . $o['number']);
?>
<p class="accback"><a class="link" href="<?= h(vr_url('account/orders.php')) ?>">← <?= te('acc_nav_orders') ?></a></p>

<article class="acccard">
  <header class="acccard__h">
    <h2><?= te('acc_order_status') ?></h2>
    <span class="accmuted"><?= h(vr_date((int)$o['created_at'], true)) ?></span>
  </header>
  <?php vr_acc_steps($o); ?>
  <?php if (!empty($o['shipped_at']) && (($o['carrier'] ?? '') !== '' || ($o['tracking'] ?? '') !== '')): ?>
    <p class="otrack"><?= vr_icon('truck', 18) ?>
      <span><?= te('acc_order_tracking') ?>: <?= h(trim((string)($o['carrier'] ?? '') . ' ' . (string)($o['tracking'] ?? ''))) ?></span></p>
  <?php endif; ?>
</article>

<article class="acccard">
  <header class="acccard__h"><h2><?= te('acc_order_items') ?></h2></header>
  <ul class="olines">
    <?php foreach ((array)$o['lines'] as $l):
      $im = vr_acc_line_img($l);
      $p  = ($l['pid'] ?? '') !== '' ? vr_product((string)$l['pid']) : null; ?>
      <li class="oline">
        <span class="olast__pic"><?php if ($im !== ''): ?><img src="<?= h(vr_img($im, 180)) ?>" alt="" loading="lazy"><?php endif; ?></span>
        <span class="oline__t">
          <small><?= h((string)($l['brand'] ?? '')) ?></small>
          <?php if ($p): ?><a href="<?= h(vr_product_url($p)) ?>"><?= h((string)($l['name'] ?? '')) ?></a><?php else: ?><b><?= h((string)($l['name'] ?? '')) ?></b><?php endif; ?>
          <em><?= h(implode(' · ', array_filter([(string)($l['colour'] ?? ''), ($l['size'] ?? '') !== '' ? t('size') . ' ' . $l['size'] : '']))) ?></em>
        </span>
        <span class="oline__q"><?= (int)($l['qty'] ?? 1) ?> × <?= h(vr_money((int)($l['unit_cents'] ?? 0))) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
  <div class="osum">
    <div><span><?= te('shipping') ?><?php if (($o['ship_label'] ?? '') !== ''): ?> · <?= h((string)$o['ship_label']) ?><?php endif; ?></span>
         <span><?= (int)$o['ship_cents'] === 0 ? te('shipping_free') : h(vr_money((int)$o['ship_cents'])) ?></span></div>
    <?php if ((int)($o['discount_cents'] ?? 0) > 0): ?>
      <div><span><?= te('vou_line', ['code' => h((string)($o['voucher_code'] ?? ''))]) ?></span><span>−<?= h(vr_money((int)$o['discount_cents'])) ?></span></div>
    <?php endif; ?>
    <div class="osum__t"><span><?= te('total') ?></span><span><?= h(vr_money((int)$o['total_cents'])) ?></span></div>
  </div>
</article>

<div class="acc2">
  <?php if (array_filter($addr)): ?>
    <article class="acccard">
      <header class="acccard__h"><h2><?= te('acc_order_shipto') ?></h2></header>
      <p class="addr">
        <?= h((string)($o['customer']['name'] ?? '')) ?><br>
        <?= h(trim((string)($addr['line1'] ?? ''))) ?><br>
        <?php if (trim((string)($addr['line2'] ?? '')) !== ''): ?><?= h(trim((string)$addr['line2'])) ?><br><?php endif; ?>
        <?= h(trim(((string)($addr['postal_code'] ?? '')) . ' ' . ((string)($addr['city'] ?? '')))) ?><br>
        <?= h(vr_country_name((string)($addr['country'] ?? $o['ship_country'] ?? ''))) ?>
      </p>
    </article>
    <?php $ba = (array)($o['customer']['billing'] ?? []); if (array_filter($ba)): ?>
    <article class="acccard">
      <header class="acccard__h"><h2><?= te('acc_addr_bill') ?></h2></header>
      <p class="addr">
        <?php if (trim((string)($ba['company'] ?? '')) !== ''): ?><?= h(trim((string)$ba['company'])) ?><br><?php endif; ?>
        <?= h((string)($ba['name'] ?? '')) ?><br>
        <?= h(trim((string)($ba['line1'] ?? ''))) ?><br>
        <?php if (trim((string)($ba['line2'] ?? '')) !== ''): ?><?= h(trim((string)$ba['line2'])) ?><br><?php endif; ?>
        <?= h(trim(((string)($ba['postal_code'] ?? '')) . ' ' . ((string)($ba['city'] ?? '')))) ?><br>
        <?= h(vr_country_name((string)($ba['country'] ?? ''))) ?>
        <?php if (trim((string)($ba['vat'] ?? '')) !== ''): ?><br><?= te('acc_vat') ?>: <?= h(trim((string)$ba['vat'])) ?><?php endif; ?>
      </p>
    </article>
    <?php endif; ?>
  <?php endif; ?>
  <article class="acccard">
    <header class="acccard__h"><h2><?= te('acc_order_help') ?></h2></header>
    <p class="accmuted"><?= te('acc_help_b', ['days' => (int)vr_config('return_days', 30)]) ?></p>
    <p style="margin-top:12px"><a class="link" href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('acc_returns_link') ?></a>
      · <a class="link" href="<?= h(vr_url('contact.php', ['order' => (string)$o['number']])) ?>"><?= te('acc_contact_link') ?></a></p>
  </article>
</div>
<?php vr_acc_close(); ?>
