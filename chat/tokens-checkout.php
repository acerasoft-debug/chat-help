<?php
/**
 * ChatHelp — Token satın alma (Stripe Checkout, tek seferlik) — PAKET veya SERBEST MİKTAR
 * ======================================================================================
 *   /chat/tokens-checkout.php?pack=10k|50k|200k        → sabit paket (Stripe Price ID)
 *   /chat/tokens-checkout.php?tokens=37000[&email=..]  → istediği miktar (kademeli tarife, price_data)
 * db.php: define('STRIPE_SK','sk_live_…');
 *         define('STRIPE_TOKEN_PRICES', ['10k'=>'price_…','50k'=>'price_…','200k'=>'price_…']);  // paketler için
 * Kademeli tarife siteyle BİREBİR aynı (tk_unit). Token miktarı session metadata'ya yazılır → webhook oradan okur.
 * Mevcut stripe-checkout.php'ye DOKUNMAZ.
 */
require_once __DIR__.'/db.php';
$sk = defined('STRIPE_SK') ? STRIPE_SK : (defined('STRIPE_SECRET') ? STRIPE_SECRET : '');
if(!$sk){ http_response_code(500); exit('Stripe anahtarı (STRIPE_SK) db.php\'de tanımlı değil.'); }

function tk_unit(int $n): float { return $n>=1000000?0.0025:($n>=200000?0.00345:($n>=50000?0.0040:($n>=10000?0.0049:0.0055))); }
const PACKS = ['10k'=>10000,'50k'=>50000,'200k'=>200000];

$prices = defined('STRIPE_TOKEN_PRICES') ? STRIPE_TOKEN_PRICES : [];
$pack   = preg_replace('/[^0-9a-z]/','',strtolower($_GET['pack'] ?? ''));
$req    = (int)preg_replace('/\D/','',(string)($_GET['tokens'] ?? ''));

$params = [
  'mode'=>'payment','success_url'=>'https://chat-help.com/chat/?tokens=success&session_id={CHECKOUT_SESSION_ID}',
  'cancel_url'=>'https://chat-help.com/#tokens','metadata[type]'=>'tokens',
  'allow_promotion_codes'=>'true','automatic_tax[enabled]'=>'true','tax_id_collection[enabled]'=>'true','invoice_creation[enabled]'=>'true',
  'line_items[0][quantity]'=>1,
];
if($req>0){                                            // serbest miktar
  $n=max(1000,min(5000000,(int)(round($req/1000)*1000)));
  $cents=(int)round($n*tk_unit($n)*100);
  $params['line_items[0][price_data][currency]']='eur';
  $params['line_items[0][price_data][unit_amount]']=$cents;
  $params['line_items[0][price_data][tax_behavior]']='exclusive';
  $params['line_items[0][price_data][product_data][name]']='ChatHelp Tokens – '.number_format($n,0,',','.');
  $params['line_items[0][price_data][product_data][description]']=number_format(tk_unit($n),4,',','.').' € / Token · gültig für alle Dienste & API · verfallen nicht';
  $params['metadata[tokens]']=$n; $params['metadata[pack]']='custom';
} elseif(isset($prices[$pack])){                       // sabit paket
  $params['line_items[0][price]']=$prices[$pack];
  $params['metadata[tokens]']=PACKS[$pack] ?? 0; $params['metadata[pack]']=$pack;
} else { http_response_code(400); exit('Geçersiz istek. ?tokens=N (min 1000) veya ?pack='.implode('|',array_keys($prices ?: PACKS))); }
if(!empty($_GET['email']) && filter_var($_GET['email'],FILTER_VALIDATE_EMAIL)) $params['customer_email']=$_GET['email'];

$ch=curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params),CURLOPT_USERPWD=>$sk.':',CURLOPT_TIMEOUT=>20]);
$res=json_decode(curl_exec($ch),true); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($code>=200 && $code<300 && !empty($res['url'])){ header('Location: '.$res['url'],true,303); exit; }
http_response_code(502); header('Content-Type: text/plain; charset=UTF-8');
echo "Stripe Checkout oluşturulamadı (HTTP $code): ".($res['error']['message'] ?? 'bilinmeyen hata');
