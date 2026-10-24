<?php
/**
 * ChatHelp — Telefon-Audio für Twilio <Play>
 * ==========================================
 * GET /chat/tts-play.php?f=<64 hex>&e=<Ablauf>&s=<Signatur>
 *   Liefert eine Vertonung, die phone-webhook.php kurz vorher selbst erzeugt und signiert hat (TTS-Cache, siehe ch_tts_play_url).
 *   Erzeugt nie selbst Audio und nimmt keinen Text an – ohne gültige Signatur (HMAC über f|e, 2 h gültig) gibt es nichts.
 *   200 audio/mpeg · 403 Signatur falsch/abgelaufen · 404 Datei nicht (mehr) im Cache · 405 andere Methode
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/speech-lib.php';

$m=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if($m!=='GET' && $m!=='HEAD'){ header('Allow: GET, HEAD'); http_response_code(405); exit; }
$f=(string)($_GET['f'] ?? ''); $e=(string)($_GET['e'] ?? ''); $s=(string)($_GET['s'] ?? '');
if(!preg_match('/^[a-f0-9]{64}$/',$f) || !preg_match('/^\d{9,12}$/',$e) || !preg_match('/^[A-Za-z0-9_-]{43}$/',$s)){ http_response_code(403); exit; }
$exp=(int)$e; $now=time();
if($exp<$now || $exp>$now+CH_TTS_PLAY_TTL+300 || !hash_equals(ch_tts_play_sig($f,$exp),$s)){ http_response_code(403); exit; }
$d=ch_tts_cache_dir(); $file=$d ? $d.'/'.$f.'.mp3' : '';
if($file==='' || !is_file($file) || filesize($file)<64){ http_response_code(404); exit; }

@ini_set('zlib.output_compression','0');
if(function_exists('apache_setenv')) @apache_setenv('no-gzip','1');
while(ob_get_level()>0) @ob_end_clean();
http_response_code(200);
header('Content-Type: audio/mpeg');
header('Content-Length: '.filesize($file));
header('Cache-Control: private, max-age=7200');
header('X-Content-Type-Options: nosniff');
if($m==='GET') readfile($file);
