<?php
/* ChatHelp — pvmail.php (CH_PVMAIL) — belgeyi PDF yapip e-posta EKI olarak yollar. */
error_reporting(E_ERROR|E_PARSE);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
@require_once __DIR__.'/pdflib.php';
function pvm_out($ok,$msg=''){ echo json_encode(['ok'=>(bool)$ok,'msg'=>$msg]); exit; }

$to=trim((string)($_POST['to'] ?? ''));
if($to===''||!filter_var($to,FILTER_VALIDATE_EMAIL)) pvm_out(false,'invalid_email');

$txt=(string)($_POST['text'] ?? '');
if(trim($txt)===''&&isset($_POST['html'])&&function_exists('ch_html_to_text')) $txt=ch_html_to_text((string)$_POST['html']);
if(trim($txt)==='') pvm_out(false,'empty');
if(strlen($txt)>400000) $txt=substr($txt,0,400000);

$title=preg_replace('/[^\p{L}\p{N} .,\-]/u','',(string)($_POST['title'] ?? 'ChatHelp-Dokument'));
if(trim($title)==='') $title='ChatHelp-Dokument';
$fname=preg_replace('/[^A-Za-z0-9._\- ]/','',$title); if(trim($fname)==='') $fname='ChatHelp-Dokument';

$pdf='';
$fr=__DIR__.'/fonts/dvs.ttf'; $fb=__DIR__.'/fonts/dvsb.ttf';
if(is_file($fr)&&is_file($fb)&&function_exists('ch_premium_pdf_uni')){
  $pdf=ch_premium_pdf_uni($txt,(string)file_get_contents($fr),(string)file_get_contents($fb));
}elseif(function_exists('ch_premium_pdf')){
  $pdf=ch_premium_pdf($txt);
}
if(strlen($pdf)<200) pvm_out(false,'pdf_failed');

try{ $b='chb_'.bin2hex(random_bytes(8)); }catch(Exception $e){ $b='chb_'.md5(uniqid('',true)); }
$from='noreply@chat-help.com';
$subject='=?UTF-8?B?'.base64_encode('ChatHelp — '.$title).'?=';
$hdr ="From: ChatHelp <$from>\r\nReply-To: $from\r\nMIME-Version: 1.0\r\n";
$hdr.="Content-Type: multipart/mixed; boundary=\"$b\"\r\n";
$body ="--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
$body.="Ihr Dokument ist als PDF angehängt.\r\nBelgeniz PDF olarak ektedir.\r\n\r\n— ChatHelp\r\n\r\n";
$body.="--$b\r\nContent-Type: application/pdf; name=\"$fname.pdf\"\r\n";
$body.="Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$fname.pdf\"\r\n\r\n";
$body.=chunk_split(base64_encode($pdf))."\r\n--$b--";

pvm_out(@mail($to,$subject,$body,$hdr) ? true : false, 'mail');