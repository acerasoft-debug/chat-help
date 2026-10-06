<?php
/**
 * VESTRA — one-click unsubscribe for seller-prospecting outreach emails.
 * Public, no login required (the recipient is a prospect, not a VESTRA
 * member). GET only shows a confirmation screen; the actual opt-out is a
 * POST, so mail-security link-scanners that pre-fetch GET links can't
 * accidentally unsubscribe someone who never clicked anything.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/leads.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$lead  = vestra_lead_by_token($token);
$done  = false;

if ($lead && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $leads = vestra_leads();
    foreach ($leads as &$l) {
        if (($l['id'] ?? '') === $lead['id']) { $l['status'] = 'unsubscribed'; break; }
    }
    unset($l);
    vestra_save_leads($leads);
    $done = true;
}

$PAGE = 'Unsubscribe'; $NAV = ''; require __DIR__.'/inc/head.php';
?>
<div class="wrap" style="max-width:520px">
  <div style="text-align:center;padding:70px 20px">
    <?php if (!$lead && !$done): ?>
      <div style="font-size:44px;margin-bottom:16px">🔍</div>
      <h2 style="margin:0 0 10px">Link not found</h2>
      <p style="color:var(--mut)">This unsubscribe link is invalid or has already been used.</p>
    <?php elseif ($done): ?>
      <div style="font-size:44px;margin-bottom:16px">✓</div>
      <h2 style="margin:0 0 10px">You're unsubscribed</h2>
      <p style="color:var(--mut)">We won't email <?= htmlspecialchars($lead['company'] ?? '') ?> again.</p>
    <?php else: ?>
      <div style="font-size:44px;margin-bottom:16px">✉️</div>
      <h2 style="margin:0 0 10px">Unsubscribe from VESTRA outreach?</h2>
      <p style="color:var(--mut);margin-bottom:24px">
        <?= htmlspecialchars($lead['email'] ?? '') ?><?= !empty($lead['company']) ? ' — ' . htmlspecialchars($lead['company']) : '' ?>
        will no longer receive invitation emails from VESTRA.
      </p>
      <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <button class="btn btn-p" type="submit">Confirm unsubscribe</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
