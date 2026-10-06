<?php
/* Katalogda gorunmeyen urun — Musterstuck (operator karari, 2 Eyl 2026:
 * "musterstuck katalogta gorunmesin yada en asagilarda olsun").
 *
 * Iki yon birden tutulmali:
 *   1. Acik listeler (vitrin, fiyat listeleri, katalog dosyalari, sitemap,
 *      kampanya, API katalog) numuneyi GORMEZ.
 *   2. Aliciya mektupla verilen dogrudan baglanti, sepet ve siparis satiri
 *      HALA calisir -- gizlemek, satilamaz yapmak degil.
 *
 * Ucuncu bir tuzak: kod tabaninda bir acik sayfa yanlislikla
 * vestra_products(true) cagirirsa numune sessizce kataloga geri doner ve kimse
 * fark etmez. O yuzden asagida acik sayfalar kaynak duzeyinde taranir.
 */
require __DIR__.'/../vestra/inc/products.php';
require_once __DIR__.'/../vestra/inc/orders.php';

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$MID = 'lac-l1212-musterstueck';
$pub = vestra_products();
$all = vestra_products(true);
$ids = fn(array $list) => array_map(fn($p) => (string)($p['id'] ?? ''), $list);

echo "-- acik liste --\n";
$t('varsayilan vestra_products() Musterstuck icermez',        !in_array($MID, $ids($pub), true));
$t('vestra_products(true) Musterstuck icerir',                 in_array($MID, $ids($all), true));
$t('gizlenen tek kayit o (fark = 1)',                          count($all) - count($pub) === 1);
$t('varsayilan listede unlisted isaretli hicbir kayit yok',   !array_filter($pub, fn($p) => !empty($p['unlisted'])));
$t('liste 0..n-1 indeksli kaliyor (array_values)',            array_keys($pub) === range(0, count($pub) - 1));
$t('normal L1212 (lac-pique-polo) hala listede',               in_array('lac-pique-polo', $ids($pub), true));
$t('Polos kategorisi L1212 uzerinden hala var',                in_array('Polos', vestra_cats(), true));

echo "-- dogrudan yol --\n";
$f = vestra_find($MID);
$t('vestra_find dogrudan baglantiyi cozer',                    $f !== null && ($f['sku'] ?? '') === 'MUSTERSTUECK-L1212');
$t('60 EUR, MOQ 1, tek kademe',                                $f !== null && abs((float)$f['list'] - 60.0) < 0.001 && (int)$f['moq'] === 1 && count($f['tiers'] ?? []) === 1);
$t('vestra_from_price 60',                                      $f !== null && abs(vestra_from_price($f) - 60.0) < 0.001);
$t('unlisted bayragi kayitta duruyor',                          $f !== null && !empty($f['unlisted']));
$s = vestra_product_by_sku('MUSTERSTUECK-L1212');
$t('siparis satiri SKU ile cozulur (buyer/seller/admin panel)', $s !== null && ($s['id'] ?? '') === $MID);
$t('admin fiyat editoru override yoluna yazar (demo product)',  vestra_is_demo_product($MID));
$t('vestra_find bilinmeyen id icin null',                       vestra_find('yok-boyle-bir-sey') === null);

echo "-- kaynak taramasi --\n";
/* Acik sayfalar: hicbiri true ile okumamali. Yeni bir acik sayfa eklenirse
   buraya da eklenmeli. */
$publicFiles = ['shop.php','catalog.php','catalog-csv.php','catalog-pdf.php','price-list.php',
                'price-lists.php','wholesale.php','wholesale-list.php','wholesale-xlsx.php',
                'sitemap.php','index.php','product.php','dropship.php','requests.php',
                'api/catalog.php','api/dropship.php','inc/notify.php'];
foreach ($publicFiles as $pf) {
    $src = @file_get_contents(__DIR__.'/../vestra/'.$pf);
    $t("acik sayfa $pf vestra_products(true) cagirmiyor",
       $src !== false && !preg_match('/vestra_products\(\s*true\s*\)/', $src));
}
/* Kapali yollar: alicinin elinde id/SKU var, cozulmek zorunda. */
foreach (['cart.php','inc/orders.php','admin.php'] as $pf) {
    $src = @file_get_contents(__DIR__.'/../vestra/'.$pf);
    $t("$pf vestra_products(true) ile okur",
       $src !== false && preg_match('/vestra_products\(\s*true\s*\)/', $src) === 1);
}
$src = file_get_contents(__DIR__.'/../vestra/product.php');
$t('product.php unlisted urune noindex basar',
   str_contains($src, "\$p['unlisted']") && str_contains($src, '$NOINDEX = true'));
$src = file_get_contents(__DIR__.'/../vestra/inc/products.php');
$t('vestra_find vestra_products(true) uzerinden okur',
   preg_match('/function vestra_find\(\$id\)\{ foreach\(vestra_products\(true\)/', $src) === 1);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
