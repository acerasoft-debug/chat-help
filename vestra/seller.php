<?php
require __DIR__.'/inc/products.php'; require __DIR__.'/inc/dash.php';
$PAGE='Seller panel'; $NAV='sell'; require __DIR__.'/inc/head.php';

if(!$MEMBER){
  echo '<div class="wrap"><div class="gate" style="margin:48px auto;max-width:460px">
    <h3 style="margin:0 0 6px">Seller workspace</h3>
    <p style="color:var(--mut);margin:0 0 16px">Sign in to manage your listings, orders and offers.</p>
    <a class="btn btn-p" href="/seller?demo_member=1">Sign in (demo)</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit;
}
$tab=$_GET['tab']??'overview';
$listings=vestra_listings();
$orders=vestra_read_csv('orders.csv');
$offers=vestra_read_csv('offers.csv');
$added=isset($_GET['added']);
$cats=vestra_cats();

dash_open('seller',$tab,
  $tab==='add'?'Add a product':($tab==='listings'?'My listings':($tab==='orders'?'Orders':($tab==='offers'?'Offers received':($tab==='kyc'?'Verification':'Overview')))),
  $tab==='overview'?'Welcome back — here is your activity':'');

if($tab==='overview'){
  $rev=0; foreach($orders as $o){ $rev+=(float)($o['total']??0); }
  stat_cards([
    ['<span class="acc">'.(count(vestra_demo_products())+count($listings)).'</span>','Live listings'],
    [count($orders),'Orders'],
    [count($offers),'Offers received'],
    [eur($rev),'Order value'],
  ]);
  echo '<div class="panelcard"><div class="pcfhead"><h3>Quick actions</h3></div>
    <div class="quickrow">
      <a class="btn btn-p btn-sm" href="/seller?tab=add">＋ Add a product</a>
      <a class="btn btn-o btn-sm" href="/seller?tab=orders">View orders</a>
      <a class="btn btn-o btn-sm" href="/requests">Browse buyer requests</a>
    </div></div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>Verification</h3><span class="status offers">✓ Verified seller</span></div>
    <p class="hint">Business KYB complete. Your listings show the “Verified” badge to buyers.</p></div>';

} elseif($tab==='add'){
  if($added) echo '<div class="banner ok">✓ Product added — it is now live in the <a class="acc" href="/shop">catalog</a>.</div>';
  ?>
  <div class="panelcard">
    <form method="post" action="/seller-add" class="addform">
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
      <div class="frow">
        <div><label>Brand *</label><input name="brand" required placeholder="e.g. Lacoste / Your label"></div>
        <div><label>Product name *</label><input name="name" required placeholder="Classic Piqué Polo"></div>
      </div>
      <div class="frow four">
        <div><label>Category</label>
          <select name="cat"><?php foreach($cats as $c) echo '<option>'.htmlspecialchars($c).'</option>'; ?><option>Other</option></select></div>
        <div><label>SKU</label><input name="sku" placeholder="auto if blank"></div>
        <div><label>Unit</label><select name="unit"><option>pc</option><option>pack</option><option>set</option><option>carton</option></select></div>
        <div><label>Min order (MOQ) *</label><input type="number" name="moq" min="1" value="12" required></div>
      </div>

      <label>Pricing mode *</label>
      <div class="moderow">
        <label class="moderadio"><input type="radio" name="mode" value="fixed" checked onchange="modeUI()"> <span>Fixed (tiered)</span></label>
        <label class="moderadio"><input type="radio" name="mode" value="sale" onchange="modeUI()"> <span>Sale (discount)</span></label>
        <label class="moderadio"><input type="radio" name="mode" value="offer" onchange="modeUI()"> <span>Make-an-offer</span></label>
      </div>

      <div id="listrow" class="frow" style="display:none">
        <div><label>List price (original €/unit)</label><input type="number" step="0.01" name="list" placeholder="for strikethrough"></div>
      </div>

      <label>Tiered pricing — quantity → unit price (€)</label>
      <div class="tiergrid">
        <div><span class="hint">Tier 1 qty</span><input type="number" name="t1min" value="12"></div>
        <div><span class="hint">€ / unit</span><input type="number" step="0.01" name="t1price" placeholder="34.00"></div>
        <div><span class="hint">Tier 2 qty</span><input type="number" name="t2min" placeholder="60"></div>
        <div><span class="hint">€ / unit</span><input type="number" step="0.01" name="t2price" placeholder="29.50"></div>
        <div><span class="hint">Tier 3 qty</span><input type="number" name="t3min" placeholder="180"></div>
        <div><span class="hint">€ / unit</span><input type="number" step="0.01" name="t3price" placeholder="25.00"></div>
      </div>
      <p class="hint" id="offerhint" style="display:none">For make-an-offer, tiers are shown as indicative guidance only.</p>

      <div class="frow">
        <div><label>Description</label><textarea name="desc" rows="2" placeholder="Sizes, colours, condition…"></textarea></div>
      </div>
      <div class="frow">
        <div><label>Origin / authenticity note *</label><input name="origin" required placeholder="e.g. EEA stock · invoice on request"></div>
        <div><label>Seller name</label><input name="seller" value="My Wholesale Co."></div>
      </div>
      <div class="banner info" style="margin:8px 0 0">By listing you confirm the goods are <b>genuine</b> and you are <b>entitled to sell</b> them (incl. EEA exhaustion where applicable) — per the Seller Agreement.</div>
      <button class="btn btn-p" type="submit" style="margin-top:14px">Publish product</button>
    </form>
  </div>
  <script>
  function modeUI(){ var m=document.querySelector('input[name=mode]:checked').value;
    document.getElementById('listrow').style.display = m==='sale'?'grid':'none';
    document.getElementById('offerhint').style.display = m==='offer'?'block':'none'; }
  modeUI();
  </script>
  <?php

} elseif($tab==='listings'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>Your listings</h3><a class="btn btn-p btn-sm" href="/seller?tab=add">＋ Add product</a></div>';
  echo '<table class="ctable"><thead><tr><th>Product</th><th>Mode</th><th>MOQ</th><th class="r">From</th><th>Status</th></tr></thead><tbody>';
  $all=array_merge($listings, vestra_demo_products());
  foreach($all as $p){ $mine=in_array($p,$listings,true);
    echo '<tr><td><b>'.htmlspecialchars($p['brand']).'</b> — '.htmlspecialchars($p['name']).'<div class="hint">SKU '.htmlspecialchars($p['sku']).($mine?' · yours':'').'</div></td>'.
      '<td><span class="modechip '.$p['mode'].'">'.$p['mode'].'</span></td><td>'.$p['moq'].' '.htmlspecialchars($p['unit']).'</td>'.
      '<td class="r">'.($p['mode']==='offer'?'—':eur(vestra_from_price($p))).'</td><td><span class="status offers">Live</span></td></tr>';
  }
  echo '</tbody></table></div>';

} elseif($tab==='orders'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>Orders</h3></div>';
  if(!$orders) dash_empty('No orders yet. Orders placed by buyers appear here.');
  else { echo '<table class="ctable"><thead><tr><th>Ref</th><th>Buyer</th><th>Items</th><th class="r">Total</th><th>When</th></tr></thead><tbody>';
    foreach($orders as $o){ echo '<tr><td><b>'.htmlspecialchars($o['ref']??'').'</b></td><td>'.htmlspecialchars($o['company']??'').'<div class="hint">'.htmlspecialchars($o['email']??'').'</div></td>'.
      '<td class="hint">'.htmlspecialchars($o['items']??'').'</td><td class="r">'.eur($o['total']??0).'</td><td class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} elseif($tab==='offers'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>Offers received</h3></div>';
  if(!$offers) dash_empty('No offers yet. Buyer offers on your make-an-offer items appear here.');
  else { echo '<table class="ctable"><thead><tr><th>Ref</th><th>Product</th><th>Buyer</th><th class="r">Offer</th><th></th></tr></thead><tbody>';
    foreach($offers as $o){ echo '<tr><td><b>'.htmlspecialchars($o['ref']??'').'</b></td><td>'.htmlspecialchars($o['product']??'').'<div class="hint">'.htmlspecialchars($o['qty']??'').'× SKU '.htmlspecialchars($o['sku']??'').'</div></td>'.
      '<td>'.htmlspecialchars($o['company']??'').'</td><td class="r"><b>'.eur($o['offer_unit']??0).'</b>/u<div class="hint">'.eur($o['offer_total']??0).' total</div></td>'.
      '<td><a class="btn btn-o btn-sm" href="#">Respond</a></td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} else { // kyc
  echo '<div class="panelcard"><div class="pcfhead"><h3>Business verification (KYB)</h3><span class="status offers">✓ Verified</span></div>
    <table class="ctable"><tbody>
    <tr><td>Company registration</td><td class="r"><span class="status offers">Approved</span></td></tr>
    <tr><td>VAT / Tax ID</td><td class="r"><span class="status offers">Approved</span></td></tr>
    <tr><td>Beneficial owner ID</td><td class="r"><span class="status offers">Approved</span></td></tr>
    <tr><td>Payout (escrow) account</td><td class="r"><span class="status open">Connect Tazapay</span></td></tr>
    </tbody></table>
    <p class="hint">Verified sellers can list and receive escrow payouts. See the <a class="acc" href="/legal?doc=seller">Seller Agreement</a>.</p></div>';
}
dash_close();
require __DIR__.'/inc/foot.php';
