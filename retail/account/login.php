<?php
/** Müşteri girişi. Hız sınırı ve sabit cevap metni vr_customer_login() içinde. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';

if (vr_current_customer() !== null) vr_redirect('account/index.php');

$err = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    [$ok, $key] = vr_customer_login($email, (string)($_POST['password'] ?? ''));
    if ($ok) vr_redirect('account/index.php');
    $err = t($key);
}

vr_layout_start(['title' => t('login'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <h1 class="sechead__t"><?= te('login') ?></h1>
    <p class="sechead__s" style="margin:12px 0 28px"><?= te('acc_login_lead') ?></p>

    <?php if ($err !== ''): ?>
      <div class="flash flash--err" style="margin-bottom:16px"><?= h($err) ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= h(vr_url('account/login.php')) ?>">
      <?= vr_csrf_field() ?>
      <div class="field">
        <label for="email"><?= te('email') ?></label>
        <input id="email" name="email" type="email" required autocomplete="email" value="<?= h($email) ?>">
      </div>
      <div class="field">
        <label for="password"><?= te('password') ?></label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </div>
      <div class="field">
        <button class="btn btn--brass" type="submit"><span><?= te('login') ?></span></button>
      </div>
    </form>

    <p style="margin-top:20px;font-size:13px;color:var(--muted)">
      <a class="link" href="<?= h(vr_url('account/forgot.php')) ?>"><?= te('acc_forgot_link') ?></a>
    </p>
    <p style="margin-top:8px;font-size:13px;color:var(--muted)">
      <?= te('acc_no_account') ?>
      <a class="link" href="<?= h(vr_url('account/register.php')) ?>"><?= te('register') ?></a>
    </p>
    <hr class="rule" style="margin:26px 0">
    <p style="font-size:12.5px;color:var(--muted)">
      <?= te('acc_guest_track') ?>
      <a class="link" href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a>
    </p>
  </div>
</section>
<?php vr_layout_end(); ?>
