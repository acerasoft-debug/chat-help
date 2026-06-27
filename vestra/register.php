<?php
require_once __DIR__.'/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['uid'])) { header('Location: /'); exit; }

$err = ''; $d = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    $result = auth_register($d);
    if (is_array($result)) {
        auth_set($result);
        header('Location: '.($result['type']==='seller' ? '/seller' : '/buyer')); exit;
    }
    $err = $result;
}

$errmsg = [
    'email_taken'        => t('An account with this email already exists.'),
    'password_short'     => t('Password must be at least 8 characters.'),
    'password_mismatch'  => t('Passwords do not match.'),
    'promo_not_found'    => t('Invite code not found.'),
    'promo_expired'      => t('This invite code has expired.'),
    'promo_exhausted'    => t('This invite code has reached its usage limit.'),
    'promo_inactive'     => t('This invite code is no longer active.'),
];

// Pre-fill promo code + type from URL (from seller-invite page links)
if (empty($d)) {
    if (isset($_GET['promo_code'])) $d['promo_code'] = strtoupper(trim($_GET['promo_code']));
    if (isset($_GET['type']))       $d['type'] = $_GET['type'];
}

$PAGE = t('Create account'); $NAV = ''; require __DIR__.'/inc/head.php';
?>
<div class="authwrap" style="padding:30px 20px">
  <div class="authcard" style="max-width:540px">
    <div class="authcard-logo">
      <svg viewBox="0 0 32 32" fill="none" width="34" height="34">
        <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/>
        <path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>VESTRA</span>
    </div>
    <h2 class="authcard-title"><?= t('Create your account') ?></h2>
    <p class="authcard-sub"><?= t('Join the verified B2B wholesale marketplace') ?></p>

    <?php if ($err): ?>
      <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:16px">
        <?= htmlspecialchars($errmsg[$err] ?? $err) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="" class="authform" id="regform">
      <!-- Account type toggle -->
      <p style="font-size:13px;color:var(--mut);margin:0 0 10px"><?= t('I want to') ?>:</p>
      <div class="authtype">
        <label class="typecard<?= ($d['type']??'buyer')==='buyer'?' on':'' ?>" id="tc-buyer" onclick="setType('buyer')">
          <input type="radio" name="type" value="buyer" <?= ($d['type']??'buyer')==='buyer'?'checked':'' ?> style="display:none">
          <span class="ticon">🛍️</span>
          <span class="tl"><?= t('Buy wholesale') ?></span>
          <span class="ts"><?= t('Source products from verified sellers') ?></span>
        </label>
        <label class="typecard<?= ($d['type']??'')==='seller'?' on':'' ?>" id="tc-seller" onclick="setType('seller')">
          <input type="radio" name="type" value="seller" <?= ($d['type']??'')==='seller'?'checked':'' ?> style="display:none">
          <span class="ticon">🏷️</span>
          <span class="tl"><?= t('Sell wholesale') ?></span>
          <span class="ts"><?= t('List your products to verified buyers') ?></span>
        </label>
      </div>

      <!-- Personal info -->
      <div class="authsect"><?= t('Personal info') ?></div>
      <div class="authfield">
        <label><?= t('Full name') ?> *</label>
        <input name="name" required placeholder="Anna Müller" value="<?= htmlspecialchars($d['name']??'') ?>">
      </div>
      <div class="authfield">
        <label><?= t('Email address') ?> *</label>
        <input type="email" name="email" required autocomplete="email" placeholder="name@company.com"
               value="<?= htmlspecialchars($d['email']??'') ?>">
      </div>
      <div class="frow" style="margin-bottom:0">
        <div class="authfield">
          <label><?= t('Password') ?> *</label>
          <input type="password" name="password" required autocomplete="new-password" placeholder="<?= htmlspecialchars(t('min. 8 characters')) ?>">
        </div>
        <div class="authfield">
          <label><?= t('Confirm password') ?> *</label>
          <input type="password" name="password2" required autocomplete="new-password" placeholder="••••••••">
        </div>
      </div>

      <!-- Company info -->
      <div class="authsect"><?= t('Company info') ?></div>
      <div class="authfield">
        <label><?= t('Company name') ?> *</label>
        <input name="company" required placeholder="Company GmbH" value="<?= htmlspecialchars($d['company']??'') ?>">
      </div>
      <div class="frow" style="margin-bottom:0">
        <div class="authfield">
          <label><?= t('VAT / Tax ID') ?></label>
          <input name="vat_id" placeholder="DE123456789" value="<?= htmlspecialchars($d['vat_id']??'') ?>">
        </div>
        <div class="authfield">
          <label><?= t('Registration number') ?></label>
          <input name="reg_number" placeholder="HRB 12345" value="<?= htmlspecialchars($d['reg_number']??'') ?>">
        </div>
      </div>
      <div class="frow" style="margin-bottom:0">
        <div class="authfield">
          <label><?= t('Country') ?> *</label>
          <input name="country" required maxlength="3" placeholder="DE" value="<?= htmlspecialchars($d['country']??'') ?>">
        </div>
        <div class="authfield">
          <label><?= t('Phone') ?></label>
          <input name="phone" type="tel" placeholder="+49 30 12345678" value="<?= htmlspecialchars($d['phone']??'') ?>">
        </div>
      </div>
      <div class="authfield">
        <label><?= t('Address') ?></label>
        <input name="address" placeholder="Hauptstraße 1, 10115 Berlin" value="<?= htmlspecialchars($d['address']??'') ?>">
      </div>
      <div class="authfield">
        <label><?= t('Website') ?></label>
        <input name="website" type="url" placeholder="https://company.com" value="<?= htmlspecialchars($d['website']??'') ?>">
      </div>

      <!-- Invite / promo code (optional) -->
      <div class="authsect"><?= t('Invite code') ?> <span class="hint" style="text-transform:none;font-size:11px;letter-spacing:0"><?= t('(optional — unlocks instant verification)') ?></span></div>
      <div class="authfield">
        <label><?= t('Invite / promo code') ?></label>
        <input name="promo_code" placeholder="e.g. VESTRA2026" value="<?= htmlspecialchars(strtoupper($d['promo_code']??'')) ?>" style="text-transform:uppercase;letter-spacing:1.5px">
      </div>
      <?php if($err && str_starts_with($err,'promo_')): ?>
        <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:10px">
          <?= htmlspecialchars($errmsg[$err] ?? $err) ?></div>
      <?php endif; ?>

      <div class="banner info" style="margin:10px 0 14px;font-size:13px">
        <?= t('By registering you agree to the') ?>
        <a class="acc" href="/legal?doc=terms"><?= t('Terms of Service') ?></a>
        <?= t('and') ?>
        <a class="acc" href="/legal?doc=<?= ($d['type']??'buyer')==='seller'?'seller':'buyer' ?>"><?= t('User Agreement') ?></a>.
        <?= t('Only verified businesses can access wholesale pricing.') ?>
      </div>

      <button class="btn btn-p" type="submit" style="width:100%;justify-content:center"><?= t('Create account') ?></button>
    </form>

    <p class="authcard-foot"><?= t('Already have an account?') ?> <a class="acc" href="/login"><?= t('Sign in') ?></a></p>
  </div>
</div>
<script>
function setType(t){
  document.querySelectorAll('.typecard').forEach(function(c){c.classList.remove('on')});
  document.getElementById('tc-'+t).classList.add('on');
  document.querySelector('input[name=type][value='+t+']').checked=true;
}
</script>
<?php require __DIR__.'/inc/foot.php';
