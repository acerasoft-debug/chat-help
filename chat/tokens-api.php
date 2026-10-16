<?php
/**
 * ChatHelp API v1 — TEK giriş noktası, tüm sektörler, gerçek Agent çalışma zamanı
 * ==================================================================================
 *   GET  /chat/tokens-api.php/v1/sectors                                   → sektörler, task'lar, token fiyatları (herkese açık)
 *   GET  /chat/tokens-api.php/v1/me                                        → hesap, bakiye, aktif paketler/agentler, son kullanım
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
 * Auth: Authorization: Bearer ch_live_…   Fiyat: sabit task tarifesi (plans.php CH_PRICING).
 * Model: CLAUDE_KEY varsa Claude, yoksa DeepSeek (db.php). Sektör/paket eklemek = plans.php'ye bir satır.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/appt-lib.php';
require_once __DIR__.'/order-lib.php';
require_once __DIR__.'/agent-tools.php';   // Werkzeug-Register + Fachmodule (Taxi, Hotel, Rückruf, Training)
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
    th_json(503,['error'=>'ai_unavailable','message'=>'KI-Backend nicht erreichbar (CLAUDE_KEY / CLAUDE_MODEL in db.php prüfen)','detail'=>th_ai_info()['errors'] ?? []]);
}
function ai_complete(string $system, string $user, int $maxTokens=1500): string { return ai_chat($system,[['role'=>'user','content'=>$user]],$maxTokens); }
function sys_for(string $sector, string $lang): string {
    $s = SECTORS[$sector]['sys'] ?? 'Du bist ein präziser, hilfreicher Business-Assistent.';
    return $s." Antworte in der Sprache: ".strtoupper($lang).". Gib nur das Ergebnis, ohne Vorrede.";
}
/** Ücretli işlem sarmalayıcı: bakiye → iş → düş → logla. */
function charged(array $acc, int $cost, string $task, string $sector, callable $work, array $extra=[]): void {
    $t0=microtime(true);
    if(th_balance((int)$acc['id']) < $cost) th_json(402,['error'=>'insufficient_tokens','need'=>$cost,'balance'=>th_balance((int)$acc['id']),'buy'=>'https://chat-help.com/#tokens']);
    $result=$work();
    th_debit((int)$acc['id'],$cost,'api:'.$task);
    th_log_usage((int)$acc['id'],$task,$sector,$cost,(int)((microtime(true)-$t0)*1000));
    th_json(200,array_merge(['result'=>$result,'tokens_used'=>$cost,'balance'=>th_balance((int)$acc['id'])],$extra));
}
function plans_public(): array { $o=[]; foreach(CH_PLANS as $id=>$v){ $o[]=['id'=>$id,'name'=>$v[0],'eur_month'=>$v[1]/100,'tokens_month'=>$v[2],'sector'=>$v[3],'kind'=>$v[4],'desc'=>$v[5]]; } return $o; }

/* ---- Router ---- */
$r = rtrim(route(),'/'); $m=$_SERVER['REQUEST_METHOD'];
if($r==='sectors' && $m==='GET'){
    th_json(200,['sectors'=>array_map(fn($k,$v)=>['id'=>$k,'name'=>$v['name']],array_keys(SECTORS),SECTORS),'plans'=>plans_public(),'pricing_tokens'=>PRICING,'tasks'=>['generate','translate','subtitles','agent']]);
}
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

switch(true){
  case $r==='me' && $m==='GET':
    $u=th_db()->prepare("SELECT task,sector,tokens,created_at FROM api_usage WHERE account_id=? ORDER BY id DESC LIMIT 15"); $u->execute([$aid]);
    $plans=[]; foreach(th_plans($aid) as $p){ $pl=ch_plan($p['plan']); if($pl) $plans[]=['id'=>$pl['id'],'name'=>$pl['name'],'kind'=>$pl['kind'],'sector'=>$pl['sector'],'status'=>$p['status'],'period_end'=>$p['period_end'],'tokens_month'=>$pl['tokens'],'active'=>in_array($p['status'],['active','trialing','past_due'],true)]; }
    $cf=th_db()->prepare("SELECT plan,company,widget_on,phone_on,phone_number,updated_at FROM agent_configs WHERE account_id=?"); $cf->execute([$aid]);
    $tr=th_db()->prepare("SELECT COUNT(*) FROM token_ledger WHERE account_id=? AND reason='trial'"); $tr->execute([$aid]);
    th_json(200,['email'=>$acc['email'],'balance'=>th_balance($aid),'plans'=>$plans,'configs'=>$cf->fetchAll(PDO::FETCH_ASSOC),'trial'=>(int)$tr->fetchColumn()>0,
      'pricing'=>['agent_turn'=>PRICING['agent_turn'],'widget_turn'=>PRICING['widget_turn'],'phone_turn'=>PRICING['phone_turn']],
      'recent'=>array_map(fn($x)=>['created_at'=>th_dbiso($x['created_at'])]+$x,$u->fetchAll(PDO::FETCH_ASSOC)),'sectors'=>array_map(fn($k,$v)=>['id'=>$k,'name'=>$v['name']],array_keys(SECTORS),SECTORS)]);

  /* ---- Telefon-Agent: Anrufliste mit Transkripten ---- */
  case $r==='phone/calls' && $m==='GET':
    th_json(200,['calls'=>array_map(fn($c)=>['started_at'=>th_dbiso($c['started_at']),'ended_at'=>th_dbiso($c['ended_at'])]+$c,th_calls($aid,$planId,60))]);

  /* ---- Terminbuchung (Kalender des Kontos) ---- */
  case $r==='appt/settings' && $m==='GET':
    th_json(200,['settings'=>appt_settings($aid),'services'=>appt_services($aid,false),'booking_url'=>appt_booking_url($aid)]);
  case $r==='appt/settings' && $m==='POST':
    $st=appt_settings_save($aid,$b); th_json(200,['ok'=>true,'settings'=>$st,'services'=>appt_services($aid,false),'booking_url'=>appt_booking_url($aid)]);
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
    th_json(appt_set_status($aid,(int)($b['id']??0),(string)($b['status']??''))?200:400,['ok'=>true]);

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
    /* Terminbuchung (appt_on) und/oder Bestellannahme (order_on) aktiv → Agent bekommt echte Werkzeuge; je Werkzeugaufruf +2 Tokens */
    $tk = ch_toolkit($aid,$phone?'phone-test':'studio','',null,$lang);
    if($tk['tools']){
        $sys .= $tk['hint'];
        $work = function() use($sys,$msgs,$phone,$tk){ $out=th_ai_tools($sys,$msgs,$tk['tools'],$tk['exec'],$phone?400:1800,90); if($out===null) th_json(503,['error'=>'ai_unavailable','detail'=>th_ai_info()['errors'] ?? []]); return $out; };
        $t0=microtime(true); if(th_balance($aid) < $cost) th_json(402,['error'=>'insufficient_tokens','need'=>$cost,'balance'=>th_balance($aid),'buy'=>'https://chat-help.com/#preise']);
        $result=$work(); $calls=th_ai_info()['tool_calls'] ?? []; $cost += 2*count($calls);
        th_debit($aid,$cost,($phone?'phone-test:':'agent:').$planId); th_log_usage($aid,'agent:'.$planId,$pl['sector'],$cost,(int)((microtime(true)-$t0)*1000));
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
    th_json(200,['config'=>$c,'plan_active'=>$active,
      'widget_snippet'=>$c['widget_key']?'<script src="https://chat-help.com/chat/widget.js" data-key="'.$c['widget_key'].'" async></script>':null,
      'phone_webhook'=>$c['widget_key']?'https://chat-help.com/chat/phone-webhook.php?key='.$c['widget_key']:null]);

  case $r==='agent/config' && $m==='POST':
    if($planId==='' || !ch_plan($planId)) th_json(400,['error'=>'plan_required']);
    $active=th_plan_active($aid,$planId);
    th_agent_cfg_save($aid,$planId,$b,$active);          // ohne aktiven Plan: speichern ja, Widget/Telefon einschalten nein
    $c=th_agent_cfg($aid,$planId); unset($c['id'],$c['account_id']);
    th_json(200,['ok'=>true,'config'=>$c,'plan_active'=>$active,'note'=>$active?null:'Wissensbasis gespeichert. Website-Widget und Telefon werden mit aktivem Plan freigeschaltet.',
      'widget_snippet'=>'<script src="https://chat-help.com/chat/widget.js" data-key="'.$c['widget_key'].'" async></script>',
      'phone_webhook'=>'https://chat-help.com/chat/phone-webhook.php?key='.$c['widget_key']]);

  default:
    th_json(404,['error'=>'not_found','routes'=>['GET /v1/sectors','POST /v1/auth/request','POST /v1/auth/verify','GET /v1/me','GET /v1/balance','POST /v1/generate','POST /v1/translate','POST /v1/subtitles','POST /v1/agent','GET|POST /v1/agent/config','GET /v1/phone/calls','GET|POST /v1/appt/settings','GET /v1/appt/slots','GET /v1/appt/bookings','POST /v1/appt/book|cancel|status','GET|POST /v1/order/settings','POST /v1/order/menu','POST /v1/order/drivers','GET /v1/order/check','GET /v1/order/orders','POST /v1/order/create|status']]);
}
