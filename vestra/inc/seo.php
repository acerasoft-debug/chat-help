<?php
/**
 * VESTRA — SEO landing-page taxonomy: categories, collections and brand × category.
 *
 * Why this file exists. Until now the only search-landing pages on the domain were the
 * per-brand ones (/wholesale/<brand>). A trade buyer just as often searches by what
 * they sell -- "sneakers wholesale", "T-Shirts Großhandel", "polos Lacoste en gros" --
 * and none of those queries had a page to land on: the catalogue's category filter is
 * client-side JavaScript on one URL, and the footwear collection (335 references from a
 * Spanish maker) was reachable only as /shop?section=footwear with the apparel title on
 * it. This file gives every live category, both collections and every brand × category
 * pair that actually has stock its own address, built from live inventory exactly like
 * the brand pages: nothing here can advertise a category we do not carry.
 *
 * Loaded by inc/products.php; every page that includes the catalogue has these.
 *
 *   /b2b/<category-slug>                  one category (Sneakers, T-Shirts …)
 *   /b2b/<group-slug>                     a taxonomy group (Footwear, Tops, Accessories …)
 *   /b2b/apparel, /b2b/footwear           the two storefront collections (sections)
 *   /wholesale/<brand>/<category-slug>    brand × category ("Lacoste polos")
 */

/* Category <-> URL slug. Same rule as vestra_brand_slug so "Hoodies & Sweatshirts" ->
   "hoodies-sweatshirts" and "Women's T-Shirts" -> "women-s-t-shirts"; the reverse lookup
   goes through the live list, never by un-slugifying. */
function vestra_seo_cat_slug(string $cat): string {
    $s = strtolower(trim($cat));
    $s = preg_replace('~[^a-z0-9]+~', '-', $s) ?? $s;
    return trim($s, '-');
}

/** Live categories => listing count, biggest first. Derived from vestra_products(). */
function vestra_seo_cats(): array {
    static $cats = null;
    if ($cats === null) {
        $cats = [];
        foreach (vestra_products() as $p) {
            $c = trim((string)($p['cat'] ?? ''));
            if ($c === '' || strcasecmp($c, 'Other') === 0) continue;
            $cats[$c] = ($cats[$c] ?? 0) + 1;
        }
        arsort($cats);
    }
    return $cats;
}

/** Taxonomy groups that have at least one live listing => [cat => count]. */
function vestra_seo_groups(): array {
    static $groups = null;
    if ($groups === null) {
        $groups = [];
        $live = vestra_seo_cats();
        foreach (vestra_all_cats() as $g => $kids) {
            $have = [];
            foreach ($kids as $k) if (isset($live[$k])) $have[$k] = $live[$k];
            if ($have) { arsort($have); $groups[$g] = $have; }
        }
    }
    return $groups;
}

/* The storefront collections as SEO pages. 'footwear' is the section field, and the
   Footwear taxonomy group is the same shelf seen from the category side; a shoe filed
   under an unexpected category still belongs to the collection, so the collection page
   takes the union of the two. 'apparel' is everything else (section 'premium').

   IC CAMASIRI BOLMESININ SLUG'I 'intimates', 'underwear' DEGIL — ve bu bilincli
   bir secim, tembellik degil. 'Underwear' taksonomide ZATEN bir kategori yapragi
   ("Underwear & Socks" grubunun altinda) ve vestra_seo_resolve() kategoriye
   ONCE bakiyor. Ikisine ayni slug'i verseydim /b2b/underwear'in ne gosterdigi
   O KATEGORIDE STOK OLUP OLMAMASINA bagli olurdu: bugun bolme sayfasi, yarin
   o kategoriye bir ilan girince kategori sayfasi. Kampanya mektubundaki bir
   bagalantinin anlami stok degisince degisemez. Etiket yine "Underwear"
   (vestra_sections()); ayrisan yalniz adres. */
function vestra_seo_collections(): array {
    return ['apparel' => 'premium', 'footwear' => 'footwear', 'intimates' => 'underwear'];
}

/* Taksonomi grubu -> vitrin bolmesi. Bir urun beklenmedik bir kategoriye
   dosyalanmis olsa bile bolmesi onu ait oldugu rafa koyuyor; grup sayfasi bu
   yuzden iki kumenin BIRLESIMI. Eskiden yalnizca Footwear icin, govdenin
   icinde `$isFootwear` diye yaziliydi -- ic camasiri bolmesi eklenince ayni
   sey ikinci kez yazilacakti ve bu depo ayni olgunun ikinci kopyasinin ne
   ettigini yeterince kaydetti. */
function vestra_seo_group_sections(): array {
    return ['footwear' => 'footwear', 'underwear & socks' => 'underwear'];
}

/**
 * Resolve a /b2b/<slug> to what it names, or null when nothing in stock answers to it.
 * Returns ['kind' => 'cat'|'group'|'collection', 'name' => taxonomy name (English key),
 *          'slug' => canonical slug, 'items' => listings, 'cats' => [cat => count]].
 * Order matters: a category first, then a group, then a collection -- so a group and a
 * category sharing a name would resolve to the more specific one. An empty result is a
 * null, never an empty page: a thin "0 listings" page is worse for the domain than a 404.
 */
function vestra_seo_resolve(string $slug): ?array {
    $slug = vestra_seo_cat_slug($slug);
    if ($slug === '') return null;
    $all = vestra_products();

    foreach (vestra_seo_cats() as $c => $n) {
        if (vestra_seo_cat_slug($c) !== $slug) continue;
        $items = array_values(array_filter($all, fn($p) => strcasecmp(trim((string)($p['cat'] ?? '')), $c) === 0));
        return $items ? ['kind' => 'cat', 'name' => $c, 'slug' => $slug, 'items' => $items, 'cats' => [$c => count($items)]] : null;
    }
    foreach (vestra_seo_groups() as $g => $kids) {
        if (vestra_seo_cat_slug($g) !== $slug) continue;
        $set = array_change_key_case($kids, CASE_LOWER);
        $groupSection = vestra_seo_group_sections()[strtolower(trim($g))] ?? null;
        $items = array_values(array_filter($all, function ($p) use ($set, $groupSection) {
            if (isset($set[strtolower(trim((string)($p['cat'] ?? '')))])) return true;
            return $groupSection !== null && vestra_product_section($p) === $groupSection;
        }));
        return $items ? ['kind' => 'group', 'name' => $g, 'slug' => $slug, 'items' => $items, 'cats' => vestra_seo_count_cats($items)] : null;
    }
    foreach (vestra_seo_collections() as $cs => $section) {
        if ($cs !== $slug) continue;
        $items = array_values(array_filter($all, fn($p) => vestra_product_section($p) === $section));
        return $items ? ['kind' => 'collection', 'name' => vestra_section_label($section), 'slug' => $slug, 'items' => $items, 'cats' => vestra_seo_count_cats($items)] : null;
    }
    return null;
}

/** [cat => count] for a list of listings, biggest first. */
function vestra_seo_count_cats(array $items): array {
    $c = [];
    foreach ($items as $p) { $k = trim((string)($p['cat'] ?? '')); if ($k !== '') $c[$k] = ($c[$k] ?? 0) + 1; }
    arsort($c);
    return $c;
}

/** [brand => count] for a list of listings, biggest first. */
function vestra_seo_count_brands(array $items): array {
    $b = [];
    foreach ($items as $p) { $k = trim((string)($p['brand'] ?? '')); if ($k !== '') $b[$k] = ($b[$k] ?? 0) + 1; }
    arsort($b);
    return $b;
}

/** Categories one brand is in stock for => count. Feeds the chips on /wholesale/<brand>. */
function vestra_seo_brand_cats(string $brand): array {
    return vestra_seo_count_cats(array_values(array_filter(vestra_products(),
        fn($p) => strcasecmp(trim((string)($p['brand'] ?? '')), $brand) === 0)));
}

/**
 * Every landing page the site can stand behind right now, for the sitemap and for the
 * footer: [path, changefreq, priority]. Collections and groups first (broadest), then
 * categories, then brand × category pairs. A pair needs stock on both sides, which the
 * resolver already guarantees; the minimum of 1 is deliberate -- a single Versace polo is
 * still the page "Versace polos wholesale" should land on.
 */
function vestra_seo_landing_paths(): array {
    $out = [];
    foreach (array_keys(vestra_seo_collections()) as $cs) {
        if (vestra_seo_resolve($cs)) $out[] = ['/b2b/'.$cs, 'daily', '0.9'];
    }
    foreach (vestra_seo_groups() as $g => $_) {
        $slug = vestra_seo_cat_slug($g);
        if (isset(vestra_seo_collections()[$slug])) continue;   // 'footwear' group == collection page
        $out[] = ['/b2b/'.$slug, 'weekly', '0.8'];
    }
    foreach (vestra_seo_cats() as $c => $_) $out[] = ['/b2b/'.vestra_seo_cat_slug($c), 'weekly', '0.8'];
    $pairs = [];
    foreach (vestra_products() as $p) {
        $b = trim((string)($p['brand'] ?? '')); $c = trim((string)($p['cat'] ?? ''));
        if ($b === '' || $c === '' || strcasecmp($c, 'Other') === 0) continue;
        $pairs[vestra_brand_slug($b).'/'.vestra_seo_cat_slug($c)] = true;
    }
    ksort($pairs);
    foreach (array_keys($pairs) as $k) $out[] = ['/wholesale/'.$k, 'weekly', '0.7'];
    /* De-duplicate on path: a group whose slug collides with a category would otherwise
       list the same address twice. */
    $seen = []; $uniq = [];
    foreach ($out as $row) { if (isset($seen[$row[0]])) continue; $seen[$row[0]] = true; $uniq[] = $row; }
    return $uniq;
}

/* ── keywords / structured data helpers ─────────────────────────────────────── */

/** "Sneaker Großhandel, T-Shirts Großhandel, …" for the visitor's language; '' when empty. */
function vestra_seo_cat_keywords(string $lang, int $max = 12): string {
    $w = vestra_seo_wholesale_word($lang);
    $out = [];
    foreach (array_slice(array_keys(vestra_seo_cats()), 0, $max) as $c) $out[] = t($c).' '.$w;
    return implode(', ', $out);
}

/** Keyword line for a category (or brand × category) landing page, in the page language plus English. */
function vestra_seo_cat_b2b_keywords(string $cat, string $lang, ?string $brand = null): string {
    $out = [];
    $head = trim(($brand !== null ? $brand.' ' : '').t($cat));
    foreach (vestra_seo_b2b_terms($lang) as $term) $out[] = $head.' '.$term;
    if ($lang !== 'en') {
        $headEn = trim(($brand !== null ? $brand.' ' : '').$cat);
        foreach (vestra_seo_b2b_terms('en') as $term) $out[] = $headEn.' '.$term;
    }
    return implode(', ', array_unique($out));
}

/** Localised names of the live categories, for Organization.knowsAbout. */
function vestra_seo_knows_about(int $max = 14): array {
    $out = [];
    foreach (array_slice(array_keys(vestra_seo_cats()), 0, $max) as $c) $out[] = t($c);
    foreach (vestra_seo_collections() as $cs => $section) {
        if (vestra_seo_resolve($cs)) $out[] = t(vestra_section_label($section));
    }
    return array_values(array_unique($out));
}

/* ── Hedef PAZARLAR: ulke / bolge inis sayfalari ─────────────────────────────
 *
 * (operator, 17 Eyl 2026: "SEO yu kontrol et ve kusursuz hale getir catalogtaki
 * markalari da kullan sadece avrupa degil avustralya japonya dubai qatar ve usa da
 * olsun... avrupada kusursuz istiyorum" -> ayni gun: "brezilya ve guney amerika,
 * israil, singapur, g.koreyi de ekle").
 *
 * Avrupa'nin DIL sayfalari var (de/fr/it/es...), ama Sidney'deki, Dubai'deki ya da
 * New York'taki bir butik "wholesale fashion supplier Australia" diye ariyor ve o
 * sorgunun inebilecegi hicbir sayfa yoktu: hreflang tek basina icerik degil.
 * /wholesale-to/<pazar> o sayfa. Uzerindeki HER OLGU koddan turuyor -- para birimi
 * (vestra_currency_for_cc), bolgesel indirim (vestra_region_discount_rates), Avrupa
 * disi asgari siparis (VESTRA_NONEU_MIN_ORDER_USD), kapinin kayitta acilip
 * acilmadigi (vestra_auto_open_countries), dil (vlang_country_lang), ve markalar
 * canli stoktan. Sayfaya elle yazilmis tek bir rakam yok: KURAL 6'nin escrow
 * tavani dersi (metne gomulen rakam bes gun kodla celisti) burada da gecerli.
 *
 * TABLO TEK YERDE. hreflang etiketleri (i18n.php), Organization.areaServed, altbilgi
 * baglantilari, sitemap ve sayfanin kendisi hep buradan okuyor; bir pazar eklemek
 * BIR satir. 'countries' tasiyan giris bir BOLGE (Guney Amerika) -- olgular bolgenin
 * her ulkesinde AYNI ise yazilir, degilse SUSULUR: 12 ulkeden 11'inde gecerli bir
 * indirimi bolgenin tamamina yazmak, uydurma rakam basmak olurdu (KURAL 3).
 * 'cities' yalnizca anahtar kelime etiketine giriyor ("wholesale fashion Dubai"):
 * Dubai bir ulke degil ama operatorun ve alicinin aradigi kelime o. */
function vestra_seo_markets(): array {
    return [
        'australia'            => ['cc' => 'AU', 'name' => 'Australia',            'cities' => ['Sydney', 'Melbourne', 'Brisbane']],
        'japan'                => ['cc' => 'JP', 'name' => 'Japan',                'cities' => ['Tokyo', 'Osaka']],
        'united-arab-emirates' => ['cc' => 'AE', 'name' => 'United Arab Emirates', 'cities' => ['Dubai', 'Abu Dhabi']],
        'qatar'                => ['cc' => 'QA', 'name' => 'Qatar',                'cities' => ['Doha']],
        'united-states'        => ['cc' => 'US', 'name' => 'United States',        'cities' => ['New York', 'Los Angeles', 'Miami']],
        'brazil'               => ['cc' => 'BR', 'name' => 'Brazil',               'cities' => ['São Paulo', 'Rio de Janeiro']],
        'south-america'        => ['cc' => '',   'name' => 'South America',        'cities' => ['Buenos Aires', 'Santiago', 'Bogotá', 'Lima'],
                                   'countries' => vestra_south_america_codes()],
        'israel'               => ['cc' => 'IL', 'name' => 'Israel',               'cities' => ['Tel Aviv']],
        'singapore'            => ['cc' => 'SG', 'name' => 'Singapore',            'cities' => []],
        'south-korea'          => ['cc' => 'KR', 'name' => 'South Korea',          'cities' => ['Seoul']],
    ];
}

/** Bir pazar girisinin kapsadigi ulke kodlari -- tek ulke ya da bolgenin listesi. */
function vestra_seo_market_ccs(array $m): array {
    if (!empty($m['countries'])) return array_values($m['countries']);
    return $m['cc'] !== '' ? [$m['cc']] : [];
}

/** Pazar slug'i -> giris (+ 'slug'), yoksa null. Eslesme TAM (mango/zara dersi). */
function vestra_seo_market(string $slug): ?array {
    $slug = vestra_seo_cat_slug($slug);
    $all = vestra_seo_markets();
    if ($slug === '' || !isset($all[$slug])) return null;
    return $all[$slug] + ['slug' => $slug];
}

/**
 * Pazar sayfasinin OLGULARI, hepsi koddan. Bolgede olgular ulke ulke farkliysa
 * ilgili alan "bilinmiyor" degerine duser ve sayfa o cumleyi HIC basmaz:
 *   currency      'AUD' | 'USD' | ...   (bolgede tek degilse '')
 *   discount      yuzde (float)         (bolgede tek degilse 0.0)
 *   min_order_usd VESTRA_NONEU_MIN_ORDER_USD, Avrupa'daki bir pazar icin 0.0
 *   auto_open     true yalnizca HER ulke KURAL 2h listesindeyse
 *   langs         sitenin bu pazara servis ettigi diller (en her zaman dahil)
 *   hreflang      sayfanin bu pazar icin tasidigi etiketler (i18n.php'den)
 */
function vestra_seo_market_facts(array $m): array {
    $ccs = vestra_seo_market_ccs($m);
    if (!function_exists('vestra_currency_for_cc')) require_once __DIR__.'/money.php';
    if (!function_exists('vestra_region_discount_rates')) require_once __DIR__.'/region_discount.php';
    if (!function_exists('vestra_auto_open_countries')) require_once __DIR__.'/security.php';

    $one = function (array $vals) { $u = array_values(array_unique($vals)); return count($u) === 1 ? $u[0] : null; };

    $cur  = $one(array_map('vestra_currency_for_cc', $ccs));
    $rates = vestra_region_discount_rates();
    $disc = $one(array_map(fn($c) => (float)($rates[$c] ?? 0.0), $ccs));
    $eu   = array_filter($ccs, fn($c) => in_array($c, vestra_europe_codes(), true));
    $auto = array_keys(vestra_auto_open_countries());
    /* Bu pazara hangi DIL sayfalarini isaret ediyoruz? Cevap hreflang etiketlerinin
       KENDISINDEN cikiyor, ayri bir tablodan degil: sayfa "su dillerde hizmet
       veriyoruz" derken tam olarak etiketlerde soz verdigi seyi yazmali, yoksa iki
       kayit ayrisir. (Ilk yazimda vlang_country_lang okunuyordu -- o tablo IP'den DIL
       tahmini icin ve JAPONYA orada YOKTU: Japonya sayfasi "yalniz Ingilizce" diyordu,
       oysa ja-JP etiketi Japonca sayfayi gosteriyor. Tablo ayrica duzeltildi.)
       Ingilizce her zaman ilk: x-default Ingilizce sayfaya gidiyor. */
    $tags = [];
    $langs = ['en'];
    foreach (vlang_hreflang_map() as $tag => $tagLang) {
        if (strlen($tag) !== 5) continue;                    // yalniz xx-YY bolgesel kodlar
        if (!in_array(substr($tag, 3), $ccs, true)) continue;
        $tags[] = $tag;
        if (!in_array($tagLang, $langs, true)) $langs[] = $tagLang;
    }
    return [
        'ccs'           => $ccs,
        'currency'      => $cur ?? '',
        'discount'      => $disc ?? 0.0,
        'min_order_usd' => $eu ? 0.0 : (float)VESTRA_NONEU_MIN_ORDER_USD,
        'auto_open'     => $ccs && !array_diff($ccs, $auto),
        'langs'         => $langs,
        'hreflang'      => $tags,
    ];
}

/** Sitemap + altbilgi icin pazar yollari, tablo sirasinda. */
function vestra_seo_market_paths(): array {
    $out = [];
    foreach (array_keys(vestra_seo_markets()) as $slug) $out[] = ['/wholesale-to/'.$slug, 'monthly', '0.8'];
    return $out;
}

/**
 * Organization.areaServed -- TEK KAYNAK. 17 Eyl 2026'ya kadar iki kopya vardi ve
 * birbirini tutmuyordu: head.php alti kita sayiyor, index.php ise 'EU' diyordu; yani
 * ana sayfa her arama motoruna "yalniz Avrupa" derken alt sayfalar "her yer" diyordu.
 * Kitalar duz metin, hedef pazarlar Country nesnesi: schema.org ikisini de kabul
 * ediyor ve Country, "Australia" kelimesini bir ulke olarak okutuyor.
 */
function vestra_seo_area_served(): array {
    $out = ['Europe', 'Middle East', 'Asia', 'Oceania', 'North America', 'South America', 'Africa'];
    foreach (vestra_seo_markets() as $m) {
        if ($m['cc'] === '') continue;                       // bolge zaten kita adiyla listede
        $out[] = ['@type' => 'Country', 'name' => $m['name']];
    }
    return $out;
}

/**
 * Site geneli Organization JSON-LD -- head.php ve index.php ikisi de BUNU basar.
 * Eskiden iki ayri kopya: ana sayfanin aciklamasi Ingilizce ve 'EU', alt
 * sayfalarinki yerellestirilmis ve alti kita; knowsAbout ilkinde 14 markayla
 * kesiliyordu (Gucci ve Lacoste ana sayfada "bilinmiyor" sayiliyordu -- canli
 * olcum 17 Eyl 2026). Artik markalarin TAMAMI ("catalogtaki markalari da kullan").
 * $extra ile cagiran taraf alan ekler (ana sayfa: slogan).
 */
function vestra_seo_org_ld(array $extra = []): array {
    $host = 'https://vestrasales.com';
    $brands = vestra_seo_brands(0);
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'Organization',
        'name' => 'VESTRA', 'url' => $host, 'logo' => $host.'/inc/og-image.png',
        'description' => trim(t('Verified B2B fashion wholesale marketplace — branded apparel and textile basics from KYC-verified sellers across Europe, shipping worldwide.')
            .($brands ? ' '.sprintf(t('Houses in stock: %s.'), implode(', ', array_slice($brands, 0, 14))) : '')),
        'areaServed' => vestra_seo_area_served(),
        'email' => 'support@vestrasales.com',
        'inLanguage' => vlang(),
        /* Satis irtibati ve hangi dillerde: ContactPoint.availableLanguage schema.org'un
           bu bilgi icin ayirdigi alan; Organization'da dogrudan yok. Liste vlang_list()'ten. */
        'contactPoint' => [
            '@type' => 'ContactPoint', 'contactType' => 'sales', 'email' => 'support@vestrasales.com',
            'availableLanguage' => array_map('vlang_native_name', array_keys(vlang_list())),
        ],
        'knowsAbout' => array_values(array_unique(array_merge($brands, vestra_seo_knows_about(14)))),
    ];
    return array_merge($ld, $extra);
}
