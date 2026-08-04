<?php
require_once __DIR__.'/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Profile save ─────────────────────────────────────────────────────────────
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='profile') {
    auth_update($_SESSION['uid'], [
        'name'=>trim($_POST['name']??''),'company'=>trim($_POST['company']??''),
        'vat_id'=>trim($_POST['vat_id']??''),'reg_number'=>trim($_POST['reg_number']??''),
        'country'=>trim($_POST['country']??''),'address'=>trim($_POST['address']??''),
        'phone'=>trim($_POST['phone']??''),'website'=>trim($_POST['website']??''),
        'bank_name'=>trim($_POST['bank_name']??''),'bank_holder'=>trim($_POST['bank_holder']??''),
        'bank_iban'=>strtoupper(trim(preg_replace('/\s+/',' ',$_POST['bank_iban']??''))),
        'bank_bic'=>strtoupper(trim($_POST['bank_bic']??'')),
    ]);
    header('Location: /seller?tab=profile&saved=1'); exit;
}

// Change password (requires the current password)
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='change_password') {
    $u = auth_user();
    if (!$u || !password_verify($_POST['current']??'', $u['hash']??'')) { header('Location: /seller?tab=profile&pwerr=cur'); exit; }
    if (strlen($_POST['password']??'') < 8)                              { header('Location: /seller?tab=profile&pwerr=len'); exit; }
    if (($_POST['password']??'') !== ($_POST['password2']??''))          { header('Location: /seller?tab=profile&pwerr=match'); exit; }
    auth_set_password($u['id'], $_POST['password']);
    header('Location: /seller?tab=profile&pw=1'); exit;
}

// ── Require products (needed for listing/status helpers) ─────────────────────
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/invoice.php';
require_once __DIR__.'/inc/orders.php';

// ── Delete listing (owner only) ────────────────────────────────────────────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='delete_listing') {
    $lid = $_POST['lid'] ?? '';
    $uid = $_SESSION['uid'] ?? '';
    if ($lid && $uid !== '' && vestra_listing_owner($lid) === $uid) {
        vestra_save_listings(array_values(array_filter(vestra_listings(), fn($p) => ($p['id']??'') !== $lid)));
    }
    header('Location: /seller?tab=listings&deleted=1'); exit;
}

// ── Update listing (owner only; text fields, image preserved) ─────────────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='update_listing') {
    $lid = $_POST['lid'] ?? '';
    $uid = $_SESSION['uid'] ?? '';
    $one = fn($s) => trim(preg_replace('/\s+/',' ', str_replace(["\r","\n"],' ',(string)$s)));
    if ($lid && $uid !== '' && vestra_listing_owner($lid) === $uid) {
        $brand = $one($_POST['brand']??''); $name = $one($_POST['name']??''); $origin = $one($_POST['origin']??'');
        if ($brand === '' || $name === '' || $origin === '' || empty($_POST['origin_confirm'])) {
            header('Location: /seller?tab=edit&lid='.urlencode($lid).'&err=1'); exit;
        }
        $list = vestra_listings();
        $tiers = [];
        foreach([['t1min','t1price'],['t2min','t2price'],['t3min','t3price']] as $pair){
            $min=(int)($_POST[$pair[0]]??0); $price=(float)($_POST[$pair[1]]??0);
            if($min>0&&$price>0) $tiers[]=['min'=>$min,'price'=>round($price,2)];
        }
        usort($tiers, fn($a,$b) => $a['min']<=>$b['min']);
        $moq = max(1,(int)($_POST['moq']??1));
        $mode = in_array($_POST['mode']??'',['fixed','sale','offer'],true) ? $_POST['mode'] : 'fixed';
        if (!$tiers) { header('Location: /seller?tab=edit&lid='.urlencode($lid).'&err=1'); exit; }
        if ($tiers[0]['min'] > $moq) $tiers[0]['min'] = $moq;
        foreach ($list as &$p) {
            if (($p['id']??'') !== $lid) continue;
            $p['brand']  = $brand;
            $p['name']   = $name;
            $p['cat']    = $one($_POST['cat']??$p['cat']);
            $p['sku']    = $one($_POST['sku']??$p['sku']);
            $p['unit']   = $one($_POST['unit']??$p['unit']);
            $p['moq']    = $moq;
            $p['mode']   = $mode;
            $p['desc']   = $one($_POST['desc']??'');
            $p['origin'] = $origin;
            $p['tiers']  = $tiers;
            if($mode==='sale') $p['list']=round((float)($_POST['list']??0),2);
            $p['offers'] = !empty($_POST['allow_offers']) && $mode!=='offer';
            $colors = array_values(array_intersect((array)($_POST['colors']??[]), array_keys(vestra_colors())));
            if($colors) $p['colors']=$colors; else unset($p['colors']);
            /* Pack size + minimum colour count — same fields the curated catalog uses */
            $step = max(0,(int)($_POST['size_step']??0));
            if($step>1) $p['size_step']=$step; else unset($p['size_step']);
            $minC = max(0,(int)($_POST['min_colors']??0));
            if($minC>0 && $colors && $minC<=count($colors)) $p['min_colors']=$minC; else unset($p['min_colors']);
            $newImgs = vestra_collect_photo_uploads('photos', 6);
            if($newImgs){ $p['images']=$newImgs; $p['image']=$newImgs[0]; }
            break;
        }
        unset($p);
        vestra_save_listings($list);
    }
    header('Location: /seller?tab=listings&updated=1'); exit;
}

// ── Bulk price / MOQ editor: retune the seller's OWN listings in one submit ───
// Only ever touches listings whose seller_uid matches the signed-in seller; other
// sellers' rows and the built-in demo products are never reachable from here.
// Fields are keyed by product id (moq[id], mode[id], list[id], t1min[id]…t3price[id]);
// empty tier pairs are ignored so clearing a box never wipes existing pricing.
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='save_prices') {
    $uid = $_SESSION['uid'] ?? '';
    if ($uid !== '') {
        $moqIn=(array)($_POST['moq']??[]); $modeIn=(array)($_POST['mode']??[]); $listIn=(array)($_POST['list']??[]);
        $tminIn=[(array)($_POST['t1min']??[]),(array)($_POST['t2min']??[]),(array)($_POST['t3min']??[])];
        $tprIn =[(array)($_POST['t1price']??[]),(array)($_POST['t2price']??[]),(array)($_POST['t3price']??[])];
        $list=vestra_listings(); $n=0;
        foreach($list as &$p){
            $id=(string)($p['id']??'');
            if(($p['seller_uid']??'')!==$uid) continue;            // ownership guard
            if(!isset($moqIn[$id]) && !isset($modeIn[$id])) continue; // row not in this form
            $tiers=[];
            for($i=0;$i<3;$i++){
                $mn=(string)($tminIn[$i][$id]??''); $pr=(string)($tprIn[$i][$id]??'');
                if($mn!=='' && $pr!=='' && (float)$pr>0) $tiers[]=['min'=>max(1,(int)$mn),'price'=>round((float)$pr,2)];
            }
            usort($tiers,fn($a,$b)=>$a['min']<=>$b['min']);
            if(isset($moqIn[$id]) && $moqIn[$id]!=='') $p['moq']=max(1,(int)$moqIn[$id]);
            if(in_array($modeIn[$id]??'',['fixed','sale','offer'],true)) $p['mode']=$modeIn[$id];
            if(($p['mode']??'')==='sale' && isset($listIn[$id]) && $listIn[$id]!=='') $p['list']=round((float)$listIn[$id],2);
            if($tiers){
                if($tiers[0]['min'] > ($p['moq']??1)) $tiers[0]['min']=(int)($p['moq']??1);
                $p['tiers']=$tiers;
            }
            $n++;
        }
        unset($p);
        if($n) vestra_save_listings($list);
    }
    header('Location: /seller?tab=prices&saved=1'); exit;
}

// ── Ship order (only if this seller's SKUs are actually in the order) ─────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='ship_order') {
    $ref = $_POST['ref'] ?? '';
    $uid = $_SESSION['uid'] ?? '';
    $mySkus = array_column(vestra_seller_listings($uid), 'sku');
    $ownsOrder = false; $orderRow = null;
    foreach (vestra_read_csv('orders.csv') as $row) {
        if (($row['ref']??'') === $ref && vestra_order_has_seller_sku($row, $mySkus)) { $ownsOrder = true; $orderRow = $row; break; }
    }
    if ($ref && $ownsOrder) {
        $st = vestra_read_json('order_statuses.json');
        $tracking = trim($_POST['tracking']??'');
        $st[$ref] = array_merge($st[$ref] ?? [], ['status'=>'shipped','tracking'=>$tracking,'shipped_at'=>date('c')]);
        $st[$ref]['history'][] = vestra_order_history_entry('shipped', 'seller', $tracking ? "Tracking: {$tracking}" : '');
        vestra_write_json('order_statuses.json', $st);
        /* Shipped card into the buyer's conversation */
        $buyerAcc = auth_find($orderRow['email'] ?? '');
        if ($buyerAcc) {
            require_once __DIR__.'/inc/messages.php';
            vestra_msg_post_system($buyerAcc['id'], $uid, '', [
                'kind'=>'order','status'=>'shipped','ref'=>$ref,'tracking'=>$tracking,
            ]);
        }
        /* Push ping to the buyer's installed devices */
        if ($buyerAcc) {
            require_once __DIR__.'/inc/push.php';
            vestra_push_send($buyerAcc['id'], 'VESTRA — order shipped 🚚',
                'Order '.$ref.($tracking !== '' ? ' · Tracking: '.$tracking : '').' is on its way.', '/buyer?tab=orders');
        }
        /* Email buyer */
        require_once __DIR__.'/inc/notify.php';
        if (!empty($orderRow['email'])) {
            $trackLine = $tracking ? "\nTracking: {$tracking}" : '';
            vestra_send_mail($orderRow['email'], "VESTRA — your order {$ref} has shipped",
              "Hello ".($orderRow['name']?:$orderRow['company']).",\n\nGreat news — your VESTRA order has been shipped!\n\nOrder ref: {$ref}{$trackLine}\n\nOnce you receive and inspect the goods, please confirm receipt in your buyer dashboard to release payment to the seller:\nhttps://vestrasales.com/buyer?tab=orders\n\n— VESTRA · vestrasales.com");
        }
    }
    header('Location: /seller?tab=orders&shipped=1'); exit;
}

// ── Edit tracking / note on an order at any time (not just once, at ship time) ─
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='update_order_note') {
    $ref = $_POST['ref'] ?? '';
    $uid = $_SESSION['uid'] ?? '';
    $mySkus = array_column(vestra_seller_listings($uid), 'sku');
    $ownsOrder = false;
    foreach (vestra_read_csv('orders.csv') as $row) {
        if (($row['ref']??'') === $ref && vestra_order_has_seller_sku($row, $mySkus)) { $ownsOrder = true; break; }
    }
    if ($ref && $ownsOrder) {
        $st = vestra_read_json('order_statuses.json');
        $tracking = trim($_POST['tracking']??''); $note = trim($_POST['seller_note']??'');
        $st[$ref] = array_merge($st[$ref] ?? [], ['tracking'=>$tracking,'seller_note'=>$note]);
        $st[$ref]['history'][] = vestra_order_history_entry($st[$ref]['status'] ?? 'pending', 'seller', t('Tracking/note updated'));
        vestra_write_json('order_statuses.json', $st);
    }
    header('Location: /seller?tab=orders&view='.urlencode($ref).'&noted=1'); exit;
}

// ── Seller confirms the buyer's BANK payment arrived → mark paid + charge commission ─
// Bank-transfer orders only: escrow orders are auto-paid via Stripe (commission already
// taken as the application fee), so this never applies to them.
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='confirm_paid') {
    $ref = $_POST['ref'] ?? '';
    $uid = $_SESSION['uid'] ?? '';
    $mySkus = array_column(vestra_seller_listings($uid), 'sku');
    $ownsOrder = false; $orderRow = null;
    foreach (vestra_read_csv('orders.csv') as $row) {
        if (($row['ref']??'') === $ref && vestra_order_has_seller_sku($row, $mySkus)) { $ownsOrder = true; $orderRow = $row; break; }
    }
    require_once __DIR__.'/inc/escrow.php';
    $isEscrow = (bool) escrow_get($ref);
    if ($ref && $ownsOrder && !$isEscrow) {
        $st = vestra_read_json('order_statuses.json');
        $prev = $st[$ref]['status'] ?? 'pending';
        if (!in_array($prev, ['paid','shipped','completed'], true)) {
            $st[$ref] = array_merge($st[$ref] ?? [], ['status'=>'paid','paid_at'=>date('c')]);
            $st[$ref]['history'][] = vestra_order_history_entry('paid', 'seller', t('Payment confirmed by seller'));
            vestra_write_json('order_statuses.json', $st);
            require_once __DIR__.'/inc/commission.php';
            vestra_charge_order_commission($ref, vestra_order_lines($orderRow)['lines']);
            require_once __DIR__.'/inc/notify.php';
            if (!empty($orderRow['email'])) {
                vestra_send_mail($orderRow['email'], "VESTRA — payment confirmed for order {$ref}",
                  "Hello ".($orderRow['name']?:($orderRow['company']?:'there')).",\n\nThe seller has confirmed your payment for order {$ref}. Your goods are being prepared — you'll get tracking as soon as it ships.\n\nTrack your order: https://vestrasales.com/buyer?tab=orders\n\n— VESTRA · vestrasales.com");
            }
            $buyerAcc = auth_find($orderRow['email'] ?? '');
            if ($buyerAcc) {
                require_once __DIR__.'/inc/messages.php';
                vestra_msg_post_system($buyerAcc['id'], $uid, '', ['kind'=>'order','status'=>'paid','ref'=>$ref]);
                require_once __DIR__.'/inc/push.php';
                vestra_push_send($buyerAcc['id'], 'VESTRA — payment confirmed 💶',
                    'Order '.$ref.' — payment received. Your goods are being prepared.', '/buyer?tab=orders');
            }
        }
    }
    header('Location: /seller?tab=orders&msg=paid'); exit;
}

// ── Seller marks a shipped order completed/delivered (bank orders; escrow completion
// is the buyer's confirm-receipt, which releases the held funds) ──────────────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='complete_order') {
    $ref = $_POST['ref'] ?? '';
    $uid = $_SESSION['uid'] ?? '';
    $mySkus = array_column(vestra_seller_listings($uid), 'sku');
    $ownsOrder = false; $orderRow = null;
    foreach (vestra_read_csv('orders.csv') as $row) {
        if (($row['ref']??'') === $ref && vestra_order_has_seller_sku($row, $mySkus)) { $ownsOrder = true; $orderRow = $row; break; }
    }
    require_once __DIR__.'/inc/escrow.php';
    if ($ref && $ownsOrder && !escrow_get($ref)) {
        $st = vestra_read_json('order_statuses.json');
        $st[$ref] = array_merge($st[$ref] ?? [], ['status'=>'completed','completed_at'=>date('c')]);
        $st[$ref]['history'][] = vestra_order_history_entry('completed', 'seller');
        vestra_write_json('order_statuses.json', $st);
        require_once __DIR__.'/inc/notify.php';
        if (!empty($orderRow['email'])) {
            vestra_send_mail($orderRow['email'], "VESTRA — order {$ref} completed",
              "Hello ".($orderRow['name']?:($orderRow['company']?:'there')).",\n\nYour order {$ref} has been marked completed. Thank you for trading on VESTRA.\n\n— VESTRA · vestrasales.com");
        }
    }
    header('Location: /seller?tab=orders&msg=completed'); exit;
}

// ── Upload KYB document ───────────────────────────────────────────────────────
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='upload_doc') {
    $req_id = preg_replace('/[^a-f0-9]/','', $_POST['req_id']??'');
    $file   = $_FILES['doc_file'] ?? null;
    if ($req_id && $file) {
        $ok = auth_upload_doc($_SESSION['uid'], $req_id, $file);
        header('Location: /seller?tab=kyc&'.($ok?'uploaded=1':'upload_err=1')); exit;
    }
    header('Location: /seller?tab=kyc'); exit;
}

// ── Respond to offer (owner of the offered SKU only) ───────────────────────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='offer_respond') {
    $ref    = trim($_POST['ref'] ?? '');
    $uid    = $_SESSION['uid'] ?? '';
    $action = $_POST['response'] ?? '';
    $ctr    = round((float)($_POST['counter_price']??0), 2);
    $ownsOffer = false; $offerRow = null; $offerListing = null;
    foreach (vestra_read_csv('offers.csv') as $row) {
        if (($row['ref']??'') !== $ref) continue;
        $offerListing = vestra_listing_by_sku($row['sku'] ?? '');
        $ownsOffer = $offerListing && ($offerListing['seller_uid'] ?? '') === $uid && $uid !== '';
        $offerRow = $row;
        break;
    }
    if ($ref && $ownsOffer && in_array($action, ['accept','decline','counter'], true) && !($action==='counter'&&$ctr<=0)) {
        $rs = vestra_read_json('offer_responses.json');
        $rs[$ref] = ['status'=>$action, 'counter_price'=>$action==='counter'?$ctr:null, 'responded_at'=>date('c')];
        vestra_write_json('offer_responses.json', $rs);
        /* Mirror the response into the buyer's Messages inbox as a highlighted card. */
        $buyerAcc = auth_find($offerRow['email'] ?? '');
        $prodName = trim(($offerListing['brand']??'').' '.($offerListing['name']??''));
        if ($buyerAcc) {
            require_once __DIR__.'/inc/messages.php';
            vestra_msg_post_system($buyerAcc['id'], $uid, $offerListing['id'] ?? '', [
                'kind'=>'offer_response', 'ref'=>$ref, 'status'=>$action,
                'counter_price'=>$action==='counter'?$ctr:null,
                'product'=>$prodName,
            ]);
            require_once __DIR__.'/inc/push.php';
            $pushTxt = match($action){
                'accept'  => ['VESTRA — offer accepted ✓', $prodName.' — your offer was accepted. Invoice is ready.'],
                'counter' => ['VESTRA — counter offer ↩', $prodName.' — seller counters at €'.number_format($ctr,2).'/unit.'],
                default   => ['VESTRA — offer declined', $prodName.' — the seller declined this offer.'],
            };
            vestra_push_send($buyerAcc['id'], $pushTxt[0], $pushTxt[1], '/buyer?tab=offers');
        }
        /* Email the buyer directly — works whether or not they have a VESTRA account,
           since offers always collect a work email. This is the buyer's only notice if
           they don't have push enabled or don't happen to check the site. Reads as
           coming from the seller (Reply-To -> them) in the buyer's own saved language. */
        require_once __DIR__.'/inc/notify.php';
        if (!empty($offerRow['email']) && filter_var($offerRow['email'], FILTER_VALIDATE_EMAIL)) {
            $buyerName = $offerRow['company'] ?? ($buyerAcc['name'] ?? 'there');
            $meSeller = auth_user();
            $sellerLabel = $meSeller ? ($meSeller['company'] ?: ($meSeller['name'] ?: 'VESTRA')) : 'VESTRA';
            [$mSubject, $mBody, $mOpts] = vestra_tpl_offer_response(vestra_user_lang($buyerAcc), $action, $buyerName, $prodName, $ref, $action==='counter'?$ctr:null);
            vestra_send_mail($offerRow['email'], $mSubject, $mBody, $meSeller['email']??'', $sellerLabel, null, '', $mOpts);
        }
        /* Accepted offer = a confirmed sale — auto-generate this seller's PDF invoice,
           enriched with the buyer's full account details when they have one. */
        if ($action === 'accept') {
            require_once __DIR__.'/inc/invoice.php';
            $sellerAcc = null;
            foreach (auth_accounts() as $a) { if (($a['id']??'')===$uid) { $sellerAcc=$a; break; } }
            $buyerFull = $buyerAcc ?: auth_find($offerRow['email'] ?? '');
            $orderMeta = [
                'ref'=>$ref, 'date'=>date('c'),
                'buyer'=>[
                    'company'=>$offerRow['company'] ?? ($buyerFull['company'] ?? ''),
                    'vat'=>$buyerFull['vat_id'] ?? '', 'name'=>$buyerFull['name'] ?? '',
                    'email'=>$offerRow['email'] ?? '', 'country'=>$buyerFull['country'] ?? '',
                    'address'=>$buyerFull['address'] ?? '',
                ],
            ];
            $items = [[
                'sku'=>$offerListing['sku'] ?? '', 'brand'=>$offerListing['brand'] ?? '', 'name'=>$offerListing['name'] ?? '',
                'colors'=>[], 'qty'=>(int)($offerRow['qty']??0), 'unit'=>(float)($offerRow['offer_unit']??0),
                'line'=>(float)($offerRow['offer_total']??($offerRow['qty']*$offerRow['offer_unit'])),
            ]];
            vestra_ensure_invoice($orderMeta, $items, $sellerAcc);
        }
    }
    header('Location: /seller?tab=offers&responded=1'); exit;
}

// ── Messaging (reply in a thread, or start one with a buyer who offered/ordered) ─
require __DIR__.'/inc/messages.php';
if (($_GET['tab']??'')==='messages' && !empty($_GET['thread']) && isset($_GET['poll'])) {
    $t = vestra_msg_find_thread($_GET['thread']);
    $ok = $t && ($t['seller_uid']??'') === ($_SESSION['uid']??'');
    header('Content-Type: application/json');
    echo json_encode(['last' => $ok ? ($t['last_at']??'') : '']);
    exit;
}
// Mark an opened thread read here, before head.php computes the nav badge below —
// otherwise the badge still shows the just-read thread as unread for this whole page view.
if (($_GET['tab']??'')==='messages' && !empty($_GET['thread']) && $_SERVER['REQUEST_METHOD']==='GET') {
    $t = vestra_msg_find_thread($_GET['thread']);
    if ($t && !empty($_SESSION['uid']) && ($t['seller_uid']??'') === $_SESSION['uid']) {
        vestra_msg_mark_read($_GET['thread'], $_SESSION['uid']);
    }
}
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='send_message') {
    $uid = $_SESSION['uid'] ?? '';
    $tid = $_POST['thread_id'] ?? '';
    $body = $_POST['body'] ?? '';
    if ($tid === '' && !empty($_POST['buyer_uid'])) {
        // Seller initiates: only towards a real buyer account, only about the seller's own listing.
        $buyerAcc  = null;
        foreach (auth_accounts() as $a) { if (($a['id']??'')===$_POST['buyer_uid'] && ($a['type']??'')==='buyer') { $buyerAcc=$a; break; } }
        $listingId = $_POST['listing_id'] ?? '';
        $listing   = $listingId !== '' ? vestra_listing_by_id($listingId) : null;
        if ($listing && ($listing['seller_uid']??'') !== $uid) $listing = null;
        if ($buyerAcc && $uid !== '') {
            $res = vestra_msg_send($buyerAcc['id'], $uid, $uid, $body, $listing ? $listingId : '');
            $tid = $res['thread_id'] ?? vestra_msg_thread_id($buyerAcc['id'], $uid, $listing ? $listingId : '');
            if (!$res['ok']) { header('Location: /seller?tab=messages&thread='.urlencode($tid).'&msgerr='.($res['error']==='flagged'?$res['flag']:'empty')); exit; }
            header('Location: /seller?tab=messages&thread='.urlencode($tid)); exit;
        }
        header('Location: /seller?tab=messages'); exit;
    }
    $thread = vestra_msg_find_thread($tid);
    if ($thread && $uid !== '' && ($thread['seller_uid']??'') === $uid) {
        $res = vestra_msg_send($thread['buyer_uid'], $thread['seller_uid'], $uid, $body, $thread['listing_id']??'');
        if (!$res['ok']) { header('Location: /seller?tab=messages&thread='.urlencode($tid).'&msgerr='.($res['error']==='flagged'?$res['flag']:'empty')); exit; }
    }
    header('Location: /seller?tab=messages&thread='.urlencode($tid)); exit;
}

/* ── Seller customer outreach: own SMTP + own customer list + one-by-one send ── */
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && in_array(($_POST['_action']??''),['seller_save_smtp','seller_send_test','seller_add_lead','seller_import_leads','seller_send_one','seller_find_email','seller_discover','seller_find_all'],true)) {
  require_once __DIR__.'/inc/notify.php'; require_once __DIR__.'/inc/leads.php';
  $suid=$_SESSION['uid']??''; $sme=auth_user();
  if($suid==='' || ($sme['type']??'')!=='seller'){ if(($_POST['_action']??'')==='seller_send_one'){ header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'auth']); } else header('Location: /seller?tab=find'); exit; }
  $sact=$_POST['_action']; $sName=$sme['company']?:($sme['name']?:'Seller');
  if($sact==='seller_save_smtp'){
    $cur=vestra_seller_mail($suid); $from=trim($_POST['from_email']??''); $pass=(string)($_POST['smtp_pass']??'');
    vestra_seller_mail_save($suid,['mail_enabled'=>true,'mail_from'=>$from,'smtp_from'=>$from,
      'smtp_name'=>trim($_POST['from_name']??'')?:$sName,'smtp_host'=>trim($_POST['smtp_host']??''),
      'smtp_port'=>(int)($_POST['smtp_port']??587)?:587,'smtp_user'=>trim($_POST['smtp_user']??'')?:$from,
      'smtp_pass'=>$pass!==''?$pass:(string)($cur['smtp_pass']??''),'mail_api_provider'=>'brevo','mail_api_key'=>(string)($cur['mail_api_key']??''),
      'finder_provider'=>trim($_POST['finder_provider']??'hunter')?:'hunter',
      'finder_key'=>(($fk=trim($_POST['finder_key']??''))!=='')?$fk:(string)($cur['finder_key']??''),
      'ai_key'=>(($ak=trim($_POST['ai_key']??''))!=='')?$ak:(string)($cur['ai_key']??'')]);
    header('Location: /seller?tab=find&msg=smtp_saved'); exit;
  }
  if($sact==='seller_find_email'){
    $sc=vestra_seller_mail($suid); $lid=$_POST['lid']??''; $leads=vestra_leads(); $found='';
    foreach($leads as &$l){ if(($l['id']??'')!==$lid || (string)($l['owner_uid']??'')!==$suid) continue;
      if(($l['email']??'')==='') { $found=vestra_find_email((string)($l['website']??''),(string)($sc['finder_key']??''),(string)($sc['finder_provider']??'hunter')); if($found!=='') $l['email']=$found; }
      break; }
    unset($l); vestra_save_leads($leads);
    header('Location: /seller?tab=find&msg='.($found!==''?'found_ok':'found_none')); exit;
  }
  if($sact==='seller_send_test'){
    $to=trim($_POST['test_to']??''); $sc=vestra_seller_mail($suid);
    $ok=filter_var($to,FILTER_VALIDATE_EMAIL) && vestra_send_mail($to,'VESTRA — test email',"Test from your VESTRA sending setup. If you received this, it works. \xE2\x9C\x93\n\n— ".$sName,'',(string)($sc['smtp_name']??''),$sc);
    header('Location: /seller?tab=find&msg='.($ok?'test_ok':'test_fail')); exit;
  }
  if($sact==='seller_add_lead'){
    $company=trim($_POST['company']??''); $email=strtolower(trim($_POST['email']??''));
    if($company!==''){ $leads=vestra_leads();
      $leads[]=['id'=>'LD'.strtoupper(bin2hex(random_bytes(4))),'added_at'=>date('c'),'owner_uid'=>$suid,
        'company'=>$company,'contact_name'=>trim($_POST['contact_name']??''),'email'=>(filter_var($email,FILTER_VALIDATE_EMAIL)?$email:''),
        'country'=>trim($_POST['country']??''),'website'=>trim($_POST['website']??''),'source'=>'Seller','category'=>trim($_POST['category']??''),
        'notes'=>'','status'=>'new','last_contacted_at'=>'','unsub_token'=>bin2hex(random_bytes(16))];
      vestra_save_leads($leads); }
    header('Location: /seller?tab=find&msg=lead_added'); exit;
  }
  if($sact==='seller_import_leads'){
    $added=0;$skipped=0;
    if(!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) [$added,$skipped]=vestra_lead_import_csv($_FILES['csv']['tmp_name'],$suid);
    header('Location: /seller?tab=find&msg=lead_import&added='.$added); exit;
  }
  if($sact==='seller_discover'){
    @set_time_limit(0);
    $country=trim($_POST['disc_country']??''); $city=trim($_POST['disc_city']??'');
    $rows=$country!==''?vestra_discover_osm($country,$city,60):[];
    $osmOk=$country!==''?vestra_osm_ok():true;
    [$addedRows]=$rows?vestra_leads_add($rows,$suid):[[],0];
    header('Location: /seller?tab=find&msg=discover&n='.count($addedRows).'&found='.count($rows).($osmOk?'':'&osmfail=1')); exit;
  }
  if($sact==='seller_find_all'){
    @set_time_limit(0); $sc=vestra_seller_mail($suid); $leads=vestra_leads(); $n=0;
    foreach($leads as &$l){ if((string)($l['owner_uid']??'')!==$suid) continue;
      if(($l['email']??'')!=='' || ($l['website']??'')==='') continue;
      $e=vestra_find_email((string)$l['website'],(string)($sc['finder_key']??''),(string)($sc['finder_provider']??'hunter'));
      if($e!==''){ $l['email']=$e; $n++; } }
    unset($l); vestra_save_leads($leads);
    header('Location: /seller?tab=find&msg=found_bulk&n='.$n); exit;
  }
  if($sact==='seller_send_one'){
    header('Content-Type: application/json');
    $sc=vestra_seller_mail($suid);
    if(!vestra_seller_can_send($sc)){ echo json_encode(['ok'=>false,'error'=>'nosender']); exit; }
    $lid=$_POST['lead_id']??''; $tpl=vestra_lead_template(); $leads=vestra_leads(); $res=['ok'=>false,'company'=>'','email'=>'','error'=>'notfound'];
    $heroImg=($tpl['img']??'')!==''?'https://vestrasales.com'.$tpl['img']:'';
    foreach($leads as &$l){
      if(($l['id']??'')!==$lid || (string)($l['owner_uid']??'')!==$suid) continue;
      $res['company']=$l['company']??''; $res['email']=$l['email']??''; $res['error']='';
      if(($l['status']??'')==='unsubscribed'){ $res['error']='unsub'; break; }
      if(!filter_var($l['email']??'',FILTER_VALIDATE_EMAIL)){ $res['error']='noemail'; break; }
      $pair=(($_POST['ai']??'')==='1')?vestra_ai_personalize($l,$tpl,$sName,(string)($sc['ai_key']??'')):null;
      [$subject,$body]=$pair!==null?$pair:vestra_lead_render_email($l,$tpl);
      if(vestra_send_mail($l['email'],$subject,$body,'',$sName,$sc,$heroImg)){ $res['ok']=true; if(($l['status']??'new')==='new') $l['status']='contacted'; $l['last_contacted_at']=date('c'); }
      else { $res['error']='send'; }
      break;
    }
    unset($l); vestra_save_leads($leads); echo json_encode($res); exit;
  }
}

require __DIR__.'/inc/dash.php';
$PAGE=t('Seller panel'); $NAV='sell'; require __DIR__.'/inc/head.php';

if(!$MEMBER){
  echo '<div class="wrap"><div class="gate" style="margin:48px auto;max-width:460px;text-align:center">
    <h3 style="margin:0 0 6px">'.t('Seller workspace').'</h3>
    <p style="color:var(--mut);margin:0 0 20px">'.t('Sign in to manage your listings, orders and offers.').'</p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
    <a class="btn btn-p" href="/login?back=/seller">'.t('Sign in').'</a>
    <a class="btn btn-o" href="/register">'.t('Create account').'</a></div></div></div>';
  require __DIR__.'/inc/foot.php'; exit;
}

$tab=$_GET['tab']??'overview';
$uid=$_SESSION['uid']??'';
$listings=vestra_seller_listings($uid);
$mySkus=array_column($listings,'sku');
$orders=array_values(array_filter(vestra_read_csv('orders.csv'), fn($o)=>vestra_order_has_seller_sku($o,$mySkus)));
$offers=array_values(array_filter(vestra_read_csv('offers.csv'), fn($o)=>in_array($o['sku']??'',$mySkus,true)));
$offerResp=vestra_read_json('offer_responses.json');
$orderSt=vestra_read_json('order_statuses.json');
$cats=vestra_cats();
$_ms  = $AUTH_USER['membership_status'] ?? '';
$_kyb = $AUTH_USER['kyb_status'] ?? '';
$canPublish = in_array($_ms, ['trialing','active'], true) || ($_ms === '' && $_kyb === 'approved');
$quotaLimit = vestra_seller_monthly_quota_limit($AUTH_USER['membership_tier'] ?? '');
$quotaUsed  = vestra_seller_monthly_quota_used($AUTH_USER);
$quotaLeft  = $quotaLimit !== null ? max(0, $quotaLimit - $quotaUsed) : null;

$tabTitle = match($tab) {
    'add'      => t('Add a product'),
    'edit'     => t('Edit listing'),
    'listings' => t('My listings'),
    'prices'   => t('Prices & MOQ'),
    'orders'   => t('Orders'),
    'offers'   => t('Offers received'),
    'messages' => t('Messages'),
    'find'     => t('Find customers'),
    'kyc'      => t('Verification'),
    'profile'  => t('My profile'),
    default    => t('Overview'),
};
dash_open('seller',$tab, $tabTitle, $tab==='overview'?t('Welcome back — here is your activity'):'');

// ── OVERVIEW ──────────────────────────────────────────────────────────────────
if($tab==='overview'){
  $rev=0; foreach($orders as $o){ $rev+=(float)($o['total']??0); }
  $liveListings = count(array_filter($listings, fn($p) => ($p['status']??'approved')==='approved'));
  $pendingOffers = count(array_filter($offers, fn($o) => empty($offerResp[$o['ref']??''])));
  stat_cards([
    ['<span class="acc">'.$liveListings.'</span>', t('Live listings')],
    [count($orders), t('Orders')],
    ['<span class="'.($pendingOffers?'acc':'').'">'.$pendingOffers.'</span>', t('Offers pending')],
    [eur($rev), t('Order value')],
  ]);
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Quick actions').'</h3></div>
    <div class="quickrow">
      <a class="btn btn-p btn-sm" href="/seller?tab=add">＋ '.t('Add a product').'</a>
      <a class="btn btn-o btn-sm" href="/seller?tab=offers">'.t('Manage offers').'</a>
      <a class="btn btn-o btn-sm" href="/seller?tab=orders">'.t('View orders').'</a>
      <a class="btn btn-o btn-sm" href="/requests">'.t('Browse buyer requests').'</a>
      <a class="btn btn-o btn-sm" href="/showroom?id='.urlencode($AUTH_USER['id']??'').'" target="_blank">'.t('My showroom').'</a>
    </div></div>';
  if(empty($AUTH_USER['bank_iban'])){
    echo '<div class="banner info">🏦 '.t('Add your bank details so buyers can pay you directly — shown on the automatic PDF invoices your buyers download.').
      ' <a class="acc" href="/seller?tab=profile">'.t('Add now →').'</a></div>';
  }
  if(empty($AUTH_USER['stripe_commission_pm'])){
    $myRate = vestra_seller_commission_rate($AUTH_USER['membership_tier'] ?? '');
    echo '<div class="banner info">💳 '.sprintf(t('Add a commission card so your %s%% platform commission is collected automatically when orders are paid — no manual invoicing.'), number_format($myRate*100,1)).
      ' <a class="acc" href="/seller?tab=profile">'.t('Add now →').'</a></div>';
  }
  $msBadge = match($_ms){
    'trialing' => '<span class="status open">⏳ '.t('Trial').'</span>',
    'active'   => '<span class="status offers">✓ '.t('Active').'</span>',
    'past_due' => '<span class="status" style="color:var(--bad)">⚠ '.t('Past due').'</span>',
    'canceled' => '<span class="status" style="color:var(--mut)">✗ '.t('Canceled').'</span>',
    default    => $canPublish ? '<span class="status offers">✓ '.t('Active').'</span>' : '<span class="status open">— '.t('None').'</span>',
  };
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Membership').'</h3>'.$msBadge.'</div>';
  if ($_ms === 'trialing') echo '<p class="hint">'.t('Your free trial is running. First charge in 30 days, cancel anytime from Stripe.').'</p>';
  elseif ($_ms === 'past_due') echo '<p class="hint" style="color:var(--bad)">'.t('Your last payment failed — please update your payment method to keep your listings active.').'</p>';
  elseif ($_ms === 'canceled') echo '<p class="hint">'.t('Your membership has ended. Reactivate to publish and keep listings live.').' <a class="acc" href="/membership">'.t('View plans').'</a></p>';
  elseif (!$canPublish) echo '<p class="hint">'.t('Choose a plan to start publishing products.').' <a class="acc" href="/membership">'.t('View membership plans').'</a></p>';
  else echo '<p class="hint">'.t('Legacy account — no active subscription required.').'</p>';
  echo '</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Verification').'</h3>';
  $kybSt = $AUTH_USER['kyb_status']??'pending';
  $kybBadge = match($kybSt){
    'approved'  => '<span class="status offers">✓ '.t('Verified seller').'</span>',
    'suspended' => '<span class="status" style="color:var(--bad)">⊘ '.t('Suspended').'</span>',
    default     => '<span class="status open">'.t('Pending review').'</span>',
  };
  echo $kybBadge.'</div>';
  if ($kybSt==='approved') echo '<p class="hint">'.t('Business KYB complete. Your listings show the "Verified" badge to buyers.').'</p>';
  elseif ($kybSt==='suspended') echo '<p class="hint" style="color:var(--bad)">'.t('Your account has been suspended. Contact support for details.').'</p>';
  else echo '<p class="hint">'.t('Upload your verification documents to earn the "Verified" badge.').' <a class="acc" href="/seller?tab=kyc">'.t('Verification').'</a></p>';
  echo '</div>';

// ── ADD PRODUCT ───────────────────────────────────────────────────────────────
} elseif($tab==='add'){
  if (!$canPublish) {
    echo '<div class="panelcard" style="text-align:center;padding:44px 24px">
      <h3 style="margin:0 0 10px">'.t('Active membership required').'</h3>
      <p style="color:var(--mut);margin:0 0 20px">'.t('You need an active seller membership to publish products.').'</p>
      <a class="btn btn-p" href="/membership">'.t('View membership plans').'</a>
    </div>';
  } else {
  $added=isset($_GET['added']);
  if($added) echo '<div class="banner ok">✓ '.t('Product added — it is now live in the').' <a class="acc" href="/shop">'.t('catalog').'</a>.</div>';
  if(($_GET['err']??'')==='quota') echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">'.sprintf(t("You've used all %d listings included in your plan this month. It renews on the 1st, or you can upgrade for more."), (int)$quotaLimit).' <a class="acc" href="/membership">'.t('View membership plans').'</a></div>';
  elseif(isset($_GET['err'])) echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">'.t('Please fill in all required fields, including at least one price tier.').'</div>';
  if($quotaLimit !== null): ?>
  <div class="banner info" style="margin-bottom:14px"><?= sprintf(t('This month: <b>%d of %d</b> listings used. Resets on the 1st.'), $quotaUsed, $quotaLimit) ?></div>
  <?php endif; ?>
  <?php if($quotaLeft === 0): ?>
  <div class="panelcard" style="text-align:center;padding:44px 24px">
    <h3 style="margin:0 0 10px"><?= t('Monthly listing quota reached') ?></h3>
    <p style="color:var(--mut);margin:0 0 20px"><?= sprintf(t("You've used all %d listings included in your plan this month. It renews on the 1st, or you can upgrade for more."), (int)$quotaLimit) ?></p>
    <a class="btn btn-p" href="/membership"><?= t('View membership plans') ?></a>
  </div>
  <?php else: ?>
  <div class="panelcard">
    <form method="post" action="/seller-add" class="addform" enctype="multipart/form-data">
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
      <div class="frow">
        <div><label><?= t('Brand') ?> *</label><input name="brand" required placeholder="<?= htmlspecialchars(t('e.g. Lacoste / Your label')) ?>"></div>
        <div><label><?= t('Product name') ?> *</label><input name="name" required placeholder="Classic Piqué Polo"></div>
      </div>
      <div class="frow four">
        <div><label><?= t('Category') ?></label>
          <select name="cat">
            <?php foreach(vestra_all_cats() as $grp=>$items): ?>
              <optgroup label="<?=htmlspecialchars(t($grp))?>"><?php foreach($items as $c) echo '<option value="'.htmlspecialchars($c).'">'.htmlspecialchars(t($c)).'</option>'; ?></optgroup>
            <?php endforeach; ?>
            <option value="Other"><?= t('Other') ?></option>
          </select></div>
        <div><label>SKU</label><input name="sku" placeholder="<?= htmlspecialchars(t('auto if blank')) ?>"></div>
        <div><label><?= t('Unit') ?></label><select name="unit"><option>pc</option><option>pack</option><option>set</option><option>carton</option></select></div>
        <div><label><?= t('Min order (MOQ)') ?> *</label><input type="number" name="moq" min="1" value="12" required></div>
      </div>
      <label><?= t('Pricing mode') ?> *</label>
      <div class="moderow">
        <label class="moderadio"><input type="radio" name="mode" value="fixed" checked onchange="modeUI()"> <span><?= t('Fixed (tiered)') ?></span></label>
        <label class="moderadio"><input type="radio" name="mode" value="sale" onchange="modeUI()"> <span><?= t('Sale (discount)') ?></span></label>
        <label class="moderadio"><input type="radio" name="mode" value="offer" onchange="modeUI()"> <span><?= t('Make-an-offer') ?></span></label>
      </div>
      <div id="listrow" class="frow" style="display:none">
        <div><label><?= t('List price (original €/unit)') ?></label><input type="number" step="0.01" name="list" placeholder="<?= htmlspecialchars(t('for strikethrough')) ?>"></div>
      </div>
      <label><?= t('Tiered pricing — quantity → unit price (€)') ?></label>
      <div class="tiergrid">
        <div><span class="hint"><?= t('Tier 1 qty') ?></span><input type="number" name="t1min" value="12"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t1price" placeholder="34.00" required></div>
        <div><span class="hint"><?= t('Tier 2 qty') ?></span><input type="number" name="t2min" placeholder="60"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t2price" placeholder="29.50"></div>
        <div><span class="hint"><?= t('Tier 3 qty') ?></span><input type="number" name="t3min" placeholder="180"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t3price" placeholder="25.00"></div>
      </div>
      <p class="hint" id="offerhint" style="display:none"><?= t('For make-an-offer, tiers are shown as indicative guidance only.') ?></p>
      <label id="offercheck" style="display:flex;gap:9px;align-items:center;margin:12px 0 0;cursor:pointer">
        <input type="checkbox" name="allow_offers" value="1">
        <span><?= t('Allow buyers to make an offer on this product (they can negotiate alongside the listed price)') ?></span>
      </label>
      <label style="display:flex;gap:9px;align-items:center;margin:10px 0 0;cursor:pointer">
        <input type="checkbox" name="group_enable" value="1" onchange="groupUI()">
        <span><?= t('Open this product for <b>group buying</b> — let small buyers pool their orders to reach your MOQ together, then unlock your best tier price') ?></span>
      </label>
      <div id="grouprow" class="frow" style="display:none;margin-top:8px">
        <div><label><?= t('Group target (qty to unlock)') ?></label><input type="number" name="group_target" min="1" placeholder="<?= htmlspecialchars(t('blank = your top tier qty')) ?>"></div>
        <div><label><?= t('Pool open for (days)') ?></label><input type="number" name="group_days" min="1" max="90" value="14"></div>
      </div>
      <div>
        <label><?= t('Available colours') ?> <span class="hint">(<?= t('optional') ?>)</span></label>
        <div class="colorpick">
          <?php foreach(vestra_colors() as $cn=>$hex): ?>
            <label class="colorchip"><input type="checkbox" name="colors[]" value="<?= htmlspecialchars($cn) ?>"><span class="cdot" style="background:<?= $hex ?>"></span><?= htmlspecialchars(t($cn)) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="frow">
        <div><label><?= t('Pack size') ?> <span class="hint">(<?= t('optional — buyers order in multiples, e.g. 10 = packs of 10, 8 = 8+8 cartons') ?>)</span></label>
          <input type="number" name="size_step" min="0" placeholder="10"></div>
        <div><label><?= t('Minimum colours per order') ?> <span class="hint">(<?= t('optional — buyer must pick at least this many of the colours above') ?>)</span></label>
          <input type="number" name="min_colors" min="0" placeholder="4"></div>
      </div>
      <div class="frow">
        <div><label><?= t('Description') ?></label><textarea name="desc" rows="2" placeholder="<?= htmlspecialchars(t('Sizes, colours, condition…')) ?>"></textarea></div>
      </div>
      <div>
        <label><?= t('Product photos') ?> <span class="hint">(<?= t('up to 6 · JPG/PNG/WebP · ≤5 MB each — first photo is the main image') ?>)</span></label>
        <div class="phgrid">
          <?php for($pi=1;$pi<=6;$pi++): ?>
          <label class="ph-slot" id="phs<?=$pi?>">
            <input type="file" name="photos[]" accept="image/png,image/jpeg,image/webp" onchange="phPrev(this,<?=$pi?>)">
            <img class="ph-preview" id="pp<?=$pi?>" style="display:none" alt="">
            <span class="ph-label" id="phl<?=$pi?>">📷 <?=$pi?></span>
          </label>
          <?php endfor; ?>
        </div>
      </div>
      <div>
        <label><?= t('Line sheet / price list') ?> <span class="hint"><?= t('(Excel or CSV · ≤8 MB · optional)') ?></span></label>
        <input type="file" name="sheet" accept=".xlsx,.xls,.csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
      </div>
      <div class="frow">
        <div><label><?= t('Origin / authenticity note') ?> *</label><input name="origin" required placeholder="<?= htmlspecialchars(t('e.g. EEA stock · invoice on request')) ?>"></div>
        <div><label><?= t('Seller name') ?></label><input name="seller" value="<?= htmlspecialchars($AUTH_USER['company']??'My Wholesale Co.') ?>"></div>
      </div>
      <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;margin:14px 0 0;background:rgba(201,168,106,.07);border:1px solid rgba(201,168,106,.25);border-radius:8px;padding:12px 14px">
        <input type="checkbox" name="origin_confirm" required style="margin-top:2px;flex-shrink:0">
        <span style="font-size:13px;line-height:1.5"><?= t('I confirm this product is genuine, lawfully acquired, and was first placed on the EEA market by or with the brand owner\'s consent. I accept full liability for any third-party claims against VESTRA arising from a breach of this declaration.') ?> <a href="/legal#seller" target="_blank" style="color:var(--acc)"><?= t('Seller Agreement §3–5') ?></a></span>
      </label>
      <button class="btn btn-p" type="submit" style="margin-top:14px"><?= t('Publish product') ?></button>
    </form>
  </div>
  <script>
  function modeUI(){ var m=document.querySelector('input[name=mode]:checked').value;
    document.getElementById('listrow').style.display=m==='sale'?'grid':'none';
    document.getElementById('offerhint').style.display=m==='offer'?'block':'none'; }
  function groupUI(){ document.getElementById('grouprow').style.display=document.querySelector('input[name=group_enable]').checked?'grid':'none'; }
  function phPrev(inp,n){ var f=inp.files[0]; if(!f) return;
    var r=new FileReader(); r.onload=function(e){ var img=document.getElementById('pp'+n); img.src=e.target.result; img.style.display='block'; document.getElementById('phl'+n).style.display='none'; }; r.readAsDataURL(f); }
  modeUI(); groupUI();
  </script>
  <?php endif; ?>
  <?php
  }

// ── EDIT LISTING ──────────────────────────────────────────────────────────────
} elseif($tab==='edit'){
  $lid = $_GET['lid'] ?? '';
  $ep = vestra_listing_by_id($lid);
  if ($ep && ($ep['seller_uid']??'') !== $uid) $ep = null;
  if (!$ep) { echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">'.t('Listing not found.').'</div>'; }
  else {
  if(isset($_GET['err'])) echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">'.t('Please fill in all required fields, including at least one price tier.').'</div>';
  $t1=$ep['tiers'][0]??null; $t2=$ep['tiers'][1]??null; $t3=$ep['tiers'][2]??null;
  ?>
  <?php if(isset($_GET['updated'])): ?><div class="banner ok">✓ <?= t('Listing updated.') ?></div><?php endif; ?>
  <div class="panelcard">
    <form method="post" action="/seller?tab=listings" class="addform" enctype="multipart/form-data">
      <input type="hidden" name="_action" value="update_listing">
      <input type="hidden" name="lid" value="<?= htmlspecialchars($lid) ?>">
      <div class="frow">
        <div><label><?= t('Brand') ?> *</label><input name="brand" required value="<?= htmlspecialchars($ep['brand']??'') ?>"></div>
        <div><label><?= t('Product name') ?> *</label><input name="name" required value="<?= htmlspecialchars($ep['name']??'') ?>"></div>
      </div>
      <div class="frow four">
        <div><label><?= t('Category') ?></label>
          <select name="cat">
            <?php foreach(vestra_all_cats() as $grp=>$items): ?>
              <optgroup label="<?=htmlspecialchars(t($grp))?>"><?php foreach($items as $c): ?>
                <option value="<?=htmlspecialchars($c)?>"<?= $c===($ep['cat']??'')?' selected':'' ?>><?=htmlspecialchars(t($c))?></option>
              <?php endforeach; ?></optgroup>
            <?php endforeach; ?>
            <option value="Other"<?= !in_array($ep['cat']??'',array_merge(...array_values(vestra_all_cats())))?' selected':'' ?>><?= t('Other') ?></option>
          </select></div>
        <div><label>SKU</label><input name="sku" value="<?= htmlspecialchars($ep['sku']??'') ?>"></div>
        <div><label><?= t('Unit') ?></label>
          <select name="unit">
            <?php foreach(['pc','pack','set','carton'] as $u): ?>
              <option<?= ($ep['unit']??'pc')===$u?' selected':'' ?>><?=$u?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label><?= t('Min order (MOQ)') ?> *</label><input type="number" name="moq" min="1" value="<?= (int)($ep['moq']??1) ?>" required></div>
      </div>
      <label><?= t('Pricing mode') ?></label>
      <div class="moderow">
        <?php foreach(['fixed'=>t('Fixed (tiered)'),'sale'=>t('Sale (discount)'),'offer'=>t('Make-an-offer')] as $mv=>$ml): ?>
          <label class="moderadio"><input type="radio" name="mode" value="<?=$mv?>" <?=($ep['mode']??'fixed')===$mv?'checked':''?> onchange="modeUI()"> <span><?=$ml?></span></label>
        <?php endforeach; ?>
      </div>
      <div id="listrow" class="frow" style="display:<?=($ep['mode']??'')!=='sale'?'none':'grid'?>">
        <div><label><?= t('List price (original €/unit)') ?></label><input type="number" step="0.01" name="list" value="<?= number_format((float)($ep['list']??0),2,'.','') ?>"></div>
      </div>
      <label><?= t('Tiered pricing') ?></label>
      <div class="tiergrid">
        <div><span class="hint"><?= t('Tier 1 qty') ?></span><input type="number" name="t1min" value="<?= $t1?$t1['min']:'' ?>"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t1price" value="<?= $t1?number_format($t1['price'],2,'.',''):'' ?>"></div>
        <div><span class="hint"><?= t('Tier 2 qty') ?></span><input type="number" name="t2min" value="<?= $t2?$t2['min']:'' ?>"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t2price" value="<?= $t2?number_format($t2['price'],2,'.',''):'' ?>"></div>
        <div><span class="hint"><?= t('Tier 3 qty') ?></span><input type="number" name="t3min" value="<?= $t3?$t3['min']:'' ?>"></div>
        <div><span class="hint">€ / <?= t('unit') ?></span><input type="number" step="0.01" name="t3price" value="<?= $t3?number_format($t3['price'],2,'.',''):'' ?>"></div>
      </div>
      <p class="hint" id="offerhint" style="display:<?=($ep['mode']??'')!=='offer'?'none':'block'?>"><?= t('For make-an-offer, tiers are shown as indicative guidance only.') ?></p>
      <div>
        <label><?= t('Available colours') ?></label>
        <div class="colorpick">
          <?php $epColors=(array)($ep['colors']??[]); foreach(vestra_colors() as $cn=>$hex): ?>
            <label class="colorchip"><input type="checkbox" name="colors[]" value="<?= htmlspecialchars($cn) ?>" <?= in_array($cn,$epColors,true)?'checked':'' ?>><span class="cdot" style="background:<?= $hex ?>"></span><?= htmlspecialchars(t($cn)) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="frow">
        <div><label><?= t('Pack size') ?> <span class="hint">(<?= t('optional — buyers order in multiples, e.g. 10 = packs of 10, 8 = 8+8 cartons') ?>)</span></label>
          <input type="number" name="size_step" min="0" value="<?= (int)($ep['size_step']??0) ?: '' ?>" placeholder="10"></div>
        <div><label><?= t('Minimum colours per order') ?> <span class="hint">(<?= t('optional — buyer must pick at least this many of the colours above') ?>)</span></label>
          <input type="number" name="min_colors" min="0" value="<?= (int)($ep['min_colors']??0) ?: '' ?>" placeholder="4"></div>
      </div>
      <div class="frow">
        <div><label><?= t('Description') ?></label><textarea name="desc" rows="2"><?= htmlspecialchars($ep['desc']??'') ?></textarea></div>
        <div><label><?= t('Origin / authenticity note') ?> *</label><input name="origin" required value="<?= htmlspecialchars($ep['origin']??'') ?>"></div>
      </div>
      <div>
        <label><?= t('Product photos') ?> <span class="hint"><?= t('Leave empty to keep the current photos.') ?></span></label>
        <?php $epImgs=(array)($ep['images'] ?? (!empty($ep['image'])?[$ep['image']]:[])); if($epImgs): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin:4px 0 10px">
            <?php foreach($epImgs as $ei): ?><img src="<?= htmlspecialchars($ei) ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--line)"><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="phgrid">
          <?php for($pi=1;$pi<=6;$pi++): ?>
          <label class="ph-slot" id="ephs<?=$pi?>">
            <input type="file" name="photos[]" accept="image/png,image/jpeg,image/webp" onchange="ephPrev(this,<?=$pi?>)">
            <img class="ph-preview" id="epp<?=$pi?>" style="display:none" alt="">
            <span class="ph-label" id="ephl<?=$pi?>">📷 <?=$pi?></span>
          </label>
          <?php endfor; ?>
        </div>
      </div>
      <label style="display:flex;gap:9px;align-items:center;margin:10px 0 14px;cursor:pointer">
        <input type="checkbox" name="allow_offers" value="1" <?= !empty($ep['offers'])?'checked':'' ?>>
        <span><?= t('Allow buyers to make an offer on this product') ?></span>
      </label>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-p" type="submit"><?= t('Save changes') ?></button>
        <a class="btn btn-o" href="/seller?tab=listings"><?= t('Cancel') ?></a>
      </div>
    </form>
  </div>
  <?php }
  ?><script>
  function modeUI(){var m=document.querySelector('input[name=mode]:checked').value;
    document.getElementById('listrow').style.display=m==='sale'?'grid':'none';
    document.getElementById('offerhint').style.display=m==='offer'?'block':'none';}
  function ephPrev(inp,n){ var f=inp.files[0]; if(!f) return;
    var r=new FileReader(); r.onload=function(e){ var img=document.getElementById('epp'+n); img.src=e.target.result; img.style.display='block'; document.getElementById('ephl'+n).style.display='none'; }; r.readAsDataURL(f); }
  </script><?php

// ── MY LISTINGS ───────────────────────────────────────────────────────────────
} elseif($tab==='listings'){
  if(isset($_GET['deleted'])) echo '<div class="banner ok">✓ '.t('Listing deleted.').'</div>';
  if(isset($_GET['updated'])) echo '<div class="banner ok">✓ '.t('Listing updated.').'</div>';
  if(isset($_GET['pending'])) echo '<div class="banner" style="background:rgba(240,192,96,.12);border:1px solid rgba(240,192,96,.35);color:#f0c060;margin-bottom:12px">⏳ '.t('Your listing has been submitted and is awaiting admin approval. It will appear in the catalog once approved.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('My listings').'</h3><a class="btn btn-p btn-sm" href="/seller?tab=add">＋ '.t('Add product').'</a></div>';
  if (!$listings) {
    dash_empty(t('No listings yet. Add your first product to get started.'));
  } else {
  echo '<table class="ctable"><thead><tr><th>'.t('Product').'</th><th>'.t('Mode').'</th><th>MOQ</th><th class="r">'.t('From').'</th><th>'.t('Status').'</th><th></th></tr></thead><tbody>';
  foreach($listings as $p){
    echo '<tr><td><b>'.htmlspecialchars($p['brand']??'').'</b> — '.htmlspecialchars($p['name']??'').'<div class="hint">SKU '.htmlspecialchars($p['sku']??'').'</div></td>'.
      '<td><span class="modechip '.($p['mode']??'fixed').'">'.($p['mode']??'fixed').'</span></td><td>'.($p['moq']??1).' '.htmlspecialchars($p['unit']??'pc').'</td>'.
      '<td class="r">'.(($p['mode']??'')==='offer'?'—':eur(vestra_from_price($p))).'</td>'.
      '<td>'.match($p['status']??'approved'){'pending'=>'<span class="status open">⏳ '.t('Pending approval').'</span>','rejected'=>'<span class="status" style="background:rgba(239,154,154,.12);color:var(--bad);border:1px solid rgba(239,154,154,.3)">✗ '.t('Rejected').'</span>','suspended'=>'<span class="status" style="background:rgba(239,154,154,.12);color:var(--bad);border:1px solid rgba(239,154,154,.3)">⊘ '.t('Suspended').'</span>',default=>'<span class="status offers">✓ '.t('Live').'</span>'}.'</td>'.
      '<td class="r" style="white-space:nowrap">'.
      '<a class="btn btn-o btn-sm" href="/seller?tab=edit&lid='.urlencode($p['id']).'">'.t('Edit').'</a> '.
      '<form method="post" action="/seller?tab=listings" style="display:inline">
        <input type="hidden" name="_action" value="delete_listing">
        <input type="hidden" name="lid" value="'.htmlspecialchars($p['id']).'">
        <button class="btn btn-o btn-sm" type="submit" style="color:var(--bad);border-color:rgba(239,154,154,.3)" onclick="return confirm(\''.htmlspecialchars(t('Delete this listing?')).'\')">'.t('Delete').'</button></form>'.
      '</td></tr>';
  }
  echo '</tbody></table>';
  }
  echo '</div>';

// ── PRICES & MOQ (bulk editor for the seller's own listings) ──────────────────
} elseif($tab==='prices'){
  if(isset($_GET['saved'])) echo '<div class="banner ok">✓ '.t('Prices & MOQ saved — live on the catalog now.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>💶 '.t('Prices & MOQ').'</h3><a class="btn btn-o btn-sm" href="/seller?tab=add">＋ '.t('Add product').'</a></div>';
  echo '<p class="hint" style="margin:-4px 0 14px;max-width:640px">'.t('Retune the minimum order quantity and tiered wholesale pricing for all your products at once, then save once. Leave a tier\'s two boxes empty to drop it — the lowest tier price is shown to buyers as the “from” price. Changes go live on the catalog immediately.').'</p>';
  if(!$listings){
    dash_empty(t('No listings yet. Add your first product to get started.'));
  } else {
    echo '<form method="post" action="/seller?tab=prices" class="pricetable">';
    echo '<style>.pricetable .ctable th,.pricetable .ctable td{padding:12px 7px}.pricetable .ctable input,.pricetable .ctable select{font-size:13px}</style>';
    echo '<input type="hidden" name="_action" value="save_prices">';
    echo '<div style="overflow-x:auto"><table class="ctable"><thead><tr>'.
      '<th>'.t('Product').'</th><th>'.t('Mode').'</th><th>MOQ</th><th>'.t('List').' €<div class="hint" style="font-weight:400">'.t('sale only').'</div></th>'.
      '<th>'.t('Tier').' 1 — min → €</th><th>'.t('Tier').' 2</th><th>'.t('Tier').' 3</th><th class="r">'.t('From').'</th></tr></thead><tbody>';
    foreach($listings as $p){
      $id=htmlspecialchars((string)($p['id']??'')); $tt=array_values($p['tiers']??[]);
      $lv=(isset($p['list'])&&$p['list']!=='')?(string)$p['list']:'';
      echo '<tr><td style="min-width:180px"><b>'.htmlspecialchars($p['brand']??'').'</b> — '.htmlspecialchars($p['name']??'').
        '<div class="hint">SKU '.htmlspecialchars($p['sku']??'').'</div></td>'.
        '<td><select name="mode['.$id.']" style="padding:6px 8px">';
      foreach(['fixed','sale','offer'] as $m) echo '<option'.(($p['mode']??'fixed')===$m?' selected':'').'>'.$m.'</option>';
      echo '</select></td>'.
        '<td><input type="number" min="1" name="moq['.$id.']" value="'.(int)($p['moq']??1).'" style="width:66px;padding:7px 8px"></td>'.
        '<td><input type="number" step="0.01" min="0" name="list['.$id.']" value="'.htmlspecialchars($lv).'" placeholder="—" style="width:74px;padding:7px 8px"></td>';
      for($i=0;$i<3;$i++){
        echo '<td><div style="display:flex;gap:4px">'.
          '<input type="number" min="1" name="t'.($i+1).'min['.$id.']" value="'.htmlspecialchars((string)($tt[$i]['min']??'')).'" placeholder="min" style="width:56px;padding:7px 6px">'.
          '<input type="number" step="0.01" min="0" name="t'.($i+1).'price['.$id.']" value="'.htmlspecialchars((string)($tt[$i]['price']??'')).'" placeholder="€" style="width:64px;padding:7px 6px"></div></td>';
      }
      echo '<td class="r"><b>'.(($p['mode']??'')==='offer'?'—':eur(vestra_from_price($p))).'</b></td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<div style="margin-top:16px"><button class="btn btn-p" type="submit">💾 '.t('Save all prices').'</button></div>';
    echo '</form>';
  }
  echo '</div>';

// ── ORDERS ────────────────────────────────────────────────────────────────────
} elseif($tab==='orders'){
  require_once __DIR__.'/inc/escrow.php';
  $viewRef = $_GET['view'] ?? '';
  $viewOrder = $viewRef ? current(array_filter($orders, fn($o)=>($o['ref']??'')===$viewRef)) : null;
  if ($viewOrder) {
    if(isset($_GET['noted'])) echo '<div class="banner ok">✓ '.t('Saved.').'</div>';
    echo vestra_render_order_detail($viewOrder, $orderSt[$viewRef] ?? ['status'=>'pending'], 'seller', $uid, '/seller?tab=orders', '/seller?tab=orders');
  } else {
  if(isset($_GET['shipped'])) echo '<div class="banner ok">✓ '.t('Order marked as shipped.').'</div>';
  if(($_GET['msg']??'')==='paid') echo '<div class="banner ok">✓ '.t('Payment confirmed — commission charged and the buyer notified.').'</div>';
  if(($_GET['msg']??'')==='completed') echo '<div class="banner ok">✓ '.t('Order marked as completed.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Orders').'</h3></div>';
  if(!$orders) dash_empty(t('No orders yet. Orders placed by buyers appear here.'));
  else {
    echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Buyer').'</th><th>'.t('Items').'</th><th class="r">'.t('Total').'</th><th>'.t('Status').'</th><th></th></tr></thead><tbody>';
    foreach($orders as $o){
      $ref = $o['ref']??'';
      $st  = $orderSt[$ref]['status'] ?? 'pending';
      $er  = escrow_get($ref);
      $escBadge = $er ? '<div style="margin-top:3px">'.escrow_badge($er['status']??'').'</div>' : '';
      $stClass = $st==='completed'?'offers':($st==='shipped'?'offers':($st==='paid'?'offers':'open'));
      $stLabel  = $st==='completed'?t('Completed'):($st==='shipped'?t('Shipped'):($st==='paid'?t('Paid — ship now'):t('Awaiting payment')));
      echo '<tr><td><a class="acc" href="/seller?tab=orders&view='.urlencode($ref).'"><b>'.htmlspecialchars($ref).'</b></a><div class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</div></td>'.
        '<td>'.htmlspecialchars($o['company']??'').'<div class="hint">'.htmlspecialchars($o['email']??'').'</div></td>'.
        '<td class="hint">'.htmlspecialchars($o['items']??'').'</td>'.
        '<td class="r">'.eur($o['total']??0).'</td>'.
        '<td><span class="status '.$stClass.'">'.$stLabel.'</span>'.$escBadge.
          ($st==='shipped'&&!empty($orderSt[$ref]['tracking'])?'<div class="hint">'.htmlspecialchars($orderSt[$ref]['tracking']).'</div>':'').'</td>'.
        '<td>';
      if ($st==='pending' && !$er) {
        echo '<form method="post" action="/seller?tab=orders" style="margin-bottom:5px" onsubmit="return confirm('.htmlspecialchars(json_encode(t('Confirm the buyer bank payment has arrived? Your platform commission will be charged.')),ENT_QUOTES).')">
            <input type="hidden" name="_action" value="confirm_paid">
            <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
            <button class="btn btn-o btn-sm" type="submit">✓ '.t('Confirm payment').'</button>
          </form>';
      }
      if (in_array($st,['pending','paid'],true)) {
        echo '<details class="respdetails"><summary class="btn btn-p btn-sm">🚚 '.t('Ship').'</summary>
          <form method="post" action="/seller?tab=orders" class="shipform">
            <input type="hidden" name="_action" value="ship_order">
            <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
            <input name="tracking" placeholder="'.htmlspecialchars(t('Tracking number (optional)')).'">
            <button class="btn btn-p btn-sm" type="submit">'.t('Mark shipped').'</button>
          </form></details>';
      }
      if ($st==='shipped' && !$er) {
        echo '<form method="post" action="/seller?tab=orders" style="margin-top:5px" onsubmit="return confirm('.htmlspecialchars(json_encode(t('Mark this order as completed?')),ENT_QUOTES).')">
            <input type="hidden" name="_action" value="complete_order">
            <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
            <button class="btn btn-o btn-sm" type="submit">✓ '.t('Mark completed').'</button>
          </form>';
      }
      foreach(vestra_invoices_for_ref($ref) as $iv){
        if($iv['seller_key']!==$uid) continue;
        echo '<a class="btn btn-o btn-sm" href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener" style="margin-top:4px">📄 '.t('Invoice').' '.htmlspecialchars($iv['no']).'</a>';
      }
      echo '</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '</div>';
  }

// ── OFFERS RECEIVED ───────────────────────────────────────────────────────────
} elseif($tab==='offers'){
  if(isset($_GET['responded'])) echo '<div class="banner ok">✓ '.t('Response sent to buyer.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Offers received').'</h3></div>';
  if(!$offers) dash_empty(t('No offers yet. Buyer offers on your make-an-offer items appear here.'));
  else {
    echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Product').'</th><th>'.t('Buyer').'</th><th class="r">'.t('Offer').'</th><th>'.t('Status').'</th><th></th></tr></thead><tbody>';
    foreach($offers as $o){
      $ref = $o['ref']??'';
      $resp = $offerResp[$ref] ?? null;
      if($resp){
        $rSt = $resp['status']; $rsClass='offers'; $rsLabel='✓ '.t('Accepted');
        if($rSt==='decline'){$rsClass=''; $rsLabel='✗ '.t('Declined');}
        if($rSt==='counter'){$rsClass='open'; $rsLabel='↩ '.t('Counter').': '.eur($resp['counter_price']??0);}
        $stCell='<span class="status '.$rsClass.'">'.$rsLabel.'</span>';
        $actCell='<span class="hint">'.substr($resp['responded_at']??'',0,10).'</span>';
        if($rSt==='accept'){
          foreach(vestra_invoices_for_ref($ref) as $iv){
            if($iv['seller_key']!==$uid) continue;
            $actCell.='<br><a class="btn btn-o btn-sm" href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener" style="margin-top:4px">📄 '.t('Invoice').' '.htmlspecialchars($iv['no']).'</a>';
          }
        }
      } else {
        $stCell='<span class="status open">'.t('Pending').'</span>';
        $actCell='<details class="respdetails"><summary class="btn btn-o btn-sm">'.t('Respond').'</summary>
          <form method="post" action="/seller?tab=offers" class="respform">
            <input type="hidden" name="_action" value="offer_respond">
            <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
            <div class="resprow">
              <button class="btn btn-sm" name="response" value="accept" type="submit" style="background:rgba(122,214,160,.15);border-color:rgba(122,214,160,.4);color:var(--ok)">✓ '.t('Accept').'</button>
              <button class="btn btn-sm" name="response" value="decline" type="submit" style="background:rgba(239,154,154,.1);border-color:rgba(239,154,154,.35);color:var(--bad)">✗ '.t('Decline').'</button>
            </div>
            <div class="resprow" style="margin-top:8px;gap:6px">
              <input type="number" step="0.01" name="counter_price" placeholder="'.htmlspecialchars(t('Counter €/unit')).'" style="max-width:140px">
              <button class="btn btn-o btn-sm" name="response" value="counter" type="submit">↩ '.t('Counter offer').'</button>
            </div>
          </form></details>';
      }
      /* One-click chat with this buyer (only when the offer email maps to a real account) */
      $obAcc = auth_find($o['email'] ?? '');
      if ($obAcc && ($obAcc['type']??'')==='buyer') {
        $obListing = vestra_listing_by_sku($o['sku'] ?? '');
        $obTid = vestra_msg_thread_id($obAcc['id'], $uid, $obListing['id'] ?? '');
        if (vestra_msg_find_thread($obTid)) {
          $actCell .= ' <a class="btn btn-o btn-sm" href="/seller?tab=messages&thread='.urlencode($obTid).'" style="margin-top:6px">💬 '.t('Message buyer').'</a>';
        } else {
          $actCell .= '<details class="respdetails" style="margin-top:6px"><summary class="btn btn-o btn-sm">💬 '.t('Message buyer').'</summary>
            <form method="post" action="/seller?tab=offers" class="respform">
              <input type="hidden" name="_action" value="send_message">
              <input type="hidden" name="buyer_uid" value="'.htmlspecialchars($obAcc['id']).'">
              <input type="hidden" name="listing_id" value="'.htmlspecialchars($obListing['id'] ?? '').'">
              <textarea name="body" rows="2" placeholder="'.htmlspecialchars(t('Write a message…')).'" required style="width:100%"></textarea>
              <button class="btn btn-p btn-sm" type="submit" style="margin-top:8px">'.t('Send').'</button>
            </form></details>';
        }
      }
      echo '<tr><td><b>'.htmlspecialchars($ref).'</b><div class="hint">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</div></td>'.
        '<td>'.htmlspecialchars($o['product']??'').'<div class="hint">'.htmlspecialchars($o['qty']??'').'× SKU '.htmlspecialchars($o['sku']??'').'</div></td>'.
        '<td>'.htmlspecialchars($o['company']??'').'<div class="hint">'.htmlspecialchars($o['email']??'').'</div></td>'.
        '<td class="r"><b>'.eur($o['offer_unit']??0).'</b>/u<div class="hint">'.eur($o['offer_total']??0).' total</div></td>'.
        '<td>'.$stCell.'</td><td>'.$actCell.'</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '</div>';

// ── MESSAGES ──────────────────────────────────────────────────────────────────
} elseif($tab==='messages'){
  $tid = $_GET['thread'] ?? '';
  $thread = $tid ? vestra_msg_find_thread($tid) : null;
  if ($thread && ($thread['seller_uid']??'') !== $uid) $thread = null;
  // (marked read earlier, before head.php rendered the nav badge — see top of file)
  $myThreads = vestra_msg_my_threads($uid);

  $listHtml = '<div class="mssearch"><input id="mfilter" placeholder="'.htmlspecialchars(t('Search conversations…')).'" oninput="mFilterThreads(this.value)"></div>';
  if (!$myThreads) {
    $listHtml .= '<p class="hint" style="padding:0 10px">'.t('No messages yet. Buyers can message you from a product page.').'</p>';
  } else {
    $listHtml .= '<div class="threadlist" id="mThreadList">';
    foreach ($myThreads as $th) {
      $last = end($th['messages']);
      $unread = vestra_msg_unread($th, $uid);
      $name = vestra_msg_counterpart_label($th, $uid);
      $listHtml .= '<a class="threadrow'.($unread?' unread':'').($th['id']===$tid?' active':'').'" data-name="'.htmlspecialchars(mb_strtolower($name)).'" href="/seller?tab=messages&thread='.urlencode($th['id']).'">
        <div class="tr-name">'.htmlspecialchars($name).($unread?' <span class="tr-dot"></span>':'').'</div>
        <div class="tr-snippet">'.htmlspecialchars(vestra_msg_snippet($last ?: [])).'</div>
        <div class="tr-time">'.htmlspecialchars(substr($th['last_at']??'', 0, 16)).'</div>
      </a>';
    }
    $listHtml .= '</div>';
  }

  if ($thread) {
    $ctp = vestra_msg_counterpart_label($thread, $uid);
    $msgerr = $_GET['msgerr'] ?? '';
    $mainHtml = '<div class="msghead"><h3 style="margin:0">'.htmlspecialchars($ctp).'</h3><a class="btn btn-o btn-sm" href="/seller?tab=messages">← '.t('Back').'</a></div>';
    if (!empty($thread['listing_id']) && ($tl = vestra_listing_by_id($thread['listing_id']))) {
      $mainHtml .= '<p class="hint" style="margin:10px 18px 0">🔗 <a class="acc" href="/product?id='.urlencode($thread['listing_id']).'">'.htmlspecialchars(trim(($tl['brand']??'').' — '.($tl['name']??''), ' —')).'</a></p>';
    }
    if (in_array($msgerr, ['email','iban','phone'], true)) {
      $mainHtml .= '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin:10px 18px 0">⚠ '.t('For your safety, sharing email addresses, phone numbers, or bank/IBAN details is not allowed here — all communication and payment must stay on VESTRA so buyer protection still applies. Your message was not sent.').'</div>';
    }
    $mainHtml .= '<div class="msgthread" id="mThread">';
    foreach ($thread['messages'] as $m) {
      if (($m['from']??'') === 'system') { $mainHtml .= vestra_msg_system_html($m, 'seller'); continue; }
      $mine = ($m['from']??'') === $uid;
      $mainHtml .= '<div class="msgbubblewrap '.($mine?'mine':'').'"><div class="msgbubble '.($mine?'mine':'').'">'.
        nl2br(htmlspecialchars($m['text']??'')).
        '<div class="msgtime">'.htmlspecialchars(substr($m['at']??'',0,16)).'</div></div></div>';
    }
    $mainHtml .= '</div>';
    $mainHtml .= '<form method="post" action="/seller?tab=messages" class="msgcompose">
      <input type="hidden" name="_action" value="send_message">
      <input type="hidden" name="thread_id" value="'.htmlspecialchars($tid).'">
      <textarea name="body" rows="2" placeholder="'.htmlspecialchars(t('Write a message…')).'" required></textarea>
      <button class="btn btn-p" type="submit">'.t('Send').'</button>
    </form>';
    $mainHtml .= '<p class="hint" style="padding:0 18px 14px">'.t('Do not share email addresses, phone numbers, or bank details — keep all communication and payment on VESTRA.').'</p>';
  } else {
    $mainHtml = '<div class="msempty">'.t('Select a conversation to start messaging.').'</div>';
  }

  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Messages').'</h3></div>';
  echo '<div class="msgshell'.($thread?' has-thread':'').'"><div class="mslist">'.$listHtml.'</div><div class="msmain">'.$mainHtml.'</div></div>';
  echo '</div>';
  echo '<script>function mFilterThreads(q){q=q.toLowerCase();document.querySelectorAll("#mThreadList .threadrow").forEach(function(r){r.style.display=r.dataset.name.indexOf(q)>-1?"":"none";});}</script>';
  if ($thread) {
    echo '<script>var mt=document.getElementById("mThread");if(mt)mt.scrollTop=mt.scrollHeight;'.
      '(function(){var last='.json_encode($thread['last_at']??'').';'.
      'setInterval(function(){fetch("/seller?tab=messages&thread='.urlencode($tid).'&poll=1",{cache:"no-store"})'.
      '.then(function(r){return r.json()}).then(function(d){if(d.last&&d.last!==last)location.reload()})'.
      '.catch(function(){})},15000)})();</script>';
  }

// ── VERIFICATION / KYB ────────────────────────────────────────────────────────
} elseif($tab==='find'){
  require_once __DIR__.'/inc/notify.php'; require_once __DIR__.'/inc/leads.php';
  $me=$AUTH_USER ?? auth_user();
  $myMail=vestra_seller_mail($uid); $mailReady=vestra_seller_can_send($myMail);
  $myLeads=array_reverse(vestra_leads_by_owner($uid));
  $fmsg=$_GET['msg']??'';
  $fmsgs=['smtp_saved'=>'✓ Your sending email & keys are saved — send a test to confirm.','test_ok'=>'✓ Test sent — check your inbox.','test_fail'=>'Test failed — check your SMTP host / username / password.','lead_added'=>'✓ Customer added.','lead_import'=>'✓ Customers imported.','found_ok'=>'✓ Real email found and added.','found_none'=>'No email found on that website — add it manually.'];
  if($fmsg==='discover'){ $df=(int)($_GET['found']??0); $dn=(int)($_GET['n']??0); $osmFail=($_GET['osmfail']??'')==='1';
    $fmsgs['discover']=$osmFail?'⚠ OpenStreetMap could not be reached (all mirrors failed) — this is a temporary outage, not "no shops". Please try again in a minute.'
      :($df===0?'No shops found in that city — try the local spelling (e.g. “Milano”, “Köln”) or a bigger nearby city.'
      :('✓ '.$df.' retailer(s) found, '.$dn.' new added'.($dn===0?' (all were already on your list)':'').'. Now run “🔍 Find all missing emails”.')); }
  if($fmsg==='found_bulk') $fmsgs['found_bulk']='✓ Email lookup finished — '.(int)($_GET['n']??0).' email(s) added from the shops’ own websites.';
  $sFinderOn=true;   // finding always works — free site-reading fallback (own/platform key optional)
  $sAiOn=($myMail['ai_key']??'')!=='' || vestra_ai_key()!=='';
  $inp='width:100%;padding:8px 11px;border:1px solid var(--line);border-radius:9px;background:var(--bg,#fff);color:var(--ink);font-size:13px;box-sizing:border-box';
  $lbl='display:block;font-size:11.5px;color:var(--mut);margin:0 0 4px';
  $card='border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:16px;background:var(--card,#fff)';
?>
<div style="max-width:920px">
  <?php if(isset($fmsgs[$fmsg])): ?><div style="background:#eaf7ef;border:1px solid #b9e3c9;color:#1f7a4d;padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:13.5px"><?= htmlspecialchars($fmsgs[$fmsg]) ?></div><?php endif; ?>
  <p style="color:var(--mut);font-size:13.5px;margin:0 0 18px">Find your own customers and email them a wholesale offer <b>from your own address</b>. Add or import a list, then send one by one. Every email carries a one-click unsubscribe.</p>

  <div style="<?= $card ?>;border-color:<?= $mailReady?'#b9e3c9':'var(--line)' ?>">
    <h3 style="margin:0 0 4px;font-size:15px">📤 Your sending email <?= $mailReady?'<span style="color:#1f9d63;font-size:12px">● Ready</span>':'<span style="color:#a9781a;font-size:12px">● Not set up</span>' ?></h3>
    <p style="color:var(--mut);font-size:12.5px;margin:0 0 12px">Enter your email + its SMTP login (from your email provider). <b>Gmail:</b> use an App Password. Stored securely — never shared.</p>
    <form method="post">
      <input type="hidden" name="_action" value="seller_save_smtp">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
        <div><label style="<?= $lbl ?>">From email *</label><input type="email" name="from_email" required value="<?= htmlspecialchars($myMail['mail_from']??($me['email']??'')) ?>" style="<?= $inp ?>"></div>
        <div><label style="<?= $lbl ?>">From name</label><input name="from_name" value="<?= htmlspecialchars($myMail['smtp_name']??($me['company']??'')) ?>" style="<?= $inp ?>"></div>
        <div><label style="<?= $lbl ?>">SMTP host</label><input name="smtp_host" value="<?= htmlspecialchars($myMail['smtp_host']??'') ?>" placeholder="smtp.gmail.com" style="<?= $inp ?>"></div>
        <div><label style="<?= $lbl ?>">SMTP port</label><input name="smtp_port" value="<?= htmlspecialchars((string)($myMail['smtp_port']??'587')) ?>" style="<?= $inp ?>"></div>
        <div><label style="<?= $lbl ?>">SMTP username</label><input name="smtp_user" value="<?= htmlspecialchars($myMail['smtp_user']??'') ?>" placeholder="usually your email" style="<?= $inp ?>"></div>
        <div><label style="<?= $lbl ?>">SMTP password <?= ($myMail['smtp_pass']??'')!==''?'· saved, blank = keep':'' ?></label><input type="password" name="smtp_pass" autocomplete="new-password" style="<?= $inp ?>"></div>
      </div>
      <div style="border-top:1px solid var(--line);margin:2px 0 12px;padding-top:12px">
        <div style="font-size:12.5px;font-weight:600;margin-bottom:2px">✨ Your own API keys <span style="color:var(--mut);font-weight:400">— optional; blank = use the platform's</span></div>
        <div style="color:var(--mut);font-size:11.5px;margin-bottom:8px">Use your own so your finder/AI usage is billed to you, not the platform.</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div><label style="<?= $lbl ?>">Email-finder key — Hunter.io<?= ($myMail['finder_key']??'')!==''?' · saved':'' ?></label><input type="password" name="finder_key" autocomplete="new-password" placeholder="real emails from a website" style="<?= $inp ?>"></div>
          <div><label style="<?= $lbl ?>">AI key — DeepSeek<?= ($myMail['ai_key']??'')!==''?' · saved':'' ?></label><input type="password" name="ai_key" autocomplete="new-password" placeholder="personalise each email" style="<?= $inp ?>"></div>
        </div>
      </div>
      <button class="btn btn-p btn-sm" type="submit">Save sending email &amp; keys</button>
    </form>
    <form method="post" style="margin-top:10px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="_action" value="seller_send_test">
      <div style="flex:1;min-width:220px"><label style="<?= $lbl ?>">Send a test to</label><input type="email" name="test_to" required value="<?= htmlspecialchars($me['email']??'') ?>" style="<?= $inp ?>"></div>
      <button class="btn btn-o btn-sm" type="submit">✉ Send test</button>
    </form>
  </div>

  <div style="<?= $card ?>;border-color:#b9e3c9">
    <h3 style="margin:0 0 6px;font-size:15px">🧭 Auto-discover customers <span style="color:#1f9d63;font-size:12px">● Free — no key needed</span></h3>
    <p style="color:var(--mut);font-size:12.5px;margin:0 0 12px">Pull <b>real small &amp; medium clothing shops</b> (independent &amp; multi-brand boutiques — not big chains) from OpenStreetMap straight into your list — searches a whole country at once. Then click <b>🔍 Find all missing emails</b> to fill their addresses from their own websites.</p>
    <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Searching… (whole-country can take up to a minute)';">
      <input type="hidden" name="_action" value="seller_discover">
      <div style="min-width:150px"><label style="<?= $lbl ?>">Country</label>
        <select name="disc_country" required style="<?= $inp ?>"><option value="" disabled selected>— choose —</option>
          <option>Germany</option><option>Netherlands</option><option>France</option><option>Italy</option>
          <option>Spain</option><option>United Kingdom</option><option>United States</option><option>Australia</option><option>UAE</option><option>Turkey</option></select>
      </div>
      <div style="flex:1;min-width:190px"><label style="<?= $lbl ?>">City <span style="font-weight:400">— optional, narrows the search</span></label><input name="disc_city" placeholder="leave blank for the whole country" style="<?= $inp ?>"></div>
      <button class="btn btn-p btn-sm" type="submit">🧭 Discover &amp; add</button>
    </form>
    <form method="post" style="margin-top:10px" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Looking up emails…';">
      <input type="hidden" name="_action" value="seller_find_all">
      <button class="btn btn-o btn-sm" type="submit">🔍 Find all missing emails</button>
      <span style="font-size:11px;color:var(--mut);margin-left:6px">Reads each shop's contact/imprint page. Long lists can take a while.</span>
    </form>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
    <div style="<?= $card ?>;margin:0">
      <h3 style="margin:0 0 10px;font-size:15px">＋ Add a customer</h3>
      <form method="post">
        <input type="hidden" name="_action" value="seller_add_lead">
        <div style="display:grid;gap:8px;margin-bottom:10px">
          <input name="company" required placeholder="Company *" style="<?= $inp ?>">
          <input type="email" name="email" placeholder="Email (optional)" style="<?= $inp ?>">
          <input name="country" placeholder="Country" style="<?= $inp ?>">
          <input name="website" placeholder="Website" style="<?= $inp ?>">
        </div>
        <button class="btn btn-p btn-sm" type="submit">＋ Add</button>
      </form>
    </div>
    <div style="<?= $card ?>;margin:0">
      <h3 style="margin:0 0 10px;font-size:15px">⬆ Import CSV</h3>
      <p style="color:var(--mut);font-size:12px;margin:0 0 10px">Columns: <code>company</code> required; <code>email,contact_name,country,website</code> optional. Email-less rows still import.</p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="_action" value="seller_import_leads">
        <input type="file" name="csv" accept=".csv,text/csv" required style="<?= $inp ?>;margin-bottom:10px">
        <button class="btn btn-p btn-sm" type="submit">⬆ Import</button>
      </form>
    </div>
  </div>

  <div style="<?= $card ?>">
    <h3 style="margin:0 0 10px;font-size:15px">My customers (<?= count($myLeads) ?>)</h3>
    <?php if(!$myLeads): ?><p style="color:var(--mut);font-size:13px;margin:0">No customers yet — add one or import a CSV above.</p>
    <?php else: ?>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
      <button class="btn btn-p btn-sm" type="button" onclick="sellerSend(this)" <?= $mailReady?'':'disabled title="Set up your sending email first"' ?>>▶ Send one-by-one (live)</button>
      <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--mut)"><input type="checkbox" id="sAll" onclick="document.querySelectorAll('.slc').forEach(c=>{if(!c.disabled)c.checked=this.checked})"> select all</label>
      <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--mut)" title="<?= $sAiOn?'Rewrite each email for the customer with AI':'Add your DeepSeek key above to enable' ?>"><input type="checkbox" id="sAi" <?= $sAiOn?'':'disabled' ?>> ✨ AI personalize<?= $sAiOn?'':' (add key)' ?></label>
      <span style="font-size:11.5px;color:var(--mut)">Email-less/unsubscribed can't be selected.</span>
    </div>
    <div id="sSob" style="display:none;background:var(--bg2,#f7f7fb);border-radius:10px;padding:10px 12px;margin-bottom:10px"><div id="sSobBar" style="font-weight:600;font-size:13px;margin-bottom:6px"></div><div id="sSobLog" style="max-height:200px;overflow:auto"></div></div>
    <div style="overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">
      <tr style="text-align:left;color:var(--mut);font-size:11.5px"><th style="padding:6px"></th><th style="padding:6px">Company</th><th style="padding:6px">Email</th><th style="padding:6px">Country</th><th style="padding:6px">Status</th></tr>
      <?php foreach($myLeads as $l): $noEmail=!filter_var($l['email']??'',FILTER_VALIDATE_EMAIL); $unsub=($l['status']??'')==='unsubscribed'; ?>
      <tr style="border-top:1px solid var(--line);opacity:<?= ($noEmail||$unsub)?.6:1 ?>">
        <td style="padding:6px"><input class="slc" type="checkbox" value="<?= htmlspecialchars($l['id']??'') ?>" <?= ($noEmail||$unsub)?'disabled':'' ?>></td>
        <td style="padding:6px"><b><?= htmlspecialchars($l['company']??'') ?></b><?php if(!empty($l['website'])): ?><div style="font-size:11px;color:var(--mut)"><?= htmlspecialchars($l['website']) ?></div><?php endif; ?></td>
        <td style="padding:6px;font-size:11.5px"><?php if($noEmail): ?><span style="color:#a9781a">—</span><?php if(!empty($l['website']) && $sFinderOn): ?> <form method="post" style="display:inline"><input type="hidden" name="_action" value="seller_find_email"><input type="hidden" name="lid" value="<?= htmlspecialchars($l['id']??'') ?>"><button class="btn btn-o btn-sm" style="padding:1px 7px;font-size:10.5px" type="submit">🔍 Find</button></form><?php endif; ?><?php else: ?><?= htmlspecialchars($l['email']) ?><?php endif; ?></td>
        <td style="padding:6px"><?= htmlspecialchars($l['country']??'') ?: '—' ?></td>
        <td style="padding:6px;font-size:11.5px"><?= htmlspecialchars(ucfirst($l['status']??'new')) ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </div>
</div>
<script>
function sellerSend(btn){
  var boxes=[].slice.call(document.querySelectorAll('.slc')).filter(function(c){return c.checked && !c.disabled;});
  if(!boxes.length){ alert('Select at least one customer first.'); return; }
  var ids=boxes.map(function(c){return c.value;});
  var wrap=document.getElementById('sSob'),bar=document.getElementById('sSobBar'),log=document.getElementById('sSobLog');
  var aiEl=document.getElementById('sAi'); var ai=(aiEl&&aiEl.checked&&!aiEl.disabled)?'1':'';
  wrap.style.display='block'; log.innerHTML=''; btn.disabled=true; var i=0,ok=0,fail=0;
  function next(){
    if(i>=ids.length){ bar.textContent='✓ Done — '+ok+' sent, '+fail+' failed of '+ids.length+'. Refresh for statuses.'; btn.disabled=false; return; }
    bar.textContent='Sending '+(i+1)+' / '+ids.length+(ai?' ✨':'')+'…';
    var fd=new FormData(); fd.append('_action','seller_send_one'); fd.append('lead_id',ids[i]); fd.append('ai',ai);
    fetch('/seller?tab=find',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
      var ln=document.createElement('div'); ln.style.fontSize='12px'; ln.style.padding='2px 0';
      if(d.ok){ ok++; ln.style.color='#1f9d63'; ln.textContent='✓ '+(d.company||d.email||''); }
      else { fail++; ln.style.color='#c0392b'; ln.textContent='✗ '+(d.company||d.email||'')+' — '+(d.error||'failed'); }
      log.appendChild(ln); log.scrollTop=log.scrollHeight; i++; setTimeout(next,250);
    }).catch(function(){ fail++; i++; setTimeout(next,250); });
  }
  next();
}
</script>
<?php
} elseif($tab==='kyc'){
  $kybSt    = $AUTH_USER['kyb_status'] ?? 'pending';
  $docReqs  = $AUTH_USER['doc_requests'] ?? [];
  $docTypes = auth_doc_types();
  $kybLabel = $kybSt==='approved'
    ? '<span class="status offers">✓ '.t('Verified').'</span>'
    : ($kybSt==='suspended'
        ? '<span class="status" style="color:var(--bad)">⊘ '.t('Suspended').'</span>'
        : '<span class="status open">'.t('Pending review').'</span>');
  if(isset($_GET['uploaded'])) echo '<div class="banner ok">✓ '.t('Document uploaded — the admin will review it shortly.').'</div>';
  if(isset($_GET['upload_err'])) echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:var(--bad)">'.t('Upload failed. Please check the file type (PDF/JPG/PNG/WebP, max 10 MB) and try again.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Business verification (KYB)').'</h3>'.$kybLabel.'</div>';
  echo '<p class="hint" style="margin:0 0 16px">'.t('Upload the required documents below. New sellers must provide: company registration, VAT/tax certificate, government-issued ID, and an authorization letter if you are not the company director.').' '.t('See the').' <a class="acc" href="/legal?doc=seller">'.t('Seller Agreement').'</a>.</p>';

  if(!$docReqs){
    echo '<div class="empty">'.t('No document requests yet. The admin will request the required documents — you will see upload buttons here.').'</div>';
  } else {
    echo '<table class="ctable"><thead><tr><th>'.t('Document').'</th><th>'.t('Note from admin').'</th><th>'.t('Status').'</th><th></th></tr></thead><tbody>';
    foreach($docReqs as $r){
      $type  = $docTypes[$r['type']??''] ?? ucfirst(str_replace('_',' ',$r['type']??''));
      $st    = $r['status'] ?? 'requested';
      $stHtml = match($st){
        'approved'  => '<span class="status offers">✓ '.t('Approved').'</span>',
        'rejected'  => '<span class="status" style="color:var(--bad);background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3)">✗ '.t('Rejected').'</span>',
        'uploaded'  => '<span class="status open">⏳ '.t('Under review').'</span>',
        default     => '<span class="status open">📎 '.t('Upload required').'</span>',
      };
      $note = htmlspecialchars($r['admin_note'] ?? $r['note'] ?? '');
      $actionCell = '';
      if($st==='requested'||$st==='rejected'){
        $actionCell = '<form method="post" action="/seller?tab=kyc" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="_action" value="upload_doc">
          <input type="hidden" name="req_id" value="'.htmlspecialchars($r['id']).'">
          <input type="file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required style="font-size:12px;max-width:200px">
          <button class="btn btn-p btn-sm" type="submit">'.t('Upload').'</button>
        </form>';
      } elseif($st==='uploaded'){
        $actionCell = '<span class="hint">'.substr($r['uploaded_at']??'',0,10).'</span>';
      } elseif($st==='approved'){
        $actionCell = '<span class="hint">'.substr($r['reviewed_at']??'',0,10).'</span>';
      }
      echo '<tr><td><b>'.htmlspecialchars($type).'</b><div class="hint">'.t('Requested').': '.htmlspecialchars(substr($r['requested_at']??'',0,10)).'</div></td>'.
        '<td class="hint">'.($note?$note:'—').'</td>'.
        '<td>'.$stHtml.'</td>'.
        '<td>'.$actionCell.'</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '<div class="hint" style="margin-top:16px;padding-top:12px;border-top:1px solid var(--brd)">'.t('Payout account').' · <a href="/seller?tab=profile" style="color:var(--acc)">'.t('Set up Stripe payouts &amp; escrow').' ↗</a></div>';
  echo '</div>';

// ── PROFILE ───────────────────────────────────────────────────────────────────
} else { // profile
  $u = $AUTH_USER ?? [];
  if(isset($_GET['saved'])) echo '<div class="banner ok">✓ '.t('Profile saved.').'</div>';
  if(isset($_GET['pw'])) echo '<div class="banner ok">✓ '.t('Password updated.').'</div>';
  if(isset($_GET['pwerr'])) echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">'.
    match($_GET['pwerr']){ 'cur'=>t('Current password is incorrect.'), 'len'=>t('Password must be at least 8 characters.'), default=>t('Passwords do not match.') }.'</div>';
  if(isset($_GET['cardok'])) echo '<div class="banner ok">✓ '.t('Commission card saved.').'</div>';
  if(isset($_GET['cardcancel'])) echo '<div class="banner info">'.t('Card setup was cancelled — no card was saved.').'</div>';
  if(($_GET['error']??'')==='notready') echo '<div class="banner info">'.t('Online payment is being set up — try again shortly, or contact support@vestrasales.com.').'</div>';
  elseif(isset($_GET['error'])) echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">'.t('Something went wrong — please try again or contact support.').'</div>';

  ?>
  <div class="panelcard">
    <form method="post" action="/seller?tab=profile" class="addform">
      <input type="hidden" name="_action" value="profile">
      <p class="hint" style="margin:0 0 16px"><?= t('Update your company details. Email cannot be changed here.') ?></p>
      <div class="authsect"><?= t('Personal info') ?></div>
      <div class="frow">
        <div><label><?= t('Full name') ?></label><input name="name" value="<?= htmlspecialchars($u['name']??'') ?>" placeholder="Anna Müller"></div>
        <div><label><?= t('Email address') ?></label><input type="email" value="<?= htmlspecialchars($u['email']??'') ?>" disabled></div>
      </div>
      <div class="authsect"><?= t('Company info') ?></div>
      <div class="frow">
        <div><label><?= t('Company name') ?></label><input name="company" value="<?= htmlspecialchars($u['company']??'') ?>" placeholder="Company GmbH"></div>
        <div><label><?= t('VAT / Tax ID') ?></label><input name="vat_id" value="<?= htmlspecialchars($u['vat_id']??'') ?>" placeholder="DE123456789"></div>
      </div>
      <div class="frow">
        <div><label><?= t('Registration number') ?></label><input name="reg_number" value="<?= htmlspecialchars($u['reg_number']??'') ?>" placeholder="HRB 12345"></div>
        <div><label><?= t('Country') ?></label><input name="country" value="<?= htmlspecialchars($u['country']??'') ?>" placeholder="DE"></div>
      </div>
      <div class="frow">
        <div><label><?= t('Address') ?></label><input name="address" value="<?= htmlspecialchars($u['address']??'') ?>" placeholder="Hauptstraße 1, 10115 Berlin"></div>
        <div><label><?= t('Phone') ?></label><input name="phone" value="<?= htmlspecialchars($u['phone']??'') ?>" placeholder="+49 30 12345678"></div>
      </div>
      <div class="frow">
        <div><label><?= t('Website') ?></label><input name="website" value="<?= htmlspecialchars($u['website']??'') ?>" placeholder="https://company.com"></div>
        <div><label><?= t('Account ID') ?></label><input value="<?= htmlspecialchars($u['id']??'—') ?>" disabled></div>
      </div>
      <div class="authsect"><?= t('Bank details for invoices') ?></div>
      <p class="hint" style="margin:0 0 10px"><?= t('Shown on the automatic PDF invoices your buyers receive, so they can pay you directly by bank transfer.') ?></p>
      <div class="frow">
        <div><label><?= t('Bank name') ?></label><input name="bank_name" value="<?= htmlspecialchars($u['bank_name']??'') ?>" placeholder="Deutsche Bank"></div>
        <div><label><?= t('Account holder') ?></label><input name="bank_holder" value="<?= htmlspecialchars($u['bank_holder']??'') ?>" placeholder="<?= htmlspecialchars($u['company']?:'Company GmbH') ?>"></div>
      </div>
      <div class="frow">
        <div><label><?= t('IBAN') ?></label><input name="bank_iban" value="<?= htmlspecialchars($u['bank_iban']??'') ?>" placeholder="DE89 3704 0044 0532 0130 00" style="text-transform:uppercase"></div>
        <div><label><?= t('BIC / SWIFT') ?></label><input name="bank_bic" value="<?= htmlspecialchars($u['bank_bic']??'') ?>" placeholder="COBADEFFXXX" style="text-transform:uppercase"></div>
      </div>
      <button class="btn btn-p" type="submit"><?= t('Save changes') ?></button>
    </form>
  </div>

  <?php
    // Stripe Connect (escrow payouts) status — only hits the API when the seller
    // has actually started onboarding, so most profile loads stay fast.
    require_once __DIR__.'/inc/stripe.php';
    $connSt = (stripe_available() && !empty($u['stripe_account_id'])) ? stripe_connect_status($u) : ['connected' => false];
    $connReady = !empty($connSt['ready']);
    /* Cache escrow readiness (can this seller receive a direct charge?) so the
       cart can offer escrow without an API call — refreshed on every profile view,
       which also covers the return from Connect onboarding before any webhook. */
    if (!empty($connSt['connected'])) {
      $ready = !empty($connSt['charges_enabled']);
      if (($u['escrow_ready'] ?? null) !== $ready) { auth_update($u['id'], ['escrow_ready'=>$ready]); $u['escrow_ready']=$ready; }
    }
  ?>
  <div class="panelcard">
    <div class="pcfhead"><h3><?= t('Payouts &amp; Escrow (Stripe)') ?></h3>
      <?php if ($connReady): ?><span class="status offers">✓ <?= t('Active') ?></span>
      <?php elseif (!empty($connSt['connected'])): ?><span class="status" style="background:rgba(240,192,96,.15);color:#f0c060">⏳ <?= t('In review') ?></span>
      <?php endif; ?>
    </div>
    <div class="panelcard-body" style="padding:0 4px">
      <p class="hint" style="margin:0 0 14px"><?= t('Connect your bank via Stripe to receive escrow payments automatically — the moment a buyer confirms delivery, your payout (minus commission) lands in your account. Stripe verifies your identity and bank details; VESTRA never sees them.') ?></p>
      <?php if (($_GET['connect'] ?? '') === 'done'): ?>
        <div class="banner ok" style="margin-bottom:12px">✓ <?= t('Thanks — your details were sent to Stripe.') ?></div>
      <?php elseif (($_GET['error'] ?? '') === 'connect'): ?>
        <?php $connErr = $_SESSION['connect_error'] ?? ''; unset($_SESSION['connect_error']); ?>
        <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:12px">
          <?= t('Something went wrong connecting to Stripe — please try again.') ?>
          <?php if ($connErr): ?><br><span style="font-size:12px;opacity:.85"><?= htmlspecialchars($connErr) ?></span><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (!stripe_available()): ?>
        <div class="banner info"><?= t('Online payments are being set up — check back shortly.') ?></div>
      <?php elseif ($connReady): ?>
        <div class="banner ok" style="margin-bottom:12px">✓ <?= t('Your payout account is active. Escrow payments transfer to your bank automatically.') ?></div>
        <a class="btn btn-o" href="/stripe/connect?dashboard=1" target="_blank" rel="noopener"><?= t('Open Stripe dashboard') ?> ↗</a>
      <?php elseif (!empty($connSt['connected'])): ?>
        <a class="btn btn-p" href="/stripe/connect"><?= t('Finish Stripe setup') ?></a>
      <?php else: ?>
        <a class="btn btn-p" href="/stripe/connect"><?= t('Set up Stripe payouts') ?></a>
        <p class="hint" style="margin-top:10px"><?= t('Bank transfer (invoice) still works without this — but escrow payments need a connected Stripe account.') ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="panelcard">
    <?php
      $msTier   = $u['membership_tier'] ?? '';
      $msTierLabel = $msTier === 'premium' ? 'Elite' : ($msTier ? ucfirst($msTier) : '—');
      $msStat   = $u['membership_status'] ?? 'none';
    ?>
    <div class="pcfhead"><h3><?= t('Membership') ?></h3>
      <?= match($msStat){
        'active'   => '<span class="status offers">✓ '.htmlspecialchars($msTierLabel).' · '.t('Active').'</span>',
        'trialing' => '<span class="status open">⏳ '.htmlspecialchars($msTierLabel).' · '.t('Trial').'</span>',
        'past_due' => '<span class="status" style="color:var(--bad)">⚠ '.t('Past due').'</span>',
        'canceled' => '<span class="status open">✗ '.t('Canceled').'</span>',
        default    => '<span class="status open">— '.t('No plan yet').'</span>',
      } ?>
    </div>
    <div style="padding:16px 18px">
      <p class="hint" style="margin:0 0 14px"><?= t('Change your plan, update your payment method, download invoices or cancel — all in the secure Stripe billing portal.') ?></p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php if (!empty($u['stripe_customer_id'])): ?>
        <form method="post" action="/stripe/portal">
          <button class="btn btn-p" type="submit"><?= t('Manage subscription') ?></button>
        </form>
        <?php endif; ?>
        <a class="btn btn-o" href="/membership"><?= t('Compare plans') ?></a>
      </div>
    </div>
  </div>
  <div class="panelcard">
    <div class="pcfhead"><h3><?= t('Commission card') ?></h3>
      <?= !empty($u['stripe_commission_pm']) ? '<span class="status offers">✓ '.t('On file').'</span>' : '<span class="status open">— '.t('Not added').'</span>' ?>
    </div>
    <div style="padding:16px 18px">
      <p class="hint" style="margin:0 0 14px"><?= sprintf(t('VESTRA charges a %s%% commission on each order automatically to this card once the buyer\'s payment is confirmed — separate from your bank details above, which are only for receiving buyer payments.'), number_format(vestra_seller_commission_rate($u['membership_tier']??'')*100,1)) ?></p>
      <form method="post" action="/stripe/setup-card">
        <button class="btn btn-p" type="submit"><?= !empty($u['stripe_commission_pm']) ? t('Update card') : t('Add commission card') ?></button>
      </form>
    </div>
  </div>
  <div class="panelcard">
    <div class="pcfhead"><h3><?= t('Security') ?></h3></div>
    <form method="post" action="/seller?tab=profile" class="addform" autocomplete="off">
      <input type="hidden" name="_action" value="change_password">
      <div class="frow">
        <div><label><?= t('Current password') ?></label><input type="password" name="current" required autocomplete="current-password"></div>
        <div></div>
      </div>
      <div class="frow">
        <div><label><?= t('New password') ?></label><input type="password" name="password" required minlength="8" autocomplete="new-password"></div>
        <div><label><?= t('Repeat new password') ?></label><input type="password" name="password2" required minlength="8" autocomplete="new-password"></div>
      </div>
      <button class="btn btn-o" type="submit"><?= t('Change password') ?></button>
    </form>
  </div>
  <?php
}
dash_close();
require __DIR__.'/inc/foot.php';
