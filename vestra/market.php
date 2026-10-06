<?php
/**
 * VESTRA — pazar (ulke/bolge) inis sayfasi:  /wholesale-to/<pazar>
 *
 * (operator, 17 Eyl 2026: "SEO yu kontrol et ve kusursuz hale getir catalogtaki
 * markalari da kullan sadece avrupa degil avustralya japonya dubai qatar ve usa da
 * olsun... avrupada kusursuz istiyorum" + ayni gun "brezilya ve guney amerika,
 * israil, singapur, g.koreyi de ekle".)
 *
 * Neden bu sayfa var. Site Avrupa'da dil dil kuruluydu ve hreflang da eklendi -- ama
 * hreflang bir ETIKET, icerik degil: Sidney'deki, Dubai'deki ya da Seul'deki bir
 * butik "wholesale fashion supplier Australia" diye ariyor ve bu alan adinda o
 * sorgunun inebilecegi TEK bir sayfa yoktu. Marka sayfalari "Lacoste wholesale"i,
 * /b2b sayfalari "sneakers wholesale"i karsiliyor; bu sayfa ucuncu soruyu
 * karsiliyor: "bana gonderiyor musunuz, hangi parayla, en az ne kadar".
 *
 * SAYFADAKI HICBIR RAKAM ELLE YAZILI DEGIL. Para birimi vestra_currency_for_cc'den,
 * bolgesel indirim vestra_region_discount_rates'ten, Avrupa disi asgari siparis
 * VESTRA_NONEU_MIN_ORDER_USD'den, kapinin kayitta acilip acilmadigi
 * vestra_auto_open_countries'ten, diller vlang_country_lang'dan, markalar ve
 * kategoriler CANLI stoktan geliyor (vestra_seo_market_facts, inc/seo.php).
 * Sebebi bu depoda yazili: escrow tavani bes gun boyunca metinde 3.000, kodda
 * 3.500 kaldi (KURAL 6) -- musteriye soylenen ile kasanin uyguladigi ayristi.
 *
 * Bir olgu o pazarin HER ulkesinde ayni degilse SATIR HIC BASILMIYOR (bolge
 * sayfasinda 12 ulkenin 11'inde gecerli bir indirimi hepsine yazmak KURAL 3'un
 * yasakladigi sey). Stokta hicbir sey yoksa sayfa 404: ince sayfa alan adina zarar
 * verir (KURAL 9).
 */
require __DIR__.'/inc/products.php';

$mk = vestra_seo_market((string)($_GET['market'] ?? ''));
if ($mk === null) { http_response_code(404); require __DIR__.'/404.php'; exit; }

$items = vestra_products();
if (!$items) { http_response_code(404); require __DIR__.'/404.php'; exit; }

$_lang      = vlang();
$_facts     = vestra_seo_market_facts($mk);
$_name      = t($mk['name']);                       // yerellestirilmis ulke adi
$_wholesale = vestra_seo_wholesale_word($_lang);
$_url       = 'https://vestrasales.com/wholesale-to/'.$mk['slug'];
$_brands    = vestra_seo_count_brands($items);
$_cats      = vestra_seo_count_cats($items);
$_colls     = [];
foreach (vestra_seo_collections() as $_cs => $_sec) if (vestra_seo_resolve($_cs)) $_colls[$_cs] = vestra_section_label($_sec);

$PAGE = sprintf(t('Wholesale fashion supplier for %s'), $_name);
$META = sprintf(t('Wholesale branded fashion delivered to %1$s: %2$d listings from %3$d houses, KYC-verified sellers, trade prices after registration and invoice-based B2B ordering.'),
                $_name, count($items), count($_brands));

/* Anahtar kelimeler: "<marka> wholesale <ulke>" ve "<kategori> wholesale <ulke>",
   arti operatorun adiyla saydigi SEHIRLER ("wholesale fashion Dubai"). Sehir adlari
   LATIN harfleriyle ve cevrilmeden basiliyor -- bilerek: meta keywords zaten en zayif
   sinyal ve on sehir adini sekiz dile cevirmek, sozluge karsiligi olcumlenemeyen 80
   anahtar eklemek olurdu. Ulke adi cevriliyor, cunku o baslikta ve govdede de geciyor. */
$_kw = [];
foreach (vestra_seo_b2b_terms($_lang) as $term) $_kw[] = trim(t('Fashion').' '.$term.' '.$_name);
foreach (array_slice(array_keys($_brands), 0, 12) as $b) $_kw[] = $b.' '.$_wholesale.' '.$_name;
foreach (array_slice(array_keys($_cats), 0, 8) as $c)   $_kw[] = t($c).' '.$_wholesale.' '.$_name;
foreach (($mk['cities'] ?? []) as $city)                $_kw[] = 'wholesale fashion supplier '.$city;
$KEYWORDS = implode(', ', array_unique($_kw));

$_ldItems = [];
foreach (array_slice($items, 0, 30) as $i => $p) {
    $_ldItems[] = ['@type' => 'ListItem', 'position' => $i + 1,
        'name' => trim(($p['brand'] ?? '').' '.($p['name'] ?? '')),
        'url'  => 'https://vestrasales.com/product?id='.rawurlencode((string)($p['id'] ?? ''))];
}
$JSONLD = [
    ['@context' => 'https://schema.org', '@type' => 'CollectionPage',
     'name' => $PAGE, 'description' => $META, 'url' => $_url, 'inLanguage' => $_lang,
     /* about = ulke (bolgede Place): sayfanin konusu bir cografya, bir marka degil. */
     'about' => ['@type' => ($mk['cc'] !== '' ? 'Country' : 'Place'), 'name' => $mk['name']],
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

/* Olgu kartlari: yalnizca CEVABI OLANLAR. Bos bir kart "bilmiyoruz" demenin en
   gurultulu yolu olurdu. Rakamlar yukarida koddan cozuldu. */
$_pct = fn(float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
$_facts_rows = [];
if ($_facts['currency'] !== '')
    $_facts_rows[] = [$_facts['currency'], t('prices shown in')];
if ($_facts['discount'] > 0)
    $_facts_rows[] = ['-'.$_pct($_facts['discount']).'%', t('standing trade discount')];
if ($_facts['min_order_usd'] > 0)
    $_facts_rows[] = ['US$'.number_format($_facts['min_order_usd'], 0, '.', ','), t('minimum order value')];
$_facts_rows[] = [count($_brands), t('brands')];
$_facts_rows[] = ['B2B', t('invoice-based ordering')];
?>
<div class="wsw">
<div class="wrap">

  <div class="wshero">
    <div class="wsbc">
      <a href="/"><?= t('Home') ?></a> › <a href="/shop"><?= t('Catalog') ?></a> › <?= htmlspecialchars($_name) ?>
    </div>
    <h1><?= htmlspecialchars(sprintf(t('Wholesale fashion for %s'), $_name)) ?></h1>
    <p class="wslede">
      <?= htmlspecialchars(sprintf(
          t('Authentic branded apparel, footwear and intimates for boutiques and multi-brand retailers in %1$s. Every seller on VESTRA is KYC-verified before a listing goes live, orders are invoiced B2B, and stock ships from Europe to %1$s.'),
          $_name)) ?>
    </p>

    <div class="wsfacts">
      <?php foreach ($_facts_rows as [$big, $label]): ?>
      <div class="wsfact"><b><?= htmlspecialchars((string)$big) ?></b><span><?= htmlspecialchars($label) ?></span></div>
      <?php endforeach; ?>
    </div>

    <?= vestra_join_cta(t('See trade prices'), 'wscta', 'buyer') ?>
    <a class="wscta2" href="/shop"><?= t('Catalog') ?> →</a>
  </div>

  <div class="wssec wscopy">
    <h2><?= htmlspecialchars(sprintf(t('Ordering from %s'), $_name)) ?></h2>
    <ul>
      <?php /* Her madde bir OLGU ve olgunun kaynagi kodda; cevabi olmayan madde
               hic basilmiyor. Kapinin kayitta acildigi ulkeler KURAL 2h'de. */ ?>
      <?php if ($_facts['auto_open']): ?>
        <li><?= htmlspecialchars(sprintf(t('Buyer accounts registered in %s are opened at sign-up — trade prices and ordering are live straight away.'), $_name)) ?></li>
      <?php else: ?>
        <li><?= t('Trade prices become visible once your account is registered — they are not shown publicly.') ?></li>
      <?php endif; ?>
      <?php if ($_facts['discount'] > 0): ?>
        <li><?= htmlspecialchars(sprintf(t('Accounts registered in %1$s carry a standing %2$s%% discount on the whole catalogue.'), $_name, $_pct($_facts['discount']))) ?></li>
      <?php endif; ?>
      <?php if ($_facts['min_order_usd'] > 0): ?>
        <li><?= htmlspecialchars(sprintf(t('Orders shipped outside Europe start at US$%s.'), number_format($_facts['min_order_usd'], 0, '.', ','))) ?></li>
      <?php endif; ?>
      <?php if ($_facts['currency'] !== '' && $_facts['currency'] !== 'EUR'): ?>
        <li><?= htmlspecialchars(sprintf(t('Prices are shown in %s and invoiced in EUR, or in USD on request.'), $_facts['currency'])) ?></li>
      <?php endif; ?>
      <li><?= t('Minimums are set per listing; many start well below a full carton.') ?></li>
      <li><?= t('Every seller passes KYC before listing, and payment is held until the goods are confirmed.') ?></li>
      <li><?= htmlspecialchars(sprintf(t('VESTRA is available in %s.'), implode(', ', array_map('vlang_native_name', $_facts['langs'])))) ?></li>
    </ul>
  </div>

  <div class="wssec">
    <h2><?= htmlspecialchars(sprintf(t('%s listings'), $_name)) ?></h2>
    <div class="wsgrid">
      <?php foreach (array_slice($items, 0, 12) as $p):
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
  </div>

  <div class="wssec wscopy">
    <?php /* KATALOGTAKI MARKALARIN TAMAMI (operatorun kendi cumlesi: "catalogtaki
             markalari da kullan"). Kesme yok -- marka sayfasi zaten her marka icin
             var ve bu sayfa onlara baglanan tek pazar sayfasi. */ ?>
    <h2><?= t('Wholesale by brand') ?></h2>
    <div class="wsother">
      <?php foreach ($_brands as $b => $n): ?>
        <a href="/wholesale/<?= urlencode(vestra_brand_slug($b)) ?>"><?= htmlspecialchars($b.' '.$_wholesale) ?> <span class="wsn"><?= (int)$n ?></span></a>
      <?php endforeach; ?>
    </div>

    <h2><?= t('Wholesale by category') ?></h2>
    <div class="wsother">
      <?php foreach ($_cats as $c => $n): ?>
        <a href="/b2b/<?= urlencode(vestra_seo_cat_slug($c)) ?>"><?= htmlspecialchars(t($c).' '.$_wholesale) ?> <span class="wsn"><?= (int)$n ?></span></a>
      <?php endforeach; ?>
    </div>

    <?php if (count($_colls) > 1): ?>
    <h2><?= t('Collections') ?></h2>
    <div class="wsother">
      <?php foreach ($_colls as $cs => $lbl): ?>
        <a href="/b2b/<?= $cs ?>"><?= htmlspecialchars(t($lbl).' '.$_wholesale) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php /* Diger pazarlar: her pazar sayfasi otekilere bagli, yani hicbiri
             yetim kalmiyor (arama motoru ic baglanti olmayan bir sayfayi gec bulur). */ ?>
    <h2><?= t('Other markets we ship to') ?></h2>
    <div class="wsother">
      <?php foreach (vestra_seo_markets() as $slug => $om): if ($slug === $mk['slug']) continue; ?>
        <a href="/wholesale-to/<?= $slug ?>"><?= htmlspecialchars(t($om['name']).' '.$_wholesale) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

</div>
</div>
<?php require __DIR__.'/inc/foot.php'; ?>
