<?php
/* Site metni tutarliligi -- 16-17 Eyl 2026 tam-site denetiminin bekcisi.
 *
 * Denetim (28 sayfa x 2 genislik ekran goruntusu + kaynak okuma) su kusurlari
 * buldu; hicbiri PHP hatasi degildi, hepsi musterinin okudugu metindeydi:
 *  1. Sozlesme 3b maddesi "HGB §377" basiyordu -- PHP tek tirnakli dizgede
 *     § bir kacis DEGIL, dort harfli bir metin. Ayrica meta satirinda bir IC
 *     NOT ("Please have a US+EU lawyer review …") 8 dile cevrilip her ziyaretciye
 *     gosteriliyordu.
 *  2. Komisyon BES sayfada UC farkli rakamdi (ana sayfa "from 2.8%", davet "7 %",
 *     yardim "3.5/3.2/2.8", uyelik "3.5% … drops as you upgrade") -- sepetin
 *     gercekten tahsil ettigi tek oran VESTRA_COMMISSION_RATE. Uyelik sayfasi
 *     22 Agu 2026'da kaldirilan ucretli planlari (19,90/39,90/89,90 EUR) hala
 *     SATIYORDU: hicbir sey vermeyen bir abonelik icin Stripe checkout acilabilirdi.
 *  3. Davet sayfasi "DE, EN, FR, IT, ES" diye BES dil sayarken site dokuz dildeydi.
 *  4. Ana sayfa ustte "Coming soon: Fred Perry" derken bir bant asagida "New
 *     arrivals: Fred Perry" satiyordu -- klasor markanin canliya cikisiyla silinmemisti.
 *  5. "Verified seller" rozeti 10px idi; hata gunlugu sondasi 800+ oturum
 *     uyarisi grubunun altinda gercek hatalari goremiyordu.
 * Iddialar OLGUYA bagli (kaynakta hangi fonksiyonun cagrildigi, hangi dizgenin
 * olmadigi, suzgecin iki yonde ne yaptigi) -- yazima degil. */
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__.'/../vestra/inc/products.php';   // vestra_commission_pct_label, vestra_soon_brands_filter
require_once __DIR__.'/../vestra/inc/legal.php';      // vestra_legal_en, vestra_legal_updated

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$root = __DIR__.'/../vestra';
$src  = fn(string $f) => (string)@file_get_contents($root.'/'.$f);

echo "== 1. Sozlesme: ham \\u kacisi yok, ic not yok, tarih ISO ==\n";
$docs = vestra_legal_en();
$all  = implode("\n", array_map(fn($d) => $d['title'].' '.$d['html'], $docs));
$t('EN metinde \\uXXXX kacisi YOK',          !preg_match('~\\\\u[0-9a-fA-F]{4}~', $all));
$t('HGB §377 gercek isaretle basiliyor',    str_contains($all, 'HGB §377'));
foreach (['inc/legal.php','inc/legal/de.php','inc/legal/fr.php','inc/legal/it.php','inc/legal/es.php'] as $f) {
    $t("$f kaynaginda \\uXXXX kacisi yok", !preg_match('~\\\\u[0-9a-fA-F]{4}~', $src($f)));
}
$t('legal.php ic notu ("lawyer review") BASMIYOR', !str_contains($src('legal.php'), 'lawyer review'));
$t('legal.php tarihi fonksiyondan basiyor',       str_contains($src('legal.php'), 'vestra_legal_updated('));
foreach (['en','de','fr','it','es','ru'] as $L) {
    $t("tarih ISO bicimde ($L)", preg_match('~^\d{4}-\d{2}-\d{2}$~', vestra_legal_updated($L)) === 1);
}
$t('bilinmeyen dil Ingilizce tarihini alir', vestra_legal_updated('xx') === vestra_legal_updated('en'));
$t('sozlukte eski ic-not anahtari kalmadi (de)', !str_contains($src('inc/lang/de.php'), 'lawyer review'));

echo "\n== 2. Komisyon: TEK sabit; musteriye giden metinde gomulu rakam yok ==\n";
$en = vestra_commission_pct_label('en');
$expected = number_format(VESTRA_COMMISSION_RATE * 100, 1, '.', '');
if (substr($expected, -2) === '.0') $expected = substr($expected, 0, -2);
$t('etiket sabitten turuyor (en)',         $en === $expected);
$t('etiket Almancada virgullu',            vestra_commission_pct_label('de') === str_replace('.', ',', $en));
$t('etiket Japoncada noktali',             vestra_commission_pct_label('ja') === $en);
$t('etiket Arapcada noktali',              vestra_commission_pct_label('ar') === $en);
/* Ust uste sabit degistirilirse (ornegin 0.04) etiket "4" olmali, "4.0" degil:
   tek ornekle degil, gercek hesaplamayla olculuyor. */
$t('sabit %3,5 ise etiket "3.5"',          VESTRA_COMMISSION_RATE !== 0.035 || $en === '3.5');
$pages = ['index.php','help.php','seller-invite.php','membership.php','seller.php'];
$stale = ['2.8%','2,8 %','2,8%','2.8٪','3.2%','3,2 %','<b>7 %</b>','takes 7 %','Tiered by membership',
          'drops as you upgrade','19,90','39,90','89,90','10 listings / month','100 listings / month',
          'DE, EN, FR, IT, ES','One-time onboarding','Compare plans'];
foreach ($pages as $f) {
    $s = $src($f); $bad = [];
    foreach ($stale as $w) if (str_contains($s, $w)) $bad[] = $w;
    $t("$f: gomulu eski oran/plan metni YOK".($bad ? ' — '.implode(', ', $bad) : ''), !$bad);
}
foreach (['index.php','help.php','seller-invite.php','membership.php'] as $f) {
    $t("$f komisyonu vestra_commission_pct_label() ile basiyor", str_contains($src($f), 'vestra_commission_pct_label('));
}
foreach (glob($root.'/inc/lang/*.php') as $d) {
    $s = (string)file_get_contents($d); $bad = [];
    foreach (['Tiered by membership','takes 7 %','DE, EN, FR, IT, ES','drops as you upgrade','89,90 €','lawyer review'] as $w) {
        if (str_contains($s, $w)) $bad[] = $w;
    }
    $t(basename($d).': eski anahtar kalmadi'.($bad ? ' — '.implode(', ', $bad) : ''), !$bad);
}
/* Iki yon: yeni sayfa satmiyor AMA eski aboneye portal birakiyor (KURAL 16 --
   satan her yerin iptal yolu olmali). */
$t('membership /stripe/checkout ACMIYOR',            !str_contains($src('membership.php'), '/stripe/checkout'));
$t('membership eski aboneye /stripe/portal birakiyor', str_contains($src('membership.php'), '/stripe/portal'));
$t('seller.php uyelik karti yalniz eski abonelikte',   str_contains($src('seller.php'), "if (\$msStat !== 'none'): ?>"));
$t('index.php sell_f2 dokuz dilde %s tasiyor',
   preg_match_all("~'sell_f2'=>'[^']*%s~u", $src('index.php')) === 9
   && str_contains($src('index.php'), "sprintf(\$t['sell_f2'], vestra_commission_pct_label(\$lang))"));

echo "\n== 3. Davet sayfasi: dil SAYISI listeden, elle yazilmiyor ==\n";
$t('dil sayisi count(vlang_list()) ile',      str_contains($src('seller-invite.php'), 'count(vlang_list())'));
$t('dil cumlesi %d tasiyor',                  str_contains($src('seller-invite.php'), 'appear in %d languages'));
$t('sozlukte %d anahtari var (de)',           str_contains($src('inc/lang/de.php'), "'Your listings appear in %d languages"));

echo "\n== 4. \"Yakinda\" seridi: satistaki marka duser, digerleri kalir ==\n";
$soon = [['name' => 'Fred Perry'], ['name' => 'Gallery Dept.'], ['name' => 'Lacoste Kids'], ['name' => 'AMI Paris']];
$live = [['brand' => 'FRED PERRY'], ['brand' => 'Lacoste'], ['brand' => ''], ['name' => 'no brand key']];
$r = array_column(vestra_soon_brands_filter($soon, $live), 'name');
$t('satistaki marka DUSTU (buyuk/kucuk harf duyarsiz)', !in_array('Fred Perry', $r, true));
$t('satista olmayanlar KALDI',                         in_array('Gallery Dept.', $r, true) && in_array('AMI Paris', $r, true));
$t('alt dize DEGIL: "Lacoste" satista, "Lacoste Kids" kaldi', in_array('Lacoste Kids', $r, true));
$t('sira korundu',                                     $r === ['Gallery Dept.', 'Lacoste Kids', 'AMI Paris']);
$t('bos katalog hicbir seyi dusurmez',                 count(vestra_soon_brands_filter($soon, [])) === 4);
$t('bos marka adi hicbir seyi dusurmez',               count(vestra_soon_brands_filter($soon, [['brand' => '']])) === 4);
$t('index.php suzgeci canli katalogla cagiriyor',
   str_contains($src('index.php'), 'vestra_soon_brands_filter($soonBrands, vestra_products())'));

echo "\n== 5. Rozet boyutu ve sondanin gurultusu ==\n";
preg_match('~\.svbadge\{[^}]*font-size:(\d+(?:\.\d+)?)px~', $src('inc/style.css'), $m);
$t('"Verified seller" rozeti >= 11px', isset($m[1]) && (float)$m[1] >= 11.0);
$yml = (string)@file_get_contents(__DIR__.'/../.github/workflows/diag-messages.yml');
$t('errlog sondasi oturum uyarilarini tek satira topluyor',
   substr_count($yml, "str_contains(\$k, 'session_start()')") >= 3 && str_contains($yml, 'oturum uyarilari:'));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
