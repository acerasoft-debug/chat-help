<?php
/* VESTRA one-time setup — creates inc/config.php then self-destructs. */
$cfg = __DIR__.'/inc/config.php';
$done = false; $err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $pass = $_POST['admin_pass'] ?? '';
  $notify = trim($_POST['notify'] ?? 'acerasoft@gmail.com');
  if(strlen($pass)<8){ $err='Password must be at least 8 characters.'; }
  else {
    $content = "<?php\nreturn [\n".
      "  'admin_pass'   => ".var_export($pass,true).",\n".
      "  'notify'       => [".var_export($notify,true)."],\n".
      "  'mail_from'    => 'support@vestrasales.com',\n".
      "  'confirm_user' => true,\n".
      "  'mail_enabled' => true,\n".
      "];\n";
    if(file_put_contents($cfg, $content)!==false){
      @unlink(__FILE__); // self-delete
      $done = true;
    } else { $err='Could not write config.php — check folder permissions.'; }
  }
}
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VESTRA Setup</title>
<link rel="stylesheet" href="/inc/style.css">
</head><body>
<div class="wrap" style="max-width:480px;margin:60px auto">
<?php if($done): ?>
  <div style="text-align:center;padding:40px 0">
    <div style="font-size:48px;margin-bottom:12px">✓</div>
    <h2 style="margin:0 0 8px">Setup complete</h2>
    <p style="color:var(--mut);margin:0 0 24px">config.php created. This page has been deleted.</p>
    <a class="btn btn-p" href="/admin">Go to Admin Panel</a>
  </div>
<?php else: ?>
  <h2 style="margin-bottom:4px">VESTRA Setup</h2>
  <p style="color:var(--mut);margin:0 0 24px;font-size:14px">Creates <code>inc/config.php</code> and deletes this page.</p>
  <?php if($err): ?>
    <div style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#ef9a9a;padding:10px 14px;border-radius:8px;margin-bottom:14px"><?=htmlspecialchars($err)?></div>
  <?php endif; ?>
  <form method="post" style="display:flex;flex-direction:column;gap:14px">
    <div>
      <label style="display:block;margin-bottom:4px;font-size:13px;color:var(--mut)">Admin password (min 8 chars)</label>
      <input type="password" name="admin_pass" required minlength="8" placeholder="Choose a strong password" style="width:100%">
    </div>
    <div>
      <label style="display:block;margin-bottom:4px;font-size:13px;color:var(--mut)">Notification email</label>
      <input type="email" name="notify" value="acerasoft@gmail.com" style="width:100%">
    </div>
    <button class="btn btn-p" type="submit" style="justify-content:center">Create config &amp; go to admin</button>
  </form>
<?php endif; ?>
</div></body></html>
