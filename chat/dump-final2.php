<?php
/* dump-final (READ-ONLY) — (A) CH_QR widget TAM (Apple rozeti eklemek icin);
   (B) CAT_DOCS['vertraege'] 11-liste TAM + master def var mi + doc-acma akisi.
   KULLANIM: pull2.php?key=...&files=dump-final.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-final (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
function w($s,$p,$b,$a){ return trim(preg_replace('/[ \t]+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

/* A) CH_QR widget: css + html + js sinirlari */
echo "=== [A1] #ch-qr-wrap HTML ===\n";
$p=strpos($s,'id="ch-qr-wrap"');
if($p!==false){ $ds=strrpos(substr($s,0,$p),'<div'); $de=strpos($s,'id="ch-qr-css"',$p);
  /* wrap div'ini kabaca al: wrap basindan ch-qr-css style'a kadar */
  echo substr($s,$ds, min(1400,($de!==false?$de-$ds:1400)))."\n"; }
echo "\n=== [A2] #ch-qr-css TAM ===\n";
$p=strpos($s,'id="ch-qr-css"');
if($p!==false){ $e=strpos($s,'</style>',$p); echo substr($s,$p,min(1800,$e-$p))."\n"; }
echo "\n=== [A3] CH_QR JS pill toggle + TT ===\n";
$p=strpos($s,'QR scannen'); if($p!==false) echo "  ".w($s,$p,400,200)."\n";

/* B) Verträge */
echo "\n\n=== [B1] CAT_DOCS['vertraege'] 11-liste TAM ===\n";
$p=strpos($s,"'vertraege':[");
if($p!==false){ $e=strpos($s,']',$p); echo substr($s,$p,$e-$p+1)."\n"; }

echo "\n=== [B2] 11 key master def var mi? (key:{ sayimi) ===\n";
foreach(['kuendigung_handy','kuendigung_internet','kuendigung_strom','kuendigung_fitness','kuendigung_streaming','kuendigung_versicherung','kuendigung_zeitung','kuendigung_bank','kuendigung_miete','kuendigung_arbeit','kuendigung_mietvertrag','kuendigung_verein','kuendigung_mitglied'] as $k){
  $def=preg_match_all('/'.preg_quote($k,'/').'\s*:\s*\{ic:/',$s);
  $ref=substr_count($s,'"'.$k.'"');
  echo "  ".$k.": def=".$def." ref=".$ref."\n";
}

echo "\n=== [B3] showCatDocs doc-satiri onclick (nasil aciliyor) ===\n";
$p=strpos($s,'function showCatDocs');
if($p!==false){ $blk=substr($s,$p,2600);
  /* onclick / genDoc / openDoc iceren satirlari goster */
  foreach(['onclick','genDoc','openDoc','startDoc','pickCat','d.k','doc.k','runIntake','askDoc'] as $k){
    $q=strpos($blk,$k); if($q!==false) echo "  ~".$k.": ".trim(preg_replace('/\s+/',' ',substr($blk,max(0,$q-30),120)))."\n";
  }
}

echo "\n=== [B4] master registry: at_fitness_kuendigung ENCLOSING obje adi ===\n";
$p=strpos($s,'at_fitness_kuendigung:{');
if($p!==false){ $pre=substr($s,max(0,$p-6000),6000);
  if(preg_match_all('/([A-Za-z_][A-Za-z0-9_]{2,})\s*=\s*\{/',$pre,$mm)){ echo "  aday objeler (son 5): ".implode(', ',array_slice($mm[1],-5))."\n"; }
  /* Pq tanimi? */
  echo "  Pq tanimli: ".(strpos($s,'function Pq')!==false?'EVET':'?')."\n";
}
echo "\n=== [B5] genDoc key kullanimi (registry lookup) ===\n";
$p=strpos($s,'function genDoc'); if($p===false)$p=strpos($s,'genDoc=function');
if($p!==false) echo "  ".w($s,$p,0,300)."\n";
/* doc questions lookup: nasil q bulunuyor */
$p=strpos($s,'.q'); 
echo "\nBITTI.\n";
