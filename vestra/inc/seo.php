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

/* The two storefront collections as SEO pages. 'footwear' is the section field, and the
   Footwear taxonomy group is the same shelf seen from the category side; a shoe filed
   under an unexpected category still belongs to the collection, so the collection page
   takes the union of the two. 'apparel' is everything else (section 'premium'). */
function vestra_seo_collections(): array {
    return ['apparel' => 'premium', 'footwear' => 'footwear'];
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
        $isFootwear = strcasecmp($g, 'Footwear') === 0;
        $items = array_values(array_filter($all, function ($p) use ($set, $isFootwear) {
            if (isset($set[strtolower(trim((string)($p['cat'] ?? '')))])) return true;
            return $isFootwear && vestra_product_section($p) === 'footwear';
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
