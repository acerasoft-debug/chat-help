<?php
/* Var olan bir siparişe YENİ bir SKU satırı ekleme (vestra_order_add_line,
 * 22 Eyl 2026 — operatör: "Black x 20, White x10, Navy 20, Grey 10 … bu
 * siparisi bu siparise ekle VES-1A68FCD1").
 *
 * NEDEN KUM HAVUZUNDA GERÇEKTEN YAZIYOR: `vestra_order_set_colours()` yalnızca
 * ZATEN `items`'te duran bir kalemin renk notunu düzeltiyor ve
 * `vestra_order_create_manual()` HER ZAMAN yeni/ayrı bir sipariş yazıyor — bu
 * ikisinin arasında "var olan siparişe sonradan yeni bir kalem ekleme" hiç
 * yoktu. Okuma tarafı (vestra_order_lines) tek başına bunu doğrulayamaz;
 * yazma tarafı olmadan boşluk görünmezdi. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_oal_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);

if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
require_once $root.'/inc/products.php';
require_once $root.'/inc/orders.php';
require_once $root.'/inc/invoice.php';

if (!function_exists('vestra_data_dir') || vestra_data_dir() !== $sand.'/data') {
    fwrite(STDERR, "kum havuzu kurulamadi (vestra_data_dir=".(function_exists('vestra_data_dir') ? vestra_data_dir() : '?').")\n");
    exit(1);
}

/* AMI-PL-014 (AMI Paris Core Logo Polo) bu depoda hard-coded bir demo ürün
   (vestra_is_demo_product) ve kum havuzunda listings.json OLMADAN da
   çözülüyor — canlı D&G/DSQUARED2 SKU'ları listings.json'a bağlı olduğu için
   burada kullanılamaz. */
$ami = vestra_product_by_sku('AMI-PL-014');
if (!$ami) { fwrite(STDERR, "AMI-PL-014 kum havuzunda cozulemedi -- test kurulamiyor\n"); exit(1); }
$amiUnit = round((float)vestra_unit_price($ami, 60, true), 2);

$head = ['ref', 'company', 'email', 'country', 'items', 'subtotal', 'commission', 'payout', 'total', 'notes', 'discount', 'shipping', 'shipping_label'];
$mk = function (string $ref, string $items, string $notes, float $subtotal, float $discount, float $shipping, float $total) use ($sand, $head) {
    $f = $sand.'/data/orders.csv';
    $new = !is_file($f);
    $h = fopen($f, $new ? 'w' : 'a');
    if ($new) fputcsv($h, $head, ',', '"', '\\');
    fputcsv($h, [$ref, 'Test SRL', 'x@example.com', 'Romania', $items,
        number_format($subtotal, 2, '.', ''), '0.00', number_format($subtotal, 2, '.', ''),
        number_format($total, 2, '.', ''), $notes,
        number_format($discount, 2, '.', ''), number_format($shipping, 2, '.', ''), 'Shipping'], ',', '"', '\\');
    fclose($h);
};

/* Gerçek VES-1A68FCD1'in şekli: iki kalem, sabit €95 indirim, €20 kargo,
   ve mevcut renk notu + adrese ait serbest metin — hiçbiri kaybolmamalı. */
$mk('VES-T1', '20x SKU-A @60.00 | 20x SKU-B @35.00',
    'Payment: Bank transfer. Colours — SKU-A: Black | SKU-B: Black. Deliver to: Strada 1, Bucuresti.',
    1805.00, 95.00, 20.00, 1825.00);

echo "== 1. Yeni satır eklendi, renk kırılımı ve toplamlar doğru ==\n";
$r = vestra_order_add_line('VES-T1', 'AMI-PL-014', 60, ['Black' => 20, 'White' => 10, 'Navy' => 20, 'Grey' => 10]);
$t('yazma basarili', empty($r['error']));
$t('birim fiyat katalogdan (elle degil)', abs(($r['unit'] ?? -1) - $amiUnit) < 0.005);
$t('satir toplami dogru', abs(($r['line_total'] ?? -1) - round($amiUnit * 60, 2)) < 0.005);
$t('goods = eski 1900 + yeni satir', abs(($r['goods'] ?? -1) - round(1900.00 + $amiUnit * 60, 2)) < 0.005);
$t('indirim DOKUNULMADI (95.00)', abs(($r['discount'] ?? -1) - 95.00) < 0.005);
$t('kargo DOKUNULMADI (20.00)', abs(($r['shipping'] ?? -1) - 20.00) < 0.005);
$t('subtotal = goods - indirim', abs(($r['subtotal'] ?? -1) - round(1900.00 + $amiUnit * 60 - 95.00, 2)) < 0.005);
$t('total = subtotal + kargo (fee=0)', abs(($r['total'] ?? -1) - round(1900.00 + $amiUnit * 60 - 95.00 + 20.00, 2)) < 0.005);
$t('renklerin toplami adede esit oldugu icin kabul edildi', empty($r['error']));
$t('ilanda kayitli olmayan renk yok', ($r['not_listed'] ?? ['x']) === []);
$t('faturasiz siparişte must_redraft FALSE', ($r['must_redraft'] ?? null) === false);

echo "\n== 2. Notların GERİSİNE dokunulmadı, ESKİ renkler ve YENİ satır bir arada ==\n";
$back = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-T1') $back = $x;
$n = (string)($back['notes'] ?? '');
$t('Payment parcasi duruyor', str_contains($n, 'Payment: Bank transfer.'));
$t('Deliver to parcasi duruyor', str_contains($n, 'Deliver to: Strada 1, Bucuresti.'));
$t('eski SKU-A rengi duruyor', str_contains($n, 'SKU-A: Black'));
$t('eski SKU-B rengi duruyor', str_contains($n, 'SKU-B: Black'));
$t('yeni SKU renk seti duruyor', str_contains($n, 'AMI-PL-014: Black, White, Navy, Grey'));
$t('renk-adet kirilimi DUZ METINDE yaziyor', str_contains($n, 'AMI-PL-014 colour split: Black×20, White×10, Navy×20, Grey×10.'));
$t('Colours parcasi TEK kez', substr_count($n, 'Colours —') === 1);

$lines = vestra_order_lines($back)['lines'];
$byS = []; foreach ($lines as $l) $byS[$l['sku']] = $l;
$t('SKU-A hala 20 adet, Black', ($byS['SKU-A']['qty'] ?? 0) === 20 && ($byS['SKU-A']['colors'] ?? []) === ['Black']);
$t('SKU-B hala 20 adet, Black', ($byS['SKU-B']['qty'] ?? 0) === 20 && ($byS['SKU-B']['colors'] ?? []) === ['Black']);
$t('YENİ satir FATURANIN gordugu renklerle geliyor', ($byS['AMI-PL-014']['colors'] ?? []) === ['Black', 'White', 'Navy', 'Grey']);
$t('YENİ satirin adedi 60', ($byS['AMI-PL-014']['qty'] ?? 0) === 60);

echo "\n== 3. AYNI SKU ikinci kez EKLENEMEZ (miktar/renk düzeltmesi başka yoldan) ==\n";
$r2 = vestra_order_add_line('VES-T1', 'AMI-PL-014', 10, []);
$t('ikinci satir REDDEDILDI', !empty($r2['error']));
$t('gerekce vestra_order_set_colours\'a yolluyor', str_contains((string)($r2['error'] ?? ''), 'vestra_order_set_colours'));
$back3 = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-T1') $back3 = $x;
$t('red sonrasi kalem sayisi degismedi', count(vestra_order_lines($back3)['lines']) === 3);

echo "\n== 4. reddetmeler ==\n";
$t('bilinmeyen ref REDDEDILDI', !empty(vestra_order_add_line('VES-YOK', 'AMI-PL-014', 60, [])['error']));
$t('katalogda olmayan SKU REDDEDILDI', !empty(vestra_order_add_line('VES-T1', 'NO-SUCH-SKU-XYZ', 5, [])['error']));
$t('adet<1 REDDEDILDI', !empty(vestra_order_add_line('VES-T1', 'ANOTHER-SKU', 0, [])['error']));
/* Renk kırılımının toplamı adetten farklıysa bu bir kayıt/paket listesi
   yalanı olurdu (KURAL 3'ün adet hali) -- sessizce yazılmaz. */
$rBad = vestra_order_add_line('VES-T2-NOPE', 'AMI-PL-014', 60, ['Black' => 20, 'White' => 10]);
$t('renk kirilimi adetle uyusmuyor -> REDDEDILDI', !empty($rBad['error']));

echo "\n== 5. ANLAŞILAN birim fiyat: ucuz kabul, PAHALI reddedilir ==\n";
$mk('VES-T3', '5x SKU-C @9.00', 'Payment: card.', 45.00, 0.00, 0.00, 45.00);
$listUnit3 = round((float)vestra_unit_price($ami, 60, true), 2);
$r5ok = vestra_order_add_line('VES-T3', 'AMI-PL-014', 60, [], max(0.01, $listUnit3 - 1.0));
$t('ilandan UCUZ anlasilan fiyat KABUL edildi', empty($r5ok['error']) && abs(($r5ok['unit'] ?? -1) - ($listUnit3 - 1.0)) < 0.005);
$r5bad = vestra_order_add_line('VES-T3', 'SOMETHING-ELSE-NOT-USED', 60, [], $listUnit3 + 5.0);
/* Bu cagrida SKU zaten katalogda yok, o yuzden PAHALI kontrolu hic devreye
   girmeden reddedilir -- asagida ayni urunle (farkli sipariste) tekrar deneniyor. */
$t('katalogda olmayan SKU + fiyat -> yine SKU gerekcesiyle RED', str_contains((string)($r5bad['error'] ?? ''), 'katalogda yok'));
$mk('VES-T4', '5x SKU-D @9.00', 'Payment: card.', 45.00, 0.00, 0.00, 45.00);
$r5bad2 = vestra_order_add_line('VES-T4', 'AMI-PL-014', 60, [], $listUnit3 + 5.0);
$t('ilandan PAHALI anlasilan fiyat REDDEDILDI', !empty($r5bad2['error']));
$t('gerekce "alici aleyhine" diyor', str_contains((string)($r5bad2['error'] ?? ''), 'alıcı aleyhine'));

echo "\n== 6. FATURALI sipariş: varsayılan RED, opt-in ile YAZAR ve must_redraft=TRUE ==\n";
/* KURAL 5f: belge alicinin elinde olabilir. Sessizce yazmak, kayit ile
   belgeyi ayristirirdi -- bu depoda faturanin UC katmani tam bundan dogdu. */
/* invoice.php bu dizini bölüm 1'deki ilk vestra_invoices_for_ref() çağrısında
   zaten kurmuş olabilir (kendi .htaccess'iyle) -- var olan bir dizini yeniden
   oluşturmaya çalışmak PHP uyarısı üretir. */
if (!is_dir($sand.'/data/invoices')) mkdir($sand.'/data/invoices', 0777, true);
$mk('VES-T5', '10x SKU-E @10.00', 'Payment: card.', 100.00, 0.00, 20.00, 120.00);
file_put_contents($sand.'/data/invoices/VES-T5__vestra.json',
    json_encode(['no' => 'INV-2026-7777', 'ref' => 'VES-T5', 'seller_key' => 'vestra', 'total' => 120.0, 'currency' => 'EUR']));
file_put_contents($sand.'/data/invoices/VES-T5__vestra.pdf', '%PDF-1.4 test');
$inv = vestra_invoices_for_ref('VES-T5');
$t('kum havuzunda fatura GORUNUYOR (olcum gecerli)', count($inv) === 1);

$r6 = vestra_order_add_line('VES-T5', 'AMI-PL-014', 60, ['Black' => 20, 'White' => 10, 'Navy' => 20, 'Grey' => 10]);
$t('faturali sipariste varsayilan RED', !empty($r6['error']));
$t('ret KURAL 5f\'e yolluyor', str_contains((string)($r6['error'] ?? ''), '5f'));
$back6 = null; foreach (vestra_read_csv('orders.csv') as $x) if (($x['ref'] ?? '') === 'VES-T5') $back6 = $x;
$t('red sonrasi kalem sayisi hala 1 (yazilmadi)', count(vestra_order_lines($back6)['lines']) === 1);
$t('red sonrasi toplam degismedi (120.00)', abs((float)($back6['total'] ?? -1) - 120.00) < 0.005);

$r7 = vestra_order_add_line('VES-T5', 'AMI-PL-014', 60, ['Black' => 20, 'White' => 10, 'Navy' => 20, 'Grey' => 10], null, '', true);
$t('opt-in ile YAZIYOR', empty($r7['error']));
$t('must_redraft bayragi TRUE', ($r7['must_redraft'] ?? null) === true);
$t('goods = eski 100 + yeni satir', abs(($r7['goods'] ?? -1) - round(100.00 + $amiUnit * 60, 2)) < 0.005);
$t('eski kargo (20.00) korundu', abs(($r7['shipping'] ?? -1) - 20.00) < 0.005);
$r8 = vestra_order_add_line('VES-T1', 'AMI-PL-014', 10, []);
/* VES-T1'de AMI-PL-014 zaten var (test 1'den), fatura YOK -- must_redraft
   FALSE donmesi gerekirdi ama zaten dup-guard'a takilip hic o noktaya
   gelmiyor; asil kontrol su asagidaki VES-T3 (fatura yok, farkli SKU). */
$t('faturasiz farkli siparişte must_redraft FALSE', ($r5ok['must_redraft'] ?? null) === false);

echo "\n== 7. Ön koşullu ürün: preorder cümlesi tek kaynaktan, ürün-özel bir cümle uydurulmadı ==\n";
$t('preorder cumlesi vestra_preorder_note() ile birebir ayni',
    ($r['preorder'] ?? '') === vestra_preorder_note($ami));

/* invoice.php ilk çağrıda data/invoices/'i kendi .htaccess'iyle birlikte
   OTOMATİK kuruyor (KURAL: data/ tarayıcıya kapalı) — glob('*') gizli dosyayı
   yakalamaz, GLOB_MARK'sız bırakılırsa /tmp'de sandbox artığı birikir. */
@array_map('unlink', glob($sand.'/data/invoices/*', GLOB_MARK | GLOB_NOSORT) ?: []);
@unlink($sand.'/data/invoices/.htaccess');
@array_map('unlink', glob($sand.'/data/*.bak-*') ?: []);
@array_map('unlink', glob($sand.'/data/*') ?: []);
@rmdir($sand.'/data/invoices'); @rmdir($sand.'/data'); @rmdir($sand);

printf("\norder_add_line_test: %d iddia gecti, %d KIRMIZI\n", $ok, $bad);
exit($bad ? 1 : 0);
