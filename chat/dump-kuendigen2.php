<?php
/* dump-kuendigen2 (READ-ONLY) — Alman(default) 'Verträge kündigen' kart→cat
   eslemesi; generic vertraege/kuendigungen cat defs + docs; de_ prefiks; X_ORDER;
   kartlari ureten fonksiyon. Kündigen duzeltmesi icin kesin harita.
   KULLANIM: pull2.php?key=...&files=dump-kuendigen2.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-kuendigen2 (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
function w($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== literal 'Verträge kündigen' kart etiketi ===\n";
$off=0;$c=0; while(($p=strpos($s,'Verträge kündigen',$off))!==false && $c<8){ echo "  [".$p."] …".w($s,$p,120,120)."…\n\n"; $off=$p+5;$c++; }
if($c===0) echo "  (literal yok — kart etiketi baska)\n";

echo "\n=== generic/de kategori tanimlari (n:) ===\n";
foreach(['kuendigungen:{','vertraege:{','de_kuendigungen:{','de_vertraege:{','kuendigung:{'] as $k){
  $off=0;$c=0; while(($p=strpos($s,$k,$off))!==false && $c<3){ echo "  ".$k." [".$p."] …".w($s,$p,10,90)."…\n"; $off=$p+strlen($k);$c++; }
}

echo "\n=== X_ORDER dizileri (kategori sirasi/ulke) ===\n";
$off=0;$c=0; while(($p=strpos($s,'X_ORDER',$off))!==false && $c<8){ echo "  [".$p."] ".w($s,$p,10,150)."\n\n"; $off=$p+6;$c++; }

echo "\n=== cat:'kuendigungen' (generic) docs — key→isim ===\n";
if(preg_match_all('/(\w+):\{ic:"[^"]*",n:"([^"]{0,50})"[^}]{0,120}?cat:"kuendigungen"/u',$s,$m,PREG_SET_ORDER)){
  $i=0; foreach($m as $x){ if($i++>=30)break; echo "  ".$x[1]." → ".$x[2]."\n"; }
} else echo "  (cat:'kuendigungen' generic doc bulunamadi)\n";

echo "\n=== cat:'vertraege' (generic) docs — key→isim (SIZINTI suphelisi) ===\n";
if(preg_match_all('/(\w+):\{ic:"[^"]*",n:"([^"]{0,50})"[^}]{0,120}?cat:"vertraege"/u',$s,$m,PREG_SET_ORDER)){
  $i=0; foreach($m as $x){ if($i++>=30)break; echo "  ".$x[1]." → ".$x[2]."\n"; }
} else echo "  (cat:'vertraege' generic doc bulunamadi)\n";

echo "\n=== k:'kuendigung_*' anahtarlar (ham 12) — key→isim ===\n";
if(preg_match_all('/(kuendigung_\w+):\{ic:"[^"]*",n:"([^"]{0,50})"/u',$s,$m,PREG_SET_ORDER)){
  foreach($m as $x){ echo "  ".$x[1]." → ".$x[2]."\n"; }
} else echo "  (yapı farkli)\n";

echo "\n=== homepage kart ureten fonksiyon (showCatDocs / cat kart tik) ===\n";
$p=strpos($s,'function showCatDocs'); if($p===false)$p=strpos($s,'showCatDocs=function'); if($p===false)$p=strpos($s,'showCatDocs');
if($p!==false) echo "  [".$p."] ".w($s,$p,10,240)."\n";

echo "\n=== 'kündigen' iceren KART/kategori etiketleri (n:\"... kündigen\") ===\n";
if(preg_match_all('/n:"([^"]*kündigen[^"]*)"/u',$s,$m)){ foreach(array_unique($m[1]) as $t) echo "  n:\"".$t."\"\n"; }
echo "\nBITTI.\n";
