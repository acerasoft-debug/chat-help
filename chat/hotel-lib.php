<?php
/**
 * ChatHelp — Hotel-Rezeptions-Agent für Hotels, Pensionen, Boardinghouses und Ferienwohnungen
 * ============================================================================================
 * • Einstellungen je Konto: Stammdaten, Check-in/-out, Sofortbuchung oder Anfrage, Frühstück, City-Tax, Mindest-/Höchstaufenthalt,
 *   Stornofrist, Hausregeln; Zimmerkategorien (Kapazität, Anzahl, Preis/Nacht, Wochenendpreis Fr+Sa, Ausstattung)
 * • Verfügbarkeit je Nacht (offene Anfragen blockieren mit → keine Überbuchung), Preis mit Aufschlüsselung, Frühstück und City-Tax
 * • Buchung in einer Transaktion (Sperre + FOR UPDATE), Buchungscode und Gästeseite stay.php (Status, Storno nach Frist, Kalender-ICS)
 * • Rezeption: bestätigen/ablehnen, Check-in mit Zimmernummer, Check-out, Storno, No-Show – im Studio und auf dem Board (hotel-board.php)
 * • Gästewünsche aus dem Zimmer (Housekeeping, Weckruf, Taxi, Late Check-out …) live auf dem Board; Taxi über das Taxi-Modul, falls aktiv
 * • Werkzeuge für den Agenten (Chat, Widget, Telefon, WhatsApp) + System-Hinweis mit Regeln, REST-Routen /v1/hotel/* für das Studio
 * • Konto 0 = öffentliche Demo „Hotel Alster Residenz · Hamburg“ (keine Benachrichtigungen, Demo-Daten nach 2 Tagen gelöscht)
 */
require_once __DIR__.'/tokens-lib.php';

const HOTEL_STATUS_LABELS = ['requested'=>'Anfrage eingegangen','confirmed'=>'Bestätigt','declined'=>'Abgelehnt','cancelled'=>'Storniert','checked_in'=>'Eingecheckt','checked_out'=>'Abgereist','no_show'=>'Nicht angereist'];
const HOTEL_BLOCKING = ['requested','confirmed','checked_in'];
const HOTEL_KINDS = ['housekeeping'=>'Housekeeping','amenities'=>'Ausstattung & Extras','roomservice'=>'Room-Service','wakeup'=>'Weckruf','maintenance'=>'Technik / Reparatur','taxi'=>'Taxi','late_checkout'=>'Late Check-out','info'=>'Auskunft','other'=>'Sonstiges / Beschwerde'];
const HOTEL_REQ_LABELS = ['open'=>'Offen','in_progress'=>'In Arbeit','done'=>'Erledigt','cancelled'=>'Storniert'];
const HOTEL_HORIZON_DAYS = 730;
const HOTEL_WD = ['So','Mo','Di','Mi','Do','Fr','Sa'];
const HOTEL_WDL = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
const HOTEL_MON = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
const HOTEL_DEMO = [
  'name'=>'Hotel Alster Residenz · Hamburg','address'=>'An der Alster 40, 20099 Hamburg','phone'=>'040 3000000','tz'=>'Europe/Berlin',
  'checkin_from'=>'15:00','checkout_until'=>'11:00','auto_confirm'=>1,'breakfast_mode'=>'optional','breakfast_cents'=>1800,'city_tax_pct'=>0.0,
  'min_nights'=>1,'max_nights'=>30,'cancel_hours'=>48,
  'policies'=>"Hunde willkommen: 15 € pro Nacht (Decke und Napf inklusive).\nTiefgarage: 22 € pro Tag, mit E-Ladesäule.\nNichtraucherhotel – Rauchen nur auf der Terrasse.\nLate Check-out bis 14:00 Uhr für 30 € nach Verfügbarkeit.\nFrühstücksbuffet 6:30–10:30 Uhr (Wochenende bis 11:30 Uhr), WLAN im ganzen Haus kostenlos.",
  'rooms'=>[
    ['id'=>1,'name'=>'Einzelzimmer Komfort','description'=>'Ruhig zum Innenhof, 18 m², Boxspringbett 1,40 m','capacity'=>1,'units'=>4,'price_cents'=>10900,'weekend_cents'=>11900,'amenities'=>'WLAN, Smart-TV, Schreibtisch, Regendusche','active'=>1,'sort'=>1],
    ['id'=>2,'name'=>'Doppelzimmer Alsterblick','description'=>'24 m² mit Blick auf die Außenalster, Kingsize-Bett 1,80 m','capacity'=>2,'units'=>6,'price_cents'=>15900,'weekend_cents'=>17900,'amenities'=>'WLAN, Alsterblick, Nespresso, Minibar, Regendusche','active'=>1,'sort'=>2],
    ['id'=>3,'name'=>'Junior Suite','description'=>'38 m² mit Sitzecke und Schlafsofa, Alsterblick','capacity'=>3,'units'=>2,'price_cents'=>23900,'weekend_cents'=>25900,'amenities'=>'WLAN, Alsterblick, Badewanne, Nespresso, Bademäntel','active'=>1,'sort'=>3],
    ['id'=>4,'name'=>'Familienzimmer','description'=>'32 m², Doppelbett und zwei Einzelbetten, Babybett auf Wunsch','capacity'=>4,'units'=>2,'price_cents'=>19900,'weekend_cents'=>21900,'amenities'=>'WLAN, Smart-TV, Kühlschrank, Babybett auf Anfrage','active'=>1,'sort'=>4]]];

/* ======================================================================= Tabellen */
function hotel_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS hotel_settings (
        account_id INT NOT NULL PRIMARY KEY, hotel_on TINYINT(1) DEFAULT 0, name VARCHAR(120) DEFAULT '', address VARCHAR(200) DEFAULT '', phone VARCHAR(40) DEFAULT '',
        notify_email VARCHAR(190) DEFAULT '', tz VARCHAR(40) DEFAULT 'Europe/Berlin', checkin_from CHAR(5) DEFAULT '15:00', checkout_until CHAR(5) DEFAULT '11:00',
        auto_confirm TINYINT(1) DEFAULT 0, breakfast_mode VARCHAR(10) DEFAULT 'none', breakfast_cents INT DEFAULT 0, city_tax_pct DECIMAL(4,2) DEFAULT 0,
        min_nights INT DEFAULT 1, max_nights INT DEFAULT 30, cancel_hours INT DEFAULT 48, policies TEXT NULL, board_token CHAR(24) NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_board (board_token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS hotel_rooms (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, name VARCHAR(120) NOT NULL, description VARCHAR(500) DEFAULT '', capacity TINYINT DEFAULT 2,
        units INT DEFAULT 1, price_cents INT DEFAULT 0, weekend_cents INT NULL, amenities VARCHAR(300) DEFAULT '', active TINYINT(1) DEFAULT 1, sort INT DEFAULT 0,
        INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS hotel_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, code CHAR(8) NOT NULL UNIQUE, room_id INT NOT NULL, room_name VARCHAR(120) DEFAULT '',
        checkin DATE NOT NULL, checkout DATE NOT NULL, nights INT DEFAULT 1, guests TINYINT DEFAULT 1, guest_name VARCHAR(120) DEFAULT '', phone VARCHAR(40) DEFAULT '',
        email VARCHAR(190) DEFAULT '', breakfast TINYINT(1) DEFAULT 0, total_cents INT DEFAULT 0, status VARCHAR(16) DEFAULT 'requested', room_no VARCHAR(20) DEFAULT '',
        channel VARCHAR(12) DEFAULT 'chat', note VARCHAR(500) DEFAULT '', price_json TEXT NULL, wa_from VARCHAR(40) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
        INDEX(account_id, checkin), INDEX(account_id, status), INDEX(account_id, room_id, checkin)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS hotel_requests (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, room_no VARCHAR(20) DEFAULT '', guest_name VARCHAR(120) DEFAULT '', kind VARCHAR(20) DEFAULT 'other',
        details VARCHAR(500) DEFAULT '', due_at DATETIME NULL, status VARCHAR(16) DEFAULT 'open', channel VARCHAR(12) DEFAULT 'chat', contact VARCHAR(40) DEFAULT '',
        ref VARCHAR(40) DEFAULT '', booking_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
        INDEX(account_id, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* Spalten nachrüsten (frühe Tabellenstände) */
    foreach(['hotel_bookings'=>['price_json'=>'TEXT NULL','wa_from'=>"VARCHAR(40) DEFAULT ''"],'hotel_requests'=>['ref'=>"VARCHAR(40) DEFAULT ''",'booking_id'=>'INT NULL']] as $tbl=>$cols){
        $have=$p->query("SHOW COLUMNS FROM $tbl")->fetchAll(PDO::FETCH_COLUMN);
        foreach($cols as $c=>$def) if(!in_array($c,$have,true)) $p->exec("ALTER TABLE $tbl ADD COLUMN $c $def");
    }
    if(random_int(1,40)===1){
        $p->exec("DELETE FROM hotel_bookings WHERE account_id=0 AND created_at < (NOW() - INTERVAL 2 DAY)");
        $p->exec("DELETE FROM hotel_requests WHERE account_id=0 AND created_at < (NOW() - INTERVAL 2 DAY)");
    }
}

/* ======================================================================= Einstellungen */
function hotel_tz(array $s): DateTimeZone { try{ return new DateTimeZone((string)($s['tz'] ?? '') ?: 'Europe/Berlin'); }catch(Throwable $e){ return new DateTimeZone('Europe/Berlin'); } }
function hotel_now(array $s): DateTimeImmutable { return new DateTimeImmutable('now',hotel_tz($s)); }
function hotel_today(array $s): string { return hotel_now($s)->format('Y-m-d'); }
function hotel_settings(int $acc): array {
    hotel_bootstrap();
    if($acc===0){ $r=HOTEL_DEMO; unset($r['rooms']); $r+=['account_id'=>0,'hotel_on'=>1,'notify_email'=>'','board_token'=>'demo','updated_at'=>null]; }
    else {
        $st=th_db()->prepare("SELECT * FROM hotel_settings WHERE account_id=?"); $st->execute([$acc]); $r=$st->fetch(PDO::FETCH_ASSOC);
        if(!$r) $r=['account_id'=>$acc,'hotel_on'=>0,'name'=>'','address'=>'','phone'=>'','notify_email'=>'','tz'=>'Europe/Berlin','checkin_from'=>'15:00','checkout_until'=>'11:00',
            'auto_confirm'=>0,'breakfast_mode'=>'none','breakfast_cents'=>0,'city_tax_pct'=>0,'min_nights'=>1,'max_nights'=>30,'cancel_hours'=>48,'policies'=>'','board_token'=>null,'updated_at'=>null];
    }
    foreach(['account_id','hotel_on','auto_confirm','breakfast_cents','min_nights','max_nights','cancel_hours'] as $k) $r[$k]=(int)($r[$k] ?? 0);
    $r['city_tax_pct']=round((float)($r['city_tax_pct'] ?? 0),2); $r['policies']=(string)($r['policies'] ?? '');
    if(!in_array($r['breakfast_mode'] ?? '',['none','included','optional'],true)) $r['breakfast_mode']='none';
    foreach(['name','address','phone','notify_email','tz','checkin_from','checkout_until'] as $k) $r[$k]=(string)($r[$k] ?? '');
    if($r['tz']==='') $r['tz']='Europe/Berlin';
    $r['min_nights']=max(1,$r['min_nights']); $r['max_nights']=max($r['min_nights'],$r['max_nights'] ?: 30);
    return $r;
}
/** Speichert die Einstellungen (+ optional rooms[] → hotel_rooms_save; new_board_token=1 erzeugt einen neuen Board-Link). Konto 0 ist schreibgeschützt. */
function hotel_settings_save(int $acc, array $d): array {
    hotel_bootstrap(); if($acc<=0) return hotel_settings($acc); $c=hotel_settings($acc); $d=hotel_scalars($d,['rooms']);
    $time=fn($v,$def)=>preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',trim((string)$v)) ? trim((string)$v) : $def;
    $cents=function($d,string $k,string $alt,int $def): int { if(isset($d[$k]) && is_numeric($d[$k])) return max(0,min(10000000,(int)$d[$k])); if(isset($d[$alt]) && trim((string)$d[$alt])!=='') return max(0,min(10000000,hotel_cents_in($d[$alt]))); return $def; };
    $mode=in_array($d['breakfast_mode'] ?? '',['none','included','optional'],true) ? $d['breakfast_mode'] : $c['breakfast_mode'];
    $tax=array_key_exists('city_tax_pct',$d) ? (float)str_replace(',','.',(string)$d['city_tax_pct']) : $c['city_tax_pct'];
    $min=max(1,min(60,(int)($d['min_nights'] ?? $c['min_nights'])));
    $mail=trim((string)($d['notify_email'] ?? $c['notify_email']));
    $token=$c['board_token']; if(!$token || !preg_match('/^[a-f0-9]{24}$/',$token) || !empty($d['new_board_token'])) $token=bin2hex(random_bytes(12));
    $v=['hotel_on'=>(int)!empty($d['hotel_on'] ?? $c['hotel_on']),'name'=>mb_substr(trim((string)($d['name'] ?? $c['name'])),0,120),'address'=>mb_substr(trim((string)($d['address'] ?? $c['address'])),0,200),
        'phone'=>mb_substr(trim(preg_replace('/[^0-9+ ()\/-]/','',(string)($d['phone'] ?? $c['phone']))),0,40),'notify_email'=>filter_var($mail,FILTER_VALIDATE_EMAIL) ? $mail : '',
        'tz'=>in_array($d['tz'] ?? '',timezone_identifiers_list(),true) ? $d['tz'] : $c['tz'],'checkin_from'=>$time($d['checkin_from'] ?? '',$c['checkin_from'] ?: '15:00'),'checkout_until'=>$time($d['checkout_until'] ?? '',$c['checkout_until'] ?: '11:00'),
        'auto_confirm'=>(int)!empty($d['auto_confirm'] ?? $c['auto_confirm']),'breakfast_mode'=>$mode,'breakfast_cents'=>$cents($d,'breakfast_cents','breakfast',$c['breakfast_cents']),
        'city_tax_pct'=>max(0,min(25,round($tax,2))),'min_nights'=>$min,'max_nights'=>max($min,min(365,(int)($d['max_nights'] ?? $c['max_nights']))),'cancel_hours'=>max(0,min(2160,(int)($d['cancel_hours'] ?? $c['cancel_hours']))),
        'policies'=>mb_substr(trim(str_replace("\r",'',(string)($d['policies'] ?? $c['policies']))),0,4000),'board_token'=>$token];
    th_db()->prepare("INSERT INTO hotel_settings(account_id,hotel_on,name,address,phone,notify_email,tz,checkin_from,checkout_until,auto_confirm,breakfast_mode,breakfast_cents,city_tax_pct,min_nights,max_nights,cancel_hours,policies,board_token)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE hotel_on=VALUES(hotel_on),name=VALUES(name),address=VALUES(address),phone=VALUES(phone),notify_email=VALUES(notify_email),tz=VALUES(tz),
        checkin_from=VALUES(checkin_from),checkout_until=VALUES(checkout_until),auto_confirm=VALUES(auto_confirm),breakfast_mode=VALUES(breakfast_mode),breakfast_cents=VALUES(breakfast_cents),city_tax_pct=VALUES(city_tax_pct),
        min_nights=VALUES(min_nights),max_nights=VALUES(max_nights),cancel_hours=VALUES(cancel_hours),policies=VALUES(policies),board_token=VALUES(board_token),updated_at=NOW()")
      ->execute([$acc,$v['hotel_on'],$v['name'],$v['address'],$v['phone'],$v['notify_email'],$v['tz'],$v['checkin_from'],$v['checkout_until'],$v['auto_confirm'],$v['breakfast_mode'],$v['breakfast_cents'],$v['city_tax_pct'],$v['min_nights'],$v['max_nights'],$v['cancel_hours'],$v['policies'],$v['board_token']]);
    if(isset($d['rooms']) && is_array($d['rooms'])) hotel_rooms_save($acc,$d['rooms']);
    return hotel_settings($acc);
}
/** Für Studio/Clients: Board-Token wird nicht ausgegeben (Link steht unter links.board). */
function hotel_settings_public(array $s): array { $s['board_ready']=!empty($s['board_token']); unset($s['board_token']); return $s; }
function hotel_enabled(int $acc): bool { return $acc===0 || hotel_settings($acc)['hotel_on']===1; }
function hotel_owner_email(int $acc): string { if($acc<=0) return ''; $s=th_db()->prepare("SELECT email FROM token_accounts WHERE id=?"); $s->execute([$acc]); return (string)($s->fetchColumn() ?: ''); }

/* ======================================================================= Zimmerkategorien */
function hotel_room_row(array $r): array {
    return ['id'=>(int)$r['id'],'name'=>(string)$r['name'],'description'=>(string)($r['description'] ?? ''),'capacity'=>(int)$r['capacity'],'units'=>(int)$r['units'],'price_cents'=>(int)$r['price_cents'],
        'weekend_cents'=>isset($r['weekend_cents']) && $r['weekend_cents']!==null && (int)$r['weekend_cents']>0 ? (int)$r['weekend_cents'] : null,'amenities'=>(string)($r['amenities'] ?? ''),'active'=>(int)($r['active'] ?? 1),'sort'=>(int)($r['sort'] ?? 0)];
}
function hotel_rooms(int $acc, bool $activeOnly=true): array {
    hotel_bootstrap(); if($acc===0) return array_map('hotel_room_row',HOTEL_DEMO['rooms']);
    $s=th_db()->prepare("SELECT * FROM hotel_rooms WHERE account_id=?".($activeOnly?" AND active=1":"")." ORDER BY active DESC, sort, id"); $s->execute([$acc]);
    return array_map('hotel_room_row',$s->fetchAll(PDO::FETCH_ASSOC));
}
/**
 * Zimmerliste auflösen (ohne zu schreiben): je Eintrag die Ziel-id – eigene vorhandene id, sonst eine gleichnamige bisherige
 * Kategorie (auch deaktivierte: „entfernt und neu angelegt“ bzw. „Beispiel laden“ behält so die id und damit die Belegung), sonst 0 = neu.
 * off = ids, die danach entfernt bzw. deaktiviert wären. wipe=false: Liste nicht leer, aber ohne gültige Namen → nichts entfernen.
 */
function hotel_rooms_plan(int $acc, array $list): array {
    $st=th_db()->prepare("SELECT id, name FROM hotel_rooms WHERE account_id=? ORDER BY active DESC, id DESC"); $st->execute([$acc]); $all=[];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r) $all[(int)$r['id']]=mb_strtolower(trim((string)$r['name']));
    $items=[]; $used=[]; $i=0; $eur=fn($v)=>hotel_cents_in($v);
    foreach($list as $it){ if(!is_array($it)) continue; $it=hotel_scalars($it); $name=mb_substr(trim((string)($it['name'] ?? '')),0,120); if($name==='') continue; $i++;
        $price=isset($it['price_cents']) && is_numeric($it['price_cents']) ? (int)$it['price_cents'] : $eur($it['price'] ?? 0);
        $we=isset($it['weekend_cents']) && is_numeric($it['weekend_cents']) ? (int)$it['weekend_cents'] : (isset($it['weekend']) && trim((string)$it['weekend'])!=='' ? $eur($it['weekend']) : 0);
        $vals=[$name,mb_substr(trim((string)($it['description'] ?? '')),0,500),max(1,min(20,(int)($it['capacity'] ?? 2))),max(1,min(999,(int)($it['units'] ?? 1))),max(0,min(10000000,$price)),
            $we>0 ? min(10000000,$we) : null,mb_substr(trim((string)($it['amenities'] ?? '')),0,300),(!isset($it['active']) || !empty($it['active'])) ? 1 : 0,$i];
        $id=(int)($it['id'] ?? 0); if($id>0 && (!isset($all[$id]) || isset($used[$id]))) $id=0;   // fremde/unbekannte/doppelte id → wie neu
        if($id>0) $used[$id]=1; $items[]=['id'=>$id,'name'=>$name,'vals'=>$vals];
    }
    foreach($items as &$x){ if($x['id']>0) continue; $n=mb_strtolower($x['name']);
        foreach($all as $rid=>$rn) if(!isset($used[$rid]) && $rn===$n){ $x['id']=$rid; $used[$rid]=1; break; } }
    unset($x);
    $wipe=$items || !$list; $off=$wipe ? array_values(array_diff(array_keys($all),array_keys($used))) : [];
    foreach($items as $x) if($x['id']>0 && $x['vals'][7]===0) $off[]=$x['id'];
    return ['items'=>$items,'off'=>array_values(array_unique($off)),'wipe'=>$wipe];
}
/** Anstehende, belegende Buchungen (Anfrage, bestätigt, im Haus; Abreise nach heute) auf diesen Kategorien. */
function hotel_rooms_in_use(int $acc, array $ids): array {
    $ids=array_values(array_filter(array_map('intval',$ids))); if(!$ids) return [];
    $st=th_db()->prepare("SELECT id, code, room_id, room_name, checkin, checkout, status FROM hotel_bookings WHERE account_id=? AND room_id IN (".implode(',',array_fill(0,count($ids),'?')).")
        AND status IN ('requested','confirmed','checked_in') AND checkout > ? ORDER BY checkin, id LIMIT 100");
    $st->execute(array_merge([$acc],$ids,[hotel_today(hotel_settings($acc))])); return $st->fetchAll(PDO::FETCH_ASSOC);
}
/** Vorab-Prüfung fürs Studio: Kategorien mit anstehenden Buchungen dürfen nicht entfernt/deaktiviert werden (sonst blockieren diese Buchungen nichts mehr → Überbuchung). */
function hotel_rooms_check(int $acc, array $list): array {
    if($acc<=0) return ['ok'=>true]; hotel_bootstrap(); $plan=hotel_rooms_plan($acc,$list); $busy=hotel_rooms_in_use($acc,$plan['off']); if(!$busy) return ['ok'=>true];
    $by=[]; foreach($busy as $b) $by[(string)$b['room_name']][]=$b;
    $parts=[]; foreach($by as $rn=>$bs){ $n=count($bs);
        $parts[]='„'.$rn.'“ hat noch '.$n.' anstehende '.($n===1 ? 'Buchung' : 'Buchungen').' ('.implode(', ',array_map(fn($b)=>$b['code'].' · '.hotel_human($b['checkin'],'short').'–'.hotel_human($b['checkout'],'short'),array_slice($bs,0,3))).($n>3 ? ' …' : '').')'; }
    return ['error'=>'room_in_use','hint'=>'Nicht gespeichert: '.implode('; ',$parts).'. Diese Kategorie bitte behalten (Name, Preise und Anzahl lassen sich ändern) oder die Buchungen zuerst stornieren bzw. umbuchen.',
        'bookings'=>array_map(fn($b)=>['id'=>(int)$b['id'],'code'=>$b['code'],'room'=>$b['room_name'],'checkin'=>$b['checkin'],'checkout'=>$b['checkout'],'status'=>$b['status']],$busy)];
}
/**
 * Komplette Liste übernehmen: vorhandene ids behalten (bzw. gleichnamige wiederverwenden), neue anlegen, fehlende entfernen
 * (mit Buchungen: nur deaktivieren). Kategorien mit anstehenden Buchungen bleiben in jedem Fall aktiv (kept_active).
 */
function hotel_rooms_save(int $acc, array $list): array {
    hotel_bootstrap(); if($acc<=0) return ['ok'=>false]; $p=th_db(); $plan=hotel_rooms_plan($acc,$list);
    $busy=array_values(array_unique(array_map(fn($b)=>(int)$b['room_id'],hotel_rooms_in_use($acc,$plan['off'])))); $kept=[];
    foreach($plan['items'] as $x){ $vals=$x['vals']; $id=$x['id'];
        if($id>0 && $vals[7]===0 && in_array($id,$busy,true)){ $vals[7]=1; $kept[]=$x['name']; }
        if($id>0) $p->prepare("UPDATE hotel_rooms SET name=?,description=?,capacity=?,units=?,price_cents=?,weekend_cents=?,amenities=?,active=?,sort=? WHERE id=? AND account_id=?")->execute([...$vals,$id,$acc]);
        else $p->prepare("INSERT INTO hotel_rooms(account_id,name,description,capacity,units,price_cents,weekend_cents,amenities,active,sort) VALUES(?,?,?,?,?,?,?,?,?,?)")->execute([$acc,...$vals]); }
    $listed=array_column($plan['items'],'id');
    $gone=array_values(array_filter($plan['off'],fn($id)=>!in_array($id,$listed,true)));
    foreach($gone as $id){
        if(in_array($id,$busy,true)){ $n=$p->prepare("SELECT name FROM hotel_rooms WHERE id=? AND account_id=?"); $n->execute([$id,$acc]); $kept[]=(string)$n->fetchColumn(); continue; }   // anstehende Buchungen → bleibt aktiv
        $p->prepare("DELETE FROM hotel_rooms WHERE id=? AND account_id=? AND NOT EXISTS (SELECT 1 FROM hotel_bookings b WHERE b.account_id=? AND b.room_id=?)")->execute([$id,$acc,$acc,$id]);
        $p->prepare("UPDATE hotel_rooms SET active=0 WHERE id=? AND account_id=?")->execute([$id,$acc]);   // Buchungen behalten Namen und Preis
    }
    return ['ok'=>true,'kept_active'=>$kept];
}
/** Zimmerkategorie per id oder Name (exakt, enthalten, ähnlich). Nur aktive. */
function hotel_room_find(int $acc, $ref): ?array {
    $rooms=hotel_rooms($acc,true); if($ref===null || $ref==='') return null;
    if(is_numeric($ref)){ foreach($rooms as $r) if($r['id']===(int)$ref) return $r; }
    $q=mb_strtolower(trim((string)$ref)); if($q==='') return null; $best=null; $bs=0;
    foreach($rooms as $r){ $n=mb_strtolower($r['name']); if($n===$q) return $r; if(str_contains($n,$q) || str_contains($q,$n)){ $sc=mb_strlen($n); if($sc>$bs){ $bs=$sc; $best=$r; } } }
    if(!$best){ foreach($rooms as $r){ similar_text($q,mb_strtolower($r['name']),$pct); if($pct>$bs && $pct>=65){ $bs=$pct; $best=$r; } } }
    return $best;
}

/* ======================================================================= Datum & Zeit */
function hotel_day(string $ymd): DateTimeImmutable { return new DateTimeImmutable($ymd.' 00:00:00',new DateTimeZone('UTC')); }
/** Eingaben (KI-Werkzeug, API, Formulare) auf Skalare reduzieren – Arrays/Objekte werden zu '' (außer $keep). */
function hotel_scalars(array $a, array $keep=[]): array { foreach($a as $k=>$v){ if(in_array($k,$keep,true)) continue; if(!is_scalar($v) && $v!==null) $a[$k]=''; } return $a; }
function hotel_eur(int $c): string { return number_format($c/100,2,',','.').' €'; }
/** „1 Nacht“ / „3 Nächte“ */
function hotel_nights_txt(int $n): string { return $n.' '.($n===1 ? 'Nacht' : 'Nächte'); }
/** Betrag aus Eingabe → Cent: „18,50“, „18.5“, „1.250,00“, „1.250“ */
function hotel_cents_in($v): int {
    $t=preg_replace('/[^0-9,.]/','',(string)$v); if($t==='') return 0;
    if(str_contains($t,',')) $t=str_replace(['.',','],['','.'],$t); elseif(preg_match('/\.\d{3}$/',$t)) $t=str_replace('.','',$t);
    return (int)round((float)$t*100);
}
/** 'short' = Fr, 17.10. · 'med' = Fr, 17.10.2026 · 'long' = Freitag, 17. Oktober 2026 */
function hotel_human(string $ymd, string $style='med'): string {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$ymd)) return $ymd; $d=hotel_day($ymd); $w=(int)$d->format('w');
    if($style==='long') return HOTEL_WDL[$w].', '.(int)$d->format('j').'. '.HOTEL_MON[(int)$d->format('n')-1].' '.$d->format('Y');
    return HOTEL_WD[$w].', '.$d->format('d.m.').($style==='short' ? '' : $d->format('Y'));
}
/** Lokale Zeit (Y-m-d H:i[:s]) → „heute 06:30“ / „morgen 06:30“ / „Sa, 18.10. 06:30“ */
function hotel_when_human(string $local, DateTimeZone $tz): string {
    $d=substr($local,0,10); $t=substr($local,11,5); $today=(new DateTimeImmutable('now',$tz))->format('Y-m-d');
    if($d===$today) return 'heute '.$t; if($d===hotel_day($today)->modify('+1 day')->format('Y-m-d')) return 'morgen '.$t;
    return hotel_human($d,'short').' '.$t;
}
function hotel_words(string $t, array $stop): array { return array_values(array_filter(preg_split('/[\s,;]+/u',$t) ?: [],fn($w)=>$w!=='' && !in_array($w,$stop,true))); }
/**
 * Datum normalisieren: YYYY-MM-DD, TT.MM.JJJJ, TT.MM.JJ, TT.MM. (nächstes Vorkommen ab $notBefore), „17. Oktober“, heute/morgen/übermorgen,
 * „in 3 Tagen“, Wochentage („Freitag“, „nächsten Freitag“). Mehrdeutiges (03/04, „Freitag“ am Freitag) wird abgelehnt.
 * Rückgabe ['date'=>'Y-m-d','human'=>…] oder ['error'=>…,'hint'=>…].
 */
function hotel_parse_date($v, DateTimeZone $tz, ?string $notBefore=null): array {
    $today=(new DateTimeImmutable('now',$tz))->format('Y-m-d'); $base=$notBefore ?: $today;
    $t=mb_strtolower(trim((string)$v)); $t=trim(preg_replace('/\s+/u',' ',$t),' ,;');
    $words=hotel_words($t,['am','den','dem','ab','bis','zum','vom','von','der','on','the']); $wdPre=null;
    $wdTok=['so'=>0,'mo'=>1,'di'=>2,'mi'=>3,'do'=>4,'fr'=>5,'sa'=>6,'sonntag'=>0,'montag'=>1,'dienstag'=>2,'mittwoch'=>3,'donnerstag'=>4,'freitag'=>5,'samstag'=>6,'sonnabend'=>6,'sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
    if(count($words)>1 && isset($wdTok[rtrim($words[0],'.')]) && preg_match('/\d/',$words[1])){ $wdPre=$wdTok[rtrim($words[0],'.')]; array_shift($words); }   // „Fr, 17.10.“ → Wochentag prüfen
    $t=implode(' ',$words);
    if($t==='') return ['error'=>'date_required','hint'=>'Bitte ein Datum nennen (z. B. 17.10.2026).'];
    if($wdPre!==null){ $r=hotel_parse_date($t,$tz,$notBefore); if(isset($r['date']) && (int)hotel_day($r['date'])->format('w')!==$wdPre) return ['error'=>'date_weekday_mismatch','hint'=>'Der '.hotel_human($r['date'],'med').' ist ein '.HOTEL_WDL[(int)hotel_day($r['date'])->format('w')].', kein '.HOTEL_WDL[$wdPre].' – bitte Datum klären.']; return $r; }
    $ok=function(int $y,int $m,int $d): array { if(!checkdate($m,$d,$y)) return ['error'=>'date_invalid','hint'=>sprintf('Den %02d.%02d.%04d gibt es nicht – bitte Datum prüfen.',$d,$m,$y)]; $s=sprintf('%04d-%02d-%02d',$y,$m,$d); return ['date'=>$s,'human'=>hotel_human($s)]; };
    $roll=function(int $m,int $d) use($base,$ok): array { $y=(int)substr($base,0,4); if(!checkdate($m,$d,$y) && !checkdate($m,$d,$y+1)) return $ok($y,$m,$d); if(!checkdate($m,$d,$y) || sprintf('%04d-%02d-%02d',$y,$m,$d) < $base) $y++; return $ok($y,$m,$d); };
    $rel=['heute'=>0,'today'=>0,'morgen'=>1,'tomorrow'=>1,'übermorgen'=>2,'uebermorgen'=>2];
    if(isset($rel[$t])){ $s=hotel_day($today)->modify('+'.$rel[$t].' days')->format('Y-m-d'); return ['date'=>$s,'human'=>hotel_human($s)]; }
    if(preg_match('/^in (\d{1,3}) (tag|tagen|days?)$/u',$t,$m)){ $s=hotel_day($today)->modify('+'.(int)$m[1].' days')->format('Y-m-d'); return ['date'=>$s,'human'=>hotel_human($s)]; }
    if(preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/',$t,$m)) return $ok((int)$m[1],(int)$m[2],(int)$m[3]);
    if(preg_match('/^(\d{1,2})\.\s?(\d{1,2})\.?\s?(\d{4}|\d{2})?$/',$t,$m)){ if(isset($m[3]) && $m[3]!=='') return $ok(strlen($m[3])===2 ? 2000+(int)$m[3] : (int)$m[3],(int)$m[2],(int)$m[1]); return $roll((int)$m[2],(int)$m[1]); }
    if(preg_match('/^(\d{1,2})\/(\d{1,2})(?:\/(\d{4}|\d{2}))?$/',$t,$m)){ $a=(int)$m[1]; $b=(int)$m[2];
        if($a<=12 && $b<=12 && $a!==$b) return ['error'=>'date_ambiguous','hint'=>'„'.trim((string)$v).'“ ist mehrdeutig (Tag/Monat) – bitte als TT.MM.JJJJ nennen.'];
        [$d,$mo]=$a>12 ? [$a,$b] : ($b>12 ? [$b,$a] : [$a,$b]); if(isset($m[3]) && $m[3]!=='') return $ok(strlen($m[3])===2 ? 2000+(int)$m[3] : (int)$m[3],$mo,$d); return $roll($mo,$d); }
    $mon=['januar'=>1,'jänner'=>1,'jaenner'=>1,'jan'=>1,'january'=>1,'februar'=>2,'feber'=>2,'feb'=>2,'february'=>2,'märz'=>3,'maerz'=>3,'mär'=>3,'mrz'=>3,'march'=>3,'april'=>4,'apr'=>4,'mai'=>5,'may'=>5,
          'juni'=>6,'jun'=>6,'june'=>6,'juli'=>7,'jul'=>7,'july'=>7,'august'=>8,'aug'=>8,'september'=>9,'sept'=>9,'sep'=>9,'oktober'=>10,'okt'=>10,'october'=>10,'oct'=>10,'november'=>11,'nov'=>11,'dezember'=>12,'dez'=>12,'december'=>12,'dec'=>12];
    if(preg_match('/^(\d{1,2})\.?\s?([a-zäöü]+)\.?\s?(\d{4})?$/u',$t,$m) && isset($mon[$m[2]])){ if(isset($m[3]) && $m[3]!=='') return $ok((int)$m[3],$mon[$m[2]],(int)$m[1]); return $roll($mon[$m[2]],(int)$m[1]); }
    $wd=['sonntag'=>0,'montag'=>1,'dienstag'=>2,'mittwoch'=>3,'donnerstag'=>4,'freitag'=>5,'samstag'=>6,'sonnabend'=>6,'sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
    if(preg_match('/^(?:(nächste[nrsm]?|naechste[nrsm]?|kommende[nrsm]?|diese[nrsm]?|next|this)\s)?([a-z]+)$/u',$t,$m) && isset($wd[$m[2]])){
        $target=$wd[$m[2]]; $pre=$m[1] ?? ''; $d=hotel_day($base);
        if($pre==='' && $base===$today && (int)$d->format('w')===$target) return ['error'=>'date_ambiguous','hint'=>'Heute ist '.HOTEL_WDL[$target].' – heute oder nächste Woche? Bitte das Datum nennen.'];
        if($pre!=='' && !preg_match('/^(diese|this)/u',$pre) && $base===$today) $d=$d->modify('+1 day');
        for($i=0;$i<7 && (int)$d->format('w')!==$target;$i++) $d=$d->modify('+1 day');
        $s=$d->format('Y-m-d'); return ['date'=>$s,'human'=>hotel_human($s)];
    }
    return ['error'=>'date_format','hint'=>'Datum bitte als TT.MM.JJJJ oder YYYY-MM-DD angeben (z. B. 17.10.2026).'];
}
/**
 * Zeitpunkt für Weckruf/Taxi: „06:30“, „6 Uhr“, „6.30“, „morgen 5 Uhr“, „17.10. 6:30“, „2026-10-17 06:30“, „8 Uhr abends“; mit $allowAsap auch „sofort“.
 * Nur Uhrzeit → heute, falls noch nicht vorbei, sonst morgen. Rückgabe ['at'=>'Y-m-d H:i:00','asap'=>bool,'human'=>…] oder Fehler.
 */
function hotel_parse_when($v, DateTimeZone $tz, bool $allowAsap=false): array {
    $now=new DateTimeImmutable('now',$tz); $t=mb_strtolower(trim((string)$v));
    if($t==='') return ['error'=>'time_required','hint'=>'Bitte eine Uhrzeit nennen (z. B. 06:30 oder „morgen 5 Uhr“).'];
    if($allowAsap && preg_match('/^(sofort|jetzt|gleich|asap|now|so schnell wie möglich|schnellstmöglich|umgehend|sofort bitte)$/u',$t)) return ['at'=>$now->format('Y-m-d H:i:00'),'asap'=>true,'human'=>'sofort'];
    $t=preg_replace('/(\d{4}-\d{2}-\d{2})t(\d)/u','$1 $2',$t); $t=preg_replace('/(\d{1,2}:\d{2}):\d{2}/','$1',$t);
    $words=hotel_words(str_replace(['uhr',' h '],[' ',' '],$t),['um','gegen','ca','ca.','circa','bitte','früh','frueh','morgens','vormittags','abends','nachmittags','am','den','pm','a.m.','p.m.']);
    $pm=(bool)preg_match('/(abends|nachmittags|\bpm\b|p\.m\.)/u',$t); $time=null; $rest=[]; $best=-1; $prio=0;
    foreach($words as $i=>$w){   // Uhrzeit-Kandidat: „6:30“ vor „6“/„6h“ vor „6.30“ (könnte ein Datum sein)
        if(!preg_match('/^(\d{1,2})(?:([:.])(\d{2}))?h?$/',$w,$m) || (int)$m[1]>23 || (int)($m[3] ?? 0)>59) continue;
        $pr=($m[2] ?? '')===':' ? 3 : (($m[2] ?? '')==='' ? 2 : 1); if($pr>$prio){ $prio=$pr; $best=$i; $time=[(int)$m[1],(int)($m[3] ?? 0)]; }
    }
    foreach($words as $i=>$w) if($i!==$best) $rest[]=$w;
    if($time===null) return ['error'=>'time_required','hint'=>'Bitte eine konkrete Uhrzeit nennen (z. B. 06:30).'];
    if($pm && $time[0]<12) $time[0]+=12;
    if(!$rest){ $at=$now->setTime($time[0],$time[1]); if($at<=$now) $at=$at->modify('+1 day'); }
    else {
        $d=hotel_parse_date(implode(' ',$rest),$tz); if(isset($d['error'])) return $d;
        $at=new DateTimeImmutable($d['date'].sprintf(' %02d:%02d:00',$time[0],$time[1]),$tz);
        if($at < $now->modify('-5 minutes')) return ['error'=>'time_past','hint'=>'Dieser Zeitpunkt ('.hotel_when_human($at->format('Y-m-d H:i'),$tz).') liegt in der Vergangenheit.'];
    }
    if($at > $now->modify('+60 days')) return ['error'=>'time_too_far','hint'=>'Bitte einen Zeitpunkt innerhalb der nächsten 60 Tage nennen.'];
    return ['at'=>$at->format('Y-m-d H:i:00'),'asap'=>false,'human'=>hotel_when_human($at->format('Y-m-d H:i'),$tz)];
}
/** Zeitraum prüfen (Vergangenheit, Reihenfolge, Mindest-/Höchstaufenthalt, Horizont). */
function hotel_range(array $s, $checkin, $checkout, $nights=null): array {
    $tz=hotel_tz($s); $today=hotel_today($s);
    $ci=hotel_parse_date($checkin,$tz,$today);
    if(isset($ci['error'])) return ['error'=>$ci['error']==='date_required' ? 'checkin_required' : $ci['error'],'hint'=>'Anreise: '.$ci['hint']];
    if(trim((string)$checkout)==='' && (int)$nights>0) $co=['date'=>hotel_day($ci['date'])->modify('+'.min(400,(int)$nights).' days')->format('Y-m-d')];
    else { $co=hotel_parse_date($checkout,$tz,hotel_day($ci['date'])->modify('+1 day')->format('Y-m-d'));
        if(isset($co['error'])) return ['error'=>$co['error']==='date_required' ? 'checkout_required' : $co['error'],'hint'=>'Abreise: '.$co['hint']]; }
    if($ci['date'] < $today) return ['error'=>'checkin_past','hint'=>'Die Anreise ('.hotel_human($ci['date']).') liegt in der Vergangenheit – heute ist '.hotel_human($today).'.'];
    if($co['date'] <= $ci['date']) return ['error'=>'checkout_not_after_checkin','hint'=>'Die Abreise muss nach der Anreise ('.hotel_human($ci['date']).') liegen.'];
    if($ci['date'] > hotel_day($today)->modify('+'.HOTEL_HORIZON_DAYS.' days')->format('Y-m-d')) return ['error'=>'too_far_ahead','hint'=>'So weit im Voraus sind noch keine Buchungen möglich – bitte die Rezeption kontaktieren.'];
    $n=(int)hotel_day($ci['date'])->diff(hotel_day($co['date']))->days;
    if($n<$s['min_nights']) return ['error'=>'min_nights','min_nights'=>$s['min_nights'],'hint'=>'Mindestaufenthalt: '.hotel_nights_txt($s['min_nights']).'.'];
    if($n>$s['max_nights']) return ['error'=>'max_nights','max_nights'=>$s['max_nights'],'hint'=>'Online höchstens '.hotel_nights_txt($s['max_nights']).' am Stück – für längere Aufenthalte bitte direkt die Rezeption kontaktieren.'];
    return ['checkin'=>$ci['date'],'checkout'=>$co['date'],'nights'=>$n];
}

/* ======================================================================= Verfügbarkeit & Preis */
/** Belegte Einheiten je Zimmerkategorie und Nacht im Zeitraum [from, to): [room_id => ['Y-m-d' => n]]. Anfragen blockieren mit. */
function hotel_occupancy(int $acc, string $from, string $to, ?int $roomId=null, bool $lock=false): array {
    $sql="SELECT room_id, checkin, checkout FROM hotel_bookings WHERE account_id=? AND status IN ('requested','confirmed','checked_in') AND checkin < ? AND checkout > ?".($roomId!==null ? " AND room_id=?" : "").($lock ? " FOR UPDATE" : "");
    $args=[$acc,$to,$from]; if($roomId!==null) $args[]=$roomId; $st=th_db()->prepare($sql); $st->execute($args); $occ=[];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){ $rid=(int)$r['room_id']; $end=min((string)$r['checkout'],$to);
        for($d=hotel_day(max((string)$r['checkin'],$from)); $d->format('Y-m-d') < $end; $d=$d->modify('+1 day')){ $k=$d->format('Y-m-d'); $occ[$rid][$k]=($occ[$rid][$k] ?? 0)+1; } }
    return $occ;
}
function hotel_free_units(array $room, array $occ): int { $max=0; foreach(($occ[$room['id']] ?? []) as $n) $max=max($max,$n); return max(0,$room['units']-$max); }
/** Preis: Nächte (Fr+Sa Wochenendpreis), Frühstück (optional × Personen × Nächte), City-Tax % auf den Zimmerpreis. */
function hotel_quote(array $s, array $room, string $checkin, int $nights, int $guests, bool $breakfast): array {
    $d=hotel_day($checkin); $lines=[]; $roomC=0;
    for($i=0;$i<$nights;$i++){ $w=(int)$d->format('w'); $we=($w===5 || $w===6) && $room['weekend_cents']!==null; $c=$we ? (int)$room['weekend_cents'] : (int)$room['price_cents'];
        $lines[]=['date'=>$d->format('Y-m-d'),'label'=>HOTEL_WD[$w].', '.$d->format('d.m.'),'weekend'=>$we,'cents'=>$c,'price'=>hotel_eur($c)]; $roomC+=$c; $d=$d->modify('+1 day'); }
    $bf=$s['breakfast_mode']==='optional' && $breakfast ? $s['breakfast_cents']*$guests*$nights : 0;
    $tax=$s['city_tax_pct']>0 ? (int)round($roomC*$s['city_tax_pct']/100) : 0;
    return ['nights'=>$lines,'room_cents'=>$roomC,'breakfast'=>$s['breakfast_mode']==='included' ? 'inklusive' : ($bf>0 ? 'gebucht' : ($s['breakfast_mode']==='optional' ? 'ohne' : 'nicht angeboten')),
        'breakfast_cents'=>$bf,'city_tax_pct'=>$s['city_tax_pct'],'city_tax_cents'=>$tax,'total_cents'=>$roomC+$bf+$tax,'total'=>hotel_eur($roomC+$bf+$tax)];
}
/** Freie Zimmerkategorien für [checkin, checkout) und Personen – mit Gesamtpreis (ohne/mit Frühstück). */
function hotel_availability(int $acc, $checkin, $checkout, $guests, $nights=null): array {
    $s=hotel_settings($acc); $r=hotel_range($s,$checkin,$checkout,$nights); if(isset($r['error'])) return $r;
    $g=(int)$guests; if($g<1) return ['error'=>'guests_required','hint'=>'Für wie viele Personen?']; if($g>20) return ['error'=>'too_many_guests','hint'=>'Gruppen über 20 Personen bitte direkt bei der Rezeption anfragen.'];
    $rooms=hotel_rooms($acc,true); if(!$rooms) return ['error'=>'no_rooms','hint'=>'Es sind noch keine Zimmerkategorien hinterlegt – bitte an die Rezeption verweisen.'];
    $occ=hotel_occupancy($acc,$r['checkin'],$r['checkout']); $opts=[]; $sold=[]; $small=[]; $maxCap=0;
    foreach($rooms as $room){ $maxCap=max($maxCap,$room['capacity']);
        if($room['capacity']<$g){ $small[]=$room['name'].' (bis '.$room['capacity'].' Pers.)'; continue; }
        $free=hotel_free_units($room,$occ); if($free<1){ $sold[]=$room['name']; continue; }
        $q=hotel_quote($s,$room,$r['checkin'],$r['nights'],$g,false); $qb=$s['breakfast_mode']==='optional' && $s['breakfast_cents']>0 ? hotel_quote($s,$room,$r['checkin'],$r['nights'],$g,true) : null;
        $opts[]=['room_id'=>$room['id'],'room'=>$room['name'],'description'=>$room['description'],'capacity'=>$room['capacity'],'amenities'=>$room['amenities'],'free_units'=>$free,'last_units'=>$free<=2 && $free<$room['units'],
            'avg_night'=>hotel_eur((int)round($q['room_cents']/max(1,$r['nights']))),'nightly'=>$q['nights'],'room_cents'=>$q['room_cents'],'city_tax_cents'=>$q['city_tax_cents'],'total_cents'=>$q['total_cents'],'total'=>$q['total'],
            'with_breakfast_total_cents'=>$qb['total_cents'] ?? null,'with_breakfast_total'=>$qb['total'] ?? null,'breakfast'=>$q['breakfast']];
    }
    usort($opts,fn($a,$b)=>[$a['capacity']-$g,$a['total_cents']]<=>[$b['capacity']-$g,$b['total_cents']]);
    $hint=null;
    if(!$opts && $g>$maxCap) $hint='Kein Zimmer für '.$g.' Personen in einem Zimmer (max. '.$maxCap.' Pers.). Vorschlag: mehrere Zimmer kombinieren – je Zimmer book_room einzeln aufrufen.';
    elseif(!$opts) $hint='Für diesen Zeitraum ist leider nichts mehr frei. Andere Daten vorschlagen oder einen Rückruf der Rezeption anbieten.';
    return ['ok'=>true,'checkin'=>$r['checkin'],'checkout'=>$r['checkout'],'checkin_human'=>hotel_human($r['checkin'],'long'),'checkout_human'=>hotel_human($r['checkout'],'long'),'nights'=>$r['nights'],'guests'=>$g,
        'booking_mode'=>$s['auto_confirm'] ? 'sofortbuchung' : 'anfrage','breakfast_mode'=>$s['breakfast_mode'],'breakfast_per_person_night'=>$s['breakfast_mode']==='optional' ? hotel_eur($s['breakfast_cents']) : null,
        'city_tax_pct'=>$s['city_tax_pct'],'options'=>$opts,'sold_out'=>$sold,'too_small'=>$small,'hint'=>$hint];
}

/* ======================================================================= Buchungen */
function hotel_code(): string { $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $c=''; for($i=0;$i<8;$i++) $c.=$a[random_int(0,strlen($a)-1)]; return $c; }
function hotel_bool($v): bool { if(is_bool($v)) return $v; $v=mb_strtolower(trim((string)$v)); return in_array($v,['1','true','ja','yes','y','on','mit','gern','gerne','inklusive'],true); }
function hotel_clean_room($v): string { $t=trim(preg_replace('/^(zimmer|zi\.?|zimmernummer|zimmer-nr\.?|room|nr\.?|no\.?)\s*/iu','',trim((string)$v))); return mb_substr(trim(preg_replace('/[^\p{L}\p{N} .\/-]/u','',$t)),0,20); }
function hotel_stay_url(string $code): string { return 'https://chat-help.com/chat/stay.php?c='.rawurlencode($code); }
function hotel_by_code(string $code): ?array { hotel_bootstrap(); $code=strtoupper(preg_replace('/[^A-Za-z0-9]/','',$code)); if($code==='' || strlen($code)>8) return null; $s=th_db()->prepare("SELECT * FROM hotel_bookings WHERE code=?"); $s->execute([$code]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function hotel_by_id(int $acc, int $id): ?array { hotel_bootstrap(); $s=th_db()->prepare("SELECT * FROM hotel_bookings WHERE id=? AND account_id=?"); $s->execute([$id,$acc]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
/** Kostenfreie Storno-Frist: Anreisetag + Check-in-Zeit (lokal) − cancel_hours. */
function hotel_cancel_deadline(array $b, array $s): DateTimeImmutable {
    $t=preg_match('/^\d{2}:\d{2}$/',$s['checkin_from']) ? $s['checkin_from'] : '15:00';
    return (new DateTimeImmutable($b['checkin'].' '.$t.':00',hotel_tz($s)))->modify('-'.max(0,(int)$s['cancel_hours']).' hours');
}
function hotel_can_guest_cancel(array $b, array $s): bool { return in_array($b['status'],['requested','confirmed'],true) && hotel_now($s) < hotel_cancel_deadline($b,$s); }
function hotel_public(array $b, ?array $s=null): array {
    $s=$s ?: hotel_settings((int)$b['account_id']); $tz=hotel_tz($s); $price=json_decode((string)($b['price_json'] ?? ''),true); $created=th_dbtime($b['created_at'] ?? null,$tz);
    $dl=hotel_cancel_deadline($b,$s); $can=hotel_can_guest_cancel($b,$s);
    return ['id'=>(int)$b['id'],'code'=>$b['code'],'status'=>$b['status'],'status_label'=>HOTEL_STATUS_LABELS[$b['status']] ?? $b['status'],'room_id'=>(int)$b['room_id'],'room'=>$b['room_name'],'room_no'=>(string)$b['room_no'],
        'checkin'=>$b['checkin'],'checkout'=>$b['checkout'],'checkin_human'=>hotel_human($b['checkin']),'checkout_human'=>hotel_human($b['checkout']),'checkin_short'=>hotel_human($b['checkin'],'short'),'checkout_short'=>hotel_human($b['checkout'],'short'),'checkin_long'=>hotel_human($b['checkin'],'long'),'checkout_long'=>hotel_human($b['checkout'],'long'),
        'checkin_from'=>$s['checkin_from'],'checkout_until'=>$s['checkout_until'],'nights'=>(int)$b['nights'],'guests'=>(int)$b['guests'],'breakfast'=>(bool)$b['breakfast'],
        'breakfast_text'=>$s['breakfast_mode']==='included' ? 'inklusive' : ((int)$b['breakfast'] ? 'gebucht' : 'ohne Frühstück'),'name'=>$b['guest_name'],'phone'=>$b['phone'],'email'=>$b['email'],'channel'=>$b['channel'],'note'=>$b['note'],
        'total_cents'=>(int)$b['total_cents'],'total'=>hotel_eur((int)$b['total_cents']),'price'=>is_array($price) ? $price : null,
        'created'=>$created ? $created->format('d.m.Y H:i') : '','created_iso'=>$created ? $created->format(DATE_ATOM) : null,
        'cancel_until'=>$dl->format('d.m.Y H:i'),'cancel_until_human'=>HOTEL_WD[(int)$dl->format('w')].', '.$dl->format('d.m.Y, H:i').' Uhr','can_cancel'=>$can,
        'hotel'=>$s['name'],'stay_url'=>hotel_stay_url($b['code']),'ics_url'=>hotel_stay_url($b['code']).'&ics=1'];
}
/** Keine Nachrichten an Gäste/Hotel: Demo-Konto 0 und Demo-/Seed-Kanäle. */
function hotel_silent(int $acc, string $channel): bool { return $acc<=0 || in_array($channel,['demo','seed'],true); }
const HOTEL_DEMO_NOMAIL=' (Live-Demo – es wird keine E-Mail versendet.)';
/**
 * Buchung anlegen. $d: room_id|room_name, checkin, checkout (oder nights), guests, name, phone?, email?, breakfast?, note?.
 * Status: confirmed bei Sofortbuchung, sonst requested. $manual = Eintrag der Rezeption im Studio-Formular (POST hotel/book):
 * immer bestätigt, auch wenn die Online-Rezeption ausgeschaltet ist. Der Agenten-Testchat im Studio (Kanal studio) folgt dagegen
 * den Einstellungen wie jeder andere Kanal. Prüft Verfügbarkeit erneut unter Sperre (keine Überbuchung).
 */
function hotel_book(int $acc, array $d, string $channel, string $from='', bool $manual=false): array {
    $s=hotel_settings($acc); $channel=mb_substr(preg_replace('/[^a-z_-]/','',strtolower($channel)) ?: 'chat',0,12); $manual=$manual && $acc>0;
    if(!hotel_enabled($acc) && !$manual) return ['error'=>'hotel_disabled','hint'=>'Online-Buchungen sind derzeit nicht aktiv – bitte an die Rezeption verweisen.'];
    $name=mb_substr(trim(preg_replace('/\s+/u',' ',(string)($d['name'] ?? $d['guest_name'] ?? ''))),0,120);
    $email=trim((string)($d['email'] ?? '')); $phone=mb_substr(trim(preg_replace('/[^0-9+ ()\/-]/','',(string)($d['phone'] ?? ''))),0,40);
    $from=preg_replace('/\D/','',$from); if($phone==='' && $from!=='' && in_array($channel,['whatsapp','phone'],true)) $phone='+'.$from;
    if($name==='') return ['error'=>'name_required','hint'=>'Bitte den Namen des Gastes erfragen (Vor- und Nachname).'];
    if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) return ['error'=>'email_invalid','hint'=>'Die E-Mail-Adresse „'.$email.'“ ist ungültig – bitte noch einmal erfragen.'];
    if($phone==='' && $email==='') return ['error'=>'contact_required','hint'=>'Bitte E-Mail (für die Bestätigung) oder Telefonnummer erfragen.'];
    $room=hotel_room_find($acc,$d['room_id'] ?? null) ?: hotel_room_find($acc,$d['room_name'] ?? ($d['room'] ?? null));
    if(!$room) return ['error'=>'room_not_found','hint'=>'Zimmerkategorie unbekannt. Verfügbare Kategorien: '.implode(', ',array_column(hotel_rooms($acc),'name')).'.'];
    $r=hotel_range($s,$d['checkin'] ?? '',$d['checkout'] ?? '',$d['nights'] ?? null); if(isset($r['error'])) return $r;
    $g=(int)($d['guests'] ?? 0); if($g<1) return ['error'=>'guests_required','hint'=>'Für wie viele Personen?'];
    if($g>$room['capacity']) return ['error'=>'too_many_guests','capacity'=>$room['capacity'],'hint'=>$room['name'].' ist für höchstens '.$room['capacity'].' Personen. Größere Kategorie anbieten oder ein weiteres Zimmer buchen.'];
    $bf=$s['breakfast_mode']==='included' ? true : ($s['breakfast_mode']==='optional' && hotel_bool($d['breakfast'] ?? false));
    $q=hotel_quote($s,$room,$r['checkin'],$r['nights'],$g,$bf);
    $status=($s['auto_confirm'] || $manual) ? 'confirmed' : 'requested';
    $note=mb_substr(trim((string)($d['note'] ?? '')),0,500); $p=th_db(); $code='';
    $p->beginTransaction();
    try{
        if($acc>0){ $lk=$p->prepare("SELECT id FROM hotel_rooms WHERE id=? AND account_id=? FOR UPDATE"); $lk->execute([$room['id'],$acc]); }   // Buchungen je Kategorie serialisieren
        $occ=hotel_occupancy($acc,$r['checkin'],$r['checkout'],$room['id'],true);
        if(hotel_free_units($room,$occ)<1){ $p->rollBack(); $alt=hotel_availability($acc,$r['checkin'],$r['checkout'],$g);
            return ['error'=>'sold_out','hint'=>$room['name'].' ist im Zeitraum leider ausgebucht.','alternatives'=>array_map(fn($o)=>['room_id'=>$o['room_id'],'room'=>$o['room'],'total'=>$o['total']],$alt['options'] ?? [])]; }
        $ins=$p->prepare("INSERT INTO hotel_bookings(account_id,code,room_id,room_name,checkin,checkout,nights,guests,guest_name,phone,email,breakfast,total_cents,status,channel,note,price_json,wa_from) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        for($try=0;;$try++){ $code=hotel_code();
            try{ $ins->execute([$acc,$code,$room['id'],$room['name'],$r['checkin'],$r['checkout'],$r['nights'],$g,$name,$phone,mb_strtolower($email),(int)$bf,$q['total_cents'],$status,$channel,$note,json_encode($q,JSON_UNESCAPED_UNICODE),$channel==='whatsapp' ? mb_substr($from,0,40) : '']); break; }
            catch(PDOException $e){ if($e->getCode()!=='23000' || $try>=4) throw $e; } }
        $p->commit();
    }catch(Throwable $e){ if($p->inTransaction()) $p->rollBack(); error_log('CH hotel_book: '.$e->getMessage()); return ['error'=>'db_error','hint'=>'Technischer Fehler beim Speichern – bitte erneut versuchen oder die Rezeption kontaktieren.']; }
    $b=hotel_by_code($code); hotel_notify($b,$s,'new');
    $pub=hotel_public($b,$s); $silent=hotel_silent($acc,$channel);
    $via=$silent ? '' : ($email!=='' ? ' per E-Mail an '.$email : ($channel==='whatsapp' && $from!=='' ? ' per WhatsApp' : ($channel==='phone' && $phone!=='' ? ' per SMS' : ($phone!=='' ? ' telefonisch' : ''))));
    return ['ok'=>true,'status'=>$status,'booking'=>$pub,
        'message'=>$status==='confirmed' ? 'Buchung bestätigt: '.$room['name'].', '.hotel_human($r['checkin']).' bis '.hotel_human($r['checkout']).', '.hotel_nights_txt($r['nights']).', '.$q['total'].'. Code '.$code.'.'.($email!=='' ? ($silent ? HOTEL_DEMO_NOMAIL : ' Bestätigung per E-Mail an '.$email.'.') : '')
                                         : 'Anfrage gespeichert (Code '.$code.'): '.$room['name'].', '.hotel_human($r['checkin']).' bis '.hotel_human($r['checkout']).', '.hotel_nights_txt($r['nights']).', '.$q['total'].'. Die Rezeption bestätigt in Kürze'.$via.'.'.($silent && $email!=='' ? HOTEL_DEMO_NOMAIL : '')];
}
function hotel_name_norm(string $x): string {
    $x=strtr(mb_strtolower($x),['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','á'=>'a','à'=>'a','â'=>'a','å'=>'a','ç'=>'c','ğ'=>'g','ı'=>'i','ş'=>'s','ñ'=>'n','ó'=>'o','ò'=>'o','ø'=>'o','ú'=>'u','í'=>'i','č'=>'c','š'=>'s','ž'=>'z']);
    return trim(preg_replace('/\s+/',' ',preg_replace('/[^a-z0-9 ]/',' ',$x)));
}
/** Stimmt die Angabe des Anrufers (Name, Nachname, Telefon oder E-Mail) mit der Buchung überein? */
function hotel_identity_ok(array $b, string $given): bool {
    $given=trim($given); if($given==='') return false;
    if(str_contains($given,'@')) return $b['email']!=='' && mb_strtolower($given)===mb_strtolower((string)$b['email']);
    $dg=preg_replace('/\D/','',$given); if(strlen($dg)>=6){ $ds=preg_replace('/\D/','',(string)$b['phone']); return strlen($ds)>=6 && substr($ds,-8)===substr($dg,-8); }
    $g=hotel_name_norm($given); $st=hotel_name_norm((string)$b['guest_name']); if($g==='' || $st==='') return false; if($g===$st) return true;
    $tok=array_values(array_filter(explode(' ',$g),fn($w)=>strlen($w)>=3 && !in_array($w,['herr','frau','familie','fam','mr','mrs','ms','miss','dr','prof'],true)));
    if(!$tok) return false; $have=explode(' ',$st); foreach($tok as $w) if(!in_array($w,$have,true)) return false; return true;
}
/** Buchung nur mit Code UND Name/Telefon/E-Mail (nie mit Code allein). */
function hotel_find_booking(int $acc, string $code, string $nameOrPhone): ?array {
    $b=hotel_by_code($code); if(!$b || (int)$b['account_id']!==$acc) return null;
    return hotel_identity_ok($b,$nameOrPhone) ? $b : null;
}
/** Storno durch den Gast (Werkzeug oder stay.php): nur requested/confirmed und vor Ablauf der kostenfreien Frist. */
function hotel_guest_cancel(array $b, string $channel='guest'): array {
    $s=hotel_settings((int)$b['account_id']);
    if($b['status']==='cancelled') return ['ok'=>true,'already'=>true,'booking'=>hotel_public($b,$s)];
    if(!in_array($b['status'],['requested','confirmed'],true)) return ['error'=>'not_cancellable','status'=>$b['status'],'hint'=>'Diese Buchung hat den Status „'.(HOTEL_STATUS_LABELS[$b['status']] ?? $b['status']).'“ und kann nicht mehr storniert werden.'];
    $dl=hotel_cancel_deadline($b,$s);
    if(hotel_now($s) >= $dl) return ['error'=>'cancel_window_passed','cancel_until'=>$dl->format('d.m.Y H:i'),'hint'=>'Die kostenfreie Stornierung war nur bis '.HOTEL_WD[(int)$dl->format('w')].', '.$dl->format('d.m.Y, H:i').' Uhr möglich. Bitte direkt die Rezeption kontaktieren'.($s['phone'] ? ' ('.$s['phone'].')' : '').' – Rückruf anbieten.'];
    $u=th_db()->prepare("UPDATE hotel_bookings SET status='cancelled', updated_at=NOW() WHERE id=? AND account_id=? AND status=?"); $u->execute([(int)$b['id'],(int)$b['account_id'],$b['status']]);
    if($u->rowCount()!==1) return ['error'=>'conflict','hint'=>'Die Buchung wurde gerade geändert – bitte erneut abrufen.'];
    $b=hotel_by_id((int)$b['account_id'],(int)$b['id']); hotel_notify($b,$s,'cancelled_guest');
    return ['ok'=>true,'booking'=>hotel_public($b,$s),'message'=>'Buchung '.$b['code'].' wurde kostenfrei storniert.'.($b['email'] ? (hotel_silent((int)$b['account_id'],(string)$b['channel']) ? HOTEL_DEMO_NOMAIL : ' Bestätigung per E-Mail.') : '')];
}
/** Rezeption: confirm | decline | checkin (room_no) | checkout | cancel | noshow */
function hotel_booking_action(int $acc, int $id, string $action, string $roomNo='', string $by='studio'): array {
    $b=hotel_by_id($acc,$id); if(!$b) return ['error'=>'not_found','hint'=>'Buchung nicht gefunden.'];
    $s=hotel_settings($acc); $today=hotel_today($s); $cur=$b['status'];
    $map=['confirm'=>[['requested'],'confirmed'],'decline'=>[['requested'],'declined'],'checkin'=>[['confirmed','requested'],'checked_in'],'checkout'=>[['checked_in'],'checked_out'],'cancel'=>[['requested','confirmed'],'cancelled'],'noshow'=>[['confirmed','requested'],'no_show']];
    if(!isset($map[$action])) return ['error'=>'bad_action','hint'=>'Erlaubt: confirm, decline, checkin, checkout, cancel, noshow.'];
    [$allowed,$to]=$map[$action]; $room=null;
    if($cur===$to && !($action==='checkin' && hotel_clean_room($roomNo)!=='' && hotel_clean_room($roomNo)!==$b['room_no'])) return ['ok'=>true,'already'=>true,'booking'=>hotel_public($b,$s)];
    if($cur===$to && $action==='checkin') $allowed[]='checked_in';   // Zimmer nachträglich ändern
    if(!in_array($cur,$allowed,true)) return ['error'=>'bad_transition','status'=>$cur,'hint'=>'Nicht möglich im Status „'.(HOTEL_STATUS_LABELS[$cur] ?? $cur).'“.'];
    if($action==='checkin'){
        $room=hotel_clean_room($roomNo); if($room==='') return ['error'=>'room_no_required','hint'=>'Bitte die Zimmernummer für den Check-in eintragen.'];
        if($b['checkin']>$today) return ['error'=>'not_arrival_day','hint'=>'Check-in ist ab dem Anreisetag ('.hotel_human($b['checkin']).') möglich.'];
        if($b['checkout']<=$today) return ['error'=>'stay_over','hint'=>'Der Aufenthalt ist bereits vorbei (Abreise '.hotel_human($b['checkout']).').'];
        $o=th_db()->prepare("SELECT guest_name FROM hotel_bookings WHERE account_id=? AND status='checked_in' AND room_no=? AND id<>? LIMIT 1"); $o->execute([$acc,$room,$id]);
        if(($other=$o->fetchColumn())!==false) return ['error'=>'room_occupied','hint'=>'Zimmer '.$room.' ist noch belegt'.($acc>0 ? ' ('.$other.')' : '').'. Bitte zuerst auschecken oder eine andere Nummer wählen.'];   // Demo: kein Gastname
    }
    if($action==='noshow' && $b['checkin']>$today) return ['error'=>'too_early','hint'=>'No-Show erst ab dem Anreisetag.'];
    $u=th_db()->prepare("UPDATE hotel_bookings SET status=?, room_no=COALESCE(?,room_no), updated_at=NOW() WHERE id=? AND account_id=? AND status=?"); $u->execute([$to,$room,$id,$acc,$cur]);
    if($u->rowCount()!==1) return ['error'=>'conflict','hint'=>'Die Buchung wurde gerade geändert – bitte neu laden.'];
    $b=hotel_by_id($acc,$id);
    if(in_array($action,['confirm','decline','cancel'],true)) hotel_notify($b,$s,$action==='confirm' ? 'confirmed' : ($action==='decline' ? 'declined' : 'cancelled_hotel'));
    return ['ok'=>true,'booking'=>hotel_public($b,$s)];
}
/** Buchungen, deren Aufenthalt [from, to] berührt (optional Status-Filter). */
function hotel_bookings(int $acc, string $from, string $to, array $statuses=[]): array {
    hotel_bootstrap(); $s=hotel_settings($acc); $statuses=array_values(array_intersect($statuses,array_keys(HOTEL_STATUS_LABELS)));
    $sql="SELECT * FROM hotel_bookings WHERE account_id=? AND checkin <= ? AND checkout >= ?".($statuses ? " AND status IN (".implode(',',array_fill(0,count($statuses),'?')).")" : "")." ORDER BY checkin, id LIMIT 500";
    $st=th_db()->prepare($sql); $st->execute(array_merge([$acc,$to,$from],$statuses));
    return array_map(fn($b)=>hotel_public($b,$s),$st->fetchAll(PDO::FETCH_ASSOC));
}

/* ======================================================================= Gästewünsche */
function hotel_kind(string $k): string {
    $k=mb_strtolower(trim($k)); if(isset(HOTEL_KINDS[$k])) return $k;
    $map=['wakeup'=>['weck','wake','alarm'],'taxi'=>['taxi','cab','transfer','shuttle'],'late_checkout'=>['late','spät','später aus','verlänger'],
          'maintenance'=>['technik','defekt','kaputt','reparatur','heizung','klima','licht','dusche','toilette','wc','fernseh','tv','wlan','wifi','safe','tür','fenster','strom'],
          'housekeeping'=>['housekeep','reinig','putz','sauber','clean','bettwäsche','müll','staub'],'roomservice'=>['room service','roomservice','zimmerservice','essen','getränk','minibar','snack','frühstück aufs'],
          'amenities'=>['handt','towel','kissen','decke','bademantel','zahnbürst','föhn','fön','shampoo','babybett','bügel','ladekabel','adapter','amenit','extra'],'info'=>['info','frage','auskunft','question'],
          'other'=>['beschwer','complaint','lärm','laut','sonstig','other','rückruf']];
    foreach($map as $kind=>$words) foreach($words as $w) if(str_contains($k,$w)) return $kind;
    return 'other';
}
function hotel_request_public(array $r, array $s): array {
    $tz=hotel_tz($s); $c=th_dbtime($r['created_at'] ?? null,$tz); $u=th_dbtime($r['updated_at'] ?? null,$tz); $due=$r['due_at'] ? new DateTimeImmutable($r['due_at'],$tz) : null;
    return ['id'=>(int)$r['id'],'room_no'=>(string)$r['room_no'],'guest_name'=>(string)$r['guest_name'],'kind'=>$r['kind'],'kind_label'=>HOTEL_KINDS[$r['kind']] ?? $r['kind'],'details'=>(string)$r['details'],
        'due_at'=>$due ? $due->format('Y-m-d H:i') : null,'due_human'=>$due ? hotel_when_human($due->format('Y-m-d H:i'),$tz).' Uhr' : null,'due_iso'=>$due ? $due->format(DATE_ATOM) : null,
        'status'=>$r['status'],'status_label'=>HOTEL_REQ_LABELS[$r['status']] ?? $r['status'],'channel'=>$r['channel'],'contact'=>(string)$r['contact'],'ref'=>(string)($r['ref'] ?? ''),
        'ride_url'=>!empty($r['ref']) ? 'https://chat-help.com/chat/ride.php?c='.rawurlencode((string)$r['ref']) : null,
        'created'=>$c ? $c->format('H:i') : '','created_day'=>$c ? $c->format('d.m.') : '','created_iso'=>$c ? $c->format(DATE_ATOM) : null,'updated_iso'=>$u ? $u->format(DATE_ATOM) : null];
}
/**
 * Gästewunsch anlegen (Werkzeug create_guest_request, Studio „Wunsch erfassen“): room_no (Pflicht außer Auskunft/Sonstiges), kind, details,
 * due_time (Pflicht bei Weckruf/Taxi), guest_name?, contact?. Art „taxi“ läuft über hotel_order_taxi() – mit aktivem Taxi-Modul wird die
 * Fahrt dort gebucht (Fahrtcode am Wunsch), sonst entsteht ein Taxi-Auftrag für die Rezeption. Ziel = destination bzw. details.
 */
function hotel_request_create(int $acc, array $d, string $channel, string $from=''): array {
    $d=hotel_scalars($d);
    if(hotel_kind((string)($d['kind'] ?? ''))!=='taxi') return hotel_request_insert($acc,$d,$channel,$from);
    $dest=trim((string)($d['destination'] ?? '')); if($dest==='') $dest=trim(preg_replace('/^\s*(?:(?:ein|einen|bitte)\s+)*(?:taxi|cab|transfer|shuttle)\b[\s:,-]*(?:(?:zum|zur|zu|nach|in die|in den|ins|an den|an die|to)\s+)?/iu','',(string)($d['details'] ?? '')));
    return hotel_order_taxi($acc,['room_no'=>$d['room_no'] ?? '','guest_name'=>$d['guest_name'] ?? '','pickup_time'=>$d['pickup_time'] ?? ($d['due_time'] ?? ''),'destination'=>$dest,
        'passengers'=>$d['passengers'] ?? 1,'phone'=>$d['phone'] ?? ($d['contact'] ?? '')],$channel,$from);
}
/** Wunsch speichern (ohne Taxi-Weiterleitung) – $status/$ref für den Taxi-Auftrag aus hotel_order_taxi(). */
function hotel_request_insert(int $acc, array $d, string $channel, string $from='', string $status='open', string $ref=''): array {
    $s=hotel_settings($acc); $tz=hotel_tz($s); $channel=mb_substr(preg_replace('/[^a-z_-]/','',strtolower($channel)) ?: 'chat',0,12);
    $kindRaw=mb_strtolower(trim((string)($d['kind'] ?? ''))); $kind=hotel_kind($kindRaw); $room=hotel_clean_room($d['room_no'] ?? ''); if(in_array(mb_strtolower($room),['unbekannt','keine','none','na','n a','-'],true)) $room='';
    $details=mb_substr(trim(preg_replace('/\s+/u',' ',(string)($d['details'] ?? ''))),0,500);
    if($room==='' && !in_array($kind,['info','other'],true)) return ['error'=>'room_no_required','hint'=>'Bitte zuerst die Zimmernummer erfragen.'];
    if($kind==='other' && !in_array($kindRaw,['other','sonstiges','beschwerde','complaint'],true) && $details!==''){ $k2=hotel_kind($details); if($k2!=='other' && $k2!=='taxi') $kind=$k2; }   // unbekannte Art → aus dem Text ableiten
    if($details==='' && $kind==='wakeup') $details='Weckruf';
    if($details==='') return ['error'=>'details_required','hint'=>'Was genau wird gewünscht?'];
    $due=null; $dueRaw=trim((string)($d['due_time'] ?? ''));
    if($dueRaw!==''){ $w=hotel_parse_when($dueRaw,$tz,$kind==='taxi'); if(isset($w['error'])) return $w; $due=$w['at']; }
    if($due===null && in_array($kind,['wakeup','taxi'],true)) return ['error'=>'due_time_required','hint'=>$kind==='wakeup' ? 'Um wie viel Uhr soll geweckt werden (und an welchem Tag)?' : 'Wann soll das Taxi da sein?'];
    $name=mb_substr(trim((string)($d['guest_name'] ?? '')),0,120); $given=$name; $bookingId=null;
    if($room!==''){ $h=th_db()->prepare("SELECT id, guest_name FROM hotel_bookings WHERE account_id=? AND status='checked_in' AND room_no=? ORDER BY id DESC LIMIT 1"); $h->execute([$acc,$room]);
        if($row=$h->fetch(PDO::FETCH_ASSOC)){ $bookingId=(int)$row['id']; if($name==='') $name=(string)$row['guest_name']; } }
    $from=preg_replace('/\D/','',$from); $contact=mb_substr(trim(preg_replace('/[^0-9+ ()\/-]/','',(string)($d['contact'] ?? $d['phone'] ?? ''))),0,40);
    if($contact==='' && $from!=='' && in_array($channel,['whatsapp','phone'],true)) $contact='+'.$from;
    $status=isset(HOTEL_REQ_LABELS[$status]) ? $status : 'open';
    th_db()->prepare("INSERT INTO hotel_requests(account_id,room_no,guest_name,kind,details,due_at,status,channel,contact,ref,booking_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
      ->execute([$acc,$room,$name,$kind,$details,$due,$status,$channel,$contact,mb_substr($ref,0,40),$bookingId]);
    $id=(int)th_db()->lastInsertId(); $st=th_db()->prepare("SELECT * FROM hotel_requests WHERE id=? AND account_id=?"); $st->execute([$id,$acc]); $pub=hotel_request_public($st->fetch(PDO::FETCH_ASSOC),$s);
    if(in_array($kind,['other','maintenance'],true)) hotel_notify_request($pub,$s,$acc);
    $pub['guest_name']=$given;   // Name aus der Zimmerbelegung nie an den Anfragenden zurückgeben (Datenschutz) – Board/Studio sehen ihn
    $what=$kind==='wakeup' ? 'Weckruf'.($room!=='' ? ' für Zimmer '.$room : '').' '.$pub['due_human'].' eingetragen.' : (HOTEL_KINDS[$kind].($room!=='' ? ' für Zimmer '.$room : '').' an die Rezeption übermittelt'.($pub['due_human'] ? ' (für '.$pub['due_human'].')' : '').'.');
    return ['ok'=>true,'request'=>$pub,'message'=>$what];
}
function hotel_request_set_status(int $acc, int $id, string $status): array {
    if(!isset(HOTEL_REQ_LABELS[$status])) return ['error'=>'bad_status','hint'=>'Erlaubt: open, in_progress, done, cancelled.'];
    hotel_bootstrap(); $u=th_db()->prepare("UPDATE hotel_requests SET status=?, updated_at=NOW() WHERE id=? AND account_id=?"); $u->execute([$status,$id,$acc]);
    $st=th_db()->prepare("SELECT * FROM hotel_requests WHERE id=? AND account_id=?"); $st->execute([$id,$acc]); $r=$st->fetch(PDO::FETCH_ASSOC);
    if(!$r) return ['error'=>'not_found','hint'=>'Wunsch nicht gefunden.'];
    return ['ok'=>true,'request'=>hotel_request_public($r,hotel_settings($acc))];
}
/** scope: open (offen + in Arbeit) · board (dazu Erledigtes der letzten 12 h) · all (14 Tage) */
function hotel_requests(int $acc, string $scope='open'): array {
    hotel_bootstrap(); $s=hotel_settings($acc);
    $q=['open'=>"SELECT * FROM hotel_requests WHERE account_id=? AND status IN ('open','in_progress') ORDER BY (due_at IS NULL), due_at, id",
        'board'=>"SELECT * FROM hotel_requests WHERE account_id=? AND (status IN ('open','in_progress') OR COALESCE(updated_at,created_at) > (NOW() - INTERVAL 12 HOUR)) ORDER BY FIELD(status,'open','in_progress','done','cancelled'), (due_at IS NULL), due_at, id DESC LIMIT 150",
        'all'=>"SELECT * FROM hotel_requests WHERE account_id=? AND created_at > (NOW() - INTERVAL 14 DAY) ORDER BY id DESC LIMIT 300"][$scope] ?? null;
    if(!$q) return []; $st=th_db()->prepare($q); $st->execute([$acc]);
    return array_map(fn($r)=>hotel_request_public($r,$s),$st->fetchAll(PDO::FETCH_ASSOC));
}
/** Taxi-Modul desselben Kontos verfügbar? (lädt ride-lib.php bei Bedarf) */
function hotel_ride_available(int $acc): bool {
    if($acc<=0) return false;   // Demo-Hotel (Hamburg) ≠ Demo-Taxi (Köln)
    if(!function_exists('ride_book') && is_file(__DIR__.'/ride-lib.php')){ try{ require_once __DIR__.'/ride-lib.php'; }catch(Throwable $e){ error_log('CH hotel: ride-lib '.$e->getMessage()); } }
    try{ return function_exists('ride_enabled') && function_exists('ride_book') && (bool)ride_enabled($acc); }catch(Throwable $e){ return false; }
}
/** Taxi für Gäste: über das Taxi-Modul (falls aktiv) buchen, sonst Auftrag an die Rezeption. Beides erscheint auf dem Board. */
function hotel_order_taxi(int $acc, array $in, string $channel, string $from=''): array {
    $in=hotel_scalars($in); $s=hotel_settings($acc); $tz=hotel_tz($s);
    $room=hotel_clean_room($in['room_no'] ?? ''); if(in_array(mb_strtolower($room),['unbekannt','keine','none','na','n a','-','lobby'],true)) $room='';
    $name=mb_substr(trim((string)($in['guest_name'] ?? $in['name'] ?? '')),0,120);
    if($name==='' && $room==='') return ['error'=>'name_required','hint'=>'Auf welchen Namen (oder für welches Zimmer) soll das Taxi bestellt werden?'];
    $rideName=$name!=='' ? $name : 'Hotelgast Zimmer '.$room;   // ohne Namensangabe nie den Namen aus der Zimmerbelegung an Fahrer/Fahrtseite geben (Datenschutz)
    $dest=mb_substr(trim((string)($in['destination'] ?? '')),0,200); if($dest==='') return ['error'=>'destination_required','hint'=>'Wohin soll die Fahrt gehen?'];
    $when=hotel_parse_when($in['pickup_time'] ?? '',$tz,true); if(isset($when['error'])) return $when['error']==='time_required' ? ['error'=>'time_required','hint'=>'Wann soll das Taxi da sein (z. B. „sofort“ oder „morgen 05:00“)?'] : $when;
    $pax=max(1,min(8,(int)($in['passengers'] ?? 1)));
    $phone=mb_substr(trim(preg_replace('/[^0-9+ ()\/-]/','',(string)($in['phone'] ?? ''))),0,40); $dg=preg_replace('/\D/','',$from);
    if($phone==='' && $dg!=='' && in_array($channel,['whatsapp','phone'],true)) $phone='+'.$dg;
    $ride=null; $rideErr='';
    if(hotel_ride_available($acc)){
        try{ $r=ride_book($acc,['name'=>$rideName,'phone'=>$phone ?: $s['phone'],'pickup'=>trim($s['name'].', '.$s['address'],', '),'destination'=>$dest,'time'=>$when['asap'] ? 'sofort' : substr($when['at'],0,16),
                 'passengers'=>$pax,'note'=>'Hotelgast'.($room!=='' ? ' · Zimmer '.$room : '').' · '.$s['name']],'hotel',$dg);
             if(is_array($r) && !empty($r['ok']) && !empty($r['ride']['code'])) $ride=$r['ride']; else $rideErr=is_array($r) ? (string)($r['hint'] ?? $r['error'] ?? '') : ''; }
        catch(Throwable $e){ error_log('CH hotel taxi: '.$e->getMessage()); $rideErr='Taxi-Zentrale nicht erreichbar'; }
    }
    $eta=$ride ? ((string)($ride['eta_text'] ?? '') ?: null) : null;
    $details='Taxi '.$when['human'].' nach '.$dest.' · '.$pax.' Pers.'   // Telefonnummer steht nur in contact (Demo-Board blendet sie dort aus)
        .($ride ? ' · ✓ bei der Taxi-Zentrale bestellt (Fahrt '.$ride['code'].($eta && $when['asap'] ? ', '.$eta : '').')' : ($rideErr!=='' ? ' · Taxi-Zentrale: '.$rideErr.' – bitte manuell bestellen' : ''));
    $req=hotel_request_insert($acc,['room_no'=>$room!=='' ? $room : 'Lobby','kind'=>'taxi','details'=>mb_substr($details,0,500),'due_time'=>$when['asap'] ? 'sofort' : substr($when['at'],0,16),'guest_name'=>$name,'contact'=>$phone],$channel,$from,$ride ? 'in_progress' : 'open',$ride ? (string)$ride['code'] : '');
    if(isset($req['error'])) return $req;
    if($ride) return ['ok'=>true,'via'=>'taxi_zentrale','request'=>$req['request'],'ride'=>['code'=>$ride['code'],'status'=>$ride['status_label'] ?? ($ride['status'] ?? ''),'when'=>$ride['when'] ?? $when['human'],'price'=>$ride['price'] ?? ($ride['price_text'] ?? null),
            'eta'=>$eta,'eta_min'=>isset($ride['eta_min']) && $ride['eta_min']!==null ? (int)$ride['eta_min'] : null,'track'=>$ride['track'] ?? null],
        'message'=>'Taxi ist bestellt ('.$when['human'].($eta && $when['asap'] ? ', Wartezeit '.$eta : '').', Fahrt '.$ride['code'].'), Abholung am Haupteingang. Die Rezeption ist informiert.'];
    return ['ok'=>true,'via'=>'reception','request'=>$req['request'],'message'=>'Taxi-Wunsch ('.$when['human'].' nach '.$dest.', '.$pax.' Pers.) ist an die Rezeption übermittelt – sie bestellt das Taxi und meldet sich bei Rückfragen.'];
}

/* ======================================================================= Benachrichtigungen */
function hotel_mail_html(array $s, string $title, string $intro, ?array $pub, string $extra=''): string {
    $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    $box=$pub ? '<div style="margin:18px 0;padding:18px;border-radius:16px;background:#f6ecd3;color:#1a1407"><b style="font-size:17px">'.$e($pub['room']).'</b><br>'
        .$e($pub['checkin_long']).' (ab '.$e($pub['checkin_from']).' Uhr) → '.$e($pub['checkout_long']).' (bis '.$e($pub['checkout_until']).' Uhr)<br>'
        .(int)$pub['nights'].' '.($pub['nights']===1 ? 'Nacht' : 'Nächte').' · '.(int)$pub['guests'].' '.($pub['guests']===1 ? 'Gast' : 'Gäste').' · Frühstück: '.$e($pub['breakfast_text']).'<br><b>Gesamt '.$e($pub['total']).'</b>'
        .'<br><span style="color:#6b7083;font-size:13px">Buchungscode '.$e($pub['code']).' · '.$e($pub['name']).'</span></div>' : '';
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:30px;border:1px solid #e6e8ef;border-radius:20px;color:#0f1222">'
        .'<div style="font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:#b8862b;font-weight:700">'.$e($s['name'] ?: 'Hotel').'</div><h2 style="margin:10px 0 12px;font-size:22px">'.$title.'</h2>'
        .$intro.$box.$extra.($pub ? '<p style="font-size:13px;color:#6b7083">Ihre Buchung ansehen, in den Kalender eintragen oder stornieren: <a href="'.$e($pub['stay_url']).'" style="color:#b8862b">'.$e($pub['stay_url']).'</a></p>' : '')
        .'<p style="font-size:12.5px;color:#9094a6;margin-top:18px">'.$e($s['name']).($s['address'] ? ' · '.$e($s['address']) : '').($s['phone'] ? ' · Tel. '.$e($s['phone']) : '').'</p></div>';
}
/**
 * WhatsApp (Kanal whatsapp) bzw. SMS (Kanal phone) an den Gast – über die Zugänge des Kontos (eigener Twilio-Account hat Vorrang).
 * WhatsApp-Freitext ist nur im 24-h-Fenster zustellbar: Fenster zu oder Versand fehlgeschlagen → SMS an die Nummer der Buchung.
 */
function hotel_msg_guest(array $b, string $text): bool {
    $acc=(int)$b['account_id']; if($acc<=0 || hotel_silent($acc,(string)$b['channel']) || !is_file(__DIR__.'/order-lib.php')) return false; require_once __DIR__.'/order-lib.php';
    $sms=function() use($b,$text,$acc): bool { $to=(string)$b['phone'] ?: ((string)$b['wa_from']!=='' ? '+'.preg_replace('/\D/','',(string)$b['wa_from']) : ''); return $to!=='' && function_exists('ord_sms_send') && ord_sms_send($to,$text,$acc); };
    try{
        if($b['channel']==='whatsapp' && !empty($b['wa_from'])){
            $os=ord_settings($acc); $open=true;
            if(function_exists('ord_wa_window_open')){ try{ $open=ord_wa_window_open($acc,(string)$b['wa_from']); }catch(Throwable $e){ $open=true; } }
            if($open && function_exists('ord_wa_send') && ord_wa_send($os,(string)$b['wa_from'],$text)) return true;
            return $sms();
        }
        if($b['channel']==='phone' && (string)$b['phone']!=='') return $sms();
    }catch(Throwable $e){ error_log('CH hotel msg: '.$e->getMessage()); }
    return false;
}
/** Ereignisse: new · confirmed · declined · cancelled_guest · cancelled_hotel. Konto 0 und Demo-Kanäle: nie. */
function hotel_notify(array $b, array $s, string $event): void {
    $acc=(int)$b['account_id']; if(hotel_silent($acc,(string)$b['channel'])) return;
    $pub=hotel_public($b,$s); $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); $hello='<p>Guten Tag '.$e($b['guest_name']).',</p>';
    $hotelTo=$s['notify_email'] ?: hotel_owner_email($acc); $hn=$s['name'] ?: 'Hotel'; $dates=hotel_human($b['checkin']).' – '.hotel_human($b['checkout']);
    $contact='<p><b>'.$e($b['guest_name']).'</b>'.($b['phone'] ? ' · <a href="tel:'.$e(preg_replace('/[^0-9+]/','',$b['phone'])).'">'.$e($b['phone']).'</a>' : '').($b['email'] ? ' · <a href="mailto:'.$e($b['email']).'">'.$e($b['email']).'</a>' : '')
        .'<br>Kanal: '.$e($b['channel']).($b['note'] ? '<br>Notiz: '.$e($b['note']) : '').'</p>';
    try{
        if($event==='new'){
            if($b['email']) th_mail($b['email'],($b['status']==='confirmed' ? 'Buchungsbestätigung' : 'Ihre Anfrage').' · '.$hn.' · '.$dates,
                $b['status']==='confirmed' ? hotel_mail_html($s,'Ihre Buchung ist bestätigt ✓',$hello.'<p>vielen Dank für Ihre Buchung – wir freuen uns auf Ihren Besuch.</p>',$pub)
                                           : hotel_mail_html($s,'Ihre Anfrage ist eingegangen',$hello.'<p>vielen Dank für Ihre Anfrage. Die Rezeption prüft sie und bestätigt Ihnen in Kürze per E-Mail. Das Zimmer ist für Sie vorgemerkt.</p>',$pub));
            if($hotelTo) th_mail($hotelTo,($b['status']==='confirmed' ? '🛎️ Neue Buchung ' : '🛎️ Neue Buchungsanfrage ').$b['code'].' · '.$dates.' · '.$pub['total'],
                hotel_mail_html($s,$b['status']==='confirmed' ? 'Neue Buchung' : 'Neue Buchungsanfrage – bitte bestätigen',$contact,$pub,$b['status']==='requested' ? '<p style="font-size:14px">Bestätigen oder ablehnen: im Studio (Hotel-Rezeption) oder auf dem Rezeptions-Board.</p>' : ''));
        } elseif($event==='confirmed'){
            if($b['email']) th_mail($b['email'],'Buchungsbestätigung · '.$hn.' · '.$dates,hotel_mail_html($s,'Ihre Buchung ist bestätigt ✓',$hello.'<p>gute Nachrichten: Die Rezeption hat Ihre Anfrage bestätigt. Wir freuen uns auf Sie!</p>',$pub));
            hotel_msg_guest($b,$hn.': Ihre Buchung '.$b['code'].' ('.$dates.', '.$b['room_name'].') ist bestätigt. Wir freuen uns auf Sie! '.$pub['stay_url']);
        } elseif($event==='declined'){
            if($b['email']) th_mail($b['email'],'Ihre Anfrage · '.$hn.' · '.$dates,hotel_mail_html($s,'Ihre Anfrage – leider ausgebucht',$hello.'<p>vielen Dank für Ihr Interesse. Leider können wir Ihre Anfrage für diesen Zeitraum nicht bestätigen. Gern finden wir einen anderen Termin für Sie'.($s['phone'] ? ' – rufen Sie uns an: '.$e($s['phone']) : '').'.</p>',$pub));
            hotel_msg_guest($b,$hn.': Leider können wir Ihre Anfrage '.$b['code'].' ('.$dates.') nicht bestätigen. Gern finden wir einen anderen Termin'.($s['phone'] ? ' – Tel. '.$s['phone'] : '').'.');
        } elseif($event==='cancelled_guest' || $event==='cancelled_hotel'){
            if($b['email']) th_mail($b['email'],'Stornierung · '.$hn.' · '.$dates,hotel_mail_html($s,'Ihre Buchung wurde storniert',$hello.'<p>'.($event==='cancelled_guest' ? 'wir bestätigen die kostenfreie Stornierung Ihrer Buchung.' : 'Ihre Buchung wurde von der Rezeption storniert. Bei Fragen sind wir gern für Sie da.').'</p>',$pub));
            if($event==='cancelled_guest' && $hotelTo) th_mail($hotelTo,'Stornierung durch Gast: '.$b['code'].' · '.$dates,hotel_mail_html($s,'Stornierung durch den Gast',$contact,$pub));
            if($event==='cancelled_hotel') hotel_msg_guest($b,$hn.': Ihre Buchung '.$b['code'].' ('.$dates.') wurde storniert. Bei Fragen: '.($s['phone'] ?: $pub['stay_url']));
        }
    }catch(Throwable $ex){ error_log('CH hotel_notify: '.$ex->getMessage()); }
}
function hotel_notify_request(array $r, array $s, int $acc): void {
    if(hotel_silent($acc,(string)$r['channel'])) return; $to=$s['notify_email'] ?: hotel_owner_email($acc); if(!$to) return; $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    th_mail($to,'🛎️ '.$r['kind_label'].($r['room_no']!=='' ? ' · Zimmer '.$r['room_no'] : '').' · '.mb_substr($r['details'],0,60),
        hotel_mail_html($s,$e($r['kind_label']).($r['room_no']!=='' ? ' · Zimmer '.$e($r['room_no']) : ''),'<p style="font-size:16px">'.$e($r['details']).'</p><p>'.($r['guest_name'] ? $e($r['guest_name']).' · ' : '').($r['contact'] ? $e($r['contact']).' · ' : '').'Kanal: '.$e($r['channel']).' · '.$e($r['created']).' Uhr</p><p style="font-size:14px">Bitte kurzfristig kümmern bzw. den Gast zurückrufen. Status auf dem Rezeptions-Board setzen.</p>',null));
}
/** Kalender: ganztägig von Anreise bis inkl. Abreisetag (DTEND = Abreise + 1, exklusiv). */
function hotel_ics(array $b, array $s): string {
    $esc=fn($t)=>str_replace(['\\',',',';',"\r\n","\n"],['\\\\','\,','\;','\n','\n'],(string)$t);
    $fold=function(string $l): string { $out=''; while(strlen($l)>74){ $cut=74; while($cut>1 && (ord($l[$cut]) & 0xC0)===0x80) $cut--; $out.=substr($l,0,$cut)."\r\n "; $l=substr($l,$cut); } return $out.$l."\r\n"; };
    $st=['requested'=>'TENTATIVE','confirmed'=>'CONFIRMED','checked_in'=>'CONFIRMED','checked_out'=>'CONFIRMED'][$b['status']] ?? 'CANCELLED';
    $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//ChatHelp//Hotel//DE','CALSCALE:GREGORIAN','METHOD:PUBLISH','BEGIN:VEVENT','UID:hotel-'.$b['code'].'@chat-help.com','DTSTAMP:'.gmdate('Ymd\THis\Z'),
        'DTSTART;VALUE=DATE:'.hotel_day($b['checkin'])->format('Ymd'),'DTEND;VALUE=DATE:'.hotel_day($b['checkout'])->modify('+1 day')->format('Ymd'),
        'SUMMARY:'.$esc(($s['name'] ?: 'Hotel').' – '.$b['room_name']),'LOCATION:'.$esc($s['address']),
        'DESCRIPTION:'.$esc('Buchungscode '.$b['code'].' · '.(HOTEL_STATUS_LABELS[$b['status']] ?? $b['status'])."\nAnreise ".hotel_human($b['checkin']).' ab '.$s['checkin_from'].' Uhr, Abreise '.hotel_human($b['checkout']).' bis '.$s['checkout_until'].' Uhr'."\n".(int)$b['guests'].' Gäste · Gesamt '.hotel_eur((int)$b['total_cents']).($s['phone'] ? "\nRezeption: ".$s['phone'] : '')."\n".hotel_stay_url($b['code'])),
        'URL:'.hotel_stay_url($b['code']),'STATUS:'.$st,'TRANSP:TRANSPARENT','END:VEVENT','END:VCALENDAR'];
    return implode('',array_map($fold,$lines));
}

/* ======================================================================= Rezeption: Kennzahlen, Board, Links */
function hotel_stats(int $acc): array {
    hotel_bootstrap(); $t=hotel_today(hotel_settings($acc)); $p=th_db();
    $one=function(string $sql,array $a) use($p): int { $s=$p->prepare($sql); $s->execute($a); return (int)$s->fetchColumn(); };
    return ['today'=>$t,
        'arrivals_today'=>$one("SELECT COUNT(*) FROM hotel_bookings WHERE account_id=? AND checkin=? AND status IN ('confirmed','checked_in')",[$acc,$t]),
        'arrivals_open'=>$one("SELECT COUNT(*) FROM hotel_bookings WHERE account_id=? AND checkin<=? AND checkout>? AND status='confirmed'",[$acc,$t,$t]),
        'departures_today'=>$one("SELECT COUNT(*) FROM hotel_bookings WHERE account_id=? AND checkout=? AND status IN ('checked_in','checked_out')",[$acc,$t]),
        'departures_open'=>$one("SELECT COUNT(*) FROM hotel_bookings WHERE account_id=? AND checkout<=? AND status='checked_in'",[$acc,$t]),
        'in_house'=>$one("SELECT COUNT(*) FROM hotel_bookings WHERE account_id=? AND status='checked_in'",[$acc]),
        'guests_in_house'=>$one("SELECT COALESCE(SUM(guests),0) FROM hotel_bookings WHERE account_id=? AND status='checked_in'",[$acc]),
        'open_requests'=>$one("SELECT COUNT(*) FROM hotel_requests WHERE account_id=? AND status IN ('open','in_progress')",[$acc]),
        'pending_requests'=>$one("SELECT COUNT(*) FROM hotel_bookings WHERE account_id=? AND status='requested' AND checkout>?",[$acc,$t])];
}
/** „Dr. Anna Weber“ → „Anna W.“ */
function hotel_mask_name(string $n): string {
    $parts=array_values(array_filter(preg_split('/\s+/u',trim($n)) ?: [],fn($w)=>$w!=='' && !in_array(mb_strtolower(rtrim($w,'.')),['dr','prof','herr','frau','mr','mrs','ms'],true)));
    $first=array_shift($parts) ?? ''; $last=$parts ? mb_substr((string)end($parts),0,1).'.' : ''; return trim($first.' '.$last);
}
function hotel_mask_phone(string $p): string { $d=preg_replace('/\D/','',$p); return $d!=='' ? '••• '.substr($d,-2) : ''; }
/** Freitext für die öffentliche Demo: Telefonnummern und E-Mail-Adressen unkenntlich machen. */
function hotel_mask_text(string $t): string {
    $t=preg_replace('/[^\s@<>]+@[^\s@<>]+\.[a-z]{2,}/iu','(E-Mail ausgeblendet)',$t);
    return (string)preg_replace_callback('/(?<![\d.])(?:\+|0)\d[\d \/()-]{4,}\d/u',fn($m)=>strlen(preg_replace('/\D/','',$m[0]))>=7 ? hotel_mask_phone($m[0]) : $m[0],$t);   // nur Rufnummern (+… / 0…), keine Daten/Uhrzeiten
}
/**
 * Öffentliche Demo (Board ohne Login): Name gekürzt, Telefon gekürzt, E-Mail/Notiz ausgeblendet – und weder Buchungscode noch
 * Gästeseiten-/Kalender-Link (mit dem Code ließe sich auf stay.php alles einsehen und stornieren). Das Board arbeitet nur mit id.
 */
function hotel_mask(array $b): array {
    $b['name']=hotel_mask_name((string)($b['name'] ?? '')); $b['phone']=hotel_mask_phone((string)($b['phone'] ?? '')); $b['email']=''; $b['note']=($b['note'] ?? '')!=='' ? '(Notiz ausgeblendet)' : '';
    $b['code']=''; unset($b['stay_url'],$b['ics_url']);
    return $b;
}
/** Gästewunsch für die öffentliche Demo: Gastname gekürzt, Kontakt gekürzt, Nummern/E-Mails im Text ausgeblendet. */
function hotel_mask_request(array $r): array {
    $r['guest_name']=hotel_mask_name((string)($r['guest_name'] ?? '')); $r['contact']=hotel_mask_phone((string)($r['contact'] ?? '')); $r['details']=hotel_mask_text((string)($r['details'] ?? ''));
    return $r;
}
/** Daten für das Rezeptions-Board (hotel-api.php?a=board). $mask = Demo: Kontaktdaten ausblenden. */
function hotel_board(int $acc, bool $mask=false): array {
    $s=hotel_settings($acc); $t=hotel_today($s); $p=th_db(); $now=hotel_now($s);
    $q=function(string $sql,array $a) use($p,$s,$mask): array { $st=$p->prepare($sql); $st->execute($a); return array_map(function($b) use($s,$mask){ $x=hotel_public($b,$s); return $mask ? hotel_mask($x) : $x; },$st->fetchAll(PDO::FETCH_ASSOC)); };
    $reqs=hotel_requests($acc,'board'); if($mask) $reqs=array_map('hotel_mask_request',$reqs);
    return ['hotel'=>['name'=>$s['name'],'phone'=>$s['phone'],'checkin_from'=>$s['checkin_from'],'checkout_until'=>$s['checkout_until']],'demo'=>$acc===0,
        'today'=>$t,'today_human'=>hotel_human($t,'long'),'now'=>$now->format('H:i'),'now_iso'=>$now->format(DATE_ATOM),
        'arrivals'=>$q("SELECT * FROM hotel_bookings WHERE account_id=? AND ((checkin=? AND status IN ('requested','confirmed','checked_in')) OR (checkin<? AND checkout>? AND status='confirmed')) ORDER BY FIELD(status,'confirmed','requested','checked_in'), checkin, guest_name",[$acc,$t,$t,$t]),
        'departures'=>$q("SELECT * FROM hotel_bookings WHERE account_id=? AND ((checkout<=? AND status='checked_in') OR (checkout=? AND status='checked_out')) ORDER BY FIELD(status,'checked_in','checked_out'), room_no",[$acc,$t,$t]),
        'in_house'=>$q("SELECT * FROM hotel_bookings WHERE account_id=? AND status='checked_in' ORDER BY LENGTH(room_no), room_no",[$acc]),
        'pending'=>$q("SELECT * FROM hotel_bookings WHERE account_id=? AND status='requested' AND checkout>? ORDER BY checkin, id LIMIT 60",[$acc,$t]),
        'requests'=>$reqs,'stats'=>hotel_stats($acc),'kinds'=>HOTEL_KINDS];
}
/** Board-Token → Konto-ID ('demo' → 0), sonst null. */
function hotel_account_by_board(string $t): ?int {
    if($t==='demo') return 0; if(!preg_match('/^[a-f0-9]{24}$/',$t)) return null; hotel_bootstrap();
    $s=th_db()->prepare("SELECT account_id FROM hotel_settings WHERE board_token=?"); $s->execute([$t]); $a=$s->fetchColumn(); return $a===false ? null : (int)$a;
}
function hotel_links(int $acc): array {
    if($acc===0) return ['board'=>'https://chat-help.com/chat/hotel-board.php?t=demo','stay_base'=>'https://chat-help.com/chat/stay.php?c='];
    $s=hotel_settings($acc);
    if(!$s['board_token']){ th_db()->prepare("INSERT INTO hotel_settings(account_id,board_token) VALUES(?,?) ON DUPLICATE KEY UPDATE board_token=COALESCE(board_token,VALUES(board_token))")->execute([$acc,bin2hex(random_bytes(12))]); $s=hotel_settings($acc); }
    return ['board'=>'https://chat-help.com/chat/hotel-board.php?t='.$s['board_token'],'stay_base'=>'https://chat-help.com/chat/stay.php?c='];
}
/** Demo-Board lebendig halten: fiktive Anreisen, Abreisen, Gäste im Haus und Wünsche für heute (einmal je Tag, Konto 0). */
function hotel_demo_seed(): void {
    hotel_bootstrap(); $s=hotel_settings(0); $t=hotel_today($s); $p=th_db();
    $seeded=function() use($p,$t): bool { $c=$p->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE account_id=0 AND channel='seed' AND checkin=?"); $c->execute([$t]); return (int)$c->fetchColumn()>0; };
    if($seeded()) return;
    if(!th_lock('hotel_demo_seed',5)) return;   // parallele Board-Abrufe: nur einer legt an, die anderen sehen danach die Daten
    try{ if(!$seeded()) hotel_demo_seed_rows($p,$s,$t); } finally { th_unlock('hotel_demo_seed'); }
}
function hotel_demo_seed_rows(PDO $p, array $s, string $t): void {
    $p->exec("DELETE FROM hotel_bookings WHERE account_id=0 AND channel='seed'"); $p->exec("DELETE FROM hotel_requests WHERE account_id=0 AND channel='seed'");
    $rooms=[]; foreach(hotel_rooms(0) as $r) $rooms[$r['id']]=$r; $day=fn(int $o)=>hotel_day($t)->modify(($o>=0 ? '+' : '').$o.' days')->format('Y-m-d');
    /* [Kategorie, Anreise relativ zu heute, Nächte, Gäste, Name, Telefon, E-Mail, Status, Zimmer, Frühstück, Notiz] – fiktive Gäste */
    $rows=[[4,0,3,4,'Familie Petersen','+49 151 00000011','','confirmed','',1,'Anreise ca. 17 Uhr, Babybett bitte'],[1,0,1,1,'Dr. Anna Weber','+49 151 00000012','anna.weber@example.com','confirmed','',0,''],
           [3,-2,3,2,'James Holloway','+44 7700 900123','','checked_in','401',1,''],[2,-3,3,2,'Elif Kaya','+49 151 00000014','','checked_in','214',1,''],
           [2,-1,3,2,'Lea Schubert','+49 151 00000015','','checked_in','208',0,''],[2,7,2,2,'Sophie Lambert','+33 6 00 00 00 16','sophie.lambert@example.com','requested','',1,'Hochzeitstag – gern ein ruhiges Zimmer']];
    $ins=$p->prepare("INSERT INTO hotel_bookings(account_id,code,room_id,room_name,checkin,checkout,nights,guests,guest_name,phone,email,breakfast,total_cents,status,room_no,channel,note,price_json) VALUES(0,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'seed',?,?)");
    foreach($rows as [$rid,$off,$nights,$g,$name,$ph,$mail,$st,$no,$bf,$note]){ $room=$rooms[$rid]; $ci=$day($off); $co=$day($off+$nights);
        $q=hotel_quote($s,$room,$ci,$nights,$g,(bool)$bf); $ins->execute([hotel_code(),$rid,$room['name'],$ci,$co,$nights,$g,$name,$ph,$mail,$bf,$q['total_cents'],$st,$no,$note,json_encode($q,JSON_UNESCAPED_UNICODE)]); }
    $tz=hotel_tz($s); $tm=(new DateTimeImmutable('tomorrow',$tz));
    $rq=$p->prepare("INSERT INTO hotel_requests(account_id,room_no,guest_name,kind,details,due_at,status,channel,contact) VALUES(0,?,?,?,?,?,?,'seed','')");
    foreach([['214','Elif Kaya','amenities','2 zusätzliche Handtücher und ein Bademantel, bitte',null,'open'],['401','James Holloway','wakeup','Weckruf',$tm->setTime(6,30)->format('Y-m-d H:i:s'),'open'],
             ['401','James Holloway','taxi','Taxi zum Flughafen Hamburg · 2 Pers. · 2 Koffer',$tm->setTime(7,15)->format('Y-m-d H:i:s'),'open'],['214','Elif Kaya','late_checkout','Late Check-out bis 13:00 Uhr gewünscht',null,'in_progress'],
             ['208','Lea Schubert','roomservice','Kanne Pfefferminztee und Wasser still aufs Zimmer',null,'done']] as $r) $rq->execute($r);
}

/* ======================================================================= Agent: Infos, Werkzeuge, Hinweis */
function hotel_info(int $acc): array {
    $s=hotel_settings($acc); $now=hotel_now($s);
    $rooms=array_map(fn($r)=>['id'=>$r['id'],'name'=>$r['name'],'description'=>$r['description'],'capacity'=>$r['capacity'],'price_per_night'=>hotel_eur($r['price_cents']),
        'weekend_price_per_night'=>$r['weekend_cents']!==null ? hotel_eur($r['weekend_cents']).' (Fr+Sa)' : null,'price_from'=>hotel_eur(min($r['price_cents'],$r['weekend_cents'] ?? $r['price_cents'])),'amenities'=>$r['amenities']],hotel_rooms($acc));
    return ['hotel'=>$s['name'],'address'=>$s['address'],'phone'=>$s['phone'],'now'=>HOTEL_WDL[(int)$now->format('w')].', '.$now->format('d.m.Y H:i'),'checkin_from'=>$s['checkin_from'].' Uhr','checkout_until'=>$s['checkout_until'].' Uhr',
        'booking_mode'=>$s['auto_confirm'] ? 'Sofortbuchung (verbindlich)' : 'Anfrage – die Rezeption bestätigt in Kürze','rooms'=>$rooms,
        'breakfast'=>$s['breakfast_mode']==='included' ? 'im Zimmerpreis inklusive' : ($s['breakfast_mode']==='optional' ? 'optional, '.hotel_eur($s['breakfast_cents']).' pro Person und Nacht' : 'wird nicht angeboten'),
        'city_tax'=>$s['city_tax_pct']>0 ? number_format($s['city_tax_pct'],2,',','.').' % auf den Zimmerpreis (im Gesamtpreis enthalten)' : 'keine','min_nights'=>$s['min_nights'],'max_nights'=>$s['max_nights'],
        'cancel_policy'=>$s['cancel_hours']>0 ? 'Kostenfreie Stornierung bis '.$s['cancel_hours'].' Stunden vor Anreise (Anreisetag '.$s['checkin_from'].' Uhr); danach nur über die Rezeption.' : 'Kostenfreie Stornierung bis zum Anreisetag '.$s['checkin_from'].' Uhr.',
        'policies'=>$s['policies']];
}
function hotel_tools(): array {
    $date=['type'=>'string','description'=>'YYYY-MM-DD (bevorzugt) oder TT.MM.JJJJ, „morgen“, „übermorgen“'];
    return [
      ['name'=>'get_hotel_info','description'=>'Hotel-Infos: Zimmerkategorien mit Kapazität, Preisen und Ausstattung, Check-in/Check-out-Zeiten, Frühstück, City-Tax, Hausregeln (Hunde, Parken, Rauchen …), Stornobedingungen, Buchungsmodus.','input_schema'=>['type'=>'object','properties'=>(object)[]]],
      ['name'=>'check_room_availability','description'=>'Freie Zimmer und Gesamtpreise (inkl. City-Tax, Frühstück optional) für Anreise, Abreise und Personenzahl. Immer vor einem Buchungsangebot aufrufen – Preise und Verfügbarkeit nie raten.','input_schema'=>['type'=>'object','properties'=>['checkin'=>$date,'checkout'=>$date,'guests'=>['type'=>'integer','description'=>'Anzahl Personen in einem Zimmer'],'nights'=>['type'=>'integer','description'=>'Alternativ zur Abreise: Anzahl Nächte']],'required'=>['checkin','guests']]],
      ['name'=>'book_room','description'=>'Zimmer verbindlich buchen bzw. anfragen (je nach Buchungsmodus) – erst nach Zusammenfassung und ausdrücklichem Ja des Gastes. Ein Zimmer je Aufruf.','input_schema'=>['type'=>'object','properties'=>['room_id'=>['type'=>'integer'],'room_name'=>['type'=>'string'],'checkin'=>$date,'checkout'=>$date,'guests'=>['type'=>'integer'],'name'=>['type'=>'string','description'=>'Vor- und Nachname des Gastes'],'phone'=>['type'=>'string'],'email'=>['type'=>'string','description'=>'für die Bestätigung'],'breakfast'=>['type'=>'boolean','description'=>'Frühstück dazubuchen (nur wenn optional)'],'note'=>['type'=>'string','description'=>'Wünsche, z. B. Anreisezeit, Hund, Babybett']],'required'=>['checkin','checkout','guests','name']]],
      ['name'=>'get_booking','description'=>'Bestehende Buchung abrufen. Nur mit Buchungscode UND Name (oder Telefonnummer/E-Mail) des Gastes – ohne beides keine Auskunft.','input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string'],'name_or_phone'=>['type'=>'string']],'required'=>['code','name_or_phone']]],
      ['name'=>'cancel_booking','description'=>'Buchung stornieren (Code UND Name/Telefon) – nur nach ausdrücklicher Bestätigung des Gastes. Die kostenfreie Stornofrist prüft das Werkzeug.','input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string'],'name_or_phone'=>['type'=>'string']],'required'=>['code','name_or_phone']]],
      ['name'=>'create_guest_request','description'=>'Wunsch eines Gastes im Haus an die Rezeption: Handtücher, Reinigung, Room-Service, Weckruf, Defekt, Late Check-out, Beschwerde … Zimmernummer erfragen; Weckruf braucht eine Uhrzeit. Taxi bevorzugt über order_taxi_for_guest (kind taxi wird genauso gebucht: details = Ziel, due_time = Abholzeit).','input_schema'=>['type'=>'object','properties'=>['room_no'=>['type'=>'string','description'=>'Zimmernummer (Pflicht bei Gästen im Haus)'],'kind'=>['type'=>'string','enum'=>array_keys(HOTEL_KINDS)],'details'=>['type'=>'string','description'=>'Was genau, z. B. „2 zusätzliche Handtücher“'],'due_time'=>['type'=>'string','description'=>'Gewünschte Zeit, z. B. „06:30“, „morgen 6:30“, „2026-10-17 06:30“'],'guest_name'=>['type'=>'string']],'required'=>['room_no','kind','details']]],
      ['name'=>'order_taxi_for_guest','description'=>'Taxi für einen Hotelgast bestellen (über die Taxi-Zentrale, falls angebunden, sonst bestellt die Rezeption). Abholung am Hotel.','input_schema'=>['type'=>'object','properties'=>['room_no'=>['type'=>'string'],'guest_name'=>['type'=>'string'],'pickup_time'=>['type'=>'string','description'=>'„sofort“ oder Zeit, z. B. „morgen 05:00“'],'destination'=>['type'=>'string'],'passengers'=>['type'=>'integer'],'phone'=>['type'=>'string']],'required'=>['guest_name','pickup_time','destination']]],
    ];
}
function hotel_exec(int $acc, string $name, array $in, string $channel, string $from=''): array {
    $in=hotel_scalars($in);
    try{
        switch($name){
            case 'get_hotel_info': return hotel_info($acc);
            case 'check_room_availability': return hotel_availability($acc,$in['checkin'] ?? '',$in['checkout'] ?? '',(int)($in['guests'] ?? 0),$in['nights'] ?? null);
            case 'book_room': return hotel_book($acc,$in,$acc===0 ? 'demo' : $channel,$from);
            case 'get_booking':
                if(trim((string)($in['code'] ?? ''))==='' || trim((string)($in['name_or_phone'] ?? ''))==='') return ['error'=>'code_and_name_required','hint'=>'Bitte Buchungscode UND Namen (oder Telefonnummer) erfragen.'];
                $b=hotel_find_booking($acc,(string)$in['code'],(string)$in['name_or_phone']);
                if(!$b) return ['error'=>'not_found','hint'=>'Keine Buchung zu diesem Code und Namen gefunden. Bitte beides noch einmal prüfen – ohne Übereinstimmung keine Auskunft.'];
                $pub=hotel_public($b); unset($pub['id'],$pub['channel']); return ['ok'=>true,'booking'=>$pub];
            case 'cancel_booking':
                if(trim((string)($in['code'] ?? ''))==='' || trim((string)($in['name_or_phone'] ?? ''))==='') return ['error'=>'code_and_name_required','hint'=>'Bitte Buchungscode UND Namen (oder Telefonnummer) erfragen.'];
                $b=hotel_find_booking($acc,(string)$in['code'],(string)$in['name_or_phone']);
                if(!$b) return ['error'=>'not_found','hint'=>'Keine Buchung zu diesem Code und Namen gefunden.'];
                return hotel_guest_cancel($b,$channel);
            case 'create_guest_request': return hotel_request_create($acc,$in,$acc===0 ? 'demo' : $channel,$from);
            case 'order_taxi_for_guest': return hotel_order_taxi($acc,$in,$acc===0 ? 'demo' : $channel,$from);
        }
    }catch(Throwable $e){ error_log('CH hotel_exec '.$name.': '.$e->getMessage()); return ['error'=>'internal_error','hint'=>'Technischer Fehler – bitte die Rezeption anbieten'.(($ph=hotel_settings($acc)['phone'] ?? '') ? ' ('.$ph.')' : '').'.']; }
    return ['error'=>'unknown_tool','hint'=>'Dieses Werkzeug gibt es nicht.'];
}
function hotel_system_hint(int $acc, string $channel='chat'): string {
    $s=hotel_settings($acc); $now=hotel_now($s); $rooms=hotel_rooms($acc);
    $rt=$rooms ? implode('; ',array_map(fn($r)=>$r['name'].' (bis '.$r['capacity'].' Pers., ab '.hotel_eur(min($r['price_cents'],$r['weekend_cents'] ?? $r['price_cents'])).'/Nacht'.($r['weekend_cents']!==null ? ', Fr+Sa '.hotel_eur($r['weekend_cents']) : '').')',$rooms)) : 'noch keine hinterlegt';
    $bf=$s['breakfast_mode']==='included' ? 'im Preis inklusive' : ($s['breakfast_mode']==='optional' ? 'optional, '.hotel_eur($s['breakfast_cents']).' pro Person und Nacht (nur auf Wunsch dazubuchen)' : 'wird nicht angeboten');
    $ask=$s['auto_confirm'] ? '„Soll ich das so verbindlich buchen?“' : '„Soll ich das so anfragen? Es ist eine Anfrage – die Rezeption bestätigt in Kürze.“';
    $h="\n\nHOTEL-REZEPTION (aktiv) für ".($s['name'] ?: 'das Hotel').($s['address'] ? ', '.$s['address'] : '').($s['phone'] ? ', Rezeption Tel. '.$s['phone'] : '').". Jetzt: ".HOTEL_WDL[(int)$now->format('w')].', '.$now->format('d.m.Y, H:i')." Uhr (".$s['tz'].").
Check-in ab ".$s['checkin_from']." Uhr, Check-out bis ".$s['checkout_until']." Uhr. Zimmer: ".$rt.". Frühstück: ".$bf.". City-Tax: ".($s['city_tax_pct']>0 ? number_format($s['city_tax_pct'],2,',','.').' % auf den Zimmerpreis (in den Gesamtpreisen enthalten)' : 'keine').". Aufenthalt ".$s['min_nights'].'–'.$s['max_nights']." Nächte. Storno: ".($s['cancel_hours']>0 ? 'kostenfrei bis '.$s['cancel_hours'].' Stunden vor Anreise (Anreisetag '.$s['checkin_from'].' Uhr), danach nur über die Rezeption' : 'kostenfrei bis zum Anreisetag').". Buchungsmodus: ".($s['auto_confirm'] ? 'SOFORTBUCHUNG – book_room bucht verbindlich.' : 'ANFRAGE – book_room legt eine Anfrage an, die Rezeption bestätigt in Kürze per E-Mail; nie „gebucht“ sagen, sondern „angefragt“.').($s['policies'] !== '' ? "\nHausregeln & Infos: ".str_replace("\n",' · ',$s['policies']) : '')."
REGELN: 1) Buchung: Anreise, Abreise und Personenzahl erfragen; relative Angaben („nächstes Wochenende“, „morgen“) in konkrete Daten umrechnen und mit Wochentag nennen → check_room_availability → höchstens 3 passende Optionen mit Gesamtpreis nennen (Frühstück nur als Option mit Aufpreis, falls angeboten). Preise und Verfügbarkeit NIE raten oder selbst rechnen – nur Werte aus den Werkzeugen. Dann Vor- und Nachname sowie E-Mail (für die Bestätigung) oder Telefonnummer erfragen, alles in einem Satz zusammenfassen (Zimmer, Daten, Nächte, Personen, Frühstück, Gesamtpreis) und fragen: ".$ask." Erst nach ausdrücklichem Ja book_room aufrufen, danach Buchungscode und Status nennen. Mehrere Zimmer: je Zimmer ein book_room. 2) Bestehende Buchungen: get_booking/cancel_booking nur mit Buchungscode UND Name (oder Telefonnummer); ohne Übereinstimmung keine Auskunft. Storno erst nach ausdrücklicher Bestätigung; ist die Frist abgelaufen, freundlich an die Rezeption verweisen und Rückruf anbieten. 3) Gäste im Haus: immer zuerst die Zimmernummer erfragen, dann create_guest_request (kind: housekeeping, amenities, roomservice, wakeup, maintenance, late_checkout, info, other). Weckruf und Taxi brauchen eine konkrete Uhrzeit (heute oder morgen klären). Taxi: order_taxi_for_guest mit Name, Ziel, Abholzeit, Personen. Keine Zusagen zu genauen Erledigungszeiten; Late Check-out nur als Wunsch aufnehmen (die Rezeption bestätigt). 4) Datenschutz: niemals Daten anderer Gäste nennen – auch nicht, wer in welchem Zimmer wohnt oder ob jemand im Haus ist. 5) Beschwerden: zuerst Verständnis zeigen und entschuldigen, dann create_guest_request mit kind other (Zimmer, Anliegen) und einen Rückruf der Rezeption anbieten. 6) Notfall: 112, danach Rezeption informieren. Hausinfos nur aus diesen Angaben und der Wissensbasis.";
    if($channel==='phone') $h.="\nKANAL TELEFON: keine Links oder URLs vorlesen – die Bestätigung kommt per E-Mail. Buchungscode langsam Zeichen für Zeichen mit kurzen Pausen buchstabieren. Daten immer mit Wochentag aussprechen (z. B. „Freitag, der 20. November“). Die Anrufernummer ist bekannt und dient als Kontakt, außer der Gast möchte eine andere.";
    elseif($channel==='whatsapp') $h.="\nKANAL WHATSAPP: Die WhatsApp-Nummer des Gastes ist bekannt und dient als Kontakt.".($s['auto_confirm'] ? '' : ' Bei Anfragen zusätzlich die E-Mail-Adresse erfragen – die Bestätigung der Rezeption kann später kommen, wenn WhatsApp-Nachrichten nicht mehr zugestellt werden.')." Nach der Buchung den Link zur Buchungsübersicht (Feld stay_url) mitschicken.";
    else $h.="\nNach der Buchung den Link zur Buchungsübersicht (Feld stay_url) mitschicken – dort kann der Gast die Buchung ansehen, in den Kalender übernehmen und fristgerecht stornieren.";
    return $h;
}

/* ======================================================================= Studio-REST: /v1/hotel/* */
function hotel_api_settings(int $aid): array {
    return ['settings'=>hotel_settings_public(hotel_settings($aid)),'rooms'=>hotel_rooms($aid,true),'links'=>hotel_links($aid),'stats'=>hotel_stats($aid),
        'kinds'=>HOTEL_KINDS,'statuses'=>HOTEL_STATUS_LABELS,'request_statuses'=>HOTEL_REQ_LABELS,'demo_rooms'=>array_map(function($r){ unset($r['id']); return $r; },HOTEL_DEMO['rooms'])];
}
function hotel_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if(strpos($route,'hotel/')!==0) return null;
    hotel_bootstrap(); $isDate=fn($v)=>is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$v); $body=hotel_scalars($body,['rooms']); $_GET=hotel_scalars($_GET);
    $routes=['GET hotel/settings','POST hotel/settings','GET hotel/bookings','POST hotel/booking','POST hotel/book','GET hotel/availability','GET hotel/requests','POST hotel/request','POST hotel/request/new'];
    try{
        switch($route){
            case 'hotel/settings':
                if($method==='GET') return [200,hotel_api_settings($aid)];
                if($method==='POST'){
                    if(isset($body['rooms']) && is_array($body['rooms'])){ $chk=hotel_rooms_check($aid,$body['rooms']); if(isset($chk['error'])) return [422,$chk]; }   // Kategorie mit anstehenden Buchungen nicht entfernen
                    hotel_settings_save($aid,$body); return [200,['ok'=>true]+hotel_api_settings($aid)]; } break;
            case 'hotel/bookings':
                if($method!=='GET') break; $t=hotel_today(hotel_settings($aid));
                $from=$isDate($_GET['from'] ?? null) ? $_GET['from'] : $t; $to=$isDate($_GET['to'] ?? null) ? $_GET['to'] : hotel_day($from)->modify('+30 days')->format('Y-m-d'); if($to<$from) [$from,$to]=[$to,$from];
                $st=array_filter(array_map('trim',explode(',',(string)($_GET['status'] ?? ''))));
                return [200,['from'=>$from,'to'=>$to,'bookings'=>hotel_bookings($aid,$from,$to,$st)]];
            case 'hotel/booking':
                if($method!=='POST') break; $r=hotel_booking_action($aid,(int)($body['id'] ?? 0),(string)($body['action'] ?? ''),(string)($body['room_no'] ?? ''),'studio');
                return [isset($r['error']) ? ($r['error']==='not_found' ? 404 : 422) : 200,$r];
            case 'hotel/book':
                if($method!=='POST') break; $r=hotel_book($aid,$body,'studio','',true); return [isset($r['error']) ? 422 : 200,$r];   // manueller Eintrag der Rezeption
            case 'hotel/availability':
                if($method!=='GET') break; $r=hotel_availability($aid,(string)($_GET['checkin'] ?? ''),(string)($_GET['checkout'] ?? ''),(int)($_GET['guests'] ?? 2),$_GET['nights'] ?? null); return [isset($r['error']) ? 422 : 200,$r];
            case 'hotel/requests':
                if($method!=='GET') break; $sc=in_array($_GET['scope'] ?? 'open',['open','all','board'],true) ? $_GET['scope'] : 'open'; return [200,['scope'=>$sc,'requests'=>hotel_requests($aid,$sc)]];
            case 'hotel/request':
                if($method!=='POST') break; $r=hotel_request_set_status($aid,(int)($body['id'] ?? 0),(string)($body['status'] ?? '')); return [isset($r['error']) ? ($r['error']==='not_found' ? 404 : 422) : 200,$r];
            case 'hotel/request/new':
                if($method!=='POST') break; $r=hotel_request_create($aid,$body,'studio'); return [isset($r['error']) ? 422 : 200,$r];
            default: return [404,['error'=>'not_found','routes'=>$routes]];
        }
    }catch(Throwable $e){ error_log('CH hotel_api '.$route.': '.$e->getMessage()); return [500,['error'=>'server_error','message'=>'Interner Fehler – bitte erneut versuchen.']]; }
    return [405,['error'=>'method_not_allowed','routes'=>$routes]];
}
