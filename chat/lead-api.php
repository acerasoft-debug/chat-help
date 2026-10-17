<?php
/**
 * ChatHelp — Lead-API für Rückrufwünsche (Website-Formulare, Immobilienportale, Zapier/Make): lead-api.php?k=<widget_key>
 *   GET  → kurze Feldbeschreibung (JSON) für Integratoren
 *   POST → JSON oder Formularfelder: name, phone, email?, message|topic, consent, consent_text?, preferred_time?, urgency?, source? (z. B. „ImmoScout24“),
 *          bis zu 10 weitere kurze Textfelder → details. Antwort JSON {ok, code, message} (klassischer Formular-Post aus dem Browser: kleine Danke-Seite).
 * Schutz: CORS *, Honeypot „website“ (muss leer sein), Limits 5 je IP / 10 Min und 60 je Schlüssel / Stunde (th_rate), nur mit aktivem Paket.
 * Einwilligung (consent) wird mit Zeitpunkt, IP-Prüfsumme und Wortlaut gespeichert; ohne Einwilligung ruft nur das Team zurück.
 * Lead-Sofortrückruf: KI-Rückruf + lead_instant + innerhalb der Anrufzeiten → nach der Antwort ruft der Assistent sofort an.
 * k=demo → Demo-Konto 0 (es wird nie angerufen, keine E-Mail).
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/callback-lib.php';
require_once __DIR__.'/agent-tools.php';   // Planprüfung (ch_plan_active_any), Heartbeat für den Planer
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); header('Access-Control-Max-Age: 86400');
$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if($method==='OPTIONS'){ http_response_code(204); exit; }
th_bootstrap(); cb_bootstrap();

$raw=$method==='POST' ? (string)file_get_contents('php://input') : '';
$ctype=strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$isJson=str_contains($ctype,'json') || ($raw!=='' && in_array(ltrim($raw)[0] ?? '',['{','['],true) && !str_contains($ctype,'form'));
/* Klassischer Formular-Post aus dem Browser (ohne JavaScript): statt JSON eine kleine Danke-Seite zeigen */
$wantsHtml=$method==='POST' && !$isJson && str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')),'text/html');

/** Antwort als JSON oder (Browser-Formular) als kleine HTML-Seite. */
function cbl_page(int $code, array $j): string {
    $ok=!empty($j['ok']); $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    $back=(string)($_SERVER['HTTP_REFERER'] ?? ''); $back=preg_match('~^https?://~i',$back) ? $back : '';
    return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>'.($ok ? 'Vielen Dank' : 'Bitte prüfen').'</title>'
        .'<style>body{margin:0;background:#f6f7fb;font:16px/1.55 Inter,system-ui,sans-serif;color:#0f1222}.c{max-width:520px;margin:12vh auto;padding:28px 22px;background:#fff;border:1px solid #e4e6ee;border-radius:22px;text-align:center;box-shadow:0 10px 30px rgba(15,18,34,.07)}'
        .'.i{width:60px;height:60px;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;font-size:28px;background:'.($ok ? '#e4f5ea;color:#1a9e5a' : '#fde7ee;color:#9f2448').'}h1{font:600 24px Georgia,serif;margin:0 0 8px}p{color:#3b4055;margin:0 0 16px}a{display:inline-block;padding:12px 18px;border-radius:12px;background:#0f1222;color:#fff;text-decoration:none;font-weight:600}</style></head>'
        .'<body><div class="c"><div class="i">'.($ok ? '✓' : '!').'</div><h1>'.($ok ? 'Vielen Dank!' : 'Bitte prüfen Sie Ihre Angaben').'</h1><p>'.$e($j['message'] ?? '').'</p>'.($back!=='' ? '<a href="'.$e($back).'">Zurück</a>' : '').'</div></body></html>';
}
function cbl_out(int $code, array $j): void {
    global $wantsHtml;
    if($wantsHtml){ th_nocache(); http_response_code($code); header('Content-Type: text/html; charset=UTF-8'); echo cbl_page($code,$j); exit; }
    th_json($code,$j);
}
const CBL_ERR=['name_required'=>'Bitte geben Sie Ihren Namen an.','phone_required'=>'Bitte geben Sie eine Telefonnummer für den Rückruf an.',
  'phone_invalid'=>'Die Telefonnummer ist ungültig – bitte mit Vorwahl angeben, z. B. 0170 1234567 oder +49 170 1234567.','email_invalid'=>'Die E-Mail-Adresse ist ungültig.',
  'too_many_requests'=>'Für diese Telefonnummer liegen heute bereits mehrere Rückrufbitten vor – wir melden uns.','callbacks_disabled'=>'Rückrufanfragen sind für dieses Unternehmen derzeit nicht aktiviert.',
  'bad_preferred_time'=>'Die Wunschzeit wurde nicht verstanden – bitte z. B. „heute 16 Uhr“ oder „2026-10-20 14:30“ angeben.','preferred_time_too_far'=>'Rückrufe sind höchstens 60 Tage im Voraus möglich.'];

$k=(string)($_GET['k'] ?? ''); $kk=cb_key_account($k);
if(!$kk) cbl_out(404,['ok'=>false,'error'=>'unknown_key','message'=>'Unbekannter Schlüssel – bitte den Link aus dem ChatHelp Studio (Rückrufe → Einbinden) verwenden.']);
$acc=$kk['account_id']; $s=cb_settings($acc); $company=cb_company($acc,$s,$kk);

if($method==='GET'){
    $u=CB_BASE_URL.'lead-api.php?k='.rawurlencode($k);
    th_json(200,['ok'=>true,'service'=>'ChatHelp Lead-API · Rückrufwünsche','business'=>$company,'demo'=>$acc===0,'endpoint'=>$u,'method'=>'POST',
        'formats'=>['application/json','application/x-www-form-urlencoded','multipart/form-data'],
        'fields'=>['name'=>'Pflicht – Vor- und Nachname','phone'=>'Pflicht – Rückrufnummer, z. B. +49 170 1234567 oder 0170 1234567','email'=>'optional',
            'message'=>'Anliegen (auch: topic, nachricht, anliegen)','consent'=>'1/true = Einwilligung in den telefonischen Rückruf (auch durch einen KI-Telefonassistenten). Ohne Einwilligung ruft nur das Team zurück.',
            'consent_text'=>'optional – Wortlaut der Einwilligung, wie er im Formular angezeigt wurde (wird als Nachweis gespeichert)',
            'preferred_time'=>'optional – „so schnell wie möglich“ (Standard), „heute 16 Uhr“, „morgen vormittag“ oder „YYYY-MM-DD HH:MM“',
            'urgency'=>'optional – normal | dringend','source'=>'optional – Herkunft, z. B. „ImmoScout24“ oder „Website“','website'=>'Honeypot – muss leer bleiben',
            '…'=>'bis zu 10 weitere kurze Textfelder (z. B. objekt, budget, einzug) werden als Details zum Lead gespeichert'],
        'response'=>['ok'=>true,'code'=>'ABC234','message'=>'Vielen Dank, Max! Unser digitaler Assistent ruft Sie in etwa einer Minute zurück.'],
        'limits'=>'5 Anfragen je IP-Adresse in 10 Minuten, 60 je Schlüssel und Stunde',
        'example'=>['name'=>'Max Mustermann','phone'=>'+49 170 1234567','email'=>'max@example.com','message'=>'Ist die Wohnung LQ-2041 noch frei?','consent'=>true,'source'=>'ImmoScout24','objekt'=>'LQ-2041','budget'=>'1.300 € warm']]);
}
if($method!=='POST') cbl_out(405,['ok'=>false,'error'=>'method_not_allowed','message'=>'Bitte per POST senden.']);
if(strlen($raw)>20000) cbl_out(413,['ok'=>false,'error'=>'too_large','message'=>'Anfrage zu groß.']);
$in=$isJson ? json_decode($raw,true) : $_POST;
if(!is_array($in) || array_is_list($in) && $in) cbl_out(400,['ok'=>false,'error'=>'bad_request','message'=>'Ungültige Anfrage – erwartet wird ein JSON-Objekt oder Formularfelder.']);

/* Mengenlimits (zählen auch abgewiesene Versuche) */
$ipHash=substr(hash('sha256','cbl|'.th_client_ip()),0,32);
if(!th_rate('cbl:ip:'.$ipHash,5,600)) cbl_out(429,['ok'=>false,'error'=>'rate_limited','message'=>'Zu viele Anfragen in kurzer Zeit – bitte versuchen Sie es in einigen Minuten erneut.']);
if(!th_rate('cbl:key:'.$acc.':'.$kk['widget_key'],60,3600)) cbl_out(429,['ok'=>false,'error'=>'rate_limited','message'=>'Derzeit gehen sehr viele Anfragen ein – bitte versuchen Sie es später erneut.']);
/* Honeypot / zu schnelles Absenden (Bots): freundlich „ok“, aber nichts speichern */
$honey=array_key_exists('website',$in) && (!is_scalar($in['website']) || trim((string)$in['website'])!=='');
$age=is_numeric($in['form_ts'] ?? null) ? time()-(int)$in['form_ts'] : null;   // form_ts setzt die gehostete Seite (Serverzeit)
if($honey || ($age!==null && $age>=0 && $age<2)) cbl_out(200,['ok'=>true,'code'=>null,'message'=>'Vielen Dank! Ihre Rückrufbitte ist eingegangen.']);
if(!cb_enabled($acc)) cbl_out(403,['ok'=>false,'error'=>'disabled','message'=>CBL_ERR['callbacks_disabled']]);
if(!cb_public_ok($acc,$kk['plan'])) cbl_out(403,['ok'=>false,'error'=>'inactive','message'=>'Dieses Rückrufformular ist derzeit nicht aktiv.']);

/* Felder (auch übliche Alias-Namen von Portalen/Formular-Baukästen) */
$pick=function(string $f) use($in){ foreach(CB_FIELD_ALIASES[$f] as $a){ if(isset($in[$a]) && is_scalar($in[$a]) && trim((string)$in[$a])!=='') return trim((string)$in[$a]); } return ''; };
$name=$pick('name');
if($name===''){ $fn=''; $ln=''; foreach(['first_name','firstname','vorname'] as $a) if(isset($in[$a]) && is_scalar($in[$a])) $fn=trim((string)$in[$a]); foreach(['last_name','lastname','nachname'] as $a) if(isset($in[$a]) && is_scalar($in[$a])) $ln=trim((string)$in[$a]); $name=trim($fn.' '.$ln); }
$known=array_merge(...array_values(CB_FIELD_ALIASES)); $extra=[];
if(isset($in['details']) && is_array($in['details'])) $extra=$in['details'];
foreach($in as $key=>$v){ $lk=strtolower((string)$key); if(in_array($lk,$known,true) || in_array($lk,CB_FIELD_IGNORE,true) || str_starts_with($lk,'_') || str_starts_with($lk,'utm_') || !is_scalar($v)) continue; $extra[$key]=$v; }
$src=cb_line($pick('source'),40); if($src!=='') $extra=['Quelle'=>$src]+$extra;
$consent=cb_truthy($pick('consent'));
$ctext=$consent ? (cb_line($pick('consent_text'),500) ?: cb_consent_text($company,$s['ai_callback']===1)) : '';
$source=$acc===0 ? 'demo' : (in_array($in['via'] ?? '',['hosted','website','form'],true) || !$isJson ? 'form' : 'api');   // Formulare (gehostet/Website) vs. Portale/Zapier
$r=cb_create($acc,['name'=>$name,'phone'=>$pick('phone'),'email'=>$pick('email'),'topic'=>$pick('topic'),'preferred_time'=>$pick('preferred_time'),'urgency'=>$pick('urgency'),
    'details'=>$extra,'consent'=>$consent ? 1 : 0,'consent_text'=>$ctext,'consent_ip'=>$consent ? $ipHash : '','plan'=>$kk['plan']],$source);
if(isset($r['error'])) cbl_out(422,['ok'=>false,'error'=>$r['error'],'message'=>CBL_ERR[$r['error']] ?? ($r['hint'] ?? 'Die Anfrage konnte nicht gespeichert werden.')]);

$first=explode(' ',$name)[0] ?? '';
$resp=['ok'=>true,'code'=>$r['code'],'message'=>!empty($r['duplicate']) ? $r['message'] : 'Vielen Dank'.($first!=='' ? ', '.$first : '').'! '.$r['message'],'callback'=>$r['callback']];
if($acc===0) $resp['demo']='Live-Demo: Im echten Betrieb ruft jetzt der digitale Assistent an – in der Demo wird niemand angerufen.';
/* Lead-Sofortrückruf: Antwort sofort senden, dann im Hintergrund anrufen. Ohne Hintergrund-Modus (CLI/Entwicklung) übernimmt der Planer innerhalb einer Minute. */
$instant=$acc>0 && empty($r['duplicate']) && $r['callback']['mode']==='ai' && $r['callback']['status']==='open' && $s['lead_instant']===1 && cb_in_window($s,cb_now($s));
if($instant){
    $bg=PHP_SAPI!=='cli' && !headers_sent() && (function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request'));
    if($bg){
        th_nocache();
        $body=$wantsHtml ? cbl_page(200,$resp) : (string)json_encode($resp,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(th_finish_response($body,$wantsHtml ? 'text/html; charset=UTF-8' : 'application/json; charset=UTF-8',200)){
            try{ $c=cb_call_now($acc,(int)$r['id']); if(isset($c['error']) && $c['error']!=='twilio_error') error_log('CH lead instant '.$acc.': '.$c['error']); }catch(Throwable $e){ error_log('CH lead instant: '.$e->getMessage()); }
            exit;
        }
    }
    ch_heartbeat();
}
cbl_out(200,$resp);
