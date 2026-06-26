<?php
/** VESTRA — seller "add product" handler. Appends to data/listings.json (merged into the live catalog). */
require __DIR__.'/inc/products.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: /seller?tab=add'); exit; }
if(!empty($_POST['website'])){ header('Location: /seller?tab=add&added=1'); exit; }

$one=function($s){ return trim(preg_replace('/\s+/',' ',str_replace(["\r","\n"],' ',(string)$s))); };
$brand=$one($_POST['brand']??''); $name=$one($_POST['name']??''); $origin=$one($_POST['origin']??'');
$moq=max(1,(int)($_POST['moq']??1));
$mode=in_array($_POST['mode']??'',['fixed','sale','offer'],true)?$_POST['mode']:'fixed';
if($brand===''||$name===''||$origin===''){ header('Location: /seller?tab=add'); exit; }

/* tiers */
$tiers=[];
foreach([['t1min','t1price'],['t2min','t2price'],['t3min','t3price']] as $pair){
  $min=(int)($_POST[$pair[0]]??0); $price=(float)($_POST[$pair[1]]??0);
  if($min>0 && $price>0) $tiers[]=['min'=>$min,'price'=>round($price,2)];
}
usort($tiers,function($a,$b){ return $a['min']<=>$b['min']; });
if(!$tiers) $tiers=[['min'=>$moq,'price'=>($mode==='offer'?0:1.00)]];
if($tiers[0]['min']>$moq) $tiers[0]['min']=$moq;

$slug=function($s){ $s=strtolower($s); $s=preg_replace('/[^a-z0-9]+/','-',$s); return trim($s,'-'); };
$id=substr($slug($brand.'-'.$name),0,40).'-'.substr(md5($brand.$name.microtime(false)),0,4);
$sku=$one($_POST['sku']??''); if($sku==='') $sku=strtoupper(substr($slug($brand),0,3).'-'.substr(md5($id),0,5));
$palette=['#1b5e3a','#0f2f5c','#3a0f12','#283b49','#392b4a','#44454e','#3a3320','#23323a'];
$accent=$palette[ hexdec(substr(md5($id),0,2)) % count($palette) ];

$item=[
  'id'=>$id,'brand'=>$brand,'name'=>$name,'mode'=>$mode,
  'cat'=>$one($_POST['cat']??'Other'),'sku'=>$sku,'moq'=>$moq,'unit'=>$one($_POST['unit']??'pc'),
  'desc'=>$one($_POST['desc']??''),'seller'=>$one($_POST['seller']??'Seller'),'origin'=>$origin,
  'verified'=>true,'accent'=>$accent,'tiers'=>$tiers,
];
if($mode==='sale'){ $item['list']=round((float)($_POST['list']??0),2) ?: round($tiers[0]['price']*1.25,2); }
if($mode==='offer'){ $item['guide']='Open to offers'; }
if(!empty($_POST['allow_offers']) && $mode!=='offer') $item['offers']=true; // seller allows negotiation on a priced item

/* ---- uploads: product photo (image) + optional line sheet (Excel/CSV) ---- */
$updir = __DIR__.'/uploads';
if(!is_dir($updir)) @mkdir($updir,0755,true);
/* harden: never execute code from the uploads folder, no directory listing */
if(!is_file($updir.'/.htaccess')){
  @file_put_contents($updir.'/.htaccess',
    "Options -Indexes\n<FilesMatch \"(?i)\\.(php\\d*|phtml|phar|pl|py|cgi|sh|asp|aspx|jsp)$\">\n  Require all denied\n</FilesMatch>\n");
}
$savePhoto = function($field) use ($updir){
  if(($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
  $f=$_FILES[$field]; if($f['size']<=0 || $f['size']>5*1024*1024) return '';
  $info=@getimagesize($f['tmp_name']); if(!$info) return '';               // real image only
  $ext=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp',IMAGETYPE_GIF=>'gif'][$info[2]] ?? '';
  if($ext==='') return '';
  $name='img_'.bin2hex(random_bytes(8)).'.'.$ext;                          // random, safe name
  return @move_uploaded_file($f['tmp_name'],$updir.'/'.$name) ? '/uploads/'.$name : '';
};
$saveSheet = function($field) use ($updir){
  if(($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
  $f=$_FILES[$field]; if($f['size']<=0 || $f['size']>8*1024*1024) return '';
  $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
  if(!in_array($ext,['xlsx','xls','csv'],true)) return '';
  $name='sheet_'.bin2hex(random_bytes(8)).'.'.$ext;
  return @move_uploaded_file($f['tmp_name'],$updir.'/'.$name) ? '/uploads/'.$name : '';
};
if($img=$savePhoto('photo'))  $item['image']=$img;
if($sheet=$saveSheet('sheet')) $item['sheet']=$sheet;

$dir=vestra_data_dir(); if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/listings.json';
$list=vestra_listings(); $list[]=$item;
@file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

header('Location: /seller?tab=add&added=1'); exit;
