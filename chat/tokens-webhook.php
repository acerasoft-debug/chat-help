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
 *                                 kind=buy     → Lizenz + Startguthaben + CH_BUY_INCLUDED_DAYS Tage Betrieb (bl_license_activate)
 *                                 kind=betrieb|byok → Folge-Abo zur Lizenz (status trialing während des Inklusiv-Betriebs, Tokens erst mit bezahlter Rechnung);
 *                                                     das jeweils andere Folge-Abo derselben Lizenz wird zum Periodenende gekündigt
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
    $ch=curl_init(bl_stripe_base().'/'.$path);   // billing-lib (CH_STRIPE_API für Tests)
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
        $subId=$o['subscription']??''; $periodEnd=null; $subStatus='active'; $run=in_array($pl['kind'],['betrieb','byok'],true);
        if($subId && $sk){ $sub=stripe_get('subscriptions/'.$subId,$sk); if(!empty($sub['current_period_end'])) $periodEnd=date('Y-m-d H:i:s',(int)$sub['current_period_end']);
            if(in_array($sub['status'] ?? '',['active','trialing','past_due'],true)) $subStatus=(string)$sub['status']; }
        $until=null;
        if($pl['kind']==='buy'){ $tm=isset($o['metadata']['term']) ? ch_buy_term($pl['id'],(int)$o['metadata']['term']) : null;   // Laufzeitwahl: Tage aus CH_BUY_TERMS (nicht aus den Metadaten)
            $until=bl_license_activate($aid,$pl['id'],$tm ? (int)$tm['days'] : 0); }   // Lizenz + Inklusiv-Betrieb (Bestandskauf ohne Enddatum bleibt unbefristet)
        else th_plan_upsert($aid,$pl['id'],$subId,$subStatus,$periodEnd);
        /* Betrieb und eigener Schlüssel schließen sich aus: das andere Folge-Abo derselben Lizenz endet zum Periodenende */
        if($run && $sk){ $other=($pl['kind']==='betrieb' ? 'byok-' : 'betrieb-').substr((string)$pl['license'],4);
            $st=th_db()->prepare("SELECT stripe_sub FROM token_plans WHERE account_id=? AND plan=? AND status IN ('active','trialing','past_due') AND stripe_sub IS NOT NULL"); $st->execute([$aid,$other]);
            foreach($st->fetchAll(PDO::FETCH_COLUMN) as $os){ $cr=bl_stripe_call('POST','subscriptions/'.$os,$sk,['cancel_at_period_end'=>'true']); if(($cr['_http'] ?? 0)>=300) error_log('CH tokens-webhook: Folge-Abo '.$os.' nicht gekündigt'); } }
        /* Tokens: sofort, außer bei Folge-Abos in der kostenlosen Anlaufzeit (die kommen mit der ersten bezahlten Rechnung) */
        $credited=($pl['tokens']>0 && !($run && $subStatus==='trialing')) ? th_credit($aid,$pl['tokens'],'stripe:plan',$o['id']) : false;
        $newKey=(!th_has_key($aid)) ? th_issue_key($aid,'auto') : null;
        $bal=th_balance_ledger($aid);
        /* Mails nur beim ersten Eintreffen dieser Checkout-Session (Stripe wiederholt Webhooks) */
        $firstSeen=true; try{ th_db()->exec("CREATE TABLE IF NOT EXISTS stripe_seen (id VARCHAR(80) PRIMARY KEY, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ins=th_db()->prepare("INSERT IGNORE INTO stripe_seen(id) VALUES(?)"); $ins->execute(['plan:'.(string)($o['id'] ?? '')]); $firstSeen=$ins->rowCount()>0; }catch(Throwable $e){ $firstSeen=true; }
        if($firstSeen) th_mail($email,'ChatHelp: '.$pl['name'].' ist aktiv ✅', mail_plan($pl,$bal,$newKey,true,$until,$subStatus));
        if($firstSeen && $pl['kind']==='agent') th_mail(defined('SMTP_FROM')?SMTP_FROM:'support@chat-help.com','[ChatHelp] Neuer Agent gebucht: '.$pl['name'],'<p>Kunde: '.htmlspecialchars($email).'<br>Agent: '.$pl['id'].'<br>Subscription: '.$subId.'</p><p>Onboarding innerhalb 24h: Wissensbasis/Konten anbinden.</p>');
        /* Einrichtung: Datensatz je Checkout-Session + Einrichtungs-Anleitung (Mail mit „Einrichtung starten“ + App-Download) + Push – Wiederholungen senden nichts doppelt */
        $onb=null; if(is_file(__DIR__.'/onboarding-lib.php')){ try{ require_once __DIR__.'/onboarding-lib.php'; $onb=onb_on_payment($aid,$email,$pl,(string)($o['id'] ?? '')); }catch(Throwable $e){ error_log('CH tokens-webhook onboarding: '.$e->getMessage()); } }
        done(['ok'=>true,'plan'=>$pl['id'],'kind'=>$pl['kind'],'status'=>$pl['kind']==='buy' ? 'active' : $subStatus,'included_until'=>$until,'credited'=>$credited,'balance'=>$bal,'key_issued'=>(bool)$newKey,'onboarding'=>$onb]);
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
    $credited=$pl['tokens']>0 ? th_credit($aid,$pl['tokens'],'stripe:renewal',$o['id']) : false;   // eigener Schlüssel: keine Tokens, nur Plattformgebühr
    if($credited) th_mail($email,'ChatHelp: '.number_format($pl['tokens'],0,',','.').' Tokens für '.$pl['name'].' gutgeschrieben', mail_plan($pl,th_balance_ledger($aid),null,false));
    done(['ok'=>true,'renewal'=>$pl['id'],'credited'=>$credited]);
}

/* ============ 3) Abonelik iptal / değişti ============ */
if($type==='customer.subscription.deleted' || $type==='customer.subscription.updated'){
    $subId=$o['id']??''; $status=$o['status']??''; // active, past_due, canceled, unpaid, trialing
    if($type==='customer.subscription.deleted') $status='canceled';
    if($subId) th_plan_status($subId,$status, !empty($o['current_period_end'])?date('Y-m-d H:i:s',(int)$o['current_period_end']):null);
    /* Gekündigt/unbezahlt: ChatHelp-Nummern dieses Kontos ohne aktives Paket freigeben (sonst zahlen wir sie weiter) */
    if($subId && in_array($status,['canceled','unpaid','incomplete_expired'],true) && is_file(__DIR__.'/number-lib.php')){
        try{ require_once __DIR__.'/number-lib.php'; $q=th_db()->prepare("SELECT DISTINCT account_id FROM token_plans WHERE stripe_sub=?"); $q->execute([$subId]);
            foreach($q->fetchAll(PDO::FETCH_COLUMN) as $na) num_release_inactive((int)$na); }catch(Throwable $e){ error_log('CH tokens-webhook numbers: '.$e->getMessage()); } }
    done(['ok'=>true,'sub'=>$subId,'status'=>$status]);
}
done(['ok'=>true,'ignored'=>$type]);

/* ---------------- e-posta şablonları ---------------- */
function mail_tokens(int $tokens, int $bal, ?string $newKey): string {
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto;color:#111">'
      .'<h2 style="color:#b0801f">Danke! '.number_format($tokens,0,',','.').' Tokens gutgeschrieben ✅</h2>'
      .'<p>Dein ChatHelp-Guthaben: <b>'.number_format($bal,0,',','.').' Tokens</b>. Nutzbar in allen Paketen, Agenten und per API.</p>'
      .key_block($newKey)
      .'<p><a href="https://chat-help.com/chat/studio.php" style="background:#b0801f;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">Zum Arbeitsbereich →</a></p>'
      .'<p style="color:#666;font-size:13px">Doku: <a href="https://chat-help.com/#api">chat-help.com/#api</a> · Fragen: support@chat-help.com</p></div>';
}
function mail_plan(array $pl, int $bal, ?string $newKey, bool $first, ?string $until=null, string $subStatus='active'): string {
    $agent = in_array($pl['kind'],['agent','buy','betrieb','byok'],true); $kind=$pl['kind'];
    $tok=number_format($pl['tokens'],0,',','.'); $h=fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');
    $fu=$kind==='buy' ? ch_followups($pl['id']) : [];
    $line=match($kind){
        'buy'=>'<p>'.$tok.' Tokens Startguthaben wurden gutgeschrieben (sie verfallen nicht). Guthaben: <b>'.number_format($bal,0,',','.').' Tokens</b>.</p>'
              .($until ? '<p>Der Betrieb ist bis <b>'.$h(date('d.m.Y',strtotime($until))).'</b> inklusive. Danach wählst du im Studio: <b>Betrieb</b> für '.$h(number_format(($fu['betrieb']['cents'] ?? 0)/100,0,',','.')).' €/Monat (inkl. '.$h(number_format($fu['betrieb']['tokens'] ?? 0,0,',','.')).' Tokens) oder deinen <b>eigenen KI-Schlüssel</b> (Claude oder DeepSeek) für '.$h(number_format(($fu['byok']['cents'] ?? 0)/100,0,',','.')).' €/Monat – monatlich kündbar. Du kannst schon jetzt buchen, berechnet wird erst ab dem Ende des Inklusiv-Zeitraums.</p>' : '<p>Dein Bestandskauf bleibt unbefristet nutzbar.</p>'),
        'byok'=>'<p>Deine Agenten nutzen deinen eigenen KI-Schlüssel – dafür bucht ChatHelp keine Tokens ab. Schlüssel hinterlegen: Studio → Telefon → Verbindungen.'.($subStatus==='trialing' ? ' Die Plattformgebühr wird erst ab dem Ende deines Inklusiv-Betriebs berechnet.' : '').'</p>',
        'betrieb'=>'<p>'.($subStatus==='trialing' ? 'Der Betrieb schließt nahtlos an deinen Inklusiv-Zeitraum an; ab dann kommen jeden Monat '.$tok.' Tokens dazu.' : $tok.' Tokens wurden gutgeschrieben, jeden Monat kommen '.$tok.' neue dazu.').' Guthaben: <b>'.number_format($bal,0,',','.').' Tokens</b>.</p>',
        default=>'<p>'.$tok.' Tokens wurden gutgeschrieben. Guthaben: <b>'.number_format($bal,0,',','.').' Tokens</b>. Jeden Monat kommen '.$tok.' neue dazu.</p>'};
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto;color:#111">'
      .'<h2 style="color:#b0801f">'.($first?'Willkommen! ':'').$h($pl['name']).' ist aktiv ✅</h2>'
      .$line
      .($agent && in_array($kind,['agent','buy'],true) ? '<p><b>So startest du deinen Agenten:</b><br>1) Arbeitsbereich öffnen und mit deinem API-Key anmelden.<br>2) Wissensbasis einfügen (FAQ, Produkte, Öffnungszeiten, Tonalität).<br>3) Testen – und per Website-Widget, Telefon oder WhatsApp einbinden. Wir melden uns innerhalb von 24 Stunden für die Einrichtung.</p>'
               : ($agent ? '' : '<p>Öffne den Arbeitsbereich, wähle dein Paket und starte mit den fertigen Vorlagen.</p>'))
      .key_block($newKey)
      .'<p><a href="https://chat-help.com/chat/studio.php?plan='.$h($pl['base'] ?? $pl['id']).'" style="background:#b0801f;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">Arbeitsbereich öffnen →</a></p>'
      .'<p style="color:#666;font-size:13px">'.($kind==='buy' ? 'Einmalkauf · Rechnung von Stripe' : 'Monatlich kündbar · Rechnung von Stripe').' · Fragen: support@chat-help.com</p></div>';
}
function key_block(?string $newKey): string {
    return $newKey
      ? '<p><b>Dein API-Key</b> (nur einmal sichtbar – sicher speichern; damit meldest du dich auch im Arbeitsbereich an):</p><pre style="background:#f4f4f8;padding:14px;border-radius:10px;font-size:14px">'.$newKey.'</pre>'
      : '<p>Dein bestehender API-Key bleibt gültig – damit meldest du dich im Arbeitsbereich an.</p>';
}
