<?php
/**
 * ChatHelp — WhatsApp-Webhook für Bestell-/Termin-/Service-Agenten
 * ==================================================================
 *   https://chat-help.com/chat/whatsapp-webhook.php?key=wk_…   (key = widget_key des Agenten; Agent-Konfiguration liefert Plan + Wissensbasis)
 *   • Meta WhatsApp Cloud API: GET-Verifizierung (hub.challenge, verify_token = wa_verify aus den Bestell-Einstellungen oder der key),
 *     POST mit entry[].changes[].value.messages[] → Antwort über Graph API (wa_phone_id + wa_token).
 *   • Twilio WhatsApp: POST (Body, From=whatsapp:+…) → Antwort als TwiML <Message>.
 *   • Gesprächsspeicher je Absender (wa_sessions), Werkzeuge: Bestellung (order_on) + Termine (appt_on). Tokens: 6 je Antwort (+2 je Werkzeug).
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/appt-lib.php';
require_once __DIR__.'/order-lib.php';
require_once __DIR__.'/agent-tools.php';
th_nocache(); th_bootstrap();

$key=(string)($_GET['key'] ?? '');
$cfg=null; if(preg_match('/^wk_[a-f0-9]{36}$/',$key)){ $s=th_db()->prepare("SELECT c.*, a.email FROM agent_configs c JOIN token_accounts a ON a.id=c.account_id WHERE c.widget_key=?"); $s->execute([$key]); $cfg=$s->fetch(PDO::FETCH_ASSOC) ?: null; }
if(!$cfg){ http_response_code(404); header('Content-Type: text/plain'); exit("unknown key\n"); }
$aid=(int)$cfg['account_id']; $os=ord_settings($aid);

/* Meta-Verifizierung */
if($_SERVER['REQUEST_METHOD']==='GET'){
    if(($_GET['hub_mode'] ?? '')==='subscribe'){ $vt=(string)($_GET['hub_verify_token'] ?? ''); if($vt!=='' && ($vt===$os['wa_verify'] || $vt===$key)){ header('Content-Type: text/plain'); echo $_GET['hub_challenge'] ?? ''; exit; } http_response_code(403); exit('verify token mismatch'); }
    header('Content-Type: text/plain; charset=UTF-8'); exit("ChatHelp WhatsApp-Webhook aktiv. Meta: diese URL als Callback eintragen (Verify-Token aus dem Studio). Twilio: als WhatsApp-Webhook (POST).\n");
}
/* Welcher aktive Plan bedient den Kanal? (Abo oder Einmalkauf; sonst der Fach-Agent eines eingeschalteten Moduls) */
$planId=ch_channel_plan($aid,(string)$cfg['plan'],['order','appt','ride','hotel']);
if(!$planId){ http_response_code(200); exit; }   // inaktiv: still (Meta soll nicht erneut zustellen)
$pl=ch_plan($planId) ?: ch_plan('agent-support');

/* Eingang prüfen + normalisieren. Echtheit: Meta X-Hub-Signature-256 (App-Secret), Twilio X-Twilio-Signature (Auth-Token). */
$raw=file_get_contents('php://input'); $json=json_decode($raw,true); $provider=null; $incoming=[];
$deny=function(string $why){ error_log('CH whatsapp-webhook denied: '.$why); http_response_code(403); header('Content-Type: text/plain'); exit("forbidden\n"); };
$skip=['reaction','system','unsupported','ephemeral','request_welcome','order'];   // keine Antwort, keine Kosten
if(is_array($json) && isset($json['entry'])){ $provider='meta';
    if($os['wa_provider']!=='meta') $deny('meta post but provider '.$os['wa_provider']);
    $secret=th_secret($aid,'meta_app_secret') ?: ((defined('META_APP_SECRET') && META_APP_SECRET) ? (string)META_APP_SECRET : '');
    if($secret!==''){ $sig=(string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''); if(!hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),$sig)) $deny('bad meta signature'); }
    foreach((array)$json['entry'] as $e) foreach((array)($e['changes']??[]) as $ch){ $v=$ch['value']??[];
        $pid=(string)($v['metadata']['phone_number_id'] ?? ''); if($os['wa_phone_id']!=='' && $pid!=='' && $pid!==$os['wa_phone_id']) continue;   // Nachricht an eine andere Nummer (anderer Betrieb)
        $names=[]; foreach((array)($v['contacts']??[]) as $c) $names[$c['wa_id']??'']=$c['profile']['name']??'';
        foreach((array)($v['messages']??[]) as $m){ $t=(string)($m['type']??''); if(in_array($t,$skip,true)) continue;
            $txt=match($t){
                'text'=>(string)($m['text']['body']??''),
                'interactive'=>(string)($m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? ''),
                'button'=>(string)($m['button']['text'] ?? $m['button']['payload'] ?? ''),
                'location'=>'[Standort geteilt: '.trim(($m['location']['name']??'').' '.($m['location']['address']??'')).' ('.($m['location']['latitude']??'?').', '.($m['location']['longitude']??'?').')]',
                default=>'' };
            $incoming[]=['id'=>(string)($m['id']??''),'from'=>(string)($m['from']??''),'text'=>$txt,'media'=>$txt==='' ? $t : '','name'=>$names[$m['from']??'']??'']; } } }
elseif(isset($_POST['From']) && (isset($_POST['Body']) || isset($_POST['NumMedia']) || isset($_POST['Latitude']))){ $provider='twilio';
    if($os['wa_provider']!=='twilio') $deny('twilio post but provider '.$os['wa_provider']);
    $tw=th_twilio($aid); $tokens=array_filter([th_secret($aid,'twilio_token'), (defined('TWILIO_AUTH_TOKEN') ? (string)TWILIO_AUTH_TOKEN : '')]);
    if(!$tokens || !th_twilio_sig_valid('https://chat-help.com'.(string)$_SERVER['REQUEST_URI'],$_POST,$tokens)) $deny('bad twilio signature');
    if($os['wa_number']!=='' && isset($_POST['To']) && !th_same_phone((string)$_POST['To'],(string)$os['wa_number'])){ http_response_code(200); header('Content-Type: text/xml'); exit('<?xml version="1.0" encoding="UTF-8"?><Response></Response>'); }
    $txt=trim((string)($_POST['Body']??''));
    if($txt==='' && isset($_POST['Latitude'],$_POST['Longitude'])) $txt='[Standort geteilt: '.trim((string)($_POST['Address']??'')).' ('.$_POST['Latitude'].', '.$_POST['Longitude'].')]';
    $incoming[]=['id'=>(string)($_POST['MessageSid']??''),'from'=>preg_replace('/\D/','',(string)$_POST['From']),'text'=>$txt,'media'=>($txt==='' && (int)($_POST['NumMedia']??0)>0) ? 'media' : '','name'=>(string)($_POST['ProfileName']??'')]; }
if(!$incoming){ http_response_code(200); header('Content-Type: application/json'); echo '{"ok":true}'; exit; }

/* Doppelte Zustellung (Meta/Twilio wiederholen bei Zeitüberschreitung) → jede Nachrichten-ID nur einmal verarbeiten */
th_db()->exec("CREATE TABLE IF NOT EXISTS wa_seen (msg_id VARCHAR(120) PRIMARY KEY, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
if(random_int(1,50)===1) th_db()->exec("DELETE FROM wa_seen WHERE created_at < (NOW() - INTERVAL 3 DAY)");
$incoming=array_values(array_filter($incoming,function($in){ if($in['id']==='') return true; $s=th_db()->prepare("INSERT IGNORE INTO wa_seen(msg_id) VALUES(?)"); $s->execute([mb_substr($in['id'],0,120)]); return $s->rowCount()===1; }));

/* Antwortweg: Meta immer per Graph-API (Webhook sofort mit 200 bestätigen), Twilio per REST, wenn eingerichtet – sonst direkt als TwiML (mit Zeitlimit) */
$twilioRest=$provider==='twilio' && $os['wa_number'] && th_twilio($aid)!==null;
$async=false;
if($provider==='meta') $async=th_finish_response('{"ok":true}');
elseif($twilioRest) $async=th_finish_response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>','text/xml; charset=UTF-8');
$sync=!$async && $provider==='twilio';   // TwiML: Twilio wartet höchstens 15 s

$replies=[];
foreach($incoming as $in){
    $from=$in['from']; if($from==='') continue;
    if($in['media']!=='' && trim($in['text'])===''){ $replies[]=[$from,'Danke! Bilder, Sprachnachrichten und Dateien kann ich leider noch nicht öffnen – schreib mir dein Anliegen bitte kurz als Text. 🙂']; continue; }
    $text=trim(mb_substr($in['text'],0,1500)); if($text==='') continue;
    if(!th_rate('wa:'.$aid.':'.$from,25,600) || !th_rate('wad:'.$aid,3000,86400)){ continue; }   // Flut von einer Nummer / Tageslimit je Konto
    /* Eine Unterhaltung je Absender nacheinander: verhindert verlorenen Verlauf und doppelte Bestellungen bei schnellen Folgenachrichten */
    $lk='wa:'.$aid.':'.$from; $locked=th_lock($lk,$sync?8:40);
    try{
        $hist=ord_wa_session($aid,$from); $hist[]=['role'=>'user','content'=>$text];
        $cost=CH_PRICING['widget_turn'];
        if(th_balance($aid) < $cost){ $replies[]=[$from,'Wir sind gleich wieder für Sie da'.($os['phone']?' – oder rufen Sie uns an: '.$os['phone']:'').'.']; continue; }
        $sys=ch_agent_system($pl,$cfg,(string)($cfg['lang']?:'de'))."\n\nKANAL: WhatsApp. Schreib wie ein freundlicher Mitarbeiter im Chat: kurze Nachrichten (max. 4–5 Zeilen), Emojis sparsam, Aufzählungen mit „•“, kein Markdown-Fett (WhatsApp zeigt *Sternchen*). Kunde: ".($in['name']?:'unbekannt').". Telefonnummer des Kunden (nicht erfragen): +".$from.". Zeilen „[intern …]“ im Verlauf sind Systemnotizen (z. B. bereits angelegte Bestellung) – beachten, aber nie zitieren und nichts doppelt anlegen.";
        $tk=ch_toolkit($aid,'whatsapp',$from); $sys.=$tk['hint'];
        $reply=$tk['tools'] ? th_ai_tools($sys,$hist,$tk['tools'],$tk['exec'],700,$sync?11:60,$sync?2:5) : th_ai($sys,$hist,600,$sync?11:45);
        if($reply===null){ $replies[]=[$from,'Entschuldigung, gerade gab es ein technisches Problem. Bitte versuchen Sie es gleich noch einmal'.($os['phone']?' oder rufen Sie an: '.$os['phone']:'').'.']; continue; }   // kein Abzug bei KI-Ausfall
        $reply=preg_replace('/\*\*(.+?)\*\*/u','*$1*',$reply);   // Markdown-Fett → WhatsApp-Fett
        $reply=trim(preg_replace('/\n?\[intern[^\]]*\]/u','',$reply));
        $calls=th_ai_info()['tool_calls'] ?? []; $cost+=2*count($calls);
        /* Werkzeug-Ergebnisse (z. B. Bestellcode) im Verlauf festhalten, damit eine Wiederholung nichts doppelt anlegt */
        $done=array_values(array_filter(array_map(fn($c)=>$c['ok'] && in_array($c['name'],['create_order','book_appointment','book_ride','book_room','cancel_order','cancel_appointment','cancel_ride','cancel_booking','request_callback'],true) ? $c['name'] : null,$calls)));
        $hist[]=['role'=>'assistant','content'=>$reply.($done?"\n[intern erledigt: ".implode(', ',$done).']':'')]; ord_wa_session_save($aid,$from,$hist,$in['name']);
        $cost=th_charge($aid,$cost,'whatsapp:'.$pl['id']); th_log_usage($aid,'whatsapp',$pl['sector'],$cost,0);
        $replies[]=[$from,$reply];
    } finally { if($locked) th_unlock($lk); }
}
if($async || $provider==='meta'){ foreach($replies as [$to,$r]) ord_wa_send($os,$to,$r); if(!$async){ http_response_code(200); header('Content-Type: application/json'); echo '{"ok":true}'; } exit; }
header('Content-Type: text/xml; charset=UTF-8'); echo '<?xml version="1.0" encoding="UTF-8"?><Response>'; foreach($replies as [$to,$r]) echo '<Message>'.htmlspecialchars(mb_substr($r,0,1590),ENT_XML1|ENT_QUOTES,'UTF-8').'</Message>'; echo '</Response>';
