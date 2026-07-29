<?php
/** VESTRA — order request handler (demo). Stores to data/orders.csv (+ optional email). */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/escrow.php';
require_once __DIR__.'/inc/stripe.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$CONTACT='support@vestrasales.com'; $NOTIFY=false;
/* Buyer's chosen payment method: 'escrow' (card, held) or 'bank' (invoice/transfer). */
$payMethod = (($_POST['pay'] ?? 'bank') === 'escrow') ? 'escrow' : 'bank';

if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: /cart'); exit; }
if(!empty($_POST['website'])){ header('Location: /cart?placed=1&ref=NA'); exit; } // honeypot

/* One-shot order token (idempotency). A double-tap posts the same token twice; the
   PHP session lock serialises the two requests: the first consumes the token and
   records its ref, the second replays the SAME confirmation — never a second order,
   invoice or email. Posts without a token (stale cached cart) still go through. */
$orderTok = preg_replace('/[^a-f0-9]/','', (string)($_POST['order_token'] ?? ''));
if($orderTok !== ''){
  $doneRef = $_SESSION['order_token_done'][$orderTok] ?? '';
  if($doneRef !== ''){ header('Location: /order-confirm?ref='.urlencode($doneRef)); exit; }
  if(empty($_SESSION['order_tokens'][$orderTok])){ header('Location: /cart'); exit; } // unknown/expired
  unset($_SESSION['order_tokens'][$orderTok]); // consume — single use
}

$company=trim($_POST['company']??''); $name=trim($_POST['name']??''); $email=trim($_POST['email']??'');
if($company===''||$name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){ header('Location: /cart'); exit; }
if(empty($_POST['consent'])){ header('Location: /cart'); exit; } // Terms acceptance is mandatory
$shipAddr=trim($_POST['ship_address']??''); // optional — empty means "deliver to the billing address"

/* Remember checkout details on the buyer's account so the next order is prefilled:
   the delivery address is always kept current; other fields only fill gaps (never
   overwrite what the user saved in their profile). */
if(!empty($_SESSION['uid'])){
  $me=auth_user();
  if($me){
    $patch=[];
    if($shipAddr!==($me['ship_address']??'')) $patch['ship_address']=$shipAddr;
    foreach(['company'=>'company','vat'=>'vat_id','name'=>'name','address'=>'address','country'=>'country','phone'=>'phone'] as $post=>$field){
      $v=trim($_POST[$post]??'');
      if($v!=='' && trim($me[$field]??'')===''){ $patch[$field]=$v; }
    }
    if($patch) auth_update($me['id'],$patch);
  }
}

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
/* Platform commission. Escrow (Treuhand) orders carry a FIXED buyer-protection fee
   (VESTRA_ESCROW_FEE_BUYER, 3.8%) plus the seller's tiered membership commission
   (3.5/3.2/2.8%), collected together as the Stripe application fee on the direct
   charge. Bank-transfer orders keep the 0% cart fees (seller commission is charged
   separately to the seller card). Escrow needs a single, known seller. */
$sellerUids = array_values(array_unique(array_filter(array_map(fn($l)=>$l['seller_uid']??'', $lines))));
$escrowSeller = null;
if(count($sellerUids)===1){ foreach(auth_accounts() as $a){ if(($a['id']??'')===$sellerUids[0]){ $escrowSeller=$a; break; } } }
if($payMethod==='escrow' && $escrowSeller){
  $FEE_BUYER  = VESTRA_ESCROW_FEE_BUYER;                                              // fixed 3.8% buyer
  $FEE_SELLER = vestra_seller_commission_rate($escrowSeller['membership_tier'] ?? ''); // 3.5/3.2/2.8%
} else {
  $FEE_SELLER = VESTRA_FEE_SELLER;
  $FEE_BUYER  = VESTRA_FEE_BUYER;
}
$buyer_fee  = round($subtotal*$FEE_BUYER, 2);
$seller_fee = round($subtotal*$FEE_SELLER, 2);
$commission = round($buyer_fee + $seller_fee, 2); // total platform revenue
$total      = round($subtotal + $buyer_fee, 2);   // what the buyer pays
$payout     = round($subtotal - $seller_fee, 2);  // what the seller receives
/* Ref must be unique per ORDER, not per buyer+items — the same buyer reordering the
   same goods must get a fresh ref (commission idempotency and status tracking key on it). */
$ref='VES-'.strtoupper(substr(md5($email.implode('',array_column($lines,'sku')).microtime(false).bin2hex(random_bytes(4))),0,8));

$dir=__DIR__.'/data'; if(!is_dir($dir)) @mkdir($dir,0775,true);
$file=$dir.'/orders.csv'; $new=!file_exists($file);
if($fh=@fopen($file,'a')){
  if($new) fputcsv($fh,['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version'],',','"','\\');
  $items=implode(' | ', array_map(function($l){return $l['qty'].'x '.$l['sku'].' @'.$l['unit'];}, $lines));
  $colorNotes=implode(' | ', array_map(fn($l)=>$l['sku'].': '.implode(', ',$l['colors']),
    array_filter($lines, fn($l)=>!empty($l['colors']))));
  $methodLabel=$payMethod==='escrow'?'Payment: Secure escrow (card). ':'Payment: Bank transfer. ';
  $shipNote=$shipAddr!==''?'Deliver to: '.$shipAddr.'. ':'';
  $notes=trim($methodLabel.$shipNote.($colorNotes!==''?'Colours — '.$colorNotes.'. ':'').trim($_POST['notes']??''));
  fputcsv($fh,[date('c'),$ref,$company,trim($_POST['vat']??''),$name,$email,trim($_POST['country']??''),
    trim($_POST['phone']??''),$items,$subtotal,$commission,$payout,$total,$notes,'yes',VESTRA_TERMS_VERSION],',','"','\\');
  fclose($fh);
}

/* ── Escrow (direct charge + delayed payout) ─────────────────────────────────
   Buyer pays by card ON the seller's connected Stripe account (a direct charge);
   the platform commission is skimmed as an application fee; the seller's share is
   HELD in their Stripe balance (manual payout) until delivery is confirmed.
   Only offered for a SINGLE-seller cart whose seller finished Connect onboarding
   — otherwise bounce back to the cart to pick bank transfer. */
if($payMethod==='escrow'){
  $seller=$escrowSeller; // single seller resolved during fee computation above
  $ready=$seller && stripe_available() && !empty($seller['stripe_account_id']) && escrow_seller_ready($seller);
  if(!$ready){ header('Location: /cart?err=escrow'); exit; }

  $amountCents=(int)round($total*100);
  $feeCents=(int)round($commission*100);
  /* Itemise for the Stripe page; the protection-fee line absorbs rounding so the sum == amount. */
  $li=[]; $acc=0;
  foreach($lines as $l){ $c=(int)round($l['line']*100); if($c<=0) continue; $li[]=['name'=>$l['qty'].'× '.$l['brand'].' '.$l['name'],'amount'=>$c,'qty'=>1]; $acc+=$c; }
  $protCents=$amountCents-$acc;
  if($protCents>0) $li[]=['name'=>'Buyer protection fee','amount'=>$protCents,'qty'=>1];

  try {
    $session=stripe_escrow_checkout($seller['stripe_account_id'],$li,$feeCents,$ref,$email,'eur');
  } catch(\Throwable $e){
    error_log('[VESTRA Escrow] checkout create failed: '.$e->getMessage());
    header('Location: /cart?err=escrow'); exit;
  }
  escrow_save([
    'ref'=>$ref,'seller_uid'=>$seller['id'],'acct_id'=>$seller['stripe_account_id'],
    'session_id'=>$session->id,'payment_intent'=>'','amount'=>$amountCents,'fee'=>$feeCents,
    'currency'=>'eur','status'=>'pending','created'=>date('c'),
    'buyer'=>['company'=>$company,'name'=>$name,'email'=>$email,'vat'=>trim($_POST['vat']??''),'country'=>trim($_POST['country']??''),'address'=>trim($_POST['address']??''),'ship_address'=>$shipAddr],
    'buyer_id'=>(!empty($_SESSION['uid'])?$_SESSION['uid']:''),
    'items'=>array_map(fn($l)=>['sku'=>$l['sku'],'brand'=>$l['brand'],'name'=>$l['name'],'qty'=>$l['qty'],'unit'=>$l['unit'],'line'=>$l['line'],'colors'=>$l['colors']],$lines),
    'subtotal'=>$subtotal,'buyer_fee'=>$buyer_fee,'seller_fee'=>$seller_fee,'commission'=>$commission,'total'=>$total,'payout'=>$payout,
  ]);
  $_SESSION['order_refs'][$ref]=time();
  if($orderTok !== ''){ $_SESSION['order_token_done'][$orderTok] = $ref; }
  header('Location: '.$session->url); exit;
}

$body="New VESTRA order request {$ref}\n\nCompany: {$company}\nContact: {$name} <{$email}>\nCountry: ".trim($_POST['country']??'')."   Phone: ".trim($_POST['phone']??'')."\n".($shipAddr!==''?"Deliver to: {$shipAddr}\n":'')."\n";
foreach($lines as $l){ $body.="  {$l['qty']}x {$l['sku']} {$l['brand']} {$l['name']} @ €{$l['unit']} = €{$l['line']}".(!empty($l['colors'])?" [".implode(", ",$l['colors'])."]":"")."\n"; }
$body.="\nSubtotal €{$subtotal}\nBuyer pays €{$total}\n".($commission>0?"VESTRA commission €{$commission} (seller €{$seller_fee} + buyer €{$buyer_fee}) · Seller payout €{$payout}\n":"No platform fees (membership model) · Seller receives €{$payout}\n")."Notes: ".trim($_POST['notes']??'')."\n";
vestra_notify("New order {$ref} — {$company}", $body, $email);

$FEE_BUYER_PCT=round($FEE_BUYER*100);
$feeNote=$FEE_BUYER_PCT>0?" (includes {$FEE_BUYER_PCT}% buyer-protection fee)":"";
/* Confirmation to buyer — always on */
vestra_send_mail($email, "VESTRA — order {$ref} received",
  "Hello {$name},\n\nThank you — your VESTRA order request ({$ref}) has been received.\n\nWe are confirming stock now. Once confirmed, your PDF invoice (with the seller's bank details) will be emailed to you and added to your account — usually within the day. Payment is then by bank transfer against that invoice; goods ship after the transfer arrives. (Other payment methods are temporarily suspended.)\n\nBuyer pays: €{$total}{$feeNote}\n".($shipAddr!==''?"Delivery address: {$shipAddr}\n":'')."\n--- Order summary ---\n".implode("\n",array_map(fn($l)=>"  {$l['qty']}x {$l['sku']} {$l['brand']} {$l['name']} @ €{$l['unit']} = €{$l['line']}".(!empty($l['colors'])?" [".implode(", ",$l['colors'])."]":""),$lines))."\n\nTrack your order: https://vestrasales.com/buyer?tab=orders\n\n— VESTRA · vestrasales.com");

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
      require_once __DIR__.'/inc/push.php';
      vestra_push_send($sid, 'VESTRA — new order 📦',
        'Order '.$ref.' · '.$company.' · €'.number_format((float)$total,2), '/seller?tab=orders');
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
          "Hello ".($acc['name']?:($acc['company']?:'there')).",\n\nA buyer placed an order for your product on VESTRA:\n\nOrder ref: {$ref}\nBuyer company: {$company}\n".($shipAddr!==''?"Deliver to: {$shipAddr}\n":'')."\n".implode("\n",array_map(fn($x)=>"  {$x['qty']}x {$x['sku']} {$x['brand']} {$x['name']} @ €{$x['unit']}".(!empty($x['colors'])?" [".implode(", ",$x['colors'])."]":""),$lines))."\n\nSubtotal: €{$subtotal}".($seller_fee>0?"   Your payout (after commission): €{$payout}":"   Your payout: €{$payout} (the ".round(VESTRA_COMMISSION_RATE*100,1)."% platform commission is charged separately to your commission card once you mark this order paid)")."\n\nThe buyer pays your invoice by bank transfer — please confirm availability and watch for the payment, then ship and mark the order as shipped.\n\nView in your seller dashboard:\nhttps://vestrasales.com/seller?tab=orders\n\n— VESTRA · vestrasales.com");
        break;
      }
      break;
    }
  }
}
/* Invoicing is SUSPENDED: no PDF is created at checkout. The operator confirms stock
   and then issues it from the admin "Invoice approvals" tab (vestra_ensure_invoice is
   a no-op here unless VESTRA_AUTO_INVOICE is switched back on). Kept so re-enabling is
   a one-line flip. */
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
if($orderTok !== ''){
  $_SESSION['order_token_done'][$orderTok] = $ref;
  if (count($_SESSION['order_token_done']) > 20) $_SESSION['order_token_done'] = array_slice($_SESSION['order_token_done'], -20, null, true);
}

header('Location: /order-confirm?ref='.urlencode($ref)); exit;
