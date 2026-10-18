<?php
/**
 * ChatHelp — Anruf-Assistent „ruft für dich an“ (Plan privat-anruf, Modul „privat“, Präfix pv_)
 * ==============================================================================================
 * Ablauf (nur im Studio-Chat bzw. in der Demo, nie während eines Telefonats – CH_MODULE_CHANNELS):
 *   1) prepare_call  – Auftrag (Unternehmen, Nummer, Anliegen, Name) prüfen und als „vorbereitet“ speichern → Zusammenfassung + Code.
 *   2) place_call    – erst nach ausdrücklicher Bestätigung der Person in einer EIGENEN, späteren Nachricht (serverseitig geprüft:
 *                      Auftrag stammt aus einer früheren Anfrage, die letzte Nutzernachricht ist ein klares Ja ohne Verneinung).
 *                      Dann ein einziger ausgehender Anruf über den vorhandenen KI-Rückruf (callback-lib: cb_call_now → phone-webhook.php?cb=…),
 *                      Ansage „Ich bin der KI-Assistent von <Name> …“ (pv_call_context), kein automatischer Wiederholversuch.
 *   3) get_call_result – Status, Ergebnis und die letzten Sätze des Transkripts.
 * Grenzen: nur Unternehmen/Einrichtungen, keine Notruf-/Sondernummern, Anrufzeiten Mo–Sa 8–20 Uhr (Europe/Berlin), höchstens 10 Aufträge je Tag.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';

const PV_PLAN = 'privat-anruf';
const PV_HOURS = ['from'=>'08:00','to'=>'20:00','days'=>[1,2,3,4,5,6]];   // Mo–Sa
const PV_TARGET_KINDS = ['praxis'=>'Arzt-/Zahnarzt-/Tierarztpraxis','salon'=>'Friseur/Kosmetik','hotel'=>'Hotel/Unterkunft','restaurant'=>'Restaurant','werkstatt'=>'Werkstatt/Handwerk','amt'=>'Behörde/Amt (Telefonservice)','geschaeft'=>'Geschäft/Dienstleister'];
/** Ja-Wörter einer Bestätigung und Wörter, die sie aufheben. */
const PV_YES = '/\b(ja|jawohl|jep|jo|ok|okay|okey|klar|gern|gerne|bitte|einverstanden|passt|mach|machen|los|ruf|anrufen|bestätig\w*|yes|evet|tamam)\b/iu';
const PV_NO  = '/\b(nein|nö|nicht|kein|keine|stopp|stop|warte|warten|später|abbrechen|no|hayır)\b/iu';

function pv_bootstrap(): void {
    static $done=false; if($done) return; $done=true;
    th_db()->exec("CREATE TABLE IF NOT EXISTS privat_calls (id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, code CHAR(7) NOT NULL,
        status VARCHAR(12) DEFAULT 'prepared', target_name VARCHAR(120) DEFAULT '', target_kind VARCHAR(20) DEFAULT '', target_phone VARCHAR(20) DEFAULT '',
        purpose VARCHAR(400) DEFAULT '', wishes VARCHAR(300) DEFAULT '', user_name VARCHAR(120) DEFAULT '', callback_number VARCHAR(20) DEFAULT '',
        callback_id INT NULL, result VARCHAR(500) DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, confirmed_at DATETIME NULL,
        UNIQUE KEY uq_code (code), INDEX(account_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function pv_enabled(int $acc): bool { return $acc===0 || (function_exists('ch_plan_active_any') && ch_plan_active_any($acc,PV_PLAN,'')); }
function pv_line($v, int $max): string { $t=is_scalar($v) ? (string)$v : ''; $t=trim(preg_replace('/\s+/u',' ',strip_tags($t)) ?? ''); return mb_substr($t,0,$max); }
function pv_phone(string $raw): ?string { $e=th_e164($raw); return $e!=='' ? $e : null; }
/** Notruf-, Sonder- und Mehrwertnummern ausschließen. */
function pv_blocked_number(string $e164): bool {
    $d=preg_replace('/\D/','',$e164) ?? '';
    if(preg_match('/^49(110|112|115|116|118|19|900|137|180|0)/',$d)) return true;   // Notruf, 115/116xxx, Auskunft, Mehrwert, Massenverkehr
    return strlen($d)<9;
}
function pv_code(): string { $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $c='AN-'; for($i=0;$i<4;$i++) $c.=$a[random_int(0,strlen($a)-1)]; return $c; }
function pv_get(int $acc, string $code): ?array { pv_bootstrap(); $s=th_db()->prepare("SELECT * FROM privat_calls WHERE account_id=? AND code=?"); $s->execute([$acc,strtoupper(trim($code))]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function pv_in_hours(?DateTimeImmutable $now=null): bool {
    if($now===null && defined('PV_TEST_NOW') && in_array(PHP_SAPI,['cli','cli-server'],true)) $now=new DateTimeImmutable((string)PV_TEST_NOW);   // nur lokale Tests
    $now=($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Europe/Berlin'));
    return in_array((int)$now->format('N'),PV_HOURS['days'],true) && $now->format('H:i')>=PV_HOURS['from'] && $now->format('H:i')<PV_HOURS['to'];
}
function pv_summary(array $r): string {
    return 'Ich rufe '.$r['target_name'].' unter '.$r['target_phone'].' an, stelle mich als KI-Assistent von '.$r['user_name'].' vor und frage: '.$r['purpose']
        .($r['wishes']!=='' ? ' (Wunsch: '.$r['wishes'].')' : '').'. Einen angebotenen Termin wiederhole ich und sage, dass '.$r['user_name'].' ihn noch bestätigt'
        .($r['callback_number']!=='' ? '; für Rückfragen nenne ich '.$r['callback_number'] : '').'. Verbindlich zusagen, bezahlen oder Verträge schließen werde ich nicht.';
}

function pv_tools(): array {
    return [
      ['name'=>'prepare_call','description'=>'Anruf-Auftrag vorbereiten (ruft NOCH NICHT an). Aufrufen, sobald Unternehmen, Telefonnummer, Anliegen und der Name der Person bekannt sind. Danach die Zusammenfassung zeigen und ausdrücklich fragen, ob jetzt angerufen werden soll.',
       'input_schema'=>['type'=>'object','properties'=>[
         'target_name'=>['type'=>'string','description'=>'Name des Unternehmens bzw. der Einrichtung, z. B. „Salon Luna“'],
         'target_kind'=>['type'=>'string','enum'=>array_keys(PV_TARGET_KINDS),'description'=>'Art des Unternehmens – Privatpersonen werden nicht angerufen'],
         'target_phone'=>['type'=>'string','description'=>'Telefonnummer des Unternehmens mit Vorwahl'],
         'purpose'=>['type'=>'string','description'=>'Anliegen in einem Satz, z. B. „Termin für einen Haarschnitt anfragen“'],
         'wishes'=>['type'=>'string','description'=>'Wunschzeiten bzw. Spielraum, z. B. „Samstag vormittags, notfalls Freitag ab 16 Uhr“'],
         'user_name'=>['type'=>'string','description'=>'Vor- und Nachname der Person, für die angerufen wird'],
         'callback_number'=>['type'=>'string','description'=>'Optional: Nummer der Person für Rückfragen des Unternehmens']],
       'required'=>['target_name','target_kind','target_phone','purpose','user_name']]],
      ['name'=>'place_call','description'=>'Den vorbereiteten Anruf JETZT starten. Nur aufrufen, wenn die Person in ihrer letzten Nachricht ausdrücklich zugestimmt hat (z. B. „Ja, ruf an“) – nie im selben Schritt wie prepare_call, nie ohne neue Zustimmung, jeder Anruf einzeln.',
       'input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string','description'=>'Code aus prepare_call, z. B. AN-7Q2K']],'required'=>['code']]],
      ['name'=>'get_call_result','description'=>'Stand bzw. Ergebnis eines Anruf-Auftrags abrufen (Status, Ergebnis, letzte Sätze des Gesprächs).',
       'input_schema'=>['type'=>'object','properties'=>['code'=>['type'=>'string']],'required'=>['code']]],
    ];
}
function pv_system_hint(int $acc, string $channel='studio'): string {
    return "\n\nANRUF-ASSISTENT (aktiv): Werkzeuge prepare_call → (Zusammenfassung zeigen, ausdrücklich fragen „Soll ich jetzt anrufen?“) → erst nach einem klaren Ja in der NÄCHSTEN Nachricht place_call → später get_call_result. "
        ."Der Server startet den Anruf nur, wenn die letzte Nachricht der Person eine eindeutige Zustimmung ist. Anrufzeiten Mo–Sa 8–20 Uhr. Nur Unternehmen/Einrichtungen, keine Privatpersonen, keine Notrufnummern."
        .($acc===0 ? ' DEMO: Es wird niemand wirklich angerufen.' : '');
}
/** Letzte Nutzernachricht des laufenden Studio-Chats (von tokens-api.php gesetzt). */
function pv_last_user_message(): ?string { $m=$GLOBALS['ch_last_user_message'] ?? null; return is_string($m) ? $m : null; }
/** Eindeutige Zustimmung? (Ja-Wort, keine Verneinung, kurz) */
function pv_is_confirmation(string $t): bool {
    $t=trim(mb_strtolower($t)); if($t==='' || mb_strlen($t)>160) return false;
    return (bool)preg_match(PV_YES,$t) && !preg_match(PV_NO,$t);
}

function pv_exec(int $acc, string $name, array $in, string $channel, string $from=''): array {
    try{
        if(!in_array($channel,['studio','demo'],true)) return ['error'=>'channel_not_allowed','hint'=>'Anruf-Aufträge gibt es nur im Studio-Chat.'];
        pv_bootstrap(); $p=th_db();
        if($name==='prepare_call'){
            $tn=pv_line($in['target_name'] ?? '',120); $kind=(string)($in['target_kind'] ?? ''); $ph=pv_phone((string)($in['target_phone'] ?? ''));
            $pu=pv_line($in['purpose'] ?? '',400); $un=pv_line($in['user_name'] ?? '',120); $wi=pv_line($in['wishes'] ?? '',300);
            $cbn=trim((string)($in['callback_number'] ?? ''))!=='' ? pv_phone((string)$in['callback_number']) : '';
            if(mb_strlen($tn)<2) return ['error'=>'target_required','hint'=>'Welches Unternehmen soll ich anrufen?'];
            if(!isset(PV_TARGET_KINDS[$kind])) return ['error'=>'business_only','hint'=>'Ich rufe nur bei Unternehmen und Einrichtungen an (Praxis, Salon, Hotel, Restaurant, Werkstatt, Amt, Geschäft) – nicht bei Privatpersonen.'];
            if($ph===null) return ['error'=>'phone_invalid','hint'=>'Bitte die Telefonnummer des Unternehmens mit Vorwahl nennen.'];
            if(pv_blocked_number($ph)) return ['error'=>'number_not_allowed','hint'=>'Notruf-, Service- und Mehrwertnummern rufe ich nicht an. Im Notfall bitte selbst 112 bzw. 110 wählen.'];
            if(mb_strlen($pu)<6) return ['error'=>'purpose_required','hint'=>'Worum soll es im Anruf gehen (ein Satz)?'];
            if(mb_strlen($un)<3) return ['error'=>'name_required','hint'=>'Wie heißt du (Vor- und Nachname)? Ich stelle mich als dein KI-Assistent vor.'];
            if($cbn===null) return ['error'=>'callback_invalid','hint'=>'Die Rückrufnummer ist ungültig – bitte mit Vorwahl oder weglassen.'];
            if($acc>0 && !th_rate('pv:'.$acc,10,86400)) return ['error'=>'too_many','hint'=>'Heute wurden schon 10 Anruf-Aufträge vorbereitet – bitte morgen weiter.'];
            $code=''; for($i=0;$i<6;$i++){ $code=pv_code(); try{ $p->prepare("INSERT INTO privat_calls(account_id,code,target_name,target_kind,target_phone,purpose,wishes,user_name,callback_number) VALUES(?,?,?,?,?,?,?,?,?)")
                ->execute([$acc,$code,$tn,$kind,$ph,$pu,$wi,$un,(string)$cbn]); break; }catch(PDOException $e){ if($e->getCode()!=='23000' || $i>=5) throw $e; } }
            $GLOBALS['__pv_prepared'][$code]=1;   // im selben Schritt nicht bestätigbar
            $r=pv_get($acc,$code);
            return ['ok'=>true,'code'=>$code,'summary'=>pv_summary($r),'in_hours'=>pv_in_hours(),
              'hint'=>'Zeig der Person diese Zusammenfassung und frage ausdrücklich: „Soll ich jetzt dort anrufen?“ Erst nach einem klaren Ja in ihrer nächsten Nachricht place_call mit code '.$code.'.'.(pv_in_hours() ? '' : ' Hinweis: Gerade ist außerhalb der Anrufzeiten (Mo–Sa 8–20 Uhr) – anrufen geht erst dann.')];
        }
        if($name==='place_call'){
            $r=pv_get($acc,(string)($in['code'] ?? '')); if(!$r) return ['error'=>'not_found','hint'=>'Diesen Auftrag gibt es nicht – bitte erst prepare_call.'];
            if($r['status']!=='prepared') return ['error'=>'already_used','status'=>$r['status'],'hint'=>'Dieser Auftrag wurde schon ausgeführt oder abgebrochen. Für einen neuen Anruf bitte neu vorbereiten und bestätigen lassen.'];
            if(isset($GLOBALS['__pv_prepared'][$r['code']])) return ['error'=>'confirmation_required','hint'=>'Erst die Zusammenfassung zeigen und auf die ausdrückliche Zustimmung in der nächsten Nachricht warten.'];
            $last=pv_last_user_message();
            if($last===null || !pv_is_confirmation($last)) return ['error'=>'confirmation_required','hint'=>'Die letzte Nachricht ist keine eindeutige Zustimmung. Bitte fragen: „Soll ich jetzt '.$r['target_name'].' anrufen?“ und auf ein klares Ja warten.'];
            if(!pv_in_hours()) return ['error'=>'outside_hours','hint'=>'Anrufe nur Montag bis Samstag zwischen 8 und 20 Uhr – der Auftrag bleibt vorbereitet; danach bitte erneut bestätigen.'];
            $u=$p->prepare("UPDATE privat_calls SET status='confirmed', confirmed_at=NOW() WHERE id=? AND status='prepared'"); $u->execute([(int)$r['id']]);
            if($u->rowCount()===0) return ['error'=>'busy','hint'=>'Der Auftrag wurde gerade geändert.'];
            if($acc===0){ $p->prepare("UPDATE privat_calls SET status='done', result=? WHERE id=?")->execute(['Demo: Es wurde nicht wirklich angerufen.',(int)$r['id']]);
                return ['ok'=>true,'demo'=>true,'code'=>$r['code'],'message'=>'Demo: Hier würde jetzt '.$r['target_name'].' angerufen. Im echten Konto siehst du Ergebnis und Transkript im Studio.']; }
            return pv_start_call($acc,pv_get($acc,$r['code']));
        }
        if($name==='get_call_result'){
            $r=pv_get($acc,(string)($in['code'] ?? '')); if(!$r) return ['error'=>'not_found','hint'=>'Auftrag nicht gefunden.'];
            return ['ok'=>true]+pv_public($acc,$r);
        }
        return ['error'=>'unknown_tool','hint'=>'Dieses Werkzeug gibt es nicht.'];
    }catch(Throwable $e){ error_log('CH pv_exec '.$name.': '.$e->getMessage()); return ['error'=>'internal_error','hint'=>'Technischer Fehler – bitte später erneut versuchen.']; }
}
/** Bestätigten Auftrag als einmaligen KI-Anruf über die Rückruf-Technik starten (kein automatischer Wiederholversuch). */
function pv_start_call(int $acc, array $r): array {
    if(!function_exists('cb_call_now')) return ['error'=>'callback_module_missing','hint'=>'Das Anruf-Modul ist nicht verfügbar.'];
    cb_bootstrap(); $p=th_db();
    /* Absender-Agent: Konfiguration des Anruf-Assistenten (Telefon an); Twilio-Nummer weist das Team bzw. Studio → Verbindungen zu */
    $cfg=th_agent_cfg($acc,PV_PLAN);
    if(empty($cfg['widget_key']) || (int)$cfg['phone_on']!==1) th_agent_cfg_save($acc,PV_PLAN,['phone_on'=>1,'company'=>$cfg['company'] ?: $r['user_name']],true);
    $det=['_privat'=>'1','Auftrag'=>$r['code'],'Im Auftrag von'=>$r['user_name']]+($r['wishes']!=='' ? ['Wunsch'=>$r['wishes']] : [])+($r['callback_number']!=='' ? ['Rückrufnummer'=>$r['callback_number']] : []);
    $p->prepare("INSERT INTO callbacks(account_id,code,plan,name,phone,topic,details,urgency,source,consent,consent_at,consent_text,mode,status) VALUES(?,?,?,?,?,?,?,'normal','studio',1,NOW(),?,'ai','open')")
      ->execute([$acc,substr(str_replace('-','',$r['code']).'X',0,8),PV_PLAN,mb_substr($r['target_name'],0,120),$r['target_phone'],mb_substr($r['purpose'],0,500),json_encode($det,JSON_UNESCAPED_UNICODE),
        'Anruf im Auftrag von '.$r['user_name'].' im Studio ausdrücklich bestätigt (Auftrag '.$r['code'].').']);
    $cid=(int)$p->lastInsertId(); $p->prepare("UPDATE privat_calls SET callback_id=?, status='calling' WHERE id=?")->execute([$cid,(int)$r['id']]);
    $res=cb_call_now($acc,$cid,true);
    if(!empty($res['ok'])) return ['ok'=>true,'code'=>$r['code'],'message'=>'Ich rufe jetzt '.$r['target_name'].' an. Das Ergebnis siehst du gleich hier (frag mich nach dem Stand) und im Studio unter Rückrufe.'];
    /* gescheitert: Eintrag schließen, damit nie ohne neue Bestätigung angerufen wird */
    $p->prepare("UPDATE callbacks SET status='cancelled', result=? WHERE id=? AND account_id=?")->execute([mb_substr('Nicht gestartet: '.($res['hint'] ?? ''),0,500),$cid,$acc]);
    $p->prepare("UPDATE privat_calls SET status='failed', result=? WHERE id=?")->execute([mb_substr((string)($res['hint'] ?? ($res['error'] ?? 'Fehler')),0,500),(int)$r['id']]);
    return ['error'=>(string)($res['error'] ?? 'call_failed'),'hint'=>'Der Anruf konnte nicht gestartet werden: '.($res['hint'] ?? 'unbekannter Fehler').' Der Auftrag ist geschlossen – für einen neuen Versuch bitte neu vorbereiten.'];
}
/** Stand eines Auftrags (Status aus dem Rückruf-Eintrag, Ergebnis, letzte Sätze des Transkripts). */
function pv_public(int $acc, array $r): array {
    $o=['code'=>$r['code'],'status'=>$r['status'],'target'=>$r['target_name'],'purpose'=>$r['purpose'],'result'=>$r['result']];
    if($r['callback_id'] && function_exists('cb_get') && ($c=cb_get($acc,(int)$r['callback_id']))){
        $map=['calling'=>'calling','done'=>'done','failed'=>'failed','cancelled'=>'failed','open'=>'calling','scheduled'=>'calling'];
        $o['status']=$map[$c['status']] ?? $r['status']; $o['result']=(string)$c['result'];
        if($o['status']!==$r['status']) th_db()->prepare("UPDATE privat_calls SET status=?, result=? WHERE id=?")->execute([$o['status'],mb_substr($o['result'],0,500),(int)$r['id']]);
        if($c['call_sid']!==''){ $s=th_db()->prepare("SELECT transcript FROM phone_calls WHERE account_id=? AND call_sid=?"); $s->execute([$acc,$c['call_sid']]); $tr=json_decode((string)$s->fetchColumn(),true) ?: [];
            $o['transcript_tail']=array_slice(array_map(fn($m)=>(($m['role'] ?? '')==='assistant' ? 'Assistent: ' : 'Gegenüber: ').mb_substr((string)($m['content'] ?? ''),0,220),$tr),-6); }
    }
    return $o;
}
/**
 * Kontext für den ausgehenden Anruf (Hook aus cb_call_context): Ansage „Ich bin der KI-Assistent von …“, Gesprächsauftrag, Anrufbeantworter-Text.
 * null = kein Anruf-Assistent-Eintrag.
 */
function pv_call_context(array $r): ?array {
    if((string)($r['plan'] ?? '')!==PV_PLAN) return null; $det=json_decode((string)($r['details'] ?? ''),true) ?: []; if(empty($det['_privat'])) return null;
    $who=(string)($det['Im Auftrag von'] ?? 'meiner Auftraggeberin bzw. meinem Auftraggeber'); $topic=function_exists('cb_speak_topic') ? cb_speak_topic((string)$r['topic']) : (string)$r['topic'];
    $h=(int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('G'); $tag=$h<11 ? 'Guten Morgen' : ($h<18 ? 'Guten Tag' : 'Guten Abend');
    $greet=$tag.', ich bin der KI-Assistent von '.$who.' und rufe in diesem Auftrag an: '.$topic.'. Haben Sie kurz einen Moment?';
    $sys="AUSGEHENDER ANRUF IM AUFTRAG EINER PRIVATPERSON: Du bist der KI-Assistent von ".$who." und rufst bei „".$r['name']."“ an (Auftrag ".($det['Auftrag'] ?? $r['code'])."). Anliegen: ".$r['topic'].'.'
        .(!empty($det['Wunsch']) ? ' Wünsche/Spielraum: '.$det['Wunsch'].'.' : '').(!empty($det['Rückrufnummer']) ? ' Rückrufnummer für Rückfragen (bei Bedarf in Dreiergruppen nennen): '.$det['Rückrufnummer'].'.' : '')
        ."\nABLAUF: Die Begrüßung mit Vorstellung ist gesprochen. 1) Anliegen kurz klären, nach Möglichkeiten fragen. 2) Angebotene Termine/Optionen wiederholen (Tag, Datum, Uhrzeit, Preis falls genannt). "
        ."3) Sagen, dass ".$who." den Termin noch bestätigt bzw. sich meldet – nichts verbindlich buchen, nichts bezahlen, keine Verträge, keine Anzahlung zusagen. 4) Fragt das Gegenüber nach Daten, die du nicht hast: ehrlich sagen, ".$who." meldet sich selbst. "
        ."5) Wird ein Mensch verlangt oder ist es unpassend: höflich bedanken, verabschieden, [ENDE]. 6) Am Ende Ergebnis in einem Satz zusammenfassen, verabschieden, [ENDE]. Nie behaupten, ein Mensch oder ".$who." selbst zu sein. Keine Gesundheitsdetails über das Nötige hinaus. Kein request_callback und keine Buchungs-Werkzeuge verwenden.";
    $vm=$tag.', hier ist der KI-Assistent von '.$who.'. Es geht um: '.$topic.'. '.(!empty($det['Rückrufnummer']) ? 'Ein Rückruf ist gern möglich unter '.(function_exists('cb_speak_number') ? cb_speak_number((string)$det['Rückrufnummer']) : $det['Rückrufnummer']).'. ' : $who.' meldet sich noch einmal selbst. ').'Vielen Dank und auf Wiederhören.';
    return ['account_id'=>(int)$r['account_id'],'callback_id'=>(int)$r['id'],'code'=>(string)$r['code'],'greeting'=>$greet,'system'=>$sys,'voicemail'=>$vm];
}
