<?php
/**
 * Kasa — Stripe Checkout'a devir
 * ------------------------------
 * Kart verisi hiçbir zaman bize gelmiyor: ödeme sayfası Stripe'ta açılıyor
 * (SCA/3DS, PCI kapsamı, cüzdanlar Stripe tarafında). Bizim işimiz siparişi
 * doğru kurmak, tutarı SUNUCUDA hesaplamak ve doğru Connect akışını seçmek.
 *
 * Akış:
 *   GET  → özet + TESLİMAT ADRESİ + onay ekranı (AGB/Widerruf onayı burada,
 *          Stripe'ta değil: böylece metnin ne zaman kabul edildiğini biz
 *          kaydediyoruz)
 *   POST → adres doğrulanır (eksikse Stripe'a GİDİLMEZ) → sipariş "pending"
 *          yazılır → Checkout Session → Stripe'a 303
 *
 * Teslimat adresi Stripe'a geçmeden ÖNCE zorunlu: ad, sokak + numara, posta
 * kodu, şehir. Hesabında tam teslimat adresi olan müşteriye yeniden
 * sorulmaz: kayıtlı adres kartta gösterilir, isterse "başka adres" açar.
 * Misafir elle yazar. Fatura adresi isteğe bağlı ("teslimatla aynı" varsayılan).
 * Adres siparişe customer.address / customer.billing olarak yazılır (webhook,
 * panel, e-posta ve hesap sayfası aynı alan adlarını okur).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/orders.php';
require_once __DIR__ . '/inc/customers.php';

$me  = vr_current_customer();
$acc = (array)($me['address'] ?? []);

$allowed = vr_shipping_countries();
$posted  = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// ------------------------------------------------- hesaptaki teslimat adresi
// Tam ise (ad, sokak, PLZ, şehir, geçerli ülke) müşteriye yeniden sorulmaz.
$savedAddr = null;
if ($me !== null) {
    $cand = [
        'name'    => trim((string)($acc['name'] ?? '')) !== '' ? trim((string)$acc['name']) : trim((string)($me['name'] ?? '')),
        'street'  => trim((string)($acc['street'] ?? '')),
        'line2'   => trim((string)($acc['line2'] ?? '')),
        'zip'     => trim((string)($acc['zip'] ?? '')),
        'city'    => trim((string)($acc['city'] ?? '')),
        'country' => strtoupper(trim((string)($acc['country'] ?? ''))),
        'phone'   => trim((string)($me['phone'] ?? '')),
    ];
    if ($cand['name'] !== '' && $cand['street'] !== '' && $cand['zip'] !== '' && $cand['city'] !== ''
        && in_array($cand['country'], $allowed, true)) {
        $savedAddr = $cand;
    }
}
// Kayıtlı adres kullanılıyor mu? İlk açılışta evet; formda kutuyu kaldırırsa hayır.
$useSaved = $savedAddr !== null && (!$posted || !empty($_POST['ship_saved']));

$country = strtoupper(trim((string)($_POST['country'] ?? $_GET['country'] ?? '')));
if ($useSaved)         $country = $savedAddr['country'];       // kargo kayıtlı ülkeye göre
elseif ($country === '') $country = strtoupper((string)($acc['country'] ?? ''));
if (!in_array($country, $allowed, true)) $country = 'DE';

// ----------------------------------------------------------- teslimat adresi
// Formdan gelen değer öncelikli; form gelmediyse hesaptaki adres (varsa).
$in = static function (string $k, int $max, string $default): string {
    if (!isset($_POST[$k])) return $default;
    return mb_substr(trim((string)$_POST[$k]), 0, $max);
};
$phoneClean = static fn(string $v): string => mb_substr((string)preg_replace('/[^0-9+()\s\/-]/', '', $v), 0, 32);

if ($useSaved) {
    $addr = $savedAddr;
    unset($addr['country']);
} else {
    $addr = [
        'name'   => $in('ship_name',   120, trim((string)($acc['name'] ?? '')) !== '' ? (string)$acc['name'] : (string)($me['name'] ?? '')),
        'street' => $in('ship_street', 160, (string)($acc['street'] ?? '')),
        'line2'  => $in('ship_line2',  160, (string)($acc['line2'] ?? '')),
        'zip'    => $in('ship_zip',     20, (string)($acc['zip'] ?? '')),
        'city'   => $in('ship_city',   120, (string)($acc['city'] ?? '')),
        // Telefon isteğe bağlı: yalnız teslimatla ilgili sorular için (acc_phone_note).
        'phone'  => $phoneClean($in('ship_phone', 40, (string)($me['phone'] ?? ''))),
    ];
}
$addrMissing = [];

// ------------------------------------------------------------ fatura adresi
// İsteğe bağlı. Varsayılan "teslimatla aynı"; hesapta ayrı fatura adresi
// kayıtlıysa o önceden dolu ve açık gelir.
$accBill  = (array)($me['billing'] ?? []);
$billSame = $posted
    ? !empty($_POST['bill_same'])
    : ($me === null || !isset($me['billing_same']) || !empty($me['billing_same']) || trim((string)($accBill['street'] ?? '')) === '');
$bc   = strtoupper(substr($in('b_country', 2, (string)($accBill['country'] ?? '')), 0, 2));
$bill = [
    'name'    => $in('b_name',    120, (string)($accBill['name'] ?? '')),
    'company' => $in('b_company', 120, (string)($accBill['company'] ?? '')),
    'street'  => $in('b_street',  160, (string)($accBill['street'] ?? '')),
    'line2'   => $in('b_line2',   160, (string)($accBill['line2'] ?? '')),
    'zip'     => $in('b_zip',      20, (string)($accBill['zip'] ?? '')),
    'city'    => $in('b_city',    120, (string)($accBill['city'] ?? '')),
    'country' => in_array($bc, $allowed, true) ? $bc : $country,
    'vat'     => mb_substr(strtoupper((string)preg_replace('/\s+/', '', $in('b_vat', 30, (string)($accBill['vat'] ?? '')))), 0, 20),
];
$billMissing = [];

$t = vr_cart_totals($country);
if (!$t['lines']) {
    // Flash YOK: sepet sayfası boş sepeti zaten kendi söylüyor. İkisi birden
    // aynı cümleyi alt alta iki kez basıyordu.
    vr_redirect('cart.php');
}

$errors = [];

// --------------------------------------------------------------- POST: öde
// Ülke seçimi değişince form kendini onaysız yeniden gönderir (data-autosubmit,
// düğme değeri gelmez): o zaman yalnız kargo yeniden hesaplanır, doğrulama ve
// ödeme yalnız "intent=pay" ile çalışır.
$paying = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $paying = (string)($_POST['intent'] ?? '') === 'pay';
}

if ($paying) {
    // Teslimat adresi: bu dört alan olmadan Stripe'a geçilmez.
    foreach (['name', 'street', 'zip', 'city'] as $k) {
        if ($addr[$k] === '') $addrMissing[$k] = true;
    }
    if ($addrMissing)                                 $errors['address'] = t('checkout_addr_missing');
    if (!$billSame) {
        foreach (['name', 'street', 'zip', 'city'] as $k) {
            if ($bill[$k] === '') $billMissing[$k] = true;
        }
        if ($billMissing)                             $errors['billing'] = t('checkout_bill_missing');
    }
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

        // Teslimat adresi siparişe burada yazılır — Stripe'tan gelmesi
        // beklenmez. Alan adları webhook/panel/e-posta ile aynı
        // (name, line1, line2, postal_code, city, country).
        $order['customer'] = [
            'email'   => (string)($me['email'] ?? ''),      // misafirde boş; Stripe tamamlar
            'name'    => $addr['name'],
            'phone'   => $addr['phone'],
            'address' => [
                'name'        => $addr['name'],
                'line1'       => $addr['street'],
                'line2'       => $addr['line2'],
                'postal_code' => $addr['zip'],
                'city'        => $addr['city'],
                'country'     => $country,
            ],
            'billing_same' => $billSame,
            'billing'      => $billSame ? [] : [
                'name'        => $bill['name'],
                'company'     => $bill['company'],
                'line1'       => $bill['street'],
                'line2'       => $bill['line2'],
                'postal_code' => $bill['zip'],
                'city'        => $bill['city'],
                'country'     => $bill['country'],
                'vat'         => $bill['vat'],
            ],
        ];
        $order['address_source'] = $useSaved ? 'account' : 'checkout';

        // Girişli müşterinin hesabında adres yoksa kasada yazdığını kaydet:
        // bir sonraki alışverişte sorulmasın. Kayıtlı olan EZİLMEZ.
        if ($me !== null) {
            $patch = [];
            if (trim((string)($acc['street'] ?? '')) === '') {
                $patch['address'] = [
                    'name'    => $addr['name'],
                    'company' => (string)($acc['company'] ?? ''),
                    'street'  => $addr['street'],
                    'line2'   => $addr['line2'],
                    'zip'     => $addr['zip'],
                    'city'    => $addr['city'],
                    'country' => $country,
                ];
            }
            if (!$billSame && trim((string)($accBill['street'] ?? '')) === '') {
                $patch['billing']      = $bill;
                $patch['billing_same'] = false;
            }
            if (trim((string)($me['phone'] ?? '')) === '' && $addr['phone'] !== '') $patch['phone'] = $addr['phone'];
            if ($patch) vr_customer_update((string)$me['id'], $patch);
        }

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

    <?php foreach ($errors as $e): ?>
      <div class="flash flash--err" style="margin-bottom:12px"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="cart">
      <!-- ------------------------------------------------------- onay formu -->
      <div>
        <?php if (!vr_stripe_ready()): ?>
      <?php
        // Ödeme anahtarı yokken müşteriye işletme dili değil, hizmet dili:
        // sepet saklanıyor, parça e-postayla ayırtılabiliyor. Konu satırı ve
        // gövde sepetteki parçaların artikel numaralarıyla hazır geliyor.
        $resv = [];
        foreach (vr_cart_lines()['lines'] as $ln) {
            $rp = vr_product((string)($ln['pid'] ?? ''));
            if (!$rp) continue;
            $resv[] = '• ' . vr_brand_label((string)$rp['brand']) . ' ' . vr_card_name($rp) . ' — ' . vr_article_no($rp)
                . (($ln['size'] ?? '') !== '' && $ln['size'] !== 'ONE' ? ' — ' . $ln['size'] : '')
                . (($ln['colour'] ?? '') !== '' ? ' — ' . vr_colour_label((string)$ln['colour']) : '');
        }
        $mail = (string)(vr_config('company')['email'] ?? '');
        $href = 'mailto:' . $mail . '?subject=' . rawurlencode(t('pay_reserve_btn') . ' · ' . vr_config('brand'))
              . '&body=' . rawurlencode(implode("\n", $resv) . "\n\n");
      ?>
      <div class="notice notice--soon">
        <strong><?= te('pay_unavailable') ?></strong>
        <p><?= te('pay_unavailable_b') ?></p>
        <?php if ($mail !== ''): ?>
          <a class="btn btn--ghost" href="<?= h($href) ?>"><span><?= te('pay_reserve_btn') ?></span><?= vr_icon('arrow', 16) ?></a>
        <?php endif; ?>
      </div>
        <?php else: ?>
        <?php
          // novalidate: ülke seçimi data-autosubmit ile formu gönderiyor; tarayıcı
          // doğrulaması o anda boş adres alanlarına takılmasın. Zorunluluk
          // sunucuda denetlenir (yukarıda), eksikse Stripe'a geçilmez.
          $fe = static fn(array $miss, string $k): string => isset($miss[$k]) ? ' field--err' : '';
          $countryOpts = vr_country_options($allowed);
          /** Adres bloğu alanları ($p: ship_ ya da b_). */
          $addrFields = static function (string $p, array $a, array $miss, string $cc, array $opts, bool $extra) use ($fe): void {
              $ac = $p === 'b_' ? 'billing' : 'shipping';
          ?>
            <div class="field<?= $fe($miss, 'name') ?>">
              <label for="<?= $p ?>name"><?= te('acc_addr_name') ?></label>
              <input id="<?= $p ?>name" name="<?= $p ?>name" type="text" required autocomplete="<?= $ac ?> name" value="<?= h((string)$a['name']) ?>">
            </div>
            <?php if ($extra): ?>
            <div class="field">
              <label for="<?= $p ?>company"><?= te('acc_company') ?></label>
              <input id="<?= $p ?>company" name="<?= $p ?>company" type="text" autocomplete="<?= $ac ?> organization" value="<?= h((string)$a['company']) ?>">
            </div>
            <?php endif; ?>
            <div class="field<?= $fe($miss, 'street') ?>">
              <label for="<?= $p ?>street"><?= te('acc_street') ?></label>
              <input id="<?= $p ?>street" name="<?= $p ?>street" type="text" required autocomplete="<?= $ac ?> address-line1" value="<?= h((string)$a['street']) ?>">
            </div>
            <div class="field">
              <label for="<?= $p ?>line2"><?= te('acc_addr_line2') ?></label>
              <input id="<?= $p ?>line2" name="<?= $p ?>line2" type="text" autocomplete="<?= $ac ?> address-line2" value="<?= h((string)$a['line2']) ?>">
            </div>
            <div class="fieldrow">
              <div class="field<?= $fe($miss, 'zip') ?>">
                <label for="<?= $p ?>zip"><?= te('acc_zip') ?></label>
                <input id="<?= $p ?>zip" name="<?= $p ?>zip" type="text" required autocomplete="<?= $ac ?> postal-code" value="<?= h((string)$a['zip']) ?>">
              </div>
              <div class="field<?= $fe($miss, 'city') ?>">
                <label for="<?= $p ?>city"><?= te('acc_city') ?></label>
                <input id="<?= $p ?>city" name="<?= $p ?>city" type="text" required autocomplete="<?= $ac ?> address-level2" value="<?= h((string)$a['city']) ?>">
              </div>
            </div>
            <div class="field">
              <label for="<?= $p ?>country"><?= $p === 'b_' ? te('country') : te('checkout_country') ?></label>
              <select id="<?= $p ?>country" name="<?= $p ?>country" autocomplete="<?= $ac ?> country"<?= $p === 'b_' ? '' : ' data-autosubmit' ?>>
                <?php foreach ($opts as $c => $name): ?>
                  <option value="<?= h($c) ?>"<?= $cc === $c ? ' selected' : '' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php };
        ?>
        <form class="form form--wide ckform" method="post" action="<?= h(vr_url('checkout.php')) ?>" novalidate>
          <?= vr_csrf_field() ?>

          <!-- ------------------------------------------- 1 · teslimat adresi -->
          <section class="ckcard<?= isset($errors['address']) ? ' ckcard--err' : '' ?>" aria-labelledby="ck-ship-h">
            <header class="ckcard__h">
              <h2 id="ck-ship-h"><?= vr_icon('truck', 18) ?> <?= te('acc_addr_ship') ?></h2>
              <span class="ckcard__step">1 / 3</span>
            </header>

            <?php if ($savedAddr !== null): ?>
              <!-- Kayıtlı adres: yeniden sorulmaz, kart olarak gösterilir. -->
              <div class="ckaddr">
                <?= vr_icon('check', 16) ?>
                <p>
                  <strong><?= h($savedAddr['name']) ?></strong><br>
                  <?= h($savedAddr['street']) ?><br>
                  <?php if ($savedAddr['line2'] !== ''): ?><?= h($savedAddr['line2']) ?><br><?php endif; ?>
                  <?= h($savedAddr['zip'] . ' ' . $savedAddr['city']) ?><br>
                  <?= h(vr_country_name($savedAddr['country'])) ?>
                </p>
                <a class="xlink" href="<?= h(vr_url('account/addresses.php')) ?>"><?= te('checkout_addr_manage') ?></a>
              </div>
              <label class="switch">
                <input type="checkbox" name="ship_saved" value="1" data-toggle-target="#shipfields"<?= $useSaved ? ' checked' : '' ?>>
                <span class="switch__ui" aria-hidden="true"></span><span><?= te('checkout_addr_keep') ?></span>
              </label>
              <p class="ckcard__lead"><?= te('checkout_addr_other') ?></p>
            <?php else: ?>
              <p class="ckcard__lead"><?= te('checkout_addr_lead') ?></p>
            <?php endif; ?>

            <div id="shipfields" class="ckfields"<?= $useSaved ? ' hidden' : '' ?>>
              <?php $addrFields('ship_', $addr, $addrMissing, $country, $countryOpts, false); ?>
              <p class="ckcard__lead" style="margin-top:-6px"><?= h(vr_zone_label((string)$ship['zone'])) ?> · <?= te('ships_in', ['days' => (string)$ship['days']]) ?></p>
              <div class="field">
                <label for="ship_phone"><?= te('acc_phone') ?></label>
                <input id="ship_phone" name="ship_phone" type="tel" autocomplete="shipping tel" value="<?= h((string)$addr['phone']) ?>">
                <small><?= te('acc_phone_note') ?></small>
              </div>
            </div>
            <?php if ($useSaved): ?>
              <p class="ckcard__lead"><?= h(vr_zone_label((string)$ship['zone'])) ?> · <?= te('ships_in', ['days' => (string)$ship['days']]) ?></p>
            <?php endif; ?>
          </section>

          <!-- -------------------------------------------- 2 · fatura adresi -->
          <section class="ckcard<?= isset($errors['billing']) ? ' ckcard--err' : '' ?>" aria-labelledby="ck-bill-h">
            <header class="ckcard__h">
              <h2 id="ck-bill-h"><?= vr_icon('doc', 18) ?> <?= te('acc_addr_bill') ?></h2>
              <span class="ckcard__step">2 / 3</span>
            </header>
            <label class="switch">
              <input type="checkbox" name="bill_same" value="1" data-toggle-target="#billfields"<?= $billSame ? ' checked' : '' ?>>
              <span class="switch__ui" aria-hidden="true"></span><span><?= te('acc_addr_same') ?></span>
            </label>
            <p class="ckcard__lead"><?= te('checkout_bill_lead') ?></p>
            <div id="billfields" class="ckfields"<?= $billSame ? ' hidden' : '' ?>>
              <?php $addrFields('b_', $bill, $billMissing, $bill['country'], $countryOpts, true); ?>
              <div class="field">
                <label for="b_vat"><?= te('acc_vat') ?></label>
                <input id="b_vat" name="b_vat" type="text" autocomplete="off" value="<?= h((string)$bill['vat']) ?>">
              </div>
            </div>
          </section>

          <!-- ------------------------------------------------- 3 · onay + öde -->
          <section class="ckcard" aria-labelledby="ck-pay-h">
            <header class="ckcard__h">
              <h2 id="ck-pay-h"><?= vr_icon('lock', 18) ?> <?= te('checkout_step_pay') ?></h2>
              <span class="ckcard__step">3 / 3</span>
            </header>

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

          <button class="btn btn--lg" type="submit" name="intent" value="pay" <?= vr_stripe_ready() ? '' : 'disabled' ?>>
            <span><?= te('checkout_go') ?></span><?= vr_icon('arrow', 16) ?>
          </button>

          <!-- START: ACERASOFT HOLDING SECURE PAYMENT NOTICE -->
          <div id="billing-notice" style="margin-top: 15px; padding: 12px; border: 1px solid #e2ddd2; background: #fdfcf7; font-size: 12px; line-height: 1.5; color: #666; text-align: left; font-family: sans-serif; border-radius: 4px;">
              <?php
                // Metin dil dosyalarında (billing_notice, billing_notice_t), şirket
                // adı Impressum ile aynı kaynaktan: vr_config('company')['legal_name'].
                $billCo = '<strong>' . h(ucfirst((string)(vr_config('company')['legal_name'] ?? 'Acerasoft LLC'))) . '</strong>';
              ?>
              <strong><?= te('billing_notice_t') ?></strong> <?= str_replace('%C%', $billCo, te('billing_notice')) ?>
          </div>
          <!-- END: ACERASOFT HOLDING SECURE PAYMENT NOTICE -->

          <p style="font-size:12px;color:var(--muted)">
            <?= vr_icon('lock', 13) ?> <?= te('checkout_stripe') ?>
            <?php if (vr_stripe_settings()['mode'] === 'test'): ?>
              <br><?= te('checkout_test_mode') ?>
            <?php endif; ?>
          </p>
          </section>
        </form>
        <?php endif; ?>
      </div>

      <!-- ------------------------------------------------------------ özet -->
      <aside class="summary">
        <h2><?= te('order_summary') ?></h2>

        <?php foreach ($t['lines'] as $ln): ?>
          <div class="srow">
            <span style="max-width:64%">
              <?= h($ln['brand']) ?> <?= h($ln['name']) ?>
              <?php
              $meta = [];
              if (($ln['colour'] ?? '') !== '') $meta[] = te('colour') . ' ' . h(vr_colour_label((string)$ln['colour']));
              if ($ln['size'] !== 'ONE')        $meta[] = te('size') . ' ' . h($ln['size']);
              if ($meta): ?><br><small style="color:var(--muted)"><?= implode(' · ', $meta) ?></small><?php endif; ?>
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
        <?php
        /**
         * Kasa özeti sepetle AYNI rakamı göstermek zorunda; kupon burada
         * yeniden değerlendiriliyor çünkü arada fiyat ya da kuponun durumu
         * değişmiş olabilir. Ödeme tutarını yine de sunucu vr_order_build()
         * içinde bir kez daha hesaplıyor — bu satır sadece gösterim.
         */
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
            if (!$vv) $vcut = 0;
        }
        $shownTotal = max(0, (int)$t['total'] - $vcut);
        $shownVat   = vr_config('prices_include_vat') ? vr_vat_part($shownTotal) : 0;
        ?>
        <?php if ($vcut > 0): ?>
          <div class="srow srow--cut">
            <span><?= te('vou_line', ['code' => h($vcode)]) ?></span>
            <span>−<?= h(vr_money($vcut)) ?></span>
          </div>
        <?php endif; ?>
        <div class="srow srow--total"><span><?= te('total') ?></span><span><?= h(vr_money($shownTotal)) ?></span></div>
        <?php if ($shownVat > 0): ?>
          <div class="srow srow--muted"><span><?= te('vat_of_which', ['amount' => vr_money($shownVat)]) ?></span></div>
        <?php endif; ?>

        <a class="xlink" href="<?= h(vr_url('cart.php')) ?>" style="margin-top:6px"><?= te('back') ?> · <?= te('cart_title') ?></a>
      </aside>
    </div>
  </div>
</section>

<?php vr_layout_end(); ?>
