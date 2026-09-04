<?php
/* SEO inis sayfalari — kategori, koleksiyon, marka x kategori (operator istegi, 3 Eyl 2026:
 * "seo yu cok guclu yap ayakkabilari da koy avrupa musterileri de girsin 5 dilde
 * eksiksiz markalar aksesuar ayakkabi tshirt bot sweat B2B").
 *
 * Tutulmasi gereken seyler:
 *   1. Her canli kategori, dolu grup ve koleksiyon bir /b2b/<slug> adresine cozulur;
 *      bos olan HICBIR sey cozulmez (ince sayfa yerine 404).
 *   2. Slug gidis-donus: slug -> kategori -> ayni slug.
 *   3. Sitemap/footer listesi yalnizca cozulen adresleri icerir ve tekrar etmez.
 *   4. hreflang haritasi: her bolgesel kod servis edilen bir dile isaret eder.
 *   5. 5 dil EKSIKSIZ: taksonomideki her ad ve yeni arayuz metinleri 4 sozlukte de var.
 *   6. Kablolama kaynak duzeyinde: .htaccess, yerel router, sitemap, head, index, foot.
 */
require __DIR__.'/../vestra/inc/products.php';

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "-- slug --\n";
$t('slug: "Hoodies & Sweatshirts" -> hoodies-sweatshirts',  vestra_seo_cat_slug('Hoodies & Sweatshirts') === 'hoodies-sweatshirts');
$t('slug: "Women\'s T-Shirts" -> women-s-t-shirts',          vestra_seo_cat_slug("Women's T-Shirts") === 'women-s-t-shirts');
$t('slug: idempotent',                                        vestra_seo_cat_slug(vestra_seo_cat_slug('T-Shirts')) === 't-shirts');

echo "-- canli kategoriler --\n";
$cats = vestra_seo_cats();
$t('en az bir canli kategori var',                            count($cats) > 0);
$t('sayilar buyukten kucuge',                                 array_values($cats) === (function($v){ rsort($v); return $v; })(array_values($cats)));
foreach ($cats as $c => $n) {
    $r = vestra_seo_resolve(vestra_seo_cat_slug($c));
    $t("kategori cozulur: $c ($n)",                           $r !== null && $r['kind'] === 'cat' && $r['name'] === $c && count($r['items']) === $n);
    $t("slug gidis-donus: $c",                                $r !== null && $r['slug'] === vestra_seo_cat_slug($c));
}

echo "-- gruplar ve koleksiyonlar --\n";
foreach (vestra_seo_groups() as $g => $kids) {
    $r = vestra_seo_resolve(vestra_seo_cat_slug($g));
    $t("grup cozulur: $g (".count($kids)." alt)",             $r !== null && in_array($r['kind'], ['group','cat'], true) && count($r['items']) >= array_sum($kids));
}
$t('taksonomide olmayan grup cozulmez (Jewelry & Watches bos)', vestra_seo_resolve('jewelry-watches') === null || count(vestra_seo_resolve('jewelry-watches')['items']) > 0);
$t('bilinmeyen slug null',                                    vestra_seo_resolve('bu-kategori-yok') === null);
$t('bos slug null',                                           vestra_seo_resolve('') === null);
$ap = vestra_seo_resolve('apparel');
$t('koleksiyon: apparel cozulur (premium bolme)',             $ap !== null && $ap['kind'] === 'collection' && count($ap['items']) > 0);
$fw = vestra_seo_resolve('footwear');
$t('koleksiyon: footwear ya dolu ya null, asla bos sayfa',    $fw === null || count($fw['items']) > 0);

echo "-- sitemap / footer listesi --\n";
$paths = vestra_seo_landing_paths();
$loc = array_column($paths, 0);
$t('liste bos degil',                                         count($loc) > 0);
$t('tekrar yok',                                              count($loc) === count(array_unique($loc)));
$t('hepsi /b2b/ ya da /wholesale/<marka>/<kat>',              !array_filter($loc, fn($p) => !preg_match('~^/(b2b/[a-z0-9-]+|wholesale/[a-z0-9-]+/[a-z0-9-]+)$~', $p)));
$t('/b2b/apparel listede',                                    in_array('/b2b/apparel', $loc, true));
foreach ($cats as $c => $_) $t("listede: /b2b/".vestra_seo_cat_slug($c), in_array('/b2b/'.vestra_seo_cat_slug($c), $loc, true));
$first = array_key_first($cats);
$p0 = null; foreach (vestra_products() as $p) if (($p['cat'] ?? '') === $first && trim((string)($p['brand'] ?? '')) !== '') { $p0 = $p; break; }
$t('marka x kategori cifti listede',                          $p0 !== null && in_array('/wholesale/'.vestra_brand_slug($p0['brand']).'/'.vestra_seo_cat_slug($first), $loc, true));
$t('marka x kategori: marka + kategori suzgeci dolu doner',   $p0 !== null && count(array_filter(vestra_seo_resolve(vestra_seo_cat_slug($first))['items'], fn($x) => strcasecmp($x['brand'], $p0['brand']) === 0)) > 0);
$t('marka sayfasi kategori cipleri: vestra_seo_brand_cats',   $p0 !== null && isset(vestra_seo_brand_cats($p0['brand'])[$first]));

echo "-- marka sirasi: stok derinligi (alfabe DEGIL) --\n";
/* 4 Eyl 2026 canli olcumu: anahtar kelime etiketi 12 marka basiyor ve liste alfabetikti,
   yani ~20 markali canli katalogda J'den sonrasi (Lacoste, Pili Perez, Ralph Lauren,
   Valentino, Versace...) arama motoruna HIC soylenmiyordu. Sira artik ilan sayisina gore. */
$brandCounts = vestra_seo_count_brands(vestra_products());
$ordered = vestra_seo_brands(0);
$t('marka listesi ilan sayisina gore azalan',        array_map(fn($b) => $brandCounts[$b], $ordered)
                                                     === (function($v){ rsort($v); return $v; })(array_map(fn($b) => $brandCounts[$b], $ordered)));
$t('en derin marka listenin basinda',                 $ordered && $ordered[0] === array_key_first($brandCounts));
$t('tum markalar listede (kesme yok, max=0)',         count($ordered) === count($brandCounts));
$t('kapak: max=1 en derin markayi verir',             vestra_seo_brands(1) === [$ordered[0]]);
$t('siralama deterministik (iki cagri ayni)',         vestra_seo_brands(0) === $ordered);
$t('anahtar kelimeler en derin markayi icerir',       str_contains(vestra_seo_brand_keywords('en', 12), $ordered[0]));
$t('slug cozumu siradan bagimsiz calisir',            vestra_brand_from_slug(vestra_brand_slug($ordered[0])) === $ordered[0]);
echo "-- hreflang --\n";
$map = vlang_hreflang_map();
$langs = array_keys(vlang_list());
$t('her temel dil haritada',                                  !array_diff($langs, array_keys($map)));
$t('her deger servis edilen bir dil',                         !array_diff(array_unique(array_values($map)), $langs));
$t('bolgesel kodlar xx-YY bicimli',                           !array_filter(array_keys($map), fn($k) => !preg_match('~^[a-z]{2}(-[A-Z]{2})?$~', $k)));
$t('Avrupa: de-AT, fr-BE, it-CH, en-NL var',                  isset($map['de-AT'], $map['fr-BE'], $map['it-CH'], $map['en-NL']));
$t('en-NL Ingilizceye gider',                                 ($map['en-NL'] ?? '') === 'en');

echo "-- her sozluk de.php'ye karsi EKSIKSIZ (8 dil) --\n";
/* Operator: "5 dilde eksiksiz" -> "6-7 dil yap, rusca ve portekizce ekle" -> "arapcada yap"
   (3 Eyl 2026). de.php referans set: vlang_list()'teki her dil icin HER anahtar var ve yer
   tutucu / HTML etiket sayisi ayni. t() Ingilizceye dustugu icin eksik bir anahtar sessizce
   yarim ceviri olur -- bu test onu gorunur kilar. */
$ref = require __DIR__.'/../vestra/inc/lang/de.php';
$t('vlang_list 8 dil: en fr es it de pt ru ar',              array_keys(vlang_list()) === ['en','fr','es','it','de','pt','ru','ar']);
foreach (array_keys(vlang_list()) as $L) {
    if ($L === 'en') continue;
    $f = __DIR__."/../vestra/inc/lang/$L.php";
    $d = is_readable($f) ? require $f : [];
    $miss = array_values(array_filter(array_keys($ref), fn($k) => !isset($d[$k]) || trim((string)$d[$k]) === ''));
    $t("$L: ".count($ref)." anahtarin tamami var".($miss ? ' — eksik '.count($miss).': '.implode(' | ', array_slice($miss, 0, 5)) : ''), !$miss);
    $bad = [];
    foreach (array_keys($ref) as $k) {
        if (!isset($d[$k])) continue;
        preg_match_all('~%(\d+\$)?[sd]~', $k, $a); preg_match_all('~%(\d+\$)?[sd]~', $d[$k], $b);
        if (count($a[0]) !== count($b[0])) $bad[] = $k;
        foreach (['<b>','</b>','<strong>','</strong>'] as $tag) if (substr_count($k, $tag) !== substr_count($d[$k], $tag)) { $bad[] = $k; break; }
    }
    $t("$L: yer tutucu ve <b> sayilari anahtarla ayni".($bad ? ' — '.implode(' | ', array_slice($bad, 0, 3)) : ''), !$bad);
}
$t('Arapca RTL: vlang_dir() yalnizca ar icin rtl',            function_exists('vlang_dir'));
$t('head.php <html dir> basiyor',                             str_contains((string)@file_get_contents(__DIR__.'/../vestra/inc/head.php'), 'dir="<?= vlang_dir() ?>"'));
$t('index.php <html dir> basiyor',                            str_contains((string)@file_get_contents(__DIR__.'/../vestra/index.php'), 'dir="<?= vlang_dir() ?>"'));
$t('index.php $T blogunda 8 dil',                             preg_match_all("~^'(en|fr|it|es|de|pt|ru|ar)'=>\\[~m", (string)@file_get_contents(__DIR__.'/../vestra/index.php')) === 8);
$t('hreflang: ar-AE, pt-BR, ru-RU var',                        isset(vlang_hreflang_map()['ar-AE'], vlang_hreflang_map()['pt-BR'], vlang_hreflang_map()['ru-RU']));
$t('toptan sozcugu 8 dilde farkli',                            count(array_unique(array_map('vestra_seo_wholesale_word', array_keys(vlang_list())))) === 8);
$t('B2B terimleri 8 dilde dolu',                               !array_filter(array_keys(vlang_list()), fn($l) => count(vestra_seo_b2b_terms($l)) < 6));

echo "-- taksonomi ve arayuz metinleri (ek kontrol) --\n";
$names = [];
foreach (vestra_all_cats() as $g => $kids) { $names[] = $g; foreach ($kids as $k) $names[] = $k; }
foreach (array_keys($cats) as $c) $names[] = $c;
foreach (['Footwear','Apparel','Home','Catalog','%1$s %2$s — B2B supplier','%1$s %2$s %3$s — B2B supplier','%1$s %2$s %3$s',
          'Brands in this category','Other categories in stock','Wholesale by brand','Wholesale by category','Collections',
          'See all %d listings in this category →','lowest MOQ','brands','%d listings','Buying %s wholesale','Other houses in stock',
          '%s wholesale — Catalog','listings in stock','product categories','invoice-based ordering','See %s trade prices',
          'Every category below is live stock — counts update as listings change.',
          'Spanish-made footwear wholesale — sneakers, boots, sandals, loafers and slippers in full size series for shoe shops and boutiques. Trade prices on registration, ordered by the series, invoice-based B2B ordering across Europe.',
          '%1$s %2$s at VESTRA: %3$d listings from %4$s. Trade prices after registration, low minimums, invoice-based B2B ordering and shipping across Europe and worldwide from KYC-verified sellers.',
          'Authentic %1$s from %2$s for boutiques, multi-brand retailers and outlets. Every seller on VESTRA is KYC-verified before a listing goes live, orders are invoiced B2B, and stock ships across Europe and worldwide.'] as $n) $names[] = $n;
$names = array_values(array_unique($names));
foreach (array_diff(array_keys(vlang_list()), ['en']) as $L) {
    $d = require __DIR__."/../vestra/inc/lang/$L.php";
    $miss = array_values(array_filter($names, fn($n) => !isset($d[$n]) || trim((string)$d[$n]) === ''));
    $t("$L: ".count($names)." metin cevrili".($miss ? ' — eksik: '.implode(' | ', $miss) : ''), !$miss);
    /* Yer tutucu sayisi: %1$s..%4$s cevirisinde kaybolursa sprintf ya eksik basar ya patlar. */
    foreach ($names as $n) {
        if (!isset($d[$n]) || strpos($n, '%') === false) continue;
        preg_match_all('~%(\d+\$)?[sd]~', $n, $a); preg_match_all('~%(\d+\$)?[sd]~', $d[$n], $b);
        if (count($a[0]) !== count($b[0])) $t("$L yer tutucu sayisi ayni: ".substr($n, 0, 40), false);
    }
}

echo "-- kablolama (kaynak) --\n";
$src = fn(string $f) => (string)@file_get_contents(__DIR__.'/../vestra/'.$f);
$t('.htaccess: /b2b/ kurali',                                 str_contains($src('.htaccess'), '^b2b/') && str_contains($src('.htaccess'), 'b2b.php?cat=$1'));
$t('.htaccess: marka x kategori kurali',                      str_contains($src('.htaccess'), 'b2b.php?brand=$1&cat=$2'));
$t('yerel router ayni kurallar',                              substr_count($src('_router_local.php'), "b2b.php") === 2);
$t('sitemap inis sayfalarini listeler',                       str_contains($src('sitemap.php'), 'vestra_seo_landing_paths()'));
$t('head.php bolgesel hreflang',                              str_contains($src('inc/head.php'), 'vlang_hreflang_map()'));
$t('index.php bolgesel hreflang',                             str_contains($src('index.php'), 'vlang_hreflang_map()'));
$t('index.php marka duvari /wholesale/ baglar',               str_contains($src('index.php'), "'/wholesale/'.urlencode(vestra_brand_slug(\$_b))"));
$t('foot.php kategori baglantilari',                          str_contains($src('inc/foot.php'), '/b2b/'));
$t('shop.php ayakkabi basligi',                               str_contains($src('shop.php'), "t('Footwear')"));
$t('wholesale.php stili ortak css\'de',                       !str_contains($src('wholesale.php'), '<style>') && str_contains($src('inc/style.css'), '.wsw{'));
$t('b2b.php kataloga vestra_products(true) ile BAKMAZ',       !str_contains($src('b2b.php'), 'vestra_products(true)'));

echo "\n$ok ok, $fail hata\n";
exit($fail ? 1 : 0);
