<?php
require __DIR__.'/inc/products.php';
$p = vestra_find($_GET['id'] ?? '');
if(!$p){ http_response_code(404); $PAGE='Not found'; require __DIR__.'/inc/head.php';
  echo '<div class="wrap"><div class="empty">Product not found. <a class="acc" href="shop.php">Back to catalog</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit; }
$PAGE=$p['name']; $NAV='shop'; require __DIR__.'/inc/head.php';
$mode=$p['mode']; $from=vestra_from_price($p); $disc=vestra_discount($p);
$offered=isset($_GET['offered']);
?>
<div class="wrap">
  <div class="crumbs" style="margin-top:24px"><a href="index.php">Home</a> · <a href="shop.php">Catalog</a> · <?=htmlspecialchars($p['brand'])?></div>

  <div class="pdetail">
    <div>
      <div class="hero-thumb" style="background:linear-gradient(135deg,<?=$p['accent']?>,#0e0e11)">
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
        <span>Seller <b><?=htmlspecialchars($p['seller'])?></b><?php if(!empty($p['verified'])): ?> ✓<?php endif; ?></span>
      </div>
      <div class="hint"><?=htmlspecialchars($p['origin'])?></div>

      <?php if(!$MEMBER): ?>
        <div class="gate" style="margin-top:22px">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="1.6" style="margin:0 auto 8px"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
          <h3 style="margin:0 0 6px">Verified buyers only</h3>
          <p style="color:var(--mut);margin:0 0 16px">Sign in as a verified business buyer to see pricing<?= $mode==='offer'?' and make an offer':' and order' ?>.</p>
          <a class="btn btn-p" href="?id=<?=urlencode($p['id'])?>&demo_member=1">Sign in</a>
        </div>

      <?php elseif($offered): ?>
        <div class="banner ok" style="margin-top:18px">✓ Your offer is in the queue (ref <b><?=htmlspecialchars(substr($_GET['ref']??'',0,16))?></b>). The seller will respond — track it under <a href="requests.php" class="acc">Requests</a>.</div>
        <a class="btn btn-o" href="shop.php">Continue browsing</a>

      <?php elseif($mode==='offer'): /* ---------- MAKE AN OFFER ---------- */ ?>
        <div class="banner info" style="margin-top:18px">💬 This item is <b>open to offers</b>. <?=htmlspecialchars($p['guide']??'')?></div>
        <table class="tiers">
          <thead><tr><th>Volume</th><th>Indicative unit</th></tr></thead>
          <tbody>
          <?php foreach($p['tiers'] as $i=>$t): ?>
            <tr><td><?=$t['min']?><?= isset($p['tiers'][$i+1])?'–'.($p['tiers'][$i+1]['min']-1):'+' ?> <?=htmlspecialchars($p['unit'])?></td><td class="amt"><?=eur($t['price'])?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="order-box">
          <form method="post" action="offer.php">
            <input type="hidden" name="id" value="<?=htmlspecialchars($p['id'])?>">
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div><label class="hint">Quantity (<?=htmlspecialchars($p['unit'])?>) — min <?=$p['moq']?></label>
                <input type="number" name="qty" min="<?=$p['moq']?>" value="<?=$p['moq']?>" required style="width:100%"></div>
              <div><label class="hint">Your offer (€ / <?=htmlspecialchars($p['unit'])?>)</label>
                <input type="number" name="price" step="0.01" min="0" placeholder="e.g. 95.00" required style="width:100%"></div>
            </div>
            <div style="margin-top:12px"><label class="hint">Message to seller</label>
              <textarea name="message" rows="2" style="width:100%" placeholder="Sizes, delivery, terms…"></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:12px">
              <div><label class="hint">Company *</label><input name="company" required style="width:100%"></div>
              <div><label class="hint">Work email *</label><input type="email" name="email" required style="width:100%"></div>
            </div>
            <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:16px">Submit offer →</button>
            <div class="hint" style="margin-top:10px">Your offer joins the seller's queue. If accepted, payment is via <b>escrow</b>.</div>
          </form>
        </div>

      <?php else: /* ---------- FIXED or SALE (orderable) ---------- */ ?>
        <?php if($mode==='sale'): ?>
          <div class="saleline">
            <span class="was"><?=eur($p['list'])?></span>
            <span class="now">from <?=eur($from)?></span>
            <span class="badge-sale">−<?=$disc?>%</span>
            <span class="hint">/ <?=htmlspecialchars($p['unit'])?> · clearance</span>
          </div>
        <?php endif; ?>
        <table class="tiers" id="tiers">
          <thead><tr><th>Quantity (<?=htmlspecialchars($p['unit'])?>)</th><th><?= $mode==='sale'?'Sale unit':'Unit price' ?></th><th>Saving</th></tr></thead>
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
            <span class="hint">Min order <b><?=$p['moq']?> <?=htmlspecialchars($p['unit'])?></b></span>
          </div>
          <div class="calc">
            <div class="unit">Unit: <span id="uprice"><?=eur($from)?></span> · <span id="tier"></span></div>
            <div class="total" id="total"><?=eur($from*$p['moq'])?> <small>excl. fees</small></div>
          </div>
          <div id="warn" class="warn" style="display:none"></div>
          <button class="btn btn-p" id="addBtn" style="width:100%;justify-content:center" onclick="addToOrder()">Add to order</button>
          <div class="hint" style="margin-top:10px">Orders are placed via secured <b>escrow</b> — funds release after you confirm receipt.</div>
        </div>
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
          document.getElementById('total').innerHTML=eur(u*q)+' <small>excl. fees</small>';
          document.querySelectorAll('#tiers tbody tr').forEach(function(tr){ tr.classList.toggle('active', q>=parseInt(tr.dataset.min) && (!tr.nextElementSibling||q<parseInt(tr.nextElementSibling.dataset.min))); });
        }
        function addToOrder(){ var q=parseInt(document.getElementById('qty').value)||0; if(q<P.moq) return; var u=unitPrice(q);
          VCart.add({id:P.id,brand:P.brand,name:P.name,sku:P.sku,unitLabel:P.unitLabel,qty:q,unit:u});
          var b=document.getElementById('addBtn'); b.textContent='✓ Added to order'; setTimeout(function(){b.textContent='Add to order';},1400); }
        recalc();
        </script>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
