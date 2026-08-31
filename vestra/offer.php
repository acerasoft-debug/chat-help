<?php
/** VESTRA — "make an offer" handler (offer-mode products). Stores to data/offers.csv. */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
if(session_status()===PHP_SESSION_NONE) session_start();
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: /shop'); exit; }
$id=$_POST['id']??''; $p=vestra_find($id);
if(!$p){ header('Location: /shop'); exit; }
if(!empty($_POST['website'])){ header('Location: /product?id='.urlencode($id).'&offered=1&ref=NA'); exit; }

$company=trim($_POST['company']??''); $email=trim($_POST['email']??'');
/* Virgullu ondalik: (float)"35,50" 35.00 verirdi. bkz. vestra_price_input(). */
$price=vestra_price_input($_POST['price']??'');

/* Per-colour carton pickers (Lacoste/RL: min colours + pack step) drive qty from the
   colour breakdown itself — the plain "qty" field is never trusted for these listings. */
$cq = vestra_parse_colorqty($p, (array)($_POST['cq'] ?? []));
if ($cq !== null) {
  $qty = $cq['qty'];
  $colors = $cq['lines']; // e.g. ["Black ×16", "Navy ×8"]
  if (count($colors) < (int)$p['min_colors'] || $qty < (int)$p['moq']) {
    header('Location: /product?id='.urlencode($id).'&colerr=1'); exit;
  }
} else {
  $qty=max((int)$p['moq'],(int)($_POST['qty']??0));
  if(!empty($p['size_step']) && $qty % (int)$p['size_step'] !== 0)
    $qty = (int)(ceil($qty/(int)$p['size_step']) * (int)$p['size_step']);   // snap to pack/lot size
  /* Mandatory colour choice (listings with min_colors) — only offered colours count */
  $colors = array_values(array_unique(array_intersect(
      array_map('strval', (array)($_POST['colors']??[])), (array)($p['colors']??[]) )));
  if(!empty($p['min_colors']) && count($colors) < (int)$p['min_colors']){
    header('Location: /product?id='.urlencode($id).'&colerr=1'); exit;
  }
}
if($company===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$price<=0){ header('Location: /product?id='.urlencode($id).'#post'); exit; }
/* FIYAT TABANI: teklif urunun yarisindan az olamaz. Sebep kullaniciya
   YAZILIR -- eskiden gecersiz bir teklif de sessizce kaydediliyordu ve
   operator elle reddetmek zorunda kaliyordu. Metin tek kaynaktan
   (vestra_offer_price_error) geliyor ki uc ekranda uc cumle olmasin. */
require_once __DIR__.'/inc/offers.php';
$__pErr = vestra_offer_price_error($p, 'buyer', $price);
if($__pErr !== null){
  if(session_status()===PHP_SESSION_NONE) session_start();
  $_SESSION['offer_price_err'] = $__pErr;
  header('Location: /product?id='.urlencode($id).'&pricerr=1#post'); exit;
}
$colorsTxt = $colors ? 'Colours: '.implode(', ', $colors) : '';

$one=function($s){ return trim(preg_replace('/\s+/',' ',str_replace(["\r","\n"],' ',(string)$s))); };
$ref='O'.strtoupper(substr(md5($email.$id.microtime(false)),0,5));
$row=[date('c'),$ref,$p['sku'],$p['brand'].' '.$p['name'],$qty,$price,round($qty*$price,2),
      $one($company),$one($email),$one(trim($colorsTxt.($colorsTxt!==''&&trim($_POST['message']??'')!==''?' — ':'').($_POST['message']??'')))];

$dir=__DIR__.'/data'; if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/offers.csv'; $new=!file_exists($file);
if($fh=@fopen($file,'a')){
  if($new) fputcsv($fh,['timestamp','ref','sku','product','qty','offer_unit','offer_total','company','email','message'],',','"','\\');
  fputcsv($fh,$row,',','"','\\'); fclose($fh);
}

/* ── Email notifications ── */
$total=round($qty*$price,2);
$adminBody="New offer {$ref} on VESTRA\n\nProduct: {$p['brand']} {$p['name']} [{$p['sku']}]\nCompany: {$company}\nBuyer email: {$email}\nQty: {$qty}   Unit price offered: €{$price}   Total: €{$total}\n".($colorsTxt!==''?$colorsTxt."\n":"")."Message: ".$one($_POST['message']??'')."\n\nAdmin: https://vestrasales.com/admin?tab=offers";
vestra_notify("Offer {$ref} — {$p['sku']} — {$company}", $adminBody, $email);

/* Confirmation to the buyer who made the offer, in their own saved language when they
 * have an account (guest checkouts fall back to English). */
$buyerAccForLang = auth_find($email);
$buyerLang = vestra_user_lang($buyerAccForLang);
[$rSubj,$rBody,$rOpts] = vestra_tpl_offer_received($buyerLang, $company, trim($p['brand'].' '.$p['name']), $p['sku'], $qty, $price, $total, $ref, $colorsTxt);
vestra_send_mail($email, $rSubj, $rBody, '', '', null, '', $rOpts);

/* Drop the offer into the buyer↔seller message thread as a prominent card, so the
   seller sees it like a new message (unread badge + inbox entry), not just a table row. */
if(!empty($p['seller_uid']) && !empty($_SESSION['uid'])){
  $me = auth_user();
  if($me && $me['id']!==$p['seller_uid']){
    require_once __DIR__.'/inc/messages.php';
    vestra_msg_post_system($me['id'], $p['seller_uid'], $p['id'], [
      'kind'=>'offer', 'ref'=>$ref, 'product'=>$p['brand'].' '.$p['name'], 'sku'=>$p['sku'],
      'qty'=>$qty, 'unit_price'=>$price, 'total'=>$total, 'colors'=>$colors,
    ]);
  }
}

/* Notify the seller who owns this listing (if seller_uid stored on listing) */
if(!empty($p['seller_uid'])){
  require_once __DIR__.'/inc/auth.php';
  require_once __DIR__.'/inc/push.php';
  vestra_push_send($p['seller_uid'], 'VESTRA — new offer 💶',
    trim(($p['brand']??'').' '.($p['name']??'')).' · €'.$price.' × '.$qty, '/seller?tab=offers');
  foreach(auth_accounts() as $acc){
    if(($acc['id']??'')!==$p['seller_uid']) continue;
    if(empty($acc['email'])) break;
    // Reads as coming from the buyer's own company (Reply-To -> straight back to them),
    // while the technical envelope stays VESTRA's own verified sender.
    [$nSubj,$nBody,$nOpts] = vestra_tpl_offer_new(vestra_user_lang($acc), $acc['name']?:($acc['company']?:'there'),
      $company, trim($p['brand'].' '.$p['name']), $p['sku'], $qty, $price, $total, $ref, $colorsTxt, $one($_POST['message']??''));
    vestra_send_mail($acc['email'], $nSubj, $nBody, $email, $company, null, '', $nOpts);
    break;
  }
}

header('Location: /product?id='.urlencode($id).'&offered=1&ref='.urlencode($ref)); exit;
