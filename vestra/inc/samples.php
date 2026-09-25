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

/* defined() guard (VESTRA_ACCOUNTS' pattern): lets a test point the store at a
   temp file instead of the real data/samples.json. Production is unchanged. */
function samples_file(): string { return defined('VESTRA_SAMPLES') ? VESTRA_SAMPLES : __DIR__ . '/../data/samples.json'; }

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

/** Mark a sample order PAID. Idempotent: only flips pending→paid once.
 *  $shipTo = the address the buyer typed on Stripe's page (sample_ship_from_session).
 *  It used to be dropped: Checkout COLLECTED it (shipping_address_collection) but
 *  nothing stored it, so the only place the operator could find where to ship a
 *  paid sample was the Stripe dashboard. */
function sample_mark_paid(string $ref, string $paymentIntent, ?array $shipTo = null): ?array {
    $rec = sample_get($ref);
    if (!$rec || ($rec['status'] ?? '') !== 'pending') return null;
    $patch = [
        'status'         => 'paid',
        'payment_intent' => $paymentIntent,
        'paid_at'        => date('c'),
    ];
    if ($shipTo) $patch['ship_to'] = $shipTo;
    return sample_update($ref, $patch);
}

/** EU-wide only — a sample price includes shipping within the EU. One list for
 *  both sample paths (the product-page checkout and the operator's pay link). */
function sample_eu_countries(): array {
    return ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
            'IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];
}

/** Pull the shipping address + phone off a paid Checkout Session. Stripe-Version
 *  2024-06-20 puts it on shipping_details; older sessions on shipping — the same
 *  two places dropship reads. Returns null when the session carried none. */
function sample_ship_from_session(object $sess): ?array {
    $sa = $sess->shipping_details->address ?? $sess->shipping->address ?? null;
    if (!$sa) return null;
    return [
        'name'        => (string)($sess->shipping_details->name ?? ($sess->shipping->name ?? '')),
        'line1'       => (string)($sa->line1 ?? ''), 'line2' => (string)($sa->line2 ?? ''),
        'postal_code' => (string)($sa->postal_code ?? ''), 'city' => (string)($sa->city ?? ''),
        'country'     => (string)($sa->country ?? ''),
        'phone'       => (string)($sess->customer_details->phone ?? ''),
    ];
}

/** One line of text for a stored ship_to (operator mail, admin). '' when none. */
function sample_ship_line(?array $s): string {
    if (!$s) return '';
    $parts = array_filter([
        trim((string)($s['name'] ?? '')),
        trim(trim((string)($s['line1'] ?? '')).' '.trim((string)($s['line2'] ?? ''))),
        trim(trim((string)($s['postal_code'] ?? '')).' '.trim((string)($s['city'] ?? ''))),
        trim((string)($s['country'] ?? '')),
    ], fn($x) => $x !== '');
    $line = implode(', ', $parts);
    if (trim((string)($s['phone'] ?? '')) !== '') $line .= ' · tel '.trim((string)$s['phone']);
    return $line;
}

/* ── OPERATOR-ISSUED PAY LINK (25 Sep 2026) ────────────────────────────────
 * The product-page sample box only exists when a listing carries sample_price
 * and only for a signed-in buyer who POSTs from that page. A seller had agreed a
 * one-off sample price with a buyer in the message thread (Ecokemet, SH9626,
 * EUR 80) and the buyer asked for "le lien de paiement" — there was no way to
 * send one. Setting sample_price on the listing would have shown that price to
 * EVERY buyer; this record carries the agreed amount only for this buyer.
 *
 *   via        'link'
 *   pay_token  32 hex — the link's only credential (never printed: public logs)
 *   line_name  what Stripe shows ("Échantillon — <item> · Ident n° <sku>")
 *   lang       buyer's language, for the line name and the letter
 *
 * Always charged on the PLATFORM account (no acct_id): KURAL 33 — VESTRA
 * collects and pays the seller on a successful order. */

/** Stripe line name: the word "sample" in the buyer's language + item + ident no. */
function sample_link_line_name(array $p, string $lang): string {
    $fmt = ['en' => 'Sample — %s · Ident no. %s', 'fr' => 'Échantillon — %s · Ident n° %s',
            'de' => 'Muster — %s · Ident-Nr. %s', 'es' => 'Muestra — %s · N.º ident. %s',
            'it' => 'Campione — %s · N. ident. %s'][$lang] ?? 'Sample — %s · Ident no. %s';
    $sku = trim((string)($p['sku'] ?? ''));
    $title = vestra_product_title($p);
    if ($sku === '') return rtrim(explode(' · ', sprintf($fmt, $title, ''))[0]);
    return sprintf($fmt, $title, $sku);
}

function sample_link_url(array $rec): string {
    return 'https://vestrasales.com/sample-pay?ref='.rawurlencode((string)($rec['ref'] ?? ''))
         .'&t='.rawurlencode((string)($rec['pay_token'] ?? ''));
}

/** True only for a link record whose token matches — hash_equals, never ==. */
function sample_link_token_ok(?array $rec, string $token): bool {
    $want = (string)($rec['pay_token'] ?? '');
    return $rec && ($rec['via'] ?? '') === 'link' && $want !== '' && $token !== '' && hash_equals($want, $token);
}

/** The open (unpaid, unexpired… Stripe decides) link record for this buyer+listing,
 *  so a second run REUSES it instead of mailing the buyer a second, parallel link. */
function sample_link_find_open(string $buyerId, string $productId): ?array {
    foreach (samples_all() as $rec) {
        if (($rec['via'] ?? '') === 'link' && ($rec['status'] ?? '') === 'pending'
            && ($rec['buyer_id'] ?? '') === $buyerId && ($rec['product_id'] ?? '') === $productId) return $rec;
    }
    return null;
}

/** Build (not save) a pay-link record. Pure apart from the random ref/token. */
function sample_link_record(array $buyer, array $p, float $amount, string $lang, string $note = ''): array {
    $rec = [
        'ref'           => 'SPL-' . strtoupper(bin2hex(random_bytes(4))),
        'via'           => 'link',
        'pay_token'     => bin2hex(random_bytes(16)),
        'product_id'    => (string)($p['id'] ?? ''),
        'brand'         => (string)($p['brand'] ?? ''),
        'name'          => vestra_product_name($p),
        'sku'           => (string)($p['sku'] ?? ''),
        'line_name'     => sample_link_line_name($p, $lang),
        'lang'          => $lang,
        'buyer_id'      => (string)($buyer['id'] ?? ''),
        'buyer_email'   => (string)($buyer['email'] ?? ''),
        'buyer_name'    => (string)($buyer['name'] ?? ''),
        'buyer_company' => (string)($buyer['company'] ?? ''),
        'note'          => $note,
        'amount'        => round($amount, 2),
        'currency'      => 'eur',
        'status'        => 'pending',
        'created'       => date('c'),
    ];
    if (!empty($p['seller_uid'])) $rec['seller_uid'] = (string)$p['seller_uid'];
    return $rec;
}

/** Create a Checkout Session for a pay-link record, on the PLATFORM account.
 *  The buyer types the delivery address (and a phone for the courier) on
 *  Stripe's page. success_url carries the token so the confirm page opens
 *  without a login — the buyer arrives from an e-mail, not from the site. */
function sample_link_session(array $rec, ?array $buyer = null): object {
    require_once __DIR__ . '/stripe.php';
    $ref   = (string)($rec['ref'] ?? '');
    $cents = (int) round(((float)($rec['amount'] ?? 0)) * 100);
    if ($ref === '' || $cents <= 0) throw new \RuntimeException('sample link: ref/amount missing');
    $desc = ['en' => '1 piece · EU-wide shipping included', 'fr' => '1 pièce · livraison dans l\'UE incluse',
             'de' => '1 Stück · EU-weiter Versand inklusive', 'es' => '1 pieza · envío en la UE incluido',
             'it' => '1 pezzo · spedizione UE inclusa'][$rec['lang'] ?? 'en'] ?? '1 piece · EU-wide shipping included';
    if (trim((string)($rec['note'] ?? '')) !== '') $desc .= ' · ' . trim((string)$rec['note']);
    $params = [
        'mode'                        => 'payment',
        'client_reference_id'         => $ref,
        'line_items'                  => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'eur',
                'unit_amount'  => $cents,
                'product_data' => ['name' => (string)$rec['line_name'], 'description' => $desc],
            ],
        ]],
        'shipping_address_collection' => ['allowed_countries' => sample_eu_countries()],
        'phone_number_collection'     => ['enabled' => 'true'],
        'metadata'                    => ['kind' => 'sample', 'order_ref' => $ref],
        'payment_intent_data'         => ['metadata' => ['kind' => 'sample', 'order_ref' => $ref],
                                          'description' => (string)$rec['line_name'] . ' (' . $ref . ')'],
        'success_url'                 => 'https://vestrasales.com/sample-confirm?ref=' . rawurlencode($ref)
                                          . '&paid=1&t=' . rawurlencode((string)($rec['pay_token'] ?? '')),
        'cancel_url'                  => 'https://vestrasales.com/product?id=' . rawurlencode((string)($rec['product_id'] ?? '')),
    ];
    if ($buyer && !empty($buyer['email'])) $params['customer'] = stripe_ensure_customer($buyer);
    elseif (!empty($rec['buyer_email']))   $params['customer_email'] = (string)$rec['buyer_email'];
    return stripe_api('POST', '/v1/checkout/sessions', $params);
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
        (sample_ship_line($rec['ship_to'] ?? null) !== '' ? "Ship to: ".sample_ship_line($rec['ship_to'] ?? null)."\n" : "Ship to: (not on the record — see the payment in Stripe)\n").
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
