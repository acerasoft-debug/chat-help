<?php
/* TEKLIF ve NUMUNE kutulari: tek karar noktasi + sunucu kapisi + yazma yolu.
 *
 * Operator (7 Eyl 2026, Lacoste "Trim Cotton Jersey T-Shirt"):
 *   "angebot vermeyi kaldir sample i da kaldir"
 *
 * Bu is uc bosluk acti ve ucu de burada tutuluyor:
 *
 *  1. /offer ucu urunun teklif ALIP ALMADIGINA hic bakmiyordu. Urun sayfasi
 *     kutuyu yalnizca mode='offer' ya da 'offers' bayragi varken ciziyor, ama
 *     sunucu tarafinda karsiligi yoktu -- yani sabit fiyatli, hicbir yerinde
 *     teklif dugmesi olmayan bir ilana elle POST atan biri teklif birakabiliyor,
 *     satici paneline dusuyor ve kabul edilirse fatura kesiliyordu.
 *     products.php'nin kendi yorumu ("DUGMEYI GIZLEMEK KAPI DEGILDIR") alti
 *     satin alma yolunun HEPSININ sunucuda kontrol edildigini soyluyordu;
 *     teklif yolunda bu dogru degildi.
 *
 *  2. Bir ilandan teklif ya da numune kutusunu kaldirmanin HICBIR yolu yoktu:
 *     ne panelde alan vardi, ne set_product.php'de anahtar (sample_price=0
 *     "gecersiz fiyat" diye reddediliyordu). Tek yol koda dokunmakti.
 *
 *  3. 'offers' alanini silmek KALICI DEGIL: seller.php her kaydetmede o alani
 *     saticinin kutucugundan yeniden yaziyor. Operatorun karari saticinin bir
 *     sonraki kaydinda sessizce geri aliniyordu -- bu yuzden ayri bir
 *     'no_offers' anahtari var ve satici tarafi onu hic yazmiyor.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents(__DIR__ . '/../' . $f);

echo "== 1. vestra_offers_open(): tek karar noktasi ==\n";
$t('mode=offer -> ACIK',            vestra_offers_open(['mode' => 'offer']) === true);
$t('offers bayragi -> ACIK',        vestra_offers_open(['mode' => 'fixed', 'offers' => true]) === true);
$t('sabit fiyat, bayrak yok -> kapali', vestra_offers_open(['mode' => 'fixed']) === false);
$t('mode=sale, bayrak yok -> kapali',   vestra_offers_open(['mode' => 'sale']) === false);
/* Operatorun anahtari IKISINI de ezmeli, yoksa yalnizca yarim bir karar olur. */
$t('no_offers, bayragi ezer',       vestra_offers_open(['mode' => 'fixed', 'offers' => true, 'no_offers' => true]) === false);
$t('no_offers, mode=offer i de ezer', vestra_offers_open(['mode' => 'offer', 'no_offers' => true]) === false);
$t('bos ilan -> kapali',            vestra_offers_open([]) === false);

echo "\n== 2. vestra_sample_price(): 0 / bos / sayi-olmayan = numune YOK ==\n";
$t('50 -> 50.0',        vestra_sample_price(['sample_price' => 50]) === 50.0);
$t('"49.5" -> 49.5',    vestra_sample_price(['sample_price' => '49.5']) === 49.5);
$t('0 -> yok',          vestra_sample_price(['sample_price' => 0]) === 0.0);
$t('"" -> yok',         vestra_sample_price(['sample_price' => '']) === 0.0);
$t('alan yok -> yok',   vestra_sample_price([]) === 0.0);
$t('sayi degil -> yok', vestra_sample_price(['sample_price' => 'on request']) === 0.0);
$t('negatif -> yok',    vestra_sample_price(['sample_price' => -5]) === 0.0);

echo "\n== 3. Kodda YAZILI demo urun: override katmani ikisini de tasiyor ==\n";
/* Kodda duran bir urunun sample_price satirini panelden silmenin baska yolu
   yok -- kaynak satiri her deploy'da geri gelir. */
$demo = [['id' => 'x1', 'mode' => 'fixed', 'offers' => true, 'sample_price' => 50.0]];
$ovf  = vestra_data_dir() . '/product_overrides.json';
$keep = is_file($ovf) ? file_get_contents($ovf) : null;
try {
    file_put_contents($ovf, json_encode(['x1' => ['no_offers' => true, 'sample_price' => 0]]));
    $applied = vestra_apply_price_overrides($demo)[0];
    $t('override no_offers uygulaniyor',    vestra_offers_open($applied) === false);
    $t('override sample_price=0 uygulaniyor', vestra_sample_price($applied) === 0.0);
} finally {
    if ($keep === null) @unlink($ovf); else file_put_contents($ovf, $keep);
}

echo "\n== 4. Kablolar: kapi SUNUCUDA, kural TEK yerde ==\n";
$offer = $src('vestra/offer.php');
$t('/offer ucu vestra_offers_open cagiriyor',   str_contains($offer, 'vestra_offers_open($p)'));
$t('/offer reddi kayittan ONCE',                strpos($offer, 'vestra_offers_open($p)') < strpos($offer, "fputcsv"));
$smp = $src('vestra/sample-checkout.php');
$t('/sample-checkout vestra_sample_price cagiriyor', str_contains($smp, 'vestra_sample_price($p)'));
$t('numune tutari ayrica (float) ile okunmuyor',    !str_contains($smp, "(float)\$p['sample_price']"));
$prod = $src('vestra/product.php');
$t('urun sayfasi teklif kutusunda deciderı kullaniyor', str_contains($prod, 'vestra_offers_open($p)'));
$t('urun sayfasinda ham offers kosulu kalmadi',        !str_contains($prod, "!empty(\$p['offers'])"));
$t('urun sayfasi numune kutusunda deciderı kullaniyor', str_contains($prod, 'vestra_sample_price($p)'));
$t('urun sayfasinda ham sample_price kosulu kalmadi',   !str_contains($prod, "is_numeric(\$p['sample_price'])"));
$adm = $src('vestra/admin.php');
$t('panelde teklif kutucugu var',      str_contains($adm, 'name="offers[<?= $eid ?>]"'));
$t('panelde numune alani var',         str_contains($adm, 'name="sample[<?= $eid ?>]"'));
/* Isaretsiz bir kutucuk HIC gonderilmez: gizli alan olmadan editor teklifi
   yalnizca ACABILIR, hicbir zaman kapatamazdi. */
$t('gizli offers_seen alani var',      str_contains($adm, 'offers_seen[<?= $eid ?>]'));
$t('save_prices offers_seen okuyor',   str_contains($adm, "\$_POST['offers_seen']"));
$t('save_prices no_offers yaziyor',    str_contains($adm, "\$p['no_offers']=true"));
$t('numune fiyati vestra_price_input ile okunuyor', str_contains($adm, 'vestra_price_input($smpIn[$id])'));
/* Kilitli satirda gizli alan da cizilmemeli: cizilseydi ayni gonderimde
   mode'u 'offer'dan cikaran bir ilan "isaretsiz" okunup teklifi sessizce
   kapatilmis olurdu. */
$t('kilitli satirda offers_seen cizilmiyor', str_contains($adm, 'if(!$offLocked): ?><input type="hidden" name="offers_seen'));

echo "\n== 5. scripts/set_product.php GERCEKTEN calistiriliyor (kum havuzu) ==\n";
/* Kaynak taramasi yerine calistirma: bu betik listings.json'i yazan tek yol
   ve dogrulamalarinin dogru calistigini yalnizca kosarak gorursun. */
$sand = sys_get_temp_dir() . '/vestra_setprod_' . getmypid();
@mkdir($sand . '/public_html', 0777, true);
exec('cp -r ' . escapeshellarg($root . '/inc') . ' ' . escapeshellarg($sand . '/public_html/inc'));
@mkdir($sand . '/public_html/data', 0777, true);

$base = [[
    'id' => 'lgp-test-tee', 'sku' => 'TEST-TEE-01', 'brand' => 'Lacoste',
    'name' => 'Trim Cotton Jersey T-Shirt', 'cat' => 'T-Shirts', 'mode' => 'sale',
    'list' => 29.0, 'moq' => 20, 'unit' => 'pc', 'sample_price' => 50,
    'offers' => true, 'tiers' => [['min' => 20, 'price' => 20.0]],
]];
$write = function (array $listings) use ($sand) {
    file_put_contents($sand . '/public_html/data/listings.json', json_encode($listings));
};
$run = function (array $fixes, bool $dry = false) use ($sand) {
    $env = 'HOME=' . escapeshellarg($sand)
         . ' P_DRY=' . ($dry ? 'true' : 'false')
         . ' P_JSON=' . escapeshellarg(base64_encode(json_encode($fixes)));
    exec($env . ' php ' . escapeshellarg(__DIR__ . '/../scripts/set_product.php') . ' 2>&1', $out, $rc);
    return [$rc, implode("\n", $out)];
};
$read = function () use ($sand) {
    return json_decode((string)file_get_contents($sand . '/public_html/data/listings.json'), true)[0] ?? [];
};

/* 5a. Operatorun istedigi degisiklik, tek satirda. */
$write($base);
[$rc, $out] = $run([[
    'match' => 'TEST-TEE-01', 'moq' => 48,
    'tiers' => [['min' => 48, 'price' => 19.00], ['min' => 104, 'price' => 17.50]],
    'offers' => 'off', 'sample_price' => 0,
]]);
$after = $read();
$t('5a kaydedildi (rc=0)',        $rc === 0);
$t('5a moq 48',                   (int)($after['moq'] ?? 0) === 48);
/* Sayisal karsilastirma: json_encode 19.00'i "19" diye yaziyor, yani geri
   okundugunda int. Katalogdaki her fiyat ayni sekilde duruyor -- burada tipi
   sart kosan bir iddia, gercekte dogru olan bir kaydi kirmizi gosterirdi. */
$tiersNum = array_map(fn($r) => [(int)$r['min'], (float)$r['price']], (array)($after['tiers'] ?? []));
$t('5a merdiven 48/104',          $tiersNum === [[48, 19.0], [104, 17.5]]);
$t('5a en dusuk kademe 17.50',    vestra_from_price($after) === 17.5);
$t('5a MOQ da tahsil edilen 19',  vestra_unit_price($after, 48) === 19.0);
$t('5a 104 adette 17.50',         vestra_unit_price($after, 104) === 17.5);
$t('5a teklif KAPALI',            vestra_offers_open($after) === false);
$t('5a satici bayragi da silindi', !isset($after['offers']));
$t('5a numune YOK',               vestra_sample_price($after) === 0.0);
$t('5a fiyat modu korundu',       ($after['mode'] ?? '') === 'sale');

/* 5b. Kuru kosu HICBIR SEY yazmaz -- kontrol adiminin kendisi degistiriyorsa
       kontrol degil, uygulamadir. */
$write($base);
[$rc, $out] = $run([['match' => 'TEST-TEE-01', 'moq' => 48,
    'tiers' => [['min'=>48,'price'=>19.0],['min'=>104,'price'=>17.5]]]], true);
$t('5b kuru kosu basarili',       $rc === 0);
$t('5b kuru kosu yazmadi',        (int)($read()['moq'] ?? 0) === 20);
$t('5b plani yaziyor',            str_contains($out, 'moq 20 -> 48') && str_contains($out, '104+ -> €17.50'));

/* 5c. Merdiven MOQ'dan baslamali: baslamazsa minimum ile ilk basamak arasi
       sessizce ilk basamagin fiyatindan satilir. */
$write($base);
[$rc, $out] = $run([['match' => 'TEST-TEE-01', 'moq' => 48,
    'tiers' => [['min'=>60,'price'=>19.0]]]]);
$t('5c ilk kademe != moq reddedildi',  $rc !== 0 && str_contains($out, 'minimum siparisten baslamali'));
$t('5c hicbir sey kaydedilmedi',       (int)($read()['moq'] ?? 0) === 20);

/* 5d. Daha cok alan daha pahaliya alamaz. */
$write($base);
[$rc, $out] = $run([['match' => 'TEST-TEE-01', 'moq' => 48,
    'tiers' => [['min'=>48,'price'=>19.0],['min'=>104,'price'=>21.0]]]]);
$t('5d artan fiyatli merdiven reddedildi', $rc !== 0 && str_contains($out, 'ucuz degil'));

/* 5e. MOQ paket adiminin kati olmali: sepet miktari adima yuvarliyor, yani
       "min 48 / paket 10" sayfada 48, kasada 50 demek. */
$b2 = $base; $b2[0]['size_step'] = 10;
$write($b2);
[$rc, $out] = $run([['match' => 'TEST-TEE-01', 'moq' => 48,
    'tiers' => [['min'=>48,'price'=>19.0]]]]);
$t('5e moq paket adimina oturmuyorsa red', $rc !== 0 && str_contains($out, 'ilan edilen minimum alinamaz'));

/* 5f. mode='offer' urunde teklif kapatilamaz: sabit fiyati yok, kapaninca
       satin alinacak hicbir sey kalmaz. */
$b3 = $base; $b3[0]['mode'] = 'offer'; unset($b3[0]['offers']);
$write($b3);
[$rc, $out] = $run([['match' => 'TEST-TEE-01', 'offers' => 'off']]);
$t('5f offer modunda kapatma reddedildi', $rc !== 0 && str_contains($out, 'satin alinamaz hale gelir'));

/* 5g. Teklifi ACMAK: iki alan birden yazilmali. */
$b4 = $base; unset($b4[0]['offers']); $b4[0]['no_offers'] = true;
$write($b4);
[$rc, $out] = $run([['match' => 'TEST-TEE-01', 'offers' => 'on']]);
$after = $read();
$t('5g teklif acildi',            $rc === 0 && vestra_offers_open($after) === true);
$t('5g no_offers kaldirildi',     !isset($after['no_offers']));

/* 5h. sample_price=0 gecerli bir deger: eskiden "gecersiz fiyat" diye
       reddediliyordu, yani numuneyi kaldirmanin hicbir yolu yoktu. */
$write($base);
[$rc, $out] = $run([['match' => 'TEST-TEE-01', 'sample_price' => 0]]);
$t('5h sample_price=0 kabul',     $rc === 0 && vestra_sample_price($read()) === 0.0);
$t('5h cikti ne oldugunu yaziyor', str_contains($out, 'numune kutusu KALKAR'));

exec('rm -rf ' . escapeshellarg($sand));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
