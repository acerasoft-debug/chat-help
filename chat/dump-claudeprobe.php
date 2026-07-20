<?php
/**
 * ChatHelp — dump-claudeprobe — foto/vision "bos yanit" kesin teshisi.
 *  config.php'deki CLAUDE_KEY ile Anthropic Messages API'sine KUCUK bir test
 *  atar; birkac MODEL adayini dener ve HTTP kodu + ham cevabin ilk parcasini
 *  basar. Boylece hangi model 200 donuyor (yani doPhoto'da hangisini
 *  kullanmaliyiz) NET gorunur. Anahtar cevaplarda yer almaz.
 *  SADECE OKUR/PROBE — index.php'ye dokunmaz.
 * KULLANIM: pull2.php?key=...&files=dump-claudeprobe.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== dump-claudeprobe ==\n\n";

@include_once __DIR__.'/config.php';
if(!defined('CLAUDE_KEY')){ echo "CLAUDE_KEY tanimli DEGIL — config.php yuklenemedi\n"; exit; }
$key=(string)CLAUDE_KEY;
echo "CLAUDE_KEY uzunluk: ".strlen($key)." (bas: ".substr($key,0,6)."… son: …".substr($key,-4).")\n\n";

$models = [
  'claude-sonnet-4-5',           // su an doPhoto'da kullanilan
  'claude-sonnet-4-5-20250929',  // tarihli surum
  'claude-sonnet-5',             // guncel Sonnet 5
  'claude-opus-4-8',             // guncel Opus 4.8
  'claude-haiku-4-5-20251001',   // guncel Haiku 4.5
];

foreach($models as $m){
  $payload = ['model'=>$m,'max_tokens'=>16,'messages'=>[['role'=>'user','content'=>'ping']]];
  $ch=curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>['x-api-key: '.$key,'anthropic-version: 2023-06-01','content-type: application/json'],
    CURLOPT_TIMEOUT=>30]);
  $res=curl_exec($ch);
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $short=substr((string)$res,0,260);
  echo "── model: $m\n";
  echo "   HTTP: $code".($err?" · curlErr: $err":"")."\n";
  echo "   yanit: ".preg_replace('/\s+/',' ',$short)."\n\n";
}
echo "== BITTI ==\n";
echo "NOT: 200 donen ve icinde 'text' olan model dogrudur; 404/not_found_error = model emekli.\n";
