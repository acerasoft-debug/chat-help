<?php
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/docs.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* post_max_size asimi: PHP govdeyi tumden atar, $_POST bos kalir ve asagidaki
   hicbir dal calismaz -- kullanici "hicbir sey olmuyor" gorur (2 Eyl 2026'da
   bir alici belgesini iki gun boyle yukleyemedi). Bu sayfaya dosya yalnizca
   belge formundan gelir; sebebiyle oraya don. */
if (!empty($_SESSION['uid']) && vestra_doc_post_overflow()) {
    /* $_POST is EMPTY on overflow (PHP drops the whole body), so there is no _action
       to branch on -- only the referring page says which upload form this was, and
       the order ref (so the banner lands back on the order, not the bare list). */
    $ovRef = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if (str_contains($ovRef, 'tab=orders')) {
        parse_str((string)parse_url($ovRef, PHP_URL_QUERY), $ovQ);
        $ovView = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($ovQ['view'] ?? ''));
        header('Location: /buyer?tab=orders'.($ovView !== '' ? '&view='.urlencode($ovView) : '').'&receipt_err=post'); exit;
    }
    header('Location: /buyer?tab=kyc&upload_err=post'); exit;
}

// Profile save
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='profile') {
    // Same gate as registration (KURAL 2g) — an account that got in before this
    // rule must not be able to relabel itself Turkish either, VPN or not.
    if (vestra_country_declares_turkey((string)($_POST['country'] ?? ''))) {
        header('Location: /buyer?tab=profile&err=country'); exit;
    }
    auth_update($_SESSION['uid'], [
        'name'=>trim($_POST['name']??''),'company'=>trim($_POST['company']??''),
        'vat_id'=>trim($_POST['vat_id']??''),'reg_number'=>trim($_POST['reg_number']??''),
        'country'=>trim($_POST['country']??''),'address'=>trim($_POST['address']??''),
        'phone'=>trim($_POST['phone']??''),'website'=>trim($_POST['website']??''),
    ]);
    header('Location: /buyer?tab=profile&saved=1'); exit;
}

// Change password (requires the current password)
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='change_password') {
    $u = auth_user();
    if (!$u || !password_verify($_POST['current']??'', $u['hash']??'')) { header('Location: /buyer?tab=profile&pwerr=cur'); exit; }
    if (strlen($_POST['password']??'') < 8)                              { header('Location: /buyer?tab=profile&pwerr=len'); exit; }
    if (($_POST['password']??'') !== ($_POST['password2']??''))          { header('Location: /buyer?tab=profile&pwerr=match'); exit; }
    auth_set_password($u['id'], $_POST['password']);
    header('Location: /buyer?tab=profile&pw=1'); exit;
}

require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/invoice.php';
require_once __DIR__.'/inc/orders.php';

// ── Upload KYC document ───────────────────────────────────────────────────────
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='upload_doc') {
    $req_id = preg_replace('/[^a-f0-9]/','', $_POST['req_id']??'');
    /* Sebep kodu URL'de: banner hangi kuralin dustugunu ve cikis yolunu yazar
       (auth_doc_error_text). Eski "upload_err=1" her hatada ayni cumleydi. */
    $res = auth_upload_doc_ex($_SESSION['uid'], $req_id, $_FILES['doc_file'] ?? null);
    header('Location: /buyer?tab=kyc&'.($res['ok'] ? 'uploaded=1' : 'upload_err='.rawurlencode($res['error']))); exit;
}

// ── Upload a bank-transfer receipt for one of MY OWN pending orders ────────────
// The segment the payment-due reminder points to (inc/orders.php, 2 Sep 2026): a
// buyer who has already paid needs somewhere to prove it besides hoping an e-mail
// reply gets read before the auto-cancel deadline.
if (!empty($_SESSION['uid']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='upload_receipt') {
    require_once __DIR__.'/inc/receipts.php';
    $rRef = trim((string)($_POST['ref'] ?? ''));
    $me = auth_user(); $myEmail = strtolower($me['email'] ?? '');
    $rOrder = null;
    foreach (vestra_read_csv('orders.csv') as $row) { if (($row['ref'] ?? '') === $rRef) { $rOrder = $row; break; } }
    $ownsOrder = $rOrder && $myEmail !== '' && strtolower($rOrder['email'] ?? '') === $myEmail;
    if (!$rRef || !$ownsOrder) { header('Location: /buyer?tab=orders'); exit; }
    $rRes = vestra_receipt_store($rRef, $_FILES['receipt_file'] ?? null, 'buyer');
    if ($rRes['ok']) {
        require_once __DIR__.'/inc/notify.php';
        $opsTo = (string)vestra_cfg('ops_email', 'acerasoft@gmail.com');
        vestra_send_mail($opsTo, 'VESTRA — payment receipt uploaded for order '.$rRef,
            "A buyer uploaded a bank-transfer receipt for order {$rRef}.\n\n"
          . "Company: ".($rOrder['company'] ?? '?')."\n\n"
          . "Review: https://vestrasales.com/admin?tab=orders&view=".rawurlencode($rRef)."\n\n— VESTRA");
    }
    header('Location: /buyer?tab=orders&view='.urlencode($rRef).($rRes['ok'] ? '&receipt=1' : '&receipt_err='.rawurlencode($rRes['error']))); exit;
}

// Confirm receipt (escrow release) — only the buyer who placed it, once shipped OR delivered.
// 'delivered' da kabul: satici teslimati isaretledikten SONRA alicinin onay dugmesi
// olmezse, 2-is-gunu sayaci baslamisken alici erken onaylayamaz hale gelirdi.
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='confirm_receipt') {
    $ref = $_POST['ref'] ?? '';
    $me  = auth_user();
    $myEmail = strtolower($me['email'] ?? '');
    $orderRow = null;
    foreach(vestra_read_csv('orders.csv') as $row){
        if(($row['ref']??'')===$ref){ $orderRow=$row; break; }
    }
    $ownsOrder = $orderRow && $myEmail !== '' && strtolower($orderRow['email']??'') === $myEmail;
    $st = vestra_read_json('order_statuses.json');
    $currentStatus = $st[$ref]['status'] ?? 'pending';
    if ($ref && $ownsOrder && in_array($currentStatus, ['shipped','delivered'], true)) {
        $st[$ref] = array_merge($st[$ref] ?? [], ['status'=>'completed','confirmed_at'=>date('c')]);
        $st[$ref]['history'][] = vestra_order_history_entry('completed', 'buyer');
        vestra_write_json('order_statuses.json', $st);
        /* Escrow: confirming receipt RELEASES the held funds to the seller. */
        require_once __DIR__.'/inc/escrow.php';
        $esc = escrow_get($ref);
        if ($esc && ($esc['status']??'')==='held') {
            $rel = escrow_do_release($ref);
            if (!$rel['ok']) error_log('[VESTRA Escrow] buyer-confirm release failed '.$ref.': '.$rel['msg']);
        }
        /* Notify admin + seller */
        require_once __DIR__.'/inc/notify.php';
        $buyerName = ($orderRow['name']?:$orderRow['company']?:'');
        $orderItems = $orderRow['items']??'';
        vestra_notify("Order {$ref} — receipt confirmed by buyer",
          "Buyer confirmed receipt for order {$ref}.\nBuyer: {$buyerName}\nItems: {$orderItems}\n\nOrder can be marked completed (invoice settlement).\n\nAdmin: https://vestrasales.com/admin?tab=orders");
        /* Notify seller(s) — match ordered SKUs from orders.csv items field */
        $allListings=vestra_listings();
        $notified=[];
        $orderSkus=array_column(vestra_parse_order_items($orderItems),'sku');
        $me = auth_user();
        foreach($allListings as $listing){
            if(empty($listing['seller_uid'])||empty($listing['sku'])) continue;
            if(!in_array($listing['sku'],$orderSkus,true)) continue;
            if(in_array($listing['seller_uid'],$notified,true)) continue;
            $notified[]=$listing['seller_uid'];
            require_once __DIR__.'/inc/push.php';
            vestra_push_send($listing['seller_uid'], 'VESTRA — receipt confirmed ✓',
                'Order '.$ref.' — the buyer confirmed delivery. Payout in progress.', '/seller?tab=orders');
            /* Completed card into the seller's conversation */
            if($me){
                require_once __DIR__.'/inc/messages.php';
                vestra_msg_post_system($me['id'], $listing['seller_uid'], '', [
                    'kind'=>'order','status'=>'completed','ref'=>$ref,
                ]);
            }
            foreach(auth_accounts() as $acc){
                if(($acc['id']??'')!==$listing['seller_uid']||empty($acc['email'])) continue;
                vestra_send_mail($acc['email'], "VESTRA — order {$ref} confirmed, payout in progress",
                  "Hello ".($acc['name']?:($acc['company']?:'there')).",\n\nThe buyer has confirmed receipt for order {$ref}. Your payout is being processed.\n\nItems: {$orderItems}\n\nView in your seller dashboard:\nhttps://vestrasales.com/seller?tab=orders\n\n— VESTRA · vestrasales.com");
                break;
            }
        }
    }
    header('Location: /buyer?tab=orders&confirmed=1'); exit;
}

// ── Accept a seller's offer on one of my sourcing requests ─────────────────────
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='request_offer_respond') {
    $oref = $_POST['ref'] ?? '';
    $me = auth_user();
    $myEmail = strtolower($me['email'] ?? '');
    $reqRow = null; $offerRow = null;
    foreach (vestra_read_csv('requests.csv') as $row) {
        $offers = array_filter(vestra_read_csv('request_offers.csv'), fn($o)=>($o['request_ref']??'')===($row['ref']??''));
        foreach ($offers as $o) { if (($o['ref']??'')===$oref) { $reqRow=$row; $offerRow=$o; break 2; } }
    }
    $owns = $reqRow && $offerRow && $myEmail !== '' && strtolower($reqRow['email']??'') === $myEmail;
    if ($owns) {
        $resp = vestra_read_json('request_offer_responses.json');
        $resp[$oref] = ['status'=>'accept', 'responded_at'=>date('c')];
        vestra_write_json('request_offer_responses.json', $resp);

        $sellerAcc = auth_find($offerRow['seller_email'] ?? '');
        $title = $reqRow['title'] ?? $oref;
        if ($sellerAcc && ($sellerAcc['type']??'')==='seller') {
            require_once __DIR__.'/inc/messages.php';
            vestra_msg_post_system($me['id'], $sellerAcc['id'], $reqRow['ref'] ?? '', [
                'kind'=>'request_offer', 'status'=>'accept', 'ref'=>$oref,
                'request_ref'=>$reqRow['ref'] ?? '', 'product'=>$title,
                'qty'=>$offerRow['qty'] ?? '', 'unit_price'=>(float)($offerRow['price'] ?? 0),
            ]);
        }
        /* Auto-generate an invoice for this sourcing deal, same as any other confirmed sale. */
        $qtyNum = (int)preg_replace('/\D/', '', (string)($offerRow['qty'] ?? '0')) ?: 1;
        $unit = (float)($offerRow['price'] ?? 0);
        $orderMeta = [
            'ref'=>$oref, 'date'=>date('c'),
            'buyer'=>['company'=>$me['company']??'','vat'=>$me['vat_id']??'','name'=>$me['name']??'','email'=>$me['email']??'','country'=>$me['country']??'','address'=>$me['address']??''],
        ];
        $items = [[ 'sku'=>$reqRow['ref'] ?? $oref, 'brand'=>t('Sourcing request'), 'name'=>$title, 'colors'=>[], 'qty'=>$qtyNum, 'unit'=>$unit, 'line'=>round($qtyNum*$unit,2) ]];
        vestra_ensure_invoice($orderMeta, $items, $sellerAcc);

        require_once __DIR__.'/inc/notify.php';
        if (!empty($offerRow['seller_email'])) {
            vestra_send_mail($offerRow['seller_email'], "VESTRA — your offer {$oref} was accepted!",
              "Hello ".($offerRow['seller_company']??'there').",\n\nGreat news — the buyer accepted your offer on sourcing request \"{$title}\".\n\nSign in to view the details and message the buyer:\nhttps://vestrasales.com/seller?tab=messages\n\n— VESTRA · vestrasales.com");
        }
    }
    header('Location: /buyer?tab=requests&accepted=1'); exit;
}

// ── Messaging (start a new thread from a product page, or reply in an existing one) ──
require __DIR__.'/inc/messages.php';
if (($_GET['tab']??'')==='messages' && !empty($_GET['thread']) && isset($_GET['poll'])) {
    $t = vestra_msg_find_thread($_GET['thread']);
    $ok = $t && ($t['buyer_uid']??'') === ($_SESSION['uid']??'');
    header('Content-Type: application/json');
    echo json_encode(['last' => $ok ? ($t['last_at']??'') : '']);
    exit;
}
// Mark an opened thread read here, before head.php computes the nav badge below —
// otherwise the badge still shows the just-read thread as unread for this whole page view.
if (($_GET['tab']??'')==='messages' && !empty($_GET['thread']) && $_SERVER['REQUEST_METHOD']==='GET') {
    $t = vestra_msg_find_thread($_GET['thread']);
    if ($t && !empty($_SESSION['uid']) && ($t['buyer_uid']??'') === $_SESSION['uid']) {
        vestra_msg_mark_read($_GET['thread'], $_SESSION['uid']);
    }
}
if (!empty($_SESSION['member']) && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='send_message') {
    $uid  = $_SESSION['uid'] ?? '';
    $tid  = $_POST['thread_id'] ?? '';
    $body = $_POST['body'] ?? '';
    if ($tid) {
        $thread = vestra_msg_find_thread($tid);
        $res = ($thread && ($thread['buyer_uid']??'') === $uid)
            ? vestra_msg_send($thread['buyer_uid'], $thread['seller_uid'], $uid, $body, $thread['listing_id']??'')
            : ['ok'=>false, 'error'=>'empty'];
    } else {
        $listingId = $_POST['listing_id'] ?? '';
        $listing = vestra_listing_by_id($listingId);
        $sellerUid = $listing['seller_uid'] ?? '';
        if ($listing && !$sellerUid) $sellerUid = VESTRA_SUPPORT_UID; // platform/seller-less listing → operator support
        if ($listing && $sellerUid && $uid) {
            $res = vestra_msg_send($uid, $sellerUid, $uid, $body, $listingId);
            $tid = $res['thread_id'] ?? vestra_msg_thread_id($uid, $sellerUid, $listingId);
        } else {
            $res = ['ok'=>false, 'error'=>'empty'];
        }
    }
    if (!$res['ok']) { header('Location: /buyer?tab=messages&thread='.urlencode($tid).'&msgerr='.($res['error']==='flagged'?$res['flag']:'empty')); exit; }
    header('Location: /buyer?tab=messages&thread='.urlencode($tid)); exit;
}

require __DIR__.'/inc/dash.php';
$PAGE=t('Buyer panel'); $NAV='account'; require __DIR__.'/inc/head.php';

if(!$MEMBER){
  echo '<div class="wrap"><div class="gate" style="margin:48px auto;max-width:460px;text-align:center">
    <h3 style="margin:0 0 6px">'.t('Buyer workspace').'</h3>
    <p style="color:var(--mut);margin:0 0 20px">'.t('Sign in to track your orders, sourcing requests and offers.').'</p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
    <a class="btn btn-p" href="/login?back=/buyer">'.t('Sign in').'</a>
    <a class="btn btn-o" href="/register">'.t('Create account').'</a></div></div></div>';
  require __DIR__.'/inc/foot.php'; exit;
}
$tab=$_GET['tab']??'overview';
$uid=$_SESSION['uid']??'';
$myEmail=strtolower($AUTH_USER['email']??'');
$orders=array_values(array_filter(vestra_read_csv('orders.csv'), fn($o)=>$myEmail!==''&&strtolower($o['email']??'')===$myEmail));
$requests=array_values(array_filter(vestra_read_csv('requests.csv'), fn($o)=>$myEmail!==''&&strtolower($o['email']??'')===$myEmail));
$offers=array_values(array_filter(vestra_read_csv('offers.csv'), fn($o)=>$myEmail!==''&&strtolower($o['email']??'')===$myEmail));
$offerResp=vestra_read_json('offer_responses.json');
$orderSt=vestra_read_json('order_statuses.json');

dash_open('buyer',$tab,
  $tab==='orders'?t('My orders'):($tab==='requests'?t('My sourcing requests'):($tab==='offers'?t('My offers'):($tab==='messages'?t('Messages'):($tab==='kyc'?t('Verification'):($tab==='profile'?t('My profile'):t('Overview')))))),
  $tab==='overview'?t('Your purchasing activity at a glance'):'');

if($tab==='overview'){
  $spent=0; foreach($orders as $o){ $spent+=(float)($o['total']??0); }
  stat_cards([
    [count($orders),t('Orders')],
    ['<span class="acc">'.count($requests).'</span>',t('Open requests')],
    [count($offers),t('Offers made')],
    [eur($spent),t('Order value')],
  ]);
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Quick actions').'</h3></div><div class="quickrow">
    <a class="btn btn-p btn-sm" href="/shop">'.t('Browse catalog').'</a>
    <a class="btn btn-o btn-sm" href="/requests#post">'.t('Post a sourcing request').'</a>
    <a class="btn btn-o btn-sm" href="/buyer?tab=orders">'.t('View orders').'</a></div></div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Buyer protection').'</h3><span class="status offers">'.t('Active').'</span></div>
    <p class="hint">'.t('Every order runs on documented invoice terms — report any issue within 48 hours of delivery and our dispute process steps in.').'</p></div>';

} elseif($tab==='orders'){
  require_once __DIR__.'/inc/escrow.php';
  $viewRef = $_GET['view'] ?? '';
  $viewOrder = $viewRef ? current(array_filter($orders, fn($o)=>($o['ref']??'')===$viewRef)) : null;
  if ($viewOrder) {
    if (isset($_GET['receipt'])) echo '<div class="banner ok">✓ '.t('Receipt received — we will confirm it shortly.').'</div>';
    if (isset($_GET['receipt_err'])) echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:14px">⚠ '.htmlspecialchars(t(auth_doc_error_text((string)$_GET['receipt_err']))).'</div>';
    echo vestra_render_order_detail($viewOrder, $orderSt[$viewRef] ?? ['status'=>'pending'], 'buyer', $uid, '/buyer?tab=orders', '/buyer?tab=orders');
  } else {
  if(isset($_GET['confirmed'])) echo '<div class="banner ok">✓ '.t('Receipt confirmed. Order completed.').'</div>';
  echo '<style>
    .ordlist{display:flex;flex-direction:column;gap:12px}
    .ordcard{position:relative;overflow:hidden;background:var(--bg2);border:1px solid var(--line);border-radius:14px;padding:16px 18px 16px 20px;box-shadow:0 1px 3px rgba(60,50,30,.05);transition:box-shadow .18s ease,transform .18s ease}
    .ordcard:hover{box-shadow:0 6px 20px rgba(60,50,30,.10);transform:translateY(-1px)}
    /* A hairline in the accent colour marks the orders that are waiting on US. It reads
       at a glance down a long list, without shouting like a full-width banner would. */
    .ordcard-review::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--acc)}
    .ordreview{margin:11px 0 0;padding:10px 12px;border-radius:10px;font-size:13px;line-height:1.5;
      background:color-mix(in srgb,var(--acc) 9%,transparent);border:1px solid color-mix(in srgb,var(--acc) 26%,transparent)}
    .ordcard-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
    .ordref{font-weight:700;font-size:15px;color:var(--ink)}
    .ordref:hover{color:var(--acc)}
    .ordcard-items{color:var(--mut);font-size:13.5px;margin:10px 0 0;line-height:1.5;overflow-wrap:anywhere}
    .ordprods{list-style:none;margin:12px 0 0;padding:0;display:flex;flex-direction:column}
    .ordprod{display:flex;align-items:center;gap:12px;padding:9px 0;border-top:1px solid var(--line)}
    .ordprod:first-child{border-top:0;padding-top:2px}
    .ordprod-i{width:46px;height:46px;flex-shrink:0;border-radius:8px;object-fit:cover;background:var(--bg);border:1px solid var(--line)}
    .ordprod-i0{display:block}
    .ordprod-t{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
    .ordprod-l{font-weight:600;color:var(--ink);text-decoration:none}
    .ordprod-l:hover{color:var(--acc);text-decoration:underline}
    .ordprod-m{color:var(--mut);font-size:12.5px}
    .ordprod-s{font-weight:600;white-space:nowrap}
    @media(max-width:480px){.ordprod-i{width:38px;height:38px}.ordprod-m{font-size:11.5px}}
    .ordcard-foot{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:13px;padding-top:13px;border-top:1px solid var(--line)}
    .ordtotal{font-family:\'Playfair Display\',serif;font-size:22px;font-weight:700}
    .ordacts{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
    .ordacts form{margin:0}
    @media(max-width:560px){.ordcard-foot{flex-direction:column;align-items:stretch}.ordacts{justify-content:flex-start}}
  </style>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('My orders').'</h3><a class="btn btn-o btn-sm" href="/shop">'.t('New order').'</a></div>';
  if(!$orders){ dash_empty(t('No orders yet. Place an order from the catalog.')); echo '</div>'; }
  else {
    $spent=0; $awaiting=0;
    foreach($orders as $o){ $spent+=(float)($o['total']??0); if((($orderSt[$o['ref']??'']['status'])??'pending')==='shipped') $awaiting++; }
    echo '<div class="statgrid" style="grid-template-columns:repeat(3,1fr);margin:0">'.
      '<div class="statcard"><div class="sv">'.count($orders).'</div><div class="sl">'.t('Orders').'</div></div>'.
      '<div class="statcard"><div class="sv">'.eur($spent).'</div><div class="sl">'.t('Total value').'</div></div>'.
      '<div class="statcard"><div class="sv" style="color:'.($awaiting?'#8a6420':'var(--mut)').'">'.$awaiting.'</div><div class="sl">'.t('Awaiting your confirmation').'</div></div></div>';
    echo '</div>';
    echo '<div class="ordlist">';
    foreach($orders as $o){
      $ref = $o['ref']??'';
      $st  = $orderSt[$ref]['status'] ?? 'pending';
      $er  = escrow_get($ref);
      $isHeld = $er && ($er['status']??'')==='held';
      $escBadge = $er ? '<div style="margin-top:5px">'.escrow_badge($er['status']??'').'</div>' : '';
      /* "Awaiting payment" on an order with no invoice yet blames the buyer for a wait
         that is ours, so a fresh order reads as in review until the invoice is issued. */
      $inReview = vestra_order_in_review($ref, $st);
      if ($inReview)          { $stClass='open'; $stLabel=t('In review'); }
      elseif($st==='completed'){ $stClass='offers'; $stLabel=t('Completed'); }
      elseif($st==='shipped') { $stClass='open'; $stLabel=t('Shipped — confirm receipt'); }
      elseif($st==='delivered') { $stClass='open'; $stLabel=t('Delivered — confirm receipt'); }
      elseif($st==='paid')    { $stClass='offers'; $stLabel=t('Paid — preparing shipment'); }
      else { $stClass='open'; $stLabel=t('Awaiting payment'); }
      $reviewNote = $inReview
        ? '<div class="ordreview">🔎 '.htmlspecialchars(vestra_order_review_note((string)($o['timestamp']??''))).'</div>'
        : '';
      $confirmBtn='';
      if($st==='shipped' || $st==='delivered'){
        $confirmBtn='<form method="post" action="/buyer?tab=orders" '.
          ($isHeld?'onsubmit="return confirm(\''.htmlspecialchars(t('Confirm you received the goods? This releases the held funds to the seller and cannot be undone.'),ENT_QUOTES).'\')"':'').'>
          <input type="hidden" name="_action" value="confirm_receipt">
          <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
          <button class="btn btn-p btn-sm" type="submit">✓ '.($isHeld?t('Confirm delivery & release payment'):t('Confirm receipt')).'</button></form>';
      }
      $invLinks='';
      foreach(vestra_invoices_for_ref($ref) as $iv){
        $invLinks.='<a class="btn btn-o btn-sm" href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener">📄 '.t('Invoice').' '.htmlspecialchars(vestra_invoice_link_label($iv)).'</a> ';
      }
      $trk = !empty($orderSt[$ref]['tracking']) ? '<div class="hint" style="margin-top:8px">🚚 '.t('Tracking').': '.htmlspecialchars($orderSt[$ref]['tracking']).'</div>' : '';
      /* The raw items field ("2x SKU @25 | …") is the storage format, not something a buyer
         should have to decode. Rebuild the real lines so each product is named, pictured and
         clickable straight through to its page — the thing they actually want to re-check. */
      $prodHtml = '';
      foreach (vestra_order_lines($o)['lines'] as $l) {
        $label = trim(($l['brand'] ?? '').' '.($l['name'] ?? ''));
        $title = $l['id']
          ? '<a class="ordprod-l" href="/product?id='.urlencode($l['id']).'">'.htmlspecialchars($label).'</a>'
          : htmlspecialchars($label);
        $cols = !empty($l['colors']) ? ' · '.htmlspecialchars(implode(', ', (array)$l['colors'])) : '';
        $prodHtml .= '<li class="ordprod">'.
          ($l['image'] ? '<img class="ordprod-i" src="'.htmlspecialchars($l['image']).'" alt="" loading="lazy">'
                       : '<span class="ordprod-i ordprod-i0"></span>').
          '<span class="ordprod-t">'.$title.
            '<span class="ordprod-m">'.(int)$l['qty'].' × '.eur($l['unit']).' · SKU '.htmlspecialchars((string)$l['sku']).$cols.'</span>'.
          '</span>'.
          '<span class="ordprod-s">'.eur($l['line']).'</span></li>';
      }
      if ($prodHtml !== '') $prodHtml = '<ul class="ordprods">'.$prodHtml.'</ul>';
      elseif (($o['items'] ?? '') !== '') $prodHtml = '<div class="ordcard-items">'.htmlspecialchars($o['items']).'</div>';
      echo '<div class="ordcard'.($inReview?' ordcard-review':'').'">'.
        '<div class="ordcard-top">'.
          '<div><a class="ordref" href="/buyer?tab=orders&view='.urlencode($ref).'">#'.htmlspecialchars($ref).'</a>'.
          '<div class="hint" style="margin-top:2px">'.htmlspecialchars(substr($o['timestamp']??'',0,10)).'</div></div>'.
          '<div style="text-align:right"><span class="status '.$stClass.'">'.$stLabel.'</span>'.$escBadge.'</div>'.
        '</div>'.
        $reviewNote.
        $prodHtml.
        $trk.
        '<div class="ordcard-foot">'.
          '<div class="ordtotal">'.eur($o['total']??0).
            /* Kargo toplamin ICINDE; dokum gorunmezse alici mal bedelini
               toplam sanip eksik havale yapabilir. Satir yalnizca kargolu
               siparislerde cikar. */
            (((float)($o['shipping']??0))>0
              ? '<div class="hint" style="font-weight:400;font-size:11px">'.t('incl. shipping').' '.eur($o['shipping']).'</div>' : '').
            ((($__iv=vestra_order_invoiced_note($ref))!=='')
              ? '<div class="hint" style="font-weight:400;font-size:11px">'.htmlspecialchars($__iv).'</div>' : '').
          '</div>'.
          '<div class="ordacts">'.$confirmBtn.$invLinks.
            '<a class="btn btn-o btn-sm" href="/order-pdf?ref='.urlencode($ref).'">⤓ PDF</a>'.
            '<a class="btn btn-o btn-sm" href="/buyer?tab=orders&view='.urlencode($ref).'">'.t('View details').' →</a>'.
          '</div>'.
        '</div>'.
      '</div>';
    }
    echo '</div>';
  }
  }

} elseif($tab==='requests'){
  $reqOffers = vestra_read_csv('request_offers.csv');
  $reqOfferResp = vestra_read_json('request_offer_responses.json');
  if(isset($_GET['accepted'])) echo '<div class="banner ok">✓ '.t('Offer accepted — an invoice was generated and the seller was notified.').'</div>';
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Sourcing requests').'</h3><a class="btn btn-p btn-sm" href="/requests#post">＋ '.t('New request').'</a></div>';
  if(!$requests) dash_empty(t('No requests yet. Post what you need on the sourcing board.'));
  else { echo '<table class="ctable"><thead><tr><th>'.t('Ref').'</th><th>'.t('Looking for').'</th><th>'.t('Target').'</th><th>'.t('Status').'</th></tr></thead><tbody>';
    foreach($requests as $r){
      $rref = $r['ref']??'';
      $myOffers = array_values(array_filter($reqOffers, fn($o)=>($o['request_ref']??'')===$rref));
      $anyAccepted = (bool)array_filter($myOffers, fn($o)=>($reqOfferResp[$o['ref']??'']['status']??'')==='accept');
      if ($myOffers) {
        $stCell = '<details class="respdetails"'.($anyAccepted?'':' open').'><summary class="status '.($anyAccepted?'offers':'open').'" style="cursor:pointer;display:inline-block">'.count($myOffers).' '.t('offer(s) received').'</summary>';
        foreach($myOffers as $of){
          $oref = $of['ref'] ?? '';
          $accepted = ($reqOfferResp[$oref]['status'] ?? '') === 'accept';
          $stCell .= '<div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--brd);font-size:13px">'.
            '<b>'.htmlspecialchars($of['seller_company']??'').'</b> — '.eur($of['price']??0).'/pc · '.htmlspecialchars($of['qty']??'').
            '<div class="hint">'.htmlspecialchars($of['delivery']??'').'</div>'.
            (!empty($of['message'])?'<div class="hint">'.htmlspecialchars($of['message']).'</div>':'');
          if ($accepted) {
            $stCell .= '<div style="margin-top:6px"><span class="status offers">✓ '.t('Accepted').'</span>';
            foreach(vestra_invoices_for_ref($oref) as $iv){
              $stCell .= ' <a class="btn btn-o btn-sm" href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener">📄 '.t('Invoice').' '.htmlspecialchars(vestra_invoice_link_label($iv)).'</a>';
            }
            $stCell .= '</div>';
          } elseif (!$anyAccepted) {
            $stCell .= '<form method="post" action="/buyer?tab=requests" style="margin-top:6px">
              <input type="hidden" name="_action" value="request_offer_respond">
              <input type="hidden" name="ref" value="'.htmlspecialchars($oref).'">
              <button class="btn btn-p btn-sm" type="submit">✓ '.t('Accept offer').'</button></form>';
          }
          $stCell .= '</div>';
        }
        $stCell .= '</details>';
      } else {
        $stCell = '<span class="status open">'.t('In queue').'</span>';
      }
      echo '<tr><td><b>'.htmlspecialchars($rref).'</b></td><td>'.htmlspecialchars($r['title']??'').'<div class="hint">'.htmlspecialchars($r['qty']??'').'</div></td>'.
        '<td>'.htmlspecialchars($r['target']??'').'</td><td>'.$stCell.'</td></tr>';
    }
    echo '</tbody></table>'; }
  echo '</div>';

} elseif($tab==='offers'){
  /* Teklifler artik SIPARISLER GIBI: her teklif kendi baglantisi olan bir
     kart, ve ?view=REF ile pazarligin tam gecmisi. Onceden dort sutunluk
     duz bir tablo vardi -- urune gidilemiyor, ne zaman ne oldugu
     gorunmuyor, ve fatura yalnizca kabul satirinda bir link olarak
     duruyordu. Alici "teklifim ne oldu" sorusunu tablodan cevaplayamiyordu. */
  require_once __DIR__.'/inc/offers.php';

  /* ── ODENMESI GEREKEN FATURALAR (operator istegi, 1 Eyl 2026) ──
     Kesilmis ama odenmemis teklif faturalari sayfanin EN USTUNDE, acik bir
     uyariyla: banka havalesi bekleyen belge, kartlarin arasinda bir linkten
     ibaret kalmasin. "Odenmemis" = birincil ref'te invoice_paid_at yok;
     isareti operator odeme gelince koyuyor. Uyeler (invoice_group_ref)
     atlanir: birlesik belge tek satir olarak bir kez gorunur. */
  $dueInvs=[];
  foreach($offers as $__o){
    $__r=(string)($__o['ref']??''); if($__r==='') continue;
    if(trim((string)($offerResp[$__r]['invoice_group_ref'] ?? ''))!=='') continue;
    if(!empty($offerResp[$__r]['invoice_paid_at'])) continue;
    foreach(vestra_invoices_for_ref($__r,false) as $__iv){
      if(($__iv['no']??'')!=='') $dueInvs[$__iv['no']]=$__iv;
    }
  }
  if($dueInvs){
    echo '<div class="panelcard" style="border-color:rgba(169,127,44,.5)"><div class="pcfhead"><h3>⚠ '.t('Payment due').'</h3></div><div class="panelcard-body" style="padding:0 4px">';
    echo '<p class="hint" style="margin:0 0 10px">'.t('The invoice(s) below have been issued and await your bank transfer. Please pay to the account shown on the invoice, quoting the payment reference printed on it — your goods ship as soon as the payment arrives.').'</p>';
    foreach($dueInvs as $__iv){
      $__cur = strtoupper((string)($__iv['currency']??''))==='USD' ? 'US$' : '€';
      echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:8px 0;border-top:1px solid var(--brd)">'
          .'<b>'.htmlspecialchars((string)($__iv['no']??'')).'</b>'
          .(((float)($__iv['total']??0))>0 ? '<span>'.$__cur.number_format((float)$__iv['total'],2,'.',',').'</span>' : '')
          .'<span class="status open">'.t('awaiting payment').'</span>'
          .'<a class="btn btn-o btn-sm" href="'.htmlspecialchars((string)($__iv['url']??'')).'" target="_blank" rel="noopener">📄 '.t('Download invoice').'</a>'
          .'</div>';
    }
    echo '</div></div>';
  }
  $viewRef = $_GET['view'] ?? '';
  $vo = $viewRef ? current(array_filter($offers, fn($o)=>($o['ref']??'')===$viewRef)) : null;

  /* Pazarligin adimlari, olan bitenden turetiliyor -- uydurulmuyor:
     yalnizca kaydi olan adim yaziliyor. */
  $steps = function(array $o, ?array $r) {
    $ref=(string)($o['ref']??''); $q=(int)($o['qty']??0);
    $s=[[ 'ok'=>true,'at'=>(string)($o['timestamp']??''),
          'title'=>t('You made an offer'),
          'note'=>eur($o['offer_unit']??0).'/'.t('unit').' × '.$q.' = '.eur($o['offer_total']??0)]];
    if(!$r){ $s[]=['ok'=>false,'at'=>'','title'=>t('Waiting for the seller'),'note'=>t('We normally reply within one business day.')]; return $s; }
    $st=(string)($r['status']??'');
    /* Butun turlar, sirasiyla ve KIMIN verdigiyle. Eskiden yalnizca son
       karsi teklif gorunuyordu; uc turluk bir pazarligin ilk ikisi
       kayboluyordu ve alici hangi rakamda nerede oldugunu izleyemiyordu. */
    $rounds=(array)($r['counters']??[]);
    if(!$rounds && (float)($r['counter_price']??0)>0){
      $rounds=[['by'=>(string)($r['counter_by']??'seller'),'price'=>(float)$r['counter_price'],'at'=>(string)($r['responded_at']??'')]];
    }
    foreach($rounds as $i=>$c){
      $mine=(($c['by']??'seller')==='buyer');
      $s[]=['ok'=>true,'at'=>(string)($c['at']??''),
            'title'=>($mine?t('You countered'):t('Seller countered')).' · '.t('round').' '.($i+1).'/'.VESTRA_OFFER_MAX_COUNTERS,
            'note'=>eur($c['price']??0).'/'.t('unit').' × '.$q.' = '.eur((float)($c['price']??0)*$q)];
    }
    if($st==='accept'){
      $by=(($r['accepted_by']??'')==='buyer')?t('You accepted the counter offer'):t('Your offer was accepted');
      $s[]=['ok'=>true,'at'=>(string)($r['accepted_at']??$r['responded_at']??''),'title'=>$by,
            'note'=>t('Agreed').': '.eur(vestra_offer_agreed_unit($ref,$r,$o)).'/'.t('unit')];
      $ivs=vestra_invoices_for_ref($ref);
      $s[]=$ivs
        ? ['ok'=>true,'at'=>'','title'=>t('Invoice issued'),'note'=>t('Pay by bank transfer to the account on the invoice. Goods ship once payment arrives.')]
        : ['ok'=>false,'at'=>'','title'=>t('Invoice being prepared'),'note'=>t('We confirm stock first, then issue the invoice — you will get it by e-mail.')];
    } elseif($st==='decline'){
      $by=(($r['declined_by']??'')==='buyer')?t('You declined the counter offer'):t('The seller declined');
      $s[]=['ok'=>true,'at'=>(string)($r['declined_at']??$r['responded_at']??''),'title'=>$by,'note'=>t('This negotiation is closed.')];
    } elseif($st==='counter'){
      /* Sira kimde: alicinin kendi karsi teklifinden sonra beklemesi
         gerektigini soylemeyen bir cizelge, ona hala bir dugme aramasi
         gerektigini dusundururdu. */
      $left=vestra_offer_counters_left($r);
      $s[]=vestra_offer_turn($r)==='buyer'
        ? ['ok'=>false,'at'=>'','title'=>t('Waiting for your answer'),
           'note'=>$left>0 ? t('Accept, decline, or send your own counter offer below.').' <span class="hint">('.sprintf(t('%d counter offer(s) left'),$left).')</span>'
                           : t('The counter limit is reached — you can accept or decline.')]
        : ['ok'=>false,'at'=>'','title'=>t('Waiting for the seller'),'note'=>t('You countered — we will come back with their answer.')];
    }
    return $s;
  };

  /* Karsi teklif dugmeleri — kart ve detayda AYNI kod. */
  $ctaFor = function(string $ref, ?array $r) {
    /* Dugmeler yalnizca SIRA ALICIDAYKEN. Kendi karsi teklifini
       bekleyen aliciya "kabul et" gostermek, kendi rakamini kendine
       onaylatmak olurdu. */
    if(!$r || vestra_offer_turn($r)!=='buyer') return '';
    $tok=(string)($r['accept_token']??''); $cp=(float)($r['counter_price']??0);
    if($tok==='' || $cp<=0) return '';
    $u='/offer-accept?ref='.rawurlencode($ref).'&amp;token='.rawurlencode($tok);
    $h='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">'
      .'<a class="btn btn-p btn-sm" href="'.$u.'">'.sprintf(t('Accept %s per unit'), eur($cp)).'</a>'
      .'<a class="btn btn-o btn-sm" href="'.$u.'&amp;intent=decline">'.t('Decline').'</a>';
    if(vestra_offer_counters_left($r)>0){
      $h.='<a class="btn btn-o btn-sm" href="'.$u.'#counter">'.t('Counter offer').'</a>';
    }
    return $h.'</div>';
  };

  $badge = function(?array $r) {
    if(!$r) return '<span class="status open">'.t('Pending seller').'</span>';
    return match((string)($r['status']??'')){
      'accept'  => '<span class="status offers">✓ '.t('Accepted').'</span>',
      'decline' => '<span class="status">✗ '.t('Declined').'</span>',
      'counter' => '<span class="status open">↩ '
                   .((($r['counter_by']??'seller')==='buyer') ? t('Your counter') : t('Counter'))
                   .': '.eur($r['counter_price']??0).'/u</span>'
                   .'<div class="hint">'.vestra_offer_counter_count($r).'/'.VESTRA_OFFER_MAX_COUNTERS.' '.t('rounds').'</div>',
      default   => '<span class="status open">'.t('Pending seller').'</span>',
    };
  };

  echo '<style>
    .offtl{list-style:none;margin:14px 0 0;padding:0}
    .offtl li{position:relative;padding:0 0 16px 26px;border-left:2px solid var(--line);margin-left:6px}
    .offtl li:last-child{border-left-color:transparent;padding-bottom:0}
    .offtl li::before{content:"";position:absolute;left:-7px;top:3px;width:12px;height:12px;border-radius:50%;
      background:var(--bg2);border:2px solid var(--line)}
    .offtl li.on::before{background:var(--acc);border-color:var(--acc)}
    .offtl b{display:block;font-size:14px}
    .offtl .hint{margin-top:2px}
  </style>';

  if($vo){
    $ref=(string)($vo['ref']??''); $r=$offerResp[$ref]??null;
    $l=vestra_listing_by_sku($vo['sku']??''); $img=$l?vestra_primary_image($l):'';
    echo '<div style="margin-bottom:14px"><a class="btn btn-o btn-sm" href="/buyer?tab=offers">← '.t('All offers').'</a></div>';
    echo '<div class="panelcard"><div class="pcfhead"><h3>'.htmlspecialchars($ref).'</h3>'.$badge($r).'</div>';
    echo '<div class="panelcard-body" style="padding:4px">';
    echo '<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:6px">';
    if($img) echo '<img src="'.htmlspecialchars($img).'" alt="" style="width:74px;height:74px;object-fit:cover;border-radius:10px;border:1px solid var(--line)">';
    echo '<div style="flex:1;min-width:180px"><b style="font-size:15px">'.htmlspecialchars($vo['product']??'').'</b>'
        .'<div class="hint">'.(int)($vo['qty']??0).'× · SKU '.htmlspecialchars($vo['sku']??'').'</div>';
    if($l && !empty($l['id'])) echo '<a href="/product?id='.urlencode((string)$l['id']).'" style="color:var(--acc);font-size:13px">'.t('View product').' ↗</a>';
    echo '</div></div>';
    echo '<ul class="offtl">';
    foreach($steps($vo,$r) as $s){
      echo '<li class="'.($s['ok']?'on':'').'"><b>'.htmlspecialchars($s['title']).'</b>'
          .($s['at']!==''?'<div class="hint">'.htmlspecialchars(substr($s['at'],0,16)).'</div>':'')
          .'<div class="hint">'.$s['note'].'</div></li>';
    }
    echo '</ul>';
    echo $ctaFor($ref,$r);
    foreach(vestra_invoices_for_ref($ref) as $iv){
      echo '<div style="margin-top:12px"><a class="btn btn-o btn-sm" href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener">📄 '
          .t('Invoice').' '.htmlspecialchars(vestra_invoice_link_label($iv)).'</a></div>';
    }
    echo '</div></div>';

  } else {
    echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Offers made').'</h3></div>';
    if(!$offers) dash_empty(t('No offers yet. Make an offer on a make-an-offer product.'));
    else {
      echo '<div class="ordlist" style="display:flex;flex-direction:column;gap:12px;padding:4px 0">';
      foreach(array_reverse($offers) as $o){
        $ref=(string)($o['ref']??''); $r=$offerResp[$ref]??null;
        $l=vestra_listing_by_sku($o['sku']??''); $img=$l?vestra_primary_image($l):'';
        $agreed = ($r && ($r['status']??'')==='accept') ? vestra_offer_agreed_unit($ref,$r,$o) : 0.0;
        echo '<div style="background:var(--bg2);border:1px solid var(--line);border-radius:14px;padding:15px 17px">'
            .'<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">'
            .'<a href="/buyer?tab=offers&view='.urlencode($ref).'" style="font-weight:700;font-size:15px;color:var(--ink)">'.htmlspecialchars($ref).'</a>'
            .$badge($r).'</div>'
            .'<div style="display:flex;gap:12px;align-items:center;margin-top:11px">';
        if($img) echo '<img src="'.htmlspecialchars($img).'" alt="" style="width:46px;height:46px;object-fit:cover;border-radius:8px;border:1px solid var(--line)">';
        echo '<div style="flex:1;min-width:0"><div style="font-size:13.5px">'.htmlspecialchars($o['product']??'').'</div>'
            .'<div class="hint">'.(int)($o['qty']??0).'× · '.t('Your offer').' '.eur($o['offer_unit']??0).'/u'
            .($agreed>0 ? ' · <b>'.t('Agreed').' '.eur($agreed).'/u</b>' : '').'</div></div>'
            .'<a class="btn btn-o btn-sm" href="/buyer?tab=offers&view='.urlencode($ref).'">'.t('Track').' →</a>'
            .'</div>'
            .$ctaFor($ref,$r)
            .'</div>';
      }
      echo '</div>';
    }
    echo '</div>';
  }

} elseif($tab==='messages'){
  $tid = $_GET['thread'] ?? '';
  $thread = $tid ? vestra_msg_find_thread($tid) : null;
  if ($thread && ($thread['buyer_uid']??'') !== $uid) $thread = null;
  // (marked read earlier, before head.php rendered the nav badge — see top of file)
  $myThreads = vestra_msg_my_threads($uid);

  $listHtml = '<div class="mssearch"><input id="mfilter" placeholder="'.htmlspecialchars(t('Search conversations…')).'" oninput="mFilterThreads(this.value)"></div>';
  if (!$myThreads) {
    $listHtml .= '<p class="hint" style="padding:0 10px">'.t('No messages yet. Start a conversation from any product page.').'</p>';
  } else {
    $listHtml .= '<div class="threadlist" id="mThreadList">';
    foreach ($myThreads as $th) {
      $last = end($th['messages']);
      $unread = vestra_msg_unread($th, $uid);
      $name = vestra_msg_counterpart_label($th, $uid);
      $listHtml .= '<a class="threadrow'.($unread?' unread':'').($th['id']===$tid?' active':'').'" data-name="'.htmlspecialchars(mb_strtolower($name)).'" href="/buyer?tab=messages&thread='.urlencode($th['id']).'">
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
    $mainHtml = '<div class="msghead"><h3 style="margin:0">'.htmlspecialchars($ctp).'</h3><a class="btn btn-o btn-sm" href="/buyer?tab=messages">← '.t('Back').'</a></div>';
    if (!empty($thread['listing_id']) && ($tl = vestra_listing_by_id($thread['listing_id']))) {
      $mainHtml .= '<p class="hint" style="margin:10px 18px 0">🔗 <a class="acc" href="/product?id='.urlencode($thread['listing_id']).'">'.htmlspecialchars(trim(($tl['brand']??'').' — '.($tl['name']??''), ' —')).'</a></p>';
    }
    if (in_array($msgerr, ['email','iban','phone'], true)) {
      $mainHtml .= '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin:10px 18px 0">⚠ '.t('For your safety, sharing email addresses, phone numbers, or bank/IBAN details is not allowed here — all communication and payment must stay on VESTRA so buyer protection still applies. Your message was not sent.').'</div>';
    }
    $mainHtml .= '<div class="msgthread" id="mThread">';
    foreach ($thread['messages'] as $m) {
      if (($m['from']??'') === 'system') { $mainHtml .= vestra_msg_system_html($m, 'buyer'); continue; }
      $mine = ($m['from']??'') === $uid;
      $mainHtml .= '<div class="msgbubblewrap '.($mine?'mine':'').'"><div class="msgbubble '.($mine?'mine':'').'">'.
        nl2br(htmlspecialchars($m['text']??'')).
        '<div class="msgtime">'.htmlspecialchars(substr($m['at']??'',0,16)).'</div></div></div>';
    }
    $mainHtml .= '</div>';
    $mainHtml .= '<form method="post" action="/buyer?tab=messages" class="msgcompose">
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
      'setInterval(function(){fetch("/buyer?tab=messages&thread='.urlencode($tid).'&poll=1",{cache:"no-store"})'.
      '.then(function(r){return r.json()}).then(function(d){if(d.last&&d.last!==last)location.reload()})'.
      '.catch(function(){})},15000)})();</script>';
  }

} elseif($tab==='profile') {
  $u = $AUTH_USER ?? [];
  if(isset($_GET['saved'])) echo '<div class="banner ok">✓ '.t('Profile saved.').'</div>';
  if(isset($_GET['pw'])) echo '<div class="banner ok">✓ '.t('Password updated.').'</div>';
  if(isset($_GET['pwerr'])) echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">'.
    match($_GET['pwerr']){ 'cur'=>t('Current password is incorrect.'), 'len'=>t('Password must be at least 8 characters.'), default=>t('Passwords do not match.') }.'</div>';
  if(isset($_GET['err']) && $_GET['err']==='country') echo '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad)">⚠ '.
    t('We are not able to serve this market. Nothing was saved.').'</div>';

  ?>
  <div class="panelcard">
    <form method="post" action="/buyer?tab=profile" class="addform">
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
        <?php $_tax = vestra_tax_id_hint($u['country'] ?? ''); ?>
        <div><label><?= t($_tax['label']) ?></label><input name="vat_id" value="<?= htmlspecialchars($u['vat_id']??'') ?>" placeholder="<?= htmlspecialchars($_tax['placeholder']) ?>"></div>
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
        <div><label><?= t('Account ID') ?></label><input value="<?= htmlspecialchars($u['id']??'—') ?>" disabled title="<?= htmlspecialchars(t('Your unique VESTRA account ID')) ?>"></div>
      </div>
      <button class="btn btn-p" type="submit"><?= t('Save changes') ?></button>
    </form>
  </div>
  <div class="panelcard">
    <div class="pcfhead"><h3><?= t('Security') ?></h3></div>
    <form method="post" action="/buyer?tab=profile" class="addform" autocomplete="off">
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

} else { // kyc
  $kybSt   = $AUTH_USER['kyb_status'] ?? 'pending';
  $docReqs = $AUTH_USER['doc_requests'] ?? [];
  /* Kayittan gelen company_reg / vat_cert satirlarini alicidan GIZLE. Bu ikisi
     artik istenmiyor (bkz. inc/auth.php) ama mevcut hesaplarin kaydinda duruyor,
     ve alici panelinde "Upload erforderlich" olarak asili kaliyordu.
     Sadece HIC dokunulmamis olanlar (status=requested) gizleniyor: birisi gercekten
     yuklediyse satir yerinde kaliyor, yoksa yukledigi dosyayi goremez hale gelirdi.
     Kayit silinmiyor, sadece cizilmiyor -- admin tarafinda gecmis olarak duruyor. */
  $docReqs = array_values(array_filter($docReqs, function($r){
      return !(in_array($r['type'] ?? '', ['company_reg','vat_cert'], true)
               && ($r['status'] ?? 'requested') === 'requested');
  }));
  $docTypes= auth_doc_types();
  $kybLabel = $kybSt==='approved'
    ? '<span class="status offers">✓ '.t('Verified').'</span>'
    : ($kybSt==='suspended'
        ? '<span class="status" style="color:var(--bad)">⊘ '.t('Suspended').'</span>'
        : '<span class="status open">'.t('Pending review').'</span>');
  if(isset($_GET['uploaded'])) echo '<div class="banner ok">✓ '.t('Document uploaded — the admin will review it shortly.').'</div>';
  if(isset($_GET['upload_err'])) echo vestra_doc_upload_err_banner((string)$_GET['upload_err']);
  echo '<div class="panelcard"><div class="pcfhead"><h3>'.t('Business verification').'</h3>'.$kybLabel.'</div>';
  /* "Prices open as soon as you upload it" YALANDI: 11 Agustos'tan beri
     fiyat kapisi ONAYLA aciliyor (auth_prices_unlocked yalnizca approved'a
     bakar). Eski cumle, belgesini yukleyip fiyat goremeyen aliciya panelin
     kendisinin yanlis soz vermesi demekti. */
  echo '<p class="hint" style="margin:0 0 16px">'.t('Verified buyers can access wholesale pricing and place orders with buyer protection. Please upload your trade licence / business registration here — we keep it on file for compliance. Your account is opened by our review, and you can add the document at any time.').'</p>';
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
        $actionCell = vestra_doc_upload_form('/buyer?tab=kyc', (string)$r['id'], t('Upload'));
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
  echo vestra_doc_email_hint();
  echo '</div>';
  echo vestra_doc_upload_js();
}
dash_close();
require __DIR__.'/inc/foot.php';
