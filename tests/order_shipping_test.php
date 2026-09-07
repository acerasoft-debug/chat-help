<?php
/* SIPARIŞE NAVLUN (operatör, 7 Eyl 2026: *"kargo bölümü yok kargo eklemek
 * gerekiyor"* — VES-6B53D265).
 *
 * Tutulan ilkeler:
 *   - İKİ ALAN BİRLİKTE: `shipping` ve `total`. Sipariş satırının toplamı
 *     navlunu içeriyor; yalnız birini yazmak alıcının sipariş sayfası ile
 *     faturasını iki ayrı rakama böler (KURAL 5f'in üç-katman dersi).
 *   - MAL TOPLAMI faturanın okuduğu AYNI fonksiyondan (`vestra_order_lines`).
 *   - FATURASI KESİLMİŞ sipariş REDDEDİLİR: belge alıcının elinde, numara yanmış.
 *   - Yazma GERİ OKUNARAK doğrulanır; yazılamayanı "kaydettim" diye raporlamak
 *     operatöre olmayan bir kaydı doğru sandırır (KURAL 5c).
 *   - Tutar siparişin KENDİ biriminde saklanır — çevrim tek yerde (KURAL 5i).
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';
require_once $root . '/inc/orders.php';
require_once $root . '/inc/invoice.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents(__DIR__ . '/../' . $f);

$file = vestra_data_dir() . '/orders.csv';
$bak  = is_file($file) ? file_get_contents($file) : null;
$stF  = vestra_data_dir() . '/order_statuses.json';
$stB  = is_file($stF) ? file_get_contents($stF) : null;

/* Gerçek katalogdan bir SKU: satırın mal toplamı ürün fiyatından değil,
   siparişin kendi birim fiyatından hesaplanıyor (vestra_order_lines). */
$REF = 'SHIPTEST-' . strtoupper(bin2hex(random_bytes(3)));
$head = ['timestamp','ref','email','name','company','items','subtotal','shipping','shipping_label',
         'discount','commission','payout','total','currency','notes'];
$row  = [date('c'), $REF, 'a@example.com', 'Test', 'Test GmbH',
         '10x TESTSKU @25.00', '250.00', '0.00', '', '0.00', '0.00', '250.00', '250.00', 'EUR', ''];

try {
    $fh = fopen($file, 'w');
    fputcsv($fh, $head, ',', '"', '\\');
    fputcsv($fh, $row,  ',', '"', '\\');
    fclose($fh);

    echo "== 1. Navlun yazılıyor; TOPLAM da güncelleniyor ==\n";
    $r = vestra_order_set_shipping($REF, 86.04, 'Air freight');
    $t('yazma başarılı',            !isset($r['error']) && !empty($r['ok']));
    $t('mal toplamı satırlardan',   abs(($r['goods'] ?? 0) - 250.00) < 0.005);
    $t('navlun kaydedildi',         abs(($r['shipping'] ?? 0) - 86.04) < 0.005);
    $t('TOPLAM = mal + navlun',     abs(($r['total'] ?? 0) - 336.04) < 0.005);
    $back = null;
    foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $REF) { $back = $x; break; }
    $t('dosyada da öyle duruyor',   $back && abs((float)$back['shipping'] - 86.04) < 0.005
                                          && abs((float)$back['total'] - 336.04) < 0.005);
    $t('etiket kaydedildi',         ($back['shipping_label'] ?? '') === 'Air freight');
    $t('operatör damgası düştü',    (vestra_read_json('order_statuses.json')[$REF]['shipping_set_by'] ?? '') === 'operator');

    echo "\n== 2. İkinci yazım TOPLAMI ŞİŞİRMEZ ==\n";
    /* Navlun ÜSTÜNE eklenmiyor, YERİNE yazılıyor: toplam her seferinde
       mal + navlun olarak yeniden kuruluyor. Aksi hâlde iki düzeltme
       siparişi sessizce iki kat navlunla bırakırdı. */
    $r2 = vestra_order_set_shipping($REF, 50.00, '');
    $t('navlun değişti',            abs(($r2['shipping'] ?? 0) - 50.00) < 0.005);
    $t('toplam yeniden kuruldu',    abs(($r2['total'] ?? 0) - 300.00) < 0.005);
    $t('etiket temizlenebiliyor',   ($r2['label'] ?? 'x') === '');

    echo "\n== 3. Sıfır ve geçersiz ==\n";
    $r3 = vestra_order_set_shipping($REF, 0.0, '');
    $t('sıfıra dönebiliyor',        !isset($r3['error']) && abs(($r3['total'] ?? 0) - 250.00) < 0.005);
    $t('negatif REDDEDİLİR',        isset(vestra_order_set_shipping($REF, -5.0, '')['error']));
    $t('olmayan sipariş REDDEDİLİR', isset(vestra_order_set_shipping('YOK-BOYLE-REF', 10.0, '')['error']));
    $t('ref temizleniyor (yol geçişi yok)',
       isset(vestra_order_set_shipping('../../etc/passwd', 10.0, '')['error']));

    echo "\n== 3b. Teslimat adresi (kargo yeri) ==\n";
    /* Adres siparişin notlarında `Deliver to: …` parçası olarak duruyor ve
       faturayı oradan besliyor; ekran GÖSTERİYORDU ama girecek yer yoktu. */
    $a1 = vestra_order_set_delivery($REF, 'Unit 5, 12 Kwun Tong Road, Kowloon, Hong Kong');
    $t('adres yazıldı',              !isset($a1['error']) && !empty($a1['ok']));
    $t('FATURA bu adresi görüyor',   ($a1['on_invoice'] ?? '') === 'Unit 5, 12 Kwun Tong Road, Kowloon, Hong Kong');
    /* Notların gerisi korunuyor: sipariş kaydından bilgi silmek yok. */
    $r4 = vestra_order_set_shipping($REF, 0.0, '');   // satırı tazele
    $row2 = null;
    foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $REF) { $row2 = $x; break; }
    $t('notlarda tek kopya var',     substr_count((string)($row2['notes'] ?? ''), 'Deliver to:') === 1);
    $a2 = vestra_order_set_delivery($REF, 'Rue Neuve 1, 1000 Brussels, Belgium');
    foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === $REF) { $row2 = $x; break; }
    $t('ikinci yazım ÇOĞALTMIYOR',   substr_count((string)($row2['notes'] ?? ''), 'Deliver to:') === 1);
    $t('yeni adres geçerli',         ($a2['on_invoice'] ?? '') === 'Rue Neuve 1, 1000 Brussels, Belgium');
    $a3 = vestra_order_set_delivery($REF, '');
    $t('boş bırakmak siliyor',       !isset($a3['error']) && ($a3['address'] ?? 'x') === '');
    $t('çok uzun adres REDDEDİLİR',  isset(vestra_order_set_delivery($REF, str_repeat('x', 301))['error']));
    $t('olmayan sipariş REDDEDİLİR', isset(vestra_order_set_delivery('YOK-BOYLE-REF', 'a')['error']));
} finally {
    if ($bak === null) @unlink($file); else file_put_contents($file, $bak);
    if ($stB === null) @unlink($stF); else file_put_contents($stF, $stB);
    foreach (glob(vestra_data_dir().'/orders.csv.bak-ship-*') ?: [] as $g) @unlink($g);
}

echo "\n== 4. Faturası kesilmiş siparişte YAZMAZ ==\n";
$fn = $src('vestra/inc/orders.php');
$t('kesilmiş fatura kontrolü var', str_contains($fn, 'if (vestra_invoices_for_ref($ref)) {'));
$t('gerekçe KURAL 5f\'e yolluyor',  str_contains($fn, 'KURAL 5f'));
$t('yazma geri okunuyor',          str_contains($fn, 'geri okuma tutmadı'));
$t('dosya önce yedekleniyor',      str_contains($fn, "bak-ship-"));
$t('atomik takas',                 str_contains($fn, "rename(\$tmp, \$file)"));
/* Ham dosya okunuyor: vestra_read_csv() satırları ters çeviriyor ve o diziyi
   geri yazmak bütün defteri ters çevirirdi (order_delete aynı tuzağı anlatıyor). */
$t('ham dosyadan okuyor',          str_contains($fn, "\$in = fopen(\$file, 'r')"));
$t('mal toplamı tek fonksiyondan', str_contains($fn, '$ld    = vestra_order_lines($assoc);'));

echo "\n== 5. Panel ve iş akışı AYNI yazıcıyı çağırıyor ==\n";
$adm = $src('vestra/admin.php');
$t('panelde navlun formu var',     str_contains($adm, 'value="order_shipping"') && str_contains($adm, '🚚 Save shipping'));
$t('panel tek yazıcıyı çağırıyor', str_contains($adm, 'vestra_order_set_shipping($ref'));
$t('başarı/başarısızlık yazılı',   str_contains($adm, "elseif(\$msg==='ship_saved')") && str_contains($adm, "elseif(\$msg==='ship_fail')"));
$t('para girişi ham (float) DEĞİL', str_contains($adm, "vestra_price_input((string)(\$_POST['shipping']"));
$t('panelde adres formu var',      str_contains($adm, 'value="order_delivery"') && str_contains($adm, '📍 Save address'));
$t('adres tek yazıcıyı çağırıyor', str_contains($adm, 'vestra_order_set_delivery($ref'));
$t('adres sonucu ekrana yazılı',   str_contains($adm, "elseif(\$msg==='addr_saved')") && str_contains($adm, "elseif(\$msg==='addr_fail')"));
/* Yazıcı, satırı değil FATURANIN GÖRDÜĞÜNÜ doğruluyor: kaydın değişmesi yetmez,
   belgeyi besleyen çözücü de aynı adresi bulmalı. */
$t('adres faturaya karşı doğrulanıyor', str_contains($fn, 'vestra_invoice_buyer($back)'));
$t('adres yazıcısı da kesilmişte durur',
   substr_count($fn, 'if (vestra_invoices_for_ref($ref)) {') === 2);
$t('okuyucuyla AYNI kalıp',        str_contains($fn, "preg_replace('/Deliver to: .*?(?:\\.\\s|\\.\$|\$)/u'"));
$wf = $src('.github/workflows/seller-products.yml');
$t('iş akışında da mod var',       str_contains($wf, "admin_mode == 'shipping'"));
$t('iş akışı aynı yazıcıyı çağırıyor', str_contains($wf, 'vestra_order_set_shipping($ref, $amount, $label)'));
$t('kur damgası yoksa DURUYOR',    str_contains($wf, 'kur damgasi YOK'));
$t('iş akışı numara YAKMIYOR',     !str_contains(explode('- name: Faturanın para birimini', $wf)[0], 'vestra_issue_order_invoices'));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
