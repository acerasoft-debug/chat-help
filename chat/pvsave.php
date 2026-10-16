<?php
/* ChatHelp — pvsave.php (CH_PV) — belgeyi token ile kaydeder (24 saat). */
error_reporting(E_ERROR|E_PARSE);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$d=__DIR__.'/pv';
if(!is_dir($d)) @mkdir($d,0755,true);
/* eski dosyalari temizle */
foreach((array)@glob($d.'/*.txt') as $f){ if(@filemtime($f) < time()-86400) @unlink($f); }
$t=(string)($_POST['text'] ?? '');
if($t===''){ http_response_code(400); echo json_encode(['ok'=>false]); exit; }
if(strlen($t)>400000){ $t=substr($t,0,400000); }
$title=preg_replace('/[^\p{L}\p{N} .,\-]/u','',(string)($_POST['title'] ?? 'ChatHelp-Dokument'));
if($title==='') $title='ChatHelp-Dokument';
try{ $id=bin2hex(random_bytes(9)); }catch(Exception $e){ $id=md5(uniqid('',true)); }
$ok=@file_put_contents($d.'/'.$id.'.txt', $title."\n\x00\n".$t);
echo json_encode(['ok'=>($ok!==false),'id'=>$id]);