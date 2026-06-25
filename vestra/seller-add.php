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

$dir=vestra_data_dir(); if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/listings.json';
$list=vestra_listings(); $list[]=$item;
@file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

header('Location: /seller?tab=add&added=1'); exit;
