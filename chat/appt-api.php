<?php
/**
 * ChatHelp — Öffentliche Buchungs-API (für book.php / appt.php), Schlüssel k = Buchungsschlüssel (widget_key) oder "demo"
 *   GET  appt-api.php?k=…&a=info                 → Betrieb, Leistungen, Öffnungszeiten, nächste freie Tage
 *   GET  appt-api.php?k=…&a=slots&date=…&service=… → freie Zeiten
 *   POST appt-api.php?k=…&a=book  {service_id,start,name,phone,email,note}
 *   GET  appt-api.php?a=booking&c=CODE           → Buchung anzeigen (ohne k)
 *   POST appt-api.php?a=cancel {code}             → absagen
 * Limit: 40 Anfragen je IP und 10 Minuten.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/appt-lib.php';
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }
th_bootstrap(); appt_bootstrap();
$in=json_decode(file_get_contents('php://input'),true); if(!is_array($in)) $in=[];
$a=preg_replace('/[^a-z_]/','',(string)($_GET['a'] ?? $in['a'] ?? 'info'));
/* Rate-Limit */
$p=th_db(); $p->exec("CREATE TABLE IF NOT EXISTS concierge_log (id INT AUTO_INCREMENT PRIMARY KEY, ip_hash CHAR(32) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(ip_hash,created_at), INDEX(created_at)) ENGINE=InnoDB");
$ip=$_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0')); $h=md5('appt|'.trim(explode(',',$ip)[0]));
$s=$p->prepare("SELECT COUNT(*) FROM concierge_log WHERE ip_hash=? AND created_at > (NOW() - INTERVAL 10 MINUTE)"); $s->execute([$h]);
if((int)$s->fetchColumn() >= 40) th_json(429,['error'=>'rate_limited','message'=>'Bitte einen Moment warten.']);
if(in_array($a,['book','cancel'],true)) $p->prepare("INSERT INTO concierge_log(ip_hash) VALUES(?)")->execute([$h]);
/* Termin-Erinnerungen lazy mitlaufen lassen (höchstens alle 10 Min, atomar) – erst nach Auslieferung der Antwort, damit die Buchungsseite nicht wartet */
if(in_array($a,['info','slots','days'],true)) register_shutdown_function(function(){ ignore_user_abort(true); if(function_exists('fastcgi_finish_request')) @fastcgi_finish_request(); elseif(function_exists('litespeed_finish_request')) @litespeed_finish_request(); try{ appt_reminders_lazy(); }catch(Throwable $e){ error_log('CH appt reminders: '.$e->getMessage()); } });

if($a==='booking'){ $b=appt_by_code((string)($_GET['c'] ?? $in['c'] ?? '')); if(!$b) th_json(404,['error'=>'not_found']); $st=appt_settings((int)$b['account_id']); th_json(200,['booking'=>appt_public($b,$st),'ics_url'=>'https://chat-help.com/chat/appt.php?c='.$b['code'].'&ics=1']); }
if($a==='cancel'){ $res=appt_cancel((string)($in['code'] ?? $_GET['c'] ?? '')); th_json(!isset($res['error'])?200:($res['error']==='not_found'?404:409),$res); }   // too_late / not_cancellable → 409 mit hint

$k=(string)($_GET['k'] ?? $in['k'] ?? ''); $acc=appt_account_by_key($k);
if($acc===null) th_json(404,['error'=>'unknown_key','message'=>'Buchungsseite nicht gefunden.']);
$set=appt_settings($acc);
if(!$set['appt_on']) th_json(403,['error'=>'booking_disabled','message'=>'Online-Buchung ist derzeit nicht aktiv.','company'=>$set['company']]);

/* Chat auf der Buchungsseite: der echte Termin-Agent DIESES Betriebs (Tokens des Betreibers). Demo-Seite → concierge.php */
function appt_chat_plan(int $acc, string $k): ?array {
    if($acc===0) return null; require_once __DIR__.'/plans.php'; require_once __DIR__.'/agent-tools.php';
    $cs=th_db()->prepare("SELECT * FROM agent_configs WHERE widget_key=?"); $cs->execute([$k]); $cfg=$cs->fetch(PDO::FETCH_ASSOC); if(!$cfg) return null;
    $plan=ch_channel_plan($acc,(string)$cfg['plan'],['appt']); if(!$plan || !($pl=ch_plan($plan))) return null;
    if(th_balance($acc) < CH_PRICING['widget_turn']) return null;
    return ['plan'=>$pl,'cfg'=>$cfg];
}
if($a==='chat' && $_SERVER['REQUEST_METHOD']==='POST'){
    $cp=appt_chat_plan($acc,$k); if(!$cp) th_json(402,['error'=>'chat_inactive','message'=>'Der Chat ist gerade nicht verfügbar – bitte oben direkt buchen.']);
    $p->prepare("INSERT INTO concierge_log(ip_hash) VALUES(?)")->execute([$h]);
    $c=$p->prepare("SELECT COUNT(*) FROM api_usage WHERE account_id=? AND task='booking' AND created_at > (NOW() - INTERVAL 1 MINUTE)"); $c->execute([$acc]);
    if((int)$c->fetchColumn() >= 40) th_json(429,['error'=>'rate_limited','message'=>'Bitte einen Moment warten.']);
    $clean=[]; foreach(array_slice(is_array($in['messages']??null)?$in['messages']:[],-12) as $m){ $t=trim((string)($m['content']??'')); if($t!=='') $clean[]=['role'=>($m['role']??'')==='assistant'?'assistant':'user','content'=>mb_substr($t,0,1500)]; }
    if(!$clean || end($clean)['role']!=='user') th_json(400,['error'=>'messages_required']);
    $pl=$cp['plan']; $t0=microtime(true);
    $system=ch_agent_system($pl,$cp['cfg'],'de')."\n\nDu bist der Buchungsassistent auf der Online-Terminseite von ".($set['company'] ?: 'diesem Betrieb').". Du sprichst mit einem Kunden. Kurz und herzlich, höchstens 4 Sätze. Termine nur über die Werkzeuge; vor dem Buchen alles zusammenfassen und bestätigen lassen.";
    $tk=ch_toolkit($acc,'booking','',['appt']); $system.=$tk['hint'];
    $answer=th_ai_tools($system,$clean,$tk['tools'],$tk['exec'],700,45);
    if($answer===null) th_json(503,['error'=>'ai_unavailable','message'=>'Gerade nicht erreichbar – bitte oben direkt buchen.']);
    $cost=CH_PRICING['widget_turn']+2*count(th_ai_info()['tool_calls'] ?? []);
    $charged=th_charge($acc,$cost,'booking:'.$pl['id']); th_log_usage($acc,'booking',$pl['sector'],$charged,(int)((microtime(true)-$t0)*1000));
    th_json(200,['reply'=>$answer]);
}
if($a==='info'){
    $svc=appt_services($acc); $tz=appt_tz($set);
    th_json(200,['company'=>$set['company'],'address'=>$set['address'],'tz'=>$set['tz'],'today'=>(new DateTimeImmutable('today',$tz))->format('Y-m-d'),'max_days'=>$set['max_days'],'hours'=>$set['hours'],'services'=>array_map(fn($x)=>['id'=>$x['id'],'name'=>$x['name'],'duration_min'=>$x['duration_min'],'price_cents'=>$x['price_cents']],$svc),'demo'=>$acc===0,'chat'=>$acc===0 || appt_chat_plan($acc,$k)!==null]);
}
$sid=isset($_GET['service'])&&$_GET['service']!==''?(int)$_GET['service']:null;
if($a==='slots'){ $r=appt_slots($acc,(string)($_GET['date'] ?? ''),$sid); th_json(isset($r['error'])?422:200,$r); }
if($a==='days'){ [, , $err]=appt_resolve_service($acc,$sid,$set); if($err) th_json(422,$err+['days'=>[]]); th_json(200,['days'=>appt_next_days($acc,$sid,14)]); }
if($a==='book' && $_SERVER['REQUEST_METHOD']==='POST'){
    $res=appt_book($acc,isset($in['service_id'])&&$in['service_id']!==''?(int)$in['service_id']:null,(string)($in['start']??''),(string)($in['name']??''),(string)($in['phone']??''),(string)($in['email']??''),(string)($in['note']??''),'web');
    th_json(isset($res['error'])?422:200,$res);
}
th_json(400,['error'=>'bad_request']);
