<?php
/**
 * VESTRA — automatic PDF invoices for orders and accepted offers.
 *
 * One invoice per SELLER per sale: a cart that spans several sellers produces
 * one PDF per seller, each carrying only that seller's line items and that
 * seller's own bank details — a company can only invoice for what it sold.
 * Lines with no real seller (demo/catalog items with no seller_uid) are
 * grouped under a "VESTRA" platform-issued invoice with no bank details.
 *
 * Every invoice is generated ONCE, the first time the underlying sale is
 * confirmed (order placed / offer accepted), and the PDF bytes are persisted
 * immutably to data/invoices/ (web-blocked, same pattern as data/docs). A
 * seller editing their bank details afterwards never rewrites an
 * already-issued invoice — vestra_ensure_invoice() is idempotent and simply
 * hands back the existing file + invoice number on every later call.
 */
require_once __DIR__.'/pdf.php';

function vestra_invoice_dir(): string {
    $dir = dirname(__DIR__).'/data/invoices';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $htaccess = $dir.'/.htaccess';
    if (!is_file($htaccess)) @file_put_contents($htaccess,
        "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
    return $dir;
}

/** Filesystem-safe issuer key: the seller's account id, or 'vestra' for sellerless lines. */
function vestra_invoice_seller_key(?array $sellerAcc): string {
    return ($sellerAcc['id'] ?? '') !== '' ? $sellerAcc['id'] : 'vestra';
}
function vestra_invoice_slug(string $ref, string $sellerKey): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $ref.'__'.$sellerKey);
}
function vestra_invoice_file(string $ref, string $sellerKey): string {
    return vestra_invoice_dir().'/'.vestra_invoice_slug($ref, $sellerKey).'.pdf';
}
function vestra_invoice_meta_file(string $ref, string $sellerKey): string {
    return vestra_invoice_dir().'/'.vestra_invoice_slug($ref, $sellerKey).'.json';
}

/** Sequential per-issuer invoice numbers: INV-2026-000001. File-locked — safe under concurrent orders. */
function vestra_next_invoice_no(string $sellerKey): string {
    $f = dirname(__DIR__).'/data/invoice_seq.json';
    $fh = @fopen($f, 'c+');
    if (!$fh) return 'INV-'.date('Y').'-'.substr(bin2hex(random_bytes(3)), 0, 6); // pathological fallback, still unique
    flock($fh, LOCK_EX);
    $seq = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($seq)) $seq = [];
    $year = date('Y');
    $k = $sellerKey.'-'.$year;
    $seq[$k] = (int)($seq[$k] ?? 0) + 1;
    $n = $seq[$k];
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, json_encode($seq, JSON_PRETTY_PRINT));
    flock($fh, LOCK_UN); fclose($fh);
    return sprintf('INV-%s-%06d', $year, $n);
}

/**
 * Word-wrap using VestraPdf's width estimate (kept here so callers don't need a PDF instance).
 *
 * Breaks inside a word when the word itself is wider than the column. Splitting on spaces
 * alone leaves a model code like WH1JQ040B139MAI whole, and a whole code wider than the SKU
 * column does not wrap — it just keeps drawing, straight over the description beside it.
 */
function vestra_invoice_wrap(string $s, float $maxW, float $size, bool $bold = false): array {
    $wide = fn(string $t): float => mb_strlen($t) * $size * ($bold ? 0.60 : 0.52);
    $chop = function (string $w) use ($wide, $maxW): array {
        $out = []; $cur = '';
        foreach (preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if ($cur !== '' && $wide($cur.$ch) > $maxW) { $out[] = $cur; $cur = ''; }
            $cur .= $ch;
        }
        if ($cur !== '') $out[] = $cur;
        return $out;
    };

    $lines = []; $cur = '';
    foreach (preg_split('/\s+/', trim($s)) as $w) {
        if ($w === '') continue;
        if ($maxW > 0 && $wide($w) > $maxW) {
            /* Unbreakable and too wide: close the current line, then let the pieces stand on
               their own so nothing is space-joined back into an over-wide line. */
            if ($cur !== '') { $lines[] = $cur; $cur = ''; }
            $pieces = $chop($w);
            $cur = (string)array_pop($pieces);
            foreach ($pieces as $p) $lines[] = $p;
            continue;
        }
        $try = $cur === '' ? $w : $cur.' '.$w;
        if ($wide($try) > $maxW && $cur !== '') { $lines[] = $cur; $cur = $w; }
        else $cur = $try;
    }
    if ($cur !== '') $lines[] = $cur;
    return $lines ?: [''];
}

/**
 * Render one seller's invoice PDF.
 * $order: ['ref'=>string,'date'=>ISO8601,'buyer'=>['company','vat','name','email','country','address']]
 * $items: list of ['sku','brand','name','colors'=>string[],'qty'=>int,'unit'=>float,'line'=>float]
 */
function vestra_render_invoice_pdf(array $order, array $items, ?array $sellerAcc, string $invoiceNo): string {
    $pdf = new VestraPdf();
    $left = 50.0; $right = 545.0; $width = $right - $left; $bottom = 70.0;
    $y = VestraPdf::PAGE_H - 60;
    $newPage = function() use (&$y, $pdf) { $pdf->addPage(); $y = VestraPdf::PAGE_H - 60; };
    $need = function(float $h) use (&$y, $bottom, $newPage) { if ($y - $h < $bottom) $newPage(); };

    // ── Header ──
    /* The mark, then the wordmark as live text beside it. Not one baked image: the name has
       to survive a reader that fails on the logo, and text stays selectable and searchable
       in the buyer's document system. If the file is missing the wordmark simply starts at
       the margin and the header still reads correctly. */
    $markX = $left;
    $logo  = vestra_pdf_thumb('/icon-192.png', 128);
    if ($logo !== '' && $pdf->imageJpeg($logo, $left, $y - 13, 32, 32)) $markX = $left + 40;
    $pdf->text($markX, $y, 20, 'VESTRA', true);
    $pdf->text($markX, $y - 16, 8, 'acerasoft LLC  ·  vestrasales.com', false);
    $pdf->textR($right, $y, 22, 'INVOICE', true);
    $pdf->textR($right, $y - 18, 9, 'Invoice No:  '.$invoiceNo);
    $pdf->textR($right, $y - 30, 9, 'Date:  '.substr($order['date'] ?? date('c'), 0, 10));
    $pdf->textR($right, $y - 42, 9, 'Order ref:  '.($order['ref'] ?? ''));
    $y -= 66;
    $pdf->line($left, $y, $right, $y, 1.0, 0.15);
    $y -= 22;

    // ── From / Bill To ──
    $colW = ($width - 24) / 2; $fromX = $left; $toX = $left + $colW + 24;
    $pdf->text($fromX, $y, 10, 'From (Seller)', true);
    $pdf->text($toX, $y, 10, 'Bill To (Buyer)', true);
    $y -= 15;

    if ($sellerAcc) {
        $sellerLines = array_values(array_filter([
            $sellerAcc['company'] ?: ($sellerAcc['name'] ?? '') ?: 'Seller',
            $sellerAcc['address'] ?? '',
            $sellerAcc['country'] ?? '',
            !empty($sellerAcc['vat_id'])    ? 'VAT ID: '.$sellerAcc['vat_id'] : '',
            !empty($sellerAcc['reg_number'])? 'Reg. no: '.$sellerAcc['reg_number'] : '',
            /* No seller e-mail. The address on the account is a login credential, not a
               billing contact, and it is usually a personal one — printing it turns a
               company invoice into a person's mailbox and invites the buyer to settle the
               order off-platform. Company, address, VAT and registration number are what
               an invoice has to carry; correspondence goes through VESTRA. */
        ], fn($v) => $v !== ''));
    } else {
        $sellerLines = ['VESTRA (acerasoft LLC)', 'Marketplace-catalog item', 'support@vestrasales.com'];
    }
    $b = $order['buyer'] ?? [];
    $buyerLines = array_values(array_filter([
        $b['company'] ?? '', $b['address'] ?? '', $b['country'] ?? '',
        !empty($b['vat']) ? 'VAT ID: '.$b['vat'] : '',
        $b['name'] ?? '', $b['email'] ?? '',
    ], fn($v) => $v !== ''));

    /* Wrap each block to its own column before pairing them up. A company address written as
       one line — street, district, post code, city — is easily wider than half the page, and
       unwrapped it ran straight across into the buyer's block on the right. */
    $wrapCol = function (array $lines) use ($colW): array {
        $out = [];
        foreach ($lines as $l) foreach (vestra_invoice_wrap($l, $colW - 8, 9) as $w) $out[] = $w;
        return $out;
    };
    $sellerLines = $wrapCol($sellerLines);
    $buyerLines  = $wrapCol($buyerLines);

    $n = max(count($sellerLines), count($buyerLines));
    for ($i = 0; $i < $n; $i++) {
        if (isset($sellerLines[$i])) $pdf->text($fromX, $y, 9, $sellerLines[$i]);
        if (isset($buyerLines[$i]))  $pdf->text($toX, $y, 9, $buyerLines[$i]);
        $y -= 13;
    }
    $y -= 8;

    // ── Payment box ──
    // Escrow-paid orders show a "PAID via secure escrow" box instead of bank
    // details — the buyer has already paid by card and no transfer is due.
    $paid = !empty($order['paid']);
    if ($paid) {
        $paidLines = ['Paid by card via VESTRA secure escrow — no bank transfer required.'];
        if (!empty($order['paid_at'])) $paidLines[] = 'Payment received: '.$order['paid_at'];
        $boxH = 16 + count($paidLines) * 13;
        $need($boxH + 14);
        $pdf->rectFill($left, $y - $boxH + 4, $width, $boxH);
        $by = $y - 8;
        $pdf->text($left + 10, $by, 9, 'Payment status — PAID (escrow)', true);
        foreach ($paidLines as $bl) { $by -= 13; $pdf->text($left + 10, $by, 9, $bl); }
        $y -= ($boxH + 14);
    } else {
        // ── Bank details (seller-issued invoices only, when provided) ──
        $bankLines = $sellerAcc ? array_values(array_filter([
            !empty($sellerAcc['bank_name'])   ? 'Bank: '.$sellerAcc['bank_name'] : '',
            !empty($sellerAcc['bank_holder']) ? 'Account holder: '.$sellerAcc['bank_holder'] : '',
            !empty($sellerAcc['bank_iban'])   ? 'IBAN: '.$sellerAcc['bank_iban'] : '',
            !empty($sellerAcc['bank_bic'])    ? 'BIC / SWIFT: '.$sellerAcc['bank_bic'] : '',
        ], fn($v) => $v !== '')) : [];
        if ($bankLines) {
            $boxH = 16 + count($bankLines) * 13;
            $need($boxH + 14);
            $pdf->rectFill($left, $y - $boxH + 4, $width, $boxH);
            $by = $y - 8;
            $pdf->text($left + 10, $by, 9, 'Payment details — bank transfer', true);
            foreach ($bankLines as $bl) { $by -= 13; $pdf->text($left + 10, $by, 9, $bl); }
            $y -= ($boxH + 14);
        } else {
            $y -= 6;
        }
    }

    // ── Line-items table ──
    $need(50);
    /* The SKU column carries full manufacturer model codes (WH1JQ040B139MAI, WV0MG10W7SS0NI),
       which is what the buyer and the customs entry match against, so it gets the room it
       needs rather than the description's leftovers. */
    $colSku = $left + 4; $colDesc = $left + 92; $colCol = $left + 265; $colQty = $left + 355; $colUnit = $left + 400;
    $pdf->rectFill($left, $y - 14, $width, 18, 0.88);
    $pdf->text($colSku, $y - 9, 8.5, 'SKU', true);
    $pdf->text($colDesc, $y - 9, 8.5, 'Description', true);
    $pdf->text($colCol, $y - 9, 8.5, 'Colour(s)', true);
    $pdf->text($colQty, $y - 9, 8.5, 'Qty', true);
    $pdf->text($colUnit, $y - 9, 8.5, 'Unit', true);
    $pdf->textR($right - 4, $y - 9, 8.5, 'Total', true);
    $y -= 30;

    $goodsTotal = 0.0;
    foreach ($items as $it) {
        $desc = trim(($it['brand'] ?? '').' '.($it['name'] ?? ''));
        $descLines = vestra_invoice_wrap($desc, $colCol - $colDesc - 6, 9);
        $skuLines  = vestra_invoice_wrap((string)($it['sku'] ?? ''), $colDesc - $colSku - 8, 8);
        $rowH = max(13, max(count($descLines), count($skuLines)) * 11) + 8;
        $need($rowH);
        foreach ($skuLines as $j => $sl)  $pdf->text($colSku,  $y - ($j * 10), 8, $sl);
        foreach ($descLines as $j => $dl) $pdf->text($colDesc, $y - ($j * 11), 9, $dl);
        if (!empty($it['colors'])) {
            $colTxt = implode(', ', (array)$it['colors']);
            foreach (vestra_invoice_wrap($colTxt, $colQty - $colCol - 6, 8) as $j => $cl) $pdf->text($colCol, $y - ($j * 10), 8, $cl);
        }
        $pdf->text($colQty, $y, 9, (string)((int)($it['qty'] ?? 0)));
        $pdf->text($colUnit, $y, 9, eur($it['unit'] ?? 0));
        $pdf->textR($right - 4, $y, 9, eur($it['line'] ?? 0));
        $goodsTotal += (float)($it['line'] ?? 0);
        $y -= $rowH;
    }

    /* A voucher and the freight charge must each be a visible line, not folded quietly into
       the goods total: this document is the seller's invoice and the basis of the bank
       transfer, so the buyer has to be able to reconcile it against the order confirmation
       line for line. On a multi-seller order the discount here is only this seller's
       apportioned share (see vestra_issue_order_invoices). */
    $discount = round((float)($order['discount'] ?? 0), 2);
    $shipping = round((float)($order['shipping'] ?? 0), 2);
    $shipLbl  = trim((string)($order['shipping_label'] ?? '')) ?: 'Shipping';

    $rows = [];
    if ($discount > 0 || $shipping > 0) $rows[] = ['Goods total', eur($goodsTotal)];
    if ($discount > 0) {
        $vcode = trim((string)($order['voucher_code'] ?? ''));
        $rows[] = ['Voucher'.($vcode !== '' ? ' '.$vcode : ''), '-'.eur($discount)];
    }
    if ($shipping > 0) $rows[] = [$shipLbl, eur($shipping)];
    $grand = max(0, $goodsTotal - $discount) + $shipping;

    $need(60 + count($rows) * 15);
    $y -= 4;
    $pdf->line($left, $y, $right, $y, 0.7, 0.5);
    $y -= 18;
    foreach ($rows as [$label, $amount]) {
        $pdf->textR($colUnit, $y, 10, $label);
        $pdf->textR($right - 4, $y, 10, $amount);
        $y -= 15;
    }
    if ($rows) { $y += 9; $pdf->line($colUnit - 60, $y, $right, $y, 0.5, 0.35); $y -= 15; }
    $pdf->textR($colUnit, $y, 10, $rows ? 'Total' : 'Goods total', true);
    $pdf->textR($right - 4, $y, 11, eur($grand), true);
    $y -= 34;

    $need(40);
    $pdf->text($left, $y, 8, $paid
        ? 'Paid in full via VESTRA secure escrow. Funds are released to the seller once the buyer confirms delivery.'
        : 'Payment due within 14 days via bank transfer to the account shown above (if provided).', false);
    $y -= 12;

    /* An order delivered in instalments has to say so on its invoice. Without it the first
       short consignment reads as an invoice discrepancy to the buyer's finance team, and to
       a customs broker as goods missing from the declared entry. Saying it here makes the
       short delivery the expected thing and points at the document that governs each box —
       the invoice value covers the whole order, the entry value never does. */
    if (!empty($order['partial_shipments'])) {
        $need(30);
        foreach ($pdf->wrap('Partial shipments permitted. The goods are delivered in instalments against this '
            .'invoice; each consignment travels with its own packing list stating the items and the value in that '
            .'consignment, which is the value to be declared for that entry.', $width, 8) as $fl) {
            $pdf->text($left, $y, 8, $fl); $y -= 11;
        }
        $y -= 2;
    }
    foreach ($pdf->wrap('This invoice is issued by the seller named above. VESTRA (acerasoft LLC) operates the marketplace connecting buyer and seller and is not the seller of record for this sale.', $width, 8) as $fl) {
        $pdf->text($left, $y, 8, $fl); $y -= 11;
    }

    return $pdf->output();
}

/**
 * Generate (once) and persist the PDF for one seller's slice of a sale.
 * Idempotent — a second call for the same ($order['ref'], $sellerAcc) returns
 * the already-issued invoice untouched rather than re-numbering/re-rendering.
 */
/* Automatic invoicing is SUSPENDED by default: after an order we confirm stock
   first and issue the invoice by hand (within the day). vestra_ensure_invoice()
   therefore only creates a PDF when explicitly forced — the operator's "Issue
   invoice" action, or a completed escrow card payment — or when auto-issuing is
   switched back on by defining VESTRA_AUTO_INVOICE=true. Already-issued invoices
   are always returned regardless of the switch. */
function vestra_auto_invoice_enabled(): bool {
    return defined('VESTRA_AUTO_INVOICE') ? (bool) VESTRA_AUTO_INVOICE : false;
}
function vestra_ensure_invoice(array $order, array $items, ?array $sellerAcc, bool $force = false): array {
    $sellerKey = vestra_invoice_seller_key($sellerAcc);
    $pdfPath  = vestra_invoice_file($order['ref'], $sellerKey);
    $metaPath = vestra_invoice_meta_file($order['ref'], $sellerKey);
    if (is_file($pdfPath) && is_file($metaPath)) {
        $meta = json_decode((string)file_get_contents($metaPath), true) ?: [];
        return ['no' => $meta['no'] ?? '', 'path' => $pdfPath, 'seller_key' => $sellerKey];
    }
    /* Suspended: no invoice is created until stock is confirmed and it is issued by hand. */
    if (!$force && !vestra_auto_invoice_enabled()) {
        return ['no' => '', 'path' => '', 'seller_key' => $sellerKey, 'pending' => true];
    }
    $no = vestra_next_invoice_no($sellerKey);
    $bytes = vestra_render_invoice_pdf($order, $items, $sellerAcc, $no);
    file_put_contents($pdfPath, $bytes, LOCK_EX);
    file_put_contents($metaPath, json_encode([
        'no' => $no, 'ref' => $order['ref'], 'seller_key' => $sellerKey, 'issued_at' => date('c'),
        /* Kept so the download name can carry the buyer without the serving endpoint
           re-reading orders/offers/requests to find out who the invoice belongs to. */
        'buyer' => trim((string)(($order['buyer']['company'] ?? '') ?: ($order['buyer']['name'] ?? ''))),
    ], JSON_PRETTY_PRINT), LOCK_EX);
    return ['no' => $no, 'path' => $pdfPath, 'seller_key' => $sellerKey];
}

/** Invoices already issued for a ref (order or offer) — for rendering download links. No regeneration. */
/**
 * Name the file gets when it is downloaded: buyer and order reference.
 *
 * "invoice.pdf" in a downloads folder is indistinguishable from every other invoice, and the
 * reference alone still needs looking up. Both parties file these by who and which order, so
 * the name carries both. Falls back to the reference when the buyer is unknown — invoices
 * issued before this was recorded have no buyer in their meta.
 */
function vestra_invoice_download_name(string $ref, string $sellerKey): string {
    $meta  = @json_decode((string)@file_get_contents(vestra_invoice_meta_file($ref, $sellerKey)), true);
    $buyer = is_array($meta) ? trim((string)($meta['buyer'] ?? '')) : '';
    $slug  = '';
    if ($buyer !== '') {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $buyer);
        $slug  = trim(preg_replace('/-+/', '-', preg_replace('/[^A-Za-z0-9]+/', '-', (string)$ascii)), '-');
        $slug  = mb_substr($slug, 0, 60);
    }
    return ($slug !== '' ? $slug.'-' : '').$ref.'.pdf';
}

function vestra_invoices_for_ref(string $ref): array {
    $dir = vestra_invoice_dir();
    $safeRef = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    $out = [];
    foreach (glob($dir.'/'.$safeRef.'__*.json') ?: [] as $metaFile) {
        $meta = json_decode((string)file_get_contents($metaFile), true);
        if (!is_array($meta)) continue;
        $sellerKey = $meta['seller_key'] ?? '';
        $label = 'VESTRA';
        if ($sellerKey !== '' && $sellerKey !== 'vestra') {
            foreach (auth_accounts() as $a) {
                if (($a['id'] ?? '') === $sellerKey) { $label = $a['company'] ?: ($a['name'] ?: 'Seller'); break; }
            }
        }
        $out[] = [
            'no' => $meta['no'] ?? '', 'seller_key' => $sellerKey, 'seller_label' => $label,
            'url' => '/invoice?ref='.urlencode($ref).'&seller='.urlencode($sellerKey),
        ];
    }
    usort($out, fn($a, $b) => strcmp($a['no'], $b['no']));
    return $out;
}

/**
 * Manually issue (force) every per-seller invoice for an order ref — used by the
 * operator's "Issue invoice" action once stock is confirmed. Rebuilds the buyer
 * and per-seller line data from orders.csv (address recovered from the notes).
 * Idempotent: already-issued invoices are returned untouched. Returns the list.
 */
function vestra_issue_order_invoices(string $ref): array {
    require_once __DIR__.'/orders.php';
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    $orderRow = null;
    foreach (vestra_read_csv('orders.csv') as $row) { if (($row['ref'] ?? '') === $ref) { $orderRow = $row; break; } }
    if (!$orderRow) return [];
    $ld = vestra_order_lines($orderRow);
    $address = '';
    if (preg_match('/Deliver to: (.*?)(?:\.\s|$)/u', (string)($orderRow['notes'] ?? ''), $m)) $address = trim($m[1]);
    $orderMeta = [
        'ref' => $ref, 'date' => $orderRow['timestamp'] ?? date('c'),
        /* Freight is charged on the order, not per seller: splitting one consignment's
           carriage across sellers would invent numbers nobody can reconcile. It rides on
           the first invoice; a second seller's invoice shows goods only. */
        'shipping' => round((float)($orderRow['shipping'] ?? 0), 2),
        'shipping_label' => trim((string)($orderRow['shipping_label'] ?? '')),
        'partial_shipments' => !empty($orderRow['partial_shipments']),
        'buyer' => [
            'company' => $orderRow['company'] ?? '', 'vat' => $orderRow['vat'] ?? '',
            'name' => $orderRow['name'] ?? '', 'email' => $orderRow['email'] ?? '',
            'country' => $orderRow['country'] ?? '', 'address' => $address,
        ],
    ];
    $bySeller = [];
    foreach ($ld['lines'] as $l) { $bySeller[$l['seller_uid'] ?: 'vestra'][] = $l; }

    /* A voucher discounts the ORDER, but invoices are issued per seller. Putting the whole
       discount on each slice would deduct it as many times as there are sellers, so it is
       split in proportion to each seller's goods value, and the last slice takes the rounding
       remainder — the per-invoice amounts then add back up to exactly what was granted. */
    $orderDiscount = round((float)($orderRow['discount'] ?? 0), 2);
    $voucherCode   = trim((string)($orderRow['voucher_code'] ?? ''));
    $goodsTotal = 0.0;
    foreach ($ld['lines'] as $l) { $goodsTotal += (float)($l['line'] ?? 0); }
    $shares = [];
    if ($orderDiscount > 0 && $goodsTotal > 0) {
        $acc = 0.0; $keys = array_keys($bySeller); $lastKey = end($keys);
        foreach ($bySeller as $sid => $sellerItems) {
            $sellerGoods = 0.0;
            foreach ($sellerItems as $l) { $sellerGoods += (float)($l['line'] ?? 0); }
            $share = ($sid === $lastKey)
                ? round($orderDiscount - $acc, 2)
                : round($orderDiscount * ($sellerGoods / $goodsTotal), 2);
            $shares[$sid] = max(0.0, $share);
            $acc = round($acc + $shares[$sid], 2);
        }
    }

    $issued = [];
    foreach ($bySeller as $sid => $sellerItems) {
        $sellerAcc = null;
        if ($sid !== 'vestra') { foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === $sid) { $sellerAcc = $a; break; } } }
        $meta = $orderMeta;
        if (!empty($shares[$sid])) { $meta['discount'] = $shares[$sid]; $meta['voucher_code'] = $voucherCode; }
        if ($sid !== array_key_first($bySeller)) { $meta['shipping'] = 0.0; }   // carriage billed once
        $iv = vestra_ensure_invoice($meta, $sellerItems, $sellerAcc, true);
        if (!empty($iv['no'])) $issued[] = $iv;
    }
    return $issued;
}
