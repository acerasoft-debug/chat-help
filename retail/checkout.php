<?php
/**
 * Kasa — Stripe Checkout'a devir
 * ------------------------------
 * Kart verisi hiçbir zaman bize gelmiyor: ödeme sayfası Stripe'ta açılıyor
 * (SCA/3DS, PCI kapsamı, cüzdanlar Stripe tarafında). Bizim işimiz siparişi
 * doğru kurmak, tutarı SUNUCUDA hesaplamak ve doğru Connect akışını seçmek.
 *
 * Akış:
 *   GET  → özet + onay ekranı (AGB/Widerruf onayı burada, Stripe'ta değil:
 *          böylece metnin ne zaman kabul edildiğini biz kaydediyoruz)
 *   POST → sipariş "pending" yazılır → Checkout Session → Stripe'a 303
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/orders.php';

$allowed = vr_shipping_countries();
$country = strtoupper(trim((string)($_POST['country'] ?? $_GET['country'] ?? '')));
if (!in_array($country, $allowed, true)) $country = 'DE';

$t = vr_cart_totals($country);
if (!$t['lines']) {
    vr_flash(t('cart_empty'), 'info');
    vr_redirect('cart.php');
}

$errors = [];

// --------------------------------------------------------------- POST: öde
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();

    if (empty($_POST['terms']))                       $errors['terms']   = t('must_accept');
    if (!empty($t['has_private']) && empty($_POST['private_ack'])) $errors['private'] = t('must_accept');
    if (!vr_stripe_ready())                           $errors['stripe']  = t('pay_unavailable');
    if (!vr_rate_ok('checkout', 20, 600))             $errors['rate']    = t('login_throttled');

    if (!$errors) {
        [$ok, $order] = vr_order_build($country);

        if (!$ok || !is_array($order)) {
            vr_flash(t(is_string($order) ? $order : 'cart_empty'), 'err');
            vr_redirect('cart.php');
        }

        // Onay kaydı: hangi metin sürümü, ne zaman, hangi IP'den kabul edildi.
        $order['consent'] = [
            'terms_version' => VR_TERMS_VERSION,
            'accepted_at'   => time(),
            'ip_hash'       => substr(hash('sha256', vr_client_ip() . vr_member_secret()), 0, 24),
            'private_ack'   => !empty($_POST['private_ack']),
        ];

        vr_order_save($order);

        [$sOk, $session] = vr_stripe_create_checkout($order);
        if (!$sOk) {
            vr_log('checkout_create_failed', ['order' => $order['id'], 'err' => (string)$session]);
            vr_order_mark_failed((string)$order['id'], 'session_create: ' . (string)$session);
            vr_flash(t('pay_unavailable') . ' (' . mb_substr((string)$session, 0, 120) . ')', 'err');
            vr_redirect('cart.php');
        }

        vr_order_update((string)$order['id'], ['stripe' => ['session_id' => (string)($session['id'] ?? '')]]);

        $url = (string)($session['url'] ?? '');
        if ($url === '') {
            vr_flash(t('pay_unavailable'), 'err');
            vr_redirect('cart.php');
        }

        // Stripe'a çıkış: uygulama içi olmadığı için vr_redirect kullanmıyoruz.
        header('Location: ' . $url, true, 303);
        exit;
    }
}

// --------------------------------------------------------------- GET: ekran
$ship = $t['shipping'];
vr_layout_start(['title' => t('checkout_title'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_breadcrumbs([t('cart_title') => vr_url('cart.php'), t('checkout_title') => null]); ?>
    <h1 class="sechead__t" style="margin-bottom:26px"><?= te('checkout_title') ?></h1>

    <?php if (!vr_stripe_ready()): ?>
      <div class="notice" style="border-left-color:var(--ember)">
        <strong><?= te('pay_unavailable') ?></strong><br><?= te('pay_unavailable_b') ?>
      </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
      <div class="flash flash--err" style="margin-bottom:12px"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="cart">
      <!-- ------------------------------------------------------- onay formu -->
      <div>
        <form class="form form--wide" method="post" action="<?= h(vr_url('checkout.php')) ?>">
          <?= vr_csrf_field() ?>

          <div class="field">
            <label for="country"><?= te('checkout_country') ?></label>
            <select id="country" name="country" data-autosubmit>
              <?php foreach ($allowed as $c): ?>
                <option value="<?= h($c) ?>" <?= $country === $c ? 'selected' : '' ?>><?= h($c) ?></option>
              <?php endforeach; ?>
            </select>
            <small><?= h(vr_zone_label((string)$ship['zone'])) ?> · <?= te('ships_in', ['days' => (string)$ship['days']]) ?></small>
          </div>

          <?php if (!empty($t['has_private'])): ?>
            <label class="check<?= isset($errors['private']) ? ' field--err' : '' ?>">
              <input type="checkbox" name="private_ack" value="1" <?= !empty($_POST['private_ack']) ? 'checked' : '' ?>>
              <span><?= te('checkout_private_ack') ?></span>
            </label>
          <?php endif; ?>

          <label class="check<?= isset($errors['terms']) ? ' field--err' : '' ?>">
            <input type="checkbox" name="terms" value="1" required <?= !empty($_POST['terms']) ? 'checked' : '' ?>>
            <span><?php
              // Bağlantılar metnin içine yerleşiyor; çeviri sırası değişse bile
              // yer tutucular doğru yere oturuyor.
              echo strtr(te('checkout_terms', ['terms' => '%T%', 'withdrawal' => '%W%']), [
                  '%T%' => '<a class="link" href="' . h(vr_url('legal/agb.php')) . '" target="_blank" rel="noopener">' . te('checkout_terms_l') . '</a>',
                  '%W%' => '<a class="link" href="' . h(vr_url('legal/widerruf.php')) . '" target="_blank" rel="noopener">' . te('checkout_wd_l') . '</a>',
              ]);
            ?></span>
          </label>

          <button class="btn btn--lg" type="submit" <?= vr_stripe_ready() ? '' : 'disabled' ?>>
            <span><?= te('checkout_go') ?></span><?= vr_icon('arrow', 16) ?>
          </button>

          <p style="font-size:12px;color:var(--muted)">
            <?= vr_icon('lock', 13) ?> <?= te('checkout_stripe') ?>
            <?php if (vr_stripe_settings()['mode'] === 'test'): ?>
              <br><strong>Stripe-Testmodus:</strong> es wird kein echtes Geld bewegt.
            <?php endif; ?>
          </p>
        </form>
      </div>

      <!-- ------------------------------------------------------------ özet -->
      <aside class="summary">
        <h2><?= te('order_summary') ?></h2>

        <?php foreach ($t['lines'] as $ln): ?>
          <div class="srow">
            <span style="max-width:64%">
              <?= h($ln['brand']) ?> <?= h($ln['name']) ?>
              <?php if ($ln['size'] !== 'ONE'): ?><br><small style="color:var(--muted)"><?= te('size') ?> <?= h($ln['size']) ?></small><?php endif; ?>
              <?php if ((int)$ln['qty'] > 1): ?><small style="color:var(--muted)"> × <?= (int)$ln['qty'] ?></small><?php endif; ?>
            </span>
            <span><?= h(vr_money((int)$ln['total_cents'])) ?></span>
          </div>
        <?php endforeach; ?>

        <div class="srow" style="border-top:1px solid var(--bone-3);padding-top:10px">
          <span><?= te('subtotal') ?></span><span><?= h(vr_money((int)$t['subtotal'])) ?></span>
        </div>
        <div class="srow">
          <span><?= te('shipping') ?> · <?= h((string)$ship['label']) ?></span>
          <span><?= (int)$t['ship_cents'] === 0 ? te('shipping_free') : h(vr_money((int)$t['ship_cents'])) ?></span>
        </div>
        <div class="srow srow--total"><span><?= te('total') ?></span><span><?= h(vr_money((int)$t['total'])) ?></span></div>
        <?php if ((int)$t['vat'] > 0): ?>
          <div class="srow srow--muted"><span><?= te('vat_of_which', ['amount' => vr_money((int)$t['vat'])]) ?></span></div>
        <?php endif; ?>

        <a class="xlink" href="<?= h(vr_url('cart.php')) ?>" style="margin-top:6px"><?= te('back') ?> · <?= te('cart_title') ?></a>
      </aside>
    </div>
  </div>
</section>

<?php vr_layout_end(); ?>
