<?php
/**
 * ChatHelp — Token paketi satın alma (Stripe Checkout, tek seferlik ödeme)
 * ========================================================================
 * GET /chat/tokens-checkout.php?pack=10k|50k|200k[&email=...]
 * Stripe REST (SDK yok). Paket → Price ID eşlemesi db.php'de:
 *   define('STRIPE_TOKEN_PRICES', ['10k'=>'price_...','50k'=>'price_...','200k'=>'price_...']);
 * Stripe'ta her Price'ın metadata'sına  tokens=10000  gibi miktar yaz → webhook oradan okur.
 * Mevcut stripe-checkout.php'ye DOKUNMAZ (izole).
 */
require_once __DIR__.'/db.php';
$sk = defined('STRIPE_SK') ? STRIPE_SK : (defined('STRIPE_SECRET') ? STRIPE_SECRET : '');
$prices = defined('STRIPE_TOKEN_PRICES') ? STRIPE_TOKEN_PRICES : [];
$pack = preg_replace('/[^0-9a-z]/','',strtolower($_GET['pack'] ?? ''));
if(!$sk){ http_response_code(500); exit('Stripe anahtarı (STRIPE_SK) db.php\'de tanımlı değil.'); }
if(!isset($prices[$pack])){ http_response_code(400); exit('Geçersiz paket. STRIPE_TOKEN_PRICES içinde tanımla: '.implode(', ',array_keys($prices))); }

$params = [
  'mode'=>'payment',
  'line_items[0][price]'=>$prices[$pack], 'line_items[0][quantity]'=>1,
  'success_url'=>'https://chat-help.com/chat/?tokens=success&session_id={CHECKOUT_SESSION_ID}',
  'cancel_url'=>'https://chat-help.com/#tokens',
  'metadata[type]'=>'tokens', 'metadata[pack]'=>$pack,
  'allow_promotion_codes'=>'true', 'automatic_tax[enabled]'=>'true', 'tax_id_collection[enabled]'=>'true',
  'invoice_creation[enabled]'=>'true',
];
if(!empty($_GET['email']) && filter_var($_GET['email'],FILTER_VALIDATE_EMAIL)) $params['customer_email']=$_GET['email'];

$ch=curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params),CURLOPT_USERPWD=>$sk.':',CURLOPT_TIMEOUT=>20]);
$res=json_decode(curl_exec($ch),true); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($code>=200 && $code<300 && !empty($res['url'])){ header('Location: '.$res['url'],true,303); exit; }
http_response_code(502); header('Content-Type: text/plain; charset=UTF-8');
echo "Stripe Checkout oluşturulamadı (HTTP $code): ".($res['error']['message'] ?? 'bilinmeyen hata');
