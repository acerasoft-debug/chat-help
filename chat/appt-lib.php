<?php
/**
 * ChatHelp — Terminbuchung (Assistenz-Kalender) für Praxen, Salons, Studios, Werkstätten, Kanzleien
 * ==================================================================================================
 * • Einstellungen je Konto: Leistungen (Dauer/Preis), Öffnungszeiten je Wochentag, Taktung, Puffer, Vorlauf, Horizont
 * • Freie Slots werden aus Zeiten − Buchungen − Sperrzeiten berechnet (keine Doppelbuchung, Transaktion + Sperre)
 * • Agent (Chat, Widget, Telefon) bucht über Werkzeuge (tool use): get_available_slots / book_appointment / cancel_appointment
 * • Bestätigung an Kunde (+ ICS/Storno-Link) und Benachrichtigung an Betrieb; Erinnerung 24 h vorher (lazy, bei Aufrufen)
 * • Konto 0 = öffentliche Demo (Salon Luna), Buchungen werden nach 2 Tagen gelöscht
 */
require_once __DIR__.'/tokens-lib.php';

const APPT_DEFAULT_HOURS = ['1'=>[['09:00','18:00']],'2'=>[['09:00','18:00']],'3'=>[['09:00','18:00']],'4'=>[['09:00','20:00']],'5'=>[['09:00','18:00']],'6'=>[['09:00','14:00']],'0'=>[]];
const APPT_DEMO = ['company'=>'Salon Luna · Friseur & Kosmetik','address'=>'Schanzenstraße 18, 20357 Hamburg','notify_email'=>'',
  'services'=>[['id'=>1,'name'=>'Damen Schnitt & Föhnen','duration_min'=>60,'price_cents'=>5900],['id'=>2,'name'=>'Herren Schnitt','duration_min'=>30,'price_cents'=>3200],['id'=>3,'name'=>'Färben / Balayage','duration_min'=>120,'price_cents'=>12900],['id'=>4,'name'=>'Kosmetik Gesichtsbehandlung','duration_min'=>60,'price_cents'=>7900],['id'=>5,'name'=>'Maniküre','duration_min'=>45,'price_cents'=>3900]]];

function appt_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS appt_settings (
        account_id INT PRIMARY KEY, appt_on TINYINT(1) DEFAULT 0, company VARCHAR(120) DEFAULT '', address VARCHAR(200) DEFAULT '',
        slot_min INT DEFAULT 30, buffer_min INT DEFAULT 0, lead_min INT DEFAULT 120, max_days INT DEFAULT 60, tz VARCHAR(40) DEFAULT 'Europe/Berlin',
        hours TEXT NULL, notify_email VARCHAR(190) DEFAULT '', confirm_text VARCHAR(400) DEFAULT '', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS appt_services (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, name VARCHAR(120) NOT NULL, duration_min INT DEFAULT 30, price_cents INT DEFAULT 0,
        staff VARCHAR(80) DEFAULT '', active TINYINT(1) DEFAULT 1, sort INT DEFAULT 0, INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS appt_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, service_id INT NULL, service_name VARCHAR(120) DEFAULT '',
        start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, customer_name VARCHAR(120) DEFAULT '', phone VARCHAR(40) DEFAULT '', email VARCHAR(190) DEFAULT '',
        note VARCHAR(500) DEFAULT '', status VARCHAR(16) DEFAULT 'booked', source VARCHAR(16) DEFAULT 'studio', code CHAR(10) NOT NULL UNIQUE,
        reminded TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(account_id, start_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS appt_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, reason VARCHAR(120) DEFAULT '', INDEX(account_id, start_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if(random_int(1,40)===1) $p->exec("DELETE FROM appt_bookings WHERE account_id=0 AND created_at < (NOW() - INTERVAL 2 DAY)");
}

/* ---------- Einstellungen & Leistungen ---------- */
function appt_settings(int $acc): array {
    appt_bootstrap();
    $s=th_db()->prepare("SELECT * FROM appt_settings WHERE account_id=?"); $s->execute([$acc]); $r=$s->fetch(PDO::FETCH_ASSOC);
    if(!$r){ $r=['account_id'=>$acc,'appt_on'=>$acc===0?1:0,'company'=>$acc===0?APPT_DEMO['company']:'','address'=>$acc===0?APPT_DEMO['address']:'','slot_min'=>30,'buffer_min'=>0,'lead_min'=>120,'max_days'=>60,'tz'=>'Europe/Berlin','hours'=>null,'notify_email'=>'','confirm_text'=>'']; }
    $h=json_decode((string)($r['hours']??''),true); $r['hours']=is_array($h)&&$h ? $h : APPT_DEFAULT_HOURS;
    foreach(['slot_min','buffer_min','lead_min','max_days','appt_on'] as $k) $r[$k]=(int)$r[$k];
    return $r;
}
function appt_settings_save(int $acc, array $d): array {
    appt_bootstrap(); $cur=appt_settings($acc);
    $hours=$cur['hours'];
    if(isset($d['hours']) && is_array($d['hours'])){ $hours=[]; for($w=0;$w<=6;$w++){ $rows=[]; foreach((array)($d['hours'][(string)$w] ?? $d['hours'][$w] ?? []) as $iv){ if(is_array($iv)&&count($iv)>=2&&preg_match('/^\d{2}:\d{2}$/',$iv[0])&&preg_match('/^\d{2}:\d{2}$/',$iv[1])&&$iv[0]<$iv[1]) $rows[]=[$iv[0],$iv[1]]; } $hours[(string)$w]=$rows; } }
    $v=['appt_on'=>(int)!empty($d['appt_on'] ?? $cur['appt_on']),'company'=>mb_substr(trim((string)($d['company']??$cur['company'])),0,120),'address'=>mb_substr(trim((string)($d['address']??$cur['address'])),0,200),
        'slot_min'=>max(5,min(240,(int)($d['slot_min']??$cur['slot_min']))),'buffer_min'=>max(0,min(120,(int)($d['buffer_min']??$cur['buffer_min']))),'lead_min'=>max(0,min(10080,(int)($d['lead_min']??$cur['lead_min']))),
        'max_days'=>max(1,min(365,(int)($d['max_days']??$cur['max_days']))),'tz'=>in_array($d['tz']??'',timezone_identifiers_list(),true)?$d['tz']:$cur['tz'],
        'notify_email'=>filter_var((string)($d['notify_email']??$cur['notify_email']),FILTER_VALIDATE_EMAIL)?:'' ,'confirm_text'=>mb_substr(trim((string)($d['confirm_text']??$cur['confirm_text'])),0,400)];
    th_db()->prepare("INSERT INTO appt_settings(account_id,appt_on,company,address,slot_min,buffer_min,lead_min,max_days,tz,hours,notify_email,confirm_text) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE appt_on=VALUES(appt_on),company=VALUES(company),address=VALUES(address),slot_min=VALUES(slot_min),buffer_min=VALUES(buffer_min),lead_min=VALUES(lead_min),max_days=VALUES(max_days),tz=VALUES(tz),hours=VALUES(hours),notify_email=VALUES(notify_email),confirm_text=VALUES(confirm_text),updated_at=NOW()")
      ->execute([$acc,$v['appt_on'],$v['company'],$v['address'],$v['slot_min'],$v['buffer_min'],$v['lead_min'],$v['max_days'],$v['tz'],json_encode($hours),$v['notify_email'],$v['confirm_text']]);
    if(isset($d['services']) && is_array($d['services'])) appt_services_save($acc,$d['services']);
    return appt_settings($acc);
}
function appt_services(int $acc, bool $activeOnly=true): array {
    appt_bootstrap();
    if($acc===0){ return APPT_DEMO['services']; }
    $s=th_db()->prepare("SELECT id,name,duration_min,price_cents,staff,active,sort FROM appt_services WHERE account_id=?".($activeOnly?" AND active=1":"")." ORDER BY sort,id"); $s->execute([$acc]);
    return array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name'],'duration_min'=>(int)$r['duration_min'],'price_cents'=>(int)$r['price_cents'],'staff'=>$r['staff'],'active'=>(int)$r['active'],'sort'=>(int)$r['sort']],$s->fetchAll(PDO::FETCH_ASSOC));
}
/** Komplette Liste ersetzen (ids behalten, fehlende deaktivieren). */
function appt_services_save(int $acc, array $list): void {
    appt_bootstrap(); $p=th_db(); $keep=[]; $i=0;
    foreach($list as $it){ if(!is_array($it)) continue; $name=mb_substr(trim((string)($it['name']??'')),0,120); if($name==='') continue;
        $dur=max(5,min(600,(int)($it['duration_min']??30)));
        $price=isset($it['price_cents']) ? max(0,(int)$it['price_cents']) : max(0,(int)round(((float)str_replace(',','.',(string)($it['price']??0)))*100));
        $staff=mb_substr(trim((string)($it['staff']??'')),0,80); $act=(!isset($it['active']) || !empty($it['active'])) ? 1 : 0; $id=(int)($it['id']??0); $i++;
        if($id>0){ $chk=$p->prepare("SELECT COUNT(*) FROM appt_services WHERE id=? AND account_id=?"); $chk->execute([$id,$acc]); if((int)$chk->fetchColumn()===0) $id=0; }
        if($id>0) $p->prepare("UPDATE appt_services SET name=?,duration_min=?,price_cents=?,staff=?,active=?,sort=? WHERE id=? AND account_id=?")->execute([$name,$dur,$price,$staff,$act,$i,$id,$acc]);
        else { $p->prepare("INSERT INTO appt_services(account_id,name,duration_min,price_cents,staff,active,sort) VALUES(?,?,?,?,?,?,?)")->execute([$acc,$name,$dur,$price,$staff,$act,$i]); $id=(int)$p->lastInsertId(); }
        $keep[]=$id; }
    if($keep) $p->prepare("UPDATE appt_services SET active=0 WHERE account_id=? AND id NOT IN (".implode(',',array_map('intval',$keep)).")")->execute([$acc]);
}
function appt_service(int $acc, ?int $id): ?array { foreach(appt_services($acc) as $s){ if($id===null || (int)$s['id']===(int)$id) return $s; } return null; }

/* ---------- Slots ---------- */
function appt_tz(array $s): DateTimeZone { try{ return new DateTimeZone($s['tz'] ?: 'Europe/Berlin'); }catch(Throwable $e){ return new DateTimeZone('Europe/Berlin'); } }
/** Freie Startzeiten (HH:MM) für ein Datum und eine Leistung. */
function appt_slots(int $acc, string $date, ?int $serviceId=null): array {
    $s=appt_settings($acc); $tz=appt_tz($s);
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) return ['error'=>'date_format','hint'=>'YYYY-MM-DD'];
    $svc=appt_service($acc,$serviceId); $dur=$svc ? (int)$svc['duration_min'] : $s['slot_min'];
    $day=new DateTimeImmutable($date.' 00:00',$tz); $now=new DateTimeImmutable('now',$tz);
    if($day < $now->setTime(0,0)) return ['date'=>$date,'slots'=>[],'closed'=>false,'reason'=>'past'];
    if($day > $now->modify('+'.$s['max_days'].' days')) return ['date'=>$date,'slots'=>[],'closed'=>false,'reason'=>'too_far','max_days'=>$s['max_days']];
    $ranges=$s['hours'][(string)(int)$day->format('w')] ?? [];
    if(!$ranges) return ['date'=>$date,'slots'=>[],'closed'=>true,'service'=>$svc];
    $p=th_db(); $q=$p->prepare("SELECT start_at,end_at FROM appt_bookings WHERE account_id=? AND status IN ('booked','done') AND start_at < ? AND end_at > ?"); $q->execute([$acc,$day->modify('+1 day')->format('Y-m-d H:i:s'),$day->format('Y-m-d H:i:s')]);
    $busy=array_map(fn($r)=>[new DateTimeImmutable($r['start_at'],$tz),new DateTimeImmutable($r['end_at'],$tz)],$q->fetchAll(PDO::FETCH_ASSOC));
    $q=$p->prepare("SELECT start_at,end_at FROM appt_blocks WHERE account_id=? AND start_at < ? AND end_at > ?"); $q->execute([$acc,$day->modify('+1 day')->format('Y-m-d H:i:s'),$day->format('Y-m-d H:i:s')]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $busy[]=[new DateTimeImmutable($r['start_at'],$tz),new DateTimeImmutable($r['end_at'],$tz)];
    $minStart=$now->modify('+'.$s['lead_min'].' minutes'); $buf=$s['buffer_min']; $out=[];
    foreach($ranges as [$o,$c]){ $t=new DateTimeImmutable($date.' '.$o,$tz); $end=new DateTimeImmutable($date.' '.$c,$tz);
        while($t->modify('+'.$dur.' minutes') <= $end){
            $te=$t->modify('+'.$dur.' minutes'); $ok=$t >= $minStart;
            if($ok) foreach($busy as [$bs,$be]){ if($t < $be->modify('+'.$buf.' minutes') && $te->modify('+'.$buf.' minutes') > $bs){ $ok=false; break; } }
            if($ok) $out[]=$t->format('H:i');
            $t=$t->modify('+'.$s['slot_min'].' minutes');
        } }
    return ['date'=>$date,'weekday'=>$day->format('l'),'slots'=>$out,'closed'=>false,'service'=>$svc ? ['id'=>$svc['id'],'name'=>$svc['name'],'duration_min'=>$dur] : null];
}
/** Nächste Tage mit freien Slots (für Agent/Buchungsseite). */
function appt_next_days(int $acc, ?int $serviceId, int $days=10): array {
    $s=appt_settings($acc); $tz=appt_tz($s); $d=new DateTimeImmutable('today',$tz); $out=[];
    for($i=0;$i<$days;$i++){ $r=appt_slots($acc,$d->format('Y-m-d'),$serviceId); if(!empty($r['slots'])) $out[]=['date'=>$r['date'],'weekday'=>$r['weekday'],'slots'=>array_slice($r['slots'],0,12),'more'=>max(0,count($r['slots'])-12)]; $d=$d->modify('+1 day'); }
    return $out;
}

/* ---------- Buchen / Stornieren ---------- */
function appt_code(): string { $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $c=''; for($i=0;$i<8;$i++) $c.=$a[random_int(0,strlen($a)-1)]; return $c; }
function appt_book(int $acc, ?int $serviceId, string $start, string $name, string $phone, string $email, string $note, string $source): array {
    $s=appt_settings($acc); $tz=appt_tz($s);
    if(!$s['appt_on']) return ['error'=>'appointments_disabled'];
    $name=mb_substr(trim($name),0,120); $phone=mb_substr(trim($phone),0,40); $email=filter_var(trim($email),FILTER_VALIDATE_EMAIL)?trim($email):''; $note=mb_substr(trim($note),0,500);
    if($name==='') return ['error'=>'name_required']; if($phone==='' && $email==='') return ['error'=>'contact_required','hint'=>'Telefonnummer oder E-Mail'];
    if(!preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/',$start,$m)) return ['error'=>'start_format','hint'=>'YYYY-MM-DD HH:MM'];
    $svc=appt_service($acc,$serviceId); $dur=$svc ? (int)$svc['duration_min'] : $s['slot_min'];
    $slots=appt_slots($acc,$m[1],$svc['id'] ?? null);
    if(!in_array($m[2],$slots['slots'] ?? [],true)) return ['error'=>'slot_unavailable','date'=>$m[1],'available'=>array_slice($slots['slots'] ?? [],0,10),'closed'=>$slots['closed'] ?? false];
    $st=new DateTimeImmutable($m[1].' '.$m[2],$tz); $en=$st->modify('+'.$dur.' minutes'); $p=th_db();
    $p->beginTransaction();
    try{
        $q=$p->prepare("SELECT COUNT(*) FROM appt_bookings WHERE account_id=? AND status IN ('booked','done') AND start_at < ? AND end_at > ? FOR UPDATE"); $q->execute([$acc,$en->format('Y-m-d H:i:s'),$st->format('Y-m-d H:i:s')]);
        if((int)$q->fetchColumn()>0){ $p->rollBack(); return ['error'=>'slot_unavailable','date'=>$m[1]]; }
        $code=appt_code();
        $p->prepare("INSERT INTO appt_bookings(account_id,service_id,service_name,start_at,end_at,customer_name,phone,email,note,status,source,code) VALUES(?,?,?,?,?,?,?,?,?,'booked',?,?)")
          ->execute([$acc,$svc['id'] ?? null,$svc['name'] ?? 'Termin',$st->format('Y-m-d H:i:s'),$en->format('Y-m-d H:i:s'),$name,$phone,$email,$note,mb_substr($source,0,16),$code]);
        $id=(int)$p->lastInsertId(); $p->commit();
    }catch(Throwable $e){ $p->rollBack(); return ['error'=>'db','message'=>$e->getMessage()]; }
    $b=appt_by_code($code); if($acc!==0) appt_notify($b,$s,'confirm');
    return ['ok'=>true,'booking'=>appt_public($b,$s)];
}
function appt_by_code(string $code): ?array { appt_bootstrap(); $code=strtoupper(preg_replace('/[^A-Z0-9]/i','',$code)); if($code==='') return null; $s=th_db()->prepare("SELECT * FROM appt_bookings WHERE code=?"); $s->execute([$code]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function appt_public(array $b, ?array $s=null): array {
    $s=$s ?: appt_settings((int)$b['account_id']); $tz=appt_tz($s); $st=new DateTimeImmutable($b['start_at'],$tz);
    $wd=['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'][(int)$st->format('w')];
    return ['code'=>$b['code'],'service'=>$b['service_name'],'date'=>$st->format('Y-m-d'),'time'=>$st->format('H:i'),'end'=>(new DateTimeImmutable($b['end_at'],$tz))->format('H:i'),'weekday'=>$wd,
        'human'=>$wd.', '.$st->format('d.m.Y').' um '.$st->format('H:i').' Uhr','name'=>$b['customer_name'],'phone'=>$b['phone'],'email'=>$b['email'],'note'=>$b['note'],'status'=>$b['status'],'company'=>$s['company'],'address'=>$s['address'],
        'manage_url'=>'https://chat-help.com/chat/appt.php?c='.$b['code']];
}
function appt_cancel(string $code, int $acc=-1): array {
    $b=appt_by_code($code); if(!$b || ($acc>=0 && (int)$b['account_id']!==$acc)) return ['error'=>'not_found'];
    if($b['status']==='cancelled') return ['ok'=>true,'already'=>true,'booking'=>appt_public($b)];
    th_db()->prepare("UPDATE appt_bookings SET status='cancelled' WHERE id=?")->execute([$b['id']]); $b['status']='cancelled';
    $s=appt_settings((int)$b['account_id']); if((int)$b['account_id']!==0) appt_notify($b,$s,'cancel');
    return ['ok'=>true,'booking'=>appt_public($b,$s)];
}
function appt_find_by_phone(int $acc, string $phone): array {
    $d=preg_replace('/\D/','',$phone); if(strlen($d)<6) return [];
    $s=th_db()->prepare("SELECT * FROM appt_bookings WHERE account_id=? AND status='booked' AND start_at >= NOW() AND REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-','') LIKE ? ORDER BY start_at LIMIT 5"); $s->execute([$acc,'%'.substr($d,-8)]);
    return array_map(fn($b)=>appt_public($b),$s->fetchAll(PDO::FETCH_ASSOC));
}
function appt_bookings(int $acc, string $from, string $to): array {
    appt_bootstrap(); $s=th_db()->prepare("SELECT * FROM appt_bookings WHERE account_id=? AND start_at >= ? AND start_at < ? ORDER BY start_at"); $s->execute([$acc,$from.' 00:00:00',$to.' 23:59:59']);
    $set=appt_settings($acc); return array_map(fn($b)=>appt_public($b,$set)+['id'=>(int)$b['id'],'source'=>$b['source'],'created_at'=>$b['created_at']],$s->fetchAll(PDO::FETCH_ASSOC));
}
function appt_set_status(int $acc, int $id, string $status): bool {
    if(!in_array($status,['booked','done','noshow','cancelled'],true)) return false;
    return th_db()->prepare("UPDATE appt_bookings SET status=? WHERE id=? AND account_id=?")->execute([$status,$id,$acc]);
}
function appt_ics(array $b, array $s): string {
    $tz=appt_tz($s); $st=(new DateTimeImmutable($b['start_at'],$tz))->setTimezone(new DateTimeZone('UTC')); $en=(new DateTimeImmutable($b['end_at'],$tz))->setTimezone(new DateTimeZone('UTC'));
    $esc=fn($t)=>str_replace([',',';',"\n"],['\,','\;','\n'],(string)$t);
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ChatHelp//Termine//DE\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:".$b['code']."@chat-help.com\r\nDTSTAMP:".gmdate('Ymd\THis\Z')."\r\nDTSTART:".$st->format('Ymd\THis\Z')."\r\nDTEND:".$en->format('Ymd\THis\Z')."\r\nSUMMARY:".$esc($b['service_name'].' – '.$s['company'])."\r\nLOCATION:".$esc($s['address'])."\r\nDESCRIPTION:".$esc('Buchungscode '.$b['code'].' · Verwalten: https://chat-help.com/chat/appt.php?c='.$b['code'])."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}
/* ---------- E-Mails ---------- */
function appt_notify(array $b, array $s, string $kind): void {
    $pub=appt_public($b,$s); $company=$s['company'] ?: 'Ihr Termin';
    $box=function(string $title,string $body) use($pub,$s){ return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:30px;border:1px solid #e6e8ef;border-radius:20px;color:#0f1222"><div style="font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:#b8862b;font-weight:700">'.htmlspecialchars($s['company']).'</div><h2 style="margin:10px 0 14px;font-size:22px">'.$title.'</h2>'.$body
        .'<div style="margin:18px 0;padding:16px;border-radius:14px;background:#f6ecd3"><b style="font-size:17px">'.htmlspecialchars($pub['service']).'</b><br>'.htmlspecialchars($pub['human']).' (bis '.htmlspecialchars($pub['end']).' Uhr)'.($s['address']?'<br>'.htmlspecialchars($s['address']):'').'<br><span style="color:#6b7083;font-size:13px">Buchungscode '.$pub['code'].'</span></div>'
        .'<p style="font-size:13px;color:#6b7083">Termin verwalten / absagen / in den Kalender: <a href="'.$pub['manage_url'].'" style="color:#b8862b">'.$pub['manage_url'].'</a></p></div>'; };
    if($kind==='confirm'){
        if($b['email']) th_mail($b['email'],'Terminbestätigung · '.$company.' · '.$pub['human'],$box('Ihr Termin ist bestätigt ✓','<p>Hallo '.htmlspecialchars($b['customer_name']).',</p><p>vielen Dank für Ihre Buchung.'.($s['confirm_text']?' '.htmlspecialchars($s['confirm_text']):'').'</p>'));
        $to=$s['notify_email'] ?: appt_owner_email((int)$b['account_id']); if($to) th_mail($to,'Neuer Termin: '.$pub['service'].' · '.$pub['human'],$box('Neue Buchung','<p><b>'.htmlspecialchars($b['customer_name']).'</b> · '.htmlspecialchars($b['phone']).' · '.htmlspecialchars($b['email']).'<br>Quelle: '.htmlspecialchars($b['source']).($b['note']?'<br>Notiz: '.htmlspecialchars($b['note']):'').'</p>'));
    } elseif($kind==='cancel'){
        if($b['email']) th_mail($b['email'],'Termin abgesagt · '.$company,$box('Ihr Termin wurde abgesagt','<p>Hallo '.htmlspecialchars($b['customer_name']).', Ihr Termin wurde storniert. Gern vereinbaren Sie einen neuen Termin.</p>'));
        $to=$s['notify_email'] ?: appt_owner_email((int)$b['account_id']); if($to) th_mail($to,'Termin abgesagt: '.$pub['service'].' · '.$pub['human'],$box('Absage','<p>'.htmlspecialchars($b['customer_name']).' · '.htmlspecialchars($b['phone']).'</p>'));
    } elseif($kind==='remind'){
        if($b['email']) th_mail($b['email'],'Erinnerung: morgen '.$pub['time'].' Uhr · '.$company,$box('Erinnerung an Ihren Termin','<p>Hallo '.htmlspecialchars($b['customer_name']).', wir freuen uns auf Sie morgen.</p>'));
    }
}
/** Öffentliche Buchungsseite des Kontos (Schlüssel = widget_key einer Agent-Konfiguration, sonst wird einer angelegt). */
function appt_booking_url(int $acc): string {
    if($acc===0) return 'https://chat-help.com/chat/book.php?k=demo';
    $s=th_db()->prepare("SELECT widget_key FROM agent_configs WHERE account_id=? AND widget_key IS NOT NULL ORDER BY (plan='agent-termine') DESC, id LIMIT 1"); $s->execute([$acc]); $wk=(string)$s->fetchColumn();
    if($wk==='') { th_agent_cfg_save($acc,'agent-termine',[],false); $s->execute([$acc]); $wk=(string)$s->fetchColumn(); }
    return 'https://chat-help.com/chat/book.php?k='.$wk;
}
/** Buchungsschlüssel (k) → Konto-ID (0 = Demo). */
function appt_account_by_key(string $k): ?int {
    if($k==='demo') return 0;
    if(!preg_match('/^wk_[a-f0-9]{36}$/',$k)) return null;
    $s=th_db()->prepare("SELECT account_id FROM agent_configs WHERE widget_key=?"); $s->execute([$k]); $a=$s->fetchColumn(); return $a===false ? null : (int)$a;
}
function appt_owner_email(int $acc): string { if($acc<=0) return ''; $s=th_db()->prepare("SELECT email FROM token_accounts WHERE id=?"); $s->execute([$acc]); return (string)($s->fetchColumn() ?: ''); }
/** Erinnerungen 20–28 h vorher – wird bei API-Aufrufen höchstens alle 10 Minuten ausgeführt (kein Cron nötig). */
function appt_reminders_lazy(): void {
    $lock=(is_writable(__DIR__)?__DIR__:sys_get_temp_dir()).'/.appt_reminder'; if(is_file($lock) && time()-filemtime($lock) < 600) return; @touch($lock);
    appt_bootstrap(); $q=th_db()->query("SELECT * FROM appt_bookings WHERE status='booked' AND reminded=0 AND account_id>0 AND email<>'' AND start_at BETWEEN (NOW() + INTERVAL 20 HOUR) AND (NOW() + INTERVAL 28 HOUR) LIMIT 50");
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $b){ appt_notify($b,appt_settings((int)$b['account_id']),'remind'); th_db()->prepare("UPDATE appt_bookings SET reminded=1 WHERE id=?")->execute([$b['id']]); }
}

/* ---------- Werkzeuge für den Agenten ---------- */
function appt_tools(): array {
    return [
      ['name'=>'list_services','description'=>'Liste der buchbaren Leistungen mit Dauer und Preis.','input_schema'=>['type'=>'object','properties'=>new stdClass(),'required'=>[]]],
      ['name'=>'get_available_slots','description'=>'Freie Terminzeiten für ein Datum (YYYY-MM-DD) und optional eine Leistung (service_id). Ohne Datum: die nächsten Tage mit freien Zeiten.','input_schema'=>['type'=>'object','properties'=>['date'=>['type'=>'string','description'=>'YYYY-MM-DD'],'service_id'=>['type'=>'integer']],'required'=>[]]],
      ['name'=>'book_appointment','description'=>'Termin verbindlich buchen. Vorher Leistung, Datum, Uhrzeit, Name und Telefonnummer (oder E-Mail) vom Kunden bestätigen lassen.','input_schema'=>['type'=>'object','properties'=>['service_id'=>['type'=>'integer'],'start'=>['type'=>'string','description'=>'YYYY-MM-DD HH:MM'],'name'=>['type'=>'string'],'phone'=>['type'=>'string'],'email'=>['type'=>'string'],'note'=>['type'=>'string']],'required'=>['start','name']]],
      ['name'=>'find_appointments','description'=>'Bestehende Termine finden: per Buchungscode, sonst nur für die verifizierte Absender-/Anrufernummer (WhatsApp/Telefon) – fremde Nummern sind gesperrt.','input_schema'=>['type'=>'object','properties'=>['phone'=>['type'=>'string'],'code'=>['type'=>'string']],'required'=>[]]],
      ['name'=>'cancel_appointment','description'=>'Termin per Buchungscode absagen (nur nach ausdrücklicher Bestätigung des Kunden).','input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string']],'required'=>['code']]],
    ];
}
function appt_exec(int $acc, string $name, array $in, string $source, string $from=''): array {
    switch($name){
        case 'list_services': return ['services'=>array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['name'],'duration_min'=>$s['duration_min'],'price_eur'=>$s['price_cents']?round($s['price_cents']/100,2):null],appt_services($acc))];
        case 'get_available_slots': $sid=isset($in['service_id'])?(int)$in['service_id']:null; if(!empty($in['date'])) return appt_slots($acc,(string)$in['date'],$sid); return ['next_days'=>appt_next_days($acc,$sid,10)];
        case 'book_appointment': return appt_book($acc,isset($in['service_id'])?(int)$in['service_id']:null,(string)($in['start']??''),(string)($in['name']??''),(string)($in['phone']??''),(string)($in['email']??''),(string)($in['note']??''),$source);
        case 'find_appointments': if(!empty($in['code'])){ $b=appt_by_code((string)$in['code']); return $b && (int)$b['account_id']===$acc ? ['appointments'=>[appt_public($b)]] : ['appointments'=>[]]; } $ph=function_exists('ch_verified_phone') ? ch_verified_phone($acc,$source,$from,(string)($in['phone']??'')) : ($acc===0 ? (string)($in['phone']??'') : null);
            if($ph===null) return ['appointments'=>[],'error'=>'code_required','hint'=>defined('CH_CODE_ONLY_HINT') ? CH_CODE_ONLY_HINT : 'Bitte nach dem Buchungscode fragen.'];
            return ['appointments'=>appt_find_by_phone($acc,$ph)];
        case 'cancel_appointment': return appt_cancel((string)($in['code']??''),$acc);
    }
    return ['error'=>'unknown_tool'];
}
/** Zusatz für den System-Prompt, wenn Terminbuchung aktiv ist. */
function appt_system_hint(int $acc, string $lang='de'): string {
    $s=appt_settings($acc); $tz=appt_tz($s); $now=new DateTimeImmutable('now',$tz);
    $wd=['So','Mo','Di','Mi','Do','Fr','Sa']; $h=[]; for($w=1;$w<=7;$w++){ $k=(string)($w%7); $r=$s['hours'][$k] ?? []; $h[]=$wd[$w%7].': '.($r?implode(', ',array_map(fn($x)=>$x[0].'–'.$x[1],$r)):'geschlossen'); }
    $svc=implode('; ',array_map(fn($x)=>"#{$x['id']} {$x['name']} ({$x['duration_min']} Min".($x['price_cents']?', '.number_format($x['price_cents']/100,2,',','.').' €':'').")",appt_services($acc)));
    return "\n\nTERMINBUCHUNG (aktiv): Heute ist ".$now->format('l, d.m.Y H:i')." (".$s['tz']."). Öffnungszeiten: ".implode(' · ',$h).". Leistungen: ".($svc?:'keine hinterlegt – Dauer '.$s['slot_min'].' Min').". Vorlauf mind. ".$s['lead_min']." Min, buchbar bis ".$s['max_days']." Tage im Voraus.
REGELN: Freie Zeiten NUR über das Werkzeug get_available_slots nennen (niemals raten). Zum Buchen brauchst du Leistung, Datum, Uhrzeit, Name und Telefonnummer (oder E-Mail) – fasse alles in einem Satz zusammen und frage „Soll ich das so buchen?“; erst nach dem Ja book_appointment aufrufen. Nach erfolgreicher Buchung: Datum, Uhrzeit, Leistung und Buchungscode nennen und sagen, dass eine Bestätigung per E-Mail kommt (falls E-Mail angegeben). Bestehende Termine nur per Buchungscode (oder automatisch über die Nummer, von der der Kunde per WhatsApp schreibt bzw. anruft) – niemals Termine zu einer genannten fremden Nummer herausgeben. Absagen nur nach ausdrücklicher Bestätigung. Relative Angaben („morgen“, „nächsten Dienstag“) immer auf ein konkretes Datum umrechnen und nennen.";
}
