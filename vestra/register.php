<?php
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/auth.php';
/* vestra_tax_id_hint() burada kullaniliyor ve products.php'de tanimli. Bu sayfa
   products.php'yi yuklemiyordu, head.php de yuklemiyor -- require olmadan KAYIT
   SAYFASI komple olumcul hataya duserdi. Sitenin en kritik sayfasinda sessiz bir
   bagimlilik: alan etiketini ulkeye gore degistirmek, kayit alamamaya mal olurdu. */
require_once __DIR__.'/inc/products.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* Already signed in → their panel, never a silent bounce back to the homepage
   (tapping "Register" and landing on the same page reads as a dead button). */
if (!empty($_SESSION['uid'])) {
    $a = auth_user();
    header('Location: '.($a && ($a['type'] ?? '') === 'seller' ? '/seller' : '/buyer')); exit;
}

$err = ''; $d = []; $check_email = isset($_GET['check_email']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    $result = auth_register($d);
    if (is_array($result)) {
        if (empty($result['email_verified'])) {
            // Verification required → show the "check your inbox" screen.
            header('Location: /register?check_email=1'); exit;
        }
        // Verification disabled → account is usable now: sign in and take them
        // straight to the KYC upload step (admin still gates final activation).
        auth_set($result);
        auth_touch_login($result['id']);
        header('Location: '.(($result['type'] ?? '') === 'seller' ? '/seller?tab=kyc' : '/buyer?tab=kyc')); exit;
    }
    $err = $result;
}

$errmsg = [
    'email_taken'           => t('An account with this email already exists.'),
    'email_pending_verify'  => t('This email is already registered but not yet verified — we resent the verification link. Check your inbox and spam folder.'),
    'password_short'     => t('Password must be at least 8 characters.'),
    'password_mismatch'  => t('Passwords do not match.'),
    'promo_not_found'    => t('Invite code not found.'),
    'promo_expired'      => t('This invite code has expired.'),
    'promo_exhausted'    => t('This invite code has reached its usage limit.'),
    'promo_inactive'     => t('This invite code is no longer active.'),
    'country_not_served' => t('We are not able to open new accounts for this market at this time.'),
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
<?php if ($check_email): ?>
    <div style="text-align:center;padding:40px 20px">
      <div style="font-size:52px;margin-bottom:16px">📧</div>
      <h2 style="margin:0 0 10px"><?= t('Check your inbox') ?></h2>
      <p style="color:var(--mut);margin:0 0 24px;font-size:15px"><?= t('We sent a verification link to your email address. Click it to activate your account.') ?></p>
      <p style="color:var(--mut);font-size:13px"><?= t("Didn't receive it? Check your spam folder or") ?> <a class="acc" href="/login"><?= t('sign in') ?></a> <?= t('to resend.') ?></p>
    </div>
<?php else: ?>
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
        <?php
        /* Kayit formu STATIK: ulke bu alanin ustunde secilse bile etiket canli
           degismiyor (sayfa yeniden cizilmeden). O yuzden burada etiket ulkeye
           gore degil, ipucu METNI her iki durumu da soyluyor. Formu geri donen
           bir hatada $d dolu geliyor, o zaman dogru etiket cikiyor. */
        $_tax = vestra_tax_id_hint($d['country'] ?? '');
        ?>
        <div class="authfield">
          <label><?= t($_tax['label']) ?></label>
          <input name="vat_id" placeholder="<?= htmlspecialchars($_tax['placeholder']) ?>" value="<?= htmlspecialchars($d['vat_id']??'') ?>">
          <?php /* Ipucu ucunu de soyluyor. Eskiden yalniz ABD vardi ve geri kalan
                   herkes "VAT" kelimesiyle bas basa kaliyordu -- Japon ya da Koreli
                   bir satici icin o kelime aradigi numaraya karsilik gelmiyor. Ucuncu
                   cumle en onemlisi: numara vermeyen ulkeler var ve alan bos
                   birakilabilir, yoksa kayit orada duruyor. */ ?>
          <div class="ahint" style="font-size:11px;margin-top:3px"><?= t('EU: your VAT number. US: your EIN (e.g. 12-3456789) — there is no VAT in the United States. Other countries: your local business tax number, or leave this blank if your country does not issue one.') ?></div>
        </div>
        <div class="authfield">
          <label><?= t('Registration number') ?></label>
          <input name="reg_number" placeholder="HRB 12345" value="<?= htmlspecialchars($d['reg_number']??'') ?>">
        </div>
      </div>
      <div class="frow" style="margin-bottom:0">
        <div class="authfield">
          <label><?= t('Country') ?> *</label>
          <?php /* maxlength was 3, for an ISO code. Buyers type the country name instead, and
                   the browser silently truncated it — one account reached the invoice as "Nor".
                   Both forms are accepted now and normalised to a full name where they are
                   printed; a name is never cut short to look like a code. */ ?>
          <input name="country" required maxlength="56" placeholder="DE" value="<?= htmlspecialchars($d['country']??'') ?>">
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

      <?php /* The button names the TYPE being created, not just "Create account".
               Whichever homepage button is the filled gold one, visitors read it
               as "the" way in and arrive here with that type preselected — the old
               neutral button then let them submit without ever seeing which. One
               wrong word here cost a support round ("I registered as buyer, it made
               me a seller"); naming the choice at the moment of commitment is the
               cheapest possible fix. Deliberately NOT written in terms of which
               button is gold today: that swapped once already (gold is the BUYER
               button as of Aug 2026) and the reason this button names the type does
               not depend on it. */ ?>
      <button class="btn btn-p" type="submit" id="regsubmit" style="width:100%;justify-content:center"><?=
        ($d['type']??'buyer')==='seller' ? t('Create seller account') : t('Create buyer account')
      ?></button>
    </form>

    <p class="authcard-foot"><?= t('Already have an account?') ?> <a class="acc" href="/login"><?= t('Sign in') ?></a></p>
<?php endif; ?>
  </div>
</div>
<script>
function setType(t){
  document.querySelectorAll('.typecard').forEach(function(c){c.classList.remove('on')});
  document.getElementById('tc-'+t).classList.add('on');
  document.querySelector('input[name=type][value='+t+']').checked=true;
  var b=document.getElementById('regsubmit');
  if(b) b.textContent = (t==='seller') ? <?= json_encode(t('Create seller account')) ?> : <?= json_encode(t('Create buyer account')) ?>;
}
</script>
<?php require __DIR__.'/inc/foot.php';
