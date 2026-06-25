<?php
require __DIR__.'/inc/products.php'; require __DIR__.'/inc/dash.php';
$PAGE=t('Buyer panel'); $NAV='account'; require __DIR__.'/inc/head.php';

if(!$MEMBER){
  echo '<div class="wrap"><div class="gate" style="margin:48px auto;max-width:460px">
    <h3 style="margin:0 0 6px">'.t('Buyer workspace').'</h3>
    <p style="color:var(--mut);margin:0 0 16px">'.t('Sign in to track your orders, sourcing requests and offers.').'</p>
    <a class="btn btn-p" href="/buyer?demo_member=1">'.t('Sign in (demo)').'</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit;
}
$tab=$_GET['tab']??'overview';
$orders=vestra_read_csv('orders.csv');
$requests=vestra_read_csv('requests.csv');
$offers=vestra_read_csv('offers.csv');

dash_open('buyer',$tab,
  $tab==='orders'?t('My orders'):($tab==='requests'?t('My sourcing requests'):($tab==='offers'?t('My offers'):($tab==='kyc'?t('Verification'):t('Overview')))),
  $tab==='overview'?t('Your purchasing activity at a glance'):'');

if($tab==='overview'){
  $spent=0; foreach($orders as $o){ $spent+=(float)($o['total']??0); }
  stat_cards([
    [count($orders),t('Orders')],
    ['<span class="acc">'.count($requests).'</span>',t('Open requests')],
    [count($offers),t('Offers made')],
    [eur($spent),t('Order value')],
  ]);
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Quick actions').'</h3></div><div class="quickrow">
    <a class="btn btn-p btn-sm" href="/shop">'.t('Browse catalog').'</a>
    <a class="btn btn-o btn-sm" href="/requests#post">'.t('Post a sourcing request').'</a>
    <a class="btn btn-o btn-sm" href="/buyer?tab=orders">'.t('View orders').'</a></div></div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Buyer protection').'</h3><span class="status offers">'.t('Escrow active').'</span></div>
    <p class="hint">'.t('Every order is escrow-protected — funds release to the seller only after you confirm receipt.').'</p></div>';

} elseif($tab==='orders'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Orders').'</h3><a class="btn btn-o btn-sm" href="/shop">'.t('New order').'</a></div>';
  if(!$orders) dash_empty(t('No orders yet. Place an order from the catalog.'));
  else { echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Items').'</th><th class="r">'.t('Total').'</th><th>'.t('Status').'</th></tr></thead><tbody>';
    foreach($orders as $o){ echo '<tr><td><b>'.htmlspecialchars($o['ref']??'').'</b><div class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</div></td>'.
      '<td class="hint">'.htmlspecialchars($o['items']??'').'</td><td class="r">'.eur($o['total']??0).'</td>'.
      '<td><span class="status open">'.t('In escrow').'</span></td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} elseif($tab==='requests'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Sourcing requests').'</h3><a class="btn btn-p btn-sm" href="/requests#post">＋ '.t('New request').'</a></div>';
  if(!$requests) dash_empty(t('No requests yet. Post what you need on the sourcing board.'));
  else { echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Looking for').'</th><th>'.t('Target').'</th><th>'.t('Status').'</th></tr></thead><tbody>';
    foreach($requests as $r){ echo '<tr><td><b>'.htmlspecialchars($r['ref']??'').'</b></td><td>'.htmlspecialchars($r['title']??'').'<div class="hint">'.htmlspecialchars($r['qty']??'').'</div></td>'.
      '<td>'.htmlspecialchars($r['target']??'').'</td><td><span class="status open">'.t('In queue').'</span></td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} elseif($tab==='offers'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Offers made').'</h3></div>';
  if(!$offers) dash_empty(t('No offers yet. Make an offer on a make-an-offer product.'));
  else { echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Product').'</th><th class="r">'.t('Your offer').'</th><th>'.t('Status').'</th></tr></thead><tbody>';
    foreach($offers as $o){ echo '<tr><td><b>'.htmlspecialchars($o['ref']??'').'</b></td><td>'.htmlspecialchars($o['product']??'').'<div class="hint">'.htmlspecialchars($o['qty']??'').'× SKU '.htmlspecialchars($o['sku']??'').'</div></td>'.
      '<td class="r"><b>'.eur($o['offer_unit']??0).'</b>/u</td><td><span class="status open">'.t('Pending seller').'</span></td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} else { // kyc
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Business verification').'</h3><span class="status offers">✓ '.t('Verified buyer').'</span></div>
    <p class="hint">'.t('Verified buyers see wholesale pricing and can order with escrow protection.').'</p>
    <table class="ctable"><tbody>
    <tr><td>'.t('Company details').'</td><td class="r"><span class="status offers">'.t('Approved').'</span></td></tr>
    <tr><td>'.t('VAT / Tax ID').'</td><td class="r"><span class="status offers">'.t('Approved').'</span></td></tr>
    <tr><td>'.t('Email verified').'</td><td class="r"><span class="status offers">'.t('Approved').'</span></td></tr>
    </tbody></table></div>';
}
dash_close();
require __DIR__.'/inc/foot.php';
