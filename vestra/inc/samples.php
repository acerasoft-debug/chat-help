<?php
/**
 * VESTRA — sample-order state ("Muster Bestellung").
 *
 * A sample order is a single-unit purchase at a fixed price (set per product
 * via $p['sample_price']). Two payment paths, chosen at checkout:
 *   - the product has no seller, or its seller hasn't finished Stripe Connect
 *     onboarding → charged on VESTRA's own platform account (pending → paid),
 *     same as before.
 *   - the seller IS Connect-ready → charged as a direct charge on the
 *     SELLER's connected account (same mechanism as wholesale escrow), so
 *     the money settles into their own Stripe balance, not VESTRA's. That
 *     balance is HELD (manual payout, same as escrow) until released — see
 *     sample_do_release() — because newly-charged card funds aren't
 *     available to pay out immediately regardless.
 *
 *   ref            SPL-xxxxxxxx
 *   product_id / brand / name / sku
 *   buyer_id / buyer_email / buyer_company
 *   note           free-text size choice or note from the buyer (optional)
 *   amount         EUR (float), EU-wide shipping included
 *   seller_uid     set when the product has an owning seller (either path)
 *   acct_id        set ONLY for a direct-charge sale — seller's acct_… id
 *   fee            application fee taken on a direct-charge sale (EUR)
 *   payout         seller's net share on a direct-charge sale (EUR)
 *   session_id     cs_…   Checkout Session (set at creation)
 *   payment_intent pi_…   set once paid
 *   status         pending → paid → released (direct-charge only)
 *   created / paid_at / released_at
 */

function samples_file(): string { return __DIR__ . '/../data/samples.json'; }

function samples_all(): array {
    $f = samples_file();
    if (!is_readable($f)) return [];
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function sample_get(string $ref): ?array {
    $all = samples_all();
    return $all[$ref] ?? null;
}

function sample_find_by_session(string $sessionId): ?array {
    foreach (samples_all() as $rec) {
        if (($rec['session_id'] ?? '') === $sessionId) return $rec;
    }
    return null;
}

function sample_save(array $rec): void {
    $ref = $rec['ref'] ?? '';
    if ($ref === '') return;
    $all = samples_all();
    $all[$ref] = $rec;
    $dir = dirname(samples_file());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents(samples_file(), json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
}

function sample_update(string $ref, array $patch): ?array {
    $all = samples_all();
    if (!isset($all[$ref])) return null;
    $all[$ref] = array_merge($all[$ref], $patch);
    @file_put_contents(samples_file(), json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
    return $all[$ref];
}

/** Mark a sample order PAID. Idempotent: only flips pending→paid once. */
function sample_mark_paid(string $ref, string $paymentIntent): ?array {
    $rec = sample_get($ref);
    if (!$rec || ($rec['status'] ?? '') !== 'pending') return null;
    return sample_update($ref, [
        'status'         => 'paid',
        'payment_intent' => $paymentIntent,
        'paid_at'        => date('c'),
    ]);
}

/**
 * Post-payment side effects: buyer confirmation email + admin notify.
 * Idempotent — guarded by a 'fulfilled' flag so a webhook + confirm-page
 * double-fire can't send duplicate emails.
 */
function sample_fulfill(array $rec): void {
    $ref = $rec['ref'] ?? '';
    if ($ref === '' || !empty($rec['fulfilled'])) return;
    sample_update($ref, ['fulfilled' => true]); // claim first — avoids double email on races

    require_once __DIR__ . '/notify.php';

    $amount = number_format((float)($rec['amount'] ?? 0), 2);
    $note   = trim((string)($rec['note'] ?? ''));

    if (!empty($rec['buyer_email'])) {
        vestra_send_mail($rec['buyer_email'], "VESTRA — sample order confirmed ({$ref})",
            "Hello ".($rec['buyer_name'] ?: 'there').",\n\n".
            "Thanks — your sample order is confirmed and paid.\n\n".
            "Order ref: {$ref}\n".
            "Item: {$rec['brand']} {$rec['name']}".(!empty($rec['sku'])?" (".$rec['sku'].")":"")."\n".
            ($note !== '' ? "Size / note: {$note}\n" : '').
            "Amount paid: €{$amount} (EU-wide shipping included)\n\n".
            "Please note: the exact size you requested may not always be available — we ship the closest match from current sample stock.\n\n".
            "We'll email you again once it ships.\n\n".
            "— VESTRA · vestrasales.com");
    }

    $directCharge = !empty($rec['acct_id']);
    $payoutLine = $directCharge
        ? "Seller payout: €".number_format((float)($rec['payout'] ?? 0), 2)." — HELD on their Stripe balance until you release it (Admin → Orders → Sample orders).\n"
        : '';
    vestra_notify(
        "Sample order paid — {$ref}",
        "A sample order has been paid.\n\n".
        "Ref: {$ref}\n".
        "Item: {$rec['brand']} {$rec['name']}".(!empty($rec['sku'])?" (".$rec['sku'].")":"")."\n".
        "Buyer: ".($rec['buyer_company'] ?: $rec['buyer_name'])." <{$rec['buyer_email']}>\n".
        ($note !== '' ? "Size / note: {$note}\n" : '').
        "Amount: €{$amount}\n".
        $payoutLine."\n".
        ($directCharge
            ? "Ship it and mark it sent."
            : "Ship it and mark it sent — there is no seller escrow step for this one, VESTRA collected the payment directly.")
    );
}

/**
 * RELEASE a direct-charge sample's held funds to the seller. Mirrors
 * escrow_do_release() exactly (same available-balance cap — newly-charged
 * card funds are typically still "pending" in Stripe for a few days, so this
 * can legitimately return "nothing available yet" right after payment; that
 * is expected, not a bug — try again once the charge has settled).
 */
function sample_do_release(string $ref): array {
    require_once __DIR__ . '/stripe.php';
    $rec = sample_get($ref);
    if (!$rec) return ['ok'=>false, 'msg'=>'Unknown sample order.'];
    if (empty($rec['acct_id'])) return ['ok'=>false, 'msg'=>'This sample was collected on the platform account — nothing to release.'];
    if (($rec['status'] ?? '') === 'released') return ['ok'=>true, 'msg'=>'Already released.'];
    if (($rec['status'] ?? '') !== 'paid') return ['ok'=>false, 'msg'=>'Order is not paid yet (status: '.($rec['status'] ?? '?').').'];
    $want = isset($rec['payout']) ? (int) round(((float)$rec['payout']) * 100) : null;
    try {
        $cur   = $rec['currency'] ?? 'eur';
        $bal   = stripe_escrow_balance($rec['acct_id']);
        $avail = (int) ($bal['available'][$cur] ?? 0);
        $amt   = ($want === null) ? $avail : min($want, $avail);
        if ($amt <= 0) return ['ok'=>false, 'msg'=>'Nothing available to release yet — card funds may still be settling. Try again shortly.'];
        $p = stripe_escrow_release($rec['acct_id'], $amt, $cur, $ref);
        sample_update($ref, ['status'=>'released', 'released_at'=>date('c'), 'payout_id'=>$p->id ?? '']);
        $paid = number_format(((int)($p->amount ?? 0))/100, 2);
        if (!empty($rec['seller_uid'])) {
            require_once __DIR__.'/push.php';
            vestra_push_send($rec['seller_uid'], 'VESTRA — sample payout released 🎉',
                'Sample '.$ref.' — €'.$paid.' is on its way to your bank.', '/seller?tab=orders');
        }
        return ['ok'=>true, 'msg'=>'Released €'.$paid.' to the seller.'];
    } catch (\Throwable $e) {
        error_log('[VESTRA Sample] release failed '.$ref.': '.$e->getMessage());
        return ['ok'=>false, 'msg'=>'Stripe error: '.$e->getMessage()];
    }
}
