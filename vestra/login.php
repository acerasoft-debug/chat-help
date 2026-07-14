<?php
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_GET['signout'])) { auth_logout(); header('Location: /'); exit; }

if (!empty($_SESSION['uid'])) {
    $a = auth_user();
    header('Location: '.($a && $a['type']==='seller' ? '/seller' : '/buyer')); exit;
}

$err = ''; $email_val = ''; $unverified = false; $resent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['resend'] ?? '') === '1') {
    $email_val = strtolower(trim($_POST['email'] ?? ''));
    $tkey = 'resend|'.$email_val.'|'.($_SERVER['REMOTE_ADDR'] ?? '');
    if (!auth_throttled($tkey, 3, 300)) {
        auth_throttle_hit($tkey);
        auth_resend_verify($email_val);
    }
    $resent = true; // same message whether or not the account exists — avoids leaking account existence
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_val = strtolower(trim($_POST['email'] ?? ''));
    $tkey = $email_val.'|'.($_SERVER['REMOTE_ADDR'] ?? '');
    if (auth_throttled($tkey)) {
        $err = t('Too many failed attempts. Please wait a few minutes and try again.');
    } else {
        $acc = auth_login($email_val, $_POST['password'] ?? '');
        if (is_array($acc)) {
            auth_throttle_clear($tkey);
            auth_set($acc);
            auth_touch_login($acc['id']);
            $back = $_GET['back'] ?? ($acc['type']==='seller' ? '/seller' : '/buyer');
            if (!str_starts_with($back, '/')) $back = '/buyer';
            header('Location: '.$back); exit;
        }
        if ($acc === 'invalid') { auth_throttle_hit($tkey); usleep(300000); }
        $unverified = ($acc === 'unverified');
        $err = match($acc) {
            'unverified' => t('Please verify your email address. Check your inbox for the confirmation link.'),
            'suspended'  => t('Your account has been suspended. Please contact support.'),
            default      => t('Email or password is incorrect.'),
        };
    }
}

$PAGE = t('Sign in'); $NAV = ''; require __DIR__.'/inc/head.php';
?>
<div class="authwrap">
  <div class="authcard">
    <div class="authcard-logo">
      <svg viewBox="0 0 32 32" fill="none" width="34" height="34">
        <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/>
        <path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>VESTRA</span>
    </div>
    <h2 class="authcard-title"><?= t('Welcome back') ?></h2>
    <p class="authcard-sub"><?= t('Sign in to your wholesale account') ?></p>

    <?php if ($resent): ?>
      <div class="banner" style="background:rgba(100,200,100,.08);border:1px solid rgba(100,200,100,.3);color:#6dbf7e;margin-bottom:16px"><?= t('If that account exists and is unverified, we just sent a new verification link. Check your inbox and spam folder.') ?></div>
    <?php elseif (isset($_GET['reset'])): ?>
      <div class="banner" style="background:rgba(100,200,100,.08);border:1px solid rgba(100,200,100,.3);color:#6dbf7e;margin-bottom:16px"><?= t('Password updated. You can now sign in with your new password.') ?></div>
    <?php elseif (isset($_GET['verified'])): ?>
      <div class="banner" style="background:rgba(100,200,100,.08);border:1px solid rgba(100,200,100,.3);color:#6dbf7e;margin-bottom:16px"><?= t('Email verified! You can now sign in.') ?></div>
    <?php elseif (isset($_GET['verify_error'])): ?>
      <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:16px"><?= t('Verification link is invalid or has already been used.') ?></div>
    <?php elseif ($err): ?>
      <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:16px">
        <?= htmlspecialchars($err) ?>
        <?php if ($unverified): ?>
          <form method="post" style="margin-top:8px">
            <input type="hidden" name="resend" value="1">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email_val) ?>">
            <button type="submit" style="background:none;border:none;padding:0;color:var(--acc);font:inherit;font-size:13px;cursor:pointer;text-decoration:underline"><?= t('Resend verification link →') ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="" class="authform">
      <div class="authfield">
        <label for="email"><?= t('Email address') ?></label>
        <input id="email" type="email" name="email" required autocomplete="email"
               value="<?= htmlspecialchars($email_val) ?>" placeholder="name@company.com">
      </div>
      <div class="authfield">
        <label for="pwd"><?= t('Password') ?></label>
        <input id="pwd" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:4px"><?= t('Sign in') ?></button>
    </form>

    <p class="authcard-foot"><?= t("Don't have an account?") ?> <a class="acc" href="/register"><?= t('Create one') ?></a></p>
    <p class="authcard-foot" style="margin-top:8px;font-size:13px">
      <a class="acc" href="/forgot" style="opacity:.75"><?= t('Forgot your password?') ?></a>
    </p>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
