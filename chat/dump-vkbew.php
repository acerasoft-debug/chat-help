<?php
/* dump-vkbew (READ-ONLY) — 'Verträge kündigen' karti: CH_VKBEW tam davranis +
   ornek kündigung doc yapisi + CAT_DOCS/CAT_LABELS + aktif hukuk(DE) fesih seti.
   KULLANIM: pull2.php?key=...&files=dump-vkbew.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-vkbew (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
function w($s,$p,$b,$a){ return trim(preg_replace('/[ \t]+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

/* 1) CH_VKBEW tam */
echo "=== [1] CH_VKBEW script TAM ===\n";
$p=strpos($s,'id="ch-vkbew-js"');
if($p!==false){ $so=strrpos(substr($s,0,$p),'<script'); $eo=strpos($s,'</script>',$p);
  $blk=substr($s,$so,$eo-$so+9);
  echo (strlen($blk)>5000? substr($blk,0,5000)."\n…[+".strlen($blk)."]":$blk)."\n"; }
else echo "(CH_VKBEW yok)\n";

/* 2) ornek kündigung doc yapisi (tam) */
echo "\n=== [2] ornek at_fitness_kuendigung TAM (sablon yapisi) ===\n";
$p=strpos($s,'at_fitness_kuendigung:{');
if($p!==false){ $e=strpos($s,'q:[',$p); $e2=strpos($s,']}',$e); echo w($s,$p,0,($e2-$p+2))."\n"; }
else echo "(yok)\n";

/* 3) CAT_DOCS tanimi + populasyon */
echo "\n=== [3] CAT_DOCS tanimi/populasyon ===\n";
$off=0;$c=0; while(($p=strpos($s,'CAT_DOCS',$off))!==false && $c<8){ echo "  [".$p."] ".w($s,$p,6,80)."\n"; $off=$p+7;$c++; }

/* 4) CAT_LABELS vertraege/kuendigungen */
echo "\n=== [4] CAT_LABELS ('vertraege'/'kuendigungen'/'Verträge kündigen') ===\n";
foreach(['CAT_LABELS','vertraege:','kuendigungen:'] as $k){
  $p=strpos($s,$k); if($p!==false) echo "  ".$k." [".$p."] ".w($s,$p,4,120)."\n";
}

/* 5) DE fesih seti var mi? de_*_kuendigung / kuendigung_* cat'leri */
echo "\n=== [5] DE fesih dokumanlari (de_/kuendigung_ + cat) ===\n";
echo "  de_*_kuendigung ham say: ".preg_match_all('/\bde_\w*kuendig\w*/i',$s)."\n";
if(preg_match_all('/(kuendigung_\w+):\{ic:"[^"]*",n:"([^"]{0,45})"[^}]{0,140}?cat:"([a-z_]+)"/u',$s,$m,PREG_SET_ORDER)){
  echo "  kuendigung_* → cat:\n"; foreach($m as $x){ echo "    ".$x[1]." [".$x[3]."] ".$x[2]."\n"; }
} else echo "  (kuendigung_* cat yapisini bulamadim)\n";

/* 6) aktif hukuk kodu (LAW/CCODE) + 'vertraege' karti onclick tam */
echo "\n=== [6] 'Verträge kündigen' wcat karti (tam) ===\n";
$p=strpos($s,'Verträge kündigen');
if($p!==false){ $cs=strrpos(substr($s,0,$p),'<div class="wcat"'); echo "  ".w($s,$cs,0,320)."\n"; }
echo "\nBITTI.\n";
