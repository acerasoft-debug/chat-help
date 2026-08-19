<?php
/**
 * ChatHelp — apply-shipgate (CH_SHIPGATE) — 'Original an meine Adresse' icin GERCEK Stripe tahsilati.
 *  Akis: butona bas -> mektup icerigi ch_ship_pending'e saklanir -> Stripe (plan bazli 1,50/1,99/2,99 €,
 *  fiyat SUNUCUDA sabit tablodan) -> odeme basarili donuste mektup OTOMATIK gonderilir.
 *  Odeme yoksa mektup GITMEZ. Belge kredisiyle (addCredit) KARISMAZ (metadata[ship] ile ayrilir).
 *
 *  4 duzenleme (once TUMU dogrulanir, biri bile tutmazsa HICBIR SEY yazilmaz):
 *   [S1] stripe-checkout.php: verify cevabina 'ship' alani
 *   [S2] stripe-checkout.php: type==='ship' -> dinamik price_data ile tek seferlik odeme
 *   [C1] index.php: doHome -> gonderim yerine Stripe'a yonlendir (icerik saklanir) + return
 *   [C2] index.php: odeme-donus handler -> d.ship ise kredi verme, sakli mektubu gonder
 * KULLANIM: /chat/pull2.php?key=...&files=apply-shipgate.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-shipgate BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$scF=__DIR__.'/stripe-checkout.php';
$ixF=__DIR__.'/index.php';
$sc=@file_get_contents($scF);
$ix=@file_get_contents($ixF);
if($sc===false||$ix===false) exit("dosyalar okunamadi\n");
if(strpos($sc,'CH_SHIPGATE')!==false || strpos($ix,'CH_SHIPGATE')!==false) exit("Zaten uygulandi (CH_SHIPGATE).\n");

/* ── capalar ── */
$s1o = "'doc_type'=>\$vs['metadata']['doc_type']??'', 'mode'=>\$vs['mode']??'']";
$s1n = "'doc_type'=>\$vs['metadata']['doc_type']??'', 'mode'=>\$vs['mode']??'', 'ship'=>\$vs['metadata']['ship']??'']";

$s2o = "\$priceId = \$priceMap[\$plan] ?? null;";
$s2block = <<<'S2B'
/* CH_SHIPGATE — gonderim ucreti: plan bazli sabit fiyat (sunucuda), dinamik price_data, tek seferlik */
if ($type === 'ship') {
    $shipPrices = ['free'=>299,'basic'=>199,'pro'=>150,'elite'=>150];
    $sp = $shipPrices[strtolower((string)$plan)] ?? 299;
    $sparams = [
        'mode' => 'payment',
        'success_url' => $domain.'/?payment=success&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $domain.'/?payment=cancel',
        'line_items[0][price_data][currency]' => 'eur',
        'line_items[0][price_data][product_data][name]' => 'Postversand — Original nach Hause (ChatHelp)',
        'line_items[0][price_data][unit_amount]' => (string)$sp,
        'line_items[0][quantity]' => '1',
        'metadata[ship]'   => 'wethome',
        'metadata[plan]'   => (string)$plan,
        'metadata[domain]' => $host,
    ];
    if ($email) $sparams['customer_email'] = $email;
    $sch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($sch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query($sparams), CURLOPT_USERPWD=>STRIPE_SECRET.':',
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'], CURLOPT_TIMEOUT=>15]);
    $sresp = json_decode(curl_exec($sch), true); curl_close($sch);
    echo json_encode(isset($sresp['url']) ? ['url'=>$sresp['url']] : ['error'=>$sresp['error']['message'] ?? 'Stripe error']);
    exit;
}

S2B;

$c1o = "localStorage.setItem('ch_mailorig_req',JSON.stringify(a.slice(-50))); }catch(e){}";
$c1gate = <<<'C1B'

    /*CH_SHIPGATE — once odeme: icerik saklanir, Stripe'a gidilir; basarili donuste otomatik gonderilir*/
    try{ localStorage.setItem('ch_ship_pending', JSON.stringify({text:bodyVal(),recipient:own,sender:sender,ts:new Date().getTime()})); }catch(__e1){}
    var __shbtn=document.getElementById('ai-home'); if(__shbtn){ __shbtn.disabled=true; __shbtn.textContent='⏳ …'; }
    fetch('stripe-checkout.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'ship',plan:(function(){try{return plan();}catch(__e2){return 'free';}})(),email:(((typeof prof==='function'?prof():{})||{}).f7||'')})})
      .then(function(r){return r.json();})
      .then(function(d){ if(d&&d.url){ location.href=d.url; } else { if(__shbtn){ __shbtn.disabled=false; } try{ alert(T('senderr')+((d&&d.error)||'Stripe')); }catch(__e3){} } })
      .catch(function(){ if(__shbtn){ __shbtn.disabled=false; } try{ alert(T('senderr')+'network'); }catch(__e4){} });
    return;
C1B;

$c2o = "if(d && d.paid && d.mode==='payment'){";
$c2n = "if(d && d.paid && d.mode==='payment' && d.ship){ /*CH_SHIPGATE — gonderim odemesi: kredi YOK, sakli mektup gonderilir*/ sessionStorage.setItem('ch_paid_'+sid,'1'); try{ var __sp=JSON.parse(localStorage.getItem('ch_ship_pending')||'null'); if(__sp&&__sp.text){ localStorage.removeItem('ch_ship_pending'); toast('✅ Zahlung erfolgreich — Ihr Brief wird jetzt versandt …'); fetch('postversand.php?action=send_letter',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({text:__sp.text,recipient:__sp.recipient,sender:__sp.sender})}).then(function(r){return r.json();}).then(function(j){ toast((j&&j.ok)?'✅ Brief versandt — das Original kommt per Post zu Ihnen.':'⚠️ Versand fehlgeschlagen — bitte den Support kontaktieren.'); }).catch(function(){ toast('⚠️ Versand fehlgeschlagen — bitte erneut versuchen.'); }); } else { toast('✅ Zahlung erfolgreich.'); } }catch(__se){} } else if(d && d.paid && d.mode==='payment'){";

/* ── ONCE TUM CAPALARI SAY ── */
$cs1=substr_count($sc,$s1o);
$cs2=substr_count($sc,$s2o);
$cc1=substr_count($ix,$c1o);
$cc2=substr_count($ix,$c2o);
echo "  capa sayimlari: S1=$cs1 S2=$cs2 C1=$cc1 C2=$cc2 (hepsi 1 olmali)\n";
if($cs1!==1||$cs2!==1||$cc1!==1||$cc2!==1){
  echo "  ✗ Capalar tam eslesmiyor — HICBIR SEY DEGISTIRILMEDI.\n"; exit;
}

/* ── uygula ── */
$sc2=str_replace($s1o,$s1n,$sc);
$sc2=str_replace($s2o,$s2block.$s2o,$sc2);
$ix2=str_replace($c1o,$c1o.$c1gate,$ix);
$ix2=str_replace($c2o,$c2n,$ix2);

/* ── lint ikisi de ── */
foreach([['sc',$sc2],['ix',$ix2]] as $pair){
  $tmp=tempnam(sys_get_temp_dir(),'sg'.$pair[0]).'.php'; file_put_contents($tmp,$pair[1]);
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
  if($rc!==0){ echo "  ✗ LINT HATASI (".$pair[0].") — HICBIR SEY YAZILMADI:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
echo "  ✓ iki dosya da lint temiz\n";

/* ── yaz ── */
@file_put_contents($scF.'.bak-shipgate-'.date('Ymd-His'),$sc);
@file_put_contents($ixF.'.bak-shipgate-'.date('Ymd-His'),$ix);
$w1=@file_put_contents($scF,$sc2);
$w2=@file_put_contents($ixF,$ix2);
if($w1===false||$w2===false) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$k1=(string)@file_get_contents($scF); $k2=(string)@file_get_contents($ixF);
if(strpos($k1,'CH_SHIPGATE')===false||strpos($k2,'CH_SHIPGATE')===false) exit("  ✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_SHIPGATE canli (stripe-checkout ".strlen($k1)." B, index ".strlen($k2)." B)\n";
echo "  · 'Original an meine Adresse' artik ONCE ODEME ister (free 2,99 · basic 1,99 · pro/elite 1,50 €).\n";
echo "  · Odeme basarili donuste mektup otomatik gonderilir; odemesiz mektup GITMEZ.\n";
echo "  · Belge kredisi akisiyla karismaz (metadata ship).\n";
echo "  TEST: butona bas -> Stripe acilmali -> odeme sonrasi '✅ Brief versandt' gorunmeli.\n";
