<?php
/** ChatHelp ops — el-stt-test (temporär, nur CLI): Aserbaidschanisch über ElevenLabs – Sprachausgabe erzeugen und mit ElevenLabs Speech-to-Text zurückerkennen. Gibt nie Schlüssel aus. */
if(PHP_SAPI!=='cli'){ http_response_code(404); exit; }
@set_time_limit(300);
require_once __DIR__.'/tokens-lib.php'; require_once __DIR__.'/speech-lib.php';
echo "ChatHelp — el-stt-test (".gmdate('c').")\n";
$c=ch_tts_creds('elevenlabs'); if(!$c){ echo "kein ElevenLabs-Schlüssel\n"; exit(0); }
$S=[['az','Salam, sabah saat onda görüş təyin etmək istəyirəm. Mənim adım Leyla.'],['az','Ödənişi nağd edəcəm, ünvan Nizami küçəsi on iki.'],['tr','Merhaba, yarın saat onda randevu almak istiyorum.']];
foreach($S as [$l,$txt]){
    $info=null; $t=microtime(true); $mp3=null; try{ $mp3=ch_tts_synth($txt,['lang'=>$l,'gender'=>'f','channel'=>'studio'],$info); }catch(Throwable $e){ echo "TTS-Fehler: ".$e->getMessage()."\n"; }
    echo "\n[$l] TTS ".(is_string($mp3) ? strlen($mp3).' Bytes' : 'FEHLT')." · ".json_encode(array_intersect_key((array)$info,array_flip(['provider','model','voice','lang','cached'])),JSON_UNESCAPED_UNICODE)." · ".round(microtime(true)-$t,1)."s\n";
    if(!is_string($mp3) || $mp3==='') continue;
    $tmp=tempnam(sys_get_temp_dir(),'els').'.mp3'; file_put_contents($tmp,$mp3);
    foreach([['scribe_v1',''],['scribe_v1',$l==='az' ? 'aze' : 'tur'],['scribe_v2','']] as [$model,$lc]){
        $f=['model_id'=>$model,'file'=>new CURLFile($tmp,'audio/mpeg','a.mp3'),'tag_audio_events'=>'false']; if($lc!=='') $f['language_code']=$lc;
        $ch=curl_init('https://api.elevenlabs.io/v1/speech-to-text'); $t=microtime(true);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$f,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['xi-api-key: '.$c['key']]]);
        $raw=(string)curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $j=json_decode($raw,true);
        echo sprintf("  STT %-10s lc=%-4s HTTP %d %4dms · erkannt=%s (%.2f) · %s\n",$model,$lc ?: 'auto',$code,(microtime(true)-$t)*1000,(string)($j['language_code'] ?? '-'),(float)($j['language_probability'] ?? 0),
            $code===200 ? mb_substr((string)($j['text'] ?? ''),0,120) : mb_substr(preg_replace('/\s+/',' ',json_encode($j['detail'] ?? $raw,JSON_UNESCAPED_UNICODE)),0,220));
    }
    @unlink($tmp);
}
echo "\nfertig\n";
