<?php
/** Müşteri girişi. Hız sınırı ve sabit cevap metni vr_customer_login() içinde. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';
require_once __DIR__ . '/../inc/lists.php';

if (vr_current_customer() !== null) vr_redirect('account/index.php');

$err = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    [$ok, $key] = vr_customer_login($email, (string)($_POST['password'] ?? ''));
    if ($ok) {
        // Bu cihazın merklistesi hesaptakiyle birleşir; hesabın dil tercihi
        // siteye geçer.
        $me = vr_current_customer();
        if ($me) vr_wish_merge_account($me);
        $ln = (string)($me['lang'] ?? '');
        vr_redirect('account/index.php', $ln !== '' ? ['lang' => $ln] : []);
    }
    $err = t($key);
}

vr_auth_open(t('login'), t('acc_login_lead'));
?>

    <?php if ($err !== ''): ?>
      <div class="flash flash--err accflash"><?= h($err) ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= h(vr_url('account/login.php')) ?>">
      <?= vr_csrf_field() ?>
      <div class="field">
        <label for="email"><?= te('email') ?></label>
        <input id="email" name="email" type="email" required autocomplete="email" value="<?= h($email) ?>">
      </div>
      <?php vr_pw_field('password', t('password'), 'current-password'); ?>
      <div class="field">
        <button class="btn btn--block" type="submit"><span><?= te('login') ?></span></button>
      </div>
    </form>

    <p class="auth__forgot"><a class="link" href="<?= h(vr_url('account/forgot.php')) ?>"><?= te('acc_forgot_link') ?></a></p>

    <div class="auth__or"><span><?= te('acc_no_account') ?></span></div>
    <a class="btn btn--ghost btn--block" href="<?= h(vr_url('account/register.php')) ?>"><span><?= te('acc_create') ?></span></a>

    <p class="auth__fine">
      <?= te('acc_guest_track') ?>
      <a class="link" href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a>
    </p>
<?php vr_auth_close(); ?>
