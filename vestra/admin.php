<?php
/** VESTRA — private admin panel. Password is set in inc/config.php (admin_pass). */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/promos.php';
require_once __DIR__.'/inc/auth.php';
if(session_status()===PHP_SESSION_NONE) session_start();

$PASS   = (string)vestra_cfg('admin_pass','');
$locked = ($PASS==='');

if(isset($_GET['logout'])){ unset($_SESSION['vadmin']); header('Location: /admin'); exit; }

$err=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pass'])){
  if(!$locked && hash_equals($PASS,(string)$_POST['pass'])){ $_SESSION['vadmin']=true; header('Location: /admin'); exit; }
  $err=true;
}
$authed = !empty($_SESSION['vadmin']);

// POST actions
if($authed && $_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['_action'] ?? '';
  if($action==='approve_kyb'){
    auth_update($_POST['uid']??'', ['kyb_status'=>'approved','status'=>'active']);
    header('Location: /admin?tab=users&msg=kyb_ok'); exit;
  }
  if($action==='suspend_account'){
    auth_update($_POST['uid']??'', ['status'=>'suspended']);
    header('Location: /admin?tab=users&msg=suspended'); exit;
  }
  if($action==='activate_account'){
    auth_update($_POST['uid']??'', ['status'=>'active']);
    header('Location: /admin?tab=users&msg=activated'); exit;
  }
  if($action==='delete_listing'){
    $lid = $_POST['lid']??'';
    if($lid) vestra_save_listings(array_values(array_filter(vestra_listings(), fn($p)=>($p['id']??'')!==$lid)));
    header('Location: /admin?tab=listings&msg=deleted'); exit;
  }
  if($action==='order_status'){
    $ref=$_POST['ref']??''; $st=$_POST['status']??'';
    if($ref && in_array($st,['pending','shipped','completed'],true)){
      $all=vestra_read_json('order_statuses.json');
      $all[$ref]=array_merge($all[$ref]??[],['status'=>$st,'tracking'=>trim($_POST['tracking']??''),'updated_at'=>date('c')]);
      vestra_write_json('order_statuses.json',$all);
    }
    header('Location: /admin?tab=orders&msg=status_ok'); exit;
  }
  if($action==='create_promo'){ promo_create($_POST); header('Location: /admin?tab=marketing&msg=promo_ok'); exit; }
  if($action==='delete_promo'){
    $all=promo_all(); unset($all[strtoupper($_POST['del_code']??'')]); promo_save($all);
    header('Location: /admin?tab=marketing&msg=promo_del'); exit;
  }
  if($action==='toggle_promo'){
    $all=promo_all(); $k=strtoupper($_POST['toggle_code']??'');
    if(isset($all[$k])){ $all[$k]['active']=!($all[$k]['active']??true); promo_save($all); }
    header('Location: /admin?tab=marketing'); exit;
  }
}

// CSV download
if($authed && isset($_GET['dl'])){
  $map=['signups'=>'signups.csv','orders'=>'orders.csv','offers'=>'offers.csv','requests'=>'requests.csv','groups'=>'groups.csv'];
  $f=$map[$_GET['dl']]??null; $path=$f?vestra_data_dir().'/'.$f:'';
  if($f && is_readable($path)){
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="vestra-'.$f.'"');
    readfile($path); exit;
  }
  http_response_code(404); echo 'No data'; exit;
}

// Helpers
function abadge(string $txt, string $color='#888'): string {
  return '<span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:'.htmlspecialchars($color).'25;color:'.htmlspecialchars($color).';border:1px solid '.htmlspecialchars($color).'50">'.htmlspecialchars($txt).'</span>';
}
function kybBadge(string $s): string {
  return match($s){
    'approved'  => abadge('✓ Verified','#7ad6a0'),
    'suspended' => abadge('⊘ Suspended','#ef9a9a'),
    default     => abadge('⏳ Pending','#f0c060'),
  };
}
function orderBadge(string $s): string {
  return match($s){
    'completed' => abadge('✓ Completed','#7ad6a0'),
    'shipped'   => abadge('🚚 Shipped','#c9a86a'),
    default     => abadge('⏳ In escrow','#888'),
  };
}
function typePill(string $type): string {
  $col = $type==='seller' ? '#c9a86a' : '#8ab4f8';
  $bg  = $type==='seller' ? 'rgba(201,168,106,.15)' : 'rgba(138,180,248,.15)';
  return '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:'.htmlspecialchars($bg).';color:'.htmlspecialchars($col).'">'.htmlspecialchars($type).'</span>';
}
function formBtn(string $label, string $action, array $fields, string $style='', string $confirm=''): string {
  $onclick = $confirm ? ' onclick="return confirm(\''.htmlspecialchars(addslashes($confirm)).'\')"' : '';
  $h = '<form method="post" style="display:inline">';
  $h.= '<input type="hidden" name="_action" value="'.htmlspecialchars($action).'">';
  foreach($fields as $k=>$v) $h.='<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';
  $h.= '<button type="submit" class="btn btn-o btn-sm"'.$onclick.' style="'.htmlspecialchars($style).'">'.htmlspecialchars($label).'</button></form>';
  return $h;
}
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>VESTRA Admin</title>
<link rel="stylesheet" href="/inc/style.css">
<style>
.adminbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:18px 0 10px;border-bottom:1px solid var(--line);margin-bottom:16px}
.atabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 20px}
.atabs a{padding:7px 14px;border:1px solid var(--line);border-radius:8px;color:var(--mut);text-decoration:none;font-size:13px;font-weight:500}
.atabs a.on{background:var(--acc);color:#0e0e11;border-color:var(--acc);font-weight:600}
.loginbox{max-width:360px;margin:80px auto;text-align:center}
.ctable{width:100%;border-collapse:collapse;font-size:13px}
.ctable th{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);color:var(--mut);font-weight:500;font-size:12px;white-space:nowrap}
.ctable td{padding:9px 10px;border-bottom:1px solid var(--line);vertical-align:top;max-width:260px;word-break:break-word}
.ctable tr:last-child td{border-bottom:none}
.tscroll{overflow-x:auto;border:1px solid var(--line);border-radius:12px}
.astatgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin:0 0 20px}
.astatcard{background:var(--bg2);border:1px solid var(--line);border-radius:12px;padding:14px 16px}
.asv{font-size:22px;font-weight:700;color:var(--acc);line-height:1.2}
.asl{font-size:12px;color:var(--mut);margin-top:3px}
.asection{background:var(--bg2);border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:18px}
.asection h3{margin:0 0 14px;font-size:15px}
.amsg{padding:10px 14px;border-radius:8px;margin:0 0 14px;font-size:13px}
.amsg.ok{background:rgba(122,214,160,.12);border:1px solid rgba(122,214,160,.3);color:#7ad6a0}
.act-btns{display:flex;gap:5px;flex-wrap:wrap}
.empty{color:var(--mut);padding:32px;text-align:center;font-size:14px}
.hint{color:var(--mut);font-size:12px}
</style></head><body>
<div class="wrap">
<?php if($locked): ?>
  <div class="loginbox">
    <h1>Admin locked</h1>
    <p class="hint">Set <code>admin_pass</code> in <code>inc/config.php</code> via cPanel File Manager.</p>
    <a class="btn btn-o" href="/">← Back to site</a>
  </div>
<?php elseif(!$authed): ?>
  <form class="loginbox" method="post">
    <h1 style="margin-bottom:4px">VESTRA Admin</h1>
    <p class="hint" style="margin-bottom:20px">vestrasales.com</p>
    <?php if($err): ?><div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#ef9a9a;margin-bottom:12px">Wrong password.</div><?php endif; ?>
    <input type="password" name="pass" placeholder="Admin password" autofocus required autocomplete="current-password" style="width:100%;margin-bottom:12px">
    <button class="btn btn-p" type="submit" style="width:100%;justify-content:center">Sign in</button>
  </form>
<?php else:
  $tab        = $_GET['tab'] ?? 'overview';
  $msg        = $_GET['msg'] ?? '';
  $accounts   = auth_accounts();
  $listings   = vestra_listings();
  $orders     = vestra_read_csv('orders.csv');
  $offers     = vestra_read_csv('offers.csv');
  $requests   = vestra_read_csv('requests.csv');
  $signups    = vestra_read_csv('signups.csv');
  $orderSt    = vestra_read_json('order_statuses.json');
  $offerResp  = vestra_read_json('offer_responses.json');
  $promos     = promo_all();
  $pools      = vestra_group_pools();

  $sellers       = array_filter($accounts, fn($a)=>($a['type']??'')==='seller');
  $buyers        = array_filter($accounts, fn($a)=>($a['type']??'')==='buyer');
  $pendingKyb    = array_filter($accounts, fn($a)=>($a['kyb_status']??'pending')==='pending' && ($a['status']??'active')!=='suspended');
  $suspended     = array_filter($accounts, fn($a)=>($a['status']??'active')==='suspended');
  $pendingOffers = array_filter($offers, fn($o)=>empty($offerResp[$o['ref']??'']));
  $totalRevenue  = array_sum(array_column($orders,'total'));

  $msgs = [
    'kyb_ok'    => 'KYB approved.',
    'suspended' => 'Account suspended.',
    'activated' => 'Account activated.',
    'deleted'   => 'Listing deleted.',
    'status_ok' => 'Order status updated.',
    'promo_ok'  => 'Promo code created.',
    'promo_del' => 'Promo code deleted.',
  ];

  function atab(string $cur, string $k, string $label): string {
    $on = $cur===$k ? ' on' : '';
    return '<a href="/admin?tab='.htmlspecialchars($k).'" class="'.$on.'">'.htmlspecialchars($label).'</a>';
  }
?>
  <div class="adminbar">
    <svg viewBox="0 0 32 32" fill="none" width="26" height="26">
      <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/>
      <path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <b style="font-size:17px">VESTRA</b>
    <span class="hint">Admin</span>
    <span style="flex:1"></span>
    <a class="btn btn-o btn-sm" href="/seller-invite" target="_blank">Invite page</a>
    <a class="btn btn-o btn-sm" href="/" target="_blank">View site</a>
    <a class="btn btn-o btn-sm" href="/admin?logout=1">Sign out</a>
  </div>

  <div class="atabs">
    <?= atab($tab,'overview','📊 Overview') ?>
    <?= atab($tab,'users','👥 Users ('.count($accounts).')') ?>
    <?= atab($tab,'orders','📦 Orders ('.count($orders).')') ?>
    <?= atab($tab,'offers','💬 Offers ('.count($offers).')') ?>
    <?= atab($tab,'listings','🏷️ Listings ('.count($listings).')') ?>
    <?= atab($tab,'requests','📋 Requests ('.count($requests).')') ?>
    <?= atab($tab,'marketing','🎟️ Marketing ('.count($promos).')') ?>
    <?= atab($tab,'waitlist','📩 Waitlist ('.count($signups).')') ?>
  </div>

  <?php if($msg && isset($msgs[$msg])): ?>
    <div class="amsg ok"><?= htmlspecialchars($msgs[$msg]) ?></div>
  <?php endif; ?>

  <?php // ── OVERVIEW ────────────────────────────────────────────────────────
  if($tab==='overview'): ?>

  <div class="astatgrid">
    <div class="astatcard"><div class="asv"><?= count($accounts) ?></div><div class="asl">Total accounts</div></div>
    <div class="astatcard"><div class="asv" style="color:#c9a86a"><?= count($sellers) ?></div><div class="asl">Sellers</div></div>
    <div class="astatcard"><div class="asv" style="color:#8ab4f8"><?= count($buyers) ?></div><div class="asl">Buyers</div></div>
    <div class="astatcard"><div class="asv" style="color:#f0c060"><?= count($pendingKyb) ?></div><div class="asl">Pending KYB</div></div>
    <div class="astatcard"><div class="asv"><?= count($orders) ?></div><div class="asl">Orders</div></div>
    <div class="astatcard"><div class="asv"><?= eur($totalRevenue) ?></div><div class="asl">Order volume</div></div>
    <div class="astatcard"><div class="asv" style="color:#7ad6a0"><?= eur($totalRevenue*0.07) ?></div><div class="asl">Platform fees (7%)</div></div>
    <div class="astatcard"><div class="asv" style="color:#f0c060"><?= count($pendingOffers) ?></div><div class="asl">Offers pending</div></div>
    <div class="astatcard"><div class="asv"><?= count($listings) ?></div><div class="asl">Custom listings</div></div>
    <div class="astatcard"><div class="asv"><?= count($signups) ?></div><div class="asl">Waitlist</div></div>
  </div>

  <?php if($pendingKyb): ?>
  <div class="asection" style="border-color:rgba(240,192,96,.4)">
    <h3 style="color:#f0c060">⚠️ KYB Queue (<?= count($pendingKyb) ?> pending)</h3>
    <div class="tscroll">
    <table class="ctable">
      <thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Company</th><th>Country</th><th>VAT</th><th>Joined</th><th></th></tr></thead>
      <tbody>
      <?php foreach(array_reverse($pendingKyb) as $a): ?>
        <tr>
          <td><b><?= htmlspecialchars($a['name']??'—') ?></b></td>
          <td><?= htmlspecialchars($a['email']??'') ?></td>
          <td><?= typePill($a['type']??'buyer') ?></td>
          <td><?= htmlspecialchars($a['company']??'—') ?></td>
          <td><?= htmlspecialchars($a['country']??'—') ?></td>
          <td class="hint"><?= htmlspecialchars($a['vat_id']??'—') ?></td>
          <td class="hint"><?= htmlspecialchars(substr($a['created']??'',0,10)) ?></td>
          <td><?= formBtn('✓ Approve KYB','approve_kyb',['uid'=>$a['id']??''],'color:var(--ok);border-color:rgba(122,214,160,.4)') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
  <div class="asection">
    <h3>Recent registrations</h3>
    <?php $rec=array_slice(array_reverse($accounts),0,8); if(!$rec){ echo '<div class="empty">No accounts yet.</div>'; } else { ?>
    <table class="ctable">
      <thead><tr><th>Name</th><th>Type</th><th>KYB</th><th>Joined</th></tr></thead>
      <tbody>
      <?php foreach($rec as $a): ?>
        <tr>
          <td><b><?= htmlspecialchars($a['name']??'—') ?></b><div class="hint"><?= htmlspecialchars($a['email']??'') ?></div></td>
          <td><?= typePill($a['type']??'buyer') ?></td>
          <td><?= kybBadge($a['kyb_status']??'pending') ?></td>
          <td class="hint"><?= htmlspecialchars(substr($a['created']??'',0,10)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="margin-top:10px"><a class="btn btn-o btn-sm" href="/admin?tab=users">All users →</a></div>
    <?php } ?>
  </div>

  <div class="asection">
    <h3>Recent orders</h3>
    <?php $rec=array_slice(array_reverse($orders),0,8); if(!$rec){ echo '<div class="empty">No orders yet.</div>'; } else { ?>
    <table class="ctable">
      <thead><tr><th>Ref</th><th>Buyer</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($rec as $o):
        $st=$orderSt[$o['ref']??'']['status']??'pending'; ?>
        <tr>
          <td style="font-family:monospace;font-size:11px"><b><?= htmlspecialchars($o['ref']??'') ?></b><div class="hint"><?= htmlspecialchars(substr($o['timestamp']??'',0,10)) ?></div></td>
          <td><?= htmlspecialchars($o['company']??$o['email']??'') ?></td>
          <td><b><?= eur($o['total']??0) ?></b></td>
          <td><?= orderBadge($st) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="margin-top:10px"><a class="btn btn-o btn-sm" href="/admin?tab=orders">All orders →</a></div>
    <?php } ?>
  </div>
  </div>


  <?php // ── USERS ────────────────────────────────────────────────────────────
  elseif($tab==='users'):
    $filterType = $_GET['type'] ?? 'all';
    if($filterType==='seller')     $shown=array_values($sellers);
    elseif($filterType==='buyer')  $shown=array_values($buyers);
    else                           $shown=$accounts;
    $shown=array_reverse($shown);
  ?>

  <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center">
    <a class="btn btn-o btn-sm<?= $filterType==='all'?' on':'' ?>" href="/admin?tab=users&type=all">All (<?= count($accounts) ?>)</a>
    <a class="btn btn-o btn-sm<?= $filterType==='seller'?' on':'' ?>" href="/admin?tab=users&type=seller">Sellers (<?= count($sellers) ?>)</a>
    <a class="btn btn-o btn-sm<?= $filterType==='buyer'?' on':'' ?>" href="/admin?tab=users&type=buyer">Buyers (<?= count($buyers) ?>)</a>
    <span class="hint" style="margin-left:4px"><?= count($suspended) ?> suspended · <?= count($pendingKyb) ?> pending KYB</span>
  </div>

  <?php if(!$shown): ?>
    <div class="empty">No accounts yet.</div>
  <?php else: ?>
  <div class="tscroll">
  <table class="ctable">
    <thead><tr>
      <th>#</th><th>Name</th><th>Email</th><th>Type</th><th>Company</th>
      <th>Country</th><th>VAT ID</th><th>Reg no.</th><th>KYB</th><th>Promo</th><th>Joined</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php $i=count($shown); foreach($shown as $a):
      $isSusp = ($a['status']??'active')==='suspended';
      $kybKey = $isSusp ? 'suspended' : ($a['kyb_status']??'pending');
    ?>
      <tr style="<?= $isSusp?'opacity:.45':'' ?>">
        <td class="hint"><?= $i-- ?></td>
        <td><b><?= htmlspecialchars($a['name']??'—') ?></b><div class="hint"><?= htmlspecialchars(substr($a['id']??'',0,12)) ?>…</div></td>
        <td><a href="mailto:<?= htmlspecialchars($a['email']??'') ?>" style="color:var(--acc)"><?= htmlspecialchars($a['email']??'') ?></a></td>
        <td><?= typePill($a['type']??'buyer') ?></td>
        <td><?= htmlspecialchars($a['company']??'—') ?></td>
        <td><?= htmlspecialchars($a['country']??'—') ?></td>
        <td class="hint"><?= htmlspecialchars($a['vat_id']??'—') ?></td>
        <td class="hint"><?= htmlspecialchars($a['reg_number']??'—') ?></td>
        <td><?= kybBadge($kybKey) ?></td>
        <td class="hint"><?= htmlspecialchars($a['promo_code']??'—') ?></td>
        <td class="hint"><?= htmlspecialchars(substr($a['created']??'',0,10)) ?></td>
        <td><div class="act-btns">
          <?php if(($a['kyb_status']??'pending')==='pending' && !$isSusp):
            echo formBtn('✓ KYB','approve_kyb',['uid'=>$a['id']??''],'color:var(--ok);border-color:rgba(122,214,160,.4)');
          endif; ?>
          <?php if($isSusp):
            echo formBtn('Activate','activate_account',['uid'=>$a['id']??'']);
          else:
            echo formBtn('Suspend','suspend_account',['uid'=>$a['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.3)');
          endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>


  <?php // ── ORDERS ───────────────────────────────────────────────────────────
  elseif($tab==='orders'):
    $orders_r   = array_reverse($orders);
    $cnt_ship   = count(array_filter($orders,fn($o)=>($orderSt[$o['ref']??'']['status']??'')==='shipped'));
    $cnt_done   = count(array_filter($orders,fn($o)=>($orderSt[$o['ref']??'']['status']??'')==='completed'));
    $cnt_pend   = count($orders)-$cnt_ship-$cnt_done;
  ?>

  <div class="astatgrid" style="grid-template-columns:repeat(5,1fr)">
    <div class="astatcard"><div class="asv"><?= count($orders) ?></div><div class="asl">Total orders</div></div>
    <div class="astatcard"><div class="asv" style="color:#888"><?= $cnt_pend ?></div><div class="asl">In escrow</div></div>
    <div class="astatcard"><div class="asv" style="color:#c9a86a"><?= $cnt_ship ?></div><div class="asl">Shipped</div></div>
    <div class="astatcard"><div class="asv" style="color:#7ad6a0"><?= $cnt_done ?></div><div class="asl">Completed</div></div>
    <div class="astatcard"><div class="asv"><?= eur($totalRevenue) ?></div><div class="asl">Total volume</div></div>
  </div>

  <div style="margin-bottom:10px"><a class="btn btn-o btn-sm" href="/admin?dl=orders">⬇ CSV</a></div>

  <?php if(!$orders): ?>
    <div class="empty">No orders yet.</div>
  <?php else: ?>
  <div class="tscroll">
  <table class="ctable">
    <thead><tr>
      <th>Ref</th><th>Date</th><th>Buyer</th><th>Company</th><th>Items</th>
      <th>Total</th><th>Status</th><th>Tracking</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach($orders_r as $o):
      $ref=$o['ref']??''; $st=$orderSt[$ref]['status']??'pending'; $trk=$orderSt[$ref]['tracking']??''; ?>
      <tr>
        <td style="font-family:monospace;font-size:11px"><?= htmlspecialchars($ref) ?></td>
        <td class="hint"><?= htmlspecialchars(substr($o['timestamp']??'',0,10)) ?></td>
        <td><a href="mailto:<?= htmlspecialchars($o['email']??'') ?>" style="color:var(--acc);font-size:12px"><?= htmlspecialchars($o['email']??'') ?></a></td>
        <td><?= htmlspecialchars($o['company']??'—') ?></td>
        <td class="hint" style="max-width:160px"><?= htmlspecialchars($o['items']??'') ?></td>
        <td><b><?= eur($o['total']??0) ?></b></td>
        <td><?= orderBadge($st) ?></td>
        <td class="hint"><?= htmlspecialchars($trk) ?></td>
        <td>
          <?php if($st!=='completed'): ?>
          <form method="post" style="display:flex;flex-direction:column;gap:6px;min-width:160px">
            <input type="hidden" name="_action" value="order_status">
            <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">
            <select name="status" style="padding:4px 6px;border:1px solid var(--line);border-radius:6px;background:var(--bg2);color:var(--ink);font-size:12px">
              <option value="pending"  <?= $st==='pending'?'selected':'' ?>>⏳ In escrow</option>
              <option value="shipped"  <?= $st==='shipped'?'selected':'' ?>>🚚 Shipped</option>
              <option value="completed"<?= $st==='completed'?'selected':'' ?>>✓ Completed</option>
            </select>
            <input name="tracking" value="<?= htmlspecialchars($trk) ?>" placeholder="Tracking no." style="padding:4px 6px;border:1px solid var(--line);border-radius:6px;background:var(--bg2);color:var(--ink);font-size:12px">
            <button class="btn btn-p btn-sm" type="submit">Save</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>


  <?php // ── OFFERS ───────────────────────────────────────────────────────────
  elseif($tab==='offers'):
    $cnt_acc = count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='accept'));
    $cnt_dec = count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='decline'));
    $cnt_ctr = count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='counter'));
  ?>

  <div class="astatgrid" style="grid-template-columns:repeat(4,1fr)">
    <div class="astatcard"><div class="asv" style="color:#f0c060"><?= count($pendingOffers) ?></div><div class="asl">Pending</div></div>
    <div class="astatcard"><div class="asv" style="color:#7ad6a0"><?= $cnt_acc ?></div><div class="asl">Accepted</div></div>
    <div class="astatcard"><div class="asv" style="color:#c9a86a"><?= $cnt_ctr ?></div><div class="asl">Countered</div></div>
    <div class="astatcard"><div class="asv" style="color:#ef9a9a"><?= $cnt_dec ?></div><div class="asl">Declined</div></div>
  </div>

  <div style="margin-bottom:10px"><a class="btn btn-o btn-sm" href="/admin?dl=offers">⬇ CSV</a></div>

  <?php if(!$offers): ?>
    <div class="empty">No offers yet.</div>
  <?php else: ?>
  <div class="tscroll">
  <table class="ctable">
    <thead><tr>
      <th>Ref</th><th>Date</th><th>Product</th><th>SKU</th><th>Qty</th>
      <th>€/u</th><th>Total</th><th>Buyer</th><th>Company</th><th>Status</th><th>Counter</th>
    </tr></thead>
    <tbody>
    <?php foreach(array_reverse($offers) as $o):
      $ref=$o['ref']??''; $resp=$offerResp[$ref]??null; $rSt=$resp['status']??'pending'; ?>
      <tr>
        <td style="font-family:monospace;font-size:11px"><?= htmlspecialchars($ref) ?></td>
        <td class="hint"><?= htmlspecialchars(substr($o['timestamp']??'',0,10)) ?></td>
        <td><?= htmlspecialchars($o['product']??'—') ?></td>
        <td class="hint"><?= htmlspecialchars($o['sku']??'') ?></td>
        <td class="hint"><?= htmlspecialchars($o['qty']??'') ?></td>
        <td><?= eur($o['offer_unit']??0) ?></td>
        <td><b><?= eur($o['offer_total']??0) ?></b></td>
        <td><a href="mailto:<?= htmlspecialchars($o['email']??'') ?>" style="color:var(--acc);font-size:12px"><?= htmlspecialchars($o['email']??'') ?></a></td>
        <td><?= htmlspecialchars($o['company']??'—') ?></td>
        <td><?= match($rSt){
          'accept'  => abadge('✓ Accepted','#7ad6a0'),
          'decline' => abadge('✗ Declined','#ef9a9a'),
          'counter' => abadge('↩ Counter','#c9a86a'),
          default   => abadge('⏳ Pending','#888'),
        } ?></td>
        <td><?= ($resp&&$rSt==='counter') ? eur($resp['counter_price']??0) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>


  <?php // ── LISTINGS ─────────────────────────────────────────────────────────
  elseif($tab==='listings'): ?>

  <div class="astatgrid" style="grid-template-columns:repeat(3,1fr)">
    <div class="astatcard"><div class="asv"><?= count($listings) ?></div><div class="asl">Custom listings</div></div>
    <div class="astatcard"><div class="asv" style="color:var(--mut)"><?= count(vestra_demo_products()) ?></div><div class="asl">Demo products</div></div>
    <div class="astatcard"><div class="asv"><?= count($listings)+count(vestra_demo_products()) ?></div><div class="asl">Total in catalog</div></div>
  </div>

  <?php if(!$listings): ?>
    <div class="empty">No custom listings yet. Demo products are always visible in the catalog.</div>
  <?php else: ?>
  <div class="tscroll">
  <table class="ctable">
    <thead><tr>
      <th>Brand</th><th>Product</th><th>SKU</th><th>Category</th>
      <th>Mode</th><th>MOQ</th><th>From</th><th>Seller</th><th>Photo</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach(array_reverse($listings) as $p): ?>
      <tr>
        <td><b><?= htmlspecialchars($p['brand']??'') ?></b></td>
        <td><?= htmlspecialchars($p['name']??'') ?><div class="hint">ID: <?= htmlspecialchars(substr($p['id']??'',0,14)) ?>…</div></td>
        <td class="hint" style="font-family:monospace;font-size:11px"><?= htmlspecialchars($p['sku']??'') ?></td>
        <td class="hint"><?= htmlspecialchars($p['cat']??'') ?></td>
        <td><span class="modechip <?= htmlspecialchars($p['mode']??'fixed') ?>"><?= htmlspecialchars($p['mode']??'fixed') ?></span></td>
        <td><?= htmlspecialchars((string)($p['moq']??'')) ?> <?= htmlspecialchars($p['unit']??'pc') ?></td>
        <td><?= ($p['mode']??'')==='offer' ? '—' : eur(vestra_from_price($p)) ?></td>
        <td><?= htmlspecialchars($p['seller']??'—') ?></td>
        <td><?= !empty($p['image'])?'✓':'—' ?></td>
        <td><?= formBtn('Delete','delete_listing',['lid'=>$p['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.3)','Delete this listing?') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>


  <?php // ── REQUESTS ─────────────────────────────────────────────────────────
  elseif($tab==='requests'): ?>

  <div style="margin-bottom:10px"><a class="btn btn-o btn-sm" href="/admin?dl=requests">⬇ CSV</a></div>

  <?php if(!$requests): ?>
    <div class="empty">No buyer sourcing requests yet.</div>
  <?php else: ?>
  <div class="tscroll">
  <table class="ctable">
    <thead><tr>
      <th>Date</th><th>Company</th><th>Email</th><th>Brand</th>
      <th>Category</th><th>Qty</th><th>Budget €/u</th><th>Notes</th>
    </tr></thead>
    <tbody>
    <?php foreach(array_reverse($requests) as $r): ?>
      <tr>
        <td class="hint"><?= htmlspecialchars(substr($r['timestamp']??'',0,10)) ?></td>
        <td><?= htmlspecialchars($r['company']??'—') ?></td>
        <td><a href="mailto:<?= htmlspecialchars($r['email']??'') ?>" style="color:var(--acc);font-size:12px"><?= htmlspecialchars($r['email']??'') ?></a></td>
        <td><b><?= htmlspecialchars($r['brand']??'—') ?></b></td>
        <td class="hint"><?= htmlspecialchars($r['category']??'') ?></td>
        <td class="hint"><?= htmlspecialchars($r['qty']??'') ?></td>
        <td class="hint"><?= htmlspecialchars($r['budget']??'') ?></td>
        <td class="hint" style="max-width:200px"><?= htmlspecialchars($r['notes']??$r['message']??'') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>


  <?php // ── MARKETING ────────────────────────────────────────────────────────
  elseif($tab==='marketing'): ?>

  <div style="display:grid;grid-template-columns:350px 1fr;gap:18px;margin-bottom:18px">
  <div class="asection">
    <h3>Create promo code</h3>
    <form method="post" action="/admin?tab=marketing" style="display:flex;flex-direction:column;gap:10px">
      <input type="hidden" name="_action" value="create_promo">
      <div><label class="hint">Code (blank = auto-generate)</label>
        <input name="code" placeholder="SELLER-SUMMER26" style="width:100%;text-transform:uppercase">
      </div>
      <div><label class="hint">Description</label>
        <input name="desc" placeholder="Early seller access" style="width:100%">
      </div>
      <div><label class="hint">Benefit</label>
        <select name="benefit" style="width:100%">
          <option value="instant_kyb">Instant KYB approval</option>
          <option value="commission_free_3m">No registration fee</option>
          <option value="commission_free_6m">0% commission — 6 months</option>
          <option value="reduced_commission">3.5% commission — 6 months</option>
          <option value="priority_listing">Priority listing placement</option>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div><label class="hint">Expiry date</label><input type="date" name="expiry" style="width:100%" value="2026-12-31"></div>
        <div><label class="hint">Max uses (0=∞)</label><input type="number" name="max_uses" value="100" style="width:100%"></div>
      </div>
      <button class="btn btn-p" type="submit">＋ Generate code</button>
    </form>
  </div>

  <div class="asection">
    <h3>Seller Scout</h3>
    <p class="hint" style="margin:0 0 14px">Find brand sellers online — pre-built search links</p>
    <input id="scout-q" placeholder="e.g. Lacoste, Tommy Hilfiger, polos" style="width:100%;margin-bottom:12px" oninput="updateLinks()">
    <div style="display:flex;flex-direction:column;gap:7px">
      <a id="sl-google" href="#" target="_blank" class="btn btn-o btn-sm">🔍 Google — wholesale distributor</a>
      <a id="sl-li" href="#" target="_blank" class="btn btn-o btn-sm">💼 LinkedIn People</a>
      <a id="sl-li2" href="#" target="_blank" class="btn btn-o btn-sm">🏢 LinkedIn Companies</a>
      <a id="sl-ep" href="#" target="_blank" class="btn btn-o btn-sm">🌍 Europages</a>
      <a id="sl-km" href="#" target="_blank" class="btn btn-o btn-sm">🗂 Kompass</a>
      <a id="sl-ig" href="#" target="_blank" class="btn btn-o btn-sm">📷 Instagram hashtag</a>
    </div>
  </div>
  </div>

  <div class="asection">
    <h3>Promo codes (<?= count($promos) ?>)</h3>
    <?php if(!$promos): ?>
      <div class="empty" style="padding:16px 0">No codes yet.</div>
    <?php else: ?>
    <div class="tscroll">
    <table class="ctable">
      <thead><tr>
        <th>Code</th><th>Description</th><th>Benefit</th><th>Expiry</th><th>Uses</th><th>Status</th><th>Invite link</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach($promos as $c=>$p):
        $active=$p['active']??true; ?>
        <tr style="opacity:<?= $active?1:.45 ?>">
          <td><b style="font-family:monospace"><?= htmlspecialchars($c) ?></b></td>
          <td><?= htmlspecialchars($p['desc']??'') ?></td>
          <td class="hint"><?= htmlspecialchars(promo_benefit_label($p['benefit']??'')) ?></td>
          <td><?= htmlspecialchars($p['expiry']??'—') ?></td>
          <td><?= ($p['used']??0) ?>/<?= ($p['max_uses']??'∞') ?></td>
          <td><?= abadge($active?'Active':'Paused',$active?'#7ad6a0':'#888') ?></td>
          <td><a href="/seller-invite?code=<?= urlencode($c) ?>" target="_blank" style="color:var(--acc);font-size:11px">/seller-invite?code=<?= htmlspecialchars($c) ?></a></td>
          <td><div class="act-btns">
            <?= formBtn($active?'Pause':'Enable','toggle_promo',['toggle_code'=>$c]) ?>
            <?= formBtn('Delete','delete_promo',['del_code'=>$c],'color:var(--bad);border-color:rgba(239,154,154,.3)','Delete code '.$c.'?') ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
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


  <?php // ── WAITLIST ─────────────────────────────────────────────────────────
  elseif($tab==='waitlist'): ?>

  <div style="margin-bottom:10px"><a class="btn btn-o btn-sm" href="/admin?dl=signups">⬇ CSV</a></div>

  <?php if(!$signups): ?>
    <div class="empty">No waitlist signups yet.</div>
  <?php else: ?>
  <div class="tscroll">
  <table class="ctable">
    <thead><tr>
      <th>Date</th><th>Name</th><th>Email</th><th>Company</th><th>Country</th><th>Type</th><th>Notes</th>
    </tr></thead>
    <tbody>
    <?php foreach(array_reverse($signups) as $s): ?>
      <tr>
        <td class="hint"><?= htmlspecialchars(substr($s['timestamp']??'',0,10)) ?></td>
        <td><?= htmlspecialchars($s['name']??'—') ?></td>
        <td><a href="mailto:<?= htmlspecialchars($s['email']??'') ?>" style="color:var(--acc);font-size:12px"><?= htmlspecialchars($s['email']??'') ?></a></td>
        <td><?= htmlspecialchars($s['company']??'—') ?></td>
        <td><?= htmlspecialchars($s['country']??'—') ?></td>
        <td><?= typePill($s['type']??'buyer') ?></td>
        <td class="hint" style="max-width:200px"><?= htmlspecialchars(substr($s['notes']??$s['message']??'',0,100)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <?php endif; // end tab switch ?>
<?php endif; // end authed ?>
</div></body></html>
