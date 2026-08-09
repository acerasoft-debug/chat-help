<?php
/**
 * Sipariş sayfası — teşekkür ekranı + durum takibi
 * ------------------------------------------------
 * Stripe'tan dönüşte (success_url) siparişi BURADA da doğruluyoruz. Webhook
 * asıl kaynaktır ama ağ gecikirse müşteri "ödedim, hiçbir şey olmadı" ekranı
 * görmesin: oturum durumunu Stripe'a sorup aynı idempotent fonksiyonu
 * çağırıyoruz. İki yol da vr_order_mark_paid()'e gidiyor, o da bir kez çalışıyor.
 *
 * Giriş gerektirmiyor: id + token ikilisi (token 16 bayt rastgele) yeterli.
 * Token olmadan sipariş numarası + e-posta ile de sorgulanabiliyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/orders.php';

$id    = trim((string)($_GET['id'] ?? ''));
$token = trim((string)($_GET['token'] ?? ''));
$order = ($id !== '' && $token !== '') ? vr_order_get($id, $token) : null;

// ------------------------------------------------------- numara + e-posta ile
$lookupErr = '';
if ($order === null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $num   = strtoupper(trim((string)($_POST['number'] ?? '')));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if (!vr_rate_ok('order-lookup', 12, 600)) {
        $lookupErr = t('login_throttled');
    } elseif ($num === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $lookupErr = t('order_not_found');
    } else {
        foreach (vr_orders() as $o) {
            if (strtoupper((string)($o['number'] ?? '')) !== $num) continue;
            if (strtolower((string)($o['customer']['email'] ?? '')) !== $email) continue;
            $order = $o;
            break;
        }
        if ($order === null) $lookupErr = t('order_not_found');
    }
}

// ----------------------------------------------- Stripe'tan dönüş doğrulaması
if ($order !== null && (string)$order['status'] !== 'paid' && !empty($_GET['paid'])) {
    $sid = (string)($order['stripe']['session_id'] ?? '');
    if ($sid !== '' && vr_stripe_ready()) {
        $s = vr_stripe_get_session($sid);

        if (is_array($s) && (string)($s['payment_status'] ?? '') === 'paid') {
            $pi     = $s['payment_intent'] ?? null;
            $piId   = is_array($pi) ? (string)($pi['id'] ?? '') : (string)$pi;
            $charge = '';
            if (is_array($pi)) {
                // Stripe sürümüne göre charge ya latest_charge'da ya charges.data[0]'da.
                $charge = (string)($pi['latest_charge'] ?? '');
                if ($charge === '' && !empty($pi['charges']['data'][0]['id'])) {
                    $charge = (string)$pi['charges']['data'][0]['id'];
                }
            }

            $cd = (array)($s['customer_details'] ?? []);
            $sd = (array)($s['shipping_details'] ?? $s['collected_information']['shipping_details'] ?? []);
            $ad = (array)($sd['address'] ?? $cd['address'] ?? []);

            $updated = vr_order_mark_paid((string)$order['id'], [
                'session_id'     => $sid,
                'payment_intent' => $piId,
                'charge_id'      => $charge,
                'customer'       => [
                    'email'   => (string)($cd['email'] ?? ''),
                    'name'    => (string)($sd['name'] ?? $cd['name'] ?? ''),
                    'address' => array_filter([
                        'name'        => (string)($sd['name'] ?? ''),
                        'line1'       => (string)($ad['line1'] ?? ''),
                        'line2'       => (string)($ad['line2'] ?? ''),
                        'postal_code' => (string)($ad['postal_code'] ?? ''),
                        'city'        => (string)($ad['city'] ?? ''),
                        'country'     => (string)($ad['country'] ?? ''),
                    ]),
                ],
            ]);
            if ($updated !== null) $order = $updated;

            // Ödeme tamam → sepeti boşalt (loslar zaten "sold" işaretlendi).
            vr_cart_clear(false);
        }
    }
}

$days = (int)vr_config('return_days', 30);

// ---------------------------------------------------------------------------
// SORGULAMA FORMU (sipariş bulunamadı / bağlantı yok)
// ---------------------------------------------------------------------------
if ($order === null) {
    vr_layout_start(['title' => t('order_status'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <h1 class="sechead__t"><?= te('order_status') ?></h1>
    <p class="sechead__s" style="margin:12px 0 28px"><?= te('order_mail_sent', ['email' => t('email')]) ?></p>

    <?php if ($lookupErr !== ''): ?>
      <div class="flash flash--err" style="margin-bottom:16px"><?= h($lookupErr) ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= h(vr_url('order.php')) ?>">
      <?= vr_csrf_field() ?>
      <div class="field">
        <label for="number"><?= te('order_number') ?></label>
        <input id="number" name="number" required placeholder="<?= h((string)vr_config('order_prefix', 'VS')) ?>-2608-A1B2C"
               value="<?= h((string)($_POST['number'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="email"><?= te('email') ?></label>
        <input id="email" name="email" type="email" required autocomplete="email"
               value="<?= h((string)($_POST['email'] ?? '')) ?>">
      </div>
      <button class="btn" type="submit"><span><?= te('order_status') ?></span></button>
    </form>
  </div>
</section>
<?php
    vr_layout_end();
    exit;
}

// ---------------------------------------------------------------------------
// SİPARİŞ GÖRÜNÜMÜ
// ---------------------------------------------------------------------------
$paid = (string)$order['status'] === 'paid';
vr_layout_start(['title' => $order['number'], 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap">
    <div class="done">
      <div class="done__mark"><?= vr_icon($paid ? 'check' : 'clock', 28) ?></div>

      <h1><?= $paid ? te('order_thanks') : h(vr_order_status_label((string)$order['status'])) ?></h1>

      <p class="done__num"><?= te('order_number') ?><br><?= h($order['number']) ?></p>

      <?php if ($paid && ($order['customer']['email'] ?? '') !== ''): ?>
        <p style="color:var(--muted);font-size:14px">
          <?= te('order_mail_sent', ['email' => $order['customer']['email']]) ?>
        </p>
      <?php elseif (!$paid): ?>
        <p style="color:var(--muted);font-size:14px"><?= te('order_pending') ?></p>
      <?php endif; ?>

      <?php if ($paid): ?>
        <div class="done__next">
          <ol>
            <li><?= vr_icon('doc', 18) ?><span><?= te('order_next_1') ?></span></li>
            <li><?= vr_icon('truck', 18) ?><span><?= te('order_next_2') ?></span></li>
            <li><?= vr_icon('check', 18) ?><span><?= te('order_next_3', ['days' => $days]) ?></span></li>
          </ol>
        </div>
      <?php endif; ?>
    </div>

    <!-- ------------------------------------------------------------- özet -->
    <div class="cart" style="margin-top:clamp(30px,4vw,54px)">
      <div>
        <?php foreach ((array)$order['lines'] as $ln): ?>
          <div class="citem">
            <div class="citem__img"><img src="<?= h(vr_img((string)$ln['image'], 180)) ?>" alt="" loading="lazy" width="200" height="250"></div>
            <div>
              <p class="citem__brand"><?= h((string)$ln['brand']) ?></p>
              <p class="citem__name"><?= h((string)$ln['name']) ?></p>
              <p class="citem__meta">
                <?php if (($ln['size'] ?? 'ONE') !== 'ONE'): ?><?= te('size') ?> <?= h((string)$ln['size']) ?> · <?php endif; ?>
                <?= te('sold_by') ?> <?= h((string)$ln['seller_name']) ?>
                <?php if (!empty($ln['vault'])): ?><span class="pill pill--brass" style="margin-left:6px">VAULT</span><?php endif; ?>
              </p>
            </div>
            <div class="citem__right">
              <span class="citem__price"><?= h(vr_money((int)$ln['total_cents'])) ?></span>
              <?php if ((int)$ln['qty'] > 1): ?>
                <span class="citem__meta"><?= (int)$ln['qty'] ?> × <?= h(vr_money((int)$ln['unit_cents'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <aside class="summary">
        <h2><?= te('order_summary') ?></h2>
        <div class="srow"><span><?= te('subtotal') ?></span><span><?= h(vr_money((int)$order['subtotal_cents'])) ?></span></div>
        <div class="srow">
          <span><?= te('shipping') ?><?= ($order['ship_label'] ?? '') !== '' ? ' · ' . h((string)$order['ship_label']) : '' ?></span>
          <span><?= (int)$order['ship_cents'] === 0 ? te('shipping_free') : h(vr_money((int)$order['ship_cents'])) ?></span>
        </div>
        <?php if ((int)($order['discount_cents'] ?? 0) > 0): ?>
          <div class="srow srow--cut">
            <span><?= te('vou_line', ['code' => h((string)($order['voucher_code'] ?? ''))]) ?></span>
            <span>−<?= h(vr_money((int)$order['discount_cents'])) ?></span>
          </div>
        <?php endif; ?>
        <div class="srow srow--total"><span><?= te('total') ?></span><span><?= h(vr_money((int)$order['total_cents'])) ?></span></div>
        <?php if ((int)($order['vat_cents'] ?? 0) > 0): ?>
          <div class="srow srow--muted"><span><?= te('vat_of_which', ['amount' => vr_money((int)$order['vat_cents'])]) ?></span></div>
        <?php endif; ?>

        <div class="srow srow--muted">
          <span><?= te('l_status') ?></span><span><?= h(vr_order_status_label((string)$order['status'])) ?></span>
        </div>
        <div class="srow srow--muted">
          <span><?= h(vr_date((int)$order['created_at'], true)) ?></span>
        </div>

        <?php if (!empty($order['has_private'])): ?>
          <p class="srow--muted" style="display:block;border-left:2px solid var(--brass);padding-left:10px">
            <?= te('checkout_private_ack') ?>
          </p>
        <?php endif; ?>

        <a class="btn btn--ghost btn--block" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('keep_shopping') ?></span></a>
        <p class="srow--muted" style="display:block">
          <a class="link" href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a> ·
          <a class="link" href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>
        </p>
      </aside>
    </div>
  </div>
</section>

<?php vr_layout_end(); ?>
