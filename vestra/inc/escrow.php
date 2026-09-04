<?php
/**
 * VESTRA — escrow order state (direct charge + delayed payout).
 *
 * orders.csv is append-only, so the mutable escrow lifecycle lives in its own
 * tiny JSON store keyed by order ref. One record per escrow order:
 *
 *   ref            VES-xxxxxxxx   (matches orders.csv)
 *   seller_uid     our account id of the paid seller
 *   acct_id        acct_…         connected account the charge lives on
 *   session_id     cs_…           Checkout Session (set at creation)
 *   payment_intent pi_…           set once paid (needed for refund)
 *   amount         cents the buyer paid (gross)
 *   fee            cents platform commission (application_fee_amount)
 *   currency       eur
 *   status         pending → held → released | refunded
 *   buyer_email / buyer_id / created / paid_at / released_at / refunded_at
 *
 * Status meaning:
 *   pending   Checkout Session created, buyer has not paid yet.
 *   held      Buyer paid; the seller's share sits HELD in their Stripe balance
 *             (manual payout). This is money in escrow.
 *   released  Platform paid the seller out (buyer confirmed delivery).
 *   refunded  Platform refunded the buyer in full before release.
 */

function escrow_file(): string { return __DIR__ . '/../data/escrow.json'; }

function escrow_all(): array {
    $f = escrow_file();
    if (!is_readable($f)) return [];
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function escrow_get(string $ref): ?array {
    $all = escrow_all();
    return $all[$ref] ?? null;
}

/** Find an escrow record by Stripe Checkout Session id (webhook lookup). */
function escrow_find_by_session(string $sessionId): ?array {
    foreach (escrow_all() as $rec) {
        if (($rec['session_id'] ?? '') === $sessionId) return $rec;
    }
    return null;
}

/** Find an escrow record by payment_intent id (webhook / refund lookup). */
function escrow_find_by_pi(string $pi): ?array {
    foreach (escrow_all() as $rec) {
        if (($rec['payment_intent'] ?? '') === $pi) return $rec;
    }
    return null;
}

function escrow_save(array $rec): void {
    $ref = $rec['ref'] ?? '';
    if ($ref === '') return;
    $all = escrow_all();
    $all[$ref] = $rec;
    $dir = dirname(escrow_file());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents(escrow_file(), json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
}

/** Merge a patch into an existing record and persist. Returns the merged record. */
function escrow_update(string $ref, array $patch): ?array {
    $all = escrow_all();
    if (!isset($all[$ref])) return null;
    $all[$ref] = array_merge($all[$ref], $patch);
    @file_put_contents(escrow_file(), json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
    return $all[$ref];
}

/** Human status label + colour for UI badges (localised where t() is loaded). */
function escrow_badge(string $status): string {
    $L = fn(string $s): string => function_exists('t') ? t($s) : $s;
    return match ($status) {
        'held'     => '<span style="color:#7ad6a0">🛡️ '.$L('In escrow').'</span>',
        'released' => '<span style="color:#8fd3ff">✓ '.$L('Released to seller').'</span>',
        'refunded' => '<span style="color:#f0c060">↩ '.$L('Refunded to buyer').'</span>',
        'pending'  => '<span style="color:#888">⏳ '.$L('Awaiting payment').'</span>',
        default    => '<span style="color:#555">—</span>',
    };
}

/**
 * Is this seller ready to receive escrow (Connect onboarded, charges enabled)?
 * Reads the cached `escrow_ready` flag written on onboarding-return and by the
 * account.updated webhook — no per-request Stripe call. When $refresh is true
 * (e.g. right after onboarding) it hits the API once and updates the cache.
 */
function escrow_seller_ready(array $seller, bool $refresh = false): bool {
    if ($refresh && !empty($seller['stripe_account_id']) && function_exists('stripe_connect_status')) {
        $st = stripe_connect_status($seller);
        $ready = !empty($st['charges_enabled']);
        if (($seller['escrow_ready'] ?? null) !== $ready) {
            auth_update($seller['id'], ['escrow_ready' => $ready]);
        }
        return $ready;
    }
    return !empty($seller['escrow_ready']);
}

/* ── Lifecycle actions ──────────────────────────────────────────────────── */

/**
 * Mark an escrow order PAID (buyer completed Checkout). Idempotent: records the
 * payment_intent and flips pending→held once, returning the record so the caller
 * can fulfil it. Returns null if the ref is unknown or already past 'pending'.
 */
function escrow_mark_paid(string $ref, string $paymentIntent): ?array {
    $rec = escrow_get($ref);
    if (!$rec || ($rec['status'] ?? '') !== 'pending') return null;
    return escrow_update($ref, [
        'status'         => 'held',
        'payment_intent' => $paymentIntent,
        'paid_at'        => date('c'),
    ]);
}

/**
 * Post-payment side effects for a freshly-held escrow order: PDF invoice (marked
 * paid), buyer + seller "payment held in escrow" emails, and the messaging order
 * card. Idempotent — guarded by a 'fulfilled' flag so a webhook + confirm-page
 * double-fire can't duplicate emails.
 */
function escrow_fulfill(array $rec): void {
    $ref = $rec['ref'] ?? '';
    if ($ref === '' || !empty($rec['fulfilled'])) return;
    escrow_update($ref, ['fulfilled' => true]); // claim first — avoids double email on races

    require_once __DIR__ . '/notify.php';
    require_once __DIR__ . '/invoice.php';

    $b     = $rec['buyer'] ?? [];
    $items = $rec['items'] ?? [];
    $total = number_format((float)($rec['total'] ?? 0), 2);

    // Seller account (for invoice + email).
    $seller = null;
    foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === ($rec['seller_uid'] ?? '')) { $seller = $a; break; } }

    // Paid invoice (idempotent by ref+seller).
    try {
        vestra_ensure_invoice([
            'ref' => $ref, 'date' => $rec['created'] ?? date('c'),
            'paid' => true, 'paid_at' => $rec['paid_at'] ?? date('c'),
            'buyer' => ['company'=>$b['company']??'','vat'=>$b['vat']??'','name'=>$b['name']??'',
                        'email'=>$b['email']??'','country'=>$b['country']??'','address'=>$b['address']??''],
        ], $items, $seller, true); // force: escrow payment already completed, invoice always issues
    } catch (\Throwable $e) { error_log('[VESTRA Escrow] invoice failed '.$ref.': '.$e->getMessage()); }

    $itemsTxt = implode("\n", array_map(
        fn($l)=>"  {$l['qty']}x {$l['sku']} {$l['brand']} {$l['name']} @ €".number_format((float)$l['unit'],2)
              .(!empty($l['colors'])?" [".implode(', ',(array)$l['colors'])."]":""), $items));

    // Buyer: payment held in escrow.
    if (!empty($b['email'])) {
        vestra_send_mail($b['email'], "VESTRA — payment secured in escrow ({$ref})",
            "Hello ".($b['name']?:'there').",\n\n".
            "Your payment for order {$ref} has been received and is now held safely in VESTRA escrow.\n\n".
            "€{$total} is protected. The seller ships your goods, and the funds are released to them only after you confirm delivery. If something goes wrong before then, you can open a dispute and we can refund you in full.\n\n".
            "--- Order summary ---\n{$itemsTxt}\n\n".
            "Confirm delivery when your goods arrive: https://vestrasales.com/buyer?tab=orders\n\n".
            "— VESTRA · vestrasales.com");
    }

    // Seller: funds held, ship now.
    if ($seller && !empty($seller['email'])) {
        $payout = number_format((float)($rec['payout'] ?? 0), 2);
        vestra_send_mail($seller['email'], "VESTRA — paid order in escrow ({$ref}) — ship now",
            "Hello ".($seller['name']?:($seller['company']?:'there')).",\n\n".
            "Good news — a buyer has PAID for an order and the funds are held in escrow on your Stripe balance:\n\n".
            "Order ref: {$ref}\nBuyer: ".($b['company']?:$b['name'])."\n".
            (!empty($b['ship_address'])?"Deliver to: {$b['ship_address']}\n":'')."\n{$itemsTxt}\n\n".
            "Your payout after commission: €{$payout}\n\n".
            "Please ship the goods and mark the order shipped. The held funds are released to your bank once the buyer confirms delivery.\n\n".
            "Your dashboard: https://vestrasales.com/seller?tab=orders\n\n".
            "— VESTRA · vestrasales.com");
    }

    // Push pings: buyer "payment secured", seller "paid order — ship now".
    require_once __DIR__.'/push.php';
    if (!empty($rec['buyer_id'])) {
        vestra_push_send($rec['buyer_id'], 'VESTRA — payment secured 🛡️',
            'Order '.$ref.' is protected in escrow. We\'ll notify you when it ships.', '/buyer?tab=orders');
    }
    if ($seller) {
        vestra_push_send($seller['id'], 'VESTRA — paid order! Ship now',
            'Order '.$ref.' is paid and held in escrow. Please ship the goods.', '/seller?tab=orders');
    }

    // Messaging order card (buyer ↔ seller) — the trade lives in one thread.
    $buyerId = $rec['buyer_id'] ?? '';
    if ($buyerId && $seller) {
        require_once __DIR__ . '/messages.php';
        $summary = implode(' · ', array_map(
            fn($l)=>$l['qty'].'× '.$l['brand'].' '.$l['name'].(!empty($l['colors'])?' ('.implode(', ',(array)$l['colors']).')':''), $items));
        try {
            vestra_msg_post_system($buyerId, $seller['id'], '', [
                'kind'=>'order', 'status'=>'paid_escrow', 'ref'=>$ref,
                'items'=>mb_substr($summary,0,160), 'total'=>(float)($rec['total'] ?? 0),
            ]);
        } catch (\Throwable $e) { error_log('[VESTRA Escrow] msg card failed '.$ref.': '.$e->getMessage()); }
    }
}

/**
 * RELEASE the escrow to the seller (buyer confirmed delivery). Pays out the
 * seller's held balance and flips held→released. Returns [ok,msg].
 */
function escrow_do_release(string $ref): array {
    require_once __DIR__ . '/stripe.php';
    $rec = escrow_get($ref);
    if (!$rec) return ['ok'=>false, 'msg'=>'Unknown order.'];
    if (($rec['status'] ?? '') === 'released') return ['ok'=>true, 'msg'=>'Already released.'];
    if (($rec['status'] ?? '') !== 'held')     return ['ok'=>false, 'msg'=>'Order is not in escrow (status: '.($rec['status'] ?? '?').').'];
    // Release the seller's NET share (payout). Direct-charge Stripe fees reduce the
    // connected balance below the gross payout, so cap at what's actually available —
    // never over-request (Stripe rejects payouts above the available balance).
    $want = isset($rec['payout']) ? (int) round(((float)$rec['payout']) * 100) : null;
    try {
        $cur   = $rec['currency'] ?? 'eur';
        $bal   = stripe_escrow_balance($rec['acct_id']);
        $avail = (int) ($bal['available'][$cur] ?? 0);
        $amt   = ($want === null) ? $avail : min($want, $avail);
        if ($amt <= 0) return ['ok'=>false, 'msg'=>'Nothing available to release yet — card funds may still be settling. Try again shortly.'];
        $p = stripe_escrow_release($rec['acct_id'], $amt, $cur, $ref);
        escrow_update($ref, ['status'=>'released', 'released_at'=>date('c'), 'payout_id'=>$p->id ?? '']);
        $paid = number_format(((int)($p->amount ?? 0))/100, 2);
        if (!empty($rec['seller_uid'])) {
            require_once __DIR__.'/push.php';
            vestra_push_send($rec['seller_uid'], 'VESTRA — funds released 🎉',
                'Order '.$ref.' — €'.$paid.' is on its way to your bank.', '/seller?tab=orders');
        }
        return ['ok'=>true, 'msg'=>'Released €'.$paid.' to the seller.'];
    } catch (\Throwable $e) {
        error_log('[VESTRA Escrow] release failed '.$ref.': '.$e->getMessage());
        return ['ok'=>false, 'msg'=>'Release failed: '.$e->getMessage()];
    }
}

/* ── Otomatik serbest birakma (operator kurali, 31 Agustos 2026) ─────────────
 * "Urun musteriye ulastiktan sonra 2 IS GUNU icinde musteri onay vermezse
 *  odeme otomatik onaylansin."
 *
 * Saat SHIPPED'de degil DELIVERED'da baslar. Kendi teslimat sartlarimiz AB
 * icinde 7-14 is gunu diyor; kargolamadan 2 gun sonra birakmak, parayi mal
 * yoldayken satıciya vermek olurdu — escrow'un tam tersi. Teslimati satici
 * isaretler (kargo takibi elinde olan o); isaretledigi anda aliciya sure
 * baslangici e-postayla bildirilir, yani surenin isledigini iki taraf da bilir.
 *
 * Alici pencere icinde onaylarsa para zaten aninda iner (buyer.php). Sorun
 * bildirirse operator paneldeki Release/Refund ile karar verir — süpurucu
 * yalnizca 'held' kayitlara dokundugu icin, operatorun o arada verdigi karar
 * otomatigi kendiliginden devre disi birakir. */

/* Alicinin teslimattan sonra YANLIS / EKSIK / HATALI mal bildirmek icin sahip
   oldugu sure, TAKVIM gunu (operator karari, 4 Eyl 2026). Politika metni
   inc/faq.php'deki 'returns' bolumunde; tests/returns_policy_test.php ikisinin
   ayni sayiyi soylemesini zorunlu tutar. */
if (!defined('VESTRA_CLAIM_DAYS')) define('VESTRA_CLAIM_DAYS', 3);

/** $ts'ten itibaren $days IS GUNU sonrasi (Cmt/Paz atlanir), epoch saniyesi. */
function vestra_business_days_after(int $ts, int $days): int {
    while ($days > 0) {
        $ts += 86400;
        $dow = (int)date('N', $ts);          // 1=Pzt … 7=Paz
        if ($dow <= 5) $days--;
    }
    return $ts;
}

/**
 * Suresi dolmus 'held' escrow'lari serbest birak. $dry=true hicbir sey yazmaz,
 * sadece ne yapacagini soyler. Donen dizi cron ciktisi icindir.
 */
function escrow_auto_release_sweep(bool $dry = false): array {
    /* Cron baglaminda hicbir sayfa yigini yuklu degil; süpurucu kendi
       bagimliliklarini kendisi getirir (json yardimcilari products.php'de,
       history orders.php'de, hesaplar auth.php'de, vestra_user_lang i18n'de). */
    require_once __DIR__ . '/products.php';
    require_once __DIR__ . '/orders.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/i18n.php';
    $out = ['checked'=>0, 'due'=>0, 'released'=>0, 'failed'=>0, 'lines'=>[]];
    $statuses = vestra_read_json('order_statuses.json');
    foreach (escrow_all() as $ref => $rec) {
        if (($rec['status'] ?? '') !== 'held') continue;
        $out['checked']++;
        $st = $statuses[$ref] ?? [];
        if (($st['status'] ?? '') !== 'delivered') continue;   // saat teslimatla baslar
        $dts = strtotime((string)($st['delivered_at'] ?? ''));
        if (!$dts) continue;
        /* Para, alicinin sikayet hakki BITMEDEN saticiya gecmemeli. Iki is gunu
           takvimde 3 gunden kisa olabiliyor (Pzt teslimat -> Crs serbest, oysa
           alicinin hakki Prs aksamina kadar suruyor), yani eski tek kural
           pencerenin ortasinda odeme yapiyordu. Ikisinin GEC olani alinir:
           mevcut 2 is gunu taban olarak korunur (hafta sonu davranisi degismez),
           uzerine 3 takvim gunluk hak garanti edilir. */
        $deadline = max(vestra_business_days_after($dts, 2),
                        $dts + VESTRA_CLAIM_DAYS * 86400);
        if (time() < $deadline) {
            $out['lines'][] = sprintf('  %s teslim %s — sure %s dolacak', $ref,
                date('Y-m-d H:i', $dts), date('Y-m-d H:i', $deadline));
            continue;
        }
        $out['due']++;
        if ($dry) { $out['lines'][] = "  {$ref} SURESI DOLMUS — kuru kosuda birakilmadi"; continue; }
        $r = escrow_do_release($ref);
        if (!$r['ok']) {
            /* Bakiye hala takas bekliyor olabilir ('Nothing available yet') —
               kayit 'held' kaldigi icin yarinki süpurucu yeniden dener. */
            $out['failed']++;
            $out['lines'][] = "  {$ref} BIRAKILAMADI: {$r['msg']}";
            continue;
        }
        $out['released']++;
        $out['lines'][] = "  {$ref} SERBEST: {$r['msg']}";
        escrow_update($ref, ['auto_released_at' => date('c')]);
        $statuses[$ref] = array_merge($statuses[$ref] ?? [], ['status'=>'completed','completed_at'=>date('c')]);
        $statuses[$ref]['history'][] = vestra_order_history_entry('completed', 'auto',
            'Released automatically — no claim reported within the buyer\'s window after delivery');
        vestra_write_json('order_statuses.json', $statuses);
        $statuses = vestra_read_json('order_statuses.json'); // yaz-oku: sonraki dongu taze gorsun
        /* Iki tarafa da e-posta — bu sonucu ikisi de tetiklemedi, push yetmez
           (admin'in zorla release'indeki gerekce burada da aynen gecerli). */
        try {
            require_once __DIR__.'/notify.php';
            require_once __DIR__.'/email_templates.php';
            $b = $rec['buyer'] ?? []; $seller = null;
            foreach (auth_accounts() as $a) { if (($a['id']??'') === ($rec['seller_uid']??'')) { $seller = $a; break; } }
            $payout = (float)($rec['payout'] ?? 0);
            if ($seller && !empty($seller['email'])) {
                [$s1,$b1,$o1] = vestra_tpl_escrow_release(vestra_user_lang($seller), $seller['name']?:($seller['company']?:'there'), 'seller', $ref, $payout);
                vestra_send_mail($seller['email'], $s1, $b1, '', '', null, '', $o1);
            }
            if (!empty($b['email'])) {
                $buyerAcc = auth_find($b['email']);
                [$s2,$b2,$o2] = vestra_tpl_escrow_release(vestra_user_lang($buyerAcc), $b['name']?:'there', 'buyer', $ref, $payout);
                vestra_send_mail($b['email'], $s2, $b2, '', '', null, '', $o2);
            }
        } catch (\Throwable $e) { error_log('[VESTRA Escrow] auto-release mail '.$ref.': '.$e->getMessage()); }
    }
    return $out;
}

/**
 * REFUND the buyer in full before release (dispute / non-delivery). Flips
 * held→refunded and claws the commission back. Returns [ok,msg].
 */
function escrow_do_refund(string $ref): array {
    require_once __DIR__ . '/stripe.php';
    $rec = escrow_get($ref);
    if (!$rec) return ['ok'=>false, 'msg'=>'Unknown order.'];
    if (($rec['status'] ?? '') === 'refunded') return ['ok'=>true, 'msg'=>'Already refunded.'];
    if (($rec['status'] ?? '') !== 'held')     return ['ok'=>false, 'msg'=>'Only in-escrow orders can be refunded (status: '.($rec['status'] ?? '?').').'];
    if (empty($rec['payment_intent']))         return ['ok'=>false, 'msg'=>'No payment on file to refund.'];
    try {
        $r = stripe_escrow_refund($rec['acct_id'], $rec['payment_intent']);
        escrow_update($ref, ['status'=>'refunded', 'refunded_at'=>date('c'), 'refund_id'=>$r->id ?? '']);
        $back = number_format(((int)($r->amount ?? 0))/100, 2);
        if (!empty($rec['buyer_id'])) {
            require_once __DIR__.'/push.php';
            vestra_push_send($rec['buyer_id'], 'VESTRA — refund issued ↩',
                'Order '.$ref.' — €'.$back.' is being returned to your card in full.', '/buyer?tab=orders');
        }
        return ['ok'=>true, 'msg'=>'Refunded €'.$back.' to the buyer.'];
    } catch (\Throwable $e) {
        error_log('[VESTRA Escrow] refund failed '.$ref.': '.$e->getMessage());
        return ['ok'=>false, 'msg'=>'Refund failed: '.$e->getMessage()];
    }
}
