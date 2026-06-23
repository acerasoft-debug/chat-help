<?php
require __DIR__.'/inc/products.php';
$p = vestra_find($_GET['id'] ?? '');
if(!$p){ http_response_code(404); $PAGE='Not found'; require __DIR__.'/inc/head.php';
  echo '<div class="wrap"><div class="empty">Product not found. <a class="acc" href="shop.php">Back to catalog</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit; }
$PAGE=$p['name']; $NAV='shop'; require __DIR__.'/inc/head.php';
$from = vestra_from_price($p);
?>
<div class="wrap">
  <div class="crumbs" style="margin-top:24px"><a href="index.php">Home</a> · <a href="shop.php">Catalog</a> · <?=htmlspecialchars($p['brand'])?></div>

  <div class="pdetail">
    <div>
      <div class="hero-thumb" style="background:linear-gradient(135deg,<?=$p['accent']?>,#0e0e11)">
        <span class="bname"><?=htmlspecialchars($p['brand'])?></span>
      </div>
    </div>
    <div>
      <span class="pcard-brand acc" style="font-size:13px;letter-spacing:1px;text-transform:uppercase"><?=htmlspecialchars($p['brand'])?></span>
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
          <h3 style="margin:0 0 6px">Wholesale pricing for verified buyers</h3>
          <p style="color:var(--mut);margin:0 0 16px">Sign in as a verified business buyer to see bulk pricing and place an order.</p>
          <a class="btn btn-p" href="?id=<?=urlencode($p['id'])?>&demo_member=1">Sign in to view pricing</a>
        </div>
      <?php else: ?>
        <table class="tiers" id="tiers">
          <thead><tr><th>Quantity (<?=htmlspecialchars($p['unit'])?>)</th><th>Unit price</th><th>Saving</th></tr></thead>
          <tbody>
          <?php foreach($p['tiers'] as $i=>$t):
            $save = $i===0 ? 0 : round(100*($p['tiers'][0]['price']-$t['price'])/$p['tiers'][0]['price']); ?>
            <tr data-min="<?=$t['min']?>" data-price="<?=$t['price']?>">
              <td><?=$t['min']?><?= isset($p['tiers'][$i+1]) ? '–'.($p['tiers'][$i+1]['min']-1) : '+' ?></td>
              <td class="amt"><?=eur($t['price'])?></td>
              <td><?= $save>0 ? '−'.$save.'%' : '—' ?></td>
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
            <div>
              <div class="unit">Unit price: <span id="uprice"><?=eur($from)?></span> · <span id="tier"></span></div>
            </div>
            <div class="total" id="total"><?=eur($from*$p['moq'])?> <small>excl. fees</small></div>
          </div>
          <div id="warn" class="warn" style="display:none"></div>
          <button class="btn btn-p" id="addBtn" style="width:100%;justify-content:center" onclick="addToOrder()">Add to order</button>
          <div class="hint" style="margin-top:10px">Orders are placed via secured <b>escrow</b> — funds release after you confirm receipt.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if($MEMBER): ?>
<script>
var P = <?= json_encode([
  'id'=>$p['id'],'brand'=>$p['brand'],'name'=>$p['name'],'sku'=>$p['sku'],
  'unitLabel'=>$p['unit'],'moq'=>(int)$p['moq'],'tiers'=>array_map(function($t){return ['min'=>(int)$t['min'],'price'=>(float)$t['price']];},$p['tiers'])
]) ?>;
function step(){ return P.moq>=100?10:(P.moq>=12?6:1); }
function unitPrice(q){ var pr=P.tiers[0].price; P.tiers.forEach(function(t){ if(q>=t.min) pr=t.price; }); return pr; }
function tierLabel(q){ var lab='—'; P.tiers.forEach(function(t,i){ if(q>=t.min){ var next=P.tiers[i+1]; lab = t.min + (next? '–'+(next.min-1) : '+'); } }); return lab; }
function eur(n){ return '€'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function bump(d){ var el=document.getElementById('qty'); el.value=Math.max(P.moq,(parseInt(el.value)||P.moq)+d); recalc(); }
function recalc(){
  var q=parseInt(document.getElementById('qty').value)||0;
  var warn=document.getElementById('warn'), btn=document.getElementById('addBtn');
  if(q<P.moq){ warn.style.display='block'; warn.textContent='Minimum order is '+P.moq+' '+P.unitLabel+'.'; btn.disabled=true; }
  else { warn.style.display='none'; btn.disabled=false; }
  var u=unitPrice(q);
  document.getElementById('uprice').textContent=eur(u);
  document.getElementById('tier').textContent='tier '+tierLabel(q)+' '+P.unitLabel;
  document.getElementById('total').innerHTML=eur(u*q)+' <small>excl. fees</small>';
  // highlight active tier row
  document.querySelectorAll('#tiers tbody tr').forEach(function(tr){
    tr.classList.toggle('active', q>=parseInt(tr.dataset.min) &&
      (!tr.nextElementSibling || q<parseInt(tr.nextElementSibling.dataset.min)));
  });
}
function addToOrder(){
  var q=parseInt(document.getElementById('qty').value)||0; if(q<P.moq) return;
  var u=unitPrice(q);
  VCart.add({id:P.id,brand:P.brand,name:P.name,sku:P.sku,unitLabel:P.unitLabel,qty:q,unit:u});
  var b=document.getElementById('addBtn'); b.textContent='✓ Added to order'; setTimeout(function(){b.textContent='Add to order';},1400);
}
recalc();
</script>
<?php endif; ?>
<?php require __DIR__.'/inc/foot.php';
