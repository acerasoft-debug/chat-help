<?php
/** Sıfırlama bağlantısıyla yeni şifre. Jeton tek kullanımlık, 1 saat geçerli. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

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

vr_auth_open(t('acc_reset_title'));
?>

    <?php if ($done): ?>
      <div class="flash flash--ok accflash"><?= te('acc_reset_ok') ?></div>
      <a class="btn btn--block" href="<?= h(vr_url('account/login.php')) ?>"><span><?= te('login') ?></span></a>
    <?php elseif ($token === ''): ?>
      <p class="auth__lead"><?= te('acc_reset_fail') ?></p>
      <a class="btn btn--ghost" href="<?= h(vr_url('account/forgot.php')) ?>"><span><?= te('acc_forgot_title') ?></span></a>
    <?php else: ?>
      <p class="auth__lead"><?= te('acc_reset_lead') ?></p>
      <?php if ($err !== ''): ?>
        <div class="flash flash--err accflash"><?= h($err) ?></div>
      <?php endif; ?>
      <form class="form" method="post" action="<?= h(vr_url('account/reset.php')) ?>">
        <?= vr_csrf_field() ?>
        <input type="hidden" name="e" value="<?= h($email) ?>">
        <input type="hidden" name="t" value="<?= h($token) ?>">
        <?php vr_pw_field('password', t('acc_pw_new'), 'new-password', true, true, 10); ?>
        <?php vr_pw_field('password2', t('password_again'), 'new-password', false, true, 10); ?>
        <div class="field">
          <button class="btn btn--block" type="submit"><span><?= te('acc_reset_cta') ?></span></button>
        </div>
      </form>
    <?php endif; ?>
<?php vr_auth_close(); ?>
