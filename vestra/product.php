<?php
require __DIR__.'/inc/products.php';
$p = vestra_find($_GET['id'] ?? '');
if(!$p){ http_response_code(404); $PAGE=t('Not found'); require __DIR__.'/inc/head.php';
  echo '<div class="wrap"><div class="empty">'.t('Product not found.').' <a class="acc" href="/shop">'.t('Back to catalog').'</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit; }

$PAGE=$p['name']; $NAV='shop'; require __DIR__.'/inc/head.php';
$mode=$p['mode']; $from=vestra_from_price($p); $disc=vestra_discount($p);
$offered=isset($_GET['offered']);
$images = !empty($p['images'])&&is_array($p['images']) ? $p['images'] : (vestra_primary_image($p)?[vestra_primary_image($p)]:[]);
$photosLocked = !$MEMBER && $images;   // photos are members-only; guests get the brand card
if(!$MEMBER) $images = [];
?>
<div class="wrap">
  <div class="crumbs" style="margin-top:24px">
    <a href="/"><?= t('Home') ?></a> · <a href="/shop"><?= t('Catalog') ?></a> · <?= htmlspecialchars($p['brand']) ?>
  </div>

  <div class="pdetail">
    <!-- ── Gallery ────────────────────────────────────────────────────────── -->
    <div class="gal-col">
      <div class="gal-main" id="gal-wrap">
        <!-- Slide 0 is ALWAYS the brand card; member photos come after it. -->
        <div class="gal-placeholder" id="gal-card" style="background:linear-gradient(135deg,<?= $p['accent'] ?>,#0e0e11);flex-direction:column;gap:14px">
          <?php $blogo=vestra_brand_logo($p['brand']); echo $blogo ?: '<span class="bname" style="font-size:38px;font-family:\'Playfair Display\',serif;font-weight:700;opacity:.9">'.htmlspecialchars($p['brand']).'</span>'; ?>
          <?php if($photosLocked): ?>
            <a href="/login?back=<?= urlencode('/product?id='.$p['id']) ?>" style="display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:#fff;background:rgba(14,14,17,.55);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.22);padding:7px 14px;border-radius:999px;position:relative;z-index:3">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
              <?= t('Sign in to view product photos') ?>
            </a>
          <?php endif; ?>
        </div>
        <?php if($images): ?>
          <img class="gal-img" id="gal-main-img" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="display:none">
          <button class="gal-nav prev" onclick="galGo(-1)" aria-label="Previous">‹</button>
          <button class="gal-nav next" onclick="galGo(1)" aria-label="Next">›</button>
        <?php endif; ?>
        <?php if($mode==='sale'): ?><span class="modetag sale">SALE −<?= $disc ?>%</span>
        <?php elseif($mode==='offer'): ?><span class="modetag offer"><?= t('Open to offers') ?></span><?php endif; ?>
        <?php if(!empty($p['verified'])): ?><span class="gal-vbadge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> <?= t('Verified seller') ?></span><?php endif; ?>
      </div>
      <?php if($images): ?>
      <div class="gal-thumbs" id="gal-thumbs">
        <button class="gal-thumb active" onclick="galSet(-1)" title="<?= htmlspecialchars($p['brand']) ?>">
          <span style="display:block;width:100%;height:100%;background:linear-gradient(135deg,<?= $p['accent'] ?>,#0e0e11)"></span>
        </button>
        <?php foreach($images as $i=>$img): ?>
          <button class="gal-thumb" onclick="galSet(<?= $i ?>)">
            <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy">
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Product info ───────────────────────────────────────────────────── -->
    <div class="pinfo">
      <span class="acc" style="font-size:12px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700"><?= htmlspecialchars($p['brand']) ?></span>
      <h1 style="margin:6px 0 10px"><?= htmlspecialchars($p['name']) ?></h1>
      <?php if(!empty($p['desc'])): ?>
        <p style="color:var(--mut);margin:0 0 18px;line-height:1.65"><?= htmlspecialchars($p['desc']) ?></p>
      <?php endif; ?>

      <div class="spec-grid">
        <div class="spec-row"><span><?= t('SKU') ?></span><b><?= htmlspecialchars($p['sku']) ?></b></div>
        <div class="spec-row"><span><?= t('Category') ?></span><b><?= htmlspecialchars($p['cat']) ?></b></div>
        <div class="spec-row"><span><?= t('Min. order (MOQ)') ?></span><b><?= $p['moq'] ?> <?= htmlspecialchars($p['unit']) ?></b></div>
        <?php if(!empty($p['sizes'])): ?><div class="spec-row"><span><?= t('Size mix') ?></span><b><?= htmlspecialchars($p['sizes']) ?></b></div><?php endif; ?>
        <?php if(!empty($p['colors'])): ?><div class="spec-row"><span><?= t('Colours') ?></span><b style="display:flex;justify-content:flex-end"><?= vestra_color_dots((array)$p['colors'], 13) ?></b></div><?php endif; ?>
        <?php if(!empty($p['seller']) && empty($p['hide_seller'])): ?><div class="spec-row"><span><?= t('Seller') ?></span><b><?= htmlspecialchars($p['seller']) ?><?= !empty($p['verified'])?' · '.t('Verified business'):'' ?></b></div>
        <?php elseif(!empty($p['verified'])): ?><div class="spec-row"><span><?= t('Seller') ?></span><b><?= t('Verified business') ?> · <?= t('via VESTRA') ?></b></div><?php endif; ?>
        <?php if(!empty($p['origin'])): ?><div class="spec-row"><span><?= t('Origin / auth.') ?></span><b><?= htmlspecialchars($p['origin']) ?></b></div><?php endif; ?>
      </div>

      <?php if(!empty($p['linesheet'])): ?>
        <?php if($MEMBER): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0">
          <?php if(!empty($p['sheet_file'])): ?>
          <a class="btn btn-o btn-sm" href="/linesheet?id=<?= urlencode($p['id']) ?>&fmt=pdf" target="_blank" rel="noopener">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 21h16"/></svg>
            <?= t('Line sheet (PDF)') ?>
          </a>
          <?php endif; ?>
          <a class="btn btn-o btn-sm" href="/linesheet?id=<?= urlencode($p['id']) ?>&fmt=xls">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
            <?= t('Line sheet (Excel)') ?>
          </a>
        </div>
        <?php else: ?>
        <div class="hint" style="margin:14px 0">🔒 <?= t('Sign in to download the line sheet (PDF & Excel).') ?></div>
        <?php endif; ?>
      <?php elseif(!empty($p['sheet'])): ?>
        <a class="btn btn-o btn-sm" style="margin:14px 0" href="<?= htmlspecialchars($p['sheet']) ?>" target="_blank" rel="noopener">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 21h16"/></svg>
          <?= t('Line sheet') ?> (<?= strtoupper(htmlspecialchars(pathinfo($p['sheet'],PATHINFO_EXTENSION))) ?>)
        </a>
      <?php endif; ?>

      <?php if(!empty($p['group'])): $gp=vestra_group_pool($p['id']); if($gp): ?>
        <a href="/group?id=<?= urlencode($p['id']) ?>" class="banner info" style="display:block;margin:14px 0;text-decoration:none">
          🤝 <?= sprintf(t('Group buy: pool with others to unlock %s/%s — %d%% committed. Join →'), eur($gp['_gprice']), htmlspecialchars($p['unit']), $gp['_pct']) ?>
        </a>
      <?php endif; endif; ?>

      <?php if(!$MEMBER): ?>
        <div class="gate" style="margin-top:22px">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="1.6" style="margin:0 auto 8px"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
          <h3 style="margin:0 0 6px"><?= t('Verified buyers only') ?></h3>
          <p style="color:var(--mut);margin:0 0 16px"><?= t('Sign in as a verified business buyer to see pricing') ?><?= $mode==='offer'?' '.t('and make an offer'):' '.t('and order') ?>.</p>
          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a class="btn btn-p" href="/login?back=/product?id=<?= urlencode($p['id']) ?>"><?= t('Sign in') ?></a>
            <a class="btn btn-o" href="/register"><?= t('Register free') ?></a>
          </div>
        </div>

      <?php elseif($offered): ?>
        <div class="banner ok" style="margin-top:18px">✓ <?= t('Your offer is in the queue (ref') ?> <b><?= htmlspecialchars(substr($_GET['ref']??'',0,16)) ?></b>). <?= t('The seller will respond — track it under') ?> <a href="/buyer?tab=offers" class="acc"><?= t('My offers') ?></a>.</div>
        <a class="btn btn-o" href="/shop"><?= t('Continue browsing') ?></a>

      <?php elseif($mode==='offer'): ?>
        <div class="banner info" style="margin-top:18px">💬 <?= t('This item is <b>open to offers</b>.') ?> <?= htmlspecialchars($p['guide']??'') ?></div>
        <table class="tiers">
          <thead><tr><th><?= t('Volume') ?></th><th><?= t('Indicative unit') ?></th></tr></thead>
          <tbody>
          <?php foreach($p['tiers'] as $i=>$tier): ?>
            <tr><td><?= $tier['min'] ?><?= isset($p['tiers'][$i+1])?'–'.($p['tiers'][$i+1]['min']-1):'+' ?> <?= htmlspecialchars($p['unit']) ?></td><td class="amt"><?= eur($tier['price']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="order-box">
          <form method="post" action="/offer">
            <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div><label class="hint"><?= t('Quantity') ?> (<?= htmlspecialchars($p['unit']) ?>) — <?= t('min') ?> <?= $p['moq'] ?></label>
                <input type="number" name="qty" min="<?= $p['moq'] ?>" step="<?= (int)($p['size_step'] ?? 1) ?>" value="<?= $p['moq'] ?>" required style="width:100%"></div>
              <div><label class="hint"><?= t('Your offer') ?> (€ / <?= htmlspecialchars($p['unit']) ?>)</label>
                <input type="number" name="price" step="0.01" min="0" placeholder="<?= htmlspecialchars(t('e.g. 95.00')) ?>" required style="width:100%"></div>
            </div>
            <div style="margin-top:12px"><label class="hint"><?= t('Message to seller') ?></label>
              <textarea name="message" rows="2" style="width:100%" placeholder="<?= htmlspecialchars(t('Sizes, delivery, terms…')) ?>"></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:12px">
              <div><label class="hint"><?= t('Company') ?> *</label><input name="company" required style="width:100%"></div>
              <div><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%"></div>
            </div>
            <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:16px"><?= t('Submit offer →') ?></button>
            <div class="hint" style="margin-top:10px"><?= t("Your offer joins the seller's queue. If accepted, you receive an <b>invoice</b> — payment by bank transfer.") ?></div>
            <?php if($AUTH_USER && ($AUTH_USER['type']??'')==='buyer' && !empty($p['seller_uid'])): ?>
            <div class="hint" style="margin-top:6px">💬 <?= t('Your offer will also appear in Messages, linked to this product.') ?></div>
            <?php endif; ?>
          </form>
        </div>

      <?php else: ?>
        <?php if($mode==='sale'): ?>
          <div class="saleline">
            <span class="was"><?= eur($p['list']) ?></span>
            <span class="now"><?= t('from') ?> <?= eur($from) ?></span>
            <span class="badge-sale">−<?= $disc ?>%</span>
            <span class="hint">/ <?= htmlspecialchars($p['unit']) ?> · <?= t('clearance') ?></span>
          </div>
        <?php endif; ?>
        <table class="tiers" id="tiers">
          <thead><tr><th><?= t('Quantity') ?> (<?= htmlspecialchars($p['unit']) ?>)</th><th><?= $mode==='sale'?t('Sale unit'):t('Unit price') ?></th><th><?= t('Saving') ?></th></tr></thead>
          <tbody>
          <?php foreach($p['tiers'] as $i=>$tier):
            $base=$mode==='sale'?$p['list']:$p['tiers'][0]['price'];
            $save=(int)round(100*($base-$tier['price'])/$base); ?>
            <tr data-min="<?= $tier['min'] ?>" data-price="<?= $tier['price'] ?>">
              <td><?= $tier['min'] ?><?= isset($p['tiers'][$i+1])?'–'.($p['tiers'][$i+1]['min']-1):'+' ?></td>
              <td class="amt"><?= eur($tier['price']) ?></td>
              <td><?= $save>0?'−'.$save.'%':'—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="order-box">
          <div class="qtyrow">
            <div class="stepper">
              <button type="button" onclick="bump(-step())">−</button>
              <input id="qty" type="number" min="<?= $p['moq'] ?>" step="<?= (int)($p['size_step'] ?? 1) ?>" value="<?= $p['moq'] ?>" oninput="recalc()">
              <button type="button" onclick="bump(step())">+</button>
            </div>
            <span class="hint"><?= t('Min order') ?> <b><?= $p['moq'] ?> <?= htmlspecialchars($p['unit']) ?></b></span>
          </div>
          <div class="calc">
            <div class="unit"><?= t('Unit:') ?> <span id="uprice"><?= eur($from) ?></span> · <span id="tier"></span></div>
            <div class="total" id="total"><?= eur($from*$p['moq']) ?> <small><?= t('excl. taxes & shipping') ?></small></div>
          </div>
          <div id="warn" class="warn" style="display:none"></div>
          <button class="btn btn-p" id="addBtn" style="width:100%;justify-content:center" onclick="addToOrder()"><?= t('Add to order') ?></button>
          <div class="hint" style="margin-top:10px"><?= t('Payment is currently by <b>invoice</b> — you receive a proforma invoice and goods ship after bank-transfer payment.') ?></div>
        </div>
        <?php if(!empty($p['offers'])): ?>
        <div class="order-box" style="margin-top:14px">
          <div class="hint" style="margin-bottom:8px">💬 <?= t('This seller also accepts offers.') ?></div>
          <details class="offerdetails">
            <summary class="btn btn-o" style="width:100%;justify-content:center"><?= t('Make an offer') ?></summary>
            <form method="post" action="/offer" style="margin-top:12px">
              <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
              <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div><label class="hint"><?= t('Quantity') ?> — <?= t('min') ?> <?= $p['moq'] ?></label>
                  <input type="number" name="qty" min="<?= $p['moq'] ?>" step="<?= (int)($p['size_step'] ?? 1) ?>" value="<?= $p['moq'] ?>" required style="width:100%"></div>
                <div><label class="hint"><?= t('Your offer') ?> (€/<?= htmlspecialchars($p['unit']) ?>)</label>
                  <input type="number" name="price" step="0.01" min="0" required style="width:100%"></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div><label class="hint"><?= t('Company') ?> *</label><input name="company" required style="width:100%"></div>
                <div><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%"></div>
              </div>
              <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:12px"><?= t('Submit offer →') ?></button>
              <?php if($AUTH_USER && ($AUTH_USER['type']??'')==='buyer' && !empty($p['seller_uid'])): ?>
              <div class="hint" style="margin-top:8px">💬 <?= t('Your offer will also appear in Messages, linked to this product.') ?></div>
              <?php endif; ?>
            </form>
          </details>
        </div>
        <?php endif; ?>
        <?php if(!empty($p['seller_uid'])): ?>
        <div class="order-box" style="margin-top:14px">
          <?php if($AUTH_USER && ($AUTH_USER['type']??'')==='buyer'): ?>
          <details class="offerdetails">
            <summary class="btn btn-o" style="width:100%;justify-content:center">💬 <?= t('Message seller') ?></summary>
            <form method="post" action="/buyer?tab=messages" style="margin-top:12px">
              <input type="hidden" name="_action" value="send_message">
              <input type="hidden" name="listing_id" value="<?= htmlspecialchars($p['id']) ?>">
              <textarea name="body" rows="3" placeholder="<?= htmlspecialchars(t('Ask about MOQ, samples, delivery…')) ?>" required style="width:100%"></textarea>
              <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:10px"><?= t('Send') ?></button>
              <div class="hint" style="margin-top:8px"><?= t('Do not share email addresses, phone numbers, or bank details — keep all communication and payment on VESTRA.') ?></div>
            </form>
          </details>
          <?php else: ?>
          <a class="btn btn-o" href="/login?back=<?= urlencode('/product?id='.$p['id']) ?>" style="width:100%;justify-content:center">💬 <?= t('Sign in to message seller') ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <script>
        var P=<?= json_encode(['id'=>$p['id'],'brand'=>$p['brand'],'name'=>$p['name'],'sku'=>$p['sku'],'unitLabel'=>$p['unit'],'moq'=>(int)$p['moq'],'step'=>(int)($p['size_step']??0),'tiers'=>array_map(function($t){return ['min'=>(int)$t['min'],'price'=>(float)$t['price']];},$p['tiers'])]) ?>;
        function step(){ return P.step||(P.moq>=100?100:(P.moq>=50?50:10)); }
        function unitPrice(q){ var pr=P.tiers[0].price; P.tiers.forEach(function(t){ if(q>=t.min) pr=t.price; }); return pr; }
        function tierLabel(q){ var lab='—'; P.tiers.forEach(function(t,i){ if(q>=t.min){ var n=P.tiers[i+1]; lab=t.min+(n?'–'+(n.min-1):'+'); } }); return lab; }
        function eur(n){ return '€'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
        function bump(d){ var el=document.getElementById('qty'); el.value=Math.max(P.moq,(parseInt(el.value)||P.moq)+d); recalc(); }
        function recalc(){
          var q=parseInt(document.getElementById('qty').value)||0, warn=document.getElementById('warn'), btn=document.getElementById('addBtn');
          if(q<P.moq){ warn.style.display='block'; warn.textContent='<?= addslashes(t('Minimum order is')) ?> '+P.moq+' '+P.unitLabel+'.'; btn.disabled=true; }
          else { warn.style.display='none'; btn.disabled=false; }
          var u=unitPrice(q);
          document.getElementById('uprice').textContent=eur(u);
          document.getElementById('tier').textContent=<?= json_encode(t('tier')) ?>+' '+tierLabel(q)+' '+P.unitLabel;
          document.getElementById('total').innerHTML=eur(u*q)+' <small>'+<?= json_encode(t('excl. taxes & shipping')) ?>+'</small>';
          document.querySelectorAll('#tiers tbody tr').forEach(function(tr){ tr.classList.toggle('active', q>=parseInt(tr.dataset.min)&&(!tr.nextElementSibling||q<parseInt(tr.nextElementSibling.dataset.min))); });
        }
        function addToOrder(){ var q=parseInt(document.getElementById('qty').value)||0; if(q<P.moq) return; var u=unitPrice(q);
          VCart.add({id:P.id,brand:P.brand,name:P.name,sku:P.sku,unitLabel:P.unitLabel,qty:q,unit:u});
          var b=document.getElementById('addBtn'); b.textContent='✓ '+<?= json_encode(t('Added to order')) ?>; setTimeout(function(){b.textContent=<?= json_encode(t('Add to order')) ?>;},1400); }
        recalc();
        </script>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if($images): ?>
<script>
/* Index -1 = the brand card (always the first slide); 0..n-1 = photos. */
var galImgs=<?= json_encode($images) ?>, galIdx=-1;
function galSet(i){
  galIdx=i;
  var img=document.getElementById('gal-main-img'), card=document.getElementById('gal-card');
  if(i<0){ img.style.display='none'; card.style.display='flex'; }
  else { img.src=galImgs[i]; img.style.display='block'; card.style.display='none'; }
  document.querySelectorAll('.gal-thumb').forEach(function(t,j){ t.classList.toggle('active', j===i+1); });
}
function galGo(d){
  var n=galImgs.length+1;                 // slides: card + photos
  var cur=galIdx+1;                       // 0-based over all slides
  galSet(((cur+d)%n+n)%n - 1);
}
document.addEventListener('keydown', function(e){
  if(e.key==='ArrowLeft') galGo(-1);
  if(e.key==='ArrowRight') galGo(1);
});
</script>
<?php endif; ?>
<?php require __DIR__.'/inc/foot.php';
