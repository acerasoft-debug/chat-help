<?php
/** VESTRA — "make an offer" handler (offer-mode products). Stores to data/offers.csv. */
require __DIR__.'/inc/products.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: /shop'); exit; }
$id=$_POST['id']??''; $p=vestra_find($id);
if(!$p){ header('Location: /shop'); exit; }
if(!empty($_POST['website'])){ header('Location: /product?id='.urlencode($id).'&offered=1&ref=NA'); exit; }

$company=trim($_POST['company']??''); $email=trim($_POST['email']??'');
$qty=max((int)$p['moq'],(int)($_POST['qty']??0)); $price=(float)($_POST['price']??0);
if($company===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$price<=0){ header('Location: /product?id='.urlencode($id).'#post'); exit; }

$one=function($s){ return trim(preg_replace('/\s+/',' ',str_replace(["\r","\n"],' ',(string)$s))); };
$ref='O'.strtoupper(substr(md5($email.$id.microtime(false)),0,5));
$row=[date('c'),$ref,$p['sku'],$p['brand'].' '.$p['name'],$qty,$price,round($qty*$price,2),
      $one($company),$one($email),$one($_POST['message']??'')];

$dir=__DIR__.'/data'; if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/offers.csv'; $new=!file_exists($file);
if($fh=@fopen($file,'a')){
  if($new) fputcsv($fh,['timestamp','ref','sku','product','qty','offer_unit','offer_total','company','email','message']);
  fputcsv($fh,$row); fclose($fh);
}

/* ── Email notifications ── */
$total=round($qty*$price,2);
$adminBody="New offer {$ref} on VESTRA\n\nProduct: {$p['brand']} {$p['name']} [{$p['sku']}]\nCompany: {$company}\nBuyer email: {$email}\nQty: {$qty}   Unit price offered: €{$price}   Total: €{$total}\nMessage: ".$one($_POST['message']??'')."\n\nAdmin: https://vestrasales.com/admin?tab=offers";
vestra_notify("Offer {$ref} — {$p['sku']} — {$company}", $adminBody, $email);

/* Confirmation to the buyer who made the offer */
vestra_send_mail($email, "VESTRA — offer {$ref} received",
  "Hello {$company},\n\nWe have received your offer for:\n\n  {$p['brand']} {$p['name']} ({$p['sku']})\n  Qty: {$qty}   Your price: €{$price}/unit   Total: €{$total}\n\nRef: {$ref}\n\nThe seller will review and respond shortly. You can also view updates in your buyer dashboard:\nhttps://vestrasales.com/buyer?tab=offers\n\n— VESTRA · vestrasales.com");

/* Notify the seller who owns this listing (if seller_uid stored on listing) */
if(!empty($p['seller_uid'])){
  require_once __DIR__.'/inc/auth.php';
  foreach(auth_accounts() as $acc){
    if(($acc['id']??'')!==$p['seller_uid']) continue;
    if(empty($acc['email'])) break;
    vestra_send_mail($acc['email'], "VESTRA — you received an offer ({$ref})",
      "Hello ".($acc['name']?:($acc['company']?:'there')).",\n\nYou received a new offer on VESTRA:\n\nProduct: {$p['brand']} {$p['name']} [{$p['sku']}]\nBuyer: {$company}\nQty: {$qty}   Offered: €{$price}/unit   Total: €{$total}\nMessage: ".$one($_POST['message']??'')."\n\nRef: {$ref}\n\nView and respond in your seller dashboard:\nhttps://vestrasales.com/seller?tab=offers\n\n— VESTRA · vestrasales.com");
    break;
  }
}

header('Location: /product?id='.urlencode($id).'&offered=1&ref='.urlencode($ref)); exit;
