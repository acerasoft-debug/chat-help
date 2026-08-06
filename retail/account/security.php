<?php
/** Şifre değiştirme ve hesap silme. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$err = '';
$ok  = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();

    if (($_POST['action'] ?? '') === 'delete') {
        // Onay kutusu işaretlenmeden silinmiyor: geri dönüşü yok.
        if (($_POST['confirm'] ?? '') === '1') {
            vr_customer_delete((string)$me['id']);
            vr_flash(t('acc_delete_done'), 'ok');
            vr_redirect('/');
        }
        $err = t('required_field');
    } else {
        $pw  = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['password2'] ?? '');
        if ($pw !== $pw2) {
            $err = t('pw_mismatch');
        } else {
            [$done, $key] = vr_customer_set_password((string)$me['id'], $pw, (string)($_POST['old'] ?? ''));
            if ($done) $ok = true;
            else $err = $key === 'pw_wrong' ? t('acc_pw_wrong') : t($key);
        }
    }
}

vr_layout_start(['title' => t('acc_nav_security'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap">
    <h1 class="sechead__t" style="margin-bottom:28px"><?= te('acc_nav_security') ?></h1>
    <div class="dash">
      <?php vr_accnav('security.php'); ?>
      <div>
        <?php if ($ok): ?>
          <div class="flash flash--ok" style="margin-bottom:18px"><?= te('acc_pw_changed') ?></div>
        <?php endif; ?>
        <?php if ($err !== ''): ?>
          <div class="flash flash--err" style="margin-bottom:18px"><?= h($err) ?></div>
        <?php endif; ?>

        <h2 class="sechead__t" style="font-size:20px;margin-bottom:14px"><?= te('acc_pw_change') ?></h2>
        <form class="form" method="post" action="<?= h(vr_url('account/security.php')) ?>">
          <?= vr_csrf_field() ?>
          <div class="field">
            <label for="old"><?= te('acc_pw_old') ?></label>
            <input id="old" name="old" type="password" required autocomplete="current-password">
          </div>
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
            <button class="btn btn--brass" type="submit"><span><?= te('acc_pw_change') ?></span></button>
          </div>
        </form>

        <hr class="rule" style="margin:38px 0 26px">

        <h2 class="sechead__t" style="font-size:20px;margin-bottom:12px"><?= te('acc_delete_title') ?></h2>
        <p style="font-size:13.5px;margin-bottom:10px"><?= te('acc_delete_lead') ?></p>
        <p style="font-size:12.5px;color:var(--muted);margin-bottom:18px"><?= te('acc_delete_orders_note') ?></p>

        <form class="form" method="post" action="<?= h(vr_url('account/security.php')) ?>">
          <?= vr_csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <label class="consent">
            <input type="checkbox" name="confirm" value="1" required>
            <span><?= te('acc_delete_confirm') ?></span>
          </label>
          <div class="field">
            <button class="btn btn--ghost btn--sm" type="submit"
                    style="border-color:var(--ember-text);color:var(--ember-text)">
              <span><?= te('acc_delete_title') ?></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
