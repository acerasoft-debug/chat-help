<?php
require __DIR__.'/inc/products.php';
$p = vestra_find($_GET['id'] ?? '');
if(!$p){ http_response_code(404); $PAGE='Not found'; require __DIR__.'/inc/head.php';
  echo '<div class="wrap"><div class="empty">Product not found. <a class="acc" href="/shop">Back to catalog</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit; }
$PAGE=$p['name']; $NAV='shop'; require __DIR__.'/inc/head.php';
$mode=$p['mode']; $from=vestra_from_price($p); $disc=vestra_discount($p);
$offered=isset($_GET['offered']);
?>
<div class="wrap">
  <div class="crumbs" style="margin-top:24px"><a href="/">Home</a> · <a href="/shop">Catalog</a> · <?=htmlspecialchars($p['brand'])?></div>

  <div class="pdetail">
    <div>
      <div class="hero-thumb" style="background:linear-gradient(135deg,<?=$p['accent']?>,#0e0e11)">
        <?php if(!empty($p['image'])): ?><img class="heroimg" src="<?=htmlspecialchars($p['image'])?>" alt="<?=htmlspecialchars($p['name'])?>"><?php endif; ?>
        <span class="bname"><?=htmlspecialchars($p['brand'])?></span>
        <?php if($mode==='sale'): ?><span class="modetag sale">SALE −<?=$disc?>%</span>
        <?php elseif($mode==='offer'): ?><span class="modetag offer">Open to offers</span><?php endif; ?>
      </div>
    </div>
    <div>
      <span class="acc" style="font-size:13px;letter-spacing:1px;text-transform:uppercase"><?=htmlspecialchars($p['brand'])?></span>
      <h1><?=htmlspecialchars($p['name'])?></h1>
      <p class="sub"><?=htmlspecialchars($p['desc'])?></p>
      <div class="spec">
        <span>SKU <b><?=htmlspecialchars($p['sku'])?></b></span>
        <span>Category <b><?=htmlspecialchars($p['cat'])?></b></span>
        <span>MOQ <b><?=$p['moq']?> <?=htmlspecialchars($p['unit'])?></b></span>
        <?php if(!empty($p['seller'])): ?><span><?= t('Seller') ?> <b><?=htmlspecialchars($p['seller'])?></b><?php if(!empty($p['verified'])): ?> ✓<?php endif; ?></span><?php endif; ?>
      </div>
      <div class="hint"><?=htmlspecialchars($p['origin'])?></div>
      <?php if(!empty($p['sheet'])): ?>
        <a class="btn btn-o btn-sm" style="margin-top:12px" href="<?=htmlspecialchars($p['sheet'])?>" target="_blank" rel="noopener">⬇ Line sheet / price list (<?=strtoupper(htmlspecialchars(pathinfo($p['sheet'],PATHINFO_EXTENSION)))?>)</a>
      <?php endif; ?>
      <?php if(!empty($p['group'])): $gp=vestra_group_pool($p['id']); if($gp): ?>
        <a href="/group?id=<?=urlencode($p['id'])?>" class="banner info" style="display:block;margin-top:14px;text-decoration:none">
          🤝 <?= sprintf(t('Also a <b>Group buy</b>: pool with other buyers to unlock %s / %s — %d%% of the target is already committed. Join the pool →'), eur($gp['_gprice']), htmlspecialchars($p['unit']), $gp['_pct']) ?>
        </a>
      <?php endif; endif; ?>

      <?php if(!$MEMBER): ?>
        <div class="gate" style="margin-top:22px">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="1.6" style="margin:0 auto 8px"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
          <h3 style="margin:0 0 6px"><?= t('Verified buyers only') ?></h3>
          <p style="color:var(--mut);margin:0 0 16px"><?= t('Sign in as a verified business buyer to see pricing') ?><?= $mode==='offer'?' '.t('and make an offer'):' '.t('and order') ?>.</p>
          <a class="btn btn-p" href="?id=<?=urlencode($p['id'])?>&demo_member=1"><?= t('Sign in') ?></a>
        </div>

      <?php elseif($offered): ?>
        <div class="banner ok" style="margin-top:18px">✓ <?= t('Your offer is in the queue (ref') ?> <b><?=htmlspecialchars(substr($_GET['ref']??'',0,16))?></b>). <?= t('The seller will respond — track it under') ?> <a href="/requests" class="acc"><?= t('Requests') ?></a>.</div>
        <a class="btn btn-o" href="/shop"><?= t('Continue browsing') ?></a>

      <?php elseif($mode==='offer'): /* ---------- MAKE AN OFFER ---------- */ ?>
        <div class="banner info" style="margin-top:18px">💬 <?= t('This item is <b>open to offers</b>.') ?> <?=htmlspecialchars($p['guide']??'')?></div>
        <table class="tiers">
          <thead><tr><th><?= t('Volume') ?></th><th><?= t('Indicative unit') ?></th></tr></thead>
          <tbody>
          <?php foreach($p['tiers'] as $i=>$t): ?>
            <tr><td><?=$t['min']?><?= isset($p['tiers'][$i+1])?'–'.($p['tiers'][$i+1]['min']-1):'+' ?> <?=htmlspecialchars($p['unit'])?></td><td class="amt"><?=eur($t['price'])?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="order-box">
          <form method="post" action="/offer">
            <input type="hidden" name="id" value="<?=htmlspecialchars($p['id'])?>">
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div><label class="hint"><?= t('Quantity') ?> (<?=htmlspecialchars($p['unit'])?>) — min <?=$p['moq']?></label>
                <input type="number" name="qty" min="<?=$p['moq']?>" value="<?=$p['moq']?>" required style="width:100%"></div>
              <div><label class="hint"><?= t('Your offer') ?> (€ / <?=htmlspecialchars($p['unit'])?>)</label>
                <input type="number" name="price" step="0.01" min="0" placeholder="<?= htmlspecialchars(t('e.g. 95.00')) ?>" required style="width:100%"></div>
            </div>
            <div style="margin-top:12px"><label class="hint"><?= t('Message to seller') ?></label>
              <textarea name="message" rows="2" style="width:100%" placeholder="<?= htmlspecialchars(t('Sizes, delivery, terms…')) ?>"></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:12px">
              <div><label class="hint"><?= t('Company') ?> *</label><input name="company" required style="width:100%"></div>
              <div><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%"></div>
            </div>
            <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:16px"><?= t('Submit offer →') ?></button>
            <div class="hint" style="margin-top:10px"><?= t("Your offer joins the seller's queue. If accepted, payment is via <b>escrow</b>.") ?></div>
          </form>
        </div>

      <?php else: /* ---------- FIXED or SALE (orderable) ---------- */ ?>
        <?php if($mode==='sale'): ?>
          <div class="saleline">
            <span class="was"><?=eur($p['list'])?></span>
            <span class="now">from <?=eur($from)?></span>
            <span class="badge-sale">−<?=$disc?>%</span>
            <span class="hint">/ <?=htmlspecialchars($p['unit'])?> · <?= t('clearance') ?></span>
          </div>
        <?php endif; ?>
        <table class="tiers" id="tiers">
          <thead><tr><th><?= t('Quantity') ?> (<?=htmlspecialchars($p['unit'])?>)</th><th><?= $mode==='sale'?t('Sale unit'):t('Unit price') ?></th><th><?= t('Saving') ?></th></tr></thead>
          <tbody>
          <?php foreach($p['tiers'] as $i=>$t):
            $base=$mode==='sale'?$p['list']:$p['tiers'][0]['price'];
            $save=(int)round(100*($base-$t['price'])/$base); ?>
            <tr data-min="<?=$t['min']?>" data-price="<?=$t['price']?>">
              <td><?=$t['min']?><?= isset($p['tiers'][$i+1])?'–'.($p['tiers'][$i+1]['min']-1):'+' ?></td>
              <td class="amt"><?=eur($t['price'])?></td>
              <td><?= $save>0?'−'.$save.'%':'—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="order-box">
          <div class="qtyrow">
            <div class="stepper">
              <button type="button" onclick="bump(-step())">−</button>
              <input id="qty" type="number" min="<?=$p['moq']?>" step="1" value="<?=$p['moq']?>" oninput="recalc()">
              <button type="button" onclick="bump(step())">+</button>
            </div>
            <span class="hint"><?= t('Min order') ?> <b><?=$p['moq']?> <?=htmlspecialchars($p['unit'])?></b></span>
          </div>
          <div class="calc">
            <div class="unit"><?= t('Unit:') ?> <span id="uprice"><?=eur($from)?></span> · <span id="tier"></span></div>
            <div class="total" id="total"><?=eur($from*$p['moq'])?> <small><?= t('excl. taxes & shipping') ?></small></div>
          </div>
          <div id="warn" class="warn" style="display:none"></div>
          <button class="btn btn-p" id="addBtn" style="width:100%;justify-content:center" onclick="addToOrder()"><?= t('Add to order') ?></button>
          <div class="hint" style="margin-top:10px"><?= t('Orders are placed via secured <b>escrow</b> — funds release after you confirm receipt.') ?></div>
        </div>
        <?php if(!empty($p['offers'])): ?>
        <div class="order-box" style="margin-top:14px">
          <div class="hint" style="margin-bottom:8px">💬 <?= t('This seller also accepts offers.') ?></div>
          <details class="offerdetails">
            <summary class="btn btn-o" style="width:100%;justify-content:center"><?= t('Make an offer') ?></summary>
            <form method="post" action="/offer" style="margin-top:12px">
              <input type="hidden" name="id" value="<?=htmlspecialchars($p['id'])?>">
              <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div><label class="hint"><?= t('Quantity') ?> (<?=htmlspecialchars($p['unit'])?>) — min <?=$p['moq']?></label>
                  <input type="number" name="qty" min="<?=$p['moq']?>" value="<?=$p['moq']?>" required style="width:100%"></div>
                <div><label class="hint"><?= t('Your offer') ?> (€ / <?=htmlspecialchars($p['unit'])?>)</label>
                  <input type="number" name="price" step="0.01" min="0" placeholder="<?= htmlspecialchars(t('e.g. 95.00')) ?>" required style="width:100%"></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div><label class="hint"><?= t('Company') ?> *</label><input name="company" required style="width:100%"></div>
                <div><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%"></div>
              </div>
              <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:12px"><?= t('Submit offer →') ?></button>
            </form>
          </details>
        </div>
        <?php endif; ?>
        <script>
        var P=<?= json_encode(['id'=>$p['id'],'brand'=>$p['brand'],'name'=>$p['name'],'sku'=>$p['sku'],'unitLabel'=>$p['unit'],'moq'=>(int)$p['moq'],'tiers'=>array_map(function($t){return ['min'=>(int)$t['min'],'price'=>(float)$t['price']];},$p['tiers'])]) ?>;
        function step(){ return P.moq>=100?10:(P.moq>=12?6:1); }
        function unitPrice(q){ var pr=P.tiers[0].price; P.tiers.forEach(function(t){ if(q>=t.min) pr=t.price; }); return pr; }
        function tierLabel(q){ var lab='—'; P.tiers.forEach(function(t,i){ if(q>=t.min){ var n=P.tiers[i+1]; lab=t.min+(n?'–'+(n.min-1):'+'); } }); return lab; }
        function eur(n){ return '€'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
        function bump(d){ var el=document.getElementById('qty'); el.value=Math.max(P.moq,(parseInt(el.value)||P.moq)+d); recalc(); }
        function recalc(){
          var q=parseInt(document.getElementById('qty').value)||0, warn=document.getElementById('warn'), btn=document.getElementById('addBtn');
          if(q<P.moq){ warn.style.display='block'; warn.textContent='Minimum order is '+P.moq+' '+P.unitLabel+'.'; btn.disabled=true; }
          else { warn.style.display='none'; btn.disabled=false; }
          var u=unitPrice(q);
          document.getElementById('uprice').textContent=eur(u);
          document.getElementById('tier').textContent='tier '+tierLabel(q)+' '+P.unitLabel;
          document.getElementById('total').innerHTML=eur(u*q)+' <small>'+<?= json_encode(t('excl. taxes & shipping')) ?>+'</small>';
          document.querySelectorAll('#tiers tbody tr').forEach(function(tr){ tr.classList.toggle('active', q>=parseInt(tr.dataset.min) && (!tr.nextElementSibling||q<parseInt(tr.nextElementSibling.dataset.min))); });
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
<?php require __DIR__.'/inc/foot.php';
