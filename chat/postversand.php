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

/* ── Fax (Phaxio) ── */
function fax_configured(){ return defined('PHAXIO_KEY') && defined('PHAXIO_SECRET') && constant('PHAXIO_KEY') && constant('PHAXIO_SECRET'); }
function fax_e164($n){ $n=preg_replace('/[^\d+]/','',(string)$n); if($n==='') return ''; if($n[0]==='+') return $n; if(strpos($n,'00')===0) return '+'.substr($n,2); if($n[0]==='0') return '+49'.substr($n,1); return '+'.$n; }


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
/* ── DIN5008 mektup PDF: alici adresi (pencere konumu) + govde + imza JPEG ── */
function ch_letter_pdf(string $text, string $recipient='', string $sender='', string $sigJpeg=''): string {
  $enc=function($s){ $t=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s); return $t===false?$s:$t; };
  $e=function($s){ return str_replace(['\\\\','(',')'],['\\\\\\\\','\\(','\\)'],$s); };
  $text=$enc($text); $recipient=$enc($recipient); $sender=$enc($sender);
  $lines=[];
  foreach(explode("\n",str_replace("\r",'',$text)) as $ln){
    if($ln===''){ $lines[]=''; continue; }
    while(strlen($ln)>90){ $cut=strrpos(substr($ln,0,90),' '); if($cut===false||$cut<40)$cut=90; $lines[]=substr($ln,0,$cut); $ln=ltrim(substr($ln,$cut)); }
    $lines[]=$ln;
  }
  $c="";
  if($sender!==''){ $c.="BT /F1 8 Tf 56 800 Td 10 TL\n"; foreach(explode("\n",$sender) as $sl){ $c.="(".$e($sl).") Tj T*\n"; } $c.="ET\n"; }
  if($recipient!==''){ $c.="BT /F1 11 Tf 64 725 Td 14 TL\n"; foreach(explode("\n",$recipient) as $rl){ $c.="(".$e($rl).") Tj T*\n"; } $c.="ET\n"; }
  $c.="BT /F1 11 Tf 56 640 Td 15 TL\n";
  foreach($lines as $i=>$ln){ $c.=($i? "T*\n":"")."(".$e($ln).") Tj\n"; }
  $c.="ET\n";
  $imgW=0;$imgH=0;
  if($sigJpeg!==''){ list($imgW,$imgH)=ch_jpeg_dims($sigJpeg); $dw=120; $dh=($imgW>0?intval($dw*$imgH/$imgW):40); if($dh>60)$dh=60; $c.="q $dw 0 0 $dh 56 140 cm /Im1 Do Q\n"; }
  $objs=[];
  $objs[1]="<</Type/Catalog /Pages 2 0 R>>";
  $objs[3]="<</Type/Font /Subtype/Type1 /BaseFont/Helvetica /Encoding/WinAnsiEncoding>>";
  $res="<</Font<</F1 3 0 R>>";
  if($sigJpeg!==''){ $objs[5]="<</Type/XObject /Subtype/Image /Width $imgW /Height $imgH /ColorSpace/DeviceRGB /BitsPerComponent 8 /Filter/DCTDecode /Length ".strlen($sigJpeg).">>\nstream\n".$sigJpeg."\nendstream"; $res.=" /XObject<</Im1 5 0 R>>"; }
  $res.=">>";
  $objs[4]="<</Length ".strlen($c).">>\nstream\n".$c."\nendstream";
  $objs[6]="<</Type/Page /Parent 2 0 R /MediaBox[0 0 595 842] /Resources $res /Contents 4 0 R>>";
  $objs[2]="<</Type/Pages /Kids[6 0 R] /Count 1>>";
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

$action = $_GET['action'] ?? 'status';

if ($action==='status') {
  $bal=null;
  if($configured){ list($c,$r)=lx_call('getBalance',['auth'=>lx_auth()]); $j=json_decode((string)$r,true); if(is_array($j)) $bal=$j['balance']??null; }
  out(['ok'=>true,'configured'=>$configured,'mode'=>$mode,'provider'=>'LetterXpress','balance'=>$bal,'fax_configured'=>fax_configured()]);
}

if ($action==='send_fax') {
  if (!fax_configured()) out(['error'=>'fax_not_configured']);
  $b=json_decode((string)file_get_contents('php://input'),true);
  if(!is_array($b)) out(['error'=>'bad_request']);
  $text=trim((string)($b['text']??'')); if(strlen($text)<20) out(['error'=>'no_text']);
  $faxnr=fax_e164($b['fax_number']??''); if(strlen($faxnr)<6) out(['error'=>'no_fax_number']);
  $recipient=trim((string)($b['recipient']??'')); $sender=trim((string)($b['sender']??''));
  $sig='';
  if(!empty($b['sig_jpeg'])){ $sj=preg_replace('#^data:image/jpe?g;base64,#','',(string)$b['sig_jpeg']); $sig=base64_decode($sj); if($sig===false)$sig=''; }
  $pdfBin=ch_letter_pdf($text,$recipient,$sender,$sig);
  $tmp=tempnam(sys_get_temp_dir(),'fax').'.pdf'; file_put_contents($tmp,$pdfBin);
  $ch=curl_init('https://api.phaxio.com/v2/faxes');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_USERPWD=>constant('PHAXIO_KEY').':'.constant('PHAXIO_SECRET'),
    CURLOPT_POSTFIELDS=>['to'=>$faxnr,'file[]'=>new CURLFile($tmp,'application/pdf','dokument.pdf')],
    CURLOPT_TIMEOUT=>90]);
  $res=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); @unlink($tmp);
  if($res===false) out(['error'=>'network','http'=>$code,'detail'=>$err]);
  $j=json_decode((string)$res,true);
  out(['ok'=>($code>=200&&$code<300 && !empty($j['success'])),'http'=>$code,'to'=>$faxnr,'pdf_bytes'=>strlen($pdfBin),'provider'=>'Phaxio','result'=>is_array($j)?$j:substr((string)$res,0,300)]);
}

if ($action==='send' || $action==='send_text') {
  if (!$configured) out(['error'=>'not_configured']);
  $b=json_decode((string)file_get_contents('php://input'),true);
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
  $b=json_decode((string)file_get_contents('php://input'),true);
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

out(['error'=>'unknown_action']);
