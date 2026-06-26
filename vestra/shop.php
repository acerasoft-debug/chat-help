<?php require __DIR__.'/inc/products.php'; $PAGE='Catalog'; $NAV='shop'; require __DIR__.'/inc/head.php';
$cats = vestra_cats();
?>
<div class="wrap">
  <div class="phead">
    <div class="crumbs"><a href="/"><?= t('Home') ?></a> · <?= t('Catalog') ?></div>
    <h1><?= t('Wholesale catalog') ?></h1>
    <p><?= t('Verified branded & textile fashion — minimum order & bulk pricing per product.') ?></p>
  </div>

  <?php if(!$MEMBER): ?>
    <div class="banner info">🔒 <?= t('Wholesale prices are visible to <b>verified buyers</b>.') ?>
      <a href="?demo_member=1" class="acc"><?= t('Sign in (demo)') ?></a> <?= t('to view pricing and order.') ?></div>
  <?php endif; ?>

  <div class="toolbar">
    <input class="search" id="search" placeholder="<?= htmlspecialchars(t('Search products, brands, SKU…')) ?>" oninput="filter()">
    <span class="grow"></span>
    <a class="btn btn-o btn-sm" href="/catalog-csv">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 21h16"/></svg>
      <?= t('Excel (CSV)') ?>
    </a>
    <a class="btn btn-o btn-sm" href="/catalog-pdf" target="_blank">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/><path d="M9.5 13h5M9.5 16h5"/></svg>
      PDF
    </a>
  </div>

  <div class="toolbar" style="margin-top:-12px">
    <button class="chip" data-cat="" onclick="setCat(this,'')"><?= t('All') ?></button>
    <?php foreach($cats as $c): ?>
      <button class="chip" data-cat="<?=htmlspecialchars($c)?>" onclick="setCat(this,'<?=htmlspecialchars($c)?>')"><?=htmlspecialchars($c)?></button>
    <?php endforeach; ?>
  </div>

  <div class="grid" id="grid">
    <?php foreach(vestra_products() as $p):
      $from = vestra_from_price($p); ?>
      <a class="pcard" href="/product?id=<?=urlencode($p['id'])?>"
         data-cat="<?=htmlspecialchars($p['cat'])?>"
         data-search="<?=htmlspecialchars(strtolower($p['brand'].' '.$p['name'].' '.$p['sku'].' '.$p['cat']))?>">
        <div class="thumb" style="background:linear-gradient(135deg,<?=$p['accent']?>,#0e0e11)">
          <?php if(!empty($p['image'])): ?><img class="thumbimg" src="<?=htmlspecialchars($p['image'])?>" alt="" loading="lazy"><?php endif; ?>
          <?php if(!empty($p['verified'])): ?>
          <span class="vbadge">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            <?= t('Verified') ?></span>
          <?php endif; ?>
          <?php $blogo=vestra_brand_logo($p['brand']); if($blogo){ echo $blogo; }else{ echo '<span class="bname">'.htmlspecialchars($p['brand']).'</span>'; } ?>
          <?php if($p['mode']==='sale'): ?><span class="modetag sale">−<?=vestra_discount($p)?>%</span>
          <?php elseif($p['mode']==='offer'): ?><span class="modetag offer"><?= t('Offers') ?></span><?php endif; ?>
        </div>
        <div class="body">
          <span class="brand"><?=htmlspecialchars($p['brand'])?></span>
          <span class="title"><?=htmlspecialchars($p['name'])?></span>
          <span class="meta"><?=htmlspecialchars($p['cat'])?> · SKU <?=htmlspecialchars($p['sku'])?> · MOQ <?=$p['moq']?> <?=htmlspecialchars($p['unit'])?></span>
          <div class="price">
            <?php if(!$MEMBER): ?>
              <span class="lock">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                <?= t('Members only') ?></span>
            <?php elseif($p['mode']==='offer'): ?>
              <span class="offerprice">💬 <?= t('Open to offers') ?></span>
            <?php elseif($p['mode']==='sale'): ?>
              <span class="was"><?=eur($p['list'])?></span> <span class="amt"><?=eur($from)?></span> <span class="from">/ <?=htmlspecialchars($p['unit'])?></span>
            <?php else: ?>
              <span class="from"><?= t('from') ?></span> <span class="amt"><?=eur($from)?></span> <span class="from">/ <?=htmlspecialchars($p['unit'])?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="empty" id="noresult" style="display:none"><?= t('No products match your search.') ?></div>
  <div class="empty" id="startprompt" style="display:none">👆 <?= t('Pick a <b>category</b> above — or type in <b>search</b> — to view products.') ?></div>
</div>

<script>
var curCat=null; // null = nothing chosen yet -> products stay hidden
function setCat(el,c){ curCat=c; document.querySelectorAll('.chip').forEach(function(x){x.classList.remove('on')}); el.classList.add('on'); filter(); }
function filter(){
  var q=(document.getElementById('search').value||'').toLowerCase().trim();
  var active = (curCat!==null) || q!=='';
  document.getElementById('grid').style.display = active?'':'none';
  document.getElementById('startprompt').style.display = active?'none':'block';
  if(!active){ document.getElementById('noresult').style.display='none'; return; }
  var shown=0;
  document.querySelectorAll('#grid .pcard').forEach(function(card){
    var okCat = (curCat===null||curCat==='') || card.dataset.cat===curCat;
    var okQ = !q || card.dataset.search.indexOf(q)>=0;
    var vis = okCat && okQ; card.style.display = vis?'flex':'none'; if(vis) shown++;
  });
  document.getElementById('noresult').style.display = shown?'none':'block';
}
filter(); // on load: nothing selected -> hide grid, show the prompt
</script>
<?php require __DIR__.'/inc/foot.php';
