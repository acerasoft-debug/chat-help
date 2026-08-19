<?php
/**
 * ChatHelp — dump-hist2 — geriye-donuste-PDF kok neden (OKUR).
 *  (1) chPrintDoc( CAGRI yerleri: gold PDF butonu html'i nereden aliyor?
 *  (2) '.racts' BAR'inin olusturuldugu gercek yer (class="racts")
 *  (3) gecmis geri-yukleme: openTopic / loadHistory / renderDoc / addMsg('ai',...doc)
 *  (4) belgenin saklanma bicimi (data-html / lastDocHtml / window.__doc)
 * KULLANIM: pull2.php?key=...&files=dump-hist2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{40,})/','***MASKED***',$s); }
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-hist2 (".strlen($src)." B) ==\n\n";

/* (1) chPrintDoc( cagri yerleri — html argumani ne? */
echo "=== (1) chPrintDoc( CAGRI yerleri (±140 before, 80 after) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'chPrintDoc(',$off))!==false && $n<8){
  echo "  [$n] @$p ...".mask(substr($src,max(0,$p-140),240))."...\n\n";
  $off=$p+11;$n++;
}
echo "  toplam chPrintDoc( cagri: ".substr_count($src,'chPrintDoc(')."\n\n";

/* (2) class="racts" gercek olusturma */
echo "=== (2) 'racts' bar olusturma (class=racts, ±60 before, 400 after) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'racts',$off))!==false && $n<40){
  $seg=substr($src,max(0,$p-14),8);
  if(stripos($seg,'cont')!==false){ $off=$p+5; continue; } /* 'contracts' atla */
  echo "  [$n] @$p ...".mask(substr($src,max(0,$p-60),420))."...\n\n";
  $off=$p+5;$n++; if($n>=4) break;
}

/* (3) gecmis geri-yukleme fonksiyonlari */
echo "=== (3) openTopic / renderDoc tanim civari (±0..500) ===\n";
foreach(['function openTopic','openTopic=','function renderDoc','renderDoc=','function loadHistory','loadHistory='] as $kw){
  $p=strpos($src,$kw);
  if($p!==false){ echo "  --[$kw @$p]--\n  ".mask(substr($src,$p,500))."\n\n"; }
}

/* (4) belge html saklama */
echo "=== (4) belge-html saklama degiskenleri ===\n";
foreach(['lastDocHtml','__doc','data-html','dataset.html','_docHtml','currentDoc','window.chDoc','ch_lastdoc','finalHtml'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n== BITTI ==\n";
