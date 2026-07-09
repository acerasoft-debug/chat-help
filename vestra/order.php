<?php
/** VESTRA — order request handler (demo). Stores to data/orders.csv (+ optional email). */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$CONTACT='support@vestrasales.com'; $NOTIFY=false;

if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: /cart'); exit; }
if(!empty($_POST['website'])){ header('Location: /cart?placed=1&ref=NA'); exit; } // honeypot

$company=trim($_POST['company']??''); $name=trim($_POST['name']??''); $email=trim($_POST['email']??'');
if($company===''||$name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){ header('Location: /cart'); exit; }
if(empty($_POST['consent'])){ header('Location: /cart'); exit; } // Terms acceptance is mandatory

$cart=json_decode($_POST['cart']??'[]', true); if(!is_array($cart)) $cart=[];

/* Re-price server-side against the real catalog (never trust client prices) */
$lines=[]; $subtotal=0;
foreach($cart as $it){
  $p=vestra_find($it['id']??''); if(!$p) continue;
  $qty=max((int)$p['moq'], (int)($it['qty']??0));
  $unit=vestra_unit_price($p,$qty); if($unit<=0) continue;
  $line=$qty*$unit; $subtotal+=$line;
  $lines[]=['sku'=>$p['sku'],'brand'=>$p['brand'],'name'=>$p['name'],'qty'=>$qty,'unit'=>$unit,'line'=>$line];
}
if(!$lines){ header('Location: /cart'); exit; }
/* Platform commission — set seller- and buyer-side rates independently. */
$FEE_SELLER = VESTRA_FEE_SELLER; // configured in inc/products.php (6% seller)
$FEE_BUYER  = VESTRA_FEE_BUYER;  // configured in inc/products.php (2% buyer)
$buyer_fee  = round($subtotal*$FEE_BUYER, 2);
$seller_fee = round($subtotal*$FEE_SELLER, 2);
$commission = round($buyer_fee + $seller_fee, 2); // total platform revenue
$total      = round($subtotal + $buyer_fee, 2);   // what the buyer pays
$payout     = round($subtotal - $seller_fee, 2);  // what the seller receives
$ref='VES-'.strtoupper(substr(md5($email.implode('',array_column($lines,'sku')).count($lines)),0,8));

$dir=__DIR__.'/data'; if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/orders.csv'; $new=!file_exists($file);
if($fh=@fopen($file,'a')){
  if($new) fputcsv($fh,['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version']);
  $items=implode(' | ', array_map(function($l){return $l['qty'].'x '.$l['sku'].' @'.$l['unit'];}, $lines));
  fputcsv($fh,[date('c'),$ref,$company,trim($_POST['vat']??''),$name,$email,trim($_POST['country']??''),
    trim($_POST['phone']??''),$items,$subtotal,$commission,$payout,$total,trim($_POST['notes']??''),'yes',VESTRA_TERMS_VERSION]);
  fclose($fh);
}
$body="New VESTRA order request {$ref}\n\nCompany: {$company}\nContact: {$name} <{$email}>\nCountry: ".trim($_POST['country']??'')."   Phone: ".trim($_POST['phone']??'')."\n\n";
foreach($lines as $l){ $body.="  {$l['qty']}x {$l['sku']} {$l['brand']} {$l['name']} @ €{$l['unit']} = €{$l['line']}\n"; }
$body.="\nSubtotal €{$subtotal}\nBuyer pays €{$total}\nVESTRA commission €{$commission} (seller €{$seller_fee} + buyer €{$buyer_fee}) · Seller payout €{$payout}\nNotes: ".trim($_POST['notes']??'')."\n";
vestra_notify("New order {$ref} — {$company}", $body, $email);

$FEE_BUYER_PCT=round($FEE_BUYER*100);
/* Confirmation to buyer — always on */
vestra_send_mail($email, "VESTRA — order {$ref} received",
  "Hello {$name},\n\nThank you — your VESTRA order request ({$ref}) has been received.\n\nWe will confirm seller availability and send a secured (escrow) payment link.\n\nBuyer pays: €{$total} (includes {$FEE_BUYER_PCT}% buyer-protection fee)\n\n--- Order summary ---\n".implode("\n",array_map(fn($l)=>"  {$l['qty']}x {$l['sku']} {$l['brand']} {$l['name']} @ €{$l['unit']} = €{$l['line']}",$lines))."\n\nTrack your order: https://vestrasales.com/buyer?tab=orders\n\n— VESTRA · vestrasales.com");

/* Notify the seller(s) who own the ordered listings */
if(!empty($lines)){
  require_once __DIR__.'/inc/auth.php';
  /* Order card in the buyer↔seller conversation: the whole trade lives in one place. */
  $buyerAcc = !empty($_SESSION['uid']) ? auth_user() : auth_find($email);
  if($buyerAcc && ($buyerAcc['type']??'')!=='buyer') $buyerAcc = null;
  $itemsSummary = implode(' · ', array_map(fn($l)=>$l['qty'].'× '.$l['brand'].' '.$l['name'], $lines));
  $notifiedSellers=[];
  $allListings=vestra_listings();
  foreach($lines as $l){
    foreach($allListings as $listing){
      if(($listing['sku']??'')!==$l['sku']||empty($listing['seller_uid'])) continue;
      $sid=$listing['seller_uid'];
      if(in_array($sid,$notifiedSellers,true)) break;
      $notifiedSellers[]=$sid;
      if($buyerAcc){
        require_once __DIR__.'/inc/messages.php';
        vestra_msg_post_system($buyerAcc['id'], $sid, '', [
          'kind'=>'order','status'=>'placed','ref'=>$ref,
          'items'=>mb_substr($itemsSummary,0,160),'total'=>$total,
        ]);
      }
      foreach(auth_accounts() as $acc){
        if(($acc['id']??'')!==$sid||empty($acc['email'])) continue;
        vestra_send_mail($acc['email'], "VESTRA — new order {$ref} for your listing",
          "Hello ".($acc['name']?:($acc['company']?:'there')).",\n\nA buyer placed an order for your product on VESTRA:\n\nOrder ref: {$ref}\nBuyer company: {$company}\n\n".implode("\n",array_map(fn($x)=>"  {$x['qty']}x {$x['sku']} {$x['brand']} {$x['name']} @ €{$x['unit']}",$lines))."\n\nSubtotal: €{$subtotal}   Your payout (after commission): €{$payout}\n\nView in your seller dashboard:\nhttps://vestrasales.com/seller?tab=orders\n\n— VESTRA · vestrasales.com");
        break;
      }
      break;
    }
  }
}
header('Location: /cart?placed=1&ref='.urlencode($ref)); exit;
