<?php
/** VESTRA — "make an offer" handler (offer-mode products). Stores to data/offers.csv. */
require __DIR__.'/inc/products.php';
$CONTACT='hello@vestrasales.com'; $NOTIFY=false;
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: shop.php'); exit; }
$id=$_POST['id']??''; $p=vestra_find($id);
if(!$p){ header('Location: shop.php'); exit; }
if(!empty($_POST['website'])){ header('Location: product.php?id='.urlencode($id).'&offered=1&ref=NA'); exit; }

$company=trim($_POST['company']??''); $email=trim($_POST['email']??'');
$qty=max((int)$p['moq'],(int)($_POST['qty']??0)); $price=(float)($_POST['price']??0);
if($company===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$price<=0){ header('Location: product.php?id='.urlencode($id).'#post'); exit; }

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
if($NOTIFY && $CONTACT){
  @mail($CONTACT,"VESTRA offer {$ref} — {$p['sku']}","{$company} offers €{$price}/unit x {$qty} for {$p['brand']} {$p['name']}\nFrom: {$email}\n","From: {$CONTACT}\r\nReply-To: {$email}");
}
header('Location: product.php?id='.urlencode($id).'&offered=1&ref='.urlencode($ref)); exit;
