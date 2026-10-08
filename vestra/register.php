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
<div class="authwrap regwrap">
  <div class="authcard regcard">
<?php if ($check_email): ?>
    <div style="text-align:center;padding:40px 20px">
      <div style="font-size:52px;margin-bottom:16px">📧</div>
      <h2 style="margin:0 0 10px"><?= t('Check your inbox') ?></h2>
      <p style="color:var(--mut);margin:0 0 24px;font-size:15px"><?= t('We sent a verification link to your email address. Click it to activate your account.') ?></p>
      <p style="color:var(--mut);font-size:13px"><?= t("Didn't receive it? Check your spam folder or") ?> <a class="acc" href="/login"><?= t('sign in') ?></a> <?= t('to resend.') ?></p>
    </div>
<?php else:
    $_type = ($d['type'] ?? 'buyer') === 'seller' ? 'seller' : 'buyer';
    $_req  = '<span class="req" aria-hidden="true">*</span>';
?>
    <div class="authcard-logo">
      <svg viewBox="0 0 32 32" fill="none" width="34" height="34">
        <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/>
        <path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>VESTRA</span>
    </div>
    <h1 class="authcard-title"><?= t('Create your account') ?></h1>
    <p class="authcard-sub"><?= t('Join the verified B2B wholesale marketplace') ?></p>
    <?php /* Uc kisa guvence -- her biri sitenin zaten verdigi bir soz: hesap
             ucretsiz (llms.txt/ana sayfa "free trade account"), fiyatlar yalniz
             dogrulanmis isletmelere, kartla odeme escrow'da. Yeni vaat yok. */ ?>
    <ul class="regtrust">
      <li><svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path d="M5 10.5l3.2 3.2L15 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><?= t('Free account') ?></li>
      <li><svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path d="M5 10.5l3.2 3.2L15 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><?= t('Verified businesses only') ?></li>
      <li><svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><path d="M5 10.5l3.2 3.2L15 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><?= t('Buyer protection (escrow)') ?></li>
    </ul>

    <?php if ($err && !str_starts_with($err, 'promo_')): ?>
      <div class="regerr" role="alert"><?= htmlspecialchars($errmsg[$err] ?? $err) ?></div>
    <?php endif; ?>

    <form method="post" action="" class="authform regform" id="regform">
      <!-- 1 · Account type -->
      <div class="regstep"><span class="regnum">1</span><?= t('I want to') ?></div>
      <div class="authtype" role="radiogroup">
        <label class="typecard<?= $_type==='buyer'?' on':'' ?>" id="tc-buyer" onclick="setType('buyer')">
          <input type="radio" name="type" value="buyer" <?= $_type==='buyer'?'checked':'' ?>>
          <span class="ticon"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h14l-1.2 11.2a1.5 1.5 0 0 1-1.5 1.3H7.7a1.5 1.5 0 0 1-1.5-1.3z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/></svg></span>
          <span class="tl"><?= t('Buy wholesale') ?></span>
          <span class="ts"><?= t('Source products from verified sellers') ?></span>
          <span class="tdot" aria-hidden="true"></span>
        </label>
        <label class="typecard<?= $_type==='seller'?' on':'' ?>" id="tc-seller" onclick="setType('seller')">
          <input type="radio" name="type" value="seller" <?= $_type==='seller'?'checked':'' ?>>
          <span class="ticon"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 12.6V4.5a1 1 0 0 1 1-1h8.1l8 8a1.5 1.5 0 0 1 0 2.1l-7 7a1.5 1.5 0 0 1-2.1 0z"/><circle cx="8.3" cy="8.3" r="1.4"/></svg></span>
          <span class="tl"><?= t('Sell wholesale') ?></span>
          <span class="ts"><?= t('List your products to verified buyers') ?></span>
          <span class="tdot" aria-hidden="true"></span>
        </label>
      </div>

      <!-- 2 · Personal info -->
      <div class="regstep"><span class="regnum">2</span><?= t('Personal info') ?></div>
      <div class="authfield">
        <label for="r-name"><?= t('Full name') ?> <?= $_req ?></label>
        <input id="r-name" name="name" required autocomplete="name" placeholder="Anna Müller" value="<?= htmlspecialchars($d['name']??'') ?>">
      </div>
      <div class="authfield">
        <label for="r-email"><?= t('Email address') ?> <?= $_req ?></label>
        <input id="r-email" type="email" name="email" required autocomplete="email" placeholder="name@company.com"
               value="<?= htmlspecialchars($d['email']??'') ?>">
      </div>
      <div class="frow">
        <div class="authfield">
          <label for="r-pw"><?= t('Password') ?> <?= $_req ?></label>
          <input id="r-pw" type="password" name="password" required autocomplete="new-password" placeholder="<?= htmlspecialchars(t('min. 8 characters')) ?>">
        </div>
        <div class="authfield">
          <label for="r-pw2"><?= t('Confirm password') ?> <?= $_req ?></label>
          <input id="r-pw2" type="password" name="password2" required autocomplete="new-password" placeholder="••••••••">
        </div>
      </div>

      <!-- 3 · Company info -->
      <div class="regstep"><span class="regnum">3</span><?= t('Company info') ?></div>
      <div class="authfield">
        <label for="r-company"><?= t('Company name') ?> <?= $_req ?></label>
        <input id="r-company" name="company" required autocomplete="organization" placeholder="Company GmbH" value="<?= htmlspecialchars($d['company']??'') ?>">
      </div>
      <div class="frow">
        <div class="authfield">
          <label for="r-country"><?= t('Country') ?> <?= $_req ?></label>
          <?php /* maxlength was 3, for an ISO code. Buyers type the country name instead, and
                   the browser silently truncated it — one account reached the invoice as "Nor".
                   Both forms are accepted now and normalised to a full name where they are
                   printed; a name is never cut short to look like a code. */ ?>
          <input id="r-country" name="country" required maxlength="56" autocomplete="country-name" placeholder="DE" value="<?= htmlspecialchars($d['country']??'') ?>">
        </div>
        <div class="authfield">
          <label for="r-phone"><?= t('Phone') ?></label>
          <input id="r-phone" name="phone" type="tel" autocomplete="tel" placeholder="+49 30 12345678" value="<?= htmlspecialchars($d['phone']??'') ?>">
        </div>
      </div>
      <?php
      /* Kayit formu STATIK: ulke bu alanin ustunde secilse bile etiket canli
         degismiyor (sayfa yeniden cizilmeden). O yuzden burada etiket ulkeye
         gore degil, ipucu METNI her iki durumu da soyluyor. Formu geri donen
         bir hatada $d dolu geliyor, o zaman dogru etiket cikiyor. */
      $_tax = vestra_tax_id_hint($d['country'] ?? '');
      ?>
      <div class="frow frow-top">
        <div class="authfield">
          <label for="r-vat"><?= t($_tax['label']) ?></label>
          <input id="r-vat" name="vat_id" aria-describedby="r-vat-hint" placeholder="<?= htmlspecialchars($_tax['placeholder']) ?>" value="<?= htmlspecialchars($d['vat_id']??'') ?>">
        </div>
        <div class="authfield">
          <label for="r-reg"><?= t('Registration number') ?></label>
          <input id="r-reg" name="reg_number" placeholder="HRB 12345" value="<?= htmlspecialchars($d['reg_number']??'') ?>">
        </div>
      </div>
      <?php /* Ipucu ucunu de soyluyor. Eskiden yalniz ABD vardi ve geri kalan
               herkes "VAT" kelimesiyle bas basa kaliyordu -- Japon ya da Koreli
               bir satici icin o kelime aradigi numaraya karsilik gelmiyor. Ucuncu
               cumle en onemlisi: numara vermeyen ulkeler var ve alan bos
               birakilabilir, yoksa kayit orada duruyor. Eskiden 11px'lik dar bir
               satir olarak tek sutunun altindaydi (okunmuyordu, sutunlari
               kaydiriyordu); artik satirin altinda tam genislikte bir not. */ ?>
      <p class="reghint" id="r-vat-hint"><svg viewBox="0 0 20 20" width="15" height="15" aria-hidden="true"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 9v5M10 6.2v.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><span><?= t('EU: your VAT number. US: your EIN (e.g. 12-3456789) — there is no VAT in the United States. Other countries: your local business tax number, or leave this blank if your country does not issue one.') ?></span></p>
      <?php /* Posta kodu ve sehir AYRI (24 Eyl 2026): adres tek serbest satirken 116
               adresli alicinin 93'unde posta kodu yoktu. Zorunlu degil -- kayit
               formunu uzatip terk ettirmek istemiyoruz; fatura/teslimat adresi
               kasada ve adres defterinde zaten alan alan isteniyor. */ ?>
      <div class="authfield">
        <label for="r-addr"><?= t('Street and number') ?></label>
        <input id="r-addr" name="address" placeholder="Hauptstraße 1" autocomplete="street-address" value="<?= htmlspecialchars($d['address']??'') ?>">
      </div>
      <div class="frow frow-pc">
        <div class="authfield">
          <label for="r-pc"><?= t('Postcode') ?></label>
          <input id="r-pc" name="postcode" placeholder="10115" autocomplete="postal-code" maxlength="16" value="<?= htmlspecialchars($d['postcode']??'') ?>">
        </div>
        <div class="authfield">
          <label for="r-city"><?= t('City') ?></label>
          <input id="r-city" name="city" placeholder="Berlin" autocomplete="address-level2" maxlength="60" value="<?= htmlspecialchars($d['city']??'') ?>">
        </div>
      </div>

      <!-- 4 · Optional -->
      <div class="regstep"><span class="regnum">4</span><?= t('Optional details') ?></div>
      <div class="authfield">
        <label for="r-web"><?= t('Website') ?></label>
        <input id="r-web" name="website" type="url" autocomplete="url" placeholder="https://company.com" value="<?= htmlspecialchars($d['website']??'') ?>">
      </div>
      <?php /* Nereden geldi -- kendi beyani (attribution.php). Otomatik kaynak
               e-posta uygulamalarinda ve kopyalanan baglantilarda kayboluyor
               ("dogrudan" gorunuyor); bu soru o boslugu dolduruyor. Opsiyonel ve
               ON-SECILI DEGIL: hazir bir cevap, durust cevabi bozar. */ ?>
      <div class="authfield">
        <label for="r-how"><?= t('How did you find VESTRA?') ?></label>
        <select id="r-how" name="how_found">
          <option value=""><?= t('Please choose') ?></option>
          <?php foreach (vestra_attr_how_options() as $hk => $hl): ?>
            <option value="<?= htmlspecialchars($hk) ?>"<?= ($d['how_found'] ?? '') === $hk ? ' selected' : '' ?>><?= htmlspecialchars(t($hl)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="authfield">
        <label for="r-promo"><?= t('Invite / promo code') ?></label>
        <input id="r-promo" name="promo_code" aria-describedby="r-promo-hint" placeholder="VESTRA2026" value="<?= htmlspecialchars(strtoupper($d['promo_code']??'')) ?>" class="regcode">
        <p class="reghint reghint-inline" id="r-promo-hint"><?= t('(optional — unlocks instant verification)') ?></p>
      </div>
      <?php if($err && str_starts_with($err,'promo_')): ?>
        <div class="regerr" role="alert"><?= htmlspecialchars($errmsg[$err] ?? $err) ?></div>
      <?php endif; ?>

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
      <button class="btn btn-p regsubmit" type="submit" id="regsubmit"><?=
        $_type==='seller' ? t('Create seller account') : t('Create buyer account')
      ?></button>

      <p class="regterms">
        <?= t('By registering you agree to the') ?>
        <a class="acc" href="/legal?doc=terms"><?= t('Terms of Service') ?></a>
        <?= t('and') ?>
        <a class="acc" id="regagree" href="/legal?doc=<?= $_type ?>"><?= t('User Agreement') ?></a>.
        <?= t('Only verified businesses can access wholesale pricing.') ?>
      </p>
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
  /* Sozlesme baglantisi da secilen turu izlesin: eskiden sayfa ilk acildigi
     turde kaliyordu, satici secen biri alici sozlesmesini aciyordu. */
  var a=document.getElementById('regagree');
  if(a) a.href='/legal?doc='+(t==='seller'?'seller':'buyer');
}
</script>
<?php require __DIR__.'/inc/foot.php';
