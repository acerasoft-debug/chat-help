<?php
/**
 * ChatHelp — Telefon-Agent (Twilio Voice Webhook → TwiML)
 * =======================================================
 * Twilio ruft diese URL bei jedem Anruf auf:  https://chat-help.com/chat/phone-webhook.php?key=wk_…
 *   • key = widget_key des Agenten (agent_configs, phone_on=1); Plan muss aktiv sein.
 *   • 1. Aufruf (kein SpeechResult): Begrüßung (phone_greeting) + <Gather input="speech">.
 *   • Folgeaufrufe: SpeechResult → KI (ch_agent_system + Telefonregeln) → <Say> Antwort → erneut <Gather>.
 *   • Antwort enthält [WEITERLEITEN] → <Dial> phone_forward (wenn gesetzt), sonst Rückruf-Zusage; [ENDE] → <Hangup>.
 *   • Status-Callback (CallStatus completed/…): Anruf abschließen.
 *   • Tokens: CH_PRICING['phone_turn'] je Antwort vom Konto des Agent-Besitzers; Transkript in phone_calls.
 * Sicherheit: wenn in db.php TWILIO_AUTH_TOKEN definiert ist, wird X-Twilio-Signature geprüft (sonst nur Schlüssel).
 * Einrichtung: Twilio-Nummer → Voice → "A call comes in": Webhook (POST) auf obige URL; Status-Callback dieselbe URL.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
th_nocache();
header('Content-Type: text/xml; charset=UTF-8');

function twiml(string $inner): void { echo '<?xml version="1.0" encoding="UTF-8"?><Response>'.$inner.'</Response>'; exit; }
function x(string $s): string { return htmlspecialchars($s, ENT_XML1|ENT_QUOTES, 'UTF-8'); }
/**
 * Natürliche Stimme je Sprache (Twilio-Namen). Standard: Amazon Polly „Generative“ (menschlichste Klasse), männliche Variante = Suffix -m in phone_voice (z. B. de-DE-m).
 * db.php: TWILIO_VOICE (fester Name für alles) · TWILIO_VOICE_TIER='neural' (falls Generative im Twilio-Konto nicht verfügbar ist → Fehler 13520).
 */
function voice_for(string $lang): string {
    if(defined('TWILIO_VOICE') && TWILIO_VOICE) return TWILIO_VOICE;
    $g=$GLOBALS['ph_gender'] ?? 'f'; $tier=(defined('TWILIO_VOICE_TIER') && TWILIO_VOICE_TIER==='neural') ? 'Neural' : 'Generative';
    $V=[ // [weiblich generativ, weiblich neural, männlich]
      'de-DE'=>['Polly.Vicki-Generative','Polly.Vicki-Neural','Polly.Daniel-Neural'],
      'en-US'=>['Polly.Joanna-Generative','Polly.Joanna-Neural','Polly.Matthew-Generative'],
      'en-GB'=>['Polly.Amy-Generative','Polly.Amy-Neural','Polly.Arthur-Neural'],
      'fr-FR'=>['Polly.Lea-Generative','Polly.Lea-Neural','Polly.Remi-Neural'],
      'es-ES'=>['Polly.Lucia-Generative','Polly.Lucia-Neural','Polly.Sergio-Neural'],
      'it-IT'=>['Polly.Bianca-Generative','Polly.Bianca-Neural','Polly.Adriano-Neural'],
      'nl-NL'=>['Polly.Laura-Neural','Polly.Laura-Neural','Polly.Laura-Neural'],
      'pl-PL'=>['Polly.Ola-Neural','Polly.Ola-Neural','Polly.Ola-Neural'],
      'tr-TR'=>['Polly.Filiz','Polly.Filiz','Polly.Filiz'],
      'ru-RU'=>['Polly.Tatyana','Polly.Tatyana','Polly.Maxim']];
    $row=$V[$lang] ?? $V['de-DE'];
    if($g==='m') return $tier==='Neural' ? str_replace('-Generative','-Neural',$row[2]) : $row[2];
    return $tier==='Neural' ? $row[1] : $row[0];
}
/** Gesprochener Text → SSML: Sätze mit kurzen Atempausen, damit es nach Mensch klingt statt nach Durchlesen. */
function say(string $text, string $lang): string {
    $v=voice_for($lang); $text=trim(preg_replace('/\s+/',' ',$text));
    if(!str_starts_with($v,'Polly.')) return '<Say voice="'.x($v).'" language="'.x($lang).'">'.x($text).'</Say>';
    $parts=preg_split('/(?<=[.!?…])\s+(?=[A-ZÄÖÜ0-9„"(])/u',$text) ?: [$text]; $ssml='';
    foreach($parts as $i=>$p){ $p=trim($p); if($p==='') continue; $ssml.=($ssml!==''?'<break time="'.(str_ends_with($p,'?')?'280':'360').'ms"/>':'').'<s>'.x($p).'</s>'; }
    return '<Say voice="'.x($v).'" language="'.x($lang).'">'.$ssml.'</Say>';
}
/** Zuhören: Standard-Spracherkennung (alle Sprachen), Ende bei natürlicher Sprechpause, Anrufer darf jederzeit unterbrechen (Twilio bargeIn = Standard).
 *  db.php: TWILIO_SPEECH_MODEL (z. B. 'phone_call') nur setzen, wenn das Modell für die Sprache freigegeben ist. */
function gather(string $inner, string $lang, string $action, string $hints=''): string {
    $model=(defined('TWILIO_SPEECH_MODEL') && TWILIO_SPEECH_MODEL) ? ' speechModel="'.x(TWILIO_SPEECH_MODEL).'"' : '';
    return '<Gather input="speech" language="'.x($lang).'"'.$model.' speechTimeout="auto" actionOnEmptyResult="true" profanityFilter="false"'.($hints!==''?' hints="'.x(mb_substr($hints,0,480)).'"':'').' action="'.x($action).'" method="POST">'.$inner.'</Gather>';
}
/** Twilio-Signatur: Base64(HMAC-SHA1(angefragte URL + sortierte POST-Parameter, AuthToken)) – gilt auch für unsere Redirect-URLs (&w=…). */
function twilio_sig_ok(): bool {
    if(!defined('TWILIO_AUTH_TOKEN') || !TWILIO_AUTH_TOKEN) return true;
    $sig=$_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? ''; if($sig==='') return false;
    $data='https://chat-help.com'.($_SERVER['REQUEST_URI'] ?? ''); $p=$_POST; ksort($p); foreach($p as $k=>$v) $data.=$k.$v;
    return hash_equals(base64_encode(hash_hmac('sha1',$data,TWILIO_AUTH_TOKEN,true)),$sig);
}
/** Kurze menschliche Überbrückung, wenn das Nachschlagen (Kalender, Karte, Adresse) etwas dauert. */
function filler(string $lang): string {
    $F=['de'=>['Einen kleinen Moment, ich schaue kurz nach.','Mhm, einen Augenblick bitte.','Ich prüfe das gerade für Sie, einen Moment.','Gleich hab ich es, einen kleinen Moment.'],
        'en'=>['One moment, let me check that for you.','Just a second, I am looking that up.'],'tr'=>['Bir saniye, hemen bakıyorum.','Kısa bir an lütfen, kontrol ediyorum.'],
        'fr'=>['Un petit instant, je vérifie.','Une seconde, je regarde.'],'es'=>['Un momento, lo compruebo.','Un segundo, lo estoy mirando.'],'it'=>['Un attimo, controllo subito.','Un secondo, verifico.']];
    $l=$F[substr($lang,0,2)] ?? $F['en']; return $l[array_rand($l)];
}

$key=(string)($_GET['key'] ?? '');
$self='https://chat-help.com/chat/phone-webhook.php?key='.$key;

if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Content-Type: text/plain; charset=UTF-8'); exit("ChatHelp Telefon-Agent – Twilio Voice Webhook. Diese URL als Voice-Webhook (POST) deiner Twilio-Nummer eintragen.\n"); }

th_bootstrap();
$cfg=th_phone_cfg($key);
if(!$cfg) twiml(say('Dieser Telefon-Assistent ist derzeit nicht aktiv. Auf Wiederhören.','de-DE').'<Hangup/>');
$pl=ch_plan($cfg['plan']); $aid=(int)$cfg['account_id'];
$lang='de-DE'; $GLOBALS['ph_gender']='f';
if(preg_match('/^([a-z]{2}-[A-Z]{2})(?:-([mf]))?$/',(string)$cfg['phone_voice'],$mv)){ $lang=$mv[1]; $GLOBALS['ph_gender']=$mv[2] ?? 'f'; }
if(!$pl || !th_plan_active($aid,$pl['id'])) twiml(say('Dieser Telefon-Assistent ist derzeit nicht aktiv. Auf Wiederhören.',$lang).'<Hangup/>');
if(!twilio_sig_ok()){ http_response_code(403); twiml('<Hangup/>'); }

$sid=preg_replace('/[^A-Za-z0-9]/','',(string)($_POST['CallSid'] ?? '')); if($sid==='') twiml('<Hangup/>');
$from=mb_substr((string)($_POST['From'] ?? ''),0,32); $to=mb_substr((string)($_POST['To'] ?? ''),0,32);
$status=(string)($_POST['CallStatus'] ?? ''); $speech=trim((string)($_POST['SpeechResult'] ?? ''));
$company=$cfg['company'] ?: 'unserem Unternehmen';
$hints=implode(', ',array_filter([$cfg['company'],'Termin','Rückruf','Bestellung','Lieferung','Abholung','Mitarbeiter','Öffnungszeiten']));   // Erkennungshilfe für Twilio

/* Anruf beendet (Status-Callback) */
if(in_array($status,['completed','busy','failed','no-answer','canceled'],true)){
    th_call_update($sid,['status'=>$status,'ended_at'=>date('Y-m-d H:i:s')]); twiml('');
}

$call=th_call($sid) ?: th_call_start($aid,$pl['id'],$sid,$from,$to);
$tr=json_decode((string)($call['transcript'] ?? '[]'),true) ?: [];
$turns=(int)($call['turns'] ?? 0); $silent=(int)($call['silent'] ?? 0);

/* Antwort der KI abholen (Warteschleife): Die KI rechnet nach dem Abschicken der Antwort an Twilio im Hintergrund weiter.
   Ist sie nach ~2,5 s fertig, antwortet der Agent sofort; sonst sagt er kurz „Einen Moment …“ und holt die Antwort danach ab. Kein 15-s-Timeout von Twilio. */
$wait=isset($_GET['w']) ? max(0,(int)$_GET['w']) : null;
if($wait!==null){
    $deadline=microtime(true)+($wait===0?2.6:8.0); $pend=null;
    do{ $pend=json_decode((string)(th_call($sid)['pending'] ?? ''),true); if(($pend['state'] ?? '')==='ready') break; usleep(250000); } while(microtime(true)<$deadline);
    if(($pend['state'] ?? '')==='ready'){ th_call_update($sid,['pending'=>null]); render_reply((array)$pend['reply'],$cfg,$lang,$self,$hints,$to); }
    if(($pend['state'] ?? '')!=='work' || $wait>=4 || time()-(int)($pend['at'] ?? 0)>45){
        th_call_update($sid,['pending'=>null]);
        twiml(gather(say('Entschuldigung, das hat gerade zu lange gedauert. Könnten Sie mir Ihr Anliegen bitte noch einmal kurz sagen?',$lang),$lang,$self,$hints));
    }
    twiml(($wait===0 ? say(filler($lang),$lang) : '<Pause length="1"/>').'<Redirect method="POST">'.x($self.'&w='.($wait+1)).'</Redirect>');
}


/* Begrüßung */
if($speech==='' && $turns===0 && !$tr){
    $h=(int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('G'); $tag=$h<11?'Guten Morgen':($h<18?'Guten Tag':'Guten Abend');
    $greet=$cfg['phone_greeting'] ?: "$tag, $company, Sie sprechen mit dem digitalen Assistenten. Was kann ich für Sie tun?";
    $tr[]=['role'=>'assistant','content'=>$greet]; th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE)]);
    twiml(gather(say($greet,$lang),$lang,$self,$hints));
}
/* Stille */
if($speech===''){
    $silent++;
    if($silent>=2){ th_call_update($sid,['silent'=>$silent,'outcome'=>'silent','status'=>'completed','ended_at'=>date('Y-m-d H:i:s')]); twiml(say('Ich höre Sie leider nicht. Rufen Sie gern jederzeit noch einmal an. Auf Wiederhören!',$lang).'<Hangup/>'); }
    th_call_update($sid,['silent'=>$silent]);
    twiml(gather(say('Hallo? Sind Sie noch dran? Ich höre zu.',$lang),$lang,$self,$hints));
}

/* Antwort der KI */
$tr[]=['role'=>'user','content'=>mb_substr($speech,0,1500)];
$cost=CH_PRICING['phone_turn'];
if(th_balance($aid) < $cost){
    th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE),'outcome'=>'no_tokens']);
    $fw=$cfg['phone_forward'];
    twiml(say('Einen Moment bitte, ich verbinde Sie mit einem Mitarbeiter.',$lang).($fw?'<Dial timeout="25">'.x($fw).'</Dial>':'').say('Leider ist gerade niemand erreichbar. Bitte versuchen Sie es später erneut. Auf Wiederhören.',$lang).'<Hangup/>');
}
$sys=ch_agent_system($pl,$cfg,substr($lang,0,2)).CH_PHONE_RULES;
if(!empty($cfg['phone_hours'])) $sys.="\nTelefonische Erreichbarkeit des Teams (für Rückrufe): ".$cfg['phone_hours'];
$sys.="\nAnrufer-Nummer (für Rückrufe, nicht vorlesen): ".($from ?: 'unbekannt').". Aktuelle Zeit (Deutschland): ".(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('l, d.m.Y H:i').".";
$msgs=array_slice(array_values(array_filter($tr,fn($m)=>in_array($m['role'],['user','assistant'],true))),-16);
if($msgs[0]['role']!=='user') array_shift($msgs);   // Claude: erste Nachricht muss vom Nutzer sein
require_once __DIR__.'/order-lib.php';   // Werkzeuge: Terminbuchung (appt_on) + Bestellannahme (order_on)
$tk=ord_toolkit($aid,'phone',(string)$from);
if($tk['tools']){ $sys.=$tk['hint'];
    if($tk['appt'])  $sys.="\nAm Telefon: Datum und Uhrzeit immer ausgesprochen wiederholen (z. B. „Donnerstag, den dreiundzwanzigsten Oktober um fünfzehn Uhr“).";
    if($tk['order']) $sys.="\nAm Telefon: Positionen einzeln bestätigen, Adresse mit Hausnummer und Postleitzahl wiederholen, am Ende Gesamtsumme und Zeit nennen. Die Rufnummer des Anrufers gilt als Kontaktnummer, falls er keine andere nennt.";
}
/** Denkt, bucht/bestellt über Werkzeuge, schreibt Transkript + Tokens. Gibt die gesprochene Antwort zurück. */
$work=function() use($sys,$msgs,$tk,$tr,$turns,$cost,$aid,$pl,$sid,$call,$cfg): array {
    if($tk['tools']){ $reply=th_ai_tools($sys,$msgs,$tk['tools'],$tk['exec'],320,40); $cost+=2*count(th_ai_info()['tool_calls'] ?? []); }
    else $reply=th_ai($sys,$msgs,300,25);
    if($reply===null) $reply='Entschuldigung, da war kurz eine Störung in der Leitung. Könnten Sie das bitte noch einmal sagen?';
    $forward=str_contains($reply,'[WEITERLEITEN]'); $end=str_contains($reply,'[ENDE]');
    $spoken=trim(preg_replace('/\s+/',' ',str_replace(['[WEITERLEITEN]','[ENDE]','*','#','`'],'',$reply)));
    if($spoken==='') $spoken=$forward?'Einen Moment bitte, ich verbinde Sie.':'Vielen Dank für Ihren Anruf. Auf Wiederhören.';
    $tr[]=['role'=>'assistant','content'=>$spoken]; $turns++;
    th_debit($aid,$cost,'phone:'.$pl['id'],$sid.':'.$turns);
    th_log_usage($aid,'phone',$pl['sector'],$cost,0);
    $upd=['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE),'turns'=>$turns,'silent'=>0,'tokens'=>(int)$call['tokens']+$cost];
    if($forward) $upd['outcome']=$cfg['phone_forward']?'forwarded':'callback';
    elseif($end){ $upd['outcome']='done'; $upd['status']='completed'; $upd['ended_at']=date('Y-m-d H:i:s'); }
    elseif($turns>=25) $upd['outcome']='maxturns';
    th_call_update($sid,$upd);
    return ['spoken'=>$spoken,'forward'=>$forward,'end'=>$end,'turns'=>$turns];
};
/* Mit Werkzeugen (Kalender/Karte/Adresse) kann es länger dauern → Antwort an Twilio sofort, KI im Hintergrund, Abholung per Redirect */
if($tk['tools']){
    th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE),'pending'=>json_encode(['state'=>'work','at'=>time()])]);
    $tw='<?xml version="1.0" encoding="UTF-8"?><Response><Redirect method="POST">'.x($self.'&w=0').'</Redirect></Response>';
    if(th_finish_response($tw,'text/xml; charset=UTF-8')){
        try{ $res=$work(); }catch(Throwable $e){ $res=['spoken'=>'Entschuldigung, da ist gerade etwas schiefgelaufen. Können Sie das bitte noch einmal sagen?','forward'=>false,'end'=>false,'turns'=>$turns]; }
        th_call_update($sid,['pending'=>json_encode(['state'=>'ready','at'=>time(),'reply'=>$res],JSON_UNESCAPED_UNICODE)]);
        exit;
    }
    th_call_update($sid,['pending'=>null]);   // Server kann nicht im Hintergrund weiterrechnen → synchron
}
render_reply($work(),$cfg,$lang,$self,$hints,$to);

/** TwiML für eine fertige Antwort: weiterleiten, auflegen oder weiter zuhören. */
function render_reply(array $r, array $cfg, string $lang, string $self, string $hints, string $to): void {
    $spoken=(string)($r['spoken'] ?? '');
    if(!empty($r['forward'])){
        $fw=$cfg['phone_forward'];
        if($fw) twiml(say($spoken,$lang).'<Dial timeout="25" callerId="'.x($to).'">'.x($fw).'</Dial>'.say('Hm, gerade ist leider niemand frei. Ich habe alles notiert – wir rufen Sie so schnell wie möglich zurück. Auf Wiederhören!',$lang).'<Hangup/>');
        twiml(say($spoken.' Ich habe Ihr Anliegen notiert, das Team ruft Sie so schnell wie möglich zurück. Auf Wiederhören!',$lang).'<Hangup/>');
    }
    if(!empty($r['end'])) twiml(say($spoken,$lang).'<Pause length="1"/><Hangup/>');
    if((int)($r['turns'] ?? 0)>=25) twiml(say($spoken.' Vielen Dank für das Gespräch, ich wünsche Ihnen noch einen schönen Tag. Auf Wiederhören!',$lang).'<Hangup/>');
    twiml(gather(say($spoken,$lang),$lang,$self,$hints));
}
