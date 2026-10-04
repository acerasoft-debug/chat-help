<?php
/**
 * ChatHelp — apply-dlpdf (CH_DL2) — dl.php yukseltmesi: HTML dosyasi yerine
 *  GERCEK, TEMIZ PDF indirme. postversand.php'deki kanitlanmis saf-PHP PDF
 *  uretici (Helvetica, A4, cok sayfa, WinAnsi+TRANSLIT) dl.php'ye tasindi:
 *  POST html+name -> HTML metne cevrilir -> gercek .pdf olarak iner.
 *  App + mobil tarayici ayni sayfada kalir, dosya dogrudan indirilir.
 *  fmt=html gonderilirse eski (HTML indirme) davranis korunur; PDF uretimi
 *  bosa dusarse otomatik HTML'e geri doner. Eski dl.php yedeklenir.
 * KULLANIM: pull2.php?key=...&files=apply-dlpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-dlpdf BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$target=__DIR__.'/dl.php';

$new = <<<'DLPHP'
<?php
/* ChatHelp — dl.php v2 (CH_DL2) — POST html+name -> GERCEK PDF indirme (ayni sayfada).
   fmt=html => eski HTML-indirme davranisi. PDF uretilemezse HTML'e geri doner. */
error_reporting(E_ERROR | E_PARSE);

function ch_html_to_text($h){
  $h=preg_replace('#<script\b[^>]*>.*?</script>#is',' ',$h);
  $h=preg_replace('#<style\b[^>]*>.*?</style>#is',' ',$h);
  $h=preg_replace('#<head\b[^>]*>.*?</head>#is',' ',$h);
  $h=preg_replace('#<br\s*/?\s*>#i',"\n",$h);
  $h=preg_replace('#</(p|div|h1|h2|h3|h4|h5|h6|tr|table|section|article|header|footer|ul|ol|blockquote|li)>#i',"\n",$h);
  $h=preg_replace('#<(h1|h2|h3)\b[^>]*>#i',"\n",$h);
  $h=preg_replace('#<li\b[^>]*>#i',"\n- ",$h);
  $h=preg_replace('#<hr\s*/?\s*>#i',"\n--------------------------------\n",$h);
  $h=preg_replace('#<td\b[^>]*>#i','  ',$h);
  $h=strip_tags($h);
  $h=html_entity_decode($h,ENT_QUOTES|ENT_HTML5,'UTF-8');
  $h=str_replace("\r",'',$h);
  $h=preg_replace('/[ \t]+\n/',"\n",$h);
  $h=preg_replace('/\n{3,}/',"\n\n",$h);
  $h=preg_replace('/[ \t]{2,}/',' ',$h);
  return trim($h);
}

/* saf-PHP gecerli PDF (Helvetica, A4, cok sayfa) — postversand.php ch_text_pdf ile ayni cekirdek */
function ch_text_pdf(string $text): string {
  $t = @iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text); if($t===false) $t=$text;
  $lines=[]; foreach(explode("\n",str_replace("\r",'',$t)) as $ln){
    if($ln===''){ $lines[]=''; continue; }
    while(strlen($ln)>92){ $cut=strrpos(substr($ln,0,92),' '); if($cut===false||$cut<40)$cut=92; $lines[]=substr($ln,0,$cut); $ln=ltrim(substr($ln,$cut)); }
    $lines[]=$ln;
  }
  $perPage=48; $pages=array_chunk($lines,$perPage); if(!$pages)$pages=[['']];
  $objs=[]; $objs[1]='';
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

$html=(string)($_POST['html'] ?? '');
if($html==='' && isset($_POST['text'])) $html=(string)$_POST['text'];
if($html===''){ http_response_code(400); header('Content-Type: text/plain'); exit('no_content'); }
if(strlen($html) > 6*1024*1024){ http_response_code(413); header('Content-Type: text/plain'); exit('too_big'); }

$name=(string)($_POST['name'] ?? 'ChatHelp-Dokument');
$name=preg_replace('/[^A-Za-z0-9._\- ]/','',basename($name)); if($name==='') $name='ChatHelp-Dokument';
$base=preg_replace('/\.(html?|pdf)$/i','',$name); if($base==='') $base='ChatHelp-Dokument';

$fmt=strtolower((string)($_POST['fmt'] ?? ($_GET['fmt'] ?? 'pdf')));
if($fmt!=='html'){
  $txt=ch_html_to_text($html);
  if($txt!==''){
    $pdf=ch_text_pdf($txt);
    if(strlen($pdf)>200){
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="'.$base.'.pdf"');
      header('Content-Length: '.strlen($pdf));
      header('Cache-Control: no-store');
      echo $pdf; exit;
    }
  }
}
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$base.'.html"');
header('Cache-Control: no-store');
echo $html;
DLPHP;

$tmp=tempnam(sys_get_temp_dir(),'dl2').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — dl.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

$old=@file_get_contents($target);
if($old!==false) @file_put_contents($target.'.bak-dl2-'.date('Ymd-His'),$old);
$w=@file_put_contents($target,$new);
if($w===false||$w<strlen($new)) exit("✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($target);
if(strpos($chk,'CH_DL2')===false) exit("✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ dl.php v2 diskte (".strlen($chk)." B)".($old!==false?" — eski surum yedeklendi":" — YENI olusturuldu")."\n";
echo "  ETKI: App + mobil tarayicida 'PDF' artik GERCEK .pdf dosyasi indirir\n";
echo "  (temiz A4, cok sayfali). Sayfa degismez. PDF uretilemezse HTML'e doner.\n";
