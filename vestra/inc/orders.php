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
        'paid' => t('Paid'), 'shipped' => t('Shipped'), 'completed' => t('Completed'),
        default => t('Awaiting payment'),
    };
}

/** Visual step tracker (Awaiting payment → Paid → Shipped → Completed). */
function vestra_order_timeline_html(string $currentStatus): string {
    $idx = array_search($currentStatus, VESTRA_ORDER_STEPS, true);
    if ($idx === false) $idx = 0;
    $out = '<div class="otimeline">';
    foreach (VESTRA_ORDER_STEPS as $i => $st) {
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
    $h .= vestra_order_timeline_html($status);

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
