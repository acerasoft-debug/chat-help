<?php
/* PLATFORM KUNYESININ TEK YAZICISI (19 Eyl 2026, KURAL 5j/5r).
 *
 * Govde admin.php'nin save_platform_billing handler'indan cikarildi:
 * operatorun verdigi SEPA hesabi ancak panelden girilebiliyordu ve girilmemisti.
 * Is akisindan yazmak icin handler'i KOPYALAMAK ikinci bir yazici demekti; bunun
 * yerine tek fonksiyon: vestra_platform_seller_save(). Bu test o fonksiyonu KUM
 * HAVUZUNDA gercekten kosturuyor (VESTRA_DATA_DIR gecici dizine yonlendirilmis --
 * gercek data/platform_seller.json'a DOKUNMUYOR) ve panelin cagirdigini kaynaktan
 * dogruluyor. Numaralar SENTETIK (belgelerde ornek olarak gecen IBAN'lar;
 * tests/no_real_iban_test.php izin listesi).
 */
$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra-pbank-'.getmypid();
@mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/invoice.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };
$raw = fn() => json_decode((string)@file_get_contents($sand.'/data/platform_seller.json'), true) ?: [];

echo "== 1. Gecersiz IBAN -> HICBIR alan yazilmaz ==\n";
$r = vestra_platform_seller_save(['bank_iban' => 'DE00370400440532013000', 'bank_eur_bic' => 'COBADEFF', 'bank_eur_name' => 'Test Bank']);
$t('ok=false', empty($r['ok']));
$t("error=iban_bad", ($r['error'] ?? '') === 'iban_bad');
$t('dosya HIC olusmadi (BIC ve ad da yazilmadi)', !is_file($sand.'/data/platform_seller.json'));

echo "\n== 2. Gecerli IBAN -> normalize + yaz + geri oku ==\n";
$r = vestra_platform_seller_save(['bank_iban' => ' de89 3704 0044 0532 0130 00 ', 'bank_eur_bic' => ' coba deff ', 'bank_eur_name' => 'Commerzbank AG', 'bank_holder' => 'Acerasoft LLC']);
$t('ok=true', !empty($r['ok']));
$d = $raw();
$t('IBAN normalize edildi (bosluksuz, buyuk harf)', ($d['bank_iban'] ?? '') === 'DE89370400440532013000');
$t('BIC normalize edildi', ($d['bank_eur_bic'] ?? '') === 'COBADEFF');
$t('banka adi yazildi', ($d['bank_eur_name'] ?? '') === 'Commerzbank AG');
$t('saved listesi yazilanlari sayiyor', array_keys($r['saved']) === ['bank_holder','bank_iban','bank_eur_bic','bank_eur_name']);
$t('vestra_platform_seller() yeni degeri okuyor', (vestra_platform_seller()['bank_iban'] ?? '') === 'DE89370400440532013000');

echo "\n== 3. Bos alan mevcut degeri SILMEZ; bilinmeyen anahtar yazilmaz ==\n";
$r = vestra_platform_seller_save(['bank_iban' => '', 'bank_eur_bic' => '', 'website' => 'vestrasales.com', 'evil' => 'x']);
$d = $raw();
$t('IBAN duruyor', ($d['bank_iban'] ?? '') === 'DE89370400440532013000');
$t('EUR BIC duruyor', ($d['bank_eur_bic'] ?? '') === 'COBADEFF');
$t('bilinmeyen anahtar dosyaya girmedi', !array_key_exists('evil', $d));

echo "\n== 4. Ikinci gecersiz IBAN mevcut GECERLI kaydi bozmaz ==\n";
$r = vestra_platform_seller_save(['bank_iban' => 'DE11370400440532013000', 'bank_eur_name' => 'Other Bank']);
$d = $raw();
$t('ret', empty($r['ok']));
$t('eski IBAN yerinde', ($d['bank_iban'] ?? '') === 'DE89370400440532013000');
$t('eski banka adi yerinde (yeni ad yazilmadi)', ($d['bank_eur_name'] ?? '') === 'Commerzbank AG');

echo "\n== 5. Yazilan kayit EUR odeme kutusunu ACIYOR (cizicinin okudugu fonksiyon) ==\n";
/* ABD hesabi da dolu bir kunye: EUR kutusu ad/BIC'i yalniz bank_eur_* 'dan basar (KURAL 5j). */
vestra_platform_seller_save(['bank_account' => '123456789012', 'bank_routing' => '021000021', 'bank_name' => 'US Bank', 'bank_bic' => 'CHASUS33']);
$plat = vestra_platform_seller();
$eur = vestra_payment_rails($plat, 'EUR');
$usd = vestra_payment_rails($plat, 'USD');
$t('EUR kutusu dolu', count($eur) >= 3);
$t('EUR kutusunda IBAN satiri var', (bool)preg_grep('/IBAN/i', array_map('strval', $eur)));
$t('EUR kutusunda ABD bankasi YOK', !preg_grep('/US Bank|CHASUS33/', array_map('strval', $eur)));
$t('USD kutusunda Alman bankasi YOK', !preg_grep('/Commerzbank|COBADEFF/', array_map('strval', $usd)));
$t('KURAL 5r: EUR kesimi artik GECER', vestra_invoice_payment_gap(null, 'EUR', false) === '');

echo "\n== 6. Kablolama: panel ve is akisi AYNI yaziciyi cagiriyor ==\n";
$a = (string)file_get_contents($root.'/vestra/admin.php');
$w = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$hb = substr($a, strpos($a, "if(\$act==='save_platform_billing'){"), 1200);
$t('admin.php handler yaziciyi cagiriyor', str_contains($hb, 'vestra_platform_seller_save($_POST)'));
$t('admin.php handler kendi file_put_contents tasimiyor', !str_contains($hb, 'file_put_contents'));
$t('admin.php iban_bad -> platform_billing_iban_bad', str_contains($hb, "platform_billing_iban_bad"));
$ws = substr($w, strpos($w, "admin_mode == 'platform_bank'"), 8000);
$t('is akisi adimi var', $ws !== '' && str_contains($ws, 'vestra_platform_seller_save($in)'));
$t('is akisi kuru kosuda YAZMIYOR', str_contains($ws, "if (!\$APPLY) {") && strpos($ws, "if (!\$APPLY) {") < strpos($ws, 'vestra_platform_seller_save($in)'));
/* Iddia olguya bagli, yazima degil: maske ulke kodu + hane sayisi basiyor ve
   $in['bank_iban'] / $after['bank_iban'] deger okuyan hicbir echo satiri
   $ibanMask'ten GECMEDEN basmiyor. Iki kez yanlis yazdim: once kapanis
   tirnagini aradim (yazim), sonra lookahead'i ters kurdum (ibanMask degerden
   ONCE geliyor) -- ikisi de dogru calisan kodu kirmizi gosterdi. Satir satir. */
$leak = false;
foreach (explode("\n", $ws) as $ln) {
  if (str_contains($ln, 'echo') && preg_match('/\$(in|after)\[.bank_iban.\]/', $ln) && !str_contains($ln, 'ibanMask')) $leak = true;
}
$t('is akisi IBAN numarasini BASMIYOR (maske: ulke+hane)',
   str_contains($ws, "substr(\$n,0,2).', '.strlen(\$n).' hane") && !$leak);
$t('is akisi deploy inmemis kontrolu tasiyor', str_contains($ws, "function_exists('vestra_platform_seller_save')"));
$t('is akisi kutuyu cizicinin fonksiyonundan sayiyor', str_contains($ws, "vestra_payment_rails(\$acc, \$c)"));

/* Kum havuzu temizligi */
@unlink($sand.'/data/platform_seller.json'); @rmdir($sand.'/data'); @rmdir($sand);
echo "\n-- $ok ok, $fail HATA --\n";
exit($fail === 0 ? 0 : 1);
