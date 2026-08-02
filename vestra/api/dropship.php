<?php
/**
 * VESTRA — Dropship partner API.
 *
 *   GET  /api/dropship.php?a=stock[&id=lac-polo-paris]
 *        → current price, EU/France shipping, per-colour/size stock.
 *
 *   POST /api/dropship.php?a=order
 *        JSON body: { "id"?, "colour", "size", "qty"?, "reference"?,
 *                      "customer_email"?, "customer_name"? }
 *        → creates a pending order + a Stripe Checkout Session, returns
 *          { ok, ref, checkout_url }. Whoever completes checkout_url pays;
 *          the site then collects their shipping address itself (France vs
 *          rest-of-EU delivery are offered as named shipping options).
 *
 * Auth: every request needs  Authorization: Bearer <DROPSHIP_API_KEY>.
 * id defaults to lac-polo-paris — the only dropship-enabled product today —
 * but any product with a `dropship.enabled` listing field works the same way.
 */
require_once __DIR__ . '/../inc/api_auth.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/products.php';
require_once __DIR__ . '/../inc/stripe.php';
require_once __DIR__ . '/../inc/escrow.php';
require_once __DIR__ . '/../inc/dropship.php';

api_require_key();

const DROPSHIP_DEFAULT_ID = 'lac-polo-paris';

$action = (string)($_GET['a'] ?? '');

if ($action === 'stock' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = trim((string)($_GET['id'] ?? DROPSHIP_DEFAULT_ID));
    $p = vestra_find($id);
    if (!$p || empty($p['dropship']['enabled'])) {
        api_json(['ok' => false, 'error' => 'not_dropship_enabled'], 404);
    }
    api_json([
        'ok'       => true,
        'id'       => $p['id'],
        'brand'    => (string)($p['brand'] ?? ''),
        'name'     => (string)($p['name'] ?? ''),
        'currency' => 'eur',
        'price'    => (float)$p['dropship']['price'],
        'shipping' => [
            'FR' => (float)$p['dropship']['ship_fr'],
            'EU' => (float)$p['dropship']['ship_eu'],
        ],
        'stock'    => $p['dropship']['stock'] ?? [],
    ]);
}

if ($action === 'order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = api_json_body();

    $id         = trim((string)($body['id'] ?? DROPSHIP_DEFAULT_ID));
    $colour     = trim((string)($body['colour'] ?? ''));
    $size       = trim((string)($body['size'] ?? ''));
    $qty        = max(1, (int)($body['qty'] ?? 1));
    $partnerRef = trim((string)($body['reference'] ?? ''));
    $custEmail  = trim((string)($body['customer_email'] ?? ''));
    $custName   = trim((string)($body['customer_name'] ?? ''));

    if ($colour === '' || $size === '') {
        api_json(['ok' => false, 'error' => 'missing_fields', 'message' => 'colour and size are required'], 400);
    }
    if ($custEmail !== '' && !filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
        api_json(['ok' => false, 'error' => 'invalid_email'], 400);
    }

    $p = vestra_find($id);
    if (!$p || empty($p['dropship']['enabled'])) {
        api_json(['ok' => false, 'error' => 'not_dropship_enabled'], 404);
    }

    $left = dropship_stock_left($p, $colour, $size);
    if ($left <= 0 || $qty > $left) {
        api_json(['ok' => false, 'error' => 'out_of_stock', 'message' => "Only {$left} left in {$colour} / {$size}."], 409);
    }

    if (!stripe_available()) {
        api_json(['ok' => false, 'error' => 'payments_unavailable'], 503);
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
    $successUrl = 'https://vestrasales.com/dropship-confirm?ref=' . rawurlencode($ref) . '&paid=1';
    $cancelUrl  = 'https://vestrasales.com/dropship-confirm?ref=' . rawurlencode($ref);

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
        api_json(['ok' => true, 'ref' => $ref, 'reference' => $partnerRef, 'checkout_url' => $session->url]);
    } catch (\Throwable $e) {
        error_log('[VESTRA dropship] Checkout error: ' . $e->getMessage());
        api_json(['ok' => false, 'error' => 'stripe_error', 'message' => $e->getMessage()], 502);
    }
}

api_json(['ok' => false, 'error' => 'unknown_action'], 400);
