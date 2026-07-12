<?php
/**
 * VESTRA — Stripe Customer Billing Portal launcher.
 * POST /stripe/portal
 *
 * Sends the logged-in seller to Stripe's hosted billing portal, where they can
 * switch plans (upgrade/downgrade), update their payment method, download
 * invoices, and cancel — everything self-service, nothing to build or maintain
 * on our side. Requires only STRIPE_SECRET_KEY, no price IDs.
 *
 * NOTE: the portal itself must be activated once in the Stripe Dashboard
 * (Settings → Billing → Customer portal → Activate); plan switching between
 * Starter/Pro/Elite is enabled there too.
 */
require_once __DIR__ . '/../inc/i18n.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/stripe.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /seller?tab=profile'); exit; }

$user = auth_user();
if (!$user || ($user['type'] ?? '') !== 'seller') {
    header('Location: /login?back=' . urlencode('/seller?tab=profile')); exit;
}
if (!stripe_available()) { header('Location: /seller?tab=profile&error=notready'); exit; }

/* No Stripe customer yet = never subscribed — nothing to manage, pick a plan first. */
if (empty($user['stripe_customer_id'])) { header('Location: /membership'); exit; }

try {
    $session = stripe_api('POST', '/v1/billing_portal/sessions', [
        'customer'   => $user['stripe_customer_id'],
        'return_url' => 'https://vestrasales.com/seller?tab=profile',
    ]);
    header('Location: ' . $session->url); exit;
} catch (\Throwable $e) {
    error_log('[VESTRA Stripe] Portal error: ' . $e->getMessage());
    header('Location: /seller?tab=profile&error=1'); exit;
}
