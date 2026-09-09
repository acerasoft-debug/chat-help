<?php
/* Gonderim bilgisi: tasiyici + servis + takip BAGLANTISI (operator, 9 Eyl 2026:
 * "bu gonderim numarasini ekle link ile beraber ups express saver" + "her
 * pakette gonderici kargo bolumude olsun").
 *
 * Testin tuttugu ASIL sey iki YON:
 *   (a) UPS numarasi tasiyiciyi ve calisan bir baglantiyi VERMELI,
 *   (b) taninmayan bir numara tasiyici UYDURMAMALI -- yanlis firmaya giden bir
 *       baglanti alicinin karsisina baska bir paketi (ya da hicbir seyi) cikarir
 *       ve bu, baglanti olmamasindan kotu. Bu depoda mango/zara dersi.
 *
 * Ayrica: baglanti KAYDA yazilmaz, numaradan turetilir -- yoksa numara
 * degistiginde eski URL kalir (desc/sizes ve thread-id hatalarinin ayni sinifi).
 */
$root = __DIR__.'/../vestra';

/* orders.php'nin tamami yuklenemez (t(), vestra_products() vs. ister); gonderim
   fonksiyonlari SAF, kaynaktan tek tek aliniyor. */
$src = file_get_contents($root.'/inc/orders.php');
foreach (['vestra_carriers', 'vestra_carrier_from_tracking', 'vestra_order_shipment'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn, '/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}
$tplSrc = file_get_contents($root.'/inc/email_templates.php');
foreach (['vestra_display_name', 'vestra_tpl_order_shipped'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn, '/').'\(.*?^}/ms', $tplSrc, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "-- 1. UPS numarasindan tasiyici cikarimi --\n";
$t('gercek 1Z numarasi UPS',            vestra_carrier_from_tracking('1ZY0089E0495346496') === 'ups');
$t('kucuk harf de UPS',                 vestra_carrier_from_tracking('1zy0089e0495346496') === 'ups');
$t('bosluklu yazim da UPS',             vestra_carrier_from_tracking('1Z Y0089E 0495346496') === 'ups');

echo "-- 2. TERS YON: tanimayan numarada tasiyici UYDURULMAZ --\n";
/* Bunlarin hepsi gercek kargo numarasi BICIMI; hicbiri 1Z degil ve hicbiri
   kesin degil, o yuzden kod susmali ve operatorun yazmasini beklemeli. */
$t('10 hane (DHL bicimi) -> cikarim YOK', vestra_carrier_from_tracking('1234567890') === '');
$t('12 hane (FedEx bicimi) -> YOK',       vestra_carrier_from_tracking('123456789012') === '');
$t('bos -> YOK',                          vestra_carrier_from_tracking('') === '');
$t('1Z ama kisa -> YOK',                  vestra_carrier_from_tracking('1Z12345') === '');
$t('1Z ama uzun -> YOK',                  vestra_carrier_from_tracking('1ZY0089E04953464961') === '');
$t('1Z + noktalama -> YOK',               vestra_carrier_from_tracking('1ZY0089E-495346496') === '');
$t('"1Z" ile baslayan duz metin -> YOK',  vestra_carrier_from_tracking('1Z is my parcel ok') === '');

echo "-- 3. Operatorun verdigi sevkiyat: O39419 --\n";
$s = vestra_order_shipment(['tracking' => '1ZY0089E0495346496', 'ship_service' => 'Express Saver']);
$t('tasiyici UPS',                      $s['carrier'] === 'ups' && $s['carrier_name'] === 'UPS');
$t('servis adi korunur',                $s['service'] === 'Express Saver');
$t('numara korunur',                    $s['tracking'] === '1ZY0089E0495346496');
$t('baglanti operatorun verdigi bicim', $s['url'] === 'https://www.ups.com/track?tracknum=1ZY0089E0495346496&loc=en_US&requester=ST/trackdetails');
$t('has=true',                          $s['has'] === true);

echo "-- 4. Baglanti TURETILIYOR, kayittan okunmuyor --\n";
/* Kayda elle bir 'url' koysak bile numara neyse baglanti odur: iki kopya
   olmadigini gosteren iddia. */
$s2 = vestra_order_shipment(['tracking' => '1ZY0089E0495346496', 'url' => 'https://example.com/eski-link']);
$t('kayittaki eski url YOK SAYILIR',    !str_contains($s2['url'], 'example.com'));
$t('numaradan turemis url',             str_contains($s2['url'], '1ZY0089E0495346496'));
$s3 = vestra_order_shipment(['tracking' => '1ZAAAAAA0000000000', 'ship_carrier' => 'ups']);
$t('numara degisince url de degisir',   str_contains($s3['url'], '1ZAAAAAA0000000000') && !str_contains($s3['url'], '0495346496'));

echo "-- 5. Operatorun YAZDIGI tasiyici cikarimi EZER --\n";
$s4 = vestra_order_shipment(['tracking' => '1234567890', 'ship_carrier' => 'dhl']);
$t('DHL yazilmissa DHL',                $s4['carrier'] === 'dhl' && $s4['carrier_name'] === 'DHL');
$t('DHL baglantisi kuruldu',            str_contains($s4['url'], 'dhl.com') && str_contains($s4['url'], '1234567890'));
$s5 = vestra_order_shipment(['tracking' => '1234567890']);
$t('yazilmamissa baglanti YOK',         $s5['url'] === '' && $s5['carrier_name'] === '');
$t('ama numara yine de duruyor',        $s5['tracking'] === '1234567890' && $s5['has'] === true);
$s6 = vestra_order_shipment(['tracking' => '1ZY0089E0495346496', 'ship_carrier' => 'bilinmeyen']);
$t('gecersiz tasiyici cikarima duser',  $s6['carrier'] === 'ups');

echo "-- 6. Bos sevkiyat --\n";
$s7 = vestra_order_shipment(null);
$t('null guvenli',                      $s7['tracking'] === '' && $s7['url'] === '' && $s7['has'] === false);
$s8 = vestra_order_shipment([]);
$t('bos dizi guvenli',                  $s8['has'] === false);
$s9 = vestra_order_shipment(['ship_service' => 'Express Saver']);
$t('yalniz servis de bir bilgidir',     $s9['has'] === true && $s9['service'] === 'Express Saver');

echo "-- 7. URL enjeksiyonu: numara URL'e kacislanarak giriyor --\n";
/* Normalizasyon YAZMA tarafinda (vestra_order_set_shipment); okuyucu kaydi
   oldugu gibi gosterir. Burada tutulan sey normalizasyon degil KACISLAMA:
   numaradaki '&' ikinci bir sorgu parametresi acamamali. */
$s10 = vestra_order_shipment(['tracking' => 'AB&x=1 y', 'ship_carrier' => 'ups']);
$t('& kacislandi (yeni parametre yok)',  !str_contains($s10['url'], '&x=1') && str_contains($s10['url'], '%26'));
$t('= ve bosluk da kacislandi',          str_contains($s10['url'], '%3D') && str_contains($s10['url'], '%20'));
$t('loc/requester parametreleri bozulmadi', str_contains($s10['url'], '&loc=en_US&requester=ST/trackdetails'));

echo "-- 8. 'gonderildi' mektubu ayni uc olguyu tasir --\n";
[$sub, $body, $opts] = vestra_tpl_order_shipped('samuel kozak', 'O39419', '1ZY0089E0495346496', true, $s);
$t('konu ref tasir',                    str_contains($sub, 'O39419'));
$t('govdede tasiyici + servis',         str_contains($body, 'Carrier: UPS Express Saver'));
$t('govdede numara',                    str_contains($body, 'Tracking number: 1ZY0089E0495346496'));
$t('govdede takip baglantisi',          str_contains($body, 'https://www.ups.com/track?tracknum=1ZY0089E0495346496'));
$t('ana dugme TAKIP sayfasi',           ($opts['button']['url'] ?? '') === $s['url']);
$t('panel linki ikincil dugmede',       ($opts['button_alt']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders');
$rowLabels = array_column($opts['rows'], 'label');
$t('kutuda Carrier satiri',             in_array('Carrier', $rowLabels, true));
$t('kutuda Tracking satiri kalin',      ($opts['rows'][2]['label'] ?? '') === 'Tracking number' && !empty($opts['rows'][2]['strong']));
$t('Turkce karakter yok',               !preg_match('/[şğıİçöüŞĞÇÖÜ]/u', $sub.$body));

echo "-- 9. Sevkiyat bilgisi YOKSA mektup eski haliyle calisir --\n";
[$sub2, $body2, $opts2] = vestra_tpl_order_shipped('Maison Test', 'O11111', '', true);
$t('tasiyici satiri YOK',               !str_contains($body2, 'Carrier:'));
$t('takip satiri YOK',                  !str_contains($body2, 'Tracking number'));
$t('dugme panele doner',                ($opts2['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders');
$t('button_alt uretilmez',              !isset($opts2['button_alt']));
$t('kutuda tek satir (ref)',            count($opts2['rows']) === 1);
/* Tasiyici cozulmemis ama numara varsa: numara yazilir, baglanti YAZILMAZ. */
[, $body3, $opts3] = vestra_tpl_order_shipped('Maison Test', 'O11111', '1234567890', true, $s5);
$t('cozulmemis tasiyicida numara var',  str_contains($body3, 'Tracking number: 1234567890'));
$t('cozulmemis tasiyicida link YOK',    !str_contains($body3, 'Track it here'));
$t('dugme panele doner (link yok)',     ($opts3['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders');

echo "-- 10. Kablolama: uc yol da AYNI cozucuyu cagiriyor --\n";
/* Bir yol kendi basina tasiyici cozerse, siparis sayfasi ile mektup iki ayri
   sey yazabilir -- bu depoda KURAL 5f'in uc katmani ayni sinifta. */
$adminSrc  = file_get_contents($root.'/admin.php');
$sellerSrc = file_get_contents($root.'/seller.php');
$t('orders.php kartinda vestra_order_shipment', str_contains($src, '$shp = vestra_order_shipment($statusEntry)'));
$t('admin mektubu shipment gonderiyor',        preg_match('/vestra_tpl_order_shipped\([^;]*vestra_order_shipment\(/s', $adminSrc) === 1);
$t('seller mektubu shipment gonderiyor',       str_contains($sellerSrc, '$shpNow = vestra_order_shipment(')
                                               && preg_match('/vestra_tpl_order_shipped\([^;]*\$shpNow\)/s', $sellerSrc) === 1);
$t('admin formunda tasiyici secici',           str_contains($adminSrc, 'name="ship_carrier"'));
$t('admin formunda servis alani',              str_contains($adminSrc, 'name="ship_service"'));
$t('satici formunda tasiyici secici',          str_contains($src, 'name="ship_carrier"'));
/* Alan formda YOKSA kayitli deger korunmali: siparis listesindeki kucuk durum
   formu tasiyici tasimiyor ve oradan durum degistirmek onu silmemeli. */
$t('admin: array_key_exists ile korunuyor',    str_contains($adminSrc, "array_key_exists('ship_carrier',\$_POST)"));
$t('seller: array_key_exists ile korunuyor',   str_contains($sellerSrc, "array_key_exists('ship_carrier', \$_POST)"));

echo "-- 11. Sozluk: yeni t() anahtarlari 8 dilde de var (KURAL 10) --\n";
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lg) {
    $d = include $root.'/inc/lang/'.$lg.'.php';
    $t("{$lg}: Carrier + Service",  isset($d['Carrier'], $d['Service']) && trim($d['Carrier']) !== '' && trim($d['Service']) !== '');
}

echo "\n";
echo $fail === 0 ? "TAMAM — {$ok} iddia gecti\n" : "KALDI — {$fail} hata / {$ok} gecti\n";
exit($fail === 0 ? 0 : 1);
