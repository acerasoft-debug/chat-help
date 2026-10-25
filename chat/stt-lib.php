<?php
/**
 * ChatHelp — günstigere Spracherkennung am Telefon (statt Twilio <Gather input="speech">, ~2 Cent je Äußerung)
 * ===========================================================================================================
 * Modus „record“: Twilio nimmt die Äußerung auf (<Record>, ohne Piepton, Stille 2 s), der Webhook lädt die Aufnahme, lässt sie
 * transkribieren und löscht sie sofort bei Twilio. Läuft auf normalem PHP-Hosting (kein WebSocket-Dienst nötig).
 *   Deepgram Nova-3 (EU-Endpunkt api.eu.deepgram.com, mip_opt_out) ≈ 0,0043 $/Min. · Azure Speech (Region aus AZURE_SPEECH_REGION) ≈ 1 $/Std.
 *   Gegenüber Gather: ~1–3 s mehr je Antwort, Anrufer kann den Agenten nicht unterbrechen → erst nach Testanrufen als Standard.
 * Einstellung (Studio-Betreiber bzw. ch_settings 'phone_stt'): ['mode'=>'gather'|'record','provider'=>'deepgram'|'azure','scope'=>'central'|'all'].
 * Schlüssel über ops/keybox: DEEPGRAM_KEY bzw. AZURE_SPEECH_KEY + AZURE_SPEECH_REGION. Ohne Schlüssel/Konto bleibt es bei Gather.
 * Verbrauch je Tag (stt_usage_day): Aufrufe, Sekunden Audio, Wartezeit, Fehler → ops/ai-costs zeigt Kosten und Latenz.
 */

const STT_PRICE_USD_MIN = ['deepgram'=>0.0043,'azure'=>1.0/60,'twilio'=>0.0];   // Listenpreise je Minute Audio (Deepgram Nova-3 pre-recorded, Azure real-time)
const STT_TWILIO_REC_USD_MIN = 0.0025;   // Twilio-Aufnahme je Minute (wird sofort gelöscht)
const STT_DEEPGRAM_LANGS = ['de','en','tr','fr','es','it','ru','nl','sv','no','fi','ja'];   // Deepgram Nova-3: kein Aserbaidschanisch → Gather

function stt_cfg(): array {
    $s=function_exists('ch_setting') ? (array)ch_setting('phone_stt',[]) : [];
    $mode=($s['mode'] ?? 'gather')==='record' ? 'record' : 'gather'; $prov=in_array($s['provider'] ?? '',['deepgram','azure'],true) ? $s['provider'] : 'deepgram';
    return ['mode'=>$mode,'provider'=>$prov,'scope'=>($s['scope'] ?? 'central')==='all' ? 'all' : 'central'];
}
function stt_key(string $prov): string {
    if($prov==='deepgram') return defined('DEEPGRAM_KEY') ? trim((string)DEEPGRAM_KEY) : '';
    if($prov==='azure') return (defined('AZURE_SPEECH_KEY') && defined('AZURE_SPEECH_REGION')) ? trim((string)AZURE_SPEECH_KEY) : '';
    return '';
}
/** Für diesen Anruf: Anbieter für den Aufnahme-Modus oder '' (= Twilio Gather). $central = Anruf über das Plattform-Konto. */
function stt_mode_for(bool $central, string $lang2): string {
    $c=stt_cfg(); if($c['mode']!=='record' || ($c['scope']==='central' && !$central)) return '';
    $p=$c['provider']; if(stt_key($p)==='') return '';
    if($p==='deepgram' && !in_array($lang2,STT_DEEPGRAM_LANGS,true)) return stt_key('azure')!=='' ? 'azure' : '';
    return $p;
}
/** TwiML: nach der Ansage aufnehmen; Stille ohne Aufnahme → gleiche Action ohne RecordingUrl (= „nichts gehört“). */
function stt_record_twiml(string $inner, string $action): string {
    $a=$action.(str_contains($action,'?') ? '&' : '?').'rec=1'; $e=fn($s)=>htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');
    return $inner.'<Record action="'.$e($a).'" method="POST" maxLength="45" timeout="2" playBeep="false" trim="trim-silence" finishOnKey=""/><Redirect method="POST">'.$e($a.'&empty=1').'</Redirect>';
}
/** Aufnahme laden (Twilio stellt sie u. U. erst kurz nach dem Callback bereit → kurz wiederholen). */
function stt_fetch_recording(string $url, string $sid, string $tok): ?string {
    if(!preg_match('#^https://api\.twilio\.com/#',$url) && !(in_array(PHP_SAPI,['cli','cli-server'],true) && defined('STT_TEST_TWILIO'))) return null;
    for($i=0;$i<6;$i++){
        $ch=curl_init($url.'.wav'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_USERPWD=>$sid.':'.$tok,CURLOPT_FOLLOWLOCATION=>true]);
        $b=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if($code===200 && is_string($b) && strlen($b)>44) return $b;
        usleep(350000);
    }
    return null;
}
function stt_delete_recording(string $url, string $sid, string $tok): void {
    $ch=curl_init($url.'.json'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_CUSTOMREQUEST=>'DELETE',CURLOPT_USERPWD=>$sid.':'.$tok]); curl_exec($ch); curl_close($ch);
}
/** WAV → Text. $lang2 = Gesprächssprache, $detect = Sprache selbst erkennen (erste Äußerung). Rückgabe ['text','conf','lang','ms','sec','error']. */
function stt_transcribe(string $wav, string $prov, string $lang2, bool $detect=false): array {
    $t0=microtime(true); $sec=stt_wav_seconds($wav); $out=['text'=>'','conf'=>0.0,'lang'=>$lang2,'ms'=>0,'sec'=>$sec,'error'=>''];
    if(isset($GLOBALS['__stt_mock']) && is_callable($GLOBALS['__stt_mock']) && in_array(PHP_SAPI,['cli','cli-server'],true)){ $r=($GLOBALS['__stt_mock'])($wav,$prov,$lang2,$detect); return $r+$out; }
    $key=stt_key($prov); if($key===''){ $out['error']='no_key'; return $out; }
    if($prov==='deepgram'){
        $q=['model'=>'nova-3','smart_format'=>'true','punctuate'=>'true','mip_opt_out'=>'true']; if($detect) $q['detect_language']='true'; else $q['language']=$lang2;
        $base=defined('STT_DEEPGRAM_BASE') ? (string)STT_DEEPGRAM_BASE : 'https://api.eu.deepgram.com';
        $ch=curl_init($base.'/v1/listen?'.http_build_query($q)); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$wav,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Token '.$key,'Content-Type: audio/wav']]);
        $r=json_decode((string)curl_exec($ch),true); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        $alt=$r['results']['channels'][0]['alternatives'][0] ?? null;
        if($code===200 && is_array($alt)){ $out['text']=trim((string)($alt['transcript'] ?? '')); $out['conf']=(float)($alt['confidence'] ?? 0); $dl=(string)($r['results']['channels'][0]['detected_language'] ?? ''); if($dl!=='') $out['lang']=strtolower(substr($dl,0,2)); }
        else $out['error']='deepgram '.$code.' '.mb_substr((string)($r['err_msg'] ?? ($r['message'] ?? '')),0,80);
    } else {
        $region=preg_replace('/[^a-z0-9]/','',strtolower((string)AZURE_SPEECH_REGION)); $tag=function_exists('ch_lang_tag') ? ch_lang_tag($lang2) : 'de-DE';
        $ch=curl_init('https://'.$region.'.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1?'.http_build_query(['language'=>$tag,'format'=>'detailed','profanity'=>'raw']));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$wav,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Ocp-Apim-Subscription-Key: '.$key,'Content-Type: audio/wav','Accept: application/json']]);
        $r=json_decode((string)curl_exec($ch),true); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if($code===200 && ($r['RecognitionStatus'] ?? '')==='Success'){ $nb=$r['NBest'][0] ?? []; $out['text']=trim((string)($nb['Display'] ?? ($r['DisplayText'] ?? ''))); $out['conf']=(float)($nb['Confidence'] ?? 0.8); }
        elseif($code===200){ $out['text']=''; }   // NoMatch / InitialSilenceTimeout = nichts verstanden
        else $out['error']='azure '.$code;
    }
    $out['ms']=(int)round((microtime(true)-$t0)*1000);
    return $out;
}
/** Dauer einer WAV-Datei (μ-law/PCM, aus dem Header). */
function stt_wav_seconds(string $wav): float {
    if(strlen($wav)<44 || substr($wav,0,4)!=='RIFF') return 0.0;
    $u=unpack('vfmt/vch/Vrate/Vbyterate',substr($wav,20,12)); $br=(int)($u['byterate'] ?? 0); if($br<=0) return 0.0;
    $pos=12; $len=strlen($wav); while($pos+8<=$len){ $id=substr($wav,$pos,4); $sz=unpack('V',substr($wav,$pos+4,4))[1]; if($id==='data') return round(min($sz,$len-$pos-8)/$br,2); $pos+=8+$sz+($sz%2); }
    return round(($len-44)/$br,2);
}
/** Verbrauch je Tag (für Kosten/Latenz-Bericht). */
function stt_usage_log(string $prov, float $sec, int $ms, bool $err): void {
    try{ th_db()->exec("CREATE TABLE IF NOT EXISTS stt_usage_day (d DATE NOT NULL, provider VARCHAR(12) NOT NULL, calls INT NOT NULL DEFAULT 0, seconds DECIMAL(12,2) NOT NULL DEFAULT 0, ms_total BIGINT NOT NULL DEFAULT 0, errors INT NOT NULL DEFAULT 0, PRIMARY KEY(d,provider)) ENGINE=InnoDB");
        th_db()->prepare("INSERT INTO stt_usage_day(d,provider,calls,seconds,ms_total,errors) VALUES(?,?,1,?,?,?) ON DUPLICATE KEY UPDATE calls=calls+1,seconds=seconds+VALUES(seconds),ms_total=ms_total+VALUES(ms_total),errors=errors+VALUES(errors)")
          ->execute([date('Y-m-d'),substr($prov,0,12),$sec,$ms,$err ? 1 : 0]); }catch(Throwable $e){ error_log('CH stt usage: '.$e->getMessage()); }
}
/** Kosten je Äußerung in US-Dollar (Anbieter + Twilio-Aufnahme); Twilio Gather zum Vergleich 0,02 $. */
function stt_cost_usd(string $prov, float $sec): float { return ($sec/60)*((STT_PRICE_USD_MIN[$prov] ?? 0)+STT_TWILIO_REC_USD_MIN); }
/** Aufnahme eines Anrufs → Text (Laden, Transkribieren, sofort löschen, Verbrauch buchen). */
function stt_from_twilio(string $recUrl, string $sid, string $tok, string $prov, string $lang2, bool $detect): array {
    $wav=stt_fetch_recording($recUrl,$sid,$tok);
    if($wav===null){ stt_usage_log($prov,0,0,true); return ['text'=>'','conf'=>0.0,'lang'=>$lang2,'ms'=>0,'sec'=>0,'error'=>'recording_unavailable']; }
    $r=stt_transcribe($wav,$prov,$lang2,$detect);
    stt_delete_recording($recUrl,$sid,$tok);
    stt_usage_log($prov,(float)$r['sec'],(int)$r['ms'],$r['error']!=='');
    return $r;
}
