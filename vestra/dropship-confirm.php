<?php
/**
 * VESTRA — dropship order confirmation ("thank you") page.
 * Shown right after Stripe Checkout for an order placed via api/dropship.php.
 * There is no VESTRA login here — the buyer is a partner's end customer —
 * so access is by possession of the ref (an unguessable token), same trust
 * model as any Stripe-hosted receipt link.
 *
 * Mirrors sample-confirm.php's reconcile-on-arrival pattern: if the buyer
 * returns with ?paid=1 but the webhook hasn't landed yet, verify the
 * Checkout Session directly and fulfil it here — keeps confirmation
 * reliable without depending on webhook delivery timing.
 */
require __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/dropship.php';

$ref = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['ref'] ?? '');
$rec = $ref !== '' ? dropship_get($ref) : null;
if (!$rec) { header('Location: /'); exit; }

if (($rec['status'] ?? '') === 'pending' && isset($_GET['paid'])) {
    require_once __DIR__ . '/inc/stripe.php';
    try {
        // A direct-charge order's session lives on the SELLER's connected account,
        // not the platform — same Stripe-Account routing stripe_escrow_checkout()
        // used to create it in the first place.
        $sess = stripe_api('GET', '/v1/checkout/sessions/' . $rec['session_id'], [], $rec['acct_id'] ?? '');
        if (($sess->payment_status ?? '') === 'paid') {
            $pi = is_string($sess->payment_intent ?? null) ? $sess->payment_intent : ($sess->payment_intent->id ?? '');
            $shipTo = null;
            $sa = $sess->shipping_details->address ?? $sess->shipping->address ?? null;
            if ($sa) {
                $shipTo = [
                    'name'        => $sess->shipping_details->name ?? ($sess->shipping->name ?? ''),
                    'line1'       => $sa->line1 ?? '', 'line2' => $sa->line2 ?? '',
                    'postal_code' => $sa->postal_code ?? '', 'city' => $sa->city ?? '', 'country' => $sa->country ?? '',
                ];
            }
            $paidRec = dropship_mark_paid($ref, $pi, $shipTo);
            if ($paidRec) { dropship_fulfill($paidRec); $rec = dropship_get($ref); }
        }
    } catch (\Throwable $e) { error_log('[VESTRA dropship] confirm reconcile failed ' . $ref . ': ' . $e->getMessage()); }
}

$paid = ($rec['status'] ?? '') === 'paid';
$amount = number_format((float)($rec['amount'] ?? 0), 2);
$PAGE = $paid ? t('Order confirmed') : t('Finishing up…');
$NAV = 'shop';
require __DIR__ . '/inc/head.php';
?>
<div class="wrap" style="max-width:640px;margin:60px auto;text-align:center">
  <?php if ($paid): ?>
    <div style="font-size:48px">✅</div>
    <h1><?= t('Order confirmed') ?></h1>
    <p class="hint"><?= t('Order ref') ?>: <b><?= htmlspecialchars($rec['ref']) ?></b></p>
    <p><?= htmlspecialchars(trim($rec['brand'] . ' ' . $rec['name'])) ?> — <?= htmlspecialchars($rec['colour'] . ' / ' . $rec['size']) ?><?= (int)$rec['qty'] > 1 ? ' × '.(int)$rec['qty'] : '' ?></p>
    <p class="hint"><?= t('Amount paid') ?>: <b>€<?= $amount ?></b> (<?= t('plus shipping') ?>)</p>
    <?php if (!empty($rec['customer_email'])): ?>
    <p><?= t('A confirmation email is on its way to') ?> <b><?= htmlspecialchars($rec['customer_email']) ?></b>.</p>
    <?php endif; ?>
  <?php else: ?>
    <div style="font-size:48px">⏳</div>
    <h1><?= t('Finishing up…') ?></h1>
    <p class="hint"><?= t('Payment is still being confirmed. Refresh this page in a moment.') ?></p>
  <?php endif; ?>
  <p style="margin-top:24px"><a class="btn btn-o" href="/shop"><?= t('Continue browsing') ?></a></p>
</div>
<?php require __DIR__ . '/inc/foot.php'; ?>
