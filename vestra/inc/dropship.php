<?php
/**
 * VESTRA — dropship API order state.
 *
 * A dropship order is placed via the external API (api/dropship.php) by a
 * partner's own system, not through the VESTRA web UI — the buyer is the
 * partner's end customer, never a logged-in VESTRA account. Same two
 * payment paths as sample orders (see inc/samples.php): direct charge on
 * the seller's connected account when Connect-ready, otherwise VESTRA's
 * platform account.
 *
 *   ref                DRP-xxxxxxxx
 *   product_id / brand / name / sku / colour / size / qty
 *   partner_reference  the caller's own order id, echoed back (optional)
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
