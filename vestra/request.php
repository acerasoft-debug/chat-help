<?php
/** VESTRA — sourcing request handler. Stores to data/requests.csv. */
$CONTACT='hello@vestra.example'; $NOTIFY=false;
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: requests.php'); exit; }
if(!empty($_POST['website'])){ header('Location: requests.php?posted=1&ref=NA'); exit; }

$title=trim($_POST['title']??''); $email=trim($_POST['email']??'');
if($title===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){ header('Location: requests.php#post'); exit; }

$one=function($s){ return trim(preg_replace('/\s+/',' ',str_replace(["\r","\n"],' ',(string)$s))); };
$ref='R'.strtoupper(substr(md5($email.$title.microtime(false)),0,5));
$row=[date('c'),$ref,$one($title),$one($_POST['cat']??''),$one($_POST['qty']??''),
      $one($_POST['target']??''),$one($_POST['country']??''),$one($email),$one($_POST['notes']??'')];

$dir=__DIR__.'/data'; if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/requests.csv'; $new=!file_exists($file);
if($fh=@fopen($file,'a')){
  if($new) fputcsv($fh,['timestamp','ref','title','cat','qty','target','country','email','notes']);
  fputcsv($fh,$row); fclose($fh);
}
if($NOTIFY && $CONTACT){
  @mail($CONTACT,"VESTRA sourcing request {$ref}","{$title}\nQty: ".($_POST['qty']??'')."\nTarget: ".($_POST['target']??'')."\nFrom: {$email}\n","From: {$CONTACT}\r\nReply-To: {$email}");
}
header('Location: requests.php?posted=1&ref='.urlencode($ref)); exit;
