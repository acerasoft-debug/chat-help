<?php
require_once __DIR__.'/inc/i18n.php';   // t() — head.php loads it too, but only after $PAGE/$META are read
$PAGE = t('Sell on VESTRA'); $NAV = 'sell';
/* This is the landing page sellers arrive on from outreach, and it is in the sitemap, so
   it needs its own description rather than the site-wide fallback every page shares. */
$META = t('Sell wholesale on VESTRA — reach KYC-verified boutique buyers across Europe. List branded stock, get paid against invoices, and keep your pricing and buyer list to yourself.');
$code = htmlspecialchars(strtoupper($_GET['code'] ?? 'VESTRA2026'));
require __DIR__.'/inc/head.php';
?>
<style>
.inv-hero{padding:70px 0 48px;text-align:center}
.inv-hero h1{font-size:clamp(32px,5vw,54px);margin:0 0 14px}
.inv-hero p{font-size:clamp(15px,2vw,18px);color:var(--mut);max-width:580px;margin:0 auto 28px}
.inv-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(201,168,106,.1);border:1px solid rgba(201,168,106,.35);border-radius:999px;padding:8px 18px;font-size:13px;font-weight:600;color:var(--acc);margin-bottom:28px;letter-spacing:.4px}
.inv-stats{display:flex;justify-content:center;gap:40px;flex-wrap:wrap;margin:0 0 44px;padding:24px 28px;background:var(--bg2);border:1px solid var(--line);border-radius:18px;max-width:680px;margin-inline:auto}
.inv-stats .ist b{font-family:'Playfair Display',serif;font-size:32px;color:var(--acc);display:block;line-height:1}
.inv-stats .ist span{font-size:13px;color:var(--mut);display:block;margin-top:4px}
.inv-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin:0 0 48px}
.inv-step{background:var(--bg2);border:1px solid var(--line);border-radius:16px;padding:22px 20px}
.inv-step .sn{width:30px;height:30px;border-radius:50%;background:rgba(201,168,106,.15);border:1px solid rgba(201,168,106,.3);display:grid;place-items:center;font-size:13px;font-weight:700;color:var(--acc);margin-bottom:12px}
.inv-step h3{margin:0 0 6px;font-size:16px}
.inv-step p{color:var(--mut);font-size:13.5px;margin:0}
.inv-promo{background:linear-gradient(135deg,rgba(201,168,106,.15) 0%,rgba(201,168,106,.04) 100%);border:1px solid rgba(201,168,106,.4);border-radius:20px;padding:32px;text-align:center;margin:0 0 48px}
.inv-promo h2{font-size:26px;margin:0 0 8px}
.inv-promo p{color:var(--mut);margin:0 0 20px}
.code-chip{font-family:'Courier New',monospace;font-size:22px;font-weight:700;letter-spacing:3px;color:var(--acc);background:rgba(201,168,106,.08);border:2px dashed rgba(201,168,106,.4);border-radius:10px;padding:10px 24px;display:inline-block;margin:0 0 20px;cursor:pointer;transition:.2s}
.code-chip:hover{background:rgba(201,168,106,.14)}
.inv-benefits{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:0 0 32px}
.inv-ben{display:flex;align-items:flex-start;gap:10px;background:var(--bg2);border:1px solid var(--line);border-radius:12px;padding:14px 16px}
.inv-ben svg{flex-shrink:0;margin-top:2px}
.inv-ben strong{display:block;font-size:14px;margin-bottom:2px}
.inv-ben span{font-size:13px;color:var(--mut)}
.inv-faq{max-width:700px;margin:0 auto 48px}
.inv-faq h2{text-align:center;font-size:28px;margin:0 0 18px}
@media(max-width:720px){.inv-steps,.inv-benefits{grid-template-columns:1fr}.inv-stats{gap:22px}}
</style>

<div class="wrap">
  <!-- HERO -->
  <div class="inv-hero">
    <div class="inv-badge">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
      <?= t('Early seller access — limited spots') ?>
    </div>
    <h1><?= t('Sell to verified B2B buyers across Europe') ?></h1>
    <p><?= t('VESTRA connects wholesale fashion sellers with verified business buyers. List your products in minutes — built-in buyer protection, group buy engine, and zero upfront cost.') ?></p>
    <a class="btn btn-p" href="/register?type=seller&promo_code=<?= $code ?>" style="font-size:16px;padding:15px 30px">
      <?= t('Start selling now') ?></a>
    <div style="margin-top:12px;font-size:13px;color:var(--mut)"><?= t('No listing fees · No subscription · Pay only when you sell') ?></div>
  </div>

  <!-- STATS -->
  <div class="inv-stats">
    <div class="ist"><b>500+</b><span><?= t('Verified buyers') ?></span></div>
    <div class="ist"><b>18</b><span><?= t('Countries') ?></span></div>
    <div class="ist"><b>€0</b><span><?= t('Upfront cost') ?></span></div>
    <div class="ist"><b>7 %</b><span><?= t('Commission on sales') ?></span></div>
  </div>

  <!-- BENEFITS -->
  <div class="inv-benefits">
    <?php $bens = [
      ['✓','Documented invoice payments','Every order runs on clear invoice terms with a full paper trail — no chasing payments.'],
      ['✓','Group buy engine','Small buyers pool their quantities to hit your MOQ. You get your minimum order, they get wholesale pricing.'],
      ['✓','Verified buyer-only access','All pricing is hidden from the public. Only verified businesses can see and order.'],
      ['✓','Multi-language catalog','Your listings appear in DE, EN, FR, IT, ES — no extra work needed.'],
    ]; foreach($bens as $b): ?>
    <div class="inv-ben">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
      <div><strong><?= $b[0].' '.t($b[1]) ?></strong><span><?= t($b[2]) ?></span></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- HOW IT WORKS -->
  <h2 style="text-align:center;margin:0 0 20px"><?= t('How it works') ?></h2>
  <div class="inv-steps">
    <?php $steps = [
      [1, t('Create your account'), t('Register in 2 minutes. Use your invite code for instant KYB — no waiting.')],
      [2, t('Add your products'), t('Fill in the product form, upload a photo, set your pricing tiers and MOQ.')],
      [3, t('Buyers order — you ship'), t('Orders land in your panel. You ship once the invoice is paid — no payment chasing.')],
    ]; foreach($steps as $s): ?>
    <div class="inv-step">
      <div class="sn"><?= $s[0] ?></div>
      <h3><?= $s[1] ?></h3>
      <p><?= $s[2] ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- PROMO BOX -->
  <div class="inv-promo">
    <h2>🎁 <?= t('Early seller offer') ?></h2>
    <p><?= t('Register with your invite code and get:') ?></p>
    <div class="code-chip" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= $code ?>');this.textContent='<?= t('Copied!') ?>'"><?= $code ?></div>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:7px;font-size:14px;color:var(--ok)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        <?= t('Instant KYB verification') ?>
      </div>
      <div style="display:flex;align-items:center;gap:7px;font-size:14px;color:var(--ok)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        <?= t('No registration fee') ?>
      </div>
    </div>
    <a class="btn btn-p" href="/register?type=seller&promo_code=<?= $code ?>" style="font-size:15px;padding:13px 28px">
      <?= t('Claim your invite') ?>
    </a>
    <p style="margin:14px 0 0;font-size:13px;color:var(--mut)"><?= t('Limited offer · Code pre-filled at registration') ?></p>
  </div>

  <!-- FAQ -->
  <div class="inv-faq">
    <h2><?= t('Seller FAQ') ?></h2>
    <div class="faq-list">
      <?php $faqs = [
        [t('What products can I sell?'), t('Branded fashion, accessories, footwear, basics, textiles — anything you can ship within the EEA with valid proof of origin. VESTRA does not accept fakes or grey-market goods.')],
        [t('What is the commission?'), t('VESTRA takes 7 % from your payout. Buyers pay a 2 % protection fee on top. With an invite code, registration on the platform is free of charge.')],
        [t('How does payment work?'), t('Payment is currently invoice-based: the buyer pays by bank transfer against a proforma invoice and goods ship after payment. Other payment methods (card / escrow checkout) are temporarily suspended.')],
        [t('How long does KYB take?'), t('With an invite code: instant approval. Without: typically 1–3 business days after you submit your registration details.')],
        [t('Can I set my own prices?'), t('Yes. You set your own MOQ, tiered pricing, and pricing mode (fixed, sale, or make-an-offer). VESTRA only takes its commission from completed sales.')],
      ]; foreach($faqs as $f): ?>
      <details class="faq-item"><summary><?= $f[0] ?></summary><div class="faq-ans"><?= $f[1] ?></div></details>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- FINAL CTA -->
  <div style="text-align:center;padding:48px 0 60px">
    <h2 style="margin:0 0 10px"><?= t('Ready to reach European buyers?') ?></h2>
    <p style="color:var(--mut);margin:0 0 22px"><?= t('Create your free seller account in 2 minutes.') ?></p>
    <a class="btn btn-p" href="/register?type=seller&promo_code=<?= $code ?>" style="font-size:16px;padding:15px 30px"><?= t('Start selling now') ?></a>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
