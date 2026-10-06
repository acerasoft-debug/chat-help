<?php
/** VESTRA — set a new password via emailed token (1 h validity, single use). */
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$acc = auth_reset_find($token);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acc) {
    $p1 = $_POST['password'] ?? ''; $p2 = $_POST['password2'] ?? '';
    if (strlen($p1) < 8)   $err = t('Password must be at least 8 characters.');
    elseif ($p1 !== $p2)   $err = t('Passwords do not match.');
    elseif (auth_set_password($acc['id'], $p1)) { header('Location: /login?reset=1'); exit; }
    else $err = t('Something went wrong. Please request a new reset link.');
}

$PAGE = t('Set a new password'); $NAV = ''; require __DIR__.'/inc/head.php';
?>
<div class="authwrap">
  <div class="authcard">
    <h2 class="authcard-title"><?= t('Set a new password') ?></h2>
    <?php if (!$acc): ?>
      <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:16px">
        <?= t('This reset link is invalid or has expired. Reset links are valid for 1 hour.') ?>
      </div>
      <p class="authcard-foot"><a class="acc" href="/forgot"><?= t('Request a new link') ?></a></p>
    <?php else: ?>
      <p class="authcard-sub"><?= htmlspecialchars($acc['email']) ?></p>
      <?php if ($err): ?>
        <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:16px"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>
      <form method="post" action="/reset" class="authform">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="authfield">
          <label for="pwd"><?= t('New password') ?></label>
          <input id="pwd" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="••••••••">
        </div>
        <div class="authfield">
          <label for="pwd2"><?= t('Repeat new password') ?></label>
          <input id="pwd2" type="password" name="password2" required minlength="8" autocomplete="new-password" placeholder="••••••••">
        </div>
        <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:4px"><?= t('Save new password') ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
