<?php
/**
 * ChatHelp ops — deepseek-link: DeepSeek-Schlüssel der Recht-App (chat/api.php, config.php, Sicherungen) auf dem Server finden, bei DeepSeek
 * prüfen (Guthaben, Anthropic-kompatibler Endpunkt) und als Plattform-Ersatz freischalten: tts-keys.php ← DEEPSEEK_KEY + CH_DEEPSEEK_CN_FALLBACK=1.
 * Der Schlüssel verlässt den Server nie; ausgegeben werden nur Länge, Präfix und ein Hash-Kürzel.
 *   (ohne args)          suchen + prüfen, nichts schreiben
 *   {"apply":"1"}        speichern + Freigabe an, danach th_ai()-Probe (Claude → DeepSeek)
 *   {"primary":"deepseek"|"claude"}  Reihenfolge der Plattform-Anbieter setzen (CH_AI_PRIMARY; deepseek = günstiger zuerst, Claude als Ersatz) + Probe
 *   {"off":"1"}          Freigabe aus (Schlüssel bleibt gespeichert) · {"remove":"1"} Schlüssel + Freigabe entfernen
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(120);
if(PHP_SAPI!=='cli'){ http_response_code(404); exit; }
$CHAT=__DIR__; $KEYS=$CHAT.'/tts-keys.php'; $apply=($_GET['apply'] ?? '')==='1'; $off=($_GET['off'] ?? '')==='1'; $remove=($_GET['remove'] ?? '')==='1';
$primary=in_array($_GET['primary'] ?? '',['deepseek','claude'],true) ? (string)$_GET['primary'] : '';
echo "ChatHelp — deepseek-link (".gmdate('c').")\n";
$load=function() use($KEYS){ if(!is_file($KEYS)) return []; $a=@include $KEYS; return is_array($a) ? $a : []; };
$save=function(array $k) use($KEYS,$CHAT){ ksort($k); $m=@fileperms($CHAT.'/db.php'); $mode=$m ? ($m & 0777) : 0600; $tmp=$KEYS.'.tmp'.bin2hex(random_bytes(3));
    $php="<?php\n/* ChatHelp – Sprachdienst-Schlüssel (nur auf dem Server; geschrieben von ops/keybox.php, nicht im Repo). Werte aus db.php haben Vorrang. */\nreturn ".var_export($k,true).";\n";
    if(@file_put_contents($tmp,$php)===false) return false; @chmod($tmp,$mode); if(!@rename($tmp,$KEYS)){ @unlink($tmp); return false; } return true; };
$tag=fn(string $k)=>substr($k,0,3).'…('.strlen($k).' Zeichen, #'.substr(hash('sha256',$k),0,6).')';
$stored=$load();
echo "tts-keys.php jetzt: ".(implode(', ',array_keys($stored)) ?: '(leer)')."\n";
if($off || $remove){
    if($remove) unset($stored['DEEPSEEK_KEY']); unset($stored['CH_DEEPSEEK_CN_FALLBACK'],$stored['CH_AI_PRIMARY']);
    echo ($save($stored) ? 'gespeichert' : 'FEHLER beim Schreiben').": ".(implode(', ',array_keys($stored)) ?: '(leer)')."\n"; echo "FERTIG\n"; exit;
}
if($primary!==''){
    if($primary==='deepseek' && empty($stored['DEEPSEEK_KEY'])){ echo "Erst den Schlüssel speichern ({\"apply\":\"1\"}).\nFERTIG\n"; exit(1); }
    if($primary==='deepseek') $stored['CH_AI_PRIMARY']='deepseek'; else unset($stored['CH_AI_PRIMARY']);
    echo ($save($stored) ? 'gespeichert' : 'FEHLER beim Schreiben').": ".implode(', ',array_keys($stored))."\n";
    require_once $CHAT.'/tokens-lib.php'; require_once $CHAT.'/plans.php';
    echo "Reihenfolge jetzt: ".(th_ai_primary()==='deepseek' ? 'DeepSeek → Claude' : 'Claude → DeepSeek'.(th_ai_cn_fallback_ok() ? '' : ' (DeepSeek-Ersatz AUS)'))."\n";
    $t=microtime(true); $r=th_ai('Antworte nur mit dem Wort OK.',[['role'=>'user','content'=>'Hallo']],20,40); $i=th_ai_info();
    echo "th_ai(): ".var_export($r,true)." (".sprintf('%.2f',microtime(true)-$t)."s) · Anbieter ".($i['provider'] ?? '–')." · Modell ".($i['model'] ?? '–')."\n";
    if($i['errors'] ?? []) echo "  Kette: ".implode(' | ',array_map(fn($e)=>mb_substr($e,0,70),$i['errors']))."\n";
    echo "FERTIG\n"; exit;
}
/* 1) Kandidaten der Recht-App */
$files=array_unique(array_filter(array_merge(
    [$CHAT.'/api.php',$CHAT.'/config.php',$CHAT.'/chatapp/api.php',$CHAT.'/wp-api.php',dirname($CHAT).'/api.php',dirname($CHAT).'/config.php'],
    glob($CHAT.'/api.php.bak*') ?: [], glob($CHAT.'/*.php.bak*') ?: [], glob($CHAT.'/config*.php') ?: [], glob(dirname($CHAT).'/chatapp/*.php') ?: []
),'is_file'));
$found=[];   // key => [dateien]
foreach($files as $f){
    $src=(string)@file_get_contents($f,false,null,0,400000);
    if(preg_match_all('/DEEPSEEK[^\n]{0,80}?(sk-[A-Za-z0-9]{20,120})/',$src,$mm)) foreach($mm[1] as $k) $found[$k][]=basename($f);
}
if(!$found){   // breiter: alle PHP-Dateien im chat-Ordner außer den Plattformdateien
    foreach(glob($CHAT.'/*.php') ?: [] as $f){ if(preg_match('/^(tokens-|billing-|tts-keys|_ops_|_opc_)/',basename($f))) continue;
        $src=(string)@file_get_contents($f,false,null,0,400000); if(preg_match_all('/DEEPSEEK[^\n]{0,80}?(sk-[A-Za-z0-9]{20,120})/',$src,$mm)) foreach($mm[1] as $k) $found[$k][]=basename($f); }
}
echo "Fundstellen: ".count($found)." Schlüssel in ".count($files)." Kandidaten\n";
if(!$found){ echo "KEIN DeepSeek-Schlüssel in den Dateien der Recht-App gefunden.\nFERTIG\n"; exit(1); }
/* 2) bei DeepSeek prüfen: Guthaben + Anthropic-kompatibler Endpunkt */
$probe=function(string $k): array {
    $ch=curl_init('https://api.deepseek.com/user/balance'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$k]]);
    $b=json_decode((string)curl_exec($ch),true) ?: []; $bc=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $bal=''; foreach((array)($b['balance_infos'] ?? []) as $bi) $bal.=($bal ? ' · ' : '').($bi['total_balance'] ?? '?').' '.($bi['currency'] ?? '');
    $ch=curl_init('https://api.deepseek.com/anthropic/v1/messages'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>40,
        CURLOPT_POSTFIELDS=>json_encode(['model'=>'deepseek-chat','max_tokens'=>8,'messages'=>[['role'=>'user','content'=>'Sag OK']]]),
        CURLOPT_HTTPHEADER=>['x-api-key: '.$k,'anthropic-version: 2023-06-01','content-type: application/json']]);
    $r=json_decode((string)curl_exec($ch),true) ?: []; $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return ['balance_http'=>$bc,'available'=>!empty($b['is_available']),'balance'=>$bal,'msg_http'=>$c,'text'=>mb_substr((string)($r['content'][0]['text'] ?? ($r['error']['message'] ?? '')),0,80)];
};
$best=null;
foreach($found as $k=>$fs){ $p=$probe($k);
    echo "  ".$tag($k)." aus ".implode(', ',array_unique($fs)).": Guthaben HTTP {$p['balance_http']} ".($p['available'] ? 'verfügbar' : 'nicht verfügbar')." ".$p['balance']." · Nachricht HTTP {$p['msg_http']} „{$p['text']}“\n";
    if($p['msg_http']===200 && !$best) $best=$k; }
if(!$best){ echo "Kein Schlüssel antwortet mit 200 (Guthaben aufladen: platform.deepseek.com → Top up).\nFERTIG\n"; exit(1); }
echo "verwendbar: ".$tag($best)."\n";
if(!$apply){ echo "(nur geprüft – mit args {\"apply\":\"1\"} speichern + freischalten)\nFERTIG\n"; exit; }
/* 3) speichern + Freigabe */
$stored['DEEPSEEK_KEY']=$best; $stored['CH_DEEPSEEK_CN_FALLBACK']='1';
echo ($save($stored) ? 'gespeichert' : 'FEHLER beim Schreiben').": ".implode(', ',array_keys($stored))."\n";
/* 4) Probe über die Plattform-Kette (db.php hat Vorrang; dort gibt es keinen DeepSeek-Schlüssel) */
require_once $CHAT.'/tokens-lib.php'; require_once $CHAT.'/plans.php';
echo "th_ai_cn_fallback_ok(): ".(function_exists('th_ai_cn_fallback_ok') && th_ai_cn_fallback_ok() ? 'an' : 'AUS')."\n";
$t=microtime(true); $r=th_ai('Antworte nur mit dem Wort OK.',[['role'=>'user','content'=>'Hallo']],20,40); $i=th_ai_info();
echo "th_ai(): ".var_export($r,true)." (".sprintf('%.2f',microtime(true)-$t)."s) · Anbieter ".($i['provider'] ?? '–')." · Modell ".($i['model'] ?? '–')."\n";
if($i['errors'] ?? []) echo "  Kette: ".implode(' | ',array_map(fn($e)=>mb_substr($e,0,70),$i['errors']))."\n";
echo "FERTIG\n";
