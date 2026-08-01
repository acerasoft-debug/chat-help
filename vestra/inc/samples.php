<?php
/**
 * VESTRA — sample-order state ("Muster Bestellung").
 *
 * A sample order is a single-unit purchase at a fixed price (set per product
 * via $p['sample_price']), charged on VESTRA's own Stripe account — unlike a
 * wholesale order, there is no seller invoice/escrow lifecycle here, just
 * pending → paid. Mirrors the shape of inc/escrow.php but deliberately
 * smaller: no release/refund step, since VESTRA collects the sample payment
 * directly rather than holding a seller's funds in escrow.
 *
 *   ref            SPL-xxxxxxxx
 *   product_id / brand / name / sku
 *   buyer_id / buyer_email / buyer_company
 *   note           free-text size choice or note from the buyer (optional)
 *   amount         EUR (float), EU-wide shipping included
 *   session_id     cs_…   Checkout Session (set at creation)
 *   payment_intent pi_…   set once paid
 *   status         pending → paid
 *   created / paid_at
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

    vestra_notify(
        "Sample order paid — {$ref}",
        "A sample order has been paid.\n\n".
        "Ref: {$ref}\n".
        "Item: {$rec['brand']} {$rec['name']}".(!empty($rec['sku'])?" (".$rec['sku'].")":"")."\n".
        "Buyer: ".($rec['buyer_company'] ?: $rec['buyer_name'])." <{$rec['buyer_email']}>\n".
        ($note !== '' ? "Size / note: {$note}\n" : '').
        "Amount: €{$amount}\n\n".
        "Ship it and mark it sent — there is no seller escrow step for samples, VESTRA collected the payment directly."
    );
}
