<?php
require_once __DIR__.'/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_GET['signout'])) { auth_logout(); header('Location: /'); exit; }

if (!empty($_SESSION['uid'])) {
    $a = auth_user();
    header('Location: '.($a && $a['type']==='seller' ? '/seller' : '/buyer')); exit;
}

$err = ''; $email_val = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_val = strtolower(trim($_POST['email'] ?? ''));
    $acc = auth_login($email_val, $_POST['password'] ?? '');
    if ($acc) {
        auth_set($acc);
        $back = $_GET['back'] ?? ($acc['type']==='seller' ? '/seller' : '/buyer');
        if (!str_starts_with($back, '/')) $back = '/buyer';
        header('Location: '.$back); exit;
    }
    $err = t('Email or password is incorrect.');
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

    <?php if ($err): ?>
      <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:16px"><?= htmlspecialchars($err) ?></div>
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
      <a class="acc" href="/?demo_member=1" style="opacity:.6"><?= t('Continue as demo') ?></a>
    </p>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
