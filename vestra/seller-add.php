<?php
/** VESTRA — seller "add product" handler. Appends to data/listings.json (merged into the live catalog). */
require_once __DIR__.'/inc/auth.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/inc/products.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: /seller?tab=add'); exit; }
if(!empty($_POST['website'])){ header('Location: /seller?tab=add&added=1'); exit; }

$_user = auth_user();
if (!$_user || ($_user['type'] ?? '') !== 'seller') {
    header('Location: /login?back=' . urlencode('/seller')); exit;
}
$_ms  = $_user['membership_status'] ?? '';
$_kyb = $_user['kyb_status'] ?? '';
/* "Uyeligi yok ama KYB onayli" satici da urun ekleyebilmeli -- kural buydu, ama
   uygulanmiyordu: auth_register() bu alana 'none' yaziyor, kosul ise === '' (bos
   dize) ariyordu. 'none' !== '' oldugu icin bu dal HIC calismadi ve KYB'si onayli
   her satici /membership?gate=1'e atildi. Iki degeri de "uyelik yok" saymak
   gerekiyor; kaydin yazdigi deger ile kapinin bekledigi deger ayni olmayinca
   kural kagit uzerinde kaliyor. */
$_noMembership = ($_ms === '' || $_ms === 'none');
if (!in_array($_ms, ['trialing', 'active'], true) && !($_noMembership && $_kyb === 'approved')) {
    header('Location: /membership?gate=1'); exit;
}
if (vestra_seller_quota_exhausted($_user)) {
    header('Location: /seller?tab=add&err=quota'); exit;
}

$one=function($s){ return trim(preg_replace('/\s+/',' ',str_replace(["\r","\n"],' ',(string)$s))); };
$brand=$one($_POST['brand']??''); $name=$one($_POST['name']??''); $origin=$one($_POST['origin']??'');
$moq=max(1,(int)($_POST['moq']??1));
$mode=in_array($_POST['mode']??'',['fixed','sale','offer'],true)?$_POST['mode']:'fixed';
if($brand===''||$name===''||$origin===''||empty($_POST['origin_confirm'])){ header('Location: /seller?tab=add'); exit; }

/* tiers */
$tiers=[];
foreach([['t1min','t1price'],['t2min','t2price'],['t3min','t3price']] as $pair){
  $min=(int)($_POST[$pair[0]]??0); $price=(float)($_POST[$pair[1]]??0);
  if($min>0 && $price>0) $tiers[]=['min'=>$min,'price'=>round($price,2)];
}
usort($tiers,function($a,$b){ return $a['min']<=>$b['min']; });
if(!$tiers){ header('Location: /seller?tab=add&err=1'); exit; }
if($tiers[0]['min']>$moq) $tiers[0]['min']=$moq;

$slug=function($s){ $s=strtolower($s); $s=preg_replace('/[^a-z0-9]+/','-',$s); return trim($s,'-'); };
$id=substr($slug($brand.'-'.$name),0,40).'-'.substr(md5($brand.$name.microtime(false)),0,4);
$sku=$one($_POST['sku']??''); if($sku==='') $sku=strtoupper(substr($slug($brand),0,3).'-'.substr(md5($id),0,5));
$palette=['#1b5e3a','#0f2f5c','#3a0f12','#283b49','#392b4a','#44454e','#3a3320','#23323a'];
$accent=$palette[ hexdec(substr(md5($id),0,2)) % count($palette) ];

$item=[
  'id'=>$id,'brand'=>$brand,'name'=>$name,'mode'=>$mode,'status'=>'pending','added_at'=>date('c'),
  'cat'=>$one($_POST['cat']??'Other'),'sku'=>$sku,'moq'=>$moq,'unit'=>$one($_POST['unit']??'pc'),
  'desc'=>$one($_POST['desc']??''),'seller'=>$one($_POST['seller']??'Seller'),'origin'=>$origin,
  'verified'=>$_kyb==='approved','accent'=>$accent,'tiers'=>$tiers,
  'seller_uid'=>$_SESSION['uid']??'',
];
$colors=array_values(array_intersect((array)($_POST['colors']??[]), array_keys(vestra_colors())));
if($colors) $item['colors']=$colors;
/* Pack size (order in multiples of N) + minimum colour count, as in the curated catalog */
$step=max(0,(int)($_POST['size_step']??0)); if($step>1) $item['size_step']=$step;
$minC=max(0,(int)($_POST['min_colors']??0)); if($minC>0 && $colors && $minC<=count($colors)) $item['min_colors']=$minC;
if($mode==='sale'){ $item['list']=round((float)($_POST['list']??0),2) ?: round($tiers[0]['price']*1.25,2); }
if($mode==='offer'){ $item['guide']='Open to offers'; }
if(!empty($_POST['allow_offers']) && $mode!=='offer') $item['offers']=true; // seller allows negotiation on a priced item
if(!empty($_POST['group_enable']) && $mode!=='offer'){ // seller opens this product for collective group buying
  $item['group']=true;
  $gt=(int)($_POST['group_target']??0); if($gt>0) $item['group_target']=$gt;
  $days=(int)($_POST['group_days']??VESTRA_GROUP_DEFAULT_DAYS); if($days<1||$days>90) $days=VESTRA_GROUP_DEFAULT_DAYS;
  $item['group_started']=date('c');
  $item['group_deadline']=date('c', strtotime("+{$days} days"));
}

/* ---- uploads: product photos + optional line sheet (Excel/CSV) ---- */
$updir = __DIR__.'/uploads';
$saveSheet = function($field) use ($updir){
  if(!is_dir($updir)) @mkdir($updir,0755,true);
  if(($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
  $f=$_FILES[$field]; if($f['size']<=0 || $f['size']>8*1024*1024) return '';
  $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
  if(!in_array($ext,['xlsx','xls','csv'],true)) return '';
  $name='sheet_'.bin2hex(random_bytes(8)).'.'.$ext;
  return @move_uploaded_file($f['tmp_name'],$updir.'/'.$name) ? '/uploads/'.$name : '';
};
$images = vestra_collect_photo_uploads('photos', 6);
if($images){ $item['images']=$images; $item['image']=$images[0]; }
if($sheet=$saveSheet('sheet')) $item['sheet']=$sheet;

$list=vestra_listings(); $list[]=$item;
vestra_save_listings($list);
if(!empty($_SESSION['uid'])) vestra_seller_monthly_quota_bump($_SESSION['uid']);

/* Notify admin about new pending listing */
$sellerName = $item['seller'] ?: ($item['seller_uid'] ? 'uid:'.$item['seller_uid'] : 'unknown');
vestra_notify(
  "New listing pending approval: {$brand} {$name} [{$sku}]",
  "A seller submitted a new listing for review.\n\nBrand: {$brand}\nProduct: {$name}\nSKU: {$sku}\nCategory: {$item['cat']}\nMode: {$mode}\nOrigin: {$origin}\nSeller: {$sellerName}\n\nReview and approve: https://vestrasales.com/admin?tab=approvals"
);

header('Location: /seller?tab=listings&pending=1'); exit;
