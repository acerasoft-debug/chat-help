<?php
/**
 * ChatHelp — dump-welcome3 — WELCOME mesaji NEREDE ekleniyor + acilis gorunumu (home/chat)
 *  + hero fonksiyon blogu (wlc-) gorunurlugu. Karsilamayi kaldirinca fonksiyonlar cikacak mi?
 * KULLANIM: pull2.php?key=...&files=dump-welcome3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-welcome3 (".number_format(strlen($src))." B) ==\n\n";

/* WELCOME kullanildigi yerler (tanim disinda) */
echo "=== [A] WELCOME[..] kullanim yerleri (mesaj ekleme) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'WELCOME',$off))!==false && $n<8){
  echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-90),200))."...\n";
  $off=$p+7; $n++;
}

/* gP( = go-page/view switch */
echo "\n=== [B] gP( görünüm fonksiyonu tanimi + ilk cagri ===\n";
$p=strpos($src,'function gP');
if($p!==false) echo "  tanim @$p: ".str_replace(["\n","\r"],' ',substr($src,$p,300))."\n";
$off=0;$n=0;
while(($p=strpos($src,"gP('",$off))!==false && $n<8){
  echo "  gP call @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-50),90))."...\n";
  $off=$p+4; $n++;
}

/* restoreConv / init: acilista ne gosteriyor */
echo "\n=== [C] restoreConv / acilis init ===\n";
$p=strpos($src,'function restoreConv');
if($p!==false) echo "  restoreConv @$p: ".str_replace(["\n","\r"],' ',substr($src,$p,600))."\n";

/* hero (wlc-quick) — home fonksiyon kartlari */
echo "\n=== [D] hero/home fonksiyon blogu (wlc-quick/wlc-cats) ===\n";
foreach(['wlc-quick','wlc-cats','wlc-hero','class="wlc','id="mi"','id="home"'] as $pat){
  $p=strpos($src,$pat);
  echo "  '$pat': ".($p===false?'yok':"@$p")."\n";
}
/* mi = mesaj alani; hero mi'nin icinde mi disinda mi */
$p=strpos($src,'wlc-quick');
if($p!==false) echo "\n  wlc-quick baglam: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-160),340))."...\n";

echo "\n== BITTI ==\n";
