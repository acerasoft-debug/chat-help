<?php
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/products.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Buyer → seller upgrade (from this page's CTA). Handled before any output so we
// can redirect. Membership/checkout is seller-only; a buyer who wants a plan is
// switched to a seller account (same login) and given the seller KYB documents.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'become_seller') {
    $me = auth_user();
    if ($me && ($me['type'] ?? '') === 'buyer') {
        auth_update($me['id'], ['type' => 'seller']);
        $have = array_column($me['doc_requests'] ?? [], 'type');
        $sellerDocs = [
            'id_document' => 'Please upload a government-issued ID: passport, national ID card, or driving licence.',
            'auth_letter' => 'If you are not the sole director/owner of the company, upload a signed authorization letter. You may skip this if you are the sole director.',
        ];
        foreach ($sellerDocs as $dt => $note) {
            if (!in_array($dt, $have, true)) auth_request_doc($me['id'], $dt, $note);
        }
        $_SESSION['utype'] = 'seller';
    }
    header('Location: /membership?welcome=seller'); exit;
}

$PAGE = t('Seller Membership'); $NAV = 'membership';
$META = 'VESTRA Seller Membership — plans and commission for wholesale fashion sellers. Reach KYC-verified boutique buyers across Europe, with lower fees on higher tiers and clear invoice-based payouts.';
require __DIR__.'/inc/head.php';

require_once __DIR__.'/inc/stripe.php';
$u = auth_user();
$isLoggedInSeller = $u && ($u['type'] ?? '') === 'seller';
$isLoggedInBuyer  = $u && ($u['type'] ?? '') === 'buyer';
$membershipStatus = $u['membership_status'] ?? 'none';
$alreadyActive    = in_array($membershipStatus, ['trialing', 'active'], true);
$stripeReady = stripe_configured();
$error    = !empty($_GET['error']) && ($_GET['error'] ?? '') !== 'notready';
$notReady = !$stripeReady || ($_GET['error'] ?? '') === 'notready';
$gated = !empty($_GET['gate']);
$curTier = $u['membership_tier'] ?? '';
$ctaDisabled = !$stripeReady; // active members get portal buttons instead of disabled CTAs
?>
<style>
/* Pricing page — design tokens from brief */
.mp-canvas{--mp-card:#EFEAE1;--mp-ink:#1A1C21;--mp-seal:#A6402B;--mp-mut:#6F6A61;--mp-line:#DBD4C7}
body{ background:#15171C }
.mwrap{max-width:1080px;margin:0 auto;padding:56px 24px 80px}
.mhero{text-align:center;margin-bottom:52px}
.mhero h1{font-family:'Playfair Display',Georgia,serif;font-size:clamp(26px,5vw,44px);font-weight:700;color:#fff;letter-spacing:-.5px;margin-bottom:12px}
.mhero p{color:var(--mut);font-size:15px;max-width:460px;margin:0 auto;line-height:1.6}
.mtiers{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;align-items:start}
.mcard{background:var(--mp-card);border-radius:16px;padding:32px 28px;position:relative;display:flex;flex-direction:column}
.mcard.featured{box-shadow:0 0 0 2px var(--mp-seal),0 8px 40px rgba(166,64,43,.18)}
.mpop{position:absolute;top:-14px;left:50%;transform:translateX(-50%);background:var(--mp-seal);color:#fff;font-size:10.5px;font-weight:700;letter-spacing:.09em;padding:4px 14px;border-radius:20px;white-space:nowrap;text-transform:uppercase}
.mname{font-family:'Playfair Display',Georgia,serif;font-size:22px;font-weight:700;color:var(--mp-ink);margin-bottom:6px}
.mprice{display:flex;align-items:baseline;gap:3px;margin-bottom:4px}
.mprice .cur{font-size:20px;font-weight:600;color:var(--mp-ink);margin-top:4px}
.mprice .amt{font-size:40px;font-weight:700;color:var(--mp-ink);letter-spacing:-1.5px;line-height:1}
.mprice .per{font-size:13px;color:var(--mp-mut);margin-left:2px}
.mtrial{font-size:12px;color:var(--mp-seal);font-weight:700;letter-spacing:.03em;margin-bottom:18px}
.mdiv{height:1px;background:var(--mp-line);margin:0 0 16px}
.mfeatures{list-style:none;padding:0;margin:0 0 28px;display:flex;flex-direction:column;gap:8px;flex:1}
.mfeatures li{font-size:13.5px;color:var(--mp-ink);display:flex;gap:8px;line-height:1.45}
.mfeatures li::before{content:"✓";color:var(--mp-seal);font-weight:700;flex-shrink:0;margin-top:1px}
.mcta{width:100%;padding:12px 0;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s;margin-top:auto}
.mcard:not(.featured) .mcta{background:var(--mp-ink);color:var(--mp-card)}
.mcard:not(.featured) .mcta:hover:not(:disabled){opacity:.82}
.mcard.featured .mcta{background:var(--mp-seal);color:#fff}
.mcard.featured .mcta:hover:not(:disabled){opacity:.88}
.mcta:disabled{opacity:.35;cursor:default}
.mfoot{text-align:center;margin-top:36px;font-size:12.5px;color:var(--mut);line-height:1.7}
.mfoot b{color:#b0a890}
.merr{background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#ef9a9a;border-radius:8px;padding:10px 14px;margin-bottom:24px;font-size:13px;text-align:center}
.mactive{background:rgba(122,214,160,.07);border:1px solid rgba(122,214,160,.22);color:#7ad6a0;border-radius:10px;padding:14px 18px;margin-bottom:32px;font-size:14px;text-align:center}
</style>

<div class="mp-canvas">
<div class="mwrap">

  <div class="mhero">
    <h1><?= t('Seller Membership') ?></h1>
    <p><?= t('Join the curated seller community. Access verified buyers and grow your wholesale business. Buyers are always free.') ?></p>
  </div>

  <?php if ($error): ?>
  <div class="merr"><?= t('Something went wrong — please try again or contact support.') ?></div>
  <?php elseif ($gated): ?>
  <div class="merr" style="background:rgba(240,192,96,.1);border-color:rgba(240,192,96,.35);color:#f0c060"><?= t('An active membership is required to publish products. Choose a plan below to get started.') ?></div>
  <?php endif; ?>

  <?php if ($notReady): ?>
  <div class="merr" style="background:rgba(138,180,248,.08);border-color:rgba(138,180,248,.3);color:#8ab4f8"><?= t('Online payment is being set up — plan checkout will open here shortly. Contact support@vestrasales.com to reserve your plan in the meantime.') ?></div>
  <?php endif; ?>

  <?php if ($isLoggedInBuyer): ?>
  <div class="mactive" style="background:rgba(138,180,248,.07);border-color:rgba(138,180,248,.25);color:#8ab4f8;text-align:left">
    🛍️ <b><?= t("You're signed in as a buyer.") ?></b>
    <?= ' ' . t('Buying on VESTRA is always free — no membership needed. The plans below are for sellers who want to list products.') ?>
    <div style="margin-top:12px">
      <form method="post" action="/membership" style="display:inline">
        <input type="hidden" name="action" value="become_seller">
        <button type="submit" class="mcta" style="width:auto;display:inline-block;padding:10px 22px;background:var(--acc,#c9a86a);color:#1a1408"><?= t('Become a seller') ?></button>
      </form>
      <span style="margin-left:10px;font-size:13px;opacity:.85"><?= t('Keeps your current login — just adds selling.') ?></span>
    </div>
  </div>
  <?php elseif (($_GET['welcome'] ?? '') === 'seller'): ?>
  <div class="mactive">✓ <?= t('You are now a seller. Choose a plan below to start listing — your first month is free.') ?></div>
  <?php endif; ?>

  <?php if ($alreadyActive): ?>
  <div class="mactive">
    ✓ <?= t('You already have an active membership.') ?>
    <?php if ($membershipStatus === 'trialing'): ?>
      <?= ' ' . t('Your trial is running — first charge in 30 days.') ?>
    <?php endif; ?>
    <form method="post" action="/stripe/portal" style="display:inline;margin-left:10px">
      <button type="submit" style="background:none;border:none;padding:0;color:#7ad6a0;font:inherit;cursor:pointer;text-decoration:underline"><?= t('Manage subscription →') ?></button>
    </form>
    <a href="/seller" style="color:#7ad6a0;margin-left:10px"><?= t('Go to dashboard →') ?></a>
  </div>
  <?php endif; ?>

  <div class="mtiers">

    <!-- ── STARTER ── -->
    <div class="mcard">
      <div class="mname"><?= t('Starter') ?></div>
      <div class="mprice"><span class="cur">€</span><span class="amt">19,90</span><span class="per"><?= t('/month') ?></span></div>
      <div class="mtrial">✓ <?= t('1 month free') ?></div>
      <div class="mdiv"></div>
      <ul class="mfeatures">
        <li><?= t('For new sellers testing wholesale.') ?></li>
        <li><?= t('10 listings / month') ?></li>
        <li><?= sprintf(t('%s%% commission per sale'), number_format(vestra_seller_commission_rate('starter')*100,1)) ?></li>
        <li><?= t('Seller profile &amp; showroom') ?></li>
        <li><?= t('"Verified Seller" badge') ?></li>
        <li><?= t('Direct buyer contact') ?></li>
        <li><?= t('Trade Record') ?></li>
      </ul>
      <?php if ($isLoggedInSeller && $alreadyActive): ?>
        <?php if ($curTier === 'starter'): ?>
        <button class="mcta" type="button" disabled>✓ <?= t('Your current plan') ?></button>
        <?php else: ?>
        <form method="post" action="/stripe/portal"><button class="mcta" type="submit"><?= t('Change plan') ?></button></form>
        <?php endif; ?>
      <?php elseif ($isLoggedInSeller): ?>
      <form method="post" action="/stripe/checkout">
        <input type="hidden" name="tier" value="starter">
        <button class="mcta" type="submit" <?= $ctaDisabled?'disabled':'' ?>><?= t('Get started') ?></button>
      </form>
      <?php elseif ($isLoggedInBuyer): ?>
        <form method="post" action="/membership"><input type="hidden" name="action" value="become_seller"><button class="mcta" type="submit" style="width:100%;background:#1A1C21;color:#EFEAE1;border:none;padding:12px 0;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer"><?= t('Become a seller') ?></button></form>
      <?php else: ?>
        <a href="/register?type=seller" class="mcta" style="display:block;text-align:center;text-decoration:none;background:#1A1C21;color:#EFEAE1;padding:12px 0;border-radius:10px;font-size:15px;font-weight:700"><?= t('Get started') ?></a>
      <?php endif; ?>
    </div>

    <!-- ── PRO (featured) ── -->
    <div class="mcard featured">
      <div class="mpop"><?= t('Most popular') ?></div>
      <div class="mname"><?= t('Pro') ?></div>
      <div class="mprice"><span class="cur">€</span><span class="amt">39,90</span><span class="per"><?= t('/month') ?></span></div>
      <div class="mtrial">✓ <?= t('1 month free') ?></div>
      <div class="mdiv"></div>
      <ul class="mfeatures">
        <li><?= t('For active sellers with a growing range.') ?></li>
        <li><?= t('100 listings / month') ?></li>
        <li><?= sprintf(t('%s%% commission per sale'), number_format(vestra_seller_commission_rate('pro')*100,1)) ?></li>
        <li><?= t('Everything in Starter') ?></li>
        <li><?= t('Priority placement') ?></li>
        <li><?= t('Analytics dashboard') ?></li>
        <li><?= t('Multiple users') ?></li>
        <li><?= t('Lead notifications') ?></li>
      </ul>
      <?php if ($isLoggedInSeller && $alreadyActive): ?>
        <?php if ($curTier === 'pro'): ?>
        <button class="mcta" type="button" disabled>✓ <?= t('Your current plan') ?></button>
        <?php else: ?>
        <form method="post" action="/stripe/portal"><button class="mcta" type="submit"><?= t('Change plan') ?></button></form>
        <?php endif; ?>
      <?php elseif ($isLoggedInSeller): ?>
      <form method="post" action="/stripe/checkout">
        <input type="hidden" name="tier" value="pro">
        <button class="mcta" type="submit" <?= $ctaDisabled?'disabled':'' ?>><?= t('Get started') ?></button>
      </form>
      <?php elseif ($isLoggedInBuyer): ?>
        <form method="post" action="/membership"><input type="hidden" name="action" value="become_seller"><button class="mcta" type="submit" style="width:100%;background:#A6402B;color:#fff;border:none;padding:12px 0;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer"><?= t('Become a seller') ?></button></form>
      <?php else: ?>
        <a href="/register?type=seller" class="mcta" style="display:block;text-align:center;text-decoration:none;background:#A6402B;color:#fff;padding:12px 0;border-radius:10px;font-size:15px;font-weight:700"><?= t('Get started') ?></a>
      <?php endif; ?>
    </div>

    <!-- ── ELITE (internal tier key stays "premium" — Stripe/account data untouched) ── -->
    <div class="mcard">
      <div class="mname"><?= t('Elite') ?></div>
      <div class="mprice"><span class="cur">€</span><span class="amt">89,90</span><span class="per"><?= t('/month') ?></span></div>
      <div class="mtrial">✓ <?= t('1 month free') ?></div>
      <div class="mdiv"></div>
      <ul class="mfeatures">
        <li><?= t('For established wholesalers with volume.') ?></li>
        <li><?= t('Unlimited listings') ?></li>
        <li><?= sprintf(t('%s%% commission per sale'), number_format(vestra_seller_commission_rate('premium')*100,1)) ?></li>
        <li><?= t('Everything in Pro') ?></li>
        <li><?= t('Top placement') ?></li>
        <li><?= t('Dedicated account manager') ?></li>
        <li><?= t('Buyer protection & dispute process') ?></li>
      </ul>
      <?php if ($isLoggedInSeller && $alreadyActive): ?>
        <?php if ($curTier === 'premium'): ?>
        <button class="mcta" type="button" disabled>✓ <?= t('Your current plan') ?></button>
        <?php else: ?>
        <form method="post" action="/stripe/portal"><button class="mcta" type="submit"><?= t('Change plan') ?></button></form>
        <?php endif; ?>
      <?php elseif ($isLoggedInSeller): ?>
      <form method="post" action="/stripe/checkout">
        <input type="hidden" name="tier" value="premium">
        <button class="mcta" type="submit" <?= $ctaDisabled?'disabled':'' ?>><?= t('Get started') ?></button>
      </form>
      <?php elseif ($isLoggedInBuyer): ?>
        <form method="post" action="/membership"><input type="hidden" name="action" value="become_seller"><button class="mcta" type="submit" style="width:100%;background:#1A1C21;color:#EFEAE1;border:none;padding:12px 0;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer"><?= t('Become a seller') ?></button></form>
      <?php else: ?>
        <a href="/register?type=seller" class="mcta" style="display:block;text-align:center;text-decoration:none;background:#1A1C21;color:#EFEAE1;padding:12px 0;border-radius:10px;font-size:15px;font-weight:700"><?= t('Get started') ?></a>
      <?php endif; ?>
    </div>

  </div><!-- /mtiers -->

  <p class="mfoot"><?= t('<b>One-time onboarding &amp; verification 89,90 €</b> · 1 month free · Buyers free · excl. VAT') ?></p>
  <p class="mfoot"><?= t('<b>Commission is charged automatically</b> to your card on file when payment arrives — no invoicing, no manual transfers. The rate depends on your plan (shown above) and drops as you upgrade.') ?></p>

  <?php if (!$isLoggedInSeller): ?>
  <p style="text-align:center;margin-top:14px;font-size:13px;color:var(--mut)">
    <a href="/register?type=seller" style="color:var(--acc)"><?= t('Create a seller account') ?></a>
    <?= ' ' . t('to get started.') ?>
    <?= t('Already registered?') ?> <a href="/login" style="color:var(--acc)"><?= t('Sign in') ?></a>
  </p>
  <?php endif; ?>

</div><!-- /mwrap -->
</div><!-- /mp-canvas -->
<?php require __DIR__.'/inc/foot.php';
