<?php
/**
 * ChatHelp — Server-Stimme für die Live-Demos der Startseite
 * ===========================================================
 * POST /chat/tts.php  JSON {demo:<planId>, text:<ein Satz aus speech[]/fillers[]>, t:<Token>}
 *   200 audio/mpeg (Binärdaten, Cache-Control: no-store, Content-Length)
 *   400 {error}                 ungültige Eingabe (JSON, Demo, Text > 400 Zeichen, Token fehlt)
 *   403 {error:'bad_token'}     Token falsch/abgelaufen – nur Sätze, die der Demo-Agent selbst erzeugt hat, werden vertont
 *   429 {error:'rate_limited'}  pro IP 120/Stunde, insgesamt 6000 neue Vertonungen/Tag (CH_TTS_RATE_IP / CH_TTS_RATE_DAY)
 *   501 {error:'tts_unavailable'} kein Anbieter in db.php → Startseite nutzt die Browser-Stimme
 *   503 {error:'tts_failed'|'busy'} Anbieter/DB gestört → Browser-Stimme
 *   GET/andere → 405
 * Anbieter und Schlüssel: siehe speech-lib.php.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/speech-lib.php';

function tts_limit(string $const, int $def): int { return defined($const) && (int)constant($const)>0 ? (int)constant($const) : $def; }

if(($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='POST'){ header('Allow: POST'); th_json(405,['error'=>'method_not_allowed']); }

$raw=(string)file_get_contents('php://input',false,null,0,16384);
$in=json_decode($raw,true);
if(!is_array($in)) th_json(400,['error'=>'bad_request']);
$demo=$in['demo'] ?? null; $text=$in['text'] ?? null; $t=$in['t'] ?? null;
if(!is_string($demo) || !preg_match('/^[a-z0-9-]{2,40}$/',$demo) || !isset(CH_DEMO[$demo])) th_json(400,['error'=>'unknown_demo']);
if(!is_string($text) || trim($text)==='' || !mb_check_encoding($text,'UTF-8') || mb_strlen($text)>400) th_json(400,['error'=>'bad_text']);
if(!is_string($t) || $t==='') th_json(400,['error'=>'token_required']);
if(!ch_tts_verify($demo,$text,$t)) th_json(403,['error'=>'bad_token']);
if(ch_tts_provider()===null) th_json(501,['error'=>'tts_unavailable']);

$voice=ch_demo_cfg($demo)['voice'];
try{
    if(!th_rate('tts:ip:'.th_client_ip(), tts_limit('CH_TTS_RATE_IP',120), 3600)){ header('Retry-After: 900'); th_json(429,['error'=>'rate_limited']); }
    /* Tageslimit zählt nur neue Vertonungen (Cache-Treffer kosten beim Anbieter nichts) */
    $cached=ch_tts_cache_path($text,$voice);
    if(!($cached && is_file($cached)) && !th_rate('tts:day', tts_limit('CH_TTS_RATE_DAY',6000), 86400)){ header('Retry-After: 3600'); th_json(429,['error'=>'rate_limited']); }
}catch(Throwable $e){ error_log('CH tts rate: '.$e->getMessage()); th_json(503,['error'=>'busy']); }

$mp3=ch_tts_synth($text,$voice);
if($mp3===null) th_json(503,['error'=>'tts_failed']);

@ini_set('zlib.output_compression','0');
if(function_exists('apache_setenv')) @apache_setenv('no-gzip','1');
while(ob_get_level()>0) @ob_end_clean();
th_nocache();
http_response_code(200);
header('Content-Type: audio/mpeg');
header('Content-Length: '.strlen($mp3));
header('Content-Disposition: inline; filename="speech.mp3"');
header('X-Content-Type-Options: nosniff');
echo $mp3;
