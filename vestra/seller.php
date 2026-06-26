<?php
require __DIR__.'/inc/products.php'; require __DIR__.'/inc/dash.php';
$PAGE=t('Seller panel'); $NAV='sell'; require __DIR__.'/inc/head.php';

if(!$MEMBER){
  echo '<div class="wrap"><div class="gate" style="margin:48px auto;max-width:460px">
    <h3 style="margin:0 0 6px">'.t('Seller workspace').'</h3>
    <p style="color:var(--mut);margin:0 0 16px">'.t('Sign in to manage your listings, orders and offers.').'</p>
    <a class="btn btn-p" href="/seller?demo_member=1">'.t('Sign in (demo)').'</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit;
}
$tab=$_GET['tab']??'overview';
$listings=vestra_listings();
$orders=vestra_read_csv('orders.csv');
$offers=vestra_read_csv('offers.csv');
$added=isset($_GET['added']);
$cats=vestra_cats();

dash_open('seller',$tab,
  $tab==='add'?t('Add a product'):($tab==='listings'?t('My listings'):($tab==='orders'?t('Orders'):($tab==='offers'?t('Offers received'):($tab==='kyc'?t('Verification'):t('Overview'))))),
  $tab==='overview'?t('Welcome back — here is your activity'):'');

if($tab==='overview'){
  $rev=0; foreach($orders as $o){ $rev+=(float)($o['total']??0); }
  stat_cards([
    ['<span class="acc">'.(count(vestra_demo_products())+count($listings)).'</span>',t('Live listings')],
    [count($orders),t('Orders')],
    [count($offers),t('Offers received')],
    [eur($rev),t('Order value')],
  ]);
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Quick actions').'</h3></div>
    <div class="quickrow">
      <a class="btn btn-p btn-sm" href="/seller?tab=add">＋ '.t('Add a product').'</a>
      <a class="btn btn-o btn-sm" href="/seller?tab=orders">'.t('View orders').'</a>
      <a class="btn btn-o btn-sm" href="/requests">'.t('Browse buyer requests').'</a>
    </div></div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Verification').'</h3><span class="status offers">✓ '.t('Verified seller').'</span></div>
    <p class="hint">'.t('Business KYB complete. Your listings show the “Verified” badge to buyers.').'</p></div>';

} elseif($tab==='add'){
  if($added) echo '<div class="banner ok">✓ '.t('Product added — it is now live in the').' <a class="acc" href="/shop">'.t('catalog').'</a>.</div>';
  ?>
  <div class="panelcard">
    <form method="post" action="/seller-add" class="addform" enctype="multipart/form-data">
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
      <div class="frow">
        <div><label><?= t('Brand') ?> *</label><input name="brand" required placeholder="<?= htmlspecialchars(t('e.g. Lacoste / Your label')) ?>"></div>
        <div><label><?= t('Product name') ?> *</label><input name="name" required placeholder="Classic Piqué Polo"></div>
      </div>
      <div class="frow four">
        <div><label><?= t('Category') ?></label>
          <select name="cat">
            <?php foreach(vestra_all_cats() as $grp=>$items): ?>
              <optgroup label="<?=htmlspecialchars($grp)?>"><?php foreach($items as $c) echo '<option>'.htmlspecialchars($c).'</option>'; ?></optgroup>
            <?php endforeach; ?>
            <option><?= t('Other') ?></option>
          </select></div>
        <div><label>SKU</label><input name="sku" placeholder="<?= htmlspecialchars(t('auto if blank')) ?>"></div>
        <div><label><?= t('Unit') ?></label><select name="unit"><option>pc</option><option>pack</option><option>set</option><option>carton</option></select></div>
        <div><label><?= t('Min order (MOQ)') ?> *</label><input type="number" name="moq" min="1" value="12" required></div>
      </div>

      <label><?= t('Pricing mode') ?> *</label>
      <div class="moderow">
        <label class="moderadio"><input type="radio" name="mode" value="fixed" checked onchange="modeUI()"> <span><?= t('Fixed (tiered)') ?></span></label>
        <label class="moderadio"><input type="radio" name="mode" value="sale" onchange="modeUI()"> <span><?= t('Sale (discount)') ?></span></label>
        <label class="moderadio"><input type="radio" name="mode" value="offer" onchange="modeUI()"> <span><?= t('Make-an-offer') ?></span></label>
      </div>

      <div id="listrow" class="frow" style="display:none">
        <div><label><?= t('List price (original €/unit)') ?></label><input type="number" step="0.01" name="list" placeholder="<?= htmlspecialchars(t('for strikethrough')) ?>"></div>
      </div>

      <label><?= t('Tiered pricing — quantity → unit price (€)') ?></label>
      <div class="tiergrid">
        <div><span class="hint"><?= t('Tier 1 qty') ?></span><input type="number" name="t1min" value="12"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t1price" placeholder="34.00"></div>
        <div><span class="hint"><?= t('Tier 2 qty') ?></span><input type="number" name="t2min" placeholder="60"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t2price" placeholder="29.50"></div>
        <div><span class="hint"><?= t('Tier 3 qty') ?></span><input type="number" name="t3min" placeholder="180"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t3price" placeholder="25.00"></div>
      </div>
      <p class="hint" id="offerhint" style="display:none"><?= t('For make-an-offer, tiers are shown as indicative guidance only.') ?></p>

      <label id="offercheck" style="display:flex;gap:9px;align-items:center;margin:12px 0 0;cursor:pointer">
        <input type="checkbox" name="allow_offers" value="1">
        <span><?= t('Allow buyers to make an offer on this product (they can negotiate alongside the listed price)') ?></span>
      </label>

      <div class="frow">
        <div><label><?= t('Description') ?></label><textarea name="desc" rows="2" placeholder="<?= htmlspecialchars(t('Sizes, colours, condition…')) ?>"></textarea></div>
      </div>
      <div class="frow">
        <div><label><?= t('Product photo') ?> <span class="hint">(JPG / PNG / WebP · ≤5 MB)</span></label>
          <input type="file" name="photo" accept="image/png,image/jpeg,image/webp"></div>
        <div><label><?= t('Line sheet / price list') ?> <span class="hint"><?= t('(Excel or CSV · ≤8 MB · optional)') ?></span></label>
          <input type="file" name="sheet" accept=".xlsx,.xls,.csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"></div>
      </div>
      <div class="frow">
        <div><label><?= t('Origin / authenticity note') ?> *</label><input name="origin" required placeholder="<?= htmlspecialchars(t('e.g. EEA stock · invoice on request')) ?>"></div>
        <div><label><?= t('Seller name') ?></label><input name="seller" value="My Wholesale Co."></div>
      </div>
      <div class="banner info" style="margin:8px 0 0"><?= t('By listing you confirm the goods are <b>genuine</b> and you are <b>entitled to sell</b> them (incl. EEA exhaustion where applicable) — per the Seller Agreement.') ?></div>
      <button class="btn btn-p" type="submit" style="margin-top:14px"><?= t('Publish product') ?></button>
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
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('My listings').'</h3><a class="btn btn-p btn-sm" href="/seller?tab=add">＋ '.t('Add product').'</a></div>';
  echo '<table class="ctable"><thead><tr><th>'.t('Product').'</th><th>'.t('Mode').'</th><th>MOQ</th><th class="r">'.t('From').'</th><th>'.t('Status').'</th></tr></thead><tbody>';
  $all=array_merge($listings, vestra_demo_products());
  foreach($all as $p){ $mine=in_array($p,$listings,true);
    echo '<tr><td><b>'.htmlspecialchars($p['brand']).'</b> — '.htmlspecialchars($p['name']).'<div class="hint">SKU '.htmlspecialchars($p['sku']).($mine?' · '.t('yours'):'').'</div></td>'.
      '<td><span class="modechip '.$p['mode'].'">'.$p['mode'].'</span></td><td>'.$p['moq'].' '.htmlspecialchars($p['unit']).'</td>'.
      '<td class="r">'.($p['mode']==='offer'?'—':eur(vestra_from_price($p))).'</td><td><span class="status offers">'.t('Live').'</span></td></tr>';
  }
  echo '</tbody></table></div>';

} elseif($tab==='orders'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Orders').'</h3></div>';
  if(!$orders) dash_empty(t('No orders yet. Orders placed by buyers appear here.'));
  else { echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Buyer').'</th><th>'.t('Items').'</th><th class="r">'.t('Total').'</th><th>'.t('When').'</th></tr></thead><tbody>';
    foreach($orders as $o){ echo '<tr><td><b>'.htmlspecialchars($o['ref']??'').'</b></td><td>'.htmlspecialchars($o['company']??'').'<div class="hint">'.htmlspecialchars($o['email']??'').'</div></td>'.
      '<td class="hint">'.htmlspecialchars($o['items']??'').'</td><td class="r">'.eur($o['total']??0).'</td><td class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} elseif($tab==='offers'){
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Offers received').'</h3></div>';
  if(!$offers) dash_empty(t('No offers yet. Buyer offers on your make-an-offer items appear here.'));
  else { echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Product').'</th><th>'.t('Buyer').'</th><th class="r">'.t('Offer').'</th><th></th></tr></thead><tbody>';
    foreach($offers as $o){ echo '<tr><td><b>'.htmlspecialchars($o['ref']??'').'</b></td><td>'.htmlspecialchars($o['product']??'').'<div class="hint">'.htmlspecialchars($o['qty']??'').'× SKU '.htmlspecialchars($o['sku']??'').'</div></td>'.
      '<td>'.htmlspecialchars($o['company']??'').'</td><td class="r"><b>'.eur($o['offer_unit']??0).'</b>/u<div class="hint">'.eur($o['offer_total']??0).' total</div></td>'.
      '<td><a class="btn btn-o btn-sm" href="#">'.t('Respond').'</a></td></tr>'; }
    echo '</tbody></table>'; }
  echo '</div>';

} else { // kyc
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Business verification (KYB)').'</h3><span class="status offers">✓ '.t('Verified').'</span></div>
    <table class="ctable"><tbody>
    <tr><td>'.t('Company registration').'</td><td class="r"><span class="status offers">'.t('Approved').'</span></td></tr>
    <tr><td>'.t('VAT / Tax ID').'</td><td class="r"><span class="status offers">'.t('Approved').'</span></td></tr>
    <tr><td>'.t('Beneficial owner ID').'</td><td class="r"><span class="status offers">'.t('Approved').'</span></td></tr>
    <tr><td>'.t('Payout (escrow) account').'</td><td class="r"><span class="status open">'.t('Connect Tazapay').'</span></td></tr>
    </tbody></table>
    <p class="hint">'.t('Verified sellers can list and receive escrow payouts. See the').' <a class="acc" href="/legal?doc=seller">'.t('Seller Agreement').'</a>.</p></div>';
}
dash_close();
require __DIR__.'/inc/foot.php';
