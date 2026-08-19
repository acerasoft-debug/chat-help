<?php
/**
 * ChatHelp - dump-aitest (TESHIS) - AI hatti canli self-test + sohbet akis haritasi.
 *  [1] config anahtarlarinin varligi (degerler MASKELI)
 *  [2] Anthropic API'ye mini test cagrisi (ayni model, max_tokens=16) -> HTTP kodu + hata govdesi
 *  [3] DeepSeek API'ye mini test cagrisi -> HTTP kodu + hata
 *  [4] index.php: ana sohbet hangi endpoint'e gidiyor (fail mesajinin kullanildigi fetch)
 *  [5] fall-api.php: fall_chat handler (model/timeout/hata donusu)
 * KULLANIM: /chat/pull2.php?key=...&files=dump-aitest.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(120);
function mask($s){
  $s=preg_replace('/\bsk-ant-[A-Za-z0-9\-_]+/','sk-ant-***',$s);
  $s=preg_replace('/\bsk-[A-Za-z0-9]{16,}/','sk-***',$s);
  $s=preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]+/','$1_$2_***',$s);
  $s=preg_replace('/([\'"]?(?:key|secret|token|pass)[\'"]?\s*(?:=>|=|:)\s*)([\'"]).{8,}?\2/i','$1$2***$2',$s);
  return $s;
}
echo "== dump-aitest ==\n\n";

echo "=== [1] Anahtarlar (varlik) ===\n";
if(is_file(__DIR__.'/config.php')) include_once __DIR__.'/config.php';
$ck = defined('CLAUDE_KEY') ? constant('CLAUDE_KEY') : '';
$dk = defined('DEEPSEEK_KEY') ? constant('DEEPSEEK_KEY') : '';
echo "  CLAUDE_KEY:   ".($ck?('VAR ('.strlen($ck).' kr, '.substr($ck,0,7).'***)'):'YOK!')."\n";
echo "  DEEPSEEK_KEY: ".($dk?('VAR ('.strlen($dk).' kr)'):'YOK!')."\n";

echo "\n=== [2] Anthropic canli test (claude-sonnet-4-5, 16 token) ===\n";
if($ck){
  $t0=microtime(true);
  $ch=curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['x-api-key: '.$ck,'anthropic-version: 2023-06-01','content-type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-sonnet-4-5','max_tokens'=>16,
      'messages'=>[['role'=>'user','content'=>'Sag nur: OK']]]),
    CURLOPT_TIMEOUT=>30]);
  $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr=curl_error($ch); curl_close($ch);
  $dt=round((microtime(true)-$t0)*1000);
  echo "  HTTP: ".$code." (".$dt."ms)".($cerr?(" curl_err: ".$cerr):'')."\n";
  if($res!==false){
    $j=json_decode($res,true);
    if($code===200 && isset($j['content'][0]['text'])) echo "  ✓ CEVAP: ".trim($j['content'][0]['text'])."\n";
    else echo "  ✗ GOVDE: ".mask(substr($res,0,500))."\n";
  }
} else echo "  (anahtar yok — test atlandi)\n";

echo "\n=== [3] DeepSeek canli test (deepseek-chat, 16 token) ===\n";
if($dk){
  $t0=microtime(true);
  $ch=curl_init('https://api.deepseek.com/chat/completions');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$dk,'content-type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>'deepseek-chat','max_tokens'=>16,
      'messages'=>[['role'=>'user','content'=>'Sag nur: OK']]]),
    CURLOPT_TIMEOUT=>30]);
  $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr=curl_error($ch); curl_close($ch);
  $dt=round((microtime(true)-$t0)*1000);
  echo "  HTTP: ".$code." (".$dt."ms)".($cerr?(" curl_err: ".$cerr):'')."\n";
  if($res!==false){
    $j=json_decode($res,true);
    if($code===200 && isset($j['choices'][0]['message']['content'])) echo "  ✓ CEVAP: ".trim($j['choices'][0]['message']['content'])."\n";
    else echo "  ✗ GOVDE: ".mask(substr($res,0,500))."\n";
  }
} else echo "  (anahtar yok — test atlandi)\n";

echo "\n=== [4] index.php: ana sohbet gonderimi (fail kullanimi + fetch) ===\n";
$idx=(string)@file_get_contents(__DIR__.'/index.php');
foreach(array('fall_chat','fall-api.php','.fail','fail)') as $pat){
  $off=0;$n=0;$hit=false;
  while(($q=strpos($idx,$pat,$off))!==false && $n<3){
    echo "  [".$pat." @".$q."]: ...".mask(str_replace(array("\r","\n"),' ',substr($idx,($q-120>0?$q-120:0),300)))."...\n";
    $off=$q+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [5] fall-api.php: fall_chat handler ===\n";
$fa=(string)@file_get_contents(__DIR__.'/fall-api.php');
echo "  fall-api.php: ".strlen($fa)." B\n";
$q=strpos($fa,'fall_chat');
if($q!==false){ echo "  [fall_chat @".$q."]:\n  ".mask(str_replace("\n","\n  ",substr($fa,($q-40>0?$q-40:0),1400)))."\n"; }
else echo "  (fall_chat yok)\n";
foreach(array('claude-','deepseek-','CURLOPT_TIMEOUT','max_tokens') as $pat){
  $off=0;$n=0;
  while(($q=strpos($fa,$pat,$off))!==false && $n<3){
    echo "  [".$pat."]: ".mask(trim(str_replace(array("\r","\n"),' ',substr($fa,($q-30>0?$q-30:0),120))))."\n";
    $off=$q+strlen($pat);$n++;
  }
}
echo "\n== BITTI ==\n";
