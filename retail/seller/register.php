<?php
/**
 * Satıcı kaydı (Anmeldung)
 * ------------------------
 * Tek adım: e-posta + şifre + satıcı tipi. Stripe onboarding'i BİLİNÇLİ olarak
 * ikinci adıma bırakıyoruz — kimlik doğrulama akışı uzun, kaydın ortasında
 * kullanıcıyı Stripe'a atmak en çok terk edilen yer. Hesap açılır, panel
 * gösterilir, sonra "ödemeleri bağla" adımına yönlendirilir.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/orders.php';

if (vr_current_seller() !== null) vr_redirect('seller/index.php');

$errors = [];
$in     = ['email' => '', 'name' => '', 'company' => '', 'seller_type' => 'private', 'country' => 'DE', 'vat' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();

    foreach ($in as $k => $_) $in[$k] = trim((string)($_POST[$k] ?? ''));
    $pw  = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');

    if (!vr_rate_ok('register', 8, 900))                       $errors['rate'] = t('login_throttled');
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL))      $errors['email'] = t('email_invalid');
    if (mb_strlen($pw) < 10)                                   $errors['password'] = t('pw_too_short');
    if ($pw !== $pw2)                                          $errors['password2'] = t('pw_mismatch');
    if ($in['name'] === '')                                    $errors['name'] = t('required_field');
    if ($in['seller_type'] === 'business' && $in['company'] === '') $errors['company'] = t('required_field');
    if (empty($_POST['terms']))                                $errors['terms'] = t('must_accept');

    if (!$errors) {
        [$ok, $res] = vr_seller_create($in + ['password' => $pw]);

        if (!$ok) {
            $errors['email'] = t(is_string($res) ? $res : 'email_taken');
        } else {
            vr_seller_login($in['email'], $pw);
            vr_flash(t('welcome_seller'), 'ok');
            vr_redirect('seller/payouts.php');
        }
    }
}

vr_layout_start(['title' => t('register') . ' · ' . t('nav_sell'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <?php vr_breadcrumbs([t('nav_sell') => vr_url('sell.php'), t('register') => null]); ?>

    <h1 class="sechead__t"><?= te('sell_cta') ?></h1>
    <p class="sechead__s" style="margin:12px 0 30px"><?= te('sell_hero_b') ?></p>

    <?php if ($errors): ?>
      <div class="flash flash--err" style="margin-bottom:18px"><?= te('required_field') ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= h(vr_url('seller/register.php')) ?>">
      <?= vr_csrf_field() ?>

      <fieldset style="border:0;padding:0;margin:0">
        <legend class="field" style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted);margin-bottom:10px">
          <?= te('seller_type') ?>
        </legend>
        <div class="radios">
          <label class="radio">
            <input type="radio" name="seller_type" value="business" <?= $in['seller_type'] === 'business' ? 'checked' : '' ?>>
            <span>
              <b><?= te('sell_biz_t') ?></b>
              <span><?= te('sell_biz_b') ?></span>
            </span>
          </label>
          <label class="radio">
            <input type="radio" name="seller_type" value="private" <?= $in['seller_type'] === 'private' ? 'checked' : '' ?>>
            <span>
              <b><?= te('sell_priv_t') ?></b>
              <span><?= te('sell_priv_b') ?></span>
            </span>
          </label>
        </div>
      </fieldset>

      <div class="field<?= isset($errors['name']) ? ' field--err' : '' ?>">
        <label for="name"><?= te('your_name') ?></label>
        <input id="name" name="name" required autocomplete="name" value="<?= h($in['name']) ?>">
        <?php if (isset($errors['name'])): ?><p class="field__err"><?= h($errors['name']) ?></p><?php endif; ?>
      </div>

      <!-- Firma adı yalnızca Händler için zorunlu; Privatverkäufer boş bırakır. -->
      <div class="field<?= isset($errors['company']) ? ' field--err' : '' ?>">
        <label for="company"><?= te('company_name') ?></label>
        <input id="company" name="company" autocomplete="organization" value="<?= h($in['company']) ?>">
        <small><?= te('sell_biz_t') ?>: <?= te('required_field') ?></small>
        <?php if (isset($errors['company'])): ?><p class="field__err"><?= h($errors['company']) ?></p><?php endif; ?>
      </div>

      <div class="field<?= isset($errors['email']) ? ' field--err' : '' ?>">
        <label for="email"><?= te('email') ?></label>
        <input id="email" name="email" type="email" required autocomplete="email" value="<?= h($in['email']) ?>">
        <?php if (isset($errors['email'])): ?><p class="field__err"><?= h($errors['email']) ?></p><?php endif; ?>
      </div>

      <div class="field<?= isset($errors['password']) ? ' field--err' : '' ?>">
        <label for="password"><?= te('password') ?></label>
        <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
        <small><?= te('pw_too_short') ?></small>
        <?php if (isset($errors['password'])): ?><p class="field__err"><?= h($errors['password']) ?></p><?php endif; ?>
      </div>

      <div class="field<?= isset($errors['password2']) ? ' field--err' : '' ?>">
        <label for="password2"><?= te('password_again') ?></label>
        <input id="password2" name="password2" type="password" required autocomplete="new-password">
        <?php if (isset($errors['password2'])): ?><p class="field__err"><?= h($errors['password2']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="country"><?= te('country') ?></label>
        <select id="country" name="country">
          <?php
          // Stripe Connect Express'in desteklediği ülkelerden, bizim sattığımız
          // pazarlar. Ülke sonradan değiştirilemez (Stripe kuralı), o yüzden
          // burada doğru seçilmesi önemli.
          foreach (['DE','AT','CH','NL','BE','FR','IT','ES','DK','SE','NO','FI','PL','CZ','PT','IE','GB','US'] as $c): ?>
            <option value="<?= h($c) ?>" <?= $in['country'] === $c ? 'selected' : '' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
        <small><?= te('seller_country_hint') ?></small>
      </div>

      <div class="field">
        <label for="vat"><?= te('vat_optional') ?></label>
        <input id="vat" name="vat" value="<?= h($in['vat']) ?>" placeholder="DE123456789">
      </div>

      <label class="check<?= isset($errors['terms']) ? ' field--err' : '' ?>">
        <input type="checkbox" name="terms" value="1" required>
        <span><?php
          echo strtr(te('agree_seller_terms'), []) . ' ';
          echo '<a class="link" href="' . h(vr_url('legal/verkaeufer.php')) . '" target="_blank" rel="noopener">' . te('legal_seller_terms') . '</a>';
          echo ' · <a class="link" href="' . h(vr_url('legal/datenschutz.php')) . '" target="_blank" rel="noopener">' . te('legal_privacy') . '</a>';
        ?></span>
      </label>

      <button class="btn btn--lg" type="submit"><span><?= te('register') ?></span><?= vr_icon('arrow', 16) ?></button>

      <p style="font-size:13px;color:var(--muted)">
        <?= te('have_account') ?>
        <a class="link" href="<?= h(vr_url('seller/login.php')) ?>"><?= te('login') ?></a>
      </p>
    </form>
  </div>
</section>

<?php vr_layout_end(); ?>
