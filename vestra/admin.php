<?php
/** VESTRA — private admin panel. Password is set in inc/config.php (admin_pass). */
require __DIR__.'/inc/products.php';
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

/* CSV download (admin only) */
if($authed && isset($_GET['dl'])){
  $map=['signups'=>'signups.csv','orders'=>'orders.csv','offers'=>'offers.csv','requests'=>'requests.csv'];
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
  $tab=$_GET['tab']??'signups';
  $data=[
    'signups' =>['Registrations', vestra_read_csv('signups.csv')],
    'orders'  =>['Orders',        vestra_read_csv('orders.csv')],
    'offers'  =>['Offers',        vestra_read_csv('offers.csv')],
    'requests'=>['Requests',      vestra_read_csv('requests.csv')],
  ];
  $listings=vestra_listings();
?>
  <div class="adminbar">
    <h1 style="margin:0">VESTRA Admin</h1>
    <span style="flex:1"></span>
    <a class="btn btn-o btn-sm" href="/">View site</a>
    <a class="btn btn-o btn-sm" href="/admin?logout=1">Sign out</a>
  </div>
  <div class="atabs">
    <?php foreach($data as $k=>$d): ?>
      <a href="/admin?tab=<?=$k?>" class="<?=$tab===$k?'on':''?>"><?=$d[0]?> (<?=count($d[1])?>)</a>
    <?php endforeach; ?>
    <a href="/admin?tab=listings" class="<?=$tab==='listings'?'on':''?>">Listings (<?=count($listings)?>)</a>
  </div>
  <?php if($tab==='listings'):
      $rows=array_map(function($p){ return [
        'brand'=>$p['brand']??'', 'name'=>$p['name']??'', 'sku'=>$p['sku']??'', 'cat'=>$p['cat']??'',
        'mode'=>$p['mode']??'', 'moq'=>$p['moq']??'', 'seller'=>$p['seller']??'',
        'photo'=>!empty($p['image'])?'✓':'', 'sheet'=>!empty($p['sheet'])?'✓':'',
      ]; }, $listings);
      atable(array_reverse($rows));
  else:
      $cur=$data[$tab]??$data['signups']; ?>
    <div style="margin-bottom:10px"><a class="btn btn-o btn-sm" href="/admin?dl=<?=htmlspecialchars($tab)?>">⬇ Download CSV</a></div>
    <?php atable($cur[1]); ?>
  <?php endif; ?>
<?php endif; ?>
</div></body></html>
