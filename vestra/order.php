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
  /* Per-colour carton pickers (Lacoste/RL: min colours + pack step) drive qty from the
     colour breakdown itself, re-derived + re-validated from the client's tokens — the
     posted "qty" is never trusted for these listings. */
  $cq = vestra_parse_colorqty_tokens($p, (array)($it['colors']??[]));
  if($cq !== null){
    $qty = $cq['qty']; $colors = $cq['lines'];
    if(count($colors) < (int)$p['min_colors'] || $qty < (int)$p['moq']){ header('Location: /cart?err=colors'); exit; }
  } else {
    $qty=max((int)$p['moq'], (int)($it['qty']??0));
    if(!empty($p['size_step']) && $qty % (int)$p['size_step'] !== 0)
      $qty = (int)(ceil($qty/(int)$p['size_step']) * (int)$p['size_step']);   // snap to pack/lot size
    /* Colour selection: only colours the listing actually offers count; enforce the minimum. */
    $colors = array_values(array_unique(array_intersect(
        array_map('strval', (array)($it['colors']??[])), (array)($p['colors']??[]) )));
    if(!empty($p['min_colors']) && count($colors) < (int)$p['min_colors']){ header('Location: /cart?err=colors'); exit; }
  }
  $unit=vestra_unit_price($p,$qty); if($unit<=0) continue;
  $line=$qty*$unit; $subtotal+=$line;
  $lines[]=['sku'=>$p['sku'],'brand'=>$p['brand'],'name'=>$p['name'],'qty'=>$qty,'unit'=>$unit,'line'=>$line,'colors'=>$colors,'seller_uid'=>$p['seller_uid']??''];
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
  if($new) fputcsv($fh,['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version'],',','"','\\');
  $items=implode(' | ', array_map(function($l){return $l['qty'].'x '.$l['sku'].' @'.$l['unit'];}, $lines));
  $colorNotes=implode(' | ', array_map(fn($l)=>$l['sku'].': '.implode(', ',$l['colors']),
    array_filter($lines, fn($l)=>!empty($l['colors']))));
  $notes=trim(($colorNotes!==''?'Colours — '.$colorNotes.'. ':'').trim($_POST['notes']??''));
  fputcsv($fh,[date('c'),$ref,$company,trim($_POST['vat']??''),$name,$email,trim($_POST['country']??''),
    trim($_POST['phone']??''),$items,$subtotal,$commission,$payout,$total,$notes,'yes',VESTRA_TERMS_VERSION],',','"','\\');
  fclose($fh);
}
$body="New VESTRA order request {$ref}\n\nCompany: {$company}\nContact: {$name} <{$email}>\nCountry: ".trim($_POST['country']??'')."   Phone: ".trim($_POST['phone']??'')."\n\n";
foreach($lines as $l){ $body.="  {$l['qty']}x {$l['sku']} {$l['brand']} {$l['name']} @ €{$l['unit']} = €{$l['line']}".(!empty($l['colors'])?" [".implode(", ",$l['colors'])."]":"")."\n"; }
$body.="\nSubtotal €{$subtotal}\nBuyer pays €{$total}\n".($commission>0?"VESTRA commission €{$commission} (seller €{$seller_fee} + buyer €{$buyer_fee}) · Seller payout €{$payout}\n":"No platform fees (membership model) · Seller receives €{$payout}\n")."Notes: ".trim($_POST['notes']??'')."\n";
vestra_notify("New order {$ref} — {$company}", $body, $email);

$FEE_BUYER_PCT=round($FEE_BUYER*100);
$feeNote=$FEE_BUYER_PCT>0?" (includes {$FEE_BUYER_PCT}% buyer-protection fee)":"";
/* Confirmation to buyer — always on */
vestra_send_mail($email, "VESTRA — order {$ref} received",
  "Hello {$name},\n\nThank you — your VESTRA order request ({$ref}) has been received.\n\nYour PDF invoice(s) with the seller's bank details are ready — download them on your confirmation page or under My orders. Payment is by bank transfer against the invoice; goods ship after payment. (Other payment methods are temporarily suspended.)\n\nBuyer pays: €{$total}{$feeNote}\n\n--- Order summary ---\n".implode("\n",array_map(fn($l)=>"  {$l['qty']}x {$l['sku']} {$l['brand']} {$l['name']} @ €{$l['unit']} = €{$l['line']}".(!empty($l['colors'])?" [".implode(", ",$l['colors'])."]":""),$lines))."\n\nTrack your order: https://vestrasales.com/buyer?tab=orders\n\n— VESTRA · vestrasales.com");

/* Notify the seller(s) who own the ordered listings */
if(!empty($lines)){
  require_once __DIR__.'/inc/auth.php';
  /* Order card in the buyer↔seller conversation: the whole trade lives in one place. */
  $buyerAcc = !empty($_SESSION['uid']) ? auth_user() : auth_find($email);
  if($buyerAcc && ($buyerAcc['type']??'')!=='buyer') $buyerAcc = null;
  $itemsSummary = implode(' · ', array_map(fn($l)=>$l['qty'].'× '.$l['brand'].' '.$l['name'].(!empty($l['colors'])?' ('.implode(', ',$l['colors']).')':''), $lines));
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
          "Hello ".($acc['name']?:($acc['company']?:'there')).",\n\nA buyer placed an order for your product on VESTRA:\n\nOrder ref: {$ref}\nBuyer company: {$company}\n\n".implode("\n",array_map(fn($x)=>"  {$x['qty']}x {$x['sku']} {$x['brand']} {$x['name']} @ €{$x['unit']}".(!empty($x['colors'])?" [".implode(", ",$x['colors'])."]":""),$lines))."\n\nSubtotal: €{$subtotal}".($seller_fee>0?"   Your payout (after commission): €{$payout}":"   Your payout: €{$payout} (the ".round(VESTRA_COMMISSION_RATE*100,1)."% platform commission is charged separately to your commission card once you mark this order paid)")."\n\nThe buyer pays your invoice by bank transfer — please confirm availability and watch for the payment, then ship and mark the order as shipped.\n\nView in your seller dashboard:\nhttps://vestrasales.com/seller?tab=orders\n\n— VESTRA · vestrasales.com");
        break;
      }
      break;
    }
  }
}
/* Auto-generate one PDF invoice per seller involved in this order (idempotent — safe to
   call again later from the buyer/seller panels; already-issued invoices are never rewritten). */
require_once __DIR__.'/inc/invoice.php';
$orderMeta = [
  'ref'=>$ref, 'date'=>date('c'),
  'buyer'=>['company'=>$company,'vat'=>trim($_POST['vat']??''),'name'=>$name,'email'=>$email,
            'country'=>trim($_POST['country']??''),'address'=>trim($_POST['address']??'')],
];
$bySeller=[];
foreach($lines as $l){ $bySeller[$l['seller_uid']?:'vestra'][] = $l; }
foreach($bySeller as $sid=>$sellerItems){
  $sellerAcc=null;
  if($sid!=='vestra'){ foreach(auth_accounts() as $a){ if(($a['id']??'')===$sid){ $sellerAcc=$a; break; } } }
  vestra_ensure_invoice($orderMeta, $sellerItems, $sellerAcc);
}

/* Let this browser session open the confirmation page + invoices (guest checkout has no
   account to authorize against). Keep only the last few refs so the session stays small. */
$_SESSION['order_refs'][$ref] = time();
if (count($_SESSION['order_refs']) > 10) {
  asort($_SESSION['order_refs']);
  $_SESSION['order_refs'] = array_slice($_SESSION['order_refs'], -10, null, true);
}

header('Location: /order-confirm?ref='.urlencode($ref)); exit;
