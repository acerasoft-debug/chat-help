<?php
/**
 * ChatHelp — Benachrichtigungen (Web Push + Firebase Cloud Messaging + Postfach + E-Mail-Ersatz)
 * ==============================================================================================
 * Ein Aufruf für alle Module:  ch_push($acc, 'order_new', 'Neue Bestellung A7K2Q9', '2× Pizza Margherita · 18,50 €', '/chat/studio.php#bestellungen', ['tag'=>'order-A7K2Q9']);
 *   1. schreibt immer zuerst einen Eintrag ins Postfach (push_inbox, Studio → Benachrichtigungen),
 *   2. prüft die Einstellungen des Betriebs (Ereignis an/aus, Ruhezeiten, dringende Aufträge),
 *   3. sendet – möglichst erst nach der HTTP-Antwort – an alle Geräte:
 *        Web Push (RFC 8030/8291/8292: aes128gcm + VAPID ES256) für Browser und installierte Web-App,
 *        FCM HTTP v1 (Service-Account, OAuth2 RS256) für die native Android-/iOS-App,
 *   4. schickt eine E-Mail, wenn kein Gerät erreicht wurde oder das Ereignis kritisch ist (höchstens 1 je Ereignis+Tag in 10 Minuten).
 * Fahrer-Handys (Taxi, Lieferdienst) und Boards melden sich über push-sub.php an: push_driver() / push_board().
 * Konto 0 (Live-Demo) sendet nie. Tabellen: push_devices, push_prefs, push_inbox, push_mails, app_keys.
 * Optionale Konstanten in db.php: VAPID_PUBLIC + VAPID_PRIVATE_PEM (sonst einmalig erzeugt und in app_keys gespeichert),
 * FCM_SERVICE_ACCOUNT_JSON (JSON-Text) oder FCM_SERVICE_ACCOUNT_FILE (Pfad), FCM_CLICK_ACTION (nur wenn die Android-App den Intent-Filter hat).
 * Nur für Tests: PUSH_TEST_FCM_BASE, PUSH_TEST_WEBPUSH_BASE (lokale Mock-Server), PUSH_TEST_MAIL_LOG (Datei statt E-Mail).
 */
require_once __DIR__.'/tokens-lib.php';

/** Ereignisse: Schlüssel → [Bezeichnung, Gruppe, Dringlichkeit (Urgency-Header), kritisch (E-Mail auch mit Gerät), Erklärung] – alle standardmäßig an. */
const PUSH_EVENTS = [
    'order_new'      => ['Neue Bestellung',              'auftraege',     'high',   true,  'Jede neue Bestellung aus Chat, Telefon, WhatsApp und Website.'],
    'order_cancel'   => ['Bestellung storniert',         'auftraege',     'high',   false, 'Der Kunde hat storniert – bitte nicht mehr zubereiten bzw. ausliefern.'],
    'booking_new'    => ['Neuer Termin',                 'auftraege',     'high',   true,  'Gebuchte Termine aus dem Kalender-Modul.'],
    'booking_cancel' => ['Termin abgesagt',              'auftraege',     'high',   false, 'Ein Kunde hat seinen Termin abgesagt – der Platz ist wieder frei.'],
    'ride_new'       => ['Neue Taxifahrt',               'auftraege',     'high',   true,  'Sofortfahrten und Vorbestellungen für die Zentrale.'],
    'ride_cancel'    => ['Fahrt storniert',              'auftraege',     'high',   false, 'Stornos, auch während der Anfahrt.'],
    'hotel_booking'  => ['Neue Zimmerbuchung/-anfrage',  'auftraege',     'high',   true,  'Buchungen und Anfragen über die Hotel-Rezeption.'],
    'hotel_request'  => ['Gästewunsch',                  'auftraege',     'high',   false, 'Wünsche von Gästen im Haus (Handtücher, Taxi, Late Check-out …).'],
    'callback_new'   => ['Rückrufwunsch',                'kommunikation', 'normal', false, 'Ein Kunde möchte zurückgerufen werden.'],
    'lead_new'       => ['Neuer Lead',                   'kommunikation', 'normal', false, 'Neue Interessenten aus Formular, Portalen oder Zapier.'],
    'call_done'      => ['Anruf beendet (Transkript)',   'kommunikation', 'normal', false, 'Zusammenfassung und Transkript nach jedem Telefonat.'],
    'tokens_low'     => ['Guthaben niedrig',             'konto',         'normal', true,  'Rechtzeitig nachladen, damit deine Agenten erreichbar bleiben.'],
    'system'         => ['Systemhinweise',               'konto',         'low',    false, 'Wichtige Hinweise zu deinem Konto und neuen Funktionen.'],
];
const PUSH_GROUPS = ['auftraege'=>'Aufträge','kommunikation'=>'Kommunikation','konto'=>'Konto'];
/** Bekannte Push-Dienste (SSRF-Schutz): exakte Hosts und Endungen. */
const PUSH_HOSTS = ['fcm.googleapis.com','android.googleapis.com','updates.push.services.mozilla.com','web.push.apple.com'];
const PUSH_HOST_SUFFIXES = ['.notify.windows.com','.push.apple.com'];
const PUSH_SUBJECT = 'mailto:support@chat-help.com';
const PUSH_BASE_URL = 'https://chat-help.com';
const PUSH_MAX_DEVICES = 25;
const PUSH_MAX_FAILS = 5;
const PUSH_TIMEOUT = 6;

/* ---------- Tabellen ---------- */
function push_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS push_devices (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, scope VARCHAR(10) NOT NULL DEFAULT 'owner', ref VARCHAR(64) NOT NULL DEFAULT '',
        type VARCHAR(8) NOT NULL DEFAULT 'webpush', endpoint TEXT NULL, endpoint_hash CHAR(64) NOT NULL, raw_hash CHAR(64) NOT NULL DEFAULT '',
        p256dh VARCHAR(120) NOT NULL DEFAULT '', auth VARCHAR(40) NOT NULL DEFAULT '', token TEXT NULL, platform VARCHAR(20) NOT NULL DEFAULT '',
        label VARCHAR(80) NOT NULL DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, last_ok_at DATETIME NULL,
        last_error VARCHAR(300) NOT NULL DEFAULT '', fails INT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_endpoint (endpoint_hash), INDEX(account_id, scope, ref), INDEX(raw_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS push_prefs (
        account_id INT PRIMARY KEY, prefs TEXT NULL, email_fallback TINYINT(1) NOT NULL DEFAULT 1, quiet_from VARCHAR(5) NOT NULL DEFAULT '',
        quiet_to VARCHAR(5) NOT NULL DEFAULT '', quiet_urgent TINYINT(1) NOT NULL DEFAULT 1, tz VARCHAR(40) NOT NULL DEFAULT 'Europe/Berlin',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS push_inbox (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, scope VARCHAR(10) NOT NULL DEFAULT 'owner', ref VARCHAR(64) NOT NULL DEFAULT '',
        event VARCHAR(32) NOT NULL, title VARCHAR(200) NOT NULL DEFAULT '', body VARCHAR(1000) NOT NULL DEFAULT '', url VARCHAR(500) NOT NULL DEFAULT '',
        tag VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, read_at DATETIME NULL,
        INDEX(account_id, id), INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS push_mails (h CHAR(32) PRIMARY KEY, sent_at DATETIME NOT NULL, INDEX(sent_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS app_keys (k VARCHAR(64) PRIMARY KEY, v TEXT NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* Spalten späterer Versionen nachrüsten */
    $cols=$p->query("SHOW COLUMNS FROM push_devices")->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('raw_hash',$cols,true)){ $p->exec("ALTER TABLE push_devices ADD COLUMN raw_hash CHAR(64) NOT NULL DEFAULT '', ADD INDEX(raw_hash)"); }
    $cols=$p->query("SHOW COLUMNS FROM push_prefs")->fetchAll(PDO::FETCH_COLUMN);
    foreach(['quiet_urgent'=>"TINYINT(1) NOT NULL DEFAULT 1",'tz'=>"VARCHAR(40) NOT NULL DEFAULT 'Europe/Berlin'"] as $c=>$def) if(!in_array($c,$cols,true)) $p->exec("ALTER TABLE push_prefs ADD COLUMN $c $def");
    if(random_int(1,50)===1){
        $p->exec("DELETE FROM push_inbox WHERE created_at < (NOW() - INTERVAL 90 DAY)");
        $p->exec("DELETE FROM push_mails WHERE sent_at < (NOW() - INTERVAL 2 DAY)");
    }
}

/* ---------- Kleine Helfer ---------- */
function push_b64u(string $bin): string { return rtrim(strtr(base64_encode($bin),'+/','-_'),'='); }
function push_b64u_dec(string $s): ?string {
    $s=trim($s); if($s==='' || !preg_match('/^[A-Za-z0-9_\-+\/]+={0,2}$/',$s)) return null;
    $s=strtr(rtrim($s,'='),'-_','+/'); $r=base64_decode($s.str_repeat('=',(4-strlen($s)%4)%4),true); return $r===false ? null : $r;
}
function push_kv_get(string $k): ?string { push_bootstrap(); $s=th_db()->prepare("SELECT v FROM app_keys WHERE k=?"); $s->execute([$k]); $v=$s->fetchColumn(); return $v===false ? null : (string)$v; }
function push_kv_set(string $k, string $v): void { push_bootstrap(); th_db()->prepare("INSERT INTO app_keys(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v),updated_at=NOW()")->execute([$k,$v]); }
/** Geheimnis für app_keys einpacken (AES-256-GCM, wenn CH_SECRET_KEY gesetzt ist). */
function push_box(string $v): string { return function_exists('th_secret_box') ? th_secret_box($v) : 'p:'.$v; }
function push_unbox(string $v): string { if(function_exists('th_secret_open')) return th_secret_open($v); return str_starts_with($v,'p:') ? substr($v,2) : ''; }
function push_clean(string $s, int $max): string { $s=trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u',' ',strip_tags($s)) ?? ''); return mb_substr($s,0,$max); }
function push_owner_email(int $acc): string { if($acc<=0) return ''; $s=th_db()->prepare("SELECT email FROM token_accounts WHERE id=?"); $s->execute([$acc]); return (string)($s->fetchColumn() ?: ''); }
/** Ziel-Link: nur eigene Seiten (relativ /chat/… oder https://chat-help.com/…); alles andere → Standardziel. */
function push_url(string $url, string $default='/chat/studio.php#benachrichtigungen'): string {
    $url=trim($url); if($url==='') return $default;
    if(preg_match('~^https://(www\.)?chat-help\.com(/[^\s]*)?$~i',$url,$m)) $url=($m[2] ?? '') ?: '/';
    if(!str_starts_with($url,'/') || str_starts_with($url,'//') || str_contains($url,'\\') || preg_match('/[\s<>"\']/',$url)) return $default;
    return mb_substr($url,0,500);
}
/** Topic-Header (RFC 8030 §5.4): höchstens 32 Zeichen aus dem URL-sicheren Base64-Alphabet. */
function push_topic(string $tag): string {
    if($tag==='') return ''; if(preg_match('/^[A-Za-z0-9_-]{1,32}$/',$tag)) return $tag;
    return push_b64u(substr(hash('sha256',$tag,true),0,24));
}
function push_tag(string $tag): string { return mb_substr(preg_replace('/[^A-Za-z0-9_.:\-]/','-',trim($tag)) ?? '',0,64); }
function push_event_meta(string $event): array { $m=PUSH_EVENTS[$event] ?? PUSH_EVENTS['system']; return ['label'=>$m[0],'group'=>$m[1],'urgency'=>$m[2],'critical'=>$m[3],'hint'=>$m[4]]; }

/* ---------- P-256-Schlüssel: Rohformat (65 Byte, unkomprimiert) ↔ PEM ---------- */
const PUSH_SPKI_P256 = '3059301306072a8648ce3d020106082a8648ce3d030107034200';
function push_pub_pem(string $raw): string { return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(PUSH_SPKI_P256).$raw),64,"\n")."-----END PUBLIC KEY-----\n"; }
/** Privater Schlüssel d (32 Byte) + öffentlicher Punkt → SEC1-PEM (RFC 5915). */
function push_priv_pem(string $d, string $pub): string {
    $der="\x30\x77\x02\x01\x01\x04\x20".$d."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\xa1\x44\x03\x42\x00".$pub;
    return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END EC PRIVATE KEY-----\n";
}
/** Öffentlicher Punkt eines Schlüssels als 65 Byte (0x04||X||Y); OpenSSL kürzt führende Nullen → auf 32 Byte auffüllen. */
function push_raw_pub($key): string {
    $d=openssl_pkey_get_details($key); if(!$d || empty($d['ec']['x'])) return '';
    return "\x04".str_pad($d['ec']['x'],32,"\0",STR_PAD_LEFT).str_pad($d['ec']['y'],32,"\0",STR_PAD_LEFT);
}
function push_raw_pub_valid(string $raw): bool { return strlen($raw)===65 && $raw[0]==="\x04" && openssl_pkey_get_public(push_pub_pem($raw))!==false; }
function push_new_ec_key() {
    $k=@openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
    return $k ?: null;
}
/** ECDSA-DER-Signatur → R||S (je 32 Byte) für JWT ES256. */
function push_der2raw(string $der): ?string {
    $o=0; $n=strlen($der); if($n<8 || ord($der[$o++])!==0x30) return null;
    $len=ord($der[$o++]); if($len & 0x80){ $b=$len & 0x7f; $len=0; for($i=0;$i<$b;$i++) $len=($len<<8)|ord($der[$o++]); }
    $out='';
    for($k=0;$k<2;$k++){
        if($o>=$n || ord($der[$o++])!==0x02) return null; $l=ord($der[$o++]); $v=substr($der,$o,$l); $o+=$l;
        $v=ltrim($v,"\0"); if(strlen($v)>32) return null; $out.=str_pad($v,32,"\0",STR_PAD_LEFT);
    }
    return $out;
}
/** R||S → DER (für openssl_verify im Test). */
function push_raw2der(string $raw): string {
    $int=function(string $v): string { $v=ltrim($v,"\0"); if($v==='' ) $v="\0"; if(ord($v[0])&0x80) $v="\0".$v; return "\x02".chr(strlen($v)).$v; };
    $seq=$int(substr($raw,0,32)).$int(substr($raw,32,32)); return "\x30".chr(strlen($seq)).$seq;
}

/* ---------- VAPID (RFC 8292) ---------- */
/** ['public'=>base64url(65 Byte), 'pem'=>privater Schlüssel] oder null, wenn OpenSSL keinen EC-Schlüssel erzeugen kann. */
function push_vapid(): ?array {
    static $v=null; if($v!==null) return $v ?: null;
    if(defined('VAPID_PUBLIC') && VAPID_PUBLIC && defined('VAPID_PRIVATE_PEM') && VAPID_PRIVATE_PEM){
        $raw=push_b64u_dec((string)VAPID_PUBLIC); $pem=trim((string)VAPID_PRIVATE_PEM);
        if(!str_contains($pem,'-----BEGIN') && $raw!==null && ($d=push_b64u_dec($pem))!==null && strlen($d)===32) $pem=push_priv_pem($d,$raw);   // roher Schlüssel d (base64url) wie aus „web-push generate-vapid-keys“
        $k=$raw!==null && push_raw_pub_valid($raw) ? openssl_pkey_get_private($pem) : false;
        if($k && hash_equals($raw,push_raw_pub($k))) return $v=['public'=>push_b64u($raw),'pem'=>$pem,'source'=>'config'];
        error_log('CH push: VAPID_PUBLIC/VAPID_PRIVATE_PEM in db.php ungültig oder passen nicht zusammen – nutze gespeicherten Schlüssel');
    }
    try{ push_bootstrap(); }catch(Throwable $e){ error_log('CH push bootstrap: '.$e->getMessage()); $v=[]; return null; }
    $read=function(){ $j=json_decode((string)push_kv_get('vapid'),true); if(!is_array($j) || empty($j['public']) || empty($j['pem'])) return null;
        $pem=push_unbox((string)$j['pem']); return ($pem!=='' && openssl_pkey_get_private($pem)) ? ['public'=>(string)$j['public'],'pem'=>$pem,'source'=>'db'] : false; };
    $cur=$read(); if(is_array($cur)) return $v=$cur;
    $k=push_new_ec_key(); if(!$k){ error_log('CH push: openssl_pkey_new(prime256v1) fehlgeschlagen'); $v=[]; return null; }
    openssl_pkey_export($k,$pem); $val=json_encode(['public'=>push_b64u(push_raw_pub($k)),'pem'=>push_box((string)$pem),'created'=>gmdate('c')]);
    if($cur===false){   // vorhandener Schlüssel nicht mehr lesbar (z. B. CH_SECRET_KEY geändert) → ersetzen; Geräte melden sich beim nächsten Öffnen neu an
        error_log('CH push: gespeicherter VAPID-Schlüssel nicht lesbar – neuer Schlüssel erzeugt');
        push_kv_set('vapid',$val);
    } else th_db()->prepare("INSERT IGNORE INTO app_keys(k,v) VALUES('vapid',?)")->execute([$val]);   // parallele Erzeugung: der erste gewinnt
    $cur=$read(); $v=is_array($cur) ? $cur : []; return $v ?: null;
}
function push_vapid_public(): ?string { $v=push_vapid(); return $v ? $v['public'] : null; }
/** JWT (ES256) für einen Push-Dienst; $aud = Origin des Endpoints (https://host). */
function push_vapid_jwt(string $aud, ?array $vapid=null, ?int $exp=null): ?string {
    static $cache=[]; $vapid=$vapid ?? push_vapid(); if(!$vapid) return null;
    $ck=$aud.'|'.$vapid['public']; if($exp===null && isset($cache[$ck]) && $cache[$ck][1]>time()+3600) return $cache[$ck][0];
    $exp=$exp ?? time()+12*3600;
    $h=push_b64u(json_encode(['typ'=>'JWT','alg'=>'ES256'])); $c=push_b64u(json_encode(['aud'=>$aud,'exp'=>$exp,'sub'=>PUSH_SUBJECT],JSON_UNESCAPED_SLASHES));
    if(!openssl_sign($h.'.'.$c,$der,$vapid['pem'],OPENSSL_ALGO_SHA256)) return null;
    $raw=push_der2raw((string)$der); if($raw===null) return null;
    $jwt=$h.'.'.$c.'.'.push_b64u($raw); $cache[$ck]=[$jwt,$exp]; return $jwt;
}

/* ---------- Verschlüsselung (RFC 8291, Content-Coding aes128gcm nach RFC 8188, ein Datensatz) ---------- */
/**
 * $uaPub = p256dh (65 Byte), $auth = Auth-Secret (16 Byte). $asPrivPem/$salt nur für Testvektoren, sonst zufällig.
 * Rückgabe ['body'=>binär, …Zwischenwerte] oder null.
 */
function push_encrypt(string $plain, string $uaPub, string $auth, ?string $asPrivPem=null, ?string $salt=null, int $pad=0): ?array {
    if(strlen($uaPub)!==65 || $uaPub[0]!=="\x04" || strlen($auth)!==16) return null;
    $as=$asPrivPem!==null ? openssl_pkey_get_private($asPrivPem) : push_new_ec_key(); if(!$as) return null;
    $asPub=push_raw_pub($as); $peer=openssl_pkey_get_public(push_pub_pem($uaPub)); if(!$peer || strlen($asPub)!==65) return null;
    $ecdh=openssl_pkey_derive($peer,$as,32); if(!is_string($ecdh) || strlen($ecdh)!==32) return null;
    $salt=$salt ?? random_bytes(16); if(strlen($salt)!==16) return null;
    $ikm=hash_hkdf('sha256',$ecdh,32,"WebPush: info\0".$uaPub.$asPub,$auth);
    $cek=hash_hkdf('sha256',$ikm,16,"Content-Encoding: aes128gcm\0",$salt);
    $nonce=hash_hkdf('sha256',$ikm,12,"Content-Encoding: nonce\0",$salt);
    $tag=''; $ct=openssl_encrypt($plain."\x02".str_repeat("\0",max(0,$pad)),'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,$tag,'',16);
    if($ct===false) return null;
    return ['body'=>$salt.pack('N',4096).chr(65).$asPub.$ct.$tag,'as_public'=>$asPub,'salt'=>$salt,'ecdh'=>$ecdh,'ikm'=>$ikm,'cek'=>$cek,'nonce'=>$nonce];
}
/** Gegenstück (Empfängerseite) – für Selbsttests und Mock-Server. */
function push_decrypt(string $body, string $uaPrivPem, string $uaPub, string $auth): ?string {
    if(strlen($body)<86+17) return null; $salt=substr($body,0,16); $idlen=ord($body[20]); if($idlen!==65) return null;
    $asPub=substr($body,21,65); $ct=substr($body,86,-16); $tag=substr($body,-16);
    $ua=openssl_pkey_get_private($uaPrivPem); $peer=openssl_pkey_get_public(push_pub_pem($asPub)); if(!$ua || !$peer) return null;
    $ecdh=openssl_pkey_derive($peer,$ua,32); if(!is_string($ecdh)) return null;
    $ikm=hash_hkdf('sha256',$ecdh,32,"WebPush: info\0".$uaPub.$asPub,$auth);
    $cek=hash_hkdf('sha256',$ikm,16,"Content-Encoding: aes128gcm\0",$salt); $nonce=hash_hkdf('sha256',$ikm,12,"Content-Encoding: nonce\0",$salt);
    $pt=openssl_decrypt($ct,'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,$tag); if($pt===false) return null;
    $pt=rtrim($pt,"\0"); if($pt==='' || substr($pt,-1)!=="\x02") return null; return substr($pt,0,-1);
}

/* ---------- Push-Dienste (SSRF-Schutz) ---------- */
/** '' = gültig, sonst Fehlercode. Nur HTTPS, Port 443, kein Benutzer/Passwort, Host aus der Liste bekannter Push-Dienste. */
function push_endpoint_error(string $ep): string {
    if($ep==='' || strlen($ep)>1024) return 'endpoint_invalid';
    $u=parse_url($ep); if(!$u || strtolower($u['scheme'] ?? '')!=='https' || empty($u['host'])) return 'endpoint_not_https';
    if(isset($u['user']) || isset($u['pass']) || (isset($u['port']) && (int)$u['port']!==443) || preg_match('/[\s"<>\\\\]/',$ep)) return 'endpoint_invalid';
    $h=strtolower($u['host']); if(filter_var($h,FILTER_VALIDATE_IP) || str_starts_with($h,'[')) return 'push_service_unknown';
    if(in_array($h,PUSH_HOSTS,true)) return '';
    foreach(PUSH_HOST_SUFFIXES as $suf) if(str_ends_with($h,$suf) && strlen($h)>strlen($suf) && preg_match('/^[a-z0-9.-]+$/',$h)) return '';
    return 'push_service_unknown';
}
/** Beim Senden zusätzlich: lokale Mock-Server nur, wenn die Testkonstante gesetzt ist. */
function push_endpoint_sendable(string $ep): bool {
    if(defined('PUSH_TEST_WEBPUSH_BASE') && PUSH_TEST_WEBPUSH_BASE && str_starts_with($ep,(string)PUSH_TEST_WEBPUSH_BASE)) return true;
    return push_endpoint_error($ep)==='';
}

/* ---------- Geräte ---------- */
function push_hash(string $scope, string $ref, string $raw): string { return hash('sha256',$scope.'|'.$ref.'|'.$raw); }
/** Gerätebezeichnung aus dem User-Agent, z. B. „Chrome · Android“. */
function push_ua_label(string $ua): string {
    $b=match(true){ str_contains($ua,'Edg/')=>'Edge', str_contains($ua,'SamsungBrowser')=>'Samsung Internet', str_contains($ua,'OPR/')=>'Opera', str_contains($ua,'Firefox/') || str_contains($ua,'FxiOS')=>'Firefox',
        str_contains($ua,'CriOS') || str_contains($ua,'Chrome/')=>'Chrome', str_contains($ua,'Safari/')=>'Safari', default=>'Browser' };
    $o=match(true){ str_contains($ua,'iPhone')=>'iPhone', str_contains($ua,'iPad')=>'iPad', str_contains($ua,'Android')=>'Android', str_contains($ua,'Windows')=>'Windows',
        str_contains($ua,'Mac OS X') || str_contains($ua,'Macintosh')=>'Mac', str_contains($ua,'CrOS')=>'ChromeOS', str_contains($ua,'Linux')=>'Linux', default=>'' };
    return $o!=='' ? $b.' · '.$o : $b;
}
/**
 * Gerät speichern (ohne Prüfung – Aufrufer validiert). Upsert über endpoint_hash = sha256(scope|ref|endpoint bzw. fcm:token):
 * dasselbe Gerät wechselt beim Anmelden mit einem anderen Studio-Konto dorthin; Fahrer-Handys je Fahrer-Token getrennt.
 */
function push_device_upsert(int $acc, string $scope, string $ref, string $type, array $d): int {
    push_bootstrap(); $p=th_db();
    $raw=$type==='fcm' ? 'fcm:'.$d['token'] : (string)$d['endpoint']; $h=push_hash($scope,$ref,$raw);
    $label=push_clean((string)($d['label'] ?? ''),80); $platform=preg_replace('/[^a-z]/','',strtolower((string)($d['platform'] ?? ''))) ?: ($type==='fcm' ? 'android' : 'web');
    $p->prepare("INSERT INTO push_devices(account_id,scope,ref,type,endpoint,endpoint_hash,raw_hash,p256dh,auth,token,platform,label) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE account_id=VALUES(account_id),endpoint=VALUES(endpoint),p256dh=VALUES(p256dh),auth=VALUES(auth),token=VALUES(token),platform=VALUES(platform),
        label=IF(VALUES(label)='',label,VALUES(label)),fails=0,last_error=''")
      ->execute([$acc,$scope,$ref,$type,$type==='fcm' ? null : (string)$d['endpoint'],$h,hash('sha256',$raw),(string)($d['p256dh'] ?? ''),(string)($d['auth'] ?? ''),
                 $type==='fcm' ? (string)$d['token'] : null,mb_substr($platform,0,20),$label]);
    $s=$p->prepare("SELECT id FROM push_devices WHERE endpoint_hash=?"); $s->execute([$h]); $id=(int)$s->fetchColumn();
    /* höchstens PUSH_MAX_DEVICES je Konto/Bereich: die am längsten stillen zuerst entfernen */
    $s=$p->prepare("SELECT id FROM push_devices WHERE account_id=? AND scope=? AND ref=? ORDER BY COALESCE(last_ok_at,created_at) DESC, id DESC LIMIT 1000 OFFSET ".PUSH_MAX_DEVICES);
    $s->execute([$acc,$scope,$ref]); $old=$s->fetchAll(PDO::FETCH_COLUMN);
    if($old){ $old=array_values(array_diff(array_map('intval',$old),[$id])); if($old) $p->prepare("DELETE FROM push_devices WHERE account_id=? AND id IN (".implode(',',array_fill(0,count($old),'?')).")")->execute(array_merge([$acc],$old)); }
    return $id;
}
/**
 * Eingaben eines Abos prüfen. Web Push: {type:'webpush', subscription:{endpoint, keys:{p256dh, auth}}}; nativ: {type:'fcm', token, platform}.
 * Rückgabe ['type'=>…, 'data'=>[…]] oder ['error'=>code,'message'=>deutsch].
 */
function push_parse_subscription(array $b): array {
    $type=($b['type'] ?? 'webpush')==='fcm' ? 'fcm' : 'webpush'; $label=push_clean((string)($b['label'] ?? ''),80);
    if($type==='fcm'){
        $t=trim((string)($b['token'] ?? '')); if(!preg_match('/^[A-Za-z0-9_:\-.]{20,4096}$/',$t)) return ['error'=>'token_invalid','message'=>'Ungültiges Geräte-Token der App.'];
        $pf=strtolower((string)($b['platform'] ?? 'android')); $pf=in_array($pf,['android','ios'],true) ? $pf : 'android';
        if(preg_match('/^[a-f0-9]{64}$/i',$t)) return ['error'=>'apns_token','message'=>'Die iOS-App liefert ein APNs-Token statt eines Firebase-Tokens – bitte die App aktualisieren.'];   // FCM HTTP v1 braucht das FCM-Token
        if($label==='') $label=$pf==='ios' ? 'ChatHelp-App · iPhone' : 'ChatHelp-App · Android';
        return ['type'=>'fcm','data'=>['token'=>$t,'platform'=>$pf,'label'=>$label]];
    }
    if($label==='') $label=push_ua_label((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $sub=is_array($b['subscription'] ?? null) ? $b['subscription'] : $b; $ep=trim((string)($sub['endpoint'] ?? ''));
    $err=push_endpoint_error($ep);
    if($err!=='') return ['error'=>$err,'message'=>$err==='push_service_unknown' ? 'Dieser Push-Dienst wird nicht unterstützt.' : 'Ungültige Push-Adresse.'];
    $k=is_array($sub['keys'] ?? null) ? $sub['keys'] : []; $pk=push_b64u_dec((string)($k['p256dh'] ?? '')); $au=push_b64u_dec((string)($k['auth'] ?? ''));
    if($pk===null || !push_raw_pub_valid($pk) || $au===null || strlen($au)!==16) return ['error'=>'keys_invalid','message'=>'Die Schlüssel des Browsers sind ungültig – bitte erneut aktivieren.'];
    return ['type'=>'webpush','data'=>['endpoint'=>$ep,'p256dh'=>push_b64u($pk),'auth'=>push_b64u($au),'platform'=>'web','label'=>$label]];
}
function push_devices(int $acc, string $scope='owner', ?string $ref=null): array {
    push_bootstrap(); $q="SELECT * FROM push_devices WHERE account_id=? AND scope=?".($ref!==null ? " AND ref=?" : "")." ORDER BY id";
    $s=th_db()->prepare($q); $s->execute($ref!==null ? [$acc,$scope,$ref] : [$acc,$scope]); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function push_device_public(array $d): array {
    return ['id'=>(int)$d['id'],'type'=>$d['type'],'platform'=>$d['platform'],'label'=>$d['label'] ?: ($d['type']==='fcm' ? 'ChatHelp-App' : 'Browser'),
        'created'=>th_dbiso($d['created_at']),'last_ok'=>th_dbiso($d['last_ok_at']),'last_error'=>(string)$d['last_error'],'fails'=>(int)$d['fails'],
        'h'=>substr((string)$d['raw_hash'],0,16)];
}
/** Abo-Wechsel des Browsers (pushsubscriptionchange): alle Einträge des alten Endpoints auf das neue Abo umziehen. Gibt die Anzahl zurück. */
function push_resubscribe(string $oldEndpoint, array $sub): int {
    $parsed=push_parse_subscription(['type'=>'webpush','subscription'=>$sub]); if(isset($parsed['error']) || $oldEndpoint==='') return 0;
    push_bootstrap(); $s=th_db()->prepare("SELECT * FROM push_devices WHERE raw_hash=? AND type='webpush'"); $s->execute([hash('sha256',$oldEndpoint)]); $n=0;
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $d){
        if(!hash_equals((string)$d['endpoint'],$oldEndpoint)) continue;
        th_db()->prepare("DELETE FROM push_devices WHERE id=?")->execute([$d['id']]);
        push_device_upsert((int)$d['account_id'],$d['scope'],$d['ref'],'webpush',array_merge($parsed['data'],$d['label']!=='' ? ['label'=>$d['label']] : [])); $n++;   // Bezeichnung des Geräts bleibt
    }
    return $n;
}

/* ---------- Einstellungen ---------- */
function push_prefs(int $acc): array {
    push_bootstrap(); $s=th_db()->prepare("SELECT * FROM push_prefs WHERE account_id=?"); $s->execute([$acc]); $r=$s->fetch(PDO::FETCH_ASSOC) ?: [];
    $saved=json_decode((string)($r['prefs'] ?? ''),true); $saved=is_array($saved) ? $saved : []; $ev=[];
    foreach(PUSH_EVENTS as $k=>$m) $ev[$k]=array_key_exists($k,$saved) ? (bool)$saved[$k] : true;
    $tz=(string)($r['tz'] ?? 'Europe/Berlin'); if(!in_array($tz,timezone_identifiers_list(),true)) $tz='Europe/Berlin';
    return ['events'=>$ev,'email_fallback'=>!isset($r['email_fallback']) || (int)$r['email_fallback']===1,
        'quiet'=>['from'=>(string)($r['quiet_from'] ?? ''),'to'=>(string)($r['quiet_to'] ?? ''),'tz'=>$tz,'urgent'=>!isset($r['quiet_urgent']) || (int)$r['quiet_urgent']===1]];
}
/** Speichern; $d = {prefs:{event:bool}, email_fallback:bool, quiet:{from,to,tz,urgent}} – fehlende Teile bleiben unverändert. */
function push_prefs_save(int $acc, array $d): array {
    $cur=push_prefs($acc); $ev=$cur['events'];
    if(is_array($d['prefs'] ?? null)) foreach($d['prefs'] as $k=>$v) if(isset(PUSH_EVENTS[$k])) $ev[$k]=(bool)$v;
    $ef=array_key_exists('email_fallback',$d) ? (bool)$d['email_fallback'] : $cur['email_fallback']; $q=$cur['quiet'];
    if(is_array($d['quiet'] ?? null)){
        $hm=fn($x)=>preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/',trim((string)$x)) ? trim((string)$x) : '';
        if(array_key_exists('from',$d['quiet'])) $q['from']=$hm($d['quiet']['from']);
        if(array_key_exists('to',$d['quiet'])) $q['to']=$hm($d['quiet']['to']);
        if(isset($d['quiet']['tz']) && in_array((string)$d['quiet']['tz'],timezone_identifiers_list(),true)) $q['tz']=(string)$d['quiet']['tz'];
        if(array_key_exists('urgent',$d['quiet'])) $q['urgent']=(bool)$d['quiet']['urgent'];
        if($q['from']==='' || $q['to']==='' || $q['from']===$q['to']){ $q['from']=''; $q['to']=''; }   // unvollständig oder gleich = keine Ruhezeit
    }
    th_db()->prepare("INSERT INTO push_prefs(account_id,prefs,email_fallback,quiet_from,quiet_to,quiet_urgent,tz) VALUES(?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE prefs=VALUES(prefs),email_fallback=VALUES(email_fallback),quiet_from=VALUES(quiet_from),quiet_to=VALUES(quiet_to),quiet_urgent=VALUES(quiet_urgent),tz=VALUES(tz),updated_at=NOW()")
      ->execute([$acc,json_encode($ev),(int)$ef,$q['from'],$q['to'],(int)$q['urgent'],$q['tz']]);
    return push_prefs($acc);
}
/** Liegt der Zeitpunkt in den Ruhezeiten (Ortszeit des Betriebs)? Über Mitternacht (22:00–07:00) wird unterstützt. */
function push_in_quiet(array $prefs, ?DateTimeImmutable $now=null): bool {
    $f=$prefs['quiet']['from'] ?? ''; $t=$prefs['quiet']['to'] ?? ''; if($f==='' || $t==='' || $f===$t) return false;
    try{ $now=($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone($prefs['quiet']['tz'] ?? 'Europe/Berlin')); }catch(Throwable $e){ return false; }
    $hm=$now->format('H:i'); return $f<$t ? ($hm>=$f && $hm<$t) : ($hm>=$f || $hm<$t);
}

/* ---------- Postfach ---------- */
function push_unread(int $acc): int {
    push_bootstrap(); $s=th_db()->prepare("SELECT COUNT(*) FROM push_inbox WHERE account_id=? AND scope='owner' AND read_at IS NULL AND created_at > (NOW() - INTERVAL 30 DAY)");
    $s->execute([$acc]); return (int)$s->fetchColumn();
}
function push_inbox_row(array $r): array {
    $m=push_event_meta((string)$r['event']);
    return ['id'=>(int)$r['id'],'event'=>$r['event'],'label'=>$m['label'],'group'=>$m['group'],'title'=>$r['title'],'body'=>$r['body'],'url'=>$r['url'],'tag'=>$r['tag'],
        'created'=>th_dbiso($r['created_at']),'read'=>$r['read_at']!==null];
}
/** Neueste zuerst. $since = nur neuere als diese id (Abfrage alle 30 s), $before = ältere als diese id („Mehr laden“). */
function push_inbox(int $acc, int $since=0, int $limit=30, int $before=0): array {
    push_bootstrap(); $limit=max(1,min(100,$limit)); $q="SELECT * FROM push_inbox WHERE account_id=? AND scope='owner'"; $a=[$acc];
    if($since>0){ $q.=" AND id>?"; $a[]=$since; } if($before>0){ $q.=" AND id<?"; $a[]=$before; }
    $s=th_db()->prepare($q." ORDER BY id DESC LIMIT ".$limit); $s->execute($a); return array_map('push_inbox_row',$s->fetchAll(PDO::FETCH_ASSOC));
}
/** $ids = Liste von ids oder 'all'. Gibt die Zahl der markierten Einträge zurück. */
function push_mark_read(int $acc, $ids): int {
    push_bootstrap();
    if($ids==='all'){ $s=th_db()->prepare("UPDATE push_inbox SET read_at=NOW() WHERE account_id=? AND scope='owner' AND read_at IS NULL"); $s->execute([$acc]); return $s->rowCount(); }
    $ids=array_values(array_unique(array_filter(array_map('intval',is_array($ids) ? $ids : [$ids]),fn($x)=>$x>0))); if(!$ids) return 0; $ids=array_slice($ids,0,200);
    $s=th_db()->prepare("UPDATE push_inbox SET read_at=NOW() WHERE account_id=? AND read_at IS NULL AND id IN (".implode(',',array_fill(0,count($ids),'?')).")");
    $s->execute(array_merge([$acc],$ids)); return $s->rowCount();
}

/* ---------- HTTP (parallel, mit Zeitlimit) ---------- */
/** $reqs[key] = ['url','headers'=>[], 'body'=>string] → [key => ['code'=>int,'body'=>string,'err'=>string,'headers'=>string]] */
function push_http_multi(array $reqs, int $timeout=PUSH_TIMEOUT): array {
    if(!$reqs) return []; $mh=curl_multi_init(); $hs=[]; $out=[];
    foreach($reqs as $k=>$r){
        $ch=curl_init((string)$r['url']); $hdr='';
        $test=(defined('PUSH_TEST_FCM_BASE') && PUSH_TEST_FCM_BASE && str_starts_with((string)$r['url'],(string)PUSH_TEST_FCM_BASE))
           || (defined('PUSH_TEST_WEBPUSH_BASE') && PUSH_TEST_WEBPUSH_BASE && str_starts_with((string)$r['url'],(string)PUSH_TEST_WEBPUSH_BASE));
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>(string)$r['body'],CURLOPT_HTTPHEADER=>$r['headers'] ?? [],CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>min(4,$timeout),CURLOPT_FOLLOWLOCATION=>false,CURLOPT_NOSIGNAL=>true,
            CURLOPT_PROTOCOLS=>$test ? (CURLPROTO_HTTPS|CURLPROTO_HTTP) : CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION=>function($c,$line) use(&$out,$k){ $out[$k]['headers']=($out[$k]['headers'] ?? '').$line; return strlen($line); }]);
        curl_multi_add_handle($mh,$ch); $hs[$k]=$ch; $out[$k]=['code'=>0,'body'=>'','err'=>'','headers'=>''];
    }
    $errs=[];
    do{
        $st=curl_multi_exec($mh,$running);
        while($info=curl_multi_info_read($mh)){ $k=array_search($info['handle'],$hs,true); if($k!==false && $info['result']!==CURLE_OK) $errs[$k]=curl_strerror($info['result']); }
        if($running && curl_multi_select($mh,0.5)===-1) usleep(20000);
    }while($running && $st===CURLM_OK);
    while($info=curl_multi_info_read($mh)){ $k=array_search($info['handle'],$hs,true); if($k!==false && $info['result']!==CURLE_OK) $errs[$k]=curl_strerror($info['result']); }
    foreach($hs as $k=>$ch){
        $out[$k]['code']=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $out[$k]['body']=(string)curl_multi_getcontent($ch); $out[$k]['err']=$errs[$k] ?? curl_error($ch);
        curl_multi_remove_handle($mh,$ch); curl_close($ch);
    }
    curl_multi_close($mh); return $out;
}
function push_http_one(string $url, array $headers, string $body, int $timeout=PUSH_TIMEOUT): array { return push_http_multi(['x'=>['url'=>$url,'headers'=>$headers,'body'=>$body]],$timeout)['x']; }

/* ---------- Firebase Cloud Messaging (HTTP v1) ---------- */
/** Service-Account aus db.php (FCM_SERVICE_ACCOUNT_JSON = JSON-Text oder FCM_SERVICE_ACCOUNT_FILE = Pfad). */
function push_fcm_sa(): ?array {
    static $sa=null; if($sa!==null) return $sa ?: null; $j=null;
    if(defined('FCM_SERVICE_ACCOUNT_JSON') && FCM_SERVICE_ACCOUNT_JSON) $j=json_decode((string)FCM_SERVICE_ACCOUNT_JSON,true);
    elseif(defined('FCM_SERVICE_ACCOUNT_FILE') && FCM_SERVICE_ACCOUNT_FILE && is_readable((string)FCM_SERVICE_ACCOUNT_FILE)) $j=json_decode((string)file_get_contents((string)FCM_SERVICE_ACCOUNT_FILE),true);
    $ok=is_array($j) && !empty($j['project_id']) && !empty($j['client_email']) && !empty($j['private_key']) && openssl_pkey_get_private((string)$j['private_key']);
    $sa=$ok ? ['project_id'=>(string)$j['project_id'],'client_email'=>(string)$j['client_email'],'private_key'=>(string)$j['private_key']] : [];
    return $sa ?: null;
}
function push_fcm_ready(): bool { return push_fcm_sa()!==null; }
function push_fcm_base(): string { return (defined('PUSH_TEST_FCM_BASE') && PUSH_TEST_FCM_BASE) ? rtrim((string)PUSH_TEST_FCM_BASE,'/') : ''; }
/** OAuth2-Zugriffstoken (JWT RS256 → oauth2.googleapis.com), in app_keys zwischengespeichert bis 5 Minuten vor Ablauf. */
function push_fcm_access_token(bool $force=false): ?string {
    $sa=push_fcm_sa(); if(!$sa) return null; $ck='fcm_token';
    if(!$force){ $c=json_decode((string)push_kv_get($ck),true);
        if(is_array($c) && ($c['sa'] ?? '')===$sa['client_email'] && (int)($c['exp'] ?? 0) > time()+300){ $t=push_unbox((string)$c['t']); if($t!=='') return $t; } }
    $now=time(); $aud='https://oauth2.googleapis.com/token'; $url=push_fcm_base()!=='' ? push_fcm_base().'/token' : $aud;
    $h=push_b64u(json_encode(['alg'=>'RS256','typ'=>'JWT'])); $c=push_b64u(json_encode(['iss'=>$sa['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging','aud'=>$aud,'iat'=>$now,'exp'=>$now+3600],JSON_UNESCAPED_SLASHES));
    if(!openssl_sign($h.'.'.$c,$sig,$sa['private_key'],OPENSSL_ALGO_SHA256)) return null;
    $r=push_http_one($url,['Content-Type: application/x-www-form-urlencoded'],http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$h.'.'.$c.'.'.push_b64u((string)$sig)]),10);
    $j=json_decode($r['body'],true);
    if($r['code']!==200 || empty($j['access_token'])){ error_log('CH push fcm token: '.$r['code'].' '.mb_substr($r['body'] ?: $r['err'],0,200)); return null; }
    push_kv_set($ck,json_encode(['t'=>push_box((string)$j['access_token']),'exp'=>$now+(int)($j['expires_in'] ?? 3600),'sa'=>$sa['client_email']]));
    return (string)$j['access_token'];
}
/** FCM-Nachricht (HTTP v1) für ein App-Gerät. */
function push_fcm_message(string $token, array $pl, int $ttl=86400): array {
    $notif=['channel_id'=>'chathelp_alerts','tag'=>$pl['tag'] !== '' ? $pl['tag'] : $pl['event']];
    if(empty($pl['silent'])) $notif['sound']='default';
    if(($pl['urgency'] ?? '')==='high') $notif['notification_priority']='PRIORITY_HIGH';
    if(defined('FCM_CLICK_ACTION') && FCM_CLICK_ACTION) $notif['click_action']=(string)FCM_CLICK_ACTION;   // nur wenn die App einen passenden Intent-Filter hat
    $aps=['badge'=>(int)($pl['badge'] ?? 0),'thread-id'=>(string)$pl['event']]; if(empty($pl['silent'])) $aps['sound']='default';
    $apnsH=['apns-priority'=>'10']; if($pl['tag']!=='') $apnsH['apns-collapse-id']=substr($pl['tag'],0,64);
    return ['message'=>['token'=>$token,'notification'=>['title'=>$pl['title'],'body'=>$pl['body']],
        'data'=>['url'=>(string)$pl['url'],'event'=>(string)$pl['event'],'tag'=>(string)$pl['tag'],'id'=>(string)($pl['id'] ?? 0),'title'=>(string)$pl['title'],'body'=>(string)$pl['body'],'badge'=>(string)(int)($pl['badge'] ?? 0)],
        'android'=>['priority'=>'HIGH','ttl'=>$ttl.'s','notification'=>$notif],
        'apns'=>['headers'=>$apnsH,'payload'=>['aps'=>$aps]]]];
}
function push_fcm_send_url(): ?string { $sa=push_fcm_sa(); if(!$sa) return null; return (push_fcm_base()!=='' ? push_fcm_base() : 'https://fcm.googleapis.com').'/v1/projects/'.rawurlencode($sa['project_id']).'/messages:send'; }

/* ---------- Versand an Geräte ---------- */
/** JSON-Nutzlast für den Service Worker (sw.js). Web Push erlaubt 4096 Byte je Nachricht → Text notfalls kürzen (Klartext ≤ 3.800 Byte). */
function push_payload_json(array $pl, string $urg): string {
    $mk=fn(string $body)=>json_encode(['title'=>$pl['title'],'body'=>$body,'url'=>$pl['url'],'tag'=>$pl['tag'],'event'=>$pl['event'],'id'=>(int)($pl['id'] ?? 0),
        'icon'=>'/chat/icons/icon-192.png','urgency'=>$urg,'silent'=>!empty($pl['silent']),'ts'=>(int)round(microtime(true)*1000)]
        +(isset($pl['badge']) ? ['badge'=>(int)$pl['badge']] : []),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $body=(string)$pl['body']; $json=$mk($body);
    while(strlen($json)>3800 && $body!==''){ $body=mb_substr($body,0,max(0,(int)(mb_strlen($body)*0.8)-1)).'…'; if(mb_strlen($body)<=1) $body=''; $json=$mk($body); }
    return $json;
}
/** Web-Push-Anfrage bauen (verschlüsselt je Gerät). null = Gerät unbrauchbar. */
function push_webpush_request(array $dev, string $json, int $ttl, string $urgency, string $topic): ?array {
    $ep=(string)$dev['endpoint']; if(!push_endpoint_sendable($ep)) return null;
    $pk=push_b64u_dec((string)$dev['p256dh']); $au=push_b64u_dec((string)$dev['auth']); if($pk===null || $au===null) return null;
    $enc=push_encrypt($json,$pk,$au); $u=parse_url($ep); $aud=$u['scheme'].'://'.$u['host'].(isset($u['port']) ? ':'.$u['port'] : '');
    $jwt=push_vapid_jwt($aud); $v=push_vapid(); if(!$enc || !$jwt || !$v) return null;
    $h=['Content-Type: application/octet-stream','Content-Encoding: aes128gcm','TTL: '.$ttl,'Urgency: '.$urgency,'Authorization: vapid t='.$jwt.', k='.$v['public']];
    if($topic!=='') $h[]='Topic: '.$topic;
    return ['url'=>$ep,'headers'=>$h,'body'=>$enc['body']];
}
/**
 * Nutzlast an Geräte senden. $pl = ['title','body','url','tag','event','badge','id','urgency','silent'].
 * Ergebnis je Gerät: ['id','type','label','platform','ok','status','error','removed'] – aktualisiert last_ok_at / fails / löscht ungültige Geräte.
 */
function push_send_devices(array $devices, array $pl, int $ttl=86400): array {
    if(!$devices) return []; $res=[]; $reqs=[]; $byKey=[];
    $urg=in_array($pl['urgency'] ?? '',['very-low','low','normal','high'],true) ? $pl['urgency'] : 'normal'; $topic=push_topic((string)($pl['tag'] ?? ''));
    $json=push_payload_json($pl,$urg);
    $fcmToken=null; $fcmUrl=null; $fcmTried=false;
    foreach($devices as $d){
        $k='d'.(int)$d['id']; $byKey[$k]=$d;
        $res[$k]=['id'=>(int)$d['id'],'type'=>$d['type'],'label'=>$d['label'],'platform'=>$d['platform'],'ok'=>false,'status'=>0,'error'=>'','removed'=>false];
        if($d['type']==='fcm'){
            if(!$fcmTried){ $fcmTried=true; $fcmToken=push_fcm_access_token(); $fcmUrl=push_fcm_send_url(); }
            if(!push_fcm_ready()){ $res[$k]['error']='fcm_not_configured'; $res[$k]['skip']=true; continue; }
            if(!$fcmToken || !$fcmUrl){ $res[$k]['error']='fcm_auth_failed'; $res[$k]['skip']=true; continue; }
            $reqs[$k]=['url'=>$fcmUrl,'headers'=>['Authorization: Bearer '.$fcmToken,'Content-Type: application/json; charset=UTF-8'],
                'body'=>json_encode(push_fcm_message((string)$d['token'],$pl,$ttl),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];
        } else {
            $r=push_webpush_request($d,$json,$ttl,$urg,$topic);
            if(!$r){ $res[$k]['error']=push_endpoint_sendable((string)$d['endpoint']) ? 'encrypt_failed' : 'endpoint_rejected'; $res[$k]['gone']=!push_endpoint_sendable((string)$d['endpoint']); continue; }
            $reqs[$k]=$r;
        }
    }
    $out=push_http_multi($reqs);
    /* 401 bei FCM: Token abgelaufen/zurückgezogen → einmal neu holen; 429/5xx → nach 1 s ein zweiter Versuch */
    $retry=[]; $fcmRefresh=false;
    foreach($out as $k=>$o){ if($byKey[$k]['type']==='fcm' && $o['code']===401){ $fcmRefresh=true; $retry[$k]=$reqs[$k]; } elseif($o['code']===429 || $o['code']>=500) $retry[$k]=$reqs[$k]; }
    if($retry){
        if($fcmRefresh && ($t=push_fcm_access_token(true))){ foreach($retry as $k=>&$rq){ if($byKey[$k]['type']==='fcm') $rq['headers'][0]='Authorization: Bearer '.$t; } unset($rq); }
        usleep(1000000);
        foreach($retry as $k=>$rq){ if($byKey[$k]['type']==='webpush'){ $nr=push_webpush_request($byKey[$k],$json,$ttl,$urg,$topic); if($nr) $retry[$k]=$nr; } }   // neu verschlüsseln (frischer Salt)
        foreach(push_http_multi($retry) as $k=>$o) $out[$k]=$o;
    }
    $p=th_db();
    foreach($res as $k=>&$r){
        $d=$byKey[$k];
        if(!isset($out[$k])){
            if(!empty($r['gone'])){ $p->prepare("DELETE FROM push_devices WHERE id=?")->execute([$d['id']]); $r['removed']=true; }
            elseif(empty($r['skip'])) push_device_failed((int)$d['id'],$r['error'],$r);
            unset($r['gone'],$r['skip']); continue;
        }
        $o=$out[$k]; $r['status']=$o['code']; $j=json_decode($o['body'],true);
        if($o['code']>=200 && $o['code']<300){ $r['ok']=true; $p->prepare("UPDATE push_devices SET last_ok_at=NOW(), fails=0, last_error='' WHERE id=?")->execute([$d['id']]); continue; }
        $fcmStatus=is_array($j) ? (string)($j['error']['status'] ?? '') : ''; $fcmCode='';
        if(is_array($j)) foreach((array)($j['error']['details'] ?? []) as $det) if(isset($det['errorCode'])) $fcmCode=(string)$det['errorCode'];
        $gone=in_array($o['code'],[404,410],true) || in_array($fcmStatus,['UNREGISTERED','NOT_FOUND'],true) || $fcmCode==='UNREGISTERED';
        $r['error']=$o['code'] ? trim('HTTP '.$o['code'].' '.($fcmCode ?: $fcmStatus).' '.mb_substr(is_array($j) ? (string)($j['error']['message'] ?? '') : strip_tags($o['body']),0,160)) : ($o['err'] ?: 'Keine Antwort');
        if($gone){ $p->prepare("DELETE FROM push_devices WHERE id=?")->execute([$d['id']]); $r['removed']=true; }
        else push_device_failed((int)$d['id'],$r['error'],$r);
    }
    unset($r);
    return array_values($res);
}
function push_device_failed(int $id, string $err, array &$r): void {
    $p=th_db(); $p->prepare("UPDATE push_devices SET fails=fails+1, last_error=? WHERE id=?")->execute([mb_substr($err,0,300),$id]);
    $s=$p->prepare("DELETE FROM push_devices WHERE id=? AND fails>=?"); $s->execute([$id,PUSH_MAX_FAILS]); if($s->rowCount()>0) $r['removed']=true;
}

/* ---------- E-Mail-Ersatz ---------- */
/** true = senden erlaubt (höchstens 1 E-Mail je Konto+Ereignis+Tag in 10 Minuten). */
function push_mail_slot(int $acc, string $event, string $key): bool { return push_slot($acc.'|'.$event.'|'.$key,600); }
/** Atomare Sperre „höchstens einmal je $seconds“ (push_mails): true = jetzt erlaubt (und vermerkt). */
function push_slot(string $key, int $seconds): bool {
    push_bootstrap(); $h=md5($key); $p=th_db();
    $s=$p->prepare("INSERT IGNORE INTO push_mails(h,sent_at) VALUES(?,NOW())"); $s->execute([$h]); if($s->rowCount()===1) return true;
    $s=$p->prepare("UPDATE push_mails SET sent_at=NOW() WHERE h=? AND sent_at < (NOW() - INTERVAL ? SECOND)"); $s->execute([$h,max(1,$seconds)]); return $s->rowCount()===1;
}
function push_send_mail(string $to, string $subject, string $html): bool {
    if(defined('PUSH_TEST_MAIL_LOG') && PUSH_TEST_MAIL_LOG){ return @file_put_contents((string)PUSH_TEST_MAIL_LOG,json_encode(['to'=>$to,'subject'=>$subject,'t'=>time()],JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND)!==false; }
    return th_mail($to,$subject,$html);
}
function push_mail_html(string $label, string $title, string $body, string $url): string {
    $h=fn($s)=>htmlspecialchars($s,ENT_QUOTES); $abs=str_starts_with($url,'/') ? PUSH_BASE_URL.$url : $url;
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:540px;margin:0 auto;padding:28px;border:1px solid #e6e8ef;border-radius:20px;color:#0f1222">'
        .'<div style="font-size:20px;font-weight:700">ChatHelp <span style="color:#b8862b">Studio</span></div>'
        .'<div style="margin-top:18px"><span style="display:inline-block;font:600 11px Inter,Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;padding:6px 10px;border-radius:999px;background:#f6ecd3;color:#b8862b">'.$h($label).'</span></div>'
        .'<h2 style="font-size:19px;line-height:1.3;margin:12px 0 6px">'.$h($title).'</h2>'
        .($body!=='' ? '<p style="color:#3b4055;margin:0 0 20px;white-space:pre-line;line-height:1.55">'.$h($body).'</p>' : '')
        .'<a href="'.$h($abs).'" style="display:inline-block;background:#0f1222;color:#fff;text-decoration:none;padding:11px 18px;border-radius:12px;font-weight:600">Öffnen</a>'
        .'<p style="color:#6b7083;font-size:12.5px;margin-top:24px;line-height:1.5">Tipp: Mit Push-Benachrichtigungen erfährst du es sofort auf dem Handy – Studio → Benachrichtigungen → „Benachrichtigungen aktivieren“.'
        .'<br>Einstellungen: '.$h(PUSH_BASE_URL.'/chat/studio.php#benachrichtigungen').'<br>ChatHelp · chat-help.com · support@chat-help.com</p></div>';
}

/* ---------- Zentrale Funktion ---------- */
/**
 * Benachrichtigung an einen Betrieb (oder mit scope driver/board an Fahrer-Handys bzw. Boards).
 * $opt: scope ('owner'|'driver'|'board'), ref (z. B. 'ride:12'), tag (Zusammenfassen/Ersetzen, z. B. 'order-A7K2Q9'), urgency ('high'|'normal'|'low'),
 *       email (true = immer, false = nie, sonst nach Einstellung/kritisch), silent (ohne Ton), ttl (Sekunden), sync (true = sofort statt nach der Antwort).
 * true = angenommen (Postfach geschrieben und mindestens ein Weg geplant bzw. – synchron – erfolgreich zugestellt).
 */
function ch_push(int $acc, string $event, string $title, string $body, string $url='', array $opt=[]): bool {
    if($acc<=0) return false;   // Konto 0 = Live-Demo: nie senden
    try{
        push_bootstrap();
        $event=substr(preg_replace('/[^a-z0-9_]/','',strtolower($event)) ?? '',0,32) ?: 'system'; $meta=push_event_meta($event);
        $scope=in_array($opt['scope'] ?? 'owner',['owner','driver','board'],true) ? (string)($opt['scope'] ?? 'owner') : 'owner';
        $ref=$scope==='owner' ? '' : mb_substr((string)($opt['ref'] ?? ''),0,64); if($scope!=='owner' && $ref==='') return false;
        $title=push_clean($title,120) ?: $meta['label']; $body=push_clean($body,600); $url=push_url($url); $tag=push_tag((string)($opt['tag'] ?? ''));
        $urgency=in_array($opt['urgency'] ?? '',['very-low','low','normal','high'],true) ? (string)$opt['urgency'] : $meta['urgency'];
        $ttl=max(60,min(2419200,(int)($opt['ttl'] ?? ($urgency==='high' ? 3600*6 : 86400))));
        $p=th_db(); $inboxId=0;
        if($scope==='owner'){
            $p->prepare("INSERT INTO push_inbox(account_id,scope,ref,event,title,body,url,tag) VALUES(?,?,?,?,?,?,?,?)")->execute([$acc,'owner','',$event,$title,$body,$url,$tag]);
            $inboxId=(int)$p->lastInsertId();
        }
        /* Einstellungen: Ereignis an/aus; Ruhezeiten (dringende Aufträge dürfen sie durchbrechen, wenn so eingestellt) */
        $prefs=$scope==='owner' ? push_prefs($acc) : null;
        $eventOn=$scope!=='owner' || (bool)($prefs['events'][$event] ?? $prefs['events']['system']);
        $quiet=$scope==='owner' && push_in_quiet($prefs) && !($urgency==='high' && $prefs['quiet']['urgent']);
        $devices=($eventOn && !$quiet) ? array_values(array_filter(push_devices($acc,$scope,$scope==='owner' ? '' : $ref),fn($d)=>(int)$d['fails']<PUSH_MAX_FAILS)) : [];
        $pl=['title'=>$title,'body'=>$body,'url'=>$url,'tag'=>$tag,'event'=>$event,'id'=>$inboxId,'urgency'=>$urgency,'silent'=>!empty($opt['silent'])];
        if($scope==='owner') $pl['badge']=push_unread($acc);
        /* E-Mail: email=true erzwingt, email=false verbietet; sonst nur mit E-Mail-Ersatz an – kritische Ereignisse immer, andere nur ohne erreichtes Gerät und außerhalb der Ruhezeiten */
        $email=$opt['email'] ?? null;
        $mailPossible=$scope==='owner' && $email!==false && ($email===true || ($eventOn && $prefs['email_fallback'] && !$quiet));   // Ruhezeit: auch keine E-Mail (Postfach bleibt)
        if(!$devices && !$mailPossible) return false;
        $job=function() use($acc,$event,$meta,$devices,$pl,$ttl,$email,$mailPossible,$tag,$title,$body,$url): bool {
            $delivered=0;
            try{ foreach(push_send_devices($devices,$pl,$ttl) as $r) if($r['ok']) $delivered++; }catch(Throwable $e){ error_log('CH push send: '.$e->getMessage()); }
            if(!$mailPossible) return $delivered>0;
            $want=$email===true || $meta['critical'] || $delivered===0;
            if(!$want) return $delivered>0;
            $to=push_owner_email($acc); if($to==='' || !push_mail_slot($acc,$event,$tag!=='' ? $tag : md5($title.'|'.$body))) return $delivered>0;
            $sent=push_send_mail($to,$title,push_mail_html($meta['label'],$title,$body,$url));
            return $delivered>0 || $sent;
        };
        if(PHP_SAPI!=='cli' && empty($opt['sync']) && function_exists('ch_after_response')){ ch_after_response($job); return true; }
        return $job();
    }catch(Throwable $e){ error_log('CH ch_push: '.$e->getMessage()); return false; }
}
/**
 * Guthaben-Hinweis für th_debit()/th_charge(): unter $threshold Tokens höchstens einmal je 24 Stunden „Guthaben niedrig“ senden.
 * true = Hinweis ausgelöst.
 */
function push_tokens_low(int $acc, int $balance, int $threshold=200): bool {
    if($acc<=0 || $balance>=$threshold) return false;
    try{ if(!push_slot('tokens_low|'.$acc,86400)) return false; }catch(Throwable $e){ return false; }
    return ch_push($acc,'tokens_low','Guthaben niedrig: noch '.number_format(max(0,$balance),0,',','.').' Tokens',
        'Lade rechtzeitig nach, damit deine Agenten am Telefon, im Chat und per WhatsApp erreichbar bleiben.','/chat/studio.php#tokens',['tag'=>'tokens-low']);
}
/** Fahrer-Handy (Taxi: module 'ride' bzw. 'taxi' → taxi_drivers, Lieferdienst: module 'order' → drivers). Standard-Ziel: die Fahrer-Seite mit Token. */
function push_driver(string $module, int $driverId, string $title, string $body, string $url='', array $opt=[]): bool {
    if($module==='taxi') $module='ride';
    $tbl=['ride'=>'taxi_drivers','order'=>'drivers'][$module] ?? null; if($tbl===null || $driverId<=0) return false;
    try{ $s=th_db()->prepare("SELECT account_id, token FROM $tbl WHERE id=? AND active=1"); $s->execute([$driverId]); $d=$s->fetch(PDO::FETCH_ASSOC); }catch(Throwable $e){ return false; }
    if(!$d || (int)$d['account_id']<=0) return false;
    if($url==='') $url=($module==='ride' ? '/chat/taxi-driver.php?t=' : '/chat/driver.php?t=').$d['token'];
    $event=(string)($opt['event'] ?? ($module==='ride' ? 'ride_new' : 'order_new'));
    return ch_push((int)$d['account_id'],$event,$title,$body,$url,['scope'=>'driver','ref'=>$module.':'.$driverId,'email'=>false,'urgency'=>$opt['urgency'] ?? 'high']+$opt);
}
/** Board-Bildschirm/-Tablet eines Moduls (order/ride/hotel), angemeldet über push-sub.php mit dem Board-Token. */
function push_board(int $acc, string $module, string $title, string $body, string $url='', array $opt=[]): bool {
    if(!in_array($module,['order','ride','hotel'],true)) return false;
    return ch_push($acc,(string)($opt['event'] ?? 'system'),$title,$body,$url,['scope'=>'board','ref'=>$module.':board','email'=>false]+$opt);
}
/** Öffentliche Anmeldung (push-sub.php): Fahrer- bzw. Board-Token → ['acc','ref','module','name'] oder null. */
function push_resolve_ref(string $scope, string $t, string $module=''): ?array {
    if(!preg_match('/^[a-f0-9]{24}$/',$t)) return null;
    if($scope==='driver'){
        if($module==='taxi') $module='ride';
        if(in_array($module,['','ride'],true) && function_exists('ride_driver_by_token')){ $d=ride_driver_by_token($t); if($d && (int)$d['account_id']>0) return ['acc'=>(int)$d['account_id'],'ref'=>'ride:'.(int)$d['id'],'module'=>'ride','name'=>(string)$d['name']]; }
        if(in_array($module,['','order'],true) && function_exists('ord_driver_by_token')){ $d=ord_driver_by_token($t); if($d && (int)$d['account_id']>0) return ['acc'=>(int)$d['account_id'],'ref'=>'order:'.(int)$d['id'],'module'=>'order','name'=>(string)$d['name']]; }
        return null;
    }
    if($scope==='board'){
        foreach(['order'=>'order_settings','ride'=>'ride_settings','hotel'=>'hotel_settings'] as $m=>$tbl){
            if($module!=='' && $module!==$m) continue;
            try{ $s=th_db()->prepare("SELECT account_id FROM $tbl WHERE board_token=?"); $s->execute([$t]); $a=$s->fetchColumn(); }catch(Throwable $e){ $a=false; }
            if($a!==false && (int)$a>0) return ['acc'=>(int)$a,'ref'=>$m.':board','module'=>$m,'name'=>'Board'];
        }
    }
    return null;
}

/* ---------- Studio-REST: /v1/push/* ---------- */
function push_config(int $aid): array {
    $prefs=push_prefs($aid); $events=[];
    foreach(PUSH_EVENTS as $k=>$m) $events[]=['key'=>$k,'label'=>$m[0],'group'=>$m[1],'group_label'=>PUSH_GROUPS[$m[1]],'hint'=>$m[4],'urgent'=>$m[2]==='high','critical'=>$m[3],'on'=>$prefs['events'][$k]];
    $s=th_db()->prepare("SELECT scope, COUNT(*) n FROM push_devices WHERE account_id=? AND scope<>'owner' GROUP BY scope"); $s->execute([$aid]); $other=$s->fetchAll(PDO::FETCH_KEY_PAIR);
    return ['vapid_public'=>push_vapid_public(),'events'=>$events,'groups'=>PUSH_GROUPS,'prefs'=>$prefs['events'],'email_fallback'=>$prefs['email_fallback'],'quiet'=>$prefs['quiet'],
        'devices'=>array_map('push_device_public',push_devices($aid,'owner')),'drivers'=>(int)($other['driver'] ?? 0),'boards'=>(int)($other['board'] ?? 0),
        'fcm_ready'=>push_fcm_ready(),'unread'=>push_unread($aid),'email'=>push_owner_email($aid),'in_quiet'=>push_in_quiet($prefs)];
}
function push_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if(!str_starts_with($route,'push/')) return null;
    try{ push_bootstrap(); }catch(Throwable $e){ return [500,['error'=>'push_unavailable','message'=>'Benachrichtigungen sind gerade nicht verfügbar.']]; }
    $need=fn(string $m)=>$method===$m;
    switch($route){
        case 'push/config':
            if(!$need('GET')) return [405,['error'=>'method_not_allowed']];
            return [200,push_config($aid)];
        case 'push/subscribe':
            if(!$need('POST')) return [405,['error'=>'method_not_allowed']];
            if(!th_rate('pushsub:'.$aid,40,3600)) return [429,['error'=>'rate_limited','message'=>'Zu viele Anmeldungen – bitte später erneut.']];
            $s=push_parse_subscription($body); if(isset($s['error'])) return [422,$s];
            $id=push_device_upsert($aid,'owner','',$s['type'],$s['data']);
            $d=th_db()->prepare("SELECT * FROM push_devices WHERE id=? AND account_id=?"); $d->execute([$id,$aid]); $row=$d->fetch(PDO::FETCH_ASSOC);
            return [200,['ok'=>true,'id'=>$id,'device'=>$row ? push_device_public($row) : null,'fcm_ready'=>push_fcm_ready()]];
        case 'push/unsubscribe':
            if(!$need('POST')) return [405,['error'=>'method_not_allowed']];
            $n=0;
            if(!empty($body['id'])){ $s=th_db()->prepare("DELETE FROM push_devices WHERE id=? AND account_id=? AND scope='owner'"); $s->execute([(int)$body['id'],$aid]); $n=$s->rowCount(); }
            else { $raw=!empty($body['endpoint']) ? (string)$body['endpoint'] : (!empty($body['token']) ? 'fcm:'.(string)$body['token'] : '');
                if($raw!==''){ $s=th_db()->prepare("DELETE FROM push_devices WHERE endpoint_hash=? AND account_id=?"); $s->execute([push_hash('owner','',$raw),$aid]); $n=$s->rowCount(); } }
            return [200,['ok'=>true,'removed'=>$n,'devices'=>array_map('push_device_public',push_devices($aid,'owner'))]];
        case 'push/prefs':
            if(!$need('POST')) return [405,['error'=>'method_not_allowed']];
            $pr=push_prefs_save($aid,$body);
            return [200,['ok'=>true,'prefs'=>$pr['events'],'email_fallback'=>$pr['email_fallback'],'quiet'=>$pr['quiet'],'in_quiet'=>push_in_quiet($pr)]];
        case 'push/test':
            if(!$need('POST')) return [405,['error'=>'method_not_allowed']];
            if(!th_rate('pushtest:'.$aid,12,600)) return [429,['error'=>'rate_limited','message'=>'Bitte einen Moment warten, bevor du erneut testest.']];
            $devs=push_devices($aid,'owner');
            $title='Test: Benachrichtigungen funktionieren ✓'; $txt='So sieht eine Meldung deiner Agenten aus – z. B. bei einer neuen Bestellung oder Fahrt.';
            th_db()->prepare("INSERT INTO push_inbox(account_id,scope,ref,event,title,body,url,tag) VALUES(?,?,?,?,?,?,?,?)")->execute([$aid,'owner','','system',$title,$txt,'/chat/studio.php#benachrichtigungen','test']);
            $iid=(int)th_db()->lastInsertId();
            $res=push_send_devices($devs,['title'=>$title,'body'=>$txt,'url'=>'/chat/studio.php#benachrichtigungen','tag'=>'test','event'=>'system','id'=>$iid,'urgency'=>'high','badge'=>push_unread($aid)],600);
            $ok=count(array_filter($res,fn($r)=>$r['ok']));
            return [200,['ok'=>$ok>0,'sent'=>$ok,'total'=>count($res),'results'=>$res,'devices'=>array_map('push_device_public',push_devices($aid,'owner')),'unread'=>push_unread($aid),
                'message'=>!$res ? 'Noch kein Gerät angemeldet – aktiviere zuerst Benachrichtigungen auf diesem Gerät.' : ($ok===count($res) ? 'Test an alle Geräte gesendet.' : $ok.' von '.count($res).' Geräten erreicht.')]];
        case 'push/inbox':
            if(!$need('GET')) return [405,['error'=>'method_not_allowed']];
            $lim=max(1,min(100,(int)($_GET['limit'] ?? 30))); $items=push_inbox($aid,max(0,(int)($_GET['since'] ?? 0)),$lim+1,max(0,(int)($_GET['before'] ?? 0)));
            return [200,['items'=>array_slice($items,0,$lim),'more'=>count($items)>$lim,'unread'=>push_unread($aid)]];
        case 'push/read':
            if(!$need('POST')) return [405,['error'=>'method_not_allowed']];
            $ids=$body['ids'] ?? (!empty($body['all']) ? 'all' : []); $n=push_mark_read($aid,$ids==='all' ? 'all' : (array)$ids);
            return [200,['ok'=>true,'marked'=>$n,'unread'=>push_unread($aid)]];
    }
    return [404,['error'=>'not_found','message'=>'Unbekannte Benachrichtigungs-Route.']];
}
/* Modul-Vertrag (Werkzeug-Register): Benachrichtigungen haben keine KI-Werkzeuge. */
function push_enabled(int $acc): bool { return true; }
function push_tools(): array { return []; }
function push_system_hint(int $acc, string $channel='chat'): string { return ''; }
