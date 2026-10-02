<?php
/*
 * SIPARIS FATURASINDAN ONCE IKI KARAR: kargo girildi mi, banka secildi mi
 * (operator, 2 Eki 2026: "banka bilgilerini ve siparislerden once bunlarin
 * secilmesi kargo fiyati girilmesi onemli").
 *
 * Olculen:
 *   §1 secim sayaci: platformun bir birimde kac hesabi var
 *   §2 karar fonksiyonu (vestra_order_issue_prereqs), IKI YON: eksikken soyler,
 *      karar verilince susar; satici kesiminde ve tek hesapli birimde banka SORMAZ
 *   §3 GERCEK kesim: eksikken NUMARA YANMAZ, karar verilince kesilir, redraft muaf,
 *      panelin cagirdigi govde error_code'u yukari tasir
 *   §4 kablolama (panel + is akisi)
 *   §5 CIZIM: admin.php kum havuzunda -- cipler, kapali dugme, kuyruktaki kargo kutusu
 *
 * Kum havuzu: VESTRA_DATA_DIR gecici dizin. IBAN'lar SENTETIK
 * (tests/no_real_iban_test.php izin listesi).
 */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-prereq-'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/inc/products.php';
require_once $root.'/inc/auth.php';
require_once $root.'/inc/invoice.php';
require_once $root.'/inc/orders.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }

$DE_IBAN = 'DE89370400440532013000';
$NL_IBAN = 'NL91ABNA0417164300';
$SELLER  = 'aaaabbbbccccdddd';
file_put_contents($sand.'/data/accounts.json', json_encode([[
    'id' => $SELLER, 'type' => 'seller', 'status' => 'active', 'company' => 'Seller Test SAS',
    'email' => 's@example.com', 'bank_holder' => 'Seller Test SAS', 'bank_iban' => 'FR1420041010050500013M02606',
]]));

/* Duz kunye: ABD hesabi + Alman SEPA (canlidaki sekil, sentetik rakamlarla). */
vestra_platform_seller_save([
    'bank_holder' => 'Acerasoft LLC', 'bank_name' => 'US Test Bank', 'bank_bic' => 'CHASUS33',
    'bank_account' => '123456789012', 'bank_routing' => '021000021',
    'bank_iban' => $DE_IBAN, 'bank_eur_bic' => 'COBADEFF', 'bank_eur_name' => 'Test Bank DE',
]);

echo "== 1. secim sayaci ==\n";
$t('profil yokken EUR: 1 hesap (duz kunye)', vestra_platform_bank_choices('EUR') === 1);
$t('profil yokken USD: 1 hesap',             vestra_platform_bank_choices('usd') === 1);
vestra_platform_bank_save('nl', ['label'=>'Airwallex NL (EUR)','currency'=>'EUR','bank_iban'=>$NL_IBAN,'bank_bic'=>'AINHNL22','bank_name'=>'Airwallex NL']);
$t('NL profiliyle EUR: 2 hesap',             vestra_platform_bank_choices('EUR') === 2);
$t('NL profili USD sayisini DEGISTIRMEZ',    vestra_platform_bank_choices('USD') === 1);

echo "\n== 2. karar fonksiyonu ==\n";
$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$h = fopen($sand.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
$row = function (string $ref, string $ship) {
    return [date('c'), $ref, 'Buyer Co', '', 'Buyer', 'b@example.com', 'Belgium', '',
            '20x VS-GD-T04 @59.90', '1198.00', '0', '1198.00', number_format(1198 + (float)$ship, 2, '.', ''),
            'Payment: Bank transfer.', 'yes', '2026-06-26', '', '0', $ship, ''];
};
foreach (['VES-PRE1' => '0.00', 'VES-PRE2' => '20.00', 'VES-PRE3' => '0.00', 'VES-PRE4' => '0.00', 'VES-PRE5' => '20.00'] as $r => $s) fputcsv($h, $row($r, $s), ',', '"', '\\');
fclose($h);
$st = ['VES-PRE1' => ['invoice_seller_uid' => 'vestra'], 'VES-PRE2' => ['invoice_seller_uid' => 'vestra'],
       'VES-PRE3' => ['invoice_seller_uid' => $SELLER],   'VES-PRE4' => ['invoice_seller_uid' => 'vestra'],
       /* USD belge: platformun USD'de TEK hesabi var (NL profili EUR) */
       'VES-PRE5' => ['invoice_seller_uid' => 'vestra', 'invoice_currency' => 'USD',
                      'fx' => ['usd' => 1.1622, 'date' => '2026-09-04', 'source' => 'ECB']]];
vestra_write_json('order_statuses.json', $st);

$m1 = vestra_order_issue_prereqs('VES-PRE1');
$t('kararsiz platform siparisi: KARGO eksik', isset($m1['shipping']));
$t('kararsiz platform siparisi: BANKA eksik (EUR\'da 2 hesap)', isset($m1['bank']));
$t('sebep insan diliyle (kargo)', stripos((string)($m1['shipping'] ?? ''), 'kargo') !== false);
$t('sebep insan diliyle (banka, birimi soyluyor)', str_contains((string)($m1['bank'] ?? ''), 'EUR'));
$m2 = vestra_order_issue_prereqs('VES-PRE2');
$t('navlun > 0 = girilmis sayilir', !isset($m2['shipping']));
$t('...ama banka yine soruluyor', isset($m2['bank']));
$m3 = vestra_order_issue_prereqs('VES-PRE3');
$t('SATICI kesiminde banka SORULMAZ (kendi IBAN\'i)', !isset($m3['bank']));
$t('...kargo yine soruluyor', isset($m3['shipping']));
$t('bilinmeyen siparis: bos (sormaz, kesim zaten durur)', vestra_order_issue_prereqs('VES-NONE') === []);
$p5 = vestra_order_invoice_payloads('VES-PRE5');
$t('(kontrol: PRE5 belgesi gercekten USD)', strtoupper((string)($p5[0]['meta']['currency'] ?? '')) === 'USD' && empty($p5[0]['currency_error']));
$t('TEK hesapli birimde (USD) banka SORULMAZ', vestra_order_issue_prereqs('VES-PRE5') === []);

/* Kararlar panelin/is akisinin yazicilariyla veriliyor. */
$rs = vestra_order_set_shipping('VES-PRE1', 0.0);
$t('0 KAYDETMEK de karar: kargo artik eksik degil', empty($rs['error']) && !isset(vestra_order_issue_prereqs('VES-PRE1')['shipping']));
$t('...banka hala eksik', isset(vestra_order_issue_prereqs('VES-PRE1')['bank']));
$t('varsayilan hesabi SECMEK yaziliyor', vestra_order_set_invoice_bank('VES-PRE1', ''));
$st1 = vestra_read_json('order_statuses.json')['VES-PRE1'] ?? [];
$t('...karar damgasi duser, profil anahtari YOK', !empty($st1['invoice_bank_at']) && !isset($st1['invoice_bank']) && vestra_order_invoice_bank('VES-PRE1') === '');
$t('iki karar verildi: HAZIR', vestra_order_issue_prereqs('VES-PRE1') === []);
vestra_order_set_invoice_bank('VES-PRE2', 'nl');
$t('profil secmek de karar: HAZIR', vestra_order_issue_prereqs('VES-PRE2') === [] && vestra_order_invoice_bank('VES-PRE2') === 'nl');

echo "\n== 3. GERCEK kesim ==\n";
$invDir = vestra_invoice_dir();
$pdfs = fn() => count(glob($invDir.'/*.pdf') ?: []);
$n0 = $pdfs();
$r4 = vestra_issue_order_invoices('VES-PRE4');
$t('kararsiz sipariste kesim DURDU', !empty($r4['error']) && ($r4['error_code'] ?? '') === 'prereq');
$t('...eksikler listede (kargo + banka)', ($r4['missing'] ?? []) === ['shipping', 'bank']);
$t('...HICBIR belge yazilmadi (numara yanmadi)', $pdfs() === $n0 && !vestra_invoices_for_ref('VES-PRE4'));
$r4b = vestra_order_invoice_issue('VES-PRE4', false);
$t('panelin govdesi error_code\'u YUKARI tasiyor', ($r4b['error_code'] ?? '') === 'prereq' && ($r4b['missing'] ?? []) === ['shipping', 'bank']);
$i1 = vestra_issue_order_invoices('VES-PRE1');
$t('kararlari verilmis sipariste kesim GECTI', is_array($i1) && empty($i1['error']) && !empty($i1[0]['no']));
$t('...belge yazildi', $pdfs() === $n0 + 1);
$i2 = vestra_issue_order_invoices('VES-PRE2');
$pdf2 = (string)@file_get_contents($i2[0]['path'] ?? '');
$t('profil secilen sipariste belge NL hesabini basiyor', str_contains($pdf2, 'IBAN: '.vestra_iban_pretty($NL_IBAN)) && !str_contains($pdf2, vestra_iban_pretty($DE_IBAN)));
/* REDRAFT MUAF: belge zaten kesilmis; kararlarin kaydi sonradan silinse bile
   ayni numarayla yeniden cizim engellenmemeli (KURAL 5f'in yolu). */
$no1 = (string)$i1[0]['no'];
$s = vestra_read_json('order_statuses.json');
unset($s['VES-PRE1']['shipping_set_at'], $s['VES-PRE1']['invoice_bank_at']);
vestra_write_json('order_statuses.json', $s);
$t('(kontrol: kararlar silindi -> fonksiyon yine eksik diyor)', count(vestra_order_issue_prereqs('VES-PRE1')) === 2);
$rd = vestra_issue_order_invoices('VES-PRE1', true);
$t('redraft kapidan MUAF: ayni numarayla yeniden cizildi', is_array($rd) && empty($rd['error']) && (string)($rd[0]['no'] ?? '') === $no1);

echo "\n== 4. kablolama ==\n";
$inv = (string)file_get_contents($root.'/inc/invoice.php');
$t('kesim yolu kapiyi soruyor (redraft disinda, kutu kontrolunden ONCE)',
   preg_match('/function vestra_issue_order_invoices\(.*?if \(!\$redraft\) \{\s*\$miss = vestra_order_issue_prereqs\(\$ref, \$payloads\);.*?vestra_invoice_payment_gap\(/s', $inv) === 1);
$a = (string)file_get_contents($root.'/admin.php');
$t('panel: prereq kodu kendi bandina gidiyor', str_contains($a, "'prereq'=>'invoice_prereq'") && str_contains($a, "\$msg==='invoice_prereq'"));
$t('panel: bant NUMARA YAKILMADIGINI yaziyor', preg_match("/\\\$msg==='invoice_prereq'.*?Hiçbir numara yakılmadı/s", $a) === 1);
$t('panel: kuyruktan kargo girilince ETIKET korunuyor', preg_match("/\\\$act==='order_shipping'.*?array_key_exists\('shipping_label', \\\$_POST\)/s", $a) === 1);
$t('panel: kuyruktan kargo girilince KUYRUGA donuyor', preg_match("/\\\$act==='order_shipping'.*?'from'\]\?\?''\)==='invoices'/s", $a) === 1);
$sp = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$t('is akisi issue: etiket sebebe gore (para birimi diye yalan yok)', str_contains($sp, "'prereq' => 'ONCE KARGO/BANKA KARARI'"));
$t('is akisi bank: "default" bilincli secim', str_contains($sp, "\$want === 'default'"));
$t('is akisi bank + shipping: kesime hazir mi satiri', substr_count($sp, 'kesime hazir mi') >= 2);

echo "\n== 5. CIZIM: admin.php kum havuzunda ==\n";
$sb = sys_get_temp_dir().'/vestra_prereqrender_'.getmypid();
@mkdir($sb, 0777, true);
exec('cp -r '.escapeshellarg($root).' '.escapeshellarg($sb.'/vestra').' 2>&1', $o, $rc);
$t('kum havuzu kuruldu', $rc === 0);
exec('rm -rf '.escapeshellarg($sb.'/vestra/data'));
@mkdir($sb.'/vestra/data', 0777, true);
file_put_contents($sb.'/vestra/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false];\n");
file_put_contents($sb.'/vestra/data/listings.json', json_encode([[
  'id'=>'gd-t04','brand'=>'Gallery Dept.','name'=>'Logo Chest Print T-Shirt — Navy/Red','sku'=>'VS-GD-T04',
  'cat'=>'T-Shirts','status'=>'approved','mode'=>'fixed','moq'=>20,'list'=>59.90,'tiers'=>[['min'=>20,'price'=>59.90]],
]], JSON_UNESCAPED_UNICODE));
file_put_contents($sb.'/vestra/data/accounts.json', json_encode([]));
$h = fopen($sb.'/vestra/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
fputcsv($h, $row('VES-RNDA', '0.00'), ',', '"', '\\');
fputcsv($h, $row('VES-RNDB', '20.00'), ',', '"', '\\');
fclose($h);
$lblRow = $row('VES-RNDB', '20.00');
file_put_contents($sb.'/vestra/data/platform_seller.json', json_encode([
  'bank_holder'=>'Acerasoft LLC','bank_iban'=>$DE_IBAN,'bank_eur_bic'=>'COBADEFF','bank_eur_name'=>'Test Bank DE',
  'bank_account'=>'123456789012','bank_routing'=>'021000021','bank_name'=>'US Test Bank',
  'banks'=>['nl'=>['label'=>'Airwallex NL (EUR)','currency'=>'EUR','bank_iban'=>$NL_IBAN,'bank_bic'=>'AINHNL22','bank_name'=>'Airwallex NL']],
]));
file_put_contents($sb.'/vestra/data/order_statuses.json', json_encode([
  'VES-RNDA'=>['status'=>'pending','invoice_seller_uid'=>'vestra'],
  'VES-RNDB'=>['status'=>'pending','invoice_seller_uid'=>'vestra','invoice_bank'=>'nl','invoice_bank_at'=>date('c')],
]));
$render = function (string $tab, string $extra = '') use ($sb): string {
    file_put_contents($sb.'/render.php', "<?php\nerror_reporting(E_ALL); ini_set('display_errors','1');\n"
      ."session_start(); \$_SESSION['vadmin']=true;\n"
      ."\$_GET=['tab'=>'{$tab}'".($extra !== '' ? ",{$extra}" : '')."]; \$_SERVER['REQUEST_METHOD']='GET';\n"
      ."\$_SERVER['REQUEST_URI']='/admin?tab={$tab}'; \$_SERVER['REMOTE_ADDR']='127.0.0.1'; \$_SERVER['HTTP_HOST']='localhost';\n"
      ."ob_start(); include __DIR__.'/vestra/admin.php'; echo ob_get_clean();\n");
    return (string)shell_exec('cd '.escapeshellarg($sb).' && php render.php 2>&1');
};
/* Satirin KENDI blogu: sayfanin tamamini aramak baska bir satirin cipini
   (ya da yardim metnini) olcerdi -- bu deponun kayitli tuzagi. */
$block = function (string $html, string $ref): string {
    $p = strpos($html, 'value="'.$ref.'"');
    if ($p === false) return '';
    $q = strpos($html, 'value="order_delete"', $p);
    return substr($html, $p, ($q === false ? 6000 : $q - $p));
};
$hv = $render('orders', "'view'=>'VES-RNDA'");
$t('siparis dosyasi cizildi, PHP uyarisi yok', strlen($hv) > 5000 && !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b/', $hv));
$t('kararsiz siparis: iki cip', str_contains($hv, 'data-missing="shipping"') && str_contains($hv, 'data-missing="bank"'));
$t('kararsiz siparis: Approve dugmesi KAPALI', preg_match('/<button class="abtn primary" type="submit" style="font-size:12px" disabled title="[^"]*"[^>]*>✓ Approve &amp; issue invoice<\/button>/u', $hv) === 1);
$hb = $render('orders', "'view'=>'VES-RNDB'");
$t('kararli siparis: cip YOK', !str_contains($hb, 'data-missing='));
$t('kararli siparis: Approve dugmesi ACIK', preg_match('/<button class="abtn primary" type="submit" style="font-size:12px">✓ Approve &amp; issue invoice<\/button>/u', $hb) === 1);
$hi = $render('invoices');
$t('onay kuyrugu cizildi, PHP uyarisi yok', strlen($hi) > 5000 && !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b/', $hi));
$ba = $block($hi, 'VES-RNDA'); $bb = $block($hi, 'VES-RNDB');
$t('kuyruk satirinda KARGO KUTUSU (onay dugmesinin yaninda)', str_contains($ba, 'value="order_shipping"') && str_contains($ba, 'name="from" value="invoices"'));
$t('kuyruk: kararsiz satirda iki cip + kapali dugme', str_contains($ba, 'data-missing="shipping"') && str_contains($ba, 'data-missing="bank"') && preg_match('/disabled title="[^"]*"[^>]*>✓ Approve &amp; issue</u', $ba) === 1);
$t('kuyruk: kararli satirda cip YOK, dugme ACIK', $bb !== '' && !str_contains($bb, 'data-missing=') && str_contains($bb, '>✓ Approve &amp; issue</button>') && !str_contains($bb, 'disabled'));
$t('kuyruk: kayitli navlun kutuda gorunuyor', str_contains($bb, 'name="shipping" inputmode="decimal" value="20.00"'));
exec('rm -rf '.escapeshellarg($sb));

/* temizlik */
exec('rm -rf '.escapeshellarg($sand));

echo "\n{$ok} ok, {$fail} HATA\n";
exit($fail ? 1 : 0);
