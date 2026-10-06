<?php
/* set-product kuru kosusunun "SAYFADA" satiri: ustu cizili (list) fiyat ve rozet.
 * (4 Eki 2026, Ralph Lauren Custom Slim Fit Polo -- operator: "fiyati yuzde 25
 * indirimli goster ... 29,90 ... 160+ 25 eur")
 *
 * scripts/set_product.php'nin sale_list dali kuru kosuda "gorunen indirim ~%X"
 * yaziyordu ve X'i ESKI ilk kademeden (tiers[0]) hesapliyordu. Sayfanin rozeti
 * ise EN DUSUK kademeden hesaplaniyor (vestra_discount -> vestra_from_price).
 * Iki kademeli 26,90/25,00 bir ilanda list 33,33 icin satir "%19" diyor, sayfa
 * "-%25" basiyordu; kademeler AYNI satirda degisiyorsa X henuz yazilmamis eski
 * merdivenden cikiyordu. Operatorun onizlemesi sayfanin gostereceginden baska
 * bir rakam soyluyordu.
 *
 * Bu test betigi gercekten kosturuyor (kaynak taramasi burada hicbir sey olcmez:
 * soru, calisma zamaninda hangi rakamin basildigi) ve ASIL iddiayi sayfanin KENDI
 * fonksiyonuyla kuruyor: kuru kosunun yazdigi rozet, yazilan kaydi
 * vestra_discount()'in okudugu rozetle ayni olmali.
 */
require_once __DIR__.'/../vestra/inc/products.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$root     = dirname(__DIR__);
$listings = $root.'/vestra/data/listings.json';
if (file_exists($listings)) {
    fwrite(STDERR, "ATLANAMAZ: {$listings} zaten var. Bu test kendi katalogunu yaziyor;\n"
                 . "yerel dosyanizi ezmemek icin duruyor. Once tasiyin.\n");
    exit(1);
}
@mkdir(dirname($listings), 0777, true);
$home = sys_get_temp_dir().'/vestra_sale_view_test_'.getmypid();
@mkdir($home, 0777, true);
@symlink($root.'/vestra', $home.'/public_html');

/* Canli polonun sekli (inspect-products, 4 Eki 2026): mode=sale, list 26,90,
   kademeler 80+ -> 26,90 | 160+ -> 25,00, MOQ 80, paket adimi 8. */
$seed = function () use ($listings) {
    file_put_contents($listings, json_encode([
        ['id'=>'rl-a','brand'=>'Ralph Lauren','cat'=>'Polos','name'=>'Custom Slim Fit Polo Shirt',
         'mode'=>'sale','moq'=>80,'size_step'=>8,'list'=>26.90,
         'tiers'=>[['min'=>80,'price'=>26.90],['min'=>160,'price'=>25.00]],'status'=>'approved'],
        // sabit fiyatli ilan: sale_list burada sayfaya HIC yansimaz
        ['id'=>'rl-fixed','brand'=>'Ralph Lauren','cat'=>'T-Shirts','name'=>'Fixed Tee',
         'mode'=>'fixed','moq'=>20,'list'=>40.00,'tiers'=>[['min'=>20,'price'=>40.00]],'status'=>'approved'],
        // kontrol grubu: baska marka -- hicbir kosuda degismemeli
        ['id'=>'lac-x','brand'=>'Lacoste','cat'=>'Polos','name'=>'Control Polo','mode'=>'sale','moq'=>10,
         'list'=>70.20,'tiers'=>[['min'=>10,'price'=>63.90]],'status'=>'approved'],
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
/* Bir ilanin PLAN satirlari (ilan basligindan sonraki ilan basligina kadar). */
$block = function (string $out, string $id): string {
    $o = []; $in = false;
    foreach (explode("\n", $out) as $ln) {
        if (preg_match('/^  (\S+)\s+\| /', $ln, $mm)) { $in = ($mm[1] === $id); continue; }
        if (str_starts_with($ln, 'degisecek alan:') || str_starts_with($ln, '  MARKA KAPSAMI')) break;
        if ($in) $o[] = $ln;
    }
    return implode("\n", $o);
};

/* Operatorun degisikligi: 100 adetten asagisi 29,90, 100+ eski 26,90, 160+ 25,00;
   "yuzde 25 indirimli goster" -> list = 25,00 / 0,75 = 33,33. */
$ladder = [
    'match'=>'rl-a','expect'=>1,
    'tiers'=>[['min'=>80,'price'=>29.90],['min'=>100,'price'=>26.90],['min'=>160,'price'=>25.00]],
    'sale_list'=>33.33,
];

echo "\n== 1. KURU KOSU: tiers + sale_list AYNI satirda -- rozet SAYFANIN hesabiyla ==\n";
$seed();
$before = (string)file_get_contents($listings);
[$rc, $out] = $run([$ladder], false);
$b = $block($out, 'rl-a');
$t('kuru kosu basarili (cikis 0)',                           $rc === 0);
$t('dosyaya dokunulmadi',                                    (string)file_get_contents($listings) === $before);
$t('SAYFADA satiri var',                                     str_contains($b, 'SAYFADA:'));
$t('ustu cizili fiyat 33.33',                                str_contains($b, 'ustu cizili €33.33'));
$t('from = EN DUSUK kademe (25.00), ilk kademe (29.90) degil', str_contains($b, 'from €25.00') && !str_contains($b, 'from €29.90'));
$t('rozet -%25 (en dusuk kademeye gore)',                    str_contains($b, 'rozet -%25'));
/* ESKI hata: base = eski tiers[0] (26.90) -> (33.33-26.90)/33.33 = %19. */
$t('ESKI "gorunen indirim ~%" ifadesi yok',                  !str_contains($b, 'gorunen indirim ~%'));
$t('ESKI formulun rakami (%19) hicbir yerde yok',            !str_contains($b, '%19'));
/* Paket adimi uyarisi DOGRULAMA asamasinda basiliyor (ilan basliginin ustunde),
   yani ilan blogunda degil tum ciktida aranir. */
$t('paket adimina oturmayan kademe UYARISI (100, adim 8 -> 104)', str_contains($out, 'UYARI (rl-a): kademe 100 paket adimi 8')
                                                                  && str_contains($out, 'fiyat 104 adette devreye girer'));
$t('160 uyari almiyor (20 x 8)',                             !str_contains($out, 'kademe 160 paket'));
$t('degisecek alan: 2 (tiers + sale_list)',                  str_contains($out, 'degisecek alan: 2'));
$t('mode bu betikte DEGISMEZ ve soylenmiyor (sale kalir)',   !str_contains($b, "mode "));

echo "\n== 2. UYGULAMA: kayda inen deger, ve SAYFANIN kendi fonksiyonlarinin gordugu ==\n";
$seed();
$ctl = json_encode(($byId())['lac-x'] ?? null);
$fixedBefore = json_encode(($byId())['rl-fixed'] ?? null);
[$rc2, $out2] = $run([$ladder], true);
$m = $byId(); $a = $m['rl-a'] ?? [];
$t('uygulama basarili',                                      $rc2 === 0 && str_contains($out2, 'KAYDEDILDI'));
$t('list 33.33 yazildi',                                     abs((float)($a['list'] ?? 0) - 33.33) < 0.001);
/* json_encode tam sayili bir float'i (25.0) JSON_PRESERVE_ZERO_FRACTION olmadan "25"
   yaziyor ve diskten int(25) donuyor -- ayni fiyat, farkli PHP tipi. Kati === bunu
   kayip sanir (bu depoda bir kez yasandi); tipi normallestirip karsilastiriyoruz. */
$norm = fn($ts) => array_map(fn($r) => [(int)$r['min'], round((float)$r['price'], 2)], (array)$ts);
$t('uc basamak yazildi (80/100/160)',                        $norm($a['tiers'] ?? []) == [[80, 29.90], [100, 26.90], [160, 25.00]]);
$t('mode sale kaldi',                                        ($a['mode'] ?? '') === 'sale');
$t('MOQ ve paket adimi AYNEN (80 / 8)',                      (int)($a['moq'] ?? 0) === 80 && (int)($a['size_step'] ?? 0) === 8);
/* ASIL IDDIA: sayfanin rozeti = kuru kosunun yazdigi rozet. */
preg_match('/rozet -%(\d+)/', $b, $bm);
$t('SAYFA fonksiyonu yazilan kayitta -%25 okuyor',           vestra_discount($a) === 25);
$t('kuru kosunun yazdigi rozet == sayfanin okudugu rozet',   isset($bm[1]) && (int)$bm[1] === vestra_discount($a));
$t('sayfa bu kaydi "sale" gosteriyor (rozet gorunur)',       vestra_display_mode($a) === 'sale');
$t('from fiyati 25.00 (sayfa fonksiyonu)',                   abs(vestra_from_price($a, true) - 25.00) < 0.001);
/* Sepetin TAHSIL ETTIGI: 8'in katlari. 96 < 100, 104 >= 100. */
$t('MOQ 80 adette 29.90',                                    abs(vestra_unit_price($a, 80, true) - 29.90) < 0.001);
$t('96 adette 29.90 (100in altinda)',                        abs(vestra_unit_price($a, 96, true) - 29.90) < 0.001);
$t('104 adette 26.90 (100+ basamagi)',                       abs(vestra_unit_price($a, 104, true) - 26.90) < 0.001);
$t('160 adette 25.00',                                       abs(vestra_unit_price($a, 160, true) - 25.00) < 0.001);
$t('KONTROL GRUBU: baska marka AYNEN',                       json_encode($m['lac-x'] ?? null) === $ctl);
$t('KONTROL GRUBU: sabit fiyatli kardes AYNEN',              json_encode($m['rl-fixed'] ?? null) === $fixedBefore);
$t('yedek alindi',                                           count(glob($listings.'.bak-*')) >= 1);

echo "\n== 3. TEKRAR KOSU: degisiklik yoksa SAYFADA satiri \"zaten istenen durumda\"yi ezmez ==\n";
[$rc3, $out3] = $run([$ladder], false);
$b3 = $block($out3, 'rl-a');
$t('tekrar kosu: degisecek alan 0',                          $rc3 === 0 && str_contains($out3, 'degisecek alan: 0'));
$t('"(zaten istenen durumda)" duruyor',                      str_contains($b3, '(zaten istenen durumda)'));
$t('SAYFADA / UYARI satiri YOK (degisen bir sey yok)',       !str_contains($b3, 'SAYFADA') && !str_contains($b3, 'UYARI'));

echo "\n== 4. mode='sale' OLMAYAN ilanda sale_list sayfaya HIC yansimaz -- sessiz olmamali ==\n";
$seed();
[$rc4, $out4] = $run([['match'=>'rl-fixed','expect'=>1,'sale_list'=>50.00]], false);
$b4 = $block($out4, 'rl-fixed');
$t('kuru kosu basarili',                                     $rc4 === 0);
$t('UYARI: mode fixed -> sayfa ustu cizili fiyati/rozeti HIC basmaz', str_contains($b4, "UYARI: mode='fixed'") && str_contains($b4, 'HIC basmaz'));
$t('sahte bir rozet iddiasi YOK',                            !str_contains($b4, 'rozet -%'));

echo "\n== 5. list en dusuk kademeden yuksek degilse sayfa sabit fiyat gosterir ==\n";
$seed();
[, $out5] = $run([['match'=>'rl-a','expect'=>1,'sale_list'=>24.00]], false);
$b5 = $block($out5, 'rl-a');
$t('UYARI: indirim yok ("-%0" basmaz)',                      str_contains($b5, 'UYARI: list €24.00 en dusuk kademe €25.00') && str_contains($b5, 'sabit fiyat gosterir'));
$t('sahte bir rozet iddiasi YOK',                            !str_contains($b5, 'rozet -%'));

echo "\n== 6. YALNIZ tiers degisince de SAYFADA satiri kademe degisikligini izliyor ==\n";
$seed();
[, $out6] = $run([['match'=>'rl-a','expect'=>1,'tiers'=>[['min'=>80,'price'=>26.90],['min'=>160,'price'=>20.00]]]], false);
$b6 = $block($out6, 'rl-a');
/* list 26.90 (dokunulmadi), en dusuk kademe 20.00 -> (26.90-20)/26.90 = %25.65 -> 26 */
$t('list DEGISMEDEN rozet yeni merdivenden (-%26)',          str_contains($b6, 'ustu cizili €26.90') && str_contains($b6, 'from €20.00') && str_contains($b6, 'rozet -%26'));

echo "\n== 7. price (tum kademeleri duzlestirir) yolu da kapsaniyor ==\n";
$seed();
[, $out7] = $run([['match'=>'rl-a','expect'=>1,'price'=>22.00]], false);
$b7 = $block($out7, 'rl-a');
$t('price=22: list ve kademeler esit -> indirim yok uyarisi', str_contains($b7, 'UYARI: list €22.00 en dusuk kademe €22.00'));

echo "\n== 8. fiyatla ilgisi olmayan satir: gurultu yok ==\n";
$seed();
[, $out8] = $run([['match'=>'rl-a','expect'=>1,'name'=>'Custom Slim Fit Polo Shirt (710548797)']], false);
$b8 = $block($out8, 'rl-a');
$t('yalniz ad degisiyor: SAYFADA/UYARI yok',                 str_contains($b8, "name '") && !str_contains($b8, 'SAYFADA') && !str_contains($b8, 'UYARI'));

foreach (array_merge([$listings], glob($listings.'.bak-*') ?: []) as $f) @unlink($f);
@unlink($home.'/public_html');
@rmdir($home);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
