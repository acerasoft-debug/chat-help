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
 * Auth: Authorization: Bearer ch_live_…   Fiyat: sabit task tarifesi (plans.php CH_PRICING).
 * Model: CLAUDE_KEY varsa Claude, yoksa DeepSeek (db.php). Sektör/paket eklemek = plans.php'ye bir satır.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
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
    th_json(502,['error'=>'ai_unavailable','message'=>'KI-Backend nicht erreichbar (CLAUDE_KEY / CLAUDE_MODEL in db.php prüfen)','detail'=>th_ai_info()['errors'] ?? []]);
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
$hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$acc = th_auth(preg_replace('/^Bearer\s+/i','',trim($hdr)));
if(!$acc) th_json(401,['error'=>'unauthorized','message'=>'Authorization: Bearer ch_live_… fehlt oder ungültig','get_key'=>'https://chat-help.com/#tokens']);
$aid=(int)$acc['id'];
$b=body(); $lang=substr(preg_replace('/[^a-z]/','',strtolower($b['lang']??($_GET['lang']??'de'))),0,2) ?: 'de';
$sector=preg_replace('/[^a-z]/','',strtolower($b['sector']??'marketing')); if(!isset(SECTORS[$sector])) $sector='marketing';
$planId=preg_replace('/[^a-z0-9-]/','',strtolower($b['plan']??($_GET['plan']??'')));

switch(true){
  case $r==='me' && $m==='GET':
    $u=th_db()->prepare("SELECT task,sector,tokens,created_at FROM api_usage WHERE account_id=? ORDER BY id DESC LIMIT 15"); $u->execute([$aid]);
    $plans=[]; foreach(th_plans($aid) as $p){ $pl=ch_plan($p['plan']); if($pl) $plans[]=['id'=>$pl['id'],'name'=>$pl['name'],'kind'=>$pl['kind'],'sector'=>$pl['sector'],'status'=>$p['status'],'period_end'=>$p['period_end'],'tokens_month'=>$pl['tokens'],'active'=>in_array($p['status'],['active','trialing','past_due'],true)]; }
    th_json(200,['email'=>$acc['email'],'balance'=>th_balance($aid),'plans'=>$plans,'recent'=>$u->fetchAll(PDO::FETCH_ASSOC),'sectors'=>array_map(fn($k,$v)=>['id'=>$k,'name'=>$v['name']],array_keys(SECTORS),SECTORS)]);

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
    $pl=ch_plan($planId); if(!$pl) th_json(404,['error'=>'unknown_plan','plans'=>array_keys(CH_PLANS)]);
    if(!th_plan_active($aid,$planId)) th_json(403,['error'=>'plan_not_active','message'=>'Dieses Paket/dieser Agent ist für dein Konto nicht aktiv.','buy'=>'https://chat-help.com/chat/tokens-checkout.php?plan='.$planId]);
    $msgs=is_array($b['messages']??null)?$b['messages']:[]; if(!$msgs && !empty($b['goal'])) $msgs=[['role'=>'user','content'=>(string)$b['goal']]];
    $cfg=th_agent_cfg($aid,$planId);
    $chars=array_sum(array_map(fn($x)=>mb_strlen((string)($x['content']??'')),$msgs));
    $cost=PRICING['agent_turn'] + (int)ceil($chars/2000);          // 6 Token/Antwort + 1 je 2.000 Zeichen Kontext
    charged($acc,$cost,'agent:'.$planId,$pl['sector'],fn()=>ai_chat(ch_agent_system($pl,$cfg,$lang),$msgs,2200),['plan'=>$planId]);

  /* ---- Sektör ajanı tek tur (plan gerekmez) ---- */
  case $r==='agent' && $m==='POST':
    $goal=trim((string)($b['goal']??'')); $msgs=is_array($b['messages']??null)?$b['messages']:[];
    if($goal==='' && !$msgs) th_json(400,['error'=>'goal_or_messages_required']);
    $ctx=trim((string)($b['context']??''));
    if(!$msgs) $msgs=[['role'=>'user','content'=>"Ziel: $goal\n\nKontext:\n$ctx"]];
    charged($acc,PRICING['agent_run'],'agent_run',$sector,fn()=>ai_chat(sys_for($sector,$lang)." Arbeite als Agent: Ziel analysieren, kurzer Plan, dann das fertige Ergebnis liefern.",$msgs,2200));

  /* ---- Agent ayarları ---- */
  case $r==='agent/config' && $m==='GET':
    if($planId==='') th_json(400,['error'=>'plan_required']);
    if(!th_plan_active($aid,$planId)) th_json(403,['error'=>'plan_not_active']);
    $c=th_agent_cfg($aid,$planId); unset($c['id'],$c['account_id']);
    th_json(200,['config'=>$c,'widget_snippet'=>$c['widget_key']?'<script src="https://chat-help.com/chat/widget.js" data-key="'.$c['widget_key'].'" async></script>':null]);

  case $r==='agent/config' && $m==='POST':
    if($planId==='') th_json(400,['error'=>'plan_required']);
    if(!th_plan_active($aid,$planId)) th_json(403,['error'=>'plan_not_active']);
    th_agent_cfg_save($aid,$planId,$b);
    $c=th_agent_cfg($aid,$planId); unset($c['id'],$c['account_id']);
    th_json(200,['ok'=>true,'config'=>$c,'widget_snippet'=>'<script src="https://chat-help.com/chat/widget.js" data-key="'.$c['widget_key'].'" async></script>']);

  default:
    th_json(404,['error'=>'not_found','routes'=>['GET /v1/sectors','GET /v1/me','GET /v1/balance','POST /v1/generate','POST /v1/translate','POST /v1/subtitles','POST /v1/agent','GET|POST /v1/agent/config']]);
}
