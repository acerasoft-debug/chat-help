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
$aid=(int)$cfg['account_id']; $pl=ch_plan($cfg['plan']) ?: ch_plan('agent-support'); $os=ord_settings($aid);

/* Meta-Verifizierung */
if($_SERVER['REQUEST_METHOD']==='GET'){
    if(($_GET['hub_mode'] ?? '')==='subscribe'){ $vt=(string)($_GET['hub_verify_token'] ?? ''); if($vt!=='' && ($vt===$os['wa_verify'] || $vt===$key)){ header('Content-Type: text/plain'); echo $_GET['hub_challenge'] ?? ''; exit; } http_response_code(403); exit('verify token mismatch'); }
    header('Content-Type: text/plain; charset=UTF-8'); exit("ChatHelp WhatsApp-Webhook aktiv. Meta: diese URL als Callback eintragen (Verify-Token aus dem Studio). Twilio: als WhatsApp-Webhook (POST).\n");
}
if(!th_plan_active($aid,$pl['id'])){ http_response_code(200); exit; }   // inaktiv: still (Meta soll nicht erneut zustellen)

/* Eingang normalisieren */
$raw=file_get_contents('php://input'); $json=json_decode($raw,true); $provider=null; $incoming=[];
if(is_array($json) && isset($json['entry'])){ $provider='meta';
    foreach((array)$json['entry'] as $e) foreach((array)($e['changes']??[]) as $ch){ $v=$ch['value']??[]; $names=[]; foreach((array)($v['contacts']??[]) as $c) $names[$c['wa_id']??'']=$c['profile']['name']??'';
        foreach((array)($v['messages']??[]) as $m){ $txt=($m['type']??'')==='text' ? (string)($m['text']['body']??'') : (($m['type']??'')==='interactive' ? (string)($m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? '') : '[Der Kunde hat eine '.($m['type']??'Nachricht').' gesendet – bitte freundlich um eine Textnachricht bitten]');
            $incoming[]=['id'=>(string)($m['id']??''),'from'=>(string)($m['from']??''),'text'=>$txt,'name'=>$names[$m['from']??'']??'']; } } }
elseif(isset($_POST['Body'],$_POST['From'])){ $provider='twilio'; $incoming[]=['id'=>(string)($_POST['MessageSid']??''),'from'=>preg_replace('/\D/','',$_POST['From']),'text'=>(string)$_POST['Body'],'name'=>(string)($_POST['ProfileName']??'')]; }
if(!$incoming){ http_response_code(200); header('Content-Type: application/json'); echo '{"ok":true}'; exit; }

/* Doppelte Zustellung (Meta/Twilio wiederholen bei Zeitüberschreitung) → jede Nachrichten-ID nur einmal verarbeiten */
th_db()->exec("CREATE TABLE IF NOT EXISTS wa_seen (msg_id VARCHAR(120) PRIMARY KEY, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
if(random_int(1,50)===1) th_db()->exec("DELETE FROM wa_seen WHERE created_at < (NOW() - INTERVAL 3 DAY)");
$incoming=array_values(array_filter($incoming,function($in){ if($in['id']==='') return true; $s=th_db()->prepare("INSERT IGNORE INTO wa_seen(msg_id) VALUES(?)"); $s->execute([mb_substr($in['id'],0,120)]); return $s->rowCount()===1; }));

/* Antwortweg: Meta immer per Graph-API (Webhook sofort mit 200 bestätigen), Twilio per REST, wenn eingerichtet – sonst direkt als TwiML */
$twilioRest=$provider==='twilio' && $os['wa_provider']==='twilio' && $os['wa_number'] && defined('TWILIO_ACCOUNT_SID') && defined('TWILIO_AUTH_TOKEN');
$async=false;
if($provider==='meta') $async=th_finish_response('{"ok":true}');
elseif($twilioRest) $async=th_finish_response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>','text/xml; charset=UTF-8');

$replies=[];
foreach($incoming as $in){
    $from=$in['from']; $text=trim(mb_substr($in['text'],0,1500)); if($text==='' || $from==='') continue;
    $hist=ord_wa_session($aid,$from); $hist[]=['role'=>'user','content'=>$text];
    $cost=CH_PRICING['widget_turn'];
    if(th_balance($aid) < $cost){ $replies[]=[$from,'Wir sind gleich wieder für Sie da'.($os['phone']?' – oder rufen Sie uns an: '.$os['phone']:'').'.']; continue; }
    $sys=ch_agent_system($pl,$cfg,(string)($cfg['lang']?:'de'))."\n\nKANAL: WhatsApp. Schreib wie ein freundlicher Mitarbeiter im Chat: kurze Nachrichten (max. 4–5 Zeilen), Emojis sparsam, Aufzählungen mit „•“, kein Markdown-Fett (WhatsApp zeigt *Sternchen*). Kunde: ".($in['name']?:'unbekannt').". Telefonnummer des Kunden (nicht erfragen): +".$from.".";
    $tk=ch_toolkit($aid,'whatsapp',$from); $sys.=$tk['hint'];
    $reply=$tk['tools'] ? th_ai_tools($sys,$hist,$tk['tools'],$tk['exec'],700,60) : th_ai($sys,$hist,600,45);
    if($reply===null) $reply='Entschuldigung, gerade gab es ein technisches Problem. Bitte versuchen Sie es gleich noch einmal'.($os['phone']?' oder rufen Sie an: '.$os['phone']:'').'.';
    $reply=preg_replace('/\*\*(.+?)\*\*/u','*$1*',$reply);   // Markdown-Fett → WhatsApp-Fett
    $cost+=2*count(th_ai_info()['tool_calls'] ?? []);
    $hist[]=['role'=>'assistant','content'=>$reply]; ord_wa_session_save($aid,$from,$hist,$in['name']);
    th_debit($aid,$cost,'whatsapp:'.$pl['id']); th_log_usage($aid,'whatsapp',$pl['sector'],$cost,0);
    $replies[]=[$from,$reply];
}
if($async || $provider==='meta'){ foreach($replies as [$to,$r]) ord_wa_send($os,$to,$r); if(!$async){ http_response_code(200); header('Content-Type: application/json'); echo '{"ok":true}'; } exit; }
header('Content-Type: text/xml; charset=UTF-8'); echo '<?xml version="1.0" encoding="UTF-8"?><Response>'; foreach($replies as [$to,$r]) echo '<Message>'.htmlspecialchars($r,ENT_XML1|ENT_QUOTES,'UTF-8').'</Message>'; echo '</Response>';
