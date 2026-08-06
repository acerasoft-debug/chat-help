<?php
/** Sıfırlama bağlantısıyla yeni şifre. Jeton tek kullanımlık, 1 saat geçerli. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';

$email = (string)($_GET['e'] ?? $_POST['e'] ?? '');
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$err = '';
$done = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $pw  = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    if (!vr_rate_ok('creset2', 10, 900)) {
        $err = t('login_throttled');
    } elseif ($pw !== $pw2) {
        $err = t('pw_mismatch');
    } else {
        [$ok, $key] = vr_customer_reset_apply($email, $token, $pw);
        if ($ok) $done = true; else $err = t($key === 'pw_too_short' ? 'pw_too_short' : 'acc_reset_fail');
    }
}

vr_layout_start(['title' => t('acc_reset_title'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <h1 class="sechead__t"><?= te('acc_reset_title') ?></h1>

    <?php if ($done): ?>
      <div class="flash flash--ok" style="margin:20px 0"><?= te('acc_reset_ok') ?></div>
      <a class="btn btn--brass" href="<?= h(vr_url('account/login.php')) ?>"><span><?= te('login') ?></span></a>
    <?php elseif ($token === ''): ?>
      <p class="sechead__s" style="margin:14px 0 24px"><?= te('acc_reset_fail') ?></p>
      <a class="btn btn--ghost" href="<?= h(vr_url('account/forgot.php')) ?>"><span><?= te('acc_forgot_title') ?></span></a>
    <?php else: ?>
      <p class="sechead__s" style="margin:12px 0 28px"><?= te('acc_reset_lead') ?></p>
      <?php if ($err !== ''): ?>
        <div class="flash flash--err" style="margin-bottom:16px"><?= h($err) ?></div>
      <?php endif; ?>
      <form class="form" method="post" action="<?= h(vr_url('account/reset.php')) ?>">
        <?= vr_csrf_field() ?>
        <input type="hidden" name="e" value="<?= h($email) ?>">
        <input type="hidden" name="t" value="<?= h($token) ?>">
        <div class="field">
          <label for="password"><?= te('acc_pw_new') ?></label>
          <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
          <small><?= te('pw_too_short') ?></small>
        </div>
        <div class="field">
          <label for="password2"><?= te('password_again') ?></label>
          <input id="password2" name="password2" type="password" required minlength="10" autocomplete="new-password">
        </div>
        <div class="field">
          <button class="btn btn--brass" type="submit"><span><?= te('acc_reset_cta') ?></span></button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php vr_layout_end(); ?>
