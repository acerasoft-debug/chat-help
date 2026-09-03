<?php
/* set-product'in EŞLEŞTİRİCİSİ: birebir yazilan bir id tek urunu bulmali.
 *
 * $norm() harf ve rakam disindaki her seyi atiyor -- bu, "XH16B005 BB04" ile
 * "xh16b005-bb04"u ayni sey saymak icin dogru; ama YALNIZCA tireyle ayrilan iki
 * ayri ilan da ayni metne iniyordu: 'pp-tobby-pirata' ve 'pp-tobbypirata' ikisi
 * de 'pptobbypirata'. Ikisi de gercek ilan oldugu icin hicbiri hedeflenemiyordu;
 * is "expect=1 ama 2 urune uydu" deyip duruyordu (3 Eyl 2026, ayakkabi seri
 * duzeltmesi 85 satirin 4'unde takildi). Cozum: birebir id/sku eslesmesi
 * hosgorulu eslesmeden ONCE deneniyor.
 *
 * Test betigi GERCEK haliyle kosturuyor (dry-run): eslestirme mantigi burada
 * kopyalanmiyor -- kopyalansaydi kod degisince test yine gecerdi.
 */
$root = dirname(__DIR__);
$listings = $root.'/vestra/data/listings.json';
if (file_exists($listings)) {
    fwrite(STDERR, "ATLANAMAZ: {$listings} zaten var. Test kendi katalogunu yaziyor;\n"
                 . "yerel dosyanizi ezmemek icin duruyor. Once tasiyin.\n");
    exit(1);
}

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$prod = fn(string $id, string $sku, string $name, string $img) => [
    'id'=>$id, 'sku'=>$sku, 'name'=>$name, 'brand'=>'Pili Pérez', 'cat'=>'Sandals',
    'moq'=>1, 'unit'=>'pair', 'mode'=>'fixed', 'list'=>14.5, 'tiers'=>[['min'=>1,'price'=>14.5]],
    'images'=>['/uploads/piliperez/'.$img], 'status'=>'approved', 'section'=>'footwear',
];
file_put_contents($listings, json_encode([
    $prod('pp-tobby-pirata', 'TOBBY PIRATA', 'Model Tobby pirata', 'tobbypirata-azul.jpg'),
    $prod('pp-tobbypirata',  'TOBBYPIRATA',  'Model TOBBYPIRATA',  'tobbypirata-azul.jpeg'),
    $prod('pp-1012',         '1012',         'Model 1012',         '1012-negro.jpg'),
]));

/* Betik $HOME/public_html bekliyor (sunucu duzeni). */
$home = sys_get_temp_dir().'/vestra_setprod_test_'.getmypid();
@mkdir($home, 0777, true);
@symlink($root.'/vestra', $home.'/public_html');

$run = function (array $fixes) use ($home, $root): array {
    $cmd = 'HOME='.escapeshellarg($home)
         . ' P_DRY=true P_JSON='.escapeshellarg(base64_encode(json_encode($fixes)))
         . ' php '.escapeshellarg($root.'/scripts/set_product.php').' 2>&1';
    exec($cmd, $out, $rc);
    return [$rc, implode("\n", $out)];
};

echo "-- birebir id, tire farkiyla catisan iki ilan --\n";
[$rc, $out] = $run([
    ['match'=>'pp-tobby-pirata', 'expect'=>1, 'moq'=>'11'],
    ['match'=>'pp-tobbypirata',  'expect'=>1, 'moq'=>'11'],
]);
$t('iki id de tek tek hedeflenebiliyor (cikis 0)',      $rc === 0);
$t('plan iki urunu de tasiyor',                          substr_count($out, 'moq 1 -> 11') === 2);
$t("'pp-tobby-pirata' satiri kendi urununu buldu",       str_contains($out, "pp-tobby-pirata            | Pili Pérez    | match='pp-tobby-pirata'"));
$t("'pp-tobbypirata' satiri kendi urununu buldu",        str_contains($out, "pp-tobbypirata             | Pili Pérez    | match='pp-tobbypirata'"));
$t('dry-run hicbir sey kaydetmedi',                      str_contains($out, 'DRY RUN'));

echo "-- hosgorulu eslesme duruyor ve hala koruyor --\n";
[$rc2, $out2] = $run([['match'=>'TOBBY PIRATA', 'expect'=>1, 'moq'=>'11']]);
$t('bosluklu SKU birebir eslesir (buyuk/kucuk + bosluk hosgorusu)', $rc2 === 0 && substr_count($out2, 'moq 1 -> 11') === 1);

[$rc3, $out3] = $run([['match'=>'pptobbypirata', 'expect'=>1, 'moq'=>'11']]);
$t('birebir olmayan yazim iki urune uyar ve is DUSER',   $rc3 !== 0 && str_contains($out3, '2 urune uydu'));

echo "-- alt dize yolu (model kodu gorsel adinda) --\n";
[$rc4, $out4] = $run([['match'=>'1012-negro', 'expect'=>1, 'moq'=>'17']]);
$t('gorsel adindan bulur',                               $rc4 === 0 && str_contains($out4, 'moq 1 -> 17'));

@unlink($listings);
@unlink($home.'/public_html');
@rmdir($home);
$t('test kendi katalogunu sildi',                        !file_exists($listings));

echo "\n$ok ok, $fail hata\n";
exit($fail ? 1 : 0);
