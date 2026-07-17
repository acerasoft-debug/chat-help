<?php
/**
 * VESTRA — Stripe Connect onboarding for sellers.
 * Creates (or reuses) the seller's Express connected account and redirects them
 * to Stripe's hosted onboarding (identity + bank details). On completion Stripe
 * returns the seller to /seller?tab=profile&connect=done.
 */
require_once __DIR__ . '/../inc/i18n.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/stripe.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user = auth_user();
if (!$user || ($user['type'] ?? '') !== 'seller') {
    header('Location: /login?back=' . urlencode('/seller?tab=profile'));
    exit;
}
if (!stripe_available()) {
    header('Location: /seller?tab=profile&error=notready');
    exit;
}

// One-click login to the seller's own Stripe Express dashboard (once onboarded).
if (($_GET['dashboard'] ?? '') === '1' && !empty($user['stripe_account_id'])) {
    try {
        header('Location: ' . stripe_connect_dashboard_link($user['stripe_account_id']));
        exit;
    } catch (\Throwable $e) {
        error_log('[VESTRA Connect] dashboard link error: ' . $e->getMessage());
        $_SESSION['connect_error'] = $e->getMessage();
        header('Location: /seller?tab=profile&error=connect');
        exit;
    }
}

try {
    $acctId = $user['stripe_account_id'] ?? '';
    if ($acctId === '') {
        $acctId = stripe_connect_create_account($user);
    }
    $url = stripe_connect_onboarding_link($acctId);
    header('Location: ' . $url);
    exit;
} catch (\Throwable $e) {
    error_log('[VESTRA Connect] onboarding error: ' . $e->getMessage());
    // Surface Stripe's own message (e.g. "Invalid country", "Connect not
    // enabled") — for a handful of sellers this is far more useful than a
    // generic "something went wrong", and Stripe's onboarding errors are safe
    // to show. Cleared once displayed on the profile page.
    $_SESSION['connect_error'] = $e->getMessage();
    header('Location: /seller?tab=profile&error=connect');
    exit;
}
