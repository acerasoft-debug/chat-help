<?php
/* dump-faxde-probe — fax-api.de (Faxsuite) baglanti/alan teshisi.
   FAXDE_TOKEN config'e eklendikten sonra calistir: durum + test gonderim ham
   yaniti gosterir; boylece gercek endpoint/alan adlari kesinlestirilir.
   (postversand.php GUNCEL olmali — send_fax dispatcher + faxde adaptoru.)
   KULLANIM: pull2.php?key=...&files=apply-postversand.php,dump-faxde-probe.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-faxde-probe BASLADI (PHP ".PHP_VERSION.")\n\n";

$base='https://'.($_SERVER['HTTP_HOST']??'chat-help.com').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/chat/x'),'/').'/postversand.php';
function get($url){ $c=curl_init($url); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]); $r=curl_exec($c); curl_close($c); return $r; }
function post($url,$b){ $c=curl_init($url); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($b),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>90]); $r=curl_exec($c); curl_close($c); return $r; }

echo "== 1) postversand status ==\n";
$st=get($base.'?action=status');
echo "  ".substr((string)$st,0,400)."\n";
$j=json_decode((string)$st,true);
$prov=$j['fax_provider']??'?'; $cfg=$j['fax_configured']??false;
echo "  fax_provider = $prov | fax_configured = ".($cfg?'EVET':'HAYIR')."\n\n";

if(!$cfg){
  echo "→ FAXDE_TOKEN yok. Once config'e ekle (FAX_PROVIDER='faxde', FAXDE_TOKEN='...').\n";
  echo "  Token'i verirsen apply-faxde-config.php yazip eklerim.\n";
  exit;
}

echo "== 2) TEST gonderim (ham yanit — endpoint/alan dogrulama) ==\n";
$body=[
  'text'=>"Sehr geehrte Damen und Herren,\n\ndies ist ein Testfax von ChatHelp.\n\nMit freundlichen Gruessen\nTest",
  'recipient'=>"Testempfaenger\nTeststrasse 1\n10115 Berlin",
  'sender'=>"Test Nutzer - Alte Str. 5 - 10115 Berlin",
  'fax_number'=>'004930000000',   /* test numarasi — gercek gonderim istemiyorsan degistir */
];
$r=post($base.'?action=send_fax',$body);
echo "  ".substr((string)$r,0,700)."\n\n";
echo "→ 'result' altindaki ham yaniti bana yapistir; endpoint/alan adlari yanlissa\n";
echo "  FAXDE_SEND_PATH / FAXDE_FIELD_* config sabitleriyle duzeltip kesinlestiririm.\n";
