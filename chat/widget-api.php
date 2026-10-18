<?php
/**
 * ChatHelp — Website-Widget Backend (herkese açık, widget anahtarı ile)
 * =====================================================================
 * POST /chat/widget-api.php  {key:"wk_…", messages:[{role,content}], lang}
 *  - widget_key → agent_configs (widget_on=1) → hesap + plan; plan aktif olmalı
 *  - İzinli alan adı tanımlıysa Origin/Referer eşleşmeli
 *  - Hesap başına dakikada 60 mesaj sınırı; tokenlar agent SAHİBİNİN defterinden düşer (widget_turn)
 * GET  /chat/widget-api.php?key=wk_…[&lang=xx]  → widget için başlık/karşılama (JSON): greeting in der Sprache des Besuchers (?lang=, Accept-Language,
 *      CF-IPCountry – nur erlaubte Sprachen, sonst Standardsprache), greetings{code:text} für alle erlaubten Sprachen, langs[{code,name}].
 * POST: Antwortsprache je Nachricht erkannt (i18n.php ch_reply_lang): letzte Kundennachricht → frühere Kundennachrichten → lang des Widgets (Browser) → Standard.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }
th_bootstrap(); ch_i18n_bootstrap();

$in = json_decode(file_get_contents('php://input'),true); if(!is_array($in)) $in=[];
$key = (string)($in['key'] ?? ($_GET['key'] ?? ''));
$w = th_widget($key); if(!$w) th_json(403,['error'=>'widget_disabled','message'=>'Widget nicht aktiv oder Schlüssel ungültig.']);
$pl = ch_plan($w['plan']); if(!$pl) th_json(404,['error'=>'unknown_plan']);
$aid=(int)$w['account_id'];
if(!th_plan_active($aid,$pl['id'])) th_json(402,['error'=>'plan_inactive','message'=>t('ch.inactive',ch_page_lang(ch_cfg_langs($w),ch_cfg_default($w)))]);

/* Alan adı kontrolü (isteğe bağlı) */
if(!empty($w['widget_domain'])){
    $allowed = array_filter(array_map('trim',preg_split('/[\s,]+/',strtolower($w['widget_domain']))));
    $origin = strtolower(parse_url($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? ''),PHP_URL_HOST) ?? '');
    $ok=false; foreach($allowed as $d){ $d=preg_replace('#^https?://#','',$d); $d=rtrim($d,'/'); if($origin===$d || ($d!=='' && str_ends_with($origin,'.'.$d))) $ok=true; }
    if(!$ok) th_json(403,['error'=>'domain_not_allowed','origin'=>$origin]);
}

$allowed=ch_cfg_langs($w); $defLang=ch_cfg_default($w);
$company = $w['company'] ?: t('ch.team',$defLang);
if($_SERVER['REQUEST_METHOD']==='GET'){
    $vl=ch_page_lang($allowed,$defLang); $gr=[]; foreach($allowed as $l) $gr[$l]=t('ch.greet',$l,['company'=>$w['company'] ?: t('ch.team',$l)]);
    th_json(200,['ok'=>true,'title'=>$company,'agent'=>$pl['name'],'lang'=>$vl,'default_lang'=>$defLang,'greeting'=>$gr[$vl] ?? $gr[$defLang],'greetings'=>$gr,
      'langs'=>array_map(fn($l)=>['code'=>$l,'name'=>CH_LANGS[$l]['native'],'tag'=>CH_LANGS[$l]['tag']],$allowed)]);
}

/* Mengenlimits (öffentlicher Schlüssel → Schutz des Token-Guthabens): je Besucher-IP 30 Nachrichten / 10 Min, je Konto 60 / Min und 1.500 / Tag */
$hintLang=ch_lang_norm(is_string($in['lang'] ?? null) ? $in['lang'] : '');
if(!th_rate('w:'.$aid.':'.th_client_ip(),30,600) || !th_rate('wa:'.$aid,60,60) || !th_rate('wd:'.$aid,1500,86400)) th_json(429,['error'=>'rate_limited','message'=>t('ch.wait',$hintLang ?: $defLang)]);

$msgs=is_array($in['messages']??null)?array_slice($in['messages'],-12):[];
$clean=[]; foreach($msgs as $m){ $role=($m['role']??'user')==='assistant'?'assistant':'user'; $t=trim((string)($m['content']??'')); if($t!=='') $clean[]=['role'=>$role,'content'=>mb_substr($t,0,3000)]; }
if(!$clean || end($clean)['role']!=='user') th_json(400,['error'=>'messages_required']);

/* Gesprächssprache: frühere Kundennachrichten (sicher erkannt) → Browser-Sprache des Widgets → Standard; dann die letzte Nachricht */
$prevLang=''; foreach(array_slice($clean,0,-1) as $m){ if($m['role']!=='user') continue; $ps=false; $pd=ch_detect_lang($m['content'],'',$ps); if($ps && in_array($pd,$allowed,true)) $prevLang=$pd; }
$replyLang=ch_reply_lang($w,end($clean)['content'],$prevLang,$hintLang);
$cost=CH_PRICING['widget_turn'];
if(th_balance($aid) < $cost) th_json(402,['error'=>'owner_out_of_tokens','message'=>t('ch.na',$replyLang)]);

/* KI çağrısı (api.php ile aynı mantık; bağımsız tutuldu) */
$system = ch_agent_system($pl,$w,$replyLang,true)."\n\nDu sprichst mit einem Website-Besucher (nicht mit dem Betreiber). Kurz, hilfreich, max. 6 Sätze. Wenn du etwas nicht weißt: ehrlich sagen und Kontaktaufnahme anbieten.";
$t0=microtime(true);
require_once __DIR__.'/agent-tools.php';   // Werkzeuge aller aktiven Module (Termine, Bestellungen, Taxi, Hotel, Rückruf)
ch_heartbeat();
$tk=ch_toolkit($aid,'widget');
if($tk['tools']){ $system.=$tk['hint']; $answer=th_ai_tools($system,$clean,$tk['tools'],$tk['exec'],900,75); $cost+=2*count(th_ai_info()['tool_calls'] ?? []); }
else $answer = th_ai($system, $clean, 700, 60);   // Claude model zinciri → DeepSeek (tokens-lib)
if($answer===null) th_json(503,['error'=>'ai_unavailable']);
$cost=th_charge($aid,$cost,'widget:'.$pl['id']);
th_log_usage($aid,'widget',$pl['sector'],$cost,(int)((microtime(true)-$t0)*1000));
th_json(200,['reply'=>$answer,'lang'=>$replyLang]);
