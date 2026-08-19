<?php
/**
 * ChatHelp — dump-faxcheck — Fax (ClickSend) calisiyor mu? (OKUR, faks GONDERMEZ):
 *  [1] Hesap bagli mi + bakiye (auth dogrular)
 *  [2] Son fax gonderimleri + TESLIM DURUMU (delivered/failed/queued)
 *  API anahtarlari ASLA basilmaz.
 * KULLANIM: pull2.php?key=...&files=dump-faxcheck.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@include_once __DIR__.'/config.php';
echo "== dump-faxcheck ==\n\n";

$prov = defined('FAX_PROVIDER') ? strtolower((string)constant('FAX_PROVIDER')) : 'clicksend';
echo "Fax saglayici: $prov\n";
if($prov!=='clicksend'){ echo "(bu arac ClickSend icin) — yine de status alttan denenir\n"; }
$hasKeys = defined('CLICKSEND_USER') && defined('CLICKSEND_KEY') && constant('CLICKSEND_USER') && constant('CLICKSEND_KEY');
echo "ClickSend anahtarlari: ".($hasKeys?"TANIMLI ✓":"EKSIK ✗")."\n\n";
if(!$hasKeys){ echo "Anahtar yok — fax gonderilemez.\n== BITTI ==\n"; exit; }
$auth=constant('CLICKSEND_USER').':'.constant('CLICKSEND_KEY');

function cs_get($url,$auth){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_USERPWD=>$auth]);
  $r=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return [$code,$r,$err];
}

echo "=== [1] Hesap + bakiye ===\n";
list($c,$r,$e)=cs_get('https://rest.clicksend.com/v3/account',$auth);
$j=json_decode((string)$r,true);
$okAcc=($c>=200&&$c<300 && is_array($j) && (($j['response_code']??'')==='SUCCESS'));
echo "  HTTP $c ".($e?"(hata: $e)":"")."\n";
if($okAcc && isset($j['data'])){
  $bal=$j['data']['balance']??'?'; $cur=$j['data']['currency']['currency_code']??($j['data']['currency']??'');
  echo "  ✓ Hesap BAGLI · bakiye: $bal $cur · kullanici: ".($j['data']['username']??$j['data']['user_email']??'?')."\n";
  if(is_numeric($bal) && (float)$bal < 0.20) echo "  ⚠️ BAKIYE DUSUK — birkac fakstan sonra biter, yukleme onerilir.\n";
} else {
  echo "  ✗ Hesap dogrulanamadi — anahtarlar yanlis veya hesap sorunlu olabilir.\n";
  echo "  yanit: ".substr((string)$r,0,200)."\n";
}

echo "\n=== [2] Son fax gonderimleri + teslim durumu ===\n";
list($c2,$r2,$e2)=cs_get('https://rest.clicksend.com/v3/fax/history?limit=15',$auth);
$j2=json_decode((string)$r2,true);
echo "  HTTP $c2 ".($e2?"(hata: $e2)":"")."\n";
$rows = is_array($j2) ? ($j2['data']['data'] ?? ($j2['data'] ?? [])) : [];
if(!is_array($rows) || !count($rows)){
  echo "  Henuz hic fax gonderilmemis (veya kayit yok).\n";
  echo "  -> Gercek test icin: uygulamada bir belge uret -> yesil Senden -> Fax sec ->\n";
  echo "     kendi faks numarani (veya bir test faks numarasi) gir -> gonder.\n";
} else {
  echo "  Son ".count($rows)." gonderim:\n";
  foreach($rows as $row){
    if(!is_array($row)) continue;
    $to=$row['to']??'?'; $st=$row['status']??'?'; $pg=$row['pages']??'?';
    $cost=$row['cost']??$row['price']??'?'; $dt=$row['date_added']??$row['date']??'';
    $when = (is_numeric($dt)) ? date('d.m H:i',(int)$dt) : (string)$dt;
    echo "   • $to · durum: $st · sayfa:$pg · ucret:$cost · $when\n";
  }
  echo "\n  DURUM ANLAMLARI: 'Sent/Delivered' = ULASTI ✓ · 'Queued/Sending' = yolda ·\n";
  echo "  'Failed/Cancelled' = BASARISIZ (numara/hat sorunu).\n";
}

echo "\n== BITTI ==\n";
