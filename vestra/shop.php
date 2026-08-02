<?php require __DIR__.'/inc/products.php'; $PAGE=t('Catalog'); $NAV='shop'; $META=t('Browse VESTRA\'s wholesale catalogue — authentic branded & designer fashion for boutiques. KYC-verified sellers, trade pricing on registration, low minimums, invoice-based B2B ordering across Europe.'); require __DIR__.'/inc/head.php';
$products = vestra_products();
/* Pinned products (operator-curated, e.g. this week's flagship listing) lead the
   default grid ahead of everything else, in the order they were pinned. A manual
   partition rather than a sort key keeps every other product's relative order
   exactly as vestra_products() returned it -- pinning one item must not reshuffle
   the other 299. */
$pinned = []; $rest = [];
foreach ($products as $p) { if (!empty($p['pinned'])) $pinned[] = $p; else $rest[] = $p; }
$products = array_merge($pinned, $rest);
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
.shopwrap{--bg:#f4f2ee;--bg2:#ffffff;--bg3:#f3efe8;--ink:#211d17;--mut:#6f695e;--acc:#a97f2c;--line:#e6e0d5;--ok:#1f9d63;--bad:#c0392b;color:var(--ink)}
.shopwrap h1,.shopwrap h2,.shopwrap h3{color:var(--ink)}
.shopwrap .filterblock,.shopwrap .scard{box-shadow:0 1px 3px rgba(60,50,30,.05)}
.shopwrap .fcheck:hover,.shopwrap .filter-export:hover{background:rgba(0,0,0,.045)}
.shopwrap .fcheck.on{background:rgba(169,127,44,.08)}
.shopwrap .fcount{background:rgba(0,0,0,.05)}
.shopwrap .scard:hover{box-shadow:0 12px 30px rgba(60,50,30,.16);border-color:rgba(169,127,44,.4)}
footer{background:#14110c;border-top:0;color:#b8b2a4;margin-top:0}
footer a{color:#d8bd86}
</style>
<div class="wrap wide shopwrap">
  <div class="phead">
    <div class="crumbs"><a href="/"><?= t('Home') ?></a> · <?= t('Catalog') ?></div>
    <h1><?= t('Wholesale catalog') ?></h1>
    <p><?= t('Verified branded & textile fashion — minimum order & bulk pricing per product.') ?></p>
  </div>
  <?php if(!$MEMBER): ?>
    <div class="banner info" style="margin-bottom:22px">🔒 <?= t('Wholesale prices are visible to <b>verified buyers</b>.') ?>
      &nbsp;<a href="/login?back=/shop" class="acc btn btn-sm btn-o" style="display:inline-flex;margin-left:6px"><?= t('Sign in') ?></a>
      <a href="/register" class="acc btn btn-sm btn-o" style="display:inline-flex;margin-left:6px"><?= t('Register free') ?></a></div>
  <?php endif; ?>
  <div class="shoplayout">

    <!-- ── Sidebar ───────────────────────────────────────────────────────── -->
    <aside class="shopside">
      <?php if(!$MEMBER): ?>
      <!-- Unregistered visitors get no search, no category list and no downloads: the
           whole sidebar collapses to a registration panel. The product grid below still
           renders (so the page keeps its content for crawlers and shows what is on
           offer), but nothing here lets a guest slice the catalogue or take a file. -->
      <div class="filterblock lockside">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="1.5"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
        <div class="filter-title" style="border:0;padding-left:0"><?= t('Trade access') ?></div>
        <p class="hint" style="margin:0 0 14px;font-size:12px;line-height:1.6">
          <?= t('Search, categories, line-sheets and trade pricing open up once you register. Free, and takes a minute.') ?>
        </p>
        <a class="btn btn-p btn-sm" style="width:100%;justify-content:center;margin-bottom:8px" href="/register"><?= t('Register free') ?></a>
        <a class="btn btn-o btn-sm" style="width:100%;justify-content:center" href="/login?back=/shop"><?= t('Sign in') ?></a>
      </div>
      <?php else: ?>
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
        <?php if($MEMBER): /* sorting is a catalogue tool like search — guests get neither */ ?>
        <select class="sortsel" id="sortsel" onchange="applyFilters()">
          <option value="def"><?= t('Default order') ?></option>
          <option value="price_asc"><?= t('Price: low → high') ?></option>
          <option value="price_desc"><?= t('Price: high → low') ?></option>
          <option value="newest"><?= t('Newest first') ?></option>
          <option value="name"><?= t('Name A–Z') ?></option>
        </select>
        <?php endif; ?>
      </div>

      <div class="shopgrid" id="shopgrid">
        <?php foreach($products as $idx=>$p):
          $from = vestra_from_price($p);
          /* Signed-in members (any status) see the catalogue photo-forward, like the
             showroom: the first product photo is the card front and the SECOND crossfades
             in on hover (or while the card is centered in view on touch). Guests get NO
             photo — only the brand tile stays as the gate. */
          $imgs = ($MEMBER && !empty($p['images']) && is_array($p['images'])) ? array_values(array_filter($p['images'])) : [];
          $img0 = $imgs[0] ?? '';   // base photo (members only)
          $img1 = $imgs[1] ?? '';   // second photo → hover reveal
          $imgCount = $MEMBER ? count($p['images'] ?? (vestra_primary_image($p) ? [vestra_primary_image($p)] : [])) : 0;
          $isNew = !empty($p['added_at']) && (strtotime($p['added_at']) > strtotime('-30 days'));
          ?>
          <a class="scard<?= !empty($p['pinned']) ? ' scard-featured' : '' ?>" href="/product?id=<?= urlencode($p['id']) ?>"
             data-idx="<?= $idx ?>"
             data-cat="<?= htmlspecialchars($p['cat']??'') ?>"
             data-mode="<?= htmlspecialchars($p['mode']??'fixed') ?>"
             data-price="<?= !$MEMBER ? '' : ($p['mode']==='offer' ? 999999 : $from) ?>"
             data-search="<?= htmlspecialchars(strtolower(($p['brand']??'').' '.($p['name']??'').' '.($p['sku']??'').' '.($p['cat']??''))) ?>"
             data-name="<?= htmlspecialchars($p['name']??'') ?>">
            <div class="sthumb" style="background:linear-gradient(135deg,<?= htmlspecialchars(vestra_accent($p)) ?>,#0e0e11)">
              <?php if($img0): ?><img src="<?= htmlspecialchars($img0) ?>" alt="" loading="lazy" class="sthumbi"><?php endif; ?>
              <?php if($img1): ?><img src="<?= htmlspecialchars($img1) ?>" alt="" loading="lazy" class="sthumbi sthumbi-reveal"><?php endif; ?>
              <?php if(!empty($p['verified'])): ?>
                <span class="svbadge">
                  <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                  <?= t('Verified seller') ?>
                </span>
              <?php endif; ?>
              <?php if(!$img0) echo vestra_brand_card($p['brand']); ?>
              <?php if($p['mode']==='sale'): ?><span class="smodetag sale">−<?= vestra_discount($p) ?>%</span>
              <?php elseif($p['mode']==='offer'): ?><span class="smodetag offer"><?= t('Offers') ?></span><?php endif; ?>
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
                <?php if(!$MEMBER): ?>
                  <span class="slock"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><?= t('Members only') ?></span>
                <?php elseif($p['mode']==='offer'): ?>
                  <span class="soffer">💬 <?= t('Open to offers') ?></span>
                <?php elseif($p['mode']==='sale'): ?>
                  <span class="swas"><?= eur($p['list']??0) ?></span>
                  <span class="samt"><?= eur($from) ?></span>
                  <span class="sfrom">/<?= htmlspecialchars($p['unit']??'pc') ?></span>
                <?php else: ?>
                  <span class="sfrom"><?= t('from') ?></span>
                  <span class="samt"><?= eur($from) ?></span>
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
var curCat='', curMode='';

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
    var show=(curCat===''||c.dataset.cat===curCat)&&(curMode===''||c.dataset.mode===curMode)&&(!q||c.dataset.search.indexOf(q)>=0);
    c.style.display=show?'flex':'none';
    if(show) visible.push(c);
  });
  if(sort!=='def'){
    visible.sort(function(a,b){
      if(sort==='price_asc')  return parseFloat(a.dataset.price)-parseFloat(b.dataset.price);
      if(sort==='price_desc') return parseFloat(b.dataset.price)-parseFloat(a.dataset.price);
      if(sort==='newest')     return parseInt(b.dataset.idx)-parseInt(a.dataset.idx);
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
