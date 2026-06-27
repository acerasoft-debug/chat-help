<?php
/** VESTRA — private admin panel. Password is set in inc/config.php (admin_pass). */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/promos.php';
require_once __DIR__.'/inc/auth.php';
if(session_status()===PHP_SESSION_NONE) session_start();

$PASS   = (string)vestra_cfg('admin_pass','');
$locked = ($PASS===''); // no password configured => panel disabled

if(isset($_GET['logout'])){ unset($_SESSION['vadmin']); header('Location: /admin'); exit; }

$err=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pass'])){
  if(!$locked && hash_equals($PASS,(string)$_POST['pass'])){ $_SESSION['vadmin']=true; header('Location: /admin'); exit; }
  $err=true;
}
$authed = !empty($_SESSION['vadmin']);

/* Create promo code */
if($authed && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_promo'])){
  promo_create($_POST);
  header('Location: /admin?tab=marketing&promo_created=1'); exit;
}
/* Delete promo code */
if($authed && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_promo'])){
  $all=promo_all(); unset($all[strtoupper($_POST['del_code']??'')]); promo_save($all);
  header('Location: /admin?tab=marketing&promo_deleted=1'); exit;
}
/* Toggle promo active/inactive */
if($authed && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_promo'])){
  $all=promo_all(); $k=strtoupper($_POST['toggle_code']??'');
  if(isset($all[$k])) { $all[$k]['active']=!($all[$k]['active']??true); promo_save($all); }
  header('Location: /admin?tab=marketing'); exit;
}
/* KYB approve account */
if($authed && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['approve_kyb'])){
  auth_update($_POST['uid']??'', ['kyb_status'=>'approved']);
  header('Location: /admin?tab=accounts&kyb_ok=1'); exit;
}

/* CSV download (admin only) */
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

function atable($rows){
  if(!$rows){ echo '<div class="empty">No records yet.</div>'; return; }
  $cols=array_keys($rows[0]);
  echo '<div style="overflow:auto"><table class="ctable"><thead><tr>';
  foreach($cols as $c) echo '<th>'.htmlspecialchars((string)$c).'</th>';
  echo '</tr></thead><tbody>';
  foreach($rows as $r){ echo '<tr>'; foreach($cols as $c){ echo '<td>'.nl2br(htmlspecialchars((string)($r[$c]??''))).'</td>'; } echo '</tr>'; }
  echo '</tbody></table></div>';
}
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>VESTRA — Admin</title>
<link rel="stylesheet" href="/inc/style.css">
<style>
.adminbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:22px 0 4px}
.atabs{display:flex;gap:6px;flex-wrap:wrap;margin:14px 0 18px}
.atabs a{padding:7px 13px;border:1px solid var(--line);border-radius:8px;color:var(--mut);text-decoration:none;font-size:14px}
.atabs a.on{background:var(--acc);color:#0e0e11;border-color:var(--acc);font-weight:600}
.loginbox{max-width:360px;margin:80px auto;text-align:center}
.loginbox input{width:100%;margin:10px 0}
.ctable td{vertical-align:top;font-size:13px;max-width:320px;word-break:break-word}
</style></head><body>
<div class="wrap">
<?php if($locked): ?>
  <div class="loginbox">
    <h1>Admin locked</h1>
    <p class="hint">Open <code>inc/config.php</code> in cPanel File Manager and set a strong <code>admin_pass</code> to enable this panel.</p>
    <a class="btn btn-o" href="/">← Back to site</a>
  </div>
<?php elseif(!$authed): ?>
  <form class="loginbox" method="post">
    <h1>VESTRA Admin</h1>
    <?php if($err): ?><div class="banner bad" style="margin:10px 0">Wrong password.</div><?php endif; ?>
    <input type="password" name="pass" placeholder="Admin password" autofocus required autocomplete="current-password">
    <button class="btn btn-p" type="submit" style="width:100%;justify-content:center">Sign in</button>
  </form>
<?php else:
  $tab=$_GET['tab']??'accounts';
  $data=[
    'signups' =>['Waitlist',  vestra_read_csv('signups.csv')],
    'orders'  =>['Orders',    vestra_read_csv('orders.csv')],
    'offers'  =>['Offers',    vestra_read_csv('offers.csv')],
    'requests'=>['Requests',  vestra_read_csv('requests.csv')],
    'groups'  =>['Group buys',vestra_read_csv('groups.csv')],
  ];
  $listings=vestra_listings();
  $pools=vestra_group_pools();
  $accounts=auth_accounts();
  $promos=promo_all();
?>
  <div class="adminbar">
    <h1 style="margin:0">VESTRA Admin</h1>
    <span style="flex:1"></span>
    <a class="btn btn-o btn-sm" href="/seller-invite" target="_blank">Invite page</a>
    <a class="btn btn-o btn-sm" href="/">View site</a>
    <a class="btn btn-o btn-sm" href="/admin?logout=1">Sign out</a>
  </div>
  <div class="atabs">
    <a href="/admin?tab=accounts"  class="<?=$tab==='accounts'?'on':''?>">Accounts (<?=count($accounts)?>)</a>
    <a href="/admin?tab=marketing" class="<?=$tab==='marketing'?'on':''?>">Marketing (<?=count($promos)?>)</a>
    <?php foreach($data as $k=>$d): ?>
      <a href="/admin?tab=<?=$k?>" class="<?=$tab===$k?'on':''?>"><?=$d[0]?> (<?=count($d[1])?>)</a>
    <?php endforeach; ?>
    <a href="/admin?tab=listings"  class="<?=$tab==='listings'?'on':''?>">Listings (<?=count($listings)?>)</a>
  </div>

  <?php if($tab==='accounts'):
    if(isset($_GET['kyb_ok'])) echo '<div class="banner ok" style="margin:0 0 12px">KYB approved.</div>';
    $arows=array_map(function($a){
      return array_diff_key($a, ['hash'=>1,'promo_benefit'=>1,'promo_expiry'=>1]);
    }, $accounts);
    // Render with KYB approve button
    if($accounts){
      echo '<div style="overflow:auto"><table class="ctable"><thead><tr>';
      foreach(['name','email','type','company','country','kyb_status','promo_code','created'] as $c) echo '<th>'.htmlspecialchars($c).'</th>';
      echo '<th></th></tr></thead><tbody>';
      foreach(array_reverse($accounts) as $a){
        echo '<tr>';
        foreach(['name','email','type','company','country','kyb_status','promo_code','created'] as $c)
          echo '<td>'.htmlspecialchars(substr((string)($a[$c]??''),0,60)).'</td>';
        echo '<td>';
        if(($a['kyb_status']??'pending')==='pending')
          echo '<form method="post" style="display:inline"><input type="hidden" name="uid" value="'.htmlspecialchars($a['id']??'').'"><button name="approve_kyb" value="1" class="btn btn-o btn-sm" type="submit" style="color:var(--ok);border-color:rgba(122,214,160,.4)">✓ Approve KYB</button></form>';
        echo '</td></tr>';
      }
      echo '</tbody></table></div>';
    } else { echo '<div class="empty">No registered accounts yet.</div>'; }

  elseif($tab==='marketing'):
    if(isset($_GET['promo_created'])) echo '<div class="banner ok" style="margin:0 0 12px">Code created.</div>';
    if(isset($_GET['promo_deleted'])) echo '<div class="banner ok" style="margin:0 0 12px">Code deleted.</div>';
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

    <!-- Create code -->
    <div style="background:var(--bg2);border:1px solid var(--line);border-radius:14px;padding:20px">
      <h3 style="margin:0 0 14px">Create promo code</h3>
      <form method="post" action="/admin?tab=marketing" style="display:flex;flex-direction:column;gap:10px">
        <div><label class="hint">Code (leave blank to auto-generate)</label>
          <input name="code" placeholder="e.g. SELLER-SUMMER" style="width:100%;text-transform:uppercase">
        </div>
        <div><label class="hint">Description</label>
          <input name="desc" placeholder="Early seller access — Summer 2026" style="width:100%">
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
          <div><label class="hint">Max uses (0 = unlimited)</label><input type="number" name="max_uses" value="100" style="width:100%"></div>
        </div>
        <button name="create_promo" value="1" class="btn btn-p btn-sm" type="submit">＋ Generate code</button>
      </form>
    </div>

    <!-- Seller Scout -->
    <div style="background:var(--bg2);border:1px solid var(--line);border-radius:14px;padding:20px">
      <h3 style="margin:0 0 6px">Seller Scout</h3>
      <p class="hint" style="margin:0 0 14px">Find brand sellers online — pre-built search links</p>
      <label class="hint">Brand or category to search</label>
      <input id="scout-q" placeholder="e.g. Lacoste, Tommy Hilfiger, polos" style="width:100%;margin-bottom:12px" oninput="updateLinks()">
      <div id="scout-links" style="display:flex;flex-direction:column;gap:7px">
        <a id="sl-google" href="#" target="_blank" rel="noopener" class="btn btn-o btn-sm">🔍 Google</a>
        <a id="sl-li" href="#" target="_blank" rel="noopener" class="btn btn-o btn-sm">💼 LinkedIn People</a>
        <a id="sl-li2" href="#" target="_blank" rel="noopener" class="btn btn-o btn-sm">🏢 LinkedIn Companies</a>
        <a id="sl-ep" href="#" target="_blank" rel="noopener" class="btn btn-o btn-sm">🌍 Europages</a>
        <a id="sl-km" href="#" target="_blank" rel="noopener" class="btn btn-o btn-sm">🗂 Kompass</a>
        <a id="sl-ig" href="#" target="_blank" rel="noopener" class="btn btn-o btn-sm">📷 Instagram</a>
      </div>
    </div>
    </div>

    <!-- Promo codes table -->
    <div style="margin-top:20px;background:var(--bg2);border:1px solid var(--line);border-radius:14px;padding:20px">
      <h3 style="margin:0 0 14px">Active codes</h3>
      <?php if(!$promos): ?><div class="empty" style="padding:20px 0">No codes yet.</div>
      <?php else: ?>
      <div style="overflow:auto"><table class="ctable"><thead><tr>
        <th>Code</th><th>Description</th><th>Benefit</th><th>Expiry</th><th>Uses</th><th>Status</th><th>Invite link</th><th></th>
      </tr></thead><tbody>
      <?php foreach($promos as $c=>$p): $active=$p['active']??true; ?>
        <tr style="opacity:<?=$active?1:.5?>">
          <td><b><?=htmlspecialchars($c)?></b></td>
          <td><?=htmlspecialchars($p['desc']??'')?></td>
          <td class="hint"><?=htmlspecialchars(promo_benefit_label($p['benefit']??''))?></td>
          <td><?=htmlspecialchars($p['expiry']??'—')?></td>
          <td><?=($p['used']??0)?>/<?=($p['max_uses']??'∞')?></td>
          <td><span class="status <?=$active?'offers':'open'?>"><?=$active?'Active':'Off'?></span></td>
          <td><a class="hint" href="/seller-invite?code=<?=urlencode($c)?>" target="_blank">/seller-invite?code=<?=htmlspecialchars($c)?></a></td>
          <td style="white-space:nowrap">
            <form method="post" style="display:inline"><input type="hidden" name="toggle_code" value="<?=htmlspecialchars($c)?>"><button name="toggle_promo" value="1" class="btn btn-o btn-sm"><?=$active?'Pause':'Enable'?></button></form>
            <form method="post" style="display:inline"><input type="hidden" name="del_code" value="<?=htmlspecialchars($c)?>"><button name="delete_promo" value="1" class="btn btn-o btn-sm" style="color:var(--bad);border-color:rgba(239,154,154,.3)" onclick="return confirm('Delete code <?=htmlspecialchars($c)?>?')">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>

    <!-- Outreach email template -->
    <div style="margin-top:20px;background:var(--bg2);border:1px solid var(--line);border-radius:14px;padding:20px">
      <h3 style="margin:0 0 6px">Outreach email template</h3>
      <p class="hint" style="margin:0 0 12px">Copy and personalise for each contact. Enter their name and the code above.</p>
      <pre id="email-tpl" style="background:var(--bg3);border:1px solid var(--line);border-radius:10px;padding:16px;font-size:12.5px;white-space:pre-wrap;line-height:1.6;color:var(--ink);overflow:auto">Subject: Invitation to sell on VESTRA — free access until <?= date('d.m.Y', strtotime('+90 days')) ?>

Dear [Name / Company],

My name is [Your Name] and I'm reaching out on behalf of VESTRA (vestrasales.com), a verified B2B wholesale marketplace for branded fashion across Europe.

We'd love to have you as one of our first verified sellers.

What we offer sellers:
• Access to 500+ verified business buyers in 18 countries
• Built-in escrow — you get paid on time, every time
• Group buy engine — small buyers pool quantities to hit your MOQ
• 0 % listing fees, no subscription
• Multilingual catalog (DE, EN, FR, IT, ES)

Special early-seller offer:
Use invite code [CODE] when registering and receive:
✓ Instant KYB approval — no waiting
✓ 0 % commission for your first 3 months

Register here: https://vestrasales.com/seller-invite?code=[CODE]

Happy to answer any questions — just reply to this email.

Best regards,
[Your Name]
VESTRA Team | support@vestrasales.com</pre>
      <button class="btn btn-o btn-sm" style="margin-top:10px" onclick="navigator.clipboard.writeText(document.getElementById('email-tpl').textContent);this.textContent='Copied!'">Copy template</button>
    </div>

    <script>
    function updateLinks(){
      var q=document.getElementById('scout-q').value||'fashion wholesale seller';
      var qs=encodeURIComponent(q+' wholesale seller Europe');
      document.getElementById('sl-google').href='https://www.google.com/search?q='+encodeURIComponent(q+' wholesale distributor EEA');
      document.getElementById('sl-li').href='https://www.linkedin.com/search/results/people/?keywords='+encodeURIComponent(q+' wholesale fashion');
      document.getElementById('sl-li2').href='https://www.linkedin.com/search/results/companies/?keywords='+encodeURIComponent(q+' wholesale');
      document.getElementById('sl-ep').href='https://www.europages.com/companies/'+encodeURIComponent(q.split(' ')[0])+'.html';
      document.getElementById('sl-km').href='https://www.kompass.com/searchinternational/search.html?text='+encodeURIComponent(q);
      document.getElementById('sl-ig').href='https://www.instagram.com/explore/tags/'+encodeURIComponent(q.replace(/\s+/g,'').toLowerCase())+'wholesale/';
    }
    updateLinks();
    </script>

  <?php elseif($tab==='listings'):
      $rows=array_map(function($p){ return [
        'brand'=>$p['brand']??'', 'name'=>$p['name']??'', 'sku'=>$p['sku']??'', 'cat'=>$p['cat']??'',
        'mode'=>$p['mode']??'', 'moq'=>$p['moq']??'', 'seller'=>$p['seller']??'',
        'photo'=>!empty($p['image'])?'✓':'', 'sheet'=>!empty($p['sheet'])?'✓':'',
      ]; }, $listings);
      atable(array_reverse($rows));
  elseif($tab==='groups'):
      $prows=array_map(function($p){ return [
        'pool'=>$p['brand'].' '.$p['name'], 'sku'=>$p['sku'],
        'committed'=>number_format($p['_committed']), 'target'=>number_format($p['_target']),
        'progress'=>$p['_pct'].'%', 'buyers'=>$p['_participants'], 'unlock_price'=>eur($p['_gprice']),
        'days_left'=>$p['_daysLeft'], 'status'=>$p['_status'],
      ]; }, $pools);
      echo '<h3 style="margin:4px 0 8px">Pools ('.count($pools).')</h3>';
      atable($prows);
      echo '<h3 style="margin:24px 0 8px">Commitments</h3>';
      echo '<div style="margin-bottom:10px"><a class="btn btn-o btn-sm" href="/admin?dl=groups">⬇ Download CSV</a></div>';
      atable($data['groups'][1]);
  else:
      $cur=$data[$tab]??$data['signups']; ?>
    <div style="margin-bottom:10px"><a class="btn btn-o btn-sm" href="/admin?dl=<?=htmlspecialchars($tab)?>">⬇ Download CSV</a></div>
    <?php atable($cur[1]); ?>
  <?php endif; ?>
<?php endif; ?>
</div></body></html>
