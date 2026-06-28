<?php
require_once __DIR__.'/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Profile save ─────────────────────────────────────────────────────────────
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='profile') {
    auth_update($_SESSION['uid'], [
        'name'=>trim($_POST['name']??''),'company'=>trim($_POST['company']??''),
        'vat_id'=>trim($_POST['vat_id']??''),'reg_number'=>trim($_POST['reg_number']??''),
        'country'=>trim($_POST['country']??''),'address'=>trim($_POST['address']??''),
        'phone'=>trim($_POST['phone']??''),'website'=>trim($_POST['website']??''),
    ]);
    header('Location: /seller?tab=profile&saved=1'); exit;
}

// ── Require products (needed for listing/status helpers) ─────────────────────
require __DIR__.'/inc/products.php';

// ── Delete listing ────────────────────────────────────────────────────────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='delete_listing') {
    $lid = $_POST['lid'] ?? '';
    if ($lid) { vestra_save_listings(array_values(array_filter(vestra_listings(), fn($p) => ($p['id']??'') !== $lid))); }
    header('Location: /seller?tab=listings&deleted=1'); exit;
}

// ── Update listing (text fields; image preserved) ─────────────────────────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='update_listing') {
    $lid = $_POST['lid'] ?? '';
    $one = fn($s) => trim(preg_replace('/\s+/',' ', str_replace(["\r","\n"],' ',(string)$s)));
    if ($lid) {
        $list = vestra_listings();
        $tiers = [];
        foreach([['t1min','t1price'],['t2min','t2price'],['t3min','t3price']] as $pair){
            $min=(int)($_POST[$pair[0]]??0); $price=(float)($_POST[$pair[1]]??0);
            if($min>0&&$price>0) $tiers[]=['min'=>$min,'price'=>round($price,2)];
        }
        usort($tiers, fn($a,$b) => $a['min']<=>$b['min']);
        $moq = max(1,(int)($_POST['moq']??1));
        $mode = in_array($_POST['mode']??'',['fixed','sale','offer'],true) ? $_POST['mode'] : 'fixed';
        if (!$tiers) $tiers=[['min'=>$moq,'price'=>1.00]];
        if ($tiers[0]['min'] > $moq) $tiers[0]['min'] = $moq;
        foreach ($list as &$p) {
            if (($p['id']??'') !== $lid) continue;
            $p['brand']  = $one($_POST['brand']??$p['brand']);
            $p['name']   = $one($_POST['name']??$p['name']);
            $p['cat']    = $one($_POST['cat']??$p['cat']);
            $p['sku']    = $one($_POST['sku']??$p['sku']);
            $p['unit']   = $one($_POST['unit']??$p['unit']);
            $p['moq']    = $moq;
            $p['mode']   = $mode;
            $p['desc']   = $one($_POST['desc']??'');
            $p['origin'] = $one($_POST['origin']??'');
            $p['tiers']  = $tiers;
            if($mode==='sale') $p['list']=round((float)($_POST['list']??0),2);
            $p['offers'] = !empty($_POST['allow_offers']) && $mode!=='offer';
            break;
        }
        vestra_save_listings($list);
    }
    header('Location: /seller?tab=listings&updated=1'); exit;
}

// ── Ship order ────────────────────────────────────────────────────────────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='ship_order') {
    $ref = $_POST['ref'] ?? '';
    if ($ref) {
        $st = vestra_read_json('order_statuses.json');
        $st[$ref] = array_merge($st[$ref] ?? [], ['status'=>'shipped','tracking'=>trim($_POST['tracking']??''),'shipped_at'=>date('c')]);
        vestra_write_json('order_statuses.json', $st);
    }
    header('Location: /seller?tab=orders&shipped=1'); exit;
}

// ── Upload KYB document ───────────────────────────────────────────────────────
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='upload_doc') {
    $req_id = preg_replace('/[^a-f0-9]/','', $_POST['req_id']??'');
    $file   = $_FILES['doc_file'] ?? null;
    if ($req_id && $file) {
        $ok = auth_upload_doc($_SESSION['uid'], $req_id, $file);
        header('Location: /seller?tab=kyc&'.($ok?'uploaded=1':'upload_err=1')); exit;
    }
    header('Location: /seller?tab=kyc'); exit;
}

// ── Respond to offer ──────────────────────────────────────────────────────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='offer_respond') {
    $ref    = trim($_POST['ref'] ?? '');
    $action = $_POST['response'] ?? '';
    $ctr    = round((float)($_POST['counter_price']??0), 2);
    if ($ref && in_array($action, ['accept','decline','counter'], true) && !($action==='counter'&&$ctr<=0)) {
        $rs = vestra_read_json('offer_responses.json');
        $rs[$ref] = ['status'=>$action, 'counter_price'=>$action==='counter'?$ctr:null, 'responded_at'=>date('c')];
        vestra_write_json('offer_responses.json', $rs);
    }
    header('Location: /seller?tab=offers&responded=1'); exit;
}

require __DIR__.'/inc/dash.php';
$PAGE=t('Seller panel'); $NAV='sell'; require __DIR__.'/inc/head.php';

if(!$MEMBER){
  echo '<div class="wrap"><div class="gate" style="margin:48px auto;max-width:460px;text-align:center">
    <h3 style="margin:0 0 6px">'.t('Seller workspace').'</h3>
    <p style="color:var(--mut);margin:0 0 20px">'.t('Sign in to manage your listings, orders and offers.').'</p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
    <a class="btn btn-p" href="/login?back=/seller">'.t('Sign in').'</a>
    <a class="btn btn-o" href="/register">'.t('Create account').'</a></div></div></div>';
  require __DIR__.'/inc/foot.php'; exit;
}

$tab=$_GET['tab']??'overview';
$listings=vestra_listings();
$orders=vestra_read_csv('orders.csv');
$offers=vestra_read_csv('offers.csv');
$offerResp=vestra_read_json('offer_responses.json');
$orderSt=vestra_read_json('order_statuses.json');
$cats=vestra_cats();

$tabTitle = match($tab) {
    'add'      => t('Add a product'),
    'edit'     => t('Edit listing'),
    'listings' => t('My listings'),
    'orders'   => t('Orders'),
    'offers'   => t('Offers received'),
    'kyc'      => t('Verification'),
    'profile'  => t('My profile'),
    default    => t('Overview'),
};
dash_open('seller',$tab, $tabTitle, $tab==='overview'?t('Welcome back — here is your activity'):'');

// ── OVERVIEW ──────────────────────────────────────────────────────────────────
if($tab==='overview'){
  $rev=0; foreach($orders as $o){ $rev+=(float)($o['total']??0); }
  $pendingOffers = count(array_filter($offers, fn($o) => empty($offerResp[$o['ref']??''])));
  $shippedOrders = count(array_filter($orders, fn($o) => ($orderSt[$o['ref']??'']['status']??'')!=='completed'));
  stat_cards([
    ['<span class="acc">'.(count(vestra_demo_products())+count($listings)).'</span>', t('Live listings')],
    [count($orders), t('Orders')],
    ['<span class="'.($pendingOffers?'acc':'').'">'.$pendingOffers.'</span>', t('Offers pending')],
    [eur($rev), t('Order value')],
  ]);
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Quick actions').'</h3></div>
    <div class="quickrow">
      <a class="btn btn-p btn-sm" href="/seller?tab=add">＋ '.t('Add a product').'</a>
      <a class="btn btn-o btn-sm" href="/seller?tab=offers">'.t('Manage offers').'</a>
      <a class="btn btn-o btn-sm" href="/seller?tab=orders">'.t('View orders').'</a>
      <a class="btn btn-o btn-sm" href="/requests">'.t('Browse buyer requests').'</a>
    </div></div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Verification').'</h3>';
  $kybSt = $AUTH_USER['kyb_status']??'pending';
  echo $kybSt==='approved'?'<span class="status offers">✓ '.t('Verified seller').'</span>':'<span class="status open">'.t('Pending review').'</span>';
  echo '</div><p class="hint">'.t('Business KYB complete. Your listings show the "Verified" badge to buyers.').'</p></div>';

// ── ADD PRODUCT ───────────────────────────────────────────────────────────────
} elseif($tab==='add'){
  $added=isset($_GET['added']);
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
      <label style="display:flex;gap:9px;align-items:center;margin:10px 0 0;cursor:pointer">
        <input type="checkbox" name="group_enable" value="1" onchange="groupUI()">
        <span><?= t('Open this product for <b>group buying</b> — let small buyers pool their orders to reach your MOQ together, then unlock your best tier price') ?></span>
      </label>
      <div id="grouprow" class="frow" style="display:none;margin-top:8px">
        <div><label><?= t('Group target (qty to unlock)') ?></label><input type="number" name="group_target" min="1" placeholder="<?= htmlspecialchars(t('blank = your top tier qty')) ?>"></div>
        <div><label><?= t('Pool open for (days)') ?></label><input type="number" name="group_days" min="1" max="90" value="14"></div>
      </div>
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
        <div><label><?= t('Seller name') ?></label><input name="seller" value="<?= htmlspecialchars($AUTH_USER['company']??'My Wholesale Co.') ?>"></div>
      </div>
      <div class="banner info" style="margin:8px 0 0"><?= t('By listing you confirm the goods are <b>genuine</b> and you are <b>entitled to sell</b> them (incl. EEA exhaustion where applicable) — per the Seller Agreement.') ?></div>
      <button class="btn btn-p" type="submit" style="margin-top:14px"><?= t('Publish product') ?></button>
    </form>
  </div>
  <script>
  function modeUI(){ var m=document.querySelector('input[name=mode]:checked').value;
    document.getElementById('listrow').style.display=m==='sale'?'grid':'none';
    document.getElementById('offerhint').style.display=m==='offer'?'block':'none'; }
  function groupUI(){ document.getElementById('grouprow').style.display=document.querySelector('input[name=group_enable]').checked?'grid':'none'; }
  modeUI(); groupUI();
  </script>
  <?php

// ── EDIT LISTING ──────────────────────────────────────────────────────────────
} elseif($tab==='edit'){
  $lid = $_GET['lid'] ?? '';
  $ep = vestra_listing_by_id($lid);
  if (!$ep) { echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">'.t('Listing not found.').'</div>'; }
  else {
  $t1=$ep['tiers'][0]??null; $t2=$ep['tiers'][1]??null; $t3=$ep['tiers'][2]??null;
  ?>
  <?php if(isset($_GET['updated'])): ?><div class="banner ok">✓ <?= t('Listing updated.') ?></div><?php endif; ?>
  <div class="panelcard">
    <p class="hint" style="margin:0 0 14px"><?= t('Edit text fields below. To change the product image, delete and re-add the listing.') ?></p>
    <form method="post" action="/seller?tab=listings" class="addform">
      <input type="hidden" name="_action" value="update_listing">
      <input type="hidden" name="lid" value="<?= htmlspecialchars($lid) ?>">
      <div class="frow">
        <div><label><?= t('Brand') ?> *</label><input name="brand" required value="<?= htmlspecialchars($ep['brand']??'') ?>"></div>
        <div><label><?= t('Product name') ?> *</label><input name="name" required value="<?= htmlspecialchars($ep['name']??'') ?>"></div>
      </div>
      <div class="frow four">
        <div><label><?= t('Category') ?></label>
          <select name="cat">
            <?php foreach(vestra_all_cats() as $grp=>$items): ?>
              <optgroup label="<?=htmlspecialchars($grp)?>"><?php foreach($items as $c): ?>
                <option<?= $c===($ep['cat']??'')?'  selected':'' ?>><?=htmlspecialchars($c)?></option>
              <?php endforeach; ?></optgroup>
            <?php endforeach; ?>
            <option<?= !in_array($ep['cat']??'',array_merge(...array_values(vestra_all_cats())))?'  selected':'' ?>><?= t('Other') ?></option>
          </select></div>
        <div><label>SKU</label><input name="sku" value="<?= htmlspecialchars($ep['sku']??'') ?>"></div>
        <div><label><?= t('Unit') ?></label>
          <select name="unit">
            <?php foreach(['pc','pack','set','carton'] as $u): ?>
              <option<?= ($ep['unit']??'pc')===$u?' selected':'' ?>><?=$u?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label><?= t('Min order (MOQ)') ?> *</label><input type="number" name="moq" min="1" value="<?= (int)($ep['moq']??1) ?>" required></div>
      </div>
      <label><?= t('Pricing mode') ?></label>
      <div class="moderow">
        <?php foreach(['fixed'=>t('Fixed (tiered)'),'sale'=>t('Sale (discount)'),'offer'=>t('Make-an-offer')] as $mv=>$ml): ?>
          <label class="moderadio"><input type="radio" name="mode" value="<?=$mv?>" <?=($ep['mode']??'fixed')===$mv?'checked':''?> onchange="modeUI()"> <span><?=$ml?></span></label>
        <?php endforeach; ?>
      </div>
      <div id="listrow" class="frow" style="display:<?=($ep['mode']??'')!=='sale'?'none':'grid'?>">
        <div><label><?= t('List price (original €/unit)') ?></label><input type="number" step="0.01" name="list" value="<?= number_format((float)($ep['list']??0),2,'.','') ?>"></div>
      </div>
      <label><?= t('Tiered pricing') ?></label>
      <div class="tiergrid">
        <div><span class="hint"><?= t('Tier 1 qty') ?></span><input type="number" name="t1min" value="<?= $t1?$t1['min']:'' ?>"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t1price" value="<?= $t1?number_format($t1['price'],2,'.',''):'' ?>"></div>
        <div><span class="hint"><?= t('Tier 2 qty') ?></span><input type="number" name="t2min" value="<?= $t2?$t2['min']:'' ?>"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t2price" value="<?= $t2?number_format($t2['price'],2,'.',''):'' ?>"></div>
        <div><span class="hint"><?= t('Tier 3 qty') ?></span><input type="number" name="t3min" value="<?= $t3?$t3['min']:'' ?>"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t3price" value="<?= $t3?number_format($t3['price'],2,'.',''):'' ?>"></div>
      </div>
      <p class="hint" id="offerhint" style="display:<?=($ep['mode']??'')!=='offer'?'none':'block'?>"><?= t('For make-an-offer, tiers are shown as indicative guidance only.') ?></p>
      <div class="frow">
        <div><label><?= t('Description') ?></label><textarea name="desc" rows="2"><?= htmlspecialchars($ep['desc']??'') ?></textarea></div>
        <div><label><?= t('Origin / authenticity note') ?> *</label><input name="origin" required value="<?= htmlspecialchars($ep['origin']??'') ?>"></div>
      </div>
      <label style="display:flex;gap:9px;align-items:center;margin:10px 0 14px;cursor:pointer">
        <input type="checkbox" name="allow_offers" value="1" <?= !empty($ep['offers'])?'checked':'' ?>>
        <span><?= t('Allow buyers to make an offer on this product') ?></span>
      </label>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-p" type="submit"><?= t('Save changes') ?></button>
        <a class="btn btn-o" href="/seller?tab=listings"><?= t('Cancel') ?></a>
      </div>
    </form>
  </div>
  <?php }
  ?><script>
  function modeUI(){var m=document.querySelector('input[name=mode]:checked').value;
    document.getElementById('listrow').style.display=m==='sale'?'grid':'none';
    document.getElementById('offerhint').style.display=m==='offer'?'block':'none';}
  </script><?php

// ── MY LISTINGS ───────────────────────────────────────────────────────────────
} elseif($tab==='listings'){
  if(isset($_GET['deleted'])) echo '<div class="banner ok">✓ '.t('Listing deleted.').'</div>';
  if(isset($_GET['updated'])) echo '<div class="banner ok">✓ '.t('Listing updated.').'</div>';
  if(isset($_GET['pending'])) echo '<div class="banner" style="background:rgba(240,192,96,.12);border:1px solid rgba(240,192,96,.35);color:#f0c060;margin-bottom:12px">⏳ '.t('Your listing has been submitted and is awaiting admin approval. It will appear in the catalog once approved.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('My listings').'</h3><a class="btn btn-p btn-sm" href="/seller?tab=add">＋ '.t('Add product').'</a></div>';
  echo '<table class="ctable"><thead><tr><th>'.t('Product').'</th><th>'.t('Mode').'</th><th>MOQ</th><th class="r">'.t('From').'</th><th>'.t('Status').'</th><th></th></tr></thead><tbody>';
  $demoIds = array_column(vestra_demo_products(), 'id');
  $all = array_merge($listings, vestra_demo_products());
  foreach($all as $p){
    $isDemo = in_array($p['id']??'', $demoIds);
    echo '<tr><td><b>'.htmlspecialchars($p['brand']).'</b> — '.htmlspecialchars($p['name']).'<div class="hint">SKU '.htmlspecialchars($p['sku']).($isDemo?' · demo':'').'</div></td>'.
      '<td><span class="modechip '.$p['mode'].'">'.$p['mode'].'</span></td><td>'.$p['moq'].' '.htmlspecialchars($p['unit']).'</td>'.
      '<td class="r">'.($p['mode']==='offer'?'—':eur(vestra_from_price($p))).'</td>'.
      '<td>'.match($p['status']??'approved'){'pending'=>'<span class="status open">⏳ '.t('Pending approval').'</span>','rejected'=>'<span class="status" style="background:rgba(239,154,154,.12);color:var(--bad);border:1px solid rgba(239,154,154,.3)">✗ '.t('Rejected').'</span>',default=>'<span class="status offers">✓ '.t('Live').'</span>'}.'</td>'.
      '<td class="r" style="white-space:nowrap">';
    if (!$isDemo) {
      echo '<a class="btn btn-o btn-sm" href="/seller?tab=edit&lid='.urlencode($p['id']).'">'.t('Edit').'</a> ';
      echo '<form method="post" action="/seller?tab=listings" style="display:inline">
        <input type="hidden" name="_action" value="delete_listing">
        <input type="hidden" name="lid" value="'.htmlspecialchars($p['id']).'">
        <button class="btn btn-o btn-sm" type="submit" style="color:var(--bad);border-color:rgba(239,154,154,.3)" onclick="return confirm(\''.htmlspecialchars(t('Delete this listing?')).'\')">'.t('Delete').'</button></form>';
    }
    echo '</td></tr>';
  }
  echo '</tbody></table></div>';

// ── ORDERS ────────────────────────────────────────────────────────────────────
} elseif($tab==='orders'){
  if(isset($_GET['shipped'])) echo '<div class="banner ok">✓ '.t('Order marked as shipped.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Orders').'</h3></div>';
  if(!$orders) dash_empty(t('No orders yet. Orders placed by buyers appear here.'));
  else {
    echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Buyer').'</th><th>'.t('Items').'</th><th class="r">'.t('Total').'</th><th>'.t('Status').'</th><th></th></tr></thead><tbody>';
    foreach($orders as $o){
      $ref = $o['ref']??'';
      $st  = $orderSt[$ref]['status'] ?? 'pending';
      $stClass = $st==='completed'?'offers':($st==='shipped'?'offers':'open');
      $stLabel  = $st==='completed'?t('Completed'):($st==='shipped'?t('Shipped'):t('In escrow'));
      echo '<tr><td><b>'.htmlspecialchars($ref).'</b><div class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</div></td>'.
        '<td>'.htmlspecialchars($o['company']??'').'<div class="hint">'.htmlspecialchars($o['email']??'').'</div></td>'.
        '<td class="hint">'.htmlspecialchars($o['items']??'').'</td>'.
        '<td class="r">'.eur($o['total']??0).'</td>'.
        '<td><span class="status '.$stClass.'">'.$stLabel.'</span>'.
          ($st==='shipped'&&!empty($orderSt[$ref]['tracking'])?'<div class="hint">'.htmlspecialchars($orderSt[$ref]['tracking']).'</div>':'').'</td>'.
        '<td>';
      if ($st==='pending') {
        echo '<details class="respdetails"><summary class="btn btn-p btn-sm">🚚 '.t('Ship').'</summary>
          <form method="post" action="/seller?tab=orders" class="shipform">
            <input type="hidden" name="_action" value="ship_order">
            <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
            <input name="tracking" placeholder="'.htmlspecialchars(t('Tracking number (optional)')).'">
            <button class="btn btn-p btn-sm" type="submit">'.t('Mark shipped').'</button>
          </form></details>';
      }
      echo '</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '</div>';

// ── OFFERS RECEIVED ───────────────────────────────────────────────────────────
} elseif($tab==='offers'){
  if(isset($_GET['responded'])) echo '<div class="banner ok">✓ '.t('Response sent to buyer.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Offers received').'</h3></div>';
  if(!$offers) dash_empty(t('No offers yet. Buyer offers on your make-an-offer items appear here.'));
  else {
    echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Product').'</th><th>'.t('Buyer').'</th><th class="r">'.t('Offer').'</th><th>'.t('Status').'</th><th></th></tr></thead><tbody>';
    foreach($offers as $o){
      $ref = $o['ref']??'';
      $resp = $offerResp[$ref] ?? null;
      if($resp){
        $rSt = $resp['status']; $rsClass='offers'; $rsLabel='✓ '.t('Accepted');
        if($rSt==='decline'){$rsClass=''; $rsLabel='✗ '.t('Declined');}
        if($rSt==='counter'){$rsClass='open'; $rsLabel='↩ '.t('Counter').': '.eur($resp['counter_price']??0);}
        $stCell='<span class="status '.$rsClass.'">'.$rsLabel.'</span>';
        $actCell='<span class="hint">'.substr($resp['responded_at']??'',0,10).'</span>';
      } else {
        $stCell='<span class="status open">'.t('Pending').'</span>';
        $actCell='<details class="respdetails"><summary class="btn btn-o btn-sm">'.t('Respond').'</summary>
          <form method="post" action="/seller?tab=offers" class="respform">
            <input type="hidden" name="_action" value="offer_respond">
            <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
            <div class="resprow">
              <button class="btn btn-sm" name="response" value="accept" type="submit" style="background:rgba(122,214,160,.15);border-color:rgba(122,214,160,.4);color:var(--ok)">✓ '.t('Accept').'</button>
              <button class="btn btn-sm" name="response" value="decline" type="submit" style="background:rgba(239,154,154,.1);border-color:rgba(239,154,154,.35);color:var(--bad)">✗ '.t('Decline').'</button>
            </div>
            <div class="resprow" style="margin-top:8px;gap:6px">
              <input type="number" step="0.01" name="counter_price" placeholder="'.htmlspecialchars(t('Counter €/unit')).'" style="max-width:140px">
              <button class="btn btn-o btn-sm" name="response" value="counter" type="submit">↩ '.t('Counter offer').'</button>
            </div>
          </form></details>';
      }
      echo '<tr><td><b>'.htmlspecialchars($ref).'</b><div class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</div></td>'.
        '<td>'.htmlspecialchars($o['product']??'').'<div class="hint">'.htmlspecialchars($o['qty']??'').'× SKU '.htmlspecialchars($o['sku']??'').'</div></td>'.
        '<td>'.htmlspecialchars($o['company']??'').'<div class="hint">'.htmlspecialchars($o['email']??'').'</div></td>'.
        '<td class="r"><b>'.eur($o['offer_unit']??0).'</b>/u<div class="hint">'.eur($o['offer_total']??0).' total</div></td>'.
        '<td>'.$stCell.'</td><td>'.$actCell.'</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '</div>';

// ── VERIFICATION / KYB ────────────────────────────────────────────────────────
} elseif($tab==='kyc'){
  $kybSt    = $AUTH_USER['kyb_status'] ?? 'pending';
  $docReqs  = $AUTH_USER['doc_requests'] ?? [];
  $docTypes = auth_doc_types();
  $kybLabel = $kybSt==='approved'
    ? '<span class="status offers">✓ '.t('Verified').'</span>'
    : ($kybSt==='suspended'
        ? '<span class="status" style="color:var(--bad)">⊘ '.t('Suspended').'</span>'
        : '<span class="status open">'.t('Pending review').'</span>');
  if(isset($_GET['uploaded'])) echo '<div class="banner ok">✓ '.t('Document uploaded — the admin will review it shortly.').'</div>';
  if(isset($_GET['upload_err'])) echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:var(--bad)">'.t('Upload failed. Please check the file type (PDF/JPG/PNG/WebP, max 10 MB) and try again.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Business verification (KYB)').'</h3>'.$kybLabel.'</div>';
  echo '<p class="hint" style="margin:0 0 16px">'.t('Upload the required documents below. New sellers must provide: company registration, VAT/tax certificate, government-issued ID, and an authorization letter if you are not the company director.').' '.t('See the').' <a class="acc" href="/legal?doc=seller">'.t('Seller Agreement').'</a>.</p>';

  if(!$docReqs){
    echo '<div class="empty">'.t('No document requests yet. The admin will request the required documents — you will see upload buttons here.').'</div>';
  } else {
    echo '<table class="ctable"><thead><tr><th>'.t('Document').'</th><th>'.t('Note from admin').'</th><th>'.t('Status').'</th><th></th></tr></thead><tbody>';
    foreach($docReqs as $r){
      $type  = $docTypes[$r['type']??''] ?? ucfirst(str_replace('_',' ',$r['type']??''));
      $st    = $r['status'] ?? 'requested';
      $stHtml = match($st){
        'approved'  => '<span class="status offers">✓ '.t('Approved').'</span>',
        'rejected'  => '<span class="status" style="color:var(--bad);background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3)">✗ '.t('Rejected').'</span>',
        'uploaded'  => '<span class="status open">⏳ '.t('Under review').'</span>',
        default     => '<span class="status open">📎 '.t('Upload required').'</span>',
      };
      $note = htmlspecialchars($r['admin_note'] ?? $r['note'] ?? '');
      $actionCell = '';
      if($st==='requested'||$st==='rejected'){
        $actionCell = '<form method="post" action="/seller?tab=kyc" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="_action" value="upload_doc">
          <input type="hidden" name="req_id" value="'.htmlspecialchars($r['id']).'">
          <input type="file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required style="font-size:12px;max-width:200px">
          <button class="btn btn-p btn-sm" type="submit">'.t('Upload').'</button>
        </form>';
      } elseif($st==='uploaded'){
        $actionCell = '<span class="hint">'.substr($r['uploaded_at']??'',0,10).'</span>';
      } elseif($st==='approved'){
        $actionCell = '<span class="hint">'.substr($r['reviewed_at']??'',0,10).'</span>';
      }
      echo '<tr><td><b>'.htmlspecialchars($type).'</b><div class="hint">'.t('Requested').': '.htmlspecialchars(substr($r['requested_at']??'',0,10)).'</div></td>'.
        '<td class="hint">'.($note?$note:'—').'</td>'.
        '<td>'.$stHtml.'</td>'.
        '<td>'.$actionCell.'</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '<div class="hint" style="margin-top:16px;padding-top:12px;border-top:1px solid var(--brd)">'.t('Payout account').' · <span class="status open">'.t('Connect Tazapay — coming soon').'</span></div>';
  echo '</div>';

// ── PROFILE ───────────────────────────────────────────────────────────────────
} else { // profile
  $u = $AUTH_USER ?? [];
  if(isset($_GET['saved'])) echo '<div class="banner ok">✓ '.t('Profile saved.').'</div>';
  ?>
  <div class="panelcard">
    <form method="post" action="/seller?tab=profile" class="addform">
      <input type="hidden" name="_action" value="profile">
      <p class="hint" style="margin:0 0 16px"><?= t('Update your company details. Email cannot be changed here.') ?></p>
      <div class="authsect"><?= t('Personal info') ?></div>
      <div class="frow">
        <div><label><?= t('Full name') ?></label><input name="name" value="<?= htmlspecialchars($u['name']??'') ?>" placeholder="Anna Müller"></div>
        <div><label><?= t('Email address') ?></label><input type="email" value="<?= htmlspecialchars($u['email']??'') ?>" disabled></div>
      </div>
      <div class="authsect"><?= t('Company info') ?></div>
      <div class="frow">
        <div><label><?= t('Company name') ?></label><input name="company" value="<?= htmlspecialchars($u['company']??'') ?>" placeholder="Company GmbH"></div>
        <div><label><?= t('VAT / Tax ID') ?></label><input name="vat_id" value="<?= htmlspecialchars($u['vat_id']??'') ?>" placeholder="DE123456789"></div>
      </div>
      <div class="frow">
        <div><label><?= t('Registration number') ?></label><input name="reg_number" value="<?= htmlspecialchars($u['reg_number']??'') ?>" placeholder="HRB 12345"></div>
        <div><label><?= t('Country') ?></label><input name="country" value="<?= htmlspecialchars($u['country']??'') ?>" placeholder="DE"></div>
      </div>
      <div class="frow">
        <div><label><?= t('Address') ?></label><input name="address" value="<?= htmlspecialchars($u['address']??'') ?>" placeholder="Hauptstraße 1, 10115 Berlin"></div>
        <div><label><?= t('Phone') ?></label><input name="phone" value="<?= htmlspecialchars($u['phone']??'') ?>" placeholder="+49 30 12345678"></div>
      </div>
      <div class="frow">
        <div><label><?= t('Website') ?></label><input name="website" value="<?= htmlspecialchars($u['website']??'') ?>" placeholder="https://company.com"></div>
        <div><label><?= t('Firma ID') ?></label><input value="<?= htmlspecialchars($u['id']??'—') ?>" disabled></div>
      </div>
      <button class="btn btn-p" type="submit"><?= t('Save changes') ?></button>
    </form>
  </div>
  <?php
}
dash_close();
require __DIR__.'/inc/foot.php';
