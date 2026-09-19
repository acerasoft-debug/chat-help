<?php
/* HOS GELDIN INDIRIMI — otomatik karar + gecmise donuk yazma (19 Eyl 2026).
 *
 * Operator: "yuzde 5 Welcome indirimini her uc siparise ekle ... bundan sonraki
 * her musterinin ilk siparine de ekle afrika ve yuzde 8 yada 10 indirim alanlar
 * haric".
 *
 * IKI YON DE TUTULUYOR ve bu sart: tek yon yazilsaydi
 *  - yalniz "indirim uygulaniyor" olculseydi, HERKESE uygulayan bir kusur
 *    (bolgesel indirim alanlar dahil) yesil kalirdi -- yani ayni satisa iki
 *    indirim;
 *  - yalniz "haric tutuluyor" olculseydi, HICBIR ZAMAN uygulamayan bir kusur
 *    yesil kalirdi ve operatorun istedigi sey hic calismazdi.
 *
 * KUM HAVUZUNDA GERCEKTEN YAZIYOR: bu depoda renk notunun kalibi yillarca
 * hicbir gercek siparise uymadi cunku okuma tarafi kendini dogrulayamiyor.
 * Burada da yazip GERI OKUYORUZ, ve dogrulama satirin degismesine degil
 * FATURAYI BESLEYEN yukun indirimi gormesine bakiyor.
 */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_wd_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);

if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
require_once $root.'/inc/products.php';
require_once $root.'/inc/orders.php';
require_once $root.'/inc/vouchers.php';
require_once $root.'/inc/invoice.php';

if (vestra_data_dir() !== $sand.'/data') {
    fwrite(STDERR, "kum havuzu kurulamadi (vestra_data_dir=".vestra_data_dir().")\n"); exit(1);
}
/* invoice_dir de kum havuzunda olmali: aksi halde "faturasi var mi" sorusu
   GERCEK klasore bakar ve muhafazayi olcemeyiz (bu depoda bir kez yasandi). */
if (!str_starts_with(vestra_invoice_dir(), $sand)) {
    fwrite(STDERR, "fatura dizini kum havuzunda degil: ".vestra_invoice_dir()."\n"); exit(1);
}

$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal',
         'commission','payout','total','notes','consent','terms_version','voucher_code','discount',
         'shipping','shipping_label'];
$mk = function (string $ref, string $email, string $country, string $items,
                float $goods, float $ship = 0.0, float $disc = 0.0, string $vc = '') use ($sand, $head) {
    $f = $sand.'/data/orders.csv';
    $new = !is_file($f);
    $h = fopen($f, $new ? 'w' : 'a');
    if ($new) fputcsv($h, $head, ',', '"', '\\');
    fputcsv($h, [date('c'), $ref, 'Test Co', 'X1', 'Tester', $email, $country, '+1', $items,
                 number_format($goods - $disc, 2, '.', ''), '0.00', number_format($goods - $disc, 2, '.', ''),
                 number_format($goods - $disc + $ship, 2, '.', ''),
                 'Payment: Bank transfer. Colours — SKU1: Black.', 'yes', '2026-06-26',
                 $vc, $disc > 0 ? number_format($disc, 2, '.', '') : '',
                 number_format($ship, 2, '.', ''), $ship > 0 ? 'Shipping' : ''], ',', '"', '\\');
    fclose($h);
};

echo "== 1. KARAR: kim aliyor, kim ALMIYOR ==\n";
/* AVRUPA: bolgesel indirimi yok -> otomatik %5 */
$a = vestra_welcome_auto('new@example.com', ['country' => 'Austria']);
$t('Avusturya musterisi %5 aliyor',        abs($a['pct'] - (float)VESTRA_WELCOME_PCT) < 0.001 && $a['why'] === 'ok');
$t('Almanya musterisi %5 aliyor',          vestra_welcome_auto('new@example.com', ['country' => 'Germany'])['pct'] > 0);
$t('Ispanya (ES kodu) %5 aliyor',          vestra_welcome_auto('new@example.com', ['country' => 'ES'])['pct'] > 0);
$t('Fransa %5 aliyor',                     vestra_welcome_auto('new@example.com', ['country' => 'France'])['pct'] > 0);

/* AFRIKA (operatorun adiyla andigi kume) -> %8 grubu, HARIC */
$ng = vestra_welcome_auto('new@example.com', ['country' => 'Nigeria']);
$t('Nijerya HARIC',                        $ng['pct'] === 0.0 && $ng['why'] === 'region');
$t('Nijerya gerekcesi %8 yaziyor',         abs($ng['region_pct'] - 8.0) < 0.001);
$t('Benin HARIC',                          vestra_welcome_auto('new@example.com', ['country' => 'Benin'])['why'] === 'region');
$t('Fas (Maroc, FR yazim) HARIC',          vestra_welcome_auto('new@example.com', ['country' => 'Maroc'])['why'] === 'region');
/* Yakin komsu tuzagi: Niger ile Nijerya AYRI ulkeler, ikisi de Afrika.
   Ikisinin de haric kalmasi dogru; olculen sey, birinin otekini "icermesi"
   yuzunden listenin bozulmadigi. */
$t('Niger de HARIC (Nigeria ile karismiyor)', vestra_welcome_auto('new@example.com', ['country' => 'Niger'])['why'] === 'region');

/* %10 grubu -> HARIC */
$jp = vestra_welcome_auto('new@example.com', ['country' => 'Japan']);
$t('Japonya HARIC',                        $jp['why'] === 'region' && abs($jp['region_pct'] - 10.0) < 0.001);
$t('Brezilya HARIC',                       vestra_welcome_auto('new@example.com', ['country' => 'Brazil'])['why'] === 'region');
$t('Polonya HARIC',                        vestra_welcome_auto('new@example.com', ['country' => 'PL'])['why'] === 'region');

/* MANGO/ZARA DERSI: 'AT' (Avusturya) ile 'AU' (Avustralya) ayri ulkeler ve
   Avustralya %10 grubunda. Alt dize / gevsek eslesme Avusturyali aliciyi
   sessizce indirimden EDERDI. */
$t('Avustralya HARIC (%10)',               vestra_welcome_auto('new@example.com', ['country' => 'Australia'])['why'] === 'region');
$t('Avusturya HARIC DEGIL (AT != AU)',     vestra_welcome_auto('new@example.com', ['country' => 'AT'])['pct'] > 0);

echo "\n== 2. KARAR: ilk siparis, kupon, bos adres ==\n";
$t('elle kupon uygulandiysa otomatik YOK', vestra_welcome_auto('new@example.com', ['country' => 'ES'], true)['why'] === 'voucher');
$t('adres yoksa karar YOK',                vestra_welcome_auto('', ['country' => 'ES'])['why'] === 'no_email');
$t('hesap null ise yine %5 (bolge yok)',   vestra_welcome_auto('new@example.com', null)['pct'] > 0);

$mk('VES-OLD1', 'repeat@example.com', 'ES', '1x SKU1 @100.00', 100.00);
$r2 = vestra_welcome_auto('repeat@example.com', ['country' => 'ES']);
$t('ikinci siparis indirim ALMAZ',         $r2['pct'] === 0.0 && $r2['why'] === 'not_first');
$t('sayac 1 diyor',                        (int)$r2['orders'] === 1);
/* exceptRef: ZATEN YAZILMIS bir siparise sonradan islerken o satir kendini
   saymamali -- yoksa kontrol tam uygulanmak istendigi anda kendini engeller. */
$r3 = vestra_welcome_auto('repeat@example.com', ['country' => 'ES'], false, 'VES-OLD1');
$t('kendi ref\'i haric tutulunca %5',      $r3['pct'] > 0 && $r3['why'] === 'ok');
$t('voucher_customer_order_count exceptRef', voucher_customer_order_count('repeat@example.com', 'VES-OLD1') === 0
                                          && voucher_customer_order_count('repeat@example.com') === 1);

echo "\n== 3. ETIKET rakami METNE GOMULU DEGIL ==\n";
$t('kod yuzdeden turuyor', vestra_welcome_auto_code()
   === 'WELCOME'.rtrim(rtrim(number_format((float)VESTRA_WELCOME_PCT, 2, '.', ''), '0'), '.'));
$t('bugunku yuzde 5',       abs((float)VESTRA_WELCOME_PCT - 5.0) < 0.001);
$t('kod voucher_find ile BULUNMUYOR (etiket)', voucher_find(vestra_welcome_auto_code()) === null);

echo "\n== 4. YAZMA: indirim + toplam + payout BIRLIKTE, geri okunarak ==\n";
$mk('VES-W1', 'w1@example.com', 'Austria', '10x SKU1 @78.90', 789.00, 20.00);
$w = vestra_order_set_discount('VES-W1', 5.0);
$t('yazma basarili',               empty($w['error']));
$t('mal toplami satirlardan 789',  abs((float)($w['goods'] ?? 0) - 789.00) < 0.005);
$t('indirim 39.45',                abs((float)($w['discount'] ?? 0) - 39.45) < 0.005);
$t('toplam 769.55 (mal - ind + navlun)', abs((float)($w['total'] ?? 0) - 769.55) < 0.005);
$t('subtotal indirim SONRASI',     abs((float)($w['subtotal'] ?? 0) - 749.55) < 0.005);
$t('payout da dustu',              abs((float)($w['payout'] ?? 0) - 749.55) < 0.005);
$t('kod etiketi yazildi',          (string)($w['code'] ?? '') === vestra_welcome_auto_code());
$t('gercek kupon yok -> damgalanmadi', empty($w['redeemed']));

$back = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-W1') $back = $x;
$t('CSV geri okundu: discount',    abs((float)($back['discount'] ?? 0) - 39.45) < 0.005);
$t('CSV geri okundu: total',       abs((float)($back['total'] ?? 0) - 769.55) < 0.005);
$t('CSV geri okundu: voucher_code',(string)($back['voucher_code'] ?? '') === vestra_welcome_auto_code());
/* Navluna DOKUNULMADI: iki ayri olgu, iki ayri yazici. */
$t('navlun degismedi',             abs((float)($back['shipping'] ?? 0) - 20.00) < 0.005);

echo "\n== 5. BELGENIN KENDISI (satirin degismesi yetmez) ==\n";
/* Yuk degil BELGE olculuyor: bu depoda bir PDF yillarca "uretildi, boyut
   makul" diye gecmis ve icinde hicbir fotograf olmamisti. inc/pdf.php
   akislari SIKISTIRMIYOR, yani ham baytta metin aranabiliyor -- ve bunu
   ayrica dogruluyoruz ki bir gun Flate eklenirse "indirim yok" diye YANLIS
   bir kirmizi degil, sebebini soyleyen satir dussun. */
$pl = vestra_order_invoice_payloads('VES-W1');
$slice = is_array($pl) ? (array)($pl[0] ?? []) : [];
$t('yuk uretildi',                 !empty($slice['meta']) && !empty($slice['items']));
$t('fatura yuku indirimi GORUYOR', abs((float)(($slice['meta'] ?? [])['discount'] ?? 0) - 39.45) < 0.005);
$t('fatura yuku kodu GORUYOR',     (string)(($slice['meta'] ?? [])['voucher_code'] ?? '') === vestra_welcome_auto_code());

$pdf = vestra_render_invoice_pdf($slice['meta'], $slice['items'], null, 'INV-TEST-WD', false);
$t('PDF uretildi',                 str_starts_with($pdf, '%PDF') && strlen($pdf) > 2000);
$t('akislar SIKISTIRILMAMIS (metin aranabilir)', !str_contains($pdf, '/FlateDecode'));
$t('BELGEDE "Voucher" satiri VAR', str_contains($pdf, 'Voucher'));
$t('BELGEDE kod yaziyor',          str_contains($pdf, vestra_welcome_auto_code()));
$t('BELGEDE indirim tutari 39.45', str_contains($pdf, '39.45'));
$t('BELGEDE genel toplam 769.55',  str_contains($pdf, '769.55'));
/* TERS YON: indirimsiz bir siparisin belgesinde bu satirlarin HICBIRI
   olmamali. Tek yon yazilsaydi "her belgeye indirim satiri basan" bir kusur
   da yesil kalirdi. */
$mk('VES-NODISC', 'nd@example.com', 'ES', '1x SKU1 @100.00', 100.00);
$pl0 = vestra_order_invoice_payloads('VES-NODISC');
$pdf0 = vestra_render_invoice_pdf($pl0[0]['meta'], $pl0[0]['items'], null, 'INV-TEST-ND', false);
$t('indirimsiz belgede "Voucher" YOK', !str_contains($pdf0, 'Voucher'));
$t('indirimsiz belgede kod YOK',       !str_contains($pdf0, vestra_welcome_auto_code()));

echo "\n== 6. pct=0 INDIRIMI KALDIRIR ==\n";
$z = vestra_order_set_discount('VES-W1', 0.0);
$t('sifir yazma basarili',         empty($z['error']));
$t('indirim 0',                    abs((float)($z['discount'] ?? -1)) < 0.005);
$t('kod temizlendi',               (string)($z['code'] ?? 'x') === '');
$t('toplam mal + navluna dondu',   abs((float)($z['total'] ?? 0) - 809.00) < 0.005);
/* Ikinci kez %5 yazmak BILESIK bir rakam uretmemeli: taban her zaman
   SATIRLARDAN gelen mal toplami, `subtotal` sutunu degil. ARDI ARDINA iki kez
   yaziliyor ve ARADA SIFIRLANMIYOR -- sifirlamak tabani 789'a geri dondurur ve
   bilesik hesap yapan bir kusur da yesil kalirdi (ilk yazimimda tam boyleydi:
   sabotaj davranisi degistirdi, test gormedi). */
$again1 = vestra_order_set_discount('VES-W1', 5.0);
$t('ilk yazim 39.45',                      abs((float)($again1['discount'] ?? 0) - 39.45) < 0.005);
$again2 = vestra_order_set_discount('VES-W1', 5.0);
$t('UST USTE ikinci yazim yine 39.45 (bilesik DEGIL)',
                                           abs((float)($again2['discount'] ?? 0) - 39.45) < 0.005);
$t('ust uste yazimda toplam da sabit',     abs((float)($again2['total'] ?? 0) - 769.55) < 0.005);
$t('ust uste yazimda mal toplami sabit',   abs((float)($again2['goods'] ?? 0) - 789.00) < 0.005);

echo "\n== 7. MUHAFAZALAR ==\n";
/* Parasi gelmis siparis: KOSULSUZ RED. */
$mk('VES-PAID', 'p@example.com', 'ES', '1x SKU1 @100.00', 100.00);
vestra_write_json('order_statuses.json', ['VES-PAID' => ['status' => 'paid', 'paid_at' => '2026-09-01T10:00:00+00:00']]);
$p = vestra_order_set_discount('VES-PAID', 5.0);
$t('odenmis siparis REDDEDILDI',   !empty($p['error']));
$t('ret gerekcesi parayi soyluyor', str_contains((string)($p['error'] ?? ''), 'parası gelmiş'));
$pb = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-PAID') $pb = $x;
$t('odenmis siparise HICBIR SEY yazilmadi', trim((string)($pb['discount'] ?? '')) === '');

/* Faturasi kesilmis siparis: opt-in olmadan RED, opt-in ile yazar ve
   must_redraft doner (KURAL 5f). */
$mk('VES-INV', 'i@example.com', 'ES', '1x SKU1 @100.00', 100.00);
@mkdir(vestra_invoice_dir(), 0777, true);
file_put_contents(vestra_invoice_dir().'/VES-INV__vestra.json',
    json_encode(['no' => 'INV-TEST-1', 'seller_key' => 'vestra', 'total' => 100.0, 'currency' => 'EUR']));
$i1 = vestra_order_set_discount('VES-INV', 5.0);
$t('faturali siparis opt-in OLMADAN reddedildi', !empty($i1['error']));
$t('ret KURAL 5f\'e yolluyor',     str_contains((string)($i1['error'] ?? ''), 'KURAL 5f'));
$ib = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-INV') $ib = $x;
$t('faturaliya da HICBIR SEY yazilmadi', trim((string)($ib['discount'] ?? '')) === '');
$i2 = vestra_order_set_discount('VES-INV', 5.0, '', true);
$t('opt-in ile YAZILDI',           empty($i2['error']));
$t('must_redraft bayragi VAR',     !empty($i2['must_redraft']));
$t('hangi numara oldugu yaziyor',  in_array('INV-TEST-1', (array)($i2['invoiced'] ?? []), true));

/* Sinirlar */
$t('yuzde 100 ustu reddedilir',    !empty(vestra_order_set_discount('VES-W1', 101.0)['error']));
$t('negatif yuzde reddedilir',     !empty(vestra_order_set_discount('VES-W1', -1.0)['error']));
$t('olmayan ref reddedilir',       !empty(vestra_order_set_discount('VES-YOK', 5.0)['error']));

echo "\n== 8. GERCEK kupon kodu DAMGALANIR ==\n";
if (!defined('VESTRA_VOUCHERS_OK')) { /* kupon deposu kum havuzunda mi? */ }
$vfile = $root.'/inc/../data/vouchers.json';
$t('kupon deposu sabit (uretimle ayni yol)', defined('VESTRA_VOUCHERS'));
/* voucher_redeem gercek depoya yazardi, o yuzden BURADA yazma denenmiyor;
   olculen sey kablolama: verilen kod depoda VARSA damgalanir dali kodda mi. */
$src = file_get_contents($root.'/inc/orders.php');
$fn  = substr($src, strpos($src, 'function vestra_order_set_discount'));
$fn  = substr($fn, 0, strpos($fn, "\nfunction ") ?: strlen($fn));
$t('voucher_find ile kontrol ediliyor', str_contains($fn, 'voucher_find($newCode)'));
$t('voucher_redeem cagriliyor',         str_contains($fn, 'voucher_redeem('));
$t('yuvarlama voucher_discount ile',    str_contains($fn, 'voucher_discount(')
                                     && !preg_match('/\$newDisc\s*=\s*round\(/', $fn));
$t('mal toplami vestra_order_lines\'tan', str_contains($fn, 'vestra_order_lines('));
$t('odeme muhafazasi settled\'a soruyor', str_contains($fn, 'vestra_order_payment_settled('));

echo "\n== 9. KASA (order.php) TEK KARAR NOKTASINI cagiriyor ==\n";
$osrc = file_get_contents($root.'/order.php');
$t('order.php vestra_welcome_auto cagiriyor', str_contains($osrc, 'vestra_welcome_auto('));
$t('kendi bolge kontrolunu YAZMIYOR',         !str_contains($osrc, 'vestra_region_discount_pct('));
$t('kendi ilk-siparis sayimini YAZMIYOR',     !str_contains($osrc, 'voucher_customer_order_count('));
$t('hesabi auth_user() ile okuyor ($me DEGIL)',
   (bool)preg_match('/vestra_welcome_auto\(\s*\$email\s*,\s*auth_user\(\)/', $osrc));
$t('yalniz indirim YOKKEN calisiyor',         str_contains($osrc, 'if ($discount <= 0) {'));
$t('etiket fonksiyondan',                     str_contains($osrc, 'vestra_welcome_auto_code()'));
/* Yanlis yazilmis bir kod ilk siparis indirimini ARTIK ENGELLEMIYOR: mektup
   ikisini birden yazmali, yoksa kodu yazan alicinin sorusu cevapsiz kalir. */
$t('mektup ikisini birden yazabiliyor',
   str_contains($osrc, '$voucherFailed ? "Note: voucher code'));

/* ── temizlik ─────────────────────────────────────────────────────────────── */
$rm = function (string $d) use (&$rm) {
    foreach (scandir($d) ?: [] as $f) { if ($f === '.' || $f === '..') continue;
        $p = $d.'/'.$f; is_dir($p) ? $rm($p) : @unlink($p); }
    @rmdir($d);
};
$rm($sand);

echo "\nwelcome_discount_test: {$ok} iddia gecti".($bad ? ", {$bad} HATA" : '')."\n";
exit($bad ? 1 : 0);
