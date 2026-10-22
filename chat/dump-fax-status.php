<?php
/* dump-fax-status — SADECE durum kontrolu (gonderim YOK, kredi harcamaz).
   postversand.php'nin ClickSend'li surumu + config kimlikleri yuklu mu?
   KULLANIM: pull2.php?key=...&files=apply-postversand2.php,dump-fax-status.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-fax-status BASLADI (PHP ".PHP_VERSION.")\n\n";

$pv=@file_get_contents(__DIR__.'/postversand.php');
echo "postversand.php boyut       : ".($pv?strlen($pv):0)." B\n";
echo "  fax_send_clicksend        : ".($pv&&strpos($pv,'fax_send_clicksend')!==false?'VAR':'YOK')."\n";
echo "  status fax_provider anahtar: ".($pv&&strpos($pv,"'fax_provider'=>")!==false?'VAR':'YOK')."\n\n";

$base='https://'.($_SERVER['HTTP_HOST']??'chat-help.com').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/chat/x'),'/').'/postversand.php';
$c=curl_init($base.'?action=status'); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]); $r=curl_exec($c); curl_close($c);
echo "== status yaniti ==\n  ".substr((string)$r,0,500)."\n\n";
$j=json_decode((string)$r,true);
$fp=$j['fax_provider']??'(yok)'; $fc=$j['fax_configured']??null;
echo "  fax_provider   = $fp\n";
echo "  fax_configured = ".($fc===null?'(anahtar yok -> ESKI postversand!)':($fc?'EVET':'HAYIR'))."\n\n";
if(($fp==='clicksend') && $fc===true){
  echo "✓ HAZIR: ClickSend adaptoru + kimlikler yuklu. Gercek bir fax numarasi verince test gondeririz.\n";
}elseif($fc===null){
  echo "→ postversand hala eski (opcache). apply-postversand2 opcache reset etti; sayfayi tekrar dene.\n";
}else{
  echo "→ Saglayici=$fp, configured=".($fc?'EVET':'HAYIR').". config kimliklerini kontrol edelim.\n";
}
