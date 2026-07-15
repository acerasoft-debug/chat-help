<?php require __DIR__.'/inc/products.php'; $PAGE=t('Catalog'); $NAV='shop'; require __DIR__.'/inc/head.php';
$products = vestra_products();
$catCounts = []; foreach($products as $p){ $c=$p['cat']??'Other'; $catCounts[$c]=($catCounts[$c]??0)+1; }
arsort($catCounts);
?>
<div class="wrap wide">
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
    </aside>

    <!-- ── Main content ──────────────────────────────────────────────────── -->
    <div class="shopmain">
      <div class="shopbar">
        <span class="shopcount" id="shopcount"><?= count($products) ?> <?= t('products') ?></span>
        <span class="grow"></span>
        <select class="sortsel" id="sortsel" onchange="applyFilters()">
          <option value="def"><?= t('Default order') ?></option>
          <option value="price_asc"><?= t('Price: low → high') ?></option>
          <option value="price_desc"><?= t('Price: high → low') ?></option>
          <option value="newest"><?= t('Newest first') ?></option>
          <option value="name"><?= t('Name A–Z') ?></option>
        </select>
      </div>

      <div class="shopgrid" id="shopgrid">
        <?php foreach($products as $idx=>$p):
          $from = vestra_from_price($p);
          $img  = '';   // grid always shows the brand card — photos live in the product gallery (members)
          $imgCount = $MEMBER ? count($p['images'] ?? (vestra_primary_image($p) ? [vestra_primary_image($p)] : [])) : 0;
          $isNew = !empty($p['added_at']) && (strtotime($p['added_at']) > strtotime('-30 days'));
          ?>
          <a class="scard" href="/product?id=<?= urlencode($p['id']) ?>"
             data-idx="<?= $idx ?>"
             data-cat="<?= htmlspecialchars($p['cat']??'') ?>"
             data-mode="<?= htmlspecialchars($p['mode']??'fixed') ?>"
             data-price="<?= !$MEMBER ? '' : ($p['mode']==='offer' ? 999999 : $from) ?>"
             data-search="<?= htmlspecialchars(strtolower(($p['brand']??'').' '.($p['name']??'').' '.($p['sku']??'').' '.($p['cat']??''))) ?>"
             data-name="<?= htmlspecialchars($p['name']??'') ?>">
            <div class="sthumb<?= $img?' has-img':'' ?>" style="background:linear-gradient(135deg,<?= $p['accent'] ?>,#0e0e11)">
              <?php if($img): ?><img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy" class="sthumbi"><?php endif; ?>
              <?php if(!empty($p['verified'])): ?>
                <span class="svbadge">
                  <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                  <?= t('Verified seller') ?>
                </span>
              <?php endif; ?>
              <?php $blogo = vestra_brand_logo($p['brand']); echo $blogo ?: '<span class="sbname">'.htmlspecialchars($p['brand']).'</span>'; ?>
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
  var q=(document.getElementById('fsearch').value||'').toLowerCase().trim();
  var sort=document.getElementById('sortsel').value;
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
</script>
<?php require __DIR__.'/inc/foot.php';
