<?php
/* dump-sidebar (READ-ONLY) — sidebar (#sb) nav ogeleri (label/onclick/id) +
   Google Play/App/store referanslari + nav-verlauf baglami. iPhone butonunu
   premium menuye (Google Store altina) koymak icin.
   KULLANIM: pull2.php?key=...&files=dump-sidebar.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "dump-sidebar (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php'); if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [1] sidebar (#sb) statik nav ogeleri ===\n";
$ps=strpos($s,'id="sb"'); 
if($ps!==false){ $pe=strpos($s,'id="mainarea"',$ps); if($pe===false||$pe-$ps>14000)$pe=$ps+14000; $seg=substr($s,$ps,$pe-$ps);
  if(preg_match_all('/<(div|a|button)[^>]*class="[^"]*sb-nav-item[^"]*"[^>]*>.*?<\/\1>/is',$seg,$m,PREG_SET_ORDER)){
    $i=0; foreach($m as $x){ if($i++>=25)break; echo "  ".trim(preg_replace('/\s+/',' ',substr($x[0],0,220)))."\n"; }
  } else echo "  (statik sb-nav-item yok — JS ile mi ekleniyor?)\n";
}
echo "\n=== [2] app/store/Play referanslari (sidebar/menu) ===\n";
foreach(['play.google','App laden','Android','iPhone','App Store','App holen','sb-app','qr','downloadApp','nav-app'] as $k){
  $off=0;$c=0; while(($p=stripos($s,$k,$off))!==false && $c<2){ echo "  '".$k."' [".$p."] …".W($s,$p,50,70)."…\n"; $off=$p+strlen($k);$c++; }
}
echo "\n=== [3] nav-verlauf + komsu ogeler (JS ekleme) ===\n";
$p=strpos($s,'nav-verlauf'); if($p!==false){ echo "  ".W($s,$p,120,260)."\n"; }
echo "\n=== [4] sidebar JS nav ekleyen fonksiyonlar (sb-nav-item olusturanlar) ===\n";
$off=0;$c=0; while(($p=strpos($s,'sb-nav-item',$off))!==false && $c<12){ echo "  [".$p."] ".W($s,$p,60,50)."\n"; $off=$p+10;$c++; }
echo "\nBITTI.\n";
