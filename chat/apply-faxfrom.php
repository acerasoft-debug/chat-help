<?php
/**
 * ChatHelp — apply-faxfrom (CH_FAXFROM) — FAKS KESIN COZUM (sunucu tarafi).
 *
 * KOK NEDEN (Sinch destek e-postasi ile kanitli):
 *   422 — "No From number was specified or available on the project for use."
 *   fax_send_sinch() istege 'from' (gonderen faks numarasi) hic koymuyor,
 *   projede de varsayilan yok -> Sinch her gonderimi ilk adimda reddediyor.
 *   Arayuz ok:false yanitini yuttugu icin kullanici hicbir hata gormuyor.
 *   Yani bugune kadar TEK BIR FAKS BILE cikmamis.
 *
 * BU YAMA (postversand.php — index.php'ye DOKUNMAZ):
 *  [1] ch_sinch_from()  : gonderen numarayi bulur —
 *        once config'teki SINCH_FROM, yoksa Sinch Numbers API'den OTOMATIK
 *        kesfeder (fax yetenekli numarayi tercih eder), 24 saat onbellekler.
 *  [2] 'from' alani gonderime eklenir  -> 422 biter.
 *  [3] Gonderen numara YOKSA sessizce 422 yemek yerine NET hata doner:
 *        {"error":"no_from_number", ...}   (arayuz artik gosterebilir)
 *  [4] Numara dogrulamasi: 8-15 hane, hep ayni rakam / 1234567890 gibi
 *        "sallama" numaralar PARA HARCAMADAN reddedilir.
 *  [5] Yanita 'mid' (Sinch fax id) eklenir -> gonderim takip edilebilir.
 *  [6] fax_status artik SINCH'i de destekler (eskiden yalniz ClickSend'di,
 *        bu yuzden Meine Sendungen'deki durum butonu hep 'na' donuyordu).
 *
 * Her adim birebir literal eslesme ister (tam 1 kez), yoksa o adim ATLANIR ve
 * raporlanir. Cekirdek adimlar tutmazsa HICBIR SEY yazilmaz. Lint + .bak var.
 *
 * KULLANIM: /chat/pull2.php?key=...&files=apply-faxfrom.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-faxfrom BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$f=__DIR__.'/postversand.php';
$src=@file_get_contents($f);
if($src===false||$src==='') exit("postversand.php okunamadi\n");
echo "postversand.php: ".strlen($src)." B  (".date('Y-m-d H:i',filemtime($f)).")\n";

if(strpos($src,'CH_FAXFROM')!==false) exit("\nZaten ekli (CH_FAXFROM) — DEGISIKLIK YOK.\n");
if(strpos($src,'function fax_send_sinch')===false)
  exit("\n✗ Sinch adaptoru yok (fax_send_sinch bulunamadi) — once apply-sinchfax.php gerekli. DEGISIKLIK YOK.\n");

$new=$src; $rep=[]; $skip=[];
function tryrep(&$s,$search,$replace,$label,&$rep,&$skip){
  $c=substr_count($s,$search);
  if($c!==1){ $skip[]="$label (eslesme=$c)"; return false; }
  $s=str_replace($search,$replace,$s); $rep[]=$label; return true;
}

/* ───────── [1] yardimci fonksiyon blogu ───────── */
$HELPERS = <<<'PHPB'
/* CH_FAXFROM — gonderen numara + dogrulama + Sinch durum sorgusu */
function ch_sinch_discover_from(){
  $tok=sinch_token(); if(!$tok) return '';
  $pid=defined('SINCH_PROJECT_ID')?(string)constant('SINCH_PROJECT_ID'):''; if($pid==='') return '';
  $ch=curl_init('https://numbers.api.sinch.com/v1/projects/'.rawurlencode($pid).'/activeNumbers');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_TIMEOUT=>30]);
  $r=curl_exec($ch); curl_close($ch);
  $j=json_decode((string)$r,true);
  $list=is_array($j)?($j['activeNumbers']??($j['numbers']??[])):[];
  $fallback='';
  if(is_array($list)) foreach($list as $it){
    if(!is_array($it)) continue;
    $num=(string)($it['phoneNumber']??($it['number']??''));
    if($num==='') continue;
    $cap=$it['capability']??($it['capabilities']??[]);
    $caps=is_array($cap)?strtoupper(implode(',',$cap)):strtoupper((string)$cap);
    if(strpos($caps,'FAX')!==false) return $num;
    if($fallback==='') $fallback=$num;
  }
  return $fallback;
}
function ch_sinch_from(){
  if(defined('SINCH_FROM') && constant('SINCH_FROM')) return (string)constant('SINCH_FROM');
  $cf=__DIR__.'/.sinch-from.json';
  if(is_file($cf)){
    $j=json_decode((string)@file_get_contents($cf),true);
    if(is_array($j) && !empty($j['from']) && (time()-(int)($j['t']??0))<86400) return (string)$j['from'];
  }
  $n=ch_sinch_discover_from();
  if($n!=='') @file_put_contents($cf,json_encode(['from'=>$n,'t'=>time()]));
  return $n;
}
function ch_sinch_fields($faxnr,$tmp){
  $fl=['to'=>fax_e164($faxnr),'file'=>new CURLFile($tmp,'application/pdf','dokument.pdf')];
  $from=ch_sinch_from(); if($from!=='') $fl['from']=$from;
  return $fl;
}
/* '' = gecerli; aksi halde sebep kodu */
function ch_fax_validate($n){
  $d=preg_replace('/\D/','',(string)$n);
  if(strlen($d)<8)  return 'zu_kurz';
  if(strlen($d)>15) return 'zu_lang';
  if(preg_match('/^(\d)\1+$/',$d)) return 'unrealistisch';
  if(strpos('01234567890',$d)!==false) return 'unrealistisch';
  if(strpos('09876543210',$d)!==false) return 'unrealistisch';
  return '';
}
function ch_fax_mid($r){
  $res=is_array($r)?($r['result']??null):null;
  if(!is_array($res)) return '';
  if(!empty($res['id'])) return (string)$res['id'];
  $m=$res['data']['messages'][0]['message_id']??'';
  return (string)$m;
}
function ch_sinch_status($id){
  $id=preg_replace('/[^A-Za-z0-9_\-]/','',(string)$id);
  if($id==='') return ['ok'=>false,'provider'=>'Sinch','error'=>'no_id','found'=>false];
  $tok=sinch_token(); if(!$tok) return ['ok'=>false,'provider'=>'Sinch','error'=>'no_token','found'=>false];
  $pid=defined('SINCH_PROJECT_ID')?(string)constant('SINCH_PROJECT_ID'):'';
  $ch=curl_init('https://fax.api.sinch.com/v3/projects/'.rawurlencode($pid).'/faxes/'.rawurlencode($id));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_TIMEOUT=>30]);
  $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $j=json_decode((string)$r,true); $ok=($c>=200&&$c<300);
  return ['ok'=>$ok,'http'=>$c,'mid'=>$id,'provider'=>'Sinch','found'=>$ok,
    'status'=>is_array($j)?($j['status']??null):null,
    'to'=>is_array($j)?($j['to']??null):null,
    'pages'=>is_array($j)?($j['numberOfPages']??null):null,
    'cost'=>is_array($j)?($j['price']['amount']??null):null,
    'date'=>is_array($j)?($j['createTime']??null):null,
    'errorCode'=>is_array($j)?($j['errorCode']??null):null];
}

PHPB;

$A1="function sinch_token(){";
if(!tryrep($new,$A1,$HELPERS.$A1,'[1] yardimci fonksiyonlar',$rep,$skip))
  exit("\n✗ CEKIRDEK ADIM 1 TUTMADI — DEGISIKLIK YOK.\n  ".implode("\n  ",$skip)."\n");

/* ───────── [2] 'from' alanini gonderime ekle ───────── */
$A2="    CURLOPT_POSTFIELDS=>['to'=>fax_e164(\$faxnr),'file'=>new CURLFile(\$tmp,'application/pdf','dokument.pdf')],";
$B2="    CURLOPT_POSTFIELDS=>ch_sinch_fields(\$faxnr,\$tmp), /*CH_FAXFROM*/";
if(!tryrep($new,$A2,$B2,"[2] 'from' alani eklendi",$rep,$skip))
  exit("\n✗ CEKIRDEK ADIM 2 TUTMADI — DEGISIKLIK YOK.\n  ".implode("\n  ",$skip)."\n");

/* ───────── [3] numara dogrulama + gonderen yoksa net hata ───────── */
$A3="  \$faxnr=fax_e164(\$b['fax_number']??''); if(strlen(\$faxnr)<6) out(['error'=>'no_fax_number']);";
$B3=$A3."\n"
   ."  /*CH_FAXFROM*/ \$chVal=ch_fax_validate(\$faxnr);\n"
   ."  if(\$chVal!=='') out(['error'=>'bad_fax_number','reason'=>\$chVal,'to'=>\$faxnr]);\n"
   ."  /*CH_FAXFROM*/ \$chFrom='';\n"
   ."  if(fax_provider()==='sinch'){ \$chFrom=ch_sinch_from();\n"
   ."    if(\$chFrom==='') out(['error'=>'no_from_number','provider'=>'Sinch',\n"
   ."      'hint'=>'Im Sinch-Projekt ist keine Absender-Faxnummer vorhanden. Bitte eine Fax-Nummer buchen oder SINCH_FROM setzen.']); }";
if(!tryrep($new,$A3,$B3,'[3] numara dogrulama + no_from_number',$rep,$skip))
  exit("\n✗ CEKIRDEK ADIM 3 TUTMADI — DEGISIKLIK YOK.\n  ".implode("\n  ",$skip)."\n");

/* ───────── [4] yanita mid + from (istege bagli) ───────── */
$A4="  out(['ok'=>!empty(\$r['ok']),'http'=>\$r['http']??0,'to'=>\$faxnr,'pdf_bytes'=>strlen(\$pdfBin),'provider'=>\$r['provider']??fax_provider(),'result'=>\$r['result']??null]);";
$B4="  out(['ok'=>!empty(\$r['ok']),'http'=>\$r['http']??0,'to'=>\$faxnr,'from'=>\$chFrom,'mid'=>ch_fax_mid(\$r),'pdf_bytes'=>strlen(\$pdfBin),'provider'=>\$r['provider']??fax_provider(),'result'=>\$r['result']??null]); /*CH_FAXFROM*/";
tryrep($new,$A4,$B4,'[4] yanita mid+from eklendi',$rep,$skip);

/* ───────── [5] fax_status Sinch destegi (istege bagli) ───────── */
$A5="  if (!fax_configured()||fax_provider()!=='clicksend') out(['ok'=>false,'error'=>'na']);";
$B5="  /*CH_FAXFROM*/ if (fax_provider()==='sinch') out(ch_sinch_status((string)(\$_GET['mid']??'')));\n".$A5;
tryrep($new,$A5,$B5,'[5] fax_status Sinch destegi',$rep,$skip);

/* ───────── saglik + lint + yaz ───────── */
foreach(['function fax_send_sinch'=>1,'function sinch_token'=>1,'function fax_send('=>1,
         "action==='send_fax'"=>1,'CH_SINCHFAX'=>1,'CH_FAXFROM'=>1] as $m=>$min){
  if(substr_count($new,$m)<$min) exit("✗ Kritik parca kayboldu ($m) — DEGISIKLIK YOK\n");
}

$tmp=tempnam(sys_get_temp_dir(),'ff').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — postversand.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($f.'.bak-faxfrom-'.date('Ymd-His'),$src);
$w=@file_put_contents($f,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$cur=(string)@file_get_contents($f);
echo "\n✓ CH_FAXFROM uygulandi (".strlen($src)." -> ".strlen($cur)." bayt)\n";
echo "  uygulanan: \n    - ".implode("\n    - ",$rep)."\n";
if($skip) echo "  ATLANAN (anchor tutmadi):\n    - ".implode("\n    - ",$skip)."\n";

/* ───────── canli kontrol: gonderen numara bulunuyor mu ───────── */
echo "\n=== CANLI KONTROL: gonderen faks numarasi ===\n";
ob_start(); @include_once __DIR__.'/config.php'; ob_end_clean();
$prov = defined('FAX_PROVIDER') ? strtolower((string)constant('FAX_PROVIDER')) : 'clicksend';
echo "  FAX_PROVIDER : $prov\n";
echo "  SINCH_FROM   : ".(defined('SINCH_FROM')&&constant('SINCH_FROM')?constant('SINCH_FROM'):'(tanimsiz — otomatik kesfedilecek)')."\n";
if($prov==='sinch'){
  $kid=defined('SINCH_KEY_ID')?(string)constant('SINCH_KEY_ID'):'';
  $ksec=defined('SINCH_KEY_SECRET')?(string)constant('SINCH_KEY_SECRET'):'';
  $pid=defined('SINCH_PROJECT_ID')?(string)constant('SINCH_PROJECT_ID'):'';
  if(!$kid||!$ksec||!$pid){ echo "  ✗ SINCH kimlikleri eksik — kontrol yapilamadi\n"; }
  else{
    $ch=curl_init('https://auth.sinch.com/oauth2/token');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_USERPWD=>$kid.':'.$ksec,
      CURLOPT_POSTFIELDS=>'grant_type=client_credentials',
      CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>30]);
    $r=curl_exec($ch); $tc=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $tj=json_decode((string)$r,true); $tok=is_array($tj)?(string)($tj['access_token']??''):'';
    echo "  OAuth token  : ".($tok?'ALINDI ✓':"ALINAMADI ✗ (HTTP $tc)")."\n";
    if($tok){
      $ch=curl_init('https://numbers.api.sinch.com/v1/projects/'.rawurlencode($pid).'/activeNumbers');
      curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_TIMEOUT=>30]);
      $r=curl_exec($ch); $nc=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      $j=json_decode((string)$r,true);
      $list=is_array($j)?($j['activeNumbers']??($j['numbers']??[])):[];
      echo "  activeNumbers: HTTP $nc, ".(is_array($list)?count($list):0)." numara\n";
      $pick=''; $fb='';
      if(is_array($list)) foreach($list as $it){
        if(!is_array($it)) continue;
        $num=(string)($it['phoneNumber']??($it['number']??'')); if($num==='') continue;
        $cap=$it['capability']??($it['capabilities']??[]);
        $caps=is_array($cap)?strtoupper(implode('/',$cap)):strtoupper((string)$cap);
        echo "     $num   yetenek=".($caps?:'-')."\n";
        if($pick==='' && strpos($caps,'FAX')!==false) $pick=$num;
        if($fb==='') $fb=$num;
      }
      if($pick==='') $pick=$fb;
      if($pick!==''){
        echo "\n  ✓ GONDEREN NUMARA: $pick\n";
        echo "    -> 422 bitti. Gercek bir faks numarasina test gonderebilirsin.\n";
        @file_put_contents(__DIR__.'/.sinch-from.json',json_encode(['from'=>$pick,'t'=>time()]));
        echo "    -> onbellege yazildi (.sinch-from.json)\n";
      }else{
        echo "\n  ✗ GONDEREN NUMARA YOK — projede hic numara kayitli degil.\n";
        echo "    -> Sinch panelinden FAKS yetenekli bir numara kiralaman gerekiyor.\n";
        echo "    -> O ana kadar gonderim sessiz kalmayacak, NET hata verecek: no_from_number\n";
        echo "    ham: ".substr(preg_replace('/\s+/',' ',(string)$r),0,240)."\n";
      }
    }
  }
}else{
  echo "  (saglayici sinch degil — from kontrolu atlandi)\n";
}

echo "\nSONRAKI ADIM:\n";
echo "  1) Sinch panelinde FAKS yetenekli numara yoksa kirala (aylik birkac euro).\n";
echo "  2) LetterXpress bakiyesi eksi (-1.04 EUR) — Brief icin kredi yukle.\n";
echo "  3) Arayuz yamasi: hatayi kullaniciya goster (dump-sinchfax3 ciktisindan sonra).\n";
