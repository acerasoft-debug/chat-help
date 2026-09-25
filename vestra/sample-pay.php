<?php
/**
 * VESTRA — operator-issued sample PAY LINK (25 Sep 2026).
 * GET /sample-pay?ref=SPL-xxxxxxxx&t=<pay_token>
 *
 * The link in the buyer's e-mail. It never expires on its own: a Stripe
 * Checkout Session lives 24 hours, so this page REUSES the stored session while
 * Stripe still calls it open and creates a fresh one once it has expired —
 * "le lien reste valable jusqu'au paiement" has to be true.
 *
 * Double-charge guard: before any NEW session is created, the stored one is
 * asked whether it was already paid (webhook late or lost). A paid session is
 * reconciled here and the buyer goes to the confirmation, never to a second
 * checkout.
 *
 * No login: the buyer arrives from an e-mail. The 32-hex token is the credential
 * (hash_equals in sample_link_token_ok) and it never appears in a log.
 */
require __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/samples.php';
require_once __DIR__ . '/inc/stripe.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$ref = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['ref'] ?? ''));
$tok = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['t'] ?? '')));
$rec = $ref !== '' ? sample_get($ref) : null;
if (!sample_link_token_ok($rec, $tok)) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

$confirmUrl = '/sample-confirm?ref=' . rawurlencode($ref) . '&t=' . rawurlencode($tok);
if (($rec['status'] ?? '') !== 'pending') { header('Location: ' . $confirmUrl); exit; }

$fail = '';
try {
    if (!stripe_available()) throw new \RuntimeException('stripe not configured');

    if (!empty($rec['session_id'])) {
        $old = stripe_api('GET', '/v1/checkout/sessions/' . $rec['session_id']);
        if (($old->payment_status ?? '') === 'paid') {
            $pi = is_string($old->payment_intent ?? null) ? $old->payment_intent : ($old->payment_intent->id ?? '');
            $paidRec = sample_mark_paid($ref, $pi, sample_ship_from_session($old));
            if ($paidRec) sample_fulfill($paidRec);
            header('Location: ' . $confirmUrl . '&paid=1'); exit;
        }
        if (($old->status ?? '') === 'open' && !empty($old->url)) { header('Location: ' . $old->url); exit; }
    }

    $buyer = null;
    foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === ($rec['buyer_id'] ?? '')) { $buyer = $a; break; } }
    $session = sample_link_session($rec, $buyer);
    sample_update($ref, ['session_id' => $session->id ?? '']);
    header('Location: ' . $session->url); exit;
} catch (\Throwable $e) {
    error_log('[VESTRA Sample] pay link ' . $ref . ': ' . $e->getMessage());
    $fail = 'x';
}

$PAGE = t('Payment');
$NAV = 'shop';
$NOINDEX = true;
require __DIR__ . '/inc/head.php';
?>
<div class="wrap" style="max-width:640px;margin:60px auto;text-align:center">
  <div style="font-size:48px">⏳</div>
  <h1><?= t('Payment') ?></h1>
  <p class="hint">The payment page could not be opened right now. Please try the link again in a few minutes, or reply to our e-mail — support@vestrasales.com.</p>
  <p class="hint"><?= htmlspecialchars($ref) ?></p>
</div>
<?php require __DIR__ . '/inc/foot.php'; ?>
