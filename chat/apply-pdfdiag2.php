<?php
/**
 * ChatHelp — apply-pdfdiag2 (CH_PDFDIAG2) — App'te PDF HALEN calismiyor. Kor tahmin
 *  yerine GORUNUR teshis: CH_APPPDF (makePDF) + CH_CPDWV (chPrintDoc x3) dallarina,
 *  isWV()/jspdf durumunu gosteren BIR KEZLIK bir uyari (alert) ekler. Boylece App'te
 *  PDF butonuna basinca TAM OLARAK ne oldugunu (dal calisiyor mu, jsPDF var mi, hata
 *  ne) goreceğiz. Sadece teshis icin — davranisi degistirmez, sadece GORUNUR yapar.
 *  Sayac-korumali (>=1) str_replace'ler. Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-pdfdiag2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pdfdiag2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PDFDIAG2')!==false) exit("Zaten ekli (CH_PDFDIAG2).\n");

$fail=[];

/* [1] makePDF (CH_APPPDF) icine teshis */
$o1="var _isWv=false; try{ _isWv=(typeof isWV==='function')&&isWV(); }catch(e0){}\n    if(_isWv && window.jspdf && window.jspdf.jsPDF){";
$n1="var _isWv=false; try{ _isWv=(typeof isWV==='function')&&isWV(); }catch(e0){}\n    try{ if(!window.__chPdfDbg){ window.__chPdfDbg=1; alert('DEBUG makePDF: isWV='+_isWv+' jspdf='+(window.jspdf?1:0)+' jsPDF='+(window.jspdf&&window.jspdf.jsPDF?1:0)); } }catch(edbg){}/*CH_PDFDIAG2*/\n    if(_isWv && window.jspdf && window.jspdf.jsPDF){";
$c1=substr_count($src,$o1);
if($c1>=1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] makePDF teshis eklendi ($c1)\n"; }
else { echo "  ✗ [1] anchor ($c1)\n"; $fail[]=1; }

/* [2] chPrintDoc (CH_CPDWV, 3 kopya) icine teshis */
$o2="var _isWv2=false; try{ _isWv2=(typeof isWV==='function')&&isWV(); }catch(e0){}\n          if(_isWv2 && window.jspdf && window.jspdf.jsPDF){";
$n2="var _isWv2=false; try{ _isWv2=(typeof isWV==='function')&&isWV(); }catch(e0){}\n          try{ if(!window.__chPdfDbg2){ window.__chPdfDbg2=1; alert('DEBUG chPrintDoc: isWV='+_isWv2+' jspdf='+(window.jspdf?1:0)+' jsPDF='+(window.jspdf&&window.jspdf.jsPDF?1:0)); } }catch(edbg2){}/*CH_PDFDIAG2*/\n          if(_isWv2 && window.jspdf && window.jspdf.jsPDF){";
$c2=substr_count($src,$o2);
if($c2>=1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] chPrintDoc teshis eklendi ($c2 kopya)\n"; }
else { echo "  ✗ [2] anchor ($c2)\n"; $fail[]=2; }

if($fail){ echo "\nHATA: eslesmeyen adim(lar): ".implode(', ',$fail)." — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'pd').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-pdfdiag2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PDFDIAG2')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PDFDIAG2 diskte (".strlen($chk)." B)\n";
echo "  Test: App'i TAM kapat-ac (2 kez), 'Als PDF speichern'e bas -> bir DEBUG uyarisi\n";
echo "  cikacak (isWV=.. jspdf=.. jsPDF=..). O yaziyi bana oldugu gibi gonder.\n";
