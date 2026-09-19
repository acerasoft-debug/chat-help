<?php
/* ODEME KUTUSU BOSSA FATURA KESILMEZ (19 Eyl 2026).
 *
 * Neden var: 19 Eylul'de olculdu ki platformun kunyesinde `bank_iban` BOSTU
 * ve bekleyen iki EUR siparisinin ikisi de kesen taraf olarak VESTRA'yi
 * tasiyordu. Taslak (KURAL 5d) "odeme kutusu yok" diye UYARIYORDU -- ama
 * YALNIZCA taslakta: operator 👁 dugmesine hic basmadan "Approve & issue"a
 * basabiliyordu. O anda numara yanar, belge aliciya e-postalanir ve mektubun
 * kendi cumlesi "faturada gosterilen hesaba havale edin" der. Belgede o hesap
 * YOKKEN. Yanlis yere yollayan bir yonerge, hic yonergeden pahalidir cunku
 * alici onu uygulamaya calisir.
 *
 * TESTIN SEKLI: kaynak taramasi TEK BASINA yetmez -- bu depoda "alan kabul
 * ediliyor" diye yesil veren bir tarama, yazma dalinin hic olmadigi bir kusuru
 * (sold_out) aylarca gecirdi. Bu test kum havuzunda GERCEKTEN kesmeyi deniyor
 * ve diskte dosya olusup olusmadigina bakiyor.
 */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_pg_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
if (!defined('VESTRA_ACCOUNTS'))  define('VESTRA_ACCOUNTS',  $sand.'/data/accounts.json');
require_once $root.'/inc/products.php';
require_once $root.'/inc/invoice.php';

if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }

/* --------------------------------------------------------------- §1 helper */
echo "== 1. karar govdesi (vestra_invoice_payment_gap) ==\n";

$noBank   = [];                                   // platform, hicbir banka alani yok
/* SENTETIK IBAN: her IBAN belgesinde ornek olarak gecen Deutsche Bank
   numarasi (iban_valid_test de ayni sayiyi kullaniyor). GERCEK bir hesap
   numarasi buraya YAZILMAZ -- depo herkese acik (Guvenlik bolumu). Mod-97
   gecmesi sart: gecmeseydi panel hicbir alani kaydetmezdi. */
$eurOnly  = ['bank_iban' => 'DE89370400440532013000'];
$usdOnly  = ['bank_account' => '123456789012', 'bank_routing' => '091000019'];
$sellerNo = ['id' => 'abc123', 'company' => 'GARAGE LE PARIS'];

$gEur = vestra_invoice_payment_gap($noBank, 'EUR', false);
$t('platform + banka yok + EUR -> gap VAR', $gEur !== '');
$t('gap cumlesi kesen tarafi soyluyor (VESTRA)', str_contains($gEur, 'VESTRA'));
$t('gap DOGRU sayfaya yolluyor (Admin > Orders)', str_contains($gEur, 'Admin > Orders'));
$t('EUR gapinde IBAN adi geciyor', stripos($gEur, 'IBAN') !== false);
$t('EUR gapinde ABA routing GECMIYOR', stripos($gEur, 'ABA') === false);

$t('platform + IBAN + EUR -> gap YOK', vestra_invoice_payment_gap($eurOnly, 'EUR', false) === '');
$t('platform + hesap/ABA + USD -> gap YOK', vestra_invoice_payment_gap($usdOnly, 'USD', false) === '');
/* Ters yon: ayni kayit OTEKI birimde kutu vermiyor. 17 Eyl'de canli olculen
   hal tam buydu -- USD CIKAR, EUR CIKMAZ. */
$t('platform + yalniz ABD hesabi + EUR -> gap VAR', vestra_invoice_payment_gap($usdOnly, 'EUR', false) !== '');
$t('platform + yalniz IBAN + USD -> gap VAR', vestra_invoice_payment_gap($eurOnly, 'USD', false) !== '');

$gUsd = vestra_invoice_payment_gap($noBank, 'USD', false);
$t('USD gapinde ABA routing geciyor', stripos($gUsd, 'ABA') !== false);

/* ODENMIS siparis MUAF: escrow faturasinda kutu zaten bilerek cizilmiyor. */
$t('odenmis sipariste gap YOK (escrow muafiyeti)', vestra_invoice_payment_gap($noBank, 'EUR', true) === '');

/* Satici hesabi: BASKA sayfaya yollamali. `$sellerAcc === null` diye sormak
   teklif faturasinda hep FALSE donuyordu (KURAL 5j). */
$gSel = vestra_invoice_payment_gap($sellerNo, 'EUR', false);
$t('satici hesabi + banka yok -> gap VAR', $gSel !== '');
$t('satici gapi Admin > Users diyor', str_contains($gSel, 'Admin > Users'));
$t('satici gapi Admin > Orders DEMIYOR', !str_contains($gSel, 'Admin > Orders'));
$t('null hesap platform sayilir', str_contains(vestra_invoice_payment_gap(null, 'EUR', false), 'Admin > Orders'));

/* ------------------------------------------------- §2 tek karar noktasi */
echo "== 2. tek karar noktasi (ikinci kopya yok) ==\n";
$src = file_get_contents($root.'/inc/invoice.php');
preg_match('/^function vestra_invoice_draft_notes\(.*?^}/ms', $src, $mDraft);
$draftBody = $mDraft[0] ?? '';
$t('taslak notu govdesi bulundu', $draftBody !== '');
$t('taslak notu helper i cagiriyor', str_contains($draftBody, 'vestra_invoice_payment_gap('));
/* Taslak artik rails i KENDISI sormuyor: sorsaydi bir gun helper degisir,
   taslak eski cevabi yazar ve ikisi ayrisirdi. */
$t('taslak notu vestra_payment_rails i ARTIK cagirmiyor', !str_contains($draftBody, 'vestra_payment_rails('));

preg_match('/^function vestra_issue_order_invoices\(.*?^}/ms', $src, $mIss);
$issBody = $mIss[0] ?? '';
$t('siparis kesim govdesi bulundu', $issBody !== '');
$t('siparis kesimi gap i soruyor', str_contains($issBody, 'vestra_invoice_payment_gap('));
$t('siparis kesimi gap DOLUYSA donuyor', str_contains($issBody, "if (\$gap !== '') return"));
$t('siparis kesimi nopay kodu donduruyor', str_contains($issBody, "'error_code' => 'nopay'"));
/* Muhafaza kesimden ONCE olmali: sonra olsaydi numara zaten yanmis olurdu. */
$t('gap kontrolu vestra_ensure_invoice CAGRISINDAN once',
   strpos($issBody, 'vestra_invoice_payment_gap(') < strpos($issBody, 'vestra_ensure_invoice('));
/* REDRAFT MUAF: orada numara zaten yanmis ve yeniden cizim duzeltmenin yolu. */
$t('redraft muaf (!$redraft dali)', str_contains($issBody, 'if (!$redraft)'));

$osrc = file_get_contents($root.'/inc/offers.php');
preg_match('/^function vestra_offers_combined_invoice_issue\(.*?^}/ms', $osrc, $mComb);
$combBody = $mComb[0] ?? '';
$t('birlesik kesim govdesi bulundu', $combBody !== '');
/* SONUCA GORE DONMELI, yalnizca CAGIRMASI yetmez: falsifikasyonda dalin
   `if (false) return ...` haline getirilmesi bu testi YESIL biraktı -- cagri
   metinde duruyordu. "Hic dusemeyen bir iddia, iddia degildir." */
$t('birlesik kesim gap i soruyor', str_contains($combBody, 'vestra_invoice_payment_gap('));
$t('birlesik kesim gap DOLUYSA donuyor', str_contains($combBody, "if (\$gapC !== '') return"));
$t('birlesik kesim nopay kodu donduruyor', str_contains($combBody, "'error_code' => 'nopay'"));
/* KURAL 5n: once KAYIT sonra BELGE. Gap kontrolu kayit yazimindan da ONCE
   olmali, yoksa teklifler bir gruba baglanir ama faturasiz kalir. */
$t('birlesik gap kontrolu KAYIT yazimindan once',
   strpos($combBody, 'vestra_invoice_payment_gap(') < strpos($combBody, "vestra_write_json('offer_responses.json'"));

preg_match('/^function vestra_offer_issue_invoice\(.*?^}/ms', $osrc, $mSingle);
$singleBody = $mSingle[0] ?? '';
$t('tek teklif kesim govdesi bulundu', $singleBody !== '');
$t('tek teklif kesimi gap i soruyor', str_contains($singleBody, 'vestra_invoice_payment_gap('));
$t('tek teklif gap DOLUYSA donuyor', str_contains($singleBody, "if (\$gap !== '') return"));
$t('tek teklif nopay kodu donduruyor', str_contains($singleBody, "'error_code' => 'nopay'"));
/* $force=false hicbir numara yakmiyor (kabul ani) -- orada durmak teklifin
   KABUL EDILMESINI engellerdi. */
$t('tek teklifte gap yalniz $force dalinda', str_contains($singleBody, 'if ($force) {'));

/* ------------------------------------------- §3 GERCEKTEN kesiyor mu? */
echo "== 3. kum havuzunda GERCEK kesim denemesi ==\n";
$head = ['ref','timestamp','company','name','email','country','items','subtotal','shipping','total','notes'];
$f = $sand.'/data/orders.csv';
$h = fopen($f, 'w');
fputcsv($h, $head, ',', '"', '\\');
fputcsv($h, ['VES-GAP1', date('c'), 'Mob SARL', 'Test Buyer', 'buyer@example.com', 'France',
             '10x SKU1 @120.00', '1200.00', '0.00', '1200.00', 'Payment: Bank transfer.'], ',', '"', '\\');
fclose($h);
file_put_contents($sand.'/data/accounts.json', json_encode([]));

$invDir = vestra_invoice_dir();
$before = glob($invDir.'/*.pdf') ?: [];

/* platform_seller.json YOK -> hicbir banka alani yok -> EUR kutusu bos */
$r1 = vestra_issue_order_invoices('VES-GAP1');
$t('banka yokken kesim REDDEDILDI', is_array($r1) && !empty($r1['error']));
$t('ret kodu nopay', (string)($r1['error_code'] ?? '') === 'nopay');
$t('ret sebebi kutuyu soyluyor', stripos((string)($r1['error'] ?? ''), 'payment box') !== false);
$after1 = glob($invDir.'/*.pdf') ?: [];
$t('HICBIR belge yazilmadi (numara yanmadi)', count($after1) === count($before));

/* Simdi IBAN giriliyor -- panelin yazdigi kaydin aynisi. */
file_put_contents($sand.'/data/platform_seller.json', json_encode([
    'bank_holder' => 'Acerasoft LLC',
    'bank_iban'   => 'DE89370400440532013000',
]));
$r2 = vestra_issue_order_invoices('VES-GAP1');
$t('IBAN girilince kesim GECIYOR', is_array($r2) && empty($r2['error']) && count($r2) > 0);
$after2 = glob($invDir.'/*.pdf') ?: [];
$t('belge gercekten yazildi', count($after2) === count($before) + 1);

/* Belge ODEME KUTUSUNU tasiyor mu? "Kesildi" tek basina yetmez: bu deponun
   kendi kaydi, fotografsiz bir PDF'i yillarca "uretildi, boyut makul" diye
   gecirdigini yaziyor. inc/pdf.php akislari SIKISTIRMIYOR, yani ham baytta
   metin aranabilir. */
$pdfNew = array_values(array_diff($after2, $before));
$pdfRaw = $pdfNew ? (string)file_get_contents($pdfNew[0]) : '';
$t('PDF uretildi ve okunuyor', strlen($pdfRaw) > 1000);
$t('akis sikistirilmamis (metin aranabilir)', !str_contains($pdfRaw, '/FlateDecode'));
$t('belgede IBAN satiri VAR', str_contains($pdfRaw, 'IBAN:'));
$t('belgede lehdar satiri VAR', str_contains($pdfRaw, 'Beneficiary:'));

/* ------------------------------------------------------- §4 panel kablosu */
echo "== 4. panel kablolamasi ==\n";
$a = file_get_contents($root.'/admin.php');
$t('nopay bandi var', str_contains($a, "\$msg==='invoice_nopay'"));
$t('bant NUMARA YAKILMADIGINI yaziyor', str_contains($a, 'Hiçbir numara yakılmadı'));
/* Sebebe gore bant secimi: metne bakip karar vermek (str_contains) bir gun
   cumle degisince sessizce para birimi bandina donerdi. */
$t('siparis yolu error_code ile bant seciyor',
   substr_count($a, "(\$r['error_code']??'')==='nopay'") + substr_count($a, "(\$iv['error_code']??'')==='nopay'") === 2);
$t('onay satirinda TIKLAMADAN ONCE uyari cipi var',
   str_contains($a, 'ödeme kutusu YOK') && str_contains($a, 'vestra_invoice_payment_gap('));

/* ------------------------------------------- §5 BOLGE VARSAYILANI (5s) */
echo "== 5. bolge varsayilani: Avrupa disi + platform -> USD ==\n";

$plat = null;                                   // platform kesimi
$sell = ['id' => 'abc123', 'company' => 'GARAGE LE PARIS'];

$t('platform + Fransa + EUR -> degisiklik YOK',  vestra_invoice_currency_default($plat, 'France', 'EUR') === '');
$t('platform + ES kodu + EUR -> degisiklik YOK', vestra_invoice_currency_default($plat, 'ES', 'EUR') === '');
$t('platform + ABD + EUR -> USD',                vestra_invoice_currency_default($plat, 'United States', 'EUR') === 'USD');
$t('platform + Japonya + EUR -> USD',            vestra_invoice_currency_default($plat, 'Japan', 'EUR') === 'USD');
$t('platform + BAE + EUR -> USD',                vestra_invoice_currency_default($plat, 'United Arab Emirates', 'EUR') === 'USD');
/* Yakin-komsu tuzagi: AT Avusturya (Avrupa), AU Avustralya (degil). Alt dize
   eslesmesi ikisini karistirirdi -- mango/zara dersinin cografya hali. */
$t('AT (Avusturya) Avrupa sayilir',  vestra_invoice_currency_default($plat, 'AT', 'EUR') === '');
$t('AU (Avustralya) Avrupa DEGIL',   vestra_invoice_currency_default($plat, 'AU', 'EUR') === 'USD');
$t('GB (Avrupa, euro degil) -> EUR kalir', vestra_invoice_currency_default($plat, 'GB', 'EUR') === '');
$t('CH (Avrupa, euro degil) -> EUR kalir', vestra_invoice_currency_default($plat, 'CH', 'EUR') === '');

/* UC KAPI, ucu de ayri ayri dusebilmeli. */
$t('SATICI kesiminde varsayilan YOK', vestra_invoice_currency_default($sell, 'United States', 'EUR') === '');
$t('EUR olmayan satista varsayilan YOK', vestra_invoice_currency_default($plat, 'United States', 'USD') === '');
$t('ulke BOS -> varsayilan YOK (ihtiyatli)', vestra_invoice_currency_default($plat, '', 'EUR') === '');
$t('taninmayan yazim -> USD (pozitif olcut)', vestra_invoice_currency_default($plat, 'Benin', 'EUR') === 'USD');

echo "== 5b. kum havuzunda GERCEK yuk: bolge birimi belirliyor mu ==\n";
$mk = function (string $ref, string $country) use ($sand, $head) {
    $h = fopen($sand.'/data/orders.csv', 'a');
    fputcsv($h, [$ref, date('c'), 'Test Co', 'Buyer', 'b@example.com', $country,
                 '10x SKU1 @120.00', '1200.00', '0.00', '1200.00', 'Payment: Bank transfer.'], ',', '"', '\\');
    fclose($h);
};
$mk('VES-US1', 'United States');
$mk('VES-FR1', 'France');
/* Cevrim SIPARIS TARIHININ damgali kuruyla; damga yoksa yuk gerekce doner.
   Damgayi elle koyuyoruz ki olculen sey KUR degil BOLGE karari olsun. */
$st = json_decode((string)@file_get_contents($sand.'/data/order_statuses.json'), true) ?: [];
$st['VES-US1']['fx'] = ['usd' => 1.1622, 'date' => '2026-09-04', 'source' => 'ECB'];
file_put_contents($sand.'/data/order_statuses.json', json_encode($st));

$pUS = vestra_order_invoice_payloads('VES-US1');
$pFR = vestra_order_invoice_payloads('VES-FR1');
$t('ABD siparisi tek dilim (platform)', count($pUS) === 1);
$t('ABD siparisinin BELGESI USD',  strtoupper((string)($pUS[0]['meta']['currency'] ?? '')) === 'USD');
$t('ABD siparisinde cevrim hatasi YOK', empty($pUS[0]['currency_error']));
/* KONTROL GRUBU: Avrupali siparis DEGISMEMELI. Tek yon olculseydi "her
   siparisi USD yapan" bir kusur da yesil gorunurdu -- operatorun bekleyen
   iki siparisi tam olarak bu grupta. */
$t('Fransa siparisinin BELGESI EUR', strtoupper((string)($pFR[0]['meta']['currency'] ?? '')) === 'EUR');

/* VARSAYILANIN OLCULEN BEDELI: damgasiz bir Avrupa disi siparis artik
   KESILEMIYOR. Once EUR olarak gecerdi; simdi belge USD olmak istiyor ve
   cevrim SIPARIS TARIHININ damgasini sart kosuyor (KURAL 5i) -- damga yoksa
   yuk gerekce donuyor, hicbir numara yanmiyor. Bunu bir iddia olarak
   yaziyorum ki bedel nesirde kalmasin: panelin caresi "Fetch missing rates",
   ve siparisler zaten yazilirken damgalaniyor. */
$mk('VES-US2', 'United States');
$pUS3 = vestra_order_invoice_payloads('VES-US2');
/* Yuk cevrilemeyince meta ESKI birimde kaliyor ve istenen birim
   `want_currency`de duruyor -- ilk yazimda meta'ya baktim ve iddia dustu:
   KOD HAKLIYDI, iddia yanlisti (cevrilememis bir yuke "USD" demek, belgenin
   tasimadigi bir birimi iddia etmek olurdu). */
$t('damgasiz ABD siparisi: istenen birim USD',
   strtoupper((string)($pUS3[0]['want_currency'] ?? '')) === 'USD');
$t('damgasiz ABD siparisi KESILEMIYOR (gerekce var)', !empty($pUS3[0]['currency_error']));
/* KONTROL GRUBU: Avrupali siparis de damgasiz ve SORUNSUZ -- yani duran sey
   damganin yoklugu degil, cevrim istegi. */
$t('damgasiz Avrupa siparisinde gerekce YOK', empty($pFR[0]['currency_error']));

/* OPERATORUN SECIMI HER ZAMAN ONDE (KURAL 5i): varsayilan bir dayatma degil. */
$st['VES-US1']['invoice_currency'] = 'EUR';
file_put_contents($sand.'/data/order_statuses.json', json_encode($st));
$pUS2 = vestra_order_invoice_payloads('VES-US1');
$t('kayitli EUR secimi bolge varsayilanini EZIYOR',
   strtoupper((string)($pUS2[0]['meta']['currency'] ?? '')) === 'EUR');

echo "== 5c. panel dogruyu gosteriyor ==\n";
$t('secici ETKIN birimi yukten okuyor', str_contains($a, "\$__pl[0]['meta']['currency']"));
/* Cevrilemeyen yukte meta eski birimde kalir; panel ISTENEN birimi yazmali,
   yoksa "otomatik (EUR)" der ve kesim USD yuzunden durur -- rakam dogru,
   etiket yalan. */
$t('secici once want_currency okuyor', str_contains($a, "\$__pl[0]['want_currency']"));
$t('satirda kur damgasi cipi var', str_contains($a, 'kur damgası yok — kesilemez'));
$t('secici "otomatik" diyor', str_contains($a, '— otomatik ('));
/* Siparis birimi listede olmazsa operator bolge varsayilanini geri ceviremez. */
$t('siparis birimi de LISTEDE (EUR zorlanabilir)', !str_contains($a, 'if($__c===$__ocur) continue;'));
$t('teklif yolu da ayni govdeyi cagiriyor',
   substr_count(file_get_contents($root.'/inc/offers.php'), 'vestra_invoice_currency_default(') === 2);

echo "\n-- {$ok} ok, {$bad} HATA --\n";
exit($bad === 0 ? 0 : 1);
