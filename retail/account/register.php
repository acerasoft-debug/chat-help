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

vr_layout_start(['title' => t('register'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <?php if ($done): ?>
      <h1 class="sechead__t"><?= te('acc_register_done_t') ?></h1>
      <p class="sechead__s" style="margin:14px 0 26px"><?= te('acc_register_done_b') ?></p>
      <a class="btn btn--ghost" href="<?= h(vr_url('account/login.php')) ?>"><span><?= te('login') ?></span></a>
    <?php else: ?>
      <h1 class="sechead__t"><?= te('register') ?></h1>
      <p class="sechead__s" style="margin:12px 0 28px"><?= te('acc_register_lead') ?></p>

      <?php if ($err !== ''): ?>
        <div class="flash flash--err" style="margin-bottom:16px"><?= h($err) ?></div>
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
        <div class="field">
          <label for="password"><?= te('password') ?> <span class="req">*</span></label>
          <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
          <small><?= te('pw_too_short') ?></small>
        </div>
        <div class="field">
          <label for="password2"><?= te('password_again') ?> <span class="req">*</span></label>
          <input id="password2" name="password2" type="password" required minlength="10" autocomplete="new-password">
        </div>

        <p style="font-size:12.5px;color:var(--muted)">
          <?= te('acc_register_note') ?>
          <a class="link" href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>
        </p>

        <div class="field">
          <button class="btn btn--brass" type="submit"><span><?= te('register') ?></span></button>
        </div>
      </form>

      <p style="margin-top:22px;font-size:13px;color:var(--muted)">
        <?= te('have_account') ?>
        <a class="link" href="<?= h(vr_url('account/login.php')) ?>"><?= te('login') ?></a>
      </p>
    <?php endif; ?>
  </div>
</section>
<?php vr_layout_end(); ?>
