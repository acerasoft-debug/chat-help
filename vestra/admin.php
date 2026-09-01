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
require_once __DIR__.'/inc/money.php';
require_once __DIR__.'/inc/api_keys.php';
require_once __DIR__.'/inc/dropship.php';
if(session_status()===PHP_SESSION_NONE) session_start();

$PASS   = (string)vestra_cfg('admin_pass','');
$locked = ($PASS==='');

if(isset($_GET['logout'])){ unset($_SESSION['vadmin'],$_SESSION['vadmin_csrf']); header('Location: /admin'); exit; }
$err=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pass'])){
  $tkey='admin|'.($_SERVER['REMOTE_ADDR']??'');
  if(auth_throttled($tkey)){ $err=true; }
  elseif(!$locked && hash_equals($PASS,(string)$_POST['pass'])){
    auth_throttle_clear($tkey); $_SESSION['vadmin']=true;
    vestra_sec_log('admin_ok');
    /* Giristen sonra GELINEN adrese don, duz /admin'e degil. Giris formu eylemsiz
       ("<form method=post>") oldugu icin sorgu dizesiyle birlikte kendi URL'ine
       gonderiyor; burada onu atmak, bir baglantiyla gelen her seyi sessizce
       dusuruyordu -- on-doldurulmus banka formu cikisken acildiginda alanlar bos
       geliyor ve kullanici "yine olmadi" diyordu.
       Yalnizca /admin ile baslayan KENDI yollarimiz kabul ediliyor: disariya
       yonlendirme (open redirect) acmamak icin. Ters egik cizgi de eleniyor --
       "/\evil.com" bazi tarayicilarda dis adres gibi cozuluyor. */
    $back = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($back === '' || !preg_match('#^/admin(\?|$)#', $back) || str_contains($back, '\\')) $back = '/admin';
    header('Location: '.$back); exit;
  }
  else { auth_throttle_hit($tkey); vestra_sec_log('admin_fail'); sleep(1); $err=true; }
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
  /* TASLAK ONIZLEME -- operator kararı (1 Eyl 2026): "faturayi musteri
     hesabina inmeden ve email ile gondermeden kendim kontrol etmem gerekiyor".
     Kesilecek belgenin AYNISINI cizer (ayni payload, ayni cizim yolu, formda
     SECILI duran satici dahil) ama:
       - numara YANMAZ (vestra_next_invoice_no hic cagrilmaz),
       - diske YAZILMAZ (vestra_ensure_invoice hic cagrilmaz),
       - aliciya e-posta GITMEZ, hesabinda gorunmez,
       - secim KALICI OLMAZ ($pickOverride -- kayit ancak Approve'da yazilir),
       - ustunde 'DRAFT INVOICE / not assigned yet' yazar ki yanlislikla
         iletilse bile fatura sanilmasin.
     Onay dugmesine basilana kadar alici tarafinda HICBIR SEY olusmaz; kontrol
     adimi tam olarak bu bosluk. POST olmasi sart: onizlenen satici, formdaki
     acilir listenin O ANKI degeri, kaydedilmis bir deger degil. */
  if($act==='preview_offer_invoice'){
    $ref=preg_replace('/[^A-Za-z0-9_-]/','',$_POST['ref']??'');
    require_once __DIR__.'/inc/offers.php';
    require_once __DIR__.'/inc/invoice.php';
    $rs=vestra_read_json('offer_responses.json');
    if((($rs[$ref]['status'] ?? '')) !== 'accept'){
      header('Location: /admin?tab=invoices&msg=invoice_none'); exit;
    }
    /* Ayni dogrulama, ayni sebeple: onizlemede gecen bir secim onayda da
       gececek sanilir. Gecersiz hesap burada da durdurulur. */
    $pick = preg_replace('/[^A-Za-z0-9_-]/','',(string)($_POST['seller_uid'] ?? ''));
    if($pick!==''){
      $ok = ($pick==='vestra');
      if(!$ok) foreach(auth_accounts() as $sa){ if(($sa['id']??'')===$pick && ($sa['type']??'')==='seller'){ $ok=true; break; } }
      if(!$ok){ header('Location: /admin?tab=invoices&msg=invoice_seller_bad'); exit; }
    }
    /* KDV satiri: formda O AN ne yaziyorsa taslak onu tasir -- satici
       secimiyle ayni desen, kayda gecmez. */
    $vn = array_key_exists('vat_note',$_POST)
        ? mb_substr(trim(preg_replace('/\s+/',' ',(string)$_POST['vat_note'])),0,200)
        : null;
    /* '' de bilincli bir deger: alani silen operator kargosuz onizleme bekler. */
    $sh = array_key_exists('shipping',$_POST) ? round(max(0.0, vestra_price_input($_POST['shipping'])),2) : null;
    $p = vestra_offer_invoice_payload($ref, $pick, $vn, $sh);
    if(!$p){ header('Location: /admin?tab=invoices&msg=invoice_none'); exit; }
    $bytes = vestra_render_invoice_pdf($p['meta'], $p['items'], $p['seller'], '', true);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="DRAFT-'.$ref.'.pdf"');
    header('Cache-Control: no-store');          // alici verisi tasiyan taslak ara belleklere dusmesin
    header('Content-Length: '.strlen($bytes));
    echo $bytes; exit;
  }
  /* KESILMIS TEKLIF FATURASINI DUZELT (redraft) -- operator istegi
     (1 Eyl 2026): "faturayi kestik fakat 50 eur shipping ucreti koyarak
     tekrar yap sistemdeki faturayi degistir ... hemen ardindan faturaniz
     hazirdir diye faturayi email olarak gonder".
     AYNI numara korunur (vestra_ensure_invoice redraft=true), dosya yerinde
     yeniden yazilir; alici panelindeki baglanti kendiliginden duzeltilmis
     belgeyi verir (ayni yol). Kargo kayda gecer, belge kayittan okur.
     Kesimden farki: numara yakilmaz, uyelik baglari degismez.
     E-posta REDRAFT'ta otomatik: alici eski toplami bilerek yanlis tutar
     gondermesin -- duzeltilmis PDF ekte, yeni toplam govdede. */
  if($act==='preview_redraft_offer_invoice' || $act==='redraft_offer_invoice'){
    require_once __DIR__.'/inc/offers.php';
    require_once __DIR__.'/inc/invoice.php';
    $ref=preg_replace('/[^A-Za-z0-9_-]/','',$_POST['ref']??'');
    $sh = array_key_exists('shipping',$_POST) ? round(max(0.0, vestra_price_input($_POST['shipping'])),2) : null;
    $p = vestra_offer_invoice_redraft_payload($ref, $sh);
    if(!empty($p['error'])){
      header('Location: /admin?tab=invoices&msg=combine_bad&why='.rawurlencode($p['error'])); exit;
    }
    if($act==='preview_redraft_offer_invoice'){
      $bytes = vestra_render_invoice_pdf($p['meta'], $p['items'], $p['seller'], '', true);
      header('Content-Type: application/pdf');
      header('Content-Disposition: inline; filename="DRAFT-duzeltme-'.$ref.'.pdf"');
      header('Cache-Control: no-store');
      header('Content-Length: '.strlen($bytes));
      echo $bytes; exit;
    }
    /* Kayit belgeden ONCE (ayni gerekce: kesim yarida kalirsa kayit dogru
       kalir ve tekrar denenebilir). */
    if($sh!==null){
      $rs=vestra_read_json('offer_responses.json');
      $rs[$ref]['invoice_shipping']=$sh;
      vestra_write_json('offer_responses.json',$rs);
    }
    $iv=vestra_ensure_invoice($p['meta'], $p['items'], $p['seller'], true, true);
    $ok = $iv && ($iv['no'] ?? '')!=='' && !empty($iv['redrafted']);
    if($ok){
      $em=(string)($p['meta']['buyer']['email'] ?? '');
      if(filter_var($em,FILTER_VALIDATE_EMAIL)){
        require_once __DIR__.'/inc/notify.php';
        $lines='';
        foreach($p['items'] as $it){ $lines.=sprintf("  %-14s %4d x EUR %s = EUR %s\n",$it['sku'],$it['qty'],number_format($it['unit'],2),number_format($it['line'],2)); }
        $goods=(float)array_sum(array_column($p['items'],'line'));
        $shp=(float)($p['meta']['shipping'] ?? 0);
        vestra_send_mail($em, "VESTRA — your invoice {$iv['no']} is ready",
          "Hello ".(($p['meta']['buyer']['company']??'')?:'there').",\n\nYour invoice ({$iv['no']}) is ready — the corrected PDF is attached and replaces any earlier copy of the same invoice number.\n\n"
         .$lines."\n  Goods total : EUR ".number_format($goods,2)."\n"
         .($shp>0 ? "  Shipping    : EUR ".number_format($shp,2)."\n" : '')
         ."  TOTAL DUE   : EUR ".number_format($goods+$shp,2)."\n\n"
         ."Please pay by bank transfer to the account shown on the invoice, quoting reference ".$p['meta']['ref'].". You can also download it any time under My offers.\n\n"
         ."View: https://vestrasales.com/buyer?tab=offers\n\n— VESTRA · vestrasales.com",
          '','',null,'',['attachments'=>[['name'=>'Invoice-'.$iv['no'].'.pdf','path'=>$iv['path']]]]);
      }
    }
    header('Location: /admin?tab=invoices&msg='.($ok?'invoice_redrafted':'invoice_none')); exit;
  }
  /* Fatura ODENDI isareti: alici panelindeki "odenmesi gereken fatura"
     uyarisini kapatan tek sey. Birincil ref'in kaydinda durur. */
  if($act==='offer_invoice_paid_toggle'){
    require_once __DIR__.'/inc/offers.php';
    $ref=preg_replace('/[^A-Za-z0-9_-]/','',$_POST['ref']??'');
    $rs=vestra_read_json('offer_responses.json');
    if($ref!=='' && isset($rs[$ref])){
      if(!empty($rs[$ref]['invoice_paid_at'])) unset($rs[$ref]['invoice_paid_at']);
      else $rs[$ref]['invoice_paid_at']=date('c');
      vestra_write_json('offer_responses.json',$rs);
    }
    header('Location: /admin?tab=invoices&msg=invoice_paid_toggled'); exit;
  }
  /* SECILEN TEKLIFLERDEN TEK FATURA -- taslak ve kesim. Kurallar ve
     gerekceler kurucuda (vestra_offers_combined_invoice_payload): ayni alici,
     tek satici, satir basina anlasilan fiyat, birincil ref'in adina tek belge,
     uyeler invoice_group_ref ile bagli. */
  if($act==='combine_preview_offer_invoice' || $act==='combine_issue_offer_invoice'){
    require_once __DIR__.'/inc/offers.php';
    require_once __DIR__.'/inc/invoice.php';
    $refs = array_map('strval', (array)($_POST['refs'] ?? []));
    $pick = preg_replace('/[^A-Za-z0-9_-]/','',(string)($_POST['seller_uid'] ?? ''));
    if($pick!==''){
      $ok = ($pick==='vestra');
      if(!$ok) foreach(auth_accounts() as $sa){ if(($sa['id']??'')===$pick && ($sa['type']??'')==='seller'){ $ok=true; break; } }
      if(!$ok){ header('Location: /admin?tab=invoices&msg=invoice_seller_bad'); exit; }
    }
    $vn = array_key_exists('vat_note',$_POST)
        ? mb_substr(trim(preg_replace('/\s+/',' ',(string)$_POST['vat_note'])),0,200)
        : null;
    $sh = array_key_exists('shipping',$_POST) ? round(max(0.0, vestra_price_input($_POST['shipping'])),2) : null;
    $p = vestra_offers_combined_invoice_payload($refs, $pick, $vn, $sh);
    if(!empty($p['error'])){
      header('Location: /admin?tab=invoices&msg=combine_bad&why='.rawurlencode($p['error'])); exit;
    }
    if($act==='combine_preview_offer_invoice'){
      $bytes = vestra_render_invoice_pdf($p['meta'], $p['items'], $p['seller'], '', true);
      header('Content-Type: application/pdf');
      header('Content-Disposition: inline; filename="DRAFT-birlesik-'.$p['meta']['ref'].'.pdf"');
      header('Cache-Control: no-store');
      header('Content-Length: '.strlen($bytes));
      echo $bytes; exit;
    }
    /* KESIM. Once kayit: secim + KDV satiri birincile, uyelik baglari
       digerlerine -- belge kayittan once degil SONRA yazilsaydi ve kesim
       yarida kalsaydi baglar kopuk kalirdi; bu sirayla en kotu durumda
       baglanmis ama belgesiz bir grup kalir, kuyruk onu yeniden gosterir
       (vestra_invoices_for_ref bos doner). */
    $rs=vestra_read_json('offer_responses.json');
    $primary=$p['meta']['ref'];
    $rs[$primary]['invoice_seller_uid']=$p['seller_pick'];
    $rs[$primary]['invoice_seller_by']='operator';
    $rs[$primary]['invoice_seller_at']=date('c');
    if($vn!==null) $rs[$primary]['invoice_vat_note']=$vn;
    if($sh!==null) $rs[$primary]['invoice_shipping']=$sh;
    $rs[$primary]['invoice_members']=$p['refs'];
    foreach($p['refs'] as $r){ if($r!==$primary) $rs[$r]['invoice_group_ref']=$primary; }
    vestra_write_json('offer_responses.json',$rs);
    $iv=vestra_ensure_invoice($p['meta'], $p['items'], $p['seller'], true);
    $issued = $iv && ($iv['no'] ?? '') !== '';
    if($issued){
      $em=(string)($p['meta']['buyer']['email'] ?? '');
      if(filter_var($em,FILTER_VALIDATE_EMAIL)){
        require_once __DIR__.'/inc/notify.php';
        $lines='';
        foreach($p['items'] as $it){ $lines.=sprintf("  %-14s %4d x EUR %s = EUR %s\n",$it['sku'],$it['qty'],number_format($it['unit'],2),number_format($it['line'],2)); }
        $shp=(float)($p['meta']['shipping'] ?? 0);
        $tot="  Goods total : EUR ".number_format($p['total'],2)."  ({$p['qty']} pcs)\n"
            .($shp>0 ? "  Shipping    : EUR ".number_format($shp,2)."\n" : '')
            ."  TOTAL DUE   : EUR ".number_format($p['total']+$shp,2)."\n";
        /* PDF EKTE: "faturayi email olarak gonder" (operator istegi, 1 Eyl 2026).
           Baglanti da duruyor -- ek suzulse bile belgeye ulasilir. */
        vestra_send_mail($em, "VESTRA — your invoice {$iv['no']} is ready",
          "Hello ".(($p['meta']['buyer']['company']??'')?:'there').",\n\nYour invoice ({$iv['no']}) for the accepted offers is ready — the PDF is attached.\n\n"
         .$lines."\n".$tot."\n"
         ."Please pay by bank transfer to the account shown on the invoice, quoting reference {$primary}. Your goods ship as soon as the payment arrives.\n"
         ."You can also download it any time under My offers.\n\n"
         ."View: https://vestrasales.com/buyer?tab=offers\n\n— VESTRA · vestrasales.com",
          '','',null,'',['attachments'=>[['name'=>'Invoice-'.$iv['no'].'.pdf','path'=>$iv['path']]]]);
      }
    }
    header('Location: /admin?tab=invoices&msg='.($issued?'invoice_issued':'invoice_none')); exit;
  }
  /* Kabul edilmis TEKLIFIN faturasini kes. Ayri islem, cunku kaynak ayri
     dosya: siparisler orders.csv'de, teklifler offers.csv + offer_responses
     .json'da. Tutar vestra_offer_agreed_unit()'ten -- karsi teklif verilmisse
     anlasilan fiyat, ilk teklif degil. */
  if($act==='issue_offer_invoice'){
    $ref=preg_replace('/[^A-Za-z0-9_-]/','',$_POST['ref']??'');
    require_once __DIR__.'/inc/offers.php';
    $rs=vestra_read_json('offer_responses.json');
    /* Kabul edilmemis bir teklife fatura kesilemez: onay kuyrugunda
       gorunmese bile dogrudan POST edilebilir. */
    if((($rs[$ref]['status'] ?? '')) !== 'accept'){
      header('Location: /admin?tab=invoices&msg=invoice_none'); exit;
    }
    /* FATURAYI KESEN SATICI operatorun secimi. Formdan geliyorsa once
       DOGRULANIR, sonra kaydedilir -- kaydedilen sey belgenin uzerindeki
       tuzel kisi, ve dosya adi da bu anahtardan turuyor (vestra_invoice_file).
       Var olmayan bir uid sessizce platforma dusseydi fatura Acerasoft LLC
       adina cikardi: operatorun sectigi degil, secemedigi kisi.
       Secim faturadan ONCE yaziliyor ki belge ile kayit ayni sey olsun. */
    $dirty=false;
    $pick = preg_replace('/[^A-Za-z0-9_-]/','',(string)($_POST['seller_uid'] ?? ''));
    if($pick!=='' && $pick!==(string)($rs[$ref]['invoice_seller_uid'] ?? '')){
      $ok = ($pick==='vestra');
      if(!$ok) foreach(auth_accounts() as $sa){ if(($sa['id']??'')===$pick && ($sa['type']??'')==='seller'){ $ok=true; break; } }
      if(!$ok){ header('Location: /admin?tab=invoices&msg=invoice_seller_bad'); exit; }
      $rs[$ref]['invoice_seller_uid']=$pick;
      $rs[$ref]['invoice_seller_by']='operator';
      $rs[$ref]['invoice_seller_at']=date('c');
      $dirty=true;
    }
    /* KDV satiri da secimle ayni anda kayda gecer -- belge onu kayittan okur
       (vestra_offer_invoice_payload). Alan formda HEP var, o yuzden bos
       gonderim "sil" demek; tek satira indirilip kirpiliyor cunku PDF'te tek
       bir 'VAT:' satirina basiliyor. */
    if(array_key_exists('vat_note',$_POST)){
      $vn = mb_substr(trim(preg_replace('/\s+/',' ',(string)$_POST['vat_note'])),0,200);
      if($vn !== (string)($rs[$ref]['invoice_vat_note'] ?? '')){ $rs[$ref]['invoice_vat_note']=$vn; $dirty=true; }
    }
    /* KARGO da secim gibi belgeden ONCE kayda gecer; belge kayittan okur.
       vestra_price_input: ham (float) virgullu ondalikta para kaybettirir. */
    if(array_key_exists('shipping',$_POST)){
      $sh = round(max(0.0, vestra_price_input($_POST['shipping'])), 2);
      if(abs($sh - (float)($rs[$ref]['invoice_shipping'] ?? 0)) > 0.004){ $rs[$ref]['invoice_shipping']=$sh; $dirty=true; }
    }
    if($dirty) vestra_write_json('offer_responses.json',$rs);
    $iv=vestra_offer_issue_invoice($ref, true);
    $issued = $iv && ($iv['no'] ?? '') !== '';
    if($issued){
      $orow=vestra_offer_row($ref);
      if($orow && filter_var($orow['email']??'',FILTER_VALIDATE_EMAIL)){
        require_once __DIR__.'/inc/notify.php';
        $u=vestra_offer_agreed_unit($ref); $q=(int)($orow['qty']??0);
        $iv_att = is_file((string)($iv['path']??'')) ? ['attachments'=>[['name'=>'Invoice-'.$iv['no'].'.pdf','path'=>$iv['path']]]] : [];
        vestra_send_mail($orow['email'], "VESTRA — invoice for {$ref}",
          "Hello ".(($orow['company']??'')?:'there').",\n\nStock is confirmed and your invoice ({$iv['no']}) for the agreed offer is ready.\n\n"
         ."Reference : {$ref}\nProduct   : ".($orow['product']??'')."\nQuantity  : {$q}\nAgreed    : EUR ".number_format($u,2)."/unit  (total EUR ".number_format($u*$q,2).")\n\n"
         ."Download it under My offers and pay by bank transfer to the account shown on the invoice. Your goods ship as soon as the payment arrives.\n\n"
         ."View: https://vestrasales.com/buyer?tab=offers&view=".rawurlencode($ref)."\n\n— VESTRA · vestrasales.com",
          '','',null,'',$iv_att);
      }
    }
    header('Location: /admin?tab=invoices&msg='.($issued?'invoice_issued':'invoice_none')); exit;
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
      /* Bolme. Gecersiz/bos deger MEVCUDU korur, varsayilana DUSURMEZ: formda
         alan bos gelirse (eski bir sekme, kismi bir gonderim) ayakkabi olarak
         isaretlenmis bir urun sessizce Premium'a geri kaymasin. */
      $sec = strtolower(trim((string)($_POST['section'] ?? '')));
      if (isset(vestra_sections()[$sec])) $p['section'] = $sec;
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
      /* Malin CIKTIGI yer. Bos BIRAKMAK gecerli bir secim: o zaman urun
         sayfasi platform varsayilanina ("Ships from EU") duser ve liste
         tablosunda "⚠ not set" olarak isaretli kalir. Alan formda gelmediyse
         (eski bir sekme) mevcut degere DOKUNMUYORUZ. */
      if(isset($_POST['ships_from'])){
        $sf=$one($_POST['ships_from']); if(mb_strlen($sf)>40) $sf=mb_substr($sf,0,40);
        if($sf!=='') $p['ships_from']=(mb_strlen($sf)===2?mb_strtoupper($sf):$sf); else unset($p['ships_from']);
      }
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
  /* Kur: ya simdi canli kaynaktan cek, ya da elle gir. Elle girilen kur SON
     CARE -- sunucudan disari HTTP cikisi kapaliysa katalog yine de cevirsin
     diye. Sayfadaki cumle o zaman "ECB kuru" demiyor, "yaklasik kur" diyor. */
  if($act==='fx_refresh'){
    @unlink(vestra_data_dir().'/fx_rates.json');   // onbellegi ve backoff'u sil, yeniden dene
    $ok = vestra_fx('USD') > 0 ? 1 : 0;
    header('Location: /admin?tab=prices&msg=fx_refresh&n='.$ok.'&src='.rawurlencode(vestra_fx_source())); exit;
  }
  /* Ortak API anahtari. Duz metin SADECE bir kez, hemen ardindan gelen sayfada
     gosteriliyor ve oturumda tutuluyor -- diske yalnizca ozeti yaziliyor. Yonlendirme
     adresine koymak en kolayi olurdu ama anahtar o zaman tarayici gecmisine,
     sunucu erisim kaydina ve varsa araya giren her vekile dusrdu. */
  if($act==='api_key_new'){
    $r = vestra_api_key_issue((string)($_POST['label'] ?? ''), (string)($_POST['account'] ?? ''));
    $_SESSION['api_key_once'] = ['secret' => $r['secret'], 'label' => $r['record']['label']];
    header('Location: /admin?tab=api&msg=api_new'); exit;
  }
  if($act==='api_key_revoke'){
    $ok = vestra_api_key_revoke((string)($_POST['kid'] ?? ''));
    header('Location: /admin?tab=api&msg='.($ok ? 'api_revoked' : 'api_notfound')); exit;
  }
  if($act==='fx_manual'){
    vestra_fx_set_manual([
      'USD' => (float)str_replace(',', '.', (string)($_POST['fx_usd'] ?? '')),
      'AUD' => (float)str_replace(',', '.', (string)($_POST['fx_aud'] ?? '')),
      'CAD' => (float)str_replace(',', '.', (string)($_POST['fx_cad'] ?? '')),
    ]);
    header('Location: /admin?tab=prices&msg=fx_manual'); exit;
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
  /* Musterinin FATURA BILGILERINI duzenle. Bu daha once HICBIR yerden yapilamiyordu:
     auth_update() panelde yalnizca KYB/aski/uyelik icin cagriliyordu, sirket adi, adres,
     vergi numarasi ve telefon salt-okunur gosteriliyordu. Sonuc: musteri bu bilgileri
     e-postayla gonderdiginde operatorun onlari SISTEME yazacak yeri yoktu -- fatura
     kesilirken hesapta hala eksik adres duruyordu.
     Bos gelen alan YAZILMIYOR: kismi bir duzenleme (sadece adres girmek) diger alanlari
     silmemeli. */
  /* PLATFORM'un kendi fatura kimligi + banka hesabi. Kurasyonlu katalog urunlerinde
     satici hesabi YOK (seller_uid bos), o yuzden fatura kesilirken satici tarafi
     bos kaliyor ve odeme kutusu hic basilmiyordu -- alici parayi nereye gonderecegini
     faturadan ogrenemiyordu.
     RAKAMLAR BURADAN GIRILIYOR, kodla degil: bu depo herkese acik ve ABD'de
     routing+hesap ikilisi ACH borclandirma icin yeterli. Dosya data/ altinda,
     web'e kapali (.htaccess), .gitignore'da ve 0600. Ayni sebeple workflow girdisi
     olarak da gecirilemez: acik bir depoda Actions girdileri ve log'u herkese gorunur. */
  if($act==='save_platform_billing'){
    $dir=vestra_data_dir(); if(!is_dir($dir)) @mkdir($dir,0775,true);
    $f=$dir.'/platform_seller.json';
    $cur=is_readable($f)?json_decode((string)file_get_contents($f),true):[]; if(!is_array($cur))$cur=[];
    foreach(['company','address','country','email','website',
             'bank_name','bank_holder','bank_iban','bank_bic',
             'bank_routing','bank_account','bank_acct_type','bank_address',
             'vat_id','reg_number'] as $k){
      $v=trim((string)($_POST[$k]??''));
      if($v!=='') $cur[$k]=$v;   // bos alan mevcut degeri SILMEZ
    }
    /* Yazdiktan sonra GERI OKUYOR. file_put_contents'in donusu goz ardi ediliyordu:
       izin/disk sebebiyle yazamazsa kullanici "kaydedildi" sayfasina donuyor ve
       hicbir sey kaydedilmemis oluyor. Bugun tam olarak bu soru soruldu -- "banka
       bilgilerini girdim" denildi, sunucuda dosya YOKTU, ve panel bunu soyleyecek
       hicbir sey basmamisti. Ayni desen bu projede birkac kez cikti: kural yazili,
       kapi calismiyor, sonuc yesil. */
    $ok = @file_put_contents($f,json_encode($cur,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);
    @chmod($f,0600);
    $back = is_readable($f) ? json_decode((string)file_get_contents($f),true) : null;
    $stuck = is_array($back);
    if($stuck) foreach($cur as $k=>$v){ if(trim((string)($back[$k]??''))!==trim((string)$v)){ $stuck=false; break; } }
    if($ok===false || !$stuck){ header('Location: /admin?tab=orders&msg=platform_billing_failed'); exit; }
    header('Location: /admin?tab=orders&msg=platform_billing_saved'); exit;
  }
  if($act==='save_billing'){
    require_once __DIR__.'/inc/invoice.php';
    $uid = $_POST['uid'] ?? '';
    /* Closure, sprintf DEGIL: urlencode'un urettigi %XX diziler sprintf'te
       bicim belirteci sayilir ve yonlendirmeyi bozardi. */
    $to  = fn(string $m) => '/admin?tab=users&msg='.$m.'#ud-'.urlencode((string)$uid);
    $upd = [];
    /* invoice_name: faturadaki ticari unvan, 'company'den AYRI -- company halka
       acik (showroom vitrin adi), fatura unvani degistirilirken magaza adinin
       da degismesi istenmiyor.
       bank_holder: IBAN'in yanindaki isim. Sahis hesabinda genelde sirket
       unvanindan farklidir ve havale formunda YANLIS isim transferi geri
       cevirtir -- bu yuzden ayri alan, ayri girilir.
       BANKA ALANLARI da buradan: satici kendi panelinden giriyordu ama operator
       duzeltemiyordu, ve fatura satici hesabinin IBAN'ini basiyor. Girilmemis
       ya da yanlis bir IBAN'da odeme kutusu ya hic cikmiyor ya yanlis hesabi
       gosteriyor; ikisi de belgeyi kesen operatorun sorunu, dolayisiyla
       duzeltmesi de onun elinde olmali.
       'email' KASITLI olarak yok: auth_update onu zaten kilitli tutuyor
       (giris kimligi + benzersizlik), formda gosterip kaydetmemek ise
       kaydedildi sanilan bir degisiklik olurdu. */
    $textF = ['company','invoice_name','name','address','city','postcode','country',
              'vat_id','reg_number','phone','website'];
    $bankF = ['bank_name','bank_holder','bank_iban','bank_bic','bank_eur_bic',
              'bank_routing','bank_account','bank_acct_type','bank_address'];
    /* "Banka bilgilerini DEGISTIR": once hepsi silinir, sonra yazilanlar
       uygulanir. Bos alan mevcudu korudugu icin (veri kaybini onleyen dogru
       varsayilan) aksi halde ESKI bir IBAN'i kaldirmanin hicbir yolu yoktu --
       hesabini kapatmis bir saticinin numarasi faturada durmaya devam ederdi.
       Ayni gonderimde silip yeniden yazmak da bu sayede tek adim. */
    if (!empty($_POST['bank_replace'])) foreach ($bankF as $f) $upd[$f] = '';
    foreach (array_merge($textF, $bankF) as $f) {
      $v = trim((string)($_POST[$f] ?? ''));
      if ($v === '') continue;
      if ($f === 'bank_iban') {
        $v = vestra_iban_normalize($v);
        /* GECERSIZ IBAN'da HICBIR SEY kaydedilmiyor -- yalnizca o alani atlamak
           digerlerini yesil bir mesajla kaydedip operatore IBAN'in da girdigini
           dusundururdu. */
        if (!vestra_iban_valid($v)) { header('Location: '.$to('billing_iban_bad')); exit; }
      }
      if ($f === 'bank_bic' || $f === 'bank_eur_bic') $v = strtoupper(preg_replace('/\s+/','',$v));
      /* ABD alanlari satici panelindekiyle AYNI bicime getiriliyor: ayni hesap,
         hangi ekrandan girildigine gore iki farkli metin olarak saklanmasin.
         Hesap turu sunucu tarafinda da kisitli -- acilir liste yalnizca
         tarayicida baglayici, POST elle kurulabilir ve faturaya serbest metin
         basmanin bir sebebi yok. */
      if ($f === 'bank_routing') $v = preg_replace('/\D/','',$v);
      if ($f === 'bank_account') $v = preg_replace('/[^0-9A-Za-z]/','',$v);
      if ($f === 'bank_acct_type' && !in_array($v,['Checking','Savings'],true)) continue;
      if ($v === '') continue;
      $upd[$f] = $v;
    }
    if ($uid === '' || !$upd) { header('Location: '.$to('billing_none')); exit; }
    auth_update($uid, $upd);
    /* GERI OKU. auth_update void doner: yazamazsa (izin/disk) sessizce
       basarisiz olur ve panel "kaydedildi" der. Bu depoda tam olarak bu desen
       platform banka bilgilerinde bir kez yasandi. */
    $back = null; foreach (auth_accounts() as $__a) { if (($__a['id'] ?? '') === $uid) { $back = $__a; break; } }
    $stuck = is_array($back);
    if ($stuck) foreach ($upd as $k => $v) { if (trim((string)($back[$k] ?? '')) !== trim((string)$v)) { $stuck = false; break; } }
    header('Location: '.$to($stuck ? 'billing_saved' : 'billing_failed')); exit;
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

    /* Silme kapisi. Onay kutusu "Accounts with orders or invoices cannot be
       deleted" diyordu ama kod yalnizca SATICI tarafindaki acik siparislere
       bakiyordu: alicinin siparisi ve KESILMIS FATURA hic kontrol edilmiyordu.
       Fatura numarali bir belgedir ve musteriye gitmistir -- konusu silinirse
       numara hicbir seye isaret etmez ve muhasebe izi kopar. Kural yaziliydi,
       kapi calismiyordu; bu desen bu projede birkac kez cikti. */
    $openOrders=0; $invoiced=0;
    $vEmail=strtolower(trim((string)($victim['email']??'')));
    foreach(vestra_read_csv('orders.csv') as $o){
      $ref=(string)($o['ref']??'');
      /* Alici tarafi: siparis satirindaki e-posta. Kapali siparis de sayilir
         cunku fatura kontrolu ondan turuyor. */
      $isBuyer  = $vEmail!=='' && strtolower(trim((string)($o['email']??'')))===$vEmail;
      $isSeller = false;
      foreach(vestra_order_lines($o)['lines'] as $l){
        if((string)($l['seller_uid']??'')===$uid){ $isSeller=true; break; }
      }
      if(!$isBuyer && !$isSeller) continue;
      if($ref!=='' && count(vestra_invoices_for_ref($ref))>0){ $invoiced++; continue; }
      if(in_array(strtolower((string)($o['status']??'')),['completed','cancelled','refunded'],true)) continue;
      $openOrders++;
    }
    if($invoiced>0){   header('Location: /admin?tab=users&msg=acct_has_invoice&n='.$invoiced); exit; }
    if($openOrders>0){ header('Location: /admin?tab=users&msg=acct_has_orders'); exit; }

    $af=vestra_data_dir().'/accounts.json';
    if(is_readable($af)) @copy($af,$af.'.bak.'.date('Ymd_His'));
    /* Silinen hesabin KENDI JSON yedegi. accounts.json'in tam kopyasi zaten
       alindi ama onun icinden tek hesabi bulmak, dosya buyudukce is haline
       geliyor. GDPR silme talebi de gelse, "yanlis hesabi sildim" kazasi da
       olsa, aranan sey tek bir kayit. */
    $ddir=vestra_data_dir().'/deleted-accounts';
    if(!is_dir($ddir)) @mkdir($ddir,0775,true);
    @file_put_contents($ddir.'/'.preg_replace('/[^a-z0-9_-]/i','',$uid).'-'.gmdate('Ymd-His').'.json',
      json_encode($victim+['deleted_at'=>gmdate('c')], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

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
  /* Kayit otomatiginin eski listesinden kalan, artik istenmeyen belge
     isteklerini temizle. Mantik auth.php'de tek bir yerde: yuklenmis,
     dosyali, operatorun elle actigi ve halen zorunlu olan istekler
     korunuyor. Sayi mesajla geri yazilir -- "temizledim" deyip sifir
     satir silmek, isin yapildigi izlenimi birakirdi. */
  if($act==='prune_docs'){
    $pr = auth_prune_stale_doc_requests(null, true);
    header('Location: /admin?tab=documents&msg=docs_pruned&n='.(int)$pr['removed'].'&a='.(int)$pr['accounts']); exit;
  }
  if($act==='request_doc'){
    $rduid=$_POST['uid']??''; $rdtype=$_POST['doc_type']??'';
    auth_request_doc($rduid, $rdtype, trim($_POST['note']??''));
    /* Talebi ACMAK yetmiyordu: musteriye hicbir bildirim gitmiyor, o da panele
       girmek icin bir sebep bilmiyordu. Talep, ulasmadigi surece talep degil. */
    $rdacc=null; foreach(auth_accounts() as $a){ if(($a['id']??'')===$rduid){ $rdacc=$a; break; } }
    if($rdacc && !empty($rdacc['email'])){
      $rdlang=vestra_user_lang($rdacc);
      $rdphrase=($rdtype==='trade_licence')
        ? auth_trade_doc_phrase(vestra_visitor_cc($rdacc))
        : vestra_doc_type_label($rdlang,$rdtype);
      $rdurl='https://vestrasales.com/'.((($rdacc['type']??'')==='seller')?'seller':'buyer').'?tab=kyc';
      [$rdS,$rdB,$rdO]=vestra_tpl_doc_requested($rdlang,$rdacc['name']?:($rdacc['company']?:'there'),$rdphrase,$rdurl);
      vestra_send_mail($rdacc['email'],$rdS,$rdB,'','',null,'',$rdO);
    }
    header('Location: /admin?tab=documents&uid='.urlencode($rduid).'&msg=doc_requested'); exit;
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
    /* (float) TEK BASINA para kaybettiriyor: "35,50" -> 35.00, "1.234,56" -> 1.23.
       Turkce klavyede ondalik ayirici virgul ve operator dogal olarak oyle yaziyor. */
    $oCtr = round(vestra_price_input($_POST['counter_price'] ?? ''), 2);
    $res  = vestra_offer_respond($oRef, $oAct, $oCtr, null, 'VESTRA');
    /* Kaydin tutmasi ile MEKTUBUN GITMESI ayri ayri raporlanir. Ikisini tek
       "basarili" mesajina sikistirmak, alicinin haberi olmadigi bir yaniti
       yanitlanmis gostermek olurdu -- ve karsi teklifte mektup, isin ta
       kendisi: alici kabul/red baglantisini oradan aliyor. */
    /* Gerekce OTURUMDA tasiniyor, URL'de degil: metin € ve bosluk iceriyor,
       ve operatorun gormesi gereken sey "bir hata oldu" degil HANGI kural.
       Fiyat kurallari (yarisindan az / normal fiyattan fazla / bir oncekine
       yaklasmiyor) reddi burada dogar ve buradan gorunur. */
    if (!$res['ok']) { $_SESSION['offer_err'] = (string)$res['error']; $m = 'offer_err'; }
    elseif (($res['mailed'] ?? null) === false) $m = 'offer_nomail';
    elseif (($res['mailed'] ?? null) === null)  $m = 'offer_noaddr';
    else                                   $m = 'offer_'.$oAct;
    header('Location: /admin?tab=offers&msg='.$m); exit;
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
    /* Read from the model rather than a second hand-written list. The two copies had
       already drifted once elsewhere in this project; a status the timeline knows about
       but the form silently refuses is the hardest kind of bug to see, because the page
       reloads looking perfectly normal and simply does nothing. */
    if($ref && in_array($st,vestra_order_settable_statuses(),true)){
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
      /* The two middle stages exist to be SEEN. Setting them and saying nothing would
         leave the panel better informed than the customer, which is the opposite of why
         they were added. Cancellation is told for the same reason, only more so. */
      if($st!==$prev && in_array($st,['preparing','to_vestra','cancelled'],true)){
        $oRow=null;
        foreach(vestra_read_csv('orders.csv') as $row){ if(($row['ref']??'')===$ref){ $oRow=$row; break; } }
        if($oRow && !empty($oRow['email'])){
          $who=$oRow['name']?:($oRow['company']?:'there');
          /* The BUYER's language, not the admin's. t() would resolve against whoever is
             clicking in the panel, so a French boutique would get an English note while
             their own order page reads "En cours de préparation". */
          $bLang='en';
          foreach(auth_accounts() as $bAcc){
            if(strcasecmp((string)($bAcc['email']??''), (string)$oRow['email'])===0){
              $bLang=substr((string)($bAcc['lang']??'en'),0,2); break;
            }
          }
          require_once __DIR__.'/inc/email_templates.php';
          [$subj,$body,$opts]=vestra_tpl_order_stage($bLang,$st,$who,$ref);
          vestra_send_mail($oRow['email'],$subj,$body,'','',null,'',$opts);
        }
      }
    }
    header('Location: /admin?tab=orders&msg=status_ok'); exit;
  }
  /* Delete an order outright. Cancelling is the everyday action and leaves the record
     standing; this is for test rows and duplicates that should never have existed.
     Refused while an invoice exists for the ref: an invoice carries a sequential number
     and is a document the company has already issued to a customer. Deleting its subject
     leaves a numbered invoice pointing at nothing, and a gap in a sequence is exactly
     what an auditor asks about. Cancel covers that case instead. */
  if($act==='order_delete'){
    $ref=trim((string)($_POST['ref']??''));
    if($ref===''){ header('Location: /admin?tab=orders&msg=ord_notfound'); exit; }
    require_once __DIR__.'/inc/invoice.php';
    /* An issued invoice blocks the first click and the panel says why. force=1 is the
       same click made again after reading that — at which point the invoice files are
       moved into data/invoices/deleted/ rather than left pointing at a row that no
       longer exists. Nothing is erased: the numbered document stays on disk. */
    $inv=vestra_invoices_for_ref($ref);
    if($inv && empty($_POST['force'])){
      header('Location: /admin?tab=orders&msg=ord_has_invoice&n='.count($inv).'&ref='.urlencode($ref)); exit;
    }
    if($inv) vestra_invoices_archive_for_ref($ref);
    /* The rewrite itself lives in inc/orders.php next to the other function that has
       to know orders.csv is stored oldest-first while vestra_read_csv() hands it back
       newest-first. Two copies of that knowledge is one copy too many. */
    $n=vestra_order_delete($ref);
    if($n<0){ header('Location: /admin?tab=orders&msg=ord_delfail'); exit; }
    if($n===0){ header('Location: /admin?tab=orders&msg=ord_notfound'); exit; }
    header('Location: /admin?tab=orders&msg=ord_deleted&n='.$n); exit;
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
  /* Bir musteriye ELDEN mektup. Panelde bu yoktu: kampanya sablonu disinda bir sey
     yazmak isteyen operatorun tek yolu kendi posta istemcisiydi -- o zaman da gonderim
     lead kaydina islenmiyor, "bu adama en son ne yazdik" sorusu cevapsiz kaliyordu.
     Mektup platformun kendi kimliginden (support@) Brevo uzerinden gidiyor, tipki
     kampanya gibi; fark, metni operatorun yazmasi.
     Gonderim SONRASI lead damgalaniyor: status=contacted, last_contacted_at ve
     nota bir satir. Boylece kampanya secimi bu adrese bir daha kendiliginden
     gondermiyor -- ayni kisiye iki koldan mektup gitmesin. */
  if($act==='lead_letter'){
    $lid  = $_POST['lid'] ?? '';
    $subj = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if($subj===''||$body===''){ header('Location: /admin?tab=prospects&msg=letter_empty'); exit; }
    $leads=vestra_leads(); $hit=null;
    foreach($leads as $l){ if(($l['id']??'')===$lid){ $hit=$l; break; } }
    if(!$hit || !filter_var($hit['email']??'',FILTER_VALIDATE_EMAIL)){
      header('Location: /admin?tab=prospects&msg=letter_nolead'); exit;
    }
    /* Gunluk kotadan gec: sifre sifirlama ve siparis bildirimleri icin ayrilan
       pay tek bir mektup ugruna harcanmasin. */
    [$qok,$qnote] = vestra_mail_bulk_allowed(1);
    if(!$qok){ header('Location: /admin?tab=prospects&msg=letter_quota'); exit; }
    $ok = vestra_send_mail($hit['email'], $subj, $body, '', '', null, '', []);
    if($ok){
      $stamp = date('c');
      foreach($leads as &$l){
        if(($l['id']??'')!==$lid) continue;
        $l['status'] = 'contacted';
        $l['last_contacted_at'] = $stamp;
        $note = trim((string)($l['notes'] ?? ''));
        $l['notes'] = ($note!==''?$note."\n":'').substr($stamp,0,10).' — elden mektup: '.mb_substr($subj,0,80);
        break;
      }
      unset($l);
      vestra_save_leads($leads);
    }
    header('Location: /admin?tab=prospects&msg='.($ok?'letter_sent':'letter_failed')); exit;
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
  /* Save the Google Cloud key (Places = addresses, Custom Search = the email fallback).
     Same storage as every other credential here: data/email_settings.json, chmod 600,
     web-denied, gitignored. This repository is public — a key must never reach it, and
     must never travel through a workflow input either, because those are printed in
     the Actions log. Blank means "keep what is stored", so re-saving the search-engine
     id does not wipe the key. */
  if($act==='save_google'){
    $dir=vestra_data_dir(); if(!is_dir($dir)) @mkdir($dir,0775,true);
    $cur=is_readable($dir.'/email_settings.json')?json_decode((string)file_get_contents($dir.'/email_settings.json'),true):[]; if(!is_array($cur))$cur=[];
    $k=trim($_POST['google_key']??''); if($k!=='') $cur['google_key']=$k;
    $x=trim($_POST['google_cx']??'');  if($x!=='') $cur['google_cx']=$x;
    /* An explicit tick clears them — the only way to retire a leaked key from the panel. */
    if(!empty($_POST['google_clear'])){ unset($cur['google_key'],$cur['google_cx']); }
    file_put_contents($dir.'/email_settings.json',json_encode($cur,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chmod($dir.'/email_settings.json',0600);
    header('Location: /admin?tab=prospects&msg='.(!empty($_POST['google_clear'])?'google_cleared':'google_saved')); exit;
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
  /* Auto-discover real small/medium clothing & textile retailers and add them straight to
     the customer list — fast, no per-candidate network calls. Emails are a separate step
     (find_lead_email_one above) so a slow/failing site-lookup never blocks discovery.

     Two sources, and they are complementary rather than rivals: OpenStreetMap is free and
     needs no key but its coverage of independent shops is patchy, while Google Maps has
     almost all of them with address, phone and website — at the cost of a billed API call.
     "both" runs OSM first and tops up with whatever Google finds that OSM missed, so the
     free source carries as much of the load as it can. */
  if($act==='discover_leads'){
    header('Content-Type: application/json');
    @set_time_limit(0); require_once __DIR__.'/inc/notify.php';
    require_once __DIR__.'/inc/discover_google.php';
    $country=trim($_POST['disc_country']??''); $city=trim($_POST['disc_city']??'');
    $src=strtolower(trim($_POST['disc_source']??'osm')); if(!in_array($src,['osm','google','both'],true)) $src='osm';
    if($src!=='osm' && vestra_google_key()===''){
      /* Asking Google without a key would come back "0 found" and read as "no shops
         here". Say the real reason instead of running a search that cannot work. */
      echo json_encode(['ok'=>true,'total'=>0,'added'=>0,'newIds'=>[],'osm_ok'=>true,'timed_out'=>false,
        'source'=>$src,'note'=>'Google anahtarı girilmemiş. Aşağıdaki "Google ile ara" kartından anahtarı ekleyin, ya da kaynağı OpenStreetMap olarak bırakın.']); exit;
    }

    $rows=[]; $osmOk=true; $timedOut=false; $gRows=[]; $gOk=true; $gNote='';
    if($country!==''){
      if($src==='osm'||$src==='both'){
        $rows=vestra_discover_osm($country,$city,80);
        $osmOk=vestra_osm_ok();
        $timedOut=function_exists('vestra_osm_timeout')?vestra_osm_timeout():false;
      }
      if($src==='google'||$src==='both'){
        $gRows=vestra_discover_google($country,$city,80);
        $gOk=vestra_google_ok(); $gNote=vestra_google_note();
        /* Merge on the company name so "both" does not offer the operator the same
           boutique twice under two source labels. vestra_leads_add() de-duplicates on
           its own key as well, but a doubled count in the live log is its own confusion. */
        $have=[]; foreach($rows as $r) $have[mb_strtolower((string)($r['company']??''))]=true;
        foreach($gRows as $g){ if(!isset($have[mb_strtolower((string)($g['company']??''))])) $rows[]=$g; }
      }
    }
    [$addedRows,$skipped]=$rows?vestra_leads_add($rows):[[],0];
    $newIds=array_values(array_map(fn($r)=>$r['id'],array_filter($addedRows,fn($r)=>$r['email']===''&&$r['website']!=='')));
    /* Bos sonucun SEBEBINI soyle. Ciplak "0 bulundu" en yaniltici cikti: kullanici
       "bu ulkede butik yokmus" saniyor, oysa neredeyse her zaman sorgu agir geldigi
       icin Overpass yetismiyor. Sehir verilince ayni sorgu calisiyor. */
    $note='';
    if(!$rows){
      if($src==='google'){
        $note=$gNote!==''?$gNote:'Google bu aramada sonuç döndürmedi.';
      } elseif($timedOut){
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
      if($src==='both' && $gNote!=='') $note.=' · Google: '.$gNote;
    } elseif($src!=='osm' && $gNote!==''){
      /* Google failed but OSM carried the run. The rows on screen are real, so this is
         a note rather than an error — but staying silent would let a dead key look like
         a working setup for weeks. */
      $note='Google tarafı çalışmadı: '.$gNote;
    }
    echo json_encode(['ok'=>true,'total'=>count($rows),'added'=>count($addedRows),'newIds'=>$newIds,
                      'osm_ok'=>$osmOk,'timed_out'=>$timedOut,'note'=>$note,
                      'source'=>$src,'google_found'=>count($gRows),'google_ok'=>$gOk]); exit;
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

  /* ── Hesap silme ────────────────────────────────────────────────────────
     BU BLOK KALDIRILDI (31 Agu 2026). Ayni '_action' icin YUKARIDA, satir
     ~530'da calisan bir isleyici var ve o exit ediyor -- yani buradaki kod
     hicbir zaman calismadi. Olu olmasi tek basina zararsizdi, ama iki sey
     yanlisti:
       - Icindeki "siparisi olan hesap silinmez" kontrolu
         function_exists('vestra_orders') ile korunuyordu ve o fonksiyon
         projede HIC tanimli degil; yani kosul her zaman false, kontrol her
         zaman atlaniyordu. Calissaydi bile korumuyordu.
       - Kendi 'del_ok / del_hasorders / del_notfound' mesajlarini
         uretiyordu; bu mesajlarin panelde karsiligi yok, yani gorunse
         bos bir banner cikardi.
     Tek yararli parcasi -- silinen hesabin data/deleted-accounts/ altina
     JSON yedegi -- calisan isleyiciye tasindi. */

  /* ── Notification Center: broadcast a push to all / buyers / sellers / one user ── */
  if($act==='sec_block_ip'){
    $ip=trim((string)($_POST['ip']??'')); $note=trim((string)($_POST['note']??''));
    /* Serbest metin degil: tam IP, "1.2.3." oneki ya da IPv4 CIDR. Yanlis yazilmis
       bir kural sessizce hic kimseyi engellemez ve operator korundugunu sanir. */
    $validIp   = filter_var($ip, FILTER_VALIDATE_IP) !== false;
    $validPre  = (bool)preg_match('/^(\d{1,3}\.){1,3}$/', $ip);
    $validCidr = (bool)preg_match('#^\d{1,3}(\.\d{1,3}){3}/\d{1,2}$#', $ip);
    if(!$validIp && !$validPre && !$validCidr){ header('Location: /admin?tab=security&msg=sec_badip'); exit; }
    /* Kendi IP'ni engellemek panele erisimi de kapatir — kilidin anahtari kapinin
       ic tarafinda kalir. Actions uzerinden acilabilir ama o yolu bilmek gerekir;
       burada acikca reddedip soyluyoruz. */
    if(vestra_ip_matches(vestra_client_ip(), $ip)){ header('Location: /admin?tab=security&msg=sec_self'); exit; }
    $blocks=vestra_ip_blocks();
    foreach($blocks as $b){ if(($b['ip']??'')===$ip){ header('Location: /admin?tab=security&msg=sec_dup'); exit; } }
    $blocks[]=['ip'=>$ip,'note'=>mb_substr($note,0,120),'added_at'=>date('c')];
    vestra_save_ip_blocks($blocks);
    header('Location: /admin?tab=security&msg=sec_blocked'); exit;
  }
  if($act==='sec_unblock_ip'){
    $ip=trim((string)($_POST['ip']??''));
    vestra_save_ip_blocks(array_values(array_filter(vestra_ip_blocks(), fn($b)=>($b['ip']??'')!==$ip)));
    header('Location: /admin?tab=security&msg=sec_unblocked'); exit;
  }
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
/* SIPARIS faturasi TASLAGI -- teklif tarafindaki preview_offer_invoice'in
   siparis esi (gerekce orada). Satici dilimi basina bir belge; secilecek bir
   satici olmadigi icin (dilimler siparisin satirlarindan geliyor) salt-okunur
   bir GET yeterli. Hicbir sey yazilmaz, numaralanmaz, gonderilmez -- ayni
   yuk + ayni cizim yolu, yalnizca draft=true. */
if($authed && ($_GET['pv_order']??'')!==''){
  require_once __DIR__.'/inc/invoice.php';
  $ref =preg_replace('/[^A-Za-z0-9_-]/','',(string)$_GET['pv_order']);
  $want=preg_replace('/[^A-Za-z0-9_-]/','',(string)($_GET['pv_seller']??''));
  foreach(vestra_order_invoice_payloads($ref) as $p){
    if($want!=='' && $p['seller_key']!==$want) continue;
    $bytes=vestra_render_invoice_pdf($p['meta'],$p['items'],$p['seller'],'',true);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="DRAFT-'.$ref.'-'.$p['seller_key'].'.pdf"');
    header('Cache-Control: no-store');          // alici verisi tasiyan taslak ara belleklere dusmesin
    header('Content-Length: '.strlen($bytes));
    echo $bytes; exit;
  }
  http_response_code(404); echo 'Bu siparis/satici dilimi bulunamadi.'; exit;
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
/* Satir yuksekligi bilerek dar: 20 baglik + 6 baslik, 900 px'lik bir ekranda bile
   sonuncusu (Security) kaydirmadan gorunsun diye. Daha bol bosluk birakinca System
   grubu ekranin altina dusuyor ve operator onu hic gormuyordu. */
.asidebar a{display:flex;align-items:center;gap:11px;padding:6px 11px;color:var(--mut);text-decoration:none;font-size:13px;font-weight:500;border-radius:9px;transition:.13s}
.asidebar a:hover{color:var(--ink);background:rgba(0,0,0,.045)}
.asidebar a.on{color:var(--acc);background:rgba(169,127,44,.12);font-weight:600;box-shadow:inset 0 0 0 1px rgba(169,127,44,.18)}
.asidebar a svg{flex:none;opacity:.75}
.asidebar a:hover svg,.asidebar a.on svg{opacity:1}
.asidebar .alabel{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.asidebar .sgrp{padding:11px 11px 4px;font-size:9.5px;font-weight:700;letter-spacing:.11em;color:#b3aa97;text-transform:uppercase}
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
  /* Dropship siparisleri AYRI bir dosyada duruyor (data/dropship_orders.json)
     ve toptan siparis akisina hic ugramiyor. Panelde hicbir yerde
     gorunmuyordu: ortak siparis veriyor, para tahsil ediliyor, operatorun
     ekraninda bir sey yok -- yalnizca e-posta. Odenmis ama gonderilmemis bir
     siparisin gorunmedigi yerde, gonderilmedigi de fark edilmiyor. */
  $dropOrders = array_values(dropship_all());
  usort($dropOrders, fn($a, $b) => strcmp((string)($b['created'] ?? ''), (string)($a['created'] ?? '')));
  $dropUnshipped = count(array_filter($dropOrders, fn($d) => ($d['status'] ?? '') === 'paid' && empty($d['shipped_at'])));
  $offers    = vestra_read_csv('offers.csv');
  $requests  = vestra_read_csv('requests.csv');
  $signups   = vestra_read_csv('signups.csv');
  $orderSt   = vestra_read_json('order_statuses.json');
  $offerResp = vestra_read_json('offer_responses.json');
  $promos    = promo_all();
  $vouchers  = voucher_all();

  $sellers      = array_filter($accounts,fn($a)=>($a['type']??'')==='seller');
  $buyers       = array_filter($accounts,fn($a)=>($a['type']??'')==='buyer');
  /* Satici basina ILAN sayisi. Panelde hicbir yerde yoktu: "onaylanmis 40
     satici" ile "urun koymus 6 satici" cok farkli iki sey ve ikincisi
     gorunmuyordu. Yayindaki ve onay bekleyen ayri sayiliyor -- toplam tek
     basina, kuyrukta bekleyen bir yiginin uzerini orter.
     Sayim ILANIN seller_uid'sinden; demo/platform urunlerinde bu alan bos,
     onlar hicbir saticiya yazilmaz. */
  $listingsBySeller = [];
  foreach($listings as $__p){
    $__u = trim((string)($__p['seller_uid'] ?? ''));
    if($__u === '') continue;
    if(!isset($listingsBySeller[$__u])) $listingsBySeller[$__u] = ['live'=>0,'pending'=>0];
    if(($__p['status'] ?? 'approved') === 'pending') $listingsBySeller[$__u]['pending']++;
    else $listingsBySeller[$__u]['live']++;
  }
  unset($__p,$__u);
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
  /* KABUL EDILMIS TEKLIFLER de faturasini bekliyor. Kuyruk yalnizca
     orders.csv'yi okuyordu: teklif kabul edilince fatura 'pending' donuyor
     ama HICBIR YERDE listelenmiyordu -- operator onaylayacagi belgeyi
     goremiyor, alici da faturayi bekliyordu. Reddedilmis/karsi teklifte
     olan teklif buraya girmez: ortada anlasma yok. */
  require_once __DIR__.'/inc/offers.php';
  $pendingInvoiceOffers = array_values(array_filter($offers, function($o) use ($offerResp){
      $ref = (string)($o['ref'] ?? ''); if($ref==='') return false;
      if ((($offerResp[$ref]['status'] ?? '')) !== 'accept') return false;
      return count(vestra_invoices_for_ref($ref)) === 0;
  }));
  $pendingInvoiceCount = count($pendingInvoiceOrders) + count($pendingInvoiceOffers);

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
    /* Bu satir EKSIKTI: save_billing zaten msg=billing_saved'e yonlendiriyordu
       ama haritada karsiligi yoktu, yani form kaydediyor ve ekranda HICBIR SEY
       yazmiyordu. Onaylanmayan bir kayit, kaydedilmemis kayittan ayirt edilemez. */
    'billing_saved'=>'✓ Fatura & banka bilgileri kaydedildi — sunucudan geri okunarak doğrulandı. Bu hesaptan kesilecek faturalar artık bunları taşıyor.',
    'status_ok'=>'Order status updated.','promo_ok'=>'Promo code created.','promo_del'=>'Promo code deleted.',
    /* ord_deleted / ord_has_invoice / ord_notfound / ord_delfail are NOT here on
       purpose: this map renders every entry as a green "✓" banner, and three of the
       four are refusals. A refusal painted like a success is why "it does not delete"
       and "it says it deleted" can both be true on the same click. They have their
       own blocks further down, in the colour they deserve. */
    'invoice_issued'=>'✓ Invoice issued and emailed to the buyer.','invoice_none'=>'No invoice could be issued for that order.',
    'invoice_seller_bad'=>'⚠ Seçilen satıcı hesabı bulunamadı — FATURA KESİLMEDİ. Boş bir hesaba düşüp belgeyi Acerasoft LLC adına çıkarmaktansa hiç kesmemek doğru: listeyi yenileyip tekrar seçin.',
    'invoice_redrafted'=>'✓ Fatura AYNI numarayla yeniden yazıldı, düzeltilmiş PDF alıcıya e-postayla (ekte) gönderildi. Alıcı panelindeki bağlantı artık düzeltilmiş belgeyi veriyor.',
    'invoice_paid_toggled'=>'✓ Ödeme işareti değiştirildi — alıcı panelindeki "ödenmesi gereken fatura" uyarısı buna göre güncellenir.',
    /* Bu ucu YALNIZCA mektup gercekten gittiginde basiliyor -- gitmediginde
       yukaridaki kirmizi/sari bloklar devreye giriyor. */
    'offer_counter'=>'✓ Karşı teklif kaydedildi ve alıcıya e-postayla gönderildi — mektupta kabul ve ret bağlantısı var.',
    'offer_accept' =>'✓ Teklif kabul edildi ve alıcıya bildirildi. Fatura kesilmedi: Invoice approvals sekmesinden onaylayın.',
    'offer_decline'=>'Teklif reddedildi ve alıcıya bildirildi.',
    'esc_released'=>'✓ Escrow released — funds paid out to the seller.','esc_refunded'=>'✓ Buyer refunded in full — sale cancelled.','esc_err'=>'⚠ Escrow action failed — see server log for details.',
    'promo_toggled'=>'Promo code status changed.',
    'sec_blocked'=>'✓ IP engellendi — o ağdan gelen her istek artık 403 alıyor.',
    'sec_unblocked'=>'IP engeli kaldırıldı.',
    'sec_dup'=>'Bu kural zaten listede.',
    'sec_badip'=>'⚠ Geçersiz kural — tam IP (1.2.3.4), önek (1.2.3.) ya da IPv4 CIDR (1.2.3.0/24) girin. Hiçbir şey eklenmedi.',
    'sec_self'=>'⚠ Bu kural SİZİN şu anki IP adresinizi kapsıyor — kendinizi engellemek admin paneline erişiminizi de kapatırdı. Hiçbir şey eklenmedi.',
    'voucher_ok'=>'✓ Voucher created.','voucher_del'=>'Voucher deleted.','voucher_toggled'=>'Voucher status changed.',
    'welcome_dry'=>'Preview only — nothing was created or sent. See the list below.',
    'welcome_sent'=>'✓ Welcome vouchers issued and emailed. See the result below.',
    'doc_requested'=>'Document requested.','doc_reviewed'=>'Document reviewed.',
    'verify_resent'=>'Verification email resent.','manual_verified'=>'Email verified manually.',
    'badge_granted'=>'✓ Verified Seller badge granted.','badge_revoked'=>'Badge revoked.',
    'csrf_fail'=>'⚠ Security check failed — please retry the action from this page.',
    /* Bu iki satir EKSIKTI. Form kaydediyordu ama hicbir sey yazmiyordu: kullanici
       Save'e basip Orders sekmesine donuyor ve kaydin tutup tutmadigini anlamiyordu.
       Onaylanmayan bir kayit, kaydedilmemis kayittan ayirt edilemez. */
    'platform_billing_saved'=>'✓ Platform billing saved — verified on the server. Invoices will now carry the payment box.',
    'platform_billing_failed'=>'⚠ Platform billing could NOT be written to the server — nothing was saved. Retry; if it repeats, the data directory is not writable.',
    'lead_added'=>'✓ Prospect added.','lead_dupe'=>'That email is already on the list.',
    'letter_sent'=>'✓ Mektup gönderildi ve müşteri kaydına işlendi (status=contacted).',
    'letter_failed'=>'✗ Mektup GÖNDERİLEMEDİ. Brevo reddetti ya da ulaşılamadı — hata günlüğüne bakın. Müşteri kaydına dokunulmadı.',
    'letter_empty'=>'Konu ve metin boş olamaz.',
    'letter_nolead'=>'Müşteri bulunamadı ya da geçerli e-posta adresi yok.',
    'letter_quota'=>'Günlük gönderim kotası dolu. Şifre sıfırlama ve sipariş bildirimleri için ayrılan pay korunuyor — yarın deneyin.',
    'lead_invalid'=>'Company and a valid email are required.','lead_status_ok'=>'Prospect status updated.',
    'lead_deleted'=>'Prospect deleted.','lead_tpl_ok'=>'✓ Outreach template saved.','lead_email_ok'=>'✓ Email added — prospect can now be emailed.',
    'quote_sent'=>'✓ Offer emailed to the customer.','quote_invalid'=>'Enter a valid customer email and pick at least one product.',
    'quote_failed'=>'Offer could not be sent — set up your Sending email below (SMTP) first.','quote_unsub'=>'That contact has unsubscribed — offer not sent.',
    'email_saved'=>'✓ Sending email saved. Send yourself a test to confirm it works.','test_ok'=>'✓ Test email sent — check that inbox.','test_fail'=>'Test failed — check the SMTP host/username/password (or use an API key).','test_invalid'=>'Enter a valid email address to send the test to.',
    'quote_nosender'=>'That seller has no sending email yet — set it up in "Configure sending for" above, then retry.',
    'finder_saved'=>'✓ Email-finder key saved.','finder_ok'=>'✓ Verified email found and added.','finder_none'=>'No email found for that domain — add it manually.',
    'ai_saved'=>'✓ AI personalisation key saved.',
    'google_saved'=>'✓ Google anahtarı kaydedildi — "Search with" listesinden Google Maps\'i seçebilirsiniz.',
    'google_cleared'=>'Google anahtarı silindi. Arama yeniden yalnızca OpenStreetMap ile çalışıyor.',
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
      'dropship'   => '<path d="M4 7h16v11H4z"/><path d="M4 7 12 3l8 4"/><path d="M9 18v-5h6v5"/>',
      'offers'     => '<path d="M4 5h16v11H9l-4 3.5V5z"/>',
      'requests'   => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9z"/><path d="M8.5 11h7M8.5 15h4.5"/>',
      'req_offers' => '<path d="M3 12.5h5l1.5 3h5l1.5-3h5"/><path d="M3 12.5V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6.5"/>',
      'groups'     => '<circle cx="8" cy="9" r="2.5"/><circle cx="16" cy="9" r="2.5"/><path d="M3 19a5 5 0 0 1 10 0"/><path d="M13.5 19a5 5 0 0 1 7.5-4.3"/>',
      'messages'   => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3 7 9 6 9-6"/>',
      'notify'     => '<path d="M6 9a6 6 0 0 1 12 0c0 4.5 2 5.5 2 5.5H4S6 13.5 6 9z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
      'prices'     => '<path d="M20.5 12.5 12 21l-9-9V4h8z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
      'api'        => '<path d="M8 7 3 12l5 5"/><path d="m16 7 5 5-5 5"/><path d="M13.5 5.5 10.5 18.5"/>',
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
  <?= navLink($tab,'traffic','📈','Trafik',count(vestra_visits_live()),false) ?>
  <?= navLink($tab,'approvals','⚠️','Approvals',count($pendingList),true) ?>
  <?= navLink($tab,'documents','📄','Documents',$pendingDocs,$pendingDocs>0) ?>

  <div class="sgrp">Customers &amp; outreach</div>
  <?= navLink($tab,'prospects','🎯','Customers ('.count($leads).')') ?>
  <?= navLink($tab,'messages','✉️','Messages ('.count($msgThreads).')',count($blockedMsgs),count($blockedMsgs)>0) ?>
  <?= navLink($tab,'users','👥','Users ('.count($accounts).')',count($pendingEmail),count($pendingEmail)>0) ?>
  <?= navLink($tab,'waitlist','📩','Waitlist ('.count($signups).')') ?>

  <div class="sgrp">Sales</div>
  <?= navLink($tab,'orders','📦','Orders ('.count($orders).')') ?>
  <?= navLink($tab,'dropship','📮','Dropship ('.count($dropOrders).')',$dropUnshipped,$dropUnshipped>0) ?>
  <?= navLink($tab,'invoices','🧾','Invoice approvals',$pendingInvoiceCount,$pendingInvoiceCount>0) ?>
  <?= navLink($tab,'offers','💬','Offers ('.count($offers).')') ?>
  <?= navLink($tab,'requests','📋','Requests ('.count($requests).')') ?>
  <?= navLink($tab,'req_offers','📩','Request Offers ('.count($reqOffers).')') ?>
  <?= navLink($tab,'groups','👥','Group buys ('.count($groupPools).')') ?>

  <div class="sgrp">Catalog</div>
  <?= navLink($tab,'listings','🏷️','Listings ('.count($listings).')') ?>
  <?= navLink($tab,'prices','💶','Prices & MOQ') ?>

  <div class="sgrp">Growth</div>
  <?= navLink($tab,'marketing','🎟️','Vouchers & codes ('.(count($vouchers)+count($promos)).')') ?>
  <?= navLink($tab,'journal','📰','Journal ('.count($journalAll).')') ?>
  <?= navLink($tab,'notify','🔔','Notifications') ?>

  <div class="sgrp">System</div>
  <?= navLink($tab,'api','🔌','Partner API') ?>
  <?= navLink($tab,'security','🔐','Security',count(vestra_ip_blocks()),false) ?>
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
<?php /* Fatura/banka kaydinin UC REDDI. $msgs'e konamazlar: orasi her satiri
         yesil "✓" olarak basiyor ve yesile boyanmis bir ret, bu depoda daha
         once "kaydettim ama kaydolmamis" olarak geri dondu. */ ?>
<?php elseif($msg==='billing_iban_bad'): ?>
<div class="amsg" style="background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.35);color:#c0392b">⚠ IBAN geçersiz — <b>hiçbir alan kaydedilmedi</b>. Sağlama (mod-97) ya da ülke uzunluğu tutmuyor: bir hane eksik veya yanlış. IBAN faturaya basılıp alıcı parayı oraya gönderdiği için yarısı doğru bir numara kabul edilmiyor. Kontrol edip formu yeniden gönderin — diğer alanlarda yazdıklarınız duruyor.</div>
<?php elseif($msg==='billing_failed'): ?>
<div class="amsg" style="background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.35);color:#c0392b">⚠ Kayıt sunucuya YAZILAMADI — geri okuma tutmadı, hiçbir şeye güvenmeyin. Tekrar deneyin; yine olursa <code>data/accounts.json</code> yazılabilir değil.</div>
<?php elseif($msg==='billing_none'): ?>
<div class="amsg" style="background:rgba(169,127,44,.1);border:1px solid rgba(169,127,44,.4);color:#8a6420">Form boş gönderildi — değişen bir şey yok.</div>
<?php elseif($msg==='combine_bad'): ?>
<div class="amsg" style="background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.35);color:#c0392b">⚠ Birleşik fatura kesilmedi: <?= htmlspecialchars((string)($_GET['why'] ?? 'geçersiz seçim')) ?></div>
<?php elseif($msg==='bulk_moq'): ?>
<div class="amsg ok">✓ MOQ set to 20 on <?= (int)($_GET['n']??0) ?> listing(s). Lacoste / Ralph Lauren / Amiri were left unchanged.</div>
<?php elseif($msg==='lead_bulk_deleted'): ?>
<div class="amsg ok">✓ <?= (int)($_GET['n']??0) ?> prospect(s) deleted.</div>
<?php elseif($msg==='rebrand'): ?>
<div class="amsg ok">✓ Rebranded <?= (int)($_GET['n']??0) ?> listing(s) to “Tyrex International BV” — the seller name is hidden on the public catalogue.</div>
<?php elseif($msg==='fx_refresh'): $__src=(string)($_GET['src']??''); ?>
<?php if((int)($_GET['n']??0)===1): ?>
<div class="amsg ok">✓ Exchange rates fetched — source: <b><?= $__src==='ecb'?'European Central Bank':($__src==='manual'?'your manual rates':'market feed') ?></b>. Converted prices are live.</div>
<?php else: ?>
<div class="amsg" style="background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.35);color:#c0392b">⚠ No rate source could be reached from the server (outbound HTTP is probably blocked by the host). Enter the rates by hand below — the catalogue will convert with those instead.</div>
<?php endif; ?>
<?php elseif($msg==='api_new'): ?>
<div class="amsg ok">✓ Key issued — copy it from the box below. It is shown only once.</div>
<?php elseif($msg==='api_revoked'): ?>
<div class="amsg ok">✓ Key revoked. Any request using it now gets a 401.</div>
<?php elseif($msg==='api_notfound'): ?>
<div class="amsg" style="background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.35);color:#c0392b">⚠ That key was already revoked or does not exist.</div>
<?php elseif($msg==='fx_manual'): ?>
<div class="amsg ok">✓ Manual rates saved. They are used only when no live source answers, and the catalogue then says “indicative rate” rather than naming the ECB.</div>
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
<?php elseif($msg==='ord_deleted'): ?>
<div class="amsg ok">✓ <?= (int)($_GET['n']??1) ?> order row(s) deleted<?= ((int)($_GET['n']??1))>1 ? ' — that reference was carried by more than one row' : '' ?>. A timestamped copy of orders.csv was saved first (data/orders.csv.bak-del-…).</div>
<?php elseif($msg==='ord_has_invoice'): ?>
<div class="amsg" style="background:rgba(240,192,96,.08);border:1px solid rgba(240,192,96,.35);color:#a9781a;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
  <span>⚠ Not deleted. <b><?= (int)($_GET['n']??1) ?></b> invoice(s) have been issued against this order.
  An invoice is a numbered document already sent to a customer, so deleting its subject leaves the number pointing at nothing —
  setting the status to <b>Cancelled</b> keeps the record and voids the sale, which is what the books want.
  If this is a test row, delete it anyway: the invoice files are <i>moved</i> to data/invoices/deleted/, not erased.</span>
  <?php /* The ref comes back through the URL, so strip it to the same charset the
           invoice files use rather than trusting htmlspecialchars() to be enough
           inside a JS string literal — that depends on the ENT_QUOTES default. */
        $__hr=preg_replace('/[^A-Za-z0-9_-]/','',(string)($_GET['ref']??'')); if($__hr!==''): ?>
  <form method="post" style="margin:0" onsubmit="return confirm('Delete <?= htmlspecialchars($__hr) ?> and move its invoice file(s) aside? This cannot be undone from the panel.')">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="order_delete">
    <input type="hidden" name="ref" value="<?= htmlspecialchars($__hr) ?>">
    <input type="hidden" name="force" value="1">
    <button class="abtn" type="submit" style="color:#c0392b">Delete anyway</button>
  </form>
  <?php endif; ?>
</div>
<?php /* Teklif yaniti: KAYIT ile MEKTUP ayri ayri. Basarili dallar asagidaki
         yesil haritada; bu ucu orada DEGIL cunku hepsi bir eksigi anlatiyor ve
         o harita her girdiyi yesil "✓" olarak basiyor. Karsi teklifte mektup
         isin ta kendisi: alici kabul/red baglantisini oradan aliyor, gitmediyse
         pazarlik sessizce durur. */ ?>
<?php elseif($msg==='offer_nomail'): ?>
<div class="amsg" style="background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.35);color:#c0392b">
  ⚠ Yanıt <b>kaydedildi</b> ama <b>e-posta GİTMEDİ</b> — sağlayıcı reddetti. Alıcının haberi yok ve karşı teklifte
  kabul/ret bağlantısı da o mektupta olduğu için pazarlık burada durur. Alıcıya başka bir yoldan ulaşın
  (Messages sekmesi) ya da adresi kontrol edip yeniden deneyin.
</div>
<?php elseif($msg==='offer_noaddr'): ?>
<div class="amsg" style="background:rgba(240,192,96,.08);border:1px solid rgba(240,192,96,.35);color:#a9781a">
  ⚠ Yanıt kaydedildi ama bu teklifte <b>geçerli bir e-posta adresi yok</b>, o yüzden mektup gönderilmedi.
  Alıcı hesabı varsa panelinde mesaj olarak görecek; yoksa ona ulaşmanın bir yolu yok.
</div>
<?php elseif($msg==='offer_err'): ?>
<?php $__oe = $_SESSION['offer_err'] ?? ''; unset($_SESSION['offer_err']); ?>
<div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#c0392b">
  ⚠ Teklife yanıt verilemedi — <b>hiçbir şey kaydedilmedi, alıcıya e-posta gitmedi.</b>
  <?php if ($__oe !== ''): ?><br><span style="font-size:13px"><?= htmlspecialchars($__oe) ?></span><?php endif; ?>
</div>
<?php elseif($msg==='acct_has_invoice'): ?>
<div class="amsg" style="background:rgba(240,192,96,.08);border:1px solid rgba(240,192,96,.35);color:#a9781a">
  ⚠ Silinmedi — bu hesaba bağlı <b><?= (int)($_GET['n']??1) ?></b> kesilmiş fatura var. Fatura numaralı bir belgedir ve
  müşteriye gitmiştir; konusu silinirse numara hiçbir şeye işaret etmez ve muhasebe izi kopar.
  Hesabı kapatmak için <b>Suspend</b> kullanın — kayıt durur, giriş kapanır.
</div>
<?php elseif($msg==='ord_notfound'): ?>
<div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#c0392b">⚠ Not deleted — no row in orders.csv carries that reference.</div>
<?php elseif($msg==='ord_delfail'): ?>
<div class="amsg" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:#c0392b">⚠ Not deleted — orders.csv could not be rewritten (the data folder is not writable). Nothing was changed.</div>
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
    <?= arow(['<span class="atag">'.htmlspecialchars(substr($o['ref']??'',0,12)).'</span>',htmlspecialchars($o['company']??$o['email']??''),'<b>'.eur($o['total']??0).'</b>'.((($__iv=vestra_order_invoiced_note($o['ref']??''))!=='')?'<div class="ahint" style="font-size:10.5px">'.htmlspecialchars($__iv).'</div>':''),orderBadge($st)]) ?>
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

<?php
  /* Eski kayit listesinden kalan artik istenmeyen istekler. Once SAYILIR,
     dugme ancak gercekten temizlenecek bir sey varsa cikar -- her zaman
     duran bir "temizle" dugmesi, basildiginda hicbir sey olmadigi icin
     bozuk gorunur. */
  $__stale = auth_prune_stale_doc_requests(null, false);
  if(($_GET['msg']??'')==='docs_pruned'):
?>
<div class="acard" style="margin-bottom:16px;border-color:rgba(31,157,99,.4)"><div class="acard-body">
  ✓ <?= (int)($_GET['n']??0) ?> stale request(s) removed from <?= (int)($_GET['a']??0) ?> account(s).
</div></div>
<?php endif; if($__stale['removed']>0): ?>
<div class="acard" style="margin-bottom:16px;border-color:rgba(169,127,44,.35)"><div class="acard-body"
     style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;justify-content:space-between">
  <div style="font-size:13px;color:var(--mut);max-width:700px">
    <b style="color:var(--ink)">🧹 <?= (int)$__stale['removed'] ?> outdated document request(s) on <?= (int)$__stale['accounts'] ?> account(s)</b><br>
    These were opened automatically at registration under an older list (company registration, VAT certificate,
    authorization letter). VESTRA now asks a seller for a <b>trade licence and a government ID</b> only, so these rows
    just tell the customer to chase paperwork nobody is waiting for. Nothing uploaded, approved, or requested by you
    is touched.
  </div>
  <form method="post" action="/admin" style="margin:0" onsubmit="return confirm('Remove <?= (int)$__stale['removed'] ?> outdated request(s) from <?= (int)$__stale['accounts'] ?> account(s)?\n\nUploaded, approved and manually requested documents are kept.')">
    <?= csrfField() ?><input type="hidden" name="_action" value="prune_docs">
    <button class="abtn primary" type="submit" style="white-space:nowrap">🧹 Clean up</button>
  </form>
</div></div>
<?php endif; ?>

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
  <?= arow(['#','Name','Email','Type','Products','Company','Country','VAT ID','Verification','KYB','Membership','Badge','Docs','Joined','Actions'],true) ?>
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
    <td class="ac" style="white-space:nowrap">
      <?php /* Sadece saticida anlamli: alicida "0 urun" bir eksiklik degil,
               tabloyu okurken gozu yanlis yere cekiyordu. */
      if(($a['type']??'')!=='seller'): ?>
        <span class="ahint">—</span>
      <?php else:
        $__c = $listingsBySeller[$a['id']??''] ?? ['live'=>0,'pending'=>0];
        if($__c['live']===0 && $__c['pending']===0): ?>
          <span class="ahint" title="This seller has not listed anything yet">0</span>
        <?php else: ?>
          <a href="/admin?tab=listings&amp;seller=<?= urlencode($a['id']??'') ?>" style="color:var(--acc);font-weight:600" title="Live listings"><?= (int)$__c['live'] ?></a>
          <?php if($__c['pending']>0): ?>
            <div class="ahint" style="color:#a9781a" title="Waiting for approval">+<?= (int)$__c['pending'] ?> pending</div>
          <?php endif; ?>
        <?php endif;
      endif; ?>
    </td>
    <td class="ac"><?= htmlspecialchars($a['company']??'—') ?></td>
    <td class="ac"><?= htmlspecialchars($a['country']??'—') ?>
      <?php /* Beyan edilen ulkenin ALTINDA kayit IP'sinin ulkesi: ikisi ayni soru
               degil. Form "Germany" derken IP Lagos'taysa, KYB onayindan once
               bakilacak ilk sey budur. Eski hesaplarda bu alanlar yok — bos kalir. */
        if(!empty($a['reg_cc']) || !empty($a['reg_vpn'])): ?>
      <div class="ahint" style="white-space:nowrap" title="Kayıt anındaki IP: <?= htmlspecialchars($a['reg_ip']??'?') ?>">
        IP: <?= htmlspecialchars($a['reg_cc']?:'?') ?><?= !empty($a['reg_city'])?' · '.htmlspecialchars($a['reg_city']):'' ?><?= !empty($a['reg_vpn'])?' · <b style="color:#a9781a">VPN</b>':'' ?>
        <?php $__declCc = vestra_cc_of_country((string)($a['country']??''));
              /* ≠ yalnizca IKI taraf da bilinirken: beyan edilen ulke haritada yoksa
                 sessiz kal — belirsizligi uyusmazlik gibi gostermek yanlis alarm. */
              if(!empty($a['reg_cc']) && $__declCc!=='' && strcasecmp($__declCc,(string)$a['reg_cc'])!==0): ?>
          <b style="color:#c0392b" title="Kayıt IP'sinin ülkesi formda beyan edilenle uyuşmuyor">≠</b>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </td>
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
        <?php /* Faturanin odeme kutusu bu alanlardan doluyor. Panelde HIC
                 gorunmuyorlardi: operator bir saticiya fatura kesmeden once
                 IBAN'in girili olup olmadigini ancak faturayi kesip PDF'e
                 bakarak anlayabiliyordu -- ve numara yoksa fatura odeme kutusu
                 OLMADAN cikiyor. Eksikse burada acikca yaziyor.
                 Bu ekran admin girisinin arkasinda ve sunucuda; depoya,
                 workflow girdisine ya da teshis ciktisina rakam girmiyor. */
              $__iban=trim((string)($a['bank_iban']??'')); $__acct=trim((string)($a['bank_account']??'')); ?>
        <div>
          <div class="ahint" style="text-transform:uppercase;font-size:10.5px;letter-spacing:.5px;margin-bottom:5px">🏦 Bank (faturaya basılır)</div>
          <div style="font-size:13px;line-height:1.6">
            <?php if($__iban==='' && $__acct===''): ?>
              <span style="color:var(--bad)">— hesap yok — bu satıcıdan kesilen faturada ödeme kutusu <b>çıkmaz</b></span>
            <?php else: ?>
              <?= htmlspecialchars(($a['bank_holder']??'')?:'⚠ hesap sahibi adı boş') ?>
              <?php if($__iban!==''): ?><br><span style="font-family:ui-monospace,monospace;font-size:12px;user-select:all"><?= htmlspecialchars(vestra_iban_pretty($__iban)) ?></span><?php endif; ?>
              <?php if(!empty($a['bank_bic'])): ?><br>BIC: <?= htmlspecialchars($a['bank_bic']) ?><?php endif; ?>
              <?php if($__acct!==''): ?><br>Acct: <?= htmlspecialchars($__acct) ?><?php if(!empty($a['bank_routing'])): ?> · ABA <?= htmlspecialchars($a['bank_routing']) ?><?php endif; ?><?php endif; ?>
              <?php if(!empty($a['bank_name'])): ?><br><span class="ahint"><?= htmlspecialchars($a['bank_name']) ?></span><?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php
      /* Musteri fatura bilgilerini yazmak icin. Yukarisi bunlari GOSTERIYOR ama
         duzenlenemiyordu; musteri adresini e-postayla gonderdiginde operatorun
         sisteme yazacak yeri yoktu ve fatura eksik adresle kesiliyordu.
         Vergi numarasi etiketi ulkeye gore: ABD'li bir sirkete "VAT ID" sormak
         var olmayan bir numarayi aratmaktir (bkz. vestra_tax_id_hint). */
      $_tax = function_exists('vestra_tax_id_hint') ? vestra_tax_id_hint($a['country'] ?? '') : ['label'=>'VAT / Tax ID','placeholder'=>''];
      ?>
      <details style="margin-top:14px">
        <summary style="cursor:pointer;font-size:12px;color:var(--acc)">✎ Edit billing details</summary>
        <form method="post" style="margin-top:10px;display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;max-width:1040px">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="save_billing">
          <input type="hidden" name="uid" value="<?= htmlspecialchars($a['id']??'',ENT_QUOTES) ?>">
          <label style="font-size:11px;color:var(--mut)">Company<input name="company" value="<?= htmlspecialchars($a['company']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Invoice name (blank = Company)<input name="invoice_name" value="<?= htmlspecialchars($a['invoice_name']??'') ?>" placeholder="<?= htmlspecialchars($a['company']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Contact name<input name="name" value="<?= htmlspecialchars($a['name']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Address (street, city, state, ZIP)<input name="address" value="<?= htmlspecialchars($a['address']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Country<input name="country" value="<?= htmlspecialchars($a['country']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)"><?= htmlspecialchars($_tax['label']) ?><input name="vat_id" value="<?= htmlspecialchars($a['vat_id']??'') ?>" placeholder="<?= htmlspecialchars($_tax['placeholder']) ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Registration number<input name="reg_number" value="<?= htmlspecialchars($a['reg_number']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Phone<input name="phone" value="<?= htmlspecialchars($a['phone']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">City<input name="city" value="<?= htmlspecialchars($a['city']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Postcode<input name="postcode" value="<?= htmlspecialchars($a['postcode']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Website<input name="website" value="<?= htmlspecialchars($a['website']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>

          <?php /* BANKA. Ayni formda, cunku operator bir saticinin fatura
                   kimligini duzeltirken hesabini da duzeltiyor -- ikisini iki
                   ayri kayde bolmek, birini kaydedip digerini unutturur.
                   grid-column:1/-1 ile tam genislik: baslik alanlarin arasina
                   sikismasin, hangi alanlarin banka oldugu belli olsun. */ ?>
          <div style="grid-column:1/-1;margin-top:6px;padding-top:10px;border-top:1px solid var(--line);font-size:11px;color:var(--mut);text-transform:uppercase;letter-spacing:.5px">🏦 Bank details — printed on this seller's invoices</div>
          <label style="font-size:11px;color:var(--mut)">Account holder (name beside the IBAN)<input name="bank_holder" value="<?= htmlspecialchars($a['bank_holder']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">IBAN<input name="bank_iban" value="<?= htmlspecialchars($a['bank_iban']??'') ?>" placeholder="FR76 3000 4008 2800 0123 4567 890" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px;text-transform:uppercase"></label>
          <label style="font-size:11px;color:var(--mut)">BIC / SWIFT<input name="bank_bic" value="<?= htmlspecialchars($a['bank_bic']??'') ?>" placeholder="PSSTFRPPSCE" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px;text-transform:uppercase"></label>
          <label style="font-size:11px;color:var(--mut)">Bank name<input name="bank_name" value="<?= htmlspecialchars($a['bank_name']??'') ?>" placeholder="LA BANQUE POSTALE" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Bank address<input name="bank_address" value="<?= htmlspecialchars($a['bank_address']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <?php /* ABD hesabinda IBAN YOKTUR: routing (ABA) + hesap numarasi
                   vardir. vestra_payment_rails para birimine gore hangi ikiliyi
                   basacagina karar veriyor, o yuzden ikisi de girilebilmeli.
                   bank_eur_bic ayri duruyor: ABD hesabi da tanimliysa duz
                   'bank_bic' o bankanin olabilir ve IBAN'in yanina basilmasi
                   aliciya birbirini tutmayan bir cift verirdi. */ ?>
          <label style="font-size:11px;color:var(--mut)">Routing (ABA) — US only<input name="bank_routing" value="<?= htmlspecialchars($a['bank_routing']??'') ?>" inputmode="numeric" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Account number — US only<input name="bank_account" value="<?= htmlspecialchars($a['bank_account']??'') ?>" inputmode="numeric" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>
          <label style="font-size:11px;color:var(--mut)">Account type — US only
            <select name="bank_acct_type" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px">
              <option value="">— not specified —</option>
              <option value="Checking"<?= ($a['bank_acct_type']??'')==='Checking'?' selected':'' ?>>Checking</option>
              <option value="Savings"<?= ($a['bank_acct_type']??'')==='Savings' ?' selected':'' ?>>Savings</option>
            </select></label>
          <label style="font-size:11px;color:var(--mut)">EUR BIC (only if a US account is also on file)<input name="bank_eur_bic" value="<?= htmlspecialchars($a['bank_eur_bic']??'') ?>" style="width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px;text-transform:uppercase"></label>

          <label style="grid-column:1/-1;font-size:11.5px;color:var(--bad);display:flex;align-items:center;gap:7px">
            <input type="checkbox" name="bank_replace" value="1" style="width:auto;margin:0">
            <span>Banka bilgilerini <b>DEĞİŞTİR</b> — önce bütün banka alanlarını sil, sonra yukarıda yazdıklarımı uygula. (Eski bir IBAN'ı kaldırmanın tek yolu bu; işaretlemezseniz boş bıraktığınız alanlar olduğu gibi kalır.)</span>
          </label>
          <div style="grid-column:1/-1"><button class="abtn" type="submit" style="color:var(--ok);border-color:rgba(122,214,160,.4)">Save billing &amp; bank details</button></div>
        </form>
        <div class="ahint" style="margin-top:6px;font-size:11px">Blank fields are left unchanged — this never clears data you don't retype (use the checkbox above to clear the bank block).<br>
          <b>Company</b> is the seller's public name (storefront). <b>Invoice name</b> replaces it on the invoice only.
          <b>Account holder</b> is the name printed beside the IBAN — it can differ from both, and it must match what the bank has on the account.<br>
          IBAN is checked (mod-97 + country length) before anything is saved; a bad one saves <b>nothing</b>. Fill in only the set the bank actually uses — the invoice prints just those.<br>
          The login <b>e-mail cannot be changed here</b>: it is the account's identity. Stored server-side in <code>data/accounts.json</code> — web-blocked and never in the code repository.</div>
      </details>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
</div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════ ORDERS
elseif($tab==='orders'):
  require_once __DIR__.'/inc/invoice.php';
  $PLAT = vestra_platform_seller();
  $platHasBank = ($PLAT['bank_iban'] ?? '') !== '' || ($PLAT['bank_account'] ?? '') !== '';
  $cnt_ship=count(array_filter($orders,fn($o)=>($orderSt[$o['ref']??'']['status']??'')==='shipped'));
  $cnt_done=count(array_filter($orders,fn($o)=>($orderSt[$o['ref']??'']['status']??'')==='completed'));
  /* Full order dossier (?tab=orders&view=REF): everything about one order on
     one screen — buyer, delivery, items, money, escrow, commission, invoices,
     status control — so the admin never pieces an order together from a row. */
  $viewRef=trim($_GET['view']??''); $viewRow=null;
  if($viewRef!==''){ foreach($orders as $__o){ if(($__o['ref']??'')===$viewRef){ $viewRow=$__o; break; } } }
?>
<?php /* Platform kendi adina fatura kestiginde (kurasyonlu katalog urunleri: satici
         hesabi yok) odeme kutusu buradan doluyor. Bos ise fatura banka bilgisi
         OLMADAN cikar ve alici parayi nereye gonderecegini bilemez -- o yuzden
         eksikse acik bir uyari duruyor, sessizce gecilmiyor. */ ?>
<details class="acard" style="margin-bottom:14px" <?= $platHasBank ? '' : 'open' ?>>
  <summary style="cursor:pointer;padding:12px 16px;font-size:13px">
    🏦 Platform billing &amp; bank details — <b><?= htmlspecialchars($PLAT['company'] ?? 'Acerasoft LLC') ?></b>
    <?= $platHasBank
        ? '<span class="ahint" style="margin-left:8px">on file</span>'
        : '<span style="margin-left:8px;color:var(--bad)">not set — invoices will have no payment box</span>' ?>
  </summary>
  <div style="padding:0 16px 16px">
    <div class="ahint" style="margin-bottom:10px;font-size:11.5px">
      Used when VESTRA invoices in its own name (catalogue items with no seller account).
      Stored on the server only — web-blocked, never in the code repository. Blank fields are left unchanged.
      <?php if (!$platHasBank && array_intersect(array_keys($_GET), ['bank_account','bank_routing','bank_iban'])): ?>
        <br><b style="color:var(--ok)">Pre-filled from the link — check the values, then press Save. Nothing is stored until you do.</b>
      <?php endif; ?>
    </div>
    <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
      <?= csrfField() ?>
      <input type="hidden" name="_action" value="save_platform_billing">
      <?php
      /* Alan BOSSA adres cubugundan on-doldurulabilir (?bank_routing=... gibi).
         Sebep: bu rakamlari operatore elle yazdirmak yerine hazir bir baglantiyla
         getirmek. Ayni rakamlar bir is akisi girdisiyle gonderilemiyor -- depo
         herkese acik, Actions log'u ve calistirma kaydi da oyle. Adres cubugu
         farkli: baglanti operatorle aramizda kaliyor ve degerler yalnizca KENDI
         sunucusunun erisim kutugune ve kendi tarayici gecmisine dusuyor.

         DOLU bir alan GET ile EZILMEZ. Boylece hazir baglantiyi ikinci kez acmak
         kayitli bir hesabi sessizce degistiremez; degistirmek isteyen alani elle
         siler. On-doldurma yalnizca oneri, kayit degil -- Save'e basilana kadar
         sunucuda hicbir sey degismiyor. */
      $pf = function(string $name, string $label, string $ph='') use ($PLAT) {
        $val = (string)($PLAT[$name] ?? '');
        if ($val === '') $val = trim((string)($_GET[$name] ?? ''));
        printf('<label style="font-size:11px;color:var(--mut)">%s<input name="%s" value="%s" placeholder="%s" style="width:100%%;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12.5px"></label>',
          htmlspecialchars($label), htmlspecialchars($name),
          htmlspecialchars($val), htmlspecialchars($ph));
      };
      $pf('company','Legal company name');
      $pf('address','Company address');
      $pf('country','Country','US');
      $pf('bank_holder','Account holder');
      $pf('bank_name','Bank name');
      $pf('bank_address','Bank address','4501 23rd Avenue S, Fargo, ND 58104, USA');
      $pf('bank_routing','Routing number (ABA) — US','091311229');
      $pf('bank_account','Account number — US');
      $pf('bank_acct_type','Account type','Checking');
      $pf('bank_iban','IBAN — EU (leave blank if none)');
      $pf('bank_bic','BIC / SWIFT');
      $pf('vat_id','Tax ID / EIN');
      ?>
      <div style="align-self:end"><button class="abtn" type="submit" style="color:var(--ok);border-color:rgba(122,214,160,.4)">Save platform billing</button></div>
    </form>
  </div>
</details>
<?php
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
      <?= arow(['<b>Buyer pays</b>','<b>'.eur($viewRow['total']??0).'</b>'.((($__iv=vestra_order_invoiced_note($viewRef))!=='')?'  <span class="ahint">'.htmlspecialchars($__iv).'</span>':'')]) ?>
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
        <a href="<?= htmlspecialchars($iv['url']) ?>" target="_blank" rel="noopener" style="color:var(--acc);display:inline-block;margin-right:12px;font-size:12.5px">📄 <?= htmlspecialchars(vestra_invoice_link_label($iv)) ?> · <?= htmlspecialchars($iv['seller_label']) ?></a>
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
        <?= vestra_order_status_options((string)$vstatus) ?>
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
    <td class="ac"><b><?= eur($o['total']??0) ?></b><?php if(($__iv=vestra_order_invoiced_note($o['ref']??''))!==''): ?><div class="ahint" style="font-size:10.5px"><?= htmlspecialchars($__iv) ?></div><?php endif; ?></td>
    <td class="ac"><?= orderBadge($st) ?></td>
    <td class="ac" style="font-size:11px"><?= htmlspecialchars($trk) ?></td>
    <td class="ac" style="font-size:11px"><?php foreach(vestra_invoices_for_ref($ref) as $iv): ?>
      <a href="<?= htmlspecialchars($iv['url']) ?>" target="_blank" rel="noopener" style="color:var(--acc);display:block"><?= htmlspecialchars(vestra_invoice_link_label($iv)) ?></a>
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
    <td class="ac">
      <?php $_sel='padding:3px 6px;border:1px solid var(--line);border-radius:5px;background:var(--bg);color:var(--ink);font-size:11px'; ?>
      <?php if($st!=='completed'): ?>
      <form method="post" style="display:flex;flex-direction:column;gap:5px">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="order_status">
        <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">
        <select name="status" style="<?= $_sel ?>">
          <?= vestra_order_status_options((string)$st) ?>
        </select>
        <input name="tracking" value="<?= htmlspecialchars($trk) ?>" placeholder="Tracking no." style="<?= $_sel ?>">
        <button class="abtn primary" type="submit" style="font-size:11px;padding:3px 8px">Save</button>
      </form>
      <?php endif; ?>
      <?php /* Delete sits OUTSIDE the not-completed guard on purpose: a finished test row
               is exactly the kind of record that needs removing, and hiding the button
               there would leave no way to do it. The handler still refuses any ref that
               carries an invoice. */ ?>
      <form method="post" style="margin:6px 0 0"
            onsubmit="return confirm('Delete order <?= htmlspecialchars($ref) ?> for good? This cannot be undone. To keep the record but void the sale, set the status to Cancelled instead.')">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="order_delete">
        <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">
        <button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#c0392b">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div></div>
<?php endif; ?>
<?php endif; // order dossier vs list ?>

<?php // ===================================================== INVOICE APPROVALS
elseif($tab==='invoices'): ?>

<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd"><h3>🧾 Invoice approvals</h3></div>
  <p class="ahint" style="margin:0">Automatic invoicing is <b>suspended</b>. After you confirm stock for an order, approve it here — the PDF invoice is then issued, emailed to the buyer and added to their account (My orders / confirmation page). Card &amp; escrow orders invoice themselves on payment and are not listed.<br>
  <b>👁 Draft</b> = kesilecek belgenin birebir ön izlemesi, <b>yalnızca size</b> açılır: numara yakmaz, hiçbir şey kaydetmez, müşteriye e-posta gitmez ve hesabında görünmez. Onaylamadan önce buradan kontrol edin — Approve'a basana kadar müşteri tarafında hiçbir şey oluşmaz.</p>
</div>

<?php if($pendingInvoiceOffers):
  require_once __DIR__.'/inc/invoice.php';
  /* FATURAYI KESECEK SATICI operatorun secimi. Liste her satirda ayni, bir kez
     kuruluyor. Ad icin vestra_invoice_issuer_name(): belgenin ustunde ne
     yazacaksa secenekte de o yazmali -- 'invoice_name' verilmis bir hesabi
     kayit adiyla listelemek, operatore secmedigi bir ad gosterirdi. */
  $invSellers = ['vestra' => 'VESTRA / '.vestra_invoice_issuer_name(vestra_platform_seller(),'Acerasoft LLC').' (platform)'];
  foreach($accounts as $__a){
    if(($__a['type']??'')!=='seller' || ($__a['id']??'')==='') continue;
    $invSellers[(string)$__a['id']] = vestra_invoice_issuer_name($__a);
  }
  asort($invSellers, SORT_NATURAL|SORT_FLAG_CASE);
?>
<div class="acard" style="margin-bottom:16px;border-color:rgba(169,127,44,.4)">
  <div class="acard-hd"><h3>↩ <?= count($pendingInvoiceOffers) ?> accepted offer(s) awaiting an invoice</h3></div>
  <p class="ahint" style="margin:0 0 10px">Faturayı <b>hangi satıcının keseceğini</b> siz seçersiniz. Varsayılan, ilanın bağlı olduğu satıcı — satıcısı olmayan ilanlarda platform. Seçim belgeyle birlikte kayda geçer ve <b>kesimden sonra değiştirilemez</b>: numara ve dosya o satıcıya yazılır.<br>
  <b>VAT satırı</b> kutusuna tıklayınca hazır gerekçeler açılır (KDV'siz kesilen faturada neden yazmak zorunlu) — seçebilir ya da kendiniz yazabilirsiniz; boş bırakılırsa satır hiç basılmaz.</p>
  <?php /* KDV'SIZ KESIMIN HAZIR GEREKCELERI (operator istegi, 1 Eyl 2026).
           datalist: secim de serbest metin de mumkun, JS yok. Metinler
           faturaya oldugu gibi basilir; PDF CP1252'ye cevirdigi icin
           aksan ve uzun tire sorunsuz. Kapsam bu pazaryerinin GERCEK
           durumlari: FR kucuk isletme (Agaya/franchise en base), FR ve
           genel AB ici B2B teslim, AB disi ihracat, ikinci el marj rejimi
           (vintage/thrift saticilar). Hicbiri otomatik SECILMEZ: hangi
           rejimin uygulanacagi operatorun/muhasebecinin karari. */ ?>
  <datalist id="vatnotes">
    <option value="TVA non applicable — article 293 B du CGI" label="FR küçük işletme (franchise en base) — alıcı VAT'i gerekmez"></option>
    <option value="Exonération de TVA — article 262 ter I du CGI (livraison intracommunautaire)" label="FR satıcı → AB içi B2B; alıcının VIES'te geçerli VAT'i ŞART"></option>
    <option value="VAT 0% — intra-Community supply of goods, Art. 138 of Directive 2006/112/EC. VAT to be accounted for by the customer (reverse charge)" label="Genel AB (örn. NL satıcı) → AB içi B2B; geçerli VAT ŞART"></option>
    <option value="VAT 0% — export of goods outside the EU, Art. 146 of Directive 2006/112/EC" label="AB dışına ihracat (gümrük çıkış belgesi saklanır)"></option>
    <option value="Margin scheme — second-hand goods, Art. 313 of Directive 2006/112/EC. VAT not separately shown or deductible" label="İkinci el / vintage marj rejimi"></option>
  </datalist>
  <div class="atscroll"><table class="atable">
    <?= arow(['☑','Offer','Product','Buyer','Qty','Agreed €/u','Total','Invoice issued by','Approve'],true) ?>
    <?php foreach($pendingInvoiceOffers as $o):
      $fref=(string)($o['ref']??''); $fq=(int)($o['qty']??0);
      /* Anlasilan fiyat TEK yerden (vestra_offer_agreed_unit): karsi teklif
         verilmisse o, yoksa alicinin ilk teklifi. Burada ayri hesaplamak,
         onay ekraninda bir rakam gosterip faturaya baskasini yazma riski. */
      $fu=vestra_offer_agreed_unit($fref); $fl=vestra_listing_by_sku($o['sku']??'');
      $fWho=(($offerResp[$fref]['accepted_by']??'')==='buyer')?'accepted by buyer':'accepted by you';
      /* Secili satici: kayitli karar varsa o, yoksa ilanin saticisi, yoksa
         platform. Ayni cozucu faturayi kesen kod yolunda da calisiyor
         (vestra_offer_invoice_seller) -- ekranda gorunen ile belgeye basilan
         ayni fonksiyondan cikmali, yoksa operator bir sey secip baskasini alir. */
      $fSelAcc = vestra_offer_invoice_seller($fref, $fl);
      $fSel    = (string)($fSelAcc['id'] ?? '') !== '' ? (string)$fSelAcc['id'] : 'vestra';
      $fPicked = trim((string)($offerResp[$fref]['invoice_seller_uid'] ?? '')) !== '';
      $fSelNm  = $invSellers[$fSel] ?? vestra_invoice_issuer_name($fSelAcc,'Acerasoft LLC');
    ?>
    <tr>
      <?php /* Birlestirme secimi: kutu ALT taraftaki fcomb formuna bagli
               (form= niteligi -- satirdaki kesim formundan ayri form). */ ?>
      <td class="ac"><input type="checkbox" name="refs[]" value="<?= htmlspecialchars($fref) ?>" form="fcomb" style="width:auto"></td>
      <td><a class="acc" href="/admin?tab=offers"><?= htmlspecialchars($fref) ?></a>
        <div class="ahint"><?= htmlspecialchars($fWho) ?></div></td>
      <td><?php if($fl && !empty($fl['id'])): ?>
            <a href="/product?id=<?= urlencode((string)$fl['id']) ?>" target="_blank" rel="noopener" style="color:var(--acc)"><?= htmlspecialchars($o['product']??'') ?> ↗</a>
          <?php else: ?><?= htmlspecialchars($o['product']??'') ?><?php endif; ?>
        <div class="ahint"><?= htmlspecialchars($o['sku']??'') ?></div></td>
      <td><?= htmlspecialchars($o['company']??'') ?><div class="ahint"><?= htmlspecialchars($o['email']??'') ?></div></td>
      <td><?= $fq ?></td>
      <td><b><?= eur($fu) ?></b><?php if(abs($fu-(float)($o['offer_unit']??0))>0.001): ?><div class="ahint" style="text-decoration:line-through"><?= eur($o['offer_unit']??0) ?></div><?php endif; ?></td>
      <td><b><?= eur($fu*$fq) ?></b></td>
      <?php /* Secim ve dugme AYNI forma bagli olmak ZORUNDA: ayri formlarda
               operator listeden bir satici secer, Approve'a basar, secim hic
               gonderilmez ve fatura eski saticidan cikar. Ama <form> dogrudan
               <tr> altina konamaz -- HTML cozumleyici tablo icindeki form'u
               aninda kapatir, icine yazilan gizli alanlar tablonun DISINA
               dusurulur ve POST bos gider. Cozum, alanlari kendi hucrelerinde
               birakip form="..." niteligiyle baglamak; form.elements bu yolla
               baglanan alanlari da tasir, onay metnindeki this.seller_uid
               dahil. */
         $fFid='finv-'.preg_replace('/[^A-Za-z0-9_-]/','',$fref); ?>
      <td>
        <select name="seller_uid" form="<?= htmlspecialchars($fFid) ?>" style="font-size:12px;max-width:200px">
          <?php foreach($invSellers as $__uid=>$__nm): ?>
            <option value="<?= htmlspecialchars((string)$__uid) ?>"<?= (string)$__uid===$fSel?' selected':'' ?>><?= htmlspecialchars($__nm) ?></option>
          <?php endforeach; ?>
          <?php if(!isset($invSellers[$fSel])): ?>
            <option value="<?= htmlspecialchars($fSel) ?>" selected><?= htmlspecialchars($fSelNm) ?></option>
          <?php endif; ?>
        </select>
        <div class="ahint" style="font-size:10.5px"><?= $fPicked ? 'sizin seçiminiz' : (($fl && ($fl['seller_uid']??'')!=='') ? 'ilandan geldi' : 'ilanda satıcı yok → platform') ?></div>
        <?php /* KDV satiri: KDV'siz kesilen faturada NEDEN'i belgenin uzeri
                 soylemeli. Kucuk satici (franchise en base) icin "TVA non
                 applicable" ibaresi, VIES'li aliciya reverse charge ibaresi
                 buradan girilir. Bos = satir hic basilmaz. */ ?>
        <input name="vat_note" form="<?= htmlspecialchars($fFid) ?>" maxlength="200" list="vatnotes"
               value="<?= htmlspecialchars((string)($offerResp[$fref]['invoice_vat_note'] ?? '')) ?>"
               placeholder='VAT satırı — örn. "TVA non applicable — article 293 B du CGI"'
               title="Faturadaki VAT satırı. KDV'siz kesiyorsanız gerekçesi burada yazmalı; boş bırakılırsa satır hiç basılmaz."
               style="margin-top:4px;width:100%;max-width:200px;padding:4px 6px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:11px">
        <input name="shipping" form="<?= htmlspecialchars($fFid) ?>" inputmode="decimal"
               value="<?= ($__fs=(float)($offerResp[$fref]['invoice_shipping'] ?? 0))>0?htmlspecialchars(number_format($__fs,2,'.','')):'' ?>"
               placeholder="Kargo € (boş = yok)"
               title="Faturaya eklenecek kargo tutarı (EUR). Belgede Goods total + Shipping + Grand total olarak ayrışır; boşsa satır hiç basılmaz."
               style="margin-top:4px;width:100%;max-width:200px;padding:4px 6px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:11px">
      </td>
      <td>
        <?php /* _action GIZLI ALANDA DEGIL, dugmelerin uzerinde: iki dugme ayni
                 formu iki ayri isleme gonderiyor (taslak / kesim) ve etkin olan
                 deger her zaman basilan dugmeninki. JS ile _action degistirmek
                 ise JS kapaliyken iki dugmeyi de KESIME gonderirdi.
                 Onay sorusu yalnizca kesim dugmesinde -- taslak zararsiz, soru
                 sorulsaydi operator iki soruyu ayirt etmeden Evet'e aliskanlik
                 kazanirdi. Enter da ilk dugmeye, yani TASLAGA gider.
                 formtarget=_blank: PDF yeni sekmede, kuyruk sayfasi durur. */ ?>
        <form id="<?= htmlspecialchars($fFid) ?>" method="post" style="margin:0;display:flex;gap:6px;flex-wrap:wrap">
          <?= csrfField() ?>
          <input type="hidden" name="ref" value="<?= htmlspecialchars($fref) ?>">
          <button class="abtn" type="submit" name="_action" value="preview_offer_invoice" formtarget="_blank" style="font-size:12px"
                  title="Kesilecek belgenin birebir taslağı — numara yakmaz, kaydetmez, müşteriye hiçbir şey gitmez">👁 Draft</button>
          <button class="abtn primary" type="submit" name="_action" value="issue_offer_invoice" style="font-size:12px"
                  onclick="var s=this.form.elements.seller_uid;return confirm('Issue the invoice for offer <?= htmlspecialchars($fref) ?> at <?= htmlspecialchars(eur($fu)) ?>/unit (total <?= htmlspecialchars(eur($fu*$fq)) ?>)?\n\nIssuer: '+s.options[s.selectedIndex].text+'\n\nThis burns the number, stores the PDF and EMAILS THE BUYER. Check the draft (👁) first.\nThe seller cannot be changed afterwards.')">✓ Approve &amp; issue</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php /* BIRLESTIRME CUBUGU: isaretlenen teklifler TEK saticidan TEK
           faturada. Ayni alici sarti sunucuda dogrulanir; taslak dugmesi
           kesimle ayni yuku cizer. Satici listesi satirlardakiyle ayni. */ ?>
  <form id="fcomb" method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding:10px 12px;border-top:1px solid var(--line)">
    <?= csrfField() ?>
    <span style="font-size:12px;color:var(--mut)">Seçili teklifleri <b>tek faturada</b> birleştir →</span>
    <select name="seller_uid" style="font-size:12px;max-width:220px">
      <option value="">— satıcı seç (zorunlu, ilanlar farklıysa) —</option>
      <?php foreach($invSellers as $__uid=>$__nm): ?>
        <option value="<?= htmlspecialchars((string)$__uid) ?>"><?= htmlspecialchars($__nm) ?></option>
      <?php endforeach; ?>
    </select>
    <input name="shipping" inputmode="decimal" placeholder="Kargo €"
           title="Birleşik faturaya eklenecek kargo tutarı (EUR); boş = yok"
           style="font-size:11px;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);width:80px">
    <input name="vat_note" maxlength="200" list="vatnotes" placeholder='VAT satırı (örn. "TVA non applicable — article 293 B du CGI")'
           style="font-size:11px;padding:5px 7px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);min-width:260px">
    <button class="abtn" type="submit" name="_action" value="combine_preview_offer_invoice" formtarget="_blank" style="font-size:12px"
            title="Birleşik belgenin birebir taslağı — numara yakmaz, kaydetmez, müşteriye hiçbir şey gitmez">👁 Draft</button>
    <button class="abtn primary" type="submit" name="_action" value="combine_issue_offer_invoice" style="font-size:12px"
            onclick="var n=0;for(var i=0;i<this.form.elements.length;i++){var e=this.form.elements[i];if(e.name==='refs[]'&&e.checked)n++;}if(!n){alert('Önce yukarıdan teklif işaretleyin.');return false;}var s=this.form.elements.seller_uid;return confirm('Issue ONE combined invoice for '+n+' selected offer(s)?\n\nIssuer: '+(s.value?s.options[s.selectedIndex].text:'(otomatik: ilanların ortak satıcısı)')+'\n\nThis burns ONE number, stores ONE PDF and EMAILS THE BUYER once. Check the draft (👁) first. The offers are then bound to that single invoice.')">✓ Approve &amp; issue ONE invoice</button>
  </form>
</div>
<?php endif; ?>

<?php
/* ── KESILMIS TEKLIF FATURALARI: duzelt (redraft) + odendi isareti ──
   Onay kuyrugu kesilenleri dusurur; oysa operator kesilmis belgeyi de
   yonetmek istiyor: kargo ekleyip AYNI numarayla yeniden yazmak, alicinin
   "odenmesi gereken fatura" uyarisini odeme gelince kapatmak. Liste yalnizca
   BIRINCIL ref'ler: uyeler (invoice_group_ref) ayni belgeyi gosterir, iki
   satir ayni faturayi iki kez yonetmek olurdu. */
$issuedOfferInvs=[];
foreach($offers as $__o){
  $__r=(string)($__o['ref']??''); if($__r==='') continue;
  if(trim((string)($offerResp[$__r]['invoice_group_ref'] ?? ''))!=='') continue;   // uye satiri degil
  $__ivs=vestra_invoices_for_ref($__r,false);
  if($__ivs) $issuedOfferInvs[]=['ref'=>$__r,'row'=>$__o,'iv'=>$__ivs[0]];
}
?>
<?php if($issuedOfferInvs): ?>
<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd"><h3>🧾 <?= count($issuedOfferInvs) ?> issued offer invoice(s)</h3></div>
  <p class="ahint" style="margin:0 0 10px">Kesilmiş belgeyi düzeltmek için: kargo tutarını yazın → <b>👁 Draft</b> ile kontrol edin → <b>🔁 Redraft &amp; email</b>. Belge <b>aynı numarayla</b> yerinde yeniden yazılır, düzeltilmiş PDF alıcıya "your invoice is ready" e-postasıyla <b>ekte</b> gider ve panelindeki bağlantı yeni hâli verir. Ödeme gelince <b>✓ Paid</b> ile işaretleyin — alıcıdaki "payment due" uyarısını o kapatır.</p>
  <div class="atscroll"><table class="atable">
    <?= arow(['Offer','Invoice','Buyer','Total','Shipping €','Fix','Paid'],true) ?>
    <?php foreach($issuedOfferInvs as $__e):
      $rref=$__e['ref']; $riv=$__e['iv'];
      $rPaid=!empty($offerResp[$rref]['invoice_paid_at']);
      $rShip=(float)($offerResp[$rref]['invoice_shipping'] ?? 0);
      $rMembers=(array)($offerResp[$rref]['invoice_members'] ?? []);
      $rFid='frdr-'.preg_replace('/[^A-Za-z0-9_-]/','',$rref);
    ?>
    <tr>
      <td><a class="acc" href="/admin?tab=offers"><?= htmlspecialchars($rref) ?></a>
        <?php if(count($rMembers)>1): ?><div class="ahint"><?= count($rMembers) ?> teklif tek belgede</div><?php endif; ?></td>
      <td><b><?= htmlspecialchars((string)($riv['no']??'')) ?></b>
        <div class="ahint"><?= htmlspecialchars((string)($riv['seller_label']??'')) ?></div></td>
      <td><?= htmlspecialchars((string)($__e['row']['company']??'')) ?></td>
      <td><b><?= eur($riv['total']??0) ?></b></td>
      <td><input name="shipping" form="<?= htmlspecialchars($rFid) ?>" value="<?= $rShip>0?htmlspecialchars(number_format($rShip,2,'.','')):'' ?>" placeholder="0.00" inputmode="decimal" style="width:80px;font-size:12px;padding:4px 6px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink)"></td>
      <td>
        <form id="<?= htmlspecialchars($rFid) ?>" method="post" style="margin:0;display:flex;gap:6px">
          <?= csrfField() ?>
          <input type="hidden" name="ref" value="<?= htmlspecialchars($rref) ?>">
          <button class="abtn" type="submit" name="_action" value="preview_redraft_offer_invoice" formtarget="_blank" style="font-size:12px"
                  title="Düzeltilmiş belgenin taslağı — hiçbir şey yazmaz, göndermez">👁 Draft</button>
          <button class="abtn primary" type="submit" name="_action" value="redraft_offer_invoice" style="font-size:12px"
                  onclick="return confirm('Rewrite invoice <?= htmlspecialchars((string)($riv['no']??'')) ?> IN PLACE (same number) with the shipping shown, and EMAIL THE BUYER the corrected PDF as attachment?\n\nThe earlier copy of this number is replaced. Check the draft (👁) first.')">🔁 Redraft &amp; email</button>
        </form>
      </td>
      <td>
        <form method="post" style="margin:0">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="offer_invoice_paid_toggle">
          <input type="hidden" name="ref" value="<?= htmlspecialchars($rref) ?>">
          <button class="abtn" type="submit" style="font-size:12px;<?= $rPaid?'color:var(--ok);border-color:rgba(122,214,160,.4)':'' ?>"><?= $rPaid?'✓ Paid':'⌛ Unpaid' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<?php if(!$pendingInvoiceOrders): ?>
  <?php if(!$pendingInvoiceOffers): ?>
  <div class="acard"><div style="padding:26px;text-align:center;color:var(--mut)">✓ Nothing is awaiting an invoice.</div></div>
  <?php endif; ?>
<?php else: ?>
<div class="acard">
  <div class="acard-hd"><h3><?= count($pendingInvoiceOrders) ?> order(s) awaiting your approval</h3></div>
  <div class="atscroll"><table class="atable">
    <?= arow(['Order','Buyer','Placed','Buyer pays','Approve'],true) ?>
    <?php foreach($pendingInvoiceOrders as $o): $oref=(string)($o['ref']??''); ?>
    <tr>
      <td><a class="acc" href="/admin?tab=orders&view=<?= urlencode($oref) ?>"><?= htmlspecialchars($oref) ?></a></td>
      <td><?= htmlspecialchars($o['company']??'') ?><div class="ahint"><?= htmlspecialchars($o['name']??'') ?> · <?= htmlspecialchars($o['email']??'') ?></div></td>
      <td style="font-size:12px;white-space:nowrap"><?= htmlspecialchars(substr($o['timestamp']??'',0,16)) ?></td>
      <td><b><?= eur($o['total']??0) ?></b><?php if(($__iv=vestra_order_invoiced_note($o['ref']??''))!==''): ?><div class="ahint" style="font-size:10.5px"><?= htmlspecialchars($__iv) ?></div><?php endif; ?></td>
      <td>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <?php /* Dilim basina bir taslak: siparis birden cok saticiya
                   bolunebiliyor ve her dilim AYRI bir belge olarak kesiliyor --
                   operator hangisini kontrol ettigini bilmeli. */
                foreach(vestra_order_invoice_payloads($oref) as $__p): ?>
            <a class="abtn" style="font-size:12px" target="_blank" rel="noopener"
               title="Taslak — numara yakmaz, kaydetmez, müşteriye hiçbir şey gitmez"
               href="/admin?pv_order=<?= urlencode($oref) ?>&pv_seller=<?= urlencode($__p['seller_key']) ?>">👁 <?= htmlspecialchars(vestra_invoice_issuer_name($__p['seller'],'VESTRA')) ?></a>
          <?php endforeach; ?>
          <form method="post" style="margin:0" onsubmit="return confirm('Issue the invoice for order <?= htmlspecialchars($oref) ?>? This burns the number(s), stores the PDF(s) and EMAILS THE BUYER. Check the draft (👁) first. Do this once stock is confirmed.')">
            <?= csrfField() ?>
            <input type="hidden" name="_action" value="issue_invoice">
            <input type="hidden" name="ref" value="<?= htmlspecialchars($oref) ?>">
            <button class="abtn primary" type="submit" style="font-size:12px">✓ Approve &amp; issue</button>
          </form>
        </div>
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
    /* Teklife bakan operatorun ilk sordugu sey "hangi urun, ne fiyata
       duruyor" -- tabloda yalnizca AD ve SKU vardi, urune ulasmak icin
       Listings sekmesinde elle aramak gerekiyordu. SKU'dan cozulen ilan
       icin canli urun sayfasi ve duzenleme baglantisi. Cozulemeyen SKU
       duz metin kalir: olu link, linksiz metinden kotu -- ustelik
       "bu SKU artik katalogda yok" bilgisinin kendisi de degerli. */
    $oL = vestra_listing_by_sku($o['sku'] ?? '');
    $oName = htmlspecialchars($o['product'] ?? '—');
    if ($oL && !empty($oL['id'])) {
      $oid = urlencode((string)$oL['id']);
      $oName = '<a href="/product?id='.$oid.'" target="_blank" rel="noopener" style="color:var(--acc)">'.$oName.' ↗</a>'
             . '<div class="ahint"><a href="/admin?tab=listings&edit='.$oid.'#top" style="color:var(--mut)">Edit listing</a>'
             . ' · from '.eur(vestra_from_price($oL)).'</div>';
    } else {
      $oName .= '<div class="ahint" style="color:#a9781a">SKU not in catalogue</div>';
    }
    /* Bu sutun daha once YOKTU: tablo salt-okunurdu ve bir teklifi kabul etmenin
       hicbir yolu yoktu. Satici ucu de katalog urunlerinde calismiyor (seller_uid
       bos). Operator icin yanit formu burada; ayni kodu calistiriyor. */
    /* Eskiden yalnizca 'pending' yanitlanabiliyordu: bir kez karsi teklif
       verilince satir salt-okunur oluyordu ve pazarlik tek turda bitiyordu.
       Artik sira SENDEYSE (henuz yanit yok ya da son karsi teklifi ALICI
       verdi) yine yanit verilebilir; karsi teklif dugmesi ise tur hakki
       kalmissa cikar. */
    $oTurn      = vestra_offer_turn($resp);
    $oLeft      = vestra_offer_counters_left($resp);
    $canRespond = ($oTurn === 'seller');
    $respondCell = $canRespond ? (
      '<form method="post" style="display:flex;gap:5px;align-items:center;flex-wrap:wrap">'
      .csrfField()
      .'<input type="hidden" name="_action" value="offer_respond">'
      .'<input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">'
      .'<button class="abtn" name="response" value="accept" type="submit" style="color:var(--ok);border-color:rgba(122,214,160,.4)" '
      .'onclick="return confirm(\'Accept offer '.htmlspecialchars($ref, ENT_QUOTES).'? The buyer is emailed immediately.\')">✓ Accept</button>'
      .'<button class="abtn" name="response" value="decline" type="submit" style="color:var(--bad);border-color:rgba(239,154,154,.3)" '
      .'onclick="return confirm(\'Decline offer '.htmlspecialchars($ref, ENT_QUOTES).'?\')">✗</button>'
      /* Tur hakki bittiginde karsi teklif alani HIC cikmaz: gosterilip
         reddedilen bir alan, olmayan bir alandan kotudur -- operator
         fiyati yazar, basar, ve neden olmadigini aramak zorunda kalir. */
      .($oLeft > 0
        ? '<input name="counter_price" placeholder="€/u" inputmode="decimal" style="width:62px;padding:4px 6px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font-size:12px">'
          .'<button class="abtn" name="response" value="counter" type="submit" style="color:#9a7320;border-color:rgba(154,115,32,.4)" title="Counter offer — '.$oLeft.' of '.VESTRA_OFFER_MAX_COUNTERS.' left">↩ '.$oLeft.'</button>'
        : '<span class="ahint" title="Counter limit reached">↩ '.VESTRA_OFFER_MAX_COUNTERS.'/'.VESTRA_OFFER_MAX_COUNTERS.'</span>')
      .'</form>'
    ) : '<span class="ahint">'.htmlspecialchars(substr((string)($resp['responded_at']??''),0,10))
        .($oTurn === 'buyer' ? '<div class="ahint" style="color:#9a7320">waiting on buyer</div>' : '').'</span>';
    /* Kabul edilmis teklifin faturasi BURADAN da acilabilsin. Onceden fatura
       yalnizca Orders sekmesindeydi; teklif uzerinden gelen bir satista operator
       "kabul ettim, belge nerede" sorusuna sekme degistirerek cevap ariyordu.
       Numara + tutar birlikte (vestra_invoice_link_label) -- belge acilmadan da
       ne kesildigi gorunur. */
    if ($rSt === 'accept') {
      foreach (vestra_invoices_for_ref($ref) as $iv) {
        $respondCell .= '<br><a href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener" '
          .'style="color:var(--acc);font-size:11.5px">📄 '.htmlspecialchars(vestra_invoice_link_label($iv)).'</a>';
      }
    }
  ?>
  <?= arow([
    '<span class="atag">'.htmlspecialchars(substr($ref,0,10)).'</span>',
    htmlspecialchars(substr($o['timestamp']??'',0,10)),
    $oName,
    htmlspecialchars($o['sku']??''),
    htmlspecialchars($o['qty']??''),
    eur($o['offer_unit']??0),
    '<b>'.eur($o['offer_total']??0).'</b>',
    '<a href="mailto:'.htmlspecialchars($o['email']??'').'" style="color:var(--acc);font-size:11px">'.htmlspecialchars($o['email']??'').'</a>',
    /* "Kim kabul etti" ayri bir bilgi: karsi teklifi ALICI e-postadan kabul
       ettiyse pazarlik onun tarafinda kapandi ve sirada fatura var. Rozetin
       tek basina "Accepted" demesi, operatorun kendi kabul ettigi bir teklifle
       ayni gorunuyordu. */
    match($rSt){
      'accept'=>abadge('✓ Accepted','#1f9d63').((($resp['accepted_by']??'')==='buyer')?'<div class="ahint" style="color:#1f9d63">by buyer ↩ accepted</div>':''),
      'decline'=>abadge('✗ Declined','#c0392b').((($resp['declined_by']??'')==='buyer')?'<div class="ahint">by buyer</div>':''),
      /* Karsi teklifi KIMIN verdigi, sirada kimin oldugunu belirliyor --
         rozetin tek basina "Counter" demesi iki durumu ayirt etmiyordu. */
      'counter'=>abadge((($resp['counter_by']??'seller')==='buyer'?'↩ Buyer countered':'↩ Countered'),'#9a7320')
                 .'<div class="ahint">'.vestra_offer_counter_count($resp).'/'.VESTRA_OFFER_MAX_COUNTERS.' rounds</div>',
      default=>abadge('⏳ Pending','#888')},
    /* Anlasilan fiyat kabulden SONRA da gorunsun: eskiden sutun yalnizca
       'counter' durumunda doluydu, kabul edilir edilmez bosaliyordu -- yani
       hangi fiyata anlasildigi tam ihtiyac duyulan anda kayboluyordu. */
    ($resp&&$rSt==='counter')?eur($resp['counter_price']??0)
      :(($resp&&$rSt==='accept'&&!empty($resp['agreed_unit']))?eur($resp['agreed_unit']).'<div class="ahint">agreed</div>':'—'),
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

<?php
/* Gosterim para birimi. Fiyatlar EUR olarak saklaniyor; burasi yalnizca
   ALICININ NE GORDUGUNU yonetiyor. Kur alinamiyorsa katalog EUR'a dusuyor --
   sessizce yanlis bir kurla cevirmektense. Bu kart, hangi durumda oldugumuzu
   tahmin ettirmiyor, YAZIYOR. */
$fxState = vestra_fx_state();
$fxSrc   = $fxState['source'];
$fxMan   = _vsec_read('fx_manual.json');
$fxLabel = ['ecb'=>'European Central Bank (daily reference rate)','market'=>'market feed',
            'manual'=>'your manual rates','' =>'— none, prices stay in EUR'][$fxSrc] ?? $fxSrc;
?>
<div class="acard" style="margin-bottom:16px<?= $fxSrc===''?';border-color:rgba(192,57,43,.4)':'' ?>">
  <div class="acard-hd"><h3>💱 Display currency — EUR · USD · AUD · CAD</h3>
    <form method="post" action="/admin" style="margin:0"><?= csrfField() ?>
      <input type="hidden" name="_action" value="fx_refresh">
      <button class="abtn" type="submit">↻ Fetch rates now</button></form></div>
  <div class="acard-body">
    <p style="color:var(--mut);font-size:13px;margin:0 0 12px;max-width:760px">
      Buyers outside the EU see prices in their own currency (US → USD, Canada → CAD, Australia → AUD,
      elsewhere → USD), and can switch it themselves from the header. <b>Orders are always invoiced in EUR</b> —
      only the catalogue display is converted, and every converted page says so.
    </p>
    <div style="display:flex;gap:26px;flex-wrap:wrap;font-size:13px;margin-bottom:14px">
      <div><div class="ahint">Rate source in use</div><b style="<?= $fxSrc===''?'color:#c0392b':'' ?>"><?= htmlspecialchars($fxLabel) ?></b></div>
      <div><div class="ahint">Rate date</div><b><?= htmlspecialchars($fxState['date'] !== '' ? $fxState['date'] : '—') ?></b></div>
      <?php foreach(['USD','AUD','CAD'] as $c): ?>
        <div><div class="ahint">1 EUR → <?= $c ?></div><b><?= ($r=(float)($fxState['rates'][$c]??0))>0 ? number_format($r,4) : '—' ?></b></div>
      <?php endforeach; ?>
    </div>
    <?php if($fxSrc===''): ?>
      <div class="amsg" style="background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.35);color:#c0392b;margin:0 0 14px">
        ⚠ No rate available, so <b>every buyer sees EUR</b> no matter which currency they pick. Press
        <b>Fetch rates now</b>; if that fails, the host blocks outbound HTTP — type the rates in below.
      </div>
    <?php endif; ?>
    <form method="post" action="/admin" style="margin:0;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <?= csrfField() ?><input type="hidden" name="_action" value="fx_manual">
      <?php foreach([['USD','fx_usd','1.09'],['AUD','fx_aud','1.66'],['CAD','fx_cad','1.49']] as [$c,$nm,$ph]): ?>
        <label style="font-size:12px;color:var(--mut)">Manual 1 EUR → <?= $c ?><br>
          <input type="text" inputmode="decimal" name="<?= $nm ?>" placeholder="<?= $ph ?>" style="width:110px;padding:6px 10px;font-size:13px;font-family:inherit"
                 value="<?= htmlspecialchars((string)(($v=(float)($fxMan['rates'][$c]??0))>0 ? $v : '')) ?>"></label>
      <?php endforeach; ?>
      <button class="abtn" type="submit">Save manual rates</button>
      <span class="ahint" style="max-width:340px">Used only as a last resort, when no live source answers. Leave empty to remove.</span>
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
  /* Users sekmesindeki urun sayisi buraya link veriyor: sayiya tiklayinca
     O saticinin ilanlari. Filtre yalnizca TABLOYU daraltiyor, ustteki
     toplu islem kartlari (senkron/rebrand) oldugu gibi kaliyor -- onlar
     zaten kendi hedeflerini kendileri seciyor. */
  $lsel       = trim((string)($_GET['seller'] ?? ''));
  $lrows      = $lsel === '' ? $listings
              : array_values(array_filter($listings, fn($p)=>(string)($p['seller_uid']??'')===$lsel));
  $lselName   = '';
  if($lsel !== ''){ foreach($accounts as $__a){ if(($__a['id']??'')===$lsel){ $lselName = (string)($__a['company'] ?: ($__a['name'] ?? '')); break; } } unset($__a); }
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
        <div class="afield"><label>Storefront section</label>
          <select name="section">
            <?php $_cs = vestra_product_section($ledit ?? []); foreach(vestra_sections() as $_sk=>$_sl): ?>
              <option value="<?= htmlspecialchars($_sk) ?>"<?= $_sk===$_cs?' selected':'' ?>><?= htmlspecialchars($_sl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
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
      <div class="acols3">
        <div class="afield"><label>Ships from <span style="color:var(--mut);font-weight:400">— country the goods leave from</span></label>
          <input name="ships_from" maxlength="40" value="<?= htmlspecialchars($ledit['ships_from']??'') ?>" placeholder="Italy · Germany · IT — empty falls back to “EU”"></div>
        <div class="afield"></div><div class="afield"></div>
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
<?php if($lsel!==''): ?>
<div class="acard" style="margin-bottom:14px;border-color:var(--acc)"><div class="acard-body" style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
  <div style="font-size:13px"><b>Filtered to one seller:</b>
    <?= htmlspecialchars($lselName !== '' ? $lselName : 'uid '.substr($lsel,0,10).'…') ?>
    — <?= count($lrows) ?> listing<?= count($lrows)===1?'':'s' ?></div>
  <a class="abtn" href="/admin?tab=listings">✕ Show all listings</a>
</div></div>
<?php endif; ?>
<?php if(!$lrows): ?><div class="acard"><div class="aempty"><?= $lsel!=='' ? 'This seller has no listings.' : 'No custom listings yet.' ?></div></div>
<?php else: ?>
<div class="acard"><div class="atscroll"><table class="atable">
  <?= arow(['','Brand','Product','SKU','Mode','MOQ','From','Ships from','Seller','Status',''],true) ?>
  <?php foreach(array_reverse($lrows) as $p): $st=$p['status']??'approved'; $thumb=vestra_primary_image($p); ?>
  <tr>
    <td class="ac"><?php if($thumb): ?><img src="<?= htmlspecialchars($thumb) ?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:7px;border:1px solid var(--line)"><?php else: ?><div style="width:42px;height:42px;border-radius:7px;background:linear-gradient(135deg,<?= htmlspecialchars($p['accent']??'#cfc8ba') ?>,#e8e2d7)"></div><?php endif; ?></td>
    <td class="ac"><b><?= htmlspecialchars($p['brand']??'') ?></b></td>
    <td class="ac"><?= htmlspecialchars($p['name']??'') ?><div class="ahint"><?= htmlspecialchars(substr($p['id']??'',0,14)) ?>…</div><?= !empty($p['colors'])?'<div style="margin-top:3px">'.vestra_color_dots((array)$p['colors'],7).'</div>':'' ?></td>
    <td class="ac"><span class="atag"><?= htmlspecialchars($p['sku']??'') ?></span></td>
    <td class="ac"><span class="modechip <?= htmlspecialchars($p['mode']??'fixed') ?>"><?= htmlspecialchars($p['mode']??'fixed') ?></span></td>
    <td class="ac"><?= htmlspecialchars((string)($p['moq']??'')) ?> <?= htmlspecialchars($p['unit']??'pc') ?></td>
    <td class="ac"><?= $st==='offer'?'—':eur(vestra_from_price($p)) ?></td>
    <td class="ac" style="white-space:nowrap">
      <?php /* Alici bu satiri gumruk/teslim suresi icin okuyor. Girilmemis
               ilan sessizce varsayilan "Ships from EU" gosteriyor -- yani
               yanlis olabilecek bir vaat. Eksigi kirmizi isaretle ki
               doldurulacaklar listede gorunsun. */
        $__sf = trim((string)($p['ships_from'] ?? ''));
        if($__sf === ''): ?>
        <span style="color:#c0392b" title="Not set — the product page falls back to “Ships from EU”">⚠ not set</span>
      <?php else: ?>
        <?= vestra_ships_from_flag($p) ?> <?= htmlspecialchars($__sf) ?>
      <?php endif; ?>
    </td>
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
  /* Google is opt-in and costs money per call, so the source picker offers it only once
     a key is actually stored — a disabled option that explains itself beats an enabled
     one that returns "0 found" and leaves the operator guessing which end is broken. */
  require_once __DIR__.'/inc/discover_google.php';
  $googleOn = vestra_google_key()!=='';
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
    <?php /* The source belongs next to the country because it changes what a run costs,
             not just what it returns. OSM is free; Google is a billed API call. */ ?>
    <div class="afield" style="margin:0"><label>Search with</label>
      <select id="discSource">
        <option value="osm">OpenStreetMap — free</option>
        <option value="google"<?= $googleOn?'':' disabled' ?>>Google Maps<?= $googleOn?' — richer, billed':' (add a key first)' ?></option>
        <option value="both"<?= $googleOn?'':' disabled' ?>>Both — OSM first, Google fills the gaps</option>
      </select>
    </div>
    <button class="abtn primary" type="button" onclick="findCustomersLive(this)">🎯 Find customers</button>
  </div>
  <p class="ahint" style="margin-top:8px;font-size:11px">Whole-country searches take longer (up to ~60s) and may return nothing for very large countries — narrow to a city (local spelling, e.g. Milano not Milan) if that happens. Already have customers without an email (e.g. from a CSV import)? <a href="#" onclick="findMissingEmailsLive(this);return false" style="color:var(--acc)">🔍 Find their emails too</a>.</p>
  <div id="fcWrap" style="display:none;margin-top:10px;padding:10px 12px;background:var(--bg2);border-radius:8px">
    <div id="fcBar" style="font-weight:600;font-size:13px;margin-bottom:6px"></div>
    <div id="fcLog" style="max-height:260px;overflow:auto"></div>
  </div>
  <details style="margin-top:12px"<?= $googleOn?'':' open' ?>>
    <summary style="cursor:pointer;font-size:12px;color:<?= $googleOn?'var(--mut)':'#a9781a' ?>">🔎 Google ile ara — <?= $googleOn?'anahtar kayıtlı ✓':'daha iyi adres ve e-posta bulur, anahtar gerekiyor' ?></summary>
    <div style="margin-top:10px;font-size:12px;color:var(--mut);line-height:1.6">
      <p style="margin:0 0 8px">Google Maps'te bağımsız butiklerin neredeyse hepsi adresi, telefonu ve sitesiyle kayıtlı — OpenStreetMap'te çoğu yok. İki API kullanılıyor:</p>
      <ol style="margin:0 0 10px 18px;padding:0">
        <li><b>Places API (New)</b> — dükkânı, adresini, telefonunu ve sitesini bulur. <i>Zorunlu.</i></li>
        <li><b>Custom Search JSON API</b> — sitenin kendi iletişim sayfası e-postayı vermediğinde Google'ın dizinine sorar. <i>İsteğe bağlı.</i></li>
      </ol>
      <p style="margin:0 0 8px"><b>Nasıl alınır:</b> Google Cloud Console → yeni proje → <i>APIs &amp; Services → Enable APIs</i>'ten <b>Places API (New)</b>'i etkinleştirin → <i>Credentials → Create API key</i>. Faturalandırma açık olmalı; Google'ın aylık ücretsiz kotası var, üstü ücretli — güncel rakam Cloud Console'daki fiyatlandırma sayfasında. Anahtarı <i>Application restrictions</i> ile <b>IP</b>'ye kısıtlamanız önerilir (sunucunun IP'si).</p>
      <p style="margin:0 0 10px">E-posta yedeği için ayrıca <a href="https://programmablesearchengine.google.com/" target="_blank" rel="noopener" style="color:var(--acc)">Programmable Search Engine</a>'den bir arama motoru oluşturup <b>"Search the entire web"</b> seçeneğini <b>açın</b> (kapalıyken <code>site:</code> sorguları hiçbir şey döndürmez) ve <b>Search engine ID</b>'yi aşağıya yapıştırın.</p>
      <p style="margin:0 0 10px;color:#8a6d1f">Anahtar sunucuda <code>data/email_settings.json</code> içinde tutuluyor: web'e kapalı, git'e girmiyor. Bu depo herkese açık olduğu için anahtar hiçbir zaman koda ya da Actions girdisine yazılmaz.</p>
    </div>
    <form method="post" class="aform" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
      <?= csrfField() ?><input type="hidden" name="_action" value="save_google">
      <div class="afield" style="margin:0;flex:1;min-width:240px"><label>Google API key <?= $googleOn?'<span class="ahint">· kayıtlı, boş bırakırsanız korunur</span>':'' ?></label><input type="password" name="google_key" placeholder="AIza…" autocomplete="new-password"></div>
      <div class="afield" style="margin:0;flex:1;min-width:180px"><label>Search engine ID (cx) <span style="font-weight:400;color:var(--mut)">— e-posta yedeği için</span></label><input name="google_cx" value="<?= htmlspecialchars((string)vestra_cfg('google_cx','')) ?>" placeholder="a1b2c3d4e5f6…"></div>
      <button class="abtn primary" type="submit">Kaydet</button>
      <?php if($googleOn): ?>
      <label style="display:flex;align-items:center;gap:5px;font-size:11px;color:#c0392b;margin:0 0 4px"><input type="checkbox" name="google_clear" value="1"> anahtarı sil</label>
      <?php endif; ?>
    </form>
  </details>
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
  var srcEl=document.getElementById('discSource'), src=srcEl?srcEl.value:'osm';
  var fd=new FormData(); fd.append('_action','discover_leads'); fd.append('_csrf',VADMIN_CSRF); fd.append('disc_country',country); fd.append('disc_city',city); fd.append('disc_source',src);
  fetch('/admin',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    /* Only warn about OSM when OSM was actually asked to run — a Google-only search
       that works fine would otherwise print "OpenStreetMap unreachable". */
    if(d.osm_ok===false && src!=='google'){
      var warn=document.createElement('div'); warn.style.fontSize='12px'; warn.style.fontWeight='600'; warn.style.color='#c0392b'; warn.style.padding='2px 0';
      warn.textContent='⚠ OpenStreetMap unreachable (all mirrors failed) — today\'s results may be incomplete. Try again in a minute.';
      log.appendChild(warn);
    }
    var line=document.createElement('div'); line.style.fontSize='12px'; line.style.fontWeight='600'; line.style.padding='2px 0';
    line.textContent=(d.total||0)+' shop(s) found, '+(d.added||0)+' new added to your customers.'
      +(src!=='osm'&&d.google_found?' ('+d.google_found+' from Google)':'');
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
      bar.textContent = (src==='google'&&d.google_ok===false) ? '✗ Google search failed — see the note above.'
                      : d.timed_out ? '✗ Overpass timed out — add a city and retry.'
                      : (d.osm_ok===false && src!=='google') ? '✗ OSM search failed — try again.'
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
    /* Satirdaki zarf: mektup kutusunu ac, musteriyi sec, oraya kaydir. Kutu tablonun
       DISINDA oldugu icin (ic ice form olmasin diye) bu kucuk kopru gerekiyor. */
    function leadLetter(id){
      var box=document.getElementById('letterBox'), sel=document.getElementById('letterLid');
      if(!box||!sel) return;
      box.open=true; sel.value=id;
      if(sel.value!==id){ alert('Bu müşteri listede yok — abonelikten çıkmış ya da geçerli adresi olmayabilir.'); return; }
      box.scrollIntoView({behavior:'smooth',block:'start'});
    }
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
        <td class="ac" style="white-space:nowrap">
          <?php if(!$noEmail && !$unsub): ?><button type="button" class="abtn" style="font-size:10.5px;padding:2px 7px" title="Bu müşteriye elden mektup yaz" onclick="leadLetter('<?= htmlspecialchars($l['id']??'') ?>')">✉️</button> <?php endif; ?>
          <button type="button" class="abtn" style="color:var(--bad);border-color:rgba(239,154,154,.3)" onclick="leadDelete('<?= htmlspecialchars($l['id']??'') ?>')">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div>
  </form>
    <?php /* ELDEN MEKTUP. Kampanya sablonu her ise yaramiyor: bir musteri belirli bir
             soru sormussa ona sablon gondermek cevap degil. Bu kutu, operatorun kendi
             metnini platformun kimliginden (support@) gondermesini sagliyor -- ve
             gonderimi musteri kaydina isliyor, ki "bu adama en son ne yazdik" sorusu
             cevapsiz kalmasin ve kampanya secimi ayni kisiye ikinci kez gitmesin.

             Tablo kendi formunun icinde oldugu icin bu form onun DISINDA duruyor:
             ic ice form HTML'de gecersiz ve tarayici sessizce ic formu dusuruyor. */ ?>
    <details id="letterBox" style="border-bottom:1px solid var(--line);background:var(--bg2)">
      <summary style="cursor:pointer;padding:12px 18px;font-size:13px;font-weight:600">✉️ Bir müşteriye elden mektup yaz</summary>
      <form method="post" action="/admin" class="aform" style="padding:0 18px 16px"
            onsubmit="return confirm('Mektup gönderilsin mi? Bu geri alınamaz.')">
        <?= csrfField() ?><input type="hidden" name="_action" value="lead_letter">
        <div class="afield"><label>Müşteri</label>
          <select name="lid" id="letterLid" required style="max-width:520px">
            <option value="">— seçin —</option>
            <?php foreach($leadsView as $ll):
              if(($ll['status']??'')==='unsubscribed') continue;
              if(!filter_var($ll['email']??'',FILTER_VALIDATE_EMAIL)) continue; ?>
            <option value="<?= htmlspecialchars($ll['id']??'') ?>"><?= htmlspecialchars(($ll['company']??'') ?: ($ll['email']??'')) ?> — <?= htmlspecialchars($ll['email']) ?><?= !empty($ll['last_contacted_at'])?' · son: '.htmlspecialchars(substr((string)$ll['last_contacted_at'],0,10)):'' ?></option>
            <?php endforeach; ?>
          </select>
          <p class="ahint" style="margin:4px 0 0">Abonelikten çıkmış ve geçerli adresi olmayan kayıtlar listede yok.</p>
        </div>
        <div class="afield"><label>Konu</label><input name="subject" required maxlength="200" style="max-width:520px" placeholder="VESTRA — following up on your enquiry"></div>
        <div class="afield"><label>Mektup</label><textarea name="body" required rows="16" style="width:100%;font-family:inherit;line-height:1.6" placeholder="Dear …"></textarea>
          <p class="ahint" style="margin:4px 0 0">Düz metin yazın; satır sonları korunur. <b>support@vestrasales.com</b> adresinden, kampanyalarla aynı yoldan (Brevo) gider.</p>
        </div>
        <button class="abtn primary" type="submit">Gönder</button>
      </form>
    </details>
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

<?php // ══════════════════════════════════════════════════════ SECURITY
elseif($tab==='dropship'):
?>
<div class="acard-hd" style="margin-bottom:6px"><h3>📮 Dropship orders</h3></div>
<p style="color:var(--mut);font-size:13px;margin:0 0 16px;max-width:780px">
  Single-piece orders placed by trade partners for their own customers. Price is the wholesale
  price plus 20%; shipping is charged per zone (Europe €16 · United States €30 · Japan €30) and
  duties at destination are not included. Per-unit stock is not tracked, so
  <b>confirm availability with the seller before shipping</b>.
</p>
<div class="acard">
  <div class="acard-hd"><h3><?= count($dropOrders) ?> order(s)<?= $dropUnshipped ? ' · '.$dropUnshipped.' paid, not yet shipped' : '' ?></h3></div>
  <div class="acard-body atscroll">
    <?php if(!$dropOrders): ?>
      <p style="color:var(--mut);font-size:13px;margin:0">No dropship orders yet.</p>
    <?php else: ?>
    <table class="atable" style="min-width:900px">
      <thead><tr><th>Ref</th><th>Placed</th><th>Article</th><th>Variant</th><th class="ac">Qty</th>
        <th class="ac">Amount</th><th>Ship to</th><th>Zone</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($dropOrders as $d):
        $addr = $d['shipping_address'] ?? null;
        $st   = (string)($d['status'] ?? 'pending');
        $stCol = $st==='paid' ? '#1f9d63' : ($st==='released' ? 'var(--mut)' : '#c0392b'); ?>
        <tr>
          <td><code style="font-size:11.5px"><?= htmlspecialchars((string)($d['ref'] ?? '')) ?></code>
              <?php if(!empty($d['partner_reference'])): ?><div class="ahint">partner: <?= htmlspecialchars((string)$d['partner_reference']) ?></div><?php endif; ?></td>
          <td style="font-size:12px;color:var(--mut)"><?= htmlspecialchars(substr((string)($d['created'] ?? ''),0,16)) ?></td>
          <td><b><?= htmlspecialchars((string)($d['brand'] ?? '')) ?></b><br>
              <span style="font-size:12px"><?= htmlspecialchars((string)($d['name'] ?? '')) ?></span>
              <?php if(!empty($d['sku'])): ?><div class="ahint">SKU <?= htmlspecialchars((string)$d['sku']) ?></div><?php endif; ?></td>
          <td style="font-size:12px"><?= htmlspecialchars(trim((string)($d['colour'] ?? '').' / '.(string)($d['size'] ?? ''), ' /')) ?></td>
          <td class="ac"><?= (int)($d['qty'] ?? 1) ?></td>
          <td class="ac"><?= eur((float)($d['amount'] ?? 0)) ?><?php if(!empty($d['ship_fee'])): ?><div class="ahint">+ <?= eur((float)$d['ship_fee']) ?> ship</div><?php endif; ?></td>
          <td style="font-size:12px;color:var(--mut);max-width:200px">
            <?php if($addr): ?>
              <?= htmlspecialchars(trim((string)($addr['name'] ?? ''))) ?><br>
              <?= htmlspecialchars(trim((string)($addr['line1'] ?? '').' '.(string)($addr['line2'] ?? ''))) ?><br>
              <?= htmlspecialchars(trim((string)($addr['postal_code'] ?? '').' '.(string)($addr['city'] ?? '').', '.(string)($addr['country'] ?? ''))) ?>
            <?php else: ?><span class="ahint">— not paid yet</span><?php endif; ?>
          </td>
          <td style="font-size:12px"><?= htmlspecialchars((string)($d['ship_zone'] ?? '—')) ?></td>
          <td style="font-size:12px;color:<?= $stCol ?>"><b><?= htmlspecialchars($st) ?></b>
              <?php if(!empty($d['fulfilled'])): ?><div class="ahint">notified</div><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php
elseif($tab==='api'):
  $apiKeys = vestra_api_keys_public();
  $once    = $_SESSION['api_key_once'] ?? null;
  if ($once) unset($_SESSION['api_key_once']);          // bir kez gosterilir, sonra gider
  $live    = array_values(array_filter($apiKeys, fn($k) => empty($k['revoked_at'])));
?>
<div class="acard-hd" style="margin-bottom:6px"><h3>🔌 Partner API — catalogue feed</h3></div>
<p style="color:var(--mut);font-size:13px;margin:0 0 16px;max-width:760px">
  Give a wholesale partner read access to the catalogue over JSON: brands, articles, sizes,
  colours, MOQ, tiered prices in EUR, photo URLs and the product link. Each partner gets
  their own key, so one can be cut off without touching the others.
  <b>The key carries trade prices</b> — the same thing the trade-licence gate protects — so
  issue one only to a verified trade account.
</p>

<?php if ($once): ?>
<div class="acard" style="margin-bottom:16px;border-color:rgba(169,127,44,.55)">
  <div class="acard-body">
    <b>New key for “<?= htmlspecialchars($once['label']) ?>” — copy it now.</b>
    <p style="color:var(--mut);font-size:13px;margin:6px 0 10px">
      This is the only time it is shown. Only a one-way hash of it is stored, so it cannot be
      looked up later — if it is lost, revoke it and issue another.
    </p>
    <code style="display:block;font-size:15px;background:#faf7f1;padding:10px 14px;border-radius:8px;
                 color:#8a6420;border:1px solid var(--line);user-select:all;word-break:break-all">
      <?= htmlspecialchars($once['secret']) ?></code>
  </div>
</div>
<?php endif; ?>

<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd"><h3>Issue a key</h3></div>
  <div class="acard-body">
    <form method="post" action="/admin" style="margin:0;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <?= csrfField() ?><input type="hidden" name="_action" value="api_key_new">
      <label style="font-size:12px;color:var(--mut)">Partner<br>
        <input type="text" name="label" required placeholder="SYMAX — Tokyo" style="width:230px;padding:6px 10px;font-size:13px;font-family:inherit"></label>
      <label style="font-size:12px;color:var(--mut)">Their account e-mail (optional)<br>
        <input type="email" name="account" placeholder="buyer@example.com" style="width:230px;padding:6px 10px;font-size:13px;font-family:inherit"></label>
      <button class="abtn primary" type="submit">Issue key</button>
    </form>
  </div>
</div>

<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd"><h3>Keys (<?= count($live) ?> active)</h3></div>
  <div class="acard-body atscroll">
    <?php if (!$apiKeys): ?>
      <p style="color:var(--mut);font-size:13px;margin:0">No keys issued yet.</p>
    <?php else: ?>
    <table class="atable" style="min-width:640px">
      <thead><tr><th>Partner</th><th>Key</th><th>Account</th><th>Issued</th><th>Last used</th><th class="ac">Calls</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($apiKeys as $k): $dead = !empty($k['revoked_at']); ?>
        <tr<?= $dead ? ' style="opacity:.5"' : '' ?>>
          <td><b><?= htmlspecialchars((string)$k['label']) ?></b><?= $dead ? ' <span class="ahint">revoked</span>' : '' ?></td>
          <td><code style="font-size:11.5px"><?= htmlspecialchars((string)$k['hint']) ?></code></td>
          <td style="font-size:12px;color:var(--mut)"><?= htmlspecialchars((string)($k['account'] ?: '—')) ?></td>
          <td style="font-size:12px;color:var(--mut)"><?= htmlspecialchars(substr((string)$k['created_at'], 0, 10)) ?></td>
          <td style="font-size:12px;color:var(--mut)"><?= htmlspecialchars(substr((string)$k['last_used'], 0, 16) ?: 'never') ?></td>
          <td class="ac" style="font-size:12px"><?= (int)($k['calls'] ?? 0) ?></td>
          <td class="ac">
            <?php if (!$dead): ?>
            <form method="post" action="/admin" style="margin:0" onsubmit="return confirm('Revoke this key? The partner\'s integration stops working immediately.')">
              <?= csrfField() ?><input type="hidden" name="_action" value="api_key_revoke">
              <input type="hidden" name="kid" value="<?= htmlspecialchars((string)$k['id']) ?>">
              <button class="abtn" type="submit" style="font-size:11px;padding:3px 9px;color:#c0392b">Revoke</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>What to send the partner</h3></div>
  <div class="acard-body">
    <p style="color:var(--mut);font-size:13px;margin:0 0 10px">
      Full documentation: <a class="acc" href="/api-docs" target="_blank">vestrasales.com/api-docs</a>
    </p>
<pre style="background:#faf7f1;border:1px solid var(--line);border-radius:8px;padding:12px 14px;
            font-size:12px;line-height:1.65;overflow-x:auto;margin:0">curl -H "Authorization: Bearer &lt;KEY&gt;" \
  "https://vestrasales.com/api/catalog?a=products&amp;page=1&amp;per=100"</pre>
    <p style="color:var(--mut);font-size:12.5px;margin:12px 0 0;line-height:1.6">
      ⚠ <b>The feed carries no live stock and no EAN.</b> Per-unit stock is not tracked, so every
      product reports <code>stock.tracked = false</code> rather than a number a partner could
      resell against. Tell them plainly — a partner who discovers this after building an
      importer will not be a partner for long.
    </p>
  </div>
</div>
<?php
elseif($tab==='security'):
  /* Rolling log, newest first. Everything shown here was recorded at the moment
     of the event; opening this tab never fires geo lookups of its own. */
  $secLog    = array_reverse(_vsec_read('security_log.json'));
  $secBlocks = vestra_ip_blocks();
  $myIp      = vestra_client_ip();
  $secBadge  = function(string $ev): string {
    return match($ev) {
      'register'   => abadge('kayıt', '#1f9d63'),
      'login_ok'   => abadge('giriş', '#2e86c1'),
      'admin_ok'   => abadge('admin giriş', '#8e6fc1'),
      'login_fail' => abadge('giriş HATALI', '#c0392b'),
      'admin_fail' => abadge('admin HATALI', '#c0392b'),
      default      => abadge($ev, '#777'),
    };
  };
  $ccFlag = function(string $cc): string {
    if (strlen($cc) !== 2) return '';
    $cc = strtoupper($cc);
    return mb_chr(0x1F1E6 + ord($cc[0]) - 65).mb_chr(0x1F1E6 + ord($cc[1]) - 65);
  };
?>
<div class="acols2" style="align-items:start;margin-bottom:16px">
  <div class="acard">
    <div class="acard-hd"><h3>🚫 IP engelle</h3></div>
    <div class="acard-body">
      <p class="ahint" style="margin:0 0 10px">Tam IP (<code>1.2.3.4</code>), önek (<code>1.2.3.</code>) ya da IPv4 CIDR (<code>1.2.3.0/24</code>). Engellenen IP sitenin <b>tamamından</b> 403 alır. Sizin IP'niz: <code><?= htmlspecialchars($myIp) ?></code> — kendinizi engellemenize izin verilmez.</p>
      <form method="post" class="aform" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <?= csrfField() ?><input type="hidden" name="_action" value="sec_block_ip">
        <div class="afield" style="margin:0"><label>IP / önek / CIDR</label><input name="ip" required placeholder="203.0.113.7" style="width:170px"></div>
        <div class="afield" style="margin:0;flex:1;min-width:160px"><label>Not (neden)</label><input name="note" placeholder="sahte kayıtlar"></div>
        <button class="abtn primary" type="submit">Engelle</button>
      </form>
    </div>
  </div>
  <div class="acard">
    <div class="acard-hd"><h3>⛔ Engelli listesi (<?= count($secBlocks) ?>)</h3></div>
    <div class="acard-body">
      <?php if(!$secBlocks): ?><div class="aempty">Engelli IP yok.</div>
      <?php else: ?><div class="atscroll"><table class="atable">
        <?= arow(['Kural','Not','Eklendi',''],true) ?>
        <?php foreach($secBlocks as $b): ?>
        <tr>
          <td class="ac"><code><?= htmlspecialchars((string)($b['ip']??'')) ?></code></td>
          <td class="ac" style="color:var(--mut)"><?= htmlspecialchars((string)($b['note']??'')) ?: '—' ?></td>
          <td class="ac" style="font-size:11px;color:var(--mut)"><?= htmlspecialchars(substr((string)($b['added_at']??''),0,10)) ?></td>
          <td class="ac"><form method="post" style="margin:0"><?= csrfField() ?><input type="hidden" name="_action" value="sec_unblock_ip"><input type="hidden" name="ip" value="<?= htmlspecialchars((string)($b['ip']??'')) ?>"><button class="abtn" type="submit" style="font-size:11px;padding:2px 8px">Kaldır</button></form></td>
        </tr>
        <?php endforeach; ?>
      </table></div><?php endif; ?>
    </div>
  </div>
</div>

<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd"><h3>🌍 Kullanıcı konumları (<?= count($accounts) ?>)</h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin:0 0 10px">Her hesabın <b>kayıt anındaki</b> ve <b>son girişteki</b> IP'si, ülkesi ve şehri. Bu oturumdan önce açılmış hesaplarda kayıt bilgisi yoktur — o satırlar kullanıcı bir dahaki girişinde kendiliğinden dolar. <b>≠</b> işareti, formda beyan edilen ülkenin IP ülkesiyle uyuşmadığını söyler.</p>
  <div class="atscroll"><table class="atable">
    <?= arow(['Hesap','Beyan','Kayıt IP','Kayıt konumu','Son giriş IP','Son konum','Son görülme',''],true) ?>
    <?php
    /* En son gireni en uste al: operatorun bakmak istedigi taraf hareketli olan. */
    $secAccs = $accounts;
    usort($secAccs, fn($x,$y)=>strcmp((string)($y['last_login']??$y['created']??''), (string)($x['last_login']??$x['created']??'')));
    foreach($secAccs as $a):
      $rIp=(string)($a['reg_ip']??''); $lIp=(string)($a['last_ip']??'');
      $declCc = vestra_cc_of_country((string)($a['country']??''));
      $mk = function(string $cc,string $city,bool $vpn) use($ccFlag): string {
        if($cc==='' && $city==='') return '<span style="color:var(--mut)">—</span>';
        $s = $ccFlag($cc).' '.htmlspecialchars($cc?:'?');
        if($city!=='') $s .= ' · '.htmlspecialchars($city);
        if($vpn) $s .= ' '.abadge('VPN','#a9781a');
        return $s;
      };
    ?>
    <tr>
      <td class="ac"><b><?= htmlspecialchars($a['name']??'—') ?></b><div class="ahint"><?= htmlspecialchars($a['email']??'') ?></div></td>
      <td class="ac" style="font-size:12px"><?= htmlspecialchars($a['country']??'') ?: '<span style="color:var(--mut)">—</span>' ?></td>
      <td class="ac"><code style="font-size:11px"><?= htmlspecialchars($rIp)?:'<span style="color:var(--mut)">—</span>' ?></code></td>
      <td class="ac" style="font-size:12px;white-space:nowrap">
        <?= $mk((string)($a['reg_cc']??''),(string)($a['reg_city']??''),!empty($a['reg_vpn'])) ?>
        <?php if(!empty($a['reg_cc']) && $declCc!=='' && strcasecmp($declCc,(string)$a['reg_cc'])!==0): ?>
          <b style="color:#c0392b" title="Beyan edilen ülke ile kayıt IP'sinin ülkesi farklı">≠</b>
        <?php endif; ?>
      </td>
      <td class="ac"><code style="font-size:11px"><?= htmlspecialchars($lIp)?:'<span style="color:var(--mut)">—</span>' ?></code></td>
      <td class="ac" style="font-size:12px;white-space:nowrap"><?= $mk((string)($a['last_cc']??''),(string)($a['last_city']??''),!empty($a['last_vpn'])) ?></td>
      <td class="ac" style="font-size:11px;color:var(--mut);white-space:nowrap"><?= htmlspecialchars(str_replace('T',' ',substr((string)($a['last_login']??''),0,16))) ?: '—' ?></td>
      <td class="ac">
        <?php $bIp = $lIp !== '' ? $lIp : $rIp;
              if($bIp!=='' && $bIp!==$myIp && !vestra_ip_blocked($bIp)): ?>
        <form method="post" style="margin:0" onsubmit="return confirm('<?= htmlspecialchars($bIp) ?> engellensin mi? Bu IP sitenin tamamından 403 alır.')">
          <?= csrfField() ?><input type="hidden" name="_action" value="sec_block_ip"><input type="hidden" name="ip" value="<?= htmlspecialchars($bIp) ?>"><input type="hidden" name="note" value="<?= htmlspecialchars(mb_substr((string)($a['email']??''),0,60)) ?>">
          <button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#c0392b">Engelle</button>
        </form>
        <?php elseif($bIp!=='' && vestra_ip_blocked($bIp)): ?><span style="font-size:10px;color:#c0392b">engelli</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  </div>
</div>

<div class="acard">
  <div class="acard-hd"><h3>🕵️ Son olaylar (<?= count($secLog) ?>)</h3></div>
  <div class="acard-body">
  <p class="ahint" style="margin:0 0 10px">Kayıt, giriş ve admin girişleri — IP, ülke ve VPN/veri merkezi işaretiyle. <b>VPN</b> rozeti "proxy ya da hosting ağından geliyor" demek: kesin hüküm değil, KYB onayından önce bakılacak bir işaret. Ülkesi boş satırlar, bilgi sağlayıcının o an cevap vermediği anlardır.</p>
  <?php if(!$secLog): ?><div class="aempty">Henüz olay yok — bundan sonraki her kayıt ve giriş burada görünecek.</div>
  <?php else: ?>
  <div class="atscroll"><table class="atable">
    <?= arow(['Zaman','Olay','E-posta','IP','Ülke','Şehir','VPN','Tarayıcı',''],true) ?>
    <?php foreach(array_slice($secLog,0,150) as $e): $eIp=(string)($e['ip']??''); ?>
    <tr>
      <td class="ac" style="font-size:11px;color:var(--mut);white-space:nowrap"><?= htmlspecialchars(str_replace('T',' ',substr((string)($e['ts']??''),0,16))) ?></td>
      <td class="ac"><?= $secBadge((string)($e['event']??'')) ?></td>
      <td class="ac" style="font-size:12px"><?= htmlspecialchars((string)($e['email']??'')) ?: '<span style="color:var(--mut)">—</span>' ?></td>
      <td class="ac"><code style="font-size:11px"><?= htmlspecialchars($eIp) ?></code></td>
      <td class="ac" style="white-space:nowrap"><?= $ccFlag((string)($e['cc']??'')) ?> <?= htmlspecialchars((string)($e['cc']??'')) ?: '<span style="color:var(--mut)">?</span>' ?></td>
      <td class="ac" style="font-size:11.5px;white-space:nowrap"><?= htmlspecialchars((string)($e['city']??'')) ?: '<span style="color:var(--mut)">—</span>' ?></td>
      <td class="ac"><?= !empty($e['vpn']) ? abadge('VPN','#a9781a') : '<span style="color:var(--mut)">—</span>' ?></td>
      <td class="ac" style="font-size:10px;color:var(--mut);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars((string)($e['ua']??'')) ?>"><?= htmlspecialchars(mb_substr((string)($e['ua']??''),0,40)) ?></td>
      <td class="ac">
        <?php if($eIp!=='' && !vestra_ip_blocked($eIp) && $eIp!==$myIp): ?>
        <form method="post" style="margin:0" onsubmit="return confirm('<?= htmlspecialchars($eIp) ?> engellensin mi? Bu IP sitenin tamamından 403 alır.')">
          <?= csrfField() ?><input type="hidden" name="_action" value="sec_block_ip"><input type="hidden" name="ip" value="<?= htmlspecialchars($eIp) ?>"><input type="hidden" name="note" value="olay listesinden">
          <button class="abtn" type="submit" style="font-size:10px;padding:2px 7px;color:#c0392b">Engelle</button>
        </form>
        <?php elseif($eIp!=='' && vestra_ip_blocked($eIp)): ?><span style="font-size:10px;color:#c0392b">engelli</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
  </div>
</div>

<?php // ══════════════════════════════════════════════════════ TRAFFIC
elseif($tab==='traffic'):
  /* Sayaclar ziyaret aninda yazildi. Bu sekme yalnizca OKUR: acmak hicbir dis
     servise istek atmaz, hicbir sayaci degistirmez -- yani panele bakmak
     istatistigi bozmaz. Admin sayfalari zaten hic sayilmiyor. */
  $vLive  = vestra_visits_live();
  $vToday = vestra_visits_day(date('Y-m-d'));
  $vYest  = vestra_visits_day(date('Y-m-d', strtotime('-1 day')));
  $v7     = vestra_visits_range(7);
  $v30    = vestra_visits_range(30);

  /* Giris yapmis ziyaretcinin adini gosterebilmek icin uid -> isim. */
  $vNames = [];
  foreach($accounts as $a) $vNames[(string)($a['id']??'')] = (string)(($a['name']??'') ?: ($a['email']??''));

  $vFlag = function(string $cc): string {
    if (strlen($cc) !== 2) return '';
    $cc = strtoupper($cc);
    return mb_chr(0x1F1E6 + ord($cc[0]) - 65).mb_chr(0x1F1E6 + ord($cc[1]) - 65);
  };
  $vAgo = function(int $ts): string {
    $s = max(0, time() - $ts);
    return $s < 60 ? $s.' sn önce' : intdiv($s, 60).' dk önce';
  };
  $vLoc = function(array $e) use ($vFlag): string {
    $cc = (string)($e['cc'] ?? ''); $city = (string)($e['city'] ?? '');
    if ($cc === '' && $city === '') return '<span style="color:var(--mut)">bilinmiyor</span>';
    return $vFlag($cc).' <b>'.htmlspecialchars($cc ?: '?').'</b>'.($city !== '' ? ' · '.htmlspecialchars($city) : '');
  };
  $vMax = 1;
  foreach($v30['series'] as $s) $vMax = max($vMax, (int)$s['hits']);
?>
<style>
.vkpi{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}
.vkpi-c{border:1px solid var(--line);border-radius:12px;padding:14px 16px;background:var(--bg)}
.vkpi-l{font-size:11.5px;color:var(--mut);font-weight:600;letter-spacing:.03em;text-transform:uppercase}
.vkpi-n{font-size:30px;font-weight:800;line-height:1.15;margin-top:4px}
.vkpi-s{font-size:12px;color:var(--mut);margin-top:2px}
.vchart{display:flex;align-items:flex-end;gap:3px;height:110px;padding-top:6px}
/* Bos gunun arkasinda dolgu YOK, yalnizca taban cizgisi: gri bir kutu cizersek
   ziyaretci gelmemis gun, dolu bir cubuk gibi okunuyor. */
.vchart .b{flex:1;position:relative;height:100%;display:flex;align-items:flex-end;box-shadow:inset 0 -1px 0 var(--line)}
.vchart .b i{display:block;width:100%;background:rgba(201,168,106,.45);border-radius:3px 3px 0 0}
.vchart .b u{position:absolute;left:0;bottom:0;width:100%;background:var(--acc);border-radius:3px 3px 0 0}
.vxax{display:flex;gap:3px;margin-top:5px}
.vxax span{flex:1;text-align:center;font-size:9.5px;color:var(--mut);white-space:nowrap;overflow:hidden}
.vlive-dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:#1f9d63;margin-right:7px;animation:vpulse 1.8s infinite}
@keyframes vpulse{0%,100%{opacity:1}50%{opacity:.25}}
@media(max-width:820px){.vkpi{grid-template-columns:1fr 1fr}}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <div>
    <h2 style="font-size:18px;font-weight:700">📈 Trafik — kim, nereden, kaç kişi</h2>
    <p class="ahint" style="margin-top:4px">Botlar ve admin sayfaları sayılmaz. <b>Ziyaretçi</b> = günde bir kez sayılan kişi; <b>görüntüleme</b> = açılan toplam sayfa. Sayfa her 60 saniyede kendini tazeler.</p>
  </div>
  <a class="abtn" href="/admin?tab=traffic">↻ Yenile</a>
</div>

<div class="vkpi">
  <div class="vkpi-c" style="border-color:rgba(122,214,160,.45)">
    <div class="vkpi-l"><span class="vlive-dot"></span>Şu an sitede</div>
    <div class="vkpi-n" style="color:#1f9d63"><?= count($vLive) ?></div>
    <div class="vkpi-s">son 5 dakika</div>
  </div>
  <div class="vkpi-c">
    <div class="vkpi-l">Bugün</div>
    <div class="vkpi-n"><?= (int)$vToday['uniq'] ?></div>
    <div class="vkpi-s"><?= (int)$vToday['hits'] ?> görüntüleme · dün <?= (int)$vYest['uniq'] ?></div>
  </div>
  <div class="vkpi-c">
    <div class="vkpi-l">Son 7 gün</div>
    <div class="vkpi-n"><?= (int)$v7['uniq'] ?></div>
    <div class="vkpi-s"><?= (int)$v7['hits'] ?> görüntüleme</div>
  </div>
  <div class="vkpi-c">
    <div class="vkpi-l">Son 30 gün</div>
    <div class="vkpi-n"><?= (int)$v30['uniq'] ?></div>
    <div class="vkpi-s"><?= (int)$v30['hits'] ?> görüntüleme</div>
  </div>
</div>

<div class="acard" style="margin-bottom:16px;border-color:<?= $vLive?'rgba(122,214,160,.45)':'var(--line)' ?>">
  <div class="acard-hd"><h3><span class="vlive-dot"></span>Şu an sitede (<?= count($vLive) ?>)</h3><span class="ahint">son 5 dakikada hareket eden herkes</span></div>
  <div class="acard-body">
  <?php if(!$vLive): ?>
    <div class="aempty">Şu anda sitede kimse yok. Biri girdiğinde 5 dakika boyunca burada görünür.</div>
  <?php else: ?>
    <?php /* min-width: telefonda dort sutun sikismasin, .atscroll yana kaysin --
             yoksa "Test Buyer GmbH" harf harf alt alta diziliyordu. */ ?>
    <div class="atscroll"><table class="atable" style="min-width:520px">
      <?= arow(['Konum','Baktığı sayfa','Kim','Son hareket'],true) ?>
      <?php foreach(array_slice($vLive,0,60) as $e): $uid=(string)($e['uid']??''); ?>
      <tr>
        <td class="ac" style="white-space:nowrap"><?= $vLoc($e) ?></td>
        <td class="ac"><code style="font-size:11.5px"><?= htmlspecialchars((string)($e['path']??'')) ?: '/' ?></code></td>
        <td class="ac" style="font-size:12px">
          <?= $uid !== '' ? '<b>'.htmlspecialchars($vNames[$uid] ?? 'üye').'</b>' : '<span style="color:var(--mut)">ziyaretçi (girişsiz)</span>' ?>
        </td>
        <td class="ac" style="font-size:11.5px;color:var(--mut);white-space:nowrap"><?= htmlspecialchars($vAgo((int)($e['ts']??0))) ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <p class="ahint" style="margin:10px 0 0">Kimlik saklanmıyor: her satır IP + tarayıcının <b>tuzlanmış özetiyle</b> ayrılıyor, IP'nin kendisi bu listeye hiç yazılmıyor. Kim olduğu ancak giriş yapmışsa görünür. Kayıt ve giriş IP'leri <a href="/admin?tab=security" style="color:var(--acc)">🔐 Security</a> sekmesinde.</p>
  <?php endif; ?>
  </div>
</div>

<div class="acard" style="margin-bottom:16px">
  <div class="acard-hd"><h3>📅 Son 30 gün</h3><span class="ahint">koyu = ziyaretçi · açık = görüntüleme</span></div>
  <div class="acard-body">
    <?php if(!$v30['hits']): ?>
      <div class="aempty">Henüz veri yok — sayaç bu sürümle başlıyor, ilk ziyaretçiden itibaren dolar.</div>
    <?php else: ?>
    <div class="vchart">
      <?php foreach($v30['series'] as $ymd => $s): $h=(int)$s['hits']; $u=(int)$s['uniq']; ?>
      <div class="b" title="<?= htmlspecialchars($ymd) ?> — <?= $u ?> ziyaretçi, <?= $h ?> görüntüleme">
        <i style="height:<?= $h ? max(2, round(100*$h/$vMax)) : 0 ?>%"></i>
        <u style="height:<?= $u ? max(2, round(100*$u/$vMax)) : 0 ?>%"></u>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="vxax">
      <?php $i=0; foreach($v30['series'] as $ymd => $s): $i++; ?>
      <span><?= ($i % 5 === 0 || $i === count($v30['series'])) ? htmlspecialchars(substr($ymd,8,2).'.'.substr($ymd,5,2)) : '&nbsp;' ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="acols3" style="align-items:start">
  <div class="acard">
    <div class="acard-hd"><h3>🌍 Ülkeler <span class="ahint">(30 gün)</span></h3></div>
    <div class="acard-body">
      <?php if(!$v30['cc']): ?><div class="aempty">Veri yok.</div>
      <?php else: ?><div class="atscroll"><table class="atable">
        <?= arow(['Ülke','Ziyaretçi'],true) ?>
        <?php foreach(array_slice($v30['cc'],0,15,true) as $cc => $n): ?>
        <?= arow([$vFlag((string)$cc).' <b>'.htmlspecialchars((string)$cc).'</b>', (string)(int)$n]) ?>
        <?php endforeach; ?>
      </table></div><?php endif; ?>
    </div>
  </div>
  <div class="acard">
    <div class="acard-hd"><h3>🏙️ Şehirler <span class="ahint">(30 gün)</span></h3></div>
    <div class="acard-body">
      <?php if(!$v30['city']): ?><div class="aempty">Veri yok.</div>
      <?php else: ?><div class="atscroll"><table class="atable">
        <?= arow(['Şehir','Ziyaretçi'],true) ?>
        <?php foreach(array_slice($v30['city'],0,15,true) as $c => $n): ?>
        <?= arow([htmlspecialchars((string)$c), (string)(int)$n]) ?>
        <?php endforeach; ?>
      </table></div><?php endif; ?>
    </div>
  </div>
  <div class="acard">
    <div class="acard-hd"><h3>📄 En çok bakılan sayfalar <span class="ahint">(30 gün)</span></h3></div>
    <div class="acard-body">
      <?php if(!$v30['pages']): ?><div class="aempty">Veri yok.</div>
      <?php else: ?><div class="atscroll"><table class="atable">
        <?= arow(['Sayfa','Görüntüleme'],true) ?>
        <?php foreach(array_slice($v30['pages'],0,15,true) as $p => $n): ?>
        <?= arow(['<code style="font-size:11.5px">'.htmlspecialchars((string)$p).'</code>', (string)(int)$n]) ?>
        <?php endforeach; ?>
      </table></div><?php endif; ?>
    </div>
  </div>
</div>

<script>
/* Canli sayilar ancak taze olursa ise yarar; sekme acik kalirsa kendini tazeler.
   Sadece bu sekmede -- panelin geri kalani yenilenmez. */
setTimeout(function(){ location.reload(); }, 60000);
</script>

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
