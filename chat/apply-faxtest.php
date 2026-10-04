<?php
/**
 * ChatHelp — apply-faxtest — GERCEK 1-sayfa test faksi gonderir (ornek olusur).
 *  Alici numarasi URL'den: ...&files=apply-faxtest.php&to=+49XXXXXXXX
 *  ClickSend'e gonderir, message_id + sonuc dondurur. Sonra dump-faxcheck ile
 *  teslim durumu gorulur. API anahtarlari ASLA basilmaz.
 * KULLANIM: pull2.php?key=...&files=apply-faxtest.php&to=+49...
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@include_once __DIR__.'/config.php';
echo "== apply-faxtest ==\n\n";

$to = trim((string)($_GET['to'] ?? ''));
if($to===''){
  echo "ALICI NUMARA YOK.\n";
  echo "Su sekilde tekrar cagir (numarani ekle):\n";
  echo "  ...pull2.php?key=...&files=apply-faxtest.php&to=+49XXXXXXXXX\n\n";
  echo "  Ornek: &to=+4917612345678  (kendi cep/faks degil — GERCEK bir FAKS numarasi olmali)\n";
  echo "  Faks numaran yoksa: ucretsiz test icin bir fax-to-email servisi kullan.\n";
  exit;
}
/* E.164 normalize */
$e = preg_replace('/[^\d+]/','',$to);
if($e!=='' && $e[0]!=='+'){ if(strpos($e,'00')===0) $e='+'.substr($e,2); elseif($e[0]==='0') $e='+49'.substr($e,1); else $e='+'.$e; }
if(strlen(preg_replace('/\D/','',$e))<7){ echo "Gecersiz numara: $to\n"; exit; }
echo "Alici (E.164): $e\n\n";

$hasKeys = defined('CLICKSEND_USER') && defined('CLICKSEND_KEY') && constant('CLICKSEND_USER') && constant('CLICKSEND_KEY');
if(!$hasKeys){ echo "ClickSend anahtarlari EKSIK — gonderilemez.\n"; exit; }
$auth=constant('CLICKSEND_USER').':'.constant('CLICKSEND_KEY');

/* basit gecerli 1-sayfa PDF (test metni) */
function testpdf(){
  $txt="ChatHelp — TEST-FAX\n\nDies ist ein Testdokument zur Ueberpruefung des Faxversands.\nDatum: ".date('d.m.Y H:i')."\n\nWenn Sie dieses Fax erhalten, funktioniert der Versand korrekt.\n\nMit freundlichen Gruessen\nChatHelp / Acerasoft LLC";
  $lines=explode("\n",$txt);
  $esc=function($s){ return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s); };
  $stream="BT /F1 12 Tf 60 760 Td 18 TL\n";
  foreach($lines as $i=>$ln){ $stream.=($i?"T*\n":"")."(".$esc($ln).") Tj\n"; }
  $stream.="ET";
  $objs=[];
  $objs[1]="<</Type/Catalog /Pages 2 0 R>>";
  $objs[2]="<</Type/Pages /Kids[4 0 R] /Count 1>>";
  $objs[3]="<</Type/Font /Subtype/Type1 /BaseFont/Helvetica /Encoding/WinAnsiEncoding>>";
  $objs[4]="<</Type/Page /Parent 2 0 R /MediaBox[0 0 595 842] /Resources<</Font<</F1 3 0 R>>>> /Contents 5 0 R>>";
  $objs[5]="<</Length ".strlen($stream).">>\nstream\n$stream\nendstream";
  ksort($objs);
  $pdf="%PDF-1.4\n"; $offs=[];
  foreach($objs as $n=>$b){ $offs[$n]=strlen($pdf); $pdf.="$n 0 obj\n$b\nendobj\n"; }
  $xref=strlen($pdf); $max=max(array_keys($objs));
  $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
  for($i=1;$i<=$max;$i++){ $pdf.=str_pad((string)($offs[$i]??0),10,'0',STR_PAD_LEFT)." 00000 n \n"; }
  $pdf.="trailer\n<</Size ".($max+1)." /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
  return $pdf;
}
$pdfBin=testpdf();
echo "Test PDF: ".strlen($pdfBin)." B\n";

/* ClickSend: 1) uploads?convert=fax  2) fax/send */
$up=curl_init('https://rest.clicksend.com/v3/uploads?convert=fax');
curl_setopt_array($up,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_USERPWD=>$auth,
  CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
  CURLOPT_POSTFIELDS=>json_encode(['content'=>base64_encode($pdfBin)]),CURLOPT_TIMEOUT=>90]);
$ur=curl_exec($up); $uc=(int)curl_getinfo($up,CURLINFO_HTTP_CODE); $ue=curl_error($up); curl_close($up);
$uj=json_decode((string)$ur,true);
$fileUrl=''; if(is_array($uj)){ $d=$uj['data']??null; if(is_array($d)) $fileUrl=$d['_url']??($d['url']??''); elseif(is_string($d)) $fileUrl=$d; }
echo "\n[1] upload: HTTP $uc ".($ue?"(hata:$ue)":"")." · file_url: ".($fileUrl?substr($fileUrl,0,60)."…":"YOK")."\n";
if(!$fileUrl){ echo "  ✗ upload basarisiz — yanit: ".substr((string)$ur,0,250)."\n"; exit; }

$payload=['file_url'=>$fileUrl,'messages'=>[['source'=>'chathelp-test','to'=>$e]]];
$sd=curl_init('https://rest.clicksend.com/v3/fax/send');
curl_setopt_array($sd,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_USERPWD=>$auth,
  CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
  CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>90]);
$sr=curl_exec($sd); $sc=(int)curl_getinfo($sd,CURLINFO_HTTP_CODE); $se=curl_error($sd); curl_close($sd);
$sj=json_decode((string)$sr,true);
$rc=($sj['response_code']??''); $mid='';
if(is_array($sj)){ try{ $mid=$sj['data']['messages'][0]['message_id']??$sj['data']['messages'][0]['messageid']??''; }catch(\Throwable $t){} }
echo "\n[2] fax/send: HTTP $sc · response_code: ".($rc?:'?')." · message_id: ".($mid?:'?')."\n";
if($rc==='SUCCESS'){
  echo "\n  ✓✓ FAX KUYRUGA ALINDI! Birkac dakikada iletilir.\n";
  echo "  ClickSend Dashboard > Fax > History'de ARTIK gorunmeli (ucret + durum).\n";
  echo "  Veya: dump-faxcheck.php calistir -> son gonderim + teslim durumu.\n";
} else {
  echo "\n  ✗ Gonderim basarisiz — yanit: ".substr((string)$sr,0,300)."\n";
}
echo "\n== BITTI ==\n";
