<?php
/* FATURA ONAY KAPISI (22 Eki 2026, inc/invoice_gate.php).
 *
 * Operator: "bundan sonra fatura olusturmadan bana test email gitsin onay
 * istesin, musteriye gitmeden". Olculen:
 *   §1 kapi KAPALIYKEN eski davranis (kesim gecer)
 *   §2 kapi ACIKKEN kesim DURUR: numara yanmaz, belge yok, istek kaydi var,
 *      ozet dogru, 'gate' kodu panelin govdesinden yukari cikar
 *   §3 ONAY: ayni kayit -> kesilir, istek duser; kayit degistiyse DURUR
 *   §4 vazgec; redraft MUAF; invoice_reissue kendi gecisiyle MUAF
 *   §5 kablolama: teklif/birlesik yollar, panel, is akislari
 * Kum havuzu: VESTRA_DATA_DIR gecici dizin; mail yapilandirmasi yok, yani
 * vestra_send_mail false doner ve istek yine de KAYDEDILIR (mailed=false).
 * IBAN'lar SENTETIK (tests/no_real_iban_test.php izin listesi). */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-igate-'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/inc/products.php';
require_once $root.'/inc/auth.php';
require_once $root.'/inc/invoice.php';
require_once $root.'/inc/orders.php';
require_once $root.'/inc/invoice_gate.php';
require_once $root.'/inc/invoice_reissue.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }
file_put_contents($sand.'/data/accounts.json', json_encode([]));

$DE_IBAN = 'DE89370400440532013000';
vestra_platform_seller_save([
    'bank_holder' => 'Acerasoft LLC', 'bank_name' => 'US Test Bank', 'bank_bic' => 'CHASUS33',
    'bank_account' => '123456789012', 'bank_routing' => '021000021',
    'bank_iban' => $DE_IBAN, 'bank_eur_bic' => 'COBADEFF', 'bank_eur_name' => 'Test Bank DE',
]);
$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$h = fopen($sand.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
foreach (['VES-G1', 'VES-G2', 'VES-G3'] as $r) {
    fputcsv($h, [date('c'), $r, 'Buyer Co', 'IT123', 'Buyer', 'b@example.com', 'Italy', '',
                 '20x VS-GD-T04 @59.90', '1198.00', '0', '1198.00', '1218.00',
                 'Payment: Bank transfer.', 'yes', '2026-06-26', '', '0', '20.00', 'Shipping'], ',', '"', '\\');
}
fclose($h);
$st = [];
foreach (['VES-G1', 'VES-G2', 'VES-G3'] as $r) {
    $st[$r] = ['invoice_seller_uid' => 'vestra', 'shipping_set_at' => date('c'), 'invoice_bank_at' => date('c'),
               'fx' => ['usd' => 1.1234, 'date' => '2026-10-21', 'source' => 'ecb']];
}
vestra_write_json('order_statuses.json', $st);
$invDir = vestra_invoice_dir();
$pdfs = fn() => count(glob($invDir.'/*.pdf') ?: []);

echo "== 1. kapi KAPALI (dosya yok) ==\n";
$t('kapi varsayilan KAPALI', !vestra_invoice_gate_on() && !vestra_invoice_gate_blocks());
$r1 = vestra_order_invoice_issue('VES-G1', false);
$t('kapali kapida kesim GECER', !empty($r1['ok']) && !empty($r1['nos'][0]));
$n1 = $pdfs();

echo "\n== 2. kapi ACIK: kesim DURUR ==\n";
$t('kapi acildi (geri okundu)', vestra_invoice_gate_set(true, 'test') && vestra_invoice_gate_on());
$r2 = vestra_order_invoice_issue('VES-G2', true);
$t("panelin govdesi 'gate' kodu doner", ($r2['error_code'] ?? '') === 'gate' && !empty($r2['error']));
$t('numara YANMADI, belge YOK', $pdfs() === $n1 && !vestra_invoices_for_ref('VES-G2'));
$reqs = vestra_invoice_gate_requests();
$t('istek kaydedildi (1)', count($reqs) === 1);
$req = reset($reqs); $tok = (string)key($reqs);
$t('istek: tur order, ref dogru, hash var', ($req['kind'] ?? '') === 'order' && ($req['ref'] ?? '') === 'VES-G2' && strlen((string)($req['hash'] ?? '')) === 64);
$s = (array)($req['summary'] ?? []);
$t('ozet: alici, kesen, birim, toplam (1198 + 20 kargo), odeme kutusu dolu',
   ($s['buyer'] ?? '') === 'Buyer Co' && str_contains((string)($s['issuer'] ?? ''), 'Acerasoft') && ($s['cur'] ?? '') === 'EUR'
   && abs((float)($s['grand'] ?? 0) - 1218.0) < 0.005 && (int)($s['pay_lines'] ?? 0) > 0);
$t('mail yapilandirmasi yok: mailed=false ama istek yine kayitli', empty($req['mailed']) && empty($r2['mailed']) && !empty($r2['saved']));
$t('bulma: ref ile', (vestra_invoice_gate_find('VES-G2')['token'] ?? '') === $tok);
$t('dogrudan vestra_issue_order_invoices de durur', (vestra_issue_order_invoices('VES-G2')['error_code'] ?? '') === 'gate');
$t('ikinci istek eskisinin YERINE gecer (tek kayit)', count(vestra_invoice_gate_requests()) === 1 && (vestra_invoice_gate_find('VES-G2')['token'] ?? '') !== $tok);
$tok = (string)vestra_invoice_gate_find('VES-G2')['token'];

echo "\n== 3. ONAY ==\n";
$bad = vestra_invoice_gate_approve('deadbeef');
$t('bilinmeyen token: durur', ($bad['error_code'] ?? '') === 'gate_missing');
$a2 = vestra_invoice_gate_approve($tok, 'test');
$t('onay: kesildi, numara var, approved bayragi', !empty($a2['ok']) && !empty($a2['nos'][0]) && !empty($a2['approved']));
$t('...belge yazildi, kayitta', $pdfs() === $n1 + 1 && count(vestra_invoices_for_ref('VES-G2')) === 1);
$t('...istek dustu', vestra_invoice_gate_requests() === []);
$t('...gecis bayragi kapandi', !vestra_invoice_gate_pass() && vestra_invoice_gate_blocks());
$t('...ayni siparise ikinci kesim "zaten kesilmis" der (kapiya takilmaz)', str_contains((string)(vestra_order_invoice_issue('VES-G2')['error'] ?? ''), 'zaten'));

/* kayit taslaktan SONRA degisirse onay DURUR */
$r3 = vestra_order_invoice_issue('VES-G3');
$tok3 = (string)vestra_invoice_gate_find('VES-G3')['token'];
$t('G3 taslagi bekliyor', ($r3['error_code'] ?? '') === 'gate' && $tok3 !== '');
vestra_order_set_shipping('VES-G3', 35.0);
$a3 = vestra_invoice_gate_approve($tok3);
$t('kargo degisti -> onay DURUR (gate_changed), belge yok', ($a3['error_code'] ?? '') === 'gate_changed' && !vestra_invoices_for_ref('VES-G3') && $pdfs() === $n1 + 1);
$t('...istek duruyor (operator yeniden taslak ister)', vestra_invoice_gate_find('VES-G3') !== null);
$r3b = vestra_order_invoice_issue('VES-G3');
$tok3b = (string)vestra_invoice_gate_find('VES-G3')['token'];
$t('yeni taslak: yeni token, yeni toplam (1233)', $tok3b !== $tok3 && abs((float)(vestra_invoice_gate_find('VES-G3')['summary']['grand'] ?? 0) - 1233.0) < 0.005);
$a3b = vestra_invoice_gate_approve($tok3b);
$t('yeni taslak onaylandi: kesildi', !empty($a3b['ok']) && count(vestra_invoices_for_ref('VES-G3')) === 1);

echo "\n== 4. vazgec, redraft, reissue ==\n";
$r1b = vestra_issue_order_invoices('VES-G1', true);
$t('REDRAFT kapidan MUAF (ayni numara)', is_array($r1b) && empty($r1b['error']) && (string)($r1b[0]['no'] ?? '') === (string)$r1['nos'][0]);
/* vazgec: yeni bir siparis gerekmiyor -- G1'in belgesini silip tekrar istek uretelim */
$del = vestra_invoice_delete('VES-G1', 'vestra');
$t('(kontrol: G1 belgesi arsivlendi)', !empty($del['ok']) && !vestra_invoices_for_ref('VES-G1'));
$rq = vestra_order_invoice_issue('VES-G1');
$tk = (string)(vestra_invoice_gate_find('VES-G1')['token'] ?? '');
$t('G1 yeniden taslakta', ($rq['error_code'] ?? '') === 'gate' && $tk !== '');
$t('vazgec: istek duser, belge yok', vestra_invoice_gate_discard($tk) && vestra_invoice_gate_find('VES-G1') === null && !vestra_invoices_for_ref('VES-G1'));
$t('vazgec ikinci kez: false', !vestra_invoice_gate_discard($tk));
/* reissue: G2 EUR -> USD, kapi ACIKKEN gecmeli (operator kuru kosu + apply ile onayladi) */
$ri = vestra_invoice_reissue_apply(['VES-G2'], 'USD');
$t('invoice_reissue kapi ACIKKEN gecer (kendi gecisi)', empty($ri['error']) && !empty($ri['ok']) && strtoupper((string)(vestra_invoices_for_ref('VES-G2')[0]['currency'] ?? '')) === 'USD');
$t('...gecis bayragi yine kapali', !vestra_invoice_gate_pass());
$t('kapi kapatildi (geri okundu)', vestra_invoice_gate_set(false) && !vestra_invoice_gate_on());

echo "\n== 5. kablolama ==\n";
$of = (string)file_get_contents($root.'/inc/offers.php');
$t('teklif kesimi kapiyi soruyor (force dalinda, kutu kontrolunden SONRA)',
   preg_match('/function vestra_offer_issue_invoice\(.*?if \(\$gap !== \'\'\) return.*?vestra_invoice_gate_blocks\(\)\) return vestra_invoice_gate_request\(\'offer\'/s', $of) === 1);
$t('birlesik kesim kapiyi soruyor (KAYITTAN ONCE)',
   preg_match('/function vestra_offers_combined_invoice_issue\(.*?vestra_invoice_gate_request\(\'combined\'.*?\$primary = \(string\)\$p\[\'meta\'\]\[\'ref\'\];/s', $of) === 1);
$t('teklif sonrasi adim tek fonksiyonda', str_contains($of, 'function vestra_offer_invoice_after_issue('));
$a = (string)file_get_contents($root.'/admin.php');
$t('panel: onay/vazgec/ac-kapat eylemleri', str_contains($a, "\$act==='invoice_gate_approve'") && str_contains($a, "\$act==='invoice_gate_toggle'"));
$t('panel: gate kodu kendi bandina (3 kesim yolu)', substr_count($a, "'gate'=>'invoice_gate_sent'") === 1 && substr_count($a, "msg=invoice_gate_sent&err=") === 2);
$t('panel: teklif kesimi after_issue helper ile', str_contains($a, 'if($issued) vestra_offer_invoice_after_issue($ref, $iv);'));
$t('panel: bant NUMARA YANMADIGINI yaziyor', preg_match("/\\\$msg==='invoice_gate_sent'.*?numara yanmadı/s", $a) === 1);
$t('panel: Invoices sekmesi bekleyenleri listeliyor', str_contains($a, 'vestra_invoice_gate_requests()') && str_contains($a, 'invoice_gate_approve'));
$sp = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$t('is akisi issue: gate = ONAY BEKLIYOR, exit 0', str_contains($sp, "=== 'gate') {") && str_contains($sp, 'ONAY BEKLIYOR -- fatura KESILMEDI'));
$t('is akisi: admin_mode=invoice_gate', str_contains($sp, "admin_mode == 'invoice_gate'") && str_contains($sp, "approve|discard"));
$ri2 = (string)file_get_contents($root.'/inc/invoice_reissue.php');
$t('reissue gecisi try/finally ile kapaniyor', preg_match('/vestra_invoice_gate_pass\(true\);\s*try \{.*?\} finally \{\s*vestra_invoice_gate_pass\(false\);/s', $ri2) === 1);

echo "\n{$ok} ok, {$fail} hata\n";
exit($fail ? 1 : 0);
