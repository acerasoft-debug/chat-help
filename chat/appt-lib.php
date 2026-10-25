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
    $p->exec("CREATE TABLE IF NOT EXISTS appt_jobs (name VARCHAR(40) PRIMARY KEY, last_run DATETIME NULL) ENGINE=InnoDB");   // Zeitsperre für lazy Jobs (Erinnerungen), ersetzt die frühere Sperrdatei
    /* Hausbesuch: none = nur im Betrieb, both = Kunde wählt, home = nur beim Kunden; Anfahrt = Pauschale + je km (ab inkl. km), bis max. km */
    $c=$p->query("SHOW COLUMNS FROM appt_settings")->fetchAll(PDO::FETCH_COLUMN);
    foreach(['visit_mode'=>"VARCHAR(8) DEFAULT 'none'",'visit_fee_cents'=>'INT DEFAULT 0','visit_km_cents'=>'INT DEFAULT 0','visit_km_incl'=>'DECIMAL(4,1) DEFAULT 0','visit_max_km'=>'DECIMAL(5,1) DEFAULT 0'] as $col=>$def)
        if(!in_array($col,$c,true)) $p->exec("ALTER TABLE appt_settings ADD COLUMN $col $def");
    $c=$p->query("SHOW COLUMNS FROM appt_bookings")->fetchAll(PDO::FETCH_COLUMN);
    foreach(['visit_address'=>"VARCHAR(240) DEFAULT ''",'travel_fee_cents'=>'INT DEFAULT 0','visit_km'=>'DECIMAL(5,1) NULL'] as $col=>$def)
        if(!in_array($col,$c,true)) $p->exec("ALTER TABLE appt_bookings ADD COLUMN $col $def");
    if(random_int(1,40)===1) $p->exec("DELETE FROM appt_bookings WHERE account_id=0 AND created_at < (NOW() - INTERVAL 2 DAY)");
}

/* ---------- Einstellungen & Leistungen ---------- */
function appt_settings(int $acc): array {
    appt_bootstrap();
    $s=th_db()->prepare("SELECT * FROM appt_settings WHERE account_id=?"); $s->execute([$acc]); $r=$s->fetch(PDO::FETCH_ASSOC);
    if(!$r){ $r=['account_id'=>$acc,'appt_on'=>$acc===0?1:0,'company'=>$acc===0?APPT_DEMO['company']:'','address'=>$acc===0?APPT_DEMO['address']:'','slot_min'=>30,'buffer_min'=>0,'lead_min'=>120,'max_days'=>60,'tz'=>'Europe/Berlin','hours'=>null,'notify_email'=>'','confirm_text'=>'']; }
    $h=json_decode((string)($r['hours']??''),true); $r['hours']=is_array($h)&&$h ? appt_hours_parse($h) : APPT_DEFAULT_HOURS;   // gespeicherte Altwerte (z. B. 25:00) werden beim Lesen verworfen statt appt_slots abstürzen zu lassen
    foreach(['slot_min','buffer_min','lead_min','max_days','appt_on','visit_fee_cents','visit_km_cents'] as $k) $r[$k]=(int)($r[$k] ?? 0);
    $r['visit_mode']=in_array($r['visit_mode'] ?? '',['both','home'],true) ? $r['visit_mode'] : 'none'; $r['visit_km_incl']=round((float)($r['visit_km_incl'] ?? 0),1); $r['visit_max_km']=round((float)($r['visit_max_km'] ?? 0),1);
    return $r;
}
/** Anfahrt als Satz (Agent, Studio, Bestätigung). */
function appt_visit_text(array $s): string {
    if($s['visit_mode']==='none') return '';
    $e=fn(int $c)=>number_format($c/100,2,',','.').' €'; $k=fn(float $x)=>str_replace('.',',',(string)round($x,1));
    $t=($s['visit_mode']==='home' ? 'Termine nur als Hausbesuch' : 'Hausbesuch möglich').': Anfahrt '.($s['visit_fee_cents']>0 ? $e($s['visit_fee_cents']) : 'ohne Pauschale');
    if($s['visit_km_cents']>0) $t.=' + '.$e($s['visit_km_cents']).' je km'.($s['visit_km_incl']>0 ? ' ab '.$k($s['visit_km_incl']).' km' : '');
    if($s['visit_max_km']>0) $t.=', bis '.$k($s['visit_max_km']).' km';
    return $t;
}
/** Hausbesuch-Adresse prüfen: Entfernung (geschätzte Fahrstrecke ab Betriebsadresse), Anfahrtskosten, innerhalb des Radius? */
function appt_visit_check(int $acc, string $address, ?array $s=null): array {
    $s=$s ?: appt_settings($acc);
    if($s['visit_mode']==='none') return ['ok'=>false,'error'=>'no_home_visits','hint'=>'Dieser Betrieb macht keine Hausbesuche – Termine finden vor Ort statt.'];
    $address=trim($address); if(mb_strlen($address)<6) return ['ok'=>false,'error'=>'address_required','hint'=>'Für den Hausbesuch bitte Straße, Hausnummer, PLZ und Ort erfragen.'];
    if(!function_exists('ord_geocode') && is_file(__DIR__.'/order-lib.php')) require_once __DIR__.'/order-lib.php';
    $to=ord_geocode($address); if(!$to) return ['ok'=>false,'error'=>'address_not_found','hint'=>'Adresse nicht gefunden – bitte Straße, Hausnummer, PLZ und Ort genau erfragen.'];
    $from=trim((string)$s['address'])!=='' ? ord_geocode((string)$s['address']) : null;
    $km=$from ? round(ord_distance_km($from['lat'],$from['lon'],$to['lat'],$to['lon'])*(defined('ORD_ROAD_FACTOR') ? ORD_ROAD_FACTOR : 1.25),1) : null;
    if($km!==null && $s['visit_max_km']>0 && $km>$s['visit_max_km']) return ['ok'=>false,'error'=>'too_far','km'=>$km,'max_km'=>$s['visit_max_km'],'hint'=>'Etwa '.str_replace('.',',',(string)$km).' km entfernt – Hausbesuche nur bis '.str_replace('.',',',(string)$s['visit_max_km']).' km.'.($s['visit_mode']==='both' ? ' Termin vor Ort anbieten.' : '')];
    $fee=$s['visit_fee_cents']+($km!==null ? max(0.0,$km-$s['visit_km_incl'])*$s['visit_km_cents'] : 0);
    return ['ok'=>true,'km'=>$km,'travel_fee_cents'=>(int)(round($fee/10)*10),'travel_fee_eur'=>round(round($fee/10)*10/100,2),'address'=>$to['display'] ?: $address,'rule'=>appt_visit_text($s),
        'note'=>$km===null ? 'Entfernung unbekannt (Betriebsadresse fehlt) – nur die Pauschale berechnet.' : null];
}
/** Öffnungszeiten prüfen: je Wochentag 0–6 Liste [von,bis] im Format HH:MM (auch H:MM), bis darf 24:00 sein.
 *  Gemeinsame Konvention (Bestellungen + Termine): bis ≤ von = endet am Folgetag (['17:00','00:00'], ['18:00','01:30']; gleich = 24 h), '24:00' = Tagesende.
 *  Ungültige Uhrzeiten → $err (deutscher Hinweis), das Intervall fehlt in der Rückgabe. */
function appt_hours_parse(array $raw, &$err=null): array {
    $err=null; $wd=['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag']; $out=[];
    $hm=function($x,bool $end=false): ?string { if(!is_scalar($x) || !preg_match('/^(\d{1,2}):(\d{2})$/',trim((string)$x),$m)) return null; $h=(int)$m[1]; $i=(int)$m[2]; return ($i<60 && ($h<24 || ($end && $h===24 && $i===0))) ? sprintf('%02d:%02d',$h,$i) : null; };
    $show=fn($x)=>is_scalar($x) ? mb_substr(trim((string)$x),0,20) : '?';   // verschachtelte Werte nie als „Array“ (keine PHP-Warnung)
    for($w=0;$w<=6;$w++){ $rows=[]; $list=$raw[(string)$w] ?? $raw[$w] ?? [];
        foreach(is_array($list)?$list:[$list] as $iv){ $v=is_array($iv) ? array_values($iv) : [$iv];
            $f=count($v)>=2 ? $hm($v[0]) : null; $t=count($v)>=2 ? $hm($v[1],true) : null;
            if($f===null || $t===null){ $err??=$wd[$w].': ungültige Uhrzeit „'.implode('–',array_map($show,array_slice($v,0,2))).'“ – bitte HH:MM (00:00–23:59, Ende auch 24:00; Ende vor dem Beginn = über Mitternacht).'; continue; }
            $rows[]=[$f,$t]; }
        $out[(string)$w]=$rows; }
    return $out;
}
/** Speichert Einstellungen. Ungültige Öffnungszeiten → nichts wird gespeichert, Rückgabe = bisherige Einstellungen, $err=['error'=>'hours_invalid','message'=>…]. */
function appt_settings_save(int $acc, array $d, &$err=null): array {
    appt_bootstrap(); $cur=appt_settings($acc); $err=null;
    $hours=$cur['hours'];
    if(isset($d['hours']) && is_array($d['hours'])){ $hours=appt_hours_parse($d['hours'],$herr);
        if($herr!==null){ $err=['error'=>'hours_invalid','message'=>'Öffnungszeiten nicht gespeichert – '.$herr,'hint'=>$herr]; if(func_num_args()<3) error_log('CH appt_settings_save acc='.$acc.' ohne $err: '.$herr); return $cur; } }
    $v=['appt_on'=>(int)!empty($d['appt_on'] ?? $cur['appt_on']),'company'=>mb_substr(trim((string)($d['company']??$cur['company'])),0,120),'address'=>mb_substr(trim((string)($d['address']??$cur['address'])),0,200),
        'slot_min'=>max(5,min(240,(int)($d['slot_min']??$cur['slot_min']))),'buffer_min'=>max(0,min(120,(int)($d['buffer_min']??$cur['buffer_min']))),'lead_min'=>max(0,min(10080,(int)($d['lead_min']??$cur['lead_min']))),
        'max_days'=>max(1,min(365,(int)($d['max_days']??$cur['max_days']))),'tz'=>in_array($d['tz']??'',timezone_identifiers_list(),true)?$d['tz']:$cur['tz'],
        'notify_email'=>filter_var((string)($d['notify_email']??$cur['notify_email']),FILTER_VALIDATE_EMAIL)?:'' ,'confirm_text'=>mb_substr(trim((string)($d['confirm_text']??$cur['confirm_text'])),0,400)];
    th_db()->prepare("INSERT INTO appt_settings(account_id,appt_on,company,address,slot_min,buffer_min,lead_min,max_days,tz,hours,notify_email,confirm_text) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE appt_on=VALUES(appt_on),company=VALUES(company),address=VALUES(address),slot_min=VALUES(slot_min),buffer_min=VALUES(buffer_min),lead_min=VALUES(lead_min),max_days=VALUES(max_days),tz=VALUES(tz),hours=VALUES(hours),notify_email=VALUES(notify_email),confirm_text=VALUES(confirm_text),updated_at=NOW()")
      ->execute([$acc,$v['appt_on'],$v['company'],$v['address'],$v['slot_min'],$v['buffer_min'],$v['lead_min'],$v['max_days'],$v['tz'],json_encode($hours),$v['notify_email'],$v['confirm_text']]);
    $num=fn($k,$lo,$hi,$dec=0)=>array_key_exists($k,$d) && is_numeric($d[$k]) ? round(max($lo,min($hi,(float)$d[$k])),$dec) : $cur[$k];
    th_db()->prepare("UPDATE appt_settings SET visit_mode=?,visit_fee_cents=?,visit_km_cents=?,visit_km_incl=?,visit_max_km=? WHERE account_id=?")
      ->execute([in_array($d['visit_mode'] ?? '',['none','both','home'],true) ? $d['visit_mode'] : $cur['visit_mode'],(int)$num('visit_fee_cents',0,50000),(int)$num('visit_km_cents',0,2000),$num('visit_km_incl',0,50,1),$num('visit_max_km',0,300,1),$acc]);
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
    else $p->prepare("UPDATE appt_services SET active=0 WHERE account_id=?")->execute([$acc]);   // leere Liste = alle Leistungen entfernt
}
function appt_service(int $acc, ?int $id): ?array { foreach(appt_services($acc) as $s){ if($id===null || (int)$s['id']===(int)$id) return $s; } return null; }
/** Leistung für Slots/Buchung auflösen → [svc|null, dauer_min, fehler|null]. id>0 muss eine AKTIVE Leistung des Kontos sein (sonst unknown_service – nie stillschweigend die erste Leistung).
 *  Ohne id (null/0): genau eine Leistung → diese; keine → Taktung slot_min; mehrere → $need ? service_required : allgemeine Zeiten in slot_min. */
function appt_resolve_service(int $acc, ?int $id, ?array $s=null, bool $need=false): array {
    $s=$s ?: appt_settings($acc); $all=appt_services($acc); $list=array_map(fn($x)=>['id'=>(int)$x['id'],'name'=>$x['name'],'duration_min'=>(int)$x['duration_min']],$all);
    if($id!==null && $id>0){ foreach($all as $x) if((int)$x['id']===$id) return [$x,(int)$x['duration_min'],null];
        return [null,0,['error'=>'unknown_service','service_id'=>$id,'hint'=>$all ? 'Diese Leistung gibt es nicht (mehr). Bitte eine der angebotenen Leistungen wählen und deren service_id verwenden.' : 'Es sind keine Leistungen hinterlegt – bitte ohne service_id anfragen bzw. buchen.','message'=>'Diese Leistung ist nicht (mehr) buchbar.','services'=>$list]]; }
    if(count($all)===1) return [$all[0],(int)$all[0]['duration_min'],null];
    if($all && $need) return [null,0,['error'=>'service_required','hint'=>'Bitte zuerst die gewünschte Leistung wählen (service_id aus der Liste).','services'=>$list]];
    return [null,(int)$s['slot_min'],null];
}
/** Jetzt als Wanduhr der Konto-Zeitzone (start_at/end_at sind in dieser Zeitzone gespeichert – nie mit MySQL NOW() vergleichen). */
function appt_now(array $s): DateTimeImmutable { return new DateTimeImmutable('now',appt_tz($s)); }
/** Anzahl Überschneidungen mit aktiven Buchungen (booked/done) und Sperrzeiten, jeweils inkl. Puffer; $lock → FOR UPDATE (in einer Transaktion). */
function appt_conflicts(int $acc, DateTimeImmutable $st, DateTimeImmutable $en, int $buf=0, int $exceptId=0, bool $lock=false): int {
    $p=th_db(); $a=$st->modify('-'.max(0,$buf).' minutes')->format('Y-m-d H:i:s'); $z=$en->modify('+'.max(0,$buf).' minutes')->format('Y-m-d H:i:s');
    $q=$p->prepare("SELECT COUNT(*) FROM appt_bookings WHERE account_id=? AND id<>? AND status IN ('booked','done') AND start_at < ? AND end_at > ?".($lock?" FOR UPDATE":"")); $q->execute([$acc,$exceptId,$z,$a]); $n=(int)$q->fetchColumn();
    $q=$p->prepare("SELECT COUNT(*) FROM appt_blocks WHERE account_id=? AND start_at < ? AND end_at > ?"); $q->execute([$acc,$z,$a]);
    return $n+(int)$q->fetchColumn();
}

/* ---------- Slots ---------- */
function appt_tz(array $s): DateTimeZone { try{ return new DateTimeZone($s['tz'] ?: 'Europe/Berlin'); }catch(Throwable $e){ return new DateTimeZone('Europe/Berlin'); } }
/** Freie Startzeiten (HH:MM) für ein Datum und eine Leistung. */
function appt_slots(int $acc, string $date, ?int $serviceId=null): array {
    $s=appt_settings($acc); $tz=appt_tz($s);
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$dm)) return ['error'=>'date_format','hint'=>'YYYY-MM-DD'];
    if(!checkdate((int)$dm[2],(int)$dm[3],(int)$dm[1])) return ['error'=>'date_invalid','date'=>$date,'hint'=>'Dieses Datum gibt es nicht (z. B. 31.11. oder 30.02.) – bitte ein gültiges Datum im Format YYYY-MM-DD nennen.'];
    [$svc,$dur,$err]=appt_resolve_service($acc,$serviceId,$s); if($err) return $err+['date'=>$date];
    $sv=$svc ? ['id'=>(int)$svc['id'],'name'=>$svc['name'],'duration_min'=>$dur] : null; $gen=!$svc && appt_services($acc) ? ['hint'=>'Ohne service_id: allgemeine Zeiten im '.$s['slot_min'].'-Minuten-Takt – für verbindliche Zeiten die Leistung (service_id) angeben.'] : [];
    $day=new DateTimeImmutable($date.' 00:00',$tz); $now=new DateTimeImmutable('now',$tz);
    if($day < $now->setTime(0,0)) return ['date'=>$date,'slots'=>[],'closed'=>false,'reason'=>'past'];
    if($day > $now->modify('+'.$s['max_days'].' days')) return ['date'=>$date,'slots'=>[],'closed'=>false,'reason'=>'too_far','max_days'=>$s['max_days']];
    /* Öffnungsfenster als Zeitpunkte: eigene Intervalle des Tages (bis ≤ von → Ende am Folgetag, 24:00 = Tagesende) + Teil nach Mitternacht der Nacht-Intervalle des Vortags */
    $iv=[]; $next=$day->modify('+1 day');
    foreach([$day->modify('-1 day'),$day] as $x) foreach($s['hours'][(string)(int)$x->format('w')] ?? [] as [$o,$c]){ $a=new DateTimeImmutable($x->format('Y-m-d').' '.$o,$tz); $b=$c==='24:00' ? $x->modify('+1 day') : new DateTimeImmutable($x->format('Y-m-d').' '.$c,$tz); if($b<=$a) $b=$b->modify('+1 day'); if($b>$day) $iv[]=[$a,$b]; }
    if(!$iv) return ['date'=>$date,'slots'=>[],'closed'=>true,'service'=>$sv];
    $p=th_db(); $lo=$day->modify('-1 day')->format('Y-m-d H:i:s'); $hi=$day->modify('+2 days')->format('Y-m-d H:i:s');   // Slots beginnen am Tag, enden ggf. nach Mitternacht (+ Puffer)
    $q=$p->prepare("SELECT start_at,end_at FROM appt_bookings WHERE account_id=? AND status IN ('booked','done') AND start_at < ? AND end_at > ?"); $q->execute([$acc,$hi,$lo]);
    $busy=array_map(fn($r)=>[new DateTimeImmutable($r['start_at'],$tz),new DateTimeImmutable($r['end_at'],$tz)],$q->fetchAll(PDO::FETCH_ASSOC));
    $q=$p->prepare("SELECT start_at,end_at FROM appt_blocks WHERE account_id=? AND start_at < ? AND end_at > ?"); $q->execute([$acc,$hi,$lo]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $busy[]=[new DateTimeImmutable($r['start_at'],$tz),new DateTimeImmutable($r['end_at'],$tz)];
    $minStart=$now->modify('+'.$s['lead_min'].' minutes'); $buf=$s['buffer_min']; $out=[];
    foreach($iv as [$t,$end]){   // Raster ab Intervallbeginn (beim Vortags-Intervall nahtlos über Mitternacht); nur Beginnzeiten an diesem Tag
        while($t->modify('+'.$dur.' minutes') <= $end && $t < $next){
            if($t < $day){ $t=$t->modify('+'.$s['slot_min'].' minutes'); continue; }
            $te=$t->modify('+'.$dur.' minutes'); $ok=$t >= $minStart;
            if($ok) foreach($busy as [$bs,$be]){ if($t < $be->modify('+'.$buf.' minutes') && $te->modify('+'.$buf.' minutes') > $bs){ $ok=false; break; } }
            if($ok) $out[]=$t->format('H:i');
            $t=$t->modify('+'.$s['slot_min'].' minutes');
        } }
    $out=array_values(array_unique($out)); sort($out);   // überlappende Intervalle eines Tages → keine doppelten Zeiten
    return ['date'=>$date,'weekday'=>$day->format('l'),'slots'=>$out,'closed'=>false,'service'=>$sv]+$gen;
}
/** Nächste Tage mit freien Slots (für Agent/Buchungsseite). */
function appt_next_days(int $acc, ?int $serviceId, int $days=10): array {
    $s=appt_settings($acc); $tz=appt_tz($s); $d=new DateTimeImmutable('today',$tz); $out=[];
    for($i=0;$i<$days;$i++){ $r=appt_slots($acc,$d->format('Y-m-d'),$serviceId); if(!empty($r['slots'])) $out[]=['date'=>$r['date'],'weekday'=>$r['weekday'],'slots'=>array_slice($r['slots'],0,12),'more'=>max(0,count($r['slots'])-12)]; $d=$d->modify('+1 day'); }
    return $out;
}

/* ---------- Buchen / Stornieren ---------- */
function appt_code(): string { $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $c=''; for($i=0;$i<8;$i++) $c.=$a[random_int(0,strlen($a)-1)]; return $c; }
function appt_book(int $acc, ?int $serviceId, string $start, string $name, string $phone, string $email, string $note, string $source, string $address=''): array {
    $s=appt_settings($acc); $tz=appt_tz($s);
    if(!$s['appt_on']) return ['error'=>'appointments_disabled'];
    $name=mb_substr(trim($name),0,120); $phone=mb_substr(trim($phone),0,40); $email=filter_var(trim($email),FILTER_VALIDATE_EMAIL)?trim($email):''; $note=mb_substr(trim($note),0,500);
    if($name==='') return ['error'=>'name_required']; if($phone==='' && $email==='') return ['error'=>'contact_required','hint'=>'Telefonnummer oder E-Mail'];
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T]([01]\d|2[0-3]):([0-5]\d)/',trim($start),$m)) return ['error'=>'start_format','hint'=>'YYYY-MM-DD HH:MM'];
    $date=$m[1].'-'.$m[2].'-'.$m[3]; $time=$m[4].':'.$m[5];
    if(!checkdate((int)$m[2],(int)$m[3],(int)$m[1])) return ['error'=>'date_invalid','date'=>$date,'hint'=>'Dieses Datum gibt es nicht (z. B. 31.11. oder 30.02.) – bitte das Datum mit dem Kunden klären.'];
    [$svc,$dur,$err]=appt_resolve_service($acc,$serviceId,$s,true); if($err) return $err;
    /* Hausbesuch: Adresse prüfen und Anfahrt berechnen (nur „home“ verlangt sie; bei „both“ entscheidet die Adresse) */
    $visit=null; $address=trim($address);
    if($s['visit_mode']==='home' && $address==='') return ['error'=>'address_required','hint'=>'Termine finden beim Kunden statt – bitte Adresse (Straße, Hausnummer, PLZ, Ort) erfragen.'];
    if($address!=='' && $s['visit_mode']!=='none'){ $visit=appt_visit_check($acc,$address,$s); if(empty($visit['ok'])) return $visit+['error'=>$visit['error'] ?? 'address_invalid']; }
    $slots=appt_slots($acc,$date,$svc ? (int)$svc['id'] : null); if(isset($slots['error'])) return $slots;   // gleiche Leistung/Dauer wie beim Speichern
    if(!in_array($time,$slots['slots'] ?? [],true)) return ['error'=>'slot_unavailable','date'=>$date,'available'=>array_slice($slots['slots'] ?? [],0,10),'closed'=>$slots['closed'] ?? false];
    $st=new DateTimeImmutable($date.' '.$time,$tz); $en=$st->modify('+'.$dur.' minutes'); $p=th_db(); $code='';
    $lk=th_lock('appt_book:'.$acc,8);   // Buchungen eines Kontos nacheinander (vermeidet Lücken-Sperren-Deadlocks); ohne Sperre greifen Transaktion + Wiederholung
    try{
        for($try=1;;$try++){
            try{
                $p->beginTransaction();
                if(appt_conflicts($acc,$st,$en,(int)$s['buffer_min'],0,true)>0){ $p->rollBack(); return ['error'=>'slot_unavailable','date'=>$date,'hint'=>'Diese Zeit wurde gerade vergeben.','available'=>array_slice(appt_slots($acc,$date,$svc ? (int)$svc['id'] : null)['slots'] ?? [],0,10)]; }
                $code=appt_code();
                $p->prepare("INSERT INTO appt_bookings(account_id,service_id,service_name,start_at,end_at,customer_name,phone,email,note,status,source,code,visit_address,travel_fee_cents,visit_km) VALUES(?,?,?,?,?,?,?,?,?,'booked',?,?,?,?,?)")
                  ->execute([$acc,$svc['id'] ?? null,$svc['name'] ?? 'Termin',$st->format('Y-m-d H:i:s'),$en->format('Y-m-d H:i:s'),$name,$phone,$email,$note,mb_substr($source,0,16),$code,$visit ? mb_substr($address,0,240) : '',$visit ? (int)$visit['travel_fee_cents'] : 0,$visit['km'] ?? null]);
                $p->commit(); break;
            }catch(Throwable $e){
                try{ if($p->inTransaction()) $p->rollBack(); }catch(Throwable $e2){}
                $st8=$e instanceof PDOException ? (string)($e->errorInfo[0] ?? $e->getCode()) : ''; $drv=$e instanceof PDOException ? (int)($e->errorInfo[1] ?? 0) : 0;
                if(($st8==='40001' || $drv===1213 || ($drv===1062 && str_contains($e->getMessage(),'code'))) && $try<3){ usleep(random_int(20000,90000)*$try); continue; }   // Deadlock / Code-Kollision → bis zu 3 Versuche
                error_log('CH appt_book acc='.$acc.' try='.$try.': '.$e->getMessage());
                return ['error'=>'db','message'=>'Die Buchung konnte gerade nicht gespeichert werden – bitte noch einmal versuchen.','hint'=>'Bitte erneut versuchen.'];
            }
        }
    } finally { if($lk) th_unlock('appt_book:'.$acc); }
    $b=appt_by_code($code); if($acc!==0) appt_notify($b,$s,'confirm');
    return ['ok'=>true,'booking'=>appt_public($b,$s)];
}
function appt_by_code(string $code): ?array { appt_bootstrap(); $code=strtoupper(preg_replace('/[^A-Z0-9]/i','',$code)); if($code==='') return null; $s=th_db()->prepare("SELECT * FROM appt_bookings WHERE code=?"); $s->execute([$code]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function appt_public(array $b, ?array $s=null): array {
    $s=$s ?: appt_settings((int)$b['account_id']); $tz=appt_tz($s); $st=new DateTimeImmutable($b['start_at'],$tz);
    $wd=['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'][(int)$st->format('w')];
    return ['code'=>$b['code'],'service'=>$b['service_name'],'date'=>$st->format('Y-m-d'),'time'=>$st->format('H:i'),'end'=>(new DateTimeImmutable($b['end_at'],$tz))->format('H:i'),'weekday'=>$wd,
        'human'=>$wd.', '.$st->format('d.m.Y').' um '.$st->format('H:i').' Uhr','name'=>$b['customer_name'],'phone'=>$b['phone'],'email'=>$b['email'],'note'=>$b['note'],'status'=>$b['status'],'company'=>$s['company'],'address'=>$s['address'],
        'home_visit'=>trim((string)($b['visit_address'] ?? ''))!=='','visit_address'=>(string)($b['visit_address'] ?? ''),'travel_fee_cents'=>(int)($b['travel_fee_cents'] ?? 0),'visit_km'=>isset($b['visit_km']) ? (float)$b['visit_km'] : null,
        'manage_url'=>'https://chat-help.com/chat/appt.php?c='.$b['code'],'cancellable'=>$b['status']==='booked' && $st > appt_now($s)];
}
/** Absagen per Buchungscode (öffentlich, Agent, Studio). Nur Status 'booked' mit Beginn in der Zukunft (Wanduhr der Konto-Zeitzone);
 *  $force (Betreiber) erlaubt auch bereits begonnene/vergangene gebuchte Termine. Fehler: not_found | not_cancellable | too_late (+ deutscher hint). */
function appt_cancel(string $code, int $acc=-1, bool $force=false): array {
    $b=appt_by_code($code); if(!$b || ($acc>=0 && (int)$b['account_id']!==$acc)) return ['error'=>'not_found','hint'=>'Kein Termin mit diesem Buchungscode gefunden.','message'=>'Kein Termin mit diesem Buchungscode gefunden.'];
    $s=appt_settings((int)$b['account_id']); $tz=appt_tz($s); $now=appt_now($s);
    if($b['status']==='cancelled') return ['ok'=>true,'already'=>true,'booking'=>appt_public($b,$s)];
    $bad=fn($b)=>['error'=>'not_cancellable','status'=>$b['status'],'hint'=>$h=('Dieser Termin ist bereits abgeschlossen ('.(['done'=>'wahrgenommen','noshow'=>'als nicht erschienen vermerkt'][$b['status']] ?? $b['status']).') und kann nicht mehr abgesagt werden.'),'message'=>$h,'booking'=>appt_public($b,$s)];
    $late=fn($b)=>['error'=>'too_late','hint'=>$h='Der Termin hat bereits begonnen oder liegt in der Vergangenheit – eine Absage ist nicht mehr möglich. Bitte wenden Sie sich direkt an den Betrieb.','message'=>$h,'booking'=>appt_public($b,$s)];
    if($b['status']!=='booked') return $bad($b);
    if(!$force && new DateTimeImmutable($b['start_at'],$tz) <= $now) return $late($b);
    $u=th_db()->prepare("UPDATE appt_bookings SET status='cancelled' WHERE id=? AND status='booked'".($force?'':' AND start_at > ?')); $u->execute($force?[$b['id']]:[$b['id'],$now->format('Y-m-d H:i:s')]);
    if($u->rowCount()!==1){ $b=appt_by_code($code) ?: $b; if($b['status']==='cancelled') return ['ok'=>true,'already'=>true,'booking'=>appt_public($b,$s)]; return $b['status']==='booked' ? $late($b) : $bad($b); }   // paralleler Statuswechsel
    $b['status']='cancelled'; if((int)$b['account_id']!==0) appt_notify($b,$s,'cancel');
    return ['ok'=>true,'booking'=>appt_public($b,$s)];
}
function appt_find_by_phone(int $acc, string $phone): array {
    $d=preg_replace('/\D/','',$phone); if(strlen($d)<6) return [];
    $set=appt_settings($acc); $now=appt_now($set)->format('Y-m-d H:i:s');   // Wanduhr der Konto-Zeitzone, nicht MySQL NOW()
    $s=th_db()->prepare("SELECT * FROM appt_bookings WHERE account_id=? AND status='booked' AND start_at > ? AND REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-','') LIKE ? ORDER BY start_at LIMIT 5"); $s->execute([$acc,$now,'%'.substr($d,-8)]);
    return array_map(fn($b)=>appt_public($b,$set),$s->fetchAll(PDO::FETCH_ASSOC));
}
function appt_bookings(int $acc, string $from, string $to): array {
    appt_bootstrap(); $s=th_db()->prepare("SELECT * FROM appt_bookings WHERE account_id=? AND start_at >= ? AND start_at < ? ORDER BY start_at"); $s->execute([$acc,$from.' 00:00:00',$to.' 23:59:59']);
    $set=appt_settings($acc); return array_map(fn($b)=>appt_public($b,$set)+['id'=>(int)$b['id'],'source'=>$b['source'],'created_at'=>$b['created_at']],$s->fetchAll(PDO::FETCH_ASSOC));
}
/** Status setzen (Studio) → ['ok'=>true] | ['error'=>'invalid_status'|'not_found'|'slot_conflict'|'db','hint'=>…].
 *  Von cancelled/noshow zurück auf einen aktiven Status (booked/done, beide belegen den Zeitraum) nur, wenn der Zeitraum (inkl. Puffer/Sperrzeiten) frei ist –
 *  geprüft solange der Termin noch nicht vorbei ist (vergangene Termine beeinflussen keine freien Zeiten mehr und dürfen nachträglich korrigiert werden). */
function appt_status_update(int $acc, int $id, string $status): array {
    $e=fn(string $c,string $h)=>['error'=>$c,'hint'=>$h,'message'=>$h];
    if(!in_array($status,['booked','done','noshow','cancelled'],true)) return $e('invalid_status','Ungültiger Status – erlaubt: booked, done, noshow, cancelled.');
    appt_bootstrap(); $p=th_db(); $s=appt_settings($acc); $tz=appt_tz($s); $lk=th_lock('appt_book:'.$acc,8);
    try{
        $p->beginTransaction();
        $q=$p->prepare("SELECT * FROM appt_bookings WHERE id=? AND account_id=? FOR UPDATE"); $q->execute([$id,$acc]); $b=$q->fetch(PDO::FETCH_ASSOC);
        if(!$b){ $p->rollBack(); return $e('not_found','Termin nicht gefunden.'); }
        if($b['status']===$status){ $p->commit(); return ['ok'=>true,'unchanged'=>true]; }
        $act=['booked','done']; if(in_array($status,$act,true) && !in_array($b['status'],$act,true) && new DateTimeImmutable($b['end_at'],$tz) > appt_now($s) && appt_conflicts($acc,new DateTimeImmutable($b['start_at'],$tz),new DateTimeImmutable($b['end_at'],$tz),(int)$s['buffer_min'],(int)$b['id'],true)>0){
            $p->rollBack(); return $e('slot_conflict','Der Zeitraum ist inzwischen anderweitig belegt (inkl. Puffer/Sperrzeit) – der Termin kann nicht wieder aktiviert werden.'); }
        $p->prepare("UPDATE appt_bookings SET status=? WHERE id=? AND account_id=?")->execute([$status,$id,$acc]); $p->commit();
        /* Zweiseitig: Betrieb sagt im Studio ab → Kunde erfährt es auf seinem Kanal (WhatsApp im 24-h-Fenster, SMS, sonst E-Mail) */
        if($status==='cancelled' && $b['status']==='booked' && $acc>0 && new DateTimeImmutable($b['start_at'],$tz) > appt_now($s)){ $pub=appt_public($b,$s);
            appt_contact_notify($b,($s['company'] ?: 'Ihr Termin').': Ihr Termin '.$pub['service'].' am '.$pub['human'].' wurde leider abgesagt. Gern vereinbaren wir einen neuen Termin – melden Sie sich einfach bei uns.','appt_cancelled',true); }
        return ['ok'=>true];
    }catch(Throwable $x){ try{ if($p->inTransaction()) $p->rollBack(); }catch(Throwable $x2){} error_log('CH appt_status acc='.$acc.': '.$x->getMessage()); return $e('db','Status konnte gerade nicht gespeichert werden – bitte erneut versuchen.'); }
    finally{ if($lk) th_unlock('appt_book:'.$acc); }
}
/** Kompatibel: true nur bei Erfolg; false bei ungültigem Status, unbekannter ID (not_found), Überschneidung oder DB-Fehler – Details in $res (beendet nie selbst die Anfrage). */
function appt_set_status(int $acc, int $id, string $status, &$res=null): bool {
    $res=appt_status_update($acc,$id,$status); return !empty($res['ok']);
}
function appt_ics(array $b, array $s): string {
    $tz=appt_tz($s); $st=(new DateTimeImmutable($b['start_at'],$tz))->setTimezone(new DateTimeZone('UTC')); $en=(new DateTimeImmutable($b['end_at'],$tz))->setTimezone(new DateTimeZone('UTC'));
    $esc=fn($t)=>str_replace([',',';',"\n"],['\,','\;','\n'],(string)$t);
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ChatHelp//Termine//DE\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:".$b['code']."@chat-help.com\r\nDTSTAMP:".gmdate('Ymd\THis\Z')."\r\nDTSTART:".$st->format('Ymd\THis\Z')."\r\nDTEND:".$en->format('Ymd\THis\Z')."\r\nSUMMARY:".$esc($b['service_name'].' – '.$s['company'])."\r\nLOCATION:".$esc($s['address'])."\r\nDESCRIPTION:".$esc('Buchungscode '.$b['code'].' · Verwalten: https://chat-help.com/chat/appt.php?c='.$b['code'])."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}
/* ---------- E-Mails ---------- */
/** Rückgabe: true, wenn alle fälligen E-Mails angenommen wurden (für die Erinnerungs-Wiederholung). */
function appt_notify(array $b, array $s, string $kind): bool {
    $pub=appt_public($b,$s); $company=$s['company'] ?: 'Ihr Termin'; $ok=true;
    if(function_exists('ch_push') && in_array($kind,['confirm','cancel'],true)) ch_push((int)$b['account_id'],$kind==='confirm'?'booking_new':'booking_cancel',($kind==='confirm'?'Neuer Termin: ':'Termin abgesagt: ').$pub['service'],$pub['human'].' · '.$b['customer_name'].($b['phone']?' · '.$b['phone']:''),'/chat/studio.php#termine',['tag'=>'appt-'.$b['code'],'email'=>false]);
    $box=function(string $title,string $body) use($pub,$s){ return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:30px;border:1px solid #e6e8ef;border-radius:20px;color:#0f1222"><div style="font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:#b8862b;font-weight:700">'.htmlspecialchars($s['company']).'</div><h2 style="margin:10px 0 14px;font-size:22px">'.$title.'</h2>'.$body
        .'<div style="margin:18px 0;padding:16px;border-radius:14px;background:#f6ecd3"><b style="font-size:17px">'.htmlspecialchars($pub['service']).'</b><br>'.htmlspecialchars($pub['human']).' (bis '.htmlspecialchars($pub['end']).' Uhr)'.($pub['home_visit'] ? '<br>Hausbesuch: '.htmlspecialchars($pub['visit_address']).($pub['travel_fee_cents']>0 ? '<br>Anfahrt: '.number_format($pub['travel_fee_cents']/100,2,',','.').' €' : '') : ($s['address']?'<br>'.htmlspecialchars($s['address']):'')).'<br><span style="color:#6b7083;font-size:13px">Buchungscode '.$pub['code'].'</span></div>'
        .'<p style="font-size:13px;color:#6b7083">Termin verwalten / absagen / in den Kalender: <a href="'.$pub['manage_url'].'" style="color:#b8862b">'.$pub['manage_url'].'</a></p></div>'; };
    if($kind==='confirm'){
        if($b['email']) $ok=th_mail($b['email'],'Terminbestätigung · '.$company.' · '.$pub['human'],$box('Ihr Termin ist bestätigt ✓','<p>Hallo '.htmlspecialchars($b['customer_name']).',</p><p>vielen Dank für Ihre Buchung.'.($s['confirm_text']?' '.htmlspecialchars($s['confirm_text']):'').'</p>')) && $ok;
        $to=$s['notify_email'] ?: appt_owner_email((int)$b['account_id']); if($to) $ok=th_mail($to,'Neuer Termin: '.$pub['service'].' · '.$pub['human'],$box('Neue Buchung','<p><b>'.htmlspecialchars($b['customer_name']).'</b> · '.htmlspecialchars($b['phone']).' · '.htmlspecialchars($b['email']).'<br>Quelle: '.htmlspecialchars($b['source']).($b['note']?'<br>Notiz: '.htmlspecialchars($b['note']):'').'</p>')) && $ok;
        /* Zweiseitig: am Telefon gebucht (oder vom Betrieb im Studio eingetragen) → Bestätigung mit Code + Verwaltungslink per WhatsApp (24-h-Fenster) bzw. SMS. WhatsApp/Chat bestätigt der Agent selbst; E-Mail ist oben schon raus. */
        if(in_array($b['source'],['phone','studio'],true) && (string)$b['phone']!=='' && (int)$b['account_id']>0) appt_contact_notify($b,$company.': Ihr Termin ist bestätigt – '.$pub['service'].', '.$pub['human'].' (Code '.$pub['code'].'). Verwalten oder absagen: '.$pub['manage_url'],'appt_confirmed',false);
    } elseif($kind==='cancel'){
        if($b['email']) $ok=th_mail($b['email'],'Termin abgesagt · '.$company,$box('Ihr Termin wurde abgesagt','<p>Hallo '.htmlspecialchars($b['customer_name']).', Ihr Termin wurde storniert. Gern vereinbaren Sie einen neuen Termin.</p>')) && $ok;
        $to=$s['notify_email'] ?: appt_owner_email((int)$b['account_id']); if($to) $ok=th_mail($to,'Termin abgesagt: '.$pub['service'].' · '.$pub['human'],$box('Absage','<p>'.htmlspecialchars($b['customer_name']).' · '.htmlspecialchars($b['phone']).'</p>')) && $ok;
    } elseif($kind==='remind'){
        $tz=appt_tz($s); $when=$pub['date']===(new DateTimeImmutable('today',$tz))->format('Y-m-d') ? 'heute' : ($pub['date']===(new DateTimeImmutable('tomorrow',$tz))->format('Y-m-d') ? 'morgen' : 'am '.$pub['weekday']);   // Wanduhr der Konto-Zeitzone
        if($b['email']) $ok=th_mail($b['email'],'Erinnerung: '.$when.' '.$pub['time'].' Uhr · '.$company,$box('Erinnerung an Ihren Termin','<p>Hallo '.htmlspecialchars($b['customer_name']).', wir freuen uns auf Sie '.$when.'.</p>'));
    }
    return $ok;
}
/** Kunde über contacts_notify (Kanalwahl, Kundenverlauf) informieren. WhatsApp/SMS nur bei verifizierter Nummer (Telefon/WhatsApp) oder Eintrag des Betriebs; $withEmail = E-Mail als letzter Weg. */
function appt_contact_notify(array $b, string $text, string $event, bool $withEmail): void {
    if(!function_exists('contacts_notify') && is_file(__DIR__.'/contacts-lib.php')) require_once __DIR__.'/contacts-lib.php';
    if(!function_exists('contacts_notify') || (int)$b['account_id']<=0) return;
    try{ contacts_notify((int)$b['account_id'],(string)$b['phone'],$text,$event,['verified'=>in_array($b['source'],['phone','whatsapp','studio'],true),'email'=>$withEmail ? (string)$b['email'] : '','ref'=>(string)$b['code'],
        'subject'=>'Ihr Termin · '.($event==='appt_cancelled' ? 'Absage' : 'Bestätigung')]); }catch(Throwable $e){ error_log('CH appt_contact_notify: '.$e->getMessage()); }
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
/** Erinnerungen 20–28 h vorher (Wanduhr der jeweiligen Konto-Zeitzone) – höchstens alle 10 Minuten (GET_LOCK + Zeitstempel in appt_jobs, kein Cron nötig;
 *  Aufrufer: Studio-Terminliste, Buchungsseite info/slots/days, Heartbeat). Jede Zeile wird atomar beansprucht (reminded 0→2, nur bei rowCount 1 senden, dann 1;
 *  Versandfehler → wieder 0). $force überspringt nur die 10-Minuten-Sperre. Rückgabe: Anzahl versendeter Erinnerungen. */
function appt_reminders_lazy(bool $force=false): int {
    if(!th_lock('appt_reminders',0)) return 0;   // läuft gerade woanders
    $n=0;
    try{
        appt_bootstrap(); $p=th_db();
        $g=$p->query("SELECT TIMESTAMPDIFF(SECOND,last_run,NOW()) FROM appt_jobs WHERE name='reminders'")->fetchColumn();
        if(!$force && $g!==false && $g!==null && (int)$g>=0 && (int)$g<600) return 0;
        $p->exec("INSERT INTO appt_jobs(name,last_run) VALUES('reminders',NOW()) ON DUPLICATE KEY UPDATE last_run=NOW()");
        /* start_at ist Wanduhr der Konto-Zeitzone (UTC−12 … UTC+14): großzügiges Fenster um UTC holen, exakt je Konto in PHP filtern */
        $utc=new DateTimeImmutable('now',new DateTimeZone('UTC'));
        $q=$p->prepare("SELECT * FROM appt_bookings WHERE status='booked' AND reminded=0 AND account_id>0 AND email<>'' AND start_at BETWEEN ? AND ? ORDER BY start_at LIMIT 300");
        $q->execute([$utc->modify('+7 hours')->format('Y-m-d H:i:s'),$utc->modify('+43 hours')->format('Y-m-d H:i:s')]);
        $sets=[]; $claim=$p->prepare("UPDATE appt_bookings SET reminded=2 WHERE id=? AND reminded=0 AND status='booked'"); $fin=$p->prepare("UPDATE appt_bookings SET reminded=? WHERE id=? AND reminded=2");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $b){
            $a=(int)$b['account_id']; $s=$sets[$a] ??= appt_settings($a); $tz=appt_tz($s); $now=new DateTimeImmutable('now',$tz); $st=new DateTimeImmutable($b['start_at'],$tz);
            if($st < $now->modify('+20 hours') || $st > $now->modify('+28 hours')) continue;
            $claim->execute([$b['id']]); if($claim->rowCount()!==1) continue;   // schon von einem anderen Lauf beansprucht
            $ok=false; try{ $ok=appt_notify($b,$s,'remind'); }catch(Throwable $e){ error_log('CH appt remind #'.$b['id'].': '.$e->getMessage()); }
            $fin->execute([$ok?1:0,$b['id']]); if($ok) $n++;
        }
    } finally { th_unlock('appt_reminders'); }
    return $n;
}

/* ---------- Werkzeuge für den Agenten ---------- */
function appt_tools(): array {
    return [
      ['name'=>'list_services','description'=>'Liste der buchbaren Leistungen mit Dauer und Preis.','input_schema'=>['type'=>'object','properties'=>new stdClass(),'required'=>[]]],
      ['name'=>'get_available_slots','description'=>'Freie Terminzeiten für ein Datum (YYYY-MM-DD) und optional eine Leistung (service_id). Ohne Datum: die nächsten Tage mit freien Zeiten.','input_schema'=>['type'=>'object','properties'=>['date'=>['type'=>'string','description'=>'YYYY-MM-DD'],'service_id'=>['type'=>'integer']],'required'=>[]]],
      ['name'=>'book_appointment','description'=>'Termin verbindlich buchen. Vorher Leistung, Datum, Uhrzeit, Name und Telefonnummer (oder E-Mail) vom Kunden bestätigen lassen. Hausbesuch: address angeben (Anfahrtskosten werden berechnet).','input_schema'=>['type'=>'object','properties'=>['service_id'=>['type'=>'integer'],'start'=>['type'=>'string','description'=>'YYYY-MM-DD HH:MM'],'name'=>['type'=>'string'],'phone'=>['type'=>'string'],'email'=>['type'=>'string'],'note'=>['type'=>'string'],'address'=>['type'=>'string','description'=>'Nur bei Hausbesuch: Straße Hausnummer, PLZ Ort des Kunden']],'required'=>['start','name']]],
      ['name'=>'check_home_visit','description'=>'Hausbesuch prüfen: Entfernung zur Kundenadresse, Anfahrtskosten und ob die Adresse im Einsatzradius liegt. Vor einer Hausbesuch-Buchung aufrufen und die Anfahrtskosten nennen.','input_schema'=>['type'=>'object','properties'=>['address'=>['type'=>'string','description'=>'Straße Hausnummer, PLZ Ort']],'required'=>['address']]],
      ['name'=>'find_appointments','description'=>'Bestehende Termine finden: per Buchungscode, sonst nur für die verifizierte Absender-/Anrufernummer (WhatsApp/Telefon) – fremde Nummern sind gesperrt.','input_schema'=>['type'=>'object','properties'=>['phone'=>['type'=>'string'],'code'=>['type'=>'string']],'required'=>[]]],
      ['name'=>'cancel_appointment','description'=>'Termin per Buchungscode absagen (nur nach ausdrücklicher Bestätigung des Kunden).','input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string']],'required'=>['code']]],
    ];
}
function appt_exec(int $acc, string $name, array $in, string $source, string $from=''): array {
    switch($name){
        case 'list_services': return ['services'=>array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['name'],'duration_min'=>$s['duration_min'],'price_eur'=>$s['price_cents']?round($s['price_cents']/100,2):null],appt_services($acc))];
        case 'get_available_slots': $sid=isset($in['service_id'])?(int)$in['service_id']:null; if(!empty($in['date'])) return appt_slots($acc,(string)$in['date'],$sid);
            [, , $err]=appt_resolve_service($acc,$sid); if($err) return $err; return ['next_days'=>appt_next_days($acc,$sid,10)];
        case 'book_appointment': return appt_book($acc,isset($in['service_id'])?(int)$in['service_id']:null,(string)($in['start']??''),(string)($in['name']??''),(string)($in['phone']??''),(string)($in['email']??''),(string)($in['note']??''),$source,is_scalar($in['address'] ?? null) ? (string)$in['address'] : '');
        case 'check_home_visit': return appt_visit_check($acc,is_scalar($in['address'] ?? null) ? (string)$in['address'] : '');
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
    $wd=['So','Mo','Di','Mi','Do','Fr','Sa']; $h=[]; for($w=1;$w<=7;$w++){ $k=(string)($w%7); $r=$s['hours'][$k] ?? []; $h[]=$wd[$w%7].': '.($r?implode(', ',array_map(fn($x)=>$x[0].'–'.$x[1].($x[1]!=='00:00' && $x[1]!=='24:00' && $x[1]<=$x[0] ? ' (Folgetag)' : ''),$r)):'geschlossen'); }
    $svc=implode('; ',array_map(fn($x)=>"#{$x['id']} {$x['name']} ({$x['duration_min']} Min".($x['price_cents']?', '.number_format($x['price_cents']/100,2,',','.').' €':'').")",appt_services($acc)));
    return "\n\nTERMINBUCHUNG (aktiv): Heute ist ".$now->format('l, d.m.Y H:i')." (".$s['tz']."). Öffnungszeiten: ".implode(' · ',$h).". Leistungen: ".($svc?:'keine hinterlegt – Dauer '.$s['slot_min'].' Min').".".($s['visit_mode']!=='none' ? ' '.appt_visit_text($s).' – bei Hausbesuch Adresse erfragen, check_home_visit aufrufen, Anfahrtskosten in der Zusammenfassung nennen und address an book_appointment übergeben.' : '')." Vorlauf mind. ".$s['lead_min']." Min, buchbar bis ".$s['max_days']." Tage im Voraus.
REGELN: Freie Zeiten NUR über das Werkzeug get_available_slots nennen (niemals raten). Zum Buchen brauchst du Leistung, Datum, Uhrzeit, Name und Telefonnummer (oder E-Mail) – fasse alles in einem Satz zusammen und frage „Soll ich das so buchen?“; erst nach dem Ja book_appointment aufrufen. Nach erfolgreicher Buchung: Datum, Uhrzeit, Leistung und Buchungscode nennen und sagen, dass eine Bestätigung per E-Mail kommt (falls E-Mail angegeben). Bestehende Termine nur per Buchungscode (oder automatisch über die Nummer, von der der Kunde per WhatsApp schreibt bzw. anruft) – niemals Termine zu einer genannten fremden Nummer herausgeben. Absagen nur nach ausdrücklicher Bestätigung. Relative Angaben („morgen“, „nächsten Dienstag“) immer auf ein konkretes Datum umrechnen und nennen.";
}
