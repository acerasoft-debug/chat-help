<?php
/**
 * VESTRA — Stripe Checkout session creator.
 * POST /stripe/checkout  (body: tier=starter|pro|premium)
 * Creates a subscription session: 30-day trial + 89,90 € one-time onboarding.
 * Day 0 charge = 89,90 € only; monthly subscription starts on day 30.
 */
require_once __DIR__ . '/../inc/i18n.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/stripe.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /membership'); exit; }

$user = auth_user();
if (!$user || ($user['type'] ?? '') !== 'seller') {
    header('Location: /login?back=' . urlencode('/membership'));
    exit;
}

$tier = $_POST['tier'] ?? '';
if (!in_array($tier, ['starter', 'pro', 'premium'], true)) {
    header('Location: /membership');
    exit;
}

if (!stripe_configured()) {
    header('Location: /membership?error=notready');
    exit;
}

try {
    $customerId = stripe_ensure_customer($user);

    $paymentMethods = stripe_sepa_enabled() ? ['sepa_debit', 'card'] : ['card'];

    $session = stripe_api('POST', '/v1/checkout/sessions', [
        'mode'       => 'subscription',
        'customer'   => $customerId,
        'line_items' => [['price' => stripe_price($tier), 'quantity' => 1]],
        'subscription_data' => [
            'trial_period_days' => 30,
            'add_invoice_items' => [['price' => stripe_price('onboarding'), 'quantity' => 1]],
            'metadata'          => ['seller_id' => $user['id'], 'tier' => $tier],
        ],
        'payment_method_types' => $paymentMethods,
        'metadata'    => ['seller_id' => $user['id'], 'tier' => $tier],
        'success_url' => 'https://vestrasales.com/membership/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => 'https://vestrasales.com/membership/cancel',
    ]);

    header('Location: ' . $session->url);
    exit;

} catch (\Throwable $e) {
    error_log('[VESTRA Stripe] Checkout error: ' . $e->getMessage());
    header('Location: /membership?error=1');
    exit;
}
