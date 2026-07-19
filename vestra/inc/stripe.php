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
 *
 * $connectedAccount (an acct_… id) makes the request act ON that connected
 * account via the Stripe-Account header — the mechanism behind DIRECT CHARGES,
 * payouts and refunds in the escrow flow: the charge lives on the seller's
 * account (so chargeback liability is theirs), while application_fee_amount
 * still routes our commission to the platform.
 */
function stripe_api(string $method, string $path, array $params = [], string $connectedAccount = ''): object {
    if (!stripe_available()) throw new \RuntimeException('Stripe not configured — STRIPE_SECRET_KEY missing in .env');
    $ch = curl_init('https://api.stripe.com'.$path);
    $headers = [
        'Authorization: Bearer '.(getenv('STRIPE_SECRET_KEY') ?: ''),
        'Stripe-Version: 2024-06-20',
    ];
    if ($connectedAccount !== '') $headers[] = 'Stripe-Account: '.$connectedAccount;
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

/**
 * Normalise a free-text country field to a Stripe-valid ISO-3166 alpha-2 code.
 * The registration form is a free 3-char text input (placeholder "DE"), so real
 * values arrive as "DE", "GER", "Ger", "D", "TÜR", "TR", "AT"… A naive
 * substr($x,0,2) turns "GER"→"GE" and "TÜR"→"TÜ", which Stripe rejects with
 * "Invalid country" and the whole Connect onboarding dies. We map the common
 * alpha-3 codes and German/Turkish variants back to alpha-2, accept a known
 * alpha-2 as-is, and fall back to DE (this is a Germany-based EU wholesaler).
 */
function stripe_country_iso($raw): string {
    // Transliterate the umlauts/diacritics that appear in DE/TR spellings to
    // ASCII first (ü→U, ö→O, ä→A, ç→C, ş→S, ı→I …) so the lookup key is clean
    // regardless of PHP's non-multibyte strtoupper.
    $s = str_ireplace(
        ['ü','ö','ä','ß','ç','ş','ğ','ı','é','è'],
        ['u','o','a','ss','c','s','g','i','e','e'],
        (string)$raw);
    $c = strtoupper(preg_replace('/[^A-Za-z]/', '', $s));
    if ($c === '') return 'DE';
    // Alpha-3 and common name fragments → alpha-2.
    static $map = [
        'DEU'=>'DE','GER'=>'DE','GERMANY'=>'DE','DEUTSCHLAND'=>'DE','D'=>'DE',
        'AUT'=>'AT','OST'=>'AT','OESTERREICH'=>'AT',
        'CHE'=>'CH','SUI'=>'CH','SWI'=>'CH','SCHWEIZ'=>'CH',
        'FRA'=>'FR','FRANCE'=>'FR','FRANKREICH'=>'FR',
        'ITA'=>'IT','ITALY'=>'IT','ITALIA'=>'IT','ITALIEN'=>'IT',
        'ESP'=>'ES','SPA'=>'ES','SPAIN'=>'ES','SPANIEN'=>'ES',
        'NLD'=>'NL','NED'=>'NL','HOL'=>'NL','NETHERLANDS'=>'NL','NIEDERLANDE'=>'NL',
        'BEL'=>'BE','BELGIUM'=>'BE','BELGIEN'=>'BE',
        'LUX'=>'LU','PRT'=>'PT','POR'=>'PT','PORTUGAL'=>'PT',
        'IRL'=>'IE','IRELAND'=>'IE','GBR'=>'GB','UK'=>'GB','ENG'=>'GB',
        'DNK'=>'DK','DEN'=>'DK','SWE'=>'SE','NOR'=>'NO','FIN'=>'FI',
        'POL'=>'PL','POLAND'=>'PL','POLEN'=>'PL','CZE'=>'CZ',
        'SVK'=>'SK','HUN'=>'HU','ROU'=>'RO','ROM'=>'RO','BGR'=>'BG','BUL'=>'BG',
        'GRC'=>'GR','GRE'=>'GR','HRV'=>'HR','CRO'=>'HR','SVN'=>'SI','SLO'=>'SI',
        'EST'=>'EE','LVA'=>'LV','LTU'=>'LT','CYP'=>'CY','MLT'=>'MT',
        'USA'=>'US','TUR'=>'TR','TURKEY'=>'TR','TURKIYE'=>'TR',
    ];
    if (isset($map[$c])) return $map[$c];
    // Known EU/EEA + common alpha-2 codes we accept verbatim.
    static $valid = ['DE','AT','CH','FR','IT','ES','NL','BE','LU','PT','IE','GB',
        'DK','SE','NO','FI','PL','CZ','SK','HU','RO','BG','GR','HR','SI','EE',
        'LV','LT','CY','MT','US','TR'];
    $two = substr($c, 0, 2);
    return in_array($two, $valid, true) ? $two : 'DE';
}

/** Create an Express connected account for a seller, persist its id, return it.
 * Payout schedule is set to MANUAL: escrow funds land in the seller's Stripe
 * balance and stay there until the platform explicitly releases them (a payout)
 * on delivery confirmation — the mechanism that makes "delayed payout" escrow
 * work without the platform ever holding the money itself.
 *
 * We DON'T pass type=express. Instead we spell out the identical Express
 * configuration through `controller` properties. The one that matters:
 *   controller[losses][payments] = stripe  → the connected account (seller) is
 *   liable for payment losses / negative balances (the Express default).
 * Declaring loss liability explicitly here is what Stripe otherwise demands via
 * the dashboard "platform profile / manage losses" attestation — with it stated
 * in the API call, account creation no longer errors with "Please review the
 * responsibilities of managing losses for connected accounts". Every other
 * property below mirrors a standard Express account exactly, so fees, dashboard
 * and onboarding behave precisely as before. */
function stripe_connect_create_account(array $seller): string {
    if (!empty($seller['stripe_account_id'])) return $seller['stripe_account_id'];
    $country = stripe_country_iso($seller['country'] ?? 'DE');
    $acct = stripe_api('POST', '/v1/accounts', [
        'country'         => $country,
        'email'           => $seller['email'] ?? '',
        'business_type'   => 'company',
        'controller'      => [
            'losses'                 => ['payments' => 'stripe'],   // seller liable (Express default)
            'fees'                   => ['payer' => 'application'],  // Express default
            'requirement_collection' => 'stripe',                   // Stripe-hosted onboarding
            'stripe_dashboard'       => ['type' => 'express'],      // Express dashboard for the seller
        ],
        'capabilities'    => ['card_payments' => ['requested' => 'true'], 'transfers' => ['requested' => 'true']],
        'business_profile'=> ['name' => $seller['company'] ?: ($seller['name'] ?: 'VESTRA seller')],
        'settings'        => ['payouts' => ['schedule' => ['interval' => 'manual']]],
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

/* ── Escrow: direct charge + delayed payout ─────────────────────────────────
 * The trade money never touches a platform-owned balance. Instead:
 *   1. Buyer pays on the SELLER's connected account (a direct charge), so
 *      card/chargeback liability sits with the seller, not the platform.
 *   2. application_fee_amount skims the platform commission into our balance.
 *   3. The seller's payout schedule is MANUAL, so the seller's share is HELD
 *      in their Stripe balance — that hold IS the escrow.
 *   4. On delivery confirmation the platform RELEASES the hold (a payout to the
 *      seller's bank). Before release, the platform can REFUND the buyer in full
 *      (refund_application_fee so our commission comes back too and the seller's
 *      balance isn't pushed negative). */

/**
 * Create a hosted Checkout Session as a DIRECT CHARGE on the seller's connected
 * account. $lineItems is [['name'=>…, 'amount'=>cents, 'qty'=>n], …] (inline
 * price_data, so no pre-made Prices needed). $appFeeCents is the platform
 * commission. Returns the Session object (->url is the payment page).
 */
function stripe_escrow_checkout(string $acctId, array $lineItems, int $appFeeCents, string $ref, string $buyerEmail = '', string $currency = 'eur'): object {
    $params = [
        'mode'                => 'payment',
        'success_url'         => 'https://vestrasales.com/order-confirm?ref='.rawurlencode($ref).'&paid=1',
        'cancel_url'          => 'https://vestrasales.com/cart?ref='.rawurlencode($ref),
        'client_reference_id' => $ref,
        'payment_intent_data' => [
            'application_fee_amount' => $appFeeCents,
            'metadata'               => ['order_ref' => $ref, 'kind' => 'escrow'],
        ],
        'metadata'            => ['order_ref' => $ref, 'kind' => 'escrow'],
    ];
    if ($buyerEmail !== '') $params['customer_email'] = $buyerEmail;
    foreach (array_values($lineItems) as $i => $li) {
        $params['line_items'][$i] = [
            'quantity'   => max(1, (int)($li['qty'] ?? 1)),
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => (int)$li['amount'],
                'product_data' => ['name' => (string)($li['name'] ?? 'Item')],
            ],
        ];
    }
    return stripe_api('POST', '/v1/checkout/sessions', $params, $acctId);
}

/** Available + pending balance (in cents) held on a connected account. */
function stripe_escrow_balance(string $acctId): array {
    $b = stripe_api('GET', '/v1/balance', [], $acctId);
    $sum = function ($rows) {
        $out = [];
        foreach (($rows ?? []) as $r) { $out[$r->currency] = ($out[$r->currency] ?? 0) + (int)$r->amount; }
        return $out;
    };
    return ['available' => $sum($b->available ?? []), 'pending' => $sum($b->pending ?? [])];
}

/**
 * RELEASE the escrow: pay out the held funds from the seller's Stripe balance to
 * their bank. Omit $amountCents to release the full available balance. Runs as a
 * payout ON the connected account. Returns the Payout object.
 */
function stripe_escrow_release(string $acctId, ?int $amountCents = null, string $currency = 'eur', string $ref = ''): object {
    if ($amountCents === null) {
        $bal = stripe_escrow_balance($acctId);
        $amountCents = (int)($bal['available'][$currency] ?? 0);
        if ($amountCents <= 0) throw new \RuntimeException('Nothing available to release yet (funds may still be pending).');
    }
    $params = ['amount' => $amountCents, 'currency' => $currency];
    if ($ref !== '') $params['metadata'] = ['order_ref' => $ref];
    return stripe_api('POST', '/v1/payouts', $params, $acctId);
}

/**
 * REFUND the buyer in full before release. refund_application_fee pulls our
 * commission back too, so the buyer gets 100% and the seller's balance is not
 * pushed negative. $paymentIntent is the charge's pi_… (from the webhook).
 */
function stripe_escrow_refund(string $acctId, string $paymentIntent, ?int $amountCents = null): object {
    $params = ['payment_intent' => $paymentIntent, 'refund_application_fee' => 'true'];
    if ($amountCents !== null) $params['amount'] = $amountCents;
    return stripe_api('POST', '/v1/refunds', $params, $acctId);
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
