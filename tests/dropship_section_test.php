<?php
/* Dropship'e kapali VITRIN BOLMESI — ayakkabi (operator karari, 3 Eyl 2026:
 * "Buy a single piece — dropshipping, tum ayakkabilardan kaldir").
 *
 * Kural bolmeye bagli, ilana degil: Footwear bolmesindeki her ilan dropship'e
 * kapali dogar, elle acilmis bir dropship blogu bile gecmez (marka yasagiyla
 * ayni oncelik). Ayni ilan Premium bolmesinde olsaydi acik olurdu -- yani
 * kapatan sey fiyat/kademe/mod degil, bolmenin kendisi.
 *
 * Urun sayfasi, /dropship, odeme ve API hepsi vestra_dropship_enabled()'a
 * bakiyor; bu test o tek kapiyi sinar.
 */
require __DIR__.'/../vestra/inc/products.php';
require_once __DIR__.'/../vestra/inc/dropship.php';

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$shoe = [
    'id'=>'pp-test-1012', 'brand'=>'Pili Pérez', 'name'=>'Model 1012', 'cat'=>'Sneakers', 'sku'=>'1012',
    'list'=>22.95, 'moq'=>1, 'unit'=>'pair', 'mode'=>'fixed', 'status'=>'approved',
    'tiers'=>[['min'=>1,'price'=>22.95]], 'images'=>['/uploads/piliperez/1012-negro.jpg'],
    'section'=>'footwear',
];

echo "-- bolme kurali --\n";
$t('excluded_sections yalnizca gecerli bolme anahtarlari icerir',
   !array_diff(vestra_dropship_excluded_sections(), array_keys(vestra_sections())));
$t('footwear kapali bolmeler arasinda',                          in_array('footwear', vestra_dropship_excluded_sections(), true));
$t('ayakkabi ilani dropship\'e KAPALI',                          vestra_dropship_of($shoe) === null && !vestra_dropship_enabled($shoe));

$premium = $shoe; $premium['section'] = 'premium';
$ds = vestra_dropship_of($premium);
$t('AYNI ilan premium bolmesinde olsaydi ACIK olurdu (turetilmis)', is_array($ds) && !empty($ds['derived']));
$t('turetilmis fiyat = taban x 1.20',                            is_array($ds) && abs($ds['price'] - round(22.95 * 1.20, 2)) < 0.001);

$noSection = $shoe; unset($noSection['section']);
$t('bolme alani bos ilan varsayilan (premium) sayilir ve acik kalir', vestra_dropship_of($noSection) !== null);

echo "-- kural elle acmayi da eziyor --\n";
$manual = $shoe; $manual['dropship'] = ['enabled'=>true, 'price'=>30.0];
$t('elle "enabled:true" yazilmis ayakkabi da KAPALI',            vestra_dropship_of($manual) === null);

/* IC CAMASIRI (operator, 10 Eyl 2026: "sadece toptan olmali").
   Iki yon de tutuluyor: kapanmasi gereken KAPALI, ve kapanmamasi gereken ACIK.
   Tek yon yazilsaydi excluded_sections'a 'premium' eklemek testi yesil birakir
   ve butun katalogu sessizce dropship'e kapatirdi. */
echo "-- ic camasiri bolmesi (toptan) --\n";
$ic = [
    'id'=>'nbb-test-1901', 'brand'=>'NBB', 'name'=>'Model 1901', 'cat'=>'Underwear', 'sku'=>'1901',
    'list'=>9.90, 'moq'=>6, 'unit'=>'pack', 'mode'=>'fixed', 'status'=>'approved',
    'tiers'=>[['min'=>1,'price'=>9.90]], 'images'=>['/uploads/kuloglu/nbb-1901.jpg'],
    'section'=>'underwear',
];
$t('underwear kapali bolmeler arasinda',   in_array('underwear', vestra_dropship_excluded_sections(), true));
$t('ic camasiri ilani dropship\'e KAPALI', vestra_dropship_of($ic) === null && !vestra_dropship_enabled($ic));
$icManual = $ic; $icManual['dropship'] = ['enabled'=>true, 'price'=>14.0];
$t('elle acilmis ic camasiri da KAPALI',   vestra_dropship_of($icManual) === null);
$icPremium = $ic; $icPremium['section'] = 'premium';
$t('AYNI ilan premium olsaydi ACIK olurdu', vestra_dropship_of($icPremium) !== null);

echo "-- bolme adi cevrilebilir --\n";
$t('vestra_section_label(footwear) = Footwear',                  vestra_section_label('footwear') === 'Footwear');

echo "\n$ok ok, $fail hata\n";
exit($fail ? 1 : 0);
