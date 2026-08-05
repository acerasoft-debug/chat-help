<?php
/**
 * Satıcı paneli — genel bakış + ürün listesi
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/listings.php';
require_once __DIR__ . '/../inc/orders.php';
require_once __DIR__ . '/_nav.php';

$seller = vr_seller_require();

// Duraklat / sürdür
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $id  = (string)($_POST['id'] ?? '');
    $act = (string)($_POST['action'] ?? '');
    if ($id !== '' && in_array($act, ['pause', 'resume'], true)) {
        vr_listing_set_status($id, (string)$seller['id'], $act === 'pause' ? 'paused' : 'review');
        vr_flash(t('l_updated'), 'ok');
    }
    vr_redirect('seller/index.php');
}

$rows   = vr_seller_listings((string)$seller['id']);
$stats  = vr_seller_stats((string)$seller['id']);
$orders = vr_orders_for_seller((string)$seller['id']);

// Bu satıcının kazancı (yalnızca ödenmiş siparişlerden).
$gross = $net = 0;
foreach ($orders as $o) {
    $row = (array)($o['by_seller'][(string)$seller['id']] ?? []);
    $gross += (int)($row['gross'] ?? 0);
    $net   += (int)($row['net'] ?? 0);
}

vr_layout_start(['title' => t('dashboard'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <div class="sechead">
      <div>
        <h1 class="sechead__t"><?= h(vr_seller_display($seller)) ?></h1>
        <p class="sechead__s">
          <?= te('seller_' . ((string)$seller['seller_type'] === 'private' ? 'private' : 'business')) ?>
          · <?= h((string)$seller['email']) ?>
          <?php if (!empty($seller['charges_enabled'])): ?>
            · <span class="pill pill--live"><?= te('connect_ready') ?></span>
          <?php endif; ?>
        </p>
      </div>
      <a class="sechead__more" href="<?= h(vr_url('seller/listing.php')) ?>"><?= te('new_listing') ?><?= vr_icon('arrow', 16) ?></a>
    </div>

    <div class="dash">
      <?php vr_dashnav('index.php', $seller); ?>

      <div>
        <?php vr_connect_box($seller); ?>

        <div class="tiles">
          <div class="tile"><b><?= (int)$stats['live'] ?></b><span><?= te('st_live') ?></span></div>
          <div class="tile"><b><?= (int)$stats['total'] ?></b><span><?= te('my_listings') ?></span></div>
          <div class="tile"><b><?= count($orders) ?></b><span><?= te('seller_orders') ?></span></div>
          <div class="tile"><b style="font-size:22px"><?= h(vr_money($net)) ?></b><span><?= te('your_payout') ?></span></div>
        </div>

        <?php if (!$rows): ?>
          <div class="notice">
            <strong><?= te('no_listings') ?></strong><br>
            <?= te('sell_step3') ?> — <?= te('l_images_hint') ?>
          </div>
          <a class="btn" href="<?= h(vr_url('seller/listing.php')) ?>"><span><?= te('new_listing') ?></span></a>

        <?php else: ?>
          <h2 style="font-size:12px;letter-spacing:.2em;text-transform:uppercase;font-family:var(--sans);color:var(--muted);margin-bottom:12px">
            <?= te('my_listings') ?>
          </h2>

          <div style="overflow-x:auto">
            <table class="table">
              <thead>
                <tr>
                  <th><?= te('l_name') ?></th>
                  <th><?= te('l_price') ?></th>
                  <th><?= te('l_sizes') ?></th>
                  <th><?= te('l_status') ?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r):
                  $sizes = (array)($r['size_stock'] ?? []);
                  $stock = array_sum(array_map('intval', $sizes));
                  $status = (string)($r['status'] ?? 'review');
              ?>
                <tr>
                  <td>
                    <strong><?= h((string)$r['brand']) ?></strong> <?= h((string)$r['name']) ?>
                    <?php if (($r['sku'] ?? '') !== ''): ?>
                      <div style="font-size:11.5px;color:var(--muted)"><?= h((string)$r['sku']) ?></div>
                    <?php endif; ?>
                    <?php if (($r['moderation_note'] ?? '') !== ''): ?>
                      <div style="font-size:11.5px;color:var(--ember)"><?= h((string)$r['moderation_note']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="white-space:nowrap"><?= h(vr_money((int)round((float)$r['retail_price'] * 100))) ?></td>
                  <td style="font-size:12px">
                    <?= h(implode(' · ', array_map(fn($k, $v) => $k . '×' . (int)$v, array_keys($sizes), $sizes))) ?>
                    <div style="color:var(--muted)"><?= (int)$stock ?> <?= te('l_stock') ?></div>
                  </td>
                  <td><?= vr_listing_status_pill($status) ?></td>
                  <td style="white-space:nowrap;text-align:right">
                    <a class="xlink" href="<?= h(vr_url('seller/listing.php', ['id' => (string)$r['id']])) ?>"><?= te('edit') ?></a>
                    <form method="post" action="<?= h(vr_url('seller/index.php')) ?>" style="display:inline;margin-left:8px">
                      <?= vr_csrf_field() ?>
                      <input type="hidden" name="id" value="<?= h((string)$r['id']) ?>">
                      <input type="hidden" name="action" value="<?= $status === 'paused' ? 'resume' : 'pause' ?>">
                      <button class="xlink" type="submit"><?= $status === 'paused' ? te('resume') : te('pause') ?></button>
                    </form>
                  </td>
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
