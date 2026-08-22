<?php
/** VESTRA — group-buy "join the pool" handler. Appends to data/groups.csv (+ optional email). */
require __DIR__.'/inc/products.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: /groups'); exit; }
$id=$_POST['id']??'';
$p=vestra_group_pool($id);
if(!$p){ header('Location: /groups'); exit; }
if(!empty($_POST['website'])){ header('Location: /group?id='.urlencode($id).'&joined=1&ref=NA'); exit; } // honeypot

$one=function($s){ return trim(preg_replace('/\s+/',' ',str_replace(["\r","\n"],' ',(string)$s))); };
$company=$one($_POST['company']??''); $name=$one($_POST['name']??''); $email=trim($_POST['email']??'');
$country=$one($_POST['country']??''); $qty=max(vestra_group_min_qty($p),(int)($_POST['qty']??0));
if($company===''||$name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){ header('Location: /group?id='.urlencode($id)); exit; }
if(empty($_POST['consent'])){ header('Location: /group?id='.urlencode($id)); exit; } // Terms acceptance is mandatory

$unit=$p['_gprice']; $est=round($qty*$unit,2);
$ref='G'.strtoupper(substr(md5($email.$id.microtime(false)),0,6));

$dir=vestra_data_dir(); if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/groups.csv'; $new=!file_exists($file);
if($fh=@fopen($file,'a')){
  if($new) fputcsv($fh,['timestamp','pool_id','ref','company','name','email','country','qty','unit_price','est_total','consent','terms_version'],',','"','\\');
  fputcsv($fh,[date('c'),$id,$ref,$company,$name,$email,$country,$qty,$unit,$est,'yes',VESTRA_TERMS_VERSION],',','"','\\');
  fclose($fh);
}

/* Recompute pool state after this commitment (to flag "target reached" in the notice). */
$after=vestra_group_pool($id);
$reached = $after && $after['_committed']>=$after['_target'];

$body="New VESTRA group-buy commitment {$ref}\n\nPool: {$p['brand']} {$p['name']} (SKU {$p['sku']})\n".
      "Buyer: {$name} / {$company} <{$email}>  ".($country?:'')."\n".
      "Committed: {$qty} {$p['unit']} @ €{$unit} = €{$est}\n\n".
      "Pool now: {$after['_committed']} / {$after['_target']} {$p['unit']} ({$after['_pct']}%)".
      ($reached?"  ← TARGET REACHED ✓":"")."\n";
vestra_notify("Group buy {$ref} — {$p['brand']} {$p['name']} ({$after['_pct']}%)", $body, $email);

if(vestra_cfg('confirm_user',false)){
  $msg = $reached
    ? "Hello {$name},\n\nGreat news — the group buy for {$p['brand']} {$p['name']} has reached its target! Your committed {$qty} {$p['unit']} at €{$unit}/{$p['unit']} is locked in. We’ll send your invoice shortly — payment by bank transfer; goods ship after payment.\n\nReference: {$ref}\n\n— VESTRA · Acerasoft LLC"
    : "Hello {$name},\n\nThank you — you’ve joined the group buy for {$p['brand']} {$p['name']} ({$ref}).\nYou committed {$qty} {$p['unit']} at the unlocked price of €{$unit}/{$p['unit']}.\n\nThe pool is at {$after['_pct']}% ({$after['_committed']} / {$after['_target']} {$p['unit']}). We’ll email you the moment it reaches the target and your invoice is ready. If the pool doesn’t fill in time, you pay nothing.\n\n— VESTRA · Acerasoft LLC";
  vestra_send_mail($email, "VESTRA — group buy {$ref} ({$p['brand']} {$p['name']})", $msg);
}

header('Location: /group?id='.urlencode($id).'&joined=1&ref='.urlencode($ref)); exit;
