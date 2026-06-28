<?php
require_once __DIR__.'/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Profile save
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='profile') {
    auth_update($_SESSION['uid'], [
        'name'=>trim($_POST['name']??''),'company'=>trim($_POST['company']??''),
        'vat_id'=>trim($_POST['vat_id']??''),'reg_number'=>trim($_POST['reg_number']??''),
        'country'=>trim($_POST['country']??''),'address'=>trim($_POST['address']??''),
        'phone'=>trim($_POST['phone']??''),'website'=>trim($_POST['website']??''),
    ]);
    header('Location: /buyer?tab=profile&saved=1'); exit;
}

require __DIR__.'/inc/products.php';

// ── Upload KYC document ───────────────────────────────────────────────────────
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='upload_doc') {
    $req_id = preg_replace('/[^a-f0-9]/','', $_POST['req_id']??'');
    $file   = $_FILES['doc_file'] ?? null;
    if ($req_id && $file) {
        $ok = auth_upload_doc($_SESSION['uid'], $req_id, $file);
        header('Location: /buyer?tab=kyc&'.($ok?'uploaded=1':'upload_err=1')); exit;
    }
    header('Location: /buyer?tab=kyc'); exit;
}

// Confirm receipt (escrow release)
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='confirm_receipt') {
    $ref = $_POST['ref'] ?? '';
    if ($ref) {
        $st = vestra_read_json('order_statuses.json');
        $st[$ref] = array_merge($st[$ref] ?? [], ['status'=>'completed','confirmed_at'=>date('c')]);
        vestra_write_json('order_statuses.json', $st);
        /* Notify admin + seller */
        require_once __DIR__.'/inc/notify.php';
        $buyerName=''; $orderItems='';
        foreach(vestra_read_csv('orders.csv') as $row){
            if(($row['ref']??'')!==$ref) continue;
            $buyerName = ($row['name']?:$row['company']?:'');
            $orderItems = $row['items']??'';
            break;
        }
        vestra_notify("Order {$ref} — receipt confirmed, escrow released",
          "Buyer confirmed receipt for order {$ref}.\nBuyer: {$buyerName}\nItems: {$orderItems}\n\nEscrow funds due for release to seller.\n\nAdmin: https://vestrasales.com/admin?tab=orders");
        /* Notify seller(s) — match ordered SKUs from order_statuses or orders.csv items field */
        $allListings=vestra_listings();
        $notified=[];
        /* parse "qty x SKU @price | ..." items string from CSV */
        $skus=[];
        foreach(explode('|',$orderItems) as $seg){
            if(preg_match('/\s([A-Z0-9\-]+)\s@/',$seg,$m)) $skus[]=$m[1];
        }
        foreach($allListings as $listing){
            if(empty($listing['seller_uid'])||empty($listing['sku'])) continue;
            if(!in_array($listing['sku'],$skus,true)) continue;
            if(in_array($listing['seller_uid'],$notified,true)) continue;
            foreach(auth_accounts() as $acc){
                if(($acc['id']??'')!==$listing['seller_uid']||empty($acc['email'])) continue;
                vestra_send_mail($acc['email'], "VESTRA — order {$ref} confirmed, payout in progress",
                  "Hello ".($acc['name']?:($acc['company']?:'there')).",\n\nThe buyer has confirmed receipt for order {$ref}. Your payout is being processed.\n\nItems: {$orderItems}\n\nView in your seller dashboard:\nhttps://vestrasales.com/seller?tab=orders\n\n— VESTRA · vestrasales.com");
                $notified[]=$listing['seller_uid'];
                break;
            }
        }
    }
    header('Location: /buyer?tab=orders&confirmed=1'); exit;
}

require __DIR__.'/inc/dash.php';
$PAGE=t('Buyer panel'); $NAV='account'; require __DIR__.'/inc/head.php';

if(!$MEMBER){
  echo '<div class="wrap"><div class="gate" style="margin:48px auto;max-width:460px;text-align:center">
    <h3 style="margin:0 0 6px">'.t('Buyer workspace').'</h3>
    <p style="color:var(--mut);margin:0 0 20px">'.t('Sign in to track your orders, sourcing requests and offers.').'</p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
    <a class="btn btn-p" href="/login?back=/buyer">'.t('Sign in').'</a>
    <a class="btn btn-o" href="/register">'.t('Create account').'</a></div></div></div>';
  require __DIR__.'/inc/foot.php'; exit;
}
$tab=$_GET['tab']??'overview';
$orders=vestra_read_csv('orders.csv');
$requests=vestra_read_csv('requests.csv');
$offers=vestra_read_csv('offers.csv');
$offerResp=vestra_read_json('offer_responses.json');
$orderSt=vestra_read_json('order_statuses.json');

dash_open('buyer',$tab,
  $tab==='orders'?t('My orders'):($tab==='requests'?t('My sourcing requests'):($tab==='offers'?t('My offers'):($tab==='kyc'?t('Verification'):($tab==='profile'?t('My profile'):t('Overview'))))),
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
  if(isset($_GET['confirmed'])) echo '<div class="banner ok">✓ '.t('Receipt confirmed. Escrow funds released to seller.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Orders').'</h3><a class="btn btn-o btn-sm" href="/shop">'.t('New order').'</a></div>';
  if(!$orders) dash_empty(t('No orders yet. Place an order from the catalog.'));
  else {
    echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Items').'</th><th class="r">'.t('Total').'</th><th>'.t('Status').'</th><th></th></tr></thead><tbody>';
    foreach($orders as $o){
      $ref = $o['ref']??'';
      $st  = $orderSt[$ref]['status'] ?? 'pending';
      if ($st==='completed') { $stClass='offers'; $stLabel=t('Completed'); }
      elseif($st==='shipped') { $stClass='open'; $stLabel=t('Shipped — confirm receipt'); }
      else { $stClass='open'; $stLabel=t('In escrow'); }
      $confirmBtn='';
      if($st==='shipped'){
        $confirmBtn='<form method="post" action="/buyer?tab=orders" style="margin-top:4px">
          <input type="hidden" name="_action" value="confirm_receipt">
          <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
          <button class="btn btn-p btn-sm" type="submit">✓ '.t('Confirm receipt').'</button></form>';
      }
      echo '<tr><td><b>'.htmlspecialchars($ref).'</b><div class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</div></td>'.
        '<td class="hint">'.htmlspecialchars($o['items']??'').'</td><td class="r">'.eur($o['total']??0).'</td>'.
        '<td><span class="status '.$stClass.'">'.$stLabel.'</span>'.
        (!empty($orderSt[$ref]['tracking'])?'<div class="hint">'.htmlspecialchars($orderSt[$ref]['tracking']).'</div>':'').'</td>'.
        '<td>'.$confirmBtn.'</td></tr>';
    }
    echo '</tbody></table>';
  }
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
  else {
    echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Product').'</th><th class="r">'.t('Your offer').'</th><th>'.t('Seller response').'</th></tr></thead><tbody>';
    foreach($offers as $o){
      $ref = $o['ref']??'';
      $resp = $offerResp[$ref] ?? null;
      if (!$resp) { $rCell='<span class="status open">'.t('Pending seller').'</span>'; }
      elseif($resp['status']==='accept') { $rCell='<span class="status offers">✓ '.t('Accepted').'</span>'; }
      elseif($resp['status']==='decline') { $rCell='<span class="status">✗ '.t('Declined').'</span>'; }
      else { $rCell='<span class="status open">↩ '.t('Counter').': '.eur($resp['counter_price']??0).'/u</span>'; }
      echo '<tr><td><b>'.htmlspecialchars($ref).'</b><div class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</div></td>'.
        '<td>'.htmlspecialchars($o['product']??'').'<div class="hint">'.htmlspecialchars($o['qty']??'').'× SKU '.htmlspecialchars($o['sku']??'').'</div></td>'.
        '<td class="r"><b>'.eur($o['offer_unit']??0).'</b>/u<div class="hint">'.eur($o['offer_total']??0).' total</div></td>'.
        '<td>'.$rCell.'</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '</div>';

} elseif($tab==='profile') {
  $u = $AUTH_USER ?? [];
  if(isset($_GET['saved'])) echo '<div class="banner ok">✓ '.t('Profile saved.').'</div>';
  ?>
  <div class="panelcard">
    <form method="post" action="/buyer?tab=profile" class="addform">
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
        <div><label><?= t('Firma ID') ?></label><input value="<?= htmlspecialchars($u['id']??'—') ?>" disabled title="<?= htmlspecialchars(t('Your unique VESTRA account ID')) ?>"></div>
      </div>
      <button class="btn btn-p" type="submit"><?= t('Save changes') ?></button>
    </form>
  </div>
  <?php

} else { // kyc
  $kybSt   = $AUTH_USER['kyb_status'] ?? 'pending';
  $docReqs = $AUTH_USER['doc_requests'] ?? [];
  $docTypes= auth_doc_types();
  $kybLabel = $kybSt==='approved'
    ? '<span class="status offers">✓ '.t('Verified').'</span>'
    : ($kybSt==='suspended'
        ? '<span class="status" style="color:var(--bad)">⊘ '.t('Suspended').'</span>'
        : '<span class="status open">'.t('Pending review').'</span>');
  if(isset($_GET['uploaded'])) echo '<div class="banner ok">✓ '.t('Document uploaded — the admin will review it shortly.').'</div>';
  if(isset($_GET['upload_err'])) echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:var(--bad)">'.t('Upload failed. Please check the file type (PDF/JPG/PNG/WebP, max 10 MB) and try again.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Business verification').'</h3>'.$kybLabel.'</div>';
  echo '<p class="hint" style="margin:0 0 16px">'.t('Verified buyers can access wholesale pricing and place orders with escrow protection. Upload your company registration and VAT/tax certificate when requested.').'</p>';
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
        $actionCell = '<form method="post" action="/buyer?tab=kyc" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
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
  echo '</div>';
}
dash_close();
require __DIR__.'/inc/foot.php';
