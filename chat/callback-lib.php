<?php
/**
 * ChatHelp — Rückruf & Lead-Sofortrückruf (Modul „callback“, Präfix cb_)
 * ======================================================================
 * • Jeder Agent nimmt Rückrufwünsche auf (Werkzeug request_callback): Name, Nummer, Anliegen, Wunschzeit → Rückrufliste im Studio + E-Mail an den Betrieb
 * • KI-Rückruf: der Telefon-Agent ruft selbst zurück (Twilio, ausgehend) – zur Wunschzeit bzw. bei Web-Leads in ca. 60 s (Lead-Sofortrückruf);
 *   er qualifiziert den Interessenten und bucht mit den Termin-Werkzeugen direkt eine Besichtigung bzw. einen Termin
 * • Recht (UWG § 7 / § 7a): nur Personen mit eigenem Rückrufwunsch bzw. Einwilligung (Formular: Häkchen, Zeitpunkt, IP-Prüfsumme und Wortlaut
 *   werden gespeichert), nur innerhalb der Anrufzeiten, höchstens N Versuche, jedes Gespräch mit Transkript (phone_calls)
 * • Planer cb_scheduler_lazy() (höchstens 1× je Minute, DB-Sperre) + Kontext für den Telefon-Webhook (cb_call_context / cb_on_call_status)
 * • Öffentlich: lead-api.php (Formulare, Portale, Zapier) und callback.php (Seite „Rückruf anfordern“); Studio-REST /v1/callbacks…
 * • Konto 0 = Live-Demo „ChatHelp Demo · Lindqvist Immobilien Köln“ (keine Anrufe/Mails, Demo-Einträge nach 2 Tagen gelöscht)
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';

const CB_BASE_URL = 'https://chat-help.com/chat/';
const CB_STATUSES = ['open','scheduled','calling','done','failed','cancelled'];
const CB_OPEN = ['open','scheduled','calling','failed'];
const CB_STATUS_LABELS = ['open'=>'Offen','scheduled'=>'Geplant','calling'=>'Anruf läuft','done'=>'Erledigt','failed'=>'Nicht erreicht','cancelled'=>'Storniert'];
const CB_SOURCES = ['chat'=>'Website-Chat','phone'=>'Telefon','whatsapp'=>'WhatsApp','widget'=>'Website-Widget','form'=>'Rückruf-Formular','studio'=>'Studio','api'=>'Lead-API','demo'=>'Demo'];
const CB_MODE_LABELS = ['human'=>'Team','ai'=>'KI-Assistent'];
const CB_WD = ['So','Mo','Di','Mi','Do','Fr','Sa'];
const CB_WDL = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
const CB_DEFAULT_WINDOW = ['0'=>[],'1'=>[['09:00','19:00']],'2'=>[['09:00','19:00']],'3'=>[['09:00','19:00']],'4'=>[['09:00','19:00']],'5'=>[['09:00','19:00']],'6'=>[['10:00','14:00']]];
const CB_DEMO = ['name'=>'ChatHelp Demo · Lindqvist Immobilien Köln','phone'=>'+492214000000','tz'=>'Europe/Berlin','ai_callback'=>1,'lead_instant'=>1,'max_attempts'=>3,'retry_min'=>30,'intro'=>'',
  'call_window'=>['0'=>[],'1'=>[['09:00','18:00']],'2'=>[['09:00','18:00']],'3'=>[['09:00','18:00']],'4'=>[['09:00','18:00']],'5'=>[['09:00','18:00']],'6'=>[]]];
/** Feldnamen, die Formulare/Portale für Standardangaben benutzen (alles andere landet – kurz und höchstens 10 Felder – in details). */
const CB_FIELD_ALIASES = [
  'name'=>['name','full_name','fullname','kontakt','contact','contact_name'],
  'phone'=>['phone','telefon','tel','telephone','phone_number','telefonnummer','mobile','mobil','handy','rufnummer'],
  'email'=>['email','e_mail','e-mail','mail'],
  'topic'=>['message','topic','nachricht','anliegen','text','comment','kommentar','betreff','subject','anfrage'],
  'preferred_time'=>['preferred_time','wunschzeit','rueckrufzeit','rückrufzeit','callback_time','zeit','time'],
  'consent'=>['consent','einwilligung','dsgvo','privacy','agree','accept','zustimmung'],
  'consent_text'=>['consent_text','einwilligung_text'],
  'source'=>['source','quelle','portal','utm_source'],
  'urgency'=>['urgency','dringend','prioritaet','priority']];
const CB_FIELD_IGNORE = ['k','website','form_ts','via','details','first_name','firstname','vorname','last_name','lastname','nachname','g-recaptcha-response','h-captcha-response','cf-turnstile-response','submit','action'];

/* ======================================================================= Tabellen */
function cb_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS callback_settings (
        account_id INT NOT NULL PRIMARY KEY, cb_on TINYINT(1) DEFAULT 1, ai_callback TINYINT(1) DEFAULT 0, plan VARCHAR(40) DEFAULT '', from_number VARCHAR(32) DEFAULT '',
        call_window TEXT NULL, tz VARCHAR(40) DEFAULT 'Europe/Berlin', max_attempts INT DEFAULT 3, retry_min INT DEFAULT 30, lead_instant TINYINT(1) DEFAULT 1,
        notify_email VARCHAR(190) DEFAULT '', intro VARCHAR(300) DEFAULT '', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS callbacks (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, code CHAR(8) NOT NULL, plan VARCHAR(40) DEFAULT '', name VARCHAR(120) DEFAULT '', phone VARCHAR(20) DEFAULT '',
        email VARCHAR(190) DEFAULT '', topic VARCHAR(500) DEFAULT '', details TEXT NULL, preferred_at DATETIME NULL, urgency VARCHAR(10) DEFAULT 'normal', source VARCHAR(12) DEFAULT 'chat',
        consent TINYINT(1) DEFAULT 0, consent_at DATETIME NULL, consent_ip CHAR(32) DEFAULT '', consent_text VARCHAR(500) DEFAULT '',
        mode VARCHAR(8) DEFAULT 'human', status VARCHAR(12) DEFAULT 'open', attempts INT DEFAULT 0, last_attempt_at DATETIME NULL, call_sid VARCHAR(64) DEFAULT '',
        call_log TEXT NULL, result VARCHAR(500) DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
        UNIQUE KEY uq_code (code), INDEX(account_id, status), INDEX(status, preferred_at), INDEX(account_id, phone)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS callback_jobs (name VARCHAR(40) NOT NULL PRIMARY KEY, last_run DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* Spalten nachrüsten (frühere Tabellenstände) */
    $have=$p->query("SHOW COLUMNS FROM callbacks")->fetchAll(PDO::FETCH_COLUMN);
    foreach(['consent_at'=>'DATETIME NULL','consent_ip'=>"CHAR(32) DEFAULT ''",'consent_text'=>"VARCHAR(500) DEFAULT ''",'call_log'=>'TEXT NULL','plan'=>"VARCHAR(40) DEFAULT ''"] as $c=>$def)
        if(!in_array($c,$have,true)) $p->exec("ALTER TABLE callbacks ADD COLUMN $c $def");
    if(random_int(1,40)===1) $p->exec("DELETE FROM callbacks WHERE account_id=0 AND created_at < (NOW() - INTERVAL 2 DAY)");
}

/* ======================================================================= kleine Helfer */
/** Nicht-skalare Eingaben neutralisieren (Werkzeug-/Formulareingaben), $keep = Schlüssel, die Arrays bleiben dürfen. */
function cb_scalars(array $a, array $keep=[]): array { foreach($a as $k=>$v){ if(in_array($k,$keep,true)) continue; if(!is_scalar($v) && $v!==null) $a[$k]=''; } return $a; }
function cb_truthy($v): bool { if(is_bool($v)) return $v; if(is_int($v) || is_float($v)) return $v>0; return in_array(mb_strtolower(trim((string)$v)),['1','true','on','yes','y','ja','j','x','checked','ok','einverstanden'],true); }
/** Eine Zeile Text: Tags/Steuerzeichen raus, Leerraum zusammenfassen, kürzen. */
function cb_line($v, int $max): string {
    $t=is_scalar($v) ? (string)$v : ''; $t=strip_tags($t); $t=preg_replace('/[\x00-\x1F\x7F]+/u',' ',$t) ?? ''; $t=trim(preg_replace('/\s+/u',' ',$t) ?? '');
    return mb_substr($t,0,$max);
}
function cb_hm($t, bool $end=false): ?string {
    if(!is_string($t) || !preg_match('/^(\d{1,2})[:.](\d{2})$/',trim($t),$m)) return null; $h=(int)$m[1]; $i=(int)$m[2];
    return ($i<60 && ($h<24 || ($end && $h===24 && $i===0))) ? sprintf('%02d:%02d',$h,$i) : null;
}
function cb_code(): string { $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $c=''; for($i=0;$i<6;$i++) $c.=$a[random_int(0,strlen($a)-1)]; return $c; }
function cb_e(string $s): string { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
function cb_owner_email(int $acc): string { if($acc<=0) return ''; $s=th_db()->prepare("SELECT email FROM token_accounts WHERE id=?"); $s->execute([$acc]); return (string)($s->fetchColumn() ?: ''); }

/* ======================================================================= Telefonnummern */
/** Rufnummer → E.164 (+491701234567) über th_e164(); „(0)“ nach der Landesvorwahl wird entfernt. null = unbrauchbar. */
function cb_phone(string $raw): ?string {
    $raw=preg_replace('/\(\s*0\s*\)/','',$raw) ?? $raw;
    $e=th_e164($raw); return $e==='' ? null : $e;
}
/** Länge der Landesvorwahl (grob nach ITU-Zonen: 1/7 einstellig, sonst zwei- bzw. dreistellig). */
function cb_cc_len(string $d): int {
    if($d==='' ) return 0; if($d[0]==='1' || $d[0]==='7') return 1;
    return in_array(substr($d,0,2),['21','22','23','24','25','26','29','35','37','38','42','50','59','67','68','69','85','88','96','97','99'],true) ? 3 : 2;
}
/** „+49 170 1234567“ */
function cb_phone_display(string $e164): string { $d=ltrim($e164,'+'); if($d==='') return ''; $c=cb_cc_len($d); return '+'.substr($d,0,$c).' '.substr($d,$c,3).' '.substr($d,$c+3); }
/** „+49 170 •••• 567“ (öffentliche Antworten) */
function cb_mask_phone(string $e164): string { $d=ltrim($e164,'+'); if(strlen($d)<8) return '••••'; $c=cb_cc_len($d); return '+'.substr($d,0,$c).' '.substr($d,$c,3).' •••• '.substr($d,-3); }
/** Zum Vorlesen: DE/AT/CH national („0 2 2 1, 4 0 0, …“), sonst international – Ziffern einzeln, in Dreiergruppen. */
function cb_speak_number(string $e164): string {
    $d=ltrim($e164,'+'); if($d==='') return '';
    foreach(['49','43','41'] as $cc) if(str_starts_with($d,$cc)){ $d='0'.substr($d,2); break; }
    if(!str_starts_with($d,'0')) $d='+'.$d;
    return implode(', ',array_map(fn($g)=>implode(' ',preg_split('//u',$g,-1,PREG_SPLIT_NO_EMPTY)),str_split($d,3)));
}

/* ======================================================================= Einstellungen */
/** Anrufzeiten normalisieren: {"1":[["09:00","19:00"]],…} oder [{"from","to"}] je Wochentag (0 = Sonntag); höchstens 4 Zeiträume je Tag, überlappende zusammengefasst. */
function cb_window_norm($raw): array {
    if(is_string($raw)) $raw=json_decode($raw,true);
    $out=[];
    for($w=0;$w<=6;$w++){
        $src=is_array($raw) ? ($raw[(string)$w] ?? ($raw[$w] ?? [])) : []; $rows=[];
        foreach((array)$src as $iv){
            if(!is_array($iv)) continue;
            $a=cb_hm($iv['from'] ?? ($iv[0] ?? null)); $b=cb_hm($iv['to'] ?? ($iv[1] ?? null),true);
            if($a===null || $b===null || $b<=$a) continue; $rows[]=[$a,$b];
        }
        usort($rows,fn($x,$y)=>strcmp($x[0],$y[0])); $m=[];
        foreach($rows as $r){ $k=count($m)-1; if($k>=0 && $r[0]<=$m[$k][1]){ if($r[1]>$m[$k][1]) $m[$k][1]=$r[1]; } else $m[]=$r; }
        $out[(string)$w]=array_slice($m,0,4);
    }
    return $out;
}
function cb_settings_defaults(int $acc): array {
    return ['account_id'=>$acc,'cb_on'=>1,'ai_callback'=>0,'plan'=>'','from_number'=>'','call_window'=>CB_DEFAULT_WINDOW,'tz'=>'Europe/Berlin','max_attempts'=>3,'retry_min'=>30,
        'lead_instant'=>1,'notify_email'=>'','intro'=>'','updated_at'=>null];
}
function cb_settings(int $acc): array {
    if(isset($GLOBALS['__cb_set'][$acc])) return $GLOBALS['__cb_set'][$acc];
    cb_bootstrap(); $r=cb_settings_defaults($acc);
    if($acc===0){ foreach(['ai_callback','lead_instant','max_attempts','retry_min','tz','intro','call_window'] as $k) $r[$k]=CB_DEMO[$k]; }
    else { $st=th_db()->prepare("SELECT * FROM callback_settings WHERE account_id=?"); $st->execute([$acc]); if($row=$st->fetch(PDO::FETCH_ASSOC)) $r=array_merge($r,$row); }
    foreach(['account_id','cb_on','ai_callback','max_attempts','retry_min','lead_instant'] as $k) $r[$k]=(int)$r[$k];
    foreach(['plan','from_number','tz','notify_email','intro'] as $k) $r[$k]=(string)($r[$k] ?? '');
    $r['call_window']=cb_window_norm($r['call_window'] ?? null);
    if(!in_array($r['tz'],timezone_identifiers_list(),true)) $r['tz']='Europe/Berlin';
    $r['max_attempts']=max(1,min(5,$r['max_attempts'] ?: 3)); $r['retry_min']=max(5,min(1440,$r['retry_min'] ?: 30));
    return $GLOBALS['__cb_set'][$acc]=$r;
}
/** Agent-Pläne des Kontos (agent_configs) – Auswahl „welcher Agent ruft an“. */
function cb_account_plans(int $acc): array { $s=th_db()->prepare("SELECT plan FROM agent_configs WHERE account_id=?"); $s->execute([$acc]); return array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN)); }
/** Speichern (nur übergebene Felder ändern sich). Konto 0 (Demo) ist schreibgeschützt. */
function cb_settings_save(int $acc, array $d): array {
    cb_bootstrap(); if($acc<=0) return cb_settings($acc); $c=cb_settings($acc); $d=cb_scalars($d,['call_window']);
    $flag=fn(string $k)=>array_key_exists($k,$d) ? (int)cb_truthy($d[$k]) : (int)$c[$k];
    $plan=$c['plan'];
    if(array_key_exists('plan',$d)){ $pl=preg_replace('/[^a-z0-9-]/','',strtolower((string)$d['plan'])); $plan=($pl==='' || in_array($pl,cb_account_plans($acc),true)) ? $pl : $c['plan']; }
    $from=$c['from_number'];
    if(array_key_exists('from_number',$d)){ $raw=trim((string)$d['from_number']); $from=$raw==='' ? '' : (cb_phone($raw) ?? $c['from_number']); }
    $win=isset($d['call_window']) && is_array($d['call_window']) ? cb_window_norm($d['call_window']) : $c['call_window'];
    $tz=in_array($d['tz'] ?? '',timezone_identifiers_list(),true) ? (string)$d['tz'] : $c['tz'];
    $mail=array_key_exists('notify_email',$d) ? trim((string)$d['notify_email']) : $c['notify_email'];
    $intro=array_key_exists('intro',$d) ? cb_line($d['intro'],300) : $c['intro'];
    $v=['cb_on'=>$flag('cb_on'),'ai_callback'=>$flag('ai_callback'),'plan'=>$plan,'from_number'=>$from,'call_window'=>json_encode($win),'tz'=>$tz,
        'max_attempts'=>max(1,min(5,is_numeric($d['max_attempts'] ?? null) ? (int)$d['max_attempts'] : $c['max_attempts'])),'retry_min'=>max(5,min(1440,is_numeric($d['retry_min'] ?? null) ? (int)$d['retry_min'] : $c['retry_min'])),
        'lead_instant'=>$flag('lead_instant'),'notify_email'=>filter_var($mail,FILTER_VALIDATE_EMAIL) ? mb_substr($mail,0,190) : '','intro'=>$intro];
    th_db()->prepare("INSERT INTO callback_settings(account_id,cb_on,ai_callback,plan,from_number,call_window,tz,max_attempts,retry_min,lead_instant,notify_email,intro)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE cb_on=VALUES(cb_on),ai_callback=VALUES(ai_callback),plan=VALUES(plan),from_number=VALUES(from_number),
        call_window=VALUES(call_window),tz=VALUES(tz),max_attempts=VALUES(max_attempts),retry_min=VALUES(retry_min),lead_instant=VALUES(lead_instant),notify_email=VALUES(notify_email),intro=VALUES(intro),updated_at=NOW()")
      ->execute([$acc,$v['cb_on'],$v['ai_callback'],$v['plan'],$v['from_number'],$v['call_window'],$v['tz'],$v['max_attempts'],$v['retry_min'],$v['lead_instant'],$v['notify_email'],$v['intro']]);
    unset($GLOBALS['__cb_set'][$acc]);
    return cb_settings($acc);
}
/** Für Studio/Clients: es gibt keine Geheimnisse in den Rückruf-Einstellungen – ergänzt nur lesbare Angaben. */
function cb_settings_public(array $s): array {
    unset($s['account_id']);
    return $s+['window_text'=>cb_window_text($s),'from_number_display'=>$s['from_number']!=='' ? cb_phone_display($s['from_number']) : ''];
}
function cb_enabled(int $acc): bool { return $acc===0 || cb_settings($acc)['cb_on']===1; }

/* ======================================================================= Zeit & Anrufzeiten */
function cb_tz(array $s): DateTimeZone { try{ return new DateTimeZone($s['tz'] ?: 'Europe/Berlin'); }catch(Throwable $e){ return new DateTimeZone('Europe/Berlin'); } }
function cb_now(array $s): DateTimeImmutable { return new DateTimeImmutable('now',cb_tz($s)); }
/** Zeiträume eines Kalendertags als Zeitpunkte [[von,bis],…] (Ortszeit des Betriebs). */
function cb_day_ranges(array $s, DateTimeImmutable $day): array {
    $d=$day->setTimezone(cb_tz($s))->setTime(0,0); $o=[];
    foreach((array)($s['call_window'][(string)(int)$d->format('w')] ?? []) as $iv){
        $a=$d->setTime((int)substr($iv[0],0,2),(int)substr($iv[0],3,2));
        $b=$iv[1]==='24:00' ? $d->modify('+1 day') : $d->setTime((int)substr($iv[1],0,2),(int)substr($iv[1],3,2));
        if($b>$a) $o[]=[$a,$b];
    }
    return $o;
}
function cb_has_window(array $s): bool { foreach($s['call_window'] as $r) if($r) return true; return false; }
function cb_in_window(array $s, DateTimeImmutable $t): bool { foreach(cb_day_ranges($s,$t) as [$a,$b]) if($a<=$t && $t<$b) return true; return false; }
/** Frühester Zeitpunkt ≥ $t innerhalb der Anrufzeiten (innerhalb von $days Tagen) oder null. */
function cb_next_window(array $s, DateTimeImmutable $t, int $days=14): ?DateTimeImmutable {
    $t=$t->setTimezone(cb_tz($s));
    for($i=0;$i<=$days;$i++){ foreach(cb_day_ranges($s,$t->setTime(0,0)->modify('+'.$i.' day')) as [$a,$b]){ if($b<=$t) continue; return $a>$t ? $a : $t; } }
    return null;
}
/** „heute“ / „morgen“ / „übermorgen“ / „am Mo, 20.10.“ */
function cb_day_word(DateTimeImmutable $t, DateTimeImmutable $now): string {
    $t=$t->setTimezone($now->getTimezone()); $d=(int)$now->setTime(0,0)->diff($t->setTime(0,0))->format('%r%a');
    if($d===0) return 'heute'; if($d===1) return 'morgen'; if($d===2) return 'übermorgen'; if($d===-1) return 'gestern';
    return 'am '.CB_WD[(int)$t->format('w')].', '.$t->format('d.m.');
}
/** „heute um 16:00 Uhr“ */
function cb_when_label(DateTimeImmutable $t, DateTimeImmutable $now): string { return cb_day_word($t,$now).' um '.$t->setTimezone($now->getTimezone())->format('H:i').' Uhr'; }
/** Kurzform für Listen: „heute 16:00“ / „Mo 20.10. 16:00“ */
function cb_short_label(DateTimeImmutable $t, DateTimeImmutable $now): string { $w=cb_day_word($t,$now); return (str_starts_with($w,'am ') ? str_replace(',','',substr($w,3)) : $w).' '.$t->setTimezone($now->getTimezone())->format('H:i'); }
/** „9“ / „18:30“ (für gesprochene/kompakte Zeiten) */
function cb_hm_short(string $hm): string { [$h,$i]=explode(':',$hm); return ((int)$h).($i!=='00' ? ':'.$i : ''); }
/** „Mo–Fr 9–19 Uhr, Sa 10–14 Uhr, So keine Rückrufe“ */
function cb_window_text(array $s): string {
    if(!cb_has_window($s)) return 'keine Anrufzeiten hinterlegt';
    $order=[1,2,3,4,5,6,0]; $groups=[];
    foreach($order as $w){
        $r=$s['call_window'][(string)$w] ?? []; $txt=$r ? implode(', ',array_map(fn($x)=>cb_hm_short($x[0]).'–'.cb_hm_short($x[1]),$r)).' Uhr' : 'keine Rückrufe';
        $k=count($groups)-1; if($k>=0 && $groups[$k]['t']===$txt) $groups[$k]['to']=$w; else $groups[]=['from'=>$w,'to'=>$w,'t'=>$txt];
    }
    return implode(', ',array_map(fn($g)=>CB_WD[$g['from']].($g['to']!==$g['from'] ? '–'.CB_WD[$g['to']] : '').' '.$g['t'],$groups));
}
/**
 * Wunschzeit verstehen: „so schnell wie möglich“, „heute 16 Uhr“, „16:30“, „morgen vormittag“ (10:00), „nachmittag“ (15:00), „abend“ (18:00),
 * „übermorgen früh“, „Freitag 14 Uhr“, „nächsten Dienstag“, „20.10. 14:30“, „2026-10-20 14:30“, „in 2 Stunden“, „zwischen 14 und 16 Uhr“, „halb 3 nachmittags“.
 * Nie in der Vergangenheit (→ so schnell wie möglich), immer in die Anrufzeiten geschoben (adjusted=true).
 * Rückgabe ['at'=>?DateTimeImmutable (Ortszeit, null = so schnell wie möglich),'asap'=>bool,'adjusted'=>bool,'past'=>bool] oder ['error'=>…,'hint'=>…].
 */
function cb_parse_time(array $s, string $raw, ?DateTimeImmutable $now=null): array {
    $tz=cb_tz($s); $now=($now ?: new DateTimeImmutable('now',$tz))->setTimezone($tz); $now=$now->setTime((int)$now->format('H'),(int)$now->format('i'));
    $t=mb_strtolower(trim(preg_replace('/\s+/u',' ',$raw) ?? '')); $t=trim(preg_replace('/(?<!\d)\.+$/u','',trim($t,' !,;?')) ?? $t);   // Schlusspunkt weg, aber „20.10.“ behalten
    $bad=['error'=>'bad_preferred_time','hint'=>'Wunschzeit nicht verstanden. Bitte konkret erfragen (z. B. „heute 16 Uhr“, „morgen vormittag“, „Freitag 14:30“ oder YYYY-MM-DD HH:MM) – oder leer lassen für „so schnell wie möglich“.'];
    $asap=['at'=>null,'asap'=>true,'adjusted'=>false,'past'=>false];
    if($t==='' || preg_match('/^(jetzt|gleich|jetzt gleich|jederzeit|egal|zeitnah|bald|wann immer|now|anytime|any time|heute|heute noch|noch heute|heute bitte|bitte heute)$/u',$t)) return $asap;
    $hasInfo=(bool)preg_match('/\d|morgen|übermorgen|uebermorgen|montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonntag|vormittag|nachmittag|mittag|abend|früh|frueh|halb /u',$t);
    if(!$hasInfo && preg_match('/(so schnell wie möglich|so bald wie möglich|schnellstmöglich|baldmöglichst|asap|sofort|umgehend|schnellstens|möglichst bald|möglichst schnell|as soon as possible|immediately)/u',$t)) return $asap;
    /* „bis 18 Uhr“ / „vor 12“ / „spätestens 17:30“ = Frist, keine Wunschzeit: herausnehmen (ohne weitere Angabe → so schnell wie möglich) */
    $t=trim(preg_replace('/\b(?:bis|vor|spätestens|spaetestens) (?:spätestens )?\d{1,2}(?:[:.]\d{2})?(?: ?uhr)?/u',' ',$t) ?? $t);
    if($t==='' || preg_match('/^(heute|bitte|heute bitte|noch heute)$/u',$t)) return $asap;
    $at=null; $date=null; $dayGiven=false; $h=null; $i=0; $part=null;
    if(preg_match('/\bin (\d{1,3}|einer|einem|zwei|drei|vier|fünf|einer halben|einer viertel) ?(minuten|minute|min|stunden|stunde|std|h)\b/u',$t,$m)){
        $n=['einer'=>1,'einem'=>1,'zwei'=>2,'drei'=>3,'vier'=>4,'fünf'=>5,'einer halben'=>0.5,'einer viertel'=>0.25][$m[1]] ?? (int)$m[1];
        $min=str_starts_with($m[2],'min') ? (int)round($n) : (int)round($n*60); if($min<1 || $min>60*24*30) return $bad;
        $at=$now->modify('+'.$min.' minutes');
    } else {
        if(preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})t?/u',$t,$m) && checkdate((int)$m[2],(int)$m[3],(int)$m[1])){ $date=(new DateTimeImmutable(sprintf('%04d-%02d-%02d',(int)$m[1],(int)$m[2],(int)$m[3]),$tz)); $dayGiven=true; $t=str_replace($m[0],' ',$t); }
        elseif(preg_match('/\b(\d{1,2})\.(\d{1,2})\.(\d{4}|\d{2})?/u',$t,$m)){
            $hasY=isset($m[3]) && $m[3]!==''; $y=$hasY ? ((int)$m[3]<100 ? 2000+(int)$m[3] : (int)$m[3]) : (int)$now->format('Y');
            if(checkdate((int)$m[2],(int)$m[1],$y)){
                $date=new DateTimeImmutable(sprintf('%04d-%02d-%02d',$y,(int)$m[2],(int)$m[1]),$tz);
                if(!$hasY && $date<$now->setTime(0,0)) $date=$date->modify('+1 year');   // „05.01.“ im Dezember = nächstes Jahr
                $dayGiven=true; $t=str_replace($m[0],' ',$t);
            }
        }
        if(!$date){
            $today=$now->setTime(0,0);
            if(preg_match('/übermorgen|uebermorgen/u',$t)){ $date=$today->modify('+2 day'); $dayGiven=true; }
            elseif(preg_match('/\bheute\b/u',$t)){ $date=$today; $dayGiven=true; if(preg_match('/\bmorgen\b/u',$t)) $part=$part ?? '09:00'; }
            elseif(preg_match('/\bmorgen\b/u',$t)){ $date=$today->modify('+1 day'); $dayGiven=true; }
            else foreach(CB_WDL as $w=>$name){ $n=mb_strtolower($name);
                if(preg_match('/\b'.$n.'s?\b/u',$t)){ $diff=($w-(int)$now->format('w')+7)%7; if($diff===0 && preg_match('/\bnächste|naechste|kommende/u',$t)) $diff=7; $date=$today->modify('+'.$diff.' day'); $dayGiven=true; break; } }
        }
        if(preg_match('/\bhalb (\d{1,2})\b/u',$t,$m)){ $h=(int)$m[1]-1; $i=30; if($h<0) $h=23; }
        elseif(preg_match('/\b(\d{1,2})[:.](\d{2})\b/u',$t,$m)){ $h=(int)$m[1]; $i=(int)$m[2]; }
        elseif(preg_match('/\b(?:um|ab|nach|gegen|zwischen|von|so um|etwa|ca\.?) (\d{1,2})\b/u',$t,$m)){ $h=(int)$m[1]; }
        elseif(preg_match('/\b(\d{1,2}) ?(?:-|–|bis) ?\d{1,2}\b/u',$t,$m)){ $h=(int)$m[1]; }
        elseif(preg_match('/\b(\d{1,2}) ?uhr\b/u',$t,$m)){ $h=(int)$m[1]; }
        elseif(preg_match('/^(\d{1,2})$/u',$t,$m)){ $h=(int)$m[1]; }
        $pod=null;
        if(str_contains($t,'nachmittag')) $pod='15:00'; elseif(str_contains($t,'abend')) $pod='18:00'; elseif(str_contains($t,'vormittag')) $pod='10:00';
        elseif(preg_match('/\bmittags?\b|mittagszeit/u',$t)) $pod='12:00'; elseif(preg_match('/\bfrüh\b|\bfrueh\b|\bmorgens\b/u',$t)) $pod='09:00';
        $part=$pod ?? $part;
        if($h!==null){
            if($h>23 || $i>59) return $bad;
            if(in_array($part,['15:00','18:00'],true) && $h<12) $h+=12;
            elseif($part===null && $h>=1 && $h<=7 && !preg_match('/\b0\d[:.]/u',$t)) $h+=12;   // „um 4“ = 16 Uhr (Rückrufe nachts sind ausgeschlossen)
        } elseif($part!==null){ $h=(int)substr($part,0,2); $i=(int)substr($part,3,2); }
        if(!$date && $h===null) return $bad;
        $day=$date ?? $now->setTime(0,0);
        if($h===null){ $r=cb_day_ranges($s,$day); $at=$r ? $r[0][0] : $day->setTime(10,0); }
        else $at=$day->setTime($h,$i);
        if(!$dayGiven && $at<$now->modify('-5 minutes')) $at=$at->modify('+1 day');   // „16 Uhr“ um 17 Uhr gesagt = morgen 16 Uhr
    }
    if($at>$now->modify('+60 days')) return ['error'=>'preferred_time_too_far','hint'=>'Rückrufe höchstens 60 Tage im Voraus – bitte eine nähere Zeit erfragen.'];
    $past=$at<$now; $adjusted=$past; if($past) $at=$now;
    $c=cb_next_window($s,$at,21); if($c && $c!=$at){ $at=$c; $adjusted=true; }
    if($at<=$now->modify('+1 minute')) return ['at'=>null,'asap'=>true,'adjusted'=>$adjusted,'past'=>$past];
    return ['at'=>$at,'asap'=>false,'adjusted'=>$adjusted,'past'=>$past];
}
/** Wählbare Rückrufzeiten für das Formular: je Tag (max. $days Tage mit Anrufzeiten) Zeiten im $step-Minuten-Raster, heute frühestens in 20 Minuten. */
function cb_slots(array $s, ?DateTimeImmutable $now=null, int $maxDays=10, int $step=30): array {
    $now=($now ?: cb_now($s))->setTimezone(cb_tz($s)); $min=$now->modify('+20 minutes'); $out=[];
    for($i=0;$i<21 && count($out)<$maxDays;$i++){
        $day=$now->setTime(0,0)->modify('+'.$i.' day'); $slots=[];
        foreach(cb_day_ranges($s,$day) as [$a,$b]){ for($t=$a;$t<$b;$t=$t->modify('+'.$step.' minutes')){ if($t>=$min) $slots[]=$t->format('H:i'); } }
        if(!$slots) continue;
        $w=cb_day_word($day,$now); $out[]=['date'=>$day->format('Y-m-d'),'label'=>in_array($w,['heute','morgen','übermorgen'],true) ? mb_strtoupper(mb_substr($w,0,1)).mb_substr($w,1).', '.$day->format('d.m.') : CB_WDL[(int)$day->format('w')].', '.$day->format('d.m.'),'today'=>$i===0,'slots'=>array_values(array_unique($slots))];
    }
    return $out;
}

/* ======================================================================= Betrieb, Agent, Twilio */
function cb_plan_ok(int $acc, string $plan): bool {
    if($acc===0) return true; if($plan==='') return false;
    if(function_exists('ch_plan_active_any')) return ch_plan_active_any($acc,$plan);
    $base=CH_PLAN_BASE[$plan] ?? $plan; $ids=[$plan,$base]; foreach(CH_PLAN_BASE as $b=>$a) if($a===$base) $ids[]=$b;
    foreach(array_unique($ids) as $id) if(th_plan_active($acc,$id)) return true; return false;
}
/** Öffentliche Kanäle (Formular, Lead-API) nur mit aktivem Paket: Plan des Schlüssels, Plan des anrufenden Agenten oder irgendein aktives Paket. */
function cb_public_ok(int $acc, string $cfgPlan): bool {
    if($acc===0) return true; if(cb_plan_ok($acc,$cfgPlan)) return true;
    $s=cb_settings($acc); if($s['plan']!=='' && cb_plan_ok($acc,$s['plan'])) return true;
    foreach(th_plans($acc) as $p) if(in_array($p['status'],['active','trialing','past_due'],true)) return true;
    return false;
}
/** Anzeigename eines Plans ohne Klammerzusatz („Immobilien-Agent (Makler & Verwaltung)“ → „Immobilien-Agent“). */
function cb_plan_name(string $plan): string { $p=function_exists('ch_plan') ? ch_plan($plan) : null; return $p ? trim(preg_replace('/\s*\(.*\)\s*$/u','',(string)$p['name']) ?? (string)$p['name']) : $plan; }
/** Firmenname für Begrüßung, Formular und Mails: Konfiguration des Schlüssels → anrufender Agent → irgendeine Konfiguration. */
function cb_company(int $acc, ?array $s=null, ?array $cfg=null): string {
    if($acc===0) return CB_DEMO['name'];
    if($cfg && trim((string)($cfg['company'] ?? ''))!=='') return trim((string)$cfg['company']);
    $s=$s ?: cb_settings($acc);
    $st=th_db()->prepare("SELECT company FROM agent_configs WHERE account_id=? AND company<>'' ORDER BY (plan=?) DESC, (phone_on=1) DESC, updated_at DESC LIMIT 1"); $st->execute([$acc,$s['plan']]);
    return trim((string)($st->fetchColumn() ?: ''));
}
/** Agent-Konfigurationen des Kontos für die Auswahl im Studio (ohne Schlüssel). */
function cb_agents(int $acc): array {
    if($acc<=0) return [];
    $st=th_db()->prepare("SELECT plan, company, phone_on, phone_number FROM agent_configs WHERE account_id=? ORDER BY plan"); $st->execute([$acc]);
    return array_map(fn($r)=>['plan'=>(string)$r['plan'],'name'=>cb_plan_name((string)$r['plan']).(trim((string)$r['company'])!=='' ? ' · '.trim((string)$r['company']) : ''),
        'has_phone_number'=>trim((string)$r['phone_number'])!=='','phone_on'=>(int)$r['phone_on']===1,'active'=>cb_plan_ok($acc,(string)$r['plan'])],$st->fetchAll(PDO::FETCH_ASSOC));
}
/** Konfiguration, über die der KI-Rückruf läuft: gewählter Agent (Einstellung plan) → Agent der Anfrage → automatisch (Telefon an, mit Nummer). */
function cb_caller_cfg(int $acc, array $s, string $rowPlan=''): ?array {
    if($acc<=0) return null; $p=th_db(); $one=$p->prepare("SELECT * FROM agent_configs WHERE account_id=? AND plan=?");
    if($s['plan']!==''){ $one->execute([$acc,$s['plan']]); $c=$one->fetch(PDO::FETCH_ASSOC); return $c ?: null; }
    if($rowPlan!==''){ $one->execute([$acc,$rowPlan]); $c=$one->fetch(PDO::FETCH_ASSOC); if($c && (int)$c['phone_on']===1 && !empty($c['widget_key'])) return $c; }
    $st=$p->prepare("SELECT * FROM agent_configs WHERE account_id=? AND phone_on=1 AND widget_key IS NOT NULL ORDER BY (phone_number<>'') DESC, (plan='immo-agent') DESC, (plan='agent-phone') DESC, id LIMIT 1"); $st->execute([$acc]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
/** Gehört die Nummer dem Konto (vom Betreiber zugewiesene Agent-Nummer)? */
function cb_is_account_number(int $acc, string $num): bool {
    $st=th_db()->prepare("SELECT phone_number FROM agent_configs WHERE account_id=? AND phone_number<>''"); $st->execute([$acc]);
    foreach($st->fetchAll(PDO::FETCH_COLUMN) as $n) if(th_same_phone((string)$n,$num)) return true; return false;
}
/**
 * Absendernummer (Caller-ID): Einstellung from_number → Telefonnummer des Agenten → Absender aus th_twilio().
 * Über den Plattform-Account gilt from_number nur, wenn es eine dem Konto zugewiesene Nummer ist (kein Anruf im Namen anderer Betriebe).
 */
function cb_caller_id(int $acc, array $s, ?array $cfg, ?array $tw): string {
    $c=[];
    if($s['from_number']!=='' && (!$tw || $tw['own'] || cb_is_account_number($acc,$s['from_number']))) $c[]=$s['from_number'];
    if($cfg && trim((string)($cfg['phone_number'] ?? ''))!=='') $c[]=(string)$cfg['phone_number'];
    if($tw && $tw['from']!=='') $c[]=(string)$tw['from'];
    foreach($c as $n){ $e=cb_phone($n); if($e!==null) return $e; }
    return '';
}
/** Anruf-Bereitschaft für das Studio: twilio_ready (Twilio + Absendernummer) und ready (zusätzlich Telefon-Agent aktiv und gebucht) mit Hinweisen. */
function cb_readiness(int $acc, ?array $s=null): array {
    $s=$s ?: cb_settings($acc);
    if($acc<=0) return ['twilio'=>false,'twilio_own'=>false,'caller_id'=>'','caller_id_display'=>'','agent'=>null,'agent_name'=>'','phone_on'=>false,'plan_active'=>true,'twilio_ready'=>false,'ready'=>false,'issues'=>['Demo: es wird nicht angerufen.']];
    $tw=th_twilio($acc); $cfg=cb_caller_cfg($acc,$s); $cid=cb_caller_id($acc,$s,$cfg,$tw); $issues=[];
    $planOk=$cfg ? cb_plan_ok($acc,(string)$cfg['plan']) : false; $phoneOn=$cfg && (int)$cfg['phone_on']===1;
    if(!$tw) $issues[]='Twilio ist nicht verbunden – unter „Telefon → Verbindungen“ das eigene Twilio-Konto hinterlegen oder den Telefon-Agenten über ChatHelp buchen.';
    if(!$cfg) $issues[]='Kein Agent mit eingeschaltetem Telefon – unter „Telefon“ einen Telefon-Agenten einrichten und hier auswählen.';
    else {
        if(!$phoneOn) $issues[]='Beim Agenten „'.cb_plan_name((string)$cfg['plan']).'“ ist das Telefon ausgeschaltet.';
        if(!$planOk) $issues[]='Der Agent „'.cb_plan_name((string)$cfg['plan']).'“ ist nicht gemietet bzw. gekauft.';
    }
    if($cid==='') $issues[]='Keine Absendernummer: dem Agenten ist noch keine Telefonnummer zugewiesen (oder eigene Twilio-Nummer als Absender eintragen).';
    elseif($s['from_number']!=='' && !th_same_phone($cid,$s['from_number'])) $issues[]='Die eigene Absendernummer gilt nur mit eigenem Twilio-Konto – angerufen wird mit '.cb_phone_display($cid).'.';
    return ['twilio'=>$tw!==null,'twilio_own'=>$tw ? (bool)$tw['own'] : false,'caller_id'=>$cid,'caller_id_display'=>$cid!=='' ? cb_phone_display($cid) : '','agent'=>$cfg ? (string)$cfg['plan'] : null,
        'agent_name'=>$cfg ? cb_plan_name((string)$cfg['plan']) : '','phone_on'=>$phoneOn,'plan_active'=>$planOk,'twilio_ready'=>$tw!==null && $cid!=='',
        'ready'=>$tw!==null && $cid!=='' && $phoneOn && $planOk && !empty($cfg['widget_key']),'issues'=>$issues];
}
/** Öffentlicher Schlüssel für Formular/Lead-API (widget_key eines Agenten des Kontos; legt bei Bedarf eine Konfiguration an). */
function cb_form_key(int $acc): string {
    if($acc===0) return 'demo'; $s=cb_settings($acc);
    $st=th_db()->prepare("SELECT widget_key FROM agent_configs WHERE account_id=? AND widget_key IS NOT NULL ORDER BY (plan=?) DESC, (plan='immo-agent') DESC, (phone_on=1) DESC, id LIMIT 1");
    $st->execute([$acc,$s['plan']]); $wk=(string)($st->fetchColumn() ?: '');
    if($wk===''){
        /* Noch gar kein Agent gespeichert → eine (inaktive) Konfiguration anlegen, damit Formular und Lead-API einen Schlüssel haben. Bestehende Konfigurationen nie überschreiben. */
        $n=th_db()->prepare("SELECT COUNT(*) FROM agent_configs WHERE account_id=?"); $n->execute([$acc]);
        if((int)$n->fetchColumn()===0){ th_agent_cfg_save($acc,'immo-agent',[],false); $st->execute([$acc,$s['plan']]); $wk=(string)($st->fetchColumn() ?: ''); }
    }
    return $wk;
}
function cb_links(int $acc): array {
    $k=cb_form_key($acc); if($k==='') return ['key'=>'','form'=>null,'api'=>null];
    return ['key'=>$k,'form'=>CB_BASE_URL.'callback.php?k='.rawurlencode($k),'api'=>CB_BASE_URL.'lead-api.php?k='.rawurlencode($k)];
}
/** Öffentlicher Schlüssel → Konto (k=demo → Konto 0). */
function cb_key_account(string $k): ?array {
    if($k==='demo') return ['account_id'=>0,'plan'=>'immo-agent','company'=>CB_DEMO['name'],'widget_key'=>'demo'];
    if(!preg_match('/^wk_[a-f0-9]{36}$/',$k)) return null;
    $st=th_db()->prepare("SELECT c.account_id, c.plan, c.company, c.widget_key FROM agent_configs c JOIN token_accounts a ON a.id=c.account_id WHERE c.widget_key=?"); $st->execute([$k]);
    $r=$st->fetch(PDO::FETCH_ASSOC); if(!$r || (int)$r['account_id']<=0) return null;
    return ['account_id'=>(int)$r['account_id'],'plan'=>(string)$r['plan'],'company'=>(string)$r['company'],'widget_key'=>(string)$r['widget_key']];
}
/** Einwilligungstext (wie im Formular angezeigt). */
function cb_consent_text(string $company, bool $ai): string {
    return 'Ich bin einverstanden, dass '.($company!=='' ? $company : 'das Unternehmen').' mich zu meiner Anfrage telefonisch zurückruft'.($ai ? ' – auch durch einen digitalen KI-Telefonassistenten' : '').'. Diese Einwilligung kann ich jederzeit widerrufen.';
}

/* ======================================================================= Rückrufe */
function cb_get(int $acc, int $id): ?array { cb_bootstrap(); $s=th_db()->prepare("SELECT * FROM callbacks WHERE id=? AND account_id=?"); $s->execute([$id,$acc]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function cb_by_code(int $acc, string $code): ?array {
    cb_bootstrap(); $code=strtoupper(preg_replace('/[^A-Za-z0-9]/','',$code) ?? ''); if($code==='' || strlen($code)>8) return null;
    $s=th_db()->prepare("SELECT * FROM callbacks WHERE code=? AND account_id=?"); $s->execute([$code,$acc]); return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
/** Freie Zusatzangaben: höchstens 10 kurze Textfelder (Schlüssel ≤ 40, Wert ≤ 300 Zeichen). */
function cb_details($raw): array {
    if(is_string($raw)) $raw=json_decode($raw,true); if(!is_array($raw)) return []; $o=[];
    foreach($raw as $k=>$v){
        if(count($o)>=10) break; if(!is_scalar($v) || is_bool($v)) continue;
        $key=cb_line(str_replace('_',' ',(string)$k),40); $val=cb_line($v,300); if($key==='' || $val==='' || is_int($k)) continue;
        $o[mb_strtoupper(mb_substr($key,0,1)).mb_substr($key,1)]=$val;
    }
    return $o;
}
function cb_log_append(int $acc, int $id, array $entry): void {
    $st=th_db()->prepare("SELECT call_log FROM callbacks WHERE id=? AND account_id=?"); $st->execute([$id,$acc]); $log=json_decode((string)$st->fetchColumn(),true); if(!is_array($log)) $log=[];
    $entry['at']=gmdate('Y-m-d\TH:i:s\Z'); $log[]=$entry; $log=array_slice($log,-20);
    th_db()->prepare("UPDATE callbacks SET call_log=? WHERE id=? AND account_id=?")->execute([json_encode($log,JSON_UNESCAPED_UNICODE),$id,$acc]);
}
/** Frühester geplanter Anrufzeitpunkt (Wunschzeit bzw. jetzt, in die Anrufzeiten geschoben). */
function cb_due_at(array $r, array $s): ?DateTimeImmutable {
    $now=cb_now($s); $pa=!empty($r['preferred_at']) ? new DateTimeImmutable((string)$r['preferred_at'],cb_tz($s)) : null;
    return cb_next_window($s,$pa && $pa>$now ? $pa : $now) ?? ($pa ?: $now);
}
/** Was passiert jetzt? „Unser Team ruft Sie heute um 16:00 Uhr zurück.“ / „Unser digitaler Assistent ruft Sie in etwa einer Minute zurück.“ */
function cb_promise(array $r, array $s): string {
    $who=$r['mode']==='ai' ? 'Unser digitaler Assistent' : 'Unser Team'; $w=cb_when_phrase($r,$s);
    return $who.' ruft Sie '.$w['when'].' zurück'.($w['tail']!=='' ? ' – '.$w['tail'] : '').'.';
}
/** Zeitangabe zur Zusage: when = „heute um 16:00 Uhr“ / „in etwa einer Minute“ / „so schnell wie möglich“; tail = Zusatz außerhalb der Rückrufzeiten. */
function cb_when_phrase(array $r, array $s): array {
    $now=cb_now($s); $pa=!empty($r['preferred_at']) ? new DateTimeImmutable((string)$r['preferred_at'],cb_tz($s)) : null;
    if($pa && $pa>$now) return ['when'=>cb_when_label($pa,$now),'tail'=>''];
    if(!cb_has_window($s) || cb_in_window($s,$now)){
        if($r['mode']==='ai') return ['when'=>(in_array($r['source'],['form','api','demo'],true) && $s['lead_instant']===1) ? 'in etwa einer Minute' : 'in wenigen Minuten','tail'=>''];
        return ['when'=>'so schnell wie möglich','tail'=>''];
    }
    $nx=cb_next_window($s,$now);
    return ['when'=>'so schnell wie möglich','tail'=>'frühestens '.($nx ? cb_when_label($nx,$now) : 'zu den nächsten Rückrufzeiten').' (Rückrufzeiten: '.cb_window_text($s).')'];
}
/** Öffentliche Form (Formular, Lead-API, Werkzeug): Nummer maskiert, keine internen Felder. */
function cb_public(array $r, ?array $s=null): array {
    $s=$s ?: cb_settings((int)$r['account_id']); $now=cb_now($s); $pa=!empty($r['preferred_at']) ? new DateTimeImmutable((string)$r['preferred_at'],cb_tz($s)) : null;
    return ['code'=>(string)$r['code'],'name'=>(string)$r['name'],'phone'=>cb_mask_phone((string)$r['phone']),'preferred'=>$pa ? cb_when_label($pa,$now) : 'so schnell wie möglich',
        'preferred_at'=>$pa ? $pa->format('Y-m-d H:i') : null,'mode'=>(string)$r['mode'],'mode_label'=>$r['mode']==='ai' ? 'Digitaler Assistent' : 'Team',
        'status'=>(string)$r['status'],'status_label'=>CB_STATUS_LABELS[$r['status']] ?? (string)$r['status'],'urgency'=>(string)$r['urgency']];
}
/** Vollständige Form für das Studio (Konto 0: Nummer maskiert). */
function cb_item(array $r, ?array $s=null): array {
    $acc=(int)$r['account_id']; $s=$s ?: cb_settings($acc); $tz=cb_tz($s); $now=cb_now($s);
    $pa=!empty($r['preferred_at']) ? new DateTimeImmutable((string)$r['preferred_at'],$tz) : null; $ct=th_dbtime($r['created_at'] ?? null,$tz); $la=th_dbtime($r['last_attempt_at'] ?? null,$tz);
    $open=in_array($r['status'],['open','scheduled','failed'],true); $det=json_decode((string)($r['details'] ?? ''),true); $log=json_decode((string)($r['call_log'] ?? ''),true);
    $next=null;
    if($r['mode']==='ai' && (int)$r['consent']===1 && $open && (int)$r['attempts']<$s['max_attempts'] && $s['ai_callback']===1 && $acc>0){
        $t=$pa && $pa>$now ? $pa : $now; if($r['status']==='failed' && $la){ $rt=$la->modify('+'.$s['retry_min'].' minutes'); if($rt>$t) $t=$rt; }
        $w=cb_next_window($s,$t); if($w) $next=$w<=$now->modify('+1 minute') ? 'jetzt' : cb_when_label($w,$now);
    }
    $mask=$acc===0; $ph=(string)$r['phone'];
    return ['id'=>(int)$r['id'],'code'=>(string)$r['code'],'name'=>(string)$r['name'],'phone'=>$mask ? cb_mask_phone($ph) : $ph,'phone_display'=>$mask ? cb_mask_phone($ph) : cb_phone_display($ph),
        'email'=>$mask ? '' : (string)$r['email'],'topic'=>(string)$r['topic'],'details'=>is_array($det) ? $det : [],'plan'=>(string)$r['plan'],
        'preferred'=>$pa ? cb_when_label($pa,$now) : 'so schnell wie möglich','preferred_short'=>$pa ? cb_short_label($pa,$now) : 'sofort','preferred_at'=>$pa ? $pa->format('Y-m-d H:i') : null,
        'due'=>$open && (!$pa || $pa<=$now),'next_call'=>$next,'urgency'=>(string)$r['urgency'],'source'=>(string)$r['source'],'source_label'=>CB_SOURCES[$r['source']] ?? (string)$r['source'],
        'consent'=>(int)$r['consent']===1,'consent_at'=>th_dbiso($r['consent_at'] ?? null),'consent_text'=>(string)($r['consent_text'] ?? ''),
        'mode'=>(string)$r['mode'],'mode_label'=>CB_MODE_LABELS[$r['mode']] ?? (string)$r['mode'],'status'=>(string)$r['status'],'status_label'=>CB_STATUS_LABELS[$r['status']] ?? (string)$r['status'],
        'attempts'=>(int)$r['attempts'],'max_attempts'=>$s['max_attempts'],'last_attempt'=>$la ? cb_short_label($la,$now) : null,'last_attempt_at'=>th_dbiso($r['last_attempt_at'] ?? null),
        'result'=>(string)$r['result'],'created'=>$ct ? cb_short_label($ct,$now) : '','created_at'=>th_dbiso($r['created_at'] ?? null),
        'has_calls'=>(string)$r['call_sid']!=='' || (is_array($log) && $log)];
}
/**
 * Rückruf anlegen. $d: name, phone, email, topic|message, details, preferred_time|preferred_at, urgency, consent, consent_text, consent_ip (Prüfsumme),
 * callback_by ('team'|'assistant'), plan; intern _channel (Kanal des Werkzeugs) und _strict (unverständliche Wunschzeit = Fehler statt „so schnell wie möglich“).
 * Modus 'ai' nur mit KI-Rückruf + Einwilligung. Gleiche Bitte kurz hintereinander → kein Duplikat; über eine verifizierte Nummer (WhatsApp/Anruf) wird eine offene Bitte aktualisiert.
 */
function cb_create(int $acc, array $d, string $source, string $from=''): array {
    cb_bootstrap(); $s=cb_settings($acc);
    if(!cb_enabled($acc)) return ['error'=>'callbacks_disabled','hint'=>'Rückrufe sind für diesen Betrieb ausgeschaltet – bitte auf die Kontaktdaten aus der Wissensbasis verweisen.'];
    $d=cb_scalars($d,['details']); $channel=(string)($d['_channel'] ?? ''); $strict=!empty($d['_strict']);
    $source=$acc===0 ? 'demo' : (isset(CB_SOURCES[$source]) ? $source : 'api');
    $name=cb_line($d['name'] ?? '',120);
    if(mb_strlen($name)<2) return ['error'=>'name_required','hint'=>'Bitte den Namen des Kunden erfragen (Vor- und Nachname).'];
    $praw=trim((string)($d['phone'] ?? '')); $phone=$praw!=='' ? cb_phone($praw) : null;
    if($praw!=='' && $phone===null) return ['error'=>'phone_invalid','hint'=>'Telefonnummer ungültig – bitte mit Vorwahl erfragen, z. B. 0170 1234567 oder +49 170 1234567.'];
    if($phone===null && $from!=='') $phone=cb_phone(preg_match('/^\d+$/',$from) ? '+'.$from : $from);
    if($phone===null) return ['error'=>'phone_required','hint'=>'Bitte die Rückrufnummer erfragen (mit Vorwahl).'];
    $email=trim((string)($d['email'] ?? ''));
    if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)){ if(in_array($source,['form','api'],true)) return ['error'=>'email_invalid','hint'=>'Die E-Mail-Adresse ist ungültig.']; $email=''; }
    $topic=cb_line($d['topic'] ?? ($d['message'] ?? ''),500); if($topic==='') $topic='Rückrufbitte';
    $details=cb_details($d['details'] ?? []);
    $urg=in_array(mb_strtolower(trim((string)($d['urgency'] ?? ''))),['dringend','urgent','eilig','hoch','high','1','true','ja'],true) ? 'dringend' : 'normal';
    $consent=cb_truthy($d['consent'] ?? 0) ? 1 : 0;
    $rawTime=trim((string)($d['preferred_time'] ?? ($d['preferred_at'] ?? '')));
    $pt=cb_parse_time($s,$rawTime);
    if(isset($pt['error'])){ if($strict) return $pt; $details['Wunschzeit (Angabe)']=mb_substr($rawTime,0,300); $pt=['at'=>null,'asap'=>true,'adjusted'=>false,'past'=>false]; }
    $by=(string)($d['callback_by'] ?? ''); $ai=$s['ai_callback']===1 && $consent===1 && $by!=='team';
    $mode=$ai ? 'ai' : 'human'; $at=$pt['at'];
    if($mode==='ai' && $at===null && $channel==='phone'){ $at=cb_next_window($s,cb_now($s)->modify('+10 minutes')); }   // Kunde ist gerade am Telefon: nicht in dasselbe Gespräch hinein zurückrufen
    $notes=[]; if($by==='assistant' && !$ai) $notes[]=$s['ai_callback']!==1 ? 'KI-Rückruf ist bei diesem Betrieb nicht eingeschaltet – das Team ruft zurück.' : 'Ohne Einwilligung ruft das Team zurück.';
    if($pt['adjusted']) $notes[]=$pt['past'] ? 'Die genannte Zeit liegt in der Vergangenheit – eingetragen als „so schnell wie möglich“.' : 'Die Wunschzeit lag außerhalb der Rückrufzeiten ('.cb_window_text($s).') und wurde auf die nächste mögliche Zeit gelegt.';
    $p=th_db();
    /* Bestehende Bitte: über eine verifizierte Nummer aktualisieren, sonst exakte Wiederholung (gleiche Nummer + gleicher Name, 15 Min) nicht doppelt anlegen */
    $ver=null;
    /* Demo (Konto 0): Besucher teilen sich Platzhalter-Nummern → dort nie Einträge verschiedener Besucher zusammenführen */
    if($channel!=='' && $acc>0){ $vp=function_exists('ch_verified_phone') ? ch_verified_phone($acc,$channel,$from,$phone) : (in_array($channel,['phone','whatsapp'],true) && $from!=='' ? $from : null); if($vp!==null){ $vv=cb_phone(preg_match('/^\d+$/',(string)$vp) ? '+'.$vp : (string)$vp); if($vv!==null && th_same_phone($vv,$phone)) $ver=$vv; } }
    if($ver!==null){
        $st=$p->prepare("SELECT * FROM callbacks WHERE account_id=? AND phone=? AND status IN ('open','scheduled') AND created_at > (NOW() - INTERVAL 7 DAY) ORDER BY id DESC LIMIT 1"); $st->execute([$acc,$phone]);
        if($old=$st->fetch(PDO::FETCH_ASSOC)){
            $nt=(string)$old['topic']; if($topic!=='Rückrufbitte' && mb_stripos($nt,$topic)===false) $nt=mb_substr(($nt==='Rückrufbitte' ? '' : $nt.' · ').$topic,0,500);
            $nd=array_slice(array_merge(json_decode((string)$old['details'],true) ?: [],$details),0,10,true);
            $npa=$rawTime!=='' ? ($at ? $at->format('Y-m-d H:i:00') : null) : $old['preferred_at']; $nst=$npa && new DateTimeImmutable($npa,cb_tz($s))>cb_now($s) ? 'scheduled' : 'open';
            /* Modus: ausdrücklicher Wunsch zählt, sonst bleibt der bisherige (KI nur, solange KI-Rückruf an ist und eine Einwilligung vorliegt) */
            $nm=$by==='team' ? 'human' : ($by==='assistant' ? $mode : ($old['mode']==='ai' && $s['ai_callback']===1 && max($consent,(int)$old['consent'])===1 ? 'ai' : 'human'));
            $p->prepare("UPDATE callbacks SET name=?, topic=?, details=?, preferred_at=?, status=?, mode=?, urgency=IF(?='dringend','dringend',urgency), consent=GREATEST(consent,?), email=IF(?<>'',?,email), updated_at=NOW() WHERE id=? AND account_id=?")
              ->execute([$name,$nt,$nd ? json_encode($nd,JSON_UNESCAPED_UNICODE) : null,$npa,$nst,$nm,$urg,$consent,$email,$email,(int)$old['id'],$acc]);
            $row=cb_get($acc,(int)$old['id']);
            return ['ok'=>true,'updated'=>true,'id'=>(int)$row['id'],'code'=>$row['code'],'callback'=>cb_public($row,$s),'message'=>'Ihr Rückrufwunsch war bereits notiert und ist jetzt aktualisiert. '.cb_promise($row,$s),'hint'=>implode(' ',array_merge(['Bestehende Rückrufbitte aktualisiert (kein Duplikat).'],$notes))];
        }
    }
    $st=$p->prepare("SELECT * FROM callbacks WHERE account_id=? AND phone=? AND LOWER(name)=LOWER(?) AND status IN ('open','scheduled','calling') AND created_at > (NOW() - INTERVAL 15 MINUTE) ORDER BY id DESC LIMIT 1"); $st->execute([$acc,$phone,$name]);
    if($dup=$st->fetch(PDO::FETCH_ASSOC)) return ['ok'=>true,'duplicate'=>true,'id'=>(int)$dup['id'],'code'=>$dup['code'],'callback'=>cb_public($dup,$s),'message'=>'Ihre Rückrufbitte liegt uns bereits vor. '.cb_promise($dup,$s),'hint'=>'Diese Rückrufbitte war schon eingetragen – nichts doppelt anlegen.'];
    /* Schutz vor Missbrauch (fremde Nummern anrufen lassen): höchstens 4 Rückrufbitten je Nummer und Tag aus öffentlichen Kanälen.
       Nicht in der Demo (Konto 0): dort wird nie angerufen, und Besucher nutzen Platzhalter-Nummern wie 0170 0000000. */
    if($acc>0 && !in_array($source,['studio','phone','whatsapp'],true) && !th_rate('cbp:'.$acc.':'.$phone,4,86400)) return ['error'=>'too_many_requests','hint'=>'Für diese Telefonnummer liegen heute bereits mehrere Rückrufbitten vor – das Team meldet sich. Keine weitere anlegen.'];
    $now=cb_now($s); $status=$at && $at>$now ? 'scheduled' : 'open';
    $ctext=$consent ? cb_line($d['consent_text'] ?? '',500) : ''; if($consent && $ctext==='') $ctext=in_array($source,['chat','widget','phone','whatsapp','demo'],true) ? 'Rückruf im Gespräch ('.(CB_SOURCES[$source] ?? $source).') ausdrücklich erbeten.' : 'Rückruf ausdrücklich angefordert.';
    $cip=preg_match('/^[a-f0-9]{32}$/',(string)($d['consent_ip'] ?? '')) ? (string)$d['consent_ip'] : '';
    $plan=preg_replace('/[^a-z0-9-]/','',strtolower((string)($d['plan'] ?? ''))) ?? '';
    $id=0;
    for($try=0;$try<6 && !$id;$try++){
        try{
            $p->prepare("INSERT INTO callbacks(account_id,code,plan,name,phone,email,topic,details,preferred_at,urgency,source,consent,consent_at,consent_ip,consent_text,mode,status)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,".($consent ? 'NOW()' : 'NULL').",?,?,?,?)")
              ->execute([$acc,cb_code(),mb_substr($plan,0,40),$name,$phone,mb_substr($email,0,190),$topic,$details ? json_encode($details,JSON_UNESCAPED_UNICODE) : null,$at ? $at->format('Y-m-d H:i:00') : null,$urg,$source,$consent,$cip,$ctext,$mode,$status]);
            $id=(int)$p->lastInsertId();
        }catch(PDOException $e){ if($e->getCode()!=='23000' || $try>=5) throw $e; }   // Code-Kollision → neuer Code
    }
    $row=cb_get($acc,$id);
    if($acc>0){ try{ cb_notify_new($row,$s); }catch(Throwable $e){ error_log('CH cb_notify_new: '.$e->getMessage()); } }
    $out=['ok'=>true,'id'=>$id,'code'=>$row['code'],'callback'=>cb_public($row,$s),'message'=>cb_promise($row,$s)];
    if($notes) $out['hint']=implode(' ',$notes);
    if($acc===0) $out['demo']='Demo: Es wird niemand angerufen und keine E-Mail verschickt.';
    return $out;
}
/** Studio-Liste: open = offen/geplant/läuft/nicht erreicht (Dringendes und Fälliges zuerst) · done = erledigt/storniert (30 Tage) · all = 90 Tage. */
function cb_list(int $acc, string $scope='open'): array {
    cb_bootstrap(); $s=cb_settings($acc); $p=th_db();
    if($scope==='done'){ $st=$p->prepare("SELECT * FROM callbacks WHERE account_id=? AND status IN ('done','cancelled') AND COALESCE(updated_at,created_at) > (NOW() - INTERVAL 30 DAY) ORDER BY COALESCE(updated_at,created_at) DESC LIMIT 200"); $st->execute([$acc]); }
    elseif($scope==='all'){ $st=$p->prepare("SELECT * FROM callbacks WHERE account_id=? AND created_at > (NOW() - INTERVAL 90 DAY) ORDER BY id DESC LIMIT 300"); $st->execute([$acc]); }
    else { $st=$p->prepare("SELECT * FROM callbacks WHERE account_id=? AND status IN ('open','scheduled','calling','failed') ORDER BY id DESC LIMIT 300"); $st->execute([$acc]); }
    $items=array_map(fn($r)=>cb_item($r,$s),$st->fetchAll(PDO::FETCH_ASSOC));
    if($scope==='open'){
        $rank=['calling'=>0,'open'=>1,'scheduled'=>1,'failed'=>2];
        usort($items,function($a,$b) use($rank){
            return [$rank[$a['status']] ?? 3,$a['urgency']==='dringend' ? 0 : 1,$a['due'] ? 0 : 1,$a['preferred_at'] ?? '0000',$a['id']] <=> [$rank[$b['status']] ?? 3,$b['urgency']==='dringend' ? 0 : 1,$b['due'] ? 0 : 1,$b['preferred_at'] ?? '0000',$b['id']];
        });
    }
    return $items;
}
/** Status/Notiz im Studio setzen: status open (wieder öffnen) | done | cancelled | '' (nur Notiz); result = Notiz/Ergebnis (null = unverändert). */
function cb_update(int $acc, int $id, string $status, ?string $result): array {
    cb_bootstrap(); $r=cb_get($acc,$id); if(!$r) return ['error'=>'not_found','hint'=>'Rückruf nicht gefunden.'];
    $status=trim($status); if($status!=='' && !in_array($status,['open','done','cancelled'],true)) return ['error'=>'bad_status','hint'=>'Erlaubt: open, done, cancelled.'];
    $s=cb_settings($acc); $set=[]; $v=[];
    if($status!==''){
        $new=$status;
        if($status==='open'){ $pa=!empty($r['preferred_at']) ? new DateTimeImmutable((string)$r['preferred_at'],cb_tz($s)) : null; $new=$pa && $pa>cb_now($s) ? 'scheduled' : 'open';
            if((int)$r['attempts']>=$s['max_attempts'] && $r['mode']==='ai'){ $set[]="mode='human'"; } }   // Versuche aufgebraucht: wieder offen heißt „Team ruft an“
        $set[]='status=?'; $v[]=$new;
    }
    if($result!==null){ $set[]='result=?'; $v[]=cb_line($result,500); }
    if(!$set) return ['ok'=>true,'unchanged'=>true,'callback'=>cb_item($r,$s)];
    $v[]=$id; $v[]=$acc;
    th_db()->prepare("UPDATE callbacks SET ".implode(',',$set).", updated_at=NOW() WHERE id=? AND account_id=?")->execute($v);
    return ['ok'=>true,'callback'=>cb_item(cb_get($acc,$id),$s)];
}
/** Kennzahlen für das Studio. */
function cb_stats(int $acc): array {
    cb_bootstrap(); $s=cb_settings($acc); $tz=cb_tz($s); $now=cb_now($s); $today=$now->format('Y-m-d'); $p=th_db();
    $st=$p->prepare("SELECT status, mode, preferred_at, attempts, result, created_at, COALESCE(updated_at,created_at) AS ch FROM callbacks WHERE account_id=? AND (status IN ('open','scheduled','calling','failed') OR created_at > (NOW() - INTERVAL 8 DAY) OR updated_at > (NOW() - INTERVAL 8 DAY))"); $st->execute([$acc]);
    $o=['open'=>0,'due'=>0,'today'=>0,'done_7d'=>0,'failed'=>0,'ai_talks_7d'=>0,'ai_attempts_7d'=>0,'today_date'=>$today];
    $wk=$now->modify('-7 days');
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
        $ct=th_dbtime($r['created_at'],$tz); $ch=th_dbtime($r['ch'],$tz);
        if(in_array($r['status'],CB_OPEN,true)){ $o['open']++; $pa=$r['preferred_at'] ? new DateTimeImmutable((string)$r['preferred_at'],$tz) : null; if($r['status']!=='calling' && (!$pa || $pa<=$now)) $o['due']++; if($r['status']==='failed') $o['failed']++; }
        if($ct && $ct->format('Y-m-d')===$today) $o['today']++;
        if($r['status']==='done' && $ch && $ch>=$wk){ $o['done_7d']++; if(str_starts_with((string)$r['result'],'KI-Gespräch')) $o['ai_talks_7d']++; }
        if($r['mode']==='ai' && $ct && $ct>=$wk) $o['ai_attempts_7d']+=(int)$r['attempts'];
    }
    return $o;
}
/** Anrufversuche eines Rückrufs mit Transkripten (phone_calls). */
function cb_calls(int $acc, int $id): ?array {
    $r=cb_get($acc,$id); if(!$r) return null;
    $log=json_decode((string)$r['call_log'],true); if(!is_array($log)) $log=[];
    $sids=array_values(array_unique(array_filter(array_merge(array_map(fn($e)=>(string)($e['sid'] ?? ''),$log),[(string)$r['call_sid']]))));
    $calls=[];
    if($sids){
        $st=th_db()->prepare("SELECT call_sid,status,turns,outcome,transcript,started_at,ended_at FROM phone_calls WHERE account_id=? AND call_sid IN (".implode(',',array_fill(0,count($sids),'?')).")");
        $st->execute(array_merge([$acc],$sids));
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $c){ $tr=json_decode((string)$c['transcript'],true); $calls[$c['call_sid']]=['status'=>$c['status'],'turns'=>(int)$c['turns'],'outcome'=>$c['outcome'],'started_at'=>th_dbiso($c['started_at']),'ended_at'=>th_dbiso($c['ended_at']),
            'transcript'=>array_values(array_map(fn($m)=>['role'=>($m['role'] ?? '')==='user' ? 'user' : 'assistant','content'=>(string)($m['content'] ?? '')],array_filter(is_array($tr) ? $tr : [],fn($m)=>is_array($m) && in_array($m['role'] ?? '',['user','assistant'],true))))]; }
    }
    $att=[];
    foreach($log as $e){ $sid=(string)($e['sid'] ?? ''); $att[]=['at'=>$e['at'] ?? null,'event'=>(string)($e['event'] ?? ''),'status'=>(string)($e['status'] ?? ''),'answered_by'=>(string)($e['answered_by'] ?? ''),'note'=>(string)($e['note'] ?? ''),'sid'=>$sid,'call'=>$sid!=='' ? ($calls[$sid] ?? null) : null]; }
    return ['id'=>(int)$r['id'],'code'=>(string)$r['code'],'attempts'=>$att];
}

/* ======================================================================= Benachrichtigungen */
function cb_mail_html(string $title, string $sub, string $body): string {
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:26px;border:1px solid #e6e8ef;border-radius:18px;color:#0f1222">'
        .'<div style="font:700 12px Inter,Arial,sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#b8862b">ChatHelp · Rückrufe</div>'
        .'<h2 style="margin:8px 0 4px;font-size:21px">'.$title.'</h2>'.($sub!=='' ? '<p style="margin:0 0 14px;color:#6b7083;font-size:14px">'.$sub.'</p>' : '').$body
        .'<p style="font-size:13px;color:#6b7083;margin-top:20px">Rückrufliste im Studio: <a href="https://chat-help.com/chat/studio.php#rueckruf" style="color:#b8862b">chat-help.com/chat/studio.php → Rückrufe</a></p></div>';
}
function cb_mail_card(array $r): string {
    $tel=(string)$r['phone']; $det=json_decode((string)($r['details'] ?? ''),true) ?: [];
    $rows=''; foreach($det as $k=>$v) $rows.='<tr><td style="padding:5px 10px 5px 0;color:#6b7083;white-space:nowrap;vertical-align:top">'.cb_e((string)$k).'</td><td style="padding:5px 0">'.cb_e((string)$v).'</td></tr>';
    return '<p style="margin:0 0 14px"><a href="tel:'.cb_e($tel).'" style="display:inline-block;background:#0f1222;color:#fff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:700">📞 '.cb_e(cb_phone_display($tel)).' anrufen</a></p>'
        .'<p style="margin:0 0 6px"><b>'.cb_e((string)$r['name']).'</b>'.($r['email'] ? ' · <a href="mailto:'.cb_e((string)$r['email']).'" style="color:#b8862b">'.cb_e((string)$r['email']).'</a>' : '').'</p>'
        .'<div style="padding:12px 14px;border-radius:12px;background:#f6ecd3;margin:8px 0 12px;font-size:15px">'.cb_e((string)$r['topic']).'</div>'
        .($rows!=='' ? '<table style="border-collapse:collapse;font-size:14px;margin-bottom:8px">'.$rows.'</table>' : '');
}
function cb_notify_new(array $r, array $s): void {
    $acc=(int)$r['account_id']; if($acc<=0) return;
    if(function_exists('ch_push')) ch_push($acc,in_array($r['source'] ?? '',['form','api'],true)?'lead_new':'callback_new',(($r['urgency'] ?? '')==='dringend'?'⚡ Dringender Rückruf: ':'Rückrufwunsch: ').$r['name'],mb_substr((string)$r['topic'],0,160).' · '.$r['phone'],'/chat/studio.php#rueckruf',['tag'=>'cb-'.$r['code'],'email'=>false]); $to=$s['notify_email'] ?: cb_owner_email($acc); if($to==='') return;
    $now=cb_now($s); $pa=!empty($r['preferred_at']) ? new DateTimeImmutable((string)$r['preferred_at'],cb_tz($s)) : null; $when=$pa ? cb_when_label($pa,$now) : 'so schnell wie möglich';
    $ai=$r['mode']==='ai'; $wp=cb_when_phrase($r,$s);
    $info='<p style="font-size:14px;color:#3b4055;margin:6px 0">Wunschzeit: <b>'.cb_e($when).'</b> · Quelle: '.cb_e(CB_SOURCES[$r['source']] ?? (string)$r['source']).' · Code '.cb_e((string)$r['code'])
        .($r['urgency']==='dringend' ? ' · <b style="color:#d9336b">DRINGEND</b>' : '').'</p>'
        .'<p style="font-size:14px;color:#3b4055;margin:6px 0">'.($ai ? '🤖 <b>Ihr digitaler Assistent ruft automatisch zurück</b> – '.cb_e($wp['when'].($wp['tail']!=='' ? ', '.$wp['tail'] : '')).'. Ergebnis und Gesprächsprotokoll sehen Sie danach im Studio.' : '👤 <b>Bitte zurückrufen</b> und danach im Studio als „Erledigt“ markieren.').'</p>'
        .((int)$r['consent']===1 ? '<p style="font-size:12.5px;color:#6b7083;margin:6px 0">Einwilligung: '.cb_e((string)$r['consent_text']).'</p>' : '<p style="font-size:12.5px;color:#6b7083;margin:6px 0">Ohne Einwilligung in einen KI-Anruf – bitte persönlich zurückrufen.</p>');
    th_mail($to,($r['urgency']==='dringend' ? '⚡ DRINGEND · ' : '').'📞 Rückrufbitte: '.$r['name'].' · '.($pa ? cb_short_label($pa,$now) : 'sofort'),cb_mail_html('Rückrufbitte von '.cb_e((string)$r['name']),cb_e(cb_short_label(th_dbtime($r['created_at'],cb_tz($s)) ?: $now,$now)).' Uhr',cb_mail_card($r).$info));
}
/** Ergebnis eines KI-Rückrufs: 'done' (mit Gesprächsprotokoll) oder 'failed' (alle Versuche erfolglos → bitte selbst anrufen). */
function cb_notify_result(array $r, array $s, string $event): void {
    $acc=(int)$r['account_id']; if($acc<=0) return; $to=$s['notify_email'] ?: cb_owner_email($acc); if($to==='') return;
    if($event==='done'){
        $tr=''; if((string)$r['call_sid']!==''){ $st=th_db()->prepare("SELECT transcript FROM phone_calls WHERE account_id=? AND call_sid=?"); $st->execute([$acc,(string)$r['call_sid']]); $lines=json_decode((string)$st->fetchColumn(),true) ?: [];
            foreach(array_slice(array_filter($lines,fn($m)=>is_array($m) && in_array($m['role'] ?? '',['user','assistant'],true)),-40) as $m) $tr.='<div style="margin:4px 0;padding:7px 10px;border-radius:10px;font-size:13.5px;'.(($m['role'] ?? '')==='user' ? 'background:#0f1222;color:#fff;margin-left:40px' : 'background:#f1f2f7;margin-right:40px').'">'.cb_e((string)($m['content'] ?? '')).'</div>'; }
        th_mail($to,'✅ KI-Rückruf geführt: '.$r['name'],cb_mail_html('KI-Rückruf mit '.cb_e((string)$r['name']).' geführt',cb_e((string)$r['result']),cb_mail_card($r).($tr!=='' ? '<h3 style="font-size:15px;margin:14px 0 6px">Gesprächsprotokoll</h3>'.$tr : '')));
    } else {
        th_mail($to,'⚠️ Nicht erreicht: '.$r['name'].' – bitte selbst zurückrufen',cb_mail_html(cb_e((string)$r['name']).' wurde nicht erreicht',cb_e((string)$r['result']),cb_mail_card($r).'<p style="font-size:14px;color:#3b4055">Der digitale Assistent hat alle '.(int)$s['max_attempts'].' Anrufversuche ausgeschöpft. Bitte persönlich zurückrufen und den Rückruf danach im Studio als erledigt markieren.</p>'));
    }
}

/* ======================================================================= Twilio: ausgehender KI-Rückruf */
/** Formular-Kodierung mit wiederholten Schlüsseln für Listen (Twilio: StatusCallbackEvent=…&StatusCallbackEvent=…). */
function cb_form_encode(array $p): string {
    $o=[]; foreach($p as $k=>$v){ foreach(is_array($v) ? $v : [$v] as $x) $o[]=rawurlencode((string)$k).'='.rawurlencode((string)$x); } return implode('&',$o);
}
function cb_twilio_call(array $tw, array $params): array {
    $base=defined('CB_TWILIO_API') && CB_TWILIO_API ? rtrim((string)CB_TWILIO_API,'/') : 'https://api.twilio.com';
    $ch=curl_init($base.'/2010-04-01/Accounts/'.rawurlencode((string)$tw['sid']).'/Calls.json');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_USERPWD=>$tw['sid'].':'.$tw['token'],
        CURLOPT_POSTFIELDS=>cb_form_encode($params),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json']]);
    $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $j=is_string($raw) ? json_decode($raw,true) : null;
    if($code>=200 && $code<300 && is_array($j) && !empty($j['sid'])) return ['ok'=>true,'sid'=>preg_replace('/[^A-Za-z0-9]/','',(string)$j['sid']),'code'=>$code];
    $m=is_array($j) ? trim((string)($j['message'] ?? '')) : ''; if($m==='') $m=$code ? 'HTTP '.$code : 'Twilio nicht erreichbar'.($err!=='' ? ' ('.$err.')' : '');
    error_log('CH cb_twilio_call: '.$code.' '.mb_substr($m,0,200));
    return ['ok'=>false,'code'=>$code,'message'=>mb_substr($m,0,240),'twilio_code'=>is_array($j) ? ($j['code'] ?? null) : null];
}
/**
 * KI-Rückruf jetzt starten. Prüft Einwilligung, Versuche, Status, Telefon-Agent (Telefon an, gebucht), Twilio + Absendernummer und – außer mit $force (Studio) – die Anrufzeiten.
 * Beansprucht den Rückruf atomar (status calling, attempts+1) und ruft Twilio Calls.json auf. Rückgabe ['ok'=>true,'call_sid'=>…] oder ['error'=>…,'hint'=>…].
 */
function cb_call_now(int $acc, int $id, bool $force=false): array {
    cb_bootstrap();
    if($acc<=0) return ['error'=>'demo','hint'=>'In der Demo wird niemand angerufen.'];
    $r=cb_get($acc,$id); if(!$r) return ['error'=>'not_found','hint'=>'Rückruf nicht gefunden.'];
    $s=cb_settings($acc); $now=cb_now($s);
    if(in_array($r['status'],['done','cancelled'],true)) return ['error'=>'already_closed','status'=>$r['status'],'hint'=>'Dieser Rückruf ist bereits '.($r['status']==='done' ? 'erledigt' : 'storniert').'.'];
    if($r['status']==='calling') return ['error'=>'already_calling','hint'=>'Der Assistent telefoniert gerade mit dieser Person.'];
    if((int)$r['consent']!==1) return ['error'=>'no_consent','hint'=>'Ohne Einwilligung darf der KI-Assistent nicht anrufen (UWG § 7) – bitte persönlich zurückrufen.'];
    if((int)$r['attempts']>=$s['max_attempts']) return ['error'=>'max_attempts','attempts'=>(int)$r['attempts'],'max_attempts'=>$s['max_attempts'],'hint'=>'Höchstzahl an Anrufversuchen erreicht ('.$s['max_attempts'].') – bitte persönlich zurückrufen.'];
    $cfg=cb_caller_cfg($acc,$s,(string)$r['plan']);
    if(!$cfg || empty($cfg['widget_key']) || (int)$cfg['phone_on']!==1) return ['error'=>'phone_agent_missing','hint'=>$cfg ? 'Beim Agenten „'.cb_plan_name((string)$cfg['plan']).'“ ist das Telefon ausgeschaltet (Studio → Telefon).' : 'Kein Telefon-Agent eingerichtet – im Studio unter „Telefon“ aktivieren und unter „Rückrufe“ als anrufenden Agenten wählen.'];
    if(!cb_plan_ok($acc,(string)$cfg['plan'])) return ['error'=>'plan_inactive','hint'=>'Der anrufende Agent „'.cb_plan_name((string)$cfg['plan']).'“ ist nicht gemietet bzw. gekauft.'];
    $tw=th_twilio($acc); if(!$tw) return ['error'=>'twilio_not_configured','hint'=>'Twilio ist nicht eingerichtet (Studio → Telefon → Verbindungen).'];
    $cid=cb_caller_id($acc,$s,$cfg,$tw); if($cid==='') return ['error'=>'twilio_not_configured','hint'=>'Keine Absendernummer (Caller-ID) – dem Agenten ist keine Telefonnummer zugewiesen.'];
    if(!$force && !cb_in_window($s,$now)){ $nx=cb_next_window($s,$now); return ['error'=>'outside_window','next_window'=>$nx ? $nx->format('Y-m-d H:i') : null,'hint'=>'Außerhalb der Anrufzeiten ('.cb_window_text($s).')'.($nx ? ' – frühestens '.cb_when_label($nx,$now) : '').'.']; }
    $u=th_db()->prepare("UPDATE callbacks SET status='calling', attempts=attempts+1, last_attempt_at=NOW(), call_sid='', updated_at=NOW() WHERE id=? AND account_id=? AND status IN ('open','scheduled','failed') AND attempts < ? AND consent=1");
    $u->execute([$id,$acc,$s['max_attempts']]); if($u->rowCount()===0) return ['error'=>'busy','hint'=>'Der Rückruf wurde gerade geändert – bitte die Liste neu laden.'];
    $att=(int)$r['attempts']+1;
    $url=CB_BASE_URL.'phone-webhook.php?key='.rawurlencode((string)$cfg['widget_key']).'&cb='.$id;
    $res=cb_twilio_call($tw,['To'=>(string)$r['phone'],'From'=>$cid,'Url'=>$url,'Method'=>'POST','StatusCallback'=>$url,'StatusCallbackMethod'=>'POST','StatusCallbackEvent'=>['completed'],
        'MachineDetection'=>'DetectMessageEnd','Timeout'=>25]);
    if($res['ok']){
        th_db()->prepare("UPDATE callbacks SET call_sid=?, result=? WHERE id=? AND account_id=?")->execute([$res['sid'],'Anrufversuch '.$att.' läuft …',$id,$acc]);
        cb_log_append($acc,$id,['event'=>'call','sid'=>$res['sid'],'status'=>'initiated','note'=>'Versuch '.$att.($force ? ' (im Studio gestartet)' : '')]);
        return ['ok'=>true,'call_sid'=>$res['sid'],'attempt'=>$att,'from'=>$cid];
    }
    $note='Anrufversuch '.$att.' fehlgeschlagen – Twilio: '.$res['message'];
    th_db()->prepare("UPDATE callbacks SET status='failed', result=?, updated_at=NOW() WHERE id=? AND account_id=? AND status='calling'")->execute([mb_substr($note,0,500),$id,$acc]);
    cb_log_append($acc,$id,['event'=>'call','sid'=>'','status'=>'error','note'=>mb_substr($note,0,300)]);
    if($att>=$s['max_attempts']){ try{ cb_notify_result(cb_get($acc,$id),$s,'failed'); }catch(Throwable $e){ error_log('CH cb_notify_result: '.$e->getMessage()); } }
    return ['error'=>'twilio_error','message'=>$res['message'],'hint'=>'Twilio hat den Anruf nicht angenommen: '.$res['message']];
}

/* ======================================================================= Planer */
/**
 * Fällige KI-Rückrufe starten (höchstens 1× je Minute, eine Instanz zugleich über th_lock): bis zu 5 Anrufe kontoübergreifend –
 * Modus ai, Einwilligung, KI-Rückruf an, offen/geplant (oder nicht erreicht mit freien Versuchen und Abstand ≥ retry_min), Wunschzeit erreicht, innerhalb der Anrufzeiten.
 * Hängende Anrufe (calling > 15 Min ohne Status-Rückmeldung) werden als „nicht erreicht“ markiert.
 */
function cb_scheduler_lazy(): void {
    if(!th_lock('cb_scheduler',0)) return;
    try{
        cb_bootstrap(); $p=th_db();
        $g=$p->query("SELECT TIMESTAMPDIFF(SECOND,last_run,NOW()) FROM callback_jobs WHERE name='scheduler'")->fetchColumn();
        if($g!==false && $g!==null && (int)$g>=0 && (int)$g<60) return;
        $p->exec("INSERT INTO callback_jobs(name,last_run) VALUES('scheduler',NOW()) ON DUPLICATE KEY UPDATE last_run=NOW()");
        foreach($p->query("SELECT * FROM callbacks WHERE status='calling' AND account_id>0 AND (last_attempt_at IS NULL OR last_attempt_at < (NOW() - INTERVAL 15 MINUTE)) LIMIT 50")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $acc=(int)$r['account_id']; $s=cb_settings($acc); $note='Versuch '.(int)$r['attempts'].': keine Rückmeldung vom Anruf (Zeitüberschreitung)';
            $u=$p->prepare("UPDATE callbacks SET status='failed', result=?, updated_at=NOW() WHERE id=? AND account_id=? AND status='calling'"); $u->execute([$note,(int)$r['id'],$acc]);
            if($u->rowCount()>0){ cb_log_append($acc,(int)$r['id'],['event'=>'timeout','sid'=>(string)$r['call_sid'],'status'=>'timeout','note'=>$note]);
                if((int)$r['attempts']>=$s['max_attempts']){ try{ cb_notify_result(cb_get($acc,(int)$r['id']),$s,'failed'); }catch(Throwable $e){ error_log('CH cb_notify_result: '.$e->getMessage()); } } }
        }
        /* preferred_at ist Wanduhr der Konto-Zeitzone (bis UTC+14): großzügig vorfiltern, exakt je Konto in PHP prüfen */
        $q=$p->prepare("SELECT c.* FROM callbacks c JOIN callback_settings s ON s.account_id=c.account_id
            WHERE c.account_id>0 AND c.mode='ai' AND c.consent=1 AND s.ai_callback=1 AND s.cb_on=1 AND c.attempts < s.max_attempts
              AND (c.status IN ('open','scheduled') OR (c.status='failed' AND (c.last_attempt_at IS NULL OR c.last_attempt_at < (NOW() - INTERVAL s.retry_min MINUTE))))
              AND (c.preferred_at IS NULL OR c.preferred_at <= ?) AND c.created_at > (NOW() - INTERVAL 14 DAY)
            ORDER BY (c.urgency='dringend') DESC, c.id LIMIT 60");
        $q->execute([(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+15 hours')->format('Y-m-d H:i:s')]);
        $calls=0; $busy=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
            if($calls>=5) break;
            $acc=(int)$r['account_id']; $s=cb_settings($acc); $tz=cb_tz($s); $now=cb_now($s);
            if($r['preferred_at']!==null){ $pa=new DateTimeImmutable((string)$r['preferred_at'],$tz); if($pa>$now || $pa<$now->modify('-3 days')) continue; }   // zu früh bzw. veraltet: nicht mehr automatisch anrufen
            elseif(($ct=th_dbtime($r['created_at'],$tz)) && $ct<$now->modify('-3 days')) continue;
            if(!cb_in_window($s,$now)) continue;
            if(!isset($busy[$acc])){ $c=$p->prepare("SELECT COUNT(*) FROM callbacks WHERE account_id=? AND status='calling'"); $c->execute([$acc]); $busy[$acc]=(int)$c->fetchColumn(); }
            if($busy[$acc]>=2) continue;   // höchstens zwei gleichzeitige KI-Rückrufe je Betrieb
            $res=cb_call_now($acc,(int)$r['id']);
            if(!empty($res['ok'])){ $busy[$acc]++; $calls++; continue; }
            if(($res['error'] ?? '')==='twilio_error'){ $calls++; continue; }
            /* Einrichtungsproblem: sichtbar machen statt jede Minute still zu scheitern */
            $note='KI-Rückruf wartet: '.($res['hint'] ?? $res['error'] ?? 'nicht möglich');
            if((string)$r['result']!==$note) $p->prepare("UPDATE callbacks SET result=? WHERE id=? AND account_id=? AND status IN ('open','scheduled','failed')")->execute([mb_substr($note,0,500),(int)$r['id'],$acc]);
        }
    }catch(Throwable $e){ error_log('CH cb_scheduler: '.$e->getMessage()); }
    finally{ th_unlock('cb_scheduler'); }
}

/* ======================================================================= Telefon-Webhook (KI-Rückruf) */
function cb_daypart(DateTimeImmutable $now): string { $h=(int)$now->format('G'); return $h<11 ? 'Guten Morgen' : ($h<18 ? 'Guten Tag' : 'Guten Abend'); }
/** Anliegen zum Vorsprechen: ohne Links, höchstens ~140 Zeichen an einer Wortgrenze. */
function cb_speak_topic(string $t): string {
    $t=trim(preg_replace('~https?://\S+~u','',$t) ?? $t); $t=trim(preg_replace('/\s+/u',' ',str_replace(['*','#','`','_'],' ',$t)) ?? $t);
    if(mb_strlen($t)>140){ $t=mb_substr($t,0,140); $sp=mb_strrpos($t,' '); if($sp!==false && $sp>60) $t=mb_substr($t,0,$sp); }
    return rtrim($t,' .,;:!?–-');
}
/**
 * Kontext für einen ausgehenden KI-Rückruf (vom Telefon-Webhook bei jedem Schritt aufgerufen): null, wenn der Rückruf nicht existiert
 * oder nicht zum Konto des widget_key gehört. greeting = erster Satz, system = Gesprächsauftrag für die KI, voicemail = Text für den Anrufbeantworter.
 */
function cb_call_context(int $cbId, string $widgetKey): ?array {
    if($cbId<=0 || !preg_match('/^wk_[a-f0-9]{36}$/',$widgetKey)) return null;
    cb_bootstrap();
    $st=th_db()->prepare("SELECT * FROM agent_configs WHERE widget_key=?"); $st->execute([$widgetKey]); $cfg=$st->fetch(PDO::FETCH_ASSOC); if(!$cfg) return null;
    $acc=(int)$cfg['account_id']; if($acc<=0) return null;
    $r=cb_get($acc,$cbId); if(!$r) return null;
    $s=cb_settings($acc); $now=cb_now($s); $company=cb_company($acc,$s,$cfg); $name=(string)$r['name']; $topic=cb_speak_topic((string)$r['topic']);
    $first=$s['intro']!=='' ? trim(strtr($s['intro'],['{firma}'=>$company,'{Firma}'=>$company,'{name}'=>$name,'{Name}'=>$name])) : cb_daypart($now).', hier ist der digitale Assistent'.($company!=='' ? ' von '.$company : '').'.';
    if(!preg_match('/[.!?]$/u',$first)) $first.='.';
    $greeting=$first.' Sie hatten um einen Rückruf gebeten'.($topic!=='' && $topic!=='Rückrufbitte' ? ' – es geht um '.$topic : '').'. Passt es Ihnen gerade?';
    $det=json_decode((string)$r['details'],true) ?: []; $ct=th_dbtime($r['created_at'],cb_tz($s));
    $appt=function_exists('appt_settings') && (int)(appt_settings($acc)['appt_on'] ?? 0)===1;
    $fw=trim((string)($cfg['phone_forward'] ?? ''))!=='';
    $immo=str_starts_with((string)$cfg['plan'],'immo') || (function_exists('ch_plan') && (ch_plan((string)$cfg['plan'])['sector'] ?? '')==='immobilien');
    $att=max(1,(int)$r['attempts']); $last=$att>=$s['max_attempts'];
    $sys="KI-RÜCKRUF – AUSGEHENDER ANRUF: Du rufst an (nicht der Kunde). Gesprächspartner: ".$name.", Nummer ".cb_phone_display((string)$r['phone'])." (nicht vorlesen). "
        ."Rückrufbitte ".$r['code']." vom ".($ct ? $ct->format('d.m.Y, H:i').' Uhr' : 'heute')." über ".(CB_SOURCES[$r['source']] ?? $r['source']).((int)$r['consent']===1 ? ', Einwilligung liegt vor' : '').". "
        ."Anliegen: ".$r['topic'].".".($det ? ' Angaben aus der Anfrage: '.implode('; ',array_map(fn($k,$v)=>$k.': '.$v,array_keys($det),$det)).'.' : '')
        .($r['urgency']==='dringend' ? ' Als DRINGEND markiert.' : '')." Versuch ".$att." von ".$s['max_attempts'].". Firma: ".($company!=='' ? $company : 'siehe Wissensbasis').". Rückrufzeiten: ".cb_window_text($s).". Jetzt: ".CB_WDL[(int)$now->format('w')].', '.$now->format('d.m.Y H:i')." Uhr.\n"
        ."ABLAUF: 1) Die Begrüßung ist schon gesprochen. Passt es gerade nicht: nach einer besseren Zeit fragen, request_callback mit name „".$name."“, dem Anliegen und dieser Wunschzeit aufrufen (callback_by „assistant“), die Zeit bestätigen, freundlich verabschieden, [ENDE]. "
        ."2) Sprichst du nicht mit ".$name." oder wollte die Person keinen Rückruf: keine Details nennen, entschuldigen, zusagen, dass wir nicht erneut anrufen, verabschieden, [ENDE]. "
        ."3) Anliegen klären und kurz qualifizieren, höchstens zwei Fragen pro Antwort".($immo ? ' (Kauf oder Miete, Budget bzw. Finanzierungsbestätigung, Haushaltsgröße, gewünschter Einzug, Haustiere)' : ' (was genau gebraucht wird, Zeitrahmen, ggf. Budget)').". Fragen nur aus der Wissensbasis beantworten, nichts erfinden. "
        ."4) ".($appt ? 'Termin-Werkzeuge sind verfügbar: passenden Termin bzw. eine Besichtigung direkt anbieten – get_available_slots, dann nach ausdrücklichem Ja book_appointment mit Name und dieser Telefonnummer.' : 'Keine Online-Terminbuchung: Termin- oder Besichtigungswunsch mit request_callback (callback_by „team“) für das Team aufnehmen.').' '
        ."5) Möchte die Person einen Menschen oder kommst du nicht weiter: ".($fw ? 'kurz ankündigen und [WEITERLEITEN].' : 'request_callback mit callback_by „team“ und einer kurzen Zusammenfassung, dann sagen, dass das Team zurückruft.').' '
        ."6) Kurz, höflich, natürlich; keine Links oder Codes vorlesen. Am Ende das Ergebnis in einem Satz zusammenfassen, verabschieden und [ENDE].";
    $vm=cb_daypart($now).', hier ist der digitale Assistent'.($company!=='' ? ' von '.$company : '').'. Sie hatten um einen Rückruf gebeten – leider haben wir Sie gerade nicht erreicht. '
        .($last ? (($cid=cb_caller_id($acc,$s,$cfg,th_twilio($acc)))!=='' ? 'Sie erreichen uns jederzeit unter '.cb_speak_number($cid).'. ' : 'Rufen Sie uns gern unter der angezeigten Nummer zurück. ') : 'Wir versuchen es später noch einmal. ')
        .'Vielen Dank und auf Wiederhören.';
    return ['account_id'=>$acc,'callback_id'=>(int)$r['id'],'code'=>(string)$r['code'],'greeting'=>$greeting,'system'=>$sys,'voicemail'=>$vm];
}
/**
 * Status-Rückmeldung des Anrufs (Telefon-Webhook): completed mit ≥ 1 Gesprächsschritt → done („KI-Gespräch geführt“);
 * Anrufbeantworter, nicht abgenommen, besetzt, fehlgeschlagen oder 0 Schritte → neuer Versuch nach retry_min (in den Anrufzeiten), sonst failed + Mail.
 * Nur Rückrufe im Status calling werden verarbeitet (doppelte Status-Rückmeldungen sind wirkungslos).
 */
function cb_on_call_status(int $cbId, string $callStatus, string $answeredBy, int $turns): void {
    try{
        cb_bootstrap(); $p=th_db();
        $st=$p->prepare("SELECT * FROM callbacks WHERE id=? AND account_id>0"); $st->execute([$cbId]); $r=$st->fetch(PDO::FETCH_ASSOC);
        if(!$r || $r['status']!=='calling') return;
        $acc=(int)$r['account_id']; $s=cb_settings($acc); $now=cb_now($s); $att=(int)$r['attempts'];
        $call=null; if((string)$r['call_sid']!==''){ $c=$p->prepare("SELECT outcome, turns FROM phone_calls WHERE account_id=? AND call_sid=?"); $c->execute([$acc,(string)$r['call_sid']]); $call=$c->fetch(PDO::FETCH_ASSOC) ?: null; }
        $machine=str_starts_with($answeredBy,'machine') || $answeredBy==='fax' || (($call['outcome'] ?? '')==='voicemail');
        $log=['event'=>'status','sid'=>(string)$r['call_sid'],'status'=>mb_substr($callStatus,0,20),'answered_by'=>mb_substr($answeredBy,0,30)];
        if($callStatus==='completed' && $turns>=1 && !$machine){
            $oc=(string)($call['outcome'] ?? '');
            $res='KI-Gespräch geführt ('.$turns.' '.($turns===1 ? 'Antwort' : 'Antworten').')'.($oc==='forwarded' ? ' · an das Team weitergeleitet' : ($oc==='callback' ? ' · Rückruf durch das Team gewünscht' : ''));
            $u=$p->prepare("UPDATE callbacks SET status='done', result=?, updated_at=NOW() WHERE id=? AND account_id=? AND status='calling'"); $u->execute([$res,$cbId,$acc]);
            if($u->rowCount()>0){ cb_log_append($acc,$cbId,$log+['note'=>$res]); try{ cb_notify_result(cb_get($acc,$cbId),$s,'done'); }catch(Throwable $e){ error_log('CH cb_notify_result: '.$e->getMessage()); } }
            return;
        }
        $reason=$machine ? (($call['outcome'] ?? '')==='voicemail' ? 'Anrufbeantworter – Nachricht hinterlassen' : 'Anrufbeantworter') : match($callStatus){
            'busy'=>'Besetzt','no-answer'=>'Nicht abgenommen','failed'=>'Anruf fehlgeschlagen','canceled'=>'Anruf abgebrochen','completed'=>'Gespräch ohne Antwort beendet', default=>'Nicht erreicht ('.mb_substr($callStatus,0,20).')'};
        if($att<$s['max_attempts']){
            $next=cb_next_window($s,$now->modify('+'.$s['retry_min'].' minutes')) ?? $now->modify('+'.$s['retry_min'].' minutes');
            $res='Versuch '.$att.': '.$reason.' – nächster Versuch '.cb_when_label($next,$now);
            $u=$p->prepare("UPDATE callbacks SET status='scheduled', preferred_at=?, result=?, updated_at=NOW() WHERE id=? AND account_id=? AND status='calling'"); $u->execute([$next->format('Y-m-d H:i:00'),$res,$cbId,$acc]);
            if($u->rowCount()>0) cb_log_append($acc,$cbId,$log+['note'=>$res]);
        } else {
            $res='Versuch '.$att.': '.$reason.' – keine weiteren Versuche (höchstens '.$s['max_attempts'].'). Bitte persönlich zurückrufen.';
            $u=$p->prepare("UPDATE callbacks SET status='failed', result=?, updated_at=NOW() WHERE id=? AND account_id=? AND status='calling'"); $u->execute([$res,$cbId,$acc]);
            if($u->rowCount()>0){ cb_log_append($acc,$cbId,$log+['note'=>$res]); try{ cb_notify_result(cb_get($acc,$cbId),$s,'failed'); }catch(Throwable $e){ error_log('CH cb_notify_result: '.$e->getMessage()); } }
        }
    }catch(Throwable $e){ error_log('CH cb_on_call_status: '.$e->getMessage()); }
}

/* ======================================================================= Werkzeuge & System-Hinweis */
function cb_tools(): array {
    return [[
      'name'=>'request_callback',
      'description'=>'Rückrufwunsch verbindlich eintragen – wenn der Kunde zurückgerufen werden möchte, du etwas nicht beantworten kannst oder es dringend ist. Vorher Name, Telefonnummer (am Telefon/WhatsApp ist sie bekannt – nur bestätigen) und Wunschzeit kurz bestätigen lassen. Das Ergebnis (Feld message) sagt, wer wann zurückruft.',
      'input_schema'=>['type'=>'object','properties'=>[
        'name'=>['type'=>'string','description'=>'Vor- und Nachname des Kunden'],
        'phone'=>['type'=>'string','description'=>'Rückrufnummer mit Vorwahl, z. B. 0170 1234567 oder +49 170 1234567. Am Telefon/WhatsApp weglassen = bekannte Nummer des Kunden.'],
        'topic'=>['type'=>'string','description'=>'Anliegen in einem Satz, z. B. „Besichtigung der Wohnung LQ-2041, Frage zur Kaution“'],
        'preferred_time'=>['type'=>'string','description'=>'Wunschzeit: „so schnell wie möglich“ (Standard), „heute 16 Uhr“, „morgen vormittag“, „Freitag nachmittag“ oder „YYYY-MM-DD HH:MM“'],
        'urgency'=>['type'=>'string','enum'=>['normal','dringend']],
        'callback_by'=>['type'=>'string','enum'=>['team','assistant'],'description'=>'team = ein Mitarbeiter ruft an (Fragen, die du nicht beantworten kannst, Beschwerden, ausdrücklicher Wunsch nach einem Menschen); assistant = der digitale Assistent ruft selbst an (nur wenn KI-Rückruf aktiv ist). Weglassen = Standard des Betriebs.'],
        'details'=>['type'=>'object','description'=>'Optionale Zusatzangaben als Schlüssel/Wert, z. B. {"Objekt":"LQ-2041","Budget":"bis 1.300 € warm"}','additionalProperties'=>['type'=>'string']]],
      'required'=>['name','topic']]]];
}
function cb_exec(int $acc, string $name, array $in, string $channel, string $from=''): array {
    try{
        if($name!=='request_callback') return ['error'=>'unknown_tool','hint'=>'Dieses Werkzeug gibt es nicht.'];
        $in=cb_scalars($in,['details']);
        $src=['chat'=>'chat','widget'=>'widget','phone'=>'phone','whatsapp'=>'whatsapp','studio'=>'studio','demo'=>'demo','selftest'=>'studio'][$channel] ?? 'chat';
        $d=['name'=>$in['name'] ?? '','phone'=>$in['phone'] ?? '','topic'=>$in['topic'] ?? '','preferred_time'=>$in['preferred_time'] ?? '','urgency'=>$in['urgency'] ?? '',
            'callback_by'=>in_array($in['callback_by'] ?? '',['team','assistant'],true) ? $in['callback_by'] : '','details'=>is_array($in['details'] ?? null) ? $in['details'] : [],
            'consent'=>1,'_channel'=>$channel,'_strict'=>1];
        $r=cb_create($acc,$d,$src,in_array($channel,['phone','whatsapp'],true) ? $from : '');
        if(isset($r['error'])) return $r;
        unset($r['id']);
        $r['hint']=trim(($r['hint'] ?? '').' Dem Kunden genau das zusagen, was in message steht – nichts anderes versprechen.'.($channel==='phone' ? ' Am Telefon keine Codes oder Links vorlesen.' : ' Den Code nur nennen, wenn der Kunde danach fragt.'));
        return $r;
    }catch(Throwable $e){ error_log('CH cb_exec '.$name.': '.$e->getMessage()); return ['error'=>'internal_error','hint'=>'Technischer Fehler beim Eintragen – Name, Nummer und Anliegen zusammenfassen und auf die Kontaktdaten aus der Wissensbasis verweisen.']; }
}
function cb_system_hint(int $acc, string $channel='chat'): string {
    $s=cb_settings($acc); if(!cb_enabled($acc)) return ''; $now=cb_now($s); $ai=$s['ai_callback']===1;
    $h="\n\nRÜCKRUF (aktiv) – Rückrufwünsche werden über request_callback eingetragen. Jetzt: ".CB_WDL[(int)$now->format('w')].', '.$now->format('d.m.Y, H:i')." Uhr (".$s['tz']."). "
      ."Rückrufzeiten: ".cb_window_text($s).". ".($ai ? 'KI-Rückruf ist eingeschaltet: Der digitale Assistent ruft selbst zurück (callback_by „assistant“, Standard); für Fragen, die nur ein Mensch klären kann, Beschwerden oder auf ausdrücklichen Wunsch callback_by „team“.' : 'Zurückgerufen wird vom Team.')."
REGELN – RÜCKRUF: Wenn der Kunde zurückgerufen werden möchte, du etwas nicht beantworten kannst oder es dringend ist: request_callback mit Name, Telefonnummer (am Telefon/WhatsApp ist die Nummer bekannt – nur bestätigen, nicht neu erfragen), Anliegen in einem Satz und Wunschzeit (z. B. „heute 16 Uhr“, „morgen vormittag“; ohne Angabe = so schnell wie möglich). Vorher Name, Nummer und Wunschzeit kurz bestätigen lassen. Dringend (Notfall, Frist, Schaden) → urgency „dringend“. Danach sagen, wer wann zurückruft (Feld message) – nichts anderes versprechen; liegt die Wunschzeit außerhalb der Rückrufzeiten, verschiebt das Werkzeug sie (Feld hint) – das dem Kunden so sagen. Nur Rückrufe eintragen, die der Kunde selbst wünscht – nie für Dritte.";
    if($channel==='phone') $h.="\nAm Telefon: keine Links oder URLs vorlesen, keine Codes buchstabieren; die Rufnummer in Dreiergruppen wiederholen.";
    elseif($channel==='whatsapp') $h.="\nWhatsApp: Die Nummer des Kunden ist bekannt und gilt als Rückrufnummer, außer er nennt eine andere.";
    elseif($ai) $h.="\nBeim KI-Rückruf darauf hinweisen, dass der digitale Assistent anruft.";
    return $h;
}

/* ======================================================================= Studio-REST: /v1/callbacks… */
function cb_api_settings(int $aid): array {
    $s=cb_settings($aid); $rd=cb_readiness($aid,$s); $company=cb_company($aid,$s);
    return ['settings'=>cb_settings_public($s),'agents'=>cb_agents($aid),'links'=>cb_links($aid),'twilio_ready'=>$rd['twilio_ready'],'readiness'=>$rd,'stats'=>cb_stats($aid),
        'company'=>$company,'consent_text'=>cb_consent_text($company,$s['ai_callback']===1),'form_active'=>cb_public_ok($aid,$s['plan']),
        'statuses'=>CB_STATUS_LABELS,'sources'=>CB_SOURCES,'now'=>cb_now($s)->format('Y-m-d H:i')];
}
function cb_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if($route!=='callbacks' && strpos($route,'callbacks/')!==0) return null;
    cb_bootstrap(); $G=cb_scalars($_GET); $body=cb_scalars($body,['call_window','details']);
    $routes=['GET callbacks?scope=open|done|all','GET callbacks/settings','POST callbacks/settings','POST callbacks/new','POST callbacks/update','POST callbacks/call','GET callbacks/calls?id='];
    try{
        switch($route){
            case 'callbacks':
                if($method!=='GET') break; $sc=(string)($G['scope'] ?? 'open'); if(!in_array($sc,['open','done','all'],true)) $sc='open';
                return [200,['scope'=>$sc,'callbacks'=>cb_list($aid,$sc),'stats'=>cb_stats($aid),'now'=>cb_now(cb_settings($aid))->format('Y-m-d H:i')]];
            case 'callbacks/settings':
                if($method==='GET') return [200,cb_api_settings($aid)];
                if($method==='POST'){
                    $fn=trim((string)($body['from_number'] ?? '')); if($fn!=='' && cb_phone($fn)===null) return [422,['error'=>'bad_from_number','message'=>'Absendernummer ungültig – bitte im Format +49 221 1234567 angeben oder leer lassen.']];
                    if(isset($body['plan']) && $body['plan']!=='' && !in_array((string)$body['plan'],cb_account_plans($aid),true)) return [422,['error'=>'bad_plan','message'=>'Dieser Agent ist im Konto nicht eingerichtet.']];
                    cb_settings_save($aid,$body); return [200,['ok'=>true]+cb_api_settings($aid)];
                }
                break;
            case 'callbacks/new':
                if($method!=='POST') break;
                foreach(array_keys($body) as $k) if(str_starts_with((string)$k,'_')) unset($body[$k]);
                $r=cb_create($aid,$body+['_strict'=>1],'studio');
                if(isset($r['error'])) return [422,$r+['message'=>$r['hint'] ?? $r['error']]];
                $row=cb_get($aid,(int)$r['id']); return [200,['ok'=>true,'message'=>$r['message'],'hint'=>$r['hint'] ?? null,'callback'=>$row ? cb_item($row) : null]];
            case 'callbacks/update':
                if($method!=='POST') break;
                $r=cb_update($aid,(int)($body['id'] ?? 0),(string)($body['status'] ?? ''),array_key_exists('result',$body) ? (string)$body['result'] : null);
                return [isset($r['error']) ? ($r['error']==='not_found' ? 404 : 422) : 200,isset($r['error']) ? $r+['message'=>$r['hint']] : $r];
            case 'callbacks/call':
                if($method!=='POST') break;
                $r=cb_call_now($aid,(int)($body['id'] ?? 0),cb_truthy($body['force'] ?? 0));
                if(!isset($r['error'])){ $row=cb_get($aid,(int)($body['id'] ?? 0)); return [200,$r+['message'=>'Anruf wird aufgebaut – der Assistent ruft jetzt an.','callback'=>$row ? cb_item($row) : null]]; }
                $code=['not_found'=>404,'demo'=>403,'already_closed'=>409,'already_calling'=>409,'outside_window'=>409,'max_attempts'=>409,'busy'=>409,'twilio_error'=>502][$r['error']] ?? 422;
                return [$code,$r+['message'=>$r['hint'] ?? $r['error']]];
            case 'callbacks/calls':
                if($method!=='GET') break; $c=cb_calls($aid,(int)($G['id'] ?? 0));
                return $c ? [200,$c] : [404,['error'=>'not_found','message'=>'Rückruf nicht gefunden.']];
            default: return [404,['error'=>'not_found','routes'=>$routes]];
        }
    }catch(Throwable $e){ error_log('CH cb_api '.$route.': '.$e->getMessage()); return [500,['error'=>'server_error','message'=>'Interner Fehler – bitte erneut versuchen.']]; }
    return [405,['error'=>'method_not_allowed','routes'=>$routes]];
}
