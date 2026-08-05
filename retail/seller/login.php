<?php
/** Satıcı girişi. Hız sınırı ve sabit cevap metni vr_seller_login() içinde. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';

if (vr_current_seller() !== null) vr_redirect('seller/index.php');

$err   = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    [$ok, $key] = vr_seller_login($email, (string)($_POST['password'] ?? ''));

    if ($ok) {
        $next = vr_current_seller();
        // Ödemeler bağlı değilse doğrudan o adıma götür: panelde kaybolmasın.
        vr_redirect(empty($next['stripe_account_id']) ? 'seller/payouts.php' : 'seller/index.php');
    }
    $err = t($key);
}

vr_layout_start(['title' => t('sell_login'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <h1 class="sechead__t"><?= te('sell_login') ?></h1>
    <p class="sechead__s" style="margin:12px 0 28px"><?= te('sell_hero_b') ?></p>

    <?php if ($err !== ''): ?>
      <div class="flash flash--err" style="margin-bottom:16px"><?= h($err) ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= h(vr_url('seller/login.php')) ?>">
      <?= vr_csrf_field() ?>
      <div class="field">
        <label for="email"><?= te('email') ?></label>
        <input id="email" name="email" type="email" required autocomplete="email" value="<?= h($email) ?>">
      </div>
      <div class="field">
        <label for="password"><?= te('password') ?></label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </div>
      <button class="btn btn--lg" type="submit"><span><?= te('login') ?></span></button>

      <p style="font-size:13px;color:var(--muted)">
        <?= te('no_account') ?>
        <a class="link" href="<?= h(vr_url('seller/register.php')) ?>"><?= te('register') ?></a>
      </p>
    </form>
  </div>
</section>
<?php vr_layout_end(); ?>
