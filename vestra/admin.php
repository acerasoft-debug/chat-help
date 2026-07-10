<?php
/** VESTRA — Admin Panel */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/promos.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/invoice.php';
if(session_status()===PHP_SESSION_NONE) session_start();

$PASS   = (string)vestra_cfg('admin_pass','');
$locked = ($PASS==='');

if(isset($_GET['logout'])){ unset($_SESSION['vadmin'],$_SESSION['vadmin_csrf']); header('Location: /admin'); exit; }
$err=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pass'])){
  $tkey='admin|'.($_SERVER['REMOTE_ADDR']??'');
  if(auth_throttled($tkey)){ $err=true; }
  elseif(!$locked && hash_equals($PASS,(string)$_POST['pass'])){ auth_throttle_clear($tkey); $_SESSION['vadmin']=true; header('Location: /admin'); exit; }
  else { auth_throttle_hit($tkey); sleep(1); $err=true; }
}
$authed=!empty($_SESSION['vadmin']);
if($authed && empty($_SESSION['vadmin_csrf'])) $_SESSION['vadmin_csrf']=bin2hex(random_bytes(16));

/* Hidden CSRF field — include in EVERY admin POST form. */
function csrfField(): string {
  return '<input type="hidden" name="_csrf" value="'.htmlspecialchars($_SESSION['vadmin_csrf']??'').'">';
}

// ── POST actions ───────────────────────────────────────────────────────────────
if($authed && $_SERVER['REQUEST_METHOD']==='POST'){
  // Every mutating action must carry the session CSRF token — blocks cross-site form posts.
  if(!hash_equals($_SESSION['vadmin_csrf']??'', (string)($_POST['_csrf']??''))){
    header('Location: /admin?msg=csrf_fail'); exit;
  }
  $act=$_POST['_action']??'';

  if($act==='approve_listing'){
    $lid=$_POST['lid']??''; $note=trim($_POST['note']??'');
    $all=vestra_listings();
    foreach($all as &$p){ if(($p['id']??'')===$lid){ $p['status']='approved'; if($note) $p['admin_note']=$note; break; } }
    vestra_save_listings($all);
    header('Location: /admin?tab=approvals&msg=approved'); exit;
  }
  if($act==='reject_listing'){
    $lid=$_POST['lid']??''; $note=trim($_POST['note']??'');
    $all=vestra_listings();
    foreach($all as &$p){ if(($p['id']??'')===$lid){ $p['status']='rejected'; if($note) $p['admin_note']=$note; break; } }
    vestra_save_listings($all);
    header('Location: /admin?tab=approvals&msg=rejected'); exit;
  }
  if($act==='approve_kyb'){
    auth_update($_POST['uid']??'',['kyb_status'=>'approved','status'=>'active']);
    header('Location: /admin?tab=users&msg=kyb_ok'); exit;
  }
  if($act==='suspend_account'){
    auth_update($_POST['uid']??'',['status'=>'suspended']);
    header('Location: /admin?tab=users&msg=suspended'); exit;
  }
  if($act==='activate_account'){
    auth_update($_POST['uid']??'',['status'=>'active']);
    header('Location: /admin?tab=users&msg=activated'); exit;
  }
  if($act==='resend_verify'){
    $uid=$_POST['uid']??'';
    foreach(auth_accounts() as $a){
      if(($a['id']??'')!==$uid) continue;
      if(($a['status']??'')==='pending_email' && !empty($a['email_token'])){
        require_once __DIR__.'/inc/notify.php';
        $lang=substr($a['lang']??'en',0,2);
        [$subj,$body]=vestra_verify_text($lang,$a['name']?:($a['company']?:'there'),$a['email_token']);
        vestra_send_mail($a['email'],$subj,$body);
      }
      break;
    }
    header('Location: /admin?tab=users&msg=verify_resent'); exit;
  }
  if($act==='manual_verify'){
    auth_update($_POST['uid']??'',['email_verified'=>true,'email_token'=>'','status'=>'pending']);
    header('Location: /admin?tab=users&msg=manual_verified'); exit;
  }
  if($act==='grant_badge'){
    auth_update($_POST['uid']??'',['verified_badge'=>true,'verification_status'=>'verified']);
    header('Location: /admin?tab=users&msg=badge_granted'); exit;
  }
  if($act==='revoke_badge'){
    auth_update($_POST['uid']??'',['verified_badge'=>false,'verification_status'=>'none']);
    header('Location: /admin?tab=users&msg=badge_revoked'); exit;
  }
  if($act==='request_doc'){
    auth_request_doc($_POST['uid']??'', $_POST['doc_type']??'', trim($_POST['note']??''));
    header('Location: /admin?tab=documents&uid='.urlencode($_POST['uid']??'').'&msg=doc_requested'); exit;
  }
  if($act==='review_doc'){
    auth_review_doc($_POST['uid']??'', $_POST['req_id']??'', $_POST['status']??'', trim($_POST['admin_note']??''));
    header('Location: /admin?tab=documents&uid='.urlencode($_POST['uid']??'').'&msg=doc_reviewed'); exit;
  }
  if($act==='delete_listing'){
    $lid=$_POST['lid']??'';
    if($lid) vestra_save_listings(array_values(array_filter(vestra_listings(),fn($p)=>($p['id']??'')!==$lid)));
    header('Location: /admin?tab=listings&msg=deleted'); exit;
  }
  if($act==='order_status'){
    $ref=$_POST['ref']??''; $st=$_POST['status']??'';
    if($ref && in_array($st,['pending','paid','shipped','completed'],true)){
      $all=vestra_read_json('order_statuses.json');
      $prev=$all[$ref]['status']??'pending';
      $all[$ref]=array_merge($all[$ref]??[],['status'=>$st,'tracking'=>trim($_POST['tracking']??''),'updated_at'=>date('c')]);
      vestra_write_json('order_statuses.json',$all);
      /* Invoice flow: on "paid", tell the buyer + the sellers whose SKUs are in the order */
      if($st==='paid' && $prev!=='paid'){
        $orderRow=null;
        foreach(vestra_read_csv('orders.csv') as $row){ if(($row['ref']??'')===$ref){ $orderRow=$row; break; } }
        if($orderRow){
          require_once __DIR__.'/inc/notify.php';
          if(!empty($orderRow['email'])){
            vestra_send_mail($orderRow['email'], "VESTRA — payment received for order {$ref}",
              "Hello ".($orderRow['name']?:($orderRow['company']?:'there')).",\n\nWe have received your invoice payment for order {$ref}. The seller is preparing your shipment — you'll get another email with tracking once it ships.\n\nTrack your order: https://vestrasales.com/buyer?tab=orders\n\n— VESTRA · vestrasales.com");
          }
          $notified=[];
          foreach(vestra_parse_order_items($orderRow['items']??'') as $it){
            $l=vestra_listing_by_sku($it['sku']); $sid=$l['seller_uid']??'';
            if($sid==='' || in_array($sid,$notified,true)) continue;
            $notified[]=$sid;
            foreach(auth_accounts() as $acc){
              if(($acc['id']??'')!==$sid || empty($acc['email'])) continue;
              vestra_send_mail($acc['email'], "VESTRA — order {$ref} is paid, please ship",
                "Hello ".($acc['name']?:($acc['company']?:'there')).",\n\nThe invoice for order {$ref} has been paid. Please ship the goods and mark the order as shipped in your panel (with tracking if available):\nhttps://vestrasales.com/seller?tab=orders\n\n— VESTRA · vestrasales.com");
              break;
            }
          }
        }
      }
    }
    header('Location: /admin?tab=orders&msg=status_ok'); exit;
  }
  if($act==='create_promo'){ promo_create($_POST); header('Location: /admin?tab=marketing&msg=promo_ok'); exit; }
  if($act==='delete_promo'){
    $all=promo_all(); unset($all[strtoupper($_POST['del_code']??'')]); promo_save($all);
    header('Location: /admin?tab=marketing&msg=promo_del'); exit;
  }
  if($act==='toggle_promo'){
    $all=promo_all(); $k=strtoupper($_POST['toggle_code']??'');
    if(isset($all[$k])){ $all[$k]['active']=!($all[$k]['active']??true); promo_save($all); }
    header('Location: /admin?tab=marketing&msg=promo_toggled'); exit;
  }
}

// ── Document file download (admin only) ───────────────────────────────────────
if($authed && isset($_GET['dl_doc'])){
  $uid  = preg_replace('/[^a-f0-9]/','', $_GET['uid']??'');
  $file = basename($_GET['dl_doc']??'');
  if($uid && $file){
    $path = auth_doc_file_path($uid, $file);
    if(is_readable($path)){
      $ext  = strtolower(pathinfo($file,PATHINFO_EXTENSION));
      $mime = match($ext){ 'pdf'=>'application/pdf','jpg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp',default=>'application/octet-stream' };
      header('Content-Type: '.$mime);
      header('Content-Disposition: inline; filename="'.addslashes($file).'"');
      readfile($path); exit;
    }
  }
  http_response_code(404); echo 'File not found'; exit;
}

// ── CSV download ───────────────────────────────────────────────────────────────
if($authed && isset($_GET['dl'])){
  $map=['signups'=>'signups.csv','orders'=>'orders.csv','offers'=>'offers.csv','requests'=>'requests.csv','groups'=>'groups.csv','request_offers'=>'request_offers.csv'];
  $f=$map[$_GET['dl']]??null; $path=$f?vestra_data_dir().'/'.$f:'';
  if($f && is_readable($path)){ header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="vestra-'.$f.'"'); readfile($path); exit; }
  http_response_code(404); echo 'No data'; exit;
}

// ── Helper functions ───────────────────────────────────────────────────────────
function abadge(string $t, string $c='#888'): string {
  return '<span style="display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:'.$c.'22;color:'.$c.';border:1px solid '.$c.'44">'.htmlspecialchars($t).'</span>';
}
function kybBadge(string $s): string {
  return match($s){ 'approved'=>abadge('✓ Verified','#7ad6a0'),'suspended'=>abadge('⊘ Suspended','#ef9a9a'),default=>abadge('⏳ Pending','#f0c060') };
}
function docBadge(string $s): string {
  return match($s){ 'approved'=>abadge('✓ Approved','#7ad6a0'),'rejected'=>abadge('✗ Rejected','#ef9a9a'),'uploaded'=>abadge('📤 Review','#c9a86a'),'requested'=>abadge('📋 Requested','#8ab4f8'),default=>abadge('—','#555') };
}
function orderBadge(string $s): string {
  return match($s){ 'completed'=>abadge('✓ Completed','#7ad6a0'),'shipped'=>abadge('🚚 Shipped','#c9a86a'),'paid'=>abadge('💶 Paid — to ship','#8fb7e8'),default=>abadge('⏳ Awaiting payment','#888') };
}
function typePill(string $t): string {
  $c=$t==='seller'?'#c9a86a':'#8ab4f8'; $b=$t==='seller'?'rgba(201,168,106,.15)':'rgba(138,180,248,.15)';
  return '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:'.$b.';color:'.$c.'">'.htmlspecialchars($t).'</span>';
}
function memberBadge(string $tier, string $status): string {
  if($tier===''&&($status===''||$status==='none')) return '<span style="color:#555;font-size:11px">—</span>';
  $tc=['starter'=>'#8ab4f8','pro'=>'#c9a86a','premium'=>'#f0c060'][$tier]??'#888';
  $tl=$tier?ucfirst($tier):'';
  $sc=match($status){'active'=>'#7ad6a0','trialing'=>'#f0c060','past_due'=>'#ef9a9a','canceled'=>'#888',default=>'#555'};
  $sl=match($status){'active'=>'Active','trialing'=>'Trial','past_due'=>'Past due','canceled'=>'Canceled',default=>'—'};
  return ($tl?abadge($tl,$tc):'').'<div style="margin-top:3px">'.abadge($sl,$sc).'</div>';
}
function fBtn(string $label, string $act, array $fields, string $style='', string $confirm=''): string {
  $oc=$confirm?' onclick="return confirm(\''.htmlspecialchars(addslashes($confirm)).'\')"':'';
  $h='<form method="post" style="display:inline">';
  $h.=csrfField();
  $h.='<input type="hidden" name="_action" value="'.htmlspecialchars($act).'">';
  foreach($fields as $k=>$v) $h.='<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';
  $h.='<button type="submit" class="abtn"'.$oc.' style="'.htmlspecialchars($style).'">'.htmlspecialchars($label).'</button></form> ';
  return $h;
}
function arow(array $cells, bool $head=false): string {
  $tag=$head?'th':'td';
  return '<tr>'.implode('',array_map(fn($c)=>'<'.$tag.' class="ac">'.$c.'</'.$tag.'>',$cells)).'</tr>';
}
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>VESTRA Admin</title>
<link rel="stylesheet" href="/inc/style.css">
<style>
:root{--sb:220px}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--ink);font-family:'Inter',sans-serif;min-height:100vh}
.alayout{display:grid;grid-template-columns:var(--sb) 1fr;grid-template-rows:52px 1fr;min-height:100vh}
/* top bar */
.atopbar{grid-column:1/-1;background:#0a0a0d;border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 20px;gap:14px;position:sticky;top:0;z-index:100}
.atopbar .logo{display:flex;align-items:center;gap:8px;color:var(--ink);text-decoration:none;font-weight:700;font-size:15px;width:var(--sb);flex-shrink:0}
.atopbar .logo svg{flex-shrink:0}
.atopbar-links{margin-left:auto;display:flex;gap:8px}
/* sidebar */
.asidebar{background:#0d0d10;border-right:1px solid var(--line);padding:16px 0;position:sticky;top:52px;height:calc(100vh - 52px);overflow-y:auto}
.asidebar a{display:flex;align-items:center;gap:9px;padding:8px 18px;color:var(--mut);text-decoration:none;font-size:13px;font-weight:500;border-left:2px solid transparent;transition:.1s}
.asidebar a:hover{color:var(--ink);background:rgba(255,255,255,.04)}
.asidebar a.on{color:var(--acc);border-left-color:var(--acc);background:rgba(201,168,106,.06)}
.asidebar .sgrp{padding:14px 18px 4px;font-size:10px;font-weight:700;letter-spacing:.08em;color:#444;text-transform:uppercase}
.aside-badge{margin-left:auto;background:var(--acc);color:#0e0e11;border-radius:20px;padding:1px 7px;font-size:10px;font-weight:700}
.aside-badge.red{background:#ef9a9a;color:#1a0000}
/* main */
.amain{padding:28px 32px;overflow-y:auto}
/* stat cards */
.asgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-bottom:24px}
.ascard{background:var(--bg2);border:1px solid var(--line);border-radius:12px;padding:14px 16px;cursor:default}
.ascard .sv{font-size:22px;font-weight:700;line-height:1.2;color:var(--acc)}
.ascard .sl{font-size:11px;color:var(--mut);margin-top:4px}
/* section card */
.acard{background:var(--bg2);border:1px solid var(--line);border-radius:14px;margin-bottom:20px;overflow:hidden}
.acard-hd{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.02)}
.acard-hd h3{font-size:14px;font-weight:600;flex:1}
.acard-body{padding:18px}
/* table */
.atable{width:100%;border-collapse:collapse;font-size:12.5px}
.atable th.ac{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);color:var(--mut);font-weight:500;font-size:11px;white-space:nowrap;background:rgba(255,255,255,.02)}
.atable td.ac{padding:9px 10px;border-bottom:1px solid #1a1a1e;vertical-align:top;max-width:220px;word-break:break-word}
.atable tr:last-child td.ac{border-bottom:none}
.atable tr:hover td.ac{background:rgba(255,255,255,.02)}
.atscroll{overflow-x:auto}
/* buttons */
.abtn{display:inline-flex;align-items:center;padding:4px 10px;border:1px solid var(--line);border-radius:7px;background:transparent;color:var(--ink);font-size:12px;cursor:pointer;white-space:nowrap;font-family:inherit;transition:.1s;text-decoration:none}
.abtn:hover{border-color:var(--acc);color:var(--acc)}
.abtn.primary{background:var(--acc);color:#0e0e11;border-color:var(--acc);font-weight:600}
.abtn.primary:hover{opacity:.9}
/* forms */
.aform{display:flex;flex-direction:column;gap:12px}
.afield label{display:block;font-size:11px;color:var(--mut);margin-bottom:4px}
.afield input,.afield select,.afield textarea{width:100%;padding:6px 10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink);font-size:13px;font-family:inherit}
.afield textarea{resize:vertical;min-height:60px}
/* misc */
.amsg{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px}
.amsg.ok{background:rgba(122,214,160,.1);border:1px solid rgba(122,214,160,.3);color:#7ad6a0}
.aempty{color:var(--mut);padding:36px;text-align:center;font-size:14px}
.atag{font-family:monospace;font-size:11px;background:var(--bg);border:1px solid var(--line);padding:2px 6px;border-radius:4px}
.cdots{display:inline-flex;align-items:center;gap:4px;flex-wrap:wrap}
.cdots .cdot{width:13px;height:13px;border-radius:50%;display:inline-block}
.cdots .cmore{font-size:10px;color:var(--mut);font-weight:600}
.ahint{font-size:11px;color:var(--mut);margin-top:3px}
.acols2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.acols3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px}
.loginwrap{display:flex;align-items:center;justify-content:center;min-height:100vh}
.loginbox{width:380px;background:var(--bg2);border:1px solid var(--line);border-radius:18px;padding:36px}
/* doc status colors */
.doc-uploaded{background:rgba(201,168,106,.1);border-left:3px solid #c9a86a}
.doc-approved{background:rgba(122,214,160,.08);border-left:3px solid #7ad6a0}
.doc-rejected{background:rgba(239,154,154,.08);border-left:3px solid #ef9a9a}
.doc-requested{background:rgba(138,180,248,.08);border-left:3px solid #8ab4f8}
/* Mobile: sidebar becomes a horizontal, scrollable tab strip instead of disappearing */
@media(max-width:900px){
  :root{--sb:0px}
  .alayout{display:block}
  .asidebar{position:static;height:auto;display:flex;flex-direction:row;align-items:center;gap:2px;overflow-x:auto;-webkit-overflow-scrolling:touch;padding:8px 10px;border-right:0;border-bottom:1px solid var(--line);white-space:nowrap}
  .asidebar a{border-left:0;border-bottom:2px solid transparent;padding:8px 12px;flex-shrink:0}
  .asidebar a.on{border-left-color:transparent;border-bottom-color:var(--acc)}
  .asidebar .sgrp{display:none}
  .amain{padding:16px}
}
</style></head><body>

<?php if($locked): ?>
<div class="loginwrap"><div class="loginbox">
  <h2 style="margin-bottom:6px">Admin locked</h2>
  <p style="color:var(--mut);font-size:13px;margin-bottom:20px">Set <code>admin_pass</code> in <code>inc/config.php</code>.</p>
  <a class="abtn primary" href="/" style="justify-content:center;width:100%">← Back to site</a>
</div></div>

<?php elseif(!$authed): ?>
<div class="loginwrap"><div class="loginbox">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
    <svg viewBox="0 0 32 32" fill="none" width="30" height="30">
      <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="#c9a86a" stroke-width="1.4"/>
      <path d="M9 10l7 13 7-13" stroke="#c9a86a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div><div style="font-weight:700;font-size:16px">VESTRA</div><div style="font-size:11px;color:var(--mut)">Admin Panel</div></div>
  </div>
  <?php if($err): ?><div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#ef9a9a">Wrong password.</div><?php endif; ?>
  <form method="post" class="aform">
    <div class="afield"><label>Admin password</label><input type="password" name="pass" autofocus required autocomplete="current-password" placeholder="••••••••"></div>
    <button class="abtn primary" type="submit" style="justify-content:center;padding:9px">Sign in</button>
  </form>
</div></div>

<?php else:
  $tab       = $_GET['tab'] ?? 'overview';
  $msg       = $_GET['msg'] ?? '';
  $filterUid = $_GET['uid'] ?? '';

  $accounts  = auth_accounts();
  $listings  = vestra_listings();
  $orders    = vestra_read_csv('orders.csv');
  $offers    = vestra_read_csv('offers.csv');
  $requests  = vestra_read_csv('requests.csv');
  $signups   = vestra_read_csv('signups.csv');
  $orderSt   = vestra_read_json('order_statuses.json');
  $offerResp = vestra_read_json('offer_responses.json');
  $promos    = promo_all();

  $sellers      = array_filter($accounts,fn($a)=>($a['type']??'')==='seller');
  $buyers       = array_filter($accounts,fn($a)=>($a['type']??'')==='buyer');
  $pendingEmail = array_filter($accounts,fn($a)=>($a['status']??'')==='pending_email');
  $pendingKyb   = array_filter($accounts,fn($a)=>($a['status']??'')==='pending'&&($a['kyb_status']??'pending')==='pending');
  $reqOffers    = vestra_read_csv('request_offers.csv');
  require_once __DIR__.'/inc/messages.php';
  $msgThreads   = vestra_msg_threads();
  $blockedMsgs  = vestra_msg_blocked_log();
  $groupPools   = vestra_group_pools();
  $pendingList  = array_filter($listings,fn($p)=>($p['status']??'approved')==='pending');
  $pendingOffers= array_filter($offers,fn($o)=>empty($offerResp[$o['ref']??'']));
  $totalRevenue = array_sum(array_column($orders,'total'));

  // Accounts with pending document uploads
  $pendingDocs  = count(array_filter($accounts, fn($a)=>count(array_filter($a['doc_requests']??[],fn($r)=>$r['status']==='uploaded'))>0));

  $msgs=[
    'approved'=>'✓ Listing approved and live.','rejected'=>'Listing rejected.','kyb_ok'=>'KYB approved.',
    'suspended'=>'Account suspended.','activated'=>'Account activated.','deleted'=>'Listing deleted.',
    'status_ok'=>'Order status updated.','promo_ok'=>'Promo code created.','promo_del'=>'Promo code deleted.',
    'promo_toggled'=>'Promo code status changed.',
    'doc_requested'=>'Document requested.','doc_reviewed'=>'Document reviewed.',
    'verify_resent'=>'Verification email resent.','manual_verified'=>'Email verified manually.',
    'badge_granted'=>'✓ Verified Seller badge granted.','badge_revoked'=>'Badge revoked.',
    'csrf_fail'=>'⚠ Security check failed — please retry the action from this page.',
  ];

  function navLink(string $cur, string $key, string $icon, string $label, int $badge=0, bool $red=false): string {
    $on = $cur===$key?' on':'';
    $b = $badge>0?'<span class="aside-badge'.($red?' red':'').'">'.$badge.'</span>':'';
    return '<a href="/admin?tab='.htmlspecialchars($key).'" class="'.$on.'">'.$icon.' '.htmlspecialchars($label).$b.'</a>';
  }
?>

<div class="alayout">
<!-- TOP BAR -->
<div class="atopbar">
  <a href="/admin" class="logo">
    <svg viewBox="0 0 32 32" fill="none" width="26" height="26">
      <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="#c9a86a" stroke-width="1.4"/>
      <path d="M9 10l7 13 7-13" stroke="#c9a86a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    VESTRA
  </a>
  <span style="color:var(--mut);font-size:12px">Admin Panel</span>
  <div class="atopbar-links">
    <a class="abtn" href="/" target="_blank">View site</a>
    <a class="abtn" href="/seller-invite" target="_blank">Invite page</a>
    <a class="abtn" href="/admin?logout=1">Sign out</a>
  </div>
</div>

<!-- SIDEBAR -->
<nav class="asidebar">
  <div class="sgrp">Main</div>
  <?= navLink($tab,'overview','📊','Dashboard') ?>
  <?= navLink($tab,'approvals','⚠️','Approvals',count($pendingList),true) ?>
  <?= navLink($tab,'documents','📄','Documents',$pendingDocs,$pendingDocs>0) ?>

  <div class="sgrp">People</div>
  <?= navLink($tab,'users','👥','Users ('.count($accounts).')',count($pendingEmail),count($pendingEmail)>0) ?>

  <div class="sgrp">Transactions</div>
  <?= navLink($tab,'orders','📦','Orders ('.count($orders).')') ?>
  <?= navLink($tab,'offers','💬','Offers ('.count($offers).')') ?>
  <?= navLink($tab,'requests','📋','Requests ('.count($requests).')') ?>
  <?= navLink($tab,'req_offers','📩','Request Offers ('.count($reqOffers).')') ?>
  <?= navLink($tab,'groups','👥','Group buys ('.count($groupPools).')') ?>

  <div class="sgrp">Moderation</div>
  <?= navLink($tab,'messages','✉️','Messages ('.count($msgThreads).')',count($blockedMsgs),count($blockedMsgs)>0) ?>

  <div class="sgrp">Catalog</div>
  <?= navLink($tab,'listings','🏷️','Listings ('.count($listings).')') ?>

  <div class="sgrp">Marketing</div>
  <?= navLink($tab,'marketing','🎟️','Promo codes ('.count($promos).')') ?>
  <?= navLink($tab,'waitlist','📩','Waitlist ('.count($signups).')') ?>
</nav>

<!-- MAIN -->
<main class="amain">

<?php if($msg && isset($msgs[$msg])): ?>
<div class="amsg ok"><?= htmlspecialchars($msgs[$msg]) ?></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ OVERVIEW
if($tab==='overview'): ?>

<div class="asgrid">
  <div class="ascard"><div class="sv"><?= count($accounts) ?></div><div class="sl">Total accounts</div></div>
  <div class="ascard"><div class="sv" style="color:#c9a86a"><?= count($sellers) ?></div><div class="sl">Sellers</div></div>
  <div class="ascard"><div class="sv" style="color:#8ab4f8"><?= count($buyers) ?></div><div class="sl">Buyers</div></div>
  <div class="ascard"><div class="sv" style="color:#ef9a9a"><?= count($pendingEmail) ?></div><div class="sl">Email unverified</div></div>
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= count($pendingKyb) ?></div><div class="sl">Pending KYB</div></div>
  <div class="ascard"><div class="sv" style="color:#ef9a9a"><?= count($pendingList) ?></div><div class="sl">Pending listings</div></div>
  <div class="ascard"><div class="sv"><?= count($orders) ?></div><div class="sl">Orders</div></div>
  <div class="ascard"><div class="sv"><?= eur($totalRevenue) ?></div><div class="sl">Order volume</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= eur($totalRevenue*0.07) ?></div><div class="sl">Platform fees (7%)</div></div>
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= count($pendingOffers) ?></div><div class="sl">Offers pending</div></div>
  <div class="ascard"><div class="sv"><?= count($signups) ?></div><div class="sl">Waitlist</div></div>
</div>

<?php if($pendingList||$pendingKyb): ?>
<div class="acols2">
<?php if($pendingList): ?>
<div class="acard">
  <div class="acard-hd"><h3>⚠️ Listings awaiting approval (<?= count($pendingList) ?>)</h3><a class="abtn" href="/admin?tab=approvals">View all →</a></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['Brand','Product','Seller','Date'],true) ?>
    <?php foreach(array_slice(array_reverse(array_values($pendingList)),0,5) as $p): ?>
    <?= arow(['<b>'.htmlspecialchars($p['brand']??'').'</b>',htmlspecialchars($p['name']??''),htmlspecialchars($p['seller']??''),htmlspecialchars(substr($p['submitted_at']??'',0,10))]) ?>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
<?php if($pendingKyb): ?>
<div class="acard">
  <div class="acard-hd"><h3>⏳ KYB Queue (<?= count($pendingKyb) ?>)</h3><a class="abtn" href="/admin?tab=users">View all →</a></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['Name','Type','Company','Date'],true) ?>
    <?php foreach(array_slice(array_reverse(array_values($pendingKyb)),0,5) as $a): ?>
    <?= arow([htmlspecialchars($a['name']??'—'),typePill($a['type']??''),htmlspecialchars($a['company']??'—'),htmlspecialchars(substr($a['created']??'',0,10))]) ?>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
</div>
<?php endif; ?>

<div class="acols2">
<div class="acard">
  <div class="acard-hd"><h3>Recent registrations</h3><a class="abtn" href="/admin?tab=users">All →</a></div>
  <?php $rec=array_slice(array_reverse($accounts),0,8); if(!$rec){ echo '<div class="aempty">No accounts yet.</div>'; } else { ?>
  <div class="atscroll"><table class="atable">
    <?= arow(['Name','Type','KYB','Joined'],true) ?>
    <?php foreach($rec as $a): ?>
    <?= arow(['<b>'.htmlspecialchars($a['name']??'—').'</b><div class="ahint">'.htmlspecialchars($a['email']??'').'</div>',typePill($a['type']??''),kybBadge($a['kyb_status']??'pending'),htmlspecialchars(substr($a['created']??'',0,10))]) ?>
    <?php endforeach; ?>
  </table></div>
  <?php } ?>
</div>
<div class="acard">
  <div class="acard-hd"><h3>Recent orders</h3><a class="abtn" href="/admin?tab=orders">All →</a></div>
  <?php $rec=array_slice(array_reverse($orders),0,8); if(!$rec){ echo '<div class="aempty">No orders yet.</div>'; } else { ?>
  <div class="atscroll"><table class="atable">
    <?= arow(['Ref','Buyer','Total','Status'],true) ?>
    <?php foreach($rec as $o): $st=$orderSt[$o['ref']??'']['status']??'pending'; ?>
    <?= arow(['<span class="atag">'.htmlspecialchars(substr($o['ref']??'',0,12)).'</span>',htmlspecialchars($o['company']??$o['email']??''),'<b>'.eur($o['total']??0).'</b>',orderBadge($st)]) ?>
    <?php endforeach; ?>
  </table></div>
  <?php } ?>
</div>
</div>


<?php // ══════════════════════════════════════════════════════ APPROVALS
elseif($tab==='approvals'): ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div><h2 style="font-size:18px;font-weight:700">Listing Approvals</h2><p class="ahint" style="margin-top:4px">Review new seller listings before they go live in the catalog.</p></div>
</div>

<?php if(!$pendingList): ?>
  <div class="acard"><div class="aempty">✓ No listings pending approval.</div></div>
<?php else: foreach(array_reverse(array_values($pendingList)) as $p): ?>
<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-size:15px;font-weight:700"><?= htmlspecialchars($p['brand']??'') ?> — <?= htmlspecialchars($p['name']??'') ?></div>
      <div class="ahint" style="margin-top:3px">SKU <?= htmlspecialchars($p['sku']??'') ?> · <?= htmlspecialchars($p['cat']??'') ?> · Seller: <?= htmlspecialchars($p['seller']??'') ?></div>
    </div>
    <?= abadge('⏳ Pending','#f0c060') ?>
  </div>
  <div class="acard-body">
    <div class="acols3" style="margin-bottom:16px">
      <div><div class="ahint">Mode</div><b><?= htmlspecialchars($p['mode']??'fixed') ?></b></div>
      <div><div class="ahint">MOQ</div><b><?= htmlspecialchars((string)($p['moq']??'')) ?> <?= htmlspecialchars($p['unit']??'pc') ?></b></div>
      <div><div class="ahint">Starting price</div><b><?= ($p['mode']??'')==='offer'?'Open to offers':eur(vestra_from_price($p)) ?></b></div>
      <div><div class="ahint">Origin</div><?= htmlspecialchars($p['origin']??'—') ?></div>
      <div><div class="ahint">Description</div><?= htmlspecialchars(substr($p['desc']??'',0,80)) ?></div>
      <div><div class="ahint">Image</div><?= !empty($p['image'])?'<a class="abtn" href="'.htmlspecialchars($p['image']).'" target="_blank">View photo</a>':'No photo' ?></div>
    </div>
    <div class="acols2">
      <form method="post" class="aform">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="approve_listing">
        <input type="hidden" name="lid" value="<?= htmlspecialchars($p['id']??'') ?>">
        <div class="afield"><label>Note to seller (optional)</label><textarea name="note" placeholder="Approved — listing is now live."></textarea></div>
        <button class="abtn primary" type="submit">✓ Approve — go live</button>
      </form>
      <form method="post" class="aform">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="reject_listing">
        <input type="hidden" name="lid" value="<?= htmlspecialchars($p['id']??'') ?>">
        <div class="afield"><label>Reason for rejection (required)</label><textarea name="note" placeholder="Please revise: missing origin documentation…" required></textarea></div>
        <button class="abtn" type="submit" style="color:var(--bad);border-color:rgba(239,154,154,.4)">✗ Reject</button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>


<?php // ══════════════════════════════════════════════════════ DOCUMENTS
elseif($tab==='documents'):
  $docTypes = auth_doc_types();
  // If specific user selected, show their docs
  $selUser = null;
  if($filterUid){ foreach($accounts as $a){ if($a['id']===$filterUid){ $selUser=$a; break; } } }
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div><h2 style="font-size:18px;font-weight:700">Document Management</h2><p class="ahint" style="margin-top:4px">Request, review, and approve KYB/KYC documents.</p></div>
</div>

<?php if($selUser): ?>
<!-- Single user document view -->
<div style="margin-bottom:16px">
  <a class="abtn" href="/admin?tab=documents">← All users</a>
</div>
<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($selUser['name']??'—') ?> <span style="font-weight:400;color:var(--mut)">— <?= htmlspecialchars($selUser['email']??'') ?></span></div>
      <div class="ahint" style="margin-top:3px"><?= htmlspecialchars($selUser['company']??'') ?> · <?= htmlspecialchars($selUser['country']??'') ?> · <?= htmlspecialchars($selUser['vat_id']??'') ?></div>
    </div>
    <?= typePill($selUser['type']??'buyer') ?>
    <?= kybBadge($selUser['kyb_status']??'pending') ?>
  </div>
  <div class="acard-body">
  <div class="acols2">
  <!-- Request a document -->
  <form method="post" class="aform">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="request_doc">
    <input type="hidden" name="uid" value="<?= htmlspecialchars($selUser['id']??'') ?>">
    <div style="font-weight:600;margin-bottom:10px;font-size:13px">Request additional document</div>
    <div class="afield"><label>Document type</label>
      <select name="doc_type">
        <?php foreach($docTypes as $k=>$v): ?><option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="afield"><label>Note to user (optional)</label><textarea name="note" placeholder="Please upload your official company registration certificate (PDF or image)." rows="3"></textarea></div>
    <button class="abtn primary" type="submit">📋 Send request</button>
  </form>
  <!-- KYB approve -->
  <div>
    <div style="font-weight:600;margin-bottom:10px;font-size:13px">Quick actions</div>
    <?php if(($selUser['kyb_status']??'pending')==='pending'): ?>
      <?= fBtn('✓ Approve KYB','approve_kyb',['uid'=>$selUser['id']??''],'color:var(--ok);border-color:rgba(122,214,160,.4)') ?>
    <?php endif; ?>
    <?php if(($selUser['status']??'active')==='suspended'): ?>
      <?= fBtn('Activate account','activate_account',['uid'=>$selUser['id']??'']) ?>
    <?php else: ?>
      <?= fBtn('Suspend account','suspend_account',['uid'=>$selUser['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.3)') ?>
    <?php endif; ?>
  </div>
  </div>
  </div>
</div>

<!-- Document requests list -->
<?php $docReqs=$selUser['doc_requests']??[];
if(!$docReqs): ?>
  <div class="acard"><div class="aempty">No documents requested yet for this user.</div></div>
<?php else: foreach(array_reverse($docReqs) as $req): $st=$req['status']??'requested'; ?>
<div class="acard doc-<?= htmlspecialchars($st) ?>" style="margin-bottom:12px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-weight:600"><?= htmlspecialchars($docTypes[$req['type']??'']??$req['type']??'Document') ?></div>
      <div class="ahint">Requested <?= htmlspecialchars(substr($req['requested_at']??'',0,10)) ?>
        <?php if(!empty($req['uploaded_at'])): ?> · Uploaded <?= htmlspecialchars(substr($req['uploaded_at'],0,10)) ?><?php endif; ?>
        <?php if(!empty($req['reviewed_at'])): ?> · Reviewed <?= htmlspecialchars(substr($req['reviewed_at'],0,10)) ?><?php endif; ?>
      </div>
      <?php if(!empty($req['note'])): ?><div class="ahint" style="margin-top:4px;font-style:italic">Note: <?= htmlspecialchars($req['note']) ?></div><?php endif; ?>
      <?php if(!empty($req['admin_note'])): ?><div class="ahint" style="margin-top:4px;color:#c9a86a">Admin: <?= htmlspecialchars($req['admin_note']) ?></div><?php endif; ?>
    </div>
    <?= docBadge($st) ?>
  </div>
  <?php if($st==='uploaded' && !empty($req['file'])): ?>
  <div class="acard-body">
    <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
      <a class="abtn" href="/admin?dl_doc=<?= urlencode($req['file']) ?>&uid=<?= urlencode($selUser['id']??'') ?>" target="_blank">📂 View uploaded file</a>
      <form method="post" style="display:inline-flex;gap:8px;align-items:center">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="review_doc">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($selUser['id']??'') ?>">
        <input type="hidden" name="req_id" value="<?= htmlspecialchars($req['id']??'') ?>">
        <input name="admin_note" placeholder="Optional note" style="padding:4px 8px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12px;width:180px">
        <button class="abtn" name="status" value="approved" type="submit" style="color:var(--ok);border-color:rgba(122,214,160,.4)">✓ Approve</button>
        <button class="abtn" name="status" value="rejected" type="submit" style="color:var(--bad);border-color:rgba(239,154,154,.3)">✗ Reject</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>

<?php else: ?>
<!-- All users with document status -->
<div class="acard">
  <div class="acard-hd"><h3>All accounts — document status</h3></div>
  <?php if(!$accounts): ?>
    <div class="aempty">No accounts yet.</div>
  <?php else: ?>
  <div class="atscroll"><table class="atable">
    <?= arow(['Name','Email','Type','KYB','Docs','Pending docs',''],true) ?>
    <?php foreach(array_reverse($accounts) as $a):
      $dreqs=$a['doc_requests']??[];
      $uploaded=count(array_filter($dreqs,fn($r)=>$r['status']==='uploaded'));
      $approved=count(array_filter($dreqs,fn($r)=>$r['status']==='approved'));
      $total=count($dreqs);
    ?>
    <?= arow([
      '<b>'.htmlspecialchars($a['name']??'—').'</b><div class="ahint">'.htmlspecialchars($a['id']??'').'</div>',
      '<a href="mailto:'.htmlspecialchars($a['email']??'').'" style="color:var(--acc);font-size:12px">'.htmlspecialchars($a['email']??'').'</a>',
      typePill($a['type']??''),
      kybBadge(($a['status']??'active')==='suspended'?'suspended':($a['kyb_status']??'pending')),
      $total>0?"$approved/$total approved":'<span class="ahint">None</span>',
      $uploaded>0?abadge("$uploaded waiting review",'#c9a86a'):'<span class="ahint">—</span>',
      '<a class="abtn" href="/admin?tab=documents&uid='.urlencode($a['id']??'').'">Manage docs →</a>',
    ]) ?>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ USERS
elseif($tab==='users'):
  $filterType = $_GET['type']??'all';
  $shown = $filterType==='seller'?array_values($sellers):($filterType==='buyer'?array_values($buyers):$accounts);
  $shown = array_reverse($shown);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px;flex-wrap:wrap">
  <h2 style="font-size:18px;font-weight:700">Users</h2>
  <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
    <input id="usearch" placeholder="🔍 Search name / email / company…" oninput="ufilter()"
      style="padding:6px 12px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink);font-size:12.5px;min-width:220px">
    <a class="abtn<?= $filterType==='all'?' primary':'' ?>" href="/admin?tab=users&type=all">All (<?= count($accounts) ?>)</a>
    <a class="abtn<?= $filterType==='seller'?' primary':'' ?>" href="/admin?tab=users&type=seller">Sellers (<?= count($sellers) ?>)</a>
    <a class="abtn<?= $filterType==='buyer'?' primary':'' ?>" href="/admin?tab=users&type=buyer">Buyers (<?= count($buyers) ?>)</a>
  </div>
</div>
<script>
function ufilter(){
  var q=document.getElementById('usearch').value.toLowerCase();
  document.querySelectorAll('.atable tr').forEach(function(tr,i){
    if(i===0) return; // header
    tr.style.display = tr.textContent.toLowerCase().indexOf(q)>-1 ? '' : 'none';
  });
}
</script>

<?php if(!$shown): ?>
  <div class="acard"><div class="aempty">No accounts yet.</div></div>
<?php else: ?>
<div class="acard">
<div class="atscroll"><table class="atable">
  <?= arow(['#','Name','Email','Type','Company','Country','VAT ID','Email','KYB','Membership','Badge','Docs','Joined','Actions'],true) ?>
  <?php $i=count($shown); foreach($shown as $a):
    $isSusp=($a['status']??'active')==='suspended';
    $isPendEmail=($a['status']??'')==='pending_email';
    $dreqs=$a['doc_requests']??[];
    $docSummary=count($dreqs)>0?(count(array_filter($dreqs,fn($r)=>$r['status']==='approved')).'/'.count($dreqs)):'—';
    $uploaded=count(array_filter($dreqs,fn($r)=>$r['status']==='uploaded'));
  ?>
  <tr style="<?= $isSusp?'opacity:.45':'' ?>">
    <td class="ac" style="color:var(--mut)"><?= $i-- ?></td>
    <td class="ac"><b><?= htmlspecialchars($a['name']??'—') ?></b><div class="ahint"><?= htmlspecialchars(substr($a['id']??'',0,10)) ?>…</div></td>
    <td class="ac"><a href="mailto:<?= htmlspecialchars($a['email']??'') ?>" style="color:var(--acc);font-size:12px"><?= htmlspecialchars($a['email']??'') ?></a></td>
    <td class="ac"><?= typePill($a['type']??'') ?></td>
    <td class="ac"><?= htmlspecialchars($a['company']??'—') ?></td>
    <td class="ac"><?= htmlspecialchars($a['country']??'—') ?></td>
    <td class="ac" style="font-family:monospace;font-size:11px"><?= htmlspecialchars($a['vat_id']??'—') ?></td>
    <td class="ac">
      <?php if($isPendEmail): ?>
        <?= abadge('⚠ Unverified','#ef9a9a') ?>
        <div style="display:flex;gap:3px;margin-top:4px">
          <?= fBtn('Resend','resend_verify',['uid'=>$a['id']??''],'font-size:11px') ?>
          <?= fBtn('Force verify','manual_verify',['uid'=>$a['id']??''],'font-size:11px;color:var(--ok);border-color:rgba(122,214,160,.4)','Force-verify email for this account?') ?>
        </div>
      <?php elseif(!empty($a['email_verified'])): ?>
        <?= abadge('✓ Verified','#7ad6a0') ?>
      <?php else: ?>
        <span class="ahint">—</span>
      <?php endif; ?>
    </td>
    <td class="ac"><?= kybBadge($isSusp?'suspended':($a['kyb_status']??'pending')) ?></td>
    <td class="ac"><?= memberBadge($a['membership_tier']??'',$a['membership_status']??'') ?></td>
    <td class="ac">
      <?php if(($a['type']??'')==='seller' && !empty($a['onboarding_paid'])): ?>
        <?php if(!empty($a['verified_badge'])): ?>
          <?= abadge('✓ Badge','#7ad6a0') ?>
          <div style="margin-top:3px"><?= fBtn('Revoke','revoke_badge',['uid'=>$a['id']??''],'font-size:11px;color:var(--bad);border-color:rgba(239,154,154,.3)','Revoke Verified Seller badge?') ?></div>
        <?php else: ?>
          <?= fBtn('Grant badge','grant_badge',['uid'=>$a['id']??''],'font-size:11px;color:var(--ok);border-color:rgba(122,214,160,.4)','Grant Verified Seller badge?') ?>
        <?php endif; ?>
      <?php else: ?>
        <span style="color:#555;font-size:11px">—</span>
      <?php endif; ?>
    </td>
    <td class="ac">
      <?= $docSummary ?>
      <?php if($uploaded>0): ?><div><?= abadge("$uploaded to review",'#c9a86a') ?></div><?php endif; ?>
    </td>
    <td class="ac" style="font-size:11px;color:var(--mut)"><?= htmlspecialchars(substr($a['created']??'',0,10)) ?></td>
    <td class="ac"><div style="display:flex;gap:4px;flex-wrap:wrap">
      <a class="abtn" href="/admin?tab=documents&uid=<?= urlencode($a['id']??'') ?>">Docs</a>
      <?php if(($a['kyb_status']??'pending')==='pending'&&!$isSusp&&!$isPendEmail): echo fBtn('✓ KYB','approve_kyb',['uid'=>$a['id']??''],'color:var(--ok);border-color:rgba(122,214,160,.4)'); endif; ?>
      <?php if($isSusp): echo fBtn('Activate','activate_account',['uid'=>$a['id']??'']); else: echo fBtn('Suspend','suspend_account',['uid'=>$a['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.3)'); endif; ?>
    </div></td>
  </tr>
  <?php endforeach; ?>
</table></div>
</div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ ORDERS
elseif($tab==='orders'):
  $cnt_ship=count(array_filter($orders,fn($o)=>($orderSt[$o['ref']??'']['status']??'')==='shipped'));
  $cnt_done=count(array_filter($orders,fn($o)=>($orderSt[$o['ref']??'']['status']??'')==='completed'));
?>

<div class="asgrid" style="grid-template-columns:repeat(5,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv"><?= count($orders) ?></div><div class="sl">Total orders</div></div>
  <div class="ascard"><div class="sv" style="color:#888"><?= count($orders)-$cnt_ship-$cnt_done ?></div><div class="sl">Awaiting payment</div></div>
  <div class="ascard"><div class="sv" style="color:#c9a86a"><?= $cnt_ship ?></div><div class="sl">Shipped</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= $cnt_done ?></div><div class="sl">Completed</div></div>
  <div class="ascard"><div class="sv"><?= eur($totalRevenue) ?></div><div class="sl">Total volume</div></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=orders">⬇ Download CSV</a></div>

<?php if(!$orders): ?><div class="acard"><div class="aempty">No orders yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Ref','Date','Buyer','Company','Items','Total','Status','Tracking','Invoices','Update'],true) ?>
  <?php foreach(array_reverse($orders) as $o):
    $ref=$o['ref']??''; $st=$orderSt[$ref]['status']??'pending'; $trk=$orderSt[$ref]['tracking']??''; ?>
  <tr>
    <td class="ac"><span class="atag"><?= htmlspecialchars(substr($ref,0,12)) ?></span></td>
    <td class="ac" style="font-size:11px;color:var(--mut)"><?= htmlspecialchars(substr($o['timestamp']??'',0,10)) ?></td>
    <td class="ac"><a href="mailto:<?= htmlspecialchars($o['email']??'') ?>" style="color:var(--acc);font-size:12px"><?= htmlspecialchars($o['email']??'') ?></a></td>
    <td class="ac"><?= htmlspecialchars($o['company']??'—') ?></td>
    <td class="ac" style="max-width:140px;font-size:11px"><?= htmlspecialchars($o['items']??'') ?></td>
    <td class="ac"><b><?= eur($o['total']??0) ?></b></td>
    <td class="ac"><?= orderBadge($st) ?></td>
    <td class="ac" style="font-size:11px"><?= htmlspecialchars($trk) ?></td>
    <td class="ac" style="font-size:11px"><?php foreach(vestra_invoices_for_ref($ref) as $iv): ?>
      <a href="<?= htmlspecialchars($iv['url']) ?>" target="_blank" rel="noopener" style="color:var(--acc);display:block"><?= htmlspecialchars($iv['no']) ?></a>
    <?php endforeach; ?></td>
    <td class="ac"><?php if($st!=='completed'): ?>
      <form method="post" style="display:flex;flex-direction:column;gap:5px">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="order_status">
        <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">
        <select name="status" style="padding:3px 6px;border:1px solid var(--line);border-radius:5px;background:var(--bg);color:var(--ink);font-size:11px">
          <option value="pending" <?= $st==='pending'?'selected':'' ?>>⏳ Awaiting payment</option>
          <option value="paid" <?= $st==='paid'?'selected':'' ?>>💶 Paid — to ship</option>
          <option value="shipped" <?= $st==='shipped'?'selected':'' ?>>🚚 Shipped</option>
          <option value="completed" <?= $st==='completed'?'selected':'' ?>>✓ Completed</option>
        </select>
        <input name="tracking" value="<?= htmlspecialchars($trk) ?>" placeholder="Tracking no." style="padding:3px 6px;border:1px solid var(--line);border-radius:5px;background:var(--bg);color:var(--ink);font-size:11px">
        <button class="abtn primary" type="submit" style="font-size:11px;padding:3px 8px">Save</button>
      </form>
    <?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ OFFERS
elseif($tab==='offers'):
  $cnt_acc=count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='accept'));
  $cnt_dec=count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='decline'));
  $cnt_ctr=count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='counter'));
?>
<div class="asgrid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= count($pendingOffers) ?></div><div class="sl">Pending</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= $cnt_acc ?></div><div class="sl">Accepted</div></div>
  <div class="ascard"><div class="sv" style="color:#c9a86a"><?= $cnt_ctr ?></div><div class="sl">Countered</div></div>
  <div class="ascard"><div class="sv" style="color:#ef9a9a"><?= $cnt_dec ?></div><div class="sl">Declined</div></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=offers">⬇ CSV</a></div>
<?php if(!$offers): ?><div class="acard"><div class="aempty">No offers yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Ref','Date','Product','SKU','Qty','€/u','Total','Buyer','Status','Counter'],true) ?>
  <?php foreach(array_reverse($offers) as $o):
    $ref=$o['ref']??''; $resp=$offerResp[$ref]??null; $rSt=$resp['status']??'pending'; ?>
  <?= arow([
    '<span class="atag">'.htmlspecialchars(substr($ref,0,10)).'</span>',
    htmlspecialchars(substr($o['timestamp']??'',0,10)),
    htmlspecialchars($o['product']??'—'),
    htmlspecialchars($o['sku']??''),
    htmlspecialchars($o['qty']??''),
    eur($o['offer_unit']??0),
    '<b>'.eur($o['offer_total']??0).'</b>',
    '<a href="mailto:'.htmlspecialchars($o['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($o['email']??'').'</a>',
    match($rSt){'accept'=>abadge('✓ Accepted','#7ad6a0'),'decline'=>abadge('✗ Declined','#ef9a9a'),'counter'=>abadge('↩ Counter','#c9a86a'),default=>abadge('⏳ Pending','#888')},
    ($resp&&$rSt==='counter')?eur($resp['counter_price']??0):'—',
  ]) ?>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ REQUESTS
elseif($tab==='requests'): ?>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=requests">⬇ CSV</a></div>
<?php if(!$requests): ?><div class="acard"><div class="aempty">No buyer sourcing requests yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Date','Company','Email','Brand','Category','Qty','Budget €/u','Notes'],true) ?>
  <?php foreach(array_reverse($requests) as $r): ?>
  <?= arow([
    htmlspecialchars(substr($r['timestamp']??'',0,10)),
    htmlspecialchars($r['company']??'—'),
    '<a href="mailto:'.htmlspecialchars($r['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($r['email']??'').'</a>',
    '<b>'.htmlspecialchars($r['brand']??'—').'</b>',
    htmlspecialchars($r['category']??''),
    htmlspecialchars($r['qty']??''),
    htmlspecialchars($r['budget']??''),
    htmlspecialchars(substr($r['notes']??$r['message']??'',0,80)),
  ]) ?>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ REQUEST OFFERS
elseif($tab==='req_offers'): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div><h2 style="font-size:18px;font-weight:700">Request Offers</h2><p class="ahint" style="margin-top:4px">Seller offers submitted on buyer sourcing requests.</p></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=request_offers">⬇ CSV</a></div>
<?php if(!$reqOffers): ?><div class="acard"><div class="aempty">No seller offers on requests yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Ref','Date','Request ref','Seller company','Seller email','Price','Qty','Delivery','Message'],true) ?>
  <?php foreach(array_reverse($reqOffers) as $ro): ?>
  <?= arow([
    '<span class="atag">'.htmlspecialchars(substr($ro['ref']??'',0,12)).'</span>',
    htmlspecialchars(substr($ro['timestamp']??'',0,10)),
    '<span class="atag">'.htmlspecialchars($ro['request_ref']??'—').'</span>',
    '<b>'.htmlspecialchars($ro['seller_company']??'—').'</b>',
    '<a href="mailto:'.htmlspecialchars($ro['seller_email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($ro['seller_email']??'').'</a>',
    htmlspecialchars($ro['price']??''),
    htmlspecialchars($ro['qty']??''),
    htmlspecialchars($ro['delivery']??''),
    htmlspecialchars(substr($ro['message']??'',0,80)),
  ]) ?>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ LISTINGS
elseif($tab==='listings'):
  $liveList   = array_filter($listings,fn($p)=>($p['status']??'approved')==='approved');
  $rejList    = array_filter($listings,fn($p)=>($p['status']??'')==='rejected');
?>
<div class="asgrid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv"><?= count($listings) ?></div><div class="sl">Custom listings</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= count($liveList) ?></div><div class="sl">Live / approved</div></div>
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= count($pendingList) ?></div><div class="sl">Pending approval</div></div>
  <div class="ascard"><div class="sv" style="color:var(--mut)"><?= count(vestra_demo_products()) ?></div><div class="sl">Demo products</div></div>
</div>
<?php if(!$listings): ?><div class="acard"><div class="aempty">No custom listings yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['','Brand','Product','SKU','Mode','MOQ','From','Seller','Status',''],true) ?>
  <?php foreach(array_reverse($listings) as $p): $st=$p['status']??'approved'; $thumb=vestra_primary_image($p); ?>
  <tr>
    <td class="ac"><?php if($thumb): ?><img src="<?= htmlspecialchars($thumb) ?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:7px;border:1px solid var(--line)"><?php else: ?><div style="width:42px;height:42px;border-radius:7px;background:linear-gradient(135deg,<?= htmlspecialchars($p['accent']??'#333') ?>,#0e0e11)"></div><?php endif; ?></td>
    <td class="ac"><b><?= htmlspecialchars($p['brand']??'') ?></b></td>
    <td class="ac"><?= htmlspecialchars($p['name']??'') ?><div class="ahint"><?= htmlspecialchars(substr($p['id']??'',0,14)) ?>…</div><?= !empty($p['colors'])?'<div style="margin-top:3px">'.vestra_color_dots((array)$p['colors'],7).'</div>':'' ?></td>
    <td class="ac"><span class="atag"><?= htmlspecialchars($p['sku']??'') ?></span></td>
    <td class="ac"><span class="modechip <?= htmlspecialchars($p['mode']??'fixed') ?>"><?= htmlspecialchars($p['mode']??'fixed') ?></span></td>
    <td class="ac"><?= htmlspecialchars((string)($p['moq']??'')) ?> <?= htmlspecialchars($p['unit']??'pc') ?></td>
    <td class="ac"><?= $st==='offer'?'—':eur(vestra_from_price($p)) ?></td>
    <td class="ac"><?= htmlspecialchars($p['seller']??'—') ?></td>
    <td class="ac"><?= match($st){'approved'=>abadge('✓ Live','#7ad6a0'),'rejected'=>abadge('✗ Rejected','#ef9a9a'),default=>abadge('⏳ Pending','#f0c060')} ?></td>
    <td class="ac"><div style="display:flex;gap:4px">
      <?php if($st==='pending'): ?><a class="abtn" href="/admin?tab=approvals">Review</a><?php endif; ?>
      <?= fBtn('Delete','delete_listing',['lid'=>$p['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.3)','Delete this listing?') ?>
    </div></td>
  </tr>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ MARKETING
elseif($tab==='marketing'): ?>
<div class="acols2">
<div class="acard">
  <div class="acard-hd"><h3>Create promo code</h3></div>
  <div class="acard-body">
  <form method="post" class="aform">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="create_promo">
    <div class="afield"><label>Code (blank = auto-generate)</label><input name="code" placeholder="SELLER-SUMMER26" style="text-transform:uppercase"></div>
    <div class="afield"><label>Description</label><input name="desc" placeholder="Early seller access"></div>
    <div class="afield"><label>Benefit</label>
      <select name="benefit">
        <option value="instant_kyb">Instant KYB approval</option>
        <option value="commission_free_3m">No registration fee</option>
        <option value="commission_free_6m">0% commission — 6 months</option>
        <option value="reduced_commission">3.5% commission — 6 months</option>
        <option value="priority_listing">Priority listing placement</option>
      </select>
    </div>
    <div class="acols2">
      <div class="afield"><label>Expiry date</label><input type="date" name="expiry" value="2026-12-31"></div>
      <div class="afield"><label>Max uses (0=∞)</label><input type="number" name="max_uses" value="100"></div>
    </div>
    <button class="abtn primary" type="submit">＋ Generate code</button>
  </form>
  </div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>Seller Scout</h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:12px">Find brand sellers online — pre-built search links</p>
  <div class="afield" style="margin-bottom:12px"><label>Brand or category</label><input id="scout-q" placeholder="e.g. Lacoste, Tommy Hilfiger" oninput="updateLinks()"></div>
  <div style="display:flex;flex-direction:column;gap:7px">
    <a id="sl-google" href="#" target="_blank" class="abtn">🔍 Google — wholesale distributor EEA</a>
    <a id="sl-li" href="#" target="_blank" class="abtn">💼 LinkedIn People</a>
    <a id="sl-li2" href="#" target="_blank" class="abtn">🏢 LinkedIn Companies</a>
    <a id="sl-ep" href="#" target="_blank" class="abtn">🌍 Europages</a>
    <a id="sl-km" href="#" target="_blank" class="abtn">🗂 Kompass</a>
    <a id="sl-ig" href="#" target="_blank" class="abtn">📷 Instagram</a>
  </div>
  </div>
</div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>Active promo codes (<?= count($promos) ?>)</h3></div>
  <?php if(!$promos): ?><div class="aempty">No codes yet.</div>
  <?php else: ?>
  <div class="atscroll"><table class="atable">
    <?= arow(['Code','Description','Benefit','Expiry','Uses','Status','Invite link',''],true) ?>
    <?php foreach($promos as $c=>$p): $active=$p['active']??true; ?>
    <tr style="opacity:<?= $active?1:.4 ?>">
      <td class="ac"><b class="atag"><?= htmlspecialchars($c) ?></b></td>
      <td class="ac"><?= htmlspecialchars($p['desc']??'') ?></td>
      <td class="ac" style="font-size:11px"><?= htmlspecialchars(promo_benefit_label($p['benefit']??'')) ?></td>
      <td class="ac"><?= htmlspecialchars($p['expiry']??'—') ?></td>
      <td class="ac"><?= ($p['used']??0) ?>/<?= ($p['max_uses']??'∞') ?></td>
      <td class="ac"><?= abadge($active?'Active':'Paused',$active?'#7ad6a0':'#888') ?></td>
      <td class="ac"><a href="/seller-invite?code=<?= urlencode($c) ?>" target="_blank" style="color:var(--acc);font-size:11px">…/seller-invite?code=<?= htmlspecialchars($c) ?></a></td>
      <td class="ac"><div style="display:flex;gap:4px">
        <?= fBtn($active?'Pause':'Enable','toggle_promo',['toggle_code'=>$c]) ?>
        <?= fBtn('Delete','delete_promo',['del_code'=>$c],'color:var(--bad);border-color:rgba(239,154,154,.3)','Delete code '.$c.'?') ?>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>

<script>
function updateLinks(){
  var q=document.getElementById('scout-q').value||'fashion wholesale';
  document.getElementById('sl-google').href='https://www.google.com/search?q='+encodeURIComponent(q+' wholesale distributor Europe');
  document.getElementById('sl-li').href='https://www.linkedin.com/search/results/people/?keywords='+encodeURIComponent(q+' wholesale');
  document.getElementById('sl-li2').href='https://www.linkedin.com/search/results/companies/?keywords='+encodeURIComponent(q+' wholesale');
  document.getElementById('sl-ep').href='https://www.europages.com/companies/'+encodeURIComponent(q.split(' ')[0])+'.html';
  document.getElementById('sl-km').href='https://www.kompass.com/searchinternational/search.html?text='+encodeURIComponent(q);
  document.getElementById('sl-ig').href='https://www.instagram.com/explore/tags/'+encodeURIComponent(q.replace(/\s+/g,'').toLowerCase())+'wholesale/';
}
updateLinks();
</script>


<?php // ══════════════════════════════════════════════════════ GROUP BUYS
elseif($tab==='groups'):
  $cnt_open   = count(array_filter($groupPools,fn($p)=>$p['_status']==='open'));
  $cnt_funded = count(array_filter($groupPools,fn($p)=>$p['_status']==='funded'));
  $cnt_exp    = count(array_filter($groupPools,fn($p)=>$p['_status']==='expired'));
?>
<div class="asgrid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv"><?= count($groupPools) ?></div><div class="sl">Pools</div></div>
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= $cnt_open ?></div><div class="sl">Open</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= $cnt_funded ?></div><div class="sl">Target reached</div></div>
  <div class="ascard"><div class="sv" style="color:var(--mut)"><?= $cnt_exp ?></div><div class="sl">Expired</div></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=groups">⬇ CSV</a></div>
<?php if(!$groupPools): ?><div class="acard"><div class="aempty">No products are open for group buying yet.</div></div>
<?php else: foreach($groupPools as $gp): ?>
<div class="acard" style="margin-bottom:12px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-weight:600"><?= htmlspecialchars($gp['brand']??'') ?> — <?= htmlspecialchars($gp['name']??'') ?>
        <a href="/group?id=<?= urlencode($gp['id']??'') ?>" target="_blank" style="color:var(--acc);font-size:11px;margin-left:8px">View page ↗</a></div>
      <div class="ahint"><?= number_format($gp['_committed']) ?> / <?= number_format($gp['_target']) ?> <?= htmlspecialchars($gp['unit']??'pc') ?>
        · <?= $gp['_pct'] ?>% · <?= (int)$gp['_participants'] ?> buyers
        · unlocks <?= eur($gp['_gprice']) ?>/<?= htmlspecialchars($gp['unit']??'pc') ?>
        · closes <?= htmlspecialchars(substr($gp['_deadline']??'',0,10)) ?></div>
    </div>
    <?= match($gp['_status']){'funded'=>abadge('✓ Target reached','#7ad6a0'),'expired'=>abadge('• Expired','#888'),default=>abadge('⏳ Open · '.$gp['_daysLeft'].'d left','#f0c060')} ?>
  </div>
  <?php if($gp['_commits']): ?>
  <div class="acard-body"><div class="atscroll"><table class="atable">
    <?= arow(['Date','Ref','Company','Email','Country','Qty','Est. total'],true) ?>
    <?php foreach($gp['_commits'] as $c): ?>
    <?= arow([
      htmlspecialchars(substr($c['timestamp']??'',0,10)),
      '<span class="atag">'.htmlspecialchars($c['ref']??'').'</span>',
      '<b>'.htmlspecialchars($c['company']??'—').'</b>',
      '<a href="mailto:'.htmlspecialchars($c['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($c['email']??'').'</a>',
      htmlspecialchars($c['country']??'—'),
      htmlspecialchars($c['qty']??'').' '.htmlspecialchars($gp['unit']??'pc'),
      eur($c['est_total']??0),
    ]) ?>
    <?php endforeach; ?>
  </table></div></div>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>


<?php // ══════════════════════════════════════════════════════ MESSAGES (moderation)
elseif($tab==='messages'):
  $accById=[]; foreach($accounts as $a){ $accById[$a['id']??'']=$a; }
  $accLabel=function(string $uid) use ($accById): string {
    $a=$accById[$uid]??null;
    return $a ? (($a['company']?:($a['name']?:$uid))) : $uid;
  };
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div><h2 style="font-size:18px;font-weight:700">Message Moderation</h2>
  <p class="ahint" style="margin-top:4px">Buyer ↔ seller conversations. Off-platform contact (email / IBAN) is auto-blocked — attempts are logged below.</p></div>
</div>

<?php if($blockedMsgs): ?>
<div class="acard" style="margin-bottom:16px;border-color:rgba(239,154,154,.35)">
  <div class="acard-hd"><h3 style="color:#ef9a9a">⚠️ Blocked off-platform attempts (<?= count($blockedMsgs) ?>)</h3></div>
  <div class="acard-body"><div class="atscroll"><table class="atable">
    <?= arow(['When','Sender','Thread','Type','Attempted text'],true) ?>
    <?php foreach(array_reverse($blockedMsgs) as $bm): ?>
    <?= arow([
      htmlspecialchars(substr($bm['at']??'',0,16)),
      '<b>'.htmlspecialchars($accLabel($bm['from']??'')).'</b>',
      htmlspecialchars($accLabel($bm['buyer_uid']??'')).' ↔ '.htmlspecialchars($accLabel($bm['seller_uid']??'')),
      abadge(strtoupper($bm['flag']??''),'#ef9a9a'),
      '<span style="font-size:11px;color:var(--mut)">'.htmlspecialchars(mb_substr($bm['text']??'',0,120)).'</span>',
    ]) ?>
    <?php endforeach; ?>
  </table></div></div>
</div>
<?php endif; ?>

<?php if(!$msgThreads): ?><div class="acard"><div class="aempty">No conversations yet. Buyer-seller chats appear here.</div></div>
<?php else:
  usort($msgThreads, fn($a,$b)=>strtotime($b['last_at']??'1970-01-01')<=>strtotime($a['last_at']??'1970-01-01'));
  foreach($msgThreads as $th): ?>
<div class="acard" style="margin-bottom:12px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-weight:600"><?= htmlspecialchars($accLabel($th['buyer_uid']??'')) ?> ↔ <?= htmlspecialchars($accLabel($th['seller_uid']??'')) ?></div>
      <div class="ahint"><?= count($th['messages']??[]) ?> messages · last <?= htmlspecialchars(substr($th['last_at']??'',0,16)) ?>
        <?php if(!empty($th['listing_id'])): ?> · <a href="/product?id=<?= urlencode($th['listing_id']) ?>" target="_blank" style="color:var(--acc)">listing ↗</a><?php endif; ?></div>
    </div>
  </div>
  <div class="acard-body">
    <details><summary style="cursor:pointer;font-size:12px;color:var(--acc)">Read conversation</summary>
      <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px">
        <?php foreach(($th['messages']??[]) as $m): $isBuyer=($m['from']??'')===($th['buyer_uid']??''); ?>
        <div style="font-size:12.5px;line-height:1.5">
          <b style="color:<?= $isBuyer?'#8ab4f8':'#c9a86a' ?>"><?= htmlspecialchars($accLabel($m['from']??'')) ?></b>
          <span class="ahint" style="margin-left:6px"><?= htmlspecialchars(substr($m['at']??'',0,16)) ?></span>
          <div><?= htmlspecialchars($m['text']??'') ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </details>
  </div>
</div>
<?php endforeach; endif; ?>


<?php // ══════════════════════════════════════════════════════ WAITLIST
elseif($tab==='waitlist'): ?>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=signups">⬇ CSV</a></div>
<?php if(!$signups): ?><div class="acard"><div class="aempty">No waitlist signups yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Date','Name','Email','Company','Country','Type','Notes'],true) ?>
  <?php foreach(array_reverse($signups) as $s): ?>
  <?= arow([
    htmlspecialchars(substr($s['timestamp']??'',0,10)),
    htmlspecialchars($s['name']??'—'),
    '<a href="mailto:'.htmlspecialchars($s['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($s['email']??'').'</a>',
    htmlspecialchars($s['company']??'—'),
    htmlspecialchars($s['country']??'—'),
    typePill($s['type']??'buyer'),
    htmlspecialchars(substr($s['notes']??$s['message']??'',0,80)),
  ]) ?>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>

<?php endif; // end tab switch ?>

</main>
</div><!-- alayout -->

<?php endif; // end authed ?>
</body></html>
