<?php
/**
 * ChatHelp — dump-faxlive (SALT-OKUR, GONDERIM YOK) — "Fax ve Brief gercekten
 *  bagli mi? Rastgele numaraya neden hata vermedi?" sorusunun KESIN cevabi.
 *
 *  Bu dosya HICBIR SEY GONDERMEZ, hicbir dosyayi degistirmez. Sadece sorgular:
 *   [1] config: hangi saglayici, kimlikler var mi, LETTERXPRESS_MODE = test/live
 *   [2] ClickSend hesabi CANLI mi (GET /v3/account) + bakiye
 *   [3] ClickSend FAX GECMISI (GET /v3/fax/history) — asil kanit:
 *       gonderdigin rastgele numara listede ne durumda (Sent / Failed / …)
 *   [4] LetterXpress bakiye + mod (test modunda mektup GERCEKTEN BASILMAZ)
 *   [5] index.php: fax numarasi dogrulaniyor mu, gonderim sonrasi durum
 *       (fax_status) sorgulaniyor mu — yani kullaniciya sonuc gosteriliyor mu
 *
 * KULLANIM: /chat/pull2.php?key=...&files=dump-faxlive.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "== dump-faxlive (SALT-OKUR — hicbir fax/mektup gonderilmez) ==\n\n";
ob_start(); @include_once __DIR__.'/config.php'; ob_end_clean();

function mask($v){ $v=(string)$v; if($v==='') return '(YOK)'; return strlen($v)>8 ? substr($v,0,4).'…'.substr($v,-4).' ('.strlen($v).' hane)' : '***('.strlen($v).')'; }
function cst($n){ return defined($n) ? constant($n) : ''; }

/* ───────── [1] config ───────── */
echo "=== [1] Yapilandirma ===\n";
$prov = defined('FAX_PROVIDER') ? strtolower((string)cst('FAX_PROVIDER')) : 'clicksend (varsayilan)';
echo "  FAX_PROVIDER        : $prov\n";
echo "  CLICKSEND_USER      : ".(cst('CLICKSEND_USER')?:'(YOK)')."\n";
echo "  CLICKSEND_KEY       : ".mask(cst('CLICKSEND_KEY'))."\n";
echo "  PHAXIO_KEY          : ".mask(cst('PHAXIO_KEY'))."\n";
echo "  LETTERXPRESS_USER   : ".(cst('LETTERXPRESS_USER')?:'(YOK)')."\n";
echo "  LETTERXPRESS_APIKEY : ".mask(cst('LETTERXPRESS_APIKEY'))."\n";
$lxmode = defined('LETTERXPRESS_MODE') ? (string)cst('LETTERXPRESS_MODE') : 'test (tanimsiz -> test)';
echo "  LETTERXPRESS_MODE   : $lxmode";
echo (stripos($lxmode,'live')===0 ? "   ← CANLI: mektup GERCEKTEN basilir/postalanir\n"
                                  : "   ← TEST: is kabul edilir ama MEKTUP POSTALANMAZ!\n");

$csU=cst('CLICKSEND_USER'); $csK=cst('CLICKSEND_KEY');

/* ───────── [2] ClickSend hesap ───────── */
echo "\n=== [2] ClickSend hesabi (GET /v3/account) ===\n";
if(!$csU||!$csK){ echo "  ✗ Kimlik yok — fax hic gitmiyor olabilir.\n"; }
else{
  $ch=curl_init('https://rest.clicksend.com/v3/account');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_USERPWD=>$csU.':'.$csK,CURLOPT_TIMEOUT=>25]);
  $r=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $j=json_decode((string)$r,true); $d=is_array($j)?($j['data']??[]):[];
  echo "  HTTP $code   response_code=".(is_array($j)?($j['response_code']??'?'):'-')."\n";
  echo "  bakiye  : ".($d['balance']??'?')." ".($d['currency']['currency_code']??'')."\n";
  echo "  hesap   : ".($d['username']??'?')." / ".($d['user_email']??($d['email']??'?'))."\n";
  if($err) echo "  curl    : $err\n";
  echo ($code>=200&&$code<300) ? "  ✓ AUTH CANLI\n" : "  ✗ AUTH SORUNU\n";
}

/* ───────── [3] ClickSend fax gecmisi — ASIL KANIT ───────── */
echo "\n=== [3] ClickSend FAX GECMISI (son 20) — asil kanit ===\n";
echo "  (Rastgele numaraya gonderdigin fax burada gorunur; STATUS sutununa bak)\n";
if(!$csU||!$csK){ echo "  (kimlik yok)\n"; }
else{
  $ch=curl_init('https://rest.clicksend.com/v3/fax/history?limit=20');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_USERPWD=>$csU.':'.$csK,CURLOPT_TIMEOUT=>30]);
  $r=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $j=json_decode((string)$r,true);
  $rows=[]; if(is_array($j)){ $rows=$j['data']['data'] ?? (is_array($j['data']??null)?$j['data']:[]); }
  echo "  HTTP $code   kayit: ".(is_array($rows)?count($rows):0)."\n\n";
  if(is_array($rows)&&count($rows)){
    printf("  %-19s %-17s %-14s %-6s %-8s %s\n",'TARIH','ALICI','DURUM','SAYFA','UCRET','MESSAGE_ID');
    foreach($rows as $row){
      if(!is_array($row)) continue;
      $ts=$row['date_added']??$row['date']??0;
      $dt=is_numeric($ts)?date('Y-m-d H:i',(int)$ts):(string)$ts;
      printf("  %-19s %-17s %-14s %-6s %-8s %s\n",
        substr($dt,0,19),
        substr((string)($row['to']??'?'),0,17),
        substr((string)($row['status']??'?'),0,14),
        (string)($row['pages']??'-'),
        (string)($row['cost']??$row['price']??'-'),
        (string)($row['message_id']??$row['messageid']??'-'));
    }
    echo "\n  DURUM anlamlari: Sent/Delivered = karsi faks aldi · Failed / Receive_Failure /\n";
    echo "                   No_Answer / Busy = TESLIM EDILEMEDI (numara faks degilse boyle olur)\n";
    echo "                   Queued/Sending   = hala kuyrukta\n";
  }else{
    echo "  (hic fax kaydi yok — ya hic gonderilmedi ya da baska hesaba gitti)\n";
    echo "  ham: ".substr((string)$r,0,300)."\n";
  }
}

/* ───────── [4] LetterXpress ───────── */
echo "\n=== [4] LetterXpress (Brief / Einschreiben) ===\n";
$lu=cst('LETTERXPRESS_USER'); $lk=cst('LETTERXPRESS_APIKEY');
if(!$lu||!$lk){ echo "  ✗ Kimlik yok -> postversand 'not_configured' doner, mektup HIC gitmez.\n"; }
else{
  $auth=['username'=>$lu,'apikey'=>$lk,'mode'=>(stripos((string)cst('LETTERXPRESS_MODE'),'live')===0?'live':'test')];
  foreach(['getBalance','getJobs'] as $ep){
    $ch=curl_init('https://api.letterxpress.de/v1/'.$ep);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,
      CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
      CURLOPT_POSTFIELDS=>json_encode(['auth'=>$auth]),CURLOPT_TIMEOUT=>30]);
    $r=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    echo "  $ep -> HTTP $code : ".substr(preg_replace('/\s+/',' ',(string)$r),0,600)."\n";
  }
  echo "  NOT: mode='test' ise LetterXpress isi kabul eder ama MEKTUBU BASMAZ/POSTALAMAZ.\n";
}

/* ───────── [5] Arayuz: dogrulama + sonuc gosterimi ───────── */
echo "\n=== [5] Arayuz (index.php): numara dogrulama + sonuc takibi ===\n";
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx===''){ echo "  index.php okunamadi\n"; }
else{
  foreach(['send_fax','fax_status','fax_ping','send_letter','letter_status',
           'fax_number','faxNr','chFaxQuota','Sendebericht','Zustellung'] as $k){
    printf("  %-16s x%d\n",$k,substr_count($idx,$k));
  }
  echo "\n  -- fax numarasi dogrulama izi --\n";
  $found=false;
  foreach(['/^\\+?[0-9','isValidFax','faxValid','fax.*test(','\\d{6,}'] as $pat){
    $p=strpos($idx,$pat);
    if($p!==false){ echo "    [$pat @$p] ...".preg_replace('/\s+/',' ',substr($idx,max(0,$p-120),260))."...\n"; $found=true; }
  }
  if(!$found) echo "    (belirgin bir fax-numarasi dogrulamasi BULUNAMADI)\n";

  echo "\n  -- gonderim sonrasi fax_status sorgulaniyor mu --\n";
  $off=0;$n=0;
  while(($p=strpos($idx,'fax_status',$off))!==false && $n<3){
    echo "    @$p ...".preg_replace('/\s+/',' ',substr($idx,max(0,$p-200),420))."...\n";
    $off=$p+10;$n++;
  }
  if(!$n) echo "    ✗ HIC sorgulanmiyor -> kullanici teslim edilmedigini ASLA ogrenemez\n";
}

echo "\n=== OZET ===\n";
echo "  · ClickSend 'SUCCESS' = KUYRUGA ALINDI demektir, TESLIM EDILDI demek DEGILDIR.\n";
echo "  · Bu yuzden rastgele numara hata vermez: hata sonra, teslim asamasinda olusur.\n";
echo "  · Gercek sonuc yalniz [3]'teki gecmis listesindeki STATUS sutununda gorunur.\n";
echo "\n== BITTI — ciktinin TAMAMINI gonder ==\n";
