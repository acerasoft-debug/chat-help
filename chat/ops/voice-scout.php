<?php
/**
 * ChatHelp ops — voice-scout: beste muttersprachliche Stimme je Sprache finden und eintragen (Tabelle tts_native), danach Übersicht.
 *   args (JSON → $_GET):  mode   = scout (Standard) | status (nur Übersicht) | dry (suchen und zeigen, nichts übernehmen/speichern)
 *                                  | models (ElevenLabs-Modelle + Sprach-Festlegung prüfen) | premade (zusätzlich Standardstimmen prüfen und für alle Sprachen eintragen)
 *                         langs  = all (Standard) | z. B. "az,tr,de"
 *                         per    = Stimmen je Sprache und Geschlecht bei ElevenLabs: auto (Standard, nach freien Stimmenplätzen) | 1–4 (4 = eigene Stimme je Persona)
 *                         providers = elevenlabs,azure,google (Standard: alle mit Schlüssel)
 * Schreibt nur in tts_native und (ElevenLabs) in die eigene Stimmen-Bibliothek des Kontos. Gibt keine Schlüssel und keine Personendaten aus.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(300);
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/voice-scout-lib.php';
if(is_file(__DIR__.'/voice-catalog.php')) require_once __DIR__.'/voice-catalog.php';

$mode=in_array($_GET['mode'] ?? '',['status','dry','models','premade'],true) ? $_GET['mode'] : 'scout';
$langs=strtolower(trim((string)($_GET['langs'] ?? 'all')));
$langs=$langs==='' || $langs==='all' ? VS_LANGS : array_values(array_intersect(VS_LANGS,array_map('trim',explode(',',$langs))));
$perArg=strtolower((string)($_GET['per'] ?? 'auto'));
$per=$perArg==='auto' ? 0 : max(1,min(4,(int)$perArg));
$provs=array_values(array_intersect(['elevenlabs','azure','google'],array_map('trim',explode(',',strtolower((string)($_GET['providers'] ?? 'elevenlabs,azure,google'))))));

echo "ChatHelp — voice-scout (".gmdate('c').") · Modus: $mode · Sprachen: ".implode(',',$langs)." · je Geschlecht: ".($per ?: 'auto')."\n";
$av=ch_tts_providers_available(); echo "Schlüssel vorhanden: ".implode(', ',array_map(fn($p)=>$p.'='.($av[$p] ? 'ja' : 'nein'),array_keys($av)))."\n";
if(defined('TTS_PROVIDER') && !in_array(strtolower(trim((string)TTS_PROVIDER)),['','auto'],true)) echo "Hinweis: TTS_PROVIDER=".TTS_PROVIDER." erzwingt einen Anbieter für alle Sprachen (für die beste Stimme je Sprache: 'auto' oder entfernen).\n";
echo "\n";

try{ vs_bootstrap(); }catch(Throwable $e){ echo "FEHLER: Tabelle tts_native: ".$e->getMessage()."\n"; exit(1); }
if($per===0 && !in_array($mode,['models','premade','status'],true)){ $elLangs=count(array_diff($langs,['en'])); $a=vs_el_auto_per($elLangs); $per=$a ?? 1; echo "Stimmen je Sprache und Geschlecht: $per (".($a!==null ? 'aus den freien Stimmenplätzen des ElevenLabs-Tarifs' : 'Standard').")\n\n"; }

if($mode==='models' || $mode==='premade'){   // nur Sprachausgabe-Berechtigung nötig
    $log=[]; vs_el_probe_models($log); if($mode==='premade') vs_el_premade(true,$log);
    echo "— ElevenLabs-Prüfung —\n".implode("\n",$log)."\n\n"; ch_tts_native_all(true);
}
elseif($mode!=='status'){
    $log=[];
    if(in_array('elevenlabs',$provs,true)) vs_scout_elevenlabs($langs,$per,$mode==='scout',$log);
    if($mode==='scout' && in_array('azure',$provs,true)) vs_scout_azure($langs,$log);
    if($mode==='scout' && in_array('google',$provs,true)) vs_scout_google($langs,$log);
    echo "— Suche —\n".implode("\n",$log)."\n\n";
    ch_tts_native_all(true);
}

echo "— Welche Stimme spricht je Sprache (Frau | Mann) —\n";
$nat=0;
foreach(vs_status() as $r){
    if($r['native']) $nat++;
    printf("%-3s %-11s %s\n     F: %s\n     M: %s%s\n",$r['lang'],$r['provider'] ?? 'Browser',$r['native'] ? '✓ muttersprachlich' : '· '.$r['note'],$r['f'],$r['m'],"");
}
echo "\nMuttersprachlich: $nat von ".count(VS_LANGS)." Sprachen.\n";
echo "\n— Kostenbremse ".ch_tts_ym()." (ElevenLabs in Credits, sonst Zeichen: verbraucht / Monatsgrenze · Demo / Demo-Grenze; 0 = ohne Grenze) —\n";
$br=ch_tts_budget_report(); if(!$br) echo "kein Anbieter eingerichtet\n";
foreach($br as $p=>$b) printf("%-11s %s / %s · Demo %s / %s%s\n",$p,number_format($b['used'],0,',','.'),number_format($b['limit'],0,',','.'),number_format($b['demo'],0,',','.'),number_format($b['demo_limit'],0,',','.'),$b['blocked_until'] ? ' · Kontingent leer, pausiert bis '.gmdate('d.m. H:i',$b['blocked_until']).' UTC' : '');
$cd=ch_tts_cache_dir(); $cf=$cd ? glob($cd.'/*.mp3') ?: [] : []; printf("Stimmen-Speicher: %s Sätze, %s MB (%s) – jeder Satz wird je Stimme nur einmal bezahlt\n",number_format(count($cf),0,',','.'),number_format(array_sum(array_map('filesize',$cf))/1048576,1,',','.'),$cd && str_contains($cd,'/ch_tts_cache') ? 'dauerhaft neben dem Web-Ordner' : 'Temp-Ordner');
if(function_exists('th_db')) try{ $rp=th_db()->query("SELECT COUNT(*) n, COALESCE(SUM(hits),0) h FROM concierge_replay")->fetch(PDO::FETCH_ASSOC); printf("Demo-Schnellwahl: %d gespeicherte Antworten, %d-mal wiederverwendet (ohne neue KI- und Stimmkosten)\n",$rp['n'],$rp['h']); }catch(Throwable $e){}
echo "FERTIG\n";
