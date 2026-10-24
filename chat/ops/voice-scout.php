<?php
/**
 * ChatHelp ops — voice-scout: beste muttersprachliche Stimme je Sprache finden und eintragen (Tabelle tts_native), danach Übersicht.
 *   args (JSON → $_GET):  mode   = scout (Standard) | status (nur Übersicht) | dry (suchen und zeigen, nichts übernehmen/speichern)
 *                         langs  = all (Standard) | z. B. "az,tr,de"
 *                         per    = Stimmen je Sprache und Geschlecht bei ElevenLabs (1–3, Standard 1 → 24 Stimmen für 12 Sprachen)
 *                         providers = elevenlabs,azure,google (Standard: alle mit Schlüssel)
 * Schreibt nur in tts_native und (ElevenLabs) in die eigene Stimmen-Bibliothek des Kontos. Gibt keine Schlüssel und keine Personendaten aus.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(300);
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/voice-scout-lib.php';
if(is_file(__DIR__.'/voice-catalog.php')) require_once __DIR__.'/voice-catalog.php';

$mode=in_array($_GET['mode'] ?? '',['status','dry'],true) ? $_GET['mode'] : 'scout';
$langs=strtolower(trim((string)($_GET['langs'] ?? 'all')));
$langs=$langs==='' || $langs==='all' ? VS_LANGS : array_values(array_intersect(VS_LANGS,array_map('trim',explode(',',$langs))));
$per=max(1,min(3,(int)($_GET['per'] ?? 1)));
$provs=array_values(array_intersect(['elevenlabs','azure','google'],array_map('trim',explode(',',strtolower((string)($_GET['providers'] ?? 'elevenlabs,azure,google'))))));

echo "ChatHelp — voice-scout (".gmdate('c').") · Modus: $mode · Sprachen: ".implode(',',$langs)." · je Geschlecht: $per\n";
$av=ch_tts_providers_available(); echo "Schlüssel vorhanden: ".implode(', ',array_map(fn($p)=>$p.'='.($av[$p] ? 'ja' : 'nein'),array_keys($av)))."\n";
if(defined('TTS_PROVIDER') && !in_array(strtolower(trim((string)TTS_PROVIDER)),['','auto'],true)) echo "Hinweis: TTS_PROVIDER=".TTS_PROVIDER." erzwingt einen Anbieter für alle Sprachen (für die beste Stimme je Sprache: 'auto' oder entfernen).\n";
echo "\n";

try{ vs_bootstrap(); }catch(Throwable $e){ echo "FEHLER: Tabelle tts_native: ".$e->getMessage()."\n"; exit(1); }

if($mode!=='status'){
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
echo "\nMuttersprachlich: $nat von ".count(VS_LANGS)." Sprachen.\nFERTIG\n";
