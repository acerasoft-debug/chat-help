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
$t('teklif blogu kapali',          str_contains($prod, "if(!\$SOLD && !empty(\$p['offers']))"));
$t('"Add to order" yerine pasif dugme', str_contains($prod, "if(\$SOLD): ?>") && str_contains($prod, 'disabled'));

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

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
