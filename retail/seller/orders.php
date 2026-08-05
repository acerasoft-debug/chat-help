<?php
/**
 * Satıcının siparişleri
 * ---------------------
 * Yalnızca kendi satırları, kendi payı ve gönderim için gereken adres.
 * Başka satıcının satırları ya da tam sipariş toplamı gösterilmiyor — veri
 * minimizasyonu (DSGVO Art. 5) ve rekabet açısından doğrusu bu.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/orders.php';
require_once __DIR__ . '/_nav.php';

$seller = vr_seller_require();
$orders = vr_orders_for_seller((string)$seller['id']);

vr_layout_start(['title' => t('seller_orders'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <h1 class="sechead__t" style="margin-bottom:24px"><?= te('seller_orders') ?></h1>

    <div class="dash">
      <?php vr_dashnav('orders.php', $seller); ?>

      <div>
        <?php vr_connect_box($seller); ?>

        <?php if (!$orders): ?>
          <div class="notice"><strong><?= te('no_orders') ?></strong><br><?= te('sell_step3') ?></div>
          <a class="btn" href="<?= h(vr_url('seller/listing.php')) ?>"><span><?= te('new_listing') ?></span></a>

        <?php else: ?>
          <?php foreach ($orders as $o):
              $mine = array_values(array_filter((array)$o['lines'], fn($l) => (string)($l['seller_uid'] ?? '') === (string)$seller['id']));
              $row  = (array)($o['by_seller'][(string)$seller['id']] ?? []);
              $addr = (array)($o['customer']['address'] ?? []);

              // Bu satıcıya ait transferin durumu.
              $trStatus = 'held';
              foreach ((array)($o['transfers'] ?? []) as $tr) {
                  if ((string)($tr['seller'] ?? '') === (string)$seller['id']) { $trStatus = (string)($tr['status'] ?? 'held'); break; }
              }
              if ((string)$o['payout_mode'] === 'destination') $trStatus = 'sent';
          ?>
            <article class="doc__box" style="margin:0 0 18px">
              <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:12px">
                <div>
                  <strong style="font-family:var(--serif);font-size:19px"><?= h((string)$o['number']) ?></strong>
                  <div style="font-size:12px;color:var(--muted)"><?= h(vr_date((int)$o['created_at'], true)) ?></div>
                </div>
                <div style="text-align:right">
                  <span class="pill <?= $trStatus === 'sent' ? 'pill--live' : 'pill--review' ?>">
                    <?= $trStatus === 'sent' ? te('order_paid') : te('order_pending') ?>
                  </span>
                  <div style="font-size:19px;font-family:var(--serif);margin-top:6px"><?= h(vr_money((int)($row['net'] ?? 0))) ?></div>
                  <div style="font-size:11.5px;color:var(--muted)"><?= te('your_payout') ?></div>
                </div>
              </div>

              <table class="table">
                <tbody>
                <?php foreach ($mine as $ln): ?>
                  <tr>
                    <td>
                      <strong><?= h((string)$ln['brand']) ?></strong> <?= h((string)$ln['name']) ?>
                      <div style="font-size:11.5px;color:var(--muted)">
                        <?php if (($ln['size'] ?? 'ONE') !== 'ONE'): ?><?= te('size') ?> <?= h((string)$ln['size']) ?> · <?php endif; ?>
                        <?= h((string)($ln['sku'] ?? '')) ?>
                        <?php if (!empty($ln['vault'])): ?> · VAULT<?php endif; ?>
                      </div>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                      <?= (int)$ln['qty'] ?> × <?= h(vr_money((int)$ln['unit_cents'])) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>

              <div class="grid grid--2" style="gap:16px;margin-top:14px">
                <div>
                  <h3 style="font-size:10.5px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted);margin-bottom:6px">
                    Lieferadresse
                  </h3>
                  <p style="font-size:13px">
                    <?php
                    $lines = array_filter([
                        (string)($addr['name'] ?? ''),
                        (string)($addr['line1'] ?? ''),
                        (string)($addr['line2'] ?? ''),
                        trim((string)($addr['postal_code'] ?? '') . ' ' . (string)($addr['city'] ?? '')),
                        (string)($addr['country'] ?? ''),
                    ], fn($v) => trim($v) !== '');
                    echo $lines ? nl2br(h(implode("\n", $lines))) : '<span style="color:var(--muted)">—</span>';
                    ?>
                  </p>
                </div>
                <div>
                  <h3 style="font-size:10.5px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted);margin-bottom:6px">
                    <?= te('order_summary') ?>
                  </h3>
                  <div class="srow"><span><?= te('subtotal') ?></span><span><?= h(vr_money((int)($row['gross'] ?? 0))) ?></span></div>
                  <div class="srow"><span><?= te('our_fee') ?></span><span>−<?= h(vr_money((int)($row['commission'] ?? 0))) ?></span></div>
                  <div class="srow" style="font-weight:600"><span><?= te('your_payout') ?></span><span><?= h(vr_money((int)($row['net'] ?? 0))) ?></span></div>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
