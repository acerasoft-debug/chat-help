<?php
/**
 * Sepet — hem eylem ucu hem sayfa
 * -------------------------------
 * POST → işle → 303 ile geri yönlendir (PRG). Böylece "geri" tuşu formu tekrar
 * göndermiyor, yenilemek ürünü ikinci kez eklemiyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/orders.php';

// ------------------------------------------------------------------- eylemler
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $action = (string)($_POST['action'] ?? '');

    switch ($action) {
        case 'voucher':
            /**
             * Kupon uygulama. Kodun kendisi oturumda tutuluyor, sepette
             * değil — sepet paylaşılabilir bir yapı, kupon ise kişiye ve
             * oturuma bağlı. Geçerlilik burada BİR KEZ, sipariş kurulurken
             * TEKRAR denetleniyor; arada fiyat değişse bile tutar doğru kalır.
             */
            require_once __DIR__ . '/inc/vouchers.php';
            $code = (string)($_POST['code'] ?? '');

            if (trim($code) === '') {
                vr_voucher_clear();
                vr_redirect('cart.php', $country !== '' ? ['country' => $country] : []);
            }
            if (!vr_rate_ok('vou', 15, 600)) {
                vr_flash(t('login_throttled'), 'err');
                vr_redirect('cart.php', $country !== '' ? ['country' => $country] : []);
            }

            $tt    = vr_cart_totals($country !== '' ? $country : null);
            $email = '';
            if (function_exists('vr_current_customer')) {
                $me = vr_current_customer();
                $email = (string)($me['email'] ?? '');
            }
            [$vok, $vcents, $verr] = vr_voucher_check($code, (int)$tt['subtotal'], PHP_INT_MAX, $email);
            if ($vok) {
                vr_voucher_set($code);
                vr_flash(t('vou_applied', ['amount' => vr_money($vcents)]), 'ok');
            } else {
                vr_voucher_clear();
                vr_flash(t($verr), 'err');
            }
            vr_redirect('cart.php', $country !== '' ? ['country' => $country] : []);

        case 'add':
            $pid  = (string)($_POST['pid'] ?? '');
            $size = (string)($_POST['size'] ?? '');
            $lot  = (string)($_POST['lot'] ?? '');
            $qty  = (int)($_POST['qty'] ?? 1);

            if ($size === '' && $lot === '') {
                // JS kapalıysa beden hatası sunucuda yakalanır.
                vr_redirect('product.php', ['id' => $pid, 'nosize' => 1]);
            }

            [$ok, $msg] = vr_cart_add($pid, $size, $qty, $lot);
            vr_flash(t($msg), $ok ? 'ok' : 'err');

            // Sepete eklendikten sonra kullanıcıyı sepette bırakıyoruz: çok
            // adımlı akışta "eklendi mi?" belirsizliği en sık kaybedilen yer.
            vr_redirect('cart.php');
            // no break — vr_redirect exit ediyor

        case 'qty':
            vr_cart_set_qty((string)($_POST['key'] ?? ''), (int)($_POST['qty'] ?? 1));
            vr_redirect('cart.php');

        case 'remove':
            vr_cart_remove((string)($_POST['key'] ?? ''));
            vr_redirect('cart.php');

        case 'clear':
            vr_cart_clear();
            vr_redirect('cart.php');
    }
    vr_redirect('cart.php');
}

// --------------------------------------------------------------------- sayfa
if (!empty($_GET['cancelled'])) {
    vr_flash(t('order_cancelled'), 'info');
    vr_redirect('cart.php');
}

$country = strtoupper(trim((string)($_GET['country'] ?? '')));
$allowed = vr_shipping_countries();
if ($country !== '' && !in_array($country, $allowed, true)) $country = '';

$t = vr_cart_totals($country !== '' ? $country : null);
foreach ($t['messages'] as $m) vr_flash($m, 'err');

vr_layout_start(['title' => t('cart_title'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_flash_render(); ?>

    <div class="sechead">
      <div>
        <h1 class="sechead__t"><?= te('cart_title') ?></h1>
        <?php if ($t['count'] > 0): ?>
          <p class="sechead__s"><?= (int)$t['count'] === 1 ? te('result_1') : te('results_n', ['n' => (int)$t['count']]) ?></p>
        <?php endif; ?>
      </div>
      <a class="sechead__more" href="<?= h(vr_url('shop.php')) ?>"><?= te('keep_shopping') ?><?= vr_icon('arrow', 16) ?></a>
    </div>

    <?php if (!$t['lines']): ?>
      <div class="notice"><strong><?= te('cart_empty') ?></strong></div>
      <a class="btn" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('cart_empty_cta') ?></span></a>
      <?php /* Boş sepet bir çıkmaz olmamalı: dört parça koyuyoruz ki ziyaretçi
               "mağazaya dön" düğmesini aramak zorunda kalmasın. */ ?>
      <?php vr_empty_suggestions(); ?>

    <?php else: ?>
      <div class="cart">
        <!-- ------------------------------------------------------- satırlar -->
        <div>
          <?php if ((int)$t['sellers'] > 1): ?>
            <div class="notice"><?= te('cart_multi_seller', ['n' => (int)$t['sellers']]) ?></div>
          <?php endif; ?>

          <?php foreach ($t['lines'] as $ln): ?>
            <div class="citem">
              <a class="citem__img" href="<?= h($ln['url']) ?>">
                <img src="<?= h(vr_img((string)$ln['image'], 300)) ?>" alt="" loading="lazy" width="200" height="250">
              </a>

              <div>
                <p class="citem__brand"><?= h($ln['brand']) ?></p>
                <p class="citem__name"><a href="<?= h($ln['url']) ?>"><?= h($ln['name']) ?></a></p>
                <p class="citem__meta">
                  <?php if ($ln['size'] !== 'ONE'): ?><?= te('size') ?> <?= h($ln['size']) ?> · <?php endif; ?>
                  <?php if ($ln['sku'] !== ''): ?><?= h($ln['sku']) ?> · <?php endif; ?>
                  <?= te('sold_by') ?> <?= h($ln['seller_name']) ?>
                  <?php if ($ln['seller_type'] === 'private'): ?>
                    <span class="pill" style="margin-left:6px"><?= te('seller_private') ?></span>
                  <?php endif; ?>
                  <?php if ($ln['vault']): ?>
                    <span class="pill pill--brass" style="margin-left:6px">VAULT</span>
                  <?php endif; ?>
                </p>

                <?php if (!$ln['vault']): ?>
                  <form method="post" action="<?= h(vr_url('cart.php')) ?>" style="margin-top:10px">
                    <?= vr_csrf_field() ?>
                    <input type="hidden" name="action" value="qty">
                    <input type="hidden" name="key" value="<?= h($ln['key']) ?>">
                    <span class="qty">
                      <button type="button" data-qty="down" aria-label="−">−</button>
                      <label class="vh" for="q-<?= h(md5($ln['key'])) ?>"><?= te('qty') ?></label>
                      <input id="q-<?= h(md5($ln['key'])) ?>" type="number" name="qty" value="<?= (int)$ln['qty'] ?>" min="1" max="10" inputmode="numeric">
                      <button type="button" data-qty="up" aria-label="+">+</button>
                    </span>
                    <noscript><button class="xlink" type="submit" style="margin-left:8px"><?= te('update') ?></button></noscript>
                  </form>
                <?php endif; ?>
              </div>

              <div class="citem__right">
                <span class="citem__price"><?= h(vr_money((int)$ln['total_cents'])) ?></span>
                <?php if ((int)$ln['qty'] > 1): ?>
                  <span class="citem__meta"><?= (int)$ln['qty'] ?> × <?= h(vr_money((int)$ln['unit_cents'])) ?></span>
                <?php endif; ?>
                <form method="post" action="<?= h(vr_url('cart.php')) ?>">
                  <?= vr_csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="key" value="<?= h($ln['key']) ?>">
                  <button class="xlink" type="submit"><?= te('remove') ?></button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- --------------------------------------------------------- özet -->
        <aside class="summary">
          <h2><?= te('order_summary') ?></h2>

          <div class="srow"><span><?= te('subtotal') ?></span><span><?= h(vr_money((int)$t['subtotal'])) ?></span></div>

          <!-- Kargo tutarını ödeme öncesi göstermek zorunlu (PAngV §6):
               ülkeyi burada soruyoruz, kasada sabitliyoruz. -->
          <form method="get" action="<?= h(vr_url('cart.php')) ?>" class="field" style="gap:6px">
            <label for="country"><?= te('checkout_country') ?></label>
            <select class="select" id="country" name="country" style="width:100%" data-autosubmit>
              <option value=""><?= te('shipping_at_checkout') ?></option>
              <?php foreach ($allowed as $c): ?>
                <option value="<?= h($c) ?>" <?= $country === $c ? 'selected' : '' ?>><?= h($c) ?></option>
              <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn--ghost btn--sm" type="submit"><span><?= te('apply') ?></span></button></noscript>
          </form>

          <div class="srow">
            <span><?= te('shipping') ?></span>
            <span><?php
              if ($t['shipping'] === null) echo te('shipping_at_checkout');
              elseif ((int)$t['ship_cents'] === 0) echo te('shipping_free');
              else echo h(vr_money((int)$t['ship_cents']));
            ?></span>
          </div>

          <?php if ($t['shipping'] !== null && (int)$t['ship_cents'] > 0 && (int)$t['shipping']['free_over'] > 0): ?>
            <div class="srow srow--muted">
              <span><?= te('free_ship_over', ['amount' => vr_money((int)$t['shipping']['free_over'])]) ?></span>
            </div>
          <?php endif; ?>

          <?php
          // ---- Gutschein
          require_once __DIR__ . '/inc/vouchers.php';
          $vcode = vr_voucher_current();
          $vcut  = 0;
          if ($vcode !== '') {
              $vmail = '';
              if (function_exists('vr_current_customer')) {
                  $vme = vr_current_customer();
                  $vmail = (string)($vme['email'] ?? '');
              }
              [$vv, $vcut] = vr_voucher_check($vcode, (int)$t['subtotal'], PHP_INT_MAX, $vmail);
              if (!$vv) { $vcut = 0; vr_voucher_clear(); $vcode = ''; }
          }
          ?>
          <?php if ($vcut > 0): ?>
            <div class="srow srow--cut">
              <span><?= te('vou_line', ['code' => h($vcode)]) ?></span>
              <span>−<?= h(vr_money($vcut)) ?></span>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= h(vr_url('cart.php')) ?>" class="vou">
            <?= vr_csrf_field() ?>
            <input type="hidden" name="action" value="voucher">
            <label class="vh" for="vou"><?= te('vou_label') ?></label>
            <input id="vou" name="code" type="text" autocomplete="off" spellcheck="false"
                   placeholder="<?= te('vou_label') ?>" value="<?= h($vcode) ?>">
            <button class="btn btn--ghost btn--sm" type="submit">
              <span><?= $vcode !== '' ? te('vou_remove') : te('apply') ?></span>
            </button>
          </form>

          <div class="srow srow--total">
            <span><?= te('total') ?></span><span><?= h(vr_money(max(0, (int)$t['total'] - $vcut))) ?></span>
          </div>
          <?php $vatShown = $vcut > 0 ? vr_vat_part(max(0, (int)$t['total'] - $vcut)) : (int)$t['vat']; ?>
          <?php if ($vatShown > 0): ?>
            <div class="srow srow--muted"><span><?= te('vat_of_which', ['amount' => vr_money($vatShown)]) ?></span></div>
          <?php endif; ?>

          <a class="btn btn--block btn--lg" href="<?= h(vr_url('checkout.php', $country !== '' ? ['country' => $country] : [])) ?>">
            <span><?= te('to_checkout') ?></span><?= vr_icon('arrow', 16) ?>
          </a>

          <p class="srow--muted" style="display:block"><?= vr_icon('lock', 13) ?> <?= te('checkout_stripe') ?></p>

          <?php if (!empty($t['has_private'])): ?>
            <p class="srow--muted" style="display:block;border-left:2px solid var(--brass);padding-left:10px">
              <?= te('checkout_private_ack') ?>
            </p>
          <?php endif; ?>
        </aside>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php vr_layout_end(); ?>
