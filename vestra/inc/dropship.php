<?php
/**
 * VESTRA — dropship order state + checkout.
 *
 * A dropship order is a single-item purchase at a product's dropship.price,
 * placed either through the external API (api/dropship.php, a partner's own
 * system) or the on-site "Buy now" form (dropship-checkout.php) — either
 * way the buyer is whoever completes Stripe Checkout, never necessarily a
 * logged-in VESTRA account. Same two payment paths as sample orders (see
 * inc/samples.php): direct charge on the seller's connected account when
 * Connect-ready, otherwise VESTRA's platform account.
 *
 *   ref                DRP-xxxxxxxx
 *   product_id / brand / name / sku / colour / size / qty
 *   partner_reference  the API caller's own order id, echoed back (optional)
 *   customer_email/name  optional, supplied by the caller
 *   shipping_address   filled in from Stripe once paid
 *   amount             EUR (float) — item total; shipping is a separate
 *                       Stripe shipping_option, not included here
 *   seller_uid / acct_id / fee / payout   same meaning as samples
 *   session_id / payment_intent
 *   status             pending → paid → released (direct-charge only)
 *   created / paid_at / released_at
 */

require_once __DIR__ . '/products.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/escrow.php';

function dropship_file(): string { return __DIR__ . '/../data/dropship_orders.json'; }

function dropship_all(): array {
    $f = dropship_file();
    if (!is_readable($f)) return [];
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function dropship_get(string $ref): ?array {
    $all = dropship_all();
    return $all[$ref] ?? null;
}

function dropship_save(array $rec): void {
    $ref = $rec['ref'] ?? '';
    if ($ref === '') return;
    $all = dropship_all();
    $all[$ref] = $rec;
    $dir = dirname(dropship_file());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents(dropship_file(), json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
}

function dropship_update(string $ref, array $patch): ?array {
    $all = dropship_all();
    if (!isset($all[$ref])) return null;
    $all[$ref] = array_merge($all[$ref], $patch);
    @file_put_contents(dropship_file(), json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
    return $all[$ref];
}

/** How many units are left for one product/colour/size (0 if unknown). */
function dropship_stock_left(array $p, string $colour, string $size): int {
    return (int)($p['dropship']['stock'][$colour][$size] ?? 0);
}

/**
 * Create a pending dropship order + a Stripe Checkout Session for it, and
 * return either {ok:true, ref, checkout_url} or {ok:false, error, message,
 * status}. Shared by api/dropship.php (partner API, JSON) and
 * dropship-checkout.php (on-site "Buy now" form, redirect) so the payment
 * logic exists exactly once.
 */
function dropship_create_order(
    array $p, string $colour, string $size, int $qty,
    string $custEmail = '', string $custName = '', string $partnerRef = '',
    ?string $successUrl = null, ?string $cancelUrl = null
): array {
    if ($colour === '' || $size === '') {
        return ['ok' => false, 'error' => 'missing_fields', 'message' => 'colour and size are required', 'status' => 400];
    }
    if ($custEmail !== '' && !filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email', 'message' => 'invalid customer_email', 'status' => 400];
    }
    if (empty($p['dropship']['enabled'])) {
        return ['ok' => false, 'error' => 'not_dropship_enabled', 'message' => 'this product is not dropship-enabled', 'status' => 404];
    }

    $qty = max(1, $qty);
    $left = dropship_stock_left($p, $colour, $size);
    if ($left <= 0 || $qty > $left) {
        return ['ok' => false, 'error' => 'out_of_stock', 'message' => "Only {$left} left in {$colour} / {$size}.", 'status' => 409];
    }
    if (!stripe_available()) {
        return ['ok' => false, 'error' => 'payments_unavailable', 'message' => 'payments are not configured', 'status' => 503];
    }

    $unit   = (float)$p['dropship']['price'];
    $amount = round($unit * $qty, 2);
    $cents  = (int) round($amount * 100);

    $ref = 'DRP-' . strtoupper(bin2hex(random_bytes(4)));

    // Resolve the product's seller and whether they can receive a direct charge —
    // same readiness check the wholesale escrow cart and sample orders use.
    $seller = null;
    if (!empty($p['seller_uid'])) {
        foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === $p['seller_uid']) { $seller = $a; break; } }
    }
    $directCharge = $seller && !empty($seller['stripe_account_id']) && escrow_seller_ready($seller);

    $feeCents = 0; $payout = $amount;
    if ($directCharge) {
        $feeCents = (int) round($cents * vestra_seller_commission_rate($seller['membership_tier'] ?? ''));
        $payout   = round($amount - $feeCents / 100, 2);
    }

    $rec = [
        'ref'               => $ref,
        'product_id'        => $p['id'],
        'brand'             => (string)($p['brand'] ?? ''),
        'name'              => (string)($p['name'] ?? ''),
        'sku'               => (string)($p['sku'] ?? ''),
        'colour'            => $colour,
        'size'              => $size,
        'qty'               => $qty,
        'partner_reference' => $partnerRef,
        'customer_email'    => $custEmail,
        'customer_name'     => $custName,
        'amount'            => $amount,
        'currency'          => 'eur',
        'status'            => 'pending',
        'created'           => date('c'),
    ];
    if ($seller) $rec['seller_uid'] = $seller['id'];
    if ($directCharge) { $rec['acct_id'] = $seller['stripe_account_id']; $rec['fee'] = $feeCents / 100; $rec['payout'] = $payout; }
    dropship_save($rec);

    // France vs rest-of-EU are offered as two named shipping options — Checkout
    // collects the address either way, so the buyer picks the one that matches
    // where they actually are.
    $EU_COUNTRIES = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
                      'IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];
    $lineName = trim(($p['brand'] ?? '') . ' ' . ($p['name'] ?? '')) . " — {$colour} / {$size}";
    $successUrl = $successUrl ?? ('https://vestrasales.com/dropship-confirm?ref=' . rawurlencode($ref) . '&paid=1');
    $cancelUrl  = $cancelUrl  ?? ('https://vestrasales.com/dropship-confirm?ref=' . rawurlencode($ref));

    $extra = [
        'shipping_address_collection' => ['allowed_countries' => $EU_COUNTRIES],
        'shipping_options' => [
            ['shipping_rate_data' => ['type' => 'fixed_amount',
                'fixed_amount' => ['amount' => (int)round((float)$p['dropship']['ship_fr'] * 100), 'currency' => 'eur'],
                'display_name' => 'France delivery']],
            ['shipping_rate_data' => ['type' => 'fixed_amount',
                'fixed_amount' => ['amount' => (int)round((float)$p['dropship']['ship_eu'] * 100), 'currency' => 'eur'],
                'display_name' => 'Rest-of-EU delivery']],
        ],
    ];

    try {
        if ($directCharge) {
            $session = stripe_escrow_checkout(
                $seller['stripe_account_id'],
                [['name' => $lineName, 'amount' => $cents, 'qty' => 1]],
                $feeCents, $ref, $custEmail, 'eur', 'dropship',
                $successUrl, $cancelUrl, $extra
            );
        } else {
            $params = [
                'mode'                => 'payment',
                'client_reference_id' => $ref,
                'line_items'          => [[
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => 'eur',
                        'unit_amount'  => $cents,
                        'product_data' => ['name' => $lineName],
                    ],
                ]],
                'metadata'            => ['kind' => 'dropship', 'order_ref' => $ref],
                'payment_intent_data' => ['metadata' => ['kind' => 'dropship', 'order_ref' => $ref]],
                'success_url'         => $successUrl,
                'cancel_url'          => $cancelUrl,
            ] + $extra;
            if ($custEmail !== '') $params['customer_email'] = $custEmail;
            $session = stripe_api('POST', '/v1/checkout/sessions', $params);
        }
        dropship_update($ref, ['session_id' => $session->id ?? '']);
        return ['ok' => true, 'ref' => $ref, 'reference' => $partnerRef, 'checkout_url' => $session->url];
    } catch (\Throwable $e) {
        error_log('[VESTRA dropship] Checkout error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'stripe_error', 'message' => $e->getMessage(), 'status' => 502];
    }
}

/** Mark a dropship order PAID. Idempotent: only flips pending→paid once. */
function dropship_mark_paid(string $ref, string $paymentIntent, ?array $shippingAddress = null): ?array {
    $rec = dropship_get($ref);
    if (!$rec || ($rec['status'] ?? '') !== 'pending') return null;
    $patch = [
        'status'         => 'paid',
        'payment_intent' => $paymentIntent,
        'paid_at'        => date('c'),
    ];
    if ($shippingAddress) $patch['shipping_address'] = $shippingAddress;
    return dropship_update($ref, $patch);
}

/**
 * Decrement dropship stock for one colour/size by $qty, clamped at 0.
 * Best-effort — the JSON store has no cross-request locking (same tradeoff
 * as the rest of the catalog: see set-product.yml), so a burst of concurrent
 * orders on the last unit could in theory oversell by a unit; that's logged,
 * not fatal, since the payment has already been captured by the time this
 * runs.
 */
function dropship_decrement_stock(string $productId, string $colour, string $size, int $qty): void {
    $all = vestra_listings();
    foreach ($all as $i => $l) {
        if (($l['id'] ?? '') !== $productId) continue;
        $have = (int)($all[$i]['dropship']['stock'][$colour][$size] ?? 0);
        $left = max(0, $have - $qty);
        $all[$i]['dropship']['stock'][$colour][$size] = $left;
        if ($have < $qty) {
            error_log("[VESTRA dropship] oversold {$productId} {$colour}/{$size}: had {$have}, sold {$qty}");
        }
        vestra_save_listings($all);
        return;
    }
}

/**
 * Post-payment side effects: customer confirmation email (if an address was
 * given) + admin/seller notify with the Stripe-collected shipping address,
 * so the order can actually be fulfilled. Idempotent — guarded by
 * 'fulfilled' so a webhook + confirm-page double-fire can't send duplicate
 * emails or double-decrement stock.
 */
function dropship_fulfill(array $rec): void {
    $ref = $rec['ref'] ?? '';
    if ($ref === '' || !empty($rec['fulfilled'])) return;
    dropship_update($ref, ['fulfilled' => true]); // claim first — avoids double-fire on races

    dropship_decrement_stock(
        (string)($rec['product_id'] ?? ''),
        (string)($rec['colour'] ?? ''),
        (string)($rec['size'] ?? ''),
        (int)($rec['qty'] ?? 1)
    );

    require_once __DIR__ . '/notify.php';

    $amount = number_format((float)($rec['amount'] ?? 0), 2);
    $addr = $rec['shipping_address'] ?? null;
    $addrLine = $addr
        ? trim(($addr['name'] ?? '') . "\n" . trim(($addr['line1'] ?? '') . ' ' . ($addr['line2'] ?? '')) . "\n" .
               trim(($addr['postal_code'] ?? '') . ' ' . ($addr['city'] ?? '')) . ', ' . ($addr['country'] ?? ''))
        : '(no shipping address on file — check the Stripe payment)';

    $itemLine = "{$rec['brand']} {$rec['name']} — {$rec['colour']} / {$rec['size']} × {$rec['qty']}";

    if (!empty($rec['customer_email'])) {
        vestra_send_mail($rec['customer_email'], "VESTRA — order confirmed ({$ref})",
            "Hello " . ($rec['customer_name'] ?: 'there') . ",\n\n" .
            "Your order is confirmed and paid.\n\n" .
            "Order ref: {$ref}\n" .
            "Item: {$itemLine}\n" .
            "Amount paid: €{$amount}\n\n" .
            "We'll ship it out shortly.\n\n" .
            "— VESTRA · vestrasales.com");
    }

    $directCharge = !empty($rec['acct_id']);
    $payoutLine = $directCharge
        ? "Seller payout: €" . number_format((float)($rec['payout'] ?? 0), 2) . " — HELD on their Stripe balance (no release UI yet for dropship orders — release manually via Stripe Dashboard, or ask to have the admin button added).\n"
        : '';
    vestra_notify(
        "Dropship order paid — {$ref}",
        "A dropship API order has been paid.\n\n" .
        "Ref: {$ref}" . (!empty($rec['partner_reference']) ? " (partner ref: {$rec['partner_reference']})" : "") . "\n" .
        "Item: {$itemLine}\n" .
        "Amount: €{$amount}" . (!empty($rec['customer_email']) ? " · Customer: {$rec['customer_email']}" : "") . "\n" .
        "Ship to:\n{$addrLine}\n\n" .
        $payoutLine . "\n" .
        ($directCharge
            ? "Ship it and mark it sent."
            : "Ship it and mark it sent — VESTRA collected the payment directly, no seller escrow step.")
    );
}
