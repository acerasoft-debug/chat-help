<?php
/* dump-app-kuend (READ-ONLY) — (A) Google Play rozeti/app-install alani nerede;
   (B) master doc registry + showCatDocs doc-acma akisi + CAT_DOCS['vertraege'] icerigi.
   KULLANIM: pull2.php?key=...&files=dump-app-kuend.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-app-kuend (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
function w($s,$p,$b,$a){ return trim(preg_replace('/[ \t]+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [A] Google Play / app-install alani ===\n";
foreach(['play.google.com','Google Play','Im App Store','App laden','sb-app','ch-qr','app-badge','Zum App','downloadApp','install'] as $k){
  $off=0;$c=0; while(($p=stripos($s,$k,$off))!==false && $c<3){ echo "  '".$k."' [".$p."] …".w($s,$p,60,90)."…\n"; $off=$p+strlen($k);$c++; }
}

echo "\n=== [B1] showCatDocs govdesi (doc listeleme + tik->acma) ===\n";
$p=strpos($s,'function showCatDocs');
if($p!==false){ echo w($s,$p,0,900)."\n"; }

echo "\n=== [B2] doc-acma fonksiyonu (openDoc/startDoc/runDoc/pickDoc) ===\n";
foreach(['openDoc','startDoc','runDoc','pickDoc','function openDok','doc.k','onclick=\"pick','selectDoc'] as $k){
  $p=strpos($s,$k); if($p!==false) echo "  '".$k."' [".$p."] ".w($s,$p,10,140)."\n";
}

echo "\n=== [B3] master doc kaydi: at_fitness_kuendigung hangi obje icinde? ===\n";
$p=strpos($s,'at_fitness_kuendigung:{');
if($p!==false){
  /* geriye dogru en yakin 'var XXX={' ya da 'XXX={' obje basi */
  $pre=substr($s,max(0,$p-4000),4000);
  if(preg_match_all('/(?:var\s+)?([A-Z_][A-Z0-9_]{2,})\s*=\s*\{/',$pre,$mm)){
    $last=end($mm[1]); echo "  en yakin obje: ".$last."\n";
  }
  echo "  civar: …".w($s,$p,80,40)."…\n";
}

echo "\n=== [B4] CAT_DOCS['vertraege'] baslangic tanimi (11 dokuman?) ===\n";
$p=strpos($s,"vertraege:[");
if($p===false)$p=strpos($s,"'vertraege':[");
if($p!==false){ echo "  [".$p."] ".w($s,$p,0,600)."\n"; }
else {
  echo "  (statik vertraege:[...] yok — runtime merge). CAT_DOCS ilk tanim:\n";
  $q=strpos($s,'CAT_DOCS='); if($q===false)$q=strpos($s,'CAT_DOCS ={'); if($q===false)$q=strpos($s,'var CAT_DOCS');
  if($q!==false) echo "  [".$q."] ".w($s,$q,0,700)."\n";
}

echo "\n=== [B5] NEWCATS (kategori ekleme) tanimi ===\n";
$p=strpos($s,'NEWCATS='); if($p===false)$p=strpos($s,'NEWCATS ='); if($p===false)$p=strpos($s,'var NEWCATS');
if($p!==false) echo "  [".$p."] ".w($s,$p,0,400)."\n";

echo "\nBITTI.\n";
