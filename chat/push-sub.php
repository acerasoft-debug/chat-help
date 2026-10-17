<?php
/**
 * ChatHelp — Öffentliche Push-Anmeldung für Fahrer-Handys und Boards (ohne Studio-Login)
 * ======================================================================================
 *   GET  push-sub.php?vapid=1&_=…   → {vapid_public, sw}   (öffentlicher VAPID-Schlüssel + aktuelle Version von sw.js)
 *   POST push-sub.php  {scope:'driver', t:<24-hex Fahrer-Token>, module?:'ride'|'order', type:'webpush', subscription:{endpoint,keys:{p256dh,auth}}, label?}
 *                      {scope:'driver', t, type:'fcm', token, platform}                       → {ok, id, name, module}
 *                      {scope:'board',  t:<24-hex Board-Token>, module?:'order'|'ride'|'hotel', …} → {ok, id, module}
 *   POST push-sub.php  {action:'unsubscribe', scope, t, endpoint|token}                      → {ok, removed}
 *   POST push-sub.php?resub=1 {old_endpoint, old_auth?, subscription}                        → {ok, moved}
 *        (vom Service Worker bei „pushsubscriptionchange“: alle Einträge des alten Browser-Abos ziehen auf das neue um)
 * Fahrer werden über ride_driver_by_token() (Taxi) bzw. ord_driver_by_token() (Lieferdienst) aufgelöst, Boards über board_token.
 * Studio-Inhaber melden ihre Geräte dagegen über /chat/tokens-api.php/v1/push/subscribe (Bearer-Key) an.
 * Missbrauchsschutz: th_rate je IP und je Token; Push-Adressen nur von bekannten Push-Diensten (push_endpoint_error).
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/push-lib.php';
foreach(['order-lib.php','ride-lib.php','hotel-lib.php'] as $f) if(is_file(__DIR__.'/'.$f)) require_once __DIR__.'/'.$f;

$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if($method==='OPTIONS'){ http_response_code(204); exit; }
try{ th_bootstrap(); push_bootstrap(); }catch(Throwable $e){ th_json(503,['error'=>'unavailable','message'=>'Benachrichtigungen sind gerade nicht verfügbar.']); }
$ip=th_client_ip();

if($method==='GET'){
    if(!isset($_GET['vapid'])) th_json(404,['error'=>'not_found']);
    if(!th_rate('pushsub-get:'.$ip,240,3600)) th_json(429,['error'=>'rate_limited','message'=>'Zu viele Anfragen – bitte kurz warten.']);
    $v=push_vapid_public(); if(!$v) th_json(503,['error'=>'vapid_unavailable','message'=>'Der Server kann gerade keine Push-Schlüssel bereitstellen.']);
    th_json(200,['vapid_public'=>$v,'sw'=>substr(md5((string)@filemtime(__DIR__.'/sw.js').'|'.(string)@filesize(__DIR__.'/sw.js')),0,10)]);
}
if($method!=='POST') th_json(405,['error'=>'method_not_allowed']);
if(!th_rate('pushsub-ip:'.$ip,60,3600)) th_json(429,['error'=>'rate_limited','message'=>'Zu viele Anmeldungen von diesem Netz – bitte später erneut versuchen.']);
$raw=(string)file_get_contents('php://input'); if(strlen($raw)>16384) th_json(413,['error'=>'too_large']);
$b=json_decode($raw,true); if(!is_array($b)) th_json(400,['error'=>'json_required','message'=>'Ungültige Anfrage.']);

/* Browser hat sein Abo erneuert (pushsubscriptionchange im Service Worker) */
if(isset($_GET['resub'])){
    $old=trim((string)($b['old_endpoint'] ?? '')); $sub=is_array($b['subscription'] ?? null) ? $b['subscription'] : null;
    if($old==='' || !$sub) th_json(422,['error'=>'subscription_required','message'=>'Altes und neues Abo werden benötigt.']);
    if(!push_endpoint_sendable($old)) th_json(422,['error'=>'endpoint_invalid']);
    /* Nachweis Pflicht: nur wer das Auth-Secret des alten Abos kennt (der Browser selbst), darf es umziehen – sonst könnte jeder mit der Endpoint-URL fremde Geräte kapern */
    $oldAuth=trim((string)($b['old_auth'] ?? '')); if($oldAuth==='') th_json(403,['error'=>'auth_required','message'=>'Ohne Nachweis des alten Abos ist kein Umzug möglich – bitte neu anmelden.']);
    $s=th_db()->prepare("SELECT auth FROM push_devices WHERE raw_hash=? AND type='webpush'"); $s->execute([hash('sha256',$old)]); $auths=$s->fetchAll(PDO::FETCH_COLUMN);
    $match=false; foreach($auths as $a){ $x=push_b64u_dec((string)$a); $y=push_b64u_dec($oldAuth); if($x!==null && $y!==null && hash_equals($x,$y)) $match=true; }
    if(!$auths || !$match) th_json(403,['error'=>'auth_mismatch']);
    $parsed=push_parse_subscription(['type'=>'webpush','subscription'=>$sub]); if(isset($parsed['error'])) th_json(422,$parsed);
    th_json(200,['ok'=>true,'moved'=>push_resubscribe($old,$sub)]);
}

$scope=(string)($b['scope'] ?? 'driver'); if(!in_array($scope,['driver','board'],true)) th_json(422,['error'=>'scope_invalid','message'=>'Nur Fahrer- und Board-Geräte melden sich hier an.']);
$t=is_string($b['t'] ?? null) ? strtolower(trim($b['t'])) : '';
if(!preg_match('/^[a-f0-9]{24}$/',$t)) th_json(403,['error'=>'bad_token','message'=>'Dieser Link ist ungültig. Bitte den aktuellen Link aus dem Studio verwenden.']);
if(!th_rate('pushsub-t:'.$t,30,3600)) th_json(429,['error'=>'rate_limited','message'=>'Zu viele Anmeldungen – bitte später erneut versuchen.']);
$module=strtolower(preg_replace('/[^a-z]/','',(string)($b['module'] ?? ''))); if($module==='taxi') $module='ride';
if(!in_array($module,['','ride','order','hotel'],true)) $module='';
$who=push_resolve_ref($scope,$t,$module);
if(!$who) th_json(403,['error'=>'bad_token','message'=>$scope==='driver' ? 'Fahrer nicht gefunden oder deaktiviert. Bitte den aktuellen Link aus der Zentrale verwenden.' : 'Board-Link ungültig. Bitte den aktuellen Link aus dem Studio verwenden.']);

if(($b['action'] ?? '')==='unsubscribe'){
    $rawId=!empty($b['endpoint']) ? (string)$b['endpoint'] : (!empty($b['token']) ? 'fcm:'.(string)$b['token'] : '');
    $n=0; if($rawId!==''){ $s=th_db()->prepare("DELETE FROM push_devices WHERE endpoint_hash=? AND account_id=?"); $s->execute([push_hash($scope,$who['ref'],$rawId),$who['acc']]); $n=$s->rowCount(); }
    th_json(200,['ok'=>true,'removed'=>$n]);
}

$sub=push_parse_subscription($b); if(isset($sub['error'])) th_json(422,$sub);
if(($sub['data']['label'] ?? '')!=='' && $scope==='driver' && empty($b['label'])) $sub['data']['label']=mb_substr($who['name'].' · '.$sub['data']['label'],0,80);
$id=push_device_upsert($who['acc'],$scope,$who['ref'],$sub['type'],$sub['data']);
th_json(200,['ok'=>true,'id'=>$id,'module'=>$who['module'],'name'=>$who['name'],'scope'=>$scope,'fcm_ready'=>push_fcm_ready(),
    'message'=>$scope==='driver' ? 'Benachrichtigungen aktiv – neue Aufträge kommen jetzt direkt auf dieses Handy.' : 'Benachrichtigungen für dieses Board sind aktiv.']);
