<?php
/**
 * Şifre sıfırlama isteği. Sonuç DAİMA aynı: hesap olsa da olmasa da aynı
 * mesaj basılıyor, yoksa form "bu adres kayıtlı mı" sorgusuna dönerdi.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/../inc/mail.php';

$done = false;
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    if (vr_rate_ok('creset', 5, 900)) {
        [, $raw] = vr_customer_reset_request($email);
        if ($raw !== '') vr_mail_customer_reset(strtolower($email), $raw);
    }
    $done = true;   // hız sınırına takılsa bile aynı mesaj
}

vr_layout_start(['title' => t('acc_forgot_title'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <h1 class="sechead__t"><?= te('acc_forgot_title') ?></h1>

    <?php if ($done): ?>
      <div class="flash flash--ok" style="margin:20px 0"><?= te('acc_forgot_done') ?></div>
      <a class="btn btn--ghost" href="<?= h(vr_url('account/login.php')) ?>"><span><?= te('login') ?></span></a>
    <?php else: ?>
      <p class="sechead__s" style="margin:12px 0 28px"><?= te('acc_forgot_lead') ?></p>
      <form class="form" method="post" action="<?= h(vr_url('account/forgot.php')) ?>">
        <?= vr_csrf_field() ?>
        <div class="field">
          <label for="email"><?= te('email') ?></label>
          <input id="email" name="email" type="email" required autocomplete="email" value="<?= h($email) ?>">
        </div>
        <div class="field">
          <button class="btn btn--brass" type="submit"><span><?= te('acc_forgot_title') ?></span></button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php vr_layout_end(); ?>
