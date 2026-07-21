<?php
/** VESTRA — Admin Panel */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/promos.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/invoice.php';
require_once __DIR__.'/inc/orders.php';
require_once __DIR__.'/inc/leads.php';
require_once __DIR__.'/inc/notify.php';
require_once __DIR__.'/inc/stripe.php';
require_once __DIR__.'/inc/commission.php';
require_once __DIR__.'/inc/escrow.php';
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
    }
    header('Location: /admin?tab=approvals&msg=rejected'); exit;
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
  if($act==='approve_kyb'){
    $uid=$_POST['uid']??'';
    auth_update($uid,['kyb_status'=>'approved','status'=>'active']);
    $acc=null; foreach(auth_accounts() as $a){ if(($a['id']??'')===$uid){ $acc=$a; break; } }
    if($acc){
      require_once __DIR__.'/inc/push.php';
      vestra_push_send($uid,'VESTRA — account verified ✓','Your business is verified. Full wholesale access is unlocked.',
        (($acc['type']??'')==='seller')?'/seller':'/buyer');
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
        require_once __DIR__.'/inc/push.php';
        $label=$tier==='premium'?'Elite':ucfirst($tier);
        vestra_push_send($uid,'VESTRA — plan updated ⭐','Your VESTRA membership is now '.$label.'.',(($acc['type']??'')==='seller')?'/seller':'/buyer');
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
  if($act==='review_doc'){
    auth_review_doc($_POST['uid']??'', $_POST['req_id']??'', $_POST['status']??'', trim($_POST['admin_note']??''));
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
     refund the buyer in full (cancels the sale, claws the commission back). */
  if($act==='escrow_release'){
    $r=escrow_do_release($_POST['ref']??'');
    header('Location: /admin?tab=orders&msg='.($r['ok']?'esc_released':'esc_err')); exit;
  }
  if($act==='escrow_refund'){
    $r=escrow_do_refund($_POST['ref']??'');
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
  if($act==='save_lead_template'){
    vestra_save_lead_template(['subject'=>trim($_POST['tpl_subject']??''),'body'=>trim($_POST['tpl_body']??'')]);
    header('Location: /admin?tab=prospects&msg=lead_tpl_ok'); exit;
  }
  if($act==='send_lead_email'){
    $ids=array_slice(array_filter((array)($_POST['lead_ids']??[])),0,50);
    $leads=vestra_leads(); $tpl=vestra_lead_template(); $sent=0;
    require_once __DIR__.'/inc/notify.php';
    foreach($leads as &$l){
      if(!in_array($l['id']??'',$ids,true)) continue;
      if(($l['status']??'')==='unsubscribed') continue; // never re-email an opt-out
      [$subject,$body]=vestra_lead_render_email($l,$tpl);
      if(vestra_send_mail($l['email'],$subject,$body)){
        $sent++;
        if(($l['status']??'new')==='new') $l['status']='contacted';
        $l['last_contacted_at']=date('c');
      }
    }
    unset($l);
    vestra_save_leads($leads);
    header('Location: /admin?tab=prospects&msg=lead_sent&n='.$sent); exit;
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
if($authed && isset($_GET['dl'])){
  $map=['signups'=>'signups.csv','orders'=>'orders.csv','offers'=>'offers.csv','requests'=>'requests.csv','groups'=>'groups.csv','request_offers'=>'request_offers.csv'];
  $f=$map[$_GET['dl']]??null; $path=$f?vestra_data_dir().'/'.$f:'';
  if($f && is_readable($path)){ header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="vestra-'.$f.'"'); readfile($path); exit; }
  http_response_code(404); echo 'No data'; exit;
}

// ── Helper functions ───────────────────────────────────────────────────────────
function abadge(string $t, string $c='#888'): string {
  return '<span style="display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:'.$c.'22;color:'.$c.';border:1px solid '.$c.'44">'.htmlspecialchars($t).'</span>';
}
function kybBadge(string $s): string {
  return match($s){ 'approved'=>abadge('✓ Verified','#7ad6a0'),'suspended'=>abadge('⊘ Suspended','#ef9a9a'),default=>abadge('⏳ Pending','#f0c060') };
}
function docBadge(string $s): string {
  return match($s){ 'approved'=>abadge('✓ Approved','#7ad6a0'),'rejected'=>abadge('✗ Rejected','#ef9a9a'),'uploaded'=>abadge('📤 Review','#c9a86a'),'requested'=>abadge('📋 Requested','#8ab4f8'),default=>abadge('—','#555') };
}
function orderBadge(string $s): string {
  return match($s){ 'completed'=>abadge('✓ Completed','#7ad6a0'),'shipped'=>abadge('🚚 Shipped','#c9a86a'),'paid'=>abadge('💶 Paid — to ship','#8fb7e8'),default=>abadge('⏳ Awaiting payment','#888') };
}
function typePill(string $t): string {
  $c=$t==='seller'?'#c9a86a':'#8ab4f8'; $b=$t==='seller'?'rgba(201,168,106,.15)':'rgba(138,180,248,.15)';
  return '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:'.$b.';color:'.$c.'">'.htmlspecialchars($t).'</span>';
}
function memberBadge(string $tier, string $status): string {
  if($tier===''&&($status===''||$status==='none')) return '<span style="color:#555;font-size:11px">—</span>';
  $tc=['starter'=>'#8ab4f8','pro'=>'#c9a86a','premium'=>'#f0c060'][$tier]??'#888';
  $tl=$tier==='premium' ? 'Elite' : ($tier?ucfirst($tier):'');
  $sc=match($status){'active'=>'#7ad6a0','trialing'=>'#f0c060','past_due'=>'#ef9a9a','canceled'=>'#888',default=>'#555'};
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
:root{--sb:220px}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--ink);font-family:'Inter',sans-serif;min-height:100vh}
.alayout{display:grid;grid-template-columns:var(--sb) 1fr;grid-template-rows:52px 1fr;min-height:100vh}
/* top bar */
.atopbar{grid-column:1/-1;background:#0a0a0d;border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 20px;gap:14px;position:sticky;top:0;z-index:100}
.atopbar .logo{display:flex;align-items:center;gap:8px;color:var(--ink);text-decoration:none;font-weight:700;font-size:15px;width:var(--sb);flex-shrink:0}
.atopbar .logo svg{flex-shrink:0}
.atopbar-links{margin-left:auto;display:flex;gap:8px}
/* sidebar */
.asidebar{background:#0d0d10;border-right:1px solid var(--line);padding:16px 0;position:sticky;top:52px;height:calc(100vh - 52px);overflow-y:auto}
.asidebar a{display:flex;align-items:center;gap:9px;padding:8px 18px;color:var(--mut);text-decoration:none;font-size:13px;font-weight:500;border-left:2px solid transparent;transition:.1s}
.asidebar a:hover{color:var(--ink);background:rgba(255,255,255,.04)}
.asidebar a.on{color:var(--acc);border-left-color:var(--acc);background:rgba(201,168,106,.06)}
.asidebar .sgrp{padding:14px 18px 4px;font-size:10px;font-weight:700;letter-spacing:.08em;color:#444;text-transform:uppercase}
.aside-badge{margin-left:auto;background:var(--acc);color:#0e0e11;border-radius:20px;padding:1px 7px;font-size:10px;font-weight:700}
.aside-badge.red{background:#ef9a9a;color:#1a0000}
/* main */
.amain{padding:28px 32px;overflow-y:auto}
/* stat cards */
.asgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-bottom:24px}
.ascard{background:var(--bg2);border:1px solid var(--line);border-radius:12px;padding:14px 16px;cursor:default}
.ascard .sv{font-size:22px;font-weight:700;line-height:1.2;color:var(--acc)}
.ascard .sl{font-size:11px;color:var(--mut);margin-top:4px}
/* section card */
.acard{background:var(--bg2);border:1px solid var(--line);border-radius:14px;margin-bottom:20px;overflow:hidden}
.acard-hd{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.02)}
.acard-hd h3{font-size:14px;font-weight:600;flex:1}
.acard-body{padding:18px}
/* table */
.atable{width:100%;border-collapse:collapse;font-size:12.5px}
.atable th.ac{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);color:var(--mut);font-weight:500;font-size:11px;white-space:nowrap;background:rgba(255,255,255,.02)}
.atable td.ac{padding:9px 10px;border-bottom:1px solid #1a1a1e;vertical-align:top;max-width:220px;word-break:break-word}
.atable tr:last-child td.ac{border-bottom:none}
.atable tr:hover td.ac{background:rgba(255,255,255,.02)}
.atscroll{overflow-x:auto}
/* buttons */
.abtn{display:inline-flex;align-items:center;padding:4px 10px;border:1px solid var(--line);border-radius:7px;background:transparent;color:var(--ink);font-size:12px;cursor:pointer;white-space:nowrap;font-family:inherit;transition:.1s;text-decoration:none}
.abtn:hover{border-color:var(--acc);color:var(--acc)}
.abtn.primary{background:var(--acc);color:#0e0e11;border-color:var(--acc);font-weight:600}
.abtn.primary:hover{opacity:.9}
/* forms */
.aform{display:flex;flex-direction:column;gap:12px}
.afield label{display:block;font-size:11px;color:var(--mut);margin-bottom:4px}
.afield input,.afield select,.afield textarea{width:100%;padding:6px 10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink);font-size:13px;font-family:inherit}
.afield textarea{resize:vertical;min-height:60px}
/* misc */
.amsg{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px}
.amsg.ok{background:rgba(122,214,160,.1);border:1px solid rgba(122,214,160,.3);color:#7ad6a0}
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
.doc-uploaded{background:rgba(201,168,106,.1);border-left:3px solid #c9a86a}
.doc-approved{background:rgba(122,214,160,.08);border-left:3px solid #7ad6a0}
.doc-rejected{background:rgba(239,154,154,.08);border-left:3px solid #ef9a9a}
.doc-requested{background:rgba(138,180,248,.08);border-left:3px solid #8ab4f8}
/* Mobile: sidebar becomes a horizontal, scrollable tab strip instead of disappearing */
@media(max-width:900px){
  :root{--sb:0px}
  .alayout{display:block}
  .asidebar{position:static;height:auto;display:flex;flex-direction:row;align-items:center;gap:2px;overflow-x:auto;-webkit-overflow-scrolling:touch;padding:8px 10px;border-right:0;border-bottom:1px solid var(--line);white-space:nowrap}
  .asidebar a{border-left:0;border-bottom:2px solid transparent;padding:8px 12px;flex-shrink:0}
  .asidebar a.on{border-left-color:transparent;border-bottom-color:var(--acc)}
  .asidebar .sgrp{display:none}
  .amain{padding:16px}
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
      <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="#c9a86a" stroke-width="1.4"/>
      <path d="M9 10l7 13 7-13" stroke="#c9a86a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div><div style="font-weight:700;font-size:16px">VESTRA</div><div style="font-size:11px;color:var(--mut)">Admin Panel</div></div>
  </div>
  <?php if($err): ?><div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#ef9a9a">Wrong password.</div><?php endif; ?>
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

  // Escrow (Treuhand) at-a-glance — held funds + lifecycle counts for the dashboard.
  require_once __DIR__.'/inc/escrow.php';
  $escrowAll   = escrow_all();
  $escHeld     = array_filter($escrowAll, fn($e)=>($e['status']??'')==='held');
  $escHeldSum  = array_sum(array_map(fn($e)=>(float)($e['total']??0), $escHeld));
  $escReleased = count(array_filter($escrowAll, fn($e)=>($e['status']??'')==='released'));
  $escRefunded = count(array_filter($escrowAll, fn($e)=>($e['status']??'')==='refunded'));
  // Membership + Connect readiness across sellers.
  $memActive    = count(array_filter($sellers, fn($a)=>in_array($a['membership_status']??'', ['active','trialing'], true)));
  $connectReady = count(array_filter($sellers, fn($a)=>!empty($a['escrow_ready'])));

  // Accounts with pending document uploads
  $pendingDocs  = count(array_filter($accounts, fn($a)=>count(array_filter($a['doc_requests']??[],fn($r)=>$r['status']==='uploaded'))>0));

  $msgs=[
    'approved'=>'✓ Listing approved and live.','rejected'=>'Listing rejected.','kyb_ok'=>'KYB approved.',
    'suspended'=>'Account suspended.','activated'=>'Account activated.','deleted'=>'Listing deleted.',
    'member_set'=>'✓ Membership plan updated.',
    'journal_saved'=>'✓ Article saved.','journal_deleted'=>'Article deleted.','journal_toggled'=>'Article visibility changed.',
    'listing_saved'=>'✓ Listing updated.',
    'status_ok'=>'Order status updated.','promo_ok'=>'Promo code created.','promo_del'=>'Promo code deleted.',
    'esc_released'=>'✓ Escrow released — funds paid out to the seller.','esc_refunded'=>'✓ Buyer refunded in full — sale cancelled.','esc_err'=>'⚠ Escrow action failed — see server log for details.',
    'promo_toggled'=>'Promo code status changed.',
    'doc_requested'=>'Document requested.','doc_reviewed'=>'Document reviewed.',
    'verify_resent'=>'Verification email resent.','manual_verified'=>'Email verified manually.',
    'badge_granted'=>'✓ Verified Seller badge granted.','badge_revoked'=>'Badge revoked.',
    'csrf_fail'=>'⚠ Security check failed — please retry the action from this page.',
    'lead_added'=>'✓ Prospect added.','lead_dupe'=>'That email is already on the list.',
    'lead_invalid'=>'Company and a valid email are required.','lead_status_ok'=>'Prospect status updated.',
    'lead_deleted'=>'Prospect deleted.','lead_tpl_ok'=>'✓ Outreach template saved.',
  ];

  function navLink(string $cur, string $key, string $icon, string $label, int $badge=0, bool $red=false): string {
    $on = $cur===$key?' on':'';
    $b = $badge>0?'<span class="aside-badge'.($red?' red':'').'">'.$badge.'</span>':'';
    return '<a href="/admin?tab='.htmlspecialchars($key).'" class="'.$on.'">'.$icon.' '.htmlspecialchars($label).$b.'</a>';
  }
?>

<div class="alayout">
<!-- TOP BAR -->
<div class="atopbar">
  <a href="/admin" class="logo">
    <svg viewBox="0 0 32 32" fill="none" width="26" height="26">
      <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="#c9a86a" stroke-width="1.4"/>
      <path d="M9 10l7 13 7-13" stroke="#c9a86a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    VESTRA
  </a>
  <span style="color:var(--mut);font-size:12px">Admin Panel</span>
  <div class="atopbar-links">
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

  <div class="sgrp">People</div>
  <?= navLink($tab,'users','👥','Users ('.count($accounts).')',count($pendingEmail),count($pendingEmail)>0) ?>

  <div class="sgrp">Transactions</div>
  <?= navLink($tab,'orders','📦','Orders ('.count($orders).')') ?>
  <?= navLink($tab,'offers','💬','Offers ('.count($offers).')') ?>
  <?= navLink($tab,'requests','📋','Requests ('.count($requests).')') ?>
  <?= navLink($tab,'req_offers','📩','Request Offers ('.count($reqOffers).')') ?>
  <?= navLink($tab,'groups','👥','Group buys ('.count($groupPools).')') ?>

  <div class="sgrp">Moderation</div>
  <?= navLink($tab,'messages','✉️','Messages ('.count($msgThreads).')',count($blockedMsgs),count($blockedMsgs)>0) ?>
  <?= navLink($tab,'notify','🔔','Notifications') ?>

  <div class="sgrp">Catalog</div>
  <?= navLink($tab,'listings','🏷️','Listings ('.count($listings).')') ?>

  <div class="sgrp">Content</div>
  <?= navLink($tab,'journal','📰','Journal ('.count($journalAll).')') ?>

  <div class="sgrp">Marketing</div>
  <?= navLink($tab,'marketing','🎟️','Promo codes ('.count($promos).')') ?>
  <?= navLink($tab,'prospects','🎯','Seller prospects ('.count($leads).')') ?>
  <?= navLink($tab,'waitlist','📩','Waitlist ('.count($signups).')') ?>
</nav>

<!-- MAIN -->
<main class="amain">

<?php if($msg==='pw_reset' && !empty($_SESSION['pw_reset_flash'])):
  $flash=$_SESSION['pw_reset_flash']; unset($_SESSION['pw_reset_flash']); // show once ?>
<div class="amsg ok" style="background:rgba(201,168,106,.1);border:1px solid rgba(201,168,106,.4)">
  🔑 New password for <b><?= htmlspecialchars($flash['email']) ?></b>:
  <code style="font-size:15px;background:#000;padding:3px 10px;border-radius:6px;color:#c9a86a;user-select:all"><?= htmlspecialchars($flash['pw']) ?></code>
  &nbsp;— copy it now and send it to them (WhatsApp / phone). It won't be shown again; they can change it after signing in.
</div>
<?php elseif($msg && isset($msgs[$msg])): ?>
<div class="amsg ok"><?= htmlspecialchars($msgs[$msg]) ?></div>
<?php elseif($msg==='lead_import'): ?>
<div class="amsg ok">✓ Imported <?= (int)($_GET['added']??0) ?> prospect(s)<?= ($_GET['skipped']??0) ? ', skipped '.(int)$_GET['skipped'].' (duplicate or invalid)' : '' ?>.</div>
<?php elseif($msg==='lead_sent'): ?>
<div class="amsg ok">✓ Sent to <?= (int)($_GET['n']??0) ?> prospect(s).</div>
<?php elseif($msg==='dupfix'): ?>
<div class="amsg ok">✓ Repaired <?= (int)($_GET['n']??0) ?> duplicate order ref(s) — every order now has its own independent status.</div>
<?php elseif($msg==='push_sent'): ?>
<div class="amsg ok">🔔 Notification sent — reached <?= (int)($_GET['n']??0) ?> subscribed user(s). Users without push enabled don't count here.</div>
<?php elseif($msg==='push_err'): ?>
<div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#ef9a9a">⚠ Title and message are required.</div>
<?php elseif($msg==='journal_seeded'): ?>
<div class="amsg ok">✓ Loaded <?= (int)($_GET['n']??0) ?> starter article(s)<?= ((int)($_GET['n']??0)===0)?' — they were already present':'' ?>. Edit or unpublish them any time below.</div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ OVERVIEW
if($tab==='overview'): ?>

<?php require_once __DIR__.'/inc/stripe.php'; if(!stripe_configured()): ?>
<div class="amsg" style="background:rgba(240,192,96,.08);border:1px solid rgba(240,192,96,.3);color:#f0c060">
  ⚠ Stripe is not configured — seller membership checkout is disabled.
  Missing keys: <code style="font-size:11px"><?= htmlspecialchars(implode(', ', stripe_missing_keys())) ?></code>.
  Copy <code>.env.example</code> to a <code>.env</code> file one level above the document root and fill in the values.
</div>
<?php endif; ?>

<div class="asgrid">
  <div class="ascard"><div class="sv"><?= count($accounts) ?></div><div class="sl">Total accounts</div></div>
  <div class="ascard"><div class="sv" style="color:#c9a86a"><?= count($sellers) ?></div><div class="sl">Sellers</div></div>
  <div class="ascard"><div class="sv" style="color:#8ab4f8"><?= count($buyers) ?></div><div class="sl">Buyers</div></div>
  <div class="ascard"><div class="sv" style="color:#ef9a9a"><?= count($pendingEmail) ?></div><div class="sl">Email unverified</div></div>
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= count($pendingKyb) ?></div><div class="sl">Pending KYB</div></div>
  <div class="ascard"><div class="sv" style="color:#ef9a9a"><?= count($pendingList) ?></div><div class="sl">Pending listings</div></div>
  <div class="ascard"><div class="sv"><?= count($orders) ?></div><div class="sl">Orders</div></div>
  <div class="ascard"><div class="sv"><?= eur($totalRevenue) ?></div><div class="sl">Order volume</div></div>
  <?php
    $comAll = vestra_commissions();
    $comCharged = array_sum(array_map(fn($c)=>($c['status']??'')==='charged'?(float)($c['amount']??0):0, $comAll));
    $comFailed  = count(array_filter($comAll, fn($c)=>in_array($c['status']??'', ['failed','no_card'], true)));
  ?>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= eur($comCharged) ?></div><div class="sl">Commission collected</div></div>
  <div class="ascard"><div class="sv" style="color:<?= $comFailed?'#ef9a9a':'#555' ?>"><?= $comFailed ?></div><div class="sl">Commission needs attention</div></div>
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= count($pendingOffers) ?></div><div class="sl">Offers pending</div></div>
  <div class="ascard"><div class="sv"><?= count($signups) ?></div><div class="sl">Waitlist</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= eur($escHeldSum) ?></div><div class="sl">🛡️ Held in escrow (<?= count($escHeld) ?>)</div></div>
  <div class="ascard"><div class="sv" style="color:#8fd3ff"><?= $escReleased ?></div><div class="sl">Escrow released</div></div>
  <div class="ascard"><div class="sv" style="color:#c9a86a"><?= $connectReady ?></div><div class="sl">Connect-ready sellers</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= $memActive ?></div><div class="sl">Active memberships</div></div>
</div>

<?php if($pendingList||$pendingKyb): ?>
<div class="acols2">
<?php if($pendingList): ?>
<div class="acard">
  <div class="acard-hd"><h3>⚠️ Listings awaiting approval (<?= count($pendingList) ?>)</h3><a class="abtn" href="/admin?tab=approvals">View all →</a></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['Brand','Product','Seller','Date'],true) ?>
    <?php foreach(array_slice(array_reverse(array_values($pendingList)),0,5) as $p): ?>
    <?= arow(['<b>'.htmlspecialchars($p['brand']??'').'</b>',htmlspecialchars($p['name']??''),htmlspecialchars($p['seller']??''),htmlspecialchars(substr($p['submitted_at']??'',0,10))]) ?>
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
        <form method="post" onsubmit="return confirm('Release the held funds to the seller?')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_release"><input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>"><button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#7ad6a0">Release</button></form>
        <form method="post" onsubmit="return confirm('Refund the buyer in full? This cancels the sale.')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_refund"><input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>"><button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#ef9a9a">Refund</button></form>
      </div></td>
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
    <?= abadge('⏳ Pending','#f0c060') ?>
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
      <?php if(!empty($req['admin_note'])): ?><div class="ahint" style="margin-top:4px;color:#c9a86a">Admin: <?= htmlspecialchars($req['admin_note']) ?></div><?php endif; ?>
    </div>
    <?= docBadge($st) ?>
  </div>
  <?php if($st==='uploaded' && !empty($req['file'])): ?>
  <div class="acard-body">
    <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
      <a class="abtn" href="/admin?dl_doc=<?= urlencode($req['file']) ?>&uid=<?= urlencode($selUser['id']??'') ?>" target="_blank">📂 View uploaded file</a>
      <form method="post" style="display:inline-flex;gap:8px;align-items:center">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="review_doc">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($selUser['id']??'') ?>">
        <input type="hidden" name="req_id" value="<?= htmlspecialchars($req['id']??'') ?>">
        <input name="admin_note" placeholder="Optional note" style="padding:4px 8px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12px;width:180px">
        <button class="abtn" name="status" value="approved" type="submit" style="color:var(--ok);border-color:rgba(122,214,160,.4)">✓ Approve</button>
        <button class="abtn" name="status" value="rejected" type="submit" style="color:var(--bad);border-color:rgba(239,154,154,.3)">✗ Reject</button>
      </form>
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
      $uploaded>0?abadge("$uploaded waiting review",'#c9a86a'):'<span class="ahint">—</span>',
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
  </div>
</div>
<script>
function ufilter(){
  var q=document.getElementById('usearch').value.toLowerCase();
  document.querySelectorAll('.atable tr').forEach(function(tr,i){
    if(i===0) return; // header
    tr.style.display = tr.textContent.toLowerCase().indexOf(q)>-1 ? '' : 'none';
  });
}
</script>

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
    <td class="ac"><b><?= htmlspecialchars($a['name']??'—') ?></b><div class="ahint"><?= htmlspecialchars(substr($a['id']??'',0,10)) ?>…</div></td>
    <td class="ac"><a href="mailto:<?= htmlspecialchars($a['email']??'') ?>" style="color:var(--acc);font-size:12px"><?= htmlspecialchars($a['email']??'') ?></a></td>
    <td class="ac"><?= typePill($a['type']??'') ?></td>
    <td class="ac"><?= htmlspecialchars($a['company']??'—') ?></td>
    <td class="ac"><?= htmlspecialchars($a['country']??'—') ?></td>
    <td class="ac" style="font-family:monospace;font-size:11px"><?= htmlspecialchars($a['vat_id']??'—') ?></td>
    <td class="ac">
      <?php if($isPendEmail): ?>
        <?= abadge('⚠ Unverified','#ef9a9a') ?>
        <?php if(!empty($a['verify_sent_at'])):
          $sentOk = $a['verify_sent_ok'] ?? true;
        ?>
          <div class="ahint" style="margin-top:2px;<?= $sentOk?'':'color:#ef9a9a' ?>">
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
        <?= abadge('✓ Verified','#7ad6a0') ?>
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
          <?= abadge('✓ Badge','#7ad6a0') ?>
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
      <?php if($uploaded>0): ?><div><?= abadge("$uploaded to review",'#c9a86a') ?></div><?php endif; ?>
    </td>
    <td class="ac" style="font-size:11px;color:var(--mut)">
      <?= htmlspecialchars(substr($a['created']??'',0,10)) ?>
      <?php if(!empty($a['last_login'])): ?><div class="ahint" style="margin-top:2px">Last in: <?= htmlspecialchars(substr($a['last_login'],0,10)) ?></div><?php endif; ?>
    </td>
    <td class="ac"><div style="display:flex;gap:4px;flex-wrap:wrap">
      <a class="abtn" href="/admin?tab=documents&uid=<?= urlencode($a['id']??'') ?>">Docs</a>
      <?php if(($a['kyb_status']??'pending')==='pending'&&!$isSusp&&!$isPendEmail): echo fBtn('✓ KYB','approve_kyb',['uid'=>$a['id']??''],'color:var(--ok);border-color:rgba(122,214,160,.4)'); endif; ?>
      <?= fBtn('🔑 Reset pw','reset_password',['uid'=>$a['id']??''],'','Generate a new temporary password for '.($a['email']??'this account').'? You will see it once, to send to them.') ?>
      <?php if($isSusp): echo fBtn('Activate','activate_account',['uid'=>$a['id']??'']); else: echo fBtn('Suspend','suspend_account',['uid'=>$a['id']??''],'color:var(--bad);border-color:rgba(239,154,154,.3)'); endif; ?>
    </div></td>
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
      <?= arow(['Platform commission','<b style="color:#7ad6a0">'.eur($viewRow['commission']??0).'</b>']) ?>
      <?= arow(['Seller payout',eur($viewRow['payout']??0)]) ?>
      <?= arow(['<b>Buyer pays</b>','<b>'.eur($viewRow['total']??0).'</b>']) ?>
    </table>
    <div style="margin-top:12px">
      <div class="ahint" style="margin-bottom:6px;font-weight:600">Commission charges</div>
      <?php $vcoms=vestra_commissions_for_ref($viewRef); if(!$vcoms): ?><span style="color:var(--mut);font-size:12px">— none recorded</span>
      <?php else: foreach($vcoms as $c): ?>
        <div style="font-size:12px;padding:3px 0"><?= match($c['status']??''){'charged'=>abadge('✓ charged '.eur($c['amount']??0),'#7ad6a0'),'failed'=>abadge('✗ failed '.eur($c['amount']??0),'#ef9a9a'),'no_card'=>abadge('⚠ no card','#f0c060'),default=>abadge($c['status']??'—','#888')} ?> <span style="color:var(--mut)"><?= htmlspecialchars(substr($c['timestamp']??'',0,16)) ?></span></div>
      <?php endforeach; endif; ?>
    </div>
    <div style="margin-top:12px">
      <div class="ahint" style="margin-bottom:6px;font-weight:600">Invoices</div>
      <?php $vinvs=vestra_invoices_for_ref($viewRef); if(!$vinvs): ?><span style="color:var(--mut);font-size:12px">— none yet</span>
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
        <form method="post" onsubmit="return confirm('Release the held funds to the seller?')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_release"><input type="hidden" name="ref" value="<?= htmlspecialchars($viewRef) ?>"><button class="abtn" type="submit" style="color:#7ad6a0">Release to seller</button></form>
        <form method="post" onsubmit="return confirm('Refund the buyer in full? This cancels the sale.')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_refund"><input type="hidden" name="ref" value="<?= htmlspecialchars($viewRef) ?>"><button class="abtn" type="submit" style="color:#ef9a9a">Refund buyer</button></form>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>

<div class="asgrid" style="grid-template-columns:repeat(5,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv"><?= count($orders) ?></div><div class="sl">Total orders</div></div>
  <div class="ascard"><div class="sv" style="color:#888"><?= count($orders)-$cnt_ship-$cnt_done ?></div><div class="sl">Awaiting payment</div></div>
  <div class="ascard"><div class="sv" style="color:#c9a86a"><?= $cnt_ship ?></div><div class="sl">Shipped</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= $cnt_done ?></div><div class="sl">Completed</div></div>
  <div class="ascard"><div class="sv"><?= eur($totalRevenue) ?></div><div class="sl">Total volume</div></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=orders">⬇ Download CSV</a></div>

<?php
/* Legacy duplicate refs (same buyer + same items pre-fix) share ONE status entry —
   the "update one, all change" bug. Offer the one-click repair when any exist. */
$__refCounts = array_count_values(array_filter(array_map(fn($o)=>$o['ref']??'', $orders)));
$__dupRefs   = array_filter($__refCounts, fn($n)=>$n>1);
if($__dupRefs): ?>
<div class="amsg" style="background:rgba(240,192,96,.08);border:1px solid rgba(240,192,96,.3);color:#f0c060;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
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
    <td class="ac" style="max-width:140px;font-size:11px"><?= htmlspecialchars($o['items']??'') ?></td>
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
          'charged'=>abadge('✓ '.eur($c['amount']??0),'#7ad6a0'),
          'failed'=>abadge('✗ '.eur($c['amount']??0),'#ef9a9a'),
          'no_card'=>abadge('⚠ no card','#f0c060'),
          default=>abadge('—','#555'),
        } ?><br>
      <?php endforeach; endif; ?>
    </td>
    <td class="ac" style="font-size:11px">
      <?php $er=escrow_get($ref); if(!$er): ?><span style="color:var(--mut)">—</span>
      <?php else: ?>
        <?= escrow_badge($er['status']??'') ?>
        <?php if(!empty($er['disputed'])): ?><div style="color:#f0c060;margin-top:2px">⚠ <?= htmlspecialchars(mb_substr((string)($er['dispute_reason']??'disputed'),0,50)) ?></div><?php endif; ?>
        <?php if(($er['status']??'')==='held'): ?>
        <div style="display:flex;gap:4px;margin-top:4px">
          <form method="post" onsubmit="return confirm('Release the held funds to the seller?')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_release"><input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>"><button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#7ad6a0" title="Release to seller">Release</button></form>
          <form method="post" onsubmit="return confirm('Refund the buyer in full? This cancels the sale.')" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="escrow_refund"><input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>"><button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#ef9a9a" title="Refund buyer">Refund</button></form>
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


<?php // ══════════════════════════════════════════════════════ OFFERS
elseif($tab==='offers'):
  $cnt_acc=count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='accept'));
  $cnt_dec=count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='decline'));
  $cnt_ctr=count(array_filter($offers,fn($o)=>($offerResp[$o['ref']??'']['status']??'')==='counter'));
?>
<div class="asgrid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= count($pendingOffers) ?></div><div class="sl">Pending</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= $cnt_acc ?></div><div class="sl">Accepted</div></div>
  <div class="ascard"><div class="sv" style="color:#c9a86a"><?= $cnt_ctr ?></div><div class="sl">Countered</div></div>
  <div class="ascard"><div class="sv" style="color:#ef9a9a"><?= $cnt_dec ?></div><div class="sl">Declined</div></div>
</div>
<div style="margin-bottom:12px"><a class="abtn" href="/admin?dl=offers">⬇ CSV</a></div>
<?php if(!$offers): ?><div class="acard"><div class="aempty">No offers yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['Ref','Date','Product','SKU','Qty','€/u','Total','Buyer','Status','Counter'],true) ?>
  <?php foreach(array_reverse($offers) as $o):
    $ref=$o['ref']??''; $resp=$offerResp[$ref]??null; $rSt=$resp['status']??'pending'; ?>
  <?= arow([
    '<span class="atag">'.htmlspecialchars(substr($ref,0,10)).'</span>',
    htmlspecialchars(substr($o['timestamp']??'',0,10)),
    htmlspecialchars($o['product']??'—'),
    htmlspecialchars($o['sku']??''),
    htmlspecialchars($o['qty']??''),
    eur($o['offer_unit']??0),
    '<b>'.eur($o['offer_total']??0).'</b>',
    '<a href="mailto:'.htmlspecialchars($o['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($o['email']??'').'</a>',
    match($rSt){'accept'=>abadge('✓ Accepted','#7ad6a0'),'decline'=>abadge('✗ Declined','#ef9a9a'),'counter'=>abadge('↩ Counter','#c9a86a'),default=>abadge('⏳ Pending','#888')},
    ($resp&&$rSt==='counter')?eur($resp['counter_price']??0):'—',
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


<?php // ══════════════════════════════════════════════════════ LISTINGS
elseif($tab==='listings'):
  $liveList   = array_filter($listings,fn($p)=>($p['status']??'approved')==='approved');
  $rejList    = array_filter($listings,fn($p)=>($p['status']??'')==='rejected');
  $ledit      = ($leid=($_GET['edit']??'')) ? vestra_listing_by_id($leid) : null;
?>
<?php if($ledit): $lc=(array)($ledit['colors']??[]); $lt=$ledit['tiers']??[]; ?>
<div class="acard" style="margin-bottom:18px;border-color:var(--acc)">
  <div class="acard-hd"><h3>✏️ Edit listing — <?= htmlspecialchars(trim(($ledit['brand']??'').' '.($ledit['name']??''))) ?></h3><a class="abtn" href="/admin?tab=listings">✕ Close</a></div>
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
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= count($liveList) ?></div><div class="sl">Live / approved</div></div>
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= count($pendingList) ?></div><div class="sl">Pending approval</div></div>
  <div class="ascard"><div class="sv" style="color:var(--mut)"><?= count(vestra_demo_products()) ?></div><div class="sl">Demo products</div></div>
</div>
<?php if(!$listings): ?><div class="acard"><div class="aempty">No custom listings yet.</div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['','Brand','Product','SKU','Mode','MOQ','From','Seller','Status',''],true) ?>
  <?php foreach(array_reverse($listings) as $p): $st=$p['status']??'approved'; $thumb=vestra_primary_image($p); ?>
  <tr>
    <td class="ac"><?php if($thumb): ?><img src="<?= htmlspecialchars($thumb) ?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:7px;border:1px solid var(--line)"><?php else: ?><div style="width:42px;height:42px;border-radius:7px;background:linear-gradient(135deg,<?= htmlspecialchars($p['accent']??'#333') ?>,#0e0e11)"></div><?php endif; ?></td>
    <td class="ac"><b><?= htmlspecialchars($p['brand']??'') ?></b></td>
    <td class="ac"><?= htmlspecialchars($p['name']??'') ?><div class="ahint"><?= htmlspecialchars(substr($p['id']??'',0,14)) ?>…</div><?= !empty($p['colors'])?'<div style="margin-top:3px">'.vestra_color_dots((array)$p['colors'],7).'</div>':'' ?></td>
    <td class="ac"><span class="atag"><?= htmlspecialchars($p['sku']??'') ?></span></td>
    <td class="ac"><span class="modechip <?= htmlspecialchars($p['mode']??'fixed') ?>"><?= htmlspecialchars($p['mode']??'fixed') ?></span></td>
    <td class="ac"><?= htmlspecialchars((string)($p['moq']??'')) ?> <?= htmlspecialchars($p['unit']??'pc') ?></td>
    <td class="ac"><?= $st==='offer'?'—':eur(vestra_from_price($p)) ?></td>
    <td class="ac"><?= htmlspecialchars($p['seller']??'—') ?></td>
    <td class="ac"><?= match($st){'approved'=>abadge('✓ Live','#7ad6a0'),'rejected'=>abadge('✗ Rejected','#ef9a9a'),default=>abadge('⏳ Pending','#f0c060')} ?></td>
    <td class="ac"><div style="display:flex;gap:4px">
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
      <td class="ac"><?= abadge($active?'Active':'Paused',$active?'#7ad6a0':'#888') ?></td>
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
?>
<p class="ahint" style="margin-bottom:16px;max-width:760px">
  This list only ever grows from research <b>you</b> do (trade shows, LinkedIn, directories, the Seller Scout links
  under Promo codes) or a CSV you compiled yourself — VESTRA never crawls the web to harvest contacts. Every outreach
  email carries a working one-click unsubscribe link; anyone who uses it is permanently excluded from future sends.
</p>

<div class="asgrid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="ascard"><div class="sv"><?= $ldNew ?></div><div class="sl">New</div></div>
  <div class="ascard"><div class="sv" style="color:#8ab4f8"><?= $ldContacted ?></div><div class="sl">Contacted</div></div>
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= $ldReplied ?></div><div class="sl">Replied</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= $ldConverted ?></div><div class="sl">Converted</div></div>
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
  <p class="ahint" style="margin-bottom:12px">Header row required. Columns: <code>company,email</code> required — <code>contact_name,country,website,source,category,notes</code> optional. Duplicate emails are skipped automatically.</p>
  <form method="post" enctype="multipart/form-data" class="aform">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="import_leads_csv">
    <div class="afield"><input type="file" name="csv" accept=".csv,text/csv" required></div>
    <button class="abtn primary" type="submit">⬆ Import</button>
  </form>
  </div>
</div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>Outreach email template</h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin-bottom:12px">Placeholders: <code>{{company}}</code> <code>{{contact_name}}</code> <code>{{country}}</code>. A sender-identification + unsubscribe footer is appended automatically to every send and can't be removed.</p>
  <form method="post" class="aform">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="save_lead_template">
    <div class="afield"><label>Subject</label><input name="tpl_subject" value="<?= htmlspecialchars($leadTpl['subject']) ?>"></div>
    <div class="afield"><label>Body</label><textarea name="tpl_body" rows="8"><?= htmlspecialchars($leadTpl['body']) ?></textarea></div>
    <button class="abtn primary" type="submit">Save template</button>
  </form>
  </div>
</div>

<form method="post" id="leadRowForm" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="_action" id="lrf_action">
  <input type="hidden" name="lid" id="lrf_lid">
  <input type="hidden" name="status" id="lrf_status">
</form>
<script>
function leadSetStatus(lid,status){
  document.getElementById('lrf_action').value='update_lead_status';
  document.getElementById('lrf_lid').value=lid;
  document.getElementById('lrf_status').value=status;
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
      <span class="ahint">Max 50 per send · unsubscribed prospects can't be selected</span>
    </div>
    <div class="atscroll"><table class="atable">
      <tr><th class="ac"><input type="checkbox" onclick="leadToggleAll(this)"></th><th class="ac">Company</th><th class="ac">Contact</th><th class="ac">Email</th><th class="ac">Country</th><th class="ac">Source</th><th class="ac">Category</th><th class="ac">Status</th><th class="ac">Last contacted</th><th class="ac"></th></tr>
      <?php foreach(array_reverse($leads) as $l): $unsub=($l['status']??'')==='unsubscribed'; ?>
      <tr style="opacity:<?= $unsub?.5:1 ?>">
        <td class="ac"><input class="leadchk" type="checkbox" name="lead_ids[]" value="<?= htmlspecialchars($l['id']??'') ?>" <?= $unsub?'disabled':'' ?>></td>
        <td class="ac"><b><?= htmlspecialchars($l['company']??'') ?></b><?php if(!empty($l['website'])): ?><div class="ahint"><?= htmlspecialchars($l['website']) ?></div><?php endif; ?></td>
        <td class="ac"><?= htmlspecialchars($l['contact_name']??'') ?: '—' ?></td>
        <td class="ac" style="font-size:11px"><?= htmlspecialchars($l['email']??'') ?></td>
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
        <td class="ac" style="font-size:11px"><?= $l['last_contacted_at'] ? htmlspecialchars(substr($l['last_contacted_at'],0,10)) : '—' ?></td>
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
  <div class="ascard"><div class="sv" style="color:#f0c060"><?= $cnt_open ?></div><div class="sl">Open</div></div>
  <div class="ascard"><div class="sv" style="color:#7ad6a0"><?= $cnt_funded ?></div><div class="sl">Target reached</div></div>
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
    <?= match($gp['_status']){'funded'=>abadge('✓ Target reached','#7ad6a0'),'expired'=>abadge('• Expired','#888'),default=>abadge('⏳ Open · '.$gp['_daysLeft'].'d left','#f0c060')} ?>
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
  <div class="acard-hd"><h3 style="color:#ef9a9a">⚠️ Blocked off-platform attempts (<?= count($blockedMsgs) ?>)</h3></div>
  <div class="acard-body"><div class="atscroll"><table class="atable">
    <?= arow(['When','Sender','Thread','Type','Attempted text'],true) ?>
    <?php foreach(array_reverse($blockedMsgs) as $bm): ?>
    <?= arow([
      htmlspecialchars(substr($bm['at']??'',0,16)),
      '<b>'.htmlspecialchars($accLabel($bm['from']??'')).'</b>',
      htmlspecialchars($accLabel($bm['buyer_uid']??'')).' ↔ '.htmlspecialchars($accLabel($bm['seller_uid']??'')),
      abadge(strtoupper($bm['flag']??''),'#ef9a9a'),
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
          <b style="color:<?= $isBuyer?'#8ab4f8':'#c9a86a' ?>"><?= htmlspecialchars($accLabel($m['from']??'')) ?></b>
          <span class="ahint" style="margin-left:6px"><?= htmlspecialchars(substr($m['at']??'',0,16)) ?></span>
          <div><?= htmlspecialchars($m['text']??'') ?></div>
        </div>
        <?php endforeach; ?>
      </div>
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
  <div class="ascard"><div class="sv" style="color:#c9a86a"><?= (int)$pstats['users'] ?></div><div class="sl">Subscribed users (<?= $subscribedPct ?>%)</div></div>
  <div class="ascard"><div class="sv"><?= (int)$pstats['devices'] ?></div><div class="sl">Devices reachable</div></div>
  <div class="ascard"><div class="sv" style="color:#8ab4f8"><?= count($plog) ?></div><div class="sl">Broadcasts sent</div></div>
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
          abadge($tl,'#8ab4f8'),
          '<b>'.htmlspecialchars($le['title']??'').'</b>',
          '<span style="color:'.((int)($le['reached']??0)>0?'#7ad6a0':'var(--mut)').'">'.(int)($le['reached']??0).' user(s)</span>',
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
  <form method="post" action="/admin"><?= csrfField() ?><input type="hidden" name="_action" value="journal_seed">
    <button class="abtn" type="submit" title="Add six ready-made, fully translated (EN/DE/FR/IT/ES) starter articles you can edit — running it again back-fills translations onto older starters">✨ Load starter articles</button>
  </form>
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
      <div style="display:flex;gap:10px;align-items:flex-start;padding:11px 2px;border-bottom:1px solid rgba(255,255,255,.05)">
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($p['title']??'') ?></div>
          <div class="ahint"><?= htmlspecialchars($p['category']??'') ?> · <?= htmlspecialchars(substr($p['created']??'',0,10)) ?> · <?= !empty($p['published'])?'<span style="color:#7ad6a0">● published</span>':'<span style="color:var(--mut)">○ draft</span>' ?></div>
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
