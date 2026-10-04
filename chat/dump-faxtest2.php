<?php
/**
 * ChatHelp — dump-faxtest2 — Sinch faks UCTAN UCA test (postversand'i TETIKLEMEDEN):
 *  sadece config.php yuklenir (guvenli define'lar), sinch_token() burada yeniden
 *  tanimlanip GERCEK cagrilir. Faks GONDERMEZ, para harcamaz.
 *   [1] OAuth2 token aliniyor mu (auth.sinch.com).
 *   [2] fax.api.sinch.com/v3/projects/{pid}/faxes -> GET erisim (HTTP kodu).
 * KULLANIM: pull2.php?key=...&files=dump-faxtest2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== dump-faxtest2 (PHP ".PHP_VERSION.") ==\n\n";

@include_once __DIR__.'/config.php';   /* sadece define'lar — guvenli */

foreach(['FAX_PROVIDER','SINCH_KEY_ID','SINCH_KEY_SECRET','SINCH_PROJECT_ID'] as $k){
  if(defined($k)){ $v=(string)constant($k); $n=strlen($v);
    $m=$n<=6?str_repeat('*',$n):substr($v,0,3).str_repeat('*',max(0,$n-6)).substr($v,-3);
    echo "  ✓ $k ($n): $m\n";
  } else echo "  ✗ $k TANIMSIZ\n";
}
echo "\n";

/* postversand.php'deki sinch_token() ile AYNI mantik (kopya) */
function ft_token(){
  if(!defined('SINCH_KEY_ID')||!defined('SINCH_KEY_SECRET')) return ['','no_const'];
  $ch=curl_init('https://auth.sinch.com/oauth2/token');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_USERPWD=>constant('SINCH_KEY_ID').':'.constant('SINCH_KEY_SECRET'),
    CURLOPT_POSTFIELDS=>'grant_type=client_credentials',
    CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>30]);
  $r=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $j=json_decode((string)$r,true);
  $tok=is_array($j)?($j['access_token']??''):'';
  return [$tok,'http='.$code.($err?' curl='.$err:'').(!$tok&&is_array($j)?' resp='.substr(json_encode($j),0,200):'')];
}

echo "[1] Sinch OAuth2 token (auth.sinch.com)\n";
$t0=microtime(true); list($tok,$info)=ft_token(); $ms=round((microtime(true)-$t0)*1000);
if($tok){ echo "  ✓✓ TOKEN ALINDI (".strlen($tok)." hane, {$ms}ms) — KIMLIK DOGRULAMA CALISIYOR\n"; }
else{ echo "  ✗ TOKEN ALINAMADI ({$ms}ms) — $info\n"; }
echo "\n";

echo "[2] Fax API erisim (fax.api.sinch.com)\n";
if($tok && defined('SINCH_PROJECT_ID')){
  $pid=(string)constant('SINCH_PROJECT_ID');
  $ch=curl_init('https://fax.api.sinch.com/v3/projects/'.rawurlencode($pid).'/faxes?pageSize=1');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_TIMEOUT=>30]);
  $res=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  echo "  HTTP $code".($err?" (curl:$err)":'')."\n";
  if($code>=200&&$code<300) echo "  ✓✓ FAX API ERISILEBILIR — SISTEM GONDERMEYE HAZIR\n";
  elseif($code===401||$code===403) echo "  ✗ Yetki ($code) — Fax API projeye acik degil / token gecersiz\n";
  else echo "  ⚠ kod $code — yanit: ".substr((string)$res,0,300)."\n";
} else echo "  (token yok — atlandi)\n";

echo "\n== SONUC: ".($tok?"✓ FAKS CANLI — son adim gercek numaraya test faksi":"✗ FAKS HAZIR DEGIL")." ==\n";
