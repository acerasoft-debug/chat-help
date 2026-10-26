<?php
/* FATURA ONAY KAPISI (22 Eki 2026).
 *
 * Operatör: *"bundan sonra fatura oluşturmadan bana test email gitsin, onay
 * istesin — müşteriye gitmeden"*. O güne kadar panelin "Approve & issue" düğmesi
 * ve iş akışının admin_mode=issue yolu numarayı YAKIP belgeyi alıcının hesabına
 * düşürüyordu; 👁 Draft vardı ama basmak operatörün aklına kalmıştı (KURAL 5r'nin
 * aynı dersi: kontrol, kesim yolunun ÜSTÜNDE olmalı, hatırlanmaya bırakılmamalı).
 * Aynı gün INV-2026-1039 (VIMA SRLS) kapanmış bir Alman IBAN'ıyla kesilip
 * gönderildi — belge alıcının eline geçtikten sonra düzeltmenin tek yolu yeni
 * numaraydı.
 *
 * Kapı AÇIKKEN her kesim yolu (sipariş, teklif, birleşik teklif) belgeyi KESMEZ:
 *   1. kesilecek belgenin BİREBİR taslağını çizer (aynı yük, aynı çizici,
 *      'DRAFT INVOICE' damgasıyla; numara yanmaz, diske yazılmaz),
 *   2. operatörün kutusuna (ops_email) taslak PDF'li bir ONAY e-postası yollar,
 *   3. isteği data/invoice_approvals.json'a yazar (yükün özeti + ÖZET HASH'İ),
 *   4. çağırana ['error'=>…, 'error_code'=>'gate'] döner — alıcıya HİÇBİR ŞEY gitmez.
 * Onay (panel ▸ Invoices ▸ Onayla ya da iş akışı admin_mode=invoice_gate
 * payload=approve:<ref>): yük YENİDEN kurulur, hash tutmuyorsa (kargo/fiyat/birim
 * değişmiş) DURUR; tutuyorsa kapı 'pass' ile geçilir ve kesim + alıcı mektubu
 * ESKİ YOLUN KENDİSİYLE yapılır (ikinci bir kesim gövdesi yazılmadı).
 *
 * Kapalı dosya = KAPALI (data/invoice_gate.json yoksa). Varsayılanın kapalı
 * olmasının sebebi testler değil: canlıda kapı admin_mode=invoice_gate payload=on
 * ile açılır ve panel kapalıysa kırmızı yazar — sessizce kapanan bir kapı
 * "açık sanılan" bir kapıdan daha tehlikeli olduğu için durum hep görünür.
 *
 * Muaf: redraft (aynı numarayla yeniden çizim, belge ZATEN alıcıda), escrow/kart
 * ödemesinin kendi faturası (para gelmiş), invoice_reissue (operatör kuru koşu +
 * move_apply ile zaten onayladı — kendi içinde 'pass' açar).
 *
 * Kütüğe/e-postaya banka NUMARASI yazılmaz: yalnızca satır sayısı. */

function vestra_invoice_gate_file(): string { return vestra_data_dir().'/invoice_gate.json'; }
function vestra_invoice_gate_store(): string { return vestra_data_dir().'/invoice_approvals.json'; }
const VESTRA_INVOICE_GATE_TTL = 7 * 86400;   /* onaylanmamış taslak 7 günde düşer: eski bir taslağı onaylamak, eski bir belge kesmektir */

/** Kapı açık mı? Dosya yoksa / bozuksa KAPALI. Her çağrıda okunur (panel ve testler anında görsün). */
function vestra_invoice_gate_on(): bool {
    $f = vestra_invoice_gate_file();
    if (!is_readable($f)) return false;
    $j = json_decode((string)file_get_contents($f), true);
    return is_array($j) && !empty($j['enabled']);
}

/** Açar/kapatır; yazma GERİ OKUNUR. */
function vestra_invoice_gate_set(bool $on, string $by = 'operator'): bool {
    $dir = vestra_data_dir(); if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ok = @file_put_contents(vestra_invoice_gate_file(),
        json_encode(['enabled' => $on, 'set_at' => date('c'), 'set_by' => $by], JSON_PRETTY_PRINT), LOCK_EX);
    @chmod(vestra_invoice_gate_file(), 0600);
    return $ok !== false && vestra_invoice_gate_on() === $on;
}

/** Onaylı geçiş bayrağı: yalnız vestra_invoice_gate_approve() ve invoice_reissue açar; kesim biter bitmez kapanır. */
function vestra_invoice_gate_pass(?bool $set = null): bool {
    static $pass = false;
    if ($set !== null) $pass = $set;
    return $pass;
}

/** Kapı bu kesimi durdurmalı mı? (açık VE geçiş bayrağı kapalı) */
function vestra_invoice_gate_blocks(): bool { return vestra_invoice_gate_on() && !vestra_invoice_gate_pass(); }

/* Yükün ÖZÜ: kalemler (sku/adet/birim/satır), birim, kargo, indirim, KDV, banka
   profili, kesen anahtar. Onayda yeniden kurulan yük bununla karşılaştırılır:
   operatör taslağı gördükten sonra kargo ya da fiyat değiştiyse, gördüğü belge
   ile kesilecek belge aynı şey değildir ve kesim DURUR. Alıcı adres/ad alanları
   bilerek dışarıda: adres düzeltmesi belgeyi "başka belge" yapmaz. */
function vestra_invoice_gate_hash(array $payloads): string {
    $core = [];
    foreach ($payloads as $p) {
        $m = (array)($p['meta'] ?? []);
        $items = [];
        foreach ((array)($p['items'] ?? []) as $it) {
            $items[] = [(string)($it['sku'] ?? ''), (float)($it['qty'] ?? 0), round((float)($it['unit'] ?? 0), 2), round((float)($it['line'] ?? 0), 2)];
        }
        $core[] = [
            'ref' => (string)($m['ref'] ?? ''), 'seller' => (string)($p['seller_key'] ?? ''),
            'cur' => strtoupper((string)($m['currency'] ?? 'EUR')), 'ship' => round((float)($m['shipping'] ?? 0), 2),
            'disc' => round((float)($m['discount'] ?? 0), 2), 'vat' => round((float)($m['vat_rate'] ?? 0), 2),
            'bank' => (string)($m['bank'] ?? ''), 'items' => $items,
        ];
    }
    return hash('sha256', json_encode($core));
}

/** Bekleyen istekler (süresi dolanlar düşürülür). token => kayıt */
function vestra_invoice_gate_requests(): array {
    $f = vestra_invoice_gate_store();
    $j = is_readable($f) ? json_decode((string)file_get_contents($f), true) : [];
    if (!is_array($j)) $j = [];
    $now = time(); $out = [];
    foreach ($j as $tok => $r) {
        if (!is_array($r)) continue;
        if ($now - (int)($r['created'] ?? 0) > VESTRA_INVOICE_GATE_TTL) continue;
        $out[(string)$tok] = $r;
    }
    return $out;
}

function vestra_invoice_gate_write(array $reqs): bool {
    $dir = vestra_data_dir(); if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $f = vestra_invoice_gate_store();
    $ok = @file_put_contents($f, json_encode($reqs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($f, 0600);
    return $ok !== false;
}

/** Bir ref'in bekleyen isteği (kind bağımsız). */
function vestra_invoice_gate_find(string $ref): ?array {
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    foreach (vestra_invoice_gate_requests() as $tok => $r) {
        if ((string)($r['ref'] ?? '') === $ref) return ['token' => (string)$tok] + $r;
    }
    return null;
}

/* Taslağın insan-dili özeti: e-postada, panelde ve kütükte AYNI satırlar.
   Banka satırları SAYI olarak -- değerler yalnızca ekteki PDF'te. */
function vestra_invoice_gate_summary(array $payloads): array {
    $s = ['buyer' => '', 'issuer' => '', 'cur' => 'EUR', 'goods' => 0.0, 'ship' => 0.0, 'disc' => 0.0, 'grand' => 0.0,
          'lines' => 0, 'qty' => 0, 'docs' => count($payloads), 'pay_lines' => 0, 'bank' => '', 'fx' => ''];
    foreach ($payloads as $i => $p) {
        $m = (array)($p['meta'] ?? []);
        if ($i === 0) {
            $b = (array)($m['buyer'] ?? []);
            $s['buyer'] = trim((string)($b['company'] ?? '')) ?: trim((string)($b['name'] ?? ''));
            $s['cur']   = strtoupper((string)($m['currency'] ?? 'EUR')) ?: 'EUR';
            $s['issuer'] = function_exists('vestra_invoice_issuer_name')
                ? vestra_invoice_issuer_name($p['seller'] ?: (function_exists('vestra_platform_seller') ? vestra_platform_seller() : null), 'Acerasoft LLC')
                : (string)($p['seller_key'] ?? '');
            $s['bank']  = (string)($m['bank'] ?? '');
            $s['fx']    = (string)($m['fx_note'] ?? '');
        }
        foreach ((array)($p['items'] ?? []) as $it) { $s['goods'] += (float)($it['line'] ?? 0); $s['qty'] += (int)($it['qty'] ?? 0); $s['lines']++; }
        $s['ship'] += (float)($m['shipping'] ?? 0);
        $s['disc'] += (float)($m['discount'] ?? 0);
        $acc = $p['seller'] ?: (function_exists('vestra_platform_seller') ? vestra_platform_seller() : []);
        if (function_exists('vestra_payment_rails')) $s['pay_lines'] += count(vestra_payment_rails((array)$acc, $s['cur']));
    }
    $s['goods'] = round($s['goods'], 2); $s['ship'] = round($s['ship'], 2); $s['disc'] = round($s['disc'], 2);
    $s['grand'] = round($s['goods'] - $s['disc'] + $s['ship'], 2);
    return $s;
}

function vestra_invoice_gate_money(string $cur, float $v): string {
    return ($cur === 'USD' ? 'US$' : ($cur === 'EUR' ? '€' : $cur.' ')).number_format($v, 2, '.', ',');
}

/**
 * KESİM YERİNE: taslağı çiz, operatöre yolla, isteği kaydet.
 * $kind: order | offer | combined. $args: onayda AYNI kesim fonksiyonuna verilecek
 * argümanlar. $payloads: çizicinin okuyacağı yük(ler). Dönen dizi bir HATA
 * dizisidir (error + error_code='gate'): çağıranlar zaten 'error'u durma sayıyor.
 */
function vestra_invoice_gate_request(string $kind, array $args, array $payloads, string $by = 'operator'): array {
    require_once __DIR__.'/notify.php';
    $ref = (string)(($payloads[0]['meta']['ref'] ?? '') ?: ($args['ref'] ?? ($args['refs'][0] ?? '')));
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    $sum = vestra_invoice_gate_summary($payloads);
    $token = bin2hex(random_bytes(12));

    /* Aynı ref'in eski bekleyen isteği DÜŞER: iki taslak, iki hash, iki onay
       düğmesi -- operatör hangisini gördüğünü bilemezdi. */
    $reqs = vestra_invoice_gate_requests();
    foreach ($reqs as $tok => $r) if ((string)($r['ref'] ?? '') === $ref) unset($reqs[$tok]);
    $reqs[$token] = ['kind' => $kind, 'ref' => $ref, 'args' => $args, 'hash' => vestra_invoice_gate_hash($payloads),
                     'summary' => $sum, 'created' => time(), 'by' => $by, 'mailed' => false];

    /* Taslak PDF(ler): ekte. Geçici dosya; gönderim bitince silinir. */
    $atts = []; $tmp = [];
    if (function_exists('vestra_render_invoice_pdf')) {
        foreach ($payloads as $i => $p) {
            $bytes = vestra_render_invoice_pdf((array)$p['meta'], (array)$p['items'], $p['seller'], '', true);
            if (strncmp($bytes, '%PDF-', 5) !== 0) continue;
            $path = sys_get_temp_dir().'/vestra-gate-'.$token.'-'.$i.'.pdf';
            if (@file_put_contents($path, $bytes) === false) continue;
            $tmp[] = $path;
            $atts[] = ['name' => 'DRAFT-'.$ref.($i ? '-'.($i+1) : '').'.pdf', 'path' => $path];
        }
    }

    $money = fn(float $v) => vestra_invoice_gate_money($sum['cur'], $v);
    $link  = 'https://vestrasales.com/admin?tab=invoices&approve='.$token;
    /* Operatör bildirimi İNGİLİZCE (operatör, 26 Eki 2026: "bana bildirimleri de İngilizce
       ya da Almanca yap"); diğer yönetici bildirimleri zaten İngilizce. */
    $kindTxt = ['order' => 'order invoice', 'offer' => 'offer invoice', 'combined' => 'combined offer invoice'][$kind] ?? $kind;
    $subject = '⏳ INVOICE APPROVAL — '.$ref.' · '.$money($sum['grand']).' · '.$sum['buyer'];
    $body = "An {$kindTxt} is ready to be issued and is WAITING FOR YOUR APPROVAL. Nothing has been sent to the customer and no invoice number has been used.\n\n"
          . "Reference  : {$ref}\n"
          . "Buyer      : {$sum['buyer']}\n"
          . "Issuer     : {$sum['issuer']}\n"
          . "Currency   : {$sum['cur']}".($sum['fx'] !== '' ? "  (".preg_replace('/\s+/', ' ', $sum['fx']).")" : '')."\n"
          . "Items      : {$sum['lines']} lines, {$sum['qty']} pcs — goods ".$money($sum['goods'])
          . ($sum['disc'] > 0 ? ", discount -".$money($sum['disc']) : '')
          . ", shipping ".$money($sum['ship'])."\n"
          . "TOTAL      : ".$money($sum['grand'])."\n"
          . "Payment box: {$sum['pay_lines']} lines".($sum['bank'] !== '' ? " (profile: {$sum['bank']})" : '')
          . ($sum['pay_lines'] === 0 ? "  *** EMPTY — issuing will stop ***" : '')."\n"
          . "Documents  : {$sum['docs']}, draft PDF attached (stamped DRAFT)\n"
          . "Requested by: {$by}\n\n"
          . "Please check the draft. If it is correct, approve it — the invoice is issued at that moment and e-mailed to the customer:\n{$link}\n\n"
          . "If it is wrong, click 'Discard' in the admin panel or do nothing: the request expires by itself after 7 days.\n"
          . "If shipping, prices, currency or the bank profile change before approval, approval STOPS and a new draft is needed.\n\n"
          . "— VESTRA invoice approval gate";

    $to = (string)vestra_cfg('ops_email', 'acerasoft@gmail.com');
    $mailed = false;
    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $mailed = (bool)vestra_send_mail($to, $subject, $body, '', 'VESTRA Admin', null, '', $atts ? ['attachments' => $atts] : []);
    }
    foreach ($tmp as $t) @unlink($t);
    $reqs[$token]['mailed'] = $mailed;
    $saved = vestra_invoice_gate_write($reqs);

    return ['error' => 'Onay bekliyor: taslak fatura '.($mailed ? 'e-postanıza gönderildi' : 'kaydedildi (e-posta GİTMEDİ — panelden onaylayın)')
                     .' — müşteriye hiçbir şey gitmedi, numara yanmadı.',
            'error_code' => 'gate', 'gate' => true, 'token' => $token, 'ref' => $ref, 'mailed' => $mailed, 'saved' => $saved,
            'summary' => $sum];
}

/** İsteğin yükünü BUGÜNKÜ kayıttan yeniden kurar (onay karşılaştırması ve kesim için). */
function vestra_invoice_gate_payloads(array $req): array {
    $a = (array)($req['args'] ?? []);
    switch ((string)($req['kind'] ?? '')) {
        case 'order':
            require_once __DIR__.'/invoice.php';
            return vestra_order_invoice_payloads((string)($a['ref'] ?? ''));
        case 'offer':
            require_once __DIR__.'/offers.php';
            $p = vestra_offer_invoice_payload((string)($a['ref'] ?? ''));
            return $p ? [$p] : [];
        case 'combined':
            require_once __DIR__.'/offers.php';
            $p = vestra_offers_combined_invoice_payload((array)($a['refs'] ?? []), (string)($a['pick'] ?? ''), $a['vat_note'] ?? null,
                    isset($a['shipping']) ? (float)$a['shipping'] : null, false,
                    isset($a['vat_rate']) ? (float)$a['vat_rate'] : null, (string)($a['currency'] ?? ''));
            return (is_array($p) && empty($p['error'])) ? [$p] : [];
    }
    return [];
}

/**
 * ONAY: yük yeniden kurulur, hash tutmazsa DURUR; tutarsa kapı geçilir ve kesim
 * ESKİ YOLUN KENDİSİYLE yapılır (alıcı mektubu dahil). Başarıda istek silinir.
 */
function vestra_invoice_gate_approve(string $token, string $by = 'operator'): array {
    $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
    $reqs = vestra_invoice_gate_requests();
    $req = $reqs[$token] ?? null;
    if (!$req) return ['error' => 'Onay isteği bulunamadı ya da süresi dolmuş (7 gün) — kesimi yeniden başlatın, yeni taslak gelsin.', 'error_code' => 'gate_missing'];
    $ref = (string)($req['ref'] ?? '');
    $payloads = vestra_invoice_gate_payloads($req);
    if (!$payloads) return ['error' => "{$ref}: yük yeniden kurulamadı (kayıt silinmiş ya da teklif kabulden düşmüş) — kesilmedi.", 'error_code' => 'gate_changed'];
    if (vestra_invoice_gate_hash($payloads) !== (string)($req['hash'] ?? '')) {
        return ['error' => "{$ref}: kayıt taslaktan SONRA değişti (kargo/fiyat/birim/banka) — onayladığınız belge artık bu değil. Kesimi yeniden başlatın, yeni taslak gelsin.",
                'error_code' => 'gate_changed'];
    }
    $a = (array)($req['args'] ?? []);
    vestra_invoice_gate_pass(true);
    try {
        switch ((string)$req['kind']) {
            case 'order':
                require_once __DIR__.'/invoice.php';
                $r = vestra_order_invoice_issue((string)($a['ref'] ?? ''), (bool)($a['notify'] ?? true), (string)($a['copy_to'] ?? ''));
                break;
            case 'offer':
                require_once __DIR__.'/offers.php';
                $iv = vestra_offer_issue_invoice((string)($a['ref'] ?? ''), true);
                if (!is_array($iv) || !empty($iv['error']) || (string)($iv['no'] ?? '') === '') {
                    $r = ['error' => (string)($iv['error'] ?? 'Fatura kesilemedi (numara üretilmedi).'), 'error_code' => (string)($iv['error_code'] ?? '')];
                } else {
                    $r = ['ok' => true, 'nos' => [(string)$iv['no']], 'count' => 1] + vestra_offer_invoice_after_issue((string)($a['ref'] ?? ''), $iv);
                }
                break;
            case 'combined':
                require_once __DIR__.'/offers.php';
                $r = vestra_offers_combined_invoice_issue((array)($a['refs'] ?? []), (string)($a['pick'] ?? ''), $a['vat_note'] ?? null,
                        isset($a['shipping']) ? (float)$a['shipping'] : null, isset($a['vat_rate']) ? (float)$a['vat_rate'] : null,
                        (bool)($a['notify'] ?? true), (string)($a['copy_to'] ?? ''), $a['currency'] ?? null);
                if (empty($r['error'])) $r['nos'] = [(string)($r['no'] ?? '')];
                break;
            default:
                $r = ['error' => 'bilinmeyen istek türü: '.(string)$req['kind']];
        }
    } finally {
        vestra_invoice_gate_pass(false);
    }
    if (!empty($r['error'])) return $r;
    unset($reqs[$token]);
    vestra_invoice_gate_write($reqs);
    return $r + ['approved' => true, 'ref' => $ref, 'approved_by' => $by];
}

/** Vazgeç: isteği düşürür (belge kesilmez). */
function vestra_invoice_gate_discard(string $token): bool {
    $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
    $reqs = vestra_invoice_gate_requests();
    if (!isset($reqs[$token])) return false;
    unset($reqs[$token]);
    return vestra_invoice_gate_write($reqs);
}
