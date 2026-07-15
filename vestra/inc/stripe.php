<?php
/**
 * VESTRA — Stripe helpers, dependency-free (no composer / no SDK).
 *
 * Talks to the Stripe REST API directly over curl and verifies webhook
 * signatures with a hand-rolled HMAC check — the same philosophy as the
 * hand-rolled PDF writer in inc/pdf.php: nothing to "composer install" on
 * the server, so a deploy can never break payments by forgetting vendor/.
 *
 * All credentials come from environment variables loaded by inc/env.php
 * from a .env file stored one level ABOVE the document root:
 *   STRIPE_SECRET_KEY      sk_live_… / sk_test_…
 *   STRIPE_PUBLISHABLE_KEY pk_live_… / pk_test_…   (not used server-side yet)
 *   STRIPE_WEBHOOK_SECRET  whsec_…                  (from the webhook endpoint)
 *   PRICE_STARTER / PRICE_PRO / PRICE_PREMIUM / PRICE_ONBOARDING  price_…
 *   SEPA_ENABLED           1 to offer SEPA debit next to cards
 * See .env.example in the repo root for a template.
 */
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';

/** True when Stripe can actually be used: curl present + secret key configured. */
function stripe_available(): bool {
    return function_exists('curl_init') && (getenv('STRIPE_SECRET_KEY') ?: '') !== '';
}

/** True when every key the membership flow needs is present (for status banners). */
function stripe_configured(): bool {
    foreach (['STRIPE_SECRET_KEY','STRIPE_WEBHOOK_SECRET','PRICE_STARTER','PRICE_PRO','PRICE_PREMIUM','PRICE_ONBOARDING'] as $k) {
        if ((getenv($k) ?: '') === '') return false;
    }
    return function_exists('curl_init');
}

/** Names of required env keys that are still missing (for the admin banner). */
function stripe_missing_keys(): array {
    $missing = [];
    foreach (['STRIPE_SECRET_KEY','STRIPE_WEBHOOK_SECRET','PRICE_STARTER','PRICE_PRO','PRICE_PREMIUM','PRICE_ONBOARDING'] as $k) {
        if ((getenv($k) ?: '') === '') $missing[] = $k;
    }
    return $missing;
}

function stripe_pk(): string {
    return getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
}

function stripe_price(string $tier): string {
    $map = [
        'starter'    => getenv('PRICE_STARTER')    ?: '',
        'pro'        => getenv('PRICE_PRO')        ?: '',
        'premium'    => getenv('PRICE_PREMIUM')    ?: '',
        'onboarding' => getenv('PRICE_ONBOARDING') ?: '',
    ];
    $id = $map[$tier] ?? '';
    if (!$id) throw new \RuntimeException("Price ID not set for tier '{$tier}' — check .env");
    if (str_starts_with($id, 'prod_')) return stripe_resolve_product_price($id);
    return $id;
}

/**
 * Allow PRICE_* env vars to hold a prod_… ID instead of a price_… ID: resolve the
 * product's default price (falling back to its newest active price) via the API,
 * and cache the result on disk so this costs one API call per product, ever.
 * Product IDs are what the Dashboard shows most prominently — accepting them
 * directly removes the most error-prone step of the setup.
 */
function stripe_resolve_product_price(string $productId): string {
    $cacheFile = __DIR__ . '/../data/stripe_price_cache.json';
    $cache = is_readable($cacheFile) ? (json_decode((string)file_get_contents($cacheFile), true) ?: []) : [];
    if (!empty($cache[$productId])) return $cache[$productId];

    $product = stripe_api('GET', '/v1/products/' . $productId);
    $price = is_string($product->default_price ?? null)
        ? $product->default_price
        : ($product->default_price->id ?? '');
    if (!$price) {
        $list = stripe_api('GET', '/v1/prices', ['product' => $productId, 'active' => 'true', 'limit' => 1]);
        $price = $list->data[0]->id ?? '';
    }
    if (!$price) {
        throw new \RuntimeException("Stripe product {$productId} has no active price — add one in the Dashboard (Products → Pricing) first.");
    }

    $cache[$productId] = $price;
    if (!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0775, true);
    @file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT), LOCK_EX);
    return $price;
}

function stripe_sepa_enabled(): bool {
    return getenv('SEPA_ENABLED') === '1';
}

/**
 * One call against the Stripe API. Params are form-encoded exactly the way
 * Stripe expects nested structures (a[b][0][c]=…), which is what PHP's
 * http_build_query produces. Returns the decoded JSON as stdClass.
 * Throws RuntimeException with Stripe's own message on any error.
 */
function stripe_api(string $method, string $path, array $params = []): object {
    if (!stripe_available()) throw new \RuntimeException('Stripe not configured — STRIPE_SECRET_KEY missing in .env');
    $ch = curl_init('https://api.stripe.com'.$path);
    $headers = [
        'Authorization: Bearer '.(getenv('STRIPE_SECRET_KEY') ?: ''),
        'Stripe-Version: 2024-06-20',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if (strtoupper($method) === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    } elseif ($params) {
        curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com'.$path.'?'.http_build_query($params));
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch); curl_close($ch);
        throw new \RuntimeException('Stripe request failed: '.$err);
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $json = json_decode((string)$body);
    if (!is_object($json)) throw new \RuntimeException('Stripe returned invalid JSON (HTTP '.$status.')');
    if ($status >= 400) {
        $msg = $json->error->message ?? ('HTTP '.$status);
        throw new \RuntimeException('Stripe error: '.$msg);
    }
    return $json;
}

/**
 * Verify a webhook's Stripe-Signature header and return the decoded event.
 * Scheme (documented by Stripe): header is "t=<unix>,v1=<hex>,…" and
 * v1 = HMAC-SHA256("<t>.<raw body>", endpoint_secret). Reject when no v1
 * matches or the timestamp is older than $tolerance seconds (replay guard).
 * Returns the event as stdClass, or null when the signature is invalid.
 */
function stripe_webhook_verify(string $payload, string $sigHeader, string $secret, int $tolerance = 300): ?object {
    $t = null; $v1s = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 't')  $t = (int)$kv[1];
        if ($kv[0] === 'v1') $v1s[] = $kv[1];
    }
    if (!$t || !$v1s) return null;
    if (abs(time() - $t) > $tolerance) return null;
    $expected = hash_hmac('sha256', $t.'.'.$payload, $secret);
    $ok = false;
    foreach ($v1s as $sig) { if (hash_equals($expected, $sig)) { $ok = true; break; } }
    if (!$ok) return null;
    $event = json_decode($payload);
    return is_object($event) ? $event : null;
}

/** Get existing Stripe Customer ID or create one, persisting it to accounts.json. */
function stripe_ensure_customer(array $account): string {
    if (!empty($account['stripe_customer_id'])) return $account['stripe_customer_id'];
    $label = trim(($account['name'] ?? '') . ' — ' . ($account['company'] ?? ''));
    $customer = stripe_api('POST', '/v1/customers', [
        'email'    => $account['email'],
        'name'     => $label,
        'metadata' => ['seller_id' => $account['id'], 'vat_id' => $account['vat_id'] ?? ''],
    ]);
    auth_update($account['id'], ['stripe_customer_id' => $customer->id]);
    return $customer->id;
}

/* ── Stripe Connect (seller payouts / escrow) ───────────────────────────────
 * Sellers onboard an Express connected account so escrow funds can be released
 * to them automatically. We store the acct_… id on the seller's account. */

/** Create an Express connected account for a seller, persist its id, return it. */
function stripe_connect_create_account(array $seller): string {
    if (!empty($seller['stripe_account_id'])) return $seller['stripe_account_id'];
    $country = strtoupper(substr(trim($seller['country'] ?? 'DE'), 0, 2)) ?: 'DE';
    $acct = stripe_api('POST', '/v1/accounts', [
        'type'            => 'express',
        'email'           => $seller['email'] ?? '',
        'country'         => $country,
        'business_type'   => 'company',
        'capabilities'    => ['card_payments' => ['requested' => 'true'], 'transfers' => ['requested' => 'true']],
        'business_profile'=> ['name' => $seller['company'] ?: ($seller['name'] ?: 'VESTRA seller')],
        'metadata'        => ['seller_id' => $seller['id'] ?? ''],
    ]);
    auth_update($seller['id'], ['stripe_account_id' => $acct->id]);
    return $acct->id;
}

/** Hosted onboarding link (KYC + bank details) for a connected account. */
function stripe_connect_onboarding_link(string $acctId): string {
    $link = stripe_api('POST', '/v1/account_links', [
        'account'     => $acctId,
        'refresh_url' => 'https://vestrasales.com/stripe/connect',
        'return_url'  => 'https://vestrasales.com/seller?tab=profile&connect=done',
        'type'        => 'account_onboarding',
    ]);
    return $link->url;
}

/** One-click login link to the seller's Express dashboard (once onboarded). */
function stripe_connect_dashboard_link(string $acctId): string {
    $link = stripe_api('POST', '/v1/accounts/' . $acctId . '/login_links', []);
    return $link->url;
}

/** Live connection status for a seller's connected account. */
function stripe_connect_status(array $seller): array {
    $id = $seller['stripe_account_id'] ?? '';
    if ($id === '') return ['connected' => false];
    try {
        $a = stripe_api('GET', '/v1/accounts/' . $id);
        return [
            'connected'         => true,
            'id'                => $id,
            'charges_enabled'   => !empty($a->charges_enabled),
            'payouts_enabled'   => !empty($a->payouts_enabled),
            'details_submitted' => !empty($a->details_submitted),
            // fully ready to receive escrow payouts
            'ready'             => !empty($a->payouts_enabled) && !empty($a->details_submitted),
        ];
    } catch (\Throwable $e) {
        return ['connected' => false, 'error' => $e->getMessage()];
    }
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
