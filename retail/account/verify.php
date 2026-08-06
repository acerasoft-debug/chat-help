<?php
/**
 * E-posta doğrulama.
 *   GET  ?e=…&t=…   bağlantıya tıklandı
 *   POST action=resend  yeni bağlantı iste (giriş yapmış kullanıcı)
 *
 * Doğrulanmadan sipariş geçmişi açılmıyor — nedeni inc/customers.php'de.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/../inc/mail.php';
require_once __DIR__ . '/../inc/vouchers.php';

$state   = '';   // 'ok' | 'fail' | 'sent'
$welcome = '';   // doğrulama sonrası verilen karşılama kuponu

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    vr_csrf_check();
    $c = vr_customer_require();
    if (!vr_rate_ok('cverify', 5, 900)) {
        $state = 'fail';
    } else {
        [$ok, $raw] = vr_customer_reverify((string)$c['id']);
        if ($ok) vr_mail_customer_verify((string)$c['email'], $raw);
        $state = $ok ? 'sent' : 'fail';
    }
} elseif (($_GET['t'] ?? '') !== '') {
    $mail = (string)($_GET['e'] ?? '');
    [$ok] = vr_customer_verify($mail, (string)$_GET['t']);
    $state = $ok ? 'ok' : 'fail';

    /**
     * Doğrulanan her adrese ilk sipariş indirimi. BURADA veriliyor, kayıtta
     * değil: kupon ancak posta kutusunun sahibi olduğu kanıtlanınca anlam
     * taşıyor. vr_voucher_welcome() aynı adrese ikinci kupon üretmiyor, yani
     * bağlantıya tekrar tıklamak kupon biriktirmiyor.
     */
    if ($ok && (int)vr_config('welcome_discount_bps', 0) > 0) {
        [$vok, $code] = vr_voucher_welcome($mail);
        if ($vok) {
            $welcome = $code;
            try { vr_mail_welcome_voucher($mail, $code); }
            catch (Throwable $e) { vr_log('mail_failed', ['welcome' => $code]); }
        }
    }
}

vr_layout_start(['title' => t('acc_verify_title'), 'robots' => 'noindex,nofollow']);
$me = vr_current_customer();
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <h1 class="sechead__t"><?= te('acc_verify_title') ?></h1>

    <?php if ($state === 'ok'): ?>
      <div class="flash flash--ok" style="margin:20px 0"><?= te('acc_verify_ok') ?></div>
      <?php if ($welcome !== ''): ?>
        <div class="panel" style="border-top:0;padding-top:0;margin-bottom:24px">
          <h2><?= te('vou_welcome_title') ?></h2>
          <p><?= te('vou_welcome_body', ['pct' => h(rtrim(rtrim(number_format((int)vr_config('welcome_discount_bps',500)/100,2,',','.'),'0'),','))]) ?></p>
          <p class="vou__code"><?= h($welcome) ?></p>
        </div>
      <?php endif; ?>
      <a class="btn btn--brass" href="<?= h(vr_url('account/orders.php')) ?>"><span><?= te('acc_nav_orders') ?></span></a>
    <?php elseif ($state === 'sent'): ?>
      <div class="flash flash--ok" style="margin:20px 0"><?= te('acc_verify_sent') ?></div>
    <?php else: ?>
      <p class="sechead__s" style="margin:14px 0 24px"><?= te('acc_verify_fail') ?></p>
      <?php if ($me !== null): ?>
        <form method="post" action="<?= h(vr_url('account/verify.php')) ?>">
          <?= vr_csrf_field() ?>
          <input type="hidden" name="action" value="resend">
          <button class="btn btn--brass" type="submit"><span><?= te('acc_verify_resend') ?></span></button>
        </form>
      <?php else: ?>
        <a class="btn btn--ghost" href="<?= h(vr_url('account/login.php')) ?>"><span><?= te('login') ?></span></a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php vr_layout_end(); ?>
