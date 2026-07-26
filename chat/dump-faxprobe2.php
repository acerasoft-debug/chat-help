<?php
/**
 * ChatHelp — dump-faxprobe2 (SALT-OKUR, FAKS GONDERILMEZ) — iki soruyu kesin cevaplar:
 *
 *  A) FAX_PROVIDER='sinch' ama koda bakinca sinch adaptoru yok gibi.
 *     CANLI postversand.php'de gercekten hangi adaptorler var, 'sinch' hangisine
 *     dusuyor? (dosya OKUNUR, calistirilmaz)
 *
 *  B) ClickSend fax/history 403 verdi. Gonderim hatti da yetkisiz mi?
 *     Gonderimin ILK adimi = dosya yukleme (POST /v3/uploads?convert=fax).
 *     Bu adim UCRETSIZDIR ve FAKS GONDERMEZ. 403 gelirse fax yetkisi yok demektir;
 *     200 gelirse yetki var, sorun baska yerde.
 *     Karsilastirma icin sms/history de sorulur (kisitlama fax'a mi ozel).
 *
 * HICBIR FAKS/MEKTUP GONDERILMEZ. HICBIR DOSYA DEGISTIRILMEZ.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-faxprobe2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "== dump-faxprobe2 (SALT-OKUR — faks GONDERILMEZ) ==\n\n";
ob_start(); @include_once __DIR__.'/config.php'; ob_end_clean();
function cst($n){ return defined($n)?constant($n):''; }

/* ─────────── [A] CANLI postversand.php ─────────── */
echo "=== [A] Canli postversand.php — hangi adaptor calisiyor ===\n";
$pf=__DIR__.'/postversand.php';
$pv=(string)@file_get_contents($pf);
if($pv===''){ echo "  ✗ postversand.php okunamadi\n"; }
else{
  echo "  dosya: ".strlen($pv)." B   ".date('Y-m-d H:i',filemtime($pf))."\n";
  echo "  -- tanimli fax adaptorleri --\n";
  if(preg_match_all('/function\s+(fax_send_\w+)/',$pv,$m)){
    foreach($m[1] as $fn) echo "     $fn\n";
  } else echo "     (hic bulunamadi)\n";
  echo "  -- 'sinch' gecisi: x".substr_count(strtolower($pv),'sinch')."\n";
  $off=0;$n=0;
  while(($p=stripos($pv,'sinch',$off))!==false && $n<5){
    echo "     @$p ...".preg_replace('/\s+/',' ',substr($pv,max(0,$p-140),300))."...\n";
    $off=$p+5;$n++;
  }
  echo "\n  -- fax_send() yonlendirici (tam govde) --\n";
  $p=strpos($pv,'function fax_send(');
  echo $p!==false ? "     ".preg_replace('/\s+/',' ',substr($pv,$p,420))."\n" : "     (bulunamadi)\n";
  echo "\n  -- fax_configured() (tam govde) --\n";
  $p=strpos($pv,'function fax_configured(');
  echo $p!==false ? "     ".preg_replace('/\s+/',' ',substr($pv,$p,700))."\n" : "     (bulunamadi)\n";
  echo "\n  -- fax numarasi kontrolu (send_fax bloğu) --\n";
  $p=strpos($pv,"action==='send_fax'");
  echo $p!==false ? substr($pv,max(0,$p-40),900)."\n" : "     (bulunamadi)\n";
}

/* config'de sinch anahtari var mi */
echo "\n  -- config: SINCH_* sabitleri --\n";
$found=false;
foreach(get_defined_constants(true)['user']??[] as $k=>$v){
  if(stripos($k,'SINCH')!==false || stripos($k,'FAX')!==false){
    $sv=(string)$v; $sv=(strlen($sv)>10)?substr($sv,0,4).'…'.substr($sv,-4).' ('.strlen($sv).')':$sv;
    echo "     $k = $sv\n"; $found=true;
  }
}
if(!$found) echo "     (SINCH_/FAX_ sabiti yok)\n";

/* ─────────── minik gecerli PDF (yalniz yukleme probu icin) ─────────── */
function probe_pdf(): string {
  $stream="BT /F1 11 Tf 56 786 Td (ChatHelp Verbindungstest - kein Versand) Tj ET";
  $objs=[];
  $objs[1]="<</Type/Catalog /Pages 2 0 R>>";
  $objs[2]="<</Type/Pages /Kids[4 0 R] /Count 1>>";
  $objs[3]="<</Type/Font /Subtype/Type1 /BaseFont/Helvetica /Encoding/WinAnsiEncoding>>";
  $objs[4]="<</Type/Page /Parent 2 0 R /MediaBox[0 0 595 842] /Resources<</Font<</F1 3 0 R>>>> /Contents 5 0 R>>";
  $objs[5]="<</Length ".strlen($stream).">>\nstream\n$stream\nendstream";
  ksort($objs);
  $pdf="%PDF-1.4\n"; $offs=[];
  foreach($objs as $num=>$body){ $offs[$num]=strlen($pdf); $pdf.="$num 0 obj\n$body\nendobj\n"; }
  $xref=strlen($pdf); $max=max(array_keys($objs));
  $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
  for($i=1;$i<=$max;$i++){ $pdf.=str_pad((string)($offs[$i]??0),10,'0',STR_PAD_LEFT)." 00000 n \n"; }
  $pdf.="trailer\n<</Size ".($max+1)." /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
  return $pdf;
}

/* ─────────── [B] ClickSend yetki probu ─────────── */
echo "\n\n=== [B] ClickSend yetki probu (FAKS GONDERILMEZ) ===\n";
$u=cst('CLICKSEND_USER'); $k=cst('CLICKSEND_KEY');
if(!$u||!$k){ echo "  ✗ kimlik yok\n"; }
else{
  function cs_get($u,$k,$url){
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_USERPWD=>$u.':'.$k,CURLOPT_TIMEOUT=>30]);
    $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return [$c,(string)$r];
  }
  foreach([
    'GET /v3/account'          => 'https://rest.clicksend.com/v3/account',
    'GET /v3/fax/history'      => 'https://rest.clicksend.com/v3/fax/history?limit=5',
    'GET /v3/fax/receipts'     => 'https://rest.clicksend.com/v3/fax/receipts?limit=5',
    'GET /v3/sms/history'      => 'https://rest.clicksend.com/v3/sms/history?limit=1',
    'GET /v3/uploads'          => 'https://rest.clicksend.com/v3/uploads',
  ] as $lbl=>$url){
    list($c,$r)=cs_get($u,$k,$url);
    $j=json_decode($r,true);
    printf("  %-22s HTTP %-4d %s\n",$lbl,$c,
      is_array($j)?(($j['response_code']??'?').' — '.substr((string)($j['response_msg']??''),0,70))
                  :substr(preg_replace('/\s+/',' ',$r),0,90));
  }

  echo "\n  -- ASIL PROB: POST /v3/uploads?convert=fax (UCRETSIZ, FAKS GITMEZ) --\n";
  $pdf=probe_pdf();
  echo "     test PDF: ".strlen($pdf)." bayt\n";
  $ch=curl_init('https://rest.clicksend.com/v3/uploads?convert=fax');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,CURLOPT_USERPWD=>$u.':'.$k,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['content'=>base64_encode($pdf)]),CURLOPT_TIMEOUT=>60]);
  $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $e=curl_error($ch); curl_close($ch);
  $j=json_decode((string)$r,true);
  echo "     HTTP $c   response_code=".(is_array($j)?($j['response_code']??'?'):'-')."\n";
  echo "     yanit: ".substr(preg_replace('/\s+/',' ',(string)$r),0,400)."\n";
  if($e) echo "     curl: $e\n";
  $fileUrl=''; if(is_array($j)){ $d=$j['data']??null; if(is_array($d)) $fileUrl=$d['_url']??($d['url']??''); elseif(is_string($d)) $fileUrl=$d; }
  echo "\n     SONUC: ";
  if($c>=200&&$c<300&&$fileUrl!==''){
    echo "✓ Yukleme CALISIYOR -> fax yetkisi VAR.\n";
    echo "             Demek ki gonderim de teknik olarak mumkun; sorun teslimde/arayuzde.\n";
  }elseif($c===403){
    echo "✗ 403 — bu ClickSend hesabinin FAX yetkisi YOK.\n";
    echo "             Hicbir faks hic gonderilmemis olabilir. ClickSend panelinden\n";
    echo "             fax hizmetini/alt-hesap yetkisini acmak gerekiyor.\n";
  }else{
    echo "? HTTP $c — yukaridaki ham yaniti degerlendirecegim.\n";
  }
}

/* ─────────── [C] LetterXpress bakiye ─────────── */
echo "\n\n=== [C] LetterXpress bakiye (tekrar, net) ===\n";
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
  echo "  bakiye: ".($bal===null?'?':$bal)." EUR   mod: ".$auth['mode']."\n";
  if($bal!==null && (float)$bal<=0){
    echo "  ✗ BAKIYE SIFIR/EKSI -> yeni mektup isleri reddedilir veya askida kalir.\n";
    echo "    LetterXpress hesabina kredi yuklenmeli.\n";
  }else{
    echo "  ✓ bakiye pozitif\n";
  }
}

/* ─────────── [D] Arayuz: gonderim hatasi kullaniciya gosteriliyor mu ─────────── */
echo "\n\n=== [D] Arayuz: send_fax yaniti nasil isleniyor ===\n";
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx===''){ echo "  index.php okunamadi\n"; }
else{
  $off=0;$n=0;
  while(($p=strpos($idx,'send_fax',$off))!==false && $n<4){
    echo "  [@$p] ...".preg_replace('/\s+/',' ',substr($idx,max(0,$p-260),700))."...\n\n";
    $off=$p+8;$n++;
  }
  if(!$n) echo "  (send_fax cagrisi bulunamadi)\n";
}

echo "\n== BITTI — ciktinin TAMAMINI gonder ==\n";
