<?php
/* SİPARİŞE İNDİRİM (operatör, 19 Eyl 2026: *"yeni yaptığımız siparişlere yüzde
 * 5 welcome indirimi uygula"*).
 *
 * `discount` ve `voucher_code` sütunları kasadan beri vardı; fatura, sipariş
 * PDF'i ve panel üçü de okuyordu — eksik olan tek şey YAZICIYDI. Tutulan
 * ilkeler, navlun yazıcısının kardeşi:
 *   - İKİ ALAN + TOPLAM birlikte (KURAL 5f'in üç-katman dersi).
 *   - MAL TOPLAMI faturanın okuduğu AYNI fonksiyondan (`vestra_order_lines`).
 *   - FATURASI KESİLMİŞ sipariş varsayılan RED; `$allowInvoiced` ile yazar ve
 *     `must_redraft` döner (KURAL 5f: aynı numarayla yeniden çizim).
 *   - Kupon KAYDINA (vouchers.json) DOKUNMAZ — kodu yakan yol iş akışı.
 *   - 0 yazmak indirimi kaldırır; 0 dışında KOD zorunlu.
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

$file = vestra_data_dir() . '/orders.csv';
$bak  = is_file($file) ? file_get_contents($file) : null;
$stF  = vestra_data_dir() . '/order_statuses.json';
$stB  = is_file($stF) ? file_get_contents($stF) : null;
$vcF  = vestra_data_dir() . '/vouchers.json';
$vcB  = is_file($vcF) ? file_get_contents($vcF) : null;

$REF  = 'DISCTEST-' . strtoupper(bin2hex(random_bytes(3)));
$head = ['timestamp','ref','email','name','company','country','items','subtotal','shipping','shipping_label',
         'discount','voucher_code','commission','payout','total','currency','notes'];
$row  = [date('c'), $REF, 'a@example.com', 'Test', 'Test GmbH', 'Germany',
         '10x TESTSKU @25.00', '250.00', '20.00', 'Shipping (EU tariff)', '', '', '0.00', '250.00', '270.00', 'EUR',
         'Payment: Bank transfer. Notes from the buyer.'];

try {
    $fh = fopen($file, 'w');
    fputcsv($fh, $head, ',', '"', '\\');
    fputcsv($fh, $row,  ',', '"', '\\');
    fclose($fh);

    echo "== 1. İndirim yazılıyor; TOPLAM navlunla birlikte yeniden kuruluyor ==\n";
    $r = vestra_order_set_discount($REF, 12.50, 'VES-TEST-0001');
    $t('yazma başarılı',           !isset($r['error']) && !empty($r['ok']));
    $t('mal toplamı satırlardan',  abs(($r['goods'] ?? 0) - 250.00) < 0.005);
    $t('indirim kaydedildi',       abs(($r['discount'] ?? 0) - 12.50) < 0.005);
    $t('navlun korundu',           abs(($r['shipping'] ?? 0) - 20.00) < 0.005);
    $t('TOPLAM = mal − indirim + navlun', abs(($r['total'] ?? 0) - 257.50) < 0.005);
    $t('faturası yok → redraft istemiyor', empty($r['must_redraft']));
    $back = null;
    foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $REF) { $back = $x; break; }
    $t('dosyada da öyle duruyor',  $back && abs((float)$back['discount'] - 12.50) < 0.005
                                         && abs((float)$back['total'] - 257.50) < 0.005);
    $t('kod kaydedildi',           ($back['voucher_code'] ?? '') === 'VES-TEST-0001');
    $t('operatör damgası düştü',   (vestra_read_json('order_statuses.json')[$REF]['discount_set_by'] ?? '') === 'operator');

    echo "\n== 2. Nota TEK kopya düşüyor, alıcının kendi metni korunuyor ==\n";
    $t('not kupon parçası taşıyor', str_contains((string)$back['notes'], 'VES-TEST-0001'));
    $t('alıcının metni duruyor',    str_contains((string)$back['notes'], 'Notes from the buyer'));
    $t('ödeme satırı duruyor',      str_contains((string)$back['notes'], 'Payment: Bank transfer'));
    /* İkinci uygulamada eski parça SÖKÜLÜYOR: iki "Voucher …" satırı, alıcının
       sipariş sayfasında iki ayrı indirim gibi okunurdu. */
    $r2 = vestra_order_set_discount($REF, 25.00, 'VES-TEST-0002');
    $back2 = null;
    foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $REF) { $back2 = $x; break; }
    $t('ikinci yazım TOPLAMI şişirmiyor', abs((float)$back2['total'] - 245.00) < 0.005);
    $t('eski kupon parçası söküldü',      !str_contains((string)$back2['notes'], 'VES-TEST-0001'));
    $t('yeni kupon parçası tek kopya',    substr_count((string)$back2['notes'], 'Voucher ') === 1);
    $t('alıcının metni hâlâ duruyor',     str_contains((string)$back2['notes'], 'Notes from the buyer'));

    echo "\n== 3. Sınırlar ==\n";
    $t('negatif reddediliyor',      isset(vestra_order_set_discount($REF, -1, 'X')['error']));
    $t('kodsuz indirim reddediliyor', isset(vestra_order_set_discount($REF, 10.0, '')['error']));
    $t('mal toplamını aşan reddediliyor', isset(vestra_order_set_discount($REF, 999.0, 'X')['error']));
    $t('olmayan ref reddediliyor',  isset(vestra_order_set_discount('YOKBOYLEREF', 5.0, 'X')['error']));
    $t('boş ref reddediliyor',      isset(vestra_order_set_discount('', 5.0, 'X')['error']));

    echo "\n== 4. SIFIR indirimi KALDIRIR (kod da silinir) ==\n";
    $r0 = vestra_order_set_discount($REF, 0.0);
    $t('sıfır kabul ediliyor (kod şart değil)', !isset($r0['error']));
    $back0 = null;
    foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $REF) { $back0 = $x; break; }
    $t('indirim alanı boşaldı',     trim((string)$back0['discount']) === '');
    $t('kod alanı boşaldı',         trim((string)$back0['voucher_code']) === '');
    $t('TOPLAM mal + navlun',       abs((float)$back0['total'] - 270.00) < 0.005);
    $t('kupon parçası nottan çıktı', !str_contains((string)$back0['notes'], 'Voucher '));

    echo "\n== 4b. FATURASI KESİLMİŞ siparişte varsayılan RED ==\n";
    /* DAVRANIŞSAL: kum havuzuna gerçek bir fatura meta dosyası konuyor.
       Kaynakta `if ($invoiced && ...)` görmek ölçüm değil — `if (false)`
       yapılan bir muhafaza da o satırı taşımaya devam ederdi (bu depoda
       KURAL 5r'de bir kez tam böyle bir boşluk çıktı). */
    $invDir = vestra_invoice_dir();
    @mkdir($invDir, 0775, true);
    $metaFile = $invDir.'/'.$REF.'__test.json';
    file_put_contents($metaFile, json_encode(['ref' => $REF, 'number' => 'INV-TEST-0001',
        'seller_key' => 'vestra', 'total' => 270.0, 'currency' => 'EUR']));
    $t('sonda faturayı gerçekten görüyor', count(vestra_invoices_for_ref($REF)) === 1);
    $rInv = vestra_order_set_discount($REF, 5.0, 'VES-TEST-0009');
    $t('faturalı sipariş REDDEDİLİYOR', isset($rInv['error']));
    $t('ret gerekçesi KURAL 5f\'i söylüyor', str_contains((string)($rInv['error'] ?? ''), 'allow_invoiced'));
    $backInv = null;
    foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $REF) { $backInv = $x; break; }
    $t('kayıt DEĞİŞMEDİ',              abs((float)$backInv['total'] - 270.00) < 0.005);
    $rAllow = vestra_order_set_discount($REF, 5.0, 'VES-TEST-0009', true);
    $t('allow_invoiced ile yazıyor',   !isset($rAllow['error']));
    $t('must_redraft dönüyor',         !empty($rAllow['must_redraft']));
    @unlink($metaFile);
    vestra_order_set_discount($REF, 0.0);

    echo "\n== 5. Kupon kaydına DOKUNMUYOR ==\n";
    $vBefore = voucher_all();
    vestra_order_set_discount($REF, 10.0, 'VES-TEST-0003');
    $t('vouchers.json değişmedi',   voucher_all() === $vBefore);
    $ordersSrc = $src('vestra/inc/orders.php');
    $fn = substr($ordersSrc, strpos($ordersSrc, 'function vestra_order_set_discount'));
    $fn = substr($fn, 0, strpos($fn, "\n}\n") + 2);
    /* ÖLÇÜM TUZAĞI (yaşandı): düz `voucher_` araması SÜTUN ADINI
       (`voucher_code`) yakalıyor ve doğru çalışan kodu kırmızı gösteriyordu —
       `class="msgtick` önekinin `msgtickdefs`'i yakalamasıyla aynı sınıf.
       Ölçüt artık gerçek FONKSİYON çağrıları. */
    $t('yazıcı kupon fonksiyonu çağırmıyor',
        !preg_match('/voucher_(create|redeem|all|validate|save|find|discount)\s*\(/', $fn));

    echo "\n== 6. Kablolama: panel ve iş akışı AYNI yazıcıyı çağırıyor ==\n";
    $adm = $src('vestra/admin.php');
    $t('panel handler var',        str_contains($adm, "\$act==='order_discount'"));
    $t('panel AYNI yazıcı',        str_contains($adm, 'vestra_order_set_discount($ref, vestra_price_input('));
    $t('panel formu var',          str_contains($adm, 'name="_action" value="order_discount"'));
    $t('panel kod alanı var',      str_contains($adm, 'name="voucher_code"'));
    $t('başarı/hata bandı var',    str_contains($adm, "\$msg==='disc_saved'") && str_contains($adm, "\$msg==='disc_fail'"));
    /* Para girişi ham (float) ile okunmamalı: "12,50" sessizce 12.00 olur. */
    $t('para girişi vestra_price_input', !preg_match('/order_discount.{0,400}\(float\)\$_POST/s', $adm));

    $wf = $src('.github/workflows/seller-products.yml');
    $t('iş akışı kipi var',        str_contains($wf, "github.event.inputs.admin_mode == 'discount'"));
    $t('iş akışı AYNI yazıcı',     str_contains($wf, 'vestra_order_set_discount($ref, (float)$amount, $code, $allowInv)'));
    $t('kuru koşu varsayılan',     str_contains($wf, 'KURU KOSU: hicbir sey yazilmadi'));
    /* Kupon ANCAK satır diske indikten SONRA yakılıyor (order.php'nin dersi). */
    $t('redeem yazmadan SONRA',    strpos($wf, 'vestra_order_set_discount($ref, (float)$amount') < strpos($wf, 'voucher_redeem($code, $ref, $email'));
    $t('adres maskeli basılıyor',  str_contains($wf, '$mask($email)'));
    $t('adres açık girdi DEĞİL',   !str_contains($wf, 'DSC_EMAIL'));
    $t('kampanya adı welcome_run ile aynı kalıp', str_contains($wf, "\$campaign = 'welcome'.rtrim(rtrim(number_format(\$pct, 2, '.', ''), '0'), '.');"));
    $t('mevcut kod önce aranıyor', str_contains($wf, "if (\$code === '' && \$wantCampaign)"));

    echo "\n== 7. Fatura yükü indirimi GÖRÜYOR (alan zaten okunuyordu) ==\n";
    $invSrc = $src('vestra/inc/invoice.php');
    /* İddia ÖNCE yanlış değişkene bakıyordu (`$assoc`); çiziciler siparişi
       `$order` adıyla okuyor. Kod doğruydu, ölçü yanlıştı. */
    $t('fatura discount okuyor',     str_contains($invSrc, "(float)(\$order['discount'] ?? 0)"));
    $t('fatura Voucher satırı basıyor', str_contains($invSrc, "'Voucher'.(\$vcode !== '' ? ' '.\$vcode : '')"));
    $t('belge toplamı indirimi düşüyor', str_contains($invSrc, 'max(0, $goodsTotal - $discount) + $shipping'));
} finally {
    if ($bak !== null) file_put_contents($file, $bak); else @unlink($file);
    if ($stB !== null) file_put_contents($stF, $stB);
    if ($vcB !== null) file_put_contents($vcF, $vcB); else @unlink($vcF);
}

echo "\n".($fail ? "SONUÇ: {$fail} HATA, {$ok} ok\n" : "SONUÇ: hepsi geçti ({$ok})\n");
exit($fail ? 1 : 0);
