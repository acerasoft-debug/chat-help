<?php
require __DIR__.'/inc/products.php'; require __DIR__.'/inc/dash.php';
$PAGE='Buyer panel'; $NAV='account'; require __DIR__.'/inc/head.php';

if(!$MEMBER){
  echo '<div class="wrap"><div class="gate" style="margin:48px auto;max-width:460px">
    <h3 style="margin:0 0 6px">Buyer workspace</h3>
    <p style="color:var(--mut);margin:0 0 16px">Sign in to track your orders, sourcing requests and offers.</p>
    <a class="btn btn-p" href="buyer.php?demo_member=1">Sign in (demo)</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit;
}
$tab=$_GET['tab']??'overview';
$orders=vestra_read_csv('orders.csv');
$requests=vestra_read_csv('requests.csv');
$offers=vestra_read_csv('offers.csv');

dash_open('buyer',$tab,
  $tab==='orders'?'My orders':($tab==='requests'?'My sourcing requests':($tab==='offers'?'My offers':($tab==='kyc'?'Verification':'Overview'))),
  $tab==='overview'?'Your purchasing activity at a glance':'');

if($tab==='overview'){
  $spent=0; foreach($orders as $o){ $spent+=(float)($o['total']??0); }
  stat_cards([
    [count($orders),'Orders'],
    ['<span class="acc">'.count($requests).'</span>','Open requests'],
    [count($offers),'Offers made'],
    [eur($spent),'Order value'],
  ]);
  echo '<div class="panelcard"><div class="pcfhead"><h3>Quick actions</h3></div><div class="quickrow">
    <a class="btn btn-p btn-sm" href="shop.php">Browse catalog</a>
    <a class="btn btn-o btn-sm" href="requests.php#post">Post a sourcing request</a>
    <a class="btn btn-o btn-sm" href="buyer.php?tab=orders">View orders</a></div></div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>Buyer protection</h3><span class="status offers">Escrow active</span></div>
    <p class="hint">Every order is escrow-protected — funds release to the seller only after you confirm receipt.</p></div>';

} elseif($tab==='orders'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>Orders</h3><a class="btn btn-o btn-sm" href="shop.php">New order</a></div>';
  if(!$orders) dash_empty('No orders yet. Place an order from the catalog.');
  else { echo '<table class="ctable"><thead><tr><th>Ref</th><th>Items</th><th class="r">Total</th><th>Status</th></tr></thead><tbody>';
    foreach($orders as $o){ echo '<tr><td><b>'.htmlspecialchars($o['ref']??'').'</b><div class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</div></td>'.
      '<td class="hint">'.htmlspecialchars($o['items']??'').'</td><td class="r">'.eur($o['total']??0).'</td>'.
      '<td><span class="status open">In escrow</span></td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} elseif($tab==='requests'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>Sourcing requests</h3><a class="btn btn-p btn-sm" href="requests.php#post">＋ New request</a></div>';
  if(!$requests) dash_empty('No requests yet. Post what you need on the sourcing board.');
  else { echo '<table class="ctable"><thead><tr><th>Ref</th><th>Looking for</th><th>Target</th><th>Status</th></tr></thead><tbody>';
    foreach($requests as $r){ echo '<tr><td><b>'.htmlspecialchars($r['ref']??'').'</b></td><td>'.htmlspecialchars($r['title']??'').'<div class="hint">'.htmlspecialchars($r['qty']??'').'</div></td>'.
      '<td>'.htmlspecialchars($r['target']??'').'</td><td><span class="status open">In queue</span></td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} elseif($tab==='offers'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>Offers made</h3></div>';
  if(!$offers) dash_empty('No offers yet. Make an offer on a make-an-offer product.');
  else { echo '<table class="ctable"><thead><tr><th>Ref</th><th>Product</th><th class="r">Your offer</th><th>Status</th></tr></thead><tbody>';
    foreach($offers as $o){ echo '<tr><td><b>'.htmlspecialchars($o['ref']??'').'</b></td><td>'.htmlspecialchars($o['product']??'').'<div class="hint">'.htmlspecialchars($o['qty']??'').'× SKU '.htmlspecialchars($o['sku']??'').'</div></td>'.
      '<td class="r"><b>'.eur($o['offer_unit']??0).'</b>/u</td><td><span class="status open">Pending seller</span></td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} else { // kyc
  echo '<div class="panelcard"><div class="pcfhead"><h3>Business verification</h3><span class="status offers">✓ Verified buyer</span></div>
    <p class="hint">Verified buyers see wholesale pricing and can order with escrow protection.</p>
    <table class="ctable"><tbody>
    <tr><td>Company details</td><td class="r"><span class="status offers">Approved</span></td></tr>
    <tr><td>VAT / Tax ID</td><td class="r"><span class="status offers">Approved</span></td></tr>
    <tr><td>Email verified</td><td class="r"><span class="status offers">Approved</span></td></tr>
    </tbody></table></div>';
}
dash_close();
require __DIR__.'/inc/foot.php';
