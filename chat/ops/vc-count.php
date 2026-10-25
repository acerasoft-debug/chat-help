<?php
/* ChatHelp ops (temporär) — Stimmenkatalog: je Sprache/Geschlecht verfügbare Stimmen nach Stufe und Anbieter (nur Zahlen, keine Schlüssel) */
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__.'/tokens-lib.php'; require_once __DIR__.'/i18n.php'; require_once __DIR__.'/voice-catalog.php';
echo "Anbieter: ".json_encode(vc_providers())."\n\n";
printf("%-4s %-2s %5s %5s %5s | %s\n",'lang','g','real','var','prem','verfügbare Premium je Anbieter');
foreach(vc_catalog() as $l=>$G) foreach($G as $g=>$list){ $n=['real'=>0,'variant'=>0,'premium'=>0]; $pp=[];
  foreach($list as $e){ if(!vc_available($e)) continue; $n[$e['tier']]++; if($e['tier']==='premium'){ $p=$e['source']['provider']; $pp[$p]=($pp[$p] ?? 0)+1; } }
  printf("%-4s %-2s %5d %5d %5d | %s\n",$l,$g,$n['real'],$n['variant'],$n['premium'],json_encode($pp)); }
$q=th_db()->query("SELECT COUNT(*) FROM agent_configs WHERE voice_id<>''"); echo "\nAgenten mit eigener Stimme: ".(int)$q->fetchColumn()."\n";
$ids=th_db()->query("SELECT voice_id FROM agent_configs WHERE voice_id<>''")->fetchAll(PDO::FETCH_COLUMN); $t=[]; foreach($ids as $i){ $e=vc_entry($i); $k=$e ? $e['tier'] : 'unbekannt'; $t[$k]=($t[$k] ?? 0)+1; } echo "davon je Stufe: ".json_encode($t)."\n";
