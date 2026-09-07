<?php
/* DROPSHIP ODEMESI DURDURULDU — ama kurulu kaldı
 * (operatör, 7 Eyl 2026: *"dropshipping ödemesini şu an için kaldır ancak
 * yeniden başlamak için kurulu olsun"*).
 *
 * Tutulan ilkeler:
 *   - KAPI SUNUCUDA ve TEK: `dropship_create_order()`. Düğmeyi gizlemek görünüm
 *     tercihi; site formu da ortak API'si de aynı fonksiyondan geçiyor.
 *   - VARSAYILAN KAPALI: ayar dosyası yoksa ödeme durur. Tersi, dosya kaybolunca
 *     sessizce para almaya başlamak olurdu.
 *   - HİÇBİR ŞEY SİLİNMEDİ: ürünlerin dropship bloğu, fiyat türetme, bölge
 *     tablosu, `a=list`/`a=stock` ve geçmiş siparişler yerinde — "yeniden
 *     başlamak için kurulu" tam olarak bu.
 *   - Çalışmayan bir düğme gösterilmez (KURAL 4'ün karşı teklif alanı dersi):
 *     ürün sayfasındaki bağlantı ve satın alma formu ödeme kapalıyken çizilmez.
 *   - Ortak, durumu SİPARİŞ ANINDA öğrenmez: list/stock `ordering_paused`
 *     taşıyor.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';
require_once $root . '/inc/dropship.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents($root . '/' . $f);

$file = vestra_dropship_settings_file();
$bak  = is_file($file) ? file_get_contents($file) : null;

/* vestra_dropship_payments_enabled() süreç içinde önbellekli (static): tek
   süreçte açıp kapatıp ölçmek, ölçüm aracının kendi gürültüsünü ölçmek olurdu.
   Dosya davranışı bu yüzden AYRI PHP süreçlerinde sınanıyor — returns_policy
   testinin dil başına süreç açmasıyla aynı sebep. */
$probe = function (?string $json) use ($root, $file): string {
    if ($json === null) @unlink($file); else file_put_contents($file, $json);
    $code = 'require '.var_export($root.'/inc/products.php', true).';'
          . 'require '.var_export($root.'/inc/dropship.php', true).';'
          . 'echo vestra_dropship_payments_enabled() ? "ON" : "OFF";';
    return (string)shell_exec('php -d error_reporting=0 -r ' . escapeshellarg($code) . ' 2>/dev/null');
};

try {
    echo "== 1. Varsayılan KAPALI ==\n";
    $t('ayar dosyası yokken ödeme KAPALI',        $probe(null) === 'OFF');
    $t('boş dosyada da KAPALI',                   $probe('{}') === 'OFF');
    $t('bozuk JSON da KAPALI (sessizce açılmaz)', $probe('{bozuk') === 'OFF');
    $t('payments_enabled=false KAPALI',           $probe('{"payments_enabled":false}') === 'OFF');
    $t('payments_enabled=true AÇIK',              $probe('{"payments_enabled":true}') === 'ON');

    echo "\n== 2. Anahtar yazılıp geri okunuyor ==\n";
    @unlink($file);
    $t('aç: true döner',      vestra_dropship_set_payments(true) === true);
    $t('dosyaya yazıldı',     ($j = json_decode((string)file_get_contents($file), true)) && $j['payments_enabled'] === true);
    $t('kim/ne zaman damgası', ($j['payments_changed_by'] ?? '') === 'operator' && ($j['payments_changed_at'] ?? '') !== '');
    $t('kapat: true döner',   vestra_dropship_set_payments(false) === true);
    $t('kapalı yazıldı',      (json_decode((string)file_get_contents($file), true)['payments_enabled'] ?? null) === false);
    $t('süreçler arası okunuyor', $probe(null) === 'OFF');   // dosya silinir, varsayılan yine kapalı
} finally {
    if ($bak === null) @unlink($file); else file_put_contents($file, $bak);
}

echo "\n== 3. Kapı: tek yer, sunucuda, en başta ==\n";
$ds = $src('inc/dropship.php');
$t('create_order ödeme kapısını soruyor',  str_contains($ds, "if (!vestra_dropship_payments_enabled()) {"));
$t('cevap 503 + payments_paused',          str_contains($ds, "'error' => 'payments_paused'") && str_contains($ds, "'status' => 503"));
/* Kapı, ürün/renk/beden kontrollerinden ÖNCE: kapalıyken geçerli bir siparişin
   neden reddedildiği tek bir sebep olsun. */
$t('kapı en başta (bölge çözümünden önce)', strpos($ds, 'payments_paused') < strpos($ds, '$zone = vestra_dropship_zone($zone);'));
/* İki çağıran da aynı fonksiyondan geçiyor — ikinci bir kapı yazılmamış. */
$t('site formu create_order çağırıyor',    str_contains($src('dropship-checkout.php'), 'dropship_create_order('));
$t('ortak API create_order çağırıyor',     str_contains($src('api/dropship.php'), 'dropship_create_order('));
/* API bayrağı OKUYOR (list/stock durumu bildiriyor) ama kendi REDDİNİ yazmıyor:
   ikinci bir kapı, iki kapının bir gün ayrışması demek. Reddi tek yer veriyor. */
$t('API kendi reddini yazmıyor',
   preg_match('~if\s*\(\s*!\s*vestra_dropship_payments_enabled\(\)~', $src('api/dropship.php')) === 0);

echo "\n== 4. Çalışmayan düğme gösterilmiyor ==\n";
$t('ürün sayfası düğmesi kapıya bağlı',    str_contains($src('product.php'), 'vestra_dropship_payments_enabled() && vestra_dropship_enabled($p)'));
$t('/dropship formu yerine durdu notu',    str_contains($src('dropship.php'), 'if (!vestra_dropship_payments_enabled()): ?>')
                                        && str_contains($src('dropship.php'), 'Single-piece ordering is paused right now.'));
$t('anlatım sayfası da söylüyor',          str_contains($src('dropshipping.php'), 'Single-piece ordering is paused right now.'));
/* Metin sözlükte: eksik anahtar sessizce İngilizceye düşerdi. */
$de = require $root . '/inc/lang/de.php';
foreach (['Single-piece ordering is paused right now.',
          'Dropshipping is being reworked and card payment for single pieces is switched off for the moment. Wholesale ordering with the usual minimums is unaffected.',
          'Go to the catalogue'] as $k) {
    $t('sözlükte: '.mb_substr($k, 0, 34), isset($de[$k]));
}

echo "\n== 5. Kurulu kaldı: hiçbir şey silinmedi ==\n";
$t('ürünün dropship bloğu çözümleyicisi duruyor', function_exists('vestra_dropship_of'));
$t('fiyat türetme duruyor',                       function_exists('vestra_dropship_base_price'));
$t('bölge/ücret tablosu duruyor',                 count(vestra_dropship_zones()) > 1);
$t('sipariş kaydı okuyucusu duruyor',             function_exists('dropship_all'));
$t('API list ucu duruyor',                        str_contains($src('api/dropship.php'), "\$action === 'list'"));
$t('API stock ucu duruyor',                       str_contains($src('api/dropship.php'), "\$action === 'stock'"));
$t('panel sekmesi duruyor',                       str_contains($src('admin.php'), "elseif(\$tab==='dropship')"));
/* Bölme yasağı (ayakkabı) ödeme anahtarından BAĞIMSIZ kalmalı: ikisi ayrı karar. */
$t('ayakkabı yasağı yerinde',                     vestra_dropship_excluded_sections() === ['footwear']);

echo "\n== 6. Ortak siparişten ÖNCE haberdar ==\n";
$api = $src('api/dropship.php');
$t('list ordering_paused taşıyor',   str_contains($api, "'ordering_paused' => !vestra_dropship_payments_enabled()"));
$t('stock ordering_paused taşıyor',  substr_count($api, 'ordering_paused') >= 2);
$t('API başlığı durumu yazıyor',     str_contains($api, 'ORDERING PAUSED'));

echo "\n== 7. Operatör kendi geri açabiliyor ==\n";
$adm = $src('admin.php');
$t('panelde anahtar formu var',      str_contains($adm, "value=\"dropship_payments\""));
$t('işleyici yazıp geri okuyor',     str_contains($adm, "if(\$act==='dropship_payments')") && str_contains($adm, 'vestra_dropship_set_payments($dsWant)'));
$t('yazılamazsa KIRMIZI uyarı',      str_contains($adm, "elseif(\$msg==='ds_pay_fail')"));
$t('durum panelde yazılı',           str_contains($adm, 'Single-piece payment is PAUSED'));
$t('geri açma düğmesi var',          str_contains($adm, 'Turn payment back on'));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
