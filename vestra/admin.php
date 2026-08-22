<?php
/** VESTRA — Admin Panel */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/promos.php';
require_once __DIR__.'/inc/vouchers.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/invoice.php';
require_once __DIR__.'/inc/orders.php';
require_once __DIR__.'/inc/leads.php';
require_once __DIR__.'/inc/notify.php';
require_once __DIR__.'/inc/stripe.php';
require_once __DIR__.'/inc/commission.php';
require_once __DIR__.'/inc/escrow.php';
require_once __DIR__.'/inc/samples.php';
require_once __DIR__.'/inc/journal.php';
if(session_status()===PHP_SESSION_NONE) session_start();

$PASS   = (string)vestra_cfg('admin_pass','');
$locked = ($PASS==='');

if(isset($_GET['logout'])){ unset($_SESSION['vadmin'],$_SESSION['vadmin_csrf']); header('Location: /admin'); exit; }
$err=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pass'])){
  $tkey='admin|'.($_SERVER['REMOTE_ADDR']??'');
  if(auth_throttled($tkey)){ $err=true; }
  elseif(!$locked && hash_equals($PASS,(string)$_POST['pass'])){ auth_throttle_clear($tkey); $_SESSION['vadmin']=true; header('Location: /admin'); exit; }
  else { auth_throttle_hit($tkey); sleep(1); $err=true; }
}
$authed=!empty($_SESSION['vadmin']);
if($authed && empty($_SESSION['vadmin_csrf'])) $_SESSION['vadmin_csrf']=bin2hex(random_bytes(16));

/* Hidden CSRF field — include in EVERY admin POST form. */
function csrfField(): string {
  return '<input type="hidden" name="_csrf" value="'.htmlspecialchars($_SESSION['vadmin_csrf']??'').'">';
}

// ── POST actions ───────────────────────────────────────────────────────────────
if($authed && $_SERVER['REQUEST_METHOD']==='POST'){
  // Every mutating action must carry the session CSRF token — blocks cross-site form posts.
  if(!hash_equals($_SESSION['vadmin_csrf']??'', (string)($_POST['_csrf']??''))){
    header('Location: /admin?msg=csrf_fail'); exit;
  }
  $act=$_POST['_action']??'';

  if($act==='approve_listing'){
    $lid=$_POST['lid']??''; $note=trim($_POST['note']??''); $sellerUid=''; $pname='';
    $all=vestra_listings();
    foreach($all as &$p){ if(($p['id']??'')===$lid){ $p['status']='approved'; if($note) $p['admin_note']=$note; $sellerUid=(string)($p['seller_uid']??''); $pname=trim(($p['brand']??'').' '.($p['name']??'')); break; } }
    vestra_save_listings($all);
    if($sellerUid){
      require_once __DIR__.'/inc/push.php';
      vestra_push_send($sellerUid,'VESTRA — listing approved 🎉', ($pname?:'Your listing').' is now live in the catalog.','/seller?tab=listings');
      foreach(auth_accounts() as $sa){
        if(($sa['id']??'')!==$sellerUid || empty($sa['email'])) continue;
        [$lSubj,$lBody,$lOpts]=vestra_tpl_listing_approved(vestra_user_lang($sa),$sa['name']?:($sa['company']?:'there'),$pname?:'Your listing');
        vestra_send_mail($sa['email'],$lSubj,$lBody,'','',null,'',$lOpts);
        break;
      }
    }
    header('Location: /admin?tab=approvals&msg=approved'); exit;
  }
  if($act==='reject_listing'){
    $lid=$_POST['lid']??''; $note=trim($_POST['note']??''); $sellerUid=''; $pname='';
    $all=vestra_listings();
    foreach($all as &$p){ if(($p['id']??'')===$lid){ $p['status']='rejected'; if($note) $p['admin_note']=$note; $sellerUid=(string)($p['seller_uid']??''); $pname=trim(($p['brand']??'').' '.($p['name']??'')); break; } }
    vestra_save_listings($all);
    if($sellerUid){
      require_once __DIR__.'/inc/push.php';
      vestra_push_send($sellerUid,'VESTRA — listing needs changes', ($pname?:'Your listing').($note?' — '.mb_substr($note,0,80):' was not approved. See your dashboard for details.'),'/seller?tab=listings');
      foreach(auth_accounts() as $sa){
        if(($sa['id']??'')!==$sellerUid || empty($sa['email'])) continue;
        [$lSubj,$lBody,$lOpts]=vestra_tpl_listing_rejected(vestra_user_lang($sa),$sa['name']?:($sa['company']?:'there'),$pname?:'Your listing',$note);
        vestra_send_mail($sa['email'],$lSubj,$lBody,'','',null,'',$lOpts);
        break;
      }
    }
    header('Location: /admin?tab=approvals&msg=rejected'); exit;
  }
  /* Issue (approve) the invoice(s) for an order once stock is confirmed. Auto-invoicing
     is suspended, so the PDF is created HERE on operator approval, then emailed to the
     buyer and added to their account (it appears under My orders / the confirmation page). */
  if($act==='issue_invoice'){
    $ref=preg_replace('/[^A-Za-z0-9_-]/','',$_POST['ref']??'');
    require_once __DIR__.'/inc/invoice.php';
    $issued=vestra_issue_order_invoices($ref);
    if($issued){
      $orow=null; foreach(vestra_read_csv('orders.csv') as $r){ if(($r['ref']??'')===$ref){ $orow=$r; break; } }
      if($orow && filter_var($orow['email']??'',FILTER_VALIDATE_EMAIL)){
        require_once __DIR__.'/inc/notify.php';
        $nos=implode(', ',array_map(fn($i)=>$i['no'],$issued));
        vestra_send_mail($orow['email'], "VESTRA — invoice for order {$ref}",
          "Hello ".($orow['name']?:'there').",\n\nGood news — stock for your order {$ref} is confirmed and your invoice ({$nos}) is now ready.\n\nDownload it from your order confirmation page or under My orders, and pay by bank transfer to the account shown on the invoice. Your goods ship as soon as the payment arrives.\n\nView: https://vestrasales.com/order-confirm?ref=".rawurlencode($ref)."\n\n— VESTRA · vestrasales.com");
      }
    }
    $back=(($_POST['from']??'')==='view')?'orders&view='.urlencode($ref):'invoices';
    header('Location: /admin?tab='.$back.'&msg='.($issued?'invoice_issued':'invoice_none')); exit;
  }
  /* Admin full listing editor — edit any field, set status, and reassign the
     listing to a different seller. */
  if($act==='admin_save_listing'){
    $lid=$_POST['lid']??''; $one=fn($s)=>trim(preg_replace('/\s+/',' ',str_replace(["\r","\n"],' ',(string)$s)));
    $all=vestra_listings();
    foreach($all as &$p){
      if(($p['id']??'')!==$lid) continue;
      $p['brand']=$one($_POST['brand']??($p['brand']??''));
      $p['name'] =$one($_POST['name'] ??($p['name'] ??''));
      $p['cat']  =$one($_POST['cat']  ??($p['cat']  ??''));
      $p['sku']  =$one($_POST['sku']  ??($p['sku']  ??''));
      $p['moq']  =max(1,(int)($_POST['moq']??($p['moq']??1)));
      $mode=in_array($_POST['mode']??'',['fixed','sale','offer'],true)?$_POST['mode']:($p['mode']??'fixed');
      $p['mode']=$mode;
      if($mode==='sale') $p['list']=round((float)($_POST['list']??($p['list']??0)),2);
      $tiers=[];
      foreach([['t1min','t1price'],['t2min','t2price'],['t3min','t3price']] as $pair){
        $mn=(int)($_POST[$pair[0]]??0); $pr=(float)($_POST[$pair[1]]??0);
        if($mn>0&&$pr>0) $tiers[]=['min'=>$mn,'price'=>round($pr,2)];
      }
      usort($tiers,fn($a,$b)=>$a['min']<=>$b['min']);
      if($tiers) $p['tiers']=$tiers;
      $colors=array_values(array_intersect((array)($_POST['colors']??[]),array_keys(vestra_colors())));
      if($colors) $p['colors']=$colors; else unset($p['colors']);
      $step=max(0,(int)($_POST['size_step']??0)); if($step>1) $p['size_step']=$step; else unset($p['size_step']);
      $minC=max(0,(int)($_POST['min_colors']??0)); if($minC>0&&$colors&&$minC<=count($colors)) $p['min_colors']=$minC; else unset($p['min_colors']);
      if(in_array($_POST['status']??'',['approved','pending','rejected','suspended'],true)) $p['status']=$_POST['status'];
      if(isset($_POST['desc'])) $p['desc']=$one($_POST['desc']);
      $ns=$_POST['seller_uid']??'';
      if($ns!==''){ $p['seller_uid']=$ns; foreach(auth_accounts() as $a){ if(($a['id']??'')===$ns){ $p['seller']=($a['company']?:($a['name']?:($p['seller']??''))); break; } } }
      break;
    }
    unset($p);
    vestra_save_listings($all);
    header('Location: /admin?tab=listings&msg=listing_saved'); exit;
  }
  /* Bulk: set MOQ to 20 on every listing whose brand is NOT Lacoste / Ralph
     Lauren / Amiri (matched loosely so "R. Lauren", "Ralph Lauren Polo", … are
     also kept as-is). Only touches seller listings in data/listings.json. */
  if($act==='bulk_moq_20'){
    $all=vestra_listings(); $n=0;
    foreach($all as &$p){
      $b=(string)($p['brand']??'');
      if(preg_match('/lacoste|ralph|lauren|amiri/i',$b)) continue;   // excluded brands stay untouched
      if((int)($p['moq']??0)!==20){ $p['moq']=20; $n++; }
    }
    unset($p);
    if($n) vestra_save_listings($all);
    header('Location: /admin?tab=listings&msg=bulk_moq&n='.$n); exit;
  }
  /* Bulk: rebrand every "SB E-Commerce…" listing's seller to "Tyrex International
     BV" and hide the name on the public catalogue (shows "Verified business ·
     via VESTRA"). Matches the stored seller name, or the seller_uid's account
     company when the listing has no seller name of its own. */
  if($act==='rebrand_sb_tyrex'){
    $accCo=[]; foreach(auth_accounts() as $a) $accCo[(string)($a['id']??'')]=(string)($a['company']?:($a['name']??''));
    $all=vestra_listings(); $n=0;
    foreach($all as &$p){
      $s=(string)($p['seller']??''); if($s===''&&!empty($p['seller_uid'])) $s=$accCo[(string)$p['seller_uid']]??'';
      if(preg_match('/sb\W*e\W*commerce/i',$s)){
        $p['seller']='Tyrex International BV';
        $p['hide_seller']=true;
        $n++;
      }
    }
    unset($p);
    if($n) vestra_save_listings($all);
    header('Location: /admin?tab=listings&msg=rebrand&n='.$n); exit;
  }
  /* Price editor — retune MOQ / mode / list price / tiered pricing for EVERY product
     in one submit. Demo (built-in) products are saved to data/product_overrides.json;
     live seller listings are edited directly in listings.json. Fields are keyed by
     product id: moq[id], mode[id], list[id], t1min[id]…t3price[id]. Empty tier pairs
     are ignored, so clearing them never wipes existing pricing by accident. */
  if($act==='save_prices'){
    $moqIn=(array)($_POST['moq']??[]); $modeIn=(array)($_POST['mode']??[]); $listIn=(array)($_POST['list']??[]);
    $tminIn=[(array)($_POST['t1min']??[]),(array)($_POST['t2min']??[]),(array)($_POST['t3min']??[])];
    $tprIn =[(array)($_POST['t1price']??[]),(array)($_POST['t2price']??[]),(array)($_POST['t3price']??[])];
    $ids=array_values(array_unique(array_merge(array_keys($moqIn),array_keys($modeIn),array_keys($listIn))));
    $all=vestra_listings(); $ov=vestra_product_overrides(); $n=0;
    foreach($ids as $id){
      $tiers=[];
      for($i=0;$i<3;$i++){
        $mn=(string)($tminIn[$i][$id]??''); $pr=(string)($tprIn[$i][$id]??'');
        if($mn!=='' && $pr!=='' && (float)$pr>0) $tiers[]=['min'=>max(1,(int)$mn),'price'=>round((float)$pr,2)];
      }
      usort($tiers,fn($a,$b)=>$a['min']<=>$b['min']);
      $m  = isset($moqIn[$id]) && $moqIn[$id]!=='' ? max(1,(int)$moqIn[$id]) : null;
      $md = in_array($modeIn[$id]??'',['fixed','sale','offer'],true) ? $modeIn[$id] : null;
      $ls = isset($listIn[$id]) && $listIn[$id]!=='' ? round((float)$listIn[$id],2) : null;
      if(vestra_is_demo_product($id)){
        $e=(array)($ov[$id]??[]);
        if($m!==null)  $e['moq']=$m;
        if($md!==null) $e['mode']=$md;
        if($ls!==null) $e['list']=$ls;
        if($tiers)     $e['tiers']=$tiers;
        if($e){ $ov[$id]=$e; $n++; }
      } else {
        foreach($all as &$p){
          if(($p['id']??'')!==$id) continue;
          if($m!==null)  $p['moq']=$m;
          if($md!==null) $p['mode']=$md;
          if($ls!==null) $p['list']=$ls;
          if($tiers)     $p['tiers']=$tiers;
          $n++; break;
        }
        unset($p);
      }
    }
    vestra_save_product_overrides($ov);
    vestra_save_listings($all);
    header('Location: /admin?tab=prices&msg=prices_saved&n='.$n); exit;
  }
  /* One-click catalogue pricing rules (seller listings only — the demo products
     Lacoste / Ralph Lauren / Amiri are set in code). Rules:
       • Remove "make an offer": every offer listing becomes a fixed price.
       • Amiri polos → €40, MOQ 50.  • All other polos → €70 (MOQ 20).
       • T-shirts (not Lacoste/Ralph/Amiri) → €49.90 on sale (-29%), flat even at 20.
       • MOQ 20 on everything else.
       • Lacoste & Ralph Lauren: price AND MOQ left completely untouched. */
  if($act==='apply_pricing_rules'){
    $all=vestra_listings(); $n=0;
    foreach($all as &$p){
      $b=strtolower((string)($p['brand']??'')); $c=strtolower((string)($p['cat']??''));
      if(str_contains($b,'lacoste')||str_contains($b,'ralph')||str_contains($b,'lauren')) continue; // untouched
      $isDG   = str_contains($b,'dolce')||str_contains($b,'gabbana')||$b==='dg'||$b==='d&g'||(bool)preg_match('/\bd\s*&\s*g\b/',$b);
      $isDsq  = str_contains($b,'dsquared')||str_contains($b,'dsq');
      $isAmiri= str_contains($b,'amiri');
      $isPolo = str_contains($c,'polo');
      $isTee  = (bool)preg_match('/t[-\s]?shirt|tee/',$c);
      $sig=json_encode([$p['mode']??'',$p['moq']??0,$p['offers']??false,$p['tiers']??[]]);
      if(($p['mode']??'')==='offer') $p['mode']='fixed';   // remove make-an-offer
      unset($p['offers']);                                  // drop "also accepts offers"
      if($isAmiri && $isPolo){ $p['moq']=50; $p['tiers']=[['min'=>50,'price'=>40.00]]; }
      elseif($isPolo){ $p['moq']=20; $p['tiers']=[['min'=>20,'price'=>70.00]]; }
      elseif($isTee && !$isAmiri){ $p['moq']=20; $p['mode']='sale'; $p['list']=69.90; $p['tiers']=[['min'=>20,'price'=>49.90]]; }
      else {                                                // others: MOQ 20, keep existing (now fixed) price
        $p['moq']=20;
        if(!empty($p['tiers']) && is_array($p['tiers'])){
          usort($p['tiers'],fn($a,$bb)=>($a['min']??0)<=>($bb['min']??0));
          if(($p['tiers'][0]['min']??0) > 20) $p['tiers'][0]['min']=20; // lowest tier starts at the new MOQ
        }
      }
      if(json_encode([$p['mode']??'',$p['moq']??0,$p['offers']??false,$p['tiers']??[]])!==$sig) $n++;
    }
    unset($p);
    vestra_save_listings($all);
    header('Location: /admin?tab=prices&msg=pricing_rules&n='.$n); exit;
  }
  /* Create (or reuse) the verified Elite "Tyrex International BV" seller account and
     migrate every SB E-Commerce Services LLC listing (and any already-rebranded
     "Tyrex" listing) onto it. Company details come from the supplier invoice (VAT /
     address). The admin supplies the login email at click-time; a one-time password
     is flashed back so it can be relayed out-of-band. */
  if($act==='create_tyrex_migrate'){
    $email=strtolower(trim((string)($_POST['tyrex_email']??'')));
    $hide=!empty($_POST['hide_name']);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){ header('Location: /admin?tab=listings&msg=tyrex_bademail'); exit; }
    $accs=auth_accounts();
    $tyrex=null;
    foreach($accs as $a){ if(($a['type']??'')==='seller' && strtolower(trim((string)($a['company']??'')))==='tyrex international bv'){ $tyrex=$a; break; } }
    foreach($accs as $a){ if(strtolower((string)($a['email']??''))===$email && ($a['id']??'')!==($tyrex['id']??'')){ header('Location: /admin?tab=listings&msg=tyrex_emailtaken'); exit; } }
    $pwPlain=null;
    if(!$tyrex){
      $pwPlain=bin2hex(random_bytes(5)).'-'.random_int(10,99);
      $tyrex=[
        'id'=>bin2hex(random_bytes(8)),'email'=>$email,'hash'=>password_hash($pwPlain,PASSWORD_DEFAULT),'type'=>'seller',
        'status'=>'active','email_verified'=>true,
        'name'=>'Tyrex International BV','company'=>'Tyrex International BV',
        'vat_id'=>'NL853943576B01','reg_number'=>'','country'=>'Netherlands',
        'address'=>'Kingsfordweg 151, 1043 GR Amsterdam, Netherlands','phone'=>'','website'=>'',
        'kyb_status'=>'approved','membership_tier'=>'premium','membership_status'=>'active',
        'onboarding_paid'=>true,'created'=>date('c'),'doc_requests'=>[],
      ];
      $accs[]=$tyrex; auth_save_accounts($accs);
    } else {
      auth_update($tyrex['id'],['email'=>$email,'status'=>'active','kyb_status'=>'approved',
        'membership_tier'=>'premium','membership_status'=>'active','onboarding_paid'=>true,
        'company'=>'Tyrex International BV','vat_id'=>($tyrex['vat_id']??'')?:'NL853943576B01']);
    }
    $tuid=$tyrex['id'];
    $accCo=[]; foreach($accs as $a) $accCo[(string)($a['id']??'')]=strtolower((string)($a['company']?:($a['name']??'')));
    $all=vestra_listings(); $n=0;
    foreach($all as &$p){
      $s=strtolower((string)($p['seller']??'')); if($s===''&&!empty($p['seller_uid'])) $s=$accCo[(string)$p['seller_uid']]??'';
      if(preg_match('/sb\W*e\W*commerce/i',$s) || str_contains($s,'tyrex')){
        $p['seller_uid']=$tuid; $p['seller']='Tyrex International BV'; $p['hide_seller']=$hide; $p['verified']=true; $n++;
      }
    }
    unset($p);
    vestra_save_listings($all);
    if($pwPlain) $_SESSION['tyrex_flash']=['email'=>$email,'pw'=>$pwPlain];
    header('Location: /admin?tab=listings&msg=tyrex_ok&n='.$n); exit;
  }
  /* Les Garage Paris catalogue sync — this seller's products are maintained in
     inc/lesgarage_polos_seed.json (add/edit a product there, click this, done).
     Adds anything new and refreshes anything already listed (price, MOQ, tiers,
     pack size, colours, images, specs) to match the seed — an ongoing tool for
     this one seller, not a one-off import. */
  if($act==='sync_lesgarage'){
    $seed=is_readable(__DIR__.'/inc/lesgarage_polos_seed.json') ? json_decode((string)file_get_contents(__DIR__.'/inc/lesgarage_polos_seed.json'),true) : [];
    if(!is_array($seed)) $seed=[];
    $accs=auth_accounts(); $sid='';
    foreach($accs as $a){ if(($a['type']??'')==='seller' && strtolower(trim((string)($a['company']??'')))==='les garage paris'){ $sid=(string)($a['id']??''); break; } }
    if($sid===''){
      $sid=bin2hex(random_bytes(8));
      $accs[]=['id'=>$sid,'email'=>'','type'=>'seller','status'=>'active','email_verified'=>true,
        'name'=>'Les Garage Paris','company'=>'Les Garage Paris','vat_id'=>'','reg_number'=>'',
        'country'=>'France','address'=>'','phone'=>'','website'=>'','kyb_status'=>'approved',
        'membership_tier'=>'premium','membership_status'=>'active','onboarding_paid'=>true,'created'=>date('c'),'doc_requests'=>[]];
      auth_save_accounts($accs);
    }
    $all=vestra_listings();
    $byId=[]; $byBS=[];
    foreach($all as $i=>$l){ $lid=(string)($l['id']??''); if($lid!=='') $byId[$lid]=$i;
      $bs=strtolower(trim(($l['brand']??'').'|'.($l['sku']??''))); if($bs!=='|') $byBS[$bs]=$i; }
    $added=0; $updated=0;
    $refreshable=['moq','unit','mode','list','desc','origin','colors','images','linesheet','sheet_file','sizes','size_step','specs','tiers','cat'];
    foreach($seed as $p){
      $id=(string)($p['id']??''); $bs=strtolower(trim(($p['brand']??'').'|'.($p['sku']??'')));
      $matchIdx = ($id!=='' && isset($byId[$id])) ? $byId[$id] : (($bs!=='|' && isset($byBS[$bs])) ? $byBS[$bs] : null);
      if($matchIdx!==null){
        foreach($refreshable as $k) if(array_key_exists($k,$p)) $all[$matchIdx][$k]=$p[$k];
        $updated++;
        continue;
      }
      $p['seller_uid']=$sid; $p['seller']='Les Garage Paris'; $p['verified']=true; $p['status']='approved'; $p['added_at']=date('c');
      $all[]=$p; $newIdx=count($all)-1; $added++;
      if($id!=='') $byId[$id]=$newIdx;
      if($bs!=='|') $byBS[$bs]=$newIdx;
    }
    vestra_save_listings($all);
    header('Location: /admin?tab=listings&msg=lgp_sync&n='.$added.'&upd='.$updated); exit;
  }
  /* Tyrex International BV catalogue sync — same idea as the Les Garage Paris sync
     above, but the seller account is NOT auto-created here: Tyrex is set up
     deliberately via "Create Tyrex Elite & migrate" (needs a real login e-mail),
     so this only attaches products to an account that already exists. */
  if($act==='sync_tyrex'){
    $seed=is_readable(__DIR__.'/inc/tyrex_products_seed.json') ? json_decode((string)file_get_contents(__DIR__.'/inc/tyrex_products_seed.json'),true) : [];
    if(!is_array($seed)) $seed=[];
    $tuid=''; foreach(auth_accounts() as $a){ if(($a['type']??'')==='seller' && strtolower(trim((string)($a['company']??'')))==='tyrex international bv'){ $tuid=(string)($a['id']??''); break; } }
    if($tuid===''){ header('Location: /admin?tab=listings&msg=tyrex_missing'); exit; }
    $all=vestra_listings();
    $byId=[]; $byBS=[];
    foreach($all as $i=>$l){ $lid=(string)($l['id']??''); if($lid!=='') $byId[$lid]=$i;
      $bs=strtolower(trim(($l['brand']??'').'|'.($l['sku']??''))); if($bs!=='|') $byBS[$bs]=$i; }
    $added=0; $updated=0;
    $refreshable=['moq','unit','mode','list','desc','origin','colors','images','linesheet','sheet_file','sizes','size_step','specs','tiers','cat'];
    foreach($seed as $p){
      $id=(string)($p['id']??''); $bs=strtolower(trim(($p['brand']??'').'|'.($p['sku']??'')));
      $matchIdx = ($id!=='' && isset($byId[$id])) ? $byId[$id] : (($bs!=='|' && isset($byBS[$bs])) ? $byBS[$bs] : null);
      if($matchIdx!==null){
        foreach($refreshable as $k) if(array_key_exists($k,$p)) $all[$matchIdx][$k]=$p[$k];
        $updated++;
        continue;
      }
      $p['seller_uid']=$tuid; $p['seller']='Tyrex International BV'; $p['verified']=true; $p['status']='approved'; $p['added_at']=date('c');
      $all[]=$p; $newIdx=count($all)-1; $added++;
      if($id!=='') $byId[$id]=$newIdx;
      if($bs!=='|') $byBS[$bs]=$newIdx;
    }
    vestra_save_listings($all);
    header('Location: /admin?tab=listings&msg=tyx_sync&n='.$added.'&upd='.$updated); exit;
  }
  if($act==='approve_kyb'){
    $uid=$_POST['uid']??'';
    auth_update($uid,['kyb_status'=>'approved','status'=>'active']);
    $acc=null; foreach(auth_accounts() as $a){ if(($a['id']??'')===$uid){ $acc=$a; break; } }
    if($acc){
      $panel=(($acc['type']??'')==='seller')?'/seller':'/buyer';
      require_once __DIR__.'/inc/push.php';
      vestra_push_send($uid,'VESTRA — account verified ✓','Your business is verified. Full wholesale access is unlocked.',$panel);
      if(!empty($acc['email'])){
        [$kSubj,$kBody,$kOpts]=vestra_tpl_kyb_approved(vestra_user_lang($acc),$acc['name']?:($acc['company']?:'there'),$acc['type']??'buyer','https://vestrasales.com'.$panel);
        vestra_send_mail($acc['email'],$kSubj,$kBody,'','',null,'',$kOpts);
      }
    }
    header('Location: /admin?tab=users&msg=kyb_ok'); exit;
  }
  if($act==='suspend_account'){
    auth_update($_POST['uid']??'',['status'=>'suspended']);
    header('Location: /admin?tab=users&msg=suspended'); exit;
  }
  if($act==='activate_account'){
    auth_update($_POST['uid']??'',['status'=>'active']);
    header('Location: /admin?tab=users&msg=activated'); exit;
  }
  /* Permanently delete an account. Suspending only hides it, so there was no way to get rid
     of a test/spam signup. Irreversible, hence: a timestamped backup of accounts.json first,
     and the seller's listings go with them — leaving those behind would keep products on the
     catalogue pointing at a seller_uid that no longer resolves (buyers could still open and
     order them). Refuses while the seller still has a live order, so nothing in flight is
     orphaned; suspend covers that case instead. */
  if($act==='delete_account'){
    $uid=(string)($_POST['uid']??'');
    $victim=null;
    foreach(auth_accounts() as $a){ if(($a['id']??'')===$uid){ $victim=$a; break; } }
    if(!$victim){ header('Location: /admin?tab=users&msg=acct_notfound'); exit; }

    $openOrders=0;
    foreach(vestra_read_csv('orders.csv') as $o){
      if(in_array(strtolower((string)($o['status']??'')),['completed','cancelled','refunded'],true)) continue;
      foreach(vestra_order_lines($o)['lines'] as $l){
        if((string)($l['seller_uid']??'')===$uid){ $openOrders++; break; }
      }
    }
    if($openOrders>0){ header('Location: /admin?tab=users&msg=acct_has_orders'); exit; }

    $af=vestra_data_dir().'/accounts.json';
    if(is_readable($af)) @copy($af,$af.'.bak.'.date('Ymd_His'));

    $kept=array_values(array_filter(auth_accounts(), fn($a)=>($a['id']??'')!==$uid));
    auth_save_accounts($kept);

    $ls=vestra_listings(); $before=count($ls);
    $ls=array_values(array_filter($ls, fn($l)=>(string)($l['seller_uid']??'')!==$uid));
    if(count($ls)!==$before) vestra_save_listings($ls);

    header('Location: /admin?tab=users&msg=acct_deleted'); exit;
  }
  /* Admin-managed membership plan (comp / manual upgrade). Sets the tier + marks
     it active; '' clears it back to no plan. Drives commission rate + listing
     quota + Elite perks. Granting a paid tier to a seller also flips
     onboarding_paid so their full package (badge eligibility etc.) is unlocked. */
  if($act==='set_membership'){
    $uid=$_POST['uid']??''; $tier=(string)($_POST['tier']??'');
    if(in_array($tier,['','starter','pro','premium'],true)){
      $acc=null; foreach(auth_accounts() as $a){ if(($a['id']??'')===$uid){ $acc=$a; break; } }
      $upd=['membership_tier'=>$tier,'membership_status'=>($tier===''?'none':'active')];
      if($tier!=='' && ($acc['type']??'')==='seller') $upd['onboarding_paid']=true;
      auth_update($uid,$upd);
      if($acc && $tier!==''){
        $panel=(($acc['type']??'')==='seller')?'/seller':'/buyer';
        require_once __DIR__.'/inc/push.php';
        $label=$tier==='premium'?'Elite':ucfirst($tier);
        vestra_push_send($uid,'VESTRA — plan updated ⭐','Your VESTRA membership is now '.$label.'.',$panel);
        if(!empty($acc['email'])){
          // Plan names (Starter/Pro/Elite) stay in English in every locale, same as any
          // branded product-tier name — only the surrounding copy is translated.
          [$pSubj,$pBody,$pOpts]=vestra_tpl_membership_changed(vestra_user_lang($acc),$acc['name']?:($acc['company']?:'there'),$label,'https://vestrasales.com'.$panel);
          vestra_send_mail($acc['email'],$pSubj,$pBody,'','',null,'',$pOpts);
        }
      }
    }
    header('Location: /admin?tab=users&msg=member_set'); exit;
  }

  /* ── Journal (editorial) ── */
  if($act==='journal_save'){
    $jid=trim($_POST['jid']??''); $title=trim($_POST['title']??'');
    if($title!==''){
      $rec=[
        'title'=>$title,
        'category'=>in_array($_POST['category']??'',VESTRA_JOURNAL_CATS,true)?$_POST['category']:VESTRA_JOURNAL_CATS[0],
        'excerpt'=>trim($_POST['excerpt']??''),
        'body'=>trim($_POST['body']??''),
        'cover'=>trim($_POST['cover']??''),
        'author'=>trim($_POST['author']??'')?:'VESTRA Editorial',
        'published'=>!empty($_POST['published']),
      ];
      if($jid!=='') $rec['id']=$jid;
      $rec['slug']=vestra_journal_slug($title,$jid);
      vestra_journal_save($rec);
    }
    header('Location: /admin?tab=journal&msg=journal_saved'); exit;
  }
  if($act==='journal_delete'){ vestra_journal_delete($_POST['jid']??''); header('Location: /admin?tab=journal&msg=journal_deleted'); exit; }
  if($act==='journal_toggle'){ vestra_journal_toggle($_POST['jid']??''); header('Location: /admin?tab=journal&msg=journal_toggled'); exit; }
  if($act==='journal_seed'){ $n=vestra_journal_seed_starters(); header('Location: /admin?tab=journal&msg=journal_seeded&n='.$n); exit; }
  /* Editorial cover photography. Kept as an admin action rather than a scheduled job: it
     reaches out to a third party and writes files, so it happens when someone asks for it. */
  if($act==='journal_photos'){
    $dry = ($_POST['dry'] ?? '1') !== '0';
    $r = vestra_journal_fetch_photos([], 6, 1400, $dry);
    $_SESSION['journal_photo_report'] = $r;
    header('Location: /admin?tab=journal&msg='.($dry ? 'journal_photos_dry' : 'journal_photos_done')); exit;
  }
  if($act==='resend_verify'){
    $uid=$_POST['uid']??'';
    foreach(auth_accounts() as $a){
      if(($a['id']??'')!==$uid) continue;
      auth_resend_verify($a['email']??'');
      break;
    }
    header('Location: /admin?tab=users&msg=verify_resent'); exit;
  }
  if($act==='manual_verify'){
    auth_update($_POST['uid']??'',['email_verified'=>true,'email_token'=>'','status'=>'pending']);
    header('Location: /admin?tab=users&msg=manual_verified'); exit;
  }
  if($act==='reset_password'){
    // Admin-assisted reset for when email delivery is unavailable: generate a
    // strong temporary password, set it, and flash it once so the admin can
    // relay it to the account holder out-of-band. The plaintext is never stored.
    $uid=$_POST['uid']??'';
    $acc=null; foreach(auth_accounts() as $a){ if(($a['id']??'')===$uid){ $acc=$a; break; } }
    if($acc){
      $temp=bin2hex(random_bytes(5)).'-'.random_int(10,99); // 12-char, easy to read
      if(auth_set_password($uid,$temp)){
        $_SESSION['pw_reset_flash']=['email'=>$acc['email']??'','pw'=>$temp];
      }
    }
    header('Location: /admin?tab=users&msg=pw_reset'); exit;
  }
  if($act==='grant_badge'){
    auth_update($_POST['uid']??'',['verified_badge'=>true,'verification_status'=>'verified']);
    header('Location: /admin?tab=users&msg=badge_granted'); exit;
  }
  if($act==='revoke_badge'){
    auth_update($_POST['uid']??'',['verified_badge'=>false,'verification_status'=>'none']);
    header('Location: /admin?tab=users&msg=badge_revoked'); exit;
  }
  if($act==='request_doc'){
    auth_request_doc($_POST['uid']??'', $_POST['doc_type']??'', trim($_POST['note']??''));
    header('Location: /admin?tab=documents&uid='.urlencode($_POST['uid']??'').'&msg=doc_requested'); exit;
  }
  /* Teklife yanit — OPERATOR olarak. Bu daha once hicbir yerde yoktu: satici ucu
     sahiplik sarti ariyor (seller_uid === uid), kurasyonlu katalog urunlerinde ise
     seller_uid YOK, dolayisiyla katalog urunune gelen bir teklifi hic kimse kabul
     edemiyordu. Admin Teklifler sekmesi de salt-okunurdu. Gecerli bir teklif geliyor,
     bildirim e-postasi gidiyor ve orada kaliyordu.
     Yanit mantigi inc/offers.php'de, satici ucuyla AYNI kod. Buradaki yetki: operator. */
  if($act==='offer_respond'){
    require_once __DIR__.'/inc/offers.php';
    $oRef = trim($_POST['ref'] ?? '');
    $oAct = $_POST['response'] ?? '';
    $oCtr = round((float)($_POST['counter_price'] ?? 0), 2);
    $res  = vestra_offer_respond($oRef, $oAct, $oCtr, null, 'VESTRA');
    header('Location: /admin?tab=offers&msg='.($res['ok'] ? 'offer_'.$oAct : 'offer_err')); exit;
  }
  if($act==='review_doc'){
    $duid=$_POST['uid']??''; $dreq=$_POST['req_id']??''; $dstatus=$_POST['status']??''; $dnote=trim($_POST['admin_note']??'');
    $dacc=null; foreach(auth_accounts() as $a){ if(($a['id']??'')===$duid){ $dacc=$a; break; } }
    $dtype=''; if($dacc) foreach(($dacc['doc_requests']??[]) as $r){ if(($r['id']??'')===$dreq){ $dtype=$r['type']??''; break; } }
    auth_review_doc($duid, $dreq, $dstatus, $dnote);
    if($dacc && !empty($dacc['email']) && in_array($dstatus,['approved','rejected'],true)){
      $dlang=vestra_user_lang($dacc);
      [$dSubj,$dBody,$dOpts]=vestra_tpl_doc_reviewed($dlang,$dacc['name']?:($dacc['company']?:'there'),$dstatus,vestra_doc_type_label($dlang,$dtype),$dnote);
      vestra_send_mail($dacc['email'],$dSubj,$dBody,'','',null,'',$dOpts);
    }
    header('Location: /admin?tab=documents&uid='.urlencode($_POST['uid']??'').'&msg=doc_reviewed'); exit;
  }
  if($act==='delete_listing'){
    $lid=$_POST['lid']??'';
    if($lid) vestra_save_listings(array_values(array_filter(vestra_listings(),fn($p)=>($p['id']??'')!==$lid)));
    header('Location: /admin?tab=listings&msg=deleted'); exit;
  }
  if($act==='order_status'){
    $ref=$_POST['ref']??''; $st=$_POST['status']??'';
    if($ref && in_array($st,['pending','paid','shipped','completed'],true)){
      $all=vestra_read_json('order_statuses.json');
      $prev=$all[$ref]['status']??'pending';
      $all[$ref]=array_merge($all[$ref]??[],['status'=>$st,'tracking'=>trim($_POST['tracking']??''),'updated_at'=>date('c')]);
      $all[$ref]['history'][] = vestra_order_history_entry($st, 'admin');
      vestra_write_json('order_statuses.json',$all);
      /* Invoice flow: on "paid", tell the buyer + the sellers whose SKUs are in the order,
         and charge each seller's per-tier commission off-session (inc/commission.php) — never
         touches what the buyer paid, purely a separate seller-side charge. */
      if($st==='paid' && $prev!=='paid'){
        $orderRow=null;
        foreach(vestra_read_csv('orders.csv') as $row){ if(($row['ref']??'')===$ref){ $orderRow=$row; break; } }
        if($orderRow){
          vestra_charge_order_commission($ref, vestra_order_lines($orderRow)['lines']);
          if(!empty($orderRow['email'])){
            vestra_send_mail($orderRow['email'], "VESTRA — payment received for order {$ref}",
              "Hello ".($orderRow['name']?:($orderRow['company']?:'there')).",\n\nWe have received your invoice payment for order {$ref}. The seller is preparing your shipment — you'll get another email with tracking once it ships.\n\nTrack your order: https://vestrasales.com/buyer?tab=orders\n\n— VESTRA · vestrasales.com");
          }
          $notified=[];
          foreach(vestra_parse_order_items($orderRow['items']??'') as $it){
            $l=vestra_listing_by_sku($it['sku']); $sid=$l['seller_uid']??'';
            if($sid==='' || in_array($sid,$notified,true)) continue;
            $notified[]=$sid;
            foreach(auth_accounts() as $acc){
              if(($acc['id']??'')!==$sid || empty($acc['email'])) continue;
              vestra_send_mail($acc['email'], "VESTRA — order {$ref} is paid, please ship",
                "Hello ".($acc['name']?:($acc['company']?:'there')).",\n\nThe invoice for order {$ref} has been paid. Please ship the goods and mark the order as shipped in your panel (with tracking if available):\nhttps://vestrasales.com/seller?tab=orders\n\n— VESTRA · vestrasales.com");
              break;
            }
          }
        }
      }
    }
    header('Location: /admin?tab=orders&msg=status_ok'); exit;
  }
  /* One-time repair: give duplicate order refs (pre-uniqueness bug) fresh refs so
     each order gets its own independent status entry. */
  if($act==='fix_dup_refs'){
    $n=vestra_orders_fix_dup_refs();
    header('Location: /admin?tab=orders&msg=dupfix&n='.$n); exit;
  }
  /* Escrow dispute resolution — force-release the held funds to the seller, or
     refund the buyer in full (cancels the sale, claws the commission back).
     escrow_do_release()/escrow_do_refund() only push-notify (they're also called
     from the buyer's own confirm-receipt flow, which already sends its own emails) —
     an admin-forced resolution is a dispute outcome neither party triggered themselves,
     so both get an explicit email here instead of relying on push alone. */
  if($act==='escrow_release'){
    $ref=(string)($_POST['ref']??''); $rec=escrow_get($ref);
    $r=escrow_do_release($ref);
    if($r['ok'] && $rec){
      require_once __DIR__.'/inc/notify.php';
      $b=$rec['buyer']??[]; $seller=null;
      foreach(auth_accounts() as $a){ if(($a['id']??'')===($rec['seller_uid']??'')){ $seller=$a; break; } }
      $payout=(float)($rec['payout']??0);
      if($seller && !empty($seller['email'])){
        [$sSubj,$sBody,$sOpts]=vestra_tpl_escrow_release(vestra_user_lang($seller),$seller['name']?:($seller['company']?:'there'),'seller',$ref,$payout);
        vestra_send_mail($seller['email'],$sSubj,$sBody,'','',null,'',$sOpts);
      }
      if(!empty($b['email'])){
        $buyerAcc=auth_find($b['email']);
        [$bSubj,$bBody,$bOpts]=vestra_tpl_escrow_release(vestra_user_lang($buyerAcc),$b['name']?:'there','buyer',$ref,$payout);
        vestra_send_mail($b['email'],$bSubj,$bBody,'','',null,'',$bOpts);
      }
    }
    header('Location: /admin?tab=orders&msg='.($r['ok']?'esc_released':'esc_err')); exit;
  }
  if($act==='sample_release'){
    $ref=(string)($_POST['ref']??''); $rec=sample_get($ref);
    $r=sample_do_release($ref);
    if($r['ok'] && $rec && !empty($rec['seller_uid'])){
      require_once __DIR__.'/inc/notify.php';
      $seller=null; foreach(auth_accounts() as $a){ if(($a['id']??'')===$rec['seller_uid']){ $seller=$a; break; } }
      if($seller && !empty($seller['email'])){
        $payout=(float)($rec['payout']??0);
        vestra_send_mail($seller['email'], "VESTRA — sample payout released ({$ref})",
          "Hello ".($seller['name']?:($seller['company']?:'there')).",\n\n".
          "Your payout for sample order {$ref} (€".number_format($payout,2).") has been released and is on its way to your bank.\n\n".
          "— VESTRA · vestrasales.com");
      }
    }
    header('Location: /admin?tab=orders&msg='.($r['ok']?'spl_released':'spl_err')); exit;
  }
  if($act==='escrow_refund'){
    $ref=(string)($_POST['ref']??''); $rec=escrow_get($ref);
    $r=escrow_do_refund($ref);
    if($r['ok'] && $rec){
      require_once __DIR__.'/inc/notify.php';
      $b=$rec['buyer']??[]; $seller=null;
      foreach(auth_accounts() as $a){ if(($a['id']??'')===($rec['seller_uid']??'')){ $seller=$a; break; } }
      $total=(float)($rec['total']??0);
      if(!empty($b['email'])){
        $buyerAcc=auth_find($b['email']);
        [$bSubj,$bBody,$bOpts]=vestra_tpl_escrow_refund(vestra_user_lang($buyerAcc),$b['name']?:'there','buyer',$ref,$total);
        vestra_send_mail($b['email'],$bSubj,$bBody,'','',null,'',$bOpts);
      }
      if($seller && !empty($seller['email'])){
        [$sSubj,$sBody,$sOpts]=vestra_tpl_escrow_refund(vestra_user_lang($seller),$seller['name']?:($seller['company']?:'there'),'seller',$ref,$total);
        vestra_send_mail($seller['email'],$sSubj,$sBody,'','',null,'',$sOpts);
      }
    }
    header('Location: /admin?tab=orders&msg='.($r['ok']?'esc_refunded':'esc_err')); exit;
  }
  if($act==='create_promo'){ promo_create($_POST); header('Location: /admin?tab=marketing&msg=promo_ok'); exit; }
  if($act==='delete_promo'){
    $all=promo_all(); unset($all[strtoupper($_POST['del_code']??'')]); promo_save($all);
    header('Location: /admin?tab=marketing&msg=promo_del'); exit;
  }
  if($act==='toggle_promo'){
    $all=promo_all(); $k=strtoupper($_POST['toggle_code']??'');
    if(isset($all[$k])){ $all[$k]['active']=!($all[$k]['active']??true); promo_save($all); }
    header('Location: /admin?tab=marketing&msg=promo_toggled'); exit;
  }

  /* Customer discount vouchers — separate store from the seller invite codes above. */
  if($act==='create_voucher'){
    voucher_create([
      'code'=>$_POST['v_code']??'', 'type'=>($_POST['v_type']??'percent')==='fixed'?'fixed':'percent',
      'value'=>$_POST['v_value']??0, 'email'=>$_POST['v_email']??'',
      'first_order_only'=>!empty($_POST['v_first']), 'min_subtotal'=>$_POST['v_min']??0,
      'max_uses'=>$_POST['v_max']??1, 'expiry'=>$_POST['v_expiry']??'', 'campaign'=>$_POST['v_campaign']??'',
    ]);
    header('Location: /admin?tab=marketing&msg=voucher_ok'); exit;
  }
  if($act==='toggle_voucher'){
    $all=voucher_all(); $k=voucher_norm($_POST['v_toggle']??'');
    if(isset($all[$k])){ $all[$k]['active']=!($all[$k]['active']??true); voucher_save($all); }
    header('Location: /admin?tab=marketing&msg=voucher_toggled'); exit;
  }
  if($act==='delete_voucher'){
    $all=voucher_all(); unset($all[voucher_norm($_POST['v_del']??'')]); voucher_save($all);
    header('Location: /admin?tab=marketing&msg=voucher_del'); exit;
  }
  /* Welcome campaign. The preview writes nothing; the send is capped and safe to repeat
     (see voucher_welcome_run) so a browser timeout mid-send can be retried by clicking again. */
  if($act==='welcome_vouchers'){
    $rep = voucher_welcome_run([
      'percent'=>$_POST['w_pct']??5, 'months'=>$_POST['w_months']??6,
      'audience'=>($_POST['w_aud']??'buyers'), 'limit'=>$_POST['w_limit']??200,
      'dry'=>(($_POST['w_mode']??'dry')!=='send'),
      'exclude_countries'=>preg_split('/[,;\n]+/', (string)($_POST['w_notc']??''), -1, PREG_SPLIT_NO_EMPTY),
    ]);
    $_SESSION['welcome_report'] = $rep;
    header('Location: /admin?tab=marketing&msg='.((($_POST['w_mode']??'dry')!=='send')?'welcome_dry':'welcome_sent')); exit;
  }

  // ── Seller prospecting (lead CRM + templated outreach) ──────────────────────
  if($act==='add_lead'){
    $company=trim($_POST['company']??''); $email=strtolower(trim($_POST['email']??''));
    if($company!=='' && filter_var($email,FILTER_VALIDATE_EMAIL)){
      $leads=vestra_leads();
      $dupe=false; foreach($leads as $l){ if(strtolower($l['email']??'')===$email){ $dupe=true; break; } }
      if(!$dupe){
        $leads[]=[
          'id'=>'LD'.strtoupper(bin2hex(random_bytes(4))),'added_at'=>date('c'),
          'company'=>$company,'contact_name'=>trim($_POST['contact_name']??''),'email'=>$email,
          'country'=>trim($_POST['country']??''),'website'=>trim($_POST['website']??''),
          'source'=>trim($_POST['source']??'')?:'Other','category'=>trim($_POST['category']??''),
          'notes'=>trim($_POST['notes']??''),'status'=>'new','last_contacted_at'=>'',
          'unsub_token'=>bin2hex(random_bytes(16)),
        ];
        vestra_save_leads($leads);
        header('Location: /admin?tab=prospects&msg=lead_added'); exit;
      }
      header('Location: /admin?tab=prospects&msg=lead_dupe'); exit;
    }
    header('Location: /admin?tab=prospects&msg=lead_invalid'); exit;
  }
  if($act==='import_leads_csv'){
    $added=0; $skipped=0;
    if(!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])){
      [$added,$skipped]=vestra_lead_import_csv($_FILES['csv']['tmp_name']);
    }
    header('Location: /admin?tab=prospects&msg=lead_import&added='.$added.'&skipped='.$skipped); exit;
  }
  if($act==='update_lead_status'){
    $lid=$_POST['lid']??''; $st=$_POST['status']??'';
    if(in_array($st,VESTRA_LEAD_STATUSES,true)){
      $leads=vestra_leads();
      foreach($leads as &$l){ if(($l['id']??'')===$lid){ $l['status']=$st; break; } }
      unset($l);
      vestra_save_leads($leads);
    }
    header('Location: /admin?tab=prospects&msg=lead_status_ok'); exit;
  }
  if($act==='delete_lead'){
    $lid=$_POST['lid']??'';
    vestra_save_leads(array_values(array_filter(vestra_leads(),fn($l)=>($l['id']??'')!==$lid)));
    header('Location: /admin?tab=prospects&msg=lead_deleted'); exit;
  }
  /* Enrich a research lead that was imported without an email (or fix a wrong one). */
  if($act==='set_lead_email'){
    $lid=$_POST['lid']??''; $email=strtolower(trim($_POST['email']??''));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){ header('Location: /admin?tab=prospects&msg=lead_invalid'); exit; }
    $leads=vestra_leads();
    foreach($leads as $l){ if(strtolower($l['email']??'')===$email && ($l['id']??'')!==$lid){ header('Location: /admin?tab=prospects&msg=lead_dupe'); exit; } }
    foreach($leads as &$l){ if(($l['id']??'')===$lid){ $l['email']=$email; break; } }
    unset($l);
    vestra_save_leads($leads);
    header('Location: /admin?tab=prospects&msg=lead_email_ok'); exit;
  }
  /* Save the operator's email-finder API key (global, in email_settings.json). */
  if($act==='save_finder'){
    $dir=vestra_data_dir(); if(!is_dir($dir)) @mkdir($dir,0775,true);
    $cur=is_readable($dir.'/email_settings.json')?json_decode((string)file_get_contents($dir.'/email_settings.json'),true):[]; if(!is_array($cur))$cur=[];
    $cur['finder_provider']=trim($_POST['finder_provider']??'hunter')?:'hunter';
    $k=trim($_POST['finder_key']??''); $cur['finder_key']=$k!==''?$k:(string)($cur['finder_key']??'');
    file_put_contents($dir.'/email_settings.json',json_encode($cur,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chmod($dir.'/email_settings.json',0600);
    header('Location: /admin?tab=prospects&msg=finder_saved'); exit;
  }
  /* Save the AI (DeepSeek) key for outreach personalisation — optional; falls back
     to a server DEEPSEEK_KEY constant. Stored web-blocked, never in git. */
  if($act==='save_ai'){
    $dir=vestra_data_dir(); if(!is_dir($dir)) @mkdir($dir,0775,true);
    $cur=is_readable($dir.'/email_settings.json')?json_decode((string)file_get_contents($dir.'/email_settings.json'),true):[]; if(!is_array($cur))$cur=[];
    $k=trim($_POST['ai_key']??''); $cur['ai_key']=$k!==''?$k:(string)($cur['ai_key']??'');
    if(($m=trim($_POST['ai_model']??''))!=='') $cur['ai_model']=$m;
    file_put_contents($dir.'/email_settings.json',json_encode($cur,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chmod($dir.'/email_settings.json',0600);
    header('Location: /admin?tab=prospects&msg=ai_saved'); exit;
  }
  /* Find a verified email for one customer from its website domain. */
  if($act==='find_lead_email'){
    require_once __DIR__.'/inc/notify.php';
    $lid=$_POST['lid']??''; $leads=vestra_leads(); $found='';
    foreach($leads as &$l){ if(($l['id']??'')!==$lid) continue;
      if(($l['email']??'')==='' ){ $found=vestra_find_email((string)($l['website']??'')); if($found!=='') $l['email']=$found; }
      break; }
    unset($l); vestra_save_leads($leads);
    header('Location: /admin?tab=prospects&msg='.($found!==''?'finder_ok':'finder_none')); exit;
  }
  /* One-at-a-time email lookup for the live progress view — same shape as send_lead_one,
     so a failure shows up as a visible per-company line (with a reason) instead of a
     silent batch total. This is the ONLY way to look up missing emails now — no more
     opaque bulk action that just returns a count with no way to tell what went wrong. */
  if($act==='find_lead_email_one'){
    header('Content-Type: application/json');
    require_once __DIR__.'/inc/notify.php';
    $lid=$_POST['lid']??''; $leads=vestra_leads();
    $res=['ok'=>false,'company'=>'','website'=>'','email'=>'','error'=>'notfound'];
    foreach($leads as &$l){
      if(($l['id']??'')!==$lid) continue;
      $res['company']=$l['company']??''; $res['website']=$l['website']??'';
      if(($l['email']??'')!==''){ $res['ok']=true; $res['email']=$l['email']; break; }
      if(($l['website']??'')===''){ $res['error']='nowebsite'; break; }
      $found=vestra_find_email((string)$l['website']);
      if($found!==''){ $l['email']=$found; $res['ok']=true; $res['email']=$found; }
      else { $res['error']='notfound'; }
      break;
    }
    unset($l); vestra_save_leads($leads);
    echo json_encode($res); exit;
  }
  /* Auto-discover real small/medium clothing & textile retailers (OpenStreetMap, free, no
     key) and add them straight to the customer list — fast, no per-candidate network calls.
     Emails are a separate step (find_lead_email_one above) so a slow/failing site-lookup
     never blocks discovery itself. */
  if($act==='discover_leads'){
    header('Content-Type: application/json');
    @set_time_limit(0); require_once __DIR__.'/inc/notify.php';
    $country=trim($_POST['disc_country']??''); $city=trim($_POST['disc_city']??'');
    $rows=$country!==''?vestra_discover_osm($country,$city,80):[];
    $osmOk=$country!==''?vestra_osm_ok():true;
    $timedOut=function_exists('vestra_osm_timeout')?vestra_osm_timeout():false;
    [$addedRows,$skipped]=$rows?vestra_leads_add($rows):[[],0];
    $newIds=array_values(array_map(fn($r)=>$r['id'],array_filter($addedRows,fn($r)=>$r['email']===''&&$r['website']!=='')));
    /* Bos sonucun SEBEBINI soyle. Ciplak "0 bulundu" en yaniltici cikti: kullanici
       "bu ulkede butik yokmus" saniyor, oysa neredeyse her zaman sorgu agir geldigi
       icin Overpass yetismiyor. Sehir verilince ayni sorgu calisiyor. */
    $note='';
    if(!$rows){
      if($timedOut){
        $note=$city===''
          ? 'Overpass ülke geneli sorguda zaman aşımına uğradı — ülke çok geniş. Şehir yazıp tekrar deneyin (ör. Amsterdam, Milan, Zurich); şehir bazlı arama çalışıyor.'
          : 'Overpass zaman aşımına uğradı — sunucu şu an yoğun. Birkaç dakika sonra tekrar deneyin.';
      } elseif(!$osmOk){
        $note='OpenStreetMap sunucularının hiçbiri yanıt vermedi. Geçici bir kesinti; birkaç dakika sonra tekrar deneyin.';
      } elseif($city===''){
        $note='Sonuç boş. Ülke geneli aramalar çoğu zaman tamamlanamıyor — bir şehir yazıp deneyin.';
      } else {
        $note='Bu şehir için OSM\'de aradığımız kategorilerde kayıtlı dükkan bulunamadı. Şehir adını yerel dilde de deneyebilirsiniz.';
      }
    }
    echo json_encode(['ok'=>true,'total'=>count($rows),'added'=>count($addedRows),'newIds'=>$newIds,
                      'osm_ok'=>$osmOk,'timed_out'=>$timedOut,'note'=>$note]); exit;
  }
  /* Written by the "Run now" button once its live discovery + email-lookup finishes, so a
     manual run leaves the exact same status trail as the 09:00 cron (inc/leads.php). */
  if($act==='record_automation_result'){
    header('Content-Type: application/json');
    $osmOk=($_POST['osm_ok']??'1')==='1';
    vestra_cron_write_status(trim($_POST['country']??''),(int)($_POST['found']??0),(int)($_POST['added']??0),
      (int)($_POST['emails_found']??0),(int)($_POST['emails_checked']??0),'manual',
      $osmOk?'':'OpenStreetMap (Overpass) sorgusu basarisiz oldu — tum yansi sunucular hata verdi. Bu ulke icin sonuclar eksik/bos olabilir, tekrar deneyin.');
    echo json_encode(['ok'=>true]); exit;
  }
  /* Bulk-delete selected prospects (e.g. big-chain results from before the discovery
     filter, or a bad CSV import) — same lead_ids[] checkboxes as the send actions. */
  if($act==='delete_leads_bulk'){
    $ids=array_filter((array)($_POST['lead_ids']??[]));
    $before=count(vestra_leads());
    vestra_save_leads(array_values(array_filter(vestra_leads(),fn($l)=>!in_array($l['id']??'',$ids,true))));
    $n=$before-count(vestra_leads());
    header('Location: /admin?tab=prospects&msg=lead_bulk_deleted&n='.$n); exit;
  }
  if($act==='save_lead_template'){
    $img=trim($_POST['tpl_img_keep']??'');
    if(($_POST['tpl_img_clear']??'')==='1') $img='';
    if(!empty($_FILES['tpl_img']['name'])){ $up=vestra_save_upload_photo($_FILES['tpl_img']); if($up!=='') $img=$up; }
    vestra_save_lead_template(['subject'=>trim($_POST['tpl_subject']??''),'body'=>trim($_POST['tpl_body']??''),'img'=>$img]);
    header('Location: /admin?tab=prospects&msg=lead_tpl_ok'); exit;
  }
  if($act==='send_lead_email'){
    @set_time_limit(0); // up to 50 individual sends — don't let a slow SMTP host time the request out
    $ids=array_slice(array_filter((array)($_POST['lead_ids']??[])),0,50);
    $leads=vestra_leads(); $tpl=vestra_lead_template(); $sent=0;
    require_once __DIR__.'/inc/notify.php';
    // Optional: send the whole batch on behalf of a seller, from THEIR own address.
    $sellerUid=trim($_POST['l_seller_uid']??''); $senderName=''; $sc=null;
    if($sellerUid!==''){
      $sc=vestra_seller_mail($sellerUid);
      if(!vestra_seller_can_send($sc)){ header('Location: /admin?tab=prospects&mailfor='.urlencode($sellerUid).'&msg=quote_nosender'); exit; }
      $a0=array_values(array_filter(auth_accounts(),fn($a)=>($a['id']??'')===$sellerUid))[0]??null;
      $senderName=$a0?($a0['company']??$a0['name']??''):(string)($sc['smtp_name']??'');
    }
    $heroImg=($tpl['img']??'')!==''?'https://vestrasales.com'.$tpl['img']:'';
    foreach($leads as &$l){
      if(!in_array($l['id']??'',$ids,true)) continue;
      if(($l['status']??'')==='unsubscribed') continue; // never re-email an opt-out
      if(!filter_var($l['email']??'',FILTER_VALIDATE_EMAIL)) continue; // research lead without an email yet
      if(vestra_name_is_blocked((string)($l['company']??''),(string)($l['brand']??''))) continue; // buyuk magaza/tek-marka -- teklif gonderme
      if(($l['last_contacted_at']??'')!=='') continue; // already emailed once — no auto-resend
      [$subject,$body]=vestra_lead_render_email($l,$tpl);
      if(vestra_send_mail($l['email'],$subject,$body,'',$senderName,$sc,$heroImg)){
        $sent++;
        if(($l['status']??'new')==='new') $l['status']='contacted';
        $l['last_contacted_at']=date('c');
      }
    }
    unset($l);
    vestra_save_leads($leads);
    header('Location: /admin?tab=prospects&msg=lead_sent&n='.$sent); exit;
  }
  /* One-at-a-time send for the live progress view — the JS calls this once per
     selected customer so the operator watches each email go out. Returns JSON. */
  if($act==='send_lead_one'){
    header('Content-Type: application/json');
    require_once __DIR__.'/inc/notify.php';
    $lid=$_POST['lead_id']??''; $sellerUid=trim($_POST['l_seller_uid']??'');
    $sc=null; $senderName='';
    if($sellerUid!==''){
      $sc=vestra_seller_mail($sellerUid);
      if(!vestra_seller_can_send($sc)){ echo json_encode(['ok'=>false,'error'=>'nosender']); exit; }
      $a0=array_values(array_filter(auth_accounts(),fn($a)=>($a['id']??'')===$sellerUid))[0]??null;
      $senderName=$a0?($a0['company']??$a0['name']??''):'';
    }
    $leads=vestra_leads(); $tpl=vestra_lead_template(); $ai=($_POST['ai']??'')==='1'; $res=['ok'=>false,'company'=>'','email'=>'','error'=>'notfound'];
    $heroImg=($tpl['img']??'')!==''?'https://vestrasales.com'.$tpl['img']:'';
    foreach($leads as &$l){
      if(($l['id']??'')!==$lid) continue;
      $res['company']=$l['company']??''; $res['email']=$l['email']??''; $res['error']='';
      if(($l['status']??'')==='unsubscribed'){ $res['error']='unsub'; break; }
      if(!filter_var($l['email']??'',FILTER_VALIDATE_EMAIL)){ $res['error']='noemail'; break; }
      /* Big chain / monobrand flagship — never send them an offer, even if one slipped into
         the list by hand. Same blocklist discovery uses, applied here as a hard safety net. */
      if(vestra_name_is_blocked((string)($l['company']??''),(string)($l['brand']??''))){ $res['error']='blocked'; break; }
      /* Already emailed once — never auto-resend the same outreach to the same
         boutique. The lead stays in the list either way; this only blocks a repeat
         send (accidental re-select, a second "run all", etc.), not the record itself. */
      if(($l['last_contacted_at']??'')!==''){ $res['error']='already_sent'; break; }
      $pair=$ai?vestra_ai_personalize($l,$tpl,$senderName):null;
      [$subject,$body]=$pair!==null?$pair:vestra_lead_render_email($l,$tpl);
      if(vestra_send_mail($l['email'],$subject,$body,'',$senderName,$sc,$heroImg)){ $res['ok']=true; $res['ai']=($pair!==null); if(($l['status']??'new')==='new') $l['status']='contacted'; $l['last_contacted_at']=date('c'); }
      else { $res['error']='send'; }
      break;
    }
    unset($l); vestra_save_leads($leads);
    echo json_encode($res); exit;
  }
  /* Send a tailored product OFFER (quote) straight to a customer — selected listings
     + prices, emailed and logged to data/quotes.csv. Respects opt-outs: a saved
     prospect who unsubscribed is never emailed. */
  if($act==='send_quote'){
    require_once __DIR__.'/inc/notify.php';
    $email=strtolower(trim($_POST['q_email']??'')); $company=trim($_POST['q_company']??'');
    $contact=trim($_POST['q_contact']??''); $note=trim($_POST['q_note']??'');
    $pids=array_slice(array_values(array_filter((array)($_POST['q_products']??[]))),0,20);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || !$pids){ header('Location: /admin?tab=prospects&msg=quote_invalid'); exit; }
    // Optional: send on behalf of a seller, from THEIR own configured address.
    $sellerUid=trim($_POST['q_seller_uid']??''); $senderName=''; $sc=null;
    if($sellerUid!==''){
      $sc=vestra_seller_mail($sellerUid);
      if(!vestra_seller_can_send($sc)){ header('Location: /admin?tab=prospects&mailfor='.urlencode($sellerUid).'&msg=quote_nosender'); exit; }
      $a0=array_values(array_filter(auth_accounts(),fn($a)=>($a['id']??'')===$sellerUid))[0]??null;
      $senderName=$a0?($a0['company']??$a0['name']??''):(string)($sc['smtp_name']??'');
    }
    // Never send to a prospect who opted out; reuse their unsubscribe link if saved.
    $unsubUrl='';
    foreach(vestra_leads() as $l){ if(strtolower($l['email']??'')===$email){
      if(($l['status']??'')==='unsubscribed'){ header('Location: /admin?tab=prospects&msg=quote_unsub'); exit; }
      $unsubUrl='https://vestrasales.com/lead-unsubscribe?token='.urlencode($l['unsub_token']??''); break; } }
    $fmt=fn($n)=>'€'.rtrim(rtrim(number_format((float)$n,2),'0'),'.');
    $lines=[]; $heroImg='';
    foreach($pids as $pid){
      $p=vestra_find($pid); if(!$p) continue;
      $price='from '.$fmt(vestra_from_price($p)).'/'.($p['unit']??'pc');
      if(($p['mode']??'')==='sale' && !empty($p['list'])) $price.=' (was '.$fmt($p['list']).')';
      $lines[]=['title'=>trim(($p['brand']??'').' '.($p['name']??'')),'price'=>$price,
        'moq'=>'MOQ '.(int)($p['moq']??0).' '.($p['unit']??'pc'),
        'url'=>'https://vestrasales.com/product?id='.rawurlencode($p['id']??'')];
      if($heroImg===''){ $img=vestra_primary_image($p); if($img!=='') $heroImg='https://vestrasales.com'.$img; }
    }
    if(!$lines){ header('Location: /admin?tab=prospects&msg=quote_invalid'); exit; }
    [$subject,$body]=vestra_quote_render_email($company,$contact,$lines,$note,$unsubUrl,$senderName);
    $ok=vestra_send_mail($email,$subject,$body,'',$senderName,$sc,$heroImg);
    $dir=vestra_data_dir(); if(!is_dir($dir)) @mkdir($dir,0775,true);
    if($fh=@fopen($dir.'/quotes.csv','a')){
      if(ftell($fh)===0) fputcsv($fh,['timestamp','email','company','contact','sender','products','note','sent'],',','"','\\');
      fputcsv($fh,[date('c'),$email,$company,$contact,$senderName?:'Platform',implode(' | ',array_map(fn($x)=>$x['title'],$lines)),$note,$ok?'yes':'no'],',','"','\\');
      fclose($fh);
    }
    header('Location: /admin?tab=prospects&msg='.($ok?'quote_sent':'quote_failed')); exit;
  }
  /* Save the operator's own sending identity + transport (SMTP or HTTP API) so all
     outbound mail goes out "from" their address. Written to data/email_settings.json
     (web-denied, gitignored); the password is kept if the field is left blank. */
  if($act==='save_email_settings'){
    $uid=trim($_POST['target_uid']??'');
    $cur = $uid!=='' ? vestra_seller_mail($uid)
         : (is_readable(vestra_data_dir().'/email_settings.json')?json_decode((string)file_get_contents(vestra_data_dir().'/email_settings.json'),true):[]);
    if(!is_array($cur)) $cur=[];
    $from=trim($_POST['from_email']??''); $pass=(string)($_POST['smtp_pass']??''); $apiKey=trim($_POST['mail_api_key']??'');
    $s=[
      'mail_enabled'=>!empty($_POST['mail_enabled']),
      'mail_from'=>$from, 'smtp_from'=>$from,
      'smtp_name'=>trim($_POST['from_name']??'')?:'VESTRA',
      'smtp_host'=>trim($_POST['smtp_host']??''),
      'smtp_port'=>(int)($_POST['smtp_port']??587)?:587,
      'smtp_user'=>trim($_POST['smtp_user']??'')?:$from,
      'smtp_pass'=>$pass!==''?$pass:(string)($cur['smtp_pass']??''),
      'mail_api_provider'=>trim($_POST['mail_api_provider']??'brevo')?:'brevo',
      'mail_api_key'=>$apiKey!==''?$apiKey:(string)($cur['mail_api_key']??''),
    ];
    if($uid!==''){ vestra_seller_mail_save($uid,$s); }
    else { $dir=vestra_data_dir(); if(!is_dir($dir)) @mkdir($dir,0775,true); file_put_contents($dir.'/email_settings.json',json_encode($s,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chmod($dir.'/email_settings.json',0600); }
    header('Location: /admin?tab=prospects'.($uid!==''?'&mailfor='.urlencode($uid):'').'&msg=email_saved'); exit;
  }
  if($act==='send_test_email'){
    require_once __DIR__.'/inc/notify.php';
    $to=trim($_POST['test_to']??''); $uid=trim($_POST['target_uid']??'');
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)){ header('Location: /admin?tab=prospects&msg=test_invalid'); exit; }
    $body="This is a test from your VESTRA sending setup.\n\nIf you received this, outbound email works and offers will send from this address. \xE2\x9C\x93\n\n— VESTRA";
    if($uid!==''){ $sc=vestra_seller_mail($uid); $ok=vestra_send_mail($to,'VESTRA — test email',$body,'',(string)($sc['smtp_name']??''),$sc); }
    else { $ok=vestra_send_mail($to,'VESTRA — test email',$body); }
    header('Location: /admin?tab=prospects'.($uid!==''?'&mailfor='.urlencode($uid):'').'&msg='.($ok?'test_ok':'test_fail')); exit;
  }
  /* Operator replies in a conversation from Admin → Messages, always speaking as
     VESTRA Support when that's one of the two thread slots — whether that's the
     seller slot (buyer messaged a seller-less listing) or the buyer slot (admin
     started this thread with a seller directly, from Admin → Users). Threads
     between two real accounts keep the old default: reply as the seller side. */
  if($act==='admin_reply'){
    require_once __DIR__.'/inc/messages.php';
    $tid=$_POST['thread_id']??''; $body=trim($_POST['body']??'');
    $th=vestra_msg_find_thread($tid);
    if($th && $body!==''){
      $from=((string)($th['buyer_uid']??''))===VESTRA_SUPPORT_UID ? VESTRA_SUPPORT_UID : (string)($th['seller_uid']??'');
      vestra_msg_send((string)($th['buyer_uid']??''),(string)($th['seller_uid']??''),$from,$body,(string)($th['listing_id']??''));
    }
    header('Location: /admin?tab=messages&msg=replied'); exit;
  }
  /* Admin starts a fresh on-platform thread with a buyer or seller straight from
     Admin → Users — e.g. an account with no usable email on file yet. */
  if($act==='start_message'){
    $uid=trim($_POST['uid']??''); $body=trim($_POST['body']??'');
    $target=null; foreach(auth_accounts() as $a){ if(($a['id']??'')===$uid){ $target=$a; break; } }
    $tid='';
    if($target && $body!==''){
      require_once __DIR__.'/inc/messages.php';
      $ttype=($target['type']??'')==='seller'?'seller':'buyer';
      $res=vestra_msg_admin_start($uid,$ttype,$body);
      if(!empty($res['ok'])) $tid=$res['thread_id'];
    }
    header('Location: /admin?tab=messages'.($tid!==''?('&thread='.urlencode($tid)):'&msg=msg_err')); exit;
  }
  /* Fix a missing/wrong email on any account — the operator's only lever when an
     account (e.g. an auto-created seller) was never given a working address, since
     that silently breaks every order/offer/message notification meant for them. */
  if($act==='set_account_email'){
    $uid=trim($_POST['uid']??''); $email=trim($_POST['email']??'');
    $msg='email_set';
    if($uid!==''){
      if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)){ $msg='email_invalid'; }
      else {
        $accs=auth_accounts();
        $dupe=false;
        foreach($accs as $a){ if($email!==''&&($a['id']??'')!==$uid&&strcasecmp((string)($a['email']??''),$email)===0){ $dupe=true; break; } }
        if($dupe){ $msg='email_dupe'; }
        else {
          foreach($accs as &$a){ if(($a['id']??'')===$uid){ $a['email']=$email; break; } }
          unset($a);
          auth_save_accounts($accs);
        }
      }
    }
    header('Location: /admin?tab=users&msg='.$msg); exit;
  }

  /* ── Hesap silme ───────────────────────────────────────────────────────────
     Kalici ve geri alinamaz, o yuzden dar tutuldu:
      - Silmeden once hesabin JSON yedegi data/deleted-accounts/ altina yazilir.
        GDPR silme talebi de gelse, "yanlis hesabi sildim" kazasi da olsa, ticari
        kayit (kimin siparis verdigi) tamamen ucup gitmemeli.
      - Siparisi/faturasi olan hesap SILINMEZ. Fatura kaydi hesaba baglidir;
        silinirse gecmis siparisler sahipsiz kalir ve muhasebe izi kopar.
        Boyle bir hesabi kapatmak isteyen once siparisleri arsivlemeli.
      - Kendi admin oturumunu degil, sadece musteri hesaplarini hedefler. */
  if($act==='delete_account'){
    $uid=trim($_POST['uid']??'');
    $msg='del_none';
    if($uid!==''){
      $accs=auth_accounts();
      $target=null;
      foreach($accs as $a){ if(($a['id']??'')===$uid){ $target=$a; break; } }
      if(!$target){ $msg='del_notfound'; }
      else {
        /* Siparis/fatura bagi var mi? Varsa silme -- ticari iz kopar. */
        $hasOrders=false;
        if(function_exists('vestra_orders')){
          foreach(vestra_orders() as $o){
            if((string)($o['buyer_uid']??'')===$uid || (string)($o['seller_uid']??'')===$uid){ $hasOrders=true; break; }
          }
        }
        if($hasOrders){ $msg='del_hasorders'; }
        else {
          $dir=vestra_data_dir().'/deleted-accounts';
          if(!is_dir($dir)) @mkdir($dir,0775,true);
          @file_put_contents($dir.'/'.preg_replace('/[^a-z0-9_-]/i','',$uid).'-'.gmdate('Ymd-His').'.json',
            json_encode($target+['deleted_at'=>gmdate('c')], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
          $left=[];
          foreach($accs as $a){ if(($a['id']??'')!==$uid) $left[]=$a; }
          if(count($left)===count($accs)){ $msg='del_notfound'; }
          else { auth_save_accounts($left); $msg='del_ok'; }
        }
      }
    }
    header('Location: /admin?tab=users&msg='.$msg); exit;
  }

  /* ── Notification Center: broadcast a push to all / buyers / sellers / one user ── */
  if($act==='send_push'){
    require_once __DIR__.'/inc/push.php';
    $target = $_POST['target'] ?? 'all';
    $title  = trim($_POST['title'] ?? '');
    $body   = trim($_POST['body']  ?? '');
    $url    = trim($_POST['url']   ?? '');
    if($url==='' || $url[0]!=='/') $url='/shop';       // same-origin only — never push external links
    if($title==='' || $body===''){ header('Location: /admin?tab=notify&msg=push_err'); exit; }
    $uids=[];
    foreach(auth_accounts() as $a){
      $uid=(string)($a['id']??''); if($uid==='') continue;
      $type=$a['type']??'';
      $hit = match($target){
        'buyers'  => $type==='buyer',
        'sellers' => $type==='seller',
        'user'    => $uid===($_POST['uid']??''),
        default   => true, // 'all'
      };
      if($hit) $uids[]=$uid;
    }
    $reached=vestra_push_broadcast($uids, mb_substr($title,0,80), mb_substr($body,0,160), $url);
    vestra_push_log(['at'=>date('c'),'target'=>$target,'title'=>mb_substr($title,0,80),'reached'=>$reached]);
    header('Location: /admin?tab=notify&msg=push_sent&n='.$reached); exit;
  }
}

// ── Document file download (admin only) ───────────────────────────────────────
if($authed && isset($_GET['dl_doc'])){
  $uid  = preg_replace('/[^a-f0-9]/','', $_GET['uid']??'');
  $file = basename($_GET['dl_doc']??'');
  if($uid && $file){
    $path = auth_doc_file_path($uid, $file);
    if(is_readable($path)){
      $ext  = strtolower(pathinfo($file,PATHINFO_EXTENSION));
      $mime = match($ext){ 'pdf'=>'application/pdf','jpg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp',default=>'application/octet-stream' };
      header('Content-Type: '.$mime);
      header('Content-Disposition: inline; filename="'.addslashes($file).'"');
      readfile($path); exit;
    }
  }
  http_response_code(404); echo 'File not found'; exit;
}

// ── CSV download ───────────────────────────────────────────────────────────────
if($authed && ($_GET['dl']??'')==='sellers'){
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="vestra-sellers.csv"');
  $out=fopen('php://output','w');
  fputcsv($out,['company','contact_name','email','country','address','vat_id','reg_number','phone','website','status','kyb_status'],',','"','\\');
  foreach(auth_accounts() as $a){ if(($a['type']??'')!=='seller') continue;
    fputcsv($out,[$a['company']??'',$a['name']??'',$a['email']??'',$a['country']??'',$a['address']??'',$a['vat_id']??'',$a['reg_number']??'',$a['phone']??'',$a['website']??'',$a['status']??'',$a['kyb_status']??''],',','"','\\');
  }
  fclose($out); exit;
}
if($authed && isset($_GET['dl'])){
  $map=['signups'=>'signups.csv','orders'=>'orders.csv','offers'=>'offers.csv','requests'=>'requests.csv','groups'=>'groups.csv','request_offers'=>'request_offers.csv'];
  $f=$map[$_GET['dl']]??null; $path=$f?vestra_data_dir().'/'.$f:'';
  if($f && is_readable($path)){ header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="vestra-'.$f.'"'); readfile($path); exit; }
  http_response_code(404); echo 'No data'; exit;
}

// ── Helper functions ───────────────────────────────────────────────────────────
function abadge(string $t, string $c='#888'): string {
  return '<span style="display:inline-flex;align-items:center;white-space:nowrap;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:'.$c.'22;color:'.$c.';border:1px solid '.$c.'44">'.htmlspecialchars($t).'</span>';
}
function kybBadge(string $s): string {
  return match($s){ 'approved'=>abadge('✓ Verified','#1f9d63'),'suspended'=>abadge('⊘ Suspended','#c0392b'),default=>abadge('⏳ Pending','#a9781a') };
}
function docBadge(string $s): string {
  return match($s){ 'approved'=>abadge('✓ Approved','#1f9d63'),'rejected'=>abadge('✗ Rejected','#c0392b'),'uploaded'=>abadge('📤 Review','#9a7320'),'requested'=>abadge('📋 Requested','#3366cc'),default=>abadge('—','#555') };
}
function orderBadge(string $s): string {
  return match($s){ 'completed'=>abadge('✓ Completed','#1f9d63'),'shipped'=>abadge('🚚 Shipped','#9a7320'),'paid'=>abadge('💶 Paid — to ship','#3a6fb0'),default=>abadge('⏳ Awaiting payment','#888') };
}
function typePill(string $t): string {
  $c=$t==='seller'?'#9a7320':'#3366cc'; $b=$t==='seller'?'rgba(201,168,106,.15)':'rgba(138,180,248,.15)';
  return '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:'.$b.';color:'.$c.'">'.htmlspecialchars($t).'</span>';
}
function memberBadge(string $tier, string $status): string {
  if($tier===''&&($status===''||$status==='none')) return '<span style="color:#555;font-size:11px">—</span>';
  $tc=['starter'=>'#3366cc','pro'=>'#9a7320','premium'=>'#a9781a'][$tier]??'#888';
  $tl=$tier==='premium' ? 'Elite' : ($tier?ucfirst($tier):'');
  $sc=match($status){'active'=>'#1f9d63','trialing'=>'#a9781a','past_due'=>'#c0392b','canceled'=>'#888',default=>'#555'};
  $sl=match($status){'active'=>'Active','trialing'=>'Trial','past_due'=>'Past due','canceled'=>'Canceled',default=>'—'};
  return ($tl?abadge($tl,$tc):'').'<div style="margin-top:3px">'.abadge($sl,$sc).'</div>';
}
function fBtn(string $label, string $act, array $fields, string $style='', string $confirm=''): string {
  $oc=$confirm?' onclick="return confirm(\''.htmlspecialchars(addslashes($confirm)).'\')"':'';
  $h='<form method="post" style="display:inline">';
  $h.=csrfField();
  $h.='<input type="hidden" name="_action" value="'.htmlspecialchars($act).'">';
  foreach($fields as $k=>$v) $h.='<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';
  $h.='<button type="submit" class="abtn"'.$oc.' style="'.htmlspecialchars($style).'">'.htmlspecialchars($label).'</button></form> ';
  return $h;
}
function arow(array $cells, bool $head=false): string {
  $tag=$head?'th':'td';
  return '<tr>'.implode('',array_map(fn($c)=>'<'.$tag.' class="ac">'.$c.'</'.$tag.'>',$cells)).'</tr>';
}
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>VESTRA Admin</title>
<link rel="stylesheet" href="/inc/style.css">
<style>
:root{--sb:220px;
  --bg:#f4f2ee; --bg2:#ffffff; --bg3:#faf8f4; --ink:#211d17; --mut:#6f695e;
  --acc:#a97f2c; --line:#e6e0d5; --ok:#2e9e6b; --bad:#d0574f;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--ink);font-family:'Inter',sans-serif;min-height:100vh}
.alayout{display:grid;grid-template-columns:var(--sb) 1fr;grid-template-rows:52px 1fr;min-height:100vh}
/* top bar */
.atopbar{grid-column:1/-1;background:#ffffff;border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 20px;gap:14px;position:sticky;top:0;z-index:100;box-shadow:0 1px 3px rgba(60,50,30,.05)}
.atopbar .logo{display:flex;align-items:center;gap:8px;color:var(--ink);text-decoration:none;font-weight:700;font-size:15px;width:var(--sb);flex-shrink:0}
.atopbar .logo svg{flex-shrink:0}
.atopbar-links{margin-left:auto;display:flex;gap:8px}
/* sidebar */
/* display:flex/column overrides the global site nav{display:flex} (row), which
   otherwise lays the whole sidebar out horizontally and clips it off-screen. */
.asidebar{display:flex;flex-direction:column;gap:2px;background:#fbfaf7;border-right:1px solid var(--line);padding:10px 10px;position:sticky;top:52px;height:calc(100vh - 52px);overflow-y:auto}
.asidebar a{display:flex;align-items:center;gap:11px;padding:8px 11px;color:var(--mut);text-decoration:none;font-size:13px;font-weight:500;border-radius:9px;transition:.13s}
.asidebar a:hover{color:var(--ink);background:rgba(0,0,0,.045)}
.asidebar a.on{color:var(--acc);background:rgba(169,127,44,.12);font-weight:600;box-shadow:inset 0 0 0 1px rgba(169,127,44,.18)}
.asidebar a svg{flex:none;opacity:.75}
.asidebar a:hover svg,.asidebar a.on svg{opacity:1}
.asidebar .alabel{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.asidebar .sgrp{padding:15px 11px 5px;font-size:9.5px;font-weight:700;letter-spacing:.11em;color:#b3aa97;text-transform:uppercase}
.asidebar .sgrp:first-child{padding-top:4px}
.aside-badge{margin-left:auto;background:var(--acc);color:#fff;border-radius:20px;padding:1px 7px;font-size:10px;font-weight:700;line-height:1.6}
.aside-badge.red{background:var(--bad);color:#fff}
/* main */
.amain{padding:28px 32px;overflow-y:auto}
/* stat cards */
.asgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:13px;margin-bottom:26px}
.ascard{background:var(--bg2);border:1px solid var(--line);border-radius:13px;padding:16px 17px;cursor:default;box-shadow:0 1px 2px rgba(60,50,30,.04);transition:.14s}
.ascard:hover{box-shadow:0 5px 16px rgba(60,50,30,.08);transform:translateY(-1px);border-color:#ddd4c4}
.ascard .sv{font-size:25px;font-weight:700;line-height:1.05;color:var(--ink);letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.ascard .sl{font-size:11px;color:var(--mut);margin-top:6px;font-weight:500}
/* section card */
.acard{background:var(--bg2);border:1px solid var(--line);border-radius:14px;margin-bottom:20px;overflow:hidden;box-shadow:0 1px 3px rgba(60,50,30,.05)}
.acard-hd{display:flex;align-items:center;gap:10px;padding:15px 18px;border-bottom:1px solid var(--line);background:linear-gradient(#fdfcfa,#fbfaf7)}
.acard-hd h3{font-size:14.5px;font-weight:600;flex:1;letter-spacing:-.01em}
.acard-body{padding:18px}
/* table */
.atable{width:100%;border-collapse:collapse;font-size:12.5px}
.atable th.ac{text-align:left;padding:10px 12px;border-bottom:1.5px solid var(--line);color:var(--mut);font-weight:600;font-size:10.5px;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap;background:transparent}
.atable td.ac{padding:11px 12px;border-bottom:1px solid var(--line);vertical-align:middle;max-width:220px;word-break:break-word}
.atable tr:last-child td.ac{border-bottom:none}
.atable tbody tr{transition:background .1s}
.atable tbody tr:hover td.ac{background:rgba(169,127,44,.05)}
.atscroll{overflow-x:auto}
/* see vestra_order_items_cell(): the max-width has to sit on a block inside the cell,
   because a <td> under auto table layout ignores it entirely */
.itemscell{overflow-wrap:anywhere;line-height:1.4}
.itemsline b{color:var(--ink);font-weight:600}
.itemsmore{color:var(--mut);font-size:10px;margin-top:2px}
/* buttons */
.abtn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink);font-size:12px;cursor:pointer;white-space:nowrap;font-family:inherit;transition:.12s;text-decoration:none;font-weight:500}
.abtn:hover{border-color:var(--acc);color:var(--acc);background:rgba(169,127,44,.05)}
.abtn.primary{background:var(--acc);color:#fff;border-color:var(--acc);font-weight:600;box-shadow:0 1px 2px rgba(169,127,44,.25)}
.abtn.primary:hover{filter:brightness(1.07);background:var(--acc);color:#fff}
/* forms */
.aform{display:flex;flex-direction:column;gap:12px}
.afield label{display:block;font-size:11px;color:var(--mut);margin-bottom:4px}
.afield input,.afield select,.afield textarea{width:100%;padding:6px 10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink);font-size:13px;font-family:inherit}
.afield textarea{resize:vertical;min-height:60px}
/* Every input in the admin is on the light theme — bare inputs not wrapped in
   .afield (e.g. the listing-edit "Price tiers" boxes) otherwise fall back to the
   dark site default (#0c0c0f) and render black-on-black. */
.amain input:not([type=checkbox]):not([type=radio]),.amain select,.amain textarea{background:var(--bg);color:var(--ink);border:1px solid var(--line);border-radius:8px}
.amain input:focus,.amain select:focus,.amain textarea:focus{outline:none;border-color:var(--acc)}
/* price editor — bare inputs in table cells need the admin light theme (they are not inside .afield) */
.pricetable input,.pricetable select{border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12px;font-family:inherit}
.pricetable input:focus,.pricetable select:focus{outline:none;border-color:var(--acc)}
.pricetable td{vertical-align:middle}
/* misc */
.amsg{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px}
.amsg.ok{background:rgba(122,214,160,.1);border:1px solid rgba(122,214,160,.3);color:#1f9d63}
.aempty{color:var(--mut);padding:36px;text-align:center;font-size:14px}
.atag{font-family:monospace;font-size:11px;background:var(--bg);border:1px solid var(--line);padding:2px 6px;border-radius:4px}
.cdots{display:inline-flex;align-items:center;gap:4px;flex-wrap:wrap}
.cdots .cdot{width:13px;height:13px;border-radius:50%;display:inline-block}
.cdots .cmore{font-size:10px;color:var(--mut);font-weight:600}
.ahint{font-size:11px;color:var(--mut);margin-top:3px}
.acols2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.acols3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px}
.loginwrap{display:flex;align-items:center;justify-content:center;min-height:100vh}
.loginbox{width:380px;background:var(--bg2);border:1px solid var(--line);border-radius:18px;padding:36px}
/* doc status colors */
.doc-uploaded{background:rgba(201,168,106,.1);border-left:3px solid #9a7320}
.doc-approved{background:rgba(122,214,160,.08);border-left:3px solid #1f9d63}
.doc-rejected{background:rgba(239,154,154,.08);border-left:3px solid #c0392b}
.doc-requested{background:rgba(138,180,248,.08);border-left:3px solid #3366cc}
/* Mobile: sidebar becomes a horizontal, scrollable tab strip instead of disappearing */
@media(max-width:900px){
  :root{--sb:0px}
  .alayout{display:block}
  /* Wrap every tab onto the screen instead of a single off-screen scroll row, so
     nothing (Listings, Journal, …) hides past the right edge. Group labels break
     to a new row and keep the tabs organised. */
  .asidebar{position:static;height:auto;display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;gap:4px 5px;padding:10px 12px;border-right:0;border-bottom:1px solid var(--line)}
  .asidebar a{border-left:0;border-bottom:2px solid transparent;padding:7px 11px;border:1px solid var(--line);border-radius:8px}
  .asidebar a.on{border-color:var(--acc);background:rgba(168,127,44,.1)}
  .asidebar .sgrp{flex-basis:100%;padding:8px 2px 0;margin:2px 0 0}
  .amain{padding:16px;overflow-x:hidden;min-width:0}
  /* Stat grids are forced to 4 columns inline on some tabs — that overflows a
     phone; wrap them to 2 columns and stop any element widening the page (which
     would push the wrapped sidebar tabs off the right edge). */
  .asgrid{grid-template-columns:repeat(2,1fr)!important}
  .acols2,.acols3{grid-template-columns:1fr}
  .abtn{white-space:normal;text-align:left}
  /* Top bar: wrap the shortcut/utility links onto their own row and drop the
     "Admin Panel" label so the 🏷️ Listings / 💶 Prices shortcuts always fit. */
  .atopbar{flex-wrap:wrap;padding:8px 12px;gap:6px 8px}
  .atopbar-sub{display:none}
  .atopbar-links{margin-left:auto;flex-wrap:wrap;gap:6px}
  .atopbar .abtn{white-space:nowrap;text-align:center;font-size:12px;padding:6px 10px}
  html,body,.alayout{overflow-x:hidden;max-width:100%}
}
</style></head><body>

<?php if($locked): ?>
<div class="loginwrap"><div class="loginbox">
  <h2 style="margin-bottom:6px">Admin locked</h2>
  <p style="color:var(--mut);font-size:13px;margin-bottom:20px">Set <code>admin_pass</code> in <code>inc/config.php</code>.</p>
  <a class="abtn primary" href="/" style="justify-content:center;width:100%">← Back to site</a>
</div></div>

<?php elseif(!$authed): ?>
<div class="loginwrap"><div class="loginbox">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
    <svg viewBox="0 0 32 32" fill="none" width="30" height="30">
      <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="#9a7320" stroke-width="1.4"/>
      <path d="M9 10l7 13 7-13" stroke="#9a7320" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div><div style="font-weight:700;font-size:16px">VESTRA</div><div style="font-size:11px;color:var(--mut)">Admin Panel</div></div>
  </div>
  <?php if($err): ?><div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#c0392b">Wrong password.</div><?php endif; ?>
  <form method="post" class="aform">
    <div class="afield"><label>Admin password</label><input type="password" name="pass" autofocus required autocomplete="current-password" placeholder="••••••••"></div>
    <button class="abtn primary" type="submit" style="justify-content:center;padding:9px">Sign in</button>
  </form>
</div></div>

<?php else:
  $tab       = $_GET['tab'] ?? 'overview';
  $msg       = $_GET['msg'] ?? '';
  $filterUid = $_GET['uid'] ?? '';

  $accounts  = auth_accounts();
  $listings  = vestra_listings();
  $orders    = vestra_read_csv('orders.csv');
  $offers    = vestra_read_csv('offers.csv');
  $requests  = vestra_read_csv('requests.csv');
  $signups   = vestra_read_csv('signups.csv');
  $orderSt   = vestra_read_json('order_statuses.json');
  $offerResp = vestra_read_json('offer_responses.json');
  $promos    = promo_all();
  $vouchers  = voucher_all();

  $sellers      = array_filter($accounts,fn($a)=>($a['type']??'')==='seller');
  $buyers       = array_filter($accounts,fn($a)=>($a['type']??'')==='buyer');
  $pendingEmail = array_filter($accounts,fn($a)=>($a['status']??'')==='pending_email');
  $pendingKyb   = array_filter($accounts,fn($a)=>($a['status']??'')==='pending'&&($a['kyb_status']??'pending')==='pending');
  $reqOffers    = vestra_read_csv('request_offers.csv');
  require_once __DIR__.'/inc/messages.php';
  $msgThreads   = vestra_msg_threads();
  $blockedMsgs  = vestra_msg_blocked_log();
  $groupPools   = vestra_group_pools();
  $leads        = vestra_leads();
  $leadTpl      = vestra_lead_template();
  $journalAll   = vestra_journal_all();
  $pendingList  = array_filter($listings,fn($p)=>($p['status']??'approved')==='pending');
  $pendingOffers= array_filter($offers,fn($o)=>empty($offerResp[$o['ref']??'']));
  $totalRevenue = array_sum(array_column($orders,'total'));

  // Invoice approvals — bank-transfer orders still awaiting a manually issued invoice.
  // (Auto-invoicing is suspended: the operator confirms stock, then approves each one.)
  require_once __DIR__.'/inc/invoice.php';
  $pendingInvoiceOrders = array_values(array_filter($orders, function($o){
      $ref = (string)($o['ref'] ?? ''); if($ref==='') return false;
      if (str_contains((string)($o['notes'] ?? ''), 'Secure escrow')) return false; // card/escrow invoices itself on payment
      return count(vestra_invoices_for_ref($ref)) === 0;
  }));
  $pendingInvoiceCount = count($pendingInvoiceOrders);

  // Escrow (Treuhand) at-a-glance — held funds + lifecycle counts for the dashboard.
  require_once __DIR__.'/inc/escrow.php';
  $escrowAll   = escrow_all();
  $escHeld     = array_filter($escrowAll, fn($e)=>($e['status']??'')==='held');
  $escHeldSum  = array_sum(array_map(fn($e)=>(float)($e['total']??0), $escHeld));

  // Sample orders (direct-charge only) awaiting release — mirrors escrow above.
  $splAll      = samples_all();
  $splHeld     = array_filter($splAll, fn($s)=>($s['status']??'')==='paid' && !empty($s['acct_id']));
  $splHeldSum  = array_sum(array_map(fn($s)=>(float)($s['payout']??0), $splHeld));
  $escReleased = count(array_filter($escrowAll, fn($e)=>($e['status']??'')==='released'));
  $escRefunded = count(array_filter($escrowAll, fn($e)=>($e['status']??'')==='refunded'));
  // Membership + Connect readiness across sellers.
  $memActive    = count(array_filter($sellers, fn($a)=>in_array($a['membership_status']??'', ['active','trialing'], true)));
  $connectReady = count(array_filter($sellers, fn($a)=>!empty($a['escrow_ready'])));
  // Commission health — used by the dashboard action center AND the stat cards below.
  $comAll       = vestra_commissions();
  $comCharged   = array_sum(array_map(fn($c)=>($c['status']??'')==='charged'?(float)($c['amount']??0):0, $comAll));
  $comFailed    = count(array_filter($comAll, fn($c)=>in_array($c['status']??'', ['failed','no_card'], true)));

  // Accounts with pending document uploads
  $pendingDocs  = count(array_filter($accounts, fn($a)=>count(array_filter($a['doc_requests']??[],fn($r)=>$r['status']==='uploaded'))>0));

  $msgs=[
    'approved'=>'✓ Listing approved and live.','rejected'=>'Listing rejected.','kyb_ok'=>'KYB approved.',
    'suspended'=>'Account suspended.','activated'=>'Account activated.','deleted'=>'Listing deleted.',
    'acct_deleted'=>'✓ Account permanently deleted (backup saved; their listings were removed too).',
    'acct_has_orders'=>'⚠ Not deleted — this seller still has open orders. Complete or cancel them first, or suspend the account instead.',
    'acct_notfound'=>'⚠ Account not found — nothing was deleted.',
    'member_set'=>'✓ Membership plan updated.',
    'journal_saved'=>'✓ Article saved.','journal_deleted'=>'Article deleted.','journal_toggled'=>'Article visibility changed.',
    'listing_saved'=>'✓ Listing updated.','prices_saved'=>'✓ Prices & MOQ saved — live on the catalogue now.',
    'status_ok'=>'Order status updated.','promo_ok'=>'Promo code created.','promo_del'=>'Promo code deleted.',
    'invoice_issued'=>'✓ Invoice issued and emailed to the buyer.','invoice_none'=>'No invoice could be issued for that order.',
    'esc_released'=>'✓ Escrow released — funds paid out to the seller.','esc_refunded'=>'✓ Buyer refunded in full — sale cancelled.','esc_err'=>'⚠ Escrow action failed — see server log for details.',
    'promo_toggled'=>'Promo code status changed.',
    'voucher_ok'=>'✓ Voucher created.','voucher_del'=>'Voucher deleted.','voucher_toggled'=>'Voucher status changed.',
    'welcome_dry'=>'Preview only — nothing was created or sent. See the list below.',
    'welcome_sent'=>'✓ Welcome vouchers issued and emailed. See the result below.',
    'doc_requested'=>'Document requested.','doc_reviewed'=>'Document reviewed.',
    'verify_resent'=>'Verification email resent.','manual_verified'=>'Email verified manually.',
    'badge_granted'=>'✓ Verified Seller badge granted.','badge_revoked'=>'Badge revoked.',
    'csrf_fail'=>'⚠ Security check failed — please retry the action from this page.',
    'lead_added'=>'✓ Prospect added.','lead_dupe'=>'That email is already on the list.',
    'lead_invalid'=>'Company and a valid email are required.','lead_status_ok'=>'Prospect status updated.',
    'lead_deleted'=>'Prospect deleted.','lead_tpl_ok'=>'✓ Outreach template saved.','lead_email_ok'=>'✓ Email added — prospect can now be emailed.',
    'quote_sent'=>'✓ Offer emailed to the customer.','quote_invalid'=>'Enter a valid customer email and pick at least one product.',
    'quote_failed'=>'Offer could not be sent — set up your Sending email below (SMTP) first.','quote_unsub'=>'That contact has unsubscribed — offer not sent.',
    'email_saved'=>'✓ Sending email saved. Send yourself a test to confirm it works.','test_ok'=>'✓ Test email sent — check that inbox.','test_fail'=>'Test failed — check the SMTP host/username/password (or use an API key).','test_invalid'=>'Enter a valid email address to send the test to.',
    'quote_nosender'=>'That seller has no sending email yet — set it up in "Configure sending for" above, then retry.',
    'finder_saved'=>'✓ Email-finder key saved.','finder_ok'=>'✓ Verified email found and added.','finder_none'=>'No email found for that domain — add it manually.',
    'ai_saved'=>'✓ AI personalisation key saved.',
    'replied'=>'✓ Reply sent.','msg_err'=>'⚠ Could not start that conversation — try again.',
    'email_set'=>'✓ Email updated.','email_invalid'=>'⚠ Enter a valid email address (or leave it blank).','email_dupe'=>'⚠ Another account already uses that email.',
    'del_ok'=>'✓ Account deleted. A JSON backup was written to data/deleted-accounts/.',
    'del_hasorders'=>'⚠ Not deleted — this account has orders or invoices. Deleting it would orphan those records and break the accounting trail; archive the orders first.',
    'del_notfound'=>'⚠ Not deleted — account not found (already removed?).',
    'del_none'=>'⚠ Not deleted — no account selected.',
  ];

  /* Consistent 16px line icons per tab — replaces the mismatched emoji so the
     sidebar reads as one clean set (colour inherited via currentColor). */
  function adminIcon(string $key): string {
    $p = [
      'overview'   => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.6"/>',
      'approvals'  => '<path d="M12 3.5 21 19.5H3z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
      'documents'  => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/>',
      'users'      => '<circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.3a3 3 0 0 1 0 5.4"/><path d="M17.6 20a5.5 5.5 0 0 0-2.3-4.4"/>',
      'orders'     => '<path d="M3 7.5 12 3l9 4.5-9 4.5z"/><path d="M3 7.5v9l9 4.5 9-4.5v-9"/><path d="M12 12v9"/>',
      'offers'     => '<path d="M4 5h16v11H9l-4 3.5V5z"/>',
      'requests'   => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9z"/><path d="M8.5 11h7M8.5 15h4.5"/>',
      'req_offers' => '<path d="M3 12.5h5l1.5 3h5l1.5-3h5"/><path d="M3 12.5V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6.5"/>',
      'groups'     => '<circle cx="8" cy="9" r="2.5"/><circle cx="16" cy="9" r="2.5"/><path d="M3 19a5 5 0 0 1 10 0"/><path d="M13.5 19a5 5 0 0 1 7.5-4.3"/>',
      'messages'   => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3 7 9 6 9-6"/>',
      'notify'     => '<path d="M6 9a6 6 0 0 1 12 0c0 4.5 2 5.5 2 5.5H4S6 13.5 6 9z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
      'prices'     => '<path d="M20.5 12.5 12 21l-9-9V4h8z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
      'listings'   => '<circle cx="4" cy="6" r="1.3"/><circle cx="4" cy="12" r="1.3"/><circle cx="4" cy="18" r="1.3"/><path d="M8.5 6H21M8.5 12H21M8.5 18H21"/>',
      'journal'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h6M7 13h6M7 16h4"/><path d="M16 9h2v7h-2z"/>',
      'marketing'  => '<path d="M4 10v4h3l8 4V6l-8 4z"/><path d="M18 9.5a3.5 3.5 0 0 1 0 5"/>',
      'prospects'  => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 11.4v1.2"/>',
      'waitlist'   => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2"/>',
    ][$key] ?? '<circle cx="12" cy="12" r="2.5"/>';
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.$p.'</svg>';
  }
  function navLink(string $cur, string $key, string $icon, string $label, int $badge=0, bool $red=false): string {
    $on = $cur===$key?' on':'';
    $b = $badge>0?'<span class="aside-badge'.($red?' red':'').'">'.$badge.'</span>':'';
    return '<a href="/admin?tab='.htmlspecialchars($key).'" class="'.$on.'">'.adminIcon($key).'<span class="alabel">'.htmlspecialchars($label).'</span>'.$b.'</a>';
  }
?>

<div class="alayout">
<!-- TOP BAR -->
<div class="atopbar">
  <a href="/admin" class="logo">
    <svg viewBox="0 0 32 32" fill="none" width="26" height="26">
      <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="#9a7320" stroke-width="1.4"/>
      <path d="M9 10l7 13 7-13" stroke="#9a7320" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    VESTRA
  </a>
  <span class="atopbar-sub" style="color:var(--mut);font-size:12px">Admin Panel</span>
  <div class="atopbar-links">
    <a class="abtn primary" href="/admin?tab=listings">🏷️ Listings</a>
    <a class="abtn" href="/admin?tab=prices">💶 Prices</a>
    <a class="abtn" href="/" target="_blank">View site</a>
    <a class="abtn" href="/seller-invite" target="_blank">Invite page</a>
    <a class="abtn" href="/admin?logout=1">Sign out</a>
  </div>
</div>

<!-- SIDEBAR -->
<nav class="asidebar">
  <div class="sgrp">Main</div>
  <?= navLink($tab,'overview','📊','Dashboard') ?>
  <?= navLink($tab,'approvals','⚠️','Approvals',count($pendingList),true) ?>
  <?= navLink($tab,'documents','📄','Documents',$pendingDocs,$pendingDocs>0) ?>

  <div class="sgrp">Customers &amp; outreach</div>
  <?= navLink($tab,'prospects','🎯','Customers ('.count($leads).')') ?>
  <?= navLink($tab,'messages','✉️','Messages ('.count($msgThreads).')',count($blockedMsgs),count($blockedMsgs)>0) ?>
  <?= navLink($tab,'users','👥','Users ('.count($accounts).')',count($pendingEmail),count($pendingEmail)>0) ?>
  <?= navLink($tab,'waitlist','📩','Waitlist ('.count($signups).')') ?>

  <div class="sgrp">Sales</div>
  <?= navLink($tab,'orders','📦','Orders ('.count($orders).')') ?>
  <?= navLink($tab,'invoices','🧾','Invoice approvals',$pendingInvoiceCount,$pendingInvoiceCount>0) ?>
  <?= navLink($tab,'offers','💬','Offers ('.count($offers).')') ?>
  <?= navLink($tab,'requests','📋','Requests ('.count($requests).')') ?>
  <?= navLink($tab,'req_offers','📩','Request Offers ('.count($reqOffers).')') ?>
  <?= navLink($tab,'groups','👥','Group buys ('.count($groupPools).')') ?>

  <div class="sgrp">Catalog</div>
  <?= navLink($tab,'listings','🏷️','Listings ('.count($listings).')') ?>
  <?= navLink($tab,'prices','💶','Prices &amp; MOQ') ?>

  <div class="sgrp">Growth</div>
  <?= navLink($tab,'marketing','🎟️','Vouchers &amp; codes ('.(count($vouchers)+count($promos)).')') ?>
  <?= navLink($tab,'journal','📰','Journal ('.count($journalAll).')') ?>
  <?= navLink($tab,'notify','🔔','Notifications') ?>
</nav>

<!-- MAIN -->
<main class="amain">

<?php if($msg==='pw_reset' && !empty($_SESSION['pw_reset_flash'])):
  $flash=$_SESSION['pw_reset_flash']; unset($_SESSION['pw_reset_flash']); // show once ?>
<div class="amsg ok" style="background:rgba(201,168,106,.1);border:1px solid rgba(201,168,106,.4)">
  🔑 New password for <b><?= htmlspecialchars($flash['email']) ?></b>:
  <code style="font-size:15px;background:#faf7f1;padding:3px 10px;border-radius:6px;color:#8a6420;border:1px solid var(--line);user-select:all"><?= htmlspecialchars($flash['pw']) ?></code>
  &nbsp;— copy it now and send it to them (WhatsApp / phone). It won't be shown again; they can change it after signing in.
</div>
<?php elseif($msg && isset($msgs[$msg])): ?>
<div class="amsg ok"><?= htmlspecialchars($msgs[$msg]) ?></div>
<?php elseif($msg==='bulk_moq'): ?>
<div class="amsg ok">✓ MOQ set to 20 on <?= (int)($_GET['n']??0) ?> listing(s). Lacoste / Ralph Lauren / Amiri were left unchanged.</div>
<?php elseif($msg==='lead_bulk_deleted'): ?>
<div class="amsg ok">✓ <?= (int)($_GET['n']??0) ?> prospect(s) deleted.</div>
<?php elseif($msg==='rebrand'): ?>
<div class="amsg ok">✓ Rebranded <?= (int)($_GET['n']??0) ?> listing(s) to “Tyrex International BV” — the seller name is hidden on the public catalogue.</div>
<?php elseif($msg==='pricing_rules'): ?>
<div class="amsg ok">✓ Pricing rules applied to <?= (int)($_GET['n']??0) ?> listing(s): offers → fixed prices · Amiri polos €40/MOQ 50 · other polos €70 · T-shirts €49.90 (sale, −29%) · MOQ 20 on the rest. Lacoste &amp; Ralph Lauren left untouched.</div>
<?php elseif($msg==='tyrex_ok'): $tf=$_SESSION['tyrex_flash']??null; if($tf) unset($_SESSION['tyrex_flash']); ?>
<div class="amsg ok">✓ <b>Tyrex International BV</b> (Elite · verified) is ready — <?= (int)($_GET['n']??0) ?> listing(s) now belong to it.
  <?php if($tf): ?><br>Login e-mail: <b><?= htmlspecialchars($tf['email']) ?></b> · temporary password:
  <code style="font-size:15px;background:#faf7f1;padding:3px 10px;border-radius:6px;color:#8a6420;border:1px solid var(--line);user-select:all"><?= htmlspecialchars($tf['pw']) ?></code>
  — copy it now, it's shown only once (change it later under the account).<?php endif; ?></div>
<?php elseif($msg==='tyrex_bademail'): ?>
<div class="amsg" style="background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.35);color:#c0392b">⚠ Enter a valid login e-mail for the Tyrex account.</div>
<?php elseif($msg==='tyrex_emailtaken'): ?>
<div class="amsg" style="background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.35);color:#c0392b">⚠ That e-mail already belongs to another account — use a different one for Tyrex.</div>
<?php elseif($msg==='lead_import'): ?>
<div class="amsg ok">✓ Imported <?= (int)($_GET['added']??0) ?> prospect(s)<?= ($_GET['skipped']??0) ? ', skipped '.(int)$_GET['skipped'].' (duplicate or invalid)' : '' ?>.</div>
<?php elseif($msg==='lead_sent'): ?>
<div class="amsg ok">✓ Sent to <?= (int)($_GET['n']??0) ?> prospect(s).</div>
<?php elseif($msg==='dupfix'): ?>
<div class="amsg ok">✓ Repaired <?= (int)($_GET['n']??0) ?> duplicate order ref(s) — every order now has its own independent status.</div>
<?php elseif($msg==='push_sent'): ?>
<div class="amsg ok">🔔 Notification sent — reached <?= (int)($_GET['n']??0) ?> subscribed user(s). Users without push enabled don't count here.</div>
<?php elseif($msg==='push_err'): ?>
<div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#c0392b">⚠ Title and message are required.</div>
<?php elseif($msg==='journal_seeded'): ?>
<div class="amsg ok">✓ Loaded <?= (int)($_GET['n']??0) ?> starter article(s)<?= ((int)($_GET['n']??0)===0)?' — they were already present':'' ?>. Edit or unpublish them any time below.</div>
<?php elseif($msg==='journal_photos_dry' || $msg==='journal_photos_done'):
  $jr = $_SESSION['journal_photo_report'] ?? null; unset($_SESSION['journal_photo_report']);
  $jdry = ($msg==='journal_photos_dry'); ?>
<div class="amsg <?= ($jr && !$jdry && (int)$jr['saved']>0) ? 'ok' : '' ?>" style="<?= ($jr && !$jdry && (int)$jr['saved']>0) ? '' : 'background:rgba(201,168,106,.08);border:1px solid rgba(201,168,106,.3)' ?>">
  <?php if(!$jr): ?>No report available.
  <?php else: ?>
    <b><?= $jdry ? 'Preview' : '✓ Downloaded' ?>:</b>
    <?= (int)$jr['examined'] ?> file(s) examined,
    <?= $jdry ? count($jr['files']).' usable' : (int)$jr['saved'].' saved to uploads/journal/' ?>.
    <?php if(!empty($jr['skipped'])): ?>
      <div class="ahint" style="margin-top:6px">Rejected —
        <?php $sk=[]; foreach($jr['skipped'] as $why=>$cnt) $sk[]=htmlspecialchars($why).': '.(int)$cnt; echo implode(' · ', $sk); ?>
      </div>
    <?php endif; ?>
    <?php if(!empty($jr['files'])): ?>
      <div style="margin-top:8px;max-height:230px;overflow:auto;font-size:11.5px;line-height:1.7">
        <?php foreach($jr['files'] as $f): ?>
          <div><?= htmlspecialchars($f['file']) ?> · <?= (int)$f['width'] ?>px ·
            <span style="color:var(--acc)"><?= htmlspecialchars($f['license']) ?></span> ·
            <?= htmlspecialchars($f['artist']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if(!empty($jr['errors'])): ?>
      <div class="ahint" style="margin-top:6px;color:#c0392b"><?= htmlspecialchars(implode(' · ', array_slice($jr['errors'],0,4))) ?></div>
    <?php endif; ?>
    <?php if($jdry): ?><div class="ahint" style="margin-top:6px">Nothing was written. Use “Fetch editorial photos” to download these.</div><?php endif; ?>
  <?php endif; ?>
</div>
<?php elseif($msg==='lgp_sync'): $lgpN=(int)($_GET['n']??0); $lgpU=(int)($_GET['upd']??0); ?>
<div class="amsg ok">✓ Les Garage Paris:
  <?= $lgpN>0 ? $lgpN.' listing(s) added' : '' ?><?= ($lgpN>0 && $lgpU>0)?' · ':'' ?><?= $lgpU>0 ? $lgpU.' existing listing(s) refreshed' : '' ?><?= ($lgpN===0 && $lgpU===0) ? 'nothing to do — already up to date.' : '.' ?></div>
<?php elseif($msg==='tyx_sync'): $tyxNn=(int)($_GET['n']??0); $tyxUu=(int)($_GET['upd']??0); ?>
<div class="amsg ok">✓ Tyrex International BV:
  <?= $tyxNn>0 ? $tyxNn.' listing(s) added' : '' ?><?= ($tyxNn>0 && $tyxUu>0)?' · ':'' ?><?= $tyxUu>0 ? $tyxUu.' existing listing(s) refreshed' : '' ?><?= ($tyxNn===0 && $tyxUu===0) ? 'nothing to do — already up to date.' : '.' ?></div>
<?php elseif($msg==='tyrex_missing'): ?>
<div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#c0392b">⚠ Tyrex International BV account not found — create it first with "Create Tyrex Elite &amp; migrate" above.</div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ OVERVIEW
if($tab==='overview'): ?>

<style>
.attn-grid{display:grid;gap:8px}
.attn-row{display:flex;align-items:center;gap:14px;padding:11px 14px;border:1px solid var(--line);border-radius:10px;text-decoration:none;color:var(--ink);background:var(--bg);transition:.15s}
.attn-row:hover{border-color:var(--acc);transform:translateX(2px)}
.attn-n{font-size:20px;font-weight:800;min-width:34px;text-align:center}
.attn-lbl{flex:1;font-size:14px;font-weight:600}
.attn-cta{font-size:12.5px;color:var(--acc);font-weight:600;white-space:nowrap}
@media(max-width:600px){.attn-lbl{font-size:13px}.attn-row{gap:10px;padding:10px}}
</style>
<?php
/* Action center — every pending task in one place, each a one-tap jump. Only
   non-empty rows appear; when nothing is pending the admin sees an all-clear. */
$att = [];
if($pendingList)   $att[] = ['#c0392b','⚠️','Listings to approve', count($pendingList), '/admin?tab=approvals','Review'];
if($pendingDocs)   $att[] = ['#9a7320','📄','Documents to review', $pendingDocs, '/admin?tab=documents','Open'];
if($pendingKyb)    $att[] = ['#a9781a','⏳','Seller / buyer verifications (KYB)', count($pendingKyb), '/admin?tab=users','Review'];
if($pendingOffers) $att[] = ['#a9781a','💬','Offers awaiting a response', count($pendingOffers), '/admin?tab=offers','Open'];
if($escHeld)       $att[] = ['#1f9d63','🛡️','Escrow to release ('.eur($escHeldSum).')', count($escHeld), '/admin?tab=orders','Manage'];
if($comFailed)     $att[] = ['#c0392b','💳','Commission charges to fix', $comFailed, '/admin?tab=orders','Fix'];
if($pendingEmail)  $att[] = ['#3366cc','✉️','Accounts with unverified email', count($pendingEmail), '/admin?tab=users','View'];
?>
<div class="acard" style="margin-bottom:18px;border-color:<?= $att?'rgba(240,192,96,.4)':'rgba(122,214,160,.35)' ?>">
  <div class="acard-hd"><h3><?= $att?'🔔 Needs your attention':'✓ You’re all caught up' ?></h3><?php if($att): ?><span class="ahint"><?= count($att) ?> to handle</span><?php endif; ?></div>
  <div class="acard-body">
  <?php if(!$att): ?>
    <div class="ahint" style="padding:6px 2px">Nothing is waiting on you — no listings to approve, no documents or verifications to review, no offers or escrow pending. 🎉</div>
  <?php else: ?>
    <div class="attn-grid">
    <?php foreach($att as $it): ?>
      <a class="attn-row" href="<?= $it[4] ?>">
        <span class="attn-n" style="color:<?= $it[0] ?>"><?= (int)$it[3] ?></span>
        <span class="attn-lbl"><?= $it[1] ?> <?= htmlspecialchars($it[2]) ?></span>
        <span class="attn-cta"><?= htmlspecialchars($it[5]) ?> →</span>
      </a>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__.'/inc/stripe.php'; if(!stripe_configured()): ?>
<div class="amsg" style="background:rgba(240,192,96,.08);border:1px solid rgba(240,192,96,.3);color:#a9781a">
  ⚠ Stripe is not configured — seller membership checkout is disabled.
  Missing keys: <code style="font-size:11px"><?= htmlspecialchars(implode(', ', stripe_missing_keys())) ?></code>.
  Copy <code>.env.example</code> to a <code>.env</code> file one level above the document root and fill in the values.
</div>
<?php endif; ?>

<div class="asgrid">
  <div class="ascard"><div class="sv"><?= count($accounts) ?></div><div class="sl">Total accounts</div></div>
  <div class="ascard"><div class="sv" style="color:#9a7320"><?= count($sellers) ?></div><div class="sl">Sellers</div></div>
  <div class="ascard"><div class="sv" style="color:#3366cc"><?= count($buyers) ?></div><div class="sl">Buyers</div></div>
  <div class="ascard"><div class="sv" style="color:#c0392b"><?= count($pendingEmail) ?></div><div class="sl">Email unverified</div></div>
  <div class="ascard"><div class="sv" style="color:#a9781a"><?= count($pendingKyb) ?></div><div class="sl">Pending KYB</div></div>
  <div class="ascard"><div class="sv" style="color:#c0392b"><?= count($pendingList) ?></div><div class="sl">Pending listings</div></div>
  <div class="ascard"><div class="sv"><?= count($orders) ?></div><div class="sl">Orders</div></div>
  <div class="ascard"><div class="sv"><?= eur($totalRevenue) ?></div><div class="sl">Order volume</div></div>
  <div class="ascard"><div class="sv" style="color:#1f9d63"><?= eur($comCharged) ?></div><div class="sl">Commission collected</div></div>
  <div class="ascard"><div class="sv" style="color:<?= $comFailed?'#c0392b':'#555' ?>"><?= $comFailed ?></div><div class="sl">Commission needs attention</div></div><?php /* commission stats computed in the data-loading section above */ ?>
  <div class="ascard"><div class="sv" style="color:#a9781a"><?= count($pendingOffers) ?></div><div class="sl">Offers pending</div></div>
  <div class="ascard"><div class="sv"><?= count($signups) ?></div><div class="sl">Waitlist</div></div>
  <div class="ascard"><div class="sv" style="color:#1f9d63"><?= eur($escHeldSum) ?></div><div class="sl">🛡️ Held in escrow (<?= count($escHeld) ?>)</div></div>
  <div class="ascard"><div class="sv" style="color:#2b7fb0"><?= $escReleased ?></div><div class="sl">Escrow released</div></div>
  <div class="ascard"><div class="sv" style="color:#9a7320"><?= $connectReady ?></div><div class="sl">Connect-ready sellers</div></div>
  <div class="ascard"><div class="sv" style="color:#1f9d63"><?= $memActive ?></div><div class="sl">Active memberships</div></div>
</div>

<?php if($pendingList||$pendingKyb): ?>
<div class="acols2">
<?php if($pendingList): ?>
<div class="acard">
  <div class="acard-hd"><h3>⚠️ Listings awaiting approval (<?= count($pendingList) ?>)</h3><a class="abtn" href="/admin?tab=approvals">Review all →</a></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['Brand','Product','Seller','Date','Approve'],true) ?>
    <?php foreach(array_slice(array_reverse(array_values($pendingList)),0,5) as $p): ?>
    <tr>
      <td class="ac"><b><?= htmlspecialchars($p['brand']??'') ?></b></td>
      <td class="ac"><?= htmlspecialchars($p['name']??'') ?></td>
      <td class="ac"><?= htmlspecialchars($p['seller']??'') ?></td>
      <td class="ac" style="font-size:11px;color:var(--mut)"><?= htmlspecialchars(substr($p['submitted_at']??'',0,10)) ?></td>
      <td class="ac"><form method="post" style="margin:0" onsubmit="return confirm('Approve this listing and make it live now?')"><?= csrfField() ?><input type="hidden" name="_action" value="approve_listing"><input type="hidden" name="lid" value="<?= htmlspecialchars($p['id']??'') ?>"><button class="abtn primary" type="submit" style="font-size:11px;padding:3px 9px" title="Approve — go live now">✓ Approve</button></form></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
<?php if($pendingKyb): ?>
<div class="acard">
  <div class="acard-hd"><h3>⏳ KYB Queue (<?= count($pendingKyb) ?>)</h3><a class="abtn" href="/admin?tab=users">View all →</a></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['Name','Type','Company','Date'],true) ?>
    <?php foreach(array_slice(array_reverse(array_values($pendingKyb)),0,5) as $a): ?>
    <?= arow([htmlspecialchars($a['name']??'—'),typePill($a['type']??''),htmlspecialchars($a['company']??'—'),htmlspecialchars(substr($a['created']??'',0,10))]) ?>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if($escHeld): ?>
<div class="acard" style="margin-bottom:18px">
  <div class="acard-hd"><h3>🛡️ Funds held in escrow (<?= count($escHeld) ?> · <?= eur($escHeldSum) ?>)</h3><a class="abtn" href="/admin?tab=orders">All orders →</a></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['Ref','Buyer','Seller','Total','Paid','Action'],true) ?>
    <?php foreach(array_slice(array_reverse(array_values($escHeld)),0,8) as $e):
      $sName=''; foreach($accounts as $sa){ if(($sa['id']??'')===($e['seller_uid']??'')){ $sName=$sa['company']?:($sa['name']??''); break; } }
      $ref=$e['ref']??''; ?>
    <tr>
      <td><span class="atag"><?= htmlspecialchars(substr($ref,0,12)) ?></span></td>
      <td><?= htmlspecialchars($e['buyer']['company']??($e['buyer']['name']??($e['buyer']['email']??'—'))) ?></td>
      <td><?= htmlspecialchars($sName?:'—') ?></td>
      <td><b><?= eur($e['total']??0) ?></b></td>
      <td class="ahint"><?= htmlspecialchars(substr($e['paid_at']??'',0,10)) ?></td>
      <td><div style="display:flex;gap:5px">
        <form method="post" onsubmit="return confirm('Release the held funds to the seller?')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_release"><input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>"><button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#1f9d63">Release</button></form>
        <form method="post" onsubmit="return confirm('Refund the buyer in full? This cancels the sale.')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_refund"><input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>"><button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#c0392b">Refund</button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<?php if($splHeld): ?>
<div class="acard" style="margin-bottom:18px">
  <div class="acard-hd"><h3>📦 Sample payouts held (<?= count($splHeld) ?> · <?= eur($splHeldSum) ?>)</h3></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['Ref','Buyer','Seller','Payout','Paid','Action'],true) ?>
    <?php foreach(array_slice(array_reverse(array_values($splHeld)),0,8) as $s):
      $sName=''; foreach($accounts as $sa){ if(($sa['id']??'')===($s['seller_uid']??'')){ $sName=$sa['company']?:($sa['name']??''); break; } }
      $sref=$s['ref']??''; ?>
    <tr>
      <td><span class="atag"><?= htmlspecialchars(substr($sref,0,12)) ?></span></td>
      <td><?= htmlspecialchars($s['buyer_company']??($s['buyer_name']??($s['buyer_email']??'—'))) ?></td>
      <td><?= htmlspecialchars($sName?:'—') ?></td>
      <td><b><?= eur($s['payout']??0) ?></b></td>
      <td class="ahint"><?= htmlspecialchars(substr($s['paid_at']??'',0,10)) ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Release the held payout to the seller?')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="sample_release"><input type="hidden" name="ref" value="<?= htmlspecialchars($sref) ?>"><button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#1f9d63">Release</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<div class="acols2">
<div class="acard">
  <div class="acard-hd"><h3>Recent registrations</h3><a class="abtn" href="/admin?tab=users">All →</a></div>
  <?php $rec=array_slice(array_reverse($accounts),0,8); if(!$rec){ echo '<div class="aempty">No accounts yet.</div>'; } else { ?>
  <div class="atscroll"><table class="atable">
    <?= arow(['Name','Type','KYB','Joined'],true) ?>
    <?php foreach($rec as $a): ?>
    <?= arow(['<b>'.htmlspecialchars($a['name']??'—').'</b><div class="ahint">'.htmlspecialchars($a['email']??'').'</div>',typePill($a['type']??''),kybBadge($a['kyb_status']??'pending'),htmlspecialchars(substr($a['created']??'',0,10))]) ?>
    <?php endforeach; ?>
  </table></div>
  <?php } ?>
</div>
<div class="acard">
  <div class="acard-hd"><h3>Recent orders</h3><a class="abtn" href="/admin?tab=orders">All →</a></div>
  <?php $rec=array_slice(array_reverse($orders),0,8); if(!$rec){ echo '<div class="aempty">No orders yet.</div>'; } else { ?>
  <div class="atscroll"><table class="atable">
    <?= arow(['Ref','Buyer','Total','Status'],true) ?>
    <?php foreach($rec as $o): $st=$orderSt[$o['ref']??'']['status']??'pending'; ?>
    <?= arow(['<span class="atag">'.htmlspecialchars(substr($o['ref']??'',0,12)).'</span>',htmlspecialchars($o['company']??$o['email']??''),'<b>'.eur($o['total']??0).'</b>',orderBadge($st)]) ?>
    <?php endforeach; ?>
  </table></div>
  <?php } ?>
</div>
</div>


<?php // ══════════════════════════════════════════════════════ APPROVALS
elseif($tab==='approvals'): ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div><h2 style="font-size:18px;font-weight:700">Listing Approvals</h2><p class="ahint" style="margin-top:4px">Review new seller listings before they go live in the catalog.</p></div>
</div>

<?php if(!$pendingList): ?>
  <div class="acard"><div class="aempty">✓ No listings pending approval.</div></div>
<?php else: foreach(array_reverse(array_values($pendingList)) as $p): ?>
<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-size:15px;font-weight:700"><?= htmlspecialchars($p['brand']??'') ?> — <?= htmlspecialchars($p['name']??'') ?></div>
      <div class="ahint" style="margin-top:3px">SKU <?= htmlspecialchars($p['sku']??'') ?> · <?= htmlspecialchars($p['cat']??'') ?> · Seller: <?= htmlspecialchars($p['seller']??'') ?></div>
    </div>
    <?= abadge('⏳ Pending','#a9781a') ?>
  </div>
  <div class="acard-body">
    <div class="acols3" style="margin-bottom:16px">
      <div><div class="ahint">Mode</div><b><?= htmlspecialchars($p['mode']??'fixed') ?></b></div>
      <div><div class="ahint">MOQ</div><b><?= htmlspecialchars((string)($p['moq']??'')) ?> <?= htmlspecialchars($p['unit']??'pc') ?></b></div>
      <div><div class="ahint">Starting price</div><b><?= ($p['mode']??'')==='offer'?'Open to offers':eur(vestra_from_price($p)) ?></b></div>
      <div><div class="ahint">Origin</div><?= htmlspecialchars($p['origin']??'—') ?></div>
      <div><div class="ahint">Description</div><?= htmlspecialchars(substr($p['desc']??'',0,80)) ?></div>
      <div><div class="ahint">Image</div><?= !empty($p['image'])?'<a class="abtn" href="'.htmlspecialchars($p['image']).'" target="_blank">View photo</a>':'No photo' ?></div>
    </div>
    <div class="acols2">
      <form method="post" class="aform">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="approve_listing">
        <input type="hidden" name="lid" value="<?= htmlspecialchars($p['id']??'') ?>">
        <div class="afield"><label>Note to seller (optional)</label><textarea name="note" placeholder="Approved — listing is now live."></textarea></div>
        <button class="abtn primary" type="submit">✓ Approve — go live</button>
      </form>
      <form method="post" class="aform">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="reject_listing">
        <input type="hidden" name="lid" value="<?= htmlspecialchars($p['id']??'') ?>">
        <div class="afield"><label>Reason for rejection (required)</label><textarea name="note" placeholder="Please revise: missing origin documentation…" required></textarea></div>
        <button class="abtn" type="submit" style="color:var(--bad);border-color:rgba(239,154,154,.4)">✗ Reject</button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>


<?php // ══════════════════════════════════════════════════════ DOCUMENTS
elseif($tab==='documents'):
  $docTypes = auth_doc_types();
  // If specific user selected, show their docs
  $selUser = null;
  if($filterUid){ foreach($accounts as $a){ if($a['id']===$filterUid){ $selUser=$a; break; } } }
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div><h2 style="font-size:18px;font-weight:700">Document Management</h2><p class="ahint" style="margin-top:4px">Request, review, and approve KYB/KYC documents.</p></div>
</div>

<?php if($selUser): ?>
<!-- Single user document view -->
<div style="margin-bottom:16px">
  <a class="abtn" href="/admin?tab=documents">← All users</a>
</div>
<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($selUser['name']??'—') ?> <span style="font-weight:400;color:var(--mut)">— <?= htmlspecialchars($selUser['email']??'') ?></span></div>
      <div class="ahint" style="margin-top:3px"><?= htmlspecialchars($selUser['company']??'') ?> · <?= htmlspecialchars($selUser['country']??'') ?> · <?= htmlspecialchars($selUser['vat_id']??'') ?></div>
    </div>
    <?= typePill($selUser['type']??'buyer') ?>
    <?= kybBadge($selUser['kyb_status']??'pending') ?>
  </div>
  <div class="acard-body">
  <div class="acols2">
  <!-- Request a document -->
  <form method="post" class="aform">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="request_doc">
    <input type="hidden" name="uid" value="<?= htmlspecialchars($selUser['id']??'') ?>">
    <div style="font-weight:600;margin-bottom:10px;font-size:13px">Request additional document</div>
    <div class="afield"><label>Document type</label>
      <select name="doc_type">
        <?php foreach($docTypes as $k=>$v): ?><option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="afield"><label>Note to user (optional)</label><textarea name="note" placeholder="Please upload your official company registration certificate (PDF or image)." rows="3"></textarea></div>
    <button class="abtn primary" type="submit">📋 Send request</button>
  </form>
  <!-- KYB approve -->
  <div>
    <div style="font-weight:600;margin-bottom:10px;font-size:13px">Quick actions</div>
    <?php if(($selUser['kyb_status']??'pending')==='pending'): ?>
      <?= fBtn('✓ Approve KYB','approve_kyb',['uid'=>$selUser['id']??''],'color:var(--ok);border-color:rgba(122,214,160,.4)') ?>
    <?php endif; ?>
    <?php if(($selUser['status']??'active')==='suspended'): ?>
      <?= fBtn('Activate account','activate_account',['uid'=>$selUser['id']??'']) ?>
    <?php else: ?>
      <?= fBtn('Suspend account','suspend_account',['uid'=>$selUser['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.3)') ?>
    <?php endif; ?>
  </div>
  </div>
  </div>
</div>

<!-- Document requests list -->
<?php $docReqs=$selUser['doc_requests']??[];
if(!$docReqs): ?>
  <div class="acard"><div class="aempty">No documents requested yet for this user.</div></div>
<?php else: foreach(array_reverse($docReqs) as $req): $st=$req['status']??'requested'; ?>
<div class="acard doc-<?= htmlspecialchars($st) ?>" style="margin-bottom:12px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-weight:600"><?= htmlspecialchars($docTypes[$req['type']??'']??$req['type']??'Document') ?></div>
      <div class="ahint">Requested <?= htmlspecialchars(substr($req['requested_at']??'',0,10)) ?>
        <?php if(!empty($req['uploaded_at'])): ?> · Uploaded <?= htmlspecialchars(substr($req['uploaded_at'],0,10)) ?><?php endif; ?>
        <?php if(!empty($req['reviewed_at'])): ?> · Reviewed <?= htmlspecialchars(substr($req['reviewed_at'],0,10)) ?><?php endif; ?>
      </div>
      <?php if(!empty($req['note'])): ?><div class="ahint" style="margin-top:4px;font-style:italic">Note: <?= htmlspecialchars($req['note']) ?></div><?php endif; ?>
      <?php if(!empty($req['admin_note'])): ?><div class="ahint" style="margin-top:4px;color:#9a7320">Admin: <?= htmlspecialchars($req['admin_note']) ?></div><?php endif; ?>
    </div>
    <?= docBadge($st) ?>
  </div>
  <?php /* Belge kutusu ESKIDEN yalnizca $st==='uploaded' iken ciziliyordu: operator
           belgeyi ONAYLADIGI anda durum 'approved' oluyor ve onizleme, indirme linki,
           dosya adi -- hepsi kayboluyordu. Yani onaylanmis bir belgeye bir daha
           ulasilamiyordu. Oysa belgeye asil ihtiyac onaydan SONRA duyuluyor: faturaya
           yazilacak sirket adresi, vergi numarasi, bir ihtilafta veya denetimde kanit.
           Dosya artik her durumda gorunuyor; degisen tek sey, ONAY/RET dugmelerinin
           yalnizca 'uploaded' durumunda cikmasi -- onaylanmis bir belgeyi yanlislikla
           yeniden "onaylamak" ya da reddetmek icin bir dugme durmamali. */
     $canReview = ($st === 'uploaded');
     if(!empty($req['file'])):
    $docUrl  = '/admin?dl_doc='.urlencode($req['file']).'&uid='.urlencode($selUser['id']??'');
    $ext     = strtolower(pathinfo($req['file'],PATHINFO_EXTENSION));
    $isImg   = in_array($ext,['jpg','jpeg','png','webp'],true);
    $isPdf   = ($ext==='pdf');
    $docLabel= $docTypes[$req['type']??'']??($req['type']??'Document');
    $who     = trim(($selUser['company']??'')?:($selUser['name']??'')?:($selUser['email']??''));
    $fpath   = function_exists('auth_doc_file_path') ? auth_doc_file_path($selUser['id']??'',$req['file']) : '';
    $fsize   = ($fpath && is_readable($fpath)) ? round(filesize($fpath)/1024).' KB' : '';
    $cfJs    = htmlspecialchars(addslashes($docLabel.' — '.$who), ENT_QUOTES);
  ?>
  <div class="acard-body">
    <!-- Exactly what is being approved -->
    <div style="background:rgba(201,168,106,.07);border:1px solid rgba(201,168,106,.28);border-radius:8px;padding:11px 13px;margin-bottom:12px">
      <div style="font-size:11px;letter-spacing:.5px;text-transform:uppercase;color:var(--mut)"><?= $canReview ? 'You are approving' : 'Document on file' ?></div>
      <div style="font-weight:600;font-size:14.5px;margin-top:3px">📄 <?= htmlspecialchars($docLabel) ?></div>
      <div class="ahint" style="margin-top:3px">For account: <b><?= htmlspecialchars($who) ?></b> · <?= htmlspecialchars($selUser['id']??'') ?></div>
      <div class="ahint" style="margin-top:3px">File: <b><?= htmlspecialchars($req['file']) ?></b> · <?= strtoupper($ext)?:'FILE' ?><?= $fsize?' · '.$fsize:'' ?></div>
      <?php if(!empty($req['note'])): ?><div class="ahint" style="margin-top:5px">What was requested: <?= htmlspecialchars($req['note']) ?></div><?php endif; ?>
    </div>
    <!-- Live preview of the actual document -->
    <?php if($isImg): ?>
      <a href="<?= $docUrl ?>" target="_blank" title="Open full size"><img src="<?= $docUrl ?>" alt="Document preview" style="max-width:100%;max-height:360px;border:1px solid var(--line);border-radius:8px;display:block;margin-bottom:12px;background:#fff"></a>
    <?php elseif($isPdf): ?>
      <iframe src="<?= $docUrl ?>#view=FitH" title="Document preview" style="width:100%;height:440px;border:1px solid var(--line);border-radius:8px;background:#fff;margin-bottom:12px"></iframe>
    <?php else: ?>
      <div class="ahint" style="margin-bottom:12px">Preview not available for .<?= htmlspecialchars($ext) ?> files — open the file to review it.</div>
    <?php endif; ?>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <a class="abtn" href="<?= $docUrl ?>" target="_blank">📂 Open full file</a>
      <?php if($canReview): ?>
      <form method="post" style="display:inline-flex;gap:8px;align-items:center">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="review_doc">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($selUser['id']??'') ?>">
        <input type="hidden" name="req_id" value="<?= htmlspecialchars($req['id']??'') ?>">
        <input name="admin_note" placeholder="Optional note" style="padding:4px 8px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12px;width:170px">
        <button class="abtn" name="status" value="approved" type="submit" style="color:var(--ok);border-color:rgba(122,214,160,.4)" onclick="return confirm('Approve the <?= $cfJs ?>?')">✓ Approve this document</button>
        <button class="abtn" name="status" value="rejected" type="submit" style="color:var(--bad);border-color:rgba(239,154,154,.3)" onclick="return confirm('Reject the <?= $cfJs ?>? They will be asked to re-upload.')">✗ Reject</button>
      </form>
      <?php else: ?>
        <span class="ahint"><?= $st==='approved' ? 'Already approved — shown here for reference.' : 'Rejected — the account has been asked to re-upload.' ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>

<?php else: ?>
<!-- All users with document status -->
<div class="acard">
  <div class="acard-hd"><h3>All accounts — document status</h3></div>
  <?php if(!$accounts): ?>
    <div class="aempty">No accounts yet.</div>
  <?php else: ?>
  <div class="atscroll"><table class="atable">
    <?= arow(['Name','Email','Type','KYB','Docs','Pending docs',''],true) ?>
    <?php foreach(array_reverse($accounts) as $a):
      $dreqs=$a['doc_requests']??[];
      $uploaded=count(array_filter($dreqs,fn($r)=>$r['status']==='uploaded'));
      $approved=count(array_filter($dreqs,fn($r)=>$r['status']==='approved'));
      $total=count($dreqs);
    ?>
    <?= arow([
      '<b>'.htmlspecialchars($a['name']??'—').'</b><div class="ahint">'.htmlspecialchars($a['id']??'').'</div>',
      '<a href="mailto:'.htmlspecialchars($a['email']??'').'" style="color:var(--acc);font-size:12px">'.htmlspecialchars($a['email']??'').'</a>',
      typePill($a['type']??''),
      kybBadge(($a['status']??'active')==='suspended'?'suspended':($a['kyb_status']??'pending')),
      $total>0?"$approved/$total approved":'<span class="ahint">None</span>',
      $uploaded>0?abadge("$uploaded waiting review",'#9a7320'):'<span class="ahint">—</span>',
      '<a class="abtn" href="/admin?tab=documents&uid='.urlencode($a['id']??'').'">Manage docs →</a>',
    ]) ?>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ USERS
elseif($tab==='users'):
  $filterType = $_GET['type']??'all';
  $shown = $filterType==='seller'?array_values($sellers):($filterType==='buyer'?array_values($buyers):$accounts);
  $shown = array_reverse($shown);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px;flex-wrap:wrap">
  <h2 style="font-size:18px;font-weight:700">Users</h2>
  <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
    <input id="usearch" placeholder="🔍 Search name / email / company…" oninput="ufilter()"
      style="padding:6px 12px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink);font-size:12.5px;min-width:220px">
    <a class="abtn<?= $filterType==='all'?' primary':'' ?>" href="/admin?tab=users&type=all">All (<?= count($accounts) ?>)</a>
    <a class="abtn<?= $filterType==='seller'?' primary':'' ?>" href="/admin?tab=users&type=seller">Sellers (<?= count($sellers) ?>)</a>
    <a class="abtn<?= $filterType==='buyer'?' primary':'' ?>" href="/admin?tab=users&type=buyer">Buyers (<?= count($buyers) ?>)</a>
    <a class="abtn" href="/admin?dl=sellers" title="Download all sellers with company, email, address, VAT">⬇ Export sellers CSV</a>
  </div>
</div>
<script>
function ufilter(){
  var q=document.getElementById('usearch').value.toLowerCase();
  document.querySelectorAll('.udetail').forEach(function(r){ r.style.display='none'; });
  document.querySelectorAll('.atable tr').forEach(function(tr,i){
    if(i===0||tr.classList.contains('udetail')) return; // header + detail rows follow their parent
    tr.style.display = tr.textContent.toLowerCase().indexOf(q)>-1 ? '' : 'none';
  });
}
function utgl(id){
  var r=document.getElementById('ud-'+id), a=document.getElementById('uarr-'+id);
  if(!r) return;
  var hidden=(r.style.display==='none');
  r.style.display=hidden?'':'none';
  if(a) a.textContent=hidden?'▾':'▸';
}
function sendUserMessage(uid,name){
  var body=prompt('Message to '+name+' (delivered on-platform — they see it in their Messages tab, as from "VESTRA Support"):');
  if(body===null) return; body=body.trim(); if(!body) return;
  document.getElementById('umf_uid').value=uid;
  document.getElementById('umf_body').value=body;
  document.getElementById('userMsgForm').submit();
}
</script>
<form method="post" id="userMsgForm" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="_action" value="start_message">
  <input type="hidden" name="uid" id="umf_uid">
  <input type="hidden" name="body" id="umf_body">
</form>

<?php if(!$shown): ?>
  <div class="acard"><div class="aempty">No accounts yet.</div></div>
<?php else: ?>
<div class="acard">
<div class="atscroll"><table class="atable">
  <?= arow(['#','Name','Email','Type','Company','Country','VAT ID','Verification','KYB','Membership','Badge','Docs','Joined','Actions'],true) ?>
  <?php $i=count($shown); foreach($shown as $a):
    $isSusp=($a['status']??'active')==='suspended';
    $isPendEmail=($a['status']??'')==='pending_email';
    $dreqs=$a['doc_requests']??[];
    $docSummary=count($dreqs)>0?(count(array_filter($dreqs,fn($r)=>$r['status']==='approved')).'/'.count($dreqs)):'—';
    $uploaded=count(array_filter($dreqs,fn($r)=>$r['status']==='uploaded'));
  ?>
  <tr style="<?= $isSusp?'opacity:.45':'' ?>">
    <td class="ac" style="color:var(--mut)"><?= $i-- ?></td>
    <td class="ac" style="cursor:pointer" onclick="utgl('<?= htmlspecialchars($a['id']??'',ENT_QUOTES) ?>')" title="Click to see full address">
      <b><?= htmlspecialchars($a['name']??'—') ?></b> <span class="ahint" id="uarr-<?= htmlspecialchars($a['id']??'') ?>" style="color:var(--acc)">▸</span>
      <div class="ahint"><?= htmlspecialchars(substr($a['id']??'',0,10)) ?>…</div>
    </td>
    <td class="ac">
      <form method="post" style="display:flex;gap:3px;align-items:center;white-space:nowrap">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="set_account_email">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($a['id']??'') ?>">
        <input type="email" name="email" value="<?= htmlspecialchars($a['email']??'') ?>" placeholder="no email on file" title="Notifications (orders, offers, messages) silently fail without this" style="width:145px;padding:3px 6px;border:1px solid <?= empty($a['email'])?'#c0392b':'var(--line)' ?>;border-radius:5px;background:var(--bg);color:var(--ink);font-size:11.5px">
        <button class="abtn" type="submit" style="font-size:11px;padding:3px 7px" title="Save email">💾</button>
      </form>
      <!-- Silme, e-posta formunun DISINDA ayri bir form: ayni forma koymak
           Enter'a basinca yanlislikla silme riski yaratirdi. Onay metni sirketi
           ve e-postayi yazar, boylece yanlis satiri silmek zorlasir. -->
      <form method="post" style="margin:4px 0 0" onsubmit="return confirm('Delete this account permanently?\n\n<?= htmlspecialchars(addslashes(($a['company']?:($a['name']??'—')).' · '.($a['email']?:'no email')), ENT_QUOTES) ?>\n\nA JSON backup is kept. Accounts with orders or invoices cannot be deleted.')">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="delete_account">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($a['id']??'') ?>">
        <button class="abtn" type="submit" style="font-size:10.5px;padding:2px 7px;color:#c0392b" title="Delete this customer account">🗑 Delete</button>
      </form>
    </td>
    <td class="ac"><?= typePill($a['type']??'') ?></td>
    <td class="ac"><?= htmlspecialchars($a['company']??'—') ?></td>
    <td class="ac"><?= htmlspecialchars($a['country']??'—') ?></td>
    <td class="ac" style="font-family:monospace;font-size:11px"><?= htmlspecialchars($a['vat_id']??'—') ?></td>
    <td class="ac">
      <?php if($isPendEmail): ?>
        <?= abadge('⚠ Unverified','#c0392b') ?>
        <?php if(!empty($a['verify_sent_at'])):
          $sentOk = $a['verify_sent_ok'] ?? true;
        ?>
          <div class="ahint" style="margin-top:2px;<?= $sentOk?'':'color:#c0392b' ?>">
            <?= $sentOk?'✓ sent':'⚠ send failed' ?> <?= htmlspecialchars(date('d.m H:i',strtotime($a['verify_sent_at']))) ?>
          </div>
        <?php endif; ?>
        <div style="display:flex;gap:3px;margin-top:4px;flex-wrap:wrap">
          <?= fBtn('Resend','resend_verify',['uid'=>$a['id']??''],'font-size:11px') ?>
          <?= fBtn('Force verify','manual_verify',['uid'=>$a['id']??''],'font-size:11px;color:var(--ok);border-color:rgba(122,214,160,.4)','Force-verify email for this account?') ?>
          <button type="button" class="abtn" style="font-size:11px" title="Copy the verification link to send manually (WhatsApp, SMS…) if email delivery is unreliable"
            onclick="navigator.clipboard.writeText('https://vestrasales.com/verify?token=<?= htmlspecialchars($a['email_token']??'',ENT_QUOTES) ?>');this.textContent='✓ Copied'">🔗 Copy link</button>
        </div>
      <?php elseif(!empty($a['email_verified'])): ?>
        <?= abadge('✓ Verified','#1f9d63') ?>
        <?php if(!empty($a['ack_sent_at'])):
          $ackOk = $a['ack_sent_ok'] ?? true;
        ?>
          <div class="ahint" style="margin-top:2px;<?= $ackOk?'':'color:#c0392b' ?>" title="Next-step 'upload your documents' email">
            <?= $ackOk?'✓ next-step mail sent':'⚠ next-step mail failed' ?> <?= htmlspecialchars(date('d.m H:i',strtotime($a['ack_sent_at']))) ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <span class="ahint">—</span>
      <?php endif; ?>
    </td>
    <td class="ac"><?= kybBadge($isSusp?'suspended':($a['kyb_status']??'pending')) ?></td>
    <td class="ac">
      <?= memberBadge($a['membership_tier']??'',$a['membership_status']??'') ?>
      <form method="post" action="/admin" style="margin-top:5px;display:flex;gap:3px;align-items:center">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="set_membership">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($a['id']??'') ?>">
        <select name="tier" title="Change plan" style="padding:3px 5px;border:1px solid var(--line);border-radius:5px;background:var(--bg);color:var(--ink);font-size:11px">
          <?php $ct=$a['membership_tier']??''; foreach(['' =>'— None','starter'=>'Starter','pro'=>'Pro','premium'=>'Elite'] as $tv=>$tl): ?>
            <option value="<?= $tv ?>" <?= $ct===$tv?'selected':'' ?>><?= $tl ?></option>
          <?php endforeach; ?>
        </select>
        <button class="abtn" type="submit" style="font-size:11px" title="Apply plan">Set</button>
      </form>
    </td>
    <td class="ac">
      <?php if(($a['type']??'')==='seller' && !empty($a['onboarding_paid'])): ?>
        <?php if(!empty($a['verified_badge'])): ?>
          <?= abadge('✓ Badge','#1f9d63') ?>
          <div style="margin-top:3px"><?= fBtn('Revoke','revoke_badge',['uid'=>$a['id']??''],'font-size:11px;color:var(--bad);border-color:rgba(239,154,154,.3)','Revoke Verified Seller badge?') ?></div>
        <?php else: ?>
          <?= fBtn('Grant badge','grant_badge',['uid'=>$a['id']??''],'font-size:11px;color:var(--ok);border-color:rgba(122,214,160,.4)','Grant Verified Seller badge?') ?>
        <?php endif; ?>
      <?php else: ?>
        <span style="color:#555;font-size:11px">—</span>
      <?php endif; ?>
    </td>
    <td class="ac">
      <?= $docSummary ?>
      <?php if($uploaded>0): ?><div><?= abadge("$uploaded to review",'#9a7320') ?></div><?php endif; ?>
    </td>
    <td class="ac" style="font-size:11px;color:var(--mut)">
      <?= htmlspecialchars(substr($a['created']??'',0,10)) ?>
      <?php if(!empty($a['last_login'])): ?><div class="ahint" style="margin-top:2px">Last in: <?= htmlspecialchars(substr($a['last_login'],0,10)) ?></div><?php endif; ?>
    </td>
    <td class="ac"><div style="display:flex;gap:4px;flex-wrap:wrap">
      <a class="abtn" href="/admin?tab=documents&uid=<?= urlencode($a['id']??'') ?>">Docs</a>
      <?php if(($a['kyb_status']??'pending')==='pending'&&!$isSusp&&!$isPendEmail): echo fBtn('✓ KYB','approve_kyb',['uid'=>$a['id']??''],'color:var(--ok);border-color:rgba(122,214,160,.4)'); endif; ?>
      <?= fBtn('🔑 Reset pw','reset_password',['uid'=>$a['id']??''],'','Generate a new temporary password for '.($a['email']??'this account').'? You will see it once, to send to them.') ?>
      <button type="button" class="abtn" onclick="sendUserMessage('<?= htmlspecialchars($a['id']??'',ENT_QUOTES) ?>','<?= htmlspecialchars($a['company']??($a['name']??'this account'),ENT_QUOTES) ?>')" title="Start an on-platform message thread — reaches them even with no email on file">💬 Message</button>
      <?php if($isSusp): echo fBtn('Activate','activate_account',['uid'=>$a['id']??'']); else: echo fBtn('Suspend','suspend_account',['uid'=>$a['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.3)'); endif; ?>
      <?= fBtn('🗑 Delete','delete_account',['uid'=>$a['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.55)',
            'PERMANENTLY delete '.($a['company']?:($a['name']?:($a['email']??'this account'))).'?'."\n\n".
            'Their listings will be removed from the catalogue too. This cannot be undone (a backup of accounts.json is saved on the server). Blocked if they still have open orders — suspend instead.') ?>
    </div></td>
  </tr>
  <tr class="udetail" id="ud-<?= htmlspecialchars($a['id']??'',ENT_QUOTES) ?>" style="display:none;background:rgba(201,168,106,.06)">
    <td></td>
    <td colspan="13" style="padding:14px 18px">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;max-width:1040px">
        <div>
          <div class="ahint" style="text-transform:uppercase;font-size:10.5px;letter-spacing:.5px;margin-bottom:5px">📍 Full address</div>
          <div style="font-size:13px;line-height:1.55"><?= ($a['address']??'')!=='' ? nl2br(htmlspecialchars($a['address'])) : '<span class="ahint">— none on file —</span>' ?><?php if(!empty($a['country'])): ?><br><?= htmlspecialchars($a['country']) ?><?php endif; ?></div>
        </div>
        <div>
          <div class="ahint" style="text-transform:uppercase;font-size:10.5px;letter-spacing:.5px;margin-bottom:5px">🏢 Company</div>
          <div style="font-size:13px;line-height:1.6"><?= htmlspecialchars(($a['company']??'')?:'—') ?><?php if(!empty($a['vat_id'])): ?><br>VAT: <b><?= htmlspecialchars($a['vat_id']) ?></b><?php endif; ?><?php if(!empty($a['reg_number'])): ?><br>Reg: <?= htmlspecialchars($a['reg_number']) ?><?php endif; ?></div>
        </div>
        <div>
          <div class="ahint" style="text-transform:uppercase;font-size:10.5px;letter-spacing:.5px;margin-bottom:5px">☎ Contact</div>
          <div style="font-size:13px;line-height:1.6"><?= htmlspecialchars(($a['name']??'')?:'—') ?><br><a href="mailto:<?= htmlspecialchars($a['email']??'') ?>" style="color:var(--acc)"><?= htmlspecialchars($a['email']??'') ?></a><?php if(!empty($a['phone'])): ?><br>📞 <?= htmlspecialchars($a['phone']) ?><?php endif; ?><?php if(!empty($a['website'])): ?><br>🔗 <a href="<?= htmlspecialchars($a['website']) ?>" target="_blank" rel="noopener" style="color:var(--acc)"><?= htmlspecialchars($a['website']) ?></a><?php endif; ?></div>
        </div>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
</div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ ORDERS
elseif($tab==='orders'):
  $cnt_ship=count(array_filter($orders,fn($o)=>($orderSt[$o['ref']??'']['status']??'')==='shipped'));
  $cnt_done=count(array_filter($orders,fn($o)=>($orderSt[$o['ref']??'']['status']??'')==='completed'));
  /* Full order dossier (?tab=orders&view=REF): everything about one order on
     one screen — buyer, delivery, items, money, escrow, commission, invoices,
     status control — so the admin never pieces an order together from a row. */
  $viewRef=trim($_GET['view']??''); $viewRow=null;
  if($viewRef!==''){ foreach($orders as $__o){ if(($__o['ref']??'')===$viewRef){ $viewRow=$__o; break; } } }
  if($viewRow):
    $vst=$orderSt[$viewRef]??[]; $vstatus=$vst['status']??'pending';
    $vlines=vestra_order_lines($viewRow)['lines']??[];
    $ver=escrow_get($viewRef);
    $vpay=$ver?'escrow':(str_contains($viewRow['notes']??'','Secure escrow')?'escrow':'bank');
    $vship=''; if(preg_match('/Deliver to: (.*?)(?:\.\s|$)/u', $viewRow['notes']??'', $m)) $vship=$m[1];
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h2 style="font-size:18px;font-weight:700">📦 Order <span class="atag" style="font-size:14px"><?= htmlspecialchars($viewRef) ?></span> · <?= orderBadge($vstatus) ?><?= $ver?' · '.escrow_badge($ver['status']??''):'' ?></h2>
  <a class="abtn" href="/admin?tab=orders">← All orders</a>
</div>

<div class="acols2">
  <div class="acard">
    <div class="acard-hd"><h3>Buyer & delivery</h3></div>
    <table class="atable">
      <?= arow(['Company','<b>'.htmlspecialchars($viewRow['company']??'—').'</b>'.(($viewRow['vat']??'')!==''?' · VAT '.htmlspecialchars($viewRow['vat']):'')]) ?>
      <?= arow(['Contact',htmlspecialchars($viewRow['name']??'—').' · <a href="mailto:'.htmlspecialchars($viewRow['email']??'').'" style="color:var(--acc)">'.htmlspecialchars($viewRow['email']??'').'</a>']) ?>
      <?= arow(['Country / Phone',htmlspecialchars($viewRow['country']??'—').(($viewRow['phone']??'')!==''?' · '.htmlspecialchars($viewRow['phone']):'')]) ?>
      <?= arow(['Delivery address',$vship!==''?htmlspecialchars($vship):'<span style="color:var(--mut)">same as billing</span>']) ?>
      <?= arow(['Payment',$vpay==='escrow'?'🛡️ Secure escrow (card)':'🏦 Bank transfer (invoice)']) ?>
      <?= arow(['Placed',htmlspecialchars(substr($viewRow['timestamp']??'',0,16))]) ?>
      <?php if(($viewRow['notes']??'')!==''): ?><?= arow(['Notes','<span style="font-size:12px">'.htmlspecialchars($viewRow['notes']).'</span>']) ?><?php endif; ?>
    </table>
  </div>

  <div class="acard">
    <div class="acard-hd"><h3>Money</h3></div>
    <table class="atable">
      <?= arow(['Subtotal',eur($viewRow['subtotal']??0)]) ?>
      <?= arow(['Platform commission','<b style="color:#1f9d63">'.eur($viewRow['commission']??0).'</b>']) ?>
      <?= arow(['Seller payout',eur($viewRow['payout']??0)]) ?>
      <?= arow(['<b>Buyer pays</b>','<b>'.eur($viewRow['total']??0).'</b>']) ?>
    </table>
    <div style="margin-top:12px">
      <div class="ahint" style="margin-bottom:6px;font-weight:600">Commission charges</div>
      <?php $vcoms=vestra_commissions_for_ref($viewRef); if(!$vcoms): ?><span style="color:var(--mut);font-size:12px">— none recorded</span>
      <?php else: foreach($vcoms as $c): ?>
        <div style="font-size:12px;padding:3px 0"><?= match($c['status']??''){'charged'=>abadge('✓ charged '.eur($c['amount']??0),'#1f9d63'),'failed'=>abadge('✗ failed '.eur($c['amount']??0),'#c0392b'),'no_card'=>abadge('⚠ no card','#a9781a'),default=>abadge($c['status']??'—','#888')} ?> <span style="color:var(--mut)"><?= htmlspecialchars(substr($c['timestamp']??'',0,16)) ?></span></div>
      <?php endforeach; endif; ?>
    </div>
    <div style="margin-top:12px">
      <div class="ahint" style="margin-bottom:6px;font-weight:600">Invoices</div>
      <?php $vinvs=vestra_invoices_for_ref($viewRef); if(!$vinvs): ?>
        <div style="color:var(--mut);font-size:12px;margin-bottom:8px">— not issued yet · auto-invoicing suspended</div>
        <?php if(!str_contains((string)($viewRow['notes']??''),'Secure escrow')): ?>
        <form method="post" style="margin:0" onsubmit="return confirm('Issue the invoice(s) for this order and email the buyer? Do this once stock is confirmed.')">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="issue_invoice">
          <input type="hidden" name="ref" value="<?= htmlspecialchars($viewRef) ?>">
          <input type="hidden" name="from" value="view">
          <button class="abtn primary" type="submit" style="font-size:12px">✓ Approve &amp; issue invoice</button>
        </form>
        <?php endif; ?>
      <?php else: foreach($vinvs as $iv): ?>
        <a href="<?= htmlspecialchars($iv['url']) ?>" target="_blank" rel="noopener" style="color:var(--acc);display:inline-block;margin-right:12px;font-size:12.5px">📄 <?= htmlspecialchars($iv['no']) ?> · <?= htmlspecialchars($iv['seller_label']) ?></a>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<div class="acard" style="margin-top:16px">
  <div class="acard-hd"><h3>Items</h3></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['SKU','Product','Colours','Qty','Unit','Line total'],true) ?>
    <?php foreach($vlines as $l): ?>
    <?= arow([htmlspecialchars($l['sku']??''),'<b>'.htmlspecialchars(($l['brand']??'').' '.($l['name']??'')).'</b>',htmlspecialchars(implode(', ',(array)($l['colors']??[]))?:'—'),(int)($l['qty']??0),eur($l['unit']??0),'<b>'.eur($l['line']??0).'</b>']) ?>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="acols2" style="margin-top:16px">
  <div class="acard">
    <div class="acard-hd"><h3>Status & tracking</h3></div>
    <?php if(!empty($vst['history'])): ?>
      <div style="margin-bottom:12px">
      <?php foreach($vst['history'] as $ev): ?>
        <div class="ahint" style="padding:3px 0"><?= htmlspecialchars(substr($ev['at']??'',0,16)) ?> — <b><?= htmlspecialchars($ev['status']??'') ?></b> <span style="color:var(--mut)">(<?= htmlspecialchars($ev['by']??'') ?>)</span><?= !empty($ev['note'])?' · '.htmlspecialchars($ev['note']):'' ?></div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <?= csrfField() ?>
      <input type="hidden" name="_action" value="order_status">
      <input type="hidden" name="ref" value="<?= htmlspecialchars($viewRef) ?>">
      <select name="status" style="padding:6px 10px;border:1px solid var(--line);border-radius:7px;background:var(--bg);color:var(--ink)">
        <option value="pending" <?= $vstatus==='pending'?'selected':'' ?>>⏳ Awaiting payment</option>
        <option value="paid" <?= $vstatus==='paid'?'selected':'' ?>>💶 Paid — to ship</option>
        <option value="shipped" <?= $vstatus==='shipped'?'selected':'' ?>>🚚 Shipped</option>
        <option value="completed" <?= $vstatus==='completed'?'selected':'' ?>>✓ Completed</option>
      </select>
      <input name="tracking" value="<?= htmlspecialchars($vst['tracking']??'') ?>" placeholder="Tracking no." style="padding:6px 10px;border:1px solid var(--line);border-radius:7px;background:var(--bg);color:var(--ink)">
      <button class="abtn primary" type="submit">Save</button>
    </form>
  </div>

  <div class="acard">
    <div class="acard-hd"><h3>🛡️ Escrow</h3></div>
    <?php if(!$ver): ?>
      <span style="color:var(--mut);font-size:13px">Not an escrow order — payment runs by bank transfer against the invoice.</span>
    <?php else: ?>
      <table class="atable">
        <?= arow(['Status',escrow_badge($ver['status']??'')]) ?>
        <?= arow(['Paid at',htmlspecialchars(substr($ver['paid_at']??'—',0,16))]) ?>
        <?= arow(['Held amount',eur($ver['total']??0).' <span style="color:var(--mut)">(fee '.eur(($ver['fee']??0)/100).')</span>']) ?>
        <?php if(!empty($ver['released_at'])): ?><?= arow(['Released',htmlspecialchars(substr($ver['released_at'],0,16))]) ?><?php endif; ?>
        <?php if(!empty($ver['refunded_at'])): ?><?= arow(['Refunded',htmlspecialchars(substr($ver['refunded_at'],0,16))]) ?><?php endif; ?>
      </table>
      <?php if(($ver['status']??'')==='held'): ?>
      <div style="display:flex;gap:8px;margin-top:12px">
        <form method="post" onsubmit="return confirm('Release the held funds to the seller?')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_release"><input type="hidden" name="ref" value="<?= htmlspecialchars($viewRef) ?>"><button class="abtn" type="submit" style="color:#1f9d63">Release to seller</button></form>
        <form method="post" onsubmit="return confirm('Refund the buyer in full? This cancels the sale.')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_refund"><input type="hidden" name="ref" value="<?= htmlspecialchars($viewRef) ?>"><button class="abtn" type="submit" style="color:#c0392b">Refund buyer</button></form>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>

<div class="asgrid" style="grid-template-columns:repeat(5,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv"><?= count($orders) ?></div><div class="sl">Total orders</div></div>
  <div class="ascard"><div class="sv" style="color:#888"><?= count($orders)-$cnt_ship-$cnt_done ?></div><div class="sl">Awaiting payment</div></div>
  <div class="ascard"><div class="sv" style="color:#9a7320"><?= $cnt_ship ?></div><div class="sl">Shipped</div></div>
  <div class="ascard"><div class="sv" style="color:#1f9d63"><?= $cnt_done ?></div><div class="sl">Completed</div></div>
  <div class="ascard"><div class="sv"><?= eur($totalRevenue) ?></div><div class="sl">Total volume</div></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=orders">⬇ Download CSV</a></div>

<?php
/* Legacy duplicate refs (same buyer + same items pre-fix) share ONE status entry —
   the "update one, all change" bug. Offer the one-click repair when any exist. */
$__refCounts = array_count_values(array_filter(array_map(fn($o)=>$o['ref']??'', $orders)));
$__dupRefs   = array_filter($__refCounts, fn($n)=>$n>1);
if($__dupRefs): ?>
<div class="amsg" style="background:rgba(240,192,96,.08);border:1px solid rgba(240,192,96,.3);color:#a9781a;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
  <span>⚠ <b><?= count($__dupRefs) ?></b> ref is shared by multiple orders (<?= htmlspecialchars(implode(', ', array_slice(array_keys($__dupRefs),0,4))) ?><?= count($__dupRefs)>4?', …':'' ?>)
  — they share one status entry, so updating one updates them all.</span>
  <form method="post" style="margin:0" onsubmit="return confirm('Give each duplicate order its own fresh ref? The oldest keeps the original ref (and its invoices); statuses are preserved.')">
    <?= csrfField() ?><input type="hidden" name="_action" value="fix_dup_refs">
    <button class="abtn primary" type="submit">🔧 Repair duplicate refs</button>
  </form>
</div>
<?php endif; ?>

<?php if(!$orders): ?><div class="acard"><div class="aempty">No orders yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Ref','Date','Buyer','Company','Items','Total','Status','Tracking','Invoices','Commission','Escrow','Update'],true) ?>
  <?php foreach(array_reverse($orders) as $o):
    $ref=$o['ref']??''; $st=$orderSt[$ref]['status']??'pending'; $trk=$orderSt[$ref]['tracking']??''; ?>
  <tr>
    <td class="ac"><a href="/admin?tab=orders&view=<?= urlencode($ref) ?>" style="text-decoration:none"><span class="atag" title="Open full order dossier"><?= htmlspecialchars(substr($ref,0,12)) ?> →</span></a></td>
    <td class="ac" style="font-size:11px;color:var(--mut)"><?= htmlspecialchars(substr($o['timestamp']??'',0,10)) ?></td>
    <td class="ac"><a href="mailto:<?= htmlspecialchars($o['email']??'') ?>" style="color:var(--acc);font-size:12px"><?= htmlspecialchars($o['email']??'') ?></a></td>
    <td class="ac"><?= htmlspecialchars($o['company']??'—') ?></td>
    <td class="ac" style="font-size:11px"><?= vestra_order_items_cell($o['items']??'', 2, 160) ?></td>
    <td class="ac"><b><?= eur($o['total']??0) ?></b></td>
    <td class="ac"><?= orderBadge($st) ?></td>
    <td class="ac" style="font-size:11px"><?= htmlspecialchars($trk) ?></td>
    <td class="ac" style="font-size:11px"><?php foreach(vestra_invoices_for_ref($ref) as $iv): ?>
      <a href="<?= htmlspecialchars($iv['url']) ?>" target="_blank" rel="noopener" style="color:var(--acc);display:block"><?= htmlspecialchars($iv['no']) ?></a>
    <?php endforeach; ?></td>
    <td class="ac" style="font-size:11px">
      <?php $coms=vestra_commissions_for_ref($ref); if(!$coms): ?><span style="color:var(--mut)">—</span>
      <?php else: foreach($coms as $c): ?>
        <?= match($c['status']??''){
          'charged'=>abadge('✓ '.eur($c['amount']??0),'#1f9d63'),
          'failed'=>abadge('✗ '.eur($c['amount']??0),'#c0392b'),
          'no_card'=>abadge('⚠ no card','#a9781a'),
          default=>abadge('—','#555'),
        } ?><br>
      <?php endforeach; endif; ?>
    </td>
    <td class="ac" style="font-size:11px">
      <?php $er=escrow_get($ref); if(!$er): ?><span style="color:var(--mut)">—</span>
      <?php else: ?>
        <?= escrow_badge($er['status']??'') ?>
        <?php if(!empty($er['disputed'])): ?><div style="color:#a9781a;margin-top:2px">⚠ <?= htmlspecialchars(mb_substr((string)($er['dispute_reason']??'disputed'),0,50)) ?></div><?php endif; ?>
        <?php if(($er['status']??'')==='held'): ?>
        <div style="display:flex;gap:4px;margin-top:4px">
          <form method="post" onsubmit="return confirm('Release the held funds to the seller?')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_release"><input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>"><button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#1f9d63" title="Release to seller">Release</button></form>
          <form method="post" onsubmit="return confirm('Refund the buyer in full? This cancels the sale.')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_refund"><input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>"><button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#c0392b" title="Refund buyer">Refund</button></form>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </td>
    <td class="ac"><?php if($st!=='completed'): ?>
      <form method="post" style="display:flex;flex-direction:column;gap:5px">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="order_status">
        <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">
        <select name="status" style="padding:3px 6px;border:1px solid var(--line);border-radius:5px;background:var(--bg);color:var(--ink);font-size:11px">
          <option value="pending" <?= $st==='pending'?'selected':'' ?>>⏳ Awaiting payment</option>
          <option value="paid" <?= $st==='paid'?'selected':'' ?>>💶 Paid — to ship</option>
          <option value="shipped" <?= $st==='shipped'?'selected':'' ?>>🚚 Shipped</option>
          <option value="completed" <?= $st==='completed'?'selected':'' ?>>✓ Completed</option>
        </select>
        <input name="tracking" value="<?= htmlspecialchars($trk) ?>" placeholder="Tracking no." style="padding:3px 6px;border:1px solid var(--line);border-radius:5px;background:var(--bg);color:var(--ink);font-size:11px">
        <button class="abtn primary" type="submit" style="font-size:11px;padding:3px 8px">Save</button>
      </form>
    <?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>
<?php endif; // order dossier vs list ?>

<?php // ===================================================== INVOICE APPROVALS
elseif($tab==='invoices'): ?>

<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd"><h3>🧾 Invoice approvals</h3></div>
  <p class="ahint" style="margin:0">Automatic invoicing is <b>suspended</b>. After you confirm stock for an order, approve it here — the PDF invoice is then issued, emailed to the buyer and added to their account (My orders / confirmation page). Card &amp; escrow orders invoice themselves on payment and are not listed.</p>
</div>

<?php if(!$pendingInvoiceOrders): ?>
  <div class="acard"><div style="padding:26px;text-align:center;color:var(--mut)">✓ No orders are awaiting an invoice.</div></div>
<?php else: ?>
<div class="acard">
  <div class="acard-hd"><h3><?= $pendingInvoiceCount ?> awaiting your approval</h3></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['Order','Buyer','Placed','Buyer pays','Approve'],true) ?>
    <?php foreach($pendingInvoiceOrders as $o): $oref=(string)($o['ref']??''); ?>
    <tr>
      <td><a class="acc" href="/admin?tab=orders&view=<?= urlencode($oref) ?>"><?= htmlspecialchars($oref) ?></a></td>
      <td><?= htmlspecialchars($o['company']??'') ?><div class="ahint"><?= htmlspecialchars($o['name']??'') ?> · <?= htmlspecialchars($o['email']??'') ?></div></td>
      <td style="font-size:12px;white-space:nowrap"><?= htmlspecialchars(substr($o['timestamp']??'',0,16)) ?></td>
      <td><b><?= eur($o['total']??0) ?></b></td>
      <td>
        <form method="post" style="margin:0" onsubmit="return confirm('Issue the invoice for order <?= htmlspecialchars($oref) ?> and email the buyer? Do this once stock is confirmed.')">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="issue_invoice">
          <input type="hidden" name="ref" value="<?= htmlspecialchars($oref) ?>">
          <button class="abtn primary" type="submit" style="font-size:12px">✓ Approve &amp; issue</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>



<?php // ══════════════════════════════════════════════════════ OFFERS
elseif($tab==='offers'):
  $cnt_acc=count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='accept'));
  $cnt_dec=count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='decline'));
  $cnt_ctr=count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='counter'));
?>
<div class="asgrid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv" style="color:#a9781a"><?= count($pendingOffers) ?></div><div class="sl">Pending</div></div>
  <div class="ascard"><div class="sv" style="color:#1f9d63"><?= $cnt_acc ?></div><div class="sl">Accepted</div></div>
  <div class="ascard"><div class="sv" style="color:#9a7320"><?= $cnt_ctr ?></div><div class="sl">Countered</div></div>
  <div class="ascard"><div class="sv" style="color:#c0392b"><?= $cnt_dec ?></div><div class="sl">Declined</div></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=offers">⬇ CSV</a></div>
<?php if(!$offers): ?><div class="acard"><div class="aempty">No offers yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Ref','Date','Product','SKU','Qty','€/u','Total','Buyer','Status','Counter','Respond'],true) ?>
  <?php foreach(array_reverse($offers) as $o):
    $ref=$o['ref']??''; $resp=$offerResp[$ref]??null; $rSt=$resp['status']??'pending';
    /* Bu sutun daha once YOKTU: tablo salt-okunurdu ve bir teklifi kabul etmenin
       hicbir yolu yoktu. Satici ucu de katalog urunlerinde calismiyor (seller_uid
       bos). Operator icin yanit formu burada; ayni kodu calistiriyor. */
    $canRespond = ($rSt === 'pending');
    $respondCell = $canRespond ? (
      '<form method="post" style="display:flex;gap:5px;align-items:center;flex-wrap:wrap">'
      .csrfField()
      .'<input type="hidden" name="_action" value="offer_respond">'
      .'<input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">'
      .'<button class="abtn" name="response" value="accept" type="submit" style="color:var(--ok);border-color:rgba(122,214,160,.4)" '
      .'onclick="return confirm(\'Accept offer '.htmlspecialchars($ref, ENT_QUOTES).'? The buyer is emailed immediately.\')">✓ Accept</button>'
      .'<button class="abtn" name="response" value="decline" type="submit" style="color:var(--bad);border-color:rgba(239,154,154,.3)" '
      .'onclick="return confirm(\'Decline offer '.htmlspecialchars($ref, ENT_QUOTES).'?\')">✗</button>'
      .'<input name="counter_price" placeholder="€/u" inputmode="decimal" style="width:62px;padding:4px 6px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12px">'
      .'<button class="abtn" name="response" value="counter" type="submit" style="color:#9a7320;border-color:rgba(154,115,32,.4)">↩</button>'
      .'</form>'
    ) : '<span class="ahint">'.htmlspecialchars(substr((string)($resp['responded_at']??''),0,10)).'</span>';
  ?>
  <?= arow([
    '<span class="atag">'.htmlspecialchars(substr($ref,0,10)).'</span>',
    htmlspecialchars(substr($o['timestamp']??'',0,10)),
    htmlspecialchars($o['product']??'—'),
    htmlspecialchars($o['sku']??''),
    htmlspecialchars($o['qty']??''),
    eur($o['offer_unit']??0),
    '<b>'.eur($o['offer_total']??0).'</b>',
    '<a href="mailto:'.htmlspecialchars($o['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($o['email']??'').'</a>',
    match($rSt){'accept'=>abadge('✓ Accepted','#1f9d63'),'decline'=>abadge('✗ Declined','#c0392b'),'counter'=>abadge('↩ Counter','#9a7320'),default=>abadge('⏳ Pending','#888')},
    ($resp&&$rSt==='counter')?eur($resp['counter_price']??0):'—',
    $respondCell,
  ]) ?>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ REQUESTS
elseif($tab==='requests'): ?>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=requests">⬇ CSV</a></div>
<?php if(!$requests): ?><div class="acard"><div class="aempty">No buyer sourcing requests yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Date','Ref','Looking for','Email','Category','Qty','Target','Country','Reference','Notes'],true) ?>
  <?php foreach(array_reverse($requests) as $r): ?>
  <?= arow([
    htmlspecialchars(substr($r['timestamp']??'',0,10)),
    '<span class="atag">'.htmlspecialchars($r['ref']??'').'</span>',
    '<b>'.htmlspecialchars($r['title']??'—').'</b>',
    '<a href="mailto:'.htmlspecialchars($r['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($r['email']??'').'</a>',
    htmlspecialchars($r['cat']??''),
    htmlspecialchars($r['qty']??''),
    htmlspecialchars($r['target']??''),
    htmlspecialchars($r['country']??''),
    !empty($r['ref_url']) ? '<a href="'.htmlspecialchars($r['ref_url']).'" target="_blank" rel="noopener nofollow" style="color:var(--acc)">🔗 link</a>'.(!empty($r['ref_image']) ? ' <a href="'.htmlspecialchars($r['ref_image']).'" target="_blank" rel="noopener">🖼</a>' : '') : (!empty($r['ref_image']) ? '<a href="'.htmlspecialchars($r['ref_image']).'" target="_blank" rel="noopener">🖼 photo</a>' : '—'),
    htmlspecialchars(substr($r['notes']??'',0,80)),
  ]) ?>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ REQUEST OFFERS
elseif($tab==='req_offers'): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div><h2 style="font-size:18px;font-weight:700">Request Offers</h2><p class="ahint" style="margin-top:4px">Seller offers submitted on buyer sourcing requests.</p></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=request_offers">⬇ CSV</a></div>
<?php if(!$reqOffers): ?><div class="acard"><div class="aempty">No seller offers on requests yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Ref','Date','Request ref','Seller company','Seller email','Price','Qty','Delivery','Message'],true) ?>
  <?php foreach(array_reverse($reqOffers) as $ro): ?>
  <?= arow([
    '<span class="atag">'.htmlspecialchars(substr($ro['ref']??'',0,12)).'</span>',
    htmlspecialchars(substr($ro['timestamp']??'',0,10)),
    '<span class="atag">'.htmlspecialchars($ro['request_ref']??'—').'</span>',
    '<b>'.htmlspecialchars($ro['seller_company']??'—').'</b>',
    '<a href="mailto:'.htmlspecialchars($ro['seller_email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($ro['seller_email']??'').'</a>',
    htmlspecialchars($ro['price']??''),
    htmlspecialchars($ro['qty']??''),
    htmlspecialchars($ro['delivery']??''),
    htmlspecialchars(substr($ro['message']??'',0,80)),
  ]) ?>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ PRICES & MOQ
elseif($tab==='prices'):
  $allProd = vestra_products();
  usort($allProd, function($a,$b){
    $ad=vestra_is_demo_product($a['id']??'')?0:1; $bd=vestra_is_demo_product($b['id']??'')?0:1;
    return $ad<=>$bd ?: strcmp(($a['brand']??'').($a['name']??''),($b['brand']??'').($b['name']??''));
  });
?>
<div class="acard-hd" style="margin-bottom:6px"><h3>💶 Prices &amp; MOQ — edit every product in one place</h3></div>
<p style="color:var(--mut);font-size:13px;margin:0 0 16px;max-width:720px">
  Retune the minimum order quantity and the tiered wholesale pricing for the whole catalogue,
  then hit <b>Save all</b> once. Built-in products and live seller listings are all editable here.
  Leave a tier's two boxes empty to drop it; the lowest tier price is shown to buyers as the “from” price.
</p>
<div class="acard" style="margin-bottom:16px;border-color:rgba(169,127,44,.35)">
  <div class="acard-body" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <div style="font-size:13px;color:var(--mut);max-width:640px">
      <b style="color:var(--ink)">⚙ Apply pricing rules</b> — one click on the seller listings:
      remove “make an offer” → fixed · <b>Amiri</b> polos €40 / MOQ 50 · other <b>polos</b> €70 ·
      <b>T-shirts</b> (excl. Lacoste/Ralph/Amiri) €49.90 sale −29% · <b>MOQ 20</b> on the rest.
      <b>Lacoste &amp; Ralph Lauren</b> stay untouched.
    </div>
    <form method="post" action="/admin" style="margin:0" onsubmit="return confirm('Apply the pricing rules to all seller listings?\n\n• Offers become fixed prices\n• Amiri polos → €40, MOQ 50\n• Other polos → €70\n• T-shirts (not Lacoste/Ralph/Amiri) → €49.90 sale -29% (flat, even at 20)\n• MOQ 20 on everything else\n• Lacoste &amp; Ralph Lauren untouched\n\nThis overwrites the affected prices.')">
      <?= csrfField() ?><input type="hidden" name="_action" value="apply_pricing_rules">
      <button class="abtn primary" type="submit" style="padding:9px 18px;white-space:nowrap">⚙ Apply pricing rules</button>
    </form>
  </div>
</div>
<form method="post" action="/admin">
  <?= csrfField() ?><input type="hidden" name="_action" value="save_prices">
  <div style="position:sticky;top:0;z-index:5;background:var(--bg);padding:8px 0;margin-bottom:6px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;border-bottom:1px solid var(--line)">
    <button class="abtn primary" type="submit" style="padding:9px 18px">💾 Save all prices</button>
    <span style="color:var(--mut);font-size:12px"><?= count($allProd) ?> products · changes apply to the live catalogue instantly</span>
  </div>
  <div class="acard"><div class="atscroll"><table class="atable pricetable">
    <?= arow(['Product','Type','MOQ','List €<div class="ahint" style="font-weight:400">sale only</div>','Tier 1 — min → €','Tier 2','Tier 3','From'],true) ?>
    <?php foreach($allProd as $p): $id=(string)($p['id']??''); $eid=htmlspecialchars($id); $t=array_values($p['tiers']??[]); $demo=vestra_is_demo_product($id); $thumb=vestra_primary_image($p); ?>
    <tr>
      <td class="ac" style="min-width:210px">
        <div style="display:flex;align-items:center;gap:9px">
          <?php if($thumb): ?><img src="<?= htmlspecialchars($thumb) ?>" alt="" style="width:34px;height:34px;object-fit:cover;border-radius:6px;border:1px solid var(--line);flex:none">
          <?php else: ?><div style="width:34px;height:34px;border-radius:6px;flex:none;background:linear-gradient(135deg,<?= htmlspecialchars($p['accent']??'#cfc8ba') ?>,#e8e2d7)"></div><?php endif; ?>
          <div style="min-width:0">
            <div style="font-size:11px;color:var(--mut);letter-spacing:.02em"><?= htmlspecialchars($p['brand']??'') ?></div>
            <div style="font-weight:600;line-height:1.2"><?= htmlspecialchars($p['name']??'') ?></div>
            <div class="ahint"><span class="atag" style="font-size:9px"><?= htmlspecialchars($p['sku']??'') ?></span>
              <?= $demo?abadge('Built-in','#9a7320'):abadge('Listing','#3366cc') ?>
              <a href="/product?id=<?= urlencode($id) ?>" target="_blank" rel="noopener" style="font-size:10px;color:#1f9d63;text-decoration:none;font-weight:600" title="Open the live product page">↗ View</a></div>
          </div>
        </div>
      </td>
      <td class="ac"><select name="mode[<?= $eid ?>]" style="padding:5px"><?php foreach(['fixed','sale','offer'] as $m): ?><option <?= ($p['mode']??'fixed')===$m?'selected':'' ?>><?= $m ?></option><?php endforeach; ?></select></td>
      <td class="ac"><input type="number" min="1" name="moq[<?= $eid ?>]" value="<?= (int)($p['moq']??1) ?>" style="width:64px;padding:5px"></td>
      <?php $lv = (isset($p['list']) && $p['list']!=='') ? (string)$p['list'] : ''; ?>
      <td class="ac"><input type="number" step="0.01" min="0" name="list[<?= $eid ?>]" value="<?= htmlspecialchars($lv) ?>" placeholder="—" style="width:72px;padding:5px"></td>
      <?php for($i=0;$i<3;$i++): ?>
      <td class="ac"><div style="display:flex;gap:4px">
        <input type="number" min="1" name="t<?= $i+1 ?>min[<?= $eid ?>]" value="<?= htmlspecialchars((string)($t[$i]['min']??'')) ?>" placeholder="min" style="width:56px;padding:5px">
        <input type="number" step="0.01" min="0" name="t<?= $i+1 ?>price[<?= $eid ?>]" value="<?= htmlspecialchars((string)($t[$i]['price']??'')) ?>" placeholder="€" style="width:62px;padding:5px">
      </div></td>
      <?php endfor; ?>
      <td class="ac"><b><?= ($p['mode']??'')==='offer' ? '—' : eur(vestra_from_price($p)) ?></b></td>
    </tr>
    <?php endforeach; ?>
  </table></div></div>
  <div style="margin-top:14px"><button class="abtn primary" type="submit" style="padding:9px 18px">💾 Save all prices</button></div>
</form>

<?php // ══════════════════════════════════════════════════════ LISTINGS
elseif($tab==='listings'):
  $liveList   = array_filter($listings,fn($p)=>($p['status']??'approved')==='approved');
  $rejList    = array_filter($listings,fn($p)=>($p['status']??'')==='rejected');
  $ledit      = ($leid=($_GET['edit']??'')) ? vestra_listing_by_id($leid) : null;
?>
<?php if($ledit): $lc=(array)($ledit['colors']??[]); $lt=$ledit['tiers']??[]; ?>
<div class="acard" style="margin-bottom:18px;border-color:var(--acc)">
  <div class="acard-hd"><h3>✏️ Edit listing — <?= htmlspecialchars(trim(($ledit['brand']??'').' '.($ledit['name']??''))) ?></h3>
    <div style="display:flex;gap:6px">
      <?php if(($ledit['status']??'approved')==='approved'): ?><a class="abtn" href="/product?id=<?= urlencode($ledit['id']??'') ?>" target="_blank" rel="noopener" style="border-color:rgba(31,157,99,.4);color:#1f9d63">View live ↗</a><?php endif; ?>
      <a class="abtn" href="/admin?tab=listings">✕ Close</a>
    </div></div>
  <div class="acard-body">
    <form method="post" action="/admin" class="aform">
      <?= csrfField() ?><input type="hidden" name="_action" value="admin_save_listing"><input type="hidden" name="lid" value="<?= htmlspecialchars($ledit['id']??'') ?>">
      <div class="acols2">
        <div class="afield"><label>Brand</label><input name="brand" value="<?= htmlspecialchars($ledit['brand']??'') ?>"></div>
        <div class="afield"><label>Product name</label><input name="name" value="<?= htmlspecialchars($ledit['name']??'') ?>"></div>
      </div>
      <div class="acols3">
        <div class="afield"><label>Category</label><input name="cat" value="<?= htmlspecialchars($ledit['cat']??'') ?>"></div>
        <div class="afield"><label>SKU</label><input name="sku" value="<?= htmlspecialchars($ledit['sku']??'') ?>"></div>
        <div class="afield"><label>MOQ</label><input type="number" name="moq" min="1" value="<?= (int)($ledit['moq']??1) ?>"></div>
      </div>
      <div class="acols3">
        <div class="afield"><label>Mode</label><select name="mode"><?php foreach(['fixed','sale','offer'] as $m): ?><option <?= ($ledit['mode']??'fixed')===$m?'selected':'' ?>><?= $m ?></option><?php endforeach; ?></select></div>
        <div class="afield"><label>Pack size (0 = none)</label><input type="number" name="size_step" min="0" value="<?= (int)($ledit['size_step']??0) ?>"></div>
        <div class="afield"><label>Min colours (0 = none)</label><input type="number" name="min_colors" min="0" value="<?= (int)($ledit['min_colors']??0) ?>"></div>
      </div>
      <label style="font-size:12px;color:var(--mut);display:block;margin:2px 0 4px">Price tiers — min qty → €/unit</label>
      <div class="acols3">
        <?php for($i=0;$i<3;$i++): ?>
        <div style="display:flex;gap:6px"><input type="number" name="t<?= $i+1 ?>min" placeholder="min qty" value="<?= htmlspecialchars((string)($lt[$i]['min']??'')) ?>"><input type="number" step="0.01" name="t<?= $i+1 ?>price" placeholder="€/unit" value="<?= htmlspecialchars((string)($lt[$i]['price']??'')) ?>"></div>
        <?php endfor; ?>
      </div>
      <div class="afield"><label>Colours</label>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:2px">
          <?php foreach(vestra_colors() as $cn=>$hex): ?>
          <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer"><input type="checkbox" name="colors[]" value="<?= htmlspecialchars($cn) ?>" <?= in_array($cn,$lc,true)?'checked':'' ?>><span style="width:13px;height:13px;border-radius:50%;background:<?= htmlspecialchars($hex) ?>;display:inline-block;border:1px solid var(--line)"></span><?= htmlspecialchars($cn) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="acols2">
        <div class="afield"><label>Status</label><select name="status"><?php foreach(['approved','pending','rejected','suspended'] as $s): ?><option <?= ($ledit['status']??'approved')===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="afield"><label>Seller (reassign)</label><select name="seller_uid">
          <option value="">— keep current (<?= htmlspecialchars($ledit['seller']??$ledit['seller_uid']??'—') ?>)</option>
          <?php foreach(array_filter($accounts,fn($a)=>($a['type']??'')==='seller') as $a): ?>
          <option value="<?= htmlspecialchars($a['id']??'') ?>" <?= ($ledit['seller_uid']??'')===($a['id']??'')?'selected':'' ?>><?= htmlspecialchars($a['company']?:($a['name']?:($a['email']??'?'))) ?></option>
          <?php endforeach; ?>
        </select></div>
      </div>
      <div class="afield"><label>Description</label><textarea name="desc" rows="3"><?= htmlspecialchars($ledit['desc']??'') ?></textarea></div>
      <button class="abtn primary" type="submit" style="justify-content:center;padding:10px">💾 Save listing</button>
    </form>
  </div>
</div>
<?php endif; ?>
<div class="asgrid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv"><?= count($listings) ?></div><div class="sl">Custom listings</div></div>
  <div class="ascard"><div class="sv" style="color:#1f9d63"><?= count($liveList) ?></div><div class="sl">Live / approved</div></div>
  <div class="ascard"><div class="sv" style="color:#a9781a"><?= count($pendingList) ?></div><div class="sl">Pending approval</div></div>
  <div class="ascard"><div class="sv" style="color:var(--mut)"><?= count(vestra_demo_products()) ?></div><div class="sl">Demo products</div></div>
</div>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 16px">
  <form method="post" style="margin:0" onsubmit="return confirm('Set MOQ = 20 on EVERY listing except Lacoste, Ralph Lauren and Amiri? (Those three keep their current MOQ.)')">
    <?= csrfField() ?><input type="hidden" name="_action" value="bulk_moq_20">
    <button class="abtn primary" type="submit" title="Bulk-set the minimum order quantity to 20 pieces on all listings whose brand is not Lacoste, Ralph Lauren or Amiri">⚙ Set MOQ = 20 — all brands except Lacoste / R.Lauren / Amiri</button>
  </form>
  <form method="post" style="margin:0" onsubmit="return confirm('Rebrand all SB E-Commerce listings to “Tyrex International BV” and hide the seller name on the public catalogue?')">
    <?= csrfField() ?><input type="hidden" name="_action" value="rebrand_sb_tyrex">
    <button class="abtn" type="submit" title="Rename every SB E-Commerce listing's seller to Tyrex International BV and hide the name publicly (shows “Verified business · via VESTRA”)">🏷 SB E-Commerce → Tyrex International BV (name hidden)</button>
  </form>
</div>
<div class="acard" style="margin-bottom:16px;border-color:rgba(169,127,44,.35)">
  <div class="acard-body">
    <div style="font-size:13px;color:var(--mut);margin-bottom:10px;max-width:720px">
      <b style="color:var(--ink)">🏢 Create Tyrex International BV (Elite) &amp; move SB E-Commerce products to it</b><br>
      Creates a verified <b>Elite</b> seller account (VAT NL853943576B01 · Amsterdam) and reassigns every
      <b>SB E-Commerce Services LLC</b> listing (and any already-rebranded “Tyrex” listing) to it.
      Enter the login e-mail for the account — a one-time password is shown after.
    </div>
    <form method="post" action="/admin" style="margin:0;display:flex;gap:10px;align-items:center;flex-wrap:wrap"
      onsubmit="return confirm('Create the verified Elite “Tyrex International BV” account and move all SB E-Commerce products to it?')">
      <?= csrfField() ?><input type="hidden" name="_action" value="create_tyrex_migrate">
      <input type="email" name="tyrex_email" required placeholder="Tyrex login e-mail" style="padding:8px 11px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink);font-size:13px;min-width:240px">
      <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--mut)"><input type="checkbox" name="hide_name" value="1"> Hide name publicly</label>
      <button class="abtn primary" type="submit" style="white-space:nowrap">🏢 Create Tyrex Elite &amp; migrate</button>
    </form>
  </div>
</div>
<?php $lgSeed=is_readable(__DIR__.'/inc/lesgarage_polos_seed.json')?json_decode((string)file_get_contents(__DIR__.'/inc/lesgarage_polos_seed.json'),true):[]; $lgN=is_array($lgSeed)?count($lgSeed):0; if($lgN): ?>
<div class="acard" style="margin-bottom:16px;border-color:rgba(169,127,44,.35)">
  <div class="acard-body" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <div style="font-size:13px;color:var(--mut);max-width:660px">
      <b style="color:var(--ink)">🅿️ Les Garage Paris catalogue (<?= $lgN ?>)</b> — this seller's products are maintained in
      inc/lesgarage_polos_seed.json (ask to add/edit a product there, then sync). Adds anything new, refreshes
      price/MOQ/colours/images/specs on anything already listed. Seller account is created automatically if missing.
    </div>
    <form method="post" action="/admin" style="margin:0" onsubmit="return confirm('Sync <?= $lgN ?> product(s) to Les Garage Paris? New items are added, existing ones get their price/MOQ/colours refreshed to match the seed.')">
      <?= csrfField() ?><input type="hidden" name="_action" value="sync_lesgarage">
      <button class="abtn primary" type="submit" style="white-space:nowrap">🅿️ Sync Les Garage Paris (<?= $lgN ?>)</button>
    </form>
  </div>
</div>
<?php endif; ?>
<?php $tyxSeed=is_readable(__DIR__.'/inc/tyrex_products_seed.json')?json_decode((string)file_get_contents(__DIR__.'/inc/tyrex_products_seed.json'),true):[]; $tyxN=is_array($tyxSeed)?count($tyxSeed):0; if($tyxN): ?>
<div class="acard" style="margin-bottom:16px;border-color:rgba(169,127,44,.35)">
  <div class="acard-body" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <div style="font-size:13px;color:var(--mut);max-width:660px">
      <b style="color:var(--ink)">👔 Tyrex International BV catalogue (<?= $tyxN ?>)</b> — this seller's products are maintained in
      inc/tyrex_products_seed.json. Adds anything new, refreshes price/MOQ/colours/images/specs on anything already
      listed. Requires the Tyrex account to already exist (create it above first if it doesn't).
    </div>
    <form method="post" action="/admin" style="margin:0" onsubmit="return confirm('Sync <?= $tyxN ?> product(s) to Tyrex International BV? New items are added, existing ones get their price/MOQ/colours refreshed to match the seed.')">
      <?= csrfField() ?><input type="hidden" name="_action" value="sync_tyrex">
      <button class="abtn primary" type="submit" style="white-space:nowrap">👔 Sync Tyrex International BV (<?= $tyxN ?>)</button>
    </form>
  </div>
</div>
<?php endif; ?>
<?php if(!$listings): ?><div class="acard"><div class="aempty">No custom listings yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['','Brand','Product','SKU','Mode','MOQ','From','Seller','Status',''],true) ?>
  <?php foreach(array_reverse($listings) as $p): $st=$p['status']??'approved'; $thumb=vestra_primary_image($p); ?>
  <tr>
    <td class="ac"><?php if($thumb): ?><img src="<?= htmlspecialchars($thumb) ?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:7px;border:1px solid var(--line)"><?php else: ?><div style="width:42px;height:42px;border-radius:7px;background:linear-gradient(135deg,<?= htmlspecialchars($p['accent']??'#cfc8ba') ?>,#e8e2d7)"></div><?php endif; ?></td>
    <td class="ac"><b><?= htmlspecialchars($p['brand']??'') ?></b></td>
    <td class="ac"><?= htmlspecialchars($p['name']??'') ?><div class="ahint"><?= htmlspecialchars(substr($p['id']??'',0,14)) ?>…</div><?= !empty($p['colors'])?'<div style="margin-top:3px">'.vestra_color_dots((array)$p['colors'],7).'</div>':'' ?></td>
    <td class="ac"><span class="atag"><?= htmlspecialchars($p['sku']??'') ?></span></td>
    <td class="ac"><span class="modechip <?= htmlspecialchars($p['mode']??'fixed') ?>"><?= htmlspecialchars($p['mode']??'fixed') ?></span></td>
    <td class="ac"><?= htmlspecialchars((string)($p['moq']??'')) ?> <?= htmlspecialchars($p['unit']??'pc') ?></td>
    <td class="ac"><?= $st==='offer'?'—':eur(vestra_from_price($p)) ?></td>
    <td class="ac"><?= htmlspecialchars($p['seller']??'—') ?></td>
    <td class="ac"><?= match($st){'approved'=>abadge('✓ Live','#1f9d63'),'rejected'=>abadge('✗ Rejected','#c0392b'),default=>abadge('⏳ Pending','#a9781a')} ?></td>
    <td class="ac"><div style="display:flex;gap:4px">
      <?php if($st==='approved'): ?><a class="abtn" href="/product?id=<?= urlencode($p['id']??'') ?>" target="_blank" rel="noopener" style="border-color:rgba(31,157,99,.4);color:#1f9d63" title="Open the live product page in a new tab">View ↗</a><?php endif; ?>
      <a class="abtn" href="/admin?tab=listings&edit=<?= urlencode($p['id']??'') ?>#top" style="border-color:rgba(201,168,106,.4)">Edit</a>
      <?php if($st==='pending'): ?><a class="abtn" href="/admin?tab=approvals">Review</a><?php endif; ?>
      <?= fBtn('Delete','delete_listing',['lid'=>$p['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.3)','Delete this listing?') ?>
    </div></td>
  </tr>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ MARKETING
elseif($tab==='marketing'): ?>
<?php
/* Customer vouchers first: these are the ones that move money on an order, so redemption
   needs to be visible at a glance. The seller invite codes below never touch an invoice. */
$vRedeemed = 0; $vGranted = 0.0;
foreach($vouchers as $v){
  foreach((array)($v['used_by']??[]) as $u){ $vRedeemed++; $vGranted += (float)($u['amount']??0); }
}
$vSorted = $vouchers;
uasort($vSorted, fn($a,$b)=>strcmp((string)($b['created']??''),(string)($a['created']??'')));
?>
<div class="acard" style="margin-bottom:18px">
  <div class="acard-hd"><h3>🎁 Welcome campaign — 5% off the first order, one personal code per customer</h3></div>
  <div class="acard-body">
    <p class="ahint" style="margin-top:0">Issues a code bound to each registered customer's e-mail (single use, first order only) and mails it in their own language. <b>Preview first.</b> Running it again never sends a second mail to anyone who already received one — it only picks up customers whose code went out unmailed, so a timeout mid-run is safe to retry.</p>
    <form method="post" class="aform">
      <?= csrfField() ?>
      <input type="hidden" name="_action" value="welcome_vouchers">
      <div class="acols2">
        <div class="afield"><label>Discount %</label><input name="w_pct" type="number" step="0.5" value="5"></div>
        <div class="afield"><label>Valid for (months)</label><input name="w_months" type="number" value="6"></div>
      </div>
      <div class="acols2">
        <div class="afield"><label>Audience</label><select name="w_aud"><option value="buyers">Buyer accounts only</option><option value="all">All accounts (incl. sellers)</option></select></div>
        <div class="afield"><label>Max sends this run</label><input name="w_limit" type="number" value="200"></div>
      </div>
      <div class="afield"><label>Leave out these countries</label>
        <input name="w_notc" placeholder="e.g. Norway, Switzerland — comma separated, blank = everyone">
        <span class="ahint">Matched on the customer's stored country. Excluded customers are listed in the preview.</span>
      </div>
      <button class="abtn" type="submit" name="w_mode" value="dry">👁 Preview (sends nothing)</button>
      <button class="abtn primary" type="submit" name="w_mode" value="send" onclick="return confirm('Issue codes and send the e-mails now?')">✉ Issue &amp; send</button>
    </form>
    <?php $wr = $_SESSION['welcome_report'] ?? null; unset($_SESSION['welcome_report']); if($wr): ?>
      <div style="margin-top:14px;padding:12px;border:1px solid var(--line,#333);border-radius:10px">
        <b><?= (int)$wr['targets'] ?> customers</b> · campaign <code><?= htmlspecialchars($wr['campaign']) ?></code> · valid to <?= htmlspecialchars($wr['expiry']) ?><br>
        <span class="ahint">new codes <?= (int)$wr['made'] ?> · reused <?= (int)$wr['reused'] ?> · sent <?= (int)$wr['sent'] ?> · already had one <?= (int)$wr['skipped'] ?> · failed <?= (int)$wr['failed'] ?></span>
        <?php if(!empty($wr['excluded'])): ?>
          <div class="ahint" style="margin-top:6px">Left out by country (<?= count($wr['excluded']) ?>):
            <?php $ex=[]; foreach($wr['excluded'] as $e) $ex[] = htmlspecialchars($e['email']).' ('.htmlspecialchars($e['country']).')'; echo implode(' · ', $ex); ?>
          </div>
        <?php endif; ?>
        <div style="max-height:260px;overflow:auto;margin-top:10px">
        <table class="atable"><tbody>
        <?php foreach($wr['rows'] as $r): if(($r['status']??'')==='limit'){ echo '<tr><td colspan="4" class="ahint">… per-run limit reached; run again for the rest</td></tr>'; continue; } ?>
          <tr>
            <td style="font-size:12px"><?= htmlspecialchars((string)($r['name']??'')) ?></td>
            <td style="font-size:12px"><?= htmlspecialchars((string)($r['email']??'')) ?></td>
            <td><code style="font-size:11px"><?= htmlspecialchars((string)($r['code']??'')) ?></code></td>
            <td style="font-size:12px"><?= match((string)($r['status']??'')){
              'sent'=>'<span style="color:#3fb27f">✓ sent</span>',
              'new'=>'would create + send',
              'retry'=>'has code, mail not sent yet',
              'already'=>'<span class="ahint">already mailed</span>',
              'failed'=>'<span style="color:#d9534f">✗ send failed</span>',
              default=>htmlspecialchars((string)($r['status']??'')) } ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="acard" style="margin-bottom:18px">
  <div class="acard-hd"><h3>🎟️ Customer vouchers (Gutschein) — <?= count($vouchers) ?> codes · <?= $vRedeemed ?> redeemed · <?= eur($vGranted) ?> granted</h3></div>
  <div class="acard-body">
  <form method="post" class="aform" style="margin-bottom:16px">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="create_voucher">
    <div class="acols2">
      <div class="afield"><label>Code (blank = auto)</label><input name="v_code" placeholder="VES-A1B2-C3D4" style="text-transform:uppercase"></div>
      <div class="afield"><label>Campaign tag</label><input name="v_campaign" placeholder="welcome5"></div>
    </div>
    <div class="acols2">
      <div class="afield"><label>Type</label><select name="v_type"><option value="percent">Percent (%)</option><option value="fixed">Fixed (€)</option></select></div>
      <div class="afield"><label>Value</label><input name="v_value" type="number" step="0.01" value="5"></div>
    </div>
    <div class="afield"><label>Bind to customer e-mail (blank = anyone)</label><input name="v_email" type="email" placeholder="buyer@shop.com"></div>
    <div class="acols2">
      <div class="afield"><label>Min. order (€, 0 = none)</label><input name="v_min" type="number" step="0.01" value="0"></div>
      <div class="afield"><label>Max uses (0 = ∞)</label><input name="v_max" type="number" value="1"></div>
    </div>
    <div class="acols2">
      <div class="afield"><label>Expiry</label><input type="date" name="v_expiry" value="<?= date('Y-m-d', strtotime('+6 months')) ?>"></div>
      <div class="afield" style="display:flex;align-items:flex-end"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="v_first" value="1" checked> First order only</label></div>
    </div>
    <button class="abtn primary" type="submit">＋ Create voucher</button>
  </form>

  <?php if(!$vouchers): ?><div class="aempty">No vouchers yet.</div><?php else: ?>
  <div style="overflow-x:auto">
  <table class="atable"><thead><tr>
    <th>Code</th><th>Value</th><th>Bound to</th><th>Campaign</th><th>Used</th><th>Expiry</th><th>Status</th><th></th>
  </tr></thead><tbody>
  <?php $shown=0; foreach($vSorted as $code=>$v): if($shown++>=60) break;
    $used=(int)($v['used']??0); $max=(int)($v['max_uses']??1);
    $exp=trim((string)($v['expiry']??''));
    $isExpired = $exp!=='' && strtotime($exp)<time();
    $active = ($v['active']??true) && !$isExpired && ($max===0 || $used<$max);
  ?>
    <tr>
      <td><code style="font-size:12px"><?= htmlspecialchars($code) ?></code></td>
      <td><?= htmlspecialchars(voucher_label($v)) ?><?= !empty($v['first_order_only'])?' <span class="ahint">· 1st order</span>':'' ?></td>
      <td style="font-size:12px"><?= $v['email']!=='' ? htmlspecialchars($v['email']) : '<span class="ahint">anyone</span>' ?></td>
      <td style="font-size:12px"><?= htmlspecialchars((string)($v['campaign']??'')) ?></td>
      <td><?= $used ?><?= $max>0?' / '.$max:'' ?><?php if($used>0): $la=end($v['used_by']); ?><div class="ahint" style="font-size:11px"><?= htmlspecialchars((string)($la['ref']??'')) ?> · <?= eur((float)($la['amount']??0)) ?></div><?php endif; ?></td>
      <td style="font-size:12px"><?= $exp!==''?htmlspecialchars($exp):'—' ?></td>
      <td><?= $active?'<span style="color:#3fb27f">● active</span>':'<span class="ahint">○ '.($isExpired?'expired':($used>=$max&&$max>0?'used':'off')).'</span>' ?></td>
      <td style="white-space:nowrap">
        <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="_action" value="toggle_voucher"><input type="hidden" name="v_toggle" value="<?= htmlspecialchars($code) ?>"><button class="abtn" type="submit">on/off</button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars($code) ?>?')"><?= csrfField() ?><input type="hidden" name="_action" value="delete_voucher"><input type="hidden" name="v_del" value="<?= htmlspecialchars($code) ?>"><button class="abtn" type="submit">✕</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
  <?php if(count($vouchers)>60): ?><p class="ahint">Showing the 60 most recent of <?= count($vouchers) ?>.</p><?php endif; ?>
  <?php endif; ?>
  </div>
</div>

<div class="acols2">
<div class="acard">
  <div class="acard-hd"><h3>Create promo code</h3></div>
  <div class="acard-body">
  <form method="post" class="aform">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="create_promo">
    <div class="afield"><label>Code (blank = auto-generate)</label><input name="code" placeholder="SELLER-SUMMER26" style="text-transform:uppercase"></div>
    <div class="afield"><label>Description</label><input name="desc" placeholder="Early seller access"></div>
    <div class="afield"><label>Benefit</label>
      <select name="benefit">
        <option value="instant_kyb">Instant KYB approval</option>
        <option value="commission_free_3m">No registration fee</option>
        <option value="commission_free_6m">0% commission — 6 months</option>
        <option value="reduced_commission">1.75% commission (half rate) — 6 months</option>
        <option value="priority_listing">Priority listing placement</option>
      </select>
    </div>
    <div class="acols2">
      <div class="afield"><label>Expiry date</label><input type="date" name="expiry" value="2026-12-31"></div>
      <div class="afield"><label>Max uses (0=∞)</label><input type="number" name="max_uses" value="100"></div>
    </div>
    <button class="abtn primary" type="submit">＋ Generate code</button>
  </form>
  </div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>Seller Scout</h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:12px">Find brand sellers online — pre-built search links</p>
  <div class="afield" style="margin-bottom:12px"><label>Brand or category</label><input id="scout-q" placeholder="e.g. Lacoste, Tommy Hilfiger" oninput="updateLinks()"></div>
  <div style="display:flex;flex-direction:column;gap:7px">
    <a id="sl-google" href="#" target="_blank" class="abtn">🔍 Google — wholesale distributor EEA</a>
    <a id="sl-li" href="#" target="_blank" class="abtn">💼 LinkedIn People</a>
    <a id="sl-li2" href="#" target="_blank" class="abtn">🏢 LinkedIn Companies</a>
    <a id="sl-ep" href="#" target="_blank" class="abtn">🌍 Europages</a>
    <a id="sl-km" href="#" target="_blank" class="abtn">🗂 Kompass</a>
    <a id="sl-ig" href="#" target="_blank" class="abtn">📷 Instagram</a>
  </div>
  </div>
</div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>Active promo codes (<?= count($promos) ?>)</h3></div>
  <?php if(!$promos): ?><div class="aempty">No codes yet.</div>
  <?php else: ?>
  <div class="atscroll"><table class="atable">
    <?= arow(['Code','Description','Benefit','Expiry','Uses','Status','Invite link',''],true) ?>
    <?php foreach($promos as $c=>$p): $active=$p['active']??true; ?>
    <tr style="opacity:<?= $active?1:.4 ?>">
      <td class="ac"><b class="atag"><?= htmlspecialchars($c) ?></b></td>
      <td class="ac"><?= htmlspecialchars($p['desc']??'') ?></td>
      <td class="ac" style="font-size:11px"><?= htmlspecialchars(promo_benefit_label($p['benefit']??'')) ?></td>
      <td class="ac"><?= htmlspecialchars($p['expiry']??'—') ?></td>
      <td class="ac"><?= ($p['used']??0) ?>/<?= ($p['max_uses']??'∞') ?></td>
      <td class="ac"><?= abadge($active?'Active':'Paused',$active?'#1f9d63':'#888') ?></td>
      <td class="ac"><a href="/seller-invite?code=<?= urlencode($c) ?>" target="_blank" style="color:var(--acc);font-size:11px">…/seller-invite?code=<?= htmlspecialchars($c) ?></a></td>
      <td class="ac"><div style="display:flex;gap:4px">
        <?= fBtn($active?'Pause':'Enable','toggle_promo',['toggle_code'=>$c]) ?>
        <?= fBtn('Delete','delete_promo',['del_code'=>$c],'color:var(--bad);border-color:rgba(239,154,154,.3)','Delete code '.$c.'?') ?>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>

<script>
function updateLinks(){
  var q=document.getElementById('scout-q').value||'fashion wholesale';
  document.getElementById('sl-google').href='https://www.google.com/search?q='+encodeURIComponent(q+' wholesale distributor Europe');
  document.getElementById('sl-li').href='https://www.linkedin.com/search/results/people/?keywords='+encodeURIComponent(q+' wholesale');
  document.getElementById('sl-li2').href='https://www.linkedin.com/search/results/companies/?keywords='+encodeURIComponent(q+' wholesale');
  document.getElementById('sl-ep').href='https://www.europages.com/companies/'+encodeURIComponent(q.split(' ')[0])+'.html';
  document.getElementById('sl-km').href='https://www.kompass.com/searchinternational/search.html?text='+encodeURIComponent(q);
  document.getElementById('sl-ig').href='https://www.instagram.com/explore/tags/'+encodeURIComponent(q.replace(/\s+/g,'').toLowerCase())+'wholesale/';
}
updateLinks();
</script>


<?php // ══════════════════════════════════════════════════════ SELLER PROSPECTS
elseif($tab==='prospects'):
  $ldNew=count(array_filter($leads,fn($l)=>($l['status']??'new')==='new'));
  $ldContacted=count(array_filter($leads,fn($l)=>($l['status']??'')==='contacted'));
  $ldReplied=count(array_filter($leads,fn($l)=>($l['status']??'')==='replied'));
  $ldConverted=count(array_filter($leads,fn($l)=>($l['status']??'')==='converted'));
  $ldUnsub=count(array_filter($leads,fn($l)=>($l['status']??'')==='unsubscribed'));
  $sellerAccts=array_values(array_filter(auth_accounts(),fn($a)=>($a['type']??'')==='seller'));
  $mailTarget=(string)($_GET['mailfor']??'');
  $emCfg = $mailTarget!=='' ? vestra_seller_mail($mailTarget)
         : (is_readable(vestra_data_dir().'/email_settings.json')?json_decode((string)file_get_contents(vestra_data_dir().'/email_settings.json'),true):[]);
  if(!is_array($emCfg)) $emCfg=[];
  $emReady = $mailTarget!=='' ? vestra_seller_can_send($emCfg)
           : (!empty($emCfg['mail_enabled']) && ((($emCfg['smtp_host']??'')!=='' && ($emCfg['smtp_pass']??'')!=='') || ($emCfg['mail_api_key']??'')!==''));
  $mailTargetName = $mailTarget!=='' ? (($a0=array_values(array_filter($sellerAccts,fn($a)=>($a['id']??'')===$mailTarget))[0]??null) ? ($a0['company']??$a0['name']??'Seller') : 'Seller') : 'Platform (VESTRA)';
  $finderApi = vestra_cfg('finder_key','')!=='';   // optional Hunter/Anymailfinder key
  $finderOn  = true;                               // finding always works — free site-reading fallback
  $cronStatus = vestra_cron_status();
  $cronTodayCountry = vestra_cron_today_country();
  $aiOn = vestra_ai_key()!=='';
?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
  <?php
    $csteps=[
      ['1','Find buyers','🧭','Auto-discover + Scout',true],
      ['2','Get real emails','🔍',$finderApi?'API + reads sites':'Reads sites — no key',true],
      ['3','Your sender','📤',$emReady?'Sending ready':'Set up SMTP',$emReady],
      ['4','AI (optional)','✨',$aiOn?'AI connected':'Optional',$aiOn],
      ['5','Send one-by-one','▶','Live, personalised',false],
    ];
    foreach($csteps as $cs){ $cd=$cs[4];
      echo '<div style="flex:1;min-width:150px;border:1px solid '.($cd?'rgba(31,157,99,.45)':'var(--line)').';border-radius:11px;padding:10px 13px;background:var(--bg2)">'
        .'<div style="font-size:10.5px;color:'.($cd?'#1f9d63':'var(--mut)').';font-weight:600;letter-spacing:.03em">STEP '.$cs[0].($cd?' ✓':'').'</div>'
        .'<div style="font-weight:700;font-size:13px;margin-top:2px">'.$cs[2].' '.htmlspecialchars($cs[1]).'</div>'
        .'<div class="ahint" style="font-size:11px;margin-top:1px">'.htmlspecialchars($cs[3]).'</div></div>';
    }
  ?>
</div>
<p class="ahint" style="margin-bottom:16px;max-width:760px">
  Your <b>customer</b> list — the retailers, stores and buyers you want to sell to. Build it by
  <b>Auto-discover</b> (real shops from OpenStreetMap), your own research (Scout links, trade shows, directories),
  or a CSV you import. Emails come only from a company's <b>own public contact/imprint page</b> or a finder API —
  real addresses, never mass-scraped private data. Every outreach email carries a working one-click unsubscribe link;
  anyone who uses it is permanently excluded from future sends. Use the offer template below (or <i>Send a product offer</i>) to pitch them.
</p>

<div class="acard" style="margin-bottom:20px;border-color:rgba(31,157,99,.4)">
  <div class="acard-hd"><h3>🤖 Automation <span style="color:#1f9d63;font-size:12px;font-weight:600">● Runs daily at 09:00 (server cron)</span></h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:12px">This is the same search as <i>Find customers</i> below, just triggered automatically every morning instead of by hand — one country per day (today: <b><?= htmlspecialchars($cronTodayCountry) ?></b>), rotating so the same one isn't hit twice in a row. It only finds &amp; adds — sending always stays a separate, manual step.</p>
  <?php if($cronStatus): $ago=time()-strtotime($cronStatus['last_run']??'now');
    $agoTxt = $ago<120?'just now':($ago<3600?intdiv($ago,60).' min ago':($ago<86400?intdiv($ago,3600).' hr ago':intdiv($ago,86400).' day(s) ago')); ?>
  <div style="background:var(--bg2);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12.5px">
    <b>Last run:</b> <?= $agoTxt ?> (<?= htmlspecialchars(date('Y-m-d H:i',strtotime($cronStatus['last_run']))) ?>) — <?= ($cronStatus['trigger']??'cron')==='manual'?'started by you':'automatic' ?><br>
    Searched <b><?= htmlspecialchars($cronStatus['country']??'—') ?></b> — found <?= (int)($cronStatus['found']??0) ?>, added <?= (int)($cronStatus['added']??0) ?> new, resolved <?= (int)($cronStatus['emails_found']??0) ?>/<?= (int)($cronStatus['emails_checked']??0) ?> emails.
    <?php if(!empty($cronStatus['note'])): ?><div style="color:#c0392b;margin-top:4px">⚠ <?= htmlspecialchars($cronStatus['note']) ?></div>
    <?php elseif(($cronStatus['found']??0)===0): ?><div style="color:#c0392b;margin-top:4px">0 found — that country genuinely has little OSM shop data for the categories we search. Try "Run now" and watch the live log below.</div><?php endif; ?>
  </div>
  <?php else: ?>
  <div style="background:var(--bg2);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12.5px;color:var(--mut)">Never run yet — click "Run now" to try it immediately, or wait for tonight's 09:00 automatic run.</div>
  <?php endif; ?>
  <button class="abtn primary" type="button" onclick="runAutomationNow(this)">▶ Run now (<?= htmlspecialchars($cronTodayCountry) ?>)</button>
  </div>
</div>

<div class="acard" style="margin-bottom:20px;border-color:rgba(31,157,99,.4)">
  <div class="acard-hd"><h3>🎯 Find customers <span style="color:#1f9d63;font-size:12px;font-weight:600">● Free · no key needed</span></h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:12px">One button: finds <b>real small &amp; medium clothing / textile shops</b> across a whole country (independent boutiques &amp; multi-brand stores, not big chains or the brands' own flagship stores), adds them, then checks each new one for a real email — live, one row at a time, so you see exactly what worked and what didn't.</p>
  <div class="aform" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
    <div class="afield" style="margin:0"><label>Country</label>
      <select id="discCountry">
        <option value="" disabled selected>— choose —</option>
        <option>Germany</option><option>Netherlands</option><option>Poland</option><option>France</option><option>Italy</option>
        <option>Spain</option><option>United Kingdom</option><option>United States</option><option>Australia</option><option>UAE</option><option>Turkey</option>
      </select>
    </div>
    <div class="afield" style="margin:0;flex:1;min-width:200px"><label>City <span style="font-weight:400;color:var(--mut)">— optional, narrows the search</span></label><input id="discCity" placeholder="leave blank to search the whole country"></div>
    <button class="abtn primary" type="button" onclick="findCustomersLive(this)">🎯 Find customers</button>
  </div>
  <p class="ahint" style="margin-top:8px;font-size:11px">Whole-country searches take longer (up to ~60s) and may return nothing for very large countries — narrow to a city (local spelling, e.g. Milano not Milan) if that happens. Already have customers without an email (e.g. from a CSV import)? <a href="#" onclick="findMissingEmailsLive(this);return false" style="color:var(--acc)">🔍 Find their emails too</a>.</p>
  <div id="fcWrap" style="display:none;margin-top:10px;padding:10px 12px;background:var(--bg2);border-radius:8px">
    <div id="fcBar" style="font-weight:600;font-size:13px;margin-bottom:6px"></div>
    <div id="fcLog" style="max-height:260px;overflow:auto"></div>
  </div>
  <details style="margin-top:12px">
    <summary style="cursor:pointer;font-size:12px;color:var(--mut)">Optional: use your own Hunter.io / Anymailfinder key (raises the hit-rate; not required)</summary>
    <form method="post" class="aform" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-top:10px">
      <?= csrfField() ?><input type="hidden" name="_action" value="save_finder">
      <div class="afield" style="margin:0"><label>Provider</label><select name="finder_provider"><option value="hunter" <?= (vestra_cfg('finder_provider','hunter')==='hunter')?'selected':'' ?>>Hunter.io</option><option value="anymailfinder" <?= (vestra_cfg('finder_provider','')==='anymailfinder')?'selected':'' ?>>Anymailfinder</option></select></div>
      <div class="afield" style="margin:0;flex:1;min-width:240px"><label>API key <?= $finderApi?'<span class="ahint">· saved, blank = keep</span>':'' ?></label><input type="password" name="finder_key" placeholder="key…" autocomplete="new-password"></div>
      <button class="abtn" type="submit">Save key</button>
    </form>
  </details>
  </div>
</div>

<div class="acard" style="margin-bottom:20px">
  <div class="acard-hd"><h3>✨ AI personalisation (DeepSeek)
    <?= $aiOn?'<span style="color:#1f9d63;font-size:12px;font-weight:600">● Connected</span>':'<span style="color:#a9781a;font-size:12px;font-weight:600">● Add key</span>' ?></h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:10px">Tick <b>✨ AI personalize each</b> before a one-by-one send and every customer gets a tailored email (written from their company / country / segment). If your server already defines <code>DEEPSEEK_KEY</code> (shared with ChatHelp) it's used automatically — otherwise paste your DeepSeek key here. Stored web-blocked, never in git.</p>
  <form method="post" class="aform" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
    <?= csrfField() ?><input type="hidden" name="_action" value="save_ai">
    <div class="afield" style="margin:0;flex:1;min-width:240px"><label>DeepSeek API key <?= (vestra_cfg('ai_key','')!=='')?'<span class="ahint">· saved, blank = keep</span>':($aiOn?'<span class="ahint">· using server DEEPSEEK_KEY ✓</span>':'') ?></label><input type="password" name="ai_key" placeholder="sk-…" autocomplete="new-password"></div>
    <div class="afield" style="margin:0"><label>Model</label><input name="ai_model" value="<?= htmlspecialchars((string)vestra_cfg('ai_model','deepseek-chat')) ?>" style="width:150px"></div>
    <button class="abtn primary" type="submit">Save AI key</button>
  </form>
  </div>
</div>

<div class="acard" style="margin-bottom:20px;border-color:<?= $emReady?'rgba(31,157,99,.45)':'rgba(169,127,44,.5)' ?>">
  <div class="acard-hd"><h3>📤 Sending email — <?= htmlspecialchars($mailTargetName) ?>
    <?= $emReady?'<span style="color:#1f9d63;font-size:12px;font-weight:600">● Ready</span>':'<span style="color:#a9781a;font-size:12px;font-weight:600">● Not set up</span>' ?></h3></div>
  <div class="acard-body">
  <div class="afield" style="margin-bottom:14px"><label>Configure sending for</label>
    <select onchange="location.href='/admin?tab=prospects&mailfor='+encodeURIComponent(this.value)">
      <option value="" <?= $mailTarget===''?'selected':'' ?>>Platform (VESTRA) — default sender</option>
      <?php foreach($sellerAccts as $s): $sid=$s['id']??''; ?>
      <option value="<?= htmlspecialchars($sid) ?>" <?= $mailTarget===$sid?'selected':'' ?>><?= htmlspecialchars($s['company']??$s['name']??'Seller') ?><?= vestra_seller_can_send(vestra_seller_mail($sid))?'  ✓ set up':'' ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <p class="ahint" style="margin-bottom:12px">Mail for <b><?= htmlspecialchars($mailTargetName) ?></b> goes out <b>from this address</b>, one email per customer. Enter the email + its SMTP login (from the provider). <b>Gmail/Google:</b> turn on 2-step verification and use an <b>App Password</b>. Saved securely — web-blocked, never committed to git. Set one up <b>per seller</b> so each seller's offers send from their own address (best deliverability).</p>
  <form method="post" class="aform">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="save_email_settings">
    <input type="hidden" name="mail_enabled" value="1">
    <input type="hidden" name="target_uid" value="<?= htmlspecialchars($mailTarget) ?>">
    <div class="acols2">
      <div class="afield"><label>From email *</label><input type="email" name="from_email" required value="<?= htmlspecialchars($emCfg['mail_from']??'') ?>" placeholder="you@yourcompany.com"></div>
      <div class="afield"><label>From name</label><input name="from_name" value="<?= htmlspecialchars($emCfg['smtp_name']??'') ?>" placeholder="Your Company"></div>
    </div>
    <div class="afield"><label>Provider preset (auto-fills SMTP)</label>
      <select onchange="smtpPreset(this.value)">
        <option value="">— choose —</option>
        <option value="gmail">Gmail / Google Workspace</option>
        <option value="outlook">Outlook / Microsoft 365</option>
        <option value="custom">Other / custom host</option>
      </select>
    </div>
    <div class="acols2">
      <div class="afield"><label>SMTP host</label><input name="smtp_host" id="smtp_host" value="<?= htmlspecialchars($emCfg['smtp_host']??'') ?>" placeholder="smtp.gmail.com"></div>
      <div class="afield"><label>SMTP port</label><input name="smtp_port" id="smtp_port" value="<?= htmlspecialchars((string)($emCfg['smtp_port']??'587')) ?>" placeholder="587"></div>
    </div>
    <div class="acols2">
      <div class="afield"><label>SMTP username</label><input name="smtp_user" value="<?= htmlspecialchars($emCfg['smtp_user']??'') ?>" placeholder="usually your email"></div>
      <div class="afield"><label>SMTP password <?= ($emCfg['smtp_pass']??'')!==''?'<span class="ahint">· saved, blank = keep</span>':'' ?></label><input type="password" name="smtp_pass" placeholder="app password" autocomplete="new-password"></div>
    </div>
    <details style="margin:2px 0 12px">
      <summary class="ahint" style="cursor:pointer">Advanced: use a transactional API key instead (best inbox rate)</summary>
      <div class="acols2" style="margin-top:8px">
        <div class="afield"><label>Provider</label><select name="mail_api_provider"><option value="brevo" <?= ($emCfg['mail_api_provider']??'brevo')==='brevo'?'selected':'' ?>>Brevo</option><option value="resend" <?= ($emCfg['mail_api_provider']??'')==='resend'?'selected':'' ?>>Resend</option></select></div>
        <div class="afield"><label>API key <?= ($emCfg['mail_api_key']??'')!==''?'<span class="ahint">· saved, blank = keep</span>':'' ?></label><input type="password" name="mail_api_key" placeholder="xkeysib-… / re_…" autocomplete="new-password"></div>
      </div>
      <p class="ahint">If an API key is set it's used instead of SMTP. Your "from" address must be verified with the provider (adds SPF/DKIM for you → far fewer spam-folder landings).</p>
    </details>
    <button class="abtn primary" type="submit">Save sending email</button>
  </form>
  <form method="post" style="margin-top:14px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="send_test_email">
    <input type="hidden" name="target_uid" value="<?= htmlspecialchars($mailTarget) ?>">
    <div class="afield" style="margin:0;flex:1;min-width:220px"><label>Send a test email (from <?= htmlspecialchars($mailTargetName) ?>) to</label><input type="email" name="test_to" required value="<?= htmlspecialchars($emCfg['mail_from']??'') ?>" placeholder="your@email.com"></div>
    <button class="abtn" type="submit">✉ Send test</button>
  </form>
  </div>
</div>
<script>
function smtpPreset(v){
  var h=document.getElementById('smtp_host'), p=document.getElementById('smtp_port');
  if(v==='gmail'){ h.value='smtp.gmail.com'; p.value='587'; }
  else if(v==='outlook'){ h.value='smtp.office365.com'; p.value='587'; }
}
</script>

<div class="asgrid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="ascard"><div class="sv"><?= $ldNew ?></div><div class="sl">New</div></div>
  <div class="ascard"><div class="sv" style="color:#3366cc"><?= $ldContacted ?></div><div class="sl">Contacted</div></div>
  <div class="ascard"><div class="sv" style="color:#a9781a"><?= $ldReplied ?></div><div class="sl">Replied</div></div>
  <div class="ascard"><div class="sv" style="color:#1f9d63"><?= $ldConverted ?></div><div class="sl">Converted</div></div>
  <div class="ascard"><div class="sv" style="color:#555"><?= $ldUnsub ?></div><div class="sl">Unsubscribed</div></div>
</div>

<div class="acols2">
<div class="acard">
  <div class="acard-hd"><h3>Add a prospect</h3></div>
  <div class="acard-body">
  <form method="post" class="aform">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="add_lead">
    <div class="acols2">
      <div class="afield"><label>Company *</label><input name="company" required placeholder="Nordic Streetwear AB"></div>
      <div class="afield"><label>Email *</label><input type="email" name="email" required placeholder="sales@company.com"></div>
    </div>
    <div class="acols2">
      <div class="afield"><label>Contact name</label><input name="contact_name" placeholder="Optional"></div>
      <div class="afield"><label>Country</label><input name="country" placeholder="e.g. Sweden"></div>
    </div>
    <div class="acols2">
      <div class="afield"><label>Website</label><input name="website" placeholder="Optional"></div>
      <div class="afield"><label>Source</label>
        <select name="source">
          <?php foreach(vestra_lead_sources() as $s): ?><option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="afield"><label>Category / notes</label><input name="category" placeholder="e.g. denim, streetwear brands"></div>
    <button class="abtn primary" type="submit">＋ Add prospect</button>
  </form>
  </div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>Import CSV</h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:12px">Header row required. Only <code>company</code> is mandatory — <code>email,contact_name,country,website,source,category,notes</code> are optional. A web-research list with no emails still imports (rows load as "＋ Add email" so you can enrich and then send). Dupes are skipped by email, or by company when there's no email.</p>
  <form method="post" enctype="multipart/form-data" class="aform">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="import_leads_csv">
    <div class="afield"><input type="file" name="csv" accept=".csv,text/csv" required></div>
    <button class="abtn primary" type="submit">⬆ Import</button>
  </form>
  <a class="ahint" style="display:inline-block;margin-top:10px" download="vestra-prospects-sample.csv" href="data:text/csv;charset=utf-8,company%2Cemail%2Ccontact_name%2Ccountry%2Cwebsite%2Csource%2Ccategory%2Cnotes%0ANordic%20Streetwear%20AB%2Csales%40nordic.example%2CAnna%2CSweden%2Cnordic.example%2CReferral%2Cstreetwear%2CReorders%20quarterly">⬇ Download sample CSV</a>
  </div>
</div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>Outreach email template</h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:12px">Placeholders: <code>{{company}}</code> <code>{{contact_name}}</code> <code>{{country}}</code>. A sender-identification + unsubscribe footer is appended automatically to every send and can't be removed. Every email goes out as a branded HTML card (with a plain-text fallback) — add an image any time to make it feel more premium; leave it out any time too.</p>
  <form method="post" class="aform" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="save_lead_template">
    <input type="hidden" name="tpl_img_keep" value="<?= htmlspecialchars($leadTpl['img']) ?>">
    <div class="afield"><label>Subject</label><input name="tpl_subject" value="<?= htmlspecialchars($leadTpl['subject']) ?>"></div>
    <div class="afield"><label>Body</label><textarea name="tpl_body" rows="8"><?= htmlspecialchars($leadTpl['body']) ?></textarea></div>
    <div class="afield"><label>Header image <span style="font-weight:400;color:var(--mut)">— optional, shown at the top of the HTML email</span></label>
      <?php if($leadTpl['img']!==''): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
          <img src="<?= htmlspecialchars($leadTpl['img']) ?>" style="height:52px;border-radius:6px;border:1px solid var(--line)">
          <label style="font-size:12px;color:var(--mut);display:flex;align-items:center;gap:5px;cursor:pointer"><input type="checkbox" name="tpl_img_clear" value="1"> Remove image</label>
        </div>
      <?php endif; ?>
      <input type="file" name="tpl_img" accept="image/png,image/jpeg,image/webp,image/gif">
    </div>
    <button class="abtn primary" type="submit">Save template</button>
  </form>
  </div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>👁 Email preview — exactly what each customer receives</h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:10px">Live render of your saved outreach (sample customer “Bodega”). Placeholders are filled per-recipient and the required sender + one-click unsubscribe footer is added automatically. One personalised email is sent per customer.</p>
  <?php
    $pv=vestra_lead_render_email(['company'=>'Bodega','contact_name'=>'Ali','country'=>'United States','unsub_token'=>'preview'],$leadTpl);
    $pvImg=$leadTpl['img']!==''?'https://vestrasales.com'.$leadTpl['img']:'';
    $pvHtml=vestra_html_email($pv[1],$pvImg);
  ?>
  <div style="font-size:12px;color:var(--mut);margin-bottom:8px">Subject:&nbsp; <b style="color:var(--ink)"><?= htmlspecialchars($pv[0]) ?></b></div>
  <iframe srcdoc="<?= htmlspecialchars($pvHtml) ?>" style="width:100%;height:640px;border:1px solid var(--line);border-radius:10px;background:#f4f2ee"></iframe>
  <details style="margin-top:10px">
    <summary style="cursor:pointer;font-size:12px;color:var(--mut)">Plain-text fallback (shown to clients that can't render HTML)</summary>
    <pre style="white-space:pre-wrap;font-family:inherit;font-size:12.5px;line-height:1.55;color:var(--ink);margin:8px 0 0;background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:14px 16px"><?= htmlspecialchars($pv[1]) ?></pre>
  </details>
  </div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>Send a product offer</h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:12px">Email a tailored wholesale offer — selected products + live prices — straight to a customer. Logged to <code>quotes.csv</code>. If the email matches a saved prospect their unsubscribe link is used, and opt-outs are never emailed.</p>
  <form method="post" class="aform" onsubmit="return confirm('Send this product offer to the customer?')">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="send_quote">
    <div class="acols2">
      <div class="afield"><label>Customer email *</label><input type="email" name="q_email" id="q_email" required placeholder="buyer@company.com" list="prospectEmails"></div>
      <div class="afield"><label>Company</label><input name="q_company" id="q_company" placeholder="Optional"></div>
    </div>
    <datalist id="prospectEmails"><?php foreach($leads as $l){ if(($l['status']??'')==='unsubscribed') continue; echo '<option data-company="'.htmlspecialchars($l['company']??'').'" data-contact="'.htmlspecialchars($l['contact_name']??'').'" value="'.htmlspecialchars($l['email']??'').'">'; } ?></datalist>
    <div class="afield"><label>Contact name</label><input name="q_contact" id="q_contact" placeholder="Optional"></div>
    <div class="afield"><label>Products *</label>
      <input type="text" onkeyup="quoteFilter(this.value)" placeholder="Filter products…" style="margin-bottom:6px">
      <div style="max-height:220px;overflow:auto;border:1px solid var(--line);border-radius:8px;padding:4px">
        <?php foreach(vestra_products() as $qp): if(empty($qp['brand'])) continue; $qfp=vestra_from_price($qp); ?>
        <label class="qprow" style="display:flex;gap:8px;align-items:center;padding:4px 6px;font-size:12.5px;cursor:pointer">
          <input type="checkbox" name="q_products[]" value="<?= htmlspecialchars($qp['id']??'') ?>">
          <span><b><?= htmlspecialchars($qp['brand']) ?></b> <?= htmlspecialchars($qp['name']??'') ?><?php if($qfp>0): ?> · <span class="ahint">from €<?= rtrim(rtrim(number_format($qfp,2),'0'),'.') ?><?= ($qp['mode']??'')==='sale'?' (sale)':'' ?></span><?php endif; ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="afield"><label>Send on behalf of</label>
      <select name="q_seller_uid">
        <option value="">Platform (VESTRA default sender)</option>
        <?php foreach($sellerAccts as $s): $sid=$s['id']??''; $ok=vestra_seller_can_send(vestra_seller_mail($sid)); ?>
        <option value="<?= htmlspecialchars($sid) ?>" <?= $ok?'':'disabled' ?>><?= htmlspecialchars($s['company']??$s['name']??'Seller') ?><?= $ok?' ✓':' — set up email first' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="afield"><label>Message (optional)</label><textarea name="q_note" rows="3" placeholder="e.g. Prices valid 14 days · mixed-size cartons available · ask for a full size breakdown."></textarea></div>
    <button class="abtn primary" type="submit">✉ Send offer</button>
  </form>
  </div>
</div>
<script>
function quoteFilter(q){ q=(q||'').toLowerCase(); document.querySelectorAll('.qprow').forEach(function(r){ r.style.display=r.textContent.toLowerCase().indexOf(q)>=0?'':'none'; }); }
document.addEventListener('DOMContentLoaded',function(){
  var e=document.getElementById('q_email'); if(!e) return;
  e.addEventListener('change',function(){
    var opts=document.querySelectorAll('#prospectEmails option'), c=document.getElementById('q_company'), n=document.getElementById('q_contact');
    opts.forEach(function(o){ if(o.value.toLowerCase()===e.value.toLowerCase()){
      if(c&&!c.value) c.value=o.getAttribute('data-company')||''; if(n&&!n.value) n.value=o.getAttribute('data-contact')||''; } });
  });
});
</script>

<form method="post" id="leadRowForm" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="_action" id="lrf_action">
  <input type="hidden" name="lid" id="lrf_lid">
  <input type="hidden" name="status" id="lrf_status">
  <input type="hidden" name="email" id="lrf_email">
</form>
<script>
function leadSetStatus(lid,status){
  document.getElementById('lrf_action').value='update_lead_status';
  document.getElementById('lrf_lid').value=lid;
  document.getElementById('lrf_status').value=status;
  document.getElementById('leadRowForm').submit();
}
function leadSetEmail(lid,current){
  var e=prompt('Email for this prospect:',current||''); if(e===null) return; e=e.trim(); if(!e) return;
  document.getElementById('lrf_action').value='set_lead_email';
  document.getElementById('lrf_lid').value=lid;
  document.getElementById('lrf_email').value=e;
  document.getElementById('leadRowForm').submit();
}
function leadFindEmail(lid){
  document.getElementById('lrf_action').value='find_lead_email';
  document.getElementById('lrf_lid').value=lid;
  document.getElementById('leadRowForm').submit();
}
function leadDelete(lid){
  if(!confirm('Delete this prospect?')) return;
  document.getElementById('lrf_action').value='delete_lead';
  document.getElementById('lrf_lid').value=lid;
  document.getElementById('leadRowForm').submit();
}
function leadToggleAll(box){
  document.querySelectorAll('.leadchk').forEach(function(c){ if(!c.disabled) c.checked=box.checked; });
}
function leadBulkDelete(form){
  var boxes=[].slice.call(document.querySelectorAll('.leadchk')).filter(function(c){return c.checked;});
  if(!boxes.length){ alert('Select at least one prospect (checkbox) first.'); return; }
  if(!confirm('Delete '+boxes.length+' selected prospect(s)? This cannot be undone.')) return;
  form.querySelector('[name="_action"]').value='delete_leads_bulk';
  form.submit();
}
/* Shared live queue runner: POSTs find_lead_email_one for each id, one at a time, logging
 * each result as it comes back. Used both right after a fresh discovery and for "find emails
 * for customers I already have" (e.g. a CSV import that had no emails). */
function runEmailFinderQueue(ids, log, onStep, onDone){
  var i=0, ok=0, fail=0;
  function next(){
    if(i>=ids.length){ onDone(ok,fail,ids.length); return; }
    onStep(i+1, ids.length);
    var fd=new FormData(); fd.append('_action','find_lead_email_one'); fd.append('_csrf',VADMIN_CSRF); fd.append('lid',ids[i]);
    fetch('/admin',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
      var line=document.createElement('div'); line.style.fontSize='12px'; line.style.padding='2px 0';
      if(d.ok){ ok++; line.style.color='#1f9d63'; line.innerHTML='✓ '+(d.company||'')+' <span style="color:var(--mut)">'+(d.email||'')+'</span>'; }
      else { fail++; line.style.color='#c0392b'; var why=d.error==='nowebsite'?'no website':'not found on site'; line.innerHTML='✗ '+(d.company||d.website||'')+' — '+why; }
      log.appendChild(line); log.scrollTop=log.scrollHeight; i++; setTimeout(next,150);
    }).catch(function(){ fail++; i++; setTimeout(next,150); });
  }
  next();
}
function findCustomersLive(btn){
  var country=document.getElementById('discCountry').value||'';
  if(!country){ alert('Choose a country first.'); return; }
  var city=(document.getElementById('discCity').value||'').trim();
  var wrap=document.getElementById('fcWrap'), bar=document.getElementById('fcBar'), log=document.getElementById('fcLog');
  wrap.style.display='block'; log.innerHTML=''; btn.disabled=true;
  bar.textContent='Searching '+(city?city+', '+country:'all of '+country)+'… (whole-country searches can take up to a minute)';
  var fd=new FormData(); fd.append('_action','discover_leads'); fd.append('_csrf',VADMIN_CSRF); fd.append('disc_country',country); fd.append('disc_city',city);
  fetch('/admin',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    if(d.osm_ok===false){
      var warn=document.createElement('div'); warn.style.fontSize='12px'; warn.style.fontWeight='600'; warn.style.color='#c0392b'; warn.style.padding='2px 0';
      warn.textContent='⚠ OpenStreetMap unreachable (all mirrors failed) — today\'s results may be incomplete. Try again in a minute.';
      log.appendChild(warn);
    }
    var line=document.createElement('div'); line.style.fontSize='12px'; line.style.fontWeight='600'; line.style.padding='2px 0';
    line.textContent=(d.total||0)+' shop(s) found, '+(d.added||0)+' new added to your customers.';
    log.appendChild(line);
    /* Sunucunun yazdigi sebep. Ciplak "0 bulundu" kullaniciyi "burada butik yok"
       sanmaya itiyordu; asil sebep neredeyse her zaman sorgunun agir gelmesi. */
    if(d.note){
      var n=document.createElement('div'); n.style.fontSize='12px'; n.style.padding='4px 0';
      n.style.color=d.timed_out?'#c0392b':'#8a6d1f';
      n.textContent=(d.timed_out?'⚠ ':'ℹ ')+d.note;
      log.appendChild(n);
    }
    var ids=d.newIds||[];
    if(!ids.length){
      bar.textContent = d.timed_out ? '✗ Overpass timed out — add a city and retry.'
                      : d.osm_ok===false ? '✗ OSM search failed — try again.'
                      : (d.total||0)===0 ? '✗ No shops found — see the note above.'
                      : '✓ Done — no new customers needed an email lookup.';
      btn.disabled=false; return;
    }
    runEmailFinderQueue(ids, log,
      function(i,n){ bar.textContent='Checking emails '+i+' / '+n+'…'; },
      function(ok,fail,n){ bar.textContent='✓ Done — '+ok+' email(s) found, '+fail+' not found, of '+n+' new customers. Refresh to see them.'; btn.disabled=false; });
  }).catch(function(){ bar.textContent='✗ Search failed — check your connection and try again.'; btn.disabled=false; });
}
function findMissingEmailsLive(btn){
  var rows=[].slice.call(document.querySelectorAll('tr[data-findable="1"]'));
  var ids=rows.map(function(r){return r.getAttribute('data-id');});
  if(!ids.length){ alert('No email-less customers with a website to look up.'); return; }
  var wrap=document.getElementById('fcWrap'), bar=document.getElementById('fcBar'), log=document.getElementById('fcLog');
  wrap.style.display='block'; log.innerHTML=''; btn.disabled=true;
  bar.textContent='Checking emails 1 / '+ids.length+'…';
  runEmailFinderQueue(ids, log,
    function(i,n){ bar.textContent='Checking emails '+i+' / '+n+'…'; },
    function(ok,fail,n){ bar.textContent='✓ Done — '+ok+' found, '+fail+' not found, of '+n+'. Refresh to see them.'; btn.disabled=false; });
}
/* "Run now" on the Automation card — exactly what tonight's 09:00 cron does (today's
 * rotation country, whole-country search), just triggered by a click instead of the clock,
 * with the same live log. Records the result so the card's "last run" reflects this click. */
function runAutomationNow(btn){
  var country=<?= json_encode($cronTodayCountry) ?>;
  var wrap=document.getElementById('fcWrap'), bar=document.getElementById('fcBar'), log=document.getElementById('fcLog');
  wrap.style.display='block'; log.innerHTML=''; btn.disabled=true;
  wrap.scrollIntoView({behavior:'smooth',block:'center'});
  bar.textContent='Running today\'s automation — '+country+'… (whole-country searches can take up to a minute)';
  var fd=new FormData(); fd.append('_action','discover_leads'); fd.append('_csrf',VADMIN_CSRF); fd.append('disc_country',country); fd.append('disc_city','');
  var record=function(emailsFound,emailsChecked,total,added,osmOk){
    var fd2=new FormData(); fd2.append('_action','record_automation_result'); fd2.append('_csrf',VADMIN_CSRF);
    fd2.append('country',country); fd2.append('found',total); fd2.append('added',added);
    fd2.append('emails_found',emailsFound); fd2.append('emails_checked',emailsChecked);
    fd2.append('osm_ok',osmOk===false?'0':'1');
    fetch('/admin',{method:'POST',body:fd2}).then(function(){ setTimeout(function(){ location.reload(); },1200); });
  };
  fetch('/admin',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    if(d.osm_ok===false){
      var warn=document.createElement('div'); warn.style.fontSize='12px'; warn.style.fontWeight='600'; warn.style.color='#c0392b'; warn.style.padding='2px 0';
      warn.textContent='⚠ OpenStreetMap unreachable (all mirrors failed) — today\'s results may be incomplete.';
      log.appendChild(warn);
    }
    var line=document.createElement('div'); line.style.fontSize='12px'; line.style.fontWeight='600'; line.style.padding='2px 0';
    line.textContent=(d.total||0)+' shop(s) found, '+(d.added||0)+' new added to your customers.';
    log.appendChild(line);
    var ids=d.newIds||[];
    if(!ids.length){ bar.textContent=(d.osm_ok===false?'✗ OSM search failed.':'✓ Done — no new customers needed an email lookup.')+' Refreshing…'; record(0,0,d.total||0,d.added||0,d.osm_ok); return; }
    runEmailFinderQueue(ids, log,
      function(i,n){ bar.textContent='Checking emails '+i+' / '+n+'…'; },
      function(ok,fail,n){ bar.textContent='✓ Done — '+ok+' email(s) found, '+fail+' not found. Refreshing…'; record(ok,n,d.total||0,d.added||0,d.osm_ok); });
  }).catch(function(){ bar.textContent='✗ Search failed — check your connection and try again.'; btn.disabled=false; });
}
</script>

<div class="acard">
  <div class="acard-hd"><h3>Prospects (<?= count($leads) ?>)</h3></div>
  <?php if(!$leads): ?><div class="aempty">No prospects yet — add one or import a CSV above.</div>
  <?php else: ?>
  <form method="post" onsubmit="return confirm('Send the outreach email to the selected prospect(s)?')">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="send_lead_email">
    <div style="padding:14px 18px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <button class="abtn primary" type="submit">✉ Send invite to selected</button>
      <button type="button" class="abtn" onclick="sendOneByOne(this)">▶ Send one-by-one (live)</button>
      <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--mut)" title="Personalise each email with AI (DeepSeek)"><input type="checkbox" id="aiPersonalize" <?= $aiOn?'':'disabled' ?>> ✨ AI personalize<?= $aiOn?'':' (add key ↑)' ?></label>
      <select name="l_seller_uid" style="background:var(--bg);color:var(--ink);border:1px solid var(--line);border-radius:6px;padding:5px 8px;font-size:12px">
        <option value="">From: Platform (VESTRA)</option>
        <?php foreach($sellerAccts as $s): $sid=$s['id']??''; $ok=vestra_seller_can_send(vestra_seller_mail($sid)); ?>
        <option value="<?= htmlspecialchars($sid) ?>" <?= $ok?'':'disabled' ?>>From: <?= htmlspecialchars($s['company']??$s['name']??'Seller') ?><?= $ok?'':' (set up first)' ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="abtn" style="color:var(--bad);border-color:rgba(239,154,154,.3)" onclick="leadBulkDelete(this.form)">🗑 Delete selected</button>
      <span class="ahint">Send: max 50 · unsubscribed/email-less/already-emailed are safely skipped (no auto-resend to the same prospect) · pick a seller to send from their address. Delete: any selected row, no limit.</span>
    </div>
    <div id="sobWrap" style="display:none;padding:12px 18px;border-bottom:1px solid var(--line);background:var(--bg2)">
      <div id="sobBar" style="font-weight:600;font-size:13px;margin-bottom:8px"></div>
      <div id="sobLog" style="max-height:230px;overflow:auto"></div>
    </div>
    <script>
    var VADMIN_CSRF=<?= json_encode($_SESSION['vadmin_csrf']??'') ?>;
    function sendOneByOne(btn){
      var boxes=[].slice.call(document.querySelectorAll('.leadchk')).filter(function(c){return c.checked && !c.disabled;});
      if(!boxes.length){ alert('Select at least one customer (checkbox) first.'); return; }
      var ids=boxes.map(function(c){return c.value;});
      var sel=document.querySelector('[name=l_seller_uid]'); var seller=sel?sel.value:'';
      var aiEl=document.getElementById('aiPersonalize'); var ai=(aiEl&&aiEl.checked)?'1':'';
      var wrap=document.getElementById('sobWrap'), bar=document.getElementById('sobBar'), log=document.getElementById('sobLog');
      wrap.style.display='block'; log.innerHTML=''; btn.disabled=true;
      var i=0, ok=0, fail=0, skip=0;
      function next(){
        if(i>=ids.length){ bar.textContent='✓ Done — '+ok+' sent, '+skip+' already emailed (skipped), '+fail+' failed of '+ids.length+'. Refresh to see updated statuses.'; btn.disabled=false; return; }
        bar.textContent='Sending '+(i+1)+' / '+ids.length+'…';
        var fd=new FormData(); fd.append('_action','send_lead_one'); fd.append('_csrf',VADMIN_CSRF); fd.append('lead_id',ids[i]); fd.append('l_seller_uid',seller); fd.append('ai',ai);
        fetch('/admin',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
          var line=document.createElement('div'); line.style.fontSize='12px'; line.style.padding='2px 0';
          if(d.ok){ ok++; line.style.color='#1f9d63'; line.innerHTML='✓ '+(d.company||d.email||'')+' <span style="color:var(--mut)">'+(d.email||'')+'</span>'; }
          else if(d.error==='already_sent'){ skip++; line.style.color='var(--mut)'; line.innerHTML='– '+(d.company||d.email||'')+' <span style="color:var(--mut)">already emailed, skipped</span>'; }
          else if(d.error==='blocked'){ skip++; line.style.color='var(--mut)'; line.innerHTML='– '+(d.company||d.email||'')+' <span style="color:var(--mut)">big chain / brand store, skipped</span>'; }
          else { fail++; line.style.color='#c0392b'; line.innerHTML='✗ '+(d.company||d.email||'')+' — '+(d.error||'failed'); }
          log.appendChild(line); log.scrollTop=log.scrollHeight; i++; setTimeout(next,250);
        }).catch(function(){ fail++; i++; setTimeout(next,250); });
      }
      next();
    }
    </script>
    <div class="atscroll"><table class="atable">
      <tr><th class="ac"><input type="checkbox" onclick="leadToggleAll(this)"></th><th class="ac">Company</th><th class="ac">Contact</th><th class="ac">Email</th><th class="ac">Country</th><th class="ac">Source</th><th class="ac">Category</th><th class="ac">Status</th><th class="ac">Last contacted</th><th class="ac"></th></tr>
      <?php
        // Premium-brand-selling boutiques float to the top (they're the best VESTRA targets);
        // newest-first order is preserved within each group. `premium` is set by the site-scan.
        $leadsView=array_reverse($leads);
        usort($leadsView, fn($a,$b)=>(!empty($b['premium'])?1:0)-(!empty($a['premium'])?1:0));
      ?>
      <?php foreach($leadsView as $l): $unsub=($l['status']??'')==='unsubscribed'; $noEmail=!filter_var($l['email']??'',FILTER_VALIDATE_EMAIL); $alreadySent=($l['last_contacted_at']??'')!==''; $findable=($noEmail && !$unsub && !empty($l['website'])); $prem=!empty($l['premium']); $premBrands=implode(', ', array_map(fn($b)=>ucwords((string)$b), (array)($l['premium_brands']??[]))); ?>
      <tr style="opacity:<?= $unsub?.5:($noEmail?.72:1) ?>" data-id="<?= htmlspecialchars($l['id']??'') ?>" data-findable="<?= $findable?'1':'0' ?>">
        <td class="ac"><input class="leadchk" type="checkbox" name="lead_ids[]" value="<?= htmlspecialchars($l['id']??'') ?>" title="<?= ($unsub||$noEmail)?'Send skips this one automatically — still selectable to delete':($alreadySent?'Already emailed — send skips it automatically (no auto-resend), still selectable to delete':'') ?>"></td>
        <td class="ac"><b><?= htmlspecialchars($l['company']??'') ?></b><?php if($prem): ?> <span title="Premium markalar tespit edildi: <?= htmlspecialchars($premBrands?:'—') ?>" style="display:inline-block;font-size:9px;font-weight:700;letter-spacing:.04em;color:#8a6420;background:rgba(201,168,106,.16);border:1px solid rgba(201,168,106,.5);border-radius:5px;padding:1px 5px;vertical-align:middle">★ PREMIUM</span><?php endif; ?><?php if(!empty($l['website'])): ?><div class="ahint"><?= htmlspecialchars($l['website']) ?></div><?php endif; ?></td>
        <td class="ac"><?= htmlspecialchars($l['contact_name']??'') ?: '—' ?></td>
        <td class="ac" style="font-size:11px"><?php if(!$noEmail): ?><span style="cursor:pointer" title="Click to edit" onclick="leadSetEmail('<?= htmlspecialchars($l['id']??'') ?>','<?= htmlspecialchars($l['email']) ?>')"><?= htmlspecialchars($l['email']) ?></span><?php elseif(!$unsub): ?><button type="button" class="abtn" style="font-size:10.5px;padding:2px 7px" onclick="leadSetEmail('<?= htmlspecialchars($l['id']??'') ?>','')">＋ Add email</button><?php if($finderOn && !empty($l['website'])): ?> <button type="button" class="abtn" style="font-size:10.5px;padding:2px 7px" onclick="leadFindEmail('<?= htmlspecialchars($l['id']??'') ?>')" title="Look up a verified email from the website">🔍 Find</button><?php endif; ?><?php else: ?>—<?php endif; ?></td>
        <td class="ac"><?= htmlspecialchars($l['country']??'') ?: '—' ?></td>
        <td class="ac" style="font-size:11px"><?= htmlspecialchars($l['source']??'') ?></td>
        <td class="ac" style="font-size:11px"><?= htmlspecialchars($l['category']??'') ?: '—' ?></td>
        <td class="ac">
          <?php if($unsub): ?><?= abadge('Unsubscribed','#555') ?>
          <?php else: ?>
          <select onchange="leadSetStatus('<?= htmlspecialchars($l['id']??'') ?>',this.value)" style="background:var(--bg);color:var(--ink);border:1px solid var(--line);border-radius:6px;padding:3px 6px;font-size:11px">
            <?php foreach(VESTRA_LEAD_STATUSES as $s): ?><option value="<?= $s ?>" <?= ($l['status']??'new')===$s?'selected':'' ?>><?= vestra_lead_status_label($s) ?></option><?php endforeach; ?>
          </select>
          <?php endif; ?>
        </td>
        <?php /* Leads imported before this field existed have no key at all. */ ?>
        <td class="ac" style="font-size:11px"><?= !empty($l['last_contacted_at']) ? htmlspecialchars(substr((string)$l['last_contacted_at'],0,10)) : '—' ?></td>
        <td class="ac"><button type="button" class="abtn" style="color:var(--bad);border-color:rgba(239,154,154,.3)" onclick="leadDelete('<?= htmlspecialchars($l['id']??'') ?>')">Delete</button></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
  </form>
  <?php endif; ?>
</div>


<?php // ══════════════════════════════════════════════════════ GROUP BUYS
elseif($tab==='groups'):
  $cnt_open   = count(array_filter($groupPools,fn($p)=>$p['_status']==='open'));
  $cnt_funded = count(array_filter($groupPools,fn($p)=>$p['_status']==='funded'));
  $cnt_exp    = count(array_filter($groupPools,fn($p)=>$p['_status']==='expired'));
?>
<div class="asgrid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv"><?= count($groupPools) ?></div><div class="sl">Pools</div></div>
  <div class="ascard"><div class="sv" style="color:#a9781a"><?= $cnt_open ?></div><div class="sl">Open</div></div>
  <div class="ascard"><div class="sv" style="color:#1f9d63"><?= $cnt_funded ?></div><div class="sl">Target reached</div></div>
  <div class="ascard"><div class="sv" style="color:var(--mut)"><?= $cnt_exp ?></div><div class="sl">Expired</div></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=groups">⬇ CSV</a></div>
<?php if(!$groupPools): ?><div class="acard"><div class="aempty">No products are open for group buying yet.</div></div>
<?php else: foreach($groupPools as $gp): ?>
<div class="acard" style="margin-bottom:12px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-weight:600"><?= htmlspecialchars($gp['brand']??'') ?> — <?= htmlspecialchars($gp['name']??'') ?>
        <a href="/group?id=<?= urlencode($gp['id']??'') ?>" target="_blank" style="color:var(--acc);font-size:11px;margin-left:8px">View page ↗</a></div>
      <div class="ahint"><?= number_format($gp['_committed']) ?> / <?= number_format($gp['_target']) ?> <?= htmlspecialchars($gp['unit']??'pc') ?>
        · <?= $gp['_pct'] ?>% · <?= (int)$gp['_participants'] ?> buyers
        · unlocks <?= eur($gp['_gprice']) ?>/<?= htmlspecialchars($gp['unit']??'pc') ?>
        · closes <?= htmlspecialchars(substr($gp['_deadline']??'',0,10)) ?></div>
    </div>
    <?= match($gp['_status']){'funded'=>abadge('✓ Target reached','#1f9d63'),'expired'=>abadge('• Expired','#888'),default=>abadge('⏳ Open · '.$gp['_daysLeft'].'d left','#a9781a')} ?>
  </div>
  <?php if($gp['_commits']): ?>
  <div class="acard-body"><div class="atscroll"><table class="atable">
    <?= arow(['Date','Ref','Company','Email','Country','Qty','Est. total'],true) ?>
    <?php foreach($gp['_commits'] as $c): ?>
    <?= arow([
      htmlspecialchars(substr($c['timestamp']??'',0,10)),
      '<span class="atag">'.htmlspecialchars($c['ref']??'').'</span>',
      '<b>'.htmlspecialchars($c['company']??'—').'</b>',
      '<a href="mailto:'.htmlspecialchars($c['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($c['email']??'').'</a>',
      htmlspecialchars($c['country']??'—'),
      htmlspecialchars($c['qty']??'').' '.htmlspecialchars($gp['unit']??'pc'),
      eur($c['est_total']??0),
    ]) ?>
    <?php endforeach; ?>
  </table></div></div>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>


<?php // ══════════════════════════════════════════════════════ MESSAGES (moderation)
elseif($tab==='messages'):
  $accById=[]; foreach($accounts as $a){ $accById[$a['id']??'']=$a; }
  $accLabel=function(string $uid) use ($accById): string {
    if($uid===VESTRA_SUPPORT_UID) return 'VESTRA Support';
    $a=$accById[$uid]??null;
    return $a ? (($a['company']?:($a['name']?:$uid))) : $uid;
  };
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div><h2 style="font-size:18px;font-weight:700">Message Moderation</h2>
  <p class="ahint" style="margin-top:4px">Buyer ↔ seller conversations. Off-platform contact (email / IBAN) is auto-blocked — attempts are logged below.</p></div>
</div>

<?php if($blockedMsgs): ?>
<div class="acard" style="margin-bottom:16px;border-color:rgba(239,154,154,.35)">
  <div class="acard-hd"><h3 style="color:#c0392b">⚠️ Blocked off-platform attempts (<?= count($blockedMsgs) ?>)</h3></div>
  <div class="acard-body"><div class="atscroll"><table class="atable">
    <?= arow(['When','Sender','Thread','Type','Attempted text'],true) ?>
    <?php foreach(array_reverse($blockedMsgs) as $bm): ?>
    <?= arow([
      htmlspecialchars(substr($bm['at']??'',0,16)),
      '<b>'.htmlspecialchars($accLabel($bm['from']??'')).'</b>',
      htmlspecialchars($accLabel($bm['buyer_uid']??'')).' ↔ '.htmlspecialchars($accLabel($bm['seller_uid']??'')),
      abadge(strtoupper($bm['flag']??''),'#c0392b'),
      '<span style="font-size:11px;color:var(--mut)">'.htmlspecialchars(mb_substr($bm['text']??'',0,120)).'</span>',
    ]) ?>
    <?php endforeach; ?>
  </table></div></div>
</div>
<?php endif; ?>

<?php if(!$msgThreads): ?><div class="acard"><div class="aempty">No conversations yet. Buyer-seller chats appear here.</div></div>
<?php else:
  usort($msgThreads, fn($a,$b)=>strtotime($b['last_at']??'1970-01-01')<=>strtotime($a['last_at']??'1970-01-01'));
  foreach($msgThreads as $th): ?>
<div class="acard" style="margin-bottom:12px">
  <div class="acard-hd">
    <div style="flex:1">
      <div style="font-weight:600"><?= htmlspecialchars($accLabel($th['buyer_uid']??'')) ?> ↔ <?= htmlspecialchars($accLabel($th['seller_uid']??'')) ?></div>
      <div class="ahint"><?= count($th['messages']??[]) ?> messages · last <?= htmlspecialchars(substr($th['last_at']??'',0,16)) ?>
        <?php if(!empty($th['listing_id'])): ?> · <a href="/product?id=<?= urlencode($th['listing_id']) ?>" target="_blank" style="color:var(--acc)">listing ↗</a><?php endif; ?></div>
    </div>
  </div>
  <div class="acard-body">
    <details><summary style="cursor:pointer;font-size:12px;color:var(--acc)">Read conversation</summary>
      <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px">
        <?php foreach(($th['messages']??[]) as $m): $isBuyer=($m['from']??'')===($th['buyer_uid']??''); ?>
        <div style="font-size:12.5px;line-height:1.5">
          <b style="color:<?= $isBuyer?'#3366cc':'#9a7320' ?>"><?= htmlspecialchars($accLabel($m['from']??'')) ?></b>
          <span class="ahint" style="margin-left:6px"><?= htmlspecialchars(substr($m['at']??'',0,16)) ?></span>
          <div><?= htmlspecialchars($m['text']??'') ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <form method="post" style="margin-top:10px;display:flex;gap:8px">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="admin_reply">
        <input type="hidden" name="thread_id" value="<?= htmlspecialchars($th['id']??'') ?>">
        <input name="body" required placeholder="Reply as <?= htmlspecialchars($accLabel($th['seller_uid']??'')) ?>…" style="flex:1;padding:7px 10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink);font-size:12.5px">
        <button class="abtn primary" type="submit">Reply</button>
      </form>
    </details>
  </div>
</div>
<?php endforeach; endif; ?>


<?php // ══════════════════════════════════════════════════════ NOTIFICATION CENTER
elseif($tab==='notify'):
  require_once __DIR__.'/inc/push.php';
  $pstats = vestra_push_stats();
  $plog   = vestra_push_log_all();
  $subscribedPct = count($accounts) ? round($pstats['users']/count($accounts)*100) : 0;
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div><h2 style="font-size:18px;font-weight:700">🔔 Notification Center</h2>
  <p class="ahint" style="margin-top:4px">Send push notifications straight to your users' phones &amp; desktops — product drops, offer news, anything. Automatic pushes (orders, offers, messages, escrow) are always on; this panel is for manual announcements.</p></div>
</div>

<div class="asgrid" style="margin-bottom:18px">
  <div class="ascard"><div class="sv" style="color:#9a7320"><?= (int)$pstats['users'] ?></div><div class="sl">Subscribed users (<?= $subscribedPct ?>%)</div></div>
  <div class="ascard"><div class="sv"><?= (int)$pstats['devices'] ?></div><div class="sl">Devices reachable</div></div>
  <div class="ascard"><div class="sv" style="color:#3366cc"><?= count($plog) ?></div><div class="sl">Broadcasts sent</div></div>
</div>

<div class="acols2" style="align-items:start">
  <div class="acard">
    <div class="acard-hd"><h3>📣 New announcement</h3></div>
    <div class="acard-body">
      <form method="post" class="aform" action="/admin">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="send_push">
        <div class="afield"><label>Send to</label>
          <select name="target" id="pushTarget" onchange="document.getElementById('pushUidRow').style.display=this.value==='user'?'':'none'">
            <option value="all">🌍 Everyone (all accounts)</option>
            <option value="buyers">🛍️ All buyers</option>
            <option value="sellers">🏷️ All sellers</option>
            <option value="user">👤 One specific user…</option>
          </select>
        </div>
        <div class="afield" id="pushUidRow" style="display:none"><label>User</label>
          <select name="uid">
            <?php foreach($accounts as $a): ?>
            <option value="<?= htmlspecialchars($a['id']??'') ?>"><?= htmlspecialchars(($a['company']?:($a['name']?:($a['email']??'?'))).' — '.($a['type']??'?')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="afield"><label>Title (max 80)</label><input name="title" maxlength="80" required placeholder="VESTRA — new D&amp;G drop 🔥"></div>
        <div class="afield"><label>Message (max 160)</label><textarea name="body" maxlength="160" rows="3" required placeholder="Fresh stock just landed: 29 new D&amp;G styles from €50/pc. First come, first served."></textarea></div>
        <div class="afield"><label>Opens page (tap target)</label><input name="url" value="/shop" placeholder="/shop"><div class="ahint">Site path only, e.g. <code>/shop</code>, <code>/product?id=…</code>, <code>/groups</code>, <code>/requests</code></div></div>
        <button class="abtn primary" type="submit" style="justify-content:center;padding:10px">🔔 Send notification</button>
        <div class="ahint">Only users who enabled notifications (bell button on the homepage / app) receive pushes. Delivery is instant.</div>
      </form>
    </div>
  </div>

  <div class="acard">
    <div class="acard-hd"><h3>🕐 Recent broadcasts</h3></div>
    <div class="acard-body">
      <?php if(!$plog): ?><div class="aempty">Nothing sent yet. Your announcement history appears here.</div>
      <?php else: ?>
      <div class="atscroll"><table class="atable">
        <?= arow(['When','Audience','Title','Reached'],true) ?>
        <?php foreach(array_slice($plog,0,15) as $le):
          $tl=['all'=>'🌍 Everyone','buyers'=>'🛍️ Buyers','sellers'=>'🏷️ Sellers','user'=>'👤 One user'][$le['target']??'all']??($le['target']??'?'); ?>
        <?= arow([
          htmlspecialchars(substr($le['at']??'',0,16)),
          abadge($tl,'#3366cc'),
          '<b>'.htmlspecialchars($le['title']??'').'</b>',
          '<span style="color:'.((int)($le['reached']??0)>0?'#1f9d63':'var(--mut)').'">'.(int)($le['reached']??0).' user(s)</span>',
        ]) ?>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="acard" style="margin-top:18px">
  <div class="acard-hd"><h3>⚡ Automatic notifications — always on</h3></div>
  <div class="acard-body" style="font-size:13px;line-height:1.9;color:var(--mut)">
    <b style="color:var(--fg)">Buyers get pushed when:</b> an offer is accepted / countered / declined · a seller answers their sourcing request · payment is confirmed · the order ships (with tracking) · escrow secures their payment · a refund is issued · a new message arrives.<br>
    <b style="color:var(--fg)">Sellers get pushed when:</b> a new order comes in · a new offer arrives · an escrow order is paid (ship now) · the buyer confirms delivery · escrow funds are released to their bank · their listing is approved or needs changes · their account is verified · a new message arrives.
  </div>
</div>

<?php // ══════════════════════════════════════════════════════ JOURNAL
elseif($tab==='journal'):
  $jarts = vestra_journal_all();
  $jEdit = ($eid=($_GET['edit']??'')) ? vestra_journal_find_id($eid) : null;
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <div><h2 style="font-size:18px;font-weight:700">📰 Journal</h2>
  <p class="ahint" style="margin-top:4px">Publish fashion, brand &amp; market articles. Published pieces appear at <a href="/journal" target="_blank" style="color:var(--acc)">/journal ↗</a>.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
  <form method="post" action="/admin"><?= csrfField() ?><input type="hidden" name="_action" value="journal_seed">
    <button class="abtn" type="submit" title="Add six ready-made, fully translated (EN/DE/FR/IT/ES) starter articles you can edit — running it again back-fills translations onto older starters">✨ Load starter articles</button>
  </form>
  <form method="post" action="/admin"><?= csrfField() ?><input type="hidden" name="_action" value="journal_photos">
    <input type="hidden" name="dry" value="1">
    <button class="abtn" type="submit" title="List the fashion photos Wikimedia Commons would supply — downloads nothing">🔍 Preview editorial photos</button>
  </form>
  <form method="post" action="/admin" onsubmit="return confirm('Download the previewed fashion photos into uploads/journal/?')"><?= csrfField() ?>
    <input type="hidden" name="_action" value="journal_photos"><input type="hidden" name="dry" value="0">
    <button class="abtn" type="submit" title="Download commercially-usable fashion photography from Wikimedia Commons into uploads/journal/, recording the photographer for each — articles without their own cover then draw from this pool">📷 Fetch editorial photos</button>
  </form>
  </div>
</div>

<div class="acols2" style="align-items:start">
  <div class="acard">
    <div class="acard-hd"><h3><?= $jEdit?'✏️ Edit article':'➕ New article' ?></h3></div>
    <div class="acard-body">
      <form method="post" action="/admin" class="aform">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="journal_save">
        <?php if($jEdit): ?><input type="hidden" name="jid" value="<?= htmlspecialchars($jEdit['id']) ?>"><?php endif; ?>
        <div class="afield"><label>Title</label><input name="title" required maxlength="140" value="<?= htmlspecialchars($jEdit['title']??'') ?>"></div>
        <div class="afield"><label>Category</label><select name="category">
          <?php foreach(VESTRA_JOURNAL_CATS as $c): ?><option value="<?= htmlspecialchars($c) ?>" <?= ($jEdit['category']??'')===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
        </select></div>
        <div class="afield"><label>Cover image URL (optional)</label><input name="cover" value="<?= htmlspecialchars($jEdit['cover']??'') ?>" placeholder="/uploads/journal/x.jpg or https://…"></div>
        <div class="afield"><label>Excerpt (1–2 sentences)</label><textarea name="excerpt" rows="2" maxlength="240"><?= htmlspecialchars($jEdit['excerpt']??'') ?></textarea></div>
        <div class="afield"><label>Body <span class="ahint">(leave a blank line between paragraphs)</span></label><textarea name="body" rows="13" style="font-family:inherit;line-height:1.6"><?= htmlspecialchars($jEdit['body']??'') ?></textarea></div>
        <div class="afield"><label>Author</label><input name="author" value="<?= htmlspecialchars($jEdit['author']??'VESTRA Editorial') ?>"></div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin:2px 0 6px"><input type="checkbox" name="published" value="1" <?= (!$jEdit||!empty($jEdit['published']))?'checked':'' ?>> Published (visible on the site)</label>
        <button class="abtn primary" type="submit" style="justify-content:center;padding:10px"><?= $jEdit?'Save changes':'Publish article' ?></button>
        <?php if($jEdit): ?><a class="abtn" href="/admin?tab=journal" style="justify-content:center;margin-top:6px">Cancel edit</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="acard">
    <div class="acard-hd"><h3>All articles (<?= count($jarts) ?>)</h3></div>
    <div class="acard-body">
      <?php if(!$jarts): ?><div class="aempty">No articles yet. Write one on the left, or press “Load starter articles”.</div>
      <?php else: foreach($jarts as $p): ?>
      <div style="display:flex;gap:10px;align-items:flex-start;padding:11px 2px;border-bottom:1px solid var(--line)">
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($p['title']??'') ?></div>
          <div class="ahint"><?= htmlspecialchars($p['category']??'') ?> · <?= htmlspecialchars(substr($p['created']??'',0,10)) ?> · <?= !empty($p['published'])?'<span style="color:#1f9d63">● published</span>':'<span style="color:var(--mut)">○ draft</span>' ?></div>
        </div>
        <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end">
          <?php if(!empty($p['published'])): ?><a class="abtn" href="/journal?slug=<?= urlencode($p['slug']??'') ?>" target="_blank" style="font-size:11px">View</a><?php endif; ?>
          <a class="abtn" href="/admin?tab=journal&edit=<?= urlencode($p['id']??'') ?>" style="font-size:11px">Edit</a>
          <?= fBtn(!empty($p['published'])?'Unpublish':'Publish','journal_toggle',['jid'=>$p['id']??''],'font-size:11px') ?>
          <?= fBtn('Delete','journal_delete',['jid'=>$p['id']??''],'font-size:11px;color:var(--bad);border-color:rgba(239,154,154,.3)','Delete this article permanently?') ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php // ══════════════════════════════════════════════════════ WAITLIST
elseif($tab==='waitlist'): ?>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=signups">⬇ CSV</a></div>
<?php if(!$signups): ?><div class="acard"><div class="aempty">No waitlist signups yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Date','Name','Email','Company','Country','Type','Notes'],true) ?>
  <?php foreach(array_reverse($signups) as $s): ?>
  <?= arow([
    htmlspecialchars(substr($s['timestamp']??'',0,10)),
    htmlspecialchars($s['name']??'—'),
    '<a href="mailto:'.htmlspecialchars($s['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($s['email']??'').'</a>',
    htmlspecialchars($s['company']??'—'),
    htmlspecialchars($s['country']??'—'),
    typePill($s['type']??'buyer'),
    htmlspecialchars(substr($s['notes']??$s['message']??'',0,80)),
  ]) ?>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>

<?php endif; // end tab switch ?>

</main>
</div><!-- alayout -->

<?php endif; // end authed ?>
</body></html>
