<?php
/**
 * ChatHelp ops — ai-costs: echte KI-Kosten je Antwort (Prompt-Cache) aus ai_usage_day / ai_usage, ohne Konten- oder Personendaten.
 *   args (JSON → $_GET):  mode = report (Standard) | probe (4 echte Testaufrufe mit festem Systemtext: Cache-Schreiben → Cache-Lesen prüfen)
 *                         days = Tage im Tagesbericht (Standard 14)
 * Ausgabe: Modell, je Tag Antworten / Cent je Antwort / Cache-Anteil, je Kanal (7 Tage), Monatswerte je Kanal (alle Konten, nur Summen).
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(180);
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
bl_bootstrap();
$mode=($_GET['mode'] ?? '')==='probe' ? 'probe' : 'report';
$days=max(1,min(60,(int)($_GET['days'] ?? 14)));
$c=fn($x)=>$x===null ? '–' : number_format((float)$x,3,',','.');
$pct=fn($x)=>round(100*(float)$x).' %';
echo "ChatHelp — ai-costs (".gmdate('c').") · Modus: $mode\n";
echo "Modell (db.php CLAUDE_MODEL): ".((defined('CLAUDE_MODEL') && CLAUDE_MODEL) ? CLAUDE_MODEL : '– (Kette '.implode(' → ',th_ai_models()).')')."\n";
$mf=is_file(__DIR__.'/.ai_model.json') ? __DIR__.'/.ai_model.json' : sys_get_temp_dir().'/ch_ai_model.json';
$mc=@json_decode((string)@file_get_contents($mf),true); echo "zuletzt genutzt: ".($mc['model'] ?? '–')."\n";
echo "Listenpreis \$/Mio. Tokens: ".json_encode(BL_AI_PRICES[$mc['model'] ?? 'claude-sonnet-5-5'] ?? BL_AI_PRICES['claude-sonnet-5-5'])." · Kurs 1 \$ = ".bl_usd_eur()." €\n";

if($mode==='probe'){
    if(!(defined('CLAUDE_KEY') && CLAUDE_KEY)){ echo "FEHLER: kein CLAUDE_KEY\n"; exit(1); }
    /* fester Systemtext (> 512 Tokens, Mindestlänge für den Cache) wie ein typischer Agent, dahinter wechselnde Uhrzeit */
    $kb=''; for($i=1;$i<=60;$i++) $kb.="Leistung $i: Haarschnitt Variante $i, Dauer ".(20+$i)." Minuten, Preis ".(25+$i)." Euro, buchbar Montag bis Samstag.\n";
    $sys="Du bist Lea, die freundliche Telefonassistenz von Salon Probe (Testbetrieb). Antworte kurz in einem Satz.\n\nWISSEN:\n".$kb.TH_AI_SPLIT."\nAktuelle Zeit: ".date('H:i:s');
    th_ai_context(0,'test'); $msgs=[]; $tot=['in'=>0,'out'=>0,'cache_read'=>0,'cache_write'=>0];
    foreach(['Hallo, was kostet Variante 3?','Und wie lange dauert Variante 10?','Habt ihr samstags offen?','Danke, tschüss!'] as $i=>$q){
        $msgs[]=['role'=>'user','content'=>$q]; $t0=microtime(true);
        $a=th_ai($sys,$msgs,120,40); $inf=th_ai_info(); $u=$inf['usage'] ?? ['in'=>0,'out'=>0,'cache_read'=>0,'cache_write'=>0];
        $eur=bl_cost_usd((string)($inf['model'] ?? 'claude-sonnet-5-5'),false,$u['in'],$u['out'],$u['cache_read'],$u['cache_write'])*bl_usd_eur();
        printf("Antwort %d (%s, %d ms): Eingabe %d · Cache gelesen %d · Cache geschrieben %d · Ausgabe %d → %s Cent\n",$i+1,$inf['model'] ?? '?',(int)((microtime(true)-$t0)*1000),$u['in'],$u['cache_read'],$u['cache_write'],$u['out'],$c(100*$eur));
        if($a===null){ echo "  KI-Fehler: ".json_encode($inf['errors'] ?? [])."\n"; break; }
        foreach($tot as $k=>$v) $tot[$k]+=$u[$k]; $msgs[]=['role'=>'assistant','content'=>$a];
    }
    $eur=bl_cost_usd((string)($inf['model'] ?? 'claude-sonnet-5-5'),false,$tot['in'],$tot['out'],$tot['cache_read'],$tot['cache_write'])*bl_usd_eur();
    $noCache=bl_cost_usd((string)($inf['model'] ?? 'claude-sonnet-5-5'),false,$tot['in']+$tot['cache_read']+$tot['cache_write'],$tot['out'])*bl_usd_eur();
    echo "Summe: ".$c(100*$eur)." Cent für 4 Antworten (Ø ".$c(25*$eur)." Cent) · ohne Cache wären es ".$c(100*$noCache)." Cent (Ø ".$c(25*$noCache).") · Cache-Anteil ".$pct(bl_cache_share($tot['in'],$tot['cache_read'],$tot['cache_write']))."\n";
    echo $tot['cache_read']>0 ? "OK: Prompt-Cache greift (Folgeantworten lesen den festen Teil aus dem Cache).\n" : "WARNUNG: keine Cache-Treffer – prüfen (Mindestlänge, wechselnder Inhalt vor der Grenze).\n";
}

echo "\n== Plattform je Tag (Claude über ChatHelp, alle Konten + Demo, ohne eigene Schlüssel) ==\n";
for($i=$days-1;$i>=0;$i--){ $d=date('Y-m-d',strtotime("-$i day")); $r=bl_answer_costs($d,date('Y-m-d',strtotime($d.' +1 day')))['total'];
    if($r['answers']) printf("%s  %6d Antworten · Ø %s Cent · Cache %s · %s € gesamt\n",$d,$r['answers'],$c($r['cent_per_answer']),$pct($r['cache_share']),number_format($r['eur'],2,',','.')); }
echo "\n== Je Kanal, letzte 7 Tage ==\n";
$w=bl_answer_costs(date('Y-m-d',strtotime('-6 day')),date('Y-m-d',strtotime('+1 day')));
foreach($w['channels'] as $r) printf("%-22s %6d Antworten · Ø %s Cent · Cache %s\n",$r['label'],$r['answers'],$c($r['cent_per_answer']),$pct($r['cache_share']));
printf("%-22s %6d Antworten · Ø %s Cent · Cache %s  (Ziel 0,52 Cent)\n",'GESAMT',$w['total']['answers'],$c($w['total']['cent_per_answer']),$pct($w['total']['cache_share']));

echo "\n== Monat ".date('Y-m')." je Kanal (aus ai_usage, alle Konten, vor Einführung der Tageswerte eingeschlossen) ==\n";
$s=th_db()->prepare("SELECT channel,model,peak,byok,SUM(calls) n,SUM(in_tok) i,SUM(out_tok) o,SUM(cache_read) cr,SUM(cache_write) cw FROM ai_usage WHERE ym=? GROUP BY channel,model,peak,byok"); $s->execute([date('Y-m')]);
$m=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){ if((int)$r['byok']) continue; $k=(string)$r['channel']; $m[$k]??=['n'=>0,'eur'=>0.0,'i'=>0,'cr'=>0,'cw'=>0];
    $m[$k]['n']+=(int)$r['n']; $m[$k]['eur']+=bl_cost_usd((string)$r['model'],(bool)(int)$r['peak'],(int)$r['i'],(int)$r['o'],(int)$r['cr'],(int)$r['cw'])*bl_usd_eur(); $m[$k]['i']+=(int)$r['i']; $m[$k]['cr']+=(int)$r['cr']; $m[$k]['cw']+=(int)$r['cw']; }
foreach($m as $k=>$r) printf("%-22s %6d Antworten · Ø %s Cent · Cache %s\n",BL_CHANNEL_LABELS[$k] ?? $k,$r['n'],$c($r['n'] ? 100*$r['eur']/$r['n'] : null),$pct(bl_cache_share($r['i'],$r['cr'],$r['cw'])));
echo "\n== Telefon-Gespräche, letzte 30 Tage (nur Summen/Mittelwerte – für die Annahmen im Studio) ==\n";
$g=th_db()->query("SELECT COUNT(*) n, AVG(TIMESTAMPDIFF(SECOND,started_at,ended_at)) dur, AVG(turns) turns, AVG(tokens) tok, SUM(turns) ans, SUM(TIMESTAMPDIFF(SECOND,started_at,ended_at)) secs,
    SUM(outcome='maxtime') maxed, SUM(outcome='lines_busy') busy FROM phone_calls WHERE started_at>=NOW()-INTERVAL 30 DAY AND ended_at IS NOT NULL AND turns>0")->fetch(PDO::FETCH_ASSOC);
if((int)$g['n']>0) printf("%d Gespräche · Ø %s Min. · Ø %s Antworten · Ø %s Tokens · %s Antworten je Gesprächsstunde · Höchstdauer erreicht: %d · Leitungen belegt: %d\n",
    $g['n'],number_format($g['dur']/60,1,',','.'),number_format($g['turns'],1,',','.'),number_format($g['tok'],0,',','.'),(float)$g['secs']>0 ? number_format(3600*$g['ans']/$g['secs'],0,',','.') : '–',(int)$g['maxed'],(int)$g['busy']);
else echo "noch keine abgeschlossenen Gespräche mit Antworten\n";
$A=bl_assumptions(); echo "Annahmen im Studio: {$A['answers_per_hour']} Antworten/Std., {$A['minutes_per_call']} Min./Gespräch\n";
echo "\n== Spracherkennung (Aufnahme-Modus, stt_usage_day) ==\n";
try{ $r=th_db()->query("SELECT provider, SUM(calls) n, SUM(seconds) s, SUM(ms_total) ms, SUM(errors) e FROM stt_usage_day WHERE d>=CURDATE()-INTERVAL 30 DAY GROUP BY provider")->fetchAll(PDO::FETCH_ASSOC);
    foreach($r as $x){ require_once __DIR__.'/stt-lib.php'; printf("%-9s %d Äußerungen · Ø %s s Audio · Ø %d ms Erkennung · Fehler %d · ≈ %s Cent je Äußerung (Gather: 1,72)\n",$x['provider'],$x['n'],number_format($x['s']/max(1,$x['n']),1,',','.'),(int)($x['ms']/max(1,$x['n'])),(int)$x['e'],number_format(100*stt_cost_usd($x['provider'],$x['s']/max(1,$x['n']))*bl_usd_eur(),3,',','.')); }
    if(!$r) echo "Aufnahme-Modus noch nicht genutzt (Standard: Twilio Gather)\n"; }catch(Throwable $e){ echo "Aufnahme-Modus noch nicht genutzt\n"; }
$bd=(int)th_db()->query("SELECT COUNT(DISTINCT account_id) FROM acct_secrets WHERE k='deepseek_key'")->fetchColumn();
echo "\nKonten mit eigenem DeepSeek-Schlüssel: $bd\n";
echo "\nfertig\n";
