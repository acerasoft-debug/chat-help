<?php
/**
 * ChatHelp — Taxi-Zentrale-Agent für Taxiunternehmen, Taxistände/Funkzentralen, Mietwagen und Shuttle-Dienste
 * ===========================================================================================================
 * • Fahrtannahme per Telefon, WhatsApp und Website-Chat: Abholort, sofort oder Vorbestellung, Personen, Gepäck,
 *   Kindersitz/Rollstuhl/Tier, Ziel → Richtpreis nach Taxitarif (inkl. Nachtzuschlag) oder Festpreis, Wartezeit ab dem nächsten Taxistand
 * • Adressprüfung: Taxistände, OpenStreetMap-Geocoding (ord_geocode, gecacht), Einsatzgebiet (Radius um die Zentrale und/oder PLZ-Liste)
 * • Route: OSRM (gecacht in route_cache), bei Ausfall Schätzung über Luftlinie × 1,3
 * • Disposition: new → assigned → en_route → arrived → onboard → completed (+ cancelled, no_show); Fahrerstatus frei/besetzt/pause;
 *   Fahrer per WhatsApp → SMS → E-Mail an die Zentrale, Kunde bei Zuteilung und „Taxi ist da“ (WhatsApp bzw. SMS)
 * • Seiten: ride.php (Kunde), taxi-board.php (Zentrale), taxi-driver.php (Fahrer-App), ride-api.php (Board-API)
 * • Konto 0 = öffentliche Demo „Taxi Rheinland · Köln“ (fiktiv): keine Benachrichtigungen, Demo-Fahrten laufen automatisch ab, Löschung nach 2 Tagen
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/order-lib.php';

const RIDE_STATUSES = ['new','assigned','en_route','arrived','onboard','completed','cancelled','no_show'];
const RIDE_FLOW     = ['new','assigned','en_route','arrived','onboard','completed'];
const RIDE_ACTIVE   = ['new','assigned','en_route','arrived','onboard'];
const RIDE_FINAL    = ['completed','cancelled','no_show'];
const RIDE_LABELS   = ['new'=>'Eingegangen','assigned'=>'Fahrer zugeteilt','en_route'=>'Anfahrt','arrived'=>'Taxi ist da','onboard'=>'Fahrt läuft','completed'=>'Abgeschlossen','cancelled'=>'Storniert','no_show'=>'Kunde nicht angetroffen'];
const RIDE_DRIVER_STATUSES = ['frei','besetzt','pause'];
const RIDE_PAYMENTS = ['bar'=>'Bar','karte'=>'EC-/Kreditkarte','app'=>'App / Apple Pay / Google Pay','rechnung'=>'Rechnung (Firmenkunden)'];
const RIDE_MIN_LEAD = 15;   // Vorbestellung: mindestens 15 Minuten im Voraus
const RIDE_TARIFF_DEFAULT = ['base_cents'=>490,'km1_cents'=>240,'km1_upto'=>7,'km2_cents'=>210,'wait_min_cents'=>63,'min_fare_cents'=>0,'night_pct'=>0,'night_from'=>'22:00','night_to'=>'06:00','booking_fee_cents'=>0];
const RIDE_VEHICLES_DEFAULT = [['key'=>'standard','name'=>'Taxi (bis 4 Personen)','seats'=>4,'factor'=>1],['key'=>'kombi','name'=>'Kombi (viel Gepäck)','seats'=>4,'factor'=>1],
    ['key'=>'grossraum','name'=>'Großraumtaxi (bis 8 Personen)','seats'=>8,'factor'=>1.25],['key'=>'rollstuhl','name'=>'Rollstuhl-Taxi','seats'=>4,'factor'=>1.2]];
const RIDE_DEMO = ['name'=>'Taxi Rheinland · Köln','phone'=>'0221 2000000','address'=>'Domstraße 10, 50668 Köln','lat'=>50.946900,'lon'=>6.957700,'tz'=>'Europe/Berlin',
  'area_km'=>25,'area_plz'=>'','tariff'=>RIDE_TARIFF_DEFAULT,'vehicles'=>RIDE_VEHICLES_DEFAULT,
  'fixed_routes'=>[['name'=>'Flughafen Köln/Bonn','match'=>'flughafen|airport|cgn|konrad','price_cents'=>3900]],
  'stands'=>[['name'=>'Hauptbahnhof','address'=>'Trankgasse 11, 50667 Köln','lat'=>50.943000,'lon'=>6.958900],['name'=>'Neumarkt','address'=>'Neumarkt 1, 50667 Köln','lat'=>50.936100,'lon'=>6.946800],
             ['name'=>'Ehrenfeld Bahnhof','address'=>'Venloer Str. 400, 50825 Köln','lat'=>50.951500,'lon'=>6.917400],['name'=>'Deutz/Messe','address'=>'Ottoplatz 1, 50679 Köln','lat'=>50.940600,'lon'=>6.974600]],
  'eta_min'=>8,'prebook_days'=>14,'payment_methods'=>'bar,karte,app,rechnung',
  'drivers'=>[['id'=>1,'name'=>'Mehmet','vehicle'=>'Mercedes E-Klasse, elfenbein','plate'=>'K-TX 1024','seats'=>4,'vehicle_key'=>'standard','stand'=>'Hauptbahnhof'],
              ['id'=>2,'name'=>'Sabine','vehicle'=>'Toyota Corolla Touring Sports, elfenbein','plate'=>'K-TX 2207','seats'=>4,'vehicle_key'=>'kombi','stand'=>'Neumarkt'],
              ['id'=>3,'name'=>'Luca','vehicle'=>'Mercedes V-Klasse, schwarz','plate'=>'K-TX 3815','seats'=>8,'vehicle_key'=>'grossraum','stand'=>'Deutz/Messe'],
              ['id'=>4,'name'=>'Jana','vehicle'=>'VW Caddy Maxi mit Rampe, elfenbein','plate'=>'K-TX 4402','seats'=>4,'vehicle_key'=>'rollstuhl','stand'=>'Ehrenfeld Bahnhof']]];

/* ---------- Tabellen ---------- */
function ride_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS ride_settings (
        account_id INT PRIMARY KEY, ride_on TINYINT(1) DEFAULT 0, name VARCHAR(120) DEFAULT '', phone VARCHAR(40) DEFAULT '', address VARCHAR(200) DEFAULT '',
        lat DECIMAL(9,6) NULL, lon DECIMAL(9,6) NULL, tz VARCHAR(40) DEFAULT 'Europe/Berlin', area_km INT DEFAULT 25, area_plz TEXT NULL,
        tariff TEXT NULL, vehicles TEXT NULL, fixed_routes TEXT NULL, stands TEXT NULL, eta_min INT DEFAULT 8, prebook_days INT DEFAULT 14,
        payment_methods VARCHAR(120) DEFAULT 'bar,karte', notify_email VARCHAR(190) DEFAULT '', board_token CHAR(24) NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_board (board_token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS rides (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, code CHAR(8) NOT NULL, channel VARCHAR(16) DEFAULT 'chat', status VARCHAR(12) DEFAULT 'new',
        customer_name VARCHAR(120) DEFAULT '', phone VARCHAR(40) DEFAULT '', wa_from VARCHAR(40) DEFAULT '',
        pickup_text VARCHAR(240) DEFAULT '', pickup_geo VARCHAR(240) DEFAULT '', pickup_lat DECIMAL(9,6) NULL, pickup_lon DECIMAL(9,6) NULL,
        dest_text VARCHAR(240) DEFAULT '', dest_geo VARCHAR(240) DEFAULT '', dest_lat DECIMAL(9,6) NULL, dest_lon DECIMAL(9,6) NULL,
        distance_km DECIMAL(6,1) NULL, duration_min INT NULL, pickup_at DATETIME NOT NULL, asap TINYINT(1) DEFAULT 1, eta_min INT NULL,
        passengers TINYINT DEFAULT 1, luggage VARCHAR(60) DEFAULT '', vehicle VARCHAR(20) DEFAULT 'standard', extras VARCHAR(200) DEFAULT '',
        price_cents INT NULL, price_kind VARCHAR(10) DEFAULT '', payment VARCHAR(20) DEFAULT 'bar', note VARCHAR(400) DEFAULT '',
        driver_id INT NULL, stand VARCHAR(80) DEFAULT '', pickup_checked TINYINT(1) DEFAULT 1, status_log TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
        UNIQUE KEY uq_code (code), INDEX(account_id, status), INDEX(account_id, pickup_at), INDEX(account_id, driver_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS taxi_drivers (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, name VARCHAR(80) NOT NULL, phone VARCHAR(40) DEFAULT '', vehicle VARCHAR(60) DEFAULT '', plate VARCHAR(20) DEFAULT '',
        seats TINYINT DEFAULT 4, vehicle_key VARCHAR(20) DEFAULT 'standard', stand VARCHAR(80) DEFAULT '', token CHAR(24) NOT NULL, status VARCHAR(10) DEFAULT 'frei',
        active TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
        UNIQUE KEY uq_token (token), INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS route_cache (h CHAR(32) PRIMARY KEY, km DECIMAL(7,1) NULL, minutes INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* Spalten, die nach der ersten Version dazukamen, nachrüsten */
    foreach(['rides'=>['pickup_geo'=>"VARCHAR(240) DEFAULT ''",'dest_geo'=>"VARCHAR(240) DEFAULT ''",'eta_min'=>"INT NULL",'pickup_checked'=>"TINYINT(1) DEFAULT 1",'status_log'=>"TEXT NULL"],
             'taxi_drivers'=>['updated_at'=>"DATETIME NULL"]] as $tb=>$cols){
        $have=$p->query("SHOW COLUMNS FROM $tb")->fetchAll(PDO::FETCH_COLUMN);
        foreach($cols as $c=>$def) if(!in_array($c,$have,true)) $p->exec("ALTER TABLE $tb ADD COLUMN $c $def");
    }
    if(random_int(1,40)===1){ $p->exec("DELETE FROM rides WHERE account_id=0 AND created_at < (NOW() - INTERVAL 2 DAY)"); $p->exec("DELETE FROM route_cache WHERE created_at < (NOW() - INTERVAL 60 DAY)"); }
}

/* ---------- kleine Helfer ---------- */
function ride_tz(array $s): DateTimeZone { try{ return new DateTimeZone((string)($s['tz'] ?? '') ?: 'Europe/Berlin'); }catch(Throwable $e){ return new DateTimeZone('Europe/Berlin'); } }
function ride_now(array $s): DateTimeImmutable { return new DateTimeImmutable('now',ride_tz($s)); }
function ride_dt(?string $v, DateTimeZone $tz): ?DateTimeImmutable { if($v===null || $v==='' || str_starts_with($v,'0000')) return null; try{ return new DateTimeImmutable($v,$tz); }catch(Throwable $e){ return null; } }
function ride_eur(?int $c): string { return number_format(((int)$c)/100,2,',','.').' €'; }
function ride_km(float $km): string { return number_format($km,1,',','.').' km'; }
function ride_str($v, string $def=''): string { return is_scalar($v) ? (string)$v : $def; }
function ride_lc(string $t): string { return mb_strtolower(trim((string)preg_replace('/\s+/u',' ',$t))); }
function ride_hm(string $v): ?string { return preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)$/',trim($v),$m) ? sprintf('%02d:%02d',(int)$m[1],(int)$m[2]) : null; }
function ride_wd(DateTimeImmutable $d, bool $long=false): string { $w=(int)$d->format('w'); return $long ? ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'][$w] : ['So','Mo','Di','Mi','Do','Fr','Sa'][$w]; }
/** „Fr, 17.10. 05:30“ */
function ride_fmt_when(DateTimeImmutable $at): string { return ride_wd($at).', '.$at->format('d.m.').' '.$at->format('H:i'); }
/** „heute um 05:30 Uhr“ · „morgen (Sa, 18.10.) um 05:30 Uhr“ · „Fr, 24.10.2026 um 05:30 Uhr“ */
function ride_fmt_when_long(DateTimeImmutable $at, DateTimeImmutable $now): string {
    $days=(int)$now->setTime(0,0)->diff($at->setTime(0,0))->format('%r%a');
    if($days===0) return 'heute um '.$at->format('H:i').' Uhr';
    if($days===1) return 'morgen ('.ride_wd($at).', '.$at->format('d.m.').') um '.$at->format('H:i').' Uhr';
    return ride_wd($at).', '.$at->format('d.m.Y').' um '.$at->format('H:i').' Uhr';
}
function ride_city(array $s): string { return trim((string)(ord_parse_address((string)($s['address'] ?? ''))['city'] ?? '')); }
function ride_code(): string { $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $c=''; for($i=0;$i<6;$i++) $c.=$a[random_int(0,strlen($a)-1)]; return $c; }
function ride_slug(string $name): string {
    $t=strtr(mb_strtolower($name),['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']);
    if(str_contains($t,'rollstuhl')) return 'rollstuhl'; if(preg_match('/grossraum|\bvan\b|\bbus\b/',$t)) return 'grossraum'; if(str_contains($t,'kombi')) return 'kombi';
    return substr(preg_replace('/[^a-z0-9]/','',$t),0,20) ?: 'fahrzeug';
}
/** Telefonnummer → E.164 (+49…); Ländervorwahl aus der Zeitzone des Betriebs, Standard DE. */
function ride_e164(string $phone, array $s): string {
    $p=trim($phone); $d=preg_replace('/\D/','',$p); if($d==='') return '';
    if(str_starts_with($p,'+')) return '+'.$d; if(str_starts_with($d,'00')) return '+'.substr($d,2);
    $cc=['Europe/Vienna'=>'43','Europe/Zurich'=>'41','Europe/Istanbul'=>'90','Europe/Paris'=>'33','Europe/London'=>'44'][(string)($s['tz'] ?? '')] ?? '49';
    return str_starts_with($d,'0') ? '+'.$cc.substr($d,1) : '+'.$d;
}
function ride_mask_phone(string $p): string { $d=preg_replace('/\D/','',$p); return strlen($d)<6 ? ($p===''?'':'•••') : substr($p,0,4).' ••• '.substr($d,-2); }
/** Protokoll der Benachrichtigungen dieses Requests (Selbsttest / Fehlersuche). */
function ride_notify_log(?array $add=null): array { static $log=[]; if($add!==null){ $log[]=$add+['t'=>gmdate('H:i:s')]; if(count($log)>100) array_shift($log); } return $log; }

/* ---------- Einstellungen ---------- */
function ride_tariff_norm($t, ?array $base=null): array {
    $t=is_array($t)?$t:[]; $out=$base ?: RIDE_TARIFF_DEFAULT;
    foreach(['base_cents','km1_cents','km2_cents','wait_min_cents','min_fare_cents','booking_fee_cents'] as $k) if(isset($t[$k]) && is_numeric($t[$k])) $out[$k]=max(0,min(100000,(int)round((float)$t[$k])));
    $up=str_replace(',','.',ride_str($t['km1_upto'] ?? null)); if(is_numeric($up)) $out['km1_upto']=max(0,min(500,round((float)$up,1)));
    if(isset($t['night_pct']) && is_numeric($t['night_pct'])) $out['night_pct']=max(0,min(100,(int)$t['night_pct']));
    foreach(['night_from','night_to'] as $k) if(($hm=ride_hm(ride_str($t[$k] ?? null)))!==null) $out[$k]=$hm;
    $out['km1_upto']=$out['km1_upto']==(int)$out['km1_upto'] ? (int)$out['km1_upto'] : (float)$out['km1_upto'];
    return $out;
}
function ride_vehicles_norm($list): array {
    $out=[]; $seen=[];
    foreach((array)$list as $v){ if(!is_array($v)) continue; $name=mb_substr(trim(ride_str($v['name'] ?? '')),0,60); if($name==='') continue;
        $key=strtolower(preg_replace('/[^a-z0-9_-]/i','',ride_str($v['key'] ?? ''))); if($key==='') $key=ride_slug($name); $key=substr($key,0,20);
        $base=$key; $i=2; while(isset($seen[$key])) $key=substr($base,0,17).'-'.$i++; $seen[$key]=1;
        $f=(float)str_replace(',','.',ride_str($v['factor'] ?? 1,'1')); if($f<=0) $f=1;
        $out[]=['key'=>$key,'name'=>$name,'seats'=>max(1,min(20,(int)($v['seats'] ?? 4))),'factor'=>max(0.5,min(5,round($f,2)))]; if(count($out)>=12) break; }
    return $out ?: RIDE_VEHICLES_DEFAULT;
}
function ride_fixed_norm($list): array {
    $out=[];
    foreach((array)$list as $f){ if(!is_array($f)) continue; $name=mb_substr(trim(ride_str($f['name'] ?? '')),0,80); if($name==='') continue;
        $alts=array_values(array_unique(array_filter(array_map(fn($x)=>mb_substr(ride_lc($x),0,40),preg_split('/[|,;]+/u',ride_str($f['match'] ?? '')) ?: []),fn($x)=>mb_strlen($x)>=2)));
        if(!$alts) $alts=[ride_lc($name)];
        $price=isset($f['price_cents']) && is_numeric($f['price_cents']) ? (int)$f['price_cents'] : (int)round(((float)str_replace(',','.',ride_str($f['price'] ?? 0,'0')))*100);
        if($price<=0) continue; $out[]=['name'=>$name,'match'=>implode('|',array_slice($alts,0,12)),'price_cents'=>min(10000000,$price)]; if(count($out)>=30) break; }
    return $out;
}
function ride_stands_norm($list): array {
    $out=[];
    foreach((array)$list as $st){ if(!is_array($st)) continue; $name=mb_substr(trim(ride_str($st['name'] ?? '')),0,80); if($name==='') continue;
        $lat=isset($st['lat']) && is_numeric($st['lat']) && abs((float)$st['lat'])<=90 ? round((float)$st['lat'],6) : null; $lon=isset($st['lon']) && is_numeric($st['lon']) && abs((float)$st['lon'])<=180 ? round((float)$st['lon'],6) : null;
        if($lat===null || $lon===null){ $lat=null; $lon=null; }
        $out[]=['name'=>$name,'address'=>mb_substr(trim(ride_str($st['address'] ?? '')),0,200),'lat'=>$lat,'lon'=>$lon]; if(count($out)>=30) break; }
    return $out;
}
function ride_payments_norm(string $v): string { $pm=array_values(array_intersect(array_keys(RIDE_PAYMENTS),array_map('trim',explode(',',strtolower($v))))); return implode(',',$pm) ?: 'bar'; }
function ride_plz_norm(string $v): string { $l=array_values(array_unique(array_filter(preg_split('/[\s,;]+/',$v) ?: [],fn($x)=>preg_match('/^\d{4,5}$/',$x)))); return implode(',',array_slice($l,0,400)); }
function ride_settings_norm(array $r): array {
    $r['tariff']=ride_tariff_norm($r['tariff'] ?? []); $r['vehicles']=ride_vehicles_norm($r['vehicles'] ?? []);
    $r['fixed_routes']=ride_fixed_norm($r['fixed_routes'] ?? []); $r['stands']=ride_stands_norm($r['stands'] ?? []);
    foreach(['ride_on'=>0,'area_km'=>25,'eta_min'=>8,'prebook_days'=>14] as $k=>$def) $r[$k]=(int)($r[$k] ?? $def);
    $r['lat']=isset($r['lat']) && $r['lat']!==null ? (float)$r['lat'] : null; $r['lon']=isset($r['lon']) && $r['lon']!==null ? (float)$r['lon'] : null;
    if($r['lat']===null || $r['lon']===null){ $r['lat']=null; $r['lon']=null; }
    $r['area_plz']=ride_plz_norm((string)($r['area_plz'] ?? '')); $r['payment_methods']=ride_payments_norm((string)($r['payment_methods'] ?? 'bar'));
    foreach(['name','phone','address','notify_email'] as $k) $r[$k]=(string)($r[$k] ?? ''); $r['tz']=(string)($r['tz'] ?? '') ?: 'Europe/Berlin';
    return $r;
}
/** Einstellungen des Kontos (je Request zwischengespeichert; $fresh=true liest neu). */
function ride_settings(int $acc, bool $fresh=false): array {
    static $cache=[]; ride_bootstrap();
    if($fresh) unset($cache[$acc]); if(isset($cache[$acc])) return $cache[$acc];
    if($acc===0){ $r=RIDE_DEMO; unset($r['drivers']); return $cache[0]=ride_settings_norm($r+['account_id'=>0,'ride_on'=>1,'notify_email'=>'','board_token'=>null,'updated_at'=>null,'saved'=>false,'demo'=>true]); }
    $q=th_db()->prepare("SELECT * FROM ride_settings WHERE account_id=?"); $q->execute([$acc]); $r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r) $r=['account_id'=>$acc,'ride_on'=>0,'name'=>'','phone'=>'','address'=>'','lat'=>null,'lon'=>null,'tz'=>'Europe/Berlin','area_km'=>25,'area_plz'=>'','tariff'=>null,'vehicles'=>null,
        'fixed_routes'=>null,'stands'=>null,'eta_min'=>8,'prebook_days'=>14,'payment_methods'=>'bar,karte','notify_email'=>'','board_token'=>null,'updated_at'=>null,'saved'=>false];
    else $r['saved']=true;
    foreach(['tariff','vehicles','fixed_routes','stands'] as $k) $r[$k]=is_string($r[$k]) ? json_decode($r[$k],true) : $r[$k];
    $r['account_id']=$acc; $r['demo']=false;
    return $cache[$acc]=ride_settings_norm($r);
}
function ride_settings_save(int $acc, array $d): array {
    ride_bootstrap(); if($acc===0) return ride_settings(0);   // Demo-Konto: Einstellungen kommen aus RIDE_DEMO
    $c=ride_settings($acc,true); $city=ride_city($c);
    $txt=fn(string $k, int $len)=>mb_substr(trim(ride_str($d[$k] ?? $c[$k],(string)$c[$k])),0,$len);
    $address=$txt('address',200); $lat=$c['lat']; $lon=$c['lon'];
    if(isset($d['lat'],$d['lon']) && is_numeric($d['lat']) && is_numeric($d['lon']) && abs((float)$d['lat'])<=90 && abs((float)$d['lon'])<=180){ $lat=(float)$d['lat']; $lon=(float)$d['lon']; }
    elseif($address==='') { $lat=null; $lon=null; }
    elseif($address!==$c['address'] || $lat===null){ $g=ord_geocode($address); if($g){ $lat=$g['lat']; $lon=$g['lon']; } elseif($address!==$c['address']){ $lat=null; $lon=null; } }
    $stands=$c['stands'];
    if(isset($d['stands']) && is_array($d['stands'])){
        $old=[]; foreach($c['stands'] as $o) if($o['lat']!==null) $old[ride_lc($o['address'] ?: $o['name'])]=$o;
        $stands=ride_stands_norm($d['stands']); $city=ride_city(['address'=>$address]) ?: $city; $geo=0;
        foreach($stands as &$st){
            if($st['lat']!==null) continue; $k=ride_lc($st['address'] ?: $st['name']);
            if(isset($old[$k])){ $st['lat']=$old[$k]['lat']; $st['lon']=$old[$k]['lon']; continue; }
            if($st['address']==='' || ++$geo>12) continue;
            $q=$st['address']; $pa=ord_parse_address($q); if($pa['plz']==='' && $city!=='' && mb_stripos($q,$city)===false) $q.=', '.$city;
            $g=ord_geocode($q); if($g){ $st['lat']=round($g['lat'],6); $st['lon']=round($g['lon'],6); }
        } unset($st);
    }
    $tz=ride_str($d['tz'] ?? ''); if(!in_array($tz,timezone_identifiers_list(),true)) $tz=$c['tz'];
    $v=['ride_on'=>(int)!empty($d['ride_on'] ?? $c['ride_on']),'name'=>$txt('name',120),'phone'=>$txt('phone',40),
        'address'=>$address,'lat'=>$lat,'lon'=>$lon,'tz'=>$tz,'area_km'=>max(1,min(300,(int)($d['area_km'] ?? $c['area_km']))),'area_plz'=>ride_plz_norm($txt('area_plz',4000)),
        'tariff'=>json_encode(isset($d['tariff']) && is_array($d['tariff']) ? ride_tariff_norm($d['tariff'],$c['tariff']) : $c['tariff']),
        'vehicles'=>json_encode(isset($d['vehicles']) && is_array($d['vehicles']) ? ride_vehicles_norm($d['vehicles']) : $c['vehicles'],JSON_UNESCAPED_UNICODE),
        'fixed_routes'=>json_encode(isset($d['fixed_routes']) && is_array($d['fixed_routes']) ? ride_fixed_norm($d['fixed_routes']) : $c['fixed_routes'],JSON_UNESCAPED_UNICODE),
        'stands'=>json_encode($stands,JSON_UNESCAPED_UNICODE),'eta_min'=>max(1,min(90,(int)($d['eta_min'] ?? $c['eta_min']))),'prebook_days'=>max(0,min(90,(int)($d['prebook_days'] ?? $c['prebook_days']))),
        'payment_methods'=>ride_payments_norm(is_array($d['payment_methods'] ?? null) ? implode(',',array_map('ride_str',$d['payment_methods'])) : $txt('payment_methods',120)),
        'notify_email'=>filter_var($txt('notify_email',190),FILTER_VALIDATE_EMAIL) ?: '','board_token'=>$c['board_token'] ?: bin2hex(random_bytes(12))];
    th_db()->prepare("INSERT INTO ride_settings(account_id,ride_on,name,phone,address,lat,lon,tz,area_km,area_plz,tariff,vehicles,fixed_routes,stands,eta_min,prebook_days,payment_methods,notify_email,board_token)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE ride_on=VALUES(ride_on),name=VALUES(name),phone=VALUES(phone),address=VALUES(address),lat=VALUES(lat),lon=VALUES(lon),tz=VALUES(tz),
        area_km=VALUES(area_km),area_plz=VALUES(area_plz),tariff=VALUES(tariff),vehicles=VALUES(vehicles),fixed_routes=VALUES(fixed_routes),stands=VALUES(stands),eta_min=VALUES(eta_min),prebook_days=VALUES(prebook_days),
        payment_methods=VALUES(payment_methods),notify_email=VALUES(notify_email),board_token=VALUES(board_token),updated_at=NOW()")
      ->execute([$acc,$v['ride_on'],$v['name'],$v['phone'],$v['address'],$v['lat'],$v['lon'],$v['tz'],$v['area_km'],$v['area_plz'],$v['tariff'],$v['vehicles'],$v['fixed_routes'],$v['stands'],$v['eta_min'],$v['prebook_days'],$v['payment_methods'],$v['notify_email'],$v['board_token']]);
    ride_settings($acc,true);
    if(isset($d['drivers']) && is_array($d['drivers'])) ride_drivers_save($acc,$d['drivers']);
    return ride_settings($acc);
}
/** Für Studio/API: Board-Token nicht in den Einstellungen (steckt im Board-Link unter links.board). */
function ride_settings_public(array $s): array { unset($s['board_token']); return $s; }
function ride_enabled(int $acc): bool { return $acc===0 ? true : ride_settings($acc)['ride_on']===1; }
function ride_owner_email(int $acc, array $s): string { return $acc<=0 ? '' : ($s['notify_email'] ?: ord_owner_email($acc)); }
function ride_account_by_board_token(string $t): ?int {
    if(!preg_match('/^[a-f0-9]{24}$/',$t)) return null; ride_bootstrap();
    $q=th_db()->prepare("SELECT account_id FROM ride_settings WHERE board_token=? AND account_id>0"); $q->execute([$t]); $a=$q->fetchColumn(); return $a===false ? null : (int)$a;
}
function ride_links(int $acc): array {
    $s=ride_settings($acc);
    return ['board'=>$s['board_token'] ? 'https://chat-help.com/chat/taxi-board.php?t='.$s['board_token'] : null,
        'drivers'=>array_map(fn($d)=>['id'=>$d['id'],'name'=>$d['name'],'url'=>$d['url']],ride_drivers($acc,true))];
}

/* ---------- Fahrer ---------- */
function ride_driver_row(array $r): array {
    return ['id'=>(int)$r['id'],'account_id'=>(int)$r['account_id'],'name'=>(string)$r['name'],'phone'=>(string)$r['phone'],'vehicle'=>(string)$r['vehicle'],'plate'=>(string)$r['plate'],'seats'=>(int)$r['seats'],
        'vehicle_key'=>(string)$r['vehicle_key'],'stand'=>(string)$r['stand'],'status'=>in_array($r['status'],RIDE_DRIVER_STATUSES,true) ? $r['status'] : 'frei','active'=>(int)$r['active'],
        'token'=>(string)$r['token'],'url'=>'https://chat-help.com/chat/taxi-driver.php?t='.$r['token']];
}
function ride_drivers(int $acc, bool $activeOnly=true): array {
    ride_bootstrap();
    if($acc===0) return array_map(fn($d)=>$d+['account_id'=>0,'phone'=>'','status'=>'frei','active'=>1,'token'=>'','url'=>null],RIDE_DEMO['drivers']);
    $q=th_db()->prepare("SELECT * FROM taxi_drivers WHERE account_id=?".($activeOnly?" AND active=1":"")." ORDER BY active DESC, id"); $q->execute([$acc]);
    return array_map('ride_driver_row',$q->fetchAll(PDO::FETCH_ASSOC));
}
function ride_driver(int $acc, int $id): ?array {
    if($id<=0) return null;
    if($acc===0){ foreach(ride_drivers(0) as $d) if($d['id']===$id) return $d; return null; }
    ride_bootstrap(); $q=th_db()->prepare("SELECT * FROM taxi_drivers WHERE id=? AND account_id=?"); $q->execute([$id,$acc]); $r=$q->fetch(PDO::FETCH_ASSOC); return $r ? ride_driver_row($r) : null;
}
function ride_driver_by_token(string $t): ?array {
    if(!preg_match('/^[a-f0-9]{24}$/',$t)) return null; ride_bootstrap();
    $q=th_db()->prepare("SELECT * FROM taxi_drivers WHERE token=? AND active=1 AND account_id>0"); $q->execute([$t]); $r=$q->fetch(PDO::FETCH_ASSOC); return $r ? ride_driver_row($r) : null;
}
/** Fahrer für Board/Listen (ohne Token und Telefonnummer). */
function ride_driver_public(array $d): array { return ['id'=>$d['id'],'name'=>$d['name'],'status'=>$d['status'],'vehicle'=>$d['vehicle'],'plate'=>$d['plate'],'seats'=>$d['seats'],'vehicle_key'=>$d['vehicle_key'],'stand'=>$d['stand']]; }
/** Komplette Fahrerliste speichern: vorhandene ids behalten ihr Token, fehlende werden deaktiviert. */
function ride_drivers_save(int $acc, array $list): void {
    ride_bootstrap(); if($acc===0) return; $p=th_db(); $keep=[]; $s=ride_settings($acc);
    $vkeys=array_column($s['vehicles'],'key'); $stands=array_column($s['stands'],'name');
    foreach($list as $d){ if(!is_array($d)) continue; $name=mb_substr(trim(strip_tags(ride_str($d['name'] ?? ''))),0,80); if($name==='') continue;
        $phone=mb_substr(trim((string)preg_replace('/[^0-9+ ]/','',ride_str($d['phone'] ?? ''))),0,40);
        $veh=mb_substr(trim(strip_tags(ride_str($d['vehicle'] ?? ''))),0,60); $plate=mb_substr(mb_strtoupper(trim((string)preg_replace('/\s+/u',' ',strip_tags(ride_str($d['plate'] ?? ''))))),0,20);
        $seats=max(1,min(20,(int)($d['seats'] ?? 4))); $vk=ride_str($d['vehicle_key'] ?? '');
        if(!in_array($vk,$vkeys,true)) $vk=($seats>4 && in_array('grossraum',$vkeys,true)) ? 'grossraum' : (in_array('standard',$vkeys,true) ? 'standard' : ($vkeys[0] ?? 'standard'));
        $stand=ride_str($d['stand'] ?? ''); if(!in_array($stand,$stands,true)) $stand='';
        $id=(int)($d['id'] ?? 0);
        if($id>0){ $chk=$p->prepare("SELECT COUNT(*) FROM taxi_drivers WHERE id=? AND account_id=?"); $chk->execute([$id,$acc]); if((int)$chk->fetchColumn()===0) $id=0; }
        if($id>0) $p->prepare("UPDATE taxi_drivers SET name=?,phone=?,vehicle=?,plate=?,seats=?,vehicle_key=?,stand=?,active=1,updated_at=NOW() WHERE id=? AND account_id=?")->execute([$name,$phone,$veh,$plate,$seats,$vk,$stand,$id,$acc]);
        else { $p->prepare("INSERT INTO taxi_drivers(account_id,name,phone,vehicle,plate,seats,vehicle_key,stand,token,status,active) VALUES(?,?,?,?,?,?,?,?,?,'frei',1)")->execute([$acc,$name,$phone,$veh,$plate,$seats,$vk,$stand,bin2hex(random_bytes(12))]); $id=(int)$p->lastInsertId(); }
        $keep[]=$id; if(count($keep)>=200) break; }
    if($keep) $p->prepare("UPDATE taxi_drivers SET active=0 WHERE account_id=? AND id NOT IN (".implode(',',array_map('intval',$keep)).")")->execute([$acc]);
    elseif(!$list) $p->prepare("UPDATE taxi_drivers SET active=0 WHERE account_id=?")->execute([$acc]);
}
function ride_driver_set_status(int $acc, int $id, string $status): array {
    if(!in_array($status,RIDE_DRIVER_STATUSES,true)) return ['error'=>'bad_status','hint'=>'Erlaubt: frei, besetzt, pause'];
    $d=ride_driver($acc,$id); if(!$d || !$d['active']) return ['error'=>'not_found','hint'=>'Fahrer nicht gefunden'];
    if($acc!==0) th_db()->prepare("UPDATE taxi_drivers SET status=?, updated_at=NOW() WHERE id=? AND account_id=?")->execute([$status,$id,$acc]);
    $d['status']=$status; return ['ok'=>true,'driver'=>ride_driver_public($d)];
}
/** Fahrerstatus aus seinen Fahrten ableiten: laufende/gleich fällige Fahrt → besetzt; sonst besetzt → frei (Pause bleibt Pause). */
function ride_driver_refresh(int $acc, int $driverId, array $s): void {
    if($acc===0 || $driverId<=0) return;
    $soon=ride_now($s)->modify('+45 minutes')->format('Y-m-d H:i:s');
    $q=th_db()->prepare("SELECT COUNT(*) FROM rides WHERE account_id=? AND driver_id=? AND (status IN ('en_route','arrived','onboard') OR (status='assigned' AND (asap=1 OR pickup_at<=?)))"); $q->execute([$acc,$driverId,$soon]);
    if((int)$q->fetchColumn()>0) th_db()->prepare("UPDATE taxi_drivers SET status='besetzt', updated_at=NOW() WHERE id=? AND account_id=?")->execute([$driverId,$acc]);
    else th_db()->prepare("UPDATE taxi_drivers SET status='frei', updated_at=NOW() WHERE id=? AND account_id=? AND status='besetzt'")->execute([$driverId,$acc]);
}
function ride_vehicle_fits(array $d, string $vkey, int $pax): bool { if((int)$d['seats']<$pax) return false; return $vkey==='' || $vkey==='standard' || $d['vehicle_key']===$vkey; }
/** Standort eines Fahrers: Koordinaten seines Taxistands, sonst der Zentrale. */
function ride_driver_pos(array $d, array $s): ?array {
    foreach($s['stands'] as $st) if($st['name']===$d['stand'] && $st['lat']!==null) return [$st['lat'],$st['lon'],$st['name']];
    return $s['lat']!==null ? [$s['lat'],$s['lon'],''] : null;
}
/** Fahrer-Reihenfolge für eine Fahrt: frei + passend (nach Entfernung) → frei → Pause → besetzt. */
function ride_driver_rank(array $ride, array $drivers, array $s): array {
    $lat=$ride['pickup_lat'] ?? null; $lon=$ride['pickup_lon'] ?? null; $rows=[];
    foreach($drivers as $d){ if(empty($d['active'])) continue; $pos=ride_driver_pos($d,$s); $km=($lat!==null && $pos) ? ord_distance_km((float)$lat,(float)$lon,$pos[0],$pos[1]) : 99;
        $fit=ride_vehicle_fits($d,(string)($ride['vehicle'] ?? 'standard'),(int)($ride['passengers'] ?? 1));
        $grp=$d['status']==='frei' ? ($fit?0:1) : ($d['status']==='pause'?2:3); $rows[]=[$grp,$km,$d['id'],$fit]; }
    usort($rows,fn($a,$b)=>[$a[0],$a[1],$a[2]]<=>[$b[0],$b[1],$b[2]]);
    return ['order'=>array_map(fn($r)=>$r[2],$rows),'suggest'=>($rows && $rows[0][0]===0) ? $rows[0][2] : null,'unfit'=>array_values(array_map(fn($r)=>$r[2],array_filter($rows,fn($r)=>!$r[3])))];
}

/* ---------- Orte, Einsatzgebiet, Route ---------- */
/** Taxistand im Text („am Hauptbahnhof“, „Köln Hbf“, „Taxistand Neumarkt“, „Messe“) – nur ohne Hausnummer und ohne fremde Ortsangabe. */
function ride_match_stand(array $s, string $text): ?array {
    if(!$s['stands'] || preg_match('/\d/',$text)) return null;
    $norm=fn(string $x)=>trim((string)preg_replace('/[^\p{L}\p{N}]+/u',' ',ride_lc($x)));
    $t=' '.str_replace([' hbf ',' bhf '],[' hauptbahnhof ',' bahnhof '],' '.$norm($text).' ').' ';
    $stop=['zum','zur','zu','am','an','der','die','das','den','dem','des','taxistand','stand','taxi','bitte','in','im','vom','von','beim','bei','vor','ab','abholung','abholen','mich','uns','hier','ich','bin','stehe','warte','eingang','haupteingang','ausgang','vorne','seite','und'];
    foreach(explode(' ',$norm(ride_city($s))) as $w) if($w!=='') $stop[]=$w;
    $best=null; $bestN=0;
    foreach($s['stands'] as $st){
        $all=array_values(array_filter(explode(' ',$norm(str_replace('/',' ',$st['name']))),fn($w)=>mb_strlen($w)>=3));
        foreach(preg_split('~\s*/\s*~u',$st['name']) ?: [] as $alt){
            $toks=array_values(array_filter(explode(' ',$norm($alt)),fn($w)=>mb_strlen($w)>=3)); if(!$toks) continue;
            foreach($toks as $w) if(!str_contains($t,' '.$w)) continue 2;
            $left=array_filter(explode(' ',trim($t)),function($w) use($stop,$all){ if(mb_strlen($w)<3 || in_array($w,$stop,true)) return false; foreach($all as $a) if(str_starts_with($w,$a)) return false; return true; });
            if($left) continue;
            if(count($toks)>$bestN){ $best=$st; $bestN=count($toks); }
        }
    }
    return $best;
}
/** Ort auflösen: Taxistand → Geocoding (ohne PLZ/Ort zuerst „<Text>, <Ort der Zentrale>“). */
function ride_locate(array $s, string $text): array {
    $text=mb_substr(trim((string)preg_replace('/\s+/u',' ',strip_tags($text))),0,240);
    $out=['text'=>$text,'found'=>false,'lat'=>null,'lon'=>null,'display'=>'','plz'=>'','source'=>'none','stand'=>null]; if($text==='') return $out;
    $pa=ord_parse_address($text); $out['plz']=$pa['plz']; $q=$text;
    if($st=ride_match_stand($s,$text)){
        $out['stand']=$st['name']; $out['display']='Taxistand '.$st['name'].($st['address']!==''?' · '.$st['address']:'');
        if($st['lat']!==null){ $out['found']=true; $out['lat']=$st['lat']; $out['lon']=$st['lon']; $out['source']='stand'; $out['plz']=ord_parse_address($st['address'])['plz'] ?: $out['plz']; return $out; }
        if($st['address']!=='') $q=$st['address'];
    }
    $city=ride_city($s); $pq=ord_parse_address($q);
    $hasCity=$pq['plz']!=='' || str_contains($q,',') || ($city!=='' && mb_stripos($q,$city)!==false);
    $tries=$hasCity ? [$q] : []; if($city!=='' && mb_stripos($q,$city)===false && $pq['plz']==='') $tries[]=$q.', '.$city; if(!$hasCity) $tries[]=$q;
    foreach(array_values(array_unique($tries)) as $try){ $g=ord_geocode($try); if(!$g) continue;
        $out['found']=true; $out['lat']=round((float)$g['lat'],6); $out['lon']=round((float)$g['lon'],6); $out['source']='geo'; $out['plz']=(string)($g['plz'] ?? '') ?: $out['plz'];
        if($out['display']==='') $out['display']=mb_substr((string)preg_replace('/,\s*(Deutschland|Österreich|Schweiz)$/u','',(string)$g['display']),0,200); break; }
    return $out;
}
/** Einsatzgebiet: Radius um die Zentrale oder PLZ-Liste. in_area null = nicht prüfbar. */
function ride_area(array $s, array $loc): array {
    $list=$s['area_plz']!=='' ? explode(',',$s['area_plz']) : []; $plzOk=$loc['plz']!=='' && in_array($loc['plz'],$list,true);
    $dist=($loc['found'] && $s['lat']!==null) ? ord_distance_km($s['lat'],$s['lon'],(float)$loc['lat'],(float)$loc['lon']) : null;
    if($plzOk) $in=true; elseif($dist!==null) $in=$dist<=$s['area_km']; elseif($list && $loc['plz']!=='') $in=false; else $in=null;
    return ['in_area'=>$in,'distance_km'=>$dist];
}
/**
 * Fahrstrecke über OSRM (Timeout 6 s, Ergebnis in route_cache). Fällt OSRM aus, wird 3 Minuten lang nicht erneut gefragt;
 * Schätzung dann: km = Luftlinie × 1,3, Minuten = km / 28 km/h + 3.
 */
function ride_route(float $lat1, float $lon1, float $lat2, float $lon2): array {
    ride_bootstrap(); $p=th_db(); $h=md5(sprintf('%.4f,%.4f;%.4f,%.4f',$lat1,$lon1,$lat2,$lon2));
    $q=$p->prepare("SELECT km,minutes FROM route_cache WHERE h=?"); $q->execute([$h]); $r=$q->fetch(PDO::FETCH_ASSOC);
    if($r && $r['km']!==null) return ['km'=>(float)$r['km'],'min'=>(int)$r['minutes'],'source'=>'cache'];
    static $down=null;
    if($down===null){ $b=$p->prepare("SELECT COUNT(*) FROM route_cache WHERE h=? AND created_at > (NOW() - INTERVAL 3 MINUTE)"); $b->execute([md5('osrm_down')]); $down=(int)$b->fetchColumn()>0; }
    if(!$down){
        $ch=curl_init(sprintf('https://router.project-osrm.org/route/v1/driving/%.6f,%.6f;%.6f,%.6f?overview=false',$lon1,$lat1,$lon2,$lat2));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_HTTPHEADER=>['User-Agent: ChatHelp-Taxi/1.0 (support@chat-help.com)','Accept: application/json']]);
        $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        $j=$code===200 ? json_decode((string)$raw,true) : null;
        if(is_array($j) && ($j['code'] ?? '')==='Ok' && isset($j['routes'][0]['distance'],$j['routes'][0]['duration'])){
            $km=round(((float)$j['routes'][0]['distance'])/1000,1); $min=max(1,(int)round(((float)$j['routes'][0]['duration'])/60));
            try{ $p->prepare("INSERT INTO route_cache(h,km,minutes) VALUES(?,?,?) ON DUPLICATE KEY UPDATE km=VALUES(km),minutes=VALUES(minutes),created_at=NOW()")->execute([$h,$km,$min]); }catch(Throwable $e){}
            return ['km'=>$km,'min'=>$min,'source'=>'osrm'];
        }
        $down=true; try{ $p->prepare("INSERT INTO route_cache(h,km,minutes) VALUES(?,NULL,NULL) ON DUPLICATE KEY UPDATE created_at=NOW()")->execute([md5('osrm_down')]); }catch(Throwable $e){}
    }
    $km=round(ord_distance_km($lat1,$lon1,$lat2,$lon2)*1.3,1);
    return ['km'=>$km,'min'=>(int)round($km/28*60)+3,'source'=>'estimate'];
}

/* ---------- Zeit, Fahrzeug, Preis, Wartezeit ---------- */
/**
 * Wunschzeit: „sofort“/„jetzt“ · „in 20 Minuten“ · „HH:MM“ (heute, schon vorbei → morgen) · „morgen HH:MM“ · „übermorgen …“ · „Freitag 5:30“
 * · „17.10. 05:30“ · „YYYY-MM-DD HH:MM“. Vorbestellung mind. 15 Min, max. prebook_days. Unter 15 Min → Sofortfahrt.
 */
function ride_parse_time(array $s, string $raw): array {
    $tz=ride_tz($s); $now=ride_now($s); $t=ride_lc($raw); $max=(int)$s['prebook_days'];
    $fmt='Bitte „sofort“ oder eine Uhrzeit angeben: „HH:MM“, „morgen HH:MM“ oder „YYYY-MM-DD HH:MM“.';
    if($t==='' || (!preg_match('/\d/',$t) && preg_match('/sofort|jetzt|gleich|asap|now|schnell|umgehend|direkt|möglichst bald|baldmöglichst/u',$t))) return ['asap'=>true,'at'=>null,'note'=>null];
    $at=null; $explicit=false; $day=null; $weekday=false;
    if(preg_match('/\bin\s+(\d{1,3})\s*(min|minuten|minute|m)\b/u',$t,$m)) $at=$now->modify('+'.(int)$m[1].' minutes');
    elseif(preg_match('/\bin\s+(einer\s+)?(halben|1\/2)\s+stunde/u',$t)) $at=$now->modify('+30 minutes');
    elseif(preg_match('/\bin\s+(einer|1)\s+stunde\b/u',$t)) $at=$now->modify('+60 minutes');
    elseif(preg_match('/\bin\s+(\d{1,2})\s*(std|stunden)\b/u',$t,$m)) $at=$now->modify('+'.(int)$m[1].' hours');
    if($at===null){
        $rest=$t;
        if(preg_match('/(\d{4})-(\d{2})-(\d{2})(?:[ t])?/',$rest,$m)){ if(!checkdate((int)$m[2],(int)$m[3],(int)$m[1])) return ['error'=>'time_format','hint'=>'Ungültiges Datum. '.$fmt]; $day=new DateTimeImmutable($m[1].'-'.$m[2].'-'.$m[3].' 00:00',$tz); $rest=str_replace($m[0],' ',$rest); $explicit=true; }
        elseif(preg_match('/\b(\d{1,2})\.(\d{1,2})\.(\d{2,4})?/',$rest,$m)){ $y=isset($m[3]) && $m[3]!=='' ? (int)$m[3] : (int)$now->format('Y'); if($y<100) $y+=2000;
            if(!checkdate((int)$m[2],(int)$m[1],$y)) return ['error'=>'time_format','hint'=>'Ungültiges Datum. '.$fmt];
            $day=new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00',$y,(int)$m[2],(int)$m[1]),$tz); if(($m[3] ?? '')==='' && $day<$now->setTime(0,0)) $day=$day->modify('+1 year'); $rest=str_replace($m[0],' ',$rest); $explicit=true; }
        elseif(preg_match('/\b(übermorgen|uebermorgen)\b/u',$rest)) $day=$now->setTime(0,0)->modify('+2 days');
        elseif(preg_match('/\bmorgen\b/u',$rest) && !preg_match('/\bheute\s+morgen\b/u',$rest)) $day=$now->setTime(0,0)->modify('+1 day');
        elseif(preg_match('/\bheute\b/u',$rest)) $day=$now->setTime(0,0);
        elseif(preg_match('/\b(nächsten?\s+|naechsten?\s+)?(montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonntag)\b/u',$rest,$m)){
            $w=array_search($m[2],['sonntag','montag','dienstag','mittwoch','donnerstag','freitag','samstag'],true); $diff=((int)$w-(int)$now->format('w')+7)%7; if($diff===0 && trim($m[1])!=='') $diff=7;
            $day=$now->setTime(0,0)->modify('+'.$diff.' days'); $explicit=true; $weekday=$diff===0; }
        $hh=null; $mm=0;
        if(preg_match('/\b([01]?\d|2[0-3])\s*[:.h]\s*([0-5]\d)\b/u',$rest,$m)){ $hh=(int)$m[1]; $mm=(int)$m[2]; }
        elseif(preg_match('/\b([01]?\d|2[0-3])\s*uhr\b/u',$rest,$m)) $hh=(int)$m[1];
        elseif($day!==null && preg_match('/^\D*\b([01]?\d|2[0-3])\b\D*$/u',$rest,$m)) $hh=(int)$m[1];
        if($hh===null) return $day!==null ? ['error'=>'time_required','hint'=>'Bitte auch die Uhrzeit erfragen (z. B. „morgen 05:30“).'] : ['error'=>'time_format','hint'=>$fmt];
        if($day===null){ $at=$now->setTime($hh,$mm); if($at<$now) $at=$at->modify('+1 day'); }
        else { $at=$day->setTime($hh,$mm); if($at<$now && $weekday) $at=$at->modify('+7 days');   // „Freitag 5:30“ am Freitagmittag = nächste Woche
            if($at<$now) return ['error'=>'time_past','hint'=>'Diese Zeit liegt in der Vergangenheit ('.ride_wd($at).', '.$at->format('d.m.Y H:i').' Uhr). Bitte eine zukünftige Zeit oder „sofort“ angeben.']; }
    }
    $at=$at->setTime((int)$at->format('H'),(int)$at->format('i'));
    $mins=(int)floor(($at->getTimestamp()-$now->getTimestamp())/60);
    if($mins<RIDE_MIN_LEAD) return ['asap'=>true,'at'=>null,'note'=>'Die Wunschzeit liegt weniger als '.RIDE_MIN_LEAD.' Minuten in der Zukunft – als Sofortfahrt gebucht.'];
    if($max<=0) return ['error'=>'prebook_disabled','hint'=>'Vorbestellungen sind nicht möglich – nur Sofortfahrten. Bitte zur gewünschten Zeit erneut anrufen bzw. schreiben.'];
    if($at>$now->modify('+'.$max.' days')) return ['error'=>'too_far','hint'=>'Vorbestellung höchstens '.$max.' Tage im Voraus (bis '.$now->modify('+'.$max.' days')->format('d.m.Y').').'];
    return ['asap'=>false,'at'=>$at,'note'=>($explicit || $day!==null) ? null : 'Uhrzeit ohne Datum → '.ride_fmt_when_long($at,$now).' (bitte so bestätigen).'];
}
/** Fahrzeug wählen: Wunsch → Rollstuhl erzwingt Rollstuhl-Taxi → mehr Personen als Plätze → Großraum. */
function ride_pick_vehicle(array $s, string $req, int $pax, string $extras): array {
    $vs=$s['vehicles']; $req=ride_lc($req);
    $byKey=function(string $k) use($vs){ foreach($vs as $v) if($v['key']===$k) return $v; return null; };
    $find=function(string $q) use($vs,$byKey){ if($q==='') return null; $q2=strtr($q,['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']);
        foreach($vs as $v) if($v['key']===$q || ride_lc($v['name'])===$q) return $v;
        foreach(['rollstuhl'=>'/rollstuhl|rolli|rampe/','grossraum'=>'/grossraum|gross|van|bus|7|8|sitzer/','kombi'=>'/kombi|gepaeck|station|caddy/','standard'=>'/standard|normal|taxi|limousine|pkw|klein/'] as $k=>$re) if(preg_match($re,$q2) && ($v=$byKey($k))) return $v;
        foreach($vs as $v){ $n=strtr(ride_lc($v['name']),['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']); if(str_contains($n,$q2) || str_contains($q2,$v['key'])) return $v; }
        return null; };
    $chosen=$find($req); $notes=[]; if($req!=='' && !$chosen) $notes[]='Fahrzeugwunsch „'.$req.'“ unbekannt – Standard gewählt.';
    if(preg_match('/rollstuhl|rolli/u',ride_lc($extras.' '.$req))){
        $w=$byKey('rollstuhl'); if(!$w) foreach($vs as $v) if(str_contains(ride_lc($v['name']),'rollstuhl')) $w=$v;
        if(!$w) return ['error'=>'no_wheelchair_vehicle','hint'=>'Kein Rollstuhl-Taxi in der Flotte. Bitte an die Zentrale verweisen'.($s['phone']?' ('.$s['phone'].')':'').'.'];
        if(!$chosen || $chosen['key']!==$w['key']) $notes[]='Rollstuhl → automatisch '.$w['name'].'.'; $chosen=$w;
        if($pax>$w['seats']) return ['error'=>'too_many_passengers','hint'=>$w['name'].' hat '.$w['seats'].' Plätze. Für mehr Personen bitte zusätzlich ein zweites Taxi buchen.'];
    }
    if(!$chosen) $chosen=$byKey('standard') ?: $vs[0];
    if($pax>$chosen['seats']){
        $big=$byKey('grossraum'); if(!$big || $big['seats']<$pax){ $big=null; foreach($vs as $v) if($v['seats']>=$pax && $v['key']!=='rollstuhl' && (!$big || $v['seats']<$big['seats'])) $big=$v; }
        if(!$big){ $maxSeats=max(array_column($vs,'seats')); return ['error'=>'too_many_passengers','hint'=>'Höchstens '.$maxSeats.' Personen je Fahrzeug. Bitte die Gruppe auf mehrere Taxis aufteilen (je eine Buchung, z. B. '.$maxSeats.' + '.($pax-$maxSeats).' Personen).']; }
        $notes[]='Für '.$pax.' Personen automatisch '.$big['name'].'.'; $chosen=$big;
    }
    return ['key'=>$chosen['key'],'name'=>$chosen['name'],'seats'=>$chosen['seats'],'factor'=>(float)$chosen['factor'],'notes'=>$notes];
}
function ride_is_night(array $t, DateTimeImmutable $at): bool {
    if((int)$t['night_pct']<=0) return false; $f=$t['night_from']; $to=$t['night_to']; if($f===$to) return false; $hm=$at->format('H:i');
    return $f<$to ? ($hm>=$f && $hm<$to) : ($hm>=$f || $hm<$to);
}
/** Festpreis-Strecke: Treffer im Ziel- oder Abholtext (|-Liste, case-insensitiv, sicher per preg_quote). */
function ride_fixed_route(array $s, string $pickup, string $dest): ?array {
    foreach($s['fixed_routes'] as $fr){ $alts=array_values(array_filter(array_map('trim',explode('|',$fr['match'])),fn($a)=>$a!=='')); if(!$alts) continue;
        $re='/('.implode('|',array_map(fn($a)=>preg_quote($a,'/'),$alts)).')/iu';
        $inDest=$dest!=='' && preg_match($re,$dest)===1; $inPick=$pickup!=='' && preg_match($re,$pickup)===1;
        if($inDest || $inPick) return $fr+['matched'=>$inDest?'dest':'pickup']; }
    return null;
}
function ride_price_text(?int $cents, string $kind, string $fixedName=''): string {
    if($cents===null || $kind==='') return 'nach Taxameter';
    return $kind==='fixed' ? ride_eur($cents).' Festpreis'.($fixedName!==''?' ('.$fixedName.')':'') : 'ca. '.ride_eur($cents).' (Richtpreis, es gilt der Taxameter)';
}
/**
 * Preis: Festpreis-Strecke (wenn die Gegenseite im Einsatzgebiet liegt und der Taxameter-Richtwert nicht unter der Hälfte des Festpreises liegt –
 * schützt z. B. „Konrad-Adenauer-Ufer“ vor dem Flughafen-Festpreis), sonst Grundpreis + km-Staffel × Fahrzeugfaktor + Nachtzuschlag,
 * Mindestfahrpreis, + Buchungsgebühr, auf 10 Cent gerundet.
 */
function ride_price(array $s, ?float $km, array $veh, DateTimeImmutable $at, string $pickup, string $dest, ?bool $destInArea=null): array {
    $t=$s['tariff']; $night=ride_is_night($t,$at); $est=null;
    if($km!==null){ $k1=min($km,(float)$t['km1_upto']); $k2=max(0.0,$km-(float)$t['km1_upto']);
        $fare=($t['base_cents']+$k1*$t['km1_cents']+$k2*$t['km2_cents'])*(float)($veh['factor'] ?? 1);
        if($night) $fare*=1+$t['night_pct']/100; $fare=max($fare,(float)$t['min_fare_cents'])+$t['booking_fee_cents']; $est=(int)(round($fare/10)*10); }
    $skipped=null;
    if($dest!=='' && ($fr=ride_fixed_route($s,$pickup,$dest))){
        if($fr['matched']==='pickup' && $destInArea===false) $skipped='Festpreis „'.$fr['name'].'“ gilt nur für Ziele im Einsatzgebiet.';
        elseif($est!==null && $est<$fr['price_cents']*0.5) $skipped='Festpreis „'.$fr['name'].'“ passt nicht zu dieser kurzen Strecke – Taxameter.';
        else return ['cents'=>$fr['price_cents'],'kind'=>'fixed','text'=>ride_price_text($fr['price_cents'],'fixed',$fr['name']),'night'=>false,'fixed_route'=>$fr['name'],'note'=>null];
    }
    if($est!==null) return ['cents'=>$est,'kind'=>'estimate','text'=>ride_price_text($est,'estimate'),'night'=>$night,'fixed_route'=>null,'note'=>$skipped];
    return ['cents'=>null,'kind'=>'','text'=>$dest==='' ? 'nach Taxameter (ohne Ziel kein Richtpreis)' : 'nach Taxameter (Ziel nicht auf der Karte gefunden)','night'=>$night,'fixed_route'=>null,'note'=>$skipped];
}
/** Wartezeit „sofort“: eta_min + 2 Min je km vom nächsten Taxistand mit freiem (passendem) Fahrer, sonst nächster Stand, sonst eta_min. */
function ride_eta(int $acc, array $s, ?float $lat, ?float $lon, string $vkey='standard', int $pax=1): array {
    $base=max(1,(int)$s['eta_min']); $res=['min'=>$base,'stand'=>null,'distance_km'=>null,'free'=>0,'note'=>null];
    $free=array_values(array_filter(ride_drivers($acc,true),fn($d)=>$d['status']==='frei')); $res['free']=count($free);
    if($lat!==null && $lon!==null){
        $fit=array_values(array_filter($free,fn($d)=>ride_vehicle_fits($d,$vkey,$pax))); $best=null; $usedFit=true;
        foreach([$fit,$free] as $i=>$pool){ foreach($pool as $d){ $pos=ride_driver_pos($d,$s); if(!$pos) continue; $km=ord_distance_km($lat,$lon,$pos[0],$pos[1]); if(!$best || $km<$best[0]) $best=[$km,$pos[2]]; } if($best){ $usedFit=$i===0; break; } }
        if(!$best) foreach($s['stands'] as $st){ if($st['lat']===null) continue; $km=ord_distance_km($lat,$lon,$st['lat'],$st['lon']); if(!$best || $km<$best[0]) $best=[$km,$st['name']]; }
        if($best){ $res['min']=$base+(int)round(2*$best[0]); $res['stand']=$best[1]!=='' ? $best[1] : null; $res['distance_km']=$best[0]; }
        if(!$usedFit && !in_array($vkey,['','standard'],true)){ $res['min']+=10; $res['note']='Gerade kein passendes Fahrzeug frei – die Wartezeit kann länger sein.'; }
    }
    $res['min']=min(90,$res['min']); $res['text']='ca. '.$res['min'].' Min'; return $res;
}
function ride_payment(array $s, string $p): array {
    $allowed=explode(',',$s['payment_methods']); $q=strtr(ride_lc($p),['ä'=>'ae','ö'=>'oe','ü'=>'ue']); $key='';
    foreach(['rechnung'=>'/rechnung|firma|firmen|kostenstelle|invoice/','app'=>'/app|paypal|apple|google|handy|mobil|online/','karte'=>'/karte|\bec\b|kredit|visa|master|giro|debit|card|amex/','bar'=>'/bar|cash|geld/'] as $k=>$re) if($q!=='' && preg_match($re,$q)){ $key=$k; break; }
    if($key!=='' && in_array($key,$allowed,true)) return [$key,null];
    $def=in_array('bar',$allowed,true) ? 'bar' : $allowed[0];
    return [$def,$key!=='' ? 'Zahlart „'.(RIDE_PAYMENTS[$key] ?? $key).'“ wird nicht angeboten – '.(RIDE_PAYMENTS[$def] ?? $def).' vorgemerkt. Möglich: '.implode(', ',array_map(fn($x)=>RIDE_PAYMENTS[$x] ?? $x,$allowed)).'.' : null];
}
/** Eingaben aus Werkzeug, Studio oder anderen Modulen (z. B. Hotel) vereinheitlichen. */
function ride_input(array $d): array {
    $pick=function(array $keys) use($d): string { foreach($keys as $k) if(isset($d[$k]) && is_scalar($d[$k]) && trim((string)$d[$k])!=='') return trim((string)$d[$k]); return ''; };
    $ex=$d['extras'] ?? ''; if(is_array($ex)) $ex=implode(', ',array_filter(array_map(fn($x)=>is_scalar($x) ? trim((string)$x) : '',$ex)));
    return ['name'=>$pick(['name','guest_name','customer_name','customer']),'phone'=>$pick(['phone','telefon','tel','phone_number']),
        'pickup'=>$pick(['pickup','pickup_text','from','start','abholung','abholort','address']),'destination'=>$pick(['destination','dest','dest_text','to','ziel']),
        'time'=>$pick(['time','pickup_time','when','zeit','at','datetime']),'passengers'=>max(1,min(16,(int)($pick(['passengers','pax','persons','personen','guests']) ?: 1))),
        'luggage'=>mb_substr($pick(['luggage','gepaeck','gepäck','bags']),0,60),'vehicle'=>$pick(['vehicle','vehicle_type','fahrzeug','car']),'extras'=>mb_substr(trim((string)$ex),0,200),
        'payment'=>$pick(['payment','zahlung','pay']),'note'=>mb_substr($pick(['note','notes','notiz','bemerkung','comment']),0,400),
        'confirm_address'=>!empty($d['confirm_address']),'force'=>!empty($d['force']),'manual'=>!empty($d['_manual'])];
}
/** Gemeinsame Kalkulation für estimate_ride und book_ride. */
function ride_quote(int $acc, array $s, array $d, bool $booking=false): array {
    if($d['pickup']==='') return ['error'=>'pickup_required','hint'=>'Bitte den Abholort erfragen: Straße + Hausnummer oder ein bekannter Ort (Bahnhof, Hotel, Taxistand).'];
    $time=ride_parse_time($s,$d['time']); if(isset($time['error'])) return $time;
    $pax=$d['passengers']; $veh=ride_pick_vehicle($s,$d['vehicle'],$pax,$d['extras']); if(isset($veh['error'])) return $veh;
    $notes=array_merge($veh['notes'],$time['note'] ? [$time['note']] : []);
    $pu=ride_locate($s,$d['pickup']); $pu+=ride_area($s,$pu);
    if($pu['in_area']===false && !$d['force']) return ['error'=>'outside_area','pickup'=>ride_loc_public($pu),'hint'=>'Der Abholort liegt außerhalb unseres Einsatzgebiets'.($pu['distance_km']!==null?' ('.ride_km($pu['distance_km']).' von der Zentrale, Einsatzgebiet '.$s['area_km'].' km)':'').'. Bitte freundlich an ein örtliches Taxiunternehmen verweisen.'];
    if($booking && !$pu['found'] && !$d['confirm_address'] && !$d['manual'] && !$d['force']) return ['error'=>'pickup_not_found','pickup'=>ride_loc_public($pu),'hint'=>'Der Abholort „'.$pu['text'].'“ wurde auf der Karte nicht gefunden. Bitte Straße, Hausnummer und Ort (oder einen bekannten Ort) genau erfragen und wiederholen. Bestätigt der Kunde die Angabe genau so, book_ride erneut mit confirm_address=true aufrufen.'];
    if(!$pu['found']) $notes[]='Abholort nicht auf der Karte gefunden – Wartezeit/Preis sind grobe Werte, der Fahrer bekommt den Text.';
    $de=$d['destination']!=='' ? ride_locate($s,$d['destination']) : null; if($de) $de+=ride_area($s,$de);
    if($de && !$de['found']) $notes[]='Ziel nicht auf der Karte gefunden – kein Richtpreis, der Fahrer bekommt den Text.';
    $route=($pu['found'] && $de && $de['found']) ? ride_route((float)$pu['lat'],(float)$pu['lon'],(float)$de['lat'],(float)$de['lon']) : null;
    $eta=ride_eta($acc,$s,$pu['lat'],$pu['lon'],$veh['key'],$pax); if($eta['note'] && $time['asap']) $notes[]=$eta['note'];
    $now=ride_now($s); $at=$time['asap'] ? $now->modify('+'.$eta['min'].' minutes') : $time['at'];
    $price=ride_price($s,$route ? (float)$route['km'] : null,$veh,$at,$d['pickup'],$d['destination'],$de ? $de['in_area'] : null); if($price['note']) $notes[]=$price['note'];
    if($d['luggage']!=='' && $veh['key']==='standard' && preg_match('/viel|gro(ß|ss)|koffer|kinderwagen|fahrrad|rollator|ski|[3-9]/iu',$d['luggage'])) foreach($s['vehicles'] as $v) if($v['key']==='kombi'){ $notes[]='Viel Gepäck: ggf. Kombi anbieten (vehicle=kombi).'; break; }
    return ['ok'=>true,'pickup'=>$pu,'destination'=>$de,'route'=>$route,'asap'=>$time['asap'],'at'=>$at,'vehicle'=>$veh,'passengers'=>$pax,'eta'=>$eta,'price'=>$price,'notes'=>array_values(array_unique($notes))];
}
function ride_loc_public(array $l): array { return ['text'=>$l['text'],'found'=>$l['found'],'display'=>$l['display'],'stand'=>$l['stand'],'plz'=>$l['plz'],'in_area'=>$l['in_area'] ?? null,'distance_from_zentrale_km'=>$l['distance_km'] ?? null]; }
/** Kalkulation → Ergebnis für KI/Studio. */
function ride_quote_public(array $q, array $s): array {
    $now=ride_now($s); $r=$q['route'];
    $when=$q['asap'] ? 'sofort ('.$q['eta']['text'].')' : ride_fmt_when_long($q['at'],$now);
    $sum='Abholung '.($q['pickup']['stand'] ? 'Taxistand '.$q['pickup']['stand'] : $q['pickup']['text']).($q['destination'] ? ' → '.$q['destination']['text'] : '').', '.$when.', '.$q['passengers'].' '.($q['passengers']===1?'Person':'Personen').', '.$q['vehicle']['name'].', Preis '.$q['price']['text'].'.';
    return ['ok'=>true,'pickup'=>ride_loc_public($q['pickup']),'destination'=>$q['destination'] ? ride_loc_public($q['destination']) : null,
        'distance_km'=>$r ? $r['km'] : null,'duration_min'=>$r ? $r['min'] : null,'distance_text'=>$r ? ride_km((float)$r['km']).' · ca. '.$r['min'].' Min Fahrzeit' : null,'route_source'=>$r ? $r['source'] : null,
        'asap'=>$q['asap'],'when'=>$q['asap'] ? 'sofort' : ride_fmt_when($q['at']),'when_long'=>$when,'pickup_time_local'=>$q['at']->format('Y-m-d H:i'),
        'eta_min'=>$q['asap'] ? $q['eta']['min'] : null,'eta_text'=>$q['asap'] ? $q['eta']['text'] : null,'nearest_stand'=>$q['eta']['stand'],
        'passengers'=>$q['passengers'],'vehicle'=>['key'=>$q['vehicle']['key'],'name'=>$q['vehicle']['name'],'seats'=>$q['vehicle']['seats']],
        'price_cents'=>$q['price']['cents'],'price_kind'=>$q['price']['kind'],'price_text'=>$q['price']['text'],'fixed_route'=>$q['price']['fixed_route'],'night_surcharge'=>$q['price']['night'],
        'payment_methods'=>array_map(fn($k)=>RIDE_PAYMENTS[$k] ?? $k,explode(',',$s['payment_methods'])),'notes'=>$q['notes'],'summary'=>$sum];
}
function ride_estimate(int $acc, array $in): array { $s=ride_settings($acc); $q=ride_quote($acc,$s,ride_input($in),false); return isset($q['error']) ? $q : ride_quote_public($q,$s); }

/* ---------- Fahrten ---------- */
function ride_by_code(string $code, ?int $acc=null): ?array {
    ride_bootstrap(); $code=strtoupper((string)preg_replace('/[^A-Za-z0-9]/','',$code)); if($code==='' || strlen($code)>8) return null;
    $q=th_db()->prepare("SELECT * FROM rides WHERE code=?".($acc!==null?" AND account_id=?":"")); $q->execute($acc!==null ? [$code,$acc] : [$code]); return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
function ride_by_id(int $acc, int $id): ?array { ride_bootstrap(); $q=th_db()->prepare("SELECT * FROM rides WHERE id=? AND account_id=?"); $q->execute([$id,$acc]); return $q->fetch(PDO::FETCH_ASSOC) ?: null; }
function ride_log_add(?string $json, string $key, ?string $val=null, ?int $ts=null): string { $l=json_decode((string)$json,true); if(!is_array($l)) $l=[]; $l[$key]=$val ?? gmdate('Y-m-d\TH:i:s\Z',$ts ?? time()); return json_encode($l,JSON_UNESCAPED_UNICODE); }
/**
 * Fahrt verbindlich anlegen. $d: name, phone, pickup, destination, time („sofort“/Uhrzeit), passengers, luggage, vehicle, extras, payment, note,
 * confirm_address (Abholort trotz fehlendem Kartentreffer), aus dem Studio zusätzlich force (außerhalb Einsatzgebiet) und _manual.
 * Telefonnummer: fehlt sie, wird +$from (Anrufer- bzw. WhatsApp-Nummer) genommen. Benachrichtigt die Zentrale per E-Mail (Konto > 0).
 */
function ride_book(int $acc, array $d, string $channel, string $from=''): array {
    try{
        $s=ride_settings($acc);
        if(!ride_enabled($acc)) return ['error'=>'rides_disabled','hint'=>'Die Taxi-Annahme ist gerade nicht aktiv – bitte direkt bei der Zentrale anrufen'.($s['phone']?' ('.$s['phone'].')':'').'.'];
        $in=ride_input($d); $channel=mb_substr(preg_replace('/[^a-z0-9_-]/','',strtolower($channel)) ?: 'chat',0,16); $relaxed=$in['manual'] || $channel==='hotel';
        if($channel==='hotel') $in['confirm_address']=true;
        $name=mb_substr(trim(strip_tags($in['name'])),0,120);
        if(mb_strlen($name)<2) return ['error'=>'name_required','hint'=>'Bitte den Namen des Fahrgasts erfragen (für den Fahrer).'];
        $digits=preg_replace('/\D/','',$from); $phone=mb_substr(trim((string)preg_replace('/[^0-9+ \/()-]/','',$in['phone'])),0,40);
        if($phone==='' && strlen($digits)>=6) $phone='+'.$digits;
        if($phone==='' && !$relaxed) return ['error'=>'phone_required','hint'=>'Bitte eine Rückrufnummer erfragen – der Fahrer ruft an, falls er den Fahrgast nicht findet.'];
        if($phone!=='' && strlen(preg_replace('/\D/','',$phone))<6) return ['error'=>'phone_invalid','hint'=>'Die Telefonnummer ist zu kurz – bitte vollständig erfragen.'];
        $q=ride_quote($acc,$s,$in,true); if(isset($q['error'])) return $q;
        [$pay,$payNote]=ride_payment($s,$in['payment']); if($payNote) $q['notes'][]=$payNote;
        $p=th_db(); $pu=$q['pickup']; $de=$q['destination']; $route=$q['route'];
        if($phone!==''){
            $dup=$p->prepare("SELECT * FROM rides WHERE account_id=? AND phone=? AND pickup_text=? AND asap=? AND status IN ('new','assigned') AND created_at > (NOW() - INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1");
            $dup->execute([$acc,$phone,$pu['text'],$q['asap']?1:0]);
            if($r=$dup->fetch(PDO::FETCH_ASSOC)) return ['ok'=>true,'duplicate'=>true,'ride'=>ride_public($r,$s),'hint'=>'Diese Fahrt ist bereits gebucht (Code '.$r['code'].') – nicht erneut buchen.'];
        }
        $vals=[$acc,null,$channel,mb_substr($name,0,120),$phone,$channel==='whatsapp' ? mb_substr($digits,0,40) : '',mb_substr($pu['text'],0,240),mb_substr($pu['display'],0,240),$pu['lat'],$pu['lon'],
            $de ? mb_substr($de['text'],0,240) : '',$de ? mb_substr($de['display'],0,240) : '',$de['lat'] ?? null,$de['lon'] ?? null,$route ? $route['km'] : null,$route ? $route['min'] : null,
            $q['at']->format('Y-m-d H:i:00'),$q['asap']?1:0,$q['asap'] ? $q['eta']['min'] : null,$q['passengers'],$in['luggage'],$q['vehicle']['key'],$in['extras'],$q['price']['cents'],$q['price']['kind'],$pay,
            mb_substr(trim(strip_tags($in['note'])),0,400),mb_substr((string)($pu['stand'] ?: ($q['eta']['stand'] ?? '')),0,80),$pu['found']?1:0,ride_log_add(null,'new')];
        $id=0;
        for($i=0;$i<6 && !$id;$i++){ $vals[1]=ride_code();
            try{ $p->prepare("INSERT INTO rides(account_id,code,channel,status,customer_name,phone,wa_from,pickup_text,pickup_geo,pickup_lat,pickup_lon,dest_text,dest_geo,dest_lat,dest_lon,distance_km,duration_min,pickup_at,asap,eta_min,passengers,luggage,vehicle,extras,price_cents,price_kind,payment,note,stand,pickup_checked,status_log)
                   VALUES(?,?,?,'new',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($vals); $id=(int)$p->lastInsertId(); }
            catch(PDOException $e){ if($e->getCode()!=='23000' || $i===5) throw $e; } }
        $r=ride_by_id($acc,$id); if($acc!==0 && $channel!=='selftest') ride_notify_zentrale($r,$s,'new');
        $pub=ride_public($r,$s);
        $say='Gebucht: Code '.$pub['code_spoken'].' · '.($pub['asap'] ? 'sofort, Wartezeit '.$pub['eta_text'] : $pub['when_long']).' · '.$pub['price_text'].'.';
        return ['ok'=>true,'ride'=>$pub,'notes'=>$q['notes'],'say'=>$say];
    }catch(Throwable $e){ error_log('CH ride_book: '.$e->getMessage()); return ['error'=>'internal_error','hint'=>'Technischer Fehler beim Anlegen der Fahrt – bitte entschuldigen und die Telefonnummer der Zentrale nennen.']; }
}
function ride_public(array $r, ?array $s=null, ?array $drivers=null): array {
    $acc=(int)$r['account_id']; $s=$s ?: ride_settings($acc); $tz=ride_tz($s); $now=ride_now($s);
    $at=ride_dt((string)$r['pickup_at'],$tz); $asap=(int)$r['asap']===1; $st=(string)$r['status'];
    $drv=null; $did=$r['driver_id']!==null ? (int)$r['driver_id'] : 0;
    if($did>0){ $d=null; if($drivers!==null){ foreach($drivers as $x) if((int)$x['id']===$did) $d=$x; } else $d=ride_driver($acc,$did); if($d) $drv=['id'=>$d['id'],'name'=>$d['name'],'vehicle'=>$d['vehicle'],'plate'=>$d['plate']]; }
    $due=$at ? (int)ceil(($at->getTimestamp()-$now->getTimestamp())/60) : null;
    $eta=''; if($asap && in_array($st,['new','assigned','en_route'],true)) $eta=($due!==null && $due>0) ? 'ca. '.$due.' Min' : 'in Kürze';
    $vname=$r['vehicle']; foreach($s['vehicles'] as $v) if($v['key']===$r['vehicle']) $vname=$v['name'];
    $times=[]; $log=json_decode((string)($r['status_log'] ?? ''),true); foreach(is_array($log)?$log:[] as $k=>$v){ if(!in_array($k,RIDE_STATUSES,true) || !is_string($v)) continue; try{ $times[$k]=(new DateTimeImmutable($v))->setTimezone($tz)->format('H:i'); }catch(Throwable $e){} }
    $city=ride_city($s); $nav=fn($lat,$lon,$txt)=>$lat!==null ? 'https://www.google.com/maps/dir/?api=1&destination='.(float)$lat.','.(float)$lon : ($txt!=='' ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($txt.($city!=='' && mb_stripos($txt,$city)===false ? ', '.$city : '')) : null);
    $km=$r['distance_km']!==null ? (float)$r['distance_km'] : null; $dur=$r['duration_min']!==null ? (int)$r['duration_min'] : null;
    $fixedName=''; if($r['price_kind']==='fixed' && ($fr=ride_fixed_route($s,(string)$r['pickup_text'],(string)$r['dest_text']))) $fixedName=$fr['name'];
    $created=th_dbtime($r['created_at'] ?? null,$tz); $cancelBy=is_array($log) ? (string)($log['cancel_by'] ?? '') : '';
    return ['id'=>(int)$r['id'],'code'=>$r['code'],'code_spoken'=>implode(' ',str_split((string)$r['code'])),'status'=>$st,'status_label'=>RIDE_LABELS[$st] ?? $st,'channel'=>$r['channel'],
        'name'=>$r['customer_name'],'phone'=>$r['phone'],'pickup'=>$r['pickup_text'],'pickup_geo'=>$r['pickup_geo'] ?? '','pickup_checked'=>(int)($r['pickup_checked'] ?? 1)===1,
        'pickup_lat'=>$r['pickup_lat']!==null ? (float)$r['pickup_lat'] : null,'pickup_lon'=>$r['pickup_lon']!==null ? (float)$r['pickup_lon'] : null,
        'destination'=>$r['dest_text'],'dest_geo'=>$r['dest_geo'] ?? '','dest_lat'=>$r['dest_lat']!==null ? (float)$r['dest_lat'] : null,'dest_lon'=>$r['dest_lon']!==null ? (float)$r['dest_lon'] : null,
        'asap'=>$asap,'when'=>$asap ? 'sofort' : ($at ? ride_fmt_when($at) : ''),'when_long'=>$asap ? 'sofort' : ($at ? ride_fmt_when_long($at,$now) : ''),
        'pickup_at'=>$at ? $at->format('Y-m-d H:i') : null,'pickup_time'=>$at ? $at->format('H:i') : '','due_in_min'=>$due,'eta_min'=>$r['eta_min']!==null ? (int)$r['eta_min'] : null,'eta_text'=>$eta,
        'passengers'=>(int)$r['passengers'],'luggage'=>$r['luggage'],'vehicle'=>$r['vehicle'],'vehicle_name'=>$vname,'extras'=>$r['extras'],
        'price_cents'=>$r['price_cents']!==null ? (int)$r['price_cents'] : null,'price_kind'=>$r['price_kind'],'price_text'=>ride_price_text($r['price_cents']!==null ? (int)$r['price_cents'] : null,(string)$r['price_kind'],$fixedName),
        'payment'=>$r['payment'],'payment_label'=>RIDE_PAYMENTS[$r['payment']] ?? $r['payment'],'note'=>$r['note'],
        'distance_km'=>$km,'duration_min'=>$dur,'distance_text'=>$km!==null ? ride_km($km).($dur!==null ? ' · ca. '.$dur.' Min Fahrzeit' : '') : '',
        'driver'=>$drv,'driver_id'=>$did ?: null,'stand'=>$r['stand'],
        'maps_pickup'=>$nav($r['pickup_lat'],$r['pickup_lon'],(string)$r['pickup_text']),'maps_dest'=>$r['dest_text']!=='' ? $nav($r['dest_lat'],$r['dest_lon'],(string)$r['dest_text']) : null,
        'track'=>'https://chat-help.com/chat/ride.php?c='.$r['code'],'created'=>$created ? $created->format('H:i') : '','created_date'=>$created ? $created->format('d.m.Y') : '','created_iso'=>th_dbiso($r['created_at'] ?? null),
        'times'=>$times,'cancel_by'=>$cancelBy,'can_cancel'=>in_array($st,['new','assigned','en_route'],true),'active'=>in_array($st,RIDE_ACTIVE,true),'company'=>$s['name'],'company_phone'=>$s['phone']];
}
/** Für die KI bei Statusabfragen: ohne interne Felder, Telefonnummer maskiert. */
function ride_for_ai(array $p): array {
    $keep=['code','code_spoken','status','status_label','name','pickup','destination','when','when_long','eta_text','passengers','vehicle_name','extras','price_text','payment_label','driver','track','can_cancel'];
    $o=array_intersect_key($p,array_flip($keep)); $o['phone']=ride_mask_phone((string)$p['phone']); $o['first_name']=explode(' ',trim((string)$p['name']))[0] ?? ''; unset($o['name']); return $o;
}
/**
 * Status setzen (Board, Studio, Fahrer, Kunde). Vorwärts jederzeit, Storno aus new/assigned/en_route, „nicht angetroffen“ aus assigned/en_route/arrived,
 * zurück auf new (Fahrer abziehen) bzw. Umteilen auf einen anderen Fahrer. Fahrerstatus wird mitgeführt, Benachrichtigungen nur für Konto > 0.
 */
function ride_set_status(int $acc, int $id, string $status, ?int $driverId=null, string $by=''): array {
    if(!in_array($status,RIDE_STATUSES,true)) return ['error'=>'bad_status','hint'=>'Erlaubt: '.implode(', ',RIDE_STATUSES)];
    $r=ride_by_id($acc,$id); if(!$r) return ['error'=>'not_found','hint'=>'Fahrt nicht gefunden'];
    $s=ride_settings($acc); $cur=(string)$r['status']; $oldDrv=$r['driver_id']!==null ? (int)$r['driver_id'] : 0; $driverId=$driverId!==null && $driverId>0 ? $driverId : null;
    if($status===$cur && ($driverId===null || $driverId===$oldDrv)) return ['ok'=>true,'unchanged'=>true,'ride'=>ride_public($r,$s)];
    if(in_array($cur,RIDE_FINAL,true)) return ['error'=>'final_state','hint'=>'Die Fahrt ist bereits '.(RIDE_LABELS[$cur] ?? $cur).'.'];
    $fi=array_search($cur,RIDE_FLOW,true); $ti=array_search($status,RIDE_FLOW,true);
    $ok=match($status){ 'cancelled'=>in_array($cur,['new','assigned','en_route'],true), 'no_show'=>in_array($cur,['assigned','en_route','arrived'],true),
        'new'=>in_array($cur,['assigned','en_route'],true), 'assigned'=>$cur==='new' || ($driverId!==null && $driverId!==$oldDrv && in_array($cur,['assigned','en_route','arrived'],true)),
        default=>$ti!==false && $fi!==false && $ti>$fi };
    if(!$ok) return ['error'=>'bad_transition','hint'=>'Von „'.(RIDE_LABELS[$cur] ?? $cur).'“ nicht nach „'.(RIDE_LABELS[$status] ?? $status).'“ möglich.'];
    $newDrv=$status==='new' ? 0 : ($driverId ?? $oldDrv); $warn=[];
    if($driverId!==null){ $d=ride_driver($acc,$driverId); if(!$d || !$d['active']) return ['error'=>'bad_driver','hint'=>'Fahrer nicht gefunden']; if($d['seats']<(int)$r['passengers']) $warn[]=$d['name'].' hat nur '.$d['seats'].' Plätze ('.(int)$r['passengers'].' Personen).'; }
    if($status==='assigned' && !$newDrv) return ['error'=>'driver_required','hint'=>'Bitte einen Fahrer wählen.'];
    $log=ride_log_add($r['status_log'] ?? null,$status); if($by!=='' && $status==='cancelled') $log=ride_log_add($log,'cancel_by',$by);
    th_db()->prepare("UPDATE rides SET status=?, driver_id=?, status_log=?, updated_at=NOW() WHERE id=? AND account_id=?")->execute([$status,$newDrv ?: null,$log,$id,$acc]);
    if($oldDrv && $oldDrv!==$newDrv) ride_driver_refresh($acc,$oldDrv,$s);
    if($newDrv) ride_driver_refresh($acc,$newDrv,$s);
    $r=ride_by_id($acc,$id);
    if($acc!==0 && $r['channel']!=='selftest'){
        if($newDrv && $newDrv!==$oldDrv && in_array($status,['assigned','en_route','arrived'],true) && ($d=ride_driver($acc,$newDrv))){ ride_notify_driver($r,$s,$d,'assigned'); if($status!=='arrived') ride_notify_customer($r,$s,'assigned'); }
        if($oldDrv && $oldDrv!==$newDrv && ($od=ride_driver($acc,$oldDrv))) ride_notify_driver($r,$s,$od,'withdrawn');
        if($status==='cancelled' && $oldDrv && ($od=ride_driver($acc,$oldDrv))) ride_notify_driver($r,$s,$od,'cancelled');
        if($status==='arrived') ride_notify_customer($r,$s,'arrived');
    }
    return ['ok'=>true,'ride'=>ride_public($r,$s)]+($warn ? ['warnings'=>$warn] : []);
}
/** Storno durch den Kunden (Werkzeug cancel_ride, Statusseite): solange new/assigned/en_route; bei Anfahrt E-Mail an die Zentrale. */
function ride_cancel_customer(int $acc, string $code): array {
    $r=ride_by_code($code,$acc); if(!$r) return ['error'=>'not_found','hint'=>'Keine Fahrt mit diesem Code gefunden – bitte den Code buchstabieren lassen.'];
    $s=ride_settings($acc);
    if($r['status']==='cancelled') return ['ok'=>true,'already'=>true,'ride'=>ride_public($r,$s)];
    if(!in_array($r['status'],['new','assigned','en_route'],true)) return ['error'=>'too_late','status'=>$r['status'],'hint'=>'Stornieren ist nicht mehr möglich (Status: '.(RIDE_LABELS[$r['status']] ?? $r['status']).'). Bitte direkt die Zentrale anrufen'.($s['phone']?' ('.$s['phone'].')':'').'.'];
    $enRoute=$r['status']==='en_route'; $res=ride_set_status($acc,(int)$r['id'],'cancelled',null,'Kunde'); if(isset($res['error'])) return $res;
    if($enRoute && $acc!==0 && $r['channel']!=='selftest') ride_notify_zentrale(ride_by_id($acc,(int)$r['id']),$s,'cancel_en_route');
    return $res+['hint'=>'Die Fahrt ist storniert.'];
}
function ride_find_by_phone(int $acc, string $phone): array {
    $d=preg_replace('/\D/','',$phone); if(strlen($d)<6) return []; $s=ride_settings($acc); $like='%'.substr($d,-8);
    $q=th_db()->prepare("SELECT * FROM rides WHERE account_id=? AND (created_at > (NOW() - INTERVAL 1 DAY) OR (status IN ('new','assigned','en_route','arrived','onboard') AND pickup_at >= ?))
        AND (REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-',''),'/','') LIKE ? OR wa_from LIKE ?) ORDER BY pickup_at DESC, id DESC LIMIT 5");
    $q->execute([$acc,ride_now($s)->format('Y-m-d 00:00:00'),$like,$like]); return $q->fetchAll(PDO::FETCH_ASSOC);
}
/** Offene Fahrten (aktiv, Abholzeit nicht älter als 12 h) bzw. alle der letzten 7 Tage – mit Fahrervorschlag. */
function ride_list(int $acc, string $scope='open'): array {
    ride_bootstrap(); $s=ride_settings($acc); $drivers=ride_drivers($acc,false); $now=ride_now($s);
    if($scope==='all'){ $q=th_db()->prepare("SELECT * FROM rides WHERE account_id=? AND (created_at > (NOW() - INTERVAL 7 DAY) OR pickup_at >= ?) ORDER BY pickup_at DESC, id DESC LIMIT 200"); $q->execute([$acc,$now->format('Y-m-d 00:00:00')]); }
    else { $q=th_db()->prepare("SELECT * FROM rides WHERE account_id=? AND status IN ('new','assigned','en_route','arrived','onboard') AND pickup_at >= ? ORDER BY pickup_at, id LIMIT 300"); $q->execute([$acc,$now->modify('-12 hours')->format('Y-m-d H:i:s')]); }
    $active=array_values(array_filter($drivers,fn($d)=>$d['active']));
    return array_map(function($r) use($s,$drivers,$active){ $p=ride_public($r,$s,$drivers); if(in_array($p['status'],['new','assigned'],true)){ $rk=ride_driver_rank($r,$active,$s); $p['driver_order']=$rk['order']; $p['suggest_driver_id']=$rk['suggest']; $p['unfit_driver_ids']=$rk['unfit']; } return $p; },$q->fetchAll(PDO::FETCH_ASSOC));
}
function ride_stats(int $acc, ?array $s=null): array {
    ride_bootstrap(); $s=$s ?: ride_settings($acc); $now=ride_now($s);
    $q=th_db()->prepare("SELECT COUNT(*) n, COALESCE(SUM(status='completed'),0) done, COALESCE(SUM(status IN ('cancelled','no_show')),0) lost, COALESCE(SUM(CASE WHEN status='completed' THEN COALESCE(price_cents,0) ELSE 0 END),0) rev
        FROM rides WHERE account_id=? AND pickup_at BETWEEN ? AND ?"); $q->execute([$acc,$now->format('Y-m-d 00:00:00'),$now->format('Y-m-d 23:59:59')]); $t=$q->fetch(PDO::FETCH_ASSOC);
    $o=th_db()->prepare("SELECT COUNT(*) n, COALESCE(SUM(asap=0 AND pickup_at > ?),0) pre FROM rides WHERE account_id=? AND status IN ('new','assigned','en_route','arrived','onboard') AND pickup_at >= ?");
    $o->execute([$now->modify('+60 minutes')->format('Y-m-d H:i:s'),$acc,$now->modify('-12 hours')->format('Y-m-d H:i:s')]); $op=$o->fetch(PDO::FETCH_ASSOC);
    return ['today'=>(int)$t['n'],'open'=>(int)$op['n'],'prebooked'=>(int)$op['pre'],'completed_today'=>(int)$t['done'],'cancelled_today'=>(int)$t['lost'],'revenue_today_cents'=>(int)$t['rev'],'revenue_today'=>ride_eur((int)$t['rev'])];
}
/** Daten für das Dispositions-Board (taxi-board.php) und die Board-API. */
function ride_board(int $acc): array {
    $s=ride_settings($acc); $rides=ride_list($acc,'open'); $drivers=ride_drivers($acc,true); $now=ride_now($s); $busy=[];
    foreach($rides as $r){ if(!$r['driver_id'] || !in_array($r['status'],['assigned','en_route','arrived','onboard'],true)) continue; $prio=['onboard'=>0,'arrived'=>1,'en_route'=>2,'assigned'=>3][$r['status']];
        if(!isset($busy[$r['driver_id']]) || $prio<$busy[$r['driver_id']]['prio']) $busy[$r['driver_id']]=['prio'=>$prio,'code'=>$r['code'],'status'=>$r['status'],'status_label'=>$r['status_label'],'when'=>$r['when']]; }
    return ['company'=>$s['name'] ?: 'Taxi-Zentrale','now'=>$now->format('H:i'),'server_iso'=>gmdate('Y-m-d\TH:i:s\Z'),'rides'=>$rides,
        'drivers'=>array_map(fn($d)=>ride_driver_public($d)+['job'=>isset($busy[$d['id']]) ? array_diff_key($busy[$d['id']],['prio'=>1]) : null],$drivers),
        'stats'=>ride_stats($acc,$s),'labels'=>RIDE_LABELS,'vehicles'=>array_map(fn($v)=>['key'=>$v['key'],'name'=>$v['name'],'seats'=>$v['seats']],$s['vehicles'])];
}
/** Demo (Konto 0): Fahrten laufen von selbst ab – Zuteilung nach 25 s, Anfahrt, „Taxi ist da“ nach wenigen Minuten, Fahrt, Ende. */
function ride_demo_tick(array $r): array {
    if((int)$r['account_id']!==0 || !in_array($r['status'],RIDE_ACTIVE,true)) return $r;
    $s=ride_settings(0); $now=time(); $asap=(int)$r['asap']===1; $id=(int)$r['id'];
    for($i=0;$i<6;$i++){
        $log=json_decode((string)$r['status_log'],true) ?: []; $ts=fn(string $k)=>isset($log[$k]) ? (int)strtotime((string)$log[$k]) : $now;
        $t0=$ts('new'); $at=ride_dt((string)$r['pickup_at'],ride_tz($s)); $pick=$at ? $at->getTimestamp() : $now; $eta=max(2,min(5,(int)($r['eta_min'] ?: 5)));
        [$next,$due]=match($r['status']){ 'new'=>['assigned',$t0+($asap?25:40)], 'assigned'=>['en_route',$asap ? $ts('assigned')+45 : $pick-max(5,(int)($r['eta_min'] ?: 8))*60],
            'en_route'=>['arrived',$asap ? max($t0+$eta*60,$ts('en_route')+60) : $pick], 'arrived'=>['onboard',$ts('arrived')+150],
            'onboard'=>['completed',$ts('onboard')+max(4,min(20,(int)($r['duration_min'] ?: 10)))*60], default=>[null,0] };
        if($next===null || $now<$due) break;
        $drv=null; if($next==='assigned'){ $rk=ride_driver_rank($r,ride_drivers(0),$s); $drv=$rk['suggest'] ?? ($rk['order'][0] ?? 1); }
        $res=ride_set_status(0,$id,$next,$drv); if(isset($res['error'])) break;
        $r=ride_by_id(0,$id); if(!$r) break;
        th_db()->prepare("UPDATE rides SET status_log=? WHERE id=? AND account_id=0")->execute([ride_log_add($r['status_log'],$next,null,min($now,$due)),$id]);
        $r=ride_by_id(0,$id); if(!$r) break;
    }
    return $r;
}

/* ---------- Benachrichtigungen ---------- */
function ride_mail_box(array $s, string $title, string $sub, array $rows, string $foot=''): string {
    $tr=''; foreach($rows as [$k,$v]) if($v!=='' && $v!==null) $tr.='<tr><td style="padding:7px 10px 7px 0;color:#6b7083;white-space:nowrap;vertical-align:top">'.htmlspecialchars($k,ENT_QUOTES).'</td><td style="padding:7px 0;color:#0f1222">'.$v.'</td></tr>';
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:26px;border:1px solid #e6e8ef;border-radius:18px;color:#0f1222">'
        .'<div style="font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#b8862b;font-weight:700">'.htmlspecialchars($s['name'] ?: 'Taxi-Zentrale',ENT_QUOTES).'</div>'
        .'<h2 style="margin:8px 0 4px;font-size:21px">'.$title.'</h2><p style="color:#6b7083;margin:0 0 14px;font-size:14px">'.$sub.'</p>'
        .'<table style="width:100%;border-collapse:collapse;font-size:14px">'.$tr.'</table>'.$foot.'</div>';
}
function ride_mail_rows(array $p): array {
    $h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES);
    return [['Abholung','<b>'.$h($p['pickup']).'</b>'.($p['pickup_checked'] ? ($p['pickup_geo']!=='' ? '<br><small style="color:#6b7083">'.$h($p['pickup_geo']).'</small>' : '') : '<br><small style="color:#9f2448">⚠ Adresse nicht auf der Karte gefunden – bitte prüfen</small>')],
        ['Ziel',$p['destination']!=='' ? $h($p['destination']).($p['distance_text']!=='' ? '<br><small style="color:#6b7083">'.$h($p['distance_text']).'</small>' : '') : '<span style="color:#6b7083">sagt der Fahrgast dem Fahrer</span>'],
        ['Zeit','<b>'.$h($p['asap'] ? 'sofort · '.$p['eta_text'] : $p['when_long']).'</b>'],['Fahrgast',$h($p['name']).($p['phone']!=='' ? ' · <a href="tel:'.$h(preg_replace('/[^0-9+]/','',$p['phone'])).'" style="color:#b8862b">'.$h($p['phone']).'</a>' : '')],
        ['Personen',$h($p['passengers'].' · '.$p['vehicle_name'])],['Extras',$h($p['extras'])],['Gepäck',$h($p['luggage'])],['Preis',$h($p['price_text'])],['Zahlung',$h($p['payment_label'])],['Notiz',$h($p['note'])],
        ['Fahrer',$p['driver'] ? $h($p['driver']['name'].' · '.$p['driver']['vehicle'].' · '.$p['driver']['plate']) : '']];
}
function ride_notify_zentrale(array $r, array $s, string $kind, string $extra=''): void {
    $acc=(int)$r['account_id']; $to=ride_owner_email($acc,$s); if($to===''){ ride_notify_log(['kind'=>'zentrale_'.$kind,'via'=>'none','ok'=>false]); return; }
    $p=ride_public($r,$s); $board=$s['board_token'] ? 'https://chat-help.com/chat/taxi-board.php?t='.$s['board_token'] : 'https://chat-help.com/chat/studio.php#taxi';
    $btn='<p style="margin:18px 0 0"><a href="'.htmlspecialchars($board,ENT_QUOTES).'" style="display:inline-block;background:#0f1222;color:#fff;text-decoration:none;padding:11px 16px;border-radius:12px;font-weight:600">Zum Dispositions-Board</a></p>';
    $chan=['phone'=>'Telefon','whatsapp'=>'WhatsApp','chat'=>'Chat','widget'=>'Website-Chat','studio'=>'Studio','hotel'=>'Hotel-Rezeption','demo'=>'Demo','phone-test'=>'Telefon-Test'][$p['channel']] ?? $p['channel'];
    [$subj,$title,$sub]=match($kind){
        'new'=>['🚕 Neue Fahrt '.$p['code'].' · '.($p['asap'] ? 'sofort' : $p['when']).' · '.mb_substr($p['pickup'],0,60),'🚕 Neue Fahrt '.$p['code'],($p['asap'] ? 'Sofortfahrt' : 'Vorbestellung').' · über '.htmlspecialchars($chan,ENT_QUOTES).' · '.$p['created'].' Uhr'],
        'cancel_en_route'=>['⚠ Storno während der Anfahrt · '.$p['code'],'⚠ Kunde hat storniert – Fahrer war unterwegs','Fahrt '.$p['code'].' · bitte Fahrer'.($p['driver'] ? ' '.htmlspecialchars($p['driver']['name'],ENT_QUOTES) : '').' informieren bzw. neu disponieren.'],
        default=>['⚠ Fahrer nicht erreicht · Fahrt '.$p['code'],'⚠ Fahrer konnte nicht benachrichtigt werden','Weder WhatsApp noch SMS haben geklappt – bitte den Fahrer direkt anrufen.'] };
    $foot=($extra!=='' ? '<pre style="font-family:Inter,Arial,sans-serif;white-space:pre-wrap;background:#f6f7fb;border-radius:12px;padding:12px;font-size:13px">'.htmlspecialchars($extra,ENT_QUOTES).'</pre>' : '').$btn;
    $ok=th_mail($to,$subj,ride_mail_box($s,$title,$sub,ride_mail_rows($p),$foot)); ride_notify_log(['kind'=>'zentrale_'.$kind,'via'=>'mail','to'=>$to,'ok'=>$ok]);
}
function ride_driver_text(array $p, array $s, array $drv, string $kind): string {
    if($kind==='cancelled') return "❌ Fahrt {$p['code']} STORNIERT".($p['cancel_by']==='Kunde' ? ' (Kunde hat abgesagt)' : '')."\n📍 {$p['pickup']}\nBitte nicht mehr anfahren. – ".($s['name'] ?: 'Zentrale');
    if($kind==='withdrawn') return "↩️ Fahrt {$p['code']} wurde dir von der Zentrale abgenommen (neu disponiert).\n📍 {$p['pickup']}";
    return "🚕 Neue Fahrt {$p['code']} · ".($s['name'] ?: 'Zentrale')."\n⏱ ".($p['asap'] ? 'SOFORT · '.$p['eta_text'] : $p['when_long'])."\n📍 Abholung: {$p['pickup']}".($p['pickup_checked'] ? '' : ' (⚠ ungeprüft)')
        .($p['destination']!=='' ? "\n🏁 Ziel: {$p['destination']}".($p['distance_text']!=='' ? " ({$p['distance_text']})" : '') : '')."\n👤 {$p['name']}".($p['phone']!=='' ? " · {$p['phone']}" : '')
        ."\n👥 {$p['passengers']} Pers. · {$p['vehicle_name']}".($p['extras']!=='' ? " · {$p['extras']}" : '').($p['luggage']!=='' ? " · Gepäck: {$p['luggage']}" : '')
        ."\n💶 {$p['price_text']} · {$p['payment_label']}".($p['note']!=='' ? "\n📝 {$p['note']}" : '')."\n🗺 {$p['maps_pickup']}\n📱 Deine Fahrten: https://chat-help.com/chat/taxi-driver.php?t={$drv['token']}";
}
function ride_notify_driver(array $r, array $s, array $drv, string $kind): void {
    $acc=(int)$r['account_id']; if($acc===0) return; $p=ride_public($r,$s); $txt=ride_driver_text($p,$s,$drv,$kind); $sent=false; $via='none';
    if($drv['phone']!==''){ $to=ride_e164($drv['phone'],$s); $os=ord_settings($acc); if(ord_wa_send($os,$to,$txt)){ $sent=true; $via='whatsapp'; } elseif(ord_sms_send($to,$txt)){ $sent=true; $via='sms'; } }
    ride_notify_log(['kind'=>'driver_'.$kind,'driver'=>$drv['id'],'via'=>$via,'ok'=>$sent]);
    if(!$sent && $kind!=='withdrawn') ride_notify_zentrale($r,$s,'driver_failed','An '.$drv['name'].($drv['phone']!=='' ? ' ('.$drv['phone'].')' : ' (keine Handynummer hinterlegt)').":\n\n".$txt);
}
function ride_notify_customer(array $r, array $s, string $kind): void {
    $acc=(int)$r['account_id']; if($acc===0) return; $p=ride_public($r,$s); $d=$p['driver']; $co=$s['name'] ?: 'Ihre Taxi-Zentrale';
    $car=$d ? trim($d['vehicle'].($d['plate']!=='' ? ' · Kennzeichen '.$d['plate'] : ''),' ·') : '';
    $msg=match($kind){
        'assigned'=>"🚕 Ihr Taxi ist bestellt (Code {$p['code']}): ".($d ? "Fahrer {$d['name']}".($car!=='' ? ", $car" : '') : 'Ein Fahrer ist eingeteilt').' · '.($p['asap'] ? 'Ankunft '.$p['eta_text'] : 'Abholung '.$p['when_long']).". Live-Status: {$p['track']}",
        'arrived'=>"🚕 Ihr Taxi ist da".($car!=='' ? ": $car" : '').($d ? " · Fahrer {$d['name']}" : '').". Gute Fahrt! – $co",
        default=>'' };
    if($msg===''){ return; } $ok=false; $via='none';
    if($r['channel']==='whatsapp' && $r['wa_from']!==''){ $ok=ord_wa_send(ord_settings($acc),(string)$r['wa_from'],$msg); $via='whatsapp'; }
    elseif($r['channel']==='phone' && $r['phone']!==''){ $ok=ord_sms_send(ride_e164((string)$r['phone'],$s),($kind==='arrived' ? '' : $co.': ').$msg); $via='sms'; }
    ride_notify_log(['kind'=>'customer_'.$kind,'via'=>$via,'ok'=>$ok]);
}

/* ---------- Werkzeuge für den Agenten ---------- */
function ride_tariff_text(array $s): string {
    $t=$s['tariff'];
    $x='Grundpreis '.ride_eur($t['base_cents']).', '.ride_eur($t['km1_cents']).'/km bis '.str_replace('.',',',(string)$t['km1_upto']).' km, danach '.ride_eur($t['km2_cents']).'/km, Wartezeit '.ride_eur($t['wait_min_cents']).'/Min';
    if($t['night_pct']>0) $x.=', Nachtzuschlag '.$t['night_pct'].' % ('.$t['night_from'].'–'.$t['night_to'].' Uhr)';
    if($t['min_fare_cents']>0) $x.=', Mindestfahrpreis '.ride_eur($t['min_fare_cents']); if($t['booking_fee_cents']>0) $x.=', Buchungsgebühr '.ride_eur($t['booking_fee_cents']);
    return $x;
}
function ride_area_text(array $s): string {
    $city=ride_city($s); $x='Abholung im Umkreis von '.$s['area_km'].' km um die Zentrale'.($city!=='' ? ' ('.$city.')' : '');
    if($s['area_plz']!=='') $x.=' sowie in den PLZ '.implode(', ',array_slice(explode(',',$s['area_plz']),0,40)).(count(explode(',',$s['area_plz']))>40 ? ' …' : '');
    return $x.'; Ziele überall (auch Fernfahrten)';
}
function ride_info(int $acc): array {
    $s=ride_settings($acc); $now=ride_now($s); $eta=ride_eta($acc,$s,$s['lat'],$s['lon']);
    return ['company'=>$s['name'],'phone'=>$s['phone'],'zentrale'=>$s['address'],'now'=>ride_wd($now,true).', '.$now->format('d.m.Y, H:i').' Uhr','service_area'=>ride_area_text($s),
        'tariff'=>['grundpreis'=>ride_eur($s['tariff']['base_cents']),'km_preis_bis'=>ride_eur($s['tariff']['km1_cents']).'/km bis '.str_replace('.',',',(string)$s['tariff']['km1_upto']).' km','km_preis_danach'=>ride_eur($s['tariff']['km2_cents']).'/km',
            'wartezeit'=>ride_eur($s['tariff']['wait_min_cents']).'/Min','nachtzuschlag'=>$s['tariff']['night_pct']>0 ? $s['tariff']['night_pct'].' % von '.$s['tariff']['night_from'].' bis '.$s['tariff']['night_to'].' Uhr' : 'keiner',
            'mindestfahrpreis'=>$s['tariff']['min_fare_cents']>0 ? ride_eur($s['tariff']['min_fare_cents']) : 'keiner','buchungsgebuehr'=>$s['tariff']['booking_fee_cents']>0 ? ride_eur($s['tariff']['booking_fee_cents']) : 'keine'],
        'tariff_text'=>ride_tariff_text($s),'vehicles'=>array_map(fn($v)=>['key'=>$v['key'],'name'=>$v['name'],'seats'=>$v['seats'],'price_factor'=>$v['factor']],$s['vehicles']),
        'stands'=>array_map(fn($st)=>['name'=>$st['name'],'address'=>$st['address']],$s['stands']),'fixed_prices'=>array_map(fn($f)=>['name'=>$f['name'],'price'=>ride_eur($f['price_cents'])],$s['fixed_routes']),
        'payment_methods'=>array_map(fn($k)=>RIDE_PAYMENTS[$k] ?? $k,explode(',',$s['payment_methods'])),
        'prebooking'=>['possible'=>$s['prebook_days']>0,'max_days'=>$s['prebook_days'],'min_lead_min'=>RIDE_MIN_LEAD],'eta_now'=>'ca. '.$eta['min'].' Min (je nach Abholort)',
        'note'=>'Preise sind Richtpreise – es gilt der Taxameter (außer Festpreis). Genaue Werte je Fahrt über estimate_ride.'];
}
function ride_tools(): array {
    return [
      ['name'=>'get_taxi_info','description'=>'Taxi-Zentrale: Tarif (Grundpreis, Kilometerpreise, Wartezeit, Nachtzuschlag), Fahrzeugtypen, Taxistände, Einsatzgebiet, Festpreise (z. B. Flughafen), Zahlarten, Vorbestellung und aktuelle Wartezeit.','input_schema'=>['type'=>'object','properties'=>(object)[],'required'=>[]]],
      ['name'=>'estimate_ride','description'=>'Fahrt kalkulieren, OHNE zu buchen: prüft Abholort (Einsatzgebiet) und Ziel auf der Karte, liefert Strecke, Fahrzeit, Richtpreis bzw. Festpreis, Wartezeit bei „sofort“ und das passende Fahrzeug. Vor der Zusammenfassung aufrufen.','input_schema'=>['type'=>'object','properties'=>[
          'pickup'=>['type'=>'string','description'=>'Abholort: Straße Hausnummer (Ort) oder bekannter Ort wie Hauptbahnhof, Hotelname, Taxistand'],'destination'=>['type'=>'string','description'=>'Ziel (optional, nötig für den Richtpreis)'],
          'time'=>['type'=>'string','description'=>'„sofort“ oder „HH:MM“ (heute), „morgen HH:MM“, „YYYY-MM-DD HH:MM“'],'vehicle'=>['type'=>'string','description'=>'standard | kombi | grossraum | rollstuhl (Schlüssel aus get_taxi_info)'],
          'passengers'=>['type'=>'integer','description'=>'Anzahl Personen (Standard 1)'],'extras'=>['type'=>'string','description'=>'z. B. Kindersitz, Rollstuhl, Hund']],'required'=>['pickup']]],
      ['name'=>'book_ride','description'=>'Taxi verbindlich bestellen – NUR nachdem der Kunde die Zusammenfassung ausdrücklich bestätigt hat. Liefert Code, Wartezeit bzw. Abholzeit, Preis und Status-Link.','input_schema'=>['type'=>'object','properties'=>[
          'name'=>['type'=>'string','description'=>'Name des Fahrgasts'],'phone'=>['type'=>'string','description'=>'Rückrufnummer; am Telefon/WhatsApp nur, wenn der Kunde eine andere als die eigene Nummer möchte'],
          'pickup'=>['type'=>'string'],'destination'=>['type'=>'string'],'time'=>['type'=>'string','description'=>'„sofort“ oder „HH:MM“, „morgen HH:MM“, „YYYY-MM-DD HH:MM“'],
          'passengers'=>['type'=>'integer'],'luggage'=>['type'=>'string','description'=>'z. B. 2 Koffer, Kinderwagen'],'vehicle'=>['type'=>'string','description'=>'standard | kombi | grossraum | rollstuhl'],
          'extras'=>['type'=>'string','description'=>'Kindersitz (Alter), Rollstuhl, Hund …'],'payment'=>['type'=>'string','description'=>'bar | karte | app | rechnung'],'note'=>['type'=>'string','description'=>'Hinweis für den Fahrer (z. B. Hinterhof, Klingel)'],
          'confirm_address'=>['type'=>'boolean','description'=>'true nur, wenn der Abholort nicht auf der Karte gefunden wurde und der Kunde ihn genau so bestätigt hat']],'required'=>['name','pickup','time']]],
      ['name'=>'get_ride_status','description'=>'Status bestellter Fahrten per Code oder Telefonnummer (Fahrten der letzten 24 Stunden und offene Vorbestellungen): Fahrer, Fahrzeug, Kennzeichen, Wartezeit.','input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string'],'phone'=>['type'=>'string']],'required'=>[]]],
      ['name'=>'cancel_ride','description'=>'Fahrt per Code stornieren – nur nach ausdrücklicher Bestätigung des Kunden; möglich, solange das Taxi noch nicht da ist.','input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string']],'required'=>['code']]],
    ];
}
function ride_exec(int $acc, string $name, array $in, string $channel, string $from=''): array {
    foreach(array_keys($in) as $k) if(!is_string($k) || $k==='' || $k[0]==='_' || $k==='force') unset($in[$k]);   // interne Schalter nur aus dem Studio
    try{
        switch($name){
            case 'get_taxi_info': return ride_info($acc);
            case 'estimate_ride': return ride_estimate($acc,$in);
            case 'book_ride': return ride_book($acc,$in,$channel,$from);
            case 'get_ride_status':
                $code=trim((string)($in['code'] ?? ''));
                if($code!==''){ $r=ride_by_code($code,$acc); $rows=$r ? [$r] : []; }
                else { $ph=trim((string)($in['phone'] ?? '')) ?: $from; if(strlen(preg_replace('/\D/','',$ph))<6) return ['error'=>'code_or_phone_required','hint'=>'Bitte den Fahrtcode (6 Zeichen) oder die Telefonnummer der Buchung erfragen.']; $rows=ride_find_by_phone($acc,$ph); }
                $s=ride_settings($acc); $out=[];
                foreach($rows as $r){ if($acc===0) $r=ride_demo_tick($r); $out[]=ride_for_ai(ride_public($r,$s)); }
                return ['rides'=>$out,'count'=>count($out)]+($out ? [] : ['hint'=>$code!=='' ? 'Kein Treffer für diesen Code – bitte buchstabieren lassen (6 Zeichen, z. B. K7M2X9).' : 'Keine Fahrt der letzten 24 Stunden unter dieser Nummer.']);
            case 'cancel_ride': return ride_cancel_customer($acc,(string)($in['code'] ?? ''));
        }
    }catch(Throwable $e){ error_log('CH ride_exec '.$name.': '.$e->getMessage()); return ['error'=>'internal_error','hint'=>'Technischer Fehler – bitte entschuldigen und die Telefonnummer der Zentrale nennen.']; }
    return ['error'=>'unknown_tool','hint'=>'Dieses Werkzeug gibt es im Taxi-Modul nicht.'];
}
/** Zusatz für den System-Prompt (Fakten + Regeln). */
function ride_system_hint(int $acc, string $channel='chat'): string {
    $s=ride_settings($acc); $now=ride_now($s); $eta=ride_eta($acc,$s,$s['lat'],$s['lon']); $phone=str_starts_with($channel,'phone');
    $veh=implode(', ',array_map(fn($v)=>$v['name'].' ['.$v['key'].', '.$v['seats'].' Plätze'.((float)$v['factor']!==1.0 ? ', Preisfaktor '.str_replace('.',',',(string)$v['factor']) : '').']',$s['vehicles']));
    $stands=$s['stands'] ? implode('; ',array_map(fn($st)=>$st['name'].($st['address']!=='' ? ' ('.$st['address'].')' : ''),$s['stands'])) : 'keine hinterlegt';
    $fixed=$s['fixed_routes'] ? implode('; ',array_map(fn($f)=>$f['name'].' '.ride_eur($f['price_cents']),$s['fixed_routes'])) : 'keine';
    $pay=implode(', ',array_map(fn($k)=>RIDE_PAYMENTS[$k] ?? $k,explode(',',$s['payment_methods'])));
    $chanRule=$phone
        ? 'TELEFON: Die Anrufernummer ist die Kontaktnummer – nicht danach fragen; nur wenn der Anrufer eine andere Nummer möchte, diese als phone übergeben (ist die Nummer unterdrückt, meldet book_ride phone_required → Rückrufnummer erfragen). Adressen kurz wiederholen. Den Code langsam mit Pausen buchstabieren (Feld code_spoken). KEINE Links oder URLs vorlesen – der Kunde bekommt, falls eingerichtet, eine SMS. Kurze Sätze, zügig – Kunden stehen oft an der Straße.'
        : ($channel==='whatsapp' ? 'WHATSAPP: Die Absendernummer ist die Kontaktnummer – nicht danach fragen. Nach der Buchung den Status-Link (Feld track) mitschicken; Zuteilung und „Taxi ist da“ kommen automatisch per WhatsApp.'
        : 'CHAT: Eine Rückrufnummer für den Fahrer ist Pflicht – bitte erfragen. Nach der Buchung den Status-Link (Feld track) mitschicken.');
    return "\n\nTAXI-ZENTRALE (aktiv) für ".($s['name'] ?: 'das Taxiunternehmen').($s['address']!=='' ? ' · Zentrale '.$s['address'] : '').($s['phone']!=='' ? ' · Tel. '.$s['phone'] : '').'. Jetzt: '.ride_wd($now,true).', '.$now->format('d.m.Y, H:i').' Uhr ('.$s['tz'].').'
        .' Einsatzgebiet: '.ride_area_text($s).'. Tarif (Richtwerte): '.ride_tariff_text($s).'. Festpreise: '.$fixed.'. Fahrzeuge: '.$veh.'. Taxistände: '.$stands.'. Zahlung: '.$pay.'.'
        .' Wartezeit bei „sofort“ derzeit ca. '.$eta['min'].' Min (genau je Abholort über estimate_ride). '.($s['prebook_days']>0 ? 'Vorbestellung bis '.$s['prebook_days'].' Tage im Voraus, mindestens '.RIDE_MIN_LEAD.' Min Vorlauf.' : 'Keine Vorbestellungen – nur Sofortfahrten.')
        ."\nREGELN: Reihenfolge: 1) Abholort (Straße + Hausnummer oder bekannter Ort wie Bahnhof, Hotel, Taxistand) → 2) Zeit (sofort oder wann; relative Angaben wie „morgen früh halb sechs“ als „morgen 05:30“ übergeben) → 3) Personen (Gepäck, Kindersitz, Rollstuhl, Tier nur ansprechen, wenn relevant) → 4) Ziel (optional, aber nötig für den Preis; der Kunde darf es auch dem Fahrer sagen) → 5) Name."
        .' Dann estimate_ride aufrufen, das Ergebnis in EINEM Satz zusammenfassen (Abholort, Zeit, Personen/Fahrzeug, Preis) und fragen: „Soll ich das Taxi so bestellen?“ – erst nach einem klaren Ja book_ride.'
        .' Preise und Wartezeiten NUR aus den Werkzeugen nennen: Richtpreis immer als „ca. … (Richtpreis, es gilt der Taxameter)“, Festpreis als Festpreis. Mehr als 4 Personen → Großraumtaxi, Rollstuhl → Rollstuhl-Taxi (wählt das Werkzeug automatisch; Hinweise in notes kurz erwähnen).'
        .' Liegt der Abholort außerhalb des Einsatzgebiets: freundlich ablehnen und auf ein örtliches Taxiunternehmen verweisen. Liefert ein Werkzeug error + hint, dem hint folgen.'
        .' Nach book_ride: Code nennen, bei „sofort“ die Wartezeit (ca.), sonst die Abholzeit, und den Preis als Richtpreis bzw. Festpreis. Niemals einen bestimmten Fahrer oder eine exakte Ankunftsminute versprechen.'
        .' Status/„Wo bleibt mein Taxi?“ → get_ride_status (Code oder Telefonnummer). Storno → Code erfragen, Bestätigung einholen, dann cancel_ride.'
        .' Medizinischer Notfall: sofort auf den Notruf 112 verweisen – kein Taxi für Notfälle. '.$chanRule;
}

/* ---------- Studio-REST (/v1/ride/…) ---------- */
function ride_api_bundle(int $aid): array {
    $s=ride_settings($aid);
    return ['settings'=>ride_settings_public($s),'drivers'=>array_map(fn($d)=>array_diff_key($d,['token'=>1,'account_id'=>1]),ride_drivers($aid,false)),'links'=>ride_links($aid),'stats'=>ride_stats($aid,$s),
        'labels'=>['statuses'=>RIDE_LABELS,'payments'=>RIDE_PAYMENTS,'driver_statuses'=>RIDE_DRIVER_STATUSES]];
}
function ride_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    $route=trim($route,'/'); if($route!=='ride' && !str_starts_with($route,'ride/')) return null;
    $method=strtoupper($method);
    try{
        ride_bootstrap();
        switch($method.' '.$route){
            case 'GET ride/settings': return [200,ride_api_bundle($aid)];
            case 'POST ride/settings': ride_settings_save($aid,$body); return [200,['ok'=>true]+ride_api_bundle($aid)];
            case 'GET ride/rides':
                $scope=($_GET['scope'] ?? 'open')==='all' ? 'all' : 'open';
                return [200,['scope'=>$scope,'rides'=>ride_list($aid,$scope),'drivers'=>array_map('ride_driver_public',ride_drivers($aid,true)),'stats'=>ride_stats($aid)]];
            case 'POST ride/status':
                $did=isset($body['driver_id']) && $body['driver_id']!=='' && $body['driver_id']!==null ? (int)$body['driver_id'] : null;
                $res=ride_set_status($aid,(int)($body['id'] ?? 0),(string)($body['status'] ?? ''),$did,'Zentrale');
                return [isset($res['error']) ? ($res['error']==='not_found' ? 404 : 400) : 200,$res];
            case 'POST ride/create':
                $res=ride_book($aid,['_manual'=>1]+array_diff_key($body,['_manual'=>1]),'studio'); return [isset($res['error']) ? 422 : 200,$res];
            case 'GET ride/estimate':
                $in=[]; foreach(['pickup','destination','time','vehicle','passengers','extras'] as $k) if(isset($_GET[$k]) && is_scalar($_GET[$k])) $in[$k]=(string)$_GET[$k];
                $res=ride_estimate($aid,$in); return [isset($res['error']) ? 422 : 200,$res];
            case 'POST ride/driver':
                $res=ride_driver_set_status($aid,(int)($body['id'] ?? $body['driver_id'] ?? 0),(string)($body['status'] ?? '')); return [isset($res['error']) ? ($res['error']==='not_found' ? 404 : 400) : 200,$res];
        }
        return [404,['error'=>'not_found','routes'=>['GET|POST /v1/ride/settings','GET /v1/ride/rides?scope=open|all','POST /v1/ride/status','POST /v1/ride/create','GET /v1/ride/estimate','POST /v1/ride/driver']]];
    }catch(Throwable $e){ error_log('CH ride_api '.$route.': '.$e->getMessage()); return [500,['error'=>'server_error','message'=>'Interner Fehler im Taxi-Modul.']]; }
}
