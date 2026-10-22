<?php
/**
 * ChatHelp — postversand.php — hibrit posta gonderim ucu.
 *  LetterXpress v1 (Brief/Einschreiben) + Phaxio (Fax).
 *  Kimlikler config.php'den: LETTERXPRESS_USER / LETTERXPRESS_APIKEY / LETTERXPRESS_MODE.
 *  action=status    : servis bagli mi + bakiye
 *  action=send      : {pdf_base64}            -> setJob
 *  action=send_text : {text, subject?}        -> metinden GERCEK PDF uretilir -> setJob
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '')==='OPTIONS') { http_response_code(204); exit; }
error_reporting(E_ERROR | E_PARSE);
@include_once __DIR__.'/config.php';
function out($a){ echo json_encode($a); exit; }

$configured = defined('LETTERXPRESS_USER') && defined('LETTERXPRESS_APIKEY')
  && constant('LETTERXPRESS_USER') && constant('LETTERXPRESS_APIKEY');
$mode = defined('LETTERXPRESS_MODE') ? constant('LETTERXPRESS_MODE') : 'test';

function lx_call($path,$payload){
  $ch=curl_init('https://api.letterxpress.de/v1/'.$path);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>60]);
  $res=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return [$code,$res,$err];
}
function lx_auth(){ global $mode; return ['username'=>constant('LETTERXPRESS_USER'),'apikey'=>constant('LETTERXPRESS_APIKEY'),'mode'=>($mode==='live'?'live':'test')]; }

/* ── Fax: saglayici anahtari (ClickSend varsayilan — kendi fax no'su gerekmez;
   interfax/faxde/phaxio yedek) ── */
function fax_provider(){ return defined('FAX_PROVIDER') ? strtolower((string)constant('FAX_PROVIDER')) : 'clicksend'; }
function fax_configured(){
  $p=fax_provider();
  if($p==='phaxio')   return defined('PHAXIO_KEY') && defined('PHAXIO_SECRET') && constant('PHAXIO_KEY') && constant('PHAXIO_SECRET');
  if($p==='faxde')    return defined('FAXDE_TOKEN') && constant('FAXDE_TOKEN');
  if($p==='interfax') return defined('INTERFAX_USER') && defined('INTERFAX_PASS') && constant('INTERFAX_USER') && constant('INTERFAX_PASS');
  return defined('CLICKSEND_USER') && defined('CLICKSEND_KEY') && constant('CLICKSEND_USER') && constant('CLICKSEND_KEY'); /* clicksend */
}
function fax_e164($n){ $n=preg_replace('/[^\d+]/','',(string)$n); if($n==='') return ''; if($n[0]==='+') return $n; if(strpos($n,'00')===0) return '+'.substr($n,2); if($n[0]==='0') return '+49'.substr($n,1); return '+'.$n; }
/* yerel/DE bicimi (fax-api.de genelde 0049... veya 0... bekler; E.164 + ise 00'a cevir) */
function fax_local($n){ $e=fax_e164($n); if($e==='') return ''; return (strpos($e,'+')===0) ? '00'.substr($e,1) : $e; }
function faxde_endpoint(){ return defined('FAXDE_ENDPOINT') ? rtrim((string)constant('FAXDE_ENDPOINT'),'/') : 'https://service.fax-api.de/4.0/rest'; }

/* Phaxio: multipart file[] + Basic auth */
function fax_send_phaxio($pdfBin,$faxnr){
  $tmp=tempnam(sys_get_temp_dir(),'fax').'.pdf'; file_put_contents($tmp,$pdfBin);
  $ch=curl_init('https://api.phaxio.com/v2/faxes');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_USERPWD=>constant('PHAXIO_KEY').':'.constant('PHAXIO_SECRET'),
    CURLOPT_POSTFIELDS=>['to'=>fax_e164($faxnr),'file[]'=>new CURLFile($tmp,'application/pdf','dokument.pdf')],
    CURLOPT_TIMEOUT=>90]);
  $res=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); @unlink($tmp);
  $j=json_decode((string)$res,true);
  return ['ok'=>($code>=200&&$code<300&&!empty($j['success'])),'http'=>$code,'provider'=>'Phaxio','err'=>$err,'result'=>is_array($j)?$j:substr((string)$res,0,400)];
}
/* fax-api.de (Faxsuite REST): X-Auth-Token + JSON. ALAN ADLARI config'den ayarlanabilir
   (canli API'ye probe ile dogrulanip kesinlestirilecek). */
function fax_send_faxde($pdfBin,$faxnr){
  $path = defined('FAXDE_SEND_PATH') ? (string)constant('FAXDE_SEND_PATH') : '/sendfax';
  $fRec = defined('FAXDE_FIELD_RECIPIENT') ? (string)constant('FAXDE_FIELD_RECIPIENT') : 'recipient';
  $fDoc = defined('FAXDE_FIELD_DOCUMENT')  ? (string)constant('FAXDE_FIELD_DOCUMENT')  : 'filedata';
  $fName= defined('FAXDE_FIELD_FILENAME')  ? (string)constant('FAXDE_FIELD_FILENAME')  : 'filename';
  $payload=[ $fRec=>fax_local($faxnr), $fName=>'dokument.pdf', $fDoc=>base64_encode($pdfBin) ];
  $ch=curl_init(faxde_endpoint().$path);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Auth-Token: '.constant('FAXDE_TOKEN')],
    CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>90]);
  $res=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $j=json_decode((string)$res,true);
  $ok=($code>=200&&$code<300);
  if(is_array($j) && (isset($j['error'])||isset($j['errors'])||(isset($j['status'])&&$j['status']==='error'))) $ok=false;
  return ['ok'=>$ok,'http'=>$code,'provider'=>'fax-api.de','err'=>$err,'result'=>is_array($j)?$j:substr((string)$res,0,400)];
}
/* InterFAX: POST /outbound/faxes?faxNumber=+49... ; Basic auth ; body=ham PDF ; 201+Location */
function fax_send_interfax($pdfBin,$faxnr){
  $url='https://rest.interfax.net/outbound/faxes?faxNumber='.rawurlencode(fax_e164($faxnr));
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HEADER=>true,
    CURLOPT_USERPWD=>constant('INTERFAX_USER').':'.constant('INTERFAX_PASS'),
    CURLOPT_HTTPHEADER=>['Content-Type: application/pdf'],
    CURLOPT_POSTFIELDS=>$pdfBin,CURLOPT_TIMEOUT=>90]);
  $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch);
  $hs=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE); curl_close($ch);
  $head=substr((string)$raw,0,$hs); $body=substr((string)$raw,$hs);
  $loc=''; if(preg_match('/^location:\s*(.+)$/mi',$head,$m)) $loc=trim($m[1]);
  $id=''; if($loc && preg_match('#/faxes/(\d+)#',$loc,$mm)) $id=$mm[1];
  return ['ok'=>($code>=200&&$code<300),'http'=>$code,'provider'=>'InterFAX','err'=>$err,
    'result'=>['fax_id'=>$id,'location'=>$loc,'body'=>substr(trim($body),0,300)]];
}
/* ClickSend: kendi fax no'su GEREKMEZ. 1) uploads?convert=fax (base64) -> _url  2) fax/send {file_url,messages[]} */
function fax_send_clicksend($pdfBin,$faxnr){
  $auth=constant('CLICKSEND_USER').':'.constant('CLICKSEND_KEY');
  $up=curl_init('https://rest.clicksend.com/v3/uploads?convert=fax');
  curl_setopt_array($up,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_USERPWD=>$auth,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['content'=>base64_encode($pdfBin)]),CURLOPT_TIMEOUT=>90]);
  $ur=curl_exec($up); $uc=(int)curl_getinfo($up,CURLINFO_HTTP_CODE); $ue=curl_error($up); curl_close($up);
  $uj=json_decode((string)$ur,true);
  $fileUrl=''; if(is_array($uj)){ $d=$uj['data']??null; if(is_array($d)) $fileUrl=$d['_url']??($d['url']??''); elseif(is_string($d)) $fileUrl=$d; }
  if(!$fileUrl) return ['ok'=>false,'http'=>$uc,'provider'=>'ClickSend','err'=>$ue?:'upload_failed','result'=>is_array($uj)?$uj:substr((string)$ur,0,400)];
  $payload=['file_url'=>$fileUrl,'messages'=>[['source'=>'chathelp','to'=>fax_e164($faxnr)]]];
  $sd=curl_init('https://rest.clicksend.com/v3/fax/send');
  curl_setopt_array($sd,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_USERPWD=>$auth,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>90]);
  $sr=curl_exec($sd); $sc=(int)curl_getinfo($sd,CURLINFO_HTTP_CODE); $se=curl_error($sd); curl_close($sd);
  $sj=json_decode((string)$sr,true);
  $ok=($sc>=200&&$sc<300); if(is_array($sj)&&isset($sj['response_code'])) $ok=($sj['response_code']==='SUCCESS');
  return ['ok'=>$ok,'http'=>$sc,'provider'=>'ClickSend','err'=>$se,'result'=>is_array($sj)?$sj:substr((string)$sr,0,400)];
}
function fax_send($pdfBin,$faxnr){ $p=fax_provider();
  if($p==='phaxio')   return fax_send_phaxio($pdfBin,$faxnr);
  if($p==='faxde')    return fax_send_faxde($pdfBin,$faxnr);
  if($p==='interfax') return fax_send_interfax($pdfBin,$faxnr);
  return fax_send_clicksend($pdfBin,$faxnr);
}


/* ── JPEG boyutu (SOF) ── */
function ch_jpeg_dims(string $j){
  $i=2; $len=strlen($j);
  while($i<$len){
    if(ord($j[$i])!==0xFF){ $i++; continue; }
    $m=ord($j[$i+1]); $i+=2;
    if($m===0xD8||$m===0xD9||($m>=0xD0&&$m<=0xD7)) continue;
    if($i+2>$len) break;
    $seglen=(ord($j[$i])<<8)+ord($j[$i+1]);
    if($m>=0xC0 && $m<=0xCF && $m!==0xC4 && $m!==0xC8 && $m!==0xCC){
      $h=(ord($j[$i+3])<<8)+ord($j[$i+4]); $w=(ord($j[$i+5])<<8)+ord($j[$i+6]); return [$w,$h];
    }
    $i+=$seglen;
  }
  return [300,90];
}
/* ── DIN5008 mektup PDF v2 (CH_LXV2): pencere-uyumlu alici + sayfalama + imza isim altinda ── */
function ch_letter_pdf(string $text, string $recipient='', string $sender='', string $sigJpeg=''): string {
  $enc=function($s){ $t=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s); return $t===false?$s:$t; };
  $esc=function($s){ return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s); };
  $text=$enc($text); $recipient=$enc($recipient); $sender=$enc($sender);

  /* metnin basindaki gonderen/alici tekrarlari + iletisim satirlari + placeholder ayiklanir */
  $norm=function($s){ return preg_replace('/[^a-z0-9]/','',strtolower($s)); };
  $recL=array_values(array_filter(array_map('trim',explode("\n",$recipient)),function($x){return $x!=='';}));
  $sndParts=array_values(array_filter(array_map('trim',preg_split('/\n|\s+-\s+|·/',$sender)),function($x){return $x!=='';}));
  $recN=array_map($norm,$recL); $sndN=array_map($norm,$sndParts);
  $raw=explode("\n",str_replace("\r",'',$text));
  $out=[];
  foreach($raw as $idx=>$ln){
    $t=trim($ln); $n=$norm($t);
    if($t!==''){
      $dup=false;
      if(preg_match('/adresse bitte selbst eintragen/i',$t)) $dup=true;
      if(!$dup && $idx<14){
        if(($n!=='' && (in_array($n,$recN,true)||in_array($n,$sndN,true)))) $dup=true;
        if(!$dup && preg_match('/^(telefon|tel\.|fax|e-?mail)\s*[:.]/i',$t)) $dup=true;
        if(!$dup && count($sndN)>=2 && strlen($n)>8){
          for($i=0;$i<count($sndN)-1 && !$dup;$i++){ $acc='';
            for($j=$i;$j<count($sndN);$j++){ $acc.=$sndN[$j]; if($j>$i && $acc===$n){ $dup=true; break; } } }
        }
      }
      if(!$dup && count($recN)>=2 && strlen($n)>8){
        for($i=0;$i<count($recN)-1 && !$dup;$i++){ $acc='';
          for($j=$i;$j<count($recN);$j++){ $acc.=$recN[$j]; if($j>$i && $acc===$n){ $dup=true; break; } } }
      }
      if($dup) continue;
    }
    $out[]=rtrim($ln);
  }
  while($out && trim($out[0])==='') array_shift($out);
  while($out && trim((string)end($out))==='') array_pop($out);

  $lines=[]; $bl=0;
  foreach($out as $ln){
    if(trim($ln)===''){ $bl++; if($bl>1) continue; $lines[]=''; continue; }
    $bl=0;
    while(strlen($ln)>90){ $cut=strrpos(substr($ln,0,90),' '); if($cut===false||$cut<40)$cut=90; $lines[]=substr($ln,0,$cut); $ln=ltrim(substr($ln,$cut)); }
    $lines[]=$ln;
  }

  /* imza olculeri */
  $imgW=0;$imgH=0;$dw=0;$dh=0;
  if($sigJpeg!==''){ list($imgW,$imgH)=ch_jpeg_dims($sigJpeg); $dw=120; $dh=($imgW>0?intval($dw*$imgH/$imgW):40); if($dh>60)$dh=60; }

  /* sayfalama: s1 govde y=545 (pencere ALTI), sonraki sayfalar y=786; alt sinir 80 */
  $lead=15; $bottom=80;
  $cap1=intdiv(545-$bottom,$lead)+1; $capN=intdiv(786-$bottom,$lead)+1;
  $chunks=[]; $tops=[]; $rest=$lines;
  $chunks[]=array_splice($rest,0,$cap1); $tops[]=545;
  while(count($rest)){ $chunks[]=array_splice($rest,0,$capN); $tops[]=786; }

  /* imza: SON metin satirinin (isim) hemen altina; yer yoksa otomatik ek sayfa */
  $sigPage=-1; $sigY=0;
  if($sigJpeg!==''){
    $li=count($chunks)-1;
    $lastY=$tops[$li]-(max(count($chunks[$li]),1)-1)*$lead;
    $sigY=$lastY-14-$dh; $sigPage=$li;
    if($sigY<40){ $chunks[]=[]; $tops[]=786; $sigPage=count($chunks)-1; $sigY=786-$dh; }
  }

  /* nesneler: 1 catalog, 2 pages, 3 font, 4 imza (varsa), 5+ sayfalar */
  $objs=[]; $kids=[]; $n=4; $hasSig=($sigJpeg!=='');
  $res="<</Font<</F1 3 0 R>>".($hasSig?" /XObject<</Im1 4 0 R>>":"").">>";
  foreach($chunks as $pi=>$pl){
    $pn=++$n; $cn=++$n; $kids[]="$pn 0 R";
    $c='';
    if($pi===0){
      if($sender!==''){
        $sl=trim(preg_replace('/\s*\n\s*/',' - ',$sender));
        $c.="BT /F1 7.5 Tf 56 795 Td\n(".$esc($sl).") Tj\nET\n";
        $c.="0.4 w 56 790 m 305 790 l S\n";
      }
      if(count($recL)){
        $c.="BT /F1 11 Tf 60 698 Td 14 TL\n";
        foreach(array_slice($recL,0,6) as $rl){ $c.="(".$esc($rl).") Tj T*\n"; }
        $c.="ET\n";
      }
    }
    if(count($pl)){
      $top=$tops[$pi];
      $c.="BT /F1 11 Tf 56 $top Td $lead TL\n";
      foreach($pl as $i=>$ln){ $c.=($i?"T*\n":"")."(".$esc($ln).") Tj\n"; }
      $c.="ET\n";
    }
    if($hasSig && $pi===$sigPage){ $c.="q $dw 0 0 $dh 56 $sigY cm /Im1 Do Q\n"; }
    $objs[$pn]="<</Type/Page /Parent 2 0 R /MediaBox[0 0 595 842] /Resources $res /Contents $cn 0 R>>";
    $objs[$cn]="<</Length ".strlen($c).">>\nstream\n$c\nendstream";
  }
  $objs[1]="<</Type/Catalog /Pages 2 0 R>>";
  $objs[2]="<</Type/Pages /Kids[".implode(' ',$kids)."] /Count ".count($kids).">>";
  $objs[3]="<</Type/Font /Subtype/Type1 /BaseFont/Helvetica /Encoding/WinAnsiEncoding>>";
  if($hasSig){ $objs[4]="<</Type/XObject /Subtype/Image /Width $imgW /Height $imgH /ColorSpace/DeviceRGB /BitsPerComponent 8 /Filter/DCTDecode /Length ".strlen($sigJpeg).">>\nstream\n".$sigJpeg."\nendstream"; }
  ksort($objs);
  $pdf="%PDF-1.4\n"; $offs=[];
  foreach($objs as $num=>$body){ $offs[$num]=strlen($pdf); $pdf.="$num 0 obj\n$body\nendobj\n"; }
  $xref=strlen($pdf); $max=max(array_keys($objs));
  $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
  for($i=1;$i<=$max;$i++){ $pdf.=str_pad((string)($offs[$i]??0),10,'0',STR_PAD_LEFT)." 00000 n \n"; }
  $pdf.="trailer\n<</Size ".($max+1)." /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
  return $pdf;
}
/* ── basit ama gecerli PDF uretici (Helvetica, A4, cok sayfa) ── */
function ch_text_pdf(string $text): string {
  $t = @iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text); if($t===false) $t=$text;
  $lines=[]; foreach(explode("\n",str_replace("\r",'',$t)) as $ln){
    if($ln===''){ $lines[]=''; continue; }
    while(strlen($ln)>92){ $cut=strrpos(substr($ln,0,92),' '); if($cut===false||$cut<40)$cut=92; $lines[]=substr($ln,0,$cut); $ln=ltrim(substr($ln,$cut)); }
    $lines[]=$ln;
  }
  $perPage=48; $pages=array_chunk($lines,$perPage); if(!$pages)$pages=[['']];
  $objs=[]; $objs[1]=''; // catalog placeholder
  $kids=[]; $n=3; $pageObjs=[];
  foreach($pages as $pl){
    $pn=++$n; $cn=++$n; $kids[]="$pn 0 R";
    $esc=function($s){ return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s); };
    $stream="BT /F1 11 Tf 56 786 Td 15 TL\n";
    foreach($pl as $i=>$ln){ $stream.=($i? "T*\n":"")."(".$esc($ln).") Tj\n"; }
    $stream.="ET";
    $pageObjs[$pn]="<</Type/Page /Parent 2 0 R /MediaBox[0 0 595 842] /Resources<</Font<</F1 3 0 R>>>> /Contents $cn 0 R>>";
    $pageObjs[$cn]="<</Length ".strlen($stream).">>\nstream\n$stream\nendstream";
  }
  $objs[1]="<</Type/Catalog /Pages 2 0 R>>";
  $objs[2]="<</Type/Pages /Kids[".implode(' ',$kids)."] /Count ".count($kids).">>";
  $objs[3]="<</Type/Font /Subtype/Type1 /BaseFont/Helvetica /Encoding/WinAnsiEncoding>>";
  foreach($pageObjs as $k=>$v) $objs[$k]=$v;
  ksort($objs);
  $pdf="%PDF-1.4\n"; $offs=[];
  foreach($objs as $num=>$body){ $offs[$num]=strlen($pdf); $pdf.="$num 0 obj\n$body\nendobj\n"; }
  $xref=strlen($pdf); $max=max(array_keys($objs));
  $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
  for($i=1;$i<=$max;$i++){ $pdf.=str_pad((string)($offs[$i]??0),10,'0',STR_PAD_LEFT)." 00000 n \n"; }
  $pdf.="trailer\n<</Size ".($max+1)." /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
  return $pdf;
}

define('PV_VERSION','6');
/* action: URL'den; yoksa/eslesmesin diye JSON govdeden de kabul (bayat opcache/WAF dayanikliligi) */
$action = $_GET['action'] ?? '';
$__rawBody = file_get_contents('php://input');
if ($action==='' || $action===null) {
  $__b = json_decode((string)$__rawBody, true);
  if (is_array($__b) && !empty($__b['action'])) $action = (string)$__b['action'];
}
if ($action==='') $action='status';

if ($action==='status') {
  $bal=null;
  if($configured){ list($c,$r)=lx_call('getBalance',['auth'=>lx_auth()]); $j=json_decode((string)$r,true); if(is_array($j)) $bal=$j['balance']??null; }
  out(['ok'=>true,'v'=>PV_VERSION,'configured'=>$configured,'mode'=>$mode,'provider'=>'LetterXpress','balance'=>$bal,'fax_configured'=>fax_configured(),'fax_provider'=>fax_provider()]);
}

if ($action==='fax_status') {
  /* ClickSend fax durumu/Sendebericht — message_id ile gecmisten bul */
  if (!fax_configured()||fax_provider()!=='clicksend') out(['ok'=>false,'error'=>'na']);
  $mid=preg_replace('/[^0-9]/','',(string)($_GET['mid']??''));
  $ch=curl_init('https://rest.clicksend.com/v3/fax/history?limit=200');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_USERPWD=>constant('CLICKSEND_USER').':'.constant('CLICKSEND_KEY')]);
  $r=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $j=json_decode((string)$r,true); $hit=null;
  if(is_array($j)){ $rows=$j['data']['data']??($j['data']??[]); if(is_array($rows)) foreach($rows as $row){ if(is_array($row) && (string)($row['message_id']??$row['messageid']??'')===$mid){ $hit=$row; break; } } }
  out(['ok'=>($code>=200&&$code<300),'http'=>$code,'mid'=>$mid,
    'status'=>$hit['status']??null,'to'=>$hit['to']??null,'pages'=>$hit['pages']??null,'cost'=>$hit['cost']??$hit['price']??null,'date'=>$hit['date_added']??$hit['date']??null,
    'found'=>$hit!==null]);
}

if ($action==='letter_status') {
  /* LetterXpress is durumu + takip (Einschreiben Sendungsnummer) */
  if (!$configured) out(['ok'=>false,'error'=>'na']);
  $jid=preg_replace('/[^0-9]/','',(string)($_GET['jid']??''));
  list($c,$r)=lx_call('getJob',['auth'=>lx_auth(),'job'=>['id'=>$jid]]);
  $j=json_decode((string)$r,true);
  $d=is_array($j)?($j['job']??$j['data']??$j):null;
  out(['ok'=>($c>=200&&$c<300),'http'=>$c,'jid'=>$jid,
    'status'=>is_array($d)?($d['status']??null):null,
    'tracking'=>is_array($d)?($d['tracking']??$d['tracking_id']??$d['shipment']??null):null,
    'raw'=>is_array($j)?$j:substr((string)$r,0,300)]);
}

if ($action==='fax_ping') {
  /* GONDERIMSIZ baglanti kontrolu — ClickSend hesap/bakiye (auth dogrular) */
  if (!fax_configured()) out(['ok'=>false,'provider'=>fax_provider(),'error'=>'fax_not_configured']);
  if (fax_provider()!=='clicksend') out(['ok'=>true,'provider'=>fax_provider(),'note'=>'ping sadece clicksend icin']);
  $ch=curl_init('https://rest.clicksend.com/v3/account');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,
    CURLOPT_USERPWD=>constant('CLICKSEND_USER').':'.constant('CLICKSEND_KEY')]);
  $r=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $j=json_decode((string)$r,true);
  $ok=($code>=200&&$code<300 && is_array($j) && (($j['response_code']??'')==='SUCCESS'));
  $bal=null;$cur=null;$acc=null;
  if(is_array($j)&&isset($j['data'])){ $bal=$j['data']['balance']??null; $cur=$j['data']['currency']['currency_code']??($j['data']['currency']??null); $acc=$j['data']['username']??($j['data']['user_email']??null); }
  out(['ok'=>$ok,'provider'=>'ClickSend','http'=>$code,'balance'=>$bal,'currency'=>$cur,'account'=>$acc,'err'=>$err,
    'raw'=>is_array($j)?['response_code'=>$j['response_code']??null,'response_msg'=>$j['response_msg']??null]:substr((string)$r,0,200)]);
}

if ($action==='send_fax') {
  if (!fax_configured()) out(['error'=>'fax_not_configured']);
  $b=json_decode((string)$__rawBody,true);
  if(!is_array($b)) out(['error'=>'bad_request']);
  $text=trim((string)($b['text']??'')); if(strlen($text)<20) out(['error'=>'no_text']);
  $faxnr=fax_e164($b['fax_number']??''); if(strlen($faxnr)<6) out(['error'=>'no_fax_number']);
  $recipient=trim((string)($b['recipient']??'')); $sender=trim((string)($b['sender']??''));
  $sig='';
  if(!empty($b['sig_jpeg'])){ $sj=preg_replace('#^data:image/jpe?g;base64,#','',(string)$b['sig_jpeg']); $sig=base64_decode($sj); if($sig===false)$sig=''; }
  $pdfBin=ch_letter_pdf($text,$recipient,$sender,$sig);
  $r=fax_send($pdfBin,$faxnr);
  out(['ok'=>!empty($r['ok']),'http'=>$r['http']??0,'to'=>$faxnr,'pdf_bytes'=>strlen($pdfBin),'provider'=>$r['provider']??fax_provider(),'result'=>$r['result']??null]);
}

if ($action==='send' || $action==='send_text') {
  if (!$configured) out(['error'=>'not_configured']);
  $b=json_decode((string)$__rawBody,true);
  if(!is_array($b)) out(['error'=>'bad_request']);
  if($action==='send_text'){
    $text=trim((string)($b['text']??'')); if(strlen($text)<20) out(['error'=>'no_text']);
    $pdfBin=ch_text_pdf($text); $pdf=base64_encode($pdfBin);
  } else {
    $pdf=preg_replace('#^data:application/pdf;base64,#','',(string)($b['pdf_base64']??''));
    if(strlen($pdf)<100) out(['error'=>'no_pdf']);
  }
  $spec=['color'=>!empty($b['color'])?'4':'1','mode'=>!empty($b['duplex'])?'duplex':'simplex','ship'=>'national'];
  if(!empty($b['registered'])){ $rv=preg_replace('/[^a-z_]/','',strtolower((string)$b['registered'])); if($rv!=='') $spec['registered']=$rv; } /* einwurf | einschreiben (Uebergabe) */
  $payload=['auth'=>lx_auth(),'letter'=>[
    'base64_file'=>$pdf,'base64_checksum'=>md5($pdf),
    'specification'=>$spec,
  ]];
  list($code,$res,$err)=lx_call('setJob',$payload);
  if($res===false) out(['error'=>'network','http'=>$code,'detail'=>$err]);
  $j=json_decode((string)$res,true);
  out(['ok'=>($code>=200&&$code<300),'http'=>$code,'mode'=>$mode,'registered'=>!empty($b['registered'])?1:0,'result'=>is_array($j)?$j:substr((string)$res,0,300)]);
}

if ($action==='send_letter') {
  if (!$configured) out(['error'=>'not_configured']);
  $b=json_decode((string)$__rawBody,true);
  if(!is_array($b)) out(['error'=>'bad_request']);
  $text=trim((string)($b['text']??'')); if(strlen($text)<20) out(['error'=>'no_text']);
  $recipient=trim((string)($b['recipient']??''));
  $sender=trim((string)($b['sender']??''));
  $sig='';
  if(!empty($b['sig_jpeg'])){ $sj=preg_replace('#^data:image/jpe?g;base64,#','',(string)$b['sig_jpeg']); $sig=base64_decode($sj); if($sig===false)$sig=''; }
  $pdfBin=ch_letter_pdf($text,$recipient,$sender,$sig);
  $pdf=base64_encode($pdfBin);
  $spec=['color'=>!empty($b['color'])?'4':'1','mode'=>'simplex','ship'=>'national'];
  if(!empty($b['registered'])){ $rv=preg_replace('/[^a-z_]/','',strtolower((string)$b['registered'])); if($rv!=='') $spec['registered']=$rv; } /* einwurf | einschreiben (Uebergabe) */
  $payload=['auth'=>lx_auth(),'letter'=>[
    'base64_file'=>$pdf,'base64_checksum'=>md5($pdf),
    'specification'=>$spec,
  ]];
  list($code,$res,$err)=lx_call('setJob',$payload);
  if($res===false) out(['error'=>'network','http'=>$code,'detail'=>$err]);
  $j=json_decode((string)$res,true);
  out(['ok'=>($code>=200&&$code<300),'http'=>$code,'mode'=>$mode,'pdf_bytes'=>strlen($pdfBin),'registered'=>!empty($b['registered'])?1:0,'result'=>is_array($j)?$j:substr((string)$res,0,300)]);
}

out(['error'=>'unknown_action','received'=>$action,'v'=>PV_VERSION]);
