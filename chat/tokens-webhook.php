<?php
/**
 * ChatHelp — Stripe webhook: Token yükleme + Paket/Agent abonelikleri
 * ====================================================================
 * Stripe Dashboard → Webhooks → endpoint: https://chat-help.com/chat/tokens-webhook.php
 *   olaylar: checkout.session.completed, invoice.paid, customer.subscription.deleted, customer.subscription.updated
 *   signing secret → db.php: define('STRIPE_TOKENS_WEBHOOK_SECRET','whsec_...');  (alternatif: STRIPE_WEBHOOK_SECRET)
 * Akış:
 *   checkout.session.completed  type=tokens → defter +N (idempotent: session id) → API anahtarı yoksa üret → e-posta
 *                               type=plan   → abonelik kaydı (token_plans) + ilk ay tokenları (+N) → anahtar → e-posta (agent kurulumu)
 *   invoice.paid (subscription_cycle) → her yenilemede aylık tokenlar (+N, idempotent: invoice id)
 *   customer.subscription.deleted/updated → token_plans durumu
 * Mevcut stripe-webhook.php'ye DOKUNMAZ.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
$secret = defined('STRIPE_TOKENS_WEBHOOK_SECRET') ? STRIPE_TOKENS_WEBHOOK_SECRET : (defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '');
$sk     = defined('STRIPE_SK') ? STRIPE_SK : (defined('STRIPE_SECRET') ? STRIPE_SECRET : (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : ''));
$payload = file_get_contents('php://input'); $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

function stripe_sig_ok(string $payload, string $sigHeader, string $secret): bool {
    if(!$secret || !$sigHeader) return false;
    $t=null; $v1=[]; foreach(explode(',',$sigHeader) as $kv){ [$k,$v]=array_pad(explode('=',$kv,2),2,''); if($k==='t')$t=$v; if($k==='v1')$v1[]=$v; }
    if(!$t || !$v1 || abs(time()-(int)$t) > 300) return false;
    $exp=hash_hmac('sha256',"$t.$payload",$secret);
    foreach($v1 as $s){ if(hash_equals($exp,$s)) return true; } return false;
}
function stripe_get(string $path, string $sk): array {
    $ch=curl_init('https://api.stripe.com/v1/'.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERPWD=>$sk.':',CURLOPT_TIMEOUT=>20]);
    $r=json_decode(curl_exec($ch),true); curl_close($ch); return is_array($r)?$r:[];
}
function done(array $out): void { http_response_code(200); header('Content-Type: application/json'); echo json_encode($out); exit; }

if(!stripe_sig_ok($payload,$sig,$secret)){ http_response_code(400); exit('bad signature'); }
$ev=json_decode($payload,true); $type=$ev['type']??''; $o=$ev['data']['object']??[];
th_bootstrap();

/* ============ 1) Checkout tamamlandı ============ */
if($type==='checkout.session.completed'){
    $mtype=$o['metadata']['type']??'';
    $email=$o['customer_details']['email'] ?? ($o['customer_email'] ?? '');
    if(!$email) done(['ok'=>true,'skip'=>'no email']);
    $acc=th_account($email); $aid=(int)$acc['id'];

    if($mtype==='tokens'){
        if(($o['payment_status']??'')!=='paid') done(['ok'=>true,'skip'=>'not paid']);
        $tokens=(int)($o['metadata']['tokens']??0);
        if($tokens<=0 && $sk){                                     // yedek: line item price metadata
            $li=stripe_get('checkout/sessions/'.$o['id'].'/line_items?expand[]=data.price.product',$sk);
            foreach(($li['data']??[]) as $item){ $q=(int)($item['quantity']??1); $tokens += $q*(int)($item['price']['metadata']['tokens'] ?? ($item['price']['product']['metadata']['tokens'] ?? 0)); }
        }
        if($tokens<=0) $tokens = ['10k'=>10000,'50k'=>50000,'200k'=>200000][$o['metadata']['pack']??''] ?? 0;
        if($tokens<=0){ error_log('CH tokens-webhook: miktar yok '.$o['id']); done(['ok'=>true,'skip'=>'no amount']); }
        $credited=th_credit($aid,$tokens,'stripe:tokens',$o['id']);
        $newKey=(!th_has_key($aid)) ? th_issue_key($aid,'auto') : null;
        $bal=th_balance($aid);
        if($credited) th_mail($email,'ChatHelp: '.number_format($tokens,0,',','.').' Tokens gutgeschrieben'.($newKey?' + dein API-Key':''), mail_tokens($tokens,$bal,$newKey));
        done(['ok'=>true,'credited'=>$credited,'tokens'=>$tokens,'balance'=>$bal,'key_issued'=>(bool)$newKey]);
    }

    if($mtype==='plan'){
        $pl=ch_plan($o['metadata']['plan']??''); if(!$pl) done(['ok'=>true,'skip'=>'unknown plan']);
        if(!in_array($o['payment_status']??'',['paid','no_payment_required'],true)) done(['ok'=>true,'skip'=>'not paid']);
        $subId=$o['subscription']??''; $periodEnd=null;
        if($subId && $sk){ $sub=stripe_get('subscriptions/'.$subId,$sk); if(!empty($sub['current_period_end'])) $periodEnd=date('Y-m-d H:i:s',(int)$sub['current_period_end']); }
        th_plan_upsert($aid,$pl['id'],$subId,'active',$periodEnd);
        $credited=th_credit($aid,$pl['tokens'],'stripe:plan',$o['id']);
        $newKey=(!th_has_key($aid)) ? th_issue_key($aid,'auto') : null;
        $bal=th_balance($aid);
        th_mail($email,'ChatHelp: '.$pl['name'].' ist aktiv ✅', mail_plan($pl,$bal,$newKey,true));
        if($pl['kind']==='agent') th_mail(defined('SMTP_FROM')?SMTP_FROM:'info@chat-help.com','[ChatHelp] Neuer Agent gebucht: '.$pl['name'],'<p>Kunde: '.htmlspecialchars($email).'<br>Agent: '.$pl['id'].'<br>Subscription: '.$subId.'</p><p>Onboarding innerhalb 24h: Wissensbasis/Konten anbinden.</p>');
        done(['ok'=>true,'plan'=>$pl['id'],'credited'=>$credited,'balance'=>$bal,'key_issued'=>(bool)$newKey]);
    }
    done(['ok'=>true,'skip'=>'other checkout']);
}

/* ============ 2) Abonelik yenilendi → aylık tokenlar ============ */
if($type==='invoice.paid'){
    if(($o['billing_reason']??'')!=='subscription_cycle') done(['ok'=>true,'skip'=>'not a renewal']);   // ilk ay checkout'ta yüklendi
    $subId=$o['subscription']??''; $email=$o['customer_email']??'';
    if(!$subId || !$sk) done(['ok'=>true,'skip'=>'no subscription']);
    $sub=stripe_get('subscriptions/'.$subId,$sk);
    $pl=ch_plan($sub['metadata']['plan']??''); if(!$pl) done(['ok'=>true,'skip'=>'no plan metadata']);
    if(!$email && !empty($sub['customer'])){ $cu=stripe_get('customers/'.$sub['customer'],$sk); $email=$cu['email']??''; }
    if(!$email) done(['ok'=>true,'skip'=>'no email']);
    $acc=th_account($email); $aid=(int)$acc['id'];
    $periodEnd=!empty($sub['current_period_end'])?date('Y-m-d H:i:s',(int)$sub['current_period_end']):null;
    th_plan_upsert($aid,$pl['id'],$subId,'active',$periodEnd);
    $credited=th_credit($aid,$pl['tokens'],'stripe:renewal',$o['id']);
    if($credited) th_mail($email,'ChatHelp: '.number_format($pl['tokens'],0,',','.').' Tokens für '.$pl['name'].' gutgeschrieben', mail_plan($pl,th_balance($aid),null,false));
    done(['ok'=>true,'renewal'=>$pl['id'],'credited'=>$credited]);
}

/* ============ 3) Abonelik iptal / değişti ============ */
if($type==='customer.subscription.deleted' || $type==='customer.subscription.updated'){
    $subId=$o['id']??''; $status=$o['status']??''; // active, past_due, canceled, unpaid, trialing
    if($type==='customer.subscription.deleted') $status='canceled';
    if($subId) th_plan_status($subId,$status, !empty($o['current_period_end'])?date('Y-m-d H:i:s',(int)$o['current_period_end']):null);
    done(['ok'=>true,'sub'=>$subId,'status'=>$status]);
}
done(['ok'=>true,'ignored'=>$type]);

/* ---------------- e-posta şablonları ---------------- */
function mail_tokens(int $tokens, int $bal, ?string $newKey): string {
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto;color:#111">'
      .'<h2 style="color:#b0801f">Danke! '.number_format($tokens,0,',','.').' Tokens gutgeschrieben ✅</h2>'
      .'<p>Dein ChatHelp-Guthaben: <b>'.number_format($bal,0,',','.').' Tokens</b>. Nutzbar in allen Paketen, Agenten und per API.</p>'
      .key_block($newKey)
      .'<p><a href="https://chat-help.com/chat/agent.php" style="background:#b0801f;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">Zum Arbeitsbereich →</a></p>'
      .'<p style="color:#666;font-size:13px">Doku: <a href="https://chat-help.com/#api">chat-help.com/#api</a> · Fragen: info@chat-help.com</p></div>';
}
function mail_plan(array $pl, int $bal, ?string $newKey, bool $first): string {
    $agent = $pl['kind']==='agent';
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto;color:#111">'
      .'<h2 style="color:#b0801f">'.($first?'Willkommen! ':'').htmlspecialchars($pl['name']).' ist aktiv ✅</h2>'
      .'<p>'.number_format($pl['tokens'],0,',','.').' Tokens wurden gutgeschrieben. Guthaben: <b>'.number_format($bal,0,',','.').' Tokens</b>. Jeden Monat kommen '.number_format($pl['tokens'],0,',','.').' neue dazu.</p>'
      .($agent ? '<p><b>So startest du deinen Agenten:</b><br>1) Arbeitsbereich öffnen und mit deinem API-Key anmelden.<br>2) Wissensbasis einfügen (FAQ, Produkte, Öffnungszeiten, Tonalität).<br>3) Testen – und per Website-Widget oder E-Mail/Chat einbinden. Wir melden uns innerhalb von 24 Stunden für die Einrichtung.</p>'
               : '<p>Öffne den Arbeitsbereich, wähle dein Paket und starte mit den fertigen Vorlagen.</p>')
      .key_block($newKey)
      .'<p><a href="https://chat-help.com/chat/agent.php?plan='.$pl['id'].'" style="background:#b0801f;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">Arbeitsbereich öffnen →</a></p>'
      .'<p style="color:#666;font-size:13px">Monatlich kündbar · Rechnung von Stripe · Fragen: info@chat-help.com</p></div>';
}
function key_block(?string $newKey): string {
    return $newKey
      ? '<p><b>Dein API-Key</b> (nur einmal sichtbar – sicher speichern; damit meldest du dich auch im Arbeitsbereich an):</p><pre style="background:#f4f4f8;padding:14px;border-radius:10px;font-size:14px">'.$newKey.'</pre>'
      : '<p>Dein bestehender API-Key bleibt gültig – damit meldest du dich im Arbeitsbereich an.</p>';
}
