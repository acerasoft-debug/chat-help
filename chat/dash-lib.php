<?php
/**
 * ChatHelp — Übersicht des Betriebs (Studio-Startseite)
 * ======================================================
 * Kennzahlen, Verlauf und alle Vorgänge eines Kontos aus den vorhandenen Tabellen (api_usage, phone_calls, wa_sessions, Module, token_ledger)
 * plus Website-Chats (widget_chats, hier gespeichert: letzte 40 Nachrichten je Besuch, 90 Tage).
 *   GET dash/summary?days=1|7|30|90      Kennzahlen + Vorperiode, Antworten je Tag und Kanal, Stoßzeiten (Wochentag × Stunde), Anruf-Ergebnisse,
 *                                        erledigte Vorgänge mit Umsatz, offene Vorgänge, Stammkunden, Guthaben-Reichweite
 *   GET dash/activity?days=&type=&channel=&q=&limit=&offset=   alle Vorgänge neueste zuerst (+ Zähler je Art für die Filter)
 *   GET dash/item?id=<art>:<id>          Einzelheiten (Felder, Nachrichten/Transkript)
 *   GET dash/export?…                    wie activity, als CSV {filename,csv}
 * Zeiträume sind rollierend (letzte N×24 Stunden), Tage im Verlauf in der Zeitzone des Betriebs.
 */
require_once __DIR__.'/tokens-lib.php';

const DASH_DAYS = [1,7,30,90];
const DASH_WEB_KEEP_DAYS = 90;
/** Geschätzte Zeit, die der Agent dem Team abnimmt (Minuten): je Chat-Antwort, Zuschlag je Anruf (zur Gesprächsdauer), je erledigtem Vorgang. */
const DASH_SAVE_MIN = ['chat'=>1.5,'call'=>1.0,'action'=>3.0];
/** Arten im Verlauf → Filtergruppe */
const DASH_GROUPS = ['call'=>'conv','whatsapp'=>'conv','web'=>'conv','order'=>'order','appt'=>'booking','hotel'=>'booking','ride'=>'ride','hotel_request'=>'request','callback'=>'request','ledger'=>'billing','plan'=>'billing','onb'=>'system','num'=>'system'];
/** Gutschriften/Abbuchungen, die im Verlauf als eigener Eintrag erscheinen (die vielen Abbuchungen je Antwort stehen in den Kennzahlen) */
const DASH_LEDGER_SHOW = ['stripe:tokens','stripe:plan','stripe:renewal','trial','upgrade','admin:credit','admin:debit','site:generate','site:translate','train:test','train:gaps','train:import','train:site','train:docs'];

function dash_bootstrap(): void {
    static $done=false; if($done) return; $done=true;
    th_db()->exec("CREATE TABLE IF NOT EXISTS widget_chats (id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, plan VARCHAR(40) NOT NULL DEFAULT '', sid CHAR(32) NOT NULL,
        lang CHAR(2) NOT NULL DEFAULT '', messages MEDIUMTEXT NULL, turns INT NOT NULL DEFAULT 0, tools VARCHAR(255) NOT NULL DEFAULT '', page VARCHAR(190) NOT NULL DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_sid (account_id,sid), INDEX(account_id,updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** Abfrage, die bei fehlender Modultabelle leer bleibt. */
function dash_q(string $sql, array $args=[]): array { try{ $s=th_db()->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){ return []; } }
function dash_one(string $sql, array $args=[]): ?array { $r=dash_q($sql,$args); return $r[0] ?? null; }
function dash_tz(int $aid): DateTimeZone { return function_exists('contacts_tz') ? contacts_tz($aid) : new DateTimeZone('Europe/Berlin'); }
function dash_ts(?string $db): int { if(!$db) return 0; $d=th_dbtime($db,new DateTimeZone('UTC')); return $d ? $d->getTimestamp() : 0; }
function dash_iso(int $ts): ?string { return $ts>0 ? gmdate('Y-m-d\TH:i:s\Z',$ts) : null; }
/** api_usage.task → Kanal (null = intern: Assistent, Training, Website-Baukasten) */
function dash_chan(string $task): ?string {
    if($task==='widget' || $task==='booking') return 'web';
    if($task==='phone') return 'phone';
    if($task==='whatsapp') return 'whatsapp';
    if(str_starts_with($task,'agent:')) return 'studio';
    return null;
}
function dash_days(mixed $v): int { $d=(int)$v; return in_array($d,DASH_DAYS,true) ? $d : 30; }
function dash_strip(string $t): string { return trim((string)preg_replace('/\n?\[intern[^\]]*\]/u','',$t)); }

/* ======================================================================= Website-Chats speichern */
/**
 * Nach jeder Widget-Antwort: Besuch (sid) anlegen bzw. fortschreiben – letzte 40 Nachrichten, Anzahl Antworten, ausgeführte Werkzeuge.
 * $sid: vom Widget (zufällig je Browser-Sitzung), sonst aus IP + erster Besucher-Nachricht abgeleitet.
 */
function dash_widget_log(int $aid, string $plan, string $sid, string $lang, array $clean, string $answer, array $calls=[], string $page=''): void {
    if($aid<=0 || trim($answer)==='') return;
    try{
        dash_bootstrap(); $sid=substr(hash('sha256',$aid.'|'.$sid),0,32);
        $msgs=array_map(fn($m)=>['role'=>$m['role']==='assistant' ? 'assistant' : 'user','content'=>mb_substr((string)$m['content'],0,1500)],$clean);
        $msgs[]=['role'=>'assistant','content'=>mb_substr($answer,0,1500)];
        $p=th_db(); $st=$p->prepare("SELECT id,messages,tools FROM widget_chats WHERE account_id=? AND sid=?"); $st->execute([$aid,$sid]); $row=$st->fetch(PDO::FETCH_ASSOC) ?: null;
        $tools=[]; foreach($calls as $c) if(!empty($c['ok']) && is_string($c['name'] ?? null) && preg_match('/^(book_|create_|request_|order_|cancel_)/',$c['name'])) $tools[]=preg_replace('/[^a-z_]/','',$c['name']);   // nur verbindliche Aktionen (Buchung, Bestellung, Rückruf …)
        if($row){
            /* Verlauf zusammenführen: das Widget schickt nur die letzten 12 Nachrichten – ältere aus dem Speicher behalten */
            $old=json_decode((string)$row['messages'],true) ?: []; $keep=$old;
            $tail=array_slice($msgs,0,-2);   // Nachrichten, die schon gespeichert sein müssten
            if($tail){ $n=count($tail); for($i=max(0,count($old)-$n-2);$i<=count($old);$i++){ $seg=array_slice($old,$i,$n); if(array_column($seg,'content')===array_column($tail,'content')){ $keep=array_slice($old,0,$i); break; } } }
            $all=array_slice(array_merge($keep,$msgs),-40);
            $tl=array_values(array_unique(array_filter(array_merge(explode(',',(string)$row['tools']),$tools))));
            $p->prepare("UPDATE widget_chats SET messages=?, turns=turns+1, tools=?, lang=?, updated_at=NOW() WHERE id=?")->execute([json_encode($all,JSON_UNESCAPED_UNICODE),mb_substr(implode(',',$tl),0,255),substr($lang,0,2),(int)$row['id']]);
        } else {
            $p->prepare("INSERT INTO widget_chats(account_id,plan,sid,lang,messages,turns,tools,page) VALUES(?,?,?,?,?,1,?,?)")
              ->execute([$aid,substr($plan,0,40),$sid,substr($lang,0,2),json_encode(array_slice($msgs,-40),JSON_UNESCAPED_UNICODE),mb_substr(implode(',',array_unique($tools)),0,255),mb_substr($page,0,190)]);
        }
        if(random_int(1,50)===1) $p->exec("DELETE FROM widget_chats WHERE updated_at < (NOW() - INTERVAL ".DASH_WEB_KEEP_DAYS." DAY)");
    }catch(Throwable $e){ error_log('CH dash_widget_log: '.$e->getMessage()); }
}

/* ======================================================================= Kennzahlen */
/** Erledigte Vorgänge je Art im Fenster [now-$from, now-$to) Tage: Anzahl + Umsatz (Cent, ohne Stornos). */
function dash_actions(int $aid, int $from, int $to=0): array {
    $w="created_at > (NOW() - INTERVAL $from DAY)".($to>0 ? " AND created_at <= (NOW() - INTERVAL $to DAY)" : '');
    $o=[];
    $r=dash_one("SELECT COUNT(*) n, COALESCE(SUM(total_cents),0) c FROM orders WHERE account_id=? AND status<>'cancelled' AND $w",[$aid]); $o['order']=['n'=>(int)($r['n'] ?? 0),'cents'=>(int)($r['c'] ?? 0)];
    $r=dash_one("SELECT COUNT(*) n, COALESCE(SUM(travel_fee_cents),0) c FROM appt_bookings WHERE account_id=? AND status<>'cancelled' AND $w",[$aid]);
    if(!$r) $r=dash_one("SELECT COUNT(*) n, 0 c FROM appt_bookings WHERE account_id=? AND status<>'cancelled' AND $w",[$aid]);
    $o['appt']=['n'=>(int)($r['n'] ?? 0),'cents'=>(int)($r['c'] ?? 0)];
    $r=dash_one("SELECT COUNT(*) n, COALESCE(SUM(price_cents),0) c FROM rides WHERE account_id=? AND status NOT IN ('cancelled','no_show') AND $w",[$aid]); $o['ride']=['n'=>(int)($r['n'] ?? 0),'cents'=>(int)($r['c'] ?? 0)];
    $r=dash_one("SELECT COUNT(*) n, COALESCE(SUM(total_cents),0) c FROM hotel_bookings WHERE account_id=? AND status NOT IN ('declined','cancelled') AND $w",[$aid]); $o['hotel']=['n'=>(int)($r['n'] ?? 0),'cents'=>(int)($r['c'] ?? 0)];
    $r=dash_one("SELECT COUNT(*) n FROM hotel_requests WHERE account_id=? AND status<>'cancelled' AND $w",[$aid]); $o['hotel_request']=['n'=>(int)($r['n'] ?? 0),'cents'=>0];
    $r=dash_one("SELECT COUNT(*) n FROM callbacks WHERE account_id=? AND status<>'cancelled' AND $w",[$aid]); $o['callback']=['n'=>(int)($r['n'] ?? 0),'cents'=>0];
    return $o;
}
/** Anrufe im Fenster: Anzahl, Sekunden, Ergebnisse. */
function dash_calls(int $aid, int $from, int $to=0): array {
    $w="started_at > (NOW() - INTERVAL $from DAY)".($to>0 ? " AND started_at <= (NOW() - INTERVAL $to DAY)" : '');
    $o=['n'=>0,'sec'=>0,'outcomes'=>[]];
    foreach(dash_q("SELECT outcome, COUNT(*) n, COALESCE(SUM(GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,COALESCE(ended_at,started_at)))),0) s FROM phone_calls WHERE account_id=? AND $w GROUP BY outcome",[$aid]) as $r){
        $o['n']+=(int)$r['n']; $o['sec']+=(int)$r['s']; $k=(string)$r['outcome']!=='' ? (string)$r['outcome'] : 'done'; $o['outcomes'][$k]=($o['outcomes'][$k] ?? 0)+(int)$r['n']; }
    arsort($o['outcomes']); return $o;
}
/** Antworten je Kanal im Fenster. */
function dash_answers(int $aid, int $from, int $to=0): array {
    $w="created_at > (NOW() - INTERVAL $from DAY)".($to>0 ? " AND created_at <= (NOW() - INTERVAL $to DAY)" : '');
    $o=['phone'=>0,'whatsapp'=>0,'web'=>0,'studio'=>0]; $tok=0;
    foreach(dash_q("SELECT task, COUNT(*) n, COALESCE(SUM(tokens),0) t FROM api_usage WHERE account_id=? AND $w GROUP BY task",[$aid]) as $r){ $c=dash_chan((string)$r['task']); if($c!==null) $o[$c]+=(int)$r['n']; $tok+=(int)$r['t']; }
    return ['by'=>$o,'customer'=>$o['phone']+$o['whatsapp']+$o['web'],'tokens'=>$tok];
}
function dash_saved_min(array $ans, array $calls, int $actions): int {
    return (int)round(($ans['by']['whatsapp']+$ans['by']['web'])*DASH_SAVE_MIN['chat'] + $calls['sec']/60 + $calls['n']*DASH_SAVE_MIN['call'] + $actions*DASH_SAVE_MIN['action']);
}
function dash_summary(int $aid, int $days): array {
    dash_bootstrap(); $tz=dash_tz($aid); $now=new DateTimeImmutable('now',$tz);
    $ans=dash_answers($aid,$days); $ansP=dash_answers($aid,2*$days,$days);
    $calls=dash_calls($aid,$days); $callsP=dash_calls($aid,2*$days,$days);
    $act=dash_actions($aid,$days); $actP=dash_actions($aid,2*$days,$days);
    $sum=fn(array $a,string $k)=>array_sum(array_column($a,$k));
    $nAct=$sum($act,'n'); $nActP=$sum($actP,'n');
    $web=dash_one("SELECT COUNT(*) n FROM widget_chats WHERE account_id=? AND updated_at > (NOW() - INTERVAL $days DAY)",[$aid]);
    $wa=dash_one("SELECT COUNT(*) n FROM wa_sessions WHERE account_id=? AND updated_at > (NOW() - INTERVAL $days DAY)",[$aid]);

    /* Verlauf je Tag (Zeitzone des Betriebs) + Stoßzeiten (Wochentag 0 = Montag × Stunde) aus Stunden-Summen */
    /* 24 Stunden: je Stunde, sonst je Kalendertag */
    $hours=$days===1; $key=fn(DateTimeImmutable $d)=>$hours ? $d->format('Y-m-d H') : $d->format('Y-m-d'); $series=[];
    for($i=($hours ? 23 : $days-1);$i>=0;$i--){ $d=$now->modify($hours ? "-$i hour" : "-$i day"); $series[$key($d)]=['d'=>$hours ? $d->format('Y-m-d\TH:00') : $d->format('Y-m-d'),'phone'=>0,'whatsapp'=>0,'web'=>0,'studio'=>0,'actions'=>0,'calls'=>0]; }
    $heat=array_fill(0,7,array_fill(0,24,0)); $after=0; $cust=0;
    $hourly=function(string $sql) use($aid,$tz): array { $o=[]; foreach(dash_q($sql,[$aid]) as $r){ $d=th_dbtime((string)$r['h'],$tz); if($d) $o[]=[$d,$r]; } return $o; };
    foreach($hourly("SELECT DATE_FORMAT(created_at,'%Y-%m-%d %H:00:00') h, task, COUNT(*) n FROM api_usage WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) GROUP BY h, task") as [$d,$r]){
        $c=dash_chan((string)$r['task']); if($c===null) continue; $n=(int)$r['n']; $k=$key($d);
        if(isset($series[$k])) $series[$k][$c]+=$n;
        if($c!=='studio'){ $wd=((int)$d->format('N'))-1; $hr=(int)$d->format('G'); $heat[$wd][$hr]+=$n; $cust+=$n; if($wd>=5 || $hr<8 || $hr>=19) $after+=$n; }
    }
    foreach(['orders'=>"status<>'cancelled'",'appt_bookings'=>"status<>'cancelled'",'rides'=>"status NOT IN ('cancelled','no_show')",'hotel_bookings'=>"status NOT IN ('declined','cancelled')",'hotel_requests'=>"status<>'cancelled'",'callbacks'=>"status<>'cancelled'"] as $t=>$cond)
        foreach($hourly("SELECT DATE_FORMAT(created_at,'%Y-%m-%d %H:00:00') h, COUNT(*) n FROM $t WHERE account_id=? AND $cond AND created_at > (NOW() - INTERVAL $days DAY) GROUP BY h") as [$d,$r]){ $k=$key($d); if(isset($series[$k])) $series[$k]['actions']+=(int)$r['n']; }
    foreach($hourly("SELECT DATE_FORMAT(started_at,'%Y-%m-%d %H:00:00') h, COUNT(*) n FROM phone_calls WHERE account_id=? AND started_at > (NOW() - INTERVAL $days DAY) GROUP BY h") as [$d,$r]){ $k=$key($d); if(isset($series[$k])) $series[$k]['calls']+=(int)$r['n']; }

    /* Guthaben + Reichweite (Verbrauch der letzten 14 Tage) */
    $bal=th_balance_ledger($aid); $use14=dash_one("SELECT COALESCE(-SUM(delta),0) u FROM token_ledger WHERE account_id=? AND delta<0 AND created_at > (NOW() - INTERVAL 14 DAY)",[$aid]);
    $perDay=round(((int)($use14['u'] ?? 0))/14,1); $byok=function_exists('bl_byok_usable') ? (bool)bl_byok_usable($aid) : false;
    $used=dash_one("SELECT COALESCE(-SUM(CASE WHEN delta<0 THEN delta END),0) u, COALESCE(SUM(CASE WHEN delta>0 THEN delta END),0) c FROM token_ledger WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY)",[$aid]);
    $usedP=dash_one("SELECT COALESCE(-SUM(delta),0) u FROM token_ledger WHERE account_id=? AND delta<0 AND created_at > (NOW() - INTERVAL ".(2*$days)." DAY) AND created_at <= (NOW() - INTERVAL $days DAY)",[$aid]);
    $quota=null; if(function_exists('bl_quota_status')){ try{ $quota=bl_quota_status($aid); }catch(Throwable $e){ $quota=null; } }

    /* Offene Vorgänge + Stammkunden (meiste Kontakte im Zeitraum) */
    $open=['total'=>0]; $top=[];
    if(function_exists('contacts_open_map')){
        try{ foreach(contacts_open_map($aid) as $items) foreach($items as $it){ $open[$it['kind']]=($open[$it['kind']] ?? 0)+1; $open['total']++; } }catch(Throwable $e){}
        try{ $g=[]; foreach(contacts_collect($aid,null,['days'=>max(1,$days),'light'=>true,'limit'=>400]) as $it){ $e=(string)$it['e164']; if($e==='') continue;
                $g[$e]??=['phone'=>$e,'display'=>function_exists('contacts_display') ? contacts_display($e) : $e,'name'=>'','n'=>0,'last'=>0,'kinds'=>[]];
                $g[$e]['n']++; if($g[$e]['name']==='' && (string)$it['name']!=='') $g[$e]['name']=(string)$it['name']; $g[$e]['last']=max($g[$e]['last'],(int)$it['ts']); $g[$e]['kinds'][$it['kind']]=1; }
            uasort($g,fn($a,$b)=>[$b['n'],$b['last']]<=>[$a['n'],$a['last']]);
            foreach(array_slice($g,0,6) as $x){ $x['last']=dash_iso($x['last']); $x['kinds']=array_keys($x['kinds']); $top[]=$x; } }catch(Throwable $e){}
    }
    $rev=$sum($act,'cents'); $revP=$sum($actP,'cents');
    return ['days'=>$days,'tz'=>$tz->getName(),'generated_at'=>gmdate('Y-m-d\TH:i:s\Z'),
        'kpi'=>['answers'=>['v'=>$ans['customer'],'prev'=>$ansP['customer']],'calls'=>['v'=>$calls['n'],'prev'=>$callsP['n'],'min'=>(int)round($calls['sec']/60),'prev_min'=>(int)round($callsP['sec']/60),'avg_sec'=>$calls['n'] ? (int)round($calls['sec']/$calls['n']) : 0],
            'actions'=>['v'=>$nAct,'prev'=>$nActP],'revenue_cents'=>['v'=>$rev,'prev'=>$revP],
            'saved_min'=>['v'=>dash_saved_min($ans,$calls,$nAct),'prev'=>dash_saved_min($ansP,$callsP,$nActP)],
            'after_hours'=>['n'=>$after,'share'=>$cust ? round($after/$cust,3) : 0.0],
            'chats'=>['web'=>(int)($web['n'] ?? 0),'whatsapp'=>(int)($wa['n'] ?? 0)],
            'tokens'=>['used'=>(int)($used['u'] ?? 0),'prev'=>(int)($usedP['u'] ?? 0),'credited'=>(int)($used['c'] ?? 0)]],
        'channels'=>array_map(fn($k)=>['key'=>$k,'n'=>$ans['by'][$k],'prev'=>$ansP['by'][$k]],array_keys($ans['by'])),
        'series'=>array_values($series),'heat'=>$heat,'calls'=>['outcomes'=>$calls['outcomes']],
        'actions'=>array_map(fn($k)=>['kind'=>$k,'n'=>$act[$k]['n'],'prev'=>$actP[$k]['n'],'cents'=>$act[$k]['cents']],array_keys($act)),
        'open'=>$open,'top'=>$top,
        'balance'=>['tokens'=>$bal,'per_day'=>$perDay,'days_left'=>(!$byok && $perDay>0) ? (int)floor($bal/$perDay) : null,'byok'=>$byok],
        'quota'=>$quota ? ['pct'=>$quota['pct'] ?? null,'used'=>$quota['used'] ?? null,'included'=>$quota['included'] ?? null,'period_end'=>$quota['period_end'] ?? null] : null,
        'assumptions'=>DASH_SAVE_MIN];
}

/* ======================================================================= Verlauf */
function dash_e164(string $p): string { return function_exists('contacts_e164') ? contacts_e164($p) : trim($p); }
function dash_disp(string $e): string { return $e!=='' && function_exists('contacts_display') ? contacts_display($e) : $e; }
function dash_chn(string $c): string { return function_exists('contacts_chan') ? contacts_chan($c) : (in_array($c,['phone','whatsapp','studio'],true) ? $c : 'web'); }
/** Offen? (gleiche Regeln wie der Kundenverlauf) */
const DASH_OPEN = ['order'=>['new','confirmed','preparing','ready','out_for_delivery'],'appt'=>['booked'],'ride'=>['new','assigned','en_route','arrived','onboard'],
    'hotel'=>['requested','confirmed','checked_in'],'hotel_request'=>['open','in_progress'],'callback'=>['open','scheduled','calling','failed']];
/**
 * Alle Vorgänge im Zeitraum (ohne Filter), neueste zuerst – sprachneutral: Art, Status-Code und Rohwerte; Titel/Zeilen setzt das Studio in der Oberflächensprache zusammen.
 * Gemeinsame Felder: id, kind, ts, at, channel, name, phone, phone_display, code, status, open, hay (Suchtext).
 */
function dash_feed(int $aid, int $days): array {
    dash_bootstrap(); $out=[]; $lim=600;
    $it=function(string $kind,int $id,?string $db,string $chan,array $x) use(&$out){ $ts=dash_ts($db); $e=(string)($x['phone'] ?? '');
        $o=$x+['id'=>$kind.':'.$id,'kind'=>$kind,'ts'=>$ts,'at'=>dash_iso($ts),'channel'=>$chan,'name'=>'','phone'=>'','code'=>'','status'=>'','open'=>false];
        $o['phone_display']=dash_disp($e);
        if($o['status']!=='' && isset(DASH_OPEN[$kind])) $o['open']=in_array($o['status'],DASH_OPEN[$kind],true);
        $o['hay']=mb_strtolower(implode(' ',array_filter([$o['name'],$o['code'],$o['phone_display'],(string)($x['text'] ?? ''),(string)($x['service'] ?? ''),(string)($x['items'] ?? ''),(string)($x['topic'] ?? ''),(string)($x['pickup'] ?? ''),(string)($x['dest'] ?? ''),(string)($x['room'] ?? ''),(string)($x['details'] ?? ''),(string)($x['reason'] ?? ''),(string)($x['plan'] ?? '')],'strlen')));
        $out[]=$o; };
    $outS=function_exists('contacts_outbound_sids') ? contacts_outbound_sids($aid) : []; $own=function_exists('contacts_internal_numbers') ? contacts_internal_numbers($aid) : [];
    foreach(dash_q("SELECT id,call_sid,plan,from_number,to_number,status,turns,outcome,started_at,ended_at FROM phone_calls WHERE account_id=? AND started_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT $lim",[$aid]) as $r){
        $fe=dash_e164((string)$r['from_number']); $te=dash_e164((string)$r['to_number']); $outb=isset($outS[(string)$r['call_sid']]) || ($fe!=='' && isset($own[$fe]));
        $sec=$r['ended_at'] ? max(0,dash_ts($r['ended_at'])-dash_ts($r['started_at'])) : null;
        $it('call',(int)$r['id'],$r['started_at'],'phone',['phone'=>$outb ? $te : $fe,'direction'=>$outb ? 'out' : 'in','turns'=>(int)$r['turns'],'sec'=>$sec,'outcome'=>(string)$r['outcome'],'live'=>!$r['ended_at'],'plan'=>(string)$r['plan']]);
    }
    foreach(dash_q("SELECT id,wa_from,profile,messages,updated_at FROM wa_sessions WHERE account_id=? AND updated_at > (NOW() - INTERVAL $days DAY) ORDER BY updated_at DESC LIMIT $lim",[$aid]) as $r){
        $m=dash_msgs($r['messages'],100); $last=''; foreach(array_reverse($m) as $x) if($x['role']==='user'){ $last=$x['content']; break; }
        $it('whatsapp',(int)$r['id'],$r['updated_at'],'whatsapp',['phone'=>dash_e164((string)$r['wa_from']),'name'=>(string)$r['profile'],'msgs'=>count($m),'text'=>mb_substr($last,0,160)]);
    }
    foreach(dash_q("SELECT id,plan,lang,messages,turns,tools,updated_at FROM widget_chats WHERE account_id=? AND updated_at > (NOW() - INTERVAL $days DAY) ORDER BY updated_at DESC LIMIT $lim",[$aid]) as $r){
        $m=json_decode((string)$r['messages'],true) ?: []; $first=''; foreach($m as $x) if(($x['role'] ?? '')==='user'){ $first=dash_strip((string)$x['content']); break; }
        $it('web',(int)$r['id'],$r['updated_at'],'web',['turns'=>(int)$r['turns'],'lang'=>(string)$r['lang'],'text'=>mb_substr($first,0,160),'tools'=>array_values(array_filter(explode(',',(string)$r['tools'])))]);
    }
    foreach(dash_q("SELECT id,code,channel,type,status,customer_name,phone,wa_from,items,total_cents,eta_at,created_at FROM orders WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT $lim",[$aid]) as $r){
        $items=json_decode((string)$r['items'],true) ?: []; $txt=implode(', ',array_map(fn($i)=>(int)($i['qty'] ?? 1).'× '.($i['name'] ?? ''),array_slice($items,0,4))).(count($items)>4 ? ' …' : '');
        $e=$r['channel']==='whatsapp' && (string)$r['wa_from']!=='' ? dash_e164((string)$r['wa_from']) : dash_e164((string)$r['phone']);
        $it('order',(int)$r['id'],$r['created_at'],dash_chn((string)$r['channel']),['code'=>(string)$r['code'],'status'=>(string)$r['status'],'name'=>(string)$r['customer_name'],'phone'=>$e,'items'=>$txt,'cents'=>(int)$r['total_cents'],'type'=>(string)$r['type'],'when'=>(string)($r['eta_at'] ?? '')]);
    }
    foreach(dash_q("SELECT id,code,service_name,start_at,status,customer_name,phone,source,created_at FROM appt_bookings WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT $lim",[$aid]) as $r)
        $it('appt',(int)$r['id'],$r['created_at'],dash_chn((string)$r['source']),['code'=>(string)$r['code'],'status'=>(string)$r['status'],'name'=>(string)$r['customer_name'],'phone'=>dash_e164((string)$r['phone']),'service'=>(string)$r['service_name'],'when'=>(string)$r['start_at']]);
    foreach(dash_q("SELECT id,code,channel,status,customer_name,phone,wa_from,pickup_text,dest_text,pickup_at,asap,price_cents,created_at FROM rides WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT $lim",[$aid]) as $r){
        $e=$r['channel']==='whatsapp' && (string)$r['wa_from']!=='' ? dash_e164((string)$r['wa_from']) : dash_e164((string)$r['phone']);
        $it('ride',(int)$r['id'],$r['created_at'],dash_chn((string)$r['channel']),['code'=>(string)$r['code'],'status'=>(string)$r['status'],'name'=>(string)$r['customer_name'],'phone'=>$e,'pickup'=>mb_substr((string)$r['pickup_text'],0,80),'dest'=>mb_substr((string)$r['dest_text'],0,80),'asap'=>(bool)(int)$r['asap'],'when'=>(string)($r['pickup_at'] ?? ''),'cents'=>(int)$r['price_cents']]);
    }
    foreach(dash_q("SELECT id,code,room_name,checkin,checkout,guests,status,guest_name,phone,wa_from,channel,total_cents,created_at FROM hotel_bookings WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT $lim",[$aid]) as $r){
        $e=$r['channel']==='whatsapp' && (string)$r['wa_from']!=='' ? dash_e164((string)$r['wa_from']) : dash_e164((string)$r['phone']);
        $it('hotel',(int)$r['id'],$r['created_at'],dash_chn((string)$r['channel']),['code'=>(string)$r['code'],'status'=>(string)$r['status'],'name'=>(string)$r['guest_name'],'phone'=>$e,'room'=>(string)$r['room_name'],'from'=>(string)$r['checkin'],'to'=>(string)$r['checkout'],'guests'=>(int)$r['guests'],'cents'=>(int)$r['total_cents']]);
    }
    foreach(dash_q("SELECT id,room_no,guest_name,kind,details,status,channel,contact,due_at,created_at FROM hotel_requests WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT $lim",[$aid]) as $r)
        $it('hotel_request',(int)$r['id'],$r['created_at'],dash_chn((string)$r['channel']),['status'=>(string)$r['status'],'name'=>(string)$r['guest_name'],'phone'=>dash_e164((string)$r['contact']),'request'=>(string)$r['kind'],'room'=>(string)$r['room_no'],'details'=>mb_substr((string)$r['details'],0,140),'when'=>(string)($r['due_at'] ?? '')]);
    foreach(dash_q("SELECT id,code,name,phone,topic,preferred_at,status,mode,source,result,created_at FROM callbacks WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT $lim",[$aid]) as $r)
        $it('callback',(int)$r['id'],$r['created_at'],dash_chn((string)$r['source']),['code'=>(string)$r['code'],'status'=>(string)$r['status'],'name'=>(string)$r['name'],'phone'=>dash_e164((string)$r['phone']),'topic'=>mb_substr((string)$r['topic'],0,140),'mode'=>(string)$r['mode'],'when'=>(string)($r['preferred_at'] ?? ''),'result'=>mb_substr((string)$r['result'],0,140)]);
    $in=implode(',',array_map(fn($x)=>th_db()->quote($x),DASH_LEDGER_SHOW));
    foreach(dash_q("SELECT id,delta,reason,created_at FROM token_ledger WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) AND (reason IN ($in) OR reason LIKE 'admin:%') ORDER BY id DESC LIMIT 300",[$aid]) as $r)
        $it('ledger',(int)$r['id'],$r['created_at'],'',['reason'=>(string)$r['reason'],'tokens'=>(int)$r['delta']]);
    foreach(dash_q("SELECT id,plan,status,created_at FROM token_plans WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT 100",[$aid]) as $r)
        $it('plan',(int)$r['id'],$r['created_at'],'',['plan'=>(string)$r['plan'],'status'=>(string)$r['status']]);
    foreach(dash_q("SELECT id,plan,status,finished_at,created_at FROM onboardings WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT 50",[$aid]) as $r){
        $it('onb',(int)$r['id'],$r['finished_at'] ?: $r['created_at'],'',['plan'=>(string)$r['plan'],'status'=>(string)$r['status']]); $out[count($out)-1]['open']=$r['status']==='open'; }
    foreach(dash_q("SELECT id,e164,status,created_at FROM phone_numbers WHERE account_id=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT 20",[$aid]) as $r)
        $it('num',(int)$r['id'],$r['created_at'],'phone',['phone'=>(string)$r['e164'],'status'=>(string)$r['status']]);
    usort($out,fn($a,$b)=>[$b['ts'],$b['id']]<=>[$a['ts'],$a['id']]);
    return $out;
}
/** Filter: type (Gruppe aus DASH_GROUPS oder Art), channel, q (Name, Nummer, Code, Text). Gibt [items, counts je Gruppe] zurück. */
function dash_filter(array $feed, string $type, string $chan, string $q): array {
    $q=mb_strtolower(trim($q)); $qd=preg_replace('/\D/','',$q); $cnt=['all'=>0];
    $hit=function(array $it) use($q,$qd): bool {
        if($q==='') return true;
        if(str_contains((string)$it['hay'],$q)) return true;
        return strlen($qd)>=3 && str_contains(preg_replace('/\D/','',(string)$it['phone']),ltrim($qd,'0'));
    };
    $o=[];
    foreach($feed as $it){
        if($chan!=='' && $it['channel']!==$chan) continue;
        if(!$hit($it)) continue;
        $g=DASH_GROUPS[$it['kind']] ?? 'system'; $cnt['all']++; $cnt[$g]=($cnt[$g] ?? 0)+1;
        if($type!=='' && $type!=='all' && $g!==$type && $it['kind']!==$type) continue;
        $o[]=$it;
    }
    return [$o,$cnt];
}

/* ======================================================================= Einzelheiten */
function dash_msgs(?string $json, int $max=60): array {
    $o=[]; foreach((array)(json_decode((string)$json,true) ?: []) as $m){ if(!is_array($m) || !is_string($m['content'] ?? null)) continue; $c=dash_strip($m['content']); if($c==='') continue;
        $role=($m['role'] ?? '')==='user' ? 'user' : (($m['role'] ?? '')==='assistant' ? 'assistant' : ''); if($role==='') continue; $o[]=['role'=>$role,'content'=>mb_substr($c,0,2000)]; }
    return array_slice($o,-$max);
}
/** Feld: [Schlüssel, Wert, Typ] – Typ: text, eur (Cent), dt (DB-Zeit → ISO), date, num, sec, phone, plan */
function dash_item(int $aid, string $id): ?array {
    dash_bootstrap(); if(!preg_match('/^([a-z_]+):(\d{1,10})$/',$id,$m)) return null; [$k,$n]=[$m[1],(int)$m[2]];
    $F=[]; $f=function(string $key,$v,string $t='text') use(&$F){ if($v===null || $v==='' || ($t==='eur' && (int)$v===0 && $key!=='total')) return; $F[]=[$key,$t==='dt' ? dash_iso(dash_ts((string)$v)) : $v,$t]; };
    $ph=fn(string $p)=>function_exists('contacts_display') && function_exists('contacts_e164') && contacts_e164($p)!=='' ? contacts_display(contacts_e164($p)) : $p;
    $o=['id'=>$id,'kind'=>$k,'messages'=>[],'panel'=>null];
    switch($k){
        case 'call':
            $r=dash_one("SELECT * FROM phone_calls WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $sec=$r['ended_at'] ? max(0,dash_ts($r['ended_at'])-dash_ts($r['started_at'])) : null;
            $f('started',$r['started_at'],'dt'); $f('duration',$sec,'sec'); $f('from',$ph((string)$r['from_number']),'phone'); $f('to',$ph((string)$r['to_number']),'phone');
            $f('turns',(int)$r['turns'],'num'); $f('outcome',(string)$r['outcome']); $f('tokens',(int)$r['tokens'],'num'); $f('agent',(string)$r['plan'],'plan');
            $o['messages']=dash_msgs($r['transcript']); $o['panel']='phone'; $o['ts']=dash_iso(dash_ts($r['started_at'])); break;
        case 'whatsapp':
            $r=dash_one("SELECT * FROM wa_sessions WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('customer',(string)$r['profile']); $f('phone',$ph((string)$r['wa_from']),'phone'); $f('last',$r['updated_at'],'dt');
            $o['messages']=dash_msgs($r['messages']); $o['panel']='whatsapp'; $o['ts']=dash_iso(dash_ts($r['updated_at'])); break;
        case 'web':
            $r=dash_one("SELECT * FROM widget_chats WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('started',$r['created_at'],'dt'); $f('last',$r['updated_at'],'dt'); $f('turns',(int)$r['turns'],'num'); $f('lang',strtoupper((string)$r['lang'])); $f('agent',(string)$r['plan'],'plan');
            $f('tools',implode(', ',array_filter(explode(',',(string)$r['tools']))),'tools');
            $o['messages']=dash_msgs($r['messages']); $o['panel']='website'; $o['ts']=dash_iso(dash_ts($r['updated_at'])); break;
        case 'order':
            $r=dash_one("SELECT * FROM orders WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('code',(string)$r['code']); $f('created',$r['created_at'],'dt'); $f('customer',(string)$r['customer_name']); $f('phone',$ph((string)($r['phone'] ?: $r['wa_from'])),'phone');
            $f('type',(string)$r['type']); $f('address',trim((string)$r['address_text'].' '.(string)$r['plz'].' '.(string)$r['city'])); $f('distance',$r['distance_km']!==null && (float)$r['distance_km']>0 ? round((float)$r['distance_km'],1) : null,'km');
            $f('subtotal',(int)$r['subtotal_cents'],'eur'); $f('fee',(int)$r['fee_cents'],'eur'); $f('discount',(int)($r['discount_cents'] ?? 0),'eur'); $f('total',(int)$r['total_cents'],'eur');
            $f('payment',(string)$r['payment']); $f('eta',$r['eta_at'],'dt'); $f('note',(string)$r['note']);
            $o['lines']=array_map(fn($i)=>['qty'=>(int)($i['qty'] ?? 1),'name'=>(string)($i['name'] ?? ''),'options'=>array_values(array_filter((array)($i['options'] ?? []),'is_string')),'cents'=>(int)($i['total_cents'] ?? ((int)($i['unit_cents'] ?? 0)*(int)($i['qty'] ?? 1))),'note'=>(string)($i['note'] ?? '')],array_slice(json_decode((string)$r['items'],true) ?: [],0,60));
            $o['panel']='bestellungen'; $o['ts']=dash_iso(dash_ts($r['created_at'])); $o['status']=(string)$r['status']; break;
        case 'appt':
            $r=dash_one("SELECT * FROM appt_bookings WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('code',(string)$r['code']); $f('service',(string)$r['service_name']); $f('start',(string)$r['start_at'],'wall'); $f('end',(string)$r['end_at'],'wall'); $f('customer',(string)$r['customer_name']);
            $f('phone',$ph((string)$r['phone']),'phone'); $f('email',(string)$r['email']); $f('visit',(string)($r['visit_address'] ?? '')); $f('fee',(int)($r['travel_fee_cents'] ?? 0),'eur');
            $f('note',(string)$r['note']); $f('source',(string)$r['source']); $f('created',$r['created_at'],'dt');
            $o['panel']='termine'; $o['ts']=dash_iso(dash_ts($r['created_at'])); $o['status']=(string)$r['status']; break;
        case 'ride':
            $r=dash_one("SELECT * FROM rides WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('code',(string)$r['code']); $f('customer',(string)$r['customer_name']); $f('phone',$ph((string)($r['phone'] ?: $r['wa_from'])),'phone'); $f('pickup',(string)$r['pickup_text']); $f('dest',(string)$r['dest_text']);
            $f('when',(int)$r['asap'] ? 'asap' : (string)$r['pickup_at'],(int)$r['asap'] ? 'text' : 'wall'); $f('distance',$r['distance_km']!==null && (float)$r['distance_km']>0 ? round((float)$r['distance_km'],1) : null,'km');
            $f('passengers',(int)$r['passengers'],'num'); $f('vehicle',(string)$r['vehicle']); $f('total',(int)$r['price_cents'],'eur'); $f('payment',(string)$r['payment']); $f('note',(string)$r['note']); $f('created',$r['created_at'],'dt');
            $o['panel']='taxi'; $o['ts']=dash_iso(dash_ts($r['created_at'])); $o['status']=(string)$r['status']; break;
        case 'hotel':
            $r=dash_one("SELECT * FROM hotel_bookings WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('code',(string)$r['code']); $f('room',(string)$r['room_name']); $f('checkin',(string)$r['checkin'],'date'); $f('checkout',(string)$r['checkout'],'date'); $f('nights',(int)$r['nights'],'num'); $f('guests',(int)$r['guests'],'num');
            $f('customer',(string)$r['guest_name']); $f('phone',$ph((string)($r['phone'] ?: $r['wa_from'])),'phone'); $f('email',(string)$r['email']); $f('total',(int)$r['total_cents'],'eur'); $f('note',(string)$r['note']); $f('created',$r['created_at'],'dt');
            $o['panel']='hotel'; $o['ts']=dash_iso(dash_ts($r['created_at'])); $o['status']=(string)$r['status']; break;
        case 'hotel_request':
            $r=dash_one("SELECT * FROM hotel_requests WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('room',(string)$r['room_no']); $f('customer',(string)$r['guest_name']); $f('request',(string)$r['kind'],'hk'); $f('details',(string)$r['details']); $f('due',(string)($r['due_at'] ?? ''),'wall'); $f('created',$r['created_at'],'dt');
            $o['panel']='hotel'; $o['ts']=dash_iso(dash_ts($r['created_at'])); $o['status']=(string)$r['status']; break;
        case 'callback':
            $r=dash_one("SELECT * FROM callbacks WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('code',(string)$r['code']); $f('customer',(string)$r['name']); $f('phone',$ph((string)$r['phone']),'phone'); $f('email',(string)$r['email']); $f('topic',(string)$r['topic']); $f('details',(string)$r['details']);
            $f('preferred',(string)($r['preferred_at'] ?? ''),'wall'); $f('mode',(string)$r['mode']); $f('attempts',(int)$r['attempts'],'num'); $f('result',(string)$r['result']); $f('created',$r['created_at'],'dt');
            $o['panel']='rueckruf'; $o['ts']=dash_iso(dash_ts($r['created_at'])); $o['status']=(string)$r['status']; break;
        case 'ledger':
            $r=dash_one("SELECT * FROM token_ledger WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('reason',(string)$r['reason']); $f('tokens',(int)$r['delta'],'num'); $f('created',$r['created_at'],'dt');
            $o['panel']='tokens'; $o['ts']=dash_iso(dash_ts($r['created_at'])); break;
        case 'plan':
            $r=dash_one("SELECT * FROM token_plans WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('agent',(string)$r['plan'],'plan'); $f('status',(string)$r['status']); $f('created',$r['created_at'],'dt'); $f('period_end',$r['period_end'],'dt'); $f('tokens_month',$r['tokens_month']!==null ? (int)$r['tokens_month'] : null,'num');
            $o['panel']='agents'; $o['ts']=dash_iso(dash_ts($r['created_at'])); break;
        case 'onb':
            $r=dash_one("SELECT id,plan,status,created_at,finished_at FROM onboardings WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('agent',(string)$r['plan'],'plan'); $f('status',(string)$r['status']); $f('created',$r['created_at'],'dt'); $f('finished',$r['finished_at'],'dt');
            $o['panel']='einrichtung'; $o['ts']=dash_iso(dash_ts($r['created_at'])); break;
        case 'num':
            $r=dash_one("SELECT id,e164,country,status,created_at,released_at FROM phone_numbers WHERE id=? AND account_id=?",[$n,$aid]); if(!$r) return null;
            $f('number',$ph((string)$r['e164']),'phone'); $f('country',(string)$r['country']); $f('status',(string)$r['status']); $f('created',$r['created_at'],'dt'); $f('released',$r['released_at'],'dt');
            $o['panel']='phone'; $o['ts']=dash_iso(dash_ts($r['created_at'])); break;
        default: return null;
    }
    $o['fields']=$F;
    return $o;
}

/* ======================================================================= CSV */
function dash_csv(array $items): string {
    $h=fopen('php://temp','w+'); fwrite($h,"\xEF\xBB\xBF");
    fputcsv($h,['time_utc','kind','channel','status','code','customer','phone','amount_eur','tokens','details'],';');
    foreach($items as $it){
        $det=trim(implode(' · ',array_filter([(string)($it['service'] ?? ''),(string)($it['items'] ?? ''),(string)($it['topic'] ?? ''),isset($it['pickup']) ? trim($it['pickup'].' → '.($it['dest'] ?? '')) : '',(string)($it['room'] ?? ''),(string)($it['text'] ?? ''),(string)($it['reason'] ?? ''),(string)($it['plan'] ?? ''),(string)($it['when'] ?? ''),isset($it['sec']) && $it['sec']!==null ? $it['sec'].' s' : ''],'strlen')));
        fputcsv($h,[$it['at'] ?? '',$it['kind'],$it['channel'],$it['status'] ?: ($it['outcome'] ?? ''),$it['code'],$it['name'],$it['phone_display'],isset($it['cents']) ? number_format($it['cents']/100,2,'.','') : '',isset($it['tokens']) ? (string)$it['tokens'] : '',$det],';');
    }
    rewind($h); $s=(string)stream_get_contents($h); fclose($h); return $s;
}

/* ======================================================================= REST */
function dash_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if(strpos($route,'dash/')!==0) return null;
    if($method!=='GET') return [405,['error'=>'method_not_allowed']];
    $in=$body+$_GET; $sc=fn($v)=>is_scalar($v) ? (string)$v : '';
    try{
        switch($route){
            case 'dash/summary':
                return [200,dash_summary($aid,dash_days($in['days'] ?? 30))];
            case 'dash/activity': case 'dash/export':
                $days=max(1,min(365,(int)($in['days'] ?? 30) ?: 30));
                if(!th_rate('dash:'.$aid,240,3600)) return [429,['error'=>'rate_limited','message'=>'Zu viele Abfragen – bitte kurz warten.']];
                [$items,$cnt]=dash_filter(dash_feed($aid,$days),preg_replace('/[^a-z_]/','',$sc($in['type'] ?? '')),preg_replace('/[^a-z_]/','',$sc($in['channel'] ?? '')),mb_substr($sc($in['q'] ?? ''),0,80));
                if($route==='dash/export') return [200,['filename'=>'chathelp-verlauf-'.date('Y-m-d').'.csv','count'=>count($items),'csv'=>dash_csv(array_slice($items,0,5000))]];
                $lim=max(1,min(100,(int)($in['limit'] ?? 40) ?: 40)); $off=max(0,(int)($in['offset'] ?? 0));
                $page=array_map(function($x){ unset($x['hay']); return $x; },array_slice($items,$off,$lim));
                return [200,['days'=>$days,'total'=>count($items),'counts'=>$cnt,'offset'=>$off,'items'=>$page,'more'=>$off+$lim<count($items)]];
            case 'dash/item':
                $it=dash_item($aid,$sc($in['id'] ?? ''));
                return $it ? [200,$it] : [404,['error'=>'not_found','message'=>'Eintrag nicht gefunden.']];
        }
        return [404,['error'=>'not_found','routes'=>['GET dash/summary?days=','GET dash/activity?days=&type=&channel=&q=&limit=&offset=','GET dash/item?id=','GET dash/export?…']]];
    }catch(Throwable $e){ error_log('CH dash_api '.$route.': '.$e->getMessage()); return [500,['error'=>'server_error','message'=>'Interner Fehler – bitte erneut versuchen.']]; }
}
