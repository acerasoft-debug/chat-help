<?php
/**
 * VESTRA — Stripe helper functions.
 * All credentials come from environment variables (never hardcoded).
 * Requires composer install (stripe/stripe-php ^14).
 */
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';

/** True if composer dependencies are installed. Loads the Stripe autoloader as a side effect. */
function stripe_available(): bool {
    static $ok = null;
    if ($ok === null) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        $ok = is_file($autoload);
        if ($ok) require_once $autoload;
    }
    return $ok;
}

function stripe_client(): \Stripe\StripeClient {
    if (!stripe_available()) throw new \RuntimeException('Stripe library not installed — run composer install.');
    static $c;
    if (!$c) {
        $key = getenv('STRIPE_SECRET_KEY') ?: '';
        if (!$key) throw new \RuntimeException('STRIPE_SECRET_KEY not configured in .env');
        $c = new \Stripe\StripeClient($key);
    }
    return $c;
}

function stripe_pk(): string {
    return getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
}

function stripe_price(string $tier): string {
    $map = [
        'starter'    => getenv('PRICE_STARTER')   ?: '',
        'pro'        => getenv('PRICE_PRO')        ?: '',
        'premium'    => getenv('PRICE_PREMIUM')    ?: '',
        'onboarding' => getenv('PRICE_ONBOARDING') ?: '',
    ];
    $id = $map[$tier] ?? '';
    if (!$id) throw new \RuntimeException("Price ID not set for tier '{$tier}' — check .env");
    return $id;
}

function stripe_sepa_enabled(): bool {
    return getenv('SEPA_ENABLED') === '1';
}

/** Get existing Stripe Customer ID or create one, persisting it to accounts.json. */
function stripe_ensure_customer(array $account): string {
    if (!empty($account['stripe_customer_id'])) return $account['stripe_customer_id'];
    $label = trim(($account['name'] ?? '') . ' — ' . ($account['company'] ?? ''));
    $customer = stripe_client()->customers->create([
        'email'    => $account['email'],
        'name'     => $label,
        'metadata' => ['seller_id' => $account['id'], 'vat_id' => $account['vat_id'] ?? ''],
    ]);
    auth_update($account['id'], ['stripe_customer_id' => $customer->id]);
    return $customer->id;
}

/** Find an account by Stripe customer ID (linear scan — fine for JSON store). */
function stripe_find_account(string $customerId): ?array {
    foreach (auth_accounts() as $a) {
        if (($a['stripe_customer_id'] ?? '') === $customerId) return $a;
    }
    return null;
}

/** Human-readable membership status label for admin UI. */
function stripe_status_badge(string $status): string {
    return match($status) {
        'trialing'  => '<span style="color:#f0c060">⏳ Trial</span>',
        'active'    => '<span style="color:#7ad6a0">✓ Active</span>',
        'past_due'  => '<span style="color:#ef9a9a">⚠ Past due</span>',
        'canceled'  => '<span style="color:#888">✗ Canceled</span>',
        default     => '<span style="color:#555">— None</span>',
    };
}
