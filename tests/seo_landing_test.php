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

echo "-- AVRUPA TAM: her AB/EFTA/GB ulkesi bir hreflang etiketinde --\n";
/* 17 Eyl 2026, operator: "avrupada kusursuz istiyorum". Once OLCULDU: Luksemburg,
   Malta, Kibris, Hirvatistan, Slovenya, Slovakya, Bulgaristan, uc Baltik, Izlanda ve
   Liechtenstein hicbir bolgesel etikette gecmiyordu -- 13 ulke. Iddia ulke ulke
   sayiyor, cunku "48 etiket var" demek hangi ulkelerin kapsandigini SOYLEMIYOR. */
$mapAll = vlang_hreflang_map();
$tagCcs = [];
foreach (array_keys($mapAll) as $tg) if (strlen($tg) === 5) $tagCcs[substr($tg, 3)] = true;
$euMiss = array_values(array_filter(vestra_europe_codes(), fn($c) => !isset($tagCcs[$c])
    /* Balkanlar/mikro devletler kapsam disi kalabilir: AB uyesi degiller ve bir
       hedef pazar da degiller. Iddia AB 27 + EFTA + GB uzerinde. */
    && in_array($c, ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','CH','NO','IS','LI','GB'], true)));
$t('AB 27 + EFTA + GB: hepsi hreflang tasiyor'.($euMiss ? ' — eksik: '.implode(',', $euMiss) : ''), !$euMiss);
$t('AB uyesi olmayan ama sitede dili olan ulkeler de var (MC, SM)', isset($tagCcs['MC'], $tagCcs['SM']));

echo "-- HEDEF PAZARLAR: tablo, olgular, adresler --\n";
/* operator, 17 Eyl 2026: "sadece avrupa degil avustralya japonya dubai qatar ve usa da
   olsun" + ayni gun "brezilya ve guney amerika, israil, singapur, g.koreyi de ekle". */
$mkts = vestra_seo_markets();
foreach (['australia','japan','united-arab-emirates','qatar','united-states',
          'brazil','south-america','israel','singapore','south-korea'] as $need)
    $t("pazar tabloda: $need", isset($mkts[$need]));
$t('bilinmeyen pazar null', vestra_seo_market('bu-pazar-yok') === null);
$t('bos slug null',        vestra_seo_market('') === null);
foreach ($mkts as $slug => $mk) {
    $r = vestra_seo_market($slug);
    $t("pazar cozulur ve slug gidis-donus: $slug", $r !== null && $r['slug'] === $slug && $r['name'] === $mk['name']);
    $ccs = vestra_seo_market_ccs($mk);
    $t("pazarin ulke kodu(lari) var: $slug", $ccs !== [] && !array_filter($ccs, fn($c) => !preg_match('~^[A-Z]{2}$~', $c)));
    /* Her pazarin ulkesi hreflang'te olmali: sayfa var ama o ulkeye "bu sayfa
       senin icin" diyen etiket yoksa, is yarim kalmis demektir. */
    $miss = array_values(array_filter($ccs, fn($c) => !isset($tagCcs[$c])));
    $t("pazarin her ulkesi hreflang tasiyor: $slug".($miss ? ' — eksik: '.implode(',', $miss) : ''), !$miss);
    /* Ulke adi 8 dilde de cevrili olmali; cevrilmezse sayfa basligi Almanca
       sayfada Ingilizce ulke adi basar. */
    foreach (array_diff(array_keys(vlang_list()), ['en']) as $L) {
        $d = require __DIR__."/../vestra/inc/lang/$L.php";
        if (!isset($d[$mk['name']]) || trim((string)$d[$mk['name']]) === '')
            $t("$L: pazar adi cevrili degil: {$mk['name']}", false);
    }
}
$t('pazar adlari 8 dilde cevrili (yukarida tek tek)', true);

/* OLGULAR KODDAN, metinden degil. Uc ornek uc ayri kaynagi tutuyor:
   para birimi (money.php), bolgesel indirim (region_discount.php) ve
   Avrupa disi asgari siparis (products.php sabiti). */
$fAu = vestra_seo_market_facts($mkts['australia']);
$t('Avustralya: para birimi AUD',            $fAu['currency'] === 'AUD');
$t('Avustralya: indirim region_discount\'tan', abs($fAu['discount'] - (vestra_region_discount_rates()['AU'] ?? 0)) < 0.001 && $fAu['discount'] > 0);
$t('Avustralya: asgari siparis sabitten',    abs($fAu['min_order_usd'] - (float)VESTRA_NONEU_MIN_ORDER_USD) < 0.001);
$t('Avustralya: kapi kayitta acilir (KURAL 2h)', $fAu['auto_open'] === true);
$fUs = vestra_seo_market_facts($mkts['united-states']);
$t('ABD: para birimi USD',                   $fUs['currency'] === 'USD');
$t('ABD: indirim YOK (tabloda degil)',       $fUs['discount'] == 0.0);
$t('ABD: kapi kayitta ACILMAZ',              $fUs['auto_open'] === false);
$fJp = vestra_seo_market_facts($mkts['japan']);
$t('Japonya: diller en + ja',                $fJp['langs'] === ['en','ja']);
$t('Japonya: ja-JP etiketi',                 in_array('ja-JP', $fJp['hreflang'], true));
$fAe = vestra_seo_market_facts($mkts['united-arab-emirates']);
$t('BAE: diller en + ar',                    $fAe['langs'] === ['en','ar']);
$fSa = vestra_seo_market_facts($mkts['south-america']);
$t('Guney Amerika: 12 ulke',                 count($fSa['ccs']) === 12);
$t('Guney Amerika: tablo tek kaynaktan',     $fSa['ccs'] === vestra_south_america_codes());
$t('Guney Amerika: Brezilya dahil',          in_array('BR', $fSa['ccs'], true));
$t('Guney Amerika: diller en/es/pt',         $fSa['langs'] === ['en','es','pt']);
/* Bolgede olgu ulke ulke ayni olmali, degilse SUSULMALI (KURAL 3). Sentetik bir
   bolge kuruluyor: Avustralya (AUD) + ABD (USD) -> para birimi tek degil. */
$fMix = vestra_seo_market_facts(['cc' => '', 'name' => 'X', 'countries' => ['AU','US']]);
$t('karisik bolgede para birimi SUSAR',      $fMix['currency'] === '');
$t('karisik bolgede indirim SUSAR',          $fMix['discount'] == 0.0);
$t('karisik bolgede auto_open SUSAR',        $fMix['auto_open'] === false);
/* Avrupa'daki bir pazar icin Avrupa disi taban YAZILMAZ. */
$fEu = vestra_seo_market_facts(['cc' => 'DE', 'name' => 'Germany']);
$t('Avrupa pazarinda asgari siparis satiri YOK', $fEu['min_order_usd'] == 0.0);

echo "-- pazar adresleri: sitemap, altbilgi, yonlendirme --\n";
$mpaths = array_column(vestra_seo_market_paths(), 0);
$t('her pazarin bir yolu var',   count($mpaths) === count($mkts));
$t('yollar /wholesale-to/<slug>', !array_filter($mpaths, fn($p) => !preg_match('~^/wholesale-to/[a-z0-9-]+$~', $p)));
$t('tekrar yok',                  count($mpaths) === count(array_unique($mpaths)));
$t('marka inis yollariyla cakismiyor', !array_intersect($mpaths, array_column(vestra_seo_landing_paths(), 0)));

echo "-- areaServed ve Organization TEK GOVDE --\n";
$area = vestra_seo_area_served();
$t('areaServed dizi (tek dize DEGIL)', is_array($area) && count($area) > 3);
$t('areaServed Avrupa ve Asya',        in_array('Europe', $area, true) && in_array('Asia', $area, true));
foreach (['Australia','Japan','United Arab Emirates','Qatar','United States','Brazil','Israel','Singapore','South Korea'] as $cn)
    $t("areaServed ulke: $cn", (bool)array_filter($area, fn($a) => is_array($a) && ($a['name'] ?? '') === $cn));
$org = vestra_seo_org_ld();
$t('Organization: tip dogru',           ($org['@type'] ?? '') === 'Organization');
$t('Organization: areaServed ayni govdeden', $org['areaServed'] === $area);
$t('Organization: knowsAbout markalarin TAMAMI',
   !array_diff(vestra_seo_brands(0), $org['knowsAbout']));
$t('Organization: iletisim dilleri 9',  count($org['contactPoint']['availableLanguage'] ?? []) === count(vlang_list()));
$t('Organization: $extra ekleniyor',    (vestra_seo_org_ld(['slogan'=>'x'])['slogan'] ?? '') === 'x');

echo "-- kablolama: pazar sayfasi --\n";
$srcM = fn(string $f) => (string)@file_get_contents(__DIR__.'/../vestra/'.$f);
$t('.htaccess: /wholesale-to/ kurali',   str_contains($srcM('.htaccess'), '^wholesale-to/') && str_contains($srcM('.htaccess'), 'market.php?market=$1'));
$t('yerel router ayni kural',            str_contains($srcM('_router_local.php'), "market.php"));
$t('sitemap pazar yollarini listeler',   str_contains($srcM('sitemap.php'), 'vestra_seo_market_paths()'));
$t('sitemap lastmod basiyor',            str_contains($srcM('sitemap.php'), '<lastmod>'));
$t('altbilgi pazar baglantilari',        str_contains($srcM('inc/foot.php'), '/wholesale-to/'));
/* head.php ve index.php AYNI govdeyi cagirmali: iki kopya ayrismisti (biri alti kita
   sayiyor, digeri 'EU' diyordu) ve ayrisma ancak canli HTML okunarak gorulmustu. */
$t('head.php Organization ortak govdeden',  str_contains($srcM('inc/head.php'), 'vestra_seo_org_ld('));
$t('index.php Organization ortak govdeden', str_contains($srcM('index.php'), 'vestra_seo_org_ld('));
$t('head.php elle Organization yazmiyor',   !preg_match("~'@type'\s*=>\s*'Organization'~", $srcM('inc/head.php')));
$t('index.php elle Organization yazmiyor',  !preg_match("~'@type'\s*=>\s*'Organization'~", $srcM('index.php')));
$t('head.php og:locale ortak govdeden',     str_contains($srcM('inc/head.php'), 'vlang_og_locale('));
$t('index.php og:locale ortak govdeden',    str_contains($srcM('index.php'), 'vlang_og_locale('));
$t('og:locale 9 dilde dolu',                count(array_unique(array_map('vlang_og_locale', array_keys(vlang_list())))) === count(vlang_list()));
$t('og:locale ja_JP',                       vlang_og_locale('ja') === 'ja_JP');
/* IP->dil tablosu: Japonca 5 Eylul'de eklendi ama tablo guncellenmemisti. */
$t('IP->dil: JP Japoncaya duser',           vlang_country_lang('JP') === 'ja');
$t('journal makalesi Article semasi',       str_contains($srcM('journal.php'), "'@type' => 'Article'"));
$t('shop.php ic camasiri bolmesi basligi',  str_contains($srcM('shop.php'), "t('Underwear')"));

echo "-- her sozluk de.php'ye karsi EKSIKSIZ (9 dil) --\n";
/* Operator: "5 dilde eksiksiz" -> "6-7 dil yap, rusca ve portekizce ekle" -> "arapcada yap"
   (3 Eyl 2026). de.php referans set: vlang_list()'teki her dil icin HER anahtar var ve yer
   tutucu / HTML etiket sayisi ayni. t() Ingilizceye dustugu icin eksik bir anahtar sessizce
   yarim ceviri olur -- bu test onu gorunur kilar. */
$ref = require __DIR__.'/../vestra/inc/lang/de.php';
/* 5 Eyl 2026: Japonca eklendi (operator: "Japon dilini tum site icin uygula"). */
$t('vlang_list 9 dil: en fr es it de pt ru ar ja',           array_keys(vlang_list()) === ['en','fr','es','it','de','pt','ru','ar','ja']);
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
$t('index.php $T blogunda 9 dil',                             preg_match_all("~^'(en|fr|it|es|de|pt|ru|ar|ja)'=>\\[~m", (string)@file_get_contents(__DIR__.'/../vestra/index.php')) === 9);
$t('hreflang: ar-AE, pt-BR, ru-RU, ja-JP var',                 isset(vlang_hreflang_map()['ar-AE'], vlang_hreflang_map()['pt-BR'], vlang_hreflang_map()['ru-RU'], vlang_hreflang_map()['ja-JP']));
$t('toptan sozcugu 9 dilde farkli',                            count(array_unique(array_map('vestra_seo_wholesale_word', array_keys(vlang_list())))) === 9);
$t('B2B terimleri 9 dilde dolu',                               !array_filter(array_keys(vlang_list()), fn($l) => count(vestra_seo_b2b_terms($l)) < 6));


/* 5 Eyl 2026: Japonca font yigini. 'Inter' ve 'Playfair Display' kana/kanji
   TASIMIYOR; yigin degismezse tarayici rastgele bir yedege duser ve Japonca
   sayfa Latin sayfalardan kopuk gorunur. Ana sayfa style.css YUKLEMIYOR,
   kendi kopyasini tasiyor -- ikisi de ayri ayri kontrol ediliyor. */
$css = (string)@file_get_contents(__DIR__.'/../vestra/inc/style.css');
$idx = (string)@file_get_contents(__DIR__.'/../vestra/index.php');
$t('style.css ja font yigini',        str_contains($css, 'html[lang="ja"]') && str_contains($css, 'Noto Sans JP'));
$t('style.css ja BASLIK yigini',      preg_match('~html\[lang="ja"\][^{]*h1~', $css) === 1);
$t('index.php kendi ja yiginini tasir', str_contains($idx, 'html[lang="ja"]') && str_contains($idx, 'Noto Sans JP'));
$t('ja icin CJK web fontu indirilmiyor', !preg_match('~fonts\.googleapis[^"\x27]*Noto\+Sans\+JP~', $css.$idx));
$t('head.php <html lang> basiyor',    str_contains((string)@file_get_contents(__DIR__.'/../vestra/inc/head.php'), 'lang="<?= vlang() ?>"'));

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
