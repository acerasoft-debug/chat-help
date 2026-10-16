<?php
/**
 * ChatHelp — Taxi-Board-API (Dispositions-Board ohne Login, Zugang über den geheimen Board-Token der Zentrale)
 *   GET  ride-api.php?a=board&t=<board_token>&_=…            → offene Fahrten, Fahrer mit Status, Kennzahlen
 *   POST ride-api.php?a=status&t=<board_token> {id,status,driver_id?}   → Status setzen / Fahrer zuteilen
 *   POST ride-api.php?a=driver&t=<board_token> {driver_id,status}      → Fahrerstatus frei | besetzt | pause
 * Alle Abfragen sind auf das Konto des Tokens beschränkt. Die Fahrer-App (taxi-driver.php) verarbeitet ihre Aktionen selbst.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/ride-lib.php';
th_bootstrap(); ride_bootstrap();
$in=json_decode((string)file_get_contents('php://input'),true); if(!is_array($in)) $in=$_POST;
$a=preg_replace('/[^a-z_]/','',(string)($_GET['a'] ?? ($in['a'] ?? '')));
$t=preg_replace('/[^a-f0-9]/','',(string)($_GET['t'] ?? ($in['t'] ?? '')));
if(strlen($t)!==24) th_json(401,['error'=>'token_required','message'=>'Board-Link unvollständig.']);
$acc=ride_account_by_board_token($t); if($acc===null) th_json(403,['error'=>'bad_token','message'=>'Board-Link ungültig – den aktuellen Link finden Sie im Studio unter Taxi.']);
$post=$_SERVER['REQUEST_METHOD']==='POST';
try{
    if($a==='board') th_json(200,ride_board($acc));
    if($a==='status'){
        if(!$post) th_json(405,['error'=>'method_not_allowed']);
        $did=isset($in['driver_id']) && $in['driver_id']!=='' && $in['driver_id']!==null ? (int)$in['driver_id'] : null;
        $res=ride_set_status($acc,(int)($in['id'] ?? 0),(string)($in['status'] ?? ''),$did,'Zentrale');
        th_json(isset($res['error']) ? ($res['error']==='not_found' ? 404 : 400) : 200,$res);
    }
    if($a==='driver'){
        if(!$post) th_json(405,['error'=>'method_not_allowed']);
        $res=ride_driver_set_status($acc,(int)($in['driver_id'] ?? ($in['id'] ?? 0)),(string)($in['status'] ?? ''));
        th_json(isset($res['error']) ? ($res['error']==='not_found' ? 404 : 400) : 200,$res);
    }
}catch(Throwable $e){ error_log('CH ride-api: '.$e->getMessage()); th_json(500,['error'=>'server_error','message'=>'Interner Fehler – bitte gleich noch einmal versuchen.']); }
th_json(400,['error'=>'bad_request','actions'=>['board','status','driver']]);
