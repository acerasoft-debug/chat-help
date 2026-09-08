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

/* ALICI DA GIREBILIR (8 Eyl 2026). Portal eskiden yalniz saticiyaydi; toptan
   erisim aboneligi alicilara satilinca bu, IPTAL YOLU OLMAYAN bir abonelik
   satmak demek oldu. Satan her yerin iptal yolu da olmali. */
require_once __DIR__ . '/../inc/dropship.php';
$user = auth_user();
$isSeller  = $user && ($user['type'] ?? '') === 'seller';
$hasDsPlan = $user && (vestra_dropship_plan_active($user)
             || ($user['dropship_plan_status'] ?? 'none') !== 'none');
$backTo    = $isSeller ? '/seller?tab=profile' : '/dropship';
/* Hata eki: /dropship'te '?' yok, duz '&error=' bozuk adres uretirdi. */
$backErr   = $backTo . (str_contains($backTo, '?') ? '&' : '?');
if (!$user || (!$isSeller && !$hasDsPlan)) {
    header('Location: /login?back=' . urlencode($backTo)); exit;
}
if (!stripe_available()) { header('Location: ' . $backErr . 'error=notready'); exit; }

/* No Stripe customer yet = never subscribed — nothing to manage, pick a plan first. */
if (empty($user['stripe_customer_id'])) { header('Location: ' . ($isSeller ? '/membership' : '/dropship')); exit; }

try {
    $session = stripe_api('POST', '/v1/billing_portal/sessions', [
        'customer'   => $user['stripe_customer_id'],
        'return_url' => 'https://vestrasales.com' . $backTo,
    ]);
    header('Location: ' . $session->url); exit;
} catch (\Throwable $e) {
    error_log('[VESTRA Stripe] Portal error: ' . $e->getMessage());
    header('Location: ' . $backErr . 'error=1'); exit;
}
