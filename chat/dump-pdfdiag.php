<?php
/**
 * ChatHelp — dump-pdfdiag — ACIL PDF yolu butunluk teshisi (OKUR).
 *  Son yamalar (CH_SENDOK/CH_HISTPDF) PDF fonksiyonlarini bozmus mu?
 * KULLANIM: pull2.php?key=...&files=dump-pdfdiag.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
function c($src,$s){ return substr_count($src,$s); }
echo "== dump-pdfdiag (".strlen($src)." B) ==\n\n";

echo "=== PDF cekirdek fonksiyon sayilari (1 olmali) ===\n";
foreach(['function makePDF','chPrintDoc=function','function proceed','function doFree','function reallySend','function showResult','function renderDocs'] as $fn){
  $n=c($src,$fn); echo "  ".($n>=1?'✓':'✗')." $fn = $n\n";
}
echo "  chPrintDoc( cagri: ".c($src,'chPrintDoc(')."\n";
echo "  makePDF( cagri: ".c($src,'makePDF(')."\n";
echo "  __addr4 wrapper: ".c($src,'__addr4')."\n";

echo "\n=== showResult PDF butonu (makePDF cagriyor mu) ===\n";
echo "  'makePDF(doc,d.n,CC)' : ".c($src,'makePDF(doc,d.n,CC)')." (>=1 olmali)\n";
echo "  'if(_tk3&&_up3' eski kapi : ".c($src,"if(_tk3&&_up3")."\n";
echo "  '{makePDF(doc,d.n,CC);return;}/*CH_SENDGATE' : ".c($src,'CH_SENDGATE-modal-acilir')."\n";

echo "\n=== CH_SENDOK butunluk ===\n";
echo "  CH_SENDOK : ".c($src,'CH_SENDOK')."\n";
echo "  chSendLoading tanim : ".c($src,'window.chSendLoading=function')." (1 olmali)\n";
echo "  chSendSuccess tanim : ".c($src,'window.chSendSuccess=function')." (1 olmali)\n";
echo "  chSendLoading( cagri : ".c($src,'chSendLoading(')."\n";
/* bozuk cift-insert kontrol */
echo "  bozuk cift-loader : ".c($src,'chSendLoading(false);}catch(e){} try{window.chSendLoading&&window.chSendLoading(false)')." (0 olmali)\n";
echo "  '=false;=false' bozuk : ".c($src,'disabled=false;=')." (0 olmali)\n";

echo "\n=== CH_HISTPDF (th-pdf) butunluk ===\n";
echo "  CH_HISTPDF : ".c($src,'CH_HISTPDF')."\n";
echo "  th-pdf makePDF : ".c($src,'window.makePDF(tx,CUR.title,CCX())')."\n";
echo "  CH_HISTPDF_B guard : ".c($src,'CH_HISTPDF_B')."\n";

echo "\n=== overlay z-index cakisma (loader ekranda takili mi riski) ===\n";
echo "  '#ch-sld-ov' : ".c($src,'ch-sld-ov')."\n";
echo "  '#ch-sok-ov' : ".c($src,'ch-sok-ov')."\n";
echo "  z-index:100000 : ".c($src,'z-index:100000')."\n";

echo "\n== BITTI ==\n";
