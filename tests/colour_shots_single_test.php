<?php
/* vestra_listing_colour_shots: TEK renkli ilan (1 Eki 2026).
 *
 * Burberry/Fred Perry/DSQUARED2 kampanyasinin kuru kosusu "FOTOGRAFI OLMAYAN
 * RENK -> dsq-101213: Black" ile durdu: ilan tek renk, fotografi tek ve adi
 * yalniz stil kodu ("dsq-101213.png"). Tek renkte hangi foto hangi renk sorusu
 * yok; kapak o rengin fotografi.
 *
 * IKI YONU DE tutuyor: tek renk bagli kalir; iki renkli ilanda adsiz foto HALA
 * eksik sayilir ("her renge foto" iddiasi orada gevsemez).
 */
require_once __DIR__.'/../vestra/inc/products.php';
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { $c ? ($ok++ . print("  ok   $n\n")) : ($fail++ . print("  HATA $n\n")); };

echo "\n== 1. Tek renk, adsiz foto: bagli ==\n";
$r = vestra_listing_colour_shots(['colors' => ['Black'], 'images' => ['/uploads/dsq/dsq-101213.png']]);
$t('eksik yok',                       $r['missing'] === []);
$t('Black kapaga bagli',              count($r['pairs']) === 1 && $r['pairs'][0]['colour'] === 'Black' && $r['pairs'][0]['img'] === '/uploads/dsq/dsq-101213.png');
$t('bagsiz foto yok',                 $r['unbound'] === []);

echo "\n== 2. Tek renk, birden fazla foto: kapak bagli, gerisi bagsiz ==\n";
$r = vestra_listing_colour_shots(['colors' => ['Black'], 'images' => ['/u/a.jpg', '/u/b.jpg', '/u/c.jpg']]);
$t('kapak (ilk foto) bagli',          $r['pairs'][0]['img'] === '/u/a.jpg');
$t('diger ikisi bagsiz',              $r['unbound'] === ['/u/b.jpg', '/u/c.jpg']);
$t('eksik yok',                       $r['missing'] === []);

echo "\n== 3. Tek renk, adi tasiyan foto: AD ESLESMESI kazanir ==\n";
$r = vestra_listing_colour_shots(['colors' => ['Black'], 'images' => ['/u/front.jpg', '/u/ilan-black.jpg']]);
$t('adi tasiyan foto secilir (kapak degil)', $r['pairs'][0]['img'] === '/u/ilan-black.jpg');
$t('kapak bagsiz kalir',              $r['unbound'] === ['/u/front.jpg']);

echo "\n== 4. IKI renk: gevseme YOK ==\n";
$r = vestra_listing_colour_shots(['colors' => ['Black', 'White'], 'images' => ['/u/dsq-1.png']]);
$t('iki renk + tek adsiz foto: ikisi de EKSIK', $r['missing'] === ['White', 'Black'] || $r['missing'] === ['Black', 'White']);
$t('hicbiri baglanmadi',              $r['pairs'] === []);
$r = vestra_listing_colour_shots(['colors' => ['Black', 'White'], 'images' => ['/u/x-black.jpg', '/u/y.jpg']]);
$t('biri adla bagli, digeri EKSIK',   $r['missing'] === ['White'] && count($r['pairs']) === 1 && $r['pairs'][0]['colour'] === 'Black');

echo "\n== 5. Kenar durumlar ==\n";
$r = vestra_listing_colour_shots(['colors' => ['Black'], 'images' => []]);
$t('tek renk, foto YOK: eksik',       $r['missing'] === ['Black'] && $r['pairs'] === []);
$r = vestra_listing_colour_shots(['colors' => [], 'images' => ['/u/a.jpg']]);
$t('renk yok: eksik de baglanma da yok', $r['missing'] === [] && $r['pairs'] === [] && $r['unbound'] === ['/u/a.jpg']);
$r = vestra_listing_colour_shots(['colors' => ['Black', ''], 'images' => ['/u/a.jpg']]);
$t('bos renk adi sayilmaz (tek renk)', $r['missing'] === [] && count($r['pairs']) === 1);

echo "\n== 6. Kablolama ==\n";
$src = file_get_contents(__DIR__.'/../vestra/inc/products.php');
$t('kural kaynakta ve yalniz renk sayisi 1 iken', (bool)preg_match('/count\(\$cols\) === 1 && \$missing && \$imgs/', $src));

echo "\n$ok ok, $fail hata\n";
exit($fail ? 1 : 0);
