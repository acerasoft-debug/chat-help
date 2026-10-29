<?php
/**
 * ChatHelp ops — ai-check: KI zinciri teşhisi (yazmaz)
 * ====================================================
 * • db.php'de hangi KI anahtarları tanımlı (yalnız adlar), model zinciri, model önbelleği
 * • Her Claude modeli için 5-token'lık ham istek: HTTP kodu + hata türü/mesajı (anahtar yazılmaz)
 * • th_ai() canlı çağrı: sonuç + sağlayıcı/model + hata listesi
 * • Canlı concierge POST (genel + demo) ham yanıt (502 teşhisi) · PHP error_log son satırları ("CH " / "Fatal")
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(180);
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
echo "ChatHelp — ai-check  (".date('c').")\n\n";
echo "=== 1) Anahtarlar / zincir ===\n";
foreach (['CLAUDE_KEY','CLAUDE_MODEL','DEEPSEEK_KEY','CH_DEEPSEEK_CN_FALLBACK','DEEPSEEK_EU_KEY','DEEPSEEK_EU_URL','TWILIO_AUTH_TOKEN','TWILIO_VOICE','BREVO_KEY','MAIL_FROM'] as $k) echo "  $k: ".(defined($k) ? (constant($k) ? 'VAR ('.strlen((string)constant($k)).' Zeichen)' : 'leer') : 'yok')."\n";
echo "  Modeller: ".implode(' → ', th_ai_models())."\n";
foreach ([__DIR__.'/.ai_model.json', sys_get_temp_dir().'/ch_ai_model.json'] as $f) echo "  Önbellek $f: ".(is_file($f) ? file_get_contents($f) : 'yok')." (dizin yazılabilir: ".(is_writable(dirname($f))?'evet':'HAYIR').")\n";

echo "\n=== 2) Modeller tek tek (ham) ===\n";
if (defined('CLAUDE_KEY') && CLAUDE_KEY) {
    foreach (array_unique(array_merge(th_ai_models(), ['claude-sonnet-4-6','claude-sonnet-4-5','claude-opus-4-1','claude-3-5-sonnet-20241022'])) as $m) {
        $ch=curl_init('https://api.anthropic.com/v1/messages'); $t=microtime(true);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>30,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>$m,'max_tokens'=>5,'messages'=>[['role'=>'user','content'=>'Sag OK']]]),
            CURLOPT_HTTPHEADER=>['x-api-key: '.CLAUDE_KEY,'anthropic-version: 2023-06-01','content-type: application/json']]);
        $raw=(string)curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); $j=json_decode($raw,true);
        echo sprintf("  %-32s HTTP %s  %.2fs  %s\n", $m, $code ?: 'ERR', microtime(true)-$t, $code===200 ? 'OK: '.mb_substr((string)($j['content'][0]['text'] ?? ''),0,30) : (($j['error']['type'] ?? $err).' · '.mb_substr((string)($j['error']['message'] ?? $raw),0,140)));
    }
} else echo "  CLAUDE_KEY yok\n";

echo "\n=== 2b) DeepSeek platform yedeği (Anthropic uyumlu uç nokta, ham) ===\n";
echo "  yedek etkin (th_ai_cn_fallback_ok): ".(function_exists('th_ai_cn_fallback_ok') && th_ai_cn_fallback_ok() ? 'EVET' : 'hayır')."\n";
if (defined('DEEPSEEK_KEY') && DEEPSEEK_KEY && function_exists('th_ai_cn_route')) {
    $rt=th_ai_cn_route();
    foreach ($rt['models'] as $m) {
        $ch=curl_init($rt['url']); $t=microtime(true);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>40,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>$m,'max_tokens'=>8,'messages'=>[['role'=>'user','content'=>'Sag OK']]]+$rt['extra']),
            CURLOPT_HTTPHEADER=>['x-api-key: '.$rt['key'],'anthropic-version: 2023-06-01','content-type: application/json']]);
        $raw=(string)curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); $j=json_decode($raw,true);
        echo sprintf("  %-20s HTTP %s  %.2fs  %s\n", $m, $code ?: 'ERR', microtime(true)-$t, $code===200 ? 'OK: '.mb_substr((string)($j['content'][0]['text'] ?? ''),0,30) : (($j['error']['type'] ?? $err).' '.mb_substr((string)($j['error']['message'] ?? ''),0,120)));
    }
} else echo "  DEEPSEEK_KEY yok\n";

echo "\n=== 3) th_ai() canlı ===\n";
$t=microtime(true); $r=th_ai('Antworte nur mit dem Wort OK.',[['role'=>'user','content'=>'Hallo']],20,40);
echo "  sonuç: ".var_export($r,true)."  (".sprintf('%.2f',microtime(true)-$t)."s)\n  info: ".json_encode(th_ai_info(),JSON_UNESCAPED_UNICODE)."\n";

echo "\n=== 4) Canlı concierge POST (ham) ===\n";
foreach ([['genel',['messages'=>[['role'=>'user','content'=>'Was kostet der Telefon-Agent? Ein Satz.']],'lang'=>'de']],
          ['demo agent-inkasso',['agent'=>'agent-inkasso','messages'=>[['role'=>'user','content'=>'Was kannst du? Ein Satz.']],'lang'=>'de']],
          ['demo agent-phone',['agent'=>'agent-phone','channel'=>'phone','messages'=>[['role'=>'user','content'=>'Wann haben Sie geöffnet?']],'lang'=>'de']]] as [$label,$body]) {
    $ch=curl_init('https://chat-help.com/chat/concierge.php'); $t=microtime(true);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>90,CURLOPT_HEADER=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
    $res=(string)curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE); curl_close($ch);
    echo "  [$label] HTTP $code  ".sprintf('%.2f',microtime(true)-$t)."s\n  headers: ".preg_replace('/\s+/',' ',mb_substr(substr($res,0,$hs),0,400))."\n  body: ".mb_substr(trim(substr($res,$hs)),0,500)."\n\n";
}

echo "=== 5) Lokal include concierge (CLI, demo) — Fatal yakalama ===\n";
$_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
$tmp=tempnam(sys_get_temp_dir(),'cin'); file_put_contents($tmp,json_encode(['agent'=>'agent-inkasso','messages'=>[['role'=>'user','content'=>'Was kannst du? Ein Satz.']],'lang'=>'de']));
$cmd='php -d display_errors=1 -d error_reporting=E_ALL -r '.escapeshellarg('$_SERVER["REQUEST_METHOD"]="POST"; $_SERVER["REMOTE_ADDR"]="127.0.0.1"; $GLOBALS["__in"]=file_get_contents("'.$tmp.'"); stream_wrapper_unregister("php"); stream_wrapper_register("php", "CHIn"); class CHIn { public $context; private $p=0; function stream_open($u,$m,$o,&$op){ return str_ends_with($u,"input"); } function stream_read($n){ $d=substr($GLOBALS["__in"],$this->p,$n); $this->p+=strlen($d); return $d; } function stream_eof(){ return $this->p>=strlen($GLOBALS["__in"]); } function stream_stat(){ return []; } } include "'.__DIR__.'/concierge.php";').' 2>&1';
$out=(string)shell_exec($cmd); @unlink($tmp); echo "  ".mb_substr(trim($out),0,900)."\n";

if (!empty($_GET['mail_to'])) {
    echo "\n=== 5b) th_mail() Test → ".$_GET['mail_to']." ===\n";
    $ok = th_mail((string)$_GET['mail_to'], 'ChatHelp Studio-Code Test ✅ '.date('H:i'), '<p>th_mail() aus dem Web-Kontext-Test (CLI) · '.date('c').'</p><p style="font:700 28px monospace">123456</p>');
    echo "  mail(): ".($ok ? 'akzeptiert ✓' : 'FALSE ✗')."  sendmail_path=".ini_get('sendmail_path')."\n";
}

echo "\n=== 6) PHP error_log ===\n";
$log=ini_get('error_log'); echo "  error_log ini: ".($log ?: '(leer → Server-Log)')."\n";
foreach (array_filter([$log, __DIR__.'/error_log', dirname(__DIR__).'/error_log', '/var/log/php-fpm/error.log']) as $f) {
    if (is_file($f) && is_readable($f)) { echo "  --- $f (CH/Fatal, son 25) ---\n"; $lines=preg_grep('/CH |Fatal|concierge|tokens-lib/', array_slice(file($f), -400)); foreach (array_slice($lines, -25) as $l) echo "  ".mb_substr(trim($l),0,220)."\n"; }
}
echo "\nBİTTİ (yazılmadı)\n";
