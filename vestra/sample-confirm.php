<?php
/**
 * VESTRA — sample order confirmation ("thank you") page.
 * Shown right after Stripe Checkout for a "Muster Bestellung" sample order.
 *
 * Mirrors order-confirm.php's escrow fallback: if the buyer returns with
 * ?paid=1 but the webhook hasn't marked the order paid yet, verify the
 * Checkout Session directly and fulfil it here — makes confirmation
 * reliable without depending on webhook delivery timing.
 */
require __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/samples.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$ref = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['ref'] ?? '');
$rec = $ref !== '' ? sample_get($ref) : null;
if (!$rec) { header('Location: /'); exit; }

$me = auth_user();
$allowed = $me && ($rec['buyer_id'] ?? '') === ($me['id'] ?? '');
if (!$allowed && !empty($_SESSION['vadmin'])) $allowed = true;
if (!$allowed) { header('Location: /login?back=' . urlencode('/sample-confirm?ref=' . $ref)); exit; }

if (($rec['status'] ?? '') === 'pending' && isset($_GET['paid'])) {
    require_once __DIR__ . '/inc/stripe.php';
    try {
        $sess = stripe_api('GET', '/v1/checkout/sessions/' . $rec['session_id']);
        if (($sess->payment_status ?? '') === 'paid') {
            $pi = is_string($sess->payment_intent ?? null) ? $sess->payment_intent : ($sess->payment_intent->id ?? '');
            $paidRec = sample_mark_paid($ref, $pi);
            if ($paidRec) { sample_fulfill($paidRec); $rec = sample_get($ref); }
        }
    } catch (\Throwable $e) { error_log('[VESTRA Sample] confirm reconcile failed ' . $ref . ': ' . $e->getMessage()); }
}

$paid = ($rec['status'] ?? '') === 'paid';
$amount = number_format((float)($rec['amount'] ?? 0), 2);
$PAGE = $paid ? t('Sample order confirmed') : t('Finishing up…');
$NAV = 'shop';
require __DIR__ . '/inc/head.php';
?>
<div class="wrap" style="max-width:640px;margin:60px auto;text-align:center">
  <?php if ($paid): ?>
    <div style="font-size:48px">✅</div>
    <h1><?= t('Sample order confirmed') ?></h1>
    <p class="hint"><?= t('Order ref') ?>: <b><?= htmlspecialchars($rec['ref']) ?></b></p>
    <p><?= htmlspecialchars($rec['brand'] . ' ' . $rec['name']) ?></p>
    <p class="hint"><?= t('Amount paid') ?>: <b>€<?= $amount ?></b> · <?= t('EU-wide shipping included') ?></p>
    <?php if (!empty(trim((string)($rec['note'] ?? '')))): ?>
    <p class="hint"><?= t('Size / note') ?>: <?= htmlspecialchars($rec['note']) ?></p>
    <?php endif; ?>
    <p class="hint"><?= t('The exact size you requested may not always be available — we ship the closest match from current sample stock.') ?></p>
    <p><?= t('A confirmation email is on its way to') ?> <b><?= htmlspecialchars($rec['buyer_email']) ?></b>.</p>
  <?php else: ?>
    <div style="font-size:48px">⏳</div>
    <h1><?= t('Finishing up…') ?></h1>
    <p class="hint"><?= t('Payment is still being confirmed. Refresh this page in a moment.') ?></p>
  <?php endif; ?>
  <p style="margin-top:24px"><a class="btn btn-o" href="/shop"><?= t('Continue browsing') ?></a></p>
</div>
<?php require __DIR__ . '/inc/foot.php'; ?>
