<?php
/**
 * ChatHelp — Website-Widget Backend (herkese açık, widget anahtarı ile)
 * =====================================================================
 * POST /chat/widget-api.php  {key:"wk_…", messages:[{role,content}], lang}
 *  - widget_key → agent_configs (widget_on=1) → hesap + plan; plan aktif olmalı
 *  - İzinli alan adı tanımlıysa Origin/Referer eşleşmeli
 *  - Hesap başına dakikada 60 mesaj sınırı; tokenlar agent SAHİBİNİN defterinden düşer (widget_turn)
 * GET  /chat/widget-api.php?key=wk_…  → widget için başlık/karşılama (JSON)
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }
th_bootstrap();

$in = json_decode(file_get_contents('php://input'),true); if(!is_array($in)) $in=[];
$key = (string)($in['key'] ?? ($_GET['key'] ?? ''));
$w = th_widget($key); if(!$w) th_json(403,['error'=>'widget_disabled','message'=>'Widget nicht aktiv oder Schlüssel ungültig.']);
$pl = ch_plan($w['plan']); if(!$pl) th_json(404,['error'=>'unknown_plan']);
$aid=(int)$w['account_id'];
if(!th_plan_active($aid,$pl['id'])) th_json(402,['error'=>'plan_inactive','message'=>'Der Agent ist derzeit nicht aktiv.']);

/* Alan adı kontrolü (isteğe bağlı) */
if(!empty($w['widget_domain'])){
    $allowed = array_filter(array_map('trim',preg_split('/[\s,]+/',strtolower($w['widget_domain']))));
    $origin = strtolower(parse_url($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? ''),PHP_URL_HOST) ?? '');
    $ok=false; foreach($allowed as $d){ $d=preg_replace('#^https?://#','',$d); $d=rtrim($d,'/'); if($origin===$d || ($d!=='' && str_ends_with($origin,'.'.$d))) $ok=true; }
    if(!$ok) th_json(403,['error'=>'domain_not_allowed','origin'=>$origin]);
}

$company = $w['company'] ?: 'Unser Team';
if($_SERVER['REQUEST_METHOD']==='GET'){
    th_json(200,['ok'=>true,'title'=>$company,'agent'=>$pl['name'],'lang'=>$w['lang'] ?: 'de',
      'greeting'=>($w['lang']??'de')==='en' ? "Hi! I'm the assistant of $company. How can I help?" : (($w['lang']??'de')==='tr' ? "Merhaba! $company asistanıyım. Nasıl yardımcı olabilirim?" : "Hallo! Ich bin der Assistent von $company. Wie kann ich helfen?")]);
}

/* Hız sınırı: hesap başına 60 mesaj / dakika */
$c=th_db()->prepare("SELECT COUNT(*) FROM api_usage WHERE account_id=? AND task='widget' AND created_at > (NOW() - INTERVAL 1 MINUTE)"); $c->execute([$aid]);
if((int)$c->fetchColumn() >= 60) th_json(429,['error'=>'rate_limited','message'=>'Bitte einen Moment warten.']);

$msgs=is_array($in['messages']??null)?array_slice($in['messages'],-12):[];
$clean=[]; foreach($msgs as $m){ $role=($m['role']??'user')==='assistant'?'assistant':'user'; $t=trim((string)($m['content']??'')); if($t!=='') $clean[]=['role'=>$role,'content'=>mb_substr($t,0,3000)]; }
if(!$clean || end($clean)['role']!=='user') th_json(400,['error'=>'messages_required']);

$cost=CH_PRICING['widget_turn'];
if(th_balance($aid) < $cost) th_json(402,['error'=>'owner_out_of_tokens','message'=>'Der Agent ist vorübergehend nicht verfügbar.']);

/* KI çağrısı (api.php ile aynı mantık; bağımsız tutuldu) */
$system = ch_agent_system($pl,$w,(string)($in['lang'] ?? ($w['lang'] ?: 'de')))."\n\nDu sprichst mit einem Website-Besucher (nicht mit dem Betreiber). Kurz, hilfreich, max. 6 Sätze. Wenn du etwas nicht weißt: ehrlich sagen und Kontaktaufnahme anbieten.";
$t0=microtime(true);
require_once __DIR__.'/agent-tools.php';   // Werkzeuge aller aktiven Module (Termine, Bestellungen, Taxi, Hotel, Rückruf)
ch_heartbeat();
$tk=ch_toolkit($aid,'widget');
if($tk['tools']){ $system.=$tk['hint']; $answer=th_ai_tools($system,$clean,$tk['tools'],$tk['exec'],900,75); $cost+=2*count(th_ai_info()['tool_calls'] ?? []); }
else $answer = th_ai($system, $clean, 700, 60);   // Claude model zinciri → DeepSeek (tokens-lib)
if($answer===null) th_json(503,['error'=>'ai_unavailable']);
th_debit($aid,$cost,'widget:'.$pl['id']);
th_log_usage($aid,'widget',$pl['sector'],$cost,(int)((microtime(true)-$t0)*1000));
th_json(200,['reply'=>$answer]);
