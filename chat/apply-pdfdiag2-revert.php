<?php
/**
 * ChatHelp — apply-pdfdiag2-revert (CH_PDFDIAG2_REVERT) — ACIL: 'Dokument generieren'
 *  dahil hicbir buton tiklanmiyor. Supheli: CH_PDFDIAG2'nin eklendigi alert() teshis
 *  kodu (bloklayici/JS akisini bozan). Bu script SADECE o teshis alert()'lerini
 *  KALDIRIR — CH_APPPDF/CH_CPDWV kalici PDF duzeltmeleri AYNEN kalir, sadece
 *  gecici DEBUG uyarilari gider.
 *  2 sayac-korumali str_replace (>=1 kabul, cok kopya olabilir). Eslesmezse
 *  HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-pdfdiag2-revert.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pdfdiag2-revert BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PDFDIAG2')===false) exit("CH_PDFDIAG2 zaten yok (belki daha once geri alindi).\n");

$fail=[];

/* [1] makePDF teshis kaldir */
$n1="var _isWv=false; try{ _isWv=(typeof isWV==='function')&&isWV(); }catch(e0){}\n    try{ if(!window.__chPdfDbg){ window.__chPdfDbg=1; alert('DEBUG makePDF: isWV='+_isWv+' jspdf='+(window.jspdf?1:0)+' jsPDF='+(window.jspdf&&window.jspdf.jsPDF?1:0)); } }catch(edbg){}/*CH_PDFDIAG2*/\n    if(_isWv && window.jspdf && window.jspdf.jsPDF){";
$o1="var _isWv=false; try{ _isWv=(typeof isWV==='function')&&isWV(); }catch(e0){}\n    if(_isWv && window.jspdf && window.jspdf.jsPDF){";
$c1=substr_count($src,$n1);
if($c1>=1){ $src=str_replace($n1,$o1,$src); echo "  ✓ [1] makePDF DEBUG alert kaldirildi ($c1)\n"; }
else { echo "  = [1] bulunamadi ($c1) — zaten temiz olabilir\n"; }

/* [2] chPrintDoc teshis kaldir (x3) */
$n2="var _isWv2=false; try{ _isWv2=(typeof isWV==='function')&&isWV(); }catch(e0){}\n          try{ if(!window.__chPdfDbg2){ window.__chPdfDbg2=1; alert('DEBUG chPrintDoc: isWV='+_isWv2+' jspdf='+(window.jspdf?1:0)+' jsPDF='+(window.jspdf&&window.jspdf.jsPDF?1:0)); } }catch(edbg2){}/*CH_PDFDIAG2*/\n          if(_isWv2 && window.jspdf && window.jspdf.jsPDF){";
$o2="var _isWv2=false; try{ _isWv2=(typeof isWV==='function')&&isWV(); }catch(e0){}\n          if(_isWv2 && window.jspdf && window.jspdf.jsPDF){";
$c2=substr_count($src,$n2);
if($c2>=1){ $src=str_replace($n2,$o2,$src); echo "  ✓ [2] chPrintDoc DEBUG alert kaldirildi ($c2 kopya)\n"; }
else { echo "  = [2] bulunamadi ($c2) — zaten temiz olabilir\n"; }

if($c1<1 && $c2<1){ echo "\nHATA: hicbir DEBUG kalintisi bulunamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'pr').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-pdfdiag2revert-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  ✓ DEBUG alert'ler kaldirildi (".strlen($chk)." B). CH_APPPDF/CH_CPDWV kalici duzeltmeler KALDI.\n";
echo "  Test: App'i tam kapat-ac -> 'Dokument generieren' tiklaniyor mu?\n";
