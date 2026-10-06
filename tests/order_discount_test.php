<?php
/* SİPARİŞE İNDİRİM — PANEL YOLU (operatör, 19 Eyl 2026: *"yeni yaptığımız
 * siparişlere yüzde 5 welcome indirimi uygula"*).
 *
 * Yazıcının kendisi `tests/welcome_discount_test.php`'de ölçülüyor
 * (`vestra_order_set_discount`, yüzdeden türeyen tutar, ödenmiş siparişte red).
 * BU DOSYA panelin o yazıcıya doğru bağlandığını tutuyor — ve bu ayrı bir
 * soru: iki oturum aynı gün aynı olguya iki ayrı yazıcı yazdı, biri YÜZDE
 * biri TUTAR alıyordu. Tekilleştirildi; panel formu TUTAR göndermeye devam
 * etseydi "%12,50 indirim" diye okunurdu — rakam doğru, anlamı bambaşka.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';
require_once $root . '/inc/orders.php';
require_once $root . '/inc/invoice.php';
require_once $root . '/inc/vouchers.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents(__DIR__ . '/../' . $f);

echo "== 1. TEK yazıcı ==\n";
$o = $src('vestra/inc/orders.php');
$t('vestra_order_set_discount tek tanımlı', substr_count($o, 'function vestra_order_set_discount') === 1);
$t('imza YÜZDE alıyor',                     str_contains($o, 'function vestra_order_set_discount(string $ref, float $pct'));
$t('tutar voucher_discount ile türüyor',    str_contains($o, "voucher_discount(['type' => 'percent'"));

echo "\n== 2. Panel AYNI yazıcıyı çağırıyor, ikinci bir mantık yok ==\n";
$adm = $src('vestra/admin.php');
$t('panel handler var',          str_contains($adm, "\$act==='order_discount'"));
$t('panel yazıcıyı çağırıyor',   str_contains($adm, 'vestra_order_set_discount($ref, vestra_price_input('));
$t('panel YÜZDE gönderiyor',     str_contains($adm, "\$_POST['discount_pct']"));
$t('form alanı da yüzde',        str_contains($adm, 'name="discount_pct"'));
$t('kod alanı boş bırakılabilir', str_contains($adm, 'boş = otomatik'));
$t('başarı/hata bandı var',      str_contains($adm, "\$msg==='disc_saved'") && str_contains($adm, "\$msg==='disc_fail'"));
/* Para girişi ham (float) ile okunmamalı: "12,5" sessizce 12 olur. */
$t('para girişi vestra_price_input', !preg_match('/order_discount.{0,500}\(float\)\$_POST/s', $adm));
/* Panelde indirim mantığı YENİDEN yazılmamalı: yüzde × mal toplamı hesabı
   yalnız GÖSTERİM için (kayıtta tutar duruyor), yazma yolunda değil. */
$h = substr($adm, strpos($adm, "\$act==='order_discount'"), 700);
/* ÖLÇÜM TUZAĞI (yaşandı): tarama YORUMLARI da okuyor ve handler'ın kendi
   açıklama satırında `voucher_discount()` geçiyor — doğru çalışan kod kırmızı
   döndü. Yorumlar silinmedi (bu depoda yorum da kayıttır); ölçüm daraltıldı:
   yorumsuz metinde bir ÇAĞRI aranıyor. */
$hCode = trim(preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $h));
$t('handler tutarı kendi hesaplamıyor', !str_contains($hCode, 'voucher_discount('));

echo "\n== 3. İş akışında da TEK kip ==\n";
$wf = $src('.github/workflows/seller-products.yml');
$t('order_discount kipi var',      str_contains($wf, "admin_mode == 'order_discount'"));
$t('ikinci bir discount kipi YOK', !str_contains($wf, "admin_mode == 'discount'"));
$t('iş akışı da aynı yazıcıyı çağırıyor', str_contains($wf, 'vestra_order_set_discount('));

echo "\n== 4. Panel formu GERÇEKTEN çiziliyor (kaynak taraması değil) ==\n";
/* admin.php kum havuzunda çizdiriliyor: `php -l` bu depoda iki çalışma-zamanı
   hatasını geçirmişti (olmayan `csrf_field()`, olmayan `vestra_order_status()`). */
$file = vestra_data_dir() . '/orders.csv';
$bak  = is_file($file) ? file_get_contents($file) : null;
$REF  = 'DISCPANEL-' . strtoupper(bin2hex(random_bytes(3)));
$head = ['timestamp','ref','email','name','company','country','items','subtotal','shipping','shipping_label',
         'discount','voucher_code','commission','payout','total','currency','notes'];
$row  = [date('c'), $REF, 'a@example.com', 'Test', 'Test GmbH', 'Germany',
         '10x TESTSKU @25.00', '250.00', '20.00', 'Shipping (EU tariff)', '', '', '0.00', '250.00', '270.00', 'EUR', ''];
try {
    $fh = fopen($file, 'w');
    fputcsv($fh, $head, ',', '"', '\\');
    fputcsv($fh, $row,  ',', '"', '\\');
    fclose($fh);

    $cmd = 'php -d error_reporting=E_ALL -d display_errors=1 -r ' . escapeshellarg(
        '$_SERVER["REQUEST_METHOD"]="GET"; $_GET=["tab"=>"orders","view"=>"'.$REF.'"];'
        . '$_SERVER["REQUEST_URI"]="/admin?tab=orders&view='.$REF.'";'
        . 'define("VESTRA_ADMIN_TEST", true); $_SESSION=[]; session_id("disc-probe");'
        . 'ob_start(); include "'.$root.'/admin.php"; $h=ob_get_clean(); echo $h;') . ' 2>&1';
    $html = (string)shell_exec($cmd);
    $drawn = str_contains($html, 'name="discount_pct"');
    $t('form çiziliyor (ya da panel giriş istiyor)', $drawn || str_contains($html, 'admin_pass') || str_contains($html, 'password'));
    if ($drawn) {
        $t('CSRF alanı var',  preg_match('/name="discount_pct".{0,4000}/s', $html) === 1 && str_contains($html, '_csrf'));
        $t('PHP uyarısı yok', !preg_match('/\b(Warning|Notice|Fatal error)\b/', $html));
    } else {
        echo "  ..   panel girişi kapalı (inc/config.php yok) — çizim atlandı\n";
    }
} finally {
    if ($bak !== null) file_put_contents($file, $bak); else @unlink($file);
}

echo "\n".($fail ? "SONUÇ: {$fail} HATA, {$ok} ok\n" : "SONUÇ: hepsi geçti ({$ok})\n");
exit($fail ? 1 : 0);
