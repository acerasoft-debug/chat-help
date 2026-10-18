<?php
/**
 * ChatHelp — Kunden über alle Kanäle (Telefon, WhatsApp, Website) + zweiseitige Benachrichtigungen
 * ==================================================================================================
 * Gleiche E.164-Nummer = gleiche Person, immer nur innerhalb desselben Betriebs (account_id).
 *   contacts            – eine Zeile je Kunde (lazy gepflegt aus den Modultabellen, siehe contacts_sync)
 *   contact_events      – eigene Ereignisse: Benachrichtigungen, Antworten aus dem Studio, Notizen
 * Quellen des Verlaufs: phone_calls (+ Transkript), wa_sessions (WhatsApp-Webhook), orders, appt_bookings, rides,
 * hotel_bookings, hotel_requests, callbacks.
 *
 * Datenschutz: Der volle Verlauf ist nur für den Betreiber selbst (Studio-API, Bearer-Schlüssel des Kontos).
 * Agenten bekommen eine Kurzfassung (contacts_context) ausschließlich auf Telefon/WhatsApp mit vom Kanal
 * verifizierter Nummer (ch_verified_phone) – nie im Website-Chat mit eingetippter Nummer.
 * Benachrichtigungen per WhatsApp/SMS nur an verifizierte Nummern (Kanal Telefon/WhatsApp oder Aktion des Betreibers).
 *
 * Studio-REST (über ch_module_api → contacts_api):
 *   GET  /v1/contacts/list?q=&limit=     GET  /v1/contacts/get?phone=
 *   POST /v1/contacts/reply {phone,text} | {phone,action:'callback',name?,topic?,preferred_time?,notify?}
 *   POST /v1/contacts/note  {phone,notes?,name?}
 * Tests: $GLOBALS['CONTACTS_SENDERS'] ersetzt die Versandwege (wa_ready, window, wa, sms_ready, sms, mail).
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/order-lib.php';   // ord_settings, ord_wa_send, ord_sms_send, ord_wa_window_open (lädt appt-lib)

const CONTACTS_CHANNEL_LABELS=['phone'=>'Telefon','whatsapp'=>'WhatsApp','web'=>'Website','studio'=>'Studio','sms'=>'SMS','email'=>'E-Mail'];
const CONTACTS_DAYS=365;

function contacts_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, phone_e164 VARCHAR(20) NOT NULL, name VARCHAR(120) DEFAULT '', lang VARCHAR(8) DEFAULT '',
        first_seen DATETIME NULL, last_seen DATETIME NULL, last_channel VARCHAR(12) DEFAULT '', notes TEXT NULL, updated_at DATETIME NULL,
        UNIQUE KEY uq_acc_phone (account_id, phone_e164), INDEX(account_id, last_seen)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS contact_events (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, phone_e164 VARCHAR(20) NOT NULL, kind VARCHAR(12) NOT NULL, event VARCHAR(40) DEFAULT '',
        channel VARCHAR(24) DEFAULT '', ok TINYINT(1) DEFAULT 1, body VARCHAR(2000) DEFAULT '', meta TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(account_id, phone_e164, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS contact_sync (account_id INT NOT NULL PRIMARY KEY, synced_at DATETIME NULL) ENGINE=InnoDB");
    if(random_int(1,60)===1){ try{   // Kunden gelöschter Konten aufräumen
        $p->exec("DELETE c FROM contacts c LEFT JOIN token_accounts a ON a.id=c.account_id WHERE a.id IS NULL");
        $p->exec("DELETE e FROM contact_events e LEFT JOIN token_accounts a ON a.id=e.account_id WHERE a.id IS NULL");
        $p->exec("DELETE s FROM contact_sync s LEFT JOIN token_accounts a ON a.id=s.account_id WHERE a.id IS NULL"); }catch(Throwable $e){} }
}

/* ======================================================================= kleine Helfer */
/** Nummer → E.164 (WhatsApp-Absender kommen als reine Ziffern „4917…“). '' = ungültig. */
function contacts_e164(string $raw): string { $raw=trim($raw); if($raw==='') return ''; if(preg_match('/^\d{10,15}$/',$raw) && $raw[0]!=='0') $raw='+'.$raw; return th_e164($raw); }
/** Gleiche Person? Beide normalisierbar → exakt gleiche E.164, sonst th_same_phone. */
function contacts_same(string $a, string $b): bool { $ea=contacts_e164($a); $eb=contacts_e164($b); if($ea!=='' && $eb!=='') return $ea===$eb; return th_same_phone($a,$b); }
function contacts_digits_sql(string $col): string { return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,' ',''),'+',''),'-',''),'/',''),'(',''),')','')"; }
/** Anzeige mit teilweiser Maskierung: +49 170 ••• 567 */
function contacts_mask(string $e164): string { $d=ltrim($e164,'+'); if(strlen($d)<8) return '•••'; $cc=function_exists('cb_cc_len') ? cb_cc_len($d) : 2; return '+'.substr($d,0,$cc).' '.substr($d,$cc,3).' ••• '.substr($d,-3); }
function contacts_display(string $e164): string { $d=ltrim($e164,'+'); if($d==='') return ''; $cc=function_exists('cb_cc_len') ? cb_cc_len($d) : 2; return '+'.substr($d,0,$cc).' '.substr($d,$cc,3).' '.substr($d,$cc+3); }
/** Kanal-Rohwerte der Module → phone | whatsapp | web | studio */
function contacts_chan(string $c): string {
    $c=strtolower($c); if($c==='whatsapp') return 'whatsapp'; if($c==='phone' || $c==='phone_out') return 'phone';
    if(in_array($c,['studio','selftest','phone-test','seed','manual'],true)) return 'studio'; return 'web';
}
function contacts_tz(int $acc): DateTimeZone { try{ return new DateTimeZone((string)(ord_settings($acc)['tz'] ?? '') ?: 'Europe/Berlin'); }catch(Throwable $e){ return new DateTimeZone('Europe/Berlin'); } }
/** Zeitpunkt relativ: „heute 18:40“, „gestern 09:10“, „am Montag 15:00“, „12.10. 15:00“. */
function contacts_when(int $ts, DateTimeZone $tz, ?int $now=null): string {
    $d=(new DateTimeImmutable('@'.$ts))->setTimezone($tz); $n=(new DateTimeImmutable('@'.($now ?? time())))->setTimezone($tz);
    $days=(int)((new DateTimeImmutable($n->format('Y-m-d'),$tz))->diff(new DateTimeImmutable($d->format('Y-m-d'),$tz))->format('%r%a'));
    $t=$d->format('H:i'); $wd=['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'][(int)$d->format('w')];
    if($days===0) return 'heute '.$t; if($days===-1) return 'gestern '.$t; if($days===1) return 'morgen '.$t;
    if($days<0 && $days>-7) return 'am '.$wd.' '.$t; if($days>1 && $days<7) return $wd.' '.$t;
    return $d->format($d->format('Y')===$n->format('Y') ? 'd.m.' : 'd.m.Y').' '.$t;
}
/** Wanduhr-Zeit der Module (start_at, pickup_at, preferred_at …) → „Do 23.10. 15:00“ */
function contacts_wall(?string $v, bool $time=true): string {
    if(!$v || str_starts_with($v,'0000')) return ''; try{ $d=new DateTimeImmutable($v); }catch(Throwable $e){ return ''; }
    return ['So','Mo','Di','Mi','Do','Fr','Sa'][(int)$d->format('w')].' '.$d->format('d.m.').($time ? ' '.$d->format('H:i') : '');
}
function contacts_eur(?int $c): string { return $c===null ? '' : number_format($c/100,2,',','.').' €'; }
function contacts_clean(string $t, int $max=2000): string { return mb_substr(trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u','',$t) ?? ''),0,$max); }
/** Interne Notizen („[intern …]“) für die Anzeige entfernen. */
function contacts_strip_intern(string $t): string { return trim((string)preg_replace('/\n?\[intern[^\]]*\]/u','',$t)); }
function contacts_q(string $sql, array $args): array { try{ $s=th_db()->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){ return []; } }   // Modultabelle evtl. (noch) nicht vorhanden
function contacts_ts(?string $db): int { if(!$db) return 0; $d=th_dbtime($db,new DateTimeZone('UTC')); return $d ? $d->getTimestamp() : 0; }
function contacts_iso(int $ts): ?string { return $ts>0 ? gmdate('Y-m-d\TH:i:s\Z',$ts) : null; }
/** Firmenname für Nachrichten an Kunden. */
function contacts_company(int $acc): string {
    foreach([fn()=>ord_settings($acc)['name'] ?? '',fn()=>function_exists('appt_settings') ? (appt_settings($acc)['company'] ?? '') : '',fn()=>function_exists('ride_settings') ? (ride_settings($acc)['name'] ?? '') : '',
             fn()=>function_exists('hotel_settings') ? (hotel_settings($acc)['name'] ?? '') : '',fn()=>function_exists('cb_company') ? cb_company($acc) : ''] as $f){ try{ $v=trim((string)$f()); if($v!=='') return $v; }catch(Throwable $e){} }
    return '';
}
/** Eigene Nummern (Agent-Nummern, Rückruf-Absender) und Fahrer – keine Kunden. */
function contacts_internal_numbers(int $acc): array {
    $o=[]; foreach([["SELECT phone_number AS n FROM agent_configs WHERE account_id=?"],["SELECT from_number AS n FROM callback_settings WHERE account_id=?"],["SELECT phone AS n FROM drivers WHERE account_id=?"],
        ["SELECT phone AS n FROM taxi_drivers WHERE account_id=?"],["SELECT wa_number AS n FROM order_settings WHERE account_id=?"],["SELECT phone AS n FROM order_settings WHERE account_id=?"]] as [$q])
        foreach(contacts_q($q,[$acc]) as $r){ $e=contacts_e164((string)$r['n']); if($e!=='') $o[$e]=true; }
    return $o;
}
/** Anruf-SIDs ausgehender Anrufe (KI-Rückrufe): dort ist der Kunde der Angerufene. */
function contacts_outbound_sids(int $acc): array {
    $o=[]; foreach(contacts_q("SELECT call_sid, call_log FROM callbacks WHERE account_id=? AND created_at > (NOW() - INTERVAL 400 DAY)",[$acc]) as $r){
        if((string)$r['call_sid']!=='') $o[(string)$r['call_sid']]=true;
        foreach((array)(json_decode((string)$r['call_log'],true) ?: []) as $l) if(is_array($l) && !empty($l['sid'])) $o[(string)$l['sid']]=true; }
    return $o;
}

/* ======================================================================= Labels */
function contacts_status(string $kind, string $st): array {
    $L=[
      'order'=>['new'=>['Neu','gold'],'confirmed'=>['Bestätigt','indigo'],'preparing'=>['In Zubereitung','indigo'],'ready'=>['Fertig','teal'],'out_for_delivery'=>['Unterwegs','teal'],'delivered'=>['Zugestellt','ok'],'cancelled'=>['Storniert','rose']],
      'appt'=>['booked'=>['Gebucht','indigo'],'done'=>['Wahrgenommen','ok'],'noshow'=>['Nicht erschienen','rose'],'cancelled'=>['Abgesagt','rose']],
      'ride'=>['new'=>['Eingegangen','gold'],'assigned'=>['Fahrer zugeteilt','indigo'],'en_route'=>['Anfahrt','indigo'],'arrived'=>['Taxi ist da','teal'],'onboard'=>['Fahrt läuft','teal'],'completed'=>['Abgeschlossen','ok'],'cancelled'=>['Storniert','rose'],'no_show'=>['Nicht angetroffen','rose']],
      'hotel'=>['requested'=>['Anfrage','gold'],'confirmed'=>['Bestätigt','ok'],'declined'=>['Abgelehnt','rose'],'cancelled'=>['Storniert','rose'],'checked_in'=>['Eingecheckt','teal'],'checked_out'=>['Abgereist',''],'no_show'=>['Nicht angereist','rose']],
      'hotel_request'=>['open'=>['Offen','gold'],'in_progress'=>['In Arbeit','indigo'],'done'=>['Erledigt','ok'],'cancelled'=>['Storniert','']],
      'callback'=>['open'=>['Offen','gold'],'scheduled'=>['Geplant','indigo'],'calling'=>['Anruf läuft','teal'],'done'=>['Erledigt','ok'],'failed'=>['Nicht erreicht','rose'],'cancelled'=>['Storniert','']],
    ];
    $x=$L[$kind][$st] ?? [$st,'']; return ['label'=>$x[0],'tone'=>$x[1]];
}
const CONTACTS_KIND_LABELS=['call'=>'Anruf','whatsapp'=>'WhatsApp','order'=>'Bestellung','appt'=>'Termin','ride'=>'Fahrt','hotel'=>'Zimmerbuchung','hotel_request'=>'Gästewunsch','callback'=>'Rückruf','notify'=>'Benachrichtigung','reply'=>'Antwort','note'=>'Notiz'];
const CONTACTS_PANELS=['order'=>'bestellungen','appt'=>'termine','ride'=>'taxi','hotel'=>'hotel','hotel_request'=>'hotel','callback'=>'rueckruf','call'=>'phone'];

/* ======================================================================= Verlauf sammeln */
/**
 * Alle Vorgänge eines Kontos (optional nur einer Nummer) als einheitliche Einträge, neueste zuerst.
 * $o: days (Standard 365), limit je Quelle, open (nur offene Vorgänge, ohne Anrufe/WhatsApp/Ereignisse), light (ohne Transkripte/Nachrichten), events.
 * Eintrag: kind, id, code, e164, name, ts, at, channel, verified, title, sub, status, status_label, tone, open, panel (+ transcript / messages / body).
 */
function contacts_collect(int $acc, ?string $e164=null, array $o=[]): array {
    contacts_bootstrap(); if($acc<=0) return [];
    $days=max(1,min(800,(int)($o['days'] ?? CONTACTS_DAYS))); $lim=max(1,min(3000,(int)($o['limit'] ?? 300))); $open=!empty($o['open']); $light=!empty($o['light']);
    $tail=null; if($e164!==null){ $e164=contacts_e164($e164); if($e164==='') return []; $tail='%'.substr(ltrim($e164,'+'),-8); }
    $tz=contacts_tz($acc); $nowWall=(new DateTimeImmutable('now',$tz))->format('Y-m-d H:i:s'); $today=(new DateTimeImmutable('now',$tz))->format('Y-m-d');
    $since=$open ? 800 : $days; $out=[];
    $add=function(array $it) use(&$out,$e164){ if($it['e164']==='' ) return; if($e164!==null && !contacts_same($it['e164'],$e164)) return; $it['at']=contacts_iso($it['ts']); $out[]=$it; };
    $ph=fn(string $col)=>contacts_digits_sql($col).' LIKE ?';

    /* Anrufe (+ Transkript) */
    if(!$open){
        $out_sids=contacts_outbound_sids($acc); $own=contacts_internal_numbers($acc);
        $w=$tail ? ' AND ('.$ph('from_number').' OR '.$ph('to_number').')' : ''; $a=$tail ? [$acc,$tail,$tail] : [$acc];
        foreach(contacts_q("SELECT id,call_sid,plan,from_number,to_number,status,turns,outcome,".($light?"'' AS transcript":"transcript").",started_at,ended_at FROM phone_calls WHERE account_id=? AND started_at > (NOW() - INTERVAL $days DAY)$w ORDER BY id DESC LIMIT $lim",$a) as $r){
            $fe=contacts_e164((string)$r['from_number']); $te=contacts_e164((string)$r['to_number']);
            $outb=isset($out_sids[(string)$r['call_sid']]) || ($fe!=='' && isset($own[$fe]));
            if($tail!==null && !$outb && !contacts_same($fe,(string)$e164) && contacts_same($te,(string)$e164)) $outb=true;   // Nummer passt nur als Angerufener
            $cust=$outb ? $te : $fe; $tr=[];
            if(!$light){ foreach((array)(json_decode((string)$r['transcript'],true) ?: []) as $m) if(is_array($m) && in_array($m['role'] ?? '',['user','assistant'],true) && is_string($m['content'] ?? null)) $tr[]=['role'=>$m['role'],'content'=>mb_substr(contacts_strip_intern($m['content']),0,1200)]; $tr=array_slice($tr,-60); }
            $dur=($r['ended_at'] && $r['started_at']) ? max(0,contacts_ts($r['ended_at'])-contacts_ts($r['started_at'])) : null;
            $oc=['callback'=>'Rückruf gewünscht','forwarded'=>'Weitergeleitet','voicemail'=>'Mailbox','silent'=>'Stille','no_tokens'=>'Kein Guthaben'][(string)$r['outcome']] ?? '';
            $add(['kind'=>'call','id'=>(int)$r['id'],'code'=>'','e164'=>$cust,'name'=>'','ts'=>contacts_ts($r['started_at']),'channel'=>'phone','verified'=>true,'direction'=>$outb?'out':'in',
                'title'=>$outb ? 'Ausgehender Anruf (KI-Rückruf)' : 'Anruf','sub'=>trim((int)$r['turns'].' Antworten'.($dur!==null ? ' · '.max(1,(int)round($dur/60)).' Min.' : '').($oc!=='' ? ' · '.$oc : '')),
                'status'=>(string)$r['status'],'status_label'=>$r['ended_at'] ? 'Beendet' : 'Läuft','tone'=>$r['ended_at'] ? '' : 'teal','open'=>false,'panel'=>'phone','transcript'=>$tr,'turns'=>(int)$r['turns']]);
        }
        /* WhatsApp-Gespräch (Gesprächsspeicher des Webhooks: letzte Nachrichten, 7 Tage) */
        $w=$tail ? ' AND wa_from LIKE ?' : ''; $a=$tail ? [$acc,$tail] : [$acc];
        foreach(contacts_q("SELECT id,wa_from,profile,".($light?"'' AS messages":"messages").",updated_at FROM wa_sessions WHERE account_id=? AND updated_at > (NOW() - INTERVAL $days DAY)$w ORDER BY updated_at DESC LIMIT $lim",$a) as $r){
            $msgs=[]; $n=0; foreach((array)(json_decode((string)$r['messages'],true) ?: []) as $m){ if(!is_array($m) || !is_string($m['content'] ?? null)) continue; $c=contacts_strip_intern($m['content']); if($c==='') continue; $n++; if(!$light) $msgs[]=['role'=>($m['role'] ?? '')==='user' ? 'user' : 'assistant','content'=>mb_substr($c,0,1500)]; }
            $add(['kind'=>'whatsapp','id'=>(int)$r['id'],'code'=>'','e164'=>contacts_e164((string)$r['wa_from']),'name'=>(string)$r['profile'],'ts'=>contacts_ts($r['updated_at']),'channel'=>'whatsapp','verified'=>true,
                'title'=>'WhatsApp-Gespräch','sub'=>$n.' Nachrichten (letzte '.min($n,16).')','status'=>'','status_label'=>'','tone'=>'','open'=>false,'panel'=>null,'messages'=>$msgs]);
        }
    }
    /* Bestellungen */
    $w=$tail ? ' AND ('.$ph('phone').' OR wa_from LIKE ?)' : ''; $a=array_merge([$acc],$open ? [$nowWall] : [],$tail ? [$tail,$tail] : []);
    $ow=$open ? " AND status NOT IN ('delivered','cancelled') AND (created_at > (NOW() - INTERVAL 1 DAY) OR eta_at > (? - INTERVAL 12 HOUR))" : '';
    foreach(contacts_q("SELECT id,code,channel,type,status,customer_name,phone,wa_from,items,total_cents,eta_at,created_at FROM orders WHERE account_id=? AND created_at > (NOW() - INTERVAL $since DAY)$ow$w ORDER BY id DESC LIMIT $lim",$a) as $r){
        $wa=(string)$r['wa_from']; $ver=in_array($r['channel'],['whatsapp','phone'],true) && $wa!=='';
        $items=json_decode((string)$r['items'],true) ?: []; $it=implode(', ',array_map(fn($i)=>(int)($i['qty'] ?? 1).'× '.($i['name'] ?? ''),array_slice($items,0,4))).(count($items)>4 ? ' …' : '');
        $st=contacts_status('order',(string)$r['status']); $isOpen=!in_array($r['status'],['delivered','cancelled'],true);
        $e=$ver && $r['channel']==='whatsapp' ? contacts_e164($wa) : contacts_e164((string)$r['phone']); if($e==='' && $wa!=='') $e=contacts_e164($wa);
        $add(['kind'=>'order','id'=>(int)$r['id'],'code'=>(string)$r['code'],'e164'=>$e,'name'=>(string)$r['customer_name'],'ts'=>contacts_ts($r['created_at']),'channel'=>contacts_chan((string)$r['channel']),'verified'=>$ver,
            'title'=>'Bestellung '.$r['code'],'sub'=>trim($it.' · '.contacts_eur((int)$r['total_cents']).' · '.($r['type']==='pickup' ? 'Abholung' : 'Lieferung').($r['eta_at'] ? ' '.contacts_wall((string)$r['eta_at']) : ''),' ·'),
            'status'=>(string)$r['status'],'status_label'=>$st['label'],'tone'=>$st['tone'],'open'=>$isOpen,'panel'=>'bestellungen','when'=>$r['eta_at'] ? contacts_wall((string)$r['eta_at']) : '']);
    }
    /* Termine */
    $w=$tail ? ' AND '.$ph('phone') : ''; $a=array_merge([$acc],$open ? [$nowWall] : [],$tail ? [$tail] : []);
    $ow=$open ? " AND status='booked' AND start_at > ?" : '';
    foreach(contacts_q("SELECT id,code,service_name,start_at,status,customer_name,phone,email,source,created_at FROM appt_bookings WHERE account_id=? AND created_at > (NOW() - INTERVAL $since DAY)$ow$w ORDER BY id DESC LIMIT $lim",$a) as $r){
        $st=contacts_status('appt',(string)$r['status']);
        $add(['kind'=>'appt','id'=>(int)$r['id'],'code'=>(string)$r['code'],'e164'=>contacts_e164((string)$r['phone']),'name'=>(string)$r['customer_name'],'email'=>(string)$r['email'],'ts'=>contacts_ts($r['created_at']),'channel'=>contacts_chan((string)$r['source']),
            'verified'=>in_array($r['source'],['phone','whatsapp'],true),'title'=>'Termin '.$r['code'],'sub'=>trim((string)$r['service_name'].' · '.contacts_wall((string)$r['start_at']),' ·'),
            'status'=>(string)$r['status'],'status_label'=>$st['label'],'tone'=>$st['tone'],'open'=>$r['status']==='booked' && (string)$r['start_at']>$nowWall,'panel'=>'termine','when'=>contacts_wall((string)$r['start_at'])]);
    }
    /* Taxi-Fahrten */
    $w=$tail ? ' AND ('.$ph('phone').' OR wa_from LIKE ?)' : ''; $a=array_merge([$acc],$open ? [$nowWall] : [],$tail ? [$tail,$tail] : []);
    $ow=$open ? " AND status IN ('new','assigned','en_route','arrived','onboard') AND pickup_at >= (? - INTERVAL 12 HOUR)" : '';
    foreach(contacts_q("SELECT id,code,channel,status,customer_name,phone,wa_from,pickup_text,dest_text,pickup_at,asap,price_cents,created_at FROM rides WHERE account_id=? AND created_at > (NOW() - INTERVAL $since DAY)$ow$w ORDER BY id DESC LIMIT $lim",$a) as $r){
        $wa=(string)$r['wa_from']; $ver=in_array($r['channel'],['whatsapp','phone'],true) && $wa!=='';
        $st=contacts_status('ride',(string)$r['status']); $isOpen=in_array($r['status'],['new','assigned','en_route','arrived','onboard'],true);
        $e=$ver && $r['channel']==='whatsapp' ? contacts_e164($wa) : contacts_e164((string)$r['phone']); if($e==='' && $wa!=='') $e=contacts_e164($wa);
        $add(['kind'=>'ride','id'=>(int)$r['id'],'code'=>(string)$r['code'],'e164'=>$e,'name'=>(string)$r['customer_name'],'ts'=>contacts_ts($r['created_at']),'channel'=>contacts_chan((string)$r['channel']),'verified'=>$ver,
            'title'=>'Fahrt '.$r['code'],'sub'=>trim(mb_substr((string)$r['pickup_text'],0,60).((string)$r['dest_text']!=='' ? ' → '.mb_substr((string)$r['dest_text'],0,60) : '').' · '.((int)$r['asap'] ? 'sofort' : contacts_wall((string)$r['pickup_at'])),' ·'),
            'status'=>(string)$r['status'],'status_label'=>$st['label'],'tone'=>$st['tone'],'open'=>$isOpen,'panel'=>'taxi','when'=>(int)$r['asap'] ? 'sofort' : contacts_wall((string)$r['pickup_at'])]);
    }
    /* Hotel: Buchungen + Gästewünsche */
    $w=$tail ? ' AND ('.$ph('phone').' OR wa_from LIKE ?)' : ''; $a=array_merge([$acc],$open ? [$today] : [],$tail ? [$tail,$tail] : []);
    $ow=$open ? " AND status IN ('requested','confirmed','checked_in') AND checkout >= ?" : '';
    foreach(contacts_q("SELECT id,code,room_name,checkin,checkout,nights,guests,status,guest_name,phone,email,wa_from,channel,total_cents,created_at FROM hotel_bookings WHERE account_id=? AND created_at > (NOW() - INTERVAL $since DAY)$ow$w ORDER BY id DESC LIMIT $lim",$a) as $r){
        $wa=(string)$r['wa_from']; $ver=in_array($r['channel'],['whatsapp','phone'],true) && $wa!=='';
        $st=contacts_status('hotel',(string)$r['status']); $isOpen=in_array($r['status'],['requested','confirmed','checked_in'],true) && (string)$r['checkout']>=$today;
        $e=$ver && $r['channel']==='whatsapp' ? contacts_e164($wa) : contacts_e164((string)$r['phone']); if($e==='' && $wa!=='') $e=contacts_e164($wa);
        $add(['kind'=>'hotel','id'=>(int)$r['id'],'code'=>(string)$r['code'],'e164'=>$e,'name'=>(string)$r['guest_name'],'email'=>(string)$r['email'],'ts'=>contacts_ts($r['created_at']),'channel'=>contacts_chan((string)$r['channel']),'verified'=>$ver,
            'title'=>'Zimmerbuchung '.$r['code'],'sub'=>trim((string)$r['room_name'].' · '.contacts_wall((string)$r['checkin'],false).' – '.contacts_wall((string)$r['checkout'],false).' · '.(int)$r['guests'].' Pers. · '.contacts_eur((int)$r['total_cents']),' ·'),
            'status'=>(string)$r['status'],'status_label'=>$st['label'],'tone'=>$st['tone'],'open'=>$isOpen,'panel'=>'hotel','when'=>contacts_wall((string)$r['checkin'],false).' – '.contacts_wall((string)$r['checkout'],false)]);
    }
    $w=$tail ? ' AND '.$ph('contact') : ''; $a=array_merge([$acc],$tail ? [$tail] : []);
    $ow=$open ? " AND status IN ('open','in_progress')" : '';
    foreach(contacts_q("SELECT id,room_no,guest_name,kind,details,status,channel,contact,due_at,created_at FROM hotel_requests WHERE account_id=? AND created_at > (NOW() - INTERVAL $since DAY)$ow$w ORDER BY id DESC LIMIT $lim",$a) as $r){
        $st=contacts_status('hotel_request',(string)$r['status']); $kl=defined('HOTEL_KINDS') ? (HOTEL_KINDS[$r['kind']] ?? (string)$r['kind']) : (string)$r['kind'];
        $add(['kind'=>'hotel_request','id'=>(int)$r['id'],'code'=>'','e164'=>contacts_e164((string)$r['contact']),'name'=>(string)$r['guest_name'],'ts'=>contacts_ts($r['created_at']),'channel'=>contacts_chan((string)$r['channel']),
            'verified'=>in_array($r['channel'],['whatsapp','phone'],true),'title'=>'Gästewunsch: '.$kl,'sub'=>trim(((string)$r['room_no']!=='' ? 'Zimmer '.$r['room_no'].' · ' : '').mb_substr((string)$r['details'],0,120)),
            'status'=>(string)$r['status'],'status_label'=>$st['label'],'tone'=>$st['tone'],'open'=>in_array($r['status'],['open','in_progress'],true),'panel'=>'hotel','when'=>$r['due_at'] ? contacts_wall((string)$r['due_at']) : '']);
    }
    /* Rückrufe */
    $w=$tail ? ' AND '.$ph('phone') : ''; $a=array_merge([$acc],$tail ? [$tail] : []);
    $ow=$open ? " AND status IN ('open','scheduled','calling','failed')" : '';
    foreach(contacts_q("SELECT id,code,name,phone,email,topic,preferred_at,status,mode,source,result,created_at FROM callbacks WHERE account_id=? AND created_at > (NOW() - INTERVAL $since DAY)$ow$w ORDER BY id DESC LIMIT $lim",$a) as $r){
        $st=contacts_status('callback',(string)$r['status']);
        $add(['kind'=>'callback','id'=>(int)$r['id'],'code'=>(string)$r['code'],'e164'=>contacts_e164((string)$r['phone']),'name'=>(string)$r['name'],'email'=>(string)$r['email'],'ts'=>contacts_ts($r['created_at']),'channel'=>contacts_chan((string)$r['source']),
            'verified'=>in_array($r['source'],['phone','whatsapp'],true),'title'=>'Rückruf '.$r['code'].($r['mode']==='ai' ? ' · KI' : ' · Team'),
            'sub'=>trim(mb_substr((string)$r['topic'],0,140).' · '.($r['preferred_at'] ? contacts_wall((string)$r['preferred_at']) : 'so schnell wie möglich').((string)$r['result']!=='' ? ' · '.mb_substr((string)$r['result'],0,120) : ''),' ·'),
            'status'=>(string)$r['status'],'status_label'=>$st['label'],'tone'=>$st['tone'],'open'=>in_array($r['status'],['open','scheduled','calling','failed'],true),'panel'=>'rueckruf','when'=>$r['preferred_at'] ? contacts_wall((string)$r['preferred_at']) : 'so schnell wie möglich']);
    }
    /* Eigene Ereignisse (Benachrichtigungen, Antworten, Notizen) – nur im Verlauf einer Nummer */
    if(!$open && $e164!==null && !empty($o['events'])){
        foreach(contacts_q("SELECT id,kind,event,channel,ok,body,meta,created_at FROM contact_events WHERE account_id=? AND phone_e164=? AND created_at > (NOW() - INTERVAL $days DAY) ORDER BY id DESC LIMIT $lim",[$acc,$e164]) as $r){
            $meta=json_decode((string)$r['meta'],true) ?: []; $ok=(int)$r['ok']===1; $ch=(string)$r['channel'];
            $add(['kind'=>(string)$r['kind'],'id'=>(int)$r['id'],'code'=>(string)($meta['ref'] ?? ''),'e164'=>$e164,'name'=>'','ts'=>contacts_ts($r['created_at']),'channel'=>$ch,'verified'=>true,
                'title'=>(CONTACTS_KIND_LABELS[$r['kind']] ?? 'Ereignis').($ch!=='' && $r['kind']!=='note' ? ' per '.(CONTACTS_CHANNEL_LABELS[$ch] ?? $ch) : ''),'sub'=>contacts_event_label((string)$r['kind'],(string)$r['event']),'body'=>(string)$r['body'],
                'status'=>$ok ? 'sent' : 'failed','status_label'=>$r['kind']==='note' ? '' : ($ok ? 'Gesendet' : 'Nicht zugestellt'),'tone'=>$r['kind']==='note' ? '' : ($ok ? 'ok' : 'rose'),'open'=>false,'panel'=>null,'by'=>(string)($meta['by'] ?? ''),'tried'=>$meta['tried'] ?? null]);
        }
    }
    usort($out,fn($x,$y)=>[$y['ts'],$y['id']]<=>[$x['ts'],$x['id']]);
    return $out;
}
/** Ereignis → lesbarer Text (Statusmeldungen der Module, Studio-Antworten). */
function contacts_event_label(string $kind, string $ev): string {
    if($kind==='reply') return 'Aus dem Studio gesendet'; if($kind==='note') return '';
    $m=['order_confirmed'=>'Bestellung bestätigt','order_ready'=>'Bestellung fertig','order_out_for_delivery'=>'Bestellung unterwegs','order_delivered'=>'Bestellung zugestellt','order_cancelled'=>'Bestellung storniert',
        'appt_confirmed'=>'Terminbestätigung','appt_cancelled'=>'Terminabsage','ride_cancelled'=>'Fahrt storniert','callback_scheduled'=>'Rückruf geplant'];
    return $m[$ev] ?? ($ev!=='' ? 'Statusmeldung' : '');
}
/** Verlauf einer Nummer (Studio): alle Vorgänge desselben Betriebs, neueste zuerst. */
function contacts_timeline(int $acc, string $phone, int $limit=150): array {
    return array_slice(contacts_collect($acc,$phone,['events'=>true,'limit'=>$limit]),0,$limit);
}
/** Offene Vorgänge (Bestellung, Termin, Fahrt, Zimmer, Gästewunsch, Rückruf) – je Nummer gruppiert. */
function contacts_open_map(int $acc, ?string $phone=null): array {
    $m=[]; foreach(contacts_collect($acc,$phone,['open'=>true,'light'=>true,'limit'=>500]) as $it){ if(!$it['open']) continue; $m[$it['e164']][]=$it; } return $m;
}

/* ======================================================================= Kundenliste (lazy gepflegt) */
/** Kundenliste aus den Modultabellen aktualisieren – höchstens alle 60 s je Konto (erster Lauf: 365 Tage, danach ab letztem Lauf − 2 Tage). */
function contacts_sync(int $acc, bool $force=false): int {
    contacts_bootstrap(); if($acc<=0) return 0; $p=th_db();
    $st=$p->prepare("SELECT TIMESTAMPDIFF(SECOND,synced_at,NOW()) AS age, synced_at FROM contact_sync WHERE account_id=?"); $st->execute([$acc]); $row=$st->fetch(PDO::FETCH_ASSOC) ?: null;
    if(!$force && $row && $row['age']!==null && (int)$row['age']>=0 && (int)$row['age']<60) return 0;
    $days=CONTACTS_DAYS; if($row && $row['synced_at'] && !$force){ $days=max(2,min(CONTACTS_DAYS,(int)ceil((int)$row['age']/86400)+2)); }
    $p->prepare("INSERT INTO contact_sync(account_id,synced_at) VALUES(?,NOW()) ON DUPLICATE KEY UPDATE synced_at=NOW()")->execute([$acc]);
    $internal=contacts_internal_numbers($acc); $g=[];
    foreach(contacts_collect($acc,null,['days'=>$days,'light'=>true,'limit'=>2000]) as $it){
        $e=$it['e164']; if($e==='' || isset($internal[$e]) || $it['ts']<=0) continue;
        $x=$g[$e] ?? ['vname'=>'','uname'=>'','first'=>PHP_INT_MAX,'last'=>0,'ch'=>''];
        if($it['name']!==''){ if($it['verified'] && $x['vname']==='') $x['vname']=$it['name']; if($x['uname']==='') $x['uname']=$it['name']; }   // neueste zuerst: Name aus bestätigtem Kanal bevorzugt
        $x['first']=min($x['first'],$it['ts']); if($it['ts']>=$x['last']){ $x['last']=$it['ts']; $x['ch']=$it['channel']; } $g[$e]=$x;
    }
    if(!$g) return 0;
    $off=(int)round(((int)$p->query("SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())")->fetchColumn())/900)*900;   // DB-Zeit wie CURRENT_TIMESTAMP
    $db=fn(int $ts)=>gmdate('Y-m-d H:i:s',$ts+$off);
    $up=$p->prepare("INSERT INTO contacts(account_id,phone_e164,name,first_seen,last_seen,last_channel,updated_at) VALUES(?,?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE name=IF(name='',VALUES(name),name), last_channel=IF(last_seen IS NULL OR VALUES(last_seen)>=last_seen,VALUES(last_channel),last_channel),
        first_seen=IF(first_seen IS NULL OR VALUES(first_seen)<first_seen,VALUES(first_seen),first_seen), last_seen=IF(last_seen IS NULL OR VALUES(last_seen)>last_seen,VALUES(last_seen),last_seen), updated_at=NOW()");
    foreach($g as $e=>$x) $up->execute([$acc,$e,mb_substr($x['vname'] ?: $x['uname'],0,120),$db($x['first']),$db($x['last']),$x['ch']]);
    return count($g);
}
/** Kontaktzeile (ohne anzulegen) oder null. */
function contacts_get_row(int $acc, string $e164): ?array { contacts_bootstrap(); $s=th_db()->prepare("SELECT * FROM contacts WHERE account_id=? AND phone_e164=?"); $s->execute([$acc,$e164]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function contacts_ensure(int $acc, string $e164): void { contacts_bootstrap(); if($acc<=0 || $e164==='') return; th_db()->prepare("INSERT IGNORE INTO contacts(account_id,phone_e164,updated_at) VALUES(?,?,NOW())")->execute([$acc,$e164]); }
/** Sprache des Kunden merken (für den 13-Sprachen-Baustein). */
function contacts_set_lang(int $acc, string $phone, string $lang): void {
    $e=contacts_e164($phone); $lang=strtolower(preg_replace('/[^a-zA-Z-]/','',$lang) ?? ''); if($acc<=0 || $e==='' || $lang==='') return; contacts_ensure($acc,$e);
    th_db()->prepare("UPDATE contacts SET lang=?, updated_at=NOW() WHERE account_id=? AND phone_e164=?")->execute([mb_substr($lang,0,8),$acc,$e]);
}
/** Nummern mit offenem WhatsApp-24-h-Fenster (wie ord_wa_window_open: Kundennachricht in den letzten 23 h) → [e164 => Ablauf-Zeitstempel]. */
function contacts_wa_windows(int $acc): array {
    $o=[]; foreach(contacts_q("SELECT wa_from, updated_at FROM wa_sessions WHERE account_id=? AND updated_at > (NOW() - INTERVAL 23 HOUR)",[$acc]) as $r){ $e=contacts_e164((string)$r['wa_from']); if($e!=='') $o[$e]=contacts_ts($r['updated_at'])+23*3600; }
    return $o;
}
function contacts_public_row(array $c, array $openMap, array $win): array {
    $e=(string)$c['phone_e164']; $ls=contacts_ts($c['last_seen'] ?? null);
    return ['phone'=>$e,'phone_masked'=>contacts_mask($e),'name'=>(string)$c['name'],'lang'=>(string)$c['lang'],'last_channel'=>(string)$c['last_channel'],'last_channel_label'=>CONTACTS_CHANNEL_LABELS[$c['last_channel']] ?? '',
        'last_seen'=>contacts_iso($ls),'first_seen'=>contacts_iso(contacts_ts($c['first_seen'] ?? null)),'open_count'=>count($openMap[$e] ?? []),'window_open'=>isset($win[$e]),'has_notes'=>trim((string)$c['notes'])!==''];
}
/** Studio-Liste: Suche nach Name, Nummer (Ziffern) oder Notiz; neueste zuerst. */
function contacts_list(int $acc, string $q='', int $limit=100): array {
    contacts_sync($acc); $limit=max(1,min(300,$limit)); $q=trim(mb_substr($q,0,80)); $args=[$acc]; $w='';
    if($q!==''){ $d=preg_replace('/\D/','',$q); $parts=['name LIKE ?','notes LIKE ?']; $args[]='%'.$q.'%'; $args[]='%'.$q.'%';
        if(strlen($d)>=3){ $d=ltrim($d,'0'); $parts[]='phone_e164 LIKE ?'; $args[]='%'.$d.'%'; } $w=' AND ('.implode(' OR ',$parts).')'; }
    $st=th_db()->prepare("SELECT * FROM contacts WHERE account_id=?$w ORDER BY (last_seen IS NULL), last_seen DESC, id DESC LIMIT $limit"); $st->execute($args);
    $open=contacts_open_map($acc); $win=contacts_wa_windows($acc);
    return array_map(fn($c)=>contacts_public_row($c,$open,$win),$st->fetchAll(PDO::FETCH_ASSOC));
}
/** Gesamtbild einer Nummer fürs Studio. null = nichts bekannt. */
function contacts_detail(int $acc, string $phone): ?array {
    $e=contacts_e164($phone); if($acc<=0 || $e==='') return null; contacts_sync($acc);
    $items=contacts_timeline($acc,$e); $row=contacts_get_row($acc,$e); if(!$items && !$row) return null;
    if(!$row){ contacts_ensure($acc,$e); $row=contacts_get_row($acc,$e); }
    $win=contacts_wa_windows($acc); $open=array_values(array_filter($items,fn($i)=>!empty($i['open'])));
    $name=(string)($row['name'] ?? ''); if($name==='') foreach($items as $i){ if($i['name']!==''){ $name=$i['name']; break; } }
    $emails=array_values(array_unique(array_filter(array_map(fn($i)=>(string)($i['email'] ?? ''),$items))));
    $chans=array_values(array_unique(array_filter(array_map(fn($i)=>in_array($i['kind'],['notify','reply','note'],true) ? '' : $i['channel'],$items))));
    $S=contacts_senders();
    return ['contact'=>['phone'=>$e,'phone_display'=>contacts_display($e),'phone_masked'=>contacts_mask($e),'name'=>$name,'notes'=>(string)($row['notes'] ?? ''),'lang'=>(string)($row['lang'] ?? ''),
            'last_channel'=>(string)($row['last_channel'] ?? ''),'last_seen'=>contacts_iso(contacts_ts($row['last_seen'] ?? null)),'first_seen'=>contacts_iso(contacts_ts($row['first_seen'] ?? null)),'channels'=>$chans,'emails'=>$emails],
        'items'=>$items,'open'=>$open,
        'whatsapp'=>['ready'=>(bool)($S['wa_ready'])($acc),'window_open'=>isset($win[$e]),'expires_at'=>isset($win[$e]) ? contacts_iso($win[$e]) : null],
        'sms_ready'=>(bool)($S['sms_ready'])($acc),'callbacks_on'=>function_exists('cb_enabled') ? cb_enabled($acc) : false];
}

/* ======================================================================= Agent-Kontext */
/**
 * Kurzfassung für den Systemprompt (≤ 600 Zeichen): letzter Kontakt + Kanal, offene Vorgänge mit Code und Status.
 * Nur Telefon/WhatsApp mit verifizierter Nummer (ch_verified_phone), nur derselbe Betrieb; '' = nichts bekannt / nicht erlaubt.
 */
function contacts_context(int $acc, string $channel, string $from): string {
    if($acc<=0 || !in_array($channel,['phone','whatsapp'],true)) return '';
    $vp=function_exists('ch_verified_phone') ? ch_verified_phone($acc,$channel,$from) : ((strlen(preg_replace('/\D/','',$from))>=8) ? $from : null);
    if($vp===null) return ''; $e=contacts_e164((string)$vp); if($e==='') return '';
    try{
        $tz=contacts_tz($acc); $now=time();
        $hist=contacts_collect($acc,$e,['days'=>180,'light'=>true,'limit'=>30]); $open=contacts_open_map($acc,$e)[$e] ?? [];
        $prev=null; foreach($hist as $i){ if($i['verified'] && $i['ts']>0 && $i['ts']<$now-20*60){ $prev=$i; break; } }   // nur bestätigte Kanäle; laufendes Gespräch nicht als „letzter Kontakt“ zählen
        if(!$prev && !$open) return '';
        $row=contacts_get_row($acc,$e); $name=''; foreach($hist as $i){ if($i['name']!=='' && $i['verified']){ $name=$i['name']; break; } }   // Name nur aus bestätigten Kanälen (nie aus einer eingetippten Nummer)
        $head="\n\nKUNDENKONTEXT (gleiche Nummer, nur dieser Betrieb; intern – nicht vorlesen, nur nutzen):";
        $parts=[]; if($name!=='') $parts[]=' Name laut Verlauf: '.mb_substr($name,0,40).'.';
        if($prev) $parts[]=' Letzter Kontakt: '.contacts_when($prev['ts'],$tz,$now).' per '.(CONTACTS_CHANNEL_LABELS[$prev['channel']] ?? $prev['channel']).(in_array($prev['kind'],['call','whatsapp'],true) ? '' : ' ('.(CONTACTS_KIND_LABELS[$prev['kind']] ?? $prev['kind']).')').'.';
        if(!empty($row['lang'])) $parts[]=' Sprache: '.$row['lang'].'.';
        $tailTxt=$open ? ' Fragt der Kunde danach, direkt darauf eingehen; nichts doppelt anlegen.' : '';
        $txt=$head.implode('',$parts);
        if($open){
            $labels=['order'=>'Bestellung','appt'=>'Termin','ride'=>'Fahrt','hotel'=>'Zimmer','hotel_request'=>'Gästewunsch','callback'=>'Rückruf'];
            $bits=[]; foreach($open as $i){ $bits[]=($labels[$i['kind']] ?? $i['kind']).($i['code']!=='' ? ' '.$i['code'] : '').' – '.$i['status_label'].(($i['when'] ?? '')!=='' ? ' ('.$i['when'].')' : ''); }
            $lead=' Offen: '; $acc2='';
            foreach($bits as $k=>$b){ $cand=$acc2==='' ? $b : $acc2.'; '.$b; if(mb_strlen($txt.$lead.$cand.'.'.$tailTxt)>600){ $rest=count($bits)-$k; $more=' (+'.$rest.' weitere)'; if(mb_strlen($txt.$lead.$acc2.$more.'.'.$tailTxt)<=600) $acc2.=$more; break; } $acc2=$cand; }
            if($acc2!=='') $txt.=$lead.$acc2.'.';
            $txt.=$tailTxt;
        }
        return mb_strlen($txt)>600 ? mb_substr($txt,0,599).'…' : $txt;
    }catch(Throwable $ex){ error_log('CH contacts_context: '.$ex->getMessage()); return ''; }
}

/* ======================================================================= Versand (zweiseitig) */
function contacts_wa_ready(int $acc): bool {
    $s=ord_settings($acc);
    if($s['wa_provider']==='meta') return $s['wa_phone_id']!=='' && (string)$s['wa_token']!=='';
    if($s['wa_provider']==='twilio' && $s['wa_number']!==''){ $tw=th_twilio($acc); if(!$tw) return false; $f=th_e164((string)$s['wa_number']); return $f!=='' && ($tw['own'] || ord_twilio_wa_allowed($acc,$f)); }
    return false;
}
function contacts_sms_ready(int $acc): bool {
    $tw=th_twilio($acc); if(!$tw) return false;
    return ($tw['from'] ?: ($tw['own'] ? '' : (defined('TWILIO_SMS_FROM') ? (string)TWILIO_SMS_FROM : '')))!=='';
}
/** Versandwege (in Tests per $GLOBALS['CONTACTS_SENDERS'] ersetzbar). */
function contacts_senders(): array {
    $d=['wa_ready'=>fn(int $a): bool=>contacts_wa_ready($a),'window'=>fn(int $a, string $to): bool=>ord_wa_window_open($a,$to),
        'wa'=>fn(int $a, string $to, string $t): bool=>ord_wa_send(ord_settings($a),$to,$t),
        'sms_ready'=>fn(int $a): bool=>contacts_sms_ready($a),'sms'=>fn(int $a, string $to, string $t): bool=>ord_sms_send($to,$t,$a),
        'mail'=>fn(int $a, string $to, string $subj, string $html, string $text): bool=>th_mail($to,$subj,$html,$text)];
    $o=$GLOBALS['CONTACTS_SENDERS'] ?? null; return is_array($o) ? array_merge($d,$o) : $d;
}
/** Ereignis in den Verlauf schreiben. */
function contacts_log(int $acc, string $e164, string $kind, string $event, string $channel, bool $ok, string $body, array $meta=[]): void {
    if($acc<=0 || $e164==='') return; contacts_bootstrap(); contacts_ensure($acc,$e164);
    try{ th_db()->prepare("INSERT INTO contact_events(account_id,phone_e164,kind,event,channel,ok,body,meta) VALUES(?,?,?,?,?,?,?,?)")
        ->execute([$acc,$e164,mb_substr($kind,0,12),mb_substr($event,0,40),mb_substr($channel,0,24),$ok?1:0,contacts_clean($body,2000),$meta ? json_encode($meta,JSON_UNESCAPED_UNICODE) : null]); }catch(Throwable $e){ error_log('CH contacts_log: '.$e->getMessage()); }
}
/** Gesendete WhatsApp-Nachricht als interne Notiz im Gesprächsspeicher vermerken, damit der Agent beim nächsten Kundentext Bescheid weiß.
 *  updated_at bleibt unverändert – das 24-h-Fenster zählt nur Nachrichten des Kunden. */
function contacts_wa_note(int $acc, string $e164, string $note): void {
    $from=ltrim($e164,'+'); if($acc<=0 || $from==='') return;
    try{
        $st=th_db()->prepare("SELECT messages FROM wa_sessions WHERE account_id=? AND wa_from=?"); $st->execute([$acc,$from]); $raw=$st->fetchColumn(); if($raw===false) return;
        $m=json_decode((string)$raw,true); if(!is_array($m) || !$m) return;
        $n='[intern Nachricht vom Team an den Kunden: '.mb_substr(trim(preg_replace('/[\[\]\s]+/u',' ',$note) ?? ''),0,400).']';
        $last=count($m)-1; if(($m[$last]['role'] ?? '')==='assistant' && is_string($m[$last]['content'] ?? null)) $m[$last]['content'].="\n".$n; else $m[]=['role'=>'assistant','content'=>$n];
        th_db()->prepare("UPDATE wa_sessions SET messages=?, updated_at=updated_at WHERE account_id=? AND wa_from=?")->execute([json_encode(array_slice($m,-16),JSON_UNESCAPED_UNICODE),$acc,$from]);
    }catch(Throwable $e){ error_log('CH contacts_wa_note: '.$e->getMessage()); }
}
function contacts_mail_html(string $company, string $text): string {
    $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); $t=nl2br($e($text)); $t=preg_replace('~(https://[^\s<]+)~','<a href="$1" style="color:#b8862b">$1</a>',$t);
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:30px;border:1px solid #e6e8ef;border-radius:20px;color:#0f1222">'
        .($company!=='' ? '<div style="font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:#b8862b;font-weight:700">'.$e($company).'</div>' : '')
        .'<p style="font-size:16px;line-height:1.55;margin:14px 0 0">'.$t.'</p></div>';
}
/**
 * Statusmeldung auf dem Kanal, über den der Kunde kam: WhatsApp im 24-h-Fenster → sonst SMS (falls eingerichtet) → sonst E-Mail (falls bekannt).
 * $opt: verified (bool, Standard true – false = nur E-Mail, z. B. bei eingetippter Nummer auf der Website), email, subject, sms_text,
 *       wa/sms/mail (false = Weg auslassen), wa_last_try (WhatsApp auch außerhalb des Fensters versuchen – zählt nicht als zugestellt), ref (Code), by.
 * Rückgabe: ['ok'=>bool,'channel'=>'whatsapp'|'sms'|'email'|null,'tried'=>[…]]. Jeder Versuch steht im Kundenverlauf.
 */
function contacts_notify(int $acc, string $phone, string $text, string $event, array $opt=[]): array {
    $text=contacts_clean($text,1500); if($acc<=0 || $text==='') return ['ok'=>false,'channel'=>null,'tried'=>[],'reason'=>$acc<=0 ? 'demo' : 'empty'];
    $e=contacts_e164($phone); $verified=(bool)($opt['verified'] ?? true); $email=trim((string)($opt['email'] ?? '')); if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $email='';
    $S=contacts_senders(); $tried=[]; $ok=false; $via=null; $waTried=false; $waReady=false;
    try{
        if($verified && $e!=='' && ($opt['wa'] ?? true)){ $waReady=(bool)($S['wa_ready'])($acc);
            if($waReady && ($S['window'])($acc,$e)){ $tried[]='whatsapp'; $waTried=true; if(($S['wa'])($acc,$e,$text)){ $ok=true; $via='whatsapp'; } } }
        if(!$ok && $verified && $e!=='' && ($opt['sms'] ?? true) && ($S['sms_ready'])($acc)){ $tried[]='sms'; if(($S['sms'])($acc,$e,(string)($opt['sms_text'] ?? $text))){ $ok=true; $via='sms'; } }
        if(!$ok && $email!=='' && ($opt['mail'] ?? true)){ $co=contacts_company($acc); $tried[]='email';
            if(($S['mail'])($acc,$email,(string)($opt['subject'] ?? (($co!=='' ? $co.' · ' : '').'Neuigkeiten zu Ihrem Auftrag')),contacts_mail_html($co,$text),$text)){ $ok=true; $via='email'; } }
        if(!$ok && !$waTried && !empty($opt['wa_last_try']) && $verified && $e!=='' && ($opt['wa'] ?? true) && $waReady){ $tried[]='whatsapp_ohne_fenster'; ($S['wa'])($acc,$e,$text); }
    }catch(Throwable $ex){ error_log('CH contacts_notify '.$event.': '.$ex->getMessage()); }
    if($tried && $e!=='') contacts_log($acc,$e,'notify',$event,$via ?? $tried[0],$ok,$text,array_filter(['tried'=>$tried,'ref'=>(string)($opt['ref'] ?? ''),'by'=>(string)($opt['by'] ?? '')]));
    if($ok && $via==='whatsapp') contacts_wa_note($acc,$e,$text);
    return ['ok'=>$ok,'channel'=>$via,'tried'=>$tried];
}
/** Antwort aus dem Studio per WhatsApp – nur im 24-h-Fenster (freie Texte außerhalb stellen Meta/Twilio nicht zu). */
function contacts_reply(int $acc, string $phone, string $text, string $by='studio'): array {
    $e=contacts_e164($phone); $text=contacts_clean($text,1500);
    if($e==='') return ['error'=>'phone_invalid','message'=>'Ungültige Telefonnummer.'];
    if($text==='') return ['error'=>'text_required','message'=>'Bitte eine Nachricht eingeben.'];
    $S=contacts_senders();
    if(!($S['wa_ready'])($acc)) return ['error'=>'whatsapp_not_connected','message'=>'WhatsApp ist noch nicht verbunden (Studio → Bestellungen/Verbindungen). Du kannst stattdessen einen Rückruf planen.','callback_possible'=>true];
    if(!($S['window'])($acc,$e)) return ['error'=>'window_closed','message'=>'Das 24-Stunden-Fenster von WhatsApp ist geschlossen: Der Kunde hat in den letzten 24 Stunden nicht geschrieben, freie Nachrichten werden dann nicht zugestellt. Plane stattdessen einen Rückruf – oder warte, bis der Kunde wieder schreibt.','callback_possible'=>true];
    $ok=false; try{ $ok=(bool)($S['wa'])($acc,$e,$text); }catch(Throwable $ex){ error_log('CH contacts_reply: '.$ex->getMessage()); }
    contacts_log($acc,$e,'reply','studio','whatsapp',$ok,$text,['by'=>$by]);
    if(!$ok) return ['error'=>'send_failed','message'=>'WhatsApp hat die Nachricht nicht angenommen – bitte später erneut versuchen oder einen Rückruf planen.','callback_possible'=>true];
    contacts_wa_note($acc,$e,$text);
    return ['ok'=>true,'channel'=>'whatsapp','message'=>'Nachricht per WhatsApp gesendet.'];
}
/** Rückruf planen (callback-lib cb_create, Quelle Studio, Team ruft an) und – wenn gewünscht – den Kunden auf seinem Kanal informieren. */
function contacts_plan_callback(int $acc, string $phone, array $d): array {
    if(!function_exists('cb_create') && is_file(__DIR__.'/callback-lib.php')) require_once __DIR__.'/callback-lib.php';
    if(!function_exists('cb_create')) return ['error'=>'callbacks_unavailable','message'=>'Das Rückruf-Modul ist nicht verfügbar.'];
    $e=contacts_e164($phone); if($e==='') return ['error'=>'phone_invalid','message'=>'Ungültige Telefonnummer.'];
    $row=contacts_get_row($acc,$e); $name=trim((string)($d['name'] ?? '')) ?: trim((string)($row['name'] ?? ''));
    if(mb_strlen($name)<2){ foreach(contacts_collect($acc,$e,['light'=>true,'limit'=>20]) as $i) if($i['name']!==''){ $name=$i['name']; break; } }
    if(mb_strlen($name)<2) $name='Kunde '.substr($e,-4);
    $r=cb_create($acc,['name'=>$name,'phone'=>$e,'topic'=>trim((string)($d['topic'] ?? '')) ?: 'Rückruf aus dem Kundenverlauf','preferred_time'=>(string)($d['preferred_time'] ?? ''),'callback_by'=>'team','_strict'=>1],'studio');
    if(isset($r['error'])) return $r+['message'=>$r['hint'] ?? $r['error']];
    $out=['ok'=>true,'code'=>$r['code'] ?? null,'message'=>'Rückruf eingetragen: '.($r['message'] ?? ''),'hint'=>$r['hint'] ?? null];
    if(!array_key_exists('notify',$d) || !empty($d['notify'])){
        $co=contacts_company($acc); $em=''; foreach(contacts_collect($acc,$e,['light'=>true,'limit'=>20]) as $i) if(!empty($i['email'])){ $em=$i['email']; break; }
        $n=contacts_notify($acc,$e,($co!=='' ? $co.': ' : '').($r['message'] ?? 'Wir rufen Sie zurück.'),'callback_scheduled',['email'=>$em,'ref'=>(string)($r['code'] ?? ''),'by'=>'studio','subject'=>($co!=='' ? $co.' · ' : '').'Wir rufen Sie zurück']);
        $out['notified']=$n['ok'] ? $n['channel'] : null;
    }
    return $out;
}
/** Notiz/Name im Studio speichern. */
function contacts_note(int $acc, string $phone, ?string $notes, ?string $name=null): array {
    $e=contacts_e164($phone); if($acc<=0 || $e==='') return ['error'=>'phone_invalid','message'=>'Ungültige Telefonnummer.'];
    contacts_ensure($acc,$e); $set=[]; $v=[];
    if($notes!==null){ $set[]='notes=?'; $v[]=contacts_clean($notes,4000); }
    if($name!==null){ $set[]='name=?'; $v[]=mb_substr(contacts_clean($name,120),0,120); }
    if($set){ $v[]=$acc; $v[]=$e; th_db()->prepare("UPDATE contacts SET ".implode(',',$set).", updated_at=NOW() WHERE account_id=? AND phone_e164=?")->execute($v); }
    $row=contacts_get_row($acc,$e); return ['ok'=>true,'notes'=>(string)($row['notes'] ?? ''),'name'=>(string)($row['name'] ?? '')];
}

/* ======================================================================= Studio-REST */
function contacts_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if($route!=='contacts' && strpos($route,'contacts/')!==0) return null;
    $routes=['GET contacts/list?q=&limit=','GET contacts/get?phone=','POST contacts/reply {phone,text} | {phone,action:"callback",topic?,preferred_time?,notify?}','POST contacts/note {phone,notes?,name?}'];
    $sc=function($v): string { return is_scalar($v) ? (string)$v : ''; };
    $in=$body+$_GET;
    try{
        contacts_bootstrap();
        switch($route){
            case 'contacts/list':
                if($method!=='GET') break;
                $list=contacts_list($aid,$sc($in['q'] ?? ''),(int)($in['limit'] ?? 100)); $S=contacts_senders();
                return [200,['contacts'=>$list,'stats'=>['total'=>count($list),'window_open'=>count(array_filter($list,fn($c)=>$c['window_open'])),'open_items'=>array_sum(array_column($list,'open_count'))],
                    'whatsapp_ready'=>(bool)($S['wa_ready'])($aid),'sms_ready'=>(bool)($S['sms_ready'])($aid)]];
            case 'contacts/get':
                if($method!=='GET') break;
                $d=contacts_detail($aid,$sc($in['phone'] ?? ''));
                return $d ? [200,$d] : [404,['error'=>'not_found','message'=>'Zu dieser Nummer gibt es keinen Verlauf.']];
            case 'contacts/reply':
                if($method!=='POST') break;
                if(!th_rate('ctr:'.$aid,120,3600)) return [429,['error'=>'rate_limited','message'=>'Zu viele Nachrichten in kurzer Zeit – bitte kurz warten.']];
                $phone=$sc($in['phone'] ?? '');
                if($sc($in['action'] ?? '')==='callback'){
                    $r=contacts_plan_callback($aid,$phone,['name'=>$sc($in['name'] ?? ''),'topic'=>$sc($in['topic'] ?? ''),'preferred_time'=>$sc($in['preferred_time'] ?? ''),'notify'=>!array_key_exists('notify',$in) || in_array($in['notify'],[true,1,'1','true','on'],true)]);
                    return [isset($r['error']) ? 422 : 200,$r];
                }
                $r=contacts_reply($aid,$phone,$sc($in['text'] ?? ''));
                return [isset($r['error']) ? (['window_closed'=>409,'whatsapp_not_connected'=>409,'send_failed'=>502][$r['error']] ?? 422) : 200,$r];
            case 'contacts/note':
                if($method!=='POST') break;
                $r=contacts_note($aid,$sc($in['phone'] ?? ''),array_key_exists('notes',$in) ? $sc($in['notes']) : null,array_key_exists('name',$in) ? $sc($in['name']) : null);
                return [isset($r['error']) ? 422 : 200,$r];
            default: return [404,['error'=>'not_found','routes'=>$routes]];
        }
    }catch(Throwable $e){ error_log('CH contacts_api '.$route.': '.$e->getMessage()); return [500,['error'=>'server_error','message'=>'Interner Fehler – bitte erneut versuchen.']]; }
    return [405,['error'=>'method_not_allowed','routes'=>$routes]];
}
