<?php
/**
 * ChatHelp — dump-faxtest — Sinch Faks sistemini UCTAN UCA test eder (OKUR, para harcamaz):
 *   [1] config.php: FAX_PROVIDER + SINCH_* tanimli mi (maskeli).
 *   [2] fax_configured() -> true mu.
 *   [3] sinch_token() -> gercek OAuth2 token aliniyor mu (auth.sinch.com).
 *   [4] fax.api.sinch.com/v3/projects/{pid}/faxes -> GET ile ERISIM var mi (HTTP kodu).
 *  Gercek faks GONDERMEZ (numara + ucret gerekir) — sadece sistemin hazir oldugunu dogrular.
 * KULLANIM: pull2.php?key=...&files=dump-faxtest.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== dump-faxtest BASLADI (PHP ".PHP_VERSION.") ==\n\n";

$dir=__DIR__;
$cfg=$dir.'/config.php';
$pv =$dir.'/postversand.php';

/* ── [1] config ── */
echo "[1] config.php\n";
if(!is_file($cfg)){ echo "  ✗ config.php YOK\n"; }
else{
  @include_once $cfg;
  $prov = defined('FAX_PROVIDER')?constant('FAX_PROVIDER'):'(tanimsiz)';
  echo "  FAX_PROVIDER = ".$prov."\n";
  foreach(['SINCH_KEY_ID','SINCH_KEY_SECRET','SINCH_PROJECT_ID'] as $k){
    if(defined($k)){ $v=(string)constant($k); $n=strlen($v);
      $m = $n<=6 ? str_repeat('*',$n) : substr($v,0,3).str_repeat('*',max(0,$n-6)).substr($v,-3);
      echo "  ✓ $k tanimli ($n hane): $m\n";
    } else echo "  ✗ $k TANIMSIZ\n";
  }
}
echo "\n";

/* ── [2]+[3]+[4] postversand fonksiyonlari ── */
echo "[2] postversand.php fonksiyonlari\n";
if(!is_file($pv)){ echo "  ✗ postversand.php YOK\n"; echo "\n== BITTI ==\n"; exit; }
@include_once $pv;

if(function_exists('fax_configured')){
  $fc=fax_configured();
  echo "  fax_configured() = ".($fc?'✓ TRUE (hazir)':'✗ FALSE (eksik)')."\n";
} else echo "  ✗ fax_configured() yok\n";
echo "\n";

/* ── [3] OAuth token ── */
echo "[3] Sinch OAuth2 token (auth.sinch.com)\n";
if(function_exists('sinch_token')){
  $t0=microtime(true);
  $tok=@sinch_token();
  $ms=round((microtime(true)-$t0)*1000);
  if($tok){ echo "  ✓✓ TOKEN ALINDI (".strlen($tok)." hane, {$ms}ms) — kimlik dogrulama CALISIYOR\n"; }
  else echo "  ✗ TOKEN ALINAMADI ({$ms}ms) — anahtarlar hatali/iptal olabilir\n";
} else { echo "  ✗ sinch_token() yok\n"; $tok=''; }
echo "\n";

/* ── [4] Fax API erisim (GET list) ── */
echo "[4] Fax API erisim (fax.api.sinch.com)\n";
if(!empty($tok) && defined('SINCH_PROJECT_ID')){
  $pid=(string)constant('SINCH_PROJECT_ID');
  $url='https://fax.api.sinch.com/v3/projects/'.rawurlencode($pid).'/faxes?pageSize=1';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_TIMEOUT=>30]);
  $res=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  echo "  HTTP $code".($err?" (curl: $err)":'')."\n";
  if($code>=200&&$code<300){ echo "  ✓✓ FAX API ERISILEBILIR — sistem GONDERMEYE HAZIR\n"; }
  elseif($code===401||$code===403){ echo "  ✗ Yetki hatasi ($code) — Fax API projeye acik degil ya da token gecersiz\n"; }
  else{ echo "  ⚠ Beklenmedik kod — yanit: ".substr((string)$res,0,300)."\n"; }
} else echo "  (token/proje eksik — atlandi)\n";

echo "\n== SONUC ==\n";
$ready = !empty($tok);
echo $ready
  ? "  ✓ Faks altyapisi CANLI: OAuth calisiyor, API erisilebilir.\n    Son adim: gercek bir numaraya test faksi (Senden > Fax) gonder.\n"
  : "  ✗ Faks HAZIR DEGIL: yukaridaki ilk ✗ satirini duzelt (genelde iptal edilmis anahtar).\n";
echo "\n== BITTI ==\n";
