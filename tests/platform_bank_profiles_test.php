<?php
/* PLATFORMUN ADLI BANKA PROFILLERI + SIPARIS BASINA SECIM (2 Eki 2026).
 *
 * Operator (VES-2DDC94D9): "Hollanda bankasi ile olustur, banka secimi ...
 * secilebilmeli adminden". Platform kunyesi TEK duz kayitti (bir EUR + bir USD
 * rayi); ikinci bir EUR hesabi ancak Alman IBAN'inin USTUNE yazilarak
 * girebilirdi. Simdi: `platform_seller.json['banks'][<key>]` profilleri,
 * siparis/teklif kaydinda `invoice_bank` secimi, cizici/taslak/kesim
 * muhafazasi hepsi `vestra_order_invoice_payloads()`'in 'vestra' dilimine
 * bindirdigi AYNI kunyeyi okuyor.
 *
 * IKI YON: profil secilince BELGE o hesabi basar; secilmeyince DUZ KUNYE aynen
 * basar (tek yon yazilsaydi "her belgeye profili basan" bir kusur yesil kalirdi).
 * Kum havuzu: VESTRA_DATA_DIR gecici dizin -- gercek data/'ya dokunmaz.
 * IBAN'lar SENTETIK (her IBAN belgesinde ornek olarak gecen numaralar;
 * tests/no_real_iban_test.php izin listesi).
 */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-pbprof-'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/inc/products.php';
require_once $root.'/inc/auth.php';
require_once $root.'/inc/invoice.php';
require_once $root.'/inc/orders.php';
require_once $root.'/inc/offers.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }
file_put_contents($sand.'/data/accounts.json', json_encode([]));

/* Duz kunye: ABD hesabi + Alman SEPA (canlidaki sekil, sentetik rakamlarla). */
$DE_IBAN = 'DE89370400440532013000';
$NL_IBAN = 'NL91ABNA0417164300';
$r = vestra_platform_seller_save([
    'bank_holder' => 'Acerasoft LLC', 'bank_name' => 'US Test Bank', 'bank_bic' => 'CHASUS33',
    'bank_account' => '123456789012', 'bank_routing' => '021000021',
    'bank_iban' => $DE_IBAN, 'bank_eur_bic' => 'COBADEFF', 'bank_eur_name' => 'Test Bank DE', 'bank_eur_address' => 'Berlin, Germany',
]);
$t('duz kunye yazildi', !empty($r['ok']));

echo "== 1. profil kaydi: hep ya da hic ==\n";
$t('bos label reddedilir',       (vestra_platform_bank_save('nl', ['currency'=>'EUR','bank_iban'=>$NL_IBAN])['error'] ?? '') === 'label_missing');
$t('gecersiz anahtar reddedilir',(vestra_platform_bank_save('Bad Key', ['label'=>'x','currency'=>'EUR','bank_iban'=>$NL_IBAN])['error'] ?? '') === 'key_bad');
$t('"default" anahtari yasak',   (vestra_platform_bank_save('default', ['label'=>'x','currency'=>'EUR','bank_iban'=>$NL_IBAN])['error'] ?? '') === 'key_bad');
$t('bilinmeyen birim reddedilir',(vestra_platform_bank_save('nl', ['label'=>'x','currency'=>'GBP','bank_iban'=>$NL_IBAN])['error'] ?? '') === 'currency_bad');
$t('gecersiz IBAN -> hicbir alan yazilmaz',
   (vestra_platform_bank_save('nl', ['label'=>'Airwallex NL','currency'=>'EUR','bank_iban'=>'NL00ABNA0417164300','bank_name'=>'X'])['error'] ?? '') === 'iban_bad'
   && vestra_platform_banks() === []);
$t('EUR profili IBAN\'siz reddedilir', (vestra_platform_bank_save('nl', ['label'=>'x','currency'=>'EUR','bank_name'=>'X'])['error'] ?? '') === 'iban_missing');
$t('USD profili ABA\'siz reddedilir',  (vestra_platform_bank_save('us2', ['label'=>'x','currency'=>'USD','bank_account'=>'999'])['error'] ?? '') === 'us_rails_missing');

$r = vestra_platform_bank_save('NL', ['label'=>'Airwallex NL (EUR)','currency'=>'eur','bank_iban'=>' nl91 abna 0417 1643 00 ','bank_bic'=>' ainh nl22 ','bank_name'=>'Airwallex (Netherlands) B.V.','bank_address'=>'Netherlands (SEPA)']);
$t('gecerli profil yazildi (anahtar kucuk harfe)', !empty($r['ok']) && $r['key'] === 'nl');
$B = vestra_platform_banks();
$t('profil geri okundu',          isset($B['nl']) && $B['nl']['label'] === 'Airwallex NL (EUR)' && $B['nl']['currency'] === 'EUR');
$t('IBAN normalize',              ($B['nl']['bank_iban'] ?? '') === $NL_IBAN);
$t('BIC normalize',               ($B['nl']['bank_bic'] ?? '') === 'AINHNL22');
$t('duz kunye DEGISMEDI (DE IBAN yerinde)', (vestra_platform_seller()['bank_iban'] ?? '') === $DE_IBAN);
$r = vestra_platform_bank_save('nl', ['bank_holder'=>'Acerasoft LLC']);
$t('guncelleme: bos alan mevcut degeri silmez', !empty($r['ok']) && (vestra_platform_banks()['nl']['bank_iban'] ?? '') === $NL_IBAN && (vestra_platform_banks()['nl']['bank_holder'] ?? '') === 'Acerasoft LLC');
$r = vestra_platform_bank_save('us2', ['label'=>'Second USD','currency'=>'USD','bank_account'=>'9876-5432-10','bank_routing'=>'0210 00021','bank_name'=>'Other US Bank']);
$t('USD profili yazildi', !empty($r['ok']) && (vestra_platform_banks()['us2']['bank_account'] ?? '') === '9876543210');
/* platform_seller_save profilleri EZMEZ */
vestra_platform_seller_save(['website' => 'vestrasales.com']);
$t('duz kunye kaydi profilleri KORUYOR', count(vestra_platform_banks()) === 2);

echo "\n== 2. overlay: secilen profilin rayi, duz kunyenin yerine ==\n";
$plain = vestra_platform_seller();
$nl    = vestra_platform_seller_bank('nl');
$t('bos anahtar = duz kunye',     vestra_platform_seller_bank('') === $plain);
$t('taninmayan anahtar = NULL (sessiz dusus yok)', vestra_platform_seller_bank('xx') === null);
$t('overlay id tasimiyor (hala platform)', $nl !== null && vestra_invoice_is_platform_issuer($nl) && vestra_invoice_seller_key($nl) === 'vestra');
$t('EUR rayi: NL IBAN',           ($nl['bank_iban'] ?? '') === $NL_IBAN);
$t('EUR rayi: NL BIC (DE BIC degil)', ($nl['bank_eur_bic'] ?? '') === 'AINHNL22');
$t('EUR rayi: NL banka adi',      ($nl['bank_eur_name'] ?? '') === 'Airwallex (Netherlands) B.V.');
$t('USD rayi DOKUNULMADI',        ($nl['bank_account'] ?? '') === '123456789012' && ($nl['bank_routing'] ?? '') === '021000021');
$rails = vestra_payment_rails($nl, 'EUR');
$t('EUR kutusu NL hesabini basiyor', in_array('IBAN: '.vestra_iban_pretty($NL_IBAN), $rails, true) && in_array('BIC / SWIFT: AINHNL22', $rails, true));
$t('EUR kutusunda DE banka adi YOK', !in_array('Beneficiary bank: Test Bank DE', $rails, true));
$t('EUR kutusunda DE adresi YOK (celisen cift yok)', !in_array('Bank address: Berlin, Germany', $rails, true));
/* BIC'siz EUR profili: DE BIC'i NL IBAN'inin yanina DUSMEMELI */
vestra_platform_bank_save('nl2', ['label'=>'NL no BIC','currency'=>'EUR','bank_iban'=>$NL_IBAN]);
$nl2 = vestra_platform_seller_bank('nl2');
$t('BIC\'siz profilde duz kunyenin EUR BIC\'i YOK', !isset($nl2['bank_eur_bic']) && !array_filter(vestra_payment_rails($nl2,'EUR'), fn($l)=>str_starts_with($l,'BIC')));
$us2 = vestra_platform_seller_bank('us2');
$t('USD profili: hesap/ABA ezildi', ($us2['bank_account'] ?? '') === '9876543210' && ($us2['bank_routing'] ?? '') === '021000021');
$t('USD profili: EUR rayi DOKUNULMADI', ($us2['bank_iban'] ?? '') === $DE_IBAN);
$t('USD profilinde duz kunyenin BIC\'i tasinmadi', !isset($us2['bank_bic']));
$t('uyumsuzluk: EUR profil + USD belge',  vestra_platform_bank_mismatch('nl', 'USD') !== '');
$t('uyumsuzluk: bos anahtar = sorun yok',  vestra_platform_bank_mismatch('', 'USD') === '');
$t('uyumsuzluk: uyan cift = sorun yok',    vestra_platform_bank_mismatch('nl', 'EUR') === '');
$t('uyumsuzluk: silinmis profil = sebep',  stripos(vestra_platform_bank_mismatch('gone', 'EUR'), 'no longer') !== false);

echo "\n== 3. siparis basina secim (kayit) ==\n";
$ref = 'VES-BANK1';
$t('varsayilan: secim yok',          vestra_order_invoice_bank($ref) === '');
$t('var olmayan profil YAZILMAZ',    !vestra_order_set_invoice_bank($ref, 'gone') && vestra_order_invoice_bank($ref) === '');
$t('nl kaydediliyor',                vestra_order_set_invoice_bank($ref, 'NL') && vestra_order_invoice_bank($ref) === 'nl');
$t('operator damgasi',               (vestra_read_json('order_statuses.json')[$ref]['invoice_bank_by'] ?? '') === 'operator');
$t('bos secim kaldiriyor',           vestra_order_set_invoice_bank($ref, '') && vestra_order_invoice_bank($ref) === '' && !isset(vestra_read_json('order_statuses.json')[$ref]['invoice_bank']));
$t('teklif: var olmayan profil YAZILMAZ', !vestra_offer_set_invoice_bank('OBANK1', 'gone'));
$t('teklif: nl kaydediliyor',        vestra_offer_set_invoice_bank('OBANK1', 'nl') && vestra_offer_invoice_bank('OBANK1') === 'nl');

echo "\n== 4. kum havuzunda GERCEK yuk + kesim: belge secilen hesabi basiyor mu ==\n";
$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$f = $sand.'/data/orders.csv';
$h = fopen($f, 'w'); fputcsv($h, $head, ',', '"', '\\');
foreach (['VES-BANK1','VES-BANK2','VES-BANK3'] as $rr) {
    fputcsv($h, [date('c'), $rr, 'JEDDI Test', '', 'Buyer', 'b@example.com', 'Belgium', '',
                 '20x VS-GD-T04 @59.90', '1138.10', '0', '1138.10', '1158.10',
                 'Payment: Bank transfer. Colours — VS-GD-T04: Navy/White, White/Navy.', 'yes', '2026-06-26', 'WELCOME5', '59.90', '20.00', 'Shipping'], ',', '"', '\\');
}
fclose($h);

/* BANK1: profil 'nl' secili; BANK2: secim yok (kontrol grubu); BANK3: silinmis profil */
vestra_order_set_invoice_bank('VES-BANK1', 'nl');
$st = vestra_read_json('order_statuses.json'); $st['VES-BANK3'] = ['invoice_bank' => 'gone']; vestra_write_json('order_statuses.json', $st);

$p1 = vestra_order_invoice_payloads('VES-BANK1');
$t('tek dilim (platform)',           count($p1) === 1 && $p1[0]['seller_key'] === 'vestra');
$t('dilim overlay kunyeyi tasiyor', ($p1[0]['seller']['bank_iban'] ?? '') === $NL_IBAN && ($p1[0]['seller']['bank_key'] ?? '') === 'nl');
$t('meta profil izini tasiyor',      ($p1[0]['meta']['bank'] ?? '') === 'nl' && ($p1[0]['meta']['bank_label'] ?? '') === 'Airwallex NL (EUR)');
$t('kesim muhafazasi: kutu VAR',     vestra_invoice_payment_gap($p1[0]['seller'], 'EUR', false) === '');
$p2 = vestra_order_invoice_payloads('VES-BANK2');
$t('kontrol grubu: secim yoksa seller NULL (duz kunye)', $p2[0]['seller'] === null && !isset($p2[0]['meta']['bank']));
$p3 = vestra_order_invoice_payloads('VES-BANK3');
$t('silinmis profil: bank_error, sessiz dusus YOK', !empty($p3[0]['bank_error']) && $p3[0]['seller'] === null);
$r3 = vestra_issue_order_invoices('VES-BANK3');
$t('silinmis profilde KESIM DURUR (nopay)', !empty($r3['error']) && ($r3['error_code'] ?? '') === 'nopay');
/* profil birimi != belge birimi: USD profili sec, belge EUR -> durur */
vestra_order_set_invoice_bank('VES-BANK2', 'us2');
$r2 = vestra_issue_order_invoices('VES-BANK2');
$t('USD profili EUR belgede: KESIM DURUR', !empty($r2['error']) && stripos((string)$r2['error'], 'USD') !== false);
vestra_order_set_invoice_bank('VES-BANK2', '');

$invDir = vestra_invoice_dir();
$before = glob($invDir.'/*.pdf') ?: [];
$i1 = vestra_issue_order_invoices('VES-BANK1');
$t('nl secili: kesim GECTI',         is_array($i1) && empty($i1['error']) && !empty($i1[0]['no']));
$pdf1 = (string)@file_get_contents($i1[0]['path'] ?? '');
$t('belge NL IBAN basiyor',          str_contains($pdf1, 'IBAN: '.vestra_iban_pretty($NL_IBAN)));
/* PDF dizgesinde parantez kacisli ("\(") -- adin parantezsiz parcalari araniyor. */
$t('belge NL banka adini basiyor',   str_contains($pdf1, 'Beneficiary bank: Airwallex') && str_contains($pdf1, 'Netherlands'));
$t('belgede DE IBAN YOK',            !str_contains($pdf1, vestra_iban_pretty($DE_IBAN)));
$t('belgede DE banka adi YOK',       !str_contains($pdf1, 'Test Bank DE'));
$t('belge yine platform adina (Acerasoft)', str_contains($pdf1, 'Acerasoft LLC'));
$meta1 = json_decode((string)file_get_contents(vestra_invoice_meta_file('VES-BANK1','vestra')), true);
$t('fatura meta profil anahtarini tasiyor', ($meta1['bank'] ?? '') === 'nl');
$t('dosya adi/anahtar degismedi (vestra)', ($meta1['seller_key'] ?? '') === 'vestra');
$i2 = vestra_issue_order_invoices('VES-BANK2');
$pdf2 = (string)@file_get_contents($i2[0]['path'] ?? '');
$t('kontrol grubu: secimsiz belge DE IBAN basiyor', str_contains($pdf2, 'IBAN: '.vestra_iban_pretty($DE_IBAN)));
$t('kontrol grubu: secimsiz belgede NL IBAN YOK', !str_contains($pdf2, vestra_iban_pretty($NL_IBAN)));
$t('kontrol grubu: meta bank bos',   (json_decode((string)file_get_contents(vestra_invoice_meta_file('VES-BANK2','vestra')), true)['bank'] ?? 'x') === '');

/* taslak notu uyumsuzlugu YAZIYOR */
$dn = vestra_invoice_draft_notes(['ref'=>'X','bank'=>'us2','buyer'=>['address'=>'a']], [], vestra_platform_seller_bank('us2'), 'EUR');
$t('taslak notu profil/birim uyumsuzlugunu yaziyor', (bool)array_filter($dn['notes'], fn($n)=>str_contains($n,'USD account')));

echo "\n== 5. teklif yolu: ayni profil, ayni muhafaza ==\n";
$sNl = vestra_offer_invoice_seller('OBANK1', null, '');
$t('teklif: secili profil overlay',  ($sNl['bank_iban'] ?? '') === $NL_IBAN && ($sNl['bank_key'] ?? '') === 'nl');
$t('teklif: override "" = duz kunye', (vestra_offer_invoice_seller('OBANK1', null, '', '')['bank_iban'] ?? '') === $DE_IBAN);
vestra_offer_set_invoice_bank('OBANK1', 'nl');
$rs = vestra_read_json('offer_responses.json'); $rs['OBANK2'] = ['invoice_bank' => 'gone']; vestra_write_json('offer_responses.json', $rs);
$sGone = vestra_offer_invoice_seller('OBANK2', null, '');
$t('teklif: silinmis profil bank_error tasiyor', !empty($sGone['bank_error']));
$t('teklif: satici hesabi secildiyse profil YOK SAYILIR', (vestra_offer_invoice_seller('OBANK1', ['seller_uid'=>'nobody'], '')['bank_key'] ?? '') === 'nl' /* hesap yok -> platforma duser */);
file_put_contents($sand.'/data/accounts.json', json_encode([['id'=>'sel1','type'=>'seller','company'=>'Seller One','bank_iban'=>$DE_IBAN]]));
$t('teklif: gercek satici hesabi kendi kaydi (overlay yok)', !isset(vestra_offer_invoice_seller('OBANK1', ['seller_uid'=>'sel1'], '')['bank_key']));
$src = file_get_contents($root.'/inc/offers.php');
$t('teklif kesimi bank_error\'da duruyor',    preg_match('/function vestra_offer_issue_invoice\(.*?bank_error.*?error_code.*?nopay.*?return vestra_ensure_invoice/s', $src) === 1);
$t('teklif kesimi uyumsuzlukta duruyor',      preg_match('/function vestra_offer_issue_invoice\(.*?vestra_platform_bank_mismatch\(.*?return vestra_ensure_invoice/s', $src) === 1);
$t('birlesik kesim bank_error\'da duruyor',   preg_match('/function vestra_offers_combined_invoice_issue\(.*?bank_error.*?vestra_platform_bank_mismatch\(/s', $src) === 1);
$t('birlesik yuk profil izi tasiyor',         preg_match('/function vestra_offers_combined_invoice_payload\(.*?\$meta\[\'bank\'\] = /s', $src) === 1);

echo "\n== 6. kablolama: panel + is akisi + sonda ==\n";
$adm = file_get_contents($root.'/admin.php');
$t('panel: profil yazicisi handler',        str_contains($adm, "if(\$act==='save_platform_bank')") && str_contains($adm, 'vestra_platform_bank_save((string)($_POST[\'bank_key\']'));
$t('panel: profil silme handler',           str_contains($adm, "if(\$act==='delete_platform_bank')"));
$t('panel: siparis secici handler',         str_contains($adm, "if(\$act==='order_invoice_bank')") && substr_count($adm, 'vestra_order_set_invoice_bank($ref') === 1);
$t('panel: kesilmis faturada secim REDDEDILIYOR', str_contains($adm, 'msg=invoice_bank_late') && str_contains($adm, "elseif(\$msg==='invoice_bank_late')"));
$t('panel: siparis dosyasinda secici',      substr_count($adm, 'value="order_invoice_bank"') === 2);   // dosya + onay kuyrugu
$t('panel: profil listesi + ekleme formu',  str_contains($adm, 'value="save_platform_bank"') && str_contains($adm, 'Bank profiles (selectable per order)'));
$t('panel: teklif satirinda secici',        str_contains($adm, '<select name="bank" form="<?= htmlspecialchars($fFid) ?>"'));
$t('panel: teklif kesimi profili kayda yaziyor', str_contains($adm, "\$rs[\$ref]['invoice_bank']=\$bk;"));
$t('panel: teklif taslagi O ANKI secimi tasiyor', str_contains($adm, 'vestra_offer_invoice_payload($ref, $pick, $vn, $sh, $vr, $cu, $bk)'));
$t('panel: uyumsuzluk cipi TIKLAMADAN ONCE', str_contains($adm, 'banka ≠ belge birimi — kesilemez'));
$t('panel: IBAN listede yalniz ulke+hane (numara yok)', str_contains($adm, "substr(\$__bibn,0,2).' · '.strlen(\$__bibn)") && !str_contains($adm, 'htmlspecialchars($__bibn)'));
$sp = file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$t('is akisi: bank modu var',              str_contains($sp, "admin_mode == 'bank'") && str_contains($sp, 'vestra_order_set_invoice_bank($ref, $want)'));
$t('is akisi: bank modu yazmayi GERI OKUYOR', str_contains($sp, "\$after  = \$isOffer ? vestra_offer_invoice_bank(\$ref) : vestra_order_invoice_bank(\$ref);"));
$t('is akisi: bank modu kesilmis faturada reddediyor', preg_match("/admin_mode == 'bank'.*?fatura ZATEN KESILMIS/s", $sp) === 1);
$t('is akisi: platform_bank zarfi bank_key ile profile yaziyor', str_contains($sp, "if (isset(\$in['bank_key']))") && str_contains($sp, 'vestra_platform_bank_save($bkey, $in)'));
$t('is akisi: aciklama bank modunu anlatiyor', str_contains($sp, "'bank' = faturanın ödeme kutusuna"));
$dm = file_get_contents(dirname(__DIR__).'/.github/workflows/diag-messages.yml');
$t('sonda: profilleri VAR/YOK + satir sayisiyla basiyor', str_contains($dm, 'BANKA PROFILLERI (siparis basina secilebilir)') && str_contains($dm, 'vestra_platform_seller_bank((string)$bk)'));
$t('sonda: IBAN numarasi basilmiyor', !preg_match("/echo .*\\\$bp\['bank_iban'\]/", $dm));

echo "\n== 8. CIZIM: admin.php kum havuzunda gercekten kosuyor (kaynak okumak olcum degil) ==\n";
$sb = sys_get_temp_dir().'/vestra_pbrender_'.getmypid();
@mkdir($sb, 0777, true);
$rc = 0; $o = [];
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
fputcsv($h, [date('c'), 'VES-RND1', 'JEDDI Test', '', 'Buyer', 'b@example.com', 'Belgium', '',
             '20x VS-GD-T04 @59.90', '1138.10', '0', '1138.10', '1158.10',
             'Payment: Bank transfer.', 'yes', '2026-06-26', 'WELCOME5', '59.90', '20.00', 'Shipping'], ',', '"', '\\');
fclose($h);
file_put_contents($sb.'/vestra/data/order_statuses.json', json_encode(['VES-RND1'=>['status'=>'pending','invoice_seller_uid'=>'vestra','invoice_bank'=>'nl']]));
file_put_contents($sb.'/vestra/data/offers.csv',
  "ref,at,listing,sku,qty,unit,total,company,email\n"
 ."ORND01,2026-10-02 10:00:00,gd-t04,VS-GD-T04,20,59.90,1198.00,JEDDI Test,b@example.com\n");
file_put_contents($sb.'/vestra/data/offer_responses.json', json_encode(['ORND01'=>['status'=>'accept','accepted_by'=>'operator','responded_at'=>'2026-10-02 11:00:00','counters'=>[],'invoice_bank'=>'nl']]));
file_put_contents($sb.'/vestra/data/platform_seller.json', json_encode([
  'bank_holder'=>'Acerasoft LLC','bank_iban'=>$DE_IBAN,'bank_eur_bic'=>'COBADEFF','bank_eur_name'=>'Test Bank DE',
  'bank_account'=>'123456789012','bank_routing'=>'021000021','bank_name'=>'US Test Bank',
  'banks'=>['nl'=>['label'=>'Airwallex NL (EUR)','currency'=>'EUR','bank_iban'=>$NL_IBAN,'bank_bic'=>'AINHNL22','bank_name'=>'Airwallex NL']],
]));
$render = function (string $tab, string $extra = '') use ($sb): string {
    file_put_contents($sb.'/render.php', "<?php\nerror_reporting(E_ALL); ini_set('display_errors','1');\n"
      ."session_start(); \$_SESSION['vadmin']=true;\n"
      ."\$_GET=['tab'=>'{$tab}'".($extra !== '' ? ",{$extra}" : '')."]; \$_SERVER['REQUEST_METHOD']='GET';\n"
      ."\$_SERVER['REQUEST_URI']='/admin?tab={$tab}'; \$_SERVER['REMOTE_ADDR']='127.0.0.1'; \$_SERVER['HTTP_HOST']='localhost';\n"
      ."ob_start(); include __DIR__.'/vestra/admin.php'; echo ob_get_clean();\n");
    return (string)shell_exec('cd '.escapeshellarg($sb).' && php render.php 2>&1');
};
$hv = $render('orders', "'view'=>'VES-RND1'");
$t('siparis dosyasi cizildi (>5 KB)',          strlen($hv) > 5000 && !str_contains($hv, 'name="pass"'));
$t('siparis dosyasi: PHP uyarisi yok',          !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b/', $hv));
$t('siparis dosyasi: banka secicisi ciziliyor', str_contains($hv, 'Payment account (platform):') && str_contains($hv, 'value="order_invoice_bank"'));
$t('siparis dosyasi: nl profili SECILI',        preg_match('/<option value="nl" selected>Airwallex NL \(EUR\) \(EUR\)<\/option>/', $hv) === 1);
$t('siparis dosyasi: belgede profil adi',       str_contains($hv, 'belgede: Airwallex NL (EUR)'));
$t('siparis dosyasi: profil listesi + ekleme formu', str_contains($hv, 'Bank profiles (selectable per order)') && str_contains($hv, 'value="save_platform_bank"') && str_contains($hv, '<code>nl</code>'));
$t('siparis dosyasi: profil satiri kutuyu "prints" diyor', str_contains($hv, 'prints (') );
/* Duz kunyenin IBAN'i ZATEN formun input'unda duruyor (operatore ait sayfa,
   mevcut davranis); PROFIL listesi ise yalniz ulke + hane basmali. */
$t('siparis dosyasi: profil IBAN\'i HTML\'de YOK (maske: ulke+hane)', !str_contains($hv, $NL_IBAN) && str_contains($hv, 'NL · 18 chars · mod-97 ok'));
$hi = $render('invoices');
$t('onay kuyrugu cizildi',                      strlen($hi) > 5000 && !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b/', $hi));
$t('onay kuyrugu: siparis satirinda banka secicisi', str_contains($hi, 'value="order_invoice_bank"') && str_contains($hi, '— bank: default —'));
$t('onay kuyrugu: teklif satirinda banka secicisi (ayni forma bagli)', preg_match('/<select name="bank" form="finv-ORND01"/', $hi) === 1 && preg_match('/<select name="bank" form="finv-ORND01"[^>]*>.*?<option value="nl" selected>/s', $hi) === 1);
$t('onay kuyrugu: 👁 taslak dugmesi platform diliminde', str_contains($hi, 'pv_seller=vestra'));
/* uyumsuzluk cipi: USD profili + EUR belge */
$ps = json_decode(file_get_contents($sb.'/vestra/data/platform_seller.json'), true);
$ps['banks']['us2'] = ['label'=>'Second USD','currency'=>'USD','bank_account'=>'9876543210','bank_routing'=>'021000021'];
file_put_contents($sb.'/vestra/data/platform_seller.json', json_encode($ps));
file_put_contents($sb.'/vestra/data/order_statuses.json', json_encode(['VES-RND1'=>['status'=>'pending','invoice_seller_uid'=>'vestra','invoice_bank'=>'us2']]));
$hm = $render('invoices');
$t('onay kuyrugu: profil birimi != belge birimi -> cip TIKLAMADAN ONCE', str_contains($hm, 'banka ≠ belge birimi — kesilemez'));
$hv2 = $render('orders', "'view'=>'VES-RND1'");
$t('siparis dosyasi: ayni uyumsuzluk cipi',     str_contains($hv2, 'profil birimi ≠ belge birimi — kesim durur'));
exec('rm -rf '.escapeshellarg($sb));

echo "\n== 7. silme ==\n";
$t('profil silindi',                 vestra_platform_bank_delete('nl2') && !isset(vestra_platform_banks()['nl2']));
$t('olmayan profil silinemez',       !vestra_platform_bank_delete('nl2'));
$t('silme duz kunyeye dokunmadi',    (vestra_platform_seller()['bank_iban'] ?? '') === $DE_IBAN && isset(vestra_platform_banks()['nl']));

/* temizlik */
foreach (glob($sand.'/data/*') ?: [] as $x) { if (is_dir($x)) { foreach (glob($x.'/*') ?: [] as $y) @unlink($y); @rmdir($x); } else @unlink($x); }
@rmdir($sand.'/data'); @rmdir($sand);

echo "\n{$ok} ok, {$fail} HATA\n";
exit($fail ? 1 : 0);
