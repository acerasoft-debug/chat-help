<?php
/**
 * ChatHelp ops — keybox: Sprachdienst-Schlüssel sicher auf den Server bringen, ohne Repo, ohne db.php, ohne Klartext im Log.
 *   mode=init   Schlüsselpaar des Servers anlegen (chat/.keybox.php, bleibt auf dem Server) und den öffentlichen Schlüssel ausgeben.
 *   mode=put    box=<Base64>: mit dem öffentlichen Schlüssel versiegeltes JSON {"ELEVENLABS_KEY":"…", …} → entschlüsseln, prüfen und in
 *               chat/tts-keys.php speichern (gleiche Dateirechte wie db.php). speech-lib.php lädt die Datei; Werte aus db.php haben Vorrang.
 *   mode=check  Namen der gespeicherten Schlüssel (nie Werte) + Prüfung bei ElevenLabs: Tarif, Zeichenkontingent, Stimmenplätze.
 *   mode=remove names=ELEVENLABS_KEY,… → Einträge löschen.
 * Erlaubte Namen: Sprachdienst-Schlüssel und Stimmen-Optionen (VS_KEY_NAMES). Gibt nie Schlüsselwerte aus.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(60);
const KB_FILE = __DIR__.'/.keybox.php';
const KB_KEYS = __DIR__.'/tts-keys.php';
const KB_NAMES = '/^(TWILIO_ACCOUNT_SID|TWILIO_AUTH_TOKEN|TWILIO_(BUNDLE|ADDRESS)_[A-Z]{2}|ELEVENLABS_KEY|AZURE_SPEECH_KEY|AZURE_SPEECH_REGION|GOOGLE_TTS_KEY|TTS_(DEMO_)?BUDGET_(ELEVENLABS|AZURE|GOOGLE|OPENAI)|TWILIO_PLAY_LANGS|TTS_ELEVENLABS_MODEL(_[A-Z]{2})?|TTS_PROVIDER(_[A-Z]{2})?|CH_ADMIN_EMAILS|DEEPSEEK_EU_(URL|KEY|MODEL)|DEEPGRAM_KEY)$/';
const KB_VALUE = '/^[A-Za-z0-9_\-.:,\/]{1,200}$/';

function kb_mode(): int { $m=@fileperms(__DIR__.'/db.php'); return $m ? ($m & 0777) : 0600; }
function kb_write(string $file, string $php): bool {
    $tmp=$file.'.tmp'.bin2hex(random_bytes(3));
    if(@file_put_contents($tmp,$php)===false) return false;
    @chmod($tmp,kb_mode());
    if(!@rename($tmp,$file)){ @unlink($tmp); return false; }
    return true;
}
function kb_load(): array { if(!is_file(KB_KEYS)) return []; $a=@include KB_KEYS; return is_array($a) ? $a : []; }
function kb_save(array $k): bool {
    ksort($k);
    return kb_write(KB_KEYS,"<?php\n/* ChatHelp – Sprachdienst-Schlüssel (nur auf dem Server; geschrieben von ops/keybox.php, nicht im Repo). Werte aus db.php haben Vorrang. */\nreturn ".var_export($k,true).";\n");
}
function kb_pair(): ?array { if(!is_file(KB_FILE)) return null; $a=@include KB_FILE; return is_array($a) && isset($a['kp']) ? $a : null; }

$mode=(string)($_GET['mode'] ?? 'check');
echo "ChatHelp — keybox (".gmdate('c').") · Modus: $mode\n";
if(!function_exists('sodium_crypto_box_seal_open')){ echo "FEHLER: PHP-Erweiterung sodium fehlt auf dem Server.\n"; exit(1); }

if($mode==='init'){
    $p=kb_pair();
    if(!$p || ($_GET['rotate'] ?? '')==='1'){
        $kp=sodium_crypto_box_keypair();
        if(!kb_write(KB_FILE,"<?php\n/* ChatHelp keybox – privater Schlüssel des Servers (nie weitergeben, nicht im Repo). */\nreturn ".var_export(['kp'=>base64_encode($kp),'at'=>gmdate('c')],true).";\n")){ echo "FEHLER: chat/.keybox.php nicht schreibbar\n"; exit(1); }
        echo "neues Schlüsselpaar angelegt\n"; $p=kb_pair();
    } else echo "Schlüsselpaar vorhanden (seit ".($p['at'] ?? '?').")\n";
    echo "PUBLIC_KEY=".base64_encode(sodium_crypto_box_publickey(base64_decode($p['kp'])))."\n";
}
elseif($mode==='put'){
    $p=kb_pair(); if(!$p){ echo "FEHLER: erst mode=init\n"; exit(1); }
    $ct=base64_decode((string)($_GET['box'] ?? ''),true);
    $pt=$ct!==false ? sodium_crypto_box_seal_open($ct,base64_decode($p['kp'])) : false;
    if($pt===false){ echo "FEHLER: Box lässt sich nicht öffnen (falscher öffentlicher Schlüssel?)\n"; exit(1); }
    $in=json_decode($pt,true); sodium_memzero($pt);
    if(!is_array($in) || !$in){ echo "FEHLER: Inhalt ist kein JSON-Objekt\n"; exit(1); }
    $k=kb_load(); $set=[];
    foreach($in as $n=>$v){
        if(!is_string($n) || !preg_match(KB_NAMES,$n)){ echo "übersprungen (Name nicht erlaubt): ".preg_replace('/[^A-Z0-9_]/','',(string)$n)."\n"; continue; }
        if(is_int($v)) $v=(string)$v;
        if(!is_string($v) || !preg_match(KB_VALUE,$v)){ echo "übersprungen (Wert ungültig): $n\n"; continue; }
        $k[$n]=$v; $set[]=$n.' ('.strlen($v).' Zeichen)';
    }
    if(!$set){ echo "nichts gespeichert\n"; exit(1); }
    if(!kb_save($k)){ echo "FEHLER: chat/tts-keys.php nicht schreibbar\n"; exit(1); }
    echo "gespeichert: ".implode(', ',$set)."\n";
    $mode='check';
}
elseif($mode==='remove'){
    $k=kb_load(); $del=[];
    foreach(array_map('trim',explode(',',(string)($_GET['names'] ?? ''))) as $n) if(isset($k[$n])){ unset($k[$n]); $del[]=$n; }
    kb_save($k); echo "entfernt: ".($del ? implode(', ',$del) : '–')."\n"; $mode='check';
}

if($mode==='check'){
    $k=kb_load();
    echo "chat/tts-keys.php: ".($k ? implode(', ',array_map(fn($n)=>$n.' ('.strlen((string)$k[$n]).' Zeichen)',array_keys($k))) : 'leer/fehlt')."\n";
    require_once __DIR__.'/tokens-lib.php';
    require_once __DIR__.'/speech-lib.php';
    $av=ch_tts_providers_available(); echo "Anbieter aktiv: ".implode(', ',array_map(fn($p)=>$p.'='.($av[$p] ? 'ja' : 'nein'),array_keys($av)))."\n";
    if($c=ch_tts_creds('elevenlabs')){
        $get=function(string $path) use($c){ $ch=curl_init('https://api.elevenlabs.io'.$path); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['xi-api-key: '.$c['key'],'Accept: application/json'],CURLOPT_TIMEOUT=>20]);
            $r=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return [$code,json_decode(is_string($r) ? $r : '',true)]; };
        [$code,$s]=$get('/v1/user/subscription');
        if($code===200 && is_array($s)){
            printf("ElevenLabs: Schlüssel gültig · Tarif %s · Zeichen %s / %s · Stimmenplätze %s · Status %s\n",$s['tier'] ?? '?',number_format((int)($s['character_count'] ?? 0),0,',','.'),number_format((int)($s['character_limit'] ?? 0),0,',','.'),$s['voice_limit'] ?? '?',$s['status'] ?? '?');
            [$c2,$v]=$get('/v1/voices'); if($c2===200 && is_array($v)){ $own=0; foreach((array)($v['voices'] ?? []) as $x) if(($x['category'] ?? '')!=='premade') $own++; echo "ElevenLabs: eigene/übernommene Stimmen in der Bibliothek: $own\n"; }
        } else echo "ElevenLabs: Konto-Abfrage nicht erlaubt (HTTP $code".(is_array($s) ? ' · '.json_encode($s['detail']['status'] ?? ($s['detail'] ?? ''),JSON_UNESCAPED_UNICODE) : '').") – Berechtigung „User: Read“ fehlt (nur für Tarif-/Platz-Anzeige nötig)\n";
        /* Berechtigungen einzeln prüfen: Sprachausgabe (6 Zeichen), Stimmen lesen (Library-Suche), Stimmen schreiben wird beim Scout geprüft */
        $ch=curl_init('https://api.elevenlabs.io/v1/text-to-speech/EXAVITQu4vr4xnSDxMaL?output_format=mp3_22050_32');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['text'=>'Hallo.','model_id'=>'eleven_multilingual_v2']),CURLOPT_HTTPHEADER=>['xi-api-key: '.$c['key'],'Content-Type: application/json','Accept: audio/mpeg'],CURLOPT_TIMEOUT=>20]);
        $a=curl_exec($ch); $tc=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        echo "ElevenLabs Sprachausgabe (text_to_speech): ".($tc===200 && is_string($a) && strlen($a)>500 ? 'OK ('.strlen($a).' Bytes Audio)' : 'FEHLER HTTP '.$tc.(is_string($a) ? ' '.mb_substr(preg_replace('/\s+/',' ',$a),0,160) : ''))."\n";
        [$vc,$vj]=$get('/v1/shared-voices?page_size=1&language=tr');
        echo "ElevenLabs Stimmen lesen (voices_read): ".($vc===200 ? 'OK' : 'FEHLT (HTTP '.$vc.') – im ElevenLabs-Konto beim Schlüssel „Voices“ auf Lesen + Schreiben stellen')."\n";
    }
    /* Zentrales Twilio-Konto (ChatHelp-Nummern): Konto erreichbar? Bundle/Adresse je Land vorhanden? – nur Status, nie Werte */
    if(is_file(__DIR__.'/number-lib.php')){ require_once __DIR__.'/number-lib.php';
        if($m=num_master()){ $r=num_http('GET','.json');
            echo "Twilio (ChatHelp-Nummern): ".($r['code']===200 ? 'Konto OK · Status '.preg_replace('/[^a-z_]/','',(string)($r['json']['status'] ?? '?')).' · Typ '.preg_replace('/[^A-Za-z_]/','',(string)($r['json']['type'] ?? '?')) : 'abgelehnt (HTTP '.$r['code'].')')."\n";
            foreach(['DE','AT','CH'] as $cc) echo "Twilio $cc: Bundle ".(defined('TWILIO_BUNDLE_'.$cc) ? 'ja' : 'nein').", Adresse ".(defined('TWILIO_ADDRESS_'.$cc) ? 'ja' : 'nein')."\n";
        } else echo "Twilio (ChatHelp-Nummern): nicht eingerichtet (TWILIO_ACCOUNT_SID + TWILIO_AUTH_TOKEN)\n";
    }
}
echo "FERTIG\n";
