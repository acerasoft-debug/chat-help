<?php
/**
 * VESTRA — automatic seller commission: 3.5% (VESTRA_COMMISSION_RATE, inc/products.php)
 * of each order's goods value, charged off-session to the seller's saved commission
 * card the moment an order is marked "paid". Completely separate from the invoice/cart
 * total the buyer pays — the buyer is never touched by this, and the amount is never
 * added to the invoice PDF.
 *
 * Assumes the caller has already required inc/products.php, inc/auth.php, inc/stripe.php
 * and inc/notify.php (same implicit-dependency convention as inc/invoice.php/orders.php).
 */

function vestra_commissions(): array { return vestra_read_json('commissions.json'); }
function vestra_save_commissions(array $c): void { vestra_write_json('commissions.json', $c); }

/** Human-readable status badge text for admin UI. */
function vestra_commission_status_label(string $status): string {
    return match ($status) {
        'charged' => 'Charged', 'failed' => 'Failed', 'no_card' => 'No card on file',
        default   => '—',
    };
}

/**
 * Charge each seller's 3.5% commission for one order — once. Idempotent per
 * (order ref, seller): a second call (e.g. an admin re-saving the same "paid"
 * status) is a no-op for any seller already recorded. Groups $lines (from
 * vestra_order_lines()) by seller_uid, so a multi-seller cart charges each
 * seller only for their own goods. Never throws — a failed or missing card
 * is recorded and flagged to admin + the seller, the order itself is
 * unaffected either way.
 */
function vestra_charge_order_commission(string $ref, array $lines): void {
    $all = vestra_commissions();
    $bySeller = [];
    foreach ($lines as $l) {
        $sid = $l['seller_uid'] ?? '';
        if ($sid === '') continue; // sellerless demo/catalog lines carry no commission
        $bySeller[$sid] = ($bySeller[$sid] ?? 0) + (float)($l['line'] ?? 0);
    }
    if (!$bySeller) return;

    foreach ($bySeller as $sid => $goods) {
        $key = $ref . '__' . $sid;
        if (isset($all[$key])) continue; // already charged or already attempted for this order+seller

        $amount = round($goods * VESTRA_COMMISSION_RATE, 2);
        $acc = null;
        foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === $sid) { $acc = $a; break; } }
        $entry = ['ref' => $ref, 'seller_uid' => $sid, 'goods' => round($goods, 2), 'amount' => $amount, 'at' => date('c')];

        if ($amount <= 0) { continue; } // nothing to collect

        $pm = $acc['stripe_commission_pm'] ?? '';
        if (!$acc || !$pm || !stripe_available()) {
            $all[$key] = $entry + ['status' => 'no_card'];
            vestra_save_commissions($all);
            vestra_notify(
                "Commission not collected (no card on file) — order {$ref}",
                "Seller " . ($acc['company'] ?? $sid) . " has no commission card on file.\n\n" .
                "Order: {$ref}\nGoods: €{$goods}\nCommission due (3.5%): €{$amount}\n\n" .
                "Please invoice this seller manually, or ask them to add a card:\n" .
                "https://vestrasales.com/admin?tab=users"
            );
            continue;
        }

        try {
            $pi = stripe_api('POST', '/v1/payment_intents', [
                'amount'        => (int) round($amount * 100),
                'currency'      => 'eur',
                'customer'      => $acc['stripe_customer_id'] ?? '',
                'payment_method' => $pm,
                'off_session'   => 'true',
                'confirm'       => 'true',
                'description'   => "VESTRA commission — order {$ref}",
                'metadata'      => ['ref' => $ref, 'seller_id' => $sid, 'kind' => 'commission'],
            ]);
            $all[$key] = $entry + ['status' => 'charged', 'payment_intent' => $pi->id ?? ''];
        } catch (\Throwable $e) {
            $all[$key] = $entry + ['status' => 'failed', 'error' => $e->getMessage()];
            vestra_notify(
                "Commission charge FAILED — order {$ref}",
                "Could not charge €{$amount} commission (3.5% of €{$goods}) from " .
                ($acc['company'] ?? $sid) . " for order {$ref}.\n\nStripe error: {$e->getMessage()}\n\n" .
                "Admin: https://vestrasales.com/admin?tab=orders"
            );
            if (!empty($acc['email'])) {
                vestra_send_mail(
                    $acc['email'],
                    'VESTRA — commission charge failed',
                    "Hello " . ($acc['name'] ?: $acc['company']) . ",\n\n" .
                    "We couldn't charge the 3.5% commission (€{$amount}) for order {$ref} to your card on file.\n\n" .
                    "Please check or update your payment method:\nhttps://vestrasales.com/seller?tab=profile\n\n" .
                    "— VESTRA · vestrasales.com"
                );
            }
        }
        vestra_save_commissions($all);
    }
}

/** All commission entries for one order (admin display). */
function vestra_commissions_for_ref(string $ref): array {
    $out = [];
    foreach (vestra_commissions() as $key => $c) {
        if (($c['ref'] ?? '') === $ref) $out[] = $c;
    }
    return $out;
}
