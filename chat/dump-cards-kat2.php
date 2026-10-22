<?php
/**
 * ChatHelp — dump-cards-kat2 (SADECE OKUR) — kesin duzeltme icin 2 mekanizma:
 *  [A] showCatDocs govdesi + CAT_DOCS'a vtr_* iten merge dongusu (kategori
 *      kirliligi kok neden)
 *  [B] ana sayfa kartlarini/karsilamayi cizen fonksiyon + tetikleme sikligi
 *      (titreme kok neden)
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-cards-kat2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-cards-kat2 — SADECE OKUR ===\n\n";

echo "───── [A] showCatDocs + merge ─────\n\n";
$p=strpos($src,'function showCatDocs');
if($p!==false) echo "[A1] showCatDocs govde (1200 kr):\n".substr($src,$p,1200)."\n\n";
else echo "[A1] (showCatDocs yok)\n\n";

/* vtr_* -> CAT_DOCS/kategori iten dongu(ler) */
echo "[A2] '.docs.push' / 'CAT_DOCS[' yakinlari:\n";
foreach(['CAT_DOCS[c','CAT_DOCS[cat','.docs.push','docs.indexOf','for(var k in V)','for(var k2 in','Object.keys(V)'] as $needle){
  $off=0;$n=0;
  while(($q=strpos($src,$needle,$off))!==false && $n<3){
    $seg=substr($src,max(0,$q-80),260); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  [$needle] …".$seg."…\n"; $off=$q+strlen($needle);$n++;
  }
}
echo "\n";

/* V objesi nasil render ediliyor — Vertrag Studio mu, ana kategori mi */
echo "[A3] 'var V={' baglami (V nerede kullaniliyor):\n";
$p=strpos($src,'var V={ vtr_');
if($p===false) $p=strpos($src,'var V={');
if($p!==false){ echo "  tanim @".$p."\n"; }
$off=0;$n=0;
while(($q=strpos($src,'V[',$off))!==false && $n<8){
  $seg=substr($src,max(0,$q-60),140); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  V[ …".$seg."…\n"; $off=$q+2;$n++;
}
echo "\n";

echo "───── [B] Kart cizimi + titreme ─────\n\n";
echo "[B1] 'wcat-grid' uretimi (innerHTML/insertAdjacent ile cizen yer):\n";
foreach(['wcat-grid">','class=\\"wcat-grid','renderWelcome','buildWelcome','function welcome','showWelcome','wlc-h1'] as $needle){
  $off=0;$n=0;
  while(($q=strpos($src,$needle,$off))!==false && $n<2){
    $seg=substr($src,max(0,$q-70),200); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  [$needle] …".$seg."…\n"; $off=$q+strlen($needle);$n++;
  }
}
echo "\n";
echo "[B2] MutationObserver sayisi: ".substr_count($src,'MutationObserver')."\n";
echo "[B3] setInterval sayisi: ".substr_count($src,'setInterval')."\n";
echo "[B4] 'observe(document.body' sayisi: ".substr_count($src,'observe(document.body')."\n\n";

echo "[B5] addPills/addFav/addDiscover cagrilma dongusu (pill ekleyip reflow yapan):\n";
foreach(['function addPills','function addFav','function addDiscover','addPills();','addFav();'] as $needle){
  $q=strpos($src,$needle);
  if($q!==false){ $seg=substr($src,$q,180); $seg=preg_replace('/\s+/',' ',$seg); echo "  [$needle] …".$seg."…\n"; }
}

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
