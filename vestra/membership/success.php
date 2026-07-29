<?php
require_once __DIR__.'/../inc/i18n.php';
require_once __DIR__.'/../inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$PAGE = t('Membership activated'); $NAV = 'membership';
require __DIR__.'/../inc/head.php';

$u = auth_user();
$tier = $u['membership_tier'] ?? '';
?>
<style>
body{ background:#15171C }
.sucwrap{max-width:520px;margin:80px auto;padding:0 24px 80px;text-align:center}
.sucicon{font-size:52px;line-height:1;margin-bottom:24px}
.suctitle{font-family:'Playfair Display',Georgia,serif;font-size:32px;font-weight:700;color:#fff;margin-bottom:12px}
.sucline{color:#9a9590;font-size:15px;line-height:1.65;margin-bottom:8px}
.sucbox{background:#EFEAE1;border-radius:14px;padding:28px 32px;margin:32px 0;text-align:left}
.sucbox p{font-size:14px;color:#1A1C21;line-height:1.6;margin-bottom:10px}
.sucbox p:last-child{margin-bottom:0}
.sucbox strong{color:#A6402B}
.sucactions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:32px}
.sucbtn{display:inline-flex;align-items:center;padding:13px 26px;border-radius:10px;font-size:15px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s}
.sucbtn.primary{background:#A6402B;color:#fff}
.sucbtn.primary:hover{opacity:.88}
.sucbtn.sec{background:#EFEAE1;color:#1A1C21}
.sucbtn.sec:hover{opacity:.88}
</style>

<div class="sucwrap">
  <div class="sucicon">✓</div>
  <h1 class="suctitle"><?= t('Membership activated') ?></h1>
  <p class="sucline"><?= t('Welcome to VESTRA seller membership.') ?></p>

  <div class="sucbox">
    <?php if ($tier): ?>
    <p><?= sprintf(t('You are on the <strong>%s</strong> plan.'), ucfirst($tier)) ?></p>
    <?php endif; ?>
    <p><?= t('Your 30-day free trial has started. Your first charge will be in 30 days.') ?></p>
    <p><?= t('Your card has been charged 89,90 € for the one-time onboarding fee.') ?></p>
  </div>

  <p class="sucline" style="font-size:13px;color:#6F6A61"><?= t('Your verified-seller badge will appear once our team completes the onboarding review — usually within 1 business day.') ?></p>

  <div class="sucactions">
    <a class="sucbtn primary" href="/seller"><?= t('Go to seller dashboard →') ?></a>
    <a class="sucbtn sec" href="/seller?tab=add"><?= t('Set up your first listing →') ?></a>
  </div>
</div>
<?php require __DIR__.'/../inc/foot.php';
