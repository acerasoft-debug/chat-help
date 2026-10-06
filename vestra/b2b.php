<?php
/**
 * VESTRA — category, collection and brand × category B2B landing pages.
 *
 *   /b2b/<category-or-group-slug>          e.g. /b2b/sneakers, /b2b/t-shirts, /b2b/footwear
 *   /wholesale/<brand-slug>/<category>     e.g. /wholesale/lacoste/polos
 *
 * The brand pages (wholesale.php) answer "Lacoste wholesale"; these answer the other
 * half of what a trade buyer types -- "sneakers wholesale", "T-Shirts Großhandel",
 * "polos Lacoste en gros". Same rules as the brand pages: built from live stock, never a
 * fixed list, and an empty answer is a 404 rather than a thin page. The taxonomy and the
 * resolver live in inc/seo.php.
 */
require __DIR__.'/inc/products.php';

$res = vestra_seo_resolve((string)($_GET['cat'] ?? ''));
if ($res === null) { http_response_code(404); require __DIR__.'/404.php'; exit; }

$brand = null;
if (trim((string)($_GET['brand'] ?? '')) !== '') {
    $brand = vestra_brand_from_slug((string)$_GET['brand']);
    if ($brand === null) { http_response_code(404); require __DIR__.'/404.php'; exit; }
}

$items = $res['items'];
if ($brand !== null) {
    $items = array_values(array_filter($items, fn($p) => strcasecmp(trim((string)($p['brand'] ?? '')), $brand) === 0));
}
if (!$items) { http_response_code(404); require __DIR__.'/404.php'; exit; }

$_lang      = vlang();
$_wholesale = vestra_seo_wholesale_word($_lang);
$_catName   = t($res['name']);                       // localised: "Sneaker", "Baskets", "Zapatillas deportivas"
$_bslug     = $brand !== null ? vestra_brand_slug($brand) : '';
$_path      = $brand !== null ? '/wholesale/'.$_bslug.'/'.$res['slug'] : '/b2b/'.$res['slug'];
$_url       = 'https://vestrasales.com'.$_path;
$_brands    = vestra_seo_count_brands($items);        // brand => count, within this page
$_cats      = vestra_seo_count_cats($items);
$_moqs      = array_filter(array_map(fn($p) => (int)($p['moq'] ?? 0), $items));
$_units     = array_count_values(array_map(fn($p) => (string)($p['unit'] ?? 'pc'), $items));
arsort($_units); $_unit = (string)array_key_first($_units);
/* Which storefront section "see all" should open: the collection the page's stock lives in. */
$_sections  = array_count_values(array_map('vestra_product_section', $items));
arsort($_sections); $_section = (string)array_key_first($_sections);
$_shopUrl   = '/shop'.($_section !== 'premium' ? '?section='.urlencode($_section) : '');

/* Title = the words the query is built from. Brand × category puts the brand first in
   English and German, after the noun in the Romance languages -- the dictionaries carry
   the word order, this file only supplies the three parts. */
if ($brand !== null) {
    $PAGE = sprintf(t('%1$s %2$s %3$s — B2B supplier'), $brand, $_catName, $_wholesale);
    $_h1  = sprintf(t('%1$s %2$s %3$s'), $brand, $_catName, $_wholesale);
} else {
    $PAGE = sprintf(t('%1$s %2$s — B2B supplier'), $_catName, $_wholesale);
    $_h1  = sprintf(t('%1$s %2$s'), $_catName, $_wholesale);
}
$_brandList = implode(', ', array_slice(array_keys($_brands), 0, 5));
$META = sprintf(t('%1$s %2$s at VESTRA: %3$d listings from %4$s. Trade prices after registration, low minimums, invoice-based B2B ordering and shipping across Europe and worldwide from KYC-verified sellers.'),
                $brand !== null ? $brand.' '.$_catName : $_catName, $_wholesale, count($items), $_brandList);
$KEYWORDS = vestra_seo_cat_b2b_keywords($res['name'], $_lang, $brand);
/* Category keywords of what is actually on the page, not the whole site: a "Footwear"
   page lists the shoe categories, a "T-Shirts" page lists the houses. */
$_extraKw = [];
foreach (array_slice(array_keys($_cats), 0, 8) as $c) if ($c !== $res['name']) $_extraKw[] = t($c).' '.$_wholesale;
foreach (array_slice(array_keys($_brands), 0, 8) as $b) $_extraKw[] = $b.' '.$_catName.' '.$_wholesale;
if ($_extraKw) $KEYWORDS .= ', '.implode(', ', $_extraKw);

$_ldItems = [];
foreach (array_slice($items, 0, 30) as $i => $p) {
    $_ldItems[] = ['@type' => 'ListItem', 'position' => $i + 1,
        'name' => trim(($p['brand'] ?? '').' '.($p['name'] ?? '')),
        'url'  => 'https://vestrasales.com/product?id='.rawurlencode((string)($p['id'] ?? ''))];
}
$_crumbs = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => t('Home'),    'item' => 'https://vestrasales.com/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => t('Catalog'), 'item' => 'https://vestrasales.com/shop'],
];
if ($brand !== null) {
    $_crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $brand, 'item' => 'https://vestrasales.com/wholesale/'.$_bslug];
}
$_crumbs[] = ['@type' => 'ListItem', 'position' => count($_crumbs) + 1, 'name' => $PAGE, 'item' => $_url];
$JSONLD = [
    ['@context' => 'https://schema.org', '@type' => 'CollectionPage',
     'name' => $PAGE, 'description' => $META, 'url' => $_url, 'inLanguage' => $_lang,
     'about' => $brand !== null
         ? ['@type' => 'Brand', 'name' => $brand]
         : ['@type' => 'Thing', 'name' => $_catName],
     'isPartOf' => ['@type' => 'WebSite', 'name' => 'VESTRA', 'url' => 'https://vestrasales.com'],
     'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($items), 'itemListElement' => $_ldItems]],
    ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $_crumbs],
];

$NAV = 'shop';
require __DIR__.'/inc/head.php';
?>
<div class="wsw">
<div class="wrap">

  <div class="wshero">
    <div class="wsbc">
      <a href="/"><?= t('Home') ?></a> › <a href="/shop"><?= t('Catalog') ?></a>
      <?php if ($brand !== null): ?> › <a href="/wholesale/<?= urlencode($_bslug) ?>"><?= htmlspecialchars($brand) ?></a><?php endif; ?>
      › <?= htmlspecialchars($_catName) ?>
    </div>
    <h1><?= htmlspecialchars($_h1) ?></h1>
    <p class="wslede">
      <?= htmlspecialchars(sprintf(
          t('Authentic %1$s from %2$s for boutiques, multi-brand retailers and outlets. Every seller on VESTRA is KYC-verified before a listing goes live, orders are invoiced B2B, and stock ships across Europe and worldwide.'),
          $_catName, $brand !== null ? $brand : $_brandList)) ?>
    </p>

    <div class="wsfacts">
      <div class="wsfact"><b><?= count($items) ?></b><span><?= t('listings in stock') ?></span></div>
      <?php if ($_moqs): ?>
      <div class="wsfact"><b><?= min($_moqs) ?></b><span><?= $_unit === 'pc' ? t('lowest MOQ (pc)') : t('lowest MOQ') ?></span></div>
      <?php endif; ?>
      <?php if ($brand === null): ?>
      <div class="wsfact"><b><?= count($_brands) ?></b><span><?= t('brands') ?></span></div>
      <?php endif; ?>
      <?php if (count($_cats) > 1): ?>
      <div class="wsfact"><b><?= count($_cats) ?></b><span><?= t('product categories') ?></span></div>
      <?php endif; ?>
      <div class="wsfact"><b>B2B</b><span><?= t('invoice-based ordering') ?></span></div>
    </div>

    <?= vestra_join_cta(sprintf(t('See %s trade prices'), $brand !== null ? $brand.' '.$_catName : $_catName), 'wscta') ?>
    <?php if ($brand !== null): ?>
      <a class="wscta2" href="/catalog?brand=<?= urlencode($brand) ?>"><?= t('Download line sheet (.xlsx)') ?></a>
    <?php else: ?>
      <a class="wscta2" href="<?= htmlspecialchars($_shopUrl) ?>"><?= t('Catalog') ?> →</a>
    <?php endif; ?>
  </div>

  <div class="wssec">
    <h2><?= htmlspecialchars(sprintf(t('%s listings'), $_h1)) ?></h2>
    <div class="wsgrid">
      <?php foreach (array_slice($items, 0, 24) as $p):
            $img = $MEMBER ? vestra_primary_image($p) : null; ?>
        <a class="wscard" href="/product?id=<?= urlencode((string)($p['id'] ?? '')) ?>">
          <div class="wsthumb">
            <?php if ($img): ?>
              <img src="<?= htmlspecialchars($img) ?>" loading="lazy"
                   alt="<?= htmlspecialchars(trim(($p['brand'] ?? '').' '.($p['name'] ?? ''))) ?>">
            <?php else: echo vestra_brand_card($p['brand'] ?? ''); endif; ?>
          </div>
          <div class="wsbody">
            <span class="wsbrand"><?= htmlspecialchars((string)($p['brand'] ?? '')) ?></span>
            <span class="wsname"><?= htmlspecialchars((string)($p['name'] ?? '')) ?></span>
            <span class="wsmeta"><?= htmlspecialchars(t((string)($p['cat'] ?? ''))) ?> · MOQ <?= (int)($p['moq'] ?? 0) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (count($items) > 24): ?>
      <p style="margin-top:16px"><a href="<?= htmlspecialchars($_shopUrl) ?>" style="color:var(--acc)">
        <?= htmlspecialchars(sprintf(t('See all %d listings in this category →'), count($items))) ?></a></p>
    <?php endif; ?>
  </div>

  <div class="wssec wscopy">
    <h2><?= htmlspecialchars(sprintf(t('Buying %s wholesale'), $brand !== null ? $brand.' '.$_catName : $_catName)) ?></h2>
    <p><?= htmlspecialchars(sprintf(
        t('%s stock on VESTRA comes from verified European sellers — overstock, end-of-season and current-season lots. Quantities are per listing rather than per container, so a single boutique can order a workable run without committing to a distributor contract.'),
        $brand !== null ? $brand.' '.$_catName : $_catName)) ?></p>
    <ul>
      <li><?= t('Trade prices become visible once your account is registered — they are not shown publicly.') ?></li>
      <li><?= t('Minimums are set per listing; many start well below a full carton.') ?></li>
      <li><?= t('Every seller passes KYC before listing, and payment is held until the goods are confirmed.') ?></li>
      <li><?= t('Invoiced B2B, with VAT handled per your country. Shipping worldwide.') ?></li>
    </ul>

    <?php /* Sub-categories of a group / collection page: the Footwear page links Sneakers,
             Boots, Sandals … each with its own address. */
          if ($brand === null && count($_cats) > 1): ?>
      <h2><?= t('Wholesale by category') ?></h2>
      <div class="wsother">
        <?php foreach ($_cats as $c => $n): if (vestra_seo_cat_slug($c) === $res['slug']) continue; ?>
          <a href="/b2b/<?= urlencode(vestra_seo_cat_slug($c)) ?>"><?= htmlspecialchars(t($c).' '.$_wholesale) ?> <span class="wsn"><?= (int)$n ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php /* Brands on a category page link to the brand × category page; on a brand ×
             category page the other houses in the same category do the same. */
          $_otherBrands = $brand === null ? $_brands
              : array_filter(vestra_seo_count_brands($res['items']), fn($n, $b) => strcasecmp($b, $brand) !== 0, ARRAY_FILTER_USE_BOTH);
          if ($_otherBrands): ?>
      <h2><?= $brand === null ? t('Brands in this category') : t('Other houses in stock') ?></h2>
      <div class="wsother">
        <?php foreach (array_slice($_otherBrands, 0, 24, true) as $b => $n):
              /* A brand × group pair has no page of its own; link the brand page instead. */
              $href = $res['kind'] === 'cat' ? '/wholesale/'.urlencode(vestra_brand_slug($b)).'/'.urlencode($res['slug'])
                                             : '/wholesale/'.urlencode(vestra_brand_slug($b)); ?>
          <a href="<?= $href ?>"><?= htmlspecialchars($b.' '.$_catName) ?> <span class="wsn"><?= (int)$n ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php $_more = array_filter(vestra_seo_cats(), fn($n, $c) => !isset($_cats[$c]), ARRAY_FILTER_USE_BOTH);
          if ($_more): ?>
      <h2><?= t('Other categories in stock') ?></h2>
      <div class="wsother">
        <?php foreach (array_slice($_more, 0, 18, true) as $c => $n): ?>
          <a href="/b2b/<?= urlencode(vestra_seo_cat_slug($c)) ?>"><?= htmlspecialchars(t($c).' '.$_wholesale) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</div>
<?php require __DIR__.'/inc/foot.php'; ?>
