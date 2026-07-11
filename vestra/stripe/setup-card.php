<?php
/**
 * VESTRA — save a seller's commission card via Stripe Checkout (mode=setup).
 * POST /stripe/setup-card
 *
 * This card is charged automatically for the 3.5% platform commission when an
 * order is marked paid (inc/commission.php) — it is completely separate from
 * the seller's bank_iban/bank_holder fields, which are for RECEIVING buyer
 * payments and are never touched here. No amount is charged at setup time;
 * the resulting payment method is just saved as the customer's default for
 * later off-session charges (webhook.php stores it on the account).
 */
require_once __DIR__ . '/../inc/i18n.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/stripe.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /seller?tab=profile'); exit; }

$user = auth_user();
if (!$user || ($user['type'] ?? '') !== 'seller') {
    header('Location: /login?back=' . urlencode('/seller?tab=profile'));
    exit;
}

if (!stripe_configured()) {
    header('Location: /seller?tab=profile&error=notready');
    exit;
}

try {
    $customerId = stripe_ensure_customer($user);

    $session = stripe_api('POST', '/v1/checkout/sessions', [
        'mode'                  => 'setup',
        'customer'              => $customerId,
        'payment_method_types'  => ['card'],
        'metadata'              => ['seller_id' => $user['id'], 'purpose' => 'commission_card'],
        'success_url' => 'https://vestrasales.com/seller?tab=profile&cardok=1',
        'cancel_url'  => 'https://vestrasales.com/seller?tab=profile&cardcancel=1',
    ]);

    header('Location: ' . $session->url);
    exit;

} catch (\Throwable $e) {
    error_log('[VESTRA Stripe] Setup-card error: ' . $e->getMessage());
    header('Location: /seller?tab=profile&error=1');
    exit;
}
