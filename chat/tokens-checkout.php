<?php
/**
 * ChatHelp — Stripe Checkout: TOKEN paketi, SERBEST miktar veya AYLIK PAKET/AGENT
 * ==============================================================================
 *   /chat/tokens-checkout.php?pack=10k|50k|200k        → sabit token paketi (Stripe Price ID, STRIPE_TOKEN_PRICES)
 *   /chat/tokens-checkout.php?tokens=37000[&email=..]  → istediği miktar (kademeli tarife, price_data, tek seferlik)
 *   /chat/tokens-checkout.php?plan=agent-support       → AYLIK abonelik (plans.php; price_data + recurring; dahil tokenlar her ay yüklenir)
 * db.php: define('STRIPE_SK','sk_live_…')  (alternatif adlar: STRIPE_SECRET, STRIPE_SECRET_KEY)
 *         define('STRIPE_TOKEN_PRICES', ['10k'=>'price_…','50k'=>'price_…','200k'=>'price_…']);  // sabit paketler için (isteğe bağlı)
 * Kademeli tarife siteyle BİREBİR aynı (tk_unit). Metadata webhook tarafından okunur. Mevcut stripe-checkout.php'ye DOKUNMAZ.
 */
require_once __DIR__.'/db.php';
require_once __DIR__.'/plans.php';
$sk = defined('STRIPE_SK') ? STRIPE_SK : (defined('STRIPE_SECRET') ? STRIPE_SECRET : (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : ''));
if(!$sk){ http_response_code(500); exit('Stripe anahtarı (STRIPE_SK / STRIPE_SECRET / STRIPE_SECRET_KEY) db.php\'de tanımlı değil.'); }

function tk_unit(int $n): float { return $n>=1000000?0.0025:($n>=200000?0.00345:($n>=50000?0.0040:($n>=10000?0.0049:0.0055))); }
const PACKS = ['10k'=>10000,'50k'=>50000,'200k'=>200000];
const SITE  = 'https://chat-help.com';

$prices = defined('STRIPE_TOKEN_PRICES') ? STRIPE_TOKEN_PRICES : [];
$pack   = preg_replace('/[^0-9a-z]/','',strtolower($_GET['pack'] ?? ''));
$planId = preg_replace('/[^a-z0-9-]/','',strtolower($_GET['plan'] ?? ''));
$req    = (int)preg_replace('/\D/','',(string)($_GET['tokens'] ?? ''));
$email  = (!empty($_GET['email']) && filter_var($_GET['email'],FILTER_VALIDATE_EMAIL)) ? $_GET['email'] : '';

$params = [
  'cancel_url'=>SITE.'/#tokens',
  'allow_promotion_codes'=>'true','automatic_tax[enabled]'=>'true','tax_id_collection[enabled]'=>'true',
  'line_items[0][quantity]'=>1,
];
if($email) $params['customer_email']=$email;

if($planId !== ''){                                                    /* ---- AYLIK PAKET / AGENT ---- */
  $pl = ch_plan($planId);
  if(!$pl){ http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); exit("Bilinmeyen paket: $planId\nMevcut: ".implode(', ',array_keys(CH_PLANS))); }
  $params['mode']='subscription';
  $params['success_url']=SITE.'/chat/agent.php?welcome='.$pl['id'].'&session_id={CHECKOUT_SESSION_ID}';
  $params['line_items[0][price_data][currency]']='eur';
  $params['line_items[0][price_data][unit_amount]']=$pl['cents'];
  $params['line_items[0][price_data][recurring][interval]']='month';
  $params['line_items[0][price_data][tax_behavior]']='exclusive';
  $params['line_items[0][price_data][product_data][name]']='ChatHelp '.($pl['kind']==='agent'?'Agent':'Paket').': '.$pl['name'];
  $params['line_items[0][price_data][product_data][description]']=$pl['desc'].' · inkl. '.number_format($pl['tokens'],0,',','.').' Tokens / Monat · monatlich kündbar';
  foreach(['metadata','subscription_data[metadata]'] as $m){
    $params[$m.'[type]']='plan'; $params[$m.'[plan]']=$pl['id']; $params[$m.'[tokens]']=$pl['tokens']; $params[$m.'[kind]']=$pl['kind'];
  }
} elseif($req>0){                                                      /* ---- SERBEST TOKEN MİKTARI ---- */
  $n=max(1000,min(5000000,(int)(round($req/1000)*1000)));
  $cents=(int)round($n*tk_unit($n)*100);
  $params['mode']='payment'; $params['invoice_creation[enabled]']='true';
  $params['success_url']=SITE.'/chat/agent.php?tokens=success&session_id={CHECKOUT_SESSION_ID}';
  $params['line_items[0][price_data][currency]']='eur';
  $params['line_items[0][price_data][unit_amount]']=$cents;
  $params['line_items[0][price_data][tax_behavior]']='exclusive';
  $params['line_items[0][price_data][product_data][name]']='ChatHelp Tokens – '.number_format($n,0,',','.');
  $params['line_items[0][price_data][product_data][description]']=number_format(tk_unit($n),4,',','.').' € / Token · gültig für alle Pakete, Agenten & API · verfallen nicht';
  $params['metadata[type]']='tokens'; $params['metadata[tokens]']=$n; $params['metadata[pack]']='custom';
} elseif(isset($prices[$pack])){                                       /* ---- SABİT PAKET (Price ID) ---- */
  $params['mode']='payment'; $params['invoice_creation[enabled]']='true';
  $params['success_url']=SITE.'/chat/agent.php?tokens=success&session_id={CHECKOUT_SESSION_ID}';
  $params['line_items[0][price]']=$prices[$pack];
  $params['metadata[type]']='tokens'; $params['metadata[tokens]']=PACKS[$pack] ?? 0; $params['metadata[pack]']=$pack;
} elseif(isset(PACKS[$pack])){                                         /* ---- SABİT PAKET (Price ID tanımlı değilse price_data) ---- */
  $n=PACKS[$pack]; $cents=(int)round($n*tk_unit($n)*100);
  $params['mode']='payment'; $params['invoice_creation[enabled]']='true';
  $params['success_url']=SITE.'/chat/agent.php?tokens=success&session_id={CHECKOUT_SESSION_ID}';
  $params['line_items[0][price_data][currency]']='eur';
  $params['line_items[0][price_data][unit_amount]']=$cents;
  $params['line_items[0][price_data][tax_behavior]']='exclusive';
  $params['line_items[0][price_data][product_data][name]']='ChatHelp Tokens – '.number_format($n,0,',','.').' ('.strtoupper($pack).')';
  $params['metadata[type]']='tokens'; $params['metadata[tokens]']=$n; $params['metadata[pack]']=$pack;
} else { http_response_code(400); header('Content-Type: text/plain; charset=UTF-8'); exit('Geçersiz istek. ?tokens=N (min 1000) · ?pack=10k|50k|200k · ?plan=<id>'); }

$ch=curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params),CURLOPT_USERPWD=>$sk.':',CURLOPT_TIMEOUT=>20]);
$res=json_decode(curl_exec($ch),true); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($code>=200 && $code<300 && !empty($res['url'])){ header('Location: '.$res['url'],true,303); exit; }
http_response_code(502); header('Content-Type: text/plain; charset=UTF-8');
echo "Stripe Checkout oluşturulamadı (HTTP $code): ".($res['error']['message'] ?? 'bilinmeyen hata');
