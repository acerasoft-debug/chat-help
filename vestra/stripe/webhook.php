<?php
/**
 * VESTRA — Stripe webhook handler.
 * POST /stripe/webhook
 *
 * Events handled:
 *   checkout.session.completed    → set membership trialing, save sub ID + tier
 *   invoice.paid                  → detect onboarding fee, set pending_review, notify admin
 *   customer.subscription.updated → sync status (trialing→active, past_due, etc.)
 *   customer.subscription.deleted → cancel membership, deactivate listings
 *   invoice.payment_failed        → set past_due, email seller
 *
 * Storage: updates accounts.json via auth_update() (no MySQL yet).
 * IMPORTANT: verify signature before ANY processing.
 */
require_once __DIR__ . '/../inc/env.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/products.php';
require_once __DIR__ . '/../inc/notify.php';
require_once __DIR__ . '/../inc/stripe.php';

if (!stripe_available()) {
    http_response_code(503); echo 'Stripe library not installed'; exit;
}

// Must read raw body before any output or other reads
$payload   = (string) file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret    = getenv('STRIPE_WEBHOOK_SECRET') ?: '';

if (!$secret || !$sigHeader || $payload === '') {
    http_response_code(400); echo 'Bad request'; exit;
}

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); echo 'Invalid signature'; exit;
} catch (\Throwable $e) {
    error_log('[VESTRA webhook] Parse error: ' . $e->getMessage());
    http_response_code(400); echo 'Webhook error'; exit;
}

$obj  = $event->data->object;
$type = $event->type;

switch ($type) {

    // ── checkout.session.completed → trial started ────────────────────────
    case 'checkout.session.completed':
        if (($obj->mode ?? '') !== 'subscription') break;
        $sellerId = $obj->metadata->seller_id ?? '';
        $tier     = $obj->metadata->tier      ?? '';
        $subId    = $obj->subscription        ?? '';
        if (!$sellerId || !$tier || !$subId) break;
        auth_update($sellerId, [
            'stripe_subscription_id' => $subId,
            'membership_tier'        => $tier,
            'membership_status'      => 'trialing',
        ]);
        break;

    // ── invoice.paid → check for onboarding one-time charge ──────────────
    case 'invoice.paid':
        $customerId = $obj->customer ?? '';
        if (!$customerId) break;
        $account = stripe_find_account($customerId);
        if (!$account) break;

        // Detect the onboarding line item by price ID
        $onboardingPriceId = getenv('PRICE_ONBOARDING') ?: '';
        $paidOnboarding = false;
        if ($onboardingPriceId) {
            foreach (($obj->lines->data ?? []) as $line) {
                if (($line->price->id ?? '') === $onboardingPriceId) {
                    $paidOnboarding = true; break;
                }
            }
        }

        if ($paidOnboarding && !($account['onboarding_paid'] ?? false)) {
            auth_update($account['id'], [
                'onboarding_paid'     => true,
                'verification_status' => 'pending_review',
            ]);
            vestra_notify(
                'Seller onboarding paid — badge review: ' . ($account['company'] ?: $account['name']),
                "Onboarding fee received from {$account['name']} ({$account['email']}).\n\n" .
                "Verification status: pending review.\n" .
                "Approve the Verified Seller badge in Admin → Users.\n\n" .
                "Admin: https://vestrasales.com/admin?tab=users"
            );
        }

        // Sync current_period_end for recurring invoices
        $periodEnd = ($obj->lines->data[0] ?? null)?->period?->end ?? null;
        if ($periodEnd) {
            auth_update($account['id'], ['current_period_end' => date('c', $periodEnd)]);
        }
        break;

    // ── customer.subscription.updated → status sync ───────────────────────
    case 'customer.subscription.updated':
        $customerId = $obj->customer ?? '';
        if (!$customerId) break;
        $account = stripe_find_account($customerId);
        if (!$account) break;

        $updates = ['membership_status' => $obj->status ?? 'none'];
        $tier = $obj->metadata->tier ?? '';
        if ($tier) $updates['membership_tier'] = $tier;
        if ($obj->trial_end) $updates['trial_ends_at'] = date('c', $obj->trial_end);
        if ($obj->current_period_end) $updates['current_period_end'] = date('c', $obj->current_period_end);
        auth_update($account['id'], $updates);
        break;

    // ── customer.subscription.deleted → cancel + deactivate listings ──────
    case 'customer.subscription.deleted':
        $customerId = $obj->customer ?? '';
        if (!$customerId) break;
        $account = stripe_find_account($customerId);
        if (!$account) break;

        auth_update($account['id'], [
            'membership_status' => 'canceled',
            'verified_badge'    => false,
        ]);

        // Suspend all approved listings belonging to this seller
        $all = vestra_listings(); $changed = false;
        foreach ($all as &$listing) {
            if (($listing['seller_uid'] ?? '') === $account['id'] && ($listing['status'] ?? '') === 'approved') {
                $listing['status'] = 'suspended'; $changed = true;
            }
        }
        unset($listing);
        if ($changed) vestra_save_listings($all);

        vestra_send_mail(
            $account['email'],
            'VESTRA — your membership has ended',
            "Hello {$account['name']},\n\n" .
            "Your VESTRA seller membership has been cancelled and your listings deactivated.\n\n" .
            "To reactivate, visit: https://vestrasales.com/membership\n\n" .
            "— VESTRA · vestrasales.com"
        );
        break;

    // ── invoice.payment_failed → past_due + warn seller ──────────────────
    case 'invoice.payment_failed':
        $customerId = $obj->customer ?? '';
        if (!$customerId) break;
        $account = stripe_find_account($customerId);
        if (!$account) break;

        auth_update($account['id'], ['membership_status' => 'past_due']);

        vestra_send_mail(
            $account['email'],
            'VESTRA — membership payment failed',
            "Hello {$account['name']},\n\n" .
            "We were unable to collect your VESTRA membership payment.\n\n" .
            "Please update your payment method to keep your listings active:\n" .
            "https://vestrasales.com/membership\n\n" .
            "— VESTRA · vestrasales.com"
        );
        break;
}

http_response_code(200);
echo 'ok';
