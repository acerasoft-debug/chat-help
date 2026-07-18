<?php
/* dump-fax-status2 — status GET'ini CACHE-BUSTER ile cagirir (CDN bayat yanit
   sorununu atlar). Gonderim YOK. postversand.php zaten diskte guncel (17367 B).
   KULLANIM: pull2.php?key=...&files=dump-fax-status2.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-fax-status2 BASLADI (PHP ".PHP_VERSION.")\n\n";

$pv=@file_get_contents(__DIR__.'/postversand.php');
echo "postversand.php diskte: ".($pv?strlen($pv):0)." B | fax_send_clicksend: ".($pv&&strpos($pv,'fax_send_clicksend')!==false?'VAR':'YOK')."\n\n";

$base='https://'.($_SERVER['HTTP_HOST']??'chat-help.com').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/chat/x'),'/').'/postversand.php';
$cb=str_replace('.','',microtime(true)).rand(1000,9999);
$url=$base.'?action=status&_cb='.$cb;
$c=curl_init($url);
curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,
  CURLOPT_HTTPHEADER=>['Cache-Control: no-cache, no-store','Pragma: no-cache']]);
$r=curl_exec($c); curl_close($c);
echo "== status (cache-buster _cb=$cb) ==\n  ".substr((string)$r,0,500)."\n\n";
$j=json_decode((string)$r,true);
$fp=$j['fax_provider']??'(yok)'; $fc=$j['fax_configured']??null;
echo "  fax_provider   = $fp\n";
echo "  fax_configured = ".($fc===null?'(anahtar yok)':($fc?'EVET':'HAYIR'))."\n\n";
if($fp==='clicksend' && $fc===true){
  echo "✓ HAZIR: ClickSend adaptoru + kimlikler CANLI. Artik gercek fax numarasi ile test gonderebiliriz.\n";
}elseif($fc===null){
  echo "→ Hala eski yanit. Cache-buster'a ragmen eskiyse: origin opcache. Bir kez daha dene\n";
  echo "  (bu pull2 sonunda opcache-reset olur; sonraki cagri taze gelir).\n";
}else{
  echo "→ provider=$fp configured=".($fc?'EVET':'HAYIR')."\n";
}
