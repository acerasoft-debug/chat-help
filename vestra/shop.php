<?php
require __DIR__.'/inc/products.php';
$NAV = 'shop';
/* The catalogue is the page a brand search should land on, so the houses in stock belong
   in its title and description rather than only in the keyword tag. Both fall back to the
   plain wording when nothing is stocked, and the brand list comes from live inventory. */
$_shopBrands = vestra_seo_brands(6);
$PAGE = $_shopBrands
    ? sprintf(t('%s wholesale — Catalog'), implode(', ', array_slice($_shopBrands, 0, 4)))
    : t('Catalog');
$META = t('Browse VESTRA\'s wholesale catalogue — authentic branded & designer fashion for boutiques. KYC-verified sellers, trade pricing on registration, low minimums, invoice-based B2B ordering across Europe.');
if ($_shopBrands) {
    $META = sprintf(t('%s and more, at trade prices for boutiques.'), implode(', ', $_shopBrands)).' '.$META;
}
require __DIR__.'/inc/head.php';
$products = vestra_products();

/* Vitrin bolmesi. Bos ya da tanimsiz gelen ?section= PREMIUM'a duser -- eski bir
   baglanti, bir arama motoru sonucu ya da elle yazilmis bir adres, bos bir izgara
   yerine ana koleksiyonu acsin. */
$SECTIONS = vestra_sections();
$SECTION  = strtolower(trim((string)($_GET['section'] ?? '')));
if (!isset($SECTIONS[$SECTION])) $SECTION = 'premium';
/* Sayimlar SUZMEDEN once, tum katalog uzerinden: sekmede kac urun oldugunu
   gostermek icin, ve bir bolme bosaldiginda sekmeyi gizleyebilmek icin. */
$sectionCounts = [];
foreach ($SECTIONS as $k => $_l) $sectionCounts[$k] = 0;
foreach ($products as $p) $sectionCounts[vestra_product_section($p)]++;
$products = array_values(array_filter($products, fn($p) => vestra_product_section($p) === $SECTION));
/* Where vestra_products() put each item before any reordering. The "newest" sort
   uses catalogue position as its proxy for age, so it has to read the original
   position -- lifting whole houses to the front would otherwise present them as the
   oldest stock on the page. */
foreach ($products as $i => $_) $products[$i]['_ord'] = $i;

/* Default grid order, front to back:
     1. pinned products (operator-curated flagship listings), in the order pinned;
     2. the lead sellers below -- a house is a label, a seller is who actually
        ships, and the operator wants this one's stock forward of the rest;
     3. the lead houses below, in the order listed -- the labels the catalogue opens
        with, so a first-time visitor lands on the strongest stock rather than on
        whatever happens to sit at the top of the file;
     4. everything else.
   Partitions rather than a sort key, so inside each group products keep exactly the
   order vestra_products() returned -- promoting a brand must not reshuffle the
   other 300. */
/* Vitrin sirasi operator karari: katalog Gucci ile aciliyor, arkasinda Givenchy,
   sonra Lacoste. Lacoste listeden cikarilmadi -- yalnizca iki hanenin arkasina
   alindi ("biraz asagiya"), cunku dropship akisi (Stripe, stok, siparis) hala o
   urune bagli ve gorunurlugunu tamamen kaybetmesi satisi dusurur. */
$leadBrands = ['GUCCI', 'GIVENCHY', 'LACOSTE', 'BALMAIN', 'DSQUARED2'];
/* Hem satici ADI hem HESAP KIMLIGI ile esleniyor, ve ikisi de gerekli: adla
   eslemek tek basina yetmedi, cunku ilanlarin cogunda 'seller' alani bos ve
   urun sayfasi orada "via VESTRA" yaziyor -- yalnizca ada bakan bir kural o
   hesabin iki ilanini kaldirip geri kalanini yerinde birakiyordu. Kimlik tek
   basina da yetmez: firma ikinci bir hesap acarsa kimlik degisir, ad kalir.
   Iki yazim birden kabul, cunku ilanlarda ikisi de gecebiliyor. */
$leadSellers    = ['GARAGE LE PARIS', 'LE GARAGE PARIS'];
$leadSellerUids = ['7ab30f26afedd840'];
$pinned = []; $seller = []; $lead = []; $rest = [];
foreach ($products as $p) {
    if (!empty($p['pinned'])) { $pinned[] = $p; continue; }
    if (in_array(strtoupper(trim((string)($p['seller'] ?? ''))), $leadSellers, true)
        || in_array((string)($p['seller_uid'] ?? ''), $leadSellerUids, true)) { $seller[] = $p; continue; }
    $i = array_search(strtoupper(trim((string)($p['brand'] ?? ''))), $leadBrands, true);
    if ($i !== false) { $lead[$i][] = $p; continue; }
    $rest[] = $p;
}
$products = array_merge($pinned, $seller);
foreach (array_keys($leadBrands) as $i) {
    if (!empty($lead[$i])) $products = array_merge($products, $lead[$i]);
}
$products = array_merge($products, $rest);
$catCounts = []; foreach($products as $p){ $c=$p['cat']??'Other'; $catCounts[$c]=($catCounts[$c]??0)+1; }
arsort($catCounts);
/* Per-brand line-sheet downloads (public .xlsx with photos + codes, no pricing). */
$brandCounts = []; foreach($products as $p){ $b=trim((string)($p['brand']??'')); if($b==='') continue; $brandCounts[$b]=($brandCounts[$b]??0)+1; }
arsort($brandCounts);
?>
<style>
/* Großhandelskatalog — "premium white" theme, scoped to /shop only. The dark site
   header stays as top chrome; body + footer are repainted here because this page
   only ever renders the catalog. Product-image tiles keep their dark gradient. */
body{background:#f4f2ee}
.secttabs{display:flex;gap:8px;margin:0 0 18px;flex-wrap:wrap}
.secttab{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border:1px solid #e6e0d5;
  border-radius:999px;background:#fff;color:#6f695e;text-decoration:none;font-size:13.5px;
  font-weight:600;letter-spacing:.01em;transition:border-color .15s,color .15s}
.secttab:hover{border-color:#cbbf9f;color:#211d17}
.secttab.on{background:#211d17;border-color:#211d17;color:#f4f2ee}
.secttab .sectn{font-size:11px;font-weight:600;opacity:.65}
.shopwrap{--bg:#f4f2ee;--bg2:#ffffff;--bg3:#f3efe8;--ink:#211d17;--mut:#6f695e;--acc:#a97f2c;--line:#e6e0d5;--ok:#1f9d63;--bad:#c0392b;color:var(--ink)}
.shopwrap h1,.shopwrap h2,.shopwrap h3{color:var(--ink)}
.shopwrap .filterblock,.shopwrap .scard{box-shadow:0 1px 3px rgba(60,50,30,.05)}
.shopwrap .fcheck:hover,.shopwrap .filter-export:hover{background:rgba(0,0,0,.045)}
.shopwrap .fcheck.on{background:rgba(169,127,44,.08)}
.bseo{display:flex;flex-wrap:wrap;gap:7px;margin:-6px 0 22px}
.bseo a{font-size:12px;color:#6f695e;text-decoration:none;border:1px solid #e6e0d5;
  background:#fff;border-radius:999px;padding:5px 12px}
.bseo a:hover{border-color:#a97f2c;color:#a97f2c}
.shopwrap .fcount{background:rgba(0,0,0,.05)}
.shopwrap .scard:hover{box-shadow:0 12px 30px rgba(60,50,30,.16);border-color:rgba(169,127,44,.4)}
footer{background:#14110c;border-top:0;color:#b8b2a4;margin-top:0}
footer a{color:#d8bd86}

/* ── Editorial masthead ──────────────────────────────────────────────────── */
.sphead{padding:36px 0 20px;border-bottom:1px solid var(--line);margin-bottom:26px}
.sphead-eyebrow{font-size:10.5px;letter-spacing:.22em;text-transform:uppercase;
  color:var(--acc);font-weight:700;margin-bottom:10px}
.sphead h1{font-size:clamp(30px,5vw,54px);margin:0 0 8px;letter-spacing:-.022em}
.sphead p{max-width:52ch;text-wrap:pretty}

/* ── Brand rail — the houses in stock, in their own wordmarks ─────────────
   A dark band under the cream masthead: the wordmarks are drawn white, so they
   need the near-black ground the rest of the site gives them. Scrolls sideways
   on its own rather than wrapping, so the row reads as one continuous strip. */
.brandrail{display:flex;gap:0;overflow-x:auto;overflow-y:hidden;margin:0 0 30px;
  background:linear-gradient(180deg,#141318,#0e0e11);border:1px solid #262229;border-radius:14px;
  scrollbar-width:thin;scrollbar-color:rgba(201,168,106,.45) transparent;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.05),0 10px 30px -18px rgba(38,30,15,.55);
  /* .shopwrap repaints --acc to the deeper gold that reads on cream; this band is
     near-black, so it takes the dark-theme gold back for its own descendants. */
  --acc:#c9a86a}
.brandrail::-webkit-scrollbar{height:6px}
.brandrail::-webkit-scrollbar-thumb{background:rgba(201,168,106,.4);border-radius:3px}
.brail-cell{flex:0 0 auto;width:154px;height:86px;display:flex;align-items:center;justify-content:center;
  position:relative;background:transparent;border:0;border-right:1px solid rgba(255,255,255,.07);
  cursor:pointer;padding:16px 18px;transition:background .35s var(--ease)}
.brail-cell:last-child{border-right:0}
.brail-cell:hover{background:rgba(201,168,106,.09)}
.brail-cell .brand-logo{width:100%;max-width:112px;height:auto;opacity:.68;
  filter:drop-shadow(0 1px 8px rgba(0,0,0,.55));transition:opacity .35s var(--ease),transform .35s var(--ease)}
.brail-cell:hover .brand-logo,.brail-cell.on .brand-logo{opacity:1;transform:translateY(-2px)}
/* Active house is marked by a gold underline that draws in, not by a fill. */
.brail-cell::after{content:'';position:absolute;left:50%;right:50%;bottom:0;height:2px;background:var(--acc);
  opacity:0;transition:left .45s var(--ease),right .45s var(--ease),opacity .45s var(--ease)}
.brail-cell.on::after,.brail-cell:hover::after{left:10%;right:10%;opacity:1}
.brail-n{position:absolute;top:9px;right:11px;font-size:9px;font-weight:700;letter-spacing:.06em;
  color:rgba(255,255,255,.42);font-variant-numeric:tabular-nums}
.brail-all{width:104px}
.brail-allx{font-family:'Playfair Display',Georgia,serif;font-size:19px;color:#fff;opacity:.82;
  letter-spacing:.03em}
.brail-cell.on .brail-allx{opacity:1}
/* The monogram fallback is drawn for the dark site theme; it needs its own sizing here. */
.brail-cell .bmono-mark{font-size:23px}
.brail-cell .bmono-name{font-size:8px;letter-spacing:.22em}
@media(max-width:640px){
  .brail-cell{width:126px;height:74px;padding:12px 14px}
  .brail-cell .brand-logo{max-width:92px}
  .sphead{padding:26px 0 16px}
}

/* ── Mosaic shapes ───────────────────────────────────────────────────────
   Wide tiles get a landscape crop instead of the 3/4 portrait, otherwise a
   double-width card would tower over its neighbours. */
.shopwrap .scard-wide{grid-column:span 2}
.shopwrap .scard-wide .sthumb{aspect-ratio:16/10}
.shopwrap .scard-wide .stitle{font-size:15px}
.shopwrap .scard-wide .samt{font-size:20px}
.shopwrap .scard-tall .sthumb{aspect-ratio:3/4.5}
@media(max-width:900px){
  .shopwrap .scard-wide{grid-column:span 1}
  .shopwrap .scard-wide .sthumb{aspect-ratio:3/4}
}

/* ── Card craft ──────────────────────────────────────────────────────────
   A gold hairline draws across the top of the card and the photo settles a
   touch on hover — the same gesture as the brand rail, so the page has one
   vocabulary rather than three. */
.shopwrap .scard{position:relative}
.shopwrap .scard::before{content:'';position:absolute;top:0;left:50%;right:50%;height:2px;
  background:linear-gradient(90deg,transparent,var(--acc),transparent);z-index:5;opacity:0;
  transition:left .5s var(--ease),right .5s var(--ease),opacity .5s var(--ease)}
.shopwrap .scard:hover::before{left:0;right:0;opacity:1}
.shopwrap .scard:hover .sbrand{letter-spacing:.19em}
.shopwrap .sbrand{transition:letter-spacing .45s var(--ease)}
</style>
<style>
  /* Uc baglanti, basligin hemen altinda: izgarayi bolmeden ama gozden kacmadan. */
  .sphead-links{display:flex;gap:18px;flex-wrap:wrap;margin:14px 0 0;font-size:13.5px}
  .sphead-links a{color:var(--acc);border-bottom:1px solid rgba(201,168,106,.32);padding-bottom:2px;transition:.18s}
  .sphead-links a:hover{color:var(--ink);border-bottom-color:var(--ink)}
</style>
<div class="wrap wide shopwrap">
  <div class="phead sphead">
    <div class="crumbs"><a href="/"><?= t('Home') ?></a> · <?= t('Catalog') ?></div>
    <div class="sphead-eyebrow"><?= t('Wholesale') ?> · <?= count($brandCounts) ?> <?= t('houses') ?> · <?= count($products) ?> <?= t('references') ?></div>
    <h1><?= t('Wholesale catalog') ?></h1>
    <p><?= t('Verified branded & textile fashion — minimum order & bulk pricing per product.') ?></p>
    <?php /* The grid is for browsing one garment at a time; a buyer pricing a whole order
             wants every article, price and MOQ on one screen instead. That view exists at
             /price-list, and without a way in from here nobody finds it -- the address was
             only ever going out by e-mail. */ ?>
    <div class="sphead-links">
      <a href="/price-list"><?= t('Full price list') ?> →</a>
      <a href="/price-lists"><?= t('By brand') ?> →</a>
      <a href="/wholesale-list.xlsx"><?= t('Excel') ?> ↓</a>
    </div>
  </div>

  <?php if($brandCounts): /* Brand rail — the houses in stock, set in their own wordmarks.
       Doubles as navigation: a tap filters the grid to that house. Rendered for guests too
       (the grid is public), so it stays a showpiece rather than a members-only tool. */ ?>
  <div class="brandrail" role="group" aria-label="<?= htmlspecialchars(t('Filter by brand')) ?>">
    <button type="button" class="brail-cell brail-all on" data-brand=""><span class="brail-allx"><?= t('All') ?></span></button>
    <?php foreach($brandCounts as $b=>$cnt): ?>
      <button type="button" class="brail-cell" data-brand="<?= htmlspecialchars($b) ?>" title="<?= htmlspecialchars($b) ?> · <?= $cnt ?>">
        <?= vestra_brand_card($b) ?>
        <span class="brail-n"><?= $cnt ?></span>
      </button>
    <?php endforeach; ?>
  </div>
  <?php /* The rail above filters this page with JavaScript, so its cells are buttons and a
           crawler follows none of them -- which left the per-brand landing pages reachable
           only from the sitemap. These are real links to them: a buyer who wants just one
           house gets a page about that house, and the pages get found. */ ?>
  <nav class="bseo" aria-label="<?= htmlspecialchars(t('Wholesale by house')) ?>">
    <?php foreach(array_keys($brandCounts) as $b): ?>
      <a href="/wholesale/<?= urlencode(vestra_brand_slug($b)) ?>"><?= htmlspecialchars($b) ?>
        <?= htmlspecialchars(vestra_seo_wholesale_word(vlang())) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <?php /* Bolme sekmeleri. Bos bir bolmenin sekmesi BASILMIYOR: henuz ayakkabi
           yuklenmemisken "Footwear" sekmesi acmak, tiklayana bos bir izgara
           gostermek demek -- katalogda bir sey yokmus izlenimi birakir. */ ?>
  <?php if (count(array_filter($sectionCounts)) > 1): ?>
    <nav class="secttabs" aria-label="<?= htmlspecialchars(t('Collection')) ?>">
      <?php foreach($SECTIONS as $sk => $sl): if(!$sectionCounts[$sk]) continue; ?>
        <a class="secttab<?= $sk===$SECTION ? ' on' : '' ?>" href="/shop?section=<?= urlencode($sk) ?>">
          <?= htmlspecialchars($sl) ?><span class="sectn"><?= (int)$sectionCounts[$sk] ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php /* Cevrilmis fiyat gosteriyorsak bunu SOYLE. Sessizce ceviren bir vitrin,
             alicinin kasada baska bir rakam gormesi demek. */ ?>
  <?php if(($__cn = vestra_money_note()) !== ''): ?>
    <p class="curnote">💱 <?= htmlspecialchars($__cn) ?></p>
  <?php endif; ?>
  <?php if($PRICE_GATE==='guest'): ?>
    <div class="banner info" style="margin-bottom:22px">🔒 <?= t('Wholesale prices are visible to <b>verified buyers</b>.') ?>
      &nbsp;<a href="/login?back=/shop" class="acc btn btn-sm btn-o" style="display:inline-flex;margin-left:6px"><?= t('Sign in') ?></a>
      <a href="/register" class="acc btn btn-sm btn-o" style="display:inline-flex;margin-left:6px"><?= t('Register free') ?></a></div>
  <?php elseif($PRICE_GATE==='doc'): ?>
    <?php /* Giris yapmis ama belgesi yok: "uye olun" demek yanlis olurdu, zaten uye.
             Eksik olan tek sey soylenmeli ve yukleme yeri gosterilmeli. */ ?>
    <div class="banner info" style="margin-bottom:22px">🔒 <?= t('Wholesale prices open as soon as you upload your trade licence / business registration.') ?>
      &nbsp;<a href="<?= htmlspecialchars($KYC_URL) ?>" class="acc btn btn-sm btn-p" style="display:inline-flex;margin-left:6px"><?= t('Upload document') ?></a></div>
  <?php endif; ?>
  <div class="shoplayout">

    <!-- ── Sidebar ───────────────────────────────────────────────────────── -->
    <aside class="shopside">
      <?php if(!$MEMBER): ?>
      <!-- Kayit paneli artik kenar cubugunun YERINE gecmiyor, BASINA ekleniyor.
           Onceden misafire arama, kategori listesi ve indirmelerin tamami kapaliydi;
           urunler goruntulense de katalog gezilemiyordu. Operator kurali net:
           urunler herkese acik, YALNIZCA fiyat gizli. Indirmeler ayri bir konu ve
           kapali kaliyor -- o dosya bir kayit magneti, urunun gorunurlugu degil. -->
      <div class="filterblock lockside">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="1.5"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
        <div class="filter-title" style="border:0;padding-left:0"><?= t('Trade access') ?></div>
        <p class="hint" style="margin:0 0 14px;font-size:12px;line-height:1.6">
          <?= t('Trade pricing and line-sheet downloads open up once you register. Free, and takes a minute.') ?>
        </p>
        <a class="btn btn-p btn-sm" style="width:100%;justify-content:center;margin-bottom:8px" href="/register"><?= t('Register free') ?></a>
        <a class="btn btn-o btn-sm" style="width:100%;justify-content:center" href="/login?back=/shop"><?= t('Sign in') ?></a>
      </div>
      <?php endif; ?>
      <div class="filterblock">
        <div class="filter-title"><?= t('Search') ?></div>
        <div class="filter-searchbox">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
          <input id="fsearch" placeholder="<?= htmlspecialchars(t('Brand, product, SKU…')) ?>" oninput="applyFilters()">
        </div>
      </div>

      <div class="filterblock">
        <div class="filter-title"><?= t('Category') ?></div>
        <label class="fcheck on" data-type="cat" data-val="">
          <span class="fcheck-dot"></span><?= t('All categories') ?><span class="fcount"><?= count($products) ?></span>
        </label>
        <?php foreach($catCounts as $cat=>$cnt): ?>
          <label class="fcheck" data-type="cat" data-val="<?= htmlspecialchars($cat) ?>">
            <span class="fcheck-dot"></span><?= htmlspecialchars($cat) ?><span class="fcount"><?= $cnt ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="filterblock">
        <div class="filter-title"><?= t('Pricing mode') ?></div>
        <?php foreach([
          ''       => t('All types'),
          'fixed'  => t('Fixed price'),
          'sale'   => t('Sale / Clearance'),
          'offer'  => t('Make an offer'),
        ] as $mv => $ml): ?>
          <label class="fcheck <?= $mv===''?'on':'' ?>" data-type="mode" data-val="<?= $mv ?>">
            <span class="fcheck-dot"></span><?= $ml ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="filterblock">
        <div class="filter-title"><?= t('Exports') ?></div>
        <a class="filter-export" href="/catalog-csv">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 21h16"/></svg>
          <?= t('Download CSV') ?>
        </a>
        <a class="filter-export" href="/catalog-pdf" target="_blank">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/><path d="M9.5 13h5M9.5 16h5"/></svg>
          PDF <?= t('catalog') ?>
        </a>
      </div>

      <?php /* Indirmeler UYE'ye ozel kaliyor. Bu dosya urunun gorunurlugu degil, bir
               kayit magneti: fotograflı ve kodlu tam Excel'i kayitsiz vermek, katalogu
               tek tiklamada disari kopyalanabilir yapardi. Arama ve kategoriler ise
               artik herkese acik -- kural "urunler acik, fiyat gizli". */ ?>
      <?php if($MEMBER): ?>
      <div class="filterblock">
        <div class="filter-title"><?= t('Line-sheets by brand') ?></div>
        <a class="filter-export" href="/catalog">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></svg>
          <?= t('All brands') ?> · Excel
        </a>
        <?php foreach($brandCounts as $b=>$cnt): ?>
          <a class="filter-export" href="/catalog?brand=<?= rawurlencode($b) ?>" title="<?= htmlspecialchars(sprintf(t('%s line-sheet (Excel, with photos)'), $b)) ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></svg>
            <?= htmlspecialchars($b) ?><span class="fcount"><?= $cnt ?></span>
          </a>
        <?php endforeach; ?>
        <p class="hint" style="margin:8px 6px 0;font-size:11.5px;line-height:1.5"><?= t('Excel with product photos &amp; identification codes · no pricing (trade prices unlock after free registration).') ?></p>
      </div>
      <?php endif; ?>
    </aside>

    <!-- ── Main content ──────────────────────────────────────────────────── -->
    <div class="shopmain">
      <div class="shopbar">
        <span class="shopcount" id="shopcount"><?= count($products) ?> <?= t('products') ?></span>
        <span class="grow"></span>
        <?php /* Siralama artik misafire de acik: bir katalog aracidir, fiyat degil.
                 Fiyata gore siralama tek istisna -- fiyati gormeyene fiyata gore
                 siralama sunmak, gizledigimiz bilgiyi siralamayla geri vermek olur. */ ?>
        <?php if(true): ?>
        <select class="sortsel" id="sortsel" onchange="applyFilters()">
          <option value="def"><?= t('Default order') ?></option>
          <?php if($PRICES): ?>
          <option value="price_asc"><?= t('Price: low → high') ?></option>
          <option value="price_desc"><?= t('Price: high → low') ?></option>
          <?php endif; ?>
          <option value="newest"><?= t('Newest first') ?></option>
          <option value="name"><?= t('Name A–Z') ?></option>
        </select>
        <?php endif; ?>
      </div>

      <div class="shopgrid" id="shopgrid">
        <?php foreach($products as $idx=>$p):
          $from = vestra_from_price($p);
          $dmode = vestra_display_mode($p);   // gercek indirimi olmayan "sale" burada fixed sayilir
          /* Signed-in members (any status) see the catalogue photo-forward, like the
             showroom: the first product photo is the card front and the SECOND crossfades
             in on hover (or while the card is centered in view on touch). Guests get NO
             photo — only the brand tile stays as the gate. */
          /* Galeri artik herkese acik: fotograf urunun kendisi, fiyat degil. */
          $imgs = (!empty($p['images']) && is_array($p['images'])) ? array_values(array_filter($p['images'])) : [];
          $img0 = $imgs[0] ?? '';   // base photo (members only)
          $img1 = $imgs[1] ?? '';   // second photo → hover reveal
          $imgCount = count($p['images'] ?? (vestra_primary_image($p) ? [vestra_primary_image($p)] : []));
          $isNew = !empty($p['added_at']) && (strtotime($p['added_at']) > strtotime('-30 days'));
          /* Editorial rhythm: every 7th tile runs wide (landscape crop), every 11th runs
             tall. A uniform 4-up grid of 343 identical portrait tiles reads as a
             spreadsheet; breaking it on a fixed cadence reads as a lookbook. Pinned
             products already take a 2x2 lead tile, so they opt out of the cadence.
             The grid packs dense, so filtering never leaves a hole behind a big tile. */
          $shape = '';
          if (empty($p['pinned'])) {
            if     ($idx % 7  === 3) $shape = ' scard-wide';
            elseif ($idx % 11 === 6) $shape = ' scard-tall';
          }
          ?>
          <a class="scard<?= !empty($p['pinned']) ? ' scard-featured' : $shape ?>" href="/product?id=<?= urlencode($p['id']) ?>"
             data-idx="<?= $idx ?>"
             data-ord="<?= (int)($p['_ord'] ?? $idx) ?>"
             data-cat="<?= htmlspecialchars($p['cat']??'') ?>"
             data-brand="<?= htmlspecialchars($p['brand']??'') ?>"
             data-mode="<?= htmlspecialchars($dmode) ?>"
             data-price="<?= !$PRICES ? '' : ($dmode==='offer' ? 999999 : $from) ?>"
             data-search="<?= htmlspecialchars(strtolower(($p['brand']??'').' '.($p['name']??'').' '.($p['sku']??'').' '.($p['cat']??''))) ?>"
             data-name="<?= htmlspecialchars($p['name']??'') ?>">
            <div class="sthumb" style="background:linear-gradient(135deg,<?= htmlspecialchars(vestra_accent($p)) ?>,#0e0e11)">
              <?php /* The first photo is the one image search has to work with, so it names the
                        product; the second is the same garment on hover and stays decorative. */
                     $_alt = trim(($p['brand'] ?? '').' '.($p['name'] ?? '')); ?>
              <?php if($img0): ?><img src="<?= htmlspecialchars($img0) ?>" alt="<?= htmlspecialchars($_alt) ?>" loading="lazy" class="sthumbi"><?php endif; ?>
              <?php if($img1): ?><img src="<?= htmlspecialchars($img1) ?>" alt="" loading="lazy" class="sthumbi sthumbi-reveal"><?php endif; ?>
              <?php if(!empty($p['verified'])): ?>
                <span class="svbadge">
                  <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                  <?= t('Verified seller') ?>
                </span>
              <?php endif; ?>
              <?php if(!$img0) echo vestra_brand_card($p['brand']); ?>
              <?php if($dmode==='sale'): ?><span class="smodetag sale">−<?= vestra_discount($p) ?>%</span>
              <?php elseif($dmode==='offer'): ?><span class="smodetag offer"><?= t('Offers') ?></span><?php endif; ?>
              <?php if($isNew): ?><span class="snewbadge"><?= t('NEW') ?></span><?php endif; ?>
              <?php if($imgCount > 1): ?><span class="sphotocount">🖼 <?= $imgCount ?></span><?php endif; ?>
            </div>
            <div class="sbody">
              <span class="sbrand"><?= htmlspecialchars($p['brand']??'') ?></span>
              <span class="stitle"><?= htmlspecialchars($p['name']??'') ?></span>
              <span class="smeta"><?= htmlspecialchars($p['cat']??'') ?> &middot; SKU <?= htmlspecialchars($p['sku']??'') ?></span>
              <span class="smeta">MOQ <b><?= $p['moq']??'?' ?></b> <?= htmlspecialchars($p['unit']??'pc') ?></span>
              <?php if(!empty($p['colors'])): ?><span class="smeta" style="margin-top:2px"><?= vestra_color_dots((array)$p['colors'], 7) ?></span><?php endif; ?>
              <div class="sprice">
                <?php if(!$PRICES): ?>
                  <span class="slock"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><?= $PRICE_GATE==='doc' ? t('Trade licence required') : t('Members only') ?></span>
                <?php elseif($dmode==='offer'): ?>
                  <span class="soffer">💬 <?= t('Open to offers') ?></span>
                <?php elseif($dmode==='sale'): ?>
                  <span class="swas"><?= vestra_money($p['list']??0) ?></span>
                  <span class="samt"><?= vestra_money($from) ?></span>
                  <span class="sfrom">/<?= htmlspecialchars($p['unit']??'pc') ?></span>
                <?php else: ?>
                  <span class="sfrom"><?= t('from') ?></span>
                  <span class="samt"><?= vestra_money($from) ?></span>
                  <span class="sfrom">/<?= htmlspecialchars($p['unit']??'pc') ?></span>
                <?php endif; ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="empty" id="noresult" style="display:none"><?= t('No products match your filters.') ?></div>
    </div>
  </div>
</div>

<script>
var curCat='', curMode='', curBrand='';

document.querySelectorAll('.fcheck').forEach(function(el){
  el.addEventListener('click', function(){
    var type=el.dataset.type, val=el.dataset.val;
    document.querySelectorAll('.fcheck[data-type="'+type+'"]').forEach(function(x){ x.classList.remove('on'); });
    el.classList.add('on');
    if(type==='cat') curCat=val;
    else curMode=val;
    applyFilters();
  });
});

/* Brand rail. Its own filter rather than a shortcut into the search box, so it works
   on the guest view too -- guests get no search input but the grid is still rendered. */
document.querySelectorAll('.brail-cell').forEach(function(el){
  el.addEventListener('click', function(){
    var b=el.dataset.brand||'';
    curBrand=(curBrand===b&&b!=='')?'':b;   // tapping the active house clears it
    document.querySelectorAll('.brail-cell').forEach(function(x){ x.classList.remove('on'); });
    var active=curBrand===''
      ? document.querySelector('.brail-cell.brail-all')
      : document.querySelector('.brail-cell[data-brand="'+curBrand.replace(/"/g,'\\"')+'"]');
    if(active) active.classList.add('on');
    applyFilters();
  });
});

function applyFilters(){
  /* The search box and the sort select only exist for registered visitors -- guests get
     the registration panel instead of the filter sidebar. Read them defensively so this
     runs (and the product count stays correct) on the guest view too, instead of throwing
     on a null and leaving the rest of the page's scripts dead. */
  var qEl=document.getElementById('fsearch'), sortEl=document.getElementById('sortsel');
  var q=((qEl&&qEl.value)||'').toLowerCase().trim();
  var sort=(sortEl&&sortEl.value)||'def';
  var cards=Array.from(document.querySelectorAll('#shopgrid .scard'));
  var visible=[];
  cards.forEach(function(c){
    var show=(curCat===''||c.dataset.cat===curCat)&&(curMode===''||c.dataset.mode===curMode)&&(curBrand===''||c.dataset.brand===curBrand)&&(!q||c.dataset.search.indexOf(q)>=0);
    c.style.display=show?'flex':'none';
    if(show) visible.push(c);
  });
  if(sort!=='def'){
    visible.sort(function(a,b){
      if(sort==='price_asc')  return parseFloat(a.dataset.price)-parseFloat(b.dataset.price);
      if(sort==='price_desc') return parseFloat(b.dataset.price)-parseFloat(a.dataset.price);
      /* data-ord, not data-idx: idx is the position on the page, which the pinned and
         lead-brand promotion has already rearranged. ord is where the catalogue itself
         put the product, which is what "newest" is a proxy for. */
      if(sort==='newest')     return parseInt(b.dataset.ord)-parseInt(a.dataset.ord);
      if(sort==='name')       return a.dataset.name.localeCompare(b.dataset.name);
      return 0;
    });
    var grid=document.getElementById('shopgrid');
    visible.forEach(function(c){ grid.appendChild(c); });
  }
  var cnt=visible.length;
  document.getElementById('shopcount').textContent=cnt+' '+(cnt===1?'<?= addslashes(t('product')) ?>':'<?= addslashes(t('products')) ?>');
  document.getElementById('noresult').style.display=cnt?'none':'block';
}
applyFilters();
/* Touch devices have no hover: reveal the product photo while the card is
   centered in the viewport instead (same crossfade as the desktop hover). */
if (window.matchMedia && window.matchMedia('(hover: none)').matches && 'IntersectionObserver' in window) {
  var revIO = new IntersectionObserver(function(entries){
    entries.forEach(function(en){ en.target.classList.toggle('sreveal', en.intersectionRatio >= .55); });
  }, {threshold:[.3,.55]});
  document.querySelectorAll('#shopgrid .scard').forEach(function(c){
    if (c.querySelector('.sthumbi-reveal')) revIO.observe(c);
  });
}
</script>
<?php require __DIR__.'/inc/foot.php';
