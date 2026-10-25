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
echo "\nTTS-Budget (Zeichen bzw. Credits, Monat ".gmdate('Y-m')."):\n";
foreach(ch_tts_budget_report() as $p=>$r) echo "  $p: ".json_encode($r)."\n";
foreach(ch_tts_usage_all() as $p=>$c) echo "  usage $p: ".json_encode($c)."\n";
echo "  TTS_BUDGET_ELEVENLABS ".(defined('TTS_BUDGET_ELEVENLABS') ? 'gesetzt' : 'Standard')." · TTS_DEMO_BUDGET_ELEVENLABS ".(defined('TTS_DEMO_BUDGET_ELEVENLABS') ? 'gesetzt' : 'Standard')."\n";
if(function_exists('ch_tts_creds') && ($cr=ch_tts_creds('elevenlabs'))){ $k=(string)($cr['key'] ?? ($cr[0] ?? ''));
  if($k!==''){ $ch=curl_init('https://api.elevenlabs.io/v1/user/subscription'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['xi-api-key: '.$k]]); $b=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $j=json_decode((string)$b,true) ?: []; echo "  ElevenLabs-Abo: HTTP $code · tier=".($j['tier'] ?? '?')." · verbraucht=".($j['character_count'] ?? '?')." / Limit=".($j['character_limit'] ?? '?')." · Erneuerung=".(isset($j['next_character_count_reset_unix']) ? gmdate('Y-m-d',(int)$j['next_character_count_reset_unix']) : '?')."\n"; } }
