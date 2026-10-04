<?php
/**
 * ChatHelp — dump-faxconn — TUM fax baglantilarini dogrular (OKUR, secret maskeli):
 *  [1] config: FAX_PROVIDER + Sinch anahtarlari
 *  [2] postversand: fax_provider/fax_configured/fax_send yonlendirme + sinch_token/fax_send_sinch
 *  [3] canli OAuth token + fax API erisim (postversand'i TETIKLEMEDEN, config include ile)
 *  [4] index.php: Senden->Fax akisi (doSend fax -> postversand cagrisi)
 * KULLANIM: pull2.php?key=...&files=dump-faxconn.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(0); ini_set('display_errors','0'); set_time_limit(60);
echo "== dump-faxconn ==\n\n";
$D=__DIR__;

echo "=== [1] config.php ===\n";
@include_once "$D/config.php";
foreach(['FAX_PROVIDER','SINCH_KEY_ID','SINCH_KEY_SECRET','SINCH_PROJECT_ID','CLICKSEND_USER','PHAXIO_KEY'] as $k){
  if(defined($k)){ $v=(string)constant($k); $n=strlen($v);
    $m=($k==='FAX_PROVIDER')?$v:($n<=6?str_repeat('*',$n):substr($v,0,3).str_repeat('*',max(0,$n-6)).substr($v,-3));
    echo "  ✓ $k = $m\n";
  } else echo "  · $k tanimsiz\n";
}

echo "\n=== [2] postversand.php fax fonksiyonlari ===\n";
$pv=(string)@file_get_contents("$D/postversand.php");
foreach(['function fax_provider','function fax_configured','function fax_send(','function sinch_token','function fax_send_sinch','function fax_send_clicksend','function fax_send_phaxio'] as $f){
  echo "  ".(strpos($pv,$f)!==false?'✓':'·')."  $f\n";
}
/* fax_send yonlendirme govdesi */
$p=strpos($pv,'function fax_send(');
if($p!==false){ echo "\n  fax_send() yonlendirme:\n  ".str_replace(["\n","\r"],["\n  ",''],substr($pv,$p,700))."\n"; }

echo "\n=== [3] CANLI Sinch testi (config include, postversand tetiklenmez) ===\n";
if(defined('SINCH_KEY_ID')&&defined('SINCH_KEY_SECRET')){
  mysqli_report(MYSQLI_REPORT_OFF);
  $ch=curl_init('https://auth.sinch.com/oauth2/token');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_USERPWD=>constant('SINCH_KEY_ID').':'.constant('SINCH_KEY_SECRET'),
    CURLOPT_POSTFIELDS=>'grant_type=client_credentials',
    CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>25]);
  $r=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $j=json_decode((string)$r,true); $tok=is_array($j)?($j['access_token']??''):'';
  echo "  OAuth: ".($tok?"✓ token alindi (http $code)":"✗ token YOK (http $code $err) ".substr((string)$r,0,120))."\n";
  if($tok && defined('SINCH_PROJECT_ID')){
    $pid=rawurlencode((string)constant('SINCH_PROJECT_ID'));
    $ch=curl_init("https://fax.api.sinch.com/v3/projects/$pid/faxes?pageSize=1");
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_TIMEOUT=>25]);
    $res=curl_exec($ch);$fc=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    echo "  Fax API: ".($fc>=200&&$fc<300?"✓ erisilebilir (http $fc)":"✗ http $fc ".substr((string)$res,0,120))."\n";
  }
} else echo "  (Sinch anahtarlari yok)\n";

echo "\n=== [4] index.php Senden->Fax akisi ===\n";
$idx=(string)@file_get_contents("$D/index.php");
foreach(['action=send','send_fax','fax_send','postversand','CH_FAXVERIFY','faxnr','chFaxQuota'] as $pat){
  echo "  '$pat': ".substr_count($idx,$pat)." kez\n";
}
echo "\n== SONUC: token+API ✓ ise fax ucu canli. ==\n";
echo "== BITTI ==\n";
