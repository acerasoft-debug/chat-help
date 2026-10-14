<?php
/**
 * Müşteri kaydı.
 *
 * Sonuç mesajı BİLEREK nötr: adres kayıtlı olsa da olmasa da aynı ekran
 * çıkıyor. Aksi hâlde form bir "bu e-posta bizde var mı" sorgulama aracına
 * dönerdi (hesap sayımı / account enumeration).
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';
require_once __DIR__ . '/../inc/mail.php';

if (vr_current_customer() !== null) vr_redirect('account/index.php');

$err = '';
$done = false;
$in = ['name' => '', 'email' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $in['name']  = trim((string)($_POST['name'] ?? ''));
    $in['email'] = trim((string)($_POST['email'] ?? ''));
    $pw  = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');

    if (!vr_rate_ok('creg', 6, 900)) {
        $err = t('login_throttled');
    } elseif ($pw !== $pw2) {
        $err = t('pw_mismatch');
    } else {
        [$ok, $res, $raw] = vr_customer_create($in + ['password' => $pw]);
        if ($ok) {
            vr_mail_customer_verify((string)$res['email'], $raw);
            $done = true;
        } elseif ($res === 'email_taken') {
            // Nötr sonuç: kayıtlı adres de aynı ekranı görür.
            $done = true;
        } else {
            $err = t((string)$res);
        }
    }
}

vr_auth_open($done ? t('acc_register_done_t') : t('register'));
?>
    <?php if ($done): ?>
      <p class="auth__lead"><?= te('acc_register_done_b') ?></p>
      <a class="btn btn--ghost" href="<?= h(vr_url('account/login.php')) ?>"><span><?= te('login') ?></span></a>
    <?php else: ?>
      <p class="auth__lead"><?= te('acc_register_lead') ?></p>

      <?php if ($err !== ''): ?>
        <div class="flash flash--err accflash"><?= h($err) ?></div>
      <?php endif; ?>

      <form class="form" method="post" action="<?= h(vr_url('account/register.php')) ?>">
        <?= vr_csrf_field() ?>
        <div class="field">
          <label for="name"><?= te('your_name') ?></label>
          <input id="name" name="name" type="text" autocomplete="name" value="<?= h($in['name']) ?>">
        </div>
        <div class="field">
          <label for="email"><?= te('email') ?> <span class="req">*</span></label>
          <input id="email" name="email" type="email" required autocomplete="email" value="<?= h($in['email']) ?>">
        </div>
        <?php vr_pw_field('password', t('password'), 'new-password', true, true, 10); ?>
        <?php vr_pw_field('password2', t('password_again'), 'new-password', false, true, 10); ?>

        <p class="auth__fine">
          <?= te('acc_register_note') ?>
          <a class="link" href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>
        </p>

        <div class="field">
          <button class="btn btn--block" type="submit"><span><?= te('register') ?></span></button>
        </div>
      </form>

      <div class="auth__or"><span><?= te('have_account') ?></span></div>
      <a class="btn btn--ghost btn--block" href="<?= h(vr_url('account/login.php')) ?>"><span><?= te('login') ?></span></a>
    <?php endif; ?>
<?php vr_auth_close(); ?>
