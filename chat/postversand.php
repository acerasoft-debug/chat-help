<?php
/**
 * ChatHelp — postversand.php — hibrit posta (LetterXpress v1) gonderim ucu.
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
  out(['ok'=>true,'configured'=>$configured,'mode'=>$mode,'provider'=>'LetterXpress','balance'=>$bal]);
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
  $payload=['auth'=>lx_auth(),'letter'=>[
    'base64_file'=>$pdf,'base64_checksum'=>md5($pdf),
    'specification'=>['color'=>!empty($b['color'])?'4':'1','mode'=>!empty($b['duplex'])?'duplex':'simplex','ship'=>'national'],
  ]];
  list($code,$res,$err)=lx_call('setJob',$payload);
  if($res===false) out(['error'=>'network','http'=>$code,'detail'=>$err]);
  $j=json_decode((string)$res,true);
  out(['ok'=>($code>=200&&$code<300),'http'=>$code,'mode'=>$mode,'result'=>is_array($j)?$j:substr((string)$res,0,300)]);
}
out(['error'=>'unknown_action']);
