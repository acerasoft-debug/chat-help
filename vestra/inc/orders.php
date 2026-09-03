<?php
/**
 * VESTRA — order detail helpers shared by the buyer, seller and admin panels.
 * Reconstructs a full line-item breakdown (brand/name/colours/image) from the
 * terse strings order.php writes into orders.csv, and renders the "open an
 * order and review it" detail view used by both buyer.php and seller.php.
 */

/** Find a product (demo or live) by SKU — reconstructs brand/name/image for an order line.
 *  Includes unlisted items: an order line for the Musterstueck sample must still show its
 *  brand, name and photo in the buyer, seller and admin panels. */
function vestra_product_by_sku(string $sku): ?array {
    if ($sku === '') return null;
    foreach (vestra_products(true) as $p) if (($p['sku'] ?? '') === $sku) return $p;
    /* Katalogdan cekilmis ilan (askidaki satici, bekleyen/reddedilen ilan):
       eski siparisin satiri yine de marka/ad/fotografla okunmali. Ham liste
       son care -- satis yolu degil, gecmis. */
    foreach (vestra_listings() as $p) if (($p['sku'] ?? '') === $sku) return $p;
    return null;
}

/* order.php prefixes the buyer's free-text notes with "Colours — SKU: A, B | SKU2: C. " when
   any line has a colour selection. Split that back out into a per-SKU colour map + the buyer's
   own remaining note text. SKUs/colour names never contain '.', so splitting on the first
   period reliably separates the auto-generated colour segment from free text. */
function vestra_order_notes_colors(string $notes): array {
    $colors = []; $rest = $notes;
    if (preg_match('/^Colours — (.+?)\.\s*(.*)$/s', $notes, $m)) {
        $rest = trim($m[2] ?? '');
        foreach (explode(' | ', $m[1]) as $seg) {
            if (preg_match('/^(\S+):\s*(.+)$/', trim($seg), $sm)) {
                $colors[$sm[1]] = array_map('trim', explode(',', $sm[2]));
            }
        }
    }
    return ['colors' => $colors, 'notes' => $rest];
}

/** Full line items for an order row, enriched with product info + per-SKU colours. */
function vestra_order_lines(array $orderRow): array {
    $parsed = vestra_parse_order_items($orderRow['items'] ?? '');
    $cn = vestra_order_notes_colors($orderRow['notes'] ?? '');
    $out = [];
    foreach ($parsed as $it) {
        $p = vestra_product_by_sku($it['sku']);
        $out[] = [
            'sku' => $it['sku'], 'qty' => $it['qty'], 'unit' => $it['unit'], 'line' => round($it['qty'] * $it['unit'], 2),
            'brand' => $p['brand'] ?? '', 'name' => $p['name'] ?? t('Product no longer listed'),
            'image' => $p ? vestra_primary_image($p) : '', 'id' => $p['id'] ?? '', 'seller_uid' => $p['seller_uid'] ?? '',
            'colors' => $cn['colors'][$it['sku']] ?? [],
        ];
    }
    return ['lines' => $out, 'notes' => $cn['notes']];
}

function vestra_order_history_entry(string $status, string $by, string $note = ''): array {
    return array_filter(['status' => $status, 'at' => date('c'), 'by' => $by, 'note' => $note], fn($v) => $v !== '');
}

/** Distinct sellers whose SKUs appear in this order (uid => label), for the "seller(s)" info block. */
function vestra_order_sellers(array $lines): array {
    $out = [];
    foreach ($lines as $l) {
        $sid = $l['seller_uid'] ?: 'vestra';
        if (isset($out[$sid])) continue;
        if ($sid === 'vestra') { $out[$sid] = 'VESTRA'; continue; }
        foreach (auth_accounts() as $a) {
            if (($a['id'] ?? '') === $sid) { $out[$sid] = $a['company'] ?: ($a['name'] ?: t('Seller')); break; }
        }
    }
    return $out;
}

/* The chain a healthy order walks. 'preparing' and 'to_vestra' sit between payment and
   despatch because that is where the real waiting happens: the buyer has paid, nothing
   visible moves for days, and "Paid" on its own reads as "nobody is doing anything".
   Naming the two stages turns silence into progress the buyer can see. */
const VESTRA_ORDER_STEPS = ['pending', 'paid', 'preparing', 'to_vestra', 'shipped', 'completed'];

/* Cancelled is deliberately NOT a step. It is not a later stage of the same journey, it
   is the journey stopping, and putting it on the end of the chain would render every
   cancelled order as though it had passed through despatch first. */
const VESTRA_ORDER_CANCELLED = 'cancelled';

function vestra_order_status_label(string $status): string {
    return match ($status) {
        'review' => t('In review'),
        'paid' => t('Paid'),
        'preparing' => t('Being prepared'),
        'to_vestra' => t('On its way to VESTRA'),
        'shipped' => t('Shipped'), 'completed' => t('Completed'),
        'cancelled' => t('Cancelled'),
        default => t('Awaiting payment'),
    };
}

/** Everything the admin may set by hand, cancellation included. */
function vestra_order_settable_statuses(): array {
    return array_merge(VESTRA_ORDER_STEPS, [VESTRA_ORDER_CANCELLED]);
}

/**
 * The <option> list for every admin status picker.
 *
 * There are two pickers — the orders table and the order dossier — and they were two
 * hand-written copies of the same list. Adding a stage to one and forgetting the other
 * produces a page that looks entirely normal and quietly cannot reach half the chain.
 * One builder, driven by the same constant the timeline and the save handler use.
 */
function vestra_order_status_options(string $current): string {
    $icons = ['pending'=>'⏳','paid'=>'💶','preparing'=>'📦','to_vestra'=>'🚛',
              'shipped'=>'🚚','completed'=>'✓','cancelled'=>'✕'];
    $out = '';
    foreach (vestra_order_settable_statuses() as $s) {
        $out .= '<option value="'.htmlspecialchars($s).'"'.($current === $s ? ' selected' : '').'>'
              . ($icons[$s] ?? '').' '.htmlspecialchars(vestra_order_status_label($s)).'</option>';
    }
    return $out;
}

/**
 * Is this order still being checked over rather than waiting to be paid?
 *
 * A fresh order sits at 'pending', which the tracker labels "Awaiting payment" — but
 * invoicing is suspended at checkout, so until the operator confirms stock and issues
 * the invoice there is nothing for the buyer to pay against. Showing them a payment
 * prompt with no invoice and no bank details reads as "we are waiting on you" when the
 * truth is the opposite, and it contradicts the confirmation mail, which promises the
 * invoice is coming once stock is confirmed.
 *
 * Derived from whether an invoice exists rather than stored as its own status: the
 * invoice IS the fact that separates the two states, so this can never drift out of
 * sync with reality, and issuing an invoice moves the order on with no extra step.
 */
function vestra_order_in_review(string $ref, string $status): bool {
    if ($status !== 'pending' || $ref === '') return false;
    if (!function_exists('vestra_invoices_for_ref')) require_once __DIR__.'/invoice.php';
    return !vestra_invoices_for_ref($ref);
}

/** What the buyer is told while the order is in review. */
function vestra_order_review_note(string $orderDate = ''): string {
    $d = $orderDate !== '' ? substr($orderDate, 0, 10) : '';
    return ($d !== '' ? $d.' — ' : '')
        . t('Your order is being reviewed. We will confirm stock and contact you shortly.');
}

/**
 * Visual step tracker. In review the chain gains a leading step, so the buyer sees a
 * stage they are actually in rather than being parked on "Awaiting payment".
 */
function vestra_order_timeline_html(string $currentStatus, bool $inReview = false): string {
    /* A cancelled order gets its own marker rather than a position in the chain. Falling
       through to the chain would have parked it on "Awaiting payment" (array_search
       fails, idx defaults to 0), telling the buyer their cancelled order is waiting on
       their money. */
    if ($currentStatus === VESTRA_ORDER_CANCELLED) {
        return '<div class="otimeline"><div class="otstep now otcancelled">'
             . '<span class="otdot"></span><span class="otlabel">'
             . vestra_order_status_label(VESTRA_ORDER_CANCELLED).'</span></div></div>';
    }
    $steps = $inReview ? array_merge(['review'], VESTRA_ORDER_STEPS) : VESTRA_ORDER_STEPS;
    $idx = $inReview ? 0 : array_search($currentStatus, VESTRA_ORDER_STEPS, true);
    if ($idx === false) $idx = 0;
    $out = '<div class="otimeline">';
    foreach ($steps as $i => $st) {
        $cls = $i < $idx ? 'done' : ($i === $idx ? 'now' : '');
        $out .= '<div class="otstep '.$cls.'"><span class="otdot"></span><span class="otlabel">'.vestra_order_status_label($st).'</span></div>';
    }
    return $out.'</div>';
}

/**
 * Render the "open an order" detail view. $viewerRole is 'buyer' or 'seller';
 * $backHref/$formHref point back to the owning panel's orders tab.
 */
function vestra_render_order_detail(array $orderRow, array $statusEntry, string $viewerRole, string $viewerUid, string $backHref, string $formHref): string {
    $ref = $orderRow['ref'] ?? '';
    $status = $statusEntry['status'] ?? 'pending';
    $ld = vestra_order_lines($orderRow);
    $lines = $ld['lines']; $buyerNotes = $ld['notes'];
    $sellers = vestra_order_sellers($lines);

    $h = '<div class="panelcard">';
    $h .= '<div class="pcfhead"><h3>'.t('Order').' <span class="atag">'.htmlspecialchars($ref).'</span></h3><a class="btn btn-o btn-sm" href="'.htmlspecialchars($backHref).'">← '.t('Back to orders').'</a></div>';
    $inReview = vestra_order_in_review($ref, $status);
    $h .= vestra_order_timeline_html($status, $inReview);
    /* Said once, plainly, at the top — this is the answer to the only question a buyer
       has on a fresh order, and it is the same thing the confirmation mail promised. */
    if ($inReview) {
        $h .= '<div class="oreview"><span class="oreview-i">🔎</span><div><b>'.t('In review').'</b>'
            . '<div>'.htmlspecialchars(vestra_order_review_note((string)($orderRow['timestamp'] ?? ''))).'</div></div></div>';
    }

    // Payment method + escrow state + delivery address, parsed from the order record —
    // the three facts both sides ask about first.
    $rawNotes = (string)($orderRow['notes'] ?? '');
    $isEscrowOrder = str_contains($rawNotes, 'Secure escrow');
    $escrowBadge = '';
    if (function_exists('escrow_get')) {
        $er = escrow_get($ref);
        if ($er) { $isEscrowOrder = true; $escrowBadge = ' · '.escrow_badge($er['status'] ?? ''); }
    }
    $shipTo = '';
    if (preg_match('/Deliver to: (.*?)(?:\.\s|$)/u', $rawNotes, $m)) $shipTo = trim($m[1]);
    $h .= '<div class="hint" style="display:flex;gap:18px;flex-wrap:wrap;margin:2px 0 14px;font-size:13px">'
        . '<span><b>'.t('Payment').':</b> '.($isEscrowOrder ? '🛡️ '.t('Secure escrow (card)') : '🏦 '.t('Bank transfer (invoice)')).$escrowBadge.'</span>'
        . ($shipTo !== '' ? '<span><b>'.t('Deliver to').':</b> '.htmlspecialchars($shipTo).'</span>' : '')
        . '</div>';

    $h .= '<div class="odgrid">';
    // Line items
    $h .= '<div><table class="ctable"><thead><tr><th>'.t('Product').'</th><th>'.t('Colours').'</th><th>'.t('Qty').'</th><th class="r">'.t('Unit').'</th><th class="r">'.t('Line total').'</th></tr></thead><tbody>';
    $subtotal = 0.0;
    foreach ($lines as $l) {
        $subtotal += $l['line'];
        $prod = $l['id'] ? '<a class="acc" href="/product?id='.urlencode($l['id']).'">'.htmlspecialchars(trim($l['brand'].' '.$l['name'])).'</a>' : htmlspecialchars(trim($l['brand'].' '.$l['name']));
        $h .= '<tr><td>'.$prod.'<div class="hint">SKU '.htmlspecialchars($l['sku']).'</div></td>'.
              '<td class="hint">'.htmlspecialchars(implode(', ', $l['colors'])).'</td>'.
              '<td>'.(int)$l['qty'].'</td><td class="r">'.eur($l['unit']).'</td><td class="r">'.eur($l['line']).'</td></tr>';
    }
    $h .= '</tbody></table>';
    if ($buyerNotes !== '') $h .= '<p class="hint" style="margin-top:10px"><b>'.t('Buyer notes').':</b> '.htmlspecialchars($buyerNotes).'</p>';

    $invLinks = '';
    foreach (vestra_invoices_for_ref($ref) as $iv) {
        if ($viewerRole === 'seller' && $iv['seller_key'] !== $viewerUid) continue;
        $invLinks .= '<a class="btn btn-o btn-sm" href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener" style="margin:10px 10px 0 0">📄 '.t('Invoice').' '.htmlspecialchars(vestra_invoice_link_label($iv)).'</a>';
    }
    /* The order summary is always downloadable, invoice or not — before the invoice is
       issued it is the only paper the buyer has, and that is precisely when a purchasing
       team asks them for one. */
    $invLinks .= '<a class="btn btn-o btn-sm" href="/order-pdf?ref='.urlencode($ref).'" style="margin:10px 10px 0 0">⤓ '.t('Download order summary (PDF)').'</a>';
    $h .= '<div>'.$invLinks.'</div>';
    $h .= '</div>'; // end line-items column

    // Side column: counterpart info + tracking/notes
    $h .= '<div>';
    if ($viewerRole === 'buyer') {
        $h .= '<div class="panelcard" style="margin:0 0 14px"><div class="pcfhead"><h3 style="font-size:14px">'.t('Seller').'</h3></div>'.
              '<p style="margin:0">'.htmlspecialchars(implode(', ', $sellers)).'</p></div>';
        /* Bank-transfer receipt segment (operator, 2 Sep 2026): only where it makes
           sense — order still pending (not paid/shipped/cancelled), an invoice already
           exists (not "in review"), and it is a bank-transfer order, not escrow (card
           payment there is instant, a receipt has no meaning). */
        if ($status === 'pending' && !$inReview && !$isEscrowOrder) {
            if (!function_exists('vestra_order_receipt')) require_once __DIR__.'/receipts.php';
            $rcpt = vestra_order_receipt($ref);
            $h .= '<div class="panelcard" style="margin:0 0 14px"><div class="pcfhead"><h3 style="font-size:14px">💳 '.t('Payment').'</h3></div>';
            if ($rcpt && $rcpt['exists']) {
                $h .= '<p class="hint" style="margin:0">📎 '.t('Receipt received').' '
                    . htmlspecialchars(substr((string)($rcpt['uploaded_at'] ?? ''), 0, 10)).' — '.t('awaiting confirmation.').'</p>';
            } else {
                $h .= '<p class="hint" style="margin:0 0 10px">'
                    . t('Already paid by bank transfer? Attach the receipt here and we will confirm your order.').'</p>'
                    . vestra_receipt_upload_form($formHref, $ref);
                if (function_exists('vestra_doc_upload_js')) $h .= vestra_doc_upload_js();
            }
            $h .= '</div>';
        }
    } else {
        $h .= '<div class="panelcard" style="margin:0 0 14px"><div class="pcfhead"><h3 style="font-size:14px">'.t('Buyer').'</h3></div>'.
              '<p style="margin:0">'.htmlspecialchars($orderRow['company'] ?? '').'<br>'.
              htmlspecialchars($orderRow['name'] ?? '').' · <a class="acc" href="mailto:'.htmlspecialchars($orderRow['email'] ?? '').'">'.htmlspecialchars($orderRow['email'] ?? '').'</a>'.
              (!empty($orderRow['country']) ? '<br>'.htmlspecialchars($orderRow['country']) : '').'</p></div>';
    }

    $h .= '<div class="panelcard" style="margin:0"><div class="pcfhead"><h3 style="font-size:14px">'.t('Shipping').'</h3></div>';
    if ($viewerRole === 'seller') {
        $h .= '<form method="post" action="'.htmlspecialchars($formHref).'">
          <input type="hidden" name="_action" value="update_order_note">
          <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
          <label class="hint">'.t('Tracking number').'</label>
          <input name="tracking" value="'.htmlspecialchars($statusEntry['tracking'] ?? '').'" style="width:100%;margin-bottom:10px">
          <label class="hint">'.t('Note to buyer').'</label>
          <textarea name="seller_note" rows="2" style="width:100%;margin-bottom:10px">'.htmlspecialchars($statusEntry['seller_note'] ?? '').'</textarea>
          <button class="btn btn-p btn-sm" type="submit">'.t('Save').'</button>
        </form>';
    } else {
        $h .= '<p style="margin:0 0 6px"><b>'.t('Tracking number').':</b> '.($statusEntry['tracking'] ?? '' ? htmlspecialchars($statusEntry['tracking']) : '<span class="hint">'.t('Not shipped yet').'</span>').'</p>';
        if (!empty($statusEntry['seller_note'])) $h .= '<p style="margin:0"><b>'.t('Note from seller').':</b> '.htmlspecialchars($statusEntry['seller_note']).'</p>';
        if ($status === 'shipped') {
            $h .= '<form method="post" action="'.htmlspecialchars($formHref).'" style="margin-top:10px">
              <input type="hidden" name="_action" value="confirm_receipt">
              <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
              <button class="btn btn-p btn-sm" type="submit">✓ '.t('Confirm receipt').'</button></form>';
        }
    }
    $h .= '</div></div>'; // end side column
    $h .= '</div>'; // end odgrid

    if (!empty($statusEntry['history'])) {
        $h .= '<details style="margin-top:16px"><summary class="hint" style="cursor:pointer">'.t('Status history').'</summary><div style="margin-top:8px">';
        foreach ($statusEntry['history'] as $ev) {
            $h .= '<div class="hint" style="padding:4px 0">'.htmlspecialchars(substr($ev['at'] ?? '', 0, 16)).' — '.vestra_order_status_label($ev['status'] ?? '').
                  (!empty($ev['note']) ? ' · '.htmlspecialchars($ev['note']) : '').'</div>';
        }
        $h .= '</div></details>';
    }

    $h .= '</div>'; // end panelcard
    return $h;
}

/**
 * Delete an order outright — meant for test rows and duplicates that should never
 * have existed. Cancelling is the everyday action and leaves the record standing;
 * this removes it.
 *
 * Reads the RAW file instead of going through vestra_read_csv(), which hands rows
 * back newest-first. Writing that array straight back would silently invert the
 * whole ledger — every "recent orders" panel would start showing the oldest eight.
 * vestra_orders_fix_dup_refs() above sidesteps the same trap the same way.
 *
 * Returns rows removed, or -1 when the file could not be rewritten. The caller needs
 * that distinction: "no such order" and "the disk refused" are different faults and
 * a shared 0 would hide a permissions problem behind a reassuring message.
 */
function vestra_order_delete(string $ref): int {
    $ref = trim($ref);
    if ($ref === '') return 0;
    $file = vestra_data_dir().'/orders.csv';
    if (!is_readable($file)) return 0;
    $in = fopen($file, 'r'); if (!$in) return -1;
    $head = fgetcsv($in, null, ',', '"', '\\');
    if (!$head) { fclose($in); return 0; }
    $refIdx = array_search('ref', $head, true);
    if ($refIdx === false) { fclose($in); return 0; }

    $keep = []; $removed = 0;
    while (($r = fgetcsv($in, null, ',', '"', '\\')) !== false) {
        if ((string)($r[$refIdx] ?? '') === $ref) { $removed++; continue; }
        $keep[] = $r;
    }
    fclose($in);
    if (!$removed) return 0;

    /* Copy first. This is the one admin action with no undo, and a timestamped file
       costs nothing next to a row that cannot be typed back in. */
    @copy($file, $file.'.bak-del-'.date('Ymd_His'));

    $tmp = $file.'.tmp';
    $out = fopen($tmp, 'w'); if (!$out) return -1;
    fputcsv($out, $head, ',', '"', '\\');
    foreach ($keep as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    if (!rename($tmp, $file)) { @unlink($tmp); return -1; }   // atomic swap

    $all = vestra_read_json('order_statuses.json');
    if (isset($all[$ref])) { unset($all[$ref]); vestra_write_json('order_statuses.json', $all); }
    return $removed;
}

/* ── Duplicate-ref repair ────────────────────────────────────────────────────
 * Before the ref-collision fix, a ref was derived from buyer+items only, so the
 * same buyer reordering the same goods got the SAME ref. Status/tracking/escrow
 * all key on the ref, so those orders shared one status entry — updating one
 * order "changed them all" in the admin. This one-time repair keeps the FIRST
 * (oldest) occurrence — its invoices stay linked — and gives every later
 * duplicate a fresh unique ref, cloning the shared status entry so each order
 * keeps the status the admin last saw. Returns how many rows were re-reffed. */
function vestra_orders_fix_dup_refs(): int {
    $file = vestra_data_dir().'/orders.csv';
    if (!is_readable($file)) return 0;
    $in = fopen($file, 'r'); if (!$in) return 0;
    $head = fgetcsv($in, null, ',', '"', '\\');
    if (!$head) { fclose($in); return 0; }
    $refIdx = array_search('ref', $head, true);
    if ($refIdx === false) { fclose($in); return 0; }
    $rows = [];
    while (($r = fgetcsv($in, null, ',', '"', '\\')) !== false) $rows[] = $r;
    fclose($in);

    $statuses = vestra_read_json('order_statuses.json');
    $seen = []; $fixed = 0;
    $existing = array_flip(array_map(fn($r) => (string)($r[$refIdx] ?? ''), $rows));
    foreach ($rows as &$r) {
        $ref = (string)($r[$refIdx] ?? '');
        if ($ref === '') continue;
        if (!isset($seen[$ref])) { $seen[$ref] = true; continue; }
        do { $new = 'VES-'.strtoupper(substr(md5(random_bytes(16)), 0, 8)); }
        while (isset($seen[$new]) || isset($existing[$new]));
        if (isset($statuses[$ref])) $statuses[$new] = $statuses[$ref];
        $r[$refIdx] = $new; $seen[$new] = true; $existing[$new] = true; $fixed++;
    }
    unset($r);
    if (!$fixed) return 0;

    $tmp = $file.'.tmp';
    $out = fopen($tmp, 'w'); if (!$out) return 0;
    fputcsv($out, $head, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    rename($tmp, $file);   // atomic swap — readers never see a half-written file
    vestra_write_json('order_statuses.json', $statuses);
    return $fixed;
}

/* ── Payment reminder / auto-cancel (operator decision, 2 Sep 2026) ─────────
 * "Siparişlerin ödemesi 5 iş günü içerisinde gelmez ise otomatik kapanacağını
 * söyle" — a pending order with an ISSUED invoice (bank transfer, not escrow)
 * gets one reminder, and is cancelled automatically if nothing arrives within
 * 5 business days of that reminder. Both cron_order_payment.php and a one-off
 * operator letter (buyer_reply → payment_due) call the SAME sender below, so
 * the deadline a letter promises is the deadline that actually fires — the
 * lesson this repo has paid for more than once (KURAL 3, the tracking-soon
 * letter, the escrow ceiling: a written promise the code doesn't keep is worse
 * than not writing it). */
const VESTRA_ORDER_PAYMENT_GRACE_DAYS = 5;

/**
 * Pure phase machine for ONE order's payment clock. The caller decides
 * ELIGIBILITY (status is 'pending', an invoice exists, it is not an escrow
 * order) before calling this — mirrors auth_seller_doc_grace()'s split between
 * eligibility and clock math, for the same reason: clock math has to be
 * testable without a live order, and every caller re-deriving eligibility its
 * own way is how the checks drift apart.
 *
 * 'has_receipt': a bank-transfer receipt is already on file for this order.
 * The clock STOPS — cron must never auto-cancel an order the buyer has already
 * paid just because nobody has looked at the receipt yet (the same lesson
 * KURAL 2f encodes for seller documents: no automatic punishment while
 * evidence sits unread). The operator still confirms and marks it paid by
 * hand; this only prevents the wrong kind of automatic action.
 *
 * Business-day math is escrow.php's vestra_business_days_after($ts, $days) — the
 * escrow auto-release sweep (31 Aug 2026 decision) already needed the identical
 * "N business days, Sat/Sun skipped" clock, and a second copy of the same loop
 * here is exactly how this repo has drifted before: two functions of the same
 * shape, in two files, is one INSTANT fatal ("Cannot redeclare") the moment both
 * files load on the same request — which is every admin/buyer/seller page.
 *
 * @param array $statusEntry order_statuses.json[$ref] (or a fresh ['status'=>'pending'])
 */
function vestra_order_payment_grace(array $statusEntry, ?int $now = null): array {
    if (!function_exists('vestra_business_days_after')) require_once __DIR__.'/escrow.php';
    $now = $now ?? time();
    $receipt = $statusEntry['payment_receipt'] ?? null;
    if (is_array($receipt) && !empty($receipt['file'])) {
        return ['phase'=>'has_receipt', 'start'=>null, 'notice_sent'=>false, 'deadline'=>null, 'days_left'=>null];
    }
    $start = trim((string)($statusEntry['payment_grace_start'] ?? ''));
    if ($start === '') {
        return ['phase'=>'unstamped', 'start'=>null, 'notice_sent'=>false, 'deadline'=>null, 'days_left'=>null];
    }
    $noticeSent = !empty($statusEntry['payment_reminder_sent_at']);
    $deadline = vestra_business_days_after((int)(strtotime($start) ?: $now), VESTRA_ORDER_PAYMENT_GRACE_DAYS);
    if ($now < $deadline) {
        return ['phase'=>'running', 'start'=>$start, 'notice_sent'=>$noticeSent, 'deadline'=>$deadline,
                'days_left'=>(int)ceil(($deadline - $now) / 86400)];
    }
    return ['phase'=>'overdue', 'start'=>$start, 'notice_sent'=>$noticeSent, 'deadline'=>$deadline, 'days_left'=>0];
}

/**
 * Sends (or resends) the payment-due reminder for one pending, invoiced order, and
 * starts the auto-cancel clock the FIRST time this runs for that order. Single sender
 * for both the daily cron and the operator's one-off letter — see the note above the
 * constant for why that matters.
 *
 * Returns ['ok'=>bool, 'error'=>string, 'phase_before'=>string, 'deadline'=>?int, 'mail_ok'=>bool].
 * 'ok'=false only for 'has_receipt' (caller should not be sending a cancellation threat
 * over a payment we may already have).
 */
function vestra_order_payment_reminder_send(string $ref, array $orderRow, string $invoiceNo, float $total, string $currency): array {
    if (!function_exists('vestra_tpl_order_payment_due')) require_once __DIR__.'/email_templates.php';
    $now = time();
    $all = vestra_read_json('order_statuses.json');
    $entry = $all[$ref] ?? ['status'=>'pending'];
    $g = vestra_order_payment_grace($entry, $now);
    if ($g['phase'] === 'has_receipt') {
        return ['ok'=>false, 'error'=>'has_receipt', 'phase_before'=>$g['phase'], 'deadline'=>null, 'mail_ok'=>false];
    }
    if ($g['phase'] === 'unstamped') {
        $entry['payment_grace_start'] = date('c', $now);
        $all[$ref] = $entry;
        vestra_write_json('order_statuses.json', $all);
        $g = vestra_order_payment_grace($entry, $now);
    }
    $buyerName = trim((string)($orderRow['name'] ?? '')) ?: trim((string)($orderRow['company'] ?? ''));
    $acc = function_exists('auth_find') ? auth_find((string)($orderRow['email'] ?? '')) : null;
    $uploadUrl = 'https://vestrasales.com/buyer?tab=orders&view='.rawurlencode($ref);
    [$subject, $body, $opts] = vestra_tpl_order_payment_due(
        $buyerName, $ref, $invoiceNo, $total, $currency, gmdate('j F Y', (int)$g['deadline']), (bool)$acc, $uploadUrl);
    $ok = !empty($orderRow['email']) && vestra_send_mail((string)$orderRow['email'], $subject, $body, '', '', null, '', $opts);
    if ($ok) {
        $all2 = vestra_read_json('order_statuses.json');
        $all2[$ref] = array_merge($all2[$ref] ?? $entry, ['payment_reminder_sent_at'=>date('c', $now)]);
        vestra_write_json('order_statuses.json', $all2);
    }
    return ['ok'=>true, 'error'=>'', 'phase_before'=>$g['phase'], 'deadline'=>(int)$g['deadline'], 'mail_ok'=>$ok];
}

/**
 * Order summary as a PDF — the buyer's own copy of what they ordered.
 *
 * Deliberately NOT an invoice. The invoice is the seller's demand for payment: it carries
 * an invoice number, the seller's bank details and a due date, and it is issued by hand
 * once stock is confirmed. This is available from the moment the order exists, which is
 * exactly the window where the buyer has nothing on paper and their own colleagues are
 * asking what was ordered. Labelling it "Order summary" and stamping "not an invoice" on
 * it keeps the two from being paid against each other by mistake.
 *
 * Each line carries the model code, because that is what a buyer matches against their
 * own system and what they quote back to us in an e-mail.
 */
function vestra_render_order_pdf(array $orderRow, array $lines, string $statusLabel): string {
    require_once __DIR__.'/pdf.php';
    $pdf = new VestraPdf();
    $left = 50.0; $right = 545.0; $width = $right - $left; $bottom = 70.0;
    $y = VestraPdf::PAGE_H - 60;
    $newPage = function() use (&$y, $pdf) { $pdf->addPage(); $y = VestraPdf::PAGE_H - 60; };
    $need = function(float $h) use (&$y, $bottom, $newPage) { if ($y - $h < $bottom) $newPage(); };
    $ref = (string)($orderRow['ref'] ?? '');

    $pdf->text($left, $y, 20, 'VESTRA', true);
    $pdf->text($left, $y - 16, 8, 'Acerasoft LLC  ·  vestrasales.com', false);
    $pdf->textR($right, $y, 18, 'ORDER SUMMARY', true);
    $pdf->textR($right, $y - 18, 9, 'Order ref:  '.$ref);
    $pdf->textR($right, $y - 30, 9, 'Date:  '.substr((string)($orderRow['timestamp'] ?? ''), 0, 10));
    $pdf->textR($right, $y - 42, 9, 'Status:  '.$statusLabel);
    $y -= 66;
    $pdf->line($left, $y, $right, $y, 1.0, 0.15);
    $y -= 22;

    $pdf->text($left, $y, 10, 'Buyer', true); $y -= 15;
    foreach (array_filter([
        (string)($orderRow['company'] ?? ''),
        (string)($orderRow['name'] ?? ''),
        (string)($orderRow['email'] ?? ''),
        ((string)($orderRow['vat'] ?? '') !== '' ? 'VAT ID: '.$orderRow['vat'] : ''),
        (string)($orderRow['country'] ?? ''),
    ], fn($v) => trim((string)$v) !== '') as $bl) { $pdf->text($left, $y, 9, $bl); $y -= 13; }
    $y -= 10;

    $colSku = $left; $colDesc = $left + 96; $colQty = $right - 168; $colUnit = $right - 96;
    $pdf->rectFill($left - 6, $y - 5, $width + 12, 20, 0.94);
    $pdf->text($colSku, $y, 9, 'Model / SKU', true);
    $pdf->text($colDesc, $y, 9, 'Product', true);
    $pdf->textR($colQty + 34, $y, 9, 'Qty', true);
    $pdf->textR($colUnit + 40, $y, 9, 'Unit', true);
    $pdf->textR($right - 4, $y, 9, 'Line', true);
    $y -= 24;

    $goods = 0.0;
    foreach ($lines as $l) {
        $desc = trim((string)($l['brand'] ?? '').' '.(string)($l['name'] ?? ''));
        $descLines = $pdf->wrap($desc, $colQty - $colDesc - 8, 9);
        $rowH = max(13, count($descLines) * 11) + 8;
        $need($rowH);
        $pdf->text($colSku, $y, 9, (string)($l['sku'] ?? ''));
        foreach ($descLines as $j => $dl) $pdf->text($colDesc, $y - ($j * 11), 9, $dl);
        if (!empty($l['colors'])) {
            foreach ($pdf->wrap(implode(', ', (array)$l['colors']), $colQty - $colDesc - 8, 8) as $j => $cl)
                $pdf->text($colDesc, $y - (count($descLines) * 11) - ($j * 10) + 1, 8, $cl);
        }
        $pdf->textR($colQty + 34, $y, 9, (string)(int)($l['qty'] ?? 0));
        $pdf->textR($colUnit + 40, $y, 9, eur($l['unit'] ?? 0));
        $pdf->textR($right - 4, $y, 9, eur($l['line'] ?? 0));
        $goods += (float)($l['line'] ?? 0);
        $y -= $rowH + (!empty($l['colors']) ? 10 : 0);
    }

    $need(70);
    $y -= 4; $pdf->line($left, $y, $right, $y, 0.7, 0.5); $y -= 18;
    $discount = round((float)($orderRow['discount'] ?? 0), 2);
    $shipping = round((float)($orderRow['shipping'] ?? 0), 2);
    $shipLbl  = trim((string)($orderRow['shipping_label'] ?? '')) ?: 'Shipping';
    $total    = isset($orderRow['total']) ? round((float)$orderRow['total'], 2)
                                          : round(max(0, $goods - $discount) + $shipping, 2);

    /* Whatever the total carries over and above goods less discount has to be named. Freight
       is named from the order's own shipping line; anything still left after that is the
       escrow protection fee, which the buyer also pays on a card order. Unnamed, either one
       just reads as the arithmetic being wrong — and freight labelled "buyer protection fee",
       which is what happened before the shipping line existed, reads worse than that. */
    $rows = [];
    if ($discount > 0) {
        $vc = trim((string)($orderRow['voucher_code'] ?? ''));
        $rows[] = ['Voucher'.($vc !== '' ? ' '.$vc : ''), '-'.eur($discount)];
    }
    if ($shipping > 0) $rows[] = [$shipLbl, eur($shipping)];
    $fee = round($total - ($goods - $discount) - $shipping, 2);
    if ($fee > 0.009) $rows[] = ['Buyer protection fee', eur($fee)];

    if ($rows) {
        $pdf->textR($colUnit, $y, 10, 'Goods total');
        $pdf->textR($right - 4, $y, 10, eur($goods)); $y -= 15;
        foreach ($rows as [$label, $amount]) {
            $pdf->textR($colUnit, $y, 10, $label);
            $pdf->textR($right - 4, $y, 10, $amount); $y -= 15;
        }
        $y += 9; $pdf->line($colUnit - 60, $y, $right, $y, 0.5, 0.35); $y -= 15;
    }
    $pdf->textR($colUnit, $y, 10, 'Total', true);
    $pdf->textR($right - 4, $y, 11, eur($total), true);
    $y -= 30;

    $need(40);
    foreach ($pdf->wrap('This is an order summary for your records — not an invoice and not a demand for payment. '
        .'Your invoice, with the seller\'s bank details, is issued once stock is confirmed and is sent to you separately.', $width, 8) as $fl) {
        $pdf->text($left, $y, 8, $fl); $y -= 11;
    }
    return $pdf->output();
}


/**
 * Neutral order sheet — the same goods as the order summary, laid out to be handed to a
 * third party.
 *
 * Carries the order number, the photos, the model codes and the quantities, and nothing
 * else: no marketplace name, no buyer identity, no prices. A picking list forwarded to a
 * supplier should tell them what to pull off the shelf and reveal neither who is buying
 * nor what anyone is paying. The branded, priced document stays at
 * vestra_render_order_pdf() and is only reachable from the customer's own account.
 */
function vestra_render_order_sheet_pdf(array $orderRow, array $lines): string {
    require_once __DIR__.'/pdf.php';
    $pdf   = new VestraPdf();
    $left  = 50.0; $right = 545.0; $bottom = 50.0;
    $imgW  = 68.0;                    // photo column, square
    $txtX  = $left + $imgW + 14;
    $textW = $right - $txtX - 74;     // leave the right edge for the quantity
    $y     = VestraPdf::PAGE_H - 62;

    $ref  = (string)($orderRow['ref'] ?? '');
    $date = substr((string)($orderRow['timestamp'] ?? ''), 0, 10);

    $header = function () use ($pdf, $left, $right, $ref, $date, &$y) {
        $pdf->text($left, $y, 16, 'ORDER SHEET', true);
        $pdf->textR($right, $y + 2, 11, 'No. '.$ref, true);
        if ($date !== '') $pdf->textR($right, $y - 11, 9, $date);
        $y -= 18;
        $pdf->line($left, $y, $right, $y, 1.0, 0.15);
        $y -= 20;
        $pdf->text($left, $y, 8.5, 'ITEM', true);
        $pdf->textR($right, $y, 8.5, 'QUANTITY', true);
        $y -= 8;
        $pdf->line($left, $y, $right, $y, 0.5, 0.72);
        $y -= 22;
    };
    $header();

    $units = 0;
    foreach ($lines as $l) {
        $qty    = (int)($l['qty'] ?? 0);
        $units += $qty;
        $sku    = trim((string)($l['sku'] ?? ''));
        $brand  = trim((string)($l['brand'] ?? ''));
        $name   = trim((string)($l['name'] ?? ''));
        $cols   = array_values(array_filter(array_map('trim', array_map('strval', (array)($l['colors'] ?? []))), fn($c) => $c !== ''));

        /* Catalogue names frequently already open with the brand and close with the model
           code that the SKU line shows directly above ("BALMAIN BALMAIN Swimsuit —
           BKBU30810"). Harmless in a product page heading, but on a one-item-per-row
           picking sheet it reads as duplication. */
        if ($brand !== '' && stripos($name, $brand) === 0) $brand = '';
        if ($sku !== '') {
            $cut = preg_replace('/\s*[—–-]\s*'.preg_quote($sku, '/').'\s*$/ui', '', $name);
            if (is_string($cut) && $cut !== '') $name = trim($cut);
        }

        $nameLines = $pdf->wrap(trim($brand.' '.$name), $textW, 10, true);
        $colLines  = $cols ? $pdf->wrap('Colours: '.implode(', ', $cols), $textW, 8.5) : [];
        $rowH = max($imgW, 14 + count($nameLines) * 12 + count($colLines) * 11) + 14;

        if ($y - $rowH < $bottom) { $pdf->addPage(); $y = VestraPdf::PAGE_H - 62; $header(); }
        $top = $y;

        $jpg = vestra_pdf_thumb((string)($l['image'] ?? ''));
        if ($jpg === '' || !$pdf->imageJpeg($jpg, $left, $top - $imgW, $imgW, $imgW)) {
            $pdf->rectFill($left, $top - $imgW, $imgW, $imgW, 0.95);
            $pdf->textR($left + $imgW / 2 + 14, $top - $imgW / 2 - 3, 7.5, 'no photo');
        }

        $ty  = $top - 11;
        $sku = trim((string)($l['sku'] ?? ''));
        if ($sku !== '') { $pdf->text($txtX, $ty, 8.5, $sku); $ty -= 14; }
        foreach ($nameLines as $nl) { $pdf->text($txtX, $ty, 10, $nl, true); $ty -= 12; }
        foreach ($colLines as $cl)  { $pdf->text($txtX, $ty, 8.5, $cl);      $ty -= 11; }

        $pdf->textR($right, $top - 14, 15, (string)$qty, true);
        $pdf->textR($right, $top - 27, 8, 'pcs');

        $y = $top - $rowH;
        $pdf->line($left, $y + 9, $right, $y + 9, 0.4, 0.84);
    }

    if ($y - 40 < $bottom) { $pdf->addPage(); $y = VestraPdf::PAGE_H - 62; }
    $y -= 4;
    $pdf->line($left, $y, $right, $y, 0.9, 0.25); $y -= 18;
    $n = count($lines);
    $pdf->text($left, $y, 10, $n.' '.($n === 1 ? 'model' : 'models'), true);
    $pdf->textR($right, $y, 13, $units.' pcs total', true);
    return $pdf->output();
}
