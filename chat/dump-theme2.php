<?php
/* dump-theme2 (READ-ONLY) — iki tema scriptinin TAM metni + ch-navy CSS ezme.
   Tek atislik dogru duzeltme icin kesin anchor'lar.
   KULLANIM: pull2.php?key=...&files=dump-theme2.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-theme2 (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");

/* 1) UST selector: ch-navy-js TAM ---- */
echo "=== [1] ch-navy-js (UST selector) TAM ===\n";
$p=strpos($s,'<script id="ch-navy-js">');
if($p!==false){ $e=strpos($s,'</script>',$p); echo substr($s,$p,$e-$p+9)."\n"; }
else echo "(bulunamadi)\n";

/* 2) ALT selector: chtheme-btn buton enjeksiyonu iceren script TAM ---- */
echo "\n\n=== [2] ALT selector (chtheme-btn HTML enjekte eden) script TAM ===\n";
$b=strpos($s,'chtheme-btn" data-t="anthracite"');   /* gercek buton HTML'i */
if($b!==false){
  $so=strrpos(substr($s,0,$b),'<script');
  $eo=strpos($s,'</script>',$b);
  echo "  [script @".$so." → ".$eo.", uzunluk ".($eo-$so+9)."]\n";
  $blk=substr($s,$so,$eo-$so+9);
  if(strlen($blk)>4000){ echo substr($blk,0,1500)."\n   …[KISALTILDI ".(strlen($blk)-3000)." bayt]…\n".substr($blk,-1500)."\n"; }
  else echo $blk."\n";
} else echo "(buton HTML bulunamadi)\n";

/* 3) ch-navy CSS ezmesi var mi? ---- */
echo "\n\n=== [3] 'ch-navy' CSS kurallari (data-chtheme'i eziyor mu?) ===\n";
$off=0;$c=0;
while(($q=strpos($s,'ch-navy',$off))!==false && $c<14){
  $ln=substr($s,max(0,$q-40),120);
  /* yalniz CSS selector baglami (html.ch-navy / .ch-navy{ ) */
  if(preg_match('/\.ch-navy/',substr($s,max(0,$q-6),12)) || strpos(substr($s,$q-6,20),'.ch-navy')!==false){
    echo "  [".$q."] …".trim(preg_replace('/\s+/',' ',$ln))."…\n"; $c++;
  }
  $off=$q+7;
}
echo "  (html.ch-navy tam say: ".substr_count($s,'.ch-navy').")\n";

/* 4) ALT selector 'sec' hangi wrap'e ekleniyor + insertBefore satiri ---- */
echo "\n=== [4] ALT selector insertBefore/wrap satiri ===\n";
$q=strpos($s,'insertBefore(sec');
if($q!==false) echo "  …".trim(preg_replace('/\s+/',' ',substr($s,max(0,$q-260),340)))."…\n";
echo "\nBITTI.\n";
