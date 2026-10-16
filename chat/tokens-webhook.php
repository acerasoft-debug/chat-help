<?php
/**
 * ChatHelp — Token paketi webhook'u (Stripe → defter yükleme + API anahtarı)
 * ==========================================================================
 * Stripe Dashboard → Webhooks → endpoint ekle:  https://chat-help.com/chat/tokens-webhook.php
 *   olay: checkout.session.completed   → signing secret'ı db.php'ye:
 *   define('STRIPE_TOKENS_WEBHOOK_SECRET','whsec_...');
 * Akış: imza doğrula → metadata.type=tokens mi → Price metadata.tokens miktarını oku →
 *       e-postaya göre hesap → defter +tokens (idempotent: session id) → API anahtarı yoksa üret →
 *       anahtarı + bakiyeyi e-posta ile gönder. Mevcut stripe-webhook.php'ye DOKUNMAZ.
 */
require_once __DIR__.'/tokens-lib.php';
$secret = defined('STRIPE_TOKENS_WEBHOOK_SECRET') ? STRIPE_TOKENS_WEBHOOK_SECRET : (defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '');
$sk     = defined('STRIPE_SK') ? STRIPE_SK : (defined('STRIPE_SECRET') ? STRIPE_SECRET : '');
$payload = file_get_contents('php://input'); $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

/* ---- imza doğrulama (SDK'sız) ---- */
function stripe_sig_ok(string $payload, string $sigHeader, string $secret): bool {
    if(!$secret || !$sigHeader) return false;
    $t=null; $v1=[]; foreach(explode(',',$sigHeader) as $kv){ [$k,$v]=array_pad(explode('=',$kv,2),2,''); if($k==='t')$t=$v; if($k==='v1')$v1[]=$v; }
    if(!$t || !$v1 || abs(time()-(int)$t) > 300) return false;
    $exp=hash_hmac('sha256',"$t.$payload",$secret);
    foreach($v1 as $s){ if(hash_equals($exp,$s)) return true; } return false;
}
if(!stripe_sig_ok($payload,$sig,$secret)){ http_response_code(400); exit('bad signature'); }
$ev=json_decode($payload,true);
if(($ev['type']??'')!=='checkout.session.completed'){ http_response_code(200); exit('ignored'); }
$s=$ev['data']['object'] ?? [];
if(($s['metadata']['type']??'')!=='tokens' || ($s['payment_status']??'')!=='paid'){ http_response_code(200); exit('not a paid token purchase'); }

th_bootstrap();
$email = $s['customer_details']['email'] ?? ($s['customer_email'] ?? '');
if(!$email){ http_response_code(200); exit('no email'); }

/* ---- token miktarı: 1) session metadata.tokens (checkout yazar)  2) price metadata  3) pack adı ---- */
$tokens=(int)($s['metadata']['tokens'] ?? 0);
if($tokens<=0){
$ch=curl_init('https://api.stripe.com/v1/checkout/sessions/'.$s['id'].'/line_items?expand[]=data.price.product');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERPWD=>$sk.':',CURLOPT_TIMEOUT=>20]);
$li=json_decode(curl_exec($ch),true); curl_close($ch);
foreach(($li['data']??[]) as $item){
    $q=(int)($item['quantity']??1);
    $t=(int)($item['price']['metadata']['tokens'] ?? ($item['price']['product']['metadata']['tokens'] ?? 0));
    $tokens += $t*$q;
}
}
if($tokens<=0){ // yedek: pack adından
    $tokens = ['10k'=>10000,'50k'=>50000,'200k'=>200000][$s['metadata']['pack']??''] ?? 0;
}
if($tokens<=0){ error_log('CH tokens-webhook: miktar bulunamadı '.$s['id']); http_response_code(200); exit('no amount'); }

$acc=th_account($email);
$credited=th_credit((int)$acc['id'],$tokens,'stripe:tokens',$s['id']);   // idempotent (aynı session 2. kez → false)
$newKey = (!th_has_key((int)$acc['id'])) ? th_issue_key((int)$acc['id'],'auto') : null;
$bal=th_balance((int)$acc['id']);

if($credited){
    $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto;color:#111">'
      .'<h2 style="color:#b0801f">Danke! '.number_format($tokens,0,',','.').' Tokens gutgeschrieben ✅</h2>'
      .'<p>Dein ChatHelp-Guthaben: <b>'.number_format($bal,0,',','.').' Tokens</b>. Nutzbar in allen Paketen, Agenten und per API.</p>'
      .($newKey ? '<p><b>Dein API-Key</b> (nur einmal sichtbar – sicher speichern):</p><pre style="background:#f4f4f8;padding:14px;border-radius:10px;font-size:14px">'.$newKey.'</pre>'
                 .'<p>Beispiel:<br><code>curl https://chat-help.com/chat/api.php/v1/balance -H "Authorization: Bearer '.$newKey.'"</code></p>' : '<p>Dein bestehender API-Key bleibt gültig.</p>')
      .'<p style="color:#666;font-size:13px">Doku &amp; Sektoren: <a href="https://chat-help.com/#api">chat-help.com/#api</a> · Fragen: api@chat-help.com</p></div>';
    th_mail($email,'ChatHelp: '.number_format($tokens,0,',','.').' Tokens gutgeschrieben'.($newKey?' + dein API-Key':''),$html);
}
http_response_code(200); echo json_encode(['ok'=>true,'credited'=>$credited,'tokens'=>$tokens,'balance'=>$bal,'key_issued'=>(bool)$newKey]);
