<?php
/**
 * VESTRA — per-brand B2B landing page:  /wholesale/<brand-slug>
 *
 * Why this page exists. Trade buyers do not search "marketplace" or "catalogue"; they
 * search "Lacoste wholesale", "DSQUARED2 B2B supplier", "Ralph Lauren stock lot". Until
 * now the only per-brand URL on this site was /catalog?brand=lacoste, which returns an
 * .xlsx download -- nothing for a search engine to read, so those queries had no page to
 * land on anywhere on the domain. This is that page, one per house actually in stock.
 *
 * It is built from live inventory, never from a fixed list: a brand that sells out stops
 * having a page (410-ish: a plain 404), and a brand added tomorrow has one immediately.
 * That matters more here than elsewhere -- an indexed page promising a house we no longer
 * carry is the kind of thing a buyer remembers.
 */
require __DIR__.'/inc/products.php';

$brand = vestra_brand_from_slug((string)($_GET['brand'] ?? ''));
if ($brand === null) { http_response_code(404); require __DIR__.'/404.php'; exit; }

$items = array_values(array_filter(vestra_products(),
    fn($p) => strcasecmp(trim((string)($p['brand'] ?? '')), $brand) === 0));
if (!$items) { http_response_code(404); require __DIR__.'/404.php'; exit; }

$_lang  = vlang();
$_slug  = vestra_brand_slug($brand);
$_url   = 'https://vestrasales.com/wholesale/'.$_slug;
$_wholesale = vestra_seo_wholesale_word($_lang);

/* Title carries the two words the query is built from -- the house and the trade term.
   Everything else in the title is noise competing for the same pixels. */
$PAGE     = sprintf(t('%1$s %2$s — B2B supplier'), $brand, $_wholesale);
$META     = sprintf(t('%1$s %2$s at VESTRA: %3$d listings for boutiques and retailers. Trade prices after registration, low minimums, invoice-based B2B ordering and worldwide shipping from KYC-verified sellers.'),
                    $brand, $_wholesale, count($items));
$KEYWORDS = vestra_seo_brand_b2b_keywords($brand, $_lang);

/* Structured data: the page is a collection of offers for one brand, so it says exactly
   that. ItemList carries the listings themselves -- a search engine that reads it knows
   how deep the range is without crawling every product page. */
$_ldItems = [];
foreach (array_slice($items, 0, 30) as $i => $p) {
    $_ldItems[] = [
        '@type' => 'ListItem', 'position' => $i + 1,
        'name'  => trim(($p['brand'] ?? '').' '.($p['name'] ?? '')),
        'url'   => 'https://vestrasales.com/product?id='.rawurlencode((string)($p['id'] ?? '')),
    ];
}
$JSONLD = [
    ['@context' => 'https://schema.org', '@type' => 'CollectionPage',
     'name' => $PAGE, 'description' => $META, 'url' => $_url, 'inLanguage' => $_lang,
     'about' => ['@type' => 'Brand', 'name' => $brand],
     'isPartOf' => ['@type' => 'WebSite', 'name' => 'VESTRA', 'url' => 'https://vestrasales.com'],
     'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($items), 'itemListElement' => $_ldItems]],
    ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => t('Home'),    'item' => 'https://vestrasales.com/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => t('Catalog'), 'item' => 'https://vestrasales.com/shop'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $PAGE,        'item' => $_url],
    ]],
];

$NAV = 'shop';
require __DIR__.'/inc/head.php';

$cats = [];
foreach ($items as $p) { $c = trim((string)($p['cat'] ?? '')); if ($c !== '') $cats[$c] = ($cats[$c] ?? 0) + 1; }
arsort($cats);
$moqs = array_filter(array_map(fn($p) => (int)($p['moq'] ?? 0), $items));
?>
<style>
.wsw{--ink:#211d17;--mut:#6f695e;--acc:#a97f2c;--line:#e6e0d5;background:#f4f2ee;color:var(--ink);padding:0 0 56px}
.wsw .wrap{max-width:1180px;margin:0 auto;padding:0 20px}
.wshero{padding:44px 0 26px;border-bottom:1px solid var(--line)}
.wsbc{font-size:12px;color:var(--mut);margin-bottom:14px}
.wsbc a{color:var(--mut);text-decoration:none}.wsbc a:hover{color:var(--acc)}
.wshero h1{font-family:'Playfair Display',serif;font-size:clamp(28px,4vw,44px);line-height:1.1;margin:0 0 12px;color:var(--ink)}
.wslede{font-size:15px;line-height:1.65;color:var(--mut);max-width:760px;margin:0 0 18px}
.wsfacts{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.wsfact{background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px 14px;font-size:13px}
.wsfact b{display:block;font-size:18px;color:var(--ink);font-family:'Playfair Display',serif}
.wsfact span{color:var(--mut)}
.wscta{display:inline-block;margin-top:22px;background:var(--acc);color:#fff;text-decoration:none;
  padding:12px 22px;border-radius:10px;font-weight:600;font-size:14px}
.wscta:hover{filter:brightness(1.06)}
.wscta2{display:inline-block;margin:22px 0 0 10px;border:1px solid var(--line);background:#fff;color:var(--ink);
  text-decoration:none;padding:12px 20px;border-radius:10px;font-size:14px}
.wssec{padding:34px 0 0}
.wssec h2{font-family:'Playfair Display',serif;font-size:22px;margin:0 0 14px}
.wsgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px}
.wscard{background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden;text-decoration:none;color:inherit;display:flex;flex-direction:column}
.wscard:hover{box-shadow:0 6px 20px rgba(60,50,30,.10)}
.wsthumb{aspect-ratio:3/4;background:linear-gradient(135deg,#2a2a30,#0e0e11);position:relative;overflow:hidden}
.wsthumb img{width:100%;height:100%;object-fit:cover;display:block}
.wsbody{padding:11px 12px 13px;display:flex;flex-direction:column;gap:3px}
.wsbrand{font-size:10px;letter-spacing:.09em;text-transform:uppercase;color:var(--acc);font-weight:700}
.wsname{font-size:13px;line-height:1.35;color:var(--ink)}
.wsmeta{font-size:11px;color:var(--mut)}
.wscopy{max-width:820px;font-size:14.5px;line-height:1.75;color:#3d382f}
.wscopy h2{margin-top:26px}
.wscopy ul{padding-left:18px;margin:10px 0}
.wscopy li{margin:5px 0}
.wsother{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.wsother a{background:#fff;border:1px solid var(--line);border-radius:999px;padding:7px 14px;
  font-size:13px;text-decoration:none;color:var(--ink)}
.wsother a:hover{border-color:var(--acc);color:var(--acc)}
</style>

<div class="wsw">
<div class="wrap">

  <div class="wshero">
    <div class="wsbc">
      <a href="/"><?= t('Home') ?></a> › <a href="/shop"><?= t('Catalog') ?></a> › <?= htmlspecialchars($brand) ?>
    </div>
    <h1><?= htmlspecialchars(sprintf(t('%1$s %2$s'), $brand, $_wholesale)) ?></h1>
    <p class="wslede">
      <?= htmlspecialchars(sprintf(
          t('Authentic %s for boutiques, multi-brand retailers and outlets. Every seller on VESTRA is KYC-verified before a listing goes live, orders are invoiced B2B, and stock ships worldwide.'),
          $brand)) ?>
    </p>

    <div class="wsfacts">
      <div class="wsfact"><b><?= count($items) ?></b><span><?= t('listings in stock') ?></span></div>
      <?php if ($moqs): ?>
      <div class="wsfact"><b><?= min($moqs) ?></b><span><?= t('lowest MOQ (pc)') ?></span></div>
      <?php endif; ?>
      <div class="wsfact"><b><?= count($cats) ?></b><span><?= t('product categories') ?></span></div>
      <div class="wsfact"><b>B2B</b><span><?= t('invoice-based ordering') ?></span></div>
    </div>

    <?= vestra_join_cta(sprintf(t('See %s trade prices'), $brand), 'wscta') ?>
    <a class="wscta2" href="/catalog?brand=<?= urlencode($brand) ?>"><?= t('Download line sheet (.xlsx)') ?></a>
  </div>

  <div class="wssec">
    <h2><?= htmlspecialchars(sprintf(t('%s listings'), $brand)) ?></h2>
    <div class="wsgrid">
      <?php foreach (array_slice($items, 0, 24) as $p):
            $img = $MEMBER ? vestra_primary_image($p) : null; ?>
        <a class="wscard" href="/product?id=<?= urlencode((string)($p['id'] ?? '')) ?>">
          <div class="wsthumb">
            <?php if ($img): ?>
              <img src="<?= htmlspecialchars($img) ?>" loading="lazy"
                   alt="<?= htmlspecialchars(trim(($p['brand'] ?? '').' '.($p['name'] ?? ''))) ?>">
            <?php else: echo vestra_brand_card($p['brand'] ?? $brand); endif; ?>
          </div>
          <div class="wsbody">
            <span class="wsbrand"><?= htmlspecialchars((string)($p['brand'] ?? '')) ?></span>
            <span class="wsname"><?= htmlspecialchars((string)($p['name'] ?? '')) ?></span>
            <span class="wsmeta"><?= htmlspecialchars((string)($p['cat'] ?? '')) ?> · MOQ <?= (int)($p['moq'] ?? 0) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (count($items) > 24): ?>
      <p style="margin-top:16px"><a href="/shop" style="color:var(--acc)">
        <?= htmlspecialchars(sprintf(t('See all %d listings in the catalogue →'), count($items))) ?></a></p>
    <?php endif; ?>
  </div>

  <div class="wssec wscopy">
    <h2><?= htmlspecialchars(sprintf(t('Buying %s wholesale'), $brand)) ?></h2>
    <p><?= htmlspecialchars(sprintf(
        t('%s stock on VESTRA comes from verified European sellers — overstock, end-of-season and current-season lots. Quantities are per listing rather than per container, so a single boutique can order a workable run without committing to a distributor contract.'),
        $brand)) ?></p>
    <ul>
      <li><?= t('Trade prices become visible once your account is registered — they are not shown publicly.') ?></li>
      <li><?= t('Minimums are set per listing; many start well below a full carton.') ?></li>
      <li><?= t('Every seller passes KYC before listing, and payment is held until the goods are confirmed.') ?></li>
      <li><?= t('Invoiced B2B, with VAT handled per your country. Shipping worldwide.') ?></li>
    </ul>
    <?php
      $others = array_values(array_filter(vestra_seo_brands(0), fn($b) => strcasecmp($b, $brand) !== 0));
      if ($others): ?>
      <h2><?= t('Other houses in stock') ?></h2>
      <div class="wsother">
        <?php foreach (array_slice($others, 0, 18) as $b): ?>
          <a href="/wholesale/<?= urlencode(vestra_brand_slug($b)) ?>">
            <?= htmlspecialchars($b) ?> <?= htmlspecialchars($_wholesale) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</div>
<?php require __DIR__.'/inc/foot.php'; ?>
