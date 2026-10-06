<?php
/* MARKA BASINA ASGARI SEPET TUTARI — KURAL 21 (operator, 10 Eyl 2026)
 *
 * *"komple marka secildiginde en az alim 300 eur olacak sekilde"*
 *   -> *"en az alimi 500 usd yap"*
 *   -> kurun EUR fiyatli bir kataloga ne yapacagi anlatilinca *"eur yap"* + *"degismesin"*.
 *
 * Tutulan ilkeler:
 *   - KAPI SUNUCUDA. Sepetteki uyari bir gorunum tercihi; dugmeyi gizlemek kapi
 *     degildir (KURAL 4b: /offer ucu ilanin teklif alip almadigina hic bakmiyordu
 *     ve elle POST atan biri gercek bir teklif birakabiliyordu).
 *   - RAKAM TEK SABITTE (KURAL 6: escrow tavani bes gun boyunca metne gomulu
 *     kaldi, musteriye soylenen ile sepetin kabul ettigi ayri kaldi).
 *   - IKI YON DE TUTULUYOR. Yalnizca "eksik sepet engelleniyor mu" yazilsaydi,
 *     esigi butun katalogaya uygulayan bir hata testi YESIL birakir ve her
 *     siparisi sessizce durdururdu -- mango/zara dersinin asgari-tutar hali.
 *   - BIRIM EUR ve CEVRILMIYOR: gosterim birimine cevrilmis bir esik, operatorun
 *     "degismesin" dedigi seyi tam da ekranda degistirirdi.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents($root . '/' . $f);

echo "== 1. Sabit ve tablo ==\n";
$t('esik 500.00 EUR',                    abs(VESTRA_BRAND_MIN_ORDER_EUR - 500.0) < 0.0001);
$t('NBB tabloda',                        abs(vestra_brand_min_order('NBB') - VESTRA_BRAND_MIN_ORDER_EUR) < 0.0001);
$t('buyuk/kucuk harf farketmiyor',       abs(vestra_brand_min_order('nbb') - VESTRA_BRAND_MIN_ORDER_EUR) < 0.0001
                                      && abs(vestra_brand_min_order('  Nbb ') - VESTRA_BRAND_MIN_ORDER_EUR) < 0.0001);
/* Kapsam DAR: kural yalnizca tabloda adi gecen markaya isliyor. Bu iddia
   dusmezse "asgari" butun katalogaya yayilmis demektir. */
$t('Gucci\'nin asgarisi YOK',            vestra_brand_min_order('Gucci') === 0.0);
$t('bos marka adi = asgari yok',         vestra_brand_min_order('') === 0.0);
/* TAM eslesme, alt dize degil: "NBB" gecen baska bir marka (varsayimsal
   "NBB Kids", ya da bir gun katalogda "Bonbon NBB") kendiliginden kapiya
   girmemeli -- bu deponun mango/zara dersi. */
$t('alt dize eslesmiyor (NBB Kids)',     vestra_brand_min_order('NBB Kids') === 0.0);
$t('alt dize eslesmiyor (Bonbon NBB)',   vestra_brand_min_order('Bonbon NBB') === 0.0);

echo "\n== 2. Eksik hesabi ==\n";
$L = fn(string $b, float $line) => ['brand' => $b, 'line' => $line];

$t('bos sepet gecer',                    vestra_brand_min_shortfall([]) === []);
$t('asgarisi olmayan marka gecer',       vestra_brand_min_shortfall([$L('Gucci', 12.0)]) === []);

$short = vestra_brand_min_shortfall([$L('NBB', 320.50)]);
$t('320.50 EUR EKSIK',                   isset($short['NBB']));
$t('eksik = 179.50',                     isset($short['NBB']) && abs($short['NBB']['short'] - 179.50) < 0.0001);
$t('elde = 320.50',                      isset($short['NBB']) && abs($short['NBB']['have'] - 320.50) < 0.0001);
$t('esik raporlaniyor',                  isset($short['NBB']) && abs($short['NBB']['min'] - 500.0) < 0.0001);

$t('500.00 tam esikte GECER',            vestra_brand_min_shortfall([$L('NBB', 500.0)]) === []);
$t('500.01 gecer',                       vestra_brand_min_shortfall([$L('NBB', 500.01)]) === []);
$t('499.99 GECMEZ',                      isset(vestra_brand_min_shortfall([$L('NBB', 499.99)])['NBB']));

/* Satirlar TOPLANIYOR: ayni markadan uc ilan tek basina esigin altinda ama
   birlikte gecmeli -- olcu "komple marka", tek ilan degil. */
$t('ayni markanin 3 satiri toplaniyor',  vestra_brand_min_shortfall([$L('NBB', 200.0), $L('NBB', 180.0), $L('NBB', 130.0)]) === []);
/* ...ve KARISIK sepette baska markanin tutari NBB'yi kurtarmaz. */
$mixed = vestra_brand_min_shortfall([$L('NBB', 100.0), $L('Gucci', 4000.0)]);
$t('baska markanin tutari sayilmaz',     isset($mixed['NBB']) && abs($mixed['NBB']['short'] - 400.0) < 0.0001);
$t('...ve o marka listeye girmiyor',     !isset($mixed['Gucci']));
/* Kayan nokta: 0.1+0.2 klasigi tam sinirdaki bir sepeti reddetmesin. */
$t('kayan nokta toleransi (250.1+249.9)', vestra_brand_min_shortfall([$L('NBB', 250.1), $L('NBB', 249.9)]) === []);

echo "\n== 3. Kapi SUNUCUDA, ve tek kaynaktan ==\n";
$ord = $src('order.php');
$t('order.php eksigi soruyor',           str_contains($ord, 'vestra_brand_min_shortfall($lines)'));
$t('...ve engelliyor',                   str_contains($ord, "err=brandmin"));
/* Kapi, satirlar YENIDEN FIYATLANDIKTAN sonra: alicinin gonderdigi tutar degil,
   katalogdan hesaplanan tutar olculuyor. */
$t('kapi yeniden fiyatlamadan SONRA',    strpos($ord, 'vestra_brand_min_shortfall') > strpos($ord, "if(!\$lines){ header('Location: /cart'); exit; }"));

$cart = $src('cart.php');
$t('sepet JS ayni tablodan basiyor',     str_contains($cart, 'json_encode(vestra_brand_min_orders()'));
$t('sepet uyari kutusu var',             str_contains($cart, "id=\"brandMinNote\""));
$t('sepet her cizimde yeniden bakiyor',  str_contains($cart, 'syncBrandMin(c);'));
$t('urun sayfasi esigi yaziyor',         str_contains($src('product.php'), 'vestra_brand_min_order((string)($p[\'brand\'] ?? \'\'))'));

echo "\n== 4. Rakam metne GOMULU DEGIL ==\n";
/* KURAL 6'nin taramasinin aynisi: musteriye gorunen uc dosyada ciplak 500
   gecmemeli. Gecerse esik iki yerde tanimlanmis demektir ve ikisi bir gun
   ayrisir. Tarama DAR: yalnizca para bicimindeki yazimlar (500, 500.00,
   "500 EUR") -- "500" bir gun bir piksel olcusu ya da bir zaman asimi olarak
   gecebilir ve genis bir tarama gercek kodu bosuna kirmizi yapardi. */
foreach (['cart.php' => $cart, 'product.php' => $src('product.php'), 'order.php' => $ord] as $f => $s) {
    $t("$f: gomulu 500 yok", !preg_match('~(?<![\d.])500(?:[.,]0{1,2})?\s*(?:EUR|€|eur)|[€]\s*500~u', $s));
}
$t('sabit yalnizca products.php\'de',
   substr_count($src('inc/products.php'), 'VESTRA_BRAND_MIN_ORDER_EUR = ') === 1);

echo "\n== 5. Metin 8 dilde ==\n";
/* Eksik anahtar t() yuzunden SESSIZCE Ingilizceye duser: alici Almanca bir
   sayfada Ingilizce bir engel gorur ve kimse fark etmez (KURAL 10). */
$keys = [
    'Minimum order value',
    'Minimum order for %1$s is %2$s.',
    'Your cart has %1$s of %2$s — add %3$s to place the order.',
];
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lang) {
    $d = require $root . '/inc/lang/' . $lang . '.php';
    foreach ($keys as $k) {
        $t("$lang: " . mb_substr($k, 0, 26), isset($d[$k]) && trim((string)$d[$k]) !== '');
    }
    /* Yer tutucular korunmus mu: %3$s'i dusuren bir ceviri, alicinin ne kadar
       eklemesi gerektigini SILER ve cumle "ekleyin" deyip rakami vermez. */
    foreach ($keys as $k) {
        $want = substr_count($k, '%');
        $t("$lang: yer tutucu sayisi " . mb_substr($k, 0, 18), substr_count((string)($d[$k] ?? ''), '%') === $want);
    }
}

echo "\n== 6. Esik EUR yaziliyor, gosterim birimine cevrilmiyor ==\n";
/* Sepette ve urun sayfasinda vestra_money(..., 'EUR') cagriliyor: ikinci
   argumansiz cagri ziyaretcinin sectigi para birimine cevirir ve "degismesin"
   denen esik ekranda dalgalanirdi. */
$t('urun sayfasi EUR zorluyor',          str_contains($src('product.php'), "vestra_money(\$pMinBrand, 'EUR')"));
$t('sepet banneri EUR zorluyor',         preg_match("~vestra_money\(.*, 'EUR'\)~", $cart) === 1);
/* JS tarafi eur() kullaniyor -- o fonksiyon her zaman € basiyor (dosyanin
   kendi tanimi), yani cevrim yolu oradan da gecmiyor. */
$t('JS eur() her zaman €',               str_contains($cart, "function eur(n){ return '€'"));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
