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

/** Naive word-wrap using VestraPdf's width estimate (kept here so callers don't need a PDF instance). */
function vestra_invoice_wrap(string $s, float $maxW, float $size, bool $bold = false): array {
    $words = preg_split('/\s+/', trim($s));
    $lines = []; $cur = '';
    foreach ($words as $w) {
        if ($w === '') continue;
        $try = $cur === '' ? $w : $cur.' '.$w;
        if (mb_strlen($try) * $size * ($bold ? 0.60 : 0.52) > $maxW && $cur !== '') { $lines[] = $cur; $cur = $w; }
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
    $pdf->text($left, $y, 20, 'VESTRA', true);
    $pdf->text($left, $y - 16, 8, 'acerasoft LLC  ·  vestrasales.com', false);
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
            $sellerAcc['email'] ?? '',
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
    $colSku = $left + 4; $colDesc = $left + 75; $colCol = $left + 260; $colQty = $left + 355; $colUnit = $left + 400;
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
        $rowH = max(13, count($descLines) * 11) + 8;
        $need($rowH);
        $pdf->text($colSku, $y, 9, (string)($it['sku'] ?? ''));
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

    $need(60);
    $y -= 4;
    $pdf->line($left, $y, $right, $y, 0.7, 0.5);
    $y -= 18;
    $pdf->textR($colUnit, $y, 10, 'Goods total', true);
    $pdf->textR($right - 4, $y, 11, eur($goodsTotal), true);
    $y -= 34;

    $need(40);
    $pdf->text($left, $y, 8, $paid
        ? 'Paid in full via VESTRA secure escrow. Funds are released to the seller once the buyer confirms delivery.'
        : 'Payment due within 14 days via bank transfer to the account shown above (if provided).', false);
    $y -= 12;
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
function vestra_ensure_invoice(array $order, array $items, ?array $sellerAcc): array {
    $sellerKey = vestra_invoice_seller_key($sellerAcc);
    $pdfPath  = vestra_invoice_file($order['ref'], $sellerKey);
    $metaPath = vestra_invoice_meta_file($order['ref'], $sellerKey);
    if (is_file($pdfPath) && is_file($metaPath)) {
        $meta = json_decode((string)file_get_contents($metaPath), true) ?: [];
        return ['no' => $meta['no'] ?? '', 'path' => $pdfPath, 'seller_key' => $sellerKey];
    }
    $no = vestra_next_invoice_no($sellerKey);
    $bytes = vestra_render_invoice_pdf($order, $items, $sellerAcc, $no);
    file_put_contents($pdfPath, $bytes, LOCK_EX);
    file_put_contents($metaPath, json_encode([
        'no' => $no, 'ref' => $order['ref'], 'seller_key' => $sellerKey, 'issued_at' => date('c'),
    ], JSON_PRETTY_PRINT), LOCK_EX);
    return ['no' => $no, 'path' => $pdfPath, 'seller_key' => $sellerKey];
}

/** Invoices already issued for a ref (order or offer) — for rendering download links. No regeneration. */
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
