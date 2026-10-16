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

if($a==='booking'){ $b=appt_by_code((string)($_GET['c'] ?? $in['c'] ?? '')); if(!$b) th_json(404,['error'=>'not_found']); $st=appt_settings((int)$b['account_id']); th_json(200,['booking'=>appt_public($b,$st),'ics_url'=>'https://chat-help.com/chat/appt.php?c='.$b['code'].'&ics=1']); }
if($a==='cancel'){ $res=appt_cancel((string)($in['code'] ?? $_GET['c'] ?? '')); th_json(isset($res['error'])?404:200,$res); }

$k=(string)($_GET['k'] ?? $in['k'] ?? ''); $acc=appt_account_by_key($k);
if($acc===null) th_json(404,['error'=>'unknown_key','message'=>'Buchungsseite nicht gefunden.']);
$set=appt_settings($acc);
if(!$set['appt_on']) th_json(403,['error'=>'booking_disabled','message'=>'Online-Buchung ist derzeit nicht aktiv.','company'=>$set['company']]);

if($a==='info'){
    $svc=appt_services($acc); $tz=appt_tz($set);
    th_json(200,['company'=>$set['company'],'address'=>$set['address'],'tz'=>$set['tz'],'today'=>(new DateTimeImmutable('today',$tz))->format('Y-m-d'),'max_days'=>$set['max_days'],'hours'=>$set['hours'],'services'=>array_map(fn($x)=>['id'=>$x['id'],'name'=>$x['name'],'duration_min'=>$x['duration_min'],'price_cents'=>$x['price_cents']],$svc),'demo'=>$acc===0,'agent_key'=>$k]);
}
if($a==='slots'){ th_json(200,appt_slots($acc,(string)($_GET['date'] ?? ''),isset($_GET['service'])&&$_GET['service']!==''?(int)$_GET['service']:null)); }
if($a==='days'){ th_json(200,['days'=>appt_next_days($acc,isset($_GET['service'])&&$_GET['service']!==''?(int)$_GET['service']:null,14)]); }
if($a==='book' && $_SERVER['REQUEST_METHOD']==='POST'){
    $res=appt_book($acc,isset($in['service_id'])&&$in['service_id']!==''?(int)$in['service_id']:null,(string)($in['start']??''),(string)($in['name']??''),(string)($in['phone']??''),(string)($in['email']??''),(string)($in['note']??''),'web');
    th_json(isset($res['error'])?422:200,$res);
}
th_json(400,['error'=>'bad_request']);
