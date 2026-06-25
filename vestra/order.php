<?php
/** VESTRA — order request handler (demo). Stores to data/orders.csv (+ optional email). */
require __DIR__.'/inc/products.php';
$CONTACT='hello@vestrasales.com'; $NOTIFY=false;

if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: cart.php'); exit; }
if(!empty($_POST['website'])){ header('Location: cart.php?placed=1&ref=NA'); exit; } // honeypot

$company=trim($_POST['company']??''); $name=trim($_POST['name']??''); $email=trim($_POST['email']??'');
if($company===''||$name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){ header('Location: cart.php'); exit; }

$cart=json_decode($_POST['cart']??'[]', true); if(!is_array($cart)) $cart=[];

/* Re-price server-side against the real catalog (never trust client prices) */
$lines=[]; $subtotal=0;
foreach($cart as $it){
  $p=vestra_find($it['id']??''); if(!$p) continue;
  $qty=max((int)$p['moq'], (int)($it['qty']??0));
  $unit=vestra_unit_price($p,$qty); $line=$qty*$unit; $subtotal+=$line;
  $lines[]=['sku'=>$p['sku'],'brand'=>$p['brand'],'name'=>$p['name'],'qty'=>$qty,'unit'=>$unit,'line'=>$line];
}
if(!$lines){ header('Location: cart.php'); exit; }
/* Platform commission — flip $COMMISSION_SIDE to change who pays (no re-pricing change). */
$COMMISSION_RATE = 0.08;     // 8%
$COMMISSION_SIDE = 'seller'; // 'seller' = buyer pays list, comm. from payout | 'buyer' = added on top | 'split'
$commission = round($subtotal*$COMMISSION_RATE, 2);
if     ($COMMISSION_SIDE==='buyer'){ $total=round($subtotal+$commission,2);   $payout=$subtotal; }
elseif ($COMMISSION_SIDE==='split'){ $total=round($subtotal+$commission/2,2); $payout=round($subtotal-$commission/2,2); }
else                               { $total=$subtotal;                        $payout=round($subtotal-$commission,2); }
$ref='VES-'.strtoupper(substr(md5($email.implode('',array_column($lines,'sku')).count($lines)),0,8));

$dir=__DIR__.'/data'; if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/orders.csv'; $new=!file_exists($file);
if($fh=@fopen($file,'a')){
  if($new) fputcsv($fh,['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes']);
  $items=implode(' | ', array_map(function($l){return $l['qty'].'x '.$l['sku'].' @'.$l['unit'];}, $lines));
  fputcsv($fh,[date('c'),$ref,$company,trim($_POST['vat']??''),$name,$email,trim($_POST['country']??''),
    trim($_POST['phone']??''),$items,$subtotal,$commission,$payout,$total,trim($_POST['notes']??'')]);
  fclose($fh);
}
if($NOTIFY && $CONTACT){
  $body="New order request {$ref}\n\nCompany: {$company}\nContact: {$name} <{$email}>\n\n";
  foreach($lines as $l){ $body.="{$l['qty']}x {$l['sku']} {$l['brand']} {$l['name']} @ €{$l['unit']} = €{$l['line']}\n"; }
  $body.="\nBuyer pays €{$total}\nVESTRA commission ".($COMMISSION_RATE*100)."% €{$commission} · Seller payout €{$payout}\n";
  @mail($CONTACT,"VESTRA order {$ref} — {$company}",$body,"From: {$CONTACT}\r\nReply-To: {$email}");
}
header('Location: cart.php?placed=1&ref='.urlencode($ref)); exit;
