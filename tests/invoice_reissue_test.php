<?php
/*
 * KESILMIS, ODENMEMIS FATURAYI USD'YE YENIDEN KESMEK (vestra/inc/invoice_reissue.php;
 * operator, 6 Eki 2026: "sen yap hepsini usd faturasina gecir").
 *
 * Olculen (kum havuzunda GERCEK kesim):
 *   §1 plan: uygun siparis ve teklif GECER; dekontlu, odenmis, satici kesimi,
 *      damgasiz, zaten-USD ve iki belgeli sipariste DURUR
 *   §2 hep-ya-da-hic: listede tek uygunsuz ref varsa HICBIR belgeye dokunulmaz
 *   §3 uygulama: eski belge ARSIVDE, yeni numara USD, banka profili kalkti,
 *      ODEME SAATI sifirlandi, eski numara/tutar `invoice_replaced`'da;
 *      belgenin KENDISI ABD hesabini basiyor, EUR IBAN'ini basmiyor
 *   §4 teklif yolu: ayni, teklifin kendi kurucusundan; siparis satiri tek kalir
 *   §5 ikinci kosu bos geciyor (belge zaten USD) -- yeni numara yakmaz
 *
 * IBAN ve hesap numaralari SENTETIK (tests/no_real_iban_test.php izin listesi).
 */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-reissue-'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/inc/invoice_reissue.php';
require_once $root.'/inc/pdf.php';
if (vestra_data_dir() !== $sand.'/data' || vestra_invoice_dir() !== $sand.'/data/invoices') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }

$DE_IBAN = 'DE89370400440532013000';
$NL_IBAN = 'NL91ABNA0417164300';
$SELLER  = 'aaaabbbbccccdddd';
file_put_contents($sand.'/data/accounts.json', json_encode([
    ['id' => $SELLER, 'type' => 'seller', 'status' => 'active', 'company' => 'Seller Test SAS', 'email' => 's@example.com',
     'bank_holder' => 'Seller Test SAS', 'bank_iban' => 'FR1420041010050500013M02606'],
    ['id' => 'b1', 'type' => 'buyer', 'status' => 'active', 'company' => 'Buyer Co', 'email' => 'b@example.com', 'country' => 'Italia', 'lang' => 'it'],
]));
vestra_platform_seller_save([
    'bank_holder' => 'Acerasoft LLC', 'bank_name' => 'US Test Bank', 'bank_bic' => 'CHASUS33',
    'bank_account' => '123456789012', 'bank_routing' => '021000021',
    'bank_iban' => $DE_IBAN, 'bank_eur_bic' => 'COBADEFF', 'bank_eur_name' => 'Test Bank DE',
]);
vestra_platform_bank_save('nl', ['label'=>'Airwallex NL (EUR)','currency'=>'EUR','bank_iban'=>$NL_IBAN,'bank_bic'=>'AINHNL22','bank_name'=>'Airwallex NL']);

$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$h = fopen($sand.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
$row = fn(string $ref, string $notes = 'Payment: Bank transfer.') => ['2026-10-02T08:00:00+00:00', $ref, 'Buyer Co', '', 'Buyer', 'b@example.com', 'Italia', '',
            '50x ZZ-TEST-A @39.90', '1995.00', '0', '1995.00', '2025.00', $notes, 'yes', '2026-06-26', '', '0', '30.00', ''];
foreach (['VES-OK1', 'VES-RCPT', 'VES-PAID', 'VES-SELL', 'VES-NOFX', 'VES-USD', 'VES-ESC', 'VES-CTRL'] as $r) {
    fputcsv($h, $row($r, $r === 'VES-ESC' ? 'Payment: Secure escrow (card).' : 'Payment: Bank transfer.'), ',', '"', '\\');
}
/* Canlidaki O34FE5 gibi: satirin ref'i SONDA BOSLUKLU. */
fputcsv($h, ['2026-09-29T15:09:43+00:00', 'OTEST1 ', 'Buyer Co', '', 'Buyer', 'b@example.com', 'Italia', '',
             '10x ZZ-TEST-O @30.00', '300.00', '0', '300.00', '320.00', 'Payment: Bank transfer. Created from accepted offer(s) OTEST1 — invoiced together.',
             'yes', '2026-06-26', '', '0', '20.00', ''], ',', '"', '\\');
fclose($h);
$h = fopen($sand.'/data/offers.csv', 'w');
fputcsv($h, ['timestamp','ref','sku','product','qty','offer_unit','offer_total','company','email','message'], ',', '"', '\\');
fputcsv($h, ['2026-09-29T15:09:43+00:00', 'OTEST1', 'ZZ-TEST-O', 'Test Polo', 10, 30, 300, 'Buyer Co', 'b@example.com', 'test'], ',', '"', '\\');
fclose($h);
file_put_contents($sand.'/data/offer_responses.json', json_encode([
    'OTEST1' => ['status' => 'accept', 'invoice_shipping' => 20, 'invoice_bank' => 'nl'],
]));

$fx = ['usd' => 1.1500, 'date' => '2026-10-01', 'source' => 'ECB'];
$grace = ['payment_grace_start' => '2026-10-02T14:00:00+00:00', 'payment_reminder_sent_at' => '2026-10-02T14:00:01+00:00'];
$base = ['status' => 'pending', 'invoice_seller_uid' => 'vestra', 'fx' => $fx, 'shipping_set_at' => '2026-10-02T08:10:00+00:00',
         'invoice_bank' => 'nl', 'invoice_bank_at' => '2026-10-02T08:11:00+00:00'] + $grace;
vestra_write_json('order_statuses.json', [
    'VES-OK1'  => $base,
    'VES-RCPT' => $base + ['payment_receipt' => ['file' => 'r.pdf', 'at' => '2026-10-02']],
    'VES-PAID' => ['status' => 'paid'] + $base,
    'VES-SELL' => ['invoice_seller_uid' => $SELLER] + $base,
    'VES-NOFX' => array_diff_key($base, ['fx' => 1]),
    'VES-USD'  => $base,
    'VES-ESC'  => $base,
    'VES-CTRL' => $base,
    'OTEST1'   => ['fx' => $fx] + $grace,
]);

/* Mevcut EUR belgeleri, sitenin kendi kesim yolundan. */
foreach (['VES-OK1', 'VES-RCPT', 'VES-PAID', 'VES-SELL', 'VES-ESC', 'VES-CTRL'] as $r) {
    $s = vestra_read_json('order_statuses.json'); $keep = $s[$r];
    $s[$r]['status'] = 'pending'; unset($s[$r]['payment_receipt']);
    vestra_write_json('order_statuses.json', $s);
    $iv = vestra_issue_order_invoices($r);
    $s = vestra_read_json('order_statuses.json'); $s[$r] = $keep; vestra_write_json('order_statuses.json', $s);
    if (isset($iv['error']) || empty($iv[0]['no'])) { fwrite(STDERR, "kurulum: {$r} kesilemedi: ".json_encode($iv)."\n"); exit(1); }
}
/* NOFX: damga gecici olarak var, belge EUR kesilir, sonra damga kalkar. */
$s = vestra_read_json('order_statuses.json'); $s['VES-NOFX']['fx'] = $fx; vestra_write_json('order_statuses.json', $s);
vestra_issue_order_invoices('VES-NOFX');
$s = vestra_read_json('order_statuses.json'); unset($s['VES-NOFX']['fx']); vestra_write_json('order_statuses.json', $s);
/* USD: zaten USD kesilmis belge. */
vestra_order_set_invoice_bank('VES-USD', '');
vestra_order_set_invoice_currency('VES-USD', 'USD');
vestra_issue_order_invoices('VES-USD');
/* Teklif: kendi kurucusundan EUR kesilir. */
$oi = vestra_offers_combined_invoice_issue(['OTEST1'], 'vestra', null, null, null, false, '', 'EUR');
if (!empty($oi['error'])) { fwrite(STDERR, "kurulum: OTEST1 kesilemedi: {$oi['error']}\n"); exit(1); }

$oldNo = fn(string $r) => (string)(vestra_invoices_for_ref($r)[0]['no'] ?? '');
$snapInv = function () use ($sand) { $o = []; foreach (glob($sand.'/data/invoices/*') ?: [] as $f) if (is_file($f)) $o[basename($f)] = md5_file($f); ksort($o); return $o; };
$snapSt  = fn() => (string)file_get_contents($sand.'/data/order_statuses.json');

echo "== 1. plan ==\n";
$p = vestra_invoice_reissue_plan('VES-OK1', 'USD');
$t('uygun siparis GECER', $p['ok'] === true);
$t('...tur siparis', $p['kind'] === 'order');
$t('...eski belge EUR', ($p['old']['currency'] ?? '') === 'EUR');
$t('...yeni belge USD, tutar damgali kurla, BIRIM once cevrilir (50 x 45.89 + 34.50 = 2329.00)', ($p['new']['currency'] ?? '') === 'USD' && abs((float)($p['new']['total'] ?? 0) - 2329.00) < 0.011);
$t('...NL (EUR) profili kaldirilacak', ($p['new']['bank_clear'] ?? '') === 'nl');
$t('...USD odeme kutusu dolu', (int)($p['new']['pay_lines'] ?? 0) >= 2);
$t('...alicinin dili okunuyor', $p['lang'] === 'it');
$why = fn(string $r) => implode(' | ', vestra_invoice_reissue_plan($r, 'USD')['errors']);
$t('DEKONTLU sipariste DURUR',            str_contains($why('VES-RCPT'), 'DEKONT'));
$t('ODENMIS sipariste DURUR',             str_contains($why('VES-PAID'), 'paid') || str_contains($why('VES-PAID'), 'GELMIS'));
$t('SATICI kestiyse DURUR',               str_contains($why('VES-SELL'), 'platform degil'));
$t('KUR DAMGASI yoksa DURUR',             str_contains($why('VES-NOFX'), 'kur damgasi'));
$t('belge ZATEN USD ise DURUR',           str_contains($why('VES-USD'), 'zaten USD'));
$t('ESCROW sipariste DURUR',              str_contains($why('VES-ESC'), 'escrow'));
$t('olmayan ref DURUR',                   !vestra_invoice_reissue_plan('VES-NONE', 'USD')['ok']);
$t('desteklenmeyen birim DURUR',          !vestra_invoice_reissue_plan('VES-OK1', 'GBP')['ok']);
$po = vestra_invoice_reissue_plan('OTEST1', 'USD');
$t('kabul edilmis TEKLIF GECER', $po['ok'] === true && $po['kind'] === 'offer');
$t('...teklif USD tutari (300+20 EUR -> 368.00)', abs((float)($po['new']['total'] ?? 0) - 368.00) < 0.011);
$t('...teklifin NL profili de kaldirilacak', ($po['new']['bank_clear'] ?? '') === 'nl');
$t('...bosluklu satir ref\'i GORULUYOR', ($po['ref_dirty'] ?? '') === '"OTEST1 "');

echo "\n== 2. hep ya da hic ==\n";
$i0 = $snapInv(); $s0 = $snapSt();
$r = vestra_invoice_reissue_apply(['VES-OK1', 'VES-RCPT'], 'USD');
$t('uygunsuz ref varken uygulama REDDEDILIR', $r['ok'] === false && str_contains($r['error'], 'VES-RCPT'));
$t('...HICBIR belge dosyasi degismedi', $snapInv() === $i0);
$t('...kayit dosyasi BAYT BAYT ayni', $snapSt() === $s0);

echo "\n== 3. uygulama (siparis) ==\n";
$was = $oldNo('VES-OK1'); $ctrl = $oldNo('VES-CTRL');
$r = vestra_invoice_reissue_apply(['VES-OK1', 'OTEST1'], 'USD');
$t('uygulama GECTI', $r['ok'] === true);
$new = vestra_invoices_for_ref('VES-OK1');
$t('TEK belge, YENI numara, USD', count($new) === 1 && $new[0]['no'] !== $was && strtoupper($new[0]['currency']) === 'USD');
$t('...meta toplami USD tutari', abs((float)$new[0]['total'] - 2329.00) < 0.011);
$arch = glob($sand.'/data/invoices/deleted/*VES-OK1__vestra.json') ?: [];
$t('eski belge ARSIVDE (silinmedi)', count($arch) === 1 && (json_decode((string)file_get_contents($arch[0]), true)['no'] ?? '') === $was);
$st = vestra_read_json('order_statuses.json')['VES-OK1'];
$t('ODEME SAATI sifirlandi', !isset($st['payment_grace_start']) && !isset($st['payment_reminder_sent_at']));
$t('...faz unstamped (cron yeni hatirlatmayi gonderip saati baslatir)', vestra_order_payment_grace($st, time(), 'VES-OK1')['phase'] === 'unstamped');
$rep = end($st['invoice_replaced']);
$t('eski numara + tutar + saat izi kayitta', ($rep['no'] ?? '') === $was && ($rep['currency'] ?? '') === 'EUR' && abs((float)$rep['total'] - 2025.0) < 0.011
    && ($rep['clock']['payment_grace_start'] ?? '') === $grace['payment_grace_start']);
$t('banka profili kaldirildi, birim USD', !isset($st['invoice_bank']) && ($st['invoice_currency'] ?? '') === 'USD');
$txt = preg_replace('/\s+/u', '', vestra_pdf_drawn_text((string)file_get_contents(vestra_invoice_file('VES-OK1', 'vestra'))));
$t('BELGE ABD hesabini basiyor (hesap no)', str_contains($txt, '123456789012'));
$t('...EUR IBAN\'larini BASMIYOR', !str_contains($txt, 'NL91') && !str_contains($txt, 'DE89'));
$t('...USD tutari belgede', str_contains($txt, '2,329.00'));
$t('KONTROL GRUBU: listede olmayan siparisin belgesi ayni', $oldNo('VES-CTRL') === $ctrl
    && isset(vestra_read_json('order_statuses.json')['VES-CTRL']['payment_grace_start']));

echo "\n== 4. teklif yolu ==\n";
$on = vestra_invoices_for_ref('OTEST1');
$t('teklif: TEK belge, USD, yeni numara', count($on) === 1 && strtoupper($on[0]['currency']) === 'USD' && $on[0]['no'] === ($r['done']['OTEST1']['no'] ?? '-'));
$rs = vestra_read_json('offer_responses.json')['OTEST1'];
$t('...teklif kaydinda birim USD, NL profili yok', ($rs['invoice_currency'] ?? '') === 'USD' && !isset($rs['invoice_bank']));
$t('...navlun kayittan (20) korundu', (float)($rs['invoice_shipping'] ?? -1) === 20.0);
$n = 0; foreach (vestra_read_csv('orders.csv') as $o) if (($o['ref'] ?? '') === 'OTEST1') $n++;
$m = 0; foreach (vestra_read_csv('orders.csv') as $o) if (trim((string)($o['ref'] ?? '')) === 'OTEST1') $m++;
$t('...siparis satiri TEK kaldi ve ref\'i TEMIZ (ikinci kopya yok)', $n === 1 && $m === 1);
$t('...orders.csv yedegi alindi', count(glob($sand.'/data/orders.csv.bak-reissue-*') ?: []) === 1);
$t('...teklifin saati de sifirlandi', !isset(vestra_read_json('order_statuses.json')['OTEST1']['payment_grace_start']));

echo "\n== 5. ikinci kosu ==\n";
$i1 = $snapInv();
$r2 = vestra_invoice_reissue_apply(['VES-OK1'], 'USD');
$t('belge zaten USD: REDDEDILIR, yeni numara YAKILMAZ', $r2['ok'] === false && $snapInv() === $i1);

echo "\n== 6. mektup (yerine gecen belge) ==\n";
require_once $root.'/inc/notify.php';
require_once $root.'/inc/email_templates.php';
$rp = ['no' => 'INV-2026-1023', 'currency' => 'EUR', 'total' => 1925.25];
[$s1, $b1] = vestra_tpl_order_invoice_pdf('Buyer', 'VES-OK1', 'INV-2026-1027', 2210.05, 'USD', false, true, 'Marco Bellini', '', 'en', false, $rp);
$t('EN: konu eski numarayi soyluyor', str_contains($s1, 'replaces INV-2026-1023'));
$t('EN: govde iptali, birimi ve odenmisse yolunu yaziyor', str_contains($b1, 'which has been cancelled') && str_contains($b1, 'US dollars') && str_contains($b1, 'already paid invoice INV-2026-1023'));
$t('EN: eski tutar KAYITTAN (EUR 1,925.25)', str_contains($b1, 'EUR 1,925.25'));
[$s2, $b2] = vestra_tpl_order_invoice_pdf('Buyer', 'VES-OK1', 'INV-2026-1027', 2210.05, 'USD', false, true, 'Marco Bellini', '', 'fr', false, $rp);
$t('FR: konu ve govde', str_contains($s2, 'remplace INV-2026-1023') && str_contains($b2, 'qui est annulée') && str_contains($b2, '1 925,25 €'));
[$s3, $b3] = vestra_tpl_order_invoice_pdf('Buyer', 'VES-OK1', 'INV-2026-1027', 2210.05, 'USD', false, true, 'Marco Bellini', '', 'en', false);
$t('yerine gecen belge YOKSA cumle de yok (eski mektup aynen)', !str_contains($s3.$b3, 'replaces') && !str_contains($b3, 'cancelled'));

echo "\nSONUC: {$ok} ok, {$fail} hata\n";
exit($fail ? 1 : 0);
