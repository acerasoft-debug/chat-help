<?php
/** ChatHelp — dump-pdfcall (SADECE OKUR) — dilekce PDF'i tam olarak neyi
 *  cagiriyor? makePDF tanimi (global mi IIFE-yerel mi) + kategori/chat dilekce
 *  PDF dugmesi cagri sekli (bare makePDF vs window.makePDF vs chPrintDoc). */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$s=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-pdfcall — SADECE OKUR ===\n\n";
/* makePDF tanimi ve cevresi: window.makePDF= var mi? */
echo "[1] makePDF tanim sekli:\n";
echo "  'function makePDF' : ".substr_count($s,'function makePDF')."\n";
echo "  'window.makePDF='  : ".substr_count($s,'window.makePDF=')."\n";
echo "  'window.makePDF ='  : ".substr_count($s,'window.makePDF =')."\n";
$p=strpos($s,'function makePDF');
if($p!==false){
  /* tanimdan 400 kr geri: hangi scope? (IIFE mi top-level mi) */
  echo "\n  tanim oncesi 300 kr (scope ipucu):\n  ".preg_replace('/\s+/',' ',substr($s,max(0,$p-300),300))."\n";
  /* makePDF chPrintDoc cagiriyor mu? tanim govdesinde ara */
  $body=substr($s,$p,3000);
  echo "\n  makePDF govdesinde chPrintDoc: ".(strpos($body,'chPrintDoc')!==false?'VAR ✓ (ortak cikis)':'YOK')."\n";
}
echo "\n[2] kategori dilekce PDF dugmesi (dv-pdf / showResult):\n";
foreach(['dv-pdf','showResult','lastDoc','function dlDoc','vd-allpdf','exportFormPDF'] as $n){
  $q=strpos($s,$n);
  if($q!==false){ echo "  [$n @$q]: ".preg_replace('/\s+/',' ',substr($s,max(0,$q-30),200))."\n"; }
  else echo "  [$n]: (yok)\n";
}
echo "\n[3] bare 'makePDF(' vs 'window.makePDF(' cagrilari:\n";
echo "  bare makePDF(   : ".substr_count($s,'makePDF(')." (toplam)\n";
echo "  window.makePDF( : ".substr_count($s,'window.makePDF(')."\n";
/* dv-pdf onclick tam govde */
$p=strpos($s,'dv-pdf');
if($p!==false){ $q=strpos($s,'onclick',$p); if($q!==false&&$q-$p<400) echo "\n  dv-pdf onclick: ".preg_replace('/\s+/',' ',substr($s,$q,240))."\n"; else echo "\n  dv-pdf cevre: ".preg_replace('/\s+/',' ',substr($s,$p,240))."\n"; }
echo "\n[4] CH_ADRES_IMZA sarma tanimi (window.makePDF mi bare mi hedefliyor):\n";
$p=strpos($s,'CH_ADRES_IMZA — makePDF');
if($p!==false){ echo "  ".preg_replace('/\s+/',' ',substr($s,$p+2000,400))."\n"; }
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
