<?php $PAGE='Your order'; $NAV='shop'; require __DIR__.'/inc/head.php'; require_once __DIR__.'/inc/products.php'; $placed=isset($_GET['placed']); ?>
<div class="wrap">
  <div class="phead">
    <div class="crumbs"><a href="/">Home</a> · <a href="/shop">Catalog</a> · Order</div>
    <h1>Your order</h1>
  </div>

  <?php if($placed): ?>
    <div class="banner ok">✓ Order request received. We'll confirm seller availability and send a secured (escrow) payment link. Reference: <b><?=htmlspecialchars(substr($_GET['ref']??'',0,20))?></b></div>
    <a class="btn btn-o" href="/shop">Continue browsing</a>
  <?php else: ?>

  <div id="empty" class="empty" style="display:none">
    Your order is empty. <a class="acc" href="/shop">Browse the catalog →</a>
  </div>

  <div id="filled" style="display:none">
    <table class="ctable">
      <thead><tr><th>Product</th><th>Qty</th><th class="r">Unit</th><th class="r">Line total</th><th></th></tr></thead>
      <tbody id="rows"></tbody>
    </table>

    <div class="summary"><div class="box">
      <div class="line"><span>Subtotal</span><span id="sub"></span></div>
      <div class="line"><span>Buyer-protection fee (<?=round(VESTRA_FEE_BUYER*100)?>%)</span><span id="bfee"></span></div>
      <div class="line big"><span>Total (you pay)</span><span id="grand"></span></div>
      <div class="hint" style="margin-top:8px">Includes a <b><?=round(VESTRA_FEE_BUYER*100)?>% buyer-protection fee</b> (secure escrow + authenticity guarantee). The seller separately pays a <?=round(VESTRA_FEE_SELLER*100)?>% commission.</div>
      <div class="hint" style="margin-top:6px">Payment is held in <b>escrow</b>; released to the seller after you confirm receipt.</div>
    </div></div>

    <form id="orderForm" method="post" action="/order">
      <input type="hidden" name="cart" id="cartField">
      <h3 style="margin:24px 0 10px">Buyer details</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:680px">
        <div><label class="hint">Company *</label><input name="company" required style="width:100%"></div>
        <div><label class="hint">VAT / Tax ID</label><input name="vat" style="width:100%"></div>
        <div><label class="hint">Contact name *</label><input name="name" required style="width:100%"></div>
        <div><label class="hint">Work email *</label><input type="email" name="email" required style="width:100%"></div>
        <div><label class="hint">Country</label><input name="country" style="width:100%"></div>
        <div><label class="hint">Phone</label><input name="phone" style="width:100%"></div>
      </div>
      <div style="margin-top:10px;max-width:680px"><label class="hint">Notes</label><textarea name="notes" rows="2" style="width:100%"></textarea></div>
      <input type="text" name="website" style="position:absolute;left:-9999px" tabindex="-1" autocomplete="off">
      <button class="btn btn-p" type="submit" style="margin-top:18px">Place order request</button>
      <span class="hint" style="margin-left:12px">No payment now — we confirm availability, then send the escrow link.</span>
    </form>
  </div>

  <?php endif; ?>
</div>

<?php if(!$placed): ?>
<script>
function eur(n){ return '€'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function render(){
  var c=VCart.all();
  document.getElementById('empty').style.display = c.length?'none':'block';
  document.getElementById('filled').style.display = c.length?'block':'none';
  var rows='', sub=0;
  c.forEach(function(x){
    var line=x.qty*x.unit; sub+=line;
    rows+='<tr><td><b>'+x.brand+'</b> — '+x.name+'<div class="hint">SKU '+x.sku+'</div></td>'+
      '<td>'+x.qty+' '+x.unitLabel+'</td><td class="r">'+eur(x.unit)+'</td><td class="r">'+eur(line)+'</td>'+
      '<td class="x" title="Remove" onclick="VCart.remove(\''+x.id+'\');render()">✕</td></tr>';
  });
  document.getElementById('rows').innerHTML=rows;
  var bfee=sub*<?=VESTRA_FEE_BUYER?>;
  document.getElementById('sub').textContent=eur(sub);
  document.getElementById('bfee').textContent=eur(bfee);
  document.getElementById('grand').textContent=eur(sub+bfee);
  document.getElementById('cartField').value=JSON.stringify(c);
}
document.getElementById('orderForm') && document.getElementById('orderForm').addEventListener('submit',function(){
  document.getElementById('cartField').value=JSON.stringify(VCart.all());
});
render();
</script>
<?php endif; ?>
<?php require __DIR__.'/inc/foot.php';
