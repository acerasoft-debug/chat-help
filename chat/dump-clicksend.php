<?php
/* dump-clicksend (READ-ONLY) — ClickSend hesabinin CANLI durumu: auth + bakiye
   (GET /v3/account). Anahtar MASKELENIR. Fax gonderimi YAPILMAZ (ucretsiz sorgu).
   KULLANIM: pull2.php?key=...&files=dump-clicksend.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "dump-clicksend (READ-ONLY)\n\n";
ob_start(); @include __DIR__.'/config.php'; ob_end_clean();
$u = defined('CLICKSEND_USER')?CLICKSEND_USER:getenv('CLICKSEND_USER');
$k = defined('CLICKSEND_KEY')?CLICKSEND_KEY:getenv('CLICKSEND_KEY');
$prov = defined('FAX_PROVIDER')?FAX_PROVIDER:getenv('FAX_PROVIDER');
echo "FAX_PROVIDER : ".($prov?:'(tanimsiz)')."\n";
echo "CLICKSEND_USER: ".($u?:'(YOK)')."\n";
echo "CLICKSEND_KEY : ".($k? (substr($k,0,4)."…".substr($k,-4)." (".strlen($k)." hane)") : '(YOK)')."\n\n";
if(!$u||!$k){ echo "✗ Cred yok — ClickSend baglanamaz.\n"; exit; }
if(!function_exists('curl_init')){ echo "✗ curl yok\n"; exit; }
$ch=curl_init('https://rest.clicksend.com/v3/account');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1, CURLOPT_USERPWD=>$u.':'.$k,
  CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>25, CURLOPT_SSL_VERIFYPEER=>true]);
$r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
echo "=== GET /v3/account -> HTTP $code ===\n";
if($r===false){ echo "curl HATA: $err\n"; exit; }
$j=json_decode($r,true);
if(is_array($j)){
  $d=$j['data']??[];
  echo "  response_code : ".($j['response_code']??'?')."\n";
  echo "  bakiye        : ".($d['balance']??'?')." ".($d['currency']['currency_code']??'')."\n";
  echo "  kullanici     : ".($d['username']??'?')." / ".($d['email']??'?')."\n";
  echo (($code>=200&&$code<300)?"\n✓ ClickSend AUTH + hesap CANLI.\n":"\n✗ Auth/hesap sorunu (HTTP $code).\n");
} else { echo "  ham: ".substr($r,0,400)."\n"; }
echo "\nNOT: Bu sadece hesabin canli oldugunu gosterir. GERCEK fax teslimi hala\n";
echo "     tek bir gercek gonderimle kanitlanmadi (ClickSend'in test modu YOK).\n";
