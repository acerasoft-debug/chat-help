<?php
/**
 * ChatHelp API v1 — Stimmen (eigener Einstieg, gleiche Anmeldung wie tokens-api.php: Authorization: Bearer ch_live_…)
 * ======================================================================================================================
 *   GET  /chat/voice-api.php/v1/voices?lang=tr[&gender=f][&all=1]   → Stimmenkatalog der Sprache (f/m, beste zuerst) + Anbieter-Flags; all=1 auch Premium ohne Schlüssel
 *   GET  /chat/voice-api.php/v1/voices/agent?plan=agent-termine     → gespeicherte Stimme des Agenten (agent_configs.voice_id) + Katalog seiner Sprache
 *   POST /chat/voice-api.php/v1/voices/agent {plan, voice_id}       → Stimme des Agenten speichern ('' = Vorrat je Konto wie bisher); hält phone_voice passend
 *   POST /chat/voice-api.php/v1/voices/sample {id, name?}           → MP3-Hörprobe einer Premium-Stimme (Server-Stimme, braucht Schlüssel + Guthaben/aktiven Agenten)
 * Katalog und Logik: voice-catalog.php (vc_api). Assistenten-Stimme: POST /v1/assistant/profile {voice:{id}} (assistant-lib).
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/agent-tools.php';     // ch_plan, th_plan_active, assistant_active_plans (Guthaben-Regel der Hörprobe)
require_once __DIR__.'/voice-catalog.php';
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Authorization, Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }
th_bootstrap();
$u=(string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''),PHP_URL_PATH); $i=strpos($u,'/v1/'); $r=$i===false ? '' : rtrim(substr($u,$i+4),'/');
$m=$_SERVER['REQUEST_METHOD'];
$hdr=$_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$acc=th_auth(preg_replace('/^Bearer\s+/i','',trim($hdr)));
if(!$acc) th_json(401,['error'=>'unauthorized','message'=>'Authorization: Bearer ch_live_… fehlt oder ungültig','get_key'=>'https://chat-help.com/chat/studio.php']);
$aid=(int)$acc['id'];
$j=json_decode((string)file_get_contents('php://input'),true); $b=is_array($j) ? $j : [];
$res=vc_api($r,$m,$aid,$b,$acc);
if($res===null) th_json(404,['error'=>'not_found','routes'=>['GET /v1/voices?lang=xx','GET|POST /v1/voices/agent','POST /v1/voices/sample']]);
th_json((int)$res[0],(array)$res[1]);
