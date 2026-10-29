<?php
/**
 * ChatHelp API v1 — TEK giriş noktası, tüm sektörler, gerçek Agent çalışma zamanı
 * ==================================================================================
 *   GET  /chat/tokens-api.php/v1/sectors                                   → sektörler, task'lar, token fiyatları (herkese açık)
 *   GET  /chat/tokens-api.php/v1/categories                                → Hauptkategorien mit Plänen, Preisen, Kanälen, Demo-Schlüssel (öffentlich, 5 Min. Cache)
 *   GET  /chat/tokens-api.php/v1/pricing                                   → Einmalkauf → Betrieb / eigener KI-Schlüssel, Stufen, KI-Listenpreise (öffentlich)
 *        /v1/categories?cur=USD&lang=en · /v1/pricing?cur=TRY               → zusätzlich „local“-Anzeigepreise + display_currency (EUR-Felder unverändert)
 *   GET  /v1/billing · GET /v1/usage?month=YYYY-MM · POST /v1/connections/ai-test {provider}   → Lizenzen/Betrieb, Kostenübersicht, Schlüssel-Test (Studio)
 *   GET  /chat/tokens-api.php/v1/me                                        → hesap, bakiye, aktif paketler/agentler, son kullanım
 *   GET|POST /v1/profile {kind,company,sectors[],size,city,country,needs[],langs[]} | {dismissed:true} → Konto-Profil + Empfehlungen (profile-lib)
 *   GET  /chat/tokens-api.php/v1/balance                                   → bakiye + son kullanım
 *   POST /chat/tokens-api.php/v1/generate   {task, sector, input, lang}    → metin üretimi (post/script/widerspruch/...)
 *   POST /chat/tokens-api.php/v1/translate  {text, target[], lang}         → çeviri
 *   POST /chat/tokens-api.php/v1/subtitles  {transcript, target[], minutes}→ zaman damgalı SRT
 *   POST /chat/tokens-api.php/v1/agent      {plan, messages[]}             → kiralanan AGENT ile çok turlu sohbet (bilgi tabanı + rol)
 *                                    {sector, goal, context}        → sektör ajanı tek tur (plan gerekmez, token yeter)
 *   GET  /chat/tokens-api.php/v1/agent/config?plan=…                       → agent ayarları (firma, ton, dil, bilgi tabanı, widget)
 *   POST /chat/tokens-api.php/v1/agent/config {plan, company, tone, lang, kb, widget_on, widget_domain}
 *   GET|POST /v1/appt/settings · GET /v1/appt/slots?date&service · GET /v1/appt/bookings · POST /v1/appt/book|cancel|status   → Terminbuchung (appt-lib)
 *   GET|POST /v1/order/settings · POST /v1/order/menu|drivers · GET /v1/order/check?address · GET /v1/order/orders · POST /v1/order/create|status → Bestellannahme (order-lib)
 *   GET|POST /v1/sites · GET /v1/sites/slug-check · GET|POST /v1/sites/<id> · POST /v1/sites/<id>/generate|translate|upload|asset-delete|publish|unpublish|delete|lead-status
 *        GET /v1/sites/<id>/leads|export → Website-Baukasten „Website erstellen“ (site-lib)
 * Auth: Authorization: Bearer ch_live_…   Fiyat: sabit task tarifesi (plans.php CH_PRICING).
 * Model: CLAUDE_KEY varsa Claude, yoksa DeepSeek (db.php). Sektör/paket eklemek = plans.php'ye bir satır.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/appt-lib.php';
require_once __DIR__.'/order-lib.php';
require_once __DIR__.'/agent-tools.php';   // Werkzeug-Register + Fachmodule (Taxi, Hotel, Rückruf, Training)
require_once __DIR__.'/profile-lib.php';   // Konto-Profil aus der Registrierung (Branche, Bedarf, Sprachen) + Empfehlungen
require_once __DIR__.'/site-lib.php';      // Website-Baukasten (/v1/sites…)
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Authorization, Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }
th_bootstrap();
const PRICING = CH_PRICING; const SECTORS = CH_SECTORS;

/* ---- Yardımcılar ---- */
function body(): array { $j=json_decode(file_get_contents('php://input'),true); return is_array($j)?$j:[]; }
function route(): string { $u=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); $i=strpos($u,'/v1/'); return $i===false?'':substr($u,$i+4); }
/** Çok turlu sohbet: messages = [{role:user|assistant, content}] */
function ai_chat(string $system, array $messages, int $maxTokens=1500): string {
    $msgs=[]; foreach($messages as $m){ $role=($m['role']??'user')==='assistant'?'assistant':'user'; $c=trim((string)($m['content']??'')); if($c!=='') $msgs[]=['role'=>$role,'content'=>mb_substr($c,0,12000)]; }
    if(!$msgs || end($msgs)['role']!=='user') th_json(400,['error'=>'messages_required','hint'=>'letzte Nachricht muss role=user sein']);
    $msgs=array_slice($msgs,-20);
    $out = th_ai($system, $msgs, $maxTokens, 90);   // model zinciri + DeepSeek yedeği (tokens-lib)
    if($out!==null) return $out;
    $inf=th_ai_info(); if(!empty($inf['byok_failed'])) th_json(503,['error'=>'byok_failed','message'=>'Dein eigener KI-Schlüssel wurde abgelehnt und es ist kein ChatHelp-Guthaben für den Ersatzbetrieb da. Bitte Schlüssel unter Telefon → Verbindungen erneuern oder Tokens nachladen.','detail'=>$inf['errors'] ?? []]);
    th_json(503,['error'=>'ai_unavailable','message'=>'KI-Backend nicht erreichbar (CLAUDE_KEY / CLAUDE_MODEL in db.php prüfen)','detail'=>$inf['errors'] ?? []]);
}
function ai_complete(string $system, string $user, int $maxTokens=1500): string { return ai_chat($system,[['role'=>'user','content'=>$user]],$maxTokens); }
function sys_for(string $sector, string $lang): string {
    $s = SECTORS[$sector]['sys'] ?? 'Du bist ein präziser, hilfreicher Business-Assistent.';
    return $s." Antworte in der Sprache: ".strtoupper($lang).". Gib nur das Ergebnis, ohne Vorrede.";
}
/** Ücretli işlem sarmalayıcı: bakiye → iş → düş → logla. */
function charged(array $acc, int $cost, string $task, string $sector, callable $work, array $extra=[]): void {
    $t0=microtime(true); if((int)(th_ai_context()['acc'] ?? 0)!==(int)$acc['id'] || (th_ai_context()['channel'] ?? '')==='') th_ai_context((int)$acc['id'],'api');
    if(th_balance((int)$acc['id']) < $cost) th_json(402,['error'=>'insufficient_tokens','need'=>$cost,'balance'=>th_balance((int)$acc['id']),'buy'=>'https://chat-help.com/#tokens']);
    $result=$work();
    $cost=th_charge((int)$acc['id'],$cost,'api:'.$task);   // nach erbrachter Leistung: höchstens Restguthaben
    th_log_usage((int)$acc['id'],$task,$sector,$cost,(int)((microtime(true)-$t0)*1000));
    th_json(200,array_merge(['result'=>$result,'tokens_used'=>$cost,'balance'=>th_balance((int)$acc['id'])],$extra));
}
/** Stufen eines Agenten für Website/Studio: Preis, Tokens und geschätzte Gesprächszeit (ChatHelp-Nummer; eigenes Twilio = mehr Stunden). */
function plan_tiers_public(string $base): array {
    if(!isset(CH_PLAN_TIERS[$base])) return []; $r=ch_phone_rates(); $o=[];
    foreach(CH_PLAN_TIERS[$base] as $tid){ $t=ch_plan($tid); $tk=ch_plan_tier($tid); $o[]=['id'=>$tid,'key'=>$tk['key'],'label'=>CH_TIER_LABELS[$tk['key']] ?? '','name'=>$t['name'],'eur_month'=>$t['cents']/100,'tokens_month'=>$t['tokens'],
        'time'=>bl_tokens_time((int)$t['tokens'],$r['central']),'time_own'=>bl_tokens_time((int)$t['tokens'],$r['own'])]; }
    return $o;
}
function plans_public(): array { $o=[]; foreach(CH_PLANS as $id=>$v){ $pl=ch_plan($id); $o[]=['id'=>$id,'name'=>$v[0],'eur_month'=>$pl['cents']/100,'tokens_month'=>$pl['tokens'],'sector'=>$v[3],'kind'=>$v[4],'desc'=>$v[5],
    'tier_of'=>$pl['tier_of'] ?? null]+(isset(CH_PLAN_TIERS[$id]) ? ['tiers'=>plan_tiers_public($id),'assumptions'=>bl_assumptions()] : [])+[
    'channels'=>$pl['channels'],'category'=>$pl['category'],'demo'=>isset(CH_DEMO[$pl['base']]) ? $pl['base'] : null]+($v[4]==='buy' ? ['followups'=>array_map(fn($f)=>['id'=>$f['id'],'kind'=>$f['kind'],'eur_month'=>$f['cents']/100,'tokens_month'=>$f['tokens']],ch_followups($id)),'included_days'=>ch_buy_included_days($id)] : []); } return $o; }
/** Öffentliche, für alle gleiche Antwort: kurz cachebar (Browser 5 Min., Edge 10 Min.), ETag → 304. */
function public_json(array $data): void {
    $body=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $etag='"'.substr(md5($body),0,20).'"';
    header('Cache-Control: public, max-age=300, s-maxage=600, stale-while-revalidate=600'); header('ETag: '.$etag); header('Vary: Accept-Encoding');
    if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))===$etag){ http_response_code(304); exit; }
    http_response_code(200); header('Content-Type: application/json; charset=UTF-8'); echo $body; exit;
}
/** Preisübersicht Einmalkauf → Betrieb / eigener Schlüssel (GET /v1/pricing). */
function pricing_public(): array {
    $buys=[]; foreach(CH_PLANS as $id=>$v){ if($v[4]!=='buy') continue; $pl=ch_plan($id); $fu=ch_followups($id);
        $buys[]=['id'=>$id,'name'=>$v[0],'price'=>$v[1]/100,'start_tokens'=>$v[2],'base'=>$pl['base'],'monthly_alternative'=>isset(CH_PLANS[$pl['base']]) ? ['id'=>$pl['base'],'price'=>CH_PLANS[$pl['base']][1]/100,'tokens'=>CH_PLANS[$pl['base']][2]] : null,
          'tier'=>$pl['tier'],'channels'=>$pl['channels'],'category'=>$pl['category'],'included_days'=>ch_buy_included_days($id)]+(isset($pl['terms']) ? ['terms'=>$pl['terms']] : [])+[
          'betrieb'=>isset($fu['betrieb']) ? ['id'=>$fu['betrieb']['id'],'price'=>$fu['betrieb']['cents']/100,'tokens'=>$fu['betrieb']['tokens']] : null,
          'byok'=>isset($fu['byok']) ? ['id'=>$fu['byok']['id'],'price'=>$fu['byok']['cents']/100] : null]; }
    return ['currency'=>'EUR','vat'=>'zzgl. USt.','included_days'=>CH_BUY_INCLUDED_DAYS,'tiers'=>CH_RUN_TIERS,'one_time'=>$buys,
      'phone_tiers'=>array_combine(array_keys(CH_PLAN_TIERS),array_map('plan_tiers_public',array_keys(CH_PLAN_TIERS))),'assumptions'=>bl_assumptions(),'phone_rates'=>['central'=>ch_phone_rates()['central'],'own'=>ch_phone_rates()['own']],
      'byok'=>['providers'=>array_map(fn($c)=>['label'=>$c['label'],'model'=>$c['model']],BL_BYOK_PROVIDERS),'tokens_charged'=>0,
        'note'=>'Mit eigenem Claude- oder DeepSeek-Schlüssel zahlst du die KI direkt beim Anbieter; ChatHelp berechnet nur die Plattformgebühr.'],
      'ai_list_prices_usd_per_mtok'=>BL_AI_PRICES,'deepseek_offpeak_utc'=>BL_DEEPSEEK_OFFPEAK_UTC,'channels'=>CH_CHANNELS,
      'rule'=>'Einmalkauf = Einrichtung + Lizenz + Startguthaben (Tokens verfallen nicht) + '.CH_BUY_INCLUDED_DAYS.' Tage Betrieb. Danach Betrieb (inkl. Tokens) oder eigener KI-Schlüssel – monatlich kündbar.'];
}

/* ---- Anzeigepreise in Landeswährung (?cur=USD …, ?lang= für die Schreibweise): EUR-Felder bleiben unverändert, dazu „local“-Objekte ---- */
/** ['currency','amount' (Haupteinheit),'minor' (Stripe unit_amount),'formatted','approx'] – derselbe Betrag wie im Checkout (tokens-checkout.php ?cur=). */
function local_price(int $eurCents, string $cur, string $lang): array {
    $m=ch_price_local($eurCents,$cur); $dec=CH_CURRENCIES[$cur]['dec'];
    return ['currency'=>$cur,'amount'=>$dec ? $m/100 : $m,'minor'=>$m,'formatted'=>ch_cur_format($m,$cur,$lang),'approx'=>$cur!=='EUR'];
}
/** Kopf der Anzeigewährung: Kurs, Stand, ob Stripe in dieser Währung abrechnet, Rundungsregel. null = kein/ungültiger ?cur. */
function display_currency(): ?array {
    $want=ch_cur_norm((string)($_GET['cur'] ?? '')); if($want==='') return null;
    [$cur,$note]=ch_display_currency($want); $fx=ch_fx_rates(); $co=ch_cur_checkout($cur);
    $lang=ch_lang_norm((string)($_GET['lang'] ?? '')) ?: 'de';
    return ['code'=>$cur,'requested'=>$want,'symbol'=>CH_CURRENCIES[$cur]['sym'],'decimals'=>CH_CURRENCIES[$cur]['dec'],'rate'=>$fx['rates'][$cur],'rate_date'=>$fx['date'],'rate_source'=>$fx['source'],
      'billing'=>$co ? $cur : 'EUR','checkout'=>$co,'note'=>$note ?? ($co ? null : 'billing_eur'),'lang'=>$lang,'rounding'=>'Tageskurs × EUR-Preis, aufgerundet auf den nächsten Preispunkt (…9, …99, …990), höchstens 1,5 % darunter; EUR unverändert'];
}
function categories_local(array $cats, array $d): array {
    foreach($cats as &$c) foreach($c['plans'] as &$p){ $p['local']=local_price((int)$p['price_cents'],$d['code'],$d['lang']);
        foreach(($p['followups'] ?? []) as $k=>$f) $p['followups'][$k]['local']=local_price((int)$f['price_cents'],$d['code'],$d['lang']); }
    unset($c,$p); return $cats;
}
function pricing_local(array $pr, array $d): array {
    $lp=fn($eur)=>local_price((int)round($eur*100),$d['code'],$d['lang']);
    foreach($pr['one_time'] as &$o){ $o['local']=$lp($o['price']);
        foreach(['monthly_alternative','betrieb','byok'] as $k) if(is_array($o[$k] ?? null)) $o[$k]['local']=$lp($o[$k]['price']); }
    unset($o);
    $pr['tiers_local']=array_map(fn($t)=>['betrieb'=>local_price((int)$t['betrieb_cents'],$d['code'],$d['lang']),'byok'=>local_price((int)$t['byok_cents'],$d['code'],$d['lang'])],$pr['tiers']);
    return $pr+['display_currency'=>$d];
}

/* ---- Router ---- */
$r = rtrim(route(),'/'); $m=$_SERVER['REQUEST_METHOD'];
if($r==='sectors' && $m==='GET'){
    public_json(['sectors'=>array_map(fn($k,$v)=>['id'=>$k,'name'=>$v['name']],array_keys(SECTORS),SECTORS),'plans'=>plans_public(),'pricing_tokens'=>PRICING,'tasks'=>['generate','translate','subtitles','agent'],
      'channels'=>CH_CHANNELS,'categories'=>array_map(fn($k,$c)=>['id'=>$k,'label'=>$c['label'],'icon'=>$c['icon'],'order'=>$c['order']],array_keys(CH_CATEGORIES),CH_CATEGORIES)]);
}
if($r==='categories' && $m==='GET'){ $d=display_currency(); $cats=ch_categories_public();
    public_json(['categories'=>$d ? categories_local($cats,$d) : $cats,'channels'=>CH_CHANNELS,'included_days'=>CH_BUY_INCLUDED_DAYS]+($d ? ['display_currency'=>$d] : [])); }
if($r==='pricing' && $m==='GET'){ $d=display_currency(); public_json($d ? pricing_local(pricing_public(),$d) : pricing_public()); }
/* ---- Studio-Login (öffentlich): E-Mail → 6-stelliger Code → API-Key. Neue Konten erhalten einmalig CH_TRIAL_TOKENS. ---- */
if(($r==='auth/request' || $r==='auth/verify') && $m==='POST'){
    $b=body(); $email=strtolower(trim((string)($b['email']??''))); $lang=substr(preg_replace('/[^a-z]/','',strtolower($b['lang']??'de')),0,2) ?: 'de';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) th_json(400,['error'=>'email_invalid','message'=>'Bitte eine gültige E-Mail-Adresse angeben.']);
    $ip=$_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0')); $ip=trim(explode(',',$ip)[0]);
    if($r==='auth/request'){ $res=th_login_request($email,$ip,$lang); th_json($res['ok']?200:429,$res); }
    $acc=th_login_verify($email,(string)($b['code']??''));
    if(!$acc) th_json(401,['error'=>'invalid_code','message'=>'Code ungültig oder abgelaufen. Bitte neu anfordern.']);
    $aid=(int)$acc['id']; $trial=th_trial_credit($aid,CH_TRIAL_TOKENS); $key=th_issue_key($aid,'studio');
    th_json(200,['ok'=>true,'key'=>$key,'email'=>$acc['email'],'trial_credited'=>$trial,'balance'=>th_balance($aid)]);
}
/* ---- Anmeldung mit Google (ruhend, bis GOOGLE_OAUTH_CLIENT_ID + GOOGLE_OAUTH_CLIENT_SECRET gesetzt sind): GET auth/google → Google → GET auth/google/callback
        prüft state (signiertes Cookie) und das id_token bei Google, Konto zur bestätigten E-Mail (wie der Code-Login), Schlüssel ins Studio über #gkey=… ---- */
if(($r==='auth/google' || $r==='auth/google/callback') && $m==='GET'){
    $cid=defined('GOOGLE_OAUTH_CLIENT_ID') ? trim((string)GOOGLE_OAUTH_CLIENT_ID) : ''; $csec=defined('GOOGLE_OAUTH_CLIENT_SECRET') ? trim((string)GOOGLE_OAUTH_CLIENT_SECRET) : '';
    if($cid==='' || $csec==='') th_json(404,['error'=>'google_login_off','message'=>'Anmeldung mit Google ist nicht eingerichtet.']);
    if(isset($_GET['probe'])) th_json(200,['ok'=>true]);
    $site=defined('ONB_SITE') ? ONB_SITE : 'https://chat-help.com'; $redir=$site.'/chat/tokens-api.php/v1/auth/google/callback'; $studio=$site.'/chat/studio.php';
    $go=function(string $url): void { th_nocache(); header('Location: '.$url,true,302); exit; };
    $ip=$_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0')); $ip=trim(explode(',',$ip)[0]);
    if(!th_rate('glogin:'.$ip,30,3600)) th_json(429,['error'=>'rate_limited','message'=>'Zu viele Versuche – bitte später erneut.']);
    $sig=fn(string $s)=>substr(hash_hmac('sha256',$s,$csec),0,24);
    if($r==='auth/google'){
        $state=bin2hex(random_bytes(16)).'.'.time();
        setcookie('ch_gstate',$state.'.'.$sig($state),['expires'=>time()+600,'path'=>'/chat/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        $hl=substr(preg_replace('/[^a-z]/','',strtolower((string)($_GET['lang'] ?? 'de'))),0,2) ?: 'de';
        $go('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$cid,'redirect_uri'=>$redir,'response_type'=>'code','scope'=>'openid email','state'=>$state,'prompt'=>'select_account','hl'=>$hl]));
    }
    setcookie('ch_gstate','',['expires'=>1,'path'=>'/chat/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    if(isset($_GET['error'])) $go($studio.'#glogin=cancel');
    $parts=explode('.',(string)($_COOKIE['ch_gstate'] ?? '')); $csig=(string)array_pop($parts); $cstate=implode('.',$parts); $st=(string)($_GET['state'] ?? ''); $code=(string)($_GET['code'] ?? '');
    if($cstate==='' || $code==='' || !hash_equals($sig($cstate),$csig) || !hash_equals($cstate,$st) || (int)(explode('.',$cstate)[1] ?? 0) < time()-600) $go($studio.'#glogin=state');
    $post=function(string $url, array $data): array { $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data),CURLOPT_TIMEOUT=>12,CURLOPT_CONNECTTIMEOUT=>6]);
        $b=(string)curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return [$c,json_decode($b,true) ?: []]; };
    [$c,$tok]=$post('https://oauth2.googleapis.com/token',['client_id'=>$cid,'client_secret'=>$csec,'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$redir]);
    $idt=(string)($tok['id_token'] ?? ''); if($c!==200 || $idt==='') $go($studio.'#glogin=token');
    $ch=curl_init('https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($idt)); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_CONNECTTIMEOUT=>6]); $info=json_decode((string)curl_exec($ch),true) ?: []; curl_close($ch);
    $email=strtolower(trim((string)($info['email'] ?? '')));
    if(($info['aud'] ?? '')!==$cid || !in_array($info['iss'] ?? '',['https://accounts.google.com','accounts.google.com'],true) || ($info['email_verified'] ?? '')!=='true' || !filter_var($email,FILTER_VALIDATE_EMAIL)) $go($studio.'#glogin=verify');
    $acc=th_account($email); $aid=(int)$acc['id']; th_trial_credit($aid,CH_TRIAL_TOKENS); $key=th_issue_key($aid,'google');
    $go($studio.'#gkey='.$key);
}

$hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$acc = th_auth(preg_replace('/^Bearer\s+/i','',trim($hdr)));
if(!$acc) th_json(401,['error'=>'unauthorized','message'=>'Authorization: Bearer ch_live_… fehlt oder ungültig','get_key'=>'https://chat-help.com/chat/studio.php']);
$aid=(int)$acc['id'];
$b=body(); $lang=substr(preg_replace('/[^a-z]/','',strtolower($b['lang']??($_GET['lang']??'de'))),0,2) ?: 'de';
$sector=preg_replace('/[^a-z]/','',strtolower($b['sector']??'marketing')); if(!isset(SECTORS[$sector])) $sector='marketing';
$planId=preg_replace('/[^a-z0-9-]/','',strtolower($b['plan']??($_GET['plan']??'')));
ch_heartbeat();   // fällige KI-Rückrufe nach der Antwort anstoßen
/* Fachmodule (ride/…, hotel/…, callbacks/…, train/…) beantworten ihre eigenen Routen */
if(($res=ch_module_api($r,$m,$aid,$b,$acc))!==null) th_json((int)$res[0],(array)$res[1]);
/* Website-Baukasten: /v1/sites… (Besitzprüfung in site_api, fremde IDs → 404) */
if(($res=site_api($r,$m,$aid,$b,$acc))!==null) th_json((int)$res[0],(array)$res[1]);

switch(true){
  case $r==='me' && $m==='GET':
    $u=th_db()->prepare("SELECT task,sector,tokens,created_at FROM api_usage WHERE account_id=? ORDER BY id DESC LIMIT 15"); $u->execute([$aid]);
    $plans=[]; foreach(th_plans($aid) as $p){ $pl=ch_plan($p['plan']); if($pl) $plans[]=['id'=>$pl['id'],'name'=>$pl['name'],'kind'=>$pl['kind'],'sector'=>$pl['sector'],'base'=>$pl['base'],'status'=>$p['status'],
      'period_end'=>$p['period_end'] ? th_dbiso((string)$p['period_end']) : null,'tokens_month'=>$pl['tokens'],'channels'=>$pl['channels'],
      'license'=>$pl['kind']==='buy' ? ($p['status']==='license' ? 'expired' : ($p['period_end']===null ? 'lifetime' : 'included')) : null,
      'active'=>in_array($p['status'],['active','trialing','past_due'],true) && th_plan_active($aid,$pl['id'])]; }
    $cf=th_db()->prepare("SELECT plan,company,widget_on,phone_on,phone_number,updated_at FROM agent_configs WHERE account_id=?"); $cf->execute([$aid]);
    $tr=th_db()->prepare("SELECT COUNT(*) FROM token_ledger WHERE account_id=? AND reason='trial'"); $tr->execute([$aid]);
    $byok=bl_byok_public($aid);
    th_json(200,['email'=>$acc['email'],'balance'=>th_balance_ledger($aid),'byok'=>['active'=>$byok['active'],'alert'=>$byok['alert'] ?? null],'channels'=>ch_account_channels($aid),'plans'=>$plans,'configs'=>$cf->fetchAll(PDO::FETCH_ASSOC),'trial'=>(int)$tr->fetchColumn()>0,
      'pricing'=>['agent_turn'=>PRICING['agent_turn'],'widget_turn'=>PRICING['widget_turn'],'phone_turn'=>PRICING['phone_turn'],'phone_call'=>(function() use($aid){ foreach(th_plans($aid) as $p){ if(in_array($p['status'],['active','trialing','past_due','license'],true) && ch_plan_has_channel((string)$p['plan'],'phone')) return ch_phone_turn_tokens($aid,(string)$p['plan']); } return ch_phone_rates()['central']; })()],
      'admin'=>bl_is_admin($aid),
      'recent'=>array_map(fn($x)=>['created_at'=>th_dbiso($x['created_at'])]+$x,$u->fetchAll(PDO::FETCH_ASSOC)),'sectors'=>array_map(fn($k,$v)=>['id'=>$k,'name'=>$v['name']],array_keys(SECTORS),SECTORS)]);

  /* ---- Konto-Profil (Registrierungs-Assistent): Betrieb/privat, Branchen, Bedarf, Sprachen → Empfehlungen + Studio-Bereiche ---- */
  case $r==='profile' && $m==='GET':
    th_json(200,profile_response($aid));
  case $r==='profile' && $m==='POST':
    if(!th_rate('profile:'.$aid,60,3600)) th_json(429,['error'=>'rate_limited','message'=>'Bitte kurz warten und erneut speichern.']);
    if(empty($b['dismissed']) && !array_intersect(array_keys($b),['kind','company','sectors','size','city','country','needs','langs'])) th_json(400,['error'=>'profile_fields_required','message'=>'Keine Profilangaben übermittelt.']);   // kaputter/leerer Body löscht kein vorhandenes Profil
    $pr=profile_save($aid,$b,!empty($b['dismissed']));
    th_json(200,['ok'=>true]+profile_response($aid,$pr));

  /* ---- Telefon-Agent: Anrufliste mit Transkripten ---- */
  case $r==='phone/calls' && $m==='GET':
    th_json(200,['calls'=>array_map(fn($c)=>['started_at'=>th_dbiso($c['started_at']),'ended_at'=>th_dbiso($c['ended_at'])]+$c,th_calls($aid,$planId,60))]);

  /* ---- Verbindungen: eigene Twilio-/Meta-Zugangsdaten des Betriebs (Antwort enthält nie den Klartext) ---- */
  case $r==='connections' && $m==='GET':
    th_json(200,['connections'=>th_secret_hints($aid),'ai'=>bl_byok_public($aid),'platform_twilio'=>defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID ? true : false,'encrypted'=>th_secret_master()!=='']);
  case $r==='connections' && $m==='POST':
    $rules=['twilio_sid'=>'/^AC[a-f0-9]{32}$/','twilio_token'=>'/^[a-f0-9]{32}$/','meta_app_secret'=>'/^[a-f0-9]{32}$/'];
    $chg=[];
    /* eigene KI-Schlüssel (Claude / DeepSeek): Format + 1-Token-Test vor dem Speichern (billing-lib) */
    $ai=null; if(array_key_exists('claude_key',$b) || array_key_exists('deepseek_key',$b) || isset($b['ai_pref'])){ $ai=bl_byok_save($aid,$b); if(isset($ai['error'])) th_json(422,$ai); }
    foreach(TH_SECRET_KEYS as $k){ if(in_array($k,['claude_key','deepseek_key'],true) || !array_key_exists($k,$b)) continue; $v=trim((string)$b[$k]); if(str_starts_with($v,'••••')) continue;
        if($v!=='' && $k==='twilio_from'){ $v=th_e164($v); if($v==='') th_json(422,['error'=>'bad_value','field'=>$k,'message'=>'Absender-Nummer bitte im Format +49…']); }
        elseif($v!=='' && isset($rules[$k]) && !preg_match($rules[$k],$v)) th_json(422,['error'=>'bad_value','field'=>$k,'message'=>['twilio_sid'=>'Account SID beginnt mit AC und hat 34 Zeichen.','twilio_token'=>'Auth Token: 32 Zeichen (0–9, a–f).','meta_app_secret'=>'App-Secret: 32 Zeichen (0–9, a–f).'][$k]]);
        $chg[$k]=$v; }
    /* Twilio-Zugang vor dem Speichern prüfen (nur wenn beide Teile vorliegen) */
    $sid=$chg['twilio_sid'] ?? th_secret($aid,'twilio_sid'); $tok=$chg['twilio_token'] ?? th_secret($aid,'twilio_token'); $verified=null;
    if((isset($chg['twilio_sid']) || isset($chg['twilio_token'])) && $sid!=='' && $tok!==''){
        $ch=curl_init('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($sid).'.json'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_USERPWD=>$sid.':'.$tok]);
        curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $verified=$code===200;
        if($code===401 || $code===404) th_json(422,['error'=>'twilio_auth_failed','message'=>'Die Zugangsdaten wurden abgelehnt – bitte prüfen.']);
    }
    foreach($chg as $k=>$v) th_secret_set($aid,$k,$v);
    th_json(200,['ok'=>true,'connections'=>th_secret_hints($aid),'twilio_verified'=>$verified,'ai'=>bl_byok_public($aid),'ai_tests'=>$ai['tests'] ?? []]);
  case $r==='connections/ai-test' && $m==='POST':
    $prov=(string)($b['provider'] ?? ''); if(!isset(BL_BYOK_PROVIDERS[$prov])) th_json(400,['error'=>'provider_required','providers'=>array_keys(BL_BYOK_PROVIDERS)]);
    if(!th_rate('byoktest:'.$aid,20,3600)) th_json(429,['error'=>'rate_limited','message'=>'Bitte etwas warten, bevor du erneut testest.']);
    $t=bl_byok_test($aid,$prov); th_json($t['status']==='missing' ? 404 : 200,$t+['ai'=>bl_byok_public($aid)]);

  /* ---- Abrechnung: Lizenzen (Einmalkauf), Betrieb / eigener Schlüssel, Kostenübersicht ---- */
  case $r==='billing' && $m==='GET':
    th_json(200,['licenses'=>bl_billing_overview($aid,(string)$acc['email']),'balance'=>th_balance_ledger($aid),'byok'=>bl_byok_public($aid),'tiers'=>CH_RUN_TIERS,'included_days'=>CH_BUY_INCLUDED_DAYS]);
  /* ---- Kontingent (Monats-Gesprächszeit) + Upgrade auf die nächste Stufe ---- */
  case $r==='quota' && $m==='GET':
    th_json(200,['quota'=>bl_quota_status($aid)]);
  case $r==='plan/upgrade' && $m==='POST':
    if(!th_rate('upg:'.$aid,5,3600)) th_json(429,['error'=>'rate_limited','message'=>'Bitte kurz warten.']);
    $res=bl_plan_upgrade($aid,preg_replace('/[^a-z0-9-]/','',strtolower((string)($b['to'] ?? '')))); th_json(isset($res['error']) ? 409 : 200,$res+['quota'=>bl_quota_status($aid)]);
  /* ---- Betreiber: Annahmen, Telefon-Grenzen, Tokens je Antwort, Paket-Anpassungen für neue Käufe (ch_settings) ---- */
  case $r==='admin/settings' && in_array($m,['GET','POST'],true):
    if(!bl_is_admin($aid)) th_json(403,['error'=>'forbidden']);
    if($m==='POST'){ $in=is_array($b['settings'] ?? null) ? $b['settings'] : [];
      $num=fn($v,$lo,$hi,$f=false)=>$v===null || $v==='' ? null : max($lo,min($hi,$f ? (float)$v : (int)$v));
      if(isset($in['assumptions'])) ch_setting_set('assumptions',array_filter(['answers_per_hour'=>$num($in['assumptions']['answers_per_hour'] ?? null,20,600),'minutes_per_call'=>$num($in['assumptions']['minutes_per_call'] ?? null,0.5,30,true)],fn($x)=>$x!==null));
      if(isset($in['phone_limits'])) ch_setting_set('phone_limits',array_filter(['max_call_min'=>$num($in['phone_limits']['max_call_min'] ?? null,2,60),'concurrent'=>$num($in['phone_limits']['concurrent'] ?? null,1,30),
        'concurrent_by_plan'=>array_filter(array_map(fn($x)=>$num($x,1,30),array_intersect_key((array)($in['phone_limits']['concurrent_by_plan'] ?? []),CH_PLANS)),fn($x)=>$x!==null)],fn($x)=>$x!==null && $x!==[]));
      if(isset($in['phone_rates'])) ch_setting_set('phone_rates',array_filter(['central'=>$num($in['phone_rates']['central'] ?? null,1,100),'own'=>$num($in['phone_rates']['own'] ?? null,1,100),'legacy'=>$num($in['phone_rates']['legacy'] ?? null,1,100),'premium'=>$num($in['phone_rates']['premium'] ?? null,0,100)],fn($x)=>$x!==null));
      if(isset($in['phone_stt'])){ $x=(array)$in['phone_stt']; ch_setting_set('phone_stt',['mode'=>($x['mode'] ?? '')==='record' ? 'record' : 'gather','provider'=>in_array($x['provider'] ?? '',['deepgram','azure'],true) ? $x['provider'] : 'deepgram','scope'=>($x['scope'] ?? '')==='all' ? 'all' : 'central']); }
      if(isset($in['plan_overrides'])){ $ov=[]; foreach(array_intersect_key((array)$in['plan_overrides'],CH_PLANS) as $pid=>$o){ $c=$num($o['cents'] ?? null,100,10000000); $t=$num($o['tokens'] ?? null,0,100000000); if($c!==null || $t!==null) $ov[$pid]=array_filter(['cents'=>$c,'tokens'=>$t],fn($x)=>$x!==null); } ch_setting_set('plan_overrides',$ov); }
    }
    $tiers=[]; foreach(CH_PLAN_TIERS as $base=>$ids) foreach($ids as $pid){ $pl=ch_plan($pid); $tiers[]=['id'=>$pid,'base'=>$base,'name'=>$pl['name'],'cents'=>$pl['cents'],'tokens'=>$pl['tokens'],'catalog'=>['cents'=>CH_PLANS[$pid][1],'tokens'=>CH_PLANS[$pid][2]],'time_central'=>bl_tokens_time((int)$pl['tokens'],ch_phone_rates()['central']),'time_own'=>bl_tokens_time((int)$pl['tokens'],ch_phone_rates()['own'])]; }
    $fl=ch_plan('buy-amazon-fba'); $tiers[]=['id'=>'buy-amazon-fba','base'=>'buy-amazon-fba','name'=>$fl['name'],'cents'=>$fl['cents'],'tokens'=>$fl['tokens'],'catalog'=>['cents'=>CH_PLANS['buy-amazon-fba'][1],'tokens'=>CH_PLANS['buy-amazon-fba'][2]],'terms'=>array_map(fn($t)=>$t['cents'],ch_buy_terms('buy-amazon-fba') ?? []),'time_central'=>null,'time_own'=>null];   // Lizenz: Preis + Tokens im Admin änderbar (2 Jahre im Verhältnis)
    if(!function_exists('stt_cfg')) require_once __DIR__.'/stt-lib.php';
    th_json(200,['assumptions'=>bl_assumptions(),'phone_limits'=>ch_setting('phone_limits',[])+CH_PHONE_LIMITS_DEFAULT,'phone_rates'=>ch_phone_rates(),
      'phone_stt'=>stt_cfg()+['keys'=>['deepgram'=>stt_key('deepgram')!=='','azure'=>stt_key('azure')!=='']],'plan_overrides'=>ch_setting('plan_overrides',[]),'tiers'=>$tiers,
      'buy_amazon'=>['id'=>'buy-amazon-fba','cents'=>ch_plan('buy-amazon-fba')['cents'],'tokens'=>ch_plan('buy-amazon-fba')['tokens'],'catalog'=>['cents'=>CH_PLANS['buy-amazon-fba'][1],'tokens'=>CH_PLANS['buy-amazon-fba'][2]]]]);

  case $r==='usage' && $m==='GET':
    $ov=bl_cost_overview($aid,(string)($_GET['month'] ?? ''));
    /* Betreiber: echte Plattform-Kosten je Antwort (alle Konten + Demo, Claude über ChatHelp) – heute, 7 und 30 Tage */
    if(bl_is_admin($aid)){ $d=fn(int $n)=>date('Y-m-d',strtotime(($n>=0?'+':'').$n.' day')); $ov['platform']=['today'=>bl_answer_costs($d(0),$d(1)),'d7'=>bl_answer_costs($d(-6),$d(1)),'d30'=>bl_answer_costs($d(-29),$d(1)),'target_cent'=>0.52]; }
    th_json(200,$ov);

  /* ---- Terminbuchung (Kalender des Kontos) ---- */
  case $r==='appt/settings' && $m==='GET':
    th_json(200,['settings'=>appt_settings($aid),'services'=>appt_services($aid,false),'booking_url'=>appt_booking_url($aid)]);
  case $r==='appt/settings' && $m==='POST':
    $st=appt_settings_save($aid,$b,$aerr); if($aerr) th_json(422,$aerr); th_json(200,['ok'=>true,'settings'=>$st,'services'=>appt_services($aid,false),'booking_url'=>appt_booking_url($aid)]);
  case $r==='appt/slots' && $m==='GET':
    th_json(200,appt_slots($aid,(string)($_GET['date'] ?? date('Y-m-d')),isset($_GET['service'])?(int)$_GET['service']:null));
  case $r==='appt/bookings' && $m==='GET':
    appt_reminders_lazy();
    $from=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['from']??''))?$_GET['from']:date('Y-m-d'); $to=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['to']??''))?$_GET['to']:date('Y-m-d',strtotime('+30 days'));
    th_json(200,['from'=>$from,'to'=>$to,'bookings'=>appt_bookings($aid,$from,$to)]);
  case $r==='appt/book' && $m==='POST':
    $res=appt_book($aid,isset($b['service_id'])?(int)$b['service_id']:null,(string)($b['start']??''),(string)($b['name']??''),(string)($b['phone']??''),(string)($b['email']??''),(string)($b['note']??''),'studio');
    th_json(isset($res['error'])?422:200,$res);
  case $r==='appt/cancel' && $m==='POST':
    $res=appt_cancel((string)($b['code']??''),$aid); th_json(isset($res['error'])?404:200,$res);
  case $r==='appt/status' && $m==='POST':
    $ares=appt_status_update($aid,(int)($b['id']??0),(string)($b['status']??'')); th_json(!empty($ares['ok']) ? 200 : (['invalid_status'=>400,'not_found'=>404,'slot_conflict'=>409][$ares['error'] ?? ''] ?? 500),$ares);

  /* ---- Bestellannahme (Restaurant, Pizzeria, Lieferdienst): Einstellungen, Karte, Fahrer, Live-Bestellungen ---- */
  case $r==='order/settings' && $m==='GET':
    th_json(200,['settings'=>ord_settings_public(ord_settings($aid)),'menu'=>ord_menu($aid,false),'drivers'=>ord_drivers($aid,false),'links'=>ord_links($aid),'statuses'=>ORD_STATUSES]);
  case $r==='order/settings' && $m==='POST':
    $st=ord_settings_save($aid,$b);   // enthält optional menu[] und drivers[]
    th_json(200,['ok'=>true,'settings'=>ord_settings_public($st),'menu'=>ord_menu($aid,false),'drivers'=>ord_drivers($aid,false),'links'=>ord_links($aid)]);
  case $r==='order/menu' && $m==='POST':
    if(!empty($b['demo'])) ord_menu_save($aid,ORD_DEMO['menu']); else ord_menu_save($aid,is_array($b['menu']??null)?$b['menu']:[]);
    th_json(200,['ok'=>true,'menu'=>ord_menu($aid,false)]);
  case $r==='order/drivers' && $m==='POST':
    ord_drivers_save($aid,is_array($b['drivers']??null)?$b['drivers']:[]); th_json(200,['ok'=>true,'drivers'=>ord_drivers($aid,false)]);
  case $r==='order/check' && $m==='GET':
    th_json(200,ord_check_address($aid,(string)($_GET['address'] ?? '')));
  case $r==='order/orders' && $m==='GET':
    th_json(200,['orders'=>ord_orders($aid,($_GET['scope'] ?? 'open')==='all'?'all':'open'),'drivers'=>array_map(fn($d)=>['id'=>$d['id'],'name'=>$d['name']],ord_drivers($aid,true))]);
  case $r==='order/create' && $m==='POST':
    $res=ord_create($aid,$b,'studio'); th_json(isset($res['error'])?422:200,$res);
  case $r==='order/status' && $m==='POST':
    $res=ord_set_status($aid,(int)($b['id']??0),(string)($b['status']??''),isset($b['driver_id'])&&$b['driver_id']!==''&&$b['driver_id']!==null?(int)$b['driver_id']:null); th_json(isset($res['error'])?400:200,$res);

  case $r==='balance' && $m==='GET':
    $u=th_db()->prepare("SELECT task,sector,tokens,created_at FROM api_usage WHERE account_id=? ORDER BY id DESC LIMIT 20"); $u->execute([$aid]);
    th_json(200,['email'=>$acc['email'],'balance'=>th_balance($aid),'recent'=>$u->fetchAll(PDO::FETCH_ASSOC)]);

  case $r==='generate' && $m==='POST':
    $task=preg_replace('/[^a-z_]/','',strtolower($b['task']??'generic')); $input=trim((string)($b['input']??''));
    if($input==='') th_json(400,['error'=>'input_required']);
    $cost=PRICING[$task] ?? PRICING['generic'];
    charged($acc,$cost,$task,$sector,fn()=>ai_complete(sys_for($sector,$lang),"Aufgabe: $task\n\n$input",1800));

  case $r==='translate' && $m==='POST':
    $text=trim((string)($b['text']??'')); $targets=array_values(array_filter(array_map('strtolower',(array)($b['target']??['en']))));
    if($text===''||!$targets) th_json(400,['error'=>'text_and_target_required']);
    $words=max(1,str_word_count($text)); $cost=(int)ceil($words/100)*PRICING['translate_100w']*count($targets);
    charged($acc,$cost,'translate',$sector,function()use($text,$targets){
        $out=[]; foreach($targets as $t){ $out[$t]=ai_complete("Du bist ein professioneller Übersetzer. Übersetze exakt, idiomatisch, ohne Zusätze.","Zielsprache: ".strtoupper($t)."\n\n$text",2000); } return $out; });

  case $r==='subtitles' && $m==='POST':
    $tr=trim((string)($b['transcript']??'')); $targets=array_values(array_filter(array_map('strtolower',(array)($b['target']??['de'])))); $mins=max(1,(int)($b['minutes']??ceil(str_word_count($tr)/130)));
    if($tr==='') th_json(400,['error'=>'transcript_required','hint'=>'Video-Upload/Transkription folgt; aktuell Transkript-Text senden.']);
    $cost=$mins*PRICING['subtitle_min']*count($targets);
    charged($acc,$cost,'subtitles',$sector,function()use($tr,$targets,$mins){
        $out=[]; foreach($targets as $t){ $out[$t]=ai_complete("Du erzeugst SRT-Untertitel. Teile den Text in Segmente von max. 2 Zeilen/42 Zeichen, verteile Zeitstempel gleichmäßig über ".$mins." Minuten, Format 00:00:00,000 --> 00:00:00,000. Gib NUR gültiges SRT aus.","Zielsprache: ".strtoupper($t)."\n\nTranskript:\n$tr",3000); } return ['format'=>'srt','files'=>$out]; });

  /* ---- Kiralanan AGENT / Paket: çok turlu, bilgi tabanlı ---- */
  case $r==='agent' && $m==='POST' && $planId!=='':
    /* Zugang: aktiver Plan (inkl. Monats-Tokens) ODER Guthaben (Studio-Test / Pay-as-you-go). Widget & Telefon erfordern einen aktiven Plan. */
    $pl=ch_plan($planId); if(!$pl) th_json(404,['error'=>'unknown_plan','plans'=>array_keys(CH_PLANS)]);
    $active=th_plan_active($aid,$planId);
    $msgs=is_array($b['messages']??null)?$b['messages']:[]; if(!$msgs && !empty($b['goal'])) $msgs=[['role'=>'user','content'=>(string)$b['goal']]];
    $cfg=th_agent_cfg($aid,$planId);
    $phone=(($b['channel']??'')==='phone');
    $chars=array_sum(array_map(fn($x)=>mb_strlen((string)($x['content']??'')),$msgs));
    $cost=($phone?PRICING['phone_turn']:PRICING['agent_turn']) + (int)ceil($chars/2000);   // 6 (8 Telefon) Token/Antwort + 1 je 2.000 Zeichen Kontext
    if(!$active && th_balance($aid) < $cost) th_json(402,['error'=>'insufficient_tokens','need'=>$cost,'balance'=>th_balance($aid),'message'=>'Kein Guthaben: Agent mieten oder Tokens kaufen.','rent'=>'https://chat-help.com/chat/tokens-checkout.php?plan='.$planId,'buy'=>'https://chat-help.com/#tokens']);
    $sys=ch_agent_system($pl,$cfg,$lang).($phone?CH_PHONE_RULES:'');
    th_ai_context($aid,$phone?'phone-test':'studio');
    /* Anruf-Assistent (privat-lib): place_call prüft serverseitig, dass die letzte Nachricht der Person eine ausdrückliche Zustimmung ist */
    $GLOBALS['ch_current_plan']=$planId;   // Rechen-Werkzeuge (growth-lib) nur für den Agenten dieses Chats
    $lastU=null; foreach(array_reverse($msgs) as $mm){ if(($mm['role'] ?? '')==='user'){ $lastU=trim((string)($mm['content'] ?? '')); break; } } $GLOBALS['ch_last_user_message']=$lastU;
    /* Terminbuchung (appt_on) und/oder Bestellannahme (order_on) aktiv → Agent bekommt echte Werkzeuge; je Werkzeugaufruf +2 Tokens */
    $tk = ch_toolkit($aid,$phone?'phone-test':'studio','',null,$lang);
    if($tk['tools']){
        $sys .= $tk['hint'];
        $work = function() use($sys,$msgs,$phone,$tk){ $out=th_ai_tools($sys,$msgs,$tk['tools'],$tk['exec'],$phone?400:1800,90); if($out===null){ $inf=th_ai_info(); th_json(503,['error'=>!empty($inf['byok_failed']) ? 'byok_failed' : 'ai_unavailable','message'=>!empty($inf['byok_failed']) ? 'Dein eigener KI-Schlüssel wurde abgelehnt und es ist kein ChatHelp-Guthaben für den Ersatzbetrieb da.' : 'KI gerade nicht erreichbar.','detail'=>$inf['errors'] ?? []]); } return $out; };
        $t0=microtime(true); if(th_balance($aid) < $cost) th_json(402,['error'=>'insufficient_tokens','need'=>$cost,'balance'=>th_balance($aid),'buy'=>'https://chat-help.com/#preise']);
        $result=$work(); $calls=th_ai_info()['tool_calls'] ?? []; $cost += 2*count($calls);
        $cost=th_charge($aid,$cost,($phone?'phone-test:':'agent:').$planId); th_log_usage($aid,'agent:'.$planId,$pl['sector'],$cost,(int)((microtime(true)-$t0)*1000));
        th_json(200,['result'=>$result,'tokens_used'=>$cost,'balance'=>th_balance($aid),'plan'=>$planId,'access'=>$active?'plan':'tokens','tools'=>array_map(fn($c)=>['name'=>$c['name'],'ok'=>$c['ok']],$calls)]);
    }
    charged($acc,$cost,($phone?'phone-test:':'agent:').$planId,$pl['sector'],fn()=>ai_chat($sys,$msgs,$phone?400:2200),['plan'=>$planId,'access'=>$active?'plan':'tokens']);

  /* ---- Sektör ajanı tek tur (plan gerekmez) ---- */
  case $r==='agent' && $m==='POST':
    $goal=trim((string)($b['goal']??'')); $msgs=is_array($b['messages']??null)?$b['messages']:[];
    if($goal==='' && !$msgs) th_json(400,['error'=>'goal_or_messages_required']);
    $ctx=trim((string)($b['context']??''));
    if(!$msgs) $msgs=[['role'=>'user','content'=>"Ziel: $goal\n\nKontext:\n$ctx"]];
    charged($acc,PRICING['agent_run'],'agent_run',$sector,fn()=>ai_chat(sys_for($sector,$lang)." Arbeite als Agent: Ziel analysieren, kurzer Plan, dann das fertige Ergebnis liefern.",$msgs,2200));

  /* ---- Agent ayarları ---- */
  case $r==='agent/config' && $m==='GET':
    if($planId==='' || !ch_plan($planId)) th_json(400,['error'=>'plan_required']);
    $active=th_plan_active($aid,$planId);
    $c=th_agent_cfg($aid,$planId); unset($c['id'],$c['account_id']);
    require_once __DIR__.'/speech-lib.php';
    th_json(200,['config'=>$c,'plan_active'=>$active,'channels'=>ch_plan_channels($planId),'max_call_min'=>(int)(ch_phone_limits($planId)['max_call_sec']/60),'eco_available'=>ch_ai_eco_plan($planId) && th_ai_eu_cfg()!==null,
      'premium_voice'=>ch_plan_has_channel($planId,'phone') && ch_tts_creds('elevenlabs')!==null ? ['extra'=>ch_phone_rates()['premium']] : null,
      'widget_snippet'=>$c['widget_key']?'<script src="https://chat-help.com/chat/widget.js" data-key="'.$c['widget_key'].'" async></script>':null,
      'phone_webhook'=>$c['widget_key']?'https://chat-help.com/chat/phone-webhook.php?key='.$c['widget_key']:null]);

  case $r==='agent/config' && $m==='POST':
    if($planId==='' || !ch_plan($planId)) th_json(400,['error'=>'plan_required']);
    $active=th_plan_active($aid,$planId);
    $phoneNote=null;   // Kanalbündel: reine Website-Bots bekommen kein Telefon (außer ein anderes aktives Paket bündelt es)
    if(!empty($b['phone_on']) && !ch_plan_has_channel($planId,'phone') && !ch_bundled_plan($aid,'phone')){ $b['phone_on']=0; $phoneNote='Telefon und WhatsApp sind in reinen Website-Bots nicht enthalten – dafür einen Kunden-Agenten (z. B. Telefon-, Termin- oder Bestell-Agent) wählen.'; }
    th_agent_cfg_save($aid,$planId,$b,$active);          // ohne aktiven Plan: speichern ja, Widget/Telefon einschalten nein
    if(isset($b['langs']) && is_array($b['langs']) && function_exists('ch_agent_langs_save')) ch_agent_langs_save($aid,$planId,(string)($b['lang'] ?? 'de'),array_slice(array_map('strval',$b['langs']),0,20));   // erlaubte Kundensprachen
    $c=th_agent_cfg($aid,$planId); unset($c['id'],$c['account_id']);
    th_json(200,['ok'=>true,'config'=>$c,'plan_active'=>$active,'channels'=>ch_plan_channels($planId),'note'=>$phoneNote ?? ($active?null:'Wissensbasis gespeichert. Website-Widget und Telefon werden mit aktivem Plan freigeschaltet.'),
      'widget_snippet'=>'<script src="https://chat-help.com/chat/widget.js" data-key="'.$c['widget_key'].'" async></script>',
      'phone_webhook'=>'https://chat-help.com/chat/phone-webhook.php?key='.$c['widget_key']]);

  default:
    th_json(404,['error'=>'not_found','routes'=>['GET /v1/sectors','GET /v1/categories','GET /v1/pricing','GET /v1/billing','GET /v1/usage','POST /v1/connections/ai-test','POST /v1/auth/request','POST /v1/auth/verify','GET /v1/me','GET|POST /v1/profile','GET /v1/balance','POST /v1/generate','POST /v1/translate','POST /v1/subtitles','POST /v1/agent','GET|POST /v1/agent/config','GET /v1/phone/calls','GET|POST /v1/appt/settings','GET /v1/appt/slots','GET /v1/appt/bookings','POST /v1/appt/book|cancel|status','GET|POST /v1/order/settings','POST /v1/order/menu','POST /v1/order/drivers','GET /v1/order/check','GET /v1/order/orders','POST /v1/order/create|status','GET|POST /v1/sites','GET|POST /v1/sites/<id>','POST /v1/sites/<id>/generate|translate|upload|publish|unpublish','GET /v1/sites/<id>/leads|export']]);
}
