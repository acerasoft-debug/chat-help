<?php
/**
 * ChatHelp — Telefonnummern aus dem ChatHelp-Twilio-Konto („ChatHelp-Nummer“: sofort startklar, ohne eigenes Twilio-Konto des Kunden)
 * ===================================================================================================================================
 * Plattform-Konto: TWILIO_ACCOUNT_SID + TWILIO_AUTH_TOKEN (db.php bzw. ops/keybox). Anrufe auf diese Nummern prüft phone-webhook.php schon heute
 * mit TWILIO_AUTH_TOKEN; Rückrufe nutzen th_twilio() (Plattform-Konto) mit der Agentennummer als Absender.
 * Ortsnummern brauchen je Land bei Twilio ein Regulatory Bundle und eine Adresse: TWILIO_BUNDLE_<CC> / TWILIO_ADDRESS_<CC> (z. B. TWILIO_BUNDLE_DE).
 *
 * Tabelle phone_numbers: höchstens eine aktive Nummer je Konto + Agent (base); Kauf über IncomingPhoneNumbers, Anrufe → phone-webhook.php?key=<widget_key>,
 * agent_configs.phone_number = Nummer, phone_on = 1. Freigabe (num_release) bei Kündigung oder auf Wunsch.
 * Nur mit gebuchtem Paket (Nummern kosten monatlich) – in der Testphase merkt sich die Einrichtung den Wunsch, die Zahlung löst den Kauf aus.
 *
 * Studio-REST (CH_MODULE_LIBS['numbers']):
 *   GET  /v1/numbers                                 → {ready, numbers:[{plan,number,display,country,created_at}]}
 *   GET  /v1/numbers/search?country=DE&area=0221     → {numbers:[{number,display,locality,region}]}
 *   POST /v1/numbers/buy     {plan, number?, area?}  → Nummer kaufen + zuweisen (ohne number: erste freie mit der Vorwahl)
 *   POST /v1/numbers/release {plan}                  → Nummer freigeben
 * Tests: NUM_TWILIO_API (nur 127.0.0.1/localhost) lenkt die Twilio-Aufrufe auf eine Attrappe.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';

const NUM_SITE = 'https://chat-help.com';
/* Länder, in denen wir Ortsnummern vergeben (Ländervorwahl); die Vorwahl des Kunden wird ohne führende 0 gesucht */
const NUM_CC = ['DE'=>'49','AT'=>'43','CH'=>'41','NL'=>'31','BE'=>'32','FR'=>'33','ES'=>'34','IT'=>'39','GB'=>'44','SE'=>'46','NO'=>'47','FI'=>'358','DK'=>'45','PL'=>'48','US'=>'1','CA'=>'1'];

function num_bootstrap(): void {
    static $done=false; if($done) return; $done=true;
    th_db()->exec("CREATE TABLE IF NOT EXISTS phone_numbers (id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, base VARCHAR(40) NOT NULL, e164 VARCHAR(20) NOT NULL,
        country CHAR(2) NOT NULL, twilio_sid VARCHAR(40) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'active', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, released_at DATETIME NULL,
        UNIQUE KEY uq_sid (twilio_sid), INDEX(account_id, base, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** Plattform-Konto (null = nicht eingerichtet → Einrichtung fragt die Nummer wie bisher per E-Mail an). */
function num_master(): ?array {
    $s=defined('TWILIO_ACCOUNT_SID') ? trim((string)TWILIO_ACCOUNT_SID) : ''; $t=defined('TWILIO_AUTH_TOKEN') ? trim((string)TWILIO_AUTH_TOKEN) : '';
    return preg_match('/^AC[a-f0-9]{32}$/',$s) && $t!=='' ? ['sid'=>$s,'token'=>$t] : null;
}
function num_ready(): bool { return num_master()!==null; }
function num_base(): string { $b=defined('NUM_TWILIO_API') ? rtrim((string)NUM_TWILIO_API,'/') : ''; return $b!=='' && preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?$#',$b) ? $b : 'https://api.twilio.com'; }
function num_http(string $method, string $path, array $data=[]): array {
    $m=num_master(); if(!$m) return ['code'=>0,'json'=>null,'err'=>'not_configured'];
    $url=num_base().'/2010-04-01/Accounts/'.rawurlencode($m['sid']).$path.($method==='GET' && $data ? '?'.http_build_query($data) : '');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_USERPWD=>$m['sid'].':'.$m['token'],CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_NOPROXY=>'127.0.0.1,localhost']);
    if($method==='POST') curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));
    $raw=(string)curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $j=json_decode($raw,true); $msg=is_array($j) ? (string)($j['message'] ?? '') : '';
    if($code<200 || $code>=300) error_log('CH num twilio '.$method.' '.preg_replace('#/Accounts/[^/]+#','/Accounts/…',$path).' '.$code.' '.mb_substr($msg ?: $err,0,200));
    return ['code'=>$code,'json'=>is_array($j) ? $j : null,'err'=>$code>=200 && $code<300 ? '' : ($msg ?: ($err ?: 'HTTP '.$code))];
}
/** Land einer E.164-Nummer (längste passende Ländervorwahl). */
function num_cc(string $e164): ?string { $best=null; foreach(NUM_CC as $cc=>$p) if(str_starts_with($e164,'+'.$p) && (!$best || strlen($p)>strlen(NUM_CC[$best]))) $best=$cc; return $best; }
/** +49 221 9876 5432 – Ländervorwahl, dann 3er-, danach 4er-Gruppen (lesbar, ohne Ortsnetz-Tabelle). */
function num_display(string $e164): string {
    $cc=num_cc($e164); if(!$cc) return $e164; $p=NUM_CC[$cc]; $rest=substr($e164,1+strlen($p));
    $g=[substr($rest,0,3)]; foreach(str_split(substr($rest,3),4) as $x) if($x!=='') $g[]=$x; return '+'.$p.' '.implode(' ',$g);
}
/** Vorwahl aus Eingaben wie „Köln (0221)“, „0221“, „+49 221“, „0049221“ → „221“. */
function num_area(string $area, string $cc): string {
    $p=NUM_CC[$cc] ?? ''; $s=trim($area); $d=(string)preg_replace('/\D/','',$s);
    if($p!=='' && str_starts_with($d,'00'.$p)) $d=substr($d,2+strlen($p)); elseif($p!=='' && str_contains($s,'+') && str_starts_with($d,$p)) $d=substr($d,strlen($p));
    return substr(ltrim($d,'0'),0,6);
}
/** Freie Ortsnummern mit Sprachanrufen. $area leer = beliebig im Land. */
function num_search(string $cc, string $area='', int $n=6): array {
    $cc=strtoupper($cc); if(!isset(NUM_CC[$cc])) return ['error'=>'country','message'=>'Für dieses Land vergeben wir noch keine Nummern.'];
    if(!num_ready()) return ['error'=>'not_configured','message'=>'ChatHelp-Nummern sind noch nicht freigeschaltet.'];
    $a=num_area($area,$cc); $q=['VoiceEnabled'=>'true','PageSize'=>max(1,min(20,$n))]; if($a!=='') $q['Contains']='+'.NUM_CC[$cc].$a.'*';
    $r=num_http('GET','/AvailablePhoneNumbers/'.$cc.'/Local.json',$q);
    if($r['code']!==200) return ['error'=>'search_failed','message'=>'Die Nummernsuche ist gerade nicht erreichbar – bitte gleich noch einmal.'];
    $out=[]; foreach(($r['json']['available_phone_numbers'] ?? []) as $x){ $e=(string)($x['phone_number'] ?? ''); if(!preg_match('/^\+\d{8,15}$/',$e)) continue;
        $out[]=['number'=>$e,'display'=>num_display($e),'locality'=>(string)($x['locality'] ?? ($x['rate_center'] ?? '')),'region'=>(string)($x['region'] ?? '')]; }
    return ['numbers'=>$out,'country'=>$cc,'area'=>$a];
}
function num_get(int $aid, string $base): ?array {
    num_bootstrap(); $s=th_db()->prepare("SELECT * FROM phone_numbers WHERE account_id=? AND base=? AND status='active' ORDER BY id DESC LIMIT 1"); $s->execute([$aid,$base]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
function num_list(int $aid): array {
    num_bootstrap(); $s=th_db()->prepare("SELECT * FROM phone_numbers WHERE account_id=? AND status='active' ORDER BY id"); $s->execute([$aid]);
    return array_map(fn($r)=>['plan'=>$r['base'],'number'=>$r['e164'],'display'=>num_display($r['e164']),'country'=>$r['country'],'created_at'=>th_dbiso($r['created_at'])],$s->fetchAll(PDO::FETCH_ASSOC));
}
/**
 * Nummer kaufen und dem Agenten zuweisen. $number leer → erste freie Nummer mit der Vorwahl $area (sonst beliebig im Land $cc).
 * Nur mit gebuchtem Paket und Telefonkanal; eine Nummer je Agent. Rückgabe ['ok','number','display'] oder ['error','message'].
 */
function num_buy(int $aid, string $planId, string $number='', string $area='', string $cc='DE'): array {
    num_bootstrap(); $pl=ch_plan($planId); if(!$pl || $aid<=0) return ['error'=>'plan','message'=>'Unbekannter Agent.'];
    $base=(string)$pl['base'];
    if(!in_array('phone',ch_plan_channels($base),true)) return ['error'=>'no_phone','message'=>'Dieser Agent hat keinen Telefonkanal.'];
    if(!th_plan_active($aid,$base)) return ['error'=>'plan_inactive','message'=>'Die ChatHelp-Nummer gibt es mit dem gebuchten Paket – direkt nach der Buchung ist sie da.'];
    if(!num_ready()) return ['error'=>'not_configured','message'=>'ChatHelp-Nummern sind noch nicht freigeschaltet.'];
    if(!th_lock('num:'.$aid.':'.$base,10)) return ['error'=>'busy','message'=>'Bitte einen Moment – die Nummer wird gerade eingerichtet.'];
    if($cur=num_get($aid,$base)) return ['ok'=>true,'number'=>$cur['e164'],'display'=>num_display($cur['e164']),'existing'=>true];
    $cc=strtoupper($cc); if(!isset(NUM_CC[$cc])) $cc='DE';
    if($number!==''){ $e=th_e164($number,NUM_CC[$cc]); if($e==='' || !preg_match('/^\+\d{8,15}$/',$e)) return ['error'=>'invalid','message'=>'Ungültige Nummer.']; $cc=num_cc($e) ?? $cc; }
    else {
        $s=num_search($cc,$area,1); if(isset($s['error'])) return $s;
        if(!$s['numbers'] && $s['area']!==''){ $s=num_search($cc,'',1); if(isset($s['error'])) return $s; }   // Vorwahl ausverkauft → andere Ortsnummer im Land
        if(!$s['numbers']) return ['error'=>'none','message'=>'Gerade ist keine passende Nummer frei – bitte später erneut oder eine andere Vorwahl.'];
        $e=$s['numbers'][0]['number'];
    }
    $cfg=th_agent_cfg($aid,$base); if(empty($cfg['widget_key'])){ th_agent_cfg_save($aid,$base,[],true); $cfg=th_agent_cfg($aid,$base); }
    $d=['PhoneNumber'=>$e,'FriendlyName'=>mb_substr('ChatHelp #'.$aid.' '.$base,0,64),'VoiceUrl'=>NUM_SITE.'/chat/phone-webhook.php?key='.rawurlencode((string)$cfg['widget_key']),'VoiceMethod'=>'POST'];
    foreach(['BundleSid'=>'TWILIO_BUNDLE_','AddressSid'=>'TWILIO_ADDRESS_'] as $f=>$c) if(defined($c.$cc) && trim((string)constant($c.$cc))!=='') $d[$f]=trim((string)constant($c.$cc));
    $r=num_http('POST','/IncomingPhoneNumbers.json',$d);
    $sid=(string)($r['json']['sid'] ?? '');
    if($r['code']<200 || $r['code']>=300 || !preg_match('/^PN[a-f0-9]{32}$/',$sid))
        return ['error'=>'buy_failed','message'=>'Die Nummer konnte nicht vergeben werden'.($r['err']!=='' ? ': '.mb_substr($r['err'],0,160) : '.').' Bitte eine andere wählen.'];
    th_db()->prepare("INSERT INTO phone_numbers(account_id,base,e164,country,twilio_sid) VALUES(?,?,?,?,?)
        ON DUPLICATE KEY UPDATE account_id=VALUES(account_id), base=VALUES(base), e164=VALUES(e164), country=VALUES(country), status='active', released_at=NULL, created_at=NOW()")->execute([$aid,$base,$e,$cc,$sid]);
    th_db()->prepare("UPDATE agent_configs SET phone_number=?, phone_on=1, updated_at=NOW() WHERE account_id=? AND plan=?")->execute([$e,$aid,$base]);
    if(function_exists('ch_push')) try{ ch_push($aid,'system','Deine Nummer ist da: '.num_display($e),'Dein Agent nimmt ab sofort Anrufe an. Bestehende Nummer? Einfach auf diese weiterleiten.','/chat/studio.php#phone',['email'=>false,'tag'=>'num-'.$base]); }catch(Throwable $x){}
    return ['ok'=>true,'number'=>$e,'display'=>num_display($e)];
}
/** Nummer freigeben (Kündigung oder Wunsch): bei Twilio löschen, Agent ohne Nummer. */
function num_release(int $aid, string $base): array {
    $n=num_get($aid,$base); if(!$n) return ['error'=>'not_found','message'=>'Dieser Agent hat keine ChatHelp-Nummer.'];
    $r=num_http('DELETE','/IncomingPhoneNumbers/'.rawurlencode($n['twilio_sid']).'.json');
    if(!in_array($r['code'],[200,204,404],true)) return ['error'=>'release_failed','message'=>'Die Nummer konnte gerade nicht freigegeben werden – bitte später erneut.'];
    th_db()->prepare("UPDATE phone_numbers SET status='released', released_at=NOW() WHERE id=?")->execute([$n['id']]);
    th_db()->prepare("UPDATE agent_configs SET phone_number='', phone_on=0, updated_at=NOW() WHERE account_id=? AND plan=? AND phone_number=?")->execute([$aid,$base,$n['e164']]);
    return ['ok'=>true,'released'=>$n['e164']];
}
/** Kündigung (tokens-webhook): Nummern von Agenten ohne aktives Paket freigeben – sonst zahlen wir sie weiter. */
function num_release_inactive(int $aid): array {
    num_bootstrap(); $out=[];
    foreach(num_list($aid) as $n) if(!th_plan_active($aid,$n['plan'])){ $r=num_release($aid,$n['plan']); $out[$n['plan']]=!empty($r['ok']); }
    return $out;
}

/* ======================================================================= Studio-REST */
function num_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if($route!=='numbers' && !str_starts_with($route,'numbers/')) return null;
    if($aid<=0) return [403,['error'=>'forbidden']];
    try{
        num_bootstrap();
        if($route==='numbers' && $method==='GET') return [200,['ready'=>num_ready(),'numbers'=>num_list($aid),'countries'=>array_keys(NUM_CC)]];
        if($route==='numbers/search' && $method==='GET'){
            if(!th_rate('num:search:'.$aid,60,3600)) return [429,['error'=>'rate_limited','message'=>'Bitte kurz warten.']];
            $r=num_search((string)($_GET['country'] ?? 'DE'),(string)($_GET['area'] ?? ''),6); return [isset($r['error']) ? ($r['error']==='not_configured' ? 501 : 422) : 200,$r];
        }
        if($route==='numbers/buy' && $method==='POST'){
            if(!th_rate('num:buy:'.$aid,10,3600)) return [429,['error'=>'rate_limited','message'=>'Bitte kurz warten.']];
            $r=num_buy($aid,(string)($body['plan'] ?? ''),(string)($body['number'] ?? ''),(string)($body['area'] ?? ''),(string)($body['country'] ?? 'DE'));
            return [isset($r['error']) ? ($r['error']==='plan_inactive' ? 402 : ($r['error']==='not_configured' ? 501 : 422)) : 200,$r];
        }
        if($route==='numbers/release' && $method==='POST'){ $pl=ch_plan((string)($body['plan'] ?? '')); if(!$pl) return [400,['error'=>'plan']]; $r=num_release($aid,(string)$pl['base']); return [isset($r['error']) ? 422 : 200,$r]; }
    }catch(Throwable $e){ error_log('CH num_api '.$route.': '.$e->getMessage()); return [500,['error'=>'server_error','message'=>'Interner Fehler – bitte erneut versuchen.']]; }
    return [404,['error'=>'not_found','routes'=>['GET numbers','GET numbers/search','POST numbers/buy','POST numbers/release']]];
}
