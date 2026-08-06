<?php
/**
 * VESTRA — order detail helpers shared by the buyer, seller and admin panels.
 * Reconstructs a full line-item breakdown (brand/name/colours/image) from the
 * terse strings order.php writes into orders.csv, and renders the "open an
 * order and review it" detail view used by both buyer.php and seller.php.
 */

/** Find a product (demo or live) by SKU — reconstructs brand/name/image for an order line. */
function vestra_product_by_sku(string $sku): ?array {
    if ($sku === '') return null;
    foreach (vestra_products() as $p) if (($p['sku'] ?? '') === $sku) return $p;
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

const VESTRA_ORDER_STEPS = ['pending', 'paid', 'shipped', 'completed'];

function vestra_order_status_label(string $status): string {
    return match ($status) {
        'review' => t('In review'),
        'paid' => t('Paid'), 'shipped' => t('Shipped'), 'completed' => t('Completed'),
        default => t('Awaiting payment'),
    };
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
        $invLinks .= '<a class="btn btn-o btn-sm" href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener" style="margin:10px 10px 0 0">📄 '.t('Invoice').' '.htmlspecialchars($iv['no']).'</a>';
    }
    if ($invLinks !== '') $h .= '<div>'.$invLinks.'</div>';
    $h .= '</div>'; // end line-items column

    // Side column: counterpart info + tracking/notes
    $h .= '<div>';
    if ($viewerRole === 'buyer') {
        $h .= '<div class="panelcard" style="margin:0 0 14px"><div class="pcfhead"><h3 style="font-size:14px">'.t('Seller').'</h3></div>'.
              '<p style="margin:0">'.htmlspecialchars(implode(', ', $sellers)).'</p></div>';
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
