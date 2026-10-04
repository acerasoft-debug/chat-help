<?php
/* dump-fax-probe — aktif fax saglayicisi (FAX_PROVIDER) baglanti + test gonderim
   teshisi. Kimlikler config'e eklendikten sonra calistir: status + test gonderim
   ham yaniti gosterir. (postversand.php GUNCEL olmali.)
   ⚠ TEST numarasi gercek gonderim tetikleyebilir (kredi harcar) — InterFAX'te
   +1 numaralar test icindir; asagidaki fax_number'i ihtiyacina gore degistir.
   KULLANIM: pull2.php?key=...&files=apply-postversand.php,dump-fax-probe.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-fax-probe BASLADI (PHP ".PHP_VERSION.")\n\n";

$base='https://'.($_SERVER['HTTP_HOST']??'chat-help.com').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/chat/x'),'/').'/postversand.php';
function g($u){ $c=curl_init($u); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]); $r=curl_exec($c); curl_close($c); return $r; }
function p($u,$b){ $c=curl_init($u); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($b),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>90]); $r=curl_exec($c); curl_close($c); return $r; }

echo "== 1) status ==\n";
$st=g($base.'?action=status'); echo "  ".substr((string)$st,0,400)."\n";
$j=json_decode((string)$st,true);
$prov=$j['fax_provider']??'?'; $cfg=$j['fax_configured']??false;
echo "  fax_provider = $prov | fax_configured = ".($cfg?'EVET':'HAYIR')."\n\n";
if(!$cfg){
  echo "→ Fax kimlikleri yok. Saglayiciya gore config'e ekle:\n";
  echo "   ClickSend (varsayilan, kendi fax no'su GEREKMEZ):\n";
  echo "             FAX_PROVIDER='clicksend', CLICKSEND_USER='...', CLICKSEND_KEY='...'\n";
  echo "   InterFAX : FAX_PROVIDER='interfax', INTERFAX_USER='...', INTERFAX_PASS='...'\n";
  echo "   Phaxio   : FAX_PROVIDER='phaxio', PHAXIO_KEY='...', PHAXIO_SECRET='...'\n";
  echo "  Kimlikleri verirsen apply-fax-config.php yazip eklerim.\n";
  exit;
}

echo "== 2) TEST gonderim (ham yanit) ==\n";
$body=[
  'text'=>"Sehr geehrte Damen und Herren,\n\ndies ist ein Testfax von ChatHelp.\n\nMit freundlichen Gruessen\nTest",
  'recipient'=>"Testempfaenger\nTeststrasse 1\n10115 Berlin",
  'sender'=>"Test Nutzer - Alte Str. 5 - 10115 Berlin",
  'fax_number'=>'+13115552368',   /* InterFAX resmi TEST numarasi (gercek gonderim yok) */
];
$r=p($base.'?action=send_fax',$body);
echo "  ".substr((string)$r,0,700)."\n\n";
echo "→ 'result' altindaki ham yaniti/HTTP kodunu bana yapistir; InterFAX basarida\n";
echo "  201 + fax_id/location doner. Hata varsa ona gore duzeltirim.\n";
