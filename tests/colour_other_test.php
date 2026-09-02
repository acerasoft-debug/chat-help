<?php
/* Renk paletindeki "Other" (operator istegi, 2 Eyl 2026: "urun koyarken
 * renklere other ekle"). Iki sey tutulur:
 *   1. "Other" palette var ve LISTENIN SONUNDA (formda son cip),
 *   2. renk TAHMINCISI (vestra_colour_options) bu adi asla urun adindan
 *      turetmez -- "& Other Stories" bir marka, "other colours on request"
 *      bir cumle; ikisi de "Other renkli urun" degil.
 * Ayrica degerin CSS arka plani olarak kullanilabilir oldugu (gradyan) ve
 * secilen "Other"in kaydedilen renk listesinde korundugu sinanir.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/products.php');
foreach (['vestra_colors', 'vestra_colour_options'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$pal = vestra_colors();
$keys = array_keys($pal);
$t('"Other" palette var',                          isset($pal['Other']));
$t('"Other" listenin SONUNDA',                     end($keys) === 'Other');
$t('degeri CSS arka plani (gradyan)',              str_starts_with((string)$pal['Other'], 'linear-gradient('));
$t('degerde HTML ozel karakteri yok (htmlspecialchars altinda da bozulmaz)', htmlspecialchars((string)$pal['Other']) === (string)$pal['Other']);
$t('diger renkler hex kaldi',                      count(array_filter($pal, fn($v, $k) => $k !== 'Other' && !preg_match('/^#[0-9a-f]{6}$/i', (string)$v), ARRAY_FILTER_USE_BOTH)) === 0);

echo "-- tahminci --\n";
$t('acik secilmis "Other" korunur',                vestra_colour_options(['colors'=>['Navy','Other']]) === ['Navy','Other']);
$t('"& Other Stories Tee" -> Other TURETILMEZ',    !in_array('Other', vestra_colour_options(['name'=>'& Other Stories Tee','brand'=>'& Other Stories']), true));
$t('"other colours on request" -> Other TURETILMEZ', !in_array('Other', vestra_colour_options(['name'=>'Polo','desc'=>'other colours on request']), true));
$t('"Navy Polo" -> Navy hala turetilir',          in_array('Navy', vestra_colour_options(['name'=>'Navy Polo']), true));
$t('"Light Blue Shirt" -> Light Blue (Blue degil)', vestra_colour_options(['name'=>'Light Blue Shirt']) === ['Light Blue']);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
