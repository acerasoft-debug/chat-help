<?php
/* NAVLUN TARİFESİ — KURAL (operatör, 19 Eyl 2026, dört cümlede):
 *   "her faturaya 10 ad. için 20 eur, sonraki her 10 ad. için +5 eur"
 *   "sadece 40+ üstü aynı model siparişlerde her 100 ad. başına 30 eur"
 *   "bu avrupa siparişleri için geçerli"
 *   "abd için her siparişe 30 eur + 20 ad. sonrasına her 10 ad. +5 eur; tek
 *    model ve üründen alınırsa her 100 ad. 50 eur, 100 + 50 ad.'e kadar 50+20"
 *
 * İKİ YÖN: tarifenin UYGULANDIĞI yerler kadar UYGULANMADIĞI yerler de
 * tutuluyor — tanınmayan ülke (Japonya), boş ülke ve yakın-komşu tuzakları
 * (AT ≠ AU, "Virgin Islands (US)" ≠ US). Tek yön yazılsaydı her siparişe
 * Avrupa tarifesi basan bir kusur da yeşil kalırdı.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';
require_once $root . '/inc/orders.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents(__DIR__ . '/../' . $f);
/* Satırlar SKU başına: bulk rayı tek bir SKU'nun adedine bakıyor. */
$L = function (array $qtys): array {
    $out = []; $i = 0;
    foreach ($qtys as $q) $out[] = ['sku' => 'SKU'.(++$i), 'qty' => $q];
    return $out;
};
$amt = function (array $qtys, string $country) use ($L) {
    $r = vestra_shipping_schedule($L($qtys), $country);
    return $r === null ? null : (float)$r['amount'];
};

echo "== 1. Bölge tanıma — POZİTİF, TAM eşleşme ==\n";
$t('Germany → eu',            vestra_shipping_region('Germany') === 'eu');
$t('Deutschland → eu',        vestra_shipping_region('Deutschland') === 'eu');
$t('AT (ISO) → eu',           vestra_shipping_region('AT') === 'eu');
$t('GB → eu (AB değil, coğrafi Avrupa)', vestra_shipping_region('GB') === 'eu');
$t('USA → us',                vestra_shipping_region('USA') === 'us');
$t('United States → us',      vestra_shipping_region('United States') === 'us');
$t('U.S.A. → us (nokta atılıyor)', vestra_shipping_region('U.S.A.') === 'us');
$t('Estados Unidos → us',     vestra_shipping_region('Estados Unidos') === 'us');
/* Yakın komşu tuzakları — mango/zara dersinin coğrafya hâli. */
$t('AU (Avustralya) tarifesiz',   vestra_shipping_region('AU') === null);
$t('Australia tarifesiz',         vestra_shipping_region('Australia') === null);
$t('Japan tarifesiz',             vestra_shipping_region('Japan') === null);
$t('boş ülke tarifesiz',          vestra_shipping_region('') === null);
$t('Virgin Islands (US) tarifesiz', vestra_shipping_region('Virgin Islands (US)') === null);
$t('Türkiye tarifesiz',           vestra_shipping_region('Türkiye') === null);

echo "\n== 2. AVRUPA havuz rayı: ilk 10 = €20, sonraki her BAŞLAYAN 10 = +€5 ==\n";
$t('1 ad. → 20',    abs($amt([1],  'Germany') - 20.0) < 0.005);
$t('10 ad. → 20',   abs($amt([10], 'Germany') - 20.0) < 0.005);
$t('11 ad. → 25',   abs($amt([11], 'Germany') - 25.0) < 0.005);
$t('19 ad. → 25 (başlayan blok)', abs($amt([19], 'Germany') - 25.0) < 0.005);
$t('20 ad. → 25',   abs($amt([20], 'Germany') - 25.0) < 0.005);
$t('30 ad. → 30',   abs($amt([30], 'Germany') - 30.0) < 0.005);
$t('39 ad. → 35',   abs($amt([39], 'Germany') - 35.0) < 0.005);
/* Havuz: bulk eşiğinin ALTINDAKİ satırlar BİRLİKTE sayılıyor — satır başına
   ayrı bir €20 almak, iki kalemlik küçük bir siparişi iki kat pahalı yapardı. */
$t('5+5 ad. tek havuz → 20',      abs($amt([5,5], 'Germany') - 20.0) < 0.005);
$t('39+5 ad. → 40 (44 ad. havuz)', abs($amt([39,5], 'Germany') - 40.0) < 0.005);

echo "\n== 3. AVRUPA toptan rayı: 40+ aynı model, her BAŞLAYAN 100 = €30 ==\n";
$t('40 ad. tek model → 30',  abs($amt([40],  'Germany') - 30.0) < 0.005);
$t('100 ad. → 30',           abs($amt([100], 'Germany') - 30.0) < 0.005);
$t('101 ad. → 60',           abs($amt([101], 'Germany') - 60.0) < 0.005);
$t('200 ad. → 60',           abs($amt([200], 'Germany') - 60.0) < 0.005);
$t('iki lot 40+40 → 60',     abs($amt([40,40], 'Germany') - 60.0) < 0.005);
/* KARIŞIK sipariş: 40'lık lot toptan rayında, 5'lik kalan havuzda. */
$t('40+5 → 50 (30 toptan + 20 havuz)', abs($amt([40,5], 'Germany') - 50.0) < 0.005);
/* 39 eşiğin ALTINDA: toptan rayına düşmemeli — operatör eşiği 40 dedi. */
$t('39 ad. havuzda kalıyor (35 ≠ 30)', abs($amt([39], 'Germany') - 35.0) < 0.005);

echo "\n== 4. ABD havuz rayı: ilk 20 = €30, sonraki her BAŞLAYAN 10 = +€5 ==\n";
$t('1 ad. → 30',    abs($amt([1],  'United States') - 30.0) < 0.005);
$t('20 ad. → 30',   abs($amt([20], 'United States') - 30.0) < 0.005);
$t('21 ad. → 35',   abs($amt([21], 'United States') - 35.0) < 0.005);
$t('30 ad. → 35',   abs($amt([30], 'United States') - 35.0) < 0.005);
$t('39 ad. → 40',   abs($amt([39], 'United States') - 40.0) < 0.005);

echo "\n== 5. ABD toptan rayı: her 100 = €50; TAM 100'ün üstündeki ≤50 kalan = +€20 ==\n";
/* Bu blok bir HATAYI pinliyor: ilk yazımda `$full > 0` şartı yoktu ve 40 adet
   yarım bloğa (€20) düşüyordu — probe yakaladı. Operatörün cümlesi "her 100
   ad.'e kadar 50 eur"; yarım blok ancak TAM bir yüzün üstünde geçerli. */
$t('40 ad. → 50 (ilk 100e kadar)',  abs($amt([40],  'United States') - 50.0) < 0.005);
$t('50 ad. → 50',                   abs($amt([50],  'United States') - 50.0) < 0.005);
$t('99 ad. → 50',                   abs($amt([99],  'United States') - 50.0) < 0.005);
$t('100 ad. → 50',                  abs($amt([100], 'United States') - 50.0) < 0.005);
$t('101 ad. → 70 (100 + yarım)',    abs($amt([101], 'United States') - 70.0) < 0.005);
$t('150 ad. → 70',                  abs($amt([150], 'United States') - 70.0) < 0.005);
$t('151 ad. → 100 (yarımı aşınca tam blok)', abs($amt([151], 'United States') - 100.0) < 0.005);
$t('200 ad. → 100',                 abs($amt([200], 'United States') - 100.0) < 0.005);
$t('250 ad. → 120',                 abs($amt([250], 'United States') - 120.0) < 0.005);

echo "\n== 6. Tarife YOKSA hiçbir rakam UYDURULMUYOR (KURAL 3) ==\n";
$t('Japonya → null',        $amt([100], 'Japan') === null);
$t('boş ülke → null',       $amt([100], '') === null);
$t('Avustralya → null',     $amt([100], 'Australia') === null);
$t('sıfır adet → null',     $amt([0], 'Germany') === null);
$t('AB ≠ ABD rakamları',    $amt([10], 'Germany') !== $amt([10], 'United States'));

echo "\n== 7. Döküm: operatör rakamın NEREDEN çıktığını görüyor ==\n";
$sch = vestra_shipping_schedule($L([120, 7]), 'Germany');
$t('bölge yazılı',          ($sch['region'] ?? '') === 'eu');
$t('toplam adet doğru',     (int)($sch['qty'] ?? 0) === 127);
$t('toptan satır ayrı',     count($sch['bulk'] ?? []) === 1 && (int)$sch['bulk'][0]['qty'] === 120);
$t('havuz ayrı',            (int)($sch['pooled_qty'] ?? 0) === 7);
$t('toplam = havuz + toptan', abs((float)$sch['amount'] - ((float)$sch['pooled'] + (float)$sch['bulk'][0]['amount'])) < 0.005);
$t('etiket bölgeyi söylüyor', str_contains((string)($sch['label'] ?? ''), 'EU'));

echo "\n== 8. RAKAM TEK TABLODA — hiçbir metne gömülü değil (KURAL 6) ==\n";
$T = vestra_shipping_tariffs();
$t('iki bölge var',          isset($T['eu'], $T['us']));
$t('AB tabanı 20/10',        abs($T['eu']['base'] - 20.0) < 0.005 && (int)$T['eu']['base_qty'] === 10);
$t('AB adımı +5/10',         abs($T['eu']['step'] - 5.0) < 0.005 && (int)$T['eu']['step_qty'] === 10);
$t('AB toptanı 30/100, eşik 40', abs($T['eu']['bulk'] - 30.0) < 0.005 && (int)$T['eu']['bulk_per'] === 100 && (int)$T['eu']['bulk_min'] === 40);
$t('AB yarım blok YOK',      (int)$T['eu']['half_qty'] === 0);
$t('ABD tabanı 30/20',       abs($T['us']['base'] - 30.0) < 0.005 && (int)$T['us']['base_qty'] === 20);
$t('ABD toptanı 50/100',     abs($T['us']['bulk'] - 50.0) < 0.005);
$t('ABD yarım bloğu 20/50',  abs($T['us']['half'] - 20.0) < 0.005 && (int)$T['us']['half_qty'] === 50);

echo "\n== 9. Kablolama: kasa, sepet, teklif faturası ve iş akışı AYNI tabloyu okuyor ==\n";
/* KURAL 34 (19 Eyl 2026): davranış BİLEREK değişti -- dört OTOMATİK çağıran
   artık pure fonksiyonu değil, PASİF anahtarına bakan `_auto_schedule`
   sarmalını çağırıyor (bölüm 12'de ayrıca ve daha ayrıntılı sınanıyor). Bu
   bölümdeki dört iddia eski, sarmalsız çağrıyı pinliyordu ve kasıtlı
   değişiklikten sonra kırmızıya düştü; bu depo "davranış bilerek değiştiyse
   testi de düzelt" kuralını burada da uyguluyor. */
$order = $src('vestra/order.php');
$t('kasa tarifeyi (sarmal üzerinden) çağırıyor', str_contains($order, 'vestra_shipping_auto_schedule($lines, $country)'));
$t('kasa navlunu TOPLAMA katıyor',   str_contains($order, '$buyer_fee + $shipping'));
$t('kasa CSV\'ye yazıyor',           str_contains($order, "'discount','shipping','shipping_label'"));
$t('escrow navlunu KENDİ satırı',    str_contains($order, "\$li[]=['name'=>(\$shipLabel!==''?\$shipLabel:'Shipping')"));
$t('alıcı mektubunda navlun satırı', str_contains($order, '{$voucherLine}{$shipLine}Buyer pays'));
/* Kasada elle yazılmış bir rakam olmamalı: 20/5/30/50 hiçbir yerde gömülü değil. */
$t('kasada gömülü rakam yok',        !preg_match('/\$shipping\s*=\s*[0-9]/', $order));

$cart = $src('vestra/cart.php');
$t('sepet tabloyu SUNUCUDAN (anahtara bağlı) basıyor', str_contains($cart, 'vestra_shipping_auto_enabled() ? vestra_shipping_tariffs() : []'));
$t('sepet bölge haritasını basıyor',   str_contains($cart, 'vestra_shipping_region_map()'));
$t('sepet toplamı navlunu içeriyor',   str_contains($cart, 'eur(net+efee+shipAmt)'));
$t('sepette elle yazılmış tarife yok', !preg_match('/base_qty\s*[:=]\s*[0-9]/', $cart));
$t('ülke değişince yeniden çiziliyor', str_contains($cart, "querySelector('#orderForm [name=country]')"));
/* JS kalan kuralı PHP ile aynı olmalı — $full > 0 şartı dahil. */
$t('JS yarım blok şartı PHP ile aynı', str_contains($cart, 'full>0 && T.half_qty>0 && rem<=T.half_qty'));

$off = $src('vestra/inc/offers.php');
$t('teklif faturası varsayılanı tarifeden', str_contains($off, 'vestra_offer_invoice_shipping('));
$t('ölçüt array_key_exists (bilinçli 0 korunuyor)', str_contains($off, "array_key_exists('invoice_shipping', \$rec)"));
$t('tek teklif yolu bağlı',   substr_count($off, 'vestra_offer_invoice_shipping(') === 3);

$adm = $src('vestra/admin.php');
$t('panel tarifeyi (sarmal üzerinden) gösteriyor', str_contains($adm, 'vestra_order_shipping_auto_schedule($viewRow)'));
$t('panel AYNI yazıcıyı çağırıyor', str_contains($adm, 'name="_action" value="order_shipping"'));
$t('kuyrukta eksik navlun çipi de sarmal üzerinden', str_contains($adm, 'vestra_order_shipping_auto_schedule($o)'));

$wf = $src('.github/workflows/seller-products.yml');
$t('iş akışı auto kipi var',        str_contains($wf, "strtolower(\$amountPart) === 'auto'"));
$t('auto tanınmayan ülkede DURUYOR', str_contains($wf, 'tarife YOK: ulke'));
$t('order_draft da tarifeyi okuyor', str_contains($wf, "strtolower(\$shipRaw) === 'auto'"));

echo "\n== 10. Elle sipariş: null = tarife, açık 0 = navlun yok ==\n";
$ordersSrc = $src('vestra/inc/orders.php');
$t('create_manual varsayılanı null', str_contains($ordersSrc, 'vestra_order_create_manual(array $acc, array $lines, ?float $shipping = null'));
$t('null iken tarife hesaplanıyor',  str_contains($ordersSrc, 'if ($shipping === null) {'));
/* Bölge haritası TÜRETİLİYOR, elle yazılmıyor: ikinci bir liste bir gün
   eklenen ülkede "sepette €0, kasada €30" demekti. */
$map = vestra_shipping_region_map();
$t('harita Avrupa kodlarını içeriyor', ($map['de'] ?? '') === 'eu' && ($map['at'] ?? '') === 'eu');
$t('harita ABD adlarını içeriyor',     ($map['usa'] ?? '') === 'us');
$t('haritada Japonya YOK',             !isset($map['japan']));
$t('haritada Avustralya YOK',          !isset($map['australia']) && !isset($map['au']));
$t('harita tablolardan türüyor',       str_contains($ordersSrc, 'foreach (vestra_europe_codes() as $cc) $map['));

echo "\n== 11. OTOMASYON ANAHTARI — KAPALIYKEN hiçbir OTOMATİK çağıran rakam ==\n";
echo "     üretmiyor (KURAL 34, operatör 19 Eyl 2026: \"tekrar söylüyorum\n";
echo "     simdilik otomatik yapma pasif olsun ben hesaplarim siparisten sonra\") ==\n";
$shipFile = vestra_shipping_settings_file();
$shipBak  = is_file($shipFile) ? file_get_contents($shipFile) : null;
/* vestra_shipping_auto_enabled() süreç içinde önbellekli (static) —
   vestra_dropship_payments_enabled()'ın aynı, bilinen sınırı. Tek süreçte
   açıp kapatıp ölçmek ölçüm aracının kendi gürültüsünü ölçmek olurdu; dosya
   davranışı bu yüzden AYRI PHP süreçlerinde sınanıyor. */
$shipProbe = function (?string $json) use ($root, $shipFile): string {
    if ($json === null) @unlink($shipFile); else file_put_contents($shipFile, $json);
    $code = 'require '.var_export($root.'/inc/products.php', true).';'
          . 'require '.var_export($root.'/inc/orders.php', true).';'
          . 'echo vestra_shipping_auto_enabled() ? "ON" : "OFF";';
    return (string)shell_exec('php -d error_reporting=0 -r ' . escapeshellarg($code) . ' 2>/dev/null');
};
try {
    $t('ayar dosyası yokken PASİF',              $shipProbe(null) === 'OFF');
    $t('boş dosyada da PASİF',                   $shipProbe('{}') === 'OFF');
    $t('bozuk JSON da PASİF (sessizce açılmaz)', $shipProbe('{bozuk') === 'OFF');
    $t('auto_enabled=false PASİF',               $shipProbe('{"auto_enabled":false}') === 'OFF');
    $t('auto_enabled=true AKTİF',                $shipProbe('{"auto_enabled":true}') === 'ON');

    echo "\n-- yazma + geri okuma --\n";
    @unlink($shipFile);
    $t('aç: true döner',       vestra_shipping_set_auto(true) === true);
    $t('dosyaya yazıldı',      ($j = json_decode((string)file_get_contents($shipFile), true)) && $j['auto_enabled'] === true);
    $t('kim/ne zaman damgası', ($j['auto_changed_by'] ?? '') === 'operator' && ($j['auto_changed_at'] ?? '') !== '');
    $t('kapat: true döner',    vestra_shipping_set_auto(false) === true);
    $t('kapalı yazıldı',       (json_decode((string)file_get_contents($shipFile), true)['auto_enabled'] ?? null) === false);
    $t('süreçler arası okunuyor', $shipProbe(null) === 'OFF');

    echo "\n-- sarmal PASİFKEN, PURE fonksiyon geçerli bir sonuç verecek girdide bile null --\n";
    @unlink($shipFile);
    $t('vestra_shipping_auto_schedule PASİFKEN null (pure fonksiyon 20.0 dönerdi)',
       vestra_shipping_auto_schedule($L([10]), 'Germany') === null);
    $t('vestra_order_shipping_auto_schedule PASİFKEN de null',
       vestra_order_shipping_auto_schedule(['items' => '10x SKU1 @2.00', 'notes' => '', 'country' => 'Germany']) === null);
    /* Tanınmayan ülkeyle AYNI cevap: ikisi de "burada otomatik bir rakam yok" demek. */
    $t('PASİF ile tanınmayan-ülke AYNI cevabı veriyor',
       vestra_shipping_auto_schedule($L([10]), 'Germany') === vestra_shipping_schedule($L([10]), 'Japan'));

    echo "\n-- AÇIKKEN sarmal PURE fonksiyonla BİREBİR aynı (ikinci bir hesap yolu değil) --\n";
    file_put_contents($shipFile, json_encode(['auto_enabled' => true]));
    $probeSame = function () use ($root): string {
        $code = 'require '.var_export($root.'/inc/products.php', true).';'
              . 'require '.var_export($root.'/inc/orders.php', true).';'
              . '$a = vestra_shipping_auto_schedule([["sku"=>"S","qty"=>10]], "Germany");'
              . '$b = vestra_shipping_schedule([["sku"=>"S","qty"=>10]], "Germany");'
              . 'echo ($a === $b) ? "SAME" : "DIFF";';
        return (string)shell_exec('php -d error_reporting=0 -r ' . escapeshellarg($code) . ' 2>/dev/null');
    };
    $t('AÇIKKEN sarmal = pure fonksiyon (birebir)', $probeSame() === 'SAME');
} finally {
    if ($shipBak === null) @unlink($shipFile); else file_put_contents($shipFile, $shipBak);
}

echo "\n== 12. Kablolama: OTOMATİK her çağıran sarmalı kullanıyor; ELLE yazma yolu DOKUNULMADI ==\n";
$order2 = $src('vestra/order.php');
$t('kasa artık sarmalı çağırıyor',       str_contains($order2, 'vestra_shipping_auto_schedule($lines, $country)'));
$t('kasada çıplak pure çağrı kalmadı',   !str_contains($order2, '$shipSched  = vestra_shipping_schedule('));

$cart2 = $src('vestra/cart.php');
$t('sepet tarife tablosunu anahtara bağlıyor', str_contains($cart2, 'vestra_shipping_auto_enabled() ? vestra_shipping_tariffs() : []'));
$t('sepet bölge haritasını da anahtara bağlıyor', str_contains($cart2, 'vestra_shipping_auto_enabled() ? vestra_shipping_region_map() : []'));

$off2 = $src('vestra/inc/offers.php');
$t('teklif faturası varsayılanı sarmalı çağırıyor', str_contains($off2, 'vestra_shipping_auto_schedule($lines,'));

$adm2 = $src('vestra/admin.php');
$t('panel ipucu ("Apply tariff") sarmalı çağırıyor', str_contains($adm2, '$__vsched = vestra_order_shipping_auto_schedule($viewRow)'));
$t('sipariş kuyruğundaki çip de sarmalı çağırıyor',  str_contains($adm2, '$__osch = vestra_order_shipping_auto_schedule($o)'));
/* ELLE yazma yolu DOKUNULMADI: manuel "🚚 Save shipping" formunun işleyicisi
   hâlâ doğrudan yazıcıyı çağırıyor, anahtara hiç sormuyor -- operatörün
   "ben hesaplarım siparişten sonra" dediği yol bu. */
$t('manuel Save-shipping işleyicisi DEĞİŞMEDİ (anahtara sormuyor)',
   str_contains($adm2, "if(\$act==='order_shipping'){")
   && str_contains($adm2, "\$r=vestra_order_set_shipping(\$ref, vestra_price_input((string)(\$_POST['shipping']??'0')), (string)(\$_POST['shipping_label']??''));"));
$t('panelde otomasyon anahtarı formu var',   str_contains($adm2, 'value="shipping_auto"'));
$t('işleyici yazıp geri okuyor',             str_contains($adm2, "if(\$act==='shipping_auto'){") && str_contains($adm2, 'vestra_shipping_set_auto($saWant)'));
$t('yazılamazsa KIRMIZI uyarı',              str_contains($adm2, "elseif(\$msg==='ship_auto_fail')"));
$t('durum panelde yazılı',                   str_contains($adm2, 'Automatic shipping tariff is PAUSED'));
$t('geri açma düğmesi var',                  str_contains($adm2, 'Turn tariff back on'));

$wf2 = $src('.github/workflows/seller-products.yml');
$t('order_draft/order_write artık sarmalı çağırıyor', str_contains($wf2, 'vestra_shipping_auto_schedule('));
$t('admin_mode=shipping: auto PASİFKEN REDDEDİLİYOR',
   str_contains($wf2, 'if (!vestra_shipping_auto_enabled()) {') && str_contains($wf2, "'auto' kabul edilmiyor"));
$t('admin_mode=shipping: elle SAYISAL tutar hâlâ kabul ediliyor',
   str_contains($wf2, '$amount = vestra_price_input($amountPart);'));

echo "\n== 13. Kurulu kaldı: hiçbir şey silinmedi ==\n";
$t('pure tarife fonksiyonu duruyor',        function_exists('vestra_shipping_schedule'));
$t('tarife tablosu duruyor',                count(vestra_shipping_tariffs()) === 2);
$t('bölge tespiti duruyor',                 vestra_shipping_region('Germany') === 'eu');
$t('manuel yazıcı duruyor',                 function_exists('vestra_order_set_shipping'));

echo "\n".($fail ? "SONUÇ: {$fail} HATA, {$ok} ok\n" : "SONUÇ: hepsi geçti ({$ok})\n");
exit($fail ? 1 : 0);
