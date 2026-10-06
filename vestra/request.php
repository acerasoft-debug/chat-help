<?php
/** VESTRA — sourcing request handler. Stores to data/requests.csv. */
require __DIR__.'/inc/products.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: /requests'); exit; }
if(!empty($_POST['website'])){ header('Location: /requests?posted=1&ref=NA'); exit; }

$title=trim($_POST['title']??''); $email=trim($_POST['email']??'');
if($title===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){ header('Location: /requests#post'); exit; }

$one=function($s){ return trim(preg_replace('/\s+/',' ',str_replace(["\r","\n"],' ',(string)$s))); };
$ref='R'.strtoupper(substr(md5($email.$title.microtime(false)),0,5));
$cat=$one($_POST['cat']??''); $qty=$one($_POST['qty']??''); $target=$one($_POST['target']??''); $country=$one($_POST['country']??''); $notes=$one($_POST['notes']??'');

/* Reference link/photo — purely a pointer for sellers to see what the buyer means.
   No fetching/scraping of the linked page happens anywhere on VESTRA. */
$refUrl = trim($_POST['ref_url'] ?? '');
if ($refUrl !== '' && (!filter_var($refUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $refUrl))) $refUrl = '';
$refImage = !empty($_FILES['ref_image']['name']) ? vestra_save_upload_photo($_FILES['ref_image']) : '';

$row=[date('c'),$ref,$one($title),$cat,$qty,$target,$country,$one($email),$notes,$refUrl,$refImage];

$dir=__DIR__.'/data'; if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/requests.csv'; $new=!file_exists($file);
$header=['timestamp','ref','title','cat','qty','target','country','email','notes','ref_url','ref_image'];
if(!$new) vestra_csv_ensure_header('requests.csv',$header);
if($fh=@fopen($file,'a')){
  if($new) fputcsv($fh,$header,',','"','\\');
  fputcsv($fh,$row,',','"','\\'); fclose($fh);
}
vestra_notify("New sourcing request {$ref} — {$title}",
  "A buyer posted a new sourcing request on VESTRA:\n\nTitle: {$title}\nCategory: {$cat}\nQty: {$qty}\nTarget: {$target}\nCountry: {$country}\nNotes: {$notes}\n\nRespond: https://vestrasales.com/requests\nAdmin: https://vestrasales.com/admin?tab=requests",
  $email);
header('Location: /requests?posted=1&ref='.urlencode($ref)); exit;
