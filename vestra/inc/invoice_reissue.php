<?php
/*
 * KESILMIS, ODENMEMIS BIR FATURAYI BASKA PARA BIRIMINDE YENIDEN KESMEK
 * (operator, 6 Eki 2026: "VES-8E46FFA2 bu siparis ve diger odemesi alinmamis
 * Avrupa hesabi ile kesilmis tum faturalari USD ile Mercury faturasi ile yap"
 * + "sen yap hepsini usd faturasina gecir").
 *
 * Kesilmis bir belgenin para birimi DEGISTIRILEMEZ (invoice_cur_late): belge
 * alicinin elinde, numara yanmis. Dogru yol uc adim ve bu dosya ucunu TEK
 * govdede yapiyor -- ayri ayri kosulduklarinda aralarinda sipariste faturanin
 * OLMADIGI bir an kaliyor:
 *   1) eski belgeyi ARSIVLE (vestra_invoice_delete -- silmez, numara yanmis kalir),
 *   2) birimi yaz ve birimi tutmayan bir banka profilini (EUR profili, USD belge)
 *      kaldir -- kalsaydi kesim "profil birimi != belge birimi" diye dururdu,
 *   3) ODEME SAATINI SIFIRLA ve yeni numarayla kes.
 *
 * Saat neden sifirlaniyor: saat KESILMIS BIR BELGEYE bagli (KURAL 7) ve
 * vestra_invoice_delete ona dokunmuyor. Dokunulmasaydi yarin dolan bir saat,
 * alicinin daha yeni gordugu bir USD belgesini IPTAL ettirirdi -- ustelik bir
 * ABD hesabina SWIFT havalesi 2-5 is gunu suruyor. Eski degerler
 * `invoice_replaced[].clock`'a tasiniyor (iz), saat "unstamped"a donuyor ve
 * cron_order_payment yeni belge icin ilk hatirlatmayi gonderip saati YENIDEN
 * baslatiyor: "ilk mektup gitmeden iptal yok" aynen gecerli.
 *
 * MUSTERIYE HICBIR SEY GITMEZ (notify=false). Yeni belgenin mektubu ayri adim:
 * send-campaign-preview -> reply_letter=order_invoice_pdf + replaced=1, once
 * send=false (KURAL 18). Eski numara ve tutar `invoice_replaced` kaydina
 * yaziliyor ki mektup "su faturanin yerine geciyor" cumlesini KAYITTAN kursun.
 *
 * KAPSAM BILEREK DAR -- her ref icin hepsi tutmazsa HICBIRI yapilmaz:
 *   - TAM 1 kesilmis belge ve keseni PLATFORM ('vestra'). Satici kestiyse
 *     (Agaya Paris gibi) belgenin ustundeki tuzel kisi baska; Acerasoft'un ABD
 *     hesabini onun belgesine basmak satici kaydini degistirmek olur -- ayri karar.
 *   - durum 'pending', parasi GELMEMIS, DEKONTU YOK (dekont varsa para yolda
 *     olabilir), escrow degil.
 *   - eski belgenin birimi hedef birimden FARKLI.
 *   - yeni yuk hedef birimde KURULABILIYOR (kur damgasi var), odeme kutusu
 *     CIKIYOR (KURAL 5r) ve siparis kesim kapisindan geciyor (KURAL 43).
 */
require_once __DIR__.'/products.php';
require_once __DIR__.'/orders.php';
require_once __DIR__.'/invoice.php';
require_once __DIR__.'/offers.php';
require_once __DIR__.'/auth.php';

/** Kabul edilmis TEKLIF mi, siparis mi. Teklif faturasi teklifin kendi kurucusundan
 *  kesiliyor (birlesik yol) -- siparis yolundan kesmek ayni satisa baska bir belge cizerdi. */
function vestra_invoice_reissue_kind(string $ref): string {
    $rs = vestra_read_json('offer_responses.json');
    if (isset($rs[$ref]) && is_array($rs[$ref]) && (string)($rs[$ref]['status'] ?? '') === 'accept'
        && vestra_offer_row($ref)) return 'offer';
    return 'order';
}

/** Tek ref'in SALT OKUNUR plani: kontroller + hedef birimdeki yeni belgenin onizlemesi.
 *  Hicbir sey yazmaz. */
function vestra_invoice_reissue_plan(string $ref, string $cur): array {
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    $cur = strtoupper(trim($cur));
    $out = ['ref' => $ref, 'kind' => '', 'ok' => false, 'errors' => [], 'old' => [], 'new' => [],
            'grace' => ['phase' => '-', 'deadline' => null], 'buyer' => '', 'lang' => '', 'ref_dirty' => ''];
    if ($ref === '') { $out['errors'][] = 'ref bos'; return $out; }
    if (!in_array($cur, vestra_invoice_currencies(), true)) { $out['errors'][] = "desteklenmeyen birim: {$cur}"; return $out; }

    /* Satir ref'i BOSLUKLU olabilir (canlida O34FE5: denetim trim'le buluyor, kesim
       yolu tam esitlik ariyor). Teklifte uygulama once ref'i temizler -- yoksa
       vestra_offer_order_ensure ayni teklife IKINCI bir siparis satiri acardi. */
    $row = null; $rawRef = '';
    foreach (vestra_read_csv('orders.csv') as $r) { if (trim((string)($r['ref'] ?? '')) === $ref) { $row = $r; $rawRef = (string)$r['ref']; break; } }
    if (!$row) {
        /* TESHIS: gorunmez bir karakter (trim'in silmedigi) tasiyan satir var mi?
           Ham ref onaltilik basiliyor -- olcmeden duzeltme yazilmaz. */
        foreach (vestra_read_csv('orders.csv') as $r) {
            $raw = (string)($r['ref'] ?? '');
            if ($raw !== $ref && preg_replace('/[^A-Za-z0-9_-]/', '', $raw) === $ref) {
                $stAll = vestra_read_json('order_statuses.json');
                $out['errors'][] = "orders.csv satirinin ref'i GORUNMEZ karakter tasiyor (hex ".bin2hex($raw).")"
                    ." | order_statuses ham anahtar ".(isset($stAll[$raw]) ? 'VAR' : 'yok')
                    .", temiz anahtar ".(isset($stAll[$ref]) ? 'VAR' : 'yok');
                return $out;
            }
        }
        $out['errors'][] = 'orders.csv satiri yok'; return $out;
    }
    $out['kind']  = vestra_invoice_reissue_kind($ref);
    $out['ref_dirty'] = $rawRef !== $ref ? (string)json_encode($rawRef) : '';
    if ($out['ref_dirty'] !== '' && $out['kind'] !== 'offer') {
        $out['errors'][] = "orders.csv satirinin ref'i bosluklu ({$out['ref_dirty']}) -- siparis yolu onu bulamaz";
        return $out;
    }
    $out['buyer'] = trim((string)($row['company'] ?? '')) ?: trim((string)($row['name'] ?? ''));
    $acc = vestra_order_buyer_account($row);
    $out['lang'] = $acc ? (string)($acc['lang'] ?? '') : '';

    $st = (array)((vestra_read_json('order_statuses.json'))[$ref] ?? []);
    $status = (string)($st['status'] ?? 'pending');
    if ($status !== 'pending') $out['errors'][] = "siparis durumu {$status} -- yalniz 'pending' yeniden kesilir";
    if (str_contains((string)($row['notes'] ?? ''), 'Secure escrow')) $out['errors'][] = 'escrow (kart) siparisi -- havale faturasi degil';
    $paid = vestra_order_payment_settled($ref, $st);
    if (!empty($paid['settled'])) $out['errors'][] = 'parasi GELMIS ('.$paid['via'].')';
    $g = vestra_order_payment_grace($st, time(), $ref);
    $out['grace'] = ['phase' => $g['phase'], 'deadline' => $g['deadline']];
    if ($g['phase'] === 'has_receipt') $out['errors'][] = 'DEKONT yuklenmis -- para yolda olabilir, once dekontu kontrol edin';

    $invs = vestra_invoices_for_ref($ref);
    if (count($invs) !== 1) { $out['errors'][] = 'kesilmis belge sayisi '.count($invs).' -- TAM 1 gerekli'; return $out; }
    $iv = $invs[0];
    $out['old'] = ['no' => (string)$iv['no'], 'currency' => strtoupper((string)$iv['currency']) ?: 'EUR',
                   'total' => (float)$iv['total'], 'seller_key' => (string)$iv['seller_key']];
    if ((string)$iv['seller_key'] !== 'vestra') {
        $out['errors'][] = 'keseni platform degil ('.$iv['seller_label'].') -- ABD hesabi o tuzel kisinin belgesine basilamaz';
    }
    if ($out['old']['currency'] === $cur) $out['errors'][] = "belge zaten {$cur}";

    /* YENI BELGENIN ONIZLEMESI -- kesimin cagiracagi AYNI kurucu, hedef birimle.
       Birimi tutmayan bir banka profili kaldirilacak; onizleme onu kaldirilmis gibi
       kuruyor (aksi halde kurucu kesimin hic gormeyecegi bir uyusmazlik gosterirdi). */
    if ($out['kind'] === 'offer') {
        $bankKey = vestra_offer_invoice_bank($ref);
        $clear = $bankKey !== '' && vestra_platform_bank_mismatch($bankKey, $cur) !== '';
        $p = vestra_offers_combined_invoice_payload([$ref], 'vestra', null, null, true, null, $cur);
        if (!empty($p['error'])) { $out['errors'][] = (string)$p['error']; return $out; }
        if ($clear) { $p['seller'] = vestra_platform_seller(); $p['meta']['bank'] = ''; }
    } else {
        $st2 = vestra_read_json('order_statuses.json');
        $bankKey = (string)($st2[$ref]['invoice_bank'] ?? '');
        $clear = $bankKey !== '' && vestra_platform_bank_mismatch($bankKey, $cur) !== '';
        $payloads = vestra_order_invoice_payloads($ref, $cur);
        if (count($payloads) !== 1) { $out['errors'][] = 'yeni belge '.count($payloads).' dilim -- TAM 1 gerekli'; return $out; }
        if ($clear) { $payloads[0]['seller'] = vestra_platform_seller(); $payloads[0]['meta']['bank'] = ''; }
        foreach (vestra_order_issue_prereqs($ref, $payloads) as $why) $out['errors'][] = "kesim kapisi: {$why}";
        $p = $payloads[0];
    }
    if (!empty($p['currency_error'])) { $out['errors'][] = 'kur damgasi yok: '.$p['currency_error']; return $out; }
    if (!empty($p['bank_error']))     { $out['errors'][] = (string)$p['bank_error']; return $out; }
    $gap = vestra_invoice_payment_gap($p['seller'], $cur, false);
    if ($gap !== '') $out['errors'][] = 'odeme kutusu: '.$gap;
    $goods = 0.0; foreach ($p['items'] as $it) $goods += (float)($it['line'] ?? 0);
    $out['new'] = [
        'currency'   => strtoupper((string)($p['meta']['currency'] ?? '')),
        'total'      => round($goods - (float)($p['meta']['discount'] ?? 0) + (float)($p['meta']['shipping'] ?? 0), 2),
        'fx_note'    => (string)($p['meta']['fx_note'] ?? ''),
        'pay_lines'  => count(vestra_payment_rails($p['seller'] ?? vestra_platform_seller(), $cur)),
        'bank_clear' => $clear ? $bankKey : '',
    ];
    if ($out['new']['currency'] !== $cur) $out['errors'][] = 'yuk '.$out['new']['currency'].' kuruldu, '.$cur.' degil';
    $out['ok'] = !$out['errors'];
    return $out;
}

/** orders.csv'de ref'i bosluklu satirin ref'ini temizler. Ayni ref'in temiz bir satiri
 *  da varsa DOKUNMAZ (ikisini birlestirmek ayri karar). Yedek + atomik takas + geri okuma. */
function vestra_invoice_reissue_fix_ref(string $ref): bool {
    $file = vestra_data_dir().'/orders.csv';
    $in = @fopen($file, 'r'); if (!$in) return false;
    $head = fgetcsv($in, null, ',', '"', '\\');
    $idx = is_array($head) ? array_search('ref', $head, true) : false;
    if ($idx === false) { fclose($in); return false; }
    $rows = []; while (($r = fgetcsv($in, null, ',', '"', '\\')) !== false) $rows[] = $r;
    fclose($in);
    $clean = 0; $dirty = 0;
    foreach ($rows as $r) { $v = (string)($r[$idx] ?? ''); if ($v === $ref) $clean++; elseif (trim($v) === $ref) $dirty++; }
    if ($clean > 0 || $dirty !== 1) return $clean > 0 && $dirty === 0;
    foreach ($rows as &$r) { if (trim((string)($r[$idx] ?? '')) === $ref) $r[$idx] = $ref; } unset($r);
    @copy($file, $file.'.bak-reissue-'.date('Ymd_His'));
    $tmp = $file.'.tmp-reissue';
    $out = fopen($tmp, 'w'); if (!$out) return false;
    fputcsv($out, $head, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    $n = 0; foreach (vestra_read_csv('orders.csv') as $r) if ((string)($r['ref'] ?? '') === $ref) $n++;
    return $n === 1;
}

/** Saati SIFIRLA, eski degerleri ve eski belgeyi iz olarak sakla. Diger alanlara dokunmaz. */
function vestra_invoice_reissue_reset_clock(string $ref, array $old, string $cur): void {
    $st = vestra_read_json('order_statuses.json');
    $e = (isset($st[$ref]) && is_array($st[$ref])) ? $st[$ref] : [];
    $prev = array_intersect_key($e, array_flip(['payment_grace_start', 'payment_reminder_sent_at']));
    unset($e['payment_grace_start'], $e['payment_reminder_sent_at']);
    $rep = (isset($e['invoice_replaced']) && is_array($e['invoice_replaced'])) ? $e['invoice_replaced'] : [];
    $rep[] = ['no' => (string)($old['no'] ?? ''), 'currency' => (string)($old['currency'] ?? ''),
              'total' => (float)($old['total'] ?? 0), 'to_currency' => $cur, 'at' => date('c'),
              'clock' => $prev];
    $e['invoice_replaced'] = $rep;
    $st[$ref] = $e;
    vestra_write_json('order_statuses.json', $st);
}

/**
 * Plan HER ref icin tutuyorsa uygular; biri tutmazsa HICBIRINE dokunmaz.
 * Bir ref yarida kalirsa (arsivlendi ama kesilemedi) DURUR, hangisinde kaldigini
 * soyler ve sonrakilere gecmez.
 */
function vestra_invoice_reissue_apply(array $refs, string $cur): array {
    $cur = strtoupper(trim($cur));
    $refs = array_values(array_unique(array_filter(array_map(fn($r) => preg_replace('/[^A-Za-z0-9_-]/', '', (string)$r), $refs))));
    $out = ['ok' => false, 'error' => '', 'plans' => [], 'done' => []];
    if (!$refs) { $out['error'] = 'ref yok'; return $out; }
    foreach ($refs as $r) $out['plans'][$r] = vestra_invoice_reissue_plan($r, $cur);
    $bad = array_filter($out['plans'], fn($p) => !$p['ok']);
    if ($bad) { $out['error'] = 'plan tutmuyor: '.implode(', ', array_keys($bad)).' -- HICBIR sey yapilmadi'; return $out; }

    foreach ($refs as $r) {
        $pl = $out['plans'][$r];
        if ($pl['ref_dirty'] !== '' && !vestra_invoice_reissue_fix_ref($r)) {
            $out['error'] = "{$r}: orders.csv ref'i temizlenemedi -- burada durdum (bu ref'te hicbir sey degismedi)"; return $out;
        }
        $del = vestra_invoice_delete($r, 'vestra');
        if (empty($del['ok'])) { $out['error'] = "{$r}: eski belge arsivlenemedi ({$del['error']}) -- burada durdum"; return $out; }
        if ($pl['kind'] === 'offer') {
            if ($pl['new']['bank_clear'] !== '') vestra_offer_set_invoice_bank($r, '');
            vestra_offer_set_invoice_currency($r, $cur);
        } else {
            if ($pl['new']['bank_clear'] !== '') vestra_order_set_invoice_bank($r, '');
            vestra_order_set_invoice_currency($r, $cur);
        }
        vestra_invoice_reissue_reset_clock($r, $pl['old'], $cur);

        if ($pl['kind'] === 'offer') {
            $res = vestra_offers_combined_invoice_issue([$r], 'vestra', null, null, null, false, '', $cur);
            if (!empty($res['error'])) { $out['error'] = "{$r}: eski belge ARSIVLENDI ama yenisi kesilemedi: {$res['error']} -- burada durdum"; return $out; }
            $no = (string)$res['no'];
        } else {
            $res = vestra_issue_order_invoices($r);
            if (isset($res['error'])) { $out['error'] = "{$r}: eski belge ARSIVLENDI ama yenisi kesilemedi: {$res['error']} -- burada durdum"; return $out; }
            $no = (string)($res[0]['no'] ?? '');
        }
        /* GERI OKUMA: kayittaki TEK belge yeni numara, hedef birim, eski numara degil. */
        $back = vestra_invoices_for_ref($r);
        if (count($back) !== 1 || (string)$back[0]['no'] !== $no || strtoupper((string)$back[0]['currency']) !== $cur
            || (string)$back[0]['no'] === (string)$pl['old']['no']) {
            $out['error'] = "{$r}: geri okuma tutmuyor (belge ".count($back).", no ".($back[0]['no'] ?? '-').") -- burada durdum";
            return $out;
        }
        $out['done'][$r] = ['no' => $no, 'total' => (float)$back[0]['total'], 'currency' => $cur, 'old' => $pl['old']['no']];
    }
    $out['ok'] = true;
    return $out;
}
