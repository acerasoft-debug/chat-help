<?php
/**
 * ChatHelp — Öffentliche Bestell-API (Demo / Küchen-Board mit Token)
 *   GET  order-api.php?a=demo_menu                    → Demo-Karte (Pizzeria Napoli)
 *   GET  order-api.php?a=board&t=<board_token>         → offene Bestellungen (Küchen-Tablet ohne Login)
 *   POST order-api.php?a=status&t=<board_token> {id,status,driver_id}
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/order-lib.php';
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }
th_bootstrap(); ord_bootstrap();
$in=json_decode(file_get_contents('php://input'),true); if(!is_array($in)) $in=[];
$a=preg_replace('/[^a-z_]/','',(string)($_GET['a'] ?? $in['a'] ?? ''));
if($a==='demo_menu'){ $s=ord_settings(0); th_json(200,['restaurant'=>$s['name'],'address'=>$s['address'],'open'=>ord_is_open($s),'zones'=>$s['zones'],'menu'=>ord_menu(0)]); }
$t=preg_replace('/[^a-f0-9]/','',(string)($_GET['t'] ?? $in['t'] ?? '')); if(strlen($t)!==24) th_json(401,['error'=>'token_required']);
$s=th_db()->prepare("SELECT account_id FROM order_settings WHERE board_token=?"); $s->execute([$t]); $acc=$s->fetchColumn(); if($acc===false) th_json(403,['error'=>'bad_token']); $acc=(int)$acc;
if($a==='board') th_json(200,['orders'=>ord_orders($acc,'open'),'cancelled'=>ord_recent_cancelled($acc),'drivers'=>array_map(fn($d)=>['id'=>$d['id'],'name'=>$d['name']],ord_drivers($acc,true)),'restaurant'=>ord_settings($acc)['name']]);
if($a==='status' && $_SERVER['REQUEST_METHOD']==='POST'){ $r=ord_set_status($acc,(int)($in['id']??0),(string)($in['status']??''),isset($in['driver_id'])&&$in['driver_id']!==''?(int)$in['driver_id']:null); th_json(isset($r['error'])?(in_array($r['error'],['invalid_transition','bad_driver'],true)?409:400):200,$r); }   // 409: Status inzwischen geändert (veraltetes Board)
th_json(400,['error'=>'bad_request']);
