<?php
/**
 * ChatHelp ops — stt-test: Qualität, Latenz und Kosten der Spracherkennung im Aufnahme-Modus (stt-lib) mit Telefon-Audio.
 *   Testsätze (feste Beispielsätze, keine echten Gespräche) → Server-Stimme (ElevenLabs, sonst Azure) als 8-kHz-μ-law wie am Telefon →
 *   WAV wie eine Twilio-Aufnahme (PCM 16 Bit, 8 kHz) → Deepgram (EU) und/oder Azure → Wortfehlerrate (WER), Latenz, Kosten je Äußerung.
 *   args (JSON → $_GET): langs = "de,tr,en,az" (Standard) · provs = "deepgram,azure" (nur mit Schlüssel)
 * Ausgabe: je Sprache und Anbieter Ø WER, Ø Latenz, Ø Audiolänge, Cent je Äußerung; Vergleich mit Twilio Gather (≈ 1,72 Cent je Antwort).
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(240);
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/speech-lib.php';
require_once __DIR__.'/stt-lib.php';
bl_bootstrap();
const STT_T_SENT = [
  'de'=>['Guten Tag, ich hätte gern einen Termin am Donnerstag um 15 Uhr.','Meine Telefonnummer ist 0171 2345678.','Haben Sie am Samstag geöffnet und was kostet eine Zahnreinigung?','Ich heiße Müller und möchte meinen Termin leider absagen.'],
  'tr'=>['Merhaba, perşembe günü saat 15 için randevu almak istiyorum.','Telefon numaram 0532 123 45 67.','Cumartesi açık mısınız ve diş temizliği ne kadar?','Adım Yılmaz, randevumu iptal etmek istiyorum.'],
  'en'=>['Hello, I would like to book an appointment on Thursday at 3 pm.','My phone number is 07700 900123.','Are you open on Saturday and how much is a cleaning?','My name is Smith and I need to cancel my appointment.'],
  'az'=>['Salam, cümə axşamı saat 15 üçün qəbula yazılmaq istəyirəm.','Telefon nömrəm 050 123 45 67.','Şənbə günü açıqsınız və diş təmizlənməsi neçəyədir?','Adım Məmmədovdur, qəbulumu ləğv etmək istəyirəm.'],
];
$langs=array_values(array_intersect(array_map('trim',explode(',',(string)($_GET['langs'] ?? 'de,tr,en,az'))),array_keys(STT_T_SENT)));
$provs=array_values(array_filter(array_map('trim',explode(',',(string)($_GET['provs'] ?? 'deepgram,azure'))),fn($p)=>in_array($p,['deepgram','azure'],true) && stt_key($p)!==''));
echo "ChatHelp — stt-test (".gmdate('c').")\nSpracherkennung mit Schlüssel: ".($provs ? implode(', ',$provs) : 'keine')."\n";
if(!$provs){ echo "Kein DEEPGRAM_KEY bzw. AZURE_SPEECH_KEY/REGION hinterlegt (ops/keybox) – Test übersprungen.\n"; exit(0); }
$tts=ch_tts_creds('elevenlabs') ? 'elevenlabs' : (ch_tts_creds('azure') ? 'azure' : '');
if($tts===''){ echo "Keine Server-Stimme (ElevenLabs/Azure) für die Testsätze.\n"; exit(1); }
echo "Testsätze gesprochen von: $tts (8 kHz μ-law wie am Telefon)\n";

/** μ-law → PCM 16 Bit (G.711). */
function stt_t_ulaw(string $u): string {
    static $t=null; if($t===null){ $t=[]; for($i=0;$i<256;$i++){ $x=~$i & 0xFF; $s=((($x&0x0F)<<3)+0x84)<<(($x>>4)&7); $s-=0x84; $t[$i]=pack('v',(($x&0x80) ? -$s : $s) & 0xFFFF); } }
    $o=''; $n=strlen($u); for($i=0;$i<$n;$i++) $o.=$t[ord($u[$i])]; return $o;
}
function stt_t_wav(string $pcm): string { return 'RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,1,8000,16000,2,16).'data'.pack('V',strlen($pcm)).$pcm; }
/** Satz → Telefon-WAV (null bei Fehler). */
function stt_t_say(string $prov, string $text, string $lang): ?string {
    $v=ch_tts_voice_for(['gender'=>'f'],null,$lang); $v['channel']='ops'; $req=ch_tts_request($prov,$text,$v,ch_tts_creds($prov)); if(!$req) return null;
    if($prov==='elevenlabs') $req['url']=str_replace('output_format=mp3_44100_128','output_format=ulaw_8000',$req['url']);
    else $req['headers']=array_map(fn($h)=>str_starts_with($h,'X-Microsoft-OutputFormat:') ? 'X-Microsoft-OutputFormat: raw-8khz-8bit-mono-mulaw' : $h,$req['headers']);
    $req['headers']=array_map(fn($h)=>str_starts_with($h,'Accept: audio') ? 'Accept: */*' : $h,$req['headers']);
    $ch=curl_init($req['url']); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$req['body'],CURLOPT_HTTPHEADER=>$req['headers'],CURLOPT_TIMEOUT=>30]);
    $b=(string)curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code!==200 || strlen($b)<800){ echo "  Stimme $prov $lang: HTTP $code\n"; return null; }
    ch_tts_usage_add($prov,'ops',mb_strlen($text));
    return stt_t_wav(stt_t_ulaw($b));
}
function stt_t_words(string $s): array { $s=mb_strtolower($s); $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s); return array_values(array_filter(explode(' ',(string)$s),'strlen')); }
/** Wortfehlerrate: Levenshtein über Wörter / Wörter der Referenz. */
function stt_t_wer(string $ref, string $hyp): float {
    $r=stt_t_words($ref); $h=stt_t_words($hyp); $n=count($r); $m=count($h); if(!$n) return $m ? 1.0 : 0.0;
    $d=range(0,$m); for($i=1;$i<=$n;$i++){ $p=$d; $d[0]=$i; for($j=1;$j<=$m;$j++) $d[$j]=min($p[$j]+1,$d[$j-1]+1,$p[$j-1]+($r[$i-1]===$h[$j-1] ? 0 : 1)); }
    return $d[$m]/$n;
}
$sum=[];
foreach($langs as $l){
    echo "\n== $l ==\n";
    foreach(STT_T_SENT[$l] as $i=>$ref){
        $wav=stt_t_say($tts,$ref,$l); if($wav===null) continue;
        foreach($provs as $p){
            if($p==='deepgram' && !in_array($l,STT_DEEPGRAM_LANGS,true)) continue;
            $r=stt_transcribe($wav,$p,$l,false); $w=$r['error']==='' ? stt_t_wer($ref,$r['text']) : 1.0;
            printf("  %-8s %d) WER %3d %% · %4d ms · %s s%s\n",$p,$i+1,(int)round(100*$w),$r['ms'],number_format((float)$r['sec'],1,',','.'),$r['error']!=='' ? ' · Fehler '.$r['error'] : ($w>0 ? ' · „'.mb_substr($r['text'],0,90).'“' : ''));
            $k="$l|$p"; $sum[$k]??=['n'=>0,'wer'=>0.0,'ms'=>0,'sec'=>0.0,'err'=>0]; $sum[$k]['n']++; $sum[$k]['wer']+=$w; $sum[$k]['ms']+=$r['ms']; $sum[$k]['sec']+=$r['sec']; $sum[$k]['err']+=$r['error']!=='' ? 1 : 0;
        }
    }
}
echo "\n== Zusammenfassung (Twilio Gather zum Vergleich: ≈ 1,72 Cent je Antwort, Latenz in Twilio enthalten) ==\n";
foreach($sum as $k=>$s){ [$l,$p]=explode('|',$k); $n=max(1,$s['n']);
    printf("%-3s %-8s %d Sätze · Ø WER %d %% · Ø %d ms · Ø %s s Audio · ≈ %s Cent je Äußerung · Fehler %d\n",$l,$p,$s['n'],(int)round(100*$s['wer']/$n),(int)($s['ms']/$n),number_format($s['sec']/$n,1,',','.'),
        number_format(100*stt_cost_usd($p,$s['sec']/$n)*bl_usd_eur(),3,',','.'),$s['err']); }
echo "\nHinweis: synthetische Stimme ohne Leitungsrauschen – echte Anrufe vor dem Umschalten mit einem Testanruf prüfen (Studio → Admin → Spracherkennung).\nfertig\n";
