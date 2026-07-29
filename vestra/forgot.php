<?php
/** VESTRA — request a password-reset link. Always answers the same way (no account enumeration). */
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/notify.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $tkey  = 'reset|'.($_SERVER['REMOTE_ADDR'] ?? '');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !auth_throttled($tkey, 8, 900)) {
        auth_throttle_hit($tkey); // counts requests, not failures — caps reset-mail spam per IP
        if ($acc = auth_reset_begin($email)) {
            $host = (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'vestrasales.com');
            $link = $host.'/reset?token='.$acc['reset_token'];
            $name = $acc['name'] ?: ($acc['company'] ?: 'there');
            // This runs inside the account holder's OWN request (they just typed their own
            // email into this form) — vlang() is the right signal here, unlike offer/message/
            // membership mail which fires from someone else's request.
            [$subj, $body, $rOpts] = vestra_reset_text(vlang(), $name, $link);
            vestra_send_mail($acc['email'], $subj, $body, '', '', null, '', $rOpts);
        }
    }
    $sent = true; // same response whether or not the account exists
}

$PAGE = t('Reset password'); $NAV = ''; require __DIR__.'/inc/head.php';
?>
<div class="authwrap">
  <div class="authcard">
    <h2 class="authcard-title"><?= t('Reset password') ?></h2>
    <?php if ($sent): ?>
      <div class="banner" style="background:rgba(100,200,100,.08);border:1px solid rgba(100,200,100,.3);color:#6dbf7e;margin-bottom:16px">
        <?= t('If an account exists for that address, a reset link is on its way. Check your inbox (and spam folder).') ?>
      </div>
      <p class="authcard-foot"><a class="acc" href="/login"><?= t('Back to sign in') ?></a></p>
    <?php else: ?>
      <p class="authcard-sub"><?= t("Enter your account email — we'll send you a link to set a new password.") ?></p>
      <form method="post" action="/forgot" class="authform">
        <div class="authfield">
          <label for="email"><?= t('Email address') ?></label>
          <input id="email" type="email" name="email" required autocomplete="email" placeholder="name@company.com">
        </div>
        <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:4px"><?= t('Send reset link') ?></button>
      </form>
      <p class="authcard-foot"><a class="acc" href="/login"><?= t('Back to sign in') ?></a></p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
