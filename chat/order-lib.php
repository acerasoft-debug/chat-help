<?php
/**
 * ChatHelp — Bestell-Agent für Pizzerien, Restaurants, Lieferdienste (WhatsApp · Telefon · Website-Chat)
 * =======================================================================================================
 * • Speisekarte (Kategorien, Optionen/Größen), Liefergebiete (PLZ → Gebühr/Mindestbestellwert), Öffnungszeiten, Zahlarten
 * • Adressprüfung: PLZ-Zone + Geocoding (OpenStreetMap Nominatim, gecacht) → Entfernung, Karten-Link für den Fahrer
 * • Bestellung mit Positionen, Summen, ETA, Code; Status-Workflow new → confirmed → preparing → ready → out_for_delivery → delivered
 * • Fahrer: Zuweisung, Benachrichtigung (WhatsApp/SMS/E-Mail) + mobile Fahrerseite (driver.php) mit Navigation & „Zugestellt“
 * • WhatsApp: Gesprächsspeicher je Absender (wa_sessions), Antwort über Meta Cloud API oder Twilio
 * • Konto 0 = Demo „Pizzeria Napoli“ (keine Benachrichtigungen, Bestellungen nach 2 Tagen gelöscht)
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/appt-lib.php';

const ORD_STATUSES = ['new','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'];
const ORD_DEMO = ['name'=>'Pizzeria Napoli','address'=>'Friesenstraße 50, 50670 Köln','lat'=>50.9432,'lon'=>6.9416,'phone'=>'0221 9876543','min_order_cents'=>1500,'delivery_fee_cents'=>250,'eta_delivery_min'=>35,'eta_pickup_min'=>20,'pickup_discount_pct'=>10,'payment_methods'=>'bar,karte,paypal',
  'hours'=>['0'=>[['12:00','22:30']],'1'=>[['11:30','22:30']],'2'=>[['11:30','22:30']],'3'=>[['11:30','22:30']],'4'=>[['11:30','22:30']],'5'=>[['11:30','23:00']],'6'=>[['12:00','23:00']]],
  'zones'=>[['plz'=>'50667,50668,50670,50672,50674','name'=>'Innenstadt','fee_cents'=>0,'min_cents'=>1500],['plz'=>'50676,50677,50678,50679,50733,50735','name'=>'Südstadt / Nippes','fee_cents'=>250,'min_cents'=>2000],['plz'=>'50823,50825,50827','name'=>'Ehrenfeld','fee_cents'=>350,'min_cents'=>2500]],
  'menu'=>[
    ['id'=>1,'category'=>'Pizza','name'=>'Margherita','description'=>'Tomaten, Mozzarella, Basilikum','price_cents'=>850,'options'=>[['name'=>'Größe','choices'=>[['label'=>'Normal 28 cm','delta'=>0],['label'=>'Groß 32 cm','delta'=>300]]]],'tags'=>'vegetarisch'],
    ['id'=>2,'category'=>'Pizza','name'=>'Salami','description'=>'Tomaten, Mozzarella, Salami','price_cents'=>990,'options'=>[['name'=>'Größe','choices'=>[['label'=>'Normal 28 cm','delta'=>0],['label'=>'Groß 32 cm','delta'=>300]]]],'tags'=>''],
    ['id'=>3,'category'=>'Pizza','name'=>'Funghi','description'=>'Tomaten, Mozzarella, Champignons','price_cents'=>990,'options'=>[['name'=>'Größe','choices'=>[['label'=>'Normal 28 cm','delta'=>0],['label'=>'Groß 32 cm','delta'=>300]]]],'tags'=>'vegetarisch'],
    ['id'=>4,'category'=>'Pizza','name'=>'Diavola','description'=>'Scharfe Salami, Peperoni, Zwiebeln','price_cents'=>1090,'options'=>[['name'=>'Größe','choices'=>[['label'=>'Normal 28 cm','delta'=>0],['label'=>'Groß 32 cm','delta'=>300]]]],'tags'=>'scharf'],
    ['id'=>5,'category'=>'Pizza','name'=>'Quattro Formaggi','description'=>'Vier Käse','price_cents'=>1150,'options'=>[['name'=>'Größe','choices'=>[['label'=>'Normal 28 cm','delta'=>0],['label'=>'Groß 32 cm','delta'=>300]]]],'tags'=>'vegetarisch'],
    ['id'=>6,'category'=>'Pasta','name'=>'Spaghetti Bolognese','description'=>'Hausgemachte Sauce','price_cents'=>1190,'options'=>[],'tags'=>''],
    ['id'=>7,'category'=>'Pasta','name'=>'Lasagne','description'=>'Klassisch, überbacken','price_cents'=>1250,'options'=>[],'tags'=>''],
    ['id'=>8,'category'=>'Salate','name'=>'Insalata Mista','description'=>'Gemischter Salat, Dressing nach Wahl','price_cents'=>650,'options'=>[],'tags'=>'vegan'],
    ['id'=>9,'category'=>'Dessert','name'=>'Tiramisu','description'=>'Hausgemacht','price_cents'=>550,'options'=>[],'tags'=>'vegetarisch'],
    ['id'=>10,'category'=>'Getränke','name'=>'Cola 0,33 l','description'=>'','price_cents'=>250,'options'=>[],'tags'=>''],
    ['id'=>11,'category'=>'Getränke','name'=>'Wasser 0,5 l','description'=>'still oder sprudel','price_cents'=>200,'options'=>[],'tags'=>'']]];

function ord_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS order_settings (
        account_id INT PRIMARY KEY, order_on TINYINT(1) DEFAULT 0, name VARCHAR(120) DEFAULT '', address VARCHAR(200) DEFAULT '', lat DECIMAL(9,6) NULL, lon DECIMAL(9,6) NULL, phone VARCHAR(40) DEFAULT '',
        hours TEXT NULL, zones TEXT NULL, delivery_on TINYINT(1) DEFAULT 1, pickup_on TINYINT(1) DEFAULT 1, min_order_cents INT DEFAULT 1500, delivery_fee_cents INT DEFAULT 250,
        eta_delivery_min INT DEFAULT 40, eta_pickup_min INT DEFAULT 20, payment_methods VARCHAR(120) DEFAULT 'bar,karte', notify_email VARCHAR(190) DEFAULT '',
        wa_provider VARCHAR(10) DEFAULT 'meta', wa_phone_id VARCHAR(40) DEFAULT '', wa_token TEXT NULL, wa_number VARCHAR(40) DEFAULT '', wa_verify VARCHAR(64) DEFAULT '',
        board_token CHAR(24) NULL, tz VARCHAR(40) DEFAULT 'Europe/Berlin', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, category VARCHAR(60) DEFAULT '', name VARCHAR(120) NOT NULL, description VARCHAR(300) DEFAULT '',
        price_cents INT DEFAULT 0, options TEXT NULL, tags VARCHAR(120) DEFAULT '', active TINYINT(1) DEFAULT 1, sort INT DEFAULT 0, INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, code CHAR(8) NOT NULL UNIQUE, channel VARCHAR(12) DEFAULT 'chat', type VARCHAR(10) DEFAULT 'delivery', status VARCHAR(20) DEFAULT 'new',
        customer_name VARCHAR(120) DEFAULT '', phone VARCHAR(40) DEFAULT '', wa_from VARCHAR(40) DEFAULT '', address_text VARCHAR(240) DEFAULT '', plz VARCHAR(10) DEFAULT '', city VARCHAR(80) DEFAULT '',
        lat DECIMAL(9,6) NULL, lon DECIMAL(9,6) NULL, zone_name VARCHAR(60) DEFAULT '', distance_km DECIMAL(5,1) NULL, items TEXT NOT NULL, subtotal_cents INT DEFAULT 0, fee_cents INT DEFAULT 0, total_cents INT DEFAULT 0,
        payment VARCHAR(20) DEFAULT 'bar', note VARCHAR(400) DEFAULT '', eta_at DATETIME NULL, driver_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
        INDEX(account_id, created_at), INDEX(account_id, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS drivers (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, name VARCHAR(80) NOT NULL, phone VARCHAR(40) DEFAULT '', token CHAR(24) NOT NULL UNIQUE, active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS wa_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, wa_from VARCHAR(40) NOT NULL, profile VARCHAR(80) DEFAULT '', messages MEDIUMTEXT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq (account_id, wa_from)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS geo_cache (h CHAR(32) PRIMARY KEY, lat DECIMAL(9,6) NULL, lon DECIMAL(9,6) NULL, display VARCHAR(240) DEFAULT '', plz VARCHAR(10) DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $c=$p->query("SHOW COLUMNS FROM order_settings")->fetchAll(PDO::FETCH_COLUMN); if(!in_array('pickup_discount_pct',$c,true)) $p->exec("ALTER TABLE order_settings ADD COLUMN pickup_discount_pct INT DEFAULT 0");
    $c=$p->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN); if(!in_array('discount_cents',$c,true)) $p->exec("ALTER TABLE orders ADD COLUMN discount_cents INT DEFAULT 0");
    if(random_int(1,40)===1){ $p->exec("DELETE FROM orders WHERE account_id=0 AND created_at < (NOW() - INTERVAL 2 DAY)"); $p->exec("DELETE FROM wa_sessions WHERE updated_at < (NOW() - INTERVAL 7 DAY)"); }
}

/* ---------- Einstellungen / Karte / Zonen ---------- */
function ord_settings(int $acc): array {
    ord_bootstrap();
    $s=th_db()->prepare("SELECT * FROM order_settings WHERE account_id=?"); $s->execute([$acc]); $r=$s->fetch(PDO::FETCH_ASSOC);
    if(!$r){ $r=['account_id'=>$acc,'order_on'=>$acc===0?1:0,'name'=>'','address'=>'','lat'=>null,'lon'=>null,'phone'=>'','hours'=>null,'zones'=>null,'delivery_on'=>1,'pickup_on'=>1,'min_order_cents'=>1500,'delivery_fee_cents'=>250,'eta_delivery_min'=>40,'eta_pickup_min'=>20,'pickup_discount_pct'=>0,'payment_methods'=>'bar,karte','notify_email'=>'','wa_provider'=>'meta','wa_phone_id'=>'','wa_token'=>'','wa_number'=>'','wa_verify'=>'','board_token'=>null,'tz'=>'Europe/Berlin'];
        if($acc===0){ foreach(['name','address','lat','lon','phone','min_order_cents','delivery_fee_cents','eta_delivery_min','eta_pickup_min','pickup_discount_pct','payment_methods'] as $k) $r[$k]=ORD_DEMO[$k]; $r['hours']=json_encode(ORD_DEMO['hours']); $r['zones']=json_encode(ORD_DEMO['zones']); } }
    $h=json_decode((string)($r['hours']??''),true); $r['hours']=is_array($h)&&$h ? $h : ['0'=>[['12:00','22:00']],'1'=>[['11:30','22:00']],'2'=>[['11:30','22:00']],'3'=>[['11:30','22:00']],'4'=>[['11:30','22:00']],'5'=>[['11:30','23:00']],'6'=>[['12:00','23:00']]];
    $z=json_decode((string)($r['zones']??''),true); $r['zones']=is_array($z) ? $z : [];
    foreach(['order_on','delivery_on','pickup_on','min_order_cents','delivery_fee_cents','eta_delivery_min','eta_pickup_min','pickup_discount_pct'] as $k) $r[$k]=(int)($r[$k] ?? 0);
    $r['lat']=$r['lat']!==null?(float)$r['lat']:null; $r['lon']=$r['lon']!==null?(float)$r['lon']:null;
    return $r;
}
function ord_settings_save(int $acc, array $d): array {
    ord_bootstrap(); $c=ord_settings($acc);
    $hours=$c['hours']; if(isset($d['hours'])&&is_array($d['hours'])){ $hours=[]; for($w=0;$w<=6;$w++){ $rows=[]; foreach((array)($d['hours'][(string)$w] ?? []) as $iv){ if(is_array($iv)&&count($iv)>=2&&preg_match('/^\d{2}:\d{2}$/',$iv[0])&&preg_match('/^\d{2}:\d{2}$/',$iv[1])&&$iv[0]<$iv[1]) $rows[]=[$iv[0],$iv[1]]; } $hours[(string)$w]=$rows; } }
    $zones=$c['zones']; if(isset($d['zones'])&&is_array($d['zones'])){ $zones=[]; foreach($d['zones'] as $z){ if(!is_array($z)) continue; $plz=implode(',',array_filter(array_map('trim',explode(',',(string)($z['plz']??''))),fn($x)=>preg_match('/^\d{4,5}$/',$x))); if($plz==='') continue; $zones[]=['plz'=>$plz,'name'=>mb_substr(trim((string)($z['name']??'')),0,60),'fee_cents'=>max(0,(int)($z['fee_cents'] ?? round(((float)str_replace(',','.',(string)($z['fee']??0)))*100))),'min_cents'=>max(0,(int)($z['min_cents'] ?? round(((float)str_replace(',','.',(string)($z['min']??0)))*100)))]; } }
    $lat=$c['lat']; $lon=$c['lon']; if(isset($d['lat'],$d['lon']) && is_numeric($d['lat']) && is_numeric($d['lon'])){ $lat=(float)$d['lat']; $lon=(float)$d['lon']; }
    $address=mb_substr(trim((string)($d['address']??$c['address'])),0,200);
    if($address!=='' && ($address!==$c['address'] || $lat===null) && empty($d['lat'])){ $g=ord_geocode($address); if($g){ $lat=$g['lat']; $lon=$g['lon']; } }
    $pm=implode(',',array_values(array_intersect(array_map('trim',explode(',',strtolower((string)($d['payment_methods']??$c['payment_methods'])))),['bar','karte','paypal','online','rechnung'])));
    $wa_token=array_key_exists('wa_token',$d) ? (string)$d['wa_token'] : (string)$c['wa_token']; if($wa_token==='••••••••') $wa_token=(string)$c['wa_token'];
    $v=['order_on'=>(int)!empty($d['order_on'] ?? $c['order_on']),'name'=>mb_substr(trim((string)($d['name']??$c['name'])),0,120),'address'=>$address,'lat'=>$lat,'lon'=>$lon,'phone'=>mb_substr(trim((string)($d['phone']??$c['phone'])),0,40),
        'hours'=>json_encode($hours),'zones'=>json_encode($zones,JSON_UNESCAPED_UNICODE),'delivery_on'=>(int)!empty($d['delivery_on'] ?? $c['delivery_on']),'pickup_on'=>(int)!empty($d['pickup_on'] ?? $c['pickup_on']),
        'min_order_cents'=>max(0,(int)($d['min_order_cents'] ?? $c['min_order_cents'])),'delivery_fee_cents'=>max(0,(int)($d['delivery_fee_cents'] ?? $c['delivery_fee_cents'])),
        'eta_delivery_min'=>max(5,min(240,(int)($d['eta_delivery_min'] ?? $c['eta_delivery_min']))),'eta_pickup_min'=>max(5,min(240,(int)($d['eta_pickup_min'] ?? $c['eta_pickup_min']))),'pickup_discount_pct'=>max(0,min(50,(int)($d['pickup_discount_pct'] ?? $c['pickup_discount_pct']))),'payment_methods'=>$pm?:'bar',
        'notify_email'=>filter_var((string)($d['notify_email']??$c['notify_email']),FILTER_VALIDATE_EMAIL)?:'','wa_provider'=>in_array($d['wa_provider']??$c['wa_provider'],['meta','twilio'],true)?($d['wa_provider']??$c['wa_provider']):'meta',
        'wa_phone_id'=>preg_replace('/[^0-9a-zA-Z_]/','',(string)($d['wa_phone_id']??$c['wa_phone_id'])),'wa_token'=>mb_substr(trim($wa_token),0,600),'wa_number'=>preg_replace('/[^0-9+]/','',(string)($d['wa_number']??$c['wa_number'])),
        'wa_verify'=>mb_substr(preg_replace('/[^A-Za-z0-9_-]/','',(string)($d['wa_verify']??$c['wa_verify'])),0,64),'board_token'=>$c['board_token'] ?: bin2hex(random_bytes(12)),'tz'=>in_array($d['tz']??'',timezone_identifiers_list(),true)?$d['tz']:$c['tz']];
    th_db()->prepare("INSERT INTO order_settings(account_id,order_on,name,address,lat,lon,phone,hours,zones,delivery_on,pickup_on,min_order_cents,delivery_fee_cents,eta_delivery_min,eta_pickup_min,pickup_discount_pct,payment_methods,notify_email,wa_provider,wa_phone_id,wa_token,wa_number,wa_verify,board_token,tz)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE order_on=VALUES(order_on),name=VALUES(name),address=VALUES(address),lat=VALUES(lat),lon=VALUES(lon),phone=VALUES(phone),hours=VALUES(hours),zones=VALUES(zones),delivery_on=VALUES(delivery_on),pickup_on=VALUES(pickup_on),min_order_cents=VALUES(min_order_cents),delivery_fee_cents=VALUES(delivery_fee_cents),eta_delivery_min=VALUES(eta_delivery_min),eta_pickup_min=VALUES(eta_pickup_min),pickup_discount_pct=VALUES(pickup_discount_pct),payment_methods=VALUES(payment_methods),notify_email=VALUES(notify_email),wa_provider=VALUES(wa_provider),wa_phone_id=VALUES(wa_phone_id),wa_token=VALUES(wa_token),wa_number=VALUES(wa_number),wa_verify=VALUES(wa_verify),board_token=VALUES(board_token),tz=VALUES(tz),updated_at=NOW()")
      ->execute([$acc,$v['order_on'],$v['name'],$v['address'],$v['lat'],$v['lon'],$v['phone'],$v['hours'],$v['zones'],$v['delivery_on'],$v['pickup_on'],$v['min_order_cents'],$v['delivery_fee_cents'],$v['eta_delivery_min'],$v['eta_pickup_min'],$v['pickup_discount_pct'],$v['payment_methods'],$v['notify_email'],$v['wa_provider'],$v['wa_phone_id'],$v['wa_token'],$v['wa_number'],$v['wa_verify'],$v['board_token'],$v['tz']]);
    if(isset($d['menu'])&&is_array($d['menu'])) ord_menu_save($acc,$d['menu']);
    if(isset($d['drivers'])&&is_array($d['drivers'])) ord_drivers_save($acc,$d['drivers']);
    return ord_settings($acc);
}
function ord_settings_public(array $s): array { $s['wa_token']=$s['wa_token']?'••••••••':''; return $s; }
function ord_menu(int $acc, bool $activeOnly=true): array {
    ord_bootstrap(); if($acc===0) return ORD_DEMO['menu'];
    $s=th_db()->prepare("SELECT id,category,name,description,price_cents,options,tags,active,sort FROM menu_items WHERE account_id=?".($activeOnly?" AND active=1":"")." ORDER BY sort,id"); $s->execute([$acc]);
    return array_map(fn($r)=>['id'=>(int)$r['id'],'category'=>$r['category'],'name'=>$r['name'],'description'=>$r['description'],'price_cents'=>(int)$r['price_cents'],'options'=>json_decode((string)$r['options'],true)?:[],'tags'=>$r['tags'],'active'=>(int)$r['active'],'sort'=>(int)$r['sort']],$s->fetchAll(PDO::FETCH_ASSOC));
}
/** Optionen-Kurzschreibweise: "Größe: Normal 0 | Groß +3,00; Extra: Käse +1,50" */
function ord_parse_options($raw): array {
    if(is_array($raw)) return $raw; $out=[];
    foreach(array_filter(array_map('trim',explode(';',(string)$raw))) as $grp){ if(!str_contains($grp,':')) continue; [$name,$rest]=array_map('trim',explode(':',$grp,2)); $choices=[];
        foreach(array_filter(array_map('trim',explode('|',$rest))) as $ch){ if(preg_match('/^(.*?)\s*([+-]?\d+(?:[.,]\d{1,2})?)?\s*$/u',$ch,$m)){ $choices[]=['label'=>trim($m[1]),'delta'=>isset($m[2])&&$m[2]!==''?(int)round(((float)str_replace(',','.',$m[2]))*100):0]; } }
        if($name!==''&&$choices) $out[]=['name'=>$name,'choices'=>$choices]; }
    return $out;
}
function ord_menu_save(int $acc, array $list): void {
    ord_bootstrap(); $p=th_db(); $keep=[]; $i=0;
    foreach($list as $it){ if(!is_array($it)) continue; $name=mb_substr(trim((string)($it['name']??'')),0,120); if($name==='') continue; $i++;
        $price=isset($it['price_cents'])?max(0,(int)$it['price_cents']):max(0,(int)round(((float)str_replace(',','.',(string)($it['price']??0)))*100));
        $opt=json_encode(ord_parse_options($it['options']??[]),JSON_UNESCAPED_UNICODE); $id=(int)($it['id']??0);
        if($id>0){ $chk=$p->prepare("SELECT COUNT(*) FROM menu_items WHERE id=? AND account_id=?"); $chk->execute([$id,$acc]); if((int)$chk->fetchColumn()===0) $id=0; }
        $vals=[mb_substr(trim((string)($it['category']??'')),0,60),$name,mb_substr(trim((string)($it['description']??'')),0,300),$price,$opt,mb_substr(trim((string)($it['tags']??'')),0,120),(!isset($it['active'])||!empty($it['active']))?1:0,$i];
        if($id>0) $p->prepare("UPDATE menu_items SET category=?,name=?,description=?,price_cents=?,options=?,tags=?,active=?,sort=? WHERE id=? AND account_id=?")->execute([...$vals,$id,$acc]);
        else { $p->prepare("INSERT INTO menu_items(account_id,category,name,description,price_cents,options,tags,active,sort) VALUES(?,?,?,?,?,?,?,?,?)")->execute([$acc,...$vals]); $id=(int)$p->lastInsertId(); }
        $keep[]=$id; }
    if($keep) $p->prepare("UPDATE menu_items SET active=0 WHERE account_id=? AND id NOT IN (".implode(',',array_map('intval',$keep)).")")->execute([$acc]);
}
function ord_drivers(int $acc, bool $activeOnly=false): array {
    ord_bootstrap(); if($acc===0) return [['id'=>1,'name'=>'Marco','phone'=>'+49 170 0000001','token'=>'demo','active'=>1],['id'=>2,'name'=>'Ayşe','phone'=>'+49 170 0000002','token'=>'demo2','active'=>1]];
    $s=th_db()->prepare("SELECT id,name,phone,token,active FROM drivers WHERE account_id=?".($activeOnly?" AND active=1":"")." ORDER BY id"); $s->execute([$acc]); return array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name'],'phone'=>$r['phone'],'token'=>$r['token'],'active'=>(int)$r['active'],'url'=>'https://chat-help.com/chat/driver.php?t='.$r['token']],$s->fetchAll(PDO::FETCH_ASSOC));
}
function ord_drivers_save(int $acc, array $list): void {
    ord_bootstrap(); $p=th_db(); $keep=[];
    foreach($list as $d){ if(!is_array($d)) continue; $name=mb_substr(trim((string)($d['name']??'')),0,80); if($name==='') continue; $phone=mb_substr(preg_replace('/[^0-9+ ]/','',(string)($d['phone']??'')),0,40); $id=(int)($d['id']??0);
        if($id>0){ $chk=$p->prepare("SELECT COUNT(*) FROM drivers WHERE id=? AND account_id=?"); $chk->execute([$id,$acc]); if((int)$chk->fetchColumn()===0) $id=0; }
        if($id>0) $p->prepare("UPDATE drivers SET name=?,phone=?,active=1 WHERE id=? AND account_id=?")->execute([$name,$phone,$id,$acc]);
        else { $p->prepare("INSERT INTO drivers(account_id,name,phone,token,active) VALUES(?,?,?,?,1)")->execute([$acc,$name,$phone,bin2hex(random_bytes(12))]); $id=(int)$p->lastInsertId(); }
        $keep[]=$id; }
    if($keep) $p->prepare("UPDATE drivers SET active=0 WHERE account_id=? AND id NOT IN (".implode(',',array_map('intval',$keep)).")")->execute([$acc]); elseif(!$list) $p->prepare("UPDATE drivers SET active=0 WHERE account_id=?")->execute([$acc]);
}
function ord_driver_by_token(string $t): ?array { ord_bootstrap(); if(!preg_match('/^[a-f0-9]{24}$/',$t)) return null; $s=th_db()->prepare("SELECT * FROM drivers WHERE token=? AND active=1"); $s->execute([$t]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }

/* ---------- Öffnungszeiten / Adresse / Geo ---------- */
function ord_tz(array $s): DateTimeZone { try{ return new DateTimeZone($s['tz'] ?: 'Europe/Berlin'); }catch(Throwable $e){ return new DateTimeZone('Europe/Berlin'); } }
function ord_is_open(array $s, ?DateTimeImmutable $at=null): array {
    $tz=ord_tz($s); $now=$at ?: new DateTimeImmutable('now',$tz); $ranges=$s['hours'][(string)(int)$now->format('w')] ?? []; $hm=$now->format('H:i');
    foreach($ranges as [$o,$c]) if($hm>=$o && $hm<$c) return ['open'=>true,'until'=>$c];
    $next=''; foreach($ranges as [$o,$c]) if($hm<$o){ $next=$o; break; }
    return ['open'=>false,'next'=>$next,'today'=>$ranges];
}
function ord_parse_address(string $text): array {
    $t=trim(preg_replace('/\s+/',' ',$text)); $plz=''; $city=''; $street=''; $no='';
    if(preg_match('/\b(\d{5})\b\s*([A-Za-zÄÖÜäöüß .-]{2,40})?/u',$t,$m)){ $plz=$m[1]; $city=trim($m[2] ?? ''); }
    if(preg_match('/^(.*?\D)\s*(\d{1,4}\s?[a-zA-Z]?(?:[\/-]\d+)?)\s*,?\s*(?:\d{5}|$)/u',$t,$m)){ $street=trim($m[1],' ,'); $no=trim($m[2]); }
    return ['street'=>$street,'number'=>$no,'plz'=>$plz,'city'=>$city,'raw'=>$t];
}
/** Nominatim (OpenStreetMap) mit Cache. Rückgabe lat/lon/display oder null. */
function ord_geocode(string $address): ?array {
    ord_bootstrap(); $address=trim($address); if(mb_strlen($address)<6) return null; $h=md5(mb_strtolower($address)); $p=th_db();
    $s=$p->prepare("SELECT lat,lon,display,plz FROM geo_cache WHERE h=?"); $s->execute([$h]); if($r=$s->fetch(PDO::FETCH_ASSOC)){ return $r['lat']!==null ? ['lat'=>(float)$r['lat'],'lon'=>(float)$r['lon'],'display'=>$r['display'],'plz'=>$r['plz']] : null; }
    $ch=curl_init('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&addressdetails=1&countrycodes=de,at,ch&q='.rawurlencode($address));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>['User-Agent: ChatHelp-Bestellagent/1.0 (support@chat-help.com)','Accept-Language: de']]);
    $j=json_decode((string)curl_exec($ch),true); curl_close($ch); $hit=is_array($j)&&!empty($j[0]) ? $j[0] : null;
    $res=$hit ? ['lat'=>(float)$hit['lat'],'lon'=>(float)$hit['lon'],'display'=>mb_substr((string)($hit['display_name']??''),0,240),'plz'=>(string)($hit['address']['postcode']??'')] : null;
    try{ $p->prepare("INSERT IGNORE INTO geo_cache(h,lat,lon,display,plz) VALUES(?,?,?,?,?)")->execute([$h,$res['lat']??null,$res['lon']??null,$res['display']??'',$res['plz']??'']); }catch(Throwable $e){}
    return $res;
}
function ord_distance_km(float $la1,float $lo1,float $la2,float $lo2): float { $r=6371; $dLa=deg2rad($la2-$la1); $dLo=deg2rad($lo2-$lo1); $a=sin($dLa/2)**2+cos(deg2rad($la1))*cos(deg2rad($la2))*sin($dLo/2)**2; return round($r*2*atan2(sqrt($a),sqrt(1-$a)),1); }
function ord_zone(array $s, string $plz): ?array { foreach($s['zones'] as $z){ if(in_array($plz,array_map('trim',explode(',',$z['plz'])),true)) return $z; } return null; }
/** Adresse prüfen: Zone, Gebühr, Mindestbestellwert, Geodaten, Entfernung. */
function ord_check_address(int $acc, string $address): array {
    $s=ord_settings($acc); $pa=ord_parse_address($address); $geo=ord_geocode($address);
    $plz=$pa['plz'] ?: (string)($geo['plz'] ?? ''); $zone=$plz!=='' ? ord_zone($s,$plz) : null;
    $dist=($geo && $s['lat']!==null) ? ord_distance_km($s['lat'],$s['lon'],$geo['lat'],$geo['lon']) : null;
    $deliverable = $s['delivery_on'] && ($zone!==null || (!$s['zones'] && $plz!==''));
    if(!$deliverable && $pa['street']==='' && $plz==='') return ['ok'=>false,'reason'=>'unvollständig','hint'=>'Bitte Straße, Hausnummer, PLZ und Ort nennen.','parsed'=>$pa];
    return ['ok'=>$deliverable,'reason'=>$deliverable?null:'ausserhalb_liefergebiet','parsed'=>$pa,'plz'=>$plz,'zone'=>$zone['name']??null,'fee_cents'=>$zone['fee_cents']??$s['delivery_fee_cents'],'min_cents'=>$zone['min_cents']??$s['min_order_cents'],
        'geo'=>$geo?['lat'=>$geo['lat'],'lon'=>$geo['lon'],'display'=>$geo['display']]:null,'distance_km'=>$dist,'pickup_possible'=>(bool)$s['pickup_on'],'eta_min'=>$s['eta_delivery_min']];
}

/* ---------- Bestellung ---------- */
function ord_code(): string { $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $c=''; for($i=0;$i<6;$i++) $c.=$a[random_int(0,strlen($a)-1)]; return $c; }
function ord_find_item(array $menu, $ref): ?array {
    if(is_numeric($ref)){ foreach($menu as $m) if((int)$m['id']===(int)$ref) return $m; }
    $q=mb_strtolower(trim((string)$ref)); $best=null; $bs=0;
    foreach($menu as $m){ $n=mb_strtolower($m['name']); if($n===$q) return $m; if(str_contains($n,$q)||str_contains($q,$n)){ $sc=mb_strlen($n); if($sc>$bs){ $bs=$sc; $best=$m; } } }
    if(!$best){ foreach($menu as $m){ similar_text($q,mb_strtolower($m['name']),$pct); if($pct>$bs && $pct>72){ $bs=$pct; $best=$m; } } }
    return $best;
}
function ord_create(int $acc, array $d, string $channel, string $waFrom=''): array {
    $s=ord_settings($acc); if(!$s['order_on']) return ['error'=>'orders_disabled'];
    $type=($d['type']??'delivery')==='pickup'?'pickup':'delivery'; if($type==='delivery'&&!$s['delivery_on']) return ['error'=>'delivery_disabled','hint'=>'Nur Abholung möglich.']; if($type==='pickup'&&!$s['pickup_on']) return ['error'=>'pickup_disabled'];
    $open=ord_is_open($s); if(!$open['open'] && empty($d['force'])) return ['error'=>'closed','next_open'=>$open['next'] ?: null,'hint'=>'Derzeit geschlossen – Vorbestellung nur mit Wunschzeit innerhalb der Öffnungszeiten.'];
    $menu=ord_menu($acc); $items=[]; $sub=0; $problems=[];
    foreach((array)($d['items']??[]) as $it){ if(!is_array($it)) continue; $m=ord_find_item($menu,$it['id'] ?? $it['name'] ?? ''); if(!$m){ $problems[]='Nicht auf der Karte: '.(string)($it['name']??$it['id']??'?'); continue; }
        $qty=max(1,min(50,(int)($it['qty']??1))); $unit=(int)$m['price_cents']; $chosen=[];
        foreach((array)($it['options']??[]) as $optSel){ $label=is_array($optSel)?(string)($optSel['choice']??$optSel['label']??''):(string)$optSel; if($label==='') continue;
            foreach($m['options'] as $grp){ foreach($grp['choices'] as $c){ if(mb_strtolower($c['label'])===mb_strtolower($label) || str_contains(mb_strtolower($c['label']),mb_strtolower($label))){ $unit+=(int)$c['delta']; $chosen[]=$grp['name'].': '.$c['label']; continue 3; } } } }
        $items[]=['id'=>$m['id'],'name'=>$m['name'],'qty'=>$qty,'options'=>$chosen,'unit_cents'=>$unit,'total_cents'=>$unit*$qty,'note'=>mb_substr((string)($it['note']??''),0,120)]; $sub+=$unit*$qty; }
    if(!$items) return ['error'=>'no_items','problems'=>$problems,'hint'=>'Keine gültigen Positionen. Karte mit get_menu prüfen.'];
    $name=mb_substr(trim((string)($d['name']??'')),0,120); $phone=mb_substr(trim((string)($d['phone']??'')),0,40); if($phone==='' && $waFrom!=='') $phone='+'.ltrim($waFrom,'+');
    if($name==='') return ['error'=>'name_required','problems'=>$problems]; if($phone==='') return ['error'=>'phone_required','problems'=>$problems];
    $addr=['ok'=>true,'fee_cents'=>0,'min_cents'=>$s['min_order_cents'],'plz'=>'','zone'=>'','geo'=>null,'distance_km'=>null,'parsed'=>['city'=>'']];
    if($type==='delivery'){ $addr=ord_check_address($acc,(string)($d['address']??'')); if(!$addr['ok']) return ['error'=>$addr['reason']==='unvollständig'?'address_incomplete':'outside_delivery_area','address'=>$addr,'problems'=>$problems]; }
    $fee=$type==='delivery'?(int)$addr['fee_cents']:0; $min=(int)($addr['min_cents'] ?? $s['min_order_cents']);
    if($type==='delivery' && $sub<$min) return ['error'=>'below_minimum','min_cents'=>$min,'subtotal_cents'=>$sub,'hint'=>'Mindestbestellwert '.number_format($min/100,2,',','.').' € für Lieferung.','problems'=>$problems];
    $pay=strtolower(trim((string)($d['payment']??'bar'))); $allowed=explode(',',$s['payment_methods']); if(!in_array($pay,$allowed,true)) $pay=$allowed[0] ?? 'bar';
    $tz=ord_tz($s); $eta=(new DateTimeImmutable('now',$tz))->modify('+'.($type==='delivery'?$s['eta_delivery_min']:$s['eta_pickup_min']).' minutes');
    if(!empty($d['requested_time']) && preg_match('/^(\d{2}):(\d{2})$/',(string)$d['requested_time'],$m)){ $rt=(new DateTimeImmutable('today',$tz))->setTime((int)$m[1],(int)$m[2]); if($rt>$eta) $eta=$rt; }
    $disc=$type==='pickup' && $s['pickup_discount_pct']>0 ? (int)round($sub*$s['pickup_discount_pct']/100) : 0;   // Abholrabatt (Einstellung)
    $code=ord_code(); $total=$sub-$disc+$fee;
    th_db()->prepare("INSERT INTO orders(account_id,code,channel,type,status,customer_name,phone,wa_from,address_text,plz,city,lat,lon,zone_name,distance_km,items,subtotal_cents,fee_cents,total_cents,payment,note,eta_at,discount_cents) VALUES(?,?,?,?,'new',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
      ->execute([$acc,$code,mb_substr($channel,0,12),$type,$name,$phone,mb_substr($waFrom,0,40),$type==='delivery'?mb_substr((string)$d['address'],0,240):'',(string)($addr['plz']??''),mb_substr((string)($addr['parsed']['city']??''),0,80),$addr['geo']['lat']??null,$addr['geo']['lon']??null,(string)($addr['zone']??''),$addr['distance_km'],json_encode($items,JSON_UNESCAPED_UNICODE),$sub,$fee,$total,$pay,mb_substr(trim((string)($d['note']??'')),0,400),$eta->format('Y-m-d H:i:s'),$disc]);
    $o=ord_by_code($code); if($acc!==0) ord_notify_restaurant($o,$s);
    return ['ok'=>true,'order'=>ord_public($o,$s),'problems'=>$problems ?: null];
}
function ord_by_code(string $code): ?array { ord_bootstrap(); $code=strtoupper(preg_replace('/[^A-Z0-9]/i','',$code)); if($code==='') return null; $s=th_db()->prepare("SELECT * FROM orders WHERE code=?"); $s->execute([$code]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function ord_public(array $o, ?array $s=null): array {
    $s=$s ?: ord_settings((int)$o['account_id']); $tz=ord_tz($s); $items=json_decode((string)$o['items'],true)?:[];
    $drv=null; if($o['driver_id']){ foreach(ord_drivers((int)$o['account_id']) as $d) if((int)$d['id']===(int)$o['driver_id']) $drv=['name'=>$d['name'],'phone'=>$d['phone']]; }
    return ['id'=>(int)$o['id'],'code'=>$o['code'],'status'=>$o['status'],'type'=>$o['type'],'channel'=>$o['channel'],'name'=>$o['customer_name'],'phone'=>$o['phone'],'address'=>$o['address_text'],'plz'=>$o['plz'],'zone'=>$o['zone_name'],'distance_km'=>$o['distance_km']!==null?(float)$o['distance_km']:null,
        'lat'=>$o['lat']!==null?(float)$o['lat']:null,'lon'=>$o['lon']!==null?(float)$o['lon']:null,'items'=>$items,'items_text'=>implode(', ',array_map(fn($i)=>$i['qty'].'× '.$i['name'].($i['options']?' ('.implode(', ',$i['options']).')':''),$items)),
        'subtotal_cents'=>(int)$o['subtotal_cents'],'discount_cents'=>(int)($o['discount_cents'] ?? 0),'fee_cents'=>(int)$o['fee_cents'],'total_cents'=>(int)$o['total_cents'],'total'=>number_format($o['total_cents']/100,2,',','.').' €','payment'=>$o['payment'],'note'=>$o['note'],
        'eta'=>$o['eta_at']?(new DateTimeImmutable($o['eta_at'],$tz))->format('H:i'):null,'created'=>($ct=th_dbtime($o['created_at'],$tz)) ? $ct->format('H:i') : '','created_at'=>$o['created_at'],'driver'=>$drv,
        'maps'=>$o['lat']!==null?'https://www.google.com/maps/dir/?api=1&destination='.$o['lat'].','.$o['lon']:($o['address_text']?'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($o['address_text']):null),'restaurant'=>$s['name'],
        'track'=>'https://chat-help.com/chat/order.php?c='.$o['code']];
}
function ord_orders(int $acc, string $scope='open'): array {
    ord_bootstrap(); $s=ord_settings($acc);
    $q = $scope==='open' ? "SELECT * FROM orders WHERE account_id=? AND status NOT IN ('delivered','cancelled') AND created_at > (NOW() - INTERVAL 1 DAY) ORDER BY created_at" : "SELECT * FROM orders WHERE account_id=? AND created_at > (NOW() - INTERVAL 7 DAY) ORDER BY created_at DESC LIMIT 200";
    $st=th_db()->prepare($q); $st->execute([$acc]); return array_map(fn($o)=>ord_public($o,$s),$st->fetchAll(PDO::FETCH_ASSOC));
}
function ord_set_status(int $acc, int $id, string $status, ?int $driverId=null): array {
    if(!in_array($status,ORD_STATUSES,true)) return ['error'=>'bad_status'];
    $st=th_db()->prepare("SELECT * FROM orders WHERE id=? AND account_id=?"); $st->execute([$id,$acc]); $o=$st->fetch(PDO::FETCH_ASSOC); if(!$o) return ['error'=>'not_found'];
    th_db()->prepare("UPDATE orders SET status=?, driver_id=COALESCE(?,driver_id), updated_at=NOW() WHERE id=?")->execute([$status,$driverId,$id]);
    $o=ord_by_code($o['code']); $s=ord_settings($acc);
    if($acc!==0){ if($status==='out_for_delivery' && $o['driver_id']) ord_notify_driver($o,$s); if(in_array($status,['confirmed','out_for_delivery','ready','delivered','cancelled'],true)) ord_notify_customer($o,$s,$status); }
    return ['ok'=>true,'order'=>ord_public($o,$s)];
}
function ord_find_by_phone(int $acc, string $phone): array {
    $d=preg_replace('/\D/','',$phone); if(strlen($d)<6) return [];
    $s=th_db()->prepare("SELECT * FROM orders WHERE account_id=? AND created_at > (NOW() - INTERVAL 1 DAY) AND REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-','') LIKE ? ORDER BY created_at DESC LIMIT 3"); $s->execute([$acc,'%'.substr($d,-8)]); return array_map(fn($o)=>ord_public($o),$s->fetchAll(PDO::FETCH_ASSOC));
}

/* ---------- Benachrichtigungen ---------- */
function ord_notify_restaurant(array $o, array $s): void {
    $p=ord_public($o,$s); $to=$s['notify_email'] ?: ord_owner_email((int)$o['account_id']); if(!$to) return;
    $rows=implode('',array_map(fn($i)=>'<tr><td>'.$i['qty'].'× '.htmlspecialchars($i['name']).($i['options']?'<br><small>'.htmlspecialchars(implode(', ',$i['options'])).'</small>':'').($i['note']?'<br><small>'.htmlspecialchars($i['note']).'</small>':'').'</td><td style="text-align:right">'.number_format($i['total_cents']/100,2,',','.').' €</td></tr>',$p['items']));
    th_mail($to,'🍕 Neue Bestellung '.$p['code'].' · '.$p['total'].' · '.($p['type']==='delivery'?'Lieferung':'Abholung'),
      '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:26px;border:1px solid #e6e8ef;border-radius:18px"><h2 style="margin:0 0 6px">Bestellung '.$p['code'].' <span style="color:#6b7083;font-weight:400">· '.$p['created'].' Uhr · '.htmlspecialchars($p['channel']).'</span></h2>'
      .'<p><b>'.htmlspecialchars($p['name']).'</b> · <a href="tel:'.htmlspecialchars($p['phone']).'">'.htmlspecialchars($p['phone']).'</a><br>'.($p['type']==='delivery'?'🚗 Lieferung: '.htmlspecialchars($p['address']).($p['zone']?' ('.htmlspecialchars($p['zone']).($p['distance_km']!==null?', '.$p['distance_km'].' km':'').')':''):'🛍️ Abholung').' · ETA '.$p['eta'].' Uhr · Zahlung: '.htmlspecialchars($p['payment']).'</p>'
      .'<table style="width:100%;border-collapse:collapse;font-size:14px">'.$rows.''.($p['discount_cents']?'<tr><td>Abholrabatt</td><td style="text-align:right">−'.number_format($p['discount_cents']/100,2,',','.').' €</td></tr>':'').'<tr><td style="border-top:1px solid #e6e8ef;padding-top:8px">Liefergebühr</td><td style="border-top:1px solid #e6e8ef;padding-top:8px;text-align:right">'.number_format($p['fee_cents']/100,2,',','.').' €</td></tr><tr><td><b>Gesamt</b></td><td style="text-align:right"><b>'.$p['total'].'</b></td></tr></table>'
      .($p['note']?'<p>📝 '.htmlspecialchars($p['note']).'</p>':'').'<p style="font-size:13px;color:#6b7083">Status &amp; Fahrer im Studio: https://chat-help.com/chat/studio.php (Bestellungen)</p></div>');
}
function ord_notify_driver(array $o, array $s): void {
    $p=ord_public($o,$s); $drv=null; foreach(ord_drivers((int)$o['account_id']) as $d) if((int)$d['id']===(int)$o['driver_id']) $drv=$d; if(!$drv) return;
    $txt="Neue Fahrt {$p['code']} · ".($s['name']?:'Restaurant')."\n{$p['items_text']}\n📍 {$p['address']}\n👤 {$p['name']} · {$p['phone']}\n💶 {$p['total']} ({$p['payment']})".($p['note']?"\n📝 {$p['note']}":'')."\n🗺 {$p['maps']}\n✅ Zugestellt melden: https://chat-help.com/chat/driver.php?t={$drv['token']}";
    /* WhatsApp nur im 24-h-Fenster zuverlässig (sonst nimmt Meta/Twilio an und verwirft später) → dann zuerst SMS, danach E-Mail an das Restaurant */
    $acc=(int)$o['account_id']; $sent=false;
    if($drv['phone']){
        $win=ord_wa_window_open($acc,$drv['phone']);
        if($win) $sent=ord_wa_send($s,$drv['phone'],$txt);
        if(!$sent) $sent=ord_sms_send($drv['phone'],$txt,$acc);
        if(!$sent && !$win) ord_wa_send($s,$drv['phone'],$txt);   // Versuch außerhalb des Fensters – gilt nicht als zugestellt, E-Mail folgt
    }
    if(!$sent){ $to=$s['notify_email'] ?: ord_owner_email((int)$o['account_id']); if($to) th_mail($to,'Fahrer '.$drv['name'].' konnte nicht benachrichtigt werden – Bestellung '.$p['code'],'<pre style="font-family:Inter,Arial">'.htmlspecialchars($txt).'</pre>'); }
}
function ord_notify_customer(array $o, array $s, string $status): void {
    $p=ord_public($o,$s);
    $msg=['confirmed'=>"✅ Bestellung {$p['code']} bestätigt! ".($p['type']==='delivery'?"Lieferung voraussichtlich um {$p['eta']} Uhr.":"Abholbereit um {$p['eta']} Uhr.")." Gesamt {$p['total']} ({$p['payment']}). Status live: {$p['track']}",
          'ready'=>"🛍️ Bestellung {$p['code']} ist fertig".($p['type']==='pickup'?' zur Abholung.':'.'),
          'out_for_delivery'=>"🚗 Bestellung {$p['code']} ist unterwegs".($p['driver']?" mit {$p['driver']['name']}":'').". Bis gleich! {$p['track']}",
          'delivered'=>"Guten Appetit! 🍕 Danke für die Bestellung bei {$s['name']}.",
          'cancelled'=>"Bestellung {$p['code']} wurde storniert. Bei Fragen: {$s['phone']}"][$status] ?? null;
    if(!$msg) return;
    if($o['channel']==='whatsapp' && $o['wa_from']){ ord_wa_send($s,$o['wa_from'],$msg); return; }
    /* Telefon-Bestellungen: SMS an die Anrufernummer (nur Bestätigung/Unterwegs/Storno, wenn Twilio-SMS eingerichtet ist) */
    if($o['channel']==='phone' && $o['phone'] && in_array($status,['confirmed','out_for_delivery','cancelled'],true)) ord_sms_send($o['phone'],($s['name']?$s['name'].': ':'').$msg,(int)$o['account_id']);
}
function ord_owner_email(int $acc): string { if($acc<=0) return ''; $s=th_db()->prepare("SELECT email FROM token_accounts WHERE id=?"); $s->execute([$acc]); return (string)($s->fetchColumn() ?: ''); }
/**
 * WhatsApp senden: Meta Cloud API (wa_phone_id + wa_token) oder Twilio.
 * Twilio: eigener Twilio-Account des Kunden (Studio → Verbindungen; Absender wa_number gehört ihm, Twilio prüft das) –
 * der Plattform-Account darf nur Nummern verwenden, die in db.php per TWILIO_WA_NUMBERS diesem Konto zugewiesen sind
 * ("+4922112345:17,+4930999:23"). So kann kein Kunde im Namen eines anderen Betriebs senden.
 */
function ord_twilio_wa_allowed(int $acc, string $num): bool {
    if(!(defined('TWILIO_WA_NUMBERS') && TWILIO_WA_NUMBERS)) return false;
    foreach(explode(',',(string)TWILIO_WA_NUMBERS) as $pair){ [$n,$a]=array_pad(explode(':',trim($pair),2),2,''); if($n!=='' && th_same_phone($n,$num) && (int)$a===$acc) return true; }
    return false;
}
function ord_wa_send(array $s, string $to, string $text): bool {
    $e=th_e164($to); if($e==='') return false; $acc=(int)($s['account_id'] ?? 0);
    if($s['wa_provider']==='meta' && $s['wa_phone_id'] && $s['wa_token']){
        $ch=curl_init('https://graph.facebook.com/v20.0/'.$s['wa_phone_id'].'/messages');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>json_encode(['messaging_product'=>'whatsapp','to'=>ltrim($e,'+'),'type'=>'text','text'=>['body'=>mb_substr($text,0,4000)]],JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$s['wa_token'],'Content-Type: application/json']]);
        $raw=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if($c<200 || $c>=300) error_log('CH wa_send meta '.$acc.': '.$c.' '.mb_substr((string)$raw,0,200));
        return $c>=200&&$c<300;
    }
    if($s['wa_provider']==='twilio' && $s['wa_number'] && ($tw=th_twilio($acc))){
        $from=th_e164((string)$s['wa_number']); if($from==='' || (!$tw['own'] && !ord_twilio_wa_allowed($acc,$from))) return false;
        $ch=curl_init('https://api.twilio.com/2010-04-01/Accounts/'.$tw['sid'].'/Messages.json');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_USERPWD=>$tw['sid'].':'.$tw['token'],CURLOPT_POSTFIELDS=>http_build_query(['From'=>'whatsapp:'.$from,'To'=>'whatsapp:'.$e,'Body'=>mb_substr($text,0,1600)])]);
        $raw=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if($c<200 || $c>=300) error_log('CH wa_send twilio '.$acc.': '.$c.' '.mb_substr((string)$raw,0,200));
        return $c>=200&&$c<300;
    }
    return false;
}
/** SMS über Twilio: eigener Account (Absender twilio_from) oder Plattform (TWILIO_SMS_FROM). */
function ord_sms_send(string $to, string $text, int $acc=0): bool {
    $e=th_e164($to); $tw=th_twilio($acc); if($e==='' || !$tw) return false;
    $from=$tw['from'] ?: ($tw['own'] ? '' : (defined('TWILIO_SMS_FROM') ? (string)TWILIO_SMS_FROM : '')); if($from==='') return false;
    $ch=curl_init('https://api.twilio.com/2010-04-01/Accounts/'.$tw['sid'].'/Messages.json');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_USERPWD=>$tw['sid'].':'.$tw['token'],CURLOPT_POSTFIELDS=>http_build_query(['From'=>$from,'To'=>$e,'Body'=>mb_substr($text,0,1500)])]);
    $raw=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($c<200 || $c>=300) error_log('CH sms_send '.$acc.': '.$c.' '.mb_substr((string)$raw,0,200));
    return $c>=200&&$c<300;
}
/** Hat diese Nummer in den letzten 23 h an den WhatsApp-Kanal des Kontos geschrieben? (Nur dann sind freie WhatsApp-Texte zustellbar.) */
function ord_wa_window_open(int $acc, string $phone): bool {
    $d=ltrim(th_e164($phone),'+'); if($d==='') return false;
    $s=th_db()->prepare("SELECT COUNT(*) FROM wa_sessions WHERE account_id=? AND wa_from=? AND updated_at > (NOW() - INTERVAL 23 HOUR)"); $s->execute([$acc,$d]); return (int)$s->fetchColumn()>0;
}

/* ---------- WhatsApp-Gesprächsspeicher ---------- */
function ord_wa_session(int $acc, string $from): array { ord_bootstrap(); $s=th_db()->prepare("SELECT messages FROM wa_sessions WHERE account_id=? AND wa_from=?"); $s->execute([$acc,$from]); $m=json_decode((string)$s->fetchColumn(),true); return is_array($m)?$m:[]; }
function ord_wa_session_save(int $acc, string $from, array $msgs, string $profile=''): void {
    $msgs=array_slice(array_values(array_filter($msgs,fn($m)=>is_string($m['content']??null))),-16);
    th_db()->prepare("INSERT INTO wa_sessions(account_id,wa_from,profile,messages) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE messages=VALUES(messages), profile=IF(VALUES(profile)='',profile,VALUES(profile)), updated_at=NOW()")->execute([$acc,$from,mb_substr($profile,0,80),json_encode($msgs,JSON_UNESCAPED_UNICODE)]);
}

/* ---------- Werkzeuge für den Agenten ---------- */
function ord_tools(): array {
    return [
      ['name'=>'get_menu','description'=>'Speisekarte mit Kategorien, Preisen, Optionen (Größen/Extras) und Hinweisen (vegetarisch, scharf …). Optional nach Kategorie oder Suchbegriff filtern.','input_schema'=>['type'=>'object','properties'=>['category'=>['type'=>'string'],'query'=>['type'=>'string']],'required'=>[]]],
      ['name'=>'check_delivery_address','description'=>'Lieferadresse prüfen: liegt sie im Liefergebiet, welche Liefergebühr und welcher Mindestbestellwert gelten, voraussichtliche Lieferzeit. Immer aufrufen, bevor eine Lieferbestellung angelegt wird.','input_schema'=>['type'=>'object','properties'=>['address'=>['type'=>'string','description'=>'Straße Hausnummer, PLZ Ort']],'required'=>['address']]],
      ['name'=>'create_order','description'=>'Bestellung verbindlich anlegen – erst nach Zusammenfassung und ausdrücklicher Bestätigung des Kunden. Positionen mit Menge und ggf. Optionen (z. B. Größe).','input_schema'=>['type'=>'object','properties'=>['type'=>['type'=>'string','enum'=>['delivery','pickup']],'items'=>['type'=>'array','items'=>['type'=>'object','properties'=>['name'=>['type'=>'string'],'id'=>['type'=>'integer'],'qty'=>['type'=>'integer'],'options'=>['type'=>'array','items'=>['type'=>'string']],'note'=>['type'=>'string']],'required'=>['qty']]],'name'=>['type'=>'string'],'phone'=>['type'=>'string'],'address'=>['type'=>'string'],'payment'=>['type'=>'string','description'=>'bar | karte | paypal | online'],'note'=>['type'=>'string'],'requested_time'=>['type'=>'string','description'=>'HH:MM, optional (Vorbestellung)']],'required'=>['type','items','name']]],
      ['name'=>'get_order_status','description'=>'Status einer Bestellung per Bestellcode. Ohne Code: Bestellungen der verifizierten Absender-/Anrufernummer (WhatsApp/Telefon) – fremde Nummern sind gesperrt.','input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string'],'phone'=>['type'=>'string']],'required'=>[]]],
      ['name'=>'cancel_order','description'=>'Bestellung stornieren (nur solange Status new/confirmed und nach Bestätigung des Kunden).','input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string']],'required'=>['code']]],
    ];
}
function ord_exec(int $acc, string $name, array $in, string $channel, string $waFrom=''): array {
    switch($name){
        case 'get_menu': $all=ord_menu($acc); $cat=mb_strtolower(trim((string)($in['category']??''))); $q=mb_strtolower(trim((string)($in['query']??'')));
            /* Wortweise suchen („Pizza Salami“ findet „Salami“), Füllwörter ignorieren; ohne Treffer: komplette Karte statt „gibt es nicht“ */
            $stop=['pizza','pizzen','bitte','eine','einen','einer','groß','große','grosse','großes','klein','kleine','normal','mit','und','ohne','extra','dazu','noch','gerne','gern','auch'];
            $words=array_values(array_filter(preg_split('/[\s,;\/+]+/u',$q) ?: [],fn($w)=>mb_strlen($w)>=3 && !in_array($w,$stop,true)));
            $match=function(array $m) use($cat,$words): bool { $hay=mb_strtolower($m['category'].' '.$m['name'].' '.$m['description'].' '.$m['tags']);
                if($cat!=='' && !str_contains(mb_strtolower($m['category']),mb_substr($cat,0,4)) && !str_contains($hay,$cat)) return false;
                foreach($words as $w){ $stem=mb_strlen($w)>=7 ? mb_substr($w,0,mb_strlen($w)-1) : $w; if(!preg_match('/(^|[^\p{L}])'.preg_quote($stem,'/').'/u',$hay)) return false; } return true; };   // nur Wortanfänge („Salami“ ≠ „Insalata“)
            $menu=array_values(array_filter($all,$match)); $note=null;
            if(!$menu && ($cat!=='' || $q!=='')){ $menu=$all; $note='Kein genauer Treffer für „'.trim($cat.' '.$q).'“ – hier die vollständige Karte. Nur diese Artikel gibt es.'; }
            $st=ord_settings($acc);
            return ['open'=>ord_is_open($st),'note'=>$note,'pickup_discount_pct'=>$st['pickup_discount_pct'],'items'=>array_map(fn($m)=>['id'=>$m['id'],'category'=>$m['category'],'name'=>$m['name'],'description'=>$m['description'],'price_eur'=>round($m['price_cents']/100,2),'options'=>array_map(fn($g)=>['name'=>$g['name'],'choices'=>array_map(fn($c)=>$c['label'].($c['delta']?' ('.($c['delta']>0?'+':'').number_format($c['delta']/100,2,',','.').' €)':''),$g['choices'])],$m['options']),'tags'=>$m['tags']],$menu)];
        case 'check_delivery_address': return ord_check_address($acc,(string)($in['address']??''));
        case 'create_order': if($acc===0) $in['force']=1; return ord_create($acc,$in,$channel,$waFrom);   // Demo (Konto 0): auch außerhalb der Öffnungszeiten
        case 'get_order_status': if(!empty($in['code'])){ $o=ord_by_code((string)$in['code']); return $o&&(int)$o['account_id']===$acc?['orders'=>[ord_public($o)]]:['orders'=>[],'hint'=>'Kein Auftrag mit diesem Code.']; }
            $ph=function_exists('ch_verified_phone') ? ch_verified_phone($acc,$channel,$waFrom,(string)($in['phone']??'')) : ($acc===0 ? (string)($in['phone']??$waFrom) : null);
            if($ph===null) return ['orders'=>[],'error'=>'code_required','hint'=>defined('CH_CODE_ONLY_HINT') ? CH_CODE_ONLY_HINT : 'Bitte nach dem Bestellcode fragen.'];
            return ['orders'=>ord_find_by_phone($acc,$ph)];
        case 'cancel_order': $o=ord_by_code((string)($in['code']??'')); if(!$o||(int)$o['account_id']!==$acc) return ['error'=>'not_found']; if(!in_array($o['status'],['new','confirmed'],true)) return ['error'=>'too_late','status'=>$o['status']]; return ord_set_status($acc,(int)$o['id'],'cancelled');
    }
    return ['error'=>'unknown_tool'];
}
function ord_system_hint(int $acc): string {
    $s=ord_settings($acc); $tz=ord_tz($s); $now=new DateTimeImmutable('now',$tz); $open=ord_is_open($s);
    $wd=['So','Mo','Di','Mi','Do','Fr','Sa']; $h=[]; for($w=1;$w<=7;$w++){ $k=(string)($w%7); $r=$s['hours'][$k]??[]; $h[]=$wd[$w%7].' '.($r?implode(', ',array_map(fn($x)=>$x[0].'–'.$x[1],$r)):'Ruhetag'); }
    $zones=$s['zones']?implode('; ',array_map(fn($z)=>$z['name'].' (PLZ '.$z['plz'].'): Gebühr '.number_format($z['fee_cents']/100,2,',','.').' €, Mindestbestellwert '.number_format($z['min_cents']/100,2,',','.').' €',$s['zones'])):'Liefergebiet nach PLZ-Prüfung';
    $cats=array_count_values(array_column(ord_menu($acc),'category'));
    return "\n\nBESTELLANNAHME (aktiv) für ".($s['name']?:'das Restaurant').($s['address']?', '.$s['address']:'').". Jetzt: ".$now->format('l H:i')." – ".($open['open']?'GEÖFFNET bis '.$open['until']:'GESCHLOSSEN'.($open['next']?', öffnet um '.$open['next']:'')).". Öffnungszeiten: ".implode(' · ',$h).". Lieferung ".($s['delivery_on']?'ja':'nein').", Abholung ".($s['pickup_on']?'ja':'nein').". Liefergebiete: $zones. Zahlarten: ".$s['payment_methods'].". Lieferzeit ca. ".$s['eta_delivery_min']." Min, Abholung ca. ".$s['eta_pickup_min']." Min.".($s['pickup_discount_pct']>0?" Abholrabatt: ".$s['pickup_discount_pct']." % auf den Warenwert (wird bei create_order automatisch abgezogen).":" Es gibt KEINEN Abholrabatt – auch wenn die Wissensbasis etwas anderes sagt.")." Karte: ".implode(', ',array_map(fn($c,$n)=>"$c ($n)",array_keys($cats),$cats)).".
REGELN: Karte/Preise NUR über get_menu (nicht raten). Gesamtsummen nie selbst anders ankündigen als die Werkzeuge: nach create_order gilt ausschließlich das Feld total (inkl. Liefergebühr bzw. Abholrabatt). In der Zusammenfassung vor der Bestellung: Positionen mit Einzelpreisen + Liefergebühr bzw. Abholrabatt laut Einstellungen. Bestellung aufnehmen: Positionen mit Menge und Größe/Optionen, Lieferung oder Abholung, bei Lieferung Adresse mit Straße, Hausnummer, PLZ, Ort → check_delivery_address (nur im Liefergebiet weiter; sonst Abholung anbieten), Name, Telefonnummer, Zahlart. Dann Zusammenfassung mit Gesamtsumme (inkl. Liefergebühr) und „Soll ich die Bestellung so aufgeben?“ – erst nach Ja create_order. Danach Bestellcode, Gesamtsumme und voraussichtliche Zeit nennen; im Chat/WhatsApp zusätzlich den Status-Link (Feld track) mitschicken, am Telefon den Link NICHT vorlesen (Bestätigung kommt per SMS). Upselling höchstens einmal, dezent (Getränk/Dessert). Allergien/Unverträglichkeiten: Hinweise aus der Karte nennen, bei Unsicherheit ans Team verweisen. Kurz, freundlich, in der Sprache des Kunden.";
}

/* ---------- Links (WhatsApp-Webhook, Küchen-Board, Fahrer) ---------- */
/** Agent-Schlüssel (widget_key) des Kontos für WhatsApp-Webhook – bevorzugt agent-bestellung; legt bei Bedarf eine Konfiguration an. */
function ord_agent_key(int $acc): string {
    if($acc===0) return 'demo';
    $s=th_db()->prepare("SELECT widget_key FROM agent_configs WHERE account_id=? AND widget_key IS NOT NULL ORDER BY (plan='agent-bestellung') DESC, id LIMIT 1"); $s->execute([$acc]); $wk=(string)$s->fetchColumn();
    if($wk===''){ th_agent_cfg_save($acc,'agent-bestellung',[],false); $s->execute([$acc]); $wk=(string)$s->fetchColumn(); }
    return $wk;
}
function ord_links(int $acc): array {
    $s=ord_settings($acc); $k=ord_agent_key($acc);
    return ['whatsapp_webhook'=>'https://chat-help.com/chat/whatsapp-webhook.php?key='.$k,'verify_token'=>$s['wa_verify'] ?: $k,
        'board'=>$s['board_token']?'https://chat-help.com/chat/board.php?t='.$s['board_token']:null,
        'drivers'=>array_map(fn($d)=>['id'=>$d['id'],'name'=>$d['name'],'url'=>$d['url'] ?? null],ord_drivers($acc,true))];
}

/* ---------- Werkzeugkasten eines Agenten: Bestellungen (order_on) + Termine (appt_on) ---------- */
/** Rückgabe: hint (System-Zusatz), tools (für th_ai_tools), exec (callable), order/appt (bool). Ohne aktive Module: tools leer. */
function ord_toolkit(int $acc, string $channel, string $waFrom='', ?bool $order=null, ?bool $appt=null, string $lang='de'): array {
    $order = $order ?? (ord_settings($acc)['order_on']===1); $appt = $appt ?? (appt_settings($acc)['appt_on']===1);
    $hint=''; $tools=[];
    if($order){ $hint.=ord_system_hint($acc); $tools=array_merge($tools,ord_tools()); }
    if($appt){ $hint.=appt_system_hint($acc,$lang); $tools=array_merge($tools,appt_tools()); }
    $ordNames=array_column(ord_tools(),'name');
    $exec=function(string $n,array $i) use($acc,$channel,$waFrom,$order,$appt,$ordNames){
        if($order && in_array($n,$ordNames,true)) return ord_exec($acc,$n,$i,$channel,$waFrom);
        if($appt) return appt_exec($acc,$n,$i,$channel,$waFrom);
        return ['error'=>'unknown_tool'];
    };
    return ['hint'=>$hint,'tools'=>$tools,'exec'=>$exec,'order'=>$order,'appt'=>$appt];
}
