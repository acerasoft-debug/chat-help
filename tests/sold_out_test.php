<?php
/* SATILDI / STOK DISI (operator karari, 5 Eyl 2026: "satildi olarak isaretle
 * satin alinamasin stok disi" -- Jacquemus, 3 urun).
 *
 * NEDEN AYRI BIR ALAN: elde iki secenek vardi ve ikisi de bu isi yapmiyordu.
 * 'unlisted' ve status!=approved urunu katalogdan tumden CIKARIYOR -- ikisi de
 * "gizle" demek. Istenen ise urunun GORUNMESI ama SATIN ALINAMAMASI.
 *
 * BU TESTIN ASIL ISI: DUGME DEGIL KAPI. Bir dugmeyi gizlemek satisi durdurmaz;
 * form gonderen biri gecer. Bu depoda ayni ders dropship'te kayitli
 * (vestra_dropship_of() en basta bakar). Asagidaki 6 satis yolunun HEPSI
 * sunucu tarafinda kontrol ediyor mu, kaynak duzeyinde taraniyor.
 */
require_once __DIR__.'/../vestra/inc/products.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents(__DIR__.'/../vestra/'.$f);

echo "== 1. vestra_is_sold_out() ==\n";
$t('bayrak yoksa satilabilir',   !vestra_is_sold_out([]));
$t('false satilabilir',          !vestra_is_sold_out(['sold_out'=>false]));
$t('true satilamaz',              vestra_is_sold_out(['sold_out'=>true]));
/* JSON'dan string gelebiliyor: "false" STRINGI (bool)"false" ile TRUE olurdu ve
   urun satista kalirdi -- operator kapattigini sanip satmaya devam ederdi. */
$t('"false" STRINGI satilabilir', !vestra_is_sold_out(['sold_out'=>'false']));
$t('"0" satilabilir',             !vestra_is_sold_out(['sold_out'=>'0']));
$t('bos string satilabilir',      !vestra_is_sold_out(['sold_out'=>'']));
$t('"true" STRINGI satilamaz',    vestra_is_sold_out(['sold_out'=>'true']));
$t('"1" satilamaz',               vestra_is_sold_out(['sold_out'=>'1']));
$t('"yes" satilamaz',             vestra_is_sold_out(['sold_out'=>'yes']));

echo "\n== 2. ALTI SATIS YOLU sunucuda kapali mi ==\n";
/* Her biri ayri bir satis yolu. Birini unutmak, o yoldan satisi acik birakir. */
$t('order.php (sepet/siparis)',      preg_match('~vestra_is_sold_out\(\$p\)~', $src('order.php')) === 1);
$t('offer.php (teklif)',             preg_match('~vestra_is_sold_out\(\$p\)~', $src('offer.php')) === 1);
$t('sample-checkout.php (numune)',   preg_match('~vestra_is_sold_out\(\$p\)~', $src('sample-checkout.php')) === 1);
$t('dropship-checkout.php',          preg_match('~vestra_is_sold_out\(\$p\)~', $src('dropship-checkout.php')) === 1);
$t('linesheet.php (fiyat listesi)',  preg_match('~vestra_is_sold_out\(\$p\)~', $src('linesheet.php')) === 1);
/* Grup alimi havuzun KAYNAGINDA kesiliyor -- hem /groups listesi hem
   group-checkout ayni fonksiyondan geciyor. */
$t('vestra_group_pool() null doner', vestra_group_pool_gate_ok());
function vestra_group_pool_gate_ok(): bool {
    $s = (string)@file_get_contents(__DIR__.'/../vestra/inc/products.php');
    return preg_match('~function vestra_group_pool\([^)]*\)\{[^}]*vestra_is_sold_out~', $s) === 1;
}
$t('/groups listesi de eliyor',
   preg_match('~vestra_group_pools\(\)\{.*?!vestra_is_sold_out~s', (string)@file_get_contents(__DIR__.'/../vestra/inc/products.php')) === 1);

echo "\n== 3. Gorunur kaliyor (gizlenmiyor) ==\n";
/* SATILDI ≠ GIZLI: sayfa ve kart ayakta, SEO ve gelen baglantilar korunuyor. */
$prod = $src('product.php'); $shop = $src('shop.php');
$t('urun sayfasi rozet basiyor',      str_contains($prod, "t('Sold out')"));
$t('katalog karti rozet basiyor',     str_contains($shop, 'ssoldbadge'));
$t('kart katalogdan CIKARILMIYOR',    !preg_match('~vestra_is_sold_out\(\$p\)\s*\)\s*continue~', $shop));
$t('fotograf soluyor ama gizlenmiyor', str_contains($shop, 'sthumb-sold') && !str_contains($shop, '.sthumb-sold{display:none'));

echo "\n== 4. Urun sayfasinda satin alma bloklari kapali ==\n";
$t('tek karar degiskeni ($SOLD)',  preg_match('~\$SOLD\s*=.*vestra_is_sold_out~', $prod) === 1);
$t('grup alimi banneri kapali',    str_contains($prod, "if(!\$SOLD && !empty(\$p['group']))"));
$t('dropship dugmesi kapali',      str_contains($prod, "if(!\$SOLD && !\$isOwnListingTop"));
$t('numune blogu kapali',          str_contains($prod, "if(!\$SOLD && !\$isOwnListing"));
/* Kosulun teklif yarisi vestra_offers_open()'a tasindi (7 Eyl 2026): urun
   sayfasi ile /offer ucu artik AYNI fonksiyonu okuyor. Burada tutulan sey
   degismedi -- satilmis ilanda teklif kutusu cizilmiyor. */
$t('teklif blogu kapali',          str_contains($prod, "if(!\$SOLD && vestra_offers_open(\$p))"));
$t('"Add to order" yerine pasif dugme', str_contains($prod, "if(\$SOLD): ?>") && str_contains($prod, 'disabled'));
/* Dugmeyi kaldirmak, ona DOKUNAN JS'i de gozden gecirmeyi gerektiriyor. recalc()
   sayfa yuklenirken kosuyor ve #addBtn artik yok: korumasiz btn.disabled ilk
   satirda TypeError atar, altindaki fiyat/kademe/toplam hic yazilmaz. Satis yine
   kapali kalirdi (kapi sunucuda) ama sayfa bozuk gorunurdu. */
$t('recalc() btn null olabilir diye koruyor',
   !str_contains($prod, 'btn.disabled=true') && !str_contains($prod, 'btn.disabled=false')
   && str_contains($prod, 'if(btn) btn.disabled=v'));

echo "\n== 5. set_product.php alani kabul ediyor ve DOGRULUYOR ==\n";
$sp = (string)@file_get_contents(__DIR__.'/../scripts/set_product.php');
$t("ALLOWED'da sold_out var",   str_contains($sp, "'sold_out'"));
$t("ALLOWED'da preorder_ship var", str_contains($sp, "'preorder_ship'"));
/* "false" STRINGI kabul edilirse operator kapattigini sanip satmaya devam eder. */
$t('sold_out yalniz gercek bool', str_contains($sp, "!is_bool(\$set['sold_out'])"));
$t('preorder_ship tarih bicimi dogrulaniyor', str_contains($sp, 'preorder_ship YYYY-MM-DD olmali'));

echo "\n== 6. Metinler 9 dilde ==\n";
$missing = [];
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lg) {
    $d = @include __DIR__."/../vestra/inc/lang/$lg.php";
    if (!is_array($d) || trim((string)($d['Sold out'] ?? '')) === ''
        || trim((string)($d['This item is no longer available to order.'] ?? '')) === '') $missing[] = $lg;
}
$t('Sold out 8 sozlukte: '.($missing ? implode(', ', $missing).' EKSIK' : 'hepsi var'), $missing === []);

echo "\n== 7. YAZMA: stoga GERI ALMA da calisiyor mu (betik gercekten kosuyor) ==\n";
/* §5 alanin KABUL edildigini tariyor; bu bolum ne YAZILDIGINI olcuyor.
 * Fark onemliydi: 16 Eyl 2026'ya kadar set_product.php'nin sold_out icin ayri
 * bir yazma dali YOKTU ve alan en asagidaki genel dala dusuyordu --
 * `$new = (string)$v`. PHP'de (string)false = "" demek, yani stoga geri
 * alinan bir ilan kayda `sold_out: ""` diye iniyordu. Kaynak taramasi bunu
 * GOREMEZ; ancak betigi kosturup kaydi geri okuyunca cikiyor.
 *
 * Bugun o bos dizge zararsiz (her okuyan vestra_is_sold_out()'tan geciyor ve
 * onu false okuyor) ama `isset()`/`array_key_exists()` ile soracak bir okuyan
 * SATISTA olan urunu "satildi" sayardi. Asagidaki is_bool iddiasi tam bunu
 * tutuyor: "satilabilir mi" dogru cikiyor diye gecmesin, DEGERIN KENDISI de
 * bool olsun. */
$root     = dirname(__DIR__);
$listings = $root.'/vestra/data/listings.json';
if (file_exists($listings)) {
    fwrite(STDERR, "ATLANAMAZ: {$listings} zaten var. Bu bolum kendi katalogunu\n"
                 . "yaziyor; yerel dosyanizi ezmemek icin duruyor. Once tasiyin.\n");
    exit(1);
}
@mkdir(dirname($listings), 0777, true);
$home = sys_get_temp_dir().'/vestra_soldout_test_'.getmypid();
@mkdir($home, 0777, true);
@symlink($root.'/vestra', $home.'/public_html');

$seed = function () use ($listings) {
    file_put_contents($listings, json_encode([
        ['id'=>'lac-a','brand'=>'Lacoste','cat'=>'Polos','mode'=>'fixed','name'=>'Alpha Polo Shirt',
         'moq'=>10,'list'=>70.20,'tiers'=>[['min'=>10,'price'=>70.20]],'sold_out'=>true],
        ['id'=>'lac-b','brand'=>'Lacoste','cat'=>'Polos','mode'=>'fixed','name'=>'Beta Polo Shirt',
         'moq'=>10,'list'=>70.20,'tiers'=>[['min'=>10,'price'=>70.20]]],
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
};
$run = function (array $fixes, bool $apply) use ($home, $root): array {
    $cmd = 'HOME='.escapeshellarg($home)
         . ' P_DRY='.escapeshellarg($apply ? 'false' : 'true')
         . ' P_JSON='.escapeshellarg(base64_encode(json_encode($fixes)))
         . ' php '.escapeshellarg($root.'/scripts/set_product.php').' 2>&1';
    $out = []; $rc = 0; exec($cmd, $out, $rc);
    return [$rc, implode("\n", $out)];
};
$byId = function () use ($listings): array {
    $m = [];
    foreach (json_decode((string)file_get_contents($listings), true) ?: [] as $p) $m[$p['id']] = $p;
    return $m;
};
$raw = fn(string $id) => ($byId())[$id]['sold_out'] ?? '(alan yok)';

/* 7a. STOGA GERI ALMA — bu isin ta kendisi (operator, 16 Eyl 2026:
       "onlari yeniden stoga gelicek yap"). */
$seed();
[$rc, $out] = $run([['match'=>'Alpha Polo Shirt', 'expect'=>1, 'sold_out'=>false]], true);
$t('geri alma kosusu basarili (cikis 0)', $rc === 0);
$t('cikti degisikligi yaziyor',           str_contains($out, 'sold_out true -> false'));
$a = ($byId())['lac-a'] ?? [];
$t('lac-a artik SATILABILIR',            !vestra_is_sold_out($a));
/* ASIL IDDIA: deger bos dizge degil GERCEK bool. Genel dal geri gelirse
   "satilabilir" yine dogru cikar ve yalniz bu satir kirmizi doner. */
$t('lac-a degeri gercek bool (is_bool)',  is_bool($a['sold_out'] ?? null));
$t('lac-a degeri bos dizge DEGIL: '.var_export($raw('lac-a'), true),
                                          ($a['sold_out'] ?? null) !== '');
$t('kardes ilan (lac-b) degismedi',      !vestra_is_sold_out(($byId())['lac-b'] ?? []));

/* 7b. Ters yon — kapatma hala calisiyor (tek yon yazilsaydi test yesil kalir,
       stok disi birakma sessizce bozulurdu). */
$seed();
[$rc2, $out2] = $run([['match'=>'Beta Polo Shirt', 'expect'=>1, 'sold_out'=>true]], true);
$b = ($byId())['lac-b'] ?? [];
$t('kapatma kosusu basarili',             $rc2 === 0);
$t('cikti kapatmayi yaziyor',             str_contains($out2, 'sold_out false -> true'));
$t('lac-b artik SATILAMAZ',               vestra_is_sold_out($b));
$t('lac-b degeri gercek bool',            is_bool($b['sold_out'] ?? null));

/* 7c. Zaten stokta olan ilana `false` yazmak DEGISIKLIK DEGIL -- yoksa her
       kosu yedek alip "guncellendi" derdi ve gercek bir degisiklik gurultude
       kaybolurdu. */
$seed();
[, $out3] = $run([['match'=>'Beta Polo Shirt', 'expect'=>1, 'sold_out'=>false]], false);
$t('degismeyen alan icin degisiklik satiri yok', !str_contains($out3, 'sold_out'));

/* 7d. KURU KOSU HICBIR SEY YAZMAZ (varsayilan; KURAL 18'in urun tarafi). */
$seed();
$before = (string)file_get_contents($listings);
$run([['match'=>'Alpha Polo Shirt', 'expect'=>1, 'sold_out'=>false]], false);
$t('kuru kosu dosyaya dokunmadi',         (string)file_get_contents($listings) === $before);

/* 7e. expect UYUSMAZSA HICBIR SEY yazilmaz -- iki poloyu ada gore eslestiren
       bu isin tek guvencesi bu. "Polo Shirt" iki urune birden uyar. */
$seed();
[$rc5, $out5] = $run([['match'=>'Polo Shirt', 'expect'=>1, 'sold_out'=>false]], true);
$t('belirsiz eslesmede is DURUYOR (cikis != 0)', $rc5 !== 0);
$t('belirsiz eslesmede kayit DEGISMEDI',   vestra_is_sold_out(($byId())['lac-a'] ?? []));

@unlink($listings);
@unlink($home.'/public_html');
@rmdir($home);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
