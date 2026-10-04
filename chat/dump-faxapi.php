<?php
/**
 * ChatHelp — dump-faxapi — ClickSend API anahtari FAX icin gecerli mi? (OKUR):
 *  [1] Hesap dogrula (account) — auth calisiyor mu
 *  [2] Fax fiyati sorgusu (fax/price) — belirli numaraya faks GONDERILEBILIR mi
 *      + fiyat doner => API faks icin YETKILI demektir
 *  [3] Fax history erisimi — faks uc noktasi acik mi
 *  Numara opsiyonel: ...&files=dump-faxapi.php&to=+49...  (yoksa +4930... ornek)
 *  API anahtarlari ASLA basilmaz.
 * KULLANIM: pull2.php?key=...&files=dump-faxapi.php[&to=+49...]
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@include_once __DIR__.'/config.php';
echo "== dump-faxapi ==\n\n";

$hasKeys = defined('CLICKSEND_USER') && defined('CLICKSEND_KEY') && constant('CLICKSEND_USER') && constant('CLICKSEND_KEY');
echo "ClickSend anahtarlari: ".($hasKeys?"TANIMLI ✓":"EKSIK ✗")."\n";
if(!$hasKeys){ echo "Anahtar yok.\n== BITTI ==\n"; exit; }
$auth=constant('CLICKSEND_USER').':'.constant('CLICKSEND_KEY');

$to = trim((string)($_GET['to'] ?? '+493012345678'));
$e = preg_replace('/[^\d+]/','',$to);
if($e!=='' && $e[0]!=='+'){ if(strpos($e,'00')===0) $e='+'.substr($e,2); elseif($e[0]==='0') $e='+49'.substr($e,1); else $e='+'.$e; }

function cs($method,$url,$auth,$body=null){
  $ch=curl_init($url);
  $o=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_USERPWD=>$auth,CURLOPT_CUSTOMREQUEST=>$method];
  if($body!==null){ $o[CURLOPT_POSTFIELDS]=json_encode($body); $o[CURLOPT_HTTPHEADER]=['Content-Type: application/json']; }
  curl_setopt_array($ch,$o);
  $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return [$c,$r,$err];
}

echo "\n=== [1] Hesap (auth testi) ===\n";
list($c,$r)=cs('GET','https://rest.clicksend.com/v3/account',$auth);
$j=json_decode((string)$r,true);
$rc=is_array($j)?($j['response_code']??''):'';
echo "  HTTP $c · response_code: ".($rc?:'?')."\n";
if($rc==='SUCCESS'){
  $bal=$j['data']['balance']??'?'; $cur=$j['data']['currency']['currency_code']??'';
  echo "  ✓ AUTH GECERLI · bakiye: $bal $cur · hesap: ".($j['data']['username']??$j['data']['user_email']??'?')."\n";
} else {
  echo "  ✗ AUTH BASARISIZ — anahtar gecersiz/yanlis. Fax da calismaz.\n";
  echo "  yanit: ".substr((string)$r,0,200)."\n";
}

echo "\n=== [2] FAX fiyat sorgusu ($e) — faks yetkisi + maliyet ===\n";
list($c2,$r2)=cs('POST','https://rest.clicksend.com/v3/fax/price',$auth,['messages'=>[['source'=>'php','to'=>$e]]]);
$j2=json_decode((string)$r2,true);
$rc2=is_array($j2)?($j2['response_code']??''):'';
echo "  HTTP $c2 · response_code: ".($rc2?:'?')."\n";
if($rc2==='SUCCESS' && is_array($j2)){
  $d=$j2['data']??[];
  $total=$d['total_price']??($d['total']??'?');
  $cur=$d['currency']??'';
  echo "  ✓✓ API FAKS ICIN GECERLI — bu numaraya faks fiyati: $total $cur\n";
  if(isset($d['messages'][0])){
    $m=$d['messages'][0];
    echo "     numara: ".($m['to']??'?')." · fiyat: ".($m['price']??$m['message_price']??'?')." · ulke: ".($m['country']??'?')."\n";
  }
} else {
  echo "  ✗ Fax fiyati alinamadi — API faks icin yetkisiz OLABILIR ya da numara gecersiz.\n";
  echo "  yanit: ".substr((string)$r2,0,300)."\n";
}

echo "\n=== [3] Fax history erisimi (uc nokta acik mi) ===\n";
list($c3,$r3)=cs('GET','https://rest.clicksend.com/v3/fax/history?limit=1',$auth);
$j3=json_decode((string)$r3,true);
$rc3=is_array($j3)?($j3['response_code']??''):'';
echo "  HTTP $c3 · response_code: ".($rc3?:'?')." ".($rc3==='SUCCESS'?"✓ fax uc noktasi ERISILEBILIR":"✗")."\n";

echo "\n=== SONUC ===\n";
if(($rc==='SUCCESS') && ($rc2==='SUCCESS')){
  echo "  ✅ API anahtari FAKS icin TAM GECERLI — gonderim yapilabilir.\n";
  echo "  ClickSend'de kayit gorunmemesinin sebebi: henuz BASARIYLA gonderilmis faks YOK.\n";
  echo "  apply-faxtest.php&to=+49... ile bir ornek olustur.\n";
} else {
  echo "  ⚠️ Yukaridaki ✗ satirlarina bak — sorun auth mu yoksa faks-yetkisi mi netlesir.\n";
}
echo "\n== BITTI ==\n";
