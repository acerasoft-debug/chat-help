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
/** Stimme je Sprache (Twilio Amazon-Polly-Namen); TWILIO_VOICE in db.php überschreibt. */
function voice_for(string $lang): string {
    if(defined('TWILIO_VOICE') && TWILIO_VOICE) return TWILIO_VOICE;
    return ['de-DE'=>'Polly.Vicki-Neural','en-US'=>'Polly.Joanna-Neural','en-GB'=>'Polly.Amy-Neural','tr-TR'=>'Polly.Filiz','fr-FR'=>'Polly.Lea-Neural','es-ES'=>'Polly.Lucia-Neural','it-IT'=>'Polly.Bianca-Neural','nl-NL'=>'Polly.Laura-Neural','pl-PL'=>'Polly.Ola-Neural','ru-RU'=>'Polly.Tatyana'][$lang] ?? 'Polly.Vicki-Neural';
}
function say(string $text, string $lang): string { return '<Say voice="'.x(voice_for($lang)).'" language="'.x($lang).'">'.x($text).'</Say>'; }
function gather(string $inner, string $lang, string $action): string {
    return '<Gather input="speech" language="'.x($lang).'" speechTimeout="auto" actionOnEmptyResult="true" action="'.x($action).'" method="POST">'.$inner.'</Gather>';
}
/** Twilio-Signatur: Base64(HMAC-SHA1(url + sortierte POST-Parameter, AuthToken)) */
function twilio_sig_ok(string $url): bool {
    if(!defined('TWILIO_AUTH_TOKEN') || !TWILIO_AUTH_TOKEN) return true;
    $sig=$_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? ''; if($sig==='') return false;
    $data=$url; $p=$_POST; ksort($p); foreach($p as $k=>$v) $data.=$k.$v;
    return hash_equals(base64_encode(hash_hmac('sha1',$data,TWILIO_AUTH_TOKEN,true)),$sig);
}

$key=(string)($_GET['key'] ?? '');
$self='https://chat-help.com/chat/phone-webhook.php?key='.$key;

if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Content-Type: text/plain; charset=UTF-8'); exit("ChatHelp Telefon-Agent – Twilio Voice Webhook. Diese URL als Voice-Webhook (POST) deiner Twilio-Nummer eintragen.\n"); }

th_bootstrap();
$cfg=th_phone_cfg($key);
if(!$cfg) twiml(say('Dieser Telefon-Assistent ist derzeit nicht aktiv. Auf Wiederhören.','de-DE').'<Hangup/>');
$pl=ch_plan($cfg['plan']); $aid=(int)$cfg['account_id'];
$lang=preg_match('/^[a-z]{2}-[A-Z]{2}$/',(string)$cfg['phone_voice']) ? $cfg['phone_voice'] : 'de-DE';
if(!$pl || !th_plan_active($aid,$pl['id'])) twiml(say('Dieser Telefon-Assistent ist derzeit nicht aktiv. Auf Wiederhören.',$lang).'<Hangup/>');
if(!twilio_sig_ok($self)){ http_response_code(403); twiml('<Hangup/>'); }

$sid=preg_replace('/[^A-Za-z0-9]/','',(string)($_POST['CallSid'] ?? '')); if($sid==='') twiml('<Hangup/>');
$from=mb_substr((string)($_POST['From'] ?? ''),0,32); $to=mb_substr((string)($_POST['To'] ?? ''),0,32);
$status=(string)($_POST['CallStatus'] ?? ''); $speech=trim((string)($_POST['SpeechResult'] ?? ''));
$company=$cfg['company'] ?: 'unserem Unternehmen';

/* Anruf beendet (Status-Callback) */
if(in_array($status,['completed','busy','failed','no-answer','canceled'],true)){
    th_call_update($sid,['status'=>$status,'ended_at'=>date('Y-m-d H:i:s')]); twiml('');
}

$call=th_call($sid) ?: th_call_start($aid,$pl['id'],$sid,$from,$to);
$tr=json_decode((string)($call['transcript'] ?? '[]'),true) ?: [];
$turns=(int)($call['turns'] ?? 0); $silent=(int)($call['silent'] ?? 0);

/* Begrüßung */
if($speech==='' && $turns===0 && !$tr){
    $greet=$cfg['phone_greeting'] ?: "Guten Tag, hier ist der digitale Assistent von $company. Wie kann ich Ihnen helfen?";
    $tr[]=['role'=>'assistant','content'=>$greet]; th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE)]);
    twiml(gather(say($greet,$lang),$lang,$self));
}
/* Stille */
if($speech===''){
    $silent++;
    if($silent>=2){ th_call_update($sid,['silent'=>$silent,'outcome'=>'silent','status'=>'completed','ended_at'=>date('Y-m-d H:i:s')]); twiml(say('Ich habe leider nichts gehört. Rufen Sie gern jederzeit wieder an. Auf Wiederhören.',$lang).'<Hangup/>'); }
    th_call_update($sid,['silent'=>$silent]);
    twiml(gather(say('Sind Sie noch da? Wie kann ich Ihnen helfen?',$lang),$lang,$self));
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
$sys.="\nAnrufer-Nummer (für Rückrufe, nicht vorlesen): ".($from ?: 'unbekannt').". Aktuelle Zeit: ".date('d.m.Y H:i').".";
$msgs=array_slice(array_values(array_filter($tr,fn($m)=>in_array($m['role'],['user','assistant'],true))),-16);
if($msgs[0]['role']!=='user') array_shift($msgs);   // Claude: erste Nachricht muss vom Nutzer sein
require_once __DIR__.'/appt-lib.php';
if(appt_settings($aid)['appt_on']===1){ $sys.=appt_system_hint($aid); $sys.="\nAm Telefon: Datum und Uhrzeit immer ausgesprochen wiederholen (z. B. „Donnerstag, den dreiundzwanzigsten Oktober um fünfzehn Uhr“)."; $reply=th_ai_tools($sys,$msgs,appt_tools(),fn($n,$i)=>appt_exec($aid,$n,$i,'phone'),320,40); }
else $reply=th_ai($sys,$msgs,300,25);
if($reply===null) $reply='Entschuldigung, das habe ich nicht verstanden. Können Sie das bitte noch einmal sagen?';
$forward=str_contains($reply,'[WEITERLEITEN]'); $end=str_contains($reply,'[ENDE]');
$spoken=trim(preg_replace('/\s+/',' ',str_replace(['[WEITERLEITEN]','[ENDE]','*','#','`'],'',$reply)));
if($spoken==='') $spoken=$forward?'Einen Moment bitte, ich verbinde Sie.':'Vielen Dank für Ihren Anruf. Auf Wiederhören.';

$tr[]=['role'=>'assistant','content'=>$spoken]; $turns++;
th_debit($aid,$cost,'phone:'.$pl['id'],$sid.':'.$turns);
th_log_usage($aid,'phone',$pl['sector'],$cost,0);
$upd=['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE),'turns'=>$turns,'silent'=>0,'tokens'=>(int)$call['tokens']+$cost];

if($forward){
    $fw=$cfg['phone_forward']; $upd['outcome']=$fw?'forwarded':'callback';
    th_call_update($sid,$upd);
    if($fw) twiml(say($spoken,$lang).'<Dial timeout="25" callerId="'.x($to).'">'.x($fw).'</Dial>'.say('Leider ist gerade niemand erreichbar. Wir rufen Sie so schnell wie möglich zurück. Auf Wiederhören.',$lang).'<Hangup/>');
    twiml(say($spoken.' Ich habe Ihr Anliegen notiert, das Team ruft Sie so schnell wie möglich zurück. Auf Wiederhören.',$lang).'<Hangup/>');
}
if($end){ $upd['outcome']='done'; $upd['status']='completed'; $upd['ended_at']=date('Y-m-d H:i:s'); th_call_update($sid,$upd); twiml(say($spoken,$lang).'<Hangup/>'); }
if($turns>=25){ $upd['outcome']='maxturns'; th_call_update($sid,$upd); twiml(say($spoken.' Vielen Dank für das Gespräch. Auf Wiederhören.',$lang).'<Hangup/>'); }
th_call_update($sid,$upd);
twiml(gather(say($spoken,$lang),$lang,$self));
