<?php
/**
 * ChatHelp — dump-sinchfax3 (SALT-OKUR, FAKS GONDERILMEZ)
 *
 *  Saglayici ClickSend'den SINCH'e gecirilmis (FAX_PROVIDER=sinch, CH_SINCHFAX).
 *  Bu dump "faks gercekten gidiyor mu, rastgele numaraya ne oldu" sorusunu
 *  Sinch tarafindan kesin cevaplar:
 *
 *   [1] config: SINCH_KEY_ID / SECRET / PROJECT_ID var mi (maskeli)
 *   [2] canli postversand.php: fax_send_sinch gercekten ekli mi, yonlendirici
 *       'sinch'i doğru dala gonderiyor mu, fax_status hangi saglayiciya bakiyor
 *   [3] Sinch OAuth2 token alinabiliyor mu (UCRETSIZ, faks gondermez)
 *   [4] SINCH FAKS GECMISI — GET /v3/projects/{pid}/faxes  ← ASIL KANIT
 *       gonderdigin rastgele numara burada STATUS'uyle gorunur
 *   [5] arayuz: send_fax yanitindaki hata kullaniciya gosteriliyor mu
 *   [6] LetterXpress bakiye (Brief tarafi)
 *
 * HICBIR FAKS/MEKTUP GONDERILMEZ. HICBIR DOSYA DEGISTIRILMEZ.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-sinchfax3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "== dump-sinchfax3 (SALT-OKUR — faks GONDERILMEZ) ==\n\n";
ob_start(); @include_once __DIR__.'/config.php'; ob_end_clean();
function cst($n){ return defined($n)?constant($n):''; }
function mk($v){ $v=(string)$v; if($v==='') return '(YOK)'; return strlen($v)>10 ? substr($v,0,4).'…'.substr($v,-4).' ('.strlen($v).' hane)' : '***('.strlen($v).')'; }

/* ─── [1] config ─── */
echo "=== [1] Sinch yapilandirmasi ===\n";
echo "  FAX_PROVIDER      : ".(cst('FAX_PROVIDER')?:'(tanimsiz)')."\n";
echo "  SINCH_KEY_ID      : ".mk(cst('SINCH_KEY_ID'))."\n";
echo "  SINCH_KEY_SECRET  : ".mk(cst('SINCH_KEY_SECRET'))."\n";
echo "  SINCH_PROJECT_ID  : ".mk(cst('SINCH_PROJECT_ID'))."\n";
$allSinch = cst('SINCH_KEY_ID') && cst('SINCH_KEY_SECRET') && cst('SINCH_PROJECT_ID');
echo "  -> fax_configured() sinch dali: ".($allSinch?"TRUE (gecerli)":"FALSE ← 'fax_not_configured' doner!")."\n";
echo "\n  -- diger FAX/SINCH sabitleri --\n";
$uc = get_defined_constants(true)['user'] ?? [];
$n=0;
foreach($uc as $k=>$v){
  if(preg_match('/SINCH|FAX|CLICKSEND|PHAXIO|INTERFAX/i',$k)){
    echo "     ".str_pad($k,22)." = ".mk($v)."\n"; $n++;
  }
}
if(!$n) echo "     (yok)\n";

/* ─── [2] canli postversand.php ─── */
echo "\n=== [2] Canli postversand.php ===\n";
$pf=__DIR__.'/postversand.php'; $pv=(string)@file_get_contents($pf);
if($pv===''){ echo "  ✗ okunamadi\n"; }
else{
  echo "  dosya: ".strlen($pv)." B   ".date('Y-m-d H:i',filemtime($pf))."\n";
  echo "  CH_SINCHFAX isareti : ".(strpos($pv,'CH_SINCHFAX')!==false?'VAR ✓':'YOK ✗ (adaptor uygulanmamis!)')."\n";
  echo "  fax_send_sinch()    : ".(strpos($pv,'function fax_send_sinch')!==false?'VAR ✓':'YOK ✗')."\n";
  echo "  sinch_token()       : ".(strpos($pv,'function sinch_token')!==false?'VAR ✓':'YOK ✗')."\n";
  echo "\n  -- tanimli adaptorler --\n";
  if(preg_match_all('/function\s+(fax_send_\w+)/',$pv,$m)) foreach($m[1] as $fn) echo "     $fn\n";
  echo "\n  -- fax_send() yonlendirici --\n";
  $p=strpos($pv,'function fax_send($pdfBin');
  echo $p!==false ? "     ".preg_replace('/\s+/',' ',substr($pv,$p,430))."\n" : "     (bulunamadi)\n";
  echo "\n  -- fax_configured() --\n";
  $p=strpos($pv,'function fax_configured(');
  echo $p!==false ? "     ".preg_replace('/\s+/',' ',substr($pv,$p,760))."\n" : "     (bulunamadi)\n";
  echo "\n  -- fax_status ucu hangi saglayiciyi destekliyor --\n";
  $p=strpos($pv,"action==='fax_status'");
  echo $p!==false ? "     ".preg_replace('/\s+/',' ',substr($pv,max(0,$p-30),420))."\n" : "     (bulunamadi)\n";
  echo "\n  -- send_fax: numara kontrolu --\n";
  $p=strpos($pv,"action==='send_fax'");
  echo $p!==false ? substr($pv,max(0,$p-30),780)."\n" : "     (bulunamadi)\n";
}

/* ─── [3] Sinch OAuth ─── */
echo "\n=== [3] Sinch OAuth2 token (UCRETSIZ, faks gondermez) ===\n";
$tok='';
if(!$allSinch){ echo "  ✗ kimlik eksik — token denenmedi\n"; }
else{
  $ch=curl_init('https://auth.sinch.com/oauth2/token');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,
    CURLOPT_USERPWD=>cst('SINCH_KEY_ID').':'.cst('SINCH_KEY_SECRET'),
    CURLOPT_POSTFIELDS=>'grant_type=client_credentials',
    CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>30]);
  $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $e=curl_error($ch); curl_close($ch);
  $j=json_decode((string)$r,true); $tok=is_array($j)?(string)($j['access_token']??''):'';
  echo "  HTTP $c   token: ".($tok?('ALINDI ✓ ('.strlen($tok).' hane)'):'ALINAMADI ✗')."\n";
  if(!$tok) echo "  yanit: ".substr(preg_replace('/\s+/',' ',(string)$r),0,300)."\n";
  if($e) echo "  curl: $e\n";
}

/* ─── [4] Sinch faks gecmisi — ASIL KANIT ─── */
echo "\n=== [4] SINCH FAKS GECMISI (son 20) — ASIL KANIT ===\n";
echo "  (rastgele numaraya gonderdigin faks burada STATUS'uyle gorunur)\n";
if(!$tok){ echo "  (token yok — sorgulanamadi)\n"; }
else{
  $pid=cst('SINCH_PROJECT_ID');
  $url='https://fax.api.sinch.com/v3/projects/'.rawurlencode($pid).'/faxes?pageSize=20';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_TIMEOUT=>40]);
  $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $j=json_decode((string)$r,true);
  echo "  HTTP $c\n";
  $rows = is_array($j) ? ($j['faxes'] ?? $j['data'] ?? (isset($j[0])?$j:[])) : [];
  if(is_array($rows) && count($rows)){
    echo "  kayit: ".count($rows)."\n\n";
    printf("  %-22s %-18s %-14s %-6s %s\n",'TARIH','ALICI','DURUM','YON','HATA');
    foreach($rows as $row){
      if(!is_array($row)) continue;
      printf("  %-22s %-18s %-14s %-6s %s\n",
        substr((string)($row['createTime']??$row['create_time']??'?'),0,22),
        substr((string)($row['to']??'?'),0,18),
        substr((string)($row['status']??'?'),0,14),
        substr((string)($row['direction']??'-'),0,6),
        substr((string)($row['errorCode']??$row['error_code']??($row['errorType']??'')),0,40));
    }
    echo "\n  DURUM: COMPLETED = karsi faks ALDI · FAILURE = TESLIM EDILEMEDI\n";
    echo "         QUEUED / IN_PROGRESS = hala kuyrukta\n";
  }else{
    echo "  kayit: 0\n";
    echo "  ham yanit: ".substr(preg_replace('/\s+/',' ',(string)$r),0,500)."\n";
    echo "\n  -> HIC KAYIT YOKSA: hicbir faks Sinch'e HIC ULASMAMIS demektir.\n";
    echo "     Yani gonderim istegi arayuzden cikmiyor ya da sunucuda erken donuyor.\n";
  }
}

/* ─── [4b] Sinch: projede FAKS NUMARASI (From) var mi — 422'nin sebebi ─── */
echo "\n=== [4b] Sinch numaralari (422 'No From number' hatasinin sebebi) ===\n";
echo "  SINCH_FROM sabiti  : ".mk(cst('SINCH_FROM'))."\n";
if(!$tok){ echo "  (token yok — sorgulanamadi)\n"; }
else{
  $pid=cst('SINCH_PROJECT_ID');
  foreach([
    'aktif numaralar' => 'https://numbers.api.sinch.com/v1/projects/'.rawurlencode($pid).'/activeNumbers',
    'fax servisleri'  => 'https://fax.api.sinch.com/v3/projects/'.rawurlencode($pid).'/services',
  ] as $lbl=>$url){
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_TIMEOUT=>40]);
    $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    echo "\n  -- $lbl (HTTP $c) --\n";
    $j=json_decode((string)$r,true);
    $list = is_array($j) ? ($j['activeNumbers'] ?? $j['services'] ?? $j['numbers'] ?? []) : [];
    if(is_array($list) && count($list)){
      foreach($list as $it){
        if(!is_array($it)){ echo "     ".substr((string)$it,0,80)."\n"; continue; }
        echo "     ".str_pad((string)($it['phoneNumber']??$it['number']??($it['id']??'?')),20)
            ." tip=".(string)($it['type']??'-')
            ."  yetenek=".(is_array($it['capability']??null)?implode('/',$it['capability']):(string)($it['capability']??'-'))
            ."  bolge=".(string)($it['regionCode']??'-')."\n";
      }
    }else{
      echo "     kayit yok.  ham: ".substr(preg_replace('/\s+/',' ',(string)$r),0,300)."\n";
    }
  }
  echo "\n  YORUM: Burada FAX yetenekli bir numara YOKSA, Sinch her gonderimi\n";
  echo "         422 'No From number' ile reddeder — bugune kadar oldugu gibi.\n";
  echo "         Cozum: Sinch panelinden faks yetenekli bir numara kirala,\n";
  echo "         sonra kodda 'from' alani gonderilsin (yamasi hazir).\n";
}

/* ─── [5] arayuz hata isleme ─── */
echo "\n=== [5] Arayuz: send_fax yaniti / hata gosterimi ===\n";
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx===''){ echo "  index.php okunamadi\n"; }
else{
  $off=0;$n=0;
  while(($p=strpos($idx,'send_fax',$off))!==false && $n<3){
    echo "  [@$p] ...".preg_replace('/\s+/',' ',substr($idx,max(0,$p-300),820))."...\n\n";
    $off=$p+8;$n++;
  }
  if(!$n) echo "  (send_fax cagrisi bulunamadi)\n";
  echo "  -- hata anahtarlari arayuzde geciyor mu --\n";
  foreach(['fax_not_configured','no_fax_number','no_text','unknown_action'] as $k)
    printf("     %-20s x%d\n",$k,substr_count($idx,$k));
}

/* ─── [6] LetterXpress ─── */
echo "\n=== [6] LetterXpress bakiye (Brief) ===\n";
$lu=cst('LETTERXPRESS_USER'); $lk=cst('LETTERXPRESS_APIKEY');
if(!$lu||!$lk){ echo "  ✗ kimlik yok\n"; }
else{
  $auth=['username'=>$lu,'apikey'=>$lk,'mode'=>(stripos((string)cst('LETTERXPRESS_MODE'),'live')===0?'live':'test')];
  $ch=curl_init('https://api.letterxpress.de/v1/getBalance');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['auth'=>$auth]),CURLOPT_TIMEOUT=>30]);
  $r=curl_exec($ch); curl_close($ch);
  $j=json_decode((string)$r,true);
  $bal=is_array($j)?($j['balance']['value']??null):null;
  echo "  mod: ".$auth['mode']."   bakiye: ".($bal===null?'?':$bal)." EUR\n";
  if($bal!==null && (float)$bal<=0) echo "  ✗ BAKIYE EKSI/SIFIR -> yeni mektup gonderilemez, kredi yuklenmeli.\n";
  else echo "  ✓ bakiye pozitif\n";
}

echo "\n== BITTI — ciktinin TAMAMINI gonder ==\n";
