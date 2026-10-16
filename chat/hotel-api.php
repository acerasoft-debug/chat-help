<?php
/**
 * ChatHelp — Rezeptions-API für das Hotel-Board (Tablet ohne Login, Zugang über den geheimen Board-Token)
 *   GET  hotel-api.php?a=board&t=<board_token>&_=…            → Anreisen, Abreisen, Gäste im Haus, Buchungsanfragen, Gästewünsche, Kennzahlen
 *   POST hotel-api.php?a=request&t=<board_token> {id,status}  → Gästewunsch: open | in_progress | done | cancelled
 *   POST hotel-api.php?a=booking&t=<board_token> {id,action,room_no?} → confirm | decline | checkin | checkout | cancel | noshow
 * t=demo → öffentliche Demo (Konto 0, Kontaktdaten ausgeblendet). Alle Aktionen sind auf das Konto des Tokens beschränkt.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/hotel-lib.php';
th_bootstrap(); hotel_bootstrap();
$in=json_decode((string)file_get_contents('php://input'),true); if(!is_array($in)) $in=$_POST; $in=hotel_scalars($in); $G=hotel_scalars($_GET);
$a=preg_replace('/[^a-z_]/','',(string)($G['a'] ?? $in['a'] ?? ''));
$t=(string)($G['t'] ?? $in['t'] ?? ''); $t=$t==='demo' ? 'demo' : preg_replace('/[^a-f0-9]/','',$t);
if($t==='' || ($t!=='demo' && strlen($t)!==24)) th_json(401,['error'=>'token_required','message'=>'Board-Link unvollständig – bitte den Link aus dem Studio verwenden.']);
$acc=hotel_account_by_board($t); if($acc===null) th_json(403,['error'=>'bad_token','message'=>'Board-Link ungültig oder erneuert – im Studio unter Hotel-Rezeption den aktuellen Link öffnen.']);
$m=$_SERVER['REQUEST_METHOD'] ?? 'GET';
try{
    if($a==='board' && $m==='GET'){ if($acc===0) hotel_demo_seed(); th_json(200,hotel_board($acc,$acc===0)); }
    if($a==='request' && $m==='POST'){ $r=hotel_request_set_status($acc,(int)($in['id'] ?? 0),(string)($in['status'] ?? '')); th_json(isset($r['error']) ? ($r['error']==='not_found' ? 404 : 422) : 200,$r); }
    if($a==='booking' && $m==='POST'){
        $r=hotel_booking_action($acc,(int)($in['id'] ?? 0),(string)($in['action'] ?? ''),(string)($in['room_no'] ?? ''),'board');
        if($acc===0 && isset($r['booking'])) $r['booking']=hotel_mask($r['booking']);
        th_json(isset($r['error']) ? ($r['error']==='not_found' ? 404 : 422) : 200,$r);
    }
}catch(Throwable $e){ error_log('CH hotel-api: '.$e->getMessage()); th_json(500,['error'=>'server_error','message'=>'Interner Fehler – bitte erneut versuchen.']); }
th_json(400,['error'=>'bad_request','message'=>'Unbekannte Aktion. Erlaubt: a=board (GET), a=request (POST), a=booking (POST).']);
